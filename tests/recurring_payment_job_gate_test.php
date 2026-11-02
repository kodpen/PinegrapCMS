<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gate of recurring_payment_job.php: a web request must come
 * from a signed-in user with e-commerce access before the job charges due
 * subscriptions, ends cancelled ones or disables PayPal profiles; the
 * scheduler (CLI or the job.php dispatcher) runs it without a session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_recurring_payment_job_gate_before_work()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/recurring_payment_job.php');

	$gate = strpos($source, 'if (!pg_cron_is_background_run()) {');
	$user = strpos($source, 'validate_user()');
	$access = strpos($source, 'validate_ecommerce_access($user)');
	$work = strpos($source, 'pg_subscription_renewals_run(');

	pg_assert_true($gate !== false, 'the job checks for a background run');
	pg_assert_true(($user !== false) && ($access !== false), 'a web run needs a user with e-commerce access');
	pg_assert_true(($work !== false) && ($gate < $user) && ($user < $access) && ($access < $work), 'the gate comes before any work');
}
