<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent card's behaviour in workspace.js: which scroll closes
 * the open card (only the reader's own scroll of the list holding its
 * anchor), which keys count as scrolling, and that a failed load or a
 * drawing error keeps the card open. The Node parts are skipped where Node
 * cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of workspace.js.
function workspace_agent_card_js_test_source()
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent; '' when it is not there.
function workspace_agent_card_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_agent_card_js_test_source(), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_agent_card_js_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgcardjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_agent_card_js_scroll_closes()
{
	$closes = workspace_agent_card_js_test_function('agentCardScrollCloses');
	$key = workspace_agent_card_js_test_function('agentCardScrollKey');

	pg_assert_true($closes !== '', 'agentCardScrollCloses() is in workspace.js');
	pg_assert_true($key !== '', 'agentCardScrollKey() is in workspace.js');

	$result = workspace_agent_card_js_test_node($closes . $key . '
var now = 100000;
console.log(JSON.stringify({
	wheel: agentCardScrollCloses(true, now - 50, false, now),
	script: agentCardScrollCloses(true, -Infinity, false, now),
	late: agentCardScrollCloses(true, now - 5000, false, now),
	bar: agentCardScrollCloses(true, -Infinity, true, now),
	other: agentCardScrollCloses(false, now - 50, true, now),
	page: agentCardScrollKey("PageDown", false),
	arrow: agentCardScrollKey("ArrowUp", false),
	typing: agentCardScrollKey(" ", true),
	letter: agentCardScrollKey("a", false)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_true($result['wheel'], 'a wheel on the list holding the anchor closes it');
	pg_assert_false($result['script'], 'a scroll the screen makes for a new message does not');
	pg_assert_false($result['late'], 'nor one long after the last wheel');
	pg_assert_true($result['bar'], 'a dragged scrollbar closes it');
	pg_assert_false($result['other'], 'a list that does not hold the anchor never closes it');
	pg_assert_true($result['page'], 'Page Down scrolls');
	pg_assert_true($result['arrow'], 'an arrow key scrolls');
	pg_assert_false($result['typing'], 'a space typed in a field does not');
	pg_assert_false($result['letter'], 'a letter does not');
}

function test_workspace_agent_card_js_failed_load_keeps_card()
{
	$show = workspace_agent_card_js_test_function('agentCardShow');
	$frame = workspace_agent_card_js_test_function('cardShow');
	$refused = workspace_agent_card_js_test_function('agentCardRefused');

	pg_assert_true($show !== '', 'agentCardShow() is in workspace.js');
	pg_assert_true($frame !== '', 'cardShow() is in workspace.js');
	pg_assert_true($refused !== '', 'agentCardRefused() is in workspace.js');

	// The failed load writes the refusal on the card and never closes it;
	// cardShow() loads every card, the agent's and a person's.
	pg_assert_contains('agentCardRefused(node, error && error.message)', $frame, 'a refusal is written on the card');
	pg_assert_same(0, preg_match('/catch\(function \([a-z]*\) \{\s*if \(agentCardOpen\.node === node\) \{\s*agentCardClose\(\);/', $frame), 'a failed load does not close the card');
	pg_assert_contains("querySelector('.ws-agent-card-loading')", $refused, 'the words take the place of Loading…');

	// The drawing runs in its own guard and reports to the console.
	$guard = workspace_agent_card_js_test_function('agentCardGuard');

	pg_assert_contains('console.error(error)', $guard, 'a drawing error goes to the console');
	pg_assert_contains('agentCardGuard(function () { agentCardBody(node, agent, card, channel); })', $show, 'the drawing is guarded');
}
