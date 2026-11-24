<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel bell's notices against who may see them, how often they come and
 * when they are read: a Workspace row whose inbox line its person may no
 * longer see is left out, a queued device banner asks the ladder again at
 * send time, a line the Workspace reads by hiding its message or by an
 * acknowledgement is read in the bell, a marketplace order announces a
 * product out of stock once, and the day heading and short time follow the
 * reader's own zone. The pure parts are run; the wiring is pinned by source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

// One PHP function of a file, up to the next top-level one.
function notification_bell_rights_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $name . '(');
	pg_assert_true($start !== false, 'found: ' . $name);
	$end = strpos($source, "\nfunction ", (int) $start + 10);

	return (string) substr($source, (int) $start, ($end === false) ? null : ($end - $start));
}

// A Workspace row is shown only while its inbox line is the person's to see,
// with the Workspace on and with it off.
function test_notification_bell_workspace_row_asks_its_line()
{
	$ladder = notification_bell_rights_function('includes/notifications.php', 'pg_notification_visible');

	pg_assert_contains("return pg_notification_ws_line_open(\$target, (int) (\$notification['reference_id'] ?? 0));", $ladder, 'Workspace on');
	pg_assert_contains("&& pg_notification_ws_line_open(\$target, (int) (\$notification['reference_id'] ?? 0));", $ladder, 'Workspace off, a flow or mail line');

	$open = notification_bell_rights_function('includes/notifications.php', 'pg_notification_ws_line_open');

	pg_assert_contains('ws_inbox_row_visible($viewer, $row)', $open, 'the inbox\'s own rule');
	pg_assert_contains("AND user_id = '\" . \$user_id . \"'", $open, 'only the person\'s own line');
	pg_assert_contains('$open[$key] = true;', $open, 'a line that is gone keeps the row');

	// No line to ask about: nothing is read and the row is not refused here.
	pg_assert_true(pg_notification_ws_line_open(0, 9), 'nobody addressed');
	pg_assert_true(pg_notification_ws_line_open(5, 0), 'no inbox line');
}

// The push queue asks the ladder again for the person it is about to wake.
function test_notification_bell_push_asks_again_at_send()
{
	$relevant = notification_bell_rights_function('includes/push.php', 'pg_push_queue_still_relevant');

	pg_assert_contains('SELECT " . pg_notification_ladder_columns() . ", readed FROM notifications', $relevant, 'the columns the ladder reads');
	pg_assert_contains('pg_notification_visible_to_user($notification, $user_row)', $relevant, 'visible to them now');
	pg_assert_contains('pg_notification_is_read($notification, $user_id)', $relevant, 'still unread');
}

// A line the Workspace marks read is read in the bell as well.
function test_notification_bell_workspace_reads_reach_the_bell()
{
	pg_assert_contains('ws_bell_sync_read((int) $viewer[\'id\']);', notification_bell_rights_function('includes/workspace/messages.php', 'ws_message_hide'), 'hiding a message');
	pg_assert_contains('ws_bell_sync_read((int) $viewer[\'id\']);', notification_bell_rights_function('includes/workspace/acks.php', 'ws_ack_set'), 'an acknowledgement');
	pg_assert_contains('ws_bell_sync_read((int) $viewer[\'id\']);', notification_bell_rights_function('includes/workspace/notify.php', 'ws_inbox_visible_rows'), 'a line gone from reach');
}

// A marketplace order announces a product out of stock once, as a web order.
function test_notification_bell_marketplace_out_of_stock_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
	$at = strpos($source, "'action'     => 'out_stock',");

	pg_assert_true($at !== false, 'the warning is there');

	$before = substr($source, max(0, (int) $at - 700), 700);

	pg_assert_contains("AND (out_of_stock != '1')", $before, 'the flag is guarded');
	pg_assert_contains("if ((mysqli_affected_rows(db::\$con) > 0) && function_exists('create_notification')) {", $before, 'the warning follows the flag');
}

// 23:30 UTC on the 23rd, seen at 01:00 UTC on the 24th: yesterday in UTC,
// today in Istanbul (02:30 and 04:00 on the 24th).
function test_notification_bell_day_in_the_readers_zone()
{
	$that = gmmktime(23, 30, 0, 11, 23, 2026);
	$now = gmmktime(1, 0, 0, 11, 24, 2026);

	pg_assert_same('yesterday', pg_notification_day($that, $now, 'UTC'), 'UTC');
	pg_assert_same('today', pg_notification_day($that, $now, 'Europe/Istanbul'), 'Istanbul');
	pg_assert_same(lang('Yesterday'), pg_notification_short_time($that, $now, 'UTC'), 'the short time follows: UTC');
	pg_assert_same(lang(array('string' => '{var:1} h', 'vars' => array(1))), pg_notification_short_time($that, $now, 'Europe/Istanbul'), 'the short time follows: Istanbul');
}
