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

	$server = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer_fx.php');
	foreach ($rows as $file) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $file), $file . ' exists');
		pg_assert_true(strpos($server, "'/" . $file) !== false || strpos($server, "'" . $file . "'") !== false, $file . ' is added by the server');
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

// Runs $steps after the Preview's document helpers (_sdPreviewDarkView(),
// _sdSetDarkThemeAttr(), _sdInsertBeforeBodyEnd(), _SD_ASSET_STAMP,
// _SD_PAGE_SCRIPTS, _sdPageScriptFiles()) with the editor script loaded
// from $script_src; the decoded JSON it prints, or null when Node is not
// installed (the test is skipped). Output that is not JSON fails the test
// and is shown: the extracted source ends in a comment, so $steps starts on
// a line of its own or it would be commented out and print nothing.
function _test_page_scripts_run($steps, $script_src = 'https://example.test/pinegrap/assets/js/style_designer.js?v=1700000000')
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$source = _test_page_scripts_designer_js();
	$from = strpos($source, "\n    function _sdPreviewDarkView(");
	$to = ($from !== false) ? strpos($source, "\n    function _sdPreviewHarness(", $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the Preview helpers are found');
	if (($from === false) || ($to === false)) {
		return null;
	}

	$script = 'var document = { currentScript: { src: ' . json_encode($script_src) . ' }, baseURI: "https://example.test/pinegrap/" };'
		. substr($source, $from, $to - $from)
		. "\n" . $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgpagescripts');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);

	$answer = json_decode($printed, true);
	pg_assert_true(($printed !== '') && (json_last_error() === JSON_ERROR_NONE), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));

	return $answer;
}

// The Preview's scripts go just before the page's own last </body>, also
// when the text before it holds letters whose lower case is longer: each
// "İ" is two code units lower-cased, and an index taken from a lower-cased
// copy cut the end tag apart (Bootstrap lost, "</body" shown as text).
function test_page_scripts_preview_inserts_before_the_last_body()
{
	$answer = _test_page_scripts_run(
		'var inj = "<script src=\"b.js\"></script>\n";'
		. 'console.log(JSON.stringify(['
		. '_sdInsertBeforeBodyEnd("<html><body><p>İSTANBUL İzmir İ</p></body></html>", inj),'
		. '_sdInsertBeforeBodyEnd("<html><body><pre>&lt;/body&gt;</pre><script>var s=\"</body>\";</script></BODY >\n</html>", inj),'
		. '_sdInsertBeforeBodyEnd("<p>İ</p>", inj)'
		. ']));'
	);
	if ($answer === null) {
		return;
	}

	pg_assert_same("<html><body><p>\u{130}STANBUL \u{130}zmir \u{130}</p><script src=\"b.js\"></script>\n</body></html>", $answer[0]);
	pg_assert_same("<html><body><pre>&lt;/body&gt;</pre><script>var s=\"</body>\";</script><script src=\"b.js\"></script>\n</BODY >\n</html>", $answer[1], 'the last end tag, in any case');
	pg_assert_same("<p>\u{130}</p><script src=\"b.js\"></script>\n", $answer[2], 'appended without an end tag');
	pg_assert_false(strpos(_test_page_scripts_js_function('_sdPreviewDocHtml'), 'toLowerCase()') !== false, 'no index from a lower-cased copy');
}

// The editor's dark view is written on <html>, where a theme switcher
// writes; on <body> too only when the page has no switcher (as on the
// canvas: Bootstrap's dark block then wins over the Themes panel's light
// block, whose `:root` also matches a dark <html>).
function test_page_scripts_preview_dark_view()
{
	$answer = _test_page_scripts_run(
		'console.log(JSON.stringify(['
		. '_sdPreviewDarkView("<html lang=\"tr\"><head></head><body class=\"x\"><p>a</p></body></html>"),'
		. '_sdPreviewDarkView("<html data-bs-theme=\"light\"><body data-bs-theme=\"light\"><button data-pg-theme-switcher=\"toggle\"></button></body></html>")'
		. ']));'
	);
	if ($answer === null) {
		return;
	}

	pg_assert_same('<html lang="tr" data-bs-theme="dark"><head></head><body class="x" data-bs-theme="dark"><p>a</p></body></html>', $answer[0]);
	pg_assert_same('<html data-bs-theme="dark"><body data-bs-theme="light"><button data-pg-theme-switcher="toggle"></button></body></html>', $answer[1], 'with a switcher <body> is left alone');
	pg_assert_contains('if (canvasDarkTheme) html = _sdPreviewDarkView(html);', _test_page_scripts_js_function('_sdPreviewDocHtml'));
}

// The platform scripts the Preview loads carry the editor's own ?v= stamp,
// so a copy cached before an upgrade is not run.
function test_page_scripts_preview_files_carry_the_editor_stamp()
{
	$answer = _test_page_scripts_run(
		'console.log(JSON.stringify([_sdPageScriptFiles("<div data-pg-theme-switcher=\"switch\"></div><a data-bs-toggle=\"popover\">x</a>"), _sdPageScriptFiles("<p>none</p>")]));'
	);
	if ($answer === null) {
		return;
	}
	pg_assert_same(array(
		'https://example.test/pinegrap/assets/js/pg_theme_switcher.js?v=1700000000',
		'https://example.test/pinegrap/assets/js/pg_bs_tips.js?v=1700000000',
	), $answer[0]);
	pg_assert_same(array(), $answer[1]);

	$bare = _test_page_scripts_run(
		'console.log(JSON.stringify(_sdPageScriptFiles("<a data-bs-toggle=\"tooltip\">x</a>")));',
		'https://example.test/pinegrap/assets/js/style_designer.js'
	);
	if ($bare !== null) {
		pg_assert_same(array('https://example.test/pinegrap/assets/js/pg_bs_tips.js'), $bare, 'no stamp, no ?v=');
	}
}

// The finished Preview document of $raw (a page markup) with the assets
// panel's $js_rows, built by _sdPreviewDocHtml() with the editor around it
// stubbed: the framework is Bootstrap from a CDN, no meta tags, no theme.
function _test_page_scripts_preview_doc($raw, $js_rows)
{
	$fn = _test_page_scripts_js_function('_sdPreviewDocHtml');
	pg_assert_true($fn !== '', '_sdPreviewDocHtml is found');
	if ($fn === '') {
		return null;
	}

	return _test_page_scripts_run(
		$fn . "\n"
		. 'var canvasDarkTheme = false, location = { protocol: "https:", host: "example.test" }, window = { OUTPUT_PATH: "/" };'
		. 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;"); }'
		. 'function _sdT(s) { return s; }'
		. 'function _sdMetaTagsEffective() { return []; }'
		. 'function _sdMetaTagAttr() { return "name"; }'
		. 'function _readAssetsFromFields() { return { cssFiles: [], jsFiles: ' . json_encode($js_rows) . ', gfFonts: [] }; }'
		. 'function _sdAssetsInPlay(list) { return (list || []).filter(function (f) { return f && f.enabled !== false; }); }'
		. 'function _sdFrameworkInfo() { return { css: "https://cdn.test/bootstrap.min.css", js: "https://cdn.test/bootstrap.bundle.min.js" }; }'
		. 'function _sdThemeHeadHtml() { return ""; }'
		. 'function _sdIconsUrl() { return "https://cdn.test/bootstrap-icons.min.css"; }'
		. 'function _sdIsBootstrapCssHref(h) { return /bootstrap(\.min)?\.css/.test(h || ""); }'
		. 'function _sdPreviewHarness() { return ""; }'
		. 'function _sdPreviewPlayHarness() { return ""; }'
		. 'console.log(JSON.stringify(_sdPreviewDocHtml({}, ' . json_encode($raw) . ')));'
	);
}

// One Bootstrap script per Preview: the assets panel's enabled Bootstrap JS
// row, or the framework's file when there is none (or it is switched off),
// never both. The marker scripts the page asks for come after it, deferred.
function test_page_scripts_preview_document_loads_one_bootstrap_bundle()
{
	$raw = '<html><head><meta charset="utf-8"></head><body><a data-bs-toggle="tooltip" title="x">y</a></body></html>';
	$row = array('type' => 'external-js', 'meta' => 'bootstrap-js', 'name' => 'Bootstrap JS', 'content' => 'https://cdn.test/own/bootstrap.bundle.js', 'enabled' => true);

	$with_row = _test_page_scripts_preview_doc($raw, array($row));
	if ($with_row === null) {
		return;
	}
	pg_assert_same(1, substr_count($with_row, 'bootstrap.bundle'), 'with the row: one bundle');
	pg_assert_contains('<script src="https://cdn.test/own/bootstrap.bundle.js"></script>', $with_row);
	pg_assert_same(1, substr_count($with_row, 'pg_bs_tips.js'), 'the tooltip marker loads the tips once');

	$row['enabled'] = false;
	foreach (array('without a row' => array(), 'with the row off' => array($row)) as $label => $rows) {
		$doc = (string) _test_page_scripts_preview_doc($raw, $rows);
		pg_assert_same(1, substr_count($doc, 'bootstrap.bundle'), $label . ': one bundle');
		pg_assert_contains('<script src="https://cdn.test/bootstrap.bundle.min.js"></script>', $doc);
		pg_assert_true(strpos($doc, 'pg_bs_tips.js') > strpos($doc, 'bootstrap.bundle.min.js'), $label . ': the tips after the framework');
	}
}
