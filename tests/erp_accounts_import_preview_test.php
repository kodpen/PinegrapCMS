<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mapping step of the ERP account CSV import: the preview
 * cells and the template file. An empty "Remind the customer" cell leaves
 * the key out of the normalised row, and the preview read it anyway (an
 * undefined-key warning per row); the mapping drawn after an upload passed
 * $clashes, set only by a re-check. The template's example line was one
 * cell short of its header.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/import.php');

function test_erp_import_preview_cell_reminder_in_words_or_empty()
{
	$kinds = array('Customer' => 'customer', 'Supplier' => 'supplier');

	pg_assert_same('', erp_import_preview_cell('overdue_notify_customer', array(), $kinds), 'an unset reminder is an empty cell');
	pg_assert_same(lang('Yes'), erp_import_preview_cell('overdue_notify_customer', array('overdue_notify_customer' => 1), $kinds));
	pg_assert_same(lang('No'), erp_import_preview_cell('overdue_notify_customer', array('overdue_notify_customer' => 0), $kinds));
	pg_assert_same('', erp_import_preview_cell('district', array(), $kinds), 'any missing field is an empty cell');
}

function test_erp_import_preview_cell_keeps_the_coded_fields_in_words()
{
	$kinds = array('Customer' => 'customer', 'Supplier' => 'supplier');

	pg_assert_same('Supplier', erp_import_preview_cell('kind', array('kind' => 'supplier'), $kinds));
	pg_assert_same(lang('Person'), erp_import_preview_cell('is_person', array('is_person' => 1), $kinds));
	pg_assert_same(lang('Company'), erp_import_preview_cell('is_person', array('is_person' => null), $kinds));
	pg_assert_same(lang('Passive'), erp_import_preview_cell('status', array('status' => 'passive'), $kinds));
	pg_assert_same('Ankara', erp_import_preview_cell('city', array('city' => 'Ankara'), $kinds));
}

function test_erp_import_template_example_has_a_cell_per_heading()
{
	// erp_import_fields() reads the tax id label from the settings; its
	// synonyms list has the same fields in the same order.
	$fields = erp_import_field_synonyms();
	$example = erp_import_template_example($fields, 'TR', 'TRY');

	pg_assert_same(count($fields), count($example), 'one example cell per heading');
	$by_field = array_combine(array_keys($fields), $example);
	pg_assert_same('TR', $by_field['country_code']);
	pg_assert_same('TRY', $by_field['currency']);
	pg_assert_same('', $by_field['overdue_notify_customer']);
}

function test_erp_accounts_import_mapping_after_upload_has_no_clashes()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accounts_import.php');

	$init = strpos($src, '$clashes = array();');
	$upload = strpos($src, "if (\$step === 'upload') {");
	pg_assert_true(($init !== false) && ($upload !== false) && ($init < $upload), '$clashes is set before the step branches');
	pg_assert_contains('erp_import_preview_cell($field, $entry[\'account\'], $kind_options)', $src, 'the preview reads cells through the helper');
}
