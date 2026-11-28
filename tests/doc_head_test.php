<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The document head (assets/js/pg_doc_head.js, window.pgDocHead): the
 * menu's groups and order, the one-row menu, the save state's words, the
 * time since a save, a renamed name made clean, the main button's look; the
 * words the script asks for printed by pg_doc_head_words(); the Design
 * examples pattern. The script parts run in Node; skipped where Node cannot
 * run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/look_examples.php');

// Runs `$code` in Node with window.pgDocHead loaded as D (no document, so
// nothing is mounted); its `out` comes back decoded. Null without Node.
function doc_head_test_node($code)
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

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_doc_head.js') . "\n"
		. "var D = window.pgDocHead, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgdochead');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_doc_head_menu_groups_in_their_order()
{
	$out = doc_head_test_node('
		var f = function () {};
		out = D.menuGroups({ "delete": f, help: f, own: [{ icon: "bi-x", label: "Columns", onClick: f }], print: f, copyLink: f, open: f, versions: { onClick: f, count: 3 } }).map(function (g) {
			return g.map(function (r) { return [r.key, r.label, r.danger, r.count, typeof r.run]; });
		});');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array(array('open', 'Open in a new tab', false, null, 'function')),
		array(array('copyLink', 'Copy link', false, null, 'function')),
		array(array('print', 'Print', false, null, 'function')),
		array(array('versions', 'Versions', false, 3, 'function')),
		array(array('own0', 'Columns', false, null, 'function')),
		array(array('help', 'Help', false, null, 'function'), array('delete', 'Delete', true, null, 'function')),
	), $out, 'groups in the fixed order, a group with no row left out, Delete last and in rose');
}

function test_doc_head_menu_phone_rows_first_and_one_row_menu()
{
	$out = doc_head_test_node('
		var f = function () {};
		var phone = D.menuGroups({ download: f }, [{ key: "undo", label: "Undo" }, { key: "redo", label: "Redo" }]);
		out = {
			phone: phone.map(function (g) { return g.map(function (r) { return r.key; }); }),
			single: D.singleRow(D.menuGroups({ help: f })).key,
			two: D.singleRow(D.menuGroups({ help: f, "delete": f })),
			none: D.singleRow(D.menuGroups({})),
			empty: D.menuGroups(null).length
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('undo', 'redo'), array('download')), $out['phone'], 'on a phone Undo and Redo come first');
	pg_assert_same('help', $out['single'], 'a menu of one row stands as that row');
	pg_assert_same(null, $out['two'], 'two rows are a menu');
	pg_assert_same(null, $out['none'], 'no row, no button');
	pg_assert_same(0, $out['empty'], 'no menu, no group');
}

function test_doc_head_state_words()
{
	$out = doc_head_test_node('
		var now = Date.UTC(2026, 10, 28, 12, 0, 0);
		var w = { "Saved": "Kaydedildi", "{var:1} min ago": "{var:1} dk önce" };
		out = {
			saving: D.stateView("saving", null, now),
			saved: D.stateView("saved", { time: now - 30000 }, now).text,
			agoNow: D.stateView("saved", { time: now - 30000, ago: true }, now).text,
			agoTwo: D.stateView("saved", { time: now - 125000, ago: true }, now, w).text,
			unsaved: D.stateView("unsaved", null, now),
			failed: D.stateView("failed", { reason: "The connection was lost." }, now),
			none: D.stateView(null, null, now),
			unknown: D.stateView("busy", null, now)
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('state' => 'saving', 'text' => 'Saving…', 'title' => 'Saving…'), $out['saving'], 'saving');
	pg_assert_same('Saved', $out['saved'], 'saved, without the time unless asked');
	pg_assert_same('Saved · Just now', $out['agoNow'], 'under a minute: Just now');
	pg_assert_same('Kaydedildi · 2 dk önce', $out['agoTwo'], 'minutes, in the given words');
	pg_assert_same('Unsaved changes', $out['unsaved']['text'], 'unsaved');
	pg_assert_same(array('state' => 'failed', 'text' => 'Could not save', 'title' => 'The connection was lost.'), $out['failed'], 'the reason is the tooltip');
	pg_assert_same(null, $out['none'], 'a view-only window shows none');
	pg_assert_same(null, $out['unknown'], 'an unknown state shows none');
}

function test_doc_head_name_and_main_button()
{
	$out = doc_head_test_node('
		out = {
			clean: D.cleanName("  Launch \n  plan  ", "Old"),
			empty: D.cleanName("   ", "Old"),
			fill: D.mainClass({ label: "Save" }),
			quiet: D.mainClass({ style: "quiet" }),
			word: D.word("Saved", { Saved: "Kaydedildi" }),
			key: D.word("Unknown word")
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same('Launch plan', $out['clean'], 'spaces trimmed and made one');
	pg_assert_same('Old', $out['empty'], 'an empty name keeps the one before');
	pg_assert_same('pg-button pg-button-fill pg-button-sm pg-doc-head-main', $out['fill'], 'Save and New row are filled');
	pg_assert_same('pg-button pg-button-quiet pg-button-sm pg-doc-head-main', $out['quiet'], 'the edit door is quiet');
	pg_assert_same('Kaydedildi', $out['word'], 'the caller\'s words first');
	pg_assert_same('Unknown word', $out['key'], 'a missing word shows its key');
}

function test_doc_head_words_cover_the_script()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_doc_head.js');
	$words = pg_doc_head_words();

	preg_match_all("/DH\\.word\\('([^']+)'/", $js, $asked);
	preg_match_all("/\\['bi-[a-z0-9-]+', '([^']+)'\\]/", $js, $rows);
	preg_match_all("/^\\s+[a-z]+: '([^']+)',?$/m", substr($js, strpos($js, 'DH.STATES'), 300), $states);

	$all = array_unique(array_merge($asked[1], $rows[1], $states[1]));

	pg_assert_true(count($all) >= 20, 'the scan finds the words');

	foreach ($all as $key) {
		pg_assert_true(isset($words[$key]), $key . ' is printed by pg_doc_head_words()');
	}

	pg_assert_contains('window.pgDocHeadWords = ', pg_doc_head_includes(), 'the page gets the words');
	pg_assert_same('', pg_doc_head_includes(), 'once per page');
}

function test_doc_head_example_pattern()
{
	$pattern = pg_look_examples_doc_head_pattern();

	pg_assert_true(!empty($pattern['phone']), 'the phone pane draws it');
	preg_match_all('/data-pg-doc-head="([^"]+)"/', $pattern['html'], $hosts);
	pg_assert_same(6, count($hosts[1]), 'a note, a table, the canvas, a Word and an Excel file, Quick Look');

	foreach ($hosts[1] as $json) {
		$options = json_decode(html_entity_decode($json, ENT_QUOTES, 'UTF-8'), true);
		pg_assert_true(is_array($options) && $options['name'] !== '', 'each host holds its options as JSON');
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/design_examples.php');
	pg_assert_same(2, substr_count($screen, 'pg_doc_head_includes()'), 'both pages print the script');
}
