<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Activity tab of Channel info
 * (assets/js/workspace_channel_activity.js): when the Info and Activity tabs
 * show, which tab a channel opens on, the words of an agent's row (its
 * request, its context and its report), the 15 second brake on reads asked
 * by the channel's sync, and the hooks in workspace.js. The Node parts are
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a script under assets/js/, '' when it is not there.
function workspace_channel_activity_js_test_source($name = 'workspace_channel_activity.js')
{
	$file = PG_FUNCTIONS_DIR . '/assets/js/' . $name;

	return is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
}

// One "function name(" of the tab's script up to the closing brace at its
// own indent; '' when it is not there.
function workspace_channel_activity_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_channel_activity_js_test_source(), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_channel_activity_js_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgactjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// A t() that names the key and its values, as the tests read the words.
function workspace_channel_activity_js_test_t()
{
	return 'function t(key) { var rest = Array.prototype.slice.call(arguments, 1); return rest.length ? key + "(" + rest.join(",") + ")" : key; }' . "\n";
}

function test_workspace_channel_activity_js_tabs_show()
{
	$shows = workspace_channel_activity_js_test_function('activityTabsShow');

	pg_assert_true($shows !== '', 'activityTabsShow() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($shows . '
console.log(JSON.stringify({
	agent: activityTabsShow({ id: 1, agents: [{ id: 4 }] }),
	repository: activityTabsShow({ id: 1, agents: [], repository_linked: true }),
	both: activityTabsShow({ id: 1, agents: [{ id: 4 }], repository_linked: true }),
	none: activityTabsShow({ id: 1, agents: [], repository_linked: false }),
	noList: activityTabsShow({ id: 1 }),
	guest: activityTabsShow({ id: 1, agents: [{ id: 4 }], guest: { name: "x" } }),
	noChannel: activityTabsShow(null)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_true($result['agent'], 'a channel with an agent gets the tabs');
	pg_assert_true($result['repository'], 'a channel with a repository link gets the tabs');
	pg_assert_true($result['both'], 'and one with both');
	pg_assert_false($result['none'], 'a channel with neither keeps its Channel info as it was');
	pg_assert_false($result['noList'], 'no agent list, no tabs');
	pg_assert_false($result['guest'], 'never on a guest\'s page');
	pg_assert_false($result['noChannel'], 'no channel, no tabs');
}

function test_workspace_channel_activity_js_remembered_tab()
{
	$pick = workspace_channel_activity_js_test_function('activityTabPick');
	$key = workspace_channel_activity_js_test_function('activityTabKey');

	pg_assert_true($pick !== '', 'activityTabPick() is in the tab\'s script');
	pg_assert_true($key !== '', 'activityTabKey() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($pick . $key . '
console.log(JSON.stringify({
	activity: activityTabPick("activity", true),
	info: activityTabPick("info", true),
	nothing: activityTabPick(null, true),
	junk: activityTabPick("tasks", true),
	hidden: activityTabPick("activity", false),
	key7: activityTabKey(7),
	key8: activityTabKey(8)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('activity', $result['activity'], 'the tab last open in the channel opens again');
	pg_assert_same('info', $result['info'], 'Info when Info was last open');
	pg_assert_same('info', $result['nothing'], 'Info when nothing is kept');
	pg_assert_same('info', $result['junk'], 'Info for a value that is no tab');
	pg_assert_same('info', $result['hidden'], 'Info where the tabs do not show');
	pg_assert_true($result['key7'] !== $result['key8'], 'each channel keeps its own tab');
	pg_assert_contains('7', $result['key7'], 'the key names the channel');
}

function test_workspace_channel_activity_js_row_words()
{
	$tokens = workspace_channel_activity_js_test_function('activityTokens');
	$context = workspace_channel_activity_js_test_function('activityContext');
	$words = workspace_channel_activity_js_test_function('activityRowWords');

	pg_assert_true($tokens !== '', 'activityTokens() is in the tab\'s script');
	pg_assert_true($context !== '', 'activityContext() is in the tab\'s script');
	pg_assert_true($words !== '', 'activityRowWords() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_t() . $tokens . $context . $words . '
var working = {
	state: "working", state_words: "Working",
	request: { message_id: 55, asked_by: { user_id: 3, name: "Ayşe" }, since: "since 09:12", note: "Running the tests" },
	model: "opus", context: { used: 412000, size: 1000000 }, branch: "plan/x", reported: "reported 12 min ago", stale: false
};
var quiet = {
	state: "idle", state_words: "Ready", request: null,
	model: "", context: null, project: null, branch: null, reported: "", stale: false
};
var other = {
	state: "working", state_words: "Working",
	request: { message_id: null, asked_by: null, since: "since 10:00", note: "" },
	model: "sonnet", context: { used: 190000, size: 200000 }, branch: null, reported: "reported 2 d ago", stale: true
};
console.log(JSON.stringify({
	t: [activityTokens(950), activityTokens(412000), activityTokens(1000000), activityTokens(1500000), activityTokens(12345)],
	ctx: activityContext({ used: 412000, size: 1000000 }, t),
	ctxHigh: activityContext({ used: 190000, size: 200000 }, t),
	ctxBad: [activityContext(null, t), activityContext({ used: 5, size: 0 }, t)],
	working: activityRowWords(working, t),
	quiet: activityRowWords(quiet, t),
	other: activityRowWords(other, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('950', '412k', '1M', '1.5M', '12k'), $result['t'], 'tokens in short words');
	pg_assert_same('act_context(412k,1M)', $result['ctx']['words'], 'context as "412k of 1M tokens"');
	pg_assert_same(41, $result['ctx']['percent'], 'its share of the window');
	pg_assert_false($result['ctx']['high'], 'under 90% is not marked');
	pg_assert_true($result['ctxHigh']['high'], 'from 90% it is marked');
	pg_assert_same(array(null, null), $result['ctxBad'], 'no context, or a window of none, shows none');

	pg_assert_same('Working', $result['working']['state'], 'the state\'s words');
	pg_assert_same('act_asked_by(Ayşe)', $result['working']['asked'], 'who asked');
	pg_assert_same(55, $result['working']['message_id'], 'linked to the message');
	pg_assert_same(array('since 09:12', 'Running the tests'), $result['working']['request'], 'since when, and the progress note');
	pg_assert_same(array('opus', 'plan/x', 'reported 12 min ago'), $result['working']['report'], 'model, branch and when it reported');
	pg_assert_false($result['working']['stale'], 'a fresh report is not muted');

	pg_assert_same(null, $result['quiet']['asked'], 'a ready agent asked by nobody');
	pg_assert_same(array(), $result['quiet']['request'], 'and holds no request');
	pg_assert_same(array(), $result['quiet']['report'], 'an agent that never reported says nothing of it');
	pg_assert_same(null, $result['quiet']['context'], 'nor of its context');

	pg_assert_same(null, $result['other']['asked'], 'a request of another channel names nobody');
	pg_assert_same(null, $result['other']['message_id'], 'and links no message');
	pg_assert_same(array('since 10:00'), $result['other']['request'], 'an empty note is left out');
	pg_assert_same(array('sonnet', 'reported 2 d ago'), $result['other']['report'], 'a null branch is left out');
	pg_assert_true($result['other']['stale'], 'a report older than a day is muted');
}

function test_workspace_channel_activity_js_brake()
{
	$brake = workspace_channel_activity_js_test_function('activityBrake');

	pg_assert_true($brake !== '', 'activityBrake() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($brake . '
var now = 1000000;
console.log(JSON.stringify({
	first: activityBrake(0, now),
	soon: activityBrake(now - 4000, now),
	just: activityBrake(now - 15000, now),
	later: activityBrake(now - 60000, now),
	clock: activityBrake(now + 5000, now)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(0, $result['first'], 'the first change reads at once');
	pg_assert_same(11000, $result['soon'], 'a change within 15 seconds waits for the rest of them');
	pg_assert_same(0, $result['just'], 'after 15 seconds it reads at once');
	pg_assert_same(0, $result['later'], 'and long after');
	pg_assert_same(15000, $result['clock'], 'a clock turned back waits at most 15 seconds');
}

function test_workspace_channel_activity_js_hooks()
{
	$source = workspace_channel_activity_js_test_source();
	$screen = workspace_channel_activity_js_test_source('workspace.js');

	pg_assert_true($source !== '', 'the tab has its own script');
	pg_assert_contains('window.PGWsChannelActivity', $source, 'it is offered to workspace.js');
	pg_assert_contains("'ws_channel_activity'", $source, 'it reads the panel action of KA1');
	pg_assert_contains("role', 'tablist'", $source, 'the tabs are a tablist');
	pg_assert_contains('pg-seg', $source, 'drawn as the segmented control');
	pg_assert_same(0, preg_match('/btn btn-|nav-tabs|nav-pills|nav-underline/', $source), 'no old buttons or tab strips');
	pg_assert_contains('60000', $source, 'read every minute while open, for the ages');

	pg_assert_contains('window.PGWsChannelActivity.mount(', $screen, 'drawInfo() mounts the tabs');
	pg_assert_contains('window.PGWsChannelActivity.changed(', $screen, 'the channel\'s sync tells the tab of a change');

	$bundles = pg_js_bundles();

	pg_assert_true(in_array('assets/js/workspace_channel_activity.js', $bundles['assets/js/workspace.min.js'], true), 'the script is in the Workspace bundle');
	pg_assert_true(
		array_search('assets/js/workspace_channel_activity.js', $bundles['assets/js/workspace.min.js'], true)
			=== array_search('assets/js/workspace_channel_agents.js', $bundles['assets/js/workspace.min.js'], true) + 1,
		'beside the agents\' script'
	);
}

function test_workspace_channel_activity_js_strings()
{
	$strings = ws_channel_activity_js_strings();

	foreach (array('act_tab_info', 'act_tab_activity', 'act_context', 'act_asked_by', 'act_all_decisions', 'act_more') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' has its words');
	}

	pg_assert_contains('{1}', $strings['act_context'], 'the context\'s words take the numbers');
	pg_assert_contains('ws_channel_activity_js_strings()', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), 'the screen sends them');
}
