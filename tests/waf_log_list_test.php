<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the firewall log's events list (includes/fn/waf_log_list.php):
 * the filters, their bound condition, the sort with its tie level, the
 * search box's condition and an event's row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/waf_log_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $waf_log_list_test_name => $waf_log_list_test_value) {
	if (!defined($waf_log_list_test_name)) {
		define($waf_log_list_test_name, $waf_log_list_test_value);
	}
}

// A value that is not one of the choices, or not one value, counts as not given.
function test_waf_log_list_filters()
{
	pg_assert_same(array('range' => '7', 'category' => '', 'search' => ''), pg_waf_log_list_filters(array()), 'the default period');
	pg_assert_same(array('range' => '30', 'category' => 'sqli', 'search' => '1.2.3.4'), pg_waf_log_list_filters(array('range' => '30', 'category' => 'sqli', 'search' => ' 1.2.3.4 ')));
	pg_assert_same(array('range' => '1', 'category' => '', 'search' => ''), pg_waf_log_list_filters(array('range' => 1, 'category' => 'nope', 'search' => array('x'))), 'a number from JSON, an unknown category, an array');
	pg_assert_same(array('range' => '7', 'category' => '', 'search' => ''), pg_waf_log_list_filters(array('range' => array('30'))), 'an array period');
	pg_assert_same(200, strlen(pg_waf_log_list_filters(array('search' => str_repeat('a', 300)))['search']), 'the search is cut to 200 characters');
}

function test_waf_log_list_condition()
{
	pg_assert_same(array('sql' => 'log_timestamp >= ?', 'params' => array(1000000 - 7 * 86400)), pg_waf_log_list_condition(pg_waf_log_list_filters(array()), 1000000));

	$condition = pg_waf_log_list_condition(pg_waf_log_list_filters(array('range' => '1', 'category' => 'bot', 'search' => '50%_?')), 1000000);
	pg_assert_same('log_timestamp >= ? AND category = ? AND (ip_address LIKE ? OR request_url LIKE ? OR rule_id LIKE ? OR reference LIKE ? OR user_agent LIKE ?)', $condition['sql']);
	pg_assert_same(array(1000000 - 86400, 'bot', '%50\\%\\_?%', '%50\\%\\_?%', '%50\\%\\_?%', '%50\\%\\_?%', '%50\\%\\_?%'), $condition['params'], 'wildcards are literal, a ? is a value');
}

function test_waf_log_list_sort()
{
	$_SESSION['software']['list_state']['view_waf_log'] = array();
	pg_assert_same('last_seen desc, id desc', pg_waf_log_list_sort()['order_by'], 'the last hit first by default');

	$_SESSION['software']['list_state']['view_waf_log'] = array('sort' => 'hits', 'order' => 'asc');
	pg_assert_same('hit_count asc, id asc', pg_waf_log_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_waf_log'] = array('sort' => 'matched', 'order' => 'asc');
	pg_assert_same('last_seen desc, id desc', pg_waf_log_list_sort()['order_by'], 'a column the list does not sort by falls back to the default');

	unset($_SESSION['software']['list_state']['view_waf_log']);
}

function test_waf_log_list_search()
{
	pg_assert_same(array('sql' => '', 'params' => array()), pg_waf_log_list_search(array()));

	$search = pg_waf_log_list_search(array('wp-login'));
	pg_assert_same('(ip_address LIKE ? OR method LIKE ? OR request_url LIKE ? OR user_agent LIKE ? OR rule_id LIKE ? OR reference LIKE ? OR target LIKE ? OR matched LIKE ?)', $search['sql']);

	$search = pg_waf_log_list_search(array(lang('Would ban')));
	pg_assert_contains('action IN (?)', $search['sql'], 'an action by its shown name');
	pg_assert_same('would-ban', end($search['params']));
}

function test_waf_log_list_row()
{
	$now = 2000000;
	$event = array(
		'id' => '4', 'log_timestamp' => (string) ($now - 600), 'last_seen' => (string) ($now - 60), 'hit_count' => '12',
		'action' => 'would-block', 'ip_address' => '203.0.113.7', 'rule_id' => 'sqli-1', 'reference' => 'AB12',
		'score' => '8', 'method' => 'GET', 'request_url' => '/?q=<script>', 'user_agent' => 'curl', 'target' => 'query', 'matched' => "' OR 1=1",
	);

	$row = pg_waf_log_list_row($event, $now);
	$html = pg_list_row_html($row);

	pg_assert_same(8, count($row['cells']), 'one cell per heading');
	pg_assert_contains('bg-warning fg-black', $html);
	pg_assert_contains('&times;12', $html, 'folded hits');
	pg_assert_contains('fg-danger fw-semibold', $html, 'still firing');
	pg_assert_contains('/?q=&lt;script&gt;', $html, 'the request is escaped');
	pg_assert_contains('&#039; OR 1=1', $html, 'the match is escaped');
	pg_assert_contains('AB12', $html, 'the reference');

	$event['last_seen'] = $event['log_timestamp'];
	$event['hit_count'] = '1';
	$event['action'] = 'unknown';
	$html = pg_list_row_html(pg_waf_log_list_row($event, $now));
	pg_assert_false(strpos($html, 'fg-danger fw-semibold') !== false, 'stopped ten minutes ago');
	pg_assert_contains('<span class="badge bg-secondary">unknown</span>', $html, 'an action without a badge');
}
