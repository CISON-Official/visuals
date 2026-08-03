<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$exam_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exam    = $exam_id ? CMP_Examinations::get_exam( $exam_id ) : null;
$current_prereq_ids = $exam_id ? wp_list_pluck( CMP_Examinations::get_prerequisites( $exam_id ), 'id' ) : array();
$all_exams = CMP_Examinations::get_all();
?>
<div class="wrap cmp-admin">
	<h1><?php echo $exam ? esc_html__( 'Edit Examination', 'cison-member-portal' ) : esc_html__( 'Add Examination', 'cison-member-portal' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cmp-examinations' ) ); ?>">
		<input type="hidden" name="cmp_admin_action" value="cmp_save_examination" />
		<input type="hidden" name="exam_id" value="<?php echo esc_attr( $exam_id ); ?>" />
		<?php wp_nonce_field( 'cmp_save_examination', 'cmp_admin_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th><label for="title"><?php esc_html_e( 'Title', 'cison-member-portal' ); ?></label></th>
				<td><input type="text" class="regular-text" id="title" name="title" value="<?php echo esc_attr( $exam->title ?? '' ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="code"><?php esc_html_e( 'Code', 'cison-member-portal' ); ?></label></th>
				<td><input type="text" class="regular-text" id="code" name="code" value="<?php echo esc_attr( $exam->code ?? '' ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="level"><?php esc_html_e( 'Level', 'cison-member-portal' ); ?></label></th>
				<td>
					<select id="level" name="level">
						<option value="1" <?php selected( (string) ( $exam->level ?? '1' ), '1' ); ?>><?php esc_html_e( 'Level 1 (Foundation)', 'cison-member-portal' ); ?></option>
						<option value="2" <?php selected( (string) ( $exam->level ?? '' ), '2' ); ?>><?php esc_html_e( 'Level 2 (Intermediate)', 'cison-member-portal' ); ?></option>
						<option value="3" <?php selected( (string) ( $exam->level ?? '' ), '3' ); ?>><?php esc_html_e( 'Level 3 (Graduate)', 'cison-member-portal' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="fee"><?php esc_html_e( 'Fee', 'cison-member-portal' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="fee" name="fee" value="<?php echo esc_attr( $exam->fee ?? '10000' ); ?>" required /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Prerequisite Exam(s)', 'cison-member-portal' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'A prerequisite must belong to exactly one level below this exam (e.g. a Level 2 exam can only require Level 1 exams).', 'cison-member-portal' ); ?></p>
					<?php foreach ( $all_exams as $candidate ) : ?>
						<?php if ( $exam_id && (int) $candidate->id === (int) $exam_id ) { continue; } ?>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="prerequisites[]" value="<?php echo esc_attr( $candidate->id ); ?>" <?php checked( in_array( (int) $candidate->id, array_map( 'intval', $current_prereq_ids ), true ) ); ?> />
							<?php echo esc_html( '[Level ' . $candidate->level . '] ' . $candidate->code . ' - ' . $candidate->title ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( $exam ? __( 'Update Examination', 'cison-member-portal' ) : __( 'Create Examination', 'cison-member-portal' ) ); ?>
	</form>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-examinations' ) ); ?>">&larr; <?php esc_html_e( 'Back to Examinations', 'cison-member-portal' ); ?></a></p>
</div>
