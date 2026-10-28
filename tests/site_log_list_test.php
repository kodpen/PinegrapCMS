<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Site Log list (includes/fn/site_log_list.php): the range the
 * action accepts, the sort with its tie level, the description cell and the
 * rows of a site log entry and of an error_log entry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_log_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $site_log_list_test_name => $site_log_list_test_value) {
	if (!defined($site_log_list_test_name)) {
		define($site_log_list_test_name, $site_log_list_test_value);
	}
}

function test_site_log_list_range()
{
	pg_assert_same(array('start' => 100, 'stop' => 200), pg_site_log_list_range('100', 200));
	pg_assert_same(array('start' => 300, 'stop' => 300), pg_site_log_list_range('300', '200'), 'the stop is never before the start');
	pg_assert_same(null, pg_site_log_list_range('1 OR 1', '200'));
	pg_assert_same(null, pg_site_log_list_range(null, '200'));
	pg_assert_same(null, pg_site_log_list_range(array(1), '200'));
	pg_assert_same(null, pg_site_log_list_range('-5', '200'));
}

function test_site_log_list_sort()
{
	unset($_SESSION['software']['list_state']['view_log']);
	pg_assert_same('log_timestamp desc, log_id desc', pg_site_log_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_log'] = array('sort' => 'user', 'order' => 'asc');
	pg_assert_same('log_user asc, log_id asc', pg_site_log_list_sort()['order_by'], 'ties keep the logged order in the first level\'s direction');
}

function test_site_log_list_site_row()
{
	$row = pg_site_log_list_row(array('log_id' => '41', 'log_description' => 'Page <b> saved', 'log_ip' => '', 'log_user' => '', 'log_timestamp' => (string) time()));
	$html = pg_list_row_html($row);

	pg_assert_same('41', $row['id']);
	pg_assert_same(5, count($row['cells']), 'one cell per heading');
	pg_assert_contains('<tr id="41" class="unselectable">', $html);
	pg_assert_contains('pglogsite', $html);
	pg_assert_contains('UNKNOWN', $html, 'a visitor without a user');
	pg_assert_contains('Page &lt;b&gt; saved', $html);
	pg_assert_contains('id="pg_log_msg_41"', $html);
	pg_assert_contains('&mdash;', $html, 'no IP address');
	pg_assert_false(strpos($html, 'pg-log-copy') !== false, 'no copy button on a site log');

	$data = pg_list_row_data($row);
	pg_assert_same('41', $data['DT_RowId']);
	pg_assert_same('unselectable', $data['DT_RowClass']);
}

function test_site_log_list_error_row()
{
	$trace = "PHP Warning: x\n#0 a\n#1 b\n#2 c";
	$html = pg_list_row_html(pg_site_log_list_row(array(
		'log_id' => 'error_php_errors_3', 'log_type' => 'Error Log', 'log_description' => $trace, 'log_ip' => '',
		'log_user' => '[SYSTEM]', 'log_location' => 'Software error log', 'log_timestamp' => (string) time(),
	)));

	pg_assert_contains('pg-log-row-warn', $html);
	pg_assert_contains('pglogwarn', $html);
	pg_assert_contains('bg-warning fg-black', $html);
	pg_assert_contains('pg-log-copy', $html);
	pg_assert_contains('pg-log-clip', $html, 'a long trace is clipped');
	pg_assert_contains('id="pg_log_msg_error_php_errors_3"', $html);

	$html = pg_list_row_html(pg_site_log_list_row(array(
		'log_id' => 'error_x_1', 'log_type' => 'Error Log', 'log_description' => 'PHP Fatal error: y', 'log_ip' => '',
		'log_user' => '[SYSTEM]', 'log_location' => '', 'log_timestamp' => (string) time(),
	)));
	pg_assert_contains('pg-log-row-error', $html);
	pg_assert_contains('pglogerror', $html);
}
