jQuery(document).ready(function($) {
    function checkPromoCode() {
        $.ajax({
            url: wc_preorder_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_promo_code'
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.valid) {
                        $('.checkout-button').show();
                    } else {
                        $('.checkout-button').hide();
                    }
                }
            }
        });
    }

    // Check promo code validity on page load and when coupons are applied or removed
    checkPromoCode();
    $(document.body).on('applied_coupon removed_coupon updated_cart_totals', checkPromoCode);
});