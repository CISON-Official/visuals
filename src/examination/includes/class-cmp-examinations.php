<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ports courses/models.py (Examination, ExamRegistrationOrder) and the
 * registration + prerequisite-gating logic from ExamRegistrationView in
 * courses/views.py.
 */
class CMP_Examinations {

	/* ---------------------------------------------------------------
	 * Examinations
	 * ------------------------------------------------------------- */

	public static function get_by_level( $level ) {
		global $wpdb;
		$table = CMP_DB::examinations();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE level = %d ORDER BY code", $level )
		);
	}

	public static function get_all() {
		global $wpdb;
		$table = CMP_DB::examinations();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY level, code" );
	}

	public static function get_exam( $exam_id ) {
		global $wpdb;
		$table = CMP_DB::examinations();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $exam_id )
		);
	}

	public static function get_prerequisites( $exam_id ) {
		global $wpdb;
		$exams  = CMP_DB::examinations();
		$prereq = CMP_DB::exam_prerequisites();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM {$exams} e
				 INNER JOIN {$prereq} p ON p.prerequisite_id = e.id
				 WHERE p.exam_id = %d",
				$exam_id
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function set_prerequisites( $exam_id, array $prerequisite_ids ) {
		global $wpdb;

		$exam = self::get_exam( $exam_id );
		if ( ! $exam ) {
			return new WP_Error( 'cmp_exam_not_found', __( 'Examination not found.', 'cison-member-portal' ) );
		}

		// Mirrors Examination.clean(): a prerequisite must be exactly one
		// level below the exam it's attached to (Level 2 <- Level 1,
		// Level 3 <- Level 2, etc.) and can't be the exam itself.
		foreach ( $prerequisite_ids as $prereq_id ) {
			$prereq_id = absint( $prereq_id );
			if ( ! $prereq_id ) {
				continue;
			}
			if ( $prereq_id === (int) $exam_id ) {
				return new WP_Error( 'cmp_self_prereq', __( 'An examination cannot be a prerequisite of itself.', 'cison-member-portal' ) );
			}
			$prereq = self::get_exam( $prereq_id );
			if ( ! $prereq ) {
				continue;
			}
			if ( (int) $exam->level - (int) $prereq->level !== 1 ) {
				/* translators: 1: prerequisite code, 2: exam code */
				return new WP_Error(
					'cmp_bad_prereq_level',
					sprintf( __( "Prerequisite '%1\$s' must link linearly to '%2\$s' (exactly one level below).", 'cison-member-portal' ), $prereq->code, $exam->code )
				);
			}
		}

		$table = CMP_DB::exam_prerequisites();
		$wpdb->delete( $table, array( 'exam_id' => $exam_id ) );
		foreach ( $prerequisite_ids as $prereq_id ) {
			$prereq_id = absint( $prereq_id );
			if ( $prereq_id && $prereq_id !== (int) $exam_id ) {
				$wpdb->insert( $table, array( 'exam_id' => $exam_id, 'prerequisite_id' => $prereq_id ) );
			}
		}

		return true;
	}

	public static function save_examination( $data, $exam_id = 0 ) {
		global $wpdb;
		$table = CMP_DB::examinations();

		$row = array(
			'title' => sanitize_text_field( $data['title'] ),
			'code'  => sanitize_text_field( $data['code'] ),
			'level' => absint( $data['level'] ),
			'fee'   => (float) $data['fee'],
		);

		if ( $exam_id ) {
			$wpdb->update( $table, $row, array( 'id' => $exam_id ) );
			$id = $exam_id;
		} else {
			$wpdb->insert( $table, $row );
			$id = $wpdb->insert_id;
		}

		if ( isset( $data['prerequisites'] ) && is_array( $data['prerequisites'] ) ) {
			$result = self::set_prerequisites( $id, $data['prerequisites'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $id;
	}

	/* ---------------------------------------------------------------
	 * Registration status helpers
	 * ------------------------------------------------------------- */

	/**
	 * Exams the user has PAID for AND PASSED. This is the set used to
	 * satisfy prerequisite requirements (matches ExamRegistrationView).
	 */
	public static function get_passed_exam_ids( $user_id ) {
		global $wpdb;
		$orders = CMP_DB::exam_orders();
		$items  = CMP_DB::exam_order_items();
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT i.exam_id FROM {$items} i
				 INNER JOIN {$orders} o ON o.id = i.order_id
				 WHERE o.user_id = %d AND o.status = 'paid' AND o.exam_status = 'passed'",
				$user_id
			)
		);
	}

	/**
	 * Exams the user has PAID for, regardless of pass/fail outcome. This is
	 * the "cleared" set used by the exemption catalog (matches
	 * ExemptionCatalogView / SubmitExemptionView, which only check
	 * status='paid').
	 */
	public static function get_paid_exam_ids( $user_id ) {
		global $wpdb;
		$orders = CMP_DB::exam_orders();
		$items  = CMP_DB::exam_order_items();
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT i.exam_id FROM {$items} i
				 INNER JOIN {$orders} o ON o.id = i.order_id
				 WHERE o.user_id = %d AND o.status = 'paid'",
				$user_id
			)
		);
	}

	public static function user_is_cleared_for_exam( $user_id, $exam_id ) {
		global $wpdb;
		$orders = CMP_DB::exam_orders();
		$items  = CMP_DB::exam_order_items();
		$paid   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$items} i
				 INNER JOIN {$orders} o ON o.id = i.order_id
				 WHERE o.user_id = %d AND i.exam_id = %d AND o.status = 'paid'",
				$user_id,
				$exam_id
			)
		);
		return $paid > 0 || CMP_Exemptions::exam_has_approved_exemption( $user_id, $exam_id );
	}

	/**
	 * Registers the selected exams for a user, enforcing prerequisite
	 * gating exactly like ExamRegistrationView.post(), then opens a fee
	 * invoice (fee_type=exam_fee) for the total.
	 *
	 * @return array{order_id:int,invoice_id:int}|WP_Error
	 */
	public static function register_exams( $user_id, array $exam_ids ) {
		global $wpdb;

		$exam_ids = array_filter( array_map( 'absint', $exam_ids ) );
		if ( empty( $exam_ids ) ) {
			return new WP_Error( 'cmp_no_exams', __( 'Please select at least one examination to register.', 'cison-member-portal' ) );
		}

		$passed_exam_ids = self::get_passed_exam_ids( $user_id );
		$exams_table      = CMP_DB::examinations();

		$placeholders = implode( ',', array_fill( 0, count( $exam_ids ), '%d' ) );
		$requested_exams = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$exams_table} WHERE id IN ({$placeholders})", $exam_ids ) // phpcs:ignore
		);

		$total = 0;
		foreach ( $requested_exams as $exam ) {
			$prereqs = self::get_prerequisites( $exam->id );
			foreach ( $prereqs as $prereq ) {
				if ( ! in_array( (string) $prereq->id, array_map( 'strval', $passed_exam_ids ), true ) ) {
					/* translators: 1: exam code and title, 2: prerequisite code and title */
					return new WP_Error(
						'cmp_prereq_blocked',
						sprintf(
							__( "Registration Blocked: To register for '%1\$s - %2\$s', you must first take and pass its prerequisite module: '%3\$s - %4\$s'.", 'cison-member-portal' ),
							$exam->code,
							$exam->title,
							$prereq->code,
							$prereq->title
						)
					);
				}
			}
			$total += (float) $exam->fee;
		}

		$orders_table = CMP_DB::exam_orders();
		$items_table  = CMP_DB::exam_order_items();

		$wpdb->insert(
			$orders_table,
			array(
				'user_id'      => $user_id,
				'total_amount' => $total,
				'status'       => 'pending_payment',
				'exam_status'  => 'pending',
				'created_at'   => current_time( 'mysql' ),
			)
		);
		$order_id = $wpdb->insert_id;

		foreach ( $requested_exams as $exam ) {
			$wpdb->insert( $items_table, array( 'order_id' => $order_id, 'exam_id' => $exam->id ) );
		}

		$invoice_id = CMP_Invoices::create_invoice(
			$user_id,
			'exam_fee',
			/* translators: %d: exam order id */
			sprintf( __( 'Examination Registration Fee for Order #%d', 'cison-member-portal' ), $order_id ),
			$total
		);

		if ( is_wp_error( $invoice_id ) ) {
			return $invoice_id;
		}

		$wpdb->update( $orders_table, array( 'invoice_id' => $invoice_id ), array( 'id' => $order_id ) );

		return array(
			'order_id'   => $order_id,
			'invoice_id' => $invoice_id,
		);
	}

	public static function get_order( $order_id ) {
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ) );
	}

	public static function get_order_by_invoice( $invoice_id ) {
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE invoice_id = %d", $invoice_id ) );
	}

	public static function get_all_orders() {
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	public static function get_order_exams( $order_id ) {
		global $wpdb;
		$exams = CMP_DB::examinations();
		$items = CMP_DB::exam_order_items();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM {$exams} e INNER JOIN {$items} i ON i.exam_id = e.id WHERE i.order_id = %d",
				$order_id
			)
		);
	}

	/**
	 * Called from the WooCommerce order-complete hook, or by an admin
	 * bulk action ("Mark as Paid/Settled" in the original Django admin).
	 */
	public static function mark_order_paid( $order_id ) {
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->update( $table, array( 'status' => 'paid' ), array( 'id' => $order_id ) );
	}

	public static function mark_order_cancelled( $order_id ) {
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->update( $table, array( 'status' => 'cancelled' ), array( 'id' => $order_id ) );
	}

	public static function set_exam_outcome( $order_id, $exam_status ) {
		if ( ! in_array( $exam_status, array( 'pending', 'failed', 'passed' ), true ) ) {
			return false;
		}
		global $wpdb;
		$table = CMP_DB::exam_orders();
		return $wpdb->update( $table, array( 'exam_status' => $exam_status ), array( 'id' => $order_id ) );
	}
}
