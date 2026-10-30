<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the frame tour and the dashboard greeting tip: each step names
 * the control it sends the user to by the label the bar prints (Back, Menu
 * view, Show menu, My Menu, Sections), not by its shape or position only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tour_control_names_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_tour_control_names_frame_tour_names_controls()
{
	$source = tour_control_names_test_source('includes/fn/tour.php');

	$gone = array(
		'from your own menu at the top right',
		'and the arrow at the top takes you back',
		'The button at the top left changes how it shows',
		'it waits behind this button',
		'notifications and your own menu',
		'pick a heading on the left',
	);
	foreach ($gone as $text) {
		pg_assert_true(strpos($source, $text) === false, 'the tour no longer says: ' . $text);
	}

	$named = array(
		'from Interface tour in My Menu',
		'Back at the top of it takes you back',
		'Menu view, the button at the top left (Ctrl+D)',
		'it waits behind Show menu',
		'notifications and My Menu',
		'on a small screen Sections opens the list',
	);
	foreach ($named as $text) {
		pg_assert_contains($text, $source, 'the tour names the control: ' . $text);
	}
}

function test_tour_control_names_shell_key_is_kept()
{
	$source = tour_control_names_test_source('includes/fn/tour.php');

	pg_assert_contains("define('PG_TOUR_SHELL_KEY', 'shell.6');", $source, 'new wording alone does not show the tour again');
}

function test_tour_control_names_output_prints_the_quoted_names()
{
	$source = tour_control_names_test_source('includes/fn/output.php');

	foreach (array("lang('Back')", "lang('Menu view')", "lang('Show menu')", "lang('My Menu')", "lang('Interface tour')", "lang('Appearance')", "lang('Light')") as $call) {
		pg_assert_contains($call, $source, 'the frame prints the name the texts quote: ' . $call);
	}
	pg_assert_contains("lang('Sections')", tour_control_names_test_source('includes/settings/modal.php'), 'the settings list button is named Sections');
}

function test_tour_control_names_dashboard_tip_names_the_switch()
{
	$source = tour_control_names_test_source('includes/dashboard/brief.php');

	pg_assert_true(strpos($source, 'it is in the user menu at the top right') === false, 'the tip no longer points at a "user menu"');
	pg_assert_contains('choose Light under Appearance in My Menu', $source, 'the tip names My Menu and the Light switch');
}
