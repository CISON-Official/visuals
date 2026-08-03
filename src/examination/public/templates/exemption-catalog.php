<?php
/**
 * Vars: $exams, $cleared_exam_ids, $pending_exemption_ids, $has_any_pending_request, $cooldown_map
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


include CMP_PLUGIN_DIR . 'includes/page_id.php';

$cleared_exam_ids      = array_map( 'intval', $cleared_exam_ids );
$pending_exemption_ids = array_map( 'intval', $pending_exemption_ids );
?>
<div class="cmp-wrap cmp-exemption-catalog">
	<h2><?php esc_html_e( 'Examination Exemptions', 'cison-member-portal' ); ?></h2>

	<?php if ( $has_any_pending_request ) : ?>
		<div class="cmp-notice cmp-notice-info">
			<?php esc_html_e( 'You have an exemption request pending review. You can submit another one once it has been decided.', 'cison-member-portal' ); ?>
		</div>
	<?php endif; ?>

	<div class="cmp-exam-list">
		<?php foreach ( $exams as $exam ) : ?>
			<?php
			$is_cleared    = in_array( (int) $exam->id, $cleared_exam_ids, true );
			$is_pending    = in_array( (int) $exam->id, $pending_exemption_ids, true );
			$cooldown_secs = isset( $cooldown_map[ $exam->id ] ) ? (int) $cooldown_map[ $exam->id ] : 0;
			?>
			<div class="cmp-exam-row">
				<span class="cmp-exam-code"><?php echo esc_html( $exam->code ); ?></span>
				<span class="cmp-exam-title"><?php echo esc_html( $exam->title ); ?></span>

				<?php if ( $is_cleared ) : ?>
					<span class="cmp-status cmp-status-approved"><?php esc_html_e( 'Cleared', 'cison-member-portal' ); ?></span>
				<?php elseif ( $is_pending ) : ?>
					<span class="cmp-status cmp-status-pending"><?php esc_html_e( 'Pending Review', 'cison-member-portal' ); ?></span>
				<?php elseif ( $cooldown_secs > 0 ) : ?>
					<span class="cmp-status cmp-status-rejected">
						<?php
						$days = (int) ceil( $cooldown_secs / DAY_IN_SECONDS );
						/* translators: %d: days remaining */
						printf( esc_html__( 'Cooldown: %d days left', 'cison-member-portal' ), $days );
						?>
					</span>
				<?php elseif ( $has_any_pending_request ) : ?>
					<span class="cmp-muted"><?php esc_html_e( 'Unavailable while another request is pending', 'cison-member-portal' ); ?></span>
				<?php else : ?>
					<a class="cmp-btn cmp-btn-small" href="<?php echo esc_url( add_query_arg( 'exam_id', $exam->id, get_permalink($page_id['exemption_apply']) ) ); ?>#exempt">
						<?php esc_html_e( 'Apply for Exemption', 'cison-member-portal' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

</div>
