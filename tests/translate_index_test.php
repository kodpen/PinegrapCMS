<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for whether a page in another language may be offered to the search
 * engines: pg_tr_same_as_source() and pg_tr_write_keeps_verdicts() in
 * includes/translate/store.php; the one rule sitemap.xml and every hreflang
 * alternate follow (pg_tr_index_decide()), the address to page mapping it
 * reads the kept answer by (pg_tr_page_id_of_path()) and the robots meta the
 * answer is taken from (pg_tr_robots_says_noindex()) in
 * includes/translate/seo.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/seo.php');

// The same words, whatever the letter case and the spaces around them.
function test_translate_same_as_source_matches_the_source()
{
	pg_assert_true(pg_tr_same_as_source('LinkedIn', 'LinkedIn'), 'a name');
	pg_assert_true(pg_tr_same_as_source('SEO', 'seo'), 'letter case');
	pg_assert_true(pg_tr_same_as_source('ERP', " ERP\n"), 'spaces around');
	pg_assert_true(pg_tr_same_as_source('Ürün', 'ÜRÜN'), 'letter case beyond ASCII');
	pg_assert_true(pg_tr_same_as_source('<strong>Pinegrap</strong> CMS', '<strong>Pinegrap</strong> CMS'), 'an inline text');
}

// A translation that changed anything is engine output.
function test_translate_same_as_source_rejects_a_translation()
{
	pg_assert_false(pg_tr_same_as_source('Website', 'Web sitesi'), 'translated');
	pg_assert_false(pg_tr_same_as_source('Import HTML or ZIP', 'Import HTML  or ZIP'), 'spacing inside');
	pg_assert_false(pg_tr_same_as_source('<b>Blog</b>', 'Blog'), 'markup dropped');
	pg_assert_false(pg_tr_same_as_source('Blog', ''), 'empty');
}

// "none" never offers, "all" always; "reviewed" only on a kept "index".
function test_translate_index_decide_follows_the_policy()
{
	pg_assert_false(pg_tr_index_decide('none', 1), 'none, even with an index answer');
	pg_assert_true(pg_tr_index_decide('all', null), 'all, with no answer');
	pg_assert_true(pg_tr_index_decide('all', 0), 'all, whatever the page said');
	pg_assert_true(pg_tr_index_decide('reviewed', 1), 'reviewed, answered index');
	pg_assert_false(pg_tr_index_decide('reviewed', 0), 'reviewed, answered noindex');
}

// No kept answer is no: the page's own texts cannot tell what the records,
// the catalog and the components drawn on it say.
function test_translate_index_decide_unknown_is_not_offered()
{
	pg_assert_false(pg_tr_index_decide('reviewed', null), 'not drawn since the answer was dropped');
	pg_assert_false(pg_tr_index_decide('', null), 'an empty policy reads as reviewed');
	pg_assert_false(pg_tr_index_decide('reviewed', '1'), 'only the integer answer counts');
}

// The site root, with or without the slash, and with a query string, is the
// home page; /en/ is not mapped (the source address is what is passed).
function test_translate_index_page_of_the_root_is_home()
{
	$index = array('names' => array('anasayfa' => 7, 'araclar' => 9), 'home' => 7);

	pg_assert_same(7, pg_tr_page_id_of_path('https://www.example.com/', '/', $index), 'root');
	pg_assert_same(7, pg_tr_page_id_of_path('https://www.example.com', '/', $index), 'no path');
	pg_assert_same(7, pg_tr_page_id_of_path('https://www.example.com/?utm=x', '/', $index), 'query string');
	pg_assert_same(7, pg_tr_page_id_of_path('https://www.example.com/anasayfa', '/', $index), 'home by name');
	pg_assert_same(0, pg_tr_page_id_of_path('https://www.example.com/en/', '/', $index), 'a language directory');
	pg_assert_same(0, pg_tr_page_id_of_path('https://www.example.com/', '/', array('names' => array(), 'home' => 0)), 'no home page');
}

// Pages by name, letter case and percent-encoding aside; deeper addresses and
// addresses outside the site's root are no page.
function test_translate_index_page_of_a_name()
{
	$index = array('names' => array('araclar' => 9, 'ürün' => 11), 'home' => 7);

	pg_assert_same(9, pg_tr_page_id_of_path('https://www.example.com/araclar', '/', $index), 'name');
	pg_assert_same(9, pg_tr_page_id_of_path('https://www.example.com/Araclar/', '/', $index), 'letter case, closing slash');
	pg_assert_same(11, pg_tr_page_id_of_path('https://www.example.com/%C3%BCr%C3%BCn', '/', $index), 'percent-encoded');
	pg_assert_same(0, pg_tr_page_id_of_path('https://www.example.com/araclar/item-1', '/', $index), 'below a page');
	pg_assert_same(0, pg_tr_page_id_of_path('https://www.example.com/yok', '/', $index), 'no such page');
}

// A site installed below the web root.
function test_translate_index_page_of_a_subfolder_install()
{
	$index = array('names' => array('araclar' => 9), 'home' => 7);

	pg_assert_same(7, pg_tr_page_id_of_path('https://example.com/site/', '/site/', $index), 'root');
	pg_assert_same(7, pg_tr_page_id_of_path('https://example.com/site', '/site/', $index), 'root without the slash');
	pg_assert_same(9, pg_tr_page_id_of_path('https://example.com/site/araclar', '/site/', $index), 'name');
	pg_assert_same(0, pg_tr_page_id_of_path('https://example.com/araclar', '/site/', $index), 'outside the root');
	pg_assert_same(0, pg_tr_page_id_of_path('https://example.com/sitex', '/site/', $index), 'a longer name');
}

// The answer kept is the robots meta as the page goes out.
function test_translate_index_robots_says_noindex()
{
	pg_assert_true(pg_tr_robots_says_noindex('<head><meta name="robots" content="noindex, follow"></head>'), 'noindex, follow');
	pg_assert_true(pg_tr_robots_says_noindex('<meta name="robots" content="NOINDEX">'), 'capitals');
	pg_assert_true(pg_tr_robots_says_noindex('<meta name="robots" content="none">'), 'none');
	pg_assert_false(pg_tr_robots_says_noindex('<meta name="robots" content="index, follow, max-snippet:-1">'), 'index');
	pg_assert_false(pg_tr_robots_says_noindex('<meta name="robots" content="noarchive">'), 'noarchive only');
	pg_assert_false(pg_tr_robots_says_noindex('<head><title>noindex</title></head>'), 'no robots meta');
}

// A reviewed translation, or the first translation of a text, keeps the
// pages' "index" answers; overwriting a translation with a machine one, or
// forcing it over a reviewed one, drops them.
function test_translate_index_write_keeps_verdicts()
{
	pg_assert_true(pg_tr_write_keeps_verdicts('reviewed', 'machine'), 'reviewed over machine');
	pg_assert_true(pg_tr_write_keeps_verdicts('reviewed', null), 'reviewed, first');
	pg_assert_true(pg_tr_write_keeps_verdicts('machine', null), 'machine, first');
	pg_assert_true(pg_tr_write_keeps_verdicts('machine', ''), 'machine, no row (empty answer)');
	pg_assert_true(pg_tr_write_keeps_verdicts('machine', false), 'machine, no row (false)');
	pg_assert_false(pg_tr_write_keeps_verdicts('machine', 'machine'), 'machine over machine');
	pg_assert_false(pg_tr_write_keeps_verdicts('machine', 'reviewed'), 'machine forced over reviewed');
}
