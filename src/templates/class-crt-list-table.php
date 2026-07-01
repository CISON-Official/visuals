<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the NSA registrations table with pagination, sorting, search
 * and per-row edit/delete actions.
 */
class CRT_List_Table extends WP_List_Table
{
    const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'registration',
            'plural'   => 'registrations',
            'ajax'     => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'cb'               => '<input type="checkbox" />',
            'id'               => __('ID', 'conference-registration-table'),
            'member_id'        => __('Member ID', 'conference-registration-table'),
            'first_name'       => __('First Name', 'conference-registration-table'),
            'last_name'        => __('Last Name', 'conference-registration-table'),
            'email'            => __('Email', 'conference-registration-table'),
            'phone'            => __('Phone', 'conference-registration-table'),
            'registering_for'  => __('Registering For', 'conference-registration-table'),
            'payment_status'   => __('Payment Status', 'conference-registration-table'),
            'registration_date' => __('Registered On', 'conference-registration-table'),
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'id'                => array('id', true),
            'member_id'         => array('member_id', false),
            'first_name'        => array('first_name', false),
            'last_name'         => array('last_name', false),
            'email'             => array('email', false),
            'payment_status'    => array('payment_status', false),
            'registration_date' => array('registration_date', false),
        );
    }

    protected function get_bulk_actions()
    {
        return array(
            'bulk-delete' => __('Delete', 'conference-registration-table'),
        );
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="entry[]" value="%d" />', (int) $item['id']);
    }

    protected function column_default($item, $column_name)
    {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    protected function column_first_name($item)
    {
        $edit_url = add_query_arg(
            array(
                'page'   => CRT_Conference_Registration_Table::MENU_SLUG,
                'action' => 'edit',
                'id'     => $item['id'],
            ),
            admin_url('tools.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'crt_delete_entry',
                    'id'     => $item['id'],
                ),
                admin_url('admin-post.php')
            ),
            CRT_Conference_Registration_Table::NONCE_DELETE . $item['id']
        );

        $actions = array(
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'conference-registration-table')),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($delete_url),
                esc_js(__('Are you sure you want to delete this entry? This cannot be undone.', 'conference-registration-table')),
                esc_html__('Delete', 'conference-registration-table')
            ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item['first_name']),
            $this->row_actions($actions)
        );
    }

    protected function column_payment_status($item)
    {
        $status = isset($item['payment_status']) ? $item['payment_status'] : '';
        $colors = array(
            'paid'      => '#46b450',
            'pending'   => '#ffb900',
            'failed'    => '#dc3232',
            'cancelled' => '#dc3232',
        );
        $color = isset($colors[$status]) ? $colors[$status] : '#666';

        return sprintf(
            '<span style="color:%s;font-weight:600;">%s</span>',
            esc_attr($color),
            esc_html(ucfirst($status))
        );
    }

    public function prepare_items()
    {
        global $wpdb;
        $table = CRT_Conference_Registration_Table::table_name();

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Search.
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $where  = '';
        $params = array();

        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where  = "WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR member_id LIKE %s OR phone LIKE %s";
            $params = array($like, $like, $like, $like, $like);
        }

        // Sorting.
        $allowed_orderby = array('id', 'member_id', 'first_name', 'last_name', 'email', 'payment_status', 'registration_date');
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $allowed_orderby, true))
            ? sanitize_key($_REQUEST['orderby'])
            : 'registration_date';
        $order = (isset($_REQUEST['order']) && strtolower($_REQUEST['order']) === 'asc') ? 'ASC' : 'DESC';

        // Pagination.
        $per_page     = self::PER_PAGE;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $count_sql = "SELECT COUNT(id) FROM $table $where";
        $total_items = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : (int) $wpdb->get_var($count_sql);

        $data_sql = "SELECT * FROM $table $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $data_params = array_merge($params, array($per_page, $offset));
        $this->items = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total_items / $per_page),
        ));
    }

    public function no_items()
    {
        esc_html_e('No registrations found.', 'conference-registration-table');
    }
}