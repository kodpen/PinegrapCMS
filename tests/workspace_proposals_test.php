<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record changes an application proposes
 * (includes/workspace/proposals.php) that need no database: the agent a
 * proposal is kept under for each kind of caller, how that agent is read
 * back, the SQL that tells proposals from the assistants' requests, the
 * agent the API lists a change under, the read scopes the kinds a body
 * names are asked with and the kind no application proposes, how an
 * application's name is cleaned and shown, and which run on a task the
 * drawer carries on when a proposal came after it.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// The code of a loaded function without its comments, so a check of what
// it calls and in which order is not misled by a comment that names a call.
function pg_test_ws_proposals_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

// The part of a piece of code from one marker to the next, '' when the
// first is not there.
function pg_test_ws_proposals_between($code, $from, $to)
{
	$start = strpos($code, $from);

	if ($start === false) {
		return '';
	}

	$end = strpos($code, $to, $start + strlen($from));

	return ($end === false) ? substr($code, $start) : substr($code, $start, $end - $start);
}

// An OAuth connection is kept under its client, since every client writes
// through the one OAuth application; a key under its application; anything
// else under none.
function test_ws_proposal_agent_key()
{
	pg_assert_same('oauth:7', ws_proposal_agent_key(7, 3), 'the client wins over the shared application');
	pg_assert_same('app:3', ws_proposal_agent_key(0, 3));
	pg_assert_same('', ws_proposal_agent_key(0, 0));
	pg_assert_same('', ws_proposal_agent_key(-1, -2), 'no negative ids');

	// The longest id still fits ws_ai_requests.agent, VARCHAR(16).
	pg_assert_true(strlen(ws_proposal_agent_key(4294967295, 0)) <= 16, 'oauth:<INT UNSIGNED> fits the column');
}

// Who calls decides the agent: a key by its application, an OAuth
// connection by its client, a team devices session by nobody (the person
// changes the record themselves).
function test_ws_proposal_caller_key()
{
	pg_assert_same('app:12', ws_proposal_caller_key(null, 12), 'an application key');
	pg_assert_same('oauth:5', ws_proposal_caller_key(array('id' => 40, 'oauth_client_id' => 5), 9), 'an OAuth connection');
	pg_assert_same('', ws_proposal_caller_key(array('id' => 41, 'oauth_client_id' => 0), 9), 'a team devices session');
	pg_assert_same('', ws_proposal_caller_key(array('id' => 42), 9), 'a device row before the OAuth column');
	pg_assert_same('', ws_proposal_caller_key(null, 0));
}

// Only the two proposal shapes read back as an application; the assistants'
// agents and anything malformed do not.
function test_ws_proposal_agent_parse()
{
	pg_assert_same(array('kind' => 'oauth', 'id' => 5), ws_proposal_agent_parse('oauth:5'));
	pg_assert_same(array('kind' => 'app', 'id' => 12), ws_proposal_agent_parse('app:12'));

	foreach (array('claude', 'ai', 'mcp', 'mcp:3', '', 'app:', 'app:0', 'oauth:05', 'oauth:x', 'app:1 ', 'APP:1', 'oauth:12345678901') as $agent) {
		pg_assert_same(null, ws_proposal_agent_parse($agent), var_export($agent, true) . ' is no proposal');
	}

	foreach (array(array(7, 0), array(0, 3)) as $ids) {
		$key = ws_proposal_agent_key($ids[0], $ids[1]);

		pg_assert_true(ws_proposal_agent_parse($key) !== null, $key . ' reads back');
	}
}

// The SQL names both shapes, with the table's alias when there is one, and
// can be turned round to keep the proposals out.
function test_ws_proposal_where()
{
	pg_assert_same("(agent LIKE 'oauth:%' OR agent LIKE 'app:%')", ws_proposal_where());
	pg_assert_same("(r.agent LIKE 'oauth:%' OR r.agent LIKE 'app:%')", ws_proposal_where('r'));
	pg_assert_same("NOT (agent LIKE 'oauth:%' OR agent LIKE 'app:%')", ws_proposal_where('', false));
}

// The API lists a change under Pinegrap AI, an MCP agent, the application
// that proposed it, or Claude: never under Claude for an application's.
function test_ws_change_agent_key()
{
	pg_assert_same('ai', ws_change_agent_key('ai'));
	pg_assert_same('mcp', ws_change_agent_key('mcp'));
	pg_assert_same('oauth:5', ws_change_agent_key('oauth:5'));
	pg_assert_same('app:12', ws_change_agent_key('app:12'));
	pg_assert_same('claude', ws_change_agent_key('claude'));
	pg_assert_same('claude', ws_change_agent_key(''), 'a row from before the column');
	pg_assert_same('claude', ws_change_agent_key('oauth:'), 'a malformed agent');
}

// The kinds a body names are read with the scope the API reads them with
// elsewhere, once each; a change that is not an object or names no kind is
// left to ws_changes_input() to refuse.
function test_ws_proposal_scopes()
{
	$types = array(
		'product' => array('tag' => 'product'),
		'stock'   => array('tag' => 'product'),
		'order'   => array('tag' => 'order'),
		'contact' => array('tag' => 'contact'),
		'task'    => array('tag' => 'task'),
		'note'    => array('tag' => ''),
	);

	pg_assert_same(array('products:read', 'orders:read'), ws_proposal_scopes(array(
		array('type' => 'product', 'id' => 1),
		array('type' => 'stock', 'id' => 1),
		array('type' => 'order', 'id' => 9),
	), $types), 'one scope per kind read');

	pg_assert_same(array('customers:read', 'tasks:read', 'workspace:read'), ws_proposal_scopes(array(
		array('type' => 'contact', 'action' => 'create'),
		array('type' => 'task', 'id' => 3),
		array('type' => 'note', 'action' => 'create'),
	), $types), 'a kind with no tag is the Workspace\'s own');

	pg_assert_same(array(), ws_proposal_scopes(array('product', array('type' => 'nothing'), array('id' => 4), null), $types), 'nothing named, nothing asked');
	pg_assert_same(array(), ws_proposal_scopes('product', $types), 'not a list');
	pg_assert_same(array(), ws_proposal_scopes(null, $types));

	// Every kind of the real catalogue an application may propose is read
	// with a scope of its own, or is the Workspace's own (a channel's
	// summary, a note): a new kind whose tag ws_ai_type_scope() does not
	// know would otherwise be read back under workspace:read alone.
	$catalogue = pg_test_ws_change_types();
	$workspace_own = array('channel', '');

	foreach ($catalogue as $kind => $type) {
		if (ws_proposal_closed_change(array(array('type' => $kind)), $catalogue) === 0) {
			continue;
		}

		$scopes = ws_proposal_scopes(array(array('type' => $kind)), $catalogue);

		pg_assert_same(1, count($scopes), $kind . ' asks one scope');

		if (in_array((string) $type['tag'], $workspace_own, true)) {
			pg_assert_same(array('workspace:read'), $scopes, $kind . ' is the Workspace\'s own');
		} else {
			pg_assert_false($scopes === array('workspace:read'), $kind . ' (tag ' . $type['tag'] . ') is read with the scope of its kind, not workspace:read');
		}
	}
}

// A user account is read by no API scope (account:read is the caller's own
// account), so its current e-mail, which a proposal answers with as from,
// would reach an application under workspace:read: no application proposes
// a change to one. Every other kind stays open.
function test_ws_proposal_closed_change()
{
	$catalogue = pg_test_ws_change_types();

	pg_assert_true(isset($catalogue['user']), 'the catalogue has user accounts');
	pg_assert_same('user_account', (string) $catalogue['user']['tag']);

	pg_assert_same(0, ws_proposal_closed_change(array(array('type' => 'user', 'id' => 4, 'fields' => array('email' => 'x@example.com'))), $catalogue));
	pg_assert_same(2, ws_proposal_closed_change(array(
		array('type' => 'product', 'id' => 1),
		array('type' => 'order', 'id' => 2),
		array('type' => 'user', 'id' => 3),
	), $catalogue), 'the index of the first closed change');
	pg_assert_same(1, ws_proposal_closed_change(array('user', array('type' => 'user')), $catalogue), 'a change that is no object is left to ws_changes_input()');

	foreach (array_keys($catalogue) as $kind) {
		if ($kind !== 'user') {
			pg_assert_same(-1, ws_proposal_closed_change(array(array('type' => $kind)), $catalogue), $kind . ' is proposed');
		}
	}

	pg_assert_same(-1, ws_proposal_closed_change(null, $catalogue));
	pg_assert_same(-1, ws_proposal_closed_change(array(array('type' => 'nothing')), $catalogue));
}

// An application's name goes into the words of a decision, and an OAuth
// client names itself: no tag, no line break survives, and it stays short.
function test_ws_proposal_name_clean()
{
	pg_assert_same('ChatGPT', ws_proposal_name_clean('ChatGPT'));
	pg_assert_same('Cursor Agent', ws_proposal_name_clean("  Cursor\n\tAgent  "));
	pg_assert_same('@user:1 x', ws_proposal_name_clean('<@user:1> x'), 'a mention tag is no tag any more');
	pg_assert_same('#order:5', ws_proposal_name_clean('<#order:5>'));
	pg_assert_same('', ws_proposal_name_clean('<>'), 'nothing left: the caller names it');
	pg_assert_same(100, mb_strlen(ws_proposal_name_clean(str_repeat('ş', 150))), 'the column\'s length');
	pg_assert_false(strpos(ws_proposal_name_clean('a<b>c'), '<') !== false);
}

// The name an application's proposal is shown with in the Site Log, a
// decision, the bell and a task's drawer always says it is an application:
// an OAuth client names itself when it registers, and a client called
// Claude or after a colleague would otherwise read as them.
function test_ws_proposal_name_shown()
{
	pg_assert_same('ChatGPT (application)', ws_proposal_name_shown('ChatGPT', 'oauth'));
	pg_assert_same('Stock sync (application)', ws_proposal_name_shown('Stock sync', 'app'));

	foreach (array('Claude', 'Pinegrap AI', 'Erdal Güral') as $name) {
		pg_assert_false(ws_proposal_name_shown($name, 'oauth') === $name, $name . ' is never shown bare');
	}

	pg_assert_same('OAuth connection', ws_proposal_name_shown('', 'oauth'), 'a client with no name');
	pg_assert_same('Application', ws_proposal_name_shown('', 'app'));
}

// The drawer carries on the run that is still open, not the newest row: an
// application's proposal may be written on the task after a run began.
function test_ws_task_delegation_open_row()
{
	$proposal = array('id' => 11, 'agent' => 'oauth:5', 'status' => 'answered');
	$run = array('id' => 10, 'agent' => 'ai', 'status' => 'running');
	$earlier = array('id' => 9, 'agent' => 'claude', 'status' => 'answered');

	pg_assert_same($run, ws_task_delegation_open_row(array($proposal, $run, $earlier)), 'the open run under a newer proposal');
	pg_assert_same($run, ws_task_delegation_open_row(array($run, $earlier)));

	$sent = array('id' => 12, 'agent' => 'mcp', 'status' => 'sent');

	pg_assert_same($sent, ws_task_delegation_open_row(array($sent, $proposal)), 'a run waiting for its approval is open');
	pg_assert_same(null, ws_task_delegation_open_row(array($proposal, $earlier)), 'nothing open');
	pg_assert_same(null, ws_task_delegation_open_row(array()));
}

// Calls sent at the same moment are counted and written under one lock per
// application and person: the lock's name is the same for the same
// application, person and database, differs for any other, and fits the 64
// characters MySQL takes.
function test_ws_proposal_lock_name()
{
	$name = ws_proposal_lock_name('shop@localhost', 'oauth:5', 12);

	pg_assert_same($name, ws_proposal_lock_name('shop@localhost', 'oauth:5', 12), 'the same for the same calls');
	pg_assert_true(strlen($name) <= 64, 'within the length of a GET_LOCK name');
	pg_assert_true(strlen(ws_proposal_lock_name(str_repeat('d', 200), 'oauth:4294967295', 4294967295)) <= 64, 'whatever the database is called');
	pg_assert_false($name === ws_proposal_lock_name('shop@localhost', 'oauth:6', 12), 'another client');
	pg_assert_false($name === ws_proposal_lock_name('shop@localhost', 'app:5', 12), 'a key of the same number');
	pg_assert_false($name === ws_proposal_lock_name('shop@localhost', 'oauth:5', 13), 'another person');
	pg_assert_false($name === ws_proposal_lock_name('other@localhost', 'oauth:5', 12), 'another site on the same server');
}

// The limit is checked and the row written under that lock, and nothing is
// counted again after the insert: of a burst, exactly the calls over the
// limit are refused, never one that fitted.
function test_ws_proposal_store_counts_under_the_lock()
{
	$code = pg_test_ws_proposals_code('ws_proposal_store');
	$lock = strpos($code, 'GET_LOCK(');
	$limited = strpos($code, 'ws_proposal_limited(');
	$insert = strpos($code, 'INSERT INTO ws_ai_requests');
	$release = ($insert === false) ? false : strpos($code, 'RELEASE_LOCK(', $insert);

	pg_assert_true($lock !== false, 'a lock is taken');
	pg_assert_true(($limited !== false) && ($limited > $lock), 'the limit is checked under it');
	pg_assert_true(($insert !== false) && ($insert > $limited), 'then the row is written');
	pg_assert_true($release !== false, 'and the lock is let go once it is');
	pg_assert_false(($insert !== false) && (strpos($code, 'ws_proposal_count(', $insert) !== false), 'nothing is counted again after the insert');
}

// The hour slides: a refused call waits until the oldest proposal still
// counted leaves it, at least a second, at most the hour.
function test_ws_proposal_wait()
{
	$now = 1800000000;

	pg_assert_same(60, ws_proposal_wait($now - 3540, $now), 'the oldest was made 59 minutes ago');
	pg_assert_same(3600, ws_proposal_wait($now, $now), 'the oldest was made now');
	pg_assert_same(1, ws_proposal_wait($now - 3600, $now), 'the oldest is leaving now');
	pg_assert_same(1, ws_proposal_wait($now - 7200, $now), 'never less than a second');
	pg_assert_same(3600, ws_proposal_wait($now + 300, $now), 'a clock ahead of this one: the hour');
	pg_assert_same(3600, ws_proposal_wait(0, $now), 'none found: the hour');
}

// The refusal says how long to wait in its words too: over MCP the
// Retry-After header rides on a tool result clients do not read.
function test_ws_proposal_limit_text_says_how_long()
{
	$text = ws_proposal_limit_text(75);

	pg_assert_contains('75', $text, 'the seconds');
	pg_assert_contains((string) WS_PROPOSALS_PER_HOUR, $text, 'the limit');

	pg_assert_true(preg_match("/'string'\\s*=>\\s*'((?:[^'\\\\]|\\\\.)*)'/", pg_test_ws_proposals_code('ws_proposal_limit_text'), $match) === 1, 'the sentence is found');
	pg_assert_true(lang(array('string' => stripslashes($match[1] ?? ''), 'language' => 'tr', 'if_known' => true)) !== null, 'and known in tr.json');
}

// A proposal on a task is no run anybody asked for: the drawer marks it, and
// draws the person it waits for, with no "asked" line and no instruction.
function test_ws_task_run_is_proposal()
{
	pg_assert_true(ws_task_run_is_proposal(array('agent' => 'oauth:5')), 'an OAuth connection\'s');
	pg_assert_true(ws_task_run_is_proposal(array('agent' => 'app:12')), 'a key\'s');

	foreach (array('claude', 'ai', 'mcp', '') as $agent) {
		pg_assert_false(ws_task_run_is_proposal(array('agent' => $agent)), var_export($agent, true) . ' is a run');
	}

	pg_assert_false(ws_task_run_is_proposal(array()), 'a row from before the column');

	$detail = pg_test_ws_proposals_code('ws_task_delegation_detail');

	pg_assert_contains('ws_task_run_is_proposal(', $detail, 'the drawer asks it of every row');
	pg_assert_contains("'proposal'", $detail, 'and sends it with the run');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_task_delegate.js');
	$run = pg_test_ws_proposals_between($script, 'function run(item)', 'function composer()');

	pg_assert_true($run !== '', 'run() is found');
	pg_assert_contains('item.proposal', $run, 'run() draws a proposal apart');
	pg_assert_contains("'dg_proposed'", $run, 'with its own line');

	pg_assert_true(preg_match("/'dg_proposed'\\s*=>\\s*ws_js_template\\('((?:[^'\\\\]|\\\\.)*)'/", pg_test_ws_proposals_code('ws_delegation_js_strings'), $match) === 1, 'the line is defined');
	pg_assert_true(lang(array('string' => stripslashes($match[1] ?? ''), 'language' => 'tr', 'if_known' => true)) !== null, 'and known in tr.json');
}

// Every slice of a bulk change after the first is written in a request of
// its own: each names who proposed the change on its records' lines
// (ws_ai_voice()), and hands back the name it found once the slice is done.
function test_ws_bulk_step_names_the_proposer()
{
	$code = pg_test_ws_proposals_code('ws_bulk_step');
	$first = strpos($code, 'ws_change_write(');
	$last = strrpos($code, 'ws_change_write(');
	$set = strpos($code, 'ws_ai_voice_change(');
	$finish = strpos($code, 'ws_bulk_finish(');

	pg_assert_true($first !== false, 'the slice writes records');
	pg_assert_true(($set !== false) && ($set < $first), 'the change is named before the first record is written');
	pg_assert_true(($finish !== false) && ($last !== false) && ($finish > $last) && (strpos(substr($code, $last, $finish - $last), 'ws_ai_voice_change(') !== false), 'and handed back after the last one');
}
