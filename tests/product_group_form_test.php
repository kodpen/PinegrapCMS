<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product group form shared by the create and the edit
 * screens (includes/forms/product_group_form.php): the text the catalog
 * address is made from, the cover image split, and that both screens use
 * the shared form and the token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function test_product_group_form_address_source()
{
	$post = array('address_name' => 'yaz-indirimi', 'short_description' => 'Yaz', 'name' => 'Yaz Ürünleri');

	pg_assert_same('yaz-indirimi', pg_product_group_form_address_source($post), 'the address name typed');
	pg_assert_same('Yaz', pg_product_group_form_address_source(array('address_name' => '') + $post), 'else the short description');
	pg_assert_same('Yaz Ürünleri', pg_product_group_form_address_source(array('name' => 'Yaz Ürünleri')), 'else the name');
	pg_assert_same('0', pg_product_group_form_address_source(array('address_name' => '0', 'name' => 'x')), 'a 0 is a value');
	pg_assert_same('x', pg_product_group_form_address_source(array('address_name' => array('a'), 'name' => 'x')), 'a list counts as not given');
	pg_assert_same('', pg_product_group_form_address_source(array()), 'nothing posted');
}

function test_product_group_form_images()
{
	pg_assert_same(array('', array()), pg_product_group_form_images(array()), 'none picked');
	pg_assert_same(array('a.jpg', array()), pg_product_group_form_images(array('a.jpg')), 'the cover only');
	pg_assert_same(array('a.jpg', array('b.jpg', 'c.jpg')), pg_product_group_form_images(array(3 => 'a.jpg', 7 => 'b.jpg', 9 => 'c.jpg')), 'the rest in the order picked');
}

function test_product_group_form_screens_use_the_shared_form()
{
	foreach (array('add_product_group.php', 'edit_product_group.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');", $source, $file . ': the form');
		pg_assert_contains('pg_product_group_form_cards($liveform, ', $source, $file . ': the cards');
		pg_assert_contains('pg_product_group_form_products_card(array(', $source, $file . ': the products card');
		pg_assert_contains('pg_product_group_form_unselected_rows(', $source, $file . ': the products offered');
		pg_assert_contains('pg_product_group_form_link_products(', $source, $file . ': the product links');
		pg_assert_contains('get_token_field()', $source, $file . ': the token field');
		pg_assert_contains('validate_token_field();', $source, $file . ': the token check');
	}

	// The image code is printed escaped on both screens.
	pg_assert_contains("<textarea id=\"code\" name=\"code\">' . h(\$v['code']) . '</textarea>", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php'), 'code escaped');
}
