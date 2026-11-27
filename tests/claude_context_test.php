<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/claude_context.php: the project folder named from a
 * working directory, the used tokens read from a transcript's lines, the
 * newest transcript, and --context=auto of tools/ws_profile.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/claude_context.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_profile.php');

function claude_context_test_line($usage, $extra = array())
{
	return json_encode($extra + array('type' => 'assistant', 'isSidechain' => false, 'message' => array('model' => 'm', 'role' => 'assistant', 'usage' => $usage)));
}

// Every character but a letter or a digit becomes a dash, as Claude Code
// names a working directory's folder under ~/.claude/projects/.
function test_claude_context_folder_name()
{
	pg_assert_same('-home-user-PinegrapCMS', claude_context_folder_name('/home/user/PinegrapCMS'));
	pg_assert_same('-srv-my-site-v2-0', claude_context_folder_name('/srv/my_site/v2.0'));
	pg_assert_same('-a', claude_context_folder_name('/a/'), 'a trailing slash is dropped');
}

// The last assistant message's input, cache creation and cache read tokens
// together; output tokens are not counted.
function test_claude_context_used_sums_the_last_usage()
{
	$lines = array(
		claude_context_test_line(array('input_tokens' => 10, 'cache_creation_input_tokens' => 20, 'cache_read_input_tokens' => 30, 'output_tokens' => 5)),
		json_encode(array('type' => 'user', 'message' => array('role' => 'user', 'content' => 'hi'))),
		claude_context_test_line(array('input_tokens' => 2, 'cache_creation_input_tokens' => 679, 'cache_read_input_tokens' => 87354, 'output_tokens' => 234)),
		json_encode(array('type' => 'attachment')),
	);

	pg_assert_same(88035, claude_context_used($lines));
}

// Lines that do not read, a sub-agent's messages, a synthetic message and a
// usage without tokens never stand for the session's context.
function test_claude_context_used_skips_what_is_not_the_session()
{
	$good = claude_context_test_line(array('input_tokens' => 1, 'cache_creation_input_tokens' => 2, 'cache_read_input_tokens' => 3));

	pg_assert_same(6, claude_context_used(array(
		$good,
		'{not json',
		'',
		claude_context_test_line(array('input_tokens' => 900), array('isSidechain' => true)),
		json_encode(array('type' => 'assistant', 'message' => array('model' => '<synthetic>', 'usage' => array('input_tokens' => 0)))),
		claude_context_test_line(array('input_tokens' => 0, 'cache_read_input_tokens' => 0)),
		claude_context_test_line(array('input_tokens' => '5')),
	)));
}

// Nothing to read gives null, never 0.
function test_claude_context_used_nothing()
{
	pg_assert_same(null, claude_context_used(array()));
	pg_assert_same(null, claude_context_used(array('{"type":"user"}', 'x')));
}

// The newest transcript by modification time; nothing for no file.
function test_claude_context_newest()
{
	pg_assert_same('/p/b.jsonl', claude_context_newest(array('/p/a.jsonl' => 100, '/p/b.jsonl' => 300, '/p/c.jsonl' => 200)));
	pg_assert_same(null, claude_context_newest(array()));
}

// The transcript read from a folder: the newest .jsonl of the cwd's
// project folder; no folder or no usage gives null.
function test_claude_context_read_from_folder()
{
	$base = sys_get_temp_dir() . '/pg_claude_context_' . getmypid();
	$folder = $base . '/projects/' . claude_context_folder_name('/w/site');
	@mkdir($folder, 0700, true);
	file_put_contents($folder . '/old.jsonl', claude_context_test_line(array('input_tokens' => 7)) . "\n");
	touch($folder . '/old.jsonl', time() - 100);
	file_put_contents($folder . '/new.jsonl', claude_context_test_line(array('input_tokens' => 40, 'cache_read_input_tokens' => 2)) . "\n" . '{"type":"user"}' . "\n");

	pg_assert_same(42, claude_context_read($base, '/w/site'));
	pg_assert_same(null, claude_context_read($base, '/w/other'), 'no project folder');

	file_put_contents($folder . '/new.jsonl', '{"type":"user"}' . "\n");
	pg_assert_same(null, claude_context_read($base, '/w/site'), 'the newest has no usage');

	foreach (glob($folder . '/*') as $file) {
		unlink($file);
	}

	rmdir($folder);
	rmdir($base . '/projects');
	rmdir($base);
}

// --context=auto takes what the reader found, or drops the context.
function test_ws_profile_context_auto()
{
	pg_assert_same(array('model' => 'm', 'context' => '88035'), ws_profile_context_auto(array('model' => 'm', 'context' => 'auto'), 88035));
	pg_assert_same(array('model' => 'm'), ws_profile_context_auto(array('model' => 'm', 'context' => 'auto'), null), 'nothing found sends no context');
	pg_assert_same(array('context' => '5'), ws_profile_context_auto(array('context' => '5'), 99), 'a given number is kept');
}

// With --context=auto alone and nothing read, ws_profile sends nothing.
function test_ws_profile_other_fields()
{
	pg_assert_true(!ws_profile_other_fields(array('context' => 'auto', 'skip-missing' => true)));
	pg_assert_true(!ws_profile_other_fields(array('model' => ' ')));
	pg_assert_true(ws_profile_other_fields(array('branch' => 'b')));
}
