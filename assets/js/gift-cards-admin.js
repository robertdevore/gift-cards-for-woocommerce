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
                    $('#gift-card-code').val(response.data.code);
                    $('#gift-card-balance').val(response.data.balance);
                    $('#gift-card-expiration-date').val(response.data.expiration_date);
                    $('#gift-card-recipient-email').val(response.data.recipient_email);
                    $('#gift-card-sender-name').val(response.data.sender_name);
                    $('#gift-card-message').val(response.data.message);

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
                    $('#gift-card-edit-modal').dialog('close');
                    refreshGiftCardTable();
                } else {
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
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(gift_cards_ajax.error_message);
                }
            });
        }
    });

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

    let batch_size = 100;
    let offset = 0;

    // Batch Export
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