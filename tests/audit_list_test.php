<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audit trail list (includes/fn/audit_list.php): links that
 * narrow the list, the sort with its tie level, the search box's condition,
 * a line's row and its changes table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $audit_list_test_name => $audit_list_test_value) {
	if (!defined($audit_list_test_name)) {
		define($audit_list_test_name, $audit_list_test_value);
	}
}

// A link keeps the other filters, changes one and drops an emptied one.
function test_audit_list_link()
{
	pg_assert_same('view_audit_log.php', pg_audit_list_link(array()));
	pg_assert_same('view_audit_log.php?type=page&id=5&action=update', pg_audit_list_link(array('type' => 'page', 'id' => 5), array('action' => 'update')));
	pg_assert_same('view_audit_log.php?type=page', pg_audit_list_link(array('type' => 'page', 'id' => 5, 'request' => 'r1'), array('id' => 0, 'request' => '')));
}

function test_audit_list_sort()
{
	// No kept sort; $_SESSION may not exist yet when this test runs first.
	$_SESSION['software']['list_state']['view_audit_log'] = array();
	pg_assert_same('created_at desc, id desc', pg_audit_list_sort()['order_by'], 'newest first by default');

	$_SESSION['software']['list_state']['view_audit_log'] = array('sort' => 'who', 'order' => 'asc');
	pg_assert_same('username asc, id asc', pg_audit_list_sort()['order_by'], 'ties keep the written order in the first level\'s direction');

	$_SESSION['software']['list_state']['view_audit_log'] = array('sort' => 'before_json', 'order' => 'asc');
	pg_assert_same('created_at desc, id desc', pg_audit_list_sort()['order_by'], 'a column the list does not sort by falls back to the default');

	unset($_SESSION['software']['list_state']['view_audit_log']);
}

// Each word is found in a text column, a shown label or the record's number.
function test_audit_list_search()
{
	pg_assert_same(array('sql' => '', 'params' => array()), pg_audit_list_search(array()), 'no word');

	$search = pg_audit_list_search(array('50%'));
	pg_assert_same('(username LIKE ? OR label LIKE ? OR ip LIKE ? OR request_id LIKE ? OR action LIKE ? OR entity_type LIKE ?)', $search['sql'], 'a word that is not a number, nor in a label');
	pg_assert_same('%50\\%%', $search['params'][0], 'wildcards are literal');

	$search = pg_audit_list_search(array('#12'));
	pg_assert_contains('CAST(entity_id AS CHAR) LIKE ?', $search['sql'], 'the record number');
	pg_assert_same('%12%', end($search['params']), 'without its #');

	$search = pg_audit_list_search(array(lang('Deleted')));
	pg_assert_contains('action IN (?)', $search['sql'], 'an action by its shown name');
	pg_assert_same('delete', end($search['params']));
}

function test_audit_list_row()
{
	$line = array(
		'id' => '9', 'created_at' => (string) mktime(10, 0, 0, 10, 1, 2026), 'request_id' => 'req1', 'source' => 'panel',
		'user_id' => '3', 'username' => 'ann<b>', 'ip' => '10.0.0.1', 'action' => 'update', 'entity_type' => 'page',
		'entity_id' => '5', 'label' => 'Home', 'before_json' => '{"page_name":"a","x":"1"}', 'after_json' => '{"page_name":"b"}',
	);

	$row = pg_audit_list_row($line, array('page' => array(5 => true)), array('source' => 'panel'));
	$html = pg_list_row_html($row);

	pg_assert_same(6, count($row['cells']), 'one cell per heading');
	pg_assert_contains('ann&lt;b&gt;', $html, 'the name is escaped');
	pg_assert_contains('href="view_audit_log.php?source=panel&amp;user=ann%3Cb%3E"', $html, 'the user narrows the list kept so far');
	pg_assert_contains('href="view_audit_log.php?source=panel&amp;type=page&amp;id=5"', $html, 'the record history');
	pg_assert_contains('href="view_audit_log.php?source=panel&amp;request=req1"', $html, 'every line of the request');
	pg_assert_contains('edit_page.php?id=5', $html, 'a live record opens');
	pg_assert_contains('data-pg-audit-line="9"', $html, 'the changes are read when opened');
	pg_assert_contains('2 fields', $html);
	pg_assert_false(strpos($html, 'page_name') !== false, 'the changed values are not in the row');

	$html = pg_list_row_html(pg_audit_list_row($line, array(), array()));
	pg_assert_false(strpos($html, 'edit_page.php') !== false, 'a deleted record has no Open link');

	$line['before_json'] = null;
	$line['after_json'] = null;
	pg_assert_false(strpos(pg_list_row_html(pg_audit_list_row($line, array(), array())), 'data-pg-audit-line') !== false, 'no fold without a change');
}

function test_audit_list_changes_html()
{
	$html = pg_audit_list_changes_html(pg_audit_view_changes('{"title":"<i>old</i>","key":"***"}', '{"title":"new","key":"***"}'));

	pg_assert_contains('<code>title</code>', $html);
	pg_assert_contains('&lt;i&gt;old&lt;/i&gt;', $html, 'values are escaped');
	pg_assert_contains(lang('Hidden'), $html, 'a secret');

	$html = pg_audit_list_changes_html(pg_audit_view_changes('{"truncated":true,"fields":["body"]}', null));
	pg_assert_contains(lang('Too large to keep'), $html);
}
