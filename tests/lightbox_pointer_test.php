<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's full-screen picture viewer (pg_lightbox.js) closes
 * only on a real tap on the dark background: a click, double-click or drag
 * on the picture, a long press, or a press that slides onto the picture
 * leaves it open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function lightbox_pointer_build()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_lightbox.js');
	$start = strpos($js, '    function build(');
	pg_assert_true($start !== false, 'build() exists');
	$end = strpos($js, "\n    }\n", $start);
	return substr($js, $start, $end - $start);
}

function test_lightbox_pointer_no_close_on_click_or_press()
{
	$build = lightbox_pointer_build();

	pg_assert_true(strpos($build, "stage.addEventListener('click'") === false, 'the stage click does not close: the captured press sends the click on the picture to the stage');
	pg_assert_true(strpos($build, "addEventListener('mousedown'") === false, 'a press on the background does not close before it is released');
}

function test_lightbox_pointer_closes_on_background_tap()
{
	$build = lightbox_pointer_build();

	pg_assert_contains("overlay.addEventListener('pointerdown', function (e) {", $build, 'a background press is noted on the overlay, which holds the stage');
	pg_assert_contains('return el === overlay || el === stage;', $build, 'only the overlay and the stage themselves are background');
	pg_assert_contains('e.isPrimary && e.button === 0 && isBackground(e.target)', $build, 'only a primary press on the background starts a tap');
	pg_assert_contains("overlay.addEventListener('pointerup', function (e) {", $build, 'the tap is judged on release');
	pg_assert_contains('> TAP_MAX_MOVE', $build, 'a press that moved is a drag, not a tap');
	pg_assert_contains('> TAP_MAX_TIME', $build, 'a long press is not a tap');
	pg_assert_contains('isBackground(document.elementFromPoint(e.clientX, e.clientY))', $build, 'the release must be on the background too: pointer capture hides where it really is');
	pg_assert_contains("overlay.addEventListener('pointercancel', function () { tapStart = null; });", $build, 'a cancelled press is not a tap');
}
