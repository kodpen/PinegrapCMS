<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the designer's Undo and Redo keep the selection
 * (assets/js/style_designer.src.js, _sdStepSelection()): the selected
 * element is found again by its id in the restored tree and dropped only
 * when the step removed it. Runs the function under Node; skipped where
 * there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_undo_selection_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_undo_selection_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs _sdStepSelection() on a restored "tree" of ids and prints, per case,
// the selected id and the multi-selection's ids. Null without Node.
function designer_undo_selection_node($cases)
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

	$fn = designer_undo_selection_fn(designer_undo_selection_source(), '_sdStepSelection');

	if (!pg_assert_true($fn !== '', 'style_designer.js has _sdStepSelection()')) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgundosel');
	file_put_contents($file, $fn . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) {' . "\n"
		. '    var tree = {};' . "\n"
		. '    c.present.forEach(function (id) { tree[id] = { _id: id }; });' . "\n"
		. '    var s = _sdStepSelection(c.keep, function (id) { return tree[id] || null; });' . "\n"
		. '    return { node: s.node ? s.node._id : null, nodes: s.nodes.map(function (n) { return n._id; }),' . "\n"
		. '        fresh: s.node ? s.node === tree[s.node._id] : true };' . "\n"
		. '})));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_designer_undo_selection_kept_or_dropped()
{
	$res = designer_undo_selection_node(array(
		array('keep' => array('id' => 'sd_4', 'ids' => array()), 'present' => array('sd_1', 'sd_4')),
		array('keep' => array('id' => 'sd_4', 'ids' => array()), 'present' => array('sd_1')),
		array('keep' => array('id' => null, 'ids' => array()), 'present' => array('sd_1')),
		array('keep' => array('id' => 'sd_3', 'ids' => array('sd_2', 'sd_3', 'sd_5')), 'present' => array('sd_2', 'sd_3', 'sd_5')),
		array('keep' => array('id' => 'sd_5', 'ids' => array('sd_2', 'sd_3', 'sd_5')), 'present' => array('sd_2', 'sd_3')),
		array('keep' => array('id' => 'sd_3', 'ids' => array('sd_2', 'sd_3')), 'present' => array('sd_3')),
	));

	if ($res === null) {
		return;
	}

	pg_assert_same(array('node' => 'sd_4', 'nodes' => array(), 'fresh' => true), $res[0], 'the selected element is found again in the restored tree');
	pg_assert_same(array('node' => null, 'nodes' => array(), 'fresh' => true), $res[1], 'an element the step removed is no longer selected');
	pg_assert_same(array('node' => null, 'nodes' => array(), 'fresh' => true), $res[2], 'no selection stays no selection');
	pg_assert_same(array('node' => 'sd_3', 'nodes' => array('sd_2', 'sd_3', 'sd_5'), 'fresh' => true), $res[3], 'a multi-selection is kept');
	pg_assert_same(array('node' => 'sd_3', 'nodes' => array('sd_2', 'sd_3'), 'fresh' => true), $res[4], 'a removed primary hands over to the last one left');
	pg_assert_same(array('node' => 'sd_3', 'nodes' => array(), 'fresh' => true), $res[5], 'one element left is a plain selection');
}

function test_designer_undo_redo_reselect()
{
	$js = designer_undo_selection_source();

	foreach (array('undo', 'redo') as $name) {
		$body = designer_undo_selection_fn($js, $name);
		pg_assert_true(strpos($body, 'var keep = _sdStepSelectionKeep();') !== false, $name . '() keeps the selection before the step');
		pg_assert_true(strpos($body, '_sdStepReselect(keep);') !== false, $name . '() selects it again after the step');
		pg_assert_false(strpos($body, 'selectedNode = null;') !== false, $name . '() no longer drops the selection');
	}
}
