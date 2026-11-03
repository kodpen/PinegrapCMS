<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a content import's media path in
 * includes/import/media.php: the address an <img> shows, the rewrite to the
 * stored file, the full-size picture of a WordPress size variant, the
 * offered file name and the refusal of a fetch over the cap.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/media.php');

function test_import_media_normal()
{
	pg_assert_same('https://old.example/a.jpg', pg_import_media_normal('http://old.example/a.jpg'), 'http asked over https');
	pg_assert_same('https://old.example/a.jpg', pg_import_media_normal('//old.example/a.jpg#top'), 'protocol-relative, fragment dropped');
	pg_assert_same('https://old.example/a.jpg?x=1&y=2', pg_import_media_normal('https://old.example/a.jpg?x=1&amp;y=2'), 'entities decoded');
	pg_assert_same('', pg_import_media_normal('data:image/gif;base64,R0lGOD'), 'data');
	pg_assert_same('', pg_import_media_normal('/wp-content/a.jpg'), 'relative');
	pg_assert_same('', pg_import_media_normal('javascript:alert(1)'), 'script');
}

function test_import_media_original()
{
	pg_assert_same('https://old.example/up/photo.jpg', pg_import_media_original('https://old.example/up/photo-300x200.jpg'), 'size variant');
	pg_assert_same('https://old.example/up/photo.jpg?v=2', pg_import_media_original('https://old.example/up/photo-scaled.jpg?v=2'), 'scaled, query kept');
	pg_assert_same('', pg_import_media_original('https://old.example/up/photo.jpg'), 'no variant');
}

function test_import_media_img_sources()
{
	$html = '<p>One <img src="http://old.example/a-150x150.jpg" srcset="http://old.example/a-150x150.jpg 150w, http://old.example/a.jpg 900w" alt="A"></p>'
		. '<img class="lazy" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" data-src="https://old.example/b.png">'
		. '<img srcset="//cdn.example/c.jpg 1x, //cdn.example/c@2x.jpg 2x">'
		. '<img src="/local.jpg"><IMG SRC=\'https://old.example/a-150x150.jpg\'/>';

	pg_assert_same(array(
		'https://old.example/a-150x150.jpg',
		'https://old.example/b.png',
		'https://cdn.example/c@2x.jpg',
	), pg_import_media_img_sources($html), 'sources');
}

function test_import_media_rewrite()
{
	$html = '<p>One <img class="size-medium" src="http://old.example/a-150x150.jpg" srcset="http://old.example/a.jpg 900w" sizes="(max-width: 150px) 100vw" alt="A &amp; B" width="150"></p>'
		. '<img data-src="https://old.example/b.png" src="data:image/gif;base64,R0lGOD" class="lazy" />'
		. '<img src="https://other.example/x.jpg" alt="kept">';

	$map = array(
		'https://old.example/a-150x150.jpg' => '/a.jpg',
		'https://old.example/b.png'         => '/b[1].png',
	);

	pg_assert_same(
		'<p>One <img src="/a.jpg" class="size-medium" alt="A &amp; B" width="150"></p>'
		. '<img src="/b[1].png" class="lazy" />'
		. '<img src="https://other.example/x.jpg" alt="kept">',
		pg_import_media_rewrite($html, $map),
		'rewritten');

	pg_assert_same($html, pg_import_media_rewrite($html, array()), 'nothing to rewrite');
}

function test_import_media_name()
{
	pg_assert_same('my photo.jpg', pg_import_media_name('https://old.example/up/my%20photo.jpg?x=1'), 'decoded last segment');
	pg_assert_same('image.png', pg_import_media_name('https://old.example/image', 'image/png; charset=binary'), 'extension from the type');
	pg_assert_same('file', pg_import_media_name('https://old.example/'), 'no segment');
}

function test_import_media_refusal_cap()
{
	$max = 1048576;
	$ok = array('ok' => true, 'status' => 200, 'received' => 2048, 'capped' => false, 'write_failed' => false, 'content_type' => 'image/jpeg');

	pg_assert_same('', pg_import_media_refusal($ok, $max, true), 'kept');

	$capped = array('ok' => false, 'status' => 200, 'received' => $max, 'capped' => true, 'error' => 'Failure writing output') + $ok;
	pg_assert_true(pg_import_media_refusal($capped, $max, true) !== '', 'over the cap');

	pg_assert_true(pg_import_media_refusal(array('ok' => false, 'status' => 404) + $ok, $max, false) !== '', 'not found');
	pg_assert_true(pg_import_media_refusal(array('received' => 0) + $ok, $max, false) !== '', 'empty');
	pg_assert_true(pg_import_media_refusal(array('content_type' => 'text/html') + $ok, $max, true) !== '', 'no picture for an img');
	pg_assert_same('', pg_import_media_refusal(array('content_type' => 'application/pdf') + $ok, $max, false), 'an attachment of any type');
}

function test_import_page_name()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/write_page.php');

	pg_assert_same('about-us', pg_import_page_name(array('title' => 'About', 'data' => array('slug' => 'about-us'))), 'slug');
	pg_assert_same('hakkimizda', pg_import_page_name(array('title' => 'Hakkımızda', 'data' => array('slug' => ''))), 'title');
	pg_assert_same('page', pg_import_page_name(array('title' => '', 'data' => array())), 'nothing');
}
