<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection's rows field (includes/fn/collection_rows.php): the
 * spec of the child collection a rows field keeps its rows in and its
 * refusals, a record's codes in order, the check of one row, the writer's
 * order of writes, a code of another record refused, the limit, a refused
 * row writing nothing and a failure midway leaving no row behind, and the
 * rows control on a site form: nothing when it cannot draw, else the rows
 * editor in the site's look.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array('OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap') as $collections_rows_test_name => $collections_rows_test_value) {
	if (!defined($collections_rows_test_name)) {
		define($collections_rows_test_name, $collections_rows_test_value);
	}
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

// A rows control whose child collection cannot be read (no database here)
// draws nothing on a site form: the control, its label and its wrapper go.
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

function test_collections_rows_posted_list()
{
	$list = pg_coll_rows_posted('[{"code":"a1","values":{"71":"Mix"}},{"code":"","values":{"71":"Bake","75":"easy"}}]');
	pg_assert_same(2, count($list));
	pg_assert_same('a1', $list[0]['code']);
	pg_assert_same('Mix', $list[0]['values'][71], 'field ids come back as numbers');
	pg_assert_same(array(), pg_coll_rows_posted('[]'), 'every row removed');
	foreach (array('', 'not json', '{"a":1}', '"text"', null, array('x')) as $bad) {
		pg_assert_same(null, pg_coll_rows_posted($bad), 'nothing to write: ' . var_export($bad, true));
	}
}

function test_collections_rows_editor_config()
{
	$fields = array(
		_collections_rows_field_row(71, 'step', 'text box', array('required' => 1)),
		_collections_rows_field_row(75, 'level', 'pick list', array('options' => array('easy', 'hard'), 'option_labels' => array('easy' => 'Easy', 'hard' => 'Hard'))),
		_collections_rows_field_row(76, 'tags', 'check box', array('options' => array('a'), 'option_labels' => array('a' => 'A'))),
		_collections_rows_field_row(77, 'speaker', 'pick list', array('collection_kind' => 'reference', 'choices' => array('p1' => 'Ada'))),
		_collections_rows_field_row(73, 'photo', 'file upload'),
	);
	$spec = pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child($fields));
	$rows = array(
		array('id' => 501, 'reference_code' => 'a1', 'values' => array(71 => 'Mix', 76 => 'a')),
		array('id' => 0, 'reference_code' => '', 'values' => array(71 => 'New')),
	);
	$config = pg_coll_rows_editor_config($spec, $rows, array(array('row' => 1, 'field' => 71, 'message' => 'Step is required.')));

	pg_assert_same(array(71, 75, 76, 77), array_map(function ($c) { return $c['id']; }, $config['columns']));
	pg_assert_same('text', $config['columns'][0]['input']);
	pg_assert_same(true, $config['columns'][0]['required']);
	pg_assert_same(array(array('value' => 'easy', 'label' => 'Easy'), array('value' => 'hard', 'label' => 'Hard')), $config['columns'][1]['options']);
	pg_assert_same('checks', $config['columns'][2]['input']);
	pg_assert_same(array(array('value' => 'p1', 'label' => 'Ada')), $config['columns'][3]['options'], 'a reference lists its records');
	pg_assert_same(array('Photo'), $config['others'], 'a field the table does not take is named');

	pg_assert_same('a1', $config['rows'][0]['code']);
	pg_assert_same(array('a'), $config['rows'][0]['values'][76], 'a check box group as a list');
	pg_assert_contains('edit_submitted_form.php?id=501', $config['rows'][0]['open'], 'an existing row opens on its own screen');
	pg_assert_same('', $config['rows'][1]['open'], 'a new row has no screen yet');
	pg_assert_same(100, $config['max']);
	pg_assert_same(array(array('row' => 1, 'field' => 71, 'message' => 'Step is required.')), $config['errors']);

	$plain = pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child(array(_collections_rows_field_row(71, 'step', 'text box'))));
	$config = pg_coll_rows_editor_config($plain, $rows, array());
	pg_assert_same('', $config['rows'][0]['open'], 'no Open the row when the table takes every field');
}

// The list travels as one field: no input per cell (max_input_vars).
function test_collections_rows_editor_markup_posts_one_field()
{
	$config = pg_coll_rows_editor_config(_collections_rows_spec(), array(), array());
	$html = pg_coll_rows_editor_markup(array('id' => 901, 'label' => 'Steps <b>'), $config, '[]');

	pg_assert_same(1, preg_match_all('/\sname="/', $html), 'one named field');
	pg_assert_contains('name="rows_901"', $html);
	pg_assert_contains('data-pg-rows', $html);
	pg_assert_contains('Steps &lt;b&gt;', $html, 'the label is escaped');
	pg_assert_contains('assets/js/pg_collection_rows.js', $html);
	pg_assert_contains('assets/lib/sortablejs/Sortable.min.js', $html);
	pg_assert_true(strpos($html, '<script>') === false || strpos($html, '"columns"') === false || strpos($html, '</script') !== false, 'config is not left open');

	$refused = pg_coll_rows_editor_markup(array('id' => 902, 'label' => 'Steps'), pg_coll_rows_spec(_collections_rows_field(0), null), '');
	pg_assert_contains(pg_coll_rows_spec_message('no_form'), $refused, 'a field that cannot draw says why');
	pg_assert_same(0, preg_match_all('/\sname="/', $refused), 'and posts nothing');
}

function test_collections_rows_parent_row()
{
	pg_assert_same('', pg_coll_rows_parent_row(null));
	$html = pg_coll_rows_parent_row(array('id' => 77, 'reference_code' => 'P1 <x>', 'label' => 'Recipe'));
	pg_assert_contains('edit_submitted_form.php?id=77', $html, 'the row names its parent with a link back');
	pg_assert_contains('P1 &lt;x&gt;', $html);
}

// A date or time cell is the panel's one date control, as the record's own
// field is: a text box with its hook, never the browser's picker.
function test_collections_rows_date_and_time_cells_use_the_panel_picker()
{
	$fields = array(
		_collections_rows_field_row(81, 'day', 'date'),
		_collections_rows_field_row(82, 'at', 'time'),
		_collections_rows_field_row(83, 'name', 'text box'),
	);
	$spec = pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child($fields));
	$config = pg_coll_rows_editor_config($spec, array(), array());
	pg_assert_same('data-pg-date', $config['columns'][0]['hook']);
	pg_assert_same('data-pg-time', $config['columns'][1]['hook']);
	pg_assert_same('', $config['columns'][2]['hook']);
	pg_assert_same(array('date', 'time'), pg_coll_rows_picker_kinds($spec), 'the screen prints the picker script for them');
	pg_assert_same(array(), pg_coll_rows_picker_kinds(pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child(array($fields[2])))));

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_collection_rows.js');
	preg_match_all('/\.type\s*=\s*([^;]+);/', $js, $m);
	foreach ($m[1] as $value) {
		pg_assert_true(in_array(trim($value), array("'text'", "'checkbox'", "'button'", "(column.input === 'date') ? 'date' : 'time'"), true), 'the script gives no input a type from a variable: ' . $value);
	}
	pg_assert_contains("look.nativeDates && (column.input === 'date' || column.input === 'time')", $js, 'the browser\'s date input only in the site look');
	pg_assert_contains("nativeDates: false", $js, 'which the panel look turns off');
	pg_assert_contains('column.hook', $js, 'the cell takes its column\'s hook');
	pg_assert_contains('pgFields.init(', $js, 'and is drawn by the panel\'s field script');
}


function _collections_rows_site_tree()
{
	$label = array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => 'Steps',
		'_attrs' => array(array('name' => 'for', 'value' => 'steps'))), 'children' => array());
	$input = array('type' => 'semantic', 'props' => array('tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'steps'), array('name' => 'id', 'value' => 'steps'))), 'children' => array());
	$wrap = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($label, $input));
	return array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array($wrap));
}

function _collections_rows_site_row($office_use_only = 0)
{
	return array(
		'id' => 901, 'page_id' => 30, 'name' => 'steps', 'label' => 'Steps', 'type' => 'text box', 'required' => 0,
		'multiple' => 0, 'maxlength' => 0, 'size' => 0, 'rows' => 0, 'cols' => 0, 'default_value' => '',
		'use_folder_name_for_default_value' => 0, 'contact_field' => '', 'office_use_only' => $office_use_only,
		'wysiwyg' => 0, 'information' => '', 'rss_field' => '', 'validation_regex' => '', 'validation_message' => '',
		'collection_kind' => 'rows', 'reference_page_id' => 40, 'rows_spec' => _collections_rows_spec(),
	);
}

// A rows field the visitor may fill is drawn as the rows editor in place of
// its control; its label goes, since the editor prints its own.
function test_collections_rows_site_form_draws_the_editor()
{
	foreach (array(false, true) as $staff) {
		$tree = _collections_rows_site_tree();
		pg_cf_apply_field_bindings($tree, array('steps' => _collections_rows_site_row()), null, 0, $staff, array());
		$wrap = $tree['children'][0];
		pg_assert_same(1, count($wrap['children']), 'the label and the control make way for the editor' . ($staff ? ' (staff)' : ''));
		pg_assert_same('custom_html', $wrap['children'][0]['props']['contentType']);
		$html = $wrap['children'][0]['props']['html'];
		pg_assert_contains('name="rows_901"', $html, 'the list travels in one field');
		pg_assert_contains('&quot;look&quot;:&quot;site&quot;', $html, 'the script is told the site look');
		pg_assert_contains('id="rows_label_901">Steps</span>', $html, 'the editor prints the label');
	}

	// An office-use-only rows field is not the visitor's: it goes as before.
	$tree = _collections_rows_site_tree();
	pg_cf_apply_field_bindings($tree, array('steps' => _collections_rows_site_row(1)), null, 0, false, array());
	pg_assert_same(0, count($tree['children']), 'office use only: nothing for a visitor');
	$tree = _collections_rows_site_tree();
	pg_cf_apply_field_bindings($tree, array('steps' => _collections_rows_site_row(1)), null, 0, true, array());
	pg_assert_contains('name="rows_901"', $tree['children'][0]['children'][0]['props']['html'], 'office use only: staff fill it');
}

function test_collections_rows_site_config()
{
	$fields = array(
		_collections_rows_field_row(71, 'step', 'text box'),
		_collections_rows_field_row(81, 'day', 'date'),
		_collections_rows_field_row(73, 'photo', 'file upload'),
	);
	$spec = pg_coll_rows_spec(_collections_rows_field(), _collections_rows_child($fields));
	$config = pg_coll_rows_site_config($spec, array(array('id' => 501, 'reference_code' => 'a1', 'values' => array(71 => 'Mix'))), array());

	pg_assert_same('site', $config['look']);
	pg_assert_same('', $config['columns'][1]['hook'], 'a date is the browser\'s input, not the panel picker');
	pg_assert_same('date', $config['columns'][1]['input']);
	pg_assert_same('', $config['rows'][0]['open'], 'no row opens on a record screen from a site');
	pg_assert_same(array(), $config['others'], 'nor are the fields only that screen fills named');
	pg_assert_same('Mix', $config['rows'][0]['values']['71']);
}

function test_collections_rows_site_markup()
{
	$html = pg_coll_rows_site_markup(array('id' => 901, 'label' => 'Steps <b>'), pg_coll_rows_site_config(_collections_rows_spec(), array(), array()), '[{"code":""}]');

	pg_assert_same(1, preg_match_all('/\sname="/', $html), 'one named field');
	pg_assert_contains('value="[{&quot;code&quot;:&quot;&quot;}]"', $html, 'the posted list comes back escaped');
	pg_assert_contains('Steps &lt;b&gt;', $html, 'the label is escaped');
	pg_assert_contains('class="mb-3 pg-coll-rows-field"', $html, 'a Bootstrap 5 field block');
	pg_assert_true(strpos($html, 'pg-icon-button') === false && strpos($html, 'fg-secondary') === false, 'no panel class on a site');
}

function test_collections_rows_site_refusal()
{
	pg_assert_contains('at least one row', pg_coll_rows_site_refusal(array(), true, true, 'Steps'), 'a required field submitted with no row');
	pg_assert_contains('at least one row', pg_coll_rows_site_refusal(null, true, true, 'Steps'), 'or with no list at all');
	pg_assert_same('', pg_coll_rows_site_refusal(array(), true, false, 'Steps'), 'Save for later asks no row');
	pg_assert_same('', pg_coll_rows_site_refusal(array(), false, true, 'Steps'), 'an optional field may stay empty');
	pg_assert_same('', pg_coll_rows_site_refusal(array(array('code' => '')), true, true, 'Steps'));
	pg_assert_true(pg_coll_rows_posted_max_bytes() >= 65536, 'a hundred short rows fit');
}
