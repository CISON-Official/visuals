<?php
/**
 * Design for viewing all the inputs for Annual Conference and Preconference
 */

add_action('bp_setup_nav', 'add_secure_to_profile_tag', 100);

function add_secure_to_profile_tag()
{
    $current_user = wp_get_current_user();

    $allowed_users = array(938, 2459, 2851);

    if (!in_array($current_user->ID, $allowed_users)) {
        return;
    }

    if (!bp_is_my_profile()) {
        return;
    }

    bp_core_new_nav_item(array(
        'name' => __('Secure', 'textdomain'),
        'slug' => 'secure-section',
        'position' => 100,
        'screen_function' => 'view_secure_screen',
        'default_subnav_slug' => 'secure-section',
        'item_css_id' => 'secure_section_style'
    ));
}

function view_secure_screen()
{
    add_action('bp_template_content', 'secure_links_content');
    bp_core_load_template('members/single/plugins');
}

function secure_links_content()
{
    echo list_secure_links_content_template();
}

function get_secure_links()
{
    return array(
        array(
            'url' => 'https://my.cison.org.ng/verify-certificate/',
            'icon' => 'fa-users-cog',
            'title' => 'Preconference Attendees',
            'description' => 'Master table of all participants and issued certificates for preconference sessions.',
            'action' => 'View Attendee Table',
        ),
        array(
            'url' => 'https://my.cison.org.ng/2025-conference-certificate/',
            'icon' => 'fa-users',
            'title' => 'Conference Attendees',
            'description' => 'Full database of 2025 conference attendees and certificate verification status.',
            'action' => 'View Attendee Table',
        ),
        array(
            'url' => 'https://my.cison.org.ng/corporate-registration/',
            'icon' => 'fa-address-book',
            'title' => 'PRS Registration List',
            'description' => 'Comprehensive table of all individuals registered via the Professional Registration System.',
            'action' => 'View Registration Table',
        ),
        array(
            'url' => 'https://my.cison.org.ng/2026-workshop-preconference-and-conference-registration-list/',
            'icon' => 'fa-address-book',
            'title' => 'Conference Registration List',
            'description' => 'Comprehensive table of all individuals registering for 2026 conference sessions (virtual and on-site).',
            'action' => 'View Registration Table',
        ),
        array(
            'url' => 'https://my.cison.org.ng/prs-registration-list/',
            'icon' => 'fa-address-book',
            'title' => 'Q2 PRS Registration List',
            'description' => 'Comprehensive table of all Q2 PRS.',
            'action' => 'View Table',
        ),
        array(
            'url' => 'https://my.cison.org.ng/fellowship-submissions-2026',
            'icon' => 'fa-user-graduate',
            'title' => 'Fellowship Submissions 2026',
            'description' => 'Table of all fellowship submissions received for 2026.',
            'action' => 'View Table',
        ),
    );
}

function list_secure_links_content_template()
{
    $secure_links = get_secure_links();

    ob_start();
    ?>
    <div class="u-6d3e91a2">
        <header class="u-b5c412f8">
            <h2> View and Access Secure Links</h2>
            <p>Access view and access secure links.</p>
        </header>

        <ul class="u-8b4e1350">
            <?php foreach ($secure_links as $link) : ?>
                <li class="u-2f9a71d2">
                    <a href="<?php echo esc_url($link['url']); ?>" class="u-f4e19b22">
                        <div class="u-1d3c5b7a"><i class="fas <?php echo esc_attr($link['icon']); ?>"></i></div>
                        <div class="u-e9b2c8f1">
                            <h4><?php echo esc_html($link['title']); ?></h4>
                            <p><?php echo esc_html($link['description']); ?></p>
                            <span class="u-a73c91eb"><?php echo esc_html($link['action']); ?> <i class="fas fa-table"></i></span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <style>
        .u-6d3e91a2 {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        .u-b5c412f8 {
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }

        .u-b5c412f8 h2 {
            color: #1a202c;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }

        .u-b5c412f8 p {
            color: #718096;
        }

        .u-8b4e1350 {
            list-style: decimal outside;
            padding-left: 36px;
            margin: 0;
        }

        .u-2f9a71d2 {
            margin-bottom: 16px;
        }

        .u-2f9a71d2:last-child {
            margin-bottom: 0;
        }

        .u-2f9a71d2::marker {
            font-weight: 600;
            color: #3182ce;
        }

        .u-f4e19b22 {
            text-decoration: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            display: block;
            transition: all 0.2s ease-in-out;
        }

        .u-f4e19b22:hover {
            border-color: #3182ce;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background-color: #f7fafc;
        }

        .u-1d3c5b7a {
            width: 54px;
            height: 54px;
            background: #ebf8ff;
            color: #2b6cb0;
            border-radius: 10px;
            font-size: 22px;
            line-height: 54px;
            text-align: center;
            margin-bottom: 14px;
        }

        .u-e9b2c8f1 h4 {
            margin: 0 0 6px 0;
            font-size: 1.15rem;
            color: #2d3748;
        }

        .u-e9b2c8f1 p {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .u-a73c91eb {
            font-weight: 600;
            color: #3182ce;
            font-size: 0.85rem;
            display: inline-block;
            padding: 6px 12px;
            background: #ebf8ff;
            border-radius: 6px;
        }

        .u-a73c91eb i {
            margin-left: 6px;
        }

        .u-f4e19b22:hover .u-a73c91eb {
            background: #3182ce;
            color: #ffffff;
        }
    </style>


    <?php
    return ob_get_clean();
}

