<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how get_file.php answers a file in the object store
 * (includes/file_store_serve.php) and the general job's passes over stored
 * rows (includes/fn/file_storage.php): the store settings read from a
 * connect_apps row, the answer chosen, the Content-Disposition asked of the
 * store, the cached copy found, which objects are orphans and which files
 * never move.
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
require_once(PG_FUNCTIONS_DIR . '/includes/file_store_serve.php');

function test_object_storage_serve_config_from_row()
{
	$config = pg_file_serve_store_config_from_row(array(
		'client_id' => ' AKID ',
		'options'   => json_encode(array('endpoint' => 'storage.example.com', 'bucket' => 'media', 'prefix' => '/site/', 'path_style' => true)),
		'enabled'   => '1',
	), 'SECRET');

	pg_assert_same('https://storage.example.com', $config['endpoint']);
	pg_assert_same('media', $config['bucket']);
	pg_assert_same('site', $config['prefix']);
	pg_assert_same('AKID', $config['key_id']);
	pg_assert_same('SECRET', $config['secret']);
	pg_assert_true($config['path_style']);
	pg_assert_true($config['enabled']);
	pg_assert_true(pg_os_ready($config), 'as pg_os_settings() would give it');

	$off = pg_file_serve_store_config_from_row(array('client_id' => 'A', 'options' => 'not json', 'enabled' => 0), 'S');
	pg_assert_false($off['enabled']);
	pg_assert_same('', $off['endpoint']);
}

function test_object_storage_serve_presign_from_row()
{
	$config = pg_file_serve_store_config_from_row(array(
		'client_id' => 'AKID',
		'options'   => json_encode(array('endpoint' => 'https://storage.example.com', 'bucket' => 'media', 'path_style' => true)),
		'enabled'   => 1,
	), 'SECRET');

	$url = pg_os_presign($config, 'files/4/a b.pdf', 300, array(
		'content_type' => 'application/pdf',
		'disposition'  => pg_file_serve_disposition('a b.pdf', true),
	), '20261102T120000Z');

	pg_assert_contains('https://storage.example.com/media/files/4/a%20b.pdf?', $url);
	pg_assert_contains('X-Amz-Expires=300', $url);
	pg_assert_contains('response-content-type=application%2Fpdf', $url);
	pg_assert_contains('response-content-disposition=inline%3B%20filename%3D%22a%20b.pdf%22', $url);
}

function test_object_storage_serve_plan()
{
	pg_assert_same('local', pg_file_serve_plan(true, '/c/4.jpg', 'https://x'), 'a replaced file is on the disk again');
	pg_assert_same('copy', pg_file_serve_plan(false, '/c/4.jpg', ''));
	pg_assert_same('redirect', pg_file_serve_plan(false, '', 'https://x'));
	pg_assert_same('unavailable', pg_file_serve_plan(false, '', ''));
}

function test_object_storage_serve_disposition()
{
	pg_assert_same('inline; filename="report.pdf"', pg_file_serve_disposition('report.pdf', true));
	pg_assert_same('attachment; filename="ab.zip"', pg_file_serve_disposition("a\"\r\nb.zip", false), 'quotes and line breaks go');
	pg_assert_same("inline; filename=\"_ehir.jpg\"; filename*=UTF-8''%C5%9Fehir.jpg", pg_file_serve_disposition('şehir.jpg', true));
}

function test_object_storage_serve_cached_copy()
{
	if (!defined('FILE_DIRECTORY_PATH')) {
		pg_assert_same('', pg_file_serve_cached_copy(4, 'jpg', 3));
		return;
	}

	pg_assert_same('', pg_file_serve_cached_copy(999999999, 'jpg', 3), 'no copy');
}

function test_object_storage_serve_orphans()
{
	$now = 1000000;
	$old = $now - 2 * 86400;

	$items = array(
		array('key' => 'files/4/a.jpg', 'modified' => $old),
		array('key' => 'files/5/b.jpg', 'modified' => $old),
		array('key' => 'files/6/c.jpg', 'modified' => $now - 60),
		array('key' => 'backups/site.zip', 'modified' => $old),
		array('key' => 'files/x/d.jpg', 'modified' => $old),
		array('key' => 'files/7/e.jpg', 'modified' => 0),
	);

	pg_assert_same(array('files/5/b.jpg'), pg_file_storage_orphans($items, array('files/4/a.jpg' => true), $now),
		'only old move-job keys no row names');
}

function test_object_storage_serve_scripts_stay_local()
{
	pg_assert_false(pg_file_storage_movable(array('name' => 'site.css', 'design' => 0, 'theme' => 0)));
	pg_assert_false(pg_file_storage_movable(array('name' => 'menu.JS', 'design' => 0, 'theme' => 0)));
	pg_assert_true(pg_file_storage_movable(array('name' => 'notes.json', 'design' => 0, 'theme' => 0)));
}
