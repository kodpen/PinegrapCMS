<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's data layers on a canvas
 * (includes/workspace/canvas_data_write.php through ws_canvas_edit_apply()):
 * a data layer added by its description, changed by the data operation,
 * the table read only with the caller's rights and its columns, the
 * refusals, the layer as GET canvas reads it, the route's operation field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// A blank 1600 x 900 canvas with one svg layer l0.
function workspace_canvas_data_doc()
{
	return json_decode(json_encode(ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#eeeeee"/></svg>')['doc']), true);
}

// Table 7 is read with columns 1 (a label), 2 and 3 (numbers); any other
// table is not the caller's to read.
function workspace_canvas_data_tables()
{
	return function ($id) {
		return ($id === 7) ? array('1', '2', '3') : null;
	};
}

function workspace_canvas_data_apply($operations, $doc = null)
{
	return ws_canvas_edit_apply($doc ?: workspace_canvas_data_doc(), $operations, null, null, '', workspace_canvas_data_tables());
}

function test_workspace_canvas_data_add()
{
	$done = workspace_canvas_data_apply(array(
		array('op' => 'add', 'type' => 'data', 'name' => 'sales', 'data' => array('table' => 7, 'kind' => 'line', 'label' => '1', 'values' => array(2, '3'), 'title' => '  Sales  ')),
	));

	pg_assert_same('', $done['error'], 'added');

	$layer = $done['doc']['layers'][1];

	pg_assert_same(array('data', 'sales', 'l1'), array($layer['type'], $layer['name'], $layer['id']), 'a named data layer on top');
	pg_assert_same(array(320, 180, 960, 540, 0, 1), array($layer['x'], $layer['y'], $layer['w'], $layer['h'], $layer['rot'], $layer['opacity']), '60 % of the board wide at 16:9, in the middle');
	pg_assert_same(array(7, 'chart', 'line', '1', array('2', '3'), 'Sales', 'none', 'light'), array($layer['data']['table'], $layer['data']['show'], $layer['data']['kind'], $layer['data']['label'], $layer['data']['values'], $layer['data']['title'], $layer['data']['group'], $layer['data']['theme']), 'the description filled as the model fills it');
	pg_assert_true(!isset($layer['data']['rows']), 'no rows kept');
	pg_assert_same('pending', $done['doc']['picture'], 'the picture is drawn again');

	$boxed = workspace_canvas_data_apply(array(array('op' => 'add', 'type' => 'data', 'x' => 10, 'y' => 20, 'w' => 300, 'h' => 200, 'data' => array('table' => 7, 'show' => 'table', 'label' => '1'))));

	pg_assert_same(array(10, 20, 300, 200, 'table'), array($boxed['doc']['layers'][1]['x'], $boxed['doc']['layers'][1]['y'], $boxed['doc']['layers'][1]['w'], $boxed['doc']['layers'][1]['h'], $boxed['doc']['layers'][1]['data']['kind']), 'a box given, a plain table');
}

function test_workspace_canvas_data_refusals()
{
	$cases = array(
		'data'         => array('op' => 'add', 'type' => 'data'),
		'data_read'    => array('op' => 'add', 'type' => 'data', 'data' => array('table' => 8, 'label' => '1', 'values' => array('2'))),
		'data_columns' => array('op' => 'add', 'type' => 'data', 'data' => array('table' => 7, 'label' => '1', 'values' => array('9'))),
		'data_kind'    => array('op' => 'add', 'type' => 'data', 'data' => array('table' => 7, 'kind' => 'pie', 'label' => '1', 'values' => array('2'))),
		'data_table'   => array('op' => 'add', 'type' => 'data', 'data' => array('table' => 0, 'label' => '1')),
		'not_data'     => array('op' => 'data', 'layer' => 'l0', 'data' => array('kind' => 'bar')),
	);

	foreach ($cases as $code => $op) {
		$done = workspace_canvas_data_apply(array($op));

		pg_assert_same(array($code, 0), array($done['error'], $done['index']), 'refused: ' . $code);
		pg_assert_true(ws_canvas_edit_error_text($code) !== ws_canvas_from_svg_error_text($code), 'its own words: ' . $code);
	}

	$none = ws_canvas_edit_apply(workspace_canvas_data_doc(), array(array('op' => 'add', 'type' => 'data', 'data' => array('table' => 7, 'label' => '1', 'values' => array('2')))));

	pg_assert_same('data_read', $none['error'], 'no table resolver: no data layer');
}

function test_workspace_canvas_data_change()
{
	$added = workspace_canvas_data_apply(array(array('op' => 'add', 'type' => 'data', 'name' => 'sales', 'data' => array('table' => 7, 'label' => '1', 'values' => array('2', '3')))));
	$done = workspace_canvas_data_apply(array(
		array('op' => 'data', 'layer' => 'sales', 'data' => array('kind' => 'donut', 'values' => array('3'), 'group' => 'sum', 'colours' => array('#AA0000'))),
	), $added['doc']);
	$d = $done['doc']['layers'][1]['data'];

	pg_assert_same('', $done['error'], 'changed');
	pg_assert_same(array('donut', array('3'), 'sum', array('#aa0000'), '1', 7), array($d['kind'], $d['values'], $d['group'], $d['colours'], $d['label'], $d['table']), 'the fields sent over the layer\'s own');

	$two = workspace_canvas_data_apply(array(array('op' => 'data', 'layer' => 'sales', 'data' => array('kind' => 'donut'))), $added['doc']);

	pg_assert_same('data_columns', $two['error'], 'a donut of two values is refused, checked whole');

	$list = workspace_canvas_data_apply(array(array('op' => 'data', 'layer' => 'sales', 'data' => array('bar'))), $added['doc']);

	pg_assert_same('data', $list['error'], 'the change is an object of fields');

	$moved = workspace_canvas_data_apply(array(array('op' => 'data', 'layer' => 'sales', 'data' => array('table' => 8))), $added['doc']);

	pg_assert_same('data_read', $moved['error'], 'another table only when it is read');

	$added['doc']['layers'][1]['locked'] = true;
	$locked = workspace_canvas_data_apply(array(array('op' => 'data', 'layer' => 'sales', 'data' => array('title' => 'x'))), $added['doc']);

	pg_assert_same('locked', $locked['error'], 'a locked data layer is not changed');
}

function test_workspace_canvas_data_read_and_route()
{
	$added = workspace_canvas_data_apply(array(array('op' => 'add', 'type' => 'data', 'data' => array('table' => 7, 'label' => '1', 'values' => array('2')))));
	$read = ws_canvas_read($added['doc'], array());

	pg_assert_same(array(null, 7), array($read['layers'][0]['data'], $read['layers'][1]['data']['table']), 'GET canvas gives a data layer\'s description');

	$route = null;

	foreach (ws_canvas_api_routes() as $row) {
		if ($row['id'] === 'workspace.messages.canvas.edit') {
			$route = $row;
		}
	}

	pg_assert_true($route !== null, 'the edit route');

	foreach ($route['params'] as $param) {
		if ($param['name'] === 'operations') {
			pg_assert_same('object', $param['of']['data'] ?? null, 'an operation takes data');
		}
	}

	pg_assert_true(in_array('data', ws_canvas_edit_ops(), true), 'data is an operation');
	pg_assert_true(strpos($route['details'], 'type data') !== false, 'the route tells agents of data layers');
}
