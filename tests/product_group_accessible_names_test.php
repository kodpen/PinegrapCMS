<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for accessible names on the product attribute and product
 * group screens: option rows and their move/remove buttons, the default
 * option select, the products table row controls, the Modify Selected
 * button, the image picker tile and the barcode dialogs' close buttons.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_group_accessible_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of a top-level function in backend.src.js, up to the next one.
function product_group_accessible_names_js_function($name)
{
	$js = product_group_accessible_names_source('assets/js/backend.src.js');
	$start = strpos($js, "\nfunction " . $name . '(');
	pg_assert_true($start !== false, $name . '() not found');
	$end = strpos($js, "\nfunction ", $start + 1);

	return $end === false ? substr($js, $start) : substr($js, $start, $end - $start);
}

function test_product_group_accessible_names_move_buttons_are_named_buttons()
{
	foreach (array('init_product_attribute_options', 'init_product_group_attributes') as $function) {
		$body = product_group_accessible_names_js_function($function);

		pg_assert_false(strpos($body, '>north</a>') !== false, $function . ': move up is still an anchor named north');
		pg_assert_false(strpos($body, '>south</a>') !== false, $function . ': move down is still an anchor named south');
		pg_assert_contains('aria-label="\' + h(label[\'Move Up\']) + \'"><span class="material-icons" aria-hidden="true">north</span></button>', $body, $function);
		pg_assert_contains('aria-label="\' + h(label[\'Move Down\']) + \'"><span class="material-icons" aria-hidden="true">south</span></button>', $body, $function);
		pg_assert_contains(".prop('disabled', true)", $body, $function . ': real disabled property');
	}

	$options = product_group_accessible_names_js_function('init_product_attribute_options');
	pg_assert_false(strpos($options, '>delete</a>') !== false, 'remove is still an anchor named delete');
	pg_assert_contains('aria-label="\' + h(label[\'Remove\']) + \'"><span class="material-icons" aria-hidden="true">delete</span></button>', $options);
}

function test_product_group_accessible_names_option_label_and_default_option()
{
	$options = product_group_accessible_names_js_function('init_product_attribute_options');
	pg_assert_contains('class="option_label form-control" aria-label="\' + h(label[\'Option\']) + \'"', $options);

	$form = product_group_accessible_names_source('includes/forms/product_attribute_form.php');
	pg_assert_contains('"Option":"\' . lang(\'Option\') . \'"', $form);
	pg_assert_false(strpos($form, 'Atribute Options') !== false, 'Atribute Options typo');
	pg_assert_contains("lang('Attribute Options')", $form);

	$group = product_group_accessible_names_js_function('init_product_group_attributes');
	pg_assert_contains('<label class="form-label" for="default_option_id_\' + attribute.id + \'">', $group);
	pg_assert_contains('<label class="input-group-text" for="default_option_id_\' + attribute.id + \'">', $group);
	pg_assert_contains('<select id="default_option_id_\' + attribute.id + \'" class="default_option_id form-control">', $group);
}

function test_product_group_accessible_names_image_tile_and_barcode_dialogs()
{
	$js = product_group_accessible_names_source('assets/js/backend.src.js');

	pg_assert_false(strpos($js, 'title="remove"') !== false, 'image tile remove still named by title="remove"');
	pg_assert_contains('bi bi-x-lg p-0" aria-label="\' + h(pgLang(\'Remove\')) + \'"', $js);
	pg_assert_contains('<img class="object-fit-contain w-100 h-100" alt="" src="', $js);
	pg_assert_contains('title="\' + h(decodeURIComponent(properties.image_name)) + \'"', $js);

	pg_assert_false(strpos($js, '<button type="button" class="btn-close" data-bs-dismiss="dialog"></button>') !== false, 'a dialog close button without aria-label');
	pg_assert_contains('<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="\' + h(_L(\'Close\')) + \'"></button>', $js);
	pg_assert_contains('<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="\' + h(pgLang(\'Close\')) + \'"></button>', $js);

	$output = product_group_accessible_names_source('includes/fn/output.php');
	pg_assert_contains("'Remove' => lang('Remove'),", $output);
	pg_assert_contains("'Close' => lang('Close'),", $output);
}

function test_product_group_accessible_names_products_table_rows()
{
	$files = array(
		'edit_product_group.php' => '$item[\'name\']',
		'includes/forms/product_group_form.php' => '$urow[\'name\']',
		'includes/panel/commerce.php' => '$product[\'name\']',
	);

	foreach ($files as $path => $name) {
		$php = product_group_accessible_names_source($path);
		pg_assert_contains('name="products[]"', $php, $path);
		pg_assert_contains('aria-label="\' . h(lang(array(\'string\' => \'Select {var:1}\', \'vars\' => array(' . $name . ')))) . \'"', $php, $path . ' row checkbox');
		pg_assert_contains('aria-label="\' . h(lang(\'Sort Order\') . \': \' . ' . $name . ') . \'"', $php, $path . ' sort order input');
	}
}

function test_product_group_accessible_names_group_screen_buttons_and_landmarks()
{
	$edit = product_group_accessible_names_source('edit_product_group.php');
	pg_assert_contains('aria-label="\' . h(lang(\'Modify Selected Products\')) . \'"', $edit);
	pg_assert_contains('<span class="material-icons" aria-hidden="true">living</span></button>', $edit);
	pg_assert_false(strpos($edit, 'material-icons" onclick="convert_product_group_to_product_form') !== false, 'Modify Selected named by its ligature');
	pg_assert_contains('aria-label="\' . h(lang(\'Button Bar\')) . \'"', $edit);
	pg_assert_contains('aria-label="\' . h(lang(\'data edit buttons\')) . \'"', $edit);
	pg_assert_false(strpos($edit, 'aria-label="data edit buttons ') !== false, 'literal English landmark');

	$add = product_group_accessible_names_source('add_product_group.php');
	pg_assert_contains('aria-label="\' . h(lang(\'data edit buttons\')) . \'"', $add);

	$form = product_group_accessible_names_source('includes/forms/product_group_form.php');
	pg_assert_false(strpos($form, '<label for="address_name" class="input-group-text material-icons"') !== false, 'icon label joins the Catalog Name field name');
}
