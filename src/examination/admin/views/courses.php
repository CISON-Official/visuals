<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new CMP_List_Table(
	array(
		'singular'      => 'course',
		'plural'        => 'courses',
		'columns'       => array(
			'code'        => __( 'Code', 'cison-member-portal' ),
			'title'       => __( 'Title', 'cison-member-portal' ),
			'designation' => __( 'Designation', 'cison-member-portal' ),
			'is_active'   => __( 'Active', 'cison-member-portal' ),
			'created_at'  => __( 'Created', 'cison-member-portal' ),
		),
		'data_callback' => function () {
			$rows = CMP_Courses::get_all_courses();
			foreach ( $rows as $row ) {
				$row->designation = 'C' === $row->designation ? __( 'Compulsory', 'cison-member-portal' ) : __( 'Elective', 'cison-member-portal' );
				$row->is_active   = $row->is_active ? __( 'Yes', 'cison-member-portal' ) : __( 'No', 'cison-member-portal' );
			}
			return $rows;
		},
		'row_actions_callback' => function ( $item ) {
			$edit_url = admin_url( 'admin.php?page=cmp-courses&edit=' . $item->id );
			return array(
				'edit' => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'cison-member-portal' ) . '</a>',
			);
		},
	)
);
$table->prepare_items();
?>
<div class="wrap cmp-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Courses', 'cison-member-portal' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-courses&new=1' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'cison-member-portal' ); ?>
	</a>
	<hr class="wp-header-end" />
	<form method="get">
		<input type="hidden" name="page" value="cmp-courses" />
		<?php $table->display(); ?>
	</form>
</div>
