<?php


/* ======================================================
 * MAIN PLUGIN CLASS
 * ====================================================== */

final class Student_Member_Upgrade
{

    /* -------------------------------------------------------
     * Singleton
     * ----------------------------------------------------- */

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->register_hooks();
    }

    /* -------------------------------------------------------
     * Hook registration
     * ----------------------------------------------------- */

    private function register_hooks(): void
    {

        add_action('bp_before_member_header_meta', [$this, 'display_profile_header_upgrade_button'], 30);


        add_action('wp_ajax_submit_cison_upgrade', [$this, 'ajax_submit_upgrade_request']);

        add_submenu_page('tools.php', 'CISON Upgrade Requests', 'Upgrade Requests', 'manage_options', 'cison-upgrade-requests', [$this, 'render_upgrade_requests_page']);
    }

    public function display_profile_header_upgrade_button()
    {

        if (!is_user_logged_in() || bp_displayed_user_id() !== get_current_user_id()) {
            return;
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        $member_type = bp_get_member_type($user_id);

        if ('student-member' !== $member_type) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cison_upgrade_requests';


        $latest_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));


        $btn_text = 'Upgrade to Charactered Statistician';
        $btn_style = 'background: #10b981; color: #fff; border: none; cursor: pointer;';
        $disabled_attr = '';

        if ($latest_entry) {
            if ($latest_entry->status === 'pending') {
                $btn_text = 'Upgrade Request Pending Review';
                $btn_style = 'background: #f0f7ff; color: #005a87; border: 1px solid #0073aa; cursor: not-allowed; opacity: 0.8;';
                $disabled_attr = 'disabled';
            } elseif ($latest_entry->status === 'approved') {
                return;
            } elseif ($latest_entry->status === 'rejected') {
                $btn_style = 'background: #f59e0b; color: #fff; border: none; cursor: pointer;';
            }
        }


        ?>
        <div class="bb-profile-header-upgrade-action"
            style="margin-top: 10px; margin-bottom: 5px; display: inline-block; width: 100%;">
            <form id="bb-header-upgrade-form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                <input type="hidden" name="action" value="submit_cison_upgrade">
                <?php wp_nonce_field('cison_upgrade_nonce_action', 'cison_nonce'); ?>

                <button type="submit" class="button button-secondary" <?php echo $disabled_attr; ?>
                    style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; <?php echo $btn_style; ?>">
                    <?php echo esc_html($btn_text); ?>
                </button>
            </form>
            <div id="header-form-feedback" style="margin-top: 6px; font-size: 12px; font-weight: 600;"></div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#bb-header-upgrade-form').on('submit', function (e) {
                    e.preventDefault();
                    var $msg = $('#header-form-feedback');
                    var $btn = $(this).find('button');

                    $btn.prop('disabled', true).css({ 'background': '#6b7280', 'color': '#fff', 'cursor': 'not-allowed' }).text('Processing...');

                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function (res) {
                            if (res.success) {
                                location.reload();
                            } else {
                                $msg.css('color', '#ef4444').text(res.data);
                                $btn.prop('disabled', false).attr('style', '<?php echo $btn_style; ?>').text('<?php echo esc_js($btn_text); ?>');
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Handles the "Upgrade to Chartered Statistician" button's AJAX submission.
     * Restricted to student members, matching the button's own visibility rule.
     */
    public function ajax_submit_upgrade_request(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to request an upgrade.');
        }

        if (!isset($_POST['cison_nonce']) || !wp_verify_nonce($_POST['cison_nonce'], 'cison_upgrade_nonce_action')) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
        }

        $user_id = get_current_user_id();
        $member_type = bp_get_member_type($user_id);

        if ('student-member' !== $member_type) {
            wp_send_json_error('Only student members can request this upgrade.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cison_upgrade_requests';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ($existing && 'pending' === $existing->status) {
            wp_send_json_error('You already have a pending upgrade request.');
        }
        if ($existing && 'approved' === $existing->status) {
            wp_send_json_error('Your upgrade has already been approved.');
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log("CISON: failed to insert upgrade request for user {$user_id}: " . $wpdb->last_error);
            wp_send_json_error('Something went wrong submitting your request. Please try again.');
        }

        wp_send_json_success('Upgrade request submitted. An admin will review it shortly.');
    }


    /* ======================================================
     * CERTIFICATE ENDPOINT
     * ====================================================== */
    public function render_upgrade_requests_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cison_upgrade_requests';

        if (isset($_POST['cison_upgrade_action'], $_POST['request_id']) && check_admin_referer('cison_upgrade_review')) {
            $request_id = intval($_POST['request_id']);
            $action = sanitize_text_field($_POST['cison_upgrade_action']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $request_id));

            if (!$row) {
                echo '<div class="notice notice-error"><p>Request not found.</p></div>';
            } elseif (!in_array($action, ['approve', 'reject'], true)) {
                echo '<div class="notice notice-error"><p>Invalid action.</p></div>';
            } else {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                $updated = $wpdb->update(
                    $table,
                    ['status' => $new_status, 'updated_at' => current_time('mysql')],
                    ['id' => $request_id],
                    ['%s', '%s'],
                    ['%d']
                );

                if ($updated === false) {
                    error_log("CISON: failed to update upgrade request {$request_id}: " . $wpdb->last_error);
                    echo '<div class="notice notice-error"><p>Failed to update the request. Check the error log.</p></div>';
                } else {
                    if ($new_status === 'approved') {
                        if (function_exists('bp_set_member_type')) {
                            bp_set_member_type((int) $row->user_id, 'statistician-member');
                        } else {
                            error_log('CISON: bp_set_member_type() not available - could not change member type for user ' . $row->user_id);
                        }
                        // Required fees differ by member type, so clear the cached
                        // paid/unpaid computation for this user now that they've changed tier.
                        delete_transient('cison_paid_fees_' . $row->user_id);
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>Request #' . intval($request_id) . ' marked as <strong>' . esc_html($new_status) . '</strong>' . ($new_status === 'approved' ? ' and member type updated to statistician-member.' : '.') . '</p></div>';
                }
            }
        }

        $status_filter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'all'], true) ? $_GET['status'] : 'pending';

        if ($status_filter === 'all') {
            $requests = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
        } else {
            $requests = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status_filter));
        }

        $base_url = admin_url('tools.php?page=cison-upgrade-requests');

        echo '<div class="wrap"><h1>CISON Upgrade Requests</h1>';
        echo '<p>Review member requests to upgrade from Student Member to Chartered/Registered Statistician.</p>';

        echo '<ul class="subsubsub">';
        foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label) {
            $class = $status_filter === $key ? 'current' : '';
            echo '<li><a class="' . esc_attr($class) . '" href="' . esc_url(add_query_arg('status', $key, $base_url)) . '">' . esc_html($label) . '</a> |</li>';
        }
        echo '</ul><br class="clear">';

        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th>ID</th><th>User</th><th>Member ID</th><th>Status</th><th>Requested</th><th>Last Updated</th><th>Action</th>';
        echo '</tr></thead><tbody>';

        if (empty($requests)) {
            echo '<tr><td colspan="7"><em>No requests found.</em></td></tr>';
        } else {
            foreach ($requests as $r) {
                $user = get_userdata($r->user_id);
                $user_name = $user ? $user->display_name . ' (' . $user->user_email . ')' : "User #{$r->user_id} (deleted)";
                $member_id = $this->get_profile_field(894, (int) $r->user_id);

                echo '<tr>';
                echo '<td>' . intval($r->id) . '</td>';
                echo '<td>' . esc_html($user_name) . '</td>';
                echo '<td>' . esc_html($member_id ?: '-') . '</td>';
                echo '<td>' . esc_html(ucfirst($r->status)) . '</td>';
                echo '<td>' . esc_html($r->created_at) . '</td>';
                echo '<td>' . esc_html($r->updated_at) . '</td>';
                echo '<td>';
                if ($r->status === 'pending') {
                    echo '<form method="post" style="display:inline-block;margin-right:6px;">';
                    wp_nonce_field('cison_upgrade_review');
                    echo '<input type="hidden" name="request_id" value="' . intval($r->id) . '">';
                    echo '<button type="submit" name="cison_upgrade_action" value="approve" class="button button-primary">Approve</button>';
                    echo '</form>';
                    echo '<form method="post" style="display:inline-block;">';
                    wp_nonce_field('cison_upgrade_review');
                    echo '<input type="hidden" name="request_id" value="' . intval($r->id) . '">';
                    echo '<button type="submit" name="cison_upgrade_action" value="reject" class="button button-secondary" onclick="return confirm(\'Reject this upgrade request?\');">Reject</button>';
                    echo '</form>';
                } else {
                    echo '<em>—</em>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table></div>';
    }



}


/* ======================================================
 * BOOT
 * ====================================================== */

Student_Member_Upgrade::get_instance();