<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's full-screen picture viewer (pg_lightbox.js) moves
 * focus in when it opens, keeps Tab inside, gives focus back to the opener
 * when it closes, carries a name, and leaves the tab order and the
 * accessibility tree while closed; its thumbnails are named buttons.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function lightbox_focus_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function lightbox_focus_function($js, $name)
{
	$start = strpos($js, '    function ' . $name . '(');
	pg_assert_true($start !== false, $name . '() exists');
	$end = strpos($js, "\n    }\n", $start);
	return substr($js, $start, $end - $start);
}

function test_lightbox_focus_dialog_named_and_closed_state()
{
	$js = lightbox_focus_source('assets/js/pg_lightbox.js');
	$build = lightbox_focus_function($js, 'build');

	pg_assert_contains("overlay.setAttribute('aria-label', L('Image viewer', true));", $build, 'the dialog has a name');
	pg_assert_contains('overlay.inert = true;', $build, 'the overlay starts inert, out of the tab order');
	pg_assert_true(strpos($build, "setAttribute('aria-modal'") === false, 'aria-modal is not set while the overlay is closed');

	$css = lightbox_focus_source('assets/css/pg_lightbox.css');
	pg_assert_contains("    visibility: hidden;\n    transition: opacity .18s ease-out, visibility 0s linear .18s;", $css, 'the closed overlay is hidden once it has faded');
	pg_assert_contains("    visibility: visible;\n    transition-delay: 0s;", $css, 'the open overlay is visible at once, so focus can move in');
}

function test_lightbox_focus_open_moves_focus_in()
{
	$open = lightbox_focus_function(lightbox_focus_source('assets/js/pg_lightbox.js'), 'open');

	pg_assert_contains("if (!overlay.classList.contains('pgl-open')) opener = document.activeElement;", $open, 'the opener is remembered once, not replaced by a reopen');
	pg_assert_contains('overlay.inert = false;', $open, 'the overlay leaves inert when it opens');
	pg_assert_contains("overlay.setAttribute('aria-modal', 'true');", $open, 'aria-modal is set only while open');
	pg_assert_contains("focusInto(overlay.querySelector('[data-pgl=\"close\"]'));", $open, 'focus moves to the Close button');
	pg_assert_contains("document.addEventListener('focusin', onFocusin, true);", $open, 'focus that leaves the overlay is brought back');
}

function test_lightbox_focus_tab_wraps_inside()
{
	$js = lightbox_focus_source('assets/js/pg_lightbox.js');

	pg_assert_contains("case 'Tab':        trapTab(e); break;", lightbox_focus_function($js, 'onKeydown'), 'Tab is handled while open');
	$trap = lightbox_focus_function($js, 'trapTab');
	pg_assert_contains('var list = focusables();', $trap, 'Tab reads the overlay\'s own controls');
	pg_assert_contains('e.preventDefault();', $trap, 'Tab never reaches the page behind');
	pg_assert_contains('e.shiftKey', $trap, 'Shift+Tab wraps backwards');
	pg_assert_contains("button:not([disabled])", lightbox_focus_function($js, 'focusables'), 'disabled buttons are skipped');
}

function test_lightbox_focus_close_returns_focus()
{
	$close = lightbox_focus_function(lightbox_focus_source('assets/js/pg_lightbox.js'), 'close');

	pg_assert_contains('overlay.inert = true;', $close, 'the closed overlay leaves the tab order');
	pg_assert_contains("overlay.removeAttribute('aria-modal');", $close, 'aria-modal goes with the open state');
	pg_assert_contains("document.removeEventListener('focusin', onFocusin, true);", $close, 'the focus guard stops');
	pg_assert_contains('if (back && back.isConnected && typeof back.focus === \'function\') {', $close, 'focus goes back only to an opener still in the page');
}

function test_lightbox_focus_thumbs_are_buttons()
{
	$js = lightbox_focus_source('assets/js/pg_lightbox.js');
	$thumbs = lightbox_focus_function($js, 'buildThumbs');

	pg_assert_contains("var t = document.createElement('button');", $thumbs, 'a thumbnail is a button');
	pg_assert_contains("t.type = 'button';", $thumbs, 'a thumbnail never submits a form');
	pg_assert_contains("t.setAttribute('aria-label', L('Image {n} of {total}', true).replace('{n}', i + 1).replace('{total}', images.length));", $thumbs, 'a thumbnail is named by its position');
	pg_assert_contains("t.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');", lightbox_focus_function($js, 'showImageAt'), 'the shown picture is marked current');

	$css = lightbox_focus_source('assets/css/pg_lightbox.css');
	pg_assert_contains('.pgl-overlay :focus-visible {', $css, 'controls show a focus ring on the dark overlay');
}

function test_lightbox_focus_labels_translated()
{
	$php = lightbox_focus_source('includes/fn/designer.php');

	pg_assert_contains("'Image viewer'                   => lang('Image viewer'),", $php, 'the dialog name is translated');
	pg_assert_contains("'Image {n} of {total}'           => lang(array('string' => 'Image {var:1} of {var:2}', 'vars' => array('{n}', '{total}'))),", $php, 'the thumbnail name reuses the existing key');
}
