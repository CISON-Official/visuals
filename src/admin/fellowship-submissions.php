<?php
/**
 * CISON Fellowship Submissions — wp-admin Tools page.
 *
 * Actions:
 *   list   (default) – table of all submissions with search / filter / pagination
 *   view   – single submission detail
 *   create – form to manually add a new submission
 *   delete – delete a submission (with confirmation)
 */

if (!defined('ABSPATH')) {
    exit;
}

class CISON_Fellowship_Submissions_Admin
{
    const PAGE_SLUG = 'cison-fellowship-submissions';
    const CAP       = 'manage_options';
    const PER_PAGE  = 20;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    /* -------------------------------------------------------------- */
    /*  Menu registration                                              */
    /* -------------------------------------------------------------- */

    public function register_menu()
    {
        add_management_page(
            __('Fellowship Submissions', 'cison'),
            __('Fellowship Submissions', 'cison'),
            self::CAP,
            self::PAGE_SLUG,
            array($this, 'render_page')
        );
    }

    /* -------------------------------------------------------------- */
    /*  Page controller                                                */
    /* -------------------------------------------------------------- */

    public function render_page()
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cison'));
        }

        if (isset($_GET['fs_clear_mail_log'])) {
            check_admin_referer('cison_fs_clear_mail_log');
            delete_option('cison_mail_failure_log');
            wp_safe_redirect(admin_url('tools.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : 'list';

        switch ($action) {
            case 'delete':
                $this->handle_delete();
                return;

            case 'create':
                $this->handle_create_save();
                $this->render_create_screen();
                return;

            case 'view':
                $this->handle_update_created_at();
                $this->handle_resend_sponsor_email();
                $this->render_view_screen();
                return;

            case 'edit':
                $this->handle_edit_save();
                $this->render_edit_screen();
                return;

            case 'list':
            default:
                $this->render_list_screen();
                return;
        }
    }

    /* -------------------------------------------------------------- */
    /*  Notices                                                        */
    /* -------------------------------------------------------------- */

    private function print_notice($key)
    {
        $messages = array(
            'created'         => array('success', __('Fellowship submission created.', 'cison')),
            'deleted'         => array('success', __('Fellowship submission deleted.', 'cison')),
            'create-error'    => array('error',   __('Could not create the submission. Please try again.', 'cison')),
            'not-found'       => array('error',   __('Record not found.', 'cison')),
            'date-updated'    => array('success', __('Created at date updated.', 'cison')),
            'date-error'      => array('error',   __('Invalid date format. Use YYYY-MM-DD HH:MM:SS.', 'cison')),
            'saved'           => array('success', __('Submission updated.', 'cison')),
            'save-error'      => array('error',   __('Could not save the changes. Please try again.', 'cison')),
            'email-sent'      => array('success', __('Sponsorship email sent to the applicant.', 'cison')),
            'email-error'     => array('error',   __('Could not send the sponsorship email. Please try again.', 'cison')),
            'email-complete'  => array('error',   __('Sponsorship is already complete. No email sent.', 'cison')),
        );

        if (!isset($messages[$key])) {
            return;
        }

        list($type, $text) = $messages[$key];
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($text)
        );

        if (!empty($_GET['fs_msg'])) {
            $detail = sanitize_text_field(wp_unslash($_GET['fs_msg']));
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong></p>%3$s</div>',
                esc_attr($type),
                esc_html($detail),
                $key === 'email-error' ? '<p style="margin-bottom:0;">' . __('Tip: verify your SMTP / mail server settings, from address, and recipient address.', 'cison') . '</p>' : ''
            );
        }
    }

    private function redirect($action = 'list', $extra = array())
    {
        $params = array_merge(array('page' => self::PAGE_SLUG, 'action' => $action), $extra);
        wp_safe_redirect(add_query_arg($params, admin_url('tools.php')));
        exit;
    }

    /* ==============================================================
     *  LIST
     * ============================================================== */

    private function render_list_screen()
    {
        global $wpdb;
        $table = cison_fellowship_get_table_name();

        $search     = isset($_GET['fs_s']) ? sanitize_text_field(wp_unslash($_GET['fs_s'])) : '';
        $filter_val = isset($_GET['fs_filter']) ? sanitize_text_field(wp_unslash($_GET['fs_filter'])) : '';
        $paged      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        $wheres = array('1=1');
        $params = array();

        if ($search) {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $wheres[] = '(reference_number LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params = array_merge($params, array($like, $like, $like, $like, $like));
        }
        if ($filter_val) {
            $wheres[] = 'payment_status = %s';
            $params[] = $filter_val;
        }

        $where = implode(' AND ', $wheres);

        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where", $params)
                : "SELECT COUNT(*) FROM $table WHERE $where"
        );
        $total_pages = max(1, (int) ceil($total / self::PER_PAGE));
        $offset     = ($paged - 1) * self::PER_PAGE;

        $page_params = array_merge($params, array(self::PER_PAGE, $offset));
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY registration_date DESC LIMIT %d OFFSET %d", $page_params),
            ARRAY_A
        );

        $filter_options = $wpdb->get_col(
            "SELECT DISTINCT payment_status FROM $table WHERE payment_status != '' ORDER BY payment_status ASC"
        );

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Fellowship Submissions', 'cison') . '</h1>';
        echo ' <a href="' . esc_url(admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=create')) . '" class="page-title-action">' . esc_html__('Add New', 'cison') . '</a>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['fs_notice'])) {
            $this->print_notice(sanitize_key(wp_unslash($_GET['fs_notice'])));
        }

        $this->render_mail_failures();

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        echo '<p class="search-box">';
        echo '<input type="search" name="fs_s" value="' . esc_attr($search) . '" placeholder="Search name, email, reference\u2026" />';
        submit_button(__('Search', 'cison'), '', 'searchsubmit', false);
        if ($filter_val) {
            echo ' <a href="' . esc_url(remove_query_arg(array('fs_s', 'fs_filter', 'paged'))) . '" class="button">' . esc_html__('Clear', 'cison') . '</a>';
        }
        echo '</p>';

        echo '<select name="fs_filter" onchange="this.form.submit()">';
        echo '<option value="">' . esc_html__('All Payment Status', 'cison') . '</option>';
        foreach ($filter_options as $opt) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($opt),
                selected($filter_val, $opt, false),
                esc_html($opt)
            );
        }
        echo '</select>';
        echo '</form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:15%;">' . esc_html__('Reference', 'cison') . '</th>';
        echo '<th style="width:18%;">' . esc_html__('Applicant', 'cison') . '</th>';
        echo '<th style="width:15%;">' . esc_html__('Email', 'cison') . '</th>';
        echo '<th style="width:10%;">' . esc_html__('Membership', 'cison') . '</th>';
        echo '<th style="width:12%;">' . esc_html__('Sponsor 1', 'cison') . '</th>';
        echo '<th style="width:12%;">' . esc_html__('Sponsor 2', 'cison') . '</th>';
        echo '<th style="width:10%;">' . esc_html__('Payment', 'cison') . '</th>';
        echo '<th style="width:8%;">' . esc_html__('Submitted', 'cison') . '</th>';
        echo '</tr></thead>';

        echo '<tbody>';
        if ($rows) {
            foreach ($rows as $row) {
                $s1 = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
                $s2 = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();
                $view_url = admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=view&ref=' . rawurlencode($row['reference_number']));
                $edit_url = admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=edit&ref=' . rawurlencode($row['reference_number']));
                $full_name = cison_fellowship_get_full_name($row);
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($view_url); ?>" style="font-weight:700;color:#0f766e;text-decoration:none;"><?php echo esc_html($row['reference_number'] ?: 'N/A'); ?></a>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'cison'); ?></a> | </span>
                            <span class="view"><a href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('View', 'cison'); ?></a></span>
                        </div>
                    </td>
                    <td><strong><?php echo esc_html($full_name); ?></strong><br><small><?php echo esc_html($row['phone'] ?: ''); ?></small></td>
                    <td><?php echo esc_html($row['email']); ?></td>
                    <td><?php echo esc_html($row['is_member'] ?: 'N/A'); ?>
                        <?php if (strtolower($row['is_nsa_fellow'] ?? '') === 'yes'): ?>
                            <br><small>NSA Fellow</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo cison_fellowship_render_status_badge($row['sponsor_1_status'] ?? 'pending'); ?>
                        <?php if (!empty($s1['name'])): ?><br><small><?php echo esc_html($s1['name']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo cison_fellowship_render_status_badge($row['sponsor_2_status'] ?? 'pending'); ?>
                        <?php if (!empty($s2['name'])): ?><br><small><?php echo esc_html($s2['name']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo cison_fellowship_render_status_badge($row['payment_status']); ?></td>
                    <td><?php echo esc_html(date_i18n('M j, Y', strtotime($row['registration_date']))); ?></td>
                </tr>
                <?php
            }
        } else {
            echo '<tr><td colspan="8">' . esc_html__('No fellowship submissions found.', 'cison') . '</td></tr>';
        }
        echo '</tbody></table>';

        if ($total_pages > 1) {
            $args = array(
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'total'   => $total_pages,
                'current' => $paged,
            );
            if ($search) {
                $args['add_args'] = array('fs_s' => $search);
            }
            if ($filter_val) {
                $args['add_args']['fs_filter'] = $filter_val;
            }
            echo '<div class="tablenav bottom"><div class="tablenav-pages">' . paginate_links($args) . '</div></div>';
        }

        echo '</div>';
    }

    /**
     * Display the most recent wp_mail() failures logged by the plugin, so admins
     * can see why fellowship emails are not being delivered.
     */
    private function render_mail_failures()
    {
        $log = get_option('cison_mail_failure_log', array());
        if (empty($log) || !is_array($log)) {
            return;
        }

        echo '<div style="margin-bottom:16px;padding:12px 14px;background:#fff8e5;border:1px solid #f0c33c;border-left-width:4px;border-radius:4px;">';
        echo '<h2 style="font-size:1.1em;margin:0 0 8px;">' . esc_html__('Recent email failures', 'cison') . '</h2>';
        echo '<p style="margin:0 0 8px;color:#555;">' .
            esc_html__('These wp_mail() calls failed recently. Check your mail/SMTP configuration.', 'cison') . '</p>';

        echo '<table class="widefat striped" style="max-width:100%;"><thead><tr>' .
            '<th>' . esc_html__('Time', 'cison') . '</th>' .
            '<th>' . esc_html__('To', 'cison') . '</th>' .
            '<th>' . esc_html__('Subject', 'cison') . '</th>' .
            '<th>' . esc_html__('Error', 'cison') . '</th>' .
            '</tr></thead><tbody>';

        foreach (array_reverse($log) as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry['time'] ?? '') . '</td>';
            echo '<td>' . esc_html($entry['to'] ?? '') . '</td>';
            echo '<td>' . esc_html($entry['subject'] ?? '') . '</td>';
            echo '<td><code>' . esc_html($entry['error'] ?? '') . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin:8px 0 0;"><a class="button button-small" href="' .
            esc_url(wp_nonce_url(add_query_arg('fs_clear_mail_log', '1'), 'cison_fs_clear_mail_log')) . '">' .
            esc_html__('Clear log', 'cison') . '</a></p>';
        echo '</div>';
    }

    /* ==============================================================
     *  VIEW
     * ============================================================== */

    private function handle_update_created_at()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cison_fs_update_created'])) {
            return;
        }

        $ref = isset($_POST['fs_ref']) ? sanitize_text_field(wp_unslash($_POST['fs_ref'])) : '';
        if (!$ref) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'cison_fs_update_created_' . md5($ref))) {
            return;
        }

        $datetime = isset($_POST['cison_fs_created_at']) ? sanitize_text_field(wp_unslash($_POST['cison_fs_created_at'])) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $datetime)) {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'date-error'));
        }

        global $wpdb;
        $table = cison_fellowship_get_table_name();

        $updated = $wpdb->update(
            $table,
            array('registration_date' => $datetime),
            array('reference_number' => $ref),
            array('%s'),
            array('%s')
        );

        $this->redirect('view', array('ref' => $ref, 'fs_notice' => false === $updated ? 'date-error' : 'date-updated'));
    }

    private function handle_resend_sponsor_email()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cison_fs_resend_email'])) {
            return;
        }

        $ref = isset($_POST['fs_ref']) ? sanitize_text_field(wp_unslash($_POST['fs_ref'])) : '';
        if (!$ref) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'cison_fs_resend_email_' . md5($ref))) {
            return;
        }

        if (!function_exists('cison_fellowship_send_applicant_email')) {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'email-error'));
        }

        $row = $this->get_row($ref);
        if (!$row) {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'not-found'));
        }

        // Sponsorship is complete once Sponsor 2 has submitted; nothing to resend.
        if (($row['sponsor_2_status'] ?? '') === 'submitted') {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'email-complete'));
        }

        $token = $row['sponsor_token'] ?? '';
        if (empty($token)) {
            // Custom/legacy submissions may lack a token; generate and store one so the sponsor link works.
            global $wpdb;
            $token = cison_fellowship_generate_token();
            $wpdb->update(
                cison_fellowship_get_table_name(),
                array('sponsor_token' => $token, 'updated_at' => current_time('mysql')),
                array('reference_number' => $ref)
            );
            $row['sponsor_token'] = $token;
        }

        if (empty($row['email']) || !is_email($row['email'])) {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'email-error', 'fs_msg' => __('The applicant email address is invalid or empty:', 'cison') . ' ' . ($row['email'] ?? 'empty')));
        }

        // Capture the underlying mailer error (e.g. SMTP details) from wp_mail_failed.
        $mail_error = '';
        $has_mail_error = false;
        add_action('wp_mail_failed', function ($wp_error) use (&$mail_error, &$has_mail_error) {
            if (is_wp_error($wp_error)) {
                $has_mail_error = true;
                $mail_error   = $wp_error->get_error_message();
                $data = $wp_error->get_error_data();
                if (is_array($data) && !empty($data['phpmailer_exception']) && method_exists($data['phpmailer_exception'], 'getMessage')) {
                    $mail_error = $data['phpmailer_exception']->getMessage();
                }
            }
        });

        $sent = cison_fellowship_send_applicant_email($row, $token);

        if ($sent) {
            $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'email-sent'));
        }

        $fallback = ($has_mail_error && $mail_error !== '')
            ? $mail_error
            : __('wp_mail() returned false without a detailed error. Check your site mail settings (SMTP provider, credentials, port/encryption, and from address) and that the hosting server allows outgoing connections on the mail port.', 'cison');

        $this->redirect('view', array('ref' => $ref, 'fs_notice' => 'email-error', 'fs_msg' => $fallback));
    }

    private function render_view_screen()
    {
        global $wpdb;
        $table = cison_fellowship_get_table_name();
        $ref   = isset($_GET['ref']) ? sanitize_text_field(wp_unslash($_GET['ref'])) : '';

        $row = $ref
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE reference_number = %s LIMIT 1", $ref), ARRAY_A)
            : null;

        $back_url = admin_url('tools.php?page=' . self::PAGE_SLUG);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Fellowship Submission Detail', 'cison') . '</h1>';

        if (!$row) {
            echo '<p>' . esc_html__('Record not found.', 'cison') . '</p>';
            echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('Back to list', 'cison') . '</a></p>';
            echo '</div>';
            return;
        }

        if (isset($_GET['fs_notice'])) {
            $this->print_notice(sanitize_key(wp_unslash($_GET['fs_notice'])));
        }

        $s1_data  = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
        $s2_data  = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();
        $quals    = !empty($row['academic_qualifications']) ? explode("\n", $row['academic_qualifications']) : array();
        $del_url  = wp_nonce_url(
            admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=delete&ref=' . rawurlencode($ref)),
            'cison_delete_fs_' . md5($ref)
        );

        echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('Back to list', 'cison') . '</a> &nbsp; ';
        $edit_url = admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=edit&ref=' . rawurlencode($ref));
        echo '<a href="' . esc_url($edit_url) . '" class="button button-primary">' . esc_html__('Edit', 'cison') . '</a> ';
        echo '<a href="' . esc_url($del_url) . '" class="button" onclick="return confirm(\'' . esc_attr__('Are you sure you want to delete this submission? This cannot be undone.', 'cison') . '\')">' . esc_html__('Delete', 'cison') . '</a></p>';

        // -- Cards output using the plugin's existing style classes --
        echo '<div class="cison-fs-detail" style="margin:20px 0;">';
        echo '<div class="cison-fs-detail__grid">';

        // Personal Information
        $this->detail_card(__('Personal Information', 'cison'), array(
            __('Full Name', 'cison')            => cison_fellowship_get_full_name($row),
            __('Title', 'cison')                => $row['title'] ?: 'N/A',
            __('Email', 'cison')               => $row['email'],
            __('Phone', 'cison')               => $row['phone'] ?: 'N/A',
            __('Gender', 'cison')              => $row['gender'] ?: 'N/A',
            __('Date of Birth', 'cison')       => $row['date_of_birth'] ? date_i18n('M j, Y', strtotime($row['date_of_birth'])) : 'N/A',
            __('Nationality', 'cison')         => $row['nationality'] ?: 'N/A',
        ));

        // Residential Address
        $this->detail_card(__('Residential Address', 'cison'), array(
            __('Street', 'cison')  => $row['street'] ?: 'N/A',
            __('City', 'cison')   => $row['city'] ?: 'N/A',
            __('State', 'cison')  => $row['state'] ?: 'N/A',
            __('Country', 'cison') => $row['country'] ?: 'N/A',
        ));

        // Professional Information
        $this->detail_card(__('Professional Information', 'cison'), array(
            __('Occupation', 'cison')             => $row['occupation'] ?: 'N/A',
            __('Designation', 'cison')           => $row['designation'] ?: 'N/A',
            __('Employer / Institution', 'cison') => $row['employer'] ?: 'N/A',
            __('Years of Practice', 'cison')     => $row['years_of_practice'] ?: 'N/A',
            __('Area of Statistics', 'cison')    => $row['area_of_practice'] ?: 'N/A',
        ));

        // Membership Details
        $this->detail_card(__('Membership Details', 'cison'), array(
            __('Status', 'cison')         => $row['is_member'] ?: 'N/A',
            __('Category', 'cison')       => $row['membership_category'] ?: 'N/A',
            __('Member Number', 'cison')  => $row['membership_number'] ?: 'N/A',
            __('NSA Fellow', 'cison')     => ($row['is_nsa_fellow'] ?: 'N/A'),
            __('NSA Fellow ID', 'cison')  => $row['nsa_fellow_id'] ?: '',
        ));

        // Sponsor 1
        $s1_fields = !empty($s1_data) ? array(
            __('Full Name', 'cison')          => $s1_data['name'] ?? 'N/A',
            __('Membership ID', 'cison')      => $s1_data['membership_id'] ?? 'N/A',
            __('Membership Status', 'cison')  => $s1_data['membership_status'] ?? 'N/A',
            __('Rank', 'cison')               => $s1_data['rank'] ?? 'N/A',
            __('Date', 'cison')               => $s1_data['date'] ?? 'N/A',
            __('Signature', 'cison')          => !empty($s1_data['signature']) ? $s1_data['signature'] : 'N/A',
        ) : array();
        $this->detail_card(
            __('Sponsor 1', 'cison') . ' &mdash; ' . cison_fellowship_render_status_badge($row['sponsor_1_status'] ?? 'pending'),
            $s1_fields,
            empty($s1_data)
        );

        // Sponsor 2
        $s2_fields = !empty($s2_data) ? array(
            __('Full Name', 'cison')          => $s2_data['name'] ?? 'N/A',
            __('Membership ID', 'cison')      => $s2_data['membership_id'] ?? 'N/A',
            __('Membership Status', 'cison')  => $s2_data['membership_status'] ?? 'N/A',
            __('Rank', 'cison')               => $s2_data['rank'] ?? 'N/A',
            __('Date', 'cison')               => $s2_data['date'] ?? 'N/A',
            __('Signature', 'cison')          => !empty($s2_data['signature']) ? $s2_data['signature'] : 'N/A',
        ) : array();
        $this->detail_card(
            __('Sponsor 2', 'cison') . ' &mdash; ' . cison_fellowship_render_status_badge($row['sponsor_2_status'] ?? 'pending'),
            $s2_fields,
            empty($s2_data)
        );

        // Qualifications
        echo '<div class="cison-fs-detail__card">';
        echo '<h4>' . esc_html__('Academic Qualifications', 'cison') . '</h4>';
        if (!empty($quals)) {
            echo '<table class="cison-fs-detail__quals-table"><thead><tr><th>#</th><th>' . esc_html__('Details', 'cison') . '</th></tr></thead><tbody>';
            foreach ($quals as $i => $line) {
                echo '<tr><td>' . ($i + 1) . '</td><td>' . esc_html($line) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="cison-fs-detail__empty">' . esc_html__('No qualifications listed.', 'cison') . '</p>';
        }
        echo '</div>';

        // Professional Experience
        $this->detail_text_card(__('Professional Experience', 'cison'), $row['professional_experience'] ?? '');

        // Publications
        $this->detail_text_card(__('Publications, Research & Contribution', 'cison'), $row['publications'] ?? '');

        // Metadata
        $this->detail_card(__('Submission Metadata', 'cison'), array(
            __('Payment Status', 'cison')    => cison_fellowship_render_status_badge($row['payment_status']),
            __('Application Status', 'cison') => $row['application_status'] ?: 'N/A',
            __('Product IDs', 'cison')      => $row['product_ids'] ?: 'N/A',
            __('Order ID', 'cison')         => $row['order_id'] ?: 'N/A',
            __('IP Address', 'cison')       => $row['ip_address'] ?: 'N/A',
            __('Registered', 'cison')       => date_i18n('M j, Y g:i a', strtotime($row['registration_date'])),
            __('Last Updated', 'cison')     => date_i18n('M j, Y g:i a', strtotime($row['updated_at'])),
        ), false, true);

        if (($row['sponsor_2_status'] ?? '') !== 'submitted') {
            // Resend sponsorship email (sponsorship not yet complete)
            echo '<div class="cison-fs-detail__card cison-fs-detail__card--meta">';
            echo '<h4>' . esc_html__('Resend Sponsorship Email', 'cison') . '</h4>';
            echo '<p class="cison-fs-detail__help">Resend the sponsorship endorsement instructions to the applicant (' .
                esc_html($row['email']) . '). Use this when the applicant has not yet secured both sponsor endorsements.</p>';
            echo '<form method="post">';
            wp_nonce_field('cison_fs_resend_email_' . md5($ref));
            echo '<input type="hidden" name="cison_fs_resend_email" value="1" />';
            echo '<input type="hidden" name="fs_ref" value="' . esc_attr($ref) . '" />';
            submit_button(__('Resend Sponsorship Email', 'cison'), 'secondary');
            echo '</form>';
            echo '</div>';
        }

        // Edit created-at form
        echo '<div class="cison-fs-detail__card cison-fs-detail__card--meta">';
        echo '<h4>' . esc_html__('Edit Created At', 'cison') . '</h4>';
        echo '<form method="post">';
        wp_nonce_field('cison_fs_update_created_' . md5($ref));
        echo '<input type="hidden" name="cison_fs_update_created" value="1" />';
        echo '<input type="hidden" name="fs_ref" value="' . esc_attr($ref) . '" />';
        echo '<p><label for="cison_fs_created_at"><strong>' . esc_html__('Created At', 'cison') . '</strong></label><br>';
        echo '<input type="text" id="cison_fs_created_at" name="cison_fs_created_at" value="' . esc_attr($row['registration_date']) . '" class="regular-text" style="margin-top:6px;" />';
        echo '<span class="description"><br>' . esc_html__('Format: YYYY-MM-DD HH:MM:SS', 'cison') . '</span></p>';
        submit_button(__('Update Created At', 'cison'));
        echo '</form>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // detail

        echo cison_fellowship_submission_detail_styles();
        echo '</div>';
    }

    /* ==============================================================
     *  EDIT
     * ============================================================== */

    private function get_row($ref)
    {
        if (!$ref) {
            return null;
        }
        global $wpdb;
        $table = cison_fellowship_get_table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE reference_number = %s LIMIT 1", $ref), ARRAY_A);
    }

    /**
     * Encode sponsor detail fields for storage. Returns an empty string when the
     * sponsor has no meaningful data yet so the detail view keeps showing the
     * "Awaiting sponsor endorsement" state.
     */
    private function sponsor_data_to_json($data)
    {
        $has_data = !empty(array_filter((array) $data, function ($v) {
            return $v !== '' && $v !== null;
        }));
        return $has_data ? wp_json_encode($data) : '';
    }

    private function handle_edit_save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cison_fs_edit_submit'])) {
            return;
        }

        $ref = isset($_POST['fs_ref']) ? sanitize_text_field(wp_unslash($_POST['fs_ref'])) : '';
        if (!$ref) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'cison_fs_edit_' . md5($ref))) {
            return;
        }

        $post = wp_unslash($_POST);

        $required = array('first_name', 'last_name', 'email', 'phone', 'membership_number');
        foreach ($required as $f) {
            if (empty($post[$f])) {
                $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'save-error'));
            }
        }

        if (!is_email($post['email'])) {
            $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'save-error'));
        }

        $created_at = isset($post['registration_date']) ? trim(sanitize_text_field($post['registration_date'])) : '';
        if ($created_at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $created_at)) {
            $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'date-error'));
        }
        if ($created_at === '') {
            $created_at = $this->get_row($ref)['registration_date'] ?? current_time('mysql');
        }

        $updated_at = isset($post['updated_at']) ? trim(sanitize_text_field($post['updated_at'])) : '';
        if ($updated_at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $updated_at)) {
            $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'date-error'));
        }

        $nsa_fellow = in_array(strtolower($post['is_nsa_fellow'] ?? ''), array('yes', 'true', '1'), true);
        $nsa_id     = strtoupper(trim($post['nsa_fellow_id'] ?? ''));

        global $wpdb;
        $table = cison_fellowship_get_table_name();

        // Duplicate membership number check (excluding this record).
        $mem = trim($post['membership_number']);
        if ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE membership_number = %s AND membership_number != '' AND reference_number != %s LIMIT 1",
            $mem,
            $ref
        ))) {
            $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'save-error'));
        }

        if ($nsa_fellow && !empty($nsa_id) && $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE is_nsa_fellow = 'yes' AND nsa_fellow_id = %s AND nsa_fellow_id != '' AND reference_number != %s LIMIT 1",
            $nsa_id,
            $ref
        ))) {
            $this->redirect('edit', array('ref' => $ref, 'fs_notice' => 'save-error'));
        }

        // Existing sponsor data so signatures are preserved.
        $existing = $this->get_row($ref);
        $existing_s1 = !empty($existing['sponsor_1_data']) ? json_decode($existing['sponsor_1_data'], true) : array();
        $existing_s2 = !empty($existing['sponsor_2_data']) ? json_decode($existing['sponsor_2_data'], true) : array();

        $s1_data = array(
            'name'             => isset($post['sponsor_1_name']) ? sanitize_text_field($post['sponsor_1_name']) : ($existing_s1['name'] ?? ''),
            'membership_id'    => isset($post['sponsor_1_membership_id']) ? sanitize_text_field($post['sponsor_1_membership_id']) : ($existing_s1['membership_id'] ?? ''),
            'membership_status' => isset($post['sponsor_1_membership_status']) ? sanitize_text_field($post['sponsor_1_membership_status']) : ($existing_s1['membership_status'] ?? ''),
            'rank'             => isset($post['sponsor_1_rank']) ? sanitize_text_field($post['sponsor_1_rank']) : ($existing_s1['rank'] ?? ''),
            'date'             => isset($post['sponsor_1_date']) ? sanitize_text_field($post['sponsor_1_date']) : ($existing_s1['date'] ?? ''),
            'signature'        => isset($post['sponsor_1_signature']) ? sanitize_text_field($post['sponsor_1_signature']) : ($existing_s1['signature'] ?? ''),
        );
        $s2_data = array(
            'name'             => isset($post['sponsor_2_name']) ? sanitize_text_field($post['sponsor_2_name']) : ($existing_s2['name'] ?? ''),
            'membership_id'    => isset($post['sponsor_2_membership_id']) ? sanitize_text_field($post['sponsor_2_membership_id']) : ($existing_s2['membership_id'] ?? ''),
            'membership_status' => isset($post['sponsor_2_membership_status']) ? sanitize_text_field($post['sponsor_2_membership_status']) : ($existing_s2['membership_status'] ?? ''),
            'rank'             => isset($post['sponsor_2_rank']) ? sanitize_text_field($post['sponsor_2_rank']) : ($existing_s2['rank'] ?? ''),
            'date'             => isset($post['sponsor_2_date']) ? sanitize_text_field($post['sponsor_2_date']) : ($existing_s2['date'] ?? ''),
            'signature'        => isset($post['sponsor_2_signature']) ? sanitize_text_field($post['sponsor_2_signature']) : ($existing_s2['signature'] ?? ''),
        );

        $data = array(
            'is_member'              => sanitize_text_field($post['membership_status'] ?? ''),
            'is_nsa_fellow'          => $nsa_fellow ? 'yes' : 'no',
            'nsa_fellow_id'          => $nsa_fellow ? $nsa_id : '',
            'membership_category'    => sanitize_text_field($post['membership_category'] ?? ''),
            'membership_number'      => $mem,
            'title'                  => sanitize_text_field($post['title'] ?? ''),
            'first_name'             => sanitize_text_field($post['first_name']),
            'middle_name'            => sanitize_text_field($post['middle_name'] ?? ''),
            'last_name'              => sanitize_text_field($post['last_name']),
            'email'                  => strtolower(sanitize_email($post['email'])),
            'phone'                  => sanitize_text_field($post['phone']),
            'gender'                 => sanitize_text_field($post['gender'] ?? ''),
            'date_of_birth'          => !empty($post['date_of_birth']) ? sanitize_text_field($post['date_of_birth']) : null,
            'nationality'            => sanitize_text_field($post['nationality'] ?? ''),
            'occupation'             => sanitize_text_field($post['occupation'] ?? ''),
            'designation'            => sanitize_text_field($post['designation'] ?? ''),
            'employer'               => sanitize_text_field($post['employer'] ?? ''),
            'street'                 => sanitize_text_field($post['street'] ?? ''),
            'city'                   => sanitize_text_field($post['city'] ?? ''),
            'state'                  => sanitize_text_field($post['state'] ?? ''),
            'country'                => sanitize_text_field($post['country'] ?? ''),
            'years_of_practice'      => sanitize_text_field($post['years_of_practice'] ?? ''),
            'area_of_practice'       => sanitize_textarea_field($post['area_of_practice'] ?? ''),
            'academic_qualifications' => sanitize_textarea_field($post['academic_qualifications'] ?? ''),
            'professional_experience' => sanitize_textarea_field($post['professional_experience'] ?? ''),
            'publications'           => sanitize_textarea_field($post['publications'] ?? ''),
            'order_id'               => sanitize_text_field($post['order_id'] ?? ''),
            'product_ids'            => sanitize_text_field($post['product_ids'] ?? ''),
            'payment_status'         => sanitize_text_field($post['payment_status'] ?? 'pending'),
            'application_status'     => sanitize_text_field($post['application_status'] ?? 'submitted'),
            'registration_date'      => $created_at,
            'updated_at'             => $updated_at !== '' ? $updated_at : current_time('mysql'),
            'ip_address'             => sanitize_text_field($post['ip_address'] ?? ''),
            'sponsor_1_status'       => sanitize_text_field($post['sponsor_1_status'] ?? 'pending'),
            'sponsor_2_status'       => sanitize_text_field($post['sponsor_2_status'] ?? 'pending'),
            'sponsor_1_data'         => $this->sponsor_data_to_json($s1_data),
            'sponsor_2_data'         => $this->sponsor_data_to_json($s2_data),
        );

        $updated = $wpdb->update($table, $data, array('reference_number' => $ref));

        $this->redirect('view', array('ref' => $ref, 'fs_notice' => false === $updated ? 'save-error' : 'saved'));
    }

    private function render_edit_screen()
    {
        $ref  = isset($_REQUEST['ref']) ? sanitize_text_field(wp_unslash($_REQUEST['ref'])) : '';
        $row  = $this->get_row($ref);
        $back_url = admin_url('tools.php?page=' . self::PAGE_SLUG);
        $view_url = admin_url('tools.php?page=' . self::PAGE_SLUG . '&action=view&ref=' . rawurlencode($ref));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Edit Fellowship Submission', 'cison') . '</h1>';
        echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('Back to list', 'cison') . '</a> | ' .
            '<a href="' . esc_url($view_url) . '">' . esc_html__('View submission', 'cison') . '</a></p>';

        if (!$row) {
            echo '<p>' . esc_html__('Record not found.', 'cison') . '</p>';
            echo '</div>';
            return;
        }

        if (isset($_GET['fs_notice'])) {
            $this->print_notice(sanitize_key(wp_unslash($_GET['fs_notice'])));
        }

        echo '<form method="post">';
        wp_nonce_field('cison_fs_edit_' . md5($ref));
        echo '<input type="hidden" name="cison_fs_edit_submit" value="1" />';
        echo '<input type="hidden" name="fs_ref" value="' . esc_attr($ref) . '" />';

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_text('reference_number', __('Reference Number', 'cison'), $row['reference_number'], false, '', true);
        $this->form_row_text('id', __('ID', 'cison'), $row['id'], false, '', true);
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Personal Information', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('title', __('Title', 'cison'), cison_fellowship_get_titles(), $row['title']);
        $this->form_row_text('first_name', __('First Name', 'cison'), $row['first_name'], true);
        $this->form_row_text('middle_name', __('Middle Name', 'cison'), $row['middle_name']);
        $this->form_row_text('last_name', __('Last Name', 'cison'), $row['last_name'], true);
        $this->form_row_text('email', __('Email', 'cison'), $row['email'], true);
        $this->form_row_text('phone', __('Phone', 'cison'), $row['phone'], true);
        $this->form_row_select('gender', __('Gender', 'cison'), cison_fellowship_get_genders(), $row['gender']);
        $this->form_row_text('date_of_birth', __('Date of Birth', 'cison'), $row['date_of_birth'], false, 'YYYY-MM-DD');
        $this->form_row_text('nationality', __('Nationality', 'cison'), $row['nationality']);
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Address', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_text('street', __('Street', 'cison'), $row['street']);
        $this->form_row_text('city', __('City', 'cison'), $row['city']);
        $this->form_row_text('state', __('State', 'cison'), $row['state']);
        $this->form_row_text('country', __('Country', 'cison'), $row['country']);
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Professional', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_text('occupation', __('Occupation', 'cison'), $row['occupation']);
        $this->form_row_text('designation', __('Designation', 'cison'), $row['designation']);
        $this->form_row_text('employer', __('Employer / Institution', 'cison'), $row['employer']);
        $this->form_row_text('years_of_practice', __('Years of Practice', 'cison'), $row['years_of_practice']);
        $this->form_row_textarea('area_of_practice', __('Area of Statistics', 'cison'), '', $row['area_of_practice']);
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Membership', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('membership_status', __('Membership Status', 'cison'), array('member', 'non-member'), $row['is_member']);
        $this->form_row_select('membership_category', __('Membership Category', 'cison'), cison_fellowship_get_membership_categories(), $row['membership_category']);
        $this->form_row_text('membership_number', __('Membership Number', 'cison'), $row['membership_number'], true);
        $this->form_row_select('is_nsa_fellow', __('NSA Fellow?', 'cison'), array('no', 'yes'), $row['is_nsa_fellow']);
        $this->form_row_text('nsa_fellow_id', __('NSA Fellow ID', 'cison'), $row['nsa_fellow_id']);
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Qualifications & Experience', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_textarea('academic_qualifications', __('Academic Qualifications', 'cison'), 'One per line.', $row['academic_qualifications']);
        $this->form_row_textarea('professional_experience', __('Professional Experience', 'cison'), '', $row['professional_experience']);
        $this->form_row_textarea('publications', __('Publications / Contribution', 'cison'), '', $row['publications']);
        echo '</tbody></table>';

        $s1_data = !empty($row['sponsor_1_data']) ? json_decode($row['sponsor_1_data'], true) : array();
        $s2_data = !empty($row['sponsor_2_data']) ? json_decode($row['sponsor_2_data'], true) : array();

        echo '<h2>' . esc_html__('Sponsor 1', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('sponsor_1_status', __('Sponsor 1 Status', 'cison'), array('pending', 'submitted', 'approved', 'rejected'), $row['sponsor_1_status']);
        $this->form_row_text('sponsor_1_name', __('Full Name', 'cison'), $s1_data['name'] ?? '');
        $this->form_row_text('sponsor_1_membership_id', __('Membership ID', 'cison'), $s1_data['membership_id'] ?? '');
        $this->form_row_text('sponsor_1_membership_status', __('Membership Status', 'cison'), $s1_data['membership_status'] ?? '');
        $this->form_row_text('sponsor_1_rank', __('Rank', 'cison'), $s1_data['rank'] ?? '');
        $this->form_row_text('sponsor_1_date', __('Date', 'cison'), $s1_data['date'] ?? '');
        $this->form_row_text('sponsor_1_signature', __('Signature URL', 'cison'), $s1_data['signature'] ?? '');
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Sponsor 2', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('sponsor_2_status', __('Sponsor 2 Status', 'cison'), array('pending', 'submitted', 'approved', 'rejected'), $row['sponsor_2_status']);
        $this->form_row_text('sponsor_2_name', __('Full Name', 'cison'), $s2_data['name'] ?? '');
        $this->form_row_text('sponsor_2_membership_id', __('Membership ID', 'cison'), $s2_data['membership_id'] ?? '');
        $this->form_row_text('sponsor_2_membership_status', __('Membership Status', 'cison'), $s2_data['membership_status'] ?? '');
        $this->form_row_text('sponsor_2_rank', __('Rank', 'cison'), $s2_data['rank'] ?? '');
        $this->form_row_text('sponsor_2_date', __('Date', 'cison'), $s2_data['date'] ?? '');
        $this->form_row_text('sponsor_2_signature', __('Signature URL', 'cison'), $s2_data['signature'] ?? '');
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Status & Metadata', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('payment_status', __('Payment Status', 'cison'), array('pending', 'paid', 'failed', 'refunded'), $row['payment_status']);
        $this->form_row_select('application_status', __('Application Status', 'cison'), array('submitted', 'under_review', 'approved', 'rejected'), $row['application_status']);
        $this->form_row_text('order_id', __('Order ID', 'cison'), $row['order_id']);
        $this->form_row_text('product_ids', __('Product IDs', 'cison'), $row['product_ids']);
        $this->form_row_text('ip_address', __('IP Address', 'cison'), $row['ip_address']);
        $this->form_row_text('registration_date', __('Created At', 'cison'), $row['registration_date'], false, 'YYYY-MM-DD HH:MM:SS');
        $this->form_row_text('updated_at', __('Last Updated', 'cison'), $row['updated_at'], false, 'YYYY-MM-DD HH:MM:SS');
        echo '</tbody></table>';

        submit_button(__('Save Changes', 'cison'), 'primary', 'cison_fs_edit_submit');

        echo '</form>';
        echo '</div>';
    }

    /* -------------------------------------------------------------- */
    /*  Detail card helpers                                            */
    /* -------------------------------------------------------------- */

    private function detail_card($title, $fields, $is_empty_message = false, $is_meta = false)
    {
        $extra = $is_meta ? ' cison-fs-detail__card--meta' : '';
        echo '<div class="cison-fs-detail__card' . $extra . '">';
        echo '<h4>' . wp_kses_post($title) . '</h4>';

        if ($is_empty_message) {
            echo '<p class="cison-fs-detail__empty">' . esc_html__('Awaiting sponsor endorsement.', 'cison') . '</p>';
        } else {
            echo '<div class="cison-fs-detail__fields">';
            foreach ($fields as $label => $value) {
                if ($label === __('Signature', 'cison') && empty($value)) {
                    continue;
                }
                echo '<div class="cison-fs-detail__field">';
                echo '<span class="cison-fs-detail__label">' . esc_html($label) . '</span>';
                if ($label === __('Signature', 'cison') && !empty($value) && $value !== 'N/A') {
                    echo '<span class="cison-fs-detail__value"><a href="' . esc_url($value) . '" target="_blank">' . esc_html__('View Signature', 'cison') . '</a></span>';
                } else {
                    echo '<span class="cison-fs-detail__value">' . esc_html($value ?: 'N/A') . '</span>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    private function detail_text_card($title, $content)
    {
        echo '<div class="cison-fs-detail__card">';
        echo '<h4>' . esc_html($title) . '</h4>';
        echo '<div class="cison-fs-detail__text-block">' . esc_html($content ?: __('No details provided.', 'cison')) . '</div>';
        echo '</div>';
    }

    /* ==============================================================
     *  CREATE
     * ============================================================== */

    private function handle_create_save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cison_fs_create_submit'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'cison_fs_create')) {
            return;
        }

        $post = wp_unslash($_POST);

        // Validate required fields
        $required = array('first_name', 'last_name', 'email', 'phone', 'membership_number');
        foreach ($required as $f) {
            if (empty($post[$f])) {
                $this->redirect('create', array('fs_notice' => 'create-error'));
            }
        }

        if (!is_email($post['email'])) {
            $this->redirect('create', array('fs_notice' => 'create-error'));
        }

        // Optional created-at; validates format or falls back to now.
        $created_at = isset($post['registration_date']) ? trim(sanitize_text_field($post['registration_date'])) : '';
        if ($created_at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $created_at)) {
            $this->redirect('create', array('fs_notice' => 'date-error'));
        }
        $registration_date = $created_at !== '' ? $created_at : current_time('mysql');

        global $wpdb;
        $table = cison_fellowship_get_table_name();

        // Duplicate membership number check
        $mem = trim($post['membership_number']);
        if ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE membership_number = %s AND membership_number != '' LIMIT 1",
            $mem
        ))) {
            $this->redirect('create', array('fs_notice' => 'create-error'));
        }

        $nsa_fellow = in_array(strtolower($post['is_nsa_fellow'] ?? ''), array('yes', 'true', '1'), true);
        $nsa_id     = strtoupper(trim($post['nsa_fellow_id'] ?? ''));

        if ($nsa_fellow && !empty($nsa_id) && $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE is_nsa_fellow = 'yes' AND nsa_fellow_id = %s AND nsa_fellow_id != '' LIMIT 1",
            $nsa_id
        ))) {
            $this->redirect('create', array('fs_notice' => 'create-error'));
        }

        $insert = array(
            'reference_number'     => cison_fellowship_generate_reference_number(),
            'order_id'             => 0,
            'is_member'            => sanitize_text_field($post['membership_status'] ?? ''),
            'is_nsa_fellow'        => $nsa_fellow ? 'yes' : 'no',
            'nsa_fellow_id'        => $nsa_fellow ? $nsa_id : '',
            'membership_category'  => sanitize_text_field($post['membership_category'] ?? ''),
            'membership_number'    => $mem,
            'title'                => sanitize_text_field($post['title'] ?? ''),
            'first_name'           => sanitize_text_field($post['first_name']),
            'middle_name'          => sanitize_text_field($post['middle_name'] ?? ''),
            'last_name'            => sanitize_text_field($post['last_name']),
            'email'                => strtolower(sanitize_email($post['email'])),
            'phone'                => sanitize_text_field($post['phone']),
            'gender'               => sanitize_text_field($post['gender'] ?? ''),
            'date_of_birth'        => $post['date_of_birth'] ? sanitize_text_field($post['date_of_birth']) : null,
            'nationality'          => sanitize_text_field($post['nationality'] ?? ''),
            'occupation'           => sanitize_text_field($post['occupation'] ?? ''),
            'designation'          => sanitize_text_field($post['designation'] ?? ''),
            'employer'             => sanitize_text_field($post['employer'] ?? ''),
            'street'               => sanitize_text_field($post['street'] ?? ''),
            'city'                 => sanitize_text_field($post['city'] ?? ''),
            'state'                => sanitize_text_field($post['state'] ?? ''),
            'country'              => sanitize_text_field($post['country'] ?? ''),
            'years_of_practice'    => sanitize_text_field($post['years_of_practice'] ?? ''),
            'area_of_practice'     => sanitize_textarea_field($post['area_of_practice'] ?? ''),
            'academic_qualifications' => sanitize_textarea_field($post['academic_qualifications'] ?? ''),
            'professional_experience' => sanitize_textarea_field($post['professional_experience'] ?? ''),
            'publications'         => sanitize_textarea_field($post['publications'] ?? ''),
            'num_sponsors'         => 2,
            'product_ids'          => '',
            'payment_status'       => sanitize_text_field($post['payment_status'] ?? 'pending'),
            'application_status'   => sanitize_text_field($post['application_status'] ?? 'submitted'),
            'registration_date'    => $registration_date,
            'updated_at'           => current_time('mysql'),
            'ip_address'           => '',
            'sponsor_token'        => cison_fellowship_generate_token(),
            'sponsor_1_status'     => 'pending',
            'sponsor_2_status'     => 'pending',
        );

        $inserted = $wpdb->insert($table, $insert);

        if (!$inserted) {
            $this->redirect('create', array('fs_notice' => 'create-error'));
        }

        $new_ref = $insert['reference_number'];
        $this->redirect('view', array('ref' => $new_ref, 'fs_notice' => 'created'));
    }

    private function render_create_screen()
    {
        $back_url = admin_url('tools.php?page=' . self::PAGE_SLUG);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Create Fellowship Submission', 'cison') . '</h1>';
        echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('Back to list', 'cison') . '</a></p>';

        if (isset($_GET['fs_notice'])) {
            $this->print_notice(sanitize_key(wp_unslash($_GET['fs_notice'])));
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('cison_fs_create');

        echo '<table class="form-table" role="presentation"><tbody>';

        $this->form_row_select('title', __('Title', 'cison'), cison_fellowship_get_titles(), '');
        $this->form_row_text('first_name', __('First Name', 'cison'), '', true);
        $this->form_row_text('middle_name', __('Middle Name', 'cison'));
        $this->form_row_text('last_name', __('Last Name', 'cison'), '', true);
        $this->form_row_text('email', __('Email', 'cison'), '', true);
        $this->form_row_text('phone', __('Phone', 'cison'), '', true);
        $this->form_row_select('gender', __('Gender', 'cison'), cison_fellowship_get_genders(), '');
        $this->form_row_text('date_of_birth', __('Date of Birth', 'cison'), '', false, 'YYYY-MM-DD');
        $this->form_row_text('nationality', __('Nationality', 'cison'));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Address', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_text('street', __('Street', 'cison'));
        $this->form_row_text('city', __('City', 'cison'));
        $this->form_row_text('state', __('State', 'cison'));
        $this->form_row_text('country', __('Country', 'cison'));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Professional', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_text('occupation', __('Occupation', 'cison'));
        $this->form_row_text('designation', __('Designation', 'cison'));
        $this->form_row_text('employer', __('Employer / Institution', 'cison'));
        $this->form_row_text('years_of_practice', __('Years of Practice', 'cison'));
        $this->form_row_textarea('area_of_practice', __('Area of Statistics', 'cison'));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Membership', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('membership_status', __('Membership Status', 'cison'), array('member', 'non-member'), '');
        $this->form_row_select('membership_category', __('Membership Category', 'cison'), cison_fellowship_get_membership_categories(), '');
        $this->form_row_text('membership_number', __('Membership Number', 'cison'), '', true);
        $this->form_row_select('is_nsa_fellow', __('NSA Fellow?', 'cison'), array('no', 'yes'), 'no');
        $this->form_row_text('nsa_fellow_id', __('NSA Fellow ID', 'cison'));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Qualifications & Experience', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_textarea('academic_qualifications', __('Academic Qualifications', 'cison'), 'One per line.');
        $this->form_row_textarea('professional_experience', __('Professional Experience', 'cison'));
        $this->form_row_textarea('publications', __('Publications / Contribution', 'cison'));
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Status', 'cison') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->form_row_select('payment_status', __('Payment Status', 'cison'), array('pending', 'paid', 'failed', 'refunded'), 'pending');
        $this->form_row_select('application_status', __('Application Status', 'cison'), array('submitted', 'under_review', 'approved', 'rejected'), 'submitted');
        $this->form_row_text('registration_date', __('Created At', 'cison'), '', false, 'YYYY-MM-DD HH:MM:SS (defaults to now)');
        echo '</tbody></table>';

        submit_button(__('Create Submission', 'cison'), 'primary', 'cison_fs_create_submit');

        echo '</form>';
        echo '</div>';
    }

    /* -------------------------------------------------------------- */
    /*  Form row helpers                                               */
    /* -------------------------------------------------------------- */

    private function form_row_text($name, $label, $value = '', $required = false, $placeholder = '', $readonly = false)
    {
        $req = $required ? ' <span class="description">*</span>' : '';
        $ro  = $readonly ? ' readonly' : '';
        printf(
            '<tr><th scope="row"><label for="cison_fs_%1$s">%2$s%3$s</label></th><td>' .
            '<input type="text" id="cison_fs_%1$s" name="%1$s" value="%4$s" class="regular-text" %5$s%6$s%7$s /></td></tr>',
            esc_attr($name),
            esc_html($label),
            $req,
            esc_attr($value),
            $required ? ' required' : '',
            $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '',
            $ro
        );
    }

    private function form_row_textarea($name, $label, $hint = '', $value = '')
    {
        $hint_html = $hint ? '<p class="description">' . esc_html($hint) . '</p>' : '';
        printf(
            '<tr><th scope="row"><label for="cison_fs_%1$s">%2$s</label></th><td>' .
            '<textarea id="cison_fs_%1$s" name="%1$s" class="large-text" rows="4">%3$s</textarea>%4$s</td></tr>',
            esc_attr($name),
            esc_html($label),
            esc_textarea($value),
            $hint_html
        );
    }

    private function form_row_select($name, $label, $options, $default = '')
    {
        printf(
            '<tr><th scope="row"><label for="cison_fs_%1$s">%2$s</label></th><td>' .
            '<select id="cison_fs_%1$s" name="%1$s">',
            esc_attr($name),
            esc_html($label)
        );
        foreach ($options as $val) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($val),
                selected($default, $val, false),
                esc_html(ucwords(str_replace(array('_', '-'), ' ', $val)))
            );
        }
        echo '</select></td></tr>';
    }

    /* ==============================================================
     *  DELETE
     * ============================================================== */

    private function handle_delete()
    {
        $ref = isset($_GET['ref']) ? sanitize_text_field(wp_unslash($_GET['ref'])) : '';
        if (!$ref) {
            $this->redirect('list', array('fs_notice' => 'not-found'));
        }

        check_admin_referer('cison_delete_fs_' . md5($ref));

        global $wpdb;
        $table = cison_fellowship_get_table_name();
        $deleted = $wpdb->delete($table, array('reference_number' => $ref), array('%s'));

        $this->redirect('list', array('fs_notice' => $deleted ? 'deleted' : 'not-found'));
    }
}

new CISON_Fellowship_Submissions_Admin();
