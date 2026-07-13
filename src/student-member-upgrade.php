<?php
/**
 * Plugin Name: CISON Student Member Upgrade
 * Description: Lets student members request an upgrade to Chartered/Registered Statistician, and gives admins a review queue.
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit; // No direct file access.
}

/* ======================================================
 * MAIN PLUGIN CLASS
 * ====================================================== */

final class Student_Member_Upgrade
{

    /** Bump this if the table schema changes, so activation re-runs dbDelta. */
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'cison_upgrade_db_version';

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
        add_action(
            'bp_before_member_header_meta',
            [$this, 'display_profile_header_upgrade_button'],
            30
        );

        add_action(
            'wp_ajax_submit_cison_upgrade',
            [$this, 'ajax_submit_upgrade_request']
        );

        // Logged-out users still hit admin-ajax.php; without this WP just
        // returns a bare "0" and the JS success/error branch never runs.
        add_action(
            'wp_ajax_nopriv_submit_cison_upgrade',
            [$this, 'ajax_submit_upgrade_request']
        );

        add_action(
            'admin_menu',
            [$this, 'register_admin_menu']
        );

        // Keep the table in sync on plugin updates too, not just activation,
        // in case the plugin is bundled/updated without a fresh activation.
        add_action('plugins_loaded', [$this, 'maybe_upgrade_db']);
    }

    /* -------------------------------------------------------
     * Schema / activation
     * ----------------------------------------------------- */

    public static function activate(): void
    {
        self::get_instance()->create_table();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public function maybe_upgrade_db(): void
    {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            $this->create_table();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    private function create_table(): void
    {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'cison_upgrade_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* -------------------------------------------------------
     * Profile header button
     * ----------------------------------------------------- */

    public function display_profile_header_upgrade_button(): void
    {
        if (!is_user_logged_in() || bp_displayed_user_id() !== get_current_user_id()) {
            return;
        }

        $user_id = get_current_user_id();

        $member_type = bp_get_member_type($user_id);

        if ('student-member' !== $member_type) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cison_upgrade_requests';

        $latest_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        $btn_text      = 'Upgrade to Chartered Statistician';
        $btn_style_bit = 'background: #10b981; color: #fff; border: none; cursor: pointer;';
        $disabled_attr = '';

        if ($latest_entry) {
            if ($latest_entry->status === 'pending') {
                $btn_text      = 'Upgrade Request Pending Review';
                $btn_style_bit = 'background: #f0f7ff; color: #005a87; border: 1px solid #0073aa; cursor: not-allowed; opacity: 0.8;';
                $disabled_attr = 'disabled';
            } elseif ($latest_entry->status === 'approved') {
                return;
            } elseif ($latest_entry->status === 'rejected') {
                $btn_style_bit = 'background: #f59e0b; color: #fff; border: none; cursor: pointer;';
            }
        }

        // Full style used on the button element. We pass this whole string
        // (not just the color bit) back to JS so a failed submission can
        // restore the button to its exact original appearance.
        $btn_style_full = 'padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; '
            . 'text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; ' . $btn_style_bit;
        ?>
        <div class="bb-profile-header-upgrade-action"
            style="margin-top: 10px; margin-bottom: 5px; display: inline-block; width: 100%;">
            <form id="bb-header-upgrade-form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                <input type="hidden" name="action" value="submit_cison_upgrade">
                <?php wp_nonce_field('cison_upgrade_nonce_action', 'cison_nonce'); ?>

                <button type="submit" class="button button-secondary" <?php echo esc_attr($disabled_attr); ?>
                    style="<?php echo esc_attr($btn_style_full); ?>">
                    <?php echo esc_html($btn_text); ?>
                </button>
            </form>
            <div id="header-form-feedback" style="margin-top: 6px; font-size: 12px; font-weight: 600;"></div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                var originalStyle = <?php echo wp_json_encode($btn_style_full); ?>;
                var originalText  = <?php echo wp_json_encode($btn_text); ?>;

                $('#bb-header-upgrade-form').on('submit', function (e) {
                    e.preventDefault();
                    var $msg = $('#header-form-feedback');
                    var $btn = $(this).find('button');

                    $btn.prop('disabled', true)
                        .css({ background: '#6b7280', color: '#fff', cursor: 'not-allowed' })
                        .text('Processing...');

                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function (res) {
                            if (res.success) {
                                location.reload();
                            } else {
                                $msg.css('color', '#ef4444').text(res.data);
                                $btn.prop('disabled', false).attr('style', originalStyle).text(originalText);
                            }
                        },
                        error: function () {
                            $msg.css('color', '#ef4444').text('Something went wrong. Please try again.');
                            $btn.prop('disabled', false).attr('style', originalStyle).text(originalText);
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
            return;
        }

        $nonce = isset($_POST['cison_nonce']) ? sanitize_text_field(wp_unslash($_POST['cison_nonce'])) : '';

        if (!$nonce || !wp_verify_nonce($nonce, 'cison_upgrade_nonce_action')) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        $user_id     = get_current_user_id();
        $member_type = bp_get_member_type($user_id);

        if ('student-member' !== $member_type) {
            wp_send_json_error('Only student members can request this upgrade.');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cison_upgrade_requests';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ($existing && 'pending' === $existing->status) {
            wp_send_json_error('You already have a pending upgrade request.');
            return;
        }
        if ($existing && 'approved' === $existing->status) {
            wp_send_json_error('Your upgrade has already been approved.');
            return;
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'status'     => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log("CISON: failed to insert upgrade request for user {$user_id}: " . $wpdb->last_error);
            wp_send_json_error('Something went wrong submitting your request. Please try again.');
            return;
        }

        wp_send_json_success('Upgrade request submitted. An admin will review it shortly.');
    }

    /* ======================================================
     * ADMIN REVIEW SCREEN
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
            $action     = sanitize_text_field(wp_unslash($_POST['cison_upgrade_action']));
            $row        = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $request_id));

            if (!$row) {
                echo '<div class="notice notice-error"><p>Request not found.</p></div>';
            } elseif (!in_array($action, ['approve', 'reject'], true)) {
                echo '<div class="notice notice-error"><p>Invalid action.</p></div>';
            } elseif ($row->status !== 'pending') {
                // Guards against double-submitting the form (e.g. browser back/refresh).
                echo '<div class="notice notice-warning"><p>Request #' . intval($request_id) . ' was already ' . esc_html($row->status) . '.</p></div>';
            } else {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                $updated    = $wpdb->update(
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
                    echo '<div class="notice notice-success is-dismissible"><p>Request #' . intval($request_id) . ' marked as <strong>' . esc_html($new_status) . '</strong>'
                        . ($new_status === 'approved' ? ' and member type updated to statistician-member.' : '.') . '</p></div>';
                }
            }
        }

        $requested_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $status_filter     = in_array($requested_status, ['pending', 'approved', 'rejected', 'all'], true) ? $requested_status : 'pending';

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
                $user      = get_userdata($r->user_id);
                $user_name = $user ? $user->display_name . ' (' . $user->user_email . ')' : "User #{$r->user_id} (deleted)";
                $member_id = $this->get_profile_field(894, (int) $r->user_id);

                echo '<tr>';
                echo '<td>' . intval($r->id) . '</td>';
                echo '<td>' . esc_html($user_name) . '</td>';
                echo '<td>' . esc_html($member_id !== '' ? $member_id : '-') . '</td>';
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

    public function register_admin_menu(): void
    {
        add_submenu_page(
            'tools.php',
            'CISON Upgrade Requests',
            'Upgrade Requests',
            'manage_options',
            'cison-upgrade-requests',
            [$this, 'render_upgrade_requests_page']
        );
    }

    /* -------------------------------------------------------
     * Helpers
     * ----------------------------------------------------- */

    /**
     * Fetches a single BuddyPress xprofile field value for a user.
     * Was referenced by render_upgrade_requests_page() but never defined -
     * this is the missing piece that caused a fatal error on that screen.
     *
     * @param int $field_id BuddyPress xprofile field ID.
     * @param int $user_id  WordPress user ID.
     * @return string Field value, or '' if unavailable.
     */
    private function get_profile_field(int $field_id, int $user_id): string
    {
        if (!function_exists('xprofile_get_field_data')) {
            return '';
        }

        $value = xprofile_get_field_data($field_id, $user_id);

        return is_string($value) ? $value : '';
    }
}

/* ======================================================
 * BOOT
 * ====================================================== */

register_activation_hook(__FILE__, ['Student_Member_Upgrade', 'activate']);

Student_Member_Upgrade::get_instance();