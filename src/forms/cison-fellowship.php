<?php
/**
 * CISON Fellowship Application - Gravity Forms + WooCommerce Checkout
 *
 * When Gravity Forms form #27 (Fellowship Application) is submitted, this
 * file:
 *   1. Determines the fellowship type from the form inputs.
 *   2. Adds the relevant WooCommerce product(s) to the cart.
 *   3. Redirects straight to the WooCommerce checkout (skipping the cart page).
 *
 * The fellowship record is only saved to the database AFTER payment has
 * completed (see save_fellowship_on_payment_complete below).
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CISON_FELLOWSHIP_FORM_ID', 27);
define('CISON_FELLOWSHIP_ENTRY_KEY', 'cison_fellowship_entry');

/**
 * The URL of the page that hosts the fellowship form (form #27).
 *
 * This is used to build the "sponsor link" that is emailed to applicants so
 * they can share it with their two sponsors. Sponsors opening this link only
 * see (and can only edit) the sponsorship section.
 *
 * Replace with the actual page URL for your site, or set it via the
 * `cison_fellowship_form_url` filter.
 */
if (!defined('CISON_FELLOWSHIP_FORM_URL')) {
    define('CISON_FELLOWSHIP_FORM_URL', home_url('/fellowship-application/'));
}

// Sponsor share URL query parameter.
define('CISON_FELLOWSHIP_SPONSOR_PARAM', 'cison_sponsor');

/**
 * Field IDs that belong to the sponsorship sections and must remain editable
 * when the form is shared with sponsors (everything else gets locked).
 */
function cison_fellowship_sponsor_field_ids()
{
    // Sponsor 1 fields.
    $sponsor_1 = array(39, 40, 41, 42, 48);
    // Sponsor 2 fields.
    $sponsor_2 = array(44, 45, 46, 47, 50);

    return array_merge($sponsor_1, $sponsor_2);
}

/**
 * Gravity Forms form that uses these products.
 *
 * Membership status (input 19): member / non-member
 * NSA Fellow (input 21): yes / no
 *
 * Mapping:
 *   - Non-member                         => product 14837
 *   - Member, NOT an NSA fellow          => product 14835
 *   - Member, NSA fellow                 => all 5 products:
 *                                              14830, 14839, 14841, 14856, 14866
 */
define('CISON_FELLOWSHIP_PRODUCT_NON_MEMBER', 14837);
define('CISON_FELLOWSHIP_PRODUCT_MEMBER_NON_FELLOW', 14835);
define('CISON_FELLOWSHIP_PRODUCT_NSA_FELLOW', array(14830, 14839, 14841, 14856, 14866));

function cison_get_fellowship_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'cison_fellowship_registrations';
}

function cison_generate_fellowship_reference_number()
{
    global $wpdb;

    $table_name = cison_get_fellowship_table_name();

    do {
        $reference_number = 'CISON-FS-' . wp_date('Ymd') . '-' . wp_rand(1000, 9999);
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE reference_number = %s LIMIT 1",
                $reference_number
            )
        );
    } while ($existing_id);

    return $reference_number;
}

/**
 * Resolve which product IDs should be added to the cart for a submission.
 *
 * @param array $entry Gravity Forms entry values.
 * @return int[] Product IDs to add.
 */
function cison_fellowship_resolve_products($entry)
{
    $membership = cison_fellowship_field_value($entry, 19);
    $is_nsa_fellow = cison_fellowship_field_value($entry, 21);

    $is_non_member = in_array(strtolower($membership), array('non-member', 'non_member', 'nonmember'), true);
    $is_member = in_array(strtolower($membership), array('member'), true);

    if ($is_non_member) {
        return array(CISON_FELLOWSHIP_PRODUCT_NON_MEMBER);
    }

    if ($is_member) {
        $fellow = in_array(strtolower($is_nsa_fellow), array('yes', 'true', '1'), true);
        if ($fellow) {
            return CISON_FELLOWSHIP_PRODUCT_NSA_FELLOW;
        }

        return array(CISON_FELLOWSHIP_PRODUCT_MEMBER_NON_FELLOW);
    }

    // Fallback for an unrecognised membership value: treat as non-member.
    return array(CISON_FELLOWSHIP_PRODUCT_NON_MEMBER);
}

/**
 * Safely read a single value from a Gravity Forms entry array.
 *
 * @param array $entry The Gravity Forms entry.
 * @param int   $field_id Field ID.
 * @return string
 */
function cison_fellowship_field_value($entry, $field_id)
{
    return isset($entry[(string) $field_id]) ? trim((string) $entry[(string) $field_id]) : '';
}

/**
 * Read a file-upload field's value and return the upload URL.
 *
 * @param array $entry Gravity Forms entry.
 * @param int   $field_id Field ID.
 * @return string
 */
function cison_fellowship_file_url($entry, $field_id)
{
    $raw = cison_fellowship_field_value($entry, $field_id);
    if (empty($raw)) {
        return '';
    }

    $json = json_decode($raw, true);

    return is_array($json) && !empty($json)
        ? (is_string($json[0]) ? $json[0] : '')
        : $raw;
}

/**
 * Hook: run after a Gravity Forms submission (form #27).
 *
 * Adds the correct product(s) to the WooCommerce cart, stores the entry data
 * in the session (so we can save to DB after payment), and redirects to
 * checkout.
 */
add_action('gform_after_submission_' . CISON_FELLOWSHIP_FORM_ID, 'cison_fellowship_handle_submission', 10, 2);
function cison_fellowship_handle_submission($entry, $form)
{
    // Always notify the applicant, even if the cart cannot be prepared.
    cison_fellowship_send_applicant_email($entry);

    if (!class_exists('WooCommerce') || !WC()) {
        return;
    }

    if (!WC()->cart) {
        WC()->initialize_cart();
    }

    $product_ids = cison_fellowship_resolve_products($entry);

    // Always start clean so stale selections never linger.
    WC()->cart->empty_cart();

    if (empty($product_ids)) {
        return;
    }

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product && $product->exists()) {
            WC()->cart->add_to_cart($product_id, 1);
        }
    }

    WC()->cart->calculate_totals();

    // Persist the entry so it can be saved to the DB once payment completes.
    WC()->session->set(CISON_FELLOWSHIP_ENTRY_KEY, $entry);

    wp_safe_redirect(wc_get_checkout_url());
    exit;
}

/**
 * @return string The shareable sponsor link for the fellowship form.
 */
function cison_fellowship_sponsor_link()
{
    $base = apply_filters('cison_fellowship_form_url', CISON_FELLOWSHIP_FORM_URL);

    return add_query_arg(CISON_FELLOWSHIP_SPONSOR_PARAM, '1', $base);
}

/**
 * Email the applicant congratulating them for applying and instructing them
 * to get two sponsors to vouch for them via the shareable link.
 *
 * @param array $entry Gravity Forms entry.
 */
function cison_fellowship_send_applicant_email($entry)
{
    $email = cison_fellowship_field_value($entry, 2);
    if (empty($email) || !is_email($email)) {
        return;
    }

    $first_name = cison_fellowship_field_value($entry, '1.3');
    $sponsor_link = cison_fellowship_sponsor_link();

    $subject = apply_filters(
        'cison_fellowship_email_subject',
        'Congratulations on Your Fellowship Application'
    );

    $message = sprintf(
        "Dear %s,\n\n" .
        "Congratulations on applying to become a CISON Fellow!\n\n" .
        "To complete your application, you will need two sponsors to vouch for you.\n\n" .
        "Please share the link below with your two potential sponsors. They will be able " .
        "to provide their supporting details directly in your application form:\n\n" .
        "%s\n\n" .
        "Once both sponsors have submitted their endorsement, your application will be " .
        "reviewed by the fellowship committee.\n\n" .
        "Best regards,\nCISON",
        $first_name ?: 'CISON Applicant',
        $sponsor_link
    );

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    wp_mail($email, $subject, $message, $headers);
}

/**
 * Hook: after WooCommerce payment completes, save the fellowship record.
 */
add_action('woocommerce_payment_complete', 'cison_fellowship_save_on_payment_complete');
add_action('woocommerce_order_status_completed', 'cison_fellowship_save_on_payment_complete');
function cison_fellowship_save_on_payment_complete($order_id)
{
    if (!WC()->session) {
        return;
    }

    $entry = WC()->session->get(CISON_FELLOWSHIP_ENTRY_KEY);
    if (!$entry || !is_array($entry)) {
        return;
    }

    global $wpdb;
    $table_name = cison_get_fellowship_table_name();

    $products = cison_fellowship_resolve_products($entry);
    $product_ids = implode(',', $products);

    $data = array(
        'reference_number' => cison_generate_fellowship_reference_number(),
        'entry_id' => isset($entry['id']) ? (int) $entry['id'] : 0,
        'order_id' => (int) $order_id,
        'is_member' => cison_fellowship_field_value($entry, 19),
        'is_nsa_fellow' => cison_fellowship_field_value($entry, 21),
        'membership_category' => cison_fellowship_field_value($entry, 25),
        'membership_number' => cison_fellowship_field_value($entry, 26),
        'title' => cison_fellowship_field_value($entry, '1.2'),
        'first_name' => cison_fellowship_field_value($entry, '1.3'),
        'middle_name' => cison_fellowship_field_value($entry, '1.4'),
        'last_name' => cison_fellowship_field_value($entry, '1.6'),
        'email' => strtolower(sanitize_email(cison_fellowship_field_value($entry, 2))),
        'phone' => cison_fellowship_field_value($entry, 13),
        'gender' => cison_fellowship_field_value($entry, 9),
        'date_of_birth' => cison_fellowship_date_value($entry, 10),
        'nationality' => cison_fellowship_field_value($entry, 11),
        'occupation' => cison_fellowship_field_value($entry, 14),
        'designation' => cison_fellowship_field_value($entry, 15),
        'employer' => cison_fellowship_field_value($entry, 16),
        'street' => cison_fellowship_field_value($entry, '12.1'),
        'city' => cison_fellowship_field_value($entry, '12.3'),
        'state' => cison_fellowship_field_value($entry, '12.4'),
        'country' => cison_fellowship_field_value($entry, '12.6'),
        'years_of_practice' => cison_fellowship_field_value($entry, 27),
        'area_of_practice' => cison_fellowship_field_value($entry, 28),
        'academic_qualifications' => cison_fellowship_list_value($entry, 29),
        'professional_experience' => cison_fellowship_field_value($entry, 30),
        'publications' => cison_fellowship_field_value($entry, 31),
        'num_sponsors' => (int) cison_fellowship_field_value($entry, 36),
        'signature' => cison_fellowship_file_url($entry, 33),
        'product_ids' => $product_ids,
        'payment_status' => 'paid',
        'application_status' => 'submitted',
        'registration_date' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'ip_address' => isset($entry['ip']) ? $entry['ip'] : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    );

    $wpdb->insert($table_name, $data);

    // Clean up session once saved.
    WC()->session->__unset(CISON_FELLOWSHIP_ENTRY_KEY);
}

/**
 * Format a Gravity Forms date-dropdown field (three array parts:
 * month, day, year) into a MySQL date string.
 *
 * @param array $entry Gravity Forms entry.
 * @param int   $field_id Field ID.
 * @return string|null
 */
function cison_fellowship_date_value($entry, $field_id)
{
    $month = isset($entry[$field_id . '.1']) ? (int) $entry[$field_id . '.1'] : 0;
    $day = isset($entry[$field_id . '.2']) ? (int) $entry[$field_id . '.2'] : 0;
    $year = isset($entry[$field_id . '.3']) ? (int) $entry[$field_id . '.3'] : 0;

    if (!$month || !$day || !$year) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Format a Gravity Forms list field (array of rows/columns) into a readable
 * multi-line string for storage.
 *
 * @param array $entry Gravity Forms entry.
 * @param int   $field_id Field ID.
 * @return string
 */
function cison_fellowship_list_value($entry, $field_id)
{
    if (!isset($entry[$field_id]) || !is_array($entry[$field_id])) {
        return '';
    }

    $lines = array();
    foreach ($entry[$field_id] as $row) {
        if (is_array($row)) {
            $lines[] = implode(' | ', array_map('sanitize_text_field', $row));
        } else {
            $lines[] = sanitize_text_field($row);
        }
    }

    return implode("\n", $lines);
}

/**
 * Whether the form is being opened via the sponsor share link.
 *
 * @return bool
 */
function cison_fellowship_is_sponsor_view()
{
    return isset($_GET[CISON_FELLOWSHIP_SPONSOR_PARAM]) && '1' === $_GET[CISON_FELLOWSHIP_SPONSOR_PARAM];
}

/**
 * Lock every field except the sponsorship sections when the form is opened
 * via the sponsor link, and render the sponsor-side helper UI.
 */
add_filter('gform_pre_render_' . CISON_FELLOWSHIP_FORM_ID, 'cison_fellowship_lock_fields_for_sponsor');
function cison_fellowship_lock_fields_for_sponsor($form)
{
    if (!cison_fellowship_is_sponsor_view()) {
        return $form;
    }

    if (empty($form['fields'])) {
        return $form;
    }

    $sponsor_ids = cison_fellowship_sponsor_field_ids();
    $sponsor_ids = array_map('intval', $sponsor_ids);

    foreach ($form['fields'] as $field) {
        $field_id = (int) $field->id;

        if (in_array($field_id, $sponsor_ids, true)) {
            continue;
        }

        // Non-sponsor fields: remove required validation and mark them as locked.
        $field->isRequired = false;
        $field->allowsPrepopulate = true;
        $field->inputName = 'cison_locked_aspirant_' . $field_id;
        $field->cssClass = trim($field->cssClass . ' cison-fellowship-locked');
    }

    return $form;
}

/**
 * Enqueue the frontend behaviour for the fellowship form:
 *  - Locks (readonly/disabled) non-sponsor fields when in sponsor view.
 *  - Enables Sponsor 2 only after Sponsor 1 is fully completed.
 */
add_action('wp_enqueue_scripts', 'cison_fellowship_enqueue_assets');
function cison_fellowship_enqueue_assets()
{
    if (!is_admin() && has_shortcode(get_post()->post_content, 'gravityform')) {
        wp_add_inline_script('jquery', cison_fellowship_frontend_script());
    }
}

function cison_fellowship_frontend_script()
{
    $form_id = CISON_FELLOWSHIP_FORM_ID;
    $sponsor_ids_json = wp_json_encode(cison_fellowship_sponsor_field_ids());
    $sponsor_1_json = wp_json_encode(array(39, 40, 41, 42, 48));

    return <<<JS
jQuery(function($) {
    var FORM_ID = {$form_id};
    var SPONSOR_FIELDS = {$sponsor_ids_json};
    var SPONSOR_1 = {$sponsor_1_json};
    var isSponsorView = new URLSearchParams(window.location.search).get('cison_sponsor') === '1';

    function getFieldId($field) {
        var id = $field.attr('id') || '';
        var m = id.match(/^field_(\\d+)/);
        return m ? parseInt(m[1], 10) : -1;
    }

    // Hidden field that drives the sponsor-section conditional logic.
    // Value 1 shows Sponsor 1; value 2 also shows Sponsor 2.
    var $sponsorCount = $('#input_' + FORM_ID + '_36');

    function setSponsorCount(value) {
        if ($sponsorCount.length) {
            $sponsorCount.val(value)
                .trigger('input')
                .trigger('keyup')
                .trigger('change');
        }
    }

    // A sponsor field is considered complete when all its enabled inputs
    // (or their checkboxes/radios) have a value.
    function fieldIsFilled(id) {
        var $field = $('#field_' + id);
        var filled = true;
        $field.find('input, select, textarea').each(function() {
            var $el = $(this);
            if ($el.is(':disabled')) { return; }
            if ($el.prop('type') === 'checkbox' || $el.prop('type') === 'radio') {
                if ($el.prop('checked')) { return; }
            }
            var val = $el.val();
            if (!val || !String(val).trim()) { filled = false; }
        });
        return filled;
    }

    function sponsor1Complete() {
        return SPONSOR_1.every(fieldIsFilled);
    }

    function refreshSponsorCount() {
        // Sponsor 2 appears only after Sponsor 1 is fully completed.
        setSponsorCount(sponsor1Complete() ? 2 : 1);
    }

    // 1. Lock every field except the sponsorship sections when opened via the
    //    sponsor link.
    if (isSponsorView) {
        $('.gfield').each(function() {
            var id = getFieldId($(this));
            if (SPONSOR_FIELDS.indexOf(id) !== -1) {
                return;
            }
            $(this).find('input, select, textarea, button').each(function() {
                var tag = $(this).prop('tagName').toLowerCase();
                if (tag === 'select' || tag === 'button' || $(this).hasClass('gfield-choice-input')) {
                    $(this).prop('disabled', true).attr('disabled', 'disabled');
                } else {
                    $(this).prop('readonly', true).attr('readonly', 'readonly');
                }
            });
        });
    }

    // 2. On load, reveal Sponsor 1 (number of sponsors = 1). Sponsor 2 appears
    //    (auto-increments to 2) once Sponsor 1 is fully completed.
    refreshSponsorCount();
    $(document).on('change input', '.gfield', refreshSponsorCount);
});
JS;
}
