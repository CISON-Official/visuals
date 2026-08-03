<?php
/**
 * Vars: $course
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="cmp-form" method="post" enctype="multipart/form-data">
	<h3><?php echo esc_html( sprintf( /* translators: %s: course title */ __( 'Apply: %s', 'cison-member-portal' ), $course->title ) ); ?></h3>

	<input type="hidden" name="cmp_action" value="cmp_apply_course" />
	<input type="hidden" name="course_id" value="<?php echo esc_attr( $course->id ); ?>" />
	<?php wp_nonce_field( 'cmp_apply_course', 'cmp_nonce' ); ?>

	<p class="cmp-field">
		<label for="cmp-statement"><?php esc_html_e( 'Why do you want to enroll in this course?', 'cison-member-portal' ); ?></label>
		<textarea id="cmp-statement" name="statement_of_purpose" rows="6" required></textarea>
	</p>

	<p class="cmp-field">
		<label for="cmp-doc"><?php esc_html_e( 'Supporting document (PDF)', 'cison-member-portal' ); ?></label>
		<input id="cmp-doc" type="file" name="supporting_document" accept="application/pdf" required />
	</p>

	<button type="submit" class="cmp-btn"><?php esc_html_e( 'Submit Application', 'cison-member-portal' ); ?></button>
</form>
