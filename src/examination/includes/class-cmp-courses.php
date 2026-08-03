<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ports courses/models.py (Course, CourseApplication) and the related
 * business rules found in courses/views.py: CourseCatalogView,
 * CourseApplyView, CourseDetailView.
 */
class CMP_Courses {

	/* ---------------------------------------------------------------
	 * Courses
	 * ------------------------------------------------------------- */

	public static function get_active_courses() {
		global $wpdb;
		$table = CMP_DB::courses();
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY created_at DESC"
		);
	}

	public static function get_course( $course_id, $active_only = true ) {
		global $wpdb;
		$table = CMP_DB::courses();
		if ( $active_only ) {
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND is_active = 1", $course_id )
			);
		}
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $course_id )
		);
	}

	public static function get_all_courses() {
		global $wpdb;
		$table = CMP_DB::courses();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	public static function save_course( $data, $course_id = 0 ) {
		global $wpdb;
		$table = CMP_DB::courses();

		$row = array(
			'title'       => sanitize_text_field( $data['title'] ),
			'code'        => sanitize_text_field( $data['code'] ),
			'description' => wp_kses_post( $data['description'] ),
			'designation' => in_array( $data['designation'], array( 'E', 'C' ), true ) ? $data['designation'] : 'C',
			'is_active'   => ! empty( $data['is_active'] ) ? 1 : 0,
		);

		if ( $course_id ) {
			$wpdb->update( $table, $row, array( 'id' => $course_id ) );
			return $course_id;
		}

		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );
		return $wpdb->insert_id;
	}

	/* ---------------------------------------------------------------
	 * Course applications
	 * ------------------------------------------------------------- */

	public static function get_enrolled_course_ids( $user_id ) {
		global $wpdb;
		$table = CMP_DB::course_applications();
		return $wpdb->get_col(
			$wpdb->prepare( "SELECT course_id FROM {$table} WHERE user_id = %d AND status = 'approved'", $user_id )
		);
	}

	public static function get_pending_course_ids( $user_id ) {
		global $wpdb;
		$table = CMP_DB::course_applications();
		return $wpdb->get_col(
			$wpdb->prepare( "SELECT course_id FROM {$table} WHERE user_id = %d AND status = 'pending'", $user_id )
		);
	}

	public static function has_pending_application( $user_id ) {
		return count( self::get_pending_course_ids( $user_id ) ) > 0;
	}

	public static function get_application_for_course( $user_id, $course_id ) {
		global $wpdb;
		$table = CMP_DB::course_applications();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d", $user_id, $course_id )
		);
	}

	/**
	 * Mirrors CourseApplyView.dispatch(): one pending application globally,
	 * and no duplicate applications to the same course.
	 *
	 * @return true|WP_Error
	 */
	public static function submit_application( $user_id, $course_id, $statement_of_purpose, $attachment_id ) {
		global $wpdb;

		$course = self::get_course( $course_id, true );
		if ( ! $course ) {
			return new WP_Error( 'cmp_course_not_found', __( 'That course could not be found or is not currently active.', 'cison-member-portal' ) );
		}

		if ( self::has_pending_application( $user_id ) ) {
			return new WP_Error( 'cmp_pending_exists', __( 'Application Denied: You already have another application pending review.', 'cison-member-portal' ) );
		}

		if ( self::get_application_for_course( $user_id, $course_id ) ) {
			return new WP_Error( 'cmp_duplicate', __( 'You have already submitted an application for this specific course.', 'cison-member-portal' ) );
		}

		$table = CMP_DB::course_applications();
		$wpdb->insert(
			$table,
			array(
				'user_id'                => $user_id,
				'course_id'              => $course_id,
				'statement_of_purpose'   => wp_kses_post( $statement_of_purpose ),
				'supporting_document_id' => $attachment_id ? absint( $attachment_id ) : null,
				'status'                 => 'pending',
				'submitted_at'           => current_time( 'mysql' ),
			)
		);

		return true;
	}

	public static function get_all_applications( $status = '' ) {
		global $wpdb;
		$table = CMP_DB::course_applications();
		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY submitted_at DESC", $status )
			);
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY submitted_at DESC" );
	}

	public static function get_applications_for_user( $user_id ) {
		global $wpdb;
		$table = CMP_DB::course_applications();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY submitted_at DESC", $user_id )
		);
	}

	/**
	 * Admin bulk action: approve/reject. Mirrors CourseApplicationAdmin actions.
	 */
	public static function set_application_status( $application_id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'approved', 'rejected', 'pending' ), true ) ) {
			return false;
		}
		$table = CMP_DB::course_applications();
		return $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $application_id ) );
	}
}
