<?php
/**
 * Vars: $exemptions, $course_applications
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cmp-wrap cmp-review-tracking">
	<h2><?php esc_html_e( 'My Applications & Exemptions', 'cison-member-portal' ); ?></h2>

	<h3><?php esc_html_e( 'Course Applications', 'cison-member-portal' ); ?></h3>
	<table class="cmp-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Course', 'cison-member-portal' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cison-member-portal' ); ?></th>
				<th><?php esc_html_e( 'Submitted', 'cison-member-portal' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $course_applications ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No course applications yet.', 'cison-member-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $course_applications as $app ) : ?>
				<?php $course = CMP_Courses::get_course( $app->course_id, false ); ?>
				<tr>
					<td><?php echo esc_html( $course ? $course->title : '#' . $app->course_id ); ?></td>
					<td><span class="cmp-status cmp-status-<?php echo esc_attr( $app->status ); ?>"><?php echo esc_html( ucfirst( $app->status ) ); ?></span></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $app->submitted_at ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Exemption Requests', 'cison-member-portal' ); ?></h3>
	<table class="cmp-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Examination', 'cison-member-portal' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cison-member-portal' ); ?></th>
				<th><?php esc_html_e( 'Submitted', 'cison-member-portal' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $exemptions ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No exemption requests yet.', 'cison-member-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $exemptions as $req ) : ?>
				<?php $exam = CMP_Examinations::get_exam( $req->exam_id ); ?>
				<tr>
					<td><?php echo esc_html( $exam ? $exam->code . ' - ' . $exam->title : '#' . $req->exam_id ); ?></td>
					<td><span class="cmp-status cmp-status-<?php echo esc_attr( $req->status ); ?>"><?php echo esc_html( ucfirst( $req->status ) ); ?></span></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $req->created_at ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
