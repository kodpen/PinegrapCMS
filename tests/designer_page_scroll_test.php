<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's "Back to top" and "Scroll progress"
 * components: the palette rows and their builders in
 * assets/js/style_designer.src.js (run under Node), the markup the site's
 * renderer prints from them, pg_page_scroll_inject() adding
 * assets/js/pg_page_scroll.js only to a page holding one of their markers,
 * the Preview's row testing the server's expression, and the rules they
 * bring to User Styles.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_page_scroll_src()
{
	return '/pinegrap/assets/js/pg_page_scroll.js?v=1';
}

function _test_page_scroll_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The two builders cut out of the editor's source and run under Node with
// the editor's createNode() shape; null when Node is not installed.
function _test_page_scroll_trees()
{
	static $trees = false;
	if ($trees !== false) {
		return $trees;
	}
	$trees = null;
	$js = _test_page_scroll_js();
	$start = strpos($js, "if (ctype === 'back-to-top') {");
	$end = strpos($js, "if (ctype === 'carousel') {", (int) $start);
	if ($start === false || $end === false) {
		pg_assert_true(false, 'the builders are found in the source');
		return $trees;
	}
	$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
	if ($node === '') {
		return $trees;
	}
	$script = "var _sdT = function (s) { return s; };\n"
		. "function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }\n"
		. "function build(ctype) {\n" . substr($js, $start, $end - $start) . "\nreturn null; }\n"
		. "process.stdout.write(JSON.stringify({ 'back-to-top': build('back-to-top'), 'scroll-progress': build('scroll-progress') }));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgps') . '.js';
	file_put_contents($file, $script);
	$out = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($file) . ' 2>&1');
	@unlink($file);
	$trees = json_decode($out, true);
	pg_assert_true(is_array($trees), 'the builders run: ' . substr($out, 0, 200));
	return $trees;
}

function _test_page_scroll_attrs($node)
{
	$out = array();
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) {
		$out[$a['name']] = $a['value'];
	}
	return $out;
}

function test_page_scroll_marker()
{
	$p = pg_page_scroll_pattern();
	pg_assert_true((bool) preg_match($p, '<a href="#top" data-pg-to-top="">'), 'the button');
	pg_assert_true((bool) preg_match($p, '<div class="progress" data-pg-scroll-progress>'), 'the bar');
	pg_assert_true((bool) preg_match($p, "<div\nDATA-PG-SCROLL-PROGRESS='1'>"), 'upper case, after a line break');
	pg_assert_false((bool) preg_match($p, '<div data-pg-to-top-offset="2">'), 'a longer attribute name');
	pg_assert_false((bool) preg_match($p, '<div data-pg-scroller="item">'), 'the sideways row');
	pg_assert_false((bool) preg_match($p, '<p>data-pg-to-top</p>'), 'the word as text');
}

function test_page_scroll_inject()
{
	$src = _test_page_scroll_src();
	$tag = '<script src="' . $src . '" defer></script>' . "\n";

	$plain = '<html><body><div data-pg-scroller="item"></div></body></html>';
	pg_assert_same($plain, pg_page_scroll_inject($plain, $src), 'a page without either component gets nothing');

	$page = '<html><body><a href="#top" data-pg-to-top="">x</a><div data-pg-scroll-progress=""></div></body></html>';
	$once = pg_page_scroll_inject($page, $src);
	pg_assert_same(str_replace('</body>', $tag . '</body>', $page), $once, 'one script before </body> for both');
	pg_assert_same($once, pg_page_scroll_inject($once, $src), 'a second call adds nothing');

	$text = '<html><body><pre>&lt;/body&gt; </body></pre><a data-pg-to-top="">x</a></body></html>';
	$out = pg_page_scroll_inject($text, $src);
	pg_assert_same(strrpos($out, $tag) + strlen($tag), strrpos($out, '</body>'), 'the script sits right before the last </body>');
	pg_assert_contains('src="/x.js?a=1&amp;b=2"', pg_page_scroll_inject('<a data-pg-to-top="">x</a>', '/x.js?a=1&b=2'), 'the address is escaped');
	pg_assert_contains('pg_page_scroll_inject($content)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the published page asks for the script');
}

function test_page_scroll_preview_pattern()
{
	$found = preg_match("~\\{ test: (/[^\\n]*?/[a-z]*), file: 'assets/js/pg_page_scroll\\.js' \\}~", _test_page_scroll_js(), $m);
	pg_assert_true((bool) $found, 'the preview has a row for pg_page_scroll.js');
	pg_assert_same(pg_page_scroll_pattern(), $found ? $m[1] : '', 'the preview tests the same expression as the server');
}

function test_page_scroll_palette_rows_and_rules()
{
	$js = _test_page_scroll_js();
	pg_assert_contains("extra: { componentType: 'back-to-top' }", $js, 'Back to top is in the palette');
	pg_assert_contains("extra: { componentType: 'scroll-progress' }", $js, 'Scroll progress is in the palette');
	pg_assert_contains("'back-to-top':          ['pg-to-top']", $js);
	pg_assert_contains("'scroll-progress':      ['pg-scroll-progress']", $js);
	pg_assert_contains("'c:back-to-top':", $js, 'a hover card');
	pg_assert_contains("'c:scroll-progress':", $js, 'a hover card');

	$css = pg_designer_block_css(array('back-to-top', 'scroll-progress'));
	pg_assert_contains('.pg-to-top {', $css);
	pg_assert_contains('prefers-reduced-motion', $css, 'the fade stops under reduced motion');
	pg_assert_contains('.pg-scroll-progress { --bs-progress-height: .25rem;', $css);
	pg_assert_false(strpos($css, '!important') !== false, 'no !important');
}

function test_page_scroll_builders_print_their_markers()
{
	$trees = _test_page_scroll_trees();
	if ($trees === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}

	$top = $trees['back-to-top'];
	pg_assert_same('a', $top['props']['tag']);
	pg_assert_same('#top', $top['props']['href'], 'the top of the page without the script');
	pg_assert_same('Back to top', _test_page_scroll_attrs($top)['aria-label'], 'the icon-only link is named');
	pg_assert_contains('pg-to-top', $top['props']['cssClass']);
	pg_assert_false(strpos($top['props']['cssClass'], 'invisible') !== false, 'shown on the canvas and without the script');

	$bar = $trees['scroll-progress'];
	pg_assert_same('true', _test_page_scroll_attrs($bar)['aria-hidden'], 'the bar is decoration');
	pg_assert_contains('pg-scroll-progress', $bar['props']['cssClass']);
	pg_assert_same('progress-bar', $bar['children'][0]['props']['cssClass']);

	$html = _render_tree_node($top) . _render_tree_node($bar);
	pg_assert_true((bool) preg_match('/<a\b[^>]*\shref="#top"[^>]*\sdata-pg-to-top/', $html) || (bool) preg_match('/<a\b[^>]*\sdata-pg-to-top[^>]*\shref="#top"/', $html), 'the button prints its marker and address');
	pg_assert_true((bool) preg_match('/<div\b[^>]*\sdata-pg-scroll-progress/', $html), 'the bar prints its marker');
	foreach (array('<script', ' onclick', ' onscroll') as $never) {
		pg_assert_false(stripos($html, $never) !== false, 'no ' . trim($never));
	}
	$page = '<html><body>' . $html . '</body></html>';
	pg_assert_contains('pg_page_scroll.js', pg_page_scroll_inject($page, _test_page_scroll_src()), 'the printed markup brings the script');
}
