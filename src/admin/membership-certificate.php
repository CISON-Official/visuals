<?php
/**
 * CISON — Membership Certificate admin screen.
 *
 * Adds a "Membership Certificate" page under Tools that lists every row in
 * CISON_CERT_TABLE, with view / edit / delete actions.
 *
 * Drop this file into your plugin (e.g. includes/class-cison-admin-certificates.php)
 * and require + instantiate it, e.g. in your main plugin file:
 *
 *     require_once CISON_PLUGIN_DIR . 'includes/class-cison-admin-certificates.php';
 *     add_action( 'plugins_loaded', function () {
 *         if ( is_admin() ) {
 *             new CISON_Admin_Certificates();
 *         }
 *     } );
 *
 * Assumes the constant CISON_CERT_TABLE (full table name, incl. $wpdb->prefix)
 * is already defined by the rest of the plugin, as in the activation code you
 * shared.
 *
 * @package CISON
 */

if (!defined('ABSPATH')) {
    exit; // No direct access.
}

/* ---------------------------------------------------------------------
 * List table
 * ------------------------------------------------------------------- */

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CISON_Certificates_List_Table extends WP_List_Table
{

    /** @var string */
    protected $table_name;

    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => 'certificate',
                'plural' => 'certificates',
                'ajax' => false,
            )
        );
        $this->table_name = CISON_CERT_TABLE;
    }

    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', 'cison'),
            'member_id' => __('Member ID', 'cison'),
            'cert_id' => __('Cert ID', 'cison'),
            'email' => __('Email', 'cison'),
            'member_type' => __('Member Type', 'cison'),
            'cutoff_date' => __('Cutoff Date', 'cison'),
            'date_issued' => __('Date Issued', 'cison'),
            'last_updated' => __('Last Updated', 'cison'),
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'member_id' => array('member_id', false),
            'cert_id' => array('cert_id', false),
            'date_issued' => array('date_issued', false),
            'last_updated' => array('last_updated', false),
        );
    }

    protected function get_bulk_actions()
    {
        return array(
            'bulk-delete' => __('Delete', 'cison'),
        );
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="user_ids[]" value="%d" />', (int) $item['user_id']);
    }

    protected function column_name($item)
    {
        $full_name = trim($item['firstname'] . ' ' . $item['middlename'] . ' ' . $item['surname']);
        $full_name = $full_name ? $full_name : '(no name on file)';

        $view_url = $this->row_url('view', $item['user_id']);
        $edit_url = $this->row_url('edit', $item['user_id']);
        $delete_url = $this->row_url('delete', $item['user_id'], true);

        $actions = array(
            'view' => sprintf('<a href="%s">%s</a>', esc_url($view_url), esc_html__('View', 'cison')),
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'cison')),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
                esc_url($delete_url),
                "'" . esc_js(__('Delete this certificate record? This cannot be undone.', 'cison')) . "'",
                esc_html__('Delete', 'cison')
            ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($view_url),
            esc_html($full_name),
            $this->row_actions($actions)
        );
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'member_id':
            case 'cert_id':
            case 'email':
                return esc_html($item[$column_name]);

            case 'member_type':
                return $item['member_type'] ? esc_html(ucfirst($item['member_type'])) : '&#8212;';

            case 'cutoff_date':
                return $item['cutoff_date'] ? esc_html($item['cutoff_date']) : '&#8212;';

            case 'date_issued':
                return $item['date_issued'] ? esc_html(date_i18n('Y-m-d H:i', (int) $item['date_issued'])) : '&#8212;';

            case 'last_updated':
                return $item['last_updated'] ? esc_html(date_i18n('Y-m-d H:i', (int) $item['last_updated'])) : '&#8212;';

            default:
                return '';
        }
    }

    private function row_url($action, $user_id, $with_nonce = false)
    {
        $args = array(
            'page' => 'cison-certificates',
            'action' => $action,
            'user_id' => (int) $user_id,
        );
        $url = add_query_arg($args, admin_url('tools.php'));
        if ($with_nonce) {
            $url = wp_nonce_url($url, 'cison_delete_cert_' . (int) $user_id);
        }
        return $url;
    }

    public function prepare_items()
    {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $where = '';
        $params = array();
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = 'WHERE member_id LIKE %s OR cert_id LIKE %s OR email LIKE %s
			            OR firstname LIKE %s OR surname LIKE %s';
            $params = array($like, $like, $like, $like, $like);
        }

        $orderby_allowed = array('member_id', 'cert_id', 'date_issued', 'last_updated');
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $orderby_allowed, true))
            ? sanitize_key($_REQUEST['orderby'])
            : 'last_updated';
        $order = (isset($_REQUEST['order']) && strtolower($_REQUEST['order']) === 'asc') ? 'ASC' : 'DESC';

        $total_sql = "SELECT COUNT(*) FROM {$this->table_name} {$where}";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($total_sql, $params))
            : (int) $wpdb->get_var($total_sql);

        $offset = ($current_page - 1) * $per_page;

        $data_sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $data_params = array_merge($params, array($per_page, $offset));
        $items = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);

        $this->items = $items ? $items : array();

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => (int) ceil($total_items / $per_page),
            )
        );
    }
}

/* ---------------------------------------------------------------------
 * Admin page controller
 * ------------------------------------------------------------------- */

class CISON_Admin_Certificates
{

    const PAGE_SLUG = 'cison-certificates';
    const CAP = 'manage_options';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu()
    {
        add_submenu_page(
            'tools.php',
            __('Membership Certificate', 'cison'),
            __('Membership Certificate', 'cison'),
            self::CAP,
            self::PAGE_SLUG,
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cison'));
        }

        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : 'list';
        $user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;

        switch ($action) {
            case 'delete':
                $this->handle_delete($user_id);
                break;

            case 'bulk-delete':
                $this->handle_bulk_delete();
                break;

            case 'edit':
                $this->handle_edit_save($user_id);
                $this->render_edit_screen($user_id);
                return;

            case 'view':
                $this->render_view_screen($user_id);
                return;

            case 'list':
            default:
                $this->render_list_screen();
                return;
        }
    }

    /* ---------------- list ---------------- */

    private function render_list_screen()
    {
        $list_table = new CISON_Certificates_List_Table();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Membership Certificates', 'cison') . '</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['cison_notice'])) {
            $this->print_notice(sanitize_key(wp_unslash($_GET['cison_notice'])));
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        $list_table->search_box(__('Search certificates', 'cison'), 'cison-cert-search');
        echo '<form method="post">';
        wp_nonce_field('cison_bulk_delete_certs');
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        echo '<input type="hidden" name="action" value="bulk-delete" />';
        $list_table->display();
        echo '</form>';
        echo '</form>';
        echo '</div>';
    }

    private function print_notice($key)
    {
        $messages = array(
            'deleted' => array('success', __('Certificate record deleted.', 'cison')),
            'bulk-deleted' => array('success', __('Selected certificate records deleted.', 'cison')),
            'saved' => array('success', __('Certificate record updated.', 'cison')),
            'not-found' => array('error', __('Certificate record not found.', 'cison')),
            'error' => array('error', __('Something went wrong. Please try again.', 'cison')),
        );
        if (!isset($messages[$key])) {
            return;
        }
        list($type, $text) = $messages[$key];
        printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($type), esc_html($text));
    }

    private function redirect_to_list($notice = '')
    {
        $url = admin_url('tools.php?page=' . self::PAGE_SLUG);
        if ($notice) {
            $url = add_query_arg('cison_notice', $notice, $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    /* ---------------- delete ---------------- */

    private function handle_delete($user_id)
    {
        if (!$user_id) {
            $this->redirect_to_list('not-found');
        }
        check_admin_referer('cison_delete_cert_' . $user_id);

        global $wpdb;
        $deleted = $wpdb->delete(CISON_CERT_TABLE, array('user_id' => $user_id), array('%d'));

        $this->redirect_to_list($deleted ? 'deleted' : 'error');
    }

    private function handle_bulk_delete()
    {
        if (empty($_POST['user_ids']) || !is_array($_POST['user_ids'])) {
            $this->redirect_to_list();
        }
        check_admin_referer('cison_bulk_delete_certs');

        global $wpdb;
        $user_ids = array_map('absint', wp_unslash($_POST['user_ids']));
        $user_ids = array_filter($user_ids);

        if ($user_ids) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM " . CISON_CERT_TABLE . " WHERE user_id IN ({$placeholders})",
                    $user_ids
                )
            );
        }

        $this->redirect_to_list('bulk-deleted');
    }

    /* ---------------- view ---------------- */

    private function render_view_screen($user_id)
    {
        global $wpdb;

        $row = $user_id ? $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . CISON_CERT_TABLE . ' WHERE user_id = %d', $user_id),
            ARRAY_A
        ) : null;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('View Certificate', 'cison') . '</h1>';

        if (!$row) {
            echo '<p>' . esc_html__('Record not found.', 'cison') . '</p>';
            $this->print_back_link();
            echo '</div>';
            return;
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->view_row(__('User ID', 'cison'), $row['user_id']);
        $this->view_row(__('Member ID', 'cison'), $row['member_id']);
        $this->view_row(__('Certificate ID', 'cison'), $row['cert_id']);
        $this->view_row(__('First name', 'cison'), $row['firstname']);
        $this->view_row(__('Middle name', 'cison'), $row['middlename']);
        $this->view_row(__('Surname', 'cison'), $row['surname']);
        $this->view_row(__('Email', 'cison'), $row['email']);
        $this->view_row(__('Member type', 'cison'), $row['member_type'] ? ucfirst($row['member_type']) : '—');
        $this->view_row(__('Cutoff date', 'cison'), $row['cutoff_date'] ? $row['cutoff_date'] : '—');
        $this->view_row(__('Date issued', 'cison'), $row['date_issued'] ? date_i18n('Y-m-d H:i', (int) $row['date_issued']) : '—');
        $this->view_row(__('Last updated', 'cison'), $row['last_updated'] ? date_i18n('Y-m-d H:i', (int) $row['last_updated']) : '—');
        $this->view_row(__('Secret token', 'cison'), $row['secret_token']);

        echo '<tr><th scope="row">' . esc_html__('Certificate file', 'cison') . '</th><td>';
        if ($row['certificate_path']) {
            echo esc_html($row['certificate_path']);
            $maybe_url = esc_url($row['certificate_path']);
            if ($maybe_url) {
                echo ' &mdash; <a href="' . $maybe_url . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open', 'cison') . '</a>';
            }
        } else {
            echo '&#8212;';
        }
        echo '</td></tr>';
        echo '</tbody></table>';

        $edit_url = add_query_arg(
            array('page' => self::PAGE_SLUG, 'action' => 'edit', 'user_id' => $user_id),
            admin_url('tools.php')
        );
        echo '<p><a href="' . esc_url($edit_url) . '" class="button button-primary">' . esc_html__('Edit', 'cison') . '</a> ';
        $this->print_back_link(true);
        echo '</p>';

        echo '</div>';
    }

    private function view_row($label, $value)
    {
        printf(
            '<tr><th scope="row">%s</th><td>%s</td></tr>',
            esc_html($label),
            esc_html((string) $value)
        );
    }

    private function print_back_link($inline = false)
    {
        $list_url = admin_url('tools.php?page=' . self::PAGE_SLUG);
        if ($inline) {
            echo '<a href="' . esc_url($list_url) . '" class="button">' . esc_html__('Back to list', 'cison') . '</a>';
        } else {
            echo '<p><a href="' . esc_url($list_url) . '">&larr; ' . esc_html__('Back to list', 'cison') . '</a></p>';
        }
    }

    /* ---------------- edit ---------------- */

    private function handle_edit_save($user_id)
    {
        if (!$user_id || empty($_POST['cison_save_cert'])) {
            return;
        }
        check_admin_referer('cison_edit_cert_' . $user_id);

        global $wpdb;

        $member_type = isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : '';
        if (!in_array($member_type, array('transiting', 'inducted'), true)) {
            $member_type = null;
        }

        $cutoff_date = isset($_POST['cutoff_date']) ? sanitize_text_field(wp_unslash($_POST['cutoff_date'])) : '';
        if ($cutoff_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff_date)) {
            $cutoff_date = null;
        }
        $cutoff_date = $cutoff_date ? $cutoff_date : null;

        $data = array(
            'member_id' => sanitize_text_field(wp_unslash($_POST['member_id'] ?? '')),
            'cert_id' => sanitize_text_field(wp_unslash($_POST['cert_id'] ?? '')),
            'certificate_path' => sanitize_text_field(wp_unslash($_POST['certificate_path'] ?? '')),
            'firstname' => sanitize_text_field(wp_unslash($_POST['firstname'] ?? '')),
            'middlename' => sanitize_text_field(wp_unslash($_POST['middlename'] ?? '')),
            'surname' => sanitize_text_field(wp_unslash($_POST['surname'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'member_type' => $member_type,
            'cutoff_date' => $cutoff_date,
            'last_updated' => time(),
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d');

        $updated = $wpdb->update(CISON_CERT_TABLE, $data, array('user_id' => $user_id), $formats, array('%d'));

        if (false === $updated) {
            $this->redirect_to_list('error');
        }

        wp_safe_redirect(
            add_query_arg(
                array('page' => self::PAGE_SLUG, 'action' => 'view', 'user_id' => $user_id, 'cison_notice' => 'saved'),
                admin_url('tools.php')
            )
        );
        exit;
    }

    private function render_edit_screen($user_id)
    {
        global $wpdb;

        $row = $user_id ? $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . CISON_CERT_TABLE . ' WHERE user_id = %d', $user_id),
            ARRAY_A
        ) : null;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Edit Certificate', 'cison') . '</h1>';

        if (!$row) {
            echo '<p>' . esc_html__('Record not found.', 'cison') . '</p>';
            $this->print_back_link();
            echo '</div>';
            return;
        }

        echo '<form method="post">';
        wp_nonce_field('cison_edit_cert_' . $user_id);
        echo '<input type="hidden" name="cison_save_cert" value="1" />';

        echo '<table class="form-table" role="presentation"><tbody>';

        $this->text_row('user_id', __('User ID', 'cison'), $row['user_id'], true);
        $this->text_row('member_id', __('Member ID', 'cison'), $row['member_id']);
        $this->text_row('cert_id', __('Certificate ID', 'cison'), $row['cert_id']);
        $this->text_row('certificate_path', __('Certificate file path/URL', 'cison'), $row['certificate_path']);
        $this->text_row('firstname', __('First name', 'cison'), $row['firstname']);
        $this->text_row('middlename', __('Middle name', 'cison'), $row['middlename']);
        $this->text_row('surname', __('Surname', 'cison'), $row['surname']);
        $this->text_row('email', __('Email', 'cison'), $row['email'], false, 'email');

        echo '<tr><th scope="row"><label for="member_type">' . esc_html__('Member type', 'cison') . '</label></th><td>';
        echo '<select name="member_type" id="member_type">';
        $options = array('' => '&#8212;', 'transiting' => __('Transiting', 'cison'), 'inducted' => __('Inducted', 'cison'));
        foreach ($options as $val => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($val),
                selected($row['member_type'], $val, false),
                esc_html($label)
            );
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="cutoff_date">' . esc_html__('Cutoff date', 'cison') . '</label></th><td>';
        printf(
            '<input type="date" name="cutoff_date" id="cutoff_date" value="%s" class="regular-text" />',
            esc_attr($row['cutoff_date'])
        );
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Date issued', 'cison') . '</th><td>';
        echo esc_html($row['date_issued'] ? date_i18n('Y-m-d H:i', (int) $row['date_issued']) : '—');
        echo ' <span class="description">(' . esc_html__('not editable here', 'cison') . ')</span></td></tr>';

        echo '</tbody></table>';

        submit_button(__('Save Changes', 'cison'));
        echo '</form>';

        $this->print_back_link();
        echo '</div>';
    }

    private function text_row($name, $label, $value, $readonly = false, $type = 'text')
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        if ($readonly) {
            printf(
                '<input type="text" id="%1$s" value="%2$s" class="regular-text" readonly />',
                esc_attr($name),
                esc_attr($value)
            );
        } else {
            printf(
                '<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" class="regular-text" />',
                esc_attr($type),
                esc_attr($name),
                esc_attr($value)
            );
        }
        echo '</td></tr>';
    }
}


new CISON_Admin_Certificates();