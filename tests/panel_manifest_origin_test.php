<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's web app manifest names its own addresses without a
 * host. The manifest is fetched from the host the panel was opened on, and
 * the panel is never redirected to the hostname setting: on an alias host
 * (the bare domain beside www, an IP address) a start_url, scope or shortcut
 * on the setting's origin is cross-origin, and browsers drop it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_manifest_addresses_carry_no_host()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/manifest.php');

	pg_assert_same(0, preg_match('/URL_SCHEME|HOSTNAME|HTTP_HOST/', $source), 'manifest.php builds no address on a host');
	pg_assert_true(strpos($source, '$software_url = PATH . SOFTWARE_DIRECTORY;') !== false, 'the panel address is a path');
}
