<?php

/**
 * Plugin Name: WooCommerce Pre-Order Plugin
 * Description: Pre-Ordering products using specific promo codes and auto-applying promos after purchase.
 * Plugin URI:  https://github.com/MrGKanev/WC-Pre-order
 * Version:     0.0.8
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

define('WC_PREORDER_VERSION', '0.0.8');
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
    private $logger;

    public function __construct()
    {

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_check_cart_items', array($this, 'validate_cart'));
        add_action('woocommerce_before_checkout_form', array($this, 'validate_cart'));
        add_action('wp_ajax_check_promo_code', array($this, 'check_promo_code_ajax'));
        add_action('wp_ajax_nopriv_check_promo_code', array($this, 'check_promo_code_ajax'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_order_status_completed', array($this, 'check_and_apply_auto_promo'), 10, 1);
        add_action('woocommerce_before_cart', array($this, 'apply_auto_promo_to_cart'), 10);
        add_action('woocommerce_before_checkout_form', array($this, 'apply_auto_promo_to_cart'), 10);
        add_action('woocommerce_before_cart', array($this, 'apply_saved_promo_to_cart'), 10);
        add_action('woocommerce_before_checkout_form', array($this, 'apply_saved_promo_to_cart'), 10);
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

        add_settings_field(
            'wc_preorder_auto_apply_promo',
            __('Auto-apply Promo Code', 'wc-preorder-plugin'),
            array($this, 'auto_apply_promo_field'),
            'wc-preorder',
            'wc_preorder_main_section'
        );

        add_settings_field(
            'wc_preorder_auto_apply_product_id',
            __('Product ID for Auto-apply', 'wc-preorder-plugin'),
            array($this, 'auto_apply_product_id_field'),
            'wc-preorder',
            'wc_preorder_main_section'
        );

        add_settings_field(
            'wc_preorder_auto_apply_limit',
            __('Auto-apply Usage Limit', 'wc-preorder-plugin'),
            array($this, 'auto_apply_limit_field'),
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
        if (isset($input['auto_apply_promo'])) {
            $sanitized_input['auto_apply_promo'] = sanitize_text_field($input['auto_apply_promo']);
        }
        if (isset($input['auto_apply_product_id'])) {
            $sanitized_input['auto_apply_product_id'] = intval($input['auto_apply_product_id']);
        }
        if (isset($input['auto_apply_limit'])) {
            $sanitized_input['auto_apply_limit'] = intval($input['auto_apply_limit']);
        }
        return $sanitized_input;
    }

    public function section_text()
    {
        echo '<p>' . __('Enter your settings below:', 'wc-preorder-plugin') . '</p>';
    }

    public function product_ids_field()
    {
        $options = $this->get_plugin_options();
        $product_ids = isset($options['product_ids']) ? $options['product_ids'] : '';
        echo '<input type="text" id="wc_preorder_product_ids" name="wc_preorder_settings[product_ids]" value="' . esc_attr($product_ids) . '" />';
    }

    public function promo_codes_field()
    {
        $options = $this->get_plugin_options();
        $promo_codes = isset($options['promo_codes']) ? $options['promo_codes'] : '';
        echo '<input type="text" id="wc_preorder_promo_codes" name="wc_preorder_settings[promo_codes]" value="' . esc_attr($promo_codes) . '" />';
        echo '<p class="description">' . __('Enter multiple promo codes separated by commas.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_promo_field()
    {
        $options = $this->get_plugin_options();
        $auto_apply_promo = isset($options['auto_apply_promo']) ? $options['auto_apply_promo'] : '';
        echo '<input type="text" id="wc_preorder_auto_apply_promo" name="wc_preorder_settings[auto_apply_promo]" value="' . esc_attr($auto_apply_promo) . '" />';
        echo '<p class="description">' . __('Enter the promo code to be auto-applied.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_product_id_field()
    {
        $options = $this->get_plugin_options();
        $auto_apply_product_id = isset($options['auto_apply_product_id']) ? $options['auto_apply_product_id'] : '';
        echo '<input type="text" id="wc_preorder_auto_apply_product_id" name="wc_preorder_settings[auto_apply_product_id]" value="' . esc_attr($auto_apply_product_id) . '" />';
        echo '<p class="description">' . __('Enter the product ID that triggers the auto-apply promo.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_limit_field()
    {
        $options = $this->get_plugin_options();
        $auto_apply_limit = isset($options['auto_apply_limit']) ? $options['auto_apply_limit'] : '';
        echo '<input type="number" id="wc_preorder_auto_apply_limit" name="wc_preorder_settings[auto_apply_limit]" value="' . esc_attr($auto_apply_limit) . '" min="0" />';
        echo '<p class="description">' . __('Enter the maximum number of times a user can use the auto-apply promo. Leave blank or 0 for unlimited use.', 'wc-preorder-plugin') . '</p>';
    }

    public function validate_cart()
    {
        $options = $this->get_plugin_options();
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
        $options = $this->get_plugin_options();
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

    public function check_and_apply_auto_promo($order_id)
    {
        $this->logger->info("check_and_apply_auto_promo called for order " . $order_id, array('source' => 'wc-preorder'));

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logger->error("Order not found", array('source' => 'wc-preorder'));
            return;
        }

        $options = $this->get_plugin_options();
        $auto_apply_promo = isset($options['auto_apply_promo']) ? $options['auto_apply_promo'] : '';
        $auto_apply_product_id = isset($options['auto_apply_product_id']) ? intval($options['auto_apply_product_id']) : 0;
        $auto_apply_limit = isset($options['auto_apply_limit']) ? intval($options['auto_apply_limit']) : 0;

        $this->logger->info("Auto apply promo: " . $auto_apply_promo, array('source' => 'wc-preorder'));
        $this->logger->info("Auto apply product ID: " . $auto_apply_product_id, array('source' => 'wc-preorder'));
        $this->logger->info("Auto apply limit: " . $auto_apply_limit, array('source' => 'wc-preorder'));

        if (empty($auto_apply_promo) || empty($auto_apply_product_id)) {
            $this->logger->error("Auto apply promo or product ID is empty", array('source' => 'wc-preorder'));
            return;
        }

        $product_in_order = false;

        // Check if the specified product is in the order
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $auto_apply_product_id) {
                $product_in_order = true;
                break;
            }
        }

        $this->logger->info("Product in order: " . ($product_in_order ? "Yes" : "No"), array('source' => 'wc-preorder'));

        if ($product_in_order) {
            $user_id = $order->get_user_id();
            if ($user_id) {
                // Check if the auto-apply promo code exists
                $coupon = new \WC_Coupon($auto_apply_promo);
                if (!$coupon->get_id()) {
                    $this->logger->error("Auto-apply promo code does not exist: " . $auto_apply_promo, array('source' => 'wc-preorder'));
                    return;
                }

                // Check usage limit
                $usage_count = get_user_meta($user_id, 'wc_preorder_auto_apply_usage', true);
                $usage_count = $usage_count ? intval($usage_count) : 0;

                if ($auto_apply_limit > 0 && $usage_count >= $auto_apply_limit) {
                    $this->logger->info("User has reached the auto-apply usage limit", array('source' => 'wc-preorder'));
                    return;
                }

                // Store the promo code as user meta for later use
                update_user_meta($user_id, 'wc_preorder_next_purchase_promo', $auto_apply_promo);
                update_user_meta($user_id, 'wc_preorder_auto_apply_usage', $usage_count + 1);
                $this->logger->info("Auto-apply promo code saved for user " . $user_id . ": " . $auto_apply_promo, array('source' => 'wc-preorder'));
            } else {
                $this->logger->error("No user ID found for order", array('source' => 'wc-preorder'));
            }
        }
    }

    public function apply_saved_promo_to_cart()
    {
        // Check if user is logged in
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Check if user has a saved promo code
        $saved_promo = get_user_meta($user_id, 'wc_preorder_next_purchase_promo', true);
        if (empty($saved_promo)) {
            return;
        }

        // Check if the promo code is already applied
        if (!in_array($saved_promo, WC()->cart->get_applied_coupons())) {
            // Apply the promo code
            $result = WC()->cart->apply_coupon($saved_promo);

            if ($result) {
                $this->logger->info("Successfully applied saved promo code: " . $saved_promo, array('source' => 'wc-preorder'));
                // Remove the saved promo code to prevent multiple uses
                delete_user_meta($user_id, 'wc_preorder_next_purchase_promo');

                // Add a notice to inform the user
                wc_add_notice(sprintf(__('Promo code %s has been automatically applied to your cart.', 'wc-preorder-plugin'), $saved_promo), 'success');
            } else {
                $this->logger->error("Failed to apply saved promo code: " . $saved_promo, array('source' => 'wc-preorder'));
            }
        }
    }

    private function get_plugin_options()
    {
        $transient_key = 'wc_preorder_settings';
        $options = get_transient($transient_key);

        if (false === $options) {
            $options = get_option('wc_preorder_settings', array());
            set_transient($transient_key, $options, HOUR_IN_SECONDS);
        }

        return $options;
    }
}

// Instantiate the class
new WC_PreOrder();