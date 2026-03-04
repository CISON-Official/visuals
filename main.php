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
function visuals_init_database()
{
    if (function_exists('create_databases')) {
        create_databases();
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'visuals_init_database');
add_action('admin_init', 'visuals_init_database');

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


add_action( 'bp_template_redirect', 'cison_custom_guest_access_control', 1 );

function cison_custom_guest_access_control() {
    // 1. If the user is already logged in, do nothing
    if ( is_user_logged_in() ) {
        return;
    }

    // 2. Define your whitelist of public URIs
    $public_uris = array(
        '/checkout/',
        '/checkout/order-received/',
        '/register/',
        '/activate/',
        '/login/'
    );

    // 3. Get the current request URI
    $current_uri = $_SERVER['REQUEST_URI'];

    // 4. Check if the current URI starts with any of our whitelisted paths
    $is_allowed = false;
    foreach ( $public_uris as $uri ) {
        if ( strpos( $current_uri, $uri ) !== false ) {
            $is_allowed = true;
            break;
        }
    }

    // 5. If not allowed and not a standard WP login page, redirect to login
    if ( ! $is_allowed && ! is_page('login') && ! strpos($current_uri, 'wp-login.php') ) {
        // Use the BuddyBoss specific "no access" redirect
        bp_core_no_access( array(
            'root'     => home_url( '/login/' ), // Change to your specific login page slug
            'redirect' => home_url( $current_uri ),
            'mode'     => 1 // 1 = Redirect to login
        ) );
    }
}