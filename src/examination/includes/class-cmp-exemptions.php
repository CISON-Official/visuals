<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ports courses/models.py (ExemptionRequest) and the gatekeeping rules from
 * ExemptionCatalogView / SubmitExemptionView / ApplyExemptionView in
 * courses/views.py, including the 4-week cooldown after a rejection.
 */
class CMP_Exemptions {

	const COOLDOWN_WEEKS = 4;

	public static function get_pending_exemption_ids( $user_id ) {
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		return $wpdb->get_col(
			$wpdb->prepare( "SELECT exam_id FROM {$table} WHERE user_id = %d AND status = 'pending'", $user_id )
		);
	}

	public static function has_any_pending( $user_id ) {
		return count( self::get_pending_exemption_ids( $user_id ) ) > 0;
	}

	public static function exam_has_approved_exemption( $user_id, $exam_id ) {
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND exam_id = %d AND status = 'approved'",
				$user_id,
				$exam_id
			)
		);
		return $count > 0;
	}

	public static function get_request_for_exam( $user_id, $exam_id, $status = '' ) {
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		if ( $status ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND exam_id = %d AND status = %s ORDER BY reviewed_at DESC LIMIT 1",
					$user_id,
					$exam_id,
					$status
				)
			);
		}
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND exam_id = %d", $user_id, $exam_id )
		);
	}

	/**
	 * exam_id => seconds remaining, for exams rejected within the last
	 * COOLDOWN_WEEKS weeks. Mirrors ExemptionCatalogView.get_context_data().
	 */
	public static function get_cooldown_map( $user_id ) {
		global $wpdb;
		$table     = CMP_DB::exemption_requests();
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( self::COOLDOWN_WEEKS * WEEK_IN_SECONDS ) );

		$rejections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT exam_id, reviewed_at FROM {$table}
				 WHERE user_id = %d AND status = 'rejected' AND reviewed_at >= %s",
				$user_id,
				$threshold
			)
		);

		$map = array();
		$now = time();
		foreach ( $rejections as $rej ) {
			$reviewed_ts  = strtotime( $rej->reviewed_at . ' UTC' );
			$cooldown_end = $reviewed_ts + ( self::COOLDOWN_WEEKS * WEEK_IN_SECONDS );
			$map[ $rej->exam_id ] = max( 0, $cooldown_end - $now );
		}
		return $map;
	}

	/**
	 * Shared validation used by both ApplyExemptionView and
	 * SubmitExemptionView. Returns true, or a WP_Error with the same
	 * messaging as the Django views.
	 */
	public static function validate_can_apply( $user_id, $exam_id ) {
		if ( self::has_any_pending( $user_id ) ) {
			return new WP_Error(
				'cmp_pending_exists',
				__( 'Application Blocked: You cannot submit a new exemption request while another application is pending review.', 'cison-member-portal' )
			);
		}

		$exam = CMP_Examinations::get_exam( $exam_id );
		if ( ! $exam ) {
			return new WP_Error( 'cmp_exam_not_found', __( 'That examination could not be found.', 'cison-member-portal' ) );
		}

		if ( CMP_Examinations::user_is_cleared_for_exam( $user_id, $exam_id ) ) {
			/* translators: %s: exam code */
			return new WP_Error( 'cmp_already_cleared', sprintf( __( 'You are already cleared or exempt from %s.', 'cison-member-portal' ), $exam->code ) );
		}

		$last_rejection = self::get_request_for_exam( $user_id, $exam_id, 'rejected' );
		if ( $last_rejection && $last_rejection->reviewed_at ) {
			$cooldown_end = strtotime( $last_rejection->reviewed_at . ' UTC' ) + ( self::COOLDOWN_WEEKS * WEEK_IN_SECONDS );
			if ( time() < $cooldown_end ) {
				$days_left = ceil( ( $cooldown_end - time() ) / DAY_IN_SECONDS );
				/* translators: %d: days left */
				return new WP_Error( 'cmp_cooldown', sprintf( __( 'Cooldown Active: This module was recently rejected. You must wait %d more days before reapplying.', 'cison-member-portal' ), $days_left ) );
			}
		}

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function submit_exemption( $user_id, $exam_id, $reason, $attachment_id ) {
		global $wpdb;

		$check = self::validate_can_apply( $user_id, $exam_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$table = CMP_DB::exemption_requests();
		$wpdb->insert(
			$table,
			array(
				'user_id'                => $user_id,
				'exam_id'                => $exam_id,
				'reason'                 => wp_kses_post( $reason ),
				'supporting_document_id' => $attachment_id ? absint( $attachment_id ) : null,
				'status'                 => 'pending',
				'created_at'             => current_time( 'mysql' ),
			)
		);

		return true;
	}

	public static function get_requests_for_user( $user_id ) {
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id )
		);
	}

	public static function get_all_requests( $status = '' ) {
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status )
			);
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	/**
	 * Admin action: approve/reject. Mirrors ExemptionRequestAdmin actions
	 * (sets reviewed_at, which starts the cooldown clock on rejection).
	 */
	public static function set_status( $request_id, $status ) {
		if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			return false;
		}
		global $wpdb;
		$table = CMP_DB::exemption_requests();
		return $wpdb->update(
			$table,
			array(
				'status'      => $status,
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $request_id )
		);
	}
}
