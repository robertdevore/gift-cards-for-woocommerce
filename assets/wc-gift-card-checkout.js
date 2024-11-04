jQuery(document).ready(function ($) {
    $('#apply_gift_card_button').on('click', function () {
        const code = $('#gift_card_code').val();

        if (!code) {
            $('#gift_card_feedback').text('Please enter a gift card code.');
            return;
        }

        $.ajax({
            url: gift_card_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'apply_gift_card',
                security: gift_card_ajax.nonce,
                code: code,
            },
            success: function (response) {
                if (response.success) {
                    $('#gift_card_feedback').css('color', 'green').text(response.data.message);
                    $('#gift_card_code').prop('disabled', true);
                    $('#apply_gift_card_button').prop('disabled', true);

                    // Recalculate totals (WooCommerce function)
                    $('body').trigger('update_checkout');
                } else {
                    $('#gift_card_feedback').css('color', 'red').text(response.data.message);
                }
            }
        });
    });
});
