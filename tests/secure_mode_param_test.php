<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the secure_mode query parameter in init.php and its
 * get_file.php copy: on a Secure Mode site (URL_SCHEME https) the parameter
 * never skips the http to https redirect and never sends the visitor to
 * http; the https to http switch is left only to a site without Secure Mode,
 * as a temporary redirect.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The secure-mode block of a file: from the secure_mode read to the first
// session cookie setting after it.
function secure_mode_param_test_block($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\$secure_mode = isset(\$_GET['secure_mode'])");
	pg_assert_true($start !== false, $file . ': secure_mode read found');
	$end = strpos($source, "session.cookie_secure", (int) $start);
	pg_assert_true($end !== false, $file . ': block end found');

	return substr($source, (int) $start, (int) $end - (int) $start);
}

// The parameter no longer takes part in the forced https redirect.
function test_secure_mode_param_does_not_skip_https_redirect()
{
	foreach (array('init.php', 'get_file.php') as $file) {
		$block = secure_mode_param_test_block($file);
		pg_assert_false(strpos($block, "\$secure_mode !== 'false'") !== false, $file . ': no skip of the https redirect');
	}
}

// The https to http switch runs only when URL_SCHEME is not https, and as a
// 302: a 301 would stay cached in the visitor's browser.
function test_secure_mode_param_http_switch_only_without_secure_mode()
{
	foreach (array('init.php', 'get_file.php') as $file) {
		$block = secure_mode_param_test_block($file);
		$switch = strpos($block, "\$secure_mode === 'false'");
		pg_assert_true($switch !== false, $file . ': switch kept for a site without Secure Mode');
		$condition = substr($block, (int) strrpos(substr($block, 0, (int) $switch), 'elseif'));
		$condition = substr($condition, 0, (int) strpos($condition, '{'));
		pg_assert_contains("URL_SCHEME !== 'https://'", $condition, $file . ': switch guarded by URL_SCHEME');

		pg_assert_same(1, substr_count($block, "header('Location: http://"), $file . ': one http Location');
		$line = substr($block, (int) strpos($block, "header('Location: http://"));
		$line = substr($line, 0, (int) strpos($line, ';'));
		pg_assert_contains('true, 302)', $line, $file . ': switch is a 302');
	}
}
