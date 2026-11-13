<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * When a bell row is read: asking for the list reads nothing; a row is read
 * when it is opened or its box is ticked (mark_read, a fold's head reading
 * its runs too), and "Mark all as read" reads only the rows the caller sees
 * (mark_all_read). The pure parts are run; the endpoint and the script are
 * pinned by source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module file holds functions only; loading it runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

function bell_read_rule_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One PHP function of a file, up to the next top-level one.
function bell_read_rule_php_function($file, $name)
{
	$source = bell_read_rule_source($file);
	$start = strpos($source, "\nfunction " . $name . '(');
	pg_assert_true($start !== false, 'found: ' . $name);
	$end = strpos($source, "\nfunction ", (int) $start + 10);
	return (string) substr($source, (int) $start, ($end === false) ? null : ($end - $start));
}

// One JavaScript function of backend.src.js, up to the next top-level one.
function bell_read_rule_js_function($name)
{
	return bell_read_rule_php_function('assets/js/backend.src.js', $name);
}

function test_bell_read_rule_run_ids_are_clean_and_leave_out_the_head()
{
	pg_assert_same(array(7, 5), pg_notification_run_ids(array('7', 5, 9, '5', 0, -3, 'x', null), 9), 'whole positive ids, once each, without the head');
	pg_assert_same(array(), pg_notification_run_ids('7,5', 9), 'only a list is read');
	pg_assert_same(array(), pg_notification_run_ids(null, 9), 'no runs');
	pg_assert_same(500, count(pg_notification_run_ids(range(1, 900), 0)), 'one request reads at most 500 runs');
}

function test_bell_read_rule_reads_only_rows_the_caller_sees()
{
	$rows = array(
		array('id' => 9, 'action' => 'workspace'),
		array('id' => '7', 'action' => 'new_order'),
		array('id' => 5, 'action' => 'workspace'),
		array('id' => 0, 'action' => 'workspace'),
		array('action' => 'workspace'),
		false
	);
	$sees = function ($row) {
		return $row['action'] === 'workspace';
	};

	pg_assert_same(array(9, 5), pg_notification_readable_ids($rows, $sees), 'a row the caller cannot see is not touched');
	pg_assert_same(array(9, 7, 5), pg_notification_readable_ids($rows, function () {
		return true;
	}), 'every row the caller sees, with a real id');
	pg_assert_same(array(), pg_notification_readable_ids(array(), $sees), 'nothing to read');
}

function test_bell_read_rule_the_list_request_reads_nothing()
{
	$get = bell_read_rule_php_function('includes/panel/notifications.php', 'pg_panel_get_notifications');

	pg_assert_false(strpos($get, 'pg_notification_mark_read') !== false, 'the list marks nothing read');
	pg_assert_false(strpos($get, 'read_mark') !== false, 'the list takes no read_mark');

	$actions = bell_read_rule_source('includes/panel/actions.php');
	pg_assert_contains("'get_notifications' => array('file' => 'notifications.php', 'handler' => 'pg_panel_get_notifications', 'exempt' => true, 'token' => true, 'write' => false)", $actions, 'the list request changes nothing');
}

function test_bell_read_rule_edit_learns_mark_read_and_mark_all_read()
{
	$edit = bell_read_rule_php_function('includes/panel/notifications.php', 'pg_panel_edit_notifications');

	pg_assert_contains("\$do_action === 'mark_all_read'", $edit, 'mark_all_read');
	pg_assert_contains("\$do_action === 'mark_read'", $edit, 'mark_read');
	pg_assert_contains("pg_notification_run_ids(\$request['runs'] ?? array(), \$id)", $edit, 'a fold head brings its runs');
	pg_assert_contains('pg_notification_readable_ids(', $edit, 'only rows the caller sees are read');
	pg_assert_contains('pg_notification_visible_to($row, $user)', $edit, 'seen as the panel sees them');
	pg_assert_same(2, substr_count($edit, 'pg_notification_mark_read('), 'both go through pg_notification_mark_read()');

	// The single row is still asked about before anything is done to it.
	$all = strpos($edit, "'mark_all_read'");
	$single = strpos($edit, 'pg_notification_visible_to($notification, $user)');
	pg_assert_true(($all !== false) && ($single !== false), 'both branches exist');
}

function test_bell_read_rule_the_script_reads_on_open()
{
	$get = bell_read_rule_js_function('get_notifications');

	pg_assert_contains('function get_notifications(keep_place)', $get, 'no read_mark argument');
	pg_assert_false(strpos($get, 'read_mark') !== false, 'the list request asks to read nothing');
	pg_assert_false(strpos($get, "\$('#notification_toggle .has-notification-icon').html('');") !== false, 'opening the bell keeps the count');
	// "Mark all as read" and "Delete All Notifications" stand in the
	// drawer's head (output.php), no longer drawn with the list.
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$at = strpos($output, '<div class="drawer-header pg-bell-head">');
	$head = ($at === false) ? '' : substr($output, $at, strpos($output, 'drawer-body', $at) - $at);
	pg_assert_false(strpos($get, 'notification-remove-btn') !== false, 'the list draws no actions');
	pg_assert_contains('pg-bell-mark-all', $head, 'Mark all as read is in the head');
	pg_assert_contains("h(lang('Mark all as read'))", $head, 'its label');
	pg_assert_true(strpos($head, 'pg-bell-mark-all') < strpos($head, 'pg-bell-delete-all'), 'Mark all as read stands before Delete All');
	pg_assert_contains('pg_bell_row(', $get, 'every row is drawn by pg_bell_row()');
	pg_assert_contains('pg_bell_read_button(', bell_read_rule_js_function('pg_bell_quick'), 'every row has Mark as read');
	pg_assert_contains('pg_bell_quick(', bell_read_rule_js_function('pg_bell_row'), 'in its quick actions');
	pg_assert_contains('pg_bell_quick(', bell_read_rule_js_function('pg_bell_run'), 'a run too');
	pg_assert_contains('var load = ++pgBellLoad;', $get, 'each list request is numbered');
	pg_assert_contains('if (load !== pgBellLoad) {', $get, 'only the latest answer is drawn, so one opening draws the list once');

	$box = bell_read_rule_js_function('pg_bell_read_button');
	pg_assert_contains("lang('Mark as read')", $box, 'an unread row offers Mark as read');
	pg_assert_contains("lang('Mark as unread')", bell_read_rule_js_function('pg_bell_quick'), 'a read row offers Mark as unread in its menu');

	$open = bell_read_rule_js_function('pg_bell_open_row');
	pg_assert_contains("edit_notification(notification_id, 'mark_read'", $open, 'opening a row reads it first');
	pg_assert_contains('complete: function', bell_read_rule_js_function('edit_notification'), 'the page opens once the read is answered, either way');

	$js = bell_read_rule_source('assets/js/backend.src.js');
	pg_assert_contains("pg_bell_open_row(\$(this), function () {", $js, 'the row link opens through the read');
	pg_assert_contains("edit_notification(0, 'mark_all_read')", $js, 'the top button reads everything the caller sees');
}

function test_bell_read_rule_edit_turns_the_row_both_ways()
{
	$edit = bell_read_rule_js_function('edit_notification');

	pg_assert_contains("do_action === 'mark_read'", $edit, 'read');
	pg_assert_contains("do_action === 'mark_unread'", $edit, 'unread');
	pg_assert_contains("do_action === 'mark_all_read'", $edit, 'all read');
	pg_assert_contains('runs: runs', $edit, 'a fold head sends its runs');
	pg_assert_contains("li[data-pg-bell-fold=\"", $edit, 'the runs are the rows folded under it');
	pg_assert_contains('pg_bell_row_state(', $edit, 'the row and its box change together');
	pg_assert_contains("check_unread_notifications('editcallback_check');", $edit, 'the bell count follows');
}
