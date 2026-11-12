<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail queue's outcome hook in includes/fn/mail_queue.php: a
 * row names a handler (on_outcome) and a reference (outcome_ref); after the
 * row is sent, given up or blocked the queue resolves the name through
 * pg_mail_outcome_handlers() and calls the handler once, and nothing a
 * handler does stops the run. Read from the source: the four places in
 * pg_mail_queue_run() call it after the row's status is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

// What the test handler was called with, one entry a call.
function mail_queue_outcome_test_calls($add = null)
{
	static $calls = array();

	if ($add === 'reset') {
		$calls = array();
	} elseif (is_array($add)) {
		$calls[] = $add;
	}

	return $calls;
}

function mail_queue_outcome_test_handler($outbox_id, $outcome, $ref, $error, $mime)
{
	mail_queue_outcome_test_calls(array($outbox_id, $outcome, $ref, $error, $mime));
}

function mail_queue_outcome_test_thrower($outbox_id, $outcome, $ref, $error, $mime)
{
	mail_queue_outcome_test_calls(array('threw', $outbox_id));

	throw new RuntimeException('handler failed');
}

// A registry for the tests: the handler file is one already loaded.
function mail_queue_outcome_test_handlers()
{
	return array(
		'test'    => array('function' => 'mail_queue_outcome_test_handler', 'file' => 'includes/fn/mail_queue.php'),
		'thrower' => array('function' => 'mail_queue_outcome_test_thrower', 'file' => 'includes/fn/mail_queue.php'),
		'nofile'  => array('function' => 'mail_queue_outcome_test_handler', 'file' => 'includes/fn/mail_queue_outcome_missing.php'),
		'nofn'    => array('function' => 'mail_queue_outcome_test_absent', 'file' => 'includes/fn/mail_queue.php'),
		'outside' => array('function' => 'mail_queue_outcome_test_handler', 'file' => '../tests/mail_queue_outcome_test.php'),
	);
}

// Every handler the registry names has its file under includes/ and its
// function in that file.
function test_mail_queue_outcome_registry_entries_exist()
{
	foreach (pg_mail_outcome_handlers() as $name => $handler) {
		pg_assert_true(is_string($name) && ($name !== ''), 'a name');
		pg_assert_same(0, strpos((string) $handler['file'], 'includes/'), $name . ': a file under includes/');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $handler['file']), $name . ': its file exists');
		require_once(PG_FUNCTIONS_DIR . '/' . $handler['file']);
		pg_assert_true(function_exists($handler['function']), $name . ': its function exists');
	}
}

// The handler is called once with the row id, the outcome, the reference,
// the error and the sent text, which only a row asking keep_mime gets.
function test_mail_queue_outcome_calls_the_named_handler()
{
	mail_queue_outcome_test_calls('reset');
	$row = array('on_outcome' => 'test', 'outcome_ref' => 'item:42', 'keep_mime' => true);

	pg_assert_true(pg_mail_outcome_call($row, 7, 'sent', '', 'MIME', mail_queue_outcome_test_handlers()), 'called');
	pg_assert_same(array(array(7, 'sent', 'item:42', '', 'MIME')), mail_queue_outcome_test_calls(), 'once, with every argument');

	mail_queue_outcome_test_calls('reset');
	unset($row['keep_mime']);
	pg_mail_outcome_call($row, 8, 'failed', 'Mailbox full', 'MIME', mail_queue_outcome_test_handlers());
	pg_assert_same(array(array(8, 'failed', 'item:42', 'Mailbox full', '')), mail_queue_outcome_test_calls(), 'no keep_mime: no sent text');

	mail_queue_outcome_test_calls('reset');
	pg_mail_outcome_call(array('on_outcome' => 'test'), 9, 'suppressed', 'Blocked', '', mail_queue_outcome_test_handlers());
	pg_assert_same(array(array(9, 'suppressed', '', 'Blocked', '')), mail_queue_outcome_test_calls(), 'no reference: empty');
}

// A row with no name, an unknown name, a missing file or function, or a file
// outside includes/ calls nothing.
function test_mail_queue_outcome_skips_what_cannot_be_resolved()
{
	mail_queue_outcome_test_calls('reset');
	$handlers = mail_queue_outcome_test_handlers();

	foreach (array(array(), array('on_outcome' => ''), array('on_outcome' => 'unknown'), array('on_outcome' => 'nofile'), array('on_outcome' => 'nofn'), array('on_outcome' => 'outside'), array('on_outcome' => array('test'))) as $row) {
		pg_assert_false(pg_mail_outcome_call($row, 1, 'sent', '', '', $handlers), 'skipped: ' . json_encode($row));
	}

	pg_assert_same(array(), mail_queue_outcome_test_calls(), 'nothing called');
	pg_assert_false(pg_mail_outcome_call(array('on_outcome' => 'test'), 1, 'sent', '', ''), 'not in the shipped registry');
}

// A handler that throws does not stop the run.
function test_mail_queue_outcome_handler_error_is_caught()
{
	mail_queue_outcome_test_calls('reset');

	pg_assert_true(pg_mail_outcome_call(array('on_outcome' => 'thrower'), 5, 'sent', '', '', mail_queue_outcome_test_handlers()), 'called, the error caught');
	pg_assert_same(array(array('threw', 5)), mail_queue_outcome_test_calls(), 'it ran once');
}

// The queue calls the hook after it writes sent, given up (stale and last
// attempt) and suppressed; the sent text is taken right after email().
function test_mail_queue_outcome_call_sites()
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source('pg_mail_queue_run')) as $token) {
		$code .= pg_test_source_text($token);
	}

	pg_assert_same(4, substr_count($code, 'pg_mail_outcome_call('), 'four places');

	$sent_status = strpos($code, "status = 'sent'");
	$sent_call = strpos($code, "'sent'", $sent_status + 10);
	pg_assert_true(($sent_status !== false) && (strpos($code, 'pg_mail_outcome_call(', $sent_status) < strpos($code, '$result[\'sent\']++', $sent_status) + 200), 'sent: after the row is written');
	pg_assert_true($sent_call !== false, 'sent: the outcome named');

	$email = strpos($code, 'if (email($properties))');
	$mime = strpos($code, 'pg_mail_last_sent_mime()', $email);
	pg_assert_true(($mime !== false) && ($mime < $sent_status), 'the sent text right after email(), before anything else');

	$suppressed = strpos($code, "status = 'suppressed'");
	pg_assert_true(strpos($code, "'suppressed'", $suppressed + 20) < strpos($code, "\$result['suppressed']++", $suppressed) + 200, 'suppressed: after the row is written');

	$stale = strpos($code, "(\$update['status'] === 'failed')");
	pg_assert_true(($stale !== false) && (strpos($code, 'pg_mail_outcome_call(', $stale) < strpos($code, 'for ($looked', $stale)), 'a stale row given up');

	$retry = strpos($code, "status = 'queued',");
	$last = strrpos(substr($code, 0, $retry), 'pg_mail_outcome_call(');
	pg_assert_true(($last !== false) && ($last > strpos($code, 'pg_mail_queue_gives_up(')), 'the last attempt given up, and nothing on a retry');
}

// A deleted row that had not left ends failed; one that already told its
// handler (sent, given up, blocked) tells nothing again.
function test_mail_queue_delete_outcome()
{
	pg_assert_same('failed', pg_mail_queue_delete_outcome('queued'), 'waiting');
	pg_assert_same('failed', pg_mail_queue_delete_outcome('sending'), 'being sent');

	foreach (array('sent', 'failed', 'suppressed', '') as $status) {
		pg_assert_same('', pg_mail_queue_delete_outcome($status), $status . ': nothing');
	}
}

// Delete on the Mail queue screen reads the row first, tells its handler
// only after the row is gone, and is the screen's one delete.
function test_mail_queue_delete_wiring()
{
	$code = pg_test_function_source('pg_mail_queue_delete');
	$read = strpos($code, 'SELECT status, properties FROM mail_outbox');
	$delete = strpos($code, 'DELETE FROM mail_outbox');
	$call = strpos($code, 'pg_mail_outcome_call(');

	pg_assert_true(($read !== false) && ($delete !== false) && ($read < $delete), 'the row read before it is deleted');
	pg_assert_true(($call !== false) && ($call > $delete), 'the handler told after the delete');
	pg_assert_contains('pg_mail_queue_delete_outcome(', $code, 'only a row that had not left');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');
	pg_assert_contains('pg_mail_queue_delete($mail_queue_id)', $screen, 'the screen deletes through it');
	pg_assert_true(strpos($screen, 'DELETE FROM mail_outbox') === false, 'and nowhere else');
}

// The queue screen names the Workspace's mail.
function test_mail_queue_outcome_workspace_type()
{
	pg_assert_true(array_key_exists('workspace', pg_mail_queue_list_types()), 'workspace type');
}
