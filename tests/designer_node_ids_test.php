<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's node ids (assets/js/style_designer.src.js): each
 * saved tree numbers its nodes from sd_1, so the counter that names new
 * nodes is kept above the page tree and every loaded shared tree, a filled
 * in id never repeats one of its own tree, and an issue badge goes to the
 * element of the issue's own node. The functions are cut out and run under
 * Node (skipped on a machine without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_node_ids_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The named function, up to its closing brace at the same indentation.
function designer_node_ids_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    }\n", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to + 6 - $from);
}

// Runs a script over the cut functions; null without Node.
function designer_node_ids_test_run($body)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_node_ids_test_source();
	$script = "var tree = null; var nodeIdCounter = 0; var _sharedCache = {};\n"
		. "function gid() { return 'sd_' + (++nodeIdCounter); }\n"
		. "function _sdNodeObjects(n) {}\n";
	foreach (array('reindex', '_sdIdFloor', '_sdRaiseIdCounter', '_backfillIds') as $fn) {
		$script .= designer_node_ids_test_function($source, $fn) . "\n";
	}
	$script .= "function node(id, kids) { var n = { type: 'semantic', props: {}, children: kids || [] }; if (id) n._id = id; return n; }\n"
		. 'console.log(JSON.stringify((function () {' . $body . '})()));';
	$file = tempnam(sys_get_temp_dir(), 'pgnodeids');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A node added after a widget's tree loaded never takes one of its ids.
function test_designer_counter_above_shared_trees()
{
	$got = designer_node_ids_test_run(
		"tree = node('sd_1', [node('sd_2')]);\n"
		. "nodeIdCounter = reindex(tree);\n"
		. "_sharedCache[7] = { tree: node('sd_1', [node('sd_2'), node('sd_3', [node('sd_9')])]) };\n"
		. "_sdRaiseIdCounter(_sharedCache[7].tree);\n"
		. "var first = gid();\n"
		. "nodeIdCounter = _sdIdFloor();\n"
		. "return [first, _sdIdFloor(), gid()];"
	);

	if ($got !== null) {
		pg_assert_same(array('sd_10', 9, 'sd_10'), $got, 'the counter goes past the widget\'s sd_9, also after an undo resets it');
	}
}

// A filled-in id is never one the tree already holds.
function test_designer_backfill_skips_the_trees_own_ids()
{
	$got = designer_node_ids_test_run(
		"var t = node('sd_1', [node(''), node('sd_3'), node('', [node('sd_2')])]);\n"
		. "_backfillIds(t);\n"
		. "var ids = []; (function w(x) { ids.push(x._id); (x.children || []).forEach(w); })(t);\n"
		. "return ids;"
	);

	if ($got !== null) {
		pg_assert_same(array('sd_1', 'sd_4', 'sd_3', 'sd_5', 'sd_2'), $got, 'the empty ids get sd_4 and sd_5, not sd_1 and sd_2 again');
	}
}

// Ids of other shapes (a template's tpl_ ids, a number) count as none.
function test_designer_reindex_reads_sd_ids_only()
{
	$got = designer_node_ids_test_run(
		"return [reindex(node('tpl_ab12_3', [node('sd_4'), node('sd_x7')])), reindex({ _id: 12, children: [node('sd_2')] }), reindex(node('tpl_9'))];"
	);

	if ($got !== null) {
		pg_assert_same(array(4, 2, 0), $got);
	}
}

function test_designer_node_ids_wiring()
{
	$js = designer_node_ids_test_source();
	$badges = designer_node_ids_test_function($js, 'applyIssueBadges');

	pg_assert_contains('_sdIssueWrapsOf(g.node)', $badges, 'a badge goes to the issue\'s own node');
	pg_assert_false(strpos($badges, "canvasDoc.querySelector('.sd-wrap[data-sd-id=\"'") !== false, 'never the first element with the id');
	pg_assert_contains('if (_sdFindNodeForEl(node._id, w) === node) out.push(w);', designer_node_ids_test_function($js, '_sdIssueWrapsOf'));
	pg_assert_contains("if (parsed) _sdRaiseIdCounter(parsed);", $js, 'a loaded shared row raises the counter');
	pg_assert_contains("_restoreShared(prev.shared);\n        nodeIdCounter = _sdIdFloor();", $js, 'undo keeps the counter above the shared trees');
	pg_assert_contains("_restoreShared(nxt.shared);\n        nodeIdCounter = _sdIdFloor();", $js, 'redo too');
	pg_assert_false(strpos($js, 'nodeIdCounter = reindex(tree);') !== false, 'no load sets the counter from the page tree alone');
}
