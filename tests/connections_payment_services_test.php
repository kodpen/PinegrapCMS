<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Stripe, PayPal, PayTR and Givex on Connections: the payment
 * drivers' keys (text and secret boxes) and PayPal Express Checkout's and
 * Givex's config keys are typed on their pages under the gate of Site
 * Settings › Ecommerce; the cards keep the switches, Mode, Transaction Type
 * and the drivers' other choices, show each service's connection, and refuse
 * a service newly chosen or switched on while its keys are missing.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_payment_services_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function connections_payment_services_test_settings($values = array(), $secrets = array(), $enabled = false)
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

function test_connections_payment_services_pages_and_registry()
{
	pg_assert_same(pg_payment_driver_pages(), pg_connections_screen_driver_pages(), 'the screen knows the same driver pages');

	foreach (array('stripe', 'paypal', 'paytr', 'givex') as $id) {
		$row = pg_connections_registry()[$id];
		pg_assert_same('connections.php', $row['place'], $id . ' is set on its page');
		pg_assert_same(array('kind' => 'reader', 'function' => 'pg_payment_connection_ready', 'args' => array($id)), $row['state'], $id . ' is connected while it takes payments with its keys');
		pg_assert_true(in_array($id, pg_connections_screen_commerce_pages(), true), $id . ' keeps the Ecommerce gate');
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => true)), $id . '\'s keys on its page');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => false)), $id . ': not without the category\'s right');
	}

	pg_assert_same('#settings/commerce/pgset-payments', pg_connections_registry()['paypal']['found_at'], 'PayPal is still found at the card');
	pg_assert_same('#settings/commerce/pgset-giftcards', pg_connections_registry()['givex']['found_at'], 'Givex is still found at its card');
	pg_assert_same('paypal_express', pg_payment_method_connection_for_page('paypal'), 'Express Checkout\'s keys are on PayPal\'s page, no service of its own');
	pg_assert_false(isset(pg_connections_registry()['paypal_express']), 'so the count does not change');
	pg_assert_same('givex', pg_payment_method_connection_for_page('givex'));
	pg_assert_same('', pg_payment_method_connection_for_page('stripe'));
}

function test_connections_payment_services_fields_part()
{
	$keys = function ($driver) {
		return array_column(pg_payment_fields_part(pg_payment_fields($driver), 'keys'), 'name');
	};
	$card = function ($driver) {
		return array_column(pg_payment_fields_part(pg_payment_fields($driver), 'card'), 'name');
	};

	pg_assert_same(array('publishable_key', 'secret_key', 'webhook_secret'), $keys('stripe'));
	pg_assert_same(array('link', 'method_ideal', 'method_bancontact', 'method_eps', 'method_p24', 'method_klarna', 'method_afterpay_clearpay'), $card('stripe'), 'Link and the payment methods stay on the card');
	pg_assert_same(array('client_id', 'client_secret', 'webhook_id'), $keys('paypal'));
	pg_assert_same(array('mode'), $card('paypal'), 'Mode stays on the card');
	pg_assert_same(array('merchant_id', 'merchant_key', 'merchant_salt'), $keys('paytr'));
	pg_assert_same(array('mode', 'max_installment'), $card('paytr'));
}

function test_connections_payment_services_keys_complete_and_refusal()
{
	$fields = pg_payment_fields('paytr');
	$definition = pg_payment_driver('paytr');
	$full = connections_payment_services_test_settings(array('merchant_id' => 'M1'), array('merchant_key' => 'k', 'merchant_salt' => 's'));
	$half = connections_payment_services_test_settings(array('merchant_id' => 'M1'), array('merchant_key' => 'k'));
	$none = connections_payment_services_test_settings();

	pg_assert_true(pg_payment_driver_keys_complete($fields, $full));
	pg_assert_false(pg_payment_driver_keys_complete($fields, $half), 'a required secret is missing');
	pg_assert_true(pg_payment_driver_keys_stored($fields, $half), 'Disconnect is offered');
	pg_assert_false(pg_payment_driver_keys_stored($fields, $none));
	pg_assert_true(pg_payment_driver_keys_complete(pg_payment_fields('stripe'), connections_payment_services_test_settings(array('publishable_key' => 'pk'), array('secret_key' => 'sk'))), 'the webhook secret is not required');

	pg_assert_true(pg_payment_driver_choice_refusal($definition, $fields, $half, true, false) !== '', 'switched on without its keys is refused');
	pg_assert_same('', pg_payment_driver_choice_refusal($definition, $fields, $half, true, true), 'a switch already on always saves');
	pg_assert_same('', pg_payment_driver_choice_refusal($definition, $fields, $half, false, false), 'switched off saves');
	pg_assert_same('', pg_payment_driver_choice_refusal($definition, $fields, $full, true, false), 'with its keys it may be switched on');
}

function test_connections_payment_services_page_and_card_decide_their_own_fields()
{
	$definition = pg_payment_driver('paypal');
	$fields = pg_payment_fields('paypal');
	$stored = connections_payment_services_test_settings(array('client_id' => 'C1', 'mode' => 'sandbox', 'webhook_id' => 'W1'), array('client_secret' => 'S1'), true);

	$post = pg_payment_driver_page_post($definition, $fields, array('payment_paypal__client_id' => 'C2', 'payment_paypal__mode' => 'live', 'payment_paypal__enabled' => ''), $stored);
	pg_assert_false(isset($post['payment_paypal__mode']), 'the page posts no choice of the card');
	pg_assert_same('1', $post['payment_paypal__enabled'], 'the switch as stored');

	$page = pg_payment_settings_decide($definition, pg_payment_fields_part($fields, 'keys'), $post, $stored, true);
	pg_assert_same(array('client_id' => 'C2', 'webhook_id' => ''), $page['values'], 'only the keys');
	pg_assert_true($page['enabled'], 'the page never switches it off');
	pg_assert_same(array(), $page['secrets'], 'an empty secret box keeps the stored one');

	$card = pg_payment_settings_decide($definition, pg_payment_fields_part($fields, 'card'), array('payment_paypal__present' => '1', 'payment_paypal__enabled' => '1', 'payment_paypal__mode' => 'live'), $stored, false);
	pg_assert_same(array('mode' => 'live'), $card['values'], 'the card writes its choices alone');
	pg_assert_same(array(), $card['marks'], 'and asks for no key');

	$store = connections_payment_services_test_source('includes/fn/payments.php');
	pg_assert_contains('$values = array_merge(is_array($stored_options) ? array_filter($stored_options, \'is_scalar\') : array(), $values);', $store, 'a save keeps the other side\'s values');
}

function test_connections_payment_services_card_shows_the_connection()
{
	$definition = pg_payment_driver('stripe');
	$settings = connections_payment_services_test_settings(array('publishable_key' => 'pk_live_x', 'link' => '1'), array('secret_key' => 'sk_live_secret'));
	$html = pg_payment_settings_card_fields_html($definition, pg_payment_fields('stripe'), $settings);

	pg_assert_contains('name="payment_stripe__link"', $html, 'Link stays');
	pg_assert_contains('name="payment_stripe__present"', $html, 'the card still posts its choices');
	pg_assert_false(strpos($html, 'payment_stripe__publishable_key') !== false, 'no key box');
	pg_assert_false(strpos($html, 'payment_callback.php') !== false, 'the webhook address is on the page');
	pg_assert_false(strpos($html, 'sk_live_secret') !== false);
	pg_assert_contains('id="pgsub-payment-stripe-connection"', $html);
	pg_assert_contains('connections.php?service=stripe', $html);
	pg_assert_contains('pg-tone-teal', $html, 'connected');

	$off = pg_payment_driver_connection_html(pg_payment_driver('paytr'), pg_payment_fields('paytr'), connections_payment_services_test_settings());
	pg_assert_contains('pg-tone-gray', $off, 'not connected');
	pg_assert_contains('connections.php?service=paytr', $off);
}

function test_connections_payment_services_method_keys()
{
	$definitions = pg_payment_method_connections();
	$columns = pg_payment_method_connection_columns();

	foreach (array('ecommerce_paypal_express_checkout_api_password', 'ecommerce_paypal_express_checkout_api_signature', 'ecommerce_givex_password') as $secret) {
		pg_assert_true(in_array($secret, $columns, true), $secret . ' moved');
	}

	$givex = array('ecommerce_givex_primary_hostname' => 'a.givex.com', 'ecommerce_givex_secondary_hostname' => '', 'ecommerce_givex_user_id' => 'U', 'ecommerce_givex_password' => 'P');
	pg_assert_true(pg_payment_gateway_connected($definitions['givex'], $givex), 'the secondary hostname is optional');
	pg_assert_false(pg_payment_gateway_connected($definitions['givex'], array_merge($givex, array('ecommerce_givex_user_id' => ''))), 'every other key is needed');

	$kept = pg_payment_method_keys_decide('givex', $definitions['givex'], array('ecommerce_givex_primary_hostname' => 'a.givex.com', 'ecommerce_givex_user_id' => 'U', 'ecommerce_givex_password' => ''), $givex, '');
	pg_assert_same(array(), $kept['values'], 'same hostnames, empty box: the password stays');

	$moved = pg_payment_method_keys_decide('givex', $definitions['givex'], array('ecommerce_givex_primary_hostname' => 'b.givex.com', 'ecommerce_givex_user_id' => 'U', 'ecommerce_givex_password' => ''), $givex, '');
	pg_assert_true(isset($moved['marks']['ecommerce_givex_password']), 'a new hostname asks for the password again');
	pg_assert_same(array(), $moved['values'], 'and writes nothing');

	$typed = pg_payment_method_keys_decide('givex', $definitions['givex'], array('ecommerce_givex_primary_hostname' => 'b.givex.com', 'ecommerce_givex_user_id' => 'U', 'ecommerce_givex_password' => 'P2'), $givex, '');
	pg_assert_same(array('ecommerce_givex_primary_hostname' => 'b.givex.com', 'ecommerce_givex_password' => 'P2'), $typed['values'], 'with the password typed again');

	$cleared = pg_payment_method_keys_decide('givex', $definitions['givex'], array(), $givex, '');
	pg_assert_same('', $cleared['values']['ecommerce_givex_password'], 'no hostname left: the password goes too');

	$express = pg_payment_method_keys_decide('paypal_express', $definitions['paypal_express'], array('ecommerce_paypal_express_checkout_api_username' => 'U', 'ecommerce_paypal_express_checkout_api_password' => 'P', 'ecommerce_paypal_express_checkout_api_signature' => 'S'), array(), str_repeat('k', 32));
	pg_assert_same('P', pg_gateway_secret_open($express['values']['ecommerce_paypal_express_checkout_api_password'], str_repeat('k', 32)), 'the API password sealed as before');
	pg_assert_same('S', $express['values']['ecommerce_paypal_express_checkout_api_signature'], 'the signature plain as before');

	pg_assert_true(pg_payment_method_choice_refusal('givex', true, false, array()) !== '', 'switched on without keys is refused');
	pg_assert_same('', pg_payment_method_choice_refusal('givex', true, true, array()), 'a switch already on saves');
	pg_assert_same('', pg_payment_method_choice_refusal('givex', true, false, $givex), 'with its keys it may be switched on');

	$block = pg_payment_method_connection_html('paypal_express', array());
	pg_assert_contains('connections.php?service=paypal', $block, 'Express Checkout\'s block leads to PayPal\'s page');
}

function test_connections_payment_services_keys_left_the_cards()
{
	$card = connections_payment_services_test_source('includes/settings/commerce.php');
	$save = connections_payment_services_test_source('includes/settings/commerce.save.php');
	$prep = connections_payment_services_test_source('includes/settings/prep.php');

	foreach (pg_payment_method_connection_columns() as $column) {
		pg_assert_false(strpos($card, 'name="' . $column . '"') !== false, $column . ' is not drawn on the card');
		pg_assert_false(strpos($save, $column . ' = ') !== false, $column . ' is not written by the card');
		pg_assert_false(strpos($prep, '$' . $column . ' = ') !== false, $column . ' is not read for the card');
	}

	foreach (array('ecommerce_givex', 'ecommerce_paypal_express_checkout', 'ecommerce_paypal_express_checkout_transaction_type', 'ecommerce_paypal_express_checkout_mode') as $kept) {
		// Drawn as markup, or named to a pg_commerce_settings_*() helper.
		pg_assert_true((strpos($card, 'name="' . $kept . '"') !== false) || (strpos($card, "'" . $kept . "'") !== false), $kept . ' stays on the card');
		pg_assert_contains($kept . " = '", $save, $kept . ' is still written by the card');
	}

	pg_assert_contains("pg_payment_method_connection_html('givex', ", $card);
	pg_assert_contains("pg_payment_method_connection_html('paypal_express', ", $card);
	pg_assert_contains('pg_payment_method_choice_refusal(', $save);
	pg_assert_contains('pg_payment_driver_choice_refusal(', connections_payment_services_test_source('includes/fn/payments.php'), 'the drivers\' switch is refused the same way');

	$screen = connections_payment_services_test_source('connections.php');

	foreach (array("array('driver_save', 'driver_remove')" => '$driver_definition', "array('method_save', 'method_remove')" => '$method_definition') as $actions => $variable) {
		$at = strpos($screen, 'if (in_array($action, ' . $actions . ', true)) {');
		pg_assert_true($at !== false, $actions . ' are there');
		pg_assert_contains('if ((' . $variable . ' !== null) && connections_commerce_may_manage($user)) {', substr($screen, $at, 1200), $actions . ' ask the gate first');
	}
}
