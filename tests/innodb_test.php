<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the table list and the pure helpers of includes/fn/innodb.php.
 * The list is checked against the schema sources it is meant to cover: the
 * starter dump under data/backups and the CREATE TABLE statements of the
 * migrations.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The table names of every CREATE TABLE statement in a piece of SQL or PHP.
function pg_test_innodb_created_tables($source)
{
	preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\(/i', $source, $matches);

	return array_values(array_unique($matches[1]));
}

// The tables of the starter dump, all created on MyISAM.
function pg_test_innodb_dump_tables()
{
	return pg_test_innodb_created_tables((string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/sql.sql'));
}

// The tables the migrations create on the legacy ENGINE constant (MyISAM):
// the CREATE TABLE statements whose closing parenthesis is followed by
// `" . ENGINE`.
function pg_test_innodb_migration_myisam_tables()
{
	$tables = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$source = (string) file_get_contents($file);

		if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((?:(?!CREATE\s+TABLE).)*?\)\s*"\s*\.\s*ENGINE\b/is', $source, $matches)) {
			foreach ($matches[1] as $table) {
				$tables[] = $table;
			}
		}
	}

	return array_values(array_unique($tables));
}

// The groups share no table, they hold 144 between them, and the flat list
// has each table once.
function test_innodb_groups_are_disjoint_and_complete()
{
	$seen = array();
	$duplicates = array();
	$total = 0;

	foreach (pg_innodb_table_groups() as $group => $tables) {
		foreach ($tables as $table) {
			$total++;
			if (isset($seen[$table])) {
				$duplicates[] = $table . ' (' . $seen[$table] . ', ' . $group . ')';
			}
			$seen[$table] = $group;
		}
	}

	pg_assert_same(array(), $duplicates, 'tables in more than one group');
	pg_assert_same(144, $total, 'tables in the groups');
	pg_assert_same(array('orders', 'products', 'people', 'site', 'search'), array_keys(pg_innodb_table_groups()), 'group order');

	$core = pg_innodb_core_tables();

	pg_assert_same(144, count($core), 'core tables');
	pg_assert_same(count($core), count(array_unique($core)), 'core tables are unique');
}

// Every table of the list is created somewhere: in the starter dump or by a
// migration.
function test_innodb_tables_exist_in_schema_sources()
{
	$known = pg_test_innodb_dump_tables();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$known = array_merge($known, pg_test_innodb_created_tables((string) file_get_contents($file)));
	}

	$known = array_flip($known);

	$unknown = array();

	foreach (pg_innodb_core_tables() as $table) {
		if (!isset($known[$table])) {
			$unknown[] = $table;
		}
	}

	pg_assert_same(array(), $unknown, 'tables no schema source creates');
}

// The list is exactly the MyISAM tables of the sources, less the ones earlier
// versions already moved (visitors 2026.3.6, user and files 2026.4.4) and
// custom_apps, dropped in 2026.4.4. A table added to the dump later and
// forgotten in the list fails here.
function test_innodb_list_covers_every_myisam_source_table()
{
	$dump = pg_test_innodb_dump_tables();

	pg_assert_same(141, count($dump), 'tables in the starter dump');

	$migrations = pg_test_innodb_migration_myisam_tables();

	sort($migrations);

	pg_assert_same(
		array('custom_apps', 'iyzipay_3ds_state', 'local_sale_history', 'local_sale_history_items', 'notifications', 'order_refunds', 'product_barcodes'),
		array_values(array_diff($migrations, $dump)),
		'MyISAM tables created by the migrations outside the dump'
	);

	$expected = array_diff(array_unique(array_merge($dump, $migrations)), array('visitors', 'user', 'files', 'custom_apps'));

	sort($expected);

	$list = pg_innodb_core_tables();

	sort($list);

	pg_assert_same(array_values($expected), $list, 'the list against the sources');
}

// Every name is safe to put between backticks in an ALTER statement.
function test_innodb_table_names_are_safe()
{
	$unsafe = array();

	foreach (pg_innodb_core_tables() as $table) {
		if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
			$unsafe[] = $table;
		}
	}

	pg_assert_same(array(), $unsafe, 'unsafe table names');
}

// Sizes read in KB below a megabyte, MB below a gigabyte, GB from there.
function test_innodb_size_label()
{
	pg_assert_same('0 KB', pg_innodb_size_label(0));
	pg_assert_same('16 KB', pg_innodb_size_label(16384));
	pg_assert_same('1 MB', pg_innodb_size_label(1048576));
	pg_assert_same('128 MB', pg_innodb_size_label(134217728));
	pg_assert_same('1.0 GB', pg_innodb_size_label(1073741824));
	pg_assert_same('2.5 GB', pg_innodb_size_label(2684354560));
}
