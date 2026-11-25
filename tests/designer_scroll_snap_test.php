<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Scroll snap in the Options panel's Advanced › Layout
 * (assets/js/style_designer.src.js): which elements take "Scroll snap" and
 * "Snap to", where the rows stand in Layout's plan, which rules a class list
 * needs and when the row says the box does not scroll yet. The pure blocks
 * are cut out of the source and run under Node; without Node those cases
 * are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_scroll_snap_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The block between two marker comments of the editor source; '' when absent.
function designer_scroll_snap_test_block($source, $from_marker, $to_marker)
{
	$from = strpos($source, '    // ── ' . $from_marker);
	$to = ($from === false) ? false : strpos($source, '    // ── ' . $to_marker, $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Runs $code after the Options tabs and Layout by context blocks; its `out`
// comes back decoded. Null without Node.
function designer_scroll_snap_test_run($code)
{
	$source = designer_scroll_snap_test_source();
	$tabs = designer_scroll_snap_test_block($source, 'The Options tabs: pure helpers', 'end of the Options tabs');
	$lay = designer_scroll_snap_test_block($source, 'Layout by context: pure helpers', 'end of layout by context');
	pg_assert_true($tabs !== '' && $lay !== '', 'the editor has the blocks of the Options tabs and of Layout by context');

	if ($tabs === '' || $lay === '' || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);
	if ($status !== 0) {
		return null;
	}

	$stubs = "function esc(s) { return String(s == null ? '' : s); }\nfunction _sdT(s, v) { return v === undefined ? s : s.replace('{var}', v); }\n";
	$file = tempnam(sys_get_temp_dir(), 'pgsnap');
	file_put_contents($file, $stubs . $tabs . $lay . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A flex or grid box at any size takes Scroll snap; the scroller's row
// (pg-snap) keeps its own; a block box does not.
function test_designer_scroll_snap_box()
{
	$answer = designer_scroll_snap_test_run(<<<'JS'
out = [
	_sdSnapBox('semantic', 'd-flex gap-3'),
	_sdSnapBox('semantic', 'd-grid'),
	_sdSnapBox('semantic', 'd-md-inline-grid'),
	_sdSnapBox('row', 'g-3'),
	_sdSnapBox('row', 'pg-snap flex-nowrap'),
	_sdSnapBox('semantic', 'd-block'),
	_sdSnapBox('semantic', ''),
	_sdSnapBox('semantic', 'pg-snap-wide d-flex')
];
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, false, false, false, true), $answer);
}

// Scroll snap follows its own context: after Gap on a box that takes it,
// after Fill on the child of one, never named in the note; a row holding
// a value is drawn whatever the context.
function test_designer_scroll_snap_plan()
{
	$answer = designer_scroll_snap_test_run(<<<'JS'
out = {
	flexBox: _sdLayPlan({ flex: true, snapBox: true }, {}).rows,
	gridBox: _sdLayPlan({ flex: false, snapBox: true }, {}),
	item: _sdLayPlan({ parentFlex: true, snapItem: true }, {}).rows,
	gridItem: _sdLayPlan({ snapItem: true }, {}).rows,
	scroller: _sdLayPlan({ flex: true, snapBox: false }, {}).rows,
	held: _sdLayPlan({}, { snapTo: true }).rows,
	of: ['snap', 'snapTo'].map(_sdLayOf)
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('container', 'direction', 'wrap', 'justify', 'alignItems', 'alignContent', 'gap', 'snap'), $answer['flexBox'], 'a flex box: Scroll snap after Gap');
	pg_assert_same(array('snap'), $answer['gridBox']['rows'], 'a grid box: Scroll snap alone');
	pg_assert_same(array('direction', 'wrap', 'alignment', 'gap'), $answer['gridBox']['hidden']['box'], 'Scroll snap is never named in the note');
	pg_assert_same(array('alignSelf', 'order', 'grow', 'shrink', 'fill', 'snapTo'), $answer['item'], 'a flex child: Snap to after Fill');
	pg_assert_same(array('snapTo'), $answer['gridItem'], 'a grid child: Snap to alone');
	pg_assert_false(in_array('snap', $answer['scroller'], true), 'the scroller row has no Scroll snap');
	pg_assert_same(array('snapTo'), $answer['held'], 'a Snap to holding a value is drawn');
	pg_assert_same(array('box', 'item'), $answer['of']);
}

// The rules a class list needs, and when the box does not scroll yet.
function test_designer_scroll_snap_rules()
{
	$answer = designer_scroll_snap_test_run(<<<'JS'
out = {
	names: [
		_sdSnapNames('d-flex pg-scroll-snap-always'),
		_sdSnapNames('pg-snap-to-center'),
		_sdSnapNames('pg-snap pg-snap-item pg-snap-wide'),
		_sdSnapNames(''),
		_sdSnapNames('pg-scroll-snap-alwaysx')
	],
	scroll: [
		_sdSnapNeedsScroll('d-flex pg-scroll-snap-near', {}),
		_sdSnapNeedsScroll('d-flex pg-scroll-snap-near', { overflowX: 'auto' }),
		_sdSnapNeedsScroll('d-flex pg-scroll-snap-always overflow-x-scroll', null),
		_sdSnapNeedsScroll('d-flex pg-scroll-snap-always', { overflow: 'hidden' }),
		_sdSnapNeedsScroll('d-flex', {}),
		_sdSnapNeedsScroll('d-grid pg-scroll-snap-always overflow-auto', null)
	]
};
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array('pg-scroll-snap-always'), array('pg-snap-to-center'), array(), array(), array()), $answer['names']);
	pg_assert_same(array(true, false, false, true, false, false), $answer['scroll']);
}

// Every Scroll snap class has its rule in _SD_BLOCK_CSS, so a pick adds it
// to User Styles once; the scroller's rules stay as they were.
function test_designer_scroll_snap_block_rules()
{
	$source = designer_scroll_snap_test_source();
	$rules = array(
		'pg-scroll-snap-always' => '.pg-scroll-snap-always { scroll-snap-type: both mandatory; }',
		'pg-scroll-snap-near' => '.pg-scroll-snap-near { scroll-snap-type: both proximity; }',
		'pg-snap-to-start' => '.pg-snap-to-start { scroll-snap-align: start; }',
		'pg-snap-to-center' => '.pg-snap-to-center { scroll-snap-align: center; }',
		'pg-snap-to-end' => '.pg-snap-to-end { scroll-snap-align: end; }',
	);
	foreach ($rules as $key => $rule) {
		pg_assert_contains("'" . $key . "'", $source, 'the block rule ' . $key . ' is listed');
		pg_assert_contains($rule, $source, 'its rule is written');
	}
	pg_assert_contains("'pg-snap':         '.pg-snap { scroll-snap-type: x mandatory; }'", $source, 'the scroller keeps its rule');
	pg_assert_contains('_sdSnapEnsure(val);', $source, 'a pick adds the rule');
}
