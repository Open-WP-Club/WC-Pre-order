<?php

/**
 * Plugin Name: WooCommerce Pre-Order Plugin
 * Description: Pre-Ordering products using specific promo codes and auto-applying promos after purchase.
 * Plugin URI:  https://github.com/MrGKanev/WC-Pre-order
 * Version:     0.0.9
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
        register_setting(
            'wc_preorder_settings_group',
            'wc_preorder_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'wc_preorder_main_section',
            __('Main Settings', 'wc-preorder-plugin'),
            array($this, 'section_text'),
            'wc-preorder'
        );

        $this->add_settings_field('product_ids', __('Product IDs (comma separated)', 'wc-preorder-plugin'));
        $this->add_settings_field('promo_codes', __('Promo Codes (comma separated)', 'wc-preorder-plugin'));
        $this->add_settings_field('auto_apply_promos', __('Auto-apply Promo Codes', 'wc-preorder-plugin'));
        $this->add_settings_field('auto_apply_product_id', __('Product ID for Auto-apply', 'wc-preorder-plugin'));
        $this->add_settings_field('auto_apply_limit', __('Auto-apply Usage Limit', 'wc-preorder-plugin'));
    }

    private function add_settings_field($id, $title)
    {
        add_settings_field(
            'wc_preorder_' . $id,
            $title,
            array($this, $id . '_field'),
            'wc-preorder',
            'wc_preorder_main_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized_input = array();
        
        $sanitized_input['product_ids'] = isset($input['product_ids']) ? sanitize_text_field($input['product_ids']) : '';
        $sanitized_input['promo_codes'] = isset($input['promo_codes']) ? sanitize_text_field($input['promo_codes']) : '';
        $sanitized_input['auto_apply_promos'] = isset($input['auto_apply_promos']) ? sanitize_textarea_field($input['auto_apply_promos']) : '';
        $sanitized_input['auto_apply_product_id'] = isset($input['auto_apply_product_id']) ? intval($input['auto_apply_product_id']) : '';
        $sanitized_input['auto_apply_limit'] = isset($input['auto_apply_limit']) ? intval($input['auto_apply_limit']) : '';

        return $sanitized_input;
    }

    public function section_text()
    {
        echo '<p>' . __('Enter your settings below:', 'wc-preorder-plugin') . '</p>';
    }

    public function product_ids_field()
    {
        $this->render_text_field('product_ids');
    }

    public function promo_codes_field()
    {
        $this->render_text_field('promo_codes');
        echo '<p class="description">' . __('Enter multiple promo codes separated by commas.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_promos_field()
    {
        $this->render_textarea_field('auto_apply_promos');
        echo '<p class="description">' . __('Enter the promo codes to be auto-applied, one per line. They will be applied in the order listed.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_product_id_field()
    {
        $this->render_text_field('auto_apply_product_id');
        echo '<p class="description">' . __('Enter the product ID that triggers the auto-apply promo.', 'wc-preorder-plugin') . '</p>';
    }

    public function auto_apply_limit_field()
    {
        $this->render_number_field('auto_apply_limit');
        echo '<p class="description">' . __('Enter the maximum number of times a user can use the auto-apply promo. Leave blank or 0 for unlimited use.', 'wc-preorder-plugin') . '</p>';
    }

    private function render_text_field($id)
    {
        $options = get_option('wc_preorder_settings');
        $value = isset($options[$id]) ? $options[$id] : '';
        echo "<input type='text' id='wc_preorder_{$id}' name='wc_preorder_settings[{$id}]' value='" . esc_attr($value) . "' />";
    }

    private function render_textarea_field($id)
    {
        $options = get_option('wc_preorder_settings');
        $value = isset($options[$id]) ? $options[$id] : '';
        echo "<textarea id='wc_preorder_{$id}' name='wc_preorder_settings[{$id}]' rows='3' cols='50'>" . esc_textarea($value) . "</textarea>";
    }

    private function render_number_field($id)
    {
        $options = get_option('wc_preorder_settings');
        $value = isset($options[$id]) ? $options[$id] : '';
        echo "<input type='number' id='wc_preorder_{$id}' name='wc_preorder_settings[{$id}]' value='" . esc_attr($value) . "' min='0' />";
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
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logger->error("Order not found", array('source' => 'wc-preorder'));
            return;
        }

        $options = $this->get_plugin_options();
        $auto_apply_promos = isset($options['auto_apply_promos']) ? array_filter(explode("\n", $options['auto_apply_promos'])) : array();
        $auto_apply_product_id = isset($options['auto_apply_product_id']) ? intval($options['auto_apply_product_id']) : 0;
        $auto_apply_limit = isset($options['auto_apply_limit']) ? intval($options['auto_apply_limit']) : 0;

        $this->logger->info("Auto apply promos: " . implode(', ', $auto_apply_promos), array('source' => 'wc-preorder'));
        $this->logger->info("Auto apply product ID: " . $auto_apply_product_id, array('source' => 'wc-preorder'));
        $this->logger->info("Auto apply limit: " . $auto_apply_limit, array('source' => 'wc-preorder'));

        if (empty($auto_apply_promos) || empty($auto_apply_product_id)) {
            $this->logger->error("Auto apply promos or product ID is empty", array('source' => 'wc-preorder'));
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
                $usage_count = get_user_meta($user_id, 'wc_preorder_auto_apply_usage', true);
                $usage_count = $usage_count ? intval($usage_count) : 0;

                if ($auto_apply_limit > 0 && $usage_count >= $auto_apply_limit) {
                    $this->logger->info("User has reached the auto-apply usage limit", array('source' => 'wc-preorder'));
                    return;
                }

                // Store the promo codes as user meta for later use
                update_user_meta($user_id, 'wc_preorder_next_purchase_promos', $auto_apply_promos);
                update_user_meta($user_id, 'wc_preorder_auto_apply_usage', $usage_count + 1);
                $this->logger->info("Auto-apply promo codes saved for user " . $user_id . ": " . implode(', ', $auto_apply_promos), array('source' => 'wc-preorder'));
            } else {
                $this->logger->error("No user ID found for order", array('source' => 'wc-preorder'));
            }
        }
    }

    public function apply_saved_promo_to_cart()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $next_promo = get_user_meta($user_id, 'wc_preorder_next_purchase_promo', true);

        if (empty($next_promo)) {
            return;
        }

        $applied_coupons = WC()->cart->get_applied_coupons();

        // Check if the next promo is already applied
        if (in_array($next_promo, $applied_coupons)) {
            return;
        }

        // Remove all existing coupons
        foreach ($applied_coupons as $coupon) {
            WC()->cart->remove_coupon($coupon);
        }

        $result = WC()->cart->apply_coupon($next_promo);

        if ($result) {
            $this->logger->info("Successfully applied auto-apply promo code: " . $next_promo, array('source' => 'wc-preorder'));
            wc_add_notice(sprintf(__('Promo code %s has been automatically applied to your cart.', 'wc-preorder-plugin'), $next_promo), 'success');

            // Delete the user meta since the promo code has been applied
            delete_user_meta($user_id, 'wc_preorder_next_purchase_promo');
        } else {
            $this->logger->error("Failed to apply auto-apply promo code: " . $next_promo, array('source' => 'wc-preorder'));
        }
    }

    private function get_plugin_options()
    {
        return get_option('wc_preorder_settings', array());
    }
}

// Instantiate the class
new WC_PreOrder();