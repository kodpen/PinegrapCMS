<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the webhook box's answer as a value later boxes read
 * ({{node.<id>.body}}): cut to WS_SCHEDULED_WEBHOOK_BODY_BYTES at a
 * character, kept only as valid UTF-8, fitting the run row's result, and
 * kept back from readers who may not change the flow.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_events.php');

// The answer is kept as it came, up to 16 KB; a longer one is cut at a
// character; one that is not UTF-8 text is no value.
function test_workspace_flow_webhook_body_cut()
{
	pg_assert_same(16384, WS_SCHEDULED_WEBHOOK_BODY_BYTES);
	pg_assert_same('{"ok":true}', ws_scheduled_webhook_body('{"ok":true}'));
	pg_assert_same('', ws_scheduled_webhook_body(''), 'an empty answer is an empty text');
	pg_assert_same(null, ws_scheduled_webhook_body(null), 'no answer, no value');

	$whole = str_repeat('a', WS_SCHEDULED_WEBHOOK_BODY_BYTES);
	pg_assert_same($whole, ws_scheduled_webhook_body($whole), 'exactly 16 KB stays whole');
	pg_assert_same($whole, ws_scheduled_webhook_body($whole . 'bcd'), 'a longer answer is cut to 16 KB');

	// A two-byte character across the limit is left out, not cut in half.
	$across = str_repeat('a', WS_SCHEDULED_WEBHOOK_BODY_BYTES - 1) . 'ş' . 'x';
	$cut = ws_scheduled_webhook_body($across);
	pg_assert_same(WS_SCHEDULED_WEBHOOK_BODY_BYTES - 1, strlen($cut));
	pg_assert_true(mb_check_encoding($cut, 'UTF-8'), 'no character cut in half');

	// The request asks for a few bytes more, so a character the limit cuts
	// is still read whole.
	pg_assert_true(WS_SCHEDULED_WEBHOOK_BODY_BYTES + 3 <= ws_scheduled_webhook_body_fetch(), 'reads past the limit by a character');

	pg_assert_same(null, ws_scheduled_webhook_body("\xff\xfe binary"), 'not UTF-8: no value');
	pg_assert_same(null, ws_scheduled_webhook_body("ok \xc3"), 'a short answer ending in half a character is not text');
	pg_assert_same('şğü', ws_scheduled_webhook_body('şğü'));
}

// The step answers the body under data, on both ends.
function test_workspace_flow_webhook_body_answered()
{
	$source = (new ReflectionFunction('ws_scheduled_webhook'))->getFileName();
	$function = new ReflectionFunction('ws_scheduled_webhook');
	$code = implode('', array_slice(file($source), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_true(strpos($code, "'max_body' => ws_scheduled_webhook_body_fetch()") !== false, 'the request reads the answer up to the limit');
	pg_assert_true(strpos($code, "'body' => ws_scheduled_webhook_body(") !== false, 'the answer is kept as body');

	$config = ws_step_config_normalize('webhook', array('url' => 'https://example.com/hook', 'format' => 'generic'));
	$data = ws_step_result_data('webhook', $config, array('ok' => true, 'message_id' => 0, 'data' => array('status_code' => 200, 'duration_ms' => 12, 'body' => '{"id":5}')));
	pg_assert_same(array('status_code' => 200, 'duration_ms' => 12, 'body' => '{"id":5}'), $data);

	$values = ws_step_node_values(array(array('node_id' => 'call', 'status' => 'done', 'outcome' => 'done',
		'result' => ws_flow_result_pack('called', $data))));
	pg_assert_same('{"id":5}', $values['call']['body'], 'a later box reads the body');
}

// A 16 KB answer fits the box's result, which fits the TEXT column; a value
// that does not fit is left out before the others.
function test_workspace_flow_webhook_body_fits()
{
	pg_assert_true(WS_FLOW_RESULT_BYTES <= 65535, 'within a TEXT column');

	$body = str_repeat('"ş\\', (int) floor(WS_SCHEDULED_WEBHOOK_BODY_BYTES / 4));
	$data = array('status_code' => 200, 'duration_ms' => 5, 'body' => $body);
	$packed = ws_flow_result_pack('webhook example.com called (HTTP 200, 5 ms)', $data);

	pg_assert_true(strlen($packed) <= WS_FLOW_RESULT_BYTES, 'within the budget');
	pg_assert_same($data, json_decode($packed, true)['data'], 'kept whole');

	// Control characters grow six times in JSON: such an answer is left
	// out, the status code stays.
	$wide = str_repeat("\x01", WS_SCHEDULED_WEBHOOK_BODY_BYTES);
	$packed = ws_flow_result_pack('called', array('status_code' => 200, 'duration_ms' => 5, 'body' => $wide));

	pg_assert_true(strlen($packed) <= WS_FLOW_RESULT_BYTES, 'a wide answer stays within the budget');
	pg_assert_same(array('status_code' => 200, 'duration_ms' => 5, 'body' => null), json_decode($packed, true)['data'], 'the value too large is left out first');
	pg_assert_same('called', ws_flow_result_text($packed));
}

// The body goes only to whoever may change the flow; its status code and
// time stay as they were shown.
function test_workspace_flow_webhook_body_kept_back()
{
	$stored = ws_flow_result_pack('called', array('status_code' => 200, 'duration_ms' => 5, 'body' => 'secret answer'));

	pg_assert_same(array('status_code' => 200, 'duration_ms' => 5, 'body' => 'secret answer'), ws_api_flow_step_outputs('webhook', $stored, true));
	pg_assert_same(array('status_code' => 200, 'duration_ms' => 5), ws_api_flow_step_outputs('webhook', $stored, false));
	pg_assert_same(array('status_code' => 200, 'duration_ms' => 5), ws_flow_run_event_outputs('webhook', $stored));
	pg_assert_same(array('body'), ws_step_outputs_held()['webhook']);
}
