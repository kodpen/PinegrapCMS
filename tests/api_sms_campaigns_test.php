<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /campaigns/sms (includes/api/resources/campaigns.php) and the
 * campaign.sent event: the owner rule, the answer's shape, the queue
 * reference read, the payload and the places that announce it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/campaigns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

// The manager role up reads every campaign, the user role its own, as on My
// SMS Campaigns.
function test_api_sms_campaigns_owner_rule()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_same('', api_sms_campaign_owner_filter(array('id' => 7, 'role' => $role)), 'role ' . $role);
	}

	pg_assert_same("sms_campaigns.created_by = '7'", api_sms_campaign_owner_filter(array('id' => '7', 'role' => 3)), 'user role');

	$route = null;

	foreach (api_schema() as $row) {
		if ($row['id'] === 'campaigns.sms.list') {
			$route = $row;
		}
	}

	pg_assert_true($route !== null, 'the route is declared');
	pg_assert_same('campaigns:read', $route['scope'], 'scope');
	pg_assert_same('/campaigns/sms', $route['path'], 'path');
}

// A queued campaign carries the queue's figures; any other has none.
function test_api_sms_campaigns_present()
{
	$row = array(
		'id' => '4', 'name' => 'Sale', 'body' => 'Today only', 'status' => 'queued', 'start_at' => '0',
		'queued_at' => '0', 'queued_count' => '120', 'refused_count' => '3', 'created_username' => '',
		'created_at' => '0', 'updated_at' => '0',
	);

	$queued = api_sms_campaign_present($row, array('included' => array(2, 5)), array('sent' => 100, 'queued' => 10, 'sending' => 2, 'failed' => 7, 'uncertain' => 1));

	pg_assert_same(array('sent' => 100, 'waiting' => 12, 'failed' => 8), $queued['outbox'], 'outbox');
	pg_assert_same(array(2, 5), $queued['contact_group_ids'], 'included groups');
	pg_assert_same(array(), $queued['excluded_contact_group_ids'], 'no excluded group');
	pg_assert_same(120, $queued['queued'], 'queued');
	pg_assert_same(3, $queued['refused'], 'refused');
	pg_assert_same(null, $queued['created_by'], 'no user name');

	$draft = api_sms_campaign_present(array('status' => 'draft') + $row, array(), array());
	pg_assert_same(null, $draft['outbox'], 'a draft has no queue figures');

	pg_assert_same(array_keys(api_sms_campaign_schema()), array_keys($queued), 'the declaration lists every field');
}

// The campaign id is read from the queue reference only.
function test_api_sms_campaigns_reference_id()
{
	pg_assert_same(12, api_sms_campaign_reference_id('smscampaign:12:'), 'grouped reference');
	pg_assert_same(12, api_sms_campaign_reference_id('smscampaign:12:99'), 'full reference');
	pg_assert_same(0, api_sms_campaign_reference_id('order:12:99'), 'another kind');
	pg_assert_same(0, api_sms_campaign_reference_id(''), 'empty');
}

// campaign.sent carries identifiers only, is in the catalogue, and is
// announced where an e-mail campaign completes and an SMS campaign is queued.
function test_api_sms_campaigns_sent_event()
{
	pg_assert_same(array('id' => 3, 'channel' => 'sms', 'type' => 'manual'), pg_campaign_sent_event('sms', '3', 'manual'), 'sms');
	pg_assert_same(array('id' => 9, 'channel' => 'email', 'type' => 'automatic'), pg_campaign_sent_event('email', 9, 'automatic'), 'automatic e-mail');
	pg_assert_same('manual', pg_campaign_sent_event('email', 9, '')['type'], 'unknown type');

	$events = api_webhook_events();
	pg_assert_true(isset($events['campaign.sent']), 'in the catalogue');

	foreach (array('email_campaign_job.php', 'send_email_campaign.php', 'includes/fn/sms_campaigns.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_same(1, substr_count($source, "pg_announce('campaign.sent'"), $file . ' announces once');
	}
}

// includes/fn/sms_campaigns.php is not among the modules functions.php loads,
// so the handler requires it: without it the readiness check found no
// function and GET /campaigns/sms answered 503 on every site.
function test_api_sms_campaigns_list_loads_its_module()
{
	$functions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_false(strpos($functions, "/includes/fn/sms_campaigns.php'") !== false, 'functions.php does not load the module');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/campaigns.php');
	$start = strpos($source, 'function api_campaigns_sms_list(');
	$require = strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');", (int) $start);
	$ready = strpos($source, 'pg_sms_campaigns_ready()', (int) $start);

	pg_assert_true(($start !== false) && ($require !== false) && ($ready !== false) && ($require < $ready), 'required above the readiness check');
}
