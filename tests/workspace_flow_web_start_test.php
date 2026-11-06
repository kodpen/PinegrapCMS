<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box that starts a flow from a web address (kind web,
 * includes/workspace/flow_web_start.php and the root flow_start.php): its
 * kind and settings, the body fields it declares as {{trigger.<field>}},
 * how a JSON or form body becomes those values, the secret's shape and its
 * constant-time comparison, the address, and the run's trigger values.
 * Read from the source: the root file's limits (60 a minute per address,
 * 64 KB) and its place among the firewall's service endpoints.
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

// A start box of its own: no arrow into it, it leaves on next, it is a start
// of its own in the flow list, and Pinegrap AI may draft it.
function test_workspace_flow_web_start_kind()
{
	$kinds = ws_flow_kinds();

	pg_assert_true(isset($kinds['web']));
	pg_assert_true($kinds['web']['trigger']);
	pg_assert_false($kinds['web']['step']);
	pg_assert_same(array('next'), $kinds['web']['outcomes']);
	pg_assert_same('web', ws_flow_start_names()['web']);
	pg_assert_false(isset(ws_flow_anchor_kinds()['web']), 'no hidden scheduled action: the address starts it');
	pg_assert_false(in_array('web', ws_flow_member_kinds(), true), 'staff only: a role-3 member\'s flow starts no address');
	pg_assert_contains('web', ws_flow_ai_catalogue_text(ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array())), 'Pinegrap AI drafts it');
	pg_assert_same(array('fields' => array()), ws_flow_config_normalize('web', null));
}

// The fields it declares: a key of letters, digits and _ (lower case, not
// starting with a digit), text or number, each key once, at most 20.
function test_workspace_flow_web_start_fields()
{
	$config = ws_flow_web_normalize(array('fields' => array(
		array('key' => 'Order_No', 'type' => 'text'),
		array('key' => 'amount', 'type' => 'number'),
		array('key' => 'amount', 'type' => 'text'),
		array('key' => '9lives', 'type' => 'text'),
		array('key' => 'bad key', 'type' => 'text'),
		array('key' => 'note', 'type' => 'other'),
		'plain',
	)));

	pg_assert_same(array(
		array('key' => 'order_no', 'type' => 'text'),
		array('key' => 'amount', 'type' => 'number'),
		array('key' => 'note', 'type' => 'text'),
	), $config['fields']);

	$many = array();

	for ($i = 0; $i < 30; $i++) {
		$many[] = array('key' => 'f' . $i, 'type' => 'text');
	}

	pg_assert_same(WS_FLOW_WEB_FIELDS_MAX, count(ws_flow_web_normalize(array('fields' => $many))['fields']));
	pg_assert_same(20, WS_FLOW_WEB_FIELDS_MAX);

	pg_assert_same(array(
		array('key' => 'order_no', 'type' => 'text', 'label' => 'order_no'),
		array('key' => 'amount', 'type' => 'number', 'label' => 'amount'),
		array('key' => 'note', 'type' => 'text', 'label' => 'note'),
	), ws_flow_trigger_outputs('web', $config), 'each field is a {{trigger.<field>}} of its type');
	pg_assert_same(array('order_no', 'amount', 'note'), ws_flow_trigger_fields(array(array('id' => 'a', 'kind' => 'web', 'config' => $config)), ws_flow_kinds()));
}

// A JSON object's top-level values, or the form's fields; a JSON body that
// is not an object is unreadable (null), an empty body gives nothing.
function test_workspace_flow_web_start_body()
{
	pg_assert_same(array('a' => 'x', 'n' => 5, 'list' => array(1, 2)), ws_flow_web_body('application/json; charset=utf-8', '{"a":"x","n":5,"list":[1,2]}', array()));
	pg_assert_same(array('a' => 'x'), ws_flow_web_body('application/x-www-form-urlencoded', 'a=x', array('a' => 'x')));
	pg_assert_same(array('a' => 'x'), ws_flow_web_body('', '{"a":"x"}', array()), 'a body that reads as an object without its type is JSON');
	pg_assert_same(null, ws_flow_web_body('application/json', '[1,2]', array()), 'a list is not an object');
	pg_assert_same(null, ws_flow_web_body('application/json', '{bad', array()));
	pg_assert_same(array(), ws_flow_web_body('application/json', '', array()));
	pg_assert_same(array(), ws_flow_web_body('', '', array()));
}

// Only the declared fields: a number field reads a number or stays empty,
// a text field one line of text cut to its length; a list, an object or a
// missing key is empty.
function test_workspace_flow_web_start_values()
{
	$config = array('fields' => array(
		array('key' => 'order_no', 'type' => 'text'),
		array('key' => 'amount', 'type' => 'number'),
		array('key' => 'qty', 'type' => 'number'),
		array('key' => 'tags', 'type' => 'text'),
		array('key' => 'paid', 'type' => 'text'),
		array('key' => 'missing', 'type' => 'text'),
	));
	$body = array('order_no' => 'A-12', 'amount' => '12.50', 'qty' => 'many', 'tags' => array('a'), 'paid' => true, 'extra' => 'dropped');

	pg_assert_same(array('order_no' => 'A-12', 'amount' => '12.50', 'qty' => '', 'tags' => '', 'paid' => '1', 'missing' => ''), ws_flow_web_values($config, $body));
	pg_assert_same(WS_FLOW_WEB_TEXT_MAX, mb_strlen(ws_flow_web_values($config, array('order_no' => str_repeat('ş', 5000)))['order_no'], 'UTF-8'));
	pg_assert_same('1e3', ws_flow_web_values($config, array('amount' => '1e3'))['amount']);
	pg_assert_same('', ws_flow_web_values($config, array('amount' => 'INF'))['amount']);
}

// 32 random bytes as 64 hex characters, compared in constant time; a key
// of another shape never matches, nor does an empty kept one.
function test_workspace_flow_web_start_secret()
{
	$one = ws_flow_web_secret_new();
	$two = ws_flow_web_secret_new();

	pg_assert_true(ws_flow_web_secret_shape($one));
	pg_assert_same(64, strlen($one));
	pg_assert_true($one !== $two);
	pg_assert_true(ws_flow_web_secret_matches($one, $one));
	pg_assert_false(ws_flow_web_secret_matches($one, $two));
	pg_assert_false(ws_flow_web_secret_matches('', ''));
	pg_assert_false(ws_flow_web_secret_matches($one, strtoupper($one)));
	pg_assert_false(ws_flow_web_secret_matches($one, array($one)));
	pg_assert_same('ws_flow_web_12', ws_flow_web_secret_name(12));
	pg_assert_true(pg_config_text_name_valid(ws_flow_web_secret_name(4294967295)));
	pg_assert_contains('hash_equals(', pg_test_function_source('ws_flow_web_secret_matches'));
}

// The address: the root file, the flow and its secret.
function test_workspace_flow_web_start_url()
{
	$secret = str_repeat('ab', 32);

	pg_assert_same('https://example.com/pinegrap/flow_start.php?flow=7&key=' . $secret, ws_flow_web_url_of('https://example.com/pinegrap', 7, $secret));
}

// A run it started: the fields the run kept, nothing else; a field the
// run lacks is empty.
function test_workspace_flow_web_start_trigger_values()
{
	$context = array('trigger' => 'web', 'web' => array('order_no' => 'A-12', 'amount' => '3'));

	pg_assert_same(array('order_no' => 'A-12', 'amount' => '3'), ws_step_trigger_values($context, false));
	pg_assert_same(array(), ws_step_trigger_values(array('trigger' => 'web'), false));
	pg_assert_same(array('order_no' => 'A-12', 'amount' => '3'), ws_flow_web_context_values($context));
}

// The root file: POST only, counted per address (60 a minute) before the
// key is read, a body over 64 KB refused, a wrong key a firewall offence,
// and the firewall treats it as a service endpoint.
function test_workspace_flow_web_start_root_file()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/flow_start.php');

	pg_assert_contains("define('PG_NO_SESSION', true);", $file);
	pg_assert_contains('ws_flow_web_receive();', $file);
	pg_assert_same(60, WS_FLOW_WEB_PER_MINUTE);
	pg_assert_same(65536, WS_FLOW_WEB_BODY_MAX);

	$receive = pg_test_function_source('ws_flow_web_receive');
	$throttled = strpos($receive, 'ws_flow_web_throttled()');
	$key = strpos($receive, 'ws_flow_web_secret(');

	pg_assert_true(($throttled !== false) && ($key !== false) && ($throttled < $key), 'counted before the key is read');
	pg_assert_contains("'POST'", $receive);
	pg_assert_contains('WS_FLOW_WEB_BODY_MAX + 1', $receive);
	pg_assert_contains('ws_flow_web_offence()', $receive);
	pg_assert_contains("waf_rate_exceeded(waf_ip_subject(\$ip), 'flow_web', WS_FLOW_WEB_PER_MINUTE, 60)", pg_test_function_source('ws_flow_web_throttled'));
	pg_assert_contains('waf_ip_is_counted(', pg_test_function_source('ws_flow_web_throttled'));
	pg_assert_contains('waf_log_event(', pg_test_function_source('ws_flow_web_throttled'));
	pg_assert_contains("'flow_start.php'", pg_test_function_source('waf_is_api_request'));
}

// The secret is kept sealed with the site's key, and sealed again when the
// key changes.
function test_workspace_flow_web_start_sealed()
{
	$found = false;

	foreach (pg_reseal_registry() as $entry) {
		if (($entry['table'] === 'config_text') && (strpos((string) $entry['where'], 'ws_flow_web') !== false)) {
			$found = ($entry['kind'] === 'iv') && ($entry['key'] === 'name');
		}
	}

	pg_assert_true($found, 'in the reseal registry');
	pg_assert_contains('encrypt_string_with_iv(', pg_test_function_source('ws_flow_web_renew'));
}
