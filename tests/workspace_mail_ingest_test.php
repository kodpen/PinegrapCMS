<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the incoming mail ingest
 * (includes/workspace/mail.php): the de-duplication key, the loop gate, the
 * DMARC result believed only from the box's own server, the Message-IDs a
 * mail answers and who a conversation lets in by them, the subject tag, HTML and plain text made even, Workspace
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

function test_workspace_mail_sender_took_part()
{
	$mails = array(
		array('from_email' => 'report@dmarc.example', 'to_list' => 'support@shop.test', 'cc_list' => ''),
		array('from_email' => 'ayse@example.com', 'to_list' => 'support@shop.test', 'cc_list' => 'Boss <Boss@Example.com>, ali@example.com'),
		array('from_email' => 'support@shop.test', 'to_list' => 'ayse@example.com', 'cc_list' => '', 'bcc_list' => 'audit@example.com'),
	);

	pg_assert_true(ws_mail_sender_took_part('Ayse@Example.com', 'ayse@example.com', array()), 'the customer, any case, without the mails');
	pg_assert_true(ws_mail_sender_took_part('boss@example.com', 'ayse@example.com', $mails), 'one the customer copied');
	pg_assert_true(ws_mail_sender_took_part('ali@example.com', 'ayse@example.com', $mails), 'the second address of a list');
	pg_assert_true(ws_mail_sender_took_part('audit@example.com', 'ayse@example.com', $mails), 'one an answer was sent to in Bcc');
	pg_assert_true(ws_mail_sender_took_part('report@dmarc.example', 'ayse@example.com', $mails), 'one who wrote in it');
	pg_assert_true(!ws_mail_sender_took_part('stranger@example.com', 'report@dmarc.example', $mails), 'a sender none of its mails names stays out');
	pg_assert_true(!ws_mail_sender_took_part('example.com', 'report@dmarc.example', $mails), 'a part of an address is no address');
	pg_assert_true(!ws_mail_sender_took_part('', '', $mails), 'no sender');
}

function test_workspace_mail_find_conversation_lets_in_who_took_part()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	$start = strpos($source, 'function ws_mail_find_conversation(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_mail_sender_took_part($from_email, (string) $row[\'customer_email\'], (array) $mails)', $body, 'a mail named by its headers lets in only who took part');
	pg_assert_contains("pg_schema_has('ws_mail_items', 'bcc_list')", $body, 'Bcc read only once the column is there');
	pg_assert_true(strpos($body, 'ws_mail_subject_tag(') > strpos($body, 'ws_mail_sender_took_part('), 'then the subject tag, with the same customer');
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

	pg_assert_contains("ws_mail_attachments_sort(\$parsed['attachments'], 'ws_upload_refused', ws_upload_max_bytes(), ", $source, 'the ingest sorts by the channel\'s own rule and size');
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

function test_workspace_mail_attachments_sort_takes_the_pictures_the_html_shows()
{
	$refused = function ($name) {
		return (bool) preg_match('/\.(exe|html|svg)$/i', $name);
	};
	$part = function ($name, $cid, $type = 'image/png', $inline = true) {
		return array('filename' => $name, 'content_type' => $type, 'size' => 10, 'inline' => $inline, 'content_id' => $cid, 'content' => 'xxxxxxxxxx');
	};
	$parts = array(
		$part('', 'Logo@Example.com'),
		$part('', 'photo@example.com', 'image/jpeg', false),
		$part('', 'unused@example.com'),
		$part('', 'icon@example.com', 'image/svg+xml'),
		$part('banner.gif', 'banner@example.com', 'image/gif'),
		$part('', 'pixel@example.com', 'image/webp'),
	);
	$shown = array('logo@example.com', 'photo@example.com', 'icon@example.com', 'banner@example.com', 'pixel@example.com');
	$sorted = ws_mail_attachments_sort($parts, $refused, 1000, $shown);

	pg_assert_same(array('image-1.png', 'image-2.jpg', 'banner.gif', 'image-3.webp'), array_column($sorted['take'], 'filename'), 'a picture the HTML shows comes in, named by its type');
	pg_assert_same(array('logo@example.com', 'photo@example.com', 'banner@example.com', 'pixel@example.com'), array_map('pg_mail_html_cid', array_column($sorted['take'], 'content_id')), 'each keeps its Content-ID');
	pg_assert_same(array(), $sorted['refused'], 'an inline picture the HTML does not show, and a nameless SVG, stay out silently');

	$plain = ws_mail_attachments_sort($parts, $refused, 1000);

	pg_assert_same(array('banner.gif'), array_column($plain['take'], 'filename'), 'without HTML a nameless picture stays out as before');
	pg_assert_same(array('image/jpeg'), $plain['refused'], 'and a nameless part that is not inline is refused by its type');
}

function test_workspace_mail_picture_extension()
{
	pg_assert_same('png', ws_mail_picture_extension('image/png'));
	pg_assert_same('jpg', ws_mail_picture_extension(' IMAGE/JPEG '));
	pg_assert_same('jpg', ws_mail_picture_extension('image/pjpeg'));
	pg_assert_same('gif', ws_mail_picture_extension('image/gif'));
	pg_assert_same('webp', ws_mail_picture_extension('image/webp'));
	pg_assert_same('', ws_mail_picture_extension('image/svg+xml'), 'no SVG');
	pg_assert_same('', ws_mail_picture_extension('application/pdf'));
}

function test_workspace_mail_html_fill_files_matches_pictures_by_kept_name()
{
	$attachments = array(
		array('filename' => 'logo.png', 'content_id' => 'logo@x'),
		array('filename' => 'C:\\Users\\a\\photo.jpg', 'content_id' => '<Photo@x>'),
		array('filename' => '', 'content_id' => 'nameless@x'),
		array('filename' => 'report.pdf', 'content_id' => ''),
		array('filename' => 'other.png', 'content_id' => 'other@x'),
		array('filename' => 'logo.png', 'content_id' => 'second@x'),
	);
	$messages = array(
		array('file_id' => 11, 'file_name' => 'logo.png'),
		array('file_id' => 12, 'file_name' => 'photo.jpg'),
		array('file_id' => 13, 'file_name' => 'report.pdf'),
		array('file_id' => 14, 'file_name' => 'logo.png'),
		array('file_id' => 0, 'file_name' => 'other.png'),
	);

	pg_assert_same(array('logo@x' => 11, 'photo@x' => 12, 'second@x' => 14), ws_mail_html_fill_files($attachments, array('logo@x', 'photo@x', 'nameless@x', 'other@x', 'second@x'), $messages),
		'by the name the upload kept, two of one name in turn; a nameless picture and a message without a file give nothing');
	pg_assert_same(array('second@x' => 14), ws_mail_html_fill_files($attachments, array('second@x'), $messages), 'a picture not shown still takes its own file');
	pg_assert_same(array(), ws_mail_html_fill_files($attachments, array(), $messages), 'no picture shown');
}

function test_workspace_mail_html_fill_done()
{
	pg_assert_true(ws_mail_html_fill_done(3, 3, 200), 'fewer than asked, all read');
	pg_assert_true(ws_mail_html_fill_done(0, 0, 200), 'nothing left');
	pg_assert_false(ws_mail_html_fill_done(200, 200, 200), 'a full run: there may be more');
	pg_assert_false(ws_mail_html_fill_done(50, 120, 200), 'stopped by the time limit');
}

function test_workspace_mail_html_is_kept_beside_the_text()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_recurring_job.php');

	pg_assert_contains("\$html = \$html_kept ? pg_mail_html_clean((string) (\$parsed['html'] ?? '')) : '';", $source, 'the HTML part is cleaned only once the column is there');
	pg_assert_contains("db(\"UPDATE ws_mail_items SET body_html = '\" . e(\$html) . \"' WHERE id = '\" . \$item_id . \"'\");", $source, 'in a statement of its own');
	pg_assert_contains("return function_exists('pg_mail_html_clean') && pg_schema_has('ws_mail_items', 'body_html');", $source, 'the column is asked for');

	$fill = strpos($job, "ws_mail_html_fill();");
	$sweep = strpos($job, "ws_mail_eml_sweep();");

	pg_assert_true(($fill !== false) && ($sweep !== false) && ($fill < $sweep), 'the fill runs before the sweep deletes the .eml it reads');
	pg_assert_contains("function ws_mail_html_fill(\$limit = 200)", $source, '200 a run');
}
