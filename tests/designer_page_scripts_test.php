<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the platform scripts a visual editor page gets from its markup:
 * pg_bs_tips_inject() adds assets/js/pg_bs_tips.js (Bootstrap tooltips and
 * popovers) to a page holding data-bs-toggle="tooltip" or "popover", once,
 * before the last </body>; the editor's Preview asks the same table of
 * markers (_SD_PAGE_SCRIPTS in assets/js/style_designer.js) with the same
 * expression, and loads the framework's script only when the design's
 * Bootstrap JS row does not.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The address the tests pass, so no software directory constant is needed.
function _test_page_scripts_src()
{
	return '/pinegrap/assets/js/pg_bs_tips.js?v=1';
}

// The source of the visual editor.
function _test_page_scripts_designer_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// The body of one function of the editor's source, from its declaration to
// the next declaration at the same indent.
function _test_page_scripts_js_function($name)
{
	$js = _test_page_scripts_designer_js();
	$start = strpos($js, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($js, "\n    function ", $start + 1);
	return substr($js, $start, ($end === false ? strlen($js) : $end) - $start);
}

// The rows of _SD_PAGE_SCRIPTS: expression source => file.
function _test_page_scripts_js_rows()
{
	$js = _test_page_scripts_designer_js();
	$rows = array();
	if (!preg_match('~var _SD_PAGE_SCRIPTS = \[(.*?)\n    \];~s', $js, $m)) {
		return $rows;
	}
	preg_match_all("~\{ test: /(.+?)/[a-z]*, file: '([^']+)' \}~", $m[1], $all, PREG_SET_ORDER);
	foreach ($all as $row) {
		$rows[$row[1]] = $row[2];
	}
	return $rows;
}

function test_page_scripts_pattern_finds_tooltips_and_popovers()
{
	$hits = array(
		'<p data-bs-toggle="tooltip" title="x">',
		"<button data-bs-toggle='popover'>",
		'<a data-bs-toggle=tooltip>',
		'<span DATA-BS-TOGGLE = "Popover">',
	);
	foreach ($hits as $html) {
		pg_assert_true((bool) preg_match(pg_bs_tips_pattern(), $html), $html);
	}
	$misses = array(
		'<button data-bs-toggle="collapse">',
		'<a data-bs-toggle="dropdown">',
		'<p data-bs-toggle="tooltips">',
		'<p title="tooltip">',
	);
	foreach ($misses as $html) {
		pg_assert_false((bool) preg_match(pg_bs_tips_pattern(), $html), $html);
	}
}

// The attribute the panel's Tooltip option prints is the one looked for.
function test_page_scripts_pattern_matches_the_renderers_tooltip()
{
	$attrs = _pg_node_title_attrs(array('tooltipEnabled' => true, 'tooltipTitle' => 'Hi'));
	pg_assert_contains('data-bs-toggle="tooltip"', $attrs);
	pg_assert_true((bool) preg_match(pg_bs_tips_pattern(), $attrs), 'the Tooltip option is found');
}

function test_page_scripts_inject_adds_one_deferred_script_before_the_last_body()
{
	$html = '<html><body><pre>&lt;/body&gt; </body></pre><button data-bs-toggle="popover">x</button></body></html>';
	$out = pg_bs_tips_inject($html, _test_page_scripts_src());

	pg_assert_same(1, substr_count($out, 'pg_bs_tips.js'));
	pg_assert_contains('<script src="' . _test_page_scripts_src() . '" defer></script>' . "\n" . '</body></html>', $out);
	pg_assert_same($out, pg_bs_tips_inject($out, _test_page_scripts_src()), 'a second call adds nothing');
}

function test_page_scripts_inject_leaves_a_page_without_tips()
{
	$html = '<html><body><button data-bs-toggle="collapse" title="Open">x</button></body></html>';
	pg_assert_same($html, pg_bs_tips_inject($html, _test_page_scripts_src()));
}

function test_page_scripts_inject_appends_without_a_body_tag()
{
	$html = '<p data-bs-toggle="tooltip" title="x">y</p>';
	$out = pg_bs_tips_inject($html, _test_page_scripts_src());
	pg_assert_same($html . '<script src="' . _test_page_scripts_src() . '" defer></script>' . "\n", $out);
}

// The Preview's table names files that exist, each added to the published
// page by a server step, and tests the tips with the server's expression.
function test_page_scripts_preview_table_mirrors_the_server()
{
	$rows = _test_page_scripts_js_rows();
	pg_assert_true(count($rows) >= 2, '_SD_PAGE_SCRIPTS rows are read');

	$server = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	foreach ($rows as $file) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $file), $file . ' exists');
		pg_assert_contains("'/" . $file, $server);
	}

	$php = pg_bs_tips_pattern();
	$body = substr($php, 1, strrpos($php, '/') - 1);
	pg_assert_same('assets/js/pg_bs_tips.js', isset($rows[str_replace("\\'", "'", $body)]) ? $rows[str_replace("\\'", "'", $body)] : null);
	pg_assert_same('assets/js/pg_theme_switcher.js', isset($rows['data-pg-theme-switcher']) ? $rows['data-pg-theme-switcher'] : null);
}

// The framework's script is the fallback for a page without the design's
// Bootstrap JS row, as on the published page: two copies toggle every
// dropdown and collapse twice per click. The tips come from the shared
// file, not from an inline start of the Preview's own.
function test_page_scripts_preview_loads_bootstrap_once()
{
	$fn = _test_page_scripts_js_function('_sdPreviewDocHtml');
	pg_assert_true($fn !== '', '_sdPreviewDocHtml is found');
	pg_assert_contains("f.meta === 'bootstrap-js'", $fn);
	pg_assert_contains('if (_pvFw.js && !_pvBsJsRow)', $fn);
	pg_assert_contains('_sdPageScriptFiles(html)', $fn);
	pg_assert_false(strpos($fn, 'new bootstrap.Tooltip') !== false, 'no inline tooltip start');
}

// The editor's dark view is written on <html>, where a theme switcher
// writes: on <body> it outranks the switcher.
function test_page_scripts_preview_dark_view_on_html()
{
	$fn = _test_page_scripts_js_function('_sdPreviewDocHtml');
	pg_assert_contains("'<html' + attrs + ' data-bs-theme=\"dark\">'", $fn);
	pg_assert_false(strpos($fn, "'<body' + attrs + ' data-bs-theme") !== false, 'the body tag is left alone');
}
