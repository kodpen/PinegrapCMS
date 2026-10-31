<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for accessible names on the product screen
 * (product_builder.php and assets/js/product_builder.js): variant matrix
 * inputs, the advanced row's labels, the image chip remove button, the
 * attribute dialog and card, the barcode type and the variant sets list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_screen_accessible_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_product_screen_accessible_names_variant_matrix_inputs()
{
	$js = product_screen_accessible_names_source('assets/js/product_builder.js');

	foreach (array('name' => 'Product ID / SKU', 'short' => 'Short Description', 'price' => 'Unit Price', 'stock' => 'Inventory Quantity') as $class => $column) {
		pg_assert_true(
			(bool) preg_match('/<input[^\n]*pg-pb-v-' . $class . '"[^\n]*cellName\(\'' . preg_quote($column, '/') . '\', combo\)/', $js),
			'matrix input pg-pb-v-' . $class . ' has no aria-label'
		);
	}

	pg_assert_contains("function cellName(column, combo)", $js);
}

function test_product_screen_accessible_names_advanced_row_labels()
{
	$js = product_screen_accessible_names_source('assets/js/product_builder.js');

	foreach (array('gtin', 'barcode', 'start', 'payments', 'period') as $field) {
		pg_assert_contains('<label class="form-label small mb-1" for="pg_pb_v_' . $field . '_\' + index + \'">', $js, $field . ' label');
		pg_assert_contains('id="pg_pb_v_' . $field . '_\' + index + \'"', $js, $field . ' field id');
	}
}

function test_product_screen_accessible_names_advanced_toggle_state()
{
	$js = product_screen_accessible_names_source('assets/js/product_builder.js');

	// The toggle names its variant and says whether the row it opens is open.
	pg_assert_contains('function advancedToggle(index, combo)', $js);
	pg_assert_contains('advancedToggle(index, combo) +', $js);
	pg_assert_contains('aria-controls="pg_pb_adv_\' + index + \'" aria-expanded="false"', $js);
	pg_assert_contains("esc(label('Advanced Settings') + ': ' + comboLabel(combo))", $js);
	pg_assert_contains('<i class="bi bi-chevron-down me-1" aria-hidden="true"></i>', $js);
	pg_assert_same(2, substr_count($js, '<tr class="pg-pb-adv-row d-none" id="pg_pb_adv_\' + index + \'" data-pg-pb-adv="'), 'both advanced rows carry the id');
	pg_assert_contains("$(this).attr('aria-expanded', open ? 'true' : 'false');", $js);
}

function test_product_screen_accessible_names_image_chip_remove()
{
	$js = product_screen_accessible_names_source('assets/js/product_builder.js');
	$php = product_screen_accessible_names_source('product_builder.php');

	pg_assert_false(strpos($js, 'pg-pb-chip-remove" tabindex="-1"') !== false, 'chip remove button out of the tab order');
	pg_assert_contains('class="pg-pb-chip-remove" aria-label="\' + esc(label(\'Remove image\')) + \'"', $js);
	pg_assert_contains('.pg-pb-strip .pg-pb-chip:focus-within .pg-pb-chip-remove', $php);
	// Hidden with opacity, not display:none: a button with no box takes no
	// focus, so :focus-within could never match and Tab skipped it.
	pg_assert_false((bool) preg_match('/\.pg-pb-chip-remove \{[^}]*display:\s*none/', $php), 'chip remove button hidden with display:none');
	pg_assert_true((bool) preg_match('/\.pg-pb-chip \.pg-pb-chip-remove \{[^}]*opacity:\s*0;/', $php), 'chip remove button not hidden with opacity');
	pg_assert_contains('@media (hover: none)', $php);
	pg_assert_contains("'Remove image'", $php);
}

function test_product_screen_accessible_names_attribute_dialog()
{
	$js = product_screen_accessible_names_source('assets/js/product_builder.js');
	$php = product_screen_accessible_names_source('product_builder.php');

	pg_assert_contains('<label class="input-group-text">', $js);
	pg_assert_contains('id="pg_pb_attribute_modal" aria-labelledby="pg_pb_attribute_modal_title"', $php);
	pg_assert_contains('<h5 class="dialog-title" id="pg_pb_attribute_modal_title">', $php);
	pg_assert_contains('id="image_code" aria-labelledby="image_code_title"', $php);
	pg_assert_contains('<h5 class="dialog-title" id="image_code_title">', $php);
	pg_assert_false(strpos($php, "lang('Atribute Options')") !== false, 'typo key still used');
	pg_assert_contains("lang('Attribute Options')", $php);
}

function test_product_screen_accessible_names_card_and_list_fields()
{
	$php = product_screen_accessible_names_source('product_builder.php');

	pg_assert_contains('<label class="fg-secondary small mb-0" for="pg_pb_attr_default_', $php);
	pg_assert_contains('class="form-control form-control-sm pg-pb-attr-default" id="pg_pb_attr_default_', $php);
	pg_assert_contains("<select id=\"pg-barcode-type\" aria-label=\"' . h(lang('Barcode Type')) . '\"", $php);
	pg_assert_contains("name=\"group_ids[]\" value=\"' . \$set_id . '\" aria-label=\"' . h(lang(array('string' => 'Select {var:1}', 'vars' => array(", $php);
	pg_assert_contains("<nav id=\"button_bar\" class=\"navigation\" aria-label=\"' . h(lang('Button Bar')) . '\">", $php);
}

function test_product_screen_accessible_names_option_boxes()
{
	$php = product_screen_accessible_names_source('product_builder.php');
	$js  = product_screen_accessible_names_source('assets/js/product_builder.js');

	// The Add Option box says which attribute it adds to.
	pg_assert_contains("pg-pb-option-add-input\" maxlength=\"255\" placeholder=\"' . lang('Label') . '\" aria-label=\"' . h(lang(array('string' => 'New option for {var:1}', 'vars' => array(\$attribute['name'])))) . '\" />", $php);

	// The New Attribute option rows are numbered and grouped under their caption.
	pg_assert_contains('<label class="form-label" id="pg_pb_attr_options_label">', $php);
	pg_assert_contains('<div id="pg_pb_attr_options" role="group" aria-labelledby="pg_pb_attr_options_label"></div>', $php);
	pg_assert_contains("'option_n'           => lang(array('string' => 'Option {var:1}', 'vars' => array('{n}'))),", $php);
	pg_assert_contains("function numberOptionRows()", $js);
	pg_assert_contains("\$(this).attr('aria-label', label('option_n').replace('{n}', index + 1));", $js);
}
