<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the Flows canvas offers a derived value box
 * (derivedOffer(), assets/js/workspace_flow.js): "Add value" and a thread
 * dropped on it offer only the boxes that come before every box reading it,
 * directly or through another value box, never a value box that reads it;
 * any box while nothing reads it. Before, the list offered every box, and a
 * value picked from a later one traded the box's missing second value for
 * "that box does not come before" problems at its readers. A long value's
 * chip wraps inside its text box, so its × stays in reach.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One function of the canvas script, as written (four spaces deep).
function workspace_flow_derived_offer_test_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	pg_assert_true($start !== false, $name . ' is in the script');

	if ($start === false) {
		return '';
	}

	$end = strpos($js, "\n    }\n", $start);

	return substr($js, $start, $end + 7 - $start);
}

// Runs a script under Node and answers what it printed as JSON; null when
// Node is not installed.
function workspace_flow_derived_offer_test_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgwsoffer');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// The flow behind it: a start, a constants box, an approval and a post that
// read the title box (a join), a task after them that reads it too, and a
// value box that reads the title. The title may read the start, the
// constants and the approval (before both of its readers that are steps),
// never the post, the task or the value box that reads it.
function test_workspace_flow_derived_offer_node()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	preg_match('~\n    var VALUE_TOKEN = [^\n]+\n~', $js, $token);
	pg_assert_true(!empty($token), 'the token pattern');

	$script = "'use strict';\n" . ($token[0] ?? '')
		. "var KINDS = { manual: { into: false, out: ['next'] }, constants: { into: false, pure: true, out: [] }, value: { into: false, pure: true, out: [] },"
		. " approval: { into: true, out: ['approved', 'rejected'] }, post: { into: true, out: ['done'] }, task: { into: true, out: ['done', 'timeout'] } };\n"
		. "function kindOf(node) { return KINDS[node.kind]; }\n"
		. "var state = { graph: { nodes: [\n"
		. " { id: 'start', kind: 'manual', config: {} },\n"
		. " { id: 'fixed', kind: 'constants', config: {} },\n"
		. " { id: 'title', kind: 'value', config: { a: '{{node.fixed.name}} -', b: '' } },\n"
		. " { id: 'more', kind: 'value', config: { a: '{{node.title.value}}', b: 'x' } },\n"
		. " { id: 'ask', kind: 'approval', config: { title: 'Go?' } },\n"
		. " { id: 'say', kind: 'post', config: { body: '{{node.title.value}}' } },\n"
		. " { id: 'work', kind: 'task', config: { title: '{{node.more.value}}' } },\n"
		. " { id: 'lone', kind: 'value', config: { a: 'y', b: 'z' } }\n"
		. "], edges: [{ from: 'start', to: 'ask', on: 'next' }, { from: 'ask', to: 'say', on: 'approved' }, { from: 'say', to: 'work', on: 'done' }] } };\n"
		. "function nodeById(id) { return state.graph.nodes.filter(function (n) { return n.id === id; })[0] || null; }\n"
		. "function fieldTexts(node) { return Object.keys(node.config).map(function (key) { return { key: key, text: node.config[key] }; }); }\n"
		. workspace_flow_derived_offer_test_function($js, 'arrowMaps')
		. workspace_flow_derived_offer_test_function($js, 'boxesBefore')
		. workspace_flow_derived_offer_test_function($js, 'derivedOffer')
		. "var ids = state.graph.nodes.map(function (n) { return n.id; });\n"
		. "function offered(id) { var test = derivedOffer(nodeById(id)); return ids.filter(function (other) { return test(other); }); }\n"
		. "console.log(JSON.stringify([offered('title'), offered('more'), offered('lone')]));\n";
	$answer = workspace_flow_derived_offer_test_node($script);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('start', 'fixed', 'ask', 'lone'), $answer[0], 'the title: before both readers, not the value box reading it');
	pg_assert_same(array('start', 'fixed', 'title', 'ask', 'say', 'lone'), $answer[1], 'a box read by a later task only: what comes before the task');
	pg_assert_same(array('start', 'fixed', 'title', 'more', 'ask', 'say', 'work'), $answer[2], 'nothing reads it: any box');
}

// The list and a dropped thread both ask it.
function test_workspace_flow_derived_offer_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_contains("var offer = (node.kind === 'value') ? derivedOffer(node) : null;", $js, 'Add value');
	pg_assert_contains("((node.kind === 'value') && !derivedOffer(node)(from))", $js, 'a thread dropped on it');
}

// A value's chip is never wider than its text box and wraps inside it; the
// flow list's filter chips keep their one line.
function test_workspace_flow_value_chip_wraps()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	preg_match('~\n\.wsf-values \.wsf-chip \{([^}]*)\}~', $css, $rule);

	pg_assert_true(!empty($rule), 'a rule of its own');
	pg_assert_contains('max-width: 100%;', $rule[1] ?? '', 'never wider than the box');
	pg_assert_contains('white-space: normal;', $rule[1] ?? '', 'wraps');
	pg_assert_contains('overflow-wrap: anywhere;', $rule[1] ?? '', 'even a long word');
	pg_assert_contains("white-space: nowrap;\n  user-select: all;", $css, 'the plain chip keeps one line');
}
