<?php

/**
 * Plugin Name: WooCommerce Pre-Order Plugin
 * Description: Pre-Ordering products using specific promo codes.
 * Plugin URI:  https://github.com/MrGKanev/WC-Pre-order
 * Version:     0.0.6
 * Author:      Gabriel Kanev
 * Author URI:  https://gkanev.com
 * License:     MIT
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * WC requires at least: 7.1
 * WC tested up to:     9.1.2
 */

namespace WCPreOrder;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('WC_PREORDER_VERSION', '0.0.6');
define('WC_PREORDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PREORDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class WC_PreOrder
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_check_cart_items', array($this, 'validate_cart'));
        add_action('woocommerce_before_checkout_form', array($this, 'validate_cart'));
        add_action('wp_ajax_check_promo_code', array($this, 'check_promo_code_ajax'));
        add_action('wp_ajax_nopriv_check_promo_code', array($this, 'check_promo_code_ajax'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Pre-Order', 'wc-preorder-plugin'),
            __('Pre-Order', 'wc-preorder-plugin'),
            'manage_woocommerce',
            'wc-preorder',
            array($this, 'settings_page')
        );
    }

    public function settings_page()
    {
?>
        <div class="wrap">
            <h2><?php _e('Pre-Order Settings', 'wc-preorder-plugin'); ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_preorder_settings_group');
                do_settings_sections('wc-preorder');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    public function register_settings()
    {
        register_setting('wc_preorder_settings_group', 'wc_preorder_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'wc_preorder_main_section',
            __('Main Settings', 'wc-preorder-plugin'),
            array($this, 'section_text'),
            'wc-preorder'
        );

        add_settings_field(
            'wc_preorder_product_ids',
            __('Product IDs (comma separated)', 'wc-preorder-plugin'),
            array($this, 'product_ids_field'),
            'wc-preorder',
            'wc_preorder_main_section'
        );

        add_settings_field(
            'wc_preorder_promo_codes',
            __('Promo Codes (comma separated)', 'wc-preorder-plugin'),
            array($this, 'promo_codes_field'),
            'wc-preorder',
            'wc_preorder_main_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized_input = array();
        if (isset($input['product_ids'])) {
            $sanitized_input['product_ids'] = implode(',', array_filter(array_map('intval', explode(',', $input['product_ids']))));
        }
        if (isset($input['promo_codes'])) {
            $promo_codes = array_map('trim', explode(',', $input['promo_codes']));
            $sanitized_input['promo_codes'] = implode(',', array_map('strtoupper', $promo_codes));
        }
        return $sanitized_input;
    }

    public function section_text()
    {
        echo '<p>' . __('Enter your settings below:', 'wc-preorder-plugin') . '</p>';
    }

    public function product_ids_field()
    {
        $options = get_option('wc_preorder_settings');
        $product_ids = isset($options['product_ids']) ? $options['product_ids'] : '';
        echo '<input type="text" id="wc_preorder_product_ids" name="wc_preorder_settings[product_ids]" value="' . esc_attr($product_ids) . '" />';
    }

    public function promo_codes_field()
    {
        $options = get_option('wc_preorder_settings');
        $promo_codes = isset($options['promo_codes']) ? $options['promo_codes'] : '';
        echo '<input type="text" id="wc_preorder_promo_codes" name="wc_preorder_settings[promo_codes]" value="' . esc_attr($promo_codes) . '" />';
        echo '<p class="description">' . __('Enter multiple promo codes separated by commas.', 'wc-preorder-plugin') . '</p>';
    }

    public function validate_cart()
    {
        $options = get_option('wc_preorder_settings');
        $required_promo_codes = array_map('trim', explode(',', $options['promo_codes'] ?? ''));
        $required_product_ids = array_map('intval', explode(',', $options['product_ids'] ?? ''));

        $found_required_product = $this->cart_has_required_product($required_product_ids);

        if ($found_required_product) {
            $valid_promo_code_applied = $this->check_valid_promo_code($required_promo_codes);

            if (!$valid_promo_code_applied) {
                wc_add_notice(__('To continue with pre-order products, please apply one of the required promo codes.', 'wc-preorder-plugin'), 'error');
                remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
            } else {
                wc_clear_notices();
                add_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
            }
        } else {
            wc_clear_notices();
            add_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        }
    }

    private function cart_has_required_product($required_product_ids)
    {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (in_array($cart_item['product_id'], $required_product_ids)) {
                return true;
            }
        }
        return false;
    }

    private function check_valid_promo_code($required_promo_codes)
    {
        $applied_coupons = WC()->cart->get_applied_coupons();
        foreach ($required_promo_codes as $promo_code) {
            if (in_array(strtoupper($promo_code), array_map('strtoupper', $applied_coupons))) {
                return true;
            }
        }
        return false;
    }

    public function check_promo_code_ajax()
    {
        $options = get_option('wc_preorder_settings');
        $required_promo_codes = array_map('trim', explode(',', $options['promo_codes'] ?? ''));
        $required_product_ids = array_map('intval', explode(',', $options['product_ids'] ?? ''));

        $found_required_product = $this->cart_has_required_product($required_product_ids);

        if ($found_required_product) {
            $valid_promo_code_applied = $this->check_valid_promo_code($required_promo_codes);
            wp_send_json_success([
                'valid' => $valid_promo_code_applied,
                'required' => true,
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'required_promo_codes' => $required_promo_codes
            ]);
        } else {
            wp_send_json_success(['valid' => true, 'required' => false]);
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('wc-preorder-script', WC_PREORDER_PLUGIN_URL . 'wc-preorder-script.js', array('jquery'), WC_PREORDER_VERSION, true);
        wp_localize_script('wc-preorder-script', 'wc_preorder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}

new WC_PreOrder();
