<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Settings dialog's own box, the warnings a save leaves and the
 * address while the dialog is open: the phone list drawer and the progress
 * bar live on the <dialog> itself, a save module's add_warning() reaches the
 * dialog in its own tone, and the #settings/... fragment follows the pane and
 * goes when the dialog closes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_dialog_shell_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of one function of the settings dialog in backend.src.js, from
// its name to the next function written at the same depth.
function settings_dialog_shell_test_function($name)
{
	$js = settings_dialog_shell_test_source('assets/js/backend.src.js');
	$from = strpos($js, "var STORE = 'pg_settings_last_pane';");
	$at = strpos($js, '    function ' . $name . '(', ($from === false) ? 0 : (int) $from);

	pg_assert_true($at !== false, $name . '() is in the settings dialog');

	$end = strpos($js, "\n    function ", (int) $at + 10);

	return substr($js, (int) $at, ($end === false) ? 4000 : ($end - $at));
}

function test_settings_dialog_shell_is_the_dialog_itself()
{
	$modal = settings_dialog_shell_test_source('includes/settings/modal.php');

	pg_assert_true((bool) preg_match('/<dialog class="[^"]*\bpg-sm\b[^"]*" id="pg_settings_modal"/', $modal), 'the dialog carries pg-sm itself');

	$shell = settings_dialog_shell_test_function('shell');

	// querySelector() looks at descendants only: asked on the dialog that
	// carries the class, it found nothing, and the drawer never opened.
	pg_assert_true(strpos($shell, "querySelector('.pg-sm')") === false, 'shell() does not look for pg-sm below the dialog');
	pg_assert_contains('return el', $shell);
}

function test_settings_pane_hands_over_warnings()
{
	$pane = settings_dialog_shell_test_source('settings_pane.php');

	pg_assert_same(2, substr_count($pane, '$liveform->get_warning_messages()'), 'the pane read and the save both read the warnings');
	pg_assert_same(2, substr_count($pane, "'warnings'"), 'both answers carry them');
	pg_assert_true(strpos($pane, '$liveform->get_warning_messages()') < strrpos($pane, '$liveform->remove_form();'), 'read before the form is removed');
}

function test_settings_dialog_shows_warnings_in_their_own_tone()
{
	$notice = settings_dialog_shell_test_function('notice');

	pg_assert_contains("'theme-warning'", $notice);
	pg_assert_contains("'theme-danger'", $notice);
	pg_assert_contains("'theme-success'", $notice);

	$paint = settings_dialog_shell_test_function('paint');
	pg_assert_contains('response.warnings', $paint, 'a pane read shows the warnings a tool left');

	$save = settings_dialog_shell_test_function('save');
	pg_assert_contains('response.warnings', $save, 'a save shows the warnings its modules left');
}

function test_product_feed_build_failure_is_a_warning()
{
	$save = settings_dialog_shell_test_source('includes/settings/commerce.save.php');

	pg_assert_contains("\$liveform->add_warning(lang(array('string' => 'The product feeds were not built: {var:1}'", $save);
	pg_assert_true((bool) preg_match("/add_warning\(lang\(array\('string' => '\{var:1\} was not built: \{var:2\}'/", $save), 'a format that was not built is a warning');
	pg_assert_true((bool) preg_match("/add_notice\(lang\(array\('string' => '\{var:1\} was built with \{var:2\} product\(s\)\.'/", $save), 'a built format stays a notice');
}

function test_settings_dialog_address_follows_the_pane()
{
	$paint = settings_dialog_shell_test_function('paint');
	pg_assert_contains('locate(key)', $paint, 'the address names the pane on screen');

	$locate = settings_dialog_shell_test_function('locate');
	pg_assert_contains('history.replaceState(', $locate, 'no history entry and no hashchange');
	pg_assert_contains("'#settings/' + key", $locate);

	$open = settings_dialog_shell_test_function('open');
	pg_assert_contains('address === null', $open, 'the page fragment is kept once, before the dialog takes the address');

	$from = settings_dialog_shell_test_function('fromHash');
	pg_assert_contains("address = ''", $from, 'a fragment that opened the dialog is not given back on close');
}

function test_settings_dialog_close_gives_the_address_back()
{
	$js = settings_dialog_shell_test_source('assets/js/backend.src.js');
	$at = strpos($js, "pgUI.dialog.on(el, 'hidden', function () {");

	pg_assert_true($at !== false, 'the dialog has a hidden handler');
	pg_assert_contains('unlocate()', substr($js, (int) $at, 1600), 'closing gives the address back');

	$unlocate = settings_dialog_shell_test_function('unlocate');
	pg_assert_contains('history.replaceState(', $unlocate);
	pg_assert_contains('location.pathname + location.search + address', $unlocate);
	pg_assert_contains('address = null', $unlocate);
}
