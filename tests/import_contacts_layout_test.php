<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Import Contacts screen (import_contacts.php) on the wide
 * edit layout: the file and group cards in the form column, the Summary in
 * the sticky status column, the import button in the sticky save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_import_contacts_uses_the_wide_layout()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_contacts.php');

	$form = strpos($source, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status = strpos($source, 'col-12 lg:col-4 2xl:col-3 order-2');
	$bar = strpos($source, 'col-12 order-3 position-sticky');
	pg_assert_true(($form !== false) && ($status !== false) && ($bar !== false), 'form column, status column and save bar');
	pg_assert_true(($form < $status) && ($status < $bar), 'status column after the form column, so it stacks below it');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'status box sticks below the top bar');
	pg_assert_false(strpos($source, 'style="bottom:.5rem;"') !== false, 'no old save bar');

	foreach (array('Contacts', 'Contact Groups', 'File') as $label) {
		pg_assert_contains("\$status_row(lang('" . $label . "')", $source, 'Summary row ' . $label);
	}

	// The fields the import reads stay as they were.
	foreach (array('name="file"', 'name="import_mode"', 'name="contact_group_', 'name="submit_import"', 'name="send_to"') as $field) {
		pg_assert_contains($field, $source, 'keeps ' . $field);
	}
}
