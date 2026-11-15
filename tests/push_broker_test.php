<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for native push through the push broker (includes/fn/push_broker.php)
 * and its two routes, POST /push/devices and POST /push/devices/delete: the
 * broker address, the headers of a broker call, what each broker answer means,
 * and the route rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/push_broker.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The source of one function, comments left out.
function push_broker_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function test_push_broker_address_is_empty_until_the_broker_is_live()
{
	pg_assert_same('', PG_PUSH_BROKER_URL, 'no broker yet: the routes answer 503');
	pg_assert_same('', pg_push_broker_base(PG_PUSH_BROKER_URL));
}

function test_push_broker_base_takes_only_an_https_address()
{
	pg_assert_same('https://push.pinegrap.com', pg_push_broker_base(' https://push.pinegrap.com/ '));
	pg_assert_same('https://push.example.test:8443/broker', pg_push_broker_base('https://push.example.test:8443/broker/'));

	foreach (array('', 'http://push.pinegrap.com', 'push.pinegrap.com', 'https://', 'https://u:p@push.pinegrap.com',
		'https://push.pinegrap.com/?a=1', 'https://push.pinegrap.com/#x', "https://push.pinegrap.com/\nx", 'https://push pinegrap.com') as $value) {
		pg_assert_same('', pg_push_broker_base($value), var_export($value, true));
	}
}

function test_push_broker_headers_sign_like_the_sender_and_add_the_licence_key()
{
	pg_assert_same(array(
		'Authorization: vapid t=TOKEN, k=PUBLIC',
		'Content-Type: application/json',
		'Accept: application/json, application/problem+json',
		'X-Pinegrap-License: ABCD2345EFGH6789',
		'X-Pinegrap-Site: www.example.com',
	), pg_push_broker_headers('TOKEN', 'PUBLIC', 'ABCD2345EFGH6789', 'www.example.com'));

	$headers = pg_push_broker_headers('TOKEN', 'PUBLIC', '', '');
	pg_assert_same(3, count($headers), 'no licence key and no site name: neither header');
	pg_assert_same(0, count(preg_grep('/^X-Pinegrap-/', $headers)));
}

function test_push_broker_register_answer_gives_the_endpoint()
{
	foreach (array(200, 201) as $status) {
		$answer = pg_push_broker_answer('register', array('status' => $status, 'body' => '{"endpoint":"https://push.pinegrap.com/e/abc","expires_at":"2027-01-01T00:00:00Z"}'));
		pg_assert_true($answer['ok'], (string) $status);
		pg_assert_same('https://push.pinegrap.com/e/abc', $answer['endpoint']);
	}

	foreach (array('', '{}', '{"endpoint":"http://push.pinegrap.com/e/abc"}', '{"endpoint":"https://' . str_repeat('a', 500) . '.com/"}', '{"endpoint":12}') as $body) {
		$answer = pg_push_broker_answer('register', array('status' => 201, 'body' => $body));
		pg_assert_false($answer['ok'], $body);
		pg_assert_same(array('unreadable', 503, 'service_unavailable'), array($answer['reason'], $answer['status'], $answer['code']), $body);
	}
}

function test_push_broker_register_refusals_are_passed_on()
{
	$busy = pg_push_broker_answer('register', array('status' => 429, 'body' => '', 'headers' => array('retry-after' => '120')));
	pg_assert_same(array('busy', 429, 'rate_limited', 120), array($busy['reason'], $busy['status'], $busy['code'], $busy['retry_after']));
	pg_assert_same(60, pg_push_broker_answer('register', array('status' => 429))['retry_after'], 'no Retry-After: a minute');
	pg_assert_same(3600, pg_push_broker_answer('register', array('status' => 429, 'headers' => array('retry-after' => '999999')))['retry_after'], 'at most an hour');

	$token = pg_push_broker_answer('register', array('status' => 422, 'body' => '{"error":{"code":"bad_token","message":"The token is not an FCM token."}}'));
	pg_assert_same(array('token', 422, 'validation_failed', 'token', 'The token is not an FCM token.'), array($token['reason'], $token['status'], $token['code'], $token['field'], $token['detail']));

	$refused = pg_push_broker_answer('register', array('status' => 403, 'body' => '{"type":"about:blank","title":"Quota","detail":"The free quota of this site is used up."}'));
	pg_assert_same(array('refused', 403, 'forbidden', 'The free quota of this site is used up.'), array($refused['reason'], $refused['status'], $refused['code'], $refused['detail']));

	foreach (array(0, 500, 502, 503) as $status) {
		$down = pg_push_broker_answer('register', array('status' => $status, 'body' => '{"detail":"internal"}'));
		pg_assert_same(array('unreachable', 503, 'service_unavailable', ''), array($down['reason'], $down['status'], $down['code'], $down['detail']), (string) $status);
	}
}

function test_push_broker_detail_is_plain_short_text()
{
	pg_assert_same('Too many devices.', pg_push_broker_detail(array('error' => array('message' => "<b>Too</b> many\n\tdevices."))));
	pg_assert_same('', pg_push_broker_detail(array('detail' => "\xff\xfe")), 'not UTF-8');
	pg_assert_same('', pg_push_broker_detail('text'));
	pg_assert_same(300, mb_strlen(pg_push_broker_detail(array('title' => str_repeat('ş', 400)))));
}

function test_push_broker_forget_counts_a_gone_endpoint_as_forgotten()
{
	foreach (array(200, 202, 204, 404, 410) as $status) {
		pg_assert_true(pg_push_broker_answer('forget', array('status' => $status))['ok'], (string) $status);
	}

	foreach (array(0, 403, 429, 500) as $status) {
		pg_assert_false(pg_push_broker_answer('forget', array('status' => $status))['ok'], (string) $status);
	}
}

function test_push_broker_call_sends_nothing_without_a_broker()
{
	$called = false;
	$answer = pg_push_broker_call('register', array('provider' => 'fcm', 'token' => 't', 'bundle_id' => 'com.example'), function () use (&$called) {
		$called = true;
		return array('status' => 201, 'body' => '{"endpoint":"https://push.pinegrap.com/e/1"}');
	});

	pg_assert_false($called, 'the stub broker is never asked');
	pg_assert_same(array(false, 503), array($answer['ok'], $answer['status']));
}

function test_push_broker_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('push.devices.register' => '/push/devices', 'push.devices.unregister' => '/push/devices/delete') as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id);
		pg_assert_same(array('POST', $path, 'account:write', true), array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope'], $routes[$id]['dry_run']), $id);
	}

	pg_assert_same(201, $routes['push.devices.register']['status']);

	$params = array_column($routes['push.devices.register']['params'], null, 'name');
	pg_assert_same(array('fcm', 'apns'), $params['provider']['values']);
	pg_assert_same(pg_push_broker_providers(), $params['provider']['values'], 'the route takes the module\'s providers');
	pg_assert_true(!empty($params['token']['required']) && !empty($params['bundle_id']['required']));
}

// A team devices session only, every check before the rehearsal, and the
// broker asked only after it.
function test_push_broker_route_handlers_check_before_the_rehearsal()
{
	$account = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');

	foreach (array('api_push_device_load', 'api_push_device_refuse', 'api_push_device_register', 'api_push_device_unregister') as $name) {
		pg_assert_contains('function ' . $name . '(', $account, $name);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');

	pg_assert_contains("oauth_client_id", push_broker_test_source('api_push_device_load'), 'an OAuth connection is refused');

	foreach (array('api_push_device_register' => 'pg_push_broker_call(', 'api_push_device_unregister' => 'pg_push_broker_forget_endpoints(') as $name => $broker) {
		$source = push_broker_test_source($name);
		pg_assert_true(strpos($source, 'api_push_device_load(') < strpos($source, 'api_dry_run_stop('), $name . ': the gate first');
		pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, $broker), $name . ': the broker after the rehearsal');
	}

	pg_assert_true(strpos(push_broker_test_source('api_push_device_register'), 'bundle_id') < strpos(push_broker_test_source('api_push_device_register'), 'api_dry_run_stop('), 'the bundle id is checked before the rehearsal');
}

function test_push_broker_schema_step()
{
	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains("upgrade_2026_11_0_push_provider();           // 11.0.215", $migration);
	pg_assert_contains("install_add_column('push_subscriptions', 'provider', \"VARCHAR(8) NOT NULL DEFAULT ''\");", $migration);
}

// A revoke, a deleted registration and a row an operator stops each have the
// broker forget the endpoint before the row is deleted.
function test_push_broker_forget_comes_before_every_delete_of_a_native_row()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/devices.php');

	$revoke = push_broker_test_source('api_device_forget_push');
	pg_assert_true(strpos($revoke, 'pg_push_broker_forget_endpoints(') < strpos($revoke, 'DELETE FROM push_subscriptions'), 'device revoke');

	require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');

	$unregister = push_broker_test_source('api_push_device_unregister');
	pg_assert_true(strpos($unregister, 'pg_push_broker_forget_endpoints(') < strpos($unregister, 'pg_push_subscription_delete('), 'POST /push/devices/delete');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/view_sessions.php');
	$stop = substr($screen, strpos($screen, "'revoke_push'"));
	pg_assert_true(strpos($stop, 'pg_push_broker_forget_endpoints(') < strpos($stop, 'DELETE FROM push_subscriptions'), 'the sessions screen');
}

function test_push_broker_forget_asks_nothing_without_a_broker()
{
	pg_assert_same(null, pg_push_broker_forget_endpoints(array('https://push.pinegrap.com/e/1')), 'returns before any call');
	pg_assert_contains("pg_push_broker_base(PG_PUSH_BROKER_URL) === ''", push_broker_test_source('pg_push_broker_forget_endpoints'));
}
