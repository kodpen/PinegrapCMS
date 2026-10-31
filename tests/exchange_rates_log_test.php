<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Update Exchange Rates writes one Site Log line naming how many rates it
 * changed, or that none could be fetched, from the button and the scheduled
 * job alike.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Some or all rates written: the line counts them out of the ones asked for.
function test_exchange_rates_log_counts_written_rates()
{
	pg_assert_same(array(
		'string' => '{var:1} of {var:2} exchange rates were updated',
		'vars'   => array(3, 3),
	), pg_exchange_rates_log_line(3, 3));

	pg_assert_same(array(
		'string' => '{var:1} of {var:2} exchange rates were updated',
		'vars'   => array(1, 4),
	), pg_exchange_rates_log_line(1, 4));
}

// Nothing fetched: the line says no rate changed.
function test_exchange_rates_log_says_none_fetched()
{
	pg_assert_same(array(
		'string' => 'Exchange rates were not updated: no rate could be fetched ({var:1} requested)',
		'vars'   => array(2),
	), pg_exchange_rates_log_line(0, 2));
}

// No currency other than the base: nothing was asked for, nothing is logged.
function test_exchange_rates_log_skips_empty_run()
{
	pg_assert_same(null, pg_exchange_rates_log_line(0, 0));
}

// The script logs the line for both callers; log_activity() names the
// signed-in user, and the scheduled job, which has none, logs with no user.
function test_exchange_rates_script_writes_log_line()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_exchange_rates.php');

	pg_assert_true(strpos($source, '$log_line = pg_exchange_rates_log_line($updated, count($wanted));') !== false);
	pg_assert_true(strpos($source, 'log_activity(pg_log_lang($log_line));') !== false);
	// Logged before the redirect, so a run without send_to logs too.
	pg_assert_true(strpos($source, 'log_activity(pg_log_lang(') < strpos($source, "if ((\$_GET['send_to'] ?? '')) {"));
}
