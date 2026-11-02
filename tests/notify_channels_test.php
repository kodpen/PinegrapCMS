<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chat notification channels (includes/fn/notify_channels.php):
 * the target checks, the message shape, the Slack, Teams and Telegram bodies
 * for every operations alert code, and escaping in each.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _notify_test_targets()
{
	return array(
		'slack'    => array('url' => 'https://hooks.slack.com/services/T000/B000/XXXXXXXXXXXXXXXX'),
		'teams'    => array('url' => 'https://contoso.webhook.office.com/webhookb2/abc/IncomingWebhook/def/ghi'),
		'telegram' => array('token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0', 'chat_id' => '-1001234567890'),
	);
}

function test_notify_channels_target_valid()
{
	foreach (_notify_test_targets() as $kind => $target) {
		pg_assert_true(pg_notify_target_valid($kind, $target), $kind . ': its own address passes');
	}

	pg_assert_true(pg_notify_target_valid('teams', array('url' => 'https://prod-01.westeurope.logic.azure.com/workflows/x/triggers/manual/paths/invoke?sig=y')), 'a Workflows address');
	pg_assert_true(pg_notify_target_valid('telegram', array('token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0', 'chat_id' => '@site_alerts')), 'an @channel');
	pg_assert_false(pg_notify_target_valid('slack', array('url' => 'http://hooks.slack.com/services/T/B/X')), 'https only');
	pg_assert_false(pg_notify_target_valid('slack', array('url' => 'https://hooks.slack.com.evil.example/services/T/B/X')), 'Slack\'s own host only');
	pg_assert_false(pg_notify_target_valid('slack', array('url' => 'https://user@hooks.slack.com/services/T/B/X')), 'no user part');
	pg_assert_false(pg_notify_target_valid('teams', array('url' => 'https://webhook.office.com.evil.example/x')), 'a look-alike Teams host');
	pg_assert_false(pg_notify_target_valid('teams', array('url' => 'https://evilwebhook.office.com/x')), 'the suffix starts at a dot');
	pg_assert_false(pg_notify_target_valid('telegram', array('token' => '123:short', 'chat_id' => '1')), 'a token too short');
	pg_assert_false(pg_notify_target_valid('telegram', array('token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0/x', 'chat_id' => '1')), 'no path in the token');
	pg_assert_false(pg_notify_target_valid('telegram', array('token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0', 'chat_id' => '1&x=2')), 'a chat id is a number or a name');
	pg_assert_false(pg_notify_target_valid('discord', array('url' => 'https://discord.com/api/webhooks/1/x')), 'an unknown kind');
	pg_assert_false(pg_notify_target_valid('slack', 'https://hooks.slack.com/services/T/B/X'), 'not an array');
}

function test_notify_channels_message_shape()
{
	$m = pg_notify_message("  Disk\n space  ", "line 1\r\nline 2", 'javascript:alert(1)', 'fatal', ' example.com ');

	pg_assert_same('Disk space', $m['title'], 'one line');
	pg_assert_same("line 1\nline 2", $m['text'], 'line ends made plain');
	pg_assert_same('', $m['url'], 'only an http(s) address is kept');
	pg_assert_same('info', $m['level'], 'an unknown level is info');
	pg_assert_same('example.com', $m['site'], 'trimmed');
	pg_assert_same(null, pg_notify_alert_message('no_such_alert', pg_ops_alert_codes(), '', '', 'x'), 'an unknown code');
}

function test_notify_channels_bodies_for_every_alert_code()
{
	$codes = pg_ops_alert_codes();

	foreach ($codes as $code => $alert) {
		$message = pg_notify_alert_message($code, $codes, 'Detail of ' . $code, 'https://example.com/pinegrap/welcome.php', 'example.com');
		pg_assert_same(($alert['type'] === 'error') ? 'error' : 'warning', $message['level'], $code . ': level from the alert type');

		foreach (_notify_test_targets() as $kind => $target) {
			$request = pg_notify_request($kind, $target, $message);
			$body = json_decode($request['body'], true);
			pg_assert_true(is_array($body), $code . ' / ' . $kind . ': a JSON body');
			pg_assert_true(strpos($request['body'], 'Detail of ' . $code) !== false, $code . ' / ' . $kind . ': carries the detail');
		}
	}
}

function test_notify_channels_slack_body()
{
	$body = pg_notify_slack_body(pg_notify_message('A <b> & c', 'x > y', 'https://example.com/a?b=1&c=2', 'error', 'Site'));

	pg_assert_same('A <b> & c (Site)', $body['text'], 'the fallback text is plain');
	pg_assert_same(':red_circle: *A &lt;b&gt; &amp; c*' . "\n" . 'x &gt; y', $body['blocks'][0]['text']['text'], 'mrkdwn escaped');
	pg_assert_same('context', $body['blocks'][1]['type'], 'the site as context');
	pg_assert_same('https://example.com/a?b=1&c=2', $body['blocks'][2]['elements'][0]['url'], 'the button address as given');
	pg_assert_same(1, count(pg_notify_slack_body(pg_notify_message('t', '', '', 'info', ''))['blocks']), 'no site, no address: one block');
}

function test_notify_channels_teams_body()
{
	$body = pg_notify_teams_body(pg_notify_message('Title', 'Text', 'https://example.com/x', 'warning', 'Site'));
	$attachment = $body['attachments'][0];

	pg_assert_same('message', $body['type'], 'a message');
	pg_assert_same('application/vnd.microsoft.card.adaptive', $attachment['contentType'], 'an Adaptive Card');
	pg_assert_same('1.4', $attachment['content']['version'], 'card version');
	pg_assert_same('Warning', $attachment['content']['body'][0]['color'], 'warning colour');
	pg_assert_same(3, count($attachment['content']['body']), 'title, text, site');
	pg_assert_same('https://example.com/x', $attachment['content']['actions'][0]['url'], 'open action');
	pg_assert_false(isset(pg_notify_teams_body(pg_notify_message('t', '', '', 'info', ''))['attachments'][0]['content']['actions']), 'no address, no action');
}

function test_notify_channels_telegram_request()
{
	$targets = _notify_test_targets();
	$request = pg_notify_request('telegram', $targets['telegram'], pg_notify_message('<script>', 'a & b', "https://example.com/?a=1&b='2'", 'info', 'Site'));
	$body = json_decode($request['body'], true);

	pg_assert_same('https://api.telegram.org/bot123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0/sendMessage', $request['url'], 'the bot address');
	pg_assert_same('-1001234567890', $body['chat_id'], 'the chat');
	pg_assert_same('HTML', $body['parse_mode'], 'HTML mode');
	pg_assert_true(strpos($body['text'], '<b>&lt;script&gt;</b>') !== false, 'the title escaped');
	pg_assert_true(strpos($body['text'], 'a &amp; b') !== false, 'the text escaped');
	pg_assert_true(strpos($body['text'], 'href="https://example.com/?a=1&amp;b=&#039;2&#039;"') !== false, 'the link escaped');

	$long = json_decode(pg_notify_request('telegram', $targets['telegram'], pg_notify_message('t', str_repeat('ş', 5000), '', 'info', ''))['body'], true);
	pg_assert_false(isset($long['parse_mode']), 'a cut message is sent as plain text');
	pg_assert_true(mb_strlen($long['text'], 'UTF-8') <= 4096, 'within Telegram\'s limit');
	pg_assert_same(null, pg_notify_request('slack', array('url' => 'https://example.com/x'), pg_notify_message('t', '', '', 'info', '')), 'no request to an address that is not the service\'s');
}
