<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the change password handler (change_password.php) treats a field
 * posted as an array (password_hint[]=) as not given: such a value reached
 * mb_strtolower(), the sign-in check and the password hash and stopped the
 * request on PHP 8.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_change_password_fields_are_single_values()
{
	$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/change_password.php');
	$list = "pg_liveform_single_values(\$liveform, array('email_address', 'current_password', 'new_password', 'new_password_verify', 'password_hint'));";
	pg_assert_contains($list, $code, 'every field of the form is a single value');

	// The fields are emptied before the first validation reads them.
	$at = strpos($code, $list);
	pg_assert_true($at !== false && $at > strpos($code, '$liveform->add_fields_to_session();'), 'after the fields are stored');
	pg_assert_true($at !== false && $at < strpos($code, "validate_required_field('email_address'"), 'before the first validation');
}
