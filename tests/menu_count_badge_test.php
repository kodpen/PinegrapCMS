<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_menu_count_badge() (includes/fn/output.php), the red count of
 * a main menu row and of the panel entry the count is about (the ERP's
 * Overdue entry): nothing for no count, the number, 99+ past 99.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No count draws no pill: an empty one reads as a notification nobody finds.
function test_menu_count_badge_draws_nothing_without_a_count()
{
	pg_assert_same('', pg_menu_count_badge(0), 'zero');
	pg_assert_same('', pg_menu_count_badge(-3), 'below zero');
	pg_assert_same('', pg_menu_count_badge('0'), 'zero as a string from the database');
	pg_assert_same('', pg_menu_count_badge(null), 'no count');
	pg_assert_same('', pg_menu_count_badge('abc'), 'not a number');
	pg_assert_same('', pg_menu_count_badge(array(1)), 'an array');
}

// A count is drawn as itself, capped at 99+, with its hover text escaped.
function test_menu_count_badge_draws_the_count()
{
	pg_assert_same('<span class="pg-menu-badge badge rounded-pill theme-danger">3</span>', pg_menu_count_badge(3), 'a count without a title');
	pg_assert_same('<span class="pg-menu-badge badge rounded-pill theme-danger">7</span>', pg_menu_count_badge('7'), 'a count as a string');
	pg_assert_same('<span class="pg-menu-badge badge rounded-pill theme-danger">99</span>', pg_menu_count_badge(99), '99 is shown');
	pg_assert_same('<span class="pg-menu-badge badge rounded-pill theme-danger">99+</span>', pg_menu_count_badge(100), 'past 99');
	pg_assert_same('<span class="pg-menu-badge badge rounded-pill theme-danger" title="2 &quot;late&quot; &amp; due">2</span>', pg_menu_count_badge(2, '2 "late" & due'), 'the title escaped');
}
