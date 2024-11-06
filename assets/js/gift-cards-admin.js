jQuery(document).ready(function($) {
    // Function to reset the modal to its initial state
    function resetModal() {
        // Show the form and hide messages
        $('#gift-card-edit-form').show();
        $('.message').removeClass('success-message error-message').hide().text('');
    }

    // Handle the Edit Gift Card button click
    $('.edit-gift-card').on('click', function(e) {
        e.preventDefault();

        var code = $(this).data('code');
        var nonce = $(this).data('nonce');

        // Reset the modal to initial state
        resetModal();

        // Populate the modal with existing gift card data via AJAX
        $.ajax({
            url: gift_cards_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_gift_card_data',
                code: code,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var giftCard = response.data;
                    $('#gift-card-code').val(giftCard.code);
                    $('#gift-card-balance').val(giftCard.balance);
                    $('#gift-card-expiration-date').val(giftCard.expiration_date);
                    $('#gift-card-recipient-email').val(giftCard.recipient_email);
                    $('#gift-card-sender-name').val(giftCard.sender_name);
                    $('#gift-card-message').val(giftCard.message);

                    // Open the modal
                    $('#gift-card-edit-modal').dialog({
                        modal: true,
                        title: 'Edit Gift Card',
                        width: 600,
                        close: function() {
                            resetModal();
                        }
                    });
                } else {
                    // Display error message inside the modal
                    $('.message').addClass('error-message').text(response.data).show();

                    // Open the modal to show the error
                    $('#gift-card-edit-modal').dialog({
                        modal: true,
                        title: 'Error',
                        width: 600,
                        close: function() {
                            resetModal();
                        }
                    });
                }
            },
            error: function() {
                // Display generic error message inside the modal
                $('.message').addClass('error-message').text(gift_cards_ajax.error_message).show();

                // Open the modal to show the error
                $('#gift-card-edit-modal').dialog({
                    modal: true,
                    title: 'Error',
                    width: 600,
                    close: function() {
                        resetModal();
                    }
                });
            }
        });
    });

    // Handle the Save Changes button in the modal
    $('#gift-card-edit-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true).text('Saving...');

        var formData = form.serialize();

        // Hide previous messages
        $('.message').removeClass('success-message error-message').hide().text('');

        $.ajax({
            url: gift_cards_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=update_gift_card',
            success: function(response) {
                submitButton.prop('disabled', false).text('Save Changes');
                if (response.success) {
                    // Hide the form
                    $('#gift-card-edit-form').hide();

                    // Display the success message
                    $('.message').addClass('success-message').text(response.data).show();

                    // Optionally, close the modal after a short delay to allow the user to read the message
                    setTimeout(function() {
                        $('#gift-card-edit-modal').dialog('close');

                        // Refresh the gift cards table to reflect the changes
                        refreshGiftCardTable();
                    }, 2000); // 2-second delay
                } else {
                    // Display the error message inside the modal
                    $('.message').addClass('error-message').text(response.data).show();
                }
            },
            error: function() {
                submitButton.prop('disabled', false).text('Save Changes');
                // Display generic error message inside the modal
                $('.message').addClass('error-message').text(gift_cards_ajax.error_message).show();
            }
        });
    });

    // Handle the Delete Gift Card button click
    $('.wp-list-table').on('click', '.delete-gift-card', function(e) {
        e.preventDefault();
        var button = $(this);
        var code = button.data('code');
        var nonce = button.data('nonce');

        if (confirm(gift_cards_ajax.confirm_message)) {
            $.ajax({
                url: gift_cards_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_gift_card',
                    code: code,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        // Optionally, display the error message in the modal or as a notification
                        $('.message').addClass('error-message').text(response.data).show();

                        // Optionally, open the modal to show the error
                        $('#gift-card-edit-modal').dialog({
                            modal: true,
                            title: 'Error',
                            width: 600,
                            close: function() {
                                resetModal();
                            }
                        });
                    }
                },
                error: function() {
                    alert(gift_cards_ajax.error_message);
                }
            });
        }
    });

    // Function to refresh the Gift Cards list table
    function refreshGiftCardTable() {
        $.ajax({
            url: window.location.href,
            type: 'GET',
            success: function(response) {
                var newTableBody = $(response).find('.wp-list-table tbody').html();
                $('.wp-list-table tbody').html(newTableBody);
            },
            error: function() {
                alert('Error refreshing the table.');
            }
        });
    }

    // Batch Export
    let batch_size = 100;
    let offset = 0;

    $('#export_gift_cards_btn').on('click', function() {
        batchExportGiftCards();
    });

    function batchExportGiftCards() {
        $.ajax({
            url: gift_cards_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'export_gift_cards_in_batches',
                offset: offset,
                batch_size: batch_size,
                _ajax_nonce: gift_cards_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.complete) {
                    window.location.href = response.data.file_url;
                } else if (response.success) {
                    offset += batch_size;
                    batchExportGiftCards();
                } else {
                    alert(response.data.error || 'Error exporting gift cards.');
                }
            }
        });
    }

    // Batch Import
    $('#import_gift_cards_btn').on('click', function() {
        let fileInput = $('#gift_card_csv')[0];
        if (fileInput.files.length === 0) {
            alert('Please select a CSV file.');
            return;
        }

        let file = fileInput.files[0];
        offset = 0;
        batchImportGiftCards(file);
    });

    function batchImportGiftCards(file) {
        let formData = new FormData();
        formData.append('action', 'import_gift_cards_in_batches');
        formData.append('offset', offset);
        formData.append('batch_size', batch_size);
        formData.append('_ajax_nonce', gift_cards_ajax.nonce);
        formData.append('file', file);

        $.ajax({
            url: gift_cards_ajax.ajax_url,
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success && response.data.complete) {
                    alert('Import completed successfully.');
                } else if (response.success) {
                    offset += batch_size;
                    batchImportGiftCards(file);
                } else {
                    alert(response.data.error || 'Error importing gift cards.');
                }
            }
        });
    }
});