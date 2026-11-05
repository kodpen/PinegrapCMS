<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that get_form_item_view.php, get_file.php and add_comment.php grant
 * a member the submitter's or a watcher's rights by e-mail address only when
 * the member has an address: a submission with no connect-to-contact e-mail field gives
 * no row (null), which the old mb_strtolower() comparison turned into '' and
 * matched against an account with no stored address; a watcher row added by
 * user id carries a blank address, which matched the same account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// get_file.php never loads includes/fn/, so the helper must sit in the
// shared authentication file both sides include.
function test_submitter_email_match_helper_is_shared()
{
	$shared = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/authentication.php');
	$auth   = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');

	pg_assert_same(1, preg_match_all('/function\s+pg_email_address_matches\s*\(/', $shared), 'defined in includes/authentication.php');
	pg_assert_same(0, preg_match_all('/function\s+pg_email_address_matches\s*\(/', $auth), 'not defined twice');
}

// Every submitter check asks the helper instead of comparing lowercased
// strings, and every watcher lookup by address skips a blank address.
function test_submitter_email_match_endpoints_use_helper()
{
	$files = array('get_form_item_view.php' => 1, 'get_file.php' => 3, 'add_comment.php' => 1);

	foreach ($files as $file => $count) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match_all('/mb_strtolower\(\s*USER_EMAIL_ADDRESS\s*\)/', $source), $file . ': no lowercased comparison');
		pg_assert_same($count, preg_match_all('/pg_email_address_matches\(\s*USER_EMAIL_ADDRESS\s*,\s*\$submitter_email_address\s*\)/', $source), $file . ': submitter checks use the helper');
		pg_assert_same(0, preg_match_all('/OR \(email_address = /', $source), $file . ': no watcher lookup on a bare address');
		pg_assert_same($count, preg_match_all("/OR \\(\\(email_address = [^\\n]*\\) AND \\(email_address != ''\\)\\)/", $source), $file . ': watcher lookups by address skip a blank address');
	}
}
