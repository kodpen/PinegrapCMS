<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Publish check's weight measure (includes/fn/
 * publish_weight.php): the bytes of a rendered page's markup, styles,
 * scripts and first-screen pictures, a picture larger than twice its drawn
 * size or without width and height, scripts that hold the first paint, the
 * rules of the design's stylesheets no page uses, the budget's grades, and
 * the findings in the Publish check's shape.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/publish_weight.php');

// A rendered page as the editor's audit render prints it (data-pg-node).
function publish_weight_test_page()
{
	return '<!DOCTYPE html><html><head><title>T</title>'
		. '<link rel="stylesheet" href="/css/site.css?v=3">'
		. '<link rel="preload" href="/fonts/a.woff2" as="font">'
		. '<style>.x{color:red}</style>'
		. '<script src="/js/head.js"></script>'
		. '<script src="/js/late.js" defer></script>'
		. '<script type="application/ld+json">{"a":1}</script>'
		. '</head><body>'
		. '<div data-pg-node="n1"><img src="/files/hero.jpg" width="600" height="400" alt="h"></div>'
		. '<img data-pg-node="n2" src="/files/logo.png" alt="l">'
		. '<img data-pg-node="n3" src="/files/below.jpg" width="300" height="200" loading="lazy" alt="b">'
		. '<img data-pg-node="n4" src="/files/icon.svg" alt="i">'
		. '<script>var a=1;</script>'
		. '<script src="https://cdn.example.com/x.js"></script>'
		. '</body></html>';
}

function publish_weight_test_files()
{
	return array(
		'/css/site.css' => 20000,
		'/js/head.js' => 30000,
		'/js/late.js' => 10000,
		'/files/hero.jpg' => array('bytes' => 500000, 'width' => 2400, 'height' => 1600),
		'/files/logo.png' => array('bytes' => 4000, 'width' => 200, 'height' => 80),
		'/files/below.jpg' => array('bytes' => 90000, 'width' => 600, 'height' => 400),
		'/files/icon.svg' => 1000,
	);
}

function test_publish_weight_urls_lists_the_files_a_page_loads_once_each()
{
	$urls = pg_publish_weight_urls(publish_weight_test_page());

	pg_assert_same(array('/css/site.css', '/js/head.js', '/js/late.js', 'https://cdn.example.com/x.js',
		'/files/hero.jpg', '/files/logo.png', '/files/below.jpg', '/files/icon.svg'), $urls);
}

function test_publish_weight_measure_counts_markup_styles_scripts_and_first_screen_pictures()
{
	$html = publish_weight_test_page();
	$m = pg_publish_weight_measure($html, publish_weight_test_files());

	$inline_style = strlen('.x{color:red}');
	$inline_script = strlen('var a=1;');

	pg_assert_same(strlen($html) - $inline_style - $inline_script, $m['bytes']['markup'], 'markup without inline style and script');
	pg_assert_same(20000 + $inline_style, $m['bytes']['styles']);
	pg_assert_same(30000 + 10000 + $inline_script, $m['bytes']['scripts'], 'a file of unknown size adds nothing');
	pg_assert_same(500000 + 4000 + 1000, $m['bytes']['images'], 'a lazy picture is not on the first screen');
	pg_assert_same($m['bytes']['markup'] + $m['bytes']['styles'] + $m['bytes']['scripts'] + $m['bytes']['images'], $m['bytes']['total']);
	pg_assert_same(array('https://cdn.example.com/x.js'), $m['unknown']);
}

function test_publish_weight_measure_finds_oversized_and_unsized_pictures()
{
	$m = pg_publish_weight_measure(publish_weight_test_page(), publish_weight_test_files());

	pg_assert_same(1, count($m['oversized']));
	pg_assert_same('/files/hero.jpg', $m['oversized'][0]['src']);
	pg_assert_same('n1', $m['oversized'][0]['node'], 'the nearest element with data-pg-node');
	pg_assert_same(2400, $m['oversized'][0]['width']);
	pg_assert_same(600, $m['oversized'][0]['drawn_width']);

	$unsized = array_map(function ($i) { return $i['node']; }, $m['unsized']);
	pg_assert_same(array('n2', 'n4'), $unsized, 'without width and height, SVG included');
}

function test_publish_weight_measure_twice_its_drawn_size_is_not_oversized()
{
	$html = '<html><body><img src="/a.jpg" width="500" height="500"><img src="/b.jpg" height="100" width="x"></body></html>';
	$m = pg_publish_weight_measure($html, array('/a.jpg' => array('bytes' => 1, 'width' => 1000, 'height' => 1000),
		'/b.jpg' => array('bytes' => 1, 'width' => 900, 'height' => 201)));

	pg_assert_same(1, count($m['oversized']), 'exactly twice stays');
	pg_assert_same('/b.jpg', $m['oversized'][0]['src'], 'measured by the height when the width is no number');
	pg_assert_same(1, count($m['unsized']), 'a width that is no number counts as missing');
}

function test_publish_weight_measure_lists_scripts_that_hold_the_first_paint()
{
	$m = pg_publish_weight_measure(publish_weight_test_page(), publish_weight_test_files());

	pg_assert_same(array('/js/head.js'), array_map(function ($s) { return $s['src']; }, $m['blocking']),
		'deferred, JSON and body scripts do not hold it');

	$m = pg_publish_weight_measure('<html><head><script src="/a.js" async></script><script type="module" src="/m.js"></script>'
		. '<script src="/b.js"></script></head><body></body></html>', array());
	pg_assert_same(array('/b.js'), array_map(function ($s) { return $s['src']; }, $m['blocking']));
}

function test_publish_weight_measure_of_no_page_is_empty()
{
	$m = pg_publish_weight_measure('', array());

	pg_assert_same(0, $m['bytes']['total']);
	pg_assert_same(array(), $m['oversized']);
}

function test_publish_weight_grade_against_the_budget()
{
	$budget = pg_publish_weight_budget();

	foreach (array('markup', 'styles', 'scripts', 'images', 'total') as $k) {
		pg_assert_true(isset($budget[$k]) && is_int($budget[$k]) && $budget[$k] > 0, 'budget ' . $k);
	}

	$small = array('markup' => 10, 'styles' => 10, 'scripts' => 10, 'images' => 10, 'total' => 40);
	$g = pg_publish_weight_grade($small, $budget);
	pg_assert_same('green', $g['state']);

	$over = $small;
	$over['scripts'] = $budget['scripts'] + 1;
	$g = pg_publish_weight_grade($over, $budget);
	pg_assert_same('amber', $g['measures']['scripts']);
	pg_assert_same('amber', $g['state']);

	$over['images'] = 2 * $budget['images'] + 1;
	$g = pg_publish_weight_grade($over, $budget);
	pg_assert_same('red', $g['measures']['images']);
	pg_assert_same('red', $g['state'], 'the worst measure');

	$at = $small;
	$at['markup'] = $budget['markup'];
	pg_assert_same('green', pg_publish_weight_grade($at, $budget)['measures']['markup'], 'at the budget is green');
}

function test_publish_weight_used_names_reads_classes_and_ids()
{
	$used = pg_publish_weight_used_names('<div class="a  b" id="top"><p class=\'c\'>x</p><span class=d></span></div>');

	pg_assert_same(array('.a', '.b', '#top', '.c', '.d'), array_keys($used));
}

function test_publish_weight_unused_rules_lists_rules_no_page_uses()
{
	$css = '/* .gone{} */ .hero{padding:1rem} .gone, .lost .x{color:red} .hero .gone{margin:0}'
		. ' @media (max-width: 600px){ .lost{display:none} .hero:hover{opacity:.5} }'
		. ' @font-face{font-family:A;src:url(a.woff2)} @keyframes k{from{opacity:0}to{opacity:1}}'
		. ' body{margin:0} a[href$=".pdf"]{color:blue} .menu.show{display:block} .card:not(.gone){color:#000}'
		. ' #top{scroll-margin:0} #nowhere{color:red}';
	$used = pg_publish_weight_used_names('<div class="hero menu card" id="top"></div>');

	$unused = pg_publish_weight_unused_rules($css, $used);

	pg_assert_same(array(
		array('selector' => '.gone, .lost .x', 'media' => ''),
		array('selector' => '.hero .gone', 'media' => ''),
		array('selector' => '.lost', 'media' => '@media (max-width: 600px)'),
		array('selector' => '#nowhere', 'media' => ''),
	), $unused, 'element, attribute, state-class and :not() rules stay');
}

function test_publish_weight_unused_rules_keeps_a_rule_one_selector_uses()
{
	$unused = pg_publish_weight_unused_rules('.gone, .hero{color:red}', pg_publish_weight_used_names('<b class="hero"></b>'));

	pg_assert_same(array(), $unused);
}

function test_publish_weight_findings_in_the_publish_check_shape()
{
	$budget = pg_publish_weight_budget();
	$m = pg_publish_weight_measure(publish_weight_test_page(), publish_weight_test_files());
	$m['bytes']['images'] = $budget['images'] + 5;
	$unused = array(array('selector' => '.gone', 'media' => ''), array('selector' => '.lost', 'media' => ''));

	$found = pg_publish_weight_findings($m, $unused, array('img_oversized' => 'Resim büyük'), $budget);
	$codes = array_map(function ($f) { return $f['code']; }, $found);

	pg_assert_same(array('weight_images', 'img_oversized', 'img_no_size', 'img_no_size', 'script_blocking', 'css_unused'), $codes);

	$keys = array('id', 'source', 'code', 'group', 'level', 'text', 'node', 'shared', 'where', 'count', 'detail', 'seo', 'concept');

	foreach ($found as $f) {
		pg_assert_same($keys, array_keys($f), 'the finding shape');
		pg_assert_same('weight', $f['source']);
		pg_assert_same('weight', $f['group']);
		pg_assert_same('look', $f['level'], 'weight never blocks');
	}

	pg_assert_same('Resim büyük', $found[1]['text'], 'a text the sheet hands in');
	pg_assert_same('weight:img_oversized:n1:0', $found[1]['id']);
	pg_assert_same('n1', $found[1]['node']);
	pg_assert_same('/files/hero.jpg', $found[1]['detail']);
	pg_assert_same('weight:img_no_size:n2:0', $found[2]['id']);
	pg_assert_same(2, $found[5]['count']);
	pg_assert_same(".gone\n.lost", $found[5]['detail']);
	pg_assert_same('weight:weight_images::0', $found[0]['id']);
	pg_assert_contains((string) $budget['images'], $found[0]['detail'], 'the budget shown');
}

function test_publish_weight_findings_of_a_light_page_are_none()
{
	$m = pg_publish_weight_measure('<html><body><img src="/a.jpg" width="10" height="10"></body></html>',
		array('/a.jpg' => array('bytes' => 100, 'width' => 10, 'height' => 10)));

	pg_assert_same(array(), pg_publish_weight_findings($m, array(), array()));
}

function test_publish_weight_failed_is_one_finding_that_blocks_nothing()
{
	$f = pg_publish_weight_failed('timeout', array());

	pg_assert_same('failed', $f['level']);
	pg_assert_same('weight', $f['group']);
	pg_assert_same('weight_failed', $f['code']);
	pg_assert_same('timeout', $f['detail']);
	pg_assert_true($f['text'] !== '');
}

function test_publish_weight_local_files_reads_sizes_under_the_root_only()
{
	$root = sys_get_temp_dir() . '/pgpw' . getmypid();
	@mkdir($root . '/files', 0777, true);
	file_put_contents($root . '/files/a.css', str_repeat('x', 123));
	// A 2x1 GIF.
	file_put_contents($root . '/files/p.gif', base64_decode('R0lGODlhAgABAIAAAP///wAAACwAAAAAAgABAAACAoRRADs='));
	file_put_contents(sys_get_temp_dir() . '/pgpw_outside.css', 'x');

	$files = pg_publish_weight_local_files(array('/site/files/a.css?v=2', '/site/files/p.gif', '/site/files/none.css',
		'/site/../pgpw_outside.css', 'https://cdn.example.com/x.js', '/other/a.css'), $root, '/site/');

	pg_assert_same(array('/site/files/a.css?v=2' => array('bytes' => 123, 'width' => 0, 'height' => 0),
		'/site/files/p.gif' => array('bytes' => strlen(base64_decode('R0lGODlhAgABAIAAAP///wAAACwAAAAAAgABAAACAoRRADs=')), 'width' => 2, 'height' => 1)), $files);

	@unlink($root . '/files/a.css');
	@unlink($root . '/files/p.gif');
	@rmdir($root . '/files');
	@rmdir($root);
	@unlink(sys_get_temp_dir() . '/pgpw_outside.css');
}
