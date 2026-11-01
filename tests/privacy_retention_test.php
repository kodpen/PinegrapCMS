<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the retention of the audit trail and the consent log
 * (includes/fn/privacy_retention.php): the years the Security screen takes,
 * the cutoff moment, and which consent lines the daily pass may remove.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// 0 or a whole number within the bounds; anything else is refused.
function test_audit_retention_years_from_input()
{
	pg_assert_same(0, pg_audit_retention_years_from_input('0'), 'keep every line');
	pg_assert_same(2, pg_audit_retention_years_from_input(' 2 '), 'trimmed');
	pg_assert_same(10, pg_audit_retention_years_from_input('10'), 'upper bound');

	foreach (array('', '11', '-1', '1.5', 'two', null, array('2')) as $bad) {
		pg_assert_same(null, pg_audit_retention_years_from_input($bad), var_export($bad, true));
	}
}

// The same moment the given years ago; 0 years removes nothing.
function test_privacy_retention_cutoff()
{
	$now = mktime(14, 30, 0, 10, 27, 2026);

	pg_assert_same(mktime(14, 30, 0, 10, 27, 2024), pg_privacy_retention_cutoff(2, $now), 'two years');
	pg_assert_same(mktime(14, 30, 0, 10, 27, 2023), pg_privacy_retention_cutoff(3, $now), 'three years');
	pg_assert_same(0, pg_privacy_retention_cutoff(0, $now), 'kept');
	pg_assert_same(0, pg_privacy_retention_cutoff(-1, $now), 'negative');
}

// Old lines go only without a person or behind a newer line of the same person, channel and purpose.
function test_consent_log_trim_query_keeps_the_answer_in_force()
{
	$query = preg_replace('/\s+/', ' ', pg_consent_log_trim_query(1700000000, 500));

	pg_assert_contains("(c.created_at < '1700000000')", $query, 'older than the cutoff');
	pg_assert_contains("((c.person_token = '') AND (c.user_id = '0') AND (c.contact_id = '0'))", $query, 'no person');

	foreach (array('person_token', 'user_id', 'contact_id', 'channel', 'purpose') as $column) {
		pg_assert_contains('(n.' . $column . ' = c.' . $column . ')', $query, 'same ' . $column);
	}

	pg_assert_contains('((n.created_at > c.created_at) OR ((n.created_at = c.created_at) AND (n.id > c.id)))', $query, 'a newer line');
	pg_assert_contains('LIMIT 500', $query, 'batch');
}

// "Newer" is the order pg_consent_log_current() reads the answer in force
// by: time first, then id. A line written later with an earlier time (an
// IYS refusal stamped with IYS's own time) does not supersede a newer answer.
function test_consent_log_trim_query_newer_by_time_then_id()
{
	$query = preg_replace('/\s+/', ' ', pg_consent_log_trim_query(1700000000, 500));

	pg_assert_true(strpos($query, 'AND (n.id > c.id) )') === false, 'a higher id alone is not newer');

	$older = array('id' => 11, 'created_at' => 100, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused');
	$newer = array('id' => 10, 'created_at' => 200, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted');
	$current = pg_consent_log_current(array($older, $newer));

	pg_assert_same(10, $current['email:marketing']['id'], 'the answer in force is the newer time, not the higher id');
}

// The bounds the screen, the save and the job share.
function test_privacy_retention_limits()
{
	$limits = pg_privacy_retention_limits();

	pg_assert_same(2, $limits['audit_default'], 'default two years');
	pg_assert_same(3, $limits['consent_years'], 'consent three years');
	pg_assert_true($limits['audit_min'] <= $limits['audit_default'] && $limits['audit_default'] <= $limits['audit_max'], 'default within bounds');
}
