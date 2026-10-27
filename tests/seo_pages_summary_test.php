<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_seo_pages_summary() in includes/fn/seo.php: the Pages screen's
 * SEO summary counts each number under the condition of the list it links
 * to, so the site map counts read public pages only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _seo_pages_summary_row($sitemap, $search, $flags, $checked, $public)
{
	return array('sitemap' => $sitemap, 'page_search' => $search, 'seo_flags' => $flags, 'seo_checked_at' => $checked, 'public' => $public);
}

// A private page marked for the site map is not in it: neither it nor its
// missing title or description is counted there.
function test_seo_pages_summary_site_map_reads_public_pages()
{
	$summary = pg_seo_pages_summary(array(
		_seo_pages_summary_row(1, 0, 1 | 32, 100, true),
		_seo_pages_summary_row(1, 0, 1 | 32, 100, false),
		_seo_pages_summary_row(1, 0, 0, 100, true),
		_seo_pages_summary_row(0, 0, 1, 100, true),
	));

	pg_assert_same(2, $summary['in_sitemap']);
	pg_assert_same(1, $summary['sitemap_no_title']);
	pg_assert_same(1, $summary['sitemap_no_description']);
}

// The other counts read every page the list shows, private ones included.
function test_seo_pages_summary_other_counts()
{
	$summary = pg_seo_pages_summary(array(
		_seo_pages_summary_row(0, 1, 512, 0, false),
		_seo_pages_summary_row(0, 1, 1024 | 131072, 100, true),
		_seo_pages_summary_row(0, 0, 131072 | 524288, 0, false),
	));

	pg_assert_same(2, $summary['in_search']);
	pg_assert_same(2, $summary['search_weak_keywords']);
	pg_assert_same(2, $summary['not_calculated']);
	pg_assert_same(2, $summary['no_h1']);
	pg_assert_same(1, $summary['img_no_alt']);
	pg_assert_same(0, $summary['in_sitemap']);
}

function test_seo_pages_summary_empty()
{
	$summary = pg_seo_pages_summary(array());

	pg_assert_same(8, count($summary));
	pg_assert_same(0, array_sum($summary));
}

// The screen leaves archived pages out of the rows, as its lists do, and
// no longer counts over the whole page table.
function test_seo_pages_summary_screen_rows()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');
	$start = strpos($source, '$seo_summary = null;');
	$end = strpos($source, "\$output_seo_summary = '';");
	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end));
	$block = substr($source, $start, $end - $start);

	pg_assert_contains("(folder.folder_archived = '0') OR (folder.folder_archived IS NULL)", $block);
	pg_assert_contains("get_access_control_type(\$folder_id) == 'public'", $block);
	pg_assert_contains('pg_seo_pages_summary(', $block);
	pg_assert_false(strpos($source, 'SUM(sitemap = 1)') !== false);
}
