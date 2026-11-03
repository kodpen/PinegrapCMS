<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Site Log list (includes/fn/site_log_list.php): the range the
 * list accepts, the sort with its tie level, the driver's source, the
 * description cell and the rows of a site log entry and of an error_log
 * entry.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

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

function site_log_list_test_html(array $row)
{
	return pg_list_table_row_html($row, pg_list_table_columns(pg_site_log_list_columns()));
}

function test_site_log_list_site_row()
{
	$now = time();
	$row = pg_site_log_list_row(array('log_id' => '41', 'log_description' => 'Page <b> saved', 'log_ip' => '', 'log_user' => '', 'log_timestamp' => (string) $now));
	$html = site_log_list_test_html($row);

	pg_assert_same('41', $row['id']);
	pg_assert_same(array_keys(pg_site_log_list_columns()), array_keys($row['cells']), 'one cell per column');
	pg_assert_contains('<tr role="row" data-pg-id="41" class="unselectable">', $html);
	pg_assert_contains('data-pg-filter="site"', $html, 'the type filter reads the cell');
	pg_assert_contains('data-pg-sort-value="' . $now . '"', $html, 'the browser sorts the time by the Unix time');
	pg_assert_contains(h(lang('Scheduled job')), $html, 'a row without a user or an address');
	pg_assert_false(strpos($html, 'UNKNOWN') !== false, 'no fixed UNKNOWN');
	pg_assert_contains('Page &lt;b&gt; saved', $html);
	pg_assert_contains('id="pg_log_msg_41"', $html);
	pg_assert_contains('&mdash;', $html, 'no IP address');
	pg_assert_false(strpos($html, 'pg-log-copy') !== false, 'no copy button on a site log');
}

function test_site_log_list_error_row()
{
	$trace = "PHP Warning: x\n#0 a\n#1 b\n#2 c";
	$html = site_log_list_test_html(pg_site_log_list_row(array(
		'log_id' => 'error_php_errors_3', 'log_type' => 'Error Log', 'log_description' => $trace, 'log_ip' => '',
		'log_user' => '[SYSTEM]', 'log_location' => 'Software error log', 'log_timestamp' => (string) time(),
	)));

	pg_assert_contains('pg-log-row-warn', $html);
	pg_assert_contains('data-pg-filter="warn"', $html);
	pg_assert_contains('theme-warning fg-black', $html);
	pg_assert_contains('pg-log-copy', $html);
	pg_assert_contains('pg-log-clip', $html, 'a long trace is clipped');
	pg_assert_contains('id="pg_log_msg_error_php_errors_3"', $html);

	$html = site_log_list_test_html(pg_site_log_list_row(array(
		'log_id' => 'error_x_1', 'log_type' => 'Error Log', 'log_description' => 'PHP Fatal error: y', 'log_ip' => '',
		'log_user' => '[SYSTEM]', 'log_location' => '', 'log_timestamp' => (string) time(),
	)));
	pg_assert_contains('pg-log-row-error', $html);
	pg_assert_contains('data-pg-filter="error"', $html);
}

function test_site_log_list_is_a_driver_list()
{
	$definition = pg_list_table_definition('view_log');

	pg_assert_same('includes/fn/site_log_list.php', $definition['file']);
	pg_assert_same('pg_site_log_list_source', $definition['source']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
	pg_assert_same(null, pg_site_log_list_source(array('id' => 9, 'role' => 3), array('start_time' => '1', 'stop_time' => '2')), 'a basic user may not open the list');
	pg_assert_same(null, pg_site_log_list_source(array('id' => 9, 'role' => 0), array('start_time' => 'x', 'stop_time' => '2')), 'a range that is not whole numbers');

	unset($_SESSION['software']['list_state']['view_log']);
	$source = pg_site_log_list_source(array('id' => 9, 'role' => 2), array('start_time' => '100', 'stop_time' => '50'));
	pg_assert_same(array('100', '100'), $source['params'], 'the stop is never before the start');
	pg_assert_same(array('start_time' => '100', 'stop_time' => '100'), $source['params_out'], 'the browser sends the range back');
	pg_assert_same(array(array('sort' => 'time', 'order' => 'desc')), $source['levels'], 'newest first');
	pg_assert_false($source['columns']['type']['sortable'], 'the type is filtered, not sorted');
	pg_assert_false($source['all'], 'the database view is read in batches');
}
