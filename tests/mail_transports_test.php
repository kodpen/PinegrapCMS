<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail transports (includes/fn/mail_transport.php and the
 * drivers under includes/mail/): the request each driver builds from a fixed
 * message, how each reads its provider's answers, the message read back from
 * PHPMailer, which refusals are permanent and what a save of the settings
 * card does. No provider is called and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array_keys(pg_mail_transports()) as $mail_transports_test_key) {
	pg_mail_transport_driver($mail_transports_test_key);
}

function mail_transports_test_message($type = 'system')
{
	return array(
		'from'        => 'shop@example.com',
		'from_name'   => 'Example "Shop"',
		'to'          => array(array('ana@example.org', 'Ana'), array('bo@example.org', '')),
		'cc'          => array(),
		'bcc'         => array(array('audit@example.com', '')),
		'reply_to'    => array(array('help@example.com', 'Help')),
		'subject'     => 'Order 1001',
		'text'        => 'Thank you.',
		'html'        => '<p>Thank you.</p>',
		'headers'     => array('X-Pinegrap-Mail' => 'abc', 'List-Unsubscribe' => '<https://example.com/u>'),
		'attachments' => array(array('name' => 'a.txt', 'type' => 'text/plain', 'content' => 'hi', 'cid' => '')),
		'type'        => $type,
	);
}

function mail_transports_test_settings($values, $secret = 'k-123')
{
	return array('values' => $values, 'secret' => $secret, 'secret_stored' => true, 'enabled' => true);
}

function test_mail_transports_every_driver_is_complete()
{
	foreach (array_keys(pg_mail_transports()) as $key) {
		pg_assert_true(pg_mail_transport_driver($key), $key);

		$secrets = 0;

		foreach (pg_mail_transport_fields($key) as $field) {
			$secrets += ($field['type'] === 'secret') ? 1 : 0;
		}

		pg_assert_same(1, $secrets, $key . ' has one secret field');
	}

	pg_assert_false(pg_mail_transport_driver('smtp'));
	pg_assert_false(pg_mail_transport_driver('../x'));
}

function test_mail_transports_address_quotes_the_name()
{
	pg_assert_same('a@example.com', pg_mail_transport_address('a@example.com', ' '));
	pg_assert_same('"Example \"Shop\"" <a@example.com>', pg_mail_transport_address('a@example.com', 'Example "Shop"'));
	pg_assert_same('"A B" <a@example.com>', pg_mail_transport_address('a@example.com', "A\r\nB"));
}

function test_mail_transports_postmark_request()
{
	$request = pg_mail_postmark_request(mail_transports_test_message(), mail_transports_test_settings(array('stream' => 'outbound', 'campaign_stream' => 'broadcast')));
	$body = json_decode($request['body'], true);

	pg_assert_same('https://api.postmarkapp.com/email', $request['url']);
	pg_assert_true(in_array('X-Postmark-Server-Token: k-123', $request['headers'], true));
	pg_assert_same('"Example \"Shop\"" <shop@example.com>', $body['From']);
	pg_assert_same('"Ana" <ana@example.org>, bo@example.org', $body['To']);
	pg_assert_same('audit@example.com', $body['Bcc']);
	pg_assert_same('"Help" <help@example.com>', $body['ReplyTo']);
	pg_assert_same('outbound', $body['MessageStream']);
	pg_assert_same('<p>Thank you.</p>', $body['HtmlBody']);
	pg_assert_same('Thank you.', $body['TextBody']);
	pg_assert_same(array('Name' => 'X-Pinegrap-Mail', 'Value' => 'abc'), $body['Headers'][0]);
	pg_assert_same(array('Name' => 'a.txt', 'Content' => 'aGk=', 'ContentType' => 'text/plain'), $body['Attachments'][0]);
	pg_assert_false(isset($body['Cc']));

	$campaign = json_decode(pg_mail_postmark_request(mail_transports_test_message('campaign'), mail_transports_test_settings(array('stream' => 'outbound', 'campaign_stream' => 'broadcast')))['body'], true);
	pg_assert_same('broadcast', $campaign['MessageStream']);

	$campaign = json_decode(pg_mail_postmark_request(mail_transports_test_message('campaign'), mail_transports_test_settings(array('stream' => 'outbound', 'campaign_stream' => '')))['body'], true);
	pg_assert_same('outbound', $campaign['MessageStream']);
}

function test_mail_transports_postmark_answer()
{
	pg_assert_same(array('ok' => true, 'id' => 'b7bc2f4a', 'error' => ''), pg_mail_postmark_answer(200, '{"To":"a@example.org","ErrorCode":0,"Message":"OK","MessageID":"b7bc2f4a"}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => 'Invalid \'From\' address.'), pg_mail_postmark_answer(422, '{"ErrorCode":300,"Message":"Invalid \'From\' address."}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => ''), pg_mail_postmark_answer(500, '<html>'));
}

function test_mail_transports_mailgun_request()
{
	$message = mail_transports_test_message();
	$message['attachments'][] = array('name' => 'logo.png', 'type' => 'image/png', 'content' => 'PNG', 'cid' => 'logo1');

	$request = pg_mail_mailgun_request($message, mail_transports_test_settings(array('domain' => 'MG.Example.com', 'region' => 'eu')), 'BND');

	pg_assert_same('https://api.eu.mailgun.net/v3/mg.example.com/messages', $request['url']);
	pg_assert_same('api:k-123', $request['userpwd']);
	pg_assert_true(in_array('Content-Type: multipart/form-data; boundary=BND', $request['headers'], true));

	$expected = "--BND\r\nContent-Disposition: form-data; name=\"from\"\r\n\r\n\"Example \\\"Shop\\\"\" <shop@example.com>\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"to\"\r\n\r\n\"Ana\" <ana@example.org>\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"to\"\r\n\r\nbo@example.org\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"bcc\"\r\n\r\naudit@example.com\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"subject\"\r\n\r\nOrder 1001\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"text\"\r\n\r\nThank you.\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"html\"\r\n\r\n<p>Thank you.</p>\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"h:Reply-To\"\r\n\r\n\"Help\" <help@example.com>\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"h:X-Pinegrap-Mail\"\r\n\r\nabc\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"h:List-Unsubscribe\"\r\n\r\n<https://example.com/u>\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"attachment\"; filename=\"a.txt\"\r\nContent-Type: text/plain\r\n\r\nhi\r\n"
		. "--BND\r\nContent-Disposition: form-data; name=\"inline\"; filename=\"logo1\"\r\nContent-Type: image/png\r\n\r\nPNG\r\n"
		. "--BND--\r\n";

	pg_assert_same($expected, $request['body']);

	$us = pg_mail_mailgun_request(mail_transports_test_message(), mail_transports_test_settings(array('domain' => 'mg.example.com', 'region' => 'us')), 'BND');
	pg_assert_same('https://api.mailgun.net/v3/mg.example.com/messages', $us['url']);
}

function test_mail_transports_mailgun_part_keeps_quotes_out_of_names()
{
	pg_assert_same("--B\r\nContent-Disposition: form-data; name=\"attachment\"; filename=\"a%22b.txt\"\r\nContent-Type: application/octet-stream\r\n\r\nx\r\n",
		pg_mail_mailgun_part('B', 'attachment', 'x', "a\"b\r\n.txt"));
}

function test_mail_transports_mailgun_answer()
{
	pg_assert_same(array('ok' => true, 'id' => '<2026.1@mg.example.com>', 'error' => ''), pg_mail_mailgun_answer(200, '{"id":"<2026.1@mg.example.com>","message":"Queued. Thank you."}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => 'to parameter is not a valid address. please check documentation'), pg_mail_mailgun_answer(400, '{"message":"to parameter is not a valid address. please check documentation"}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => 'Forbidden'), pg_mail_mailgun_answer(401, 'Forbidden'));
}

function test_mail_transports_brevo_request()
{
	$request = pg_mail_brevo_request(mail_transports_test_message(), mail_transports_test_settings(array()));
	$body = json_decode($request['body'], true);

	pg_assert_same('https://api.brevo.com/v3/smtp/email', $request['url']);
	pg_assert_true(in_array('api-key: k-123', $request['headers'], true));
	pg_assert_same(array('email' => 'shop@example.com', 'name' => 'Example "Shop"'), $body['sender']);
	pg_assert_same(array(array('email' => 'ana@example.org', 'name' => 'Ana'), array('email' => 'bo@example.org')), $body['to']);
	pg_assert_same(array(array('email' => 'audit@example.com')), $body['bcc']);
	pg_assert_same(array('email' => 'help@example.com', 'name' => 'Help'), $body['replyTo']);
	pg_assert_same('<p>Thank you.</p>', $body['htmlContent']);
	pg_assert_same('Thank you.', $body['textContent']);
	pg_assert_same(array('X-Pinegrap-Mail' => 'abc', 'List-Unsubscribe' => '<https://example.com/u>'), $body['headers']);
	pg_assert_same(array(array('name' => 'a.txt', 'content' => 'aGk=')), $body['attachment']);
	pg_assert_false(isset($body['cc']));
}

function test_mail_transports_brevo_answer()
{
	pg_assert_same(array('ok' => true, 'id' => '<20261102.1@smtp-relay.mailin.fr>', 'error' => ''), pg_mail_brevo_answer(201, '{"messageId":"<20261102.1@smtp-relay.mailin.fr>"}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => 'Key not found'), pg_mail_brevo_answer(401, '{"code":"unauthorized","message":"Key not found"}'));
}

function test_mail_transports_resend_request()
{
	$message = mail_transports_test_message();
	$message['html'] = '';
	$message['attachments'][0]['cid'] = 'c1';

	$request = pg_mail_resend_request($message, mail_transports_test_settings(array()));
	$body = json_decode($request['body'], true);

	pg_assert_same('https://api.resend.com/emails', $request['url']);
	pg_assert_true(in_array('Authorization: Bearer k-123', $request['headers'], true));
	pg_assert_same('"Example \"Shop\"" <shop@example.com>', $body['from']);
	pg_assert_same(array('"Ana" <ana@example.org>', 'bo@example.org'), $body['to']);
	pg_assert_same(array('"Help" <help@example.com>'), $body['reply_to']);
	pg_assert_same('Thank you.', $body['text']);
	pg_assert_false(isset($body['html']));
	pg_assert_same(array(array('filename' => 'a.txt', 'content' => 'aGk=', 'content_type' => 'text/plain', 'content_id' => 'c1')), $body['attachments']);
}

function test_mail_transports_resend_answer()
{
	pg_assert_same(array('ok' => true, 'id' => '49a3999c-0ce1-4ea6-ab68-afcd6dc2e794', 'error' => ''), pg_mail_resend_answer(200, '{"id":"49a3999c-0ce1-4ea6-ab68-afcd6dc2e794"}'));
	pg_assert_same(array('ok' => false, 'id' => '', 'error' => 'The example.com domain is not verified.'), pg_mail_resend_answer(403, '{"statusCode":403,"name":"validation_error","message":"The example.com domain is not verified."}'));
}

function test_mail_transports_status_permanent()
{
	pg_assert_true(pg_mail_transport_status_permanent(400));
	pg_assert_true(pg_mail_transport_status_permanent(422));
	pg_assert_false(pg_mail_transport_status_permanent(401));
	pg_assert_false(pg_mail_transport_status_permanent(403));
	pg_assert_false(pg_mail_transport_status_permanent(429));
	pg_assert_false(pg_mail_transport_status_permanent(500));
	pg_assert_false(pg_mail_transport_status_permanent(0));
}

function test_mail_transports_message_from_phpmailer()
{
	$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
	$mail->CharSet = 'UTF-8';
	$mail->setFrom('shop@example.com', 'Shop');
	$mail->addAddress('ana@example.org', 'Ana');
	$mail->addBCC('audit@example.com');
	$mail->addReplyTo('help@example.com');
	$mail->addCustomHeader('X-Pinegrap-Mail', 'abc');
	$mail->addStringAttachment('hi', 'a.txt', 'base64', 'text/plain');
	$mail->Subject = 'Order';
	$mail->isHTML(true);
	$mail->Body = '<p>Hi</p>';
	$mail->AltBody = 'Hi';

	$message = pg_mail_transport_message($mail, 'campaign');

	pg_assert_same('shop@example.com', $message['from']);
	pg_assert_same('Shop', $message['from_name']);
	pg_assert_same(array(array('ana@example.org', 'Ana')), $message['to']);
	pg_assert_same(array(array('audit@example.com', '')), $message['bcc']);
	pg_assert_same(array(array('help@example.com', '')), $message['reply_to']);
	pg_assert_same('<p>Hi</p>', $message['html']);
	pg_assert_same('Hi', $message['text']);
	pg_assert_same(array('X-Pinegrap-Mail' => 'abc'), $message['headers']);
	pg_assert_same(array(array('name' => 'a.txt', 'type' => 'text/plain', 'content' => 'hi', 'cid' => '')), $message['attachments']);
	pg_assert_same('campaign', $message['type']);

	$plain = new \PHPMailer\PHPMailer\PHPMailer(true);
	$plain->setFrom('shop@example.com');
	$plain->addAddress('ana@example.org');
	$plain->Body = 'Plain';

	$message = pg_mail_transport_message($plain, 'other');

	pg_assert_same('Plain', $message['text']);
	pg_assert_same('', $message['html']);
	pg_assert_same('system', $message['type']);
}

function mail_transports_test_before($enabled = '')
{
	$before = array();

	foreach (array_keys(pg_mail_transports()) as $key) {
		$settings = array('values' => array(), 'secret' => '', 'secret_stored' => false, 'enabled' => ($key === $enabled));

		foreach (pg_mail_transport_fields($key) as $name => $field) {
			if ($field['type'] !== 'secret') {
				$settings['values'][$name] = isset($field['default']) ? (string) $field['default'] : '';
			}
		}

		$before[$key] = $settings;
	}

	return $before;
}

function mail_transports_test_fields()
{
	$fields = array();

	foreach (array_keys(pg_mail_transports()) as $key) {
		$fields[$key] = pg_mail_transport_fields($key);
	}

	return $fields;
}

function test_mail_transports_settings_decide_smtp_left_alone()
{
	$decision = pg_mail_transport_settings_decide(array('mail_transport' => 'smtp'), mail_transports_test_fields(), mail_transports_test_before(), true);

	pg_assert_same('smtp', $decision['chosen']);
	pg_assert_same(null, $decision['write']);
	pg_assert_same(array(), $decision['disable']);

	// An unknown key reads as SMTP.
	pg_assert_same('smtp', pg_mail_transport_settings_decide(array('mail_transport' => 'nope'), mail_transports_test_fields(), mail_transports_test_before(), true)['chosen']);
}

function test_mail_transports_settings_decide_switches_on_and_others_off()
{
	$post = array('mail_transport' => 'mailgun', 'mail_mailgun_key' => " key-1\n", 'mail_mailgun_domain' => 'mg.example.com', 'mail_mailgun_region' => 'mars');
	$decision = pg_mail_transport_settings_decide($post, mail_transports_test_fields(), mail_transports_test_before('postmark'), true);

	pg_assert_same('mailgun', $decision['chosen']);
	pg_assert_same(array('values' => array('domain' => 'mg.example.com', 'region' => 'us'), 'secret' => 'key-1'), $decision['write']);
	pg_assert_same(array('postmark'), $decision['disable']);
	pg_assert_same(array(), $decision['marks']);
}

function test_mail_transports_settings_decide_marks_missing_fields()
{
	$decision = pg_mail_transport_settings_decide(array('mail_transport' => 'mailgun', 'mail_mailgun_domain' => ''), mail_transports_test_fields(), mail_transports_test_before('postmark'), true);

	pg_assert_same(array('mail_mailgun_key', 'mail_mailgun_domain'), array_keys($decision['marks']));
	pg_assert_same(null, $decision['write']);
	pg_assert_same(array(), $decision['disable'], 'nothing switched off while a field is marked');
}

function test_mail_transports_settings_decide_stored_secret_kept()
{
	$before = mail_transports_test_before('resend');
	$before['resend']['secret'] = 're_1';
	$before['resend']['secret_stored'] = true;

	$decision = pg_mail_transport_settings_decide(array('mail_transport' => 'resend', 'mail_resend_key' => ''), mail_transports_test_fields(), $before, false);

	pg_assert_same(null, $decision['write'], 'unchanged: nothing written, no key needed');
	pg_assert_same('', $decision['error']);

	$before['resend']['enabled'] = false;
	$decision = pg_mail_transport_settings_decide(array('mail_transport' => 'resend', 'mail_resend_key' => ''), mail_transports_test_fields(), $before, true);

	pg_assert_same(array('values' => array(), 'secret' => null), $decision['write']);

	$decision = pg_mail_transport_settings_decide(array('mail_transport' => 'resend', 'mail_resend_key' => 're_2'), mail_transports_test_fields(), $before, false);

	pg_assert_same(null, $decision['write']);
	pg_assert_true($decision['error'] !== '');
}
