<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * get_system_status_checks() serves its result from a ten-minute cache. A
 * screen that changes what a tile reports drops that cache through
 * pg_system_status_forget(), so the next draw shows the new state. These
 * tests check the helper removes the file and pin the places that call it:
 * both Site Settings save paths, the webhook switch and Retry, the
 * Maintenance Mode switch and the e-Fatura threshold save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/efatura_threshold.php');

/**
 * A product file without its comments, so a call named only in a comment
 * does not count.
 *
 * @param string $path relative to pinegrap/
 * @return string
 */
function system_status_forget_code($path)
{
	$code = '';

	foreach (token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path)) as $token) {
		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}

		$code .= is_array($token) ? $token[1] : $token;
	}

	return $code;
}

/**
 * The text of $code from $start up to the first $end after it ('' when
 * $start is missing).
 *
 * @param string $code
 * @param string $start
 * @param string $end
 * @return string
 */
function system_status_forget_between($code, $start, $end)
{
	$from = strpos($code, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($code, $end, $from + strlen($start));

	return ($to === false) ? substr($code, $from) : substr($code, $from, $to - $from);
}

function test_system_status_forget_removes_the_cache()
{
	pg_assert_true(function_exists('pg_system_status_forget'), 'pg_system_status_forget() is defined');

	if (!function_exists('pg_system_status_forget')) {
		return;
	}

	$file = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';
	$kept = is_file($file) ? file_get_contents($file) : null;

	file_put_contents($file, '{"ts":0}');
	pg_system_status_forget();
	pg_assert_true(!is_file($file), 'the cache file is gone');

	// Nothing to drop is not an error.
	pg_system_status_forget();
	pg_assert_true(!is_file($file), 'a second call is harmless');

	if ($kept !== null) {
		file_put_contents($file, $kept);
	}
}

function test_system_status_forget_after_a_dialog_settings_save()
{
	$code = system_status_forget_between(system_status_forget_code('includes/settings/fragment.php'), 'function pg_settings_apply(', 'return isset($url_scheme)');
	$block = system_status_forget_between($code, 'if (!$liveform->check_form_errors()) {', '}');

	pg_assert_true($block !== '', 'pg_settings_apply() has its success block');
	pg_assert_true(strpos($block, 'pg_system_status_forget();') !== false, 'the dialog save drops the System Status cache when it wrote without errors');
}

function test_system_status_forget_after_a_page_settings_save()
{
	$code = system_status_forget_code('includes/settings/screen.php');
	$block = system_status_forget_between($code, 'if (!$liveform->check_form_errors()) {', '$liveform->add_notice(');

	pg_assert_true($block !== '', 'screen.php has its success block');
	pg_assert_true(strpos($block, 'pg_system_status_forget();') !== false, 'the page save drops the System Status cache when it wrote without errors');
}

function test_system_status_forget_after_a_webhook_switch_or_retry()
{
	$code = system_status_forget_code('api_settings.php');

	foreach (array('webhook_status', 'webhook_retry') as $action) {
		$branch = system_status_forget_between($code, "\$action === '" . $action . "'", 'exit();');

		pg_assert_true($branch !== '', 'api_settings.php has the ' . $action . ' branch');
		pg_assert_true(strpos($branch, 'pg_system_status_forget();') !== false, $action . ' drops the System Status cache');
	}
}

function test_system_status_forget_is_the_one_way_to_drop_the_cache()
{
	foreach (array('includes/settings/general.save.php', 'includes/erp/efatura_threshold.php', 'includes/settings/fragment.php', 'includes/settings/screen.php', 'api_settings.php') as $path) {
		pg_assert_true(strpos(system_status_forget_code($path), 'system_status_cache.json') === false, $path . ' drops the cache through pg_system_status_forget(), not by its file name');
	}

	pg_assert_true(strpos(pg_test_function_source('erp_efatura_threshold_forget_status'), 'pg_system_status_forget();') !== false, 'the e-Fatura threshold save uses the helper');

	$general = system_status_forget_code('includes/settings/general.save.php');
	$branch = system_status_forget_between($general, 'if ($maintenance_switched !== null) {', '}');

	pg_assert_true(strpos($branch, 'pg_system_status_forget();') !== false, 'the Maintenance Mode switch uses the helper');
}
