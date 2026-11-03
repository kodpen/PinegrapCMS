<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests how the catalog listing reads its search text: one value only
 * (?query[]=x is no search, not a PHP warning), cut at 100 characters, never
 * inside a multi-byte character, so the search box can print it back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_catalog_search_query_read_guarded_and_by_character()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');

	pg_assert_same(0, preg_match('/isset\(\$_GET\[\$query_param\]\)\) \? trim\(\(string\)/', $source), 'search text cast without is_scalar()');
	pg_assert_same(0, preg_match('/(?<!mb_)substr\(\$search_query, 0, 100\)/', $source), 'search text cut by bytes');
	pg_assert_true(strpos($source, 'mb_substr($search_query, 0, 100)') !== false, 'search text cut by characters');
}

function test_catalog_search_query_read_long_turkish_text_prints()
{
	$text = str_repeat('ş', 60);

	pg_assert_same('', h(substr($text, 0, 101)), 'a cut inside a character prints nothing (the old read)');
	pg_assert_same(str_repeat('ş', 60), h(mb_substr($text, 0, 100)), 'a cut by characters prints the text');
}
