<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the panel chat window's text size: the #pg-chat-root
 * style block in chat.php and the inline sizes chat_backend.src.js sets are
 * in rem, so names, messages, times, tabs, chips, the composer and the
 * window itself grow with the browser's default text size.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The wide-screen rules of the #pg-chat-root style block, up to the phone
// layout's media query.
function chat_window_text_size_block()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/chat.php');
	$start = strpos($source, '<div id="pg-chat-root"');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, '@media (max-width: 575.98px)', $start);

	return $end === false ? '' : substr($source, $start, $end - $start);
}

// The declarations of the first rule whose selector is exactly $selector.
function chat_window_text_size_rule($block, $selector)
{
	if (!preg_match('/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $block, $match)) {
		return '';
	}

	return $match[1];
}

function test_chat_window_font_sizes_are_rem()
{
	$block = chat_window_text_size_block();
	pg_assert_true($block !== '', 'the #pg-chat-root style block is found in chat.php');

	preg_match_all('/([^{}]+)\{[^}]*font-size:\s*[\d.]+px/', $block, $matches);
	$px = array();
	foreach ($matches[1] as $selector) {
		// The capture may start with the comment or markup before the rule.
		$lines = explode("\n", trim($selector));
		$selector = trim(end($lines));
		// The round launcher button keeps its fixed 58px size and icon.
		if ($selector !== '#pg-chat-root .pg-chat-launcher') {
			$px[] = $selector;
		}
	}
	pg_assert_same(array(), $px, 'no chat window text is sized in px');

	$expected = array(
		'#pg-chat-root .pg-chat-row-name' => '0.8125rem',
		'#pg-chat-root .pg-chat-row-sub' => '0.71875rem',
		'#pg-chat-root .pg-chat-row-time' => '0.65625rem',
		'#pg-chat-root .pg-chat-chip' => '0.59375rem',
		'#pg-chat-root .pg-chat-bubble' => '0.8125rem',
		'#pg-chat-root .pg-chat-composer textarea' => '0.8125rem',
	);
	foreach ($expected as $selector => $size) {
		pg_assert_contains('font-size: ' . $size, chat_window_text_size_rule($block, $selector), $selector . ' is ' . $size);
	}
}

function test_chat_window_boxes_grow_with_text()
{
	$block = chat_window_text_size_block();

	$panel = chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-panel');
	pg_assert_contains('width: 23.25rem', $panel, 'the window width is in rem');
	pg_assert_contains('height: 34rem', $panel, 'the window height is in rem');
	pg_assert_contains('max-width: calc(100vw - 32px)', $panel, 'the window still fits the screen width');
	pg_assert_contains('max-height: calc(100vh - 128px)', $panel, 'the window still fits the screen height');

	$chip = chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-chip');
	pg_assert_true(preg_match('/line-height:\s*[\d.]+\s*;/', $chip) === 1, 'the chip line height is unitless');

	$textarea = chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-composer textarea');
	pg_assert_contains('min-height: 1.5625rem', $textarea, 'the composer minimum height is in rem');
	pg_assert_contains('max-height: 6rem', $textarea, 'the composer maximum height is in rem');

	pg_assert_contains('height: 8.625rem', chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-emoji-grid'), 'the emoji grid height is in rem');
	pg_assert_contains('height: 1.375rem', chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-badge'), 'the unread badge height is in rem');
	pg_assert_contains('height: 1.375rem', chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-file-ext'), 'the file tag height is in rem');
	pg_assert_contains('--bs-avatar-size: 2.125rem', chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-face'), 'the avatar is in rem');
	pg_assert_contains('width: 2.125rem', chat_window_text_size_rule($block, '#pg-chat-root .pg-chat-ai-avatar'), 'the AI avatar is in rem');
}

function test_chat_backend_inline_sizes_are_rem()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/chat_backend.src.js');

	pg_assert_true(preg_match('/style\.fontSize\s*=\s*\'[\d.]+px\'/', $source) !== 1, 'chat_backend.src.js sets no font size in px');
	pg_assert_contains('getComputedStyle(textarea).maxHeight', $source, 'the composer grows up to its CSS max-height');
	pg_assert_true(strpos($source, 'Math.min(wanted, 96)') === false, 'the composer is not capped at a literal 96px');
}
