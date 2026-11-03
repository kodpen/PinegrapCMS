<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Create Campaign recipient rule
 * (includes/fn/email_campaign_recipients.php): a commercial campaign takes
 * only contacts opted in globally and to a subscription group, a
 * transactional one takes every contact of the group with an address; the
 * group list shows both counts. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_recipients.php');

function email_campaign_recipients_test_squash($sql)
{
	return trim(preg_replace('/\s+/', ' ', $sql));
}

function test_email_campaign_recipients_opt_in_required()
{
	pg_assert_true(pg_email_campaign_opt_in_required('commercial'), 'commercial needs opt-in');
	pg_assert_false(pg_email_campaign_opt_in_required('transactional'), 'transactional does not');
	pg_assert_true(pg_email_campaign_opt_in_required(''), 'unknown purpose needs opt-in');
	pg_assert_true(pg_email_campaign_opt_in_required(null), 'missing purpose needs opt-in');
}

function test_email_campaign_recipients_commercial_query()
{
	$sql = email_campaign_recipients_test_squash(pg_email_campaign_recipient_query('commercial', true));
	pg_assert_contains('(contacts_contact_groups_xref.contact_group_id = ?)', $sql, 'group placeholder');
	pg_assert_contains('(contacts.opt_in = 1)', $sql, 'global opt-in');
	pg_assert_contains('LEFT JOIN opt_in ON', $sql, 'group opt-in joined');
	pg_assert_contains('((opt_in.opt_in = 1) OR (opt_in.opt_in IS NULL))', $sql, 'group opt-in');
	pg_assert_contains("(contacts.email_address != '')", $sql, 'address required');

	$sql = email_campaign_recipients_test_squash(pg_email_campaign_recipient_query('commercial', false));
	pg_assert_contains('(contacts.opt_in = 1)', $sql, 'global opt-in without subscription');
	pg_assert_false(strpos($sql, 'opt_in.opt_in') !== false, 'no group opt-in without subscription');
}

function test_email_campaign_recipients_transactional_query()
{
	foreach (array(true, false) as $subscription) {
		$sql = email_campaign_recipients_test_squash(pg_email_campaign_recipient_query('transactional', $subscription));
		pg_assert_contains('(contacts_contact_groups_xref.contact_group_id = ?)', $sql, 'group placeholder');
		pg_assert_contains("(contacts.email_address != '')", $sql, 'address still required');
		pg_assert_false(strpos($sql, 'opt_in = 1') !== false, 'no opt-in filter');
		pg_assert_contains('SELECT contacts.id, contacts.email_address FROM', $sql, 'id and address');
	}
}

function test_email_campaign_recipients_count_query()
{
	$sql = email_campaign_recipients_test_squash(pg_email_campaign_group_count_query(true));
	pg_assert_contains('COUNT(contacts.id) AS total', $sql, 'total count');
	pg_assert_contains('SUM(CASE WHEN (contacts.opt_in = 1) AND ((opt_in.opt_in = 1) OR (opt_in.opt_in IS NULL)) THEN 1 ELSE 0 END) AS opted_in', $sql, 'opted-in count follows the commercial rule');
	pg_assert_contains("WHERE (contacts_contact_groups_xref.contact_group_id = ?) AND (contacts.email_address != '')", $sql, 'group and address');

	$sql = email_campaign_recipients_test_squash(pg_email_campaign_group_count_query(false));
	pg_assert_contains('SUM(CASE WHEN (contacts.opt_in = 1) THEN 1 ELSE 0 END) AS opted_in', $sql, 'global opt-in only');
	pg_assert_false(strpos($sql, 'LEFT JOIN opt_in') !== false, 'no group join');
}

function test_email_campaign_recipients_group_label()
{
	pg_assert_same('Orders &amp; Co (' . lang(array('string' => '{var:1}, opted in {var:2}', 'vars' => array(pg_format_number(23, 0), pg_format_number(3, 0)))) . ')', pg_email_campaign_group_label('Orders & Co', 23, 3), 'both counts');
	pg_assert_same('News (' . pg_format_number(5, 0) . ')', pg_email_campaign_group_label('News', 5, '5'), 'one count when all opted in');
}
