<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/privacy.php: the e-mail form the person token is made
 * from, the token itself, and the consent log's row, person condition and
 * current answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Trimmed and lower-cased; anything that is not one address is empty.
function test_privacy_normalize_email()
{
	pg_assert_same('ali.veli@example.com', pg_privacy_normalize_email("  Ali.Veli@Example.COM \n"), 'trim and case');

	foreach (array('', '   ', 'no-at-sign', '@example.com', 'ali@', 'a@b@c.com', 'ali veli@example.com', 'a<b@c.com', 'a@b.com, c@d.com', null, 5, array('a@b.com')) as $bad) {
		pg_assert_same('', pg_privacy_normalize_email($bad), var_export($bad, true));
	}

	pg_assert_same('', pg_privacy_normalize_email(str_repeat('a', 250) . '@b.com'), 'longer than an address may be');
}

// A keyed hash: the same for one address however it is typed, different per key, never the plain hash.
function test_privacy_person_token_is_keyed_and_stable()
{
	$token = pg_privacy_person_token('ali@example.com', 'k1');

	pg_assert_same(hash_hmac('sha256', 'person:ali@example.com', 'k1'), $token, 'HMAC of the prefixed address');
	pg_assert_true(pg_privacy_is_person_token($token), 'token shape');
	pg_assert_same($token, pg_privacy_person_token(' ALI@example.com ', 'k1'), 'same person, other spelling');
	pg_assert_false($token === pg_privacy_person_token('ali@example.com', 'k2'), 'another key, another token');
	pg_assert_false($token === hash('sha256', 'ali@example.com'), 'not the plain hash');
}

// No token without an address or without a key (ENCRYPTION_KEY is not defined in the runner).
function test_privacy_person_token_needs_address_and_key()
{
	pg_assert_same('', pg_privacy_person_token('not an address', 'k1'), 'bad address');
	pg_assert_same('', pg_privacy_person_token('ali@example.com', ''), 'empty key');
	pg_assert_false(defined('ENCRYPTION_KEY'), 'no site key in the test runner');
	pg_assert_same('', pg_privacy_person_token('ali@example.com'), 'no site key');

	foreach (array('', str_repeat('A', 64), str_repeat('a', 63), str_repeat('g', 64), null, 12) as $bad) {
		pg_assert_false(pg_privacy_is_person_token($bad), var_export($bad, true));
	}
}

// The cookie purposes are the notice's categories; contract covers every checkout document and the KVKK consent.
function test_consent_log_purposes_follow_their_writers()
{
	$purposes = pg_consent_log_purposes();

	pg_assert_same(array('cookie', 'email', 'sms', 'call', 'contract'), array_keys($purposes), 'channels');
	pg_assert_same(array_keys(pg_consent_category_letters()), $purposes['cookie'], 'cookie notice categories');

	foreach (array_keys(pg_legal_document_types()) as $type) {
		pg_assert_true(in_array($type, $purposes['contract'], true), 'legal document ' . $type);
	}

	// The order_documents.type ENUM of step 11.0.1.
	pg_assert_same(array('preinfo', 'contract', 'kvkk'), $purposes['contract'], 'order_documents types');
	pg_assert_same(array('granted', 'refused', 'pending'), pg_consent_log_statuses(), 'statuses');
	pg_assert_contains('form', pg_consent_log_sources(), 'forms write consents');
}

// An e-mail consent is for marketing or for one contact group, named by its id.
function test_consent_log_purpose_valid_takes_contact_groups()
{
	pg_assert_true(pg_consent_log_purpose_valid('email', 'marketing'), 'marketing');
	pg_assert_true(pg_consent_log_purpose_valid('email', 'group:12'), 'a contact group');
	pg_assert_true(pg_consent_log_purpose_valid('email', 'group:4294967295'), 'the highest group id');

	foreach (array('group:0', 'group:x', 'group:', 'group:012', 'group:-1', 'group:12 ', 'group:12345678901', 'Group:12', 'group:1.5') as $bad) {
		pg_assert_false(pg_consent_log_purpose_valid('email', $bad), $bad);
	}

	pg_assert_false(pg_consent_log_purpose_valid('sms', 'group:12'), 'a group is an e-mail purpose only');
	pg_assert_false(pg_consent_log_purpose_valid('cookie', 'group:12'), 'not a cookie category');
	pg_assert_false(pg_consent_log_purpose_valid('email', array('group:12')), 'an array');
	pg_assert_false(pg_consent_log_purpose_valid('fax', 'marketing'), 'unknown channel');

	$row = pg_consent_log_row(array('channel' => 'email', 'purpose' => 'group:7', 'status' => 'pending', 'source' => 'form', 'contact_id' => 3), 1);

	pg_assert_same('group:7', $row['purpose'], 'a pending group row');
	pg_assert_same('pending', $row['status'], 'pending status');
	pg_assert_same('form', $row['source'], 'form source');
	pg_assert_same('purpose', pg_consent_log_row(array('purpose' => 'group:0') + $row), 'group 0');
}

// A valid event becomes the row in column order.
function test_consent_log_row_from_event()
{
	$row = pg_consent_log_row(array(
		'channel'    => 'email',
		'purpose'    => 'marketing',
		'status'     => 'granted',
		'source'     => 'signup',
		'person_token' => str_repeat('ab', 32),
		'user_id'    => '7',
		'contact_id' => 9,
		'ip'         => '2001:db8::1',
		'user_agent' => 'Mozilla/5.0',
		'created_at' => 1760000000,
	), 1);

	pg_assert_same(array(
		'created_at'   => 1760000000,
		'person_token' => str_repeat('ab', 32),
		'user_id'      => 7,
		'contact_id'   => 9,
		'channel'      => 'email',
		'purpose'      => 'marketing',
		'status'       => 'granted',
		'source'       => 'signup',
		'ip'           => '2001:db8::1',
		'user_agent'   => 'Mozilla/5.0',
	), $row);
}

// Unknown labels are refused, each with its own code.
function test_consent_log_row_refuses_unknown_labels()
{
	$valid = array('channel' => 'sms', 'purpose' => 'marketing', 'status' => 'refused', 'source' => 'unsubscribe', 'contact_id' => 3);

	pg_assert_true(is_array(pg_consent_log_row($valid)), 'valid');
	pg_assert_same('channel', pg_consent_log_row(array('channel' => 'fax') + $valid), 'channel');
	pg_assert_same('purpose', pg_consent_log_row(array('purpose' => 'analytics') + $valid), 'purpose of another channel');
	pg_assert_same('status', pg_consent_log_row(array('status' => 'withdrawn') + $valid), 'status');
	pg_assert_same('source', pg_consent_log_row(array('source' => 'somewhere') + $valid), 'source');
	pg_assert_same('channel', pg_consent_log_row(array()), 'empty event');
}

// Every channel but cookie needs a person; a cookie answer may be anonymous.
function test_consent_log_row_needs_a_person_except_for_cookies()
{
	$event = array('channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'checkout');

	pg_assert_same('person', pg_consent_log_row($event), 'nobody');
	pg_assert_same('person', pg_consent_log_row($event + array('user_id' => -4, 'email' => 'no address')), 'no usable identifier');
	pg_assert_same(4, pg_consent_log_row($event + array('user_id' => 4))['user_id'], 'by user');

	$cookie = pg_consent_log_row(array('channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'refused', 'source' => 'banner'), 5);

	pg_assert_same('', $cookie['person_token'], 'anonymous visitor');
	pg_assert_same(0, $cookie['user_id'], 'no user');
	pg_assert_same(5, $cookie['created_at'], 'stamped now');
}

// A token that is not one falls back to the e-mail's (none here without a key); the ip must be one.
function test_consent_log_row_cleans_person_and_request_fields()
{
	$row = pg_consent_log_row(array(
		'channel'      => 'contract',
		'purpose'      => 'kvkk',
		'status'       => 'granted',
		'source'       => 'checkout',
		'person_token' => 'not-a-token',
		'contact_id'   => 2,
		'ip'           => '10.0.0.1, 203.0.113.9',
		'user_agent'   => "Agent\r\nInjected: header\x00" . str_repeat('ğ', 300),
	));

	pg_assert_same('', $row['person_token'], 'bad token dropped');
	pg_assert_same('', $row['ip'], 'a list is not an address');
	pg_assert_same(255, mb_strlen($row['user_agent'], 'UTF-8'), 'cut to the column in characters');
	pg_assert_same(0, strpos($row['user_agent'], 'Agent Injected: header '), 'control characters become spaces');
	pg_assert_true(time() - $row['created_at'] < 5, 'stamped with the time');
}

// The condition takes the token, the user and the contact; a person with none gets no condition.
function test_consent_log_person_where()
{
	$token = str_repeat('0f', 32);

	pg_assert_same("(person_token = '" . $token . "' OR user_id = '5' OR contact_id = '9')", pg_consent_log_person_where(array('person_token' => $token, 'user_id' => 5, 'contact_id' => '9')), 'all three');
	pg_assert_same("(contact_id = '12')", pg_consent_log_person_where(array('person_token' => "x' OR '1'='1", 'user_id' => "0 OR 1=1", 'contact_id' => '12abc')), 'only what is an identifier');
	pg_assert_same('', pg_consent_log_person_where(array('email' => 'ali@example.com')), 'no token without a key');
	pg_assert_same('', pg_consent_log_person_where(array()), 'nobody');
}

// The newest row of each channel and purpose wins, by time and then id, whatever the order.
function test_consent_log_current_keeps_newest_answer()
{
	$rows = array(
		array('id' => 3, 'created_at' => 200, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused'),
		array('id' => 1, 'created_at' => 100, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted'),
		array('id' => 5, 'created_at' => 300, 'channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'granted'),
		array('id' => 4, 'created_at' => 300, 'channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'refused'),
		array('id' => 6, 'created_at' => 50, 'channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted'),
		'not a row',
		array('id' => 7, 'status' => 'granted'),
	);

	$current = pg_consent_log_current($rows);

	pg_assert_same(array('cookie:analytics', 'email:marketing', 'sms:marketing'), array_keys($current), 'one per channel and purpose');
	pg_assert_same('refused', $current['email:marketing']['status'], 'withdrawn later');
	pg_assert_same(5, $current['cookie:analytics']['id'], 'same second: higher id');
	pg_assert_same(array(), pg_consent_log_current(array()), 'no rows');
}

// Without the table (no connection here) nothing is written and nothing is thrown.
function test_consent_log_record_without_table()
{
	pg_assert_false(pg_consent_log_ready(), 'not ready without a database');
	pg_assert_same(0, pg_consent_log_record(array('channel' => 'cookie', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'banner')), 'nothing written');
	pg_assert_same(array(), pg_consent_log_rows(array('user_id' => 1)), 'nothing read');
}
