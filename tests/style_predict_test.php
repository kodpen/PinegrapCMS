<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the predictor of the designer's smart completion
 * (assets/js/pg_style_predict.js), run under Node on the shipped Bootstrap
 * vocabulary (assets/js/designer_vocab/bootstrap5.json). On a machine without
 * Node the tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs calls on a predictor made from the shipped vocabulary as P and returns
// what the script prints as JSON; null when there is no Node to run it.
function style_predict_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpredict');

	if ($file === false) {
		return null;
	}

	file_put_contents($file,
		'var S = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_style_predict.js') . ");\n"
		. 'var P = S.create(JSON.parse(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/designer_vocab/bootstrap5.json') . ", 'utf8')));\n"
		. "function labels(r) { return r.map(function (i) { return i.label; }); }\n"
		. "function find(r, l) { for (var i = 0; i < r.length; i++) if (r[i].label === l) return r[i]; return null; }\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// Runs one script and fails the test on a Node error; null when skipped.
function style_predict_test_run($script)
{
	$result = style_predict_test_node($script);

	if ($result === null) {
		return null;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	return isset($result['error']) ? null : $result;
}

function test_style_predict_match_tiers()
{
	$r = style_predict_test_run('console.log(JSON.stringify([S.match("d-flex", "d-flex"), S.match("d-flex", "d-f") > S.match("d-md-flex", "d-f"), S.match("d-flex", "flex") > S.match("d-flex", "lex"), S.match("d-flex", "zz"), S.match("x", "")]));');

	if ($r !== null) {
		pg_assert_same(array(1000, true, true, -1, 0), $r);
	}
}

// Same input, same list; a class already on the element is never offered.
function test_style_predict_classes_are_stable_and_skip_present()
{
	$r = style_predict_test_run(
		'var ctx = { tag: "div", classes: ["d-flex"] };'
		. 'console.log(JSON.stringify([labels(P.classes("d-", ctx)), labels(P.classes("d-", ctx)).indexOf("d-flex"), JSON.stringify(P.classes("d-", ctx)) === JSON.stringify(P.classes("d-", ctx))]));'
	);

	if ($r !== null) {
		pg_assert_same(15, count($r[0]));
		pg_assert_same(-1, $r[1]);
		pg_assert_true($r[2], 'stable');
	}
}

// Inside a row the columns come first, inside a card its parts.
function test_style_predict_classes_follow_the_parent()
{
	$r = style_predict_test_run(
		'var row = P.classes("", { tag: "div", parents: [{ tag: "div", classes: ["row"] }] });'
		. 'var card = P.classes("", { tag: "div", parents: [{ tag: "div", classes: ["card"] }] });'
		. 'console.log(JSON.stringify([labels(row).slice(0, 3), find(row, "col").codes, labels(card).indexOf("card-body") !== -1, labels(card).indexOf("card-header") !== -1]));'
	);

	if ($r !== null) {
		pg_assert_same(array('col', 'col-1', 'col-2'), $r[0]);
		pg_assert_same(array('ctx'), $r[1]);
		pg_assert_true($r[2], 'card-body offered in a card');
		pg_assert_true($r[3], 'card-header offered in a card');
	}
}

// A nav link is offered its states; a nav's variants are not offered to its
// children ('nav-tabs' is a variant of 'nav', not a part of it).
function test_style_predict_classes_states_and_variants()
{
	$r = style_predict_test_run(
		'var r = P.classes("", { tag: "a", classes: ["nav-link"], parents: [{ tag: "li", classes: ["nav-item"] }, { tag: "ul", classes: ["nav", "nav-tabs"] }] });'
		. 'var flat = P.classes("", { tag: "a", classes: ["nav-link"], parents: [{ tag: "ul", classes: ["nav"] }] });'
		. 'console.log(JSON.stringify([labels(r)[0], find(r, "active").codes, find(flat, "nav-tabs"), find(flat, "nav-fill"), find(flat, "active").codes]));'
	);

	if ($r !== null) {
		pg_assert_same('active', $r[0]);
		pg_assert_same(array('ctx', 'state'), $r[1]);
		pg_assert_same(null, $r[2]);
		pg_assert_same(null, $r[3]);
		pg_assert_same(array('state'), $r[4]);
	}
}

// Beside d-none a responsive display class is the next step; d-flex would
// override it and d-md-none would change nothing.
function test_style_predict_classes_breakpoints_and_conflicts()
{
	$r = style_predict_test_run(
		'var ctx = { classes: ["d-none"], limit: 200 };'
		. 'var r = P.classes("d-", ctx);'
		. 'console.log(JSON.stringify([find(r, "d-md-block").codes, find(r, "d-md-block").bp, find(r, "d-flex").codes, find(r, "d-flex").conflicts, find(r, "d-md-none").codes, labels(r).indexOf("d-md-block") < labels(r).indexOf("d-flex"), labels(r).indexOf("d-flex") < labels(r).indexOf("d-md-none")]));'
	);

	if ($r !== null) {
		pg_assert_same(array('bp'), $r[0]);
		pg_assert_same('md', $r[1]);
		pg_assert_same(array('conflict'), $r[2]);
		pg_assert_same(array('d-none'), $r[3]);
		pg_assert_same(array('redundant'), $r[4]);
		pg_assert_true($r[5], 'd-md-block before d-flex');
		pg_assert_true($r[6], 'd-flex before d-md-none');
	}
}

// A colour that ignores the colour mode comes last, with its replacement;
// a button variant beside another overrides it.
function test_style_predict_classes_fixed_colours_and_variants()
{
	$r = style_predict_test_run(
		'var r = P.classes("text-", { limit: 500 });'
		. 'var b = P.classes("btn-sec", { tag: "button", classes: ["btn", "btn-primary"] });'
		. 'console.log(JSON.stringify([find(r, "text-muted").replace, find(r, "text-muted").codes, labels(r).indexOf("text-muted") > labels(r).indexOf("text-body-secondary"), find(b, "btn-secondary").conflicts]));'
	);

	if ($r !== null) {
		pg_assert_same('text-body-secondary', $r[0]);
		pg_assert_same(array('fixed'), $r[1]);
		pg_assert_true($r[2], 'text-muted after its replacement');
		pg_assert_same(array('btn-primary'), $r[3]);
	}
}

// The design's own classes are offered and ranked by use.
function test_style_predict_classes_from_the_design()
{
	$r = style_predict_test_run(
		'var r = P.classes("hero", { design: { classes: { "hero-title": 4, "hero-x": 1 } } });'
		. 'console.log(JSON.stringify([labels(r), find(r, "hero-title").codes]));'
	);

	if ($r !== null) {
		pg_assert_same(array('hero-title', 'hero-x'), $r[0]);
		pg_assert_same(array('design', 'custom'), $r[1]);
	}
}

function test_style_predict_properties_and_values()
{
	$r = style_predict_test_run(
		'var props = P.properties("pad", { styles: { padding: "1rem" } });'
		. 'var units = P.values("padding", "12", {});'
		. 'var time = P.values("transition-duration", "2", {});'
		. 'var color = P.values("color", "prim", { limit: 50 });'
		. 'var mine = P.values("color", "", { design: { tokens: { "--brand": "#ff0000", "--gap": "2rem" } }, limit: 50 });'
		. 'console.log(JSON.stringify([labels(props).indexOf("padding"), labels(props)[0], labels(units).slice(0, 2), labels(time), find(color, "var(--bs-primary)").swatch, labels(mine)[0], find(mine, "var(--brand)").swatch, find(mine, "var(--gap)")]));'
	);

	if ($r !== null) {
		pg_assert_same(-1, $r[0]);
		pg_assert_same(0, strpos($r[1], 'padding-'), 'a padding longhand first');
		pg_assert_same(array('12rem', '12px'), $r[2]);
		pg_assert_same(array('2ms', '2s'), $r[3]);
		pg_assert_same('#0d6efd', $r[4]);
		pg_assert_same('var(--brand)', $r[5]);
		pg_assert_same('#ff0000', $r[6]);
		pg_assert_same(null, $r[7]);
	}
}

// A component's tokens come first on its own element and are hidden far
// from it when nothing is typed.
function test_style_predict_tokens()
{
	$r = style_predict_test_run(
		'var own = P.tokens("", { classes: ["btn"] });'
		. 'var none = P.tokens("", {});'
		. 'var typed = P.tokens("--bs-primary", {});'
		. 'console.log(JSON.stringify([labels(own).every(function (l) { return l.indexOf("--bs-btn-") === 0; }), find(own, "--bs-btn-bg").codes, labels(none), labels(typed)[0], find(typed, "--bs-primary").swatch]));'
	);

	if ($r !== null) {
		pg_assert_true($r[0], 'only button tokens on a button');
		pg_assert_same(array('token', 'own'), $r[1]);
		pg_assert_same(array(), $r[2]);
		pg_assert_same('--bs-primary', $r[3]);
		pg_assert_same('#0d6efd', $r[4]);
	}
}

function test_style_predict_selectors()
{
	$r = style_predict_test_run(
		'var ctx = { classes: ["card", "shadow"], attrs: { id: "x" }, parents: [{ classes: ["row"] }] };'
		. 'console.log(JSON.stringify([labels(P.selectors("", ctx)), labels(P.selectors(".card:ho", ctx))]));'
	);

	if ($r !== null) {
		pg_assert_same(array('#x', '.card', '.shadow', '.card.shadow', '.row .card'), $r[0]);
		pg_assert_same(array('.card:hover'), $r[1]);
	}
}

// A dropdown trigger names what it opens; a close button inside a modal
// closes it; tabs take the toggle their list says.
function test_style_predict_attributes_and_values()
{
	$r = style_predict_test_run(
		'var trigger = P.attributes("", { tag: "button", classes: ["btn", "dropdown-toggle"], parents: [{ classes: ["dropdown"] }] });'
		. 'var close = P.attributes("data", { tag: "button", classes: ["btn-close"], parents: [{ classes: ["modal-header"] }, { classes: ["modal-content"] }, { classes: ["modal-dialog"] }, { classes: ["modal"] }] });'
		. 'var tabs = P.attributeValues("data-bs-toggle", "", { tag: "a", classes: ["nav-link"], parents: [{ classes: ["nav-item"] }, { classes: ["nav", "nav-pills"] }] });'
		. 'var plain = P.attributeValues("data-bs-toggle", "", { tag: "button", classes: ["btn"] });'
		. 'var dismiss = P.attributeValues("data-bs-dismiss", "", { classes: ["btn-close"], parents: [{ classes: ["offcanvas-header"] }, { classes: ["offcanvas"] }] });'
		. 'var target = P.attributeValues("data-bs-target", "", { tag: "button", classes: ["navbar-toggler"], design: { ids: [{ id: "m1", classes: ["modal"] }, { id: "nav1", classes: ["collapse", "navbar-collapse"] }, { id: "x", classes: [] }] } });'
		. 'var href = P.attributes("", { tag: "a", attrs: { href: "#" } });'
		. 'console.log(JSON.stringify([labels(trigger)[0], labels(close)[0], labels(tabs)[0], labels(plain)[0], labels(plain).indexOf("true"), labels(dismiss)[0], labels(target), labels(href).indexOf("href"), labels(href)[0]]));'
	);

	if ($r !== null) {
		pg_assert_same('data-bs-toggle', $r[0]);
		pg_assert_same('data-bs-dismiss', $r[1]);
		pg_assert_same('pill', $r[2]);
		pg_assert_same('modal', $r[3]);
		pg_assert_same(-1, $r[4]);
		pg_assert_same('offcanvas', $r[5]);
		pg_assert_same(array('#nav1', '#m1', '#x'), $r[6]);
		pg_assert_same(-1, $r[7]);
		pg_assert_contains($r[8], array('download', 'rel', 'target'));
	}
}

function test_style_predict_javascript_api()
{
	$r = style_predict_test_run(
		'var ctx = { classes: ["modal", "fade"] };'
		. 'console.log(JSON.stringify([labels(P.jsClasses("", ctx))[0], labels(P.jsClasses("Toa", {})), labels(P.jsMembers("Modal", "", false)).slice(0, 3), labels(P.jsMembers("bootstrap.Modal", "get", true)), labels(P.jsMembers("Modal", "get", true)), labels(P.jsEvents("", ctx)), labels(P.jsEvents("", {}))]));'
	);

	if ($r !== null) {
		pg_assert_same('bootstrap.Modal', $r[0]);
		pg_assert_same(array('bootstrap.Toast'), $r[1]);
		pg_assert_same(array('hide', 'show', 'toggle'), $r[2]);
		pg_assert_same(array(), $r[3]);
		pg_assert_same(array('getOrCreateInstance', 'getInstance'), $r[4]);
		pg_assert_same(array('hide.bs.modal', 'show.bs.modal', 'shown.bs.modal', 'hidden.bs.modal', 'hidePrevented.bs.modal'), $r[5]);
		pg_assert_same(array(), $r[6]);
	}
}

// An empty or missing vocabulary gives empty lists, never an error.
function test_style_predict_without_vocabulary()
{
	$r = style_predict_test_run(
		'var E = S.create(null);'
		. 'console.log(JSON.stringify([E.classes("d", {}).length, E.tokens("x").length, E.jsMembers("Modal", "", false).length, E.attributes("", {}).length > 0]));'
	);

	if ($r !== null) {
		pg_assert_same(array(0, 0, 0, true), $r);
	}
}
