<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short prompt pasted into Claude's routine
 * (ws_claude_bootstrap_prompt()) and its version, that need no database:
 * where a version sent stands against the site's, the note kept for the
 * settings card and read back from it, what the bootstrap and the
 * instructions the site serves say.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_claude_bootstrap_test_base()
{
	return 'https://shop.example/pinegrap/integration.php';
}

// No version, a broken one, an older one, the current one and a newer one.
function test_workspace_claude_bootstrap_version_state()
{
	pg_assert_same('none', ws_claude_version_state(null, 3), 'nothing sent');
	pg_assert_same('none', ws_claude_version_state('', 3), 'an empty value');
	pg_assert_same('none', ws_claude_version_state(0, 3), 'zero is no version');
	pg_assert_same('none', ws_claude_version_state('2a', 3), 'not a number');
	pg_assert_same('none', ws_claude_version_state(-1, 3), 'below zero');
	pg_assert_same('older', ws_claude_version_state(1, 3), 'an older one');
	pg_assert_same('older', ws_claude_version_state('2', 3), 'a number as text');
	pg_assert_same('current', ws_claude_version_state(3, 3), 'the current one');
	pg_assert_same('current', ws_claude_version_state(4, 3), 'a newer one is not out of date');
	pg_assert_same('current', ws_claude_version_state(WS_CLAUDE_BOOTSTRAP_VERSION, WS_CLAUDE_BOOTSTRAP_VERSION), 'the site\'s own');
}

// The note kept in config.ws_claude_error and read back by the card; the
// error of a start is no note.
function test_workspace_claude_bootstrap_version_note()
{
	pg_assert_same('routine_version:0', ws_claude_version_mark(null), 'no version');
	pg_assert_same('routine_version:0', ws_claude_version_mark('x'), 'a broken one counts as none');
	pg_assert_same('routine_version:2', ws_claude_version_mark(2), 'the version sent');
	pg_assert_same(0, ws_claude_version_noted(ws_claude_version_mark(null)), 'none read back');
	pg_assert_same(2, ws_claude_version_noted(ws_claude_version_mark('2')), 'a version read back');
	pg_assert_same(null, ws_claude_version_noted(''), 'nothing stored');
	pg_assert_same(null, ws_claude_version_noted('claude.ai answered HTTP 500.'), 'the error of a start');
	pg_assert_same(null, ws_claude_version_noted('routine_version:2 and more'), 'only the whole note');
}

// The bootstrap signs in, reads the instructions with its version and
// takes no orders from the fire payload; the queue's work is not in it.
function test_workspace_claude_bootstrap_prompt()
{
	$prompt = ws_claude_bootstrap_prompt(workspace_claude_bootstrap_test_base());

	pg_assert_contains('/workspace/claude/instructions?version=' . WS_CLAUDE_BOOTSTRAP_VERSION, $prompt, 'reads the instructions with its version');
	pg_assert_contains('Site API: ' . workspace_claude_bootstrap_test_base(), $prompt, 'names the site');
	pg_assert_contains('PINEGRAP_KEY', $prompt, 'says how to sign in');
	pg_assert_contains('only names the site and the queue', $prompt, 'the fire payload instructs nothing');
	pg_assert_false(strpos($prompt, '/claim') !== false, 'the queue\'s steps come from the site');
	pg_assert_true(strlen($prompt) < 1200, 'short');
}

// The instructions the site serves read the brief and send the version
// with the queue, so an old pasted prompt is noticed.
function test_workspace_claude_bootstrap_instructions()
{
	$text = ws_claude_routine_prompt(workspace_claude_bootstrap_test_base());

	pg_assert_contains('GET /workspace/claude/requests/{id}/brief', $text, 'step 2b reads the brief');
	pg_assert_contains('GET /workspace/claude/requests?version=' . WS_CLAUDE_BOOTSTRAP_VERSION, $text, 'the queue with the version');
	pg_assert_contains('<<<end name>>>', $text, 'what people wrote is data');
}

// Where the Site API the routine sends stands against this site's: none
// (a bootstrap before version 2 sent no address), other or current.
function test_workspace_claude_bootstrap_site_state()
{
	$base = workspace_claude_bootstrap_test_base();

	pg_assert_same('none', ws_claude_site_state(null, $base), 'nothing sent');
	pg_assert_same('none', ws_claude_site_state('', $base), 'an empty value');
	pg_assert_same('current', ws_claude_site_state($base, $base), 'the same address');
	pg_assert_same('current', ws_claude_site_state(' ' . $base . '/ ', $base), 'a slash or a space around it is the same address');
	pg_assert_same('other', ws_claude_site_state('http://shop.example/pinegrap/integration.php', $base), 'the old scheme');
	pg_assert_same('other', ws_claude_site_state('https://www.shop.example/pinegrap/integration.php', $base), 'another host');
}

// The note keeps the address the routine sent beside the version, and the
// card reads both back; a note without an address still reads.
function test_workspace_claude_bootstrap_site_note()
{
	$base = workspace_claude_bootstrap_test_base();

	pg_assert_same('routine_version:2', ws_claude_version_mark(2), 'no address: the old note');
	pg_assert_same('routine_version:2 site:' . $base, ws_claude_version_mark(2, $base), 'the address sent');
	pg_assert_same(array('version' => 2, 'site' => $base), ws_claude_note_parse(ws_claude_version_mark(2, $base)), 'both read back');
	pg_assert_same(array('version' => 0, 'site' => ''), ws_claude_note_parse(ws_claude_version_mark(null)), 'none read back');
	pg_assert_same(2, ws_claude_version_noted(ws_claude_version_mark(2, $base)), 'the version alone, as before');
	pg_assert_same(null, ws_claude_note_parse('claude.ai answered HTTP 500.'), 'the error of a start');
	pg_assert_same(null, ws_claude_note_parse(''), 'nothing stored');
}

// The bootstrap sends the Site API it was pasted with, so the site notices
// when its own address has changed since; the served instructions name the
// current one.
function test_workspace_claude_bootstrap_sends_its_site()
{
	$base = workspace_claude_bootstrap_test_base();
	$prompt = ws_claude_bootstrap_prompt($base);

	pg_assert_contains('/workspace/claude/instructions?version=' . WS_CLAUDE_BOOTSTRAP_VERSION . '&site=' . rawurlencode($base), $prompt, 'the address goes with the version');
	pg_assert_true(WS_CLAUDE_BOOTSTRAP_VERSION >= 2, 'the bootstrap text changed, so its version was raised');
	pg_assert_contains('current address', ws_claude_routine_prompt($base), 'the instructions name the current Site API');
}

// The instructions' version is a short hash of their text: the same text
// gives the same version, another address another one, and the claim step
// names it.
function test_workspace_claude_instructions_version()
{
	$base = workspace_claude_bootstrap_test_base();
	$version = ws_claude_instructions_version($base);

	pg_assert_true((bool) preg_match('/^[0-9a-f]{8}$/', $version), 'eight hex digits');
	pg_assert_same($version, ws_claude_instructions_version($base), 'the same text, the same version');
	pg_assert_true($version !== ws_claude_instructions_version('https://other.example/integration.php'), 'another address, another text');
	pg_assert_same(substr(sha1(ws_claude_routine_text($base, '')), 0, 8), $version, 'worked out from the text without it');
	pg_assert_contains('/claim with {"instructions": "' . $version . '"}', ws_claude_routine_prompt($base), 'the claim step sends it back');
	pg_assert_contains('409 instructions_outdated', ws_claude_routine_prompt($base), 'and says what a refusal means');
}

// What a claim sends against the site's version: nothing, another or the
// current one.
function test_workspace_claude_instructions_state()
{
	pg_assert_same('none', ws_claude_instructions_state(null, 'ab12cd34'), 'nothing sent');
	pg_assert_same('none', ws_claude_instructions_state('  ', 'ab12cd34'), 'blank');
	pg_assert_same('none', ws_claude_instructions_state(array('ab12cd34'), 'ab12cd34'), 'not a string');
	pg_assert_same('other', ws_claude_instructions_state('ab12cd35', 'ab12cd34'), 'an earlier text');
	pg_assert_same('other', ws_claude_instructions_state('2', 'ab12cd34'), 'the bootstrap version is not the instructions version');
	pg_assert_same('current', ws_claude_instructions_state(' AB12CD34 ', 'ab12cd34'), 'case and spaces do not count');
}

// The claim route checks the version before the dry run stops and before
// it writes; the instructions route hands the version out.
function test_workspace_claude_claim_checks_instructions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_claude.php');
	$start = strpos($source, 'function ws_api_claude_claim(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_true(strpos($body, 'ws_api_claude_instructions_check(') < strpos($body, 'api_dry_run_stop('), 'checked before the dry run stops');
	pg_assert_contains("'instructions_outdated'", $source, 'the error code');
	pg_assert_contains("'instructions_version' => ws_claude_instructions_version()", $source, 'served with the instructions');
	pg_assert_contains("'instructions_version' => 'string'", $source, 'and in the schema');
}
