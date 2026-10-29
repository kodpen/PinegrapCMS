<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the composer's ; list of saved replies
 * (assets/js/workspace_replies.js). Run under Node: at() answers whether the
 * list may open at the caret, match() which replies a typed word picks,
 * preview() a reply's one-line text. The rest holds the wiring in
 * workspace.js and the writing box: the channel's composer only, never in
 * code, a link or a card nor while a word is composed, and the pick is one
 * undo step with the tags as chips.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the expression on each case under Node with window.PGWsReplies as R;
// null when Node is not installed.
function workspace_saved_replies_picker_run($expression, $cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\nvar window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_replies.js')
		. "\nvar R = window.PGWsReplies;\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return " . $expression . "; })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsreply');
	file_put_contents($file, $script);
	$result = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $result, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $result));

	return json_decode(implode("\n", $result), true);
}

function test_ws_saved_replies_picker_at()
{
	$cases = array(
		';'              => array('start' => 0, 'word' => ''),
		';th'            => array('start' => 0, 'word' => 'th'),
		'Hello ;kargo-2' => array('start' => 6, 'word' => 'kargo-2'),
		"line\n;iade"    => array('start' => 5, 'word' => 'iade'),
		"\u{FFFC};teş"   => array('start' => 1, 'word' => 'teş'),
		'a;b'            => null,
		'&amp;'          => null,
		'; '             => null,
		';a.b'           => null,
		';' . str_repeat('a', 33) => null,
	);
	$got = workspace_saved_replies_picker_run('R.at(one)', array_keys($cases));

	if ($got === null) {
		return;
	}

	foreach (array_values($cases) as $index => $want) {
		pg_assert_same($want, $got[$index], json_encode(array_keys($cases)[$index]));
	}
}

function test_ws_saved_replies_picker_match()
{
	$items = array(
		array('shortcut' => 'iade', 'title' => 'Return steps'),
		array('shortcut' => 'kargo', 'title' => 'İade kargosu'),
		array('shortcut' => 'thanks', 'title' => 'Thank you'),
	);
	$got = workspace_saved_replies_picker_run('R.match(one.items, one.word, one.limit).map(function (item) { return item.shortcut; })', array(
		array('items' => $items, 'word' => '', 'limit' => 8),
		array('items' => $items, 'word' => 'İA', 'limit' => 8),
		array('items' => $items, 'word' => 'th', 'limit' => 8),
		array('items' => $items, 'word' => '', 'limit' => 2),
		array('items' => $items, 'word' => 'zz', 'limit' => 8),
	));

	if ($got === null) {
		return;
	}

	pg_assert_same(array('iade', 'kargo', 'thanks'), $got[0], 'nothing typed: every reply');
	pg_assert_same(array('iade', 'kargo'), $got[1], 'shortcut first, then a title holding it, Turkish lower case');
	pg_assert_same(array('thanks'), $got[2]);
	pg_assert_same(array('iade', 'kargo'), $got[3], 'at most limit');
	pg_assert_same(array(), $got[4]);
}

function test_ws_saved_replies_picker_preview()
{
	$got = workspace_saved_replies_picker_run('R.preview(one.item, one.length)', array(
		array('item' => array('text' => "Hi <@user:4>,\n\nthanks", 'labels' => array('<@user:4>' => 'Ayşe')), 'length' => 80),
		array('item' => array('text' => 'abcdefghij'), 'length' => 5),
	));

	if ($got === null) {
		return;
	}

	pg_assert_same('Hi Ayşe, thanks', $got[0], 'tags by their names, one line');
	pg_assert_same('abcd…', $got[1]);
}

function test_ws_saved_replies_picker_wiring()
{
	$app = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_editor.js');

	pg_assert_contains('self.savedReplies = true;', $app, 'the channel and thread composer');
	pg_assert_contains('(self.savedReplies && self.rich && CFG.saved_replies && window.PGWsReplies && !self.rich.node.wsComposing && !self.rich.caretBlocked())', $app, 'not in code, a link or a card, nor while composing');
	pg_assert_contains("this.replySkip = this.pickerStart;", $app, 'Escape leaves that ;');
	pg_assert_contains('this.rich.insertStored(typedReply, item.reply.text, item.reply.labels || {});', $app, 'the filled text with its chips');
	pg_assert_contains("window.PGWsReplies.drawer(self, featureHelpers());", $app, 'the Insert menu opens the list');

	$stored = substr($editor, (int) strpos($editor, 'insertStored: function'), 700);
	pg_assert_contains('return self.change(function () {', $stored, 'one undo step');
	pg_assert_contains('self.replaceTyped(count, { text: \'\' });', $stored, 'the ;word goes inside that step');
	pg_assert_contains('markupNodes(blocks, labels || {})', $editor);

	pg_assert_contains("'assets/js/workspace_replies.js',\n            'assets/js/workspace.js',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'bundled before workspace.js');
	pg_assert_contains("'saved_replies' => ws_saved_replies_ready(),", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'));

	foreach (array('ws_saved_replies', 'ws_saved_reply_save', 'ws_saved_reply_delete') as $action) {
		pg_assert_contains("'" . $action . "'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'), $action . ' reaches the workspace handler');
	}
}
