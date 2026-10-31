<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Software Updater and Repair Software completion
 * steps: the JavaScript redirect to mode=autoupgrade / mode=done carries the
 * session form token, and both GET branches check it before they log, change
 * config or delete caches.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function software_redirect_token_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// Returns the body of the GET branch that starts at $opening, up to its exit().
function software_redirect_token_branch($source, $opening)
{
	$start = strpos($source, $opening);
	pg_assert_true($start !== false, 'branch found: ' . $opening);
	$end = strpos($source, 'exit();', $start);
	pg_assert_true($end !== false, 'branch ends with exit(): ' . $opening);
	return substr($source, $start, $end - $start);
}

function software_redirect_token_check_first($branch, $label, array $writes)
{
	$token = strpos($branch, 'validate_token_field();');
	pg_assert_true($token !== false, $label . ' checks the form token');
	foreach ($writes as $write) {
		$at = strpos($branch, $write);
		pg_assert_true($at !== false, $label . ' still has ' . $write);
		pg_assert_true($token < $at, $label . ' checks the token before ' . $write);
	}
}

function test_software_update_autoupgrade_checks_the_token_first()
{
	$branch = software_redirect_token_branch(software_redirect_token_source('software_update.php'), "if (\$mode === 'autoupgrade') {");
	software_redirect_token_check_first($branch, 'autoupgrade', array('log_activity(', 'db("UPDATE config SET software_update_available = 0")', 'header('));
}

function test_software_repair_done_checks_the_token_first()
{
	$branch = software_redirect_token_branch(software_redirect_token_source('software_repair.php'), "if (isset(\$_GET['mode']) && (\$_GET['mode'] === 'done')) {");
	software_redirect_token_check_first($branch, 'repair done', array('unset($_SESSION[\'software\'][\'repair\'])', 'log_activity(', 'db("UPDATE config SET software_update_available = 0")', '@unlink(', 'header('));
}

function test_software_completion_redirects_carry_the_token()
{
	pg_assert_contains('window.location.replace("?mode=autoupgrade&token=" + encodeURIComponent(software_token))', software_redirect_token_source('software_update.php'), 'update redirect token');
	pg_assert_contains('window.location.replace("software_repair.php?mode=done&token=" + encodeURIComponent(software_token))', software_redirect_token_source('software_repair.php'), 'repair redirect token');
}
