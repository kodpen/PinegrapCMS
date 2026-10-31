<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for two boxes whose text scrolls in its own box: the guided
 * tour's step text (assets/js/pg_tour.js, pg_tour.css) and the panel chat
 * window's conversation (assets/js/chat_backend.src.js, its .min.js twin and
 * chat.php). Each is reachable with Tab, named, and scrolls with the arrow
 * keys; the chat reads new messages out, not the history it opens with. A
 * unit test cannot press keys, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function scroll_regions_tour_chat_file($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of one function of a script, up to the next one at the same depth.
function scroll_regions_tour_chat_function($path, $name)
{
	$source = scroll_regions_tour_chat_file($path);
	$from = strpos($source, "\n    function " . $name . '(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    function ", $from + 1);

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

function test_tour_step_text_is_a_named_region_while_it_scrolls()
{
	$fit = scroll_regions_tour_chat_function('assets/js/pg_tour.js', 'fitBody');

	pg_assert_true($fit !== '', 'fitBody() is found in pg_tour.js');
	pg_assert_contains('scrollHeight', $fit, 'fitBody() measures whether the step text overflows');
	pg_assert_contains("setAttribute('tabindex', '0')", $fit, 'overflowing step text joins the tab order');
	pg_assert_contains("setAttribute('role', 'region')", $fit, 'overflowing step text is a region');
	pg_assert_contains("setAttribute('aria-labelledby', 'pg_tour_title')", $fit, 'the region is named by the step title');
	pg_assert_contains("removeAttribute('tabindex')", $fit, 'step text that fits leaves the tab order');
	pg_assert_contains('parts.next.focus()', $fit, 'focus left on text that no longer scrolls moves to Next');

	pg_assert_contains('fitBody()', scroll_regions_tour_chat_function('assets/js/pg_tour.js', 'paint'), 'paint(), run on every resize, rechecks the overflow');

	$css = scroll_regions_tour_chat_file('assets/css/pg_tour.css');

	pg_assert_true((bool) preg_match('/\.pg-tour-body:focus-visible\s*\{[^}]*outline:/', $css), 'focused step text shows a ring');
}

function test_chat_conversation_is_a_named_log_that_reads_only_new_messages()
{
	$view = scroll_regions_tour_chat_function('assets/js/chat_backend.src.js', 'openConversationView');

	pg_assert_true($view !== '', 'openConversationView() is found in chat_backend.src.js');
	pg_assert_contains("body.setAttribute('role', 'log')", $view, 'the conversation is a log');
	pg_assert_contains("body.setAttribute('tabindex', '0')", $view, 'the conversation is in the tab order, so it scrolls from the keyboard');
	pg_assert_contains("body.setAttribute('aria-label', STR.messages)", $view, 'the conversation is named');
	pg_assert_contains("body.setAttribute('aria-live', 'off')", $view, 'the history it opens with is not read out');

	$append = scroll_regions_tour_chat_function('assets/js/chat_backend.src.js', 'appendMessages');

	pg_assert_contains("setAttribute('aria-live', 'polite')", $append, 'messages after the first fill are read out');

	$chat = scroll_regions_tour_chat_file('chat.php');

	pg_assert_contains("'messages' => lang('Messages')", $chat, 'the name is translated');
	pg_assert_true((bool) preg_match('/\.pg-chat-body:focus-visible\s*\{[^}]*--pg-focus-ring-color/', $chat), 'the focused conversation shows the panel focus ring');

	$min = scroll_regions_tour_chat_file('assets/js/chat_backend.min.js');

	pg_assert_true(strpos($min, '"role","log"') !== false, 'the served .min.js carries the log role');
	pg_assert_true(strpos($min, '"aria-live","polite"') !== false, 'the served .min.js turns the live part on');
}
