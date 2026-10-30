<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish fold of the Workspace composer pickers: the @ and #
 * pickers in workspace.js, the saved-reply ; list in workspace_replies.js
 * and the tool filter of Workspace Settings › MCP fold both the typed text
 * and the compared text, so İ, I, ı and i match alike whatever the
 * browser's language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_picker_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Runs match() of workspace_replies.js under node; false when node is
// missing, null when the function is not found.
function workspace_picker_fold_test_replies($items, $words)
{
	$source = workspace_picker_fold_test_source('assets/js/workspace_replies.js');

	if (!preg_match('/^    function foldText\(value\) \{.*?^    \}$/ms', $source, $fold)
		|| !preg_match('/^    function match\(items, word, limit\) \{.*?^    \}$/ms', $source, $match)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsfold');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, 'var window = {};' . "\n" . $fold[0] . "\n" . $match[0] . "\n"
		. 'var items = ' . json_encode($items) . ';' . "\n"
		. 'console.log(JSON.stringify(' . json_encode($words) . '.map(function (w) { return match(items, w, 10).map(function (i) { return i.shortcut; }); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

// A saved reply is found by its shortcut or title whichever i was typed:
// the server stores a shortcut through ws_lower(), so "IBAN" is "ıban".
function test_workspace_picker_fold_saved_replies()
{
	$found = workspace_picker_fold_test_replies(
		array(
			array('shortcut' => 'ıban', 'title' => 'Bank account'),
			array('shortcut' => 'iade', 'title' => 'İade süreci'),
			array('shortcut' => 'odeme', 'title' => 'IBAN ile ödeme'),
		),
		array('iban', 'IBAN', 'İade', 'iade', 'odeme')
	);

	pg_assert_true($found !== null, 'workspace_replies.js defines foldText() and match()');

	if ($found === false) {
		return;
	}

	pg_assert_same(array(
		array('ıban', 'odeme'),
		array('ıban', 'odeme'),
		array('iade'),
		array('iade'),
		array('odeme'),
	), $found, 'shortcuts first, then titles, with İ, I, ı and i alike');
}

// The @ and # pickers fold both sides with foldText(); toLocaleLowerCase()
// without a locale is left only where case alone is compared.
function test_workspace_picker_fold_composer_pickers()
{
	$js = workspace_picker_fold_test_source('assets/js/workspace.js');

	pg_assert_same(2, substr_count($js, 'var needle = foldText(query);'), 'the people value source and the @ picker fold the typed text');
	pg_assert_contains('return !needle || foldText(text).indexOf(needle) !== -1;', $js, 'the people value source folds each name');
	pg_assert_contains("foldText('ai').indexOf(needle) === 0", $js, 'a typed AI finds Pinegrap AI');
	pg_assert_contains('foldText(member.name).indexOf(needle) !== -1 || foldText(member.username).indexOf(needle) !== -1', $js, 'the @ picker folds member names');
	pg_assert_contains('foldText(item.name).indexOf(needle) !== -1', $js, 'the @ picker folds department names');
	pg_assert_contains('var wanted = foldText(search.trim());', $js, 'the # picker folds what is typed');
	pg_assert_contains('BOOT.ref_types[key].prefixes.map(foldText).indexOf(foldText(prefix[1])) !== -1', $js, 'the # picker folds the record prefix');
	pg_assert_contains('foldText: foldText,', $js, 'foldText() is shared with the other workspace scripts');

	$plain = preg_replace('/function quickEditChanged\(.*?\n    \}\n/s', '', $js);

	pg_assert_true(strpos($plain, '.toLocaleLowerCase()') === false, 'no picker lowercases in the browser\'s language');
}

// The MCP tool filter folds the rows on the server and the typed words in
// the browser.
function test_workspace_picker_fold_mcp_tool_filter()
{
	$php = workspace_picker_fold_test_source('includes/workspace/mcp.php');

	pg_assert_contains('data-ws-mcp-tool="\' . h(ws_fold($name . \' \' . $tool[\'title\'] . \' \' . $description))', $php, 'the tool rows are folded on the server');
	pg_assert_contains('var words = fold(field.value).trim();', $php, 'the typed words are folded');
	pg_assert_true(strpos($php, 'field.value.toLocaleLowerCase()') === false, 'the filter no longer lowercases in the browser\'s language');
}
