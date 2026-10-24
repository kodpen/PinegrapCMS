<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace webhook: the receiver's checks
 * (includes/api/outbound/webhook_receiver.php) - which key a delivery
 * presents, which status refuses it, when a webhook may be registered - the
 * Trendyol connector's webhook calls (read a delivery, register, remove), the
 * job's rebuilt package for accepting webhook-imported orders, and the account
 * screen keeping the webhook's key and id across a Save.
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers from the saved responses in
 * tests/fixtures/trendyol/ (built in the shape of their webhook documentation,
 * October 2026, not recorded from a live account).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_receiver.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function webhook_test_account($overrides = array(), $credentials = array())
{
	return array_merge(array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'status'      => 'active',
		'pull_orders' => 1,
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
			'webhook_key' => 'k3y-0f-th1rty-tw0-r4nd0m-byt3s-wr1tt3n-url-s4f3',
			'webhook_id'  => '6f1c2a9e-3b4d-4e5f-8a7b-9c0d1e2f3a4b',
		), $credentials),
	), $overrides);
}

function webhook_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// A transport that answers every call with $status and $body and writes each
// call it was given into $calls.
function webhook_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

/* ------------------------------------------------------------- the key */

function test_marketplace_webhook_presented_key_reads_the_api_key_header_first()
{
	pg_assert_same('abc', mp_webhook_presented_key(array(
		'HTTP_X_API_KEY'     => ' abc ',
		'PHP_AUTH_PW'        => 'basic-password',
		'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('user:other'),
	)), 'x-api-key wins, trimmed');

	pg_assert_same('', mp_webhook_presented_key(array()), 'nothing presented');
	pg_assert_same('', mp_webhook_presented_key(array('HTTP_X_API_KEY' => '   ')), 'a blank header is no key');
	pg_assert_same('', mp_webhook_presented_key(array('HTTP_X_API_KEY' => array('x'))), 'not text');
}

function test_marketplace_webhook_presented_key_reads_basic_authentication()
{
	pg_assert_same('pw', mp_webhook_presented_key(array('PHP_AUTH_USER' => 'u', 'PHP_AUTH_PW' => 'pw')));

	pg_assert_same('pa:ss', mp_webhook_presented_key(array('HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('user:pa:ss'))),
		'the password is everything after the first colon');

	pg_assert_same('pw2', mp_webhook_presented_key(array('REDIRECT_HTTP_AUTHORIZATION' => 'basic ' . base64_encode('u:pw2'))),
		'the header a rewrite rule passes on, in any case');

	pg_assert_same('', mp_webhook_presented_key(array('HTTP_AUTHORIZATION' => 'Bearer abc')), 'a bearer token is not a key');
	pg_assert_same('', mp_webhook_presented_key(array('HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('no-colon'))));
	pg_assert_same('', mp_webhook_presented_key(array('HTTP_AUTHORIZATION' => 'Basic %%%')), 'not base64');
}

/* -------------------------------------------------------- the refusals */

function test_marketplace_webhook_gate_answers_each_refusal_with_its_status()
{
	$key = 'k3y-0f-th1rty-tw0-r4nd0m-byt3s-wr1tt3n-url-s4f3';

	pg_assert_same(0, mp_webhook_gate('POST', webhook_test_account(), $key), 'the right key on an account that takes deliveries');

	pg_assert_same(405, mp_webhook_gate('GET', webhook_test_account(), $key));
	pg_assert_same(404, mp_webhook_gate('POST', null, $key), 'no such account');

	pg_assert_same(403, mp_webhook_gate('POST', webhook_test_account(array('status' => 'paused')), $key), 'paused');
	pg_assert_same(403, mp_webhook_gate('POST', webhook_test_account(array('pull_orders' => 0)), $key), 'not bringing orders in');
	pg_assert_same(403, mp_webhook_gate('POST', webhook_test_account(array('provider' => 'n11')), $key), 'a connector without webhooks');

	pg_assert_same(401, mp_webhook_gate('POST', webhook_test_account(), 'wrong'));
	pg_assert_same(401, mp_webhook_gate('POST', webhook_test_account(), ''), 'no key presented');
	pg_assert_same(401, mp_webhook_gate('POST', webhook_test_account(array(), array('webhook_key' => '')), ''),
		'no key stored: nothing matches, not even nothing');
	pg_assert_same(401, mp_webhook_gate('POST', webhook_test_account(array(), array('webhook_key' => array('x'))), 'x'));
}

function test_marketplace_webhook_register_problem()
{
	pg_assert_contains('already registered', mp_webhook_register_problem(webhook_test_account()), 'one at a time');

	$fresh = webhook_test_account(array(), array('webhook_id' => ''));

	pg_assert_same('', mp_webhook_register_problem($fresh));
	pg_assert_contains('Switch the account on', mp_webhook_register_problem(array_merge($fresh, array('status' => 'paused'))));
	pg_assert_contains('Switch the account on', mp_webhook_register_problem(array_merge($fresh, array('pull_orders' => 0))));
}

/* ------------------------------------------------------- the connector */

function test_marketplace_webhook_trendyol_names_its_webhook_entries()
{
	pg_assert_true(mp_can('trendyol', 'webhook_packages'));
	pg_assert_true(mp_can('trendyol', 'register_webhook'));
	pg_assert_true(mp_can('trendyol', 'remove_webhook'));
	pg_assert_false(mp_can('n11', 'webhook_packages'), 'n11 has no webhooks');
}

function test_marketplace_webhook_trendyol_reads_a_delivery()
{
	$result = mp_trendyol_webhook_packages(webhook_test_account(), json_decode(webhook_test_fixture('webhook-package.json'), true));

	pg_assert_true($result['ok']);
	pg_assert_same(1, count($result['data']['packages']));

	$package = $result['data']['packages'][0];

	pg_assert_same('3330111144', $package['remote_package_id']);
	pg_assert_same('10654433333', $package['remote_order_id']);
	pg_assert_same('Created', $package['remote_status']);
	pg_assert_same(1792720500, $package['placed_at']);
	pg_assert_same('8680000000028', $package['lines'][0]['code']);
	pg_assert_same(1000, $package['lines'][0]['discount_total']);

	$totals = mp_order_totals($package['lines']);

	pg_assert_same(16980, $totals['total'], 'packageGrossAmount - packageSellerDiscount');
}

function test_marketplace_webhook_trendyol_reads_a_bare_package_and_skips_the_rest()
{
	$page = json_decode(webhook_test_fixture('webhook-package.json'), true);

	$bare = mp_trendyol_webhook_packages(webhook_test_account(), $page['content'][0]);

	pg_assert_same(array('3330111144'), array_column($bare['data']['packages'], 'remote_package_id'), 'one package without the page around it');

	$awaiting = $page;
	$awaiting['content'][0]['status'] = 'Awaiting';

	pg_assert_same(array(), mp_trendyol_webhook_packages(webhook_test_account(), $awaiting)['data']['packages'], 'payment not confirmed');
	pg_assert_same(array(), mp_trendyol_webhook_packages(webhook_test_account(), array('hello' => 'world'))['data']['packages']);
	pg_assert_true(mp_trendyol_webhook_packages(webhook_test_account(), array())['ok'], 'nothing to import is not an error');
}

function test_marketplace_webhook_trendyol_registers_with_an_api_key_for_every_status()
{
	$calls = array();
	$url = 'https://shop.example.com/pinegrap/marketplace_webhook.php?account=7';

	$result = mp_trendyol_register_webhook(webhook_test_account(), $url, 'the-key',
		webhook_test_transport(200, webhook_test_fixture('webhook-created.json'), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('6f1c2a9e-3b4d-4e5f-8a7b-9c0d1e2f3a4b', $result['data']['webhook_id']);

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/webhook/sellers/123456/webhooks', $calls[0]['url']);
	pg_assert_same('{"url":"' . $url . '","authenticationType":"API_KEY","apiKey":"the-key","subscribedStatuses":[]}',
		$calls[0]['options']['body'], 'every status: an empty list');
}

function test_marketplace_webhook_trendyol_refuses_an_address_they_would_refuse()
{
	$calls = array();
	$transport = webhook_test_transport(200, webhook_test_fixture('webhook-created.json'), $calls);

	$result = mp_trendyol_register_webhook(webhook_test_account(), 'http://shop.example.com/marketplace_webhook.php?account=7', 'k', $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('https', $result['error']);

	foreach (array('https://trendyol-shop.example.com/x', 'https://shop.example.com/DOLAP/x', 'https://LocalHost/x') as $url) {
		$result = mp_trendyol_register_webhook(webhook_test_account(), $url, 'k', $transport);

		pg_assert_false($result['ok'], $url);
		pg_assert_contains('"trendyol", "dolap" or "localhost"', $result['error'], $url);
	}

	pg_assert_same(0, count($calls), 'nothing was sent');
}

function test_marketplace_webhook_trendyol_registration_without_an_id_is_an_error()
{
	$calls = array();

	$result = mp_trendyol_register_webhook(webhook_test_account(), 'https://shop.example.com/x', 'k',
		webhook_test_transport(200, '{}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('did not return an id', $result['error']);

	$result = mp_trendyol_register_webhook(webhook_test_account(), 'https://shop.example.com/x', 'k',
		webhook_test_transport(400, webhook_test_fixture('error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('Kategori bulunamadı', $result['error'], 'their refusal is passed on');
}

function test_marketplace_webhook_trendyol_removes_it_and_takes_a_404_as_gone()
{
	foreach (array(200, 204, 404) as $status) {
		$calls = array();

		$result = mp_trendyol_remove_webhook(webhook_test_account(), webhook_test_transport($status, '', $calls));

		pg_assert_true($result['ok'], $status . ': ' . $result['error']);
		pg_assert_same('DELETE', $calls[0]['method']);
		pg_assert_same('https://apigw.trendyol.com/integration/webhook/sellers/123456/webhooks/6f1c2a9e-3b4d-4e5f-8a7b-9c0d1e2f3a4b',
			$calls[0]['url']);
		pg_assert_false(isset($calls[0]['options']['body']), 'a DELETE carries no body');
	}

	$calls = array();

	$result = mp_trendyol_remove_webhook(webhook_test_account(), webhook_test_transport(500, '', $calls));

	pg_assert_false($result['ok'], 'their outage is not a removal');
	pg_assert_true($result['retry']);
}

function test_marketplace_webhook_trendyol_removes_nothing_without_an_id()
{
	$calls = array();
	$transport = webhook_test_transport(200, '', $calls);

	$result = mp_trendyol_remove_webhook(webhook_test_account(array(), array('webhook_id' => '')), $transport);

	pg_assert_false($result['ok']);
	pg_assert_contains('No webhook is registered', $result['error']);

	$result = mp_trendyol_remove_webhook(webhook_test_account(array(), array('webhook_id' => '../../products')), $transport);

	pg_assert_false($result['ok'], 'an id that is not one stays out of the path');

	pg_assert_same(0, count($calls));
}

/* ------------------------------------------------------------ the job */

function test_marketplace_webhook_package_rebuilt_from_the_map_row()
{
	$lines = array(array('remote_line_id' => '4765111141', 'code' => '8680000000028', 'quantity' => 2));

	$package = mp_sync_package_from_row(array(
		'order_id'          => '91',
		'remote_order_id'   => '10654433333',
		'remote_package_id' => '3330111144',
		'remote_status'     => 'Created',
		'lines_json'        => json_encode($lines),
	));

	pg_assert_same(array(
		'remote_order_id'   => '10654433333',
		'remote_package_id' => '3330111144',
		'remote_status'     => 'Created',
		'lines'             => $lines,
	), $package);

	pg_assert_true(mp_sync_approvable(webhook_test_account(), $package));

	pg_assert_same(array(), mp_sync_package_from_row(array('lines_json' => 'not json'))['lines']);
	pg_assert_same(array(), mp_sync_package_from_row(array('lines_json' => null))['lines']);
}

/* ------------------------------------------------- firewall and screen */

// The marketplace posts without a browser's user agent; the endpoint is one
// of the firewall's API scripts, so the bot policy does not turn it away.
function test_marketplace_webhook_endpoint_is_an_api_script_for_the_firewall()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	$start = strpos($source, 'function waf_is_api_request()');

	pg_assert_true($start !== false);
	pg_assert_contains("'marketplace_webhook.php'", substr($source, $start, 400));
}

// A Save writes the form's credential fields; the webhook's key and id are not
// among them and must survive it, or a registered webhook delivers with a key
// nothing here knows.
function test_marketplace_webhook_account_save_keeps_the_webhook_credentials()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	$start = strpos($source, "if (\$action === 'update' && \$account) {");
	$end = strpos($source, "if (\$action === 'test' && \$account) {");

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end));

	$update = substr($source, $start, $end - $start);

	$merge = strpos($update, '$credentials = array_merge((array)$account[\'credentials\'], $credentials);');
	$write = strpos($update, 'mp_credentials_encode($credentials)');

	pg_assert_true($merge !== false, 'the stored credentials are merged under the posted ones');
	pg_assert_true(($merge !== false) && ($write !== false) && ($merge < $write), 'before they are written');
}

// The key is made and read only where the screen handles a write; nothing
// that draws the page touches it.
function test_marketplace_webhook_screen_never_draws_the_key()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	$writes_end = strpos($source, '// The two-step mapping for one article');

	pg_assert_true($writes_end !== false);

	$offset = 0;
	$seen = 0;

	while (($found = strpos($source, 'webhook_key', $offset)) !== false) {
		$seen++;
		pg_assert_true($found < $writes_end, 'webhook_key at offset ' . $found . ' is outside the writes');
		$offset = $found + 1;
	}

	pg_assert_true($seen > 0, 'the register action keeps the key');
	pg_assert_contains("mp_webhook_register_problem(\$account)", $source, 'registration is refused while one is registered');
}

// Deleting an account removes its webhook from the marketplace first, and a
// failed removal leaves the account in place: a webhook left behind delivers
// to an address answering 404 for ever.
function test_marketplace_webhook_account_delete_removes_the_webhook_first()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	$start = strpos($source, "if (\$action === 'delete' && \$account) {");
	$end = strpos($source, "if (\$action === 'categories' && \$account) {");

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end));

	$delete = substr($source, $start, $end - $start);

	$remove = strpos($delete, "mp_call(\$account['provider'], 'remove_webhook', array(\$account))");
	$refuse = strpos($delete, 'if (!$removed[\'ok\']) {');
	$back = strpos($delete, "header('Location: ' . \$screen_url . '?account=' . \$account_id);");
	$first_delete = strpos($delete, 'DELETE FROM');

	pg_assert_true($remove !== false, 'the webhook is removed');
	pg_assert_true(($remove !== false) && ($first_delete !== false) && ($remove < $first_delete), 'before anything is deleted');
	pg_assert_true(($refuse !== false) && ($back !== false) && ($refuse < $back) && ($back < $first_delete),
		'a failed removal goes back to the account without deleting');
	pg_assert_contains("mp_can(\$account['provider'], 'remove_webhook')", $delete);
	pg_assert_contains('h($removed[\'error\'])', $delete, 'the connector\'s message is escaped');
}

/* ---------------------------------------------------- whose webhook */

// A webhook belongs to one seller in one environment. A save that changed
// either under it would let the old one keep delivering - test packages
// imported as real orders - so those changes are refused while it exists.
function test_marketplace_webhook_owner_change_is_the_seller_or_the_environment()
{
	$stored = webhook_test_account()['credentials'];

	pg_assert_true(mp_webhook_owner_changed('trendyol', $stored, array_merge($stored, array('environment' => 'stage'))), 'live to test');
	pg_assert_true(mp_webhook_owner_changed('trendyol', $stored, array_merge($stored, array('seller_id' => '654321'))), 'another seller');

	pg_assert_false(mp_webhook_owner_changed('trendyol', $stored, $stored), 'nothing changed');
	pg_assert_false(mp_webhook_owner_changed('trendyol', $stored, array_merge($stored, array('api_key' => 'new', 'api_secret' => 'new'))),
		'a new key and secret for the same seller');
	pg_assert_false(mp_webhook_owner_changed('trendyol', $stored, array_merge($stored, array('seller_id' => ' 123456 '))), 'the same id, spaced');

	$old = $stored;
	unset($old['environment']);

	pg_assert_false(mp_webhook_owner_changed('trendyol', $old, array_merge($stored, array('environment' => 'production'))),
		'a row saved before the environment existed is live, like the default');
	pg_assert_true(mp_webhook_owner_changed('trendyol', $old, array_merge($stored, array('environment' => 'stage'))));

	pg_assert_false(mp_webhook_owner_changed('n11', array('app_key' => 'a'), array('app_key' => 'b')), 'a provider that names no owner fields');
	pg_assert_same(array('seller_id', 'environment'), mp_providers()['trendyol']['webhook_owner_fields']);
}

// The screen asks before it writes anything, and goes back without writing.
function test_marketplace_webhook_save_refuses_to_change_the_webhooks_owner()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	$start = strpos($source, "if (\$action === 'update' && \$account) {");
	$end = strpos($source, "if (\$action === 'test' && \$account) {");

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end));

	$update = substr($source, $start, $end - $start);

	$check = strpos($update, "mp_webhook_owner_changed(\$account['provider'], \$account['credentials'], \$credentials)");
	$back = ($check === false) ? false : strpos($update, "header('Location: ' . \$screen_url . '?account=' . \$account_id);", $check);
	$write = strpos($update, 'UPDATE marketplace_accounts SET');

	pg_assert_true($check !== false, 'the owner fields are compared');
	pg_assert_true(($check !== false) && ($back !== false) && ($write !== false) && ($back < $write), 'and the save goes back before anything is written');
	pg_assert_contains("lang('Remove the webhook before changing the environment or the seller ID: the webhook was registered for the current ones and would keep delivering their packages.')", $update);
}
