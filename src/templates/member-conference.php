<?php

function verify_member_id($member_id)
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

add_filter('gform_validation', 'validate_member_type_input');
function validate_member_type_input($validation_result)
{
    $form = $validation_result['form'];

    $target_form_id = 26;
    if ($form['id'] != $target_form_id) {
        return $validation_result;
    }

    $target_field_id = 6;
    $member_id_value = rgpost('input_' . $target_field_id);

    if (!verify_member_id($member_id_value)) {
        $validation_result['is_valid'] = false;

        foreach ($form['fields'] as &$field) {
            if ($field->id == $target_field_id) {
                $field->failed_validation = true;
                $field->validation_message = 'The provided Membership ID is invalid or could not be found in our database records.';
                break;
            }
        }
    }

    $validation_result['form'] = $form;
    return $validation_result;
}

add_filter('gform_confirmation', 'redirect_and_add_multiple_to_cart', 10, 4);
function redirect_and_add_multiple_to_cart($confirmation, $form, $entry, $ajax)
{
    $target_form_id = 26;
    if ($form['id'] != $target_form_id) {
        return $confirmation;
    }

    $product_mapping = [
        'preconference-virtual' => 14263,
        'preconference-on-site' => 12816,
        'conference-virtual' => 12818,
        'conference-on-site' => 12817
    ];

    if (!function_exists('wc_get_checkout_url') || !function_exists('bp_get_member_type')) {
        return $confirmation;
    }

    $member_id = rgar($entry, '6');
    if (!verify_member_id($member_id)) {
        return $confirmation;
    }

    save_nsa_registration_entry($entry, $form);

    if (isset(WC()->cart)) {
        WC()->cart->empty_cart();
    }

    $items_added = false;

    foreach ($entry as $key => $value) {
        if (strpos($key, '11.') === 0 && !empty($value)) {
            $checkbox_value = sanitize_text_field(strtolower(trim($value)));
            if (array_key_exists($checkbox_value, $product_mapping)) {
                $product_id = $product_mapping[$checkbox_value];
                WC()->cart->add_to_cart($product_id, 1);
                $items_added = true;
            }
        }
    }

    if ($items_added) {
        $checkout_url = wc_get_checkout_url();
        $confirmation = array('redirect' => $checkout_url);
    }

    return $confirmation;
}

function save_nsa_registration_entry($entry, $form)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'nsa_registrations';

    $registering_for = array();
    foreach ($entry as $key => $value) {
        if (strpos($key, '11.') === 0 && !empty($value)) {
            $registering_for[] = sanitize_text_field($value);
        }
    }

    $data = array(
        'member_id' => sanitize_text_field(rgar($entry, '10')),
        'registering_for' => implode(', ', $registering_for),
        'title' => sanitize_text_field(rgar($entry, '1')),
        'first_name' => sanitize_text_field(rgar($entry, '2.3')),
        'last_name' => sanitize_text_field(rgar($entry, '2.6')),
        'email' => sanitize_email(rgar($entry, '3')),
        'phone' => sanitize_text_field(rgar($entry, '4')),
        'occupation' => sanitize_text_field(rgar($entry, '5')),
        'organisation' => sanitize_text_field(rgar($entry, '6')),
        'street' => sanitize_text_field(rgar($entry, '8.1')),
        'city' => sanitize_text_field(rgar($entry, '8.3')),
        'state' => sanitize_text_field(rgar($entry, '8.4')),
        'postcode' => sanitize_text_field(rgar($entry, '8.5')),
        'country' => sanitize_text_field(rgar($entry, '8.6')),
        'gender' => sanitize_text_field(rgar($entry, '9')),
        'order_id' => 0,
        'payment_status' => 'pending',
        'ip_address' => sanitize_text_field(rgar($entry, 'ip')),
    );

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
        '%d',
        '%s',
        '%s',
    );

    $wpdb->insert($table_name, $data, $formats);
}