<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's WhatsApp picture, video, voice message or document
 * fetched into the conversation card's files: Meta's media requests and
 * answers, the file's name and limit, which failures are given up, and the
 * wiring of the general job. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_media.php');
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');

function test_whatsapp_media_requests()
{
	$app = array('app_id' => '1', 'app_secret' => 'secret', 'token' => 'token', 'phone_number_id' => '106540352242922', 'account_id' => '2');
	$info = pg_whatsapp_cloud_media_info_request($app, '1037543291543636');

	pg_assert_contains('/1037543291543636?', $info['url'], 'the media\'s own address');
	pg_assert_contains('phone_number_id=106540352242922', $info['url'], 'asked for this number');
	pg_assert_contains('appsecret_proof=', $info['url'], 'proof');

	$file = pg_whatsapp_cloud_media_file_request($app, 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1037&ext=1&hash=x');
	pg_assert_same('https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1037&ext=1&hash=x', $file['url'], 'the address Meta gave');
	pg_assert_contains('Authorization: Bearer token', implode("\n", $file['headers']), 'with the token');
}

function test_whatsapp_media_info_parse()
{
	$read = pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(200, '{"url":"https:\/\/lookaside.fbsbx.com\/x?mid=1","mime_type":"image\/jpeg","sha256":"ab","file_size":"2048","id":"1037"}'));

	pg_assert_same(array('ok' => true, 'url' => 'https://lookaside.fbsbx.com/x?mid=1', 'mime' => 'image/jpeg', 'size' => 2048, 'error' => '', 'gone' => false), $read, 'the address, type and size');
	pg_assert_true(!pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(200, '{"url":"http://example.com/x","mime_type":"image/jpeg"}'))['ok'], 'only https');
	pg_assert_true(!pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(200, '{"mime_type":"image/jpeg"}'))['ok'], 'no address');

	$gone = pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(400, '{"error":{"code":100,"message":"Unsupported get request"}}'));
	pg_assert_true(!$gone['ok'] && $gone['gone'], 'Meta no longer has it: given up');

	$token = pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(401, '{"error":{"code":190,"message":"Invalid OAuth access token"}}'));
	pg_assert_true(!$token['ok'] && !$token['gone'], 'a refused token gives nothing up');

	$down = pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer(0, '', 'timeout'));
	pg_assert_true(!$down['ok'] && !$down['gone'], 'Meta not reached: tried again');
}

function test_whatsapp_media_file_name()
{
	pg_assert_same('jpg', pg_whatsapp_media_extension('image/jpeg'), 'a picture');
	pg_assert_same('ogg', pg_whatsapp_media_extension('audio/ogg; codecs=opus'), 'a voice message');
	pg_assert_same('webp', pg_whatsapp_media_extension('image/webp'), 'a sticker');
	pg_assert_same('docx', pg_whatsapp_media_extension('application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'a Word document');
	pg_assert_same('', pg_whatsapp_media_extension('application/x-msdownload'), 'a program is not taken');

	pg_assert_same('fatura-1042.pdf', pg_whatsapp_media_file_name('document', 'application/pdf', 'fatura-1042.pdf', 1763000000), 'a document keeps its name');
	pg_assert_same('whatsapp-document-20251113-021320.pdf', pg_whatsapp_media_file_name('document', 'application/pdf', 'Faturam ekte', 1763000000), 'a caption is no name');
	pg_assert_same('whatsapp-image-20251113-021320.jpg', pg_whatsapp_media_file_name('image', 'image/jpeg', 'Kargo kutusu', 1763000000), 'a picture is named by its kind and time');
	pg_assert_same('whatsapp-audio-20251113-021320.ogg', pg_whatsapp_media_file_name('audio', 'audio/ogg; codecs=opus', '', 1763000000), 'a voice message');
	pg_assert_same('', pg_whatsapp_media_file_name('document', 'application/x-msdownload', 'setup.exe', 1763000000), 'nothing for a type not taken');
	pg_assert_same('whatsapp-document-20251113-021320.pdf', pg_whatsapp_media_file_name('document', 'application/pdf', '../../x.exe.pdf/', 1763000000), 'no path in a name');
}

function test_whatsapp_media_size()
{
	pg_assert_same('', pg_whatsapp_media_refusal('image/jpeg', 2048), 'taken');
	pg_assert_same('too_large', pg_whatsapp_media_refusal('video/mp4', PG_WHATSAPP_MEDIA_MAX + 1), 'over what the job reads');
	pg_assert_same('', pg_whatsapp_media_refusal('video/mp4', 0), 'a size Meta did not say is checked when it arrives');
	pg_assert_same('type', pg_whatsapp_media_refusal('application/zip', 10), 'a type not taken');
}

function test_whatsapp_media_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains('pg_whatsapp_media_run(', $read('job.php'), 'the general job fetches');
	pg_assert_contains("AND i.refused_files = ''", $read('includes/fn/whatsapp_media.php'), 'a given-up file is not asked again');
	pg_assert_contains("m.sender_kind = 'mail' AND m.sender_id = i.id AND m.file_id > 0", $read('includes/fn/whatsapp_media.php'), 'a fetched file is not asked again');
}
