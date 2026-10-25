<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow shared with the team (includes/workspace/flow_shares.php):
 * the access a person has from their own flow, its home channel and its
 * shares (ws_flow_access_level(), pure: the highest wins, edit only while on
 * the staff), what each share gives, the checks of what is shared, who
 * manages the shares, and the 2026.11.0 step that creates the table
 * (11.0.83): called once under its own label after the flows step, written
 * with the idempotent helper only, listed among the software's tables. The
 * step itself needs a database and is run twice in a sandbox instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only; the assign rules of a task box are the
// templates' (ws_template_assign_valid()).
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');

// What is known about a person and a flow: nothing at all, on a live flow.
function ws_flow_shares_test_facts($facts = array())
{
	return $facts + array('owner' => false, 'staff' => false, 'status' => 'live', 'home' => false, 'person' => '', 'channel' => false);
}

function ws_flow_shares_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function of a file, as written.
function ws_flow_shares_test_function($relative, $name)
{
	$source = ws_flow_shares_test_source($relative);
	$start = strpos($source, 'function ' . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The owner while on the staff edits; somebody who is not reaches the flow
// as anybody else does. A deleted flow gives nothing to anybody.
function test_workspace_flow_shares_owner_edits_while_on_the_staff()
{
	pg_assert_same('edit', ws_flow_access_level(ws_flow_shares_test_facts(array('owner' => true, 'staff' => true))));
	pg_assert_same('edit', ws_flow_access_level(ws_flow_shares_test_facts(array('owner' => true, 'staff' => true, 'status' => 'draft'))), 'a draft too');
	pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts(array('owner' => true, 'staff' => false))), 'an owner who left the staff');
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('owner' => true, 'staff' => false, 'home' => true))), 'who still reads its home');
	pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts(array('owner' => true, 'staff' => true, 'status' => 'binned'))), 'a deleted flow');
	pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts(array('status' => 'binned', 'person' => 'edit', 'staff' => true, 'channel' => true, 'home' => true))), 'shares of a deleted flow');
	pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts()), 'nothing at all');
}

// The home channel's readers run a live flow, as before the shares.
function test_workspace_flow_shares_home_readers_run_a_live_flow()
{
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('home' => true))));

	foreach (array('draft', 'paused') as $status) {
		pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts(array('home' => true, 'status' => $status))), 'a ' . $status . ' flow');
	}
}

// A person shared at edit changes the flow only while on the staff, and runs
// it otherwise; at run they run it, at view they look at it, in any status
// (a run itself still needs a live flow). A channel share lets its readers
// look at it. The highest wins.
function test_workspace_flow_shares_person_and_channel_levels()
{
	pg_assert_same('edit', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'edit', 'staff' => true))));
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'edit', 'staff' => false))), 'edit acts as run off the staff');
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'run'))));
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'run', 'status' => 'paused'))), 'a paused flow');
	pg_assert_same('view', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'view', 'status' => 'draft'))), 'a draft');
	pg_assert_same('view', ws_flow_access_level(ws_flow_shares_test_facts(array('channel' => true))));
	pg_assert_same('view', ws_flow_access_level(ws_flow_shares_test_facts(array('channel' => true, 'status' => 'draft'))));
	pg_assert_same('', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'admin', 'staff' => true))), 'a level the flow does not know');

	// The highest of them.
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'view', 'home' => true))), 'view shared, run from the home');
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('channel' => true, 'home' => true))));
	pg_assert_same('edit', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'edit', 'staff' => true, 'channel' => true, 'home' => true))));
	pg_assert_same('run', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'run', 'channel' => true))));
	pg_assert_same('view', ws_flow_access_level(ws_flow_shares_test_facts(array('person' => 'view', 'channel' => true, 'home' => true, 'status' => 'paused'))), 'the home gives nothing on a paused flow');
}

function test_workspace_flow_shares_what_a_share_gives()
{
	pg_assert_same('edit', ws_flow_share_gives('user', 'edit', true));
	pg_assert_same('run', ws_flow_share_gives('user', 'edit', false));
	pg_assert_same('run', ws_flow_share_gives('user', 'run', false));
	pg_assert_same('view', ws_flow_share_gives('user', 'view', true));
	pg_assert_same('view', ws_flow_share_gives('channel', 'view', false));
	pg_assert_same('view', ws_flow_share_gives('channel', 'run', true), 'a channel share gives a view whatever is stored');
	pg_assert_same('', ws_flow_share_gives('user', '', true));
	pg_assert_same('', ws_flow_share_gives('group', 'view', true));
}

function test_workspace_flow_shares_access_ranks()
{
	pg_assert_same(array(0, 1, 2, 3, 0), array_map('ws_flow_access_rank', array('', 'view', 'run', 'edit', 'owner')));
	pg_assert_same(array('view', 'run', 'edit'), array_keys(ws_flow_share_levels()), 'the levels, lowest first');
}

// What a share names is checked before anything is read: a person or a
// channel by its id, a level the flow knows, and a channel only at view
// (running comes from the flow's home channel).
function test_workspace_flow_shares_input_is_checked()
{
	pg_assert_same('', ws_flow_share_input_problem('user', 5, 'edit'));
	pg_assert_same('', ws_flow_share_input_problem('user', 5, 'view'));
	pg_assert_same('', ws_flow_share_input_problem('channel', 3, 'view'));
	pg_assert_same('target_type', ws_flow_share_input_problem('group', 5, 'view'));
	pg_assert_same('target_type', ws_flow_share_input_problem('', 5, 'view'));
	pg_assert_same('target_type', ws_flow_share_input_problem(array('user'), 5, 'view'), 'a list is no kind');
	pg_assert_same('target_id', ws_flow_share_input_problem('user', 0, 'view'));
	pg_assert_same('target_id', ws_flow_share_input_problem('channel', -2, 'view'));
	pg_assert_same('level', ws_flow_share_input_problem('user', 5, 'admin'));
	pg_assert_same('level', ws_flow_share_input_problem('user', 5, ''));
	pg_assert_same('channel_level', ws_flow_share_input_problem('channel', 3, 'run'));
	pg_assert_same('channel_level', ws_flow_share_input_problem('channel', 3, 'edit'));

	// Every stored value fits its VARCHAR(16) column.
	foreach (array_merge(array_keys(ws_flow_share_levels()), ws_flow_share_targets()) as $value) {
		pg_assert_true(strlen($value) <= 16, $value . ' fits');
	}
}

// The owner, while they may change the flow, manages its shares: a person
// shared at edit changes the graph but does not share it further or delete
// it, and an administrator gets nothing by rank. Deleting asks the same.
function test_workspace_flow_shares_only_the_owner_manages()
{
	pg_assert_true(ws_flow_share_manages(true, 'edit'));
	pg_assert_false(ws_flow_share_manages(false, 'edit'), 'a person shared at edit');
	pg_assert_false(ws_flow_share_manages(true, 'run'), 'an owner who left the staff');
	pg_assert_false(ws_flow_share_manages(false, 'view'));

	foreach (array('ws_flow_share_set_check', 'ws_flow_share_remove_check', 'ws_flow_share_manager') as $name) {
		pg_assert_true(ws_flow_shares_test_function('includes/workspace/flow_shares.php', $name) !== '', $name . ' found');
	}

	foreach (array('ws_flow_share_set_check', 'ws_flow_share_remove_check') as $name) {
		pg_assert_contains('ws_flow_share_manager($viewer, $flow)', ws_flow_shares_test_function('includes/workspace/flow_shares.php', $name), $name . ' asks for the owner');
	}

	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flow_bin_check'), 'deleting asks for the owner');
	pg_assert_contains('ws_flow_share_manages(', ws_flow_shares_test_function('includes/workspace/flow_shares.php', 'ws_flow_share_manager'));
}

// Moving a flow to another home channel decides who may run it: an edit
// share changes the graph, the owner alone the home.
function test_workspace_flow_shares_home_is_moved_by_the_owner()
{
	$check = ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flow_save_check');

	pg_assert_contains("(int) \$flow['owner_id'] !== (int) \$viewer['id']", $check, 'a change of home asks who owns the flow');
	pg_assert_true(strpos($check, "(int) \$flow['owner_id'] !== (int) \$viewer['id']") > strpos($check, "array_key_exists('channel_id', \$data)"), 'only when the home changes');
}

// A published flow opens its tasks in the owner's name with the owner's
// rights (an assign rule resolved through them, the home channel they post
// in), so the owner alone publishes: a colleague shared at edit saves the
// draft and may pause the flow, which only stops new runs.
function test_workspace_flow_shares_owner_publishes()
{
	$publish = ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flow_publish_check');

	pg_assert_true($publish !== '', 'the publish check is found');
	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', $publish, 'publishing asks for the owner');
	pg_assert_true(strpos($publish, "ws_flow_access(\$viewer, \$flow) !== 'edit'") === false, 'not for every editor');

	foreach (array('ws_flow_save_check', 'ws_flow_pause_check') as $name) {
		pg_assert_contains("ws_flow_access(\$viewer, \$flow) !== 'edit'", ws_flow_shares_test_function('includes/workspace/flows.php', $name), $name . ' stays open to a colleague shared at edit');
	}

	// The canvas draws Publish for the owner alone and tells the others why.
	$script = ws_flow_shares_test_source('assets/js/workspace_flow.js');

	pg_assert_contains("if (flow.manage) {\n            ui.publish = K.button(", $script, 'Publish for whoever manages the flow');
	pg_assert_contains("t('fl_publish_owner')", $script);
}

// ws_flow_access() gathers the facts and the pure function decides; a view
// share does not start a run.
function test_workspace_flow_shares_access_reads_the_shares()
{
	$access = ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flow_access');

	pg_assert_contains('ws_flow_access_level(', $access);
	pg_assert_contains('ws_flow_share_reach(', $access);
	pg_assert_contains("empty(\$viewer['member'])", $access, 'site members never');

	$start = ws_flow_shares_test_function('includes/workspace/flow_runs.php', 'ws_flow_run_start_check');

	pg_assert_contains("=== 'view'", $start, 'a view share is refused a run');
}

// A list of flows or runs asks for every row whether the person reads a
// channel: the flow's home, a channel it is shared in, the channel a run was
// started in. All three ask one memo, read once a request per person and
// channel and again after the request changed a membership, so a page of
// rows costs a read per distinct channel instead of one per row and share.
function test_workspace_flow_shares_channels_are_read_once_a_request()
{
	$memo = ws_flow_shares_test_function('includes/workspace/flow_shares.php', 'ws_flow_channel_readable');

	pg_assert_true($memo !== '', 'the memo is found');
	pg_assert_contains('static $', $memo, 'kept for the request');
	pg_assert_contains('ws_membership_generation', $memo, 'read again once a membership changed');
	pg_assert_contains('ws_flow_share_channel_readable(', $memo, 'a public or private channel the person reads');

	$callers = array(
		'ws_flow_access'               => 'includes/workspace/flows.php',
		'ws_flow_share_reach'          => 'includes/workspace/flow_shares.php',
		'ws_flow_run_channel_readable' => 'includes/workspace/flow_runs.php',
	);

	foreach ($callers as $name => $file) {
		$body = ws_flow_shares_test_function($file, $name);

		pg_assert_contains('ws_flow_channel_readable(', $body, $name . ' asks the memo');
		pg_assert_false(strpos($body, 'ws_channel(') !== false, $name . ' reads no channel row itself');
	}

	pg_assert_false(ws_flow_channel_readable(array('id' => 4, 'member' => true, 'role' => 0), 0), 'no channel');
}

// Whoever may change a flow reads its draft; whoever else reaches it reads
// the draft only while no version is published (shared before it was), and
// the live graph once one is.
function test_workspace_flow_shares_draft_readers()
{
	pg_assert_true(ws_flow_draft_readable('edit', 5), 'an editor of a published flow');
	pg_assert_true(ws_flow_draft_readable('edit', 0), 'an editor of a flow never published');
	pg_assert_true(ws_flow_draft_readable('run', 0), 'a run share of a flow never published');
	pg_assert_true(ws_flow_draft_readable('view', 0), 'a view share of a flow never published');
	pg_assert_false(ws_flow_draft_readable('run', 5), 'a run share reads the live graph');
	pg_assert_false(ws_flow_draft_readable('view', 5), 'a view share reads the live graph');
	pg_assert_false(ws_flow_draft_readable('', 0), 'nobody else');
	pg_assert_false(ws_flow_draft_readable('', 5));
}

// The table is probed by its exact name, and the list reads the shares of
// every flow it lists in one statement.
function test_workspace_flow_shares_probe_and_list()
{
	pg_assert_contains("pg_schema_has('ws_flow_shares')", ws_flow_shares_test_function('includes/workspace/flow_shares.php', 'ws_flow_shares_ready'));

	$list = ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flows_list');

	pg_assert_contains('ws_flow_shares', $list, 'the flows shared with the person are read');
	pg_assert_contains('ws_flow_share_rows_prime(', $list, 'their shares in one read');
}

// The canvas lists the flows of others under one heading: those shared with
// the person, those shared in a channel they read and the live flows of a
// home channel they read, which nobody shared. The heading names the
// channels, not the person alone.
function test_workspace_flow_shares_list_heading_names_the_channels()
{
	preg_match("/'fl_list_shared'\\s*=>\\s*lang\\('([^']*)'\\)/", ws_flow_shares_test_function('includes/workspace/flows.php', 'ws_flow_js_strings'), $heading);

	pg_assert_true(isset($heading[1]), 'the heading is found');
	pg_assert_contains('channels', $heading[1] ?? '', 'the heading names the channels');
}

// A person told of a share gets a line of their own in the bell, named by
// the flow; the kind fits ws_inbox.kind.
function test_workspace_flow_shares_bell_row()
{
	pg_assert_true(strlen('flow_shared') <= 20, 'fits VARCHAR(20)');
	pg_assert_contains("ws_notify(\$user_id, 'flow_shared'", ws_flow_shares_test_function('includes/workspace/flow_shares.php', 'ws_flow_share_tell'));
	pg_assert_contains("'separate' => true", ws_flow_shares_test_function('includes/workspace/flow_shares.php', 'ws_flow_share_tell'), 'never folded into another flow\'s line');
	pg_assert_contains("case 'flow_shared':", ws_flow_shares_test_source('includes/workspace/notify.php'));
	pg_assert_contains('flow_shared', ws_flow_shares_test_source('includes/workspace/api.php'), 'the inbox route names the kind');
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function ws_flow_shares_test_step()
{
	if (!preg_match('/^function upgrade_2026_11_0_workspace_flow_shares\(\) \{\n(.*?)\n\}\n/ms', ws_flow_shares_test_source('includes/migrations/2026.11.0.php'), $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_flow_shares_step_is_called_once_after_the_flows_step()
{
	$source = ws_flow_shares_test_source('includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	$calls = isset($entry[1]) ? $entry[1] : '';

	pg_assert_true($calls !== '', 'the entry function is found');
	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_workspace_flow_shares(); // 11.0.83\n"), 'called once, labelled 11.0.83');
	pg_assert_true(strpos($calls, 'upgrade_2026_11_0_workspace_flow_shares();') > strpos($calls, 'upgrade_2026_11_0_workspace_flows();'), 'after the flows step, whose table it reads');

	preg_match_all('#// (11\.0\.\d+)\n#', $calls, $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.83', true)), 'no other step carries 11.0.83');
	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

function test_workspace_flow_shares_step_creates_the_table_with_the_helper()
{
	$step = ws_flow_shares_test_step();

	pg_assert_true($step !== '', 'the step function is found');
	pg_assert_contains("install_create_table('ws_flow_shares', \"CREATE TABLE ws_flow_shares (", $step, 'created through the helper, which asks first');

	foreach (array(
		"flow_id      INT UNSIGNED NOT NULL DEFAULT 0,",
		"target_type  VARCHAR(16) NOT NULL DEFAULT '',",
		"target_id    INT UNSIGNED NOT NULL DEFAULT 0,",
		"level        VARCHAR(16) NOT NULL DEFAULT 'view',",
		"created_by   INT UNSIGNED NOT NULL DEFAULT 0,",
		"created_at   INT UNSIGNED NOT NULL DEFAULT 0,",
		"UNIQUE KEY uq_target (flow_id, target_type, target_id),",
		"KEY idx_target (target_type, target_id, flow_id)",
	) as $line) {
		pg_assert_contains($line, $step);
	}

	pg_assert_true(stripos($step, 'ENUM') === false, 'VARCHAR, not ENUM');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

// A reinstall into the same database drops the table with the software's
// others, and the version's one [SCHEME] line names it.
function test_workspace_flow_shares_table_is_listed_and_named()
{
	pg_assert_same(1, substr_count(ws_flow_shares_test_source('install/index.php'), "'ws_flow_shares',"), 'in get_tables()');

	$changelog = ws_flow_shares_test_source('changelog.txt');
	$start = strpos($changelog, "\n2026.11.0\n");
	$end = ($start !== false) ? strpos($changelog, "\n=====", $start) : false;
	$version = (($start !== false) && ($end !== false)) ? substr($changelog, $start, $end - $start) : '';

	pg_assert_same(1, substr_count($version, '[SCHEME]'), 'one [SCHEME] line');
	pg_assert_contains('runs, run steps and shares', $version, 'it names the shares');
}
