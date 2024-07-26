(function($) {
    var debounceTimer;

    function convertPromoCodeToUppercase() {
        $(document).on('input', 'input[name="coupon_code"]', function() {
            this.value = this.value.toUpperCase();
        });
    }

    function checkPromoCode() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            $.ajax({
                url: wc_preorder_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_promo_code'
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        }, 300); // 300ms debounce
    }

    function updateCheckoutButton(data) {
        var $checkoutButton = $('.checkout-button');
        var $errorMessage = $('.woocommerce-error').filter(function() {
            return $(this).text().includes('To continue with pre-order products, please apply one of the required promo codes.');
        });

        if (data.required) {
            $checkoutButton.toggle(data.valid);
            $errorMessage.toggle(!data.valid);
        } else {
            $checkoutButton.show();
            $errorMessage.remove();
        }
    }

    $(function() {
        convertPromoCodeToUppercase();
        checkPromoCode();

        $(document.body).on('applied_coupon removed_coupon updated_cart_totals', checkPromoCode);
    });
})(jQuery);