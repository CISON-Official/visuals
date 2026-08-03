<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'order',
		'plural'        => 'orders',
		'status_column' => 'status',
		'status_labels' => array(
			'pending_payment' => __( 'Awaiting Fee Settlement', 'cison-member-portal' ),
			'paid'            => __( 'Registered & In the Loop', 'cison-member-portal' ),
			'cancelled'       => __( 'Cancelled', 'cison-member-portal' ),
		),
		'columns'       => array(
			'id_display'   => __( 'Order', 'cison-member-portal' ),
			'user'         => __( 'Member', 'cison-member-portal' ),
			'exams_list'   => __( 'Exams', 'cison-member-portal' ),
			'total_amount' => __( 'Total', 'cison-member-portal' ),
			'status'       => __( 'Status', 'cison-member-portal' ),
			'exam_status'  => __( 'Outcome', 'cison-member-portal' ),
			'created_at'   => __( 'Created', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Examinations::get_all_orders();
			foreach ( $rows as $row ) {
				$user = get_userdata( $row->user_id );

				$row->id_display   = '#' . $row->id;
				$row->user         = $user ? esc_html( $user->display_name ) : '#' . absint( $row->user_id );
				$row->exams_list   = esc_html( implode( ', ', wp_list_pluck( CMP_Examinations::get_order_exams( $row->id ), 'code' ) ) );
				$row->total_amount = number_format( (float) $row->total_amount, 2 );
			}
			return $rows;
		},
		'row_actions_callback' => function ( $item ) {
			$actions = array();

			if ( 'paid' !== $item->status ) {
				$paid_url = wp_nonce_url( admin_url( 'admin.php?page=cmp-exam-orders&cmp_admin_action=cmp_mark_order_paid&id=' . $item->id ), 'cmp_mark_order_paid', 'cmp_admin_nonce' );
				$actions['mark_paid'] = '<a href="' . esc_url( $paid_url ) . '">' . esc_html__( 'Mark as Paid', 'cison-member-portal' ) . '</a>';
			}
			if ( 'cancelled' !== $item->status ) {
				$cancel_url = wp_nonce_url( admin_url( 'admin.php?page=cmp-exam-orders&cmp_admin_action=cmp_cancel_order&id=' . $item->id ), 'cmp_cancel_order', 'cmp_admin_nonce' );
				$actions['cancel'] = '<a href="' . esc_url( $cancel_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Cancel this order?', 'cison-member-portal' ) ) . '\')">' . esc_html__( 'Cancel', 'cison-member-portal' ) . '</a>';
			}

			foreach ( array( 'pending' => __( 'Pending', 'cison-member-portal' ), 'passed' => __( 'Passed', 'cison-member-portal' ), 'failed' => __( 'Failed', 'cison-member-portal' ) ) as $outcome => $label ) {
				if ( $item->exam_status === $outcome ) {
					continue;
				}
				$url = wp_nonce_url( admin_url( 'admin.php?page=cmp-exam-orders&cmp_admin_action=cmp_set_exam_outcome&id=' . $item->id . '&outcome=' . $outcome ), 'cmp_set_exam_outcome', 'cmp_admin_nonce' );
				/* translators: %s: outcome label, e.g. "Passed" */
				$actions[ 'set_' . $outcome ] = '<a href="' . esc_url( $url ) . '">' . sprintf( esc_html__( 'Set: %s', 'cison-member-portal' ), esc_html( $label ) ) . '</a>';
			}

			return $actions;
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1><?php esc_html_e( 'Exam Registration Orders', 'cison-member-portal' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Orders normally move to "Registered & In the Loop" automatically once the linked WooCommerce order is paid. Use "Mark as Paid" only for manual overrides (e.g. bank transfer reconciled outside WooCommerce).', 'cison-member-portal' ); ?></p>
	<form method="get">
		<input type="hidden" name="page" value="cmp-exam-orders" />
		<?php $table->display(); ?>
	</form>
</div>
