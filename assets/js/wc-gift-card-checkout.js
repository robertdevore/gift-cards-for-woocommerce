jQuery(function($) {
    console.log('Gift card script loaded'); // Debugging statement

    // Toggle the input field for editing
    $('#edit-gift-card-amount').on('click', function(e) {
        e.preventDefault();

        // Show or hide the input field
        $('#gift_card_amount_input').toggle();

        // Toggle button text between 'Edit' and 'Close'
        $(this).text($(this).text() === 'Edit' ? 'Close' : 'Edit');

        // If closing, update the visible gift card amount in the blue box
        if ($(this).text() === 'Edit') {
            var newAmount = parseFloat($('#gift_card_amount_input').val()).toFixed(2);

            // Update the displayed amount if input is valid
            if (!isNaN(newAmount) && newAmount > 0) {
                $('#gift_card_amount_display').text(newAmount);
            }
        }
    });

    // Update both checkout and displayed balance when the gift card amount input is changed
    $('#gift_card_amount_input').on('input', function() {
        var amount = parseFloat($(this).val()).toFixed(2);

        // Update the displayed amount in real-time
        if (!isNaN(amount) && amount > 0) {
            $('#gift_card_amount_display').text(amount);
        }

        // Re-trigger checkout calculations
        $('body').trigger('update_checkout');
    });

    // Pass the updated gift card amount to the checkout process on form submission
    $('form.checkout').on('checkout_place_order', function() {
        var amount = parseFloat($('#gift_card_amount_input').val());
        $('input[name="gift_card_amount"]').val(amount);
    });
});
