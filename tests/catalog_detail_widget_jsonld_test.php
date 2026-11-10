<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Product structured data of a catalog detail page that draws
 * its product only through a catalog_item_view widget
 * (_pg_catalog_detail_widget_writes_jsonld() in includes/fn/widgets_catalog.php
 * and its use in get_page_content.php): such a page gets the widget's Product
 * block; a page with a real or synthetic System region keeps the classic one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_catalog_detail_without_system_region_uses_widget_jsonld()
{
	$layout = '<html><head></head><body><div class="pg-widget">x</div></body></html>';
	pg_assert_same(true, _pg_catalog_detail_widget_writes_jsonld('catalog detail', $layout), 'no System region: the widget writes it');
}

function test_catalog_detail_with_system_region_keeps_classic_jsonld()
{
	pg_assert_same(false, _pg_catalog_detail_widget_writes_jsonld('catalog detail', '<body><system></system></body>'), 'a plain System region');
	pg_assert_same(false, _pg_catalog_detail_widget_writes_jsonld('catalog detail', '<body><system class="main"></system></body>'), 'a System region with attributes');
	pg_assert_same(false, _pg_catalog_detail_widget_writes_jsonld('catalog detail', '<body><!--pg-comments-block--></body>'), 'a comments block gets a synthetic System region');
}

function test_other_page_types_never_use_the_catalog_detail_rule()
{
	foreach (array('standard', 'catalog', 'form item view', '') as $type) {
		pg_assert_same(false, _pg_catalog_detail_widget_writes_jsonld($type, '<body></body>'), 'page type ' . $type);
	}
}

function test_page_content_asks_the_rule_and_builds_product_jsonld()
{
	$src = file_get_contents(__DIR__ . '/../pinegrap/get_page_content.php');
	pg_assert_true(strpos($src, '_pg_catalog_detail_widget_writes_jsonld($page_type, $content)') !== false, 'get_page_content.php asks the rule');
	pg_assert_true((bool) preg_match('/\$_pg_sw_detail_only\)\s*\?\s*\$canonical_url/', $src), 'the catalog detail case uses the page canonical URL');
	pg_assert_true(substr_count($src, '_pg_build_product_jsonld(') === 1, 'one widget Product builder call serves both page types');
}
