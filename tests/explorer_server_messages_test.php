<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the messages the File Manager is handed by another screen: the
 * screen a redirect lands on is the one whose form carries its notice
 * (pg_redirect_notice_form()), and view_folders.php shows the pending
 * errors, warnings and notices as its own toasts, not as the panel's alert
 * boxes above the explorer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The form named after the screen the redirect opens.
function test_redirect_notice_form_is_the_target_screen()
{
	$cases = array(
		'/pinegrap/view_folders.php?view=files' => 'view_folders',
		'/pinegrap/view_folders.php' => 'view_folders',
		'/pinegrap/welcome.php' => 'welcome',
		'/pinegrap/view_design_files.php#top' => 'view_design_files',
		'/pinegrap/edit_file.php?id=3&send_to=%2Fpinegrap%2Fwelcome.php' => 'edit_file',
		'https://example.com/pinegrap/view_folders.php?folder=4' => 'view_folders',
	);

	foreach ($cases as $location => $form) {
		pg_assert_same($form, pg_redirect_notice_form($location), $location);
	}
}

// A site page or a path without a script shows no panel notice.
function test_redirect_notice_form_is_empty_off_the_panel_screens()
{
	foreach (array('', '/', '/about-us', '/pinegrap/', '/files/a.php.txt', '/pinegrap/../x.php', '/pinegrap/view-folders.php') as $location) {
		pg_assert_same('', pg_redirect_notice_form($location), $location);
	}
}

function pg_test_explorer_server_messages_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// No alert box is printed above the explorer.
function test_explorer_prints_no_panel_alert_boxes()
{
	$source = pg_test_explorer_server_messages_source();

	pg_assert_false(strpos($source, '->output_notices()') !== false, 'no output_notices()');
	pg_assert_false(strpos($source, '->output_errors()') !== false, 'no output_errors()');
	pg_assert_false(strpos($source, '->get_warnings()') !== false, 'no get_warnings()');
}

// The pending messages reach the script and are shown as toasts: errors and
// warnings stay until closed, as markup the liveform already escaped.
function test_explorer_shows_pending_messages_as_toasts()
{
	$source = pg_test_explorer_server_messages_source();

	pg_assert_contains('var SERVER_MESSAGES = <?php echo encode_json($explorer_messages); ?>;', $source);
	pg_assert_contains('SERVER_MESSAGES.forEach(function (entry) {', $source);
	pg_assert_contains("toast(entry.message, entry.type === 'success', null, { html: true, tone: entry.type === 'warning' ? 'warning' : '', sticky: entry.type !== 'success' });", $source);
	pg_assert_contains('$explorer_liveform->get_warning_messages()', $source);
}

// The screens that redirect to send_to leave their notice on the screen the
// redirect opens.
function test_send_to_redirects_leave_the_notice_on_their_target()
{
	foreach (array('edit_file.php', 'duplicate_folder.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(strpos($source, "new liveform('view_folders')") !== false, $file . ' leaves nothing on the File Manager blindly');
		pg_assert_contains('pg_redirect_notice_form(', $source, $file);
	}
}
