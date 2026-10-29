<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for controls that act the moment they change. An arrow key on a closed
 * select changes its value and fires change on every step, so the
 * translation engine select (assets/js/translations.js) and the "Write with
 * AI" tone select (assets/js/pg_ai_write.js) save only on Enter or blur after
 * a navigation key; a pointer pick still saves at once. On Cloudflare Tools
 * the switch forms and Clear Cache carry disable_shortcut, so Ctrl+S never
 * flips a zone setting or purges the cache, and a toggled switch gets focus
 * back after the redirect.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The listeners of a select whose change is held after a navigation key: a
// keydown that records the key and commits on Enter, a change that commits
// only without one, a blur that commits.
function select_commit_test_deferred($source, $select, $flag, $commit, $what)
{
	$keys = "\\['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'\\]";

	pg_assert_true(preg_match('/' . $select . "\\.addEventListener\\('keydown', function \\(event\\) \\{\\s*if \\(" . $keys . '\\.indexOf\\(event\\.key\\) !== -1\\) \\{\\s*' . $flag . ' = true;\\s*\\} else if \\(event\\.key === \'Enter\'\\) \\{\\s*event\\.preventDefault\\(\\);\\s*' . $commit . '\\(\\);/', $source) === 1,
		$what . ': a navigation key holds the commit, Enter commits');
	pg_assert_true(preg_match('/' . $select . "\\.addEventListener\\('change', function \\(\\) \\{\\s*if \\(!" . $flag . '\\) \\{\\s*' . $commit . '\\(\\);/', $source) === 1,
		$what . ': change commits only without a navigation key');
	pg_assert_true(preg_match('/' . $select . "\\.addEventListener\\('blur', " . $commit . '\\);/', $source) === 1,
		$what . ': blur commits');
	pg_assert_true(preg_match('/' . $select . "\\.addEventListener\\('pointerdown', function \\(\\) \\{\\s*" . $flag . ' = false;/', $source) === 1,
		$what . ': a pointer pick commits at once');
}

function test_select_commit_translation_engine_waits_for_a_keyboard_pick()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/translations.js');

	select_commit_test_deferred($source, 'engineSelect', 'engineKeyed', 'saveEngine', 'engine select');
	pg_assert_true(preg_match('/if \(engineSelect\.disabled \|\| \(engineSelect\.value === config\.engine\)\) \{\s*return;/', $source) === 1,
		'the saved engine is not posted again');
}

function test_select_commit_ai_tone_waits_for_a_keyboard_pick()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ai_write.js');

	select_commit_test_deferred($source, 'tone', 'toneKeyed', 'askTone', 'tone select');
	pg_assert_true(preg_match('/if \(tone\.disabled \|\| \(tone\.value === panel\._pgAi\.askedTone\)\) \{\s*return;/', $source) === 1,
		'the tone already asked for takes no draft');
	pg_assert_true(preg_match('/state\.askedTone = state\.tone\.value;\s*setBusy\(panel, true\);/', $source) === 1,
		'each request records its tone');
}

// The control that had focus when a request started gets it back when the
// request ends; disabling it had dropped focus to the page.
function test_select_commit_ai_write_gives_focus_back()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ai_write.js');
	$from = strpos($source, 'function setBusy(panel, busy) {');
	$body = ($from === false) ? '' : substr($source, $from, 900);

	pg_assert_contains('state.focusBack = active;', $body, 'the focused control is remembered');
	pg_assert_contains('state.focusBack.focus();', $body, 'and focused again');
}

function test_select_commit_cloudflare_forms_skip_the_save_shortcut()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/cloudflare.php');

	foreach (array('toggle_dev', 'toggle_uam', 'toggle_always_https', 'toggle_brotli', 'toggle_rocket', 'clear_cache') as $action) {
		pg_assert_true(preg_match('/<form method="post" class="disable_shortcut[^"]*">[^\n]*\n\s*<input type="hidden" name="action" value="' . $action . '">/', $source) === 1,
			$action . ' form is disable_shortcut');
	}

	foreach (array('cf_dev', 'cf_uam', 'cf_https', 'cf_brotli', 'cf_rocket') as $id) {
		pg_assert_contains('id="' . $id . '" onchange="this.form.submit()"\'.$switch_focus(\'' . $id . '\').\'', $source, $id . ' gets focus back');
	}

	pg_assert_contains("\$redirect_query['focus'] = \$switch_ids[\$action];", $source, 'the redirect names the toggled switch');
}
