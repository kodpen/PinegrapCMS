<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_comment_rating_value() and pg_comment_list_name()
 * (includes/fn/comments.php). The first is the star
 * rating the comment form and the Edit Comment screen store, one to five or
 * 0 for none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One to five is kept, as a number.
function test_comment_rating_value_in_range()
{
	pg_assert_same(1, pg_comment_rating_value('1'), 'one star');
	pg_assert_same(5, pg_comment_rating_value('5'), 'five stars');
	pg_assert_same(3, pg_comment_rating_value(3), 'an integer');
	pg_assert_same(4, pg_comment_rating_value(' 4 '), 'spaces around');
}

// Anything else is no rating: out of range, empty, not a whole number, an array.
function test_comment_rating_value_rejects_the_rest()
{
	pg_assert_same(0, pg_comment_rating_value('9'), 'above five');
	pg_assert_same(0, pg_comment_rating_value('0'), 'zero');
	pg_assert_same(0, pg_comment_rating_value('-1'), 'negative');
	pg_assert_same(0, pg_comment_rating_value(''), 'empty');
	pg_assert_same(0, pg_comment_rating_value(null), 'not posted');
	pg_assert_same(0, pg_comment_rating_value('abc'), 'text');
	pg_assert_same(0, pg_comment_rating_value('4.5'), 'a fraction');
	pg_assert_same(0, pg_comment_rating_value('3abc'), 'a number with text after it');
	pg_assert_same(0, pg_comment_rating_value(array('4')), 'an array');
}

// The Comments list cuts a name over 40 characters and keeps the whole name
// in the title; a shorter one is printed as it is. Both escaped.
function test_comment_list_name()
{
	pg_assert_same('Jane &amp; Co', pg_comment_list_name('Jane & Co'), 'a short name, escaped');
	pg_assert_same(str_repeat('Ş', 40), pg_comment_list_name(str_repeat('Ş', 40)), '40 characters are kept');

	$long = 'pg-test ' . str_repeat('Ş', 84);
	$out = pg_comment_list_name($long);
	pg_assert_same('<span title="' . $long . '">' . mb_substr($long, 0, 40, 'UTF-8') . '…</span>', $out, 'a long name is cut, whole in the title');
}
