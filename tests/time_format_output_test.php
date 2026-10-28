<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for times printed on the site's clock (Site Settings: 12 or 24
 * hours): the form value formatter (pg_form_output_time() and
 * prepare_form_data_for_output() for 'time' and 'date and time') and the
 * time format help text (pg_time_format_help()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_time_format_output_time_follows_the_clock()
{
	pg_assert_same('17:05', pg_form_output_time('17:05:00', 'twenty_four_hours'), 'afternoon on the 24-hour clock');
	pg_assert_same('5:05 PM', pg_form_output_time('17:05:00', 'twelve_hours'), 'afternoon on the 12-hour clock');
	pg_assert_same('00:30', pg_form_output_time('00:30:00', 'twenty_four_hours'), 'just after midnight on the 24-hour clock');
	pg_assert_same('12:30 AM', pg_form_output_time('00:30:00', 'twelve_hours'), 'just after midnight on the 12-hour clock');
	pg_assert_same('12:00 PM', pg_form_output_time('12:00', 'twelve_hours'), 'noon without seconds');
	pg_assert_same('09:07', pg_form_output_time('9:7', 'twenty_four_hours'), 'unpadded parts');
}

function test_time_format_output_form_values_use_the_site_clock()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}

	$twenty_four = (pg_time_format_code() === 'H:i');
	$date = (DATE_FORMAT == 'month_day') ? '10/3/2026' : '3/10/2026';

	pg_assert_same($twenty_four ? '17:05' : '5:05 PM', prepare_form_data_for_output('17:05:00', 'time', false), 'a time field');
	pg_assert_same($date . ' ' . ($twenty_four ? '00:30' : '12:30 AM'), prepare_form_data_for_output('2026-10-03 00:30:00', 'date and time', false), 'a date and time field');
	pg_assert_same(date('j F', strtotime('2026-10-03 17:05:00')), prepare_form_data_for_output('2026-10-03 17:05:00', 'date and time', false, 'j F'), 'an explicit format is kept');
}

function test_time_format_output_printed_time_reads_back()
{
	// What the formatter prints on either clock is accepted when the same
	// text is posted again.
	foreach (array('twenty_four_hours', 'twelve_hours') as $clock) {
		$printed = pg_form_output_time('00:30:00', $clock);

		pg_assert_true(validate_time($printed), $clock . ' time validates');
	}

	pg_assert_same(array(0, 30, 0), pg_time_24_hour_parts('00:30'), 'a 24-hour time reads as hour 0');
}

function test_time_format_help_follows_the_clock()
{
	pg_assert_same(lang('HH:mm'), pg_time_format_help('twenty_four_hours'), '24-hour help');
	pg_assert_same(lang('h:mm AM/PM'), pg_time_format_help('twelve_hours'), '12-hour help');
}

function test_time_format_output_screens_have_no_fixed_twelve_hour_text()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	$commerce = file_get_contents($root . 'includes/settings/commerce.php');
	pg_assert_false(strpos($commerce, '(h:mm AM/PM)') !== false, 'commerce settings print no fixed 12-hour help');
	pg_assert_false(strpos($commerce, "date('g:i A')") !== false, 'commerce settings print the site time on the site clock');

	pg_assert_false(strpos(file_get_contents($root . 'includes/forms/shipping_method_form.php'), 'date("g:i A")') !== false, 'shipping method form prints the site time on the site clock');

	foreach (array('includes/forms/email_campaign_profile_form.php', 'includes/forms/submitted_form_form.php', 'includes/fn/content.php', 'get_form_item_view.php', 'get_form_list_view.php', 'generate_layout_content.php') as $file) {
		pg_assert_contains('TIME_FORMAT', file_get_contents($root . $file), $file . ' picks its time help by the clock');
	}
}
