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

/**
 * Ensures the database table is created or updated.
 * We use a wrapper to ensure dbDelta logic is available.
 */
function visuals_init_database() {
    if (function_exists('create_databases')) {
        create_databases();
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'visuals_init_database');

// 3. Core Database Logic
require_once VISUALS_PATH . 'src/db/conference.php';

// 4. PRS (Professional Registration System) Modules
require_once VISUALS_PATH . 'src/PRS/corporate.php';
require_once VISUALS_PATH . 'src/PRS/student.php';
require_once VISUALS_PATH . 'src/PRS/company.php';

// 5. Profile & Security Modules
require_once VISUALS_PATH . 'src/profile/email.php';
require_once VISUALS_PATH . 'src/profile/certificate.php';
require_once VISUALS_PATH . 'src/profile/secure.php';
require_once VISUALS_PATH . 'src/profile/conference.php';

// 6. User Forms
require_once VISUALS_PATH . 'src/forms/conference.php';