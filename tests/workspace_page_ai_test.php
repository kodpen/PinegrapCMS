<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the live page tools of the built-in assistant and the model
 * assistants (includes/workspace/page_ai.php): the libraries and the
 * replacements as the model writes them, the replacements applied, the
 * read it is given in parts, the names and flat arguments.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page_ai.php');

// Libraries as a JSON list or names with commas; left out is not none.
function test_workspace_page_ai_libraries()
{
	pg_assert_same(array('chartjs'), ws_page_ai_libraries('chartjs')['names'], 'one name');
	pg_assert_same(array('chartjs'), ws_page_ai_libraries('["ChartJS", "chartjs"]')['names'], 'JSON, once');
	pg_assert_same(array('chartjs'), ws_page_ai_libraries(' chartjs , ')['names'], 'commas');
	pg_assert_same('d3', ws_page_ai_libraries('chartjs, d3')['error'], 'unknown named');
	pg_assert_false(ws_page_ai_libraries(null)['given'], 'left out');
	pg_assert_false(ws_page_ai_libraries('  ')['given'], 'empty is left out');
	$none = ws_page_ai_libraries('none');
	pg_assert_true($none['given'], 'none given');
	pg_assert_same(array(), $none['names'], 'none is no library');
	pg_assert_same('', $none['error'], 'none is no error');
}

// Replacements as JSON; one object is a list of one; find never empty.
function test_workspace_page_ai_replacements()
{
	$list = ws_page_ai_replacements('[{"find": "<h1>A</h1>", "replace": "<h1>B</h1>"}, {"find": "x"}]');
	pg_assert_same(2, count($list), 'two');
	pg_assert_same('', $list[1]['replace'], 'replace left out is removal');
	pg_assert_same(array(array('find' => 'a', 'replace' => 'b')), ws_page_ai_replacements('{"find": "a", "replace": "b"}'), 'one object');
	pg_assert_same(null, ws_page_ai_replacements('[{"find": ""}]'), 'empty find');
	pg_assert_same(null, ws_page_ai_replacements('[{"replace": "b"}]'), 'no find');
	pg_assert_same(null, ws_page_ai_replacements('[{"find": "a", "replace": 4}]'), 'replace not text');
	pg_assert_same(null, ws_page_ai_replacements('[]'), 'empty');
	pg_assert_same(null, ws_page_ai_replacements('not json'), 'not JSON');
	pg_assert_same(null, ws_page_ai_replacements(json_encode(array_fill(0, ws_page_ai_replacements_max() + 1, array('find' => 'a')))), 'too many');
}

// Applied in order, each find once in what the ones before left, all or none.
function test_workspace_page_ai_apply()
{
	$html = '<h1>Sales</h1><p>Q1</p>';
	$done = ws_page_ai_apply($html, array(array('find' => 'Sales', 'replace' => 'Sales 2026'), array('find' => 'Sales 2026</h1><p>Q1', 'replace' => 'Sales 2026</h1><p>Q2')));
	pg_assert_same('<h1>Sales 2026</h1><p>Q2</p>', $done['html'], 'in order');
	pg_assert_same('', $done['error'], 'no error');

	$missing = ws_page_ai_apply($html, array(array('find' => 'Q1', 'replace' => 'Q3'), array('find' => 'Q9', 'replace' => '')));
	pg_assert_same('missing', $missing['error'], 'missing');
	pg_assert_same(1, $missing['index'], 'which one');
	pg_assert_same('', $missing['html'], 'nothing changed');

	$twice = ws_page_ai_apply('<b>a</b><b>a</b>', array(array('find' => '<b>a</b>', 'replace' => '')));
	pg_assert_same('ambiguous', $twice['error'], 'twice');
	pg_assert_same(0, $twice['index'], 'first');
}

// The read: newest version, makers, libraries, the HTML in parts.
function test_workspace_page_ai_present()
{
	$rows = array(
		array('version_no' => 1, 'doc_json' => ws_page_version_doc(array('id' => 0, 'kind' => 'ai', 'handle' => 'ai')), 'size' => 10, 'user_id' => 3, 'source' => 'agent', 'created_at' => 0),
		array('version_no' => 2, 'doc_json' => ws_page_version_doc(array('id' => 7, 'kind' => 'model', 'handle' => 'gpt'), array('chartjs')), 'size' => 12, 'user_id' => 3, 'source' => 'agent', 'created_at' => 0),
	);
	$html = str_repeat('ç', ws_page_ai_read_max() + 5);
	$read = ws_page_ai_present($html, $rows, 42);

	pg_assert_same(42, $read['message_id'], 'message');
	pg_assert_same(2, $read['version'], 'newest');
	pg_assert_same(array('chartjs'), $read['libraries'], 'newest libraries');
	pg_assert_same('ai', $read['versions'][0]['agent'], 'maker of the first');
	pg_assert_false(array_key_exists('agent_id', $read['versions'][0]), 'no agent id for Pinegrap AI');
	pg_assert_same(7, $read['versions'][1]['agent_id'], 'model assistant id');
	pg_assert_same('1970-01-01 00:00 UTC', $read['versions'][0]['created_at'], 'date');
	pg_assert_same(ws_page_ai_read_max(), mb_strlen($read['html']), 'first part');
	pg_assert_same(ws_page_ai_read_max(), $read['next'], 'next part');
	pg_assert_same(ws_page_ai_read_max() + 5, $read['length'], 'length in characters');

	$rest = ws_page_ai_present($html, $rows, 42, $read['next']);
	pg_assert_same(5, mb_strlen($rest['html']), 'the rest');
	pg_assert_same(null, $rest['next'], 'the end');
	pg_assert_same(0, ws_page_ai_present('<p>x</p>', $rows, 42, -4)['from'], 'from not below 0');
	pg_assert_same('', ws_page_ai_present('<p>x</p>', $rows, 42, 999)['html'], 'from past the end');

	// What the model reads stays inside the 12000-character tool answer.
	pg_assert_true(ws_page_ai_read_max() < 12000, 'a part fits the tool answer');
}

// Every tool is a name the text-call reader knows, never a Visual Page
// Editor tool's name, and takes flat arguments.
function test_workspace_page_ai_tool_names()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

	foreach (ws_page_ai_tool_names() as $name) {
		pg_assert_true(in_array($name, ws_ai_tool_names(), true), $name);
		pg_assert_false(($name === 'read_page') || (strpos($name, 'page_') === 0), 'not a designer tool: ' . $name);
	}

	$tool = function ($name, $description, $properties, $required) {
		return array('name' => $name, 'properties' => $properties, 'required' => $required);
	};

	foreach (ws_page_ai_tools($tool) as $made) {
		pg_assert_true(in_array($made['name'], ws_page_ai_tool_names(), true), 'offered ' . $made['name']);

		foreach ($made['properties'] as $key => $property) {
			pg_assert_true(in_array($property['type'], array('string', 'integer'), true), $made['name'] . '.' . $key);
		}

		foreach ($made['required'] as $key) {
			pg_assert_true(isset($made['properties'][$key]), $made['name'] . ' requires ' . $key);
		}
	}
}
