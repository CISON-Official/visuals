<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ports accounts/models.py FeeInvoice as a lightweight ledger table.
 *
 * IMPORTANT: this class does not talk to any payment gateway itself.
 * create_invoice() opens the ledger row; CMP_WooCommerce is responsible
 * for turning that row into a real WooCommerce order and for writing the
 * result back via mark_paid(). WordPress + WooCommerce own the actual
 * checkout/payment flow, same as the brief asked for.
 */
class CMP_Invoices {

	/**
	 * @return int|WP_Error  New invoice id.
	 */
	public static function create_invoice( $user_id, $fee_type, $description, $amount ) {
		global $wpdb;

		if ( ! in_array( $fee_type, array( 'exam_fee', 'course_enrollment', 'exemption_processing' ), true ) ) {
			return new WP_Error( 'cmp_bad_fee_type', __( 'Unknown fee type.', 'cison-member-portal' ) );
		}

		$table = CMP_DB::fee_invoices();

		$invoice_number = self::generate_invoice_number( $fee_type );

		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'invoice_number' => $invoice_number,
				'fee_type'       => $fee_type,
				'description'    => sanitize_text_field( $description ),
				'amount'         => (float) $amount,
				'status'         => 'unpaid',
				'created_at'     => current_time( 'mysql' ),
			)
		);

		return $wpdb->insert_id;
	}

	private static function generate_invoice_number( $fee_type ) {
		$prefix_map = array(
			'exam_fee'              => 'EXM',
			'course_enrollment'     => 'CRS',
			'exemption_processing'  => 'EXP',
		);
		$prefix = isset( $prefix_map[ $fee_type ] ) ? $prefix_map[ $fee_type ] : 'INV';
		return sprintf( '%s-%s', $prefix, strtoupper( wp_generate_password( 8, false, false ) ) );
	}

	public static function get_invoice( $invoice_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $invoice_id ) );
	}

	public static function get_invoice_by_wc_order( $wc_order_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE wc_order_id = %d", $wc_order_id ) );
	}

	public static function get_unpaid_for_user( $user_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status IN ('unpaid','pending_verification') ORDER BY created_at DESC",
				$user_id
			)
		);
	}

	public static function get_paid_for_user( $user_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND status = 'paid' ORDER BY paid_at DESC", $user_id )
		);
	}

	public static function get_total_outstanding( $user_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		$sum   = $wpdb->get_var(
			$wpdb->prepare( "SELECT SUM(amount) FROM {$table} WHERE user_id = %d AND status = 'unpaid'", $user_id )
		);
		return $sum ? (float) $sum : 0.0;
	}

	public static function get_all_invoices() {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	public static function attach_wc_order( $invoice_id, $wc_order_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->update( $table, array( 'wc_order_id' => $wc_order_id ), array( 'id' => $invoice_id ) );
	}

	public static function mark_paid( $invoice_id ) {
		global $wpdb;
		$table = CMP_DB::fee_invoices();
		return $wpdb->update(
			$table,
			array(
				'status'  => 'paid',
				'paid_at' => current_time( 'mysql' ),
			),
			array( 'id' => $invoice_id )
		);
	}
}
