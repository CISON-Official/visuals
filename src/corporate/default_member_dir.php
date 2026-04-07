<?php

/**
 * Exclude corporate users from the buddyboss members directory
 */

add_action('bp_user_query_uid_clauses', function ($sql_clauses, $bp_user_query) {
    global $wpdb;

    if (!bp_is_members_directory()) {
        return $sql_clauses;
    }

    $corporate_ids = $wpdb->get_col($wpdb->prepare("
        SELECT user_id
        FROM {$wpdb->prefix}bp_xprofile_data
        WHERE field_id = %d
        and value != %s
    ", 1614, 'Corporate'));

    if (!empty($corporate_ids)) {
        $ids = implode(',', array_map('intval', $corporate_ids));
        $sql_clauses['where'][] = "u.ID NOT IN ($ids)";
    }

    return $sql_clauses;
});


/**
 * Fix the member count to exclude Corporate Ueser
 */
add_filter('bp_get_total_member_count', function ($count) {
    global $wpdb;

    if (!bp_is_members_directory()) {
        return $count;
    }

    $corporate_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}bp_xprofile_data
        WHERE field_id = %d
        and value != %s
    ", 1614, 'Corporate'));

    return max(0, $count - intval($corporate_count));
});

