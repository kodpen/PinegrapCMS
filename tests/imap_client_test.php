<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the IMAP client in includes/fn/imap_client.php: the tokenizer,
 * the tagged and untagged response parsers, astrings and modified UTF-7,
 * sequence sets, and whole sessions played against a scripted server over
 * memory streams (sign-in both ways, SELECT, SEARCH, FETCH with literals,
 * STORE, MOVE and its fallback, failures). Every fixture is inline.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/imap_client.php');

// A session whose server says $script (lines joined with CRLF); the second
// value is the stream the client wrote to.
function imap_client_test_session($script)
{
	$in = fopen('php://memory', 'w+b');
	fwrite($in, is_array($script) ? implode("\r\n", $script) . "\r\n" : $script);
	rewind($in);
	$out = fopen('php://memory', 'w+b');

	return array(pg_imap_session($in, $out), $out);
}

// What the client sent.
function imap_client_test_sent($out)
{
	rewind($out);

	return stream_get_contents($out);
}

function test_imap_client_tokenize()
{
	pg_assert_same(array('UID', '7', 'FLAGS', array('\\Seen', '\\Answered')), pg_imap_tokenize('UID 7 FLAGS (\\Seen \\Answered)'));
	pg_assert_same(array('a "quoted" \\ word', null, 'x'), pg_imap_tokenize('"a \\"quoted\\" \\\\ word" NIL x'), 'quoted with escapes, NIL');
	pg_assert_same(array(array('BODY[HEADER.FIELDS (MESSAGE-ID)]', "Message-ID: <a@b>\r\n\r\n")), pg_imap_tokenize("(BODY[HEADER.FIELDS (MESSAGE-ID)] {21}\r\nMessage-ID: <a@b>\r\n\r\n)"), 'section with spaces, literal');
	pg_assert_same(array(array('A', array('B', array('C')))), pg_imap_tokenize('(A (B (C)))'), 'nested lists');
	pg_assert_same(array('x', ''), pg_imap_tokenize("x {0}\r\n"), 'empty literal');
	pg_assert_same(array(array('UID', '1', 'BODY[]', 'abc')), pg_imap_tokenize("(UID 1 BODY[] {50}\r\nabc"), 'cut literal keeps what came, never raises');
	pg_assert_same(array(array('"unterminated')), pg_imap_tokenize('("\\"unterminated'), 'broken quote');
}

function test_imap_client_parse_untagged()
{
	pg_assert_same(array('type' => 'EXISTS', 'number' => 172, 'code' => null, 'text' => '', 'data' => null), pg_imap_parse_untagged("* 172 EXISTS\r\n"));

	$ok = pg_imap_parse_untagged("* OK [UIDVALIDITY 3857529045] UIDs valid\r\n");
	pg_assert_same('OK', $ok['type']);
	pg_assert_same(array('name' => 'UIDVALIDITY', 'args' => '3857529045'), $ok['code']);
	pg_assert_same('UIDs valid', $ok['text']);

	pg_assert_same(array(2, 84, 882), pg_imap_parse_untagged("* SEARCH 2 84 882\r\n")['data']);
	pg_assert_same(array(), pg_imap_parse_untagged("* SEARCH\r\n")['data'], 'nothing found');

	$body = "From: a@b\r\nSubject: (x)\r\n\r\nBody ) with \"quotes\" {3}\r\n";
	$fetch = pg_imap_parse_untagged("* 12 FETCH (UID 345 RFC822.SIZE " . strlen($body) . " FLAGS (\\Seen) BODY[]<0> {" . strlen($body) . "}\r\n" . $body . ")\r\n");
	pg_assert_same(12, $fetch['number']);
	pg_assert_same(345, $fetch['data']['UID']);
	pg_assert_same(strlen($body), $fetch['data']['RFC822.SIZE']);
	pg_assert_same(array('\\Seen'), $fetch['data']['FLAGS']);
	pg_assert_same($body, $fetch['data']['BODY[]'], 'literal kept byte for byte, origin mark dropped');
	pg_assert_same($body, pg_imap_fetch_section($fetch['data'], 'BODY['));
	pg_assert_same(null, pg_imap_fetch_section($fetch['data'], 'BODY[HEADER'));

	pg_assert_same(array('IMAP4REV1', 'AUTH=PLAIN', 'MOVE'), pg_imap_capability_list(' IMAP4rev1  AUTH=PLAIN move '));
}

function test_imap_client_parse_tagged()
{
	pg_assert_same(
		array('tag' => 'A0002', 'status' => 'NO', 'code' => array('name' => 'AUTHENTICATIONFAILED', 'args' => ''), 'text' => 'Invalid credentials (Failure)'),
		pg_imap_parse_tagged("A0002 NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n"));
	pg_assert_same('', pg_imap_parse_tagged("A0003 WHAT\r\n")['status'], 'unknown status');
	pg_assert_same('bad   text', pg_imap_parse_code("bad \x01 text")['text'], 'control characters become spaces');
	pg_assert_same(200, strlen(pg_imap_clean_text(str_repeat('a', 300))));
	pg_assert_true(strlen(pg_imap_clean_text(str_repeat('a', 199) . "\xC5\x9F")) === 199, 'no half character at the cut');
}

function test_imap_client_astring_and_utf7()
{
	pg_assert_same('"INBOX"', pg_imap_astring('INBOX'));
	pg_assert_same('"pa\\"ss\\\\w"', pg_imap_astring('pa"ss\\w'));
	pg_assert_same(array('literal' => "\xC5\x9Fifre"), pg_imap_astring("\xC5\x9Fifre"), '8-bit goes as a literal');
	pg_assert_same(array('literal' => "a\r\nb"), pg_imap_astring("a\r\nb"));

	pg_assert_same('INBOX', pg_imap_utf7_encode('INBOX'));
	pg_assert_same('~peter/mail/&U,BTFw-/&ZeVnLIqe-', pg_imap_utf7_encode("~peter/mail/\xE5\x8F\xB0\xE5\x8C\x97/\xE6\x97\xA5\xE6\x9C\xAC\xE8\xAA\x9E"), 'RFC 3501 example');
	pg_assert_same('&ATABXw-lendi', pg_imap_utf7_encode("\xC4\xB0\xC5\x9Flendi"));
	pg_assert_same('Gelen &- Giden &2D3eAA-', pg_imap_utf7_encode("Gelen & Giden \xF0\x9F\x98\x80"), 'ampersand and a surrogate pair');
	pg_assert_same('ab', pg_imap_utf7_encode("a\xFFb"), 'not UTF-8: the ASCII part');
}

function test_imap_client_sets_and_dates()
{
	pg_assert_same('1:3,5,7:8', pg_imap_uid_set(array(8, 1, 2, 3, 5, 7, 2, 0, -4)));
	pg_assert_same('', pg_imap_uid_set(array()));
	pg_assert_same('42', pg_imap_uid_set(array('42')));
	pg_assert_same('5-Oct-2026', pg_imap_search_date(gmmktime(23, 0, 0, 10, 5, 2026)));
	pg_assert_true(pg_imap_host_valid('imap.gmail.com'));
	pg_assert_true(pg_imap_host_valid('192.0.2.10'));
	pg_assert_false(pg_imap_host_valid('localhost'), 'a dotless name is refused');
	pg_assert_false(pg_imap_host_valid('imap.example.com:993'));
	pg_assert_false(pg_imap_host_valid('-bad.example.com'));

	pg_assert_same('timeout', pg_imap_connect_error(110, 'Unable to connect to ssl://192.0.2.1:993 (Connection timed out)'));
	pg_assert_same('tls', pg_imap_connect_error(0, 'SSL operation failed with code 1. OpenSSL Error messages: error:0A000086:SSL routines::certificate verify failed'));
	pg_assert_same('connect', pg_imap_connect_error(111, 'Unable to connect to ssl://192.0.2.1:993 (Connection refused)'));
}

function test_imap_client_session_read_flow()
{
	$mail = "Message-ID: <m1@example.com>\r\nSubject: Hi\r\n\r\nHello\r\n";
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN] ready',
		'A0001 OK [CAPABILITY IMAP4rev1 MOVE UIDPLUS] signed in',
		'* FLAGS (\\Answered \\Seen)',
		'* 3 EXISTS',
		'* OK [UIDVALIDITY 1700] UIDs valid',
		'* OK [UIDNEXT 45] next',
		'A0002 OK [READ-WRITE] SELECT done',
		'* SEARCH 43 44',
		'* SEARCH 42',
		'A0003 OK done',
		'* 2 FETCH (UID 43 RFC822.SIZE ' . strlen($mail) . ' BODY[] {' . strlen($mail) . "}\r\n" . $mail . ')',
		'* 2 FETCH (FLAGS (\\Seen))',
		'A0004 OK done',
		'A0005 OK stored',
		'A0006 OK moved',
		'* BYE logging out',
		'A0007 OK bye',
	));

	pg_assert_true(pg_imap_greeting($session), 'greeting');
	pg_assert_same(array('IMAP4REV1', 'SASL-IR', 'AUTH=PLAIN'), $session['capabilities']);
	pg_assert_true(pg_imap_login($session, 'support@example.com', "p\xC3\xA4ss"), 'login');
	pg_assert_true(in_array('MOVE', $session['capabilities'], true), 'capabilities from the tagged code');

	$folder = pg_imap_select($session, 'INBOX');
	pg_assert_same(array('ok' => true, 'exists' => 3, 'uidvalidity' => 1700, 'uidnext' => 45), $folder);
	pg_assert_same(array(42, 43, 44), pg_imap_uid_search($session, 'UID 42:*'));

	$fetched = pg_imap_uid_fetch($session, array(43), '(UID RFC822.SIZE BODY.PEEK[])');
	pg_assert_same(array(43), array_keys($fetched));
	pg_assert_same($mail, $fetched[43]['BODY[]']);

	pg_assert_true(pg_imap_uid_mark_seen($session, array(43)));
	pg_assert_true(pg_imap_uid_move($session, array(43, 44), "\xC4\xB0\xC5\x9Flendi"));
	pg_assert_true(pg_imap_command($session, array('LOGOUT'))['ok'], 'BYE before the tagged OK');
	pg_assert_same('', $session['error']);

	pg_assert_same(implode("\r\n", array(
		'A0001 AUTHENTICATE PLAIN ' . base64_encode("\0support@example.com\0p\xC3\xA4ss"),
		'A0002 SELECT "INBOX"',
		'A0003 UID SEARCH UID 42:*',
		'A0004 UID FETCH 43 (UID RFC822.SIZE BODY.PEEK[])',
		'A0005 UID STORE 43 +FLAGS.SILENT (\\Seen)',
		'A0006 UID MOVE 43:44 "&ATABXw-lendi"',
		'A0007 LOGOUT',
	)) . "\r\n", imap_client_test_sent($out));
}

function test_imap_client_login_without_plain()
{
	// No AUTH=PLAIN and no LITERAL+: LOGIN, the 8-bit password as a
	// synchronising literal after the server's go-ahead.
	list($session, $out) = imap_client_test_session(array(
		'* OK ready',
		'* CAPABILITY IMAP4rev1',
		'A0001 OK',
		'+ go ahead',
		'A0002 OK logged in',
		'* CAPABILITY IMAP4rev1 IDLE',
		'A0003 OK',
	));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_true(pg_imap_login($session, 'me', "\xC5\x9Fifre"));
	pg_assert_same(array('IMAP4REV1', 'IDLE'), $session['capabilities']);
	pg_assert_same("A0001 CAPABILITY\r\nA0002 LOGIN \"me\" {6}\r\n\xC5\x9Fifre\r\nA0003 CAPABILITY\r\n", imap_client_test_sent($out));
}

function test_imap_client_authenticate_without_sasl_ir()
{
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] ready',
		'+ ',
		'A0001 OK [CAPABILITY IMAP4rev1] done',
	));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_true(pg_imap_login($session, 'u', 'p'));
	pg_assert_same("A0001 AUTHENTICATE PLAIN\r\n" . base64_encode("\0u\0p") . "\r\n", imap_client_test_sent($out));
}

function test_imap_client_failures()
{
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN SASL-IR] ready',
		'A0001 NO [AUTHENTICATIONFAILED] Invalid credentials',
	));
	pg_assert_true(pg_imap_greeting($session));
	pg_assert_false(pg_imap_login($session, 'u', 'secret-pass'));
	pg_assert_same('auth', $session['error']);
	pg_assert_same('Invalid credentials', $session['detail']);
	pg_assert_false(strpos($session['detail'], 'secret') !== false, 'no password in the detail');

	list($session) = imap_client_test_session(array('* OK [CAPABILITY IMAP4rev1 LOGINDISABLED] ready'));
	pg_imap_greeting($session);
	pg_assert_false(pg_imap_login($session, 'u', 'p'));
	pg_assert_same('auth_disabled', $session['error']);

	list($session) = imap_client_test_session(array('* BYE too many connections'));
	pg_assert_false(pg_imap_greeting($session));
	pg_assert_same('refused', $session['error']);
	pg_assert_same('too many connections', $session['detail']);

	list($session) = imap_client_test_session(array('* PREAUTH welcome'));
	pg_assert_false(pg_imap_greeting($session), 'a session nobody signed into is refused');

	list($session) = imap_client_test_session(array('* OK ready', 'A0001 NO [NONEXISTENT] Unknown folder'));
	pg_imap_greeting($session);
	pg_assert_false(pg_imap_select($session, 'Archive')['ok']);
	pg_assert_same('folder', $session['error']);

	list($session) = imap_client_test_session("* OK ready\r\n* 1 FETCH (UID 5 BODY[] {99999999}\r\nabc");
	pg_imap_greeting($session);
	pg_assert_same(null, pg_imap_uid_fetch($session, array(5), '(UID BODY.PEEK[])'));
	pg_assert_same('too_large', $session['error']);

	list($session) = imap_client_test_session("* OK ready\r\n* 1 FETCH (UID 5 BODY[] {50}\r\nabc");
	pg_imap_greeting($session);
	pg_assert_same(null, pg_imap_uid_fetch($session, array(5), '(UID BODY.PEEK[])'));
	pg_assert_same('closed', $session['error'], 'the stream ended inside a literal');

	list($session) = imap_client_test_session(array('* OK ready', 'B0001 OK wrong tag'));
	pg_imap_greeting($session);
	pg_assert_false(pg_imap_command($session, array('NOOP'))['ok']);
	pg_assert_same('protocol', $session['error']);
	pg_assert_false(pg_imap_command($session, array('NOOP'))['ok'], 'a broken session answers at once');

	list($session) = imap_client_test_session(array('* OK ready'));
	$session['deadline'] = microtime(true) - 1;
	pg_assert_false(pg_imap_command($session, array('NOOP'))['ok']);
	pg_assert_same('timeout', $session['error'], 'the budget is spent');
}

function test_imap_client_move_fallback()
{
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 UIDPLUS] ready',
		'A0001 OK copied',
		'A0002 OK stored',
		'A0003 OK expunged',
	));
	pg_imap_greeting($session);
	pg_assert_true(pg_imap_uid_move($session, array(7), 'Done'));
	pg_assert_same("A0001 UID COPY 7 \"Done\"\r\nA0002 UID STORE 7 +FLAGS.SILENT (\\Seen \\Deleted)\r\nA0003 UID EXPUNGE 7\r\n", imap_client_test_sent($out));

	list($session, $out) = imap_client_test_session(array('* OK ready', 'A0001 OK copied', 'A0002 OK stored'));
	pg_imap_greeting($session);
	pg_assert_true(pg_imap_uid_move($session, array(7), 'Done'), 'without UIDPLUS nothing is expunged');
	pg_assert_same("A0001 UID COPY 7 \"Done\"\r\nA0002 UID STORE 7 +FLAGS.SILENT (\\Seen \\Deleted)\r\n", imap_client_test_sent($out));
}

function test_imap_client_logout_closes()
{
	list($session, $out) = imap_client_test_session(array('* OK ready', '* BYE', 'A0001 OK bye'));
	pg_imap_greeting($session);
	pg_imap_logout($session);
	pg_assert_false(is_resource($session['in']), 'input closed');
	pg_assert_false(is_resource($out), 'output closed');
}

function test_imap_client_sent_folder_by_its_mark()
{
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 SPECIAL-USE] ready',
		'* LIST (\\HasNoChildren \\Drafts) "/" "Drafts"',
		'* LIST (\\HasNoChildren \\Sent) "/" "Gesendete Objekte"',
		'A0001 OK listed',
	));
	pg_imap_greeting($session);
	pg_assert_same('Gesendete Objekte', pg_imap_sent_folder($session), 'the folder marked \\Sent');
	pg_assert_same("A0001 LIST (SPECIAL-USE) \"\" \"*\"\r\n", imap_client_test_sent($out), 'only the marked folders asked for');

	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 SPECIAL-USE] ready',
		'A0001 OK nothing marked',
		'* LIST (\\HasNoChildren) "." "INBOX"',
		'* LIST (\\Noselect) "." "Sent"',
		'* LIST (\\HasNoChildren) "." "INBOX.Sent"',
		'A0002 OK listed',
	));
	pg_imap_greeting($session);
	pg_assert_same('INBOX.Sent', pg_imap_sent_folder($session), 'a usual name when nothing is marked; a \\Noselect folder is skipped');

	list($session) = imap_client_test_session(array('* OK ready', 'A0001 NO not allowed'));
	pg_imap_greeting($session);
	pg_assert_same('', pg_imap_sent_folder($session), 'a refused LIST finds none');

	pg_assert_same('Sent Items', pg_imap_pick_sent_folder(array(
		array('name' => 'Archive', 'attributes' => array()),
		array('name' => 'Sent Items', 'attributes' => array()),
	)), 'a usual name, case ignored');
	pg_assert_same('', pg_imap_pick_sent_folder(array(array('name' => 'Outbox', 'attributes' => array()))), 'no sent folder');
}

function test_imap_client_append()
{
	list($session, $out) = imap_client_test_session(array(
		'* OK ready',
		'+ go ahead',
		'A0001 OK [APPENDUID 1 9] appended',
	));
	pg_imap_greeting($session);
	pg_assert_true(pg_imap_append($session, 'Gönderilmiş', "Subject: Hi\nMessage-ID: <a@b>\n\nHello\n"));
	$mime = "Subject: Hi\r\nMessage-ID: <a@b>\r\n\r\nHello\r\n";
	pg_assert_same("A0001 APPEND \"G&APY-nderilmi&AV8-\" (\\Seen) {" . strlen($mime) . "}\r\n" . $mime . "\r\n", imap_client_test_sent($out), 'CRLF line ends, the folder in modified UTF-7');

	list($session, $out) = imap_client_test_session(array('* OK [CAPABILITY IMAP4rev1 LITERAL+] ready', 'A0001 OK appended'));
	pg_imap_greeting($session);
	pg_assert_true(pg_imap_append($session, '&AV8-Sent', "A: b\r\n\r\nx", '(\\Seen)', true), 'a name from LIST goes as it is');
	pg_assert_same("A0001 APPEND \"&AV8-Sent\" (\\Seen) {9+}\r\nA: b\r\n\r\nx\r\n", imap_client_test_sent($out));

	list($session) = imap_client_test_session(array('* OK ready', '+ go ahead', 'A0001 NO [TRYCREATE] no such folder'));
	pg_imap_greeting($session);
	pg_assert_false(pg_imap_append($session, 'Missing', "A: b\r\n\r\nx"));
	pg_assert_same('folder', $session['error'], 'a refused APPEND is a folder error');

	list($session) = imap_client_test_session(array('* OK ready'));
	pg_imap_greeting($session);
	pg_assert_false(pg_imap_append($session, 'Sent', ''), 'nothing to append');
}

function test_imap_client_xoauth2()
{
	$response = pg_imap_xoauth2_response('box@contoso.com', 'tok');
	pg_assert_same("user=box@contoso.com\x01auth=Bearer tok\x01\x01", base64_decode($response));

	// The token in the command line where the server allows it.
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 AUTH=XOAUTH2 SASL-IR] ready',
		'A0001 OK [CAPABILITY IMAP4rev1 MOVE] done',
	));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_true(pg_imap_login_xoauth2($session, 'box@contoso.com', 'tok'));
	pg_assert_same("A0001 AUTHENTICATE XOAUTH2 " . $response . "\r\n", imap_client_test_sent($out));

	// After the go-ahead otherwise.
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 AUTH=XOAUTH2] ready',
		'+ ',
		'A0001 OK done',
		'* CAPABILITY IMAP4rev1',
		'A0002 OK',
	));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_true(pg_imap_login_xoauth2($session, 'box@contoso.com', 'tok'));
	pg_assert_same("A0001 AUTHENTICATE XOAUTH2\r\n" . $response . "\r\nA0002 CAPABILITY\r\n", imap_client_test_sent($out));
}

function test_imap_client_xoauth2_refused()
{
	// A refused token: the reason as a challenge, an empty line, then NO;
	// the token never reaches the detail.
	list($session, $out) = imap_client_test_session(array(
		'* OK [CAPABILITY IMAP4rev1 AUTH=XOAUTH2 SASL-IR] ready',
		'+ eyJzdGF0dXMiOiI0MDAifQ==',
		'A0001 NO AUTHENTICATE failed.',
	));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_false(pg_imap_login_xoauth2($session, 'box@contoso.com', 'secret-token'));
	pg_assert_same('auth', $session['error']);
	pg_assert_true(strpos($session['detail'], 'secret-token') === false);
	pg_assert_same("A0001 AUTHENTICATE XOAUTH2 " . pg_imap_xoauth2_response('box@contoso.com', 'secret-token') . "\r\n\r\n", imap_client_test_sent($out));

	// No XOAUTH2 offered: nothing is sent.
	list($session, $out) = imap_client_test_session(array('* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] ready'));

	pg_assert_true(pg_imap_greeting($session));
	pg_assert_false(pg_imap_login_xoauth2($session, 'box@contoso.com', 'tok'));
	pg_assert_same('auth_disabled', $session['error']);
	pg_assert_same('', imap_client_test_sent($out));
}
