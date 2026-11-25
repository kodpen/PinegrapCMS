<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a grid's Columns and Rows in the Options panel's Advanced ›
 * Layout: the track values (assets/js/pg_grid_tracks.js) read and written
 * as a count of equal tracks, fixed sizes or "As many as fit" with a
 * smallest width; where the rows stand in Layout's plan (a grid box keeps
 * Gap); the part that draws them and its wiring. The pure code runs under
 * Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whether Node runs here.
function designer_grid_tracks_node()
{
	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	return $code === 0;
}

// Runs $prefix and $script under Node; returns what `out` holds, decoded.
function designer_grid_tracks_node_run($prefix, $script)
{
	$file = @tempnam(sys_get_temp_dir(), 'pggrid');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $prefix . "\nvar out = null;\n" . $script . "\nconsole.log(JSON.stringify(out));\n");
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return $code === 0 ? json_decode(implode("\n", $out), true) : null;
}

// Runs $script with the track model as G, the size writer as S.
function designer_grid_tracks_run($script)
{
	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	pg_assert_true(is_file($dir . '/pg_grid_tracks.js'), 'assets/js/pg_grid_tracks.js exists');

	if (!is_file($dir . '/pg_grid_tracks.js') || !designer_grid_tracks_node()) {
		return null;
	}

	return designer_grid_tracks_node_run(
		"global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'require(' . json_encode($dir . '/pg_custom_style.js') . ");\n"
		. 'var S = require(' . json_encode($dir . '/pg_size_style.js') . ");\n"
		. 'var G = require(' . json_encode($dir . '/pg_grid_tracks.js') . ");\n",
		$script
	);
}

function designer_grid_tracks_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// Runs $script after the Options tabs and Layout by context blocks.
function designer_grid_tracks_lay_run($script)
{
	$source = designer_grid_tracks_source();
	$cut = function ($from, $to) use ($source) {
		$a = strpos($source, '    // ── ' . $from);
		$b = ($a === false) ? false : strpos($source, '    // ── ' . $to, $a);

		return ($b === false) ? '' : substr($source, $a, $b - $a);
	};
	$tabs = $cut('The Options tabs: pure helpers', 'end of the Options tabs');
	$lay = $cut('Layout by context: pure helpers', 'end of layout by context');
	pg_assert_true($tabs !== '' && $lay !== '', 'the editor has the blocks of the Options tabs and of Layout by context');

	if ($tabs === '' || $lay === '' || !designer_grid_tracks_node()) {
		return null;
	}

	return designer_grid_tracks_node_run(
		"function esc(s) { return String(s == null ? '' : s); }\nfunction _sdT(s, v) { return v === undefined ? s : s.replace('{var}', v); }\n" . $tabs . $lay,
		$script
	);
}

// A value read back as the mode it was written in.
function test_designer_grid_tracks_parse()
{
	$r = designer_grid_tracks_run(<<<'JS'
out = {
	none: G.parse('', 'columns'),
	equal: G.parse('repeat(3, minmax(0, 1fr))', 'columns'),
	equalPlain: G.parse('repeat(4,1fr)', 'columns'),
	fit: G.parse('repeat(auto-fit, minmax(min(240px, 100%), 1fr))', 'columns'),
	fill: G.parse('repeat(auto-fill, minmax(12rem, 1fr))', 'columns'),
	fixed: G.parse('200px  1fr 2fr', 'columns'),
	fixedRows: G.parse('auto 1fr auto', 'rows'),
	fitRows: G.parse('repeat(auto-fit, minmax(min(240px, 100%), 1fr))', 'rows'),
	typed: G.parse('[a] 1fr [b] 2fr', 'columns'),
	tooMany: G.parse('repeat(13, 1fr)', 'columns')
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('', $r['none']['mode'], 'no value: Default');
	pg_assert_same(array('equal', 3), array($r['equal']['mode'], $r['equal']['count']), 'a count of equal columns');
	pg_assert_same(array('equal', 4), array($r['equalPlain']['mode'], $r['equalPlain']['count']), 'repeat(n, 1fr) is equal too');
	pg_assert_same(array('fit', '240px'), array($r['fit']['mode'], $r['fit']['min']), 'As many as fit, its smallest width');
	pg_assert_same(array('fit', '12rem'), array($r['fill']['mode'], $r['fill']['min']), 'auto-fill reads as As many as fit');
	pg_assert_same(array('fixed', array('200px', '1fr', '2fr'), 3), array($r['fixed']['mode'], $r['fixed']['sizes'], $r['fixed']['count']), 'fixed sizes and their count');
	pg_assert_same(array('fixed', array('auto', '1fr', 'auto')), array($r['fixedRows']['mode'], $r['fixedRows']['sizes']), 'fixed rows');
	pg_assert_same('typed', $r['fitRows']['mode'], 'rows have no As many as fit');
	pg_assert_same(array('typed', '[a] 1fr [b] 2fr'), array($r['typed']['mode'], $r['typed']['value']), 'any other value is kept as typed');
	pg_assert_same('typed', $r['tooMany']['mode'], 'more than twelve is typed');
}

// What each mode writes; a value out of range or not a track size is
// refused (null); Default writes nothing.
function test_designer_grid_tracks_build()
{
	$r = designer_grid_tracks_run(<<<'JS'
out = [
	G.build({ mode: '' }, 'columns'),
	G.build({ mode: 'equal', count: 3 }, 'columns'),
	G.build({ mode: 'equal', count: '2' }, 'rows'),
	G.build({ mode: 'equal', count: 0 }, 'columns'),
	G.build({ mode: 'equal', count: 13 }, 'columns'),
	G.build({ mode: 'equal', count: 2.5 }, 'columns'),
	G.build({ mode: 'fixed', sizes: ['200', '1fr', 'auto', 'minmax(100px, 1fr)', 'fit-content(20rem)'] }, 'columns'),
	G.build({ mode: 'fixed', sizes: ['1fr', 'red'] }, 'columns'),
	G.build({ mode: 'fixed', sizes: [] }, 'columns'),
	G.build({ mode: 'fit', min: '240' }, 'columns'),
	G.build({ mode: 'fit', min: '15rem' }, 'columns'),
	G.build({ mode: 'fit', min: '1fr' }, 'columns'),
	G.build({ mode: 'fit', min: '240px' }, 'rows'),
	G.build({ mode: 'typed', value: '[a] 1fr [b] 2fr' }, 'columns'),
	G.build({ mode: 'typed', value: '1fr; color: red' }, 'columns')
];
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'',
		'repeat(3, minmax(0, 1fr))',
		'repeat(2, minmax(0, 1fr))',
		null,
		null,
		null,
		'200px 1fr auto minmax(100px, 1fr) fit-content(20rem)',
		null,
		null,
		'repeat(auto-fit, minmax(min(240px, 100%), 1fr))',
		'repeat(auto-fit, minmax(min(15rem, 100%), 1fr))',
		null,
		null,
		'[a] 1fr [b] 2fr',
		null,
	), $r);
}

// Choosing a mode starts from what the element shows: its count kept, a
// sensible value where it has none, so no further choice is needed.
function test_designer_grid_tracks_pick()
{
	$r = designer_grid_tracks_run(<<<'JS'
var three = G.parse('repeat(3, minmax(0, 1fr))', 'columns');
var none = G.parse('', 'columns');
out = [
	G.pick(none, 'equal', 'columns'),
	G.pick(none, 'equal', 'rows'),
	G.pick(none, 'fixed', 'columns'),
	G.pick(none, 'fixed', 'rows'),
	G.pick(none, 'fit', 'columns'),
	G.pick(three, 'fixed', 'columns'),
	G.pick(G.parse('200px 1fr', 'columns'), 'equal', 'columns'),
	G.pick(three, '', 'columns'),
	G.pick(none, 'fit', 'rows')
];
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'repeat(3, minmax(0, 1fr))',
		'repeat(2, minmax(0, 1fr))',
		'1fr 1fr',
		'auto auto',
		'repeat(auto-fit, minmax(min(200px, 100%), 1fr))',
		'1fr 1fr 1fr',
		'repeat(2, minmax(0, 1fr))',
		'',
		null,
	), $r);
}

// A field's change: the count, the sizes typed with spaces, the smallest
// width, the typed value; refused input gives null.
function test_designer_grid_tracks_edit()
{
	$r = designer_grid_tracks_run(<<<'JS'
var eq = G.parse('repeat(3, minmax(0, 1fr))', 'columns');
var fit = G.parse('repeat(auto-fit, minmax(min(200px, 100%), 1fr))', 'columns');
var fx = G.parse('1fr 1fr', 'columns');
out = [
	G.edit(eq, 'count', '4', 'columns'),
	G.edit(eq, 'count', 'x', 'columns'),
	G.edit(fx, 'sizes', ' 240px   1fr ', 'columns'),
	G.edit(fx, 'sizes', '240px, 1fr', 'columns'),
	G.edit(fx, 'sizes', '', 'columns'),
	G.edit(fit, 'min', '18rem', 'columns'),
	G.edit(fit, 'min', '', 'columns'),
	G.edit(eq, 'typed', 'repeat(2, 100px 1fr)', 'columns')
];
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'repeat(4, minmax(0, 1fr))',
		null,
		'240px 1fr',
		'240px 1fr',
		null,
		'repeat(auto-fit, minmax(min(18rem, 100%), 1fr))',
		null,
		'repeat(2, 100px 1fr)',
	), $r);
}

// The value goes through the per size writer: a size's rule on the pgs-
// class, read back per size, the value in force at a size inherited.
function test_designer_grid_tracks_per_size()
{
	$r = designer_grid_tracks_run(<<<'JS'
var c = 'pgs-abc123', t = '';
t = S.write(t, c, G.CSS.columns, 'xs', G.build({ mode: 'equal', count: 1 }, 'columns'));
t = S.write(t, c, G.CSS.columns, 'md', G.build({ mode: 'equal', count: 3 }, 'columns'));
var v = S.read(t, c, G.CSS.columns);
out = { text: t, lg: S.at(v, 'lg'), held: S.held(t, c, [G.CSS.columns, G.CSS.rows]), css: G.CSS };
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('columns' => 'grid-template-columns', 'rows' => 'grid-template-rows'), $r['css']);
	pg_assert_true(strpos($r['text'], ".pgs-abc123 {\n  grid-template-columns: repeat(1, minmax(0, 1fr));\n}") !== false, 'every size in the base rule');
	pg_assert_true(strpos($r['text'], "@media (min-width: 768px) {\n  .pgs-abc123 {\n    grid-template-columns: repeat(3, minmax(0, 1fr));") !== false, 'MD up in its @media rule');
	pg_assert_same(array('value' => 'repeat(3, minmax(0, 1fr))', 'here' => false, 'size' => 'md'), $r['lg'], 'LG takes MD\'s');
	pg_assert_same(array('md'), $r['held']);
}

// A grid box at any size takes Columns and Rows, keeps Gap and Scroll
// snap; a flex box gets no grid rows; the rows stand before Gap.
function test_designer_grid_tracks_layout_plan()
{
	$r = designer_grid_tracks_lay_run(<<<'JS'
out = {
	box: [_sdGridBox('d-grid gap-3'), _sdGridBox('d-md-inline-grid'), _sdGridBox('d-flex'), _sdGridBox(''), _sdGridBox('d-block d-lg-grid')],
	grid: _sdLayPlan({ flex: false, gridBox: true, snapBox: true }, {}),
	flex: _sdLayPlan({ flex: true, snapBox: true }, {}).rows,
	of: _sdLayOf('grid')
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(true, true, false, false, true), $r['box']);
	pg_assert_same(array('grid', 'gap', 'snap'), $r['grid']['rows'], 'a grid box: Columns and Rows, Gap, Scroll snap');
	pg_assert_same(array('direction', 'wrap', 'alignment'), $r['grid']['hidden']['box'], 'Gap is not named as hidden on a grid');
	pg_assert_false(in_array('grid', $r['flex'], true), 'a flex box has no grid rows');
	pg_assert_same('box', $r['of']);
}

// The rows are a part loaded on first use, drawn into their place in
// Layout through the size host; the context knows a grid box.
function test_designer_grid_tracks_wiring()
{
	$src = designer_grid_tracks_source();
	pg_assert_true(strpos($src, "{ id: 'grid', of: 'box', name: '', when: 'gridBox' }") !== false, 'Layout lists the grid rows');
	pg_assert_true(strpos($src, 'gridBox: _sdGridBox(p.cssClass)') !== false, 'the context reads a grid box');
	pg_assert_true(strpos($src, 'gridBox: a.gridBox && c.gridBox') !== false, 'several elements: a grid box when all are');
	pg_assert_true(strpos($src, '<div data-sd-gridbox hidden></div>') !== false, 'the rows\' place in Layout');
	pg_assert_true(strpos($src, "_sdPartLoad('spacing').then(function () { return _sdPartCall('grid', 'draw', [_sdSzHost(") !== false, 'the part draws through the size host, after the spacing part');

	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_grid.min.js', isset($parts['grid']) ? $parts['grid'] : '', 'grid is a part');
	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_grid_tracks.js', 'assets/js/style_designer_grid.js'),
		isset($bundles['assets/js/style_designer_grid.min.js']) ? $bundles['assets/js/style_designer_grid.min.js'] : null, 'its bundle');
	pg_assert_same('always', pg_designer_part_files()['grid']['when'], 'the editor is handed the part');

	$part = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_grid.js');
	pg_assert_true(strpos($part, "StyleDesigner.definePart('grid', function (K) {") !== false, 'the part registers itself');

	foreach (array('Columns', 'Rows', 'Equal', 'Fixed sizes', 'As many as fit', 'Smallest width', 'Screen size') as $key) {
		pg_assert_true(strpos($part, "_sdT('" . $key . "')") !== false, 'the part says ' . $key);
	}
}
