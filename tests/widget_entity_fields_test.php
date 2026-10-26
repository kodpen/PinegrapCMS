<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record values a system widget may show from an entity's
 * allow-list (includes/fn/widgets_entity_fields.php): how each kind reads,
 * the recurring-only values, the editor's twin list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wef_product($extra = array())
{
	return $extra + array(
		'keywords' => '  oak, desk ',
		'length' => '120.5000',
		'width' => '0.0000',
		'height' => '75.0000',
		'minimum_quantity' => '2',
		'maximum_quantity' => '0',
		'preparation_time' => '3',
		'extra_shipping_cost' => '1250',
		'tax_rate' => '20.000',
		'recurring' => '0',
		'payment_period' => 'Monthly',
		'number_of_payments' => '12',
		'free_shipping' => '1',
		'gift_card' => '0',
		'selection_type' => 'quantity',
	);
}

function test_widget_entity_fields_product_values()
{
	$v = pg_sw_entity_values('product', _wef_product(), array('currency_symbol' => '$'));

	pg_assert_same('oak, desk', $v['__keywords'], 'text is trimmed');
	pg_assert_same('120.5', $v['__length'], 'trailing zeros dropped');
	pg_assert_same('', $v['__width'], 'zero measure is empty');
	pg_assert_same('75', $v['__height']);
	pg_assert_same('2', $v['__minimum_quantity']);
	pg_assert_same('', $v['__maximum_quantity'], 'zero count is empty');
	pg_assert_same('3', $v['__preparation_days']);
	pg_assert_contains('12', $v['__extra_shipping_cost'], 'minor units read as money');
	pg_assert_contains('$', $v['__extra_shipping_cost']);
	pg_assert_same('20', $v['__vat_rate']);
	pg_assert_same('1', $v['__is_free_shipping']);
	pg_assert_same('', $v['__is_gift_card'], 'no is empty, so "is empty" reads no');
	pg_assert_same('', $v['__is_donation']);
}

function test_widget_entity_fields_recurring_only()
{
	$v = pg_sw_entity_values('product', _wef_product());
	pg_assert_same('', $v['__payment_period'], 'not a recurring product');
	pg_assert_same('', $v['__number_of_payments']);

	$v = pg_sw_entity_values('product', _wef_product(array('recurring' => '1')));
	pg_assert_same(lang('Monthly'), $v['__payment_period'], 'translated words');
	pg_assert_same('12', $v['__number_of_payments']);

	$v = pg_sw_entity_values('product', _wef_product(array('recurring' => '1', 'payment_period' => 'Hourly')));
	pg_assert_same('', $v['__payment_period'], 'a value the column does not hold');
}

function test_widget_entity_fields_nulls_and_missing()
{
	$v = pg_sw_entity_values('product', _wef_product(array('tax_rate' => null, 'selection_type' => 'donation')));
	pg_assert_same('', $v['__vat_rate'], 'NULL rate: the zone decides, nothing shown');
	pg_assert_same('1', $v['__is_donation']);

	$v = pg_sw_entity_values('product', null);
	pg_assert_same(array_keys(pg_sw_entity_fields('product')), array_keys($v), 'no row: every token, empty');
	pg_assert_same('', implode('', $v));

	pg_assert_same(array(), pg_sw_entity_values('nothing', array('x' => 1)));
}

function test_widget_entity_fields_catalog_is_new()
{
	$now = strtotime('2026-10-26 12:00:00');
	pg_assert_true(pg_sw_catalog_is_new('2026-10-20', $now));
	pg_assert_false(pg_sw_catalog_is_new('2026-08-01', $now), 'older than 30 days');
	pg_assert_false(pg_sw_catalog_is_new('2026-11-01', $now), 'in the future');
	pg_assert_false(pg_sw_catalog_is_new('0000-00-00', $now));
	pg_assert_false(pg_sw_catalog_is_new('', $now));
}

function _wef_event($extra = array())
{
	return $extra + array(
		'reservations' => '1',
		'limit_reservations' => '1',
		'number_of_remaining_spots' => 0,
		'last_modified_timestamp' => strtotime('2026-10-20 14:30:00'),
		'product_price' => 4500,
		'recurrence' => '1',
		'recurrence_type' => 'week',
	);
}

function test_widget_entity_fields_event_values()
{
	$v = pg_sw_entity_values('calendar_event', _wef_event(), array('date_format' => 'd.m.Y', 'currency_symbol' => '$'));

	pg_assert_same('0', $v['__event_spots_left'], 'no places left reads 0, not empty');
	pg_assert_same('20.10.2026', $v['__event_updated_date']);
	pg_assert_contains('45', $v['__event_reservation_price']);
	pg_assert_same(lang('Weekly'), $v['__event_recurrence']);
	pg_assert_same('1', $v['__event_reservations_open']);
	pg_assert_same('1', $v['__event_spots_limited']);
	pg_assert_same('1', $v['__event_is_recurring']);
}

function test_widget_entity_fields_event_gates()
{
	$v = pg_sw_entity_values('calendar_event', _wef_event(array('limit_reservations' => '0', 'number_of_remaining_spots' => 12)));
	pg_assert_same('', $v['__event_spots_left'], 'unlimited: no count');
	pg_assert_same('', $v['__event_spots_limited']);

	$v = pg_sw_entity_values('calendar_event', _wef_event(array('reservations' => '0', 'recurrence' => '0')));
	pg_assert_same('', $v['__event_spots_left'], 'no reservations at all');
	pg_assert_same('', $v['__event_reservation_price']);
	pg_assert_same('', $v['__event_reservations_open']);
	pg_assert_same('', $v['__event_spots_limited']);
	pg_assert_same('', $v['__event_recurrence'], 'not recurring: no words');
	pg_assert_same('', $v['__event_is_recurring']);

	$v = pg_sw_entity_values('calendar_event', _wef_event(array('last_modified_timestamp' => 0, 'product_price' => 0)));
	pg_assert_same('', $v['__event_updated_date']);
	pg_assert_same('', $v['__event_reservation_price'], 'free reservation');
}

function test_widget_entity_fields_wanted()
{
	pg_assert_true(pg_sw_entity_wanted('calendar_event', '{"_bindings":{"show_if":"__event_is_recurring"}}'));
	pg_assert_false(pg_sw_entity_wanted('calendar_event', '{"_bindings":{"text":"__event_title"}}'));
}

function test_widget_entity_fields_quiz_score()
{
	$v = pg_sw_entity_values('form_record', array('quiz_score' => '0', 'quiz' => 1));
	pg_assert_same('0', $v['__quiz_score'], 'a zero score shows');

	$v = pg_sw_entity_values('form_record', array('quiz_score' => '85', 'quiz' => 1));
	pg_assert_same('85', $v['__quiz_score']);

	$v = pg_sw_entity_values('form_record', array('quiz_score' => '0', 'quiz' => 0));
	pg_assert_same('', $v['__quiz_score'], 'not a quiz form');
}

// The list view never offers the record's allow-listed values.
function test_widget_entity_fields_quiz_not_in_list_view()
{
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true((bool)preg_match('/var SW_BUILTIN_FIELD_OPTIONS = (.*?);\n/s', $js, $m), 'list view options found');
	pg_assert_false(strpos($m[1], 'SW_FORM_RECORD_ENTITY_TOKENS') !== false || strpos($m[1], '__quiz_score') !== false);
}

// The editor's twin lists name the same tokens in the same order as
// pg_sw_entity_fields(), and mark exactly the yes/no ones as condition-only.
function test_widget_entity_fields_editor_twin()
{
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	foreach (array('product' => 'SW_PRODUCT_ENTITY_TOKENS', 'calendar_event' => 'SW_EVENT_ENTITY_TOKENS', 'form_record' => 'SW_FORM_RECORD_ENTITY_TOKENS') as $entity => $name) {
		pg_assert_true((bool)preg_match('/var ' . $name . ' = \[(.*?)\n    \];/s', $js, $m), $name . ' found');

		preg_match_all("/\['(__[a-z0-9_]+)',[^\n]*?(, 'cond')?\]/", $m[1], $rows, PREG_SET_ORDER);
		$tokens = array();
		$cond = array();
		foreach ($rows as $row) {
			$tokens[] = $row[1];
			if (!empty($row[2])) $cond[] = $row[1];
		}

		$fields = pg_sw_entity_fields($entity);
		pg_assert_same(array_keys($fields), $tokens, $name);

		$yesno = array();
		foreach ($fields as $token => $spec) {
			if ($spec[1] === 'yesno') $yesno[] = $token;
		}
		pg_assert_same($yesno, $cond, $name . ' conditions');
	}
}
