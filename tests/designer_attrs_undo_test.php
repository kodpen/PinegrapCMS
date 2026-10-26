<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the undo steps of the designer's Attributes panel
 * (assets/js/style_designer.js, renderAttrsPanel()): a text field takes one
 * snapshot before its first change, and the style row is written on Enter or
 * blur. Runs the panel's binding block under Node on fake fields; skipped
 * where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the Attributes panel's event bindings (from "id change" up to "Add a
// new attr") and _sdUndoBeforeFirstInput() on fake inputs, then $script.
// `log` lists "save <props>" for every saveState() and "style <value>" for
// every style row write. Prints the script's JSON; null without Node.
function designer_attrs_undo_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		$b = ($a !== false) ? strpos($js, $to, $a) : false;

		return ($b !== false) ? substr($js, $a, $b - $a) : '';
	};
	$bind = $cut('        // ── id change ──', '        // ── Add a new attr ──');
	$undo = $cut('    function _sdUndoBeforeFirstInput(', '    function addClassToken(');

	if (($bind === '') || ($undo === '')) {
		return array('error' => 'a block of style_designer.js was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgattrsundo');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var log = [];' . "\n"
		. 'var n = { props: { id: "old", src: "a.png", _attrs: [{ name: "title", value: "Old" }, { name: "style", value: "color: red" }] } };' . "\n"
		. 'function saveState() { log.push("save " + JSON.stringify(n.props)); }' . "\n"
		. 'function _writeInlineStyleValue(node, v) { log.push("style " + v); }' . "\n"
		. 'function noop() {}' . "\n"
		. 'var renderCanvas = noop, renderHtmlTree = noop, renderProperties = noop, renderAttrsPanel = noop,' . "\n"
		. '    _sdSchedulePropsRefresh = noop, _sdRetargetLabels = noop, _attachClassAutocomplete = noop, focusClassControl = noop;' . "\n"
		. 'function field(cls, data, value) {' . "\n"
		. '    var h = {};' . "\n"
		. '    return { value: value, dataset: data, classList: { contains: function (c) { return cls.indexOf(c) !== -1; } },' . "\n"
		. '        addEventListener: function (t, f) { (h[t] = h[t] || []).push(f); },' . "\n"
		. '        fire: function (t, e) { var self = this; (h[t] || []).forEach(function (f) { f.call(self, e || { key: "", preventDefault: noop }); }); },' . "\n"
		. '        type: function (v) { this.value = v; this.fire("input"); } };' . "\n"
		. '}' . "\n"
		. 'var idEl = field(["sd-attr-val"], {}, "old"), srcEl = field(["sd-attr-prop-val"], { propkey: "src" }, "a.png");' . "\n"
		. 'var nameEl = field(["sd-attr-name"], { idx: "0" }, "title"), valEl = field(["sd-attr-val"], { idx: "0" }, "Old");' . "\n"
		. 'var styleEl = field(["sd-attr-val", "sd-attr-style-val"], { idx: "1" }, "color: red");' . "\n"
		. 'function list(map) { return function (sel) { return map[sel] || []; }; }' . "\n"
		. 'var panel = { querySelector: function (sel) { return sel === \'.sd-attr-val[data-prop="id"]\' ? idEl : null; },' . "\n"
		. '    querySelectorAll: list({ ".sd-attr-prop-val": [srcEl] }) };' . "\n"
		. 'var dynEl = { querySelector: function () { return null; },' . "\n"
		. '    querySelectorAll: list({ ".sd-attr-name": [nameEl], ".sd-attr-val": [valEl, styleEl] }) };' . "\n"
		. 'var document = { getElementById: function (id) { return id === "sd-attr-dynamic" ? dynEl : null; } };' . "\n"
		. $undo . "\n"
		. '(function () {' . "\n" . $bind . "\n" . '})();' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The id, a prop-bound attribute, a custom attribute's name and value: one
// snapshot per edit, taken before the first keystroke changed anything (so
// Undo brings the old value back), and a new snapshot for the next focus.
function test_designer_attrs_undo_snapshot_before_the_change()
{
	$result = designer_attrs_undo_node(
		'idEl.fire("focus"); idEl.type("n"); idEl.type("ne"); idEl.type("new"); idEl.fire("change");' . "\n"
		. 'srcEl.fire("focus"); srcEl.type("b"); srcEl.type("b.png"); srcEl.fire("change");' . "\n"
		. 'nameEl.fire("focus"); nameEl.type("tit"); nameEl.type("titles");' . "\n"
		. 'valEl.fire("focus"); valEl.type("N"); valEl.type("New");' . "\n"
		. 'idEl.fire("focus"); idEl.type("again");' . "\n"
		. 'console.log(JSON.stringify(log.map(function (l) { var p = JSON.parse(l.slice(5)); return [p.id, p.src, p._attrs[0].name, p._attrs[0].value]; })));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		array('old', 'a.png', 'title', 'Old'),
		array('new', 'a.png', 'title', 'Old'),
		array('new', 'b.png', 'title', 'Old'),
		array('new', 'b.png', 'titles', 'Old'),
		array('new', 'b.png', 'titles', 'New'),
	), $result, 'one snapshot per edit, each before its change');
}

// The style row is written on Enter or blur, once per new value: typing
// writes nothing, so a half-typed declaration is never split into options.
function test_designer_attrs_undo_style_row_on_enter_or_blur()
{
	$result = designer_attrs_undo_node(
		'styleEl.fire("focus"); styleEl.type("color: red; font-fam"); styleEl.type("color: red; font-family: Inter");' . "\n"
		. 'var typed = log.length;' . "\n"
		. 'styleEl.fire("keydown", { key: "Enter", preventDefault: noop });' . "\n"
		. 'styleEl.fire("change");' . "\n"
		. 'styleEl.type("color: blue"); styleEl.fire("change");' . "\n"
		. 'console.log(JSON.stringify({ typed: typed, log: log }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(0, $result['typed'], 'typing writes nothing');
	pg_assert_same(array('style color: red; font-family: Inter', 'style color: blue'), $result['log'], 'Enter writes, the blur after it does not write again, a blur writes a new value');
}
