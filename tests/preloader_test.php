<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's loading curtain and leaving bar in
 * includes/fn/output.php: the printed markup, the light leaving rules in its
 * script, and the download marker a file answer carries.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_preloader_prints_the_curtain_and_the_leaving_bar()
{
	$html = pg_preloader_markup();
	pg_assert_contains('id="pg-preloader" class="pg-preloader"', $html, 'curtain');
	pg_assert_contains('<div class="pg-leave-bar" aria-hidden="true"><i></i></div>', $html, 'leaving bar');
	pg_assert_contains("host.classList.add('is-hold')", $html, 'a held curtain is marked, the mark shows only there');
	pg_assert_contains('timer = setTimeout(hide, 9000);', $html, 'the boot timer is the one hide() clears');
}

function test_preloader_leaving_has_no_curtain_and_no_timer_exit()
{
	$html = pg_preloader_markup();
	pg_assert_false(strpos($html, 'pgPreloaderShow') !== false, 'no exit curtain');
	pg_assert_false(strpos($html, 'setTimeout(hide, 6000)') !== false, 'no 6 s exit');
	pg_assert_false(strpos($html, "'pointerdown', 'keydown', 'wheel'") !== false, 'no interaction exit');
	pg_assert_contains("nav.addEventListener('navigate'", $html, 'script navigations raise the bar');
	pg_assert_contains('}, 150);', $html, 'the bar waits 150 ms');
	pg_assert_contains('e.defaultPrevented', $html, 'a cancelled navigation shows nothing');
}

function test_preloader_is_left_out_of_the_edit_toolbar()
{
	$saved = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null;
	$_SERVER['SCRIPT_NAME'] = '/pinegrap/toolbar.php';
	$html = pg_preloader_markup();
	if ($saved === null) {
		unset($_SERVER['SCRIPT_NAME']);
	} else {
		$_SERVER['SCRIPT_NAME'] = $saved;
	}
	pg_assert_same('', $html, 'toolbar.php');
}

function test_download_marker_only_for_attachments()
{
	pg_assert_true(pg_download_marker_needed(array('Content-Type: text/csv', 'Content-Disposition: attachment; filename="a.csv"')), 'attachment');
	pg_assert_true(pg_download_marker_needed(array('content-disposition:ATTACHMENT')), 'any case, no space');
	pg_assert_false(pg_download_marker_needed(array('Content-Disposition: inline; filename="a.pdf"')), 'inline');
	pg_assert_false(pg_download_marker_needed(array('X-Note: Content-Disposition: attachment')), 'only the header itself');
	pg_assert_false(pg_download_marker_needed(array()), 'no headers');
}
