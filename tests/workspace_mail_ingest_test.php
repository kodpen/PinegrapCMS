<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the incoming mail ingest
 * (includes/workspace/mail.php): the de-duplication key, the loop gate, the
 * DMARC result believed only from the box's own server, the Message-IDs a
 * mail answers, the subject tag, HTML and plain text made even, Workspace
 * tags taken apart in the channel message, which attachments come in, and
 * where the raw mail is kept. ws_mail_ingest() itself writes to the
 * database and is not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_ingest_test_mail($headers, $body = 'Hello')
{
	return pg_mail_parse(implode("\r\n", $headers) . "\r\n\r\n" . $body);
}

function test_workspace_mail_hash_keys_by_message_id_else_by_the_raw_mail()
{
	pg_assert_same(hash('sha256', 'id:abc@example.com'), ws_mail_hash('abc@example.com', 'raw one'), 'the Message-ID');
	pg_assert_same(ws_mail_hash('abc@example.com', 'raw one'), ws_mail_hash(' abc@example.com ', 'raw two'), 'the raw mail does not matter beside an id');
	pg_assert_same(hash('sha256', 'raw:raw one'), ws_mail_hash('', 'raw one'), 'no id: the raw mail');
	pg_assert_true(ws_mail_hash('', 'raw one') !== ws_mail_hash('', 'raw two'), 'two mails without an id differ');
	pg_assert_same(64, strlen(ws_mail_hash('', '')), 'fits CHAR(64)');
}

function test_workspace_mail_loop_gate()
{
	$plain = workspace_mail_ingest_test_mail(array('From: Ayşe <ayse@example.com>', 'Subject: Hi'));

	pg_assert_same('', ws_mail_loop_reason($plain, 'support@shop.test'), 'a person\'s mail comes in');
	pg_assert_same('own', ws_mail_loop_reason($plain, 'Ayse@Example.com'), 'the box\'s own address as sender, any case');
	pg_assert_same('own', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'X-Pinegrap-Mail: ' . pg_mail_site_mark())), 'support@shop.test'), 'this site\'s mark');
	pg_assert_same('', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: shop@other.test', 'X-Pinegrap-Mail: 0123456789abcdef')), 'support@shop.test'), 'another Pinegrap site\'s mail comes in');
	pg_assert_same('auto', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'Auto-Submitted: auto-replied; owner-email="a@example.com"')), 'support@shop.test'), 'an automatic answer');
	pg_assert_same('', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'Auto-Submitted: No')), 'support@shop.test'), 'Auto-Submitted: no is a person');
	pg_assert_same('bulk', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'Precedence: bulk')), 'support@shop.test'), 'bulk');
	pg_assert_same('bulk', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'Precedence: List')), 'support@shop.test'), 'a list');
	pg_assert_same('', ws_mail_loop_reason(workspace_mail_ingest_test_mail(array('From: a@example.com', 'Precedence: first-class')), 'support@shop.test'), 'other precedence');
}

function test_workspace_mail_auth_believes_only_the_box_server()
{
	$headers = array('authentication-results' => array(
		'mx.google.com; dkim=pass header.i=@example.com; spf=pass; dmarc=pass (p=REJECT) header.from=example.com',
	));

	pg_assert_same('pass', ws_mail_auth($headers, 'mx.google.com'), 'the box\'s server');
	pg_assert_same('pass', ws_mail_auth($headers, ' MX.Google.com '), 'any case');
	pg_assert_same('none', ws_mail_auth($headers, ''), 'a box that names no server trusts no header');
	pg_assert_same('none', ws_mail_auth($headers, 'mx.other.test'), 'another server\'s header is not believed');

	$forged = array('authentication-results' => array(
		'evil.test; dmarc=pass header.from=example.com',
		'mx.google.com 1; dmarc=fail (p=NONE) header.from=example.com',
	));

	pg_assert_same('fail', ws_mail_auth($forged, 'mx.google.com'), 'a header naming another server is skipped, the version after the id read past');
	pg_assert_same('none', ws_mail_auth(array('authentication-results' => array('mx.google.com; spf=pass')), 'mx.google.com'), 'no DMARC result');
	pg_assert_same('none', ws_mail_auth(array('authentication-results' => array('mx.google.com; arc=pass header.dmarc=pass')), 'mx.google.com'), 'only a dmarc= result counts');
	pg_assert_same('none', ws_mail_auth(array(), 'mx.google.com'), 'no header');
}

function test_workspace_mail_thread_ids_closest_first()
{
	$parsed = workspace_mail_ingest_test_mail(array(
		'From: a@example.com',
		'In-Reply-To: <three@example.com>',
		'References: <one@example.com> <two@example.com> <three@example.com>',
	));

	pg_assert_same(array('three@example.com', 'two@example.com', 'one@example.com'), ws_mail_thread_ids($parsed), 'In-Reply-To, then References from the newest, once each');

	$refs = array();

	for ($i = 0; $i < 40; $i++) {
		$refs[] = 'r' . $i . '@example.com';
	}

	pg_assert_same(30, count(ws_mail_thread_ids(array('in_reply_to' => array(), 'references' => $refs))), 'at most 30');
	pg_assert_same(array(), ws_mail_thread_ids(array()), 'none');
}

function test_workspace_mail_subject_tag()
{
	pg_assert_same(123, ws_mail_subject_tag('Re: Order question [#T123]'), 'the tag');
	pg_assert_same(0, ws_mail_subject_tag('Re: Order #T123'), 'no brackets, no tag');
	pg_assert_same(0, ws_mail_subject_tag(''), 'no subject');
}

function test_workspace_mail_text_from_plain_or_html()
{
	pg_assert_same("Line one\n\nLine two", ws_mail_tidy_text("  Line one  \r\n\r\n\r\n\r\nLine two\x07\r\n"), 'one kind of break, one empty line at most, no control characters');
	pg_assert_same("Hello\nWorld\n\n- one\n- two\nA & B", ws_mail_html_text('<html><head><title>T</title><style>p{}</style></head><body><p>Hello<br>World</p><ul><li>one</li><li>two</li></ul><script>x()</script>A &amp; B</body></html>'), 'HTML as text');
	pg_assert_same('Plain', ws_mail_text(array('text' => 'Plain', 'html' => '<p>Rich</p>')), 'the plain body first');
	pg_assert_same('Rich', ws_mail_text(array('text' => "  \n", 'html' => '<p>Rich</p>')), 'else the HTML');
	pg_assert_same(WS_MAIL_TEXT_MAX, strlen(ws_mail_tidy_text(str_repeat('ş', WS_MAIL_TEXT_MAX))), 'cut on a character boundary');
}

function test_workspace_mail_message_body_takes_workspace_tags_apart()
{
	pg_assert_same('Hi @user:1 see #task:22 and <b>', ws_mail_message_body('Hi <@user:1> see <#task:22> and <b>'), 'tags name nothing');
	pg_assert_same(array(), ws_tokens(ws_mail_message_body('<@user:1><#order:5>')), 'no token left for the renderer');

	$long = ws_mail_message_body(str_repeat('a', WS_MESSAGE_MAX + 50));

	pg_assert_same(WS_MESSAGE_MAX, mb_strlen($long), 'cut to a message');
	pg_assert_same('…', mb_substr($long, -1), 'marked as cut');
}

function test_workspace_mail_attachments_sort()
{
	$refused = function ($name) {
		return (bool) preg_match('/\.(exe|html)$/i', $name);
	};
	$file = function ($name, $size = 10, $inline = false, $type = 'application/pdf') {
		return array('filename' => $name, 'content_type' => $type, 'size' => $size, 'inline' => $inline, 'content' => str_repeat('x', $size));
	};

	$sorted = ws_mail_attachments_sort(array(
		$file('invoice.pdf'),
		$file('', 10, true, 'image/png'),
		$file('', 10, false, 'application/octet-stream'),
		$file('setup.exe'),
		$file('big.zip', 2000),
		$file('logo.png', 10, true, 'image/png'),
	), $refused, 1000);

	pg_assert_same(array('invoice.pdf', 'logo.png'), array_column($sorted['take'], 'filename'), 'named files the channel takes, an inline one with a name too');
	pg_assert_same(array('application/octet-stream', 'setup.exe', 'big.zip'), $sorted['refused'], 'a nameless part by its type, a refused name, a file too large; a nameless inline picture left out silently');

	$many = array();

	for ($i = 0; $i < WS_MAIL_ATTACHMENTS_MAX + 2; $i++) {
		$many[] = $file('f' . $i . '.pdf');
	}

	$sorted = ws_mail_attachments_sort($many, $refused, 1000);

	pg_assert_same(WS_MAIL_ATTACHMENTS_MAX, count($sorted['take']), 'at most the limit');
	pg_assert_same(array('f20.pdf', 'f21.pdf'), $sorted['refused'], 'the rest by name');

	$no_content = $file('a.pdf');
	unset($no_content['content']);
	pg_assert_same(array('a.pdf'), ws_mail_attachments_sort(array($no_content), $refused, 1000)['refused'], 'a part parsed without its bytes is refused');
}

function test_workspace_mail_attachments_use_the_channel_rule()
{
	pg_assert_true(ws_upload_refused('page.html'), 'the channel refuses HTML');
	pg_assert_true(ws_upload_refused('logo.svg'), 'and SVG');
	pg_assert_true(!ws_upload_refused('invoice.pdf'), 'a PDF comes in');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');

	pg_assert_contains("ws_mail_attachments_sort(\$parsed['attachments'], 'ws_upload_refused', ws_upload_max_bytes())", $source, 'the ingest sorts by the channel\'s own rule and size');
	pg_assert_contains("\$inspected = ws_upload_inspect(", $source, 'and inspects the bytes as an upload');
}

function test_workspace_mail_eml_path()
{
	$hash = hash('sha256', 'x');

	pg_assert_same('mail/7/202610/' . $hash . '.eml', ws_mail_eml_path(7, $hash, gmmktime(12, 0, 0, 10, 28, 2026)), 'box and month');
	pg_assert_same('mail/7/202610/abc.eml', ws_mail_eml_path(7, '../ABC/', gmmktime(12, 0, 0, 10, 28, 2026)), 'only hex characters in the name');
}

function test_workspace_mail_eml_sweep_runs_from_the_daily_job()
{
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_recurring_job.php');

	pg_assert_contains("if (ws_enabled() && function_exists('ws_mail_eml_sweep')) {\n    ws_mail_eml_sweep();\n}", $job, 'called by the repeating-tasks job');
}
