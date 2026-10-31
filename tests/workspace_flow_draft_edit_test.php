<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Edit here on the flow draft card's window
 * (includes/workspace/flow_draft.php): who is offered it, the box's form,
 * the box changed in the graph, the save through the canvas's own save and
 * the proposal left as it was. Nothing here reads the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function workspace_flow_draft_edit_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

function workspace_flow_draft_edit_graph()
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'x' => 96, 'y' => 96),
			array('id' => 'call', 'kind' => 'task', 'label' => 'Call', 'x' => 384, 'y' => 96, 'config' => array('title' => 'Call back', 'assignees' => array(4), 'priority' => 'high')),
		),
		'edges' => array(array('from' => 'start', 'to' => 'call', 'on' => 'next')),
	));
}

// Offered where opening would pass on a waiting card (open first), where the
// flow may be changed on an opened one; never to anyone but its asker.
function test_ws_flow_draft_box_edit_who()
{
	pg_assert_same('open', ws_flow_draft_box_edit(true, 'pending', true, true, '', false), 'a waiting new flow: open first');
	pg_assert_same('open', ws_flow_draft_box_edit(true, 'pending', true, true, 'edit', true), 'a waiting patch with edit on its flow');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'pending', true, true, 'view', true), 'a patch on a flow only seen');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'pending', false, true, '', false), 'no right to build flows');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'pending', true, false, '', false), 'may no longer post there');
	pg_assert_same('', ws_flow_draft_box_edit(false, 'pending', true, true, 'edit', false), 'not the asker');
	pg_assert_same('edit', ws_flow_draft_box_edit(true, 'applied', true, true, 'edit', false), 'opened, flow at edit: edit at once');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'applied', true, true, 'view', false), 'opened, flow only seen');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'applied', true, true, '', false), 'opened, flow gone');
	pg_assert_same('', ws_flow_draft_box_edit(true, 'dismissed', true, true, 'edit', false), 'dismissed');
}

// The form shows the texts the canvas fills, each a setting its kind takes.
function test_ws_flow_draft_box_texts_are_settings()
{
	$keys = ws_flow_ai_setting_keys(ws_flow_kind_catalogue());

	foreach (ws_flow_draft_box_texts() as $kind => $fields) {
		pg_assert_true(isset($keys[$kind]), $kind . ' is a kind of box');

		foreach ($fields as $field) {
			pg_assert_true(in_array($field['key'], $keys[$kind], true), $kind . '.' . $field['key'] . ' is a setting');
			pg_assert_true($field['label'] !== '' && $field['most'] > 0, $kind . '.' . $field['key'] . ' named and bounded');
		}
	}

	$fields = ws_flow_draft_box_fields('task', array('title' => 'Call back', 'description' => 7), ws_flow_draft_box_texts());
	pg_assert_same(array('title', 'description'), array_column($fields, 'key'), 'a task: title and description');
	pg_assert_same(array('Call back', '7'), array_column($fields, 'value'), 'with what it holds');
	pg_assert_same(array(), ws_flow_draft_box_fields('manual', array(), ws_flow_draft_box_texts()), 'a start: its name only');
}

// Only the box's name and its form's texts change; people, places and
// arrows stay; a box gone is refused.
function test_ws_flow_draft_box_set()
{
	$graph = workspace_flow_draft_edit_graph();
	$set = ws_flow_draft_box_set($graph, 'call', ' Ring them ', array('title' => "Ring\nback", 'assignees' => array(9), 'priority' => 'low'), ws_flow_draft_box_texts());

	pg_assert_true($set['ok'], 'saved');
	$node = $set['graph']['nodes'][1];
	pg_assert_same('Ring them', $node['label'], 'name on one line');
	pg_assert_same('Ring back', $node['config']['title'], 'title on one line');
	pg_assert_same($graph['nodes'][1]['config']['description'], $node['config']['description'], 'a text not sent stays');
	pg_assert_same(array(4), $node['config']['assignees'], 'people stay the canvas\'s');
	pg_assert_same('high', $node['config']['priority'], 'so do other settings');
	pg_assert_same(array($graph['nodes'][1]['x'], $graph['nodes'][1]['y']), array($node['x'], $node['y']), 'place kept');
	pg_assert_same($graph['edges'], $set['graph']['edges'], 'arrows kept');
	pg_assert_same($graph['nodes'][0], $set['graph']['nodes'][0], 'other boxes kept');

	pg_assert_false(ws_flow_draft_box_set($graph, 'gone', 'X', array(), ws_flow_draft_box_texts())['ok'], 'a box no longer there');
}

// A waiting card opens through ws_flow_draft_open() (the client then edits
// the opened card); the save goes through ws_flow_save() with the draft_rev
// the form was read at (409 when it moved) and never writes the proposal.
function test_ws_flow_draft_box_save_path()
{
	$flow = workspace_flow_draft_edit_code('ws_flow_draft_box_flow');
	pg_assert_contains("['requested_by']", $flow, 'only its asker');
	pg_assert_contains("!== 'edit'", $flow, 'only an opened card whose flow they may change');
	pg_assert_contains('ws_flow_access(', $flow, 'access to the flow it was opened as');

	$save = workspace_flow_draft_edit_code('ws_flow_draft_box_save');
	pg_assert_contains("ws_flow_save(\$viewer, array('graph' => \$set['graph'], 'draft_rev' => (int) (\$data['draft_rev'] ?? 0)), \$flow)", $save, 'the canvas\'s save at the draft_rev read');

	foreach (array('ws_flow_draft_box_flow', 'ws_flow_draft_box_read', 'ws_flow_draft_box_save') as $function) {
		$code = workspace_flow_draft_edit_code($function);
		pg_assert_true(strpos($code, 'UPDATE ws_ai_changes') === false && strpos($code, 'ws_flow_draft_mark_opened(') === false, $function . ' leaves the proposal');
	}

	$read = workspace_flow_draft_edit_code('ws_flow_draft_card_graph_read');
	pg_assert_contains("'edit'", $read, 'the window says what Edit here does');
	pg_assert_contains('ws_flow_draft_box_edit(', $read, 'from the one rule');

	$save_flow = workspace_flow_draft_edit_code('ws_flow_save');
	pg_assert_contains('409', $save_flow, 'a moved draft is refused with 409');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$case = strpos($actions, "case 'ws_flow_draft_box_save':");
	pg_assert_true($case !== false, 'the save action is there');
	pg_assert_contains('ws_action_flow_refused(', substr($actions, (int) $case, 600), 'a moved draft told apart as on the canvas');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_contains("'ws_flow_draft_box', 'ws_flow_draft_box_save'", $panel, 'the panel lets both through');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains("api('ws_flow_from_draft', { change_id: card.change.id })", $js, 'a waiting card is opened first, as Open on canvas does');
}
