<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the incoming mail parser in includes/fn/mail_parse.php: header
 * unfolding and RFC 2047 words, RFC 2231 parameters, addresses and message
 * IDs, transfer encodings, charsets to UTF-8, multipart trees with text,
 * HTML and attachments, the limits, and broken input that must never raise.
 * Every fixture is inline.
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

// A fixture with CRLF line endings, as mail travels.
function mail_parse_test_crlf($lines)
{
	return implode("\r\n", $lines);
}

// A typical client message: alternative text and HTML inside mixed, a PDF
// with a Turkish RFC 2231 file name and an inline image in related.
function mail_parse_test_message()
{
	return mail_parse_test_crlf(array(
		'Return-Path: <ayse@example.com.tr>',
		'From: =?UTF-8?B?QXnFn2UgWcSxbG1heg==?= <Ayse@Example.COM.tr>',
		'To: "Destek, Ekip" <support@company.com>, ali@company.com',
		'Cc: undisclosed-recipients:;',
		'Subject: =?ISO-8859-9?Q?Sipari=FE_hakk=FDnda?=',
		' =?UTF-8?Q?_=C3=A7ok_acil?=',
		'Date: Tue, 27 Oct 2026 10:15:00 +0300 (TRT)',
		'Message-ID: <abc.123@mail.example.com.tr>',
		'In-Reply-To: <pg.c12.x9@company.com>',
		'References: <first@a.example> <pg.c12.x9@company.com>',
		'MIME-Version: 1.0',
		'Content-Type: multipart/mixed; boundary="outer"',
		'',
		'This is a multi-part message in MIME format.',
		'--outer',
		'Content-Type: multipart/alternative; boundary=inner',
		'',
		'--inner',
		'Content-Type: text/plain; charset=utf-8',
		'Content-Transfer-Encoding: quoted-printable',
		'',
		'Merhaba, sipari=C5=9Fim gelmedi. Bu sat=C4=B1r uzun bir sat=C4=B1rd=',
		'=C4=B1r.',
		'--inner',
		'Content-Type: multipart/related; boundary="rel"',
		'',
		'--rel',
		'Content-Type: text/html; charset="windows-1254"',
		'Content-Transfer-Encoding: 8bit',
		'',
		"<p>Sipari\xFEim gelmedi.</p><img src=\"cid:logo@x\">",
		'--rel',
		'Content-Type: image/png',
		'Content-ID: <logo@x>',
		'Content-Transfer-Encoding: base64',
		'',
		'iVBORw0KGgo=',
		'--rel--',
		'--inner--',
		'--outer',
		'Content-Type: application/pdf; name="fallback.pdf"',
		"Content-Disposition: attachment; filename*=utf-8''Fatura%20%C5%9Fubat.pdf",
		'Content-Transfer-Encoding: base64',
		'',
		'JVBERi0x',
		'LjQK',
		'--outer--',
		'epilogue',
	));
}

function test_mail_parse_headers_and_addresses()
{
	$mail = pg_mail_parse(mail_parse_test_message());

	pg_assert_same("Sipari\xC5\x9F hakk\xC4\xB1nda \xC3\xA7ok acil", $mail['subject'], 'subject over two encoded words');
	pg_assert_same(array('email' => 'Ayse@example.com.tr', 'name' => "Ay\xC5\x9Fe Y\xC4\xB1lmaz"), $mail['from'], 'from');
	pg_assert_same(array(
		array('email' => 'support@company.com', 'name' => 'Destek, Ekip'),
		array('email' => 'ali@company.com', 'name' => ''),
	), $mail['to'], 'to');
	pg_assert_same(array(), $mail['cc'], 'empty group');
	pg_assert_same('abc.123@mail.example.com.tr', $mail['message_id']);
	pg_assert_same(array('pg.c12.x9@company.com'), $mail['in_reply_to']);
	pg_assert_same(array('first@a.example', 'pg.c12.x9@company.com'), $mail['references']);
	pg_assert_same(gmmktime(7, 15, 0, 10, 27, 2026), $mail['date'], 'date with comment');
	pg_assert_same(array('<ayse@example.com.tr>'), $mail['headers']['return-path']);
	pg_assert_same(array(), $mail['errors']);
}

function test_mail_parse_bodies_and_attachments()
{
	$mail = pg_mail_parse(mail_parse_test_message(), array('with_content' => true));

	pg_assert_same("Merhaba, sipari\xC5\x9Fim gelmedi. Bu sat\xC4\xB1r uzun bir sat\xC4\xB1rd\xC4\xB1r.", $mail['text'], 'quoted-printable soft break joins');
	pg_assert_same("<p>Sipari\xC5\x9Fim gelmedi.</p><img src=\"cid:logo@x\">", $mail['html'], 'windows-1254 html');
	pg_assert_same(2, count($mail['attachments']));

	$image = $mail['attachments'][0];
	pg_assert_same('image/png', $image['content_type']);
	pg_assert_same('logo@x', $image['content_id']);
	pg_assert_true($image['inline'], 'cid image is inline');
	pg_assert_same('1.2.2', $image['part']);
	pg_assert_same("\x89PNG\r\n\x1A\n", $image['content']);
	pg_assert_same(8, $image['size']);

	$pdf = $mail['attachments'][1];
	pg_assert_same("Fatura \xC5\x9Fubat.pdf", $pdf['filename'], 'RFC 2231 name wins over name=');
	pg_assert_same('attachment', $pdf['disposition']);
	pg_assert_false($pdf['inline']);
	pg_assert_same('2', $pdf['part']);
	pg_assert_same("%PDF-1.4\n", $pdf['content'], 'base64 over several lines');

	$light = pg_mail_parse(mail_parse_test_message());
	pg_assert_false(isset($light['attachments'][1]['content']), 'no content by default');
}

function test_mail_parse_header_decode()
{
	pg_assert_same('plain text', pg_mail_header_decode('plain text'));
	pg_assert_same('ab', pg_mail_header_decode('=?utf-8?q?a?= =?utf-8?q?b?='), 'space between words dropped');
	pg_assert_same('a b', pg_mail_header_decode('=?utf-8?q?a?= b'), 'plain text kept');
	pg_assert_same('Re: a_b c', pg_mail_header_decode('Re: =?UTF-8?Q?a=5Fb_c?='));
	pg_assert_same("\xC3\xA7", pg_mail_header_decode('=?UTF-8?B?ww==?= =?UTF-8?B?pw==?='), 'character split across words survives');
	pg_assert_same("\xC3\xA7", pg_mail_header_decode('=?UTF-8*tr?Q?=C3=A7?='), 'RFC 2231 language tag');
	pg_assert_same("\xC4\x9F", pg_mail_header_decode('=?iso-8859-9?Q?=F0?='));
	pg_assert_same('a b', pg_mail_header_decode('=?UTF-8?Q?a=0Ab?='), 'no line break smuggled in');
	pg_assert_same('=?broken', pg_mail_header_decode('=?broken'));
	pg_assert_same("\xC5\x9F", pg_mail_header_decode("\xFE"), 'raw 8-bit header read as windows-1254');
}

function test_mail_parse_header_params()
{
	pg_assert_same(array('value' => 'text/plain', 'params' => array('charset' => 'UTF-8', 'format' => 'flowed')), pg_mail_header_params('Text/Plain; charset=UTF-8; Format="flowed"'));

	$split = pg_mail_header_params("attachment; filename*0*=iso-8859-9''%DE; filename*1=ubat; filename*2*=%20rapor.txt");
	pg_assert_same("\xC5\x9Eubat rapor.txt", $split['params']['filename'], 'continuations joined and decoded');

	$quoted = pg_mail_header_params('attachment; filename="a;b \\"c\\".txt"');
	pg_assert_same('a;b "c".txt', $quoted['params']['filename'], 'semicolon and escapes inside quotes');

	$word = pg_mail_header_params('attachment; filename="=?UTF-8?B?w6dpZnQucGRm?="');
	pg_assert_same("\xC3\xA7ift.pdf", $word['params']['filename'], 'encoded word inside a quoted value');

	pg_assert_same(array('value' => '', 'params' => array()), pg_mail_header_params(''));
}

function test_mail_parse_addresses_and_ids()
{
	pg_assert_same(array(array('email' => 'a@b.example', 'name' => 'Ali Veli')), pg_mail_address_list('a@B.Example (Ali Veli)'));
	pg_assert_same(array(array('email' => 'x@y.example', 'name' => '')), pg_mail_address_list('<mailto:x@y.example>'));
	pg_assert_same(array(
		array('email' => 'a@t.example', 'name' => ''),
		array('email' => 'b@t.example', 'name' => 'B'),
	), pg_mail_address_list('Team: a@t.example, B <b@t.example>;'), 'group members');
	pg_assert_same(array(), pg_mail_address_list('not an address, @nobody, someone@'));

	pg_assert_same(array('x@y'), pg_mail_message_ids('x@y'), 'bare id');
	pg_assert_same(array('a@b', 'c@d'), pg_mail_message_ids('<a@b> <c@d>  <a@b>'), 'no repeats');
	pg_assert_same(array(), pg_mail_message_ids('garbage'));

	pg_assert_same(0, pg_mail_parse_date('not a date'));
	pg_assert_same(0, pg_mail_parse_date(''));
}

function test_mail_parse_charsets()
{
	pg_assert_same("\xC4\xB0\xC5\x9F\xC4\x9F", pg_mail_charset_to_utf8("\xDD\xFE\xF0", 'ISO-8859-9'));
	pg_assert_same("\xC3\x9D\xC3\xBE", pg_mail_charset_to_utf8("\xDD\xFE", 'latin1'));
	pg_assert_same("\xE2\x82\xAC", pg_mail_charset_to_utf8("\x80", 'windows-1252'));
	pg_assert_same("\xEF\xBF\xBD", pg_mail_charset_to_utf8("\x8E", 'windows-1254'), 'undefined byte');
	pg_assert_same("a\xEF\xBF\xBDb\xC3\xA7", pg_mail_charset_to_utf8("a\xFFb\xC3\xA7", 'utf-8'), 'broken byte in UTF-8 text');
	pg_assert_same("\xC3\xA7", pg_mail_charset_to_utf8("\xC3\xA7", 'us-ascii'), 'mislabelled UTF-8 kept');
	pg_assert_same("\xC5\x9F", pg_mail_charset_to_utf8("\xFE", 'x-no-such-charset'), 'unknown charset falls back');
	pg_assert_same("\xC5\x9F", pg_mail_charset_to_utf8("\xFE", 'bad charset; name'), 'odd charset name falls back');
	pg_assert_same('plain', pg_mail_charset_to_utf8('plain', 'koi8-r'));

	if (function_exists('mb_convert_encoding') || function_exists('iconv')) {
		pg_assert_same("\xD0\x9F", pg_mail_charset_to_utf8("\xF0", 'koi8-r'), 'other charsets through mbstring or iconv');
	}
}

function test_mail_parse_transfer_encodings()
{
	pg_assert_same("a=b\nc", pg_mail_decode_body("a=3Db  \nc", 'quoted-printable'), 'trailing space dropped');
	pg_assert_same('=ZZ', pg_mail_decode_body('=ZZ', 'quoted-printable'), 'broken escape kept');
	pg_assert_same('a', pg_mail_decode_body("YQ\n", 'base64'), 'missing padding');
	pg_assert_same('hello', pg_mail_decode_body("aGVs\n bG8=\n", 'base64'));
	pg_assert_same('raw', pg_mail_decode_body('raw', 'x-uuencode'), 'unknown encoding as is');

	$state = array('errors' => array());
	pg_assert_same('', pg_mail_decode_body('!!!', 'base64', $state));
	pg_assert_same(array('bad_base64'), $state['errors']);
}

function test_mail_parse_simple_and_odd_messages()
{
	$plain = pg_mail_parse("From: a@b.example\nSubject: Hi\n\nBody line\n");
	pg_assert_same('Body line', $plain['text'], 'LF only, no MIME headers');
	pg_assert_same('Hi', $plain['subject']);

	$mbox = pg_mail_parse("From a@b.example Tue Oct 27 10:00:00 2026\nSubject: X\n\nY");
	pg_assert_same('X', $mbox['subject'], 'mbox separator line skipped');

	$latin = pg_mail_parse("Subject: x\nContent-Type: text/plain\n\nSipari\xFE");
	pg_assert_same("Sipari\xC5\x9F", $latin['text'], 'unlabelled 8-bit body');

	$html_only = pg_mail_parse("Content-Type: text/html; charset=utf-8\n\n<b>x</b>");
	pg_assert_same('', $html_only['text']);
	pg_assert_same('<b>x</b>', $html_only['html']);

	$forward = pg_mail_parse(mail_parse_test_crlf(array(
		'Content-Type: multipart/mixed; boundary=b',
		'',
		'--b',
		'Content-Type: text/plain',
		'',
		'See below.',
		'--b',
		'Content-Type: message/rfc822',
		'',
		'Subject: inner',
		'',
		'inner body',
		'--b--',
	)));
	pg_assert_same('See below.', $forward['text']);
	pg_assert_same(1, count($forward['attachments']), 'forwarded mail is an attachment');
	pg_assert_same('message/rfc822', $forward['attachments'][0]['content_type']);

	$named_text = pg_mail_parse("Content-Type: multipart/mixed; boundary=b\n\n--b\nContent-Type: text/plain\n\nbody\n--b\nContent-Type: text/plain; name=\"../../notes.txt\"\n\nfile\n--b--");
	pg_assert_same('body', $named_text['text'], 'named text part is not body');
	pg_assert_same('notes.txt', $named_text['attachments'][0]['filename'], 'path removed from the name');
}

function test_mail_parse_broken_input_never_raises()
{
	$inputs = array(
		'',
		"\n\n\n",
		'no headers at all',
		"Content-Type: multipart/mixed\n\nno boundary",
		"Content-Type: multipart/mixed; boundary=zz\n\n--zz\nContent-Type: text/plain\n\nunclosed",
		"Content-Type: multipart/mixed; boundary=zz\n\nno boundary lines here",
		"Content-Type: ;;;==\"\nContent-Transfer-Encoding: base64\n\n@@@",
		": empty name\n\x00\x01\xFF\n\nbody",
		"Subject: =?utf-8?B?\xFF\xFE?=\n\n",
		"From: \"unterminated <a@b\n\n",
		"Content-Type: multipart/alternative; boundary=\"\"\n\n--\n",
	);

	foreach ($inputs as $index => $input) {
		$mail = pg_mail_parse($input);
		pg_assert_true(is_array($mail) && isset($mail['text'], $mail['errors']), 'input ' . $index);
		pg_assert_true(pg_mail_is_utf8($mail['text'] . $mail['html'] . $mail['subject']), 'UTF-8 out for input ' . $index);
	}

	$unclosed = pg_mail_parse($inputs[4]);
	pg_assert_same('unclosed', $unclosed['text']);
	pg_assert_same(array('no_closing_boundary'), $unclosed['errors']);

	$no_lines = pg_mail_parse($inputs[5]);
	pg_assert_same('no boundary lines here', $no_lines['text'], 'body kept as text');

	$no_boundary = pg_mail_parse($inputs[3]);
	pg_assert_same(array('no_boundary'), $no_boundary['errors']);
	pg_assert_same('no boundary', $no_boundary['text']);
}

function test_mail_parse_limits()
{
	$big = pg_mail_parse("Subject: big\n\n" . str_repeat('x', 200), array('max_bytes' => 100));
	pg_assert_same('big', $big['subject'], 'headers still read');
	pg_assert_same('', $big['text'], 'body not parsed');
	pg_assert_same(array('too_large'), $big['errors']);

	// Twelve nested multiparts with the text at the bottom.
	$raw = '';
	$close = '';

	for ($level = 0; $level < 12; $level++) {
		$raw .= "Content-Type: multipart/mixed; boundary=b{$level}\n\n--b{$level}\n";
		$close = "\n--b{$level}--" . $close;
	}

	$raw .= "Content-Type: text/plain\n\ndeep" . $close;
	$deep = pg_mail_parse($raw, array('max_depth' => 5));
	pg_assert_same('', $deep['text']);
	pg_assert_same(array('too_deep'), $deep['errors']);
	$within = pg_mail_parse($raw, array('max_depth' => 20));
	pg_assert_same('deep', $within['text'], 'within the depth limit');

	$many = "Content-Type: multipart/mixed; boundary=m\n\n";

	for ($part = 0; $part < 10; $part++) {
		$many .= "--m\nContent-Type: application/octet-stream\n\nx\n";
	}

	$parts = pg_mail_parse($many . '--m--', array('max_parts' => 4));
	pg_assert_same(3, count($parts['attachments']), 'the container counts as a part');
	pg_assert_same(array('too_many_parts'), $parts['errors']);

	$headers = pg_mail_parse(str_repeat("X-A: 1\n", 20) . "\nbody", array('max_headers' => 5));
	pg_assert_same(5, count($headers['headers']['x-a']));
	pg_assert_same(array('too_many_headers'), $headers['errors']);

	$long = pg_mail_parse('Subject: ' . str_repeat('y', 300) . "\n\nbody", array('max_header_bytes' => 50));
	pg_assert_same(41, strlen($long['subject']));
	pg_assert_true(in_array('header_too_large', $long['errors'], true));
}

function test_mail_parse_safe_filename()
{
	pg_assert_same('evil.exe', pg_mail_safe_filename('C:\\Windows\\evil.exe'));
	pg_assert_same('htaccess', pg_mail_safe_filename('..htaccess'));
	pg_assert_same('a_b_.txt', pg_mail_safe_filename("a:b?\x01.txt"));
	pg_assert_same('', pg_mail_safe_filename('...'));

	$long = pg_mail_safe_filename(str_repeat("\xC5\x9F", 150) . '.pdf');
	pg_assert_true(strlen($long) <= 200, 'at most 200 bytes');
	pg_assert_true(pg_mail_is_utf8($long), 'cut on a whole character');
	pg_assert_same('.pdf', substr($long, -4), 'extension kept');
}
