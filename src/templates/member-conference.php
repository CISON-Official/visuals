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

// Cleaned up the duplicate add_filter call
add_filter('gform_confirmation', 'redirect_and_add_multiple_to_cart', 10, 4);
function redirect_and_add_multiple_to_cart($confirmation, $form, $entry, $ajax)
{
    $target_form_id = 26;
    if ($form['id'] != $target_form_id) {
        return $confirmation;
    }

    $product_mapping = [
        'preconference-virtual' => 14302,
        'preconference-on-site' => 12816,
        'conference-virtual' => 12818,
        'conference-on-site' => 12817
    ];

    if (!function_exists('wc_get_checkout_url') || !function_exists('bp_get_member_type')) {
        return $confirmation;
    }

    $member_id = rgar($entry, '6');
    if (!verify_member_id($member_id)) {
        return array(
            'redirect' => false,
            'message' => '<div class="gform_confirmation_message_' . $form['id'] . '">We could not verify your Membership ID. Please contact support before trying again.</div>',
        );
    }

    save_nsa_registration_entry($entry, $form);

    // --- ENHANCEMENT FOR LOGGED-OUT GUESTS ---
    // 1. Force WooCommerce to drop persistent session tracker cookies for non-logged-in visitors
    if (isset(WC()->session) && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
    }

    // 2. Safely capture the active cart container or load an immediate fallback wrapper instance
    if (isset(WC()->cart)) {
        WC()->cart->empty_cart();
    } else {
        WC()->cart = new WC_Cart();
    }
    // ------------------------------------------

    $items_added = false;

    foreach ($entry as $key => $value) {
        if ((strpos($key, '11.') === 0 && !empty($value)) || (strpos($key, '17.') === 0 && !empty($value))) {
            $checkbox_value = sanitize_text_field(strtolower(trim($value)));
            if (array_key_exists($checkbox_value, $product_mapping)) {
                $product_id = $product_mapping[$checkbox_value];
                $added = WC()->cart->add_to_cart($product_id, 1);

                if ($added) {
                    $items_added = true;
                } else {
                    error_log("NSA registration: failed to add product $product_id ($checkbox_value) to cart.");
                    foreach (wc_get_notices('error') as $notice) {
                        error_log("WC error: " . $notice['notice']);
                    }
                }
            }
        }
    }

    if (!$items_added) {
        return array(
            'redirect' => false,
            'message' => '<div class="gform_confirmation_message_' . $form['id'] . '">There was a problem adding your registration to the cart. Please contact support.</div>',
        );
    }

    // --- ENHANCEMENT FOR LOGGED-OUT GUESTS ---
    // 3. Explicitly calculate metrics and prices so WooCommerce locks it in before routing
    WC()->cart->calculate_totals();
    // ------------------------------------------

    $checkout_url = wc_get_checkout_url();
    $confirmation = array('redirect' => $checkout_url);

    return $confirmation;
}

function save_nsa_registration_entry($entry, $form)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'nsa_registrations';

    // Support gathering both Field 11 and Field 17 checkbox selections into your database logs
    $registering_for = array();
    foreach ($entry as $key => $value) {
        if ((strpos($key, '11.') === 0 && !empty($value)) || (strpos($key, '17.') === 0 && !empty($value))) {
            $registering_for[] = sanitize_text_field($value);
        }
    }

    $data = array(
        'member_id' => sanitize_text_field(rgar($entry, '6')),
        'registering_for' => implode(', ', $registering_for),
        'title' => sanitize_text_field(rgar($entry, '1')),
        'first_name' => sanitize_text_field(rgar($entry, '3.3')),
        'middle_name' => sanitize_text_field(rgar($entry, '3.4')),
        'last_name' => sanitize_text_field(rgar($entry, '3.6')),
        'email' => sanitize_email(rgar($entry, '4')),
        'phone' => sanitize_text_field(rgar($entry, '5')),
        'occupation' => sanitize_text_field(rgar($entry, '7')),
        'organisation' => sanitize_text_field(rgar($entry, '8')),
        'street' => sanitize_text_field(rgar($entry, '14.1')),
        'city' => sanitize_text_field(rgar($entry, '14.3')),
        'state' => sanitize_text_field(rgar($entry, '14.4')),
        'postcode' => sanitize_text_field(rgar($entry, '14.5')),
        'country' => sanitize_text_field(rgar($entry, '14.6')),
        'gender' => sanitize_text_field(rgar($entry, '13')),
        'order_id' => 0,
        'payment_status' => 'pending',
        'ip_address' => sanitize_text_field(rgar($entry, 'ip')),
    );

    $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');

    $wpdb->insert($table_name, $data, $formats);
}


add_filter('woocommerce_is_purchasable', 'force_guest_purchase_for_conference', 9999, $product);
function force_guest_purchase_for_conference($is_purchasable, $product)
{
    $exclude = array(14302,12816,12818,12817,14270,14271);
    if (in_array($product->get_id(), $exclude)) {
        return true;
    }
    return $is_purchasable;
}