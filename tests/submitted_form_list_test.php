<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the submitted forms list (includes/fn/submitted_form_list.php):
 * the session defaults, the filter's SQL and bound values for a manager
 * (no folder rule, so no database), the search columns and a row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

function submitted_form_list_test_state($state)
{
	$_SESSION['software']['forms']['view_submitted_forms'] = $state;
	unset($_SESSION['software']['list_state']['view_submitted_forms']);
}

function test_submitted_form_list_defaults_fill_what_is_missing()
{
	submitted_form_list_test_state(array('start_year' => 'x', 'stop_day' => '7'));
	pg_submitted_form_list_defaults(array(array('id' => '5', 'name' => 'A')));
	$state = $_SESSION['software']['forms']['view_submitted_forms'];

	pg_assert_same('[All]', $state['custom_form']);
	pg_assert_false($state['advanced_filters']);
	pg_assert_same('', $state['query']);
	pg_assert_same('last modified', $state['date_type']);
	pg_assert_same(date('Y', time() - 2678400), $state['start_year'], 'a date part that is not a number');
	pg_assert_same('7', $state['stop_day'], 'a good date part stays');
	pg_assert_false(isset($state['custom_forms']), 'no checked forms while the advanced filters are off');
}

function test_submitted_form_list_defaults_check_forms_for_the_advanced_filters()
{
	$forms = array(array('id' => '5', 'name' => 'A'), array('id' => '9', 'name' => 'B'));

	submitted_form_list_test_state(array('advanced_filters' => true, 'custom_form' => '[All]'));
	pg_submitted_form_list_defaults($forms);
	pg_assert_same(array('5', '9'), $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'], 'every form');

	submitted_form_list_test_state(array('advanced_filters' => true, 'custom_form' => '9'));
	pg_submitted_form_list_defaults($forms);
	pg_assert_same(array('9'), $_SESSION['software']['forms']['view_submitted_forms']['custom_forms'], 'the picked form');
}

function test_submitted_form_list_filter_binds_every_value()
{
	submitted_form_list_test_state(array());
	pg_submitted_form_list_defaults(array());
	$_SESSION['software']['forms']['view_submitted_forms'] = array_merge($_SESSION['software']['forms']['view_submitted_forms'], array(
		'start_month' => '1', 'start_day' => '2', 'start_year' => '2026',
		'stop_month' => '3', 'stop_day' => '4', 'stop_year' => '2026',
		'custom_form' => "7' OR '1",
		'query' => 'ABC%',
	));

	$filter = pg_submitted_form_list_filter(array('role' => 0, 'id' => 1));

	pg_assert_contains('(forms.last_modified_timestamp >= ?) AND (forms.last_modified_timestamp <= ?)', $filter['where']);
	pg_assert_contains('(forms.page_id = ?)', $filter['where']);
	pg_assert_contains('LIKE ?)', $filter['where']);
	pg_assert_same(array(mktime(0, 0, 0, 1, 2, 2026), mktime(23, 59, 59, 3, 4, 2026), "7' OR '1", '%abc\\%%'), $filter['params']);
	pg_assert_same(substr_count($filter['from'] . $filter['where'], '?'), count($filter['params']), 'one value per placeholder');
	pg_assert_false(strpos($filter['where'], "7'") !== false, 'no value in the SQL text');
	pg_assert_false(strpos($filter['where'], 'page.page_folder') !== false, 'a manager sees every folder');
	pg_assert_same('forms.last_modified_timestamp desc, forms.id asc', $filter['order_by']);
	pg_assert_same('last_modified_timestamp', $filter['date_type_column']);
}

function test_submitted_form_list_filter_all_forms_and_submitted_date()
{
	submitted_form_list_test_state(array('date_type' => 'submitted'));
	pg_submitted_form_list_defaults(array());

	$filter = pg_submitted_form_list_filter(array('role' => 2, 'id' => 1));

	pg_assert_same('submitted_timestamp', $filter['date_type_column']);
	pg_assert_same('WHERE (forms.submitted_timestamp >= ?) AND (forms.submitted_timestamp <= ?)', $filter['where'], '[All] adds no form condition');
	pg_assert_same(2, count($filter['params']));
}

function test_submitted_form_list_filter_advanced_without_forms_finds_nothing()
{
	submitted_form_list_test_state(array('advanced_filters' => true, 'custom_forms' => array(), 'status' => 'complete', 'reference_code' => 'R1'));
	pg_submitted_form_list_defaults(array());

	$filter = pg_submitted_form_list_filter(array('role' => 0, 'id' => 1));

	pg_assert_contains('(0 = 1)', $filter['where']);
	pg_assert_contains("(forms.complete = '1')", $filter['where']);
	pg_assert_contains('(forms.reference_code LIKE ?)', $filter['where']);
	pg_assert_same('%R1%', end($filter['params']));
	pg_assert_same(substr_count($filter['from'] . $filter['where'], '?'), count($filter['params']));
}

function test_submitted_form_list_contact_sort_is_two_levels()
{
	submitted_form_list_test_state(array());
	pg_submitted_form_list_defaults(array());
	$_SESSION['software']['list_state']['view_submitted_forms'] = array('sort' => 'contact', 'order' => 'asc');

	$filter = pg_submitted_form_list_filter(array('role' => 0, 'id' => 1));

	pg_assert_same('contacts.last_name asc, contacts.first_name asc, forms.id asc', $filter['order_by']);
}

function test_submitted_form_list_search_columns()
{
	$search = pg_submitted_form_list_search();
	$where = pg_list_search_where(array(lang('Complete')), $search['columns'], $search['labels']);

	pg_assert_contains('forms.reference_code LIKE ?', $where['sql']);
	pg_assert_contains('forms.complete IN (', $where['sql'], 'the status through its label');
	pg_assert_same(substr_count($where['sql'], '?'), count($where['params']));
}

function test_submitted_form_list_row()
{
	// get_relative_time() reads the site's date order.
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}

	$row = pg_submitted_form_list_row(array(
		'id' => '12', 'complete' => '1', 'form_name' => '<b>Form</b>', 'reference_code' => 'R&1',
		'contact_id' => '4', 'contact_name' => ' ', 'submitted_username' => null,
		'submitted_timestamp' => time(), 'last_modified_username' => 'ed', 'last_modified_timestamp' => time(),
	), true);

	pg_assert_same(9, count($row['cells']), 'as many cells as headings');
	pg_assert_contains('name="forms[]" value="12"', $row['cells'][0]['html']);
	pg_assert_contains("edit_submitted_form.php?id=12", $row['cells'][1]['html']);
	pg_assert_same('&lt;b&gt;Form&lt;/b&gt;', $row['cells'][2]['html']);
	pg_assert_same('chart_label', $row['cells'][2]['class']);
	pg_assert_same('R&amp;1', $row['cells'][3]['html']);
	pg_assert_contains('[' . lang('No Name') . ']', $row['cells'][5]['html'], 'a contact without a name');
	pg_assert_contains('edit_contact.php?id=4', $row['cells'][5]['html']);
	pg_assert_same('', $row['cells'][6]['html'], 'no user');
	pg_assert_contains('[' . lang('Unknown') . ']', $row['cells'][7]['html']);

	$row = pg_submitted_form_list_row(array(
		'id' => '3', 'complete' => '0', 'form_name' => 'F', 'reference_code' => '',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => 'sam',
		'submitted_timestamp' => time(), 'last_modified_username' => '', 'last_modified_timestamp' => time(),
	), false);

	pg_assert_same('', $row['cells'][5]['html'], 'no contact, no contacts access');
	pg_assert_contains(lang('Incomplete'), $row['cells'][4]['html']);
	pg_assert_same('sam', $row['cells'][6]['html']);
}
