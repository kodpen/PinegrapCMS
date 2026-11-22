<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas's true picture of a page's regions: the markers the
 * page render puts around every region tag while the flag is on, the cut
 * that takes each region out of a whole page, the designer/
 * legacy_region_preview endpoint, which draws in the background for
 * everyone who may open the page, and the canvas's band and match.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function _legacy_region_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// One function of style_designer.js, from its declaration to its closing
// brace; '' when it is not there.
function _legacy_region_js_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs functions of style_designer.js and a script under Node. Returns the
// decoded JSON the script prints, or null when Node is missing (the test is
// then skipped) or a function is missing (counted as a failure).
function _legacy_region_node($names, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = '';
	$found = true;

	foreach ($names as $name) {
		$body = _legacy_region_js_function($js, $name);
		$found = pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()') && $found;
		$code_js .= $body . "\n";
	}

	if (!$found) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pglrp');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($res), 'the script answers');

	return is_array($res) ? $res : null;
}

// Each region tag between two markers: its type, its name (entities read,
// tags dropped) and its order among the same type and name.
function test_legacy_region_mark_tags()
{
	$page = '<nav><menu>Main</menu><div style="clear: both"></div></nav>'
		. '<MENU_SEQUENCE>Side</MENU_SEQUENCE><cregion>head &amp; foot</cregion>'
		. '<system></system><dregion "x=1">news</dregion><cart></cart><ad>top</ad>'
		. '<cregion>head &amp; foot</cregion><address>a</address><tcloud></tcloud>';

	$marked = pg_layout_region_mark_tags($page);
	$regions = pg_layout_regions_cut($marked);

	$keys = array();
	foreach ($regions as $r) {
		$keys[] = $r['type'] . ':' . $r['name'] . ':' . $r['order'];
	}
	pg_assert_same(array('menu:Main:0', 'menu_sequence:Side:0', 'cregion:head & foot:0', 'system::0',
		'dregion:news:0', 'cart::0', 'ad:top:0', 'cregion:head & foot:1', 'tcloud::0'), $keys, 'every tag, in order');
	pg_assert_same('<dregion "x=1">news</dregion>', $regions[4]['html'], 'the tag stays for its pass');
	pg_assert_contains('<address>a</address>', $marked, 'another element is not a region');
	pg_assert_same('plain', pg_layout_region_mark_tags('plain'));

	// Numbers are unique in the request: a second render never reuses one.
	preg_match('/pg-region-start:(\d+):/', pg_layout_region_mark_tags('<pdf></pdf>'), $m1);
	preg_match('/pg-region-start:(\d+):/', pg_layout_region_mark_tags('<pdf></pdf>'), $m2);
	pg_assert_true((int) $m2[1] > (int) $m1[1], 'a new number each time');
}

function test_legacy_region_cut()
{
	$page = '<html><body><!--pg-region-start:7:cregion:outer:0--><div>'
		. '<!--pg-region-start:8:cregion:inner:0--> <p>in</p> <!--pg-region-end:8-->'
		. '</div><!--pg-region-end:7--><!--pg-region-start:9:menu:a%3Ab:0-->x<!--pg-region-end:9-->'
		. '<!--pg-region-start:10:pdf::0-->cut short</body></html>';

	$regions = pg_layout_regions_cut($page);
	pg_assert_same(3, count($regions), 'a start with no end is passed over');
	pg_assert_same(array('type' => 'cregion', 'name' => 'outer', 'order' => 0, 'html' => '<div> <p>in</p> </div>'), $regions[0],
		'the region inside is drawn, its markers gone');
	pg_assert_same('<p>in</p>', $regions[1]['html'], 'the inner region on its own, trimmed');
	pg_assert_same('a:b', $regions[2]['name'], 'the name decoded');
	pg_assert_same(array(), pg_layout_regions_cut('<html><body>no region</body></html>'));
	pg_assert_same(array(), pg_layout_regions_cut(''));
}

// Off unless a preview turns it on, so no visitor's page carries a marker.
function test_legacy_region_marking_flag()
{
	pg_assert_false(pg_layout_region_marking());
	pg_assert_true(pg_layout_region_marking(true));
	pg_assert_true(pg_layout_region_marking());
	pg_assert_false(pg_layout_region_marking(false));
	pg_assert_false(pg_layout_region_marking());
}

// Every region tag is marked, only under the flag, before the first region
// pass; the page's own system region has no marker of its own any more.
function test_legacy_region_render_marks_every_region()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	$mark = strpos($source, "if (function_exists('pg_layout_region_marking') && pg_layout_region_marking()) {\n        \$content = pg_layout_region_mark_tags(\$content);");
	$first_pass = strpos($source, "preg_match_all('/<cregion>.*?<\\/cregion>/i', \$content, \$regions);");
	pg_assert_true(($mark !== false) && ($first_pass !== false) && ($mark < $first_pass), 'marked before the first pass');
	pg_assert_false(strpos($source, 'pg-layout-region-start') !== false, 'no second marker');
}

// A background render whose borrowed request state always comes back.
function test_legacy_region_preview_background_render()
{
	$source = _legacy_region_source('pg_layout_regions_preview');

	pg_assert_false(strpos($source, 'pg_layout_custom_page_ids(') !== false, 'every page, not only a custom layout');
	pg_assert_contains('pg_seo_rendering(true);', $source);
	pg_assert_contains('pg_error_throws(true);', $source);
	pg_assert_contains("'preview'", $source, 'never edit mode');
	pg_assert_contains('return pg_layout_regions_cut($html);', $source);

	$finally = strpos($source, '} finally {');
	pg_assert_true($finally !== false);
	$tail = substr($source, $finally);
	foreach (array('ob_end_clean();', 'pg_layout_region_marking(false);', 'pg_seo_rendering($saved_seo);',
		'pg_error_throws($saved_throw);', '$_GET = $saved_get;') as $restore) {
		pg_assert_contains($restore, $tail, $restore);
	}

	pg_assert_same(array(), pg_layout_regions_preview(0));
	pg_assert_same(array(), pg_layout_regions_preview(-4));
}

// Everyone who may open the page in the editor: a content-level operator
// too, but not for a page hidden from them.
function test_legacy_region_preview_endpoint()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	$source = _legacy_region_source('pg_panel_designer');

	$content_ok = substr($source, strpos($source, '$content_ok = array('));
	$content_ok = substr($content_ok, 0, strpos($content_ok, ');'));
	pg_assert_contains("'legacy_region_preview'", $content_ok, 'opened to content editors');

	$case = strpos($source, "case 'legacy_region_preview':");
	pg_assert_true($case !== false);
	$branch = substr($source, $case, strpos($source, 'break;', $case) - $case);
	$access = strpos($branch, "pg_designer_page_access(\$lrp_page, \$user) === 'hidden'");
	$render = strpos($branch, 'pg_layout_regions_preview(');
	pg_assert_true(($access !== false) && ($access < $render), 'the page access before the render');
	pg_assert_true(strpos($source, 'validate_token();') < $case, 'the token is checked');

	$sub_actions = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('legacy_region_preview', $sub_actions['designer']['clean'], true), 'a read keeps the page cache');
}

// The canvas draws every region but the page region and the comments block,
// keeps the picture out of the tree, and drops what would run in the editor:
// scripts, frames and plugins, event attributes, javascript: addresses.
function test_legacy_region_preview_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('if (_sdRegionTrueKey(node)) _sdRegionTrueAppend(doc, rg, node);', $js);
	pg_assert_false(strpos($js, 'function _sdLegacyRegionAppend(') !== false, 'one way of drawing a region');

	$start = strpos($js, 'function _sdRegionTrueClean(');
	$body = substr($js, $start, strpos($js, "\n    }\n", $start) - $start);
	pg_assert_contains("querySelectorAll('script, iframe, object, embed')", $body, 'no script, frame or plugin');
	pg_assert_contains("a.name.slice(0, 2) === 'on'", $body);
	pg_assert_contains('_sdLegacyRegionJsUrl(a.value)', $body, 'no javascript: address in any attribute');
	pg_assert_contains("box.setAttribute('inert', '');", $body);
	pg_assert_false(strpos($body, 'data-sd-id') !== false, 'never selectable');

	$start = strpos($js, 'function _sdRegionTrueAppend(');
	$body = substr($js, $start, strpos($js, "\n    }\n", $start) - $start);
	pg_assert_contains("_pgApiPost('legacy_region_preview'", $body);
	pg_assert_false(strpos($body, "(_design.layoutPages || []).indexOf(p.page_id) === -1) return") !== false, 'every page, not only a custom layout');
	pg_assert_contains("_sdT('Code')", $body, 'the pill');
	pg_assert_contains("_sdT('Classic')", $body);
}

// Each region type to the tag the server marks, the key a node is matched
// by, and the match by type, name and order.
function test_legacy_region_true_key_and_match()
{
	$script = <<<'JS'
var reg = function (t, n) { return { type: 'region', props: { regionType: t, regionName: n } }; };
var regions = [
    { type: 'cregion', name: 'head', order: 0, html: 'h0' },
    { type: 'cregion', name: 'head', order: 1, html: 'h1' },
    { type: 'system', name: '', order: 0, html: 's' }
];
var out = {
    page: _sdRegionTrueKey(reg('page', '')),
    comments: _sdRegionTrueKey(reg('comments_block', '')),
    unknown: _sdRegionTrueKey(reg('nothing', 'x')),
    none: _sdRegionTrueKey(null),
    common: _sdRegionTrueKey(reg('common', 'head')),
    designer: _sdRegionTrueKey(reg('designer', 'head')),
    tag_cloud: _sdRegionTrueKey(reg('tag_cloud', 'search')),
    cart: _sdRegionTrueKey(reg('cart', 'ignored')),
    system: _sdRegionTrueKey(reg('system')),
    dynamic: _sdRegionTrueKey(reg('dynamic', 'news')),
    m0: (_sdRegionTrueMatch(regions, 'cregion:head', 0) || {}).html,
    m1: (_sdRegionTrueMatch(regions, 'cregion:head', 1) || {}).html,
    m2: _sdRegionTrueMatch(regions, 'cregion:head', 2),
    ms: (_sdRegionTrueMatch(regions, 'system:', 0) || {}).html,
    bad: _sdRegionTrueMatch(null, 'system:', 0)
};
console.log(JSON.stringify(out));
JS;

	$res = _legacy_region_node(array('_sdRegionMarkType', '_sdRegionTrueKey', '_sdRegionTrueMatch'), $script);

	if ($res === null) {
		return;
	}

	pg_assert_same('', $res['page'], 'the page region is the page\'s own content');
	pg_assert_same('', $res['comments'], 'the comments block keeps its placeholder');
	pg_assert_same('', $res['unknown']);
	pg_assert_same('', $res['none']);
	pg_assert_same('cregion:head', $res['common']);
	pg_assert_same('cregion:head', $res['designer'], 'a designer region is a <cregion> too');
	pg_assert_same('tcloud:search', $res['tag_cloud']);
	pg_assert_same('cart:', $res['cart'], 'a tag with no name');
	pg_assert_same('system:', $res['system']);
	pg_assert_same('dregion:news', $res['dynamic']);
	pg_assert_same('h0', $res['m0']);
	pg_assert_same('h1', $res['m1'], 'the second of the same name');
	pg_assert_same(null, $res['m2']);
	pg_assert_same('s', $res['ms']);
	pg_assert_same(null, $res['bad']);
}

// Only the unnamed system region is the page's own, the one whose page type
// the Region panel offers to convert; a named one draws another page's
// region.
function test_legacy_region_preview_own_region()
{
	$script = <<<'JS'
var cases = {
    unnamed: { type: 'region', props: { regionType: 'system' } },
    empty_name: { type: 'region', props: { regionType: 'system', regionName: '' } },
    named: { type: 'region', props: { regionType: 'system', regionName: 'search' } },
    menu: { type: 'region', props: { regionType: 'menu', regionName: '' } },
    dynamic: { type: 'region', props: { regionType: 'dynamic' } },
    no_props: { type: 'region' }
};
var out = {};
Object.keys(cases).forEach(function (k) { out[k] = _sdLegacyRegionOwn(cases[k]); });
out.none = _sdLegacyRegionOwn(null);
console.log(JSON.stringify(out));
JS;

	$res = _legacy_region_node(array('_sdLegacyRegionOwn'), $script);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['unnamed'], 'the unnamed system region is the page\'s own');
	pg_assert_true($res['empty_name'], 'an empty name is no name');
	pg_assert_false($res['named'], 'a named system region keeps the placeholder');
	pg_assert_false($res['menu'], 'another region type keeps its placeholder');
	pg_assert_false($res['dynamic'], 'a dynamic region keeps its placeholder');
	pg_assert_false($res['no_props'], 'a node without props');
	pg_assert_false($res['none'], 'no node');
}

// A javascript: address as the browser reads one: the scheme's case does not
// matter, nor C0 controls and spaces around it or tabs and newlines inside it.
function test_legacy_region_preview_js_urls()
{
	$script = <<<'JS'
var cases = {
    plain: 'javascript:alert(1)',
    upper: 'JAVASCRIPT:alert(1)',
    mixed: ' JaVaScRiPt:alert(1)',
    tab_inside: 'java\tscript:alert(1)',
    newline_inside: 'javas\ncript:alert(1)',
    control_before: '\u0001\u0010javascript:alert(1)',
    https: 'https://example.com/a?b=javascript:1',
    relative: '/page?x=javascript:1',
    fragment: '#top',
    mailto: 'mailto:a@example.com',
    empty: '',
    none: null
};
var out = {};
Object.keys(cases).forEach(function (k) { out[k] = _sdLegacyRegionJsUrl(cases[k]); });
console.log(JSON.stringify(out));
JS;

	$res = _legacy_region_node(array('_sdLegacyRegionJsUrl'), $script);

	if ($res === null) {
		return;
	}

	foreach (array('plain', 'upper', 'mixed', 'tab_inside', 'newline_inside', 'control_before') as $key) {
		pg_assert_true($res[$key], 'a javascript: address: ' . $key);
	}

	foreach (array('https', 'relative', 'fragment', 'mailto', 'empty', 'none') as $key) {
		pg_assert_false($res[$key], 'not a javascript: address: ' . $key);
	}
}
