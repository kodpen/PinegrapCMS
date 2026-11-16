<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the content review announce and task (includes/fn/
 * content_review.php): one content.review_due per item and date, none for an
 * item gone or not set, the claim's statement, the payload, the event's
 * places in the webhook list and the workspace, the follow-up's fields and
 * when "Make a task" is offered. The runner has no connection, so the run
 * is played against a row store kept in the test.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * Plays pg_content_review_announce_run() against $store (kind:id => row
 * with announced_on), claiming as the UPDATE does.
 */
function content_review_announce_test_play(array &$store, $today, array $gone = array())
{
	$announced = array();
	$rows = array();

	foreach ($store as $row) {
		if (($row['review_on'] !== null) && ($row['review_on'] < $today) && (($row['announced_on'] === null) || ($row['announced_on'] !== $row['review_on']))) {
			$rows[] = $row;
		}
	}

	pg_content_review_announce_run($rows,
		function ($row) use (&$store, $today) {
			$key = $row['item_kind'] . ':' . $row['item_id'];
			$now = $store[$key];

			if (($now['review_on'] !== $row['review_on']) || !($now['review_on'] < $today) || ($now['announced_on'] === $now['review_on'])) {
				return false;
			}

			$store[$key]['announced_on'] = $now['review_on'];

			return true;
		},
		function ($row) use ($gone) {
			return !in_array($row['item_kind'] . ':' . $row['item_id'], $gone, true);
		},
		function ($row) use (&$announced) {
			$announced[] = $row['item_kind'] . ':' . $row['item_id'];
		});

	return $announced;
}

function content_review_announce_test_row($kind, $id, $review_on, $announced_on = null)
{
	return array('item_kind' => $kind, 'item_id' => $id, 'user_id' => 7, 'review_on' => $review_on, 'announced_on' => $announced_on);
}

function test_content_review_announce_once_per_item_and_date()
{
	$store = array(
		'page:1'   => content_review_announce_test_row('page', 1, '2026-11-15'),
		'record:2' => content_review_announce_test_row('record', 2, '2026-11-10'),
		'page:3'   => content_review_announce_test_row('page', 3, '2026-11-16'),
	);

	pg_assert_same(array('page:1', 'record:2'), content_review_announce_test_play($store, '2026-11-16'), 'the dates passed are announced; today\'s is not yet');
	pg_assert_same(array(), content_review_announce_test_play($store, '2026-11-16'), 'a second run the same day announces nothing');
	pg_assert_same(array('page:3'), content_review_announce_test_play($store, '2026-11-17'), 'the next day only the date that passed then');
	pg_assert_same(array(), content_review_announce_test_play($store, '2026-11-20'), 'an item is not announced again on later days');

	// Mark reviewed or a new date separates the two dates again.
	$store['page:1']['review_on'] = '2026-11-18';
	pg_assert_same(array('page:1'), content_review_announce_test_play($store, '2026-11-20'), 'a new date that passed is announced once more');
}

function test_content_review_announce_none_for_gone_or_not_set()
{
	$store = array(
		'page:1' => content_review_announce_test_row('page', 1, '2026-11-01'),
		'page:4' => content_review_announce_test_row('page', 4, null),
	);

	pg_assert_same(array(), content_review_announce_test_play($store, '2026-11-16', array('page:1')), 'an item gone or binned is not announced');
	pg_assert_same('2026-11-01', $store['page:1']['announced_on'], 'but it is claimed, so it is not read again every run');
	pg_assert_same(null, $store['page:4']['announced_on'], 'an item without a date is never touched');

	$claimed = 0;
	$count = pg_content_review_announce_run(array(array('item_kind' => 'page', 'item_id' => 5, 'user_id' => 0, 'review_on' => null)),
		function () use (&$claimed) { $claimed++; return true; },
		function () { return true; },
		function () {});
	pg_assert_same(array(0, 0), array($count, $claimed), 'a row without a date is not claimed or announced');
}

function test_content_review_announce_claim_statement()
{
	$claim = pg_content_review_claim_sql('page', 12, '2026-11-15', '2026-11-16');

	pg_assert_true(strpos($claim['sql'], 'SET announced_on = review_on') !== false, 'the claim writes announced_on = review_on');
	pg_assert_true(strpos($claim['sql'], '(announced_on IS NULL OR announced_on <> review_on)') !== false, 'only where it is empty or another date');
	pg_assert_true(strpos($claim['sql'], 'review_on = ?') !== false, 'only the date the row was read with');
	pg_assert_same(false, stripos($claim['sql'], 'CURDATE') !== false, 'today is bound, never CURDATE()');
	pg_assert_same(array('page', '12', '2026-11-15', '2026-11-16'), $claim['params'], 'its values');
}

function test_content_review_announce_payload_and_places()
{
	$payload = pg_content_review_due_payload(array('item_kind' => 'record', 'item_id' => '42', 'user_id' => '7', 'review_on' => '2026-11-15'), 'Our story', 'ayse');
	pg_assert_same(array('id' => 42, 'kind' => 'record', 'name' => 'Our story', 'user_id' => 7, 'person' => 'ayse', 'review_on' => '2026-11-15'), $payload, 'the item, its person and the date');

	$nobody = pg_content_review_due_payload(array('item_kind' => 'page', 'item_id' => 3, 'user_id' => 0, 'review_on' => '2026-11-15'), 'home', 'admin');
	pg_assert_same(array(0, ''), array($nobody['user_id'], $nobody['person']), 'nobody\'s item names no person');

	require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
	pg_assert_true(array_key_exists('content.review_due', api_webhook_events()), 'a webhook can carry it');
	pg_assert_true(in_array('content.review_due', pg_event_workspace_events(), true), 'the workspace keeps it, so a flow can start from it');

	pg_assert_same(array('form', 42), ws_events_record_of('content.review_due', $payload), 'a record is the form it is');
	pg_assert_same(array('page', 3), ws_events_record_of('content.review_due', $nobody), 'a page is the page');

	$outputs = ws_flow_event_outputs();
	pg_assert_true(isset($outputs['content.review_due']), 'a flow reads its values');
}

function test_content_review_task_offered_and_fields()
{
	pg_assert_same(false, pg_content_review_task_offered(false, 7, true), 'no button with the Workspace off');
	pg_assert_same(false, pg_content_review_task_offered(true, 0, false), 'none for nobody\'s item');
	pg_assert_same(false, pg_content_review_task_offered(true, 7, false), 'none when the person is not on the team');
	pg_assert_same(true, pg_content_review_task_offered(true, 7, true), 'offered with the Workspace on and the person a member');

	$page = pg_content_review_task_input('page', 12, 'home', 7, '2026-12-01', '2026-11-16');
	pg_assert_same(array(array('type' => 'page', 'id' => 12)), $page['refs'], 'the page is tagged');
	pg_assert_same(array(7), $page['assignees'], 'a task for the item\'s person');
	pg_assert_same('2026-12-01', $page['due_date'], 'due on the review date');
	pg_assert_same(false, array_key_exists('remind_minutes', $page), 'a date ahead is reminded at its time');
	pg_assert_same(0, $page['channel_id'], 'the person\'s own task, on no channel');

	$record = pg_content_review_task_input('record', 42, 'Our story', 7, '2026-11-10', '2026-11-16');
	pg_assert_same(array(array('type' => 'form', 'id' => 42)), $record['refs'], 'a record is tagged as its form record');
	pg_assert_true(array_key_exists('remind_minutes', $record) && ($record['remind_minutes'] === null), 'a date passed sends no reminder');

	$undated = pg_content_review_task_input('page', 12, 'home', 7, null, '2026-11-16');
	pg_assert_same('2026-11-16', $undated['due_date'], 'without a date: due today');

	$data = ws_followup_task_data(1, $page, strtotime('2026-11-16 09:00:00'));
	pg_assert_true($data['ok'], 'the follow-up takes these fields');
}
