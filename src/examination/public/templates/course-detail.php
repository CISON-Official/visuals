<?php
/**
 * Vars: $course, $current_application, $has_other_pending
 */
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="cmp-wrap cmp-course-detail">
	<h2><?php echo esc_html($course->title); ?></h2>
	<p class="cmp-code"><?php echo esc_html($course->code); ?></p>
	<div class="cmp-prose"><?php echo wp_kses_post(wpautop($course->description)); ?></div>

	<h3><?php esc_html_e('Syllabus', 'cison-member-portal'); ?></h3>
	<ul class="cmp-syllabus">
		<li><strong>01</strong> —
			<?php esc_html_e('Foundations of Social Policy in Nigeria', 'cison-member-portal'); ?> <span
				class="cmp-muted"><?php esc_html_e('(Week 1-2)', 'cison-member-portal'); ?></span></li>
		<li><strong>02</strong> —
			<?php esc_html_e('Quantitative Methodologies and Field Research', 'cison-member-portal'); ?> <span
				class="cmp-muted"><?php esc_html_e('(Week 3-4)', 'cison-member-portal'); ?></span></li>
		<li><strong>03</strong> —
			<?php esc_html_e('Ethical Compliance Frameworks in Public Governance', 'cison-member-portal'); ?> <span
				class="cmp-muted"><?php esc_html_e('(Week 5-6)', 'cison-member-portal'); ?></span></li>
	</ul>
	<p class="cmp-muted">
		<em><?php esc_html_e('Syllabus modules above are placeholder content carried over unchanged from the original application — wire these up to real per-course data when that model exists.', 'cison-member-portal'); ?></em>
	</p>

	<div id="apply" class="cmp-apply-section">
		<?php if ($current_application): ?>
			<p>
				<?php
				/* translators: %s: application status */
				printf(esc_html__('Your application status: %s', 'cison-member-portal'), '<strong>' . esc_html(ucfirst($current_application->status)) . '</strong>');
				?>
			</p>
		<?php elseif ($has_other_pending): ?>
			<div class="cmp-notice cmp-notice-info">
				<?php esc_html_e('You have another application pending review, so you cannot apply for this course right now.', 'cison-member-portal'); ?>
			</div>
		<?php else: ?>
			<?php echo do_shortcode('[cmp_apply_course course_id="' . absint($course->id) . '"]'); ?>
		<?php endif; ?>
	</div>
</div>