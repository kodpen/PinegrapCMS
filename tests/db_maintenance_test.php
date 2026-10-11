<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of includes/fn/db_maintenance.php: which tables
 * are worth an OPTIMIZE, and the order and limits of the findings the
 * Database Engine screen lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A catalog row as pg_db_table_catalog() returns it.
function pg_test_db_catalog_row($engine, $bytes, $free, $rows = 0)
{
	return array(
		'engine' => $engine,
		'rows' => $rows,
		'data' => (float) $bytes,
		'index' => 0.0,
		'bytes' => (float) $bytes,
		'free' => (float) $free,
		'collation' => 'utf8mb4_unicode_ci',
		'row_format' => 'Dynamic',
		'auto_increment' => null,
		'updated' => null,
	);
}

// At least 10 MB free and at least a fifth of the file.
function test_db_maintenance_optimize_candidates_thresholds()
{
	$mb = 1048576;

	$catalog = array(
		'large_table' => pg_test_db_catalog_row('innodb', 1024 * $mb, 50 * $mb),
		'holey_table' => pg_test_db_catalog_row('myisam', 50 * $mb, 15 * $mb),
		'small_free' => pg_test_db_catalog_row('myisam', 10 * $mb, 9 * $mb),
		'clean_table' => pg_test_db_catalog_row('innodb', 5 * $mb, 0),
	);

	pg_assert_same(array('holey_table'), pg_db_optimize_candidates($catalog));

	pg_assert_same(array(), pg_db_optimize_candidates(array()), 'empty catalog');
}

// With innodb_file_per_table off every InnoDB table reports the shared
// tablespace's free space as its own: none of them is a candidate, and no
// fragmented finding is written for them. MyISAM tables still are.
function test_db_maintenance_shared_tablespace_is_not_a_candidate()
{
	$mb = 1048576;

	$catalog = array(
		'shared_innodb' => pg_test_db_catalog_row('innodb', 20 * $mb, 64 * $mb),
		'holey_myisam' => pg_test_db_catalog_row('myisam', 50 * $mb, 15 * $mb),
	);

	pg_assert_same(array('shared_innodb', 'holey_myisam'), pg_db_optimize_candidates($catalog), 'file per table');
	pg_assert_same(array('shared_innodb', 'holey_myisam'), pg_db_optimize_candidates($catalog, array('file_per_table' => '1')), 'file per table on');
	pg_assert_same(array('holey_myisam'), pg_db_optimize_candidates($catalog, array('file_per_table' => '0')), 'shared tablespace');
	pg_assert_same(array('holey_myisam'), pg_db_optimize_candidates($catalog, array('file_per_table' => 'OFF')), 'shared tablespace, OFF');

	$findings = pg_db_table_findings($catalog, array(), array('issues' => array()), array('file_per_table' => '0'));

	$kinds = array();

	foreach ($findings as $finding) {
		$kinds[] = $finding['kind'] . ':' . $finding['table'];
	}

	pg_assert_same(array('fragmented:holey_myisam', 'file_per_table:'), $kinds, 'findings with a shared tablespace');
}

// Danger before warning before info, the larger first within a level; the
// large-table findings stop at five.
function test_db_maintenance_findings_order_and_large_limit()
{
	$mb = 1048576;

	$catalog = array();

	for ($i = 1; $i <= 7; $i++) {
		$catalog['big_' . $i] = pg_test_db_catalog_row('innodb', (100 + $i) * $mb, 0, 1000 * $i);
	}

	$catalog['holey'] = pg_test_db_catalog_row('myisam', 40 * $mb, 20 * $mb);
	$catalog['broken'] = pg_test_db_catalog_row('', 0, 0);
	$catalog['repaired_one'] = pg_test_db_catalog_row('myisam', 2 * $mb, 0);

	$pending = array(
		'holey' => array('engine' => 'myisam', 'rows' => 0, 'bytes' => 40.0 * $mb, 'row_estimate' => null),
	);

	$health = array(
		'checked_at' => 0,
		'tables' => 3,
		'issues' => array(
			'broken' => array('error'),
			'repaired_one' => array('repaired'),
		),
	);

	$facts = array('file_per_table' => '1', 'strict_mode' => '0');

	$findings = pg_db_table_findings($catalog, $pending, $health, $facts);

	$kinds = array();

	foreach ($findings as $finding) {
		$kinds[] = $finding['kind'] . ':' . $finding['table'];
	}

	pg_assert_same(array(
		'unreadable:broken',
		'fragmented:holey',
		'health:repaired_one',
		'large:big_7',
		'large:big_6',
		'large:big_5',
		'large:big_4',
		'large:big_3',
		'myisam:',
		'strict_mode:',
	), $kinds, 'kinds in order');

	pg_assert_same('danger', $findings[0]['level'], 'first level');
	pg_assert_same('info', $findings[count($findings) - 1]['level'], 'last level');
	pg_assert_contains('broken', $findings[0]['text']);
}

// A pending table whose row does not fit is a warning; one without an
// estimate is not.
function test_db_maintenance_findings_too_wide()
{
	$estimate = array('bytes' => 14342, 'limit' => 8126, 'fits' => false, 'columns' => 91, 'inline_columns' => 91, 'external_columns' => 0, 'fixed_columns' => 0, 'nullable' => 91, 'widest' => array());

	$catalog = array(
		'config' => pg_test_db_catalog_row('myisam', 65536, 0),
		'page' => pg_test_db_catalog_row('myisam', 32768, 0),
	);

	$pending = array(
		'config' => array('engine' => 'myisam', 'rows' => 1, 'bytes' => 65536.0, 'row_estimate' => $estimate),
		'page' => array('engine' => 'myisam', 'rows' => 1, 'bytes' => 32768.0, 'row_estimate' => null),
	);

	$findings = pg_db_table_findings($catalog, $pending, array('issues' => array()), array());

	pg_assert_same('too_wide', $findings[0]['kind'], 'first kind');
	pg_assert_same('config', $findings[0]['table'], 'first table');
	pg_assert_same('warning', $findings[0]['level'], 'first level');
	pg_assert_same('myisam', $findings[1]['kind'], 'second kind');
	pg_assert_same(2, count($findings), 'findings');
}
