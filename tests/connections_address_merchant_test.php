<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for address autocomplete and Google Merchant Center on Connections:
 * each address provider's key or server, and Merchant Center's account,
 * country, feed and issues, are set on their pages under the gate of Site
 * Settings › Ecommerce; the Address Autocomplete card keeps the switch and
 * the provider and shows each provider's connection; the Product Feeds card
 * shows Merchant Center's connection and posts nothing of it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_address_merchant_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_address_merchant_pages_and_registry()
{
	pg_assert_same(array_keys(pg_addr_providers()), pg_connections_screen_address_pages(), 'the screen knows every address provider');

	$registry = pg_connections_registry();
	$found = array(
		'google_places'   => '#settings/commerce/pgset-address-autocomplete',
		'mapbox'          => '#settings/commerce/pgset-address-autocomplete',
		'photon'          => '#settings/commerce/pgset-address-autocomplete',
		'merchant_center' => '#settings/commerce/pgset-feeds',
	);

	foreach ($found as $id => $card) {
		pg_assert_same('connections.php', $registry[$id]['place'], $id . ' is set on its page');
		pg_assert_same($card, $registry[$id]['found_at'], $id . ' is still found at its card');
		pg_assert_true(in_array($id, pg_connections_screen_commerce_pages(), true), $id . ' keeps the Ecommerce gate');
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => true)), $id . '\'s details on its page');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => false)), $id . ': not without the category\'s right');
	}

	foreach (pg_connections_screen_address_pages() as $id) {
		pg_assert_same(array('kind' => 'app', 'key' => 'addr_' . $id), $registry[$id]['state'], $id . ' is connected while it is the provider on');
	}

	pg_assert_same('account', $registry['merchant_center']['state']['kind'], 'Merchant Center by the Google account for its purpose');
	pg_assert_same('#settings/commerce/pgset-feeds', $registry['openai_feed']['place'], 'the OpenAI feed has no account, so it stays on the card');
	pg_assert_same(85, pg_connections_count(), 'no service is added');
}

function test_connections_address_merchant_card_shows_the_address_connection()
{
	$providers = pg_addr_providers();
	$none = array('key' => '', 'key_stored' => false, 'host' => '', 'enabled' => false, 'exists' => false);

	$on = pg_addr_settings_connection_html('mapbox', $providers['mapbox'], array('key' => 'K', 'key_stored' => true) + $none, true);
	pg_assert_contains('id="pgsub-addr-mapbox-connection"', $on);
	pg_assert_contains('data-addr-connected="1"', $on);
	pg_assert_contains('connections.php?service=mapbox', $on, 'it leads to its page');
	pg_assert_contains('pg-tone-teal', $on, 'Connected');

	$off = pg_addr_settings_connection_html('google_places', $providers['google_places'], $none, false);
	pg_assert_contains('data-addr-connected="0"', $off);
	pg_assert_contains('pg-tone-gray', $off, 'Not connected');
	pg_assert_contains(' hidden', $off, 'not the provider the select names');

	pg_assert_contains('data-addr-connected="1"', pg_addr_settings_connection_html('photon', $providers['photon'], $none, true), 'Photon needs no account');
}

function test_connections_address_merchant_card_shows_the_merchant_connection()
{
	$state = pg_merchant_state_parse(null);

	$none = pg_merchant_card_connection_html($state, false);
	pg_assert_contains('id="pgsub-merchant-connection"', $none);
	pg_assert_contains('connections.php?service=merchant_center', $none, 'it leads to its page');
	pg_assert_contains('pg-tone-gray', $none);

	$state['account'] = '123';
	$state['account_name'] = 'Shop';

	$linked = pg_merchant_card_connection_html($state, true);
	pg_assert_contains('pg-tone-teal', $linked, 'an account chosen and reached');
	pg_assert_contains('Shop (123)', $linked);

	pg_assert_contains('pg-tone-gray', pg_merchant_card_connection_html($state, false), 'the Google account is gone');
}

function test_connections_address_merchant_keys_left_the_cards()
{
	$addr = connections_address_merchant_test_source('includes/fn/address_autocomplete.php');
	$start = strpos($addr, "\nfunction pg_addr_settings_fields(");
	$end = strpos($addr, "\nfunction pg_addr_settings_script(");
	pg_assert_true(($start !== false) && ($end > $start));
	$card = substr($addr, $start, $end - $start);

	pg_assert_false(strpos($card, 'type="password"') !== false, 'the card draws no key');
	pg_assert_false(strpos($card, '_host"') !== false, 'nor Photon\'s server');
	pg_assert_false(strpos($card, 'addr_test') !== false, 'nor the test address');

	$merchant = connections_address_merchant_test_source('includes/settings/commerce_merchant.php');
	pg_assert_false(strpos($merchant, 'pg_connect_start_fields(') !== false, 'the Product Feeds card connects no Google account');
	pg_assert_false(strpos($merchant, 'name="merchant_') !== false, 'and posts no Merchant Center field');
	pg_assert_false(strpos(connections_address_merchant_test_source('includes/settings/commerce.save.php'), 'merchant_card') !== false, 'the Ecommerce save writes nothing of Merchant Center');

	$screen = connections_address_merchant_test_source('connections.php');
	pg_assert_contains("array('addr_save', 'addr_remove')", $screen);
	pg_assert_contains("(\$action === 'merchant_save') && connections_commerce_may_manage(\$user)", $screen);
	pg_assert_contains('connections.php?service=', connections_address_merchant_test_source('marketplace_settings.php'), 'the Marketplaces drawer leads to the page');
}
