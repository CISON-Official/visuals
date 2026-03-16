<?php

add_filter('wp_nav_menu_items', 'filter_menu_by_permission', 10, 2);

function filter_menu_by_permission($items, $args)
{
    // if ($args->theme_location == 'primary') {
    $toadd = '';

    if (current_user_can('manage_options')) {
        $toadd .= '<li class="menu-item"><a href="https://my.cison.org.ng/members/wp-admin/admin.php">Backend</a></li>';
    }

    // $user = wp_get_current_user();
    // if (in_array('journal_editor', (array) $user->roles)) {
    //     $items .= '<li class="menu-item"><a href="/editor-panel/">Review Manuscripts</a></li>';
    // }
    // }
    return $toadd . $items;
}
?>