<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the list screens' date range filters are drawn on the one
 * date-and-time control: the start and end month, day and year selects sit
 * in a data-pg-date-selects wrapper (they stay the posted fields), the
 * wrapper is a fieldset named by its legend, and every such screen prints
 * the site's date order with the field script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_date_filters_test_screens()
{
	return array('view_commissions.php', 'view_contacts.php', 'view_log.php', 'view_orders.php', 'view_submitted_forms.php');
}

// The markup of the element whose opening tag carries data-pg-date-selects,
// up to the next one or the end of the source.
function list_date_filters_test_wrappers($source)
{
	$parts = explode('data-pg-date-selects', $source);
	array_shift($parts);
	return $parts;
}

function test_list_date_filters_each_range_end_is_one_wrapper()
{
	foreach (list_date_filters_test_screens() as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$wrappers = list_date_filters_test_wrappers($source);

		pg_assert_same(2, count($wrappers), $file . ' has a start and an end wrapper');
		if (count($wrappers) !== 2) {
			continue;
		}

		foreach (array('start' => $wrappers[0], 'stop' => $wrappers[1]) as $end => $markup) {
			$inside = substr($markup, 0, (int) strpos($markup, '</fieldset>'));

			foreach (array('month', 'day', 'year') as $part) {
				pg_assert_contains('name="' . $end . '_' . $part . '"', $inside, $file . ': ' . $end . '_' . $part . ' stays a posted select inside its wrapper');
			}
			pg_assert_true(
				preg_match('/<legend[^>]*>\' \. lang\(\'(Start|End)\'\)/', $inside) === 1,
				$file . ': the ' . $end . ' wrapper is named by its legend'
			);
		}
	}
}

function test_list_date_filters_screens_print_the_date_order_with_the_script()
{
	foreach (list_date_filters_test_screens() as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('get_date_picker_format() . pg_fields_script()', $source, $file . ' prints the date order with the field script');
		pg_assert_same(1, substr_count($source, 'pg_fields_script()'), $file . ' prints the field script once');
	}
}
