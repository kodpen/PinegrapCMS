<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a grid box's named areas in the Options panel's Advanced ›
 * Layout: `grid-template-areas` read and written as rectangles of cells
 * (assets/js/pg_grid_areas.js), names, overlaps, fewer tracks; the part
 * that draws the small grid and a child's "Area", and its wiring. The pure
 * code runs under Node; without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the areas model as A; returns what `out` holds.
function designer_grid_areas_run($script)
{
	$file = PG_FUNCTIONS_DIR . '/assets/js/pg_grid_areas.js';
	pg_assert_true(is_file($file), 'assets/js/pg_grid_areas.js exists');

	if (!is_file($file) || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$tmp = @tempnam(sys_get_temp_dir(), 'pgarea');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, 'var A = require(' . json_encode($file) . ");\nvar out = null;\n" . $script . "\nconsole.log(JSON.stringify(out));\n");
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	@unlink($tmp);
	pg_assert_same(0, $code, implode("\n", $out));

	return $code === 0 ? json_decode(implode("\n", $out), true) : null;
}

// A value read back as rectangles; a value that is not one is refused.
function test_designer_grid_areas_parse()
{
	$r = designer_grid_areas_run(<<<'JS'
out = {
	none: A.parse(''),
	noneKw: A.parse('none'),
	four: A.parse('"header header header" "filters classes classes" "footer footer footer"'),
	dots: A.parse("'a a .' '. ... b'"),
	uneven: A.parse('"a a" "b"'),
	notRect: A.parse('"a a" "a b"'),
	split: A.parse('"a b a"'),
	bare: A.parse('a b'),
	badName: A.parse('"1a b"')
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(true, array()), array($r['none']['ok'], $r['none']['areas']), 'no value: no area');
	pg_assert_same(array(true, array()), array($r['noneKw']['ok'], $r['noneKw']['areas']), 'none: no area');
	pg_assert_same(array(3, 3), array($r['four']['cols'], $r['four']['rows']), 'three by three');
	pg_assert_same(array(
		array('name' => 'header', 'c' => 1, 'r' => 1, 'w' => 3, 'h' => 1),
		array('name' => 'filters', 'c' => 1, 'r' => 2, 'w' => 1, 'h' => 1),
		array('name' => 'classes', 'c' => 2, 'r' => 2, 'w' => 2, 'h' => 1),
		array('name' => 'footer', 'c' => 1, 'r' => 3, 'w' => 3, 'h' => 1),
	), $r['four']['areas'], 'four areas as rectangles');
	pg_assert_same(array(true, 2), array($r['dots']['ok'], count($r['dots']['areas'])), 'dots are free cells, single quotes read');
	foreach (array('uneven', 'notRect', 'split', 'bare', 'badName') as $k) {
		pg_assert_same(false, $r[$k]['ok'], $k . ' is not areas the grid can draw');
	}
}

// Written back; names, overlaps, the rectangle between two cells.
function test_designer_grid_areas_build_and_checks()
{
	$r = designer_grid_areas_run(<<<'JS'
var four = A.parse('"header header header" "filters classes classes" "footer footer footer"').areas;
var two = [{ name: 'header', c: 1, r: 1, w: 3, h: 1 }, { name: 'area2', c: 2, r: 2, w: 1, h: 1 }];
out = {
	round: A.build(four, 3, 3),
	gaps: A.build(two, 3, 2),
	empty: A.build([], 3, 3),
	ok: A.nameError('sidebar', four, -1),
	taken: A.nameError('footer', four, -1),
	self: A.nameError('footer', four, 3),
	bad: [A.nameError('2col', four, -1), A.nameError('a b', four, -1), A.nameError('', four, -1), A.nameError('auto', four, -1)],
	next: A.nextName(two),
	nextFirst: A.nextName([]),
	at: [A.at(four, 3, 2), A.at(two, 1, 2)],
	free: A.free(two, { c: 1, r: 2, w: 1, h: 1 }, 3, 2, -1),
	overlap: A.free(two, { c: 1, r: 2, w: 2, h: 1 }, 3, 2, -1),
	moveSelf: A.free(two, { c: 2, r: 2, w: 2, h: 1 }, 3, 2, 1),
	outside: A.free(two, { c: 3, r: 2, w: 2, h: 1 }, 3, 2, -1),
	rect: A.rect({ c: 3, r: 1 }, { c: 2, r: 2 }),
	count: [A.count({ mode: 'equal', count: 3 }), A.count({ mode: 'fixed', count: 2 }), A.count({ mode: 'fit', count: 0 }), A.count({ mode: '', count: 0 })],
	item: [A.item('classes'), A.item('auto'), A.item('1 / 2 / 3 / 4'), A.item('')]
};
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('"header header header" "filters classes classes" "footer footer footer"', $r['round'], 'read and written the same');
	pg_assert_same('"header header header" ". area2 ."', $r['gaps'], 'a free cell is a dot');
	pg_assert_same('', $r['empty'], 'no area writes nothing');
	pg_assert_same('', $r['ok'], 'a new name is kept');
	pg_assert_same('taken', $r['taken'], 'a name another area has');
	pg_assert_same('', $r['self'], 'an area keeps its own name');
	pg_assert_same(array('bad', 'bad', 'bad', 'bad'), $r['bad'], 'letters, digits, - and _, starting with a letter');
	pg_assert_same(array('area3', 'area1'), array($r['next'], $r['nextFirst']), 'the next free name');
	pg_assert_same(array(2, -1), $r['at'], 'the area on a cell');
	pg_assert_same(array(true, false, true, false), array($r['free'], $r['overlap'], $r['moveSelf'], $r['outside']), 'free cells only, inside the grid');
	pg_assert_same(array('c' => 2, 'r' => 1, 'w' => 2, 'h' => 2), $r['rect'], 'the rectangle between two cells');
	pg_assert_same(array(3, 2, 0, 0), $r['count'], 'only Equal and Fixed sizes set a count');
	pg_assert_same(array('classes', '', '', ''), $r['item'], 'a child\'s area name');
}

// A column or row taken away cuts the areas that reached into it.
function test_designer_grid_areas_fit()
{
	$r = designer_grid_areas_run(<<<'JS'
var four = A.parse('"header header header" "filters classes classes" "footer footer footer"').areas;
out = { two: A.fit(four, 2, 3), oneRow: A.fit(four, 3, 1), more: A.fit(four, 4, 4) };
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('header', 'classes', 'footer'), $r['two']['cut'], 'narrower areas named');
	pg_assert_same(array(), $r['two']['gone'], 'none gone at two columns');
	pg_assert_same(1, $r['two']['areas'][2]['w'], 'classes keeps column 2');
	pg_assert_same(array('filters', 'classes', 'footer'), $r['oneRow']['gone'], 'an area left with no cell is removed');
	pg_assert_same(array(array(), array()), array($r['more']['cut'], $r['more']['gone']), 'more tracks arrive as free cells');
}
