<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Artifact Canvas's data layer (the DATA section of
 * pg_image_editor_model.js, run under Node): the description's check, the
 * rows grouped, sorted and cut, the numbers as the chart operation writes
 * them, the guide's values, the marks of every kind against the shared
 * fixture (tests/fixtures/canvas_data/layers.json), and the part's place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_chart.php');

// Runs $script with the model as M and the fixture as F and answers its
// JSON; null without Node.
function image_editor_data_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedata');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = require(' . json_encode(dirname(__FILE__) . '/fixtures/canvas_data/layers.json') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A description is filled with its defaults; each wrong part is refused by its code.
function test_image_editor_data_check()
{
	$r = image_editor_data_node(
		'var bad = { table: [{ table: 0, label: "a", values: ["b"] }, { table: 1.5, label: "a", values: ["b"] }, null],'
		. ' show: [{ table: 1, show: "picture", label: "a", values: ["b"] }],'
		. ' kind: [{ table: 1, kind: "pie", label: "a", values: ["b"] }],'
		. ' columns: [{ table: 1, values: ["b"] }, { table: 1, label: "a", values: [] }, { table: 1, label: "a", values: ["b c"] },'
		. '   { table: 1, label: "a", values: ["1", "2", "3", "4", "5", "6"] }, { table: 1, kind: "donut", label: "a", values: ["b", "c"] }],'
		. ' group: [{ table: 1, label: "a", values: ["b"], group: "max" }],'
		. ' sort: [{ table: 1, label: "a", values: ["b"], sort: "random" }, { table: 1, label: "a", values: ["b"], dir: "up" }],'
		. ' format: [{ table: 1, label: "a", values: ["b"], format: "de" }],'
		. ' title: [{ table: 1, label: "a", values: ["b"], title: "a\nb" }, { table: 1, label: "a", values: ["b"], title: new Array(122).join("x") }],'
		. ' colours: [{ table: 1, label: "a", values: ["b"], colours: ["red"] }, { table: 1, label: "a", values: ["b"], colours: ["#000000", "#000000", "#000000", "#000000", "#000000", "#000000"] }],'
		. ' theme: [{ table: 1, label: "a", values: ["b"], theme: "blue" }],'
		. ' font: [{ table: 1, label: "a", values: ["b"], font: "g:" }, { table: 1, label: "a", values: ["b"], font: "Comic" }] }, codes = {};'
		. 'Object.keys(bad).forEach(function (k) { codes[k] = bad[k].map(function (d) { return M.dataCheck(d).error; }); });'
		. 'console.log(JSON.stringify({ codes: codes, filled: M.dataCheck({ table: "4", label: 11, values: ["12", "12"], title: "  Sales ", colours: ["#AABBCC"] }),'
		. ' table: M.dataCheck({ table: 4, show: "table", kind: "pie", label: "11" }), count: M.dataCheck({ table: 4, label: "11", values: ["12"], group: "count" }).data }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach ($r['codes'] as $part => $codes) {
		foreach ($codes as $i => $code) {
			pg_assert_same('data_' . $part, $code, $part . ' ' . $i);
		}
	}

	pg_assert_same(array('table' => 4, 'show' => 'chart', 'kind' => 'bar', 'group' => 'none', 'values' => array('12'), 'label' => '11', 'sort' => 'none', 'dir' => 'asc',
		'format' => 'en', 'title' => 'Sales', 'colours' => array('#aabbcc'), 'theme' => 'light', 'font' => 'g:Inter', 'legend' => true), $r['filled']['data'], 'defaults, ids as strings, a value once');
	pg_assert_true($r['table']['ok'] && ($r['table']['data']['kind'] === 'table') && ($r['table']['data']['values'] === array()), 'a plain table needs no value column and takes no chart kind');
	pg_assert_same(array(), $r['count']['values'], 'count takes no value column');
}

// Rows grouped by their label with sum, count and average, sorted, and cut.
function test_image_editor_data_series()
{
	$r = image_editor_data_node(
		'function s(d, rows) { return M.dataSeries(M.dataCheck(d).data, F.columns, rows || F.rows, F.words); }'
		. 'var many = []; for (var i = 0; i < 30; i++) { many.push({ cells: { "11": "L" + i, "12": i } }); }'
		. 'var huge = []; for (i = 0; i < 6000; i++) { huge.push({ cells: { "11": "x", "12": 1 } }); }'
		. 'console.log(JSON.stringify({'
		. ' sum: s({ table: 7, label: "11", values: ["12", "13"], group: "sum" }),'
		. ' count: s({ table: 7, label: "11", group: "count", sort: "value", dir: "desc" }),'
		. ' average: s({ table: 7, label: "11", values: ["13"], group: "average", sort: "label" }),'
		. ' none: s({ table: 7, show: "table", label: "11", values: ["14"] }),'
		. ' donut: s({ table: 7, kind: "donut", label: "11", values: ["12"] }, many),'
		. ' bars: s({ table: 7, label: "11", values: ["12"] }, many).cut,'
		. ' bound: s({ table: 7, label: "11", values: ["12"], group: "sum" }, huge).series[0].values,'
		. ' numbers: [M.dataNumber(" 12.5 "), M.dataNumber("1,5"), M.dataNumber(true), M.dataNumber(Infinity), M.dataNumber(-3)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('Region', $r['sum']['labelName']);
	pg_assert_same(array('North', 'South', 'East', 'West'), $r['sum']['labels'], 'in the order a label first comes');
	pg_assert_same(array(1500, 940.5, 15250, 0), $r['sum']['series'][0]['values']);
	pg_assert_same(array(800, 300, 9000, 120), $r['sum']['series'][1]['values'], 'a cell that is no number adds nothing');
	pg_assert_same(array('Revenue', 'Cost'), array($r['sum']['series'][0]['name'], $r['sum']['series'][1]['name']));
	pg_assert_same(array('North', 'South', 'East', 'West'), $r['count']['labels'], 'equal counts keep their order');
	pg_assert_same(array(2, 2, 1, 1), $r['count']['series'][0]['values']);
	pg_assert_same('Count', $r['count']['series'][0]['name']);
	pg_assert_same(array('East', 'North', 'South', 'West'), $r['average']['labels']);
	pg_assert_same(array(9000, 800, 300, 120), $r['average']['series'][0]['values'], 'over the cells that are numbers');
	pg_assert_same(array('first', '', 'a, b', '', '', ''), $r['none']['series'][0]['texts'], 'a plain table keeps the words');
	pg_assert_same(array('L0', 'L1', 'L2', 'L3', 'L4', 'Other'), $r['donut']['labels'], 'a donut keeps five slices and Other');
	pg_assert_same(array(0, 1, 2, 3, 4, 425), $r['donut']['series'][0]['values']);
	pg_assert_same(25, $r['donut']['cut']);
	pg_assert_same(6, $r['bars'], 'a chart keeps 24 labels');
	pg_assert_same(array(5000), $r['bound'], 'no more than 5000 rows are read');
	pg_assert_same(array(12.5, null, null, null, -3), $r['numbers']);
}

// Numbers and axis steps as the chart operation writes them; the guide's values are the Workspace guide's.
function test_image_editor_data_matches_the_chart_guide()
{
	$numbers = array(0, 7, 12.5, 999, 1234, 9999, 10000, 12345, 15250, 250000, 1234567);
	$maxes = array(0.5, 1, 7, 42, 99, 1500, 15250, 87654);
	$r = image_editor_data_node('console.log(JSON.stringify({ en: ' . json_encode($numbers) . '.map(function (n) { return M.dataFormat(n, "en"); }),'
		. ' tr: ' . json_encode($numbers) . '.map(function (n) { return M.dataFormat(n, "tr"); }),'
		. ' steps: ' . json_encode($maxes) . '.map(function (n) { return M.dataStep(n, 5); }), guide: M.DATA_GUIDE }));');

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach ($numbers as $i => $n) {
		pg_assert_same(ws_canvas_chart_number($n, 'en'), $r['en'][$i], 'en ' . $n);
		pg_assert_same(ws_canvas_chart_number($n, 'tr'), $r['tr'][$i], 'tr ' . $n);
	}

	foreach ($maxes as $i => $n) {
		pg_assert_same((float) ws_canvas_chart_step($n, 5), (float) $r['steps'][$i], 'step ' . $n);
	}

	$v = ws_canvas_guide_values();
	$g = $r['guide'];

	pg_assert_same($v['scale_base'], $g['base']);
	pg_assert_same(array($v['grid']['margin'], $v['grid']['gutter'], $v['grid']['axis_gutter']), array($g['grid']['margin'], $g['grid']['gutter'], $g['grid']['axis']));

	foreach ($g['type'] as $role => $type) {
		pg_assert_same(array($v['type'][$role]['size'], $v['type'][$role]['weight'], $v['type'][$role]['ink']), $type, 'type ' . $role);
	}

	pg_assert_same(array($v['spacing']['subtitle_legend'], $v['spacing']['legend_plot']), array($g['spacing']['legend'], $g['spacing']['plot']));

	foreach ($g['palettes'] as $theme => $palette) {
		foreach ($palette as $role => $colour) {
			pg_assert_same($v['palettes'][$theme][$role], $colour, $theme . ' ' . $role);
		}
	}

	$names = array('barMax' => 'bar_max', 'barBand' => 'bar_band', 'barRadius' => 'bar_radius', 'groupGap' => 'group_gap', 'stackGap' => 'stack_gap', 'line' => 'line', 'dot' => 'dot',
		'ring' => 'ring', 'areaMix' => 'area_mix', 'grid' => 'grid', 'baseline' => 'baseline', 'ticksMax' => 'ticks_max', 'donutOuter' => 'donut_outer', 'donutInner' => 'donut_inner');

	foreach ($names as $js => $php) {
		pg_assert_same($v['marks'][$php], $g['marks'][$js], 'mark ' . $php);
	}
}

// Every case of the shared fixture draws its marks; the server's draw is held to the same list.
function test_image_editor_data_marks_match_the_fixture()
{
	$r = image_editor_data_node(
		'console.log(JSON.stringify(F.cases.map(function (c) { var d = M.dataCheck(c.data).data;'
		. ' var read = c.refused ? { state: "refused" } : M.dataSeries(d, F.columns, c.empty ? [] : F.rows, F.words);'
		. ' return { name: c.name, same: JSON.stringify(M.dataMarks(d, c.w, c.h, read, F.words)) === JSON.stringify(c.marks), unread: M.dataMarks(d, c.w, c.h, null, F.words) }; })));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	$fixture = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/canvas_data/layers.json'), true);
	$kinds = array();

	foreach ($r as $i => $case) {
		pg_assert_true($case['same'], $case['name'] . ' draws the fixture\'s marks');
		pg_assert_same('No rows to show.', end($case['unread'])['v'], $case['name'] . ' unread says so');
		$kinds[] = $fixture['cases'][$i]['data']['kind'] ?? 'table';
	}

	pg_assert_same(array(), array_values(array_diff(array('bar', 'stacked_bar', 'line', 'area', 'donut', 'table'), $kinds)), 'every kind is in the fixture');

	foreach ($fixture['cases'] as $case) {
		$texts = array_column(array_filter($case['marks'], function ($m) {
			return $m['t'] === 'text';
		}), 'v');
		$box = array_filter($case['marks'], function ($m) use ($case) {
			foreach ($m['pts'] ?? array() as $p) {
				if (($p[0] < -0.01) || ($p[1] < -0.01) || ($p[0] > $case['w'] + 0.01) || ($p[1] > $case['h'] + 0.01)) {
					return true;
				}
			}

			return false;
		});

		pg_assert_same(array(), array_values($box), $case['name'] . ' stays in its box');

		if (!empty($case['refused'])) {
			pg_assert_same(array('Hidden', 'You cannot read this table.'), $texts, 'a table the reader may not read gives an empty layer that says so');
		}
	}
}

// The part is bundled after the glyph part, with its labels; a data layer turns with the picture as a text does.
function test_image_editor_data_bundled()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_true(array_search('assets/js/pg_image_editor_data.js', $files, true) > array_search('assets/js/pg_image_editor_glyph.js', $files, true), 'after the glyph part');
	pg_assert_true(in_array('data', pg_image_editor_parts(), true), 'a part with its strings');
	pg_assert_same(array('layer_data', 'dataEmpty', 'dataRefused', 'dataMore', 'dataCount', 'dataOther'), array_keys(pg_image_editor_strings_data()));
	pg_assert_contains('%d', pg_image_editor_strings_data()['dataMore']);

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_data.js');

	pg_assert_contains('Editor.layerTypes.data = {', $js);
	pg_assert_contains("var UPRIGHT = ['text', 'sticker', 'image', 'glyph', 'data'];", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js'));
}
