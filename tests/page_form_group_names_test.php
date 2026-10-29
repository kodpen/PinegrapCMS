<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get on the Create/Edit Page type
 * properties and the ERP accounts import: every radio group sits under a
 * legend, and the custom form's two e-mail blocks are named by their switch.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_form_group_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_page_form_radio_questions_are_legends()
{
	$src = page_form_group_names_source('includes/forms/page_form.php');

	$legends = array(
		'Product Description Type' => 5,
		'Format' => 5,
		'Confirmation Type' => 1,
		'If User has already submitted form in the past, then show' => 1,
		'Layout Type' => 1,
	);

	foreach ($legends as $text => $count) {
		pg_assert_true(substr_count($src, '<legend class="form-label fs-md">\' . lang(\'' . $text . '\') . \'</legend>') === $count, $text . ' is a legend ' . $count . ' time(s)');
		pg_assert_true(preg_match('/<label class="form-label"( for="")?>\'\s*\.\s*lang\(\'' . preg_quote($text, '/') . '\'\)/', $src) === 0, 'no orphan ' . $text . ' label');
	}

	pg_assert_true(strpos($src, '<label class="form-label" for="">') === false, 'no label with an empty for');
}

function test_page_form_radio_groups_keep_their_ids_and_hooks()
{
	$src = page_form_group_names_source('includes/forms/page_form.php');

	pg_assert_contains('<div class="form-field" id="order_form_product_layout_row_2" style="\' . $order_form_product_layout_row_2_style . \'">', $src, 'the drop-down option wrapper keeps its id');
	pg_assert_contains('id="layout_type_row"', $src, 'the layout type row keeps its id');
	pg_assert_contains('onclick="show_or_hide_custom_form_confirmation_type()" data-bs-target="#custom_form_confirmation_type_message_row"', $src, 'the confirmation radio keeps its hooks');
	pg_assert_contains('onclick="show_or_hide_custom_form_return_type()" data-bs-target="#custom_form_return_type_page_row"', $src, 'the return type radio keeps its hooks');
	pg_assert_contains('data-bs-target="#custom_form_administrator_email_format_html_row"', $src, 'the administrator format radio keeps its target');
}

function test_page_form_custom_form_email_blocks_are_named()
{
	$src = page_form_group_names_source('includes/forms/page_form.php');

	foreach (array('submitter' => 'E-mail Submitter', 'administrator' => 'E-mail Administrator') as $who => $text) {
		pg_assert_contains('<label class="" for="custom_form_' . $who . '_email" id="custom_form_' . $who . '_email_label">\' . lang(\'' . $text . '\') . \'</label>', $src, 'the ' . $who . ' switch label has an id');
		pg_assert_contains('id="show_or_hide_custom_form_' . $who . '_email" role="group" aria-labelledby="custom_form_' . $who . '_email_label"', $src, 'the ' . $who . ' block is named by its switch');
	}
}

function test_erp_accounts_import_duplicate_mode_is_a_legend()
{
	$src = page_form_group_names_source('erp_accounts_import.php');

	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'If the account already exists\') . \'</legend>', $src, 'the duplicate question is a legend');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'If the account already exists\')') === false, 'no orphan duplicate label');
	pg_assert_contains('id="duplicate_mode_update"', $src, 'the update radio keeps its id');
	pg_assert_contains('id="duplicate_mode_skip"', $src, 'the skip radio keeps its id');
}
