<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account half of the sign-in throttle (includes/fn/auth.php):
 * a name the user table's collation takes for the same account (case,
 * accents, compatibility forms) is counted on the same subject, and the
 * Unlock Sign-in button reaches that subject.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_login_throttle_account_subject_folds_what_the_collation_matches()
{
	$plain = pg_login_throttle_account_subject('admin');

	pg_assert_true($plain !== '', 'a name has a subject');
	pg_assert_same($plain, pg_login_throttle_account_subject('ADMIN'));
	pg_assert_same($plain, pg_login_throttle_account_subject(' Admin '));

	// utf8mb4_unicode_ci finds the account "admin" for each of these, so
	// each must count on its counter, not open a fresh one.
	pg_assert_same($plain, pg_login_throttle_account_subject("\u{00E1}dmin"));
	pg_assert_same($plain, pg_login_throttle_account_subject("adm\u{00EE}n"));
	pg_assert_same($plain, pg_login_throttle_account_subject("\u{FF41}dmin"));
	pg_assert_same($plain, pg_login_throttle_account_subject("ADM\u{0130}N"));
}

function test_login_throttle_account_subject_keeps_other_names_apart()
{
	pg_assert_same('', pg_login_throttle_account_subject('   '));
	pg_assert_true(pg_login_throttle_account_subject('admin') !== pg_login_throttle_account_subject('admin2'));
	pg_assert_true(pg_login_throttle_account_subject('admin') !== pg_login_throttle_account_subject('admin@example.com'));
}

function test_login_throttle_unlock_reaches_the_folded_subject()
{
	$subjects = pg_login_lock_identifiers('Admin', 'Admin@Example.com');

	pg_assert_true(in_array(pg_login_throttle_account_subject("\u{00C1}DMIN"), $subjects, true), 'the username subject');
	pg_assert_true(in_array(pg_login_throttle_account_subject("admin@ex\u{00E1}mple.com"), $subjects, true), 'the email subject');
}
