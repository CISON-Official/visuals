<?php

add_action('bp_template_redirect', 'cison_custom_guest_access_control', 1);

function cison_custom_guest_access_control()
{

    if (is_user_logged_in()) {
        return;
    }

    $public_uris = array(
        '/donation/',
        '/fellowship-application/',
        '/q3-prs-student-registration/',
        '/q4-prs-student-registration/',
        '/q3-prs-virtual-registration/',
        '/q4-prs-virtual-registration/',
        '/q3-prs-corporate-registration/',
        '/q4-prs-corporate-registration/',
        '/q3-prs-individual-registration/',
        '/q4-prs-individual-registration/',
        '/certificate-verifiers/',
        '/q2-virtual-prs-registration/',
        '/2nd-quarter-prs-student-registration/',
        '/2nd-prs-individual-registration/',
        '/corporate-prs-2nd-quarter-registration/',
        '/examination-submissions/',
        '/checkout/',
        '/checkout/order-received/',
        '/register/',
        '/activate/',
        '/login/',
        '/member-registration/',
        '/event/',
        '/news/',
        '/groups/',
        '/event/1st-annual-conference-1st-pre-conference-workshop/',
        '/guidelines-for-cisons-elections/',
        '/refund_returns/',
        '/privacy-policy/',
        '/terms-of-service/',
        '/thank-you-for-your-r/',
        '/verify-certificate/',
        '/product-category/conference/',
        '/cart/',
        '/checkout/',
        '/maintenance/',
        '/product/prs-participants/',
        '/product/prs-organization-1st-quarter/',
        '/product/prs-undergraduate-students-1st-quarter/',
        '/participants-registration/',
        '/organization-registration/',
        '/q1-2026-planning-research-and-statistics-prs/',
        '/student-registration-page/',
        '/3rd-workshop-preconference-and-conference-registration/',
        '/checkout/',
        '/checkout/order-received/',
        '/checkout/order-pay/',
        '/12724-2/',
        '/product/conference-fee-virtual/',
        '/product/annual-conference-on-site-and-pre-conference-workshop/',
        '/corporate-registration-2/',
        '/signup/',
        '/group-conference-registration/',
        '/product/workshop-virtual-2026/',
        '/product/annual-conference-virtual-non-member-2026/',
        '/product/annual-conference-on-site-non-member/'
    );

    $current_uri = $_SERVER['REQUEST_URI'];

    $is_allowed = false;
    foreach ($public_uris as $uri) {
        if (strpos($current_uri, $uri) !== false) {
            $is_allowed = true;
            break;
        }
    }

    // if (strpos($current_uri, 'cison-members/me/profile/') !== false) {
    //     if (isset(WC()->cart)) {
    //         WC()->cart->empty_cart();
    //     }
    // }


    if (!$is_allowed && !is_page('login') && !strpos($current_uri, 'wp-login.php')) {
        bp_core_no_access(array(
            'root' => home_url('members/wp-login.php'),
            'redirect' => home_url($current_uri),
            'mode' => 1
        ));
    }
}

add_action('template_redirect', 'cison_maybe_clear_profile_cart');

function cison_maybe_clear_profile_cart()
{
    if (!is_user_logged_in()) {
        return;
    }

    if (!function_exists('bp_is_user') || !bp_is_user() || !bp_is_my_profile()) {
        return;
    }

    if (!function_exists('WC') || !WC()) {
        return;
    }

    $user_id = get_current_user_id();
    $transient_key = 'cison_cart_cleared_' . $user_id;

    if (get_transient($transient_key)) {
        return;
    }

    if (!WC()->cart) {
        WC()->initialize_cart();
    }

    if (WC()->cart) {
        WC()->cart->empty_cart();
        set_transient($transient_key, current_time('timestamp'), DAY_IN_SECONDS);
    }
}
