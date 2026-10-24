<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stage of a notes import (includes/workspace/notes_import.php):
 * the stage goes under data/temp whatever is_writable() says of it,
 * stage.json is put in its place by rename(), written into the file itself
 * when renaming is refused, and a failure says what PHP said about both.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');

/**
 * A folder of its own under the system's temporary folder.
 *
 * @return string
 */
function pg_test_notes_import_dir()
{
	$dir = sys_get_temp_dir() . '/pg_notes_import_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	return $dir;
}

/**
 * Removes such a folder, with what is in it (one level of folders).
 *
 * @param string $dir
 */
function pg_test_notes_import_clean($dir)
{
	foreach ((array) glob($dir . '/*') as $path) {
		if (is_dir($path)) {
			@rmdir($path);
		} else {
			@unlink($path);
		}
	}

	@rmdir($dir);
}

// The usual way: the file written beside it takes the place of the old one,
// and of none at the first write.
function test_workspace_notes_import_replace_renames()
{
	$dir = pg_test_notes_import_dir();
	$path = $dir . '/stage.json';

	file_put_contents($dir . '/first.tmp', '{"a":1}');
	$why = 'left from before';
	pg_assert_true(ws_note_import_replace($dir . '/first.tmp', $path, '{"a":1}', $why, array()), 'first write');
	pg_assert_same('{"a":1}', file_get_contents($path));
	pg_assert_false(is_file($dir . '/first.tmp'), 'nothing left beside it');
	pg_assert_same('', $why, 'nothing to say');

	file_put_contents($dir . '/second.tmp', '{"a":2}');
	pg_assert_true(ws_note_import_replace($dir . '/second.tmp', $path, '{"a":2}', $why, array()), 'over the old one');
	pg_assert_same('{"a":2}', file_get_contents($path));
	pg_assert_false(is_file($dir . '/second.tmp'));

	pg_test_notes_import_clean($dir);
}

// When rename() is refused (here the file beside it is gone), the bytes are
// written into the file itself.
function test_workspace_notes_import_replace_writes_in_place()
{
	$dir = pg_test_notes_import_dir();
	$path = $dir . '/stage.json';
	file_put_contents($path, '{"old":true}');

	$why = '';
	pg_assert_true(ws_note_import_replace($dir . '/gone.tmp', $path, '{"new":true}', $why, array()), 'written in place');
	pg_assert_same('{"new":true}', file_get_contents($path));
	pg_assert_same('', $why);

	pg_test_notes_import_clean($dir);
}

// Neither way works (the place is a folder): every try is made, the answer
// is false, and $why holds what PHP said about both ways. The file beside it
// is the caller's to remove.
function test_workspace_notes_import_replace_says_why()
{
	$dir = pg_test_notes_import_dir();
	mkdir($dir . '/stage.json');
	file_put_contents($dir . '/next.tmp', '{"a":3}');

	$why = '';
	pg_assert_false(ws_note_import_replace($dir . '/next.tmp', $dir . '/stage.json', '{"a":3}', $why, array(0, 0)), 'refused');
	pg_assert_contains('rename: ', $why, 'what rename() said');
	pg_assert_contains('; writing it in place: ', $why, 'what the write said');
	pg_assert_true(is_file($dir . '/next.tmp'), 'the file beside it stays');
	pg_assert_true(is_dir($dir . '/stage.json'), 'the folder is untouched');

	pg_test_notes_import_clean($dir);
}

// The stage is written without its folder, nothing is left beside it, and a
// second write replaces the first.
function test_workspace_notes_import_save_writes_the_stage()
{
	$dir = pg_test_notes_import_dir();

	pg_assert_true(ws_note_import_save(array('dir' => $dir, 'user' => 7, 'entries' => array(array('a.md', 3, 0, 0)))), 'first');
	pg_assert_true(ws_note_import_save(array('dir' => $dir, 'user' => 7, 'entries' => array(), 'next' => 1)), 'second');

	$stage = json_decode((string) file_get_contents($dir . '/stage.json'), true);
	pg_assert_same(array('user' => 7, 'entries' => array(), 'next' => 1), $stage, 'the second write, without dir');
	pg_assert_same(array($dir . '/stage.json'), glob($dir . '/*'), 'no temporary file left');

	pg_assert_false(ws_note_import_save(array('dir' => '', 'user' => 7, 'entries' => array())), 'no folder, no write');

	pg_test_notes_import_clean($dir);
}

// A stream wrapper whose every path is a folder with no write bit, owned by
// another user: is_dir() says yes and is_writable() says no, whoever runs the
// tests. A folder made read-only with chmod() would not stop root.
class pg_notes_import_test_closed_folder
{
	public $context;

	public function url_stat($path, $flags)
	{
		return array('mode' => 040555, 'uid' => 65534, 'gid' => 65534, 'nlink' => 2);
	}
}

// A folder that is_writable() calls closed is still where the stage goes: on
// Windows is_writable() reads the folder's read-only attribute, which
// Explorer and OneDrive set freely and which stops nothing. The writes that
// follow are the answer, so only a folder that is not there (and cannot be
// made) is ''.
function test_workspace_notes_import_temp_dir_ignores_is_writable()
{
	stream_wrapper_register('pgnotesclosed', 'pg_notes_import_test_closed_folder');
	pg_assert_true(is_dir('pgnotesclosed://temp'), 'the stand-in is a folder');
	pg_assert_false(is_writable('pgnotesclosed://temp'), 'that is_writable() calls closed');
	pg_assert_same('pgnotesclosed://temp', ws_note_import_temp_dir('pgnotesclosed://temp'), 'the stage goes there all the same');
	stream_wrapper_unregister('pgnotesclosed');

	$dir = pg_test_notes_import_dir();
	pg_assert_same($dir, ws_note_import_temp_dir($dir), 'a folder that is there');
	pg_assert_same($dir . '/temp', ws_note_import_temp_dir($dir . '/temp'), 'one that is not is made');
	pg_assert_true(is_dir($dir . '/temp'));

	// A file stands where the folder's parent should be: mkdir() is refused
	// whoever runs the tests, and what PHP said stays for the log.
	file_put_contents($dir . '/plain', 'x');
	error_clear_last();
	pg_assert_same('', ws_note_import_temp_dir($dir . '/plain/temp'), 'cannot be made');
	$last = error_get_last();
	pg_assert_contains('mkdir', is_array($last) ? (string) $last['message'] : '', 'what mkdir() said');

	pg_test_notes_import_clean($dir);
}

// A token that is not of our shape never reaches a path.
function test_workspace_notes_import_stage_dir_refuses_other_tokens()
{
	foreach (array('', 'abc', '../../config', str_repeat('A', 32), str_repeat('a', 31), str_repeat('a', 32) . '/') as $token) {
		pg_assert_same('', ws_note_import_stage_dir($token), var_export($token, true));
	}
}
