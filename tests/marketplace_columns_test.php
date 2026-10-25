<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two marketplace columns of 2026.11.0 (11.0.37):
 * marketplace_product_map.commission, the commission rate a marketplace
 * reports for an approved listing, and marketplace_order_map.invoice_sent,
 * when an imported package's invoice link was accepted. The step is read from
 * the open version's file; the probes code reads the columns through are
 * read from the outbound layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_columns_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function marketplace_columns_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The step is called from the open version with its label, adds the two
// columns with the idempotent helper and tells the operator in one sentence.
// commission is NULL until a rate is read: 0.00 would be a rate of nothing.
function test_marketplace_columns_step()
{
	$source = marketplace_columns_test_source('includes/migrations/2026.11.0.php');
	$entry = marketplace_columns_test_body($source, 'upgrade_to_2026_11_0');
	$step = marketplace_columns_test_body($source, 'upgrade_2026_11_0_marketplace_columns');

	pg_assert_true(preg_match('/upgrade_2026_11_0_marketplace_columns\(\);\s+\/\/ 11\.0\.37/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.37\b/', $entry), 'the label is used once');

	pg_assert_contains("install_add_column('marketplace_product_map', 'commission', \"DECIMAL(6,2) NULL DEFAULT NULL\")", $step);
	pg_assert_contains("install_add_column('marketplace_order_map', 'invoice_sent', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');
}

// Code that reads either column asks first, by the exact names
// (pg_schema_has(), never a LIKE probe), and says no when the probe itself is
// missing; base.php is loaded by every file of the outbound layer.
function test_marketplace_columns_are_probed_by_their_exact_names()
{
	$source = marketplace_columns_test_source('includes/api/outbound/connectors/base.php');

	pg_assert_contains("return function_exists('pg_schema_has') && pg_schema_has('marketplace_product_map', 'commission');",
		marketplace_columns_test_body($source, 'mp_commission_ready'));
	pg_assert_contains("return function_exists('pg_schema_has') && pg_schema_has('marketplace_order_map', 'invoice_sent');",
		marketplace_columns_test_body($source, 'mp_invoice_sent_ready'));

	foreach (array('includes/api/outbound/listings.php', 'includes/api/outbound/orders.php', 'includes/api/outbound/sync.php') as $file) {
		pg_assert_contains("require_once(dirname(__FILE__) . '/connectors/base.php');", marketplace_columns_test_source($file), $file . ' loads the probes');
	}
}
