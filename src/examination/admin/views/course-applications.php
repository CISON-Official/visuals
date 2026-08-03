<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'application',
		'plural'        => 'applications',
		'status_column' => 'status',
		'status_labels' => array(
			'pending'  => __( 'Pending Review', 'cison-member-portal' ),
			'approved' => __( 'Approved / Enrolled', 'cison-member-portal' ),
			'rejected' => __( 'Rejected', 'cison-member-portal' ),
		),
		'columns'       => array(
			'user'          => __( 'Member', 'cison-member-portal' ),
			'course'        => __( 'Course', 'cison-member-portal' ),
			'status'        => __( 'Status', 'cison-member-portal' ),
			'document'      => __( 'Document', 'cison-member-portal' ),
			'submitted_at'  => __( 'Submitted', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Courses::get_all_applications();
			foreach ( $rows as $row ) {
				$user   = get_userdata( $row->user_id );
				$course = CMP_Courses::get_course( $row->course_id, false );

				$row->user   = $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '#' . absint( $row->user_id );
				$row->course = $course ? esc_html( $course->code . ' - ' . $course->title ) : '#' . absint( $row->course_id );

				$row->document = $row->supporting_document_id
					? '<a href="' . esc_url( wp_get_attachment_url( $row->supporting_document_id ) ) . '" target="_blank">' . esc_html__( 'View PDF', 'cison-member-portal' ) . '</a>'
					: '&mdash;';
			}
			return $rows;
		},
		'row_actions_callback' => function ( $item ) {
			$actions = array();
			if ( 'pending' === $item->status ) {
				$approve_url = wp_nonce_url( admin_url( 'admin.php?page=cmp-course-applications&cmp_admin_action=cmp_approve_application&id=' . $item->id ), 'cmp_approve_application', 'cmp_admin_nonce' );
				$reject_url  = wp_nonce_url( admin_url( 'admin.php?page=cmp-course-applications&cmp_admin_action=cmp_reject_application&id=' . $item->id ), 'cmp_reject_application', 'cmp_admin_nonce' );
				$actions['approve'] = '<a href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve & Enroll', 'cison-member-portal' ) . '</a>';
				$actions['reject']  = '<a href="' . esc_url( $reject_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Reject this application?', 'cison-member-portal' ) ) . '\')">' . esc_html__( 'Reject', 'cison-member-portal' ) . '</a>';
			}
			return $actions;
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1><?php esc_html_e( 'Course Applications', 'cison-member-portal' ); ?></h1>
	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-course-applications' ) ); ?>"><?php esc_html_e( 'All', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-course-applications&status=pending' ) ); ?>"><?php esc_html_e( 'Pending', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-course-applications&status=approved' ) ); ?>"><?php esc_html_e( 'Approved', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-course-applications&status=rejected' ) ); ?>"><?php esc_html_e( 'Rejected', 'cison-member-portal' ); ?></a></li>
	</ul>
	<form method="get">
		<input type="hidden" name="page" value="cmp-course-applications" />
		<?php $table->display(); ?>
	</form>
</div>
