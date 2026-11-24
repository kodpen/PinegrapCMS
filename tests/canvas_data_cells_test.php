<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cells a data layer reads (ws_canvas_data_rows_shape(),
 * includes/workspace/canvas_tables.php): a money cell as its figure, a
 * status as its choice's label, a person by name, as the table's screen
 * shows them, the rest as stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

// A money column stores minor units: a chart of 12.50 must not draw 1250.
function test_canvas_data_cells_money_as_figure()
{
	$columns = array(
		'1' => array('name' => 'Region', 'type' => 'text', 'options' => array()),
		'2' => array('name' => 'Revenue', 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'EUR')),
		'3' => array('name' => 'Units', 'type' => 'number', 'options' => array()),
	);
	$rows = array(array('cells' => array('1' => 'North', '2' => 1250, '3' => 4)), array('cells' => array('1' => 'South', '2' => 300000, '3' => '7')));
	$shape = ws_canvas_data_rows_shape($columns, $rows);

	pg_assert_same(12.5, $shape['rows'][0]['cells']['2']);
	pg_assert_same(3000.0, $shape['rows'][1]['cells']['2']);
	pg_assert_same('North', $shape['rows'][0]['cells']['1']);
	pg_assert_same(4, $shape['rows'][0]['cells']['3'], 'a number stays as stored');
	pg_assert_same('7', $shape['rows'][1]['cells']['3']);
}

// A status is drawn by its choice's label and a person by name, not by the
// key or the user id the row stores.
function test_canvas_data_cells_status_and_person_words()
{
	$columns = array(
		'4' => array('name' => 'Stage', 'type' => 'status', 'options' => array('choices' => array(array('key' => 'won', 'label' => 'Won', 'color' => '#00aa00')))),
		'5' => array('name' => 'Owner', 'type' => 'person', 'options' => array()),
		'6' => array('name' => 'Tags', 'type' => 'list', 'options' => array()),
	);
	$rows = array(array('cells' => array('4' => 'won', '5' => 9, '6' => array('a', 'b'))));
	$shape = ws_canvas_data_rows_shape($columns, $rows, array(9 => array('name' => 'Ada Lovelace')));

	pg_assert_same('Won', $shape['rows'][0]['cells']['4']);
	pg_assert_same('Ada Lovelace', $shape['rows'][0]['cells']['5']);
	pg_assert_same(array('a', 'b'), $shape['rows'][0]['cells']['6'], 'a list stays a list');
}
