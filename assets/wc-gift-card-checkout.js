jQuery(function($) {
    console.log('Gift card script loaded'); // Debugging statement

    $('form.checkout').on('change', 'input[name="apply_gift_card_balance"]', function() {
        console.log('Gift card checkbox changed'); // Debugging statement
        $('body').trigger('update_checkout');
    });
});
