<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation intentionally does NOT drop tables or delete data — that
 * only happens if the site owner explicitly deletes the plugin AND
 * uninstall.php runs (see uninstall.php).
 */
class CMP_Deactivator {

	public static function deactivate() {
		// Nothing to clean up on simple deactivate; data and tables stay.
	}
}
