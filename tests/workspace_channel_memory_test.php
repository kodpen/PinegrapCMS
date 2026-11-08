<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's memory (includes/workspace/channel_memory.php) that
 * need no database: an entry's tags and checks, and the brief's digest.
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

function test_workspace_memory_tags()
{
	pg_assert_same(array('release', 'ci'), ws_memory_tags('#Release, ci  release'));
	pg_assert_same(array('a', 'b'), ws_memory_tags(array(' A ', 'b', '', 'a')));
	pg_assert_same(array(), ws_memory_tags(''));
}

function test_workspace_memory_entry_problem()
{
	$now = 1000;
	$ok = array('title' => 'Merge window', 'text' => 'Trains land at 08:00.', 'tags' => array('merge'), 'expires_at' => 0);

	pg_assert_same('', ws_memory_entry_problem($ok, $now));
	pg_assert_same('', ws_memory_entry_problem(array('title' => 'x', 'expires_at' => 1001), $now));
	pg_assert_true(ws_memory_entry_problem(array('title' => '  '), $now) !== '', 'a title is needed');
	pg_assert_true(ws_memory_entry_problem(array('title' => str_repeat('a', 151)), $now) !== '', 'title too long');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'text' => str_repeat('a', 4001)), $now) !== '', 'text too long');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'tags' => range(1, 11)), $now) !== '', 'too many tags');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'tags' => array('two words')), $now) !== '', 'a tag is one word');
	pg_assert_true(ws_memory_entry_problem(array('title' => 'x', 'expires_at' => 1000), $now) !== '', 'expiry in the past');
}

function test_workspace_memory_digest()
{
	$now = 1000;
	$entries = array(
		array('title' => 'Old', 'text' => 'first', 'tags' => array(), 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 10),
		array('title' => 'Gone', 'text' => 'expired', 'tags' => array(), 'pinned' => 1, 'expires_at' => 900, 'updated_at' => 50),
		array('title' => 'New', 'text' => "two\nlines", 'tags' => array('ci'), 'pinned' => 0, 'expires_at' => 0, 'updated_at' => 30),
		array('title' => 'Pin', 'text' => '', 'tags' => array(), 'pinned' => 1, 'expires_at' => 2000, 'updated_at' => 5),
	);
	$decisions = array(array('body' => 'Ship on Friday', 'marked_at' => 20), array('body' => '  ', 'marked_at' => 10));

	pg_assert_same("Channel memory:\n- Pin\n- New [ci]: two lines\n- Old: first\n\nDecisions in this channel:\n- Ship on Friday",
		ws_memory_digest($entries, $decisions, $now));
	pg_assert_same('', ws_memory_digest(array(), array(), $now));

	// A line that does not fit is left out whole, with the lines after it, and counted.
	$small = ws_memory_digest($entries, $decisions, $now, 40);
	pg_assert_same("Channel memory:\n- Pin\n\n(3 more not shown: read the channel's memory and decisions.)", $small);
}
