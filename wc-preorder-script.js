jQuery(document).ready(function($) {
    function convertPromoCodeToUppercase() {
        $('input[name="coupon_code"]').on('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    convertPromoCodeToUppercase();

    function checkPromoCode() {
        $.ajax({
            url: wc_preorder_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_promo_code'
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    if (response.data.required) {
                        if (response.data.valid) {
                            $('.checkout-button').show();
                            $('.woocommerce-error').each(function() {
                                if ($(this).text().includes('To continue with pre-order products, please apply one of the required promo codes.')) {
                                    $(this).remove();
                                }
                            });
                        } else {
                            $('.checkout-button').hide();
                        }
                    } else {
                        $('.checkout-button').show();
                    }
                    
                    // Display debug info
                    var debugInfo = '<div id="wc-preorder-debug">' +
                        '<h4>Debug Info:</h4>' +
                        '<pre>' + JSON.stringify(response.data.debug_info, null, 2) + '</pre>' +
                        '<h4>Applied Coupons:</h4>' +
                        '<pre>' + JSON.stringify(response.data.applied_coupons, null, 2) + '</pre>' +
                        '<h4>Required Promo Codes:</h4>' +
                        '<pre>' + JSON.stringify(response.data.required_promo_codes, null, 2) + '</pre>' +
                        '</div>';
                    $('#wc-preorder-debug').remove();
                    $('body').append(debugInfo);
                }
            }
        });
    }

    checkPromoCode();
    $(document.body).on('applied_coupon removed_coupon updated_cart_totals', checkPromoCode);

    $(document.body).on('applied_coupon removed_coupon', convertPromoCodeToUppercase);
});