<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a page puts in its head (includes/fn/seo.php and its use in
 * get_page_content.php): the head's <title> only, the robots header beside
 * the robots tag, the Product offer's price and availability, og:locale and
 * the page number in a classic list's canonical address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page title goes into the head's <title>; a drawing's <title> in the
// body (an icon's accessible name) is left as it is.
function test_head_title_replace_leaves_svg_titles_alone()
{
	$html = "<html><head><title>Old</title></head><body><svg><title>Close</title></svg><svg><title>Menu</title></svg></body></html>";
	$out = pg_head_title_replace($html, 'New &amp; page');

	pg_assert_same("<html><head><title>New &amp; page</title></head><body><svg><title>Close</title></svg><svg><title>Menu</title></svg></body></html>", $out);
}

// A title written over several lines in the design is still replaced.
function test_head_title_replace_spans_lines()
{
	$html = "<head>\n<title>\n  Old\n</title>\n</head><body></body>";
	pg_assert_same("<head>\n<title>New</title>\n</head><body></body>", pg_head_title_replace($html, 'New'));
}

// A head without a title gets one before </head>; the body's drawing keeps its own.
function test_head_title_replace_adds_a_missing_title()
{
	$html = "<head><meta charset=\"utf-8\"></head><body><svg><title>Close</title></svg></body>";
	pg_assert_same("<head><meta charset=\"utf-8\"><title>New</title></head><body><svg><title>Close</title></svg></body>", pg_head_title_replace($html, 'New'));
}

// Output that is no document (JSON, a fragment) is left as it is.
function test_head_title_replace_leaves_non_documents_alone()
{
	pg_assert_same('{"a":1}', pg_head_title_replace('{"a":1}', 'New'));
	pg_assert_same('<p>x</p>', pg_head_title_replace('<p>x</p>', 'New'));
}

// The design's own title is read from the head, never from a drawing.
function test_head_title_text_reads_the_head_only()
{
	pg_assert_same('Shop', pg_head_title_text("<head><title>Shop</title></head><body><svg><title>Cart</title></svg></body>"));
	pg_assert_same('', pg_head_title_text("<head></head><body><svg><title>Cart</title></svg></body>"));
	pg_assert_same('Two lines', pg_head_title_text("<head><title>\nTwo lines\n</title></head>"));
}

function test_page_content_uses_the_head_title_helpers()
{
	$src = file_get_contents(__DIR__ . '/../pinegrap/get_page_content.php');
	pg_assert_true(strpos($src, "preg_replace('/<title>.*?<\\/title>/i'") === false, 'no page-wide title replace');
	pg_assert_true(strpos($src, 'pg_head_title_replace($content') !== false, 'the head helper writes the title');
	pg_assert_true(strpos($src, 'pg_head_title_text($content)') !== false, 'the head helper reads the title');
}

// noindex goes out as a header as well, so a PDF or JSON answer of the page
// stays out of the index; the other directives stay in the tag.
function test_page_robots_header()
{
	pg_assert_same('X-Robots-Tag: noindex, follow', pg_page_robots_header('noindex, follow'));
	pg_assert_same('X-Robots-Tag: noindex, nofollow', pg_page_robots_header('noindex, nofollow'));
	pg_assert_same('', pg_page_robots_header(''));
	pg_assert_same('', pg_page_robots_header('noarchive, max-snippet:50'));
	pg_assert_same('', pg_page_robots_header('max-image-preview:large'));
}

function test_page_content_sends_the_robots_header()
{
	$src = file_get_contents(__DIR__ . '/../pinegrap/get_page_content.php');
	pg_assert_true(strpos($src, 'pg_page_robots_header($robots_content)') !== false, 'the header is built from the tag');
}

// The offer price everybody gets is the price the page shows, so it is the
// offer's price; a missing or higher one keeps the product's price.
function test_product_jsonld_price_cents()
{
	pg_assert_same(10000, pg_product_jsonld_price_cents(10000, null));
	pg_assert_same(8000, pg_product_jsonld_price_cents(10000, 8000));
	pg_assert_same(8000, pg_product_jsonld_price_cents('10000', '8000'));
	pg_assert_same(10000, pg_product_jsonld_price_cents(10000, 0));
	pg_assert_same(10000, pg_product_jsonld_price_cents(10000, 12000));
	pg_assert_same(10000, pg_product_jsonld_price_cents(10000, 10000));
}

// Stock: an oversold product (quantity below zero) is out of stock too, and
// a product that may be ordered without stock is on back order.
function test_product_jsonld_availability()
{
	pg_assert_same('https://schema.org/InStock', pg_product_jsonld_availability(0, 0, 0), 'no inventory kept');
	pg_assert_same('https://schema.org/InStock', pg_product_jsonld_availability(1, 3, 0));
	pg_assert_same('https://schema.org/OutOfStock', pg_product_jsonld_availability(1, 0, 0));
	pg_assert_same('https://schema.org/OutOfStock', pg_product_jsonld_availability('1', '-2', '0'), 'oversold');
	pg_assert_same('https://schema.org/BackOrder', pg_product_jsonld_availability(1, 0, 1));
	pg_assert_same('https://schema.org/BackOrder', pg_product_jsonld_availability(1, -1, 1));
}

function test_both_product_builders_use_the_offer_helpers()
{
	$page = file_get_contents(__DIR__ . '/../pinegrap/get_page_content.php');
	$widget = file_get_contents(__DIR__ . '/../pinegrap/includes/fn/widgets_catalog.php');

	foreach (array('get_page_content.php' => $page, 'widgets_catalog.php' => $widget) as $name => $src) {
		pg_assert_true(strpos($src, 'pg_product_jsonld_price_cents(') !== false, $name . ' price');
		pg_assert_true(strpos($src, 'pg_product_jsonld_availability(') !== false, $name . ' availability');
	}

	pg_assert_true(strpos($page, 'https://schema.org/PreOrder') === false, 'no PreOrder for a back order');
}

// og:locale follows the language the pages are written in, not the panel's.
function test_og_locale_follows_the_pages_language()
{
	pg_assert_same('tr_TR', pg_og_locale('tr'));
	pg_assert_same('en_US', pg_og_locale('en'));
	pg_assert_same('en_US', pg_og_locale('de'));
	pg_assert_same('tr', pg_og_locale_language('tr', 'en'), 'source language wins');
	pg_assert_same('en', pg_og_locale_language('en', 'tr'), 'source language wins');
	pg_assert_same('tr', pg_og_locale_language('', 'tr'), 'panel language when no source');
	pg_assert_same('en', pg_og_locale_language('', ''), 'English when neither');
}

// A classic list's canonical names a later page by its whole number only.
function test_canonical_page_number()
{
	pg_assert_same(2, pg_canonical_page_number('2'));
	pg_assert_same(12, pg_canonical_page_number(' 12 '));
	pg_assert_same(0, pg_canonical_page_number('1'));
	pg_assert_same(0, pg_canonical_page_number('0'));
	pg_assert_same(0, pg_canonical_page_number('2abc'));
	pg_assert_same(0, pg_canonical_page_number('1.5'));
	pg_assert_same(0, pg_canonical_page_number('-3'));
	pg_assert_same(0, pg_canonical_page_number(array('2')));
	pg_assert_same(0, pg_canonical_page_number(null));
}

function test_page_content_uses_the_canonical_page_number()
{
	$src = file_get_contents(__DIR__ . '/../pinegrap/get_page_content.php');
	pg_assert_true(strpos($src, 'pg_canonical_page_number(') !== false, 'the classic list canonical reads a whole number');
}
