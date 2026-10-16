<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for event subscriptions narrowed to a group of workspace channels
 * (api_webhooks.channel_group_id, 2026.4.10, 10.76) that need no database:
 * which channel an event happened in, that every workspace event names one,
 * that the three webhook writes take the group, and that removing a group
 * switches its subscriptions off.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function webhook_channel_group_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// A message, a poll, an approval or a template names its channel; a task
// event carries the task's; a note carries it inside the task.
function test_webhook_channel_group_event_channel()
{
	pg_assert_same(7, ws_webhook_event_channel_id(array('id' => 3, 'channel_id' => 7)), 'channel_id');
	pg_assert_same(7, ws_webhook_event_channel_id(array('id' => 3, 'task' => array('id' => 9, 'channel_id' => 7))), 'a note: the task\'s channel');
	pg_assert_same(0, ws_webhook_event_channel_id(array('id' => 3, 'channel_id' => 0, 'task' => array('channel_id' => 7))), 'the event\'s own channel_id wins');
	pg_assert_same(0, ws_webhook_event_channel_id(array('id' => 3)), 'no channel');
	pg_assert_same(0, ws_webhook_event_channel_id(array('channel_id' => -4)), 'never below 0');
	pg_assert_same(0, ws_webhook_event_channel_id(array('task' => 'x')), 'a task that is not an object');
}

// An order or a product happens in no channel: a narrowed subscription is
// never matched against it, and the database is not asked.
function test_webhook_channel_group_other_events_name_no_group()
{
	pg_assert_same(array(), ws_webhook_event_groups('order.created', array('channel_id' => 4)));
	pg_assert_same(array(), ws_webhook_event_groups('webhook.test', array('webhook_id' => 1)));
}

// Every event a narrowed subscription may listen to says which channel it
// happened in, or a subscriber to a group would never hear it.
function test_webhook_channel_group_every_workspace_event_names_its_channel()
{
	$sites = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$source = file_get_contents($file);

		if (!preg_match_all("~pg_announce\('(workspace\.[a-z_.]+)',~", $source, $found, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($found[1] as $match) {
			$rest = substr($source, $match[1]);
			$end = strpos($rest, '));');
			$sites[$match[0]][] = ($end === false) ? $rest : substr($rest, 0, $end);
		}
	}

	foreach (array_keys(ws_webhook_events()) as $event) {
		pg_assert_true(!empty($sites[$event]), $event . ' is announced');

		foreach ((array) ($sites[$event] ?? array()) as $payload) {
			pg_assert_true((strpos($payload, "'channel_id'") !== false) || (strpos($payload, 'ws_task_event_payload(') !== false), $event . ' names its channel');
		}
	}

	pg_assert_contains("'channel_id'", webhook_channel_group_test_source('ws_task_event_payload'), 'a task event carries its channel');
}

// Create, change and the change addressed as a POST take the group; 0 or
// null is the whole site.
function test_webhook_channel_group_routes_take_the_group()
{
	$taken = array();

	foreach (api_schema() as $route) {
		if (!in_array($route['id'], array('webhooks.create', 'webhooks.update', 'webhooks.update_post'), true)) {
			continue;
		}

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'channel_group_id') {
				$taken[] = $route['id'];

				pg_assert_same('body', $param['in'], $route['id'] . ': in the body');
				pg_assert_same('int', $param['type'], $route['id'] . ': a whole number');
				pg_assert_same(0, $param['min'], $route['id'] . ': 0 takes the narrowing off');
				pg_assert_true(!empty($param['nullable']), $route['id'] . ': null takes the narrowing off');
				pg_assert_true(empty($param['required']), $route['id'] . ': may be left out');
			}
		}
	}

	pg_assert_same(array('webhooks.create', 'webhooks.update', 'webhooks.update_post'), $taken);

	pg_assert_contains("'channel_group_id' => 'integer?'", file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php'), 'the answer says null for a whole-site subscription');
}

// The group and the events are checked before the rehearsal stops and
// before anything is written.
function test_webhook_channel_group_checked_before_the_write()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');

	foreach (array('function api_webhooks_create(' => 'INSERT INTO api_webhooks', 'function api_webhooks_update(' => 'UPDATE api_webhooks SET') as $start => $write) {
		$body = substr($source, (int) strpos($source, $start));
		$check = strpos($body, 'api_webhook_clean_channel_group(');
		$stop = strpos($body, 'api_dry_run_stop(');

		pg_assert_true(($check !== false) && ($stop !== false) && ($check < $stop), $start . ' checks the group before the rehearsal stops');
		pg_assert_true($stop < strpos($body, $write), $start . ' stops before it writes');
	}
}

// Removing a group switches the subscriptions narrowed to it off, after the
// group is gone, rather than leaving them to hear the channels where those
// went.
function test_webhook_channel_group_removal_switches_subscriptions_off()
{
	$remove = webhook_channel_group_test_source('ws_group_remove');

	pg_assert_true(strpos($remove, 'ws_webhook_group_removed(') > strpos($remove, 'DELETE FROM ws_channel_groups'), 'after the group is deleted');

	$removed = webhook_channel_group_test_source('ws_webhook_group_removed');

	pg_assert_contains("pg_schema_has('api_webhooks', 'channel_group_id')", $removed, 'asks for the column first');
	pg_assert_contains("SET status = 'disabled'", $removed, 'switched off, not moved up');
	pg_assert_same(0, ws_webhook_group_removed(0), 'no group, nothing to do');
}
