<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the users list's filter conditions (pg_user_list_filter_rule()): the
 * folder filters list a manager without a folder right of their own, and
 * the list reads one row per user from the user table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_user_list_folder_filters_ask_aclfolder_per_user()
{
	pg_assert_same(true, function_exists('pg_user_list_filter_rule'), 'the filter conditions are a function of their own');

	foreach (array('my_private_users' => '1', 'my_content_managers' => '2') as $filter => $rights) {
		$rule = pg_user_list_filter_rule($filter);
		$condition = (string) $rule[0];

		pg_assert_same(0, strpos($condition, "((user.user_role < '3') OR EXISTS (SELECT 1 FROM aclfolder WHERE (aclfolder.aclfolder_user = user.user_id)"), $filter . ': a manager is listed without a folder right');
		pg_assert_same(true, strpos($condition, "aclfolder.aclfolder_rights = '" . $rights . "'") !== false, $filter . ': the right it asks for');
	}

	pg_assert_same(null, pg_user_list_filter_rule('default'));
	pg_assert_same(null, pg_user_list_filter_rule('all_my_users'));
	pg_assert_same('(user.user_role < 3)', pg_user_list_filter_rule('all_site_managers')[0]);
}

function test_user_list_reads_from_the_user_table()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');

	pg_assert_same(false, strpos($source, "\$from = 'aclfolder'") !== false, 'no filter lists from aclfolder, which drops a manager without folder rows');
	pg_assert_same(false, strpos($source, "'DISTINCT user.user_id'") !== false);
}
