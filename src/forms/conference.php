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
// 2. ADD TO CART
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
// 3. LOAD CHECKOUT
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
// 5. SAVE REGISTRATION
// ============================================================
function ajax_save_registration()
{
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Security check failed');
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    // registering_for arrives as a comma-joined string from JS
    $data = array(
        'member_id' => sanitize_text_field($_POST['member_id'] ?? ''),
        'registering_for' => sanitize_text_field($_POST['registering_for'] ?? ''),
        'title' => sanitize_text_field($_POST['title'] ?? ''),
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'middle_name' => sanitize_text_field($_POST['middle_name'] ?? ''),
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

    $required = ['registering_for', 'title', 'first_name', 'last_name', 'email', 'phone', 'street', 'city', 'state', 'country', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            wp_send_json_error("Missing required field: $field");
            wp_die();
        }
    }

    if (!is_email($data['email'])) {
        wp_send_json_error('Invalid email address');
        wp_die();
    }

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
// 7. SHORTCODE — form HTML
// ============================================================
function registration_form_with_checkout_shortcode()
{
    ob_start();
    ?>
    <div class="registration-container">
        <form id="registration-form" method="post" novalidate>

            <hr class="my-5">
            <h4>Please let us get your Information</h4>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Title <span class="text-danger">*</span></label>
                    <select class="form-select" name="title" required>
                        <option value="">Select</option>
                        <option>Mr.</option>
                        <option>Mrs.</option>
                        <option>Ms.</option>
                        <option>Dr.</option>
                        <option>Prof.</option>
                        <option>Engr.</option>
                        <option>Rev.</option>
                        <option>Hon.</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label>First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" required>
                </div>
                <div class="col-md-4">
                    <label>Middle Name</label>
                    <input type="text" class="form-control" name="middle_name">
                </div>
                <div class="col-md-4">
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
                            placeholder="Postcode"></div>
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
                <h5>Are you a CISON member? <span class="text-danger">*</span></h5>
                <div>
                    <label>
                        <input type="radio" name="is_cison_member" value="yes" onclick="toggleCisonId(true)"> Yes
                    </label>
                    <label class="ms-3">
                        <input type="radio" name="is_cison_member" value="no" onclick="toggleCisonId(false)"> No
                    </label>
                </div>
            </div>

            <div class="mb-4" id="cisonIdField" style="display:none;">
                <h5>CISON ID <span class="text-danger">*</span></h5>
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="member_id" pattern="[0-9]{8}"
                            title="Enter valid CISON ID (8 digits)">
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h5>Registering for <span class="text-danger">*</span></h5>
                <p class="text-muted small mb-2">
                    You may select the Pre-Conference Workshop together with <em>one</em> conference option,
                    or a single conference option on its own. You cannot select both On-Site and Virtual at the same time.
                </p>

                <!-- Pre-conference workshop -->
                <div class="form-check">
                    <input class="" type="checkbox" name="registering_for[]" value="workshop" id="chk_workshop"
                        onchange="handleRegistrationChange()">
                    <label class="form-check-label" for="chk_workshop">
                        Pre-Conference Workshop only
                    </label>
                </div>

                <!-- On-site conference -->
                <div class="form-check">
                    <input class="" type="checkbox" name="registering_for[]" value="conference" id="chk_conference"
                        onchange="handleRegistrationChange()">
                    <label class="form-check-label" for="chk_conference">
                        3rd Annual Conference only (On-Site) (Early Bird)
                    </label>
                </div>

                <!-- Virtual conference -->
                <div class="form-check">
                    <input class="" type="checkbox" name="registering_for[]" value="virtual" id="chk_virtual"
                        onchange="handleRegistrationChange()">
                    <label class="form-check-label" for="chk_virtual">
                        3rd Annual Conference only (Virtual) (Early Bird)
                    </label>
                </div>

                <div id="registration-error" class="text-danger small mt-1" style="display:none;"></div>
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

    <script>
        function toggleCisonId(show) {
            var field = document.getElementById('cisonIdField');
            var input = field.querySelector('input');
            field.style.display = show ? 'block' : 'none';
            input.required = show;
        }

        // ── Enforce mutually exclusive conference options ──────────────────────
        // Rules:
        //   - "conference" (on-site) and "virtual" cannot both be checked
        //   - "workshop" can accompany EITHER conference option, but not stand
        //     in the way of either — it is always freely toggleable
        function handleRegistrationChange() {
            var chkConference = document.getElementById('chk_conference');
            var chkVirtual = document.getElementById('chk_virtual');
            var errDiv = document.getElementById('registration-error');

            // If the user just checked on-site, uncheck virtual (and vice-versa)
            if (chkConference.checked && chkVirtual.checked) {
                // Whichever was most recently checked wins; we detect by which
                // event fired — but since we can't know that easily, we just
                // uncheck virtual when conference is checked and vice-versa.
                // The event always comes from the box that was just toggled ON.
                // We store the "last changed" id via a data attribute.
                var last = document.getElementById('chk_conference').dataset.last === 'true'
                    ? 'conference' : 'virtual';

                if (last === 'conference') {
                    chkVirtual.checked = false;
                } else {
                    chkConference.checked = false;
                }
            }

            errDiv.style.display = 'none';
            errDiv.textContent = '';

            // Trigger cart update
            updateCartFromCheckboxes();
        }

        // Track which conference checkbox was most recently clicked
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('chk_conference').addEventListener('change', function () {
                this.dataset.last = this.checked ? 'true' : 'false';
                document.getElementById('chk_virtual').dataset.last = 'false';
            });
            document.getElementById('chk_virtual').addEventListener('change', function () {
                this.dataset.last = this.checked ? 'true' : 'false';
                document.getElementById('chk_conference').dataset.last = 'false';
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('registration_wc_checkout', 'registration_form_with_checkout_shortcode');


// ============================================================
// 8. INLINE JS — cart logic + form submit
// ============================================================
function add_registration_script()
{
    $script = <<<'JS'
    jQuery(document).ready(function($) {

        // ── Product IDs ──────────────────────────────────────────────────────
        var PRODUCTS = {
            workshop            : 12816,   // workshop only
            conference          : 12817,   // on-site only
            virtual             : 12818,   // virtual only
            workshop_conference : 12670,   // workshop + on-site
            workshop_virtual    : 12672    // workshop + virtual
        };

        // ── Resolve which product to add based on checked boxes ──────────────
        function resolveProduct() {
            var workshop   = $('#chk_workshop').is(':checked');
            var conference = $('#chk_conference').is(':checked');
            var virtual_   = $('#chk_virtual').is(':checked');

            if (workshop && conference)  return PRODUCTS.workshop_conference;
            if (workshop && virtual_)    return PRODUCTS.workshop_virtual;
            if (workshop)                return PRODUCTS.workshop;
            if (conference)              return PRODUCTS.conference;
            if (virtual_)                return PRODUCTS.virtual;
            return null;
        }

        // ── Expose to inline HTML onchange ───────────────────────────────────
        window.updateCartFromCheckboxes = function() {
            var productId = resolveProduct();

            // Always clear first
            $.post(ajax_object.ajax_url, { action: 'clear_cart', nonce: ajax_object.nonce });

            if (!productId) {
                $('.cart-status').text('');
                $('#pay-submit').prop('disabled', true);
                return;
            }

            $('.cart-status').text('Updating cart…');
            $('#pay-submit').prop('disabled', true);

            $.post(ajax_object.ajax_url, {
                action     : 'add_to_cart_dynamic',
                product_id : productId,
                nonce      : ajax_object.nonce
            }, function(response) {
                if (response.success) {
                    $('.cart-status').html('✅ Item added! Ready to pay.');
                    $('#pay-submit').prop('disabled', false);
                } else {
                    $('.cart-status').html('❌ Error: ' + (response.data || 'Try again'));
                    $('#pay-submit').prop('disabled', true);
                }
            }).fail(function() {
                $('.cart-status').html('❌ Network error — try again');
                $('#pay-submit').prop('disabled', true);
            });
        };

        // ── Form submit ──────────────────────────────────────────────────────
        $('#registration-form').on('submit', function(e) {
            e.preventDefault();

            // 1. Required-field validation
            var valid = true;
            $(this).find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            // 2. At least one registration option must be checked
            var productId = resolveProduct();
            if (!productId) {
                $('#registration-error').text('Please select at least one registration option.').show();
                return;
            }

            // 3. Email match
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

            // 4. Build registering_for as a comma-joined string for the DB
            var selections = [];
            $('[name="registering_for[]"]:checked').each(function() {
                selections.push($(this).val());
            });
            var registeringForStr = selections.join(', ');

            // 5. Serialize form but override registering_for with our string
            //    (jQuery serialize sends multiple values for checkboxes which
            //    the PHP handler treats as a single sanitize_text_field call)
            var formData = $(this).serializeArray().filter(function(item) {
                return item.name !== 'registering_for[]';
            });
            formData.push({ name: 'registering_for', value: registeringForStr });
            formData.push({ name: 'action', value: 'save_registration' });
            formData.push({ name: 'nonce',  value: ajax_object.nonce });

            $('#pay-submit').prop('disabled', true).text('Saving…');

            // 6. Save registration to DB first
            $.post(ajax_object.ajax_url, $.param(formData), function(response) {
                if (response.success) {
                    console.log('Registration saved. ID:', response.data.registration_id);

                    // 7. Load WooCommerce checkout modal
                    $.post(ajax_object.ajax_url, {
                        action : 'load_wc_checkout',
                        nonce  : ajax_object.nonce
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

        // ── Detect payment success ───────────────────────────────────────────
        $('#checkoutModal').on('hidden.bs.modal', function() {
            if (window.paymentCompleted) { location.reload(); }
        });

        $(document.body).on('order_received updated_wc_div', function() {
            window.paymentCompleted = true;
        });
    });
JS;
    wp_add_inline_script('bootstrap-js', $script);
}
add_action('wp_enqueue_scripts', 'add_registration_script');


function rb_registration_buttons_shortcode($atts)
{

    $atts = shortcode_atts(
        array(
            'member_url' => '#',
            'non_member_url' => '#',
            'member_label' => 'Register as Member',
            'non_member_label' => 'Register as Non-Member',
        ),
        $atts,
        'registration_buttons'
    );

    $member_url = esc_url($atts['member_url']);
    $non_member_url = esc_url($atts['non_member_url']);
    $member_label = esc_html($atts['member_label']);
    $non_member_label = esc_html($atts['non_member_label']);

    ob_start();
    ?>


<style>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap');

.page-root {
    min-height: 100vh;
    background: #f4fff6;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    font-family: 'DM Sans', sans-serif;
    position: relative;
    overflow: hidden;
}

.page-root::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 60% 40% at 15% 10%, rgba(34, 197, 94, 0.15) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 85% 90%, rgba(34, 197, 94, 0.15) 0%, transparent 60%);
    pointer-events: none;
}

.page-root::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(34, 197, 94, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(34, 197, 94, 0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}

.eyebrow {
    font-size: 11px;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #22c55e;
}

.page-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 700;
    color: #065f46;
}

.page-subtitle {
    color: #166534;
}

.card--member {
    background: #ffffff;
    border: 1px solid rgba(34, 197, 94, 0.3);
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.08), 0 24px 48px rgba(34, 197, 94, 0.1);
}

.card--member:hover {
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.4), 0 32px 64px rgba(34, 197, 94, 0.2);
}

.card--nonmember {
    background: #f9fffa;
    border: 1px solid rgba(34, 197, 94, 0.2);
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.05), 0 24px 48px rgba(34, 197, 94, 0.08);
}

.card--nonmember:hover {
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.3), 0 32px 64px rgba(34, 197, 94, 0.15);
}

.card--member .card-top-bar {
    background: #22c55e;
}

.card--nonmember .card-top-bar {
    background: #86efac;
}

.card--member .card-type,
.card--nonmember .card-type {
    color: #22c55e;
}

.card-title {
    color: #065f46;
}

.card--member .card-pill,
.card--nonmember .card-pill {
    background: rgba(34, 197, 94, 0.1);
    color: #166534;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.card--member .card-divider,
.card--nonmember .card-divider {
    background: rgba(34, 197, 94, 0.2);
}

.card--member .perk-item.active .perk-label,
.card--nonmember .perk-item .perk-label {
    color: #065f46;
}

.card--member .perk-item.active .perk-desc,
.card--nonmember .perk-item .perk-desc {
    color: #166534;
}

.perk-icon-wrap.active {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.perk-icon-wrap.inactive {
    background: rgba(34, 197, 94, 0.05);
    border: 1px solid rgba(34, 197, 94, 0.1);
}

.card--member .early-bird-box,
.card--nonmember .early-bird-box {
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.card--member .eb-strong,
.card--nonmember .eb-strong {
    color: #16a34a;
}

.card--member .eb-desc,
.card--nonmember .eb-desc {
    color: #166534;
}

.card--member .reg-btn {
    background: #22c55e;
    color: #ffffff;
}

.card--member .reg-btn:hover {
    background: #16a34a;
}

.card--nonmember .reg-btn {
    background: transparent;
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.5);
}

.card--nonmember .reg-btn:hover {
    background: rgba(34, 197, 94, 0.15);
    color: #15803d;
}

.note {
    color: #166534;
}
</style>

    <div class="page-root">

        <p class="eyebrow">Registration</p>
        <h1 class="page-title">Choose Your Path</h1>
        <p class="page-subtitle">Select the registration type that best reflects your status and the benefits you wish to
            receive.</p>

        <div class="cards-row">

            <!-- MEMBER CARD -->
            <div class="card card--member">
                <div class="card-top-bar"></div>
                <div class="card-header">
                    <div>
                        <p class="card-type">Full Access</p>
                        <h2 class="card-title">Register as<br>a Member</h2>
                    </div>
                    <span class="card-pill">Member</span>
                </div>
                <hr class="card-divider">
                <ul class="card-perks">
                    <li class="perk-item active">
                        <div class="perk-icon-wrap active">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <path d="M7 1l1.5 3.2 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.7.6-3.5L2 4.7l3.5-.5L7 1z"
                                    fill="#4ec46a" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Induction Eligibility</p>
                            <p class="perk-desc">Be formally inducted and recognised as an official member of the
                                organisation.</p>
                        </div>
                    </li>
                    <li class="perk-item active">
                        <div class="perk-icon-wrap active">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <rect x="1" y="2" width="12" height="10" rx="1" stroke="#4ec46a" stroke-width="1.2"
                                    fill="none" />
                                <path d="M4 6h6M4 8.5h4" stroke="#4ec46a" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Official Certificate</p>
                            <p class="perk-desc">Receive a recognised certificate of membership upon successful completion.
                            </p>
                        </div>
                    </li>
                    <li class="perk-item active">
                        <div class="perk-icon-wrap active">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <circle cx="7" cy="7" r="5.5" stroke="#4ec46a" stroke-width="1.2" fill="none" />
                                <path d="M4.5 7l2 2 3-3" stroke="#4ec46a" stroke-width="1.2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Implement &amp; Use Access</p>
                            <p class="perk-desc">Full rights to implement and utilise all member resources and privileges.
                            </p>
                        </div>
                    </li>
                </ul>
                <div class="early-bird-box">
                    <svg class="eb-icon" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="6.5" stroke="#4ec46a" stroke-width="1.2" fill="none" />
                        <path d="M8 5v3.5l2 2" stroke="#4ec46a" stroke-width="1.2" stroke-linecap="round" />
                    </svg>
                    <div>
                        <span class="eb-strong">25% Early Bird Discount</span>
                        <span class="eb-desc">Exclusively for members — lock in your savings by registering before the
                            deadline.</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="#" class="reg-btn">Register as Member &rarr;</a>
                </div>
            </div>

            <!-- NON-MEMBER CARD -->
            <div class="card card--nonmember">
                <div class="card-top-bar"></div>
                <div class="card-header">
                    <div>
                        <p class="card-type">Standard Access</p>
                        <h2 class="card-title">Register as<br>Non-Member</h2>
                    </div>
                    <span class="card-pill">Non-Member</span>
                </div>
                <hr class="card-divider">
                <ul class="card-perks">
                    <li class="perk-item inactive">
                        <div class="perk-icon-wrap inactive">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <path d="M4 4l6 6M10 4l-6 6" stroke="#1a3020" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Induction Eligibility</p>
                            <p class="perk-desc">Not eligible for formal induction into the organisation.</p>
                        </div>
                    </li>
                    <li class="perk-item inactive">
                        <div class="perk-icon-wrap inactive">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <path d="M4 4l6 6M10 4l-6 6" stroke="#1a3020" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Official Certificate</p>
                            <p class="perk-desc">Certificate is not included with non-member registration.</p>
                        </div>
                    </li>
                    <li class="perk-item inactive">
                        <div class="perk-icon-wrap inactive">
                            <svg class="perk-icon" viewBox="0 0 14 14" fill="none">
                                <path d="M4 4l6 6M10 4l-6 6" stroke="#1a3020" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="perk-label">Implement &amp; Use Access</p>
                            <p class="perk-desc">Member resources and privileges are not available to non-members.</p>
                        </div>
                    </li>
                </ul>
                <div class="early-bird-box">
                    <svg class="eb-icon" viewBox="0 0 16 16" fill="none">
                        <rect x="3" y="7" width="10" height="7" rx="1" stroke="#1a3020" stroke-width="1.2" fill="none" />
                        <path d="M5 7V5a3 3 0 016 0v2" stroke="#1a3020" stroke-width="1.2" stroke-linecap="round" />
                    </svg>
                    <div>
                        <span class="eb-strong">Early Bird Discount not available</span>
                        <span class="eb-desc">This option does not qualify for the 25% early bird rate.</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="#" class="reg-btn">Register as Non-Member &rarr;</a>
                </div>
            </div>

        </div>

        <p class="note">Membership status is verified at the time of registration &nbsp;·&nbsp; For enquiries, contact your
            event coordinator</p>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('registration_buttons', 'rb_registration_buttons_shortcode');
