<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_list_page_clamp() (includes/fn/list_paging.php): a page
 * number held to the pages a list has, so a reload after rows left the list
 * shows its last page instead of an empty one past the end.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_list_page_clamp_keeps_a_page_inside_the_list()
{
	pg_assert_same(1, pg_list_page_clamp(1, 41, 40));
	pg_assert_same(2, pg_list_page_clamp(2, 41, 40));
	pg_assert_same(3, pg_list_page_clamp(3, 100, 40));
}

function test_list_page_clamp_brings_a_page_past_the_end_to_the_last_one()
{
	pg_assert_same(1, pg_list_page_clamp(2, 40, 40), 'the one row of page 2 was accepted');
	pg_assert_same(2, pg_list_page_clamp(3, 45, 40), 'accept all sure on page 3 of 100');
	pg_assert_same(3, pg_list_page_clamp(999, 81, 40));
}

function test_list_page_clamp_answers_1_for_an_empty_list_or_odd_values()
{
	pg_assert_same(1, pg_list_page_clamp(5, 0, 40), 'no rows');
	pg_assert_same(1, pg_list_page_clamp(0, 100, 40), 'page 0');
	pg_assert_same(1, pg_list_page_clamp(-3, 100, 40), 'negative page');
	pg_assert_same(1, pg_list_page_clamp(4, 100, 0), 'per_page 0');
	pg_assert_same(1, pg_list_page_clamp(4, 100, -10), 'negative per_page');
	pg_assert_same(2, pg_list_page_clamp('2', '41', '40'), 'numeric strings');
}
