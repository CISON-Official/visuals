<?php

function cison_get_examination_registration_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'cison_examination_registrations';
}

function cison_validate_examination_registration_data($raw_data)
{
    $data = array(
        'membership_id' => sanitize_text_field($raw_data['membership_id'] ?? ''),
        'title' => sanitize_text_field($raw_data['title'] ?? ''),
        'first_name' => sanitize_text_field($raw_data['first_name'] ?? ''),
        'last_name' => sanitize_text_field($raw_data['last_name'] ?? ''),
        'email' => sanitize_email($raw_data['email'] ?? ''),
        'phone' => sanitize_text_field($raw_data['phone'] ?? ''),
        'gender' => sanitize_text_field($raw_data['gender'] ?? ''),
        'date_of_birth' => sanitize_text_field($raw_data['date_of_birth'] ?? ''),
        'examination_stage' => sanitize_text_field($raw_data['examination_stage'] ?? ''),
        'highest_qualification' => sanitize_text_field($raw_data['highest_qualification'] ?? ''),
        'current_employer' => sanitize_text_field($raw_data['current_employer'] ?? ''),
        'years_experience' => sanitize_text_field($raw_data['years_experience'] ?? ''),
        'street' => sanitize_text_field($raw_data['street'] ?? ''),
        'city' => sanitize_text_field($raw_data['city'] ?? ''),
        'state' => sanitize_text_field($raw_data['state'] ?? ''),
        'country' => strtoupper(substr(sanitize_text_field($raw_data['country'] ?? 'NG'), 0, 2)),
        'notes' => sanitize_textarea_field($raw_data['notes'] ?? ''),
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    );

    $required_fields = array(
        'title' => 'Title',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'examination_stage' => 'Examination stage',
        'highest_qualification' => 'Highest qualification',
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
    $formats = array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
    );

    $result = $wpdb->insert($table_name, $data, $formats);

    if ($result === false) {
        return new WP_Error('db_insert_failed', 'Unable to save examination registration: ' . $wpdb->last_error);
    }

    return (int) $wpdb->insert_id;
}
