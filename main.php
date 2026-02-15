<?php
/**
 * Plugin Name: Visuals
 * Description: visuals for CISON WordPress Application
 * Version: 1.0.0
 * Author: cison
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAIN_PATH', plugin_dir_path(__FILE__));

require_once("src/PRS/corporate.php");
require_once("src/PRS/student.php");
require_once("src/PRS/company.php");



