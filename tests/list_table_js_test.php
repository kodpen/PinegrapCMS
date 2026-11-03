<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The list table driver's selection model and sort clicks
 * (assets/js/pg_list_table.js, window.PGListTable) run in Node: a plain
 * click, Ctrl and Shift clicks, Ctrl+A with exceptions, the undo of a
 * replaced selection, the payload pg_list_table_selection_parse() reads,
 * and the sort levels after a heading click. Skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

// Runs the script after the driver in Node and answers what it printed as
// JSON; null when Node cannot run.
function list_table_js_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pglt');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_list_table.js')
		. "\nvar T = window.PGListTable;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_list_table_js_clicks()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
var order = ['10', '9', '8', '7', '6'];
s.only(9); out.only = s.payload({});
s.toggle('7'); out.toggle = s.payload({}).ids.sort();
s.toggle(9); out.untoggle = s.payload({}).ids;
s.only('9'); s.range(order, '6', false); out.range = s.payload({}).ids.sort();
s.only('10'); s.range(order, '8', false); s.range(order, '6', true); out.additive = s.payload({}).ids.sort();
s.clear(); s.range(order, '7', false); out.noAnchor = s.payload({}).ids;
s.range(order, 'gone', false); out.unknown = s.payload({}).ids;
out.has = [s.has(7), s.has('8')];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ids' => array('9')), $result['only']);
	pg_assert_same(array('7', '9'), $result['toggle']);
	pg_assert_same(array('7'), $result['untoggle']);
	pg_assert_same(array('6', '7', '8', '9'), $result['range'], 'from the anchor down');
	pg_assert_same(array('10', '6', '7', '8', '9'), $result['additive'], 'Ctrl+Shift keeps the first range');
	pg_assert_same(array('7'), $result['noAnchor'], 'no anchor: the row alone');
	pg_assert_same(array('7'), $result['unknown'], 'a row not on screen changes nothing');
	pg_assert_same(array(true, false), $result['has']);
}

function test_list_table_js_select_all_with_exceptions()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
s.selectAll(1700000000.7);
s.toggle('4'); s.toggle('5'); s.toggle('5');
out.payload = s.payload({ search: 'bag', params: { filter: 'all' } });
out.count = s.count(250);
out.has = [s.has('4'), s.has('5'), s.has('999')];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$payload = array('condition' => array('search' => 'bag', 'params' => array('filter' => 'all')), 'except' => array('4'), 'as_of' => 1700000000);

	pg_assert_same($payload, $result['payload']);
	pg_assert_same(249, $result['count'], 'rows not loaded yet count too');
	pg_assert_same(array(false, true, true), $result['has']);

	// The server reads the same payload.
	$selection = pg_list_table_selection_parse($result['payload'], 1800000000);
	pg_assert_same(array(4), $selection['except']);
	pg_assert_same(1700000000, $selection['as_of']);
	pg_assert_same('bag', $selection['search']);
}

function test_list_table_js_undo_of_replaced_selection()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
s.only('1');
out.single = s.replaced(10, function () { this.only('2'); });
s.toggle('3');
var before = s.replaced(10, function () { this.clear(); });
out.before = before.ids.sort();
out.cleared = s.count(10);
s.restore(before);
out.restored = s.payload({}).ids.sort();
s.selectAll(5);
var all = s.replaced(10, function () { this.only('1'); });
s.restore(all);
out.all = s.payload({ search: '' });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(null, $result['single'], 'one row replaced: no undo');
	pg_assert_same(array('2', '3'), $result['before']);
	pg_assert_same(0, $result['cleared']);
	pg_assert_same(array('2', '3'), $result['restored']);
	pg_assert_same(array('condition' => array('search' => ''), 'except' => array(), 'as_of' => 5), $result['all'], 'a whole-list selection comes back');
}

function test_list_table_js_sort_clicks_and_texts()
{
	$result = list_table_js_test_node(<<<'JS'
var out = {};
var levels = T.sortParse('name:asc,price:desc');
out.parse = levels;
out.plainNew = T.sortAfterClick(levels, 'price', false);
out.plainTurn = T.sortAfterClick(levels, 'name', false);
out.shiftTurn = T.sortAfterClick(levels, 'name', true);
out.shiftDrop = T.sortAfterClick(levels, 'price', true);
out.shiftAdd = T.sortAfterClick(levels, 'folder', true);
out.shiftOnly = T.sortAfterClick([{ sort: 'name', order: 'desc' }], 'name', true);
out.value = T.sortValue(levels);
out.text = T.text('%n seçili', 3);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc'), array('sort' => 'price', 'order' => 'desc')), $result['parse']);
	pg_assert_same(array(array('sort' => 'price', 'order' => 'asc')), $result['plainNew']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'desc')), $result['plainTurn']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'desc'), array('sort' => 'price', 'order' => 'desc')), $result['shiftTurn']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), $result['shiftDrop'], 'a descending level leaves');
	pg_assert_same('folder', $result['shiftAdd'][2]['sort']);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), $result['shiftOnly'], 'the only level turns back');
	pg_assert_same('name:asc,price:desc', $result['value']);
	pg_assert_same('3 seçili', $result['text']);
}
