<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the visitor journey's scores tell (includes/fn/lead_tell.php):
 * when customer.hot and customer.returned are due, their payload, the
 * "Hot contacts" alert's code and sentence, and the places the two events
 * are named in (webhook catalogue, Workspace, flow start box, the scoring
 * pass).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');

function test_lead_tell_hot_once_until_it_falls()
{
	$now = 1800000000;

	pg_assert_same(array('hot' => true, 'returned' => false), pg_lead_tell_moves(null, 1, $now - 60, $now), 'a first score already hot is told');
	pg_assert_same(array('hot' => true, 'returned' => false), pg_lead_tell_moves(array('hot' => 0, 'last_visit_at' => $now - 9000, 'returned_at' => 0), 1, $now - 60, $now), 'rising over the threshold is told');
	pg_assert_same(false, pg_lead_tell_moves(array('hot' => 1, 'last_visit_at' => $now - 60, 'returned_at' => 0), 1, $now - 60, $now)['hot'], 'still hot is not told again');
	pg_assert_same(array('hot' => false, 'returned' => false), pg_lead_tell_moves(array('hot' => 1, 'last_visit_at' => $now - 60, 'returned_at' => 0), 0, $now - 60, $now), 'falling below says nothing');
	pg_assert_same(array('hot' => false, 'returned' => false), pg_lead_tell_moves(null, 0, $now - 60, $now), 'a cold contact says nothing');
}

function test_lead_tell_returned_once_a_day()
{
	$now = 1800000000;
	$old = array('hot' => 1, 'last_visit_at' => $now - 5 * 86400, 'returned_at' => 0);

	pg_assert_same(array('hot' => false, 'returned' => true), pg_lead_tell_moves($old, 1, $now - 300, $now), 'a hot contact starting a new visit is told');
	pg_assert_false(pg_lead_tell_moves($old, 1, $old['last_visit_at'], $now)['returned'], 'no new visit');
	pg_assert_false(pg_lead_tell_moves(array('returned_at' => $now - 3600) + $old, 1, $now - 300, $now)['returned'], 'told within the day');
	pg_assert_true(pg_lead_tell_moves(array('returned_at' => $now - 86400) + $old, 1, $now - 300, $now)['returned'], 'a day after the last one');
	pg_assert_false(pg_lead_tell_moves(array('hot' => 0) + $old, 1, $now - 300, $now)['returned'], 'turning hot by this visit is told as hot only');
	pg_assert_false(pg_lead_tell_moves($old, 1, $now - 2 * 86400, $now)['returned'], 'a visit tied back from days ago');
	pg_assert_false(pg_lead_tell_moves($old, 0, $now - 300, $now)['returned'], 'no longer hot');
}

function test_lead_tell_payload()
{
	$payload = pg_lead_event_payload(array(
		'contact_id' => '41', 'interest' => '72', 'fit' => 60,
		'reasons' => array(array('kind' => 'pricing_page', 'points' => 30.2), array('kind' => 'form', 'points' => 20)),
		'topics' => array(array('id' => 3, 'kind' => 'folder', 'name' => 'Services', 'weight' => 4.1), array('id' => 9, 'kind' => 'category', 'name' => 'Tables', 'weight' => 1)),
		'last_visit_at' => 1800000000,
	), 60);

	pg_assert_same(array(
		'id' => 41, 'score' => 72, 'fit' => 60, 'threshold' => 60, 'reasons' => array('pricing_page', 'form'),
		'topic' => 'Services', 'topics' => array('Services', 'Tables'), 'last_visit_at' => gmdate('Y-m-d\TH:i:s\Z', 1800000000),
	), $payload, 'codes and names only, never pages');

	$empty = pg_lead_event_payload(array('contact_id' => 2), 60);
	pg_assert_same('', $empty['topic'], 'no interest');
	pg_assert_same(null, $empty['last_visit_at'], 'no visit');
}

function test_lead_tell_alert()
{
	$codes = pg_ops_alert_codes();
	pg_assert_true(isset($codes['contacts_hot']), 'the alert code');
	pg_assert_same('view_contacts.php', $codes['contacts_hot']['url'], 'the bell opens the contacts');

	$detail = pg_lead_alert_detail(5, array('Ayşe Demir', '', 'ali@example.com', 'Can', 'Deniz'));
	pg_assert_contains('5', $detail, 'the count');
	pg_assert_contains('Ayşe Demir, ali@example.com, Can', $detail, 'the first names, empty ones dropped');
	pg_assert_false(strpos($detail, 'Deniz') !== false, 'at most three named');
	pg_assert_contains('2', substr($detail, strpos($detail, 'Can') + 3), 'the rest counted');
	pg_assert_false(strpos(pg_lead_alert_detail(1, array()), ':') !== false, 'nobody named');
}

function test_lead_tell_events_are_named()
{
	$events = api_webhook_core_events();
	$payloads = api_webhook_event_payloads();

	foreach (array('customer.hot', 'customer.returned') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' in the webhook catalogue');
		pg_assert_true(isset($payloads[$event]), $event . ' has its fields and sample');
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' kept for the Workspace');
		pg_assert_same(lang('Customers'), api_app_event_area($event), $event . ' under Customers');
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_quick.php');

	pg_assert_same(array('contact', 41), ws_events_record_of('customer.hot', array('id' => 41)));
	pg_assert_same(array('contact', 41), ws_events_record_of('customer.returned', array('id' => 41)));
	$outputs = ws_flow_event_outputs();
	pg_assert_true(isset($outputs['customer.hot'], $outputs['customer.returned']), 'the start box gives its values');
	pg_assert_contains('event:customer.hot', implode(' ', ws_flow_quick_areas()['customers']['starts']), 'offered under Customers');
}

function test_lead_tell_scoring_pass_calls()
{
	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_score.php');
	pg_assert_contains('pg_lead_tell(pg_lead_tell_moves($old, $hot, $last_visit, $now)', $module, 'each scored contact is told from its old row');
	pg_assert_contains('pg_lead_alert_send($now);', $module, 'the alert at the end of a pass');
	pg_assert_true(strpos($module, 'SELECT hot, last_visit_at, returned_at FROM contact_scores') < strpos($module, 'INSERT INTO contact_scores'), 'the old row is read before the write');
}
