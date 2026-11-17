<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a move made by dragging rows in the designer's Overview tree
 * keeps the moved elements selected (assets/js/style_designer.src.js,
 * _sdTreeMoveSelection()), as a move on the canvas does. Runs the function
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

function designer_tree_move_selection_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_tree_move_selection_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs _sdTreeMoveSelection() per case on nodes named by id and prints the
// selected id and the multi-selection's ids. Null without Node.
function designer_tree_move_selection_node($cases)
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

	$fn = designer_tree_move_selection_fn(designer_tree_move_selection_source(), '_sdTreeMoveSelection');

	if (!pg_assert_true($fn !== '', 'style_designer.js has _sdTreeMoveSelection()')) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgtreemove');
	file_put_contents($file, $fn . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) {' . "\n"
		. '    var all = {};' . "\n"
		. '    var get = function (id) { if (id === null) return null; return all[id] || (all[id] = { _id: id }); };' . "\n"
		. '    var s = _sdTreeMoveSelection(c.moved.map(get), get(c.primary));' . "\n"
		. '    return { node: s.node ? s.node._id : null, nodes: s.nodes.map(function (n) { return n._id; }) };' . "\n"
		. '})));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function test_designer_tree_move_selection_keeps_moved_nodes()
{
	$res = designer_tree_move_selection_node(array(
		// One row dragged: it is the selection, whatever was selected before.
		array('moved' => array('sd_4'), 'primary' => 'sd_4'),
		array('moved' => array('sd_4'), 'primary' => null),
		// A multi-selection moved together stays selected as a whole, with
		// the same primary.
		array('moved' => array('sd_2', 'sd_5', 'sd_7'), 'primary' => 'sd_5'),
		// Its primary was left behind (dropped onto its own row): the last
		// moved one takes its place.
		array('moved' => array('sd_2', 'sd_7'), 'primary' => 'sd_5'),
		// Nothing moved: nothing is selected.
		array('moved' => array(), 'primary' => 'sd_5'),
	));

	if ($res === null) {
		return;
	}

	pg_assert_same(array('node' => 'sd_4', 'nodes' => array()), $res[0], 'a single moved row is selected');
	pg_assert_same(array('node' => 'sd_4', 'nodes' => array()), $res[1], 'a moved row that was not selected becomes the selection');
	pg_assert_same(array('node' => 'sd_5', 'nodes' => array('sd_2', 'sd_5', 'sd_7')), $res[2], 'a multi-selection moved together stays selected');
	pg_assert_same(array('node' => 'sd_7', 'nodes' => array('sd_2', 'sd_7')), $res[3], 'the last moved node becomes primary when the primary stayed');
	pg_assert_same(array('node' => null, 'nodes' => array()), $res[4], 'no move, no selection');
}

function test_designer_tree_move_selection_drop_uses_it()
{
	$js = designer_tree_move_selection_source();
	$fn = designer_tree_move_selection_fn($js, 'buildTreeItems');

	pg_assert_true($fn !== '', 'style_designer.js has buildTreeItems()');
	pg_assert_same(2, substr_count($fn, '_sdTreeMoveSelect(nodesToMove)'), 'both tree-to-tree move endings select the moved nodes');
	pg_assert_same(0, substr_count($fn, "selectedNodes = [];\n                    selectedNode = null;\n                    render();"), 'the move into a shared component no longer clears the selection');
	pg_assert_same(0, substr_count($fn, "selectedNodes = [];\n            selectedNode = null;\n            render();"), 'the move among rows no longer clears the selection');
}
