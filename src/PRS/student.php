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
 * Short code to disply the entries of the students
 */

function display_all_entries_for_students ($atts) {
    $a = shortcode_atts(
        array(
            'form_id' => '15',
        ),
        $atts
    );

    if (!current_user_can('manage_options')) {
        return '<div class="bb-alert">Access Denied: You do not have permission to view all entries.</div>';
    }

    $form_id = intval($a['form_id']);
    $output = '<div class="all-entries-container">';

    if (class_exists('GFAPI')) {

        $search_criteria = array('status'=>'active');

        $entries = GFAPI::get_entries($form_id, $search_criteria, array('key'=>'date_created', 'direction'=>'DESC'), array('offset'=>0, 'page_size'=>100));

        if (!is_wp_error($entries) && !empty($entries)) {
            $output .= "<table class='entry-table' style='width:100%; border-collapse: collapse;'><thead><tr>";
            $output .= "<th>Name</th><th>Email</th><th>Phone</th><th>Institution</th><th>University</th><th>Student ID</th><th>Date Submitted</th><th> Has Paid </th></tr></thead><tbody>";
            foreach ($entries as $entry) {
                $first_name = rgar($entry, '1.3');
                $last_name = rgar($entry, '1.6');
                $name = trim($first_name . ' ' . $last_name);
                
                $email = rgar($entry, '3.1');
                $phone = rgar($entry, '4');
                $institution = rgar($entry, '5');

                $faculty = rgar($entry, '6.2');
                $department = rgar($entry, '6.4');
                $matric_no = rgar($entry, '6.5');
                
                $university = trim('Faculty: ' . $faculty . '\nDepartment: ' . $department . '\nMatric Number: ' . $matric_no);

                if (has_customer_purchased_product_by_email($email, 12293)) {
    
                    $has_purchased = "Yes";
                } else {
                    $has_purchased = "No";
                }

                $student_id = rgar($entry, '7');
                $date_added = date('M j, Y - g:i a', strtotime($entry['date_created']));

                $output .= "<tr><td>" . esc_html($name) . "</td><td>" . esc_html($email) . "</td><td>" . esc_html($phone) . "</td><td>" . esc_html($institution) . "</td><td>" . esc_html($university) . "</td><td>" . esc_html($student_id) . "</td><td>" . esc_html($date_added) . "</td>";
                $output .= "<td>" . esc_html($has_purchased) . "</td></tr>";
            }
            $output .= "</tbody></table>";
        } else {
            $output .= "<p>No active entries found for this form.</p>";
        }
    } else {
        $output .= "<p>Gravity Forms is not active.</p>";
    }

    $output .= "</div>";
    return $output;
}

add_shortcode('all_user_entries_for_students', 'display_all_entries_for_students');

 