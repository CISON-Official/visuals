<?php
/**
 * CISON Fellowship Application - Custom Form + WooCommerce Checkout
 *
 * Shortcodes:
 *   [cison_fellowship_application]   - Main application form
 *   [cison_fellowship_submissions]   - Admin submissions viewer
 *
 * Flow:
 *   1. Applicant fills form → WooCommerce cart → checkout
 *   2. On payment: save to DB, generate token, email sponsor link
 *   3. Sponsor 1 opens token link → fills sponsor 1 fields → submits
 *   4. Sponsor 2 opens token link → fills sponsor 2 fields → submits
 *   5. Application complete
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CISON_FELLOWSHIP_FORM_URL', home_url('/fellowship-application/'));

define('CISON_FELLOWSHIP_PRODUCT_NON_MEMBER', 14837);
define('CISON_FELLOWSHIP_PRODUCT_MEMBER_NON_FELLOW', 14835);
define('CISON_FELLOWSHIP_PRODUCT_NSA_FELLOW', array(14830, 14839, 14841, 14856, 14866));

// ============================================================
// HELPERS
// ============================================================

function cison_fellowship_get_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'cison_fellowship_registrations';
}

function cison_fellowship_get_valid_nsa_fellow_ids()
{
    // Format: NSA/FNSA/YYYYNNN per year.
    $year_max = array(
        2014 => 9,
        2015 => 10,
        2016 => 6,
        2017 => 5,
        2018 => 6,
        2021 => 21,
        2022 => 13,
    );

    $ids = array();
    foreach ($year_max as $year => $max) {
        for ($i = 1; $i <= $max; $i++) {
            $ids[] = sprintf('NSA/FNSA/%d%03d', $year, $i);
        }
    }

    return $ids;
}

function cison_fellowship_is_valid_nsa_fellow_id($id)
{
    if (empty($id)) {
        return false;
    }
    return in_array(strtoupper(trim($id)), cison_fellowship_get_valid_nsa_fellow_ids(), true);
}

function cison_fellowship_get_form_defaults()
{
    return array(
        'title' => '',
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'gender' => '',
        'date_of_birth' => '',
        'nationality' => '',
        'occupation' => '',
        'designation' => '',
        'employer' => '',
        'years_of_practice' => '',
        'area_of_practice' => '',
        'street' => '',
        'city' => '',
        'state' => '',
        'state_manual' => '',
        'country' => 'NG',
        'membership_status' => '',
        'membership_category' => '',
        'membership_number' => '',
        'nsa_fellow' => '',
        'nsa_fellow_id' => '',
        'academic_qualifications' => array(),
        'professional_experience' => '',
        'publications' => '',
        'sponsor_1_name' => '',
        'sponsor_1_membership_id' => '',
        'sponsor_1_membership_status' => '',
        'sponsor_1_rank' => '',
        'sponsor_1_signature' => '',
        'sponsor_1_date' => '',
        'sponsor_2_name' => '',
        'sponsor_2_membership_id' => '',
        'sponsor_2_membership_status' => '',
        'sponsor_2_rank' => '',
        'sponsor_2_signature' => '',
        'sponsor_2_date' => '',
    );
}

function cison_fellowship_get_titles()
{
    return array('Mr', 'Mrs', 'Ms', 'Dr', 'Prof');
}

function cison_fellowship_get_genders()
{
    return array('Male', 'Female', 'Prefer Not to Answer');
}

function cison_fellowship_get_membership_categories()
{
    return array(
        'Registered Statistician',
        'Associate Statistician',
        'Chartered Statistician',
    );
}

function cison_fellowship_get_countries()
{
    return array(
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'KE' => 'Kenya',
        'ZA' => 'South Africa',
        'US' => 'United States',
        'GB' => 'United Kingdom',
    );
}

function cison_fellowship_get_nigerian_states()
{
    return array(
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa',
        'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo',
        'Ekiti', 'Enugu', 'Federal Capital Territory', 'Gombe', 'Imo',
        'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi',
        'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo',
        'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
        'Yobe', 'Zamfara',
    );
}

function cison_fellowship_sanitize($data)
{
    $sanitized = array();
    $text_fields = array(
        'title', 'first_name', 'middle_name', 'last_name', 'phone',
        'gender', 'nationality', 'occupation', 'designation', 'employer',
        'years_of_practice', 'area_of_practice', 'street', 'city',
        'state', 'state_manual', 'country', 'membership_status',
        'membership_category', 'membership_number', 'nsa_fellow', 'nsa_fellow_id',
        'professional_experience', 'publications',
        'sponsor_1_name', 'sponsor_1_membership_id', 'sponsor_1_membership_status',
        'sponsor_1_rank', 'sponsor_1_date',
        'sponsor_2_name', 'sponsor_2_membership_id', 'sponsor_2_membership_status',
        'sponsor_2_rank', 'sponsor_2_date',
    );

    foreach ($text_fields as $field) {
        $sanitized[$field] = isset($data[$field]) ? sanitize_text_field(wp_unslash($data[$field])) : '';
    }

    $sanitized['email'] = isset($data['email']) ? sanitize_email(wp_unslash($data['email'])) : '';
    $sanitized['date_of_birth'] = isset($data['date_of_birth']) ? sanitize_text_field(wp_unslash($data['date_of_birth'])) : '';

    $sanitized['academic_qualifications'] = array();
    if (!empty($data['academic_qualifications']) && is_array($data['academic_qualifications'])) {
        foreach ($data['academic_qualifications'] as $row) {
            if (is_array($row)) {
                $sanitized['academic_qualifications'][] = array_map('sanitize_text_field', $row);
            }
        }
    }

    return $sanitized;
}

function cison_fellowship_handle_sponsor_signature_upload($sponsor_num)
{
    $field_name = "sponsor_{$sponsor_num}_signature";
    
    if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field_name];
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    
    if (!in_array($file['type'], $allowed_types)) {
        return null;
    }

    $upload_dir = wp_upload_dir();
    $sponsor_dir = $upload_dir['path'] . '/fellowship_sponsors';
    
    if (!file_exists($sponsor_dir)) {
        wp_mkdir_p($sponsor_dir);
    }

    $filename = 'sponsor_' . $sponsor_num . '_' . time() . '_' . sanitize_file_name($file['name']);
    $filepath = $sponsor_dir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $upload_dir['url'] . '/fellowship_sponsors/' . $filename;
    }

    return null;
}

function cison_fellowship_validate($data)
{
    $errors = array();

    if (empty($data['first_name'])) {
        $errors[] = 'First name is required.';
    }
    if (empty($data['last_name'])) {
        $errors[] = 'Last name is required.';
    }
    if (empty($data['email']) || !is_email($data['email'])) {
        $errors[] = 'A valid email address is required.';
    }
    if (empty($data['phone'])) {
        $errors[] = 'Phone number is required.';
    }
    if (empty($data['membership_status'])) {
        $errors[] = 'Membership status is required.';
    }

    $status = strtolower($data['membership_status'] ?? '');
    $is_member = in_array($status, array('member'), true);
    $is_nsa_fellow = in_array(strtolower($data['nsa_fellow'] ?? ''), array('yes', 'true', '1'), true);

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    if ($is_member) {
        $member_id = trim($data['membership_number'] ?? '');
        if (!$is_nsa_fellow) {
            if (empty($member_id)) {
                $errors[] = 'CISON membership number is required.';
            }
        }
        if (!empty($member_id)) {
            // Check the member ID exists in BuddyPress profile field 894.
            if (!cison_fellowship_member_id_exists($member_id)) {
                $errors[] = 'The CISON membership number is not recognized. Please check it and try again.';
            } elseif ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE membership_number = %s AND membership_number != '' LIMIT 1",
                $member_id
            ))) {
                $errors[] = 'This CISON membership number has already been used for a fellowship application.';
            }
        }
    }

    if ($is_nsa_fellow) {
        $nsa_id = trim($data['nsa_fellow_id'] ?? '');
        if (empty($nsa_id)) {
            $errors[] = 'NSA fellow ID is required.';
        } else {
            if (!cison_fellowship_is_valid_nsa_fellow_id($nsa_id)) {
                $errors[] = 'The NSA fellow ID provided is not valid. Please check it and try again.';
            } elseif ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE is_nsa_fellow = %s AND nsa_fellow_id = %s AND nsa_fellow_id != '' LIMIT 1",
                'yes',
                strtoupper($nsa_id)
            ))) {
                $errors[] = 'This NSA fellow ID has already been used for a fellowship application.';
            }
        }
    }

    return $errors;
}

function cison_fellowship_member_id_exists($member_id)
{
    global $wpdb;
    if (empty($member_id)) {
        return false;
    }

    $table_name = $wpdb->prefix . 'bp_xprofile_data';

    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
        894,
        sanitize_text_field($member_id)
    ));

    return !empty($user_id);
}

function cison_fellowship_validate_sponsor($data, $sponsor_num)
{
    $errors = array();
    $prefix = "sponsor_{$sponsor_num}_";

    if (empty($data[$prefix . 'name'])) {
        $errors[] = "Sponsor {$sponsor_num} name is required.";
    }
    if (empty($data[$prefix . 'membership_id'])) {
        $errors[] = "Sponsor {$sponsor_num} membership ID is required.";
    }
    if (empty($data[$prefix . 'membership_status'])) {
        $errors[] = "Sponsor {$sponsor_num} membership status is required.";
    }
    if (empty($data[$prefix . 'date'])) {
        $errors[] = "Sponsor {$sponsor_num} date is required.";
    }
    
    // Check signature upload for sponsor
    if (empty($_FILES["sponsor_{$sponsor_num}_signature"]) || $_FILES["sponsor_{$sponsor_num}_signature"]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Sponsor {$sponsor_num} signature is required.";
    }

    return $errors;
}

function cison_fellowship_generate_reference_number()
{
    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    do {
        $reference_number = 'CISON-FS-' . wp_date('Ymd') . '-' . wp_rand(1000, 9999);
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table_name WHERE reference_number = %s LIMIT 1", $reference_number)
        );
    } while ($existing);

    return $reference_number;
}

function cison_fellowship_generate_token()
{
    return bin2hex(random_bytes(16));
}

function cison_fellowship_resolve_products($data)
{
    $status = strtolower($data['membership_status'] ?? '');
    $fellow = strtolower($data['nsa_fellow'] ?? '');

    $is_non_member = in_array($status, array('non-member', 'non_member', 'nonmember'), true);
    $is_member = in_array($status, array('member'), true);

    if ($is_non_member) {
        return array(CISON_FELLOWSHIP_PRODUCT_NON_MEMBER);
    }

    if ($is_member) {
        if (in_array($fellow, array('yes', 'true', '1'), true)) {
            return CISON_FELLOWSHIP_PRODUCT_NSA_FELLOW;
        }
        return array(CISON_FELLOWSHIP_PRODUCT_MEMBER_NON_FELLOW);
    }

    return array(CISON_FELLOWSHIP_PRODUCT_NON_MEMBER);
}

function cison_fellowship_get_application_by_token($token)
{
    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE sponsor_token = %s LIMIT 1", $token),
        ARRAY_A
    );
}

function cison_fellowship_get_token_status($application)
{
    if (empty($application['sponsor_token'])) {
        return 'none';
    }
    if (($application['sponsor_2_status'] ?? '') === 'submitted') {
        return 'complete';
    }
    if (($application['sponsor_1_status'] ?? '') === 'submitted') {
        return 's2';
    }
    return 's1';
}

function cison_fellowship_qualifications_to_string($quals)
{
    if (empty($quals) || !is_array($quals)) {
        return '';
    }

    $lines = array();
    foreach ($quals as $row) {
        if (is_array($row)) {
            $parts = array_filter(array_map('trim', $row));
            $lines[] = implode(' | ', $parts);
        }
    }
    return implode("\n", $lines);
}

function cison_fellowship_sponsor_link($token)
{
    return add_query_arg('token', $token, CISON_FELLOWSHIP_FORM_URL);
}

function cison_fellowship_get_full_name($row)
{
    return implode(' ', array_filter(array(
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? '',
    )));
}

function cison_fellowship_render_status_badge($status)
{
    $normalized = strtolower(trim((string) $status));
    $class = 'cison-fs-badge cison-fs-badge--' . sanitize_html_class($normalized ?: 'unknown');
    return sprintf('<span class="%s">%s</span>', esc_attr($class), esc_html($status ?: 'N/A'));
}

// ============================================================
// APPLICANT FORM SUBMISSION
// ============================================================

function cison_fellowship_handle_applicant_submission()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cison_fellowship_submit'])) {
        return;
    }

    if (!isset($_POST['cison_fellowship_nonce']) || !wp_verify_nonce($_POST['cison_fellowship_nonce'], 'cison_fellowship_action')) {
        return;
    }

    $data = cison_fellowship_sanitize($_POST);
    $errors = cison_fellowship_validate($data);

    if (!empty($errors)) {
        return;
    }

    if (!class_exists('WooCommerce') || !WC()) {
        return;
    }

    if (!WC()->cart) {
        WC()->initialize_cart();
    }

    $product_ids = cison_fellowship_resolve_products($data);

    WC()->cart->empty_cart();

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product && $product->exists()) {
            WC()->cart->add_to_cart($product_id, 1);
        }
    }

    WC()->cart->calculate_totals();
    WC()->session->set('cison_fellowship_entry', $data);

    wp_safe_redirect(wc_get_checkout_url());
    exit;
}
add_action('template_redirect', 'cison_fellowship_handle_applicant_submission');

// ============================================================
// SPONSOR FORM SUBMISSION
// ============================================================

function cison_fellowship_handle_sponsor_submission()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cison_fellowship_sponsor_submit'])) {
        return;
    }

    if (!isset($_POST['cison_fellowship_sponsor_nonce']) || !wp_verify_nonce($_POST['cison_fellowship_sponsor_nonce'], 'cison_fellowship_sponsor_action')) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_POST['sponsor_token'] ?? ''));
    if (empty($token)) {
        return;
    }

    $application = cison_fellowship_get_application_by_token($token);
    if (!$application) {
        return;
    }

    $status = cison_fellowship_get_token_status($application);
    $data = cison_fellowship_sanitize($_POST);

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    if ($status === 's1') {
        $errors = cison_fellowship_validate_sponsor($data, 1);
        if (!empty($errors)) {
            return;
        }

        // Handle signature upload
        $signature_url = cison_fellowship_handle_sponsor_signature_upload(1);
        if ($signature_url) {
            $data['sponsor_1_signature'] = $signature_url;
        }

        $sponsor_data = json_encode(array(
            'name' => $data['sponsor_1_name'],
            'membership_id' => $data['sponsor_1_membership_id'],
            'membership_status' => $data['sponsor_1_membership_status'],
            'rank' => $data['sponsor_1_rank'],
            'signature' => $data['sponsor_1_signature'] ?? '',
            'date' => $data['sponsor_1_date'],
        ));

        $wpdb->update(
            $table_name,
            array(
                'sponsor_1_status' => 'submitted',
                'sponsor_1_data' => $sponsor_data,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $application['id']),
            array('%s', '%s', '%s'),
            array('%d')
        );
    } elseif ($status === 's2') {
        $errors = cison_fellowship_validate_sponsor($data, 2);
        if (!empty($errors)) {
            return;
        }

        // Handle signature upload
        $signature_url = cison_fellowship_handle_sponsor_signature_upload(2);
        if ($signature_url) {
            $data['sponsor_2_signature'] = $signature_url;
        }

        $sponsor_data = json_encode(array(
            'name' => $data['sponsor_2_name'],
            'membership_id' => $data['sponsor_2_membership_id'],
            'membership_status' => $data['sponsor_2_membership_status'],
            'rank' => $data['sponsor_2_rank'],
            'signature' => $data['sponsor_2_signature'] ?? '',
            'date' => $data['sponsor_2_date'],
        ));

        $wpdb->update(
            $table_name,
            array(
                'sponsor_2_status' => 'submitted',
                'sponsor_2_data' => $sponsor_data,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $application['id']),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    wp_safe_redirect(add_query_arg('token', $token, CISON_FELLOWSHIP_FORM_URL));
    exit;
}
add_action('template_redirect', 'cison_fellowship_handle_sponsor_submission');

// ============================================================
// WOOCOMMERCE: SAVE ON PAYMENT COMPLETE
// ============================================================

function cison_fellowship_save_on_payment_complete($order_id)
{
    if (!WC()->session) {
        return;
    }

    $data = WC()->session->get('cison_fellowship_entry');
    if (!$data || !is_array($data)) {
        return;
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    $products = cison_fellowship_resolve_products($data);
    $product_ids = implode(',', $products);
    $token = cison_fellowship_generate_token();

    $membership_number = trim($data['membership_number'] ?? '');
    $nsa_fellow_id = strtoupper(trim($data['nsa_fellow_id'] ?? ''));
    $is_nsa_fellow = in_array(strtolower($data['nsa_fellow'] ?? ''), array('yes', 'true', '1'), true);

    // Ensure unique membership number / NSA fellow ID before storing.
    if (!empty($membership_number) && $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE membership_number = %s AND membership_number != '' LIMIT 1",
        $membership_number
    ))) {
        error_log('CISON Fellowship: duplicate membership number blocked on save: ' . $membership_number . ' (order ' . $order_id . ')');
        WC()->session->__unset('cison_fellowship_entry');
        return;
    }

    if ($is_nsa_fellow && !empty($nsa_fellow_id) && $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE is_nsa_fellow = %s AND nsa_fellow_id = %s AND nsa_fellow_id != '' LIMIT 1",
        'yes',
        $nsa_fellow_id
    ))) {
        error_log('CISON Fellowship: duplicate NSA fellow ID blocked on save: ' . $nsa_fellow_id . ' (order ' . $order_id . ')');
        WC()->session->__unset('cison_fellowship_entry');
        return;
    }

    $insert_data = array(
        'reference_number' => cison_fellowship_generate_reference_number(),
        'order_id' => (int) $order_id,
        'is_member' => $data['membership_status'],
        'is_nsa_fellow' => $data['nsa_fellow'],
        'nsa_fellow_id' => $is_nsa_fellow ? $nsa_fellow_id : '',
        'membership_category' => $data['membership_category'],
        'membership_number' => $membership_number,
        'title' => $data['title'],
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'],
        'last_name' => $data['last_name'],
        'email' => strtolower($data['email']),
        'phone' => $data['phone'],
        'gender' => $data['gender'],
        'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
        'nationality' => $data['nationality'],
        'occupation' => $data['occupation'],
        'designation' => $data['designation'],
        'employer' => $data['employer'],
        'street' => $data['street'],
        'city' => $data['city'],
        'state' => $data['state'],
        'country' => $data['country'],
        'years_of_practice' => $data['years_of_practice'],
        'area_of_practice' => $data['area_of_practice'],
        'academic_qualifications' => cison_fellowship_qualifications_to_string($data['academic_qualifications']),
        'professional_experience' => $data['professional_experience'],
        'publications' => $data['publications'],
        'num_sponsors' => 2,
        'product_ids' => $product_ids,
        'payment_status' => 'paid',
        'application_status' => 'submitted',
        'registration_date' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'sponsor_token' => $token,
        'sponsor_1_status' => 'pending',
        'sponsor_2_status' => 'pending',
    );

    $inserted = $wpdb->insert($table_name, $insert_data);

    if ($inserted) {
        cison_fellowship_send_applicant_email($insert_data, $token);
    }

    WC()->session->__unset('cison_fellowship_entry');
}
add_action('woocommerce_payment_complete', 'cison_fellowship_save_on_payment_complete');
add_action('woocommerce_order_status_completed', 'cison_fellowship_save_on_payment_complete');

// ============================================================
// EMAIL
// ============================================================

function cison_fellowship_send_applicant_email($data, $token)
{
    $email = $data['email'] ?? '';
    if (empty($email) || !is_email($email)) {
        return;
    }

    $first_name = $data['first_name'] ?: 'Applicant';
    $sponsor_link = cison_fellowship_sponsor_link($token);

    $subject = apply_filters(
        'cison_fellowship_email_subject',
        'Congratulations on Your Fellowship Application'
    );

    $message_html = sprintf(
        '<p>Dear %s,</p>' .
        '<p>Congratulations on applying to become a <strong>CISON Fellow</strong>!</p>' .
        '<p>To complete your application, you will need <strong>two sponsors</strong> to vouch for you.</p>' .
        '<p>Please share the link below with your two potential sponsors. They will be able to ' .
        'provide their supporting details directly in your application form:</p>' .
        '<p style="margin:20px 0;"><a href="%s" style="display:inline-block;padding:12px 24px;color:#ffffff;background-color:#0f766e;border-radius:6px;text-decoration:none;">%s</a></p>' .
        '<p style="font-size:13px;color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br>%s</p>' .
        '<p>Once both sponsors have submitted their endorsement, your application will be reviewed by the fellowship committee.</p>' .
        '<p>Best regards,<br>CISON</p>',
        esc_html($first_name),
        esc_url($sponsor_link),
        esc_html($sponsor_link),
        esc_html($sponsor_link)
    );

    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail($email, $subject, $message_html, $headers);
}

// ============================================================
// SHORTCODE: MAIN FORM
// ============================================================

function cison_fellowship_form_shortcode()
{
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
    $application = null;
    $view_status = 'new';

    if (!empty($token)) {
        $application = cison_fellowship_get_application_by_token($token);
        if ($application) {
            $view_status = cison_fellowship_get_token_status($application);
        }
    }

    $has_valid_token = ($view_status === 's1' || $view_status === 's2');

    $values = cison_fellowship_get_form_defaults();
    $feedback_message = '';
    $feedback_type = '';

    if ($view_status === 'new') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cison_fellowship_submit'])) {
            $values = array_merge($values, cison_fellowship_sanitize($_POST));

            if (!isset($_POST['cison_fellowship_nonce']) || !wp_verify_nonce($_POST['cison_fellowship_nonce'], 'cison_fellowship_action')) {
                $feedback_message = 'Security check failed. Please try again.';
                $feedback_type = 'error';
            } else {
                $errors = cison_fellowship_validate($values);
                if (!empty($errors)) {
                    $feedback_message = implode(' ', $errors);
                    $feedback_type = 'error';
                }
            }
        }
    } elseif ($view_status === 's1' || $view_status === 's2') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cison_fellowship_sponsor_submit'])) {
            $feedback_message = 'Security check failed. Please try again.';
            $feedback_type = 'error';
        }
    }

    $titles = cison_fellowship_get_titles();
    $genders = cison_fellowship_get_genders();
    $categories = cison_fellowship_get_membership_categories();
    $countries = cison_fellowship_get_countries();
    $nigerian_states = cison_fellowship_get_nigerian_states();

    $is_member = ($values['membership_status'] === 'member');
    $is_nsa_fellow = ($values['nsa_fellow'] === 'yes');
    $is_nigeria = ($values['country'] ?? 'NG') === 'NG';
    $manual_state_value = $is_nigeria
        ? ($values['state_manual'] ?? '')
        : ($values['state_manual'] ?: $values['state']);

    $sponsor_1_data = !empty($application['sponsor_1_data']) ? json_decode($application['sponsor_1_data'], true) : array();
    $sponsor_2_data = !empty($application['sponsor_2_data']) ? json_decode($application['sponsor_2_data'], true) : array();

    ob_start();
    ?>
    <div class="cison-fs" data-has-token="<?php echo $has_valid_token ? '1' : '0'; ?>">
        <?php if ($view_status === 'complete'): ?>
            <div class="cison-fs__header">
                <h3>Fellowship Application Complete</h3>
                <p>Both sponsors have submitted their endorsements. Your application will be reviewed by the fellowship committee.</p>
            </div>
            <div class="cison-fs__alert cison-fs__alert--success">
                Reference Number: <?php echo esc_html($application['reference_number']); ?>
            </div>

        <?php elseif ($view_status === 's1' || $view_status === 's2'): ?>
            <div class="cison-fs__header">
                <h3>CISON Fellowship - Sponsor Endorsement</h3>
                <p>Please complete the sponsor section below. The applicant's information is shown for reference.</p>
            </div>

            <div class="cison-fs__applicant-info">
                <h4>Applicant Information</h4>
                <div class="cison-fs__info-grid">
                    <div><strong>Name:</strong> <?php echo esc_html(cison_fellowship_get_full_name($application)); ?></div>
                    <div><strong>Email:</strong> <?php echo esc_html($application['email']); ?></div>
                    <div><strong>Phone:</strong> <?php echo esc_html($application['phone']); ?></div>
                    <div><strong>Reference:</strong> <?php echo esc_html($application['reference_number']); ?></div>
                </div>
            </div>

            <form method="post" class="cison-fs__form" enctype="multipart/form-data" novalidate>
                <?php wp_nonce_field('cison_fellowship_sponsor_action', 'cison_fellowship_sponsor_nonce'); ?>
                <input type="hidden" name="sponsor_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="cison_fellowship_sponsor_submit" value="1">

                <?php if ($view_status === 's1'): ?>
                    <div class="cison-fs__section">
                        <h4>Sponsor 1 Details</h4>
                        <?php echo cison_fellowship_render_sponsor_fields(1, $sponsor_1_data, true); ?>
                    </div>

                    <div class="cison-fs__section cison-fs__section--locked">
                        <h4>Sponsor 2</h4>
                        <p class="cison-fs__locked-notice">Sponsor 2 section will be available after Sponsor 1 submits.</p>
                    </div>

                <?php elseif ($view_status === 's2'): ?>
                    <div class="cison-fs__section cison-fs__section--locked">
                        <h4>Sponsor 1 <span class="cison-fs__badge cison-fs__badge--submitted">Submitted</span></h4>
                        <div class="cison-fs__info-grid">
                            <div><strong>Name:</strong> <?php echo esc_html($sponsor_1_data['name'] ?? ''); ?></div>
                            <div><strong>Membership ID:</strong> <?php echo esc_html($sponsor_1_data['membership_id'] ?? ''); ?></div>
                            <div><strong>Membership Status:</strong> <?php echo esc_html($sponsor_1_data['membership_status'] ?? ''); ?></div>
                            <div><strong>Rank:</strong> <?php echo esc_html($sponsor_1_data['rank'] ?? ''); ?></div>
                            <div><strong>Date:</strong> <?php echo esc_html($sponsor_1_data['date'] ?? ''); ?></div>
                            <?php if (!empty($sponsor_1_data['signature'])): ?>
                                <div><strong>Signature:</strong> <a href="<?php echo esc_url($sponsor_1_data['signature']); ?>" target="_blank">View Signature</a></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cison-fs__section">
                        <h4>Sponsor 2 Details</h4>
                        <?php echo cison_fellowship_render_sponsor_fields(2, $sponsor_2_data, true); ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="cison-fs__submit">
                    Submit Sponsor Endorsement
                </button>
            </form>

        <?php else: ?>
            <div class="cison-fs__header">
                <!-- <h3>CISON Fellowship Application</h3> -->
                <p>Complete the form below to apply for CISON Fellowship.</p>
            </div>

            <?php if ($feedback_message): ?>
                <div class="cison-fs__alert cison-fs__alert--<?php echo esc_attr($feedback_type); ?>">
                    <?php echo esc_html($feedback_message); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="cison-fs__form" enctype="multipart/form-data" novalidate>
                <?php wp_nonce_field('cison_fellowship_action', 'cison_fellowship_nonce'); ?>
                <input type="hidden" name="cison_fellowship_submit" value="1">

                <div class="cison-fs__section cison-fs__section--membership">
                    <h4>Present Membership Status in CISON</h4>
                    <p class="cison-fs__help">If you are a non-member, you may qualify for honorary fellowship.</p>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_member_status">Are you a CISON Member? <span>*</span></label>
                            <select id="cison_fs_member_status" name="membership_status" required>
                                <option value="">Select</option>
                                <option value="member" <?php selected($values['membership_status'], 'member'); ?>>Member</option>
                                <option value="non-member" <?php selected($values['membership_status'], 'non-member'); ?>>Non-Member</option>
                            </select>
                        </div>
                    </div>

                    <div class="cison-fs__nsa-fellow-wrap js-nsa-fellow-wrap" style="<?php echo $is_member ? '' : 'display:none;'; ?>">
                        <div class="cison-fs__grid cison-fs__grid--two">
                            <div>
                                <label for="cison_fs_nsa_fellow">Are you an NSA Fellow? <span>*</span></label>
                                <select id="cison_fs_nsa_fellow" name="nsa_fellow">
                                    <option value="">Select</option>
                                    <option value="yes" <?php selected($values['nsa_fellow'], 'yes'); ?>>Yes</option>
                                    <option value="no" <?php selected($values['nsa_fellow'], 'no'); ?>>No</option>
                                </select>
                            </div>
                        </div>

                        <div class="cison-fs__nsa-id-wrap js-nsa-id-wrap" style="<?php echo $is_nsa_fellow ? '' : 'display:none;'; ?>">
                            <div class="cison-fs__grid cison-fs__grid--two">
                                <div>
                                    <label for="cison_fs_nsa_fellow_id">NSA Fellow ID <span>*</span></label>
                                    <input id="cison_fs_nsa_fellow_id" type="text" name="nsa_fellow_id" value="<?php echo esc_attr($values['nsa_fellow_id']); ?>" placeholder="e.g. NSA/FNSA/2021001" <?php echo $is_nsa_fellow ? 'required' : ''; ?>>
                                    <span class="cison-fs__help">Enter the NSA Fellow ID shown on your fellowship certificate.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section">
                    <h4>Personal Information</h4>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_title">Title <span>*</span></label>
                            <select id="cison_fs_title" name="title" required>
                                <option value="">Select</option>
                                <?php foreach ($titles as $t): ?>
                                    <option value="<?php echo esc_attr($t); ?>" <?php selected($values['title'], $t); ?>><?php echo esc_html($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--three">
                        <div>
                            <label for="cison_fs_first_name">First Name <span>*</span></label>
                            <input id="cison_fs_first_name" type="text" name="first_name" value="<?php echo esc_attr($values['first_name']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_middle_name">Middle Name</label>
                            <input id="cison_fs_middle_name" type="text" name="middle_name" value="<?php echo esc_attr($values['middle_name']); ?>">
                        </div>
                        <div>
                            <label for="cison_fs_last_name">Last Name <span>*</span></label>
                            <input id="cison_fs_last_name" type="text" name="last_name" value="<?php echo esc_attr($values['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_email">Email Address <span>*</span></label>
                            <input id="cison_fs_email" type="email" name="email" value="<?php echo esc_attr($values['email']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_phone">Phone Number <span>*</span></label>
                            <input id="cison_fs_phone" type="tel" name="phone" value="<?php echo esc_attr($values['phone']); ?>" required>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_gender">Gender</label>
                            <select id="cison_fs_gender" name="gender">
                                <option value="">Select</option>
                                <?php foreach ($genders as $g): ?>
                                    <option value="<?php echo esc_attr($g); ?>" <?php selected($values['gender'], $g); ?>><?php echo esc_html($g); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="cison_fs_dob">Date of Birth</label>
                            <input id="cison_fs_dob" type="date" name="date_of_birth" value="<?php echo esc_attr($values['date_of_birth']); ?>">
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_nationality">Nationality</label>
                            <input id="cison_fs_nationality" type="text" name="nationality" value="<?php echo esc_attr($values['nationality']); ?>">
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section">
                    <h4>Residential Address</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_street">Street Address</label>
                            <input id="cison_fs_street" type="text" name="street" value="<?php echo esc_attr($values['street']); ?>" placeholder="House number and street name">
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--three">
                        <div>
                            <label for="cison_fs_city">City</label>
                            <input id="cison_fs_city" type="text" name="city" value="<?php echo esc_attr($values['city']); ?>">
                        </div>
                        <div>
                            <label for="cison_fs_country">Country</label>
                            <select id="cison_fs_country" name="country">
                                <?php foreach ($countries as $code => $label): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($values['country'], $code); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="js-state-select-wrap" style="<?php echo $is_nigeria ? '' : 'display:none;'; ?>">
                            <label for="cison_fs_state">State</label>
                            <select id="cison_fs_state" name="state" <?php echo $is_nigeria ? '' : 'disabled'; ?>>
                                <option value="">Select</option>
                                <?php foreach ($nigerian_states as $state): ?>
                                    <option value="<?php echo esc_attr($state); ?>" <?php selected($values['state'], $state); ?>><?php echo esc_html($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="js-state-manual-wrap" style="<?php echo $is_nigeria ? 'display:none;' : ''; ?>">
                            <label for="cison_fs_state_manual">State / Region</label>
                            <input id="cison_fs_state_manual" type="text" name="state_manual" value="<?php echo esc_attr($manual_state_value); ?>" <?php echo $is_nigeria ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section">
                    <h4>Professional Information</h4>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_occupation">Current Occupation <span>*</span></label>
                            <input id="cison_fs_occupation" type="text" name="occupation" value="<?php echo esc_attr($values['occupation']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_designation">Designation</label>
                            <input id="cison_fs_designation" type="text" name="designation" value="<?php echo esc_attr($values['designation']); ?>">
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_employer">Employer / Institution</label>
                            <input id="cison_fs_employer" type="text" name="employer" value="<?php echo esc_attr($values['employer']); ?>">
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="additional">
                    <h4>Additional Information</h4>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_years">Years of Practice</label>
                            <input id="cison_fs_years" type="text" name="years_of_practice" value="<?php echo esc_attr($values['years_of_practice']); ?>">
                        </div>
                        <div>
                            <label for="cison_fs_member_number">CISON Member Number <?php echo ($is_member && !$is_nsa_fellow) ? '<span>*</span>' : ''; ?></label>
                            <input id="cison_fs_member_number" type="text" name="membership_number" value="<?php echo esc_attr($values['membership_number']); ?>" <?php echo ($is_member && !$is_nsa_fellow) ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_area">Area of Statistics</label>
                            <textarea id="cison_fs_area" name="area_of_practice" rows="3"><?php echo esc_textarea($values['area_of_practice']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="membership-details">
                    <h4>Membership Details</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_member_category">Membership Category</label>
                            <select id="cison_fs_member_category" name="membership_category">
                                <option value="">Select</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($values['membership_category'], $cat); ?>><?php echo esc_html($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="qualifications">
                    <h4>Academic & Professional Background</h4>

                    <div class="cison-fs__qualifications">
                        <label>Academic Qualifications</label>
                        <div id="cison-fs-quals" class="cison-fs__quals-list">
                            <?php
                            $quals = $values['academic_qualifications'];
                            if (empty($quals)) {
                                $quals = array(array('institution' => '', 'degree' => '', 'year' => ''));
                            }
                            foreach ($quals as $i => $qual):
                            ?>
                            <div class="cison-fs__qual-row">
                                <input type="text" name="academic_qualifications[<?php echo $i; ?>][institution]" placeholder="Institution" value="<?php echo esc_attr($qual['institution'] ?? ''); ?>">
                                <input type="text" name="academic_qualifications[<?php echo $i; ?>][degree]" placeholder="Degree / Qualification" value="<?php echo esc_attr($qual['degree'] ?? ''); ?>">
                                <input type="text" name="academic_qualifications[<?php echo $i; ?>][year]" placeholder="Year" value="<?php echo esc_attr($qual['year'] ?? ''); ?>" class="cison-fs__qual-year">
                                <button type="button" class="cison-fs__qual-remove" title="Remove">&times;</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="cison-fs-add-qual" class="cison-fs__qual-add">+ Add Qualification</button>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_experience">Professional Experience</label>
                            <textarea id="cison_fs_experience" name="professional_experience" rows="4"><?php echo esc_textarea($values['professional_experience']); ?></textarea>
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_publications">Publications, Research and Contribution</label>
                            <textarea id="cison_fs_publications" name="publications" rows="4"><?php echo esc_textarea($values['publications']); ?></textarea>
                        </div>
                    </div>
                </div>

                <?php if ($has_valid_token): ?>
                <div class="cison-fs__section js-form-section" data-section="sponsors">
                    <h4>Sponsors</h4>
                    <p class="cison-fs__help">You need two sponsors to endorse your application. Their details are required below.</p>

                    <div class="cison-fs__sponsor-group">
                        <h5>Sponsor 1</h5>
                        <?php echo cison_fellowship_render_sponsor_fields(1, array(
                            'name' => $values['sponsor_1_name'],
                            'email' => $values['sponsor_1_email'],
                            'phone' => $values['sponsor_1_phone'],
                            'organization' => $values['sponsor_1_organization'],
                            'relationship' => $values['sponsor_1_relationship'],
                        ), true); ?>
                    </div>

                    <div class="cison-fs__sponsor-group">
                        <h5>Sponsor 2</h5>
                        <?php echo cison_fellowship_render_sponsor_fields(2, array(
                            'name' => $values['sponsor_2_name'],
                            'email' => $values['sponsor_2_email'],
                            'phone' => $values['sponsor_2_phone'],
                            'organization' => $values['sponsor_2_organization'],
                            'relationship' => $values['sponsor_2_relationship'],
                        ), true); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="cison-fs__section">
                    <h4>Signature</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_signature">Upload Signature (Image)</label>
                            <input id="cison_fs_signature" type="file" name="signature" accept="image/*">
                            <span class="cison-fs__help">Accepted formats: JPG, PNG, GIF. Max size: 2MB.</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="cison-fs__submit">
                    Submit Application &amp; Proceed to Payment
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo cison_fellowship_render_styles(); ?>
    <?php echo cison_fellowship_render_scripts($is_member, $is_nsa_fellow); ?>
    <?php
    return ob_get_clean();
}
add_shortcode('cison_fellowship_application', 'cison_fellowship_form_shortcode');

function cison_fellowship_render_sponsor_fields($num, $data, $editable)
{
    $readonly_attr = $editable ? '' : 'readonly';
    $disabled_attr = $editable ? '' : 'disabled';
    $d = array_merge(array(
        'name' => '', 'membership_id' => '', 'membership_status' => '',
        'rank' => '', 'signature' => '', 'date' => '',
    ), $data);

    ob_start();
    ?>
    <div class="cison-fs__grid">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_name">Full Name <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_name" type="text" name="sponsor_<?php echo $num; ?>_name" value="<?php echo esc_attr($d['name']); ?>" <?php echo $editable ? 'required' : ''; ?> <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <div class="cison-fs__grid cison-fs__grid--two">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_membership_id">Membership ID <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_membership_id" type="text" name="sponsor_<?php echo $num; ?>_membership_id" value="<?php echo esc_attr($d['membership_id']); ?>" <?php echo $editable ? 'required' : ''; ?> <?php echo $readonly_attr; ?>>
        </div>
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_membership_status">Membership Status <span>*</span></label>
            <select id="cison_fs_s<?php echo $num; ?>_membership_status" name="sponsor_<?php echo $num; ?>_membership_status" <?php echo $editable ? 'required' : 'disabled'; ?>>
                <option value="">Select</option>
                <option value="Registered Statistician" <?php selected($d['membership_status'], 'Registered Statistician'); ?>>Registered Statistician</option>
                <option value="Associate Statistician" <?php selected($d['membership_status'], 'Associate Statistician'); ?>>Associate Statistician</option>
                <option value="Chartered Statistician" <?php selected($d['membership_status'], 'Chartered Statistician'); ?>>Chartered Statistician</option>
            </select>
        </div>
    </div>
    <div class="cison-fs__grid cison-fs__grid--two">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_rank">Rank</label>
            <input id="cison_fs_s<?php echo $num; ?>_rank" type="text" name="sponsor_<?php echo $num; ?>_rank" value="<?php echo esc_attr($d['rank']); ?>" <?php echo $readonly_attr; ?>>
        </div>
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_signature">Signature (Image) <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_signature" type="file" name="sponsor_<?php echo $num; ?>_signature" accept="image/*" <?php echo $editable ? 'required' : 'disabled'; ?>>
            <?php if (!empty($d['signature'])): ?>
                <span class="cison-fs__help">Current: <a href="<?php echo esc_url($d['signature']); ?>" target="_blank">View Signature</a></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="cison-fs__grid">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_date">Date <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_date" type="date" name="sponsor_<?php echo $num; ?>_date" value="<?php echo esc_attr($d['date']); ?>" <?php echo $editable ? 'required' : ''; ?> <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// SHORTCODE: ADMIN SUBMISSIONS VIEWER
// ============================================================

function cison_fellowship_submissions_shortcode($atts)
{
    if (!current_user_can('manage_options')) {
        return '<p>You do not have permission to view fellowship submissions.</p>';
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return '<p style="color:red;">Error: Fellowship submissions table not found.</p>';
    }

    $atts = shortcode_atts(array('per_page' => 20), $atts);

    $search = isset($_GET['fs_s']) ? sanitize_text_field(wp_unslash($_GET['fs_s'])) : '';
    $filter_key = 'fs_filter_payment_status';
    $filter_value = isset($_GET[$filter_key]) ? sanitize_text_field(wp_unslash($_GET[$filter_key])) : '';
    $paged = isset($_GET['fs_paged']) ? max(1, intval($_GET['fs_paged'])) : 1;
    $per_page = max(1, intval($atts['per_page']));
    $offset = ($paged - 1) * $per_page;

    $where_clauses = array('1=1');
    $query_params = array();

    if ($search) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = '(reference_number LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
        for ($i = 0; $i < 5; $i++) {
            $query_params[] = $search_term;
        }
    }

    if ($filter_value) {
        $where_clauses[] = 'payment_status = %s';
        $query_params[] = $filter_value;
    }

    $where_sql = implode(' AND ', $where_clauses);

    $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
    if ($query_params) {
        $count_query = $wpdb->prepare($count_query, $query_params);
    }
    $total_items = (int) $wpdb->get_var($count_query);
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    $query_params_with_paging = array_merge($query_params, array($per_page, $offset));
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE $where_sql ORDER BY registration_date DESC LIMIT %d OFFSET %d",
        $query_params_with_paging
    );
    $results = $wpdb->get_results($query, ARRAY_A);
    $filter_options = $wpdb->get_col("SELECT DISTINCT payment_status FROM $table_name WHERE payment_status != '' ORDER BY payment_status ASC");

    ob_start();
    ?>
    <div class="cison-fs-submissions">
        <div class="cison-fs-submissions__controls">
            <form method="get" class="cison-fs-submissions__search">
                <input type="text" name="fs_s" value="<?php echo esc_attr($search); ?>" placeholder="Search by name, email, reference...">
                <button type="submit">Search</button>
                <?php if ($search || $filter_value): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('fs_s', 'fs_paged', $filter_key))); ?>">Clear</a>
                <?php endif; ?>
            </form>

            <form method="get" class="cison-fs-submissions__filter">
                <select name="<?php echo esc_attr($filter_key); ?>" onchange="this.form.submit()">
                    <option value="">All Payment Status</option>
                    <?php foreach ($filter_options as $option): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($filter_value, $option); ?>><?php echo esc_html($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="fs_s" value="<?php echo esc_attr($search); ?>">
            </form>
        </div>

        <div class="cison-fs-submissions__table-wrap">
            <table class="cison-fs-submissions__table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Membership</th>
                        <th>Sponsor 1</th>
                        <th>Sponsor 2</th>
                        <th>Payment</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): ?>
                        <?php foreach ($results as $row): ?>
                            <?php
                            $s1_data = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
                            $s2_data = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();
                            ?>
                            <tr>
                                <td><?php echo esc_html($row['reference_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo esc_html(cison_fellowship_get_full_name($row)); ?></strong><br>
                                    <small><?php echo esc_html($row['phone'] ?: ''); ?></small>
                                </td>
                                <td><?php echo esc_html($row['email']); ?></td>
                                <td><?php echo esc_html($row['is_member'] ?: 'N/A'); ?>
                                    <?php if (strtolower($row['is_nsa_fellow'] ?? '') === 'yes'): ?>
                                        <br><small>NSA Fellow</small>
                                        <?php if (!empty($row['nsa_fellow_id'])): ?>
                                            <br><small><?php echo esc_html($row['nsa_fellow_id']); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo cison_fellowship_render_status_badge($row['sponsor_1_status'] ?? 'pending'); ?>
                                    <?php if (!empty($s1_data['name'])): ?>
                                        <br><small><?php echo esc_html($s1_data['name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo cison_fellowship_render_status_badge($row['sponsor_2_status'] ?? 'pending'); ?>
                                    <?php if (!empty($s2_data['name'])): ?>
                                        <br><small><?php echo esc_html($s2_data['name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo cison_fellowship_render_status_badge($row['payment_status']); ?></td>
                                <td><?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($row['registration_date']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No fellowship submissions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="cison-fs-submissions__pagination">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('fs_paged', '%#%'),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $paged,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'add_args' => array(
                        'fs_s' => $search,
                        $filter_key => $filter_value,
                    ),
                ));
                ?>
            </div>
        <?php endif; ?>
    </div>

    <?php echo cison_fellowship_submissions_styles(); ?>
    <?php
    return ob_get_clean();
}
add_shortcode('cison_fellowship_submissions', 'cison_fellowship_submissions_shortcode');

// ============================================================
// STYLES
// ============================================================

function cison_fellowship_render_styles()
{
    return '
    <style>
        .cison-fs {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
        }

        .cison-fs__header {
            margin-bottom: 24px;
        }

        .cison-fs__header h3 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            color: #0f172a;
        }

        .cison-fs__header p {
            margin: 0;
            color: #475569;
        }

        .cison-fs__alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
        }

        .cison-fs__alert--success {
            background: #dcfce7;
            color: #166534;
        }

        .cison-fs__alert--error {
            background: #fee2e2;
            color: #991b1b;
        }

        .cison-fs__section {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .cison-fs__section:last-of-type {
            border-bottom: none;
        }

        .cison-fs__section h4 {
            margin: 0 0 16px;
            font-size: 1.2rem;
            color: #0f172a;
        }

        .cison-fs__section h5 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #334155;
        }

        .cison-fs__form label {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .cison-fs__form label span {
            color: #dc2626;
        }

        .cison-fs__help {
            margin: 0 0 16px;
            color: #64748b;
            font-size: 13px;
        }

        .cison-fs__grid {
            display: grid;
            gap: 16px;
            margin-bottom: 16px;
        }

        .cison-fs__grid--two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cison-fs__grid--three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .cison-fs__form input,
        .cison-fs__form select,
        .cison-fs__form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            background: #fff;
            box-sizing: border-box;
        }

        .cison-fs__form input[readonly],
        .cison-fs__form textarea[readonly] {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }

        .cison-fs__submit {
            display: block;
            width: 100%;
            border: 0;
            border-radius: 999px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            background: #0f766e;
            cursor: pointer;
            margin-top: 16px;
        }

        .cison-fs__submit:hover {
            background: #115e59;
        }

        .cison-fs__applicant-info {
            margin-bottom: 24px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .cison-fs__applicant-info h4 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #334155;
        }

        .cison-fs__info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .cison-fs__info-grid div {
            font-size: 14px;
            color: #475569;
        }

        .cison-fs__section--locked {
            opacity: 0.6;
        }

        .cison-fs__locked-notice {
            color: #64748b;
            font-style: italic;
        }

        .cison-fs__badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .cison-fs__badge--submitted {
            background: #dcfce7;
            color: #166534;
        }

        .cison-fs__badge--pending {
            background: #fef3c7;
            color: #92400e;
        }

        .cison-fs__sponsor-group {
            margin-bottom: 20px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .cison-fs__qualifications {
            margin-bottom: 16px;
        }

        .cison-fs__qualifications > label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0f172a;
            font-size: 14px;
        }

        .cison-fs__quals-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .cison-fs__qual-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr auto;
            gap: 8px;
            align-items: center;
        }

        .cison-fs__qual-row input {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
        }

        .cison-fs__qual-year {
            max-width: 80px;
        }

        .cison-fs__qual-remove {
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cison-fs__qual-remove:hover {
            background: #fecaca;
        }

        .cison-fs__qual-add {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            background: transparent;
            color: #0f766e;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .cison-fs__qual-add:hover {
            border-color: #0f766e;
            background: #f0fdfa;
        }

        .cison-fs__nsa-fellow-wrap {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .cison-fs__nsa-id-wrap {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        @media (max-width: 768px) {
            .cison-fs__grid--two,
            .cison-fs__grid--three {
                grid-template-columns: 1fr;
            }

            .cison-fs__qual-row {
                grid-template-columns: 1fr;
            }

            .cison-fs__qual-year {
                max-width: none;
            }

            .cison-fs__info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>';
}

function cison_fellowship_submissions_styles()
{
    return '
    <style>
        .cison-fs-submissions {
            margin: 24px 0;
        }

        .cison-fs-submissions__controls {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .cison-fs-submissions__search,
        .cison-fs-submissions__filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .cison-fs-submissions input,
        .cison-fs-submissions select,
        .cison-fs-submissions button {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        .cison-fs-submissions button {
            border: 0;
            background: #0f766e;
            color: #fff;
            cursor: pointer;
        }

        .cison-fs-submissions__table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
        }

        .cison-fs-submissions__table {
            width: 100%;
            border-collapse: collapse;
        }

        .cison-fs-submissions__table th,
        .cison-fs-submissions__table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .cison-fs-submissions__table th {
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cison-fs-submissions__pagination {
            margin-top: 18px;
        }

        .cison-fs-submissions__pagination .page-numbers {
            display: inline-block;
            margin-right: 8px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-decoration: none;
        }

        .cison-fs-submissions__pagination .current {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }

        .cison-fs-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            background: #e2e8f0;
            color: #0f172a;
        }

        .cison-fs-badge--submitted,
        .cison-fs-badge--paid {
            background: #dcfce7;
            color: #166534;
        }

        .cison-fs-badge--pending {
            background: #fef3c7;
            color: #92400e;
        }

        .cison-fs-badge--rejected,
        .cison-fs-badge--failed {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>';
}

// ============================================================
// JAVASCRIPT
// ============================================================

function cison_fellowship_render_scripts($is_member = false, $is_nsa_fellow = false)
{
    ob_start();
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var container = document.querySelector(".cison-fs");
        if (!container) return;

        var hasToken = container.getAttribute("data-has-token") === "1";

        // State field toggle
        var countrySelect = container.querySelector("[name='country']");
        var stateSelectWrap = container.querySelector(".js-state-select-wrap");
        var stateSelect = container.querySelector("[name='state']");
        var stateManualWrap = container.querySelector(".js-state-manual-wrap");
        var stateManual = container.querySelector("[name='state_manual']");

        function toggleState() {
            var isNigeria = countrySelect && countrySelect.value === "NG";
            if (stateSelectWrap) stateSelectWrap.style.display = isNigeria ? "" : "none";
            if (stateManualWrap) stateManualWrap.style.display = isNigeria ? "none" : "";
            if (stateSelect) {
                stateSelect.disabled = !isNigeria;
                if (!isNigeria) stateSelect.value = "";
            }
            if (stateManual) {
                stateManual.disabled = isNigeria;
                if (isNigeria) stateManual.value = "";
            }
        }
        if (countrySelect) countrySelect.addEventListener("change", toggleState);
        toggleState();

        // Conditional form flow based on membership and NSA fellow status
        var memberStatus = container.querySelector("[name='membership_status']");
        var nsaFellow = container.querySelector("[name='nsa_fellow']");
        var nsaFellowWrap = container.querySelector(".js-nsa-fellow-wrap");
        var formSections = container.querySelectorAll(".js-form-section");

        function toggleFormSections() {
            var status = memberStatus ? memberStatus.value : "";
            var isMember = status === "member";
            var isNonMember = status === "non-member";

            // Show/hide NSA Fellow question
            if (nsaFellowWrap) {
                nsaFellowWrap.style.display = isMember ? "" : "none";
            }
            if (nsaFellow) {
                nsaFellow.disabled = !isMember;
                if (!isMember) nsaFellow.value = "";
            }

            // Show/hide NSA Fellow ID input
            var nsaIdWrap = container.querySelector(".js-nsa-id-wrap");
            var nsaIdInput = container.querySelector("[name='nsa_fellow_id']");
            var isNsaFellowYes = isMember && nsaFellow && nsaFellow.value === "yes";
            if (nsaIdWrap) {
                nsaIdWrap.style.display = isNsaFellowYes ? "" : "none";
            }
            if (nsaIdInput) {
                if (isNsaFellowYes) {
                    nsaIdInput.setAttribute("required", "required");
                } else {
                    nsaIdInput.removeAttribute("required");
                    if (!isNsaFellowYes) nsaIdInput.value = "";
                }
            }

            // Determine if additional form sections should be visible
            var showSections = false;

            if (isNonMember) {
                showSections = false;
            } else if (isMember) {
                if (nsaFellow && nsaFellow.value === "yes") {
                    showSections = false;
                } else if (nsaFellow && nsaFellow.value === "no") {
                    showSections = true;
                }
            }

            // Toggle additional form sections
            formSections.forEach(function(section) {
                var sectionName = section.getAttribute("data-section");
                if (sectionName === "sponsors" && !hasToken) {
                    section.style.display = "none";
                    return;
                }
                section.style.display = showSections ? "" : "none";
            });

            // CISON member number is required for members who are not NSA fellows
            var memberNumberInput = container.querySelector("[name='membership_number']");
            if (memberNumberInput) {
                var memberNumberRequired = isMember && !isNsaFellowYes;
                if (memberNumberRequired) {
                    memberNumberInput.setAttribute("required", "required");
                } else {
                    memberNumberInput.removeAttribute("required");
                }
            }
        }

        if (memberStatus) memberStatus.addEventListener("change", toggleFormSections);
        if (nsaFellow) nsaFellow.addEventListener("change", toggleFormSections);
        toggleFormSections();

        // Qualifications add/remove
        var qualsList = container.querySelector("#cison-fs-quals");
        var addQualBtn = container.querySelector("#cison-fs-add-qual");

        function updateQualIndices() {
            if (!qualsList) return;
            var rows = qualsList.querySelectorAll(".cison-fs__qual-row");
            rows.forEach(function(row, i) {
                row.querySelectorAll("input").forEach(function(input) {
                    var name = input.getAttribute("name");
                    if (name) {
                        input.setAttribute("name", name.replace(/academic_qualifications\[\d+\]/, "academic_qualifications[" + i + "]"));
                    }
                });
            });
        }

        if (addQualBtn) {
            addQualBtn.addEventListener("click", function() {
                var row = document.createElement("div");
                row.className = "cison-fs__qual-row";
                var idx = qualsList.querySelectorAll(".cison-fs__qual-row").length;
                row.innerHTML = '<input type="text" name="academic_qualifications[' + idx + '][institution]" placeholder="Institution">' +
                    '<input type="text" name="academic_qualifications[' + idx + '][degree]" placeholder="Degree / Qualification">' +
                    '<input type="text" name="academic_qualifications[' + idx + '][year]" placeholder="Year" class="cison-fs__qual-year">' +
                    '<button type="button" class="cison-fs__qual-remove" title="Remove">&times;</button>';
                qualsList.appendChild(row);
            });
        }

        if (qualsList) {
            qualsList.addEventListener("click", function(e) {
                if (e.target.classList.contains("cison-fs__qual-remove")) {
                    var row = e.target.closest(".cison-fs__qual-row");
                    if (row) {
                        row.remove();
                        updateQualIndices();
                    }
                }
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
