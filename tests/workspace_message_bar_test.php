<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for a channel message's look (assets/js/workspace.js): the
 * bar over a message keeps a reaction, Reply, the discussion and Make a task
 * of it, in that order, with every other action in More; the discussion
 * under a message reads as one line from its texts.
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

// The body of one method of the channel screen in workspace.js, or ''.
function workspace_message_bar_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        ' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// The actions messageActions() puts on the bar: each label key with its place.
function test_workspace_message_bar_holds_the_answers_and_a_task()
{
	$body = workspace_message_bar_test_method('messageActions');
	$bar = array();

	pg_assert_true($body !== '', 'messageActions() is found');
	preg_match_all('/items\.push\(\{[^\n]*?\btool: ([0-9]+)[^\n]*/', $body, $found, PREG_SET_ORDER);

	foreach ($found as $line) {
		preg_match_all("/\\bt\\('([a-z0-9_]+)'\\)/", $line[0], $keys);

		foreach ($keys[1] as $key) {
			$bar[$key] = (int) $line[1];
		}
	}

	ksort($bar);
	pg_assert_same(array('reply' => 1, 'task_from_message' => 3, 'th_join' => 2, 'th_open' => 2, 'th_start' => 2), $bar, 'only Reply, the discussion and Make a task of it are on the bar; the rest wait in More');
	pg_assert_true(strpos($body, 'tool: true') === false, 'a place on the bar is a number, its order');
}

// The bar draws the reaction picker, the actions in their order, then More;
// no quick emojis.
function test_workspace_message_bar_draws_them_in_order()
{
	$body = workspace_message_bar_test_method('messageTools');

	pg_assert_true($body !== '', 'messageTools() is found');
	pg_assert_contains("button('', '', 'bi-emoji-smile', t('add_reaction'))", $body);
	pg_assert_contains('return a.tool - b.tool;', $body);
	pg_assert_contains("button('', '', 'bi-three-dots', t('more'))", $body);
	pg_assert_true(strpos($body, 'BOOT.reactions') === false, 'no quick emojis on the bar');
}

// The discussion line: its reply count and the last reply, or none yet; the
// title for the tooltip and the screen reader.
function test_workspace_message_discussion_line_texts()
{
	$texts = ws_threads_js_strings();
	$body = workspace_message_bar_test_method('threadBar');

	foreach (array('th_bar', 'th_bar_none', 'th_bar_reply', 'th_bar_replies', 'th_bar_last_reply', 'th_bar_closed') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
		pg_assert_contains("t('" . $key . "'", $body, $key . ' is used');
	}

	pg_assert_true(!isset($texts['th_bar_count']) && !isset($texts['th_bar_last']), 'the card\'s old texts are gone');
	pg_assert_contains("el('span', 'visually-hidden', t('th_bar', thread.title) + ':')", $body, 'the title is read out first');
}
