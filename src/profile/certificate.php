<?php
/**
 * Certificates Profile Tab (BuddyPress / BuddyBoss)
 */

add_action('bp_setup_nav', 'add_certificate_to_profile_tag', 20);

function add_certificate_to_profile_tag()
{
    bp_core_new_nav_item([
        'name' => __('Certificates', 'textdomain'),
        'slug' => 'profile/certificates',
        'position' => 55,
        'screen_function' => 'view_certificates_screen',
        'default_subnav_slug' => 'certificates',
        'item_css_id' => 'certificates_section_style'
    ]);
}

function view_certificates_screen()
{
    add_action('bp_template_content', 'certificates_links_content');
    bp_core_load_template('members/single/profile');
    error_log("Trying to run this");
}

/**
 * Fetch user certificates
 */
function bbc_get_user_certificates(int $user_id): array
{
    global $wpdb;

    $table = $wpdb->prefix . 'certificate_registry';

    // Check table exists
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        )
    ) ?: [];
}

/**
 * Render content
 */
function certificates_links_content()
{
    echo list_certificates_content_template();
}

/**
 * Template output
 */

function list_certificates_content_template(): string
{
 
    $displayed_user_id = bp_displayed_user_id();
 
    if (!$displayed_user_id) {
        return '';
    }
 
    $certificates = bbc_get_user_certificates($displayed_user_id);
    $count = count($certificates);
 
    ob_start();
    ?>
 
    <?php if ($count === 0): ?>
        <div class="bbc-empty-state">
            <span class="bbc-empty-icon">🎓</span>
            <p class="bbc-empty-text">
                <?php esc_html_e('No certificates found.', 'buddyboss-certificates'); ?>
            </p>
        </div>
    <?php else: ?>
        <table class="bbc-table">
            <thead>
                <tr>
                    <th class="bbc-table-col-preview"><?php esc_html_e('', 'buddyboss-certificates'); ?></th>
                    <th class="bbc-table-col-name"><?php esc_html_e('Certificate', 'buddyboss-certificates'); ?></th>
                    <th class="bbc-table-col-status"><?php esc_html_e('Status', 'buddyboss-certificates'); ?></th>
                    <th class="bbc-table-col-issued"><?php esc_html_e('Issued', 'buddyboss-certificates'); ?></th>
                    <th class="bbc-table-col-expires"><?php esc_html_e('Expires', 'buddyboss-certificates'); ?></th>
                    <th class="bbc-table-col-action"><?php esc_html_e('', 'buddyboss-certificates'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $cert):
 
                    $path = $cert->certificate_path ?? '';
                    $name = $cert->name ?? __('Untitled Certificate', 'buddyboss-certificates');
 
                    $created_at = $cert->created_at ?? '';
                    $expire_at = $cert->expire_date ?? '';
 
                    $is_expired = !empty($expire_at) && strtotime($expire_at) < time();
 
                    $issued_label = !empty($created_at)
                        ? date_i18n(get_option('date_format'), strtotime($created_at))
                        : __('Unknown', 'buddyboss-certificates');
 
                    $expire_label = !empty($expire_at)
                        ? date_i18n(get_option('date_format'), strtotime($expire_at))
                        : __('No expiry', 'buddyboss-certificates');
 
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
                    ?>
 
                    <tr class="bbc-table-row <?php echo $is_expired ? 'bbc-table-row--expired' : ''; ?>">
 
                        <td class="bbc-table-col-preview" data-label="<?php esc_attr_e('Preview', 'buddyboss-certificates'); ?>">
                            <?php if ($is_image && !empty($path)): ?>
                                <img src="<?php echo esc_url($path); ?>" alt="<?php echo esc_attr($name); ?>" class="bbc-table-thumb"
                                    loading="lazy" />
                            <?php else: ?>
                                <div class="bbc-table-thumb bbc-table-thumb--placeholder" aria-hidden="true">
                                    <span class="bbc-table-thumb-icon">📄</span>
                                </div>
                            <?php endif; ?>
                        </td>
 
                        <td class="bbc-table-col-name" data-label="<?php esc_attr_e('Certificate', 'buddyboss-certificates'); ?>">
                            <span class="bbc-table-name"><?php echo esc_html($name); ?></span>
                        </td>
 
                        <td class="bbc-table-col-status" data-label="<?php esc_attr_e('Status', 'buddyboss-certificates'); ?>">
                            <span class="bbc-badge <?php echo $is_expired ? 'bbc-badge--expired' : 'bbc-badge--active'; ?>">
                                <?php echo $is_expired
                                    ? esc_html__('Expired', 'buddyboss-certificates')
                                    : esc_html__('Active', 'buddyboss-certificates'); ?>
                            </span>
                        </td>
 
                        <td class="bbc-table-col-issued" data-label="<?php esc_attr_e('Issued', 'buddyboss-certificates'); ?>">
                            <?php echo esc_html($issued_label); ?>
                        </td>
 
                        <td class="bbc-table-col-expires" data-label="<?php esc_attr_e('Expires', 'buddyboss-certificates'); ?>">
                            <span class="<?php echo $is_expired ? 'bbc-text--expired' : ''; ?>">
                                <?php echo esc_html($expire_label); ?>
                            </span>
                        </td>
 
                        <td class="bbc-table-col-action" data-label="<?php esc_attr_e('Action', 'buddyboss-certificates'); ?>">
                            <?php if (!empty($path)): ?>
                                <a href="<?php echo esc_url($path); ?>" class="bbc-view-btn" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('View', 'buddyboss-certificates'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
 
                    </tr>
 
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
 
    <?php
    return ob_get_clean();
}
 