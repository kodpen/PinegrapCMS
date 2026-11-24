<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of a prototype link asking approval
 * (includes/fn/prototype_approval.php): the draft's fingerprint, the node
 * fingerprints and what changed between two drafts, a verdict's check and
 * state, the "Answer by" date and the mails' timing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A small draft tree: a section holding a heading and a paragraph, and a
// second section holding a button.
function prototype_approval_test_tree()
{
	return array(
		'type' => 'root', '_id' => 'sd_root', 'props' => array(), 'children' => array(
			array('type' => 'semantic', '_id' => 'sd_1', 'props' => array('tag' => 'section'), 'children' => array(
				array('type' => 'semantic', '_id' => 'sd_2', 'props' => array('tag' => 'h2', 'text' => 'Season'), 'children' => array()),
				array('type' => 'content', '_id' => 'sd_3', 'props' => array('contentType' => 'paragraph', 'text' => 'Our news.'), 'children' => array()),
			)),
			array('type' => 'semantic', '_id' => 'sd_4', 'props' => array('tag' => 'section'), 'styles' => array('padding' => '2rem'), 'children' => array(
				array('type' => 'content', '_id' => 'sd_5', 'props' => array('contentType' => 'button', 'text' => 'Buy'), 'children' => array()),
			)),
		),
	);
}

function prototype_approval_test_settings()
{
	return array('page_name' => 'home', 'page_title' => 'Home', 'page_meta_description' => '');
}

function prototype_approval_test_fingerprint($tree, $settings = null)
{
	return pg_proto_draft_fingerprint(json_encode($tree), ($settings === null) ? prototype_approval_test_settings() : $settings);
}

function test_prototype_approval_fingerprint_is_sha1_shaped_and_stable()
{
	$a = prototype_approval_test_fingerprint(prototype_approval_test_tree());

	pg_assert_true((bool) preg_match('/^[0-9a-f]{40}$/', $a), 'sha1 shape');
	pg_assert_same($a, prototype_approval_test_fingerprint(prototype_approval_test_tree()));
}

function test_prototype_approval_fingerprint_ignores_settings_key_order()
{
	$tree     = prototype_approval_test_tree();
	$settings = prototype_approval_test_settings();
	$reversed = array_reverse($settings, true);

	pg_assert_same(prototype_approval_test_fingerprint($tree, $settings), prototype_approval_test_fingerprint($tree, $reversed));
}

function test_prototype_approval_fingerprint_of_row_reads_tree_and_settings()
{
	$tree = json_encode(prototype_approval_test_tree());
	$row  = array('page_tree_json' => $tree, 'page_name' => 'home', 'page_title' => 'Home', 'page_meta_description' => null, 'page_id' => 9);

	pg_assert_same(pg_proto_draft_fingerprint($tree, prototype_approval_test_settings()), pg_proto_draft_fingerprint_of_row($row));
	pg_assert_same('', pg_proto_draft_fingerprint_of_row(null));
}

function test_prototype_approval_settings_only_change()
{
	$tree     = prototype_approval_test_tree();
	$settings = prototype_approval_test_settings();
	$changed  = array_merge($settings, array('page_title' => 'Welcome'));

	pg_assert_true(prototype_approval_test_fingerprint($tree, $settings) !== prototype_approval_test_fingerprint($tree, $changed), 'fingerprint moves');

	$diff = pg_proto_nodes_diff(pg_proto_node_fingerprints($tree), pg_proto_node_fingerprints($tree));
	pg_assert_same(array('changed' => array(), 'added' => array(), 'removed' => array()), $diff);
}

function test_prototype_approval_text_change()
{
	$before = prototype_approval_test_tree();
	$after  = $before;
	$after['children'][0]['children'][1]['props']['text'] = 'Our latest news.';

	pg_assert_true(prototype_approval_test_fingerprint($before) !== prototype_approval_test_fingerprint($after), 'fingerprint moves');

	$diff = pg_proto_nodes_diff(pg_proto_node_fingerprints($before), pg_proto_node_fingerprints($after));
	// Only the paragraph itself: its parents' own values did not change.
	pg_assert_same(array('changed' => array('sd_3'), 'added' => array(), 'removed' => array()), $diff);
}

function test_prototype_approval_moved_node()
{
	$before = prototype_approval_test_tree();
	$after  = $before;
	// The button moves from the second section into the first.
	$button = $after['children'][1]['children'][0];
	$after['children'][1]['children'] = array();
	$after['children'][0]['children'][] = $button;

	pg_assert_true(prototype_approval_test_fingerprint($before) !== prototype_approval_test_fingerprint($after), 'fingerprint moves');

	// A move changes no node's own values: nothing to outline.
	$diff = pg_proto_nodes_diff(pg_proto_node_fingerprints($before), pg_proto_node_fingerprints($after));
	pg_assert_same(array('changed' => array(), 'added' => array(), 'removed' => array()), $diff);
}

function test_prototype_approval_removed_and_added_node()
{
	$before = prototype_approval_test_tree();
	$after  = $before;
	unset($after['children'][0]['children'][0]);
	$after['children'][0]['children'] = array_values($after['children'][0]['children']);
	$after['children'][1]['children'][] = array('type' => 'content', '_id' => 'sd_6', 'props' => array('contentType' => 'paragraph', 'text' => 'New'), 'children' => array());

	$diff = pg_proto_nodes_diff(pg_proto_node_fingerprints($before), pg_proto_node_fingerprints($after));
	pg_assert_same(array('changed' => array(), 'added' => array('sd_6'), 'removed' => array('sd_2')), $diff);
}

function test_prototype_approval_node_fingerprints_shape()
{
	$prints = pg_proto_node_fingerprints(json_encode(prototype_approval_test_tree()));

	pg_assert_same(array('sd_root', 'sd_1', 'sd_2', 'sd_3', 'sd_4', 'sd_5'), array_keys($prints));
	pg_assert_true((bool) preg_match('/^[0-9a-f]{40}$/', $prints['sd_2']), 'sha1 shape');
	pg_assert_same(array(), pg_proto_node_fingerprints('not json'));
	pg_assert_same(array(), pg_proto_node_fingerprints(null));
}

function test_prototype_approval_node_fingerprint_ignores_key_order_and_skips_bad_ids()
{
	$a = array('type' => 'semantic', '_id' => 'sd_1', 'props' => array('tag' => 'h2', 'text' => 'A'), 'children' => array());
	$b = array('children' => array(), 'props' => array('text' => 'A', 'tag' => 'h2'), '_id' => 'sd_1', 'type' => 'semantic');
	pg_assert_same(pg_proto_node_fingerprints($a), pg_proto_node_fingerprints($b));

	// No id, an id not of the node shape, and a repeated id: the first one stands.
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'semantic', 'props' => array(), 'children' => array()),
		array('type' => 'semantic', '_id' => 'bad id!', 'children' => array()),
		array('type' => 'semantic', '_id' => 'sd_7', 'props' => array('text' => 'first'), 'children' => array()),
		array('type' => 'semantic', '_id' => 'sd_7', 'props' => array('text' => 'second'), 'children' => array()),
	));
	$prints = pg_proto_node_fingerprints($tree);
	pg_assert_same(array('sd_7'), array_keys($prints));
	pg_assert_same(pg_proto_node_fingerprints($tree['children'][2]), $prints);
}

function test_prototype_approval_diff_keeps_draft_order()
{
	$before = array('a' => '1', 'b' => '2', 'c' => '3');
	$after  = array('c' => '9', 'd' => '4', 'a' => '8');

	pg_assert_same(array('changed' => array('c', 'a'), 'added' => array('d'), 'removed' => array('b')), pg_proto_nodes_diff($before, $after));
	pg_assert_same(array('changed' => array(), 'added' => array('d'), 'removed' => array()), pg_proto_nodes_diff(null, array('d' => '4')));
}

function test_prototype_approval_verdict_check()
{
	$ok = pg_proto_verdict_check(array('verdict' => 'approved', 'name' => " Ayşe\n", 'note' => ''));
	pg_assert_same('', $ok['error']);
	pg_assert_same('approved', $ok['verdict']);
	pg_assert_same('Ayşe', $ok['name']);
	pg_assert_same('', $ok['note']);

	$changes = pg_proto_verdict_check(array('verdict' => 'changes_requested', 'name' => 'Ayşe', 'note' => "Bigger logo.\r\nThanks"));
	pg_assert_same('', $changes['error']);
	pg_assert_same("Bigger logo.\nThanks", $changes['note']);

	pg_assert_same('note', pg_proto_verdict_check(array('verdict' => 'changes_requested', 'name' => 'Ayşe', 'note' => "  \n"))['error']);
	pg_assert_same('verdict', pg_proto_verdict_check(array('verdict' => 'maybe', 'name' => 'Ayşe'))['error']);
	pg_assert_same('name', pg_proto_verdict_check(array('verdict' => 'approved', 'name' => ''))['error']);
	pg_assert_same('name', pg_proto_verdict_check(array('verdict' => 'approved', 'name' => str_repeat('a', 61)))['error']);
	pg_assert_same('note', pg_proto_verdict_check(array('verdict' => 'approved', 'name' => 'A', 'note' => str_repeat('a', 1001)))['error']);
	pg_assert_same('verdict', pg_proto_verdict_check('nope')['error']);
}

function test_prototype_approval_newest_verdict_per_page()
{
	$rows = array(
		array('id' => 1, 'page_id' => 10, 'verdict' => 'changes_requested', 'created_at' => 100),
		array('id' => 3, 'page_id' => 10, 'verdict' => 'approved', 'created_at' => 200),
		array('id' => 2, 'page_id' => 11, 'verdict' => 'approved', 'created_at' => 150),
		array('id' => 4, 'page_id' => 11, 'verdict' => 'changes_requested', 'created_at' => 150),
		array('id' => 5, 'page_id' => 0, 'verdict' => 'approved', 'created_at' => 300),
		'junk',
	);
	$newest = pg_proto_verdicts_newest($rows);

	pg_assert_same(array(10, 11), array_keys($newest));
	pg_assert_same(3, $newest[10]['id']);
	// The same second: the later row stands.
	pg_assert_same(4, $newest[11]['id']);
}

function test_prototype_approval_verdict_state()
{
	$print = str_repeat('a', 40);

	pg_assert_same(array('state' => 'not_reviewed', 'older' => false), pg_proto_verdict_state(null, $print));
	pg_assert_same(array('state' => 'approved', 'older' => false), pg_proto_verdict_state(array('verdict' => 'approved', 'fingerprint' => $print), $print));
	pg_assert_same(array('state' => 'approved', 'older' => true), pg_proto_verdict_state(array('verdict' => 'approved', 'fingerprint' => $print), str_repeat('b', 40)));
	pg_assert_same(array('state' => 'changes_requested', 'older' => true), pg_proto_verdict_state(array('verdict' => 'changes_requested', 'fingerprint' => $print), ''));
	pg_assert_same(array('state' => 'not_reviewed', 'older' => false), pg_proto_verdict_state(array('verdict' => 'odd', 'fingerprint' => $print), $print));

	pg_assert_true(pg_proto_verdict_approves(array('verdict' => 'approved', 'fingerprint' => $print), $print), 'current approval');
	pg_assert_false(pg_proto_verdict_approves(array('verdict' => 'approved', 'fingerprint' => $print), str_repeat('b', 40)), 'older approval');
	pg_assert_false(pg_proto_verdict_approves(array('verdict' => 'changes_requested', 'fingerprint' => $print), $print), 'changes');
	pg_assert_false(pg_proto_verdict_approves(array('verdict' => 'approved', 'fingerprint' => ''), ''), 'empty print');
}

function test_prototype_approval_answer_by_check()
{
	$now     = mktime(12, 0, 0, 11, 24, 2026);
	$expires = mktime(9, 0, 0, 12, 1, 2026);

	pg_assert_same(array('error' => '', 'date' => ''), pg_proto_answer_by_check('', $expires, $now));
	pg_assert_same(array('error' => '', 'date' => '2026-11-24'), pg_proto_answer_by_check('2026-11-24', $expires, $now));
	pg_assert_same(array('error' => '', 'date' => '2026-12-01'), pg_proto_answer_by_check(' 2026-12-01 ', $expires, $now));
	pg_assert_same('after_expiry', pg_proto_answer_by_check('2026-12-02', $expires, $now)['error']);
	pg_assert_same('past', pg_proto_answer_by_check('2026-11-23', $expires, $now)['error']);
	pg_assert_same('date', pg_proto_answer_by_check('2026-02-30', $expires, $now)['error']);
	pg_assert_same('date', pg_proto_answer_by_check('24.11.2026', $expires, $now)['error']);
	pg_assert_same('date', pg_proto_answer_by_check(array('2026-11-25'), $expires, $now)['error']);
	// A link that never expires bounds nothing.
	pg_assert_same('', pg_proto_answer_by_check('2030-01-01', 0, $now)['error']);
}

function test_prototype_approval_reminder_due()
{
	$day_before = mktime(10, 0, 0, 11, 29, 2026);

	pg_assert_true(pg_proto_answer_by_reminder_due('2026-11-30', $day_before, 2, ''), 'due');
	pg_assert_false(pg_proto_answer_by_reminder_due('2026-11-30', $day_before, 0, ''), 'all decided');
	pg_assert_false(pg_proto_answer_by_reminder_due('2026-11-30', $day_before, 2, '2026-11-29'), 'sent today');
	pg_assert_false(pg_proto_answer_by_reminder_due('2026-11-30', mktime(10, 0, 0, 11, 28, 2026), 2, ''), 'two days before');
	pg_assert_false(pg_proto_answer_by_reminder_due('2026-11-30', mktime(10, 0, 0, 11, 30, 2026), 2, ''), 'the day itself');
	pg_assert_false(pg_proto_answer_by_reminder_due('', $day_before, 2, ''), 'no date');
	// Across a month's end.
	pg_assert_true(pg_proto_answer_by_reminder_due('2026-12-01', mktime(23, 0, 0, 11, 30, 2026), 1, ''), 'month end');
}

function test_prototype_approval_answer_mail_due()
{
	pg_assert_same(array('limit' => 1, 'window' => 3600), pg_proto_answer_mail_rate());

	pg_assert_true(pg_proto_answer_mail_due('ayse@example.com', 3, false), 'due');
	pg_assert_false(pg_proto_answer_mail_due('ayse@example.com', 3, true), 'within the hour');
	pg_assert_false(pg_proto_answer_mail_due('ayse@example.com', 0, false), 'no answers');
	pg_assert_false(pg_proto_answer_mail_due('', 3, false), 'no address');
	pg_assert_false(pg_proto_answer_mail_due('not an address', 3, false), 'bad address');
}
