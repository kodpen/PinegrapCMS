<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the group rows a recompute could not reach before: a contact
 * merge keeps each re-inserted row's source (pg_contact_merge_group_source()),
 * the recompute touches only rule rows and re-reads the kind before each
 * page's write, and the switch to dynamic reviews the other rows
 * (pg_audience_switch_review()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_merge_keeps_the_row_source()
{
	$rows = array(
		array('contact_id' => 1, 'contact_group_id' => 5, 'source' => 'rule'),
		array('contact_id' => 2, 'contact_group_id' => 5, 'source' => 'rule'),
		array('contact_id' => 1, 'contact_group_id' => 6, 'source' => 'rule'),
		array('contact_id' => 2, 'contact_group_id' => 6, 'source' => 'form'),
		array('contact_id' => 2, 'contact_group_id' => 7, 'source' => 'product'),
		array('contact_id' => 1, 'contact_group_id' => 7, 'source' => 'hand'),
		array('contact_id' => 1, 'contact_group_id' => 8),
		array('contact_id' => 1, 'contact_group_id' => 9, 'source' => 'odd'),
	);

	pg_assert_same('rule', pg_contact_merge_group_source($rows, 5), 'a rule row stays a rule row');
	pg_assert_same('form', pg_contact_merge_group_source($rows, 6), 'a row of another source wins over a rule row: the recompute never removes it');
	pg_assert_same('hand', pg_contact_merge_group_source($rows, 7), 'hand before form and product');
	pg_assert_same('hand', pg_contact_merge_group_source($rows, 8), 'a row read before the upgrade is hand');
	pg_assert_same('hand', pg_contact_merge_group_source($rows, 9), 'an unknown source is hand');
	pg_assert_same('hand', pg_contact_merge_group_source(array(), 5), 'no row, hand');
}

function test_audiences_merge_inserts_write_the_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/contacts.php');

	pg_assert_same(2, substr_count($source, 'pg_contact_merge_group_source($contact_groups_to_be_merged,'), 'both merge blocks pick the source');
	pg_assert_same(2, substr_count($source, 'INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id, source) VALUES (?, ?, ?)'), 'both merge inserts write the source');
	pg_assert_contains('contacts_contact_groups_xref.source', $source, 'the merge reads the source');
}

function test_audiences_recompute_leaves_other_rows()
{
	pg_assert_same(
		array('add' => array(5), 'remove' => array()),
		pg_audience_diff(array(5, 6), array(), array(6, 7, 9)),
		'a hand member is neither added again nor removed, selected or not'
	);

	$queries = pg_audience_write_queries(7, array('add' => array(), 'remove' => array(20)));
	pg_assert_same(1, count($queries));
	pg_assert_contains("source = 'rule'", $queries[0]['sql'], 'the removal deletes rule rows only');
}

function test_audiences_recompute_rechecks_the_kind_before_each_write()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/audiences.php');
	$start = strpos($source, 'function pg_audience_recompute(');
	$body = substr($source, $start, strpos($source, 'function pg_audience_due_sql(') - $start);
	$check = strpos($body, 'pg_audience_still_dynamic($group_id)');
	$write = strpos($body, 'pg_audience_write($group_id, $diff)');

	pg_assert_true($check !== false && $write !== false && $check < $write, 'the kind is read again inside the loop, before the write');
	pg_assert_true(strpos($body, 'while (true)') < $check, 'the check runs on every page');
}

function test_audiences_switch_review()
{
	$review = pg_audience_switch_review('12', array(
		array('source' => 'hand', 'n' => '1240'),
		array('source' => 'form', 'n' => 30),
		array('source' => 'product', 'n' => '12'),
		array('source' => 'rule', 'n' => 99),
		array('source' => 'odd', 'n' => 5),
	));

	pg_assert_same(array('hand' => 1240, 'form' => 30, 'product' => 12), $review['counts'], 'counts by source; rule and unknown rows ignored');
	pg_assert_same(1282, $review['total']);
	pg_assert_same(
		"SELECT source, COUNT(*) AS n FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule' GROUP BY source",
		$review['count']['sql']
	);
	pg_assert_same(array(12), $review['count']['params']);

	pg_assert_same(1, count($review['remove']));
	pg_assert_same("DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule'", $review['remove'][0]['sql']);

	pg_assert_same(2, count($review['to_rule']), 'drop the duplicate beside a rule row, then turn the rest');
	pg_assert_contains("by_rule.source = 'rule'", $review['to_rule'][0]['sql']);
	pg_assert_contains("other.contact_group_id = ? AND other.source <> 'rule'", $review['to_rule'][0]['sql']);
	pg_assert_same("UPDATE contacts_contact_groups_xref SET source = 'rule' WHERE contact_group_id = ? AND source <> 'rule'", $review['to_rule'][1]['sql']);
	pg_assert_same(array(12), $review['to_rule'][1]['params']);

	$empty = pg_audience_switch_review(3);
	pg_assert_same(0, $empty['total'], 'no rows, nothing to review');
}
