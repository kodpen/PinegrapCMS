<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /offers and POST /offers/{id}/duplicate
 * (includes/api/resources/offers_create.php): the route rows, how a body is
 * read into the offer editor's own payload, which values are refused before
 * the editor's checks run, and that the handlers write only through the
 * editor's save.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_offers_create_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_api_offers_create_routes()
{
	$route = api_offers_create_test_route('offers.create');
	pg_assert_true(is_array($route), 'create declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/offers', $route['path']);
	pg_assert_same('offers:write', $route['scope']);
	pg_assert_same(201, $route['status']);
	pg_assert_true(!empty($route['dry_run']));

	$route = api_offers_create_test_route('offers.duplicate');
	pg_assert_true(is_array($route), 'duplicate declared');
	pg_assert_same('/offers/{id}/duplicate', $route['path']);
	pg_assert_same('offers:write', $route['scope']);
	pg_assert_same(201, $route['status']);
}

require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/offers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/offers_create.php');

function api_offers_create_test_payload($params, $base = null)
{
	return api_offer_payload($params, ($base === null) ? _pg_offer_new_state() : $base, '2026-11-03');
}

function test_api_offer_payload_defaults_follow_the_editor()
{
	list($payload, $error) = api_offers_create_test_payload(array('code' => ' WINTER '));
	pg_assert_same('', $error);
	pg_assert_same('WINTER', $payload['offer']['code']);
	pg_assert_same(0, $payload['offer']['id']);
	pg_assert_same(1, $payload['offer']['enabled']);
	pg_assert_same(1, $payload['offer']['only_apply_best_offer']);
	pg_assert_same('open', $payload['offer']['period']);
	pg_assert_same(array(), $payload['conditions']);
	pg_assert_same(array(), $payload['actions']);
}

function test_api_offer_payload_dates()
{
	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'start_date' => '2026-12-01', 'end_date' => null));
	pg_assert_same('range', $payload['offer']['period'], 'an open end that starts later is a range to the open end date');
	pg_assert_same('2026-12-01', $payload['offer']['start_date']);
	pg_assert_same(PG_OFFER_OPEN_END_DATE, $payload['offer']['end_date']);

	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'end_date' => '2026-12-31'));
	pg_assert_same('2026-11-03', $payload['offer']['start_date'], 'no start is today');

	// The base is built from the clock; one already past the given today
	// (after midnight, or in another time zone) still starts on that today.
	$base = _pg_offer_new_state();
	$base['offer']['start_date'] = '2026-11-04';
	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'end_date' => '2026-12-31'), $base);
	pg_assert_same('2026-11-03', $payload['offer']['start_date'], 'an open base never starts after today');
	$base['offer']['start_date'] = '2026-10-01';
	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'end_date' => '2026-12-31'), $base);
	pg_assert_same('2026-10-01', $payload['offer']['start_date'], 'an open base that started earlier keeps its start');
	$base['offer']['period'] = 'range';
	$base['offer']['start_date'] = '2026-11-20';
	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'end_date' => '2026-12-31'), $base);
	pg_assert_same('2026-11-20', $payload['offer']['start_date'], 'a ranged base keeps a later start');

	list(, $error, $field) = api_offers_create_test_payload(array('code' => 'A', 'start_date' => '2026-02-30'));
	pg_assert_same('start_date', $field);
	list(, $error, $field) = api_offers_create_test_payload(array('code' => 'A', 'start_date' => '2026-12-02', 'end_date' => '2026-12-01'));
	pg_assert_same(lang('The end date must not be before the start date.'), $error);
	pg_assert_same('end_date', $field);
}

function test_api_offer_payload_reads_conditions_into_editor_shape()
{
	list($payload, $error) = api_offers_create_test_payload(array('code' => 'A', 'conditions' => array(
		array('type' => 'subtotal', 'amount' => 50000),
		array('type' => 'product_group', 'group_ids' => array(4, '4', 9)),
		array('type' => 'usage_limit', 'total_limit' => 100, 'customer_limit' => null),
		array('type' => 'new_customer', 'mode' => 'registered', 'days' => 14))));
	pg_assert_same('', $error);
	pg_assert_same(array('type' => 'subtotal', 'amount' => 50000), $payload['conditions'][0]);
	pg_assert_same(array('type' => 'product group', 'group_ids' => array(4, 9), 'quantity' => 1), $payload['conditions'][1]);
	pg_assert_same(array('type' => 'usage limit', 'total_limit' => 100), $payload['conditions'][2]);
	pg_assert_same(array('type' => 'new customer', 'days' => 14, 'mode' => 'registered'), $payload['conditions'][3]);
}

function test_api_offer_payload_refuses_what_the_editor_would_bend()
{
	$refused = array(
		array('conditions', array(array('type' => 'subtotal', 'amount' => 12.5))),
		array('conditions', array(array('type' => 'subtotal', 'amount' => '12,50'))),
		array('conditions', array(array('type' => 'product group', 'group_ids' => array(1)))),
		array('conditions', array(array('type' => 'new_customer', 'mode' => 'everyone'))),
		array('conditions', array(array('type' => 'cart_quantity', 'quantity' => 2, 'amount' => 5))),
		array('conditions', array(array('type' => 'products', 'product_ids' => array(0)))),
		array('actions', array(array('type' => 'discount_shipping', 'amount' => 500, 'shipping_method_ids' => array(1)))),
		array('actions', array(array('type' => 'discount_order', 'amount' => 500, 'percentage' => 10))),
		array('actions', array(array('type' => 'discount_order'))),
		array('actions', array(array('type' => 'discount_order', 'amount' => 500, 'tiers' => array(array('min_subtotal' => 100, 'percentage' => 5))))),
		array('actions', array(array('type' => 'discount_product', 'target' => 'all', 'percentage' => 10))),
		array('actions', array(array('type' => 'add_product', 'product_id' => 1.5))),
		array('actions', array('discount_order')));
	foreach ($refused as $case) {
		list($payload, $error, $field) = api_offers_create_test_payload(array('code' => 'A', $case[0] => $case[1]));
		pg_assert_true($error !== '', 'refused: ' . json_encode($case[1]));
		pg_assert_same($case[0], $field);
		pg_assert_same(null, $payload);
	}

	list(, $error) = api_offers_create_test_payload(array('code' => 'A', 'conditions' => array(array('type' => 'subtotal', 'amount' => 1), array('type' => 'subtotal', 'amount' => 'x'))));
	pg_assert_true(strpos($error, '2') !== false, 'the row is numbered from 1');
}

function test_api_offer_payload_reads_actions_into_editor_shape()
{
	list($payload, $error) = api_offers_create_test_payload(array('code' => 'A', 'actions' => array(
		array('type' => 'discount_order', 'tiers' => array(array('min_subtotal' => 10000, 'percentage' => 5), array('min_subtotal' => 25000, 'percentage' => 10))),
		array('type' => 'discount_product', 'target' => 'group', 'group_id' => 3, 'amount' => 1500),
		array('type' => 'add_product', 'product_id' => 7),
		array('type' => 'discount_shipping', 'percentage' => 100, 'shipping_method_ids' => array(2, 5)))));
	pg_assert_same('', $error);
	pg_assert_same(array('id' => 0, 'type' => 'discount order', 'unit' => 'percent', 'value' => 0, 'tiers' => array(array('min' => 10000, 'percent' => 5), array('min' => 25000, 'percent' => 10))), $payload['actions'][0]);
	pg_assert_same(array('id' => 0, 'type' => 'discount product', 'unit' => 'amount', 'value' => 1500, 'target' => 'group', 'group_id' => 3), $payload['actions'][1]);
	pg_assert_same(array('id' => 0, 'type' => 'add product', 'unit' => 'percent', 'value' => 0, 'product_id' => 7), $payload['actions'][2], 'a gift at full price');
	pg_assert_same(array(2, 5), $payload['actions'][3]['shipping_method_ids']);
}

function test_api_offer_payload_upsell()
{
	list($payload, $error) = api_offers_create_test_payload(array('code' => 'A', 'upsell' => array('enabled' => true, 'message' => ' Almost ', 'trigger_subtotal' => 4000)));
	pg_assert_same('', $error);
	pg_assert_same(1, $payload['offer']['upsell']['enabled']);
	pg_assert_same('Almost', $payload['offer']['upsell']['message']);
	pg_assert_same(4000, $payload['offer']['upsell']['trigger_subtotal']);

	list(, , $field) = api_offers_create_test_payload(array('code' => 'A', 'upsell' => array('page_id' => 3)));
	pg_assert_same('upsell', $field);
	list(, , $field) = api_offers_create_test_payload(array('code' => 'A', 'upsell' => array('trigger_subtotal' => 40.5)));
	pg_assert_same('upsell', $field);
}

function test_api_offer_payload_duplicate_keeps_the_source()
{
	$base = _pg_offer_new_state();
	$base['offer']['description'] = 'Ten off';
	$base['offer']['enabled'] = 0;
	$base['conditions'] = array(array('type' => 'subtotal', 'amount' => 100));
	$base['actions'] = array(array('id' => 0, 'type' => 'discount order', 'unit' => 'percent', 'value' => 10));
	list($payload, $error) = api_offers_create_test_payload(array('code' => 'COPY', 'enabled' => true), $base);
	pg_assert_same('', $error);
	pg_assert_same('COPY', $payload['offer']['code']);
	pg_assert_same('Ten off', $payload['offer']['description']);
	pg_assert_same(1, $payload['offer']['enabled']);
	pg_assert_same($base['conditions'], $payload['conditions']);
	pg_assert_same($base['actions'], $payload['actions']);
}

function test_api_offer_editor_error_names_the_body_field()
{
	pg_assert_same(array('x', 'code'), api_offer_editor_error(array('offer.code' => 'x')));
	pg_assert_same(array('x', 'end_date'), api_offer_editor_error(array('offer.dates' => 'x')));
	pg_assert_same(array(api_offer_row_message('actions', 1, 'x'), 'actions'), api_offer_editor_error(array('actions.1.value' => 'x')));
	pg_assert_same(array(api_offer_row_message('conditions', 0, 'x'), 'conditions'), api_offer_editor_error(array('conditions.0' => 'x')));
}

function test_api_offer_editor_payload_passes_the_editor_checks()
{
	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'conditions' => array(array('type' => 'subtotal', 'amount' => 3000)),
		'actions' => array(array('type' => 'discount_order', 'percentage' => 10))));
	$checked = _pg_offer_validate($payload);
	pg_assert_same(array(), $checked['errors']);
	pg_assert_same('discount order', $checked['clean']['actions'][0]['type']);
	pg_assert_same(10, $checked['clean']['actions'][0]['value']);

	list($payload) = api_offers_create_test_payload(array('code' => 'A', 'actions' => array(array('type' => 'discount_order', 'percentage' => 120))));
	$checked = _pg_offer_validate($payload);
	pg_assert_same(lang('A percentage cannot be more than 100.'), $checked['errors']['actions.0.value']);
}

function test_api_offers_create_writes_only_through_the_editor()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/offers_create.php');
	pg_assert_true(strpos($src, 'api_exec(') === false, 'no write of its own');
	pg_assert_true(strpos($src, '_pg_offer_save(') !== false);
	pg_assert_true(strpos($src, 'api_dry_run_stop(') < strpos($src, 'api_ok(api_offer_create_write('), 'the rehearsal stops above the write');
	pg_assert_true(strpos($src, 'api_offer_create_checked($payload);') < strpos($src, 'api_dry_run_stop('), 'every check runs before the stop');
}

function test_offer_writes_announce_events()
{
	$events = api_webhook_events();
	foreach (array('offer.created', 'offer.updated', 'offer.deleted') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' in the catalogue');
	}
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
	pg_assert_true(strpos($src, "pg_announce(\$is_new ? 'offer.created' : 'offer.updated'") !== false, 'the editor save');
	pg_assert_true(strpos($src, "pg_announce('offer.deleted'") !== false, 'the delete');
	pg_assert_true(strpos($src, "pg_announce('offer.updated'") !== false, 'the on/off switch');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/offers.php'), "pg_announce('offer.updated'") !== false, 'the API update');
}
