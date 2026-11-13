<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's calendar view (get_calendar() in
 * includes/fn/calendar.php with a user): the reservation dot, removed
 * repeating instances, the weekly day cards and the picker row, while the
 * site's output stays as it was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_availability_icon_site_keeps_its_dot()
{
	pg_assert_same('<span style="color: green; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>', pg_calendar_availability_icon(true, false));
	pg_assert_same('<span style="color: lightgrey; font-weight: bold; font-size: 130%; margin-right: .25em">&#9679;</span>', pg_calendar_availability_icon(false, false));
}

function test_calendar_availability_icon_panel_names_what_it_means()
{
	$open = pg_calendar_availability_icon(true, array('role' => 0));
	$closed = pg_calendar_availability_icon(false, array('role' => 0));

	pg_assert_contains('class="pg-cal-avail is-open"', $open);
	pg_assert_contains('class="visually-hidden">' . h(lang('Reservations open')) . '</span>', $open);
	pg_assert_contains('class="pg-cal-avail is-closed"', $closed);
	pg_assert_contains('title="' . h(lang('Reservations closed')) . '"', $closed);
	pg_assert_true(strpos($open . $closed, 'style=') === false, 'no inline colour in the panel');
}

function test_calendar_panel_view_markup()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	// A removed instance was drawn like a live one: the class had no rule.
	pg_assert_contains('.calendar_event_exception,', $css);
	pg_assert_contains("<span class=\"calendar_event_exception\"><span class=\"visually-hidden\">' . h(lang('Removed')) . ': </span>", $source);

	// The weekly list is a card per day in the panel, a fieldset on the site.
	pg_assert_contains('<section class="card pg-cal-day mb-3">', $source);
	pg_assert_contains('<fieldset style="margin-bottom: 15px"', $source);

	// The calendar select had a quarter of half the row and cut every name.
	pg_assert_contains('<div class="col-12 md:col-6 lg:col-5 align-self-center">', $source);
	pg_assert_contains('<span class="bi bi-chevron-left" aria-hidden="true"></span>', $source);
}

function test_calendar_panel_has_no_upcoming_view()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/calendars.php');

	// The panel never fills the events' calendars the upcoming list reads.
	pg_assert_contains("array('', 'monthly', 'weekly'), true)", $source);
}

// On a phone the month scrolls sideways inside .monthly. Positioned, the box
// is the containing block of the visually hidden "Removed: " label before a
// removed occurrence, which the outer card was: the label stood at its place
// in the wide grid outside the box and the whole page scrolled sideways. The
// site keeps its markup.
function test_calendar_panel_month_box_holds_its_hidden_labels()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');

	pg_assert_contains("\$output_calendar = '<div class=\"monthly' . (\$user ? ' position-relative' : '') . '\">", $source);
}
