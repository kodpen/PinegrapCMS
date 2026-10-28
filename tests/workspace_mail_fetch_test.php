<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading a Workspace mailbox over IMAP
 * (includes/workspace/mail_fetch.php): what is searched on a first read, a
 * renumbered folder and an ordinary one, which UIDs are looked at, where the
 * position stops (held and unread mail are read again), the Message-ID taken
 * from a header block, what each ingest answer means for the mailbox, and
 * the job in the catalogue.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');

function test_workspace_mail_fetch_plan()
{
	$added = gmmktime(10, 0, 0, 10, 28, 2026);
	$box = array('uidvalidity' => 0, 'last_uid' => 0, 'created_at' => $added, 'ok_at' => 0);

	pg_assert_same(array('reset' => true, 'criteria' => 'SINCE 27-Oct-2026'), ws_mail_fetch_plan($box, array('uidvalidity' => 1700)), 'a first read from the day before the box was added');

	$box = array('uidvalidity' => 1700, 'last_uid' => 41, 'created_at' => $added, 'ok_at' => gmmktime(9, 0, 0, 11, 5, 2026));

	pg_assert_same(array('reset' => false, 'criteria' => 'UID 42:*'), ws_mail_fetch_plan($box, array('uidvalidity' => 1700)), 'past the last UID');
	pg_assert_same(array('reset' => true, 'criteria' => 'SINCE 4-Nov-2026'), ws_mail_fetch_plan($box, array('uidvalidity' => 1800)), 'a renumbered folder from the day before the last good read');
	pg_assert_same('SINCE 27-Oct-2026', ws_mail_fetch_plan(array_merge($box, array('ok_at' => 0)), array('uidvalidity' => 1800))['criteria'], 'never read well: from the box\'s day');
}

function test_workspace_mail_fetch_candidates()
{
	pg_assert_same(array(), ws_mail_fetch_candidates(array(41), false, 41), '"UID 42:*" answering the newest mail is dropped');
	pg_assert_same(array(42, 45), ws_mail_fetch_candidates(array(45, 42, 41, 42), false, 41));
	pg_assert_same(array(3, 9), ws_mail_fetch_candidates(array(9, 3), true, 500), 'a fresh position takes what the date search found');
	pg_assert_same(WS_MAIL_FETCH_LOOK, count(ws_mail_fetch_candidates(range(1, 300), false, 0)), 'at most a look\'s worth');
	pg_assert_same(1, ws_mail_fetch_candidates(range(1, 300), false, 0)[0], 'the oldest first');
}

function test_workspace_mail_fetch_cursor()
{
	pg_assert_same(44, ws_mail_fetch_cursor(41, false, 50, array(42 => 'done', 44 => 'done', 43 => 'done')), 'past a row of finished mail');
	pg_assert_same(42, ws_mail_fetch_cursor(41, false, 50, array(42 => 'done', 43 => 'held', 44 => 'done')), 'held mail keeps the position before it');
	pg_assert_same(42, ws_mail_fetch_cursor(41, false, 50, array(42 => 'done', 43 => 'waiting')), 'mail not reached is read next time');
	pg_assert_same(41, ws_mail_fetch_cursor(41, false, 50, array()), 'nothing new');
	pg_assert_same(49, ws_mail_fetch_cursor(0, true, 50, array()), 'a fresh position with nothing found starts at the newest mail');
	pg_assert_same(8, ws_mail_fetch_cursor(0, true, 50, array(9 => 'held', 12 => 'done')), 'a fresh position stops before a held first mail');
	pg_assert_same(12, ws_mail_fetch_cursor(0, true, 50, array(9 => 'done', 12 => 'done')));
	pg_assert_same(0, ws_mail_fetch_cursor(0, true, 0, array()), 'an empty folder');
}

function test_workspace_mail_fetch_message_id_and_outcome()
{
	pg_assert_same('a.1@example.com', ws_mail_fetch_message_id("Message-ID:\r\n <a.1@example.com>\r\n\r\n"), 'folded');
	pg_assert_same('', ws_mail_fetch_message_id("\r\n"), 'none');
	pg_assert_same(ws_mail_hash('a.1@example.com', 'whole mail'), ws_mail_hash(ws_mail_fetch_message_id("Message-ID: <a.1@example.com>\r\n"), ''), 'the same key the ingest keeps');

	pg_assert_same('taken', ws_mail_fetch_outcome(array('status' => 'stored', 'reason' => '')));
	pg_assert_same('taken', ws_mail_fetch_outcome(array('status' => 'duplicate', 'reason' => '')));
	pg_assert_same('taken', ws_mail_fetch_outcome(array('status' => 'loop', 'reason' => 'auto')));
	pg_assert_same('taken', ws_mail_fetch_outcome(array('status' => 'refused', 'reason' => 'no_sender')));
	pg_assert_same('held', ws_mail_fetch_outcome(array('status' => 'held', 'reason' => '')));
	pg_assert_same('stop', ws_mail_fetch_outcome(array('status' => 'refused', 'reason' => 'no_channel')), 'a box without its channel stops');
	pg_assert_same('stop', ws_mail_fetch_outcome(array('status' => 'refused', 'reason' => 'not_saved')));
}

function test_workspace_mail_fetch_job_is_in_the_catalogue()
{
	$job = pg_cron_jobs()['workspace_mail_job'] ?? null;

	pg_assert_true(is_array($job), 'listed');
	pg_assert_same('workspace_mail_job.php', $job['script']);
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $job['script']), 'its script exists');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_mail_job.php');

	pg_assert_contains("pg_cron_ran('workspace_mail_job');", $script, 'it records its run');
	pg_assert_contains("validate_area_access(\$user, 'manager');", $script, 'a web request needs a manager');

	pg_assert_true(!empty($job['inline']), 'the general job reads the boxes itself');
	pg_assert_true(empty($job['dispatch']), 'no switch and no turn in the rotation');

	$general = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	pg_assert_contains('ws_mail_fetch_run(', $general, 'job.php reads the boxes');
	pg_assert_contains("pg_cron_ran('workspace_mail_job');", $general, 'and records the run');
}

function test_workspace_mail_fetch_health()
{
	$now = gmmktime(12, 0, 0, 11, 5, 2026);
	$server = json_encode(array('host' => 'imap.example.com', 'port' => 993));
	$box = function ($id, $over) use ($now, $server) {
		return array_merge(array('id' => $id, 'name' => 'Box ' . $id, 'address' => 'b' . $id . '@example.com', 'transport' => 'imap', 'enabled' => 1,
			'settings' => $server, 'last_error' => '', 'ok_at' => $now - 60, 'checked_at' => $now - 60, 'created_at' => $now - 86400), $over);
	};

	$none = ws_mail_fetch_health(array($box(1, array('settings' => '{}')), $box(2, array('enabled' => 0)), $box(3, array('transport' => 'push'))), $now);
	pg_assert_same('none', $none['state'], 'no box with a server that is on: no tile');
	pg_assert_same(array(), $none['rows']);

	$ok = ws_mail_fetch_health(array($box(1, array()), $box(2, array('ok_at' => 0, 'checked_at' => 0, 'created_at' => $now - 600))), $now);
	pg_assert_same('ok', $ok['state'], 'read a minute ago, and one added ten minutes ago');
	pg_assert_same(2, $ok['on']);

	$warn = ws_mail_fetch_health(array($box(1, array()), $box(2, array('ok_at' => $now - 7200, 'checked_at' => $now - 7200))), $now);
	pg_assert_same('warn', $warn['state'], 'not read for two hours');
	pg_assert_same(1, $warn['stale']);
	pg_assert_same('warn', ws_mail_fetch_health(array($box(1, array('ok_at' => 0, 'checked_at' => 0))), $now)['state'], 'added a day ago, never read');

	$large = ws_mail_fetch_health(array($box(1, array('last_error' => 'too_large|'))), $now);
	pg_assert_same('warn', $large['state'], 'a mail left in the box');
	pg_assert_same(0, $large['stale']);
	pg_assert_same('too_large', $large['rows'][0]['error']);

	$fail = ws_mail_fetch_health(array($box(1, array('ok_at' => $now - 7200)), $box(2, array('last_error' => 'login|AUTHENTICATIONFAILED Invalid credentials'))), $now);
	pg_assert_same('fail', $fail['state'], 'a failing box wins');
	pg_assert_same(1, $fail['failing']);
	pg_assert_same('login', $fail['rows'][1]['error']);
	pg_assert_same('AUTHENTICATIONFAILED Invalid credentials', $fail['rows'][1]['detail']);
	pg_assert_same('imap.example.com', $fail['rows'][1]['host']);
}
