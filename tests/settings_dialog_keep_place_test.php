<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Settings dialog keeps its place after a save: the form's scroll
 * stays and the card drawer the save came from opens again on the pane read
 * back, except after a button that removes the record and after a new
 * record's first save. pgSettingsKeepDrawer() in backend.src.js decides the
 * drawer; it is run under Node, skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_dialog_keep_place_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

// Runs pgSettingsKeepDrawer() on each [drawer, button] pair; null when the
// function is not found, false when Node cannot run.
function settings_dialog_keep_place_test_run($cases)
{
	if (!preg_match('/^window\.pgSettingsKeepDrawer = function \(drawer, button\) \{.*?^\};$/ms', settings_dialog_keep_place_test_source(), $m)) {
		return null;
	}

	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgkeep');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, 'var window = {};' . "\n" . $m[0] . "\n"
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) { return window.pgSettingsKeepDrawer(c[0], c[1]); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_settings_dialog_keep_place_drawer_rule()
{
	$cases = array(
		array('pgset-ws-agent-7', array('name' => 'ws_agent_save', 'value' => '7')),
		array('pgset-ws-agent-7', array('name' => 'ws_agent_test', 'value' => '7')),
		array('pgset-ws-mcp-3', null),
		array('pgset-ws-agent-7', array('name' => 'ws_agent_delete', 'value' => '7')),
		array('pgset-ws-mcp-3', array('name' => 'ws_mcp_delete', 'value' => '3')),
		array('pgset-ws-dept-2', array('name' => 'ws_department_archive', 'value' => '2')),
		array('pgset-ws-github-link', array('name' => 'ws_github_link_remove', 'value' => '4')),
		array('pgset-ws-agent-0', array('name' => 'ws_agent_save', 'value' => '')),
		array('pgset-ws-mcp-add', array('name' => 'ws_mcp_save', 'value' => '0')),
		array('pgset-ws-holiday-new', array('name' => 'ws_holiday_add', 'value' => '1')),
		array('pgset-ws-github-link', array('name' => 'ws_github_link_save', 'value' => '1')),
		array('', array('name' => 'ws_agent_save', 'value' => '7')),
		array(null, null),
	);

	$kept = settings_dialog_keep_place_test_run($cases);

	pg_assert_true($kept !== null, 'backend.src.js defines window.pgSettingsKeepDrawer');

	if ($kept === false) {
		return;
	}

	pg_assert_same(array(true, true, true, false, false, false, false, false, false, false, false, false, false), $kept, 'an existing record\'s drawer opens again; not after delete, remove or archive, not from an add drawer, not without a drawer');
}

// The save path keeps the form's scroll and hands the drawer over to the
// pane read back; "saved" is one toast in the dialog, not the notice at the
// top of the form the operator may have scrolled away from.
function test_settings_dialog_keep_place_save_path()
{
	$source = settings_dialog_keep_place_test_source();

	pg_assert_contains('window.pgSettingsKeepDrawer(from.id, button)', $source, 'the save asks which drawer opens again');
	pg_assert_contains('load(pane, place).done(', $source, 'the pane is read back with the place to return to');
	pg_assert_contains('form.scrollTop = place.scroll;', $source, 'paint() puts the form\'s scroll back');
	pg_assert_contains("savedToast = window.pgToast({ message: said, variant: 'success'", $source, 'saved is one toast');
	pg_assert_contains('savedToast.hide();', $source, 'the toast of the last save goes before the next one');
	pg_assert_contains('data-pgsaved=', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/modal.php'), 'the dialog carries the plain confirmation');
}
