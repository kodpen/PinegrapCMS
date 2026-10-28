<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping method form shared by the create and the edit
 * screens (includes/forms/shipping_method_form.php): the defaults of a new
 * method, the stored record read back into the form, the amounts, variable
 * base rates and excluded dates a post is saved with, the zone picker, and
 * that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

if (!defined('BASE_CURRENCY_SYMBOL')) {
	define('BASE_CURRENCY_SYMBOL', '$');
}

// The site's date order: day/month unless a test file before this one
// defined the month-first order.
function shipping_method_form_test_date_format()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	return DATE_FORMAT;
}

// A date typed in the site's order.
function shipping_method_form_test_date($day, $month, $year)
{
	$order = (shipping_method_form_test_date_format() == 'month_day') ? $month . '/' . $day : $day . '/' . $month;

	return $order . '/' . $year;
}

function test_shipping_method_form_new_values()
{
	$today = (shipping_method_form_test_date_format() == 'month_day') ? date('n/j/Y') : date('j/n/Y');

	$values = pg_shipping_method_form_new_values();

	pg_assert_true($values['status'] && $values['street_address'] && $values['po_box'], 'enabled, street address and PO box on');
	pg_assert_false($values['protected'] || $values['realtime_rate'] || $values['variable_base_rate'] || $values['adjust_transit'], 'the other switches off');
	pg_assert_same(null, $values['service'], 'the service picker keeps its default');

	foreach (array('mon', 'tue', 'wed', 'thu', 'fri') as $day) {
		pg_assert_true($values['handle_' . $day] && $values['ship_' . $day], $day . ' handled and shipped');
	}

	pg_assert_false($values['handle_sat'] || $values['handle_sun'] || $values['ship_sat'] || $values['ship_sun'], 'not on the weekend');

	foreach (pg_shipping_method_form_days() as $long) {
		pg_assert_true($values['available_on_' . $long], 'available on ' . $long);
		pg_assert_same('', $values['available_on_' . $long . '_cutoff_time'], $long . ' has no cut-off');
	}

	// Month-first text read day-first was day 10 of month 27, or a swapped
	// date on days 1-12.
	pg_assert_same($today . ' 12:00 AM', $values['start_time'], 'opens today, in the site\'s date order');
	pg_assert_contains('2099 11:59 PM', $values['end_time'], 'closes at the end of 2099');
	pg_assert_same('2026-01-01 00:00', pg_shipping_method_form_date_time_column(shipping_method_form_test_date(1, 1, 2026) . ' 12:00 AM'), 'the default is read back in the same order');
	pg_assert_same('2099-12-31 23:59', pg_shipping_method_form_date_time_column($values['end_time']), 'the end default is read back');
}

function test_shipping_method_form_date_time_column()
{
	$date = 'shipping_method_form_test_date';
	$swapped = (shipping_method_form_test_date_format() == 'month_day') ? '27/10/2026' : '10/27/2026';

	pg_assert_same('2026-10-27 00:00', pg_shipping_method_form_date_time_column($date(27, 10, 2026) . ' 12:00 AM'), 'midnight');
	pg_assert_same('2026-03-05 17:00', pg_shipping_method_form_date_time_column($date(5, 3, 2026) . ' 5:00 PM'), 'day 5 of March in the site\'s order');
	pg_assert_same('2026-10-27 17:00', pg_shipping_method_form_date_time_column($date(27, 10, 2026) . ' 17:00'), 'a 24-hour time');
	pg_assert_same('2026-10-27 09:05', pg_shipping_method_form_date_time_column(' ' . $date(27, 10, 2026) . '  9:05 AM '), 'white space around and between');
	pg_assert_same('2026-10-27 09:05', pg_shipping_method_form_date_time_column(str_replace('/', '-', $date(27, 10, 2026)) . ' 9:05 AM'), 'dashes as the date validator allows');

	pg_assert_same(null, pg_shipping_method_form_date_time_column($swapped . ' 12:00 AM'), 'the other order: day 10 of month 27 is no date');
	pg_assert_same(null, pg_shipping_method_form_date_time_column($date(30, 2, 2026) . ' 5:00 PM'), 'an impossible day');
	pg_assert_same(null, pg_shipping_method_form_date_time_column($date(27, 10, 2026)), 'no time');
	pg_assert_same(null, pg_shipping_method_form_date_time_column($date(27, 10, 2026) . ' 25:00'), 'no such hour');
	pg_assert_same(null, pg_shipping_method_form_date_time_column($date(27, 10, 2026) . ' 13:00 PM'), 'no such 12-hour time');
	pg_assert_same(null, pg_shipping_method_form_date_time_column('2026-10-27 17:00'), 'an ISO date is not in the site\'s order');
	pg_assert_same(null, pg_shipping_method_form_date_time_column('27/10/26 5:00 PM'), 'a two-digit year');
	pg_assert_same(null, pg_shipping_method_form_date_time_column(''), 'blank');
	pg_assert_same(null, pg_shipping_method_form_date_time_column(null), 'not posted');
	pg_assert_same(null, pg_shipping_method_form_date_time_column(array('27/10/2026 12:00 AM')), 'an array is no date');
}

function test_shipping_method_form_date_time_value()
{
	// The time is on the site's clock (pg_form_output_time()).
	$shown = ((shipping_method_form_test_date_format() == 'month_day') ? '10/27/2026 ' : '27/10/2026 ') . pg_form_output_time('17:00');

	pg_assert_same($shown, pg_shipping_method_form_date_time_value('2026-10-27 17:00:00'), 'a stored time as the form shows it');
	pg_assert_same('', pg_shipping_method_form_date_time_value('0000-00-00 00:00:00'), 'a zero date is shown empty, not as 0/0/0000 12:00 AM');
	pg_assert_same('', pg_shipping_method_form_date_time_value(''), 'blank');
	pg_assert_same('', pg_shipping_method_form_date_time_value(null), 'null');
}

function test_shipping_method_form_errors()
{
	$valid = array(
		'start_time' => shipping_method_form_test_date(27, 10, 2026) . ' 12:00 AM',
		'end_time' => shipping_method_form_test_date(31, 12, 2099) . ' 11:59 PM',
	);

	pg_assert_same(array(), pg_shipping_method_form_errors($valid), 'both times in the site\'s order');

	$swapped = (shipping_method_form_test_date_format() == 'month_day') ? '27/10/2026' : '10/27/2026';
	$errors = pg_shipping_method_form_errors(array('start_time' => $swapped . ' 12:00 AM'));

	pg_assert_same(array('start_time', 'end_time'), array_keys($errors), 'the swapped start and the missing end are named');
	pg_assert_contains(lang('Start Time'), $errors['start_time'], 'the start message names its field');
	pg_assert_contains(lang('End Time'), $errors['end_time'], 'the end message names its field');
}

function test_shipping_method_form_time_help()
{
	pg_assert_same(lang('h:mm AM/PM'), pg_shipping_method_form_time_help('twelve_hours'), '12-hour site');
	pg_assert_same(lang('HH:mm'), pg_shipping_method_form_time_help('twenty_four_hours'), '24-hour site');
}

// The cards reach the database for the money mask, so their markup is read
// from the source.
function test_shipping_method_form_cards_times()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

	pg_assert_contains('name="start_time" id="start_time" class="form-control" autocomplete="off" data-pg-datetime', $source, 'the start time has no maxlength: the value is 19 characters');
	pg_assert_contains('name="end_time" id="end_time" class="form-control" autocomplete="off" data-pg-datetime', $source, 'the end time has no maxlength');
	pg_assert_false(strpos($source, '>h:mm AM/PM<') !== false, 'no hour format written into a time field\'s help');
	pg_assert_contains('pg_shipping_method_form_time_help(TIME_FORMAT)', $source, 'the help follows the time format setting');
	pg_assert_same(2, substr_count($source, '<div class="form-text text-end">\' . $time_help . \'</div>'), 'under the cut-off times and the end of day');
}

function test_shipping_method_amount_cents()
{
	pg_assert_same(0, pg_shipping_method_amount_cents(''), 'blank');
	pg_assert_same(0, pg_shipping_method_amount_cents('0'), 'zero');
	pg_assert_same(1250, pg_shipping_method_amount_cents('12.50'), 'decimal point');
	pg_assert_same(1, pg_shipping_method_amount_cents('0.005'), 'rounded, not truncated');
	pg_assert_same(0, pg_shipping_method_amount_cents(array('1')), 'an array is no amount');
}

function test_shipping_method_variable_base_rates()
{
	$off = pg_shipping_method_variable_base_rates(array('base_rate_2' => '5', 'base_rate_2_subtotal' => '50'));
	pg_assert_same(0, $off['variable_base_rate'], 'switch off: nothing saved');
	pg_assert_same(0, $off['base_rate_2_subtotal'], 'switch off: pairs are 0');

	$rates = pg_shipping_method_variable_base_rates(array(
		'variable_base_rate' => '1',
		'base_rate_2' => '3',
		'base_rate_2_subtotal' => '100',
		'base_rate_3' => '',
		'base_rate_3_subtotal' => '',
		'base_rate_4' => '5',
		'base_rate_4_subtotal' => '50',
	));

	pg_assert_same(
		array(
			'variable_base_rate' => 1,
			'base_rate_2' => 500,
			'base_rate_2_subtotal' => 5000,
			'base_rate_3' => 300,
			'base_rate_3_subtotal' => 10000,
			'base_rate_4' => 0,
			'base_rate_4_subtotal' => 0,
		),
		$rates,
		'valid pairs ordered by subtotal, from base_rate_2 on');

	$none = pg_shipping_method_variable_base_rates(array('variable_base_rate' => '1', 'base_rate_2' => '3', 'base_rate_2_subtotal' => '0'));
	pg_assert_same(0, $none['variable_base_rate'], 'no pair with a subtotal: off');
}

function test_shipping_method_excluded_dates()
{
	pg_assert_same(array(), pg_shipping_method_excluded_dates(''), 'empty box');
	pg_assert_same(
		array('2026-01-01', '2026-12-25'),
		pg_shipping_method_excluded_dates('2026-12-25, 2026-01-01,2026-12-25,2026-02-30,nonsense'),
		'valid dates only, once each, sorted');
}

function test_shipping_method_zone_options()
{
	$zones = array(
		array('id' => '1', 'name' => 'Europe'),
		array('id' => '2', 'name' => 'Turkey & <Cyprus>'),
		array('id' => '3', 'name' => 'World'),
	);

	pg_assert_same(
		'<option value="1">Europe</option><option value="2">Turkey &amp; &lt;Cyprus&gt;</option><option value="3">World</option>',
		pg_shipping_method_zone_options($zones, array()),
		'a new method: every zone, none selected');

	pg_assert_same(
		'<option selected="selected" value="3">World</option><option value="1">Europe</option><option value="2">Turkey &amp; &lt;Cyprus&gt;</option>',
		pg_shipping_method_zone_options($zones, array(3)),
		'allowed zones first and selected');
}

// The columns and values the screens wrote before the form was shared.
function test_shipping_method_form_columns()
{
	$times = array(
		'start_time' => shipping_method_form_test_date(27, 10, 2026) . ' 12:00 AM',
		'end_time' => shipping_method_form_test_date(31, 12, 2099) . ' 11:59 PM',
	);

	$columns = pg_shipping_method_form_columns($times + array(
		'name' => 'Courier',
		'service' => 'fedex_ground',
		'realtime_rate' => '1',
		'status' => '1',
		'base_rate' => '9.99',
		'item_rate' => '1',
		'item_rate_first_item_excluded' => '1',
		'handle_mon' => '1',
		'available_on_sunday' => '1',
		'protected' => array('1'),
	));

	pg_assert_same(
		array('name', 'description', 'code', 'carrier_title', 'carrier_vkn', 'status', 'start_time', 'end_time', 'service', 'realtime_rate',
			'base_rate', 'variable_base_rate', 'base_rate_2', 'base_rate_2_subtotal', 'base_rate_3', 'base_rate_3_subtotal', 'base_rate_4', 'base_rate_4_subtotal',
			'primary_weight_rate', 'primary_weight_rate_first_item_excluded', 'secondary_weight_rate', 'secondary_weight_rate_first_item_excluded', 'item_rate', 'item_rate_first_item_excluded',
			'base_transit_days', 'adjust_transit', 'street_address', 'po_box', 'handle_days',
			'handle_mon', 'handle_tue', 'handle_wed', 'handle_thu', 'handle_fri', 'handle_sat', 'handle_sun',
			'ship_mon', 'ship_tue', 'ship_wed', 'ship_thu', 'ship_fri', 'ship_sat', 'ship_sun',
			'end_of_day', 'transit_on_sunday', 'transit_on_saturday',
			'available_on_monday', 'available_on_monday_cutoff_time', 'available_on_tuesday', 'available_on_tuesday_cutoff_time',
			'available_on_wednesday', 'available_on_wednesday_cutoff_time', 'available_on_thursday', 'available_on_thursday_cutoff_time',
			'available_on_friday', 'available_on_friday_cutoff_time', 'available_on_saturday', 'available_on_saturday_cutoff_time',
			'available_on_sunday', 'available_on_sunday_cutoff_time', 'protected'),
		array_keys($columns),
		'every column the screens save');

	pg_assert_same('enabled', $columns['status'], 'ticked status');
	pg_assert_same('0', $columns['realtime_rate'], 'FedEx has no real-time rate');
	pg_assert_same('999', $columns['base_rate'], 'base rate in minor units');
	pg_assert_same('100', $columns['item_rate'], 'item rate in minor units');
	pg_assert_same('0', $columns['primary_weight_rate'], 'blank rate is 0');
	pg_assert_same('1', $columns['item_rate_first_item_excluded'], 'ticked box');
	pg_assert_same('', $columns['handle_tue'], 'unticked box is not posted');
	pg_assert_same('', $columns['protected'], 'an array is not saved as "Array"');
	pg_assert_same('', $columns['end_of_day'], 'blank time');
	pg_assert_same('2026-10-27 00:00', $columns['start_time'], 'the start time in storage format');
	pg_assert_same('2099-12-31 23:59', $columns['end_time'], 'the end time in storage format');

	foreach ($columns as $column => $value) {
		pg_assert_true(is_string($value), $column . ' is bound as a string, as before');
	}

	$disabled = pg_shipping_method_form_columns($times + array('service' => 'ups_ground', 'realtime_rate' => '1'));
	pg_assert_same('disabled', $disabled['status'], 'unticked status');
	pg_assert_same('1', $disabled['realtime_rate'], 'a service with real-time rates keeps the box');
}

// A post whose start or end time is not a date and time in the site's order
// ends on the error screen before any column is read: a strict SQL mode
// refused the value as a failed query, a lenient one stored 0000-00-00 or,
// with the day and month swapped, a date in the future.
function test_shipping_method_form_columns_refuse_invalid_time()
{
	$swapped = (shipping_method_form_test_date_format() == 'month_day') ? '27/10/2026' : '10/27/2026';
	$throws = pg_error_throws();
	pg_error_throws(true);
	$message = '';

	try {
		pg_shipping_method_form_columns(array(
			'name' => 'Courier',
			'start_time' => $swapped . ' 12:00 AM',
			'end_time' => shipping_method_form_test_date(31, 12, 2099) . ' 11:59 PM',
		));
	} catch (RuntimeException $exception) {
		$message = $exception->getMessage();
	} finally {
		pg_error_throws($throws);
	}

	pg_assert_contains(lang('Start Time'), $message, 'the error screen names the start time');
	pg_assert_false(strpos($message, lang('End Time')) !== false, 'and not the valid end time');
}

function test_shipping_method_screens_use_shared_form()
{
	foreach (array('add_shipping_method.php', 'edit_shipping_method.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/shipping_method_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_shipping_method_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_shipping_method_form_columns($_POST)', $source, $file . ' saves the shared columns');
		pg_assert_contains('pg_shipping_method_save_links(', $source, $file . ' writes zones and excluded dates');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
	}
}
