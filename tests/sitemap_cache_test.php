<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the kept sitemap.xml in includes/fn/seo.php: the kept file read
 * back whole or not at all, and when a kept copy is served, drawn again or
 * not read; and that a write marks the generation while the page cache is
 * off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function sitemap_cache_test_content()
{
	return '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
		. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
		. '    <url><loc>https://example.com/</loc></url>' . "\n"
		. '</urlset>';
}

function sitemap_cache_test_entry(array $change = array())
{
	return pg_sitemap_cache_unpack(pg_sitemap_cache_pack(array_merge(array(
		'created' => 1000,
		'generation' => 7,
		'version' => '2026.11.0',
		'urls_exist' => true,
	), $change), sitemap_cache_test_content()));
}

function test_sitemap_cache_file_reads_back()
{
	$entry = sitemap_cache_test_entry();

	pg_assert_same(sitemap_cache_test_content(), $entry['content'], 'content');
	pg_assert_same(array('created' => 1000, 'generation' => 7, 'version' => '2026.11.0', 'urls_exist' => true), $entry['meta'], 'meta');
	pg_assert_same(false, sitemap_cache_test_entry(array('urls_exist' => false))['meta']['urls_exist'], 'no addresses');
}

function test_sitemap_cache_file_cut_or_foreign_is_no_copy()
{
	$raw = pg_sitemap_cache_pack(array('created' => 1, 'generation' => 1, 'version' => 'x', 'urls_exist' => true), sitemap_cache_test_content());

	pg_assert_same(null, pg_sitemap_cache_unpack(''), 'empty');
	pg_assert_same(null, pg_sitemap_cache_unpack(substr($raw, 0, 40)), 'cut in the first line');
	pg_assert_same(null, pg_sitemap_cache_unpack(substr($raw, 0, -12)), 'cut in the sitemap');
	pg_assert_same(null, pg_sitemap_cache_unpack(str_replace('"v":1', '"v":2', $raw)), 'another layout');
	pg_assert_same(null, pg_sitemap_cache_unpack("{\"v\":1}\n" . sitemap_cache_test_content()), 'fields missing');
	pg_assert_same(null, pg_sitemap_cache_unpack("not json\n" . sitemap_cache_test_content()), 'not JSON');
}

function test_sitemap_cache_state()
{
	$entry = sitemap_cache_test_entry();

	pg_assert_same('miss', pg_sitemap_cache_state(null, 7, '2026.11.0', 1000, 3600), 'no copy');
	pg_assert_same('miss', pg_sitemap_cache_state($entry, 7, '2026.11.1', 1000, 3600), 'another version');
	pg_assert_same('fresh', pg_sitemap_cache_state($entry, 7, '2026.11.0', 1000, 3600), 'just drawn');
	pg_assert_same('fresh', pg_sitemap_cache_state($entry, 7, '2026.11.0', 4599, 3600), 'within the lifetime');
	pg_assert_same('stale', pg_sitemap_cache_state($entry, 7, '2026.11.0', 4600, 3600), 'lifetime over');
	pg_assert_same('stale', pg_sitemap_cache_state($entry, 8, '2026.11.0', 1001, 3600), 'a write since');
	pg_assert_same('stale', pg_sitemap_cache_state($entry, 7, '2026.11.0', 900, 3600), 'clock set back');
	pg_assert_same('fresh', pg_sitemap_cache_state($entry, 7, '2026.11.0', 990, 3600), 'clocks a little apart');
}

function test_sitemap_cache_write_round_trip()
{
	$directory = sys_get_temp_dir() . '/pg_sitemap_cache_test_' . getmypid();
	$file = $directory . '/sitemap.txt';
	$raw = pg_sitemap_cache_pack(array('created' => 5, 'generation' => 2, 'version' => 'v', 'urls_exist' => true), sitemap_cache_test_content());

	pg_assert_true(pg_page_cache_write($file, $raw), 'written');
	pg_assert_same(sitemap_cache_test_content(), pg_sitemap_cache_unpack((string) file_get_contents($file))['content'], 'read back');
	pg_assert_same(array('sitemap.txt'), array_values(array_diff(scandir($directory), array('.', '..'))), 'no temporary file left');

	@unlink($file);
	@rmdir($directory);
}

function test_sitemap_cache_generation_marked_while_page_cache_off()
{
	pg_assert_same(false, pg_page_cache_file_enabled(), 'page cache off in the test runner');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');
	$start = strpos($source, 'function pg_page_cache_mark_dirty()');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_false(strpos($body, 'pg_page_cache_file_enabled') !== false, 'mark_dirty does not wait for the page cache');
}
