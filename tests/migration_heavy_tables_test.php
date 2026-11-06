<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the preflight list of heavy tables of 2026.11.0
 * (install_heavy_tables(), includes/migrations/runner.php): every table that
 * grows with orders or membership and that a 2026.11.0 step rebuilds is on
 * the version's list, so the preflight card warns about its size before the
 * upgrade starts. The runner cannot be loaded without the upgrade's gate, so
 * the list is read from its source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function migration_heavy_tables_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The tables of the version's entry in install_heavy_tables().
function migration_heavy_tables_test_list($version)
{
	$runner = migration_heavy_tables_test_source('includes/migrations/runner.php');

	if (!preg_match("/'" . preg_quote($version, '/') . "' => array\\(([^)]*)\\)/", $runner, $match)) {
		return array();
	}

	preg_match_all("/'([a-z_0-9]+)'/", $match[1], $tables);

	return $tables[1];
}

function test_migration_heavy_tables_2026_11_0_lists_the_rebuilt_growing_tables()
{
	$step = migration_heavy_tables_test_source('includes/migrations/2026.11.0.php');
	$heavy = migration_heavy_tables_test_list('2026.11.0');

	// The table each step rebuilds, and the call that shows it does.
	$rebuilt = array(
		'shipping_tracking_numbers' => "install_add_index('shipping_tracking_numbers', 'uk_parcel'",
		'user'                      => "install_add_column('user', 'user_disabled_at'",
		'files'                     => "'storage_key' =>",
	);

	foreach ($rebuilt as $table => $call) {
		pg_assert_true(strpos($step, $call) !== false, '2026.11.0 still rebuilds ' . $table);
		pg_assert_true(in_array($table, $heavy, true), 'the preflight warns about ' . $table . ' for 2026.11.0');
	}
}

function test_migration_heavy_tables_2026_11_0_keeps_its_earlier_tables()
{
	$heavy = migration_heavy_tables_test_list('2026.11.0');

	foreach (array('products', 'page', 'style', 'comments', 'order_items', 'contacts', 'ship_tos', 'contacts_contact_groups_xref', 'forms', 'orders') as $table) {
		pg_assert_true(in_array($table, $heavy, true), 'the preflight still warns about ' . $table . ' for 2026.11.0');
	}
}
