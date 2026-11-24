<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store of a prototype link asking approval (step 11.0.276,
 * includes/fn/prototype_verdicts.php): the migration step's columns and
 * table, the table in get_tables(), the link's approval settings as read,
 * the row a verdict is written as, the delete with the design and with a
 * link's comments, and the registrations with the personal data erasure,
 * export and inventory.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function prototype_verdicts_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of upgrade_2026_11_0_prototype_approval().
function prototype_verdicts_test_step()
{
	$source = prototype_verdicts_test_source('includes/migrations/2026.11.0.php');
	$start = (int) strpos($source, 'function upgrade_2026_11_0_prototype_approval()');
	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_prototype_verdicts_step_is_called_with_its_label()
{
	$source = prototype_verdicts_test_source('includes/migrations/2026.11.0.php');

	pg_assert_true((bool) preg_match('/upgrade_2026_11_0_prototype_approval\(\);\s+\/\/ 11\.0\.276\n/', $source), 'the step is called with its label');
}

function test_prototype_verdicts_step_adds_the_five_link_columns_idempotently()
{
	$step = prototype_verdicts_test_step();

	foreach (array(
		'approval'       => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
		'publish_rule'   => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
		'approver_name'  => "VARCHAR(60) NOT NULL DEFAULT ''",
		'approver_email' => "VARCHAR(255) NOT NULL DEFAULT ''",
		'answer_by'      => 'DATE NULL DEFAULT NULL',
	) as $column => $type) {
		pg_assert_true(strpos($step, "install_add_column('prototype_links', '" . $column . "', \"" . $type . "\")") !== false, $column . ' is added as ' . $type);
	}

	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no hand-written ALTER TABLE');
	pg_assert_true(strpos($step, 'install_note(') !== false, 'the step tells the operator');
}

function test_prototype_verdicts_step_creates_the_table()
{
	$step = prototype_verdicts_test_step();

	pg_assert_true(strpos($step, "install_create_table('prototype_verdicts'") !== false, 'the table is created idempotently');

	foreach (array('link_id', 'style_id', 'page_id', 'verdict', 'author_name', 'note', 'fingerprint', 'node_fingerprints', 'created_at') as $column) {
		pg_assert_true((bool) preg_match('/\n\t\t' . $column . '\s+/', $step), $column . ' is a column');
	}

	pg_assert_true(strpos($step, 'KEY idx_link_page (link_id, page_id, id)') !== false, 'a link\'s verdicts by page');
	pg_assert_true(strpos($step, 'KEY idx_page (page_id, id)') !== false, 'a page\'s verdicts');
	pg_assert_true(strpos($step, 'KEY idx_style (style_id)') !== false, 'the delete with the design');
	pg_assert_false((bool) preg_match('/\n\t\t(ip|email|user_agent|contact_id)\s+/', $step), 'the guest\'s address is not kept');

	$install = prototype_verdicts_test_source('install/index.php');
	pg_assert_true(strpos($install, "'prototype_verdicts',") !== false, 'get_tables() lists the table');
}

function test_prototype_verdicts_link_approval_reads_an_old_link_as_asking_nothing()
{
	pg_assert_same(
		array('approval' => false, 'publish_rule' => false, 'approver_name' => '', 'approver_email' => '', 'answer_by' => ''),
		pg_proto_link_approval(array('id' => 4, 'allow_comments' => 1)),
		'a link written before the step'
	);

	pg_assert_same('', pg_proto_link_approval(array('approval' => 1, 'answer_by' => null))['answer_by'], 'no date');
	pg_assert_same('', pg_proto_link_approval(array('approval' => 1, 'answer_by' => '0000-00-00'))['answer_by'], 'a zero date is none');
	pg_assert_same('2026-12-01', pg_proto_link_approval(array('approval' => 1, 'answer_by' => '2026-12-01'))['answer_by'], 'a date');

	$link = pg_proto_link_approval(array('approval' => '1', 'publish_rule' => '1', 'approver_name' => 'Ayşe', 'approver_email' => 'ayse@example.com'));
	pg_assert_true($link['approval'] && $link['publish_rule'], 'both on');
	pg_assert_same('ayse@example.com', $link['approver_email'], 'the address');

	pg_assert_false(pg_proto_link_approval(array('approval' => 0, 'publish_rule' => 1))['publish_rule'], 'no publish rule on a link not asking approval');
}

function test_prototype_verdicts_row_is_built_from_a_checked_verdict()
{
	$link = array('id' => 7, 'style_id' => 3);
	$hash = sha1('draft');
	$checked = pg_proto_verdict_check(array('verdict' => 'changes_requested', 'name' => 'Ayşe', 'note' => "Bigger logo\nplease"));

	$row = pg_proto_verdict_row($checked, $link, 12, $hash, array('n1abc' => sha1('a'), 'bad id!' => sha1('b'), 'n2abc' => 'not a hash'));

	pg_assert_same(7, $row['link_id'], 'the link');
	pg_assert_same(3, $row['style_id'], 'the design, from the link');
	pg_assert_same(12, $row['page_id'], 'the page');
	pg_assert_same('changes_requested', $row['verdict'], 'the verdict');
	pg_assert_same('Ayşe', $row['author_name'], 'the name typed');
	pg_assert_same("Bigger logo\nplease", $row['note'], 'the note');
	pg_assert_same($hash, $row['fingerprint'], 'sealed to the draft');
	pg_assert_same(array('n1abc' => sha1('a')), pg_proto_verdict_nodes($row), 'only well-formed node fingerprints are kept');

	pg_assert_same(null, pg_proto_verdict_row(pg_proto_verdict_check(array('verdict' => 'changes_requested', 'name' => 'Ayşe')), $link, 12, $hash, array()), 'a refused verdict is not written');
	pg_assert_same(null, pg_proto_verdict_row($checked, $link, 0, $hash, array()), 'no page');
	pg_assert_same(null, pg_proto_verdict_row($checked, array('id' => 7), 12, $hash, array()), 'no design');
	pg_assert_same(null, pg_proto_verdict_row($checked, $link, 12, 'abc', array()), 'no fingerprint');

	$approved = pg_proto_verdict_row(pg_proto_verdict_check(array('verdict' => 'approved', 'name' => 'Ayşe')), $link, 12, $hash, array());
	pg_assert_same('', $approved['note'], 'Approve without a note');
	pg_assert_same('{}', $approved['node_fingerprints'], 'an empty map is kept as an empty object');
	pg_assert_same(array(), pg_proto_verdict_nodes(array('node_fingerprints' => 'not json')), 'a broken map reads empty');
}

function test_prototype_verdicts_go_with_the_design_and_the_links_comments()
{
	pg_assert_same('DELETE FROM prototype_verdicts WHERE style_id IN (3,5)', pg_proto_verdicts_delete_design_sql(array(3, '5', 0, 3)), 'the designs\' verdicts');
	pg_assert_same('', pg_proto_verdicts_delete_design_sql(array(0, -1)), 'no design, no statement');

	$comments = prototype_verdicts_test_source('includes/fn/prototype_comments.php');

	$start = (int) strpos($comments, 'function pg_proto_comments_delete_design(');
	pg_assert_true(strpos(substr($comments, $start, 400), 'pg_proto_verdicts_delete_design($style_ids);') !== false, 'every design delete path takes the verdicts');

	$start = (int) strpos($comments, 'function pg_proto_comments_delete_link(');
	pg_assert_true(strpos(substr($comments, $start, 400), 'pg_proto_verdicts_delete_link($link_id);') !== false, 'Delete comments takes the link\'s verdicts');
}

function test_prototype_verdicts_privacy_registrations()
{
	$erase = pg_privacy_erase_tables();
	$order = array_keys($erase);

	pg_assert_same(array('link_id' => 'prototype_link_ids'), $erase['prototype_verdicts']['find'], 'verdicts found by the links naming the approver');
	pg_assert_same(array('author_name' => '{name}', 'note' => ''), $erase['prototype_verdicts']['fields'], 'a verdict stays without the name and the note');
	pg_assert_same('prototype_links', $erase['prototype_link_approvers']['table'], 'the link\'s approver columns');
	pg_assert_same(array('approver_email' => 'emails'), $erase['prototype_link_approvers']['find'], 'found by the address');
	pg_assert_same(array('approver_name' => '', 'approver_email' => ''), $erase['prototype_link_approvers']['fields'], 'no mail of the review goes any more');
	pg_assert_true(array_search('prototype_verdicts', $order, true) < array_search('prototype_link_approvers', $order, true), 'the verdicts before the link, which finds them');

	$scope = prototype_verdicts_test_source('includes/fn/privacy.php');
	pg_assert_true(strpos($scope, "\$scope['prototype_link_ids'] = ") !== false, 'the scope finds the links');

	$labels = pg_privacy_erase_labels();
	pg_assert_true(($labels['prototype_verdicts'] ?? '') !== '' && ($labels['prototype_link_approvers'] ?? '') !== '', 'the erasure names both');

	$sections = pg_privacy_export_sections();
	pg_assert_true(isset($sections['prototype_reviews']), 'the export has a section');

	$export = prototype_verdicts_test_source('includes/fn/privacy_export.php');
	$start = (int) strpos($export, "\$sections['prototype_reviews'] = ");
	$select = substr($export, $start, (int) strpos($export, ');', $start) - $start);
	pg_assert_true($start > 0, 'the section is read');

	foreach (array('token_hash', 'token_secret', 'token_hint', 'password_hash', 'node_fingerprints') as $column) {
		pg_assert_false(strpos($select, $column) !== false, 'the export leaves ' . $column . ' out');
	}

	$inventory = pg_privacy_inventory();
	pg_assert_same(array('approver_email', 'text'), $inventory['prototype_links']['person'], 'approvers counted by address');
	pg_assert_true(isset($inventory['prototype_verdicts']), 'the verdicts are listed');

	// Neither table keeps a contact's id, so the contact merge has nothing
	// to move.
	$holders = pg_contact_holders();
	pg_assert_false(isset($holders['prototype_links']) || isset($holders['prototype_verdicts']), 'no contact id is kept');
}
