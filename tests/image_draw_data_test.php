<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the server's draw of a data layer (includes/fn/image_draw_data.php
 * and its place in includes/fn/image_draw.php's plan): the description's
 * check as the model's, the rows grouped and cut, the numbers and guide of
 * the chart operation, the marks of every kind against the fixture the
 * model is held to (tests/fixtures/canvas_data/layers.json), the plan's
 * verdicts, and the row read's shape (includes/workspace/canvas_tables.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_chart.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

function image_draw_data_test_fixture()
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/canvas_data/layers.json'), true);
}

// Two mark lists alike: the same keys and words, numbers within a rounding.
function image_draw_data_test_same($a, $b)
{
	if (is_array($a) && is_array($b)) {
		if (array_keys($a) !== array_keys($b)) {
			return false;
		}

		foreach ($a as $k => $v) {
			if (!image_draw_data_test_same($v, $b[$k])) {
				return false;
			}
		}

		return true;
	}

	if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
		return abs($a - $b) < 1e-9;
	}

	return $a === $b;
}

// Every case of the shared fixture gives the marks the browser's model gives.
function test_image_draw_data_marks_match_the_fixture()
{
	$f = image_draw_data_test_fixture();

	foreach ($f['cases'] as $case) {
		$read = !empty($case['refused']) ? array('state' => 'refused') : array('columns' => $f['columns'], 'rows' => !empty($case['empty']) ? array() : $f['rows']);
		$drawn = pg_image_draw_data_layer_marks($case['data'], $case['w'], $case['h'], $read, $f['words']);

		pg_assert_true(image_draw_data_test_same($case['marks'], $drawn['marks']), $case['name'] . ' draws the fixture\'s marks');
		$unread = pg_image_draw_data_layer_marks($case['data'], $case['w'], $case['h'], null, $f['words'])['marks'];
		pg_assert_same('No rows to show.', end($unread)['v'], $case['name'] . ' unread says so');
	}

	$bad = pg_image_draw_data_layer_marks(array('table' => 0), 400, 240, array('state' => 'refused'), $f['words']);
	pg_assert_same('No rows to show.', end($bad['marks'])['v'], 'a description that fails its check draws as an empty layer');
	pg_assert_same('g:Inter', $bad['font']);
}

// The same refusal codes and defaults as M.dataCheck() (tests/image_editor_data_test.php).
function test_image_draw_data_check()
{
	$base = array('table' => 1, 'label' => 'a', 'values' => array('b'));
	$bad = array(
		'table'   => array(array('table' => 0) + $base, array('table' => 1.5) + $base, null, array(1, 2)),
		'show'    => array(array('show' => 'picture') + $base),
		'kind'    => array(array('kind' => 'pie') + $base),
		'columns' => array(array('table' => 1, 'values' => array('b')), array('values' => array()) + $base, array('values' => array('b c')) + $base,
			array('values' => array('1', '2', '3', '4', '5', '6')) + $base, array('kind' => 'donut', 'values' => array('b', 'c')) + $base, array('values' => array('x' => 'b')) + $base),
		'group'   => array(array('group' => 'max') + $base),
		'sort'    => array(array('sort' => 'random') + $base, array('dir' => 'up') + $base),
		'format'  => array(array('format' => 'de') + $base),
		'title'   => array(array('title' => "a\nb") + $base, array('title' => str_repeat('x', 121)) + $base, array('title' => 5) + $base),
		'colours' => array(array('colours' => array('red')) + $base, array('colours' => array_fill(0, 6, '#000000')) + $base),
		'theme'   => array(array('theme' => 'blue') + $base),
		'font'    => array(array('font' => 'g:') + $base, array('font' => 'Comic') + $base, array('font' => 'g:Bad!Name') + $base),
		'legend'  => array(array('legendAt' => 'left') + $base),
		'limit'   => array(array('limit' => -1) + $base, array('limit' => 51) + $base, array('limit' => 2.5) + $base, array('limit' => '3') + $base),
		'size'    => array(array('size' => 'xl') + $base, array('size' => 1) + $base),
	);

	foreach ($bad as $part => $list) {
		foreach ($list as $i => $d) {
			pg_assert_same('data_' . $part, pg_image_draw_data_check($d)['error'] ?? 'passed', $part . ' ' . $i);
		}
	}

	pg_assert_same(array('table' => 4, 'show' => 'chart', 'kind' => 'bar', 'group' => 'none', 'values' => array('12'), 'label' => '11', 'sort' => 'none', 'dir' => 'asc',
		'format' => 'en', 'title' => 'Sales', 'colours' => array('#aabbcc'), 'theme' => 'light', 'font' => 'g:Inter', 'legend' => true,
		'legendAt' => 'top', 'limit' => 0, 'size' => 'm'),
		pg_image_draw_data_check(array('table' => '4', 'label' => 11, 'values' => array('12', 12), 'title' => '  Sales ', 'colours' => array('#AABBCC')))['data'], 'defaults, ids as strings, a value once');

	$table = pg_image_draw_data_check(array('table' => 4, 'show' => 'table', 'kind' => 'pie', 'label' => '11'));
	pg_assert_true($table['ok'] && ($table['data']['kind'] === 'table') && ($table['data']['values'] === array()), 'a plain table needs no value column and takes no chart kind');
	pg_assert_same(array(), pg_image_draw_data_check(array('group' => 'count') + $base)['data']['values'], 'count takes no value column');
	pg_assert_true(pg_image_draw_data_check(array('legend' => '0') + $base)['data']['legend'], 'the legend is tested as the browser tests it');
	pg_assert_false(pg_image_draw_data_check(array('legend' => 0) + $base)['data']['legend']);
	pg_assert_same(array('bottom', 3, 'l'), array_values(array_intersect_key(pg_image_draw_data_check(array('legendAt' => 'bottom', 'limit' => 3.0, 'size' => 'l') + $base)['data'],
		array('legendAt' => 1, 'limit' => 1, 'size' => 1))), 'the legend under the plot, a row limit and a type size are kept');
}

// Rows grouped, averaged, sorted and cut as the model's (the same cases as its test).
function test_image_draw_data_series()
{
	$f = image_draw_data_test_fixture();
	$s = function ($d, $rows = null) use ($f) {
		return pg_image_draw_data_series(pg_image_draw_data_check($d)['data'], $f['columns'], ($rows === null) ? $f['rows'] : $rows, $f['words']);
	};
	$many = array();
	$huge = array();

	for ($i = 0; $i < 30; $i++) {
		$many[] = array('cells' => array('11' => 'L' . $i, '12' => $i));
	}

	for ($i = 0; $i < 6000; $i++) {
		$huge[] = array('cells' => array('11' => 'x', '12' => 1));
	}

	$sum = $s(array('table' => 7, 'label' => '11', 'values' => array('12', '13'), 'group' => 'sum'));
	pg_assert_same(array('North', 'South', 'East', 'West'), $sum['labels'], 'in the order a label first comes');
	pg_assert_true(image_draw_data_test_same(array(1500, 940.5, 15250, 0), $sum['series'][0]['values']));
	pg_assert_true(image_draw_data_test_same(array(800, 300, 9000, 120), $sum['series'][1]['values']), 'a cell that is no number adds nothing');

	$count = $s(array('table' => 7, 'label' => '11', 'group' => 'count', 'sort' => 'value', 'dir' => 'desc'));
	pg_assert_same(array('North', 'South', 'East', 'West'), $count['labels'], 'equal counts keep their order');
	pg_assert_same(array(2, 2, 1, 1), $count['series'][0]['values']);

	$average = $s(array('table' => 7, 'label' => '11', 'values' => array('13'), 'group' => 'average', 'sort' => 'label'));
	pg_assert_same(array('East', 'North', 'South', 'West'), $average['labels']);
	pg_assert_true(image_draw_data_test_same(array(9000, 800, 300, 120), $average['series'][0]['values']), 'over the cells that are numbers');

	pg_assert_same(array('first', '', 'a, b', '', '', ''), $s(array('table' => 7, 'show' => 'table', 'label' => '11', 'values' => array('14')))['series'][0]['texts'], 'a plain table keeps the words');

	$donut = $s(array('table' => 7, 'kind' => 'donut', 'label' => '11', 'values' => array('12')), $many);
	pg_assert_same(array('L0', 'L1', 'L2', 'L3', 'L4', 'Other'), $donut['labels'], 'a donut keeps five slices and Other');
	pg_assert_same(25, $donut['cut']);
	pg_assert_same(6, $s(array('table' => 7, 'label' => '11', 'values' => array('12')), $many)['cut'], 'a chart keeps 24 labels');
	pg_assert_true(image_draw_data_test_same(array(5000), $s(array('table' => 7, 'label' => '11', 'values' => array('12'), 'group' => 'sum'), $huge)['series'][0]['values']), 'no more than 5000 rows are read');

	pg_assert_same(array(12.5, null, null, null, -3), array(pg_image_draw_data_number(' 12.5 '), pg_image_draw_data_number('1,5'), pg_image_draw_data_number(true),
		pg_image_draw_data_number(INF), pg_image_draw_data_number(-3)));
	pg_assert_same(array('900.5', '0.3', '1200', '1, 2'), array(pg_image_draw_data_text(900.5), pg_image_draw_data_text(0.3), pg_image_draw_data_text(1200.0), pg_image_draw_data_text(array(1, '', 2))));
}

// Numbers, axis steps and the guide's values are the chart operation's.
function test_image_draw_data_matches_the_chart_guide()
{
	foreach (array(0, 7, 12.5, 999, 1234, 9999, 10000, 12345, 15250, 250000, 1234567, -1500) as $n) {
		pg_assert_same(ws_canvas_chart_number($n, 'en'), pg_image_draw_data_format($n, 'en'), 'en ' . $n);
		pg_assert_same(ws_canvas_chart_number($n, 'tr'), pg_image_draw_data_format($n, 'tr'), 'tr ' . $n);
	}

	pg_assert_same(array('1,234.5', '1.234,56', '12,345', '0.1'), array(pg_image_draw_data_format(1234.5, 'en', true), pg_image_draw_data_format(1234.555, 'tr', true),
		pg_image_draw_data_format(12345, 'en', true), pg_image_draw_data_format(0.1, 'en', true)), 'a plain table writes every digit');

	foreach (array(0.5, 1, 7, 42, 99, 1500, 15250, 87654) as $n) {
		pg_assert_same((float) ws_canvas_chart_step($n, 5), (float) pg_image_draw_data_step($n, 5), 'step ' . $n);
	}

	$v = ws_canvas_guide_values();
	$g = pg_image_draw_data_guide();

	pg_assert_same($v['scale_base'], $g['base']);
	pg_assert_same(array($v['grid']['margin'], $v['grid']['gutter'], $v['grid']['axis_gutter']), array($g['grid']['margin'], $g['grid']['gutter'], $g['grid']['axis']));

	foreach ($g['type'] as $role => $type) {
		pg_assert_same(array($v['type'][$role]['size'], $v['type'][$role]['weight'], $v['type'][$role]['ink']), $type, 'type ' . $role);
	}

	foreach ($g['palettes'] as $theme => $palette) {
		foreach ($palette as $role => $colour) {
			pg_assert_same($v['palettes'][$theme][$role], $colour, $theme . ' ' . $role);
		}
	}

	$names = array('barMax' => 'bar_max', 'barBand' => 'bar_band', 'barRadius' => 'bar_radius', 'groupGap' => 'group_gap', 'stackGap' => 'stack_gap', 'line' => 'line', 'dot' => 'dot',
		'ring' => 'ring', 'areaMix' => 'area_mix', 'grid' => 'grid', 'baseline' => 'baseline', 'ticksMax' => 'ticks_max', 'donutOuter' => 'donut_outer', 'donutInner' => 'donut_inner');

	foreach ($names as $php => $guide) {
		pg_assert_same($v['marks'][$guide], $g['marks'][$php], 'mark ' . $guide);
	}

	$model = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js');
	pg_assert_contains('rows: 5000', $model, 'the model reads as many rows as the server');
	pg_assert_same(5000, pg_image_draw_data_max()['rows']);
}

// The plan draws a data layer only with a reader of its rows, in faces the server holds.
function test_image_draw_data_plan()
{
	$can = array('shapes' => true, 'text' => true, 'icons' => false, 'svg' => false, 'pictures' => array());
	$layer = array('type' => 'data', 'x' => 10, 'y' => 10, 'w' => 400, 'h' => 240, 'rot' => 0, 'data' => array('table' => 7, 'label' => '11', 'values' => array('12')));
	$doc = function ($l) {
		return array('artboard' => array('width' => 500, 'height' => 300, 'background' => '#ffffff'), 'layers' => array($l));
	};
	$asked = array();
	$reader = function ($table) use (&$asked) {
		$asked[] = $table;

		return array('state' => 'refused');
	};

	pg_assert_same('data', pg_image_draw_plan($doc($layer), $can)['error'], 'without a reader a data layer waits for a browser');
	pg_assert_same('data', pg_image_draw_plan($doc($layer), array('text' => false) + $can, null, 0.0, $reader)['error'], 'a host that draws no text leaves it');
	pg_assert_same('data', pg_image_draw_plan($doc($layer + array('shadow' => array('blur' => 4))), $can, null, 0.0, $reader)['error'], 'an unknown field leaves it');
	pg_assert_same('', pg_image_draw_plan($doc($layer + array('hidden' => true)), $can, null, 0.0, $reader)['error'], 'a hidden one is not read');
	pg_assert_same(array(), $asked);

	// Its labels are 600 and Roboto's bundled files have no 600: the face waits.
	$roboto = $layer;
	$roboto['data']['font'] = 'g:Roboto';
	pg_assert_same('font', pg_image_draw_plan($doc($roboto), $can, null, 0.0, $reader)['error'], 'a face the server has no file of');
	pg_assert_same(array(7), $asked, 'the rows are asked for its table');

	$two = $doc($roboto);
	$two['layers'][] = $roboto;
	$asked = array();
	pg_image_draw_plan($two, $can, null, 0.0, $reader);
	pg_assert_same(array(7), $asked, 'one table is read once a drawing');

	$item = pg_image_draw_data_item(array('kind' => 'data', 'x' => 0, 'y' => 0, 'w' => 400, 'h' => 240, 'rot' => 0, 'opacity' => 1), array('table' => 0), null);
	pg_assert_same(null, $item, 'the empty board\'s words need a face too');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw_data.php');", $js);
}

// The row read hands the layer columns by name and cells by id, 5000 rows at most.
function test_image_draw_data_rows_shape()
{
	$columns = array('11' => array('name' => 'Region', 'type' => 'text'), '12' => array('name' => 'Revenue', 'type' => 'number'));
	$rows = array(array('id' => 1, 'cells' => array('11' => 'North', '12' => 5)), array('id' => 2, 'cells' => null));
	$shape = ws_canvas_data_rows_shape($columns, $rows);

	pg_assert_same(array('11' => array('name' => 'Region'), '12' => array('name' => 'Revenue')), $shape['columns']);
	pg_assert_same(array(array('cells' => array('11' => 'North', '12' => 5)), array('cells' => array())), $shape['rows']);
	pg_assert_same(5000, count(ws_canvas_data_rows_shape($columns, array_fill(0, 5100, array('cells' => array())))['rows']));

	$f = image_draw_data_test_fixture();
	$drawn = pg_image_draw_data_layer_marks($f['cases'][0]['data'], $f['cases'][0]['w'], $f['cases'][0]['h'], ws_canvas_data_rows_shape($f['columns'], $f['rows']), $f['words']);
	pg_assert_true(image_draw_data_test_same($f['cases'][0]['marks'], $drawn['marks']), 'the read\'s shape is what the layer draws from');
}
