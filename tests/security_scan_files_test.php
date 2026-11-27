<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Security Scan's file scan (includes/fn/security_scan_files.php):
 * the name rules, the shell marks and PHP tag, the server settings baseline,
 * which file gets which sighting, the walk order and its resume, and the
 * chunked read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_security_files_executable_names()
{
	pg_assert_same('php', pg_security_name_executable('data/files/shell.php'));
	pg_assert_same('php', pg_security_name_executable('data/files/shell.php.jpg'), 'a double extension');
	pg_assert_same('phtml', pg_security_name_executable('data/files/a.PHTML'));
	pg_assert_same('phar', pg_security_name_executable('x.phar'));
	pg_assert_same('', pg_security_name_executable('data/files/shell_php.jpg'), 'as prepare_file_name() stores it');
	pg_assert_same('', pg_security_name_executable('data/files/php'), 'no extension');
	pg_assert_same('', pg_security_name_executable('data/files/php.jpg'), 'the first segment is the name');
	pg_assert_true(pg_security_name_is_php('a/b.php5'));
	pg_assert_false(pg_security_name_is_php('a/b.php.jpg'));
	pg_assert_true(pg_security_name_is_picture('a/B.JPG'));
	pg_assert_false(pg_security_name_is_picture('a/b.pdf'));
	pg_assert_true(pg_security_name_is_server_config('data/files/.htaccess'));
	pg_assert_true(pg_security_name_is_server_config('.user.ini'));
	pg_assert_false(pg_security_name_is_server_config('htaccess.txt'));
}

function test_security_files_shell_marks()
{
	pg_assert_same('eval(base64_decode(', pg_security_text_shell_mark('<?php @eval( base64_decode ($x));'));
	pg_assert_same('assert($_', pg_security_text_shell_mark('<?php assert($_POST["c"]);'));
	pg_assert_same('eval($_', pg_security_text_shell_mark('<?php eval($_REQUEST[1]);'));
	pg_assert_same('eval(gzinflate(', pg_security_text_shell_mark('eval(gzinflate(base64_decode("x")))'));
	pg_assert_same('system($_', pg_security_text_shell_mark('shell_exec($_GET["c"])'));
	pg_assert_same('', pg_security_text_shell_mark('<?php $x = base64_decode($y); echo $x;'));
	pg_assert_same('', pg_security_text_shell_mark('<?php medieval($a);'), 'a word ending in eval');
}

function test_security_files_php_tag()
{
	pg_assert_true(pg_security_text_has_php_tag("\xFF\xD8GIF<?php system(1);"));
	pg_assert_true(pg_security_text_has_php_tag("abc<?PHP\n"));
	pg_assert_true(pg_security_text_has_php_tag('abc<?php'), 'at the end of the text');
	pg_assert_false(pg_security_text_has_php_tag("abc<?= 1 ?>"), 'short tags turn up by chance in binary data');
	pg_assert_false(pg_security_text_has_php_tag('<?phpx'));
	pg_assert_false(pg_security_text_has_php_tag('<?xml version="1.0"?>'));
}

function test_security_files_config_verdict()
{
	pg_assert_same('', pg_security_config_verdict(null, '.htaccess', 'a'), 'the first run takes files as they are');
	pg_assert_same('', pg_security_config_verdict(array('.htaccess' => 'a'), '.htaccess', 'a'));
	pg_assert_same('changed', pg_security_config_verdict(array('.htaccess' => 'a'), '.htaccess', 'b'));
	pg_assert_same('new', pg_security_config_verdict(array('.htaccess' => 'a'), 'data/files/.htaccess', 'a'));
	pg_assert_same('new', pg_security_config_verdict(array(), '.user.ini', 'a'), 'an empty baseline is a baseline');
}

function test_security_files_reads()
{
	pg_assert_same(array('shell'), pg_security_file_reads('shell.php', false), 'a root PHP file outside the reference');
	pg_assert_same(array(), pg_security_file_reads('index.php', true), 'in the reference');
	pg_assert_same(array(), pg_security_file_reads('index.php', null), 'no reference: not judged');
	pg_assert_same(array(), pg_security_file_reads('data/config.php', null), 'shipped outside the reference');
	pg_assert_same(array('shell'), pg_security_file_reads('data/temp/x.php', null));
	pg_assert_same(array('shell'), pg_security_file_reads('data/temp/lang_tr.php', null), 'the language cache is still read');
	pg_assert_same(array('shell'), pg_security_file_reads('assets/x.php', true), 'outside the reference scope');
	pg_assert_same(array('php_tag'), pg_security_file_reads('data/files/a.jpg', null));
	pg_assert_same(array('shell'), pg_security_file_reads('data/files/a.php.jpg', null));
	pg_assert_same(array('shell'), pg_security_file_reads('data/layouts/12.php', null));
	pg_assert_same(array('digest'), pg_security_file_reads('includes/.htaccess', true));
	pg_assert_same(array(), pg_security_file_reads('assets/images/logo.png', null), 'pictures are read only in the upload folders');
}

function test_security_files_sightings()
{
	$kinds = function (array $sightings) {
		return array_map(function ($s) {
			return $s['kind'] . ':' . $s['severity'];
		}, $sightings);
	};
	$none = array();

	pg_assert_same(array('file_php_unknown:3'), $kinds(pg_security_file_sightings('x.php', false, $none)));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('x.php', true, $none)));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('x.php', null, $none)), 'no reference');
	pg_assert_same(array('file_php_unknown:3', 'file_shell_mark:4'), $kinds(pg_security_file_sightings('includes/x.php', false, array('shell' => 'assert($_'))));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('install/index.php', null, $none)));
	pg_assert_same(array('file_php_unknown:3'), $kinds(pg_security_file_sightings('install/shell.php', null, $none)));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('data/temp/lang_tr.php', null, $none)), 'the language cache');
	pg_assert_same(array('file_php_unknown:3'), $kinds(pg_security_file_sightings('data/temp/lang_tr.php.php', null, $none)));
	pg_assert_same(array('file_upload_executable:4'), $kinds(pg_security_file_sightings('data/files/a.php.jpg', null, $none)));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('data/files/a_php.jpg', null, $none)));
	pg_assert_same(array('file_picture_php:3'), $kinds(pg_security_file_sightings('data/files/a.gif', null, array('php_tag' => true))));
	pg_assert_same(array(), $kinds(pg_security_file_sightings('data/layouts/12.php', null, $none)), 'a custom layout');
	pg_assert_same(array('file_upload_executable:4'), $kinds(pg_security_file_sightings('data/layouts/x.php', null, $none)));
	pg_assert_same(array('file_shell_mark:4'), $kinds(pg_security_file_sightings('data/layouts/12.php', null, array('shell' => 'eval($_'))));
	pg_assert_same(array('file_server_config:2'), $kinds(pg_security_file_sightings('.htaccess', true, array('config' => 'changed'))));

	// A place an earlier release carried, on the Cleanup tool's list: retired
	// (medium), at the root, inside a listed folder of includes/ and outside
	// the reference's scope; a shell mark in it is still a shell mark.
	pg_assert_same(array('file_php_retired:2'), $kinds(pg_security_file_sightings('page_designer.php', false, $none)));
	pg_assert_same(array('file_php_retired:2'), $kinds(pg_security_file_sightings('includes/phpexcel/PHPExcel/IOFactory.php', false, $none)));
	pg_assert_same(array('file_php_retired:2'), $kinds(pg_security_file_sightings('assets/lib/image_editor/save.php', null, $none)));
	pg_assert_same(array('file_php_unknown:3'), $kinds(pg_security_file_sightings('page_designer2.php', false, $none)), 'not listed');
	pg_assert_same(array('file_php_unknown:3'), $kinds(pg_security_file_sightings('includes/phpexcel2/x.php', false, $none)), 'a listed folder is no prefix of a name');
	pg_assert_same(array(), $kinds(pg_security_file_sightings('page_designer.php', null, $none)), 'not judged without a reference');
	pg_assert_same(array('file_php_retired:2', 'file_shell_mark:4'), $kinds(pg_security_file_sightings('view_files.php', false, array('shell' => 'eval(base64_decode('))));
	pg_assert_same(array('shell'), pg_security_file_reads('view_files.php', false), 'a retired file is read for shell marks');
	pg_assert_same(array('file_server_config:3'), $kinds(pg_security_file_sightings('data/files/sub/.htaccess', null, array('config' => 'new'))), 'in an upload folder');

	$sightings = pg_security_file_sightings('data/files/a.phtml', null, $none);
	pg_assert_same('.phtml', $sightings[0]['detail']);
	pg_assert_same(pg_security_fingerprint('file_upload_executable', 'data/files/a.phtml'), $sightings[0]['fingerprint']);
}

function test_security_files_place_order_and_resume()
{
	pg_assert_true(pg_security_place_compare('a/z', 'a.b') < 0, 'inside a folder comes before a longer name');
	pg_assert_true(pg_security_place_compare('a', 'a/b') < 0);
	pg_assert_same(0, pg_security_place_compare('a/b', 'a/b'));
	pg_assert_true(pg_security_place_compare('b', 'a/z') > 0);

	pg_assert_false(pg_security_folder_done('includes', ''));
	pg_assert_false(pg_security_folder_done('includes', 'includes/fn/a.php'), 'the cursor is inside');
	pg_assert_true(pg_security_folder_done('assets', 'includes/fn/a.php'));
	pg_assert_false(pg_security_folder_done('page', 'includes/fn/a.php'));
	pg_assert_true(pg_security_walk_skipped('data/backups'));
	pg_assert_false(pg_security_walk_skipped('data/temp'));
	pg_assert_true(pg_security_walk_skipped('data/temp/restore_ab12'), 'a restore being unpacked');
	pg_assert_false(pg_security_walk_skipped('data/temp/restore_ab12/x'));
}

function test_security_files_walk_resumes_after_cursor()
{
	$root = sys_get_temp_dir() . '/pg_security_walk_' . getmypid();
	@mkdir($root . '/a/b', 0777, true);
	@mkdir($root . '/data/backups', 0777, true);

	foreach (array('a/b/1.txt', 'a/2.txt', 'a.txt', 'c.txt', 'data/backups/x.php') as $file) {
		file_put_contents($root . '/' . $file, 'x');
	}

	$all = array_keys(iterator_to_array(pg_security_files_walk($root, '', '')));
	pg_assert_same(array('a/2.txt', 'a/b/1.txt', 'a.txt', 'c.txt'), $all, 'sorted, data/backups skipped');

	$rest = array_keys(iterator_to_array(pg_security_files_walk($root, '', 'a/b/1.txt')));
	pg_assert_same(array('a.txt', 'c.txt'), $rest);

	$prefixed = array_keys(iterator_to_array(pg_security_files_walk($root . '/a', 'data/files', 'data/files/2.txt')));
	pg_assert_same(array('data/files/b/1.txt'), $prefixed);

	foreach (array('a/b/1.txt', 'a/2.txt', 'a.txt', 'c.txt', 'data/backups/x.php') as $file) {
		@unlink($root . '/' . $file);
	}

	foreach (array('a/b', 'a', 'data/backups', 'data', '') as $folder) {
		@rmdir($root . '/' . $folder);
	}
}

function test_security_files_read_finds_a_mark_across_chunks()
{
	$file = tempnam(sys_get_temp_dir(), 'pgsec');
	// The mark straddles the first chunk's end, then a later one's.
	file_put_contents($file, str_repeat('a', PG_SECURITY_FILES_READ_CHUNK - 6) . ' eval(base64_decode("x"));');
	$facts = pg_security_files_read($file, array('shell'));
	pg_assert_same('eval(base64_decode(', $facts['shell']);

	file_put_contents($file, str_repeat('a', 3 * PG_SECURITY_FILES_READ_CHUNK - 6) . ' eval(base64_decode("x"));');
	$facts = pg_security_files_read($file, array('shell'));
	pg_assert_same('eval(base64_decode(', $facts['shell']);

	file_put_contents($file, "GIF89a<?php echo 1;");
	$facts = pg_security_files_read($file, array('php_tag', 'digest'));
	pg_assert_true($facts['php_tag']);
	pg_assert_same(sha1("GIF89a<?php echo 1;"), $facts['digest']);
	@unlink($file);
}

function test_security_files_state_and_judged_kinds()
{
	$state = pg_security_files_state(null);
	pg_assert_same(0, $state['started']);
	pg_assert_same(null, $state['baseline']);

	$state = pg_security_files_state(array('baseline' => array('.htaccess' => 'a'), 'configs' => 'bad', 'other' => 1));
	pg_assert_same(array('.htaccess' => 'a'), $state['baseline']);
	pg_assert_same(array(), $state['configs']);
	pg_assert_false(isset($state['other']));

	pg_assert_same(array_keys(pg_security_file_kinds()), pg_security_files_judged_kinds('github'));
	pg_assert_false(in_array('file_php_unknown', pg_security_files_judged_kinds(''), true), 'not judged without a reference');
	pg_assert_false(in_array('file_php_retired', pg_security_files_judged_kinds(''), true), 'not judged without a reference');
	pg_assert_true(in_array('file_upload_executable', pg_security_files_judged_kinds(''), true));

	foreach (array_keys(pg_security_file_kinds()) as $kind) {
		pg_assert_true(pg_security_kind_valid($kind), $kind);
	}
}

function test_security_files_shipped_php_list_matches_the_repository()
{
	$software = dirname(__DIR__) . '/pinegrap/';

	foreach (pg_security_php_shipped_outside_reference() as $place) {
		pg_assert_true(is_file($software . $place) || ($place === 'data/config.php'), $place);
	}

	// Every PHP file the repository ships outside the reference's scope is on
	// the list, or every site would report it.
	$folders = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($software, FilesystemIterator::SKIP_DOTS));

	foreach ($folders as $file) {
		$place = str_replace('\\', '/', substr($file->getPathname(), strlen($software)));

		if (
			!pg_security_name_is_php($place) || pg_security_place_in_reference_scope($place)
			|| (strpos($place, 'data/backups/') === 0) || (pg_security_place_area($place) !== '')
		) {
			continue;
		}

		pg_assert_true(pg_security_php_known_outside_reference($place), $place . ' is not on the list');
	}
}
