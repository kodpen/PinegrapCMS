<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stage of a notes import (includes/workspace/notes_import.php):
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
