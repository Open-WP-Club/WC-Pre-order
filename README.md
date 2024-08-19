# WooCommerce Pre-Order Plugin

## Description

This plugin enhances WooCommerce with pre-order functionality, allowing for specific promo code requirements on certain products and automatic application of promo codes after purchase.

## Features

- Adds a Pre-Order settings page under the WooCommerce tab.
- Allows admins to specify product IDs that require a promo code.
- Allows admins to specify the promo codes that must be applied.
- Prevents users from proceeding to checkout if the required promo code is not applied to the specified products.
- Supports auto-apply promo codes for future purchases.
- Automatically applies saved promo codes to the cart for eligible users.
- Provides usage limits for auto-apply promo codes.

## Installation

1. Download the plugin files and upload them to the `/wp-content/plugins/woocommerce-preorder-plugin` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce -> Pre-Order to configure the plugin settings.

## Usage

1. Go to WooCommerce -> Pre-Order in the WordPress admin area.
2. Enter the product IDs (comma-separated) that require a promo code.
3. Enter the promo codes that must be applied for the specified products.
4. Configure auto-apply promo codes settings:
   - Enter the auto-apply promo codes (one per line).
   - Specify the product ID that triggers the auto-apply promo.
   - Set the usage limit for auto-apply promos (optional).
5. Save the settings.

## Auto-Apply Promo Codes

- When a customer purchases the specified auto-apply trigger product, they will receive a promo code for their next purchase.
- The promo code will be automatically applied to their cart on their next visit.
- Usage limits can be set to restrict the number of times a user can benefit from auto-apply promos.

## Notes

- The plugin automatically adds auto-apply promo codes to the general list of promo codes.
- Promo codes are case-insensitive.
- The checkout button will be hidden if a required promo code is not applied to the cart containing pre-order products.

## License

This plugin is licensed under the GPL-2.0 license.
