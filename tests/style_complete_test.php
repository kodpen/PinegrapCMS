<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the smart completion inside the designer's code editors
 * (assets/js/pg_style_complete.js): attribute names and values, CSS values
 * and var(--…) tokens, and the rankers that order the engine's own options
 * by the element around the cursor. They run the module with the real
 * predictor and vocabulary inside the code editor facade's completion
 * slots, on the engine from the bundle, under Node; skipped without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with `ask(doc, explicit)`: an HTML editor state with the
// module's sources and rankers registered, the cursor at |, every slot's
// options in { labels: [...], options: { label: [option, ...] } }. Prints the
// script's JSON; null without Node, ['error' => …] when it fails.
function style_complete_node($script)
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

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$a = strpos($facade, '    function _languageObject(');
	$b = ($a !== false) ? strpos($facade, '    // Syntax errors as diagnostics:', $a) : false;

	if ($b === false) {
		return array('error' => 'the completion block of the facade was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgstylecomplete');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js/';
	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		. 'global.document = { body: { style: { color: "", display: "", margin: "", columns: "", width: "" } } };' . "\n"
		. 'var cm = PgCM, _completions = {}, _rankers = {};' . "\n"
		. substr($facade, $a, $b - $a) . "\n"
		. 'var P = require(' . json_encode($dir . 'pg_style_predict.js') . ').create(JSON.parse(require("fs").readFileSync(' . json_encode($dir . 'designer_vocab/bootstrap5.json') . ', "utf8")));' . "\n"
		. 'var C = require(' . json_encode($dir . 'pg_style_complete.js') . ');' . "\n"
		. 'var opts = { design: function () { return { classes: {}, tokens: { "--theme": "#123456" } }; } };' . "\n"
		. '_completions = { html: [function (c, s) { return C.html(P, opts, c, s); }], css: [function (c, s) { return C.css(P, opts, c, s); }] };' . "\n"
		. '_rankers = { html: [function (o, c, i) { return C.htmlRank(P, opts, o, c, i); }], css: [function (o, c, i) { return C.cssRank(P, opts, o, c, i); }] };' . "\n"
		. 'function ask(doc, explicit) {' . "\n"
		. '    var pos = doc.indexOf("|"); doc = doc.replace("|", "");' . "\n"
		. '    var state = cm.state.EditorState.create({ doc: doc, extensions: [cm.langHtml.html(), _completionExtensions(cm, "html")] });' . "\n"
		. '    cm.language.ensureSyntaxTree(state, doc.length, 5000);' . "\n"
		. '    var out = { labels: [], options: {} };' . "\n"
		. '    _completionSlots(cm).forEach(function (slot) {' . "\n"
		. '        var r = slot(new cm.autocomplete.CompletionContext(state, pos, explicit !== false));' . "\n"
		. '        if (!r) return;' . "\n"
		. '        r.options.forEach(function (o) { out.labels.push(o.label); (out.options[o.label] = out.options[o.label] || []).push(o); });' . "\n"
		. '    });' . "\n"
		. '    return out;' . "\n"
		. '}' . "\n"
		. 'function has(r, label) { return r.labels.indexOf(label) >= 0; }' . "\n"
		. 'function boost(r, label) { return r.options[label] ? (r.options[label][0].boost || 0) : null; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// Attribute names: the component's attributes are added and ranked by the
// element's place (a button in a modal's footer: data-bs-dismiss first); a
// name the engine offers too comes twice in the same shape, so the editor
// lists it once; a present attribute is not offered again. Values: the
// component a trigger may open.
function test_style_complete_html_attributes()
{
	$result = style_complete_node(
		'var doc = "<div class=\"modal\"><div class=\"modal-footer\"><button class=\"btn\" type=\"button\" |></button></div></div>";' . "\n"
		. 'var names = ask(doc);' . "\n"
		. 'var name = names.options["name"] || [];' . "\n"
		. 'var vals = ask("<button data-bs-toggle=\"|\">");' . "\n"
		. 'var typed = ask("<button data-bs-toggle=\"mo|\">", false);' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '    dismiss: has(names, "data-bs-dismiss"), dismissBoost: boost(names, "data-bs-dismiss"),' . "\n"
		. '    nameCount: name.length, nameSame: name.length === 2 && JSON.stringify(name[0]) === JSON.stringify(name[1]),' . "\n"
		. '    typeAgain: (names.options["type"] || []).filter(function (o) { return o.boost; }).length,' . "\n"
		. '    modal: has(vals, "modal"), modalTyped: has(typed, "modal"), modalApply: typed.options["modal"] ? typed.options["modal"][0].apply : null,' . "\n"
		. '    classValue: has(ask("<div class=\"mo|\">"), "modal-backdrop") ? "class source" : "none"' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_true($result['dismiss'] === true, 'data-bs-dismiss offered in a modal footer');
	pg_assert_same(20, $result['dismissBoost'], 'and ranked first');
	pg_assert_same(2, $result['nameCount'], 'a name both offer comes from both');
	pg_assert_true($result['nameSame'] === true, 'in the same shape, so the editor lists it once');
	pg_assert_same(0, $result['typeAgain'], 'a present attribute is not moved up again');
	pg_assert_true($result['modal'] === true, 'data-bs-toggle offers modal');
	pg_assert_true($result['modalTyped'] === true, 'also while typed');
	pg_assert_same('modal', $result['modalApply']);
	pg_assert_same('none', $result['classValue'], 'class="" is left to the class source');
}

// CSS: var(-- offers tokens with their colour; a value offers what the
// engine lacks (lengths, the design's tokens) and ranks its keywords; the
// properties are ranked by the element.
function test_style_complete_css()
{
	$result = style_complete_node(
		'var token = ask("<style>.a{color: var(--bs-pri|)}</style>");' . "\n"
		. 'var width = ask("<div style=\"width: 5|\">", false);' . "\n"
		. 'var color = ask("<style>:root{--brand:#f00}</style><p style=\"color: |\">");' . "\n"
		. 'var display = ask("<style>.a{display: f|}</style>", false);' . "\n"
		. 'var prop = ask("<div style=\"margin:0; |\">");' . "\n"
		. 'var calc = ask("<div style=\"width: calc(1|\">", false);' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '    primary: has(token, "--bs-primary"), swatch: token.options["--bs-primary"] ? token.options["--bs-primary"][0].swatch : null,' . "\n"
		. '    rem: has(width, "5rem"), pct: has(width, "50%"),' . "\n"
		. '    brand: has(color, "var(--brand)"), brandSwatch: color.options["var(--brand)"] ? color.options["var(--brand)"][0].swatch : null,' . "\n"
		. '    theme: has(color, "var(--theme)"),' . "\n"
		. '    flexCount: (display.options["flex"] || []).length, flexBoost: boost(display, "flex"),' . "\n"
		. '    displayBoost: boost(prop, "display"), columnsBoost: boost(prop, "columns"),' . "\n"
		. '    calc: has(calc, "1rem")' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_true($result['primary'] === true, 'var(-- offers the Bootstrap tokens');
	pg_assert_same('#0d6efd', $result['swatch']);
	pg_assert_true($result['rem'] === true, 'a length with its unit');
	pg_assert_true($result['pct'] === true);
	pg_assert_true($result['brand'] === true, 'a token the document declares');
	pg_assert_same('#f00', $result['brandSwatch']);
	pg_assert_true($result['theme'] === true, 'a token of the design');
	pg_assert_same(1, $result['flexCount'], 'a keyword the engine offers is not added again');
	pg_assert_true($result['flexBoost'] > 0, 'but ranked');
	pg_assert_true($result['displayBoost'] > 0, 'a property the predictor ranks moves up');
	pg_assert_same(0, $result['columnsBoost'], 'one it does not stays');
	pg_assert_false($result['calc'], 'inside calc() the engine decides');
}

// The element's context read from the syntax tree, and the custom
// properties a text declares.
function test_style_complete_context()
{
	$result = style_complete_node(
		'var doc = "<body><section class=\"row g-2\"><div class=\"col\" id=\"x\" style=\"color: red; margin:0\" data-a></div></section></body>";' . "\n"
		. 'var state = cm.state.EditorState.create({ doc: doc, extensions: [cm.langHtml.html()] });' . "\n"
		. 'var tree = cm.language.ensureSyntaxTree(state, doc.length, 5000);' . "\n"
		. 'var tag = C.htmlSpot({ state: state, pos: doc.indexOf(" data-a") + 1 }, tree.resolveInner(doc.indexOf(" data-a") + 1, -1));' . "\n"
		. 'console.log(JSON.stringify({ ctx: C.tagContext(state, tag.tag), tokens: C.docTokens(":root{--a: 1px; --b:#fff} .x{--a:2px}") }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'tag' => 'div',
		'classes' => array('col'),
		'attrs' => array('id' => 'x', 'data-a' => ''),
		'styles' => array('color' => 'red', 'margin' => '0'),
		'parents' => array(array('tag' => 'section', 'classes' => array('row', 'g-2'))),
	), $result['ctx']);
	pg_assert_same(array('--a' => '2px', '--b' => '#fff'), $result['tokens']);
}
