<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer part 'versions' (assets/js/style_designer_versions.js): the
 * rows a started new version adds to the editor's versions block, and the
 * hooks the core gives it (the tab menu, the palette, the Publish sheet's
 * foot). Run in Node where it can.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` with the part's API as `api`; its `out` comes back decoded.
// Null when Node cannot run.
function designer_versions_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_versions.js');
	$kit = 'var K = { _sdT: function (k, v) { var l = Array.isArray(v) ? v : [v]; return String(k).replace(/\{var(?::(\d+))?\}/g, function (m, n) { return String(l[n ? n - 1 : 0]); }); },'
		. ' esc: function (s) { return String(s); } };';
	$file = @tempnam(sys_get_temp_dir(), 'pgversions');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $kit . "\nvar api = null;\nvar StyleDesigner = { definePart: function (n, f) { api = f(K); } };\n"
		. $part . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_designer_versions_entries()
{
	$out = designer_versions_node('out = api.entries({ bundle_id: 3, draft_page_id: 41, live_page_id: 12, draft_name: "about-copy" }, "about");');

	if ($out === null) {
		return;
	}

	$row = array('bundle_id' => 3, 'draft_page_id' => 41, 'live_page_id' => 12, 'draft_name' => 'about-copy', 'live_name' => 'about');
	pg_assert_same(array('role' => 'version') + $row, $out['41'], 'the draft is the version, naming its live page');
	pg_assert_same(array('role' => 'live') + $row, $out['12'], 'the live page names its draft');
	pg_assert_same(2, count($out), 'two rows, by page id');
}

function test_designer_versions_core_hooks()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$sheet = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_true(strpos($js, "case 'version':  _pgVersionCall('start', key);") !== false, 'the tab menu starts or opens a version');
	pg_assert_true(strpos($js, "case 'version-discard': _pgVersionCall('discard', key);") !== false, 'the draft\'s tab menu discards it');
	pg_assert_true(strpos($js, "id: 'act_new_version'") !== false, 'the command palette has the row');
	pg_assert_true(strpos($js, "run: function (key) { _pgVersionCall('start', key); }") !== false, 'the Publish sheet\'s bridge runs the part');
	pg_assert_true(strpos($sheet, "act === 'new-version'") !== false, 'the sheet\'s foot button is bound');
	pg_assert_true(strpos($screen, 'data-sdp-act="new-version"') !== false, 'the sheet has the foot button');
	pg_assert_true(strpos($screen, "lang('The page on the site stays as it is.')") !== false, 'with its line under it');
	pg_assert_true(strpos($screen, "'versionsReady' => pg_page_bundles_ready()") !== false, 'the door waits for the upgrade');
}

// Publish from the sheet: the part posts version_publish, says a refusal
// other than a changed live page, and on success closes the draft's tab and
// lays the page as the site has it onto the live tab.
function test_designer_versions_publish()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_versions.js');
	$fn = substr($part, strpos($part, 'function publish('));

	pg_assert_contains("K._pgApiPost('version_publish', { page_id: p.page_id, replace: replace ? 1 : 0, session_key: K._collabKey() })", $fn, 'with the editor\'s own lock key');
	pg_assert_contains('if (!res.changed) K.sdToast(', $fn, 'a changed page is asked by the sheet, not toasted');
	$done = substr($part, strpos($part, 'function published('));
	pg_assert_true(strpos($done, 'forget(own)') < strpos($done, 'closeTab(p, own)'), 'the version leaves the state, then its tab');
	pg_assert_contains('K._pgContentLay(live, sp.tree || K.createDefaultTree(), sp, true)', $done);
	pg_assert_contains('publish: publish', $part);
}
