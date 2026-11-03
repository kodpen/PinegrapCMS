<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the group rows a recompute could not reach before: a contact
 * merge keeps each re-inserted row's source (pg_contact_merge_group_source()),
 * and the recompute of a dynamic group turns its other rows into rule rows
 * or drops them (pg_audience_diff(), pg_audience_write_queries()).
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

function test_audiences_diff_takes_over_other_rows_of_a_dynamic_group()
{
	pg_assert_same(
		array('add' => array(5), 'remove' => array(7, 9), 'convert' => array(6), 'drop' => array()),
		pg_audience_diff(array(5, 6), array(), array(6, 7, 9)),
		'a hand member the rule selects becomes a rule row; the others leave'
	);

	pg_assert_same(
		array('add' => array(), 'remove' => array(8, 11), 'convert' => array(), 'drop' => array(3)),
		pg_audience_diff(array(3), array(3, 8), array(3, 8, 11)),
		'a contact with both rows keeps its rule row only; unselected contacts leave whatever the source'
	);
}

function test_audiences_write_converts_and_drops()
{
	$queries = pg_audience_write_queries(7, array('add' => array(), 'remove' => array(20), 'convert' => array(30, 31), 'drop' => array(40)));

	pg_assert_same(3, count($queries), 'one convert, one delete, one drop');
	pg_assert_same("UPDATE contacts_contact_groups_xref SET source = 'rule' WHERE contact_group_id = ? AND source <> 'rule' AND contact_id IN (?, ?)", $queries[0]['sql']);
	pg_assert_same(array(7, 30, 31), $queries[0]['params']);
	pg_assert_same("DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND contact_id IN (?)", $queries[1]['sql']);
	pg_assert_same(array(7, 20), $queries[1]['params']);
	pg_assert_same("DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule' AND contact_id IN (?)", $queries[2]['sql']);
	pg_assert_same(array(7, 40), $queries[2]['params']);
}
