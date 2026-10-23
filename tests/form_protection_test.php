<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the custom form protections: the signed fill time
 * (pg_cf_issued_token() / pg_cf_issued_verdict()), the counters a submission
 * is held against (pg_cf_throttle_subjects()), the submitter address worked
 * out before the form row is written, the staff rule
 * (waf_panel_access_from_row()) and the sweep that keeps day-long counters.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// A token signed now verifies once a person had time to fill the form.
function test_form_protection_issued_round_trip()
{
	$token = pg_cf_issued_token(1000, 'secret');

	pg_assert_true((bool) preg_match('/^1000\.[0-9a-f]{32}$/', $token), 'shape');
	pg_assert_same('ok', pg_cf_issued_verdict($token, 1010, 'secret'), 'ten seconds later');
	pg_assert_same('ok', pg_cf_issued_verdict($token, 1003, 'secret'), 'exactly the minimum');
}

// A changed time, a changed signature or another key is refused.
function test_form_protection_issued_tampered()
{
	$token = pg_cf_issued_token(1000, 'secret');
	list(, $signature) = explode('.', $token);

	pg_assert_same('bad', pg_cf_issued_verdict('900.' . $signature, 1010, 'secret'), 'earlier time, old signature');
	pg_assert_same('bad', pg_cf_issued_verdict('1000.' . str_repeat('0', 32), 1010, 'secret'), 'made-up signature');
	pg_assert_same('bad', pg_cf_issued_verdict($token, 1010, 'other'), 'another key');
	pg_assert_same('bad', pg_cf_issued_verdict('1000', 1010, 'secret'), 'time alone');
	pg_assert_same('bad', pg_cf_issued_verdict($token . 'x', 1010, 'secret'), 'trailing junk');
}

// Missing, too fast, from the future and too old.
function test_form_protection_issued_missing_fast_old()
{
	$token = pg_cf_issued_token(1000, 'secret');

	pg_assert_same('missing', pg_cf_issued_verdict('', 1010, 'secret'), 'empty');
	pg_assert_same('missing', pg_cf_issued_verdict(null, 1010, 'secret'), 'not posted');
	pg_assert_same('missing', pg_cf_issued_verdict(array('x'), 1010, 'secret'), 'array');
	pg_assert_same('fast', pg_cf_issued_verdict($token, 1002, 'secret'), 'two seconds');
	pg_assert_same('fast', pg_cf_issued_verdict($token, 990, 'secret'), 'ahead of the clock');
	pg_assert_same('ok', pg_cf_issued_verdict($token, 1000 + 86400, 'secret'), 'a day');
	pg_assert_same('old', pg_cf_issued_verdict($token, 1000 + 86401, 'secret'), 'over a day');
}

// A site without an encryption key prints no field and checks nothing.
function test_form_protection_issued_without_key()
{
	pg_assert_same('', pg_cf_issued_token(1000, ''), 'no token');
	pg_assert_same('ok', pg_cf_issued_verdict('', 1000, ''), 'nothing posted');
	pg_assert_same('ok', pg_cf_issued_verdict('junk', 1000, ''), 'anything posted');
}

// One row per counter, the address folded to one mailbox, its domain apart.
function test_form_protection_subjects_rows()
{
	$limits = array('ip_hour' => 10, 'recipient_day' => 3, 'domain_hour' => 30);

	$rows = pg_cf_throttle_subjects('  Foo.Bar@Example.COM ', '203.0.113.7', $limits);

	pg_assert_same(array(
		array('subject' => 'i|203.0.113.7', 'scope' => 'cfi', 'window' => 3600, 'limit' => 10),
		array('subject' => 'r|foo.bar@example.com', 'scope' => 'cfr', 'window' => 86400, 'limit' => 3),
		array('subject' => 'd|example.com', 'scope' => 'cfd', 'window' => 3600, 'limit' => 30),
	), $rows, 'three rows');

	$again = pg_cf_throttle_subjects('foo.bar@example.com', '203.0.113.7', $limits);

	pg_assert_same($rows, $again, 'case and spaces do not make another mailbox');
}

// A limit of 0 drops its row; no address leaves the visitor's own counter.
function test_form_protection_subjects_zero_and_empty()
{
	$rows = pg_cf_throttle_subjects('a@b.example', '203.0.113.7', array('ip_hour' => 10, 'recipient_day' => 0, 'domain_hour' => 30));

	pg_assert_same(array('cfi', 'cfd'), array_column($rows, 'scope'), 'recipient off');

	$rows = pg_cf_throttle_subjects('', '203.0.113.7', array('ip_hour' => 10, 'recipient_day' => 3, 'domain_hour' => 30));

	pg_assert_same(array('cfi'), array_column($rows, 'scope'), 'no address');

	foreach (array('no-at-sign', '@example.com', 'name@') as $broken) {
		$rows = pg_cf_throttle_subjects($broken, '', array('ip_hour' => 10, 'recipient_day' => 3, 'domain_hour' => 30));
		pg_assert_same(array(), $rows, 'not an address: ' . $broken);
	}

	$rows = pg_cf_throttle_subjects('a@b.example', '', array('ip_hour' => 0, 'recipient_day' => 0, 'domain_hour' => 0));

	pg_assert_same(array(), $rows, 'every limit off');
}

// The domain is what follows the last @.
function test_form_protection_subjects_last_at()
{
	$rows = pg_cf_throttle_subjects('"odd@local"@mail.example', '', array('ip_hour' => 10, 'recipient_day' => 3, 'domain_hour' => 30));

	pg_assert_same('d|mail.example', $rows[1]['subject'], 'domain');
}

// The address as custom_form.php settles it: the contact e-mail field, the
// signed-in account, then the only e-mail field.
function test_form_protection_submitter_address_guess()
{
	$fields = array(
		array('id' => 1, 'type' => 'text box', 'contact_field' => ''),
		array('id' => 2, 'type' => 'email address', 'contact_field' => ''),
		array('id' => 3, 'type' => 'email address', 'contact_field' => 'email_address'),
	);

	pg_assert_same('contact@example.com', pg_cf_submitter_address_guess($fields, array(1 => 'x', 2 => 'first@example.com', 3 => 'contact@example.com'), 'user@example.com'), 'contact field first');
	pg_assert_same('user@example.com', pg_cf_submitter_address_guess($fields, array(2 => 'first@example.com', 3 => ''), 'user@example.com'), 'then the account');
	pg_assert_same('', pg_cf_submitter_address_guess($fields, array(2 => 'first@example.com', 3 => ''), ''), 'two e-mail fields: nobody');

	$one = array(
		array('id' => 5, 'type' => 'email address', 'contact_field' => ''),
		array('id' => 6, 'type' => 'check box', 'contact_field' => ''),
	);

	pg_assert_same('only@example.com', pg_cf_submitter_address_guess($one, array(5 => 'only@example.com', 6 => array('a')), ''), 'the only e-mail field');
	pg_assert_same('', pg_cf_submitter_address_guess($one, array(5 => array('x@example.com')), ''), 'a list is no address');
}

// The address counters apply only when the form writes to the address.
function test_form_protection_form_writes_to_submitter()
{
	pg_assert_false(pg_cf_form_writes_to_submitter(array('submitter_email' => 0, 'contact_group_id' => 0, 'auto_registration' => 0)), 'stores only');
	pg_assert_true(pg_cf_form_writes_to_submitter(array('submitter_email' => 1, 'contact_group_id' => 0, 'auto_registration' => 0)), 'mails');
	pg_assert_true(pg_cf_form_writes_to_submitter(array('submitter_email' => 0, 'contact_group_id' => '4', 'auto_registration' => 0)), 'subscribes');
	pg_assert_true(pg_cf_form_writes_to_submitter(array('submitter_email' => 0, 'contact_group_id' => '', 'auto_registration' => '1')), 'registers');
}

// Staff: role 0-2, or role 3 with a panel area or an editable folder.
function test_form_protection_panel_access_from_row()
{
	for ($role = 0; $role <= 2; $role++) {
		pg_assert_true(waf_panel_access_from_row(array('user_role' => (string) $role), false), 'role ' . $role);
	}

	$member = array('user_role' => '3', 'user_manage_contacts' => 'no', 'user_manage_visitors' => 'no',
		'user_manage_ecommerce' => 'no', 'user_manage_forms' => 'no', 'user_manage_calendars' => 'no',
		'user_manage_emails' => 'no', 'manage_ecommerce_reports' => '0', 'manage_erp' => '0', 'manage_workspace' => '0');

	pg_assert_false(waf_panel_access_from_row($member, false), 'member');
	pg_assert_true(waf_panel_access_from_row($member, true), 'member who edits a folder');

	foreach (array('user_manage_contacts', 'user_manage_visitors', 'user_manage_ecommerce',
	               'user_manage_forms', 'user_manage_calendars', 'user_manage_emails') as $flag) {
		$row = $member;
		$row[$flag] = 'yes';
		pg_assert_true(waf_panel_access_from_row($row, false), $flag);
	}

	foreach (array('manage_ecommerce_reports', 'manage_erp', 'manage_workspace') as $flag) {
		$row = $member;
		$row[$flag] = '1';
		pg_assert_true(waf_panel_access_from_row($row, false), $flag);
	}

	pg_assert_false(waf_panel_access_from_row(array(), true), 'no row');
	pg_assert_false(waf_panel_access_from_row(null, true), 'not a row');
}

// The shared sweep spares the day-long counters; they get their own.
function test_form_protection_sweep_keeps_day_counters()
{
	$queries = waf_rate_sweep_queries(100000, 3600);

	pg_assert_same(array(
		"DELETE FROM waf_rate WHERE window_start < 96400 AND bucket_key NOT LIKE '%|cfr' LIMIT 5000",
		"DELETE FROM waf_rate WHERE window_start < 13600 AND bucket_key LIKE '%|cfr' LIMIT 5000",
	), $queries, 'hour retention');

	// A sign-in lockout longer than a day already covers them.
	pg_assert_same(array(
		"DELETE FROM waf_rate WHERE window_start < 0 LIMIT 5000",
	), waf_rate_sweep_queries(100000, 100000), 'retention past a day');
}
