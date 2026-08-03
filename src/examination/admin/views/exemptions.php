<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'exemption',
		'plural'        => 'exemptions',
		'status_column' => 'status',
		'status_labels' => array(
			'pending'  => __( 'Pending Review', 'cison-member-portal' ),
			'approved' => __( 'Exemption Granted', 'cison-member-portal' ),
			'rejected' => __( 'Exemption Denied', 'cison-member-portal' ),
		),
		'columns'       => array(
			'user'         => __( 'Member', 'cison-member-portal' ),
			'exam'         => __( 'Examination', 'cison-member-portal' ),
			'status'       => __( 'Status', 'cison-member-portal' ),
			'document'     => __( 'Document', 'cison-member-portal' ),
			'created_at'   => __( 'Submitted', 'cison-member-portal' ),
			'reviewed_at'  => __( 'Reviewed', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Exemptions::get_all_requests();
			foreach ( $rows as $row ) {
				$user = get_userdata( $row->user_id );
				$exam = CMP_Examinations::get_exam( $row->exam_id );

				$row->user = $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '#' . absint( $row->user_id );
				$row->exam = $exam ? esc_html( $exam->code . ' - ' . $exam->title ) : '#' . absint( $row->exam_id );

				$row->document = $row->supporting_document_id
					? '<a href="' . esc_url( wp_get_attachment_url( $row->supporting_document_id ) ) . '" target="_blank">' . esc_html__( 'View PDF', 'cison-member-portal' ) . '</a>'
					: '&mdash;';

				$row->reviewed_at = $row->reviewed_at ? $row->reviewed_at : '&mdash;';
			}
			return $rows;
		},
		'row_actions_callback' => function ( $item ) {
			$actions = array();
			if ( 'pending' === $item->status ) {
				$approve_url = wp_nonce_url( admin_url( 'admin.php?page=cmp-exemptions&cmp_admin_action=cmp_approve_exemption&id=' . $item->id ), 'cmp_approve_exemption', 'cmp_admin_nonce' );
				$reject_url  = wp_nonce_url( admin_url( 'admin.php?page=cmp-exemptions&cmp_admin_action=cmp_reject_exemption&id=' . $item->id ), 'cmp_reject_exemption', 'cmp_admin_nonce' );
				$actions['approve'] = '<a href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Grant Exemption', 'cison-member-portal' ) . '</a>';
				$actions['reject']  = '<a href="' . esc_url( $reject_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Deny this exemption request? A 4-week cooldown will start.', 'cison-member-portal' ) ) . '\')">' . esc_html__( 'Deny', 'cison-member-portal' ) . '</a>';
			}
			return $actions;
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1><?php esc_html_e( 'Exemption Requests', 'cison-member-portal' ); ?></h1>
	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-exemptions' ) ); ?>"><?php esc_html_e( 'All', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-exemptions&status=pending' ) ); ?>"><?php esc_html_e( 'Pending', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-exemptions&status=approved' ) ); ?>"><?php esc_html_e( 'Approved', 'cison-member-portal' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-exemptions&status=rejected' ) ); ?>"><?php esc_html_e( 'Rejected', 'cison-member-portal' ); ?></a></li>
	</ul>
	<form method="get">
		<input type="hidden" name="page" value="cmp-exemptions" />
		<?php $table->display(); ?>
	</form>
</div>
