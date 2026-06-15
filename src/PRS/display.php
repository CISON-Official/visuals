<?php

function has_email_bought_product($email, $product_id)
{
    if (!$email || !$product_id) {
        return false;
    }

    $customer_orders = wc_get_orders(array(
        'customer' => $email,
        'status' => array('wc-completed', 'wc-processing'),
        'limit' => -1,
        'return' => 'ids',
    ));

    if (empty($customer_orders)) {
        return false;
    }

    foreach ($customer_orders as $order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id || $item->get_variation_id() == $product_id) {
                return true;
            }
        }
    }

    return false;
}

function display_gravity_form_entries_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => 1,
        'product' => 12293,
    ), $atts, 'display_gf_entries');

    $form_id = intval($atts['id']);
    $product_id = intval($atts['product']);

    if (!class_exists('GFAPI')) {
        return '<p>Gravity Forms is not active.</p>';
    }

    $entries = GFAPI::get_entries(
        $form_id,
        array("status" => "active"),
        array(),
        array('offset' => 0, 'page_size' => 200)
    );

    if (empty($entries)) {
        return '<p>No entries found for this form.</p>';
    }

    ob_start();
    ?>
    <div class="gf-entries-display">
        <table border="1" style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background-color:#f2f2f2;">
                    <th style="padding:8px;">Entry ID</th>
                    <th style="padding:8px;">First Name</th>
                    <th style="padding:8px;">Last Name</th>
                    <th style="padding:8px;">Email</th>
                    <th style="padding:8px;">Date Submitted</th>
                    <th style="padding:8px;">Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php $email = esc_html(rgar($entry, '2')); ?>
                    <tr>
                        <td style="padding:8px;"><?php echo esc_html($entry['id']); ?></td>
                        <td style="padding:8px;"><?php echo esc_html(rgar($entry, '1.3')); ?></td>
                        <td style="padding:8px;"><?php echo esc_html(rgar($entry, '1.4')); ?></td>
                        <td style="padding:8px;"><?php echo $email; ?></td>
                        <td style="padding:8px;"><?php echo esc_html($entry['date_created']); ?></td>
                        <td style="padding:8px;">
                            <?php echo has_email_bought_product($email, $product_id) ? 'Paid' : 'Pending'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('display_gf_entries', 'display_gravity_form_entries_shortcode');