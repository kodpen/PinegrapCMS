<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the write permission walk (pg_write_permission_walk(),
 * includes/fn/system_status.php): the folders-only walk the update and
 * repair screens read answers the same folders as the full walk, and asks
 * no file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A small software tree under the system's temporary folder: folders with
 * files, a data/ folder with files of its own, and one folder made read-only.
 *
 * @return array('root' => string, 'closed' => string)
 */
function pg_test_write_permission_tree()
{
	$root = sys_get_temp_dir() . '/pg_write_permission_test_' . bin2hex(random_bytes(4));

	foreach (array('', '/includes', '/includes/fn', '/assets', '/assets/js', '/data', '/data/files', '/locked') as $folder) {
		mkdir($root . $folder);
	}

	foreach (array('/index.php', '/includes/a.php', '/includes/fn/b.php', '/assets/js/c.js', '/data/files/d.jpg', '/data/config.php', '/locked/e.php') as $file) {
		file_put_contents($root . $file, 'x');
	}

	chmod($root . '/locked', 0555);

	return array('root' => $root, 'closed' => $root . '/locked');
}

function pg_test_write_permission_tree_remove($root)
{
	chmod($root . '/locked', 0777);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $path => $entry) {
		if ($entry->isDir()) {
			rmdir($path);
		} else {
			unlink($path);
		}
	}

	rmdir($root);
}

function test_write_permission_folders_only_walk_matches_the_full_walk_on_folders()
{
	$tree = pg_test_write_permission_tree();

	$full = pg_write_permission_walk($tree['root']);
	$folders = pg_write_permission_walk($tree['root'], false);

	pg_test_write_permission_tree_remove($tree['root']);

	pg_assert_same(8, $full['checked_directories'], 'the root and its seven folders');
	pg_assert_same(5, $full['checked_files'], 'every file outside data/');

	foreach (array('directories', 'directories_count', 'checked_directories', 'truncated') as $key) {
		pg_assert_same($full[$key], $folders[$key], $key . ' is the same in both walks');
	}

	pg_assert_same(0, $folders['checked_files'], 'no file is asked');
	pg_assert_same(array(), $folders['files'], 'no file is listed');
	pg_assert_same(0, $folders['files_count'], 'no file is counted');
}

function test_write_permission_walk_lists_a_closed_folder()
{
	$tree = pg_test_write_permission_tree();

	// Root writes into a 0555 folder all the same; the listing is only
	// asked of a runner that the mode stops. On Windows chmod() only sets
	// the read-only attribute, which Windows does not enforce on folders,
	// so the walk (pg_dir_writable()) rightly lists nothing there.
	$closed = (DIRECTORY_SEPARATOR !== '\\') && !is_writable($tree['closed']);

	$full = pg_write_permission_walk($tree['root']);
	$folders = pg_write_permission_walk($tree['root'], false);

	pg_test_write_permission_tree_remove($tree['root']);

	if (!$closed) {
		pg_assert_same(0, $folders['directories_count'], 'nothing refuses this runner');
		return;
	}

	pg_assert_same(array('locked' => '0555'), $folders['directories'], 'the closed folder by its relative path and mode');
	pg_assert_same(1, $folders['directories_count']);
	pg_assert_same($full['directories'], $folders['directories'], 'the full walk lists the same folder');
}

function test_write_permission_time_budget_follows_max_execution_time()
{
	pg_assert_same(10.0, pg_write_permission_time_budget(0), 'no limit: the ten-second ceiling');
	pg_assert_same(10.0, pg_write_permission_time_budget('0'), 'ini_get() answers a string');
	pg_assert_same(10.0, pg_write_permission_time_budget(false), 'an unreadable setting: the ceiling');
	pg_assert_same(10.0, pg_write_permission_time_budget('300'), 'a long limit: the ceiling');
	pg_assert_same(7.5, pg_write_permission_time_budget(30), 'a quarter of a 30-second limit');
	pg_assert_same(1.0, pg_write_permission_time_budget(2), 'never under one second');
	pg_assert_same(10.0, pg_write_permission_time_budget(-5), 'a negative limit reads as none');
}

function test_write_permission_walk_stop_answers_by_entries_then_time()
{
	pg_assert_same('', pg_write_permission_walk_stop(10, 0.5, 10.0), 'within both ceilings');
	pg_assert_same('entries', pg_write_permission_walk_stop(40001, 0.5, 10.0), 'past the entry ceiling');
	pg_assert_same('', pg_write_permission_walk_stop(40000, 0.5, 10.0), 'the ceiling itself is still walked');
	pg_assert_same('time', pg_write_permission_walk_stop(10, 10.0, 10.0), 'the budget spent');
	pg_assert_same('time', pg_write_permission_walk_stop(10, 12.3, 10.0), 'the budget overrun');
	pg_assert_same('', pg_write_permission_walk_stop(10, 999.0, 0.0), 'no budget: time never stops it');
	pg_assert_same('entries', pg_write_permission_walk_stop(40001, 12.0, 10.0), 'both: the entry ceiling is named');
}

function test_write_permission_walk_stops_in_time_and_says_so()
{
	$tree = pg_test_write_permission_tree();

	// A budget already spent stops the walk at its first entry; the root
	// is still asked, as on a slow disk where the first answers came late.
	$spent = pg_write_permission_walk($tree['root'], true, 0.000001);
	$whole = pg_write_permission_walk($tree['root'], true, 60.0);

	pg_test_write_permission_tree_remove($tree['root']);

	pg_assert_true($spent['truncated'], 'a walk out of time is truncated');
	pg_assert_true($spent['timed_out'], 'and says it ran out of time');
	pg_assert_same(1, $spent['checked_directories'], 'only the root was asked');

	pg_assert_false($whole['truncated'], 'a walk within its budget is whole');
	pg_assert_false($whole['timed_out']);
	pg_assert_same(8, $whole['checked_directories']);
}
