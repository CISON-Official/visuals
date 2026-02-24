<?php
/**
 * Design for the email tag
 * 
 */

add_action('bp_setup_nav', 'add_email_to_profile_tag', 100);

function add_email_to_profile_tag() {
    $current_user = wp_get_current_user();
    $allowed_users = array(938, 2459);

    if (!in_array($current_user->ID, $allowed_users)) {
        return ;
    }

    if (bp_is_my_profile()){
        return;
    }

    bp_core_new_nav_item( array(
        'name'                => __( 'Email', 'textdomain' ),
        'slug'                => 'send-email',
        'position'            => 80,
        'screen_function'     => 'custom_email_screen_view',
        'default_subnav_slug' => 'send-email',
        'item_css_id'         => 'custom-email-tab'
    ) 
    );
}
