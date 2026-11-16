<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection's rows field outside its editor: the API's read of
 * a record (codes on a list read, the rows on a single read, the field's
 * kind and child form), the check of a value written from outside the
 * editor (a foreign code and a code of another record's list refused, 422
 * on the API, the same check on a Workspace change), and Import Submitted
 * Forms leaving a rows column out and naming it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/forms.php');

// The lines of a function, to read what it calls and in which order.
function _collections_rows_api_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function _collections_rows_api_kinds()
{
	return array(
		5 => array('kind' => 'rows', 'reference_page_id' => 40),
		7 => array('kind' => 'reference', 'reference_page_id' => 41),
	);
}

function _collections_rows_api_values()
{
	return array(
		array('field_id' => 4, 'name' => 'title', 'label' => 'Title', 'type' => 'text box', 'value' => 'Bread', 'file_id' => null),
		array('field_id' => 5, 'name' => 'steps', 'label' => 'Steps', 'type' => 'text box', 'value' => 'a1, b2', 'file_id' => null),
		array('field_id' => 7, 'name' => 'author', 'label' => 'Author', 'type' => 'text box', 'value' => 'x9', 'file_id' => null),
	);
}

// (R13) A list read gives a rows field's codes in order and no rows.
function test_collections_rows_api_list_read_gives_the_codes()
{
	$values = api_form_values_rows(_collections_rows_api_values(), _collections_rows_api_kinds(), null);

	pg_assert_same(array('a1', 'b2'), $values[1]['codes'], 'the rows field');
	pg_assert_same(array(), $values[1]['rows'], 'no rows on a list read');
	pg_assert_same('a1, b2', $values[1]['value'], 'the stored value stays');
	pg_assert_same(null, $values[0]['codes'], 'a plain field');
	pg_assert_same(null, $values[2]['codes'], 'a reference is not a rows field');
	pg_assert_same(array(), $values[2]['rows']);
}

// (R13) A single read gives the rows: id, code and values, in order.
function test_collections_rows_api_single_read_gives_the_rows()
{
	$child_fields = array(
		array('id' => 71, 'name' => 'step', 'label' => 'Step', 'type' => 'text box'),
		array('id' => 73, 'name' => 'photo', 'label' => 'Photo', 'type' => 'file upload'),
		array('id' => 76, 'name' => 'tools', 'label' => 'Tools', 'type' => 'check box'),
	);
	$rows = array(
		array('id' => 902, 'reference_code' => 'b2', 'values' => array(76 => array('bowl', 'oven'), 71 => 'Bake', 73 => '')),
		array('id' => 901, 'reference_code' => 'a1', 'values' => array(71 => 'Mix')),
	);

	$second = api_form_row_present($rows[0], $child_fields);
	pg_assert_same(902, $second['id']);
	pg_assert_same('b2', $second['reference_code']);
	pg_assert_same(array(
		array('field_id' => 71, 'name' => 'step', 'label' => 'Step', 'type' => 'text box', 'value' => 'Bake'),
		array('field_id' => 76, 'name' => 'tools', 'label' => 'Tools', 'type' => 'check box', 'value' => 'bowl'),
		array('field_id' => 76, 'name' => 'tools', 'label' => 'Tools', 'type' => 'check box', 'value' => 'oven'),
	), $second['values'], 'in the child form\'s field order, one item a choice, no file');

	$presented = array(5 => array(api_form_row_present($rows[0], $child_fields), api_form_row_present($rows[1], $child_fields)));
	$values = api_form_values_rows(_collections_rows_api_values(), _collections_rows_api_kinds(), $presented);

	pg_assert_same(array('a1', 'b2'), $values[1]['codes']);
	pg_assert_same(array(902, 901), array_column($values[1]['rows'], 'id'), 'the rows as given, in order');
	pg_assert_same(array(), $values[0]['rows'], 'a plain field has none');

	pg_assert_true(strpos(_collections_rows_api_source('api_form_submission_get'), 'api_form_submission_values_out($row') !== false, 'a single read gives the rows');
	pg_assert_true(preg_match('/api_form_submission_values_out\(\$row, [^;]*, false\)/', _collections_rows_api_source('api_form_submissions')) === 1, 'a list read gives the codes only');
}

// (R13) GET /forms/{id}/fields names the kind and the child form.
function test_collections_rows_api_field_names_kind_and_child_form()
{
	$row = array('id' => 5, 'name' => 'steps', 'label' => 'Steps', 'type' => 'text box', 'required' => 0, 'sort_order' => 2,
		'multiple' => 0, 'office_use_only' => 0, 'contact_field' => '', 'default_value' => '', 'wysiwyg' => 0);

	$rows = api_form_field_present($row + array('kind' => 'rows', 'child_form_id' => 40));
	pg_assert_same('rows', $rows['kind']);
	pg_assert_same(40, $rows['child_form_id']);

	$reference = api_form_field_present($row + array('kind' => 'reference', 'child_form_id' => 41));
	pg_assert_same('reference', $reference['kind']);
	pg_assert_same(null, $reference['child_form_id'], 'only a rows field names a child form');

	$plain = api_form_field_present($row);
	pg_assert_same(null, $plain['kind']);
	pg_assert_same(null, $plain['child_form_id']);

	$schema = api_form_field_schema();
	pg_assert_same('string?', $schema['kind']);
	pg_assert_same('integer?', $schema['child_form_id']);
	pg_assert_true(strpos(_collections_rows_api_source('api_form_fields_present'), 'pg_coll_field_kinds($id)') !== false, 'the kinds are read');
}

// (R14) The lists a code is looked for in: every rows list of the child
// collection but the written record's own.
function test_collections_rows_api_held_sql()
{
	$sql = pg_coll_rows_held_sql(array('a1', 'b2'), 40, 7, 5);
	pg_assert_true(strpos($sql, "form_fields.collection_kind = 'rows'") !== false);
	pg_assert_true(strpos($sql, "form_fields.reference_page_id = '40'") !== false);
	pg_assert_true(strpos($sql, "NOT (form_data.form_id = '7' AND form_data.form_field_id = '5')") !== false, 'its own list is not another\'s');
	pg_assert_true(strpos($sql, "FIND_IN_SET('a1'") !== false && strpos($sql, "FIND_IN_SET('b2'") !== false, 'each code');
	pg_assert_same('', pg_coll_rows_held_sql(array(), 40, 7, 5), 'no code, no read');
}

// (R14) A code another list holds is named, in the order sent.
function test_collections_rows_api_taken_codes()
{
	pg_assert_same(array('b2', 'c3'), pg_coll_rows_taken(array('a1', 'b2', 'c3'), array('x9, b2', 'c3')));
	pg_assert_same(array(), pg_coll_rows_taken(array('a1'), array('a10, xa1')), 'a code is matched whole');
	pg_assert_same(array(), pg_coll_rows_taken(array('a1'), array()));
}

// (R14) A value that is not codes, or over the limit, is refused before
// anything is read; an empty value clears the field.
function test_collections_rows_api_foreign_value_refused()
{
	pg_assert_same('', pg_coll_rows_outside_refusal('', 40, 7, 5, 'steps'), 'empty');
	pg_assert_true(pg_coll_rows_outside_refusal('a1, b 2', 40, 7, 5, 'steps') !== '', 'not a code');
	pg_assert_true(pg_coll_rows_outside_refusal(array('a1'), 40, 7, 5, 'steps') !== '', 'a list');
	pg_assert_true(pg_coll_rows_outside_refusal('a1', 0, 7, 5, 'steps') !== '', 'a field whose child form is gone');
	pg_assert_true(pg_coll_rows_outside_refusal(implode(', ', array_map(function ($i) { return 'c' . $i; }, range(1, 101))), 40, 7, 5, 'steps') !== '', 'over the limit');
}

// (R14) The API's update and create answer 422 for a rows value it cannot
// keep, before the rehearsal stop and any write; a Workspace change is
// refused the same way.
function test_collections_rows_api_writes_check_rows()
{
	$refusal = _collections_rows_api_source('api_form_rows_refusal');
	pg_assert_true(strpos($refusal, 'pg_coll_is_rows_field(') !== false && strpos($refusal, 'pg_coll_rows_outside_refusal(') !== false, 'the shared check');

	foreach (array('api_form_submission_update' => '$id)', 'api_form_submission_create' => '$id, 0)') as $handler => $form_arg) {
		$source = _collections_rows_api_source($handler);
		$check = strpos($source, 'api_form_rows_refusal(');
		pg_assert_true($check !== false, $handler . ' checks rows');
		pg_assert_true(strpos($source, $form_arg, $check) !== false, $handler . ' names the record written');
		pg_assert_true(strpos($source, 'api_fail_validation($refusal', $check) !== false, $handler . ' answers 422');
		pg_assert_true($check < strpos($source, 'api_dry_run_stop('), $handler . ' checks before the rehearsal stop');
	}

	$ws = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	pg_assert_true(strpos($ws, "pg_coll_rows_outside_refusal(\$answer, \$rows_kinds[\$field_id]['reference_page_id'], (int) \$record['id']") !== false, 'a Workspace change');
}

// (R19) Import Submitted Forms leaves a rows column out and names it.
function test_collections_rows_api_import_skips_the_rows_cell()
{
	pg_assert_true(pg_coll_is_rows_field(5, _collections_rows_api_kinds()), 'a rows field');
	pg_assert_true(pg_coll_is_rows_field('5', _collections_rows_api_kinds()), 'an id read as text');
	pg_assert_same(false, pg_coll_is_rows_field(7, _collections_rows_api_kinds()), 'a reference');
	pg_assert_same(false, pg_coll_is_rows_field(4, _collections_rows_api_kinds()), 'a plain field');

	$import = file_get_contents(PG_FUNCTIONS_DIR . '/import_submitted_forms.php');
	$skip = strpos($import, 'pg_coll_is_rows_field($row[\'id\'], $rows_kinds)');
	pg_assert_true($skip !== false, 'the column is matched');
	pg_assert_true($skip < strpos($import, '$mapped_field_ids[$field_id] = true;'), 'and left out before it is mapped');
	pg_assert_true(strpos($import, 'These columns fill a rows field and were left out') !== false, 'the import names it');
}
