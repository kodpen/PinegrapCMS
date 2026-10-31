<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Orders > Export for Paraşüt Selected waits for its zip: the
 * panel script sends the form with fetch, saves the zip the answer carries
 * and only then reloads, instead of reloading after a fixed time (which
 * cancelled a slow export after its orders were already marked exported).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function parasut_order_export_function_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, 'function pgParasutOrderExport(');
	pg_assert_true($start !== false, 'pgParasutOrderExport() exists');
	$end = strpos($source, "\n    }\n", $start);
	return substr($source, $start, $end - $start);
}

// The Confirm branch hands the form to the exporter and neither submits it
// nor reloads on a timer.
function test_parasut_order_export_confirm_branch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, "if (value == 'Export Orders For Parasut') {");
	pg_assert_true($start !== false, 'Confirm branch');
	$branch = substr($source, $start, strpos($source, "\n                }\n", $start) - $start);
	pg_assert_true(strpos($branch, 'pgParasutOrderExport(document.form, el)') !== false, 'branch calls the exporter');
	pg_assert_true(strpos($branch, 'return true;') !== false, 'branch returns before document.form.submit()');
	pg_assert_true(strpos($source, '}, 4000);//4sec wait') === false, 'no fixed-time reload');
	pg_assert_true(strpos($source, 'couldown_for_order_exporting_10000') === false, 'old overlay removed');
}

// The exporter posts to the form's action attribute (the hidden "action"
// input shadows form.action), saves only a zip, reloads after saving it and
// leaves the page as it is on any other answer.
function test_parasut_order_export_waits_for_the_zip()
{
	$fn = parasut_order_export_function_source();

	pg_assert_true(strpos($fn, "form.getAttribute('action')") !== false, 'posts to the action attribute');
	pg_assert_true(strpos($fn, 'new FormData(form)') !== false, 'sends the whole form');
	pg_assert_true(strpos($fn, "role=\"status\"") !== false, 'wait overlay is a status region');
	pg_assert_true(strpos($fn, 'application/zip') !== false, 'checks the answer is a zip');
	pg_assert_true(strpos($fn, 'Content-Disposition') !== false, 'names the file from Content-Disposition');
	pg_assert_true(strpos($fn, '.download = ') !== false, 'saves through a download link');
	pg_assert_true(strpos($fn, 'window.location.reload()') !== false, 'reloads after saving');
	pg_assert_true(strpos($fn, "variant: 'danger'") !== false, 'shows the error');
	pg_assert_true(strpos($fn, "\$button.attr('data-wait-text')") !== false, 'wait text from the button');
	pg_assert_true(strpos($fn, "\$button.attr('data-error-text')") !== false, 'error text from the button');
	pg_assert_true(strpos($fn, '4000') === false, 'no fixed wait');
}

// The Confirm button carries both texts translated.
function test_parasut_order_export_button_texts()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	pg_assert_true(strpos($source, "data-wait-text=\"' . h(lang('Preparing the export file. Please wait.')) . '\"") !== false, 'wait text');
	pg_assert_true(strpos($source, "data-error-text=\"' . h(lang('The export file could not be created. Please try again.')) . '\"") !== false, 'error text');
}
