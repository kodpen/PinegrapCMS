<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for field help that was only a hover popover: the folder,
 * design file, menu item, page and catalog address, migration, Paraşüt id,
 * Custom layout and barcode stock hints are printed under their field as
 * form-text the field points at with aria-describedby, with no title,
 * data-bs-content or " (?)" left on the label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function field_hint_visible_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// Each file: the hint ids it prints, the describedby it gives, and the hover
// text that must be gone.
function field_hint_visible_cases()
{
	return array(
		'includes/forms/folder_form.php' => array(
			'hints' => array('order_hint', 'folder_archived_hint', 'access_control_type_hint'),
			'gone' => array("data-bs-content=\"' . lang('Display order", "data-bs-content=\"' . lang('Archive Folder", "data-bs-content=\"' . lang('Access Control for all"),
		),
		'includes/forms/design_file_form.php' => array(
			'hints' => array('design_hint', 'theme_hint'),
			'gone' => array('data-bs-original-title', 'data-bs-content'),
		),
		'includes/forms/menu_item_form.php' => array(
			'hints' => array('security_hint'),
			'gone' => array("data-bs-content=\"' . lang('only show Menu Item"),
		),
		'includes/forms/product_group_form.php' => array(
			'hints' => array('address_name_hint'),
			'gone' => array("title=\"' . lang('This option determines the url address of the product."),
		),
		'add_page.php' => array(
			'hints' => array('name_hint'),
			'gone' => array("title=\"' . lang('This option determines the url address of the page.')"),
		),
		'edit_page.php' => array(
			'hints' => array('name_hint'),
			'gone' => array("title=\"' . lang('This option determines the url address of the page.')"),
		),
		'mass_edit.php' => array(
			'hints' => array("_hint\""),
			'gone' => array("title=\"' . lang('This option determines the url address of the page.')"),
		),
		'migration.php' => array(
			'hints' => array('url_hint'),
			'gone' => array("data-bs-content=\"' . lang('Example')"),
		),
		'edit_contact.php' => array(
			'hints' => array('parasut_contact_id_hint'),
			'gone' => array("title=\"' . lang('Set automatically when the first invoice is created.')"),
		),
		'includes/forms/page_form.php' => array(
			'hints' => array('layout_type_custom_hint'),
			'gone' => array("<label title=\"' . \$layout_type_custom_label_title"),
		),
		'barcode_increase_inventory.php' => array(
			'hints' => array('scanner_input_hint'),
			'gone' => array("data-bs-content=\"' . lang('-Last Scan", "lang('what is this?')"),
		),
		'barcode_decrease_inventory.php' => array(
			'hints' => array('scanner_input_hint'),
			'gone' => array("data-bs-content=\"' . lang('-Last Scan", "lang('what is this?')"),
		),
	);
}

function test_field_hint_visible_hints_are_printed()
{
	foreach (field_hint_visible_cases() as $path => $case) {
		$source = field_hint_visible_source($path);
		pg_assert_true($source !== '', $path . ' read');
		pg_assert_false(strpos($source, ' (?)<') !== false || strpos($source, " (?)</label>") !== false, $path . ': no (?) after a caption');
		foreach ($case['gone'] as $gone) {
			pg_assert_false(strpos($source, $gone) !== false, $path . ': hover hint gone: ' . $gone);
		}
		foreach ($case['hints'] as $hint) {
			pg_assert_contains('class="form-text', $source, $path . ': prints form-text');
			pg_assert_contains($hint, $source, $path . ': hint ' . $hint);
		}
	}
}

function test_field_hint_visible_fields_point_at_their_hint()
{
	$pins = array(
		'includes/forms/folder_form.php' => array('id="order" aria-describedby="order_hint"', 'id="folder_archived" aria-describedby="folder_archived_hint"', 'id="access_control_type" aria-describedby="access_control_type_hint"', '<div class="form-text" id="order_hint">'),
		'includes/forms/design_file_form.php' => array('aria-describedby="design_hint" id="design"', 'aria-describedby="theme_hint" id="theme"'),
		'includes/forms/menu_item_form.php' => array("'aria-describedby'=>'security_hint'", 'id="security_hint"'),
		'includes/forms/product_group_form.php' => array('id="address_name" aria-describedby="address_name_hint"', '<div class="form-text" id="address_name_hint">'),
		'add_page.php' => array("'aria-describedby'=>'name_hint'", '<div class="form-text" id="name_hint">'),
		'edit_page.php' => array('id="name" aria-describedby="name_hint"', '<div class="form-text" id="name_hint">'),
		'migration.php' => array("'aria-describedby'=>'url_hint'", 'id="url_hint"'),
		'edit_contact.php' => array('id="parasut_contact_id" aria-describedby="parasut_contact_id_hint"', 'id="parasut_contact_id_hint"'),
		'includes/forms/page_form.php' => array('id="layout_type_custom_hint"', 'aria-describedby="layout_type_custom_hint"'),
		'barcode_increase_inventory.php' => array('aria-describedby="scanner_input_hint"', 'id="scanner_input_hint"'),
		'barcode_decrease_inventory.php' => array('aria-describedby="scanner_input_hint"', 'id="scanner_input_hint"'),
	);
	foreach ($pins as $path => $needles) {
		$source = field_hint_visible_source($path);
		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}
}

// The address hints show the pattern in <code>, escaped, like Short Link.
function test_field_hint_visible_address_pattern_in_code()
{
	foreach (array('add_page.php', 'edit_page.php', 'mass_edit.php', 'includes/forms/product_group_form.php') as $path) {
		pg_assert_contains("<code>' . h(URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang(", field_hint_visible_source($path), $path);
	}
}
