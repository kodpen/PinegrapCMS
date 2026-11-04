<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pinegrap logo: the inline wordmark (pg_logo_svg()), the
 * panel lockup (pg_logo_markup(): the mascot's muted loop over its still
 * frame, the wordmark, the name as hidden text), the brand files in
 * assets/images and the old image editor's logo left only to clean_up.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_brand_logo_wordmark_gradient_ids()
{
	$plain = pg_logo_svg();
	$curtain = pg_logo_svg('-pl');

	pg_assert_same(1, substr_count($plain, 'id="pgLogoGradient"'), 'one gradient');
	pg_assert_same(2, substr_count($plain, 'url(#pgLogoGradient)'), 'the line and the dot use it');
	pg_assert_same(1, substr_count($curtain, 'id="pgLogoGradient-pl"'), 'the suffix renames the gradient');
	pg_assert_same(0, substr_count($curtain, 'pgLogoGradient"') + substr_count($curtain, 'pgLogoGradient)'), 'no reference keeps the old id');
	pg_assert_true(strpos($plain, 'var(--pg-logo-color-1, #3D5AFE)') !== false, 'a stop falls back to its colour without the panel stylesheet');
	pg_assert_true(strpos($plain, 'viewBox="-152 -100 958 375"') !== false, 'room above the letters as large as the descenders');
	pg_assert_true(strpos($plain, 'aria-label="Pinegrap"') !== false, 'the wordmark names the software');
}

function test_brand_logo_markup()
{
	$html = pg_logo_markup('-x');

	pg_assert_true((bool) preg_match('/<video class="pinegrap-logo-mark"[^>]* muted loop playsinline preload="none"[^>]*poster="[^"]*pinegrap-mascot-still\.webp"[^>]*data-pg-logo-video>/', $html), 'a muted loop that downloads nothing until started, over its still frame');
	pg_assert_true(strpos($html, 'pinegrap-mascot-loop.webm" type="video/webm"') < strpos($html, 'pinegrap-mascot-loop.mp4" type="video/mp4"'), 'WebM first, MP4 for the rest');
	pg_assert_true(strpos($html, 'id="pgLogoGradient-x"') !== false, 'the wordmark with the suffix');
	pg_assert_true(strpos($html, '<span class="visually-hidden">Pinegrap</span>') !== false, 'the name stays when the wordmark is hidden');
	pg_assert_false((bool) preg_match('/\sautoplay/', $html), 'backend.js starts it, after the motion and data checks');
}

function test_brand_logo_files()
{
	$images = dirname(__DIR__) . '/pinegrap/assets/images/';

	foreach (array('pinegrap-mascot.svg', 'pinegrap-wordmark.svg', 'pinegrap-logo.svg') as $name) {
		$svg = (string) @file_get_contents($images . $name);
		pg_assert_true(strpos($svg, '<svg xmlns="http://www.w3.org/2000/svg"') === 0, $name . ' is an SVG');
		pg_assert_false((bool) preg_match('/<script|\son[a-z]+=|<foreignObject|@keyframes|animation/i', $svg), $name . ' is still and has no script');
	}

	foreach (array('pinegrap-mascot-loop.webm', 'pinegrap-mascot-loop.mp4', 'pinegrap-mascot-still.webp', 'logo.png') as $name) {
		pg_assert_true(is_file($images . $name) && (filesize($images . $name) > 0), $name . ' is there');
	}

	foreach (array('icon-192.png' => 192, 'icon-512.png' => 512, 'icon-maskable-512.png' => 512) as $name => $size) {
		$info = @getimagesize($images . $name);
		pg_assert_true(is_array($info) && ($info[0] === $size) && ($info[1] === $size), $name . ' is ' . $size . ' px square');
	}
}

function test_brand_logo_old_image_editor_logo_only_cleaned_up()
{
	$root = dirname(__DIR__) . '/pinegrap';

	pg_assert_false(is_file($root . '/assets/images/icon_image_editor.png'), 'the old image editor\'s logo is gone');
	pg_assert_true(strpos((string) file_get_contents($root . '/clean_up.php'), "'assets/images/icon_image_editor.png',") !== false, 'clean_up.php removes it from installed sites');

	foreach (array('/includes/fn/content.php', '/frontend.src.js', '/frontend.min.js') as $file) {
		pg_assert_false(strpos((string) file_get_contents($root . $file), 'icon_image_editor.png') !== false, $file . ' no longer shows it');
	}
}
