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
	// asked of a runner that the mode stops.
	$closed = !is_writable($tree['closed']);

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
