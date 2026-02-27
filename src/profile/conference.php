<?php
/**
 * Design for viewing all the inputs for Annual Conference and Preconference
 */

add_action('bp_setup_nav', 'add_secure_to_profile_tag', 100);

function add_secure_to_profile_tag() {
    $current_user = wp_get_current_user();

    $allowed_users = array(938, 2459);

    if (!in_array($current_user->ID, $allowed_users)) {
        return;
    }

    // if (bp_is_my_profile()) {
    //     return ;
    // }

    bp_core_new_nav_item(array(
        'name' => __('Secure', 'textdomain'),
        'slug' => 'secure-section',
        'position' => 100,
        'screen_function' => 'view_secure_screen',
        'default_subnav_slug' => 'secure-section',
        'item_css_id' => 'secure_section_style'
    ));
}

function view_secure_screen() {
    add_action('bp_template_content', 'list_secure_links_content_template');
    bb_core_load_template(apply_filters('bp_core_template_loader', 'members/single/plugins'));
}

function list_secure_links_content_template() {
    ob_start();
    ?>
    <div class="secure-links-container">
        <div class="secure-links-header">
            <h2><i class="fas fa-link"></i> Secure Access Links</h2>
            <p>Your personalized links for conference materials and access</p>
        </div>

        <div class="links-grid">
            <!-- Live Stream Link -->
            <div class="secure-link-card live-stream">
                <div class="link-icon">
                    <i class="fas fa-video"></i>
                </div>
                <div class="link-content">
                    <h4>Main Conference Live Stream</h4>
                    <p>3rd Annual NSA Conference - Live Access</p>
                    <?php if (has_access('live_stream')) : ?>
                        <a href="<?php echo get_secure_url('live_stream'); ?>" 
                           class="secure-link-btn primary" target="_blank">
                            <i class="fas fa-play-circle"></i> Watch Live
                        </a>
                    <?php else : ?>
                        <span class="access-denied">Not Available</span>
                    <?php endif; ?>
                </div>
                <div class="link-status <?php echo has_access('live_stream') ? 'active' : 'inactive'; ?>">
                    <?php echo has_access('live_stream') ? 'Active' : 'Locked'; ?>
                </div>
            </div>

            <!-- Workshop Materials -->
            <div class="secure-link-card workshop">
                <div class="link-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="link-content">
                    <h4>Pre-Conference Workshop</h4>
                    <p>Materials & Recordings</p>
                    <?php if (has_access('workshop')) : ?>
                        <div class="link-buttons">
                            <a href="<?php echo get_secure_url('workshop_pdf'); ?>" 
                               class="secure-link-btn secondary" download>
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                            <a href="<?php echo get_secure_url('workshop_video'); ?>" 
                               class="secure-link-btn primary" target="_blank">
                                <i class="fas fa-video"></i> Watch Recording
                            </a>
                        </div>
                    <?php else : ?>
                        <span class="access-denied">Complete Payment to Unlock</span>
                    <?php endif; ?>
                </div>
                <div class="link-status <?php echo has_access('workshop') ? 'active' : 'inactive'; ?>">
                    <?php echo has_access('workshop') ? 'Unlocked' : 'Pending Payment'; ?>
                </div>
            </div>

            <!-- Conference Materials -->
            <div class="secure-link-card materials">
                <div class="link-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="link-content">
                    <h4>Conference Materials</h4>
                    <p>All presentations & handouts</p>
                    <?php if (has_access('conference_materials')) : ?>
                        <a href="<?php echo get_secure_url('materials_zip'); ?>" 
                           class="secure-link-btn primary" download>
                            <i class="fas fa-download"></i> Download All (ZIP)
                        </a>
                    <?php else : ?>
                        <span class="access-denied">Conference Access Required</span>
                    <?php endif; ?>
                </div>
                <div class="link-status <?php echo has_access('conference_materials') ? 'active' : 'inactive'; ?>">
                    <?php echo has_access('conference_materials') ? 'Ready' : 'Pending'; ?>
                </div>
            </div>

            <!-- Certificate -->
            <div class="secure-link-card certificate">
                <div class="link-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="link-content">
                    <h4>Attendance Certificate</h4>
                    <p>Official NSA Certificate</p>
                    <?php if (has_access('certificate')) : ?>
                        <a href="<?php echo get_secure_url('certificate'); ?>" 
                           class="secure-link-btn success" target="_blank">
                            <i class="fas fa-print"></i> View & Print Certificate
                        </a>
                    <?php else : ?>
                        <span class="access-denied">Available After Event</span>
                    <?php endif; ?>
                </div>
                <div class="link-status <?php echo has_access('certificate') ? 'active' : 'inactive'; ?>">
                    <?php echo has_access('certificate') ? 'Issued' : 'Post-Event'; ?>
                </div>
            </div>
        </div>

        <!-- Access Summary -->
        <div class="access-summary">
            <h3>Your Access Level</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <span>Member ID:</span>
                    <strong><?php echo get_current_member_id(); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Registration:</span>
                    <strong><?php echo get_registration_type(); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Total Items:</span>
                    <strong><?php echo count_available_links(); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <style>
    .secure-links-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .secure-links-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .secure-links-header h2 {
        color: #1a1a1a;
        margin-bottom: 10px;
        font-size: 2.5em;
    }
    .links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }
    .secure-link-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        border-left: 5px solid #007cba;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .secure-link-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
    }
    .link-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #007cba, #005a87);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        margin-bottom: 15px;
        float: left;
    }
    .link-content {
        margin-left: 75px;
        min-height: 100px;
    }
    .link-content h4 {
        margin: 0 0 8px 0;
        color: #1a1a1a;
        font-size: 1.3em;
    }
    .secure-link-btn {
        display: inline-block;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 10px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }
    .secure-link-btn.primary {
        background: linear-gradient(135deg, #007cba, #005a87);
        color: white;
    }
    .secure-link-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,124,186,0.4);
    }
    .secure-link-btn.secondary {
        background: #f8f9fa;
        color: #495057;
        border: 2px solid #dee2e6;
    }
    .secure-link-btn.success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }
    .access-denied {
        display: block;
        padding: 12px 20px;
        background: #fff3cd;
        color: #856404;
        border-radius: 8px;
        font-weight: 500;
        margin-top: 10px;
    }
    .link-status {
        position: absolute;
        top: 20px;
        right: 20px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .link-status.active {
        background: #d4edda;
        color: #155724;
    }
    .link-status.inactive {
        background: #f8d7da;
        color: #721c24;
    }
    .access-summary {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 30px;
        border-radius: 15px;
        text-align: center;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .summary-item span {
        color: #6c757d;
        display: block;
    }
    @media (max-width: 768px) {
        .links-grid {
            grid-template-columns: 1fr;
        }
        .link-content {
            margin-left: 0;
            text-align: center;
        }
        .link-icon {
            float: none;
            margin: 0 auto 15px;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('.secure-link-btn').on('click', function() {
            $(this).find('i').removeClass('fa-play-circle fa-download fa-print')
                           .addClass('fa-spinner fa-spin');
            setTimeout(() => {
                $(this).find('i').removeClass('fa-spinner fa-spin')
                               .addClass('fa-check-circle');
            }, 1500);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

