<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cloud destinations of the remote backup copy that need no
 * connection (includes/fn/backup_cloud.php): the folder a destination keeps,
 * the parts an archive is sent in, where an unfinished upload goes on and how
 * a refusal reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_backup_cloud_types_are_remote_types()
{
	foreach (pg_backup_cloud_types() as $type => $cloud) {
		pg_assert_same($type, pg_backup_remote_type($type), $type . ' is a remote type');
		pg_assert_true(pg_backup_cloud_is_type($type), $type . ' is a cloud type');
		pg_assert_true(is_array(pg_connect_provider($cloud['provider'])), $type . ': its provider exists');
		pg_assert_true(pg_connect_purpose_allows(pg_connect_purpose('backup'), $cloud['provider'], 'site'), $type . ': the backup purpose serves it');
	}

	pg_assert_false(pg_backup_cloud_is_type('ftp'), 'FTP is not a cloud type');
	pg_assert_same('', pg_backup_remote_type('box'), 'unknown type');
	pg_assert_same('', pg_backup_remote_type(array('gdrive')), 'not a word');
	pg_assert_false(pg_connect_purpose_allows(pg_connect_purpose('backup'), 'google', 'person'), 'only the site\'s own account');
}

function test_backup_cloud_folder_keeps_safe_names_only()
{
	pg_assert_same('', pg_backup_cloud_folder(''), 'empty is the default');
	pg_assert_same('', pg_backup_cloud_folder(' / '), 'slashes alone');
	pg_assert_same('example.com', pg_backup_cloud_folder(' /example.com/ '), 'trimmed');
	pg_assert_same('Backups/Site 1/2026', pg_backup_cloud_folder('Backups\\Site 1//2026'), 'levels');
	pg_assert_same(null, pg_backup_cloud_folder('a/b/c/d'), 'four levels');
	pg_assert_same(null, pg_backup_cloud_folder('../etc'), 'a dot first');
	pg_assert_same(null, pg_backup_cloud_folder('name.'), 'a dot last');
	pg_assert_same(null, pg_backup_cloud_folder('a:b'), 'a colon');
	pg_assert_same(null, pg_backup_cloud_folder("a'b"), 'a quote');
	pg_assert_same(null, pg_backup_cloud_folder(str_repeat('a', 61)), 'too long');
	pg_assert_same(null, pg_backup_cloud_folder(array('x')), 'not a word');
}

function test_backup_cloud_default_folder_is_the_host_name()
{
	pg_assert_same('www.example.com', pg_backup_cloud_default_folder('WWW.Example.com'), 'host name');
	pg_assert_same('localhost-8080', pg_backup_cloud_default_folder('localhost:8080'), 'a port');
	pg_assert_same('pinegrap', pg_backup_cloud_default_folder(''), 'none');
	pg_assert_same(pg_backup_cloud_default_folder('a.b'), pg_backup_cloud_folder(pg_backup_cloud_default_folder('a.b')), 'a valid folder');
}

function test_backup_cloud_remote_normalize_keeps_account_and_folder()
{
	pg_assert_same(array('connection_id' => 0, 'folder' => ''), pg_backup_remote_normalize('gdrive', 'nope'), 'defaults');
	pg_assert_same(array('connection_id' => 7, 'folder' => 'site'), pg_backup_remote_normalize('dropbox', array('connection_id' => '7', 'folder' => '/site/', 'password' => 'x')), 'typed, nothing else carried');
	pg_assert_same(array('connection_id' => 0, 'folder' => ''), pg_backup_remote_normalize('onedrive', array('connection_id' => -3, 'folder' => '..')), 'a bad id and folder');
}

function test_backup_cloud_remote_decode_has_every_type()
{
	$remote = pg_backup_remote_decode('');

	pg_assert_same(pg_backup_remote_types(), array_keys($remote), 'every type, in order');
	pg_assert_same(0, $remote['onedrive']['connection_id'], 'nothing stored');
}

function test_backup_cloud_chunks_cover_the_file()
{
	pg_assert_same(array(array(0, 0)), pg_backup_cloud_chunks(0, 10), 'an empty file');
	pg_assert_same(array(array(0, 7)), pg_backup_cloud_chunks(7, 10), 'one part');
	pg_assert_same(array(array(0, 10), array(10, 10)), pg_backup_cloud_chunks(20, 10), 'exact parts');
	pg_assert_same(array(array(0, 10), array(10, 10), array(20, 1)), pg_backup_cloud_chunks(21, 10), 'a short last part');

	$size = pg_backup_cloud_chunk_size();

	pg_assert_same(0, $size % (256 * 1024), 'a multiple of Drive\'s 256 KiB');
	pg_assert_same(0, $size % (320 * 1024), 'a multiple of Graph\'s 320 KiB');
}

function test_backup_cloud_content_range()
{
	pg_assert_same('bytes 0-9/21', pg_backup_cloud_content_range(0, 10, 21), 'first part');
	pg_assert_same('bytes 20-20/21', pg_backup_cloud_content_range(20, 1, 21), 'last byte');
	pg_assert_same('bytes */0', pg_backup_cloud_content_range(0, 0, 0), 'nothing to send');
}

function test_backup_cloud_next_offset_reads_both_services()
{
	pg_assert_same(0, pg_backup_cloud_next_offset(''), 'Drive without a Range: nothing kept');
	pg_assert_same(10485760, pg_backup_cloud_next_offset('bytes=0-10485759'), 'Drive');
	pg_assert_same(26, pg_backup_cloud_next_offset('26-'), 'Graph');
	pg_assert_same(26, pg_backup_cloud_next_offset('26-30'), 'Graph, a closed range');
	pg_assert_same(null, pg_backup_cloud_next_offset('bytes=5-9'), 'a range that does not start at zero');
	pg_assert_same(null, pg_backup_cloud_next_offset('soon'), 'not a range');
}

function test_backup_cloud_drive_quote_escapes()
{
	pg_assert_same("'a\\'b\\\\c'", pg_backup_cloud_drive_quote("a'b\\c"), 'quote and backslash');
	pg_assert_same("'root'", pg_backup_cloud_drive_quote('root'), 'plain');
}

function test_backup_cloud_dropbox_arg_is_ascii()
{
	$arg = pg_backup_cloud_dropbox_arg(array('path' => '/şube/a.zip', 'mode' => 'overwrite'));

	pg_assert_same(1, preg_match('/^[\x20-\x7E]+$/', $arg), 'ASCII only');
	pg_assert_same('/şube/a.zip', json_decode($arg, true)['path'], 'the same path');
	pg_assert_contains('"/', $arg, 'slashes are not escaped');
}

function test_backup_cloud_error_reads_each_service()
{
	pg_assert_contains('insufficientPermissions', pg_backup_cloud_error(array('status' => 403, 'body' => '{"error":{"code":403,"message":"insufficientPermissions"}}')), 'Google');
	pg_assert_contains('itemNotFound: The resource could not be found.', pg_backup_cloud_error(array('status' => 404, 'body' => '{"error":{"code":"itemNotFound","message":"The resource could not be found."}}')), 'Graph');
	pg_assert_contains('path/insufficient_space/', pg_backup_cloud_error(array('status' => 409, 'body' => '{"error_summary":"path/insufficient_space/","error":{}}')), 'Dropbox');
	pg_assert_contains('Error in call to API function', pg_backup_cloud_error(array('status' => 400, 'body' => 'Error in call to API function "files/upload"')), 'Dropbox plain text');
	pg_assert_contains('503', pg_backup_cloud_error(array('status' => 503, 'body' => '<html>busy</html>')), 'a page is not quoted');
	pg_assert_same('timed out', pg_backup_cloud_error(array('status' => 0, 'error' => 'timed out')), 'no answer');
}
