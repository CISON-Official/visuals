<?php
/**
 * Plugin Name: Visuals
 * Description: Visuals for CISON WordPress Application
 * Version:     1.0.0
 * Author:      CISON
 * Text Domain: visuals-cison
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Define Constants for clean path referencing
define('VISUALS_PATH', plugin_dir_path(__FILE__));
define('VISUALS_URL', plugin_dir_url(__FILE__));

// 2. Database & Schema Initialization
// Note: We include the file first so the function is available for the hook
require_once VISUALS_PATH . 'src/database.php';
require_once VISUALS_PATH . 'src/PRS/display.php';

/**
 * Ensures the database table is created or updated.
 * We use a wrapper to ensure dbDelta logic is available.
 */
function visuals_init_database()
{
    if (function_exists('create_databases')) {
        create_databases();
    }
    // new DisplayPRSDetails();
}

// Register activation hook
register_activation_hook(__FILE__, 'visuals_init_database');
add_action('admin_init', 'visuals_init_database');

// 3. Core Database Logic
require_once VISUALS_PATH . 'src/db/conference.php';
require_once VISUALS_PATH . 'src/db/examination.php';

// 4. PRS (Professional Registration System) Modules
require_once VISUALS_PATH . 'src/PRS/corporate.php';
require_once VISUALS_PATH . 'src/PRS/student.php';
require_once VISUALS_PATH . 'src/PRS/company.php';
require_once VISUALS_PATH . 'src/PRS/remaining.php';

// 5. Profile & Security Modules
require_once VISUALS_PATH . 'src/profile/email.php';
// require_once VISUALS_PATH . 'src/profile/certificate.php';
require_once VISUALS_PATH . 'src/profile/secure.php';
require_once VISUALS_PATH . 'src/profile/conference.php';

// 6. User Forms
require_once VISUALS_PATH . 'src/forms/conference.php';
require_once VISUALS_PATH . 'src/forms/mock-examination.php';
require_once VISUALS_PATH . 'src/forms/organisation_conference.php';

// Authentication
require_once VISUALS_PATH . 'src/auth.php';

// Menu
require_once VISUALS_PATH . 'src/menu.php';

// Corporate Pages
require_once VISUALS_PATH . 'src/corporate/signuppage.php';
require_once VISUALS_PATH . 'src/corporate/nav.php';
require_once VISUALS_PATH . 'src/corporate/default_member_dir.php';

// add_action('plugins_loaded', function () {
//    new DisplayPRSDetails(); 
// });

require_once VISUALS_PATH . 'src/templates/conference_table.php';
require_once VISUALS_PATH . 'src/student-member-upgrade.php';
require_once VISUALS_PATH . 'src/admin/membership-certificate.php';
require_once VISUALS_PATH . 'src/templates/member-conference.php';
require_once VISUALS_PATH . 'src/election/main.php';
require_once VISUALS_PATH . 'src/election/database.php';
require_once VISUALS_PATH . 'src/election/admin.php';   // <-- add: the ballot form + admin-post handler
require_once VISUALS_PATH . 'src/election/results.php';  // <-- add: the [election_results] chart shortcode