<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_same_as_source() in includes/translate/store.php: a machine
 * translation that is the source text itself does not count as engine output
 * when a page decides whether the search engines may index it.
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
