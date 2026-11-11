<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for {path} and {software_directory} in a visual design's addresses:
 * the address a CSS or JS asset row loads on the page
 * (pg_design_asset_address()), filled before the renderer's address check,
 * and the editor's twin that fills them on the canvas (asset links, inline
 * CSS, a node's addresses and attributes) while the tree and the rows keep
 * them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- the page: an asset row's address ------------------------------------------

function test_design_asset_address_fills_the_placeholders()
{
	pg_assert_same('/css/style.css', pg_design_asset_address('{path}css/style.css', '/', 'pinegrap'));
	pg_assert_same('/site/style.css', pg_design_asset_address('{PATH}style.css', '/site/', 'pinegrap'));
	pg_assert_same('/pinegrap/frontend.min.js', pg_design_asset_address('{path}{software_directory}/frontend.min.js', '/', 'pinegrap'));
	pg_assert_same('/site/pg/x.js?v=2', pg_design_asset_address(' {Path}{SOFTWARE_DIRECTORY}/x.js?v=2 ', '/site/', 'pg'));
}

// Addresses the renderer printed before keep printing as they are.
function test_design_asset_address_keeps_other_addresses()
{
	pg_assert_same('https://cdn.jsdelivr.net/npm/x@1/x.min.css', pg_design_asset_address('https://cdn.jsdelivr.net/npm/x@1/x.min.css', '/', 'pinegrap'));
	pg_assert_same('/style.css', pg_design_asset_address('/style.css', '/', 'pinegrap'));
	pg_assert_same('/a/b.css', pg_design_asset_address("  /a/b.css\n", '/', 'pinegrap'));
}

// What the check refused before is still refused, with or without a
// placeholder: a relative address and an empty row print nothing.
function test_design_asset_address_refuses_what_is_no_address()
{
	pg_assert_same('', pg_design_asset_address('style.css', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('{software_directory}/x.js', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('', '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address(null, '/', 'pinegrap'));
	pg_assert_same('', pg_design_asset_address('   ', '/', 'pinegrap'));
}

// A path holding $ or \ is copied as written.
function test_design_asset_address_path_is_literal()
{
	pg_assert_same('/a$1\\b/x.css', pg_design_asset_address('{path}x.css', '/a$1\\b/', ''));
}

// Both linked rows of the page go through it, with the raw path: the
// address is escaped once when it is printed.
function test_design_asset_address_wired_into_the_page()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("\$_url = pg_design_asset_address(isset(\$_cf['content']) ? \$_cf['content'] : '', PATH, SOFTWARE_DIRECTORY);", $src);
	pg_assert_contains("\$_url = pg_design_asset_address(isset(\$_jf['content']) ? \$_jf['content'] : '', PATH, SOFTWARE_DIRECTORY);", $src);
	pg_assert_same(0, substr_count($src, "strpos(\$_url, '/') === 0"), 'the address check lives in pg_design_asset_address()');
}

// -- the canvas: the editor's twin ---------------------------------------------

function _dapp_js_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start + 6);
}

// $script run under Node after the named functions of the editor: its JSON
// answer, or null when Node is missing.
function _dapp_node($script, $fns, $prelude = '')
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = $prelude . "\n";
	foreach ($fns as $fn) {
		$body = _dapp_js_fn($js, $fn);
		pg_assert_true($body !== '', $fn . '() is found');
		$code_js .= $body . "\n";
	}
	$file = tempnam(sys_get_temp_dir(), 'pgdapp');
	file_put_contents($file, $code_js . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	return json_decode(implode("\n", $out), true);
}

// The same answers as the page's fill (pg_designer_resolve_output_placeholders()).
function test_design_asset_path_canvas_fill()
{
	$res = _dapp_node(<<<'JS'
console.log(JSON.stringify([
    _sdPathFill('{path}css/a.css', '/', 'pinegrap'),
    _sdPathFill('{PATH}{Software_Directory}/x.js', '/site/', 'pg'),
    _sdPathFill('a { background: url({path}bg.jpg) } b { background: url("{path}b.png") }', '/', 'pinegrap'),
    _sdPathFill('{path}x.css', '/a$1$&\\b/', ''),
    _sdPathFill('no braces', '/', 'pinegrap'),
    _sdPathFill('', '/', 'pinegrap'),
    _sdPathFill(null, '/', 'pinegrap'),
    _sdPathFill(undefined, '/', 'pinegrap'),
    _sdPathFill('{paths} {software_dir}', '/', 'pinegrap')
]));
JS
		, array('_sdPathFill'));
	if ($res === null) {
		return;
	}
	pg_assert_same(array(
		'/css/a.css',
		'/site/pg/x.js',
		'a { background: url(/bg.jpg) } b { background: url("/b.png") }',
		'/a$1$&\\b/x.css',
		'no braces',
		'',
		'',
		'',
		'{paths} {software_dir}',
	), $res);
}

// The editor's own values: the site path and the software folder it was
// given (window.OUTPUT_PATH, software_directory).
function test_design_asset_path_canvas_site_values()
{
	$res = _dapp_node(<<<'JS'
var out = [_sdSitePath('{path}{software_directory}/x.js')];
window.OUTPUT_PATH = '/site/';
var software_directory = 'pg';
out.push(_sdSitePath('{path}{software_directory}/x.js'));
console.log(JSON.stringify(out));
JS
		, array('_sdPathFill', '_sdSitePath'), 'var window = {};');
	if ($res === null) {
		return;
	}
	pg_assert_same(array('/pinegrap/x.js', '/site/pg/x.js'), $res);
}

// A node's canvas props: its addresses and a Custom HTML block's markup
// filled in a copy; the node keeps what was written.
function test_design_asset_path_canvas_node_props()
{
	$res = _dapp_node(<<<'JS'
var img = { src: '{path}logo.png', alt: 'Logo {path}', cssClass: '' };
var link = { href: '{path}about', text: '<a href="{path}x">x</a>' };
var html = { html: '<img src="{path}a.png">' };
var plain = { src: '/logo.png', href: 'https://example.com/' };
var filled = [img, link, html, plain].map(_sdPathProps);
console.log(JSON.stringify({
    filled: filled,
    kept: [img.src, link.href, html.html],
    same: filled[3] === plain
}));
JS
		, array('_sdPathFill', '_sdSitePath', '_sdPathProps'), "var window = { OUTPUT_PATH: '/' };\nvar software_directory = 'pinegrap';\nvar _SD_PATH_PROPS = ['src', 'href', 'poster', 'html'];");
	if ($res === null) {
		return;
	}
	pg_assert_same(array('src' => '/logo.png', 'alt' => 'Logo {path}', 'cssClass' => ''), $res['filled'][0], 'only the address props');
	pg_assert_same(array('href' => '/about', 'text' => '<a href="{path}x">x</a>'), $res['filled'][1], 'the text an inline edit reads back stays');
	pg_assert_same(array('html' => '<img src="/a.png">'), $res['filled'][2]);
	pg_assert_same(array('{path}logo.png', '{path}about', '<img src="{path}a.png">'), $res['kept']);
	pg_assert_true($res['same'], 'props with nothing to fill are not copied');
}

// The asset links: the filled address, a managed file's stamp still found
// by the row's own text.
function test_design_asset_path_canvas_asset_href()
{
	$res = _dapp_node(<<<'JS'
var _sdAssetLoadStamp = 100;
var _sdAssetStamps = { '/own.css': 200 };
console.log(JSON.stringify([
    _sdAssetHref({ content: ' {path}css/a.css ' }),
    _sdAssetHref({ content: '{path}{software_directory}/x.js' }),
    _sdAssetHref({ content: '/own.css', _file: true }),
    _sdAssetHref({ content: '/other.css', _file: true }),
    _sdAssetHref({ content: 'https://cdn.example/x.css' }),
    _sdAssetHref({ content: '' })
]));
JS
		, array('_sdPathFill', '_sdSitePath', '_sdAssetHref'), "var window = { OUTPUT_PATH: '/' };\nvar software_directory = 'pinegrap';");
	if ($res === null) {
		return;
	}
	pg_assert_same(array('/css/a.css', '/pinegrap/x.js', '/own.css?_t=200', '/other.css?_t=100', 'https://cdn.example/x.css', ''), $res);
}

// Where the canvas uses it: node props, attributes and style, the inline
// CSS rows; the code view and the save read the rows and the tree as written.
function test_design_asset_path_canvas_wired()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$props = _dapp_js_fn($js, '_propsWithBindings');
	pg_assert_true(substr_count($props, 'return _sdPathProps(') === 2, 'both answers of _propsWithBindings() are filled');
	$attrs = _dapp_js_fn($js, '_sdApplyNodeAttrs');
	pg_assert_contains("_attrEl.setAttribute(a.name.trim(), _sdSitePath(_sdResolveAttrTokens(a.value || '')));", $attrs);
	pg_assert_contains("_attrEl.setAttribute('style', _sdSitePath(_combined));", $attrs);
	$apply = _dapp_js_fn($js, 'applyAssetsToIframe');
	pg_assert_contains("userStylesCss += _sdSitePath(f.content.trim()) + '\\n';", $apply);
	pg_assert_contains("+ _sdSitePath(f.content) + '\\n';", $apply);
	pg_assert_contains('scr.textContent = _sdSitePath(f.content);', $apply);
	pg_assert_false(strpos(_dapp_js_fn($js, '_sdPageFrameRows'), '_sdSitePath') !== false, 'the code view shows the rows as written');
}
