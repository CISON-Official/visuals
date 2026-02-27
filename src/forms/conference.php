<?php

function enqueue_registration_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '1.0', true);
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    
    wp_localize_script('jquery', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('registration_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_registration_scripts');


function ajax_add_to_cart_handler() {
    check_ajax_referer('registration_nonce', 'nonce');
    
    $product_id = intval($_POST['product_id']);
    $quantity = 1;
    
    if ($product_id) {
        WC()->cart->empty_cart(0);
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($cart_item_key) {
            wp_send_json_success(array('message' => 'Added to cart'));
        } else {
            wp_send_json_error('Failed to add to cart');
        }
    }
    wp_die();
}
add_action('wp_ajax_add_to_cart_dynamic', 'ajax_add_to_cart_handler');
add_action('wp_ajax_nopriv_add_to_cart_dynamic', 'ajax_add_to_cart_dynamic');

function ajax_load_wc_checkout() {
    check_ajax_referer('registration_nonce', 'nonce');
    
    if (!WC()->cart->is_empty()) {
        ob_start();
        echo do_shortcode('[woocommerce_checkout]');
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    } else {
        wp_send_json_error('Cart is empty. Please select registration option.');
    }
    wp_die();
}
add_action('wp_ajax_load_wc_checkout', 'ajax_load_wc_checkout');
add_action('wp_ajax_nopriv_load_wc_checkout', 'ajax_load_wc_checkout');

function registration_form_with_checkout_shortcode() {
    ob_start();
    // ajax_clear_cart();
    ?>
    <div class="registration-container">
        <form id="registration-form" method="post" novalidate>
            <div class="mb-4">
                <h5>Member ID <span class="text-danger">*</span> (Required)</h5>
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="member_id"
                               pattern="[0-9]{8}" title="Enter valid NSA Member ID (3-10 chars)">
                        <small class="form-text text-muted">Your NSA Member ID (Leave empty if you are not a Member of CISON)</small>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h5>Registering for <span class="text-danger">*</span> (Required)</h5>
                <select class="form-select" name="registering_for" id="registering_for" required>
                    <option value="">Choose registration option...</option>
                    <option value="workshop">Pre-Conference Workshop only (Early Bird)</option>
                    <option value="conference">3rd Annual Conference only (On-Site) (Early Bird)</option>
                    <option value="virtual">3rd Annual Conference only (Virtual) (Early Bird)</option>
                    <option value="both">3rd Annual Conference (On-Site) and Pre-Conference Workshop (Early Bird)</option>
                    <option value="virtual_both">3rd Annual Conference (Virtual) and Pre-Conference Workshop (Early Bird)</option>
                </select>
            </div>

            <hr class="my-5">

            <h4>Please let's get your Information</h4>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Title <span class="text-danger">*</span></label>
                    <select class="form-select" name="title" required>
                        <option value="">Select</option>
                        <option>Mr</option><option>Mrs</option><option>Ms</option>
                        <option>Dr</option><option>Prof</option><option>Engr</option>
                        <option>Rev</option><option>Hon</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" required>
                </div>
                <div class="col-md-6">
                    <label>Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="col-md-6">
                    <label>Confirm Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="confirm_email" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Phone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="phone" required>
                </div>
                <div class="col-md-6">
                    <label>Occupation</label>
                    <input type="text" class="form-control" name="occupation">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Organisation</label>
                    <input type="text" class="form-control" name="organisation">
                </div>
            </div>

            <div class="mb-4">
                <h6>Address <span class="text-danger">*</span></h6>
                <div class="row">
                    <div class="col-md-12 mb-2"><input type="text" class="form-control" name="street" placeholder="Street Address" required></div>
                    <div class="col-md-4 mb-2"><input type="text" class="form-control" name="city" placeholder="City/Town" required></div>
                    <div class="col-md-4 mb-2"><input type="text" class="form-control" name="state" placeholder="State" required></div>
                    <div class="col-md-2 mb-2"><input type="text" class="form-control" name="postcode" placeholder="Postcode" required></div>
                    <div class="col-md-2 mb-2">
                        <select class="form-select" name="country" required>
                            <option value="">Country</option>
                            <option value="NG" selected>Nigeria</option>
                            <option value="GH">Ghana</option>
                            <option value="KE">Kenya</option>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Gender <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" required>
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                        <option>Prefer Not to Answer</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label>How did you hear about this event?</label>
                <select class="form-select" name="hear_about">
                    <option value="">Select</option>
                    <option>Social Media</option>
                    <option>Google</option>
                    <option>Word of Mouth</option>
                    <option>From a Friend</option>
                    <option>News Media</option>
                    <option>Other</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100" id="pay-submit" disabled>
                Proceed to Payment & Submit
            </button>
        </form>

        <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Secure Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="checkout-container">Loading checkout...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .registration-container { max-width: 900px; margin: 0 auto; padding: 20px; }
    .text-danger { color: #dc3545 !important; }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    #checkout-container .woocommerce { padding: 20px; }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('registration_wc_checkout', 'registration_form_with_checkout_shortcode');


function add_registration_script() {
    $script = "
    jQuery(document).ready(function($) {
        // UPDATE THESE PRODUCT IDs FROM YOUR WOOCCOMMERCE PRODUCTS
        var conference_id = 6623;
        var workshop_id = 6647;
        var virtual_id = 6625;
        var workshop_conference_id = 12670;
        var workshop_virtual_id = 12672;

        
        // Auto-add to cart when selection changes
        $('#registering_for').on('change', function() {

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
            console.log('Adding to cart: '+ product_id);

            var postData = {
                action: 'add_to_cart_dynamic',
                product_id: product_id
            };
            
            if (ajax_object.nonce && ajax_object.user_logged_in !== false) {
                postData.nonce = ajax_object.nonce;
            }
            $.post(ajax_object.ajax_url, postData, function(response) {
                if (response.success) {
                    $('.cart-status').html('✅ Item added! Ready to pay');
                    $('#pay-submit').prop('disabled', false);
                } else {
                    $('.cart-status').html('❌ Error: ' + (response.data || 'Try again'));
                    $('#pay-submit').prop('disabled', true);
                }
                console.log('Response:',response);
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $('.cart-status').html('❌ Network error - try again');
                $('#pay-submit').prop('disabled', true);
                console.log('AJAX Error:', error, xhr, status);
            });
        }
        
        
        // Form submit → Open checkout modal
        $('#registration-form').on('submit', function(e) {
            e.preventDefault();
            
            if ($('#pay-submit').prop('disabled')) {
                alert('Please select registration option and add to cart first');
                return;
            }
            
            // Load checkout
            $.post(ajax_object.ajax_url, {
                action: 'load_wc_checkout',
                nonce: ajax_object.nonce
            }, function(response) {
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
        });
        
        // After payment modal closes, submit form
        $('#checkoutModal').on('hidden.bs.modal', function() {
            if (window.paymentCompleted) {
                $('#registration-form')[0].submit();
            }
        });
        
        // Detect payment success (WooCommerce event)
        $(document.body).on('order_received updated_wc_div', function() {
            window.paymentCompleted = true;
        });
    });
    ";
    wp_add_inline_script('bootstrap-js', $script);
}
add_action('wp_enqueue_scripts', 'add_registration_script');

function ajax_clear_cart() {
    // check_ajax_referer('registration_nonce', 'nonce');
    if ( ! function_exists( 'WC' ) ) {
        return '0';
    }

    if (!WC()->cart) {
        WC()->initialize_cart();
    }
    if (WC()->cart) {
        $count = WC()->cart->get_cart_contents_count();
        if ($count > 0) {
            WC()->cart->empty_cart();
            do_action('woocommerce_cart_emptied');
        }
    }
    
    // wp_send_json_success('Cart cleared');
}


function handle_registration_submit() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
        
        $data = array(
            'member_id' => sanitize_text_field($_POST['member_id']),
            'registering_for' => sanitize_text_field($_POST['registering_for']),
            'title' => sanitize_text_field($_POST['title']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'occupation' => sanitize_text_field($_POST['occupation']),
            'organisation' => sanitize_text_field($_POST['organisation']),
            'street' => sanitize_text_field($_POST['street']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'country' => sanitize_text_field($_POST['country']),
            'gender' => sanitize_text_field($_POST['gender']),
            'hear_about' => sanitize_text_field($_POST['hear_about'])
        );
        
        $order_data = array(
            'Member ID' => $data['member_id'],
            'Full Name' => $data['title'] . ' ' . $data['first_name'] . ' ' . $data['last_name'],
            'Email' => $data['email'],
            'Phone' => $data['phone']
        );
        
        $latest_order = wc_get_orders(array('limit' => 1, 'status' => 'wc-completed', 'orderby' => 'date', 'order' => 'DESC'));
        if (!empty($latest_order)) {
            $order = $latest_order[0];
            $order->add_order_note('Registration: ' . json_encode($order_data));
        }
        
        wp_redirect(add_query_arg('registered', 'success', wp_get_referer()));
        exit;
    }
}
add_action('wp_ajax_clear_cart', 'ajax_clear_cart');
add_action('wp_ajax_nopriv_clear_cart', 'ajax_clear_cart');

add_action('init', 'handle_registration_submit');
?>
