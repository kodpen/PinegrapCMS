<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the end of the Software Updater and Repair Software: the
 * replace step itself writes the Site Log line, the notice and the update flag
 * (pg_vendor_update_finish()), behind the panel action's own token check, and
 * both screens then go straight to the upgrade screen. Once the files are in
 * place validate_user() sends every panel address to the upgrade, so the old
 * GET completion addresses (mode=autoupgrade, mode=done) never ran; they are
 * gone, and with them a link that could be forged.
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

// The body of the function that starts at $opening, up to the next function.
function software_redirect_token_function($source, $opening)
{
	$start = strpos($source, $opening);
	pg_assert_true($start !== false, 'function found: ' . $opening);
	$end = strpos($source, "\nfunction ", $start + strlen($opening));
	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_software_completion_branches_are_gone()
{
	pg_assert_true(strpos(software_redirect_token_source('software_update.php'), 'autoupgrade') === false, 'no mode=autoupgrade branch');
	pg_assert_true(strpos(software_redirect_token_source('software_repair.php'), "'done'") === false, 'no mode=done branch');
}

function test_software_completion_goes_to_the_upgrade()
{
	$update = software_redirect_token_source('software_update.php');

	pg_assert_contains("window.location.replace(\"' . escape_javascript(\$software_upgrade_url) . '\");", $update, 'the updater goes to the upgrade screen');
	// The automated address only for an administrator: a designer or manager
	// gets the plain one, whose lock screen an administrator unlocks.
	pg_assert_contains("\$software_upgrade_url = PATH . SOFTWARE_DIRECTORY . '/install/index.php' . (((int) \$user['role'] === 0) ? '?automated_upgrade=true' : '');", $update, 'the address by role');
	pg_assert_contains("window.location.replace(\"' . escape_javascript(\$software_upgrade_url) . '\");", software_redirect_token_source('software_repair.php'), 'the repair goes to the upgrade screen');
}

function test_software_replace_step_finishes()
{
	$panel = software_redirect_token_function(software_redirect_token_source('includes/panel/software.php'), 'function pg_panel_software_update(');
	$token = strpos($panel, 'validate_token();');
	$record = strpos($panel, '$build_error = pg_vendor_update_record_build($pin, $installed_build);');
	$refused = strpos($panel, "'message' => \$build_error");
	$finish = strpos($panel, 'pg_vendor_update_finish($pin, $repair);');

	pg_assert_true($token !== false, 'the panel action checks the form token');
	pg_assert_true(($record !== false) && ($refused !== false) && ($finish !== false), 'the replace step records the build, then finishes');
	pg_assert_true(($token < $record) && ($record < $refused) && ($refused < $finish), 'finished only after the token, the build record and its refusal');
	pg_assert_same(1, substr_count($panel, 'pg_vendor_update_finish($'), 'one call');
}

function test_software_finish_writes()
{
	$finish = software_redirect_token_function(software_redirect_token_source('includes/fn/update.php'), 'function pg_vendor_update_finish(');

	foreach (array(
		"'Software repaired with the {var:1} package from the update server.'",
		"lang('Software files repaired.')",
		"lang('Software updated successfully')",
		"'system_status_cache.json', 'hash_reference_state.json'",
		'log_activity($text);',
		'db("UPDATE config SET software_update_available = 0");',
		"new liveform('welcome')",
	) as $part) {
		pg_assert_contains($part, $finish, 'pg_vendor_update_finish() ' . $part);
	}
}
