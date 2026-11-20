<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/ws_note.php: the command line, the base kept beside a
 * file, the request bodies, the exit codes and the 409 path.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_note.php');

// new needs a file and takes no note id; --channel-edit goes with it alone.
function test_ws_note_parse_new()
{
	$plan = ws_note_parse(array('new', '--file=plan.md', '--title=Plan', '--channel-edit'));
	pg_assert_same('', $plan['error']);
	pg_assert_same('new', $plan['command']);
	pg_assert_same('plan.md', $plan['file']);
	pg_assert_same('Plan', $plan['title']);
	pg_assert_true($plan['channel_edit']);
	pg_assert_same('new needs --file', ws_note_parse(array('new'))['error']);
	pg_assert_same('new takes no note id', ws_note_parse(array('new', '4', '--file=a.md'))['error']);
	pg_assert_same('--channel-edit goes with new', ws_note_parse(array('update', '4', '--file=a.md', '--revision=2', '--channel-edit'))['error']);
}

// read needs the id; it writes to stdout or into --file, never reads stdin.
function test_ws_note_parse_read()
{
	$plan = ws_note_parse(array('read', '12'));
	pg_assert_same('', $plan['error']);
	pg_assert_same(12, $plan['note_id']);
	pg_assert_same('read needs the note id', ws_note_parse(array('read'))['error']);
	pg_assert_same('the note id must be a number', ws_note_parse(array('read', 'x'))['error']);
	pg_assert_same('the note id must be a number', ws_note_parse(array('read', '0'))['error']);
	pg_assert_same('read writes to stdout without --file', ws_note_parse(array('read', '12', '--file=-'))['error']);
	pg_assert_same('--title goes with new or update', ws_note_parse(array('read', '12', '--title=T'))['error']);
}

// update takes the id and the revision from the base kept beside the file,
// and never runs without a revision.
function test_ws_note_parse_update_base()
{
	$base = array('note_id' => 12, 'revision' => 4);
	$plan = ws_note_parse(array('update', '--file=plan.md'), $base);
	pg_assert_same('', $plan['error']);
	pg_assert_same(12, $plan['note_id']);
	pg_assert_same(4, $plan['revision']);

	$plan = ws_note_parse(array('update', '12', '--file=plan.md', '--revision=7'), $base);
	pg_assert_same(7, $plan['revision'], '--revision wins over the kept one');

	$plan = ws_note_parse(array('update', '13', '--file=plan.md'), $base);
	pg_assert_same('update needs the revision the note was read at: --revision, or read --file first', $plan['error'], 'a base kept for another note is not used');

	pg_assert_same('update needs the note id, or a file read or written with this tool', ws_note_parse(array('update', '--file=plan.md'))['error']);
	pg_assert_same('update needs --file', ws_note_parse(array('update', '12', '--revision=3'))['error']);
	pg_assert_same('--revision must be a number', ws_note_parse(array('update', '12', '--file=a', '--revision=x'))['error']);
}

// Usage errors name the commands.
function test_ws_note_parse_command()
{
	pg_assert_same('name a command: new, read or update', ws_note_parse(array())['error']);
	pg_assert_same('unknown command delete', ws_note_parse(array('delete', '4'))['error']);
	pg_assert_same('the title is longer than 200 characters', ws_note_parse(array('new', '--file=a', '--title=' . str_repeat('x', 201)))['error']);
	pg_assert_true(strpos(ws_note_usage(), 'update [<note id>] --file=PATH') !== false);
}

// The kept base reads only whole positive numbers.
function test_ws_note_base()
{
	pg_assert_same(array('note_id' => 12, 'revision' => 4), ws_note_base_parse(ws_note_base_json(12, 4)));
	pg_assert_same(array('note_id' => 0, 'revision' => 0), ws_note_base_parse(false));
	pg_assert_same(array('note_id' => 0, 'revision' => 0), ws_note_base_parse('{"note_id":"12","revision":-1}'));
	pg_assert_same('plan.md.ws_note', ws_note_base_path('plan.md'));
}

// new sends channel_edit only when asked; update always sends the revision.
function test_ws_note_body()
{
	$new = ws_note_parse(array('new', '--file=a', '--channel-edit'));
	pg_assert_same(array('body' => "# Plan\n", 'channel_edit' => true), ws_note_body($new, "# Plan\n")['body']);

	$update = ws_note_parse(array('update', '12', '--file=a', '--revision=3', '--title=New'));
	pg_assert_same(array('body' => 'x', 'title' => 'New', 'revision' => 3), ws_note_body($update, 'x')['body']);

	pg_assert_same('the file is empty', ws_note_body($new, " \n")['error']);
	pg_assert_same('the text is longer than 60000 characters', ws_note_body($new, str_repeat('a', 60001))['error']);
	pg_assert_same('', ws_note_body($new, str_repeat('ş', 60000))['error'], 'characters are counted, not bytes');
}

// 0 written, 1 refused or no answer.
function test_ws_note_exit()
{
	pg_assert_same(0, ws_note_exit(array('status' => 201)));
	pg_assert_same(0, ws_note_exit(array('status' => 200)));
	pg_assert_same(1, ws_note_exit(array('status' => 409)));
	pg_assert_same(1, ws_note_exit(array('status' => 403)));
	pg_assert_same(1, ws_note_exit(array('status' => 0)));
}

// A 409 changed_meanwhile is told apart, with the site's words.
function test_ws_note_conflict()
{
	$answer = array('status' => 409, 'body' => json_encode(array('error' => array('code' => 'changed_meanwhile', 'message' => 'Ayşe changed it.', 'field' => 'revision'))));
	pg_assert_true(ws_note_conflict($answer));
	pg_assert_same('note 12 changed since revision 4 (Ayşe changed it.); nothing was written: read it again, merge, update again', ws_note_conflict_line($answer, 12, 4));
	pg_assert_true(!ws_note_conflict(array('status' => 409, 'body' => '{"error":{"code":"idempotency_in_progress"}}')));
	pg_assert_true(!ws_note_conflict(array('status' => 422, 'body' => '{"error":{"code":"changed_meanwhile"}}')));
}

// The printed line: new names the card's message too.
function test_ws_note_result()
{
	pg_assert_same('note=12 revision=1 message=88', ws_note_result(array('message_id' => 88, 'note_id' => 12, 'revision' => 1), 'new')['line']);
	pg_assert_same(array('note_id' => 12, 'revision' => 5, 'line' => 'note=12 revision=5'), ws_note_result(array('id' => 12, 'revision' => 5, 'body' => 'x'), 'update'));
	pg_assert_same('note=0 revision=0', ws_note_result(null, 'read')['line']);
}

// A call without an answer is sent once more; a refusal is not.
function test_ws_note_send()
{
	$calls = 0;
	$answer = ws_note_send(function () use (&$calls) {
		$calls++;
		return array('status' => $calls === 1 ? 504 : 200, 'headers' => '', 'body' => '', 'error' => '');
	});
	pg_assert_same(2, $calls);
	pg_assert_same(200, $answer['status']);

	$calls = 0;
	ws_note_send(function () use (&$calls) {
		$calls++;
		return array('status' => 409, 'headers' => '', 'body' => '', 'error' => '');
	});
	pg_assert_same(1, $calls, 'a 409 is not sent again');
}
