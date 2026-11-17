<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor's Options panel controls
 * (assets/js/style_designer.src.js): a reset is one undo step, an invalid
 * length is not written, a click on the selected segment removes the value,
 * a value list becomes a button group or stays a select, a lone select is
 * read into its options, and the screen-size badge stands only on a value
 * that starts at a size. The helpers are cut out of the source and run
 * under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The controls' pure helpers of the editor source; '' when they are not there.
function designer_options_controls_test_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    // ── The Options panel controls: pure helpers');
	$to = ($from === false) ? false : strpos($source, '    // ── end of the Options panel controls', $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the block; its `out` comes back decoded. Null without Node.
function designer_options_controls_test_run($code)
{
	$block = designer_options_controls_test_block();
	pg_assert_true($block !== '', 'the editor has the block of the Options panel controls');

	if ($block === '' || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgctl');
	file_put_contents($file, "function _sdT(s) { return s; }\n" . $block . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A reset that changes several things takes one snapshot: every
// saveState() inside _sdUndoAsOne() joins the first. Outside it each call
// without a key is a step of its own, and the next edit after the reset is
// a new step again.
function test_designer_options_reset_is_one_undo_step()
{
	$answer = designer_options_controls_test_run(<<<'JS'
var run = { key: null, at: 0, one: null, seq: 0 };
var taken = 0;
function save(key) { if (_sdStateRunTakes(run, run.one || key || null, 1000, 700)) taken++; }
_sdUndoAsOne(run, function () { save(); save(); save('run:x'); });
var inGroup = taken;
save();
save();
var after = taken;
_sdUndoAsOne(run, function () { _sdUndoAsOne(run, function () { save(); }); save(); });
out = [inGroup, after, taken, run.one];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(1, 3, 4, null), $answer);
}

// An invalid length is not written and says why; what the canvas writes
// (a CSS length, a bare number as pixels, a keyword, a CSS function) is
// valid, and so is an empty field. A z-index is a whole number or auto.
function test_designer_options_invalid_value_not_written()
{
	$answer = designer_options_controls_test_run(<<<'JS'
var ok = ['', '12', '-8', '1.5', '12px', '50%', '12rem', '1.25em', '100vh', '0', 'auto', 'none', 'fit-content',
	'calc(100% - 2rem)', 'min(50%, 300px)', 'var(--w)', ' 20px '];
var bad = ['12pxx', 'px', '12 px', 'abc', '10%%', 'calc(100% - 2rem', '--', '1.2.3'];
out = [
	ok.filter(function (v) { return _sdLenCheck('len', v) !== ''; }),
	bad.filter(function (v) { return _sdLenCheck('len', v) === ''; }),
	_sdLenCheck('z', '10'), _sdLenCheck('z', 'auto'), _sdLenCheck('z', '') ,
	_sdLenCheck('z', '1.5') !== '', _sdLenCheck('z', '10px') !== ''
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array(), array(), '', '', '', true, true), $answer);
}

// A click on the selected segment removes the value; another segment
// takes its value; with no empty choice the selected one stays.
function test_designer_options_selected_segment_removes_value()
{
	$answer = designer_options_controls_test_run(<<<'JS'
out = [
	_sdSegNext('fw-bold', 'fw-bold', true),
	_sdSegNext('fw-bold', 'fw-light', true),
	_sdSegNext('', 'fw-bold', true),
	_sdSegNext('h2', 'h2', false)
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('', 'fw-light', 'fw-bold', 'h2'), $answer);
}

// Up to six values are a button group, seven or more a select. A group
// that does not fit beside its label stands under it; one that would not
// fit the row's whole width stays a select. Under 300 px a group of four
// or more written segments stands under its label, icons stay beside it.
function test_designer_options_group_or_select()
{
	$answer = designer_options_controls_test_run(<<<'JS'
function p(labels, icons) { var r = _sdSegPlan(labels, icons); return r.kind + (r.stack ? '/stack' : '') + (r.stackNarrow ? '/narrow' : ''); }
out = [
	p(['Thin', 'Normal', 'Bold']),
	p(['1', '2', '3', '4', '5', '6']),
	p(['1', '2', '3', '4', '5', '6', '7']),
	p([]),
	p(['Static', 'Relative', 'Absolute', 'Fixed', 'Sticky']),
	p(['Contain (whole image)', 'Cover (crop to fill)', 'Stretch to fill', 'Scale down only', 'Natural size']),
	p(['a', 'b', 'c', 'd'], true),
	p(['None', 'Start', 'End'])
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'group/narrow', 'group/narrow', 'select', 'select', 'group/stack/narrow', 'select', 'group', 'group/narrow',
	), $answer);
}

// A lone select is read into its attributes and options; anything else,
// a select with an id or one inside other markup, is left alone.
function test_designer_options_select_read()
{
	$answer = designer_options_controls_test_run(<<<'JS'
var a = _sdSelParse('<select class="form-control form-control-sm" data-stylegroup="fw-light,fw-bold"><option value="">Default</option><option value="fw-light">Light</option><option value="fw-bold" selected>Bold &amp; more</option></select>');
out = [
	a && a.attrs, a && a.opts,
	_sdSelParse('<select data-prop="x" id="sd-x"><option value="a">A</option></select>'),
	_sdSelParse('<div><select data-prop="x"><option value="a">A</option></select></div>'),
	_sdSelParse('<select data-prop="x" multiple><option value="a">A</option></select>'),
	_sdSelParse('<select data-other="x"><option value="a">A</option></select>'),
	_sdSelParse('<input data-prop="x">')
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		' data-stylegroup="fw-light,fw-bold"',
		array(
			array('v' => '', 'l' => 'Default', 'on' => false),
			array('v' => 'fw-light', 'l' => 'Light', 'on' => false),
			array('v' => 'fw-bold', 'l' => 'Bold &amp; more', 'on' => true),
		),
		null, null, null, null, null,
	), $answer);
}

// The screen-size badge stands only on a value that starts at a size: the
// first size holding one when no value holds at every size; "all" when a
// value holds at every size and a size changes it; nothing otherwise.
function test_designer_options_size_badge_only_from_a_size()
{
	$answer = designer_options_controls_test_run(<<<'JS'
var v = ['none', 'flex', 'block'];
out = [
	_sdSizeBadge('p-3 d-md-flex', 'd-', v),
	_sdSizeBadge('d-lg-none d-xl-block', 'd-', v),
	_sdSizeBadge('d-none d-md-flex', 'd-', v),
	_sdSizeBadge('d-flex', 'd-', v),
	_sdSizeBadge('', 'd-', v),
	_sdSizeBadge('d-md-grid', 'd-', v),
	_sdSizeBadge('justify-content-sm-center', 'justify-content-', [['center', 'Center'], ['end', 'End']])
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('MD', 'LG', 'all', '', '', '', 'SM'), $answer);
}
