<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registers the wp-admin "Member Portal" menu and processes every admin
 * action (approve/reject applications & exemptions, mark exam orders
 * paid/cancelled, save courses/examinations). Mirrors the actions found in
 * courses/admin.py (CourseApplicationAdmin, ExamRegistrationOrderAdmin,
 * ExemptionRequestAdmin, ExaminationAdmin, CourseAdmin).
 */
class CMP_Admin
{

	const CAP = 'manage_cmp_portal';

	public function __construct()
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_init', array($this, 'handle_actions'));
		add_action('admin_notices', array($this, 'render_notices'));
	}

	private function set_notice($type, $message)
	{
		set_transient('cmp_admin_notice_' . get_current_user_id(), array('type' => $type, 'message' => $message), 60);
	}

	public function render_notices()
	{
		$key = 'cmp_admin_notice_' . get_current_user_id();
		$notice = get_transient($key);
		if (!$notice) {
			return;
		}
		delete_transient($key);
		$css_class = 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr($css_class) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
	}

	public function register_menu()
	{
		add_menu_page(
			__('Member Portal', 'cison-member-portal'),
			__('Member Portal', 'cison-member-portal'),
			self::CAP,
			'cmp-courses',
			array($this, 'render_courses_page'),
			'dashicons-welcome-learn-more',
			26
		);

		add_submenu_page('cmp-courses', __('Courses', 'cison-member-portal'), __('Courses', 'cison-member-portal'), self::CAP, 'cmp-courses', array($this, 'render_courses_page'));
		add_submenu_page('cmp-courses', __('Course Applications', 'cison-member-portal'), __('Course Applications', 'cison-member-portal'), self::CAP, 'cmp-course-applications', array($this, 'render_course_applications_page'));
		add_submenu_page('cmp-courses', __('Examinations', 'cison-member-portal'), __('Examinations', 'cison-member-portal'), self::CAP, 'cmp-examinations', array($this, 'render_examinations_page'));
		add_submenu_page('cmp-courses', __('Exam Orders', 'cison-member-portal'), __('Exam Orders', 'cison-member-portal'), self::CAP, 'cmp-exam-orders', array($this, 'render_exam_orders_page'));
		add_submenu_page('cmp-courses', __('Exemption Requests', 'cison-member-portal'), __('Exemption Requests', 'cison-member-portal'), self::CAP, 'cmp-exemptions', array($this, 'render_exemptions_page'));
		add_submenu_page('cmp-courses', __('Fee Invoices', 'cison-member-portal'), __('Fee Invoices', 'cison-member-portal'), self::CAP, 'cmp-invoices', array($this, 'render_invoices_page'));
	}

	/* ---------------------------------------------------------------
	 * Page renderers — each just includes a view file.
	 * ------------------------------------------------------------- */

	public function render_courses_page()
	{
		$this->guard();
		$editing = isset($_GET['edit']) ? absint($_GET['edit']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$adding = isset($_GET['new']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($editing || $adding) {
			include CMP_PLUGIN_DIR . 'admin/views/course-edit.php';
		} else {
			include CMP_PLUGIN_DIR . 'admin/views/courses.php';
		}
	}

	public function render_course_applications_page()
	{
		$this->guard();
		include CMP_PLUGIN_DIR . 'admin/views/course-applications.php';
	}

	public function render_examinations_page()
	{
		$this->guard();
		$editing = isset($_GET['edit']) ? absint($_GET['edit']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$adding = isset($_GET['new']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($editing || $adding) {
			include CMP_PLUGIN_DIR . 'admin/views/examination-edit.php';
		} else {
			include CMP_PLUGIN_DIR . 'admin/views/examinations.php';
		}
	}

	public function render_exam_orders_page()
	{
		$this->guard();
		include CMP_PLUGIN_DIR . 'admin/views/exam-orders.php';
	}

	public function render_exemptions_page()
	{
		$this->guard();
		include CMP_PLUGIN_DIR . 'admin/views/exemptions.php';
	}

	public function render_invoices_page()
	{
		$this->guard();
		include CMP_PLUGIN_DIR . 'admin/views/invoices.php';
	}

	private function guard()
	{
		if (!current_user_can(self::CAP)) {
			wp_die(esc_html__('You do not have permission to access this page.', 'cison-member-portal'));
		}
	}

	/* ---------------------------------------------------------------
	 * Action handling (runs on admin_init, before any page HTML)
	 * ------------------------------------------------------------- */

	public function handle_actions()
	{
		if (empty($_REQUEST['cmp_admin_action']) || !current_user_can(self::CAP)) {
			return;
		}

		$action = sanitize_key(wp_unslash($_REQUEST['cmp_admin_action']));

		if (!isset($_REQUEST['cmp_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['cmp_admin_nonce'])), $action)) {
			wp_die(esc_html__('Security check failed. Please go back and try again.', 'cison-member-portal'));
		}

		switch ($action) {

			case 'cmp_approve_application':
				CMP_Courses::set_application_status(absint($_REQUEST['id']), 'approved');
				$this->redirect_back('cmp-course-applications');

			case 'cmp_reject_application':
				CMP_Courses::set_application_status(absint($_REQUEST['id']), 'rejected');
				$this->redirect_back('cmp-course-applications');

			case 'cmp_approve_exemption':
				CMP_Exemptions::set_status(absint($_REQUEST['id']), 'approved');
				$this->redirect_back('cmp-exemptions');

			case 'cmp_reject_exemption':
				CMP_Exemptions::set_status(absint($_REQUEST['id']), 'rejected');
				$this->redirect_back('cmp-exemptions');

			case 'cmp_mark_order_paid':
				CMP_Examinations::mark_order_paid(absint($_REQUEST['id']));
				$this->redirect_back('cmp-exam-orders');

			case 'cmp_cancel_order':
				CMP_Examinations::mark_order_cancelled(absint($_REQUEST['id']));
				$this->redirect_back('cmp-exam-orders');

			case 'cmp_set_exam_outcome':
				CMP_Examinations::set_exam_outcome(absint($_REQUEST['id']), sanitize_key(wp_unslash($_REQUEST['outcome'] ?? '')));
				$this->redirect_back('cmp-exam-orders');

			case 'cmp_save_course':
				CMP_Courses::save_course(wp_unslash($_POST), absint($_POST['course_id'] ?? 0));
				$this->set_notice('success', __('Course saved.', 'cison-member-portal'));
				$this->redirect_back('cmp-courses');

			case 'cmp_save_examination':
				$result = CMP_Examinations::save_examination(wp_unslash($_POST), absint($_POST['exam_id'] ?? 0));
				if (is_wp_error($result)) {
					$this->set_notice('error', $result->get_error_message());
				} else {
					$this->set_notice('success', __('Examination saved.', 'cison-member-portal'));
				}
				$this->redirect_back('cmp-examinations');

			default:
				return;
		}
	}

	private function redirect_back($page)
	{
		wp_safe_redirect(admin_url('admin.php?page=' . $page));
		exit;
	}
}
