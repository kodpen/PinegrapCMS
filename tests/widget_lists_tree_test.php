<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record tree sources (includes/fn/widgets_lists_tree.php):
 * the records that name a record in a reference field (a category's
 * sub-categories and articles) and the records above it (breadcrumbs). The
 * reads need a database; the rows are built by pure functions tested here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_lists_tree_page()
{
	return array('page_id' => 9, 'page_name' => 'help', 'cfg' => array(), 'pretty' => true);
}

function _widget_lists_tree_record($id, $code, $name, $title, $ref = '')
{
	return array('id' => $id, 'reference_code' => $code, 'address_name' => $name, 'title' => $title, 'ref' => $ref, 'page' => _widget_lists_tree_page());
}

function test_widget_lists_tree_descriptor()
{
	$sources = pg_sw_list_sources();
	foreach (array('form_record.children', 'form_record.crumbs') as $source) {
		pg_assert_true(isset($sources[$source]), $source . ' is a source');
		pg_assert_same('form_record', $sources[$source]['family'], $source . ' family');
		pg_assert_same('submitted_form_id', $sources[$source]['key'], $source . ' key');
		pg_assert_true($sources[$source]['arg_required'], $source . ' needs its field');
		$tokens = array();
		foreach ($sources[$source]['tokens'] as $pair) $tokens[] = $pair[0];
		// The row carries the tokens, then its own id: the key of a list
		// inside it.
		$row = pg_sw_list_tree_row(_widget_lists_tree_record(4, 'AB12', 'billing', 'Billing'), _widget_lists_tree_page());
		pg_assert_same(array_merge($tokens, array('submitted_form_id')), array_keys($row), $source . ' row keys');
		pg_assert_true(isset(pg_sw_kind_lists('form_item_view')[$source]), $source . ' in the record view');
		pg_assert_true(isset(pg_sw_kind_lists('form_list_view')[$source]), $source . ' in the list');
		pg_assert_false(isset(pg_sw_kind_lists('catalog_listing')[$source]), $source . ' not in the catalog');
	}
}

function test_widget_lists_tree_row()
{
	$page = _widget_lists_tree_page();
	$row  = pg_sw_list_tree_row(_widget_lists_tree_record(4, 'AB12', 'billing', '  Billing '), $page);
	pg_assert_same('Billing', $row['__record_title'], 'the title, trimmed');
	pg_assert_same('AB12', $row['__record_code']);
	pg_assert_same('4', $row['submitted_form_id']);
	pg_assert_true(substr($row['__record_url'], -13) === '/help/billing', 'a pretty address names the record: ' . $row['__record_url']);

	$page['pretty'] = false;
	$row = pg_sw_list_tree_row(_widget_lists_tree_record(5, 'CD34', '', ''), $page);
	pg_assert_same('CD34', $row['__record_title'], 'no title: the code');
	pg_assert_true(substr($row['__record_url'], -13) === '/help?r=CD34', 'else ?r=: ' . $row['__record_url']);
}

function test_widget_lists_tree_children_of()
{
	$parents = array(
		1 => array('reference_code' => 'ROOT1', 'address_name' => 'account'),
		2 => array('reference_code' => 'ROOT2', 'address_name' => ''),
	);
	$children = array(
		_widget_lists_tree_record(10, 'S10', 'login', 'Signing in', 'account'),
		_widget_lists_tree_record(11, 'S11', '', 'Invoices', 'ROOT2, ROOT1'),
		_widget_lists_tree_record(12, 'S12', '', 'Twice', 'ROOT2,ROOT2'),
		_widget_lists_tree_record(13, 'S13', '', 'Nobody', 'GONE'),
		_widget_lists_tree_record(2, 'ROOT2', '', 'Itself', 'ROOT2'),
	);
	$out = pg_sw_list_tree_children_of($parents, $children);
	$titles = function ($rows) {
		$t = array();
		foreach ($rows as $r) $t[] = $r['__record_title'];
		return $t;
	};
	pg_assert_same(array('Signing in', 'Invoices'), $titles($out[1]), 'by address name and by code, in the children\'s order');
	pg_assert_same(array('Invoices', 'Twice'), $titles($out[2]), 'several names; one named twice listed once; never its own child');
	pg_assert_same(array(1, 2), array_keys($out), 'a name nobody has lists nothing');
	pg_assert_same(array(), pg_sw_list_tree_children_of(array(), $children));
}

function test_widget_lists_tree_path_rows()
{
	$steps = array(
		_widget_lists_tree_record(20, 'C20', 'billing', 'Billing'),
		_widget_lists_tree_record(21, 'C21', 'account', 'Account'),
	);
	$rows = pg_sw_list_tree_path_rows($steps, 30);
	pg_assert_same(array('Account', 'Billing'), array($rows[0]['__record_title'], $rows[1]['__record_title']), 'root first');

	// A loop in the parents ends the path where a record comes back.
	$loop = array($steps[0], $steps[1], $steps[0], $steps[1]);
	pg_assert_same(2, count(pg_sw_list_tree_path_rows($loop, 30)), 'a loop ends the path');
	pg_assert_same(array(), pg_sw_list_tree_path_rows(array(_widget_lists_tree_record(30, 'X', '', 'Self')), 30), 'the record itself is never above itself');
	pg_assert_same(array(), pg_sw_list_tree_path_rows(array(), 30));
}

// Outside a database the provider still answers every key it was asked.
function test_widget_lists_tree_source_rows_without_keys()
{
	pg_assert_same(array(), pg_sw_list_tree_source_rows('form_record.children', array()));
	pg_assert_same(array(0 => array('parent' => array())), pg_sw_list_tree_source_rows('form_record.crumbs', array(0 => array('parent' => true))));
}
