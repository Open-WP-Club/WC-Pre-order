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
                    action: 'check_promo_code',
                    nonce: wc_preorder_ajax.nonce
                },
                success: function(response) {
                    // Handle the response
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        }, 300);
    }

    $(function() {
        convertPromoCodeToUppercase();
        $(document.body).on('applied_coupon removed_coupon updated_cart_totals', checkPromoCode);
    });
})(jQuery);