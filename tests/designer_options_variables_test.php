<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design variables offered by the Options panel
 * (assets/js/style_designer.src.js): a bound value is written as a CSS
 * expression on the design's --pg-var-* tokens and read back into the
 * variable, the type scale's step or the base unit's multiple it names;
 * anything else is a typed value. The computed rows give the size they
 * give now, and "Used in n places" counts the elements that use a token.
 * The helpers are cut out of the source and run under Node; without Node
 * those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The pure helpers of the editor source; '' when they are not there.
function designer_options_variables_test_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    // ── Design variables in Options: pure helpers');
	$to = ($from === false) ? false : strpos($source, '    // ── end of the design variables in Options', $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the block; its `out` comes back decoded. Null without Node.
function designer_options_variables_test_run($code)
{
	$block = designer_options_variables_test_block();
	pg_assert_true($block !== '', 'the editor has the block of the design variables in Options');

	if ($block === '' || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgvar');
	file_put_contents($file, "function _sdT(s, v) { return String(s).replace('{var}', v); }\n" . $block . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_designer_options_variables_expressions()
{
	$out = designer_options_variables_test_run(
		"out = [_sdVarExpr({ token: 'brand' }), _sdVarExpr({ token: 'c-sand' }),"
		. " _sdVarExpr({ step: 2 }), _sdVarExpr({ step: -1 }), _sdVarExpr({ units: 6 }),"
		. " _sdVarExpr({ step: 0 }), _sdVarExpr({ units: 5 }), _sdVarExpr({ token: 'Brand;x' })];"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same(array(
		'var(--pg-var-brand)',
		'var(--pg-var-c-sand)',
		'calc(var(--pg-var-size-base) * var(--pg-var-scale) * var(--pg-var-scale))',
		'calc(var(--pg-var-size-base) / var(--pg-var-scale))',
		'calc(var(--pg-var-space-unit) * 6)',
		'',
		'',
		'',
	), $out);
}

function test_designer_options_variables_parse()
{
	$out = designer_options_variables_test_run(
		"var vals = ['var(--pg-var-brand)', ' var(--pg-var-c-sand) ', _sdVarExpr({ step: 5 }), _sdVarExpr({ step: -2 }),"
		. " _sdVarExpr({ units: 8 }), '#2563eb', 'var(--bs-primary)', 'calc(var(--pg-var-space-unit) * 7)', 'var(--pg-var-brand, red)', ''];"
		. " out = vals.map(_sdVarParse);"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same(array('token' => 'brand'), $out[0]);
	pg_assert_same(array('token' => 'c-sand'), $out[1]);
	pg_assert_same(array('step' => 5), $out[2]);
	pg_assert_same(array('step' => -2), $out[3]);
	pg_assert_same(array('units' => 8), $out[4]);
	for ($i = 5; $i < 10; $i++) {
		pg_assert_same(null, $out[$i], 'value ' . $i . ' is typed');
	}
}

function test_designer_options_variables_computed_rows()
{
	$out = designer_options_variables_test_run(
		"out = {"
		. " steps: _SD_VAR_STEPS.map(function (s) { return s[0] + ':' + _sdVarStepPx(16, 1.25, s[1]); }),"
		. " units: _SD_VAR_UNITS.map(function (u) { return _sdVarUnitPx(8, u); }),"
		. " px: [_sdVarPx('1rem'), _sdVarPx('13px'), _sdVarPx('0'), _sdVarPx('auto')],"
		. " names: [_sdVarRefName({ step: 3 }, {}), _sdVarRefName({ units: 6 }, {}), _sdVarRefName({ token: 'brand' }, { brand: 'Brand' }), _sdVarRefName({ token: 'c-caption-size' }, {})]"
		. "};"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same(array('XS:10', 'S:13', 'L:20', 'XL:25', '2XL:31', '3XL:39', '4XL:49', '5XL:61'), $out['steps']);
	pg_assert_same(array(16, 24, 32, 48, 64), $out['units']);
	pg_assert_same(array(16, 13, 0, null), $out['px']);
	pg_assert_same(array('Scale 2XL', '6 units', 'Brand', 'Caption size'), $out['names']);
}

function test_designer_options_variables_usage()
{
	$out = designer_options_variables_test_run(
		"var tree = { props: {}, children: ["
		. " { props: { _attrs: [{ name: 'style', value: 'color: var(--pg-var-brand); border-color: var(--pg-var-brand)' }] }, children: [] },"
		. " { props: { _attrs: [{ name: 'style', value: 'font-size: ' + _sdVarExpr({ step: 3 }) }] }, children: ["
		. "   { props: { _attrs: [{ name: 'style', value: 'color: var(--pg-var-brand-2)' }] } } ] }"
		. "] };"
		. " out = _sdVarUsage([tree, { props: { _attrs: [{ name: 'style', value: 'padding: ' + _sdVarExpr({ units: 2 }) }] } }, null]);"
	);
	if ($out === null) {
		return;
	}
	ksort($out);
	pg_assert_same(array('brand' => 1, 'brand-2' => 1, 'scale' => 1, 'size-base' => 1, 'space-unit' => 1), $out);
}
