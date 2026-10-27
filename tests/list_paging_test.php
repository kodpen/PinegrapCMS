<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the server-side list paging helpers (includes/fn/list_paging.php)
 * and the pure parts of the users list (includes/fn/user_list.php): the page
 * window, the search box read as DataTables' smart search read it, the search
 * condition with its bound values, and a row as markup and as DataTables data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');

function test_list_page_window_takes_the_menu_lengths()
{
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window(0, 100));
	pg_assert_same(array('offset' => 200, 'limit' => 25), pg_list_page_window('200', '25'));
	pg_assert_same(array('offset' => 50, 'limit' => null), pg_list_page_window(50, -1), 'unlimited');
}

function test_list_page_window_refuses_odd_values()
{
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window(-5, 7), 'negative offset, unknown length');
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window('1.5', '10.5'));
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window(array(1), array(10)));
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window('1; DROP', '100 OR 1'));
	pg_assert_same(array('offset' => 0, 'limit' => 100), pg_list_page_window(null, null));
}

function test_list_limit_sql()
{
	pg_assert_same(' LIMIT 0, 100', pg_list_limit_sql(array('offset' => 0, 'limit' => 100)));
	pg_assert_same(' LIMIT 300, 10', pg_list_limit_sql(array('offset' => 300, 'limit' => 10)));
	pg_assert_same('', pg_list_limit_sql(array('offset' => 0, 'limit' => null)), 'every row');
	pg_assert_same(' LIMIT 20, 18446744073709551615', pg_list_limit_sql(array('offset' => 20, 'limit' => null)));
}

function test_list_search_terms_split_like_datatables()
{
	pg_assert_same(array(), pg_list_search_terms(''));
	pg_assert_same(array(), pg_list_search_terms('   '));
	pg_assert_same(array(), pg_list_search_terms(array('x')));
	pg_assert_same(array('erdal', 'kodpen'), pg_list_search_terms('  erdal   kodpen '));
	pg_assert_same(array('Ali Veli', 'admin'), pg_list_search_terms('"Ali Veli" admin'), 'quoted phrase');
	pg_assert_same(array('"open'), pg_list_search_terms('"open'), 'unclosed quote');
	pg_assert_same(array('a'), pg_list_search_terms('a a "a"'), 'repeats');
	pg_assert_same(array('Şükrü', 'ığ'), pg_list_search_terms('Şükrü ığ'));
}

function test_list_search_terms_are_capped()
{
	pg_assert_same(10, count(pg_list_search_terms(implode(' ', range(1, 30)))));
	pg_assert_same(array(str_repeat('x', 200)), pg_list_search_terms(str_repeat('x', 500)));
}

function test_list_like_pattern_escapes_wildcards()
{
	pg_assert_same('%abc%', pg_list_like_pattern('abc'));
	pg_assert_same('%50\\%%', pg_list_like_pattern('50%'));
	pg_assert_same('%a\\_b%', pg_list_like_pattern('a_b'));
	pg_assert_same('%a\\\\b%', pg_list_like_pattern('a\\b'));
	pg_assert_same("%O'Brien%", pg_list_like_pattern("O'Brien"), 'quotes are left to the binder');
}

function test_list_search_where_every_word_in_some_column()
{
	pg_assert_same(array('sql' => '', 'params' => array()), pg_list_search_where(array(), array('a.x')));

	$where = pg_list_search_where(array('ali', 'x'), array('user.user_username', 'user.user_email'));
	pg_assert_same('(user.user_username LIKE ? OR user.user_email LIKE ?) AND (user.user_username LIKE ? OR user.user_email LIKE ?)', $where['sql']);
	pg_assert_same(array('%ali%', '%ali%', '%x%', '%x%'), $where['params']);
	pg_assert_same(substr_count($where['sql'], '?'), count($where['params']), 'one value per placeholder');
}

function test_list_search_where_matches_labels()
{
	$labels = array(array('expr' => 'user.user_role', 'labels' => array(0 => 'Administrator', 1 => 'Designer', 2 => 'Manager', 3 => 'User')));

	$where = pg_list_search_where(array('man'), array('user.user_username'), $labels);
	pg_assert_same('(user.user_username LIKE ? OR user.user_role IN (?))', $where['sql']);
	pg_assert_same(array('%man%', '2'), $where['params']);

	$where = pg_list_search_where(array('er'), array('user.user_username'), $labels);
	pg_assert_same('(user.user_username LIKE ? OR user.user_role IN (?, ?, ?))', $where['sql'], 'Designer, Manager, User');
	pg_assert_same(array('%er%', '1', '2', '3'), $where['params']);

	$where = pg_list_search_where(array('zzz'), array(), $labels);
	pg_assert_same('(1 = 0)', $where['sql'], 'a word found nowhere finds nothing');
}

function test_list_row_html_and_data()
{
	$row = array('class' => 'unselectable', 'cells' => array(
		pg_list_cell('<input type="checkbox">', 'select-all align-middle'),
		pg_list_cell('a &amp; b'),
		pg_list_cell('x', ''),
	));

	pg_assert_same('<tr class="unselectable"><td class="select-all align-middle"><input type="checkbox"></td><td class="align-middle">a &amp; b</td><td>x</td></tr>', pg_list_row_html($row));

	$data = pg_list_row_data($row);
	pg_assert_same('<input type="checkbox">', $data['0']);
	pg_assert_same('x', $data['2']);
	pg_assert_same('unselectable', $data['DT_RowClass']);
	pg_assert_same(array('select-all align-middle', 'align-middle', ''), $data['DT_RowData']['pgCells']);
	pg_assert_contains('"0":"<input', json_encode($data), 'an object DataTables reads by column number');
}

function test_list_rows_answer()
{
	$answer = pg_list_rows_answer('7', '120', 3, array(array('class' => '', 'cells' => array(pg_list_cell('a')))));
	pg_assert_same(7, $answer['draw']);
	pg_assert_same(120, $answer['recordsTotal']);
	pg_assert_same(3, $answer['recordsFiltered']);
	pg_assert_same(1, count($answer['data']));
	pg_assert_same(0, pg_list_rows_answer('<x>', 0, 0, array())['draw']);
}

function test_user_list_filter()
{
	pg_assert_same('default', pg_user_list_filter(''));
	pg_assert_same('default', pg_user_list_filter('default'));
	pg_assert_same('default', pg_user_list_filter(array('my_private_users')));
	pg_assert_same('my_private_users', pg_user_list_filter('my_private_users'));
	pg_assert_same('all_site_managers', pg_user_list_filter('all_site_managers'));
	pg_assert_same('all_site_administrators', pg_user_list_filter('all_site_administrators'));
	pg_assert_same('all_my_users', pg_user_list_filter('<script>'), 'unknown names list like All My Users');
}

function test_user_list_filters_follow_the_role()
{
	pg_assert_true(isset(pg_user_list_filters(0)['all_site_administrators']));
	pg_assert_true(isset(pg_user_list_filters(1)['all_site_managers']));
	pg_assert_false(isset(pg_user_list_filters(1)['all_site_designers']));
	pg_assert_false(isset(pg_user_list_filters(2)['all_site_managers']));
}

function test_user_list_role_names()
{
	pg_assert_same(lang('Manager'), get_role_name('2'));
	pg_assert_same(lang('Administrator'), get_role_name(0));
	pg_assert_same(null, get_role_name(7));
}
