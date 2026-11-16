<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list of a record's rows on its record page
 * (includes/fn/collection_rows.php, includes/fn/widgets.php): the dynamic
 * value "Rows of the shown record" listing the records a rows field holds,
 * in the field's order; an empty field listing none; a code that names
 * nothing left out; the editor offering the value the server reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_rows_list_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function _collections_rows_list_filter($operator = 'is equal to', $attribute = 'steps', $field = 'reference_code', $value = '')
{
	return array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => 'shown record rows', 'dynamic_value_attribute' => $attribute);
}

function test_collections_rows_list_sql_order()
{
	_collections_rows_list_db_stub();
	$sql = pg_coll_rows_list_sql(array('c3', 'a1', 'b2'));
	pg_assert_same(" AND (forms.reference_code IN ('c3', 'a1', 'b2'))", $sql['where'], 'the records the field holds');
	pg_assert_same("FIELD(forms.reference_code, 'c3', 'a1', 'b2') ASC", $sql['order'], 'in the field\'s order');
}

function test_collections_rows_list_sql_empty()
{
	_collections_rows_list_db_stub();
	$sql = pg_coll_rows_list_sql(array());
	pg_assert_same(' AND (TRUE = FALSE)', $sql['where'], 'an empty field lists no record');
	pg_assert_same('', $sql['order']);
	pg_assert_same(' AND (TRUE = FALSE)', pg_coll_rows_list_sql('a1')['where'], 'not a list: no record');
}

// A code that names no record matches nothing in the list's form; a code
// that is no code at all (a quote, a space inside) is left out before the
// SQL, and the others keep their order.
function test_collections_rows_list_sql_code_names_nothing()
{
	_collections_rows_list_db_stub();
	$sql = pg_coll_rows_list_sql(pg_coll_rows_codes("b2, x'y, gone1, a1, b2"));
	pg_assert_same(" AND (forms.reference_code IN ('b2', 'gone1', 'a1'))", $sql['where']);
	pg_assert_same("FIELD(forms.reference_code, 'b2', 'gone1', 'a1') ASC", $sql['order'], 'a repeat once, a bad code left out');
}

function test_collections_rows_list_filter_without_record()
{
	_collections_rows_list_db_stub();
	$none = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter()), array());
	pg_assert_same(' AND (TRUE = FALSE)', $none['where'], 'no record shown: no rows');
	pg_assert_same('', $none['order']);
	pg_assert_false(pg_sw_filter_sql_closed($none));

	$nofield = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter('is equal to', '')), array('r' => 'AB12CD'));
	pg_assert_same(' AND (TRUE = FALSE)', $nofield['where'], 'no rows field named: no rows');

	$record_view = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter()), null);
	pg_assert_same(1, $record_view['address'], 'no address: counted');
	pg_assert_true(pg_sw_filter_sql_closed($record_view, true), 'a record view closes on it');
}

function test_collections_rows_list_filter_misplaced()
{
	_collections_rows_list_db_stub();
	$op = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter('is not equal to')), array('r' => 'AB12CD'));
	pg_assert_same(1, $op['invalid'], 'only with "is equal to"');
	pg_assert_true(pg_sw_filter_sql_closed($op));

	$field = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter('is equal to', 'steps', 'submitter')), array('r' => 'AB12CD'));
	pg_assert_same(1, $field['invalid'], 'only on the reference code');

	$fixed = pg_sw_form_list_filter_sql(40, array(_collections_rows_list_filter('is equal to', 'steps', 'reference_code', 'R1')), array('r' => 'AB12CD'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'R1')", $fixed['where'], 'a fixed value wins');
	pg_assert_same('', $fixed['order']);
}

function test_collections_rows_list_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("'url parameter', 'shown record', 'shown record rows',", $widgets, 'a known dynamic value');
	pg_assert_contains('pg_coll_rows_shown_value($custom_form_page_id, $shown_code, $rows_field)', $widgets, 'the shown record\'s field of this form\'s rows');
	pg_assert_contains("\$order_by_sql = \$filter_sql['order'] . ', ' . \$order_by_sql;", $widgets, 'the list keeps the field\'s order');

	$rows = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/collection_rows.php');
	pg_assert_contains("form_fields.collection_kind = 'rows'", $rows, 'a rows field only');
	pg_assert_contains("form_fields.reference_page_id = '\$child_page_id'", $rows, 'whose rows are records of the list\'s form');
	pg_assert_contains('forms.complete = 1', $rows, 'of a complete record');
}

// The editor offers "Rows of the shown record" on a list's reference code
// only, and the server reads it.
function test_collections_rows_list_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true((bool) preg_match('/function _swFilterDynamicOptions\(type, withUrl, field\) \{(.*?)\n    \}/s', $js, $m), 'the dynamic values are found');
	pg_assert_contains("if (withUrl && field === 'reference_code') o.push(['shown record rows', _sdT('Rows of the shown record')]);", $m[1]);
	pg_assert_contains("_swFilterDynamicOptions(type, withUrl, field)", $js, 'the row passes its field');
	pg_assert_contains("if (dyn === 'shown record rows') attr = ", $js, 'the rows field is saved as the attribute');
}

// "Rows of this record" on a record page adds a list of the child form set
// up with the dynamic value on the reference code.
function test_collections_rows_list_designer_action()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true((bool) preg_match('/function _swRowsListConfig\(childPageId, fieldName\) \{(.*?)\n    \}/s', $js, $m), 'the list\'s config is found');
	pg_assert_contains("cfg.custom_form_page_id = childPageId;", $m[1]);
	pg_assert_contains("field: 'reference_code', operator: 'is equal to', value: '', dynamic_value: 'shown record rows', dynamic_value_attribute: fieldName", $m[1]);
	pg_assert_contains("_sdT('Rows of this record')", $js);
}
