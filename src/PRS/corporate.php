<?php
/**
 * Shortcode to display ALL form entries for ALL users
 * Usage: [all_user_entries form_id="15"]
 */
function gemini_display_all_entries($atts) {
    $a = shortcode_atts( array(
        'form_id' => '15', // Change to your target form ID
    ), $atts );

    // 1. SECURITY: Only allow Admins to see this list by default
    // If you want everyone to see it, delete the next 3 lines.
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<div class="bb-alert">Access Denied: You do not have permission to view all entries.</div>';
    }

    $output = '<div class="all-entries-container">';

    if ( function_exists( 'wpforms' ) ) {
        // 2. Fetch ALL entries (removing the user_id filter)
        $entries = wpforms()->entry->get_entries( array(
            'form_id' => $a['form_id'],
            'number'  => 100, // Limit to last 100 entries to maintain speed
        ) );

        if ( ! empty( $entries ) ) {
            $output .= '<table class="entry-table" style="width:100%; border: 1px solid #ddd; border-collapse: collapse;">';
            $output .= '<thead style="background-color:#222; color:#fff;"><tr>
                            <th style="padding:12px;">Ref #</th>
                            <th>User Name</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr></thead><tbody>';

            foreach ( $entries as $entry ) {
                // Get the user data for the person who submitted
                $user_info = get_userdata($entry->user_id);
                $user_display_name = ($user_info) ? $user_info->display_name : 'Guest';

                $output .= '<tr style="border-bottom:1px solid #eee;">';
                $output .= '<td style="padding:12px;">#' . esc_html($entry->entry_id) . '</td>';
                $output .= '<td><strong>' . esc_html($user_display_name) . '</strong></td>';
                $output .= '<td>' . date( 'M j, Y - g:i a', strtotime( $entry->date_added ) ) . '</td>';
                $output .= '<td><a href="' . admin_url('admin.php?page=wpforms-entries&view=details&entry_id=' . $entry->entry_id) . '" class="view-btn">Edit Entry</a></td>';
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
        } else {
            $output .= '<p>No entries found in the database for this form.</p>';
        }
    }

    $output .= '</div>';
    return $output;
}
add_shortcode( 'all_user_entries', 'gemini_display_all_entries' );