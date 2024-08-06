<?php

namespace WCPreOrder\Core;

class PreOrderExpiration
{
    public function init()
    {
        add_action('woocommerce_scheduled_sales', [$this, 'check_expired_pre_orders']);
    }

    public function check_expired_pre_orders()
    {
        // Implementation to check and handle expired pre-orders
    }
}
