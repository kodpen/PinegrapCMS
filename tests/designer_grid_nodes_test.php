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
		'col width class in cssClass' => _test_designer_grid_node('col', array('xs' => '12', 'cssClass' => 'col-12 py-3')),
		'col id and attributes' => _test_designer_grid_node('col', array('xs' => '6', 'id' => 'side', '_attrs' => $attrs(array('data-x' => '1', 'aria-label' => 'Side "a"', 'style' => 'color:red')))),
		'col search section' => _test_designer_grid_node('col', array('xs' => '12', 'id' => 'faq', 'searchSection' => true)),
		'col sizing' => _test_designer_grid_node('col', array('xs' => '6', 'width' => '50%', 'minHeight' => '10rem', 'srOnly' => true, 'cssClass' => 'visually-hidden')),
		'row' => _test_designer_grid_node('row', array('gutter' => '4', 'justify' => 'center', 'align' => 'end', 'cssClass' => 'g-lg-5', 'id' => 'r1', '_attrs' => $attrs(array('role' => 'list', 'Style' => 'gap:1rem')))),
		'row ratio' => _test_designer_grid_node('row', array('maxWidth' => '60rem', 'aspectRatioEnabled' => true, 'aspectRatio' => '4x3', 'cssClass' => 'ratio ratio-4x3')),
		'container' => _test_designer_grid_node('container', array('cssClass' => 'py-5')),
		'container fluid' => _test_designer_grid_node('container', array('fluid' => true, 'cssClass' => 'py-5 bg-body-tertiary', 'id' => 'top', '_attrs' => $attrs(array('data-bs-theme' => 'dark', 'class' => 'x', 'ID' => 'y')))),
		'container escaping' => _test_designer_grid_node('container', array('cssClass' => 'a"b&c', '_attrs' => $attrs(array('title' => 'x<y & "z"')))),
		'container toggles' => _test_designer_grid_node('container', array('focusRing' => true, 'placeholderMode' => true, 'placeholderAnim' => 'wave', 'cssClass' => 'focus-ring placeholder placeholder-wave')),
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
		array('function getNodeExtraStyle(', '// Set of all sizing-managed'),
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

// A Display & Sizing toggle's class is in cssClass as well: written once.
function test_designer_grid_toggle_class_once()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container focus-ring placeholder placeholder-wave">', _test_designer_grid_php($cases['container toggles']));
	pg_assert_same('<div class="row ratio ratio-4x3" style="max-width:60rem">', _test_designer_grid_php($cases['row ratio']));
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

// The class and the attributes are escaped once.
function test_designer_grid_escaped_once()
{
	$cases = _test_designer_grid_cases();

	pg_assert_same('<div class="container a&quot;b&amp;c" title="x&lt;y &amp; &quot;z&quot;">', _test_designer_grid_php($cases['container escaping']));
}

// -- the editor ----------------------------------------------------------------

// The editor's HTML (code view, preview, revision compare) opens every grid
// node as the page does.
function test_designer_grid_editor_writes_the_same()
{
	$cases = _test_designer_grid_cases();
	$js = _test_designer_grid_js($cases);

	if ($js === null) {
		return;
	}

	foreach ($cases as $name => $node) {
		pg_assert_same(_test_designer_grid_php($node), $js[$name], $name);
	}
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
