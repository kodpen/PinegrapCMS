<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the easing curve (DO19): an easing in its written form
 * (pg_fx_easing_clean(), pg_fx_curve_numbers()), a stored curve as one of
 * the design's own variables (includes/fn/design_variables.php), and the
 * curve's numbers in assets/js/pg_easing_curve.js, run under Node: the
 * points of an easing, the typed numbers, where the curve stands at a
 * time, the overshoot and a handle held in the graph's room. On a machine
 * without Node the script half is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The typed numbers both sides read: text => the four numbers or null.
function _test_curve_numbers()
{
	return array(
		'0.22, 1, 0.36, 1'      => array('0.22', '1', '0.36', '1'),
		'.2,0,0,1'              => array('0.2', '0', '0', '1'),
		' 0.1234 , -0.0, 1, 1 ' => array('0.123', '0', '1', '1'),
		'0.34, 2.5, 0.64, -1'   => array('0.34', '1.5', '0.64', '-0.5'),
		'0.0005, 0, 1, 1'       => array('0.001', '0', '1', '1'),
		'1.4, 0, 0, 1'          => null,
		'0, 0, -0.1, 1'         => null,
		'0, 0, 1'               => null,
		'0, 0, 1, 1, 1'         => null,
		'a, 0, 1, 1'            => null,
		'1e1, 0, 1, 1'          => null,
		''                      => null,
	);
}

function test_designer_curve_numbers()
{
	foreach (_test_curve_numbers() as $text => $want) {
		pg_assert_same($want, pg_fx_curve_numbers($text), 'numbers: ' . $text);
	}
	pg_assert_same(null, pg_fx_curve_numbers(array('0', '0', '1', '1')), 'not a text');
}

function test_designer_curve_easing_clean()
{
	$cases = array(
		'ease-out'                                              => 'ease-out',
		' LINEAR '                                              => 'linear',
		'cubic-bezier(0.22,1,.36,1)'                            => 'cubic-bezier(0.22, 1, 0.36, 1)',
		'var(--pg-var-c-snap,cubic-bezier(0.2, 0, 0, 1))'       => 'var(--pg-var-c-snap, cubic-bezier(0.2, 0, 0, 1))',
		'var(--pg-var-c-snap, var(--x))'                        => '',
		'var(--pg-var-snap, cubic-bezier(0.2, 0, 0, 1))'        => '',
		'cubic-bezier(1.2, 0, 0, 1)'                            => '',
		'steps(4)'                                              => '',
		'cubic-bezier(0, 0, 1, 1)) ; color: red'                => '',
	);
	foreach ($cases as $in => $want) {
		pg_assert_same($want, pg_fx_easing_clean($in), 'clean: ' . $in);
	}
	pg_assert_same('', pg_fx_easing_clean(null), 'nothing');
	$attrs = pg_fx_attrs(array('_fx' => array('effect' => 'fade', 'easing' => 'var(--pg-var-c-soft-landing, cubic-bezier(0.22, 1, 0.36, 1))')));
	pg_assert_contains(' data-pg-fx-easing="var(--pg-var-c-soft-landing, cubic-bezier(0.22, 1, 0.36, 1))"', $attrs, 'a stored curve is written with its fallback');
}

// A stored curve is one of the design's own variables: checked, written as
// its token, offered to the editor's lists, exported as a cubicBezier.
function test_designer_curve_stored_as_a_variable()
{
	$defaults = pg_design_variables_defaults('', '');
	$model = $defaults;
	$model['custom'] = array(
		array('key' => 'soft-landing', 'name' => 'Soft landing', 'kind' => 'curve', 'value' => '0.22,1,.36,1'),
		array('key' => 'bad', 'name' => 'Bad', 'kind' => 'curve', 'value' => '1.4, 0, 0, 1'),
		array('key' => 'size-1', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'),
	);
	$m = pg_design_variables_normalize($model, $defaults);
	pg_assert_same(array(
		array('key' => 'soft-landing', 'name' => 'Soft landing', 'kind' => 'curve', 'value' => '0.22, 1, 0.36, 1'),
		array('key' => 'size-1', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'),
	), $m['custom'], 'a refused curve is dropped, a good one kept in its written form');

	$css = pg_design_variables_css($m, $defaults);
	pg_assert_contains('--pg-var-c-soft-landing: cubic-bezier(0.22, 1, 0.36, 1);', $css, 'the token');
	pg_assert_same($m['custom'], pg_design_variables_read($css, $defaults)['custom'], 'read back');

	pg_assert_same(array(array('key' => 'soft-landing', 'name' => 'Soft landing', 'value' => '0.22, 1, 0.36, 1')),
		pg_design_variables_curves($model), 'the curves of a stored model, checked');
	pg_assert_same(array(), pg_design_variables_curves(null), 'no model');
	pg_assert_same(array(), pg_design_variables_curves(array('custom' => array(array('key' => 'X"', 'kind' => 'curve', 'value' => '0, 0, 1, 1')))), 'a bad key');

	$tokens = pg_design_variables_tokens_export($m)['tokens'];
	pg_assert_same(array('$type' => 'cubicBezier', '$value' => array(0.22, 1.0, 0.36, 1.0), '$description' => 'Soft landing'),
		$tokens['own']['soft-landing'], 'exported');
}

// Runs $script under Node and returns its JSON output, null without Node.
function _test_curve_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgcurve') . '.js';
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$json = json_decode(implode("\n", $out), true);
	pg_assert_true($code === 0 && $json !== null, 'node ran: ' . implode("\n", $out));
	return $json;
}

function test_designer_curve_script()
{
	$texts = array_keys(_test_curve_numbers());
	$script = 'var window = {};' . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_easing_curve.js')
		. 'var C = window.PGCurve, out = {};'
		. 'out.parse = ' . json_encode($texts) . '.map(function (t) { var p = C.parse(t); return p ? p.map(String) : null; });'
		. 'out.points = ["ease", "LINEAR", "cubic-bezier(0.22,1,.36,1)", "var(--pg-var-c-snap, cubic-bezier(0.2, 0, 0, 1))", "", "steps(4)", "var(--pg-ease)"].map(C.points);'
		. 'out.text = [C.text([0.22, 1, 0.36, 1]), C.css([0.16, 0.84, 0.44, 1]), C.text([0.1235, -0, 1, 1])];'
		. 'var r = function (n) { return Math.round(n * 1000) / 1000; };'
		. 'out.at = [r(C.at([0, 0, 1, 1], 0.3)), r(C.at([0.25, 0.1, 0.25, 1], 0)), r(C.at([0.25, 0.1, 0.25, 1], 1)), r(C.at([0.42, 0, 0.58, 1], 0.5)), r(C.at([0, 0, 0.58, 1], 0.5)), r(C.at([0, 0, 1, 1], 7))];'
		. 'out.over = [C.overshoots([0.34, 1.56, 0.64, 1]), C.overshoots([0.22, 1, 0.36, 1]), C.overshoots([0.5, -0.4, 0.5, 1])];'
		. 'out.move = [C.move([0.2, 0, 0, 1], 0, 0.123, 1.7), C.move([0.2, 0, 0, 1], 1, -2, -3), C.move([0.2, 0, 0, 1], 1, 0.555, 0.999)];'
		. 'console.log(JSON.stringify(out));';
	$got = _test_curve_node($script);
	if ($got === null) {
		return;
	}
	$i = 0;
	foreach (_test_curve_numbers() as $text => $want) {
		pg_assert_same($want, $got['parse'][$i++], 'the script reads the numbers as the server: ' . $text);
	}
	pg_assert_same(array(array(0.25, 0.1, 0.25, 1), array(0, 0, 1, 1), array(0.22, 1, 0.36, 1), array(0.2, 0, 0, 1), null, null, null), $got['points'], 'points');
	pg_assert_same(array('0.22, 1, 0.36, 1', 'cubic-bezier(0.16, 0.84, 0.44, 1)', '0.124, 0, 1, 1'), $got['text'], 'written forms');
	pg_assert_same(array(0.3, 0, 1, 0.5, 0.685), array_slice($got['at'], 0, 5), 'the change at a time');
	pg_assert_same(1, $got['at'][5], 'a time past the end is the end');
	pg_assert_same(array(true, false, true), $got['over'], 'overshoot');
	pg_assert_same(array(array(0.12, 1.5, 0, 1), array(0.2, 0, 0, -0.5), array(0.2, 0, 0.56, 1)), $got['move'], 'a handle held in the room, two decimals');
}

// The arrow keys write the curve once they rest (500 ms). A panel drawing
// in that time (another row changed) opens the editor anew: the curve the
// keys reached was lost, and the old timer then wrote the new editor's
// curve, a stored one as plain numbers that left its design variable.
// The timer belongs to its editor, and the same row reopened keeps the
// keys' curve and writes it when they rest.
function test_designer_curve_keys_survive_a_reopen()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_curve.js');
	pg_assert_contains('if (E !== ed) return;', $part, 'a timer writes only through its own editor');
	pg_assert_contains('old.timer && old.host.at === host.at', $part, 'the same row reopened keeps the keys\' curve');
	pg_assert_contains('clearTimeout(old.timer);', $part, 'a replaced editor\'s timer is stopped');
}
