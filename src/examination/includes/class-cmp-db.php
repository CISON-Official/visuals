<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central place for table names so nothing hardcodes a prefix string.
 */
class CMP_DB {

	public static function courses() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_courses';
	}

	public static function course_applications() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_course_applications';
	}

	public static function examinations() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_examinations';
	}

	public static function exam_prerequisites() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_exam_prerequisites';
	}

	public static function exam_orders() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_exam_orders';
	}

	public static function exam_order_items() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_exam_order_items';
	}

	public static function exemption_requests() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_exemption_requests';
	}

	public static function fee_invoices() {
		global $wpdb;
		return $wpdb->prefix . 'cmp_fee_invoices';
	}
}
