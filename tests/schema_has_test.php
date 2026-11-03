<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the schema probe in includes/fn/core.php: the
 * cache file name and the cache lookups behind pg_schema_has().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Both versions are in the name, with only letters, digits and underscores.
function test_schema_has_cache_name_carries_both_versions()
{
	pg_assert_same('schema_2026_4_8_2026_4_8.json', pg_schema_cache_name('2026.4.8', '2026.4.8'));
	pg_assert_same('schema_2026_4_7_2026_4_8.json', pg_schema_cache_name('2026.4.7', '2026.4.8'));
}

// A change of either version gives another file.
function test_schema_has_cache_name_changes_with_version()
{
	$name = pg_schema_cache_name('2026.4.7', '2026.4.8');

	pg_assert_true($name !== pg_schema_cache_name('2026.4.8', '2026.4.8'), 'database version');
	pg_assert_true($name !== pg_schema_cache_name('2026.4.7', '2026.4.9'), 'code version');
}

// Nothing in a version can reach outside data/temp or make an odd file name.
function test_schema_has_cache_name_is_safe()
{
	$name = pg_schema_cache_name('../../x/2026', "a b/c\\d");

	pg_assert_same(1, preg_match('/^schema_[A-Za-z0-9_]*\.json$/', $name), $name);
}

// A table stored with its columns answers for itself and for each column.
function test_schema_has_lookup_known_table()
{
	$cache = pg_schema_cache_store(array(), 'perf_stats', array('id', 'Total_Queries'));

	pg_assert_same(true, pg_schema_cache_lookup($cache, 'perf_stats'));
	pg_assert_same(true, pg_schema_cache_lookup($cache, 'perf_stats', 'id'));
	pg_assert_same(true, pg_schema_cache_lookup($cache, 'perf_stats', 'total_queries'), 'names are kept in lower case');
	pg_assert_same(false, pg_schema_cache_lookup($cache, 'perf_stats', 'max_queries'));
}

// A table stored as missing answers false, for a column question too.
function test_schema_has_lookup_missing_table()
{
	$cache = pg_schema_cache_store(array(), 'api_webhooks', false);

	pg_assert_same(false, pg_schema_cache_lookup($cache, 'api_webhooks'));
	pg_assert_same(false, pg_schema_cache_lookup($cache, 'api_webhooks', 'url'));
}

// A table the cache has not seen is unknown, not missing.
function test_schema_has_lookup_unknown_table()
{
	$cache = pg_schema_cache_store(array(), 'config', array('version'));

	pg_assert_same(null, pg_schema_cache_lookup($cache, 'page'));
	pg_assert_same(null, pg_schema_cache_lookup($cache, 'page', 'page_id'));
	pg_assert_same(null, pg_schema_cache_lookup(array(), 'page'));
}

// A table that turns up after being stored as missing is no longer missing,
// and the other way round.
function test_schema_has_store_replaces_earlier_answer()
{
	$cache = pg_schema_cache_store(array(), 'ws_threads', false);
	$cache = pg_schema_cache_store($cache, 'ws_threads', array('channel_id'));

	pg_assert_same(true, pg_schema_cache_lookup($cache, 'ws_threads', 'channel_id'));
	pg_assert_same(array(), $cache['missing']);

	$cache = pg_schema_cache_store($cache, 'ws_threads', false);

	pg_assert_same(false, pg_schema_cache_lookup($cache, 'ws_threads'));
	pg_assert_false(isset($cache['tables']['ws_threads']), 'columns forgotten');
}

// Forgetting a table makes it unknown again, whether it was stored with its
// columns or as missing; other tables keep their answers.
function test_schema_has_forget_makes_table_unknown()
{
	$cache = pg_schema_cache_store(array(), 'page', array('page_id', 'noindex'));
	$cache = pg_schema_cache_store($cache, 'visitor_content_hourly', false);
	$cache = pg_schema_cache_store($cache, 'style', array('style_id'));

	$cache = pg_schema_cache_forget($cache, 'page');
	$cache = pg_schema_cache_forget($cache, 'visitor_content_hourly');

	pg_assert_same(null, pg_schema_cache_lookup($cache, 'page'), 'stored table');
	pg_assert_same(null, pg_schema_cache_lookup($cache, 'page', 'noindex'), 'its column');
	pg_assert_same(null, pg_schema_cache_lookup($cache, 'visitor_content_hourly'), 'missing table');
	pg_assert_same(array(), $cache['missing']);
	pg_assert_same(true, pg_schema_cache_lookup($cache, 'style', 'style_id'), 'another table');
}

// A table the cache never knew, or a cache that is not one yet, gives a
// well-formed cache back.
function test_schema_has_forget_unknown_table_and_empty_cache()
{
	$cache = pg_schema_cache_forget(pg_schema_cache_store(array(), 'config', array('version')), 'page');

	pg_assert_same(true, pg_schema_cache_lookup($cache, 'config', 'version'));
	pg_assert_same(array('tables' => array(), 'missing' => array()), pg_schema_cache_forget(null, 'page'));
	pg_assert_same(array('tables' => array(), 'missing' => array()), pg_schema_cache_forget(array('tables' => 'x'), 'page'));
}

// pg_schema_forget() works on what this request holds. It loads the request's
// state first, so a cache file read later cannot bring back the answer that
// was just forgotten.
function test_schema_has_forget_drops_request_answer()
{
	$state = &pg_schema_cache_state();
	$saved = $state;

	$cache = pg_schema_cache_store(array(), 'page', array('page_id'));
	$cache = pg_schema_cache_store($cache, 'submitted_form_views', false);
	$state = array('loaded' => false, 'file' => '', 'cache' => $cache);

	pg_schema_forget('submitted_form_views');

	pg_assert_true($state['loaded'], 'state loaded before forgetting');
	pg_assert_same(null, pg_schema_cache_lookup($state['cache'], 'submitted_form_views'));
	pg_assert_same(true, pg_schema_cache_lookup($state['cache'], 'page', 'page_id'));

	$state = $saved;
}

// The order view widget and the security settings save probe columns with
// pg_schema_has(): SHOW COLUMNS ... LIKE treats `_` as a wildcard and runs a
// query on every request.
function test_schema_has_replaces_show_columns_probes()
{
	foreach (array('includes/fn/widgets.php', 'includes/settings/security.save.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true($source !== '', $file . ' read');
		pg_assert_true(stripos($source, 'SHOW COLUMNS') === false, $file . ' has no SHOW COLUMNS probe');
	}
}

// What another request wrote to the file is kept beside this request's
// answers; this request's answer wins for a table both know, and a table
// this request forgot is not brought back.
function test_schema_has_merge_keeps_other_requests_tables()
{
	$ours = array('tables' => array('page' => array('page_id', 'page_name'), 'orders' => array('id')), 'missing' => array('user_mfa'));
	$stored = array(
		'tables' => array('contacts' => array('id'), 'orders' => array('old'), 'user_mfa' => array('id'), 'style' => array('style_id')),
		'missing' => array('waf_rate', 'page', 'gone'),
	);

	$merged = pg_schema_cache_merge($ours, $stored, array('style', 'gone'));

	pg_assert_same(true, pg_schema_cache_lookup($merged, 'contacts'), 'their table');
	pg_assert_same(false, pg_schema_cache_lookup($merged, 'waf_rate'), 'their missing table');
	pg_assert_same(true, pg_schema_cache_lookup($merged, 'page', 'page_name'), 'our table, not their missing');
	pg_assert_same(true, pg_schema_cache_lookup($merged, 'orders', 'id'), 'our columns win');
	pg_assert_same(false, pg_schema_cache_lookup($merged, 'orders', 'old'), 'their columns of our table dropped');
	pg_assert_same(false, pg_schema_cache_lookup($merged, 'user_mfa'), 'our missing, not their table');
	pg_assert_same(null, pg_schema_cache_lookup($merged, 'style'), 'a forgotten table');
	pg_assert_same(null, pg_schema_cache_lookup($merged, 'gone'), 'a forgotten missing table');
	pg_assert_same(1, count(array_keys($merged['missing'], 'user_mfa', true)), 'listed once');
}

// A file that is not a cache adds nothing.
function test_schema_has_merge_ignores_bad_file()
{
	$ours = array('tables' => array('page' => array('page_id')), 'missing' => array());

	pg_assert_same($ours, pg_schema_cache_merge($ours, null));
	pg_assert_same($ours, pg_schema_cache_merge($ours, array('tables' => 'x', 'missing' => array(array('y'), 5))));
}

// The cache file written while one is already there is replaced, merged with
// what it held.
function test_schema_has_cache_file_write_replaces_existing()
{
	$file = sys_get_temp_dir() . '/pg_schema_write_' . getmypid() . '.json';
	file_put_contents($file, json_encode(array('tables' => array('contacts' => array('id')), 'missing' => array('waf_rate'))));

	$written = pg_schema_cache_file_write($file, array('tables' => array('page' => array('page_id')), 'missing' => array()), array());

	pg_assert_true($written !== null, 'written');
	$stored = json_decode((string) file_get_contents($file), true);
	pg_assert_same(true, pg_schema_cache_lookup($stored, 'page'), 'ours');
	pg_assert_same(true, pg_schema_cache_lookup($stored, 'contacts'), 'theirs');
	pg_assert_same(false, pg_schema_cache_lookup($stored, 'waf_rate'), 'their missing');
	pg_assert_same(true, pg_schema_cache_lookup($written, 'contacts'), 'the merge returned');
	pg_assert_same(array(), glob($file . '.*.tmp'), 'no temporary file left');

	@unlink($file);
}

// Runs $test with a db class whose connection is set, and with what
// pg_schema_has() knows set to $cache (no file), restoring both after.
function pg_test_schema_has_with_cache(array $cache, callable $test)
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}

	$state = &pg_schema_cache_state();
	$saved_state = $state;
	$saved_con = db::$con;
	$state = array('loaded' => true, 'file' => '', 'cache' => $cache);
	db::$con = new stdClass();

	try {
		$test();
	} finally {
		db::$con = $saved_con;
		$state = $saved_state;
	}
}

// waf_table_has_column() asks pg_schema_has() once it is loaded, so its
// answer comes from the file cache, not a SHOW COLUMNS query each request.
function test_schema_has_waf_column_probe_uses_cache()
{
	require_once(PG_FUNCTIONS_DIR . '/waf.php');

	pg_test_schema_has_with_cache(
		array('tables' => array('ws_channels' => array('id', 'kind')), 'missing' => array('erp_accounts')),
		function () {
			pg_assert_same(true, waf_table_has_column('ws_channels', 'kind'), 'known column');
			pg_assert_same(false, waf_table_has_column('ws_channels', 'k_nd'), 'exact name, no wildcard');
			pg_assert_same(false, waf_table_has_column('erp_accounts', 'id'), 'missing table');
		}
	);
}

// pg_mfa_table_exists() reads both tables through pg_schema_has().
function test_schema_has_mfa_tables_use_cache()
{
	pg_test_schema_has_with_cache(
		array('tables' => array('user_mfa' => array('id'), 'user_mfa_recovery' => array('id')), 'missing' => array()),
		function () {
			pg_assert_same(true, pg_mfa_table_exists(), 'both tables');
		}
	);

	pg_test_schema_has_with_cache(
		array('tables' => array('user_mfa' => array('id')), 'missing' => array('user_mfa_recovery')),
		function () {
			pg_assert_same(false, pg_mfa_table_exists(), 'one missing');
		}
	);
}
