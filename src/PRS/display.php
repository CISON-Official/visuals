<?php
function display_gravity_form_entries_shortcode($atts)
{
    // 1. Parse the shortcode attributes (Default to Form ID 1 if not provided)
    $atts = shortcode_atts(array(
        'id' => 1,
    ), $atts, 'display_gf_entries');

    $form_id = intval($atts['id']);

    // 2. Check if Gravity Forms and GFAPI class are available
    if (!class_exists('GFAPI')) {
        return '<p>Gravity Forms is not active.</p>';
    }

    // 3. Set search criteria (Only fetch active, non-deleted entries)
    $search_criteria = array('status' => 'active');

    // 4. Fetch the entries using Gravity Forms API
    $entries = GFAPI::get_entries($form_id, $search_criteria);

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
                    <th style="padding: 8px;">Date Submitted</th>
                    <th style="padding: 8px;">Field 1 Data</th>
                    <th style="padding: 8px;">Field 2 Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo esc_html($entry['id']); ?></td>
                        <td style="padding: 8px;"><?php echo esc_html($entry['date_created']); ?></td>
                        <!-- Replace '1' and '2' with your actual Gravity Forms Field IDs -->
                        <td style="padding: 8px;"><?php echo esc_html(rgar($entry, '1')); ?></td>
                        <td style="padding: 8px;"><?php echo esc_html(rgar($entry, '2')); ?></td>
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
