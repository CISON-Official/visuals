<?php

// -------------------------------------------------------
// Helper - get account type
// -------------------------------------------------------
function get_user_account_type($user_id)
{
    if (!function_exists('bp_get_profile_field_data'))
        return null;

    $account_type = bp_get_profile_field_data(array(
        'field' => 1614,
        'user_id' => $user_id,
    ));

    error_log('get_user_account_type --> User ID: ' . $user_id . ' | Account Type: ' . ($account_type ?: 'EMPTY'));

    return strtolower($account_type);
}


// -------------------------------------------------------
// Filter profile nav tabs
// -------------------------------------------------------
add_filter('bp_nouveau_get_nav_items_member', function ($tabs) {
    $user_id = bp_displayed_user_id() ?: get_current_user_id();
    $account_type = get_user_account_type($user_id);

    error_log('bp_nouveau_get_nav_items_member --> All tab slugs: ' . implode(', ', array_keys($tabs)));

    if ($account_type === 'corporate') {

        // -------------------------------------------------------
        // Remove these tabs for corporate
        // -------------------------------------------------------
        $remove = array('connections', 'forums', 'groups');

        foreach ($remove as $tab) {
            if (isset($tabs[$tab])) {
                unset($tabs[$tab]);
                error_log('bp_nouveau_get_nav_items_member --> Removed tab: ' . $tab . ' for corporate user: ' . $user_id);
            } else {
                error_log('bp_nouveau_get_nav_items_member --> Tab not found: ' . $tab . ' | Check slug name in log above');
            }
        }

        // -------------------------------------------------------
        // Add Payments tab for corporate
        // -------------------------------------------------------
        $tabs['payments'] = array(
            'slug' => 'payments',
            'title' => __('Payments', 'buddyboss'),
            'link' => bp_displayed_user_domain() . 'payments/',
            'position' => 50,
        );
        error_log('bp_nouveau_get_nav_items_member --> Added Payments tab for corporate user: ' . $user_id);

        // -------------------------------------------------------
        // Add Members tab for corporate
        // -------------------------------------------------------
        $tabs['members'] = array(
            'slug' => 'members',
            'title' => __('Members', 'buddyboss'),
            'link' => bp_displayed_user_domain() . 'members/',
            'position' => 60,
        );
        error_log('bp_nouveau_get_nav_items_member --> Added Members tab for corporate user: ' . $user_id);

    } 

    return $tabs;
});


// -------------------------------------------------------
// Register the custom nav items with BuddyPress/BuddyBoss
// so the URLs actually work
// -------------------------------------------------------
add_action('bp_setup_nav', function () {
    $user_id = bp_displayed_user_id() ?: get_current_user_id();
    $account_type = get_user_account_type($user_id);

    if ($account_type !== 'corporate')
        return;

    $user_domain = bp_displayed_user_domain() ?: bp_loggedin_user_domain();

    // -------------------------------------------------------
    // Register Payments nav
    // -------------------------------------------------------
    bp_core_new_nav_item(array(
        'name' => __('Payments', 'buddyboss'),
        'slug' => 'payments',
        'position' => 50,
        'screen_function' => 'corporate_payments_screen',
        'default_subnav_slug' => 'payments',
        'item_css_id' => 'payments',
        'show_for_displayed_user' => true,
        'user_domain' => $user_domain,
    ));

    // -------------------------------------------------------
    // Register Members nav
    // -------------------------------------------------------
    bp_core_new_nav_item(array(
        'name' => __('Members', 'buddyboss'),
        'slug' => 'members',
        'position' => 60,
        'screen_function' => 'corporate_members_screen',
        'default_subnav_slug' => 'members',
        'item_css_id' => 'members',
        'show_for_displayed_user' => true,
        'user_domain' => $user_domain,
    ));
});


// -------------------------------------------------------
// Screen function for Payments tab
// -------------------------------------------------------
function corporate_payments_screen()
{
    add_action('bp_template_content', function () {
        echo '<h3>' . __('Payments', 'buddyboss') . '</h3>';
        echo '<p>' . __('Your payments will appear here.', 'buddyboss') . '</p>';
        // add your payments content/shortcode here
        // e.g. echo do_shortcode( '[your_payments_shortcode]' );
    });
    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}


// -------------------------------------------------------
// Screen function for Members tab
// -------------------------------------------------------
function corporate_members_screen()
{
    add_action('bp_template_content', function () {
        echo '<h3>' . __('Members', 'buddyboss') . '</h3>';
        echo '<p>' . __('Your members will appear here.', 'buddyboss') . '</p>';
        // add your members content/shortcode here
        // e.g. echo do_shortcode( '[your_members_shortcode]' );
    });
    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}
?>