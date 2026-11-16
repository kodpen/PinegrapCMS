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

	// A tap's card is not closed by the mouse events the tap brings when the
	// layout moves the anchor; it closes on a tap outside, Escape or a scroll.
	$hook_frame = workspace_agent_card_js_test_function('cardHook');
	pg_assert_contains("touched = (event.pointerType === 'touch');", $hook_frame, 'a tap is told apart');
	pg_assert_contains('if (!touched) {', $hook_frame, 'leaving closes only a hover\'s card');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.pg-card.ws-agent-card.ws-msgref-card', $css, 'the float frame of the agent card');
	pg_assert_contains('.ws-msgref-text.ws-msgref-cut', $css);

	$strings = ws_message_ref_js_strings();
	pg_assert_same(lang('Go to the message'), $strings['mr_go']);
}

// One "function name(" of workspace_editor.js up to its closing brace.
function workspace_message_ref_js_test_editor_function($name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js'));

	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', $source, $match) ? $match[0] : '';
}

// A pasted message tag, or the address Copy link gives for a message of
// this site, names the message; anything else does not.
function test_workspace_message_ref_js_paste()
{
	$pasted = workspace_message_ref_js_test_editor_function('pastedMessageRef');

	pg_assert_true($pasted !== '', 'pastedMessageRef() is in workspace_editor.js');

	$result = workspace_agent_card_js_test_node($pasted . '
var home = "/pg/workspace.php", origin = "https://shop.example";
console.log(JSON.stringify({
	token: pastedMessageRef(" <#message:42> ", home, origin),
	link: pastedMessageRef("https://shop.example/pg/workspace.php?channel=7&message=42", home, origin),
	channel: pastedMessageRef("https://shop.example/pg/workspace.php?channel=7", home, origin),
	other_site: pastedMessageRef("https://evil.example/pg/workspace.php?channel=7&message=42", home, origin),
	other_page: pastedMessageRef("https://shop.example/pg/workspace_tasks.php?message=42", home, origin),
	words: pastedMessageRef("see <#message:42>", home, origin),
	order: pastedMessageRef("<#order:42>", home, origin),
	bad: pastedMessageRef("https://shop.example/pg/workspace.php?message=4x2", home, origin)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(42, $result['token'], 'its tag');
	pg_assert_same(42, $result['link'], 'its link on this site');
	pg_assert_same(0, $result['channel'], 'a channel\'s link is no message');
	pg_assert_same(0, $result['other_site'], 'another site\'s link');
	pg_assert_same(0, $result['other_page'], 'another page of this site');
	pg_assert_same(0, $result['words'], 'a tag among words is pasted as text');
	pg_assert_same(0, $result['order'], 'a record tag');
	pg_assert_same(0, $result['bad'], 'no number');
}

// Copy as reference sits in a message's menu beside Copy link and copies the
// tag; only a message box turns a pasted one into the chip, after a link
// put on selected words.
function test_workspace_message_ref_js_copy_and_paste_wiring()
{
	$js = workspace_agent_card_js_test_source();
	$link = strpos($js, "label: t('copy_link'), action: function () {\n                copyText(absoluteUrl(CFG.urls.workspace + '?channel='");
	$copy = strpos($js, "copyText('<#message:' + message.id + '>');");

	pg_assert_true(($link !== false) && ($copy !== false) && ($copy > $link) && ($copy - $link < 800), 'Copy as reference right after Copy link');
	pg_assert_contains("label: t('mr_copy')", $js);
	pg_assert_contains("messageRefs: true,", $js, 'the channel\'s and the discussion\'s box');

	$editor = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js'));
	$selection = strpos($editor, 'if (url && this.linkSelection(url)) {');
	$ref = strpos($editor, 'var ref = this.options.messageRefs ? pastedMessageRef(');

	pg_assert_true(($selection !== false) && ($ref !== false) && ($selection < $ref), 'selected words take the link first');
	pg_assert_contains("this.replaceTyped(0, { token: '<#message:' + ref + '>', shown: t('mr_label', ref), icon: 'bi-chat-quote' });", $editor, 'the chip');
	pg_assert_contains("((type === 'message') && shown)", workspace_message_ref_js_test_editor_function('chipNode'), 'the chip reads as the channel draws it');

	$strings = ws_message_ref_js_strings();
	pg_assert_same(lang('Copy as reference'), $strings['mr_copy']);
	pg_assert_contains('{1}', $strings['mr_label'], 'the number goes into the label');
}
