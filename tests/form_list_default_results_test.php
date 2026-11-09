<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_form_list_view_shows_results() in includes/fn/forms.php and
 * its use in get_form_list_view.php: a list with neither browse nor search
 * lists its records without "Show Results by Default".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Neither browse nor search: results are listed, whatever the default setting.
function test_form_list_default_results_without_browse_or_search()
{
	pg_assert_true(pg_form_list_view_shows_results(0, false, 0, false));
	pg_assert_true(pg_form_list_view_shows_results('0', null, '0', false));
}

// Browse or search on and the visitor has done neither: only the default setting lists.
function test_form_list_default_results_waits_for_visitor()
{
	pg_assert_same(false, pg_form_list_view_shows_results(1, false, 0, false));
	pg_assert_same(false, pg_form_list_view_shows_results(0, true, 0, false));
	pg_assert_true(pg_form_list_view_shows_results(1, true, 1, false));
	pg_assert_true(pg_form_list_view_shows_results(1, true, 0, true));
}

// The page sets the browse and search state before the block that may skip it,
// and decides with the helper.
function test_form_list_default_results_page_wiring()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/get_form_list_view.php');
	$block = strpos($source, 'if (($browse == 1) || ($search == 1)) {');
	pg_assert_true($block !== false, 'browse/search block found');
	foreach (array('$browse_enabled = false;', "\$search_query = '';", '$advanced_filter = false;', '$number_of_results = 0;') as $init) {
		$at = strpos($source, $init);
		pg_assert_true($at !== false && $at < $block, $init . ' before the browse/search block');
	}
	pg_assert_contains('pg_form_list_view_shows_results(', $source);
	pg_assert_same(false, strpos($source, 'isset($browse_enabled)'), 'no isset() on a variable now always set');
}
