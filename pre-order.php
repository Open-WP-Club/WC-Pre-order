<?php

/**
 * Plugin Name: WooCommerce Pre-Order Plugin
 * Description: Adds a Pre-Order page in the WooCommerce tab and checks for promo codes on specific products.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: wc-preorder-plugin
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

// Hook into WooCommerce cart validation and checkout page to hide the proceed button
add_action('woocommerce_check_cart_items', 'check_product_promo_code_and_hide_checkout_button');
add_action('woocommerce_before_checkout_form', 'check_product_promo_code_and_hide_checkout_button');

function check_product_promo_code_and_hide_checkout_button()
{
    // Check if a promo code is applied
    $applied_coupons = WC()->cart->get_applied_coupons();
    if (empty($applied_coupons)) {
        // Get the product IDs that require a promo code from the settings
        $options = get_option('wc_preorder_settings');
        $product_ids_string = isset($options['product_ids']) ? $options['product_ids'] : '';
        $required_product_ids = array_map('trim', explode(',', $product_ids_string));

        // Check if any of the required product IDs are in the cart
        $found_required_product = false;
        $required_product_id = null;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (in_array($cart_item['product_id'], $required_product_ids)) {
                $found_required_product = true;
                $required_product_id = $cart_item['product_id'];
                break;
            }
        }

        // If a required product is found and no promo code is applied, display an error message
        if ($found_required_product) {
            // Get the product name dynamically using the product ID
            $product_name = get_the_title($required_product_id);
            $error_message = sprintf('To continue, please apply a promo code for %s.', $product_name);
            wc_add_notice($error_message, 'error');

            // Hide the Proceed to Checkout button
            remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        }
    }
}

?>