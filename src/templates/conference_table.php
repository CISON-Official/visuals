<?php

/**
 * Main plugin controller.
 */
class CRT_Conference_Registration_Table
{
    const CAPABILITY = 'manage_options';
    const MENU_SLUG = 'conference-registration-table';
    const NONCE_EDIT = 'crt_edit_entry';
    const NONCE_DELETE = 'crt_delete_entry';
    const NONCE_BULK = 'crt_bulk_action';

    /** @var array<string,string> Field label map for the editable form + list columns. */
    public static $fields = array(
        'member_id' => 'Member ID',
        'registering_for' => 'Registering For',
        'title' => 'Title',
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'occupation' => 'Occupation',
        'organisation' => 'Organisation',
        'street' => 'Street',
        'city' => 'City',
        'state' => 'State',
        'postcode' => 'Postcode',
        'country' => 'Country',
        'gender' => 'Gender',
        'hear_about' => 'How They Heard',
        'payment_status' => 'Payment Status',
        'who_paid' => 'Who Paid',
    );

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_crt_update_entry', array($this, 'handle_update_entry'));
        add_action('admin_post_crt_delete_entry', array($this, 'handle_delete_entry'));
        add_action('admin_post_crt_bulk_delete', array($this, 'handle_bulk_delete'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('wp_ajax_crt_check_woo_purchase', array($this, 'ajax_check_woo_purchase'));
    }

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'nsa_registrations';
    }

    public function register_menu()
    {
        add_management_page(
            'Conference Registrations',
            'Conference Registrations',
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_page')
        );
    }

    /**
     * Router: decides whether to show the list table or the edit form.
     */
    public function render_page()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Conference Registrations', 'conference-registration-table') . '</h1>';

        if ($action === 'edit' && isset($_GET['id'])) {
            $this->render_edit_form((int) $_GET['id']);
        } else {
            $this->render_list_table();
        }

        echo '</div>';
    }

    /**
     * Renders the paginated, sortable, searchable list table.
     */
    private function render_list_table()
    {
        require_once dirname(__FILE__) . '/class-crt-list-table.php';

        $list_table = new CRT_List_Table();
        $list_table->prepare_items();
        ?>
        <form method="post">
            <?php wp_nonce_field(self::NONCE_BULK, '_crt_bulk_nonce'); ?>
            <input type="hidden" name="action" value="crt_bulk_delete" />
            <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>" />
            <?php
            $list_table->search_box('Search entries', 'crt-search');
            $list_table->display();
            ?>
        </form>
        <?php
    }

    /**
     * Renders the edit form for a single entry.
     */
    private function render_edit_form($id)
    {
        global $wpdb;
        $table = self::table_name();

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);

        if (!$entry) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Entry not found.', 'conference-registration-table') . '</p></div>';
            $this->render_back_link();
            return;
        }
        $woo_active = class_exists('WooCommerce') && function_exists('wc_get_orders');
        ?>
        <p><?php $this->render_back_link(); ?></p>

        <?php if ($woo_active): ?>
            <script>
                var CRT_Data = <?php echo wp_json_encode(array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('crt_woo_check'),
                )); ?>;
            </script>
            <div class="notice notice-info" style="padding:12px;">
                <p style="margin-top:0;">
                    <button type="button" class="button button-secondary" id="crt-check-woo-purchase">
                        <?php esc_html_e('Check WooCommerce Purchase', 'conference-registration-table'); ?>
                    </button>
                    <span id="crt-woo-check-spinner" class="spinner" style="float:none;vertical-align:middle;"></span>
                </p>
                <div id="crt-woo-check-result"></div>
            </div>
            <script>
                (function () {
                    var btn = document.getElementById('crt-check-woo-purchase');
                    if (!btn) { return; }

                    btn.addEventListener('click', function () {
                        var spinner = document.getElementById('crt-woo-check-spinner');
                        var resultBox = document.getElementById('crt-woo-check-result');
                        var emailField = document.getElementById('crt-email');
                        var registeringForField = document.getElementById('crt-registering_for');

                        var email = emailField ? emailField.value.trim() : '';
                        var registeringFor = registeringForField ? registeringForField.value.trim() : '';

                        if (!email) {
                            resultBox.innerHTML = '<p style="color:#dc3232;"><?php echo esc_js(__('Enter an email address first.', 'conference-registration-table')); ?></p>';
                            return;
                        }

                        btn.disabled = true;
                        spinner.classList.add('is-active');
                        resultBox.innerHTML = '';

                        var formData = new FormData();
                        formData.append('action', 'crt_check_woo_purchase');
                        formData.append('nonce', CRT_Data.nonce);
                        formData.append('email', email);
                        formData.append('registering_for', registeringFor);

                        fetch(CRT_Data.ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                spinner.classList.remove('is-active');
                                btn.disabled = false;

                                if (!res.success) {
                                    resultBox.innerHTML = '<p style="color:#dc3232;">' + res.data.message + '</p>';
                                    return;
                                }

                                if (!res.data.found) {
                                    resultBox.innerHTML = '<p style="color:#dc3232;"><?php echo esc_js(__('No matching WooCommerce purchase found for this email.', 'conference-registration-table')); ?></p>';
                                    return;
                                }

                                var html = '<p style="color:#46b450;font-weight:600;"><?php echo esc_js(__('Purchase found:', 'conference-registration-table')); ?></p><ul style="list-style:disc;margin-left:20px;">';
                                res.data.matches.forEach(function (m) {
                                    html += '<li>' +
                                        '<?php echo esc_js(__('Order', 'conference-registration-table')); ?> #' + m.order_id +
                                        ' (' + m.order_status + ') &mdash; ' + m.product_name +
                                        ' &mdash; ' + m.order_date +
                                        ' &mdash; <a href="' + m.edit_link + '" target="_blank" rel="noopener">' +
                                        '<?php echo esc_js(__('View order', 'conference-registration-table')); ?></a>' +
                                        '</li>';
                                });
                                html += '</ul>';
                                resultBox.innerHTML = html;
                            })
                            .catch(function () {
                                spinner.classList.remove('is-active');
                                btn.disabled = false;
                                resultBox.innerHTML = '<p style="color:#dc3232;"><?php echo esc_js(__('Request failed. Please try again.', 'conference-registration-table')); ?></p>';
                            });
                    });
                })();
            </script>
        <?php else: ?>
            <div class="notice notice-warning" style="padding:12px;">
                <p style="margin:0;">
                    <?php esc_html_e('WooCommerce is not active, so purchase verification is unavailable.', 'conference-registration-table'); ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="crt_update_entry" />
            <input type="hidden" name="id" value="<?php echo esc_attr($entry['id']); ?>" />
            <?php wp_nonce_field(self::NONCE_EDIT . $entry['id'], '_crt_edit_nonce'); ?>

            <table class="form-table" role="presentation">
                <?php foreach (self::$fields as $key => $label): ?>
                    <tr>
                        <th scope="row"><label for="crt-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <?php if ($key === 'payment_status'): ?>
                                <select name="<?php echo esc_attr($key); ?>" id="crt-<?php echo esc_attr($key); ?>"
                                    class="regular-text">
                                    <?php foreach (array('pending', 'paid', 'failed', 'cancelled') as $status): ?>
                                        <option value="<?php echo esc_attr($status); ?>" <?php selected($entry[$key], $status); ?>>
                                            <?php echo esc_html(ucfirst($status)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($key === 'gender'): ?>
                                <select name="<?php echo esc_attr($key); ?>" id="crt-<?php echo esc_attr($key); ?>"
                                    class="regular-text">
                                    <?php foreach (array('Male', 'Female', 'Other', 'Prefer not to say') as $g): ?>
                                        <option value="<?php echo esc_attr($g); ?>" <?php selected($entry[$key], $g); ?>>
                                            <?php echo esc_html($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="regular-text" id="crt-<?php echo esc_attr($key); ?>"
                                    name="<?php echo esc_attr($key); ?>"
                                    value="<?php echo esc_attr(isset($entry[$key]) ? $entry[$key] : ''); ?>" />
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <th scope="row"><?php esc_html_e('Registration Date', 'conference-registration-table'); ?></th>
                    <td><?php echo esc_html($entry['registration_date']); ?> <span class="description">(read-only)</span></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Order ID', 'conference-registration-table'); ?></th>
                    <td><?php echo esc_html($entry['order_id']); ?> <span class="description">(read-only)</span></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('IP Address', 'conference-registration-table'); ?></th>
                    <td><?php echo esc_html($entry['ip_address']); ?> <span class="description">(read-only)</span></td>
                </tr>
            </table>

            <?php submit_button(__('Save Changes', 'conference-registration-table')); ?>
        </form>
        <?php
    }

    private function render_back_link()
    {
        $url = admin_url('tools.php?page=' . self::MENU_SLUG);
        echo '&larr; <a href="' . esc_url($url) . '">' . esc_html__('Back to all registrations', 'conference-registration-table') . '</a>';
    }

    /**
     * Handles saving edits (admin-post.php?action=crt_update_entry).
     */
    public function handle_update_entry()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to do this.'));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if (!$id || !isset($_POST['_crt_edit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_crt_edit_nonce'])), self::NONCE_EDIT . $id)) {
            wp_die(esc_html__('Security check failed.'));
        }

        global $wpdb;
        $table = self::table_name();

        $data = array();
        $formats = array();

        foreach (self::$fields as $key => $label) {
            if (isset($_POST[$key])) {
                $value = wp_unslash($_POST[$key]);
                $value = ($key === 'email') ? sanitize_email($value) : sanitize_text_field($value);
                $data[$key] = $value;
                $formats[] = '%s';
            }
        }

        if (!empty($data)) {
            $wpdb->update($table, $data, array('id' => $id), $formats, array('%d'));
        }

        $redirect = add_query_arg(
            array('page' => self::MENU_SLUG, 'crt_notice' => 'updated'),
            admin_url('tools.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handles single-row delete (admin-post.php?action=crt_delete_entry).
     */
    public function handle_delete_entry()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to do this.'));
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!$id || !wp_verify_nonce($nonce, self::NONCE_DELETE . $id)) {
            wp_die(esc_html__('Security check failed.'));
        }

        global $wpdb;
        $wpdb->delete(self::table_name(), array('id' => $id), array('%d'));

        $redirect = add_query_arg(
            array('page' => self::MENU_SLUG, 'crt_notice' => 'deleted'),
            admin_url('tools.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handles bulk delete from the list table checkboxes.
     */
    public function handle_bulk_delete()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to do this.'));
        }

        if (
            !isset($_POST['_crt_bulk_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_crt_bulk_nonce'])), self::NONCE_BULK)
        ) {
            wp_die(esc_html__('Security check failed.'));
        }

        $ids = isset($_POST['entry']) ? array_map('intval', (array) $_POST['entry']) : array();

        if (!empty($ids)) {
            global $wpdb;
            $table = self::table_name();
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));
        }

        $redirect = add_query_arg(
            array('page' => self::MENU_SLUG, 'crt_notice' => 'bulk_deleted'),
            admin_url('tools.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Checks WooCommerce for a paid order, placed with the given email,
     * that contains a product matching the "registering for" text.
     *
     * @param string $email           Person's email address.
     * @param string $registering_for What they registered for (matched loosely against product titles).
     * @return array {
     *     @type bool  $found   Whether at least one matching order was found.
     *     @type array $matches List of matching orders (order_id, order_status, order_date, product_name, edit_link).
     * }
     */
    public static function check_woocommerce_purchase($email, $registering_for)
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return array('error' => __('WooCommerce is not active.', 'conference-registration-table'));
        }

        $email = sanitize_email($email);
        $registering_for = sanitize_text_field($registering_for);

        if (!$email || !is_email($email)) {
            return array('error' => __('A valid email address is required.', 'conference-registration-table'));
        }

        // Find products whose title loosely matches the "registering for" value.
        $matching_product_ids = array();
        if ($registering_for !== '') {
            $product_query = new WP_Query(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                's' => $registering_for,
                'fields' => 'ids',
                'posts_per_page' => -1,
                'no_found_rows' => true,
            ));
            $matching_product_ids = $product_query->posts;
        }

        // Only orders that represent a real, confirmed payment.
        $paid_statuses = array('wc-processing', 'wc-completed', 'wc-on-hold');

        $orders = wc_get_orders(array(
            'billing_email' => $email,
            'limit' => -1,
            'status' => $paid_statuses,
        ));

        $matches = array();

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();

                $is_match = false;

                if ($registering_for === '') {
                    // No product filter supplied — any paid order for this email counts.
                    $is_match = true;
                } elseif (!empty($matching_product_ids) && in_array($product_id, $matching_product_ids, true)) {
                    $is_match = true;
                } elseif (stripos($product_name, $registering_for) !== false) {
                    $is_match = true;
                }

                if ($is_match) {
                    $matches[] = array(
                        'order_id' => $order->get_id(),
                        'order_status' => wc_get_order_status_name($order->get_status()),
                        'order_date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                        'product_name' => $product_name,
                        'edit_link' => $order->get_edit_order_url(),
                    );
                }
            }
        }

        return array(
            'found' => !empty($matches),
            'matches' => $matches,
        );
    }

    /**
     * AJAX callback for the "Check WooCommerce Purchase" button.
     */
    public function ajax_check_woo_purchase()
    {
        check_ajax_referer('crt_woo_check', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'conference-registration-table')));
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $registering_for = isset($_POST['registering_for']) ? sanitize_text_field(wp_unslash($_POST['registering_for'])) : '';

        $result = self::check_woocommerce_purchase($email, $registering_for);

        if (isset($result['error'])) {
            wp_send_json_error(array('message' => $result['error']));
        }

        wp_send_json_success($result);
    }

    public function show_admin_notices()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG || !isset($_GET['crt_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['crt_notice']));
        $messages = array(
            'updated' => __('Entry updated successfully.', 'conference-registration-table'),
            'deleted' => __('Entry deleted.', 'conference-registration-table'),
            'bulk_deleted' => __('Selected entries deleted.', 'conference-registration-table'),
        );

        if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
    }
}

new CRT_Conference_Registration_Table();