<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which accounts the device push queue may still wake
 * (pg_push_account_wakeable() in includes/push.php): a disabled or deleted
 * account's browsers are dropped, not woken. A subscription made in a
 * session without "remember me" carries no auth_selector, so disabling the
 * account (pg_auth_token_revoke_user()) never removed it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

function test_push_disabled_account_enabled_row_is_woken()
{
	pg_assert_true(pg_push_account_wakeable(array('user_id' => '7', 'user_disabled_at' => '0')));
}

function test_push_disabled_account_row_before_the_column_is_woken()
{
	pg_assert_true(pg_push_account_wakeable(array('user_id' => '7')));
}

function test_push_disabled_account_disabled_row_is_not_woken()
{
	pg_assert_false(pg_push_account_wakeable(array('user_id' => '7', 'user_disabled_at' => '1762300000')));
}

function test_push_disabled_account_gone_account_is_not_woken()
{
	pg_assert_false(pg_push_account_wakeable(null));
	pg_assert_false(pg_push_account_wakeable(array()));
}

// The queue asks before it sends, and drops the person's rows and browsers
// instead of waking them.
function test_push_disabled_account_queue_run_asks_before_sending()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/push.php');
	$run = substr($source, (int) strpos($source, 'function pg_push_queue_run('));

	$ask = strpos($run, 'pg_push_account_wakeable(pg_push_account($user_id))');
	$send = strpos($run, 'pg_push_notify_user($user_id)');

	pg_assert_true($ask !== false);
	pg_assert_true(($ask !== false) && ($send !== false) && ($ask < $send));
	pg_assert_contains("DELETE FROM push_subscriptions WHERE user_id = '\" . \$user_id . \"'", $run);
}
