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
    add_action('bp_template_content', 'secure_links_content');
    bp_core_load_template('members/single/plugins');
}

function secure_links_content() {
    echo list_secure_links_content_template();
}

function list_secure_links_content_template() {
    ob_start();
    ?>
    <div class="u-6d3e91a2">
        <header class="u-b5c412f8">
            <h2><i class="fas fa-database"></i> 2025 Attendance & Records</h2>
            <p>Access the master tables for certificates and registration data.</p>
        </header>

        <ul class="u-8b4e1350">
            <li class="u-2f9a71d2">
                <a href="/admin/preconference-attendees" class="u-f4e19b22">
                    <div class="u-1d3c5b7a"><i class="fas fa-users-cog"></i></div>
                    <div class="u-e9b2c8f1">
                        <h4>Preconference Attendees</h4>
                        <p>Master table of all participants and issued certificates for preconference sessions.</p>
                        <span class="u-a73c91eb">View Attendee Table <i class="fas fa-table"></i></span>
                    </div>
                </a>
            </li>

            <li class="u-2f9a71d2">
                <a href="/admin/conference-attendees" class="u-f4e19b22">
                    <div class="u-1d3c5b7a"><i class="fas fa-users"></i></div>
                    <div class="u-e9b2c8f1">
                        <h4>Conference Attendees</h4>
                        <p>Full database of 2025 conference attendees and certificate verification status.</p>
                        <span class="u-a73c91eb">View Attendee Table <i class="fas fa-table"></i></span>
                    </div>
                </a>
            </li>

            <li class="u-2f9a71d2">
                <a href="/admin/prs-registrants" class="u-f4e19b22">
                    <div class="u-1d3c5b7a"><i class="fas fa-address-book"></i></div>
                    <div class="u-e9b2c8f1">
                        <h4>PRS Registration List</h4>
                        <p>Comprehensive table of all individuals registered via the Professional Registration System.</p>
                        <span class="u-a73c91eb">View Registration Table <i class="fas fa-table"></i></span>
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <style>
    /* Container Wrapper - u-6d3e91a2 */
        .u-6d3e91a2 {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        /* Header Section - u-b5c412f8 */
        .u-b5c412f8 {
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }
        .u-b5c412f8 h2 { color: #1a202c; font-size: 1.8rem; margin-bottom: 8px; }
        .u-b5c412f8 p { color: #718096; }

        /* Grid UL - u-8b4e1350 */
        .u-8b4e1350 {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        /* Link Card - u-f4e19b22 */
        .u-f4e19b22 {
            text-decoration: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            transition: all 0.2s ease-in-out;
        }

        .u-f4e19b22:hover {
            border-color: #3182ce;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background-color: #f7fafc;
        }

        /* Icon Box - u-1d3c5b7a */
        .u-1d3c5b7a {
            width: 54px;
            height: 54px;
            background: #ebf8ff;
            color: #2b6cb0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-right: 18px;
            flex-shrink: 0;
        }

        /* Content Area - u-e9b2c8f1 */
        .u-e9b2c8f1 h4 { margin: 0 0 6px 0; font-size: 1.15rem; color: #2d3748; }
        .u-e9b2c8f1 p { font-size: 0.9rem; color: #4a5568; margin-bottom: 16px; line-height: 1.5; }

        /* CTA Text - u-a73c91eb */
        .u-a73c91eb {
            font-weight: 600;
            color: #3182ce;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #ebf8ff;
            border-radius: 6px;
        }

        .u-f4e19b22:hover .u-a73c91eb {
            background: #3182ce;
            color: #ffffff;
        }

        @media (max-width: 640px) {
            .u-f4e19b22 { flex-direction: column; align-items: flex-start; }
            .u-1d3c5b7a { margin-bottom: 15px; }
        }
    </style>

    
    <?php
    return ob_get_clean();
}

