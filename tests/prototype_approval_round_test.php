<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a prototype link's second review round and the verdicts in the
 * designer's lists: what the viewer outlines after the draft moved
 * (pg_proto_viewer_changes()), each page's mark in the viewer's page list
 * (pg_proto_review_page_states()), a link's count in the Share prototype
 * dialog (pg_proto_review_tally()) and the verdicts in the Comments list
 * (pg_proto_comment_list_verdicts()); the viewer's pure twins in
 * assets/js/pg_proto_comments.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');

function prototype_approval_round_test_row($id, $link, $page, $verdict, $fingerprint, $at, $note = '')
{
	return array(
		'id' => $id, 'link_id' => $link, 'style_id' => 3, 'page_id' => $page, 'verdict' => $verdict,
		'author_name' => 'Ayşe', 'note' => $note, 'fingerprint' => $fingerprint, 'node_fingerprints' => '{}', 'created_at' => $at,
	);
}

function test_prototype_approval_round_changes_only_for_an_older_verdict()
{
	$before = array('sd_1' => 'a', 'sd_2' => 'b', 'sd_3' => 'c');
	$after = array('sd_9' => 'z', 'sd_1' => 'a', 'sd_2' => 'B');

	pg_assert_same(null, pg_proto_viewer_changes($before, $after, false), 'a verdict on the draft as it is shows nothing');

	$changes = pg_proto_viewer_changes($before, $after, true);

	// Outlined in the draft's order now: the added node first, then the
	// changed one; the removed one is listed.
	pg_assert_same(array('sd_9', 'sd_2'), $changes['marks']);
	pg_assert_same(array('sd_3'), $changes['removed']);
}

function test_prototype_approval_round_changes_are_capped()
{
	$after = array();

	for ($i = 0; $i < 700; $i++) {
		$after['n' . $i] = 'x';
	}

	$changes = pg_proto_viewer_changes(array(), $after, true);

	pg_assert_same(500, count($changes['marks']));
	pg_assert_same('n0', $changes['marks'][0]);

	// A verdict stored without a node map: every node now counts as added.
	pg_assert_same(array('marks' => array('a'), 'removed' => array()), pg_proto_viewer_changes(null, array('a' => 'x'), true));
}

function test_prototype_approval_round_page_states()
{
	$rows = array(
		prototype_approval_round_test_row(1, 7, 10, 'changes_requested', 'f10', 100, 'Bigger logo'),
		prototype_approval_round_test_row(2, 7, 10, 'approved', 'f10', 200),
		prototype_approval_round_test_row(3, 7, 11, 'approved', 'old', 150),
		prototype_approval_round_test_row(4, 7, 12, 'changes_requested', 'f12', 150, 'No'),
		// A page that is no longer a draft is not listed.
		prototype_approval_round_test_row(5, 7, 99, 'approved', 'f99', 150),
	);
	$fingerprints = array(10 => 'f10', 11 => 'f11', 12 => 'f12', 13 => 'f13');

	pg_assert_same(array(10 => 'approved', 11 => 'changed', 12 => 'changes_requested', 13 => 'not_reviewed'),
		pg_proto_review_page_states($rows, $fingerprints));
	pg_assert_same(array(), pg_proto_review_page_states($rows, array()));
}

function test_prototype_approval_round_tally()
{
	$tally = pg_proto_review_tally(array(10 => 'approved', 11 => 'changed', 12 => 'changes_requested', 13 => 'not_reviewed', 14 => 'approved'));

	pg_assert_same(array('approved' => 2, 'pages' => 5, 'changes' => 1), $tally);
	pg_assert_same(array('approved' => 0, 'pages' => 0, 'changes' => 0), pg_proto_review_tally(array()));
}

function test_prototype_approval_round_comment_list_verdicts()
{
	$groups = array(
		array('page_id' => 10, 'name' => 'Home', 'open' => 1, 'resolved' => 0, 'links' => 1, 'threads' => array(array('id' => 4))),
		array('page_id' => 55, 'name' => '', 'open' => 1, 'resolved' => 0, 'links' => 1, 'threads' => array(array('id' => 5))),
	);
	$rows = array(
		prototype_approval_round_test_row(1, 7, 10, 'changes_requested', 'f10', 100, 'Bigger logo'),
		prototype_approval_round_test_row(2, 7, 10, 'approved', 'f10', 200),
		prototype_approval_round_test_row(3, 8, 10, 'changes_requested', 'old', 150, 'Darker'),
		prototype_approval_round_test_row(4, 7, 11, 'approved', 'f11', 150),
	);
	$names = array(11 => 'About', 10 => 'Home');
	$out = pg_proto_comment_list_verdicts($groups, $rows, $names, array(7 => 'Ab12', 8 => 'Zz99'), array(10 => 'f10', 11 => 'f11'));

	// A page with verdicts and no comment gets its own group, in the names'
	// order; a deleted page's group stays last.
	pg_assert_same(array(11, 10, 55), array_map(function ($g) { return $g['page_id']; }, $out));
	pg_assert_same('About', $out[0]['name']);
	pg_assert_same(array(), $out[0]['threads']);
	pg_assert_same(0, $out[0]['open']);

	// One verdict per link: the newest, in link order, with its hint.
	$home = $out[1]['verdicts'];
	pg_assert_same(2, count($home));
	pg_assert_same(array(7, 'Ab12', 'approved', false), array($home[0]['link_id'], $home[0]['link_hint'], $home[0]['verdict'], $home[0]['older']));
	pg_assert_same(array(8, 'Zz99', 'changes_requested', true, 'Darker'), array($home[1]['link_id'], $home[1]['link_hint'], $home[1]['verdict'], $home[1]['older'], $home[1]['note']));
	pg_assert_same(array(), $out[2]['verdicts']);
	pg_assert_same(array(array('id' => 4)), $out[1]['threads'], 'the threads are kept');

	pg_assert_same($groups[0]['threads'], pg_proto_comment_list_verdicts($groups, array(), $names, array(), array())[0]['threads']);
}

function test_prototype_approval_round_viewer_twins()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_comments.js') . "\n"
		. 'var C = window.PGProtoComments;'
		. 'var t = C.threads([{ id: 1, node: "sd_3", x: 1, y: 1 }, { id: 2, node: "sd_4", x: 1, y: 1 }, { id: 3, node: "sd_3", x: 1, y: 1, parent: 1 }]);'
		. 'console.log(JSON.stringify([C.changes({ marks: ["sd_1", "bad id", 4, "sd_1", "sd_2"], removed: ["sd_3"] }), C.changes(null), C.changes({ marks: "x" }),'
		. ' C.removedThreads(["sd_3", "sd_9"], t).map(function (x) { return x.id; }), C.removedThreads([], t).length]));';
	$file = @tempnam(sys_get_temp_dir(), 'pgpr');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));
	$got = json_decode(implode("\n", $out), true);

	pg_assert_same(array('marks' => array('sd_1', 'sd_2'), 'removed' => array('sd_3')), $got[0], 'only node ids, once each');
	pg_assert_same(null, $got[1]);
	pg_assert_same(array('marks' => array(), 'removed' => array()), $got[2]);
	pg_assert_same(array(1), $got[3], 'the threads left on a removed node');
	pg_assert_same(0, $got[4]);
}

function test_prototype_approval_round_viewer_review_carries_the_changes()
{
	$link = array('id' => 7, 'style_id' => 3, 'approval' => 1, 'publish_rule' => 0, 'approver_name' => 'Ada', 'approver_email' => 'ada@example.com', 'answer_by' => null, 'allow_comments' => 1);
	$standing = array('id' => 3, 'verdict' => 'approved', 'author_name' => 'Ada', 'note' => '', 'fingerprint' => str_repeat('a', 40), 'created_at' => 50);

	$data = pg_proto_viewer_review_data($link, 'Home', $standing, str_repeat('b', 40), array('marks' => array('sd_1'), 'removed' => array()));
	pg_assert_true($data['verdict']['older']);
	pg_assert_same(array('sd_1'), $data['changes']['marks']);

	foreach (array('show', 'hide', 'removed', 'changesHint') as $key) {
		pg_assert_true(isset($data['strings'][$key]) && $data['strings'][$key] !== '', 'string ' . $key);
	}

	pg_assert_same(null, pg_proto_viewer_review_data($link, 'Home', $standing, str_repeat('a', 40))['changes'], 'none without an older verdict');
}

function test_prototype_approval_round_shell_marks_the_page_list()
{
	$html = pg_proto_shell_html(array(
		'design' => 'Shop', 'pages' => array(
			array('id' => 10, 'name' => 'home', 'title' => 'Home', 'scheduled' => false),
			array('id' => 11, 'name' => 'about', 'title' => 'About', 'scheduled' => false),
		),
		'current' => 10, 'fallback' => false, 'frame_url' => '/f', 'viewer' => '/v', 'close_url' => '', 'software' => '/s', 'lang' => 'en', 'error' => '',
		'review_states' => array(10 => 'approved', 11 => 'changed'),
	));

	pg_assert_true(strpos($html, '>Home · Approved</option>') !== false);
	pg_assert_true(strpos($html, '>About · Changed since</option>') !== false);
}

function test_prototype_approval_round_frame_draws_the_marks()
{
	$script = pg_proto_frame_comment_script();

	pg_assert_true(strpos($script, 'm.className = "pg-proto-mark"') !== false, 'outlines in the pins layer');
	pg_assert_true(strpos($script, 'NODE.test(id)') !== false, 'only node ids are taken');
	pg_assert_true(strpos(pg_proto_frame_comment_css(), '.pg-proto-mark{') !== false);
}
