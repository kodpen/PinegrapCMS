<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values a webhook box's address takes: a number threaded
 * into the path or query is filled URL-encoded, a value that is not digits
 * refuses the box at run, a value in the host or scheme is refused at save,
 * and every other address keeps its references as written.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A box of the graph.
function ws_flow_url_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A manual start, a post box, then a webhook calling $url.
function ws_flow_url_test_graph($url)
{
	return array(
		'nodes' => array(
			ws_flow_url_test_node('start', 'manual'),
			ws_flow_url_test_node('post_1', 'post', array('channel_id' => 5, 'body' => 'Hello')),
			ws_flow_url_test_node('webhook_1', 'webhook', array('url' => $url, 'format' => 'generic', 'text' => '')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'post_1', 'on' => 'next'),
			array('from' => 'post_1', 'to' => 'webhook_1', 'on' => 'done'),
		),
	);
}

// The problems the graph check finds at the webhook box.
function ws_flow_url_test_problems($url)
{
	$out = array();

	foreach (ws_flow_graph_check(ws_flow_url_test_graph($url)) as $problem) {
		if (($problem['node'] === 'webhook_1') && empty($problem['warning'])) {
			$out[] = $problem['code'];
		}
	}

	return $out;
}

function test_workspace_flow_webhook_url_fills_digits_into_path_and_query()
{
	$values = array('post_1' => array('message_id' => '0042'), WS_STEP_TRIGGER => array('count' => '7'));
	$action = array('type' => 'webhook', 'url' => 'https://example.com/orders/{{node.post_1.message_id}}?n={{trigger.count}}', 'format' => 'generic', 'text' => '');

	pg_assert_same('', ws_step_url_refusal('webhook', $action, $values), 'digits are taken');
	pg_assert_same('https://example.com/orders/0042?n=7', ws_step_fill_action('webhook', $action, $values)['url']);

	$filled = ws_step_url_fill('https://example.com/a/{{node.post_1.message_id}}', array('post_1' => array('message_id' => '12')));

	pg_assert_same(array('url' => 'https://example.com/a/12', 'error' => ''), $filled);
}

function test_workspace_flow_webhook_url_refuses_a_value_that_is_not_digits()
{
	$action = array('type' => 'webhook', 'url' => 'https://example.com/orders/{{node.post_1.text}}', 'format' => 'generic', 'text' => '');

	foreach (array('abc', '', '-5', '4 2', '1/../admin', '12?x=1', '3.5') as $value) {
		$refusal = ws_step_url_refusal('webhook', $action, array('post_1' => array('text' => $value)));

		pg_assert_true($refusal !== '', 'refused: ' . var_export($value, true));
		pg_assert_same('https://example.com/orders/', ws_step_fill_action('webhook', $action, array('post_1' => array('text' => $value)))['url'], 'never filled in: ' . var_export($value, true));
	}

	pg_assert_true(ws_step_url_refusal('webhook', $action, array()) !== '', 'a box that did not run gives no number');
}

function test_workspace_flow_webhook_url_refuses_a_value_in_host_or_scheme_at_save()
{
	foreach (array(
		'https://{{node.post_1.message_id}}.example.com/x',
		'https://example.com{{node.post_1.message_id}}',
		'https://example.com:{{node.post_1.message_id}}/x',
		'https://user{{node.post_1.message_id}}@example.com/x',
		'{{node.post_1.message_id}}://example.com/x',
	) as $url) {
		pg_assert_true(count(ws_flow_url_test_problems($url)) > 0, 'refused at save: ' . $url);
		pg_assert_true(ws_step_url_refusal('webhook', array('url' => $url), array('post_1' => array('message_id' => '5'))) !== '', 'refused at run: ' . $url);
	}

	pg_assert_same(array(), ws_flow_url_test_problems('https://example.com/orders/{{node.post_1.message_id}}?page={{node.post_1.message_id}}'), 'a number in path and query');
}

function test_workspace_flow_webhook_url_takes_number_outputs_only()
{
	pg_assert_same(array('bad_reference'), ws_flow_url_test_problems('https://example.com/orders/{{node.post_1.text}}'), 'a text value');
	pg_assert_same(array('bad_reference'), ws_flow_url_test_problems('https://example.com/orders/{{node.post_1.ghost}}'), 'a value the box does not give');
	pg_assert_same(array(), ws_flow_url_test_problems('https://example.com/orders'), 'no value');
}

function test_workspace_flow_webhook_url_other_addresses_unchanged()
{
	$values = array('post_1' => array('message_id' => '5'));

	$check = ws_step_fill_action('web_check', array('type' => 'web_check', 'url' => 'https://example.com/{{node.post_1.message_id}}', 'mode' => 'status'), $values);
	pg_assert_same('https://example.com/{{node.post_1.message_id}}', $check['url'], 'a web check address is not filled');

	$email = ws_step_fill_action('email', array('type' => 'email', 'to' => '{{node.post_1.message_id}}@example.com', 'subject' => 's', 'body' => 'b'), $values);
	pg_assert_same('{{node.post_1.message_id}}@example.com', $email['to'], 'an e-mail recipient is not filled');

	pg_assert_same('', ws_step_url_refusal('web_check', array('url' => 'https://{{node.post_1.message_id}}/'), $values), 'only the webhook box reads its address');

	pg_assert_same(array('url'), ws_step_kinds()['webhook']['urls']);

	foreach (ws_step_kinds() as $kind => $info) {
		if ($kind !== 'webhook') {
			pg_assert_same(array(), $info['urls'] ?? array(), $kind . ' fills no address');
		}
	}
}

// The canvas: the address joins the value fields, and lights up for
// numbers only; its help says so.
function test_workspace_flow_webhook_url_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true(strpos($js, "webhook: [['text', 'fl_post_body', 4000], ['url', 'fl_url', 2000]]") !== false, 'the address is a value field');
	pg_assert_true((bool) preg_match("/\\(node\\.kind === 'webhook'\\) && \\(key === 'url'\\)\\) \\{\\s*return source\\.value\\.type === 'number';/", $js), 'only number values light it up');
	pg_assert_true(strpos($js, 'urlWithValue(target.text, token)') !== false, 'a thread dropped on it never lands in the host');
	pg_assert_true(isset(ws_flow_js_strings()['fl_hook_url_help']), 'its help is translated');
}
