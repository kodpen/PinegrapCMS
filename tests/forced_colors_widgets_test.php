<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Windows High Contrast (forced colours) for three panel widgets that draw
 * their meaning with a background or a box-shadow: the Edit Comment rating
 * stars, the panel chat window (its edge, open tab and focused row) and the
 * guided tour's spotlight ring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function forced_colors_widgets_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of the first @media (forced-colors: active) block, or ''.
function forced_colors_widgets_block($source)
{
	$start = strpos($source, '@media (forced-colors: active) {');
	if ($start === false) {
		return '';
	}

	$depth = 0;
	$length = strlen($source);
	for ($i = strpos($source, '{', $start); $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

function test_comment_rating_stars_painted_in_forced_colors()
{
	$block = forced_colors_widgets_block(forced_colors_widgets_source('edit_comment.php'));

	pg_assert_true(
		preg_match('/\.rating label::before\s*\{[^}]*background-color:\s*CanvasText/', $block) === 1,
		'the rating stars are painted CanvasText in forced colours');
}

function test_chat_window_marks_edge_tab_and_row_in_forced_colors()
{
	$source = forced_colors_widgets_source('chat.php');
	$root = substr($source, (int) strpos($source, '#pg-chat-root .pg-chat-launcher'));
	$root = substr($root, 0, (int) strpos($root, '</style>'));
	$block = forced_colors_widgets_block($root);

	pg_assert_true(
		preg_match('/#pg-chat-root \.pg-chat-panel\s*\{[^}]*border:\s*1px solid CanvasText/', $block) === 1,
		'the chat window has an edge in forced colours');
	pg_assert_true(
		preg_match('/#pg-chat-root \.pg-chat-tabs \.nav-link\.active\s*\{[^}]*forced-color-adjust:\s*none[^}]*Highlight[^}]*HighlightText/', $block) === 1,
		'the open chat tab is drawn in Highlight');
	pg_assert_true(
		preg_match('/#pg-chat-root \.pg-chat-row:focus-visible \{[^}]*outline: 2px solid transparent;/', $source) === 1,
		'a focused chat row keeps a transparent outline that forced colours paint');
	pg_assert_false(strpos($block, '#pg-chat-site') !== false, 'the site chat widget is left alone');
}

function test_tour_hole_has_an_outline_in_forced_colors()
{
	$block = forced_colors_widgets_block(forced_colors_widgets_source('assets/css/pg_tour.css'));

	pg_assert_true(
		preg_match('/\.pg-tour-hole\s*\{[^}]*outline:\s*3px solid Highlight/', $block) === 1,
		'the tour spotlight ring is an outline in forced colours');
}
