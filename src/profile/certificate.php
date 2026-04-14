<?php
/**
 * Design for viewing all certificates
 */

add_action('bp_setup_nav', 'add_certificate_to_profile_tag', 100);

function add_certificate_to_profile_tag()
{

    bp_core_new_nav_item(array(
        'name' => __('Certificates', 'textdomain'),
        'slug' => 'certificates',
        'position' => 10,
        'screen_function' => 'view_certificates_screen', // This must match the function name
        'default_subnav_slug' => 'certificates-section',
        'item_css_id' => 'certificates_section_style'
    ));
}

function view_certificates_screen()
{
    // Essential: Tell BuddyPress this screen is handled
    add_action('bp_template_content', 'certificates_links_content');

    // This loads the standard profile wrapper
    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}

function bbc_get_user_certificates(int $user_id): array
{
    global $wpdb;
    // Ensure table name is correct (your previous SQL prompt used 'user_certificates')
    $table = $wpdb->prefix . 'user_certificates';

    // Verify table exists to prevent silent DB failures
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", // Note: changed 'created' to 'created_at' based on your previous SQL schema
            $user_id
        )
    );
}

function certificates_links_content()
{
    $displayed_user_id = bp_displayed_user_id();
    if (!$displayed_user_id)
        return;

    $certificates = bbc_get_user_certificates($displayed_user_id);
    $count = count($certificates);

    echo list_certificates_content_template($certificates, $count);
}

function list_certificates_content_template(array $certificates, int $count): string
{
    ob_start();

    if ($count === 0 || empty($certificates)): ?>
        <div class="bbc-empty-state">
            <span class="bbc-empty-icon">🎓</span>
            <p class="bbc-empty-text">
                <?php esc_html_e('No certificates found.', 'buddyboss-certificates'); ?>
            </p>
        </div>
    <?php else: ?>
        <ul class="bbc-list">
            <?php foreach ($certificates as $cert):
                // Handle JSON if 'certs' is the column (from our previous SQL conversation)
                // If $cert->certs exists and is a JSON string, decode it
                $cert_data = is_string($cert->certs) ? json_decode($cert->certs) : $cert;

                $is_expired = !empty($cert->expire_date) && strtotime($cert->expire_date) < time();
                $issued_label = date_i18n(get_option('date_format'), strtotime($cert->created_at));
                $expire_label = !empty($cert->expire_date) ? date_i18n(get_option('date_format'), strtotime($cert->expire_date)) : __('No expiry', 'buddyboss-certificates');

                $path = $cert->certificate_path ?? '';
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
                ?>

                <li class="bbc-list-row <?php echo $is_expired ? 'bbc-list-row--expired' : ''; ?>">
                    <div class="bbc-list-preview">
                        <?php if ($is_image && !empty($path)): ?>
                            <img src="<?php echo esc_url($path); ?>" alt="<?php echo esc_attr($cert->name); ?>" class="bbc-list-thumb"
                                loading="lazy" />
                        <?php else: ?>
                            <div class="bbc-list-thumb bbc-list-thumb--placeholder" aria-hidden="true">
                                <span class="bbc-list-thumb-icon">📄</span>
                            </div>
                        <?php endif; ?>
                        <span class="bbc-badge <?php echo $is_expired ? 'bbc-badge--expired' : 'bbc-badge--active'; ?>">
                            <?php echo $is_expired ? esc_html__('Expired', 'buddyboss-certificates') : esc_html__('Active', 'buddyboss-certificates'); ?>
                        </span>
                    </div>

                    <div class="bbc-list-details">
                        <h3 class="bbc-list-name"><?php echo esc_html($cert->name); ?></h3>
                        <div class="bbc-list-meta">
                            <div class="bbc-list-meta-item">
                                <span class="bbc-meta-label"><?php esc_html_e('Issued', 'buddyboss-certificates'); ?></span>
                                <span class="bbc-meta-value"><?php echo esc_html($issued_label); ?></span>
                            </div>
                            <div class="bbc-list-meta-item">
                                <span class="bbc-meta-label"><?php esc_html_e('Expires', 'buddyboss-certificates'); ?></span>
                                <span
                                    class="bbc-meta-value <?php echo $is_expired ? 'bbc-text--expired' : ''; ?>"><?php echo esc_html($expire_label); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($path)): ?>
                            <a href="<?php echo esc_url($path); ?>" class="bbc-view-btn" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('View Certificate', 'buddyboss-certificates'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;

    return ob_get_clean();
}