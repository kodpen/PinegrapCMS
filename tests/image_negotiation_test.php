<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/image_negotiation.php: whether a request's Accept names
 * a picture type, whether it came through a CDN or proxy, and which format a
 * JPEG or PNG asked for at its own address is answered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/image_negotiation.php');

// What browsers send for an <img>, and the forms the RFC allows.
function test_pg_image_accepts_reads_browser_headers()
{
	$chrome = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8';
	$firefox = 'image/avif,image/webp,image/png,image/svg+xml,image/*;q=0.8,*/*;q=0.5';
	$safari = 'image/webp,image/avif,image/jxl,image/heic,image/heic-sequence,video/*;q=0.8,image/png,image/svg+xml,image/*;q=0.8,*/*;q=0.5';

	pg_assert_true(pg_image_accepts($chrome, 'image/webp'), 'Chrome');
	pg_assert_true(pg_image_accepts($firefox, 'image/webp'), 'Firefox');
	pg_assert_true(pg_image_accepts($safari, 'image/webp'), 'Safari');
	pg_assert_true(pg_image_accepts('IMAGE/WebP', 'image/webp'), 'case');
	pg_assert_true(pg_image_accepts(' image/webp ; q=0.5 , text/html', 'image/webp'), 'spaces and a q-value');
	pg_assert_true(pg_image_accepts('image/webp;q=0.001', 'image/webp'), 'the smallest q above zero');
	pg_assert_true(pg_image_accepts('image/webp;Q=1', 'image/webp'), 'Q in capitals');
}

// q=0 refuses; a wildcard never counts; nothing sent is nothing named.
function test_pg_image_accepts_refusals_and_wildcards()
{
	pg_assert_false(pg_image_accepts('image/webp;q=0', 'image/webp'), 'q=0');
	pg_assert_false(pg_image_accepts('image/webp;q=0.000,image/*', 'image/webp'), 'q=0.000');
	pg_assert_false(pg_image_accepts('image/*', 'image/webp'), 'image/*');
	pg_assert_false(pg_image_accepts('*/*', 'image/webp'), '*/*');
	pg_assert_false(pg_image_accepts('image/png,image/*;q=0.8,*/*;q=0.5', 'image/webp'), 'an older browser');
	pg_assert_false(pg_image_accepts('', 'image/webp'), 'empty');
	pg_assert_false(pg_image_accepts(null, 'image/webp'), 'missing');
	pg_assert_false(pg_image_accepts(array('image/webp'), 'image/webp'), 'not a string');
	pg_assert_false(pg_image_accepts('image/webpx,ximage/webp', 'image/webp'), 'a longer name');
	pg_assert_false(pg_image_accepts('image/webp;q=abc', 'image/webp'), 'a q that is not a number');
	pg_assert_false(pg_image_accepts('image/webp;q=0,image/webp', 'image/webp'), 'the first entry for the type decides');
}

// Each header a CDN or proxy adds on its way to the origin.
function test_pg_image_behind_proxy_headers()
{
	pg_assert_false(pg_image_behind_proxy(array()), 'none');
	pg_assert_false(pg_image_behind_proxy(array('HTTP_ACCEPT' => 'image/webp', 'REMOTE_ADDR' => '203.0.113.5')), 'a direct request');
	pg_assert_true(pg_image_behind_proxy(array('HTTP_CF_RAY' => '8c1f2a3b4c5d6e7f-IST')), 'Cloudflare');
	pg_assert_true(pg_image_behind_proxy(array('HTTP_CDN_LOOP' => 'cloudflare')), 'CDN-Loop');
	pg_assert_true(pg_image_behind_proxy(array('HTTP_VIA' => '1.1 varnish')), 'Via');
	pg_assert_true(pg_image_behind_proxy(array('HTTP_X_AMZ_CF_ID' => 'abc==')), 'CloudFront');
	pg_assert_false(pg_image_behind_proxy(array('HTTP_VIA' => '')), 'an empty header');
	pg_assert_false(pg_image_behind_proxy('HTTP_VIA'), 'not an array');
}

// Bit 4 is the switch and needs bit 1 (WebP offered); bit 8 confirms the CDN.
function test_pg_image_same_address_settings_bits()
{
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_same_address_settings(null), 'no row');
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_same_address_settings(array()), 'no column');
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_same_address_settings(array('image_derivative_formats' => '3')), 'the default');
	pg_assert_same(array('on' => true, 'cdn' => false), pg_image_same_address_settings(array('image_derivative_formats' => '5')), 'WebP and the switch');
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_same_address_settings(array('image_derivative_formats' => '4')), 'the switch without WebP');
	pg_assert_same(array('on' => true, 'cdn' => true), pg_image_same_address_settings(array('image_derivative_formats' => '15')), 'every bit');
	pg_assert_same(array('on' => false, 'cdn' => true), pg_image_same_address_settings(array('image_derivative_formats' => 9)), 'confirmation alone');
	pg_assert_same(array('on' => false, 'cdn' => false), pg_image_same_address_settings(array('image_derivative_formats' => 'x')), 'not a number');
}

function pg_image_negotiation_test_server($accept, $extra = array())
{
	return array_merge(array('HTTP_ACCEPT' => $accept), $extra);
}

// The switch on: a browser naming WebP gets it, every other reader the original,
// and both answers say they vary on Accept.
function test_pg_image_negotiated_format_switch_on()
{
	$row = array('image_derivative_formats' => '7');
	$webp = pg_image_negotiation_test_server('image/avif,image/webp,*/*;q=0.8');
	$other = pg_image_negotiation_test_server('image/png,image/*;q=0.8,*/*;q=0.5');

	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format($row, 'jpg', array(), $webp), 'jpg');
	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format($row, 'jpeg', array(), $webp), 'jpeg');
	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format($row, 'jpe', array(), $webp), 'jpe');
	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format($row, 'png', array(), $webp), 'png');
	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format($row, 'jpg', array('w' => '960'), $webp), 'a width');
	pg_assert_same(array('format' => '', 'vary' => true), pg_image_negotiated_format($row, 'jpg', array(), $other), 'no WebP named');
	pg_assert_same(array('format' => '', 'vary' => true), pg_image_negotiated_format($row, 'jpg', array(), array()), 'no Accept');
}

// What never negotiates: other types, an explicit format, the switch off.
function test_pg_image_negotiated_format_left_alone()
{
	$on = array('image_derivative_formats' => '5');
	$webp = pg_image_negotiation_test_server('image/webp,*/*');
	$none = array('format' => '', 'vary' => false);

	pg_assert_same($none, pg_image_negotiated_format($on, 'gif', array(), $webp), 'gif');
	pg_assert_same($none, pg_image_negotiated_format($on, 'webp', array(), $webp), 'webp');
	pg_assert_same($none, pg_image_negotiated_format($on, 'svg', array(), $webp), 'svg');
	pg_assert_same($none, pg_image_negotiated_format($on, 'pdf', array(), $webp), 'pdf');
	pg_assert_same($none, pg_image_negotiated_format($on, 'jpg', array('f' => 'webp'), $webp), 'f=webp');
	pg_assert_same($none, pg_image_negotiated_format($on, 'jpg', array('f' => 'avif'), $webp), 'f=avif');
	pg_assert_same($none, pg_image_negotiated_format($on, 'jpg', array('f' => 'original'), $webp), 'f=original');
	pg_assert_same($none, pg_image_negotiated_format($on, 'jpg', array('w' => '480', 'f' => 'original'), $webp), 'f=original with a width');
	pg_assert_same($none, pg_image_negotiated_format(array('image_derivative_formats' => '3'), 'jpg', array(), $webp), 'switch off');
	pg_assert_same($none, pg_image_negotiated_format(array('image_derivative_formats' => '4'), 'jpg', array(), $webp), 'switch without WebP');
	pg_assert_same($none, pg_image_negotiated_format(null, 'jpg', array(), $webp), 'no config row');
}

// Through a CDN or proxy the original goes until the operator confirmed
// that the edge keeps a copy per format.
function test_pg_image_negotiated_format_behind_proxy()
{
	$cf = pg_image_negotiation_test_server('image/webp,*/*', array('HTTP_CF_RAY' => '1-IST'));

	pg_assert_same(array('format' => '', 'vary' => true), pg_image_negotiated_format(array('image_derivative_formats' => '5'), 'jpg', array(), $cf), 'unconfirmed');
	pg_assert_same(array('format' => 'webp', 'vary' => true), pg_image_negotiated_format(array('image_derivative_formats' => '13'), 'jpg', array(), $cf), 'confirmed');
}

function test_pg_image_original_asked()
{
	pg_assert_true(pg_image_original_asked(array('f' => 'original')), 'f=original');
	pg_assert_true(pg_image_original_asked(array('f' => ' Original ', 'w' => '960')), 'case, spaces and a width');
	pg_assert_false(pg_image_original_asked(array('f' => 'webp')), 'f=webp');
	pg_assert_false(pg_image_original_asked(array()), 'nothing');
	pg_assert_false(pg_image_original_asked(array('f' => array('original'))), 'not a string');
	pg_assert_false(pg_image_original_asked(null), 'not an array');
}

function test_pg_image_negotiated_download_name()
{
	pg_assert_same('photo.webp', pg_image_negotiated_download_name('photo.jpg', 'webp'), 'jpg');
	pg_assert_same('my.photo.webp', pg_image_negotiated_download_name('my.photo.png', 'webp'), 'two dots');
	pg_assert_same('çiçek.webp', pg_image_negotiated_download_name('çiçek.jpeg', 'webp'), 'a non-ASCII start');
	pg_assert_same('photo.webp', pg_image_negotiated_download_name('photo', 'webp'), 'no extension');
	pg_assert_same('.htaccess.webp', pg_image_negotiated_download_name('.htaccess', 'webp'), 'a leading dot only');
}

// A mail asks for the uploaded bytes of this site's JPEG and PNG pictures.
function test_pg_image_mail_original_url()
{
	$host = 'www.example.com';

	pg_assert_same('/photo.jpg?f=original', pg_image_mail_original_url('/photo.jpg', $host), 'root-relative');
	pg_assert_same('photo.PNG?f=original', pg_image_mail_original_url('photo.PNG', $host), 'relative, capitals');
	pg_assert_same('https://example.com/a/b.jpeg?f=original', pg_image_mail_original_url('https://example.com/a/b.jpeg', $host), 'own host without www');
	pg_assert_same('http://www.example.com:8080/b.jpe?f=original', pg_image_mail_original_url('http://www.example.com:8080/b.jpe', $host), 'own host with a port');
	pg_assert_same('//www.example.com/b.png?f=original', pg_image_mail_original_url('//www.example.com/b.png', $host), 'scheme-relative');
	pg_assert_same('/photo.jpg?w=960&amp;f=original', pg_image_mail_original_url('/photo.jpg?w=960', $host), 'after a query');
	pg_assert_same('/photo.jpg?f=original#top', pg_image_mail_original_url('/photo.jpg#top', $host), 'before a fragment');
	pg_assert_same('/photo.jpg?f=original', pg_image_mail_original_url('/photo.jpg?', $host), 'an empty query');

	pg_assert_same('/photo.jpg?f=webp', pg_image_mail_original_url('/photo.jpg?f=webp', $host), 'a format already named');
	pg_assert_same('/photo.jpg?w=1&amp;f=avif', pg_image_mail_original_url('/photo.jpg?w=1&amp;f=avif', $host), 'a format after &amp;');
	pg_assert_same('https://other.com/photo.jpg', pg_image_mail_original_url('https://other.com/photo.jpg', $host), 'another site');
	pg_assert_same('/photo.gif', pg_image_mail_original_url('/photo.gif', $host), 'a GIF');
	pg_assert_same('/photo.webp', pg_image_mail_original_url('/photo.webp', $host), 'a WebP');
	pg_assert_same('/photo.jpg.zip', pg_image_mail_original_url('/photo.jpg.zip', $host), 'another extension');
	pg_assert_same('cid:photo.jpg', pg_image_mail_original_url('cid:photo.jpg', $host), 'an embedded part');
	pg_assert_same('data:image/png;base64,AAAA', pg_image_mail_original_url('data:image/png;base64,AAAA', $host), 'a data address');
	pg_assert_same('https://example.com/photo.jpg', pg_image_mail_original_url('https://example.com/photo.jpg', ''), 'no host known');
}

function test_pg_image_mail_original_urls()
{
	$host = 'example.com';

	pg_assert_same(
		'<p><img alt="x" src="/a.jpg?f=original" width="10"></p>',
		pg_image_mail_original_urls('<p><img alt="x" src="/a.jpg" width="10"></p>', $host),
		'an img');
	pg_assert_same(
		"<td background='/bg.png?f=original'>",
		pg_image_mail_original_urls("<td background='/bg.png'>", $host),
		'a background attribute');
	pg_assert_same(
		'<div style="color: red; background-image: url(\'/bg.jpg?f=original\')">',
		pg_image_mail_original_urls('<div style="color: red; background-image: url(\'/bg.jpg\')">', $host),
		'url() in a style');
	pg_assert_same(
		'<div style="background:url(&quot;/bg.jpg?f=original&quot;)">',
		pg_image_mail_original_urls('<div style="background:url(&quot;/bg.jpg&quot;)">', $host),
		'url() with &quot;');
	pg_assert_same(
		'<a href="/a.jpg">/a.jpg</a>',
		pg_image_mail_original_urls('<a href="/a.jpg">/a.jpg</a>', $host),
		'a link and text stay');
	pg_assert_same(
		'<style>.x{background:url(/a.jpg)}</style>',
		pg_image_mail_original_urls('<style>.x{background:url(/a.jpg)}</style>', $host),
		'a style block stays');
	pg_assert_same('<p>no pictures</p>', pg_image_mail_original_urls('<p>no pictures</p>', $host), 'nothing to do');
}
