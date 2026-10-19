<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the upkeep of the image derivative cache (includes/fn/image.php):
 * which entries of a file's folder are stale, the purge that sets the cache
 * aside, deleting a folder against a deadline, and when the daily pass is due.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_cache_upkeep_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_upkeep_test_' . bin2hex(random_bytes(4));
	mkdir($folder . '/cache/img', 0755, true);

	return $folder;
}

function test_image_derivative_stale_entries()
{
	$now = 1760000000;
	$source = array('mtime' => 1750000000, 'size' => 4321);
	$jpg = pg_image_derivative_signature(1750000000, 4321, pg_image_derivative_quality('jpg'));
	$webp = pg_image_derivative_signature(1750000000, 4321, pg_image_derivative_quality('webp'));
	$old = pg_image_derivative_signature(1740000000, 4321, pg_image_derivative_quality('jpg'));

	$entries = array(
		'960-' . $jpg . '.jpg' => $now - 10,
		'480-' . $webp . '.webp' => $now - 10,
		'480-' . $webp . '.webp.skip' => $now - 10,
		'960-' . $jpg . '.jpg.try' => $now - 10,
		'960-' . $old . '.jpg' => $now - 10,
		'480-' . $old . '.jpg.skip' => $now - 10,
		'960-' . $old . '.jpg.try' => $now - 10,
		'960-' . $jpg . '.webp' => $now - 10,
		'960-' . $jpg . '.jpg.lock' => $now - 60,
		'1440-' . $jpg . '.jpg.lock' => $now - 7200,
		'1440-' . $jpg . '.jpg.tmp1234' => $now - 7200,
		'readme.txt' => $now - 999999,
	);

	$stale = pg_image_derivative_stale_entries($entries, $source, $now);
	sort($stale);

	$expected = array(
		'1440-' . $jpg . '.jpg.lock',
		'1440-' . $jpg . '.jpg.tmp1234',
		'480-' . $old . '.jpg.skip',
		'960-' . $jpg . '.webp',
		'960-' . $old . '.jpg',
		'960-' . $old . '.jpg.try',
	);
	sort($expected);

	pg_assert_same($expected, $stale, 'old signatures, a quality of another format, dead locks and temporary files');

	pg_assert_same(array_keys($entries), pg_image_derivative_stale_entries($entries, null, $now), 'everything when the file is gone');
}

function test_image_derivative_purge_sets_the_cache_aside()
{
	$folder = image_cache_upkeep_test_folder();
	$directory = $folder . '/cache/img';

	try {
		mkdir($directory . '/12');
		file_put_contents($directory . '/12/480-abcdefabcdef.jpg', str_repeat('x', 100));
		file_put_contents($directory . '/formats.json', '{}');

		pg_assert_true(pg_image_derivative_purge($directory), 'purged');
		pg_assert_false(is_dir($directory), 'the cache is gone at once');
		pg_assert_same(array(), pg_image_derivative_set_aside($directory), 'and deleted within the purge when it is small');
		pg_assert_false(pg_image_derivative_purge($directory), 'nothing to purge the second time');

		// A large cache: the part the purge had no time for waits beside it.
		mkdir($folder . '/cache/img.old-1-1/7', 0755, true);
		file_put_contents($folder . '/cache/img.old-1-1/7/a', 'abc');

		pg_assert_same(array($folder . '/cache/img.old-1-1'), pg_image_derivative_set_aside($directory), 'found beside it');
		pg_assert_true(pg_image_derivative_sweep_due($directory), 'which makes a pass due');

		$removed = pg_image_derivative_remove_tree($folder . '/cache/img.old-1-1', microtime(true) + 5);
		pg_assert_same(array('finished' => true, 'files' => 1, 'bytes' => 3), $removed, 'deleted file by file');
		pg_assert_false(is_dir($folder . '/cache/img.old-1-1'), 'and the folder');

		mkdir($folder . '/cache/img.old-2-2', 0755, true);
		file_put_contents($folder . '/cache/img.old-2-2/a', 'abc');
		$late = pg_image_derivative_remove_tree($folder . '/cache/img.old-2-2', microtime(true) - 1);
		pg_assert_false($late['finished'], 'a deadline already passed stops it');
		pg_assert_true(is_file($folder . '/cache/img.old-2-2/a'), 'before it deletes anything');

	} finally {
		pg_image_derivative_remove_tree($folder, microtime(true) + 30);
	}
}

function test_image_derivative_sweep_due()
{
	$folder = image_cache_upkeep_test_folder();
	$directory = $folder . '/cache/img';

	try {
		pg_assert_false(pg_image_derivative_sweep_due(''), 'no cache folder known');
		pg_assert_false(pg_image_derivative_sweep_due($folder . '/cache/none'), 'no cache yet');
		pg_assert_true(pg_image_derivative_sweep_due($directory), 'never swept');

		file_put_contents($directory . '/sweep.json', json_encode(array('last_id' => 0, 'done_at' => time())));
		pg_assert_false(pg_image_derivative_sweep_due($directory), 'swept today');
		pg_assert_same(array('last_id' => 0, 'done_at' => (int) json_decode(file_get_contents($directory . '/sweep.json'), true)['done_at']), pg_image_derivative_sweep_state($directory), 'state read back');

		file_put_contents($directory . '/sweep.json', json_encode(array('last_id' => 345, 'done_at' => time())));
		pg_assert_true(pg_image_derivative_sweep_due($directory), 'a pass under way');

		file_put_contents($directory . '/sweep.json', json_encode(array('last_id' => 0, 'done_at' => time() - 90000)));
		pg_assert_true(pg_image_derivative_sweep_due($directory), 'a day since the last pass');

	} finally {
		pg_image_derivative_remove_tree($folder, microtime(true) + 30);
	}
}
