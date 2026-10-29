<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cloud file picker of the Upload File screen
 * (includes/fn/cloud_file_picker.php, add_file.php): which address a chosen
 * file is read from, the refusals before and during the download, and the
 * hand-off to pg_file_store_upload(), the function every browser upload
 * goes through, with the same refusals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloud_file_picker.php');

// A Drive file id becomes its metadata and its bytes on Google's API host,
// both read with the person's token; anything else is refused.
function test_cloud_picker_google_id_maps_to_the_drive_api()
{
	$source = pg_cloud_picker_source('google', array('id' => '1AbC-dEf_GhIjKlMnOp'));

	pg_assert_true($source['ok'], 'a Drive id is taken');
	pg_assert_true($source['token'], 'read with the token');
	pg_assert_same('https://www.googleapis.com/drive/v3/files/1AbC-dEf_GhIjKlMnOp?alt=media&supportsAllDrives=true', $source['url'], 'the bytes');
	pg_assert_same('https://www.googleapis.com/drive/v3/files/1AbC-dEf_GhIjKlMnOp?fields=name%2CmimeType%2Csize&supportsAllDrives=true', $source['meta_url'], 'the metadata');

	foreach (array('', 'short', '../../files/x?alt=media', 'abcdefghijk/../l', 'abcdefghijk@evil.example', str_repeat('a', 201)) as $id) {
		pg_assert_true(!pg_cloud_picker_source('google', array('id' => $id))['ok'], 'refused: ' . $id);
	}

	pg_assert_true(!pg_cloud_picker_source('google', null)['ok'], 'nothing chosen');
	pg_assert_true(!pg_cloud_picker_source('onedrive', array('id' => '1AbC-dEf_GhIjKlMnOp'))['ok'], 'an unknown provider');
}

// The Chooser's direct link is read without a token, and only from
// Dropbox's download host.
function test_cloud_picker_dropbox_link_only_on_the_download_host()
{
	$source = pg_cloud_picker_source('dropbox', array('link' => 'https://dl.dropboxusercontent.com/1/view/abc/Fiyat Listesi.pdf', 'name' => 'Fiyat Listesi.pdf', 'bytes' => 2048));

	pg_assert_true($source['ok'], 'a direct link is taken');
	pg_assert_true(!$source['token'], 'no token');
	pg_assert_same('', $source['meta_url'], 'no metadata request');
	pg_assert_same('Fiyat Listesi.pdf', $source['name'], 'the name');
	pg_assert_same(2048, $source['size'], 'the size');

	pg_assert_same('a.pdf', pg_cloud_picker_source('dropbox', array('link' => 'https://dl.dropboxusercontent.com/x', 'name' => '../../a.pdf'))['name'], 'a name is its last segment');

	foreach (array(
		'http://dl.dropboxusercontent.com/1/view/abc/a.pdf',
		'https://127.0.0.1/a.pdf',
		'https://169.254.169.254/latest/meta-data/',
		'https://dl.dropboxusercontent.com.evil.example/a.pdf',
		'https://evildl.dropboxusercontent.com/a.pdf',
		'https://www.dropbox.com/s/abc/a.pdf',
		'https://user@dl.dropboxusercontent.com/a.pdf',
		'https://dl.dropboxusercontent.com:8443/a.pdf',
		'file:///etc/passwd',
		'',
	) as $link) {
		pg_assert_true(!pg_cloud_picker_source('dropbox', array('link' => $link))['ok'], 'refused: ' . $link);
	}

	pg_assert_true(pg_cloud_picker_source('dropbox', array('link' => 'https://uc1.dl.dropboxusercontent.com/a.pdf'))['ok'], 'a regional download host');
}

function test_cloud_picker_google_app_id_from_the_client_id()
{
	pg_assert_same('123456789012', pg_cloud_picker_google_app_id('123456789012-abc123def.apps.googleusercontent.com'), 'the project number');
	pg_assert_same('', pg_cloud_picker_google_app_id('abc.apps.googleusercontent.com'), 'none in it');
	pg_assert_same('', pg_cloud_picker_google_app_id(''), 'no client');
}

// A Google Docs file has no bytes to download; a file's name is cut to its
// last segment.
function test_cloud_picker_google_meta()
{
	$taken = pg_cloud_picker_google_meta(array('name' => 'Katalog.pdf', 'mimeType' => 'application/pdf', 'size' => '5000'));

	pg_assert_true($taken['ok'], 'a stored file');
	pg_assert_same(5000, $taken['size'], 'its size');

	$doc = pg_cloud_picker_google_meta(array('name' => 'Teklif', 'mimeType' => 'application/vnd.google-apps.document'));

	pg_assert_true(!$doc['ok'], 'a Google Docs file is refused');
	pg_assert_contains('Teklif', $doc['error'], 'naming it');

	pg_assert_same('x.php', pg_cloud_picker_google_meta(array('name' => 'a/b/x.php', 'mimeType' => 'text/plain'))['name'], 'the last segment');
	pg_assert_true(!pg_cloud_picker_google_meta(null)['ok'], 'no answer');
}

function test_cloud_picker_refuses_over_size_before_and_during_the_download()
{
	pg_assert_same('', pg_cloud_picker_size_problem('a.jpg', 100, 100), 'at the limit');
	pg_assert_same('', pg_cloud_picker_size_problem('a.jpg', 10 ** 9, 0), 'no limit');
	pg_assert_contains('a.jpg', pg_cloud_picker_size_problem('a.jpg', 101, 100), 'over: names the file');

	$handle = fopen('php://memory', 'w+b');
	$written = 0;
	$over = false;

	pg_assert_true(pg_cloud_picker_write($handle, str_repeat('x', 60), $written, $over, 100), 'first piece');
	pg_assert_true(!pg_cloud_picker_write($handle, str_repeat('x', 41), $written, $over, 100), 'the piece past the limit stops it');
	pg_assert_true($over, 'stopped by the limit');
	pg_assert_same(60, $written, 'nothing past the limit written');

	rewind($handle);
	pg_assert_same(60, strlen(stream_get_contents($handle)), 'the file holds what was counted');
	fclose($handle);
}

// The download goes through api_http_request(), which refuses an address
// inside the network before anything is sent.
function test_cloud_picker_download_refuses_private_hosts()
{
	$path = tempnam(sys_get_temp_dir(), 'pgpicktest');

	foreach (array('https://127.0.0.1/a.pdf', 'https://169.254.169.254/latest/meta-data/', 'https://10.0.0.5/a.pdf', 'https://100.100.100.200/a') as $url) {
		$download = pg_cloud_picker_download($url, array(), 1000, $path);

		pg_assert_true(!$download['ok'], 'refused: ' . $url);
		pg_assert_contains('inside this network', $download['error'], 'the guard says why: ' . $url);
		pg_assert_same(0, filesize($path), 'nothing written: ' . $url);
	}

	unlink($path);
}

// The cloud file is stored by the browser upload's own function, which
// refuses a server-run name and an over-size file before anything is
// written.
function test_cloud_picker_hand_off_has_the_upload_refusals()
{
	$options = array('folder' => 1, 'description' => '', 'design' => null, 'image_profile' => '', 'image_settings' => array(), 'file_max' => 100);

	foreach (array('shell.php', 'resim.php.jpg', '.htaccess', 'web.config') as $name) {
		$stored = pg_file_store_upload('/nonexistent/' . $name, $name, 10, $options);

		pg_assert_true(!$stored['ok'], 'refused: ' . $name);
		pg_assert_same(pg_upload_blocked_message($name), $stored['message'], 'the upload sentence: ' . $name);
	}

	$stored = pg_file_store_upload('/nonexistent/buyuk.jpg', 'buyuk.jpg', 101, $options);

	pg_assert_true(!$stored['ok'], 'over the single-file limit');
	pg_assert_contains('buyuk.jpg', $stored['message'], 'names the file');
}

// add_file.php stores the browser's files and the cloud file through the
// one function, writes no row itself, and checks the folder right and the
// token before a cloud download.
function test_cloud_picker_add_file_uses_the_one_store_function()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_file.php');

	pg_assert_same(2, preg_match_all('/pg_file_store_upload\(\s*\$/', $source), 'both paths');
	pg_assert_same(false, stripos($source, 'INSERT INTO files'), 'no second INSERT');

	$cloud = substr($source, strpos($source, "if (\$cloud_provider !== '') {"));

	pg_assert_true(strpos($cloud, 'pg_cloud_picker_token_valid()') < strpos($cloud, 'check_edit_access('), 'the token first');
	pg_assert_true(strpos($cloud, 'check_edit_access(') < strpos($cloud, 'pg_cloud_picker_fetch('), 'the folder right before the download');
	pg_assert_contains("\$upload_limits['file_max']", substr($cloud, strpos($cloud, 'pg_cloud_picker_fetch('), 300), 'downloaded within the upload limit');
}

// The purpose asks Google only for drive.file, and each person connects
// their own account.
function test_cloud_picker_purpose()
{
	$purpose = pg_connect_purpose('file_picker');

	pg_assert_true(is_array($purpose), 'the purpose loads');
	pg_assert_same('person', $purpose['owner'], 'each person their own');
	pg_assert_same(array('https://www.googleapis.com/auth/drive.file'), $purpose['scopes']['google'], 'drive.file only');
	pg_assert_true(!pg_connect_purpose_allows($purpose, 'google', 'site'), 'not the site\'s account');
	pg_assert_true(pg_connect_purpose_allows($purpose, 'google', 'person'), 'a person\'s account');
}
