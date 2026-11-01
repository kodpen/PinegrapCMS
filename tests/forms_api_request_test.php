<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the request checks of the get_forms / get_form panel actions
 * (forms.php, includes/fn/forms.php): a limit or offset that is not a whole
 * number and a fields or filter value of the wrong type are refused with a
 * JSON error instead of reaching in_array() (a TypeError on PHP 8) or the
 * SQL LIMIT clause (an HTML error page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Well-formed requests pass.
function test_forms_api_request_valid()
{
	pg_assert_same('', pg_forms_request_problem(array()), 'empty');
	pg_assert_same('', pg_forms_request_problem(array('limit' => 10, 'offset' => '20')), 'whole numbers');
	pg_assert_same('', pg_forms_request_problem(array('limit' => '', 'offset' => 0)), 'not given');
	pg_assert_same('', pg_forms_request_problem(array('fields' => array('id', 'name'))), 'field list');
	pg_assert_same('', pg_forms_request_problem(array('filters' => array(array('field' => 'id', 'operator' => 'is equal to', 'value' => '1')))), 'filter');
	pg_assert_same('', pg_forms_request_problem(array('sort' => array(array('field' => 'id')))), 'sort list');
}

// A limit or offset that is not a positive whole number written in digits.
function test_forms_api_request_limit_and_offset()
{
	foreach (array('1.0', '1e1', '+5', '-1', '0x1A', ' 5', array(5), 2.5) as $bad) {
		pg_assert_true(pg_forms_request_problem(array('limit' => $bad)) !== '', 'limit ' . var_export($bad, true));
		pg_assert_true(pg_forms_request_problem(array('limit' => 5, 'offset' => $bad)) !== '', 'offset ' . var_export($bad, true));
	}
}

// fields must be a list of names.
function test_forms_api_request_fields()
{
	pg_assert_true(pg_forms_request_problem(array('fields' => 'id')) !== '', 'string');
	pg_assert_true(pg_forms_request_problem(array('fields' => array(array('id')))) !== '', 'nested');
}

// A filter or sort entry must be an object whose field is a name.
function test_forms_api_request_filter_and_sort_entries()
{
	pg_assert_true(pg_forms_request_problem(array('filters' => array('id'))) !== '', 'filter string');
	pg_assert_true(pg_forms_request_problem(array('filters' => array(array('field' => array('id'))))) !== '', 'filter field array');
	pg_assert_true(pg_forms_request_problem(array('sort' => array('id'))) !== '', 'sort string');
	pg_assert_true(pg_forms_request_problem(array('sort' => array(array('field' => array('id'))))) !== '', 'sort field array');
	pg_assert_true(pg_forms_request_problem(array('sort' => 'id', 'sort_order' => array('asc'))) !== '', 'sort order array');
}

// get_forms() runs the check before it reads the database.
function test_forms_api_request_checked_by_get_forms()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/forms.php');
	pg_assert_true(strpos($source, 'pg_forms_request_problem($request)') !== false, 'get_forms calls the check');
}
