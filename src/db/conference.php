<?php

add_action('woocommerce_checkout_init', 'store_registration_form_session');
function store_registration_form_session($checkout) {
    if (!empty($_POST) && isset($_POST['member_id'])) {
        $form_data = array();
        $fields = array('member_id', 'registering_for', 'title', 'first_name', 'last_name', 
                       'email', 'phone', 'occupation', 'organisation', 'street', 
                       'city', 'state', 'postcode', 'country', 'gender', 'hear_about');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $form_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        WC()->session->set('nsa_registration_data', $form_data);
    }
}

add_action('woocommerce_after_checkout_billing_form', 'add_nsa_hidden_fields');
function add_nsa_hidden_fields($checkout) {
    $form_data = WC()->session->get('nsa_registration_data');
    if ($form_data) {
        echo '<div style="display:none;">';
        foreach ($form_data as $key => $value) {
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($key), esc_attr($value));
        }
        echo '</div>';
    }
}

add_action('woocommerce_order_status_completed', 'save_nsa_registration_on_payment_success', 10, 1);
function save_nsa_registration_on_payment_success($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';
    
    $form_data = WC()->session->get('nsa_registration_data');
    if (!$form_data) return;
    
    $data = array(
        'member_id' => $form_data['member_id'] ?? '',
        'registering_for' => $form_data['registering_for'] ?? '',
        'title' => $form_data['title'] ?? '',
        'first_name' => $form_data['first_name'] ?? '',
        'last_name' => $form_data['last_name'] ?? '',
        'email' => $form_data['email'] ?? '',
        'phone' => $form_data['phone'] ?? '',
        'occupation' => $form_data['occupation'] ?? '',
        'organisation' => $form_data['organisation'] ?? '',
        'street' => $form_data['street'] ?? '',
        'city' => $form_data['city'] ?? '',
        'state' => $form_data['state'] ?? '',
        'postcode' => $form_data['postcode'] ?? '',
        'country' => $form_data['country'] ?? 'NG',
        'gender' => $form_data['gender'] ?? '',
        'hear_about' => $form_data['hear_about'] ?? '',
        'order_id' => $order_id,
        'payment_status' => 'paid',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    );
    
    $wpdb->insert($table_name, $data);
    
    WC()->session->__unset('nsa_registration_data');
    
    $admin_email = get_option('admin_email');
    $subject = '✅ NSA Registration Complete - Order #' . $order_id;
    $message = "Payment successful!\n\n";
    $message .= "Member ID: " . $data['member_id'] . "\n";
    $message .= "Name: " . $data['title'] . ' ' . $data['first_name'] . ' ' . $data['last_name'] . "\n";
    $message .= "Email: " . $data['email'] . "\n";
    $message .= "Order: " . $order_id . "\n";
    
    wp_mail($admin_email, $subject, $message);
}


add_action('woocommerce_checkout_order_processed', 'save_nsa_on_order_processed', 10, 3);
function save_nsa_on_order_processed($order_id, $posted_data, $order) {
    if (WC()->session->__isset('nsa_registration_data')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nsa_registrations';
        $wpdb->update($table_name, array('payment_status' => 'pending'), 
                     array('order_id' => $order_id), array('%s'), array('%d'));
    }
}
?>
