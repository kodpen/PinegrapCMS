<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ; list of saved replies in the e-mail reply drawer
 * (assets/js/workspace_mail.js). Run under Node: plain() is the reply as
 * plain text for an e-mail, its tags by their names and its lines kept.
 * The rest holds the wiring: the list comes from window.PGWsReplies (the
 * channel composer's at(), match() and cached()), only where saved replies
 * are on, and the pick replaces ";word" in the textarea.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_ws_mail_saved_reply_plain()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$cases = array(
		array('text' => "Hello Ayşe,\n\nYour order 1042 is on its way.", 'labels' => array()),
		array('text' => 'Ask <@user:7> or see <#channel:3>.', 'labels' => array('<@user:7>' => 'Ali', '<#channel:3>' => 'support')),
		array('text' => 'Unknown <@user:9> tag', 'labels' => array()),
		array('body' => 'Body {customer}'),
		array(),
	);
	$script = "'use strict';\nvar window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js')
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return window.PGWsMail.plain(one); })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsmail');
	file_put_contents($file, $script);
	$result = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $result, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $result));
	pg_assert_same(array(
		"Hello Ayşe,\n\nYour order 1042 is on its way.",
		'Ask Ali or see support.',
		'Unknown  tag',
		'Body {customer}',
		'',
	), json_decode(implode("\n", $result), true));
}

function test_ws_mail_saved_reply_wiring()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains('savedReplies(text, box, view, h);', $js, 'the reply drawer opens the list');
	pg_assert_contains('!h.cfg || !h.cfg.saved_replies', $js, 'only where saved replies are on');
	pg_assert_contains('R.at(text.value.slice(0, caret))', $js, 'the same ; rule as the composer');
	pg_assert_contains('R.cached(channelId, h.api, check)', $js, 'the list filled for the channel');
	pg_assert_contains('R.match(replies || [], semi.word, 8)', $js, 'the same order as the composer');
	pg_assert_contains("setSelectionRange(start, caret)", $js, 'the pick replaces ;word');

	$css = file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.ws-mail-reply-box .ws-picker', $css, 'the list opens under the answer');
}
