<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The list table driver's browser mode: a list printed whole
 * (pg_list_table_printed()), its sort kept in the session
 * (pg_list_table_printed_sort()), and in Node the browser's own sort
 * (PGListTable.compareSortValues(), PGListTable.sortRows()) and Ctrl+A over
 * the rows the search shows (Selection.selectIds()).
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
require_once(__DIR__ . '/list_table_js_test.php');

function test_list_table_printed_source()
{
	$printed = pg_list_table_printed(array(
		'list' => 'view_menus',
		'columns' => array(
			'name' => array('heading' => 'Name'),
			'effect' => array('heading' => 'Effect', 'sortable' => false),
			'open' => array('heading' => 'Open', 'kind' => 'actions'),
			'last_modified' => array('heading' => 'Last Modified', 'kind' => 'date'),
		),
		'levels' => array(array('sort' => 'last_modified', 'order' => 'desc'), array('sort' => 'effect'), array('sort' => 'gone')),
		'rows' => array(
			array('id' => 4, 'cells' => array('name' => pg_list_cell('Top'))),
			array('id' => 7, 'cells' => array('name' => pg_list_cell('Foot'))),
		),
	));

	$source = $printed['source'];
	$page = $printed['page'];

	pg_assert_same(true, $source['all'], 'a printed list is sorted and searched in the browser');
	pg_assert_same(array(true, false, false, true), array_values(array_map(function ($column) { return $column['sortable']; }, $source['columns'])),
		'every column sorts but one that says no and an actions column');
	pg_assert_same('', $source['columns']['name']['sort'], 'no SQL reaches a printed column');
	pg_assert_same(array(array('sort' => 'last_modified', 'order' => 'desc')), $source['levels'], 'only sortable levels are kept');
	pg_assert_same(2, $page['total']);
	pg_assert_same(null, $page['next'], 'nothing is left to load');
	pg_assert_same('4', (string) $page['rows'][0]['id']);

	$html = pg_list_table_html($source, $page, 'Menus');
	pg_assert_true(strpos($html, 'data-pg-list-mode="browser"') !== false, 'the browser mode is on the root');
	pg_assert_true(strpos($html, 'aria-sort="descending"') !== false, 'the leading sort is marked');
	pg_assert_true(strpos($html, 'data-pg-list-printed="1"') !== false, 'the root says its sort is kept by the printed sort action');
}

function test_list_table_printed_sort_kept()
{
	$_SESSION['software']['list_state'] = array();

	pg_assert_same(false, pg_list_table_printed_sort('not_a_list', 'name:asc'), 'a list that is not printed is refused');
	pg_assert_same(false, pg_list_table_printed_sort('view_menus', ''), 'an empty sort is refused');
	pg_assert_same(false, pg_list_table_printed_sort('view_menus', 'Bad Key:asc'), 'a key that is no column key is refused');

	pg_assert_same(true, pg_list_table_printed_sort('view_menus', 'name:desc,created:asc'));
	pg_assert_same(array('sort' => 'name', 'order' => 'desc', 'then' => array(array('sort' => 'created', 'order' => 'asc'))),
		$_SESSION['software']['list_state']['view_menus'], 'kept the way pg_list_state() reads it');

	pg_assert_same(true, pg_list_table_printed_sort('view_short_links', 'last_modified:desc'));
	pg_assert_same(array('sort' => 'last_modified', 'order' => 'desc'), $_SESSION['software']['list_state']['view_short_links']);

	$_SESSION['software']['list_state'] = array();
}

function test_list_table_js_browser_sort()
{
	$result = list_table_js_test_node(<<<'JS'
var c = new Intl.Collator('tr', { numeric: true, sensitivity: 'base' }), out = {};
out.numbers = [T.compareSortValues('9', '10', c), T.compareSortValues('-2', '1.5', c), T.compareSortValues('1.5', '1.25', c)];
out.texts = [T.compareSortValues('çay', 'dere', c), T.compareSortValues('ılık', 'iz', c), T.compareSortValues('Ab', 'ab', c)];
out.empty = T.compareSortValues('', 'a', c);
var rows = [
  { id: '1', values: { name: 'Zeytin', size: '10' } },
  { id: '2', values: { name: 'armut', size: '9' } },
  { id: '3', values: { name: 'Çilek', size: '10' } },
  { id: '4', values: { name: 'elma', size: '' } }
];
out.byName = T.sortRows(rows, [{ sort: 'name', order: 'asc' }], c);
out.bySizeDesc = T.sortRows(rows, [{ sort: 'size', order: 'desc' }, { sort: 'name', order: 'asc' }], c);
out.none = T.sortRows(rows, [], c);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(-1, -1, 1), array_map(function ($n) { return $n <=> 0; }, $result['numbers']), 'numbers compare as numbers');
	pg_assert_same(array(-1, -1, 0), array_map(function ($n) { return $n <=> 0; }, $result['texts']), 'texts compare in Turkish order, case aside');
	pg_assert_true($result['empty'] < 0, 'an empty value sorts first');
	pg_assert_same(array('2', '3', '4', '1'), $result['byName']);
	pg_assert_same(array('3', '1', '2', '4'), $result['bySizeDesc'], 'a tie falls to the next level');
	pg_assert_same(array('1', '2', '3', '4'), $result['none'], 'no level keeps the printed order');
}

function test_list_table_js_browser_select_shown()
{
	$result = list_table_js_test_node(<<<'JS'
var s = new T.Selection(), out = {};
s.only('5');
var before = s.replaced(3, function () { this.selectIds(['3', '1', 3]); });
out.payload = s.payload({ search: 'x' }); out.payload.ids.sort();
out.all = s.all;
out.count = s.count(10);
out.before = before;
s.toggle('1');
out.toggled = s.payload({}).ids;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ids' => array('1', '3')), $result['payload'], 'Ctrl+A in a printed list sends the shown rows by id');
	pg_assert_same(false, $result['all']);
	pg_assert_same(2, $result['count']);
	pg_assert_same(null, $result['before'], 'one row replaced offers no undo');
	pg_assert_same(array('3'), $result['toggled']);
}

function test_list_table_printed_cell_helpers()
{
	$check = pg_list_table_check_cell(true);
	pg_assert_same('1', $check['attributes']['data-pg-sort-value'], 'a check sorts by 1');
	pg_assert_contains('task_alt', $check['html']);
	pg_assert_contains('aria-label', $check['html'], 'the mark says Yes');

	$none = pg_list_table_check_cell(false);
	pg_assert_same('', $none['html']);
	pg_assert_same('0', $none['attributes']['data-pg-sort-value']);

	$when = pg_list_table_when_cell('1700000000', '');
	pg_assert_same('1700000000', $when['attributes']['data-pg-sort-value'], 'a date sorts by its time');
	pg_assert_true(strpos($when['html'], 'by ') === false, 'an unknown user leaves no dangling "by"');

	pg_assert_true(in_array('view_themes', pg_list_table_printed_lists(), true));
}
