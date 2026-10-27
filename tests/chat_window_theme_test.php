<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the panel chat window (pg_chat_render_backend_launcher()
 * in chat.php). It is printed only on panel screens, which load the --pg-*
 * token layer of backend.src.css; Bootstrap 6 drops or renames most v5
 * variables, so a var(--bs-*) read there falls back to its light default
 * in dark mode too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, from its declaration to the next one.
function chat_window_theme_function_source($path, $name)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
	$start = strpos($source, "\nfunction " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_chat_window_reads_only_panel_tokens()
{
	$source = chat_window_theme_function_source('chat.php', 'pg_chat_render_backend_launcher');

	pg_assert_true($source !== '', 'pg_chat_render_backend_launcher() is found in chat.php');
	pg_assert_true(strpos($source, '#pg-chat-root') !== false, 'the launcher styles are in pg_chat_render_backend_launcher()');
	pg_assert_true(
		preg_match_all('/var\(\s*--bs-[a-z0-9-]+/', $source, $matches) === 0,
		'the chat window reads Bootstrap variables: ' . implode(', ', array_unique($matches[0])));
}
