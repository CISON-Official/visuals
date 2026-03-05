<?php
// ============================================================
// 1. ENQUEUE SCRIPTS
// ============================================================
function enqueue_registration_scripts()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '1.0', true);
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');

    wp_localize_script('jquery', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('registration_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_registration_scripts');


// ============================================================
// 2. ADD TO CART (unchanged)
// ============================================================
function ajax_add_to_cart_handler()
{
    if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }

    $product_id = intval($_POST['product_id']);
    $quantity = 1;

    if (!$product_id) {
        wp_send_json_error('No product ID');
        wp_die();
    }
    if (!class_exists('WooCommerce') || !WC()) {
        wp_send_json_error('WooCommerce unavailable');
        wp_die();
    }
    if (!WC()->cart) {
        WC()->initialize_cart();
    }
    if (!WC()->cart) {
        wp_send_json_error('Cart unavailable');
        wp_die();
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->exists()) {
        wp_send_json_error("Product $product_id not found");
        wp_die();
    }

    WC()->cart->empty_cart();
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);

    if ($cart_item_key) {
        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'message' => 'Added to cart',
            'cart_count' => WC()->cart->get_cart_contents_count()
        ));
    } else {
        wp_send_json_error('Failed to add product');
    }
    wp_die();
}
add_action('wp_ajax_add_to_cart_dynamic', 'ajax_add_to_cart_handler');
add_action('wp_ajax_nopriv_add_to_cart_dynamic', 'ajax_add_to_cart_handler');


// ============================================================
// 3. LOAD CHECKOUT (unchanged)
// ============================================================
function ajax_load_wc_checkout()
{
    check_ajax_referer('registration_nonce', 'nonce');

    if (!WC()->cart->is_empty()) {
        ob_start();
        echo do_shortcode('[woocommerce_checkout]');
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    } else {
        wp_send_json_error('Cart is empty. Please select a registration option.');
    }
    wp_die();
}
add_action('wp_ajax_load_wc_checkout', 'ajax_load_wc_checkout');
add_action('wp_ajax_nopriv_load_wc_checkout', 'ajax_load_wc_checkout');


// ============================================================
// 4. CLEAR CART
// ============================================================
function ajax_clear_cart()
{
    if (!function_exists('WC')) {
        wp_send_json_success('WC not loaded');
        wp_die();
    }
    if (!WC()->cart) {
        WC()->initialize_cart();
    }
    if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
        WC()->cart->empty_cart();
        do_action('woocommerce_cart_emptied');
    }
    wp_send_json_success('Cart cleared');
    wp_die();
}
add_action('wp_ajax_clear_cart', 'ajax_clear_cart');
add_action('wp_ajax_nopriv_clear_cart', 'ajax_clear_cart');


// ============================================================
// 5. ✅ SAVE REGISTRATION — the fixed/new handler
// ============================================================
function ajax_save_registration()
{
    // Verify nonce (security)
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Security check failed');
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    // Sanitize every field before touching the DB
    $data = array(
        'member_id' => sanitize_text_field($_POST['member_id'] ?? ''),
        'registering_for' => sanitize_text_field($_POST['registering_for'] ?? ''),
        'title' => sanitize_text_field($_POST['title'] ?? ''),
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'occupation' => sanitize_text_field($_POST['occupation'] ?? ''),
        'organisation' => sanitize_text_field($_POST['organisation'] ?? ''),
        'street' => sanitize_text_field($_POST['street'] ?? ''),
        'city' => sanitize_text_field($_POST['city'] ?? ''),
        'state' => sanitize_text_field($_POST['state'] ?? ''),
        'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
        'country' => sanitize_text_field($_POST['country'] ?? 'NG'),
        'gender' => sanitize_text_field($_POST['gender'] ?? ''),
        'hear_about' => sanitize_text_field($_POST['hear_about'] ?? ''),
        'payment_status' => 'pending',
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    );

    // Basic required-field validation on the server side
    $required = ['registering_for', 'title', 'first_name', 'last_name', 'email', 'phone', 'street', 'city', 'state', 'country', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            wp_send_json_error("Missing required field: $field");
            wp_die();
        }
    }

    // Validate email
    if (!is_email($data['email'])) {
        wp_send_json_error('Invalid email address');
        wp_die();
    }

    // Insert into DB
    $result = $wpdb->insert($table_name, $data);

    if ($result !== false) {
        wp_send_json_success(array(
            'registration_id' => $wpdb->insert_id,
            'message' => 'Registration saved successfully',
        ));
    } else {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    wp_die();
}
add_action('wp_ajax_save_registration', 'ajax_save_registration');
add_action('wp_ajax_nopriv_save_registration', 'ajax_save_registration');


// ============================================================
// 6. UPDATE ORDER WITH REGISTRATION ID (called after payment)
// ============================================================
function link_registration_to_order($order_id)
{
    $registration_id = WC()->session ? WC()->session->get('nsa_registration_id') : 0;
    if (!$registration_id)
        return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    $wpdb->update(
        $table_name,
        array('order_id' => $order_id, 'payment_status' => 'paid'),
        array('id' => $registration_id),
        array('%d', '%s'),
        array('%d')
    );

    WC()->session->__unset('nsa_registration_id');
}
add_action('woocommerce_payment_complete', 'link_registration_to_order');


// ============================================================
// 7. SHORTCODE — form HTML (updated JS only)
// ============================================================
function registration_form_with_checkout_shortcode()
{
    ob_start();
    ?>
    <div class="registration-container">
        <form id="registration-form" method="post" novalidate>

            <div class="mb-4">
                <h5>Member ID <span class="text-danger">*</span> (Required)</h5>
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="member_id" pattern="[0-9]{8}"
                            title="Enter valid NSA Member ID (3-10 chars)">
                        <small class="form-text text-muted">Your NSA Member ID (Leave empty if you are not a Member of
                            CISON)</small>
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
                    <option value="virtual_both">3rd Annual Conference (Virtual) and Pre-Conference Workshop (Early Bird)
                    </option>
                </select>
            </div>

            <hr class="my-5">

            <h4>Please let's get your Information</h4>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Title <span class="text-danger">*</span></label>
                    <select class="form-select" name="title" required>
                        <option value="">Select</option>
                        <option>Mr</option>
                        <option>Mrs</option>
                        <option>Ms</option>
                        <option>Dr</option>
                        <option>Prof</option>
                        <option>Engr</option>
                        <option>Rev</option>
                        <option>Hon</option>
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
                    <div class="col-md-12 mb-2"><input type="text" class="form-control" name="street"
                            placeholder="Street Address" required></div>
                    <div class="col-md-4 mb-2"><input type="text" class="form-control" name="city" placeholder="City/Town"
                            required></div>
                    <div class="col-md-4 mb-2"><input type="text" class="form-control" name="state" placeholder="State"
                            required></div>
                    <div class="col-md-2 mb-2"><input type="text" class="form-control" name="postcode"
                            placeholder="Postcode" required></div>
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

            <p class="cart-status text-muted"></p>

            <button type="submit" class="btn btn-primary btn-lg w-100" id="pay-submit" disabled>
                Proceed to Payment &amp; Submit
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
        .registration-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #checkout-container .woocommerce {
            padding: 20px;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('registration_wc_checkout', 'registration_form_with_checkout_shortcode');


// ============================================================
// 8. INLINE JS — updated to call save_registration first
// ============================================================
function add_registration_script()
{
    $script = "
    jQuery(document).ready(function($) {

        var conference_id        = 6623;
        var workshop_id          = 6647;
        var virtual_id           = 6625;
        var workshop_conference_id = 12670;
        var workshop_virtual_id  = 12672;

        // ── Auto-add to cart on selection change ──────────────
        $('#registering_for').on('change', function() {
            $.post(ajax_object.ajax_url, { action: 'clear_cart', nonce: ajax_object.nonce });

            var selection = $(this).val();
            $('.cart-status').text('Adding to cart...');
            $('#pay-submit').prop('disabled', true);

            var productMap = {
                conference:   conference_id,
                workshop:     workshop_id,
                both:         workshop_conference_id,
                virtual:      virtual_id,
                virtual_both: workshop_virtual_id
            };

            if (productMap[selection]) {
                addToCart(productMap[selection]);
            } else {
                $('.cart-status').text('Please select a registration option');
            }
        });

        function addToCart(product_id) {
            $.post(ajax_object.ajax_url, {
                action:     'add_to_cart_dynamic',
                product_id: product_id,
                nonce:      ajax_object.nonce
            }, function(response) {
                if (response.success) {
                    $('.cart-status').html('✅ Item added! Ready to pay');
                    $('#pay-submit').prop('disabled', false);
                } else {
                    $('.cart-status').html('❌ Error: ' + (response.data || 'Try again'));
                    $('#pay-submit').prop('disabled', true);
                }
            }).fail(function() {
                $('.cart-status').html('❌ Network error - try again');
                $('#pay-submit').prop('disabled', true);
            });
        }

        // ── Form submit ───────────────────────────────────────
        $('#registration-form').on('submit', function(e) {
            e.preventDefault();

            // 1. Client-side validation
            var valid = true;
            $(this).find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            if ($('#registering_for').val() === '') {
                alert('Please select a registration option first.');
                return;
            }

            var email        = $('[name=email]').val().trim();
            var confirmEmail = $('[name=confirm_email]').val().trim();
            if (email !== confirmEmail) {
                alert('Email addresses do not match.');
                $('[name=confirm_email]').addClass('is-invalid');
                return;
            }

            if (!valid) {
                alert('Please complete all required fields.');
                return;
            }

            // 2. ✅ SAVE REGISTRATION TO DB FIRST
            var formData = $(this).serialize() + '&action=save_registration&nonce=' + ajax_object.nonce;

            $('#pay-submit').prop('disabled', true).text('Saving...');

            $.post(ajax_object.ajax_url, formData, function(response) {
                if (response.success) {
                    console.log('Registration saved. ID:', response.data.registration_id);

                    // 3. Then load the WooCommerce checkout modal
                    $.post(ajax_object.ajax_url, {
                        action: 'load_wc_checkout',
                        nonce:  ajax_object.nonce
                    }, function(checkoutResponse) {
                        if (checkoutResponse.success) {
                            $('#checkout-container').html(checkoutResponse.data.html);
                            $('#checkoutModal').modal('show');
                            $(document.body).trigger('update_checkout');
                            $(document.body).trigger('wc_fragment_refresh');
                        } else {
                            alert('Checkout error: ' + checkoutResponse.data);
                        }
                        $('#pay-submit').prop('disabled', false).text('Proceed to Payment & Submit');
                    });

                } else {
                    alert('Could not save registration: ' + response.data);
                    $('#pay-submit').prop('disabled', false).text('Proceed to Payment & Submit');
                }
            }).fail(function() {
                alert('Network error. Please try again.');
                $('#pay-submit').prop('disabled', false).text('Proceed to Payment & Submit');
            });
        });

        // ── Detect payment success ────────────────────────────
        $('#checkoutModal').on('hidden.bs.modal', function() {
            if (window.paymentCompleted) { location.reload(); }
        });

        $(document.body).on('order_received updated_wc_div', function() {
            window.paymentCompleted = true;
        });
    });
    ";
    wp_add_inline_script('bootstrap-js', $script);
}
add_action('wp_enqueue_scripts', 'add_registration_script');