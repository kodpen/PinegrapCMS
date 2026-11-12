<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a workflow table that belongs to a channel (11.0.191;
 * includes/workspace/wtables.php, wtable_channel.php): what the channel's
 * rights give, that nobody owns such a table, the move from one home to
 * another, the lists and the bin, the flow gate, the row events, the screen
 * and the API.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function ws_wtable_channel_test_code($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// A team member who reads the channel and may write in it.
function ws_wtable_channel_test_facts($facts = array())
{
	return $facts + array('member' => true, 'read' => true, 'post' => true, 'manage' => false);
}

// The owner of a person's table moving it into a channel they may write in.
function ws_wtable_channel_test_move($facts = array())
{
	$refusal = ws_wtable_home_set_refusal($facts + array('ready' => true, 'access' => 'owner', 'same' => false, 'channel' => true, 'writable' => true, 'count' => 0));

	return ($refusal === null) ? '' : $refusal['error'] . '/' . $refusal['field'] . '/' . $refusal['status'];
}

// Whoever reads the channel reads its table, whoever may write there
// changes it, whoever may manage it deletes, shares and moves it.
function test_ws_wtable_channel_rights_give_the_access()
{
	pg_assert_same('edit', ws_wtable_home_access_level(ws_wtable_channel_test_facts()));
	pg_assert_same('view', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('post' => false))), 'an archived channel, or a private one opened for inspection');
	pg_assert_same('owner', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('manage' => true))));
	pg_assert_same('owner', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('manage' => true, 'post' => false))), 'its managers still decide about the table of an archived channel');
	pg_assert_same('', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('read' => false))), 'a private channel they are not in');
	pg_assert_same('', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('read' => false, 'manage' => true))), 'managing a channel one does not read gives nothing');
	pg_assert_same('', ws_wtable_home_access_level(ws_wtable_channel_test_facts(array('member' => false, 'manage' => true))), 'a site member or a guest');
	pg_assert_same('', ws_wtable_home_access_level(array()));

	pg_assert_same(array('public', 'private'), ws_wtable_home_kinds(), 'no discussion, guest room or mailbox holds a table');
}

function test_ws_wtable_channel_the_further_access_wins()
{
	pg_assert_same('edit', ws_wtable_access_higher('view', 'edit'));
	pg_assert_same('edit', ws_wtable_access_higher('edit', 'view'));
	pg_assert_same('view', ws_wtable_access_higher('', 'view'), 'a share reaches somebody the channel does not');
	pg_assert_same('owner', ws_wtable_access_higher('owner', 'edit'));
	pg_assert_same('', ws_wtable_access_higher('', ''));
	pg_assert_same('view', ws_wtable_access_higher('view', 'run'), 'a level that is none gives nothing');
	pg_assert_same('', ws_wtable_access_higher('admin', ''));
}

// The reader asks the channel first: no owner, no "owner is gone", no role.
function test_ws_wtable_channel_reader_asks_the_channel()
{
	$access = ws_wtable_channel_test_code('ws_wtable_access');

	pg_assert_true(strpos($access, 'ws_wtable_home($table)') !== false && strpos($access, 'ws_wtable_home($table)') < strpos($access, 'ws_wtable_owner_present('), 'the home is asked before the owner');
	pg_assert_contains('return ws_wtable_home_access($viewer, $table, $home);', $access);

	$home = ws_wtable_channel_test_code('ws_wtable_home_access');

	foreach (array('ws_can_read_channel($viewer, $home)', 'ws_can_post_channel($viewer, $home)', 'ws_can_manage_channel($viewer, $home)', 'ws_wtable_share_reach(') as $needle) {
		pg_assert_contains($needle, $home);
	}

	pg_assert_true(strpos($home, "['user_id']") === false, 'who made it gives nothing');
	pg_assert_true(strpos($home, "['role']") === false, 'no role of its own: the channel\'s rights decide');
	pg_assert_true(strpos($home, 'ws_wtable_owner_present(') === false, 'a maker who left takes nothing away');
}

// The reader on a public channel, which needs no membership row.
function test_ws_wtable_channel_reader_on_a_public_channel()
{
	$channel = array('id' => 4, 'kind' => 'public', 'archived_at' => 0, 'owner_user_id' => 9, 'name' => 'Sales');
	$table = array('id' => 7, 'user_id' => 5, 'channel_id' => 4);

	pg_assert_same('edit', ws_wtable_home_access(array('id' => 5, 'role' => 3, 'member' => true), $table, $channel), 'its maker writes as everybody does');
	pg_assert_same('edit', ws_wtable_home_access(array('id' => 6, 'role' => 3, 'member' => true), $table, $channel));
	pg_assert_same('owner', ws_wtable_home_access(array('id' => 9, 'role' => 3, 'member' => true), $table, $channel), 'the channel\'s owner');
	pg_assert_same('owner', ws_wtable_home_access(array('id' => 2, 'role' => 2, 'member' => true), $table, $channel), 'staff manage a public channel');
	pg_assert_same('', ws_wtable_home_access(array('id' => 9, 'role' => 3, 'member' => false), $table, $channel), 'outside the team');
	pg_assert_same('owner', ws_wtable_home_access(array('id' => 9, 'role' => 3, 'member' => true), $table, array('archived_at' => 50) + $channel));
}

// A person's own table has no home, whatever is asked first.
function test_ws_wtable_channel_no_home()
{
	pg_assert_same(null, ws_wtable_home(null));
	pg_assert_same(null, ws_wtable_home(array('id' => 7, 'user_id' => 5)));
	pg_assert_same(null, ws_wtable_home(array('id' => 7, 'user_id' => 5, 'channel_id' => 0)));
	pg_assert_same(null, ws_wtable_home_channel(0));
	pg_assert_false(ws_wtable_present(null));

	foreach (array(null, '', 0, '0') as $own) {
		pg_assert_true(ws_wtable_home_is_own($own), var_export($own, true));
	}

	foreach (array(4, '4', 'x', false, array()) as $channel) {
		pg_assert_false(ws_wtable_home_is_own($channel), var_export($channel, true));
	}
}

// Only whoever manages a table moves it, into a channel they may write in
// or to their own tables, while the new home has room.
function test_ws_wtable_channel_move_rule()
{
	pg_assert_same('', ws_wtable_channel_test_move());
	pg_assert_same('not_ready//503', ws_wtable_channel_test_move(array('ready' => false)));
	pg_assert_same('not_found//404', ws_wtable_channel_test_move(array('access' => '')));
	pg_assert_same('forbidden//403', ws_wtable_channel_test_move(array('access' => 'edit')), 'whoever writes in the channel does not move its table');
	pg_assert_same('forbidden//403', ws_wtable_channel_test_move(array('access' => 'view')));
	pg_assert_same('channel_unwritable/channel_id/422', ws_wtable_channel_test_move(array('writable' => false)), 'a channel they do not read, an archived one, a discussion');
	pg_assert_same('too_many_tables/channel_id/409', ws_wtable_channel_test_move(array('count' => ws_wtable_limits()['tables'])));
	pg_assert_same('', ws_wtable_channel_test_move(array('count' => ws_wtable_limits()['tables'] - 1)));
	pg_assert_same('', ws_wtable_channel_test_move(array('channel' => false, 'writable' => false)), 'out of the channel, to their own tables');
	pg_assert_same('too_many_tables/channel_id/409', ws_wtable_channel_test_move(array('channel' => false, 'count' => ws_wtable_limits()['tables'])), 'their own tables are full');
	pg_assert_same('', ws_wtable_channel_test_move(array('same' => true, 'writable' => false, 'count' => ws_wtable_limits()['tables'])), 'the home it has is no change');
	pg_assert_same('forbidden//403', ws_wtable_channel_test_move(array('same' => true, 'access' => 'edit')), 'not even the home it has, for somebody who does not manage it');
}

// Into a channel the table keeps who made it; taken out it becomes the
// table of whoever takes it, and the share the move made pointless goes.
function test_ws_wtable_channel_move_writes()
{
	$set = ws_wtable_channel_test_code('ws_wtable_home_set');

	pg_assert_contains('ws_wtable_home_set_check($viewer, $table, $channel_id)', $set);
	pg_assert_contains("(\$target === 0) ? \", user_id = '\" . (int) \$viewer['id'] . \"'\" : ''", $set, 'a new owner only when taken out');
	pg_assert_contains("target_type = 'channel' AND target_id = '\" . \$target . \"'", $set);
	pg_assert_contains("target_type = 'user' AND target_id = '\" . (int) \$viewer['id'] . \"'", $set);
	pg_assert_contains('log_activity(', $set, 'a Site Log line');
	pg_assert_true(strpos($set, "if (!\$check['ok'] || \$check['same'])") < strpos($set, 'UPDATE ws_wtables'), 'no change writes nothing');

	$check = ws_wtable_channel_test_code('ws_wtable_home_set_check');

	pg_assert_contains('ws_wtable_home_writable($viewer, $channel_id)', $check);
	pg_assert_contains('ws_wtable_home_count((int) $viewer[\'id\'], $target)', $check, 'the new home\'s tables are counted');
	pg_assert_contains('ws_can_post_channel($viewer, $channel)', ws_wtable_channel_test_code('ws_wtable_home_writable'), 'a channel they may write in, so not an archived one');
}

// A table is made as a channel's only where its maker may write, and each
// home keeps its own limit.
function test_ws_wtable_channel_create()
{
	$check = ws_wtable_channel_test_code('ws_wtable_create_check');

	pg_assert_contains('ws_wtable_home_writable($viewer, $channel_id)', $check);
	pg_assert_contains("ws_wtable_refusal('channel_unwritable', 'channel_id')", $check);
	pg_assert_contains('ws_wtable_home_count((int) $viewer[\'id\'], $channel_id)', $check);
	pg_assert_contains("ws_wtable_own_sql((int) \$user_id)", ws_wtable_channel_test_code('ws_wtable_home_count'));
	pg_assert_contains('channel_id = 0 OR', ws_wtable_channel_test_code('ws_wtable_own_sql'), 'a table made for a channel is not its maker\'s own');
}

// The lists: a person's own tables leave a channel's out, the channels'
// tables are those of channels they read, and nothing is listed twice.
function test_ws_wtable_channel_lists()
{
	pg_assert_contains('ws_wtable_own_sql((int) $viewer[\'id\'])', ws_wtable_channel_test_code('ws_wtables_own'));

	$of_channels = ws_wtable_channel_test_code('ws_wtables_of_channels');

	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $of_channels);
	pg_assert_same(2, substr_count($of_channels, 'ws_wtable_live_sql()'), 'binned tables are left out of both reads');

	$reached = ws_wtable_channel_test_code('ws_wtables_reached');

	pg_assert_contains("array('mine', 'channels', 'shared')", $reached);
	pg_assert_contains('ws_wtables_of_channels($viewer)', $reached);
	pg_assert_contains('!isset($direct[(int) $share[\'table_id\']])', $reached, 'a table reached without a share is not "shared with me"');

	$binned = ws_wtable_channel_test_code('ws_wtables_binned_of_channels');

	pg_assert_contains("ws_wtable_may(ws_wtable_access(\$viewer, \$row), 'manage')", $binned, 'only who may restore it sees it');
	pg_assert_contains('ws_wtables_binned_of_channels($viewer)', ws_wtable_channel_test_code('ws_wtables_binned'));
	pg_assert_contains('ws_wtable_home_count(', ws_wtable_channel_test_code('ws_wtable_restore_check'), 'restored only while its home has room');
}

// A flow's box is paused by a gone owner only on a person's table, and a
// public channel's table announces its rows for that channel first.
function test_ws_wtable_channel_flows_and_events()
{
	pg_assert_contains("'present' => ws_wtable_present(\$table)", ws_wtable_channel_test_code('ws_wtable_flow_check'));
	pg_assert_contains('(ws_wtable_home($table) !== null) || ws_wtable_owner_present(', ws_wtable_channel_test_code('ws_wtable_present'));

	$events = ws_wtable_channel_test_code('ws_wtable_event_channels');

	pg_assert_contains("t.channel_id > 0 AND c.kind = 'public' AND c.archived_at = 0", $events);
	pg_assert_true(strpos($events, 'FROM ws_wtables t JOIN ws_channels c') < strpos($events, 'FROM ws_wtable_shares s'), 'the home before the shares');
	pg_assert_contains('!in_array((int) $share[\'target_id\'], $ids, true)', $events, 'a channel is named once');
	pg_assert_contains("(ws_wtable_home(\$table) === null)", ws_wtable_channel_test_code('ws_wtable_share_set_check'), 'the maker of a channel\'s table may be shared with');
}

// One step, its label taken once, with the decided column and index.
function test_ws_wtable_channel_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	$calls = isset($entry[1]) ? $entry[1] : '';

	pg_assert_same(1, substr_count($calls, "\tupgrade_2026_11_0_ws_wtable_channel();       // 11.0.191\n"), 'called once, labelled 11.0.191');
	preg_match_all('#// (11\.0\.\d+)\n#', $calls, $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.191', true)), 'no other step carries 11.0.191');

	pg_assert_true(preg_match('/^function upgrade_2026_11_0_ws_wtable_channel\(\) \{\n(.*?)\n\}\n/ms', $source, $match) === 1, 'the step is found');
	$step = $match[1];

	pg_assert_contains("'channel_id' => \"INT UNSIGNED NOT NULL DEFAULT 0\",", $step);
	pg_assert_contains("install_add_columns_instant('ws_wtables', \$columns)", $step);
	pg_assert_contains("install_add_column('ws_wtables', 'channel_id', \$columns['channel_id'])", $step);
	pg_assert_contains("install_add_index('ws_wtables', 'idx_channel', \"KEY idx_channel (channel_id, updated_at)\")", $step);
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');

	pg_assert_same(1, substr_count((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php'), "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_channel.php');"), 'loaded');
	pg_assert_contains("pg_schema_has('ws_wtables', 'channel_id')", ws_wtable_channel_test_code('ws_wtable_home_ready'), 'probed by its exact name');
}

// The table's screen: the line under the name, the card, the move posted
// with the token and asked first.
function test_ws_wtable_channel_screen()
{
	$note = ws_wtable_home_note(array('id' => 4, 'kind' => 'private', 'name' => 'A & B'), 'edit');

	pg_assert_contains('bi-lock', $note, 'a private channel shows a lock');
	pg_assert_contains('A &amp; B', $note, 'the name is escaped');
	pg_assert_contains(h(lang('Can edit')), $note);
	pg_assert_contains('bi-hash', ws_wtable_home_note(array('id' => 4, 'kind' => 'public', 'name' => 'Sales'), 'owner'));
	pg_assert_contains(h(lang('You manage it')), ws_wtable_home_note(array('id' => 4, 'kind' => 'public', 'name' => 'Sales'), 'owner'));

	$card = ws_wtable_channel_test_code('ws_wtable_home_card');

	foreach (array('get_token_field()', 'name="wt_action" value="table_home"', 'data-ws-confirm="', 'id="ws-wt-home"', 'name="channel"') as $needle) {
		pg_assert_contains($needle, $card);
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');

	foreach (array("case 'table_home':", 'ws_wtable_home_set($viewer, $table, $field(\'channel\'))', 'ws_wtable_home_card($viewer, $table, $self_url)', 'ws_wtable_home_note($home, (string) $table[\'access\'])', "'channel', 'value'") as $needle) {
		pg_assert_contains($needle, $screen);
	}

	pg_assert_same(lang('Choose a channel you may write in.'), ws_wtable_home_refusal_text(ws_wtable_refusal('channel_unwritable', 'channel_id'), true));
	pg_assert_contains((string) ws_wtable_limits()['tables'], ws_wtable_home_refusal_text(ws_wtable_refusal('too_many_tables', 'channel_id', 409), true));
	pg_assert_same(ws_wtable_screen_error_text('too_many_tables'), ws_wtable_home_refusal_text(ws_wtable_refusal('too_many_tables', 'channel_id', 409), false), 'their own tables are full');
	pg_assert_same(ws_wtable_screen_error_text('forbidden'), ws_wtable_home_refusal_text(ws_wtable_refusal('forbidden', '', 403), true));
}

// The API: the channel on the table, the scope, the table made as a
// channel's, and the move as the person's own decision.
function test_ws_wtable_channel_api()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same('integer', ws_api_wtable_schema()['channel_id'] ?? null);
	pg_assert_same(0, ws_api_wtable_present(array('id' => 7, 'user_id' => 5, 'name' => 'T', 'access' => 'owner', 'row_count' => 0, 'revision' => 1, 'created_at' => 0, 'updated_at' => 0))['channel_id'], 'a person\'s table');
	pg_assert_same(array_keys(ws_api_wtable_schema()), array_keys(ws_api_wtable_present(array('id' => 7, 'user_id' => 5, 'name' => 'T', 'access' => 'owner', 'row_count' => 0, 'revision' => 1, 'created_at' => 0, 'updated_at' => 0))), 'the schema names every field');

	pg_assert_same(array('all', 'mine', 'channels', 'shared'), $routes['workspace.tables.list']['params'][0]['values']);

	$create = array();

	foreach ($routes['workspace.tables.create']['params'] as $param) {
		$create[$param['name']] = $param;
	}

	pg_assert_same(array('name', 'description', 'channel_id'), array_keys($create));
	pg_assert_true(empty($create['channel_id']['required']), 'left out: the owner\'s own table');

	$home = $routes['workspace.tables.home'];

	pg_assert_same(array('POST', '/workspace/tables/{id}/home', 'workspace:write', false, true), array($home['method'], $home['path'], $home['scope'], $home['mcp'] ?? null, !empty($home['dry_run'])));
	pg_assert_false(pg_mcp_route_is_offered($home), 'no tool');

	$handler = ws_wtable_channel_test_code('ws_api_wtables_home');
	$stop = strpos($handler, 'api_dry_run_stop(');

	pg_assert_true(strpos($handler, 'ws_api_own_words_only(') < $stop, 'the team devices session first');
	pg_assert_true(strpos($handler, 'if (ws_api_is_assistant())') < $stop, 'an assistant refused first');
	pg_assert_true(strpos($handler, 'ws_wtable_home_set_check(') < $stop && $stop < strpos($handler, 'ws_wtable_home_set($viewer'), 'check, rehearsal, write');
	pg_assert_true(strpos($handler, 'ws_api_as_owner(') > $stop, 'written in the owner\'s name');
	pg_assert_contains('team devices session (POST /auth/login)', $handler);

	// Claude makes or reads a channel's table only where it may be asked.
	pg_assert_contains('ws_api_channel_or_404($viewer, (int) $data[\'channel_id\'])', ws_wtable_channel_test_code('ws_api_wtables_create'));
	pg_assert_contains('ws_can_read_channel($viewer, $home) && ws_claude_channel_allowed($home)', ws_wtable_channel_test_code('ws_api_wtable_reachable'));
}
