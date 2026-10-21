<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/page_schedule.php: how a sent time
 * is read in the site's time zone, how a stored time is shown again, and what
 * a save does with a page's publish time and time to come off the site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $check with the PHP default time zone set to $zone, the way init.php
// sets the site's, and puts the previous one back.
function page_schedule_test_in_zone($zone, $check)
{
	$previous = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$check();
	} finally {
		date_default_timezone_set($previous);
	}
}

// Nothing sent, or nothing meant, is no time.
function test_page_schedule_parse_empty_is_none()
{
	foreach (array(null, '', '   ', 0, '0', false) as $value) {
		pg_assert_same(array('ok' => true, 'time' => 0), pg_page_schedule_parse($value));
	}
}

// A datetime-local value is the site's wall clock, not UTC.
function test_page_schedule_parse_local_time_in_site_zone()
{
	page_schedule_test_in_zone('Europe/Istanbul', function () {
		$expected = gmmktime(6, 30, 0, 10, 21, 2026);

		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T09:30'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21 09:30'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T09:30:00'));
	});

	page_schedule_test_in_zone('UTC', function () {
		pg_assert_same(gmmktime(9, 30, 0, 10, 21, 2026), pg_page_schedule_parse('2026-10-21T09:30')['time']);
	});
}

// An explicit offset wins over the site's zone; Z is UTC.
function test_page_schedule_parse_offset_and_z()
{
	page_schedule_test_in_zone('Europe/Istanbul', function () {
		pg_assert_same(gmmktime(9, 30, 0, 10, 21, 2026), pg_page_schedule_parse('2026-10-21T09:30:00Z')['time']);
		pg_assert_same(gmmktime(7, 30, 0, 10, 21, 2026), pg_page_schedule_parse('2026-10-21T09:30:00+02:00')['time']);
		pg_assert_same(gmmktime(7, 30, 0, 10, 21, 2026), pg_page_schedule_parse('2026-10-21T09:30+0200')['time']);
	});
}

// A unix time passes as it is.
function test_page_schedule_parse_unix_time()
{
	pg_assert_same(array('ok' => true, 'time' => 1800000000), pg_page_schedule_parse(1800000000));
	pg_assert_same(array('ok' => true, 'time' => 1800000000), pg_page_schedule_parse('1800000000'));
}

// Anything else is refused rather than read as some other moment.
function test_page_schedule_parse_refuses_what_is_no_time()
{
	foreach (array('2026-02-30T10:00', '2026-10-21T25:00', '2026-13-01T10:00', 'tomorrow', '21.10.2026 09:30',
				   '2026-10-21', array('2026-10-21T09:30'), -5, '2026-10-21T09:30:00+25:00') as $value) {
		pg_assert_false(pg_page_schedule_parse($value)['ok'], 'refused: ' . json_encode($value));
	}
}

// A stored time is shown as the box takes it, and read back to the minute.
function test_page_schedule_format_round_trip()
{
	page_schedule_test_in_zone('Europe/Istanbul', function () {
		pg_assert_same('', pg_page_schedule_format(0));
		pg_assert_same('2026-10-21T09:30', pg_page_schedule_format(gmmktime(6, 30, 0, 10, 21, 2026)));
		pg_assert_same(gmmktime(6, 30, 0, 10, 21, 2026), pg_page_schedule_parse(pg_page_schedule_format(gmmktime(6, 30, 0, 10, 21, 2026)))['time']);
	});
}

// No times: the draft state the save had decided stands.
function test_page_schedule_plan_without_times_keeps_state()
{
	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(0, 0, true, false, 1000));
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(0, 0, false, false, 1000));
}

// A publish time ahead keeps the page a draft, whatever the switch said.
function test_page_schedule_plan_future_publish_is_draft()
{
	pg_assert_same(array('draft' => true, 'publish_at' => 2000, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(2000, 0, false, false, 1000));
	pg_assert_same(array('draft' => true, 'publish_at' => 2000, 'unpublish_at' => 3000, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(2000, 3000, true, false, 1000));
}

// A publish time that has come is carried out by the save and cleared; it
// says so only when the page was going to stay a draft.
function test_page_schedule_plan_due_publish_publishes_now()
{
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('published_now')),
		pg_page_schedule_plan(1000, 0, true, false, 1000));
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(500, 0, false, false, 1000));
	// Its end still ahead stays.
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 3000, 'error' => '', 'notes' => array('published_now')),
		pg_page_schedule_plan(500, 3000, true, false, 1000));
}

// A time to come off that has come takes the page off now; one ahead stays
// and leaves the page on the site.
function test_page_schedule_plan_unpublish()
{
	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('unpublished_now')),
		pg_page_schedule_plan(0, 1000, false, false, 1000));
	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(0, 900, true, false, 1000));
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 3000, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(0, 3000, false, false, 1000));
	// Both times passed while the editor was open: on, then off again.
	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('published_now', 'unpublished_now')),
		pg_page_schedule_plan(500, 800, true, false, 1000));
}

// An end that does not come after the start refuses the save.
function test_page_schedule_plan_order_error()
{
	pg_assert_same('order', pg_page_schedule_plan(3000, 3000, true, false, 1000)['error']);
	pg_assert_same('order', pg_page_schedule_plan(3000, 2000, true, false, 1000)['error']);
	pg_assert_same('order', pg_page_schedule_plan(800, 500, true, false, 1000)['error']);
}

// The home page stays on the site and takes no time.
function test_page_schedule_plan_home_takes_no_time()
{
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('home')),
		pg_page_schedule_plan(2000, 3000, true, true, 1000));
	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array()),
		pg_page_schedule_plan(0, 0, false, true, 1000));
}

// The external API takes a time that names its moment: digits, or a date and
// time with an offset or Z. Without one the site's zone would be a guess.
function test_page_schedule_parse_absolute_needs_an_offset()
{
	page_schedule_test_in_zone('Europe/Istanbul', function () {
		$expected = gmmktime(6, 30, 0, 10, 21, 2026);

		pg_assert_same(array('ok' => true, 'time' => $expected, 'error' => ''), pg_page_schedule_parse_absolute('2026-10-21T09:30:00+03:00'));
		pg_assert_same(array('ok' => true, 'time' => $expected, 'error' => ''), pg_page_schedule_parse_absolute('2026-10-21T06:30:00Z'));
		pg_assert_same(array('ok' => true, 'time' => $expected, 'error' => ''), pg_page_schedule_parse_absolute((string) $expected));
		pg_assert_same(array('ok' => true, 'time' => $expected, 'error' => ''), pg_page_schedule_parse_absolute($expected));

		foreach (array('2026-10-21T09:30', '2026-10-21 09:30', '2026-10-21T09:30:00', ' 2026-10-21T09:30 ') as $local) {
			pg_assert_same(array('ok' => false, 'time' => 0, 'error' => 'offset'), pg_page_schedule_parse_absolute($local), $local);
		}
	});
}

// Nothing sent is a clear; a number too small to be a unix time this
// century (a date written as digits, a flag) and a date that does not exist
// are not times.
function test_page_schedule_parse_absolute_clears_and_refusals()
{
	foreach (array(null, '', 0, '0') as $value) {
		pg_assert_same(array('ok' => true, 'time' => 0, 'error' => ''), pg_page_schedule_parse_absolute($value));
	}

	foreach (array('1', '20261021', 999999999, '2026-02-30T10:00:00Z', 'tomorrow', '2026-10-21T09:30:00+15:00', array()) as $value) {
		pg_assert_same(array('ok' => false, 'time' => 0, 'error' => 'invalid'), pg_page_schedule_parse_absolute($value), json_encode($value));
	}
}

// The times sent lie over the stored ones; a time not sent keeps its value.
function test_page_schedule_request_plan_keeps_what_is_not_sent()
{
	$check = pg_page_schedule_request_plan(array('unpublish_at' => 5000), array('publish_at' => 3000, 'unpublish_at' => 4000), true, false, 1000);

	pg_assert_same('', $check['error']);
	pg_assert_same(array('draft' => true, 'publish_at' => 3000, 'unpublish_at' => 5000, 'error' => '', 'notes' => array()), $check['plan']);

	$cleared = pg_page_schedule_request_plan(array('publish_at' => 0), array('publish_at' => 3000, 'unpublish_at' => 4000), true, false, 1000);

	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 4000, 'error' => '', 'notes' => array()), $cleared['plan']);
}

// The home page is refused a time instead of having it dropped; clearing
// one is not a time.
function test_page_schedule_request_plan_refuses_the_home_page()
{
	pg_assert_same(array('error' => 'home', 'field' => 'unpublish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('publish_at' => 0, 'unpublish_at' => 3000), array(), false, true, 1000));

	pg_assert_same('', pg_page_schedule_request_plan(array('publish_at' => 0), array(), false, true, 1000)['error']);
}

// A new page starts as a draft, so a publish time that has come is refused;
// for a page that exists it is carried out, as by any save.
function test_page_schedule_request_plan_passed_publish_time()
{
	pg_assert_same(array('error' => 'passed', 'field' => 'publish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('publish_at' => 1000), array(), true, false, 1000, true));

	$existing = pg_page_schedule_request_plan(array('publish_at' => 900), array(), true, false, 1000);

	pg_assert_same(array('draft' => false, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('published_now')), $existing['plan']);

	// A publish time ahead takes a page that is on the site off it until then.
	$ahead = pg_page_schedule_request_plan(array('publish_at' => 2000), array(), false, false, 1000);

	pg_assert_same(true, $ahead['plan']['draft']);
}

// An end that does not come after the start names the time that was sent.
function test_page_schedule_request_plan_order_names_the_field()
{
	pg_assert_same(array('error' => 'order', 'field' => 'unpublish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('unpublish_at' => 2500), array('publish_at' => 3000), true, false, 1000));

	pg_assert_same(array('error' => 'order', 'field' => 'publish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('publish_at' => 4500), array('unpublish_at' => 4000), true, false, 1000));
}

// JavaScript's toISOString() writes milliseconds; a space instead of the T
// is read with an offset as well as without one. The fraction is dropped.
function test_page_schedule_parse_fractions_and_space_with_offset()
{
	page_schedule_test_in_zone('Europe/Istanbul', function () {
		$expected = gmmktime(6, 30, 0, 10, 21, 2026);

		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T06:30:00.000Z'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T06:30:00.999Z'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T09:30:00.123456+03:00'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21 09:30:00+03:00'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21 06:30Z'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T06:30:00z'));
		pg_assert_same(array('ok' => true, 'time' => $expected), pg_page_schedule_parse('2026-10-21T09:30:00.500'), 'local time with a fraction');

		pg_assert_same(array('ok' => true, 'time' => $expected, 'error' => ''), pg_page_schedule_parse_absolute('2026-10-21T06:30:00.000Z'));
		pg_assert_same('offset', pg_page_schedule_parse_absolute('2026-10-21T09:30:00.000')['error'], 'a fraction does not stand for an offset');
	});

	foreach (array('2026-10-21T09:30.5Z', '2026-10-21T09:30:00.Z', '2026-10-21T09:30:00,5Z') as $value) {
		pg_assert_false(pg_page_schedule_parse($value)['ok'], 'refused: ' . $value);
	}
}

// The columns are INT UNSIGNED: a later time would be clipped to 2106 by
// MySQL, and one before 1970 would read as no time at all.
function test_page_schedule_parse_refuses_times_the_columns_cannot_hold()
{
	pg_assert_same(array('ok' => true, 'time' => 4294967295), pg_page_schedule_parse('4294967295'));
	pg_assert_same(array('ok' => true, 'time' => 4294967295), pg_page_schedule_parse(4294967295));

	foreach (array('4294967296', 4294967296, 9999999999, 5.0e9, '2106-02-07T06:28:16Z', '2200-01-01T00:00:00Z', '1969-12-31T23:59:59Z', -1) as $value) {
		pg_assert_same(array('ok' => false, 'time' => 0), pg_page_schedule_parse($value), 'refused: ' . json_encode($value));
	}

	pg_assert_same('invalid', pg_page_schedule_parse_absolute('2200-01-01T00:00:00Z')['error']);
}

// A new page is a draft: a time to come off the site that has passed would
// be cleared without doing anything, so it is refused like a passed publish
// time.
function test_page_schedule_request_plan_passed_unpublish_time_on_create()
{
	pg_assert_same(array('error' => 'passed', 'field' => 'unpublish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('unpublish_at' => 900), array(), true, false, 1000, true));

	pg_assert_same(array('error' => 'passed', 'field' => 'unpublish_at', 'plan' => null),
		pg_page_schedule_request_plan(array('publish_at' => 2000, 'unpublish_at' => 1000), array(), true, false, 1000, true));

	// Ahead it is kept; on a page that exists a passed one is carried out.
	pg_assert_same(3000, pg_page_schedule_request_plan(array('unpublish_at' => 3000), array(), true, false, 1000, true)['plan']['unpublish_at']);
	pg_assert_same(array('draft' => true, 'publish_at' => 0, 'unpublish_at' => 0, 'error' => '', 'notes' => array('unpublished_now')),
		pg_page_schedule_request_plan(array('unpublish_at' => 900), array(), false, false, 1000)['plan']);
}
