<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Public-facing shortcodes. Each one is a rough front-end equivalent of a
 * Django CBV from courses/views.py. Form POSTs are intercepted on
 * template_redirect (before any HTML is sent) so we can wp_safe_redirect()
 * the same way Django's redirect()-after-POST pattern works, then flash a
 * one-time notice via a transient (WordPress' answer to django.contrib.messages).
 */
class CMP_Shortcodes
{

	public function __construct()
	{
		add_shortcode('cmp_course_catalog', array($this, 'render_course_catalog'));
		add_shortcode('cmp_course_detail', array($this, 'render_course_detail'));
		add_shortcode('cmp_apply_course', array($this, 'render_apply_course'));
		add_shortcode('cmp_exam_register', array($this, 'render_exam_register'));
		add_shortcode('cmp_exemption_catalog', array($this, 'render_exemption_catalog'));
		add_shortcode('cmp_apply_exemption', array($this, 'render_apply_exemption'));
		add_shortcode('cmp_review_tracking', array($this, 'render_review_tracking'));

		add_action('template_redirect', array($this, 'handle_form_submissions'));
	}

	/* ---------------------------------------------------------------
	 * Flash notices (django.contrib.messages equivalent)
	 * ------------------------------------------------------------- */

	private function set_notice($type, $message)
	{
		set_transient('cmp_notice_' . get_current_user_id(), array('type' => $type, 'message' => $message), 60);
	}

	private function get_notice()
	{
		$key = 'cmp_notice_' . get_current_user_id();
		$notice = get_transient($key);
		if ($notice) {
			delete_transient($key);
		}
		return $notice;
	}

	private function render_notice_html()
	{
		$notice = $this->get_notice();
		if (!$notice) {
			return '';
		}
		$css_class = 'cmp-notice cmp-notice-' . sanitize_html_class($notice['type']);
		return '<div class="' . esc_attr($css_class) . '">' . esc_html($notice['message']) . '</div>';
	}

	private function login_prompt()
	{
		return '<div class="cmp-login-prompt"><p>' .
			esc_html__('Please log in to access the member portal.', 'cison-member-portal') .
			'</p><a class="cmp-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">' .
			esc_html__('Log In', 'cison-member-portal') . '</a></div>';
	}

	private function template($name, $vars = array())
	{
		$path = CMP_PLUGIN_DIR . 'public/templates/' . $name . '.php';
		if (!file_exists($path)) {
			return '';
		}
		extract($vars); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		include $path;
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_course_catalog]
	 * ------------------------------------------------------------- */

	public function render_course_catalog($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$user_id = get_current_user_id();

		return $this->render_notice_html() . $this->template(
			'course-catalog',
			array(
				'courses' => CMP_Courses::get_active_courses(),
				'enrolled_course_ids' => CMP_Courses::get_enrolled_course_ids($user_id),
				'pending_course_ids' => CMP_Courses::get_pending_course_ids($user_id),
				'has_pending_application' => CMP_Courses::has_pending_application($user_id),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_course_detail course_id="123"]
	 * ------------------------------------------------------------- */

	public function render_course_detail()
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}

		// Fetch course_id strictly from URL arguments
		$course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$course = CMP_Courses::get_course($course_id);
		if (!$course) {
			return '<p>' . esc_html__('Course not found.', 'cison-member-portal') . '</p>';
		}

		$user_id = get_current_user_id();

		return $this->render_notice_html() . $this->template(
			'course-detail',
			array(
				'course' => $course,
				'current_application' => CMP_Courses::get_application_for_course($user_id, $course_id),
				'has_other_pending' => CMP_Courses::has_pending_application($user_id) &&
					!(CMP_Courses::get_application_for_course($user_id, $course_id) && 'pending' === CMP_Courses::get_application_for_course($user_id, $course_id)->status),
			)
		);
	}


	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_apply_course course_id="123"]
	 * ------------------------------------------------------------- */

	public function render_apply_course($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$atts = shortcode_atts(array('course_id' => 0), $atts);
		$course_id = absint($atts['course_id'] ? $atts['course_id'] : ($_GET['course_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = get_current_user_id();

		$course = CMP_Courses::get_course($course_id);
		if (!$course) {
			return '<p>' . esc_html__('Course not found or not currently accepting applications.', 'cison-member-portal') . '</p>';
		}

		if (CMP_Courses::has_pending_application($user_id) || CMP_Courses::get_application_for_course($user_id, $course_id)) {
			return $this->render_notice_html() . '<p>' . esc_html__('You are not eligible to apply for this course right now — see the notice above, or check the catalog for your application status.', 'cison-member-portal') . '</p>';
		}

		return $this->render_notice_html() . $this->template(
			'course-apply-form',
			array('course' => $course)
		);
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_exam_register]
	 * ------------------------------------------------------------- */

	public function render_exam_register($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$user_id = get_current_user_id();

		return $this->render_notice_html() . $this->template(
			'exam-register',
			array(
				'level_1_exams' => CMP_Examinations::get_by_level(1),
				'level_2_exams' => CMP_Examinations::get_by_level(2),
				'level_3_exams' => CMP_Examinations::get_by_level(3),
				'passed_exam_ids' => CMP_Examinations::get_passed_exam_ids($user_id),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_exemption_catalog]
	 * ------------------------------------------------------------- */

	public function render_exemption_catalog($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$user_id = get_current_user_id();

		$passed_or_exempt = array_unique(
			array_merge(
				CMP_Examinations::get_paid_exam_ids($user_id),
				wp_list_pluck(array_filter(CMP_Exemptions::get_all_requests(), function ($r) use ($user_id) {
					return (int) $r->user_id === (int) $user_id && 'approved' === $r->status;
				}), 'exam_id')
			)
		);

		return $this->render_notice_html() . $this->template(
			'exemption-catalog',
			array(
				'exams' => CMP_Examinations::get_all(),
				'cleared_exam_ids' => $passed_or_exempt,
				'pending_exemption_ids' => CMP_Exemptions::get_pending_exemption_ids($user_id),
				'has_any_pending_request' => CMP_Exemptions::has_any_pending($user_id),
				'cooldown_map' => CMP_Exemptions::get_cooldown_map($user_id),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_apply_exemption exam_id="123"]
	 * ------------------------------------------------------------- */

	public function render_apply_exemption($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$atts = shortcode_atts(array('exam_id' => 0), $atts);
		$exam_id = absint($atts['exam_id'] ? $atts['exam_id'] : ($_GET['exam_id'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = get_current_user_id();

		$exam = CMP_Examinations::get_exam($exam_id);
		if (!$exam) {
			return '<p>' . esc_html__('Examination not found.', 'cison-member-portal') . '</p>';
		}

		$check = CMP_Exemptions::validate_can_apply($user_id, $exam_id);
		if (is_wp_error($check)) {
			return '<div class="cmp-notice cmp-notice-error">' . esc_html($check->get_error_message()) . '</div>';
		}

		return $this->render_notice_html() . $this->template(
			'exemption-apply-form',
			array('exam' => $exam)
		);
	}

	/* ---------------------------------------------------------------
	 * Shortcode: [cmp_review_tracking]
	 * ------------------------------------------------------------- */

	public function render_review_tracking($atts)
	{
		if (!is_user_logged_in()) {
			return $this->login_prompt();
		}
		$user_id = get_current_user_id();

		return $this->render_notice_html() . $this->template(
			'review-tracking',
			array(
				'exemptions' => CMP_Exemptions::get_requests_for_user($user_id),
				'course_applications' => CMP_Courses::get_applications_for_user($user_id),
			)
		);
	}

	/* ---------------------------------------------------------------
	 * Form submission handling
	 * ------------------------------------------------------------- */

	public function handle_form_submissions()
	{
		if (empty($_POST['cmp_action'])) {
			return;
		}

		if (!is_user_logged_in()) {
			wp_safe_redirect(wp_login_url(wp_get_referer()));
			exit;
		}

		$action = sanitize_key(wp_unslash($_POST['cmp_action']));
		$user_id = get_current_user_id();
		$referer = wp_get_referer() ? wp_get_referer() : home_url('/');

		if (!isset($_POST['cmp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmp_nonce'])), $action)) {
			$this->set_notice('error', __('Your session expired, please try again.', 'cison-member-portal'));
			wp_safe_redirect($referer);
			exit;
		}

		switch ($action) {

			case 'cmp_apply_course':
				$course_id = absint($_POST['course_id'] ?? 0);
				$statement = sanitize_textarea_field(wp_unslash($_POST['statement_of_purpose'] ?? ''));

				$attachment_id = CMP_Uploads::handle_pdf_upload('supporting_document');
				if (is_wp_error($attachment_id)) {
					$this->set_notice('error', $attachment_id->get_error_message());
					wp_safe_redirect($referer);
					exit;
				}

				$result = CMP_Courses::submit_application($user_id, $course_id, $statement, $attachment_id);
				if (is_wp_error($result)) {
					$this->set_notice('error', $result->get_error_message());
				} else {
					$course = CMP_Courses::get_course($course_id);
					/* translators: %s: course title */
					$this->set_notice('success', sprintf(__('Application for %s submitted successfully!', 'cison-member-portal'), $course ? $course->title : ''));
				}
				wp_safe_redirect($referer);
				exit;

			case 'cmp_register_exams':
				$selected_exam_ids = isset($_POST['selected_exams']) ? array_map('absint', (array) $_POST['selected_exams']) : array();

				$result = CMP_Examinations::register_exams($user_id, $selected_exam_ids);
				if (is_wp_error($result)) {
					$this->set_notice('error', $result->get_error_message());
					wp_safe_redirect($referer);
					exit;
				}

				$invoice = CMP_Invoices::get_invoice($result['invoice_id']);
				$wc = new CMP_WooCommerce();
				$pay_url = $wc->create_order_for_invoice($invoice);

				if (is_wp_error($pay_url)) {
					$this->set_notice('error', $pay_url->get_error_message());
					wp_safe_redirect($referer);
					exit;
				}

				wp_safe_redirect($pay_url);
				exit;

			case 'cmp_submit_exemption':
				$exam_id = absint($_POST['exam_id'] ?? 0);
				$reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

				$attachment_id = CMP_Uploads::handle_pdf_upload('supporting_document');
				if (is_wp_error($attachment_id)) {
					$this->set_notice('error', $attachment_id->get_error_message());
					wp_safe_redirect($referer);
					exit;
				}

				$result = CMP_Exemptions::submit_exemption($user_id, $exam_id, $reason, $attachment_id);
				if (is_wp_error($result)) {
					$this->set_notice('error', $result->get_error_message());
				} else {
					$exam = CMP_Examinations::get_exam($exam_id);
					/* translators: %s: exam code */
					$this->set_notice('success', sprintf(__('Exemption application for %s submitted successfully.', 'cison-member-portal'), $exam ? $exam->code : ''));
				}
				wp_safe_redirect($referer);
				exit;

			default:
				return;
		}
	}
}
