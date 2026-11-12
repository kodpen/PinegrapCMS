<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A card's drawer in the Site Settings dialog: a panel inside the <dialog>
 * that slides in from its right edge over the pane, never a second dialog in
 * the top layer. Pins its two shells (inside the dialog, and Bootstrap's
 * drawer on the settings_<key>.php page), the trigger the dialog takes out of
 * Bootstrap's hands, the Cron Jobs card as its first user, the host inside
 * the dialog and outside the form, and the order Escape and Close follow.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fragment.php');

function settings_modal_drawer_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of one function of the settings dialog in backend.src.js, from
// its name to the next function written at the same depth.
function settings_modal_drawer_test_function($name)
{
	$js = settings_modal_drawer_test_source('assets/js/backend.src.js');
	$from = strpos($js, "var STORE = 'pg_settings_last_pane';");
	$at = strpos($js, '    function ' . $name . '(', ($from === false) ? 0 : (int) $from);

	pg_assert_true($at !== false, $name . '() is in the settings dialog');

	$end = strpos($js, "\n    function ", (int) $at + 10);

	return substr($js, (int) $at, ($end === false) ? 4000 : ($end - $at));
}

function test_settings_modal_drawer_in_dialog_is_a_panel_not_a_dialog()
{
	$html = pg_settings_drawer('cron_jobs', 'Cron Jobs <span lang="en">(Linux)</span>', '<p>Body</p>', true);

	pg_assert_same(false, stripos($html, '<dialog'), 'never a second <dialog> in the top layer');
	pg_assert_same(false, strpos($html, 'data-bs-dismiss'), 'its Close is not Bootstrap\'s, which would close the settings');
	pg_assert_true((bool) preg_match('/^\s*<div class="pg-sm-drawer" id="cron_jobs" role="dialog" aria-modal="true" aria-labelledby="cron_jobs_title" tabindex="-1">/', $html), 'a panel named by its title, focusable as a whole');
	pg_assert_contains('<h3 class="pg-sm-drawer-title" id="cron_jobs_title">Cron Jobs <span lang="en">(Linux)</span></h3>', $html, 'the title is markup the card gives');
	pg_assert_true((bool) preg_match('/<button type="button" class="btn-close" data-pgdrawer-close aria-label="[^"]+" title="[^"]+"><\/button>/', $html), 'a Close of its own');
	pg_assert_contains('<div class="pg-sm-drawer-body"><p>Body</p></div>', $html, 'the body scrolls on its own');
	pg_assert_true(strpos($html, 'pg-sm-drawer-head') < strpos($html, 'pg-sm-drawer-body'), 'the head over the body');
}

function test_settings_modal_drawer_on_the_page_is_bootstraps_drawer()
{
	$html = pg_settings_drawer('cron_jobs', 'Cron Jobs', '<p>Body</p>', false);

	pg_assert_true((bool) preg_match('/^\s*<dialog class="drawer drawer-end pg-set-drawer" id="cron_jobs" aria-labelledby="cron_jobs_title">/', $html), 'the page has no outer dialog: Bootstrap\'s drawer');
	pg_assert_true((bool) preg_match('/<button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="[^"]+"><\/button>/', $html), 'closed by Bootstrap');
	pg_assert_contains('<h5 class="drawer-title" id="cron_jobs_title">Cron Jobs</h5>', $html);
	pg_assert_contains('<div class="drawer-body"><p>Body</p></div>', $html, 'the same content');
	pg_assert_same(false, strpos($html, 'data-pgdrawer'), 'nothing of the dialog\'s own');
}

function test_settings_modal_drawer_escapes_its_id()
{
	$html = pg_settings_drawer('a"b', 'T', '', true);

	pg_assert_contains('id="a&quot;b"', $html);
	pg_assert_contains('aria-labelledby="a&quot;b_title"', $html);
}

function test_settings_modal_drawer_trigger_is_taken_from_bootstrap()
{
	$card = '<button type="button" data-bs-toggle="drawer" data-bs-target="#cron_jobs">Setup</button>'
		. '<a href="#!" data-bs-toggle="dialog" data-bs-target="#x">Other</a>';

	$html = pg_settings_own_dialog_triggers($card);

	// Bootstrap's data-api, clicked inside an open dialog, shows its drawer in
	// the top layer and hides the dialog the trigger sits in.
	pg_assert_same(false, strpos($html, 'data-bs-toggle="drawer"'), 'no drawer toggle Bootstrap answers to');
	pg_assert_contains('<button type="button" data-pgdrawer="open" data-bs-target="#cron_jobs">', $html, 'the dialog\'s own name');
	pg_assert_same(false, strpos($html, 'data-pgdialog'), 'no second-dialog path left');
}

// What a card hands over to $pg_settings_modals[]: a drawer, or a hidden
// Connect form a button submits by its form attribute. Never a dialog.
function test_settings_modal_drawer_cards_hand_over_no_second_dialog()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/settings';
	$entries = 0;

	foreach (glob($dir . '/*.php') as $path) {
		$name = basename($path);

		if (in_array($name, array('modal.php', 'drawer.php', 'fragment.php', 'screen.php'), true)) {
			continue;
		}

		$src = (string) file_get_contents($path);

		pg_assert_same(false, stripos($src, 'data-bs-toggle="dialog"'), $name . ': no card opens a dialog');
		pg_assert_same(0, preg_match('/<dialog[\s>]/i', $src), $name . ': no card prints a dialog');

		if (!preg_match_all('/\$pg_settings_modals\[\]\s*=\s*([^;]{0,200})/s', $src, $m)) {
			continue;
		}

		foreach ($m[1] as $entry) {
			$entries++;
			// pg_settings_drawer(), or a card's helper that returns one
			// (ws_settings_department_drawer()).
			$drawer = (bool) preg_match('/^(pg_settings_drawer|ws_settings_[a-z_]+_drawer)\(/', $entry);
			$form = (bool) preg_match('/^\'\s*<form method="post" action="connect\.php" id="pgset_[a-z_]+(\' \. \$[a-z_]+ \. \')?" class="disable_shortcut d-none">/', $entry);
			pg_assert_true($drawer || $form, $name . ': a drawer or a hidden Connect form, got ' . substr($entry, 0, 60));
		}
	}

	pg_assert_same(18, $entries, 'Cron Jobs, the four Connect forms, the two department drawers, the Working calendar\'s three, Claude\'s new secret, the two agent drawers, the two MCP server drawers, Link a repository and the two mailbox drawers');
}

function test_settings_modal_drawer_second_dialog_path_is_gone()
{
	$js = settings_modal_drawer_test_source('assets/js/backend.src.js');

	pg_assert_same(false, strpos($js, 'data-pgdialog'), 'no click handler for a card dialog');
	pg_assert_same(false, strpos(settings_modal_drawer_test_function('clearExtras'), 'pgUI.dialog.dispose'), 'no card dialog to dispose');
	pg_assert_same(false, strpos(settings_modal_drawer_test_source('includes/settings/fragment.php'), 'data-pgdialog'), 'no dialog trigger renamed');
}

function test_settings_modal_drawer_cron_jobs_is_its_first_user()
{
	$cards = settings_modal_drawer_test_source('includes/settings/maintenance.php');

	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#cron_jobs" aria-controls="cron_jobs"', $cards, 'the card opens a drawer');
	pg_assert_same(false, (bool) preg_match('/<dialog[^>]*id="cron_jobs"/', $cards), 'no second dialog for the cron commands');
	pg_assert_contains("\$pg_settings_modals[] = pg_settings_drawer('cron_jobs',", $cards, 'handed over as a dialog is');
	pg_assert_contains('!empty($pg_settings_in_dialog)', $cards, 'the shell follows where the card is drawn');

	// The command fields stay read-only code fields.
	pg_assert_same(12, substr_count($cards, "pg_code_editor_attach(array('id' => 'cron_job_"), 'the commands are code fields');
	pg_assert_same(0, preg_match_all("/pg_code_editor_attach\(array\('id' => 'cron_job_[a-z_]+', 'code_type' => 'plain'(?!,'readonly'=>true )/", $cards), 'all read-only');
}

function test_settings_modal_drawer_shell_is_named_by_its_caller()
{
	$fragment = settings_modal_drawer_test_source('includes/settings/fragment.php');
	$screen = settings_modal_drawer_test_source('includes/settings/screen.php');
	$pane = settings_modal_drawer_test_source('settings_pane.php');

	pg_assert_contains('$pg_settings_in_dialog = true;', $fragment, 'the dialog draws the panel');
	pg_assert_true(strpos($fragment, '$pg_settings_in_dialog = true;') < strpos($fragment, "include(PG_FUNCTIONS_DIR . '/includes/settings/' . \$key . '.php');"), 'before the cards are drawn');
	pg_assert_contains('$pg_settings_in_dialog = false;', $screen, 'the page draws Bootstrap\'s drawer');
	pg_assert_contains("include_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');", $screen);
	pg_assert_contains("include_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');", $pane);
}

function test_settings_modal_drawer_host_is_inside_the_dialog_outside_the_form()
{
	$modal = settings_modal_drawer_test_source('includes/settings/modal.php');

	$form_end = strpos($modal, '</form>');
	$host = strpos($modal, 'id="pg_settings_modal_drawers"');
	$scrim = strpos($modal, 'id="pg_settings_modal_dscrim"');
	$dialog_end = strpos($modal, '</dialog>');

	pg_assert_true($host !== false, 'a host for the drawers');
	pg_assert_true(($form_end < $host) && ($host < $dialog_end), 'in the dialog, not in the form: a drawer may carry a form of its own');
	pg_assert_true(($scrim !== false) && ($scrim < $dialog_end), 'its scrim in the dialog too');
}

function test_settings_modal_drawer_moves_into_the_dialog_and_goes_with_the_pane()
{
	$paint = settings_modal_drawer_test_function('paint');
	$extras = strpos($paint, "\$('#pg_settings_modal_extras').html(response.modals)");
	$move = strpos($paint, "children('.pg-sm-drawer').appendTo('#pg_settings_modal_drawers')");

	pg_assert_true($extras !== false, 'the card markup arrives as before');
	pg_assert_true(($move !== false) && ($move > $extras), 'its drawers then move into the dialog');

	$clear = settings_modal_drawer_test_function('clearExtras');
	pg_assert_contains('closeCardDrawer(false)', $clear, 'an open drawer shuts with the pane');
	pg_assert_contains("window.pgReleaseEditors(byId('pg_settings_modal_drawers'))", $clear, 'its code fields are released');
	pg_assert_contains("\$('#pg_settings_modal_drawers').empty()", $clear);
}

function test_settings_modal_drawer_escape_and_close_shut_it_first()
{
	$js = settings_modal_drawer_test_source('assets/js/backend.src.js');
	$at = strpos($js, "if ((event.key !== 'Escape') || !pgUI.dialog.isOpen(el)) {");

	pg_assert_true($at !== false, 'one Escape handler for the dialog\'s layers');

	$handler = substr($js, (int) $at, 1200);
	$card = strpos($handler, 'closeCardDrawer()');
	$list = strpos($handler, 'drawer(false)');

	pg_assert_true(($card !== false) && ($list !== false) && ($card < $list), 'the card drawer before the phone list, both before the dialog');
	pg_assert_contains('event.preventDefault();', $handler, 'the dialog\'s own Escape is cancelled');

	pg_assert_contains("\$(el).on('click', '#pg_settings_modal_pane [data-pgdrawer=\"open\"]'", $js, 'the renamed trigger opens it');
	pg_assert_contains("\$(el).on('click', '[data-pgdrawer-close], #pg_settings_modal_dscrim'", $js, 'Close and the scrim shut it');
}

function test_settings_modal_drawer_keeps_the_pane_and_gives_focus_back()
{
	$open = settings_modal_drawer_test_function('openCardDrawer');
	$close = settings_modal_drawer_test_function('closeCardDrawer');
	$under = settings_modal_drawer_test_function('underCardDrawer');

	pg_assert_contains('opener = button', $open, 'the button that opened it is kept');
	pg_assert_contains('underCardDrawer(true)', $open, 'what it covers goes inert');
	pg_assert_contains('target.focus(', $open);

	// Inert first, so that the opener can take the focus again.
	$inert = strpos($close, 'underCardDrawer(false)');
	$focus = strpos($close, 'back.focus(');
	pg_assert_true(($inert !== false) && ($focus !== false) && ($inert < $focus), 'focus returns to the opener');

	// The pane is covered, never rebuilt: its values and its unsaved mark stay.
	foreach (array('setDirty(', 'snapshot(', '.html(', 'baseline') as $word) {
		pg_assert_same(false, strpos($open . $close . $under, $word), $word . ' is not the drawer\'s business');
	}

	pg_assert_contains("toggleAttribute('inert', state)", $under);
}

function test_settings_modal_drawer_takes_the_dialogs_width_on_a_phone()
{
	$css = settings_modal_drawer_test_source('assets/css/backend.src.css');
	$at = strpos($css, '@media (max-width: 575.98px) {', (int) strpos($css, '.pg-sm-drawer {'));

	pg_assert_true($at !== false, 'a phone rule after the drawer\'s');
	pg_assert_contains('.pg-sm-drawer { width: 100%; }', substr($css, (int) $at, 600));
	pg_assert_true((bool) preg_match('/\.pg-sm-drawer \{[^}]*position: absolute;[^}]*right: 0;/s', $css), 'inside the dialog, from its right edge');
	pg_assert_true((bool) preg_match('/\.pg-sm-drawer-body \{[^}]*min-height: 0;[^}]*overflow-y: auto;/s', $css), 'the body scrolls on its own');
}
