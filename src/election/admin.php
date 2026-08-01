<?php

function evp_get_table_names()
{
    global $wpdb;
    return [
        'elections' => $wpdb->prefix . 'election_entries',
        'candidates' => $wpdb->prefix . 'election_candidates',
        'voters' => $wpdb->prefix . 'election_voters',
    ];
}

/**
 * Central capability gate for every state-changing action in this plugin.
 * Kept as its own function so every write path calls the exact same check.
 */
function evp_current_user_can_manage_elections()
{
    return current_user_can('manage_options');
}

/* ------------------------------------------------------------------ */
/* Admin menu                                                          */
/* ------------------------------------------------------------------ */

add_action('admin_menu', 'evp_register_tools_admin_menu');
function evp_register_tools_admin_menu()
{
    add_management_page(
        'Election Management',
        'Election',
        'manage_options',
        'election-manager',
        'evp_render_tools_admin_page'
    );
}

/* ------------------------------------------------------------------ */
/* Form / action handling                                              */
/* ------------------------------------------------------------------ */

function evp_handle_admin_actions()
{
    global $wpdb;
    $tables = evp_get_table_names();

    // Nothing here applies unless one of our own actions was actually
    // submitted, so bail immediately for every other admin_init call.
    $is_evp_post = isset($_POST['evp_save_election_entry']) || isset($_POST['evp_save_candidate']);
    $is_evp_get = isset($_GET['action']) && in_array($_GET['action'], ['delete_election', 'delete_candidate'], true);

    if (!$is_evp_post && !$is_evp_get) {
        return;
    }

    // Defense in depth: the nonce alone is not proof of authorization,
    // since any logged-in user can generate a valid nonce for a known
    // action name. Capability is what actually gates the write.
    if (!evp_current_user_can_manage_elections()) {
        wp_die(esc_html__('You do not have permission to perform this action.'), 403);
    }

    if (isset($_POST['evp_save_election_entry']) && check_admin_referer('evp_election_entry_nonce')) {
        $id = isset($_POST['election_id']) ? intval($_POST['election_id']) : 0;
        $name = sanitize_text_field(wp_unslash($_POST['election_name']));
        $position = sanitize_text_field(wp_unslash($_POST['election_position']));

        if ($id > 0) {
            $wpdb->update($tables['elections'], ['name' => $name, 'position' => $position], ['id' => $id]);
            $redirect = add_query_arg(['page' => 'election-manager', 'notice' => 'election_updated'], admin_url('tools.php'));
        } else {
            $wpdb->insert($tables['elections'], ['name' => $name, 'position' => $position]);
            $redirect = add_query_arg(['page' => 'election-manager', 'notice' => 'election_created'], admin_url('tools.php'));
        }
        wp_safe_redirect($redirect);
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete_election' && isset($_GET['id'])) {
        check_admin_referer('evp_delete_election_action');
        $id = intval($_GET['id']);
        $wpdb->delete($tables['elections'], ['id' => $id]);
        $wpdb->delete($tables['candidates'], ['election_id' => $id]); // Cascade delete candidates
        wp_safe_redirect(add_query_arg(['page' => 'election-manager', 'notice' => 'election_deleted'], admin_url('tools.php')));
        exit;
    }

    if (isset($_POST['evp_save_candidate']) && check_admin_referer('evp_candidate_nonce')) {
        $id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
        $election_id = intval($_POST['election_id']);
        $name = sanitize_text_field(wp_unslash($_POST['candidate_name']));
        $description = sanitize_text_field(wp_unslash($_POST['candidate_description']));
        $manifesto = wp_kses_post(wp_unslash($_POST['candidate_manifesto']));
        $user_id = isset($_POST['candidate_user_id']) ? intval($_POST['candidate_user_id']) : 0;

        // Make sure the target election actually exists before attaching
        // a candidate to it.
        $election_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['elections']} WHERE id = %d", $election_id));
        if (!$election_exists) {
            wp_die(esc_html__('Invalid election.'), 400);
        }

        $data = [
            'election_id' => $election_id,
            'name' => $name,
            'description' => $description,
            'manifesto' => $manifesto,
            'user_id' => $user_id,
        ];

        if ($id > 0) {
            $wpdb->update($tables['candidates'], $data, ['id' => $id]);
            $notice = 'candidate_updated';
        } else {
            $wpdb->insert($tables['candidates'], $data);
            $notice = 'candidate_created';
        }
        wp_safe_redirect(add_query_arg(
            ['page' => 'election-manager', 'action' => 'manage_candidates', 'id' => $election_id, 'notice' => $notice],
            admin_url('tools.php')
        ));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete_candidate' && isset($_GET['id']) && isset($_GET['election_id'])) {
        check_admin_referer('evp_delete_candidate_action');
        $id = intval($_GET['id']);
        $election_id = intval($_GET['election_id']);
        $wpdb->delete($tables['candidates'], ['id' => $id, 'election_id' => $election_id]);
        wp_safe_redirect(add_query_arg(
            ['page' => 'election-manager', 'action' => 'manage_candidates', 'id' => $election_id, 'notice' => 'candidate_deleted'],
            admin_url('tools.php')
        ));
        exit;
    }
}
add_action('admin_init', 'evp_handle_admin_actions');

/* ------------------------------------------------------------------ */
/* Rendering                                                            */
/* ------------------------------------------------------------------ */

function evp_render_tools_admin_page()
{
    // Defense in depth alongside the 'manage_options' capability already
    // required by add_management_page().
    if (!evp_current_user_can_manage_elections()) {
        wp_die(esc_html__('You do not have permission to access this page.'), 403);
    }

    global $wpdb;
    $tables = evp_get_table_names();
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';

    if (isset($_GET['notice'])) {
        $notice_key = sanitize_key(wp_unslash($_GET['notice']));
        $message = '';
        $type = 'updated';
        if ($notice_key === 'election_created')
            $message = 'Election entry created successfully.';
        if ($notice_key === 'election_updated')
            $message = 'Election entry updated successfully.';
        if ($notice_key === 'election_deleted') {
            $message = 'Election entry and associated candidates deleted.';
            $type = 'error';
        }
        if ($notice_key === 'candidate_created')
            $message = 'Candidate profile registered successfully.';
        if ($notice_key === 'candidate_updated')
            $message = 'Candidate profile modified successfully.';
        if ($notice_key === 'candidate_deleted') {
            $message = 'Candidate profile deleted.';
            $type = 'error';
        }
        if ($message !== '') {
            echo '<div class="' . esc_attr($type) . ' notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    echo '<div class="wrap"><h1>Election Infrastructure Dashboard</h1>';

    if ($action === 'manage_candidates') {
        $election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $election = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['elections']} WHERE id = %d", $election_id));

        if (!$election) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Election not found.') . '</p></div>';
            echo '<p><a class="button" href="' . esc_url(admin_url('tools.php?page=election-manager')) . '">&larr; Back to Elections</a></p>';
            echo '</div>';
            return;
        }

        $candidates = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['candidates']} WHERE election_id = %d", $election_id));
        $cand_edit_id = isset($_GET['edit_cand']) ? intval($_GET['edit_cand']) : 0;
        $editing_cand = $cand_edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['candidates']} WHERE id = %d AND election_id = %d", $cand_edit_id, $election_id)) : null;
        ?>
        <h2>Candidates running for: <?php echo esc_html($election->name); ?> (<?php echo esc_html($election->position); ?>)</h2>
        <p><a class="button" href="<?php echo esc_url(admin_url('tools.php?page=election-manager')); ?>">&larr; Back to
                Elections</a></p>

        <div style="display: flex; gap: 20px;">
            <!-- Candidate Creation/Editing Card Form -->
            <div
                style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; height: fit-content;">
                <h3><?php echo $editing_cand ? 'Modify Candidate Info' : 'Register New Candidate'; ?></h3>
                <form method="POST" action="">
                    <?php wp_nonce_field('evp_candidate_nonce'); ?>
                    <input type="hidden" name="election_id" value="<?php echo esc_attr($election_id); ?>">
                    <input type="hidden" name="candidate_id" value="<?php echo esc_attr($cand_edit_id); ?>">
                    <p><label><strong>Candidate Name:</strong><br><input type="text" name="candidate_name"
                                value="<?php echo $editing_cand ? esc_attr($editing_cand->name) : ''; ?>" required
                                class="large-text"></label></p>
                    <p><label><strong>Short Description / Catchphrase:</strong><br><input type="text"
                                name="candidate_description"
                                value="<?php echo $editing_cand ? esc_attr($editing_cand->description) : ''; ?>"
                                class="large-text"></label></p>
                    <p><label><strong>Manifesto
                                Statement:</strong><br><?php $content = $editing_cand ? $editing_cand->manifesto : '';
                                wp_editor($content, 'candidate_manifesto', ['textarea_rows' => 6, 'media_buttons' => false]); ?></label>
                    </p>
                    <p><label><strong>WordPress User ID Mapping (Optional):</strong><br><input type="number"
                                name="candidate_user_id"
                                value="<?php echo $editing_cand ? esc_attr(intval($editing_cand->user_id)) : 0; ?>"
                                class="small-text"></label></p>
                    <input type="submit" name="evp_save_candidate" class="button button-primary"
                        value="<?php echo $editing_cand ? 'Update Profile' : 'Add Candidate'; ?>">
                    <?php if ($editing_cand): ?>
                        <a style="margin-left: 8px; line-height: 28px;"
                            href="<?php echo esc_url(admin_url('tools.php?page=election-manager&action=manage_candidates&id=' . $election_id)); ?>">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <div style="flex: 1.5;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;">ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="4">No candidates registered for this position yet.</td>
                            </tr>
                        <?php else:
                            foreach ($candidates as $cand): ?>
                                <tr>
                                    <td><?php echo intval($cand->id); ?></td>
                                    <td><strong><?php echo esc_html($cand->name); ?></strong></td>
                                    <td><?php echo esc_html($cand->description); ?></td>
                                    <td>
                                        <a class="button button-small"
                                            href="<?php echo esc_url(admin_url('tools.php?page=election-manager&action=manage_candidates&id=' . $election_id . '&edit_cand=' . $cand->id)); ?>">Edit</a>
                                        <a class="button button-small" style="color:#b32d2e;"
                                            href="<?php echo esc_url(wp_nonce_url(admin_url('tools.php?page=election-manager&action=delete_candidate&id=' . $cand->id . '&election_id=' . $election_id), 'evp_delete_candidate_action')); ?>"
                                            onclick="return confirm('Are you sure you want to remove this candidate?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    } else {
        // Primary Core Route: Render the Election Slots and Creation Interface
        $edit_id = isset($_GET['edit_election']) ? intval($_GET['edit_election']) : 0;
        $editing_election = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['elections']} WHERE id = %d", $edit_id)) : null;
        $elections = $wpdb->get_results("SELECT * FROM {$tables['elections']} ORDER BY id DESC");
        ?>
        <div style="display: flex; gap: 20px;">
            <!-- Election Creation / Editing Card Form -->
            <div
                style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; height: fit-content;">
                <h3><?php echo $editing_election ? 'Edit Election Details' : 'Launch New Election Entry'; ?></h3>
                <form method="POST" action="">
                    <?php wp_nonce_field('evp_election_entry_nonce'); ?>
                    <input type="hidden" name="election_id" value="<?php echo esc_attr($edit_id); ?>">
                    <p><label><strong>Election Group Name:</strong><br><input type="text" name="election_name"
                                value="<?php echo $editing_election ? esc_attr($editing_election->name) : ''; ?>"
                                placeholder="e.g. 2026 Annual General Vote" required class="large-text"></label></p>
                    <p><label><strong>Target Position Role:</strong><br><input type="text" name="election_position"
                                value="<?php echo $editing_election ? esc_attr($editing_election->position) : ''; ?>"
                                placeholder="e.g. Chief Executive Officer" required class="large-text"></label></p>
                    <input type="submit" name="evp_save_election_entry" class="button button-primary"
                        value="<?php echo $editing_election ? 'Update Entry' : 'Create Entry'; ?>">
                    <?php if ($editing_election): ?>
                        <a style="margin-left: 8px; line-height: 28px;"
                            href="<?php echo esc_url(admin_url('tools.php?page=election-manager')); ?>">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Existing Elections Display Table -->
            <div style="flex: 1.5;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Election Campaign</th>
                            <th>Target Position</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($elections)): ?>
                            <tr>
                                <td colspan="4">No election configurations setup yet. Use the control card to add entries.</td>
                            </tr>
                        <?php else:
                            foreach ($elections as $row): ?>
                                <tr>
                                    <td><?php echo intval($row->id); ?></td>
                                    <td><strong><?php echo esc_html($row->name); ?></strong></td>
                                    <td><span class="post-state"><?php echo esc_html($row->position); ?></span></td>
                                    <td>
                                        <a class="button button-small button-primary"
                                            href="<?php echo esc_url(admin_url('tools.php?page=election-manager&action=manage_candidates&id=' . $row->id)); ?>">Candidates</a>
                                        <a class="button button-small"
                                            href="<?php echo esc_url(admin_url('tools.php?page=election-manager&edit_election=' . $row->id)); ?>">Edit</a>
                                        <a class="button button-small" style="color:#b32d2e;"
                                            href="<?php echo esc_url(wp_nonce_url(admin_url('tools.php?page=election-manager&action=delete_election&id=' . $row->id), 'evp_delete_election_action')); ?>"
                                            onclick="return confirm('Warning! Deleting this election will remove all assigned candidates. Proceed?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    echo '</div>'; // End Wrap Container
}
