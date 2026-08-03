<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates / upgrades the custom tables this plugin needs.
 *
 * Mirrors the original Django models as closely as WordPress conventions
 * allow: numeric wp-style auto-increment primary keys instead of UUIDs,
 * user_id pointing at wp_users.ID instead of a custom user model, and
 * file uploads stored as WP attachment (media library) IDs instead of
 * raw FileField paths.
 */
class CMP_Activator {

	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$courses = CMP_DB::courses();
		$course_applications = CMP_DB::course_applications();
		$examinations = CMP_DB::examinations();
		$exam_prerequisites = CMP_DB::exam_prerequisites();
		$exam_orders = CMP_DB::exam_orders();
		$exam_order_items = CMP_DB::exam_order_items();
		$exemption_requests = CMP_DB::exemption_requests();
		$fee_invoices = CMP_DB::fee_invoices();

		$sql = array();

		// Courses -------------------------------------------------------
		$sql[] = "CREATE TABLE {$courses} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			code VARCHAR(50) NOT NULL,
			description LONGTEXT NULL,
			designation VARCHAR(2) NOT NULL DEFAULT 'C',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) {$charset_collate};";

		// Course applications --------------------------------------------
		$sql[] = "CREATE TABLE {$course_applications} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			statement_of_purpose LONGTEXT NULL,
			supporting_document_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			submitted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$charset_collate};";

		// Examinations -----------------------------------------------------
		$sql[] = "CREATE TABLE {$examinations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			code VARCHAR(50) NOT NULL,
			level TINYINT UNSIGNED NOT NULL,
			fee DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY level (level)
		) {$charset_collate};";

		// Exam prerequisites (many-to-many, self-referencing) -------------
		$sql[] = "CREATE TABLE {$exam_prerequisites} (
			exam_id BIGINT UNSIGNED NOT NULL,
			prerequisite_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (exam_id, prerequisite_id)
		) {$charset_collate};";

		// Exam registration orders (the checkout bundle) -------------------
		$sql[] = "CREATE TABLE {$exam_orders} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(20) NOT NULL DEFAULT 'pending_payment',
			exam_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			invoice_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		// Exam order <-> exam (many-to-many) --------------------------------
		$sql[] = "CREATE TABLE {$exam_order_items} (
			order_id BIGINT UNSIGNED NOT NULL,
			exam_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (order_id, exam_id)
		) {$charset_collate};";

		// Exemption requests ------------------------------------------------
		$sql[] = "CREATE TABLE {$exemption_requests} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			exam_id BIGINT UNSIGNED NOT NULL,
			reason LONGTEXT NULL,
			supporting_document_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			reviewed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_exam (user_id, exam_id),
			KEY status (status)
		) {$charset_collate};";

		// Fee invoices (ledger; actual money movement happens in WooCommerce) -
		$sql[] = "CREATE TABLE {$fee_invoices} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			invoice_number VARCHAR(100) NOT NULL,
			fee_type VARCHAR(40) NOT NULL,
			description VARCHAR(255) NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(25) NOT NULL DEFAULT 'unpaid',
			wc_order_id BIGINT UNSIGNED NULL,
			transaction_reference VARCHAR(150) NULL,
			created_at DATETIME NOT NULL,
			paid_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY user_id (user_id),
			KEY status (status),
			KEY wc_order_id (wc_order_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		// A dedicated capability, granted to administrators, gates every
		// review/approve/reject admin screen in this plugin.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'manage_cmp_portal' ) ) {
			$admin_role->add_cap( 'manage_cmp_portal' );
		}

		update_option( 'cmp_db_version', CMP_DB_VERSION );
	}
}
