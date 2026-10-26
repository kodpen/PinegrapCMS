<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the text side of the designer's panel input suggestions
 * (assets/js/pg_style_fields.js): the declaration and word under the caret,
 * the custom properties of a style sheet, what the predictor is asked for
 * each kind of field and the text after a pick, plus the reason text the
 * designer shows beside a suggestion (_sdFieldHint() in style_designer.js).
 * Run under Node on the shipped predictor and vocabulary; skipped on a
 * machine without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script under Node with the file as F and a predictor as P; returns
// what it prints as JSON, null without Node.
function style_fields_test_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgfields');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	file_put_contents($file,
		'var F = require(' . json_encode($dir . '/pg_style_fields.js') . ");\n"
		. 'var P = require(' . json_encode($dir . '/pg_style_predict.js') . ').create(JSON.parse(require("fs").readFileSync(' . json_encode($dir . '/designer_vocab/bootstrap5.json') . ', "utf8")));' . "\n"
		. 'function labels(r) { return r.items.map(function (i) { return i.label; }); }' . "\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_style_fields_decl_at()
{
	$r = style_fields_test_run(
		'var t = "color: red; background: url(\\"a;b\\") ; font-wei : bold";'
		. 'console.log(JSON.stringify({'
		. '  name: F.declAt(t, t.indexOf("wei") + 3),'
		. '  value: F.declAt(t, t.length),'
		. '  open: F.declAt("border: var(--bs-bor ;x: y", 20),'
		. '  fresh: F.declAt("color: red; ", 12),'
		. '  line: F.declAt("color: red\\nmar", 14)'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('part' => 'name', 'from' => 37, 'to' => 45, 'query' => 'font-wei', 'colon' => true,
		'styles' => array('color' => 'red', 'background' => 'url("a;b")')), $r['name'], 'a semicolon in quotes stays in the value');
	pg_assert_same('value', $r['value']['part']);
	pg_assert_same('font-wei', $r['value']['name']);
	pg_assert_same('bold', $r['value']['query']);
	pg_assert_same(array('x' => 'y'), $r['open']['styles'], 'an open bracket does not swallow the next declaration');
	pg_assert_same('var(--bs-bor', $r['open']['query']);
	pg_assert_same(array('part' => 'name', 'from' => 12, 'to' => 12, 'query' => '', 'colon' => false,
		'styles' => array('color' => 'red')), $r['fresh'], 'after the last semicolon, a new name');
	pg_assert_same(array('name', 'mar', array('color' => 'red')), array($r['line']['part'], $r['line']['query'], $r['line']['styles']), 'a new line starts a new declaration');
}

function test_style_fields_word_and_tokens()
{
	$r = style_fields_test_run(
		'console.log(JSON.stringify({'
		. '  word: F.wordAt("1px solid re", 12),'
		. '  mid: F.wordAt("a, bc d", 4),'
		. '  tokens: F.cssTokens(":root{--brand: #f00;} /* --x: 1 */ .a{--gap:1rem} .b{color:var(--brand)} .c{--brand:blue}")'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('from' => 10, 'to' => 12, 'query' => 're'), $r['word']);
	pg_assert_same(array('from' => 3, 'to' => 5, 'query' => 'b'), $r['mid'], 'commas end a word');
	pg_assert_same(array('--brand' => 'blue', '--gap' => '1rem'), $r['tokens'], 'comments left out, the last one wins');
}

// What each kind of field asks for and its text after a pick.
function test_style_fields_suggest_and_apply()
{
	$r = style_fields_test_run(
		'var out = {};'
		. 'var t = "color: red; backg", s = F.suggest(P, { kind: "style" }, t, t.length, {});'
		. 'out.styleName = [s.part, labels(s)[0], F.apply(t, s, "background-color")];'
		. 't = "font-wei : bold"; s = F.suggest(P, { kind: "style" }, t, 8, {});'
		. 'out.colon = F.apply(t, s, "font-weight");'
		. 't = "color: red; margin: 1"; s = F.suggest(P, { kind: "style" }, t, t.length, {});'
		. 'out.units = labels(s).slice(0, 3); out.noColor = F.suggest(P, { kind: "style" }, "color: red; co", 14, {}).items.some(function (i) { return i.label === "color"; });'
		. 't = "1px solid var(--bs-bor"; s = F.suggest(P, { kind: "value", name: "border" }, t, t.length, {});'
		. 'out.later = labels(s).every(function (l) { return !/\\s/.test(l); }); out.laterPick = F.apply(t, s, "var(--bs-border-color)").text;'
		. 't = "var(--bs-bor"; s = F.suggest(P, { kind: "value", name: "border" }, t, t.length, {});'
		. 'out.whole = F.apply(t, s, "var(--bs-border-width) solid var(--bs-border-color)").text;'
		. 's = F.suggest(P, { kind: "value", name: "color" }, "", 0, { design: { tokens: F.cssTokens(":root{--brand:#f00}") } });'
		. 'out.token = s.items[0];'
		. 'out.selector = labels(F.suggest(P, { kind: "selector" }, ".card:h", 7, { classes: ["card"] }));'
		. 'out.toggle = labels(F.suggest(P, { kind: "attrValue", name: "data-bs-toggle" }, "", 0, { parents: [{ tag: "ul", classes: ["nav", "nav-tabs"] }] }))[0];'
		. 'out.attr = labels(F.suggest(P, { kind: "attribute" }, "data-bs-dis", 11, {}));'
		. 'out.none = F.suggest(null, { kind: "property" }, "co", 2, {}).items;'
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('name', 'background-color', array('text' => 'color: red; background-color: ', 'caret' => 30)), $r['styleName'], 'a property gets its colon');
	pg_assert_same(array('text' => 'font-weight: bold', 'caret' => 13), $r['colon'], 'the colon already there is reused');
	pg_assert_same(array('1rem', '1.5rem', '1px'), $r['units'], 'units after a number');
	pg_assert_false($r['noColor'], 'a property already set is not offered again');
	pg_assert_true($r['later'], 'a value of several words only for the first word');
	pg_assert_same('1px solid var(--bs-border-color)', $r['laterPick'], 'a word replaces only the word');
	pg_assert_same('var(--bs-border-width) solid var(--bs-border-color)', $r['whole'], 'a value of several words replaces the value');
	pg_assert_same(array('var(--brand)', array('design')), array($r['token']['label'], $r['token']['codes']), 'the design\'s own colour token first');
	pg_assert_same(array('.card:hover'), $r['selector']);
	pg_assert_same('tab', $r['toggle'], 'the parent decides the toggle');
	pg_assert_same('data-bs-dismiss', $r['attr'][0]);
	pg_assert_same(array(), $r['none'], 'no predictor, nothing');
}

// The reason text beside a field suggestion.
function test_style_fields_hint()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdFieldHint(');
	pg_assert_true($from !== false, '_sdFieldHint is in the designer');

	if ($from === false) {
		return;
	}

	$end = strpos($source, "\n    }\n", $from);
	$hint = substr($source, $from, $end - $from + 6);

	$r = style_fields_test_run(
		'function _sdT(s) { return s; }' . $hint
		. 'console.log(JSON.stringify(['
		. '  _sdFieldHint({ codes: ["design"] }), _sdFieldHint({ codes: ["pseudo"] }), _sdFieldHint({ codes: ["token"] }),'
		. '  _sdFieldHint({ codes: ["bs"], swatch: "#fff" }), _sdFieldHint({ codes: ["unit"] })'
		. ']));'
	);

	if ($r !== null) {
		pg_assert_same(array('Used in this design', 'Element state', 'Bootstrap variable', '#fff', ''), $r);
	}
}
