<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Repair Software screen's way to the upgrade screen
 * (software_repair.php): both cards redirect with a relative address, so the
 * upgrade screen keeps the scheme the visitor used instead of the site
 * setting's, which behind a proxy that ends TLS may say http for an https
 * panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_software_repair_upgrade_redirect_is_relative()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_repair.php');

	pg_assert_true(strpos($source, "\$software_upgrade_url = PATH . SOFTWARE_DIRECTORY . '/install/index.php?automated_upgrade=true';") !== false, 'the upgrade address is relative');
	pg_assert_true(strpos($source, "URL_SCHEME . \$_SERVER['HTTP_HOST']") === false, 'no address is built from the setting and the request host');
	pg_assert_same(2, substr_count($source, "header('Location: ' . \$software_upgrade_url);"), 'the repair card and the re-run card both use it');
}
