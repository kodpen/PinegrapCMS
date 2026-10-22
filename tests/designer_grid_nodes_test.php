<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the opening tag of the designer's grid nodes (container, row,
 * col) on the page (_render_tree_node()) and in the editor's HTML
 * (_sdEmitNode() in assets/js/style_designer.js): each class once and
 * escaped once, the sizing style before the Attributes panel's style, then
 * the id and the panel's other attributes, the same on both sides. The
 * editor's emitter is cut out of style_designer.js and run under Node; on a
 * machine without Node that half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A grid node with no children.
function _test_designer_grid_node($type, $props)
{
	return array('type' => $type, 'props' => $props, 'children' => array());
}

// The opening tag the page gets for a node.
function _test_designer_grid_php($node)
{
	return trim((string) strtok(_render_tree_node($node, 0, 0), "\n"));
}

// The nodes both sides are compared on.
function _test_designer_grid_cases()
{
	$attrs = function ($list) {
		$out = array();

		foreach ($list as $name => $value) {
			$out[] = array('name' => $name, 'value' => $value);
		}

		return $out;
	};

	return array(
		'col with classes' => _test_designer_grid_node('col', array('xs' => '12', 'lg' => '8', 'cssClass' => 'mx-auto text-center')),
		'col offsets' => _test_designer_grid_node('col', array('xs' => 'col', 'offsetXs' => '1', 'offsetMd' => '2', 'order' => 'last')),
		'col auto' => _test_designer_grid_node('col', array('xs' => 'auto', 'md' => '6', 'lg' => 'auto')),
		'col without widths' => _test_designer_grid_node('col', array('xs' => '', 'sm' => '', 'md' => '', 'lg' => '', 'xl' => '', 'xxl' => '')),
		'col equal at breakpoints' => _test_designer_grid_node('col', array('xs' => '12', 'md' => 'col', 'xxl' => 'col')),
		'col width class in cssClass' => _test_designer_grid_node('col', array('xs' => '12', 'cssClass' => 'col-12 py-3')),
		'col id and attributes' => _test_designer_grid_node('col', array('xs' => '6', 'id' => 'side', '_attrs' => $attrs(array('data-x' => '1', 'aria-label' => 'Side "a"', 'style' => 'color:red')))),
		'col search section' => _test_designer_grid_node('col', array('xs' => '12', 'id' => 'faq', 'searchSection' => true)),
		'col sizing' => _test_designer_grid_node('col', array('xs' => '6', 'width' => '50%', 'minHeight' => '10rem', 'srOnly' => true, 'cssClass' => 'visually-hidden')),
		'row' => _test_designer_grid_node('row', array('gutter' => '4', 'justify' => 'center', 'align' => 'end', 'cssClass' => 'g-lg-5', 'id' => 'r1', '_attrs' => $attrs(array('role' => 'list', 'Style' => 'gap:1rem')))),
		'row ratio' => _test_designer_grid_node('row', array('maxWidth' => '60rem', 'aspectRatioEnabled' => true, 'aspectRatio' => '4x3', 'cssClass' => 'ratio ratio-4x3')),
		'container' => _test_designer_grid_node('container', array('cssClass' => 'py-5')),
		'container fluid' => _test_designer_grid_node('container', array('fluid' => true, 'cssClass' => 'py-5 bg-body-tertiary', 'id' => 'top', '_attrs' => $attrs(array('data-bs-theme' => 'dark', 'class' => 'x', 'ID' => 'y')))),
		'container escaping' => _test_designer_grid_node('container', array('cssClass' => 'a"b&c', '_attrs' => $attrs(array('title' => 'x<y & "z"')))),
		'container sizing order' => _test_designer_grid_node('container', array('zIndex' => '5', 'left' => '1rem', 'top' => '0', 'overflowX' => 'auto', 'height' => '10px', 'width' => '5px')),
		'container zero and z-index' => _test_designer_grid_node('container', array('zIndex' => '0', 'bottom' => 0, 'minWidth' => '', 'maxHeight' => '50vh')),
		'container bad z-index' => _test_designer_grid_node('container', array('zIndex' => '9; color:red', 'width' => 'calc(100% - 2rem)')),
		'row ratio unpicked' => _test_designer_grid_node('row', array('aspectRatioEnabled' => true)),
		'row ratio custom' => _test_designer_grid_node('row', array('aspectRatioEnabled' => true, 'aspectRatio' => 'custom', 'aspectRatioCustom' => '40%')),
		'col style escaping' => _test_designer_grid_node('col', array('xs' => '6', 'maxWidth' => 'min(40rem, 100% - "a" & b)')),
		'container toggles' => _test_designer_grid_node('container', array('focusRing' => true, 'placeholderMode' => true, 'placeholderAnim' => 'wave', 'cssClass' => 'focus-ring placeholder placeholder-wave')),
		'container font and inline style' => _test_designer_grid_node('container', array('fontFamily' => 'Press Start 2P', 'inlineStyle' => 'color:red; ', 'maxWidth' => '60rem', '_attrs' => $attrs(array('style' => 'margin:0;')))),
		'row bare font' => _test_designer_grid_node('row', array('fontFamily' => 'Inter')),
		'col title' => _test_designer_grid_node('col', array('xs' => '6', 'id' => 'c1', 'title' => 'Plain "t"')),
		'col tooltip' => _test_designer_grid_node('col', array('xs' => '6', 'title' => 'Plain', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip', 'tooltipPlacement' => 'bottom', '_attrs' => $attrs(array('data-x' => '1')))),
		'row tooltip without placement' => _test_designer_grid_node('row', array('tooltipEnabled' => true, 'tooltipTitle' => 'Tip')),
		'container tooltip off' => _test_designer_grid_node('container', array('tooltipEnabled' => false, 'tooltipTitle' => 'Tip')),
		'col tooltip and popover' => _test_designer_grid_node('col', array('xs' => '12', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip', '_attrs' => $attrs(array('data-bs-toggle' => 'popover', 'data-bs-placement' => 'end')))),
	);
}

// The opening tags the editor's emitter gives the nodes, null without Node.
function _test_designer_grid_js($nodes)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$parts = array(
		array('function _sdEmitNode(', '// Children written into a component'),
		array('function buildColClasses(', '// ========================= ROW / COL CLASS PARSERS'),
		array('function _sdSizingValue(', '// Set of all sizing-managed'),
		array('function getNodeExtraClasses(', '// ========================= TREE VIEW & HTML GEN'),
	);
	// esc() of the designer, without its DOM: a text node's innerHTML with
	// the double quote escaped.
	$script = 'function esc(s) { if (!s) return ""; return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }';

	foreach ($parts as $part) {
		$from = strpos($source, $part[0]);
		$to = ($from !== false) ? strpos($source, $part[1], $from) : false;
		pg_assert_true(($from !== false) && ($to !== false), 'the designer has ' . $part[0]);

		if (($from === false) || ($to === false)) {
			return null;
		}

		$script .= substr($source, $from, $to - $from);
	}

	$script .= 'var nodes = ' . json_encode(array_values($nodes)) . ';'
		. 'console.log(JSON.stringify(nodes.map(function (n) { return _sdEmitNode(n, 0, 0, null).split("\n")[0].trim(); })));';

	$file = tempnam(sys_get_temp_dir(), 'pggrid');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	$tags = json_decode(implode("\n", $out), true);

	return is_array($tags) ? array_combine(array_keys($nodes), $tags) : null;
}

// -- the page ----------------------------------------------------------------

// A column's cssClass is written once: _build_col_classes() ends with it.
function test_designer_grid_col_css_class_once()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="col-12 col-lg-8 mx-auto text-center">', _test_designer_grid_php($cases['col with classes']));
	pg_assert_same('<div class="col-12 py-3">', _test_designer_grid_php($cases['col width class in cssClass']), 'a width class also typed into cssClass');
	pg_assert_same('<div class="col offset-1 offset-md-2 order-last">', _test_designer_grid_php($cases['col offsets']));
}

// A column without a width is a full row, the 12 its Column options show;
// Equal at a breakpoint is Bootstrap's col-<bp>.
function test_designer_grid_col_widths()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="col-12">', _test_designer_grid_php($cases['col without widths']));
	pg_assert_same('<div class="col-12 col-md col-xxl">', _test_designer_grid_php($cases['col equal at breakpoints']));
	pg_assert_same('<div class="col-auto col-md-6 col-lg-auto">', _test_designer_grid_php($cases['col auto']));
}

// A Display & Sizing toggle's class is in cssClass as well: written once.
function test_designer_grid_toggle_class_once()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container focus-ring placeholder placeholder-wave">', _test_designer_grid_php($cases['container toggles']));
	pg_assert_same('<div class="row ratio ratio-4x3" style="max-width:60rem">', _test_designer_grid_php($cases['row ratio']));
}

// The sizing props in one order, 0 as a value, z-index only as an integer
// or auto, a ratio switched on with no preset as 16:9; the style escaped once.
function test_designer_grid_sizing()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container" style="width:5px;height:10px;overflow-x:auto;top:0;left:1rem;z-index:5">', _test_designer_grid_php($cases['container sizing order']));
	pg_assert_same('<div class="container" style="max-height:50vh;bottom:0;z-index:0">', _test_designer_grid_php($cases['container zero and z-index']));
	pg_assert_same('<div class="container" style="width:calc(100% - 2rem)">', _test_designer_grid_php($cases['container bad z-index']));
	pg_assert_same('<div class="row ratio ratio-16x9">', _test_designer_grid_php($cases['row ratio unpicked']));
	pg_assert_same('<div class="row ratio" style="--bs-aspect-ratio:40%">', _test_designer_grid_php($cases['row ratio custom']));
	pg_assert_same('<div class="col-6" style="max-width:min(40rem, 100% - &quot;a&quot; &amp; b)">', _test_designer_grid_php($cases['col style escaping']));
}

// The sizing style, then the Attributes panel's style, the id and the
// panel's other attributes; class and id in the list are not printed.
function test_designer_grid_attributes()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="col-6" style="color:red" id="side" data-x="1" aria-label="Side &quot;a&quot;">', _test_designer_grid_php($cases['col id and attributes']));
	pg_assert_same('<div class="col-12" id="faq" data-pg-search-section>', _test_designer_grid_php($cases['col search section']));
	pg_assert_same('<div class="row g-4 justify-content-center align-items-end g-lg-5" style="gap:1rem" id="r1" role="list">', _test_designer_grid_php($cases['row']));
	pg_assert_same('<div class="container-fluid py-5 bg-body-tertiary" id="top" data-bs-theme="dark">', _test_designer_grid_php($cases['container fluid']));
}

// The font, the old inline style field, the title and the tooltip, which
// the canvas applies to a grid node, are written as on any other node: the
// style in _compose_inline_style()'s order, the title and the tooltip after
// the id; an element that is another toggle's gets no tooltip.
function test_designer_grid_font_style_title_tooltip()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container" style="font-family:&#039;Press Start 2P&#039;,sans-serif;color:red;max-width:60rem;margin:0">', _test_designer_grid_php($cases['container font and inline style']));
	pg_assert_same('<div class="row" style="font-family:Inter,sans-serif">', _test_designer_grid_php($cases['row bare font']));
	pg_assert_same('<div class="col-6" id="c1" title="Plain &quot;t&quot;">', _test_designer_grid_php($cases['col title']));
	pg_assert_same('<div class="col-6" title="Tip" data-bs-toggle="tooltip" data-bs-placement="bottom" data-x="1">', _test_designer_grid_php($cases['col tooltip']));
	pg_assert_same('<div class="row" title="Tip" data-bs-toggle="tooltip" data-bs-placement="top">', _test_designer_grid_php($cases['row tooltip without placement']));
	pg_assert_same('<div class="container">', _test_designer_grid_php($cases['container tooltip off']));
	pg_assert_same('<div class="col-12" data-bs-toggle="popover" data-bs-placement="end">', _test_designer_grid_php($cases['col tooltip and popover']));
}

// The class and the attributes are escaped once.
function test_designer_grid_escaped_once()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container a&quot;b&amp;c" title="x&lt;y &amp; &quot;z&quot;">', _test_designer_grid_php($cases['container escaping']));
}

// -- the editor ----------------------------------------------------------------

// The editor's HTML (code view, preview, revision compare) opens every grid
// node as the page does. h() writes a single quote as &#039;, the editor's
// esc() leaves it; both read the same. The search section mark is the
// page's alone: the switch in the options sets it, so the code view does
// not show an attribute that typing could not change.
function test_designer_grid_editor_writes_the_same()
{
	$cases = _test_designer_grid_cases();
	$js = _test_designer_grid_js($cases);

	if ($js === null) {
		return;
	}

	foreach ($cases as $name => $node) {
		$php = str_replace(array('&#039;', ' data-pg-search-section'), array("'", ''), _test_designer_grid_php($node));
		pg_assert_same($php, $js[$name], $name);
	}

	pg_assert_same('<div class="col-12" id="faq">', $js['col search section'], 'no search section mark in the editor');
}

// A search section mark typed into the Attributes panel is not written a
// second time beside the switch's.
function test_designer_grid_search_mark_once()
{
	$node = _test_designer_grid_node('col', array('xs' => '12', 'id' => 'faq', 'searchSection' => true, '_attrs' => array(array('name' => 'data-pg-search-section', 'value' => ''))));
	pg_assert_same('<div class="col-12" id="faq" data-pg-search-section>', _test_designer_grid_php($node));

	$node = array('type' => 'semantic', 'props' => array('tag' => 'section', 'id' => 'faq', 'searchSection' => true, '_attrs' => array(array('name' => 'Data-Pg-Search-Section', 'value' => ''))), 'children' => array());
	pg_assert_same('<section id="faq" data-pg-search-section></section>', trim(_render_tree_node($node, 0, 0)));
}

// The three cases of the emitter open their tag in one place.
function test_designer_grid_emitter_uses_one_open_tag()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdEmitNode(');
	$to = ($from !== false) ? strpos($source, "case 'region':", $from) : false;
	$cases = ($from !== false && $to !== false) ? substr($source, $from, $to - $from) : '';

	pg_assert_true($cases !== '', '_sdEmitNode() is found');
	pg_assert_same(3, substr_count($cases, '_sdGridOpenTag(n, '), 'container, row and col call _sdGridOpenTag()');
}
