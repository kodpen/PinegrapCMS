<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin for F1 in the panel (assets/js/backend.src.js): while the Site
 * Settings dialog is open, F1 follows the help link of the category on view.
 * The dialog is a Bootstrap 6 <dialog>, which is open when it has [open] and
 * is not .hiding; it never gets the Bootstrap 5 .show class.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_settings_help_f1_reads_dialog_open_state()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains("#pg_settings_modal[open]:not(.hiding) #pg_settings_modal_help", $js, 'F1 finds the category help in an open dialog');
	pg_assert_false(strpos($js, '#pg_settings_modal.show') !== false, 'no Bootstrap 5 .show test on the settings dialog');
}
