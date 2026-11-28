<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Translate on a message (assets/js/workspace_translate.js):
 * pieces() and join() run under Node, so tags, links, code, e-mail
 * addresses and pieces without letters are kept and the translated pieces
 * go back in their places. The rest holds the wiring: the menu item is
 * offered only with the browser's translator, the script is in the
 * Workspace bundle, nothing calls the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_message_translate_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Runs the script under Node with a bare window, then each case's pieces()
// and join() (every piece to translate upper-cased); null without Node.
function workspace_message_translate_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\nvar window = {};\n"
		. workspace_message_translate_test_source('assets/js/workspace_translate.js')
		. "\nvar T = window.PGWsTranslate;\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) {\n"
		. "  var list = T.pieces(c);\n"
		. "  return { pieces: list, wanted: T.wanted(list), joined: T.join(list, T.wanted(list).map(function (p) { return p.toUpperCase(); })), same: T.join(list, []) };\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwstr');
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

function test_workspace_message_translate_pieces()
{
	$cases = array(
		'Hi <@user:12>, see <#order:5> today.',
		'Read https://example.com/a?b=1. Then call.',
		'Run `npm test` before ali@example.com writes.',
		'  hello  ',
		'12:30 — 45%',
		'',
		'Merhaba dünya',
	);
	$answer = workspace_message_translate_test_run($cases);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('Hi', ', see', 'today.'), $answer[0]['wanted'], 'tags are not sent to the translator');
	pg_assert_same('HI <@user:12>, SEE <#order:5> TODAY.', $answer[0]['joined'], 'tags stay in their places');

	pg_assert_same(array('Read', '. Then call.'), $answer[1]['wanted'], 'the address ends before the full stop');
	pg_assert_same('READ https://example.com/a?b=1. THEN CALL.', $answer[1]['joined'], 'the address is kept as written');

	pg_assert_same(array('Run', 'before', 'writes.'), $answer[2]['wanted'], 'code and e-mail address kept');
	pg_assert_same('RUN `npm test` BEFORE ali@example.com WRITES.', $answer[2]['joined'], 'code and address in place');

	pg_assert_same(array('hello'), $answer[3]['wanted'], 'spaces around a piece are not translated');
	pg_assert_same('  HELLO  ', $answer[3]['joined'], 'the spaces come back');

	pg_assert_same(array(), $answer[4]['wanted'], 'no letters: nothing to translate');
	pg_assert_same('12:30 — 45%', $answer[4]['joined'], 'kept as it is');

	pg_assert_same(array(), $answer[5]['pieces'], 'an empty text has no pieces');
	pg_assert_same('Merhaba dünya', $answer[6]['same'], 'a missing translation keeps the piece');

	foreach ($cases as $i => $text) {
		pg_assert_same($text, $answer[$i]['same'], 'the pieces join back to the text: ' . $text);
	}
}

function test_workspace_message_translate_wiring()
{
	$script = workspace_message_translate_test_source('assets/js/workspace_translate.js');
	$workspace = workspace_message_translate_test_source('assets/js/workspace.js');

	pg_assert_true(strpos($script, 'window.Translator') !== false && strpos($script, 'window.LanguageDetector') !== false, 'the browser\'s translator and detector');
	pg_assert_false((bool) preg_match('/\b(fetch|XMLHttpRequest|h\.api)\s*\(/', $script), 'nothing goes to the server');
	pg_assert_false(strpos($script, 'localStorage') !== false || strpos($script, 'sessionStorage') !== false, 'nothing is saved');
	pg_assert_true(strpos($workspace, "window.PGWsTranslate && window.PGWsTranslate.supported()") !== false, 'the item only with the translator');
	pg_assert_true(strpos($workspace, "label: t('tr_translate')") !== false, 'the item in the message\'s actions');
	pg_assert_true(strpos($workspace, 'window.PGWsTranslate.apply(message, body') !== false, 'a redraw keeps the translation');

	$bundles = pg_js_bundles();
	pg_assert_true(in_array('assets/js/workspace_translate.js', $bundles['assets/js/workspace.min.js'], true), 'in the Workspace bundle');
}

function test_workspace_message_translate_letters_of_every_script()
{
	// A letter of any script is something to translate: Georgian, Khmer,
	// Ethiopic, Vietnamese letters of Latin Extended Additional, Greek
	// Extended. Signs and digits alone stay nothing.
	$cases = array('გამარჯობა', 'សួស្តី', 'ሰላም', 'Ḁ ỹ', 'ἀ', '१२३ — 45%');
	$answer = workspace_message_translate_test_run($cases);

	if ($answer === null) {
		return;
	}

	foreach (array(0, 1, 2, 3, 4) as $i) {
		pg_assert_same(array($cases[$i]), $answer[$i]['wanted'], 'translated: ' . $cases[$i]);
	}

	pg_assert_same(array(), $answer[5]['wanted'], 'digits and signs only');
}
