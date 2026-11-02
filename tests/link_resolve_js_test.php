<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The link resolver's browser twin (assets/js/pg_link_resolve.js,
 * window.PGLinkResolve) answers as the PHP resolver
 * (includes/fn/link_resolve.php) does: the same hrefs, site values and name
 * maps go through both, and every answer (type, id, class, the item name)
 * must be the same. Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function link_resolve_js_test_maps()
{
	return array(
		'pages' => array(
			'home' => array('id' => 1, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'About' => array('id' => 2, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'shop' => array('id' => 3, 'type' => 'catalog', 'layout_type' => 'custom', 'folder' => 1),
			'blog' => array('id' => 4, 'type' => 'form list view', 'layout_type' => 'custom', 'folder' => 1),
			'events' => array('id' => 6, 'type' => 'calendar view', 'layout_type' => 'custom', 'folder' => 1),
			'about us' => array('id' => 7, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'old-blog' => array('id' => 8, 'type' => 'form list view', 'layout_type' => 'system', 'folder' => 1),
			'ürünler' => array('id' => 9, 'type' => 'catalog detail', 'layout_type' => 'custom', 'folder' => 2),
			'2024' => array('id' => 11, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
		),
		'files' => array('logo.png' => 10, 'Brochure.PDF' => 12),
		'short_links' => array('promo' => true),
		'products' => array('red-shirt' => 20),
		'product_groups' => array('shirts' => 30),
		'home_page_id' => 1,
	);
}

function link_resolve_js_test_hrefs()
{
	return array(
		'', '   ', ' /about ', '#top', '#', 'mailto:a@example.com', 'MAILTO:a@example.com', 'tel:+905551112233', 'sms:123',
		'javascript:void(0)', 'data:text/plain,hi', 'ftp://example.com/x', '?page=2', '?q=1#x',
		'http://example.com', 'https://example.com/', 'https://EXAMPLE.com/about', 'https://example.com:8080/about',
		'https://user:pass@example.com/about', 'https://other.org/about', '//example.com/about', '//other.org/x',
		'http:///about', 'http://', '//', '/', '/index.php', 'index.php', '/about', '/About', '/ABOUT/', 'about',
		'/about%20us', '/about us', '/%C3%BCr%C3%BCnler', '/ürünler/red-shirt', '/a+b',
		'/logo.png', '/LOGO.PNG', '/brochure.pdf', '/promo', '/PROMO', '/nothing', '/nothing/else',
		'/shop/red-shirt', '/shop/shirts', '/shop/nothing', '/shop/', '/shop//', '/Shop/Red-Shirt',
		'/blog/hello-world', '/blog/Hello-World', '/blog/nothing', '/old-blog/anything', '/old-blog/red-shirt',
		'/events/2026-11-02', '/home/x/y/', '/2024', '/2024/x', '/robots.txt', '/Sitemap.xml',
		'/pinegrap/', '/pinegrap/edit.php', '/pinegrapx/a', '/about?x=1#y', '/about#team', 'about/', '///about',
	);
}

// Runs the twin in Node over every case; null when Node cannot run.
function link_resolve_js_test_node($cases, $extra)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_link_resolve.js') . "\n"
		. 'var R = window.PGLinkResolve;' . "\n"
		. 'var cases = ' . json_encode($cases, JSON_UNESCAPED_UNICODE) . ";\n"
		. 'var out = cases.map(function (one) {' . "\n"
		. '    var maps = one.fold ? R.fold(one.maps) : one.maps;' . "\n"
		. '    var forms = one.lazy ? function () { return one.forms; } : one.forms;' . "\n"
		. '    var parsed = R.parse(one.href, one.env);' . "\n"
		. '    return { result: R.resolve(one.href, maps, one.env, forms), item: parsed.item };' . "\n"
		. "});\n"
		. $extra . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pglink');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Every href, under each site path and host, with and without the folded
// index, with form names given at once and by a function: the twin answers
// what pg_link_resolve() answers, and pg_link_parse() reads the same item.
// Left out: a %-escape that is no UTF-8 (PHP reads raw bytes, which no
// JavaScript string holds; both answer unknown).
function test_link_resolve_js_answers_as_the_php_resolver()
{
	$envs = array(
		array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		array('host' => 'example.com:8080', 'path' => '/site/', 'software_directory' => 'pinegrap'),
		array('host' => '', 'path' => '/', 'software_directory' => null),
		array('host' => 'Example.COM', 'path' => '', 'software_directory' => 'pinegrap'),
	);
	$forms = array('hello-world' => true);
	$cases = array();
	$expected = array();

	foreach ($envs as $e => $env) {
		foreach (link_resolve_js_test_hrefs() as $h => $href) {
			$fold = (($e + $h) % 2) === 0;
			$lazy = (($e + $h) % 3) === 0;
			$maps = link_resolve_js_test_maps();
			$href = (strpos($href, '/site/') === false && $env['path'] === '/site/' && strpos($href, '/') === 0 && strpos($href, '//') !== 0) ? '/site' . $href : $href;

			$cases[] = array('href' => $href, 'env' => $env, 'maps' => $maps, 'fold' => $fold, 'lazy' => $lazy, 'forms' => $forms);
			$parsed = pg_link_parse($href, $env);
			$expected[] = array(
				'result' => pg_link_resolve($href, $fold ? pg_link_maps_fold($maps) : $maps, $env, $lazy ? function () use ($forms) { return $forms; } : $forms),
				'item' => $parsed['item'],
			);
		}
	}

	$result = link_resolve_js_test_node($cases, '');

	if ($result === null) {
		return;
	}

	pg_assert_same(count($expected), count($result), 'one answer per case');

	foreach ($expected as $i => $one) {
		pg_assert_same($one, isset($result[$i]) ? $result[$i] : null, 'the same answer for ' . json_encode($cases[$i]['href'], JSON_UNESCAPED_UNICODE) . ' at ' . json_encode($cases[$i]['env']['path']));
	}
}

// The names a set of items is looked up by, and a folded map's keys, are
// the same as pg_link_lookup_names() and pg_link_maps_fold() give.
function test_link_resolve_js_lookup_names_and_fold()
{
	$items = array('about', 'shop/red-shirt', 'shop/shirts/', 'blog/hello-world', 'about', '', 'ürünler/x', 'a//b');
	$extra = 'out = { names: R.lookupNames(' . json_encode($items, JSON_UNESCAPED_UNICODE) . '), folded: R.fold(' . json_encode(link_resolve_js_test_maps(), JSON_UNESCAPED_UNICODE) . ').folded };';
	$result = link_resolve_js_test_node(array(), $extra);

	if ($result === null) {
		return;
	}

	pg_assert_same(pg_link_lookup_names($items), $result['names'], 'the same lookup names');

	$folded = pg_link_maps_fold(link_resolve_js_test_maps());

	// Compared without order: JavaScript lists an index key ('2024') first.
	foreach ($folded['folded'] as $kind => $index) {
		$got = isset($result['folded'][$kind]) ? array_map('strval', $result['folded'][$kind]) : array();
		$index = array_map('strval', $index);
		ksort($index);
		ksort($got);
		pg_assert_same($index, $got, 'the same folded ' . $kind);
	}
}

// The twin is a bundle of its own.
function test_link_resolve_js_is_bundled()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_link_resolve.js'), isset($bundles['assets/js/pg_link_resolve.min.js']) ? $bundles['assets/js/pg_link_resolve.min.js'] : null, 'the twin has its bundle');
}
