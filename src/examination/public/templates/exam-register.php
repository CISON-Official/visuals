<?php
/**
 * Vars: $level_1_exams, $level_2_exams, $level_3_exams, $passed_exam_ids
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$passed_exam_ids = array_map( 'intval', $passed_exam_ids );

$render_level = function ( $title, $exams ) use ( $passed_exam_ids ) {
	if ( empty( $exams ) ) {
		return;
	}
	echo '<h3>' . esc_html( $title ) . '</h3><div class="cmp-exam-list">';
	foreach ( $exams as $exam ) {
		$already_passed = in_array( (int) $exam->id, $passed_exam_ids, true );
		$prereqs        = CMP_Examinations::get_prerequisites( $exam->id );
		?>
		<label class="cmp-exam-row <?php echo $already_passed ? 'cmp-exam-row-passed' : ''; ?>">
			<input type="checkbox" name="selected_exams[]" value="<?php echo esc_attr( $exam->id ); ?>" <?php disabled( $already_passed ); ?> />
			<span class="cmp-exam-code"><?php echo esc_html( $exam->code ); ?></span>
			<span class="cmp-exam-title"><?php echo esc_html( $exam->title ); ?></span>
			<span class="cmp-exam-fee"><?php echo esc_html( number_format( (float) $exam->fee, 2 ) ); ?></span>
			<?php if ( $already_passed ) : ?>
				<span class="cmp-status cmp-status-approved"><?php esc_html_e( 'Passed', 'cison-member-portal' ); ?></span>
			<?php elseif ( ! empty( $prereqs ) ) : ?>
				<span class="cmp-muted cmp-prereq-note">
					<?php
					/* translators: %s: comma separated prerequisite codes */
					printf( esc_html__( 'Requires: %s', 'cison-member-portal' ), esc_html( implode( ', ', wp_list_pluck( $prereqs, 'code' ) ) ) );
					?>
				</span>
			<?php endif; ?>
		</label>
		<?php
	}
	echo '</div>';
};
?>
<div class="cmp-wrap cmp-exam-register">
	<h2><?php esc_html_e( 'Examination Registration', 'cison-member-portal' ); ?></h2>
	<p class="cmp-muted"><?php esc_html_e( 'Select the examinations you want to register for. Modules with unmet prerequisites will be blocked at submission.', 'cison-member-portal' ); ?></p>

	<form class="cmp-form" method="post">
		<input type="hidden" name="cmp_action" value="cmp_register_exams" />
		<?php wp_nonce_field( 'cmp_register_exams', 'cmp_nonce' ); ?>

		<?php
		$render_level( __( 'Level 1 (Foundation)', 'cison-member-portal' ), $level_1_exams );
		$render_level( __( 'Level 2 (Intermediate)', 'cison-member-portal' ), $level_2_exams );
		$render_level( __( 'Level 3 (Graduate)', 'cison-member-portal' ), $level_3_exams );
		?>

		<button type="submit" class="cmp-btn"><?php esc_html_e( 'Register & Proceed to Payment', 'cison-member-portal' ); ?></button>
	</form>

</div>
