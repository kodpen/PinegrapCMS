<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the vendored vanilla-calendar-pro, the calendar of the one
 * date-and-time control (assets/js/pg_datetime.js): the release file sits
 * unmodified under assets/lib/vanilla-calendar-pro/ with its license, it is
 * the version Bootstrap 6 embeds, and pg_fields_script() prints the control
 * with the library's address before pg_fields.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function vanilla_calendar_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function test_vanilla_calendar_library_is_the_release_file()
{
	$library = vanilla_calendar_test_source('assets/lib/vanilla-calendar-pro/index.js');

	pg_assert_same(0, strpos($library, '/*! name: vanilla-calendar-pro v3.4.0 |'), 'index.js opens with its own banner');
	// An update copies index.js and LICENSE from the npm package and changes
	// this hash and the version above (core/vanilla-calendar-library.md).
	pg_assert_same('3104901e40d02e560d7228262814dc1ceb52ffcab35ac6439ebdc56d444c554d', hash('sha256', $library), 'index.js is the npm release file byte for byte');
	pg_assert_contains('Yury Uvarov', vanilla_calendar_test_source('assets/lib/vanilla-calendar-pro/LICENSE'), 'the license file ships beside it');
	pg_assert_contains('MIT License', vanilla_calendar_test_source('assets/lib/vanilla-calendar-pro/LICENSE'));
}

function test_vanilla_calendar_is_the_version_bootstrap_6_embeds()
{
	// bootstrap.css styles every [data-vc=calendar]; it matches the markup of
	// the version its bundle carries.
	pg_assert_contains('vanilla-calendar-pro v3.4.0', vanilla_calendar_test_source('assets/lib/bootstrap-6/js/bootstrap.bundle.min.js'), 'the Bootstrap 6 bundle embeds 3.4.0 too');
}

function test_vanilla_calendar_printed_before_pg_fields()
{
	$tags = pg_fields_script_tags_for_test();

	pg_assert_contains('/assets/js/pg_datetime.js?v=', $tags, 'the control is printed');
	pg_assert_contains('data-pg-calendar-src="\' . $path . \'/assets/lib/vanilla-calendar-pro/index.js?v=', $tags, 'with the library address it loads once');
	pg_assert_same(true, strpos($tags, 'pg_datetime.js') < strpos($tags, 'pg_fields.js'), 'before pg_fields.js, which draws dates with it');
	pg_assert_same(0, substr_count($tags, '<script src="\' . $path . \'/assets/lib/'), 'the library has no tag of its own: pg_datetime.js adds it once');
}

// pg_fields_script() prints once a request; the source is read instead of
// calling it, so the test does not use up that one call.
function pg_fields_script_tags_for_test()
{
	$source = vanilla_calendar_test_source('includes/fn/core.php');
	$start = strpos($source, 'function pg_fields_script()');

	return $start === false ? '' : (string) substr($source, $start, 2000);
}

function test_vanilla_calendar_settings_follow_the_site_clock()
{
	$twelve = pg_calendar_settings('twelve_hours');
	$twenty_four = pg_calendar_settings('twenty_four_hours');

	pg_assert_same(12, $twelve['timeMode'], 'the 12-hour clock: hour, minute and AM/PM');
	pg_assert_same(24, $twenty_four['timeMode'], 'the 24-hour setting: the time row in 24 hours');
	pg_assert_same(
		array('application', 'navigation', 'arrowNext', 'arrowPrev', 'month', 'months', 'year', 'years', 'week', 'weekNumber', 'dates', 'selectingTime', 'inputHour', 'inputMinute', 'rangeHour', 'rangeMinute', 'btnKeeping'),
		array_keys($twelve['labels']),
		'every screen-reader name of the calendar that reaches the panel is translated'
	);
	pg_assert_same(true, $twelve['done'] !== '', 'the Done button has its word');
}

function test_vanilla_calendar_time_row_stylesheet_printed()
{
	$tags = pg_fields_script_tags_for_test();

	pg_assert_contains('/assets/css/pg_datetime.css?v=', $tags, 'the time row has its stylesheet');
	pg_assert_contains('data-pg-calendar="\' . h(json_encode(pg_calendar_settings()))', $tags, 'the clock and the words on the control\'s tag');
	pg_assert_contains('@layer custom', vanilla_calendar_test_source('assets/css/pg_datetime.css'), 'in the panel layer, above Bootstrap\'s components');
}
