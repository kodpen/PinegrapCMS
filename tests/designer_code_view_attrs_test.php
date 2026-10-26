<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for editing, in the visual designer's code view, the attributes a
 * node prints from its props: the style (font, old inline style field,
 * Attributes panel style), the title and the tooltip. What is typed goes
 * back to the props it was printed from, so the next refresh prints what
 * was typed. The code view's attribute writer and the emitter's style and
 * title helpers are cut out of assets/js/style_designer.js and run under
 * Node; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $steps after the code view's attribute writer and the emitter's
// attribute helpers; null without Node.
function designer_code_view_attrs_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$parts = array(
		array('function _sdCodeSetAttr(', "// Where a plain element's single text sits"),
		array('function _sdInlineStyleProps(', 'function _toggleInlineDecl('),
		array('function _sdUniqueClasses(', '// Children written into a component'),
		array('function _sdContentSizingNode(', '// The props a content node'),
		array('function _sdSizingValue(', '// Set of all sizing-managed'),
	);
	$script = 'function esc(s) { if (!s) return ""; return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }'
		. 'function _sdCodeApplyBoundValue() { return false; } function _sdRetargetLabels() {}';

	foreach ($parts as $part) {
		$from = strpos($source, $part[0]);
		$to = ($from !== false) ? strpos($source, $part[1], $from) : false;
		pg_assert_true(($from !== false) && ($to !== false), 'the designer has ' . $part[0]);

		if (($from === false) || ($to === false)) {
			return null;
		}

		$script .= substr($source, $from, $to - $from);
	}

	// What the emitter prints for a node's style (with the Display & Sizing
	// style, as _sdGridOpenTag() writes it) and its title attributes.
	$script .= 'function printed(n) { return { style: _sdNodeStyle(n.props, "", getNodeExtraStyle(n)), attrs: _sdNodeExtraAttrs(n.props, n.props._attrs, []) }; }'
		. $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgcodeattrs');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A style edited in the code view: the font goes back to the font picker
// as its bare family, the old inline style field is folded into the typed
// style, and the next print is what was typed.
function test_designer_code_view_style_round_trip()
{
	$answer = designer_code_view_attrs_test_run(
		'var n = { type: "col", props: { fontFamily: "Press Start 2P", inlineStyle: "color:red", _attrs: [{ name: "style", value: "margin:0" }] } };'
		. 'var was = printed(n).style;'
		. '_sdCodeSetAttr(n, "style", was, "font-family:\'Press Start 2P\',sans-serif;color:blue;margin:0", {});'
		. 'var m = { type: "content", props: { contentType: "heading", fontFamily: "Inter" } };'
		. '_sdCodeSetAttr(m, "style", printed(m).style, "font-family: Georgia, serif", {});'
		. 'console.log(JSON.stringify([was, n.props.fontFamily, n.props.inlineStyle, printed(n).style, m.props.fontFamily, printed(m).style]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same("font-family:'Press Start 2P',sans-serif;color:red;margin:0", $answer[0], 'what the emitter printed');
	pg_assert_same('Press Start 2P', $answer[1], 'the bare family is the font picker\'s');
	pg_assert_same('', $answer[2], 'the old inline style field is folded in');
	pg_assert_same("font-family:'Press Start 2P',sans-serif;color: blue; margin: 0", $answer[3], 'printed as typed');
	pg_assert_same('', $answer[4], 'a stack of families is not one family');
	pg_assert_same('font-family: Georgia, serif', $answer[5], 'the stack stays in the style');
}

// The title and the tooltip: a plain title, the tooltip's text and
// placement go back to their props; turning the toggle into another one
// switches the tooltip off; an Attributes panel entry stays the list's.
function test_designer_code_view_title_and_tooltip()
{
	$answer = designer_code_view_attrs_test_run(
		'var a = { type: "row", props: { title: "Old" } };'
		. '_sdCodeSetAttr(a, "title", "Old", "New", {});'
		. 'var b = { type: "col", props: { tooltipEnabled: true, tooltipTitle: "Tip" } };'
		. '_sdCodeSetAttr(b, "title", "Tip", "Other", {}); _sdCodeSetAttr(b, "data-bs-placement", "top", "end", {});'
		. 'var bAttrs = printed(b).attrs;'
		. 'var c = { type: "content", props: { contentType: "span", tooltipEnabled: true, tooltipTitle: "Tip" } };'
		. '_sdCodeSetAttr(c, "data-bs-toggle", "tooltip", "modal", {});'
		. 'var d = { type: "content", props: { contentType: "span", tooltipEnabled: true, tooltipTitle: "Tip" } };'
		. '_sdCodeSetAttr(d, "data-bs-toggle", "tooltip", undefined, {});'
		. 'var e = { type: "semantic", props: { tag: "a", title: "Plain", _attrs: [{ name: "title", value: "Typed" }] } };'
		. '_sdCodeSetAttr(e, "title", "Typed", "Changed", {});'
		. 'console.log(JSON.stringify([a.props.title, printed(a).attrs, bAttrs, c.props.tooltipEnabled, printed(c).attrs, d.props.tooltipEnabled, printed(d).attrs, e.props.title, e.props._attrs[0].value]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same('New', $answer[0]);
	pg_assert_same(' title="New"', $answer[1]);
	pg_assert_same(' title="Other" data-bs-toggle="tooltip" data-bs-placement="end"', $answer[2]);
	pg_assert_same(false, $answer[3], 'another toggle typed over the tooltip\'s');
	pg_assert_same(' data-bs-toggle="modal"', $answer[4]);
	pg_assert_same(false, $answer[5], 'the tooltip\'s toggle removed');
	pg_assert_same('', $answer[6]);
	pg_assert_same('Plain', $answer[7], 'the typed entry is the list\'s');
	pg_assert_same('Changed', $answer[8]);
}

// The Display & Sizing style the emitter printed goes back to the sizing
// props: kept as it is, it is printed once (no copy in the Attributes
// panel's style); changed, the prop follows; deleted, the prop is cleared.
// A width typed where none was printed is the Attributes panel's, and an
// image keeps its width in its own prop.
function test_designer_code_view_style_keeps_sizing()
{
	$answer = designer_code_view_attrs_test_run(
		'var n = { type: "col", props: { width: "50%", maxWidth: "600", overflow: "hidden", zIndex: "2", _attrs: [{ name: "style", value: "color:red" }] } };'
		. 'var was = printed(n).style;'
		. '_sdCodeSetAttr(n, "style", was, "z-index:2;width:50%;max-width:600px;overflow:hidden;color:red;margin:0", {});'
		. 'var kept = [printed(n).style, n.props._attrs[0].value];'
		. '_sdCodeSetAttr(n, "style", printed(n).style, "z-index:3;width:40%;color:red;margin:0", {});'
		. 'var changed = [n.props.zIndex, n.props.width, n.props.maxWidth, n.props.overflow, printed(n).style];'
		. 'var m = { type: "semantic", props: { tag: "div" } };'
		. '_sdCodeSetAttr(m, "style", printed(m).style, "width:10rem", {});'
		. 'var img = { type: "content", props: { contentType: "image", width: "50%", minHeight: "20px" } };'
		. 'var iw = _sdNodeStyle(img.props, "", getNodeExtraStyle(_sdContentSizingNode(img)));'
		. '_sdCodeSetAttr(img, "style", iw, "width:60%;min-height:20px;color:red", {});'
		. 'console.log(JSON.stringify([was, kept, changed, m.props.width || "", printed(m).style, iw, img.props.width, img.props.minHeight, img.props._attrs]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same('z-index:2;width:50%;max-width:600px;overflow:hidden;color:red', $answer[0], 'what the emitter printed');
	pg_assert_same('z-index:2;width:50%;max-width:600px;overflow:hidden;color: red; margin: 0', $answer[1][0], 'the sizing is printed once');
	pg_assert_same('color: red; margin: 0', $answer[1][1], 'no copy of the sizing in the panel\'s style');
	pg_assert_same(array('3', '40%', '', '', 'z-index:3;width:40%;color: red; margin: 0'), $answer[2], 'changed and deleted sizing');
	pg_assert_same('', $answer[3], 'a width typed where none was printed');
	pg_assert_same('width: 10rem', $answer[4]);
	pg_assert_same('width:50%;min-height:20px', $answer[5], 'what the emitter printed for the image');
	pg_assert_same('60%', $answer[6], 'the image width stays its own prop');
	pg_assert_same('20px', $answer[7]);
	pg_assert_same(array(array('name' => 'style', 'value' => 'color: red')), $answer[8]);
}
