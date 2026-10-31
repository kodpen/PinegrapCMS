<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the selection state of panel lists: the select-all box of a
 * list and the "Select All" switch of a checkbox list show the mixed state
 * when only some rows are ticked, and the bulk buttons that act on the ticked
 * rows are really disabled (not only greyed) while nothing is ticked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one top-level function in backend.src.js.
function list_selection_test_js_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, "\nfunction " . $name . '(');

	pg_assert_true($start !== false, $name . '() exists');

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end - $start);
}

function test_list_select_all_box_shows_the_mixed_state()
{
	$body = list_selection_test_js_function('chart_checkbox_state_change');

	pg_assert_contains("tr:not(.unselectable) .select-all input", $body, 'counts the rows the plugin selects');
	pg_assert_contains("prop('indeterminate'", $body, 'a part-ticked list says it is mixed');
	pg_assert_contains("#select_all", $body, 'on the select-all box');
}

function test_list_bulk_buttons_toggle_the_disabled_property_with_the_class()
{
	$body = list_selection_test_js_function('chart_checkbox_state_change');

	pg_assert_contains("prop('disabled', false)", $body, 'a ticked row enables the bulk buttons');
	pg_assert_contains("prop('disabled', true)", $body, 'nothing ticked disables them for keyboard and screen readers');
}

function test_every_checkbox_list_switch_shows_the_mixed_state()
{
	$body = list_selection_test_js_function('pgSyncCheckboxLists');

	pg_assert_contains('.multiselect-checkbox-container', $body, 'every multiselect list, not only the permission lists');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_true((bool) preg_match("/\\\$\\(document\\)\\.on\\('change', '\\.multiselect-checkbox-container'/", $source), 'one delegated change listener keeps the switches in step');
}

function test_erp_bulk_buttons_start_disabled()
{
	foreach (array('erp_invoices.php' => 3, 'erp_account_sync.php' => 5) as $file => $expected) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		preg_match_all('/<span class="enable-on-selected[^"]*">(.*?)<\/span>\';/s', $source, $groups);

		$buttons = 0;

		foreach ($groups[1] as $group) {
			preg_match_all('/<button [^>]*>/', $group, $tags);

			foreach ($tags[0] as $tag) {
				$buttons++;
				pg_assert_true((bool) preg_match('/ disabled( |>)/', $tag), $file . ': a bulk button is printed with the disabled attribute');
			}
		}

		pg_assert_same($expected, $buttons, $file . ': every bulk button checked');
	}
}
