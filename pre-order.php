<?php

/**
 * Plugin Name:             WooCommerce Pre-Order Plugin
 * Description:             Pre-Ordering products using specific promo codes.
 * Plugin URI:              https://github.com/MrGKanev/WC-Pre-order
 * Description:             Displays users' last order dates and allows changing roles based on order inactivity.
 * Version:                 0.0.1
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 MIT
 * Requires at least:       6.4
 * Requires PHP:            7.4
 * WC requires at least:    6.0
 * WC tested up to:         9.1.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

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
        'wc_preorder_promo_code',
        __('Promo Code', 'wc-preorder-plugin'),
        'wc_preorder_promo_code_field',
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

function wc_preorder_promo_code_field()
{
    $options = get_option('wc_preorder_settings');
    $promo_code = isset($options['promo_code']) ? $options['promo_code'] : '';
    echo '<input type="text" id="wc_preorder_promo_code" name="wc_preorder_settings[promo_code]" value="' . esc_attr($promo_code) . '" />';
}

// Hook into WooCommerce cart validation and checkout page to hide the proceed button
add_action('woocommerce_check_cart_items', 'check_product_promo_code_and_hide_checkout_button');
add_action('woocommerce_before_checkout_form', 'check_product_promo_code_and_hide_checkout_button');

function check_product_promo_code_and_hide_checkout_button()
{
    // Get the promo code from settings
    $options = get_option('wc_preorder_settings');
    $required_promo_code = isset($options['promo_code']) ? $options['promo_code'] : '';

    // Check if the required promo code is applied
    $applied_coupons = WC()->cart->get_applied_coupons();
    if (!in_array($required_promo_code, $applied_coupons)) {
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

        // If a required product is found and the required promo code is not applied, display an error message
        if ($found_required_product) {
            $error_message = 'To continue, please apply the required promo code.';
            wc_add_notice($error_message, 'error');

            // Hide the Proceed to Checkout button
            remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        }
    }
}

?>