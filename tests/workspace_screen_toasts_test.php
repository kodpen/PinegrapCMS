<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace screens' operation messages: the liveform's
 * errors, warnings and notices become corner toasts (ws_screen_toasts(),
 * ws_screen_messages()), and no Workspace screen prints the panel's
 * "Notice:" / "Warning:" / "Error:" boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

// Errors, warnings and notices map to danger, warning and success, in that order.
function test_ws_screen_toasts_variants_and_order()
{
	$toasts = ws_screen_toasts(array('Bad name.'), array('Half done.'), array('The table was saved.'));

	pg_assert_same(array(
		array('variant' => 'danger', 'message' => 'Bad name.'),
		array('variant' => 'warning', 'message' => 'Half done.'),
		array('variant' => 'success', 'message' => 'The table was saved.'),
	), $toasts);
}

// The messages are stored as escaped markup; the toast takes plain text.
function test_ws_screen_toasts_turn_markup_into_text()
{
	$toasts = ws_screen_toasts(array(), array(), array('The table &quot;A &amp; B&quot; was<br>deleted.', '  ', null));

	pg_assert_same(array(
		array('variant' => 'success', 'message' => 'The table "A & B" was deleted.'),
	), $toasts);
}

// A repeated message is shown once.
function test_ws_screen_toasts_drop_repeats()
{
	$toasts = ws_screen_toasts(array('Failed.', 'Failed.'), array(), array('Failed.'));

	pg_assert_same(2, count($toasts));
	pg_assert_same('danger', $toasts[0]['variant']);
	pg_assert_same('success', $toasts[1]['variant']);
}

// A link in a notice is handed over apart, so the toast can still offer it.
function test_ws_screen_toasts_keep_a_link()
{
	$toasts = ws_screen_toasts(array(), array(), array('A draft flow was made. <a href="/pinegrap/workspace_flows.php?flow=4&amp;x=1">Open the draft flow</a>'));

	pg_assert_same(array(
		array(
			'variant' => 'success',
			'message' => 'A draft flow was made.',
			'link'    => array('url' => '/pinegrap/workspace_flows.php?flow=4&x=1', 'label' => 'Open the draft flow'),
		),
	), $toasts);
}

// A link that is neither on the site nor http(s) stays text only.
function test_ws_screen_toasts_refuse_a_foreign_scheme()
{
	$toasts = ws_screen_toasts(array(), array(), array('Done. <a href="javascript:alert(1)">Go</a>', 'Also. <a href="//evil.example/">Here</a>'));

	pg_assert_same(array('variant' => 'success', 'message' => 'Done. Go'), $toasts[0]);
	pg_assert_same(array('variant' => 'success', 'message' => 'Also. Here'), $toasts[1]);
}

// Nothing to say: no script.
function test_ws_screen_toasts_script_is_empty_without_messages()
{
	pg_assert_same('', ws_screen_toasts_script(array()));

	$script = ws_screen_toasts_script(array(array('variant' => 'success', 'message' => '</script><b>')));
	pg_assert_true(strpos($script, 'pgToast') !== false, 'calls pgToast');
	pg_assert_true(strpos($script, '</script><b>') === false, 'the text cannot close the script');
}

// None of the server-drawn Workspace screens prints the panel's alert boxes.
function test_workspace_screens_print_no_alert_boxes()
{
	foreach (glob(PG_FUNCTIONS_DIR . '/workspace*.php') as $file) {
		$source = (string) file_get_contents($file);
		$name = basename($file);

		pg_assert_true(strpos($source, 'output_errors()') === false, $name . ' prints output_errors()');
		pg_assert_true(strpos($source, 'output_notices()') === false, $name . ' prints output_notices()');
		pg_assert_true(strpos($source, 'get_warnings()') === false, $name . ' prints get_warnings()');
	}
}
