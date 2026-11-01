<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the encode cap and the warm-up of image derivatives: the encode
 * slots (includes/image_engine.php) and the warm-up pass with its cursor
 * (includes/fn/image.php), over pictures the test draws with GD.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_encode_warmup_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_warmup_test_' . bin2hex(random_bytes(4));
	mkdir($folder . '/files', 0755, true);
	mkdir($folder . '/cache', 0755, true);

	return $folder;
}

function image_encode_warmup_test_remove($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $entry) {
			if (($entry !== '.') && ($entry !== '..')) {
				image_encode_warmup_test_remove($path . '/' . $entry);
			}
		}

		rmdir($path);

	} elseif (file_exists($path) || is_link($path)) {
		unlink($path);
	}
}

function image_encode_warmup_test_webp()
{
	return function_exists('imagecreatetruecolor') && function_exists('imagejpeg') && function_exists('imagewebp');
}

// A photo-like JPEG, saved at a high quality so its WebP copy is lighter.
function image_encode_warmup_test_jpeg($path, $width, $height, $seed)
{
	$image = imagecreatetruecolor($width, $height);

	mt_srand($seed);

	for ($i = 0; $i < 1500; $i++) {
		$x = mt_rand(0, $width - 1);
		$y = mt_rand(0, $height - 1);
		imagefilledrectangle($image, $x, $y, $x + mt_rand(4, 30), $y + mt_rand(4, 30), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
	}

	imagejpeg($image, $path, 95);
	pg_image_destroy($image);
}

// A fetch over a fixed list, in the pass's order, honouring the cursor the
// way pg_image_warmup_after_sql() does in SQL.
function image_encode_warmup_test_fetch($rows, &$calls = null)
{
	usort($rows, function ($a, $b) {
		return ($a['size'] === $b['size']) ? ($b['id'] - $a['id']) : ($b['size'] - $a['size']);
	});

	return function ($state, $limit) use ($rows, &$calls) {
		$calls++;
		$after = array();

		foreach ($rows as $row) {
			if (($state['id'] < 1) || ($row['size'] < $state['size']) || (($row['size'] === $state['size']) && ($row['id'] < $state['id']))) {
				$after[] = $row;
			}
		}

		return array_slice($after, 0, $limit);
	};
}

function test_image_encode_slot_count_default_and_bounds()
{
	if (!defined('PG_IMAGE_ENCODE_SLOTS')) {
		pg_assert_same(2, pg_image_encode_slot_count(), 'two by default');
	}

	pg_assert_true(pg_image_encode_slot_count() >= 1 && pg_image_encode_slot_count() <= 16, 'between 1 and 16');
}

function test_image_encode_slots_are_taken_and_given_back()
{
	$folder = image_encode_warmup_test_folder();

	try {
		$first = pg_image_encode_slot_take($folder . '/cache', 2);
		$second = pg_image_encode_slot_take($folder . '/cache', 2);

		pg_assert_true(is_resource($first), 'the first slot');
		pg_assert_true(is_resource($second), 'the second slot');
		pg_assert_true(is_file($folder . '/cache/encode-1.lock') && is_file($folder . '/cache/encode-2.lock'), 'beside formats.json');
		pg_assert_same(null, pg_image_encode_slot_take($folder . '/cache', 2), 'none left');

		pg_image_encode_slot_release($first);

		$again = pg_image_encode_slot_take($folder . '/cache', 2);
		pg_assert_true(is_resource($again), 'a slot given back is free again');
		pg_assert_same(null, pg_image_encode_slot_take($folder . '/cache', 2), 'and taken');

		pg_image_encode_slot_release($again);
		pg_image_encode_slot_release($second);
		pg_image_encode_slot_release(null);

		pg_assert_same(null, pg_image_encode_slot_take('', 2), 'no directory, no slot');
		pg_assert_same(null, pg_image_encode_slot_take($folder . '/missing', 2), 'a folder that does not exist');

	} finally {
		image_encode_warmup_test_remove($folder);
	}
}

// With every slot taken a derivative is not made: the original is sent as
// busy, nothing is left behind, and it is made once a slot is free.
function test_image_derivative_busy_while_every_slot_is_taken()
{
	if (!image_encode_warmup_test_webp()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_encode_warmup_test_folder();
	$source = $folder . '/files/photo.jpg';
	$slots = array();

	try {
		image_encode_warmup_test_jpeg($source, 1200, 700, 4);

		for ($slot = 1; $slot <= pg_image_encode_slot_count(); $slot++) {
			$slots[] = pg_image_encode_slot_take($folder . '/cache');
		}

		$busy = pg_image_derivative(7, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('busy', $busy['status'], 'every slot taken');
		pg_assert_same($source, $busy['path'], 'the original meanwhile');
		pg_assert_same(array('.', '..'), scandir($folder . '/cache/7'), 'no derivative, lock or try marker left');

		pg_image_encode_slot_release(array_pop($slots));

		$made = pg_image_derivative(7, $source, array('width' => 480, 'format' => ''), $folder . '/cache');
		pg_assert_same('derivative', $made['status'], 'made once a slot is free');

		$made = pg_image_derivative(7, $source, array('width' => 960, 'format' => ''), $folder . '/cache');
		pg_assert_same('derivative', $made['status'], 'the slot was given back after the encode');

	} finally {
		foreach ($slots as $slot) {
			pg_image_encode_slot_release($slot);
		}

		image_encode_warmup_test_remove($folder);
	}
}

function test_image_warmup_wanted_needs_webp_and_own_address()
{
	pg_assert_true(pg_image_warmup_wanted(5), 'WebP and own address');
	pg_assert_true(pg_image_warmup_wanted(15), 'with AVIF and the CDN confirmation');
	pg_assert_false(pg_image_warmup_wanted(3), 'own address off');
	pg_assert_false(pg_image_warmup_wanted(4), 'own address without WebP');
	pg_assert_false(pg_image_warmup_wanted(0), 'nothing');
}

function test_image_warmup_state_and_due()
{
	$folder = image_encode_warmup_test_folder();
	$now = 1760000000;

	try {
		$state = pg_image_warmup_state($folder . '/cache');
		pg_assert_same(array('size' => 0, 'id' => 0, 'done_at' => 0), $state, 'no file: the start of a first pass');
		pg_assert_true(pg_image_warmup_due($state, $now), 'a first pass is due');

		pg_image_warmup_save($folder . '/cache', array('size' => 52000, 'id' => 31, 'done_at' => $now - 3600));
		$state = pg_image_warmup_state($folder . '/cache');
		pg_assert_same(array('size' => 52000, 'id' => 31, 'done_at' => $now - 3600), $state, 'read back');
		pg_assert_true(pg_image_warmup_due($state, $now), 'a pass under way goes on');

		pg_assert_false(pg_image_warmup_due(array('size' => 0, 'id' => 0, 'done_at' => $now - 3600), $now), 'through an hour ago');
		pg_assert_true(pg_image_warmup_due(array('size' => 0, 'id' => 0, 'done_at' => $now - 90000), $now), 'through more than a day ago');

		file_put_contents($folder . '/cache/warmup.json', '{"size":"x","id":-4');
		pg_assert_same(array('size' => 0, 'id' => 0, 'done_at' => 0), pg_image_warmup_state($folder . '/cache'), 'a broken file starts again');

	} finally {
		image_encode_warmup_test_remove($folder);
	}
}

function test_image_warmup_after_sql()
{
	pg_assert_same('', pg_image_warmup_after_sql(array('size' => 0, 'id' => 0, 'done_at' => 0)), 'the start of a pass');
	pg_assert_same(
		'((IFNULL(files.size, 0) < 52000) OR ((IFNULL(files.size, 0) = 52000) AND (files.id < 31)))',
		pg_image_warmup_after_sql(array('size' => '52000', 'id' => '31', 'done_at' => 0)),
		'smaller files, or the same size and older');
}

// A pass goes largest first, stops at its deadline after at least one
// picture, goes on from the cursor on the next tick, and once through starts
// over only after a day.
function test_image_warmup_run_moves_the_cursor()
{
	if (!image_encode_warmup_test_webp()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_encode_warmup_test_folder();
	$cache = $folder . '/cache';

	try {
		image_encode_warmup_test_jpeg($folder . '/files/small.jpg', 300, 200, 1);
		image_encode_warmup_test_jpeg($folder . '/files/large.jpg', 900, 600, 2);
		image_encode_warmup_test_jpeg($folder . '/files/middle.jpg', 600, 400, 3);

		$rows = array(
			array('id' => 11, 'name' => 'small.jpg', 'size' => filesize($folder . '/files/small.jpg')),
			array('id' => 12, 'name' => 'large.jpg', 'size' => filesize($folder . '/files/large.jpg')),
			array('id' => 13, 'name' => 'middle.jpg', 'size' => filesize($folder . '/files/middle.jpg')),
			array('id' => 14, 'name' => 'gone.jpg', 'size' => 1),
			array('id' => 15, 'name' => 'notes.txt', 'size' => 1),
		);

		$fetch = image_encode_warmup_test_fetch($rows);

		// No time at all: the largest picture, and the cursor after it.
		$tick = pg_image_warmup_run($cache, 0, pg_image_delivery_settings(null), $fetch, $folder . '/files');

		pg_assert_same('more', $tick['state'], 'stopped at the deadline');
		pg_assert_same(1, $tick['files'], 'one picture a tick at least');
		pg_assert_same(1, $tick['made'], 'its copy made');
		pg_assert_same(12, pg_image_warmup_state($cache)['id'], 'the cursor is on the largest');
		pg_assert_same(1, count(glob($cache . '/12/*.webp')), 'a WebP copy of the largest');
		pg_assert_same(array(), glob($cache . '/11/*'), 'not yet the smallest');

		// The rest, from the cursor.
		$tick = pg_image_warmup_run($cache, 10, pg_image_delivery_settings(null), $fetch, $folder . '/files');

		pg_assert_same('done', $tick['state'], 'through');
		pg_assert_same(4, $tick['files'], 'the other four rows');
		pg_assert_same(2, $tick['made'], 'two pictures made; a missing file and a text file skipped');
		pg_assert_same(1, count(glob($cache . '/11/*.webp')), 'the smallest made');
		pg_assert_same(1, count(glob($cache . '/13/*.webp')), 'the middle one made');

		$state = pg_image_warmup_state($cache);
		pg_assert_same(0, $state['id'], 'the cursor is back at the start');
		pg_assert_true($state['done_at'] >= time() - 5, 'the pass is through now');
		pg_assert_false(pg_image_warmup_due($state, time()), 'and not due for a day');

		// A pass over copies that are there makes nothing new.
		pg_image_warmup_save($cache, array('size' => 0, 'id' => 0, 'done_at' => 0));
		$tick = pg_image_warmup_run($cache, 10, pg_image_delivery_settings(null), $fetch, $folder . '/files');
		pg_assert_same(0, $tick['made'], 'nothing made twice');

	} finally {
		image_encode_warmup_test_remove($folder);
	}
}

// With every encode slot taken the pass gives way to visitors: it stops
// without moving the cursor past the picture it could not make.
function test_image_warmup_run_gives_way_when_slots_are_taken()
{
	if (!image_encode_warmup_test_webp()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_encode_warmup_test_folder();
	$cache = $folder . '/cache';
	$slots = array();

	try {
		image_encode_warmup_test_jpeg($folder . '/files/a.jpg', 600, 400, 5);
		image_encode_warmup_test_jpeg($folder . '/files/b.jpg', 300, 200, 6);

		$fetch = image_encode_warmup_test_fetch(array(
			array('id' => 21, 'name' => 'a.jpg', 'size' => filesize($folder . '/files/a.jpg')),
			array('id' => 22, 'name' => 'b.jpg', 'size' => filesize($folder . '/files/b.jpg')),
		));

		for ($slot = 1; $slot <= pg_image_encode_slot_count(); $slot++) {
			$slots[] = pg_image_encode_slot_take($cache);
		}

		$tick = pg_image_warmup_run($cache, 10, pg_image_delivery_settings(null), $fetch, $folder . '/files');

		pg_assert_same('busy', $tick['state'], 'gave way');
		pg_assert_same(0, $tick['files'], 'nothing gone through');
		pg_assert_same(0, pg_image_warmup_state($cache)['id'], 'the cursor did not move');

		foreach ($slots as $slot) {
			pg_image_encode_slot_release($slot);
		}

		$slots = array();

		$tick = pg_image_warmup_run($cache, 10, pg_image_delivery_settings(null), $fetch, $folder . '/files');

		pg_assert_same('done', $tick['state'], 'through once the slots are free');
		pg_assert_same(2, $tick['made'], 'both made');

	} finally {
		foreach ($slots as $slot) {
			pg_image_encode_slot_release($slot);
		}

		image_encode_warmup_test_remove($folder);
	}
}
