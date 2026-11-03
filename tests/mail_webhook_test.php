<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail transport webhooks (includes/fn/mail_webhook.php and
 * each driver's webhook reader): every provider's documented report read
 * into events, the Mailgun and Resend signatures (good, wrong, stale), what
 * an event does, the replay list and what a save of the card writes. No
 * request is served and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array_keys(pg_mail_transports()) as $mail_webhook_test_key) {
	pg_mail_transport_driver($mail_webhook_test_key);
}

function test_mail_webhook_every_driver_reads_reports()
{
	foreach (array_keys(pg_mail_transports()) as $key) {
		pg_assert_true(function_exists('pg_mail_' . $key . '_webhook'), $key . ' has a reader');
		pg_assert_same(false, call_user_func('pg_mail_' . $key . '_webhook', 'not json', array()), $key . ' refuses a body it cannot read');
	}
}

function test_mail_webhook_postmark()
{
	$bounce = '{"RecordType":"Bounce","ID":4323372036854775807,"Type":"HardBounce","TypeCode":1,"Name":"Hard bounce","MessageID":"883953f4-6105-42a2-a16a-77a8eac79483","MessageStream":"outbound","Email":"John@Example.com","From":"sender@example.com","BouncedAt":"2019-11-05T16:33:54.9070259Z"}';
	pg_assert_same(array(array('id' => 'postmark:Bounce:4323372036854775807', 'kind' => 'hard', 'address' => 'John@Example.com', 'message_id' => '883953f4-6105-42a2-a16a-77a8eac79483')), pg_mail_postmark_webhook($bounce, array()));

	$soft = pg_mail_postmark_webhook('{"RecordType":"Bounce","ID":5,"Type":"SoftBounce","Email":"a@example.com","MessageID":"m"}', array());
	pg_assert_same('soft', $soft[0]['kind']);

	$auto = pg_mail_postmark_webhook('{"RecordType":"Bounce","ID":6,"Type":"AutoResponder","Email":"a@example.com","MessageID":"m"}', array());
	pg_assert_same('', $auto[0]['kind'], 'an auto-responder is not a bounce');

	$spam = pg_mail_postmark_webhook('{"RecordType":"SpamComplaint","ID":7,"Type":"SpamComplaint","Email":"a@example.com","MessageID":"m"}', array());
	pg_assert_same('complaint', $spam[0]['kind']);

	$delivery = pg_mail_postmark_webhook('{"RecordType":"Delivery","MessageID":"m","Recipient":"a@example.com","DeliveredAt":"2019-11-05T16:33:54Z"}', array());
	pg_assert_same(array('id' => 'postmark:Delivery:m:a@example.com', 'kind' => 'delivered', 'address' => 'a@example.com', 'message_id' => 'm'), $delivery[0]);
}

function test_mail_webhook_brevo()
{
	$hard = pg_mail_brevo_webhook('{"event":"hard_bounce","email":"a@example.com","id":12,"date":"2020-10-09 00:00:00","ts_event":1604933619,"message-id":"<201798300811.5787683@relay.domain.com>","reason":"unknown user"}', array());
	pg_assert_same(array(array('id' => 'brevo:hard_bounce:<201798300811.5787683@relay.domain.com>:a@example.com:1604933619', 'kind' => 'hard', 'address' => 'a@example.com', 'message_id' => '<201798300811.5787683@relay.domain.com>')), $hard);

	foreach (array('soft_bounce' => 'soft', 'spam' => 'complaint', 'delivered' => 'delivered', 'blocked' => '', 'opened' => '') as $event => $kind) {
		$read = pg_mail_brevo_webhook('{"event":"' . $event . '","email":"a@example.com","message-id":"<m>","ts_event":1}', array());
		pg_assert_same($kind, $read[0]['kind'], $event);
	}
}

function mail_webhook_test_mailgun($event, $severity = '')
{
	$data = array('event' => $event, 'id' => 'G9Bn5sl1TC6nu79C8C0bwg', 'recipient' => 'alice@example.com', 'message' => array('headers' => array('message-id' => '20130503182626.18666.16540@example.com')));

	if ($severity !== '') {
		$data['severity'] = $severity;
	}

	return json_encode(array('signature' => array('timestamp' => '1529006854', 'token' => 'a8ce0edb2dd8301dee6c2405235584e45aa91d1e9f979f3de0', 'signature' => hash_hmac('sha256', '1529006854a8ce0edb2dd8301dee6c2405235584e45aa91d1e9f979f3de0', 'signing-key')), 'event-data' => $data));
}

function test_mail_webhook_mailgun()
{
	pg_assert_same(array(array('id' => 'mailgun:G9Bn5sl1TC6nu79C8C0bwg', 'kind' => 'hard', 'address' => 'alice@example.com', 'message_id' => '20130503182626.18666.16540@example.com')), pg_mail_mailgun_webhook(mail_webhook_test_mailgun('failed', 'permanent'), array()));

	$read = pg_mail_mailgun_webhook(mail_webhook_test_mailgun('failed', 'temporary'), array());
	pg_assert_same('soft', $read[0]['kind']);
	$read = pg_mail_mailgun_webhook(mail_webhook_test_mailgun('complained'), array());
	pg_assert_same('complaint', $read[0]['kind']);
	$read = pg_mail_mailgun_webhook(mail_webhook_test_mailgun('opened'), array());
	pg_assert_same('', $read[0]['kind']);

	$raw = mail_webhook_test_mailgun('failed', 'permanent');
	pg_assert_same('ok', pg_mail_mailgun_webhook_verify($raw, array(), 'signing-key', 1529006854 + 10));
	pg_assert_same('stale', pg_mail_mailgun_webhook_verify($raw, array(), 'signing-key', 1529006854 + PG_MAIL_WEBHOOK_MAX_AGE + 1));
	pg_assert_same('bad', pg_mail_mailgun_webhook_verify($raw, array(), 'other-key', 1529006854));
	pg_assert_same('bad', pg_mail_mailgun_webhook_verify('{"event-data":{}}', array(), 'signing-key', 1529006854));
}

function mail_webhook_test_resend_headers($raw, $secret, $timestamp)
{
	$signature = base64_encode(hash_hmac('sha256', 'msg_2Lh9KRb6pzN4pRhz.' . $timestamp . '.' . $raw, base64_decode($secret), true));

	return array('svix-id' => 'msg_2Lh9KRb6pzN4pRhz', 'svix-timestamp' => (string) $timestamp, 'svix-signature' => 'v1,bm90IGl0 v1,' . $signature);
}

function test_mail_webhook_resend()
{
	$raw = '{"type":"email.bounced","created_at":"2024-11-22T23:41:12.126Z","data":{"created_at":"2024-11-22T23:41:11.894719+00:00","email_id":"56761188-7520-42d8-8898-ff6fc54ce618","from":"Acme <onboarding@resend.dev>","to":["delivered@resend.dev","b@example.com"],"subject":"Sending this example","bounce":{"message":"The recipient\'s email address is on the suppression list.","subType":"Suppressed","type":"Permanent"}}}';
	$secret = base64_encode('resend-secret-bytes');
	$headers = mail_webhook_test_resend_headers($raw, $secret, 1700000000);

	pg_assert_same(array(
		array('id' => 'resend:msg_2Lh9KRb6pzN4pRhz:delivered@resend.dev', 'kind' => 'hard', 'address' => 'delivered@resend.dev', 'message_id' => '56761188-7520-42d8-8898-ff6fc54ce618'),
		array('id' => 'resend:msg_2Lh9KRb6pzN4pRhz:b@example.com', 'kind' => 'hard', 'address' => 'b@example.com', 'message_id' => '56761188-7520-42d8-8898-ff6fc54ce618'),
	), pg_mail_resend_webhook($raw, $headers));

	$transient = pg_mail_resend_webhook(str_replace('"Permanent"', '"Transient"', $raw), $headers);
	pg_assert_same('soft', $transient[0]['kind']);
	$complained = pg_mail_resend_webhook('{"type":"email.complained","data":{"email_id":"x","to":["a@example.com"]}}', array());
	pg_assert_same('complaint', $complained[0]['kind']);

	pg_assert_same('ok', pg_mail_resend_webhook_verify($raw, $headers, 'whsec_' . $secret, 1700000060));
	pg_assert_same('stale', pg_mail_resend_webhook_verify($raw, $headers, 'whsec_' . $secret, 1700000000 + PG_MAIL_WEBHOOK_MAX_AGE + 1));
	pg_assert_same('bad', pg_mail_resend_webhook_verify($raw . ' ', $headers, 'whsec_' . $secret, 1700000000), 'a changed body');
	pg_assert_same('bad', pg_mail_resend_webhook_verify($raw, $headers, 'whsec_' . base64_encode('other'), 1700000000));
	pg_assert_same('bad', pg_mail_resend_webhook_verify($raw, array(), 'whsec_' . $secret, 1700000000), 'no Svix headers');
}

function test_mail_webhook_ses()
{
	$bounce = json_encode(array('notificationType' => 'Bounce', 'bounce' => array('bounceType' => 'Permanent', 'bounceSubType' => 'General', 'bouncedRecipients' => array(array('emailAddress' => 'jane@example.com', 'status' => '5.1.1')), 'feedbackId' => '0100'), 'mail' => array('messageId' => '000001378603177f-7a5433e7')));
	$raw = json_encode(array('Type' => 'Notification', 'MessageId' => '22b80b92-fdea-4c2c-8f9d-bdfb0c7bf324', 'TopicArn' => 'arn:aws:sns:us-west-2:123456789012:MyTopic', 'Message' => $bounce));

	pg_assert_same(array(array('id' => 'ses:22b80b92-fdea-4c2c-8f9d-bdfb0c7bf324:jane@example.com', 'kind' => 'hard', 'address' => 'jane@example.com', 'message_id' => '000001378603177f-7a5433e7')), pg_mail_ses_webhook($raw, array()));

	$complaint = json_encode(array('Type' => 'Notification', 'MessageId' => 'c1', 'Message' => json_encode(array('eventType' => 'Complaint', 'complaint' => array('complainedRecipients' => array(array('emailAddress' => 'r@example.com'))), 'mail' => array('messageId' => 'm')))));
	$read = pg_mail_ses_webhook($complaint, array());
	pg_assert_same('complaint', $read[0]['kind'], 'event publishing names it eventType');

	$transient = pg_mail_ses_webhook(str_replace('Permanent', 'Transient', $raw), array());
	pg_assert_same('soft', $transient[0]['kind']);

	$confirm = pg_mail_ses_webhook(json_encode(array('Type' => 'SubscriptionConfirmation', 'MessageId' => 's1', 'SubscribeURL' => 'https://sns.us-west-2.amazonaws.com/?Action=ConfirmSubscription&Token=abc')), array());
	pg_assert_same('confirm', $confirm[0]['kind']);
	pg_assert_same(array(), pg_mail_ses_webhook(json_encode(array('Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'https://evil.example.com/sns.us-west-2.amazonaws.com')), array()), 'a confirmation address off SNS is not visited');
	pg_assert_same(array(), pg_mail_ses_webhook(json_encode(array('Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'http://sns.us-west-2.amazonaws.com/x')), array()), 'nor one without TLS');
}

function test_mail_webhook_decide()
{
	pg_assert_same(array('action' => 'record', 'kind' => 'hard', 'address' => 'a@example.com'), pg_mail_webhook_decide(array('kind' => 'hard', 'address' => ' A@Example.com ')));
	pg_assert_same('ignored', pg_mail_webhook_decide(array('kind' => 'hard', 'address' => 'not an address'))['action'], 'an address the record cannot key');
	pg_assert_same('ignored', pg_mail_webhook_decide(array('kind' => '', 'address' => 'a@example.com'))['action']);
	pg_assert_same('delivered', pg_mail_webhook_decide(array('kind' => 'delivered', 'address' => 'a@example.com'))['action'], 'a delivery records nothing');
	pg_assert_same('confirm', pg_mail_webhook_decide(array('kind' => 'confirm', 'address' => '', 'url' => 'https://sns.x.amazonaws.com/'))['action']);
	pg_assert_same('ignored', pg_mail_webhook_decide(array('kind' => 'manual', 'address' => 'a@example.com'))['action'], 'a report never blocks by hand');
}

function test_mail_webhook_seen_note()
{
	$first = pg_mail_webhook_seen_note(null, 'a');
	pg_assert_same(array('seen' => false, 'list' => array('a')), $first);
	pg_assert_same(true, pg_mail_webhook_seen_note($first['list'], 'a')['seen'], 'a replay');

	$full = pg_mail_webhook_seen_note(array_map('strval', range(1, PG_MAIL_WEBHOOK_SEEN_KEPT)), 'b');
	pg_assert_same(PG_MAIL_WEBHOOK_SEEN_KEPT, count($full['list']));
	pg_assert_same('b', end($full['list']));
}

function test_mail_webhook_settings_decide()
{
	$none = array('webhook_key' => '', 'webhook_signing' => '');
	$signed = array('webhook' => true, 'signed' => true);

	pg_assert_same(array('webhook_key' => 'new'), pg_mail_webhook_settings_decide(array('webhook' => true, 'signed' => false), $none, 'typed', 'new'), 'unsigned service: the key only');
	pg_assert_same(array('webhook_key' => 'new', 'webhook_signing' => 'sig'), pg_mail_webhook_settings_decide($signed, $none, 'sig', 'new'));
	pg_assert_same(array(), pg_mail_webhook_settings_decide($signed, array('webhook_key' => 'old', 'webhook_signing' => 'sig'), '', 'new'), 'a stored key stays and an empty box keeps the signing key');
	pg_assert_same(array(), pg_mail_webhook_settings_decide(array('webhook' => false, 'signed' => false), $none, 'sig', 'new'));
}

function test_mail_webhook_url_and_support()
{
	pg_assert_same('/mail_webhook.php?t=postmark&key=k%2B1', pg_mail_webhook_url('postmark', 'k+1'));
	pg_assert_same(array('webhook' => true, 'signed' => true), pg_mail_webhook_support('mailgun'));
	pg_assert_same(array('webhook' => true, 'signed' => false), pg_mail_webhook_support('postmark'));
	pg_assert_same(array('webhook' => false, 'signed' => false), pg_mail_webhook_support('smtp'));
	pg_assert_same(array('svix-id' => 'x', 'content-type' => 'a'), pg_mail_webhook_headers(array('HTTP_SVIX_ID' => 'x', 'HTTP_CONTENT_TYPE' => 'a', 'REMOTE_ADDR' => '1.2.3.4')));
}
