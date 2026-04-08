<?php


// ============================================================
// 2. ADD TO CART  (now accepts quantity for bulk)
// ============================================================
function ajax_add_to_cart_handler_for_organization()
{
    if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }

    $product_id = intval($_POST['product_id']);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));

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
add_action('wp_ajax_add_to_cart_dynamic', 'ajax_add_to_cart_handler_for_organization');
add_action('wp_ajax_nopriv_add_to_cart_dynamic', 'ajax_add_to_cart_handler_for_organization');


// ============================================================
// 3. LOAD CHECKOUT
// ============================================================
function ajax_load_wc_checkout_for_organization()
{
    check_ajax_referer('registration_nonce', 'nonce');

    if (!WC()->cart->is_empty()) {
        ob_start();
        echo do_shortcode('[woocommerce_checkout]');
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    } else {
        wp_send_json_error('Cart is empty. Please add at least one attendee.');
    }
    wp_die();
}
add_action('wp_ajax_load_wc_checkout', 'ajax_load_wc_checkout_for_organization');
add_action('wp_ajax_nopriv_load_wc_checkout', 'ajax_load_wc_checkout_for_organization');


// ============================================================
// 4. CLEAR CART
// // ============================================================
// function ajax_clear_cart()
// {
//     if (!function_exists('WC')) { wp_send_json_success('WC not loaded'); wp_die(); }
//     if (!WC()->cart) { WC()->initialize_cart(); }
//     if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) {
//         WC()->cart->empty_cart();
//         do_action('woocommerce_cart_emptied');
//     }
//     wp_send_json_success('Cart cleared');
//     wp_die();
// }
// add_action('wp_ajax_clear_cart',        'ajax_clear_cart');
// add_action('wp_ajax_nopriv_clear_cart', 'ajax_clear_cart');


// ============================================================
// 5. SAVE BULK REGISTRATIONS
//    Receives JSON array of attendees + org billing details.
//    Inserts one row per attendee.
//    who_paid = "OrgName|orgemail"
// ============================================================
function ajax_save_bulk_registrations()
{
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'registration_nonce')) {
        wp_send_json_error('Security check failed');
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    // --- Billing / org details ---
    $org_name = sanitize_text_field($_POST['org_name'] ?? '');
    $org_email = sanitize_email($_POST['org_email'] ?? '');
    $org_phone = sanitize_text_field($_POST['org_phone'] ?? '');

    if (empty($org_name) || empty($org_email)) {
        wp_send_json_error('Organisation name and email are required.');
        wp_die();
    }
    if (!is_email($org_email)) {
        wp_send_json_error('Invalid organisation email address.');
        wp_die();
    }

    // who_paid format: "OrgName|orgemail"
    $who_paid = $org_name . '|' . $org_email;

    // --- Attendees JSON ---
    $attendees_raw = stripslashes($_POST['attendees'] ?? '[]');
    $attendees = json_decode($attendees_raw, true);

    if (!is_array($attendees) || count($attendees) === 0) {
        wp_send_json_error('No attendees provided.');
        wp_die();
    }

    $required_fields = ['registering_for', 'title', 'first_name', 'last_name', 'email', 'phone', 'street', 'city', 'state', 'country', 'gender'];
    $inserted_ids = [];
    $errors = [];

    foreach ($attendees as $idx => $a) {
        $num = $idx + 1;

        $data = array(
            'member_id' => sanitize_text_field($a['member_id'] ?? ''),
            'registering_for' => sanitize_text_field($a['registering_for'] ?? ''),
            'title' => sanitize_text_field($a['title'] ?? ''),
            'first_name' => sanitize_text_field($a['first_name'] ?? ''),
            'middle_name' => sanitize_text_field($a['middle_name'] ?? ''),
            'last_name' => sanitize_text_field($a['last_name'] ?? ''),
            'email' => sanitize_email($a['email'] ?? ''),
            'phone' => sanitize_text_field($a['phone'] ?? ''),
            'occupation' => sanitize_text_field($a['occupation'] ?? ''),
            'organisation' => $org_name,
            'street' => sanitize_text_field($a['street'] ?? ''),
            'city' => sanitize_text_field($a['city'] ?? ''),
            'state' => sanitize_text_field($a['state'] ?? ''),
            'postcode' => sanitize_text_field($a['postcode'] ?? ''),
            'country' => sanitize_text_field($a['country'] ?? 'NG'),
            'gender' => sanitize_text_field($a['gender'] ?? ''),
            'hear_about' => sanitize_text_field($a['hear_about'] ?? ''),
            'payment_status' => 'pending',
            'who_paid' => $who_paid,
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Attendee $num: missing required field '$field'";
            }
        }
        if (!is_email($data['email'])) {
            $errors[] = "Attendee $num: invalid email address";
        }

        if (empty($errors)) {
            $result = $wpdb->insert($table_name, $data);
            if ($result !== false) {
                $inserted_ids[] = $wpdb->insert_id;
            } else {
                $errors[] = "Attendee $num: database error — " . $wpdb->last_error;
            }
        }
    }

    if (!empty($errors)) {
        wp_send_json_error(implode('; ', $errors));
        wp_die();
    }

    wp_send_json_success(array(
        'registration_ids' => $inserted_ids,
        'count' => count($inserted_ids),
        'message' => count($inserted_ids) . ' registration(s) saved successfully.',
    ));
    wp_die();
}
add_action('wp_ajax_save_bulk_registrations', 'ajax_save_bulk_registrations');
add_action('wp_ajax_nopriv_save_bulk_registrations', 'ajax_save_bulk_registrations');


// ============================================================
// 6. LINK ORDER TO ALL REGISTRATION IDS (after payment)
// ============================================================
function link_registrations_to_order($order_id)
{
    $ids_json = WC()->session ? WC()->session->get('nsa_registration_ids') : null;
    if (!$ids_json)
        return;

    $ids = json_decode($ids_json, true);
    if (!is_array($ids) || empty($ids))
        return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';

    foreach ($ids as $reg_id) {
        $wpdb->update(
            $table_name,
            array('order_id' => $order_id, 'payment_status' => 'paid'),
            array('id' => intval($reg_id)),
            array('%d', '%s'),
            array('%d')
        );
    }

    WC()->session->__unset('nsa_registration_ids');
}
add_action('woocommerce_payment_complete', 'link_registrations_to_order');


// ============================================================
// 8. SHORTCODE
// ============================================================
function registration_form_with_checkout_shortcode_for_organization()
{
    ob_start();
    ?>
    <div class="registration-container" id="nsa-reg-app">

        <!-- ── Step indicator ─────────────────────────────────────── -->
        <div class="nsa-steps mb-4">
            <div class="nsa-step active" id="step-ind-1">
                <span class="nsa-step-num">1</span> Billing Details
            </div>
            <div class="nsa-step-connector"></div>
            <div class="nsa-step" id="step-ind-2">
                <span class="nsa-step-num">2</span> Attendees
            </div>
            <div class="nsa-step-connector"></div>
            <div class="nsa-step" id="step-ind-3">
                <span class="nsa-step-num">3</span> Payment
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             STEP 1 — BILLING / ORG DETAILS
        ═════════════════════════════════════════════════════════ -->
        <div id="nsa-step-1">
            <div class="nsa-card mb-4">
                <div class="nsa-card-header">
                    <h5 class="mb-0">🏢 Billing &amp; Organisation Details</h5>
                    <p class="text-muted small mb-0 mt-1">
                        Enter the details of the organisation or individual paying for all attendees.
                        These details will be recorded as <strong>who_paid</strong> against every registration.
                    </p>
                </div>
                <div class="nsa-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Organisation / Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="org_name" placeholder="e.g. CISON Nigeria" required>
                            <div class="invalid-feedback">Organisation name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organisation Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="org_email" placeholder="finance@organisation.org"
                                required>
                            <div class="invalid-feedback">A valid email is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organisation Phone</label>
                            <input type="tel" class="form-control" id="org_phone" placeholder="+234 …">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person (submitting this form)</label>
                            <input type="text" class="form-control" id="org_contact" placeholder="Full name">
                        </div>
                    </div>

                    <div class="nsa-who-paid-preview mt-3 p-3 bg-light border rounded small text-muted"
                        id="who-paid-preview" style="display:none;">
                        <strong>who_paid will be recorded as:</strong>
                        <span id="who-paid-value" class="text-dark ms-1 fw-semibold"></span>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-primary btn-lg px-5" id="btn-to-step-2">
                    Continue to Attendees →
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             STEP 2 — ATTENDEES
        ═════════════════════════════════════════════════════════ -->
        <div id="nsa-step-2" style="display:none;">
            <div class="col-12">
                <label class="form-label fw-semibold">Registering for <span class="text-danger">*</span></label>
                <p class="text-muted small mb-2">
                    Workshop may be combined with one conference option.
                    On-site and virtual cannot both be selected.
                </p>
                <div class="form-check">
                    <input class="att-reg-check" type="checkbox" value="workshop">
                    <label class="form-check-label">Pre-Conference Workshop</label>
                </div>
                <div class="form-check">
                    <input class="att-reg-check att-conference-opt" type="checkbox" value="conference">
                    <label class="form-check-label">3rd Annual Conference — On-Site (Early Bird)</label>
                </div>
                <div class="form-check">
                    <input class="att-reg-check att-conference-opt" type="checkbox" value="virtual">
                    <label class="form-check-label">3rd Annual Conference — Virtual (Early Bird)</label>
                </div>
                <div class="att-reg-error text-danger small mt-1" style="display:none;"></div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">👥 Attendee Details</h5>
                    <p class="text-muted small mb-0" id="attendee-count-label">0 attendee(s) added</p>
                </div>
                <button class="btn btn-success btn-sm px-4" id="btn-add-attendee">
                    ＋ Add Attendee
                </button>
            </div>

            <!-- Cards render here -->
            <div id="attendee-list"></div>

            <!-- Empty state -->
            <div id="attendee-empty" class="nsa-empty-state">
                <div class="nsa-empty-icon">👤</div>
                <p class="mb-1 fw-semibold">No attendees added yet</p>
                <p class="text-muted small mb-0">Click <strong>＋ Add Attendee</strong> to begin.</p>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <button class="btn btn-outline-secondary" id="btn-back-to-1">← Back</button>
                <button class="btn btn-primary btn-lg px-5" id="btn-to-checkout" disabled>
                    Proceed to Checkout →
                </button>
            </div>
        </div>

        <!-- ── Checkout modal ─────────────────────────────────────── -->
        <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Complete Secure Payment</h5>
                            <p class="text-muted small mb-0" id="checkout-summary-label"></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="checkout-container" class="p-3">Loading checkout…</div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.registration-container -->


    <!-- ════════════════════════════════════════════════════════════
         ATTENDEE CARD TEMPLATE  (hidden, cloned by JS)
    ═════════════════════════════════════════════════════════════ -->
    <template id="attendee-card-tpl">
        <div class="nsa-attendee-card" data-attendee-id="">
            <div class="nsa-attendee-header">
                <div class="nsa-attendee-title">
                    <span class="nsa-attendee-badge">👤</span>
                    <strong>Attendee #<span class="att-num"></span></strong>
                    <span class="nsa-attendee-name-preview text-muted ms-2 small"></span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle-card"
                        title="Collapse/Expand">▲</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-attendee" title="Remove">✕
                        Remove</button>
                </div>
            </div>

            <div class="nsa-attendee-body">
                <div class="row g-3">

                    <div class="col-md-3 g-3 mb-5">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <select class="form-select att-field" name="title" required>
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
                    <div class="col-md-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control att-field att-first-name" name="first_name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control att-field" name="middle_name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control att-field att-last-name" name="last_name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control att-field att-email" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control att-field att-confirm-email" name="confirm_email" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control att-field" name="phone" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Occupation</label>
                        <input type="text" class="form-control att-field" name="occupation">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select class="form-select att-field" name="gender" required>
                            <option value="">Select</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Prefer Not to Answer</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label d-block">CISON Member?</label>
                        <div class="d-flex gap-4 align-items-center mt-1">
                            <label class="d-flex align-items-center gap-1 mb-0">
                                <input type="radio" name="is_cison_member" value="yes" class="att-cison-radio"> Yes
                            </label>
                            <label class="d-flex align-items-center gap-1 mb-0">
                                <input type="radio" name="is_cison_member" value="no" class="att-cison-radio" checked> No
                            </label>
                        </div>
                        <div class="att-cison-id-wrap mt-2" style="display:none;">
                            <input type="text" class="form-control att-field" name="member_id"
                                placeholder="CISON ID (8 digits)" pattern="[0-9]{8}"
                                title="Enter valid CISON ID (8 digits)">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="col-12">
                        <label class="form-label">Street Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control att-field" name="street" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City/Town <span class="text-danger">*</span></label>
                        <input type="text" class="form-control att-field" name="city" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State <span class="text-danger">*</span></label>
                        <input type="text" class="form-control att-field" name="state" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Postcode</label>
                        <input type="text" class="form-control att-field" name="postcode">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country <span class="text-danger">*</span></label>
                        <select class="form-select att-field" name="country" required>
                            <option value="">Select</option>
                            <option value="NG" selected>Nigeria</option>
                            <option value="GH">Ghana</option>
                            <option value="KE">Kenya</option>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                        </select>
                    </div>

                    <!-- Registration options -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Registering for <span class="text-danger">*</span></label>
                        <p class="text-muted small mb-2">
                            Workshop may be combined with one conference option.
                            On-site and virtual cannot both be selected.
                        </p>
                        <div class="form-check">
                            <input class="att-reg-check" type="checkbox" value="workshop">
                            <label class="form-check-label">Pre-Conference Workshop</label>
                        </div>
                        <div class="form-check">
                            <input class="att-reg-check att-conference-opt" type="checkbox" value="conference">
                            <label class="form-check-label">3rd Annual Conference — On-Site (Early Bird)</label>
                        </div>
                        <div class="form-check">
                            <input class="att-reg-check att-conference-opt" type="checkbox" value="virtual">
                            <label class="form-check-label">3rd Annual Conference — Virtual (Early Bird)</label>
                        </div>
                        <div class="att-reg-error text-danger small mt-1" style="display:none;"></div>
                    </div>

                    <!-- <div class="col-md-6">
                        <label class="form-label">How did you hear about this event?</label>
                        <select class="form-select att-field" name="hear_about">
                            <option value="">Select</option>
                            <option>Social Media</option><option>Google</option>
                            <option>Word of Mouth</option><option>From a Friend</option>
                            <option>News Media</option><option>Other</option>
                        </select>
                    </div> -->

                </div><!-- /.row -->
            </div><!-- /.nsa-attendee-body -->
        </div><!-- /.nsa-attendee-card -->
    </template>


    <style>
        .registration-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 20px;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        /* Steps */
        .nsa-steps {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }

        .nsa-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #999;
            font-weight: 500;
            flex: 1;
        }

        .nsa-step.active {
            color: #0d6efd;
        }

        .nsa-step.done {
            color: #198754;
        }

        .nsa-step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #dee2e6;
            color: #666;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .nsa-step.active .nsa-step-num {
            background: #0d6efd;
            color: #fff;
        }

        .nsa-step.done .nsa-step-num {
            background: #198754;
            color: #fff;
        }

        .nsa-step-connector {
            flex: none;
            width: 32px;
            height: 2px;
            background: #dee2e6;
        }

        /* Org card */
        .nsa-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
        }

        .nsa-card-header {
            background: #f8f9fa;
            padding: 16px 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .nsa-card-body {
            padding: 20px;
        }

        /* Attendee cards */
        .nsa-attendee-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: box-shadow .15s;
        }

        .nsa-attendee-card:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }

        .nsa-attendee-card.is-invalid-card {
            border-color: #dc3545;
        }

        .nsa-attendee-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }

        .nsa-attendee-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nsa-attendee-badge {
            font-size: 18px;
            line-height: 1;
        }

        .nsa-attendee-body {
            padding: 20px;
        }

        .nsa-attendee-body.collapsed {
            display: none;
        }

        /* Empty state */
        .nsa-empty-state {
            text-align: center;
            padding: 48px 20px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            color: #666;
        }

        .nsa-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        #checkout-container .woocommerce {
            padding: 20px;
        }
    </style>

    <?php
    return ob_get_clean();
}
add_shortcode('registration_wc_checkout_for_organization', 'registration_form_with_checkout_shortcode_for_organization');


// ============================================================
// 9. INLINE JS
// ============================================================
function add_registration_script_for_organization()
{
    $script = <<<'ENDJS'
jQuery(document).ready(function ($) {

    // ── Product map ────────────────────────────────────────────────
    var PRODUCTS = {
        workshop            : 12816,
        conference          : 12817,
        virtual             : 12818,
        workshop_conference : 12670,
        workshop_virtual    : 12672
    };

    // ── Internal counter (unique per page load, never reused) ──────
    var _uid = 0;
    function nextId() { return ++_uid; }

    // ──────────────────────────────────────────────────────────────
    // STEP MANAGEMENT
    // ──────────────────────────────────────────────────────────────
    function setStep(n) {
        $('#nsa-step-1, #nsa-step-2').hide();
        $('#nsa-step-' + n).show();
        $('.nsa-step').removeClass('active done');
        for (var i = 1; i < n; i++) {
            $('#step-ind-' + i).addClass('done').find('.nsa-step-num').html('✓');
        }
        $('#step-ind-' + n).addClass('active');
    }

    // ── Step 1 → Step 2 ───────────────────────────────────────────
    $('#btn-to-step-2').on('click', function () {
        var orgName  = $('#org_name').val().trim();
        var orgEmail = $('#org_email').val().trim();
        var valid    = true;

        if (!orgName) {
            $('#org_name').addClass('is-invalid');
            valid = false;
        } else {
            $('#org_name').removeClass('is-invalid');
        }
        if (!orgEmail || !isValidEmail(orgEmail)) {
            $('#org_email').addClass('is-invalid');
            valid = false;
        } else {
            $('#org_email').removeClass('is-invalid');
        }
        if (!valid) return;

        setStep(2);
        if ($('#attendee-list .nsa-attendee-card').length === 0) {
            addAttendeeCard();
        }
    });

    // ── Live who_paid preview on step 1 ───────────────────────────
    $('#org_name, #org_email').on('input', function () {
        var n = $('#org_name').val().trim();
        var e = $('#org_email').val().trim();
        if (n || e) {
            $('#who-paid-value').text((n || '…') + '|' + (e || '…'));
            $('#who-paid-preview').show();
        } else {
            $('#who-paid-preview').hide();
        }
    });

    // ── Back ───────────────────────────────────────────────────────
    $('#btn-back-to-1').on('click', function () { setStep(1); });


    // ──────────────────────────────────────────────────────────────
    // ADD ATTENDEE CARD
    // ──────────────────────────────────────────────────────────────
    $('#btn-add-attendee').on('click', addAttendeeCard);

    function addAttendeeCard() {
        var id  = nextId();
        var tpl = document.getElementById('attendee-card-tpl');
        var frag = document.importNode(tpl.content, true);

        // Serialise to string so jQuery can work with it cleanly
        var tmp = document.createElement('div');
        tmp.appendChild(frag);
        var html = tmp.innerHTML;

        // Stamp data-attendee-id
        html = html.replace('data-attendee-id=""', 'data-attendee-id="' + id + '"');

        var card = $(html);
        card.find('.att-num').text(id);

        // Unique radio group name per card
        card.find('input[type=radio]').attr('name', 'is_cison_member_' + id);

        $('#attendee-list').append(card);

        // Grab the live node
        var live = $('#attendee-list .nsa-attendee-card[data-attendee-id="' + id + '"]');
        bindCardEvents(live, id);

        updateUI();

        // Scroll the new card into view
        live[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ──────────────────────────────────────────────────────────────
    // BIND EVENTS TO A CARD
    // ──────────────────────────────────────────────────────────────
    function bindCardEvents(card, id) {

        // Remove
        card.find('.btn-remove-attendee').on('click', function (e) {
            e.stopPropagation();
            if ($('#attendee-list .nsa-attendee-card').length === 1) {
                alert('You need at least one attendee. Add another before removing this one.');
                return;
            }
            card.slideUp(200, function () {
                card.remove();
                renumberCards();
                updateUI();
            });
        });

        // Collapse header click
        card.find('.nsa-attendee-header').on('click', function (e) {
            if ($(e.target).closest('button').length) return;
            toggleCollapse(card);
        });
        card.find('.btn-toggle-card').on('click', function (e) {
            e.stopPropagation();
            toggleCollapse(card);
        });

        // Name preview
        card.find('.att-first-name, .att-last-name').on('input', function () {
            var fn = card.find('.att-first-name').val().trim();
            var ln = card.find('.att-last-name').val().trim();
            card.find('.nsa-attendee-name-preview')
                .text(fn || ln ? '— ' + [fn, ln].filter(Boolean).join(' ') : '');
        });

        // CISON member toggle
        card.find('.att-cison-radio').on('change', function () {
            var wrap = card.find('.att-cison-id-wrap');
            if ($(this).val() === 'yes') {
                wrap.slideDown(150);
                wrap.find('input').prop('required', true);
            } else {
                wrap.slideUp(150);
                wrap.find('input').prop('required', false).val('');
            }
        });

        // Conference mutual exclusion
        card.find('.att-conference-opt').on('change', function () {
            if ($(this).is(':checked')) {
                card.find('.att-conference-opt').not(this).prop('checked', false);
            }
            updateRegError(card);
        });
        card.find('.att-reg-check').on('change', function () {
            updateRegError(card);
        });

        // Clear is-invalid on any input
        card.find('.att-field').on('change input', function () {
            $(this).removeClass('is-invalid');
        });
    }

    function toggleCollapse(card) {
        var body   = card.find('.nsa-attendee-body');
        var toggle = card.find('.btn-toggle-card');
        if (body.hasClass('collapsed')) {
            body.removeClass('collapsed');
            toggle.text('▲');
        } else {
            body.addClass('collapsed');
            toggle.text('▼');
        }
    }

    function updateRegError(card) {
        var s = getSelections(card);
        var errDiv = card.find('.att-reg-error');
        if (s.conference && s.virtual) {
            errDiv.text('On-Site and Virtual cannot both be selected. Please choose one.').show();
        } else {
            errDiv.hide();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // UI HELPERS
    // ──────────────────────────────────────────────────────────────
    function renumberCards() {
        $('#attendee-list .nsa-attendee-card').each(function (i) {
            $(this).find('.att-num').first().text(i + 1);
        });
        var count = $('#attendee-list .nsa-attendee-card').length;
        $('#attendee-count-label').text(count + ' attendee(s) added');
    }

    function updateUI() {
        var count = $('#attendee-list .nsa-attendee-card').length;
        $('#attendee-empty').toggle(count === 0);
        $('#btn-to-checkout').prop('disabled', count === 0);
        $('#attendee-count-label').text(count + ' attendee(s) added');
    }

    // ──────────────────────────────────────────────────────────────
    // SELECTIONS HELPERS
    // ──────────────────────────────────────────────────────────────
    function getSelections(card) {
        return {
            workshop   : card.find('.att-reg-check[value="workshop"]').is(':checked'),
            conference : card.find('.att-reg-check[value="conference"]').is(':checked'),
            virtual    : card.find('.att-reg-check[value="virtual"]').is(':checked')
        };
    }

    function selectionsToString(s) {
        var parts = [];
        if (s.workshop)   parts.push('workshop');
        if (s.conference) parts.push('conference');
        if (s.virtual)    parts.push('virtual');
        return parts.join(', ');
    }

    function resolveProduct(s) {
        if (s.workshop && s.conference) return PRODUCTS.workshop_conference;
        if (s.workshop && s.virtual)    return PRODUCTS.workshop_virtual;
        if (s.workshop)                 return PRODUCTS.workshop;
        if (s.conference)               return PRODUCTS.conference;
        if (s.virtual)                  return PRODUCTS.virtual;
        return null;
    }

    // ──────────────────────────────────────────────────────────────
    // VALIDATE ALL ATTENDEE CARDS
    // Returns { valid: bool, errors: [], attendees: [] }
    // ──────────────────────────────────────────────────────────────
    function validateAll() {
        var errors   = [];
        var dataList = [];

        $('#attendee-list .nsa-attendee-card').each(function (idx) {
            var card = $(this);
            var num  = idx + 1;
            var ok   = true;

            // Clear previous marks
            card.find('.is-invalid').removeClass('is-invalid');

            // Required fields
            card.find('.att-field[required]').each(function () {
                if (!$(this).val().trim()) {
                    $(this).addClass('is-invalid');
                    ok = false;
                }
            });

            // Email match
            var email   = card.find('.att-email').val().trim();
            var confirm = card.find('.att-confirm-email').val().trim();
            if (email && confirm && email !== confirm) {
                card.find('.att-confirm-email').addClass('is-invalid');
                errors.push('Attendee ' + num + ': email addresses do not match');
                ok = false;
            }
            if (email && !isValidEmail(email)) {
                card.find('.att-email').addClass('is-invalid');
                errors.push('Attendee ' + num + ': invalid email format');
                ok = false;
            }

            // Registration options
            var s = getSelections(card);
            if (s.conference && s.virtual) {
                errors.push('Attendee ' + num + ': cannot select both On-Site and Virtual');
                ok = false;
            }
            if (!s.workshop && !s.conference && !s.virtual) {
                errors.push('Attendee ' + num + ': please select at least one registration option');
                ok = false;
            }

            // Generic required-field errors
            if (!ok && card.find('[required].is-invalid').length) {
                errors.push('Attendee ' + num + ': please fill all required fields');
            }

            card.toggleClass('is-invalid-card', !ok);

            // Expand collapsed invalid cards
            if (!ok && card.find('.nsa-attendee-body').hasClass('collapsed')) {
                card.find('.nsa-attendee-body').removeClass('collapsed');
                card.find('.btn-toggle-card').text('▲');
            }

            // Collect data for this attendee
            var data = {};
            card.find('.att-field').each(function () {
                var raw  = $(this).attr('name') || '';
                var name = raw.replace(/_\d+$/, '');
                if (name) data[name] = $(this).val().trim();
            });
            data.registering_for = selectionsToString(s);
            data.member_id       = card.find('[name^="member_id"]').val().trim();

            dataList.push(data);
        });

        return { valid: errors.length === 0, errors: errors, attendees: dataList };
    }

    // ──────────────────────────────────────────────────────────────
    // BUILD CART PLAN  — groups attendees by product, sums quantities
    // ──────────────────────────────────────────────────────────────
    function buildCartPlan(attendeesData) {
        var counts = {};
        attendeesData.forEach(function (a) {
            var s = {
                workshop   : a.registering_for.indexOf('workshop')   >= 0,
                conference : a.registering_for.indexOf('conference') >= 0,
                virtual    : a.registering_for.indexOf('virtual')    >= 0
            };
            var pid = resolveProduct(s);
            if (pid) counts[pid] = (counts[pid] || 0) + 1;
        });
        var plan = [];
        Object.keys(counts).forEach(function (pid) {
            plan.push({ product_id: parseInt(pid, 10), quantity: counts[pid] });
        });
        return plan;  // e.g. [{product_id:12817, quantity:3}, {product_id:12670, quantity:1}]
    }

    // ──────────────────────────────────────────────────────────────
    // PROCEED TO CHECKOUT
    // ──────────────────────────────────────────────────────────────
    $('#btn-to-checkout').on('click', function () {
        var result = validateAll();
        if (!result.valid) {
            // De-duplicate error messages
            var unique = result.errors.filter(function (v, i, a) { return a.indexOf(v) === i; });
            alert('Please fix the following:\n\n• ' + unique.join('\n• '));
            return;
        }

        var orgName  = $('#org_name').val().trim();
        var orgEmail = $('#org_email').val().trim();
        var orgPhone = $('#org_phone').val().trim();
        var btn      = $(this).prop('disabled', true).text('Saving…');

        // 1. Save all registrations to DB
        $.post(ajax_object.ajax_url, {
            action    : 'save_bulk_registrations',
            nonce     : ajax_object.nonce,
            org_name  : orgName,
            org_email : orgEmail,
            org_phone : orgPhone,
            attendees : JSON.stringify(result.attendees)
        })
        .done(function (resp) {
            if (!resp.success) {
                alert('Could not save registrations:\n\n' + resp.data);
                btn.prop('disabled', false).text('Proceed to Checkout →');
                return;
            }

            console.log('Saved', resp.data.count, 'registrations:', resp.data.registration_ids);

            // 2. Clear cart, then add products
            var cartPlan = buildCartPlan(result.attendees);
            btn.text('Adding to cart…');

            $.post(ajax_object.ajax_url, { action: 'clear_cart', nonce: ajax_object.nonce })
            .done(function () {
                // Add the first product/quantity (primary payment item)
                var first = cartPlan[0];
                $.post(ajax_object.ajax_url, {
                    action     : 'add_to_cart_dynamic',
                    product_id : first.product_id,
                    quantity   : first.quantity,
                    nonce      : ajax_object.nonce
                })
                .done(function (cartResp) {
                    if (!cartResp.success) {
                        alert('Cart error: ' + cartResp.data);
                        btn.prop('disabled', false).text('Proceed to Checkout →');
                        return;
                    }

                    btn.text('Loading checkout…');

                    // 3. Load WooCommerce checkout into modal
                    $.post(ajax_object.ajax_url, {
                        action : 'load_wc_checkout',
                        nonce  : ajax_object.nonce
                    })
                    .done(function (checkoutResp) {
                        if (checkoutResp.success) {
                            var n = resp.data.count;
                            $('#checkout-summary-label').text(
                                orgName + ' · ' + n + ' attendee' + (n !== 1 ? 's' : '')
                            );
                            $('#checkout-container').html(checkoutResp.data.html);
                            setStep(3);
                            $('#checkoutModal').modal('show');
                            $(document.body).trigger('update_checkout');
                            $(document.body).trigger('wc_fragment_refresh');
                        } else {
                            alert('Checkout error: ' + checkoutResp.data);
                        }
                        btn.prop('disabled', false).text('Proceed to Checkout →');
                    })
                    .fail(networkError.bind(null, btn));
                })
                .fail(networkError.bind(null, btn));
            })
            .fail(networkError.bind(null, btn));
        })
        .fail(networkError.bind(null, btn));
    });

    function networkError(btn) {
        alert('Network error. Please check your connection and try again.');
        btn.prop('disabled', false).text('Proceed to Checkout →');
    }

    // ──────────────────────────────────────────────────────────────
    // PAYMENT DETECTION
    // ──────────────────────────────────────────────────────────────
    $('#checkoutModal').on('hidden.bs.modal', function () {
        if (window.paymentCompleted) { location.reload(); }
    });
    $(document.body).on('order_received updated_wc_div', function () {
        window.paymentCompleted = true;
    });

    // ──────────────────────────────────────────────────────────────
    // UTIL
    // ──────────────────────────────────────────────────────────────
    function isValidEmail(e) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    }

    // Initialise
    setStep(1);
    updateUI();
});
ENDJS;

    wp_add_inline_script('bootstrap-js', $script);
}
add_action('wp_enqueue_scripts', 'add_registration_script_for_organization');