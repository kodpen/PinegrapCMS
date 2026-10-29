<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas preview of a classic page's system region drawn by
 * its custom layout: the markers the page render puts around the page's own
 * system region while the flag is on, the cut that takes the region out of
 * a whole page, and the designer/legacy_region_preview endpoint, which asks
 * for the layout editor's right and draws in the background.
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

function test_legacy_region_cut()
{
	$page = '<html><body><nav>menu</nav><div class="c">'
		. '<!--pg-layout-region-start--> <div class="software_catalog">items</div> <!--pg-layout-region-end-->'
		. '</div><!--pg-layout-region-start-->second<!--pg-layout-region-end--></body></html>';

	pg_assert_same('<div class="software_catalog">items</div>', pg_layout_region_cut($page), 'the first region, trimmed');
	pg_assert_same("a\nb", pg_layout_region_cut("<!--pg-layout-region-start-->a\nb<!--pg-layout-region-end-->"), 'across lines');
	pg_assert_same('', pg_layout_region_cut('<html><body>no region</body></html>'), 'a tree without the region');
	pg_assert_same('', pg_layout_region_cut('<!--pg-layout-region-start-->cut short'), 'no end marker');
	pg_assert_same('', pg_layout_region_cut(''));
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

// Only the page's own system region is marked, and only under the flag.
function test_legacy_region_render_marks_primary_region()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains(
		"if (\$primary_system_region && function_exists('pg_layout_region_marking') && pg_layout_region_marking()) {",
		$source
	);
	$mark = strpos($source, "'<!--pg-layout-region-start-->' . \$system_output . '<!--pg-layout-region-end-->'");
	$replace = strpos($source, "pg_regex_replacement(\$system_output), \$content);");
	pg_assert_true(($mark !== false) && ($replace !== false) && ($mark < $replace), 'marked before it is put in place');
}

// A background render whose borrowed request state always comes back.
function test_legacy_region_preview_background_render()
{
	$source = _legacy_region_source('pg_layout_region_preview');

	$gate = strpos($source, 'pg_layout_custom_page_ids(array($page_id))');
	$render = strpos($source, 'get_page_content(');
	pg_assert_true(($gate !== false) && ($gate < $render), 'only a page drawing a custom layout');
	pg_assert_contains('pg_seo_rendering(true);', $source);
	pg_assert_contains('pg_error_throws(true);', $source);
	pg_assert_contains("'preview'", $source, 'never edit mode');

	$finally = strpos($source, '} finally {');
	pg_assert_true($finally !== false);
	$tail = substr($source, $finally);
	foreach (array('ob_end_clean();', 'pg_layout_region_marking(false);', 'pg_seo_rendering($saved_seo);',
		'pg_error_throws($saved_throw);', '$_GET = $saved_get;') as $restore) {
		pg_assert_contains($restore, $tail, $restore);
	}

	pg_assert_same('', pg_layout_region_preview(0));
	pg_assert_same('', pg_layout_region_preview(-4));
}

// Designers and administrators, as the layout tab; a content-level operator
// never reaches it.
function test_legacy_region_preview_endpoint()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	$source = _legacy_region_source('pg_panel_designer');

	$content_ok = substr($source, strpos($source, '$content_ok = array('));
	$content_ok = substr($content_ok, 0, strpos($content_ok, ');'));
	pg_assert_false(strpos($content_ok, 'legacy_region_preview') !== false, 'not opened to content editors');

	$case = strpos($source, "case 'legacy_region_preview':");
	pg_assert_true($case !== false);
	$branch = substr($source, $case, strpos($source, 'break;', $case) - $case);
	$role = strpos($branch, "if ((int)\$user['role'] > 1)");
	$render = strpos($branch, 'pg_layout_region_preview(');
	pg_assert_true(($role !== false) && ($role < $render), 'the role before the render');
	pg_assert_true(strpos($source, 'validate_token();') < $case, 'the token is checked');

	$sub_actions = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('legacy_region_preview', $sub_actions['designer']['clean'], true), 'a read keeps the page cache');
}

// The canvas draws it only under the page's own system region of a page in
// the layout list, keeps it out of the tree, and drops what would run in the
// editor: scripts, frames and plugins, event attributes, javascript:
// addresses.
function test_legacy_region_preview_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('if (_sdLegacyRegionOwn(node)) _sdLegacyRegionAppend(doc, rg);', $js);
	pg_assert_false(strpos($js, "if (node.props.regionType === 'system') _sdLegacyRegionAppend(doc, rg);") !== false,
		'a named system region is not drawn with the page\'s own region');

	$start = strpos($js, 'function _sdLegacyRegionAppend(');
	$body = substr($js, $start, strpos($js, "\n    }\n", $start) - $start);
	pg_assert_contains("(_design.layoutPages || []).indexOf(p.page_id) === -1", $body);
	pg_assert_contains("_pgApiPost('legacy_region_preview'", $body);
	pg_assert_contains("querySelectorAll('script, iframe, object, embed')", $body, 'no script, frame or plugin');
	pg_assert_contains("a.name.slice(0, 2) === 'on'", $body);
	pg_assert_contains('_sdLegacyRegionJsUrl(a.value)', $body, 'no javascript: address in any attribute');
	pg_assert_contains("box.setAttribute('inert', '');", $body);
	pg_assert_false(strpos($body, 'data-sd-id') !== false, 'never selectable');
}

// Only the unnamed system region is the page's own (the one the server marks,
// get_page_content.php); a named one draws another page's region and keeps
// the plain placeholder.
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
