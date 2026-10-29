<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values a flow's boxes give the boxes after them: every kind
 * declares its outputs (key, type, label), a step box writes each declared
 * key into its result's data, the step it carries out answers each one, and
 * the canvas is told them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_flow_outputs_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A box of each step kind as it may be set up: a report counting two
// things, a web check of either mode.
function ws_flow_outputs_test_configs()
{
	$out = array();

	foreach (array_keys(ws_step_kinds()) as $kind) {
		$out[$kind] = ws_step_config_normalize($kind, array());
	}

	$out['report'] = ws_step_config_normalize('report', array('metrics' => array(
		array('key' => 'tasks_open', 'param' => 4),
		array('key' => 'comments_pending', 'param' => 0),
	)));
	$out['web_check_ssl'] = ws_step_config_normalize('web_check', array('mode' => 'ssl', 'url' => 'https://example.com'));

	return $out;
}

// Every kind declares its outputs: a key a {{node.<id>.<field>}} reads, a
// type, a label; never twice the same key.
function test_workspace_flow_outputs_declared()
{
	foreach (ws_flow_kinds() as $kind => $info) {
		pg_assert_true(isset($info['outputs']) && is_array($info['outputs']), $kind . ' declares its outputs');
		$keys = array();

		foreach ($info['outputs'] as $output) {
			pg_assert_same(1, preg_match('/^[a-z_][a-z0-9_]{0,31}$/', (string) ($output['key'] ?? '')), $kind . ' output key ' . ($output['key'] ?? ''));
			pg_assert_true(in_array($output['type'] ?? '', ws_step_output_types(), true), $kind . ' ' . $output['key'] . ' has a known type');
			pg_assert_true(is_string($output['label'] ?? null) && ($output['label'] !== ''), $kind . ' ' . $output['key'] . ' has a label');
			$keys[] = $output['key'] . '/' . ($output['mode'] ?? '');
		}

		pg_assert_same(count($keys), count(array_unique($keys)), $kind . ' declares a key once');
	}

	foreach (ws_step_kinds() as $kind => $info) {
		pg_assert_same($info['outputs'], ws_flow_kinds()[$kind]['outputs'], $kind . ' declares on the canvas what the step declares');
	}

	// What the scheduled-action parity needs: the box that waits names its
	// task, a webhook and a web check their answer, a report its counts.
	pg_assert_same(array('task_id'), array_column(ws_flow_kinds()['task']['outputs'], 'key'), 'the waiting task box names its task');
	$configs = ws_flow_outputs_test_configs();
	pg_assert_same(array('status_code', 'duration_ms', 'body'), array_column(ws_step_outputs('webhook', $configs['webhook']), 'key'), 'a webhook gives its answer');
	pg_assert_same('text', ws_step_outputs('webhook', $configs['webhook'])[2]['type'], 'the answer body is a text');
	pg_assert_same(array('status_code', 'duration_ms'), array_column(ws_step_outputs('web_check', $configs['web_check']), 'key'), 'a web check gives its answer');
	pg_assert_same(array('days_left'), array_column(ws_step_outputs('web_check', $configs['web_check_ssl']), 'key'), 'a certificate check gives its days');
	pg_assert_same(array('message_id', 'tasks_open_4', 'comments_pending'), array_column(ws_step_outputs('report', $configs['report']), 'key'), 'a report gives each count');
	pg_assert_same('number', ws_step_outputs('report', $configs['report'])[1]['type'], 'a count is a number');
	pg_assert_same('Open tasks', ws_step_outputs('report', $configs['report'], array('tasks_open' => 'Open tasks'))[1]['label'], 'a count is labelled as given');
}

// Every declared key is written, a number or null; a step that gave a value
// writes it, one that gave none (a failure, a refused count) writes null.
function test_workspace_flow_outputs_written()
{
	foreach (ws_flow_outputs_test_configs() as $name => $config) {
		$kind = ($name === 'web_check_ssl') ? 'web_check' : $name;
		$outputs = ws_step_outputs($kind, $config);
		$keys = array_column($outputs, 'key');

		// What the step answers when it went well: each value at the top or
		// under data, a report's counts in the order of its metrics.
		$done = array('ok' => true, 'text' => 'ok', 'message_id' => 0, 'data' => array());

		foreach ($outputs as $output) {
			if (isset($output['count'])) {
				$done['data']['counts'][$output['count']] = 10 + $output['count'];
			} elseif (substr($output['key'], -3) === '_id') {
				$done[$output['key']] = 7;
			} elseif ($output['type'] === 'text') {
				$done['data'][$output['key']] = 'seven';
			} else {
				$done['data'][$output['key']] = 7;
			}
		}

		$data = ws_step_result_data($kind, $config, $done);
		pg_assert_same($keys, array_keys($data), $name . ' writes every declared key');

		foreach ($outputs as $output) {
			pg_assert_true(($output['type'] === 'text') ? is_string($data[$output['key']]) : is_int($data[$output['key']]), $name . ' ' . $output['key'] . ' is its type');
		}

		$failed = ws_step_result_data($kind, $config, array('ok' => false, 'text' => 'no', 'message_id' => 0));
		pg_assert_same($keys, array_keys($failed), $name . ' failing writes every declared key');
		pg_assert_same(array(), array_filter($failed, function ($value) {
			return $value !== null;
		}), $name . ' failing writes null');
	}

	$report = ws_flow_outputs_test_configs()['report'];
	$data = ws_step_result_data('report', $report, array('ok' => true, 'message_id' => 12, 'data' => array('counts' => array(3, null))));
	pg_assert_same(array('message_id' => 12, 'tasks_open_4' => 3, 'comments_pending' => null), $data, 'a refused count is null');

	// An id of 0 is no id.
	pg_assert_same(array('message_id' => null), ws_step_result_data('post', ws_step_config_normalize('post', array()), array('ok' => false, 'message_id' => 0)), 'no message, no id');

	// The values a later box reads: a null is no value.
	$values = ws_step_node_values(array(array('node_id' => 'say', 'status' => 'done', 'outcome' => 'done',
		'result' => json_encode(array('text' => 'x', 'data' => array('message_id' => 5, 'sent' => null))))));
	pg_assert_same('5', $values['say']['message_id'], 'a number is read');
	pg_assert_same('', ws_step_fill_nodes('{{node.say.sent}}', $values), 'a null is empty');
}

// The step a box carries out answers each declared value, and the box
// writes them through ws_step_result_data().
function test_workspace_flow_outputs_answered()
{
	$answers = array(
		'post'        => array('ws_scheduled_do'),
		'email'       => array('ws_scheduled_do'),
		'checkin'     => array('ws_scheduled_do'),
		'notify'      => array('ws_scheduled_notify'),
		'task_open'   => array('ws_scheduled_task'),
		'webhook'     => array('ws_scheduled_webhook'),
		'web_check'   => array('ws_scheduled_web_check', 'ws_scheduled_ssl_check'),
		'task_digest' => array('ws_scheduled_task_digest'),
		'report'      => array('ws_scheduled_summary'),
		'start_action' => array('ws_scheduled_start'),
		'change'      => array('ws_scheduled_change'),
		'lead'        => array('ws_scheduled_lead'),
		'approval'    => array('ws_scheduled_do'),
		'mcp'         => array('ws_step_mcp_do'),
		'row_add'     => array('ws_step_row_do'),
		'row_set'     => array('ws_step_row_do'),
	);

	$kinds = array_keys(ws_step_kinds());
	$named = array_keys($answers);
	sort($kinds);
	sort($named);
	pg_assert_same($kinds, $named, 'every step kind has the function that answers it here');

	foreach (ws_step_kinds() as $kind => $info) {
		$source = implode("\n", array_map('ws_flow_outputs_test_source', $answers[$kind]));

		foreach ($info['outputs'] as $output) {
			pg_assert_true(strpos($source, "'" . $output['key'] . "' =>") !== false, $kind . ' answers ' . $output['key']);
		}
	}

	pg_assert_true(strpos(ws_flow_outputs_test_source('ws_scheduled_summary'), "'counts' =>") !== false, 'a report answers its counts');

	$run = ws_flow_outputs_test_source('ws_step_run');
	pg_assert_true(strpos($run, 'ws_step_result_data($kind, $node[\'config\'], $done)') !== false, 'a step box writes its declared values');
	pg_assert_true(strpos(ws_flow_outputs_test_source('ws_flow_js_config'), "'outputs'") !== false, 'the canvas is told the outputs');
}
