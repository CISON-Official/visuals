<?php

add_action('bp_template_redirect', 'cison_custom_guest_access_control', 1);

function cison_custom_guest_access_control()
{
    if (is_user_logged_in()) {
        return;
    }

    $public_uris = array(
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

    );

    $current_uri = $_SERVER['REQUEST_URI'];

    $is_allowed = false;
    foreach ($public_uris as $uri) {
        if (strpos($current_uri, $uri) !== false) {
            $is_allowed = true;
            break;
        }
    }

    if (!$is_allowed && !is_page('login') && !strpos($current_uri, 'wp-login.php')) {
        bp_core_no_access(array(
            'root' => home_url('members/wp-login.php'),
            'redirect' => home_url($current_uri),
            'mode' => 1
        ));
    }
}
