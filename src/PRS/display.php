<?php

function has_email_bought_product($email, $product_id)
{
    if (!$email || !$product_id) {
        return false;
    }

    // Get all orders for this customer email
    $customer_orders = wc_get_orders(array(
        'customer' => $email,
        'status' => array('wc-completed', 'wc-processing'), // only count paid orders
        'limit' => -1,
        'return' => 'ids',
    ));

    if (empty($customer_orders)) {
        return false;
    }

    foreach ($customer_orders as $order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();

            // Check both simple products and product variations
            if ($item_product_id == $product_id || $item_variation_id == $product_id) {
                return true;
            }
        }
    }

    return false;
}
function display_gravity_form_entries_shortcode($atts)
{
    // 1. Parse the shortcode attributes (Default to Form ID 1 if not provided)
    $atts = shortcode_atts(array(
        'id' => 1,
        'product' => 12293,
    ), $atts, 'display_gf_entries');

    $form_id = intval($atts['id']);

    // 2. Check if Gravity Forms and GFAPI class are available
    if (!class_exists('GFAPI')) {
        return '<p>Gravity Forms is not active.</p>';
    }

    // 3. Set search criteria (Only fetch active, non-deleted entries)
    $search_criteria = array();

    // 4. Fetch the entries using Gravity Forms API
    $sorting = array();
    $paging = array('offset' => 0, 'page_size' => 200);
    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);

    if (empty($entries)) {
        return '<p>No entries found for this form.</p>';
    }

    // 5. Start building the HTML table string (Output buffering handles layout safely)
    ob_start();
    ?>
    <div class="gf-entries-display">
        <table border="1" style="width:100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="padding: 8px;">Entry ID</th>
                    <th style="padding: 8px;">First Name</th>
                    <th style="padding: 8px;">LastName</th>
                    <th style="padding: 8px;">Email</th>
                    <th style="padding: 8px;">Date Submitted</th>
                    <th style="padding: 8px;">Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo esc_html($entry['id']); ?></td>
                        <!-- Replace '1' and '2' with your actual Gravity Forms Field IDs -->
                        <td style="padding: 8px;"><?php echo esc_html(rgar($entry, '1.3')); ?></td>
                        <td style="padding: 8px;"><?php echo esc_html(rgar($entry, '1.4')); ?></td>
                        <td style="padding: 8px;"><?php echo esc_html(rgar($entry, '2')); ?></td>
                        <td style="padding: 8px;"><?php echo esc_html($entry['date_created']); ?></td>
                        <td style="padding: 8px;">Pending</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
// Register the shortcode with WordPress
add_shortcode('display_gf_entries', 'display_gravity_form_entries_shortcode');
