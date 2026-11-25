<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a prototype share link asking approval: the Share prototype
 * dialog's and the API create route's approval fields
 * (pg_proto_link_plan_approval()), the link's summary, the verdict door, the
 * viewer's review data, the receipt mail and the bell line.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

// A plan pg_proto_link_plan() accepted: a week from $now.
function prototype_approval_link_test_plan($now)
{
	return pg_proto_link_plan(array('audience' => 'anyone', 'start_page_id' => 0, 'duration' => 604800, 'password' => ''), array(11), array(604800), $now);
}

function test_prototype_approval_link_off_asks_nothing()
{
	$now = mktime(12, 0, 0, 3, 10, 2027);
	$plan = pg_proto_link_plan_approval(prototype_approval_link_test_plan($now), array('approval' => 0, 'approver_name' => 'Ada', 'publish_rule' => 1), $now);

	pg_assert_true($plan['ok']);
	pg_assert_same(0, $plan['link']['approval']);
	pg_assert_same(0, $plan['link']['publish_rule'], 'the publish rule only with approval');
	pg_assert_same('', $plan['link']['approver_name']);
	pg_assert_same(0, $plan['link']['allow_comments']);

	$plan = pg_proto_link_plan_approval(prototype_approval_link_test_plan($now), array(), $now);
	pg_assert_true($plan['ok'], 'not sent: off');
	pg_assert_same(0, $plan['link']['approval']);
}

function test_prototype_approval_link_on_takes_the_fields_and_turns_comments_on()
{
	$now = mktime(12, 0, 0, 3, 10, 2027);
	$plan = pg_proto_link_plan_approval(prototype_approval_link_test_plan($now), array(
		'approval' => '1', 'approver_name' => "  Ada Lovelace\n", 'approver_email' => ' ada@example.com ',
		'answer_by' => '2027-03-17', 'publish_rule' => true,
	), $now);

	pg_assert_true($plan['ok']);
	pg_assert_same(1, $plan['link']['approval']);
	pg_assert_same(1, $plan['link']['publish_rule']);
	pg_assert_same('Ada Lovelace', $plan['link']['approver_name']);
	pg_assert_same('ada@example.com', $plan['link']['approver_email']);
	pg_assert_same('2027-03-17', $plan['link']['answer_by'], 'the expiry day itself');
	pg_assert_same(1, $plan['link']['allow_comments']);

	$plan = pg_proto_link_plan_approval(prototype_approval_link_test_plan($now), array('approval' => true, 'approver_name' => 'Ada'), $now);
	pg_assert_true($plan['ok'], 'e-mail, date and rule are optional');
	pg_assert_same('', $plan['link']['approver_email']);
	pg_assert_same('', $plan['link']['answer_by']);
	pg_assert_same(0, $plan['link']['publish_rule']);
}

function test_prototype_approval_link_refusals_name_their_field()
{
	$now = mktime(12, 0, 0, 3, 10, 2027);
	$cases = array(
		array(array('approval' => 'yes', 'approver_name' => 'Ada'), 'approval', 'approval'),
		array(array('approval' => 1, 'approver_name' => '   '), 'approver_name', 'approver_name'),
		array(array('approval' => 1, 'approver_name' => str_repeat('a', 61)), 'approver_name', 'approver_name'),
		array(array('approval' => 1, 'approver_name' => array('Ada')), 'approver_name', 'approver_name'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'approver_email' => 'not an address'), 'approver_email', 'approver_email'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'approver_email' => array('a@b.c')), 'approver_email', 'approver_email'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'answer_by' => '2027-02-30'), 'answer_by', 'answer_by'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'answer_by' => '2027-03-09'), 'answer_by_past', 'answer_by'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'answer_by' => '2027-03-18'), 'answer_by_after_expiry', 'answer_by'),
		array(array('approval' => 1, 'approver_name' => 'Ada', 'publish_rule' => 'maybe'), 'publish_rule', 'publish_rule'),
	);

	foreach ($cases as $i => $case) {
		$plan = pg_proto_link_plan_approval(prototype_approval_link_test_plan($now), $case[0], $now);
		pg_assert_false($plan['ok'], 'case ' . $i);
		pg_assert_same($case[1], $plan['error'], 'case ' . $i);
		pg_assert_same($case[2], $plan['field'], 'case ' . $i);
		pg_assert_true(pg_proto_link_error_message($plan['error']) !== lang('Sorry, we could not accept your request.'), 'case ' . $i . ' has its sentence');
	}
}

function test_prototype_approval_link_a_refused_plan_stays_refused()
{
	$refused = array('ok' => false, 'error' => 'audience', 'field' => 'audience', 'link' => null);

	pg_assert_same($refused, pg_proto_link_plan_approval($refused, array('approval' => 1, 'approver_name' => 'Ada'), time()));
}

function test_prototype_approval_link_summary_shows_the_settings_without_the_address()
{
	$row = array(
		'id' => 4, 'style_id' => 2, 'start_page_id' => 0, 'audience' => 'anyone', 'token_hint' => 'AbCdEf',
		'password_hash' => '', 'expires_at' => 0, 'created_by' => 1, 'created_at' => 1, 'revoked_at' => 0,
		'uses' => 0, 'last_used_at' => 0, 'allow_comments' => 1, 'approval' => 1, 'publish_rule' => 1,
		'approver_name' => 'Ada', 'approver_email' => 'ada@example.com', 'answer_by' => '2027-03-17',
	);
	$summary = pg_proto_link_summary($row, 2);

	pg_assert_true($summary['approval']);
	pg_assert_true($summary['publish_rule']);
	pg_assert_same('Ada', $summary['approver_name']);
	pg_assert_same('2027-03-17', $summary['answer_by']);
	pg_assert_false(in_array('ada@example.com', $summary, true), 'the address is not listed');

	unset($row['approval'], $row['publish_rule'], $row['approver_name'], $row['answer_by']);
	$summary = pg_proto_link_summary($row, 2);
	pg_assert_false($summary['approval'], 'a row read before the step');
	pg_assert_same('', $summary['answer_by']);
}

// A link row asking approval, as the store keeps it.
function prototype_approval_link_test_row($over = array())
{
	return array_merge(array(
		'id' => 4, 'style_id' => 2, 'created_by' => 7, 'allow_comments' => 1, 'approval' => 1, 'publish_rule' => 0,
		'approver_name' => 'Ada', 'approver_email' => 'ada@example.com', 'answer_by' => null,
	), $over);
}

function test_prototype_approval_link_verdict_public_reads_an_older_version()
{
	$row = array('id' => 9, 'verdict' => 'approved', 'author_name' => 'Ada', 'note' => '', 'fingerprint' => str_repeat('a', 40), 'created_at' => 100, 'link_id' => 4);

	pg_assert_same(array('id' => 9, 'verdict' => 'approved', 'older' => false, 'name' => 'Ada', 'note' => '', 'created_at' => 100), pg_proto_verdict_public($row, str_repeat('a', 40)));
	pg_assert_true(pg_proto_verdict_public($row, str_repeat('b', 40))['older'], 'the draft moved since');
}

function test_prototype_approval_link_viewer_review_only_for_a_link_asking_approval()
{
	pg_assert_same(null, pg_proto_viewer_review_data(prototype_approval_link_test_row(array('approval' => 0)), 'Home', null, ''));

	$data = pg_proto_viewer_review_data(prototype_approval_link_test_row(), 'Home', null, str_repeat('a', 40));
	pg_assert_same('Home', $data['page']);
	pg_assert_same(null, $data['verdict'], 'not reviewed yet');
	pg_assert_same(60, $data['nameMax']);
	pg_assert_same(1000, $data['noteMax']);
	pg_assert_false(isset($data['approver_email']), 'the address never goes to the viewer');
	pg_assert_false(strpos(json_encode($data), 'ada@example.com') !== false);

	foreach (array('review', 'approve', 'changes', 'note', 'noteOptional', 'noteNeeded', 'choose', 'approved', 'requested', 'changed') as $key) {
		pg_assert_true(isset($data['strings'][$key]) && $data['strings'][$key] !== '', 'string ' . $key);
	}

	$standing = array('id' => 3, 'verdict' => 'changes_requested', 'author_name' => 'Ada', 'note' => 'Bigger logo', 'fingerprint' => str_repeat('a', 40), 'created_at' => 50);
	$data = pg_proto_viewer_review_data(prototype_approval_link_test_row(), 'Home', $standing, str_repeat('a', 40));
	pg_assert_same('changes_requested', $data['verdict']['verdict']);
	pg_assert_false($data['verdict']['older']);
}

function test_prototype_approval_link_door_routes_a_verdict_after_the_gates()
{
	$door = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype_comment.php');
	$gates = strpos($door, '$pg_pc_refusal = pg_proto_comment_door($pg_pc_facts);');
	$ping = strpos($door, 'if (pg_proto_comment_is_ping($_POST))');
	$verdict = strpos($door, 'if (pg_proto_verdict_is_post($_POST))');
	$comment = strpos($door, '$pg_pc_checked = pg_proto_comment_check(');

	pg_assert_true($gates !== false && $ping !== false && $verdict !== false && $comment !== false);
	pg_assert_true($gates < $ping && $ping < $verdict && $verdict < $comment, 'gates, ping, verdict, then a comment');
	pg_assert_true(pg_proto_verdict_is_post(array('verdict' => 'approved')));
	pg_assert_false(pg_proto_verdict_is_post(array('text' => 'Hi')));
	pg_assert_same(403, pg_proto_comment_error_status('approval_off'));
	pg_assert_same(400, pg_proto_comment_error_status('note'));

	foreach (array('approval_off', 'verdict', 'note') as $code) {
		pg_assert_true(pg_proto_comment_error_message($code) !== lang('Sorry, we could not accept your request.'), $code . ' has its sentence');
	}

	// A link asking nothing is refused before a field is read.
	$taken = pg_proto_verdict_take(prototype_approval_link_test_row(array('approval' => 0)), 11, array('verdict' => 'approved', 'name' => 'Ada'), time());
	pg_assert_same('approval_off', $taken['error']);
	pg_assert_same(403, $taken['status']);
}

function test_prototype_approval_link_bell_line_words_and_place()
{
	$words = pg_proto_verdict_bell_words('approved', 'Home', 'Ada');
	pg_assert_same(lang('Prototype page approved'), $words['short']);
	pg_assert_contains('Ada', $words['title']);
	pg_assert_contains('Home', $words['title']);

	$words = pg_proto_verdict_bell_words('changes_requested', 'Home', 'Ada');
	pg_assert_same(lang('Changes requested on a prototype'), $words['short']);

	$words = pg_proto_verdict_bell_words('approved', '', '');
	pg_assert_same($words['short'], $words['title'], 'a deleted verdict and page');

	include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
	pg_assert_same('content', pg_notification_module('prototype_verdict'));
	pg_assert_same(false, pg_notification_visible(array('action' => 'prototype_verdict', 'target_user_id' => 0), array('id' => 3, 'role' => 0)), 'never unaddressed');
	pg_assert_same(true, pg_notification_visible(array('action' => 'prototype_verdict', 'target_user_id' => 3), array('id' => 3, 'role' => 1)), 'the maker\'s');
	pg_assert_same(false, pg_proto_verdict_bell(prototype_approval_link_test_row(array('created_by' => 0)), array('id' => 1), 'Home'), 'no maker, no line');
}

function test_prototype_approval_link_receipt_carries_the_address_never_the_password()
{
	$row = array('verdict' => 'changes_requested', 'author_name' => 'Ada', 'note' => "Bigger logo\nand blue");
	$mail = pg_proto_verdict_receipt_words($row, 'Home', 'Shop', '2027-03-10', 'https://example.com/prototype.php?link=abc');

	pg_assert_contains('Home', $mail['subject']);
	pg_assert_contains('Ada', $mail['body']);
	pg_assert_contains("Bigger logo\nand blue", $mail['body']);
	pg_assert_contains('https://example.com/prototype.php?link=abc', $mail['body']);

	$mail = pg_proto_verdict_receipt_words(array('verdict' => 'approved', 'author_name' => 'Ada', 'note' => ''), 'Home', 'Shop', '2027-03-10', '');
	pg_assert_false(strpos($mail['body'], lang('Your note:')) !== false, 'no note, no heading');
	pg_assert_false(strpos($mail['body'], lang('To look at the prototype again:')) !== false, 'no address to give');

	pg_assert_false(pg_proto_verdict_receipt(prototype_approval_link_test_row(array('approver_email' => '')), array('verdict' => 'approved'), 'Home'), 'no address, no mail');
	pg_assert_false(pg_proto_verdict_receipt(prototype_approval_link_test_row(array('approver_email' => 'nope')), array('verdict' => 'approved'), 'Home'));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_verdicts.php');
	pg_assert_false(strpos($source, 'password_hash') !== false, 'the receipt never reads a password');
}

function test_prototype_approval_link_shell_draws_review_only_with_its_settings()
{
	$view = array(
		'design' => 'Shop', 'pages' => array(array('id' => 11, 'name' => 'home', 'title' => 'Home')), 'current' => 11,
		'frame_url' => 'prototype.php?frame=1', 'viewer' => 'prototype.php?link=x', 'software' => '/pinegrap',
		'comments' => array('door' => 'd', 'link' => 4, 'page' => 11, 'token' => 't', 'comments' => array(), 'strings' => array()),
	);

	$html = pg_proto_shell_html($view);
	pg_assert_false(strpos($html, 'id="pg-proto-review"') !== false, 'no review on a link asking nothing');

	$view['comments']['review'] = pg_proto_viewer_review_data(prototype_approval_link_test_row(), 'Home', null, '');
	$html = pg_proto_shell_html($view);
	pg_assert_contains('id="pg-proto-review"', $html);
	pg_assert_contains('id="pg-proto-review-state"', $html);
	pg_assert_contains(h(lang('Review this page')), $html);
}

function test_prototype_approval_link_viewer_bar_line()
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

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_comments.js') . "\n"
		. 'var S = { approved: "A {date}", requested: "R {date}", changed: "C" }, L = window.PGProtoComments.reviewLine;'
		. 'console.log(JSON.stringify([L(null, S, "d"), L({ verdict: "approved", older: false }, S, "d"), L({ verdict: "changes_requested", older: false }, S, "d"), L({ verdict: "approved", older: true }, S, "d"), L({ verdict: "x" }, S, "d")]));';
	$file = @tempnam(sys_get_temp_dir(), 'pgpr');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status);
	pg_assert_same(array('', 'A d', 'R d', 'C', ''), json_decode(implode("\n", $out), true));
}

// A verdict is sealed to the draft the guest saw: the viewer is drawn with
// the draft's fingerprint and posts it back; a draft changed since is
// refused, so an approval never stands for a version the guest never saw.
function test_prototype_approval_link_verdict_names_the_draft_it_saw()
{
	$fp = str_repeat('a', 40);
	$data = pg_proto_viewer_review_data(prototype_approval_link_test_row(), 'Home', null, $fp);
	pg_assert_same($fp, $data['fingerprint'], 'the viewer knows the draft it draws');

	pg_assert_true(pg_proto_verdict_seen_ok($fp, $fp), 'the same draft');
	pg_assert_false(pg_proto_verdict_seen_ok(str_repeat('b', 40), $fp), 'the draft changed since');
	pg_assert_false(pg_proto_verdict_seen_ok('', $fp), 'nothing named');
	pg_assert_false(pg_proto_verdict_seen_ok(null, $fp), 'no field');
	pg_assert_false(pg_proto_verdict_seen_ok(array($fp), $fp), 'not a string');
	pg_assert_false(pg_proto_verdict_seen_ok($fp, ''), 'the draft is gone');

	pg_assert_same(409, pg_proto_comment_error_status('changed'));
	pg_assert_true(pg_proto_comment_error_message('changed') !== lang('Sorry, we could not accept your request.'), 'changed has its sentence');

	$take = prototype_approval_link_test_body('includes/fn/prototype_verdicts.php', 'pg_proto_verdict_take');
	$seen = strpos($take, 'pg_proto_verdict_seen_ok(');
	$save = strpos($take, 'pg_proto_verdict_save(');
	pg_assert_true($seen !== false && $save !== false && $seen < $save, 'the seen draft is checked before the verdict is written');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_comments.js');
	pg_assert_contains("data.append('seen', String(R.fingerprint || ''));", $js, 'the viewer posts the draft it drew');
}

// The body of a named function in a file under pinegrap/.
function prototype_approval_link_test_body($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 10);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}
