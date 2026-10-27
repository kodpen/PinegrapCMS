<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Rewrite with AI" in the Workspace composer
 * (assets/js/workspace.js, target ws_message of includes/fn/ai_write.php).
 * aiRewriteTokens() runs under Node: the draft keeps the tags of the
 * person's text and loses those the model made up. The rest holds the
 * wiring: the button follows the screen's config and the box's text, the
 * draft goes in through the box's own undo step, the screen loads the
 * draft panel only where the composer is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_ai_rewrite_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Runs aiRewriteTokens() under Node on each [text, known] and answers what
// it gave; null when Node is not installed.
function workspace_ai_rewrite_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = workspace_ai_rewrite_test_source('assets/js/workspace.js');
	$start = strpos($source, "\n    function aiRewriteTokens(");
	pg_assert_true($start !== false, 'aiRewriteTokens is found');
	$end = strpos($source, "\n    }\n", (int) $start);
	$function = substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";

	$script = "'use strict';\n" . $function
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return aiRewriteTokens(c[0], c[1]); })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsai');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

function test_workspace_ai_rewrite_keeps_known_tags_only()
{
	$answer = workspace_ai_rewrite_test_run(array(
		array('Hi <@user:12>, see <#order:5>.', array('<@user:12>', '<#order:5>')),
		array('Hi <@user:12> and <@user:99>', array('<@user:12>')),
		array('**a** <b>x</b> 3 < 4', array()),
		array('', array()),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same('Hi <@user:12>, see <#order:5>.', $answer[0], 'known tags stay');
	pg_assert_same('Hi <@user:12> and ', $answer[1], 'a made-up tag goes');
	pg_assert_same('**a** <b>x</b> 3 < 4', $answer[2], 'other text stays');
	pg_assert_same('', $answer[3], 'empty');
}

function test_workspace_ai_rewrite_wiring()
{
	$js = workspace_ai_rewrite_test_source('assets/js/workspace.js');
	$screen = workspace_ai_rewrite_test_source('includes/workspace/screen.php');
	$panel = workspace_ai_rewrite_test_source('assets/js/pg_ai_write.js');

	pg_assert_contains("target: 'ws_message', record: 'message'", $js, 'the target');
	pg_assert_contains('if (!CFG.ai_rewrite || !window.PinegrapAiWrite)', $js, 'only where a model is offered');
	pg_assert_contains("!window.PinegrapAiWrite.ready || this.inputEmpty()", $js, 'only with text');
	pg_assert_contains('this.aiRewriteSwap();', $js, 'followed on every input');
	pg_assert_contains('setMarkup(aiRewriteTokens(text, Object.keys(labels)), labels, true)', $js, 'one undo step');
	pg_assert_contains("'tool_ai_rewrite'", $screen, 'the label');
	pg_assert_contains("'ai_rewrite'   => ws_screen_ai_rewrite(\$mode)", $screen, 'the config');
	pg_assert_contains("((\$mode === 'channels') && function_exists('pg_ai_write_engine'))", $screen, 'the channel screen only');
	pg_assert_contains('state.adapter.use(', $panel, 'the panel hands the draft to the box');
	pg_assert_contains("dispatchEvent(new CustomEvent('pg:ai-write-ready'))", $panel, 'the browser model found later');
}
