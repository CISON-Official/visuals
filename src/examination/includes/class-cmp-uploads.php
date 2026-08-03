<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "supporting_document" PDF uploads used by course applications
 * and exemption requests. Mirrors the Django FileExtensionValidator(['pdf'])
 * behaviour, but stores the result as a WordPress media attachment (private,
 * not publicly listed) rather than a raw file path.
 */
class CMP_Uploads {

	/**
	 * @param string $field_name  Name of the $_FILES entry, e.g. 'supporting_document'.
	 * @return int|WP_Error       Attachment ID on success.
	 */
	public static function handle_pdf_upload( $field_name ) {
		if ( empty( $_FILES[ $field_name ] ) || empty( $_FILES[ $field_name ]['name'] ) ) {
			return new WP_Error( 'cmp_no_file', __( 'Please attach a supporting document.', 'cison-member-portal' ) );
		}

		$file = $_FILES[ $field_name ];

		$filetype = wp_check_filetype( $file['name'] );
		if ( 'pdf' !== strtolower( $filetype['ext'] ) ) {
			return new WP_Error( 'cmp_bad_filetype', __( 'Only PDF files are accepted for supporting documents.', 'cison-member-portal' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array( 'pdf' => 'application/pdf' ),
		);

		$attachment_id = media_handle_upload( $field_name, 0, array(), $overrides );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $attachment_id;
	}
}
