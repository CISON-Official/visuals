<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$course    = $course_id ? CMP_Courses::get_course( $course_id, false ) : null;
?>
<div class="wrap cmp-admin">
	<h1><?php echo $course ? esc_html__( 'Edit Course', 'cison-member-portal' ) : esc_html__( 'Add Course', 'cison-member-portal' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cmp-courses' ) ); ?>">
		<input type="hidden" name="cmp_admin_action" value="cmp_save_course" />
		<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>" />
		<?php wp_nonce_field( 'cmp_save_course', 'cmp_admin_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th><label for="title"><?php esc_html_e( 'Title', 'cison-member-portal' ); ?></label></th>
				<td><input type="text" class="regular-text" id="title" name="title" value="<?php echo esc_attr( $course->title ?? '' ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="code"><?php esc_html_e( 'Code', 'cison-member-portal' ); ?></label></th>
				<td><input type="text" class="regular-text" id="code" name="code" value="<?php echo esc_attr( $course->code ?? '' ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="description"><?php esc_html_e( 'Description', 'cison-member-portal' ); ?></label></th>
				<td><textarea id="description" name="description" rows="6" class="large-text"><?php echo esc_textarea( $course->description ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="designation"><?php esc_html_e( 'Designation', 'cison-member-portal' ); ?></label></th>
				<td>
					<select id="designation" name="designation">
						<option value="C" <?php selected( ( $course->designation ?? 'C' ), 'C' ); ?>><?php esc_html_e( 'Compulsory', 'cison-member-portal' ); ?></option>
						<option value="E" <?php selected( ( $course->designation ?? 'C' ), 'E' ); ?>><?php esc_html_e( 'Elective', 'cison-member-portal' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="is_active"><?php esc_html_e( 'Active', 'cison-member-portal' ); ?></label></th>
				<td><input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( ! $course || ! empty( $course->is_active ) ); ?> /></td>
			</tr>
		</table>

		<?php submit_button( $course ? __( 'Update Course', 'cison-member-portal' ) : __( 'Create Course', 'cison-member-portal' ) ); ?>
	</form>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-courses' ) ); ?>">&larr; <?php esc_html_e( 'Back to Courses', 'cison-member-portal' ); ?></a></p>
</div>
