<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the source an imported <img> keeps (includes/designer_import.php).
 * On a page built for lazy loading, src holds a placeholder (a transparent
 * 1×1 GIF, an empty SVG) and the picture is in a data-src style attribute,
 * in srcset, in a <source> of the <picture> or in the <noscript> copy after
 * the image; the image node has to carry that picture, not the placeholder.
 * A src that is the picture stays as it was. The alt prop is there only when
 * the <img> has the attribute.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

// The placeholder most lazy-loading scripts put in src.
function _test_di_pixel()
{
	return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
}

// A picture embedded as a data: URI, longer than any placeholder.
function _test_di_photo()
{
	return 'data:image/png;base64,' . str_repeat('iVBORw0KGgoAAAANSUhEUgAA', 30);
}

// Every node of a converted tree, depth first.
function _test_di_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) {
			$out = array_merge($out, _test_di_nodes($n['children']));
		}
	}
	return $out;
}

// The image nodes a pasted fragment turns into.
function _test_di_images($html)
{
	$out = pg_designer_import_fragment($html, array('id' => 1));
	$images = array();
	foreach (_test_di_nodes($out['children']) as $n) {
		if (isset($n['props']['contentType']) && $n['props']['contentType'] === 'image') {
			$images[] = $n['props'];
		}
	}
	return $images;
}

// The src of the one image a fragment turns into ('' when it is not one).
function _test_di_src($html)
{
	$images = _test_di_images($html);
	return (count($images) === 1) ? $images[0]['src'] : '';
}

// The names of an image node's raw attributes.
function _test_di_attr_names($props)
{
	$names = array();
	foreach (isset($props['_attrs']) ? $props['_attrs'] : array() as $a) {
		$names[] = $a['name'];
	}
	return $names;
}

function test_designer_import_images_data_uri_src_is_kept()
{
	pg_assert_same(_test_di_photo(), _test_di_src('<img src="' . _test_di_photo() . '" alt="x">'), 'a long data: URI');
	pg_assert_same(_test_di_pixel(), _test_di_src('<img src="' . _test_di_pixel() . '" alt="x">'), 'a placeholder with nothing behind it');
}

function test_designer_import_images_lazy_attribute_wins()
{
	foreach (array('data-src', 'data-lazy-src', 'data-original') as $attr) {
		$images = _test_di_images('<img src="' . _test_di_pixel() . '" ' . $attr . '="' . _test_di_photo() . '" class="lazyload" alt="x">');
		pg_assert_same(1, count($images), $attr . ': one image');
		pg_assert_same(_test_di_photo(), $images[0]['src'], $attr . ': the picture is the source');
		pg_assert_same(array(), _test_di_attr_names($images[0]), $attr . ': the lazy attribute is gone');
		pg_assert_same('lazyload', $images[0]['cssClass'], $attr . ': classes stay');
	}
	// Whatever src holds: the page's script puts the lazy source over it.
	pg_assert_same('photo-large.jpg', _test_di_src('<img src="photo-small.jpg" data-src="photo-large.jpg">'), 'a real src gives way too');
}

function test_designer_import_images_lazy_srcset()
{
	$images = _test_di_images('<img src="' . _test_di_pixel() . '" data-srcset="a.jpg 400w, b.jpg 1200w, c.jpg 800w" data-sizes="auto">');
	pg_assert_same('b.jpg', $images[0]['src'], 'the widest candidate');
	pg_assert_same(array('data-sizes'), _test_di_attr_names($images[0]), 'the lazy srcset is gone');
	pg_assert_same('a.jpg', _test_di_src('<img src="' . _test_di_pixel() . '" data-src="a.jpg" data-srcset="b.jpg 2x">'), 'data-src comes before data-srcset');
}

function test_designer_import_images_srcset_behind_placeholder()
{
	pg_assert_same(_test_di_photo(), _test_di_src('<img src="' . _test_di_pixel() . '" srcset="' . _test_di_photo() . ' 1x" alt="x">'), 'a data: URI in srcset');
	pg_assert_same('l.jpg', _test_di_src('<img srcset="s.jpg 1x, l.jpg 2x, m.jpg 1.5x">'), 'no src: the densest candidate');
	pg_assert_same('b.jpg', _test_di_src('<img src="" srcset="a.jpg 300w, b.jpg 900w">'), 'empty src: the widest candidate');
	pg_assert_same(_test_di_pixel(), _test_di_src('<img src="' . _test_di_pixel() . '" srcset="' . _test_di_pixel() . ' 1x">'), 'a placeholder in srcset is no picture');
}

function test_designer_import_images_real_src_keeps_its_place()
{
	pg_assert_same('photo.jpg', _test_di_src('<img src="photo.jpg" srcset="photo-400.jpg 400w, photo-1600.jpg 1600w">'), 'responsive image');
	pg_assert_same('photo.jpg', _test_di_src('<picture><source srcset="photo.webp" type="image/webp"><img src="photo.jpg"></picture>'), 'picture with a real image');
	pg_assert_same('photo.jpg', _test_di_src('<span><img src="photo.jpg"><noscript><img src="other.jpg"></noscript></span>'), 'noscript after a real image');
	// A flag, not an address.
	$images = _test_di_images('<img src="photo.jpg" data-src="true">');
	pg_assert_same('photo.jpg', $images[0]['src'], 'data-src="true" is not a source');
	pg_assert_same(array('data-src'), _test_di_attr_names($images[0]), 'and it stays an attribute');
}

function test_designer_import_images_picture_source()
{
	$pixel = _test_di_pixel();
	pg_assert_same('wide.jpg', _test_di_src('<picture><source media="(max-width: 600px)" srcset="narrow.jpg"><source srcset="wide.jpg 1600w, mid.jpg 800w"><img src="' . $pixel . '" alt="x"></picture>'), 'the source without a media condition');
	pg_assert_same('narrow.jpg', _test_di_src('<picture><source media="(max-width: 600px)" srcset="narrow.jpg"><source media="(min-width: 601px)" srcset="wide.jpg"><img src="' . $pixel . '"></picture>'), 'every source has one: the first');
	pg_assert_same('lazy.webp', _test_di_src('<picture><source type="image/webp" data-srcset="lazy.webp"><img src="' . $pixel . '"></picture>'), 'a lazy source');
}

function test_designer_import_images_noscript_copy()
{
	$images = _test_di_images('<span style="display:block"><img src="' . _test_di_pixel() . '" alt="x"><noscript><img src="' . _test_di_photo() . '" alt="x"></noscript></span>');
	pg_assert_same(1, count($images), 'the copy is not a second image');
	pg_assert_same(_test_di_photo(), $images[0]['src'], 'the copy\'s src');
	pg_assert_same('big.jpg', _test_di_src('<span><img src="' . _test_di_pixel() . '"><noscript><img srcset="small.jpg 1x, big.jpg 2x"></noscript></span>'), 'the copy\'s srcset');
}

// libxml versions that read <noscript> as raw text leave its <img> as text.
function test_designer_import_images_noscript_copy_as_text()
{
	$doc = new DOMDocument('1.0', 'UTF-8');
	$wrap = $doc->createElement('span');
	$doc->appendChild($wrap);
	$img = $doc->createElement('img');
	$img->setAttribute('src', _test_di_pixel());
	$wrap->appendChild($img);
	$ns = $doc->createElement('noscript');
	$ns->appendChild($doc->createTextNode('<img alt="x" data-src="no.jpg" src="real&amp;1.jpg" srcset="other.jpg 2x">'));
	$wrap->appendChild($ns);
	pg_di_settle_img_sources($doc);
	pg_assert_same('real&1.jpg', $img->getAttribute('src'), 'src read from the text');
	pg_assert_same(0, $wrap->getElementsByTagName('noscript')->length, 'the copy is taken out');
}

function test_designer_import_images_inline_image_in_text()
{
	$out = pg_designer_import_fragment('<p>Hello <img src="' . _test_di_pixel() . '" data-src="photo.jpg" alt="x"> world</p>', array('id' => 1));
	$text = $out['children'][0]['props']['text'];
	pg_assert_contains('src="photo.jpg"', $text, 'the picture');
	pg_assert_false(strpos($text, 'data-src') !== false, 'no lazy attribute left in the markup');
}

function test_designer_import_images_page_import_resolves_lazy_refs()
{
	$ctx = new stdClass();
	$ctx->files = array(
		'images/blank.gif' => array('name' => 'blank.gif', 'url' => '/blank.gif', 'type' => 'asset'),
		'images/photo.jpg' => array('name' => 'photo.jpg', 'url' => '/photo.jpg', 'type' => 'asset'),
	);
	$ctx->warnings = array();
	$ctx->folders = array();
	$ctx->root_id = 0;
	$ctx->project = 'test';
	$page = pg_designer_import_html('<html><body><img src="images/blank.gif" data-src="images/photo.jpg" alt="x"></body></html>', 'index.html', $ctx);
	$images = array();
	foreach (_test_di_nodes($page['tree']['children']) as $n) {
		if (isset($n['props']['contentType']) && $n['props']['contentType'] === 'image') {
			$images[] = $n['props'];
		}
	}
	pg_assert_same(1, count($images), 'one image');
	pg_assert_same('/photo.jpg', $images[0]['src'], 'the archive\'s copy of the lazy source');
}

function test_designer_import_images_srcset_with_data_uris()
{
	$a = 'data:image/png;base64,AAAA';
	$b = 'data:image/png;base64,BBBB';
	pg_assert_same(
		array(array('url' => $a, 'descriptor' => '1x'), array('url' => $b, 'descriptor' => '2x')),
		pg_di_srcset_candidates($a . ' 1x, ' . $b . ' 2x'),
		'the comma inside a data: URI does not split it'
	);
	pg_assert_same(
		array(array('url' => 'a.jpg', 'descriptor' => ''), array('url' => 'b.jpg', 'descriptor' => '2x')),
		pg_di_srcset_candidates(' a.jpg, b.jpg 2x ,, '),
		'a URL ending in a comma, stray commas and spaces'
	);
	pg_assert_same(
		array(array('url' => 'a.jpg,b.jpg', 'descriptor' => '2x')),
		pg_di_srcset_candidates('a.jpg,b.jpg 2x'),
		'a comma inside a URL is part of it, as in a browser'
	);
	pg_assert_same('b.jpg', pg_di_srcset_best('a.jpg 1x, b.jpg 2x'), 'the densest');
	pg_assert_same(_test_di_photo(), pg_di_srcset_best(_test_di_photo() . ' 1x, ' . _test_di_pixel() . ' 2x'), 'a placeholder is passed over');
	pg_assert_same('', pg_di_srcset_best($a . ' 1x, ' . $b . ' 2x'), 'data: URIs too short to be a picture');
	pg_assert_same('', pg_di_srcset_best(''), 'nothing');

	$ctx = new stdClass();
	$ctx->files = array();
	$ctx->base_dir = '';
	$ctx->fragment = true;
	pg_assert_same($a . ' 1x, ' . $b . ' 2x', pg_di_rewrite_srcset($a . ' 1x,' . $b . ' 2x', $ctx), 'rewriting keeps the data: URIs whole');
}

// An imported <img> without an alt attribute keeps no alt prop, so the
// audit asks for one; alt="" stays as the decorative statement it is.
function test_designer_import_images_alt_decision_kept()
{
	$images = array();

	foreach (array('<img src="/a.jpg">', '<img src="/a.jpg" alt="">', '<img src="/a.jpg" alt="Logo">') as $html) {
		$out = pg_designer_import_fragment($html, array('id' => 1));
		$node = isset($out['children'][0]) ? $out['children'][0] : array();
		pg_assert_same('image', isset($node['props']['contentType']) ? $node['props']['contentType'] : '', $html . ' is an image');
		$images[] = isset($node['props']) ? $node['props'] : array();
	}

	pg_assert_false(array_key_exists('alt', $images[0]), 'no attribute, no alt prop');
	pg_assert_same('', $images[1]['alt'], 'alt="" is kept');
	pg_assert_same('Logo', $images[2]['alt'], 'an alt text is kept');
	pg_assert_same('/a.jpg', $images[0]['src'], 'the rest of the image is as before');
}
