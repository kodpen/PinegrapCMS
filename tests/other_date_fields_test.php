<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the remaining native date and time inputs of the panel are
 * drawn on the one date-and-time control: the Cloudflare report ranges, an
 * API application's expiry, the Paraşüt inbox range, the Workspace
 * holidays, the maintenance end time and a folder's access expiry. Each
 * native input keeps its ISO store and each screen prints the site's date
 * order next to the field script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function other_date_fields_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_other_date_fields_native_inputs_are_hooked()
{
	foreach (array('cloudflare.php' => 4, 'api_settings.php' => 1, 'view_parasut_inbox.php' => 2, 'includes/workspace/workdays.php' => 2) as $file => $count) {
		preg_match_all('/<input [^>]*type="date"[^>]*>/', other_date_fields_test_source($file), $inputs);

		pg_assert_same($count, count($inputs[0]), $file . ' native date inputs');

		foreach ($inputs[0] as $input) {
			pg_assert_true(strpos($input, ' data-pg-date') !== false, $file . ': ' . substr($input, 0, 90) . ' is hooked');
		}
	}

	pg_assert_contains('<input type="datetime-local" data-pg-datetime name="maintenance_until"', other_date_fields_test_source('includes/settings/general.php'), 'the maintenance end keeps its Y-m-d\TH:i store on the control');
}

function test_other_date_fields_screens_print_the_date_order_with_the_script()
{
	foreach (array('cloudflare.php', 'api_settings.php', 'view_parasut_inbox.php', 'includes/workspace/workdays.php', 'includes/settings/general.php', 'view_folders.php') as $file) {
		pg_assert_true(strpos(other_date_fields_test_source($file), 'get_date_picker_format() . pg_fields_script()') !== false, $file . ' prints the date order with the field script');
	}
}

// The drawer fills the expiry of the application it opens: through the
// wrapper, so the box shows it too.
function test_other_date_fields_api_expiry_set_through_the_wrapper()
{
	$source = other_date_fields_test_source('api_settings.php');

	pg_assert_contains('window.pgDatePicker.set(document.getElementById("d_expires"), app.expires || "")', $source, 'the expiry is written through pgDatePicker');
	pg_assert_false(strpos($source, 'document.getElementById("d_expires").value = app.expires') !== false, 'not into the hidden store alone');
}

// The folder access rows are built by script: the expiry is hooked, its
// wrapper (not the hidden store) is shown and hidden with the rights, and the
// new rows are drawn.
function test_other_date_fields_folder_access_expiry()
{
	$source = other_date_fields_test_source('view_folders.php');

	pg_assert_contains('<input type="date" data-pg-date class="form-control form-control-sm access-expiration"', $source, 'the expiry is hooked');
	pg_assert_contains("select.closest('tr').querySelector('.access-expiration-wrap')", $source, 'the rights select shows and hides the wrapper');
	pg_assert_contains('window.pgFields.init(body)', $source, 'the rows are drawn once in the document');
}
