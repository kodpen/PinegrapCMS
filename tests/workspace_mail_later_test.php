<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Send later (includes/workspace/mail_later.php): the typed time
 * read in the writer's time zone, the limits, the hour a zone skips. No
 * database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_mail_later_parse_reads_the_writers_zone()
{
	$now = gmmktime(12, 0, 0, 11, 14, 2026);

	$istanbul = ws_mail_later_parse('2026-11-20T09:30', 'Europe/Istanbul', $now);
	pg_assert_same(array(true, gmmktime(6, 30, 0, 11, 20, 2026)), array($istanbul['ok'], $istanbul['at']), '09:30 in Istanbul is 06:30 UTC');

	$berlin = ws_mail_later_parse('2026-11-20 09:30:45', 'Europe/Berlin', $now);
	pg_assert_same(gmmktime(8, 30, 0, 11, 20, 2026), $berlin['at'], 'a space and seconds; seconds dropped; Berlin in winter');

	pg_assert_same(gmmktime(9, 30, 0, 11, 20, 2026), ws_mail_later_parse('2026-11-20T09:30', '', $now)['at'], 'no zone reads as UTC');
	pg_assert_same(gmmktime(9, 30, 0, 11, 20, 2026), ws_mail_later_parse('2026-11-20T09:30', 'Mars/Olympus', $now)['at'], 'an unknown zone reads as UTC');
}

function test_workspace_mail_later_parse_refuses()
{
	$now = gmmktime(12, 0, 0, 11, 14, 2026);

	pg_assert_same('missing', ws_mail_later_parse('', 'UTC', $now)['error'], 'nothing typed');
	pg_assert_same('missing', ws_mail_later_parse(array('x'), 'UTC', $now)['error'], 'not text');
	pg_assert_same('format', ws_mail_later_parse('20.11.2026 09:30', 'UTC', $now)['error'], 'another format');
	pg_assert_same('format', ws_mail_later_parse('2026-02-30T09:30', 'UTC', $now)['error'], 'a day that does not exist');
	pg_assert_same('format', ws_mail_later_parse('2027-03-28T02:30', 'Europe/Berlin', $now)['error'], 'the hour Berlin skips in spring');
	pg_assert_same('past', ws_mail_later_parse('2026-11-14T11:00', 'UTC', $now)['error'], 'in the past');
	pg_assert_same('past', ws_mail_later_parse('2026-11-14T12:04', 'UTC', $now)['error'], 'less than five minutes ahead');
	pg_assert_true(ws_mail_later_parse('2026-11-14T12:05', 'UTC', $now)['ok'], 'five minutes ahead');
	pg_assert_true(ws_mail_later_parse('2027-01-13T12:00', 'UTC', $now)['ok'], 'sixty days ahead');
	pg_assert_same('too_far', ws_mail_later_parse('2027-01-13T12:01', 'UTC', $now)['error'], 'past sixty days');

	foreach (array('missing', 'format', 'past', 'too_far', 'other') as $error) {
		pg_assert_true(ws_mail_later_error_text($error) !== '', $error . ' is told');
	}
}

function workspace_mail_later_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_later_step_is_called_once_under_a_label_of_its_own()
{
	$source = workspace_mail_later_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_send_later();      // 11.0.207\n"), 'called once under 11.0.207');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');

	$step = substr($source, (int) strpos($source, 'function upgrade_2026_11_0_ws_mail_send_later'));
	pg_assert_contains("install_add_column('ws_mail_items', 'send_at', \"INT UNSIGNED NOT NULL DEFAULT 0\");", $step, 'the column as the owner approved it');
	pg_assert_contains("install_add_index('ws_mail_items', 'idx_later', \"INDEX idx_later (send_state, send_at)\");", $step, 'and its index');
	pg_assert_true(strpos($step, 'db(') === false, 'only the runner helpers');
}

// Runs the step twice in a child process against an in-memory schema.
function workspace_mail_later_test_run_twice($schema)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmaillater');

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
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_send_later();
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

function test_workspace_mail_later_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_later_test_run_twice(array('ws_mail_items' => array('id' => 'int', 'send_state' => 'varchar(8)')));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(2, count($result['runs'][0]), 'the first run adds the column and the index');
	pg_assert_same(array(), $result['runs'][1], 'the second run writes nothing');
	pg_assert_same('int', $result['schema']['ws_mail_items']['send_at'], 'send_at');
	pg_assert_true(!empty($result['indexes']['ws_mail_items']['idx_later']), 'idx_later');

	$none = workspace_mail_later_test_run_twice(array());
	pg_assert_same(array(array(), array()), isset($none['runs']) ? $none['runs'] : null, 'without the table it writes nothing');
}

function test_workspace_mail_later_state_badge_and_tools()
{
	pg_assert_same('later', ws_mail_send_state(array('direction' => 'out', 'send_state' => 'later')), 'a waiting mail is later, never sent');
	pg_assert_contains('Sends at', ws_mail_send_badge_html(array('direction' => 'out', 'send_state' => 'later', 'send_at' => time() + 3600)), 'its badge');

	$item = array('id' => 7, 'send_state' => 'later');
	pg_assert_contains('data-ws-mail-later-send="7"', ws_mail_later_tools_html($item, true), 'Send now');
	pg_assert_contains('data-ws-mail-later-cancel="7"', ws_mail_later_tools_html($item, true), 'Cancel');
	pg_assert_same('', ws_mail_later_tools_html($item, false), 'not for who may not answer');
	pg_assert_same('', ws_mail_later_tools_html(array('id' => 7, 'send_state' => 'queued'), true), 'not for a mail on its way');

	pg_assert_same('queued', ws_mail_send_first_state(array('send_at' => 0)), 'no time: queued');
	pg_assert_same(array('ok' => true, 'error' => '', 'at' => 0), ws_mail_later_request(array(), time()), 'no time asked');
	pg_assert_same(array('ok' => true, 'error' => '', 'at' => 0), ws_mail_later_request(array('send_later' => '  '), time()), 'an empty field');
}

function test_workspace_mail_later_wiring()
{
	$send = workspace_mail_later_test_source('includes/workspace/mail_send.php');
	pg_assert_same(2, substr_count($send, '" . ws_mail_send_first_state($check) . "'), 'a new mail and an answer are written later when timed');
	pg_assert_same(2, substr_count($send, '? ws_mail_send_hand_on($item, $check)'), 'and kept from the queue');
	pg_assert_contains("\$check['send_at'] = max(0, (int) (\$mail['send_at'] ?? 0));", $send, 'the time reaches the write');

	$release = workspace_mail_later_test_source('includes/workspace/mail_later.php');
	pg_assert_contains("SET send_state = 'queued', send_at = 0", $release, 'claimed before it is handed on');
	pg_assert_contains("AND send_state = '\" . WS_MAIL_LATER_STATE . \"'\");\n\n    if (mysqli_affected_rows(db::\$con) !== 1)", $release, 'only by one runner');

	pg_assert_contains("\$where[] = \$out . \"('queued', 'failed', 'later'))\";", workspace_mail_later_test_source('includes/workspace/mail_list.php'), 'Not sent lists the waiting mails');
	pg_assert_contains('ws_mail_later_run(time());', workspace_mail_later_test_source('job.php'), 'the general job hands them on');

	$actions = workspace_mail_later_test_source('includes/workspace/actions.php');
	pg_assert_same(2, substr_count($actions, '$later = ws_mail_later_request($request, time());'), 'New e-mail and Reply by e-mail read the time');

	foreach (array('ws_mail_later_send', 'ws_mail_later_cancel') as $action) {
		pg_assert_true(in_array($action, ws_mail_actions_without_workspace(), true), $action . ' works with the Workspace off');
		pg_assert_contains("'" . $action . "'", workspace_mail_later_test_source('includes/panel/actions.php'), $action . ' is a panel action');
	}

	$js = workspace_mail_later_test_source('assets/js/workspace_mail.js');
	pg_assert_same(2, substr_count($js, 'post.send_later = later.value();'), 'both forms post the time');
	pg_assert_contains('[data-ws-mail-later-send], [data-ws-mail-later-cancel]', $js, 'the pane\'s buttons');
}

function test_workspace_mail_later_look_conditions()
{
	$js = workspace_mail_later_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("relabel(h, send, on ? t('mail_later') : t('mail_reply_send'), on ? 'bi-clock' : 'bi-send');", $js, 'the drawer\'s Send says Send later while a time is set');
	pg_assert_contains("relabel(h, send, on ? t('mail_later') : t('mail_new_send'), on ? 'bi-clock' : 'bi-send');", $js, 'and the New e-mail form\'s');
	pg_assert_same('Remove the time', ws_mail_later_js_strings()['mail_later_none'], 'the link that closes the field names what it does');
	pg_assert_contains("ws_mail_later_time_text((int) (\$item['send_at'] ?? 0))", workspace_mail_later_test_source('includes/workspace/mail_later.php'), 'the badge writes the time as the panel does');
	pg_assert_true(strpos(ws_mail_later_time_text(gmmktime(9, 30, 0, 11, 20, 2026)), '2026') !== false, 'a date with its year');
}

function test_workspace_mail_later_row_alone_is_teal()
{
	$row = array('id' => 4, 'subject' => 'Offer', 'customer_name' => 'Ayşe', 'customer_email' => 'a@b.test', 'last_in_at' => 0, 'last_out_at' => time(),
		'mails' => 1, 'snippet' => '', 'unread' => false, 'unsent' => 'later', 'box_name' => 'Support', 'channel_name' => '', 'support' => false,
		'transport' => 'email', 'overdue' => false, 'assignee_name' => '', 'first_reply_hours' => 0, 'customer_phone' => '');
	$html = ws_mail_list_rows_html(array($row), ws_mail_list_filters(array('view' => 'unsent')), time());

	pg_assert_contains('<span class="badge theme-info">Sends later</span>', $html, 'a conversation whose only unsent mail waits carries the teal pill');
	pg_assert_true(strpos($html, 'theme-danger') === false, 'never the red one');
}

// At its time a waiting mail leaves only while its writer may still answer
// where it is kept; otherwise it is held and turns back into its draft.
function test_workspace_mail_later_fate_reads_the_writer_as_now()
{
	pg_assert_same('leave', ws_mail_later_fate(7, true), 'the writer may still answer there');
	pg_assert_same('hold', ws_mail_later_fate(7, false), 'taken out of the channel or the room, or off the team');
	pg_assert_same('hold', ws_mail_later_fate(0, true), 'no writer: an account that is gone');
}

function test_workspace_mail_later_held_people()
{
	pg_assert_same(array(7, 9), ws_mail_later_held_people(7, 9, array(3, 4), false, array()), 'the writer and the conversation\'s holder');
	pg_assert_same(array(7, 3, 4), ws_mail_later_held_people(7, 0, array(3, 4), false, array(5)), 'nobody holds it: the channel\'s owners');
	pg_assert_same(array(7, 3), ws_mail_later_held_people(7, 7, array(7, 3), false, array()), 'the writer holds it: the owners, the writer once');
	pg_assert_same(array(7, 5), ws_mail_later_held_people(7, 0, array(3), true, array(5)), 'a mailbox\'s own room: its members with the Workspace settings right');
	pg_assert_same(array(4), ws_mail_later_held_people(0, 0, array(4), false, array()), 'a writer that is gone is not told');
}

function test_workspace_mail_later_hold_wiring()
{
	$later = workspace_mail_later_test_source('includes/workspace/mail_later.php');
	$run = pg_test_function_source('ws_mail_later_run');
	pg_assert_true(strpos($run, 'ws_mail_later_fate(') !== false && strpos($run, 'ws_mail_later_fate(') < strpos($run, 'ws_mail_later_release('), 'the job asks before it hands a mail on');
	pg_assert_contains('ws_rights_for_id((int) $item[\'user_id\'])', $run, 'the writer\'s rights as they are now');
	pg_assert_contains('ws_mail_later_to_draft($item, false)', pg_test_function_source('ws_mail_undo_send'), 'Undo turns it into its draft');
	pg_assert_contains('ws_mail_later_to_draft($item, true)', pg_test_function_source('ws_mail_later_hold'), 'the job too, its files kept');
	pg_assert_contains("ws_notify(\$user_id, 'mail_held'", $later, 'the inbox line');

	pg_assert_true(in_array('mail_held', pg_notification_mail_kinds(), true), 'the bell shows it with the Workspace off');
	pg_assert_contains("case 'mail_held':", workspace_mail_later_test_source('includes/workspace/notify.php'), 'described');
	pg_assert_contains('mail_held (an e-mail', workspace_mail_later_test_source('includes/workspace/api.php'), 'named for applications');
	pg_assert_contains("AND i.send_state = '\" . WS_MAIL_DRAFT_STATE . \"'", pg_test_function_source('ws_mail_send_file_reach'), 'a held draft\'s files can be sent again');
}
