<?php
// ============================================================
// CISON Donation Form Shortcode
// Usage: [cison_donation]
//
// Creates a WooCommerce order with the donation amount as a fee
// line-item and redirects to the pay-for-order checkout page.
// No pre-existing WooCommerce product is required.
// ============================================================

// ------------------------------------------------------------
// 1. GET OR CREATE A HIDDEN FEE PRODUCT (same approach as CMP)
//    Used as a line-item placeholder for the order.
// ------------------------------------------------------------
function cison_donation_get_fee_product()
{
    $product_id = get_option('cison_donation_fee_product_id');
    if ($product_id && 'product' === get_post_type($product_id)) {
        return wc_get_product($product_id);
    }

    $product = new WC_Product_Simple();
    $product->set_name('CISON Donation');
    $product->set_status('private');
    $product->set_catalog_visibility('hidden');
    $product->set_virtual(true);
    $product->set_price(0);
    $product->set_regular_price(0);
    $product->save();

    update_option('cison_donation_fee_product_id', $product->get_id());

    return $product;
}

// ------------------------------------------------------------
// 2. HANDLE FORM SUBMISSION via template_redirect
//    Saves donation to DB, creates WC order, redirects to
//    WooCommerce pay-for-order checkout.
// ------------------------------------------------------------
function cison_donation_handle_submission()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cison_donation_submit'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['_donation_nonce'] ?? '', 'cison_donation_action')) {
        return;
    }

    if (!class_exists('WooCommerce') || !WC()) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cison_donations';

    // ---- Sanitise inputs ----
    $name    = sanitize_text_field($_POST['donor_name'] ?? '');
    $email   = sanitize_email($_POST['donor_email'] ?? '');
    $phone   = sanitize_text_field($_POST['donor_phone'] ?? '');
    $amount  = floatval($_POST['donation_amount'] ?? 0);
    $like    = sanitize_textarea_field($_POST['what_they_like'] ?? '');
    $future  = sanitize_textarea_field($_POST['what_they_want'] ?? '');

    // Billing address
    $address_1 = sanitize_text_field($_POST['billing_address_1'] ?? '');
    $address_2 = sanitize_text_field($_POST['billing_address_2'] ?? '');
    $city      = sanitize_text_field($_POST['billing_city'] ?? '');
    $state     = sanitize_text_field($_POST['billing_state'] ?? '');
    $postcode  = sanitize_text_field($_POST['billing_postcode'] ?? '');
    $country   = sanitize_text_field($_POST['billing_country'] ?? 'NG');

    // ---- Validate ----
    $errors = array();
    if (empty($name))    $errors[] = 'Name is required.';
    if (empty($email) || !is_email($email)) $errors[] = 'A valid email is required.';
    if (empty($phone))   $errors[] = 'Phone number is required.';
    if ($amount <= 0)    $errors[] = 'Please enter a valid donation amount.';
    if (empty($like))    $errors[] = 'Please tell us what you like about CISON.';
    if (empty($future))  $errors[] = 'Please share what you would like CISON to do in the future.';
    if (empty($address_1)) $errors[] = 'Street address is required.';
    if (empty($city))    $errors[] = 'City is required.';
    if (empty($state))   $errors[] = 'State is required.';

    if (!empty($errors)) {
        WC()->session->set('cison_donation_error', implode(' ', $errors));
        wp_safe_redirect(wp_get_referer() ?: wp_get_current_url());
        exit;
    }

    // ---- Save to database ----
    $wpdb->insert($table, array(
        'donor_name'        => $name,
        'donor_email'       => $email,
        'donor_phone'       => $phone,
        'donation_amount'   => $amount,
        'what_they_like'    => $like,
        'what_they_want'    => $future,
        'billing_address_1' => $address_1,
        'billing_address_2' => $address_2,
        'billing_city'      => $city,
        'billing_state'     => $state,
        'billing_postcode'  => $postcode,
        'billing_country'   => strtoupper($country),
        'donation_date'     => current_time('mysql'),
        'user_id'           => get_current_user_id(),
        'payment_status'    => 'pending',
    ));

    if (!$wpdb->insert_id) {
        WC()->session->set('cison_donation_error', 'Something went wrong saving your donation. Please try again.');
        wp_safe_redirect(wp_get_current_url());
        exit;
    }

    $donation_id = $wpdb->insert_id;

    // ---- Create WooCommerce order ----
    $fee_product = cison_donation_get_fee_product();
    $user_id     = get_current_user_id();
    $order       = wc_create_order(array('customer_id' => $user_id));

    if (is_wp_error($order)) {
        WC()->session->set('cison_donation_error', 'Could not create payment order. Please try again.');
        wp_safe_redirect(wp_get_current_url());
        exit;
    }

    // Line item: "CISON Donation — <Name>"
    $item = new WC_Order_Item_Product();
    $item->set_product($fee_product);
    $item->set_name('CISON Donation — ' . $name);
    $item->set_quantity(1);
    $item->set_subtotal($amount);
    $item->set_total($amount);
    $order->add_item($item);

    // Billing address from form
    $billing = array(
        'first_name' => $name,
        'email'      => $email,
        'phone'      => $phone,
        'address_1'  => $address_1,
        'address_2'  => $address_2,
        'city'       => $city,
        'state'      => $state,
        'postcode'   => $postcode,
        'country'    => $country,
    );
    $order->set_address($billing, 'billing');
    $order->set_address($billing, 'shipping');

    // Store donation metadata on the order
    $order->update_meta_data('_donation_id', $donation_id);
    $order->update_meta_data('_donor_name', $name);
    $order->update_meta_data('_what_they_like', $like);
    $order->update_meta_data('_what_they_want', $future);

    $order->calculate_totals();
    $order->set_status('pending');
    $order->save();

    // Link donation record to this order
    $wpdb->update(
        $table,
        array('order_id' => $order->get_id()),
        array('id' => $donation_id),
        array('%d'),
        array('%d')
    );

    // ---- Redirect to WooCommerce pay-for-order checkout ----
    wp_safe_redirect($order->get_checkout_payment_url());
    exit;
}
add_action('template_redirect', 'cison_donation_handle_submission');


// ------------------------------------------------------------
// 3. MARK DONATION AS PAID when WooCommerce order is completed
// ------------------------------------------------------------
function cison_donation_on_order_paid($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $donation_id = $order->get_meta('_donation_id');
    if (!$donation_id) {
        return;
    }

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'cison_donations',
        array('payment_status' => 'paid'),
        array('id' => $donation_id),
        array('%s'),
        array('%d')
    );
}
add_action('woocommerce_payment_complete', 'cison_donation_on_order_paid');
add_action('woocommerce_order_status_completed', 'cison_donation_on_order_paid');


// ------------------------------------------------------------
// 4. SHORTCODE RENDER — form with address fields
// ------------------------------------------------------------
function cison_donation_shortcode($atts)
{
    // Retrieve flash error from session
    $error = '';
    if (function_exists('WC') && WC()->session) {
        $error = WC()->session->get('cison_donation_error', '');
        if ($error) {
            WC()->session->__unset('cison_donation_error');
        }
    }

    ob_start();
    ?>
    <style>
        .cison-donation-form { max-width: 640px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .cison-donation-form h2 { text-align: center; color: #1a3a5c; margin-bottom: 5px; }
        .cison-donation-form .subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 0.95em; }
        .cison-donation-form .form-section { margin-bottom: 25px; }
        .cison-donation-form .form-section h3 { font-size: 1.05em; color: #1a3a5c; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; margin-bottom: 15px; }
        .cison-donation-form .form-row { display: flex; gap: 15px; }
        .cison-donation-form .form-row .form-group { flex: 1; }
        .cison-donation-form .form-group { margin-bottom: 15px; }
        .cison-donation-form label { display: block; font-weight: 600; margin-bottom: 5px; color: #333; font-size: 0.92em; }
        .cison-donation-form input[type="text"],
        .cison-donation-form input[type="email"],
        .cison-donation-form input[type="tel"],
        .cison-donation-form input[type="number"],
        .cison-donation-form select,
        .cison-donation-form textarea { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; box-sizing: border-box; }
        .cison-donation-form input:focus,
        .cison-donation-form select:focus,
        .cison-donation-form textarea:focus { outline: none; border-color: #1a3a5c; box-shadow: 0 0 0 2px rgba(26,58,92,0.15); }
        .cison-donation-form textarea { resize: vertical; min-height: 85px; }
        .cison-donation-form .btn-donate { display: block; width: 100%; padding: 13px; background: #1a3a5c; color: #fff; border: none; border-radius: 5px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .cison-donation-form .btn-donate:hover { background: #14304a; }
        .cison-donation-form .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; font-size: 0.95em; }
        .cison-donation-form .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 600px) { .cison-donation-form .form-row { flex-direction: column; gap: 0; } }
    </style>

    <div class="cison-donation-form">
        <h2>Support CISON</h2>
        <p class="subtitle">Your donation helps us continue our mission. Thank you for your generosity!</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('cison_donation_action', '_donation_nonce'); ?>

            <!-- Donor Information -->
            <div class="form-section">
                <h3>Your Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="donor_name">Full Name <span style="color:#c00">*</span></label>
                        <input type="text" id="donor_name" name="donor_name" required placeholder="e.g. Adebayo Johnson">
                    </div>
                    <div class="form-group">
                        <label for="donor_phone">Phone <span style="color:#c00">*</span></label>
                        <input type="tel" id="donor_phone" name="donor_phone" required placeholder="0801 234 5678">
                    </div>
                </div>

                <div class="form-group">
                    <label for="donor_email">Email <span style="color:#c00">*</span></label>
                    <input type="email" id="donor_email" name="donor_email" required placeholder="you@example.com">
                </div>

                <div class="form-group">
                    <label for="donation_amount">Donation Amount (₦) <span style="color:#c00">*</span></label>
                    <input type="number" id="donation_amount" name="donation_amount" min="100" step="0.01" required placeholder="Enter amount in Naira">
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Billing Address</h3>

                <div class="form-group">
                    <label for="billing_address_1">Street Address <span style="color:#c00">*</span></label>
                    <input type="text" id="billing_address_1" name="billing_address_1" required placeholder="House number and street name">
                </div>

                <div class="form-group">
                    <label for="billing_address_2">Apartment, suite, etc. (optional)</label>
                    <input type="text" id="billing_address_2" name="billing_address_2" placeholder="Flat, building, floor">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="billing_city">City <span style="color:#c00">*</span></label>
                        <input type="text" id="billing_city" name="billing_city" required placeholder="e.g. Lagos">
                    </div>
                    <div class="form-group">
                        <label for="billing_state">State <span style="color:#c00">*</span></label>
                        <input type="text" id="billing_state" name="billing_state" required placeholder="e.g. Lagos">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="billing_postcode">Postcode</label>
                        <input type="text" id="billing_postcode" name="billing_postcode" placeholder="e.g. 100001">
                    </div>
                    <div class="form-group">
                        <label for="billing_country">Country</label>
                        <select id="billing_country" name="billing_country">
                            <option value="NG" selected>Nigeria</option>
                            <option value="GH">Ghana</option>
                            <option value="KE">Kenya</option>
                            <option value="ZA">South Africa</option>
                            <option value="GB">United Kingdom</option>
                            <option value="US">United States</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- About CISON -->
            <div class="form-section">
                <h3>About CISON</h3>

                <div class="form-group">
                    <label for="what_they_like">What do you like about CISON? <span style="color:#c00">*</span></label>
                    <textarea id="what_they_like" name="what_they_like" required placeholder="Tell us what you appreciate about CISON..."></textarea>
                </div>

                <div class="form-group">
                    <label for="what_they_want">What would you like CISON to do in the future? <span style="color:#c00">*</span></label>
                    <textarea id="what_they_want" name="what_they_want" required placeholder="Share your hopes and suggestions for CISON's future..."></textarea>
                </div>
            </div>

            <button type="submit" name="cison_donation_submit" class="btn-donate">Proceed to Payment</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cison_donation', 'cison_donation_shortcode');
