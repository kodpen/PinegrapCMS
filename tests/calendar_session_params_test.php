<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar values kept in the session. A calendar view page
 * wrote ?date[]=x, ?view[]=x and ?view=x into the session as they came: from
 * then on every calendar drawn in that session wrote PHP warnings ("Array to
 * string conversion", undefined variables) and an unknown view left the
 * calendar empty. Now an array or unknown value counts as not given, and
 * get_calendar() drops a date that is not text (one an older version kept).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The calendar view page writes calendar_id, view and date to the session
// only when the value is text, and the view only when the calendar draws it.
function test_calendar_session_params_view_page_checks_values()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_calendar_view_screen_content.php');

	foreach (array('calendar_id', 'view', 'date') as $parameter) {
		$read = preg_quote("\$_GET['" . $parameter . "']", '/');

		pg_assert_true((bool) preg_match('/isset\(\s*' . $read . '\s*\)\s*&&\s*is_string\(\s*' . $read . '\s*\)/', $source), $parameter . ' not checked with is_string()');
		pg_assert_false((bool) preg_match('/isset\(\s*' . $read . '\s*\)\s*==\s*true/', $source), $parameter . ' written with a bare isset()');
	}

	pg_assert_true((bool) preg_match("/in_array\(\s*\\\$_GET\['view'\]\s*,\s*\\\$views\s*,\s*true\s*\)/", $source), 'view from the query string not checked against the views');
	pg_assert_true((bool) preg_match("/!in_array\(\s*\(\s*\\\$_SESSION\['software'\]\['calendar_views'\]\[\\\$current_page_id\]\['view'\]\s*\?\?\s*null\s*\)\s*,\s*\\\$views\s*,\s*true\s*\)/", $source), 'kept view not checked against the views');
	pg_assert_true(strpos($source, "\$views = array('', 'monthly', 'weekly', 'upcoming');") !== false, 'views list missing');
}

// get_calendar() turns a date that is not text into "no date" before it
// splits it, instead of casting it to the string "Array".
function test_calendar_session_params_get_calendar_drops_non_text_date()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');

	$guard = strpos($source, 'if (!is_string($date)) {');
	$split = strpos($source, "explode('-', \$date)");

	pg_assert_true($guard !== false, 'get_calendar(): no is_string() check on the date');
	pg_assert_true(($split !== false) && ($guard < $split), 'get_calendar(): date split before the is_string() check');
	pg_assert_false(strpos($source, '(string) $date') !== false, 'get_calendar(): date cast with (string)');
}
