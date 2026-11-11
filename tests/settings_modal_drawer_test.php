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
	pg_assert_contains('data-pgdialog="modal"', $html, 'a card dialog still renamed');
}
