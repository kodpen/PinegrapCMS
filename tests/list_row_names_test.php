<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get in the orders, products, submitted
 * forms, pages and files lists: each row box names its row, the SEO links
 * name the score rather than reading the bare number, the No Image picture
 * is named, and a folder's access level is given by an icon and a word, not
 * by the cell's colour alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $list_row_names_name => $list_row_names_value) {
	if (!defined($list_row_names_name)) {
		define($list_row_names_name, $list_row_names_value);
	}
}

function list_row_names_source($path)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function list_row_names_select($name)
{
	return 'aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($name)))) . '"';
}

function test_list_row_names_seo_link_name()
{
	pg_assert_same(lang('SEO score has not been calculated yet.'), pg_seo_link_name(array('seo_score' => '0', 'seo_analysis_current' => '0', 'seo_checked_at' => '0')));
	pg_assert_same(lang('SEO Score') . ': 85/100', pg_seo_link_name(array('seo_score' => '85', 'seo_flags' => '0', 'seo_analysis_current' => '1', 'seo_checked_at' => (string) time())));
}

function test_list_row_names_product_row()
{
	$_SESSION['software']['list_state']['view_products'] = array();
	$view = pg_product_list_view('all_products', true, false, true, array(1 => 'Colour', 2 => '', 3 => '', 4 => ''), true);
	$product = array(
		'id' => '12', 'name' => 'MUG <1>', 'enabled' => '1', 'image_name' => '', 'inventory' => '1', 'inventory_quantity' => '4',
		'short_description' => 'Mug', 'price' => '1250', 'taxable' => '1', 'form_name' => '', 'seo_score' => '0',
		'seo_flags' => '0', 'seo_checked_at' => '0', 'seo_analysis_current' => '0', 'seo_impact' => null, 'seo_views' => '0',
		'shippable' => '1', 'selection_type' => 'quantity', 'default_quantity' => '1', 'custom_field_1' => '',
		'custom_field_2' => '', 'custom_field_3' => '', 'custom_field_4' => '', 'recurring' => '0', 'barcode_count' => '0',
		'user' => 'ali', 'out_of_stock' => '0', 'out_of_stock_timestamp' => '0', 'timestamp' => (string) time(),
	);
	$row = pg_product_list_row($product, $view, array('quick_edit' => pg_pb_quick_edit_columns(), 'more_images' => array(), 'zones' => array()));
	$html = '';

	foreach ($row['cells'] as $cell) {
		$html .= $cell['html'];
	}

	pg_assert_contains(list_row_names_select('MUG <1>'), $row['cells'][0]['html'], 'the row box names the product');
	pg_assert_contains('class="pg-seo-open d-block text-decoration-none" aria-label="' . h(lang('SEO score has not been calculated yet.')) . '"', $html, 'the SEO link names the score');
}

function test_list_row_names_no_image_named()
{
	$html = pg_product_image_cell_content('product-1', '', 'A', '', array());
	pg_assert_contains('role="img" aria-label="' . h(lang('No Image')) . '"', $html);
}

function test_list_row_names_submitted_form_row()
{
	$row = pg_submitted_form_list_row(array(
		'id' => '12', 'complete' => '1', 'form_name' => 'Form <b>', 'reference_code' => 'R&1',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => 'sam',
		'submitted_timestamp' => time(), 'last_modified_username' => 'ed', 'last_modified_timestamp' => time(),
	), false);
	pg_assert_contains(list_row_names_select('Form <b> R&1'), $row['cells'][0]['html'], 'form name and reference code');

	$row = pg_submitted_form_list_row(array(
		'id' => '3', 'complete' => '0', 'form_name' => 'F', 'reference_code' => '',
		'contact_id' => null, 'contact_name' => null, 'submitted_username' => '',
		'submitted_timestamp' => time(), 'last_modified_username' => '', 'last_modified_timestamp' => time(),
	), false);
	pg_assert_contains(list_row_names_select('F'), $row['cells'][0]['html'], 'no reference code, no trailing space');
}

function test_list_row_names_order_row()
{
	$source = list_row_names_source('view_orders.php');
	pg_assert_contains('name="orders[]" value="\' . $id . \'" aria-label="\' . h(lang(array(\'string\' => \'Select {var:1}\', \'vars\' => array($output_row_name)))) . \'"', $source);
	pg_assert_contains("\$output_row_name = (trim((string) \$order_number) !== '') ? \$order_number : trim(\$billing_first_name . ' ' . \$billing_last_name);", $source);
	pg_assert_contains("\$output_row_name = '#' . \$id;", $source);
}

function test_list_row_names_page_seo_links()
{
	$source = list_row_names_source('view_pages.php');
	pg_assert_same(2, substr_count($source, 'aria-label="\' . h(pg_seo_link_name($page)) . \'"'), 'name-cell bar and SEO column badge');
}

function test_list_row_names_folder_access_mark()
{
	foreach (array('public' => 'bi-people-fill', 'guest' => 'bi-incognito', 'registration' => 'bi-person-fill', 'membership' => 'bi-person-vcard-fill', 'private' => 'bi-lock-fill') as $type => $icon) {
		$mark = pg_folder_access_mark($type);
		pg_assert_contains('<i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>', $mark, $type);
		pg_assert_contains('<span class="visually-hidden">' . h(get_access_control_type_name($type)) . '</span>', $mark, $type);
	}

	pg_assert_same('', pg_folder_access_mark('nonsense'));

	foreach (array('view_pages.php' => '$page', 'view_files.php' => '$file') as $path => $var) {
		pg_assert_contains('style="width: 100px;max-width:100%;">\' . pg_folder_access_mark(' . $var . '[\'folder_access_control_type\']) . \'<span class="material-icons d-inline" aria-hidden="true">folder</span>', list_row_names_source($path), $path);
	}
}
