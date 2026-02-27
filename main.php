<?php
/**
 * Plugin Name: Visuals
 * Description: Visuals for CISON WordPress Application
 * Version: 1.0.0
 * Author: CISON
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define main plugin path
define('MAIN_PATH', plugin_dir_path(__FILE__));

// Database Initialization
require_once MAIN_PATH . 'src/db/conference.php';

// Include required files
require_once MAIN_PATH . 'src/PRS/corporate.php';
require_once MAIN_PATH . 'src/PRS/student.php';
require_once MAIN_PATH . 'src/PRS/company.php';

require_once MAIN_PATH . 'src/profile/email.php';
require_once MAIN_PATH . 'src/profile/certificate.php';
require_once MAIN_PATH . 'src/profile/conference.php';

require_once MAIN_PATH . 'src/forms/conference.php';

