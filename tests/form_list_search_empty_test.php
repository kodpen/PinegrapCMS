<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sw_form_list_empty_message() in includes/fn/widgets.php: a
 * form list whose search matched nothing says so, while a list with no
 * records at all keeps its configured empty message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Without a search: the configured message, else "No records found."
function test_form_list_search_empty_without_search()
{
	pg_assert_same('No topics yet.', pg_sw_form_list_empty_message('No topics yet.', false));
	pg_assert_same(lang('No records found.'), pg_sw_form_list_empty_message('', false));
	pg_assert_same(lang('No records found.'), pg_sw_form_list_empty_message(null, false));
}

// With a search that matched nothing: the search message, whatever is set.
function test_form_list_search_empty_with_search()
{
	$expected = lang('Nothing matched your search. Try fewer or different words.');
	pg_assert_same($expected, pg_sw_form_list_empty_message('No topics yet.', true));
	pg_assert_same($expected, pg_sw_form_list_empty_message('', true));
}

// The form list builds its empty state from the helper, once the search is known.
function test_form_list_search_empty_renderer_wiring()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	pg_assert_contains('pg_sw_state_message_html(pg_sw_form_list_empty_message($empty_message, $search_active)', $source);
	$search = strpos($source, '$search_active = $search_enabled');
	$empty = strpos($source, 'pg_sw_form_list_empty_message($empty_message, $search_active)');
	pg_assert_true($search !== false && $empty !== false && $search < $empty, 'the search is known first');
}
