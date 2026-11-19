<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests which nodes a drag of rows in the designer's Overview tree moves
 * (assets/js/style_designer.src.js, _sdTreeMoveSet()): a selected node inside
 * another selected node travels with it and is not moved on its own, and a
 * node holding the drop target is not moved into itself. Runs the function
 * under Node; skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_tree_move_set_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_tree_move_set_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Builds the tree below once per case, runs _sdTreeMoveSet() on the picked
// ids and the target id, and prints the ids it returns. Null without Node.
//
// root
// ├─ sd_1
// │  └─ sd_2
// │     └─ sd_4
// └─ sd_3
function designer_tree_move_set_node($cases)
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

	$fn = designer_tree_move_set_fn(designer_tree_move_set_source(), '_sdTreeMoveSet');

	if (!pg_assert_true($fn !== '', 'style_designer.js has _sdTreeMoveSet()')) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgtreeset');
	file_put_contents($file, $fn . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) {' . "\n"
		. '    var n4 = { _id: "sd_4", type: "semantic", children: [] };' . "\n"
		. '    var n2 = { _id: "sd_2", type: "semantic", children: [n4] };' . "\n"
		. '    var n1 = { _id: "sd_1", type: "semantic", children: [n2] };' . "\n"
		. '    var n3 = { _id: "sd_3", type: "semantic", children: [] };' . "\n"
		. '    var root = { _id: "root", type: "root", children: [n1, n3] };' . "\n"
		. '    var all = { root: root, sd_1: n1, sd_2: n2, sd_3: n3, sd_4: n4 };' . "\n"
		. '    return _sdTreeMoveSet(c.picked.map(function (id) { return all[id]; }), all[c.target]).map(function (n) { return n._id; });' . "\n"
		. '})));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_designer_tree_move_set_outer_nodes_only()
{
	$res = designer_tree_move_set_node(array(
		// sd_2 sits in sd_1: it travels inside sd_1, which is the one moved.
		array('picked' => array('sd_1', 'sd_2'), 'target' => 'sd_3'),
		array('picked' => array('sd_2', 'sd_1'), 'target' => 'sd_3'),
		// Deeper nesting and an unrelated node keep their order.
		array('picked' => array('sd_4', 'sd_3', 'sd_1'), 'target' => 'root'),
		// sd_1 holds the target sd_4: it cannot move next to its own child.
		array('picked' => array('sd_1', 'sd_3'), 'target' => 'sd_4'),
		// The target, the root and repeats are never moved.
		array('picked' => array('sd_3', 'sd_3', 'root', 'sd_4'), 'target' => 'sd_4'),
		// One plain row is moved as it is.
		array('picked' => array('sd_3'), 'target' => 'sd_1'),
	));

	if ($res === null) {
		return;
	}

	pg_assert_same(array('sd_1'), $res[0], 'a node inside a moved node is not moved again');
	pg_assert_same(array('sd_1'), $res[1], 'the order of the pick does not matter');
	pg_assert_same(array('sd_3', 'sd_1'), $res[2], 'the outer nodes keep their order');
	pg_assert_same(array('sd_3'), $res[3], 'a node holding the target stays');
	pg_assert_same(array('sd_3'), $res[4], 'no target, root or repeat');
	pg_assert_same(array('sd_3'), $res[5], 'a single row moves');
}

function test_designer_tree_move_set_drop_uses_it()
{
	$fn = designer_tree_move_set_fn(designer_tree_move_set_source(), 'buildTreeItems');

	pg_assert_true($fn !== '', 'style_designer.js has buildTreeItems()');
	pg_assert_same(1, substr_count($fn, '_sdTreeMoveSet('), 'the tree drop takes its moved nodes from _sdTreeMoveSet()');
	pg_assert_same(0, substr_count($fn, 'if (isDescendant(n, treeDragNode) && n !== treeDragNode) return;'), 'no node is inserted without being taken out first');
}
