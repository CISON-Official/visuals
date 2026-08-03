<?php
/**
 * Vars: $courses, $enrolled_course_ids, $pending_course_ids, $has_pending_application
 *
 * NOTE ON THEME MARKUP: this markup uses plain, generically-styled wrapper
 * classes (cmp-*) rather than hardcoded BuddyBoss template markup, since
 * BuddyBoss child-theme structure varies per site. Drop this shortcode into
 * a BuddyBoss page template (or a BuddyPress-style page) and adjust
 * assets/css/cmp-public.css to match your child theme's design tokens.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cmp-wrap cmp-course-catalog">
	<h2><?php esc_html_e( 'Course Catalog', 'cison-member-portal' ); ?></h2>

	<?php if ( $has_pending_application ) : ?>
		<div class="cmp-notice cmp-notice-info">
			<?php esc_html_e( 'You already have an application pending review. You can apply for another course once it has been decided.', 'cison-member-portal' ); ?>
		</div>
	<?php endif; ?>

	<div class="cmp-card-grid">
		<?php if ( empty( $courses ) ) : ?>
			<p><?php esc_html_e( 'No courses are currently open for application.', 'cison-member-portal' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $courses as $course ) : ?>
			<?php
			$is_enrolled = in_array( (int) $course->id, array_map( 'intval', $enrolled_course_ids ), true );
			$is_pending  = in_array( (int) $course->id, array_map( 'intval', $pending_course_ids ), true );
			?>
			<div class="cmp-card">
				<span class="cmp-badge cmp-badge-<?php echo 'C' === $course->designation ? 'compulsory' : 'elective'; ?>">
					<?php echo 'C' === $course->designation ? esc_html__( 'Compulsory', 'cison-member-portal' ) : esc_html__( 'Elective', 'cison-member-portal' ); ?>
				</span>
				<h3><?php echo esc_html( $course->title ); ?></h3>
				<p class="cmp-code"><?php echo esc_html( $course->code ); ?></p>
				<p><?php echo esc_html( wp_trim_words( $course->description, 24 ) ); ?></p>

				<div class="cmp-card-actions">
					<a class="cmp-btn cmp-btn-secondary" href="<?php echo esc_url( add_query_arg( 'course_id', $course->id, get_permalink(259) ) ); ?>">
						<?php esc_html_e( 'View Details', 'cison-member-portal' ); ?>
					</a>

					<?php if ( $is_enrolled ) : ?>
						<span class="cmp-status cmp-status-approved"><?php esc_html_e( 'Enrolled', 'cison-member-portal' ); ?></span>
					<?php elseif ( $is_pending ) : ?>
						<span class="cmp-status cmp-status-pending"><?php esc_html_e( 'Application Pending', 'cison-member-portal' ); ?></span>
					<?php elseif ( ! $has_pending_application ) : ?>
						<a class="cmp-btn" href="<?php echo esc_url( add_query_arg( 'course_id', $course->id, get_permalink() ) ); ?>#apply">
							<?php esc_html_e( 'Apply', 'cison-member-portal' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
