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
	pg_assert_same('[{"label":"Red"}]', pg_product_attribute_form_output_options('[{"label":"Red"}]'), 'a list is kept as it is');
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
