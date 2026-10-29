<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guided tour engine (assets/js/pg_tour.js and pg_tour.css):
 * every step is read out to a screen reader, the count and the dots leave
 * out steps that are passed over, Back never walks off the start, focus goes
 * back to a menu's toggle when the row that started the tour is hidden, the
 * Next button is readable and the bubble scrolls instead of leaving the
 * screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tour_engine_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_tour.js');
}

// The body of one function of the engine, up to the next one at the same depth.
function tour_engine_test_function($name)
{
	$source = tour_engine_test_source();
	$from = strpos($source, 'function ' . $name . '(');
	$to = ($from === false) ? false : strpos($source, "\n    function ", $from + 1);

	if ($from === false) {
		return '';
	}

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// The declarations of one CSS rule, by its exact selector.
function tour_engine_test_rule($selector)
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_tour.css');

	if (!preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $match)) {
		return '';
	}

	return $match[1];
}

function test_tour_engine_bubble_is_described_and_announces_each_step()
{
	$build = tour_engine_test_function('build');

	pg_assert_contains("'aria-describedby'", $build, 'the bubble is described by its count and text, not named by its title only');
	pg_assert_contains('role="status"', $build, 'the bubble carries a polite status region');
	pg_assert_contains('aria-live="polite"', $build, 'the status region is polite');
	pg_assert_contains('data-part="status"', $build, 'the status region is one of the bubble parts');

	$show = tour_engine_test_function('show');

	pg_assert_contains('parts.status', $show, 'show() fills the status region, so a step reached with focus already on Next is still read out');
}

function test_tour_engine_count_and_dots_use_shown_steps_only()
{
	$show = tour_engine_test_function('show');

	pg_assert_false(strpos($show, 'steps.length') !== false, 'show() does not count steps that will be passed over');
	pg_assert_contains('shownSteps()', $show, 'show() counts and numbers only the steps that will show');

	$shown = tour_engine_test_function('shownSteps');

	pg_assert_contains('ruledOut(', $shown, 'a step ruled out by skipIf is not counted');
	pg_assert_contains('targetReady(', $shown, 'a step whose target is missing is not counted');
}

function test_tour_engine_back_is_hidden_without_an_earlier_shown_step()
{
	$show = tour_engine_test_function('show');

	pg_assert_false(strpos($show, '(index === 0)') !== false, 'Back is not hidden only on the first registered step');
	pg_assert_contains('position === 0', $show, 'Back is hidden on the first step that shows');

	$move = tour_engine_test_function('move');

	pg_assert_contains('shownSteps().indexOf(index) === 0', $move, 'Back and ArrowLeft do nothing on the first step that shows');
}

function test_tour_engine_go_to_turns_forward_at_the_start()
{
	$go = tour_engine_test_function('goTo');

	pg_assert_true((bool) preg_match('/if \(next < 0\) \{[^}]*direction = 1;/', $go), 'goTo() turns forward at the start instead of clamping to 0 and recursing for ever');
}

function test_tour_engine_focus_falls_back_to_the_menu_toggle()
{
	$back = tour_engine_test_function('focusBack');

	pg_assert_contains("closest('.menu')", $back, 'a row inside a menu hands focus to its menu');
	pg_assert_contains('[data-bs-toggle="menu"]', $back, 'focus goes to the toggle of that menu');
	pg_assert_contains('isConnected', $back, 'a row taken out of the page counts as gone');

	$finish = tour_engine_test_function('finish');

	pg_assert_contains('focusBack(', $finish, 'finish() returns focus through the fallback');
}

function test_tour_engine_next_button_uses_the_accent_ink()
{
	$rule = tour_engine_test_rule('.pg-tour-primary');

	pg_assert_contains('color: var(--pg-on-accent)', $rule, 'Next and Finish use the panel ink for filled accents');
	pg_assert_false(strpos($rule, '#fff') !== false, 'Next and Finish are not white on the light accent');
}

function test_tour_engine_bubble_scrolls_and_keeps_its_foot()
{
	$bubble = tour_engine_test_rule('.pg-tour-bubble');

	pg_assert_contains('max-height:', $bubble, 'the bubble never grows past the screen');
	pg_assert_false((bool) preg_match('/overflow(-[xy])?\s*:/', $bubble), 'the bubble itself does not clip, so its arrow stays visible');

	$body = tour_engine_test_rule('.pg-tour-body');

	pg_assert_contains('overflow-y: auto', $body, 'the count, title and text scroll inside the bubble');
	pg_assert_contains('class="pg-tour-body"', tour_engine_test_function('build'), 'the bubble wraps its words in the scrolling body');

	$foot = tour_engine_test_rule('.pg-tour-foot');

	pg_assert_contains('flex: none', $foot, 'the foot with Skip and Next keeps its height');
}

function test_tour_engine_centred_bubble_is_clamped_to_the_edge()
{
	$place = tour_engine_test_function('placeBubble');
	$paint = tour_engine_test_function('paint');

	pg_assert_false(strpos($place, 'Math.round((viewportHeight - height) / 2)') !== false, 'the centred fallback of placeBubble() never gets a negative top');
	pg_assert_false(strpos($paint, 'Math.round((viewportHeight - bubble.offsetHeight) / 2)') !== false, 'a centred step never gets a negative top');
}
