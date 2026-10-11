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
