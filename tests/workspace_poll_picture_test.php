<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a poll question's own picture (includes/workspace/poll_picture.php):
 * the picture check, the picture an ask-again carries, the change refused
 * once answered, the order that keeps a refused question from storing a
 * file, the API routes that take it and tools/ws_poll.php's --file. The
 * runner has no connection, so the writing paths are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_parts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_again.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_picture.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_poll.php');

function ws_poll_picture_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// Q1: an image passes; any other file and an oversize one are refused with a sentence.
function test_ws_poll_picture_refusal()
{
	$max = 20 * 1048576;

	foreach (array('sheet.png', 'Photo.JPG', 'a.jpeg', 'b.gif', 'c.webp', 'd.avif', 'e.bmp') as $name) {
		pg_assert_same('', ws_poll_picture_refusal($name, 1000, $max), $name . ' is a picture');
	}

	foreach (array('notes.pdf', 'clip.mp4', 'drawing.svg', 'page.html', 'noext') as $name) {
		$refused = ws_poll_picture_refusal($name, 1000, $max);
		pg_assert_true($refused !== '' && substr($refused, -1) === '.', $name . ' is refused with a sentence');
	}

	pg_assert_true(ws_poll_picture_refusal('big.png', $max + 1, $max) !== '', 'one byte over the limit is refused');
	pg_assert_same('', ws_poll_picture_refusal('big.png', $max, $max), 'the limit itself passes');
	pg_assert_true(ws_poll_picture_refusal('empty.png', 0, $max) !== '', 'an empty file is refused');
}

// The shapes a picture travels in: new bytes, or a kept file an ask-again carries.
function test_ws_poll_picture_normal()
{
	pg_assert_same(null, ws_poll_picture_normal(null), 'none');
	pg_assert_same(null, ws_poll_picture_normal('x.png'), 'a bare name is none');
	pg_assert_same(array('name' => 'a.png', 'bytes' => 'abc'), ws_poll_picture_normal(array('name' => 'a.png', 'bytes' => 'abc', 'field' => 'file')), 'new bytes');
	pg_assert_same(array('file_id' => 7, 'file_name' => 'a.png'), ws_poll_picture_normal(array('file_id' => '7', 'file_name' => 'a.png')), 'a kept file');
	pg_assert_same(null, ws_poll_picture_normal(array('name' => 'a.png', 'bytes' => false)), 'bytes that could not be decoded');
}

// Q11: the picture a question's message carries is what an ask-again takes along.
function test_ws_poll_picture_of_message()
{
	pg_assert_same(array('file_id' => 12, 'file_name' => 'sheet.png'), ws_poll_picture_of_message(array('file_id' => 12, 'file_name' => 'sheet.png')), 'its picture');
	pg_assert_same(null, ws_poll_picture_of_message(array('file_id' => 0, 'file_name' => '')), 'no file');
	pg_assert_same(null, ws_poll_picture_of_message(array('file_id' => 12, 'file_name' => 'notes.pdf')), 'a file that is no picture is not carried');
	pg_assert_same(null, ws_poll_picture_of_message(null), 'no message');
}

// Q11: ask again carries the picture unless a new one is sent; null leaves it out.
function test_ws_poll_again_carries_the_picture()
{
	$old = array('question' => 'Which sheet?', 'picture' => array('file_id' => 12, 'file_name' => 'sheet.png'), 'options' => array(array('label' => 'A'), array('label' => 'B')));

	$carried = ws_poll_again_data($old, array());
	pg_assert_same(array('file_id' => 12, 'file_name' => 'sheet.png'), $carried['picture'], 'carried over');

	$new = ws_poll_again_data($old, array('picture' => array('name' => 'new.png', 'bytes' => 'xyz')));
	pg_assert_same(array('name' => 'new.png', 'bytes' => 'xyz'), $new['picture'], 'a new one replaces it');

	$none = ws_poll_again_data($old, array('picture' => null));
	pg_assert_same(null, $none['picture'], 'remove_picture leaves it out');

	$plain = ws_poll_again_data(array('question' => 'Q', 'options' => array()), array());
	pg_assert_same(null, $plain['picture'], 'a question without one stays without');

	$check = ws_poll_picture_test_source('ws_poll_again_check');
	pg_assert_contains("'picture'   => ws_poll_picture_of_message(\$checked['message'])", $check, 'the old question\'s picture is read from its message');
	pg_assert_contains('ws_poll_picture_check(', $check, 'a new one meets the picture check');
}

// Q10: replace or remove while open and unanswered; an answered question keeps what was asked.
function test_ws_poll_picture_change_refusal()
{
	pg_assert_same('', ws_poll_picture_change_refusal(true, false, 0), 'open and unanswered');
	pg_assert_true(ws_poll_picture_change_refusal(true, false, 1) !== '', 'answered: refused');
	pg_assert_true(ws_poll_picture_change_refusal(false, false, 0) !== '', 'closed: refused');
	pg_assert_true(ws_poll_picture_change_refusal(true, true, 0) !== '', 'taken back: refused');

	$check = ws_poll_picture_test_source('ws_poll_picture_change_check');
	pg_assert_contains('ws_poll_is_asker(', $check, 'only the asker');
	pg_assert_contains('FROM ws_poll_votes', $check, 'the answers are counted');

	$change = ws_poll_picture_test_source('ws_poll_picture_change');
	pg_assert_contains('ws_poll_picture_change_check(', $change, 'checked before it writes');
	pg_assert_contains('UPDATE ws_messages SET file_id', $change, 'the question\'s message carries the new one');
}

// Q2: a refused question leaves no stored file; the message carries the picture.
function test_ws_poll_picture_refused_question_stores_no_file()
{
	$send = ws_poll_picture_test_source('ws_poll_picture_send');
	$check_at = strpos($send, 'ws_message_send_check(');
	$store_at = strpos($send, 'ws_poll_picture_store(');

	pg_assert_true($check_at !== false && $store_at !== false && $check_at < $store_at, 'the message is checked before the picture is kept');
	pg_assert_contains("'file_id' => \$kept['file_id']", $send, 'the question\'s message carries it');
	pg_assert_contains('ws_upload_discard($kept[\'file_id\'])', $send, 'a message refused after all takes the file back');

	$create = ws_poll_picture_test_source('ws_poll_create');
	$picture_at = strpos($create, 'ws_poll_picture_check(');
	$sent_at = strpos($create, 'ws_poll_picture_send(');
	pg_assert_true($picture_at !== false && $sent_at !== false && $picture_at < $sent_at, 'the picture is checked before anything is sent');
	pg_assert_false(strpos($create, 'ws_message_send($viewer, $channel, $checked[\'question\']') !== false, 'the question goes out through the picture\'s path');

	$picture = ws_poll_picture_test_source('ws_poll_picture_check');
	pg_assert_contains('ws_upload_check(', $picture, 'held to a channel upload\'s checks');
}

// Q11: taking a question back keeps its picture.
function test_ws_poll_withdraw_keeps_the_picture()
{
	pg_assert_false(strpos(ws_poll_picture_test_source('ws_poll_withdraw_write'), 'file_id') !== false, 'taking back touches no file');
}

// Q8, Q10: the routes take the picture as workspace.files.create takes a file; a dry run stores nothing.
function test_ws_api_polls_take_a_picture()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.polls.create', 'workspace.polls.update', 'workspace.polls.again') as $id) {
		$names = array_column($routes[$id]['params'], 'name');
		pg_assert_same('file', $routes[$id]['form_file'] ?? '', $id . ' takes a form part named file');
		pg_assert_true(in_array('content_base64', $names, true) && in_array('name', $names, true), $id . ' takes content_base64 with name');
	}

	pg_assert_true(in_array('remove_picture', array_column($routes['workspace.polls.update']['params'], 'name'), true), 'update removes it');
	pg_assert_true(in_array('remove_picture', array_column($routes['workspace.polls.again']['params'], 'name'), true), 'again leaves it out');

	$create = ws_poll_picture_test_source('ws_api_polls_create');
	$input_at = strpos($create, 'ws_api_poll_picture_input(');
	$dry_at = strpos($create, 'api_dry_run_stop(');
	pg_assert_true($input_at !== false && $dry_at !== false && $input_at < $dry_at, 'create: the picture is checked before the dry run stops');

	$update = ws_poll_picture_test_source('ws_api_polls_update');
	$change_check_at = strpos($update, 'ws_poll_picture_change_check(');
	pg_assert_true($change_check_at !== false && $change_check_at < strpos($update, 'api_dry_run_stop('), 'update: the change is checked before the dry run stops');

	$input = ws_poll_picture_test_source('ws_api_poll_picture_input');
	pg_assert_contains('ws_api_upload_input(', $input, 'the files route\'s own reader');
}

// Q9: --file sends the picture with the question; a missing file is an error line.
function test_ws_poll_tool_file()
{
	$path = tempnam(sys_get_temp_dir(), 'pgq') . '.png';
	file_put_contents($path, "\x89PNG-bytes");

	$read = ws_poll_file_read($path);
	pg_assert_same('', $read['error'], 'a readable file');
	pg_assert_same(basename($path), $read['name'], 'its own name');
	pg_assert_same(base64_encode("\x89PNG-bytes"), $read['content_base64'], 'its bytes, base64');

	$built = ws_poll_ask_body('Which one?', array('A', 'B'), array('file' => $read));
	pg_assert_same(basename($path), $built['body']['name'], 'the request names the picture');
	pg_assert_same($read['content_base64'], $built['body']['content_base64'], 'and carries it');

	$plain = ws_poll_ask_body('Which one?', array('A', 'B'), array());
	pg_assert_false(isset($plain['body']['content_base64']), 'without --file, no picture');

	@unlink($path);
	pg_assert_true(ws_poll_file_read($path)['error'] !== '', 'a missing file stops it');
	pg_assert_true(ws_poll_file_read('')['error'] !== '', 'an empty --file stops it');
	pg_assert_contains('--file=PATH', ws_poll_usage(), '--help names it');
}
