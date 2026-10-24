<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_dir_writable() (includes/image_engine.php): a folder that is
 * not there takes nothing, one is_writable() allows takes a file, and one it
 * calls closed is asked by making a file in it on Windows only, where
 * is_writable() reads the folder's read-only attribute, which stops nothing.
 * The answer is yes only when the file landed inside that folder.
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
 * A folder of its own under the system's temporary folder.
 *
 * @return string
 */
function pg_test_folder_writable_dir()
{
	$dir = sys_get_temp_dir() . '/pg_folder_writable_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	return $dir;
}

// A stream wrapper whose every path is a folder with no write bit for anyone
// (mode 0555): is_dir() says yes and is_writable() says no, whoever runs the
// tests, since PHP reads the mode bits of a wrapper's stat. A folder made
// read-only with chmod() would not stop root. unlink() is recorded, so a test
// can see the probe file was taken away again.
class pg_folder_writable_test_closed_folder
{
	public static $unlinked = array();

	public $context;

	public function url_stat($path, $flags)
	{
		return array('mode' => 040555, 'nlink' => 2);
	}

	public function unlink($path)
	{
		self::$unlinked[] = $path;

		return true;
	}
}

/**
 * Runs $test with the closed-folder wrapper registered as pgdirclosed://.
 *
 * @param callable $test
 */
function pg_test_folder_writable_closed(callable $test)
{
	pg_folder_writable_test_closed_folder::$unlinked = array();
	stream_wrapper_register('pgdirclosed', 'pg_folder_writable_test_closed_folder');

	try {
		$test();
	} finally {
		stream_wrapper_unregister('pgdirclosed');
	}
}

// Nothing to write into: no folder, an empty path, a file.
function test_folder_writable_needs_a_folder()
{
	$dir = pg_test_folder_writable_dir();
	file_put_contents($dir . '/plain', 'x');

	foreach (array(true, false) as $windows) {
		$label = $windows ? 'windows' : 'elsewhere';
		pg_assert_false(pg_dir_writable($dir . '/missing', $windows), $label . ': a folder that is not there');
		pg_assert_false(pg_dir_writable('', $windows), $label . ': no path');
		pg_assert_false(pg_dir_writable($dir . '/plain', $windows), $label . ': a file');
	}

	@unlink($dir . '/plain');
	@rmdir($dir);
}

// A folder is_writable() allows is the answer as it stands, without a probe.
function test_folder_writable_trusts_a_yes()
{
	$dir = pg_test_folder_writable_dir();
	$probed = 0;

	$probe = function ($directory, $prefix) use (&$probed) {
		$probed++;
		return false;
	};

	pg_assert_true(pg_dir_writable($dir), 'this server');
	pg_assert_true(pg_dir_writable($dir, false, $probe), 'elsewhere');
	pg_assert_true(pg_dir_writable($dir, true, $probe), 'windows');
	pg_assert_same(0, $probed, 'no file made to ask');

	@rmdir($dir);
}

// Away from Windows a folder is_writable() calls closed is closed, and
// nothing is written to find out.
function test_folder_writable_trusts_a_no_elsewhere()
{
	pg_test_folder_writable_closed(function () {
		$probed = 0;

		$probe = function ($directory, $prefix) use (&$probed) {
			$probed++;
			return $directory . '/pgw1234.tmp';
		};

		pg_assert_true(is_dir('pgdirclosed://temp'), 'the stand-in is a folder');
		pg_assert_false(is_writable('pgdirclosed://temp'), 'that is_writable() calls closed');
		pg_assert_false(pg_dir_writable('pgdirclosed://temp', false, $probe), 'closed');
		pg_assert_same(0, $probed, 'no file made to ask');
	});
}

// On Windows the same folder takes files when a file can be made in it, and
// that file is removed again.
function test_folder_writable_asks_windows_by_making_a_file()
{
	pg_test_folder_writable_closed(function () {
		$asked = array();

		$probe = function ($directory, $prefix) use (&$asked) {
			$asked[] = array($directory, $prefix);
			return $directory . '/pgw1234.tmp';
		};

		pg_assert_true(pg_dir_writable('pgdirclosed://temp', true, $probe), 'the file was made there');
		pg_assert_same(array(array('pgdirclosed://temp', 'pgw')), $asked, 'one file, in that folder');
		pg_assert_same(array('pgdirclosed://temp/pgw1234.tmp'), pg_folder_writable_test_closed_folder::$unlinked, 'and taken away again');

		// a trailing separator and Windows separators in the answer are the same folder
		pg_assert_true(pg_dir_writable('pgdirclosed://temp/', true, $probe), 'trailing slash');
		pg_assert_true(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) {
			return 'pgdirclosed:\\\\temp\\pgw1234.tmp';
		}), 'backslashes');
	});
}

// No file could be made: closed.
function test_folder_writable_windows_probe_refused()
{
	pg_test_folder_writable_closed(function () {
		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) {
			return false;
		}), 'tempnam() said false');

		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) {
			return '';
		}), 'an empty answer');
	});
}

// A file that landed anywhere else says nothing about this folder, and is
// removed all the same. tempnam() itself does this: a folder it cannot use
// sends the file to the system's temporary folder.
function test_folder_writable_windows_probe_landed_elsewhere()
{
	$dir = pg_test_folder_writable_dir();

	pg_test_folder_writable_closed(function () use ($dir) {
		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) use ($dir) {
			file_put_contents($dir . '/pgw1234.tmp', '');
			return $dir . '/pgw1234.tmp';
		}), 'a file in another folder');
		pg_assert_false(is_file($dir . '/pgw1234.tmp'), 'removed');

		// a folder whose name starts like this one's is another folder
		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) {
			return 'pgdirclosed://temporary/pgw1234.tmp';
		}), 'a sibling with a longer name');

		$made = '';

		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true, function ($directory, $prefix) use (&$made) {
			$made = tempnam($directory, $prefix);
			return $made;
		}), 'tempnam() fell back to the system temporary folder');
		pg_assert_true(($made !== '') && ($made !== false) && (strpos($made, 'pgdirclosed://') !== 0), 'it did land elsewhere');
		pg_assert_false(is_file((string) $made), 'and that file was removed');

		pg_assert_false(pg_dir_writable('pgdirclosed://temp', true), 'with tempnam() itself');
	});

	@rmdir($dir);
}
