<?php

function cison_get_examination_registration_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'cison_examination_registrations';
}

function cison_get_examination_registration_formats($data)
{
    $format_map = array(
        'reference_number' => '%s',
        'is_member' => '%s',
        'membership_id' => '%s',
        'title' => '%s',
        'first_name' => '%s',
        'middle_name' => '%s',
        'last_name' => '%s',
        'email' => '%s',
        'phone' => '%s',
        'gender' => '%s',
        'date_of_birth' => '%s',
        'examination_stage' => '%s',
        'highest_qualification' => '%s',
        'current_employer' => '%s',
        'street' => '%s',
        'city' => '%s',
        'state' => '%s',
        'country' => '%s',
        'payment_platform' => '%s',
        'payment_status' => '%s',
        'application_status' => '%s',
        'notes' => '%s',
        'registration_date' => '%s',
        'updated_at' => '%s',
        'ip_address' => '%s',
    );

    $formats = array();
    foreach (array_keys($data) as $key) {
        $formats[] = $format_map[$key] ?? '%s';
    }

    return $formats;
}

function cison_generate_examination_reference_number()
{
    global $wpdb;

    $table_name = cison_get_examination_registration_table_name();

    do {
        $reference_number = 'CISON-EX-' . wp_date('Ymd') . '-' . wp_rand(1000, 9999);
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE reference_number = %s LIMIT 1",
                $reference_number
            )
        );
    } while ($existing_id);

    return $reference_number;
}

function cison_validate_examination_registration_data($raw_data)
{
    $is_member = strtolower(sanitize_text_field($raw_data['is_member'] ?? ''));
    $country = strtoupper(substr(sanitize_text_field($raw_data['country'] ?? 'NG'), 0, 2));
    $state = 'NG' === $country
        ? sanitize_text_field($raw_data['state'] ?? '')
        : sanitize_text_field($raw_data['state_manual'] ?? ($raw_data['state'] ?? ''));

    $data = array(
        'is_member' => in_array($is_member, array('yes', 'no'), true) ? $is_member : '',
        'membership_id' => sanitize_text_field($raw_data['membership_id'] ?? ''),
        'title' => sanitize_text_field($raw_data['title'] ?? ''),
        'first_name' => sanitize_text_field($raw_data['first_name'] ?? ''),
        'middle_name' => sanitize_text_field($raw_data['middle_name'] ?? ''),
        'last_name' => sanitize_text_field($raw_data['last_name'] ?? ''),
        'email' => strtolower(sanitize_email($raw_data['email'] ?? '')),
        'phone' => sanitize_text_field($raw_data['phone'] ?? ''),
        'gender' => sanitize_text_field($raw_data['gender'] ?? ''),
        'date_of_birth' => sanitize_text_field($raw_data['date_of_birth'] ?? ''),
        'examination_stage' => sanitize_text_field($raw_data['examination_stage'] ?? ''),
        'highest_qualification' => sanitize_text_field($raw_data['highest_qualification'] ?? ''),
        'current_employer' => sanitize_text_field($raw_data['current_employer'] ?? ''),
        'street' => sanitize_text_field($raw_data['street'] ?? ''),
        'city' => sanitize_text_field($raw_data['city'] ?? ''),
        'state' => $state,
        'country' => $country,
        'payment_platform' => sanitize_text_field($raw_data['payment_platform'] ?? ''),
        'notes' => sanitize_textarea_field($raw_data['notes'] ?? ''),
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    );

    $required_fields = array(
        'is_member' => 'Membership status',
        'title' => 'Title',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'examination_stage' => 'Examination stage',
        'highest_qualification' => 'Highest qualification',
        'payment_platform' => 'Payment platform',
        'state' => 'State',
        'country' => 'Country',
    );

    foreach ($required_fields as $field => $label) {
        if (empty($data[$field])) {
            return new WP_Error('missing_field', sprintf('%s is required.', $label));
        }
    }

    if (!is_email($data['email'])) {
        return new WP_Error('invalid_email', 'Please enter a valid email address.');
    }

    if ('yes' === $data['is_member'] && empty($data['membership_id'])) {
        return new WP_Error('missing_membership_id', 'CISON Membership ID is required for members.');
    }

    if ('no' === $data['is_member']) {
        $data['membership_id'] = '';
    }

    if (!empty($data['date_of_birth'])) {
        $date = date_create($data['date_of_birth']);
        if (!$date) {
            return new WP_Error('invalid_dob', 'Please provide a valid date of birth.');
        }
        $data['date_of_birth'] = $date->format('Y-m-d');
    } else {
        $data['date_of_birth'] = null;
    }

    return $data;
}

function cison_insert_examination_registration($raw_data)
{
    global $wpdb;

    $data = cison_validate_examination_registration_data($raw_data);
    if (is_wp_error($data)) {
        return $data;
    }

    $table_name = cison_get_examination_registration_table_name();
    $existing_registration = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, reference_number, payment_status, application_status
            FROM $table_name
            WHERE email = %s
            ORDER BY id ASC
            LIMIT 1",
            $data['email']
        ),
        ARRAY_A
    );

    $data['reference_number'] = !empty($existing_registration['reference_number'])
        ? $existing_registration['reference_number']
        : cison_generate_examination_reference_number();
    $data['payment_status'] = !empty($existing_registration['payment_status'])
        ? $existing_registration['payment_status']
        : 'pending';
    $data['application_status'] = !empty($existing_registration['application_status'])
        ? $existing_registration['application_status']
        : 'submitted';
    $data['updated_at'] = current_time('mysql');

    if ($existing_registration) {
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => (int) $existing_registration['id']),
            cison_get_examination_registration_formats($data),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_update_failed', 'Unable to update examination registration: ' . $wpdb->last_error);
        }

        return array(
            'registration_id' => (int) $existing_registration['id'],
            'reference_number' => $data['reference_number'],
            'updated' => true,
        );
    }

    $data['registration_date'] = current_time('mysql');
    $result = $wpdb->insert($table_name, $data, cison_get_examination_registration_formats($data));

    if ($result === false) {
        return new WP_Error('db_insert_failed', 'Unable to save examination registration: ' . $wpdb->last_error);
    }

    return array(
        'registration_id' => (int) $wpdb->insert_id,
        'reference_number' => $data['reference_number'],
        'updated' => false,
    );
}
