<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the people and content events a flow can start on: a user
 * account made, a consent given or withdrawn, a bounced e-mail, a campaign
 * sent, a page made or changed, a calendar event reserved, store credit
 * issued or expired. Each reaches the Workspace (pg_event_workspace_events()),
 * names its record (ws_events_record_of()), gives the start box values
 * (ws_flow_event_outputs()) from the payload the Workspace keeps
 * (pg_event_workspace_payload()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_people_content_start_events_list()
{
	return array(
		'user.created'             => 'user_account',
		'customer.consent_changed' => 'contact',
		'mail.bounced'             => 'contact',
		'campaign.sent'            => '',
		'page.created'             => 'page',
		'page.updated'             => 'page',
		'calendar_event.reserved'  => 'calendar_event',
		'credit.issued'            => 'contact',
		'credit.expired'           => 'contact',
	);
}

// Kept from pg_announce(), with outputs, in the catalogue as written.
function test_workspace_people_content_start_events_reach_the_workspace()
{
	$outputs = ws_flow_event_outputs();
	$catalog = pg_test_function_source('ws_events_catalog');

	foreach (array_keys(workspace_people_content_start_events_list()) as $event) {
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' is kept for the Workspace');
		pg_assert_false(ws_events_is_own($event), $event . ' is the site\'s, not the Workspace\'s own');
		pg_assert_true(isset($outputs[$event]), $event . ' gives the start box values');
		pg_assert_contains("'" . $event . "'", $catalog, $event . ' in ws_events_catalog()');
		pg_assert_false(ws_events_watched($event), $event . ' is not written into watching channels');
	}
}

// Each names its record from the payload the Workspace keeps.
function test_workspace_people_content_start_events_record_of()
{
	pg_assert_same(array('user_account', 4), ws_events_record_of('user.created', array('id' => 4, 'username' => 'ayse')));
	pg_assert_same(array('contact', 5), ws_events_record_of('customer.consent_changed', array('id' => 5)));
	pg_assert_same(array('contact', 6), ws_events_record_of('mail.bounced', array('address' => 'a@b.c', 'contact_id' => 6)));
	pg_assert_same(array('', 0), ws_events_record_of('mail.bounced', array('address' => 'a@b.c', 'contact_id' => 0)), 'an address of no contact');
	pg_assert_same(array('', 0), ws_events_record_of('campaign.sent', array('id' => 3)), 'a campaign is no tagged record');
	pg_assert_same(array('page', 8), ws_events_record_of('page.created', array('id' => 8)));
	pg_assert_same(array('page', 8), ws_events_record_of('page.updated', array('id' => 8)));
	pg_assert_same(array('calendar_event', 9), ws_events_record_of('calendar_event.reserved', array('id' => 9, 'order_id' => 2)));
	pg_assert_same(array('contact', 10), ws_events_record_of('credit.issued', array('id' => 1, 'customer_id' => 10)));
	pg_assert_same(array('', 0), ws_events_record_of('credit.expired', array('id' => 1, 'customer_id' => null)), 'an account of no contact');
}

// The payload as the Workspace keeps it: a consent's nested answer and a
// bounce's yes/no as single values; the others as announced.
function test_workspace_people_content_start_events_payload()
{
	$consent = pg_event_workspace_payload('customer.consent_changed', array('id' => 5, 'consent' => array('id' => 2, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused', 'source' => 'form')));

	pg_assert_same('email', $consent['consent_channel']);
	pg_assert_same('marketing', $consent['consent_purpose']);
	pg_assert_same('refused', $consent['consent_status']);
	pg_assert_same('form', $consent['consent_source']);
	pg_assert_same(5, $consent['id']);

	$bounce = pg_event_workspace_payload('mail.bounced', array('address' => 'a@b.c', 'kind' => 'hard', 'suppressed' => true), function ($address) {
		return ($address === 'a@b.c') ? 6 : 0;
	});

	pg_assert_same(1, $bounce['suppressed']);
	pg_assert_same(6, $bounce['contact_id']);
	pg_assert_same(0, pg_event_workspace_payload('mail.bounced', array('address' => 'x@y.z', 'suppressed' => false), function () {
		return 0;
	})['suppressed']);

	pg_assert_same(array('id' => 8, 'name' => 'Home'), pg_event_workspace_payload('page.created', array('id' => 8, 'name' => 'Home')));
}

// Every output a start box offers is a single value the kept payload holds;
// no amount in minor units is offered.
function test_workspace_people_content_start_events_outputs_are_single_values()
{
	$outputs = ws_flow_event_outputs();
	$keys = function ($event) use ($outputs) {
		return array_column($outputs[$event], 'key');
	};

	pg_assert_same(array('username', 'email', 'role'), $keys('user.created'));
	pg_assert_same(array('consent_channel', 'consent_purpose', 'consent_status', 'consent_source'), $keys('customer.consent_changed'));
	pg_assert_same(array('address', 'kind', 'source', 'suppressed', 'contact_id', 'campaign_id'), $keys('mail.bounced'));
	pg_assert_same(array('id', 'channel', 'type'), $keys('campaign.sent'));
	pg_assert_same(array('name'), $keys('page.created'));
	pg_assert_same(array('name'), $keys('page.updated'));
	pg_assert_same(array('recurrence_number', 'quantity', 'order_id', 'order_number', 'contact_id'), $keys('calendar_event.reserved'));

	foreach (array('credit.issued', 'credit.expired') as $event) {
		pg_assert_same(array('customer_id', 'account_id', 'kind', 'reason', 'expires_at'), $keys($event));
	}
}

// A page is read as its tag resolves for the creator; the campaigns screen
// asks for the e-mail right.
function test_workspace_people_content_start_events_rights()
{
	pg_assert_true(ws_events_checks_creator('page.created'));
	pg_assert_true(ws_events_checks_creator('page.updated'));
	pg_assert_false(ws_events_checks_creator('user.created'));

	$staff = ws_viewer(array('id' => 1, 'role' => 2));
	$member = ws_viewer(array('id' => 2, 'role' => 3, 'manage_emails' => true));
	$other = ws_viewer(array('id' => 3, 'role' => 3));

	pg_assert_true($staff['emails']);
	pg_assert_true($member['emails']);
	pg_assert_false($other['emails']);

	$from_row = pg_test_function_source('ws_change_viewer_from_row');
	pg_assert_contains("'manage_emails'", $from_row);

	$api_page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/pages.php');
	pg_assert_contains("api_webhook_enqueue('page.updated'", $api_page, 'a page changed through the API is queued, which keeps it for the Workspace');
	pg_assert_false(strpos($api_page, "pg_event_record('page.updated'") !== false, 'no second record: api_webhook_enqueue() keeps the event already');
}
