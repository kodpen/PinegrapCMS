<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for campaign profiles over the external API (/campaigns/profiles)
 * and the write path the profile screens share with it
 * (includes/fn/email_campaign_profiles.php): the route rows and their
 * scopes, the action list against the form's, partial updates laid over the
 * stored row with the action settling item, period and base, the schedule
 * time, the user-role row rule, the screens calling the shared write.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/campaign_profiles.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_campaign_profiles_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function api_campaign_profiles_test_row()
{
	return array(
		'id' => '7',
		'name' => 'Reminder',
		'enabled' => '1',
		'action' => 'calendar_event_reserved',
		'action_item_id' => '12',
		'subject' => 'See you soon',
		'format' => 'plain_text',
		'body' => 'Hello',
		'page_id' => '0',
		'from_name' => 'Shop',
		'from_email_address' => 'shop@example.com',
		'reply_email_address' => '',
		'bcc_email_address' => '',
		'schedule_time' => '09:30:00',
		'schedule_length' => '1',
		'schedule_unit' => 'days',
		'schedule_period' => 'before',
		'schedule_base' => 'calendar_event_start_time',
		'purpose' => 'transactional',
		'created_user_id' => '3',
		'created_username' => 'erdal',
		'created_timestamp' => '1700000000',
		'last_modified_timestamp' => '1700000100',
	);
}

function test_api_campaign_profiles_routes()
{
	$expected = array(
		'campaigns.profiles.list'        => array('GET', '/campaigns/profiles', 'campaigns:read'),
		'campaigns.profiles.get'         => array('GET', '/campaigns/profiles/{id}', 'campaigns:read'),
		'campaigns.profiles.create'      => array('POST', '/campaigns/profiles', 'campaigns:write'),
		'campaigns.profiles.update'      => array('POST', '/campaigns/profiles/{id}', 'campaigns:write'),
		'campaigns.profiles.delete'      => array('DELETE', '/campaigns/profiles/{id}', 'campaigns:write'),
		'campaigns.profiles.delete_post' => array('POST', '/campaigns/profiles/{id}/delete', 'campaigns:write'),
	);

	$found = array();

	foreach (api_schema() as $route) {
		if (!isset($expected[$route['id']])) {
			continue;
		}

		$found[] = $route['id'];
		$row = $expected[$route['id']];

		pg_assert_same($row[0], $route['method'], $route['id'] . ' method');
		pg_assert_same($row[1], $route['path'], $route['id'] . ' path');
		pg_assert_same($row[2], $route['scope'], $route['id'] . ' scope');
		pg_assert_true(function_exists($route['handler']), $route['handler'] . '() exists');

		if ($route['method'] !== 'GET') {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}

		// The action lists of the routes are the form's own, so a new action
		// added to the form is not refused by the router.
		foreach ($route['params'] as $param) {
			if ($param['name'] === 'action') {
				pg_assert_same(api_campaign_profile_actions(), $param['values'], $route['id'] . ' action values');
			}
		}
	}

	pg_assert_same(count($expected), count($found), 'every profile route is declared');
}

// A text field's max_length fits its varchar column.
function test_api_campaign_profiles_text_limits()
{
	$limits = array('name' => 100, 'subject' => 255, 'from_name' => 100, 'from_email' => 100, 'reply_to' => 100, 'bcc' => 100);

	foreach (api_schema() as $route) {
		if (!in_array($route['id'], array('campaigns.profiles.create', 'campaigns.profiles.update'), true)) {
			continue;
		}

		foreach ($route['params'] as $param) {
			if (isset($limits[$param['name']])) {
				pg_assert_same($limits[$param['name']], $param['max_length'] ?? null, $route['id'] . ' ' . $param['name'] . ' fits its column');
			}
		}
	}
}

function test_api_campaign_profile_time()
{
	pg_assert_same('09:30:00', api_campaign_profile_time_in('09:30'), 'a time');
	pg_assert_same('00:01:00', api_campaign_profile_time_in('00:00'), 'midnight is kept apart from any time');
	pg_assert_same('00:00:00', api_campaign_profile_time_in(''), 'empty is any time');
	pg_assert_same('00:00:00', api_campaign_profile_time_in(null), 'null is any time');
	pg_assert_same(null, api_campaign_profile_time_in('9:30'), 'one-digit hour');
	pg_assert_same(null, api_campaign_profile_time_in('24:00'), 'no hour 24');
	pg_assert_same(null, api_campaign_profile_time_in('9:30 AM'), 'no 12-hour clock');
	pg_assert_same('09:30', api_campaign_profile_time_out('09:30:00'), 'stored time out');
	pg_assert_same(null, api_campaign_profile_time_out('00:00:00'), 'any time out');
}

function test_api_campaign_profile_values_partial_update()
{
	$row = api_campaign_profiles_test_row();

	$values = api_campaign_profile_values($row, array('subject' => ' New ', 'enabled' => false));
	pg_assert_same('New', $values['subject'], 'subject sent');
	pg_assert_same('0', $values['enabled'], 'disabled');
	pg_assert_same('12', $values['action_item_id'], 'item kept');
	pg_assert_same('before', $values['schedule_period'], 'calendar period kept');
	pg_assert_same('calendar_event_start_time', $values['schedule_base'], 'calendar base kept');
	pg_assert_same('09:30:00', $values['schedule_time'], 'time kept');
	pg_assert_same('', pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, false), 'the form accepts it');
	pg_assert_same(pg_email_campaign_profile_columns(), array_keys($values), 'every column, in table order');

	// Another action sends after the action and drops the calendar event.
	$values = api_campaign_profile_values($row, array('action' => 'order_completed'));
	pg_assert_same('0', $values['action_item_id'], 'commerce action ties to no record');
	pg_assert_same('after', $values['schedule_period'], 'after');
	pg_assert_same('action', $values['schedule_base'], 'the action');
	pg_assert_true(pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, false) !== '', 'commerce action without the right');
	pg_assert_same('', pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, true), 'commerce action with the right');

	$values = api_campaign_profile_values($row, array('schedule_time' => '', 'reply_to' => '', 'page_id' => ''));
	pg_assert_same('00:00:00', $values['schedule_time'], 'time cleared');
	pg_assert_same('0', $values['page_id'], 'page cleared');

	$values = api_campaign_profile_values($row, array('schedule_period' => 'before', 'schedule_base' => 'action'));
	pg_assert_true(pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, false) !== '', 'before the action is refused');
}

function test_api_campaign_profile_values_create()
{
	$values = api_campaign_profile_values(null, array(
		'name' => 'Thanks',
		'action' => 'custom_form_submitted',
		'action_item_id' => 44,
		'subject' => 'Thanks',
		'from_name' => 'Shop',
		'from_email' => 'shop@example.com',
	));

	pg_assert_same('1', $values['enabled'], 'a new profile is enabled');
	pg_assert_same('44', $values['action_item_id'], 'form page');
	pg_assert_same('plain_text', $values['format'], 'plain text');
	pg_assert_same('commercial', $values['purpose'], 'commercial');
	pg_assert_same('after', $values['schedule_period'], 'after');
	pg_assert_same('', pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, false), 'the form accepts it');

	$values = api_campaign_profile_values(null, array('name' => 'X', 'action' => 'custom_form_submitted', 'from_email' => 'shop@example.com'));
	pg_assert_true(pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, false) !== '', 'an item action needs its item');

	$values = api_campaign_profile_values(null, array('name' => 'X', 'action' => 'order_completed', 'format' => 'html', 'from_email' => 'shop@example.com'));
	pg_assert_true(pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, true) !== '', 'html needs a page');
}

function test_api_campaign_profile_present()
{
	$row = api_campaign_profiles_test_row();

	$profile = api_campaign_profile_present($row);
	pg_assert_same(7, $profile['id'], 'id');
	pg_assert_same(true, $profile['enabled'], 'enabled');
	pg_assert_same(12, $profile['action_item_id'], 'item');
	pg_assert_same(null, $profile['page_id'], 'no page for plain text');
	pg_assert_same(null, $profile['reply_to'], 'no reply-to');
	pg_assert_same('09:30', $profile['schedule_time'], 'time');
	pg_assert_same(array_keys(api_campaign_profile_schema()), array_keys($profile), 'the declared fields');

	$profile = api_campaign_profile_present(array('action' => 'order_completed', 'action_item_id' => '0') + $row);
	pg_assert_same(null, $profile['action_item_id'], 'no item for an order action');
}

function test_api_campaign_profile_owner_filter()
{
	pg_assert_same('', api_campaign_profile_owner_filter(array('id' => 5, 'role' => 2)), 'manager reaches every profile');
	pg_assert_same("email_campaign_profiles.created_user_id = '5'", api_campaign_profile_owner_filter(array('id' => 5, 'role' => 3)), 'user role reaches its own');
}

function test_api_campaign_profile_screens_share_write()
{
	$add = api_campaign_profiles_test_source('add_email_campaign_profile.php');
	$edit = api_campaign_profiles_test_source('edit_email_campaign_profile.php');

	pg_assert_contains('pg_email_campaign_profile_save(0,', $add, 'create screen writes through the shared function');
	pg_assert_contains('pg_email_campaign_profile_save($id,', $edit, 'edit screen writes through the shared function');
	pg_assert_contains('pg_email_campaign_profile_delete($id)', $edit, 'edit screen deletes through the shared function');

	foreach (array('add' => $add, 'edit' => $edit) as $screen => $source) {
		pg_assert_false(stripos($source, 'INSERT INTO email_campaign_profiles') !== false, $screen . ' keeps no insert');
		pg_assert_false(stripos($source, 'UPDATE email_campaign_profiles') !== false, $screen . ' keeps no update');
		pg_assert_false(stripos($source, 'DELETE FROM email_campaign_profiles') !== false, $screen . ' keeps no delete');
	}

	// The API checks access from its owner, never from the session constants
	// it does not define.
	$fn = api_campaign_profiles_test_source('includes/fn/email_campaign_profiles.php');
	pg_assert_false(strpos($fn, 'USER_ROLE') !== false, 'no USER_ROLE in the shared module');
	pg_assert_false(strpos($fn, 'USER_ID') !== false, 'no USER_ID in the shared module');
	pg_assert_false(strpos($fn, 'check_view_access(') !== false, 'no session view check');
}
