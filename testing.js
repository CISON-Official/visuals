jQuery(document).ready(function ($) {
    // UPDATE THESE PRODUCT IDs FROM YOUR WOOCCOMMERCE PRODUCTS
    var conference_id = 6623;
    var workshop_id = 6647;
    var virtual_id = 6625;
    var workshop_conference_id = 12670;
    var workshop_virtual_id = 12672;


    // Auto-add to cart when selection changes
    $('#registering_for').on('change', function () {

        $.post(ajax_object.ajax_url, {
            action: 'clear_cart',
            nonce: ajax_object.nonce
        });

        var selection = $(this).val();
        $('.cart-status').text('Adding to cart...');
        $('#pay-submit').prop('disabled', true);

        if (selection === 'conference') {
            addToCart(conference_id);
        } else if (selection === 'workshop') {
            addToCart(workshop_id);
        } else if (selection === 'both') {
            addToCart(workshop_conference_id);
        } else if (selection === 'virtual') {
            addToCart(virtual_id);
        } else if (selection === 'virtual_both') {
            addToCart(workshop_virtual_id);
        } else {
            $('.cart-status').text('Please select registration option');
            $('#pay-submit').prop('disabled', true);
        }
    });

    function addToCart(product_id) {
        console.log('Adding to cart: ' + product_id);

        var postData = {
            action: 'add_to_cart_dynamic',
            product_id: product_id
        };

        if (ajax_object.nonce && ajax_object.user_logged_in !== false) {
            postData.nonce = ajax_object.nonce;
        }
        $.post(ajax_object.ajax_url, postData, function (response) {
            if (response.success) {
                $('.cart-status').html('✅ Item added! Ready to pay');
                $('#pay-submit').prop('disabled', false);
            } else {
                $('.cart-status').html('❌ Error: ' + (response.data || 'Try again'));
                $('#pay-submit').prop('disabled', true);
            }
            console.log('Response:', response);
            if (ajax_object.user_logged_in) {
                console.log('User is logged in');
            } else {
                console.log('User is not logged in')
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX Error:', error);
            $('.cart-status').html('❌ Network error - try again');
            $('#pay-submit').prop('disabled', true);
            console.log('AJAX Error:', error, xhr, status);
        });
    }


    // Form submit → Open checkout modal
    let valid = true;
    $(this).find('[required]').each(function () {
        if (!$(this).val().trim()) {
            $(this).addClass('is-invalid');
            valid = false;
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    if (!valid || $('#pay-submit').prop('disabled')) {
        alert('Please complete all required fields and select registration.');
        return;
    }
    $.post(ajax_object.ajax_url, $(this).serialize() + '&action=save_nsa_registration_on_payment_success', function (response) {
        if (response.success) {
            // Redirect to checkout with registration ID
            console.log('Data saved: ', response);
        } else {
            alert('Save error: ' + response.data);
        }
    });
    // Load checkout
    $.post(ajax_object.ajax_url, {
        action: 'load_wc_checkout',
        nonce: ajax_object.nonce
    }, function (response) {
        if (response.success) {
            $('#checkout-container').html(response.data.html);
            $('#checkoutModal').modal('show');

            // Re-init WooCommerce checkout
            $(document.body).trigger('update_checkout');
            $(document.body).trigger('wc_fragment_refresh');
        } else {
            alert('Error: ' + response.data);
        }
    });

    // After payment modal closes, submit form
    $('#checkoutModal').on('hidden.bs.modal', function () {
        if (window.paymentCompleted) {
            $('#registration-form')[0].submit();
        }
    });

    // Detect payment success (WooCommerce event)
    $(document.body).on('order_received updated_wc_div', function () {
        window.paymentCompleted = true;
    });
});