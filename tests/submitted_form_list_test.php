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
	submitted_form_list_test_state(array('range_picked' => true, 'start_year' => 'x', 'stop_day' => '7'));
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

	pg_assert_same(12, $row['id']);
	pg_assert_same(array_keys(pg_submitted_form_list_columns()), array_keys($row['cells']), 'a cell for every column');
	pg_assert_contains('data-pg-open href="edit_submitted_form.php?id=12"', $row['cells']['custom_form']['html'], 'the name opens the form');
	pg_assert_contains('&lt;b&gt;Form&lt;/b&gt;</a>', $row['cells']['custom_form']['html']);
	pg_assert_same('chart_label', $row['cells']['custom_form']['class']);
	pg_assert_contains('edit_submitted_form.php?id=12', $row['cells']['actions']['html']);
	pg_assert_same('R&amp;1', $row['cells']['reference_code']['html']);
	pg_assert_contains('[' . lang('No Name') . ']', $row['cells']['contact']['html'], 'a contact without a name');
	pg_assert_contains('edit_contact.php?id=4', $row['cells']['contact']['html']);
	pg_assert_same('', $row['cells']['user']['html'], 'no user');
	pg_assert_contains('[' . lang('Unknown') . ']', $row['cells']['submitted']['html']);

	$row = pg_submitted_form_list_row(array(
		'id' => '3', 'complete' => '0', 'form_name' => 'F', 'reference_code' => '',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => 'sam',
		'submitted_timestamp' => time(), 'last_modified_username' => '', 'last_modified_timestamp' => time(),
	), false);

	pg_assert_same('', $row['cells']['contact']['html'], 'no contact, no contacts access');
	pg_assert_contains(lang('Incomplete'), $row['cells']['status']['html']);
	pg_assert_same('sam', $row['cells']['user']['html']);

	// A submission whose contact is missing or deleted reads contacts.id as
	// NULL: no empty, nameless link even with contacts access.
	$row = pg_submitted_form_list_row(array(
		'id' => '5', 'complete' => '1', 'form_name' => 'F', 'reference_code' => '',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => 'sam',
		'submitted_timestamp' => time(), 'last_modified_username' => '', 'last_modified_timestamp' => time(),
	), true);

	pg_assert_same('', $row['cells']['contact']['html'], 'no contact, contacts access');
}

function test_submitted_form_list_labels_tell_forms_apart()
{
	$labels = pg_submitted_form_list_labels(array(
		array('id' => '1152', 'form_name' => 'Blog', 'page_name' => 'blog-old', 'binned' => true),
		array('id' => '1609', 'form_name' => 'Blog', 'page_name' => 'blog', 'binned' => false),
		array('id' => '1584', 'form_name' => 'Contact', 'page_name' => 'contact', 'binned' => false),
		array('id' => '1580', 'form_name' => '', 'page_name' => 'catering', 'binned' => false),
		array('id' => '1123', 'form_name' => 'Old', 'page_name' => 'old', 'binned' => true),
	));

	pg_assert_same(array('1609', '1584', '1580', '1152', '1123'), array_column($labels, 'id'), 'binned forms go last, the rest keep their order');
	pg_assert_same('Blog — blog', $labels[0]['name'], 'a repeated form name gets its page name');
	pg_assert_same('Contact', $labels[1]['name'], 'a single form name stays as it is');
	pg_assert_same('catering', $labels[2]['name'], 'a form without a name shows its page name');
	pg_assert_same('Blog — blog-old [' . lang('IN RECYCLE BIN') . ']', $labels[3]['name'], 'a binned form is marked');
	pg_assert_same('Old [' . lang('IN RECYCLE BIN') . ']', $labels[4]['name'], 'a binned form with a single name');
	pg_assert_same(true, $labels[3]['binned'], 'the binned flag is kept');
}

function test_submitted_form_list_options_keep_every_form()
{
	$options = pg_submitted_form_list_options(array(
		array('id' => '1609', 'name' => 'Blog — blog', 'binned' => false),
		array('id' => '1654', 'name' => 'Blog — blog', 'binned' => false),
		array('id' => '1584', 'name' => 'Contact & Co', 'binned' => false),
	));

	pg_assert_same(array('1609', '1654', '1584'), array_values($options), 'each form is its own option');
	pg_assert_same(array('Blog — blog', 'Blog — blog (#1654)', 'Contact &amp; Co'), array_keys($options), 'a label still equal gets the form id; labels are escaped');
}

// A field filter is a condition on the form, not a join: a check box group
// stores one row per ticked box, and a join on a filter two of them match
// listed the form twice and counted it twice.
function test_submitted_form_list_field_condition_is_one_per_form()
{
	$condition = pg_submitted_form_list_field_condition(12, 'e');

	pg_assert_same(0, preg_match('/\bJOIN\b/i', $condition['sql']), 'no join');
	pg_assert_contains('EXISTS (SELECT 1 FROM form_data', $condition['sql']);
	pg_assert_contains('(form_data.form_id = forms.id)', $condition['sql']);
	pg_assert_same(array('12', pg_list_like_pattern('e')), $condition['params']);
}

function test_submitted_form_list_labels_number_forms_still_equal()
{
	$labels = pg_submitted_form_list_labels(array(
		array('id' => '1745', 'form_name' => 'Blog', 'page_name' => 'post-4', 'binned' => true),
		array('id' => '1778', 'form_name' => 'Blog', 'page_name' => 'post-4', 'binned' => true),
		array('id' => '1609', 'form_name' => 'Blog', 'page_name' => 'blog', 'binned' => false),
	));

	pg_assert_same('Blog — blog', $labels[0]['name'], 'a label of its own gets no id');
	pg_assert_same('Blog — post-4 [' . lang('IN RECYCLE BIN') . '] (#1745)', $labels[1]['name'], 'every form of a shared label gets its id');
	pg_assert_same('Blog — post-4 [' . lang('IN RECYCLE BIN') . '] (#1778)', $labels[2]['name'], 'the second one too');
}

function test_submitted_form_list_field_label_reads_the_label()
{
	pg_assert_same(array('text' => 'Department', 'title' => 'Department (department)'), pg_submitted_form_list_field_label('Department', 'department'), 'the label is shown, the title names the field');
	pg_assert_same(array('text' => 'doctor', 'title' => 'doctor'), pg_submitted_form_list_field_label('', 'doctor'), 'a field without a label shows its name');
	pg_assert_same('A & B', pg_submitted_form_list_field_label('<b>A &amp; B</b>', 'ab')['text'], 'markup is taken off, not escaped twice');

	$long = pg_submitted_form_list_field_label(str_repeat('consent ', 20), 'consent', 30);
	pg_assert_same(30, mb_strlen($long['text'], 'UTF-8'), 'a long label is cut');
	pg_assert_same('…', mb_substr($long['text'], -1, 1, 'UTF-8'), 'and ends with an ellipsis');
	pg_assert_true(strpos($long['title'], '(consent)') !== false, 'the title keeps the whole label and the name');
}

function test_submitted_form_import_fixed_columns()
{
	$export = pg_submitted_form_export_columns();

	pg_assert_same(12, pg_submitted_form_import_fixed_columns(array_merge($export, array('status', 'first_name'))), 'an exported file starts with the record columns');
	pg_assert_same(13, pg_submitted_form_import_fixed_columns(array_merge($export, array('quiz_score', 'status'))), 'a quiz adds its score column');
	pg_assert_same(0, pg_submitted_form_import_fixed_columns(array('status', 'first_name')), 'a hand-made file maps every column to fields');
	pg_assert_same(0, pg_submitted_form_import_fixed_columns(array('form_name', 'reference_code')), 'a part of the record columns is not the export');
}

function test_submitted_form_import_complete()
{
	pg_assert_same('0', pg_submitted_form_import_complete(lang('Incomplete')), 'the incomplete status in the panel language');
	pg_assert_same('0', pg_submitted_form_import_complete(' incomplete '), 'in English, any case');
	pg_assert_same('1', pg_submitted_form_import_complete(lang('Complete')), 'complete');
	pg_assert_same('1', pg_submitted_form_import_complete(''), 'an empty cell is complete');
}

function test_submitted_form_import_value_converts_dates()
{
	pg_assert_same('2026-12-20', pg_submitted_form_import_value('2026-12-20', 'date'), 'the export ISO date stays');
	pg_assert_same('1990-12-15', pg_submitted_form_import_value('15/12/1990', 'date'), 'the site order is read');
	pg_assert_same('1990-12-15', pg_submitted_form_import_value('15.12.1990', 'date'), 'the dotted date is read');
	pg_assert_same(null, pg_submitted_form_import_value('31.02.2026', 'date'), 'no real date is refused');
	pg_assert_same(null, pg_submitted_form_import_value('soon', 'date'), 'text is refused');
	pg_assert_same('', pg_submitted_form_import_value('  ', 'date'), 'an empty cell stays empty');

	pg_assert_same('2026-12-20 17:05', pg_submitted_form_import_value('2026-12-20 17:05', 'date and time'), 'the export date and time stays');
	pg_assert_same('2026-12-20 17:05', pg_submitted_form_import_value('2026-12-20T17:05', 'date and time'), 'the ISO T form');
	pg_assert_same('1990-12-15 17:05', pg_submitted_form_import_value('15.12.1990 5:05 PM', 'date and time'), 'a site date with a 12-hour time');
	pg_assert_same(null, pg_submitted_form_import_value('15.12.1990', 'date and time'), 'a date without a time is refused');
	pg_assert_same(null, pg_submitted_form_import_value('15.12.1990 25:00', 'date and time'), 'an impossible time is refused');

	pg_assert_same('17:05', pg_submitted_form_import_value('17:05:00', 'time'), 'the export time');
	pg_assert_same('17:05', pg_submitted_form_import_value('5:05 PM', 'time'), 'a 12-hour time');
	pg_assert_same(null, pg_submitted_form_import_value('noon', 'time'), 'text is no time');

	pg_assert_same(' as is ', pg_submitted_form_import_value(' as is ', 'text box'), 'other types are left as they are');
}

function test_submitted_form_list_columns_keep_the_sort_codes()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$columns = pg_list_table_columns(pg_submitted_form_list_columns());

	pg_assert_same(array('custom_form', 'reference_code', 'status', 'contact', 'user', 'form_editor', 'submitted', 'last_modified'), array_keys(pg_list_table_sort_columns($columns)), 'a kept sort still names its column');
	pg_assert_same(false, $columns['custom_form']['hideable'], 'the form name is never hidden');
	pg_assert_same("CONCAT(contacts.last_name, ' ', contacts.first_name)", $columns['contact']['sort'], 'the contact sorts by last name, then first name');
	pg_assert_false($columns['actions']['sortable']);
}

function test_submitted_form_list_is_a_driver_list()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$definition = pg_list_table_definition('view_submitted_forms');

	pg_assert_same('includes/fn/submitted_form_list.php', $definition['file']);
	pg_assert_same('pg_submitted_form_list_source', $definition['source']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
	pg_assert_same(null, pg_submitted_form_list_source(array('id' => 4, 'role' => 3, 'manage_forms' => false), array()), 'a user who does not manage forms may not open the list');
}

// A designed form's fee: the Payment column only once the fee columns exist,
// after Status and never sorted, so the kept sort codes stay as they were.
function test_submitted_form_list_payment_column()
{
	pg_assert_false(array_key_exists('payment', pg_submitted_form_list_columns()), 'no fee columns: no Payment column');

	$columns = pg_submitted_form_list_columns(true);
	$keys = array_keys($columns);
	pg_assert_same(array_search('status', $keys) + 1, array_search('payment', $keys), 'right after Status');
	pg_assert_same('badge', $columns['payment']['kind']);
	pg_assert_false(isset($columns['payment']['sort']), 'not sorted');
}

// The badge: nothing for a record without a fee order, Unpaid or Paid.
function test_submitted_form_list_payment_cell()
{
	pg_assert_same('', pg_submitted_form_list_payment_badge(array('order_id' => 0, 'paid_at' => 0)), 'no fee');
	pg_assert_contains(lang('Unpaid'), pg_submitted_form_list_payment_badge(array('order_id' => '7', 'paid_at' => '0')));
	pg_assert_contains(lang('Paid'), pg_submitted_form_list_payment_badge(array('order_id' => '7', 'paid_at' => '1700000000')));

	$record = array(
		'id' => '3', 'complete' => '1', 'form_name' => 'F', 'reference_code' => 'R',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => '',
		'submitted_timestamp' => time(), 'last_modified_username' => '', 'last_modified_timestamp' => time(),
	);
	pg_assert_same(array_keys(pg_submitted_form_list_columns()), array_keys(pg_submitted_form_list_row($record, false)['cells']), 'a record read without the fee columns has no Payment cell');

	$row = pg_submitted_form_list_row($record + array('order_id' => '7', 'paid_at' => '0'), false);
	pg_assert_same(array_keys(pg_submitted_form_list_columns(true)), array_keys($row['cells']), 'a cell for every column with the fee columns');
	pg_assert_contains(lang('Unpaid'), $row['cells']['payment']['html']);
}

// The Unpaid filter, in the simple and the advanced view alike, only once
// the fee columns exist.
function test_submitted_form_list_unpaid_filter()
{
	pg_assert_same(pg_form_payment_unpaid_where('forms'), pg_submitted_form_list_payment_condition(array('payment' => 'unpaid'), true));
	pg_assert_same('', pg_submitted_form_list_payment_condition(array('payment' => 'unpaid'), false), 'no fee columns: no filter');
	pg_assert_same('', pg_submitted_form_list_payment_condition(array('payment' => 'any'), true), 'any');
	pg_assert_same('', pg_submitted_form_list_payment_condition(array(), true), 'unset');
	pg_assert_same('', pg_submitted_form_list_payment_condition(array('payment' => array('unpaid')), true), 'not text');
}
