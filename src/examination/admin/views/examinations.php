<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'examination',
		'plural'        => 'examinations',
		'columns'       => array(
			'code'          => __( 'Code', 'cison-member-portal' ),
			'title'         => __( 'Title', 'cison-member-portal' ),
			'level'         => __( 'Level', 'cison-member-portal' ),
			'fee'           => __( 'Fee', 'cison-member-portal' ),
			'prerequisites' => __( 'Prerequisite Exam(s)', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Examinations::get_all();
			foreach ( $rows as $row ) {
				$row->fee           = number_format( (float) $row->fee, 2 );
				$row->prerequisites = esc_html( implode( ', ', wp_list_pluck( CMP_Examinations::get_prerequisites( $row->id ), 'code' ) ) );
			}
			return $rows;
		},
		'row_actions_callback' => function ( $item ) {
			$edit_url = admin_url( 'admin.php?page=cmp-examinations&edit=' . $item->id );
			return array(
				'edit' => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'cison-member-portal' ) . '</a>',
			);
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Examinations', 'cison-member-portal' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-examinations&new=1' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'cison-member-portal' ); ?>
	</a>
	<hr class="wp-header-end" />
	<form method="get">
		<input type="hidden" name="page" value="cmp-examinations" />
		<?php $table->display(); ?>
	</form>
</div>
