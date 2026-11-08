<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas tools of the built-in assistant and the model
 * assistants (includes/workspace/canvas_ai.php): who a version names as
 * its maker, the operations as the model writes them, the read it is given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// A model assistant names itself; Pinegrap AI otherwise.
function test_workspace_canvas_ai_by()
{
	pg_assert_same(array('id' => 7, 'kind' => 'model', 'handle' => 'gpt'), ws_canvas_ai_by(array('id' => '7', 'handle' => 'gpt', 'model' => 'x')), 'model assistant');
	pg_assert_same(array('id' => 0, 'kind' => 'ai', 'handle' => 'ai'), ws_canvas_ai_by(null), 'Pinegrap AI');
	pg_assert_same('ai', ws_canvas_ai_by(array('id' => 0))['kind'], 'no row id');
}

// The model writes the list as JSON text; one object alone is a list of one.
function test_workspace_canvas_ai_operations()
{
	$list = ws_canvas_ai_operations('[{"op": "text", "layer": "l2", "text": "Q4"}, {"op": "remove", "layer": "l1"}]');
	pg_assert_same(2, count($list), 'two from JSON');
	pg_assert_same('remove', $list[1]['op'], 'in order');
	pg_assert_same(array(array('op' => 'remove', 'layer' => 'l1')), ws_canvas_ai_operations('{"op": "remove", "layer": "l1"}'), 'one object');
	pg_assert_same(1, count(ws_canvas_ai_operations(array(array('op' => 'box', 'layer' => 'l0', 'x' => 4)))), 'already decoded');
	pg_assert_same(null, ws_canvas_ai_operations('[]'), 'empty');
	pg_assert_same(null, ws_canvas_ai_operations('not json'), 'not JSON');
	pg_assert_same(null, ws_canvas_ai_operations('["text"]'), 'no objects');
	pg_assert_same(null, ws_canvas_ai_operations(null), 'missing');
}

// Long markup is cut, nulls dropped, times written as dates.
function test_workspace_canvas_ai_present()
{
	$doc = array('artboard' => array('width' => 200, 'height' => 100, 'background' => '#ffffff'), 'picture' => 'pending', 'layers' => array(
		array('id' => 'l0', 'type' => 'svg', 'kind' => 'g', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100, 'markup' => str_repeat('a', ws_canvas_ai_markup_max() + 50)),
		array('id' => 'l1', 'type' => 'text', 'x' => 5, 'y' => 5, 'w' => 50, 'h' => 20, 'text' => 'Hi', 'color' => '#000000'),
	));
	$versions = array(array('version_no' => 1, 'source' => 'agent', 'user_id' => 3, 'created_at' => 0, 'doc' => array('by' => array('id' => 0, 'kind' => 'ai', 'handle' => 'ai'))));
	$read = ws_canvas_ai_present(ws_canvas_read($doc, $versions), 42);

	pg_assert_same(42, $read['message_id'], 'message');
	pg_assert_same(1, $read['version'], 'version');
	pg_assert_same('pending', $read['picture'], 'pending');
	pg_assert_true(mb_strlen($read['layers'][0]['markup']) < ws_canvas_ai_markup_max() + 80, 'markup cut');
	pg_assert_true(strpos($read['layers'][0]['markup'], '[cut') !== false, 'cut said');
	pg_assert_false(array_key_exists('text', $read['layers'][0]), 'no text on an svg layer');
	pg_assert_same('Hi', $read['layers'][1]['text'], 'text layer');
	pg_assert_false(array_key_exists('markup', $read['layers'][1]), 'no markup on a text layer');
	pg_assert_same('ai', $read['versions'][0]['agent'], 'agent handle');
	pg_assert_false(array_key_exists('agent_id', $read['versions'][0]), 'no agent id for Pinegrap AI');
	pg_assert_same('1970-01-01 00:00 UTC', $read['versions'][0]['created_at'], 'date');
}

// Every canvas tool is a name the text-call reader knows.
function test_workspace_canvas_ai_tool_names()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

	foreach (ws_canvas_ai_tool_names() as $name) {
		pg_assert_true(in_array($name, ws_ai_tool_names(), true), $name);
	}

	$tool = function ($name, $description, $properties, $required) {
		return array('name' => $name, 'properties' => $properties, 'required' => $required);
	};

	foreach (ws_canvas_ai_tools($tool) as $made) {
		pg_assert_true(in_array($made['name'], ws_canvas_ai_tool_names(), true), 'offered ' . $made['name']);

		// Flat arguments only: the model server reads nested objects badly.
		foreach ($made['properties'] as $key => $property) {
			pg_assert_true(in_array($property['type'], array('string', 'integer'), true), $made['name'] . '.' . $key);
		}
	}
}
