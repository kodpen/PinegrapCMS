<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card a message chip (<#message:N>) opens in workspace.js:
 * where "Go to the message" leads, that every chip drawn through
 * chipMenus() opens it once, that it is the agent card's frame (hover,
 * focus, Enter, a tap; closing as that card does), that its data is the
 * ws_message_ref action's and that a reader without the right gets the
 * server's sentence alone. The Node part is skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_agent_card_js_test.php');

function test_workspace_message_ref_js_go()
{
	$go = workspace_agent_card_js_test_function('messageRefGo');

	pg_assert_true($go !== '', 'messageRefGo() is in workspace.js');

	$result = workspace_agent_card_js_test_node($go . '
var ref = { url: "/pg/workspace.php?channel=7&message=42", message: { id: 42, channel_id: 7 } };
console.log(JSON.stringify({
	same: messageRefGo(7, ref),
	other: messageRefGo(3, ref),
	none: messageRefGo(0, ref),
	hidden: messageRefGo(7, { url: "", meta: "x" }),
	empty: messageRefGo(7, null)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('kind' => 'jump', 'id' => 42), $result['same'], 'the open channel scrolls to it');
	pg_assert_same(array('kind' => 'url', 'url' => '/pg/workspace.php?channel=7&message=42'), $result['other'], 'another channel opens its address');
	pg_assert_same('url', $result['none']['kind'], 'no channel open: its address');
	pg_assert_same(array('kind' => 'none'), $result['hidden'], 'nothing for a message the reader may not see');
	pg_assert_same(array('kind' => 'none'), $result['empty']);
}

function test_workspace_message_ref_js_card()
{
	$menus = workspace_agent_card_js_test_function('chipMenus');
	$hook = workspace_agent_card_js_test_function('messageRefHook');
	$show = workspace_agent_card_js_test_function('messageRefShow');
	$body = workspace_agent_card_js_test_function('messageRefBody');

	pg_assert_contains("chip.classList.contains('ws-chip-message')", $menus, 'every chip chipMenus() draws');
	pg_assert_contains('messageRefHook(chip)', $menus);
	pg_assert_contains("chip.hasAttribute('data-ws-msgref')", $hook, 'hooked once however often the text is drawn');
	pg_assert_contains('cardHook(chip, function () { messageRefShow(chip, id); }, true)', $hook, 'the agent card\'s opening: hover, focus, Enter, tap');
	pg_assert_contains("cardShow(chip, 'message:' + id, t('mr_title'), 'ws-msgref-card'", $show, 'the agent card\'s frame, one at a time');
	pg_assert_contains("api('ws_message_ref', { message_id: id })", $show, 'the data behind the right on the server');
	pg_assert_contains('agentCardGuard(function () { messageRefBody(node, chip, data); })', $show, 'the drawing is guarded');
	pg_assert_contains("(ref && ref.meta) || t('error_generic')", $body, 'without the message: the server\'s sentence');
	pg_assert_contains("text.scrollHeight > text.clientHeight + 1", $body, 'a long message is cut');
	pg_assert_contains("t('mr_go')", $body, 'Go to the message');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.pg-card.ws-agent-card.ws-msgref-card', $css, 'the float frame of the agent card');
	pg_assert_contains('.ws-msgref-text.ws-msgref-cut', $css);

	$strings = ws_message_ref_js_strings();
	pg_assert_same(lang('Go to the message'), $strings['mr_go']);
}
