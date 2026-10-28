<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's own icon (includes/fn/site_icon.php): which sources
 * are taken, the addresses the router hands to get_site_icon.php, the head
 * links and what a design's own head already declares, the web manifest,
 * and the sizes the image engine draws from a PNG.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_site_icon_kind()
{
	pg_assert_same('png', pg_site_icon_kind('logo.png'));
	pg_assert_same('png', pg_site_icon_kind(' Logo.PNG '));
	pg_assert_same('svg', pg_site_icon_kind('mark.svg'));
	pg_assert_same('', pg_site_icon_kind('photo.jpg'), 'no PNG can be named from a JPEG');
	pg_assert_same('', pg_site_icon_kind('png'));
	pg_assert_same('', pg_site_icon_kind(''));
}

function test_site_icon_item_size()
{
	pg_assert_same(0, pg_site_icon_item_size('site.webmanifest'));
	pg_assert_same(180, pg_site_icon_item_size('apple-touch-icon.png'));
	pg_assert_same(48, pg_site_icon_item_size('site-icon-48.png'));
	pg_assert_same(512, pg_site_icon_item_size('site-icon-512.png'));
	pg_assert_same(-1, pg_site_icon_item_size('site-icon-64.png'), 'only the drawn sizes');
	pg_assert_same(-1, pg_site_icon_item_size('site-icon-180.png'), '180 is the apple-touch-icon');
	pg_assert_same(-1, pg_site_icon_item_size('favicon.ico'));
	pg_assert_same('/apple-touch-icon.png', pg_site_icon_href(180, '/'));
	pg_assert_same('/site/site-icon-192.png', pg_site_icon_href(192, '/site/'));

	// Every drawn size has an address the router accepts.
	foreach (pg_site_icon_sizes() as $size) {
		pg_assert_same($size, pg_site_icon_item_size(ltrim(pg_site_icon_href($size, '/'), '/')));
	}
}

function test_site_icon_head_html()
{
	$png = pg_site_icon_head_html('png', '/', '/logo.png', 'abcdef0123456789');
	pg_assert_contains('<link rel="icon" type="image/png" sizes="48x48" href="/site-icon-48.png?v=abcdef01">', $png);
	pg_assert_contains('<link rel="icon" type="image/png" sizes="192x192" href="/site-icon-192.png?v=abcdef01">', $png);
	pg_assert_contains('<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=abcdef01">', $png);
	pg_assert_contains('<link rel="manifest" href="/site.webmanifest?v=abcdef01">', $png);

	$svg = pg_site_icon_head_html('svg', '/', '/mark.svg', '');
	pg_assert_contains('<link rel="icon" type="image/svg+xml" href="/mark.svg">', $svg);
	pg_assert_false(strpos($svg, 'apple-touch-icon') !== false, 'no PNG to give iOS');

	$own = pg_site_icon_head_html('png', '/', '/logo.png', '', array('icon', 'manifest'));
	pg_assert_false(strpos($own, 'rel="icon"') !== false, 'the design keeps its own favicon');
	pg_assert_false(strpos($own, 'rel="manifest"') !== false, 'and its own manifest');
	pg_assert_contains('rel="apple-touch-icon"', $own);

	pg_assert_same('', pg_site_icon_head_html('', '/', '', ''), 'no source, no links');
	pg_assert_contains('href="/a&amp;b.svg"', pg_site_icon_head_html('svg', '/', '/a&b.svg', ''));
}

function test_site_icon_declared()
{
	pg_assert_same(array(), pg_site_icon_declared('<html><head><title>x</title></head><body><link rel="icon" href="/x"></body></html>'), 'only the head counts');
	pg_assert_same(array('icon'), pg_site_icon_declared('<head><link rel="shortcut icon" href="/favicon.ico"></head>'));
	pg_assert_same(array('icon', 'apple-touch-icon', 'manifest'), pg_site_icon_declared("<head><LINK REL='icon' href='/a.png'><link href=\"/b.png\" rel=\"apple-touch-icon\"><link rel=manifest href=/m.json></head>"));
	pg_assert_same(array(), pg_site_icon_declared('<head><link rel="stylesheet" href="/iconic.css"><link rel="preload" href="/icon.png"></head>'));
}

function test_site_icon_manifest()
{
	$png = pg_site_icon_manifest('png', 'Kodpen', '/', '/logo.png', 'abcdef0123');
	pg_assert_same('Kodpen', $png['name']);
	pg_assert_same('/', $png['start_url']);
	pg_assert_same('browser', $png['display']);
	pg_assert_same(array(
		array('src' => '/site-icon-192.png?v=abcdef01', 'sizes' => '192x192', 'type' => 'image/png'),
		array('src' => '/site-icon-512.png?v=abcdef01', 'sizes' => '512x512', 'type' => 'image/png'),
	), $png['icons']);

	$svg = pg_site_icon_manifest('svg', '', '/site/', '/site/mark.svg', '');
	pg_assert_false(isset($svg['name']), 'no empty name');
	pg_assert_same(array(array('src' => '/site/mark.svg', 'sizes' => 'any', 'type' => 'image/svg+xml')), $svg['icons']);
}

// The engine draws every size from a large PNG and keeps a small one as it
// is; a JPEG named .png is refused.
function test_site_icon_draw()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
		return;
	}

	$base = sys_get_temp_dir() . '/pg_site_icon_test_' . getmypid();
	@mkdir($base, 0755, true);
	$source = $base . '/logo.png';
	$image = imagecreatetruecolor(600, 600);
	imagefilledrectangle($image, 100, 100, 500, 500, imagecolorallocate($image, 200, 30, 30));
	imagepng($image, $source);

	$result = pg_site_icon_draw($source, $base . '/out', 'stamp-1');
	pg_assert_same(array('ok' => true, 'reason' => ''), $result);

	foreach (pg_site_icon_sizes() as $size) {
		$drawn = getimagesize($base . '/out/icon-' . $size . '.png');
		pg_assert_same(array($size, $size, 'image/png'), array($drawn[0], $drawn[1], $drawn['mime']), $size . ' px');
	}

	pg_assert_same('stamp-1', file_get_contents($base . '/out/stamp.txt'));
	pg_assert_same(array(), glob($base . '/out/*.draw.png'), 'no half-drawn file left');

	$small = $base . '/small.png';
	imagepng(imagecreatetruecolor(32, 32), $small);
	pg_assert_true(pg_site_icon_draw($small, $base . '/small', '')['ok'], 'a small source is kept as it is');
	pg_assert_same(32, getimagesize($base . '/small/icon-512.png')[0]);

	$jpeg = $base . '/photo.png';
	imagejpeg($image, $jpeg);
	pg_assert_same('not_png', pg_site_icon_draw($jpeg, $base . '/jpeg', '')['reason']);

	foreach (array_merge(glob($base . '/*/*'), glob($base . '/*')) as $file) {
		is_dir($file) ? @rmdir($file) : @unlink($file);
	}
	foreach (glob($base . '/*') as $dir) @rmdir($dir);
	@rmdir($base);
}

// The router hands the names over only while an icon is set, before the
// files table is asked; the page head adds the links outside e-mail.
function test_site_icon_wiring()
{
	$router = (string) file_get_contents(PG_FUNCTIONS_DIR . '/router.php');
	$hook = strpos($router, "&& router_site_icon_set()");
	pg_assert_true($hook !== false, 'the router asks whether an icon is set');
	pg_assert_true($hook < strpos($router, '// Check if item is a file.'), 'before the files table');
	pg_assert_contains("SELECT value FROM config_text WHERE name = 'site_icon'", $router);

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("if ((\$email != TRUE) && (stripos(\$content, '</head>') !== false) && function_exists('pg_site_icon_page_head')) {", $page);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/seo.save.php');
	pg_assert_contains("pg_config_text_save('site_icon', \$site_icon_input);", $save);
}
