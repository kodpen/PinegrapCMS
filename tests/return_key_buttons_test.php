<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Return and Enter on panel buttons. Return in a text field presses
 * the first submit button of its form, so a drawer whose first button issues
 * a new secret or removes an account must start with a hidden, nameless
 * submit button that only saves. The guided tour reads Enter as Next, but a
 * focused Skip or Back button keeps Enter for itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The form inside the dialog with the given id, from its opening tag to its end.
function return_key_buttons_test_form($file, $dialog_id)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$dialog = strpos($source, 'id="' . $dialog_id . '"');

	if ($dialog === false) {
		return '';
	}

	$from = strpos($source, '<form', $dialog);
	$to = ($from === false) ? false : strpos($source, '</form>', $from);

	return (($from === false) || ($to === false)) ? '' : substr($source, $from, $to - $from);
}

// The position of the first submit button in a form, and whether it has a name.
function return_key_buttons_test_first_submit($form)
{
	if (!preg_match('/<button\b[^>]*type="submit"[^>]*>/', $form, $match, PREG_OFFSET_CAPTURE)) {
		return array(false, '');
	}

	return array($match[0][1], $match[0][0]);
}

function test_return_key_buttons_api_drawer_starts_with_a_nameless_submit()
{
	$form = return_key_buttons_test_form('api_settings.php', 'api_drawer');
	list($at, $button) = return_key_buttons_test_first_submit($form);

	pg_assert_true($at !== false, 'the API application drawer has a submit button');
	pg_assert_false(strpos($button, 'name=') !== false, 'the first submit button of the API drawer has no name, so Return posts api_action=update');
	pg_assert_contains('visually-hidden', $button, 'the first submit button of the API drawer is hidden');

	$rotate = strpos($form, 'id="d_rotate"');

	pg_assert_true(($rotate !== false) && ($at < $rotate), 'the hidden submit button stands before Issue a new secret');
}

function test_return_key_buttons_marketplace_drawer_starts_with_a_nameless_submit()
{
	$form = return_key_buttons_test_form('marketplace_settings.php', 'mp_drawer');
	list($at, $button) = return_key_buttons_test_first_submit($form);

	pg_assert_true($at !== false, 'the marketplace account drawer has a submit button');
	pg_assert_false(strpos($button, 'name=') !== false, 'the first submit button of the marketplace drawer has no name, so Return posts mp_action=update');
	pg_assert_contains('visually-hidden', $button, 'the first submit button of the marketplace drawer is hidden');

	$delete = strpos($form, 'value="delete"');

	pg_assert_true(($delete !== false) && ($at < $delete), 'the hidden submit button stands before Remove marketplace');
}

// onKey() of the tour, up to the next function.
function test_return_key_buttons_tour_leaves_enter_to_a_focused_button()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_tour.js');
	$from = strpos($source, 'function onKey(event)');
	$to = ($from === false) ? false : strpos($source, "\n    function ", $from + 1);
	$body = ($to === false) ? '' : substr($source, $from, $to - $from);

	$guard = strpos($body, 'parts.next');
	$enter = strpos($body, "(event.key === 'Enter')) { event.preventDefault(); move(1)");

	pg_assert_true($guard !== false, 'onKey tells the Next button apart from the other bubble buttons');
	pg_assert_contains('bubble.contains(event.target)', $body, 'onKey checks whether the key was pressed on a bubble button');
	pg_assert_true(($guard !== false) && ($enter !== false) && ($guard < $enter), 'the bubble button check comes before Enter is read as Next');
}
