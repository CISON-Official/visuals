<?php

define('EVP_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));

if (file_exists(EVP_PLUGIN_DIR_PATH . 'database.php')) {
    require_once EVP_PLUGIN_DIR_PATH . 'database.php';
}

// if (function_exists('evp_initialize_election_database')) {
    // register_activation_hook(__FILE__, 'evp_initialize_election_database');
// }

if (file_exists(EVP_PLUGIN_DIR_PATH . 'admin.php')) {
    require_once EVP_PLUGIN_DIR_PATH . 'admin.php';
}

if (file_exists(EVP_PLUGIN_DIR_PATH . 'shortcode.php')) {
    require_once EVP_PLUGIN_DIR_PATH . 'shortcode.php';
}

if (file_exists(EVP_PLUGIN_DIR_PATH . 'results.php')) {
    require_once EVP_PLUGIN_DIR_PATH . 'results.php';
}
