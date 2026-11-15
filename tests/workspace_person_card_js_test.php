<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's card in a channel (personCardBody() in
 * assets/js/workspace.js, the facts of ws_person_card). Run under Node:
 * personCardDays() writes the working days, a run of three or more as one
 * span. The rest holds the wiring: the card is drawn in the agent card's
 * frame, it opens on a person's picture and name in a message and in
 * Channel info, and its texts are given.
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

function workspace_person_card_js_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent, or ''.
function workspace_person_card_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_person_card_js_test_source(), $match) ? $match[0] : '';
}

// The working days: Monday is 1; a run of three or more days is one span.
function test_workspace_person_card_days()
{
	$days = workspace_person_card_js_test_function('personCardDays');

	pg_assert_true($days !== '', 'personCardDays() is in workspace.js');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgpcard');
	file_put_contents($file, $days . '
var names = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
console.log(JSON.stringify([
	personCardDays([1, 2, 3, 4, 5], names),
	personCardDays([1, 2, 3, 4, 5, 6, 7], names),
	personCardDays([1, 3, 5], names),
	personCardDays([1, 2, 4, 5, 6], names),
	personCardDays([], names),
	personCardDays([6, 7], names)
]));');
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	pg_assert_same(array('Mon–Fri', 'Mon–Sun', 'Mon, Wed, Fri', 'Mon, Tue, Thu–Sat', '', 'Sat, Sun'), json_decode($printed, true));
}

// The person's card is drawn in the agent card's frame: one card at a
// time, placed and closed the same way.
function test_workspace_person_card_frame()
{
	$show = workspace_person_card_js_test_function('cardShow');
	$hook = workspace_person_card_js_test_function('cardHook');
	$person = workspace_person_card_js_test_function('personCardShow');
	$body = workspace_person_card_js_test_function('personCardBody');

	pg_assert_true($show !== '' && $hook !== '', 'one show and one hook for both cards');
	pg_assert_contains('agentCardPlace(', $show, 'placed as the agent card');
	pg_assert_contains("api('ws_person_card', { channel_id: channel.id, user_id: person.id })", $person);
	pg_assert_contains('cardShow(', workspace_person_card_js_test_function('agentCardShow'), 'the agent card uses the same frame');
	pg_assert_contains('agentCardRow(', $body, 'the agent card\'s rows');
	pg_assert_contains('directMessage(', $body, 'Message writes to the person');
	pg_assert_contains('personEditUrl(', $body, 'the account only for whoever may change it');
}

// It opens on a person's picture and name in a message and in Channel info;
// a guest, an application or somebody gone has none.
function test_workspace_person_card_hooks()
{
	$source = workspace_person_card_js_test_source();
	$hook = workspace_person_card_js_test_function('personCardHook');

	pg_assert_true($hook !== '', 'personCardHook() is in workspace.js');
	pg_assert_contains('person.guest || person.gone', $hook, 'no card for a guest or somebody gone');
	pg_assert_true(substr_count($source, 'personCardHook(') >= 5, 'a message\'s picture and name, Channel info\'s face and name');
}

function test_workspace_person_card_texts()
{
	$texts = ws_person_card_js_strings();

	foreach (array('pc_departments', 'pc_leads_it', 'pc_led_by', 'pc_workday', 'pc_local_time', 'pc_away', 'pc_away_now', 'pc_away_mail', 'pc_message', 'pc_days') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}

	pg_assert_same(7, count($texts['pc_days']), 'a short name for each day, Monday first');
	pg_assert_contains('ws_person_card_js_strings()', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), 'the screen gives them');
}

// api.php answers only the actions its table lists: the card's read is in
// it, or the card closes on "Invalid action." as soon as it opens.
function test_workspace_person_card_actions_reach_api()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	$row = pg_panel_action('ws_person_card');

	pg_assert_true(is_array($row), 'ws_person_card is in the panel\'s action table');
	pg_assert_same('pg_panel_ws', is_array($row) ? $row['handler'] : '', 'it goes to the workspace handler');
}
