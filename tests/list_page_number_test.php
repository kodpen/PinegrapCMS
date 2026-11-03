<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_list_page_number() (includes/fn/list_paging.php): the page a
 * list screen's ?screen= asks for, always a whole number of 1 or more, so a
 * forged value such as 1e400 cannot turn the offset negative.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_list_page_number_keeps_a_whole_page()
{
	pg_assert_same(1, pg_list_page_number('1'));
	pg_assert_same(7, pg_list_page_number('7'));
	pg_assert_same(3, pg_list_page_number(3));
	pg_assert_same(4, pg_list_page_number('4.0'), 'a whole float string');
	pg_assert_same(1000, pg_list_page_number('1e3'), 'exponent form of a whole number');
}

function test_list_page_number_answers_1_for_anything_else()
{
	pg_assert_same(1, pg_list_page_number(''));
	pg_assert_same(1, pg_list_page_number(null));
	pg_assert_same(1, pg_list_page_number('0'));
	pg_assert_same(1, pg_list_page_number('-2'));
	pg_assert_same(1, pg_list_page_number('2.5'), 'not whole');
	pg_assert_same(1, pg_list_page_number('abc'));
	pg_assert_same(1, pg_list_page_number(array('2')));
	pg_assert_same(1, pg_list_page_number(true));
	pg_assert_same(1, pg_list_page_number('-1e400'), 'minus infinity');
	pg_assert_same(1, pg_list_page_number(NAN));
}

function test_list_page_number_holds_a_huge_page_at_the_cap()
{
	pg_assert_same(1000000, pg_list_page_number('1e400'), 'INF cast to 0 before');
	pg_assert_same(1000000, pg_list_page_number('1e19'), 'past PHP_INT_MAX cast to 0 before');
	pg_assert_same(1000000, pg_list_page_number('1e18'), 'its offset overflowed to a float');
	pg_assert_same(1000000, pg_list_page_number('1000000'));
	pg_assert_same(999999, pg_list_page_number('999999'));
	pg_assert_same(50, pg_list_page_number('80', 50), 'a smaller cap');
	pg_assert_same(1, pg_list_page_number('80', 0), 'a cap under 1 is 1');
}
