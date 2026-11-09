<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for why a flow box failed (flow_failure_reason.php): the values its
 * texts read empty without a fallback, how they are named, the sentence the
 * failure adds and what a stored result hands the run card, the bell line
 * and the run view.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_failure_reason.php');

// A run where the row box found nothing and the start gave a name.
function ws_flow_reason_test_values()
{
	return array(
		'row'           => array('text' => 'The table “Leads” has no row 4. Nothing was read.', 'outcome' => 'done', 'status' => 'done', 'found' => '0', 'cell_7' => ''),
		'say'           => array('text' => 'Posted.', 'outcome' => 'done', 'status' => 'done', 'count' => '0'),
		WS_STEP_TRIGGER => array('name' => 'Ada'),
	);
}

// Empty, missing and fallback-less values are named once, in order; a value
// given, a "0" or one with a fallback is not.
function test_workspace_flow_failure_reason_empty_values()
{
	$values = ws_flow_reason_test_values();
	$texts = array(
		'Hi {{trigger.name}}, {{node.row.cell_7}} and {{node.row.cell_7}} {{node.say.count}}',
		'{{node.row.cell_8}} {{node.row.cell_7|none}} {{node.gone.text}} {{trigger.mail}}',
	);

	pg_assert_same(array(
		array('box' => 'row', 'key' => 'cell_7'),
		array('box' => 'row', 'key' => 'cell_8'),
		array('box' => 'gone', 'key' => 'text'),
	), ws_flow_empty_values($texts, $values), 'at most three, each once');

	pg_assert_same(array(array('box' => '', 'key' => 'mail')), ws_flow_empty_values(array('{{trigger.mail}} {{trigger.name}}'), $values), 'what started the run, by an empty box id');
	pg_assert_same(array(), ws_flow_empty_values(array('{{node.row.cell_7|-}} {{node.say.count}}', 7), $values), 'a fallback or a value given names nothing');
}

// Only a step's texts are read, as written.
function test_workspace_flow_failure_reason_step_texts()
{
	pg_assert_same(array('{{node.row.cell_7}}'), ws_flow_step_texts('post', array('channel_id' => 3, 'body' => '{{node.row.cell_7}}')));
	pg_assert_same(array(), ws_flow_step_texts('no_such_kind', array('body' => 'x')));
}

// Each value is named by its box and the picker's name; a row read that
// found nothing brings its own sentence.
function test_workspace_flow_failure_reason_entries()
{
	$nodes = array(
		array('id' => 'start', 'kind' => 'manual', 'label' => 'Start', 'config' => array()),
		array('id' => 'row', 'kind' => 'row_get', 'label' => 'Read a row', 'config' => array()),
		array('id' => 'say', 'kind' => 'post', 'label' => '', 'config' => array()),
	);
	$label = function ($giver, $field) {
		return ($field === 'cell_7') ? 'Message' : '';
	};
	$entries = ws_flow_empty_entries(array(array('box' => 'row', 'key' => 'cell_7'), array('box' => 'say', 'key' => 'count'), array('box' => 'gone', 'key' => 'text')), $nodes, ws_flow_reason_test_values(), $label);

	pg_assert_same(array('node_id' => 'row', 'token' => '{{node.row.cell_7}}', 'key' => 'cell_7', 'box' => 'Read a row', 'name' => 'Message', 'said' => 'The table “Leads” has no row 4. Nothing was read.'), $entries[0]);
	pg_assert_same(array('node_id' => 'say', 'token' => '{{node.say.count}}', 'key' => 'count', 'box' => 'say', 'name' => 'count', 'said' => ''), $entries[1], 'no label: the key; no row read: no sentence');
	pg_assert_same('', $entries[2]['node_id'], 'a box the flow lacks has no id to open');
	pg_assert_same(array('start', '{{trigger.name}}'), array_values(array_intersect_key(ws_flow_empty_entries(array(array('box' => '', 'key' => 'name')), $nodes, array(), $label)[0], array('node_id' => 1, 'token' => 1))), 'what started the run: the start box, a trigger reference');

	$found = ws_flow_reason_test_values();
	$found['row']['found'] = '1';
	pg_assert_same('', ws_flow_empty_entries(array(array('box' => 'row', 'key' => 'cell_7')), $nodes, $found, $label)[0]['said'], 'a row found adds no sentence');
}

// The sentence; the row box's own only for whoever may change the flow.
function test_workspace_flow_failure_reason_text()
{
	$entries = array(array('node_id' => 'row', 'key' => 'cell_7', 'box' => 'Read a row', 'name' => 'Message', 'said' => 'No row 4.'));

	pg_assert_same('The value “Message” from the box “Read a row” was empty. No row 4.', ws_flow_reason_text($entries, true));
	pg_assert_same('The value “Message” from the box “Read a row” was empty.', ws_flow_reason_text($entries, false));
	pg_assert_same('', ws_flow_reason_text(array(), true));
	pg_assert_same('', ws_flow_reason_text(array('junk', array('key' => 'x')), true), 'a broken entry names nothing');
}

// A stored result: the failed box's sentence gains the reason; a done box,
// or a failure that kept no list, stays as it was.
function test_workspace_flow_failure_reason_stored()
{
	$entries = array(array('node_id' => 'row', 'token' => '{{node.row.cell_7}}', 'key' => 'cell_7', 'box' => 'Read a row', 'name' => 'Message', 'said' => 'No row 4.'));
	$stored = ws_flow_result_pack('Could not write in #genel: The message is empty.', array('empty_values' => $entries));
	$plain = ws_flow_result_pack('The channel is gone.');

	pg_assert_same('Could not write in #genel: The message is empty. The value “Message” from the box “Read a row” was empty. No row 4.', ws_flow_text_with_reason(ws_flow_result_text($stored), 'failed', $stored, true));
	pg_assert_same('Posted.', ws_flow_text_with_reason('Posted.', 'done', $stored, true), 'only a failed box');
	pg_assert_same('The channel is gone.', ws_flow_text_with_reason('The channel is gone.', 'failed', $plain, true), 'another reason shows nothing new');
	pg_assert_same('The value “Message” from the box “Read a row” was empty.', ws_flow_text_with_reason('', 'failed', $stored, false));

	pg_assert_same(array(array('node_id' => 'row', 'token' => '{{node.row.cell_7}}', 'key' => 'cell_7', 'box' => 'Read a row', 'name' => 'Message')), ws_flow_empty_present('failed', $stored), 'the canvas gets no row sentence');
	pg_assert_same(array(), ws_flow_empty_present('done', $stored));
	pg_assert_same(array(), ws_flow_empty_present('failed', $plain));
	pg_assert_same(array(), ws_flow_empty_present('failed', 'not json'));
}
