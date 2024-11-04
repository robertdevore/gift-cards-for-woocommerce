jQuery(document).ready(function($) {
    $('.wp-list-table').on('click', '.delete-gift-card', function(e) {
        e.preventDefault();

        var button = $(this);
        var code = button.data('code');
        var nonce = button.data('nonce');

        if ( confirm( gift_cards_ajax.confirm_message ) ) {
            $.ajax({
                url: gift_cards_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_gift_card',
                    code: code,
                    nonce: nonce
                },
                success: function( response ) {
                    if ( response.success ) {
                        // Remove the row from the table
                        button.closest('tr').fadeOut( 300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert( response.data );
                    }
                },
                error: function() {
                    alert( gift_cards_ajax.error_message );
                }
            });
        }
    });
});
