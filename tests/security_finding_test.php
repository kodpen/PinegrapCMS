<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Security Scan's finding model (includes/fn/security_scan.php):
 * kinds, places, the fingerprint, a sighting, merging it into the stored
 * row, the states a scan and a person move a finding to, the Ignore note and
 * the 90-day removal of gone findings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_security_finding_kind_valid_takes_rule_names_only()
{
	pg_assert_true(pg_security_kind_valid('upload_php'));
	pg_assert_true(pg_security_kind_valid(str_repeat('a', 40)));
	pg_assert_false(pg_security_kind_valid(str_repeat('a', 41)), 'longer than the column');
	pg_assert_false(pg_security_kind_valid('Upload'), 'upper case');
	pg_assert_false(pg_security_kind_valid('1rule'), 'starts with a digit');
	pg_assert_false(pg_security_kind_valid('a-b'));
	pg_assert_false(pg_security_kind_valid(''));
	pg_assert_false(pg_security_kind_valid(null));
}

function test_security_finding_place_path_is_one_spelling()
{
	pg_assert_same('files/shell.php', pg_security_place_path('./files/shell.php'));
	pg_assert_same('files/shell.php', pg_security_place_path('/files//shell.php'));
	pg_assert_same('files/a/b.php', pg_security_place_path('files\\a\\.\\b.php'));
	pg_assert_same('', pg_security_place_path('files/../config.php'), 'a .. segment');
	pg_assert_same('', pg_security_place_path('/'));
	pg_assert_same('', pg_security_place_path("a\0b"));
	pg_assert_same('', pg_security_place_path(array()));
	pg_assert_same('', pg_security_place_path(str_repeat('a', 501)), 'longer than the column');
	pg_assert_same(500, strlen(pg_security_place_path(str_repeat('a', 500))));
	pg_assert_same('dosyalar/şablon.php', pg_security_place_path('dosyalar/şablon.php'));
}

function test_security_finding_place_record_is_type_and_id()
{
	pg_assert_same('page:12', pg_security_place_record('page', 12));
	pg_assert_same('user:3', pg_security_place_record('user', '3'));
	pg_assert_same('', pg_security_place_record('page', 0));
	pg_assert_same('', pg_security_place_record('page', '12abc'));
	pg_assert_same('', pg_security_place_record('page', '1.5'));
	pg_assert_same('', pg_security_place_record('Page', 1));
	pg_assert_same('', pg_security_place_record('', 1));
}

function test_security_finding_fingerprint_is_rule_and_place()
{
	$fp = pg_security_fingerprint('upload_php', 'files/a.php');

	pg_assert_same(40, strlen($fp));
	pg_assert_true(ctype_xdigit($fp), 'hex');
	pg_assert_same($fp, pg_security_fingerprint('upload_php', 'files/a.php'), 'stable');
	pg_assert_true($fp !== pg_security_fingerprint('upload_php', 'files/b.php'), 'another place');
	pg_assert_true($fp !== pg_security_fingerprint('shell_mark', 'files/a.php'), 'another rule');
	// The separator keeps kind and place apart.
	pg_assert_true(pg_security_fingerprint('ab', 'c') !== pg_security_fingerprint('a', 'bc'));
}

function test_security_finding_severity_and_state_keys()
{
	pg_assert_same('critical', pg_security_severity_key(4));
	pg_assert_same('low', pg_security_severity_key('1'));
	pg_assert_same('', pg_security_severity_key(0));
	pg_assert_same('', pg_security_severity_key(5));
	pg_assert_same('open', pg_security_state_key(0));
	pg_assert_same('gone', pg_security_state_key(3));
	pg_assert_same('', pg_security_state_key(4));
}

function test_security_finding_sighting_refuses_what_is_not_a_finding()
{
	$s = pg_security_sighting('upload_php', 'files/a.php', PG_SECURITY_SEVERITY_HIGH, 'A PHP file in the upload folder.');

	pg_assert_same('upload_php', $s['kind']);
	pg_assert_same('files/a.php', $s['place']);
	pg_assert_same(pg_security_fingerprint('upload_php', 'files/a.php'), $s['fingerprint']);
	pg_assert_same(3, $s['severity']);
	pg_assert_same('A PHP file in the upload folder.', $s['detail']);

	pg_assert_same(null, pg_security_sighting('Bad kind', 'files/a.php', 3));
	pg_assert_same(null, pg_security_sighting('upload_php', '', 3));
	pg_assert_same(null, pg_security_sighting('upload_php', 'files/a.php', 9));
	pg_assert_same('', pg_security_sighting('upload_php', 'files/a.php', 3, null)['detail']);
	pg_assert_same(65535, strlen(pg_security_sighting('upload_php', 'a', 3, str_repeat('x', 70000))['detail']));
}

function test_security_finding_merge_opens_a_new_finding()
{
	$s = pg_security_sighting('upload_php', 'files/a.php', 3, 'why');
	$row = pg_security_finding_merge(null, $s, 5000);

	pg_assert_same(PG_SECURITY_STATE_OPEN, $row['state']);
	pg_assert_same(5000, $row['first_seen']);
	pg_assert_same(5000, $row['last_seen']);
	pg_assert_same(0, $row['acted_at']);
	pg_assert_same(0, $row['acted_by']);
	pg_assert_same('', $row['note']);
	pg_assert_same($s['fingerprint'], $row['fingerprint']);
}

function test_security_finding_merge_updates_a_stored_finding()
{
	$s = pg_security_sighting('upload_php', 'files/a.php', 4, 'new reason');
	$stored = array('id' => 7, 'kind' => 'upload_php', 'place' => 'files/a.php', 'fingerprint' => $s['fingerprint'], 'severity' => 3,
		'state' => PG_SECURITY_STATE_IGNORED, 'detail' => 'old', 'note' => 'Our own tool', 'acted_by' => 2, 'first_seen' => 100,
		'last_seen' => 200, 'acted_at' => 150);

	$row = pg_security_finding_merge($stored, $s, 5000);
	pg_assert_same(7, $row['id']);
	pg_assert_same(PG_SECURITY_STATE_IGNORED, $row['state'], 'ignored stays ignored');
	pg_assert_same(100, $row['first_seen']);
	pg_assert_same(5000, $row['last_seen']);
	pg_assert_same(4, $row['severity']);
	pg_assert_same('new reason', $row['detail']);
	pg_assert_same('Our own tool', $row['note']);
	pg_assert_same(2, $row['acted_by']);
	pg_assert_same(150, $row['acted_at']);

	$stored['state'] = PG_SECURITY_STATE_GONE;
	pg_assert_same(PG_SECURITY_STATE_OPEN, pg_security_finding_merge($stored, $s, 5000)['state'], 'gone found again opens');

	$stored['state'] = PG_SECURITY_STATE_QUARANTINED;
	pg_assert_same(PG_SECURITY_STATE_OPEN, pg_security_finding_merge($stored, $s, 5000)['state'], 'back at its place opens');
}

function test_security_finding_unseen_state()
{
	pg_assert_same(PG_SECURITY_STATE_GONE, pg_security_state_unseen(PG_SECURITY_STATE_OPEN));
	pg_assert_same(PG_SECURITY_STATE_GONE, pg_security_state_unseen(PG_SECURITY_STATE_IGNORED));
	pg_assert_same(PG_SECURITY_STATE_GONE, pg_security_state_unseen(PG_SECURITY_STATE_GONE));
	pg_assert_same(PG_SECURITY_STATE_QUARANTINED, pg_security_state_unseen(PG_SECURITY_STATE_QUARANTINED));
}

function test_security_finding_person_actions()
{
	pg_assert_same(PG_SECURITY_STATE_QUARANTINED, pg_security_state_after(PG_SECURITY_STATE_OPEN, 'quarantine'));
	pg_assert_same(PG_SECURITY_STATE_IGNORED, pg_security_state_after(PG_SECURITY_STATE_OPEN, 'ignore'));
	pg_assert_same(PG_SECURITY_STATE_OPEN, pg_security_state_after(PG_SECURITY_STATE_QUARANTINED, 'restore'));
	pg_assert_same(PG_SECURITY_STATE_OPEN, pg_security_state_after(PG_SECURITY_STATE_IGNORED, 'reopen'));

	pg_assert_same(null, pg_security_state_after(PG_SECURITY_STATE_QUARANTINED, 'ignore'));
	pg_assert_same(null, pg_security_state_after(PG_SECURITY_STATE_OPEN, 'restore'));
	pg_assert_same(null, pg_security_state_after(PG_SECURITY_STATE_GONE, 'quarantine'), 'nothing to act on');
	pg_assert_same(null, pg_security_state_after(PG_SECURITY_STATE_OPEN, 'delete'), 'the scan never deletes');
}

function test_security_finding_note_clean()
{
	pg_assert_same('Our backup tool', pg_security_note_clean("  Our\nbackup\t tool "));
	pg_assert_same(255, mb_strlen(pg_security_note_clean(str_repeat('ş', 300)), 'UTF-8'));
	pg_assert_same('', pg_security_note_clean(null));
	pg_assert_same('', pg_security_note_clean("\xff\xfe"), 'invalid UTF-8');
}

function test_security_finding_gone_rows_expire_after_90_days()
{
	$now = 100 * 86400;
	$row = array('state' => PG_SECURITY_STATE_GONE, 'last_seen' => $now - 90 * 86400);

	pg_assert_true(pg_security_finding_expired($row, $now));
	$row['last_seen'] = $now - 89 * 86400;
	pg_assert_false(pg_security_finding_expired($row, $now));
	pg_assert_false(pg_security_finding_expired(array('state' => PG_SECURITY_STATE_IGNORED, 'last_seen' => 0), $now), 'only gone rows');
}
