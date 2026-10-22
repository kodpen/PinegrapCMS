<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the attributes every content and semantic node carries on the
 * page (_render_tree_node()) and in the editor's HTML (_sdEmitNode() and
 * addGlobalAttrs() in assets/js/style_designer.js): one style attribute in
 * one order (font, old inline style, the type's own style, Display & Sizing,
 * the Attributes panel's style), the sizing classes once, the title and
 * tooltip, and the Attributes panel's entries without a second class, style,
 * id or built-in attribute. The editor's emitter is cut out of
 * style_designer.js and run under Node; on a machine without Node that half
 * is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Attributes panel entries from name => value pairs.
function _test_designer_attrs_list($list)
{
	$out = array();

	foreach ($list as $name => $value) {
		$out[] = array('name' => $name, 'value' => $value);
	}

	return $out;
}

// A content node of a type with props, no children.
function _test_designer_attrs_content($type, $props)
{
	return array('type' => 'content', 'props' => array_merge(array('contentType' => $type), $props), 'children' => array());
}

// A semantic node with props, no children.
function _test_designer_attrs_semantic($props)
{
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

// The opening tag the page gets for a node; an image's <img> tag.
function _test_designer_attrs_php($node)
{
	$html = _render_tree_node($node, 0, 0);
	$image = isset($node['props']['contentType']) && $node['props']['contentType'] === 'image';

	return preg_match($image ? '~<img\b[^>]*>~' : '~^\s*(<[^>]*>)~', $html, $m) ? trim($m[0]) : '';
}

// The nodes both sides are compared on.
function _test_designer_attrs_cases()
{
	$a = '_test_designer_attrs_list';

	return array(
		'heading font and inline style' => _test_designer_attrs_content('heading', array('tag' => 'h2', 'text' => 'Hi', 'align' => 'center', 'id' => 'x1', 'fontFamily' => 'Press Start 2P', 'inlineStyle' => 'color:red; ', '_attrs' => $a(array('style' => 'margin:0;')))),
		'paragraph bare font' => _test_designer_attrs_content('paragraph', array('text' => 'P', 'fontFamily' => 'Inter')),
		'span tooltip' => _test_designer_attrs_content('span', array('text' => 'S', 'title' => 'Plain', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip "1"', 'tooltipPlacement' => 'bottom', '_attrs' => $a(array('data-k' => 'v')))),
		'text title' => _test_designer_attrs_content('text', array('text' => 'T', 'title' => 'Plain')),
		'heading tooltip and popover' => _test_designer_attrs_content('heading', array('tag' => 'h3', 'text' => 'H', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip', '_attrs' => $a(array('data-bs-toggle' => 'popover', 'title' => 'Typed')))),
		'divider sizing' => _test_designer_attrs_content('divider', array('margin' => '3', 'maxWidth' => '30rem', 'srOnly' => true, 'cssClass' => 'visually-hidden')),
		'paragraph sizing not in cssClass' => _test_designer_attrs_content('paragraph', array('text' => 'P', 'srOnly' => true, 'minHeight' => '4rem')),
		'heading attributes panel' => _test_designer_attrs_content('heading', array('tag' => 'h2', 'text' => 'H', '_attrs' => $a(array('class' => 'ac', 'Style' => 'gap:1px', 'ID' => 'dup', 'aria-label' => 'L')))),
		'link built-ins' => _test_designer_attrs_content('link', array('text' => 'L', 'href' => '/x', 'target' => '_blank', 'cssClass' => 'link-primary', '_attrs' => $a(array('href' => '/stale', 'target' => '_self', 'rel' => 'nofollow')))),
		'icon own style' => _test_designer_attrs_content('icon', array('iconName' => 'bi-star', 'fontSize' => '24', 'fontFamily' => 'Inter', 'width' => '2rem', '_attrs' => $a(array('style' => 'color:gold')))),
		'image own size' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'width' => '72', 'height' => '72', 'maxWidth' => '100%', 'objectPosition' => 'top', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip')),
		'image in a ratio box' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'aspectRatio' => '16x9', 'overflow' => 'hidden', 'minHeight' => '5rem', 'id' => 'pic')),
		'spacer own height' => _test_designer_attrs_content('spacer', array('height' => '40', 'minWidth' => '1rem', 'id' => 'gap')),
		'custom html as written' => _test_designer_attrs_content('custom_html', array('html' => '<div class="x">', 'id' => 'not-written', 'fontFamily' => 'Inter')),
		'semantic font and style' => _test_designer_attrs_semantic(array('tag' => 'section', 'cssClass' => 'py-5', 'id' => 's1', 'fontFamily' => 'IBM Plex Mono', 'inlineStyle' => 'color:blue', 'width' => '10rem', '_attrs' => $a(array('style' => 'padding:0;', 'data-x' => '1')))),
		'semantic tooltip' => _test_designer_attrs_semantic(array('tag' => 'button', 'text' => 'B', 'cssClass' => 'btn', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip', 'tooltipPlacement' => 'end', '_attrs' => $a(array('type' => 'button')))),
		'semantic modal toggle and tooltip' => _test_designer_attrs_semantic(array('tag' => 'button', 'text' => 'B', 'tooltipEnabled' => true, 'tooltipTitle' => 'Tip', '_attrs' => $a(array('data-bs-toggle' => 'modal', 'data-bs-target' => '#m')))),
		'semantic attributes panel' => _test_designer_attrs_semantic(array('tag' => 'div', 'cssClass' => 'a', '_attrs' => $a(array('class' => 'ac', 'Style' => 'gap:1px', 'ID' => 'dup', 'aria-label' => 'L')))),
		'semantic sizing once' => _test_designer_attrs_semantic(array('tag' => 'div', 'srOnly' => true, 'cssClass' => 'p-2 visually-hidden')),
		'semantic link' => _test_designer_attrs_semantic(array('tag' => 'a', 'href' => '/y', 'rel' => 'noopener', 'text' => 'Go', '_attrs' => $a(array('href' => '/stale', 'title' => 'Typed')))),
		'semantic image' => _test_designer_attrs_semantic(array('tag' => 'img', 'src' => 'https://example.com/b.png', 'alt' => 'B', '_attrs' => $a(array('src' => '/stale')))),
		'semantic search section' => _test_designer_attrs_semantic(array('tag' => 'section', 'id' => 'faq', 'searchSection' => true, 'cssClass' => 'py-5')),
		'heading search section' => _test_designer_attrs_content('heading', array('tag' => 'h2', 'text' => 'Q', 'id' => 'q1', 'searchSection' => true)),
	);
}

// The opening tags the editor's emitter gives the nodes (an image's <img>
// tag, the first tag with $first), null without Node.
function _test_designer_attrs_js($nodes, $first = false)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$parts = array(
		array('var CONTENT = {', '// ========================= IFRAME CSS'),
		array('var _SD_CONTENT_GLOBAL_ATTRS', '// ── Sizing / Tooltip / A11y helpers ──'),
		array('function _sdSizingValue(', '// Set of all sizing-managed'),
		array('function getNodeExtraClasses(', '// ========================= TREE VIEW & HTML GEN'),
		array('function _sdEmitNode(', '// Children written into a component'),
		array('function buildColClasses(', '// ========================= ROW / COL CLASS PARSERS'),
	);
	// esc() of the designer without its DOM, the text helper and no SVG
	// parser (no case here draws an SVG).
	$script = 'function esc(s) { if (!s) return ""; return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }'
		. 'function _sdT(s) { return s; } function _sdSvgElement() { return null; }';

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
		. 'console.log(JSON.stringify(nodes.map(function (n) { var h = _sdEmitNode(n, 0, 0, null);'
		. ' var m = (n.props.contentType === "image" && !' . ($first ? 'true' : 'false') . ' ? /<img\b[^>]*>/ : /^\s*<[^>]*>/).exec(h);'
		. ' return m ? m[0].trim() : ""; })));';

	$file = tempnam(sys_get_temp_dir(), 'pgattrs');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	$tags = json_decode(implode("\n", $out), true);

	return is_array($tags) ? array_combine(array_keys($nodes), $tags) : null;
}

// -- the page ----------------------------------------------------------------

// One style attribute: the font (quoted when it holds a space, with its
// fallback), the old inline style field, the type's own style, the sizing
// props, then the Attributes panel's style.
function test_designer_attrs_style_order()
{
	$cases = _test_designer_attrs_cases();

	pg_assert_same('<h2 class="text-center" style="font-family:&#039;Press Start 2P&#039;,sans-serif;color:red;margin:0" id="x1">', _test_designer_attrs_php($cases['heading font and inline style']));
	pg_assert_same('<p style="font-family:Inter,sans-serif">', _test_designer_attrs_php($cases['paragraph bare font']));
	pg_assert_same('<i class="bi bi-star" style="font-family:Inter,sans-serif;font-size:24px;width:2rem;color:gold">', _test_designer_attrs_php($cases['icon own style']));
	pg_assert_same('<section id="s1" class="py-5" data-x="1" style="font-family:&#039;IBM Plex Mono&#039;,sans-serif;color:blue;width:10rem;padding:0">', _test_designer_attrs_php($cases['semantic font and style']));
}

// The tooltip's text is the title while it is on, then its toggle and
// placement. An element that is another toggle's (a modal, a popover) gets
// no tooltip; title and placement yield to a panel entry of the same name.
function test_designer_attrs_title_and_tooltip()
{
	$cases = _test_designer_attrs_cases();

	pg_assert_same('<span title="Tip &quot;1&quot;" data-bs-toggle="tooltip" data-bs-placement="bottom" data-k="v">', _test_designer_attrs_php($cases['span tooltip']));
	pg_assert_same('<span title="Plain">', _test_designer_attrs_php($cases['text title']));
	pg_assert_same('<h3 data-bs-toggle="popover" title="Typed">', _test_designer_attrs_php($cases['heading tooltip and popover']));
	pg_assert_same('<button class="btn" title="Tip" data-bs-toggle="tooltip" data-bs-placement="end" type="button">', _test_designer_attrs_php($cases['semantic tooltip']));
	pg_assert_same('<button data-bs-toggle="modal" data-bs-target="#m">', _test_designer_attrs_php($cases['semantic modal toggle and tooltip']), 'a modal toggle keeps working');
}

// The Display & Sizing classes once, their style on a content node too; an
// image's and a spacer's own size props are not sizing.
function test_designer_attrs_sizing()
{
	$cases = _test_designer_attrs_cases();

	pg_assert_same('<hr class="my-3 visually-hidden" style="max-width:30rem">', _test_designer_attrs_php($cases['divider sizing']));
	pg_assert_same('<p class="visually-hidden" style="min-height:4rem">', _test_designer_attrs_php($cases['paragraph sizing not in cssClass']));
	pg_assert_same('<div class="p-2 visually-hidden">', _test_designer_attrs_php($cases['semantic sizing once']));
	pg_assert_same('<div style="height:40px;min-width:1rem" id="gap">', _test_designer_attrs_php($cases['spacer own height']));
	pg_assert_contains('width="72" height="72" style="object-position:top;max-width:100%" title="Tip" data-bs-toggle="tooltip" data-bs-placement="top"', _test_designer_attrs_php($cases['image own size']));
	pg_assert_contains('alt="A" id="pic"', _test_designer_attrs_php($cases['image in a ratio box']), 'the image keeps its id, the box takes the sizing style');
	pg_assert_contains('<div class="ratio ratio-16x9" style="min-height:5rem;overflow:hidden">', _render_tree_node($cases['image in a ratio box'], 0, 0), 'the ratio box takes the sizing style');
}

// The Attributes panel's list never writes a second class, style or id, or
// an attribute the node's props wrote.
function test_designer_attrs_panel_entries()
{
	$cases = _test_designer_attrs_cases();

	pg_assert_same('<h2 style="gap:1px" aria-label="L">', _test_designer_attrs_php($cases['heading attributes panel']));
	pg_assert_same('<div class="a" aria-label="L" style="gap:1px">', _test_designer_attrs_php($cases['semantic attributes panel']));
	pg_assert_same('<a href="/x" class="link-primary" target="_blank" rel="nofollow">', _test_designer_attrs_php($cases['link built-ins']));
	pg_assert_same('<a href="/y" rel="noopener" title="Typed">', _test_designer_attrs_php($cases['semantic link']));
	pg_assert_contains('<img src="https://example.com/b.png" alt="B"', _test_designer_attrs_php($cases['semantic image']));
	pg_assert_false(strpos(_test_designer_attrs_php($cases['semantic image']), '/stale') !== false, 'the stale src is not written');
	pg_assert_same('<div class="x">', _test_designer_attrs_php($cases['custom html as written']));
}

// -- the editor ----------------------------------------------------------------

// The editor's HTML (code view, preview, revision compare) writes the same
// attributes in the same order. h() writes a single quote as &#039;, the
// editor's esc() leaves it; an image's onerror and the search section mark
// (set by the Search section switch) are the page's alone.
function test_designer_attrs_editor_writes_the_same()
{
	$cases = _test_designer_attrs_cases();
	$js = _test_designer_attrs_js($cases);

	if ($js === null) {
		return;
	}

	foreach ($cases as $name => $node) {
		$php = str_replace('&#039;', "'", _test_designer_attrs_php($node));
		$php = preg_replace('~ onerror="[^"]*"| data-pg-search-section~', '', $php);
		pg_assert_same($php, $js[$name], $name);
	}

	pg_assert_same('<section id="faq" class="py-5">', $js['semantic search section']);
	pg_assert_same('<h2 id="q1">', $js['heading search section']);
}

// An image in a ratio box: the box takes the node's whole sizing style in
// its order, the image's width and height in it (a bare number in px), and
// the image's rounded classes in their order, on the page and in the
// editor's HTML.
function test_designer_attrs_image_ratio_box()
{
	$nodes = array(
		'sized' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'aspectRatio' => '1x1', 'width' => '200', 'height' => '50%', 'overflow' => 'hidden')),
		'rounded' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'aspectRatio' => '4x3', 'rounded' => true, 'cssClass' => 'shadow rounded-3', 'width' => '20rem')),
		'plain' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'aspectRatio' => '16x9')),
		'whole style' => _test_designer_attrs_content('image', array('src' => 'https://example.com/a.png', 'alt' => 'A', 'aspectRatio' => '1x1', 'overflow' => 'hidden', 'minHeight' => '5rem', 'width' => '200', 'zIndex' => '2', 'inlineStyle' => 'opacity:.5')),
	);
	$php = array();

	foreach ($nodes as $name => $node) {
		$php[$name] = trim((string) strtok(_render_tree_node($node, 0, 0), "\n"));
	}

	pg_assert_same('<div class="ratio ratio-1x1" style="width:200px;height:50%;overflow:hidden">', $php['sized']);
	pg_assert_same('<div class="ratio ratio-4x3 rounded rounded-3" style="width:20rem">', $php['rounded']);
	pg_assert_same('<div class="ratio ratio-16x9">', $php['plain']);
	pg_assert_same('<div class="ratio ratio-1x1" style="z-index:2;width:200px;min-height:5rem;overflow:hidden">', $php['whole style']);

	$js = _test_designer_attrs_js($nodes, true);

	if ($js === null) {
		return;
	}

	foreach ($php as $name => $tag) {
		pg_assert_same($tag, $js[$name], $name);
	}

	// The <img> keeps its own style, without the box's.
	$img = preg_match('~<img\b[^>]*>~', _render_tree_node($nodes['whole style'], 0, 0), $m) ? preg_replace('~ onerror="[^"]*"~', '', $m[0]) : '';
	pg_assert_same('<img src="https://example.com/a.png" alt="A" width="200" style="opacity:.5">', $img);
	$js_img = _test_designer_attrs_js(array($nodes['whole style']));
	pg_assert_same($img, $js_img[0]);
}

// The page marks a search section after its id, for the structured data.
function test_designer_attrs_search_mark_on_the_page()
{
	$cases = _test_designer_attrs_cases();

	pg_assert_same('<section id="faq" data-pg-search-section class="py-5">', _test_designer_attrs_php($cases['semantic search section']));
	pg_assert_same('<h2 id="q1" data-pg-search-section>', _test_designer_attrs_php($cases['heading search section']));
}
