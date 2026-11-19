<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A reply's quote line over its text (messageNode() in workspace.js) is cut
 * with an ellipsis in a narrow column; its title carries the whole quoted
 * text on the first line and what a click does on the second
 * (quoteTitle()), so the cut words read without jumping.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_reply_quote_title_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/workspace.js');
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_reply_quote_title_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgquotejs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_reply_quote_title_text_then_action()
{
	$source = workspace_reply_quote_title_source();
	$found = preg_match('/\n    function quoteTitle\([^)]*\) \{\n.*?\n    \}\n/s', $source, $match);

	pg_assert_true($found === 1, 'quoteTitle() is in workspace.js');

	if ($found !== 1) {
		return;
	}

	$result = workspace_reply_quote_title_node($match[0] . '
console.log(JSON.stringify({
	both: quoteTitle("Ayşe: Önceki mesajın uzun alıntısı", "Show the message this answers"),
	trimmed: quoteTitle("  Ayşe: kısa  ", "Show the message this answers"),
	empty: quoteTitle("", "Show the message this answers"),
	none: quoteTitle(null, "Show the message this answers")
}));');

	if ($result === null) {
		pg_assert_true(true, 'Node is not available; the source checks above stand');
		return;
	}

	pg_assert_same("Ayşe: Önceki mesajın uzun alıntısı\nShow the message this answers", $result['both'], 'the whole quote, then the action on its own line');
	pg_assert_same("Ayşe: kısa\nShow the message this answers", $result['trimmed'], 'the quote is trimmed');
	pg_assert_same('Show the message this answers', $result['empty'], 'no quote: the action alone');
	pg_assert_same('Show the message this answers', $result['none'], 'a missing quote: the action alone');
}

function test_workspace_reply_quote_title_used_by_both_quote_lines()
{
	$source = workspace_reply_quote_title_source();

	pg_assert_same(2, substr_count($source, "title = quoteTitle("), 'the quote line and each further quote take their title from quoteTitle()');
	pg_assert_true(strpos($source, "quote.title = t('reply_go_to');") === false, 'the action is no longer the whole title');
}
