<?php
/**
 * CISON Fellowship Application - Custom Form + WooCommerce Checkout
 *
 * Shortcodes:
 *   [cison_fellowship_application]      - Main application form
 *   [cison_fellowship_submissions]      - Admin submissions viewer (list page: /fellowship-submissions-2026/)
 *   [cison_fellowship_submission_detail] - Admin single submission detail (detail page: /fellowship-2026-detail-submission/)
 *
 * Admin features:
 *   - List rows link to the detail page using the submission reference number (?fs_ref=...)
 *   - Email a single submission from the detail page to one or more addresses
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
define('CISON_FELLOWSHIP_SUBMISSIONS_URL', home_url('/fellowship-submissions-2026/'));
define('CISON_FELLOWSHIP_DETAIL_URL', home_url('/fellowship-2026-detail-submission/'));

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
        2012 => 1,
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
        'cv' => '',
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
        'Abia',
        'Adamawa',
        'Akwa Ibom',
        'Anambra',
        'Bauchi',
        'Bayelsa',
        'Benue',
        'Borno',
        'Cross River',
        'Delta',
        'Ebonyi',
        'Edo',
        'Ekiti',
        'Enugu',
        'Federal Capital Territory',
        'Gombe',
        'Imo',
        'Jigawa',
        'Kaduna',
        'Kano',
        'Katsina',
        'Kebbi',
        'Kogi',
        'Kwara',
        'Lagos',
        'Nasarawa',
        'Niger',
        'Ogun',
        'Ondo',
        'Osun',
        'Oyo',
        'Plateau',
        'Rivers',
        'Sokoto',
        'Taraba',
        'Yobe',
        'Zamfara',
    );
}

function cison_fellowship_sanitize($data)
{
    $sanitized = array();
    $text_fields = array(
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'gender',
        'nationality',
        'occupation',
        'designation',
        'employer',
        'years_of_practice',
        'area_of_practice',
        'street',
        'city',
        'state',
        'state_manual',
        'country',
        'membership_status',
        'membership_category',
        'membership_number',
        'nsa_fellow',
        'nsa_fellow_id',
        'professional_experience',
        'publications',
        'sponsor_1_name',
        'sponsor_1_membership_id',
        'sponsor_1_membership_status',
        'sponsor_1_rank',
        'sponsor_1_date',
        'sponsor_2_name',
        'sponsor_2_membership_id',
        'sponsor_2_membership_status',
        'sponsor_2_rank',
        'sponsor_2_date',
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

function cison_fellowship_handle_applicant_signature_upload()
{
    $field_name = 'signature';

    if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field_name];
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

    if (!in_array($file['type'], $allowed_types)) {
        return null;
    }

    $upload_dir = wp_upload_dir();
    $signature_dir = $upload_dir['path'] . '/fellowship_applicants';

    if (!file_exists($signature_dir)) {
        wp_mkdir_p($signature_dir);
    }

    $filename = 'applicant_' . time() . '_' . sanitize_file_name($file['name']);
    $filepath = $signature_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $upload_dir['url'] . '/fellowship_applicants/' . $filename;
    }

    return null;
}

/**
 * Handle the optional multi-file certificate upload (field name "certificates").
 * Returns an array of file URLs, or null when no (valid) files were uploaded.
 */
function cison_fellowship_handle_certificates_upload()
{
    if (empty($_FILES['certificates']) || !is_array($_FILES['certificates']['name'] ?? null)) {
        return null;
    }

    $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf');
    $allowed_exts  = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf');
    $max_size      = 2 * 1024 * 1024;

    $uploaded = array();
    $count = count($_FILES['certificates']['name']);

    for ($i = 0; $i < $count; $i++) {
        $error = $_FILES['certificates']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $name  = $_FILES['certificates']['name'][$i] ?? '';

        if ($error !== UPLOAD_ERR_OK || $name === '') {
            continue;
        }

        $filetype = wp_check_filetype($name);
        if (!in_array(strtolower($filetype['ext']), $allowed_exts, true)) {
            continue;
        }

        $size = $_FILES['certificates']['size'][$i] ?? 0;
        if ((int) $size > $max_size) {
            continue;
        }

        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['path'] . '/fellowship_certificates';

        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        $filename = 'cert_' . time() . '_' . $i . '_' . sanitize_file_name($name);
        $filepath = $cert_dir . '/' . $filename;

        if (move_uploaded_file($_FILES['certificates']['tmp_name'][$i], $filepath)) {
            $uploaded[] = $upload_dir['url'] . '/fellowship_certificates/' . $filename;
        }
    }

    return !empty($uploaded) ? $uploaded : null;
}

function cison_fellowship_handle_cv_upload()
{
    if (empty($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_exts  = array('pdf', 'doc', 'docx', 'txt');
    $max_size      = 5 * 1024 * 1024;

    $file = $_FILES['cv'];

    $filetype = wp_check_filetype($file['name']);
    if (!in_array(strtolower($filetype['ext']), $allowed_exts, true)) {
        return null;
    }

    if ((int) $file['size'] > $max_size) {
        return null;
    }

    $upload_dir = wp_upload_dir();
    $cv_dir = $upload_dir['path'] . '/fellowship_cv';

    if (!file_exists($cv_dir)) {
        wp_mkdir_p($cv_dir);
    }

    $filename = 'cv_' . time() . '_' . sanitize_file_name($file['name']);
    $filepath = $cv_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $upload_dir['url'] . '/fellowship_cv/' . $filename;
    }

    return null;
}

function cison_fellowship_validate($data, $edit_id = 0)
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

    // When editing an existing application, exclude the applicant's own row
    // from uniqueness checks below.
    $id_exclude_sql  = '';
    $id_exclude_args = array();
    if ($edit_id > 0) {
        $id_exclude_sql  = ' AND id != %d';
        $id_exclude_args = array((int) $edit_id);
    }

    $member_id = trim($data['membership_number'] ?? '');
    if (empty($member_id)) {
        $errors[] = 'CISON membership number is required.';
    } else {
        // Check the member ID exists in BuddyPress profile field 894.
        if (!cison_fellowship_member_id_exists($member_id)) {
            $errors[] = 'The CISON membership number is not recognized. Please check it and try again.';
        } elseif (
            $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE membership_number = %s AND membership_number != '' $id_exclude_sql LIMIT 1",
                array_merge(array($member_id), $id_exclude_args)
            ))
        ) {
            $errors[] = 'This CISON membership number has already been used for a fellowship application.';
        }
    }

    // A CV is only mandatory when the application does not already have one
    // (kept from the original submission while editing).
    $existing_cv = '';
    if ($edit_id > 0) {
        $existing_cv = trim((string) $wpdb->get_var($wpdb->prepare(
            "SELECT cv FROM $table_name WHERE id = %d",
            (int) $edit_id
        )));
    }

    $cv_uploaded = !empty($_FILES['cv'])
        && is_array($_FILES['cv'])
        && ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

    if (!$cv_uploaded && $existing_cv === '') {
        $errors[] = 'A curriculum vitae (CV) is required.';
    }
    if ($cv_uploaded) {
        $cv_type = wp_check_filetype($_FILES['cv']['name']);
        if (!in_array(strtolower($cv_type['ext']), array('pdf', 'doc', 'docx', 'txt'), true)) {
            $errors[] = 'The CV must be a PDF, DOC, DOCX or TXT file.';
        }
        if ((int) ($_FILES['cv']['size'] ?? 0) > 5 * 1024 * 1024) {
            $errors[] = 'The CV file must be 5MB or smaller.';
        }
    }

    if ($is_nsa_fellow) {
        $nsa_id = trim($data['nsa_fellow_id'] ?? '');
        if (empty($nsa_id)) {
            $errors[] = 'NSA fellow ID is required.';
        } else {
            if (!cison_fellowship_is_valid_nsa_fellow_id($nsa_id)) {
                $errors[] = 'The NSA fellow ID provided is not valid. Please check it and try again.';
            } elseif (
                $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE is_nsa_fellow = %s AND nsa_fellow_id = %s AND nsa_fellow_id != '' $id_exclude_sql LIMIT 1",
                    array_merge(array('yes', strtoupper($nsa_id)), $id_exclude_args)
                ))
            ) {
                $errors[] = 'This NSA fellow ID has already been used for a fellowship application.';
            }
        }

        $outstanding_fee_error = cison_fellowship_get_outstanding_fee_error($member_id);
        if ($outstanding_fee_error !== '') {
            $errors[] = $outstanding_fee_error;
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

function cison_fellowship_get_outstanding_fee_error($member_id)
{
    if (
        !function_exists('bp_get_userid_by_profile_field')
        || !function_exists('bp_get_member_type')
        || !function_exists('cison_get_required_fees')
        || !function_exists('cison_get_paid_fees')
        || !function_exists('cison_get_unpaid_fees')
    ) {
        return '';
    }

    $user_id = bp_get_userid_by_profile_field(894, $member_id);
    if (empty($user_id)) {
        return '';
    }

    $member_type_bp = bp_get_member_type($user_id);
    $is_student = $member_type_bp === 'student-member';
    $is_corporate = $member_type_bp === 'corporate-member';

    $reg_year = (int) substr($member_id, 0, 4);
    if ($reg_year < 1900) {
        return '';
    }

    $required = cison_get_required_fees(true, $reg_year, $is_student, $is_corporate);
    $paid = cison_get_paid_fees($user_id);
    $unpaid = cison_get_unpaid_fees($required, $paid);

    if (!empty($unpaid)) {
        return 'You have outstanding fees. Please clear all outstanding dues before applying as a transiting (NSA) fellow.';
    }

    return '';
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
    // NSA fellows: sponsors waived → application is complete as-is.
    if (($application['sponsor_1_status'] ?? '') === 'waived') {
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

/**
 * Derive an applicant-only edit key from the sponsor token. The key is not
 * stored anywhere; it is recomputed when an edit link is opened, so the link
 * is self-contained and unguessable.
 */
function cison_fellowship_edit_key($token)
{
    return wp_hash($token . '|edit');
}

function cison_fellowship_edit_link($row)
{
    return add_query_arg(
        array(
            'edit' => '1',
            'ref'  => $row['reference_number'] ?? '',
            'key'  => cison_fellowship_edit_key($row['sponsor_token'] ?? ''),
        ),
        CISON_FELLOWSHIP_FORM_URL
    );
}

function cison_fellowship_get_application_by_reference($reference_number)
{
    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE reference_number = %s LIMIT 1", $reference_number),
        ARRAY_A
    );
}

/**
 * Map a saved application row back to the values shape the application form
 * expects, so the applicant can review and correct their details.
 */
function cison_fellowship_map_db_to_form($row)
{
    $qualifications = array();
    $quals_string = trim((string) ($row['academic_qualifications'] ?? ''));
    if ($quals_string !== '') {
        foreach (explode("\n", $quals_string) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $qualifications[] = array(
                'institution' => $parts[0] ?? '',
                'degree'      => $parts[1] ?? '',
                'year'        => $parts[2] ?? '',
            );
        }
    }
    if (empty($qualifications)) {
        $qualifications = array(array('institution' => '', 'degree' => '', 'year' => ''));
    }

    return array_merge(cison_fellowship_get_form_defaults(), array(
        'title'                  => $row['title'] ?? '',
        'first_name'             => $row['first_name'] ?? '',
        'middle_name'            => $row['middle_name'] ?? '',
        'last_name'              => $row['last_name'] ?? '',
        'email'                  => $row['email'] ?? '',
        'phone'                  => $row['phone'] ?? '',
        'gender'                 => $row['gender'] ?? '',
        'date_of_birth'          => $row['date_of_birth'] ?? '',
        'nationality'            => $row['nationality'] ?? '',
        'occupation'             => $row['occupation'] ?? '',
        'designation'            => $row['designation'] ?? '',
        'employer'               => $row['employer'] ?? '',
        'years_of_practice'      => $row['years_of_practice'] ?? '',
        'area_of_practice'       => $row['area_of_practice'] ?? '',
        'street'                 => $row['street'] ?? '',
        'city'                   => $row['city'] ?? '',
        'state'                  => $row['state'] ?? '',
        'country'                => $row['country'] ?? 'NG',
        'membership_status'      => $row['is_member'] ?? '',
        'membership_category'    => $row['membership_category'] ?? '',
        'membership_number'      => $row['membership_number'] ?? '',
        'nsa_fellow'             => $row['is_nsa_fellow'] ?? '',
        'nsa_fellow_id'          => $row['nsa_fellow_id'] ?? '',
        'professional_experience' => $row['professional_experience'] ?? '',
        'publications'           => $row['publications'] ?? '',
        'academic_qualifications' => $qualifications,
        'cv'                     => $row['cv'] ?? '',
        'signature'              => $row['signature'] ?? '',
        'certificates'           => $row['certificates'] ?? '',
    ));
}

/**
 * Fee-bearing fields the applicant cannot change after submission. Also
 * carries over stored file values so they are preserved when no new file is
 * uploaded during an edit.
 */
function cison_fellowship_locked_edit_values($row)
{
    return array(
        'membership_status'   => $row['is_member'] ?? '',
        'membership_category' => $row['membership_category'] ?? '',
        'membership_number'   => $row['membership_number'] ?? '',
        'nsa_fellow'          => $row['is_nsa_fellow'] ?? '',
        'nsa_fellow_id'       => $row['nsa_fellow_id'] ?? '',
        'cv'                  => $row['cv'] ?? '',
        'signature'           => $row['signature'] ?? '',
    );
}

function cison_fellowship_get_full_name($row)
{
    return implode(' ', array_filter(array(
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? '',
    )));
}

function cison_fellowship_build_submission_summary($row)
{
    $s1_data = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
    $s2_data = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();

    // NSA fellows do not require sponsorship.
    $sponsors_waived = (($row['sponsor_1_status'] ?? '') === 'waived');

    $rows = array(
        __('Applicant Details', 'cison') => array(
            'Reference Number' => $row['reference_number'] ?? 'N/A',
            'Full Name' => cison_fellowship_get_full_name($row),
            'Email' => $row['email'] ?? 'N/A',
            'Phone' => $row['phone'] ?? 'N/A',
            'Membership Status' => $row['is_member'] ?? 'N/A',
            'Membership Number' => $row['membership_number'] ?? 'N/A',
            'NSA Fellow' => strtolower($row['is_nsa_fellow'] ?? '') === 'yes' ? 'Yes' : 'No',
            'NSA Fellow ID' => $row['nsa_fellow_id'] ?? '',
            'Occupation' => $row['occupation'] ?? 'N/A',
            'Designation' => $row['designation'] ?? 'N/A',
            'Employer' => $row['employer'] ?? 'N/A',
            'Years of Practice' => $row['years_of_practice'] ?? 'N/A',
            'Area of Statistics' => $row['area_of_practice'] ?? 'N/A',
            'Signature' => $row['signature'] ?? '',
        ),
    );

    if ($sponsors_waived) {
        $rows[__('Sponsorship', 'cison')] = array(
            'Sponsors Required' => __('No — waived for NSA Fellow.', 'cison'),
        );
    } else {
        $rows[__('Sponsor 1', 'cison') . ' (' . ($row['sponsor_1_status'] ?? 'pending') . ')'] = array(
            'Name' => $s1_data['name'] ?? 'N/A',
            'Membership ID' => $s1_data['membership_id'] ?? 'N/A',
            'Membership Status' => $s1_data['membership_status'] ?? 'N/A',
            'Rank' => $s1_data['rank'] ?? 'N/A',
            'Date' => $s1_data['date'] ?? 'N/A',
            'Signature' => $s1_data['signature'] ?? '',
        );
        $rows[__('Sponsor 2', 'cison') . ' (' . ($row['sponsor_2_status'] ?? 'pending') . ')'] = array(
            'Name' => $s2_data['name'] ?? 'N/A',
            'Membership ID' => $s2_data['membership_id'] ?? 'N/A',
            'Membership Status' => $s2_data['membership_status'] ?? 'N/A',
            'Rank' => $s2_data['rank'] ?? 'N/A',
            'Date' => $s2_data['date'] ?? 'N/A',
            'Signature' => $s2_data['signature'] ?? '',
        );
    }

    $html = '';
    $html .= '<h2 style="margin:0 0 18px;color:#0f172a;font-family:Arial,sans-serif;">Fellowship Submission</h2>';
    $html .= '<p style="margin:0 0 18px;color:#475569;font-family:Arial,sans-serif;font-size:14px;">Below are the details of the CISON Fellowship submission.</p>';

    foreach ($rows as $title => $fields) {
        $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html($title) . '</h3>';
        $html .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;">';
        foreach ($fields as $label => $value) {
            $html .= '<tr>';
            $html .= '<td style="width:35%;padding:8px 10px;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-weight:600;vertical-align:top;">' . esc_html($label) . '</td>';
            if ($label === 'Signature' && !empty($value)) {
                $html .= '<td style="padding:8px 10px;border:1px solid #e2e8f0;color:#0f172a;vertical-align:top;">';
                $html .= '<a href="' . esc_url($value) . '" style="color:#0f766e;font-weight:600;text-decoration:none;">View Signature</a>';
                $html .= '<br><img src="' . esc_url($value) . '" alt="Signature" style="max-width:180px;height:auto;border:1px solid #e2e8f0;border-radius:6px;margin-top:6px;">';
                $html .= '</td>';
            } else {
                $html .= '<td style="padding:8px 10px;border:1px solid #e2e8f0;color:#0f172a;vertical-align:top;">' . esc_html($value ?: 'N/A') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
    }

    $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html__('Academic Qualifications', 'cison') . '</h3>';
    $html .= cison_fellowship_email_block($row['academic_qualifications'] ?: 'N/A');

    $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html__('Professional Experience', 'cison') . '</h3>';
    $html .= cison_fellowship_email_block($row['professional_experience'] ?: 'N/A');

    $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html__('Publications / Contribution', 'cison') . '</h3>';
    $html .= cison_fellowship_email_block($row['publications'] ?: 'N/A');

    $certificates = !empty($row['certificates']) ? json_decode($row['certificates'], true) : array();
    if (is_array($certificates) && !empty($certificates)) {
        $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html__('Certificates', 'cison') . '</h3>';
        $html .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;">';
        foreach ($certificates as $cert_url) {
            $html .= '<tr>';
            $html .= '<td style="padding:8px 10px;border:1px solid #e2e8f0;color:#0f172a;vertical-align:top;">';
            $html .= '<a href="' . esc_url($cert_url) . '" style="color:#0f766e;font-weight:600;text-decoration:none;">' . esc_html(basename(parse_url($cert_url, PHP_URL_PATH))) . '</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
    }

    if (!empty($row['cv'])) {
        $html .= '<h3 style="margin:22px 0 10px;color:#0f766e;font-family:Arial,sans-serif;font-size:14px;text-transform:uppercase;letter-spacing:0.04em;">' . esc_html__('Curriculum Vitae', 'cison') . '</h3>';
        $html .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;">';
        $html .= '<tr>';
        $html .= '<td style="padding:8px 10px;border:1px solid #e2e8f0;color:#0f172a;vertical-align:top;">';
        $html .= '<a href="' . esc_url($row['cv']) . '" style="color:#0f766e;font-weight:600;text-decoration:none;">' . esc_html(basename(parse_url($row['cv'], PHP_URL_PATH))) . '</a>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
    }

    return $html;
}

function cison_fellowship_email_document($body_html)
{
    return '<!DOCTYPE html>' .
        '<html lang="' . esc_attr(get_locale()) . '">' .
        '<body style="margin:0;padding:0;background-color:#f1f5f9;">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f1f5f9;">' .
        '<tr><td align="center" style="padding:24px 12px;">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="max-width:640px;width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">' .
        '<tr><td style="padding:28px 32px;font-family:Arial,sans-serif;color:#0f172a;font-size:14px;">' .
        $body_html .
        '</td></tr>' .
        '<tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;font-family:Arial,sans-serif;font-size:12px;color:#94a3b8;">' .
        esc_html__('This email was sent from the CISON website.', 'cison') .
        '</td></tr>' .
        '</table>' .
        '</td></tr>' .
        '</table>' .
        '</body>' .
        '</html>';
}

function cison_fellowship_email_block($content)
{
    $paragraphs = preg_split("/\r\n|\r|\n/", (string) $content);
    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

    $html = '<div style="padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;font-family:Arial,sans-serif;font-size:13px;color:#334155;line-height:1.6;">';
    if (empty($paragraphs)) {
        $html .= esc_html('N/A');
    } else {
        foreach ($paragraphs as $i => $para) {
            if ($i > 0) {
                $html .= '<br>';
            }
            $html .= esc_html($para);
        }
    }
    $html .= '</div>';

    return $html;
}

function cison_fellowship_url_to_path($url)
{
    $upload = wp_upload_dir();

    if (!empty($url) && strpos($url, $upload['baseurl']) === 0) {
        return $upload['basedir'] . substr($url, strlen($upload['baseurl']));
    }

    return null;
}

function cison_fellowship_get_signature_attachments($row)
{
    $attachments = array();

    $applicant_sig = $row['signature'] ?? '';
    $applicant_path = cison_fellowship_url_to_path($applicant_sig);
    if ($applicant_path && is_file($applicant_path)) {
        $attachments[] = $applicant_path;
    }

    foreach (array('sponsor_1_data', 'sponsor_2_data') as $key) {
        $data = !empty($row[$key]) ? json_decode($row[$key], true) : array();
        $url = $data['signature'] ?? '';
        $path = cison_fellowship_url_to_path($url);

        if ($path && is_file($path)) {
            $attachments[] = $path;
        }
    }

    return $attachments;
}

function cison_fellowship_get_certificate_attachments($row)
{
    $attachments = array();

    $certificates = !empty($row['certificates']) ? json_decode($row['certificates'], true) : array();
    if (!is_array($certificates)) {
        return $attachments;
    }

    foreach ($certificates as $url) {
        $path = cison_fellowship_url_to_path($url);

        if ($path && is_file($path)) {
            $attachments[] = $path;
        }
    }

    return $attachments;
}

function cison_fellowship_get_cv_attachment($row)
{
    $url = $row['cv'] ?? '';
    $path = cison_fellowship_url_to_path($url);

    if ($path && is_file($path)) {
        return $path;
    }

    return null;
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

    $signature_url = cison_fellowship_handle_applicant_signature_upload();
    if ($signature_url) {
        $data['signature'] = $signature_url;
    }

    $certificates = cison_fellowship_handle_certificates_upload();
    if (!empty($certificates)) {
        $data['certificates'] = array_map('esc_url_raw', $certificates);
    }

    $cv_url = cison_fellowship_handle_cv_upload();
    if ($cv_url) {
        $data['cv'] = $cv_url;
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
// APPLICANT SELF-SERVICE EDIT
// ============================================================

/**
 * Applicants use a signed link (?edit=1&ref=...&key=...) to update their own
 * submission in place. No new order is created: fee-bearing fields and any
 * sponsor endorsements are left untouched.
 */
function cison_fellowship_handle_applicant_edit()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cison_fellowship_edit_submit'])) {
        return;
    }

    if (!isset($_POST['cison_fellowship_edit_nonce']) || !wp_verify_nonce($_POST['cison_fellowship_edit_nonce'], 'cison_fellowship_edit_action')) {
        return;
    }

    $reference_number = sanitize_text_field(wp_unslash($_POST['ref'] ?? ''));
    $provided_key     = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
    if (empty($reference_number) || empty($provided_key)) {
        return;
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE reference_number = %s LIMIT 1", $reference_number),
        ARRAY_A
    );
    if (!$row || empty($row['sponsor_token'])) {
        return;
    }

    $expected_key = cison_fellowship_edit_key($row['sponsor_token']);
    if (!hash_equals($expected_key, $provided_key)) {
        return;
    }

    $data = cison_fellowship_sanitize($_POST);
    $data = array_merge($data, cison_fellowship_locked_edit_values($row));

    $errors = cison_fellowship_validate($data, (int) $row['id']);
    if (!empty($errors)) {
        // Return silently: the application shortcode re-renders the edit form
        // with the same POST data and shows the validation errors.
        return;
    }

    $data['certificates'] = array();
    if (!empty($row['certificates'])) {
        $existing = json_decode($row['certificates'], true);
        if (is_array($existing)) {
            $data['certificates'] = $existing;
        }
    }
    $new_certificates = cison_fellowship_handle_certificates_upload();
    if (!empty($new_certificates)) {
        $data['certificates'] = array_values(array_merge($data['certificates'], array_map('esc_url_raw', $new_certificates)));
    }

    $signature_url = cison_fellowship_handle_applicant_signature_upload();
    if ($signature_url) {
        $data['signature'] = $signature_url;
    }

    $cv_url = cison_fellowship_handle_cv_upload();
    if ($cv_url) {
        $data['cv'] = $cv_url;
    }

    $update_data = array(
        'is_member' => $data['membership_status'],
        'is_nsa_fellow' => $data['nsa_fellow'],
        'nsa_fellow_id' => $data['nsa_fellow_id'],
        'membership_category' => $data['membership_category'],
        'membership_number' => $data['membership_number'],
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
        'certificates' => !empty($data['certificates']) ? wp_json_encode(array_values($data['certificates'])) : '',
        'signature' => $data['signature'] ?? '',
        'cv' => $data['cv'] ?? '',
        'updated_at' => current_time('mysql'),
    );

    $wpdb->update($table_name, $update_data, array('id' => (int) $row['id']));

    wp_safe_redirect(add_query_arg('edited', '1', cison_fellowship_edit_link($row)));
    exit;
}
add_action('template_redirect', 'cison_fellowship_handle_applicant_edit');

// ============================================================
// WOOCOMMERCE: PERSIST APPLICATION DATA ON ORDER
// ============================================================

function cison_fellowship_persist_entry_on_order($order, $data)
{
    if (!WC()->session) {
        return;
    }

    $entry = WC()->session->get('cison_fellowship_entry');
    if (!empty($entry) && is_array($entry)) {
        $order->update_meta_data('_cison_fellowship_entry', $entry);
    }
}
add_action('woocommerce_checkout_create_order', 'cison_fellowship_persist_entry_on_order', 10, 2);

function cison_fellowship_clear_session_entry()
{
    if (WC()->session) {
        WC()->session->__unset('cison_fellowship_entry');
    }
}

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
// ADMIN: EMAIL SINGLE SUBMISSION
// ============================================================

function cison_fellowship_handle_send_submission_email()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['cison_fellowship_email_submit'])) {
        return;
    }

    if (
        !isset($_POST['cison_fellowship_email_nonce'])
        || !wp_verify_nonce($_POST['cison_fellowship_email_nonce'], 'cison_fellowship_email_action')
    ) {
        return;
    }

    $ref = isset($_POST['fs_ref']) ? sanitize_text_field(wp_unslash($_POST['fs_ref'])) : '';
    if (empty($ref)) {
        return;
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE reference_number = %s LIMIT 1", $ref),
        ARRAY_A
    );

    if (!$row) {
        wp_safe_redirect(add_query_arg(array('fs_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    $to_raw = isset($_POST['cison_fellowship_email_to']) ? trim(wp_unslash($_POST['cison_fellowship_email_to'])) : '';
    $subject = isset($_POST['cison_fellowship_email_subject']) ? sanitize_text_field(wp_unslash($_POST['cison_fellowship_email_subject'])) : '';
    $message = isset($_POST['cison_fellowship_email_message']) ? wp_kses_post(wp_unslash($_POST['cison_fellowship_email_message'])) : '';

    if (empty($to_raw) || empty($subject) || empty($message)) {
        wp_safe_redirect(add_query_arg(array('fs_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    // If the message is plain text (no HTML tags), escape it and preserve line breaks.
    if (strip_tags($message) === $message) {
        $message = nl2br(esc_html($message));
    }

    $message = cison_fellowship_email_document($message);

    $emails = array_map('trim', explode(',', $to_raw));
    $emails = array_values(array_filter($emails));

    if (empty($emails)) {
        wp_safe_redirect(add_query_arg(array('fs_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    $sent = false;
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );
    $attachments = array_merge(
        cison_fellowship_get_signature_attachments($row),
        cison_fellowship_get_certificate_attachments($row),
        array_filter(array(cison_fellowship_get_cv_attachment($row)))
    );

    $mail_error = '';
    add_action('wp_mail_failed', function ($wp_error) use (&$mail_error) {
        if (is_wp_error($wp_error)) {
            $mail_error = $wp_error->get_error_message();
            $data = $wp_error->get_error_data();
            if (is_array($data) && !empty($data['phpmailer_exception']) && method_exists($data['phpmailer_exception'], 'getMessage')) {
                $mail_error = $data['phpmailer_exception']->getMessage();
            }
        }
    });

    foreach ($emails as $email) {
        if (is_email($email)) {
            $sent = wp_mail($email, $subject, $message, $headers, $attachments) || $sent;
        }
    }

    $redirect_args = array('fs_ref' => rawurlencode($ref));
    if ($sent) {
        $redirect_args['fs_email_sent'] = '1';
    } else {
        $redirect_args['fs_email_error'] = '1';
        if ($mail_error !== '') {
            $redirect_args['fs_email_msg'] = $mail_error;
        }
    }

    $redirect = add_query_arg($redirect_args, CISON_FELLOWSHIP_DETAIL_URL);
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_cison_fellowship_email_submission', 'cison_fellowship_handle_send_submission_email');

function cison_fellowship_get_field_request_map()
{
    return array(
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'last_name' => 'Last Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'title' => 'Title',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
        'nationality' => 'Nationality',
        'membership_number' => 'CISON Member Number',
        'membership_category' => 'Membership Category',
        'street' => 'Street Address',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'occupation' => 'Current Occupation',
        'designation' => 'Designation',
        'employer' => 'Employer / Institution',
        'years_of_practice' => 'Years of Practice',
        'area_of_practice' => 'Area of Statistics',
        'academic_qualifications' => 'Academic Qualifications',
        'professional_experience' => 'Professional Experience',
        'publications' => 'Publications, Research & Contribution',
        'certificates' => 'Certificates',
        'cv' => 'Curriculum Vitae (CV)',
        'signature' => 'Signature',
        'nsa_fellow_id' => 'NSA Fellow ID',
    );
}

function cison_fellowship_handle_request_fields_email()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['cison_fellowship_fields_submit'])) {
        return;
    }

    if (
        !isset($_POST['cison_fellowship_fields_nonce'])
        || !wp_verify_nonce($_POST['cison_fellowship_fields_nonce'], 'cison_fellowship_fields_action')
    ) {
        return;
    }

    $ref = isset($_POST['fs_ref']) ? sanitize_text_field(wp_unslash($_POST['fs_ref'])) : '';
    if (empty($ref)) {
        return;
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE reference_number = %s LIMIT 1", $ref),
        ARRAY_A
    );

    if (!$row) {
        wp_safe_redirect(add_query_arg(array('fs_field_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    $applicant_email = $row['email'] ?? '';
    if (empty($applicant_email) || !is_email($applicant_email)) {
        wp_safe_redirect(add_query_arg(array('fs_field_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    $map = cison_fellowship_get_field_request_map();

    // Only accept keys that exist in the map, so the email can never list
    // arbitrary attacker-controlled field names.
    $selected = array();
    if (!empty($_POST['cison_fellowship_fields']) && is_array($_POST['cison_fellowship_fields'])) {
        foreach ((array) $_POST['cison_fellowship_fields'] as $key) {
            $clean_key = sanitize_key($key);
            if (isset($map[$clean_key])) {
                $selected[$clean_key] = $map[$clean_key];
            }
        }
    }

    if (empty($selected)) {
        wp_safe_redirect(add_query_arg(array('fs_field_email_error' => '1', 'fs_ref' => rawurlencode($ref)), CISON_FELLOWSHIP_DETAIL_URL));
        exit;
    }

    $note = isset($_POST['cison_fellowship_fields_note']) ? sanitize_textarea_field(wp_unslash($_POST['cison_fellowship_fields_note'])) : '';

    $items_html = '';
    foreach ($selected as $label) {
        $items_html .= '<li>' . esc_html($label) . '</li>';
    }

    $subject = sprintf('Action Required: Complete Your CISON Fellowship Application (%s)', $row['reference_number']);

    $edit_link = cison_fellowship_edit_link($row);

    $message_html = sprintf(
        '<p>Dear %s,</p>' .
        '<p>Thank you for applying to become a <strong>CISON Fellow</strong>. While reviewing your application (reference <strong>%s</strong>), we noticed that the following field(s) need your attention:</p>' .
        '<ul>%s</ul>' .
        '<p>Please complete or correct the field(s) listed above so we can continue processing your application.</p>' .
        '%s' .
        '<p>You can update these details yourself using the secure edit link below:</p>' .
        '<p style="margin:20px 0;"><a href="%s" style="display:inline-block;padding:12px 24px;color:#ffffff;background-color:#0f766e;border-radius:6px;text-decoration:none;">Edit My Application</a></p>' .
        '<p style="font-size:13px;color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br>%s</p>' .
        '<p>If you have any questions, please contact us for assistance.</p>' .
        '<p>Best regards,<br>CISON Fellowship Committee</p>',
        esc_html(cison_fellowship_get_full_name($row) ?: 'Applicant'),
        esc_html($row['reference_number']),
        $items_html,
        $note !== '' ? '<p><strong>Additional note from the committee:</strong><br>' . nl2br(esc_html($note)) . '</p>' : '',
        esc_url($edit_link),
        esc_html($edit_link)
    );

    $message = cison_fellowship_email_document($message_html);
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $mail_error = '';
    add_action('wp_mail_failed', function ($wp_error) use (&$mail_error) {
        if (is_wp_error($wp_error)) {
            $mail_error = $wp_error->get_error_message();
            $data = $wp_error->get_error_data();
            if (is_array($data) && !empty($data['phpmailer_exception']) && method_exists($data['phpmailer_exception'], 'getMessage')) {
                $mail_error = $data['phpmailer_exception']->getMessage();
            }
        }
    });

    $sent = wp_mail($applicant_email, $subject, $message, $headers);

    $redirect_args = array('fs_ref' => rawurlencode($ref));
    if ($sent) {
        $redirect_args['fs_field_email_sent'] = '1';
    } else {
        $redirect_args['fs_field_email_error'] = '1';
        if ($mail_error !== '') {
            $redirect_args['fs_email_msg'] = $mail_error;
        }
    }

    wp_safe_redirect(add_query_arg($redirect_args, CISON_FELLOWSHIP_DETAIL_URL));
    exit;
}
add_action('admin_post_cison_fellowship_request_fields', 'cison_fellowship_handle_request_fields_email');

// ============================================================
// WOOCOMMERCE: SAVE ON PAYMENT COMPLETE
// ============================================================

function cison_fellowship_save_on_payment_complete($order_id)
{
    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    // Guard against duplicate registrations for the same order: both
    // woocommerce_payment_complete and woocommerce_order_status_completed
    // can fire for a single completion.
    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE order_id = %d LIMIT 1", (int) $order_id))) {
        return;
    }

    $data = array();

    // Prefer the application entry persisted on the order itself so saving
    // no longer depends on the applicant's browser session (works for late
    // Paystack webhooks and manual admin order updates too).
    $order = wc_get_order($order_id);
    if ($order) {
        $order_entry = $order->get_meta('_cison_fellowship_entry', true);
        if (!empty($order_entry) && is_array($order_entry)) {
            $data = $order_entry;
        }
    }

    // Fall back to the session entry for carts created before this fix.
    if (empty($data) && WC()->session) {
        $data = WC()->session->get('cison_fellowship_entry');
    }

    if (!$data || !is_array($data)) {
        return;
    }

    $products = cison_fellowship_resolve_products($data);
    $product_ids = implode(',', $products);
    $token = cison_fellowship_generate_token();

    $membership_number = trim($data['membership_number'] ?? '');
    $nsa_fellow_id = strtoupper(trim($data['nsa_fellow_id'] ?? ''));
    $is_nsa_fellow = in_array(strtolower($data['nsa_fellow'] ?? ''), array('yes', 'true', '1'), true);

    // Ensure unique membership number / NSA fellow ID before storing.
    if (
        !empty($membership_number) && $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE membership_number = %s AND membership_number != '' LIMIT 1",
            $membership_number
        ))
    ) {
        error_log('CISON Fellowship: duplicate membership number blocked on save: ' . $membership_number . ' (order ' . $order_id . ')');
        cison_fellowship_clear_session_entry();
        return;
    }

    if (
        $is_nsa_fellow && !empty($nsa_fellow_id) && $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE is_nsa_fellow = %s AND nsa_fellow_id = %s AND nsa_fellow_id != '' LIMIT 1",
            'yes',
            $nsa_fellow_id
        ))
    ) {
        error_log('CISON Fellowship: duplicate NSA fellow ID blocked on save: ' . $nsa_fellow_id . ' (order ' . $order_id . ')');
        cison_fellowship_clear_session_entry();
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
'publications'          => $data['publications'],
        'certificates'          => !empty($data['certificates']) ? wp_json_encode(array_values($data['certificates'])) : '',
        'cv'                    => $data['cv'] ?? '',
        'signature'             => $data['signature'] ?? '',
        'num_sponsors' => 2,
        'product_ids' => $product_ids,
        'payment_status' => 'paid',
        'application_status' => 'submitted',
        'registration_date' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'sponsor_token' => $token,
        'sponsor_1_status' => $is_nsa_fellow ? 'waived' : 'pending',
        'sponsor_2_status' => $is_nsa_fellow ? 'waived' : 'pending',
    );

    $inserted = $wpdb->insert($table_name, $insert_data);

    if ($inserted) {
        cison_fellowship_send_applicant_email($insert_data, $token);
    }

    cison_fellowship_clear_session_entry();
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
    $is_nsa_fellow = strtolower($data['is_nsa_fellow'] ?? '') === 'yes';

    $edit_link = cison_fellowship_edit_link($data);

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // NSA fellows do not require sponsorship; send a simple confirmation.
    if ($is_nsa_fellow) {
        $subject = apply_filters(
            'cison_fellowship_email_subject',
            'Your CISON Fellowship Application: Received'
        );

        $message_html = sprintf(
            '<p>Dear %s,</p>' .
            '<p>Thank you for applying to become a <strong>CISON Fellow</strong>! ' .
            'Your payment has been received successfully.</p>' .
            '<p>As a current <strong>NSA Fellow</strong>, sponsorship endorsement is not required for your application.</p>' .
            '<p>Your application has been submitted and will be reviewed by the fellowship committee. ' .
            'We will contact you once a decision has been made.</p>' .
            '<p>If you need to correct or update any of your details, you can edit your application at any time:</p>' .
            '<p style="margin:20px 0;"><a href="%s" style="display:inline-block;padding:12px 24px;color:#ffffff;background-color:#0f766e;border-radius:6px;text-decoration:none;">Edit My Application</a></p>' .
            '<p style="font-size:13px;color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br>%s</p>' .
            '<p>If you have any questions, please contact us for assistance.</p>' .
            '<p>Best regards,<br>CISON Fellowship Committee</p>',
            esc_html($first_name),
            esc_url($edit_link),
            esc_html($edit_link)
        );

        return wp_mail($email, $subject, $message_html, $headers);
    }

    // Standard applicants need two sponsors.
    $sponsor_link = cison_fellowship_sponsor_link($token);

    $subject = apply_filters(
        'cison_fellowship_email_subject',
        'Your CISON Fellowship Application: Next Steps'
    );

    $message_html = sprintf(
        '<p>Dear %s,</p>' .
        '<p>Thank you for applying to become a <strong>CISON Fellow</strong>! ' .
        'Your payment has been received successfully.</p>' .
        '<p>To complete your application, you need <strong>two sponsors</strong> (current CISON members) ' .
        'to endorse your application. Please follow the steps below:</p>' .
        '<ol style="margin:16px 0;padding-left:22px;line-height:1.7;">' .
        '<li><strong>Choose two sponsors</strong> &mdash; Ask two current CISON members who can vouch for your qualifications and character to sponsor you.</li>' .
        '<li><strong>Forward this email or send them the link below</strong> &mdash; Each sponsor opens the link and fills in their own details and endorsement directly on the page.</li>' .
        '<li><strong>Sponsor 1 submits first, then Sponsor 2</strong> &mdash; Your sponsors can use the <em>same link</em>. Once Sponsor 1 has submitted, the form will then allow Sponsor 2 to complete their endorsement.</li>' .
        '<li><strong>You&rsquo;re done</strong> &mdash; Once both sponsors have submitted, your application will be reviewed by the fellowship committee.</li>' .
        '</ol>' .
        '<p>Here is the sponsor link to share:</p>' .
        '<p style="margin:20px 0;"><a href="%s" style="display:inline-block;padding:12px 24px;color:#ffffff;background-color:#0f766e;border-radius:6px;text-decoration:none;">Open Sponsor Endorsement Form</a></p>' .
        '<p style="font-size:13px;color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br>%s</p>' .
        '<p style="font-size:13px;color:#6b7280;">Please share this link only with your two chosen sponsors.</p>' .
        '<p style="font-size:13px;color:#6b7280;">If you need to correct or update any of your application details, you can <a href="%s" style="color:#0f766e;font-weight:600;text-decoration:none;">edit your application here</a>.</p>' .
        '<p>If you have any questions, please contact us for assistance.</p>' .
        '<p>Best regards,<br>CISON Fellowship Committee</p>',
        esc_html($first_name),
        esc_url($sponsor_link),
        esc_html($sponsor_link),
        esc_url($edit_link)
    );

    $headers = array('Content-Type: text/html; charset=UTF-8');

    return wp_mail($email, $subject, $message_html, $headers);
}

// ============================================================
// MAIL FAILURE LOGGING
// ============================================================

/**
 * Extract a human-readable failure reason from the WP_Error fired by wp_mail().
 */
function cison_fellowship_mail_error_reason($wp_error)
{
    $message = $wp_error->get_error_message();
    $data    = is_wp_error($wp_error) ? $wp_error->get_error_data() : null;

    if (is_array($data) && !empty($data['phpmailer_exception']) && method_exists($data['phpmailer_exception'], 'getMessage')) {
        $ex_message = $data['phpmailer_exception']->getMessage();
        if ($ex_message !== '') {
            $message = $ex_message;
        }
    }

    return $message !== '' ? $message : 'wp_mail() failed without a detailed error message.';
}

/**
 * Record every failed wp_mail() call so admins can see why emails are not
 * being delivered, even for hooks fired outside the admin area.
 */
function cison_fellowship_log_mail_failures($wp_error)
{
    if (!is_wp_error($wp_error)) {
        return;
    }

    $log  = get_option('cison_mail_failure_log', array());
    if (!is_array($log)) {
        $log = array();
    }

    $error_data = $wp_error->get_error_data();

    $log[] = array(
        'time'    => current_time('mysql'),
        'to'      => isset($error_data['to']) ? sanitize_text_field($error_data['to']) : '',
        'subject' => isset($error_data['subject']) ? sanitize_text_field($error_data['subject']) : '',
        'error'   => cison_fellowship_mail_error_reason($wp_error),
    );

    $log = array_slice($log, -20);
    update_option('cison_mail_failure_log', $log);
}
add_action('wp_mail_failed', 'cison_fellowship_log_mail_failures');

// ============================================================
// SHORTCODE: MAIN FORM
// ============================================================

function cison_fellowship_form_shortcode()
{
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
    $application = null;
    $view_status = 'new';

    $is_edit = isset($_GET['edit']) && sanitize_text_field(wp_unslash($_GET['edit'])) === '1';
    $edit_application = null;
    $edit_verified = false;
    $edit_ref = '';
    $edit_key = '';

    if ($is_edit) {
        // Applicant self-service edit via the signed link.
        $edit_ref = sanitize_text_field(wp_unslash($_GET['ref'] ?? ''));
        $edit_key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
        if (!empty($edit_ref) && !empty($edit_key)) {
            $edit_application = cison_fellowship_get_application_by_reference($edit_ref);
            if ($edit_application && !empty($edit_application['sponsor_token'])) {
                $edit_verified = hash_equals(cison_fellowship_edit_key($edit_application['sponsor_token']), $edit_key);
            }
        }
        $view_status = $edit_verified ? 'edit' : 'invalid_edit';
    } elseif (!empty($token)) {
        $application = cison_fellowship_get_application_by_token($token);
        if ($application) {
            $view_status = cison_fellowship_get_token_status($application);
        }
    }

    $has_valid_token = ($view_status === 's1' || $view_status === 's2');

    $values = cison_fellowship_get_form_defaults();
    $feedback_message = '';
    $feedback_type = '';

    if ($view_status === 'edit') {
        $values = cison_fellowship_map_db_to_form($edit_application);

        if (isset($_GET['edited'])) {
            $feedback_message = 'Your application details have been updated successfully.';
            $feedback_type = 'success';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cison_fellowship_edit_submit'])) {
            if (!isset($_POST['cison_fellowship_edit_nonce']) || !wp_verify_nonce($_POST['cison_fellowship_edit_nonce'], 'cison_fellowship_edit_action')) {
                $feedback_message = 'Security check failed. Please try again.';
                $feedback_type = 'error';
            } else {
                $values = array_merge($values, cison_fellowship_sanitize($_POST));
                $values = array_merge($values, cison_fellowship_locked_edit_values($edit_application));
                $errors = cison_fellowship_validate($values, (int) $edit_application['id']);
                if (!empty($errors)) {
                    $feedback_message = implode(' ', $errors);
                    $feedback_type = 'error';
                }
            }
        }
    } elseif ($view_status === 'new') {
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
    <div class="cison-fs" data-has-token="<?php echo $has_valid_token ? '1' : '0'; ?>"<?php echo $view_status === 'edit' ? ' data-edit="1"' : ''; ?>>
        <?php if ($view_status === 'invalid_edit'): ?>
            <div class="cison-fs__header">
                <h3>Invalid or Expired Edit Link</h3>
                <p>The edit link you used is invalid or has expired. If you believe this is a mistake, please contact
                    the CISON Fellowship Committee for assistance.</p>
            </div>
        <?php elseif ($view_status === 'complete'): ?>
            <div class="cison-fs__header">
                <h3>Fellowship Application Complete</h3>
                <p>Both sponsors have submitted their endorsements. Your application will be reviewed by the fellowship
                    committee.</p>
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
                            <div><strong>Membership ID:</strong> <?php echo esc_html($sponsor_1_data['membership_id'] ?? ''); ?>
                            </div>
                            <div><strong>Membership Status:</strong>
                                <?php echo esc_html($sponsor_1_data['membership_status'] ?? ''); ?></div>
                            <div><strong>Rank:</strong> <?php echo esc_html($sponsor_1_data['rank'] ?? ''); ?></div>
                            <div><strong>Date:</strong> <?php echo esc_html($sponsor_1_data['date'] ?? ''); ?></div>
                            <?php if (!empty($sponsor_1_data['signature'])): ?>
                                <div><strong>Signature:</strong> <a href="<?php echo esc_url($sponsor_1_data['signature']); ?>"
                                        target="_blank">View Signature</a></div>
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
                <?php if ($view_status === 'edit'): ?>
                    <p>Review your application details below. You can update the information shown and save your
                        changes.</p>
                    <p class="cison-fs__help">Fee-related settings (membership status, membership category and NSA
                        fellow status) were locked at submission and cannot be changed here.</p>
                <?php else: ?>
                    <p>Complete the form below to apply for CISON Fellowship.</p>
                <?php endif; ?>
            </div>

            <?php if ($feedback_message): ?>
                <div class="cison-fs__alert cison-fs__alert--<?php echo esc_attr($feedback_type); ?>">
                    <?php echo esc_html($feedback_message); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="cison-fs__form" enctype="multipart/form-data" novalidate>
                <?php if ($view_status === 'edit'): ?>
                    <?php wp_nonce_field('cison_fellowship_edit_action', 'cison_fellowship_edit_nonce'); ?>
                    <input type="hidden" name="cison_fellowship_edit_submit" value="1">
                    <input type="hidden" name="ref" value="<?php echo esc_attr($edit_ref); ?>">
                    <input type="hidden" name="key" value="<?php echo esc_attr($edit_key); ?>">
                <?php else: ?>
                    <?php wp_nonce_field('cison_fellowship_action', 'cison_fellowship_nonce'); ?>
                    <input type="hidden" name="cison_fellowship_submit" value="1">
                <?php endif; ?>

                <div class="cison-fs__section cison-fs__section--membership">
                    <h4>Present Membership Status in CISON</h4>

                    <?php if ($view_status === 'edit'): ?>
                        <p class="cison-fs__help">These details are locked at submission. Contact us if they need to
                            change.</p>
                        <div class="cison-fs__grid cison-fs__grid--two">
                            <div>
                                <label for="cison_fs_member_status">Are you a CISON Member?</label>
                                <select id="cison_fs_member_status" name="membership_status" disabled>
                                    <option value="member" <?php selected($values['membership_status'], 'member'); ?>>
                                        Member</option>
                                    <option value="non-member" <?php selected($values['membership_status'], 'non-member'); ?>>
                                        Non-Member</option>
                                </select>
                            </div>
                        </div>

                        <div class="cison-fs__nsa-fellow-wrap js-nsa-fellow-wrap"
                            style="<?php echo $is_member ? '' : 'display:none;'; ?>">
                            <div class="cison-fs__grid cison-fs__grid--two">
                                <div>
                                    <label for="cison_fs_nsa_fellow">Are you an NSA Fellow?</label>
                                    <select id="cison_fs_nsa_fellow" name="nsa_fellow" disabled>
                                        <option value="yes" <?php selected($values['nsa_fellow'], 'yes'); ?>>Yes</option>
                                        <option value="no" <?php selected($values['nsa_fellow'], 'no'); ?>>No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="cison-fs__nsa-id-wrap js-nsa-id-wrap"
                                style="<?php echo $is_nsa_fellow ? '' : 'display:none;'; ?>">
                                <div class="cison-fs__grid cison-fs__grid--two">
                                    <div>
                                        <label for="cison_fs_nsa_fellow_id">NSA Fellow ID</label>
                                        <input id="cison_fs_nsa_fellow_id" type="text" name="nsa_fellow_id"
                                            value="<?php echo esc_attr($values['nsa_fellow_id']); ?>" disabled>
                                        <span class="cison-fs__help">Enter the NSA Fellow ID shown on your fellowship
                                            certificate.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="cison-fs__help">If you are a non-member, you may qualify for honorary fellowship.</p>
                        <div class="cison-fs__grid cison-fs__grid--two">
                            <div>
                                <label for="cison_fs_member_status">Are you a CISON Member? <span>*</span></label>
                                <select id="cison_fs_member_status" name="membership_status" required>
                                    <option value="">Select</option>
                                    <option value="member" <?php selected($values['membership_status'], 'member'); ?>>
                                        Member</option>
                                    <option value="non-member" <?php selected($values['membership_status'], 'non-member'); ?>>
                                        Non-Member</option>
                                </select>
                            </div>
                        </div>

                        <div class="cison-fs__nsa-fellow-wrap js-nsa-fellow-wrap"
                            style="<?php echo $is_member ? '' : 'display:none;'; ?>">
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

                            <div class="cison-fs__nsa-id-wrap js-nsa-id-wrap"
                                style="<?php echo $is_nsa_fellow ? '' : 'display:none;'; ?>">
                                <div class="cison-fs__grid cison-fs__grid--two">
                                    <div>
                                        <label for="cison_fs_nsa_fellow_id">NSA Fellow ID <span>*</span></label>
                                        <input id="cison_fs_nsa_fellow_id" type="text" name="nsa_fellow_id"
                                            value="<?php echo esc_attr($values['nsa_fellow_id']); ?>"
                                            placeholder="e.g. NSA/FNSA/2021001" <?php echo $is_nsa_fellow ? 'required' : ''; ?>>
                                        <span class="cison-fs__help">Enter the NSA Fellow ID shown on your fellowship
                                            certificate.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cison-fs__section">
                    <h4>Personal Information</h4>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_title">Title <span>*</span></label>
                            <select id="cison_fs_title" name="title" required>
                                <option value="">Select</option>
                                <?php foreach ($titles as $t): ?>
                                    <option value="<?php echo esc_attr($t); ?>" <?php selected($values['title'], $t); ?>>
                                        <?php echo esc_html($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--three">
                        <div>
                            <label for="cison_fs_first_name">First Name <span>*</span></label>
                            <input id="cison_fs_first_name" type="text" name="first_name"
                                value="<?php echo esc_attr($values['first_name']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_middle_name">Middle Name</label>
                            <input id="cison_fs_middle_name" type="text" name="middle_name"
                                value="<?php echo esc_attr($values['middle_name']); ?>">
                        </div>
                        <div>
                            <label for="cison_fs_last_name">Last Name <span>*</span></label>
                            <input id="cison_fs_last_name" type="text" name="last_name"
                                value="<?php echo esc_attr($values['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_email">Email Address <span>*</span></label>
                            <input id="cison_fs_email" type="email" name="email"
                                value="<?php echo esc_attr($values['email']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_phone">Phone Number <span>*</span></label>
                            <input id="cison_fs_phone" type="tel" name="phone" value="<?php echo esc_attr($values['phone']); ?>"
                                required>
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_gender">Gender</label>
                            <select id="cison_fs_gender" name="gender">
                                <option value="">Select</option>
                                <?php foreach ($genders as $g): ?>
                                    <option value="<?php echo esc_attr($g); ?>" <?php selected($values['gender'], $g); ?>>
                                        <?php echo esc_html($g); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="cison_fs_dob">Date of Birth</label>
                            <input id="cison_fs_dob" type="date" name="date_of_birth"
                                value="<?php echo esc_attr($values['date_of_birth']); ?>">
                        </div>
                    </div>

                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_nationality">Nationality</label>
                            <input id="cison_fs_nationality" type="text" name="nationality"
                                value="<?php echo esc_attr($values['nationality']); ?>">
                        </div>
                        <div>
                            <label for="cison_fs_member_number">CISON Member Number <span>*</span></label>
                            <input id="cison_fs_member_number" type="text" name="membership_number"
                                value="<?php echo esc_attr($values['membership_number']); ?>" required<?php echo $view_status === 'edit' ? ' disabled' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section">
                    <h4>Residential Address</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_street">Street Address</label>
                            <input id="cison_fs_street" type="text" name="street"
                                value="<?php echo esc_attr($values['street']); ?>" placeholder="House number and street name">
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
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($values['country'], $code); ?>>
                                        <?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="js-state-select-wrap" style="<?php echo $is_nigeria ? '' : 'display:none;'; ?>">
                            <label for="cison_fs_state">State</label>
                            <select id="cison_fs_state" name="state" <?php echo $is_nigeria ? '' : 'disabled'; ?>>
                                <option value="">Select</option>
                                <?php foreach ($nigerian_states as $state): ?>
                                    <option value="<?php echo esc_attr($state); ?>" <?php selected($values['state'], $state); ?>>
                                        <?php echo esc_html($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="js-state-manual-wrap" style="<?php echo $is_nigeria ? 'display:none;' : ''; ?>">
                            <label for="cison_fs_state_manual">State / Region</label>
                            <input id="cison_fs_state_manual" type="text" name="state_manual"
                                value="<?php echo esc_attr($manual_state_value); ?>" <?php echo $is_nigeria ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section">
                    <h4>Professional Information</h4>
                    <div class="cison-fs__grid cison-fs__grid--two">
                        <div>
                            <label for="cison_fs_occupation">Current Occupation <span>*</span></label>
                            <input id="cison_fs_occupation" type="text" name="occupation"
                                value="<?php echo esc_attr($values['occupation']); ?>" required>
                        </div>
                        <div>
                            <label for="cison_fs_designation">Designation</label>
                            <input id="cison_fs_designation" type="text" name="designation"
                                value="<?php echo esc_attr($values['designation']); ?>">
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_employer">Employer / Institution</label>
                            <input id="cison_fs_employer" type="text" name="employer"
                                value="<?php echo esc_attr($values['employer']); ?>">
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="additional">
                    <h4>Additional Information</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_years">Years of Practice</label>
                            <input id="cison_fs_years" type="text" name="years_of_practice"
                                value="<?php echo esc_attr($values['years_of_practice']); ?>">
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_area">Area of Statistics</label>
                            <textarea id="cison_fs_area" name="area_of_practice"
                                rows="3"><?php echo esc_textarea($values['area_of_practice']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="membership-details">
                    <h4>Membership Details</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_member_category">Membership Category</label>
                            <select id="cison_fs_member_category" name="membership_category"<?php echo $view_status === 'edit' ? ' disabled' : ''; ?>>
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
                                    <input type="text" name="academic_qualifications[<?php echo $i; ?>][institution]"
                                        placeholder="Institution" value="<?php echo esc_attr($qual['institution'] ?? ''); ?>">
                                    <input type="text" name="academic_qualifications[<?php echo $i; ?>][degree]"
                                        placeholder="Degree / Qualification" value="<?php echo esc_attr($qual['degree'] ?? ''); ?>">
                                    <input type="text" name="academic_qualifications[<?php echo $i; ?>][year]" placeholder="Year"
                                        value="<?php echo esc_attr($qual['year'] ?? ''); ?>" class="cison-fs__qual-year">
                                    <button type="button" class="cison-fs__qual-remove" title="Remove">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="cison-fs-add-qual" class="cison-fs__qual-add">+ Add Qualification</button>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_experience">Professional Experience</label>
                            <textarea id="cison_fs_experience" name="professional_experience"
                                rows="4"><?php echo esc_textarea($values['professional_experience']); ?></textarea>
                        </div>
                    </div>

                    <div class="cison-fs__grid">
                        <div>
                            <label for="cison_fs_publications">Publications, Research and Contribution</label>
                            <textarea id="cison_fs_publications" name="publications"
                                rows="4"><?php echo esc_textarea($values['publications']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="cv">
                    <h4>Curriculum Vitae</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <?php if ($view_status === 'edit' && !empty($values['cv'])): ?>
                                <div class="cison-fs__help">Current CV: <a href="<?php echo esc_url($values['cv']); ?>"
                                        target="_blank">View uploaded CV</a> — upload a new file below only to replace
                                    it.</div>
                                <label for="cison_fs_cv">Replace CV</label>
                            <?php else: ?>
                                <label for="cison_fs_cv">Upload CV <span>*</span></label>
                            <?php endif; ?>
                            <input id="cison_fs_cv" type="file" name="cv" accept=".pdf,.doc,.docx,.txt">
                            <span class="cison-fs__help">Accepted formats: PDF, DOC, DOCX, TXT. Max size: 5MB.</span>
                        </div>
                    </div>
                </div>

                <div class="cison-fs__section js-form-section" data-section="certificates">
                    <h4>Certificates</h4>
                    <div class="cison-fs__grid">
                        <div>
                            <?php
                            $existing_cert_urls = array();
                            if ($view_status === 'edit' && !empty($values['certificates'])) {
                                $decoded = json_decode($values['certificates'], true);
                                if (is_array($decoded)) {
                                    $existing_cert_urls = $decoded;
                                }
                            }
                            ?>
                            <?php if (!empty($existing_cert_urls)): ?>
                                <div class="cison-fs__help">Current certificates:
                                    <?php foreach ($existing_cert_urls as $cert_url): ?>
                                        <a href="<?php echo esc_url($cert_url); ?>" target="_blank">
                                            <?php echo esc_html(basename(parse_url($cert_url, PHP_URL_PATH))); ?></a>
                                        &nbsp;
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <label>Upload <?php echo !empty($existing_cert_urls) ? 'Additional ' : ''; ?>Certificates</label>
                            <span class="cison-fs__help">Add a certificate below and use "+ Add Certificate" to upload
                                more. Accepted formats: JPG, PNG, GIF, PDF. Max size: 2MB each.</span>
                            <div id="cison-fs-certs" class="cison-fs__cert-list">
                                <div class="cison-fs__cert-row">
                                    <input type="file" name="certificates[]"
                                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                    <button type="button" class="cison-fs__cert-remove" title="Remove">&times;</button>
                                </div>
                            </div>
                            <button type="button" id="cison-fs-add-cert" class="cison-fs__cert-add">+ Add
                                Certificate</button>
                        </div>
                    </div>
                </div>

                <?php if ($has_valid_token): ?>
                    <div class="cison-fs__section js-form-section" data-section="sponsors">
                        <h4>Sponsors</h4>
                        <p class="cison-fs__help">You need two sponsors to endorse your application. Their details are required
                            below.</p>

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
                            <?php if ($view_status === 'edit' && !empty($values['signature'])): ?>
                                <div class="cison-fs__help">Current signature: <a href="<?php echo esc_url($values['signature']); ?>"
                                        target="_blank">View signature</a> — upload a new image below only to replace
                                    it.</div>
                                <label for="cison_fs_signature">Replace Signature (Image)</label>
                            <?php else: ?>
                                <label for="cison_fs_signature">Upload Signature (Image)</label>
                            <?php endif; ?>
                            <input id="cison_fs_signature" type="file" name="signature" accept="image/*">
                            <span class="cison-fs__help">Accepted formats: JPG, PNG, GIF. Max size: 2MB.</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="cison-fs__submit">
                    <?php echo $view_status === 'edit' ? 'Save Changes' : 'Submit Application &amp; Proceed to Payment'; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo cison_fellowship_render_styles(); ?>
    <?php echo cison_fellowship_render_scripts($is_member, $is_nsa_fellow); ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var container = document.querySelector(".cison-fs[data-edit='1']");
            if (!container) return;
            ["membership_status", "nsa_fellow", "nsa_fellow_id", "membership_number", "membership_category"].forEach(function (name) {
                var el = container.querySelector("[name='" + name + "']");
                if (el) el.setAttribute("disabled", "disabled");
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('cison_fellowship_application', 'cison_fellowship_form_shortcode');

function cison_fellowship_render_sponsor_fields($num, $data, $editable)
{
    $readonly_attr = $editable ? '' : 'readonly';
    $disabled_attr = $editable ? '' : 'disabled';
    $d = array_merge(array(
        'name' => '',
        'membership_id' => '',
        'membership_status' => '',
        'rank' => '',
        'signature' => '',
        'date' => '',
    ), $data);

    ob_start();
    ?>
    <div class="cison-fs__grid">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_name">Full Name <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_name" type="text" name="sponsor_<?php echo $num; ?>_name"
                value="<?php echo esc_attr($d['name']); ?>" <?php echo $editable ? 'required' : ''; ?>     <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <div class="cison-fs__grid cison-fs__grid--two">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_membership_id">Membership ID <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_membership_id" type="text"
                name="sponsor_<?php echo $num; ?>_membership_id" value="<?php echo esc_attr($d['membership_id']); ?>" <?php echo $editable ? 'required' : ''; ?>     <?php echo $readonly_attr; ?>>
        </div>
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_membership_status">Membership Status <span>*</span></label>
            <select id="cison_fs_s<?php echo $num; ?>_membership_status"
                name="sponsor_<?php echo $num; ?>_membership_status" <?php echo $editable ? 'required' : 'disabled'; ?>>
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
            <input id="cison_fs_s<?php echo $num; ?>_rank" type="text" name="sponsor_<?php echo $num; ?>_rank"
                value="<?php echo esc_attr($d['rank']); ?>" <?php echo $readonly_attr; ?>>
        </div>
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_signature">Signature (Image) <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_signature" type="file" name="sponsor_<?php echo $num; ?>_signature"
                accept="image/*" <?php echo $editable ? 'required' : 'disabled'; ?>>
            <?php if (!empty($d['signature'])): ?>
                <span class="cison-fs__help">Current: <a href="<?php echo esc_url($d['signature']); ?>" target="_blank">View
                        Signature</a></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="cison-fs__grid">
        <div>
            <label for="cison_fs_s<?php echo $num; ?>_date">Date <span>*</span></label>
            <input id="cison_fs_s<?php echo $num; ?>_date" type="date" name="sponsor_<?php echo $num; ?>_date"
                value="<?php echo esc_attr($d['date']); ?>" <?php echo $editable ? 'required' : ''; ?>     <?php echo $readonly_attr; ?>>
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
    $allowed_user_ids = array(216, 284, 180, 284, 797);
    if (!current_user_can('manage_options') && !in_array(get_current_user_id(), $allowed_user_ids)) {
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
                <input type="text" name="fs_s" value="<?php echo esc_attr($search); ?>"
                    placeholder="Search by name, email, reference...">
                <button type="submit">Search</button>
                <?php if ($search || $filter_value): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('fs_s', 'fs_paged', $filter_key))); ?>">Clear</a>
                <?php endif; ?>
            </form>

            <form method="get" class="cison-fs-submissions__filter">
                <select name="<?php echo esc_attr($filter_key); ?>" onchange="this.form.submit()">
                    <option value="">All Payment Status</option>
                    <?php foreach ($filter_options as $option): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($filter_value, $option); ?>>
                            <?php echo esc_html($option); ?></option>
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
                            $detail_url = add_query_arg('fs_ref', rawurlencode($row['reference_number'] ?? ''), CISON_FELLOWSHIP_DETAIL_URL);
                            ?>
                            <tr style="cursor:pointer;" onclick="window.location='<?php echo esc_url($detail_url); ?>';">
                                <td><a href="<?php echo esc_url($detail_url); ?>"
                                        style="color:#0f766e;font-weight:700;text-decoration:none;"><?php echo esc_html($row['reference_number'] ?: 'N/A'); ?></a>
                                </td>
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
                        <tr>
                            <td colspan="8">No fellowship submissions found.</td>
                        </tr>
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
// SHORTCODE: ADMIN SUBMISSION DETAIL
// ============================================================

function cison_fellowship_submission_detail_shortcode()
{
    $allowed_user_ids = array(216, 284, 180, 284, 797);
    if (!current_user_can('manage_options') && !in_array(get_current_user_id(), $allowed_user_ids)) {
        return '<p>You do not have permission to view fellowship submissions.</p>';
    }

    $ref = isset($_GET['fs_ref']) ? sanitize_text_field(wp_unslash($_GET['fs_ref'])) : '';
    if (empty($ref)) {
        return '<p>No reference number provided.</p>';
    }

    global $wpdb;
    $table_name = cison_fellowship_get_table_name();

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE reference_number = %s LIMIT 1", $ref),
        ARRAY_A
    );

    if (!$row) {
        return '<p>No submission found for reference: ' . esc_html($ref) . '</p>';
    }

    $s1_data = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
    $s2_data = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();
    $quals = !empty($row['academic_qualifications']) ? explode("\n", $row['academic_qualifications']) : array();
    $certificates = !empty($row['certificates']) ? json_decode($row['certificates'], true) : array();
    $certificates = is_array($certificates) ? $certificates : array();
    $cv_url = $row['cv'] ?? '';
    $show_member_content = ($row['is_member'] === 'member') && strtolower($row['is_nsa_fellow'] ?? '') !== 'yes';

    ob_start();
    ?>
    <div class="cison-fs-detail">
        <div class="cison-fs-detail__nav">
            <a href="<?php echo esc_url(CISON_FELLOWSHIP_SUBMISSIONS_URL); ?>">&larr; Back to Submissions</a>
        </div>

        <?php if (isset($_GET['fs_email_sent'])): ?>
            <div class="cison-fs-detail__message cison-fs-detail__message--success">Email sent successfully.</div>
        <?php elseif (isset($_GET['fs_email_error'])): ?>
            <div class="cison-fs-detail__message cison-fs-detail__message--error">
                There was an error sending the email. Please check the recipient addresses and try again.
                <?php if (!empty($_GET['fs_email_msg'])): ?>
                    <br><strong>Reason:</strong> <?php echo esc_html(wp_unslash($_GET['fs_email_msg'])); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['fs_field_email_sent'])): ?>
            <div class="cison-fs-detail__message cison-fs-detail__message--success">Request email sent to the applicant.</div>
        <?php elseif (isset($_GET['fs_field_email_error'])): ?>
            <div class="cison-fs-detail__message cison-fs-detail__message--error">
                There was an error sending the request email to the applicant. Please check the applicant's email address
                and selected fields and try again.
                <?php if (!empty($_GET['fs_email_msg'])): ?>
                    <br><strong>Reason:</strong> <?php echo esc_html(wp_unslash($_GET['fs_email_msg'])); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="cison-fs-detail__header">
            <h3>Submission Details</h3>
            <div class="cison-fs-detail__ref">
                Reference: <strong><?php echo esc_html($row['reference_number']); ?></strong>
            </div>
        </div>

        <div class="cison-fs-detail__grid">
            <div class="cison-fs-detail__card">
                <h4>Personal Information</h4>
                <div class="cison-fs-detail__fields">
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Full Name</span>
                        <span
                            class="cison-fs-detail__value"><?php echo esc_html(cison_fellowship_get_full_name($row)); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Title</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['title'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Email</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['email']); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Phone</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['phone'] ?: 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($row['gender'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Gender</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['gender']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['date_of_birth'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Date of Birth</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html(date_i18n('M j, Y', strtotime($row['date_of_birth']))); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['nationality'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Nationality</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['nationality']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['signature'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Signature</span>
                            <span class="cison-fs-detail__value"><a href="<?php echo esc_url($row['signature']); ?>"
                                    target="_blank">View Signature</a></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cison-fs-detail__card">
                <h4>Residential Address</h4>
                <div class="cison-fs-detail__fields">
                    <?php if (!empty($row['street'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Street</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['street']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['city'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">City</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['city']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['state'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">State</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['state']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Country</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['country'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <div class="cison-fs-detail__card">
                <h4>Professional Information</h4>
                <div class="cison-fs-detail__fields">
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Occupation</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['occupation'] ?: 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($row['designation'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Designation</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['designation']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['employer'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Employer / Institution</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['employer']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['years_of_practice'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Years of Practice</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($row['years_of_practice']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['area_of_practice'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Area of Statistics</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($row['area_of_practice']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cison-fs-detail__card">
                <h4>Membership Details</h4>
                <div class="cison-fs-detail__fields">
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Status</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['is_member'] ?: 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($row['membership_category'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Category</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($row['membership_category']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Member Number</span>
                        <span
                            class="cison-fs-detail__value"><?php echo esc_html($row['membership_number'] ?: 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($row['is_nsa_fellow'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">NSA Fellow</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['is_nsa_fellow']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($row['nsa_fellow_id'])): ?>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">NSA Fellow ID</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($row['nsa_fellow_id']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($show_member_content): ?>
                <div class="cison-fs-detail__card">
                    <h4>Academic Qualifications</h4>
                    <?php if (!empty($quals)): ?>
                        <table class="cison-fs-detail__quals-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quals as $i => $line): ?>
                                    <tr>
                                        <td><?php echo ($i + 1); ?></td>
                                        <td><?php echo esc_html($line); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($row['professional_experience'])): ?>
                <div class="cison-fs-detail__card">
                    <h4>Professional Experience</h4>
                    <div class="cison-fs-detail__text-block">
                        <?php echo esc_html($row['professional_experience']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($show_member_content && !empty($row['publications'])): ?>
                <div class="cison-fs-detail__card">
                    <h4>Publications, Research &amp; Contribution</h4>
                    <div class="cison-fs-detail__text-block">
                        <?php echo esc_html($row['publications']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($certificates)): ?>
                <div class="cison-fs-detail__card">
                    <h4>Certificates</h4>
                    <div class="cison-fs-detail__text-block">
                        <ul class="cison-fs-detail__file-list">
                            <?php foreach ($certificates as $cert_url): ?>
                                <li>
                                    <a href="<?php echo esc_url($cert_url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html(basename(parse_url($cert_url, PHP_URL_PATH))); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($cv_url)): ?>
                <div class="cison-fs-detail__card">
                    <h4>Curriculum Vitae</h4>
                    <div class="cison-fs-detail__text-block">
                        <ul class="cison-fs-detail__file-list">
                            <li>
                                <a href="<?php echo esc_url($cv_url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html(basename(parse_url($cv_url, PHP_URL_PATH))); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="cison-fs-detail__card">
                <h4>Sponsor 1 — <?php echo cison_fellowship_render_status_badge($row['sponsor_1_status'] ?? 'pending'); ?>
                </h4>
                <?php if (($row['sponsor_1_status'] ?? '') === 'waived'): ?>
                    <p class="cison-fs-detail__empty">Sponsorship not required — NSA Fellow.</p>
                <?php elseif (!empty($s1_data)): ?>
                    <div class="cison-fs-detail__fields">
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Full Name</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s1_data['name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Membership ID</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($s1_data['membership_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Membership Status</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($s1_data['membership_status'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Rank</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s1_data['rank'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Date</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s1_data['date'] ?? 'N/A'); ?></span>
                        </div>
                        <?php if (!empty($s1_data['signature'])): ?>
                            <div class="cison-fs-detail__field">
                                <span class="cison-fs-detail__label">Signature</span>
                                <span class="cison-fs-detail__value"><a href="<?php echo esc_url($s1_data['signature']); ?>"
                                        target="_blank">View Signature</a></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="cison-fs-detail__empty">Awaiting sponsor endorsement.</p>
                <?php endif; ?>
            </div>

            <div class="cison-fs-detail__card">
                <h4>Sponsor 2 — <?php echo cison_fellowship_render_status_badge($row['sponsor_2_status'] ?? 'pending'); ?>
                </h4>
                <?php if (($row['sponsor_2_status'] ?? '') === 'waived'): ?>
                    <p class="cison-fs-detail__empty">Sponsorship not required — NSA Fellow.</p>
                <?php elseif (!empty($s2_data)): ?>
                    <div class="cison-fs-detail__fields">
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Full Name</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s2_data['name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Membership ID</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($s2_data['membership_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Membership Status</span>
                            <span
                                class="cison-fs-detail__value"><?php echo esc_html($s2_data['membership_status'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Rank</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s2_data['rank'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="cison-fs-detail__field">
                            <span class="cison-fs-detail__label">Date</span>
                            <span class="cison-fs-detail__value"><?php echo esc_html($s2_data['date'] ?? 'N/A'); ?></span>
                        </div>
                        <?php if (!empty($s2_data['signature'])): ?>
                            <div class="cison-fs-detail__field">
                                <span class="cison-fs-detail__label">Signature</span>
                                <span class="cison-fs-detail__value"><a href="<?php echo esc_url($s2_data['signature']); ?>"
                                        target="_blank">View Signature</a></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="cison-fs-detail__empty">Awaiting sponsor endorsement.</p>
                <?php endif; ?>
            </div>

            <div class="cison-fs-detail__card cison-fs-detail__card--meta">
                <h4>Submission Metadata</h4>
                <div class="cison-fs-detail__fields">
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Payment Status</span>
                        <span
                            class="cison-fs-detail__value"><?php echo cison_fellowship_render_status_badge($row['payment_status']); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Application Status</span>
                        <span
                            class="cison-fs-detail__value"><?php echo esc_html($row['application_status'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Product IDs</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['product_ids'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Order ID</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['order_id'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">IP Address</span>
                        <span class="cison-fs-detail__value"><?php echo esc_html($row['ip_address'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Registered</span>
                        <span
                            class="cison-fs-detail__value"><?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($row['registration_date']))); ?></span>
                    </div>
                    <div class="cison-fs-detail__field">
                        <span class="cison-fs-detail__label">Last Updated</span>
                        <span
                            class="cison-fs-detail__value"><?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($row['updated_at']))); ?></span>
                    </div>
                </div>
            </div>

            <div class="cison-fs-detail__card cison-fs-detail__card--meta cison-fs-detail__card--email">
                <h4>Email This Submission</h4>
                <p class="cison-fs-detail__help">
                    Send this submission's details to one or more recipients. Separate multiple email addresses with commas
                    (e.g. a@example.com, b@example.com). The applicant's CV, signature and certificates are attached
                    automatically.
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    class="cison-fs-detail__email-form">
                    <input type="hidden" name="action" value="cison_fellowship_email_submission">
                    <?php wp_nonce_field('cison_fellowship_email_action', 'cison_fellowship_email_nonce'); ?>
                    <input type="hidden" name="cison_fellowship_email_submit" value="1">
                    <input type="hidden" name="fs_ref" value="<?php echo esc_attr($row['reference_number']); ?>">

                    <div class="cison-fs-detail__email-cols">
                        <div class="cison-fs-detail__email-settings">
                            <div class="cison-fs-detail__email-field">
                                <label for="cison_fs_email_to">To</label>
                                <input id="cison_fs_email_to" type="text" name="cison_fellowship_email_to"
                                    value="<?php echo esc_attr(strtolower($row['email'])); ?>"
                                    class="cison-fs-detail__input" required>
                                <span class="cison-fs-detail__hint">Separate multiple email addresses with commas.</span>
                            </div>
                            <div class="cison-fs-detail__email-field">
                                <label for="cison_fs_email_subject">Subject</label>
                                <input id="cison_fs_email_subject" type="text" name="cison_fellowship_email_subject"
                                    value="Fellowship Submission <?php echo esc_attr($row['reference_number']); ?>"
                                    class="cison-fs-detail__input" required>
                            </div>
                        </div>

                        <div class="cison-fs-detail__email-message">
                            <div class="cison-fs-detail__email-field">
                                <label>Message</label>
                                <div class="cison-fs-detail__tabs">
                                    <button type="button" class="cison-fs-detail__tab is-active"
                                        data-tab="preview">Preview</button>
                                    <button type="button" class="cison-fs-detail__tab" data-tab="source">Edit HTML</button>
                                </div>
                                <div class="cison-fs-detail__preview js-email-preview"></div>
                                <textarea id="cison_fs_email_message" name="cison_fellowship_email_message" rows="12"
                                    class="cison-fs-detail__input js-email-source"
                                    required><?php echo esc_textarea(cison_fellowship_build_submission_summary($row)); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="cison-fs-detail__send-btn">Send Email</button>
                </form>
            </div>

            <div class="cison-fs-detail__card cison-fs-detail__card--meta cison-fs-detail__card--email">
                <h4>Request Applicant to Complete Fields</h4>
                <p class="cison-fs-detail__help">
                    Select the fields the applicant needs to fill or correct. An email will be sent to
                    <strong><?php echo esc_html(strtolower($row['email'])); ?></strong> listing the selected fields.
                    Fields currently left empty are pre-selected.
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    class="cison-fs-detail__email-form">
                    <input type="hidden" name="action" value="cison_fellowship_request_fields">
                    <?php wp_nonce_field('cison_fellowship_fields_action', 'cison_fellowship_fields_nonce'); ?>
                    <input type="hidden" name="cison_fellowship_fields_submit" value="1">
                    <input type="hidden" name="fs_ref" value="<?php echo esc_attr($row['reference_number']); ?>">

                    <div class="cison-fs-detail__field-request-grid">
                        <?php foreach (cison_fellowship_get_field_request_map() as $key => $label):
                            $is_empty = empty($row[$key]);
                            ?>
                            <label class="cison-fs-detail__field-request-item">
                                <input type="checkbox" name="cison_fellowship_fields[]"
                                    value="<?php echo esc_attr($key); ?>" <?php checked($is_empty); ?>>
                                <span><?php echo esc_html($label); ?></span>
                                <?php if ($is_empty): ?>
                                    <em class="cison-fs-detail__field-request-empty">(empty)</em>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="cison-fs-detail__email-field">
                        <label for="cison_fs_fields_note">Additional note (optional)</label>
                        <textarea id="cison_fs_fields_note" name="cison_fellowship_fields_note" rows="4"
                            class="cison-fs-detail__input"
                            placeholder="Optional message from the committee..."></textarea>
                    </div>

                    <button type="submit" class="cison-fs-detail__send-btn">Send Request to Applicant</button>
                </form>
            </div>
        </div>
    </div>
    <?php echo cison_fellowship_submission_detail_styles(); ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var preview = document.querySelector(".js-email-preview");
            var source = document.querySelector(".js-email-source");
            var tabs = document.querySelectorAll(".cison-fs-detail__tab");
            if (!preview || !source) return;

            function renderPreview() {
                preview.innerHTML = source.value;
            }

            function setTab(activeTab) {
                var isSource = activeTab === "source";
                tabs.forEach(function (tab) {
                    var match = tab.getAttribute("data-tab");
                    tab.classList.toggle("is-active", match === activeTab);
                });
                source.style.display = isSource ? "" : "none";
                preview.style.display = isSource ? "none" : "block";
                if (!isSource) {
                    renderPreview();
                } else {
                    source.focus();
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener("click", function () {
                    setTab(tab.getAttribute("data-tab"));
                });
            });

            source.addEventListener("input", renderPreview);
            setTab("preview");
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('cison_fellowship_submission_detail', 'cison_fellowship_submission_detail_shortcode');

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

        .cison-fs__cert-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }

        .cison-fs__cert-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }

        .cison-fs__cert-row input[type="file"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: #f8fafc;
        }

        .cison-fs__cert-remove {
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

        .cison-fs__cert-remove:hover {
            background: #fecaca;
        }

        .cison-fs__cert-add {
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

        .cison-fs__cert-add:hover {
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

        .cison-fs-submissions__table tbody tr {
            transition: background 0.15s ease;
        }

        .cison-fs-submissions__table tbody tr:hover {
            background: #f0fdfa;
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

        .cison-fs-badge--waived {
            background: #e5e7eb;
            color: #6b7280;
        }

        .cison-fs-badge--rejected,
        .cison-fs-badge--failed {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>';
}

function cison_fellowship_submission_detail_styles()
{
    return '
    <style>
        .cison-fs-detail {
            margin: 24px 0;
        }

        .cison-fs-detail__nav {
            margin-bottom: 16px;
        }

        .cison-fs-detail__nav a {
            color: #0f766e;
            text-decoration: none;
            font-weight: 600;
        }

        .cison-fs-detail__nav a:hover {
            text-decoration: underline;
        }

        .cison-fs-detail__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .cison-fs-detail__header h3 {
            margin: 0;
            font-size: 1.6rem;
            color: #0f172a;
        }

        .cison-fs-detail__ref {
            color: #475569;
            font-size: 14px;
        }

        .cison-fs-detail__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .cison-fs-detail__card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
        }

        .cison-fs-detail__card h4 {
            margin: 0 0 14px;
            font-size: 1.05rem;
            color: #0f172a;
        }

        .cison-fs-detail__fields {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .cison-fs-detail__field {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .cison-fs-detail__field:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cison-fs-detail__label {
            color: #64748b;
            font-weight: 600;
            flex-shrink: 0;
        }

        .cison-fs-detail__value {
            color: #0f172a;
            text-align: right;
            word-break: break-word;
        }

        .cison-fs-detail__text-block {
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .cison-fs-detail__file-list {
            margin: 0;
            padding: 0;
            list-style: none;
            white-space: normal;
        }

        .cison-fs-detail__file-list li {
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .cison-fs-detail__file-list li:last-child {
            border-bottom: none;
        }

        .cison-fs-detail__file-list a {
            color: #0f766e;
            font-weight: 600;
            text-decoration: none;
            word-break: break-all;
        }

        .cison-fs-detail__file-list a:hover {
            text-decoration: underline;
        }

        .cison-fs-detail__empty {
            color: #94a3b8;
            font-style: italic;
            font-size: 14px;
            margin: 0;
        }

        .cison-fs-detail__quals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .cison-fs-detail__quals-table th,
        .cison-fs-detail__quals-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            vertical-align: top;
        }

        .cison-fs-detail__quals-table th {
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cison-fs-detail__card--meta {
            grid-column: 1 / -1;
        }

        .cison-fs-detail__card--meta .cison-fs-detail__fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 24px;
        }

        .cison-fs-detail__message {
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
        }

        .cison-fs-detail__message--success {
            background: #dcfce7;
            color: #166534;
        }

        .cison-fs-detail__message--error {
            background: #fee2e2;
            color: #991b1b;
        }

        .cison-fs-detail__card--email {
            background: #f8fafc;
        }

        .cison-fs-detail__help {
            margin: 0 0 16px;
            color: #64748b;
            font-size: 14px;
        }

        .cison-fs-detail__email-form .cison-fs-detail__fields {
            display: flex;
            flex-direction: column;
        }

        .cison-fs-detail__email-cols {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            margin-bottom: 8px;
        }

        .cison-fs-detail__email-field {
            margin-bottom: 16px;
        }

        .cison-fs-detail__email-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        .cison-fs-detail__hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #94a3b8;
        }

        .cison-fs-detail__tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 8px;
        }

        .cison-fs-detail__tab {
            padding: 6px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .cison-fs-detail__tab:hover {
            border-color: #0f766e;
        }

        .cison-fs-detail__tab.is-active {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
        }

        .cison-fs-detail__preview {
            display: none;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            background: #ffffff;
            max-height: 340px;
            overflow: auto;
        }

        .cison-fs-detail__preview table {
            font-family: Arial, sans-serif;
        }

        .cison-fs-detail__preview h2,
        .cison-fs-detail__preview h3 {
            font-family: Arial, sans-serif;
            color: #0f172a;
        }

        .cison-fs-detail__preview h3 {
            color: #0f766e;
        }

        .cison-fs-detail__input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            background: #fff;
            box-sizing: border-box;
        }

        .cison-fs-detail__input:focus {
            border-color: #0f766e;
            outline: none;
        }

        .cison-fs-detail__send-btn {
            display: inline-block;
            margin-top: 4px;
            padding: 10px 20px;
            border: 0;
            border-radius: 999px;
            background: #0f766e;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .cison-fs-detail__send-btn:hover {
            background: #115e59;
        }

        .cison-fs-detail__field-request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px;
            margin-bottom: 16px;
        }

        .cison-fs-detail__field-request-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            font-size: 13px;
            color: #334155;
            cursor: pointer;
        }

        .cison-fs-detail__field-request-item input {
            accent-color: #0f766e;
        }

        .cison-fs-detail__field-request-empty {
            margin-left: auto;
            font-style: normal;
            font-size: 11px;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        @media (max-width: 768px) {
            .cison-fs-detail__grid {
                grid-template-columns: 1fr;
            }

            .cison-fs-detail__email-cols {
                grid-template-columns: 1fr;
            }

            .cison-fs-detail__card--meta .cison-fs-detail__fields {
                grid-template-columns: 1fr;
            }

            .cison-fs-detail__field {
                flex-direction: column;
                gap: 2px;
            }

            .cison-fs-detail__value {
                text-align: left;
            }
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
        document.addEventListener("DOMContentLoaded", function () {
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
                formSections.forEach(function (section) {
                    var sectionName = section.getAttribute("data-section");
                    if (sectionName === "sponsors" && !hasToken) {
                        section.style.display = "none";
                        return;
                    }
                    // Certificates are available to every applicant regardless of membership status.
                    if (sectionName === "certificates" || sectionName === "cv") {
                        section.style.display = "";
                        return;
                    }
                    section.style.display = showSections ? "" : "none";
                });

                // CISON member number is required for all applicants
                var memberNumberInput = container.querySelector("[name='membership_number']");
                if (memberNumberInput) {
                    memberNumberInput.setAttribute("required", "required");
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
                rows.forEach(function (row, i) {
                    row.querySelectorAll("input").forEach(function (input) {
                        var name = input.getAttribute("name");
                        if (name) {
                            input.setAttribute("name", name.replace(/academic_qualifications\[\d+\]/, "academic_qualifications[" + i + "]"));
                        }
                    });
                });
            }

            if (addQualBtn) {
                addQualBtn.addEventListener("click", function () {
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
                qualsList.addEventListener("click", function (e) {
                    if (e.target.classList.contains("cison-fs__qual-remove")) {
                        var row = e.target.closest(".cison-fs__qual-row");
                        if (row) {
                            row.remove();
                            updateQualIndices();
                        }
                    }
                });
            }

            // Certificates add/remove
            var certsList = container.querySelector("#cison-fs-certs");
            var addCertBtn = container.querySelector("#cison-fs-add-cert");

            if (addCertBtn) {
                addCertBtn.addEventListener("click", function () {
                    var row = document.createElement("div");
                    row.className = "cison-fs__cert-row";
                    row.innerHTML = '<input type="file" name="certificates[]" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">' +
                        '<button type="button" class="cison-fs__cert-remove" title="Remove">&times;</button>';
                    certsList.appendChild(row);
                });
            }

            if (certsList) {
                certsList.addEventListener("click", function (e) {
                    if (e.target.classList.contains("cison-fs__cert-remove")) {
                        var row = e.target.closest(".cison-fs__cert-row");
                        if (row) {
                            row.remove();
                        }
                    }
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
