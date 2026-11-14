<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rest of a WhatsApp answer from the Workspace: a file inside
 * the customer's 24 hours (Meta's types and sizes, the queue row, the upload
 * and the media message) and an approved template after them (its values,
 * the components, the text kept on the card). No database and no call to
 * Meta.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_offer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_reply.php');
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_whatsapp_reply_media_kinds()
{
	$mb = 1048576;

	pg_assert_same(array('type' => 'image', 'mime' => 'image/jpeg', 'error' => ''), pg_whatsapp_reply_media('Kargo.JPG', 2 * $mb), 'a picture');
	pg_assert_same(array('type' => 'image', 'mime' => 'image/png', 'error' => ''), pg_whatsapp_reply_media('a.png', 5 * $mb), 'a picture at the limit');
	pg_assert_same('file_size', pg_whatsapp_reply_media('a.png', 5 * $mb + 1)['error'], 'a picture over 5 MB');
	pg_assert_same(array('type' => 'video', 'mime' => 'video/mp4', 'error' => ''), pg_whatsapp_reply_media('a.mp4', 16 * $mb), 'a video');
	pg_assert_same('file_size', pg_whatsapp_reply_media('a.3gp', 16 * $mb + 1)['error'], 'a video over 16 MB');
	pg_assert_same(array('type' => 'document', 'mime' => 'application/pdf', 'error' => ''), pg_whatsapp_reply_media('fatura.pdf', 16 * $mb), 'a document');
	pg_assert_same('document', pg_whatsapp_reply_media('liste.xlsx', 1000)['type'], 'a spreadsheet goes as a document');
	pg_assert_same('file_size', pg_whatsapp_reply_media('fatura.pdf', 16 * $mb + 1)['error'], 'a document over what the queue reads');
	pg_assert_same('file_type', pg_whatsapp_reply_media('a.gif', 1000)['error'], 'a GIF is not taken');
	pg_assert_same('file_type', pg_whatsapp_reply_media('a.zip', 1000)['error'], 'an archive is not taken');
	pg_assert_same('file_type', pg_whatsapp_reply_media('noextension', 1000)['error'], 'no ending');
	pg_assert_same('file_size', pg_whatsapp_reply_media('a.jpg', 0)['error'], 'an empty file');
}

function test_whatsapp_reply_media_row()
{
	$file = array('id' => 77, 'name' => 'fatura.pdf', 'type' => 'document');
	$row = pg_whatsapp_reply_row('905321234567', ' Faturanız ektedir. ', array('reference' => 'wsr:5:0', 'window_ends' => 5000, 'file' => $file), 1000);

	pg_assert_same('{"type":"document","file_id":77,"name":"fatura.pdf","caption":"Faturanız ektedir."}', $row['payload'], 'the file and its caption');
	pg_assert_same('reply', $row['kind'], 'an answer');
	pg_assert_same('', $row['template_name'], 'no template');

	$alone = pg_whatsapp_reply_row('905321234567', '', array('window_ends' => 5000, 'file' => array('id' => 3, 'name' => 'a.jpg', 'type' => 'image')), 1000);
	pg_assert_same('{"type":"image","file_id":3,"name":"a.jpg","caption":""}', $alone['payload'], 'a file without words');

	pg_assert_same(array('error' => 'caption_long'), pg_whatsapp_reply_row('905321234567', str_repeat('ş', 1025), array('window_ends' => 5000, 'file' => $file), 1000), 'a caption holds 1024');
	pg_assert_same(array('error' => 'window'), pg_whatsapp_reply_row('905321234567', '', array('window_ends' => 1000, 'file' => $file), 1000), 'a file only inside the window');
	pg_assert_same(array('error' => 'file_type'), pg_whatsapp_reply_row('905321234567', '', array('window_ends' => 5000, 'file' => array('id' => 3, 'name' => 'a.gif', 'type' => 'gif')), 1000), 'a kind Meta does not take');
	pg_assert_same(array('error' => 'empty'), pg_whatsapp_reply_row('905321234567', '', array('window_ends' => 5000, 'file' => array('id' => 0, 'name' => 'a.jpg', 'type' => 'image')), 1000), 'no file kept');
}

function test_whatsapp_reply_media_requests()
{
	$app = array('app_id' => '1', 'app_secret' => 'secret', 'token' => 'token', 'phone_number_id' => '106540352242922', 'account_id' => '2');
	$upload = pg_whatsapp_cloud_media_upload_request($app, 'fatura.pdf', 'application/pdf', '%PDF-1.4', 'pgboundary');

	pg_assert_contains('/106540352242922/media?', $upload['url'], 'the number\'s media');
	pg_assert_contains('appsecret_proof=', $upload['url'], 'proof');
	pg_assert_contains('Content-Type: multipart/form-data; boundary=pgboundary', implode("\n", $upload['headers']), 'a form');
	pg_assert_contains("name=\"messaging_product\"\r\n\r\nwhatsapp\r\n", $upload['body'], 'WhatsApp\'s');
	pg_assert_contains("name=\"type\"\r\n\r\napplication/pdf\r\n", $upload['body'], 'its type');
	pg_assert_contains("name=\"file\"; filename=\"fatura.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.4\r\n--pgboundary--", $upload['body'], 'the bytes');
	pg_assert_contains('filename="a_b.pdf"', pg_whatsapp_cloud_media_upload_request($app, "a\"b.pdf", 'application/pdf', 'x', 'b')['body'], 'a quote never breaks the header');

	pg_assert_same(array('ok' => true, 'id' => '1037543291543636', 'error' => ''),
		pg_whatsapp_cloud_media_id_parse(pg_whatsapp_cloud_answer(200, '{"id":"1037543291543636"}')), 'the media id');
	pg_assert_true(!pg_whatsapp_cloud_media_id_parse(pg_whatsapp_cloud_answer(200, '{"id":"a/b"}'))['ok'], 'an id that is no id');
	pg_assert_true(!pg_whatsapp_cloud_media_id_parse(pg_whatsapp_cloud_answer(400, '{"error":{"code":131053,"message":"Media upload error"}}'))['ok'], 'refused');

	$document = json_decode(pg_whatsapp_cloud_media_request($app, '905321234567', 'document', '1037', 'Faturanız', 'fatura.pdf')['body'], true);
	pg_assert_same(array('messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => '+905321234567', 'type' => 'document',
		'document' => array('id' => '1037', 'caption' => 'Faturanız', 'filename' => 'fatura.pdf')), $document, 'a document keeps its name');

	$image = json_decode(pg_whatsapp_cloud_media_request($app, '905321234567', 'image', '1037', '', 'a.jpg')['body'], true);
	pg_assert_same(array('id' => '1037'), $image['image'], 'a picture without words carries no caption');
}

function test_whatsapp_reply_template_fill()
{
	$template = array(
		'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL', 'offer' => '',
		'components' => array(
			array('type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Siparişiniz'),
			array('type' => 'BODY', 'text' => 'Merhaba {{1}}, {{2}} numaralı siparişiniz hakkında yazdık.'),
			array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'URL', 'text' => 'Gör', 'url' => 'https://example.com/o/{{1}}'))),
		),
	);

	$filled = pg_whatsapp_reply_template_fill($template, array('body:1' => ' Ayşe ', 'body:2' => '1042', 'button:0' => 'a b'));

	pg_assert_true($filled['ok'], 'filled');
	pg_assert_same(array(
		array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => 'Ayşe'), array('type' => 'text', 'text' => '1042'))),
		array('type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => array(array('type' => 'text', 'text' => 'a%20b'))),
	), $filled['components'], 'the components the queue sends');
	pg_assert_same("Siparişiniz\nMerhaba Ayşe, 1042 numaralı siparişiniz hakkında yazdık.", $filled['text'], 'what the card shows');

	$missing = pg_whatsapp_reply_template_fill($template, array('body:1' => 'Ayşe', 'body:2' => '  ', 'button:0' => 'x'));
	pg_assert_true(!$missing['ok'], 'every value is asked for');
	pg_assert_same('body:2', $missing['slot'], 'the empty one is named');

	$pending = pg_whatsapp_reply_template_fill(array('status' => 'PENDING') + $template, array('body:1' => 'a', 'body:2' => 'b', 'button:0' => 'c'));
	pg_assert_true(!$pending['ok'], 'only an approved template');

	$named = array('status' => 'APPROVED', 'parameter_format' => 'NAMED', 'offer' => '',
		'components' => array(array('type' => 'BODY', 'text' => 'Merhaba {{first_name}}!')));
	$filled = pg_whatsapp_reply_template_fill($named, array('body:first_name' => 'Ali'));
	pg_assert_same(array(array('type' => 'body', 'parameters' => array(array('type' => 'text', 'parameter_name' => 'first_name', 'text' => 'Ali')))), $filled['components'], 'a named variable');
	pg_assert_same('Merhaba Ali!', $filled['text'], 'its text');
}

function test_whatsapp_reply_template_list()
{
	$rows = array(
		array('id' => 4, 'name' => 'siparis_bilgi', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL',
			'components' => '[{"type":"BODY","text":"Merhaba {{1}}"}]', 'samples' => '{"body":[{"label":"Müşteri adı"}]}', 'offer' => ''),
		array('id' => 5, 'name' => 'resimli', 'language' => 'tr', 'category' => 'MARKETING', 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL',
			'components' => '[{"type":"HEADER","format":"IMAGE"},{"type":"BODY","text":"Yeni"}]', 'samples' => '', 'offer' => ''),
		array('id' => 6, 'name' => 'taslak', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'PENDING', 'parameter_format' => 'POSITIONAL',
			'components' => '[{"type":"BODY","text":"x"}]', 'samples' => '', 'offer' => ''),
	);

	$list = pg_whatsapp_reply_templates_offered($rows);

	pg_assert_same(1, count($list), 'approved and fillable by hand only');
	pg_assert_same('siparis_bilgi|tr', $list[0]['key'], 'by name and language');
	pg_assert_same('Merhaba {{1}}', $list[0]['text'], 'its text');
	pg_assert_same(array(array('key' => 'body:1', 'label' => 'Müşteri adı')), $list[0]['slots'], 'one field per variable, as the designer named it');
	pg_assert_same(false, $list[0]['marketing'], 'a utility template');
}

function test_whatsapp_reply_media_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("pg_whatsapp_reply_send_media(\$app, \$row['recipient'], \$payload)", $read('includes/fn/whatsapp.php'), 'a file payload goes as media');
	pg_assert_contains("ws_mail_phone_reply_send(\$viewer, \$conversation_id, \$request, \$files['files'])", $read('includes/workspace/actions.php'), 'the drawer\'s file and template');
	pg_assert_contains("formPost(h, 'ws_whatsapp_reply', request.fields, request.files)", $read('assets/js/workspace_mail.js'), 'the drawer sends the file');
}
