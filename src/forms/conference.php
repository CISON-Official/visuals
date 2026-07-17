<?php
// ============================================================
// 1. ENQUEUE SCRIPTS
// ============================================================
function enqueue_registration_scripts()
{
    wp_enqueue_script('jquery');
    // wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '1.0', true);
    // wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');

    wp_localize_script('jquery', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('registration_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_registration_scripts');


// ============================================================
// 2. SYNC CART (replaces single-item add-to-cart)
//    Accepts an array of product IDs (0, 1, or 2 of them),
//    empties the cart, then adds each one. This lets a user
//    hold Conference + Preconference at the same time.
// ============================================================
function ajax_sync_cart_handler()
{
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Invalid nonce');
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

    // product_ids arrives as an array, e.g. product_ids[]=12817&product_ids[]=12816
    $raw_ids = isset($_POST['product_ids']) ? (array) $_POST['product_ids'] : array();
    $product_ids = array();
    foreach ($raw_ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            $product_ids[] = $id;
        }
    }

    // Always start clean so stale selections never linger
    WC()->cart->empty_cart();

    if (empty($product_ids)) {
        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'message' => 'Cart cleared',
            'cart_count' => WC()->cart->get_cart_contents_count(),
        ));
        wp_die();
    }

    $added = array();
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->exists()) {
            wp_send_json_error("Product $product_id not found");
            wp_die();
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
        if (!$cart_item_key) {
            wp_send_json_error("Failed to add product $product_id");
            wp_die();
        }
        $added[] = $product_id;
    }

    WC()->cart->calculate_totals();
    wp_send_json_success(array(
        'message' => 'Cart updated',
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'added' => $added,
        'cart_total' => WC()->cart->get_cart_total(),
    ));
    wp_die();
}
add_action('wp_ajax_sync_cart_dynamic', 'ajax_sync_cart_handler');
add_action('wp_ajax_nopriv_sync_cart_dynamic', 'ajax_sync_cart_handler');


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
// 4. CLEAR CART (kept as a standalone utility, e.g. for a
//    "reset" button — sync_cart_dynamic also clears on its own)
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

    // registering_for arrives as a comma-joined string from JS,
    // e.g. "conference_onsite, preconference_virtual"
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
                    You may pick one Conference option and/or one Preconference Workshop option.
                    You cannot select both On-Site and Virtual within the same category
                    (e.g. Conference On-Site + Conference Virtual together is not allowed).
                </p>

                <!-- Preconference Workshop group -->
                <div class="reg-group mb-3">
                    <h6 class="mb-2">Preconference Workshop</h6>
                    <div class="form-check">
                        <input type="checkbox" class=" reg-preconference" name="registering_for[]"
                            value="preconference_onsite" id="chk_preconference_onsite"
                            onchange="handleRegistrationChange('preconference', 'chk_preconference_onsite')">
                        <label class="form-check-label" for="chk_preconference_onsite">
                            Preconference Workshop (On-Site)
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class=" reg-preconference" name="registering_for[]"
                            value="preconference_virtual" id="chk_preconference_virtual"
                            onchange="handleRegistrationChange('preconference', 'chk_preconference_virtual')">
                        <label class="form-check-label" for="chk_preconference_virtual">
                            Preconference Workshop (Virtual)
                        </label>
                    </div>
                </div>

                <!-- Conference group -->
                <div class="reg-group mb-3">
                    <h6 class="mb-2">3rd Annual Conference</h6>
                    <div class="form-check">
                        <input type="checkbox" class=" reg-conference" name="registering_for[]"
                            value="conference_onsite" id="chk_conference_onsite"
                            onchange="handleRegistrationChange('conference', 'chk_conference_onsite')">
                        <label class="form-check-label" for="chk_conference_onsite">
                            3rd Annual Conference (On-Site) (Early Bird)
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class=" reg-conference" name="registering_for[]"
                            value="conference_virtual" id="chk_conference_virtual"
                            onchange="handleRegistrationChange('conference', 'chk_conference_virtual')">
                        <label class="form-check-label" for="chk_conference_virtual">
                            3rd Annual Conference (Virtual) (Early Bird)
                        </label>
                    </div>
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

        .reg-group {
            padding: 10px 14px;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
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

        // ── Enforce mutually exclusive options WITHIN each group ───────────────
        // Rules:
        //   - Preconference group: onsite + virtual cannot both be checked
        //   - Conference group:    onsite + virtual cannot both be checked
        //   - The two groups are fully independent of each other — a user
        //     may have one item checked in each group at the same time.
        function handleRegistrationChange(group, changedId) {
            var groupSelector = group === 'preconference' ? '.reg-preconference' : '.reg-conference';
            var boxes = document.querySelectorAll(groupSelector);
            var changed = document.getElementById(changedId);

            if (changed.checked) {
                boxes.forEach(function (box) {
                    if (box.id !== changedId) {
                        box.checked = false;
                    }
                });
            }

            var errDiv = document.getElementById('registration-error');
            errDiv.style.display = 'none';
            errDiv.textContent = '';

            updateCartFromCheckboxes();
        }
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
            preconference_onsite  : 12816,
            preconference_virtual : 14263,
            conference_onsite     : 14270,
            conference_virtual    : 14271
        };

        // ── Resolve which product IDs are currently selected ─────────────────
        // Returns an array of 0, 1, or 2 product IDs (max one per group).
        function resolveProducts() {
            var ids = [];

            var preSel = $('.reg-preconference:checked').val();
            if (preSel && PRODUCTS[preSel]) {
                ids.push(PRODUCTS[preSel]);
            }

            var confSel = $('.reg-conference:checked').val();
            if (confSel && PRODUCTS[confSel]) {
                ids.push(PRODUCTS[confSel]);
            }

            return ids;
        }

        // ── Expose to inline HTML onchange ───────────────────────────────────
        window.updateCartFromCheckboxes = function() {
            var productIds = resolveProducts();

            if (productIds.length === 0) {
                $('.cart-status').text('');
                $('#pay-submit').prop('disabled', true);
                // still tell the server to clear out any stale cart items
                $.post(ajax_object.ajax_url, {
                    action: 'sync_cart_dynamic',
                    nonce: ajax_object.nonce
                });
                return;
            }

            $('.cart-status').text('Updating cart…');
            $('#pay-submit').prop('disabled', true);

            $.post(ajax_object.ajax_url, {
                action        : 'sync_cart_dynamic',
                product_ids   : productIds,
                nonce         : ajax_object.nonce
            }, function(response) {
                if (response.success) {
                    $('.cart-status').html('✅ Item(s) added! Ready to pay.');
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
            var productIds = resolveProducts();
            if (productIds.length === 0) {
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