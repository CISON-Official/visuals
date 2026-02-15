<?php
/**
 * Shortcode to display Gravity Forms entries with specific fields
 * Usage: [all_user_entries form_id="15"]
 */
function gemini_display_all_entries_gf( $atts ) {
    $a = shortcode_atts( array(
        'form_id' => '15',
    ), $atts );

    // Restrict to admins (optional)
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<div class="bb-alert">Access Denied: You do not have permission to view all entries.</div>';
    }

    $form_id = intval( $a['form_id'] );
    $output  = '<div class="all-entries-container">';

    if ( class_exists( 'GFAPI' ) ) {

        $entries = GFAPI::get_entries(
            $form_id,
            array(),
            array( 'key' => 'date_created', 'direction' => 'DESC' ),
            array( 'offset' => 0, 'page_size' => 100 )
        );

        if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) {

            $output .= '<table class="entry-table" style="width:100%; border-collapse: collapse;">';
            $output .= '<thead style="background:#222;color:#fff;">
                <tr>
                    <th style="padding:10px;">Ref #</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Position</th>
                    <th>Questions</th>
                    <th>Heard About PRS</th>
                    <th>Date Submitted</th>
                    <th>Action</th>
                </tr>
            </thead><tbody>';

            foreach ( $entries as $entry ) {

                // Name field (ID 20)
                $first_name = rgar( $entry, '20.3' );
                $last_name  = rgar( $entry, '20.6' );
                $name       = trim( $first_name . ' ' . $last_name );

                // Other fields
                $email     = rgar( $entry, '21' );
                $company   = rgar( $entry, '19' );
                $position  = rgar( $entry, '7' );
                $questions = rgar( $entry, '8' );
                $heard     = rgar( $entry, '22' );

                $entry_id   = intval( $entry['id'] );
                $date_added = date( 'M j, Y - g:i a', strtotime( $entry['date_created'] ) );

                $edit_link = admin_url(
                    'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id
                );

                $output .= '<tr style="border-bottom:1px solid #eee;">';
                $output .= '<td style="padding:10px;">#' . esc_html( $entry_id ) . '</td>';
                $output .= '<td>' . esc_html( $name ) . '</td>';
                $output .= '<td>' . esc_html( $email ) . '</td>';
                $output .= '<td>' . esc_html( $company ) . '</td>';
                $output .= '<td>' . esc_html( $position ) . '</td>';
                $output .= '<td>' . esc_html( $questions ) . '</td>';
                $output .= '<td>' . esc_html( $heard ) . '</td>';
                $output .= '<td>' . esc_html( $date_added ) . '</td>';
                $output .= '<td><a href="' . esc_url( $edit_link ) . '" class="view-btn">Edit Entry</a></td>';
                $output .= '</tr>';
            }

            $output .= '</tbody></table>';

        } else {
            $output .= '<p>No entries found for this form.</p>';
        }
    } else {
        $output .= '<p>Gravity Forms is not active.</p>';
    }

    $output .= '</div>';
    return $output;
}
add_shortcode( 'all_user_entries', 'gemini_display_all_entries_gf' );
