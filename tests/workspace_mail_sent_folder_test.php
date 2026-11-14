<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's Sent folder (includes/workspace/mail_sent_folder.php):
 * the 2026.11.0 step 11.0.200 run twice against an in-memory schema, when
 * the folder is read, where its read starts, what a sent mail changes on its
 * conversation, how its customer is recognised, when the box's save forgets
 * the position, how an external mail reads (sent, not an automatic answer)
 * and the wiring into the mail job and the box form. No database, no IMAP.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_sent_folder.php');

function test_workspace_mail_sent_folder_step_is_called_once_under_a_label_of_its_own()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_sent_folder();     // 11.0.200\n"), 'called once under 11.0.200');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

// Runs the step twice in a child process: the fake db layer answers the
// runner's probes from an array, applies MODIFY, ADD and ADD INDEX to it and
// logs every write. Null when PHP cannot be started again.
function workspace_mail_sent_folder_test_run_twice($schema)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmailsent');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_writes'] = array();
$GLOBALS['fake_indexes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]]);
		}
		return array();
	}
	if (preg_match("/^SHOW INDEX FROM `([^`]+)` WHERE Key_name = '([^']+)'$/", $sql, $m)) {
		return isset($GLOBALS['fake_indexes'][$m[1]][$m[2]]) ? array('Key_name' => $m[2]) : array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD INDEX ([a-z_]+) /', $sql, $m)) {
		$GLOBALS['fake_indexes'][$m[1]][$m[2]] = true;
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` (ADD|MODIFY) `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[3]] = strtolower($m[4]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_sent_folder();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema'], 'indexes' => $GLOBALS['fake_indexes']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'fake_schema\'] = ' . var_export($schema, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_sent_folder_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_sent_folder_test_run_twice(array(
		'ws_mail_boxes' => array('id' => 'int', 'uidvalidity' => 'int', 'last_uid' => 'int'),
	));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(2, count($result['runs'][0]), 'the first run adds the two columns');
	pg_assert_same(array(), $result['runs'][1], 'the second run writes nothing');
	pg_assert_same('int', $result['schema']['ws_mail_boxes']['sent_uidvalidity'], 'the folder number');
	pg_assert_same('int', $result['schema']['ws_mail_boxes']['sent_last_uid'], 'the position');

	$none = workspace_mail_sent_folder_test_run_twice(array());
	pg_assert_true(!isset($none['error']), 'without the mailbox table it runs');
	pg_assert_same(array(array(), array()), isset($none['runs']) ? $none['runs'] : null, 'and writes nothing');
}

function test_workspace_mail_sent_folder_due()
{
	pg_assert_true(ws_mail_sent_due(array('read_sent' => 1), true, 10.0), 'switched on, ready, time left');
	pg_assert_true(!ws_mail_sent_due(array(), true, 10.0), 'off by default');
	pg_assert_true(!ws_mail_sent_due(array('read_sent' => 0), true, 10.0), 'switched off');
	pg_assert_true(!ws_mail_sent_due(array('read_sent' => 1), false, 10.0), 'before the step');
	pg_assert_true(!ws_mail_sent_due(array('read_sent' => 1), true, WS_MAIL_SENT_LEFT - 0.5), 'too little of the session left');
}

function test_workspace_mail_sent_folder_plan_starts_at_the_present()
{
	$first = ws_mail_sent_plan(array('sent_uidvalidity' => 0, 'sent_last_uid' => 0), array('uidvalidity' => 77, 'uidnext' => 501));
	pg_assert_true($first['reset'], 'a first read starts again');
	pg_assert_same('', $first['criteria'], 'and searches nothing');
	pg_assert_same(500, $first['last_uid'], 'its position is the newest mail: nothing older comes in');

	$renumbered = ws_mail_sent_plan(array('sent_uidvalidity' => 76, 'sent_last_uid' => 900), array('uidvalidity' => 77, 'uidnext' => 12));
	pg_assert_true($renumbered['reset'], 'a renumbered folder starts again');
	pg_assert_same(11, $renumbered['last_uid'], 'at its newest mail');

	$next = ws_mail_sent_plan(array('sent_uidvalidity' => 77, 'sent_last_uid' => 500), array('uidvalidity' => 77, 'uidnext' => 505));
	pg_assert_true(!$next['reset'], 'a known folder goes on');
	pg_assert_same('UID 501:*', $next['criteria'], 'past the position');
	pg_assert_same(500, $next['last_uid'], 'from the stored position');

	pg_assert_same(500, ws_mail_fetch_cursor(500, false, 505, array(501 => 'waiting', 502 => 'done')), 'a mail not reached keeps the position before it');
	pg_assert_same(502, ws_mail_fetch_cursor(500, false, 505, array(501 => 'done', 502 => 'done')), 'finished mail moves it');
}

function test_workspace_mail_sent_folder_conversation_changes()
{
	$conversation = array('status' => 'open', 'last_in_at' => 1000, 'last_out_at' => 0, 'first_reply_at' => 0);

	pg_assert_same(array('last_out_at' => 1500, 'first_reply_at' => 1500, 'status' => 'waiting', 'updated_at' => 2000),
		ws_mail_sent_conversation_changes($conversation, 1500, 2000), 'an answer after the customer: answered and waiting');

	pg_assert_same(array('last_out_at' => 900, 'first_reply_at' => 900, 'updated_at' => 2000),
		ws_mail_sent_conversation_changes($conversation, 900, 2000), 'an older mail does not set it waiting');

	pg_assert_same(2000, ws_mail_sent_conversation_changes($conversation, 0, 2000)['last_out_at'], 'no Date: the time it was read');
	pg_assert_same(2000, ws_mail_sent_conversation_changes($conversation, 99999, 2000)['last_out_at'], 'a Date in the future: the time it was read');

	$closed = array('status' => 'closed', 'last_in_at' => 1000, 'last_out_at' => 1200, 'first_reply_at' => 1100);
	pg_assert_same(array('last_out_at' => 1500, 'updated_at' => 2000), ws_mail_sent_conversation_changes($closed, 1500, 2000), 'closed stays closed, the first answer stays');

	$later = array('status' => 'open', 'last_in_at' => 1000, 'last_out_at' => 1800, 'first_reply_at' => 1100);
	pg_assert_same(1800, ws_mail_sent_conversation_changes($later, 1500, 2000)['last_out_at'], 'the newest answer time never goes back');
}

function test_workspace_mail_sent_folder_customer_among_recipients()
{
	$parsed = array('to' => array(array('email' => 'Ali@Example.com')), 'cc' => array(array('email' => 'b@example.com')));

	pg_assert_true(ws_mail_sent_to_customer('ali@example.com', $parsed), 'To, case ignored');
	pg_assert_true(ws_mail_sent_to_customer('b@example.com', $parsed), 'Cc');
	pg_assert_true(ws_mail_sent_to_customer('c@example.com', array('bcc' => array(array('email' => 'c@example.com')))), 'Bcc');
	pg_assert_true(!ws_mail_sent_to_customer('d@example.com', $parsed), 'somebody else');
	pg_assert_true(!ws_mail_sent_to_customer('', $parsed), 'a conversation without an address');
}

function test_workspace_mail_sent_folder_position_resets_on_save()
{
	pg_assert_true(ws_mail_sent_position_resets(array('read_sent' => 1), array('read_sent' => 0)), 'switched off');
	pg_assert_true(ws_mail_sent_position_resets(array(), array()), 'never on');
	pg_assert_true(!ws_mail_sent_position_resets(array('read_sent' => 1, 'sent_folder' => 'Sent'), array('read_sent' => 1, 'sent_folder' => 'Sent')), 'nothing changed: it goes on');
	pg_assert_true(ws_mail_sent_position_resets(array('read_sent' => 1, 'sent_folder' => 'Sent'), array('read_sent' => 1, 'sent_folder' => 'Sent Items')), 'another folder');
	pg_assert_true(!ws_mail_sent_position_resets(array('read_sent' => 0), array('read_sent' => 1, 'sent_folder' => 'X')), 'switched on: already at 0');
}

function test_workspace_mail_sent_folder_external_reads_as_sent()
{
	$external = array('direction' => 'out', 'send_state' => 'external', 'user_id' => 0);

	pg_assert_same('external', ws_mail_send_state($external), 'named as itself');
	pg_assert_true(!ws_mail_item_is_auto_reply($external), 'not the automatic answer');
	pg_assert_true(ws_mail_item_is_auto_reply(array('direction' => 'out', 'send_state' => 'sent', 'user_id' => 0)), 'the automatic answer still is');
	pg_assert_same(ws_mail_send_badge_html(array('direction' => 'out', 'send_state' => 'sent', 'user_id' => 1)), ws_mail_send_badge_html($external), 'the badge of a sent mail');
	pg_assert_true(ws_mail_send_state($external) !== 'failed', 'Send again is offered only for failed');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_list.php');
	pg_assert_contains("('', 'sent', 'external')", $list, 'the Sent view counts it');
}

function test_workspace_mail_sent_folder_wiring()
{
	$fetch = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');
	pg_assert_contains("require_once(__DIR__ . '/mail_sent_folder.php');", $fetch, 'the fetch loads it');
	pg_assert_contains('ws_mail_sent_read($session, $box, $settings)', $fetch, 'after the box\'s own folder');
	pg_assert_contains('sent_last_uid', $fetch, 'and keeps its position');

	$settings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains('name="read_sent"', $settings, 'the switch on the box form');
	pg_assert_contains("'read_sent', 'connection_id'", $settings, 'kept in the settings');

	$box = ws_mail_box_input(array('name' => 'S', 'address' => 's@example.com', 'read_sent' => '1'));
	pg_assert_same(1, $box['values']['read_sent'], 'ticked');
	$box = ws_mail_box_input(array('name' => 'S', 'address' => 's@example.com'));
	pg_assert_same(0, $box['values']['read_sent'], 'off by default');
}
