<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for prepare_form_data_for_input() and validate_time() in
 * includes/fn/forms.php: the form list search runs every search term through
 * the first for each date and time field; both read 12-hour and 24-hour time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function forms_test_date_format()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
}

// An ISO date and time, as an <input type="datetime-local"> of a designed
// form or a product form posts it, is valid and stored as it is, never read
// the site's way ('0026-10-2026 17:00').
function test_forms_date_and_time_iso()
{
	forms_test_date_format();

	pg_assert_same('2026-10-26 17:05', prepare_form_data_for_input('2026-10-26T17:05', 'date and time'));
	pg_assert_same('2026-10-26 07:05', prepare_form_data_for_input('2026-10-26 7:05', 'date and time'));
	pg_assert_same('2026-10-26 17:05', prepare_form_data_for_input('2026-10-26T17:05:30', 'date and time'));
	pg_assert_true(validate_date_and_time('2026-10-26T17:05'), 'the posted datetime-local value');
	pg_assert_true(validate_date_and_time('2026-10-26T17:05:30'), 'with seconds');
	pg_assert_true(validate_date_and_time('2026-10-26 17:05'), 'with a space');
	pg_assert_true(validate_date_and_time('26/10/2026 5:05 PM'), 'the site text still');
	pg_assert_false(validate_date_and_time('2026-02-30T17:05'), 'a day the month has not');
	pg_assert_false(validate_date_and_time('2026-10-26T25:05'), 'an hour the clock has not');
	pg_assert_false(validate_date_and_time('2026-10-26'), 'a date alone, without a warning');
}

// An ISO date with a 12-hour time is valid (validate_date_and_time()), so its
// AM/PM is read too, never dropped after the hour.
function test_forms_date_and_time_iso_twelve_hour()
{
	forms_test_date_format();

	pg_assert_true(validate_date_and_time('2026-10-26 5:00 PM'), 'valid');
	pg_assert_same('2026-10-26 17:00', prepare_form_data_for_input('2026-10-26 5:00 PM', 'date and time'));
	pg_assert_same('2026-10-26 00:00', prepare_form_data_for_input('2026-10-26 12:00 AM', 'date and time'));
	pg_assert_same('2026-10-26 12:30', prepare_form_data_for_input('2026-10-26 12:30 pm', 'date and time'));
	pg_assert_same('2026-01-05 09:15', prepare_form_data_for_input('2026-1-5 9:15 AM', 'date and time'));
	pg_assert_same('2026-10-26 17:00', prepare_form_data_for_input('2026-10-26T17:00', 'date and time'), '24-hour unchanged');
	pg_assert_same('2026-10-26 17:00', prepare_form_data_for_input('26/10/2026 5:00 PM', 'date and time'), 'site format unchanged');
}

// The value a native date, date-and-time or time input takes: ISO, from a
// stored value or a default in the site's format; other text stays.
function test_forms_native_date_value()
{
	forms_test_date_format();

	pg_assert_same('2026-10-26', pg_native_date_value('26/10/2026', 'date'), 'a site date');
	pg_assert_same('2026-10-26', pg_native_date_value('2026-10-26', 'date'), 'already ISO');
	pg_assert_same('2026-10-26', pg_native_date_value('2026-10-26 17:05:00', 'date'), 'a stored date and time');
	pg_assert_same('2026-10-26', pg_native_date_value('26/10/2026 5:05 PM', 'date'), 'a site date and time');
	pg_assert_same('2026-10-26T17:05', pg_native_date_value('26/10/2026 5:05 PM', 'datetime-local'), 'a site date and time');
	pg_assert_same('2026-10-26T17:05', pg_native_date_value('2026-10-26 17:05:00', 'datetime-local'), 'a stored date and time');
	pg_assert_same('2026-10-26T17:05', pg_native_date_value('2026-10-26T17:05', 'DATETIME-LOCAL'), 'already ISO');
	pg_assert_same('2026-10-26T00:00', pg_native_date_value('26/10/2026', 'datetime-local'), 'a date alone is midnight');
	pg_assert_same('17:05', pg_native_date_value('5:05 PM', 'time'), 'a 12-hour time');
	pg_assert_same('09:30', pg_native_date_value('9:30', 'time'), 'a 24-hour time');
	pg_assert_same('', pg_native_date_value('  ', 'date'), 'nothing');
	pg_assert_same('soon', pg_native_date_value('soon', 'date'), 'no date stays');
	pg_assert_same('soon later', pg_native_date_value('soon later', 'datetime-local'), 'no date and time stays');
	pg_assert_same('26/10/2026', pg_native_date_value('26/10/2026', 'text'), 'another input type is left');
}

// A search term with no date or time parts is converted without a warning:
// the missing parts count as empty.
function test_forms_input_date_and_time_without_parts()
{
	forms_test_date_format();

	pg_assert_same('0000-00-blog 00:00', prepare_form_data_for_input('blog', 'date and time'));
}

// A date with no time is converted without a warning; the time is midnight.
function test_forms_input_date_and_time_without_time()
{
	forms_test_date_format();

	pg_assert_same('2016-03-03 00:00', prepare_form_data_for_input('03/03/2016', 'date and time'));
}

// A complete date and time is converted to the database format.
function test_forms_input_date_and_time_complete()
{
	forms_test_date_format();

	pg_assert_same('2016-03-03 21:30', prepare_form_data_for_input('03/03/2016 9:30 PM', 'date and time'));
}

// A search term that is not a time is converted without a warning.
function test_forms_input_time_without_parts()
{
	pg_assert_same('00:00', prepare_form_data_for_input('blog', 'time'));
}

// A time with AM/PM is converted to the 24-hour format.
function test_forms_input_time_complete()
{
	pg_assert_same('00:05', prepare_form_data_for_input('12:05 AM', 'time'));
}

// An advanced search field that holds one value counts as not given when its
// value arrives as an array; a plain value is searched for as it is.
function test_forms_list_search_value_single_field()
{
	pg_assert_same('', pg_form_list_search_value(array('x'), false));
	pg_assert_same('', pg_form_list_search_value(array('a' => 'x'), false));
	pg_assert_same('', pg_form_list_search_value(array(array('x')), false));
	pg_assert_same('blog', pg_form_list_search_value('blog', false));
	pg_assert_same(null, pg_form_list_search_value(null, false));
}

// A multi-selection pick list or check box group keeps its selected values.
function test_forms_list_search_value_multiple_field_keeps_values()
{
	pg_assert_same(array('a', 'b'), pg_form_list_search_value(array('a', 'b'), true));
	pg_assert_same('a', pg_form_list_search_value('a', true));
}

// Values that are arrays themselves are dropped from a multiple-value field;
// with none left the field counts as not given.
function test_forms_list_search_value_multiple_field_drops_nested_values()
{
	pg_assert_same(array(0 => 'a'), pg_form_list_search_value(array('a', array('x')), true));
	pg_assert_same('', pg_form_list_search_value(array(array('x')), true));
	pg_assert_same('', pg_form_list_search_value(array('b' => array('c' => 'x')), true));
}

// A 24-hour time, as <input type="time"> posts it, is kept as it is; seconds
// are dropped like they are from a 12-hour time.
function test_forms_input_time_24_hour()
{
	pg_assert_same('17:00', prepare_form_data_for_input('17:00', 'time'));
	pg_assert_same('00:05', prepare_form_data_for_input('00:05', 'time'));
	pg_assert_same('09:30', prepare_form_data_for_input('9:30', 'time'));
	pg_assert_same('23:59', prepare_form_data_for_input('23:59:59', 'time'));
	pg_assert_same('17:00', prepare_form_data_for_input(' 17:00 ', 'time'));
}

// The 12-hour reading is unchanged: noon, afternoon, morning.
function test_forms_input_time_12_hour_unchanged()
{
	pg_assert_same('12:00', prepare_form_data_for_input('12:00 PM', 'time'));
	pg_assert_same('17:00', prepare_form_data_for_input('5:00 PM', 'time'));
	pg_assert_same('09:05', prepare_form_data_for_input('9:05 AM', 'time'));
}

// An hour or minute out of range, or text around the time, is not a 24-hour
// time: the parts count as empty, as for any other search term.
function test_forms_input_time_24_hour_out_of_range()
{
	pg_assert_same('00:00', prepare_form_data_for_input('24:00', 'time'));
	pg_assert_same('00:00', prepare_form_data_for_input('17:60', 'time'));
	pg_assert_same('00:00', prepare_form_data_for_input('at 17:00', 'time'));
	pg_assert_same('00:00', prepare_form_data_for_input('17:0', 'time'));
}

// A date with a 24-hour time is converted to the database format.
function test_forms_input_date_and_time_24_hour()
{
	forms_test_date_format();

	pg_assert_same('2016-03-03 17:00', prepare_form_data_for_input('03/03/2016 17:00', 'date and time'));
	pg_assert_same('2016-03-03 00:15', prepare_form_data_for_input('03/03/2016 00:15:00', 'date and time'));
	pg_assert_same('2016-03-03 21:30', prepare_form_data_for_input('03/03/2016 9:30 PM', 'date and time'));
}

// validate_time() accepts a 24-hour time with or without seconds.
function test_forms_validate_time_24_hour()
{
	pg_assert_true(validate_time('17:00'));
	pg_assert_true(validate_time('00:00'));
	pg_assert_true(validate_time('23:59:59'));
	pg_assert_true(validate_time('9:30'));
}

// validate_time() refuses a 24-hour time out of range or with other text.
function test_forms_validate_time_24_hour_refused()
{
	pg_assert_false(validate_time('24:00'));
	pg_assert_false(validate_time('12:60'));
	pg_assert_false(validate_time('12:30:60'));
	pg_assert_false(validate_time('17:0'));
	pg_assert_false(validate_time('noon 12:00'));
	pg_assert_false(validate_time(''));
	pg_assert_false(validate_time('blog'));
}

// validate_time() reads a 12-hour time exactly as before: hour 1 to 12, an
// hour past 12 refused even with AM/PM, seconds optional.
function test_forms_validate_time_12_hour_unchanged()
{
	pg_assert_true(validate_time('12:00 AM'));
	pg_assert_true(validate_time('5:00 pm'));
	pg_assert_true(validate_time('5:00:59 PM'));
	pg_assert_false(validate_time('0:30 AM'));
	pg_assert_false(validate_time('13:00 PM'));
	pg_assert_false(validate_time('17:00 PM'));
	pg_assert_false(validate_time('5:60 PM'));
}

// A date with a 24-hour time is a valid date and time.
function test_forms_validate_date_and_time_24_hour()
{
	forms_test_date_format();

	pg_assert_true(validate_date_and_time('03/03/2016 17:00'));
	pg_assert_false(validate_date_and_time('03/03/2016 25:00'));
}

// A typed date as ISO: the site's day and month order with any separator
// (the Turkish dotted form included), ISO itself, and '' for no real date
// or a two-digit year.
function test_date_input_iso()
{
	$day_month = !defined('DATE_FORMAT') || (DATE_FORMAT != 'month_day');

	pg_assert_same('2026-12-31', pg_date_input_iso('2026-12-31'), 'ISO');
	pg_assert_same('2026-02-03', pg_date_input_iso('2026-2-3'), 'ISO without zeros');
	pg_assert_same('2026-12-31', pg_date_input_iso($day_month ? '31.12.2026' : '12.31.2026'), 'dots');
	pg_assert_same('2026-12-31', pg_date_input_iso($day_month ? ' 31/12/2026 ' : ' 12/31/2026 '), 'slashes, trimmed');
	pg_assert_same('2026-12-31', pg_date_input_iso($day_month ? '31-12-2026' : '12-31-2026'), 'dashes');

	foreach (array('', 'abc', '32/13/2027', '31/12/27', '30.02.2026', '2026-13-01', '0000-00-00', '31.12.2026x', array('2026-12-31'), null) as $bad) {
		pg_assert_same('', pg_date_input_iso($bad), 'no date: ' . json_encode($bad));
	}
}

// A date in the site's format is valid only when the whole value is one:
// '5/5/20271' was read as 5/5/2027 and stored with the year 20271, and text
// before or after a date passed as well.
function test_forms_validate_date_whole_value()
{
	forms_test_date_format();

	pg_assert_true(validate_date('05/05/2027'), 'a site date');
	pg_assert_true(validate_date(' 5-5-2027 '), 'surrounding white space');
	pg_assert_false(validate_date('5/5/20271'), 'a five-digit year');
	pg_assert_false(validate_date('15/5/2027x'), 'text after the date');
	pg_assert_false(validate_date('x5/5/2027'), 'text before the date');
	pg_assert_false(validate_date('5/5/2027 5:05 PM'), 'a date and time');
	pg_assert_true(validate_date_and_time('5/5/2027 5:05 PM'), 'still a date and time');
	pg_assert_same('2027-05-05', prepare_form_data_for_input(' 5/5/2027 ', 'date'), 'stored without the white space');
}
