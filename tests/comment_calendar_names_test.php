<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Edit Comment rating, the panel calendar's navigation
 * links and selects, and the accessibility report's tables: the rating
 * radios stay reachable from the keyboard and carry a name, the calendar
 * controls are named, and the report's tables scroll inside their card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_calendar_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_comment_rating_radios_reachable_and_named()
{
	$source = comment_calendar_names_source('edit_comment.php');

	pg_assert_true((bool) preg_match_all("/output_field\(array\('type'=>'radio', 'name'=>'rating'[^\n]*/", $source, $matches), 'no rating radio found');
	pg_assert_true(count($matches[0]) === 5, 'expected five rating radios');

	foreach ($matches[0] as $call) {
		pg_assert_true(strpos($call, 'display:none') === false, 'a rating radio is hidden from the keyboard');
		pg_assert_contains("'aria-label'=>lang(array('string'=>'Rated {var:1} out of 5'", $call);
	}

	pg_assert_contains('<label id="comment_rating_label" class="form-label">', $source);
	pg_assert_contains('role="radiogroup" aria-labelledby="comment_rating_label"', $source);
	pg_assert_true(strpos($source, 'aria-label="Rating"') === false, 'the rating group keeps a literal English name');
	pg_assert_contains('.rating .btn-check:focus-visible+label', $source);
}

function test_panel_calendar_controls_named()
{
	$source = comment_calendar_names_source('includes/fn/calendar.php');

	pg_assert_true(strpos($source, '" class="btn btn-outline theme-primary" class="') === false, 'a calendar link carries two class attributes');

	foreach (array('Previous month', 'Next month', 'Previous week', 'Next week', 'Calendar', 'View', 'Status') as $name) {
		pg_assert_contains("aria-label=\"' . h(lang('" . $name . "')) . '\"", $source);
	}
}

function test_accessibility_report_tables_responsive()
{
	$source = comment_calendar_names_source('view_accessibility.php');

	pg_assert_true((bool) preg_match_all('/<table class="table/', $source, $tables), 'no table found');
	pg_assert_true((bool) preg_match_all('/<div class="table-responsive">\s*<table class="table/', $source, $wrapped), 'no wrapped table found');
	pg_assert_true(count($tables[0]) === count($wrapped[0]), 'a report table is not in .table-responsive');
}
