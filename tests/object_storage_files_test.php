<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/file_storage.php: where a row's
 * bytes are, the object key, which rows the move job takes, its state from
 * tick to tick, the cache names and the cache sweep's choice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_storage.php');

function test_object_storage_files_storage_of_a_row()
{
	pg_assert_same('local', pg_file_storage(array('name' => 'a.jpg')), 'a row read before the migration');
	pg_assert_same('local', pg_file_storage(array('storage' => 'local', 'storage_key' => '')));
	pg_assert_same('s3', pg_file_storage(array('storage' => 's3', 'storage_key' => 'files/4/a.jpg')));
	pg_assert_same('local', pg_file_storage(array('storage' => 's3', 'storage_key' => '')), 'no key: nothing to read in the store');
	pg_assert_same('local', pg_file_storage(array('storage' => 'cdn', 'storage_key' => 'x')), 'an unknown place');
}

function test_object_storage_files_key()
{
	pg_assert_same('files/12/report.pdf', pg_file_storage_key(12, 'report.pdf'));
	pg_assert_same('files/12/a_b.pdf', pg_file_storage_key('12', 'a/b.pdf'), 'no slash from the name');
	pg_assert_same('files/3/file', pg_file_storage_key(3, ' '));
}

function test_object_storage_files_movable()
{
	pg_assert_true(pg_file_storage_movable(array('id' => 1, 'name' => 'photo.jpg', 'design' => 0, 'theme' => 0)));
	pg_assert_false(pg_file_storage_movable(array('id' => 1, 'name' => 'site.css', 'design' => 1, 'theme' => 0)), 'a design file');
	pg_assert_false(pg_file_storage_movable(array('id' => 1, 'name' => 'theme.css', 'design' => 0, 'theme' => 1)), 'a theme file');
	pg_assert_false(pg_file_storage_movable(array('id' => 1, 'name' => 'DKIM.key', 'design' => 0, 'theme' => 0)), 'the DKIM key');
	pg_assert_false(pg_file_storage_movable(array('id' => 1, 'name' => '../x.jpg', 'design' => 0, 'theme' => 0)), 'a name with a folder');
	pg_assert_false(pg_file_storage_movable(array('id' => 1, 'name' => '', 'design' => 0, 'theme' => 0)));
}

function test_object_storage_files_state_normalize()
{
	$state = pg_file_move_state_normalize(null);
	pg_assert_same(array('cursor' => 0, 'failures' => array(), 'rested_at' => 0), $state);

	$state = pg_file_move_state_normalize(array('cursor' => '40', 'failures' => array('7' => '2', '0' => 3, '9' => 0), 'rested_at' => -5));
	pg_assert_same(40, $state['cursor']);
	pg_assert_same(array(7 => 2), $state['failures'], 'bad ids and counts dropped');
	pg_assert_same(0, $state['rested_at']);

	$many = array();
	for ($i = 1; $i <= 520; $i++) {
		$many[$i] = 1;
	}
	$state = pg_file_move_state_normalize(array('failures' => $many));
	pg_assert_same(500, count($state['failures']), 'the list is capped');
	pg_assert_false(isset($state['failures'][1]), 'the oldest go first');
}

function test_object_storage_files_due()
{
	pg_assert_true(pg_file_move_due(array('cursor' => 50, 'failures' => array(), 'rested_at' => 1000), 1001), 'a pass under way');
	pg_assert_false(pg_file_move_due(array('cursor' => 0, 'failures' => array(), 'rested_at' => 1000), 1300), 'resting');
	pg_assert_true(pg_file_move_due(array('cursor' => 0, 'failures' => array(), 'rested_at' => 1000), 1600));
}

function test_object_storage_files_next_state()
{
	$state = pg_file_move_state_normalize(array('failures' => array(8 => 1, 6 => 2)));

	$next = pg_file_move_next_state($state, array(9 => true, 8 => true, 7 => null, 6 => false), false, 5000);
	pg_assert_same(6, $next['state']['cursor'], 'below the lowest row read');
	pg_assert_false(isset($next['state']['failures'][8]), 'a moved file forgets its failures');
	pg_assert_same(3, $next['state']['failures'][6]);
	pg_assert_same(array(6), $next['given_up'], 'named once, when it reaches the limit');
	pg_assert_same(0, $next['state']['rested_at']);
	pg_assert_true(pg_file_move_given_up($next['state'], 6));
	pg_assert_false(pg_file_move_given_up($next['state'], 7));

	$again = pg_file_move_next_state($next['state'], array(5 => false), true, 6000);
	pg_assert_same(0, $again['state']['cursor'], 'the pass is over');
	pg_assert_same(6000, $again['state']['rested_at']);
	pg_assert_same(array(), $again['given_up']);
	pg_assert_same(1, $again['state']['failures'][5], 'tried again on the next pass');

	$empty = pg_file_move_next_state($state, array(), true, 7000);
	pg_assert_same(0, $empty['state']['cursor']);
	pg_assert_same(7000, $empty['state']['rested_at']);
}

function test_object_storage_files_cache_name()
{
	pg_assert_same('15.pdf', pg_file_cache_name(15, 'PDF'));
	pg_assert_same('15', pg_file_cache_name(15, ''));
	pg_assert_same('15.php', pg_file_cache_name('15', '.p/h p'), 'only letters and digits');
}

function test_object_storage_files_cache_sweep_pick()
{
	$now = 1000000;
	$day = 86400;

	$entries = array(
		'/c/1.jpg'        => array(400, $now - 3 * $day),
		'/c/2.jpg'        => array(400, $now - 1 * $day),
		'/c/3.jpg'        => array(400, $now - 60),
		'/c/4.jpg'        => array(10, $now - 8 * $day),
		'/c/5.jpg.1.part' => array(10, $now - 2 * $day),
		'/c/6.jpg.2.part' => array(10, $now - 60),
	);

	$delete = pg_file_cache_sweep_pick($entries, 1000, $now);
	sort($delete);
	pg_assert_same(array('/c/1.jpg', '/c/4.jpg', '/c/5.jpg.1.part'), $delete, 'stale, dead fetches, then least recently used above the cap');

	pg_assert_same(array(), pg_file_cache_sweep_pick(array('/c/3.jpg' => array(400, $now)), 1000, $now));
}
