<?php
/**
 * Vars: $exam
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="cmp-form" method="post" enctype="multipart/form-data">
	<h3><?php echo esc_html( sprintf( /* translators: %s: exam code */ __( 'Exemption Request: %s', 'cison-member-portal' ), $exam->code ) ); ?></h3>

	<input type="hidden" name="cmp_action" value="cmp_submit_exemption" />
	<input type="hidden" name="exam_id" value="<?php echo esc_attr( $exam->id ); ?>" />
	<?php wp_nonce_field( 'cmp_submit_exemption', 'cmp_nonce' ); ?>

	<p class="cmp-field">
		<label for="cmp-reason"><?php esc_html_e( 'State your qualification or basis for this exemption request.', 'cison-member-portal' ); ?></label>
		<textarea id="cmp-reason" name="reason" rows="6" required></textarea>
	</p>

	<p class="cmp-field">
		<label for="cmp-doc"><?php esc_html_e( 'Upload certificate or transcript (PDF)', 'cison-member-portal' ); ?></label>
		<input id="cmp-doc" type="file" name="supporting_document" accept="application/pdf" required />
	</p>

	<button type="submit" class="cmp-btn"><?php esc_html_e( 'Submit Exemption Request', 'cison-member-portal' ); ?></button>
</form>
