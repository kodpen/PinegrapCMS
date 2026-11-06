<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the entry names pg_extract_archive() (includes/fn/update.php)
 * accepts: an update package or a rollback archive whose entry leaves the
 * destination is refused before anything is written or deleted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A scratch folder with a destination inside it and an archive of the given
// entries next to it.
function pg_test_extract_setup(array $entries)
{
	$base = sys_get_temp_dir() . '/pg_extract_' . bin2hex(random_bytes(6));
	mkdir($base . '/root', 0755, true);

	$zip = new ZipArchive();
	$zip->open($base . '/package.zip', ZipArchive::CREATE);

	foreach ($entries as $name => $body) {
		if (substr($name, -1) === '/') {
			$zip->addEmptyDir(rtrim($name, '/'));
		} else {
			$zip->addFromString($name, $body);
		}
	}

	$zip->close();

	return $base;
}

function pg_test_extract_cleanup($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $name) {
			if ($name !== '.' && $name !== '..') {
				pg_test_extract_cleanup($path . '/' . $name);
			}
		}

		@rmdir($path);
	} elseif (file_exists($path) || is_link($path)) {
		@chmod($path, 0644);
		@unlink($path);
	}
}

// A plain package lands whole.
function test_extract_archive_writes_plain_entries()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$base = pg_test_extract_setup(array('pinegrap/a.php' => 'a', 'index.php' => 'i'));
	$result = pg_extract_archive($base . '/package.zip', $base . '/root');

	pg_assert_true($result['ok']);
	pg_assert_same('a', file_get_contents($base . '/root/pinegrap/a.php'));
	pg_assert_same('i', file_get_contents($base . '/root/index.php'));

	pg_test_extract_cleanup($base);
}

// An entry that climbs out of the destination stops the whole archive: no
// file of it is written, inside or outside.
function test_extract_archive_refuses_entry_leaving_destination()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	foreach (array('../escape.php', 'pinegrap/../../escape.php', '/escape.php', 'pinegrap/./a.php', 'pinegrap//a.php') as $bad) {
		$base = pg_test_extract_setup(array('pinegrap/a.php' => 'a', $bad => 'x'));
		$result = pg_extract_archive($base . '/package.zip', $base . '/root');

		pg_assert_false($result['ok'], $bad);
		pg_assert_same(array($bad), $result['blocked'], $bad);
		pg_assert_false(file_exists($base . '/root/pinegrap/a.php'), $bad);
		pg_assert_false(file_exists($base . '/escape.php'), $bad);

		pg_test_extract_cleanup($base);
	}
}

// The check before extraction never deletes a read-only file outside the
// destination that an entry names.
function test_extract_archive_keeps_file_outside_destination()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$base = pg_test_extract_setup(array('../keep.txt' => 'x'));
	file_put_contents($base . '/keep.txt', 'mine');
	chmod($base . '/keep.txt', 0444);

	$result = pg_extract_archive($base . '/package.zip', $base . '/root');

	pg_assert_false($result['ok']);
	clearstatcache();
	pg_assert_same('mine', @file_get_contents($base . '/keep.txt'));

	pg_test_extract_cleanup($base);
}

// A directory entry that leaves the destination is refused too.
function test_extract_archive_refuses_directory_leaving_destination()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$base = pg_test_extract_setup(array('pinegrap/' => '', '../outside/' => '', 'pinegrap/a.php' => 'a'));
	$result = pg_extract_archive($base . '/package.zip', $base . '/root');

	pg_assert_false($result['ok']);
	pg_assert_false(is_dir($base . '/outside'));

	pg_test_extract_cleanup($base);
}
