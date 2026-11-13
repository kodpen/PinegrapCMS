<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WhatsApp account (includes/fn/whatsapp.php) and the Cloud API
 * driver (includes/whatsapp/cloud_api.php): what a save of the settings card
 * decides, the card's markup, the requests the driver builds, how Meta's
 * answers are read, the account check's readers, and the webhook address's
 * verify answer and signature check. Meta is not called and no database is
 * used.
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
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');

// A stored, complete and checked account.
function whatsapp_test_app($changes = array())
{
	return $changes + array(
		'stored'               => true,
		'app_id'               => '1234567890123',
		'app_secret'           => '0123456789abcdef0123456789abcdef',
		'token'                => 'EAAJBsbCmS80BO4hZBf5xYKFaFW7kVMNxAWzIInZB7q1PuZCLiEqKh3gZDZD',
		'verify_token'         => 'c0ffee',
		'secrets_stored'       => true,
		'secrets_unreadable'   => false,
		'phone_number_id'      => '106540352242922',
		'account_id'           => '102290129340398',
		'display_phone_number' => '+90 212 555 01 23',
		'verified_name'        => 'Pinegrap Shop',
		'quality_rating'       => 'GREEN',
		'checked'              => 1762900000,
		'enabled'              => true,
	);
}

// No account on the site.
function whatsapp_test_none()
{
	return whatsapp_test_app(array(
		'stored' => false, 'app_id' => '', 'app_secret' => '', 'token' => '', 'verify_token' => '',
		'secrets_stored' => false, 'phone_number_id' => '', 'account_id' => '', 'display_phone_number' => '',
		'verified_name' => '', 'quality_rating' => '', 'checked' => 0, 'enabled' => false,
	));
}

// The card posted as drawn for an account: ids back, secrets empty.
function whatsapp_test_post($app, $changes = array())
{
	return $changes + array(
		'whatsapp_enabled'         => $app['enabled'] ? '1' : null,
		'whatsapp_app_id'          => $app['app_id'],
		'whatsapp_app_secret'      => '',
		'whatsapp_token'           => '',
		'whatsapp_phone_number_id' => $app['phone_number_id'],
		'whatsapp_account_id'      => $app['account_id'],
		'whatsapp_test_to'         => '',
	);
}

// An account sent back as it was is not written or asked about, so an
// account Meta now refuses never blocks the rest of the pane.
function test_whatsapp_decide_unchanged_does_nothing()
{
	$app = whatsapp_test_app();
	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($app), $app, '', 'new');

	pg_assert_same('none', $decision['action']);
	pg_assert_false($decision['ask']);
	pg_assert_same(array(), $decision['marks']);
	pg_assert_same('c0ffee', $decision['verify_token'], 'the stored verify token stays');
}

// Off with the same account writes the switch alone; off with an empty App
// ID removes the account.
function test_whatsapp_decide_off_and_forget()
{
	$app = whatsapp_test_app();

	pg_assert_same('disable', pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_enabled' => null)), $app, '', 'new')['action']);
	pg_assert_same('forget', pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_enabled' => null, 'whatsapp_app_id' => '')), $app, '', 'new')['action']);
	pg_assert_same('none', pg_whatsapp_settings_decide(whatsapp_test_post(whatsapp_test_none()), whatsapp_test_none(), '', 'new')['action'], 'nothing stored, nothing typed');
}

// A new account: every step is required, ids are digits, and a verify token
// is made for it.
function test_whatsapp_decide_new_account()
{
	$none = whatsapp_test_none();
	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($none, array('whatsapp_enabled' => '1')), $none, '', 'fresh');

	pg_assert_same('save', $decision['action']);
	pg_assert_false($decision['ask']);
	pg_assert_same(array('whatsapp_app_id', 'whatsapp_phone_number_id', 'whatsapp_account_id', 'whatsapp_app_secret', 'whatsapp_token'), array_keys($decision['marks']));
	pg_assert_same('fresh', $decision['verify_token']);

	$typed = whatsapp_test_post($none, array(
		'whatsapp_enabled'         => '1',
		'whatsapp_app_id'          => ' 1234 5678 90123 ',
		'whatsapp_app_secret'      => '0123456789abcdef0123456789abcdef',
		'whatsapp_token'           => "EAAJBsbCmS80BO4hZBf5xYKFaFW7kVMNxAWzIInZB7q1\n",
		'whatsapp_phone_number_id' => '106540352242922',
		'whatsapp_account_id'      => '102290129340398',
	));
	$decision = pg_whatsapp_settings_decide($typed, $none, '', 'fresh');

	pg_assert_same(array(), $decision['marks']);
	pg_assert_true($decision['ask'], 'a complete account is checked with Meta');
	pg_assert_same('1234567890123', $decision['app_id'], 'spaces dropped from an id');
	pg_assert_same('EAAJBsbCmS80BO4hZBf5xYKFaFW7kVMNxAWzIInZB7q1', $decision['token_effective'], 'a copied line break is trimmed');

	$typed['whatsapp_account_id'] = 'WABA-1022';
	$typed['whatsapp_token'] = 'EAAJ Bsb CmS80BO4hZBf5xYKFaFW7k';
	$decision = pg_whatsapp_settings_decide($typed, $none, '', 'fresh');

	pg_assert_same(array('whatsapp_account_id', 'whatsapp_token'), array_keys($decision['marks']));
}

// A secret left empty keeps the stored one; one this site's key cannot read
// must be typed again.
function test_whatsapp_decide_stored_secrets()
{
	$app = whatsapp_test_app();
	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_phone_number_id' => '106540352242923')), $app, '', 'new');

	pg_assert_same('save', $decision['action']);
	pg_assert_true($decision['ask']);
	pg_assert_same($app['app_secret'], $decision['app_secret_effective']);
	pg_assert_same($app['token'], $decision['token_effective']);

	$lost = whatsapp_test_app(array('app_secret' => '', 'token' => '', 'secrets_unreadable' => true));
	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($lost, array('whatsapp_account_id' => '102290129340399')), $lost, '', 'new');

	pg_assert_same(array('whatsapp_app_secret', 'whatsapp_token'), array_keys($decision['marks']));
	pg_assert_contains('encryption key', $decision['marks']['whatsapp_token']);
}

// A site that cannot keep an account marks the switch when it is turned on,
// and only says so when it is off.
function test_whatsapp_decide_refusal()
{
	$none = whatsapp_test_none();
	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($none, array('whatsapp_enabled' => '1', 'whatsapp_app_id' => '1234567890')), $none, 'No key.', 'new');

	pg_assert_same(array('whatsapp_enabled' => 'No key.'), $decision['marks']);
	pg_assert_same('none', $decision['action']);

	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($none, array('whatsapp_app_id' => '1234567890')), $none, 'No key.', 'new');

	pg_assert_same(array(), $decision['marks']);
	pg_assert_same(1, count($decision['notices']));
}

// The test number is read only when its button was pressed.
function test_whatsapp_decide_test_number()
{
	$app = whatsapp_test_app();

	pg_assert_same('', pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_test_to' => '0532 123 45 67')), $app, '', 'new')['test_to'], 'Enter is a plain save');

	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_test' => '1', 'whatsapp_test_to' => '0532 123 45 67')), $app, '', 'new');

	pg_assert_same('905321234567', $decision['test_to']);
	pg_assert_same('none', $decision['action'], 'the account itself is unchanged');

	$decision = pg_whatsapp_settings_decide(whatsapp_test_post($app, array('whatsapp_test' => '1', 'whatsapp_test_to' => '12')), $app, '', 'new');

	pg_assert_same(array('whatsapp_test_to'), array_keys($decision['marks']));
}

// The card draws no secret, shows the webhook step's address only once a
// verify token exists, and fills in the signed-in person's number.
function test_whatsapp_card_markup()
{
	$app = whatsapp_test_app();
	$html = pg_whatsapp_settings_html($app, array('refusal' => '', 'webhook_url' => 'https://shop.test/pinegrap/whatsapp_webhook.php', 'test_to' => '+905321234567'));

	pg_assert_false(strpos($html, $app['app_secret']) !== false, 'no app secret');
	pg_assert_false(strpos($html, $app['token']) !== false, 'no token');
	pg_assert_contains('value="https://shop.test/pinegrap/whatsapp_webhook.php" readonly="readonly"', $html);
	pg_assert_contains('id="whatsapp_verify_token" class="form-control" value="c0ffee" readonly="readonly"', $html);
	pg_assert_contains('value="+905321234567"', $html);
	pg_assert_contains('Pinegrap Shop', $html, 'the connection line');

	foreach (array(1, 2, 3, 4, 5) as $step) {
		pg_assert_contains('<h6>' . $step . '. ', $html, 'step ' . $step);
	}

	pg_assert_same(substr_count($html, '<div'), substr_count($html, '</div>'), 'every div closed');

	$html = pg_whatsapp_settings_html(whatsapp_test_none(), array('refusal' => '', 'webhook_url' => 'http://shop.test/whatsapp_webhook.php', 'test_to' => ''));

	pg_assert_false(strpos($html, 'whatsapp_webhook_url') !== false, 'no address before the first save');
	pg_assert_false(strpos($html, 'placeholder=') !== false, 'nothing stored to keep');
}

// The address and the proof.
function test_whatsapp_cloud_requests()
{
	$app = whatsapp_test_app();

	pg_assert_same('https://graph.facebook.com/v26.0/106540352242922/messages', pg_whatsapp_cloud_url('106540352242922/messages'));
	pg_assert_same(hash_hmac('sha256', $app['token'], $app['app_secret']), pg_whatsapp_cloud_proof($app['token'], $app['app_secret']));

	$debug = pg_whatsapp_cloud_debug_request($app);

	pg_assert_contains('/debug_token?input_token=' . $app['token'], $debug['url']);
	pg_assert_contains('Authorization: Bearer 1234567890123|0123456789abcdef0123456789abcdef', implode("\n", $debug['headers']), 'asked with the app token');

	$phone = pg_whatsapp_cloud_phone_request($app);

	pg_assert_contains('/v26.0/106540352242922?fields=display_phone_number%2Cverified_name%2Cquality_rating&appsecret_proof=' . pg_whatsapp_cloud_proof($app['token'], $app['app_secret']), $phone['url']);
	pg_assert_contains('Authorization: Bearer ' . $app['token'], implode("\n", $phone['headers']));
	pg_assert_contains('/v26.0/102290129340398/phone_numbers?fields=id&limit=100&appsecret_proof=', pg_whatsapp_cloud_numbers_request($app)['url']);
}

// A template message: the plus in front of the number, the name and the
// language, no components when there are none.
function test_whatsapp_cloud_template_request()
{
	$request = pg_whatsapp_cloud_template_request(whatsapp_test_app(), '905321234567', 'hello_world', 'en_US');
	$body = json_decode($request['body'], true);

	pg_assert_contains('/v26.0/106540352242922/messages?appsecret_proof=', $request['url']);
	pg_assert_same(array(
		'messaging_product' => 'whatsapp',
		'recipient_type'    => 'individual',
		'to'                => '+905321234567',
		'type'              => 'template',
		'template'          => array('name' => 'hello_world', 'language' => array('code' => 'en_US')),
	), $body);
	pg_assert_contains('Content-Type: application/json', implode("\n", $request['headers']));

	$request = pg_whatsapp_cloud_template_request(whatsapp_test_app(), '905321234567', 'order_shipped', 'tr', array(array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => 'Ayşe')))));

	pg_assert_contains('"text":"Ayşe"', $request['body'], 'Unicode kept as it is');
}

// Meta's answers: its error object with the code, a success, and what is not
// Meta's JSON.
function test_whatsapp_cloud_answer()
{
	$answer = pg_whatsapp_cloud_answer(401, '{"error":{"message":"Authentication Error","code":190,"type":"OAuthException"}}');

	pg_assert_same('190', $answer['code']);
	pg_assert_true($answer['meta']);
	pg_assert_contains('access token', $answer['error']);

	$answer = pg_whatsapp_cloud_answer(200, '{"id":"106540352242922","display_phone_number":"+90 212 555 01 23"}');

	pg_assert_same('', $answer['error']);
	pg_assert_same('106540352242922', $answer['data']['id']);

	$answer = pg_whatsapp_cloud_answer(502, '<html>Bad gateway</html>');

	pg_assert_false($answer['meta']);
	pg_assert_contains('502', $answer['error']);
	pg_assert_contains('could not be reached', pg_whatsapp_cloud_answer(0, '', 'timeout')['error']);
}

// When a message may be tried again, and when Meta may have it.
function test_whatsapp_cloud_send_parse()
{
	$ok = pg_whatsapp_cloud_send_parse(200, '{"messaging_product":"whatsapp","contacts":[{"input":"+905321234567","wa_id":"905321234567"}],"messages":[{"id":"wamid.HBgLMTY0NjcwNDM1OTUVAgARGBI4","message_status":"accepted"}]}');

	pg_assert_true($ok['ok']);
	pg_assert_same('wamid.HBgLMTY0NjcwNDM1OTUVAgARGBI4', $ok['id']);

	$limit = pg_whatsapp_cloud_send_parse(400, '{"error":{"message":"(#130429) Rate limit hit","code":130429}}');

	pg_assert_true($limit['retry'], 'throughput: later');
	pg_assert_false($limit['uncertain']);

	$missing = pg_whatsapp_cloud_send_parse(404, '{"error":{"message":"(#132001) Template name does not exist in the translation","code":132001}}');

	pg_assert_false($missing['retry']);
	pg_assert_false($missing['uncertain']);
	pg_assert_contains('not approved', $missing['error']);

	pg_assert_true(pg_whatsapp_cloud_send_parse(503, 'Service Unavailable')['uncertain'], 'a 5xx without Meta JSON may come after');
	pg_assert_false(pg_whatsapp_cloud_send_parse(400, 'Bad Request')['uncertain'], 'a bare 4xx took nothing');
	pg_assert_true(pg_whatsapp_cloud_send_parse(200, '{"messages":[]}')['uncertain'], 'accepted without an id');

	$never = pg_whatsapp_cloud_send_parse(0, '', 'could not resolve', true);

	pg_assert_true($never['retry']);
	pg_assert_false($never['uncertain']);
	pg_assert_true(pg_whatsapp_cloud_send_parse(0, '', 'timeout', false)['uncertain'], 'a timeout after the request was written');
}

// The token's check: the app pair, validity, the app, the expiry, the
// permissions.
function test_whatsapp_cloud_debug_parse()
{
	$app = whatsapp_test_app();
	$read = function ($data) use ($app) {
		return pg_whatsapp_cloud_debug_parse(pg_whatsapp_cloud_answer(200, json_encode(array('data' => $data))), $app, 1762900000);
	};
	$good = array('app_id' => '1234567890123', 'type' => 'SYSTEM_USER', 'is_valid' => true, 'expires_at' => 0, 'scopes' => array('business_management', 'whatsapp_business_management', 'whatsapp_business_messaging'));

	pg_assert_true($read($good)['ok']);

	$check = $read(array('is_valid' => false, 'error' => array('code' => 190, 'message' => 'Session has expired')) + $good);
	pg_assert_same(array(false, true, 'token'), array($check['ok'], $check['refused'], $check['field']));
	pg_assert_contains('Session has expired', $check['error']);

	pg_assert_contains('another Meta app', $read(array('app_id' => '999') + $good)['error']);
	pg_assert_contains(gmdate('Y-m-d H:i', 1762992000), $read(array('expires_at' => 1762992000) + $good)['error'], 'a day token is refused with its expiry');
	pg_assert_contains('whatsapp_business_messaging', $read(array('scopes' => array('whatsapp_business_management')) + $good)['error']);

	$pair = pg_whatsapp_cloud_debug_parse(pg_whatsapp_cloud_answer(400, '{"error":{"message":"Error validating client secret.","type":"OAuthException","code":190}}'), $app, 0);
	pg_assert_same(array(true, 'app_secret'), array($pair['refused'], $pair['field']));
	pg_assert_contains('Error validating client secret.', $pair['error']);
	pg_assert_false(strpos($pair['error'], 'access token') !== false, 'the pair refusal does not blame the access token');

	$offline = pg_whatsapp_cloud_debug_parse(pg_whatsapp_cloud_answer(0, '', 'timeout'), $app, 0);
	pg_assert_same(array(false, false), array($offline['ok'], $offline['refused']), 'not asked: saved unchecked');
}

// The number and the account: what is read and which field a refusal marks.
function test_whatsapp_cloud_phone_and_numbers()
{
	$phone = pg_whatsapp_cloud_phone_parse(pg_whatsapp_cloud_answer(200, '{"verified_name":"Pinegrap Shop","display_phone_number":"+90 212 555 01 23","quality_rating":"GREEN","id":"106540352242922"}'));

	pg_assert_same(array('display_phone_number' => '+90 212 555 01 23', 'verified_name' => 'Pinegrap Shop', 'quality_rating' => 'GREEN'), $phone['info']);

	$unknown = pg_whatsapp_cloud_phone_parse(pg_whatsapp_cloud_answer(400, '{"error":{"message":"Unsupported get request.","code":100}}'));
	pg_assert_same('phone_number_id', $unknown['field']);

	$denied = pg_whatsapp_cloud_phone_parse(pg_whatsapp_cloud_answer(403, '{"error":{"message":"Permission denied","code":200}}'));
	pg_assert_same('token', $denied['field']);

	$list = '{"data":[{"id":"106540352242921"},{"id":"106540352242922"}]}';

	pg_assert_true(pg_whatsapp_cloud_numbers_parse(pg_whatsapp_cloud_answer(200, $list), '106540352242922')['ok']);

	$elsewhere = pg_whatsapp_cloud_numbers_parse(pg_whatsapp_cloud_answer(200, $list), '106540352242999');
	pg_assert_same(array(false, true, 'account_id'), array($elsewhere['ok'], $elsewhere['refused'], $elsewhere['field']));
}

// Meta's verify call: the challenge comes back only for the card's token.
function test_whatsapp_webhook_verify_answer()
{
	$get = array('hub_mode' => 'subscribe', 'hub_verify_token' => 'c0ffee', 'hub_challenge' => '1158201444');

	pg_assert_same(array('status' => 200, 'body' => '1158201444', 'wrong' => false), pg_whatsapp_webhook_verify_answer($get, 'c0ffee'));
	pg_assert_same(403, pg_whatsapp_webhook_verify_answer($get, 'other')['status']);
	pg_assert_true(pg_whatsapp_webhook_verify_answer($get, 'other')['wrong']);
	pg_assert_same(403, pg_whatsapp_webhook_verify_answer($get, '')['status'], 'no account, no answer');
	pg_assert_same(400, pg_whatsapp_webhook_verify_answer(array('hub_mode' => 'unsubscribe') + $get, 'c0ffee')['status']);
	pg_assert_same(400, pg_whatsapp_webhook_verify_answer(array('hub_challenge' => '<script>') + $get, 'c0ffee')['status'], 'nothing but the challenge is echoed');
	pg_assert_same(400, pg_whatsapp_webhook_verify_answer(array('hub_verify_token' => array('c0ffee')) + $get, 'c0ffee')['status']);
}

// A post is taken only with the body's signature.
function test_whatsapp_webhook_signed()
{
	$body = '{"object":"whatsapp_business_account","entry":[]}';
	$signature = 'sha256=' . hash_hmac('sha256', $body, 'secret');

	pg_assert_true(pg_whatsapp_webhook_signed($body, $signature, 'secret'));
	pg_assert_true(pg_whatsapp_webhook_signed($body, 'sha256=' . strtoupper(substr($signature, 7)), 'secret'), 'hex in capitals');
	pg_assert_false(pg_whatsapp_webhook_signed($body . ' ', $signature, 'secret'), 'a changed body');
	pg_assert_false(pg_whatsapp_webhook_signed($body, substr($signature, 7), 'secret'), 'without its prefix');
	pg_assert_false(pg_whatsapp_webhook_signed($body, $signature, ''), 'no app secret');
	pg_assert_false(pg_whatsapp_webhook_signed($body, '', 'secret'));
}

// Meta's ids and secrets as typed.
function test_whatsapp_ids_and_secrets()
{
	pg_assert_true(pg_whatsapp_id_valid('106540352242922'));
	pg_assert_false(pg_whatsapp_id_valid('1065-4035'));
	pg_assert_false(pg_whatsapp_id_valid('123'));
	pg_assert_true(pg_whatsapp_secret_valid('0123456789abcdef0123456789abcdef', 16));
	pg_assert_false(pg_whatsapp_secret_valid('0123456789 abcdef0123456789abcdef', 16));
	pg_assert_false(pg_whatsapp_secret_valid('short', 16));
	pg_assert_same(40, strlen(pg_whatsapp_verify_token_new()));
	pg_assert_true(pg_whatsapp_cloud_code_retries('131056'));
	pg_assert_false(pg_whatsapp_cloud_code_retries('131050'));
}
