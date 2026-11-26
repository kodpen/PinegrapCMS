<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the removal of "Clean up" (includes/fn/design_cleanup_remove.php
 * and the window's pure part): a rule's id, the rules cut out of a design's
 * stylesheets, own variables dropped from the model, what of a ticked list
 * is still unused, and the closing line's counts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_cleanup_remove.php');

function test_design_cleanup_remove_rule_rows_carry_their_place_and_id()
{
	$state = pg_cleanup_state(array());
	pg_cleanup_add_classes($state, 'used');
	$json = json_encode(array(
		array('name' => 'lib.css', 'type' => 'external-css', 'content' => 'https://cdn.example/lib.css'),
		array('name' => 'theme.css', 'type' => 'css', 'content' => ".used{}\n.gone{a:b}\n"),
	));
	$list = pg_cleanup_rule_list(pg_cleanup_own_sheets($json), $state);
	$row = $list['rows'][0];
	pg_assert_same(1, $row['index'], 'the place of the sheet in the asset list');
	pg_assert_same(array(8, 18), array($row['start'], $row['end']));
	pg_assert_same(pg_cleanup_rule_id(1, 8, '.gone{a:b}'), $row['id']);
	pg_assert_true(pg_cleanup_rule_id(1, 8, '.gone{a:c}') !== $row['id'], 'another text, another id');
	pg_assert_true(pg_cleanup_rule_id(1, 9, '.gone{a:b}') !== $row['id'], 'another place, another id');
}

function test_design_cleanup_remove_cuts_rules_and_their_line_end()
{
	$json = json_encode(array(
		array('name' => 'lib.css', 'type' => 'external-css', 'content' => 'https://cdn.example/lib.css'),
		array('name' => 'theme.css', 'type' => 'css', 'content' => ".a{}\n.b{x:y}\r\n.c{}"),
	));
	$out = json_decode(pg_cleanup_cut_rules($json, array(
		array('index' => 1, 'start' => 0, 'end' => 4),
		array('index' => 1, 'start' => 5, 'end' => 12),
		array('index' => 0, 'start' => 0, 'end' => 5),
		array('index' => 9, 'start' => 0, 'end' => 1),
	)), true);
	pg_assert_same('.c{}', $out[1]['content'], 'both rules go with the line end after each');
	pg_assert_same('https://cdn.example/lib.css', $out[0]['content'], 'a linked sheet is never touched');
	pg_assert_same('theme.css', $out[1]['name'], 'the row keeps its other keys');
	pg_assert_same('', pg_cleanup_cut_rules('not json', array()), 'an unreadable list gives nothing');
}

function test_design_cleanup_remove_drops_own_variables_only()
{
	$model = array('values' => array('primary' => 'x'), 'custom' => array(
		array('key' => 'accent_old', 'kind' => 'color'),
		array('key' => 'space_xxl', 'kind' => 'size'),
	));
	$out = pg_cleanup_drop_variables($model, array('accent_old', 'primary'));
	pg_assert_same(array(array('key' => 'space_xxl', 'kind' => 'size')), $out['custom']);
	pg_assert_same(array('primary' => 'x'), $out['values'], 'built-in values stay');
	pg_assert_same(null, pg_cleanup_drop_variables(null, array('a')));
}

function test_design_cleanup_remove_pick_keeps_only_what_is_still_listed()
{
	$lists = array(
		'rules'      => array('rows' => array(array('id' => 'r1', 'index' => 0, 'start' => 0, 'end' => 4))),
		'files'      => array('rows' => array(array('id' => 7, 'name' => 'a.png'))),
		'components' => array('rows' => array(array('id' => 3, 'name' => 'Banner'))),
		'variables'  => array('rows' => array(array('key' => 'accent_old'))),
	);
	$pick = pg_cleanup_remove_pick($lists, array(
		'rules' => array('r1', 'r2'), 'files' => array('7', 8), 'components' => array(4), 'variables' => array('accent_old'),
	));
	pg_assert_same(array('r1'), array_map(function ($r) {
		return $r['id'];
	}, $pick['rules']));
	pg_assert_same(array(7), array_map(function ($r) {
		return $r['id'];
	}, $pick['files']), 'an id sent as text matches');
	pg_assert_same(array(), $pick['components']);
	pg_assert_same(array('accent_old'), $pick['variables']);
	pg_assert_same(array(
		array('kind' => 'rules', 'id' => 'r2', 'reason' => 'changed'),
		array('kind' => 'files', 'id' => '8', 'reason' => 'changed'),
		array('kind' => 'components', 'id' => '4', 'reason' => 'changed'),
	), $pick['failed'], 'what is no longer listed is not removed');
}

function _dc_remove_js($expr)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = @tempnam(sys_get_temp_dir(), 'pgcr');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_design_cleanup.js')
		. "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return json_decode(implode("\n", $out), true);
}

function test_design_cleanup_window_removal_request_and_outcome()
{
	$got = _dc_remove_js('(function () { var C = PgDesignCleanup;'
		. ' var l = C.lists({ status: "success", rules: { rows: [{ id: "a", bytes: 400 }, { id: "b", bytes: 100 }] }, files: { rows: [{ id: 7, bytes: 1000 }] }, variables: { rows: [{ key: "x" }] } });'
		. ' var marks = { "rules:a": true, "rules:b": true, "files:7": true, "variables:x": true };'
		. ' return { keys: l[0].rows.map(function (r) { return r.key; }), ask: C.removal(l, marks),'
		. ' all: C.outcome(l, marks, {}), some: C.outcome(l, marks, { "rules:b": "changed" }) }; })()');
	if ($got === null) {
		return;
	}
	pg_assert_same(array('rules:a', 'rules:b'), $got['keys'], 'a rule is known by its id');
	pg_assert_same(array('rules' => array('a', 'b'), 'files' => array(7), 'components' => array(), 'variables' => array('x')), $got['ask']);
	pg_assert_same(array('of' => 4, 'count' => 4, 'bytes' => 1500, 'kinds' => array('rules' => 2, 'files' => 1, 'variables' => 1)), $got['all']);
	pg_assert_same(array('of' => 4, 'count' => 3, 'bytes' => 1400, 'kinds' => array('rules' => 1, 'files' => 1, 'variables' => 1)), $got['some']);
}

function test_design_cleanup_window_keeps_rows_that_stayed()
{
	$got = _dc_remove_js('(function () { var C = PgDesignCleanup;'
		. ' var before = C.lists({ status: "success", rules: { rows: [{ id: "a", sheet: "t.css", text: ".x{}", media: "" }] }, files: { rows: [{ id: 7 }] } });'
		. ' var after = C.lists({ status: "success", rules: { rows: [{ id: "c", sheet: "t.css", text: ".x{}", media: "" }] } });'
		. ' var k = C.keep(after, before, { "rules:a": "changed", "files:7": "Access denied." });'
		. ' return { rules: k[0].rows.map(function (r) { return [r.key, r.failed || ""]; }), files: k[1].rows.map(function (r) { return [r.key, r.failed || ""]; }) }; })()');
	if ($got === null) {
		return;
	}
	pg_assert_same(array(array('rules:c', 'changed')), $got['rules'], 'the same rule read again carries the reason');
	pg_assert_same(array(array('files:7', 'Access denied.')), $got['files'], 'a row read no more stays listed');
}
