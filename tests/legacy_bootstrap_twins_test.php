<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the theme parts' designer twins (includes/fn/legacy_bootstrap_twins.php):
 * photo backgrounds, sliders and lightbox links of the starting sites'
 * parts, the parts with no twin, the sheet copies' selectors, a second pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_twins.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');

// A common region's HTML as the bulk move makes it a shared part's tree.
function lbs_twins_tree($html)
{
	$r = pg_di_common_region_tree($html, function () { return array('regionType' => 'common'); }, array('php' => false));
	return $r['tree'];
}

function lbs_twins_html($tree)
{
	return generate_style_code_from_tree($tree, 'x', '');
}

function test_lbs_twin_photo()
{
	// home-3-image-right-strip
	$tree = lbs_twins_tree('<section class="image-square right bg-secondary"><div class="col-md-6 image"><div class="background-image-holder">'
		. '<img alt="image" class="background-image" src="{path}photo-5.jpg" /></div></div></section>');
	$r = pg_lbs_twins($tree);
	pg_assert_same(array('photo' => 1, 'slider' => 0, 'lightbox' => 0), $r['twins']);
	pg_assert_same(array('photo' => 0, 'slider' => 0, 'lightbox' => 0), $r['kept']);
	$html = lbs_twins_html($r['tree']);
	pg_assert_contains('class="legacy-photo fadeIn"', $html);
	pg_assert_contains('background-image: url(&quot;{path}photo-5.jpg&quot;); background-size: cover; background-position: center"', $html);
	pg_assert_false(strpos($html, '<img') !== false, 'the image is the background now');
	pg_assert_false(strpos($html, 'background-image-holder') !== false, 'scripts.js finds no holder to overwrite');
	pg_assert_same($r['tree'], pg_lbs_twins($r['tree'])['tree'], 'a second pass changes nothing');
	pg_assert_same(0, pg_lbs_twins($r['tree'])['twins']['photo']);

	$node = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'background-image-holder', '_attrs' => array(array('name' => 'style', 'value' => 'top: 0'))),
		'children' => array(array('type' => 'content', 'props' => array('contentType' => 'image', 'src' => 'a"b(c).jpg'), 'children' => array())));
	pg_assert_same('top: 0; background-image: url("a%22b%28c%29.jpg"); background-size: cover; background-position: center;', pg_lbs_twin_attr(pg_lbs_twin_photo($node), 'style'), 'its own style kept, the address escaped');

	// A holder beside a video player, or with no image: kept for scripts.js.
	$video = lbs_twins_tree('<section class="cover image-bg vid-bg"><div class="background-image-holder"><div class="player" data-video-id="x"></div></div></section>');
	pg_assert_same(array('photo' => 0, 'slider' => 0, 'lightbox' => 0), pg_lbs_twins($video)['twins']);
	pg_assert_same(1, pg_lbs_twins($video)['kept']['photo']);
	// local-video-container: Play hides the cover through scripts.js.
	$local = lbs_twins_tree('<div class="local-video-container"><div class="background-image-holder"><img alt="x" class="background-image" src="{path}cover20.jpg"></div>'
		. '<video controls=""><source src="{path}video.mp4" type="video/mp4"></video><div class="play-button"></div></div>');
	pg_assert_same(array(0, 1), array(pg_lbs_twins($local)['twins']['photo'], pg_lbs_twins($local)['kept']['photo']));
}

function test_lbs_twin_slider()
{
	// home-agency-2-intro
	$tree = lbs_twins_tree('<section class="kenburns cover fullscreen image-slider slider-all-controls controls-inside"><ul class="slides">'
		. '<li class="image-bg"><div class="background-image-holder"><img alt="image" class="background-image" src="{path}cover33.jpg" /></div><h2>One</h2></li>'
		. '<li class="image-bg"><h2>Two</h2></li></ul></section>');
	$r = pg_lbs_twins($tree, array('prev' => 'Back', 'next' => 'On', 'slide' => 'Picture {var:1}'));
	pg_assert_same(array('photo' => 1, 'slider' => 1, 'lightbox' => 0), $r['twins']);
	$html = lbs_twins_html($r['tree']);
	pg_assert_same(1, preg_match('/<section id="(legacy-slider-[0-9a-f]{8})" class="kenburns cover fullscreen image-slider controls-inside carousel slide">/', $html, $m), 'the trigger class goes, carousel comes');
	$id = $m[1];
	pg_assert_contains('<ul class="slides carousel-inner">', $html);
	pg_assert_contains('<li class="image-bg carousel-item active">', $html);
	pg_assert_contains('<li class="image-bg carousel-item">', $html);
	pg_assert_same(1, substr_count($html, 'carousel-item active'), 'one slide shows first');
	pg_assert_contains('<div class="carousel-indicators">', $html);
	pg_assert_contains('data-bs-target="#' . $id . '" data-bs-slide-to="1" aria-label="Picture 2"', $html);
	pg_assert_contains('<button class="carousel-control-prev" type="button" data-bs-target="#' . $id . '" data-bs-slide="prev" aria-label="Back">', $html);
	pg_assert_contains('<span class="carousel-control-next-icon" aria-hidden="true"></span>', $html);
	pg_assert_false(strpos($html, 'data-bs-ride') !== false, 'it never plays by itself');
	pg_assert_same($r['tree'], pg_lbs_twins($r['tree'])['tree'], 'a second pass changes nothing');

	$paging = pg_lbs_twins(lbs_twins_tree('<div class="slider-paging-controls"><ul class="slides"><li>a</li><li>b</li></ul></div>'));
	$html = lbs_twins_html($paging['tree']);
	pg_assert_contains('carousel-indicators', $html);
	pg_assert_false(strpos($html, 'carousel-control-prev') !== false, 'paging: dots only');
	$arrow = lbs_twins_html(pg_lbs_twins(lbs_twins_tree('<div class="slider-arrow-controls"><ul class="slides"><li>a</li></ul></div>'))['tree']);
	pg_assert_contains('carousel-control-next', $arrow);
	pg_assert_false(strpos($arrow, 'carousel-indicators') !== false, 'arrows only');

	// scrolling-carousel, and a slider whose slides are not a ul of li.
	$logo = pg_lbs_twins(lbs_twins_tree('<div class="logo-carousel"><ul class="slides"><li><a href="#"><img alt="Logo" src="{path}l1.png" /></a></li></ul></div>'));
	pg_assert_same(0, $logo['twins']['slider']);
	pg_assert_same(1, $logo['kept']['slider']);
	pg_assert_contains('class="logo-carousel"', lbs_twins_html($logo['tree']));
	$odd = pg_lbs_twins(lbs_twins_tree('<div class="slider-all-controls"><div class="slides"><p>a</p></div></div>'));
	pg_assert_same(array(0, 1), array($odd['twins']['slider'], $odd['kept']['slider']));
	$own_id = pg_lbs_twin_slider(array('type' => 'semantic', 'props' => array('tag' => 'div', 'id' => 'hero', 'cssClass' => 'slider-arrow-controls'),
		'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'ul', 'cssClass' => 'slides'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'li'), 'children' => array()))))), array('prev' => 'P', 'next' => 'N', 'slide' => 'S'));
	pg_assert_same('#hero', pg_lbs_twin_attr($own_id['children'][1], 'data-bs-target'), 'its own id is the target');
}

function test_lbs_twin_lightbox()
{
	// lightbox-grid: scripts.js gives each link the grid's title as data-lightbox.
	$tree = lbs_twins_tree('<div class="lightbox-grid square-thumbs" data-gallery-title="Gallery"><ul>'
		. '<li><a href="{path}cover1.jpg" data-lightbox="true"><div class="background-image-holder"><img alt="image" class="background-image" src="{path}cover1.jpg" /></div></a></li>'
		. '<li><a href="{path}cover2.jpg"><img alt="image" src="{path}cover2.jpg" /></a></li></ul></div>'
		. '<p><a href="{path}big.jpg" data-lightbox="team">x</a> <a href="/about">About</a></p>');
	$r = pg_lbs_twins($tree);
	pg_assert_same(array('photo' => 1, 'slider' => 0, 'lightbox' => 3), $r['twins']);
	$html = lbs_twins_html($r['tree']);
	pg_assert_contains('<a href="{path}cover1.jpg" data-fancybox="Gallery">', $html);
	pg_assert_contains('<a href="{path}cover2.jpg" data-fancybox="Gallery">', $html);
	pg_assert_contains('data-fancybox="team"', $html, 'a named lightbox keeps its gallery');
	pg_assert_false(strpos($html, 'data-lightbox') !== false);
	pg_assert_contains('<a href="/about">', $html, 'another link stays');
	pg_assert_same($r['tree'], pg_lbs_twins($r['tree'])['tree'], 'a second pass changes nothing');
	pg_assert_same(0, pg_lbs_twins($r['tree'])['twins']['lightbox']);

	pg_assert_same(array('html' => '<a href="a.jpg" data-fancybox="gallery">a</a><a data-fancybox="x y" href="b">', 'count' => 2),
		pg_lbs_twin_html_links('<a href="a.jpg" data-lightbox="true">a</a><a data-lightbox=\'x y\' href="b">'));
	pg_assert_same('<a data-fancybox="gallery">', pg_lbs_twin_html_links('<a data-lightbox>')['html'], 'an attribute with no value');
	pg_assert_same('<p data-lightboxes="1">', pg_lbs_twin_html_links('<p data-lightboxes="1">')['html'], 'a whole attribute name only');
}

function test_lbs_twin_kept_in_html_and_shared_parts()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'html', 'html' => '<div class="tweets-slider"></div><div class="lightbox-grid"></div><a data-lightbox="x" href="a.jpg">a</a>'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array(array('type' => 'semantic', 'props' => array('cssClass' => 'background-image-holder'), 'children' => array()))),
	));
	$r = pg_lbs_twins($tree);
	pg_assert_same(array('photo' => 0, 'slider' => 1, 'lightbox' => 1), $r['kept'], 'HTML text is counted, a placed part is not read');
	pg_assert_same(1, $r['twins']['lightbox'], 'a lightbox link in HTML text becomes a Fancybox link');
	pg_assert_same('<div class="tweets-slider"></div><div class="lightbox-grid"></div><a data-fancybox="x" href="a.jpg">a</a>', $r['tree']['children'][0]['props']['html']);
	pg_assert_same($tree['children'][1], $r['tree']['children'][1]);
	pg_assert_same(array('twins' => array('photo' => 1, 'slider' => 0, 'lightbox' => 2), 'kept' => array('photo' => 0, 'slider' => 1, 'lightbox' => 0)),
		pg_lbs_twins_add(array('twins' => array('photo' => 1, 'lightbox' => 1)), array('twins' => array('lightbox' => 1), 'kept' => array('slider' => 1))));
}

function test_lbs_twin_selectors()
{
	pg_assert_same('.background-image-holder, .legacy-photo ', pg_lbs_twin_selectors('.background-image-holder '));
	pg_assert_same(".kenburns .slides li .background-image-holder,\n.x .background-image-holder.fadeIn, .kenburns .slides li .legacy-photo, .x .legacy-photo.fadeIn",
		pg_lbs_twin_selectors(".kenburns .slides li .background-image-holder,\n.x .background-image-holder.fadeIn"));
	pg_assert_same('.site_modal *:not(.background-image-holder):not(.close-modal)', pg_lbs_twin_selectors('.site_modal *:not(.background-image-holder):not(.close-modal)'), ':not() is not widened');
	pg_assert_same('.parallax > .background-image-holder, .parallax .slides li > .background-image-holder', pg_lbs_twin_selectors('.parallax > .background-image-holder, .parallax .slides li > .background-image-holder'), 'the parallax offsets stay off the twin');
	pg_assert_same('.background-image-holders', pg_lbs_twin_selectors('.background-image-holders'), 'a whole class name only');
	pg_assert_same('a[title="x,y"] .background-image-holder, a[title="x,y"] .legacy-photo', pg_lbs_twin_selectors('a[title="x,y"] .background-image-holder'));

	$css = ".background-image-holder { position: absolute; }\n.background-image-holder img { display: none; }\n@media (max-width: 767px) { .col-sm-6 .background-image-holder { top: 0 } }\n@keyframes kenBurnsEast { from { opacity: 0 } }";
	$once = pg_lbs_css($css, 'pg_lbs_twin_selectors')['css'];
	pg_assert_contains('.background-image-holder, .legacy-photo { position: absolute; }', $once);
	pg_assert_contains('.background-image-holder img, .legacy-photo img { display: none; }', $once);
	pg_assert_contains('.col-sm-6 .background-image-holder, .col-sm-6 .legacy-photo { top: 0 }', $once, 'inside @media too');
	pg_assert_contains('@keyframes kenBurnsEast { from { opacity: 0 } }', $once);
	pg_assert_same($once, pg_lbs_css($once, 'pg_lbs_twin_selectors')['css'], 'a second pass changes nothing');
	pg_assert_same(pg_lbs_css($css)['css'], $css, 'without twins the sheet is renamed only');
}
