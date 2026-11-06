<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the object store orphan sweep's window: the listing query that
 * resumes after a key (pg_os_list_query()) and where the next day's pass
 * starts (pg_file_storage_sweep_cursor()), so a bucket of more than one
 * window's keys is swept to its end instead of its first keys every day.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/object_storage.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_storage.php');

function test_object_storage_orphan_sweep_list_query_resumes_after_key()
{
	$config = array('prefix' => 'site');

	pg_assert_same(array('list-type' => '2', 'max-keys' => '1000', 'prefix' => 'site/files/'),
		pg_os_list_query($config, 'site/files/', 1000, '', ''), 'first page, no start');

	pg_assert_same(array('list-type' => '2', 'max-keys' => '500', 'prefix' => 'site/files/', 'start-after' => 'site/files/42/a.jpg'),
		pg_os_list_query($config, 'site/files/', 500, '', 'files/42/a.jpg'), 'start after a key below the prefix');

	pg_assert_same(array('list-type' => '2', 'max-keys' => '1000', 'prefix' => 'site/files/', 'continuation-token' => 'TOKEN'),
		pg_os_list_query($config, 'site/files/', 1000, 'TOKEN', 'files/42/a.jpg'), 'a later page follows its token only');

	pg_assert_same(array('list-type' => '2', 'max-keys' => '1000', 'start-after' => 'files/9/b.jpg'),
		pg_os_list_query(array('prefix' => ''), '', 1000, '', 'files/9/b.jpg'), 'no configured prefix');
}

function test_object_storage_orphan_sweep_cursor_moves_past_full_window()
{
	$items = array(
		array('key' => 'files/1/a.jpg'),
		array('key' => 'files/10/b.jpg'),
		array('key' => 'files/100/c.jpg'),
	);

	pg_assert_same('files/100/c.jpg', pg_file_storage_sweep_cursor($items, true, array('files/10/b.jpg'), 200),
		'more keys than the window: the next pass starts after its last key');

	pg_assert_same('', pg_file_storage_sweep_cursor($items, false, array('files/10/b.jpg'), 200),
		'the listing reached the end: the next pass starts from the top');

	pg_assert_same('', pg_file_storage_sweep_cursor(array(), true, array(), 200), 'an empty window starts over');
}

function test_object_storage_orphan_sweep_cursor_keeps_orphans_over_the_delete_cap()
{
	$items = array(
		array('key' => 'files/1/a.jpg'),
		array('key' => 'files/2/b.jpg'),
		array('key' => 'files/3/c.jpg'),
		array('key' => 'files/4/d.jpg'),
	);
	$orphans = array('files/1/a.jpg', 'files/2/b.jpg', 'files/3/c.jpg');

	pg_assert_same('files/2/b.jpg', pg_file_storage_sweep_cursor($items, true, $orphans, 2),
		'orphans past the cap are read again next pass');

	pg_assert_same('files/2/b.jpg', pg_file_storage_sweep_cursor($items, false, $orphans, 2),
		'also at the end of the listing');

	pg_assert_same('', pg_file_storage_sweep_cursor($items, false, array_slice($orphans, 0, 2), 2),
		'exactly the cap: the window is done');
}
