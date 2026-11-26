<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Variables tab (assets/js/style_designer_variables.js): a
 * typed value the model would refuse is caught in the field, a used own
 * variable is asked about before Remove, and a late answer of the server
 * never takes the place of a newer change. The pure helpers are cut out of
 * the part; the change flow runs the whole part under Node with stubs.
 * Without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function design_variables_tab_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_variables.js');
}

// The pure helpers of the part; '' when they are not there.
function design_variables_tab_test_block()
{
	$source = design_variables_tab_test_source();
	$from = strpos($source, '    // ── Pure helpers');
	$to = ($from === false) ? false : strpos($source, '    // ── end of the pure helpers', $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code under Node after $prefix; its `out` comes back decoded. Null
// without Node.
function design_variables_tab_test_node($prefix, $code)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);
	if ($status !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgvtab');
	file_put_contents($file, "function _sdT(s, v) { if (Array.isArray(v)) { v.forEach(function (x, i) { s = String(s).replace('{var:' + (i + 1) + '}', x); }); return s; } return String(s).replace('{var}', v); }\n"
		. $prefix . "\nvar out = null;\n" . $code);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);
	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function design_variables_tab_test_run($code)
{
	$block = design_variables_tab_test_block();
	pg_assert_true($block !== '', 'the Variables part has its block of pure helpers');
	if ($block === '') {
		return null;
	}

	return design_variables_tab_test_node($block, $code . "\nconsole.log(JSON.stringify(out));");
}

// A typed value the model refuses is caught before it is sent: the server
// would put the default in its place, and an own size would be dropped
// with every place that reads it. A bare 0 only where the row starts at 0.
function test_design_variables_tab_typed_values()
{
	$out = design_variables_tab_test_run(
		"var base = { kind: 'length', rem: [0.75, 1.5], px: [12, 24] };"
		. " var radius = { kind: 'length', rem: [0, 3], px: [0, 48] };"
		. " var line = { kind: 'length', px: [0, 8] };"
		. " var scale = { kind: 'number', min: 1, max: 2 };"
		. " var font = { kind: 'font' };"
		. " var own = { kind: 'size' };"
		. " out = ["
		. " _sdVarsTypedOk(base, '1rem'), _sdVarsTypedOk(base, ' 16px '), _sdVarsTypedOk(base, '0'), _sdVarsTypedOk(base, '18'),"
		. " _sdVarsTypedOk(base, '2rem'), _sdVarsTypedOk(base, '-1rem'), _sdVarsTypedOk(base, '1em'),"
		. " _sdVarsTypedOk(radius, '0'), _sdVarsTypedOk(line, '0'), _sdVarsTypedOk(line, '1rem'),"
		. " _sdVarsTypedOk(scale, '1.25'), _sdVarsTypedOk(scale, '0'), _sdVarsTypedOk(scale, 'abc'),"
		. " _sdVarsTypedOk(font, 'Inter, \"Segoe UI\", sans-serif'), _sdVarsTypedOk(font, '\"Inter'), _sdVarsTypedOk(font, 'a; color: red'),"
		. " _sdVarsTypedOk(own, '0'), _sdVarsTypedOk(own, '2.5rem'), _sdVarsTypedOk(own, '2,5rem'), _sdVarsTypedOk(own, '400px'),"
		. " _sdVarsTypedHint(base), _sdVarsTypedHint(line), _sdVarsTypedHint(scale) ];"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same(array(
		true, true, false, false,
		false, false, false,
		true, true, false,
		true, false, false,
		true, false, false,
		true, true, false, false,
		'From 0.75rem to 1.5rem, or from 12px to 24px.', 'From 0px to 8px.', 'A number from 1 to 2.',
	), $out);
}

// Remove on an own colour or size that elements read is asked about, as a
// curve's is: those elements would lose their value without a word.
function test_design_variables_tab_remove_used()
{
	$out = design_variables_tab_test_run(
		"var used = { 'c-sand': 3, 'c-gap': 1 };"
		. " out = [ _sdVarsUsedBy({ key: 'sand', kind: 'color' }, used), _sdVarsUsedBy({ key: 'gap', kind: 'size' }, used),"
		. " _sdVarsUsedBy({ key: 'ease', kind: 'curve' }, used), _sdVarsUsedBy(null, used), _sdVarsUsedBy({ key: 'sand', kind: 'color' }, null) ];"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same(array(3, 1, 0, 0, 0), $out);
}

// The answer to an older change arrives after a newer change was made: the
// hidden input the save posts keeps the newer model.
function test_design_variables_tab_late_answer()
{
	$part = design_variables_tab_test_source();
	$prefix = "var parts = {}; var StyleDesigner = { definePart: function (n, f) { parts[n] = f; } };\n"
		. "var timers = []; setTimeout = function (f) { timers.push(f); return timers.length; }; clearTimeout = function (t) { if (t) timers[t - 1] = null; };\n"
		. "function flush() { var t = timers; timers = []; t.forEach(function (f) { if (f) f(); }); }\n"
		. $part;
	$code = "var queue = [];\n"
		. "var K = { _sdT: _sdT, esc: function (s) { return String(s); }, sdToast: function () {}, _sdTipsOff: function () { return true; },\n"
		. "  _sdCurveSign: function () { return ''; }, _sdCurveNums: function (v) { var n = String(v).split(',').map(Number); return n.length === 4 ? n : null; },\n"
		. "  _pgApiPost: function (sub, body) { return new Promise(function (r) { queue.push({ body: JSON.parse(JSON.stringify(body)), r: r }); }); } };\n"
		. "function answer(i) { var q = queue[i]; q.r({ status: 'success', model: q.body.model || { values: {}, custom: [] }, list: { groups: [], rows: [] }, css: '', contrast: [] }); }\n"
		. "var P = parts.variables(K); var model = null;\n"
		. "var host = { look: function () { return ''; }, palette: function () { return ''; }, model: function () { return model; },\n"
		. "  setModel: function (m) { model = m; }, tryCss: function () {}, el: null };\n"
		. "function tick() { return new Promise(function (r) { setImmediate(r); }); }\n"
		. "(async function () {\n"
		. "  var a = P.curve(host, 'add', { name: 'A', value: '0.1, 0.2, 0.3, 0.4' }); await tick(); answer(0); await a; flush();\n"
		. "  var b = P.curve(host, 'add', { name: 'B', value: '0.2, 0.2, 0.3, 0.4' }); await b;\n"
		. "  answer(1); await tick(); await tick();\n"
		. "  out = model.custom.map(function (c) { return c.name; });\n"
		. "  console.log(JSON.stringify(out));\n"
		. "})();";
	$out = design_variables_tab_test_node($prefix, $code);
	if ($out === null) {
		return;
	}
	pg_assert_same(array('A', 'B'), $out);
}
