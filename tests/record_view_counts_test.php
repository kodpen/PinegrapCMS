<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the view counters of records shown on pages built in the visual
 * editor and for ordering form lists by them and by newest activity: the
 * ORDER BY term of a counter or activity order (pg_sw_counter_order_sql()),
 * the recent-views window (pg_sw_recent_views_days()), which registered views
 * count (pg_sw_should_count_record_view(), pg_sw_record_view_seen()), the
 * shared automated-client rule (pg_request_is_automated()), the editor's copy
 * of the list and its Statistics options, and where get_page.php and the
 * record widget register and count a view.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

function _record_view_counts_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a top-level PHP function, from its declaration to the next one.
function _record_view_counts_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) return '';
	$end = strpos($source, "\nfunction ", $start + 10);
	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function _record_view_counts_ends_with($haystack, $needle)
{
	return substr($haystack, -strlen($needle)) === $needle;
}

// -- the ORDER BY term -----------------------------------------------------------

function test_record_view_counts_order_sql_counters()
{
	$views = pg_sw_counter_order_sql('number_of_views', 'DESC', 12, null, true);
	pg_assert_contains('MAX(pg_ord_i.number_of_views)', $views);
	pg_assert_contains("pg_ord_i.page_id = '12'", $views);
	pg_assert_contains('pg_ord_i.submitted_form_id = forms.id', $views);
	pg_assert_true(_record_view_counts_ends_with($views, ' DESC'));

	$comments = pg_sw_counter_order_sql('number_of_comments', 'ASC', 12, null, true);
	pg_assert_contains('MAX(pg_ord_i.number_of_comments)', $comments);
	pg_assert_true(_record_view_counts_ends_with($comments, ' ASC'));

	// Never a join: a duplicate counter row must not print a record twice.
	pg_assert_false(stripos($views, 'JOIN') !== false);
}

function test_record_view_counts_order_sql_not_a_counter()
{
	pg_assert_same(null, pg_sw_counter_order_sql('submitted_date_and_time', 'DESC', 12, null, true));
	pg_assert_same(null, pg_sw_counter_order_sql('title', 'DESC', 12, null, true));
	pg_assert_same(null, pg_sw_counter_order_sql('', 'DESC', 12, null, true));
}

// The counters are kept per detail page: without one there is no order.
function test_record_view_counts_order_sql_without_detail_page()
{
	foreach (pg_sw_form_order_counters() as $counter) {
		pg_assert_same('', pg_sw_counter_order_sql($counter, 'DESC', 0, 7, true), $counter . ' 0');
		pg_assert_same('', pg_sw_counter_order_sql($counter, 'DESC', '', 7, true), $counter . ' empty');
		pg_assert_same('', pg_sw_counter_order_sql($counter, 'DESC', 'tab:x', 7, true), $counter . ' tab');
		pg_assert_same('', pg_sw_counter_order_sql($counter, 'DESC', array(5), 7, true), $counter . ' array');
	}
}

function test_record_view_counts_order_sql_recent_views()
{
	$week = pg_sw_counter_order_sql('recent_views', 'DESC', 12, 7, true);
	pg_assert_contains('SUM(pg_ord_s.views)', $week);
	pg_assert_contains('submitted_form_view_stats', $week);
	pg_assert_contains("pg_ord_s.page_id = '12'", $week);
	pg_assert_contains('DATE_SUB(CURDATE(), INTERVAL 6 DAY)', $week);

	pg_assert_contains('INTERVAL 0 DAY', pg_sw_counter_order_sql('recent_views', 'DESC', 12, 1, true));
	pg_assert_contains('INTERVAL 179 DAY', pg_sw_counter_order_sql('recent_views', 'DESC', 12, 500, true));

	// Without the daily table: the all-time counter.
	pg_assert_same(
		pg_sw_counter_order_sql('number_of_views', 'DESC', 12, null, true),
		pg_sw_counter_order_sql('recent_views', 'DESC', 12, 7, false)
	);
}

function test_record_view_counts_order_sql_hostile_input()
{
	pg_assert_true(_record_view_counts_ends_with(pg_sw_counter_order_sql('number_of_views', 'ASC; DROP', 12, null, true), ' DESC'));

	$page = pg_sw_counter_order_sql('number_of_views', 'DESC', '12 OR 1=1', null, true);
	pg_assert_contains("pg_ord_i.page_id = '12')", $page);
	pg_assert_false(strpos($page, 'OR 1=1') !== false);

	$days = pg_sw_counter_order_sql('recent_views', 'DESC', 12, '7) OR (1', true);
	pg_assert_contains('INTERVAL 6 DAY)', $days);
	pg_assert_false(strpos($days, 'OR (1') !== false);
}

// Newest activity: the later of the submitted time and the newest comment the
// detail page's row names, compared as integers with a 0 fallback.
function test_record_view_counts_order_sql_newest_activity()
{
	$s = pg_sw_counter_order_sql('newest_activity_date_and_time', 'DESC', 12, null, true);
	pg_assert_true(is_string($s) && $s !== '', 'an order term');
	pg_assert_contains('GREATEST(forms.submitted_timestamp, IFNULL((SELECT MAX(pg_ord_c.created_timestamp) FROM submitted_form_info pg_ord_i INNER JOIN comments pg_ord_c ON pg_ord_c.id = pg_ord_i.newest_comment_id', (string) $s);
	pg_assert_contains("pg_ord_i.page_id = '12'", (string) $s);
	pg_assert_contains('pg_ord_i.submitted_form_id = forms.id', (string) $s);
	pg_assert_true(_record_view_counts_ends_with((string) $s, '), 0)) DESC'));

	pg_assert_true(_record_view_counts_ends_with((string) pg_sw_counter_order_sql('newest_activity_date_and_time', 'ASC', 12, null, true), ' ASC'));

	// The daily table does not matter here.
	pg_assert_same($s, pg_sw_counter_order_sql('newest_activity_date_and_time', 'DESC', 12, null, false));

	// Integers only: no '' fallback, no date string, no outer join.
	pg_assert_false(strpos((string) $s, "''") !== false);
	pg_assert_false(strpos((string) $s, 'FROM_UNIXTIME') !== false);
	pg_assert_false(stripos((string) $s, 'LEFT JOIN') !== false);

	pg_assert_true(_record_view_counts_ends_with((string) pg_sw_counter_order_sql('newest_activity_date_and_time', 'ASC; DROP', 12, null, true), ' DESC'));
	$page = (string) pg_sw_counter_order_sql('newest_activity_date_and_time', 'DESC', '12 OR 1=1', null, true);
	pg_assert_contains("pg_ord_i.page_id = '12')", $page);
	pg_assert_false(strpos($page, 'OR 1=1') !== false);
}

function test_record_view_counts_recent_days()
{
	foreach (array(null, '', 'abc', 0, -3, '7) OR (1', array()) as $value) {
		pg_assert_same(7, pg_sw_recent_views_days($value), var_export($value, true));
	}

	pg_assert_same(1, pg_sw_recent_views_days(1));
	pg_assert_same(180, pg_sw_recent_views_days(180));
	pg_assert_same(180, pg_sw_recent_views_days(181));
	pg_assert_same(180, pg_sw_recent_views_days(9999));
	pg_assert_same(14, pg_sw_recent_views_days('14'));
	pg_assert_same(7, pg_sw_recent_views_days(7.9));
}

// -- which views count -------------------------------------------------------------

function test_record_view_counts_should_count()
{
	$seen = array('form_id' => 5, 'page_id' => 7);

	pg_assert_true(pg_sw_should_count_record_view($seen, 'GET', false));
	pg_assert_true(pg_sw_should_count_record_view($seen, 'get', false));

	pg_assert_false(pg_sw_should_count_record_view(array(), 'GET', false));
	pg_assert_false(pg_sw_should_count_record_view(null, 'GET', false));
	pg_assert_false(pg_sw_should_count_record_view(array('form_id' => 5, 'page_id' => 0), 'GET', false));
	pg_assert_false(pg_sw_should_count_record_view(array('form_id' => 0, 'page_id' => 7), 'GET', false));
	pg_assert_false(pg_sw_should_count_record_view($seen, 'HEAD', false));
	pg_assert_false(pg_sw_should_count_record_view($seen, 'POST', false));
	pg_assert_false(pg_sw_should_count_record_view($seen, '', false));
	pg_assert_false(pg_sw_should_count_record_view($seen, 'GET', true));
}

function test_record_view_counts_seen_first_wins()
{
	pg_sw_record_view_seen(false);
	pg_assert_same(array(), pg_sw_record_view_seen());

	pg_sw_record_view_seen(5, 7);
	pg_sw_record_view_seen(6, 8);
	pg_assert_same(array('form_id' => 5, 'page_id' => 7), pg_sw_record_view_seen());

	pg_sw_record_view_seen(false);
	pg_sw_record_view_seen(0, 7);
	pg_sw_record_view_seen(5, 0);
	pg_assert_same(array(), pg_sw_record_view_seen());

	pg_sw_record_view_seen(false);
}

function test_record_view_counts_automated_clients()
{
	$had = array_key_exists('HTTP_USER_AGENT', $_SERVER);
	$saved = $had ? $_SERVER['HTTP_USER_AGENT'] : null;

	pg_assert_false(defined('IS_BOT'));

	// The classifier reads the firewall settings, which need a connection;
	// the same preload as maintenance_mode_test.php, ignored once one is set.
	waf_config(array('waf_trusted_proxies' => ''));

	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
	pg_assert_false(pg_request_is_automated(), 'chrome');

	$automated = array(
		'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
		'curl/8.5.0',
		'python-requests/2.31',
		'',
		'Pinegrap/2026.11.0',
	);
	foreach ($automated as $agent) {
		$_SERVER['HTTP_USER_AGENT'] = $agent;
		pg_assert_true(pg_request_is_automated(), $agent);
	}

	unset($_SERVER['HTTP_USER_AGENT']);
	pg_assert_true(pg_request_is_automated(), 'no user agent');

	if ($had) {
		$_SERVER['HTTP_USER_AGENT'] = $saved;
	}
}

// -- the editor keeps the same list ------------------------------------------------

function test_record_view_counts_editor_lockstep()
{
	$js = _record_view_counts_source('assets/js/style_designer.js');

	pg_assert_true((bool) preg_match('/var _SW_FORM_ORDER_COUNTERS = \[([^\]]*)\];/', $js, $m));
	$list = array();
	foreach (isset($m[1]) ? explode(',', $m[1]) : array() as $value) {
		$list[] = trim($value, " '\"");
	}
	pg_assert_same(pg_sw_form_order_counters(), $list);

	// Every record statistic is offered in the Statistics group of the Sort
	// selects.
	$start = strpos($js, 'function _buildOrderOpts(');
	$end = ($start === false) ? false : strpos($js, "html += '</optgroup>';", $start);
	$opts = ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
	pg_assert_contains("esc(_sdT('Statistics'))", $opts);
	foreach (pg_sw_form_order_counters() as $counter) {
		pg_assert_contains('<option value="' . $counter . '"', $opts, $counter);
	}
	pg_assert_contains("esc(_sdT('Newest Activity Date & Time'))", $opts);

	pg_assert_true((bool) preg_match('/id="sd-sw-orderby-days"[^>]*max="180"/', $js));
	$start = strpos($js, 'function _swTypeDefaults(');
	$end = ($start === false) ? false : strpos($js, "\n    function ", $start + 10);
	$defaults = ($start === false) ? '' : (($end === false) ? substr($js, $start) : substr($js, $start, $end - $start));
	pg_assert_contains('_SW_FORM_ORDER_COUNTERS.indexOf(cfg.order_by_field)', $defaults);
}

// -- where a view is registered and counted ----------------------------------------

function test_record_view_counts_placement()
{
	$page = _record_view_counts_source('get_page.php');

	$echo = strpos($page, 'echo $content;');
	$count = strpos($page, 'pg_sfv_count_view(');
	$tracking = strpos($page, 'if (VISITOR_TRACKING == true)');
	pg_assert_true($echo !== false && $count !== false && $tracking !== false && $echo < $count && $count < $tracking);
	pg_assert_contains('pg_request_is_automated()', _record_view_counts_function_body($page, 'init_tracking'));

	$widgets = _record_view_counts_source('includes/fn/widgets.php');
	$view = _record_view_counts_function_body($widgets, '_render_system_widget_form_item_view');
	$allowed = strpos($view, '$found_and_allowed = ');
	$register = strpos($view, "pg_sw_record_view_seen((int)\$form_row['id']");
	pg_assert_true($allowed !== false && $register !== false && $allowed < $register);

	// Counting stays out of the widget: a background render never reaches it.
	pg_assert_false(strpos($widgets, 'pg_sfv_count_view') !== false);
}

// The counter writer never goes through db(), which turns a failed query
// into an error screen.
function test_record_view_counts_writer_swallows_failures()
{
	$seo = _record_view_counts_source('includes/fn/seo.php');

	foreach (array('pg_sfv_count_view', 'pg_sfv_sweep_sampled') as $name) {
		$body = _record_view_counts_function_body($seo, $name);
		pg_assert_true($body !== '', $name);
		pg_assert_false((bool) preg_match('/\bdb(_value|_item|_items)?\(/', $body), $name);
	}

	pg_assert_contains('GET_LOCK(', _record_view_counts_function_body($seo, 'pg_sfv_count_view'));
	pg_assert_contains('INTERVAL 6 MONTH', _record_view_counts_function_body($seo, 'pg_sfv_sweep_sampled'));
}
