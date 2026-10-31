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

// The body of a function in backend.src.js, from its declaration to the first
// line that closes it at the given indent.
function select_commit_test_backend_body($start, $close)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($source, $close, $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

// Edit Comment: an arrow step onto "At a Scheduled Time" shows the date box but
// leaves focus on the select; a pointer pick or Enter moves focus to the box.
function test_select_commit_edit_comment_publish_moves_focus_only_on_a_pick()
{
	$body = select_commit_test_backend_body("\nfunction init_edit_comment_publish() {", "\n}\n");

	pg_assert_true($body !== '', 'init_edit_comment_publish() found');
	pg_assert_true(preg_match("/publish\\.on\\('keydown', function \\(event\\) \\{\\s*if \\(!event\\.altKey && \\(\\['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'\\]\\.indexOf\\(event\\.key\\) !== -1\\)\\) \\{\\s*publish_keyed = true;/", $body) === 1,
		'a navigation key without Alt is recorded');
	pg_assert_true(preg_match("/publish\\.on\\('pointerdown', function \\(\\) \\{\\s*publish_keyed = false;/", $body) === 1,
		'a pointer pick moves focus at once');
	pg_assert_true(preg_match("/if \\(!publish_keyed && window\\.pgFields\\) \\{\\s*window\\.pgFields\\.focus\\(publish_date_and_time\\[0\\]\\);/", $body) === 1,
		'change moves focus only without a navigation key');
	pg_assert_true(preg_match("/event\\.key === 'Enter'\\) && \\(publish\\.val\\(\\) == 'schedule'\\)/", $body) === 1,
		'Enter with the schedule chosen goes to the date box');
	pg_assert_true(preg_match("/publish_keyed = false;\\s*\\}\\s*\\}\\);/", $body) === 1 && (strpos($body, "event.key === 'F4'") !== false) && (strpos($body, "event.key === ' '") !== false),
		'the keys that open the list clear the flag');
}

// List quick edit: an arrow step on a closed choice list waits for Enter, Tab,
// Ctrl+S or leaving the list; a pointer pick saves at once.
function test_select_commit_list_quick_edit_waits_for_a_keyboard_pick()
{
	$body = select_commit_test_backend_body("    function open(cell) {", "\n    document.addEventListener('click'");

	pg_assert_true($body !== '', 'the quick-edit open() found');
	pg_assert_contains('done: false, keyed: false };', $body, 'the edit starts without a navigation key');
	pg_assert_true(preg_match("/if \\(!event\\.altKey && \\(\\['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'\\]\\.indexOf\\(event\\.key\\) !== -1\\)\\) \\{\\s*state\\.keyed = true;/", $body) === 1,
		'a navigation key without Alt is recorded');
	pg_assert_true(preg_match("/\\} else if \\(event\\.altKey \\|\\| \\(event\\.key === 'F4'\\) \\|\\| \\(event\\.key === ' '\\)\\) \\{\\s*state\\.keyed = false;/", $body) === 1,
		'the keys that open the list clear the flag');
	pg_assert_true(preg_match("/control\\.addEventListener\\('pointerdown', function \\(\\) \\{\\s*state\\.keyed = false;/", $body) === 1,
		'a pointer pick saves at once');
	pg_assert_true(preg_match("/control\\.addEventListener\\('change', function \\(\\) \\{\\s*if \\(!state\\.keyed\\) \\{\\s*commit\\(state\\);/", $body) === 1,
		'change saves only without a navigation key');
	pg_assert_true(preg_match("/control\\.addEventListener\\('blur', function \\(\\) \\{\\s*commit\\(state\\);/", $body) === 1,
		'leaving the list still saves');
}
