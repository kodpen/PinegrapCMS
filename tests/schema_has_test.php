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
