<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-Fatura threshold warning (includes/erp/efatura_threshold.php):
 * how the year's gross sales are weighed against the internet-sale and the
 * general threshold, and when the move to e-Fatura falls due.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/efatura_threshold.php');

// 500 Bin TL and 3 Milyon TL in kurus.
function erp_efatura_threshold_test_limits()
{
	return array('web' => 50000000, 'general' => 300000000);
}

// Well under both: one row each, nothing to warn about.
function test_erp_efatura_threshold_below()
{
	$rows = erp_efatura_threshold_rows(array('web' => 10000000, 'all' => 12000000), erp_efatura_threshold_test_limits(), 2026);

	pg_assert_same(2, count($rows));
	pg_assert_same('web', $rows[0]['key']);
	pg_assert_same('below', $rows[0]['state']);
	pg_assert_same(20, $rows[0]['share']);
	pg_assert_same('general', $rows[1]['key']);
	pg_assert_same('below', $rows[1]['state']);
	pg_assert_same(4, $rows[1]['share']);
	pg_assert_same('below', erp_efatura_threshold_worst($rows));
}

// Four fifths of a threshold is near; a kurus less is not.
function test_erp_efatura_threshold_near()
{
	$limits = erp_efatura_threshold_test_limits();

	$rows = erp_efatura_threshold_rows(array('web' => 40000000, 'all' => 40000000), $limits, 2026);
	pg_assert_same('near', $rows[0]['state']);
	pg_assert_same('below', $rows[1]['state']);
	pg_assert_same('near', erp_efatura_threshold_worst($rows));

	$rows = erp_efatura_threshold_rows(array('web' => 39999999, 'all' => 39999999), $limits, 2026);
	pg_assert_same('below', $rows[0]['state']);
	pg_assert_same(79, $rows[0]['share']);
}

// The threshold itself counts as reached ("500 Bin TL or more"), and the move
// falls due on the first day of the seventh month of the next year.
function test_erp_efatura_threshold_reached_and_deadline()
{
	$rows = erp_efatura_threshold_rows(array('web' => 50000000, 'all' => 310000000), erp_efatura_threshold_test_limits(), 2026);

	pg_assert_same('reached', $rows[0]['state']);
	pg_assert_same('reached', $rows[1]['state']);
	pg_assert_same('2027-07-01', $rows[0]['deadline']);
	pg_assert_same('2027-07-01', $rows[1]['deadline']);
	pg_assert_same(103, $rows[1]['share']);
	pg_assert_same('reached', erp_efatura_threshold_worst($rows));

	$rows = erp_efatura_threshold_rows(array('web' => 49999999, 'all' => 49999999), erp_efatura_threshold_test_limits(), 2026);
	pg_assert_same('near', $rows[0]['state']);
}

// A threshold of 0 is switched off and gets no row; with both off there is
// nothing to say.
function test_erp_efatura_threshold_off()
{
	$rows = erp_efatura_threshold_rows(array('web' => 90000000, 'all' => 90000000), array('web' => 0, 'general' => 300000000), 2026);

	pg_assert_same(1, count($rows));
	pg_assert_same('general', $rows[0]['key']);

	$rows = erp_efatura_threshold_rows(array('web' => 90000000, 'all' => 90000000), array('web' => 0, 'general' => -5), 2026);
	pg_assert_same(array(), $rows);
	pg_assert_same('', erp_efatura_threshold_worst($rows));
}

// Values as the database hands them back (strings, a missing key) are read
// as whole kurus, and a negative sum (refunds larger than sales) as nothing.
function test_erp_efatura_threshold_reads_database_values()
{
	$rows = erp_efatura_threshold_rows(array('web' => '50000000'), array('web' => '50000000', 'general' => '300000000'), '2026');

	pg_assert_same('reached', $rows[0]['state']);
	pg_assert_same(0, $rows[1]['sales']);
	pg_assert_same('below', $rows[1]['state']);
	pg_assert_same('2027-07-01', $rows[0]['deadline']);

	$rows = erp_efatura_threshold_rows(array('web' => -100, 'all' => -100), erp_efatura_threshold_test_limits(), 2026);
	pg_assert_same(0, $rows[0]['sales']);
	pg_assert_same(0, $rows[0]['share']);
}
