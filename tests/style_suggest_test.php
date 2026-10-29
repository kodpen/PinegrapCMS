<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the browser side of the designer's smart completion
 * (assets/js/pg_style_suggest.js): the one-time loader of the predictor and
 * the vocabulary, the element context it reads for the predictor, the class
 * counts of a design, and the designer's reason text for a class suggestion
 * (_sdClassHint() in assets/js/style_designer.src.js). Run under Node on plain
 * stand-ins for the document; skipped on a machine without Node. The
 * dropdown itself draws into the document and is not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script under Node after $prelude (which may set up `window` before
// the file loads) with the file as S; returns what it prints as JSON, null
// without Node.
function style_suggest_test_run($script, $prelude = '')
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsuggest');

	if ($file === false) {
		return null;
	}

	file_put_contents($file,
		$prelude
		. 'var S = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_style_suggest.js') . ");\n"
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

// A plain element stand-in: tag, class, attributes, parent.
function style_suggest_test_dom()
{
	return 'function El(tag, attrs, parent) {'
		. '  this.tagName = tag.toUpperCase(); this.parentElement = parent || null;'
		. '  this.attributes = Object.keys(attrs || {}).map(function (k) { return { name: k, value: attrs[k] }; });'
		. '  this.classList = { contains: function (c) { return ((attrs || {})["class"] || "").split(" ").indexOf(c) !== -1; } };'
		. '}'
		. 'El.prototype.getAttribute = function (n) { for (var i = 0; i < this.attributes.length; i++) if (this.attributes[i].name === n) return this.attributes[i].value; return null; };';
}

function test_style_suggest_element_context()
{
	$r = style_suggest_test_run(
		style_suggest_test_dom()
		. 'var body = new El("body", {});'
		. 'var row = new El("div", { "class": "row g-3 sd-selected" }, body);'
		. 'var wrap = new El("div", { "class": "sd-wrap" }, row);'
		. 'var col = new El("div", { "class": "col-md-6  sd-hover", "data-sd-id": "n4", "id": "a", "style": "color:red", "data-bs-toggle": "x" }, wrap);'
		. 'var opts = { skipClass: /^sd-/, skipEl: function (p) { return p.classList.contains("sd-wrap"); } };'
		. 'console.log(JSON.stringify({ ctx: S.elementContext(col, opts), none: S.elementContext(null), bare: S.elementContext(col).parents.length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'tag' => 'div',
		'classes' => array('col-md-6'),
		'attrs' => array('id' => 'a', 'data-bs-toggle' => 'x'),
		'parents' => array(array('tag' => 'div', 'classes' => array('row', 'g-3'))),
	), $r['ctx'], 'editor classes, wrappers, style and editor attributes left out; the walk stops at body');
	pg_assert_same(array('tag' => '', 'classes' => array(), 'attrs' => array(), 'parents' => array()), $r['none'], 'no element, an empty context');
	pg_assert_same(2, $r['bare'], 'without skipEl the wrapper counts as a parent');
}

function test_style_suggest_design_classes()
{
	$r = style_suggest_test_run('console.log(JSON.stringify(S.designClasses(["btn  btn-primary", "", ["btn", "card"], null])));');

	if ($r !== null) {
		pg_assert_same(array('btn' => 2, 'btn-primary' => 1, 'card' => 1), $r);
	}
}

// The loader fetches the script and the vocabulary once, answers every
// waiting caller with the same predictor and later callers at once; a
// failed fetch answers null and stays failed.
function test_style_suggest_loads_once()
{
	$prelude = 'var log = [];'
		. 'function stubWindow(vocabOk) {'
		. '  return { document: { head: { appendChild: function (s) { log.push("script " + s.src); setTimeout(function () { global.window.PgStylePredict = { create: function (v) { return { v: v.version }; } }; s.onload(); }, 5); } },'
		. '    createElement: function () { return {}; } },'
		. '    fetch: function (u) { log.push("fetch " + u); return Promise.resolve({ ok: vocabOk, status: vocabOk ? 200 : 404, json: function () { return Promise.resolve({ version: "5.3.8" }); } }); } };'
		. '}';

	$r = style_suggest_test_run(
		'S.configure({ predictor: "p.js", vocab: "v.json" });'
		. 'var got = [];'
		. 'S.load(function (p) { got.push(p); });'
		. 'S.load(function (p) { got.push(p); });'
		. 'S.load();'
		. 'setTimeout(function () { S.load(function (p) { got.push(p); }); console.log(JSON.stringify({ log: log, got: got, get: S.get() })); }, 30);',
		'var log = [];' . $prelude . 'global.window = stubWindow(true);'
	);

	if ($r !== null) {
		pg_assert_same(array('script p.js', 'fetch v.json'), $r['log'], 'one script and one fetch');
		pg_assert_same(array(array('v' => '5.3.8'), array('v' => '5.3.8'), array('v' => '5.3.8')), $r['got'], 'every caller gets the predictor');
		pg_assert_same(array('v' => '5.3.8'), $r['get']);
	}

	$r = style_suggest_test_run(
		'S.configure({ predictor: "p.js", vocab: "v.json" });'
		. 'var got = [];'
		. 'S.load(function (p) { got.push(p); });'
		. 'setTimeout(function () { S.load(function (p) { got.push(p); }); console.log(JSON.stringify({ log: log, got: got })); }, 30);',
		$prelude . 'global.window = stubWindow(false);'
	);

	if ($r !== null) {
		pg_assert_same(array(null, null), $r['got'], 'a failed vocabulary answers null, now and later');
		pg_assert_same(2, count($r['log']), 'no second try');
	}

	$r = style_suggest_test_run('var got = "x"; S.load(function (p) { got = p; }); console.log(JSON.stringify({ got: got, get: S.get() }));');

	if ($r !== null) {
		pg_assert_same(array('got' => null, 'get' => null), $r, 'nothing configured, no document: null at once');
	}
}

// The reason text beside a class suggestion, for real predictor results.
function test_style_suggest_class_hint()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, 'function _sdClassHint(');
	pg_assert_true($from !== false, '_sdClassHint is in the designer');

	if ($from === false) {
		return;
	}

	$end = strpos($source, "\n    }\n", $from);
	$hint = substr($source, $from, $end - $from + 6);

	$r = style_suggest_test_run(
		'function _sdT(s, v) { return s.replace("{var}", v); }' . $hint
		. 'var P = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_style_predict.js') . ').create(JSON.parse(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/designer_vocab/bootstrap5.json') . ', "utf8")));'
		. 'function hint(q, ctx, label) { var r = P.classes(q, ctx); for (var i = 0; i < r.length; i++) if (r[i].label === label) return _sdClassHint(r[i]); return "missing"; }'
		. 'console.log(JSON.stringify({'
		. '  fixed: hint("bg-white", {}, "bg-white"),'
		. '  col: hint("", { parents: [{ tag: "div", classes: ["row"] }] }, "col"),'
		. '  bp: hint("d-md", { classes: ["d-none"] }, "d-md-block"),'
		. '  redundant: hint("d-md-none", { classes: ["d-none"] }, "d-md-none"),'
		. '  conflict: hint("p-4", { classes: ["p-3"] }, "p-4"),'
		. '  design: hint("hero", { design: { classes: { hero: 3 } } }, "hero"),'
		. '  none: _sdClassHint({ label: "x", codes: [] })'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'fixed' => 'Not for dark mode, use bg-body',
		'col' => 'Fits the parent',
		'bp' => 'From md up',
		'redundant' => 'Changes nothing here',
		'conflict' => 'Overrides p-3',
		'design' => 'Used in this design',
		'none' => '',
	), $r);
}

// The code editor delegates: three sources and two rankers registered; they
// answer nothing and start the load until the predictor and the completion
// module are there, then pass the predictor and the options on. The module
// is fetched with the predictor.
function test_style_suggest_install_code()
{
	$prelude = 'var log = [];'
		. 'global.window = { document: { head: { appendChild: function (s) { log.push("script " + s.src);'
		. '  if (s.src === "c.js") { global.window.PgStyleComplete = { html: function (P, o, c, s) { return [P.v, o.design(), c, s]; }, css: function () { return "css"; }, js: function () { return "js"; }, htmlRank: function () { return 3; }, cssRank: function () { return 4; } }; return; }'
		. '  setTimeout(function () { global.window.PgStylePredict = { create: function (v) { return { v: v.version }; } }; s.onload(); }, 5); } },'
		. '  createElement: function () { return {}; } },'
		. '  fetch: function (u) { log.push("fetch " + u); return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ version: "5.3.8" }); } }); } };';

	$r = style_suggest_test_run(
		'S.configure({ predictor: "p.js", vocab: "v.json", complete: "c.js" });'
		. 'var reg = { html: [], css: [], javascript: [], rank: {} };'
		. 'S.installCode({ addCompletionSource: function (l, f) { reg[l].push(f); }, addCompletionRanker: function (l, f) { reg.rank[l] = f; } }, { design: function () { return "d"; } });'
		. 'var before = [reg.html[0]("ctx", "syn"), reg.rank.css("o")];'
		. 'setTimeout(function () { console.log(JSON.stringify({ before: before, log: log, html: reg.html[0]("ctx", "syn"), css: reg.css[0](), js: reg.javascript[0](), ranks: [reg.rank.html(), reg.rank.css()] })); }, 30);',
		$prelude
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(null, 0), $r['before'], 'nothing before the load');
	pg_assert_same(array('script c.js', 'script p.js', 'fetch v.json'), $r['log'], 'the module comes with the predictor');
	pg_assert_same(array('5.3.8', 'd', 'ctx', 'syn'), $r['html']);
	pg_assert_same('css', $r['css']);
	pg_assert_same('js', $r['js']);
	pg_assert_same(array(3, 4), $r['ranks']);
}
