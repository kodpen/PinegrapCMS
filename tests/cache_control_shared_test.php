<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_cache_control_shared_safe() (includes/http_range.php) and a
 * guard over the product tree: an answer drawn for the signed-in person or
 * the visitor is never sent with a Cache-Control a shared cache (a CDN or a
 * proxy) may keep, and a screen that replaces the session's no-store keeps
 * private or no-store in its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_cache_control_shared_safe_values()
{
	pg_assert_true(pg_cache_control_shared_safe('no-store'), 'no-store');
	pg_assert_true(pg_cache_control_shared_safe('private, no-cache'), 'private, no-cache');
	pg_assert_true(pg_cache_control_shared_safe('Private, Max-Age=240'), 'case');
	pg_assert_true(pg_cache_control_shared_safe('no-store, no-cache, must-revalidate'), 'the session limiter');
	pg_assert_true(pg_cache_control_shared_safe(' max-age=0 ,private'), 'spaces and order');

	pg_assert_false(pg_cache_control_shared_safe(''), 'missing');
	pg_assert_false(pg_cache_control_shared_safe('max-age=0'), 'max-age=0 alone may be stored');
	pg_assert_false(pg_cache_control_shared_safe('no-cache, must-revalidate'), 'no-cache alone may be stored');
	pg_assert_false(pg_cache_control_shared_safe('public, max-age=604800'), 'public');
	pg_assert_false(pg_cache_control_shared_safe('private="Set-Cookie", max-age=60'), 'private with field names keeps the body shareable');
	pg_assert_false(pg_cache_control_shared_safe('x-private, max-age=60'), 'a token that only contains the word');
}

// Answers that are the same for every visitor and may be kept by a shared
// cache. get_file.php decides per file and is checked on its own.
function cache_control_shared_test_public_files()
{
	return array(
		'connect_client.php',
		'get_file.php',
		'get_site_icon.php',
		'get_sitemap.xml.php',
		'manifest_icon.php',
	);
}

function test_cache_control_literal_headers_keep_shared_caches_out()
{
	$root = PG_FUNCTIONS_DIR;
	$skip = '#/(includes/(dompdf|phpmailer|vendor|htmlpurifier)|vendor|install)/#i';
	$found = array();

	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if ((substr($path, -4) !== '.php') || preg_match($skip, $path)) {
			continue;
		}

		$relative = ltrim(substr($path, strlen(str_replace('\\', '/', $root))), '/');

		if (in_array($relative, cache_control_shared_test_public_files(), true)) {
			continue;
		}

		// header('Cache-Control: ...') written out as a literal; a second
		// line added with replace = false joins the first and is not judged
		// alone.
		if (!preg_match_all('/header\(\s*([\'"])Cache-Control:\s*([^\'"]*)\1\s*\)/i', (string) file_get_contents($path), $m)) {
			continue;
		}

		foreach ($m[2] as $value) {
			if (!pg_cache_control_shared_safe($value)) {
				$found[] = $relative . ': ' . $value;
			}
		}
	}

	pg_assert_same(array(), $found, 'literal Cache-Control headers without private or no-store');
}
