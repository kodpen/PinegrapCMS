<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Import My Site's final Import (import_design.php). Import
 * fetches every scanned address and writes folders and pages with nothing
 * sent to the browser, far longer than the window guard's 3 seconds. A
 * second press waited on the session lock, found the scanned list already
 * taken and ended on "Sorry, we could not find any items to import.";
 * scanning again as told made a second copy of the site. The scan-result
 * form now sends once and says the import is running.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function import_design_send_once_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/import_design.php');
}

// The scan-result form sends once: the first Import goes, later submits are
// cancelled until a page restored from the cache resets it.
function test_import_design_import_sends_once()
{
	$src = import_design_send_once_source();

	pg_assert_contains('<form id="import_design_form" action="', $src, 'the form has an id');
	pg_assert_contains('id="import_design_status" role="status" aria-live="polite"', $src, 'a status line is there');
	pg_assert_contains('window.addEventListener("submit", function (event) {', $src, 'the listener sits on window');
	pg_assert_contains('if (import_design_sent) { event.preventDefault(); return; }', $src, 'a later submit is cancelled');
	pg_assert_contains('window.addEventListener("pageshow", function (event) {', $src, 'a restored page resets it');
	pg_assert_contains('form.setAttribute("aria-busy", "true");', $src, 'the form is busy while the import runs');
	pg_assert_contains("escape_javascript(lang('Importing…'))", $src, 'the status line says the import is running');
	pg_assert_contains('event.submitter.value === "Import"', $src, 'only Import fills the status line');
}

// The guard is printed only on the scan-result page: Scan streams its own
// progress, and PHP stops a scan the browser dropped.
function test_import_design_send_once_only_after_scan()
{
	$src = import_design_send_once_source();

	$script_at = strpos($src, 'var import_design_sent = false;');
	pg_assert_true($script_at !== false, 'the send-once script is there');
	$branch_at = strrpos(substr($src, 0, (int) $script_at), "if (\$action == 'scan') {");
	pg_assert_true($branch_at !== false, 'the script sits in the scan-result branch');
}
