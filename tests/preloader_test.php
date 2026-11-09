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

function test_preloader_lifts_on_dom_ready_without_a_list_hold()
{
	$html = pg_preloader_markup();
	pg_assert_contains("document.addEventListener('DOMContentLoaded', hide);", $html, 'DOM ready lifts the curtain');
	pg_assert_false(strpos($html, 'table.chart') !== false, 'no list screen holds the curtain for a table library');
	pg_assert_false(strpos($html, 'DataTable') !== false, 'no DataTables check');
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

function test_panel_html_may_enter_the_back_forward_cache()
{
	pg_assert_same('private, no-cache', pg_panel_cache_control('/pinegrap/view_orders.php'), 'an ordinary screen');
	pg_assert_same('private, no-cache', pg_panel_cache_control('welcome.php'), 'file name alone');
	pg_assert_false(strpos(pg_panel_cache_control('view_pages.php'), 'no-store') !== false, 'no no-store');
}

function test_secret_screens_keep_no_store()
{
	foreach (array('api_settings.php', 'connections.php', 'connected_apps.php', 'edit_user.php', 'view_key_codes.php', 'edit_key_code.php',
		'workspace_calendar_feed.php', 'workspace_mcp.php', 'erp_accountant.php', 'edit_erp_quote.php',
		'view_short_links.php', 'view_folders.php', 'view_cart_quotes.php',
		'settings_security.php', 'settings_commerce.php', 'view_not_found_log.php') as $script) {
		pg_assert_same('private, no-store', pg_panel_cache_control('/pinegrap/' . $script), $script);
	}
	pg_assert_same('private, no-store', pg_panel_cache_control('API_Settings.php'), 'any case');
}

function test_restored_page_asks_for_the_session()
{
	$html = pg_preloader_markup();
	pg_assert_contains('/session_state.php"', $html, 'session address on the curtain');
	pg_assert_contains("host.getAttribute('data-pg-session')", $html, 'read on pageshow');
	pg_assert_contains('data-pg-view="', $html, 'view key on the curtain');
	pg_assert_contains("n.type === 'back_forward'", $html, 'an HTTP-cache history visit is checked too');
	pg_assert_contains('if (e.persisted || historyVisit()) checkSession();', $html, 'checked on every history pageshow');
	pg_assert_contains('html.pg-session-check body{visibility:hidden}', $html, 'hidden until the answer');
	pg_assert_contains("d.signed_in === true && (want === '' || d.view === want)", $html, 'only the same session and user is shown');
	pg_assert_contains('.catch(function () { location.reload(); })', $html, 'a failed check loads the page again');
}

function test_view_key_names_session_and_user()
{
	$a = pg_session_view_key_for('nonce-one', 7);
	pg_assert_same(32, strlen($a), 'length');
	pg_assert_same($a, pg_session_view_key_for('nonce-one', '7'), 'stable');
	pg_assert_true($a !== pg_session_view_key_for('nonce-one', 8), 'another user');
	pg_assert_true($a !== pg_session_view_key_for('nonce-two', 7), 'another session');
	pg_assert_same('', pg_session_view_key_for('', 7), 'no nonce');
	pg_assert_same('', pg_session_view_key_for('nonce-one', 0), 'no user');
}

function test_a_screen_s_own_cache_control_is_kept()
{
	pg_assert_true(pg_panel_cache_is_session_default(array('Content-Type: text/html')), 'none set');
	pg_assert_true(pg_panel_cache_is_session_default(array('Expires: Thu, 19 Nov 1981 08:52:00 GMT', 'Cache-Control: no-store, no-cache, must-revalidate', 'Pragma: no-cache')), 'the session default');
	pg_assert_true(pg_panel_cache_is_session_default(array('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0')), 'older PHP');
	pg_assert_false(pg_panel_cache_is_session_default(array('Cache-Control: no-store, max-age=0')), 'device pairing');
	pg_assert_false(pg_panel_cache_is_session_default(array('cache-control: private, no-store')), 'any case');
}

function test_every_session_end_clears_the_browser_cache()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	foreach (array('includes/fn/forms.php', 'includes/fn/auth.php', 'account_security.php', 'kiosk.php', 'login_as_user.php') as $file) {
		$source = (string) file_get_contents($root . $file);
		$ends = preg_match_all('/session_destroy\(\);/', $source);
		$cleared = preg_match_all('/session_destroy\(\);(?:\s*\/\/[^\n]*)*\s*pg_session_end_clear_cache\(\);/', $source);
		pg_assert_true($ends > 0, $file . ' ends a session');
		pg_assert_same($ends, $cleared, $file . ': every session_destroy() clears the cache');
	}
}
