<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Delete Key Codes screen (delete_key_codes.php) on the wide
 * edit layout: the warning card in the form column, the counts in the
 * sticky status column, the delete button in the sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_delete_key_codes_uses_the_wide_layout()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/delete_key_codes.php');

	$form = strpos($source, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status = strpos($source, 'col-12 lg:col-4 2xl:col-3 order-2');
	$bar = strpos($source, 'col-12 order-3 position-sticky');
	pg_assert_true(($form !== false) && ($status !== false) && ($bar !== false), 'form column, status column and save bar');
	pg_assert_true(($form < $status) && ($status < $bar), 'status column after the form column, so it stacks below it');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'status box sticks below the top bar');
	pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, 'no old save bar');

	foreach (array('Key Codes', 'Enabled', 'Expired') as $label) {
		pg_assert_contains("\$status_row(lang('" . $label . "')", $source, 'Summary row ' . $label);
	}

	// The POST the screen sends stays as it was, with its confirmation.
	foreach (array('get_token_field()', 'name="submit_delete"', 'data-confirm-content=', 'TRUNCATE key_codes', 'validate_token_field()') as $part) {
		pg_assert_contains($part, $source, 'keeps ' . $part);
	}
}

function test_delete_key_codes_offers_the_export_and_disables_an_empty_delete()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/delete_key_codes.php');

	pg_assert_contains("view_key_codes.php?submit_data=' . urlencode('Export Key Codes')", $source, 'export link to the list screen export');
	pg_assert_contains("(\$key_code_total > 0 ? '' : ' disabled')", $source, 'no delete without key codes');
	pg_assert_contains("expiration_date != '0000-00-00'", $source, 'a code without a date is not counted as expired');
}
