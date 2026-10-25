<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/privacy_export.php: the sections of a personal data
 * pack, how a person is put together from the records found for them, who
 * may export whom, how rows are cleaned, the pack and its JSON, the file
 * name, the audit line and the PDF summary's HTML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The sections and their order are what the JSON file promises its reader; a
// change here is a change of the file's format.
function test_privacy_export_sections_are_pinned()
{
	pg_assert_same(
		array(
			'user', 'sessions', 'two_step', 'identities', 'passkeys', 'push_subscriptions',
			'contact', 'contact_groups', 'address_book',
			'orders', 'order_items', 'ship_tos', 'order_documents', 'returns', 'refunds', 'gift_cards',
			'form_submissions', 'signatures', 'comments', 'watchers', 'wishlist', 'stock_notify', 'campaigns', 'consents',
			'mail', 'sms', 'chats', 'erp_accounts', 'erp_documents', 'workspace',
		),
		array_keys(pg_privacy_export_sections()),
		'section keys'
	);

	foreach (pg_privacy_export_sections() as $key => $label) {
		pg_assert_true(is_string($label) && ($label !== ''), 'label of ' . $key);
	}
}

// A passkey is exported by what the person sees of it; the credential (its
// id and hash, public key, user handle, counter, algorithm) is never read.
function test_privacy_export_passkeys_leave_the_credential_out()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_export.php');

	pg_assert_same(1, preg_match_all('/SELECT ([^"]*?) FROM user_passkeys/', $source, $matches), 'one read of user_passkeys');
	pg_assert_same(
		'id, user_id, name, aaguid, transports, backup_eligible, backup_state, created_at, last_used_at',
		isset($matches[1][0]) ? $matches[1][0] : '',
		'columns'
	);
}

// The person as asked for: one address in its normal form, positive ids only.
function test_privacy_export_seed()
{
	pg_assert_same(
		array('emails' => array('ali@example.com'), 'user_ids' => array(7), 'contact_ids' => array(12)),
		pg_privacy_export_seed(array('email' => ' Ali@Example.com ', 'user_id' => '7', 'contact_id' => 12)),
		'all three'
	);

	pg_assert_same(
		array('emails' => array(), 'user_ids' => array(), 'contact_ids' => array()),
		pg_privacy_export_seed(array('email' => 'not an address', 'user_id' => '-3', 'contact_id' => array(5))),
		'nothing usable'
	);

	pg_assert_true(pg_privacy_export_is_empty(pg_privacy_export_seed(array())), 'empty input');
	pg_assert_false(pg_privacy_export_is_empty(pg_privacy_export_seed(array('contact_id' => 1))), 'a contact is enough');
}

// Users bring their contact and address, contacts their address; ids sorted,
// addresses in the order met, duplicates once.
function test_privacy_export_merge_follows_the_ties()
{
	$person = pg_privacy_export_merge(
		pg_privacy_export_seed(array('email' => 'ali@example.com')),
		array(
			array('user_id' => '9', 'user_email' => 'ALI@example.com', 'user_contact' => '30'),
			array('user_id' => '4', 'user_email' => 'ali.work@example.com', 'user_contact' => '0'),
		),
		array(
			array('id' => '30', 'email_address' => 'ali.home@example.com'),
			array('id' => '11', 'email_address' => ''),
		)
	);

	pg_assert_same(array('ali@example.com', 'ali.work@example.com', 'ali.home@example.com'), $person['emails'], 'addresses');
	pg_assert_same(array(4, 9), $person['user_ids'], 'users');
	pg_assert_same(array(11, 30), $person['contact_ids'], 'contacts, a user without one adds none');
}

// The sessions screen's rule: an administrator exports anyone; others
// themselves and strictly lower roles only.
function test_privacy_export_allowed_by_role()
{
	$admin = array('user_id' => 1, 'user_role' => 0);
	$designer = array('user_id' => 2, 'user_role' => 1);
	$manager = array('user_id' => 3, 'user_role' => 2);
	$customer = array('user_id' => 4, 'user_role' => 3);

	pg_assert_true(pg_privacy_export_allowed(0, 1, array($admin, $designer, $manager, $customer)), 'administrator');
	pg_assert_true(pg_privacy_export_allowed(2, 3, array($customer)), 'manager, a customer');
	pg_assert_true(pg_privacy_export_allowed(2, 3, array($manager)), 'manager, themselves');
	pg_assert_true(pg_privacy_export_allowed(2, 3, array()), 'manager, a person without an account');
	pg_assert_false(pg_privacy_export_allowed(2, 3, array(array('user_id' => 8, 'user_role' => 2))), 'manager, another manager');
	pg_assert_false(pg_privacy_export_allowed(2, 3, array($customer, $designer)), 'manager, a designer among them');
	pg_assert_true(pg_privacy_export_allowed(1, 2, array($manager)), 'designer, a manager');
	pg_assert_false(pg_privacy_export_allowed(1, 2, array($admin)), 'designer, an administrator');
}

// Secrets by name, extra masks and drops, IP numbers as addresses, at any depth.
function test_privacy_export_clean_rows()
{
	$row = pg_privacy_export_clean(
		array(
			'user_username'            => 'ali',
			'user_password'            => '$2y$10$hash',
			'token'                    => '',
			'card_number'              => '4111',
			'card_verification_number' => null,
			'totp_secret'              => 'JBSW',
			'validator_hash'           => 'abc',
			'endpoint'                 => 'https://push.example/x',
			'strokes'                  => '[[1,2]]',
			'ip_address'               => '3232235777',
			'other_ip'                 => '3232235777',
			'fields'                   => array(
				array('name' => 'password', 'data' => 'kept as an answer', 'secret' => 'x'),
			),
		),
		array('endpoint'),
		array('strokes')
	);

	pg_assert_same('ali', $row['user_username'], 'plain column');
	pg_assert_same('***', $row['user_password'], 'password');
	pg_assert_same('', $row['token'], 'empty secret stays empty');
	pg_assert_same('***', $row['card_number'], 'card number');
	pg_assert_same(null, $row['card_verification_number'], 'null secret stays null');
	pg_assert_same('***', $row['totp_secret'], 'two-step secret');
	pg_assert_same('***', $row['validator_hash'], 'hash');
	pg_assert_same('***', $row['endpoint'], 'masked by the section');
	pg_assert_false(array_key_exists('strokes', $row), 'dropped by the section');
	pg_assert_same('192.168.1.1', $row['ip_address'], 'INET_ATON number');
	pg_assert_same('3232235777', $row['other_ip'], 'only ip_address is a stored number');
	pg_assert_same('kept as an answer', $row['fields'][0]['data'], 'a nested answer');
	pg_assert_same('***', $row['fields'][0]['secret'], 'a nested secret');

	pg_assert_same('', pg_privacy_export_clean(array('ip_address' => '0'))['ip_address'], 'no address');
	pg_assert_same('::1', pg_privacy_export_clean(array('ip_address' => '::1'))['ip_address'], 'text address kept');
	pg_assert_same('…7Q2C', pg_privacy_export_code_hint('ABCD7Q2C'), 'code hint');
	pg_assert_same('…', pg_privacy_export_code_hint('AB'), 'short code shows nothing');
	pg_assert_same('', pg_privacy_export_code_hint(''), 'no code');
}

// Every section is written, empty ones too, and rows are cleaned again even
// when a collector forgot.
function test_privacy_export_document_shape()
{
	$document = pg_privacy_export_document(
		array('emails' => array('ali@example.com'), 'user_ids' => array('9', 9), 'contact_ids' => array(30)),
		array(
			'user'   => array('rows' => array(array('user_id' => '9', 'user_password' => 'x')), 'truncated' => false),
			'orders' => array('rows' => array(array('id' => '1'), array('id' => '2')), 'truncated' => true),
			'nope'   => array('rows' => array(array('id' => '1'))),
		),
		array('time' => 1760961600, 'site' => 'example.com', 'prepared_by' => 'admin')
	);

	pg_assert_same('pinegrap-personal-data', $document['format'], 'format');
	pg_assert_same(1, $document['version'], 'version');
	pg_assert_same('2025-10-20T12:00:00Z', $document['generated_at'], 'time in UTC');
	pg_assert_same(array(9), $document['person']['user_ids'], 'ids normalised');
	pg_assert_same(array_keys(pg_privacy_export_sections()), array_keys($document['sections']), 'every section, in order, nothing else');
	pg_assert_same('***', $document['sections']['user']['rows'][0]['user_password'], 'cleaned again');
	pg_assert_same(2, $document['sections']['orders']['count'], 'count');
	pg_assert_true($document['sections']['orders']['truncated'], 'truncated');
	pg_assert_same(0, $document['sections']['sms']['count'], 'empty section');

	$counts = pg_privacy_export_counts($document);
	pg_assert_same(3, array_sum($counts), 'counts');

	$json = pg_privacy_export_json($document);
	$decoded = json_decode($json, true);
	pg_assert_true(is_array($decoded), 'valid JSON');
	pg_assert_same($document['sections']['orders']['rows'], $decoded['sections']['orders']['rows'], 'round trip');

	$turkish = pg_privacy_export_json(array('name' => 'Şükrü Öztürk', 'broken' => "a\xC3"));
	pg_assert_contains('Şükrü Öztürk', $turkish, 'UTF-8 kept as it is');
	pg_assert_true(is_array(json_decode($turkish, true)), 'an invalid byte does not lose the file');
}

// ASCII only, after the first address, else the first id.
function test_privacy_export_filename()
{
	$time = mktime(12, 0, 0, 10, 20, 2026);

	pg_assert_same('personal-data-sukru-ozturk-example-com-2026-10-20.json', pg_privacy_export_filename(array('emails' => array('şükrü.öztürk@example.com')), 'json', $time), 'address');
	pg_assert_same('personal-data-user-7-2026-10-20.pdf', pg_privacy_export_filename(array('emails' => array(), 'user_ids' => array(7)), 'PDF', $time), 'user');
	pg_assert_same('personal-data-contact-3-2026-10-20.json', pg_privacy_export_filename(array('contact_ids' => array(3)), 'json', $time), 'contact');
	pg_assert_same('personal-data-2026-10-20.json', pg_privacy_export_filename(array(), 'j"s/on', $time), 'nobody, odd extension');
}

// The audit line names the address, keeps the token under a name the trail
// does not mask, and lists only the sections that held rows.
function test_privacy_export_audit_entry()
{
	$document = pg_privacy_export_document(
		array('emails' => array('ali@example.com'), 'user_ids' => array(9), 'contact_ids' => array(30, 31)),
		array('orders' => array('rows' => array(array('id' => '1')))),
		array('time' => 1760961600)
	);

	$entry = pg_privacy_export_audit_entry($document, 'pdf');

	pg_assert_same('ali@example.com', $entry['label'], 'label');
	pg_assert_same('pdf', $entry['after']['format'], 'format');
	pg_assert_same('9', $entry['after']['user_ids'], 'users');
	pg_assert_same('30,31', $entry['after']['contact_ids'], 'contacts');
	pg_assert_same(array('orders' => 1), $entry['after']['sections'], 'sections with rows');

	$redacted = pg_audit_redact(array('person' => str_repeat('a', 64)));
	pg_assert_same(str_repeat('a', 64), $redacted['person'], 'the token survives the trail');

	$row = pg_audit_row('export', 'person', 0, array(), $entry['after'], $entry['label']);
	pg_assert_true(is_array($row), 'a line is written');
	pg_assert_same(null, $row['before_json'], 'no before side');

	$nobody = pg_privacy_export_audit_entry(pg_privacy_export_document(array('user_ids' => array(5)), array(), array()), 'json');
	pg_assert_same('#5', $nobody['label'], 'label without an address');
}

// Only mobile numbers, in the queue's form, once each.
function test_privacy_export_phones()
{
	pg_assert_same(
		array('905321234567', '4915112345678'),
		pg_privacy_export_phones(array(
			array('mobile_phone' => '0532 123 45 67', 'home_phone' => '0212 123 45 67'),
			array('phone_number' => '+90 532 123 4567'),
			array('billing_phone_number' => '+49 151 12345678'),
			'not a row',
		)),
		'numbers'
	);
}

// The conditions the collectors are built from (texts are escaped with the
// connection, so they are seen in a sandbox, not here).
function test_privacy_export_conditions()
{
	pg_assert_same('', _pg_privacy_export_in('user_id', array()), 'nothing to look for');
	pg_assert_same('`user_id` IN (3, 7)', _pg_privacy_export_in('user_id', array(3, 7, 3)), 'ids');
	pg_assert_same('`x`.`contact_id` IN (5)', _pg_privacy_export_in('x.contact_id', array(5)), 'alias');
	pg_assert_same('', _pg_privacy_export_any(array('', '')), 'no condition');
	pg_assert_same('(a OR b)', _pg_privacy_export_any(array('a', '', 'b')), 'joined');
	pg_assert_same(array(2, 5), _pg_privacy_export_column(array(array('id' => '5'), array('id' => '2'), array('id' => '0'), array()), 'id'), 'column ids');
}

// The summary escapes what the records hold and lists every section.
function test_privacy_export_summary_html()
{
	$document = pg_privacy_export_document(
		array('emails' => array('ali@example.com')),
		array(
			'contact'  => array('rows' => array(array('id' => '30', 'first_name' => '<script>x</script>', 'last_name' => 'Veli', 'home_city' => 'İzmir'))),
			'orders'   => array('rows' => array(array('id' => '1', 'order_number' => '1172', 'order_date' => '1760961600', 'status' => 'complete', 'total' => '4714', 'currency_code' => 'TRY'))),
			'consents' => array('rows' => array(
				array('id' => '2', 'created_at' => '200', 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused', 'source' => 'account'),
				array('id' => '1', 'created_at' => '100', 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'signup'),
			)),
		),
		array('time' => 1760961600, 'site' => 'example.com', 'prepared_by' => 'admin')
	);

	$html = pg_privacy_export_summary_html($document);

	pg_assert_false(strpos($html, '<script>') !== false, 'record text escaped');
	pg_assert_contains('&lt;script&gt;x&lt;/script&gt; Veli', $html, 'name shown escaped');
	pg_assert_contains('İzmir', $html, 'address');
	pg_assert_contains('1172', $html, 'order number');
	pg_assert_contains('47', $html, 'order total in major units');
	pg_assert_contains('email:marketing', $html, 'current consent');
	pg_assert_contains('account', $html, 'newest answer wins');
	pg_assert_false(strpos($html, 'signup') !== false, 'older answer not listed');
	pg_assert_true(strpos($html, '<meta charset="utf-8">') < strpos($html, '<title>'), 'charset before any text');

	foreach (pg_privacy_export_sections() as $label) {
		pg_assert_contains(h($label), $html, 'section ' . $label);
	}
}

// A pending group consent (a double opt-in mail not answered) is shown as waiting, never as given.
function test_privacy_export_summary_pending_consent()
{
	$document = pg_privacy_export_document(
		array('emails' => array('ali@example.com')),
		array(
			'consents' => array('rows' => array(
				array('id' => '3', 'created_at' => '300', 'channel' => 'email', 'purpose' => 'group:7', 'status' => 'pending', 'source' => 'form'),
				array('id' => '4', 'created_at' => '300', 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'account'),
			)),
		),
		array('time' => 1760961600, 'site' => 'example.com', 'prepared_by' => 'admin')
	);

	$html = pg_privacy_export_summary_html($document);

	pg_assert_contains('<td>email:group:7</td><td>' . h(lang('Waiting for confirmation')) . '</td>', $html, 'pending');
	pg_assert_contains('<td>email:marketing</td><td>' . h(lang('Given')) . '</td>', $html, 'granted');
}
