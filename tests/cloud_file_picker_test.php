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
	pg_assert_true(!pg_cloud_picker_source('box', array('id' => '1AbC-dEf_GhIjKlMnOp'))['ok'], 'an unknown provider');
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
	pg_assert_same(array('https://graph.microsoft.com/Files.Read'), $purpose['scopes']['microsoft'], 'Microsoft: Files.Read only');
	pg_assert_true(pg_connect_purpose_allows($purpose, 'microsoft', 'person'), 'a person\'s Microsoft account');
}

// A OneDrive choice is a drive id and an item id; the server reads the
// item's metadata and its content from Graph with the person's token, never
// from the endpoint the picker names.
function test_cloud_picker_onedrive_choice_maps_to_graph()
{
	$source = pg_cloud_picker_source('onedrive', array('drive_id' => 'b!-RIj2DuyvEyV1T4NlOaMHk8XkS_I8MdF', 'item_id' => '01BYE5RZ6QN3ZWBTUFOFD3GSPGOHDJD36K'));

	pg_assert_true($source['ok'], 'a work account item');
	pg_assert_true($source['token'], 'read with the token');
	pg_assert_same(array('https://graph.microsoft.com/Files.Read'), $source['scopes'], 'the Graph token');
	pg_assert_same('https://graph.microsoft.com/v1.0/drives/b!-RIj2DuyvEyV1T4NlOaMHk8XkS_I8MdF/items/01BYE5RZ6QN3ZWBTUFOFD3GSPGOHDJD36K?$select=name,size,file', $source['meta_url'], 'the metadata');
	pg_assert_same('https://graph.microsoft.com/v1.0/drives/b!-RIj2DuyvEyV1T4NlOaMHk8XkS_I8MdF/items/01BYE5RZ6QN3ZWBTUFOFD3GSPGOHDJD36K/content', $source['content_url'], 'the content');
	pg_assert_same('', $source['url'], 'the download address comes from Graph');

	pg_assert_true(pg_cloud_picker_source('onedrive', array('drive_id' => 'd4648f06c91d9d3d', 'item_id' => 'D4648F06C91D9D3D!54927'))['ok'], 'a personal account item');

	foreach (array(
		array('drive_id' => '', 'item_id' => '01BYE5RZ6Q'),
		array('drive_id' => 'abc', 'item_id' => ''),
		array('drive_id' => '../me', 'item_id' => '01BYE5RZ6Q'),
		array('drive_id' => 'abc', 'item_id' => '01BY/../../me/drive'),
		array('drive_id' => 'abc', 'item_id' => 'a?x=1'),
		array('drive_id' => 'abc', 'item_id' => 'a.b'),
		array('drive_id' => 'abc', 'item_id' => str_repeat('a', 201)),
		array('drive_id' => array('x'), 'item_id' => 'abc'),
	) as $choice) {
		pg_assert_true(!pg_cloud_picker_source('onedrive', $choice)['ok'], 'refused: ' . json_encode($choice));
	}
}

// Personal accounts open the picker on onedrive.live.com with a OneDrive
// token; work and school accounts on their own SharePoint host with a token
// for that host, never for another tenant's.
function test_cloud_picker_onedrive_personal_and_work_base()
{
	$personal = pg_cloud_picker_onedrive_drive(array('driveType' => 'personal', 'webUrl' => 'https://onedrive.live.com/?cid=D4648F06C91D9D3D'));

	pg_assert_true($personal['ok'], 'a personal drive');
	pg_assert_same('personal', $personal['kind'], 'personal');
	pg_assert_same('https://onedrive.live.com/picker', $personal['picker_url'], 'the consumer picker');
	pg_assert_same(array('OneDrive.ReadOnly'), pg_cloud_picker_onedrive_scopes($personal, ''), 'read-only OneDrive scope');
	pg_assert_same(array('OneDrive.ReadOnly'), pg_cloud_picker_onedrive_scopes($personal, 'https://evil.example'), 'a personal token is never for another resource');

	$work = pg_cloud_picker_onedrive_drive(array('driveType' => 'business', 'webUrl' => 'https://contoso-my.sharepoint.com/personal/ayse_contoso_com/Documents'));

	pg_assert_true($work['ok'], 'a work drive');
	pg_assert_same('work', $work['kind'], 'work');
	pg_assert_same('https://contoso-my.sharepoint.com', $work['resource'], 'the SharePoint host');
	pg_assert_same('https://contoso-my.sharepoint.com/_layouts/15/FilePicker.aspx', $work['picker_url'], 'the SharePoint picker');
	pg_assert_same(array('https://contoso-my.sharepoint.com/.default'), pg_cloud_picker_onedrive_scopes($work, ''), 'its own host');
	pg_assert_same(array('https://contoso.sharepoint.com/.default'), pg_cloud_picker_onedrive_scopes($work, 'https://contoso.sharepoint.com/'), 'the tenant\'s sites');

	foreach (array('https://fabrikam.sharepoint.com', 'https://contoso.sharepoint.com.evil.example', 'http://contoso.sharepoint.com', 'https://graph.microsoft.com', 'https://user@contoso.sharepoint.com', 'https://contoso.sharepoint.com:444') as $resource) {
		pg_assert_same(array(), pg_cloud_picker_onedrive_scopes($work, $resource), 'refused: ' . $resource);
	}

	foreach (array(null, array(), array('driveType' => 'business', 'webUrl' => 'https://evil.example/x'), array('driveType' => 'business', 'webUrl' => 'http://contoso-my.sharepoint.com/x'), array('driveType' => 'documentLibrary', 'webUrl' => 'https://contoso.sharepoint.com/x')) as $data) {
		pg_assert_true(!pg_cloud_picker_onedrive_drive($data)['ok'], 'refused: ' . json_encode($data));
	}
}

// An item without a file of its own (a OneNote notebook, a folder) has no
// bytes to download; Graph's pre-authenticated download address is taken
// only on Microsoft's storage hosts.
function test_cloud_picker_onedrive_meta_and_download_host()
{
	$taken = pg_cloud_picker_onedrive_meta(array('name' => 'Teklif.docx', 'size' => 4096, 'file' => array('mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')));

	pg_assert_true($taken['ok'], 'a stored file');
	pg_assert_same(4096, $taken['size'], 'its size');

	$notebook = pg_cloud_picker_onedrive_meta(array('name' => 'Toplanti Notlari', 'size' => 100, 'package' => array('type' => 'oneNote')));

	pg_assert_true(!$notebook['ok'], 'a OneNote notebook is refused');
	pg_assert_contains('Toplanti Notlari', $notebook['error'], 'naming it');
	pg_assert_true(!pg_cloud_picker_onedrive_meta(array('name' => 'Klasor', 'folder' => array('childCount' => 2)))['ok'], 'a folder');
	pg_assert_same('x.php', pg_cloud_picker_onedrive_meta(array('name' => 'a/b/x.php', 'file' => array()))['name'], 'the last segment');
	pg_assert_true(!pg_cloud_picker_onedrive_meta(null)['ok'], 'no answer');

	foreach (array(
		'https://contoso-my.sharepoint.com/personal/a/_layouts/15/download.aspx?UniqueId=x&tempauth=y',
		'https://public.am3.files.1drv.com/y4m/abc',
		'https://my.microsoftpersonalcontent.com/personal/abc/_layouts/15/download.aspx',
	) as $url) {
		pg_assert_true(pg_cloud_picker_onedrive_download_url($url), 'taken: ' . $url);
	}

	foreach (array('', 'http://contoso-my.sharepoint.com/a', 'https://127.0.0.1/a', 'https://sharepoint.com.evil.example/a', 'https://evilsharepoint.com/a', 'https://u@contoso.sharepoint.com/a', 'https://contoso.sharepoint.com:8443/a', 'https://graph.microsoft.com/v1.0/me') as $url) {
		pg_assert_true(!pg_cloud_picker_onedrive_download_url($url), 'refused: ' . $url);
	}
}

// A refresh refused because nobody consented to one resource (SharePoint)
// leaves the account connected; any other invalid_grant asks for it again.
function test_cloud_picker_missing_consent_keeps_the_connection()
{
	pg_assert_true(!pg_connect_grant_lost(array('error_code' => 'invalid_grant', 'error' => 'invalid_grant: AADSTS65001: The user or administrator has not consented to use the application.')), 'consent missing');
	pg_assert_true(pg_connect_grant_lost(array('error_code' => 'invalid_grant', 'error' => 'invalid_grant: AADSTS70008: The refresh token has expired.')), 'expired');
	pg_assert_true(!pg_connect_grant_lost(array('error_code' => 'temporarily_unavailable', 'error' => 'x')), 'not a grant error');
}
