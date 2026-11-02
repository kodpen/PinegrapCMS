<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product attribute form shared by the create and the edit
 * screens (includes/forms/product_attribute_form.php): the options list
 * the form starts with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_attribute_form.php');

function test_product_attribute_form_output_options()
{
	$empty = '[{label: ""},{label: ""}]';

	pg_assert_same($empty, pg_product_attribute_form_output_options(''), 'nothing posted starts with two empty options');
	pg_assert_same($empty, pg_product_attribute_form_output_options('[]'), 'an empty list starts with two empty options');
	pg_assert_same($empty, pg_product_attribute_form_output_options('not json'), 'a broken list starts with two empty options');
	pg_assert_same('[{"id":"4","label":"Red","no_value":""}]', pg_product_attribute_form_output_options('[{"id":4,"label":"Red","extra":"x"}]'), 'a list keeps only id, label and no value');

	$output = pg_product_attribute_form_output_options('[{"id":"","label":"M </script><b id=\\"x\\">\'&","no_value":"1"}]');
	pg_assert_false(strpos($output, '<') !== false || strpos($output, '>') !== false, 'no tag survives into the script');
	pg_assert_false(strpos($output, "'") !== false || strpos($output, '&') !== false, 'no quote or ampersand survives into the script');
	$decoded = json_decode($output, true);
	pg_assert_same('M </script><b id="x">\'&', $decoded[0]['label'], 'the posted label comes back unchanged');
	pg_assert_same('1', $decoded[0]['no_value'], 'the no value flag comes back');
}

function test_product_attribute_form_clean_options()
{
	$clean = pg_product_attribute_form_clean_options(array(
		array('id' => '', 'label' => ' Red ', 'no_value' => ''),
		array('id' => '', 'label' => '', 'no_value' => ''),
		array('id' => '7', 'label' => '   ', 'no_value' => '1'),
		array('id' => '9', 'label' => 'Blue', 'no_value' => '1'),
		'junk',
	));

	pg_assert_same(array(
		array('id' => 0, 'label' => 'Red', 'no_value' => ''),
		array('id' => 9, 'label' => 'Blue', 'no_value' => '1'),
	), $clean, 'labels are trimmed and blank rows dropped');
	pg_assert_same(array(), pg_product_attribute_form_clean_options(array(array('label' => ''), array('label' => ' '))), 'only blank rows leave nothing');
	pg_assert_same(array(), pg_product_attribute_form_clean_options('x'), 'no list leaves nothing');
}

function test_product_attribute_form_duplicate_label()
{
	pg_assert_same('', pg_product_attribute_form_duplicate_label(array(array('label' => 'S'), array('label' => 'M'))), 'different labels pass');
	pg_assert_same('S', pg_product_attribute_form_duplicate_label(array(array('label' => 'S'), array('label' => 'S'))), 'the same label is found');
	pg_assert_same('kırmızı', pg_product_attribute_form_duplicate_label(array(array('label' => 'Kırmızı'), array('label' => 'kırmızı'))), 'case is ignored');
	pg_assert_same('ÇİÇEK', pg_product_attribute_form_duplicate_label(array(array('label' => 'çiçek'), array('label' => 'ÇİÇEK'))), 'case is ignored beyond ASCII');
}

function test_product_attribute_edit_screen_scopes_options()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_product_attribute.php');

	pg_assert_contains('SET default_option_id = 0', $source, 'a removed option is no product group default any more');
	pg_assert_contains('AND (product_attribute_id = ?)', $source, 'option updates stay on the edited attribute');
	pg_assert_contains("\$liveform->get_field_value('name')", substr($source, strpos($source, 'was modified') - 400, 500), 'the log names the new name');
}

function test_product_attribute_form_screens_use_shared_form()
{
	foreach (array('add_product_attribute.php', 'edit_product_attribute.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/product_attribute_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_product_attribute_form_cards($liveform)', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_product_attribute_form_check($liveform, ', $source, $file . ' checks the name and options the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'init_product_attribute_options') !== false, $file . ' keeps no copy of the cards');
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_attribute_form.php');
	pg_assert_contains('db_q($query, $values)', $form, 'the name check binds its values');
	pg_assert_false((bool) preg_match('/(?<![\w>$])e\s*\(/', $form), 'the form escapes no value into SQL');
}
