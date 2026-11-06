<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the VERP return path and the bounce report reader: the signed
 * per-message token (includes/fn/mail_bounces.php), pg_mail_parse_dsn()
 * (includes/fn/mail_parse.php) on Gmail, Outlook, Yahoo, Postfix, qmail and
 * ARF reports (tests/fixtures/mail_dsn/), and the match of a report to the
 * address its token signs. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

const MAIL_VERP_TEST_KEY = 'test-signing-key';

// A fixture with {TOKEN} replaced by the token of this ref and address.
function mail_verp_test_fixture($name, $ref, $address)
{
	$raw = (string) file_get_contents(dirname(__FILE__) . '/fixtures/mail_dsn/' . $name . '.eml');

	return str_replace('{TOKEN}', pg_mail_bounce_token($ref, $address, MAIL_VERP_TEST_KEY), $raw);
}

function test_mail_verp_ref_round_trip()
{
	pg_assert_same('q1b', pg_mail_bounce_ref('queue', 47));
	pg_assert_same('c2s', pg_mail_bounce_ref('campaign', 100));
	pg_assert_same('m', pg_mail_bounce_ref('queue', 0));
	pg_assert_same('m', pg_mail_bounce_ref('other', 5));

	pg_assert_same(array('type' => 'queue', 'id' => 47), pg_mail_bounce_ref_parts('q1b'));
	pg_assert_same(array('type' => 'campaign', 'id' => 100), pg_mail_bounce_ref_parts('c2s'));
	pg_assert_same(array('type' => 'mail', 'id' => 0), pg_mail_bounce_ref_parts('m'));
	pg_assert_same(null, pg_mail_bounce_ref_parts('x1'));
	pg_assert_same(null, pg_mail_bounce_ref_parts(''));
}

// The token signs the reference and the address, case-blind on the address.
function test_mail_verp_token_signs_ref_and_address()
{
	$token = pg_mail_bounce_token('q1b', 'Ann@Example.com', MAIL_VERP_TEST_KEY);

	pg_assert_same(1, preg_match('/^q1b\.[0-9a-f]{16}$/', $token));
	pg_assert_true(pg_mail_bounce_token_valid($token, 'ann@example.com', MAIL_VERP_TEST_KEY));
	pg_assert_true(pg_mail_bounce_token_valid(strtoupper($token), 'ANN@example.com', MAIL_VERP_TEST_KEY), 'mail servers may change case');
	pg_assert_false(pg_mail_bounce_token_valid($token, 'bob@example.com', MAIL_VERP_TEST_KEY), 'another address');
	pg_assert_false(pg_mail_bounce_token_valid($token, 'ann@example.com', 'other-key'), 'another site');
	pg_assert_false(pg_mail_bounce_token_valid('q1c' . substr($token, 3), 'ann@example.com', MAIL_VERP_TEST_KEY), 'another message');
	pg_assert_false(pg_mail_bounce_token_valid('garbage', 'ann@example.com', MAIL_VERP_TEST_KEY));

	pg_assert_same('', pg_mail_bounce_token('q1b', 'ann@example.com', ''), 'no key, no token');
	pg_assert_same('', pg_mail_bounce_token('q1b', 'not an address', MAIL_VERP_TEST_KEY));
	pg_assert_same('', pg_mail_bounce_token('zz', 'ann@example.com', MAIL_VERP_TEST_KEY));
}

function test_mail_verp_return_path()
{
	$token = pg_mail_bounce_token('c2s', 'ann@example.org', MAIL_VERP_TEST_KEY);

	pg_assert_same('bounces+' . $token . '@example.com', pg_mail_bounce_return_path('Bounces@Example.com', $token));
	pg_assert_same('', pg_mail_bounce_return_path('', $token));
	pg_assert_same('', pg_mail_bounce_return_path('bounces@example.com', ''));
	pg_assert_same('', pg_mail_bounce_return_path(str_repeat('a', 50) . '@example.com', $token), 'local part over 64');

	pg_assert_same(array($token), pg_mail_bounce_tokens_in('To: bounces+' . $token . '@example.com, bounces+' . $token . '@example.com'));
	pg_assert_same(array(), pg_mail_bounce_tokens_in('ann+tag@example.com'));
}

// The return path replaces the sender only on the sender's own domain.
function test_mail_verp_envelope_same_domain_only()
{
	pg_assert_same('bounces+t@example.com', pg_mail_bounce_envelope('shop@example.com', 'bounces+t@example.com'));
	pg_assert_same('shop@example.com', pg_mail_bounce_envelope('shop@example.com', 'bounces+t@other.com'));
	pg_assert_same('shop@example.com', pg_mail_bounce_envelope('shop@example.com', ''));
	pg_assert_same('', pg_mail_bounce_envelope('', 'bounces+t@example.com'));
}

function test_mail_verp_gmail_hard_bounce()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('gmail', 'q1b', 'missing.person@gmail.com'));

	pg_assert_same('hard', $dsn['kind']);
	pg_assert_same('missing.person@gmail.com', $dsn['recipient']);
	pg_assert_same('5.1.1', $dsn['status']);
	pg_assert_same('a1b2c3@example.com', $dsn['original_id']);
	pg_assert_same(1, count($dsn['tokens']));

	$match = pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY);
	pg_assert_same('missing.person@gmail.com', $match['address']);
	pg_assert_same(array('type' => 'queue', 'id' => 47), $match['ref']);
}

function test_mail_verp_outlook_hard_bounce_with_headers_only()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('outlook', 'c2s', 'gone.reader@outlook.com'));

	pg_assert_same('hard', $dsn['kind']);
	pg_assert_same('gone.reader@outlook.com', $dsn['recipient']);
	pg_assert_same('out123@example.com', $dsn['original_id']);

	$match = pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY);
	pg_assert_same(array('type' => 'campaign', 'id' => 100), $match['ref']);
}

function test_mail_verp_yahoo_soft_bounce()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('yahoo', 'm', 'full.box@yahoo.com'));

	pg_assert_same('soft', $dsn['kind']);
	pg_assert_same('4.2.2', $dsn['status']);
	pg_assert_same('full.box@yahoo.com', pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY)['address']);
}

function test_mail_verp_postfix_hard_bounce()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('postfix', 'q1', 'nobody@nowhere.example.org'));

	pg_assert_same('hard', $dsn['kind']);
	pg_assert_same('nobody@nowhere.example.org', $dsn['recipient']);
	pg_assert_contains('User unknown', $dsn['diagnostic']);
	pg_assert_same('nobody@nowhere.example.org', pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY)['address']);
}

// A delay warning is no bounce.
function test_mail_verp_delayed_is_not_a_bounce()
{
	pg_assert_same(null, pg_mail_parse_dsn(mail_verp_test_fixture('postfix_delayed', 'q1', 'slow@slow.example.net')));
}

// A plain-text notice names no recipient field: the token picks the
// address among those written in it.
function test_mail_verp_qmail_plain_text()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('qmail_plain', 'q2', 'old.user@oldhost.example.net'));

	pg_assert_same('hard', $dsn['kind']);
	pg_assert_same('5.1.1', $dsn['status']);
	pg_assert_true(in_array('old.user@oldhost.example.net', $dsn['candidates'], true));
	pg_assert_same('old.user@oldhost.example.net', pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY)['address']);
}

function test_mail_verp_arf_complaint()
{
	$dsn = pg_mail_parse_dsn(mail_verp_test_fixture('arf_complaint', 'c2s', 'angry.reader@hotmail.com'));

	pg_assert_same('complaint', $dsn['kind']);
	pg_assert_same('angry.reader@hotmail.com', $dsn['recipient']);
	pg_assert_same('angry.reader@hotmail.com', pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY)['address']);

	$not_spam = str_replace('Feedback-Type: abuse', 'Feedback-Type: not-spam', mail_verp_test_fixture('arf_complaint', 'c2s', 'angry.reader@hotmail.com'));
	pg_assert_same(null, pg_mail_parse_dsn($not_spam));
}

// A report whose token signs another address, or one with no token, is not
// the site's to record: anyone can send a mailbox a report.
function test_mail_verp_unsigned_reports_do_not_match()
{
	$forged = pg_mail_parse_dsn(mail_verp_test_fixture('gmail', 'q1b', 'someone.else@gmail.com'));
	pg_assert_same(null, pg_mail_bounce_dsn_match($forged, MAIL_VERP_TEST_KEY));

	$no_token = pg_mail_parse_dsn(str_replace('+{TOKEN}', '', (string) file_get_contents(dirname(__FILE__) . '/fixtures/mail_dsn/gmail.eml')));
	pg_assert_same(array(), $no_token['tokens']);
	pg_assert_same(null, pg_mail_bounce_dsn_match($no_token, MAIL_VERP_TEST_KEY));
	pg_assert_same(null, pg_mail_bounce_dsn_match(null, MAIL_VERP_TEST_KEY));
}

// A message with a bcc copy leaves with the return path signed for its one
// "to". When the copy bounces, the report names the bcc address as the failed
// recipient while the returned headers still say To: the customer, whom the
// token signs. The report is about the copy, so the customer is not matched.
function test_mail_verp_bcc_copy_bounce_does_not_match_the_to()
{
	$raw = str_replace(
		"To: nobody@nowhere.example.org\n",
		"To: buyer@example.net\n",
		mail_verp_test_fixture('postfix', 'q1', 'buyer@example.net'));

	$dsn = pg_mail_parse_dsn($raw);

	pg_assert_same('nobody@nowhere.example.org', $dsn['recipient']);
	pg_assert_true(in_array('buyer@example.net', $dsn['candidates'], true), 'the returned To is read');
	pg_assert_same(null, pg_mail_bounce_dsn_match($dsn, MAIL_VERP_TEST_KEY));

	// The same report signed for the failed recipient still matches it.
	$own = pg_mail_parse_dsn(mail_verp_test_fixture('postfix', 'q1', 'nobody@nowhere.example.org'));
	pg_assert_same('nobody@nowhere.example.org', pg_mail_bounce_dsn_match($own, MAIL_VERP_TEST_KEY)['address']);
}

// An ordinary mail is no report.
function test_mail_verp_ordinary_mail_is_not_a_bounce()
{
	$raw = "From: ann@example.org\nTo: support@example.com\nSubject: Question\n\nWhere is my parcel?\n";

	pg_assert_same(null, pg_mail_parse_dsn($raw));
}

function test_mail_verp_kind_words()
{
	pg_assert_same('soft', pg_mail_dsn_kind('', '452 mailbox full'));
	pg_assert_same('hard', pg_mail_dsn_kind('', '550 no such user'));
	pg_assert_same('soft', pg_mail_dsn_kind('', 'Temporary failure, try again later'));
	pg_assert_same('hard', pg_mail_dsn_kind('', 'no mailbox here by that name'));
	pg_assert_same('soft', pg_mail_dsn_kind('4.4.7', ''));
}

// The wiring: email() puts the return path only for a message with a
// bounce_ref and not through a connected account; the queue and campaigns
// carry one; a box that reads bounces records and files the report.
function test_mail_verp_wiring()
{
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	pg_assert_contains("if (!\$connection && !empty(\$properties['bounce_ref'])", $mail);

	$queue = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue.php');
	pg_assert_contains("\$properties['bounce_ref'] = pg_mail_bounce_ref('queue', \$id);", $queue);

	$properties = pg_email_campaign_mail_properties(array('id' => 100, 'purpose' => 'commercial'), 'a@example.com', 'A', 'S', 'B');
	pg_assert_same('c2s', $properties['bounce_ref']);
	pg_assert_false(isset(pg_email_campaign_mail_properties(array('purpose' => 'commercial'), 'a@example.com', 'A', 'S', 'B')['bounce_ref']));

	$fetch = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');
	pg_assert_true(strpos($fetch, 'ws_mail_fetch_records_bounce($box, $raw)') < strpos($fetch, '$ingested = ws_mail_ingest($raw, $box);'), 'recorded before the channel');

	$settings = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("'reads_bounces' => !empty(\$input['reads_bounces']) ? 1 : 0", $settings);
	pg_assert_contains("'connection_id', 'reads_bounces') as \$key", $settings);
}
