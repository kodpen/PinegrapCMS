<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The room of a mailbox kept in no channel (a ws_channels row of kind
 * "mailbox", includes/workspace/mail_room.php) is no place among the
 * channels: no channel list, picker or search shows it, nothing is posted
 * into it and nobody manages it as a channel. These tests read every query
 * of the product that reads ws_channels and hold each to what it is listed
 * as in workspace_mail_room_hidden_test_readers(): a query that picks its
 * rows by id, one that names the kinds it reads, one that leaves the room
 * out by name, one that reads by a column the room never fills. A new
 * reader fails the test until it is listed, which is the moment to ask
 * whether it would show the room. The rules that do not live in a query
 * (who may post, manage, leave; the two message searches; the API's channel
 * lookup) are held beside them.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// What every reader of ws_channels is, one word for each of its queries in
// the order they are written, keyed "file::function" ("(file)" outside a
// function):
//   id       rows picked by id, or joined to rows that name a channel by id
//   kinds    names the kinds it reads; mailbox is not one of them
//   hidden   lists channels and leaves the room out by name
//   key:<c>  reads by a column the room never fills (a group, a contact) or
//            fills as any channel does (its folder)
//   by:<why> anything else, with the reason
function workspace_mail_room_hidden_test_readers()
{
	return array(
		// A file's channel by its folder, and only a public one opens it to
		// the team; a shared note's public channel; an old file's channel.
		'get_file.php::(file)'                                             => array('key:folder_id', 'kinds', 'id'),
		'includes/dashboard/v2/cards/ai.php::pg_dashboard_v2_ai_channels'  => array('kinds'),
		// Every channel's folder is one, the room's as well.
		'includes/fn/image.php::pg_image_warmup_fetch'                     => array('key:folder_id'),
		'includes/fn/privacy_export.php::pg_privacy_export_collect'        => array('key:contact_id'),
		'includes/workspace/access.php::ws_channel'                        => array('id'),
		'includes/workspace/agent_activity.php::ws_agent_activity_open'    => array('id'),
		'includes/workspace/agent_queue.php::ws_agent_brief'               => array('id'),
		'includes/workspace/ai.php::ws_ai_settings_card'                   => array('id'),
		'includes/workspace/api_resources.php::ws_api_channel_groups_delete' => array('key:group_id'),
		'includes/workspace/api_resources.php::ws_api_message_threads'     => array('id'),
		'includes/workspace/api_threads.php::ws_api_threads_list'          => array('kinds', 'id'),
		'includes/workspace/calendar.php::ws_calendar'                     => array('hidden'),
		'includes/workspace/channel_memory.php::ws_memory_sweep'           => array('id'),
		'includes/workspace/channels.php::ws_channel_name_taken'           => array('hidden'),
		'includes/workspace/channels.php::ws_channel_place'                => array('hidden'),
		'includes/workspace/channels.php::ws_channels_for'                 => array('hidden'),
		'includes/workspace/channels.php::ws_contact_channels'             => array('key:contact_id'),
		'includes/workspace/channels.php::ws_ensure_first_channel'         => array('hidden'),
		'includes/workspace/channels.php::ws_private_channels_for_audit'   => array('kinds'),
		'includes/workspace/claude.php::ws_claude_settings_card'           => array('id'),
		'includes/workspace/claude.php::ws_claude_summary_channels'        => array('kinds'),
		'includes/workspace/customer.php::ws_customer_channels'            => array('by:the customer a channel is about (contact_id, customer_id), which a room never has'),
		'includes/workspace/eras.php::ws_channel_floor'                    => array('id'),
		'includes/workspace/files.php::ws_channel_folder_id'               => array('id'),
		// Every channel's folder is one, the room's as well.
		'includes/workspace/files.php::ws_root_folder_id'                  => array('key:folder_id'),
		'includes/workspace/flow_mail_person.php::ws_flow_mail_person'     => array('id'),
		'includes/workspace/flows.php::ws_flows_channel_names'             => array('id'),
		'includes/workspace/flows.php::ws_flows_nav_visible'               => array('kinds', 'kinds'),
		'includes/workspace/groups.php::ws_group_channel_ids'              => array('key:group_id'),
		'includes/workspace/groups.php::ws_group_reach'                    => array('key:group_id'),
		'includes/workspace/groups.php::ws_group_remove'                   => array('key:group_id'),
		'includes/workspace/groups.php::ws_group_sync_channel'             => array('id'),
		'includes/workspace/groups.php::ws_group_sync_plan'                => array('id'),
		'includes/workspace/groups.php::ws_groups_for'                     => array('key:group_id'),
		'includes/workspace/groups.php::ws_webhook_event_groups'           => array('id'),
		'includes/workspace/home.php::ws_home_joinable'                    => array('kinds'),
		'includes/workspace/refs.php::ws_ref_search'                       => array('hidden'),
		'includes/workspace/refs.php::ws_refs_resolve'                     => array('id'),
		'includes/workspace/scheduled.php::ws_scheduled_execute'           => array('id'),
		'includes/workspace/scheduled.php::ws_scheduled_fill'              => array('id'),
		'includes/workspace/scheduled.php::ws_scheduled_lead'              => array('key:contact_id'),
		'includes/workspace/scheduled_draft.php::ws_scheduled_draft_lists' => array('kinds'),
		'includes/workspace/steps.php::ws_step_trigger_values_for'         => array('id'),
		// A discussion and the channel it is in: nothing is posted in a
		// room, so none starts there.
		'includes/workspace/threads.php::ws_threads_for'                   => array('kinds', 'kinds'),
		'includes/workspace/threads.php::ws_threads_map'                   => array('id'),
		'includes/workspace/threads.php::ws_threads_purge'                 => array('id', 'id'),
		'includes/workspace/timeline.php::ws_timeline_channels'            => array('hidden'),
		'includes/workspace/timeline.php::ws_timeline_hidden'              => array('kinds'),
		'includes/workspace/watch.php::ws_events_channels'                 => array('kinds'),
		'includes/workspace/watch.php::ws_watch_channels'                  => array('kinds'),
		'includes/workspace/wtable_shares.php::ws_wtable_event_channels'   => array('kinds'),
	);
}

// Every query of the product that reads ws_channels: "file::function" =>
// the text of each, from the quote its string opens with to the call's end.
// Language files, migrations, the installer and vendored libraries have no
// reader to list.
function workspace_mail_room_hidden_test_found()
{
	$root = PG_FUNCTIONS_DIR;
	$skip = array('includes/local/', 'includes/migrations/', 'install/', 'includes/phpmailer/', 'includes/stripe/', 'includes/iyzipay-php/',
		'includes/phpexcel/', 'includes/boxpacker/', 'includes/dompdf/', 'assets/', 'data/', 'files/');
	$found = array();

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
		$path = str_replace('\\', '/', substr((string) $file, strlen($root) + 1));

		if (substr($path, -4) !== '.php') {
			continue;
		}

		foreach ($skip as $prefix) {
			if (strpos($path, $prefix) === 0) {
				continue 2;
			}
		}

		$source = (string) file_get_contents((string) $file);

		if (!preg_match_all('/\b(?:FROM|JOIN)\s+ws_channels\b/', $source, $reads, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		preg_match_all('/^function\s+(\w+)\s*\(/m', $source, $functions, PREG_OFFSET_CAPTURE);

		foreach ($reads[0] as $read) {
			$function = '(file)';

			foreach ($functions[1] as $candidate) {
				if ($candidate[1] < $read[1]) {
					$function = $candidate[0];
				}
			}

			$start = strrpos(substr($source, 0, $read[1]), '"');
			$end = strpos($source, '")', $read[1]);
			$found[$path . '::' . $function][] = preg_replace('/\s+/', ' ', substr($source, (int) $start, ($end === false) ? 600 : $end - (int) $start));
		}
	}

	ksort($found);

	return $found;
}

// '' when a query is what it is listed as, else what is wrong with it.
function workspace_mail_room_hidden_test_wrong($class, $query)
{
	if ($class === 'id') {
		return preg_match('/(?<![a-z_.])id (?:=|IN) |\b[a-z]+\.id = [a-z]+\.(?:channel_id|parent_channel_id|target_id)\b/', $query) ? '' : 'does not pick its rows by id';
	}

	if ($class === 'kinds') {
		return (preg_match('/\bkind (?:= \'|IN \()/', $query) && (strpos($query, 'mailbox') === false)) ? '' : 'does not name the kinds it reads, or names mailbox';
	}

	if ($class === 'hidden') {
		return preg_match('/\bkind (?:<> \'mailbox\'|NOT IN \(\'thread\', \'mailbox\'\))/', $query) ? '' : 'does not leave the room out';
	}

	if (strpos($class, 'key:') === 0) {
		return (strpos($query, substr($class, 4)) !== false) ? '' : 'does not read by ' . substr($class, 4);
	}

	return ((strpos($class, 'by:') === 0) && (strlen($class) > 10)) ? '' : 'has no reason';
}

function test_workspace_mail_room_hidden_every_reader_is_listed()
{
	$listed = workspace_mail_room_hidden_test_readers();
	$found = workspace_mail_room_hidden_test_found();

	pg_assert_true(count($found) > 40, 'the readers were found');

	foreach ($found as $reader => $queries) {
		pg_assert_true(isset($listed[$reader]), $reader . ' reads ws_channels and is not listed: would it show the room of a mailbox kept in no channel?');
		pg_assert_same(count($queries), count($listed[$reader] ?? $queries), $reader . ': one word for each of its queries');
	}

	foreach (array_keys($listed) as $reader) {
		pg_assert_true(isset($found[$reader]), $reader . ' is listed and reads ws_channels no more');
	}
}

function test_workspace_mail_room_hidden_every_reader_is_what_it_is_listed_as()
{
	$found = workspace_mail_room_hidden_test_found();

	foreach (workspace_mail_room_hidden_test_readers() as $reader => $classes) {
		foreach ($classes as $index => $class) {
			pg_assert_same('', isset($found[$reader][$index]) ? workspace_mail_room_hidden_test_wrong($class, $found[$reader][$index]) : 'is not there', $reader . ' #' . ($index + 1) . ' as ' . $class);
		}
	}
}

function test_workspace_mail_room_hidden_the_check_tells_the_classes_apart()
{
	pg_assert_same('', workspace_mail_room_hidden_test_wrong('id', '"SELECT * FROM ws_channels WHERE id = \'" . $id . "\''));
	pg_assert_same('', workspace_mail_room_hidden_test_wrong('id', '"SELECT r.* FROM ws_ai_requests r LEFT JOIN ws_channels c ON c.id = r.channel_id'));
	pg_assert_true(workspace_mail_room_hidden_test_wrong('id', '"SELECT * FROM ws_channels WHERE folder_id = \'3\' AND m.channel_id = c.id') !== '', 'a folder, and the members joined to the channel, are no id');
	pg_assert_true(workspace_mail_room_hidden_test_wrong('kinds', '"SELECT id FROM ws_channels WHERE kind <> \'thread\'') !== '', 'every kind but one names none');
	pg_assert_true(workspace_mail_room_hidden_test_wrong('kinds', '"SELECT id FROM ws_channels WHERE kind IN (\'private\', \'mailbox\')') !== '', 'the room among the kinds');
	pg_assert_true(workspace_mail_room_hidden_test_wrong('hidden', '"SELECT id FROM ws_channels WHERE kind <> \'thread\'') !== '', 'a list that forgot the room');
	pg_assert_same('', workspace_mail_room_hidden_test_wrong('hidden', '"SELECT id FROM ws_channels c WHERE c.kind NOT IN (\'thread\', \'mailbox\')'));
	pg_assert_true(workspace_mail_room_hidden_test_wrong('by:', '"SELECT 1 FROM ws_channels') !== '', 'a reason is asked for');
}

function test_workspace_mail_room_hidden_nothing_is_posted_and_nobody_manages()
{
	$owner = array('id' => 5, 'role' => 0, 'member' => true);
	$room = array('id' => 9, 'kind' => 'mailbox', 'owner_user_id' => 5, 'archived_at' => 0);

	pg_assert_false(ws_can_manage_channel($owner, $room), 'not even an administrator named as its owner');
	pg_assert_false(ws_can_read_channel(array('id' => 5, 'role' => 0, 'member' => false), $room), 'team accounts only');

	// Who is in a room is read from the database: the rest is held in source.
	$read = pg_test_function_source('ws_can_read_channel');
	$post = pg_test_function_source('ws_can_post_channel');

	pg_assert_contains("if (\$channel['kind'] === 'mailbox') {\n        return (bool) ws_channel_membership(\$channel['id'], \$rights['id']);", $read, 'read by its members, never opened for inspection');
	pg_assert_true(strpos($read, "'mailbox'") < strpos($read, 'ws_audit_open('), 'the room answers before the inspection');
	pg_assert_contains("if (\$channel['kind'] === 'mailbox') {\n        return false;", $post, 'nothing is posted into a room');
	pg_assert_contains('ws_channel_is_mailbox($channel)', pg_test_function_source('ws_channel_leave'), 'nobody leaves a room: its people are chosen on the mailbox');
}

function test_workspace_mail_room_hidden_from_search_the_api_and_the_channels_screen()
{
	pg_assert_contains('!ws_channel_is_mailbox($channels[$id])', pg_test_function_source('ws_message_search'), 'the message search');
	pg_assert_contains('!ws_channel_is_mailbox($channels[$id])', pg_test_function_source('ws_palette_message_rows'), 'the palette\'s searches');
	pg_assert_contains('ws_channel_is_mailbox($channel) ||', pg_test_function_source('ws_api_channel_or_404'), 'no channel to an application');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$open = substr($actions, (int) strpos($actions, "case 'ws_channel_open':"), 900);

	pg_assert_contains('if (ws_channel_is_mailbox($channel)) {', $open, 'a room never opens as a channel');
	pg_assert_contains('go($ws_mail_room_url);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace.php'), 'its address leads to the E-mails screen');
}
