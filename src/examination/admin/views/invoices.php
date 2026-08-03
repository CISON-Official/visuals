<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'invoice',
		'plural'        => 'invoices',
		'status_column' => 'status',
		'status_labels' => array(
			'unpaid'               => __( 'Unpaid', 'cison-member-portal' ),
			'pending_verification' => __( 'Pending Verification', 'cison-member-portal' ),
			'paid'                 => __( 'Paid', 'cison-member-portal' ),
		),
		'columns'       => array(
			'invoice_number' => __( 'Invoice #', 'cison-member-portal' ),
			'user'           => __( 'Member', 'cison-member-portal' ),
			'fee_type'       => __( 'Fee Type', 'cison-member-portal' ),
			'description'    => __( 'Description', 'cison-member-portal' ),
			'amount'         => __( 'Amount', 'cison-member-portal' ),
			'status'         => __( 'Status', 'cison-member-portal' ),
			'wc_order_link'  => __( 'WooCommerce Order', 'cison-member-portal' ),
			'created_at'     => __( 'Created', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Invoices::get_all_invoices();
			foreach ( $rows as $row ) {
				$user = get_userdata( $row->user_id );

				$row->user    = $user ? esc_html( $user->display_name ) : '#' . absint( $row->user_id );
				$row->amount  = number_format( (float) $row->amount, 2 );

				$row->wc_order_link = $row->wc_order_id
					? '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $row->wc_order_id ) . '&action=edit' ) ) . '">#' . absint( $row->wc_order_id ) . '</a>'
					: '&mdash;';
			}
			return $rows;
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1><?php esc_html_e( 'Fee Invoices', 'cison-member-portal' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'This is a read-only ledger. Payment itself is processed and refunded through WooCommerce → Orders; this table just tracks which invoice each WooCommerce order was opened for.', 'cison-member-portal' ); ?>
	</p>
	<form method="get">
		<input type="hidden" name="page" value="cmp-invoices" />
		<?php $table->display(); ?>
	</form>
</div>
