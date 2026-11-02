<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that add_or_remove_watcher.php grants a member the submitter's
 * rights by e-mail address only when both addresses are present: a
 * submission with no connect-to-contact e-mail field gives no row (null),
 * which the old mb_strtolower() comparison turned into '' (a deprecation on
 * PHP 8.1+) and matched against an account with no stored address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The comparison the handler relies on: a missing or blank address on
// either side never matches, letter case aside otherwise.
function test_watcher_email_match_missing_address()
{
	pg_assert_same(false, pg_email_address_matches('', null), 'no stored address, no submission address');
	pg_assert_same(false, pg_email_address_matches('a@example.com', null), 'submission without an e-mail field');
	pg_assert_same(false, pg_email_address_matches('', ''), 'both blank');
	pg_assert_same(true, pg_email_address_matches('A@Example.com', 'a@example.com'), 'letter case aside');
}

// Both submitter checks of the handler (manage access and view access) ask
// pg_email_address_matches() rather than comparing lowercased strings.
function test_watcher_email_match_handler_uses_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_or_remove_watcher.php');

	pg_assert_same(0, preg_match_all('/mb_strtolower\(\s*USER_EMAIL_ADDRESS\s*\)/', $source), 'no lowercased comparison of the member address');
	pg_assert_same(2, preg_match_all('/pg_email_address_matches\(\s*USER_EMAIL_ADDRESS\s*,\s*\$submitter_email_address\s*\)/', $source), 'both submitter checks use the helper');
}
