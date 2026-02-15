<?php
function has_customer_purchased_product_by_email($email, $product_id) {
    if (!$email || !$product_id) {
        return false;
    }

    $orders = wc_get_orders([
        'limit' => -1,
        'billing_email' => $email,
        'status' => ['wc-completed']
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ((int)$item->get_product_id() === (int)$product_id) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Shortcode to display gravity forms for the comanies
 */

function gemini_display_all_entries_for_company( $atts ) {
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

        // Only ACTIVE entries (exclude trash/deleted)
        $search_criteria = array(
            'status' => 'active'
        );

        $entries = GFAPI::get_entries(
            $form_id,
            $search_criteria,
            array( 'key' => 'date_created', 'direction' => 'DESC' ),
            array( 'offset' => 0, 'page_size' => 100 )
        );

        if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) {

            $output .= '<table class="entry-table" style="width:100%; border-collapse: collapse;">';
            // <th style="padding:10px;">Ref #</th>
            $output .= '<thead style="color:#fff;">
                <tr>
                    <th>Organization Name</th>
                    <th>Organization Email</th>
                    <th>How may People Registered?</th>
                    <th>Date Submitted</th>
                    <th> Has Paid </th>
                </tr>
            </thead><tbody>';

            foreach ( $entries as $entry ) {


                // Name field (ID 20)
                $name = rgar( $entry, '14.3' );
                // $last_name  = rgar( $entry, '14.6' );
                // $name       = trim( $first_name . ' ' . $last_name );

                // Other fields
                $email     = rgar( $entry, '14.6' );
                $register   = rgar( $entry, '20' );

                $entry_id   = intval( $entry['id'] );
                $date_added = date( 'M j, Y - g:i a', strtotime( $entry['date_created'] ) );

                $edit_link = admin_url(
                    'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id
                );

                if (has_customer_purchased_product_by_email($email, 12293)) {
    
                    $has_purchased = "Yes";
                } else {
                    $has_purchased = "No";
                }

                $output .= '<tr style="border-bottom:1px solid #eee;">';
                // $output .= '<td style="padding:10px;">#' . esc_html( $entry_id ) . '</td>';
                $output .= '<td>' . esc_html( $name ) . '</td>';
                $output .= '<td>' . esc_html( $email ) . '</td>';
                $output .= '<td>' . esc_html( $register ) . '</td>';
                $output .= '<td>' . esc_html( $date_added ) . '</td>';
                // $output .= '<td><a href="' . esc_url( $edit_link ) . '" class="view-btn">Edit Entry</a></td>';
                $output .= '<td>' . esc_html($has_purchased ) . '</td>';
                $output .= '</tr>';
            }

            $output .= '</tbody></table>';

        } else {
            $output .= '<p>No active entries found for this form.</p>';
        }
    } else {
        $output .= '<p>Gravity Forms is not active.</p>';
    }

    $output .= '</div>';
    return $output;
}
add_shortcode( 'all_user_entries_for_company', 'gemini_display_all_entries_gf' );
