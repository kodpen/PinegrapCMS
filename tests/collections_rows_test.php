<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection's rows field (includes/fn/collection_rows.php): the
 * spec of the child collection a rows field keeps its rows in and its
 * refusals, a record's codes in order, the check of one row, the writer's
 * order of writes, a code of another record refused, the limit, a refused
 * row writing nothing and a failure midway leaving no row behind, and the
 * rows control drawn as nothing on a site form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_rows_field_row($id, $name, $type, $extra = array())
{
	return array_merge(array(
		'id' => $id, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => 0,
		'wysiwyg' => 0, 'multiple' => 0, 'collection_kind' => '', 'reference_page_id' => 0, 'options' => array(),
	), $extra);
}

function _collections_rows_child($fields = null, $collection = true)
{
	if ($fields === null) {
		$fields = array(
			_collections_rows_field_row(71, 'step', 'text box', array('required' => 1)),
			_collections_rows_field_row(72, 'minutes', 'text box', array('collection_kind' => 'number')),
			_collections_rows_field_row(73, 'photo', 'file upload'),
			_collections_rows_field_row(74, 'notes', 'text area', array('wysiwyg' => 1)),
			_collections_rows_field_row(75, 'level', 'pick list', array('options' => array('easy', 'hard'))),
		);
	}
	return array('page_id' => 40, 'collection' => $collection, 'fields' => $fields);
}

function _collections_rows_field($ref = 40)
{
	return array('page_id' => 30, 'kind' => 'rows', 'reference_page_id' => $ref);
}

function _collections_rows_spec()
{
	return pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child());
}

// Fake writes: each call is logged; a create gives the next new code, and a
// call named in $fail answers false.
function _collections_rows_ops(&$log, $fail = array())
{
	$log = array();
	$made = 0;
	return array(
		'create' => function ($values) use (&$log, &$made, $fail) {
			$made++;
			$log[] = 'create ' . (isset($values[71]) ? $values[71] : '');
			if (in_array('create ' . $made, $fail, true)) return false;
			return 'new' . $made;
		},
		'update' => function ($code, $values) use (&$log, $fail) {
			$log[] = 'update ' . $code;
			return !in_array('update ' . $code, $fail, true);
		},
		'set' => function ($value) use (&$log, $fail) {
			$log[] = 'set ' . $value;
			return !in_array('set', $fail, true);
		},
		'delete' => function ($code) use (&$log, $fail) {
			$log[] = 'delete ' . $code;
			return !in_array('delete ' . $code, $fail, true);
		},
	);
}

function test_collections_rows_kind()
{
	pg_assert_same('rows', pg_coll_field_kind('rows', 'text box'));
	foreach (array('text area', 'pick list', 'check box', 'file upload') as $type) {
		pg_assert_same('', pg_coll_field_kind('rows', $type), 'rows sit in a text box, not a ' . $type);
	}
	pg_assert_same(40, pg_coll_field_reference('rows', 40), 'a rows field keeps its child collection');
	pg_assert_same(40, pg_coll_field_reference('rows', '40'));
	pg_assert_same(0, pg_coll_field_reference('rows', 'tab:x'));
}

function test_collections_rows_spec_takes_the_table_fields()
{
	$spec = _collections_rows_spec();
	pg_assert_true($spec['ok'], 'a collection with fields the table takes');
	pg_assert_same(40, $spec['page_id']);
	pg_assert_same(array(71, 72, 75), array_map(function ($f) { return (int) $f['id']; }, $spec['fields']));
	pg_assert_same(array(73, 74), array_map(function ($f) { return (int) $f['id']; }, $spec['others']), 'a file and rich text are opened on the row');

	$types = array('text box', 'text area', 'pick list', 'check box', 'date', 'time');
	$fields = array();
	foreach ($types as $i => $type) {
		$fields[] = _collections_rows_field_row(80 + $i, 'f' . $i, $type);
	}
	$fields[] = _collections_rows_field_row(90, 'answer', 'radio button');
	$fields[] = _collections_rows_field_row(91, 'sub', 'text box', array('collection_kind' => 'rows', 'reference_page_id' => 50));
	$spec = pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child($fields));
	pg_assert_same(6, count($spec['fields']), 'every type of the table');
	pg_assert_same(array(90, 91), array_map(function ($f) { return (int) $f['id']; }, $spec['others']), 'a radio button and rows inside rows are not in the table');
}

function test_collections_rows_spec_refusals()
{
	$cases = array(
		'no_form'        => array(_collections_rows_field(0), _collections_rows_child()),
		'own_form'       => array(_collections_rows_field(30), _collections_rows_child()),
		'gone'           => array(_collections_rows_field(), null),
		'not_collection' => array(_collections_rows_field(), _collections_rows_child(null, false)),
		'no_fields'      => array(_collections_rows_field(), _collections_rows_child(array(_collections_rows_field_row(73, 'photo', 'file upload')))),
	);
	foreach ($cases as $reason => $args) {
		$spec = pg_coll_rows_spec($args[0], $args[1]);
		pg_assert_same(false, $spec['ok'], $reason);
		pg_assert_same($reason, $spec['reason']);
		pg_assert_true(pg_coll_rows_spec_message($reason) !== '', 'a reason is said: ' . $reason);
	}

	$spec = pg_coll_rows_spec(array('page_id' => 30, 'kind' => 'reference', 'reference_page_id' => 40), _collections_rows_child());
	pg_assert_same('no_form', $spec['reason'], 'a field of another kind keeps no rows');

	$own = _collections_rows_child();
	$own['page_id'] = 30;
	$spec = pg_coll_rows_spec(_collections_rows_field(30), $own);
	pg_assert_same('own_form', $spec['reason'], 'never its own form');
}

function test_collections_rows_codes_in_order()
{
	pg_assert_same(array('c3', 'a1', 'b2'), pg_coll_rows_codes('c3, a1,b2'));
	pg_assert_same(array('a1', 'b2'), pg_coll_rows_codes(' a1 ,, a1, b2 , bad code, <x>'), 'blanks, repeats and non-codes left out');
	pg_assert_same(array(), pg_coll_rows_codes(''));
	pg_assert_same(array(), pg_coll_rows_codes(array('a1')), 'not text');
	pg_assert_same('a1, b2', pg_coll_rows_value(array('a1', 'b2')), 'joined as a reference text box joins its codes');
	pg_assert_same(100, pg_coll_rows_max());
}

function test_collections_rows_check_row()
{
	$spec = _collections_rows_spec();

	$check = pg_coll_rows_check_row($spec['fields'], array(71 => ' Mix ', 72 => '1,5', 75 => 'easy'), true, true);
	pg_assert_same(array(), $check['errors']);
	pg_assert_same('1.5', $check['values'][72], 'a number as its field stores it');
	pg_assert_same(' Mix ', $check['values'][71], 'text as typed');

	$check = pg_coll_rows_check_row($spec['fields'], array(71 => '', 72 => 'many', 75 => 'medium'), true, true);
	pg_assert_same(array(71, 72, 75), array_keys($check['errors']), 'required, number and choice refused');

	$check = pg_coll_rows_check_row($spec['fields'], array(72 => '2'), false, true);
	pg_assert_same(array(), $check['errors'], 'a record saved for later asks no row field to be filled');

	$check = pg_coll_rows_check_row($spec['fields'], array(72 => '2'), true, false);
	pg_assert_same(array(), $check['errors'], 'a field an existing row was not sent keeps its value');
	pg_assert_same(array(72), array_keys($check['values']), 'only what was sent is written');

	$check = pg_coll_rows_check_row($spec['fields'], array(71 => array('x'), 99 => 'other form'), true, true);
	pg_assert_same(array(71), array_keys($check['errors']), 'a list in a text field is refused');
	pg_assert_true(!isset($check['values'][99]), 'a field the table does not take is never written');

	$fields = array(
		_collections_rows_field_row(81, 'day', 'date'),
		_collections_rows_field_row(82, 'at', 'time'),
		_collections_rows_field_row(83, 'tags', 'check box', array('options' => array('a', 'b'))),
	);
	$check = pg_coll_rows_check_row($fields, array(81 => '2026-02-30', 82 => '25:00', 83 => array('a', 'z')), true, true);
	pg_assert_same(array(81, 82, 83), array_keys($check['errors']));
	$check = pg_coll_rows_check_row($fields, array(81 => '2026-02-28', 82 => '14:30', 83 => array('a', 'b')), true, true);
	pg_assert_same(array(), $check['errors']);
}

function test_collections_rows_writer_order_of_writes()
{
	$spec = _collections_rows_spec();
	$posted = array(
		array('code' => 'b2', 'values' => array(71 => 'Second')),
		array('code' => '', 'values' => array(71 => 'New one')),
		array('code' => 'a1', 'values' => array(71 => 'First')),
		array('code' => '', 'values' => array(71 => 'New two')),
	);
	$result = pg_coll_rows_write($spec, 'a1, b2, c3', $posted, true, _collections_rows_ops($log));

	pg_assert_true($result['ok']);
	pg_assert_same(array(
		'create New one', 'create New two',
		'update b2', 'update a1',
		'set b2, new1, a1, new2',
		'delete c3',
	), $log, 'created, changed, the parent set, then the removed deleted');
	pg_assert_same('b2, new1, a1, new2', $result['value']);
	pg_assert_same(array('new1', 'new2'), $result['created']);
	pg_assert_same(array('c3'), $result['deleted']);
}

function test_collections_rows_writer_empty_list()
{
	$result = pg_coll_rows_write(_collections_rows_spec(), 'a1', array(), true, _collections_rows_ops($log));
	pg_assert_true($result['ok']);
	pg_assert_same(array('set ', 'delete a1'), $log, 'every row removed');
}

function test_collections_rows_writer_refuses_a_foreign_code()
{
	$posted = array(
		array('code' => 'a1', 'values' => array(71 => 'Mine')),
		array('code' => 'zz9', 'values' => array(71 => 'Another record\'s')),
		array('code' => '', 'values' => array(71 => 'New')),
	);
	$result = pg_coll_rows_write(_collections_rows_spec(), 'a1', $posted, true, _collections_rows_ops($log));

	pg_assert_same(false, $result['ok']);
	pg_assert_same(array(), $log, 'nothing written');
	pg_assert_same(1, $result['errors'][0]['row'], 'the line is named');

	$posted = array(array('code' => 'a1', 'values' => array()), array('code' => 'a1', 'values' => array()));
	$result = pg_coll_rows_write(_collections_rows_spec(), 'a1', $posted, true, _collections_rows_ops($log));
	pg_assert_same(false, $result['ok'], 'one row listed twice');
	pg_assert_same(array(), $log);
}

function test_collections_rows_writer_limit()
{
	$posted = array();
	for ($i = 0; $i < 101; $i++) {
		$posted[] = array('code' => '', 'values' => array(71 => 'Row ' . $i));
	}
	$result = pg_coll_rows_write(_collections_rows_spec(), '', $posted, true, _collections_rows_ops($log));
	pg_assert_same(false, $result['ok'], 'over 100 rows');
	pg_assert_same(array(), $log);

	array_pop($posted);
	$result = pg_coll_rows_write(_collections_rows_spec(), '', $posted, true, _collections_rows_ops($log));
	pg_assert_true($result['ok'], '100 rows are taken');
	pg_assert_same(101, count($log), '100 created and the parent set');
}

function test_collections_rows_writer_refused_row_writes_nothing()
{
	$posted = array(
		array('code' => '', 'values' => array(71 => 'Good')),
		array('code' => 'a1', 'values' => array(71 => 'Fine', 72 => 'ten')),
	);
	$result = pg_coll_rows_write(_collections_rows_spec(), 'a1', $posted, true, _collections_rows_ops($log));

	pg_assert_same(false, $result['ok']);
	pg_assert_same(array(), $log, 'one refused row refuses the whole save');
	pg_assert_same(array(array('row' => 1, 'field' => 72)), array_map(function ($e) {
		return array('row' => $e['row'], 'field' => $e['field']);
	}, $result['errors']), 'its line and field are marked');

	foreach (array('not a list', array('x' => 'y'), array(array('values' => 'text'))) as $bad) {
		$result = pg_coll_rows_write(_collections_rows_spec(), 'a1', $bad, true, _collections_rows_ops($log));
		pg_assert_same(false, $result['ok'], var_export($bad, true));
		pg_assert_same(array(), $log);
	}

	$spec = pg_coll_rows_spec(_collections_rows_field(), null);
	$result = pg_coll_rows_write($spec, 'a1', array(), true, _collections_rows_ops($log));
	pg_assert_same(false, $result['ok'], 'a field whose collection is gone writes nothing');
	pg_assert_same(array(), $log);
}

function test_collections_rows_writer_failure_midway_leaves_no_row()
{
	$spec = _collections_rows_spec();
	$posted = array(
		array('code' => '', 'values' => array(71 => 'One')),
		array('code' => 'a1', 'values' => array(71 => 'Kept')),
		array('code' => '', 'values' => array(71 => 'Two')),
	);

	$result = pg_coll_rows_write($spec, 'a1, b2', $posted, true, _collections_rows_ops($log, array('create 2')));
	pg_assert_same(false, $result['ok']);
	pg_assert_same(array('create One', 'create Two', 'delete new1'), $log, 'a create failing deletes the rows made before it');

	$result = pg_coll_rows_write($spec, 'a1, b2', $posted, true, _collections_rows_ops($log, array('update a1')));
	pg_assert_same(false, $result['ok']);
	pg_assert_same(array('create One', 'create Two', 'update a1', 'delete new1', 'delete new2'), $log, 'the parent never lists them');

	$result = pg_coll_rows_write($spec, 'a1, b2', $posted, true, _collections_rows_ops($log, array('set')));
	pg_assert_same(false, $result['ok']);
	pg_assert_same(array('create One', 'create Two', 'update a1', 'set new1, a1, new2', 'delete new1', 'delete new2'), $log,
		'the parent not set: the new rows go and the removed one stays listed');

	$result = pg_coll_rows_write($spec, 'a1, b2', $posted, true, _collections_rows_ops($log, array('delete b2')));
	pg_assert_true($result['ok'], 'the parent is set');
	pg_assert_same(array('b2'), $result['left'], 'a removed row that could not be deleted is named');
}

function test_collections_rows_control_draws_nothing_on_a_site_form()
{
	$label = array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => 'Steps',
		'_attrs' => array(array('name' => 'for', 'value' => 'steps'))), 'children' => array());
	$input = array('type' => 'semantic', 'props' => array('tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'steps'), array('name' => 'id', 'value' => 'steps'))), 'children' => array());
	$title = array('type' => 'semantic', 'props' => array('tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'title'))), 'children' => array());
	$wrap = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($label, $input));
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array($wrap, $title));

	$row = function ($id, $name, $kind) {
		return array(
			'id' => $id, 'name' => $name, 'label' => ucfirst($name), 'type' => 'text box', 'required' => 0,
			'multiple' => 0, 'maxlength' => 0, 'size' => 0, 'rows' => 0, 'cols' => 0, 'default_value' => '',
			'use_folder_name_for_default_value' => 0, 'contact_field' => '', 'office_use_only' => 0,
			'wysiwyg' => 0, 'information' => '', 'rss_field' => '', 'validation_regex' => '', 'validation_message' => '',
			'collection_kind' => $kind, 'reference_page_id' => ($kind === 'rows' ? 40 : 0),
		);
	};
	$map = array('steps' => $row(901, 'steps', 'rows'), 'title' => $row(902, 'title', ''));

	foreach (array(false, true) as $staff) {
		$copy = $tree;
		pg_cf_apply_field_bindings($copy, $map, null, 0, $staff, array());
		pg_assert_same(1, count($copy['children']), 'the control, its label and the wrapper left empty go' . ($staff ? ' (staff too)' : ''));
		pg_assert_same('902', $copy['children'][0]['props']['_attrs'][1]['value'], 'the other field stays');
	}
}
