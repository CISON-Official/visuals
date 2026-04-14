<?php
/**
 * Design for viewing all certificates
 */

add_action('bp_setup_nav', 'add_certificate_to_profile_tag', 100);

function add_certificate_to_profile_tag()
{
    bp_core_new_nav_item(array(
        'name' => __('Certificates', 'textdomain'),
        'slug' => 'certificates-section',
        'position' => 100,
        'screen_function' => 'view_certificates_screen',
        'default_subnav_slug' => 'certificates-section',
        'item_css_id' => 'certificates_section_style'
    ));
}

function view_certificates_screen()
{
    add_action('bp_template_content', 'certificates_links_content');
    bp_core_load_template('members/single/plugins');
}

/**
 * Get all certificates for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return array Array of certificate objects.
 */
function bbc_get_user_certificates(int $user_id): array
{
    global $wpdb;

    $table = $wpdb->prefix . 'user_certificates';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created DESC",
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

/**
 * Renders the certificates list as a buffered HTML string.
 *
 * Each row shows: thumbnail preview on the left, then name,
 * issued date, expiry date, and a View button on the right.
 *
 * @param array $certificates  Array of certificate objects from DB.
 * @param int   $count         Total number of certificates.
 *
 * @return string  The fully rendered HTML.
 */
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

                $is_expired = !empty($cert->expire_date)
                    && strtotime($cert->expire_date) < time();

                $issued_label = date_i18n(
                    get_option('date_format'),
                    strtotime($cert->created)
                );

                $expire_label = !empty($cert->expire_date)
                    ? date_i18n(get_option('date_format'), strtotime($cert->expire_date))
                    : __('No expiry', 'buddyboss-certificates');

                // Decide preview: use the cert path if it's an image,
                // otherwise fall back to a generic certificate icon.
                $ext = strtolower(pathinfo($cert->certificate_path, PATHINFO_EXTENSION));
                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);

                ?>

                <li class="bbc-list-row <?php echo $is_expired ? 'bbc-list-row--expired' : ''; ?>">

                    <!-- LEFT: preview thumbnail -->
                    <div class="bbc-list-preview">
                        <?php if ($is_image): ?>
                            <img src="<?php echo esc_url($cert->certificate_path); ?>" alt="<?php echo esc_attr($cert->name); ?>"
                                class="bbc-list-thumb" loading="lazy" />
                        <?php else: ?>
                            <div class="bbc-list-thumb bbc-list-thumb--placeholder" aria-hidden="true">
                                <span class="bbc-list-thumb-icon">📄</span>
                            </div>
                        <?php endif; ?>

                        <!-- Active / Expired pill overlaid on preview -->
                        <span class="bbc-badge <?php echo $is_expired ? 'bbc-badge--expired' : 'bbc-badge--active'; ?>">
                            <?php echo $is_expired
                                ? esc_html__('Expired', 'buddyboss-certificates')
                                : esc_html__('Active', 'buddyboss-certificates');
                            ?>
                        </span>
                    </div>

                    <!-- RIGHT: details -->
                    <div class="bbc-list-details">

                        <h3 class="bbc-list-name"><?php echo esc_html($cert->name); ?></h3>

                        <div class="bbc-list-meta">
                            <div class="bbc-list-meta-item">
                                <span class="bbc-meta-label">
                                    <?php esc_html_e('Issued', 'buddyboss-certificates'); ?>
                                </span>
                                <span class="bbc-meta-value">
                                    <?php echo esc_html($issued_label); ?>
                                </span>
                            </div>

                            <div class="bbc-list-meta-item">
                                <span class="bbc-meta-label">
                                    <?php esc_html_e('Expires', 'buddyboss-certificates'); ?>
                                </span>
                                <span class="bbc-meta-value <?php echo $is_expired ? 'bbc-text--expired' : ''; ?>">
                                    <?php echo esc_html($expire_label); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($cert->certificate_path)): ?>

                            href="<?php echo esc_url($cert->certificate_path); ?>"
                            class="bbc-view-btn"
                            target="_blank"
                            rel="noopener noreferrer"
                            >
                            <?php esc_html_e('View Certificate', 'buddyboss-certificates'); ?>
                            </a>
                        <?php endif; ?>

                    </div><!-- .bbc-list-details -->

                </li>

            <?php endforeach; ?>

        </ul><!-- .bbc-list -->

    <?php endif;

    return ob_get_clean();
}
