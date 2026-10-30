<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the responsive <img> markup (includes/fn/image.php): which image
 * addresses are files of this site, the derivative addresses and srcset lists
 * written for them, the sizes attribute, the loading rule (first image first,
 * the rest lazy, the node's own choice first) as the designer's renderer
 * applies it, and the e-mail pass that takes the markup out again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_img_local_file_name_reads_site_addresses()
{
	$hosts = array('example.com', 'www.example.com');

	pg_assert_same('photo.jpg', pg_img_local_file_name('/photo.jpg', '/', $hosts), 'root-relative');
	pg_assert_same('photo.jpg', pg_img_local_file_name('photo.jpg', '/', $hosts), 'relative');
	pg_assert_same('photo.jpg', pg_img_local_file_name('{path}photo.jpg', '/', $hosts), 'path placeholder');
	pg_assert_same('photo.jpg', pg_img_local_file_name('https://example.com/photo.jpg', '/', $hosts), 'own host');
	pg_assert_same('photo.jpg', pg_img_local_file_name('//WWW.example.com:8443/photo.jpg', '/', $hosts), 'protocol-relative, case and port');
	pg_assert_same('photo.jpg', pg_img_local_file_name('/photo.jpg?v=3#top', '/', $hosts), 'query and fragment');
	pg_assert_same('my photo.jpg', pg_img_local_file_name('/my%20photo.jpg', '/', $hosts), 'decoded');
	pg_assert_same('photo.jpg', pg_img_local_file_name('/site/photo.jpg', '/site/', $hosts), 'site in a folder');
	pg_assert_same('photo.jpg', pg_img_local_file_name('{path}photo.jpg', '/site/', $hosts), 'placeholder in a folder');
}

function test_img_local_file_name_refuses_the_rest()
{
	$hosts = array('example.com');

	foreach (array(
		'' => 'empty',
		'https://picsum.photos/800/500' => 'another host',
		'/images/photo.jpg' => 'a folder',
		'/other/photo.jpg' => 'outside the site folder',
		'^^__image_url^^' => 'a bound token',
		'{{product_group_image:x}}' => 'a template placeholder',
		'data:image/png;base64,AAAA' => 'data address',
		'/a,b.jpg' => 'a comma, which srcset cannot carry',
		'/a b.jpg' => 'a space',
		'/' => 'the site root',
	) as $src => $label) {
		pg_assert_same('', pg_img_local_file_name($src, '/', $hosts), $label);
	}

	pg_assert_same('', pg_img_local_file_name('/photo.jpg', '/site/', $hosts), 'root address of a site in a folder');
}

function test_img_derivative_url_adds_width_and_format()
{
	pg_assert_same('/p.jpg?w=480', pg_img_derivative_url('/p.jpg', 480, 2000, ''), 'width');
	pg_assert_same('/p.jpg?w=480&f=webp', pg_img_derivative_url('/p.jpg', 480, 2000, 'webp'), 'width and format');
	pg_assert_same('/p.jpg?f=avif', pg_img_derivative_url('/p.jpg', 2000, 2000, 'avif'), 'full size in a format');
	pg_assert_same('/p.jpg', pg_img_derivative_url('/p.jpg', 2000, 2000, ''), 'the file itself');
	pg_assert_same('/p.jpg?v=3&w=960', pg_img_derivative_url('/p.jpg?v=3', 960, 2000, ''), 'after an existing query');
}

function test_img_responsive_sets_lists_widths_below_the_file()
{
	$all = array('webp' => true, 'avif' => true);

	$sets = pg_img_responsive_sets('/p.jpg', 1200, 'jpg', $all);

	pg_assert_same('/p.jpg?w=480 480w, /p.jpg?w=960 960w, /p.jpg 1200w', $sets['srcset'], 'own format, then the file');
	pg_assert_same(array('image/avif', 'image/webp'), array_keys($sets['sources']), 'avif before webp');
	pg_assert_same('/p.jpg?w=480&f=avif 480w, /p.jpg?w=960&f=avif 960w, /p.jpg?f=avif 1200w', $sets['sources']['image/avif'], 'avif up to the full size');

	$big = pg_img_responsive_sets('/p.jpg', 4000, 'jpg', $all);

	pg_assert_same('/p.jpg?w=480 480w, /p.jpg?w=960 960w, /p.jpg?w=1440 1440w, /p.jpg?w=1920 1920w, /p.jpg 4000w', $big['srcset'], 'the original closes the list');
	pg_assert_same('/p.jpg?w=480&f=webp 480w, /p.jpg?w=960&f=webp 960w, /p.jpg?w=1440&f=webp 1440w, /p.jpg?w=1920&f=webp 1920w', $big['sources']['image/webp'], 'a format stops at the widest width');

	$small = pg_img_responsive_sets('/p.png', 300, 'png', array('webp' => true, 'avif' => false));

	pg_assert_same('', $small['srcset'], 'nothing narrower than a small file');
	pg_assert_same(array('image/webp' => '/p.png?f=webp 300w'), $small['sources'], 'only the format');

	$webp = pg_img_responsive_sets('/p.webp', 1000, 'webp', $all);

	pg_assert_same(array('image/avif'), array_keys($webp['sources']), 'no webp source for a webp file');

	$none = pg_img_responsive_sets('/p.jpg', 1000, 'jpg', array('webp' => false, 'avif' => false));

	pg_assert_same(array(), $none['sources'], 'no format this server cannot write');
	pg_assert_same(array('srcset' => '', 'sources' => array(), 'width' => 0), pg_img_responsive_sets('/p.jpg', 0, 'jpg', $all), 'no width');
	pg_assert_same(1200, $sets['width'], 'the file width comes along');
}

function test_img_sizes()
{
	pg_assert_same('48px', pg_img_sizes('48', 1200), 'a pixel width');
	pg_assert_same('320px', pg_img_sizes('320px', 1200), 'with its unit');
	pg_assert_same('(max-width: 1200px) 100vw, 1200px', pg_img_sizes('', 1200), 'the window, at most the file');
	pg_assert_same('(max-width: 900px) 100vw, 900px', pg_img_sizes('50%', 900), 'not in pixels');
	pg_assert_same('(max-width: 900px) 100vw, 900px', pg_img_sizes('0', 900), 'zero');
	pg_assert_same('100vw', pg_img_sizes('', 0), 'no file width');
}

function test_img_sizes_auto_only_when_asked()
{
	pg_assert_same('auto, (max-width: 1200px) 100vw, 1200px', pg_img_sizes('', 1200, true), 'the layout width first');
	pg_assert_same('48px', pg_img_sizes('48', 1200, true), 'a pixel width stays exact');
	pg_assert_true(pg_img_fills_width('card-img-top rounded'), 'card image');
	pg_assert_true(pg_img_fills_width('d-block w-100'), 'w-100');
	pg_assert_false(pg_img_fills_width('img-fluid'), 'img-fluid is only a max-width');
	pg_assert_false(pg_img_fills_width('w-100x mw-100'), 'not a word of it');
}

function test_img_height_given()
{
	pg_assert_true(pg_img_height_given('<img src="/p.jpg" height="300">'), 'a whole-number height attribute');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" height="0">'), 'height 0');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" height="50%">'), 'not a whole number');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" class="w-100">'), 'no height');
	pg_assert_true(pg_img_height_given('<img src="/p.jpg" style="height:300px">'), 'style height');
	pg_assert_true(pg_img_height_given('<img src="/p.jpg" style="color:red; aspect-ratio: 4 / 3">'), 'style aspect-ratio');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" style="max-height:300px">'), 'max-height');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" style="line-height:2;min-height:10px">'), 'line-height and min-height');
	pg_assert_false(pg_img_height_given('<img src="/p.jpg" style="height:auto">'), 'height auto');
}

function test_img_tag_attr()
{
	$tag = '<img src="/p.jpg?a=1&amp;b=2" alt=\'it\'\'s\' loading=lazy class="w-100 x" data-src="/no.jpg">';

	pg_assert_same('/p.jpg?a=1&b=2', pg_img_tag_attr($tag, 'src'), 'double quotes, entities decoded');
	pg_assert_same('lazy', pg_img_tag_attr($tag, 'loading'), 'unquoted');
	pg_assert_same('w-100 x', pg_img_tag_attr($tag, 'class'), 'class');
	pg_assert_same('', pg_img_tag_attr($tag, 'sizes'), 'absent');
	pg_assert_same('', pg_img_tag_attr('<img data-src="/no.jpg">', 'src'), 'data-src is not src');
	pg_assert_same('x', pg_img_tag_attr("<img src='x'>", 'src'), 'single quotes');
}

// The pass over widget output and page bodies, with a stand-in for the file
// lookup.
function test_img_responsive_html_adds_srcset_and_sizes()
{
	$resolve = function ($src) {
		if ($src === '/p.jpg' || $src === '/p.jpg?v=1') {
			return array('srcset' => $src . '?w=480 480w, ' . $src . ' 1200w', 'sources' => array('image/webp' => 'x'), 'width' => 1200);
		}
		return null;
	};

	pg_assert_same(
		'<div><img src="/p.jpg" alt="A" srcset="/p.jpg?w=480 480w, /p.jpg 1200w" sizes="(max-width: 1200px) 100vw, 1200px"></div>',
		pg_img_responsive_html('<div><img src="/p.jpg" alt="A"></div>', $resolve),
		'added before the end of the tag');

	pg_assert_same(
		'<img class="card-img-top" loading="lazy" src="/p.jpg" srcset="/p.jpg?w=480 480w, /p.jpg 1200w" sizes="(max-width: 1200px) 100vw, 1200px" />',
		pg_img_responsive_html('<img class="card-img-top" loading="lazy" src="/p.jpg" />', $resolve),
		'a lazy card image without a height: no auto, self-closing kept');

	pg_assert_same(
		'<img class="card-img-top" loading="lazy" style="aspect-ratio:1/1" src="/p.jpg" srcset="/p.jpg?w=480 480w, /p.jpg 1200w" sizes="auto, (max-width: 1200px) 100vw, 1200px" />',
		pg_img_responsive_html('<img class="card-img-top" loading="lazy" style="aspect-ratio:1/1" src="/p.jpg" />', $resolve),
		'a lazy card image with its ratio: auto first');

	pg_assert_same(
		'<img src="/p.jpg" class="d-block w-100" alt="" loading="lazy" srcset="/p.jpg?w=480 480w, /p.jpg 1200w" sizes="(max-width: 1200px) 100vw, 1200px">',
		pg_img_responsive_html('<img src="/p.jpg" class="d-block w-100" alt="" loading="lazy">', $resolve),
		'a lazy carousel slide without a height: no auto');

	pg_assert_same(
		'<img src="/p.jpg" width="320" sizes="50vw" srcset="/p.jpg?w=480 480w, /p.jpg 1200w">',
		pg_img_responsive_html('<img src="/p.jpg" width="320" sizes="50vw">', $resolve),
		'the author\'s sizes stays');

	foreach (array(
		'<img src="/p.jpg" srcset="/p.jpg 1x">' => 'already has a srcset',
		'<img src="/other.jpg">' => 'not a file of the site',
		'<img src="^^__image_url^^">' => 'a token',
		'<img src="/p.jpg" alt="a " b">' => 'a tag cut short by a quote',
		'<p>no image</p>' => 'no image',
	) as $html => $label) {
		pg_assert_same($html, pg_img_responsive_html($html, $resolve), $label);
	}

	pg_assert_same('<img src="/p.jpg">', pg_img_responsive_html('<img src="/p.jpg">'), 'no database: nothing added');
}

function test_img_picture_wraps_sources()
{
	$html = pg_img_picture('<img src="/p.jpg" alt="">', array('image/webp' => '/p.jpg?f=webp 300w'), '100vw');

	pg_assert_contains('<picture data-pg-picture style="display:contents">', $html, 'out of the layout');
	pg_assert_contains('<source type="image/webp" srcset="/p.jpg?f=webp 300w" sizes="100vw">', $html, 'the source');
	pg_assert_contains('<img src="/p.jpg" alt="">' . "\n" . '</picture>', $html, 'the image last');

	$ratio = pg_img_picture('<img src="/p.jpg" alt="">', array('image/webp' => '/p.jpg?f=webp 300w'), '100vw', '', true);

	pg_assert_contains('<picture data-pg-picture>', $ratio, 'the child of a ratio box keeps its box');
}

function test_img_loading_attrs_first_then_lazy()
{
	$state = &pg_img_page_state();
	$saved = $state;

	try {
		$state = array('active' => false, 'first_seen' => false);
		pg_assert_same(array(), pg_img_loading_attrs('', array()), 'outside a page body nothing is added');
		pg_assert_same(array('loading' => 'lazy'), pg_img_loading_attrs('lazy', array()), 'the node\'s choice still is');

		$state = array('active' => true, 'first_seen' => false);
		pg_assert_same(array('fetchpriority' => 'high'), pg_img_loading_attrs('', array()), 'the first image');
		pg_assert_same(array('loading' => 'lazy', 'decoding' => 'async'), pg_img_loading_attrs('', array()), 'the next');
		pg_assert_same(array('loading' => 'eager'), pg_img_loading_attrs('Eager', array()), 'the node\'s choice');
		pg_assert_same(array(), pg_img_loading_attrs('', array('loading')), 'an attribute the node writes itself');
		pg_assert_same(array('loading' => 'lazy'), pg_img_loading_attrs('', array('decoding')), 'never written twice');

		$state = array('active' => true, 'first_seen' => false);
		pg_assert_same(array('loading' => 'lazy'), pg_img_loading_attrs('lazy', array()), 'a first image the node made lazy');
		pg_assert_same(array('loading' => 'lazy', 'decoding' => 'async'), pg_img_loading_attrs('', array()), 'still counts as the first');

	} finally {
		$state = $saved;
	}
}

// Through the renderer: two Image elements and a bare <img> on a page. With
// no database the files are not looked up, so no srcset; the loading rule
// is the page's.
function test_img_loading_in_a_page_body()
{
	$image = function ($src, $extra = array()) {
		return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => 'A'), $extra));
	};

	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		$image('https://picsum.photos/800/500'),
		$image('/second.jpg'),
		$image('/third.jpg', array('loading' => 'eager')),
		array('type' => 'semantic', 'props' => array('tag' => 'img', 'src' => '/fourth.jpg', 'alt' => 'B')),
		array('type' => 'semantic', 'props' => array('tag' => 'img', 'alt' => 'C', '_attrs' => array(array('name' => 'src', 'value' => '/fifth.jpg'), array('name' => 'loading', 'value' => 'eager')))),
	));

	$html = generate_style_code_from_tree($tree, 'test');

	pg_assert_contains('<img src="https://picsum.photos/800/500" alt="A" fetchpriority="high"', $html, 'the first image is fetched first');
	pg_assert_contains('<img src="/second.jpg" alt="A" loading="lazy" decoding="async"', $html, 'the next waits');
	pg_assert_contains('<img src="/third.jpg" alt="A" loading="eager"', $html, 'the node\'s choice');
	pg_assert_contains('<img src="/fourth.jpg" alt="B" loading="lazy" decoding="async">', $html, 'a bare image too');
	pg_assert_true(substr_count($html, 'loading=') === 4, 'an attribute the node writes is written once');
	pg_assert_false(strpos($html, 'srcset') !== false, 'no database: no srcset');

	$state = pg_img_page_state();
	pg_assert_false($state['active'], 'the rule ends with the body');

	// A tree drawn on its own (a shared component) is not a page body.
	$alone = _render_tree_node($image('/alone.jpg'), 0);
	pg_assert_false(strpos($alone, 'loading=') !== false || strpos($alone, 'fetchpriority') !== false, 'nothing added outside a page body');
}

// The loading attribute outranks props.loading, which is why the editor's
// Loading row writes the attribute: under a start tree's loading="lazy" a
// prop never reached the page. A tree that has only the prop keeps it.
function test_img_loading_attribute_outranks_prop()
{
	$image = function ($src, $extra = array()) {
		return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => 'A'), $extra));
	};
	$attr = function ($value) {
		return array('_attrs' => array(array('name' => 'loading', 'value' => $value)));
	};

	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		$image('/first.jpg', $attr('eager')),
		$image('/second.jpg', array_merge($attr('lazy'), array('loading' => 'eager'))),
		$image('/third.jpg', array('loading' => 'eager')),
	));

	$html = generate_style_code_from_tree($tree, 'test');

	pg_assert_contains('<img src="/first.jpg" alt="A" loading="eager" onerror=', $html, 'the attribute, written once');
	pg_assert_contains('<img src="/second.jpg" alt="A" loading="lazy" onerror=', $html, 'the attribute wins over the prop');
	pg_assert_contains('<img src="/third.jpg" alt="A" loading="eager" onerror=', $html, 'a prop alone still counts');
	pg_assert_same(3, substr_count($html, 'loading='), 'one loading attribute per image');
	pg_assert_false(strpos($html, 'fetchpriority') !== false, 'a first image with its own choice is not raised');
}

// The output stamp versions every local address of a srcset, so a replaced
// file is not answered from a cached derivative.
function test_img_srcset_addresses_are_versioned()
{
	$version_of = function ($url) {
		return in_array($url, array('/p.jpg', '/my photo.jpg'), true) ? 1760000000 : false;
	};

	pg_assert_same(
		'/p.jpg?w=480&amp;v=1760000000 480w, /p.jpg?w=960&amp;f=webp&amp;v=1760000000 960w, /p.jpg?v=1760000000 2400w',
		pg_version_srcset('/p.jpg?w=480 480w, /p.jpg?w=960&amp;f=webp 960w, /p.jpg 2400w', $version_of),
		'derivatives and the file');
	pg_assert_same('https://cdn.example.com/x.jpg 1x, /q.jpg 2x', pg_version_srcset('https://cdn.example.com/x.jpg 1x, /q.jpg 2x', $version_of), 'unknown files are left alone');
	pg_assert_same('/p.jpg?v=5 1x', pg_version_srcset('/p.jpg?v=5 1x', $version_of), 'an explicit version stays');

	$asked = array();
	pg_version_srcset('/a&amp;b.jpg?w=480 480w, /c.png 2x', function ($url) use (&$asked) {
		$asked[] = $url;
		return false;
	});
	pg_assert_same(array('/a&b.jpg', '/c.png'), $asked, 'asked without the query, entities decoded');
}

function test_img_strip_responsive_for_email()
{
	$html = '<p>x</p>'
		. '<picture data-pg-picture style="display:contents">' . "\n"
		. '    <source type="image/avif" srcset="/p.jpg?f=avif 300w" sizes="100vw">' . "\n"
		. '    <img src="/p.jpg" srcset="/p.jpg?w=480 480w, /p.jpg 900w" sizes="auto, 100vw" alt="P" loading="lazy">' . "\n"
		. '</picture>'
		. '<img src="/q.png" alt="Q" srcset=\'/q.png 1x\' sizes=100vw>';

	$stripped = pg_img_strip_responsive($html);

	pg_assert_same('<p>x</p><img src="/p.jpg" alt="P" loading="lazy"><img src="/q.png" alt="Q">', $stripped, 'only the plain images stay');
	pg_assert_same('<p>no images</p>', pg_img_strip_responsive('<p>no images</p>'), 'nothing to do');
	pg_assert_same('<picture><img src="/r.jpg"></picture>', pg_img_strip_responsive('<picture><img src="/r.jpg"></picture>'), 'a picture someone else wrote stays');
}
