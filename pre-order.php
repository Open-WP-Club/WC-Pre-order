<?php

/**
 * Plugin Name: WooCommerce Pre-Order Plugin
 * Description: Pre-Ordering products using specific promo codes and auto-applying promos after purchase.
 * Version:     0.1.0
 * Author:      Gabriel Kanev
 * License:     MIT
 * Text Domain: wc-preorder-plugin
 */

namespace WCPreOrder;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Autoloader
require_once __DIR__ . '/vendor/autoload.php';

use WCPreOrder\Admin\SettingsPage;
use WCPreOrder\Admin\PreOrderDashboard;
use WCPreOrder\Core\PreOrderManager;
use WCPreOrder\Core\PromoCodeManager;
use WCPreOrder\Core\PreOrderExpiration;
use WCPreOrder\Notifications\EmailNotifications;

class Plugin
{
    private $settings_page;
    private $pre_order_dashboard;
    private $pre_order_manager;
    private $promo_code_manager;
    private $pre_order_expiration;
    private $email_notifications;

    public function __construct()
    {
        $this->settings_page = new SettingsPage();
        $this->pre_order_dashboard = new PreOrderDashboard();
        $this->pre_order_manager = new PreOrderManager();
        $this->promo_code_manager = new PromoCodeManager();
        $this->pre_order_expiration = new PreOrderExpiration();
        $this->email_notifications = new EmailNotifications();

        $this->init();
    }

    private function init()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('woocommerce_loaded', [$this, 'woocommerce_loaded']);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('wc-preorder-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function woocommerce_loaded()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        $this->settings_page->init();
        $this->pre_order_dashboard->init();
        $this->pre_order_manager->init();
        $this->promo_code_manager->init();
        $this->pre_order_expiration->init();
        $this->email_notifications->init();
    }

    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p>' . sprintf(__('WooCommerce Pre-Order Plugin requires WooCommerce to be installed and active. You can download %s here.', 'wc-preorder-plugin'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</p></div>';
    }
}

new Plugin();
