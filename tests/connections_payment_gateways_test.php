<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card gateways on Connections: each gateway of the Payment
 * Gateway list keeps its keys on its own page under the gate of Site
 * Settings › Ecommerce; the Payment Methods card keeps the choice, Mode,
 * Transaction Type and the shop options, shows each gateway's connection
 * and refuses a newly chosen gateway whose keys are missing; the stored
 * columns and values do not change.
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

function connections_payment_gateways_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_gateways_cover_the_legacy_list()
{
	$gateways = array();

	foreach (pg_payment_gateway_connections() as $id => $definition) {
		$gateways[] = $definition['gateway'];
		pg_assert_true(isset(pg_connections_registry()[$id]), $id . ' is a registry service');
		pg_assert_same('connections.php', pg_connections_registry()[$id]['place'], $id . ' is set on its page');
		pg_assert_same('#settings/commerce/pgset-payments', pg_connections_registry()[$id]['found_at'] ?? null, $id . ' is still found at the card');
		pg_assert_same($id, pg_payment_gateway_connection_for($definition['gateway']), $id . ' is found by its gateway');
	}

	sort($gateways);
	$legacy = pg_payment_legacy_gateways();
	sort($legacy);
	pg_assert_same($legacy, $gateways, 'one page per gateway of the list');
	pg_assert_same(array_keys(pg_payment_gateway_connections()), pg_connections_screen_gateway_pages(), 'the screen knows the same pages');
	pg_assert_same('', pg_payment_gateway_connection_for('Stripe PaymentIntents'), 'a driver keeps its own fields');

	foreach (array_keys(pg_gateway_secret_fields()) as $column) {
		if ($column !== 'ecommerce_paypal_express_checkout_api_password') {
			pg_assert_true(in_array($column, pg_payment_gateway_connection_columns(), true), $column . ' moved with its gateway');
		}
	}
}

function test_connections_gateway_keys_decide()
{
	$definitions = pg_payment_gateway_connections();
	$stored = array('ecommerce_sage_merchant_id' => 'M1', 'ecommerce_sage_merchant_key' => 'K1');

	$kept = pg_payment_gateway_keys_decide($definitions['sage'], array('ecommerce_sage_merchant_id' => ' M1 ', 'ecommerce_sage_merchant_key' => ''), $stored, '');
	pg_assert_same(array(), $kept['values'], 'an empty secret keeps the stored one, nothing changed');

	$masked = pg_payment_gateway_keys_decide($definitions['sage'], array('ecommerce_sage_merchant_id' => 'M1', 'ecommerce_sage_merchant_key' => pg_gateway_secret_mask()), $stored, '');
	pg_assert_same(array(), $masked['values'], 'the mask keeps it too');

	$changed = pg_payment_gateway_keys_decide($definitions['sage'], array('ecommerce_sage_merchant_id' => 'M2', 'ecommerce_sage_merchant_key' => 'K2'), $stored, '');
	pg_assert_same(array('ecommerce_sage_merchant_id' => 'M2', 'ecommerce_sage_merchant_key' => 'K2'), $changed['values'], 'typed values are written');

	$long = pg_payment_gateway_keys_decide($definitions['sage'], array('ecommerce_sage_merchant_id' => str_repeat('x', 101), 'ecommerce_sage_merchant_key' => 'K2'), $stored, '');
	pg_assert_true(isset($long['marks']['ecommerce_sage_merchant_id']), 'longer than its column is marked');
	pg_assert_same(array(), $long['values'], 'and nothing is written');

	$sealed = pg_payment_gateway_keys_decide($definitions['authorize_net'], array('ecommerce_authorizenet_api_login_id' => 'L', 'ecommerce_authorizenet_transaction_key' => 'T'), array(), str_repeat('k', 32));
	pg_assert_same('L', $sealed['values']['ecommerce_authorizenet_api_login_id'], 'the login as typed');
	pg_assert_same('T', pg_gateway_secret_open($sealed['values']['ecommerce_authorizenet_transaction_key'], str_repeat('k', 32)), 'the key sealed with the site\'s key');

	$too_long = pg_payment_gateway_keys_decide($definitions['authorize_net'], array('ecommerce_authorizenet_api_login_id' => 'L', 'ecommerce_authorizenet_transaction_key' => str_repeat('t', 100)), array(), str_repeat('k', 32));
	pg_assert_true(isset($too_long['marks']['ecommerce_authorizenet_transaction_key']), 'a secret that would not fit sealed is marked');
}

function test_connections_gateway_connected_and_choice()
{
	$definitions = pg_payment_gateway_connections();
	$full = array('ecommerce_stripe_api_key' => 'sk_live_x');

	pg_assert_true(pg_payment_gateway_connected($definitions['stripe_classic'], $full), 'every key there');
	pg_assert_false(pg_payment_gateway_connected($definitions['stripe_classic'], array('ecommerce_stripe_api_key' => ' ')), 'a blank key is missing');
	pg_assert_true(pg_payment_gateway_stored($definitions['payflow_pro'], array('ecommerce_paypal_payflow_pro_user' => 'u')), 'one key stored offers Disconnect');
	pg_assert_false(pg_payment_gateway_connected($definitions['payflow_pro'], array('ecommerce_paypal_payflow_pro_user' => 'u')), 'but is not connected');

	pg_assert_same('', pg_payment_gateway_choice_refusal('Stripe', 'Stripe', true, array()), 'an unchanged choice always saves');
	pg_assert_same('', pg_payment_gateway_choice_refusal('Stripe', 'Sage', false, array()), 'nor with cards switched off');
	pg_assert_same('', pg_payment_gateway_choice_refusal('Stripe', 'Sage', true, $full), 'a connected gateway may be chosen');
	pg_assert_same('', pg_payment_gateway_choice_refusal('Stripe PaymentIntents', 'Sage', true, array()), 'a driver is not judged here');
	pg_assert_true(pg_payment_gateway_choice_refusal('Stripe', 'Sage', true, array()) !== '', 'a new choice without keys is refused');
}

function test_connections_gateway_pages_keep_the_category_gate()
{
	foreach (pg_connections_screen_gateway_pages() as $id) {
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => true)), $id . '\'s keys on its page');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => false)), $id . ': not without the category\'s right');
	}

	$screen = connections_payment_gateways_test_source('connections.php');
	$save = strpos($screen, "if (in_array(\$action, array('gateway_save', 'gateway_remove'), true)) {");
	pg_assert_true($save !== false, 'the save action is there');
	pg_assert_contains('if (($gateway_definition !== null) && connections_commerce_may_manage($user)) {', substr($screen, $save, 900), 'the save asks the gate first');
	pg_assert_contains("return pg_settings_may_open('commerce', \$user);", $screen, 'the gate is Ecommerce\'s');
}

function test_connections_gateway_keys_left_the_card()
{
	$card = connections_payment_gateways_test_source('includes/settings/commerce.php');
	$save = connections_payment_gateways_test_source('includes/settings/commerce.save.php');

	foreach (pg_payment_gateway_connection_columns() as $column) {
		pg_assert_false(strpos($card, 'name="' . $column . '"') !== false, $column . ' is not drawn on the card');
		pg_assert_false(strpos($save, $column . ' = ') !== false, $column . ' is not written by the card');
	}

	foreach (array('ecommerce_payment_gateway', 'ecommerce_payment_gateway_mode', 'ecommerce_payment_gateway_transaction_type', 'ecommerce_paypal_payments_pro_gateway_mode', 'ecommerce_iyzipay_installment', 'ecommerce_iyzipay_threeds', 'ecommerce_surcharge_percentage') as $kept) {
		// Drawn as markup, or named to a pg_commerce_settings_*() helper.
		pg_assert_true((strpos($card, 'name="' . $kept . '"') !== false) || (strpos($card, "'" . $kept . "'") !== false), $kept . ' stays on the card');
	}

	pg_assert_contains('pg_payment_gateway_connections_html(', $card, 'the card shows each gateway\'s connection');
	pg_assert_contains('pg_payment_gateway_choice_refusal(', $save, 'and refuses a gateway without keys');

	$html = pg_payment_gateway_connection_html('sage', pg_payment_gateway_connections()['sage'], array(), true);
	pg_assert_contains('connections.php?service=sage', $html, 'the block leads to the page');
	pg_assert_contains('pg-tone-gray', $html, 'not connected is gray');

	pg_assert_same(array('commerce', 'pgset-payments'), pg_settings_legacy_anchors()['ecommerce_stripe_api_key'] ?? null, 'an old link to a key lands on the card');
	pg_assert_same(86, pg_connections_count(), 'the figure: three gateways became services of their own');
}
