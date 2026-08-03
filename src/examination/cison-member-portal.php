<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * -----------------------------------------------------------------------
 * Constants
 * -----------------------------------------------------------------------
 */
define( 'CMP_VERSION', '1.0.0' );
define( 'CMP_DB_VERSION', '1.0.0' ); // Bump this to trigger table upgrades on next load.
define( 'CMP_PLUGIN_FILE', __FILE__ );
define( 'CMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CMP_TEXT_DOMAIN', 'cison-member-portal' );

/**
 * -----------------------------------------------------------------------
 * Requirement check: WooCommerce must be active.
 * -----------------------------------------------------------------------
 * This plugin intentionally does NOT process card payments itself — every
 * fee invoice it creates is checked out through WooCommerce. If WooCommerce
 * is missing we deactivate ourselves and tell the admin why, rather than
 * silently failing later at checkout time.
 */
function cmp_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'CISON Member Portal requires WooCommerce to be installed and active, since all fee checkouts (exam fees, course fees, exemption processing fees) are handled through WooCommerce orders.', 'cison-member-portal' );
	echo '</p></div>';
}

function cmp_check_requirements() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cmp_woocommerce_missing_notice' );
		return false;
	}
	return true;
}

/**
 * -----------------------------------------------------------------------
 * Includes
 * -----------------------------------------------------------------------
 */
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-db.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-activator.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-deactivator.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-uploads.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-courses.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-examinations.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-exemptions.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-invoices.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-woocommerce.php';
require_once CMP_PLUGIN_DIR . 'includes/class-cmp-shortcodes.php';
require_once CMP_PLUGIN_DIR . 'admin/class-cmp-list-table.php';
require_once CMP_PLUGIN_DIR . 'admin/class-cmp-admin.php';

/**
 * -----------------------------------------------------------------------
 * Activation / Deactivation
 * -----------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'CMP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CMP_Deactivator', 'deactivate' ) );

/**
 * Keep the schema in sync if CMP_DB_VERSION changes in a future plugin update.
 */
function cmp_maybe_upgrade_db() {
	if ( get_option( 'cmp_db_version' ) !== CMP_DB_VERSION ) {
		CMP_Activator::activate();
	}
}
add_action( 'plugins_loaded', 'cmp_maybe_upgrade_db' );

/**
 * -----------------------------------------------------------------------
 * Bootstrap
 * -----------------------------------------------------------------------
 */
function cmp_init_plugin() {
	load_plugin_textdomain( CMP_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! cmp_check_requirements() ) {
		return;
	}

	// Admin area (menus, list tables, edit screens, row actions).
	new CMP_Admin();

	// Front-end shortcodes (catalog, apply forms, registration, exemptions, tracking).
	new CMP_Shortcodes();

	// WooCommerce order <-> invoice bridge (order creation + payment-complete hook).
	new CMP_WooCommerce();
}
add_action( 'plugins_loaded', 'cmp_init_plugin' );

/**
 * -----------------------------------------------------------------------
 * Assets
 * -----------------------------------------------------------------------
 */
function cmp_enqueue_public_assets() {
	wp_enqueue_style( 'cmp-public', CMP_PLUGIN_URL . 'assets/css/cmp-public.css', array(), CMP_VERSION );
	wp_enqueue_script( 'cmp-public', CMP_PLUGIN_URL . 'assets/js/cmp-public.js', array( 'jquery' ), CMP_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'cmp_enqueue_public_assets' );

function cmp_enqueue_admin_assets( $hook ) {
	if ( strpos( $hook, 'cmp-' ) === false && strpos( $hook, 'cison' ) === false ) {
		return;
	}
	wp_enqueue_style( 'cmp-admin', CMP_PLUGIN_URL . 'assets/css/cmp-admin.css', array(), CMP_VERSION );
}
add_action( 'admin_enqueue_scripts', 'cmp_enqueue_admin_assets' );
