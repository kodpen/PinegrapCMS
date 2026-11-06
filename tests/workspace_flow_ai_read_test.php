<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what Pinegrap AI's read_flow tells the model of one flow
 * (ws_flow_ai_read_graph() in includes/workspace/flow_draft.php): the graph
 * the Flows screen gives the same person, a draft only to whoever may change
 * the flow (or of a flow never published), and a webhook address, a change
 * box's record and values and a condition's value masked for everybody else.
 * Nothing here reads the database.
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

function workspace_flow_ai_read_graph_json($url, $label)
{
	return json_encode(array('v' => 1, 'nodes' => array(
		array('id' => 'start', 'kind' => 'manual', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array()),
		array('id' => 'hook', 'kind' => 'webhook', 'label' => $label, 'x' => 288, 'y' => 0, 'config' => array('url' => $url, 'format' => 'generic', 'text' => 'Hi')),
		array('id' => 'fix', 'kind' => 'change', 'label' => '', 'x' => 576, 'y' => 0, 'config' => array('record' => 'contact', 'action' => 'update', 'id' => 42, 'fields' => array('note' => 'VIP customer'))),
	), 'edges' => array(
		array('from' => 'start', 'to' => 'hook', 'on' => 'next'),
		array('from' => 'hook', 'to' => 'fix', 'on' => 'done'),
	), 'notes' => array()));
}

function workspace_flow_ai_read_box($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

// Whoever may change the flow reads its draft whole, as the canvas gives it.
function test_ws_flow_ai_read_graph_editor_reads_draft()
{
	$flow = array('access' => 'edit', 'draft' => workspace_flow_ai_read_graph_json('https://hooks.example.com/services/T0/B0/secret', 'Draft hook'));
	$live = ws_flow_graph_normalize(workspace_flow_ai_read_graph_json('https://hooks.example.com/services/T0/B0/old', 'Live hook'));
	$graph = ws_flow_ai_read_graph($flow, $live);
	$hook = workspace_flow_ai_read_box($graph, 'hook');
	$fix = workspace_flow_ai_read_box($graph, 'fix');

	pg_assert_same('Draft hook', $hook['label'], 'the draft, not the live version');
	pg_assert_same('https://hooks.example.com/services/T0/B0/secret', $hook['config']['url'], 'whole address');
	pg_assert_same(42, $fix['config']['id'], 'record kept');
	pg_assert_same('VIP customer', $fix['config']['fields']['note'], 'value kept');
}

// A person who only views or runs a published flow reads its live version,
// masked: the address's secret path, the record and its values stay back.
function test_ws_flow_ai_read_graph_reader_reads_live_masked()
{
	foreach (array('view', 'run') as $access) {
		$flow = array('access' => $access, 'draft' => workspace_flow_ai_read_graph_json('https://hooks.example.com/services/T0/B0/next', 'Draft hook'));
		$live = ws_flow_graph_normalize(workspace_flow_ai_read_graph_json('https://Hooks.example.com/services/T0/B0/secret', 'Live hook'));
		$graph = ws_flow_ai_read_graph($flow, $live);
		$hook = workspace_flow_ai_read_box($graph, 'hook');
		$fix = workspace_flow_ai_read_box($graph, 'fix');

		pg_assert_same('Live hook', $hook['label'], $access . ': the live version, not the draft');
		pg_assert_same('https://hooks.example.com/…', $hook['config']['url'], $access . ': host only');
		pg_assert_same(0, $fix['config']['id'], $access . ': record kept back');
		pg_assert_same(array('note' => ''), $fix['config']['fields'], $access . ': values kept back, names kept');
	}
}

// A flow never published is read as its draft, masked the same way.
function test_ws_flow_ai_read_graph_reader_never_published()
{
	$flow = array('access' => 'view', 'draft' => workspace_flow_ai_read_graph_json('https://hooks.example.com/services/T0/B0/secret', 'Draft hook'));
	$graph = ws_flow_ai_read_graph($flow, null);
	$hook = workspace_flow_ai_read_box($graph, 'hook');

	pg_assert_same('Draft hook', $hook['label'], 'draft of a flow never published');
	pg_assert_same('https://hooks.example.com/…', $hook['config']['url'], 'host only');
	pg_assert_true(is_array($hook['config']), 'settings stay an array for the model');
}
