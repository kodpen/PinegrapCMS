<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sitemap_add_stylesheet() and pg_sitemap_stylesheet_css() in
 * includes/fn/seo.php: sitemap.xml names a CSS stylesheet for browsers and
 * stays well-formed XML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A sitemap the way get_sitemap_info() builds it, with an alternate.
function sitemap_style_sample()
{
	return '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
		. '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
		. '    <url>' . "\n"
		. '        <loc>https://example.com/</loc>' . "\n"
		. '        <xhtml:link rel="alternate" hreflang="tr" href="https://example.com/tr/"/>' . "\n"
		. '    </url>' . "\n"
		. '</urlset>';
}

// The instruction follows the declaration on a line of its own, its address
// escaped for XML.
function test_sitemap_style_follows_the_declaration()
{
	$out = pg_sitemap_add_stylesheet(sitemap_style_sample(), '/sitemap.xml?stylesheet=css&v=2026.4.9');

	pg_assert_same(0, strpos($out, '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" . '<?xml-stylesheet type="text/css" href="/sitemap.xml?stylesheet=css&amp;v=2026.4.9"?>' . "\n" . '<urlset '), 'declaration, instruction, urlset');
	pg_assert_same(1, substr_count($out, '<?xml-stylesheet'), 'once');
}

// The result is still a well-formed sitemap, with the instruction in it.
function test_sitemap_style_stays_well_formed()
{
	if (!class_exists('DOMDocument')) {
		return;
	}

	$dom = new DOMDocument();
	pg_assert_true(@$dom->loadXML(pg_sitemap_add_stylesheet(sitemap_style_sample(), '/sitemap.xml?stylesheet=css&v=1')), 'parses');
	pg_assert_same('urlset', $dom->documentElement->localName, 'root');
	pg_assert_same(XML_PI_NODE, $dom->firstChild->nodeType, 'instruction before the root');
	pg_assert_same('xml-stylesheet', $dom->firstChild->target, 'its target');
}

// A quote in the address cannot end the pseudo-attribute.
function test_sitemap_style_escapes_the_address()
{
	$out = pg_sitemap_add_stylesheet(sitemap_style_sample(), '/a"b<c');

	pg_assert_contains('href="/a&quot;b&lt;c"?>', $out, 'escaped');
}

// Without a declaration the instruction comes first; a sitemap that already
// names a stylesheet is left alone.
function test_sitemap_style_without_declaration_or_twice()
{
	pg_assert_same('<?xml-stylesheet type="text/css" href="/s.css"?>' . "\n" . '<urlset/>', pg_sitemap_add_stylesheet('<urlset/>', '/s.css'), 'no declaration');

	$once = pg_sitemap_add_stylesheet(sitemap_style_sample(), '/s.css');
	pg_assert_same($once, pg_sitemap_add_stylesheet($once, '/other.css'), 'not added twice');
}

// The labels reach the stylesheet as CSS strings; quotes, backslashes and
// line breaks cannot break out of them.
function test_sitemap_style_css_labels()
{
	$css = pg_sitemap_stylesheet_css(array(
		'title'    => 'XML Site Haritası',
		'note'     => "Say \"hi\"\nnow \\ ok",
		'count'    => 'Adres sayısı:',
		'lastmod'  => 'Son düzenleme:',
		'priority' => 'Öncelik:',
	));

	pg_assert_contains('content: "XML Site Haritası";', $css, 'title');
	pg_assert_contains('content: "Adres sayısı:" " " counter(pg-sitemap-url) "\A" "Say \"hi\"\A now \\\\ ok";', $css, 'count and note');
	pg_assert_contains('content: "Son düzenleme:" " ";', $css, 'lastmod');
	pg_assert_contains('@namespace xhtml url(http://www.w3.org/1999/xhtml);', $css, 'xhtml namespace for the alternates');
	pg_assert_same(substr_count($css, '{'), substr_count($css, '}'), 'balanced braces');
}
