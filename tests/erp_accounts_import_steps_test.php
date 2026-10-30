<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the steps of the ERP account CSV import (erp_accounts_import.php).
 * A Start import refused for a missing Name column used to go back to the
 * address, whose plain visit discards the staged file: the person landed on
 * the upload step and had to choose the file and every column again. It now
 * draws the mapping step again from what was posted. Start import also runs
 * the whole file, far longer than the window guard's 3 seconds: a second
 * press waited for the first run, found the file gone and ended on "The
 * upload has expired." with the result never shown. The step 2 form now
 * sends once and says the import is running.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_accounts_import_steps_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accounts_import.php');
}

// The refused run marks its message away from the upload's file field and
// does not leave for the address; it falls through to the mapping draw.
function test_erp_accounts_import_refused_run_keeps_the_mapping()
{
	$src = erp_accounts_import_steps_source();

	$start = strpos($src, "if (isset(\$_POST['submit_run']) && !in_array('title', \$mapping, true)) {");
	pg_assert_true($start !== false, 'the run checks for a Name column');
	$end = strpos($src, '}', $start);
	$block = substr($src, $start, $end - $start);

	pg_assert_true(strpos($block, 'go($self_url)') === false, 'a refused run does not go back to the address');
	pg_assert_true(strpos($block, "mark_error('file'") === false, 'the message is not put on the upload field');
	pg_assert_contains("mark_error('map', lang('Map a column to Name to continue.'))", $block, 'the message is marked on the mapping');
	pg_assert_contains("} elseif (isset(\$_POST['submit_run'])) {", $src, 'the run only happens with a Name column');
}

// The mapping form sends once: the first submit goes, later ones are
// cancelled until a page restored from the cache resets it.
function test_erp_accounts_import_start_import_sends_once()
{
	$src = erp_accounts_import_steps_source();

	pg_assert_contains('<form name="form" id="erp_import_map_form" action="erp_accounts_import.php" method="post">', $src, 'the mapping form has an id');
	pg_assert_contains('id="erp_import_map_status" role="status" aria-live="polite"', $src, 'a status line is there');
	pg_assert_contains('window.addEventListener("submit", function (event) {', $src, 'the listener sits on window');
	pg_assert_contains('if (erp_import_sent) { event.preventDefault(); return; }', $src, 'a later submit is cancelled');
	pg_assert_contains('window.addEventListener("pageshow", function (event) {', $src, 'a restored page resets it');
	pg_assert_contains('form.setAttribute("aria-busy", "true");', $src, 'the form is busy while the import runs');
	pg_assert_contains("escape_javascript(lang('Importing…'))", $src, 'the status line says the import is running');
	pg_assert_contains('event.submitter.name === "submit_run"', $src, 'only Start import fills the status line');
}
