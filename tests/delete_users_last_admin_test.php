<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the last administrator rule of Delete Selected on the users
 * list: it asks pg_user_is_last_admin() (another enabled administrator) per
 * user, as the edit screen does, instead of counting every role 0 account,
 * disabled ones included.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_delete_users_keeps_last_enabled_admin()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/delete_users.php');

	pg_assert_contains('!pg_user_is_last_admin($user_id)', $source, 'delete_users asks for another enabled administrator');
	pg_assert_false(strpos($source, "COUNT(user_id) FROM user WHERE user_role = '0'") !== false, 'delete_users no longer counts disabled administrators');
	pg_assert_false(strpos($source, '$number_of_administrators') !== false, 'no administrator count is kept');
}
