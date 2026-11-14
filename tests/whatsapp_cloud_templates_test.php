<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WhatsApp driver's template calls: a template sent for
 * review, an edit, the header sample's upload, and how each answer is read.
 * No call to Meta.
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
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_templates.php');

function whatsapp_cloud_templates_test_app()
{
	return array(
		'enabled' => true, 'app_id' => '1234567890123', 'app_secret' => 'secret', 'token' => 'token',
		'phone_number_id' => '106540352242922', 'account_id' => '102290129340398',
	);
}

function test_whatsapp_cloud_template_submit_request()
{
	$template = array('name' => 'thank_you', 'language' => 'tr', 'category' => 'MARKETING', 'components' => array(array('type' => 'BODY', 'text' => 'Hi')));

	$request = pg_whatsapp_cloud_template_submit_request(whatsapp_cloud_templates_test_app(), $template);
	pg_assert_contains('/v26.0/102290129340398/message_templates?appsecret_proof=', $request['url'], 'new: the account\'s list');
	pg_assert_same(array('name' => 'thank_you', 'language' => 'tr', 'category' => 'MARKETING', 'parameter_format' => 'POSITIONAL', 'components' => array(array('type' => 'BODY', 'text' => 'Hi'))), json_decode($request['body'], true), 'new: body');
	pg_assert_true(in_array('Content-Type: application/json', $request['headers'], true), 'JSON');

	$request = pg_whatsapp_cloud_template_submit_request(whatsapp_cloud_templates_test_app(), $template, '991122');
	pg_assert_contains('/v26.0/991122?appsecret_proof=', $request['url'], 'edit: the template itself');
	pg_assert_same(array('category' => 'MARKETING', 'components' => array(array('type' => 'BODY', 'text' => 'Hi'))), json_decode($request['body'], true), 'edit: no name or language');
}

function test_whatsapp_cloud_template_submit_parse()
{
	$ok = function ($data) {
		return array('data' => $data, 'code' => '', 'meta' => true, 'error' => '');
	};

	$read = pg_whatsapp_cloud_template_submit_parse($ok(array('id' => '594425479261596', 'status' => 'pending', 'category' => 'MARKETING')));
	pg_assert_same(array('ok' => true, 'id' => '594425479261596', 'status' => 'PENDING', 'category' => 'MARKETING', 'error' => ''), $read, 'created');

	$read = pg_whatsapp_cloud_template_submit_parse($ok(array('status' => 'PENDING')));
	pg_assert_false($read['ok'], 'no id');

	$read = pg_whatsapp_cloud_template_submit_parse($ok(array('success' => true)), '991122');
	pg_assert_same('PENDING', $read['status'], 'edited: in review again');
	pg_assert_same('991122', $read['id'], 'edited: same id');

	$read = pg_whatsapp_cloud_template_submit_parse($ok(array('success' => false)), '991122');
	pg_assert_false($read['ok'], 'edit not confirmed');

	$read = pg_whatsapp_cloud_template_submit_parse(array('data' => array(), 'code' => '100', 'meta' => true, 'error' => 'Invalid parameter'));
	pg_assert_same('Invalid parameter', $read['error'], 'Meta\'s refusal');
}

function test_whatsapp_cloud_upload_requests()
{
	$app = whatsapp_cloud_templates_test_app();

	$request = pg_whatsapp_cloud_upload_start_request($app, 'kasim kampanya.jpg', 2048, 'image/jpeg');
	pg_assert_contains('/v26.0/1234567890123/uploads?file_name=kasim%20kampanya.jpg&file_length=2048&file_type=image%2Fjpeg&appsecret_proof=', $request['url'], 'start');

	$request = pg_whatsapp_cloud_upload_send_request($app, 'upload:MTphdHRhY2htZW50', 'BYTES');
	pg_assert_same('https://graph.facebook.com/v26.0/upload:MTphdHRhY2htZW50', $request['url'], 'session path');
	pg_assert_same(array('Authorization: OAuth token', 'file_offset: 0', 'Content-Type: application/octet-stream'), $request['headers'], 'OAuth and offset');
	pg_assert_same('BYTES', $request['body'], 'bytes');

	$answer = function ($data) {
		return array('data' => $data, 'code' => '', 'meta' => true, 'error' => '');
	};

	pg_assert_same('upload:MTph', pg_whatsapp_cloud_upload_parse($answer(array('id' => 'upload:MTph')), 'id')['value'], 'session id');
	pg_assert_false(pg_whatsapp_cloud_upload_parse($answer(array('id' => '../me')), 'id')['ok'], 'not a session id');
	pg_assert_false(pg_whatsapp_cloud_upload_parse($answer(array('id' => 'upload:a/b')), 'id')['ok'], 'no slash in a session id');
	pg_assert_same('4::aW1h', pg_whatsapp_cloud_upload_parse($answer(array('h' => '4::aW1h')), 'h')['value'], 'handle');
	pg_assert_false(pg_whatsapp_cloud_upload_parse($answer(array()), 'h')['ok'], 'no handle');
}
