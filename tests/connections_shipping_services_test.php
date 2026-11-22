<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping services on Connections: the carrier label
 * services' keys (their secrets and the text fields declared as keys) and
 * UPS's, FedEx's and USPS's client ID and secret are typed on their pages
 * under the gate of Site Settings › Ecommerce; the Shipping card keeps the
 * switches and the other choices, shows each service's connection, and
 * refuses a service newly switched on while its keys are missing. The test
 * driver below lives in this file and is registered with its path.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/payments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_labels.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function pg_label_pgtestconn_info()
{
	return array('carriers' => array('aras'), 'formats' => array('pdf'));
}

function pg_label_pgtestconn_fields()
{
	return array(
		array('name' => 'user', 'label' => 'User', 'required' => true, 'connection' => true),
		array('name' => 'secret', 'type' => 'secret', 'label' => 'Secret', 'required' => true),
		array('name' => 'code', 'label' => 'Code', 'connection' => true),
		array('name' => 'sender', 'label' => 'Sender', 'required' => true),
		array('name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => array('test' => 'Test', 'live' => 'Live'), 'default' => 'test'),
		array('name' => 'select_key', 'type' => 'select', 'label' => 'Select', 'options' => array('a' => 'A'), 'connection' => true),
	);
}

function pg_label_pgtestconn_create($shipment, $account)
{
	return $shipment;
}

function connections_shipping_services_test_driver()
{
	static $done = false;

	if (!$done) {
		pg_label_driver_register('pgtestconn', array('label' => 'Test Connection', 'file' => __FILE__));
		$done = true;
	}

	return pg_label_fields('pgtestconn');
}

function connections_shipping_services_test_stored($values = array(), $secrets = array(), $enabled = false)
{
	return array(
		'values'        => $values,
		'secrets'       => $secrets,
		'secret_stored' => ($secrets !== array()),
		'readable'      => true,
		'enabled'       => $enabled,
		'exists'        => true,
	);
}

function connections_shipping_services_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_shipping_services_pages_and_registry()
{
	pg_assert_same(array_keys(pg_label_builtin_drivers()), pg_connections_screen_label_pages(), 'the screen knows every shipped label service');
	pg_assert_same(array_keys(pg_carrier_api_definitions()), pg_connections_screen_carrier_pages(), 'and every carrier API');

	$registry = pg_connections_registry();

	foreach (array_merge(pg_connections_screen_label_pages(), pg_connections_screen_carrier_pages()) as $id) {
		pg_assert_same('connections.php', $registry[$id]['place'], $id . ' is set on its page');
		pg_assert_same('#settings/commerce/pgset-shipping', $registry[$id]['found_at'], $id . ' is still found at the Shipping card');
		pg_assert_true(in_array($id, pg_connections_screen_commerce_pages(), true), $id . ' keeps the Ecommerce gate');
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => true)), $id . '\'s keys on its page');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => false)), $id . ': not without the category\'s right');
	}

	foreach (pg_connections_screen_label_pages() as $id) {
		pg_assert_same(array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array($id)), $registry[$id]['state'], $id . ' is connected while on with its keys');
	}

	foreach (pg_connections_screen_carrier_pages() as $id) {
		pg_assert_same(array('kind' => 'reader', 'function' => 'pg_carrier_api_connected', 'args' => array($id)), $registry[$id]['state'], $id . ' is connected while in use with its keys');
	}

	pg_assert_same('#settings/commerce/pgset-shipping', $registry['shipworks']['place'], 'ShipWorks has no keys, so it stays on the card');
	pg_assert_same(84, pg_connections_count(), 'no service is added');
}

function test_connections_shipping_services_fields_part()
{
	$fields = connections_shipping_services_test_driver();

	pg_assert_same(array('user', 'secret', 'code'), array_column(pg_label_fields_part($fields, 'keys'), 'name'), 'secrets and the text fields declared as keys; a select never');
	pg_assert_same(array('sender', 'mode', 'select_key'), array_column(pg_label_fields_part($fields, 'card'), 'name'), 'the rest stays on the card');

	pg_assert_same(array('username', 'password', 'customer_code'), array_column(pg_label_fields_part(pg_label_fields('aras'), 'keys'), 'name'), 'Aras Kargo: the web service user, its password and the customer code');
	pg_assert_same(array('api_token'), array_column(pg_label_fields_part(pg_label_fields('shippo'), 'keys'), 'name'), 'Shippo: the token; the sender address and customs stay on the card');
	pg_assert_same(array('public_key', 'secret_key'), array_column(pg_label_fields_part(pg_label_fields('sendcloud'), 'keys'), 'name'));
	pg_assert_same(array('api_token'), array_column(pg_label_fields_part(pg_label_fields('kargonomi'), 'keys'), 'name'), 'Kargonomi\'s warehouse stays on the card');
}

function test_connections_shipping_services_keys_complete_and_refusal()
{
	$fields = connections_shipping_services_test_driver();
	$none = connections_shipping_services_test_stored();
	$keys = connections_shipping_services_test_stored(array('user' => 'u'), array('secret' => 's'));

	pg_assert_false(pg_label_keys_complete($fields, $none));
	pg_assert_true(pg_label_keys_complete($fields, $keys), 'the card\'s required sender does not count');
	pg_assert_false(pg_label_keys_complete($fields, array('readable' => false) + $keys), 'unreadable keys are no keys');

	pg_assert_false(pg_label_keys_stored($fields, $none));
	pg_assert_true(pg_label_keys_stored($fields, connections_shipping_services_test_stored(array('code' => 'c'))), 'any key offers Disconnect');
	pg_assert_false(pg_label_keys_stored($fields, connections_shipping_services_test_stored(array('sender' => 's'))), 'a card value is no key');

	pg_assert_contains('Connections', pg_label_choice_refusal('Test Connection', $fields, $none, true), 'switched on without keys');
	pg_assert_same('', pg_label_choice_refusal('Test Connection', $fields, $keys, true), 'with keys');
	pg_assert_same('', pg_label_choice_refusal('Test Connection', $fields, $none, false), 'left off');
	pg_assert_same('', pg_label_choice_refusal('Test Connection', $fields, connections_shipping_services_test_stored(array(), array(), true), true), 'already on: a running store keeps saving');
}

function test_connections_shipping_services_page_and_card_decide_their_own_fields()
{
	$fields = connections_shipping_services_test_driver();
	$stored = connections_shipping_services_test_stored(array('user' => 'u', 'sender' => 'S1', 'mode' => 'live', 'auto_label' => '1', 'auto_label_since' => '1000'), array('secret' => 's'), true);

	// The page: the keys as typed, the switch and the automatic label as stored.
	$post = pg_label_page_post('pgtestconn', $fields, array('label_pgtestconn__user' => ' u2 ', 'label_pgtestconn__sender' => 'X', 'submit_try' => '1'), $stored);
	pg_assert_same('1', $post['label_pgtestconn__enabled'] ?? null, 'the switch as stored');
	pg_assert_same('1', $post['label_pgtestconn__auto_label'] ?? null, 'the automatic label as stored');
	pg_assert_false(isset($post['label_pgtestconn__sender']), 'a card field is not taken from the page');
	pg_assert_same('pgtestconn', $post['label_test'] ?? null, 'Save and try asks for the test');

	$page = pg_label_settings_decide('pgtestconn', pg_label_fields_part($fields, 'keys'), $post, $stored, 5000);
	pg_assert_same(array(), $page['marks']);
	pg_assert_same('u2', $page['values']['user']);
	pg_assert_same('S1', $page['values']['sender'], 'the card\'s sender is kept');
	pg_assert_same('live', $page['values']['mode'], 'and its mode');
	pg_assert_same('1000', $page['values']['auto_label_since'], 'the automatic label keeps its time');
	pg_assert_true($page['enabled']);
	pg_assert_true($page['test']);

	$required = pg_label_settings_decide('pgtestconn', pg_label_fields_part($fields, 'keys'), pg_label_page_post('pgtestconn', $fields, array(), $stored), $stored);
	pg_assert_same(array('label_pgtestconn__user'), array_keys($required['marks']), 'a key is required while the service is on; the stored secret is kept');

	$off = connections_shipping_services_test_stored(array(), array(), false);
	pg_assert_same(array(), pg_label_settings_decide('pgtestconn', pg_label_fields_part($fields, 'keys'), pg_label_page_post('pgtestconn', $fields, array(), $off), $off)['marks'], 'not while it is off');

	// The card: its fields as posted, the keys as stored.
	$card = pg_label_settings_decide('pgtestconn', pg_label_fields_part($fields, 'card'), array(
		'label_pgtestconn__present' => '1',
		'label_pgtestconn__enabled' => '1',
		'label_pgtestconn__user'    => 'ignored',
		'label_pgtestconn__sender'  => 'S2',
		'label_pgtestconn__mode'    => 'test',
	), $stored);
	pg_assert_same('u', $card['values']['user'], 'the page\'s key is kept');
	pg_assert_same('S2', $card['values']['sender']);
	pg_assert_same(array(), $card['secrets'], 'the card writes no secret');
	pg_assert_false(isset($card['values']['auto_label']), 'the card turned the automatic label off');
	pg_assert_true($card['changed']);
}

function test_connections_shipping_services_card_shows_the_connection()
{
	$fields = connections_shipping_services_test_driver();
	$definition = pg_label_driver('pgtestconn');

	$on = pg_label_connection_html('pgtestconn', $definition, $fields, connections_shipping_services_test_stored(array('user' => 'u'), array('secret' => 's')));
	pg_assert_contains('id="label_pgtestconn__connection"', $on);
	pg_assert_contains('connections.php?service=pgtestconn', $on, 'it leads to its page');
	pg_assert_contains('pg-tone-teal', $on, 'Connected');

	$off = pg_label_connection_html('pgtestconn', $definition, $fields, connections_shipping_services_test_stored());
	pg_assert_contains('pg-tone-gray', $off, 'Not connected');
}

function test_connections_shipping_services_carrier_keys()
{
	pg_assert_same('ups', pg_carrier_api_switch('ups'));
	pg_assert_same('fedex', pg_carrier_api_switch('fedex'));
	pg_assert_same('ecommerce_address_verification', pg_carrier_api_switch('usps'), 'USPS is put to use by Verify US Addresses');
	pg_assert_same('', pg_carrier_api_switch('dhl'));

	$none = array('client_id' => '', 'client_secret' => '', 'secret_stored' => false, 'environment' => 'production');
	$kept = array('client_id' => 'ID1', 'client_secret' => 'S1', 'secret_stored' => true, 'environment' => 'test');

	$decision = pg_carrier_api_keys_decide('ups', array('ups_client_id' => ' ID2 ', 'ups_client_secret' => ''), $kept, true);
	pg_assert_same('ID2', $decision['client_id']);
	pg_assert_same('', $decision['client_secret'], 'an empty box keeps the stored secret');
	pg_assert_same(array(), $decision['marks']);
	pg_assert_true($decision['changed']);

	pg_assert_false(pg_carrier_api_keys_decide('ups', array('ups_client_id' => 'ID1'), $kept, true)['changed'], 'nothing new');

	$required = pg_carrier_api_keys_decide('fedex', array(), $none, true);
	pg_assert_same(array('fedex_client_id', 'fedex_client_secret'), array_keys($required['marks']), 'both are required while its rates are on');
	pg_assert_same(array(), pg_carrier_api_keys_decide('fedex', array(), $none, false)['marks'], 'not while they are off');

	$long = pg_carrier_api_keys_decide('usps', array('usps_client_id' => str_repeat('a', 256)), $none, false);
	pg_assert_same(array('usps_client_id'), array_keys($long['marks']), 'longer than the box is refused');

	pg_assert_contains('UPS', pg_carrier_api_choice_refusal('ups', true, false, false), 'switched on without keys');
	pg_assert_same('', pg_carrier_api_choice_refusal('ups', true, true, false), 'already on: a running store keeps saving');
	pg_assert_same('', pg_carrier_api_choice_refusal('ups', true, false, true), 'with keys');
	pg_assert_same('', pg_carrier_api_choice_refusal('ups', false, false, false), 'left off');
	pg_assert_same('', pg_carrier_api_choice_refusal('dhl', true, false, false), 'no carrier');
}

function test_connections_shipping_services_keys_left_the_card()
{
	$labels = connections_shipping_services_test_source('includes/fn/carrier_labels.php');
	$api = connections_shipping_services_test_source('includes/fn/carrier_api.php');
	$save = connections_shipping_services_test_source('includes/settings/commerce.save.php');

	pg_assert_contains("foreach (pg_label_fields_part(\$fields, 'card') as \$field)", $labels, 'the card draws no key of a label service');
	pg_assert_contains("pg_label_settings_decide(\$key, pg_label_fields_part(\$fields, 'card'), \$post, \$settings)", $labels, 'and decides none');
	pg_assert_false(strpos($api, "client_id\" class=\"form-control\"") !== false, 'the card draws no client ID of a carrier');
	pg_assert_contains('pg_carrier_api_choice_refusal(', $save, 'a carrier switched on without keys is refused');

	foreach (array('ups_client_id', 'fedex_client_id', 'usps_client_id', 'label_geliver__api_token', 'label_aras__username', 'label_sendcloud__public_key') as $anchor) {
		pg_assert_same(array('commerce', 'pgset-shipping'), pg_settings_legacy_anchors()[$anchor] ?? null, 'an old link to ' . $anchor . ' lands on the card');
	}

	$screen = connections_shipping_services_test_source('connections.php');
	pg_assert_contains("array('label_save', 'label_remove')", $screen);
	pg_assert_contains("array('carrier_save', 'carrier_remove')", $screen);
}
