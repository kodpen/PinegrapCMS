<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for api_app_row_locked() (includes/api/keys.php): Application Access
 * is open to roles 0-2, and an application row whose owner outranks the
 * editor (a lower user_role number) is locked for that editor, since its key
 * calls the API with the owner's rights as the cap. Equal rank is allowed; a
 * row whose owner is gone (NULL from the LEFT JOIN) is not locked, because
 * such a key cannot sign in at all (owner_unavailable).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/keys.php');

// An administrator's row is locked for a Designer and a Manager.
function test_api_app_row_locked_admin_row()
{
	pg_assert_false(api_app_row_locked(0, 0), 'administrator edits an administrator row');
	pg_assert_true(api_app_row_locked(0, 1), 'Designer edits an administrator row');
	pg_assert_true(api_app_row_locked(0, 2), 'Manager edits an administrator row');
}

// Designer (1) is above Manager (2): a Designer's row is locked for a Manager.
function test_api_app_row_locked_designer_above_manager()
{
	pg_assert_true(api_app_row_locked(1, 2), 'Manager edits a Designer row');
	pg_assert_false(api_app_row_locked(1, 1), 'Designer edits a Designer row');
	pg_assert_false(api_app_row_locked(2, 1), 'Designer edits a Manager row');
	pg_assert_false(api_app_row_locked(2, 2), 'Manager edits a Manager row');
	pg_assert_false(api_app_row_locked(3, 2), 'Manager edits a user-role row');
}

// The database hands the role back as a string.
function test_api_app_row_locked_string_roles()
{
	pg_assert_true(api_app_row_locked('0', '2'), 'string roles');
	pg_assert_false(api_app_row_locked('2', '0'), 'string roles, administrator editing');
}

// A row whose owner was deleted is not locked: its key answers
// owner_unavailable, so a Manager may still clear it away.
function test_api_app_row_locked_deleted_owner()
{
	pg_assert_false(api_app_row_locked(null, 2), 'NULL owner role');
	pg_assert_false(api_app_row_locked('', 2), 'empty owner role');
}
