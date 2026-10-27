<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for event subscriptions narrowed to a group of workspace channels
 * (api_webhooks.channel_group_id, 2026.4.10, 10.76) that need no database:
 * which channel an event happened in, that every workspace event names one
 * (or, for an event about a group, the group), that the three webhook writes
 * take the group, that removing a group switches its subscriptions off, and
 * what the events about the groups themselves carry and where they go.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_events.php');

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

	// A discussion is a channel in no group: it is heard where the channel
	// it is about is.
	pg_assert_same(7, ws_webhook_event_channel_id(array('id' => 3, 'channel_id' => 40, 'thread' => array('channel_id' => 7, 'message_id' => 9))), 'a discussion: the channel it is about');
	pg_assert_same(7, ws_webhook_event_channel_id(array('channel_id' => 40, 'thread' => array('channel_id' => '7'))), 'an id read as text');
	pg_assert_same(40, ws_webhook_event_channel_id(array('channel_id' => 40, 'thread' => null)), 'no discussion');
	pg_assert_same(40, ws_webhook_event_channel_id(array('channel_id' => 40, 'thread' => array('channel_id' => 0))), 'a discussion of no channel');
	pg_assert_same(40, ws_webhook_event_channel_id(array('channel_id' => 40, 'thread' => array('channel_id' => array(7)))), 'a list is no channel');
	pg_assert_same(40, ws_webhook_event_channel_id(array('channel_id' => 40, 'thread' => 'x')), 'a discussion that is not an object');
}

// An order or a product happens in no channel: a narrowed subscription is
// never matched against it, and the database is not asked.
function test_webhook_channel_group_other_events_name_no_group()
{
	pg_assert_same(array(), ws_webhook_event_groups('order.created', array('channel_id' => 4)));
	pg_assert_same(array(), ws_webhook_event_groups('webhook.test', array('webhook_id' => 1)));
}

// Every event a narrowed subscription may listen to says which channel it
// happened in, or a subscriber to a group would never hear it. An event about
// a group itself is announced through ws_group_announce(), which carries the
// group, and is matched by the group rather than by a channel.
function test_webhook_channel_group_every_workspace_event_names_its_channel()
{
	$sites = array();
	$groups = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$source = file_get_contents($file);

		if (preg_match_all("~pg_announce\('(workspace\.[a-z_.]+)',~", $source, $found, PREG_OFFSET_CAPTURE)) {
			foreach ($found[1] as $match) {
				$rest = substr($source, $match[1]);
				$end = strpos($rest, '));');
				$sites[$match[0]][] = ($end === false) ? $rest : substr($rest, 0, $end);
			}
		}

		if (preg_match_all("~ws_group_announce\('(workspace\.[a-z_.]+)',~", $source, $found)) {
			foreach ($found[1] as $event) {
				$groups[$event] = true;
			}
		}
	}

	foreach (array_keys(ws_webhook_events()) as $event) {
		if (strpos($event, 'workspace.channel_group.') === 0) {
			pg_assert_true(isset($groups[$event]) && empty($sites[$event]), $event . ' is announced with its group');
			continue;
		}

		pg_assert_true(!empty($sites[$event]), $event . ' is announced');

		foreach ((array) ($sites[$event] ?? array()) as $payload) {
			pg_assert_true((strpos($payload, "'channel_id'") !== false) || (strpos($payload, 'ws_task_event_payload(') !== false)
				|| (strpos($payload, 'ws_app_mention_payload(') !== false) || (strpos($payload, 'ws_flow_run_event_body(') !== false), $event . ' names its channel');
		}
	}

	foreach (array_keys($groups) as $event) {
		pg_assert_true(array_key_exists($event, ws_webhook_events()), $event . ' is in the catalogue');
	}

	pg_assert_contains("'channel_id'", webhook_channel_group_test_source('ws_task_event_payload'), 'a task event carries its channel');
	pg_assert_contains("'channel_id'", webhook_channel_group_test_source('ws_app_mention_payload'), 'a mention carries its channel');
	pg_assert_contains("'channel_id'", webhook_channel_group_test_source('ws_flow_run_event_body'), 'a flow run event carries its channel');
	pg_assert_contains('ws_group_event_payload($group)', webhook_channel_group_test_source('ws_group_announce'), 'a group event carries its group');
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

// The four events about a group are workspace events, so a subscription
// narrowed to a group may listen to them; the names fit the queue's column.
function test_webhook_channel_group_group_events_are_listed()
{
	$events = ws_webhook_events();

	foreach (array('created', 'updated', 'access_changed', 'deleted') as $what) {
		$event = 'workspace.channel_group.' . $what;

		pg_assert_true(isset($events[$event]), $event . ' is listed');
		pg_assert_true(strlen($event) <= 50, $event . ' fits api_webhook_queue.event');
	}
}

// A group as its events carry it: the fields of WorkspaceChannelGroup, null
// at the top and for no colour, the path ending in its own name.
function test_webhook_channel_group_event_payload()
{
	$top = ws_group_event_payload(array('id' => '4', 'name' => 'Sales', 'parent_id' => '0', 'color' => '0'));

	pg_assert_same(array('id' => 4, 'name' => 'Sales', 'parent_id' => null, 'path' => 'Sales', 'color' => null), $top);

	$inside = ws_group_event_payload(array('id' => 5, 'name' => 'Istanbul', 'parent_id' => 4, 'color' => 1), 'Customers › Sales');

	pg_assert_same(4, $inside['parent_id'], 'the group above');
	pg_assert_same(ws_palette_hex(1), $inside['color'], 'the colour as the API gives it');
	pg_assert_same('Customers › Sales › Istanbul', $inside['path'], 'the path of the group above, then its own name');
	pg_assert_same('Istanbul', ws_group_event_payload(array('id' => 5, 'name' => 'Istanbul', 'parent_id' => 4), '')['path'], 'a group above that is gone');
}

// Only a name, a colour or a parent that really changed is announced, and
// in a fixed order.
function test_webhook_channel_group_event_changes()
{
	$was = array('id' => 5, 'name' => 'Istanbul', 'parent_id' => 4, 'path' => 'Sales › Istanbul', 'color' => null);

	pg_assert_same(array(), ws_group_event_changes($was, $was), 'saved unchanged');
	pg_assert_same(array(), ws_group_event_changes($was, array('path' => 'Customers › Istanbul') + $was), 'a group above renamed');
	pg_assert_same(array('name'), ws_group_event_changes($was, array('name' => 'Ankara') + $was));
	pg_assert_same(array('parent_id', 'color'), ws_group_event_changes($was, array('parent_id' => null, 'color' => '#b5534b') + $was));
	pg_assert_same(array('parent_id'), ws_group_event_changes($was, array('parent_id' => '4') + $was), 'a parent id read as text is a change of type, never sent');
}

// An event about a group is matched by the group itself and the groups
// above it, a group moved by the groups it left too, and a group taken away
// by the groups that moved up out of it. A group at the top is matched by
// itself alone, without reading the groups.
function test_webhook_channel_group_group_event_groups()
{
	pg_assert_same(array(5), ws_webhook_group_event_groups(array('id' => 5, 'parent_id' => null, 'previous' => array('parent_id' => null))));
	pg_assert_same(array(5), ws_webhook_group_event_groups(array('id' => '5', 'previous' => 'x')), 'previous that is not an object');
	pg_assert_same(array(5, 8, 9), ws_webhook_group_event_groups(array('id' => 5, 'parent_id' => null, 'group_ids' => array(8, '9', 0, 5))), 'a group taken away: the groups that moved up out of it');
	pg_assert_same(array(5), ws_webhook_group_event_groups(array('id' => 5, 'group_ids' => 'x')), 'group_ids that is not a list');
	pg_assert_same(array(), ws_webhook_group_event_groups(array('id' => 0)), 'no group');
	pg_assert_same(array(), ws_webhook_group_event_groups(array()), 'nothing');

	$groups = webhook_channel_group_test_source('ws_webhook_event_groups');

	pg_assert_true(strpos($groups, "'workspace.channel_group.'") < strpos($groups, 'ws_webhook_event_channel_id('), 'a group event is not looked up by channel');

	$matched = webhook_channel_group_test_source('ws_webhook_group_event_groups');

	pg_assert_contains("array(\$payload['parent_id'] ?? 0, \$previous['parent_id'] ?? 0)", $matched, 'the groups above it and the ones it left');
	pg_assert_contains('ws_group_chain((int) $parent_id)', $matched);
}

// Each of the five functions the channel list and the API share announces
// its event, after the change; access is announced only for the people whose
// access really changed, and a removed group only once its own narrowed
// subscriptions are switched off.
function test_webhook_channel_group_group_events_are_announced()
{
	$save = webhook_channel_group_test_source('ws_group_save');

	pg_assert_contains("ws_group_announce('workspace.channel_group.created'", $save);
	pg_assert_contains('ws_group_announce_update($previous', $save);
	pg_assert_true(strpos($save, '$previous = ') < strpos($save, 'UPDATE ws_channel_groups'), 'save: read before the change');

	$move = webhook_channel_group_test_source('ws_group_move');

	pg_assert_contains('ws_group_announce_update($previous', $move);
	pg_assert_true(strpos($move, '$previous = ') < strpos($move, 'UPDATE ws_channel_groups'), 'move: read before the change');

	$remove = webhook_channel_group_test_source('ws_group_remove');

	pg_assert_true(strpos($remove, "ws_group_announce('workspace.channel_group.deleted'") > strpos($remove, 'ws_webhook_group_removed('), 'removal: after the narrowed subscriptions are off');
	pg_assert_true(strpos($remove, '$holders = ') < strpos($remove, 'DELETE FROM ws_channel_group_access'), 'removal: who held access, read before it ends');

	$grant = webhook_channel_group_test_source('ws_group_access_grant');

	pg_assert_true(strpos($grant, '$added = ') < strpos($grant, 'INSERT IGNORE INTO ws_channel_group_access'), 'grant: who held it already, read before');
	pg_assert_contains("if (!empty(\$added)) {\n        ws_group_announce('workspace.channel_group.access_changed'", $grant);

	$revoke = webhook_channel_group_test_source('ws_group_access_revoke');

	pg_assert_true(strpos($revoke, '$removed = ') < strpos($revoke, 'DELETE FROM ws_channel_group_access'), 'revoke: who held it, read before');
	pg_assert_contains("if (!empty(\$removed)) {\n        ws_group_announce('workspace.channel_group.access_changed'", $revoke);

	pg_assert_same(null, ws_group_announce('workspace.channel_group.created', null), 'no group, nothing announced');
}
