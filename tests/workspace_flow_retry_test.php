<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a failed step box of a flow tried again on its own
 * (includes/workspace/flow_runs.php): the two settings a step box keeps
 * (how many times, minutes before each try), their range (at most 8 tries in
 * all, the depth a scheduled action that starts itself again stops at), the
 * publish problems, the moment the next try is due, the key it waits on, and,
 * read from the source, that a step's failure waits before it settles, the
 * look that makes the box ready again, and the canvas fields.
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
function ws_flow_retry_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Seven tries again at most: eight in all, the scheduled chain's depth.
function test_workspace_flow_retry_max()
{
	pg_assert_same(7, WS_FLOW_RETRY_MAX);
	pg_assert_same(8, WS_FLOW_RETRY_MAX + 1);
}

// Off by default; the times held within 0-7, the minutes within a week.
function test_workspace_flow_retry_normalize()
{
	pg_assert_same(array('retry' => 0, 'retry_after' => ''), ws_flow_retry_normalize(array()));
	pg_assert_same(array('retry' => 3, 'retry_after' => 15), ws_flow_retry_normalize(array('retry' => '3', 'retry_after' => '15')));
	pg_assert_same(array('retry' => 7, 'retry_after' => 10080), ws_flow_retry_normalize(array('retry' => 50, 'retry_after' => 99999)));
	pg_assert_same(array('retry' => 0, 'retry_after' => 1), ws_flow_retry_normalize(array('retry' => -2, 'retry_after' => 0)));
	pg_assert_same(array('retry' => 0, 'retry_after' => ''), ws_flow_retry_normalize(array('retry' => true, 'retry_after' => 'soon')));
}

// Every step box keeps the two settings beside its own; other boxes do not.
function test_workspace_flow_retry_kept_on_step_boxes()
{
	$post = ws_flow_config_normalize('post', array('channel_id' => 4, 'body' => 'Hi', 'retry' => 2, 'retry_after' => 30));

	pg_assert_same(2, $post['retry']);
	pg_assert_same(30, $post['retry_after']);
	pg_assert_same('Hi', $post['body']);

	$webhook = ws_flow_config_normalize('webhook', array());

	pg_assert_same(0, $webhook['retry']);
	pg_assert_same('', $webhook['retry_after']);

	pg_assert_true(!array_key_exists('retry', ws_flow_config_normalize('delay', array('amount' => 2, 'unit' => 'hours', 'retry' => 2))));
	pg_assert_true(!array_key_exists('retry', ws_flow_config_normalize('task', array('title' => 'Call', 'retry' => 2))));
}

// Tries again without the minutes to wait: a publish problem.
function test_workspace_flow_retry_problems()
{
	pg_assert_same(array(), ws_flow_retry_problems(ws_flow_retry_normalize(array()), 'Post'));
	pg_assert_same(array(), ws_flow_retry_problems(ws_flow_retry_normalize(array('retry' => 2, 'retry_after' => 5)), 'Post'));
	pg_assert_same(1, count(ws_flow_retry_problems(ws_flow_retry_normalize(array('retry' => 2)), 'Post')));

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array()),
			array('id' => 'call', 'kind' => 'webhook', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('url' => 'https://example.com/hook', 'format' => 'generic', 'retry' => 3)),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'call', 'on' => 'next'),
		),
	));
	$found = false;

	foreach (ws_flow_graph_check($graph) as $problem) {
		if (($problem['code'] === 'bad_config') && ($problem['node'] === 'call') && (strpos($problem['text'], 'minutes') !== false)) {
			$found = true;
		}
	}

	pg_assert_true($found);
}

// The next try is due after the minutes while tries are left; the attempt
// is the claim's count, so the first failure is attempt 1.
function test_workspace_flow_retry_until()
{
	$config = ws_flow_retry_normalize(array('retry' => 2, 'retry_after' => 10));

	pg_assert_same(1000 + 600, ws_flow_retry_until($config, 1, 1000));
	pg_assert_same(1000 + 600, ws_flow_retry_until($config, 2, 1000));
	pg_assert_same(0, ws_flow_retry_until($config, 3, 1000));
	pg_assert_same(0, ws_flow_retry_until(ws_flow_retry_normalize(array()), 1, 1000));
	pg_assert_same(0, ws_flow_retry_until(ws_flow_retry_normalize(array('retry' => 2)), 1, 1000));
	pg_assert_same(0, ws_flow_retry_until(ws_flow_retry_normalize(array('retry' => 7, 'retry_after' => 1)), 8, 1000));
}

// The key a waiting try is kept under, and read back; a delay's is not one.
function test_workspace_flow_retry_key()
{
	pg_assert_same('retry:1700000000', ws_flow_retry_key(1700000000));
	pg_assert_same(1700000000, ws_flow_retry_key_until('retry:1700000000'));
	pg_assert_same(0, ws_flow_retry_key_until('delay:1700000000'));
	pg_assert_same(0, ws_flow_retry_key_until('retry:'));
	pg_assert_same(0, ws_flow_delay_key_until('retry:1700000000'));
	pg_assert_same(null, ws_flow_effect_wait('retry:1700000000'));
}

// Every failure of a step goes through the wait first; the look hands the
// key to the retry look, which makes the box ready again from waiting only.
function test_workspace_flow_retry_wired()
{
	$run = ws_flow_retry_test_source('ws_step_run');

	pg_assert_same(3, substr_count($run, 'ws_flow_retry_wait('));

	$recheck = ws_flow_retry_test_source('ws_flow_step_recheck');

	pg_assert_true(strpos($recheck, 'ws_flow_retry_key_until(') !== false);
	pg_assert_true(strpos($recheck, 'ws_flow_retry_recheck(') !== false);

	$wait = ws_flow_retry_test_source('ws_flow_retry_wait');

	pg_assert_true(strpos($wait, "status = 'waiting'") !== false);
	pg_assert_true(strpos($wait, "AND status = 'running'") !== false);
	pg_assert_true(strpos($wait, "effect = ''") !== false);

	$ready = ws_flow_retry_test_source('ws_flow_retry_recheck');

	pg_assert_true(strpos($ready, "status = 'ready'") !== false);
	pg_assert_true(strpos($ready, "AND status = 'waiting'") !== false);
}

// The canvas offers the two settings on every step box.
function test_workspace_flow_retry_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true(strpos($js, 'function retryFields(') !== false);
	pg_assert_true(strpos($js, "t('fl_self_retry')") !== false);
	pg_assert_true(strpos($js, "t('fl_self_retry_after')") !== false);

	$config = ws_flow_retry_test_source('ws_flow_js_config');

	pg_assert_true(strpos($config, "'retry'") !== false);
}
