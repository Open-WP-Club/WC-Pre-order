<?php

/**
 * Plugin Name:             WooCommerce Pre-Order Plugin
 * Description:             Pre-Ordering products using specific promo codes.
 * Plugin URI:              https://github.com/MrGKanev/WC-Pre-order
 * Description:             Displays users' last order dates and allows changing roles based on order inactivity.
 * Version:                 0.0.3
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 MIT
 * Requires at least:       6.4
 * Requires PHP:            7.4
 * WC requires at least:    7.1
 * WC tested up to:         9.1.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Add a Pre-Order page under the WooCommerce tab
add_action('admin_menu', 'wc_preorder_admin_menu');

function wc_preorder_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        __('Pre-Order', 'wc-preorder-plugin'),
        __('Pre-Order', 'wc-preorder-plugin'),
        'manage_woocommerce',
        'wc-preorder',
        'wc_preorder_settings_page'
    );
}

function wc_preorder_settings_page()
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

add_action('admin_init', 'wc_preorder_admin_init');

function wc_preorder_admin_init()
{
    register_setting('wc_preorder_settings_group', 'wc_preorder_settings');

    add_settings_section(
        'wc_preorder_main_section',
        __('Main Settings', 'wc-preorder-plugin'),
        'wc_preorder_section_text',
        'wc-preorder'
    );

    add_settings_field(
        'wc_preorder_product_ids',
        __('Product IDs (comma separated)', 'wc-preorder-plugin'),
        'wc_preorder_product_ids_field',
        'wc-preorder',
        'wc_preorder_main_section'
    );

    add_settings_field(
        'wc_preorder_promo_codes',
        __('Promo Codes (comma separated)', 'wc-preorder-plugin'),
        'wc_preorder_promo_codes_field',
        'wc-preorder',
        'wc_preorder_main_section'
    );
}

function wc_preorder_section_text()
{
    echo '<p>' . __('Enter your settings below:', 'wc-preorder-plugin') . '</p>';
}

function wc_preorder_product_ids_field()
{
    $options = get_option('wc_preorder_settings');
    $product_ids = isset($options['product_ids']) ? $options['product_ids'] : '';
    echo '<input type="text" id="wc_preorder_product_ids" name="wc_preorder_settings[product_ids]" value="' . esc_attr($product_ids) . '" />';
}

function wc_preorder_promo_codes_field()
{
    $options = get_option('wc_preorder_settings');
    $promo_codes = isset($options['promo_codes']) ? $options['promo_codes'] : '';
    echo '<input type="text" id="wc_preorder_promo_codes" name="wc_preorder_settings[promo_codes]" value="' . esc_attr($promo_codes) . '" />';
    echo '<p class="description">' . __('Enter multiple promo codes separated by commas.', 'wc-preorder-plugin') . '</p>';
}

// Hook into WooCommerce cart validation and checkout page to hide/show the proceed button
add_action('woocommerce_check_cart_items', 'check_product_promo_code_and_toggle_checkout_button');
add_action('woocommerce_before_checkout_form', 'check_product_promo_code_and_toggle_checkout_button');

function check_product_promo_code_and_toggle_checkout_button()
{
    // Get the promo codes from settings
    $options = get_option('wc_preorder_settings');
    $promo_codes_string = isset($options['promo_codes']) ? $options['promo_codes'] : '';
    $required_promo_codes = array_map('trim', explode(',', $promo_codes_string));

    // Convert required promo codes to lowercase
    $required_promo_codes = array_map('strtolower', $required_promo_codes);

    // Get the product IDs that require a promo code from the settings
    $product_ids_string = isset($options['product_ids']) ? $options['product_ids'] : '';
    $required_product_ids = array_map('trim', explode(',', $product_ids_string));

    // Check if any of the required product IDs are in the cart
    $found_required_product = false;
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (in_array($cart_item['product_id'], $required_product_ids)) {
            $found_required_product = true;
            break;
        }
    }

    if ($found_required_product) {
        // Check if any of the required promo codes are applied
        $applied_coupons = WC()->cart->get_applied_coupons();

        // Convert applied coupons to lowercase
        $applied_coupons = array_map('strtolower', $applied_coupons);

        $valid_promo_code_applied = false;
        foreach ($required_promo_codes as $promo_code) {
            if (in_array($promo_code, $applied_coupons)) {
                $valid_promo_code_applied = true;
                break;
            }
        }

        if (!$valid_promo_code_applied) {
            // If a required product is found and no valid promo code is applied, display an error message
            $error_message = 'To continue, please apply one of the required promo codes.';
            wc_add_notice($error_message, 'error');

            // Hide the Proceed to Checkout button
            remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        } else {
            // If a valid promo code is applied, make sure the Proceed to Checkout button is visible
            add_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        }
    }
}

// Add AJAX action to check promo code validity
add_action('wp_ajax_check_promo_code', 'check_promo_code_ajax');
add_action('wp_ajax_nopriv_check_promo_code', 'check_promo_code_ajax');

function check_promo_code_ajax()
{
    $options = get_option('wc_preorder_settings');
    $promo_codes_string = isset($options['promo_codes']) ? $options['promo_codes'] : '';
    $required_promo_codes = array_map('trim', explode(',', $promo_codes_string));

    // Convert required promo codes to lowercase
    $required_promo_codes = array_map('strtolower', $required_promo_codes);

    $applied_coupons = WC()->cart->get_applied_coupons();

    // Convert applied coupons to lowercase
    $applied_coupons = array_map('strtolower', $applied_coupons);

    $valid_promo_code_applied = false;
    foreach ($required_promo_codes as $promo_code) {
        if (in_array($promo_code, $applied_coupons)) {
            $valid_promo_code_applied = true;
            break;
        }
    }

    wp_send_json_success(['valid' => $valid_promo_code_applied]);
}

// Enqueue JavaScript for dynamic button visibility
add_action('wp_enqueue_scripts', 'wc_preorder_enqueue_scripts');

function wc_preorder_enqueue_scripts()
{
    wp_enqueue_script('wc-preorder-script', plugin_dir_url(__FILE__) . 'wc-preorder-script.js', array('jquery'), '1.0', true);
    wp_localize_script('wc-preorder-script', 'wc_preorder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

?>