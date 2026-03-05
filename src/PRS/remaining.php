<?php
// Check if a customer with a given email has purchased a specific product
function remaining_customer_purchased_product_by_email($email, $product_id)
{
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
            if ((int) $item->get_product_id() === (int) $product_id) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Shortcode to display all Gravity Forms entries for companies
 */
function remaining_gemini_display_all_entries_for_company($atts)
{
    $a = shortcode_atts([
        'form_id' => '15',
    ], $atts);

    $current_user = wp_get_current_user();
    $allowed_users = array(938, 2459);

    // Restrict access to admins
    if (!in_array($current_user->ID, $allowed_users)) {
        return '<div class="bb-alert">Access Denied: You do not have permission to view all entries.</div>';
    }

    $form_id = intval($a['form_id']);
    $output = '<div class="all-entries-container">';

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
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Date Submitted</th>
                    <th>Has Paid</th>
                </tr>
            </thead><tbody>';

            foreach ($entries as $entry) {

                $name = rgar($entry, '1.3'); // Name field
                $lastname = rgar($entry, '1.6'); // Email field
                $email = rgar($entry, '3');   // Registered people
                $date_added = date('M j, Y - g:i a', strtotime($entry['date_created']));

                // Check if email purchased the product (replace 12293 with your product ID)
                $has_purchased = remaining_customer_purchased_product_by_email($email, 12721) ? "Yes" : "No";

                $output .= '<tr style="border-bottom:1px solid #eee;">';
                $output .= '<td>' . esc_html($name) . '</td>';
                $output .= '<td>' . esc_html($lastname) . '</td>';
                $output .= '<td>' . esc_html($email) . '</td>';
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
add_shortcode('all_users_paying_for_remaining_conference', 'remaining_gemini_display_all_entries_for_company');
