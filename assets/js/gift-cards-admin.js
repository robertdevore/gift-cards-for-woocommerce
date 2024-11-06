jQuery(document).ready(function($) {
    // Handle Edit button click
    $('.edit-gift-card').on('click', function(e) {
        e.preventDefault();
        var code = $(this).data('code');
        var nonce = $(this).data('nonce');

        // Fetch gift card data via AJAX
        $.ajax({
            url: gift_cards_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'get_gift_card_data',
                code: code,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Populate the form fields
                    $('#gift-card-code').val(response.data.code);
                    $('#gift-card-balance').val(response.data.balance);
                    $('#gift-card-expiration-date').val(response.data.expiration_date);
                    $('#gift-card-recipient-email').val(response.data.recipient_email);
                    $('#gift-card-sender-name').val(response.data.sender_name);
                    $('#gift-card-message').val(response.data.message);

                    // Open the modal with additional options
                    $('#gift-card-edit-modal').dialog({
                        modal: true,
                        width: 500,
                        title: 'Edit Gift Card',
                        dialogClass: 'gift-card-dialog',
                        position: { my: "center", at: "center", of: window }
                    });
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(gift_cards_ajax.error_message);
            }
        });
    });

    // Handle form submission
    $('#gift-card-edit-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
            url: gift_cards_ajax.ajax_url,
            method: 'POST',
            data: formData + '&action=update_gift_card',
            success: function(response) {
                if (response.success) {
                    // Replace the form content with the success message
                    $('#gift-card-edit-form').html('<p class="success-message">' + response.data + '</p>');
                    // After 1 second, close the modal and reload the page
                    setTimeout(function() {
                        $('#gift-card-edit-modal').dialog('close');
                        location.reload(); // Reload the page to reflect changes
                    }, 1000); // 1000 milliseconds = 1 second
                } else {
                    // Display error message inside the form
                    $('#gift-card-edit-form').prepend('<p class="error-message">' + response.data + '</p>');
                }                
            },
            error: function() {
                alert(gift_cards_ajax.error_message);
            }
        });
    });

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
