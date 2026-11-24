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
