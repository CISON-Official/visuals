<?php
// Check if a customer with a given email has purchased a specific product
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
 * Shortcode to display Gravity Forms entries (excluding deleted/trashed)
 * Usage: [all_user_entries form_id="15"]
 */
function gemini_display_all_entries_gf($atts) {
    $a = shortcode_atts([
        'form_id' => '15',
    ], $atts);
    $current_user = wp_get_current_user();
    $allowed_users = array(938);

    // Restrict access to admins
    if (!current_user_can('manage_options') || !in_array($current_user->ID, $allowed_users)) {
        return '<div class="bb-alert">Access Denied: You do not have permission to view all entries.</div>';
    }

    $form_id = intval($a['form_id']);
    $output  = '<div class="all-entries-container">';

    if (class_exists('GFAPI')) {

        // Only ACTIVE entries (exclude trash/deleted)
        $search_criteria = [
            'status' => 'active'
        ];

        $entries = GFAPI::get_entries(
            $form_id,
            $search_criteria,
            ['key' => 'date_created', 'direction' => 'DESC'],
            ['offset' => 0, 'page_size' => 100]
        );

        if (!is_wp_error($entries) && !empty($entries)) {

            $output .= '<table class="entry-table" style="width:100%; border-collapse: collapse;">';
            $output .= '<thead style="color:#fff;">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Position</th>
                    <th>Questions</th>
                    <th>Heard About PRS</th>
                    <th>Date Submitted</th>
                    <th>Has Paid</th>
                </tr>
            </thead><tbody>';

            foreach ($entries as $entry) {

                // Combine first and last name (fields 20.3 & 20.6)
                $first_name = rgar($entry, '20.3');
                $last_name  = rgar($entry, '20.6');
                $name       = trim($first_name . ' ' . $last_name);

                // Other fields
                $email     = rgar($entry, '21');
                $company   = rgar($entry, '19');
                $position  = rgar($entry, '7');
                $questions = rgar($entry, '8');
                $heard     = rgar($entry, '22');

                $entry_id   = intval($entry['id']);
                $date_added = date('M j, Y - g:i a', strtotime($entry['date_created']));

                // Check if email purchased the product (replace 12293 with your product ID)
                $has_purchased = has_customer_purchased_product_by_email($email, 12293) ? "Yes" : "No";

                $output .= '<tr style="border-bottom:1px solid #eee;">';
                $output .= '<td>' . esc_html($name) . '</td>';
                $output .= '<td>' . esc_html($email) . '</td>';
                $output .= '<td>' . esc_html($company) . '</td>';
                $output .= '<td>' . esc_html($position) . '</td>';
                $output .= '<td>' . esc_html($questions) . '</td>';
                $output .= '<td>' . esc_html($heard) . '</td>';
                $output .= '<td>' . esc_html($date_added) . '</td>';
                $output .= '<td>' . esc_html($has_purchased) . '</td>';
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

// Register shortcode
add_shortcode('all_user_entries', 'gemini_display_all_entries_gf');
