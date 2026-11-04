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

function test_product_group_form_member_rows()
{
	// The form listed 1, 2 and 3; meanwhile 9 joined the group elsewhere.
	pg_assert_same(array('delete' => array(1, 2, 3), 'keep' => array(9)), pg_product_group_form_member_rows('1,2,3', array(1, 2, 3, 9)), 'a product that joined later is kept');
	pg_assert_same(array('delete' => array(2), 'keep' => array(7)), pg_product_group_form_member_rows('1,2', array('2', '7')), 'a listed member already gone is not deleted again');
	pg_assert_same(array('delete' => array(), 'keep' => array(4, 5)), pg_product_group_form_member_rows('', array(4, 5)), 'an empty list deletes nothing');
	pg_assert_same(array('delete' => array(4, 5), 'keep' => array()), pg_product_group_form_member_rows(null, array(4, 5, 5)), 'a form without the field deletes every row, as before');
	pg_assert_same(array('delete' => array(3), 'keep' => array(8)), pg_product_group_form_member_rows('3,x,-1,0,3', array(3, 8)), 'only positive ids count');
	pg_assert_same(array('delete' => array(), 'keep' => array(3)), pg_product_group_form_member_rows(array('3'), array(3)), 'a list posted as an array counts as not listed');
}

function test_product_group_form_edit_save_scoped_to_listed_members()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_product_group.php');

	pg_assert_contains('<input type="hidden" name="listed_product_ids" value="\' . h(implode(\',\', array_map(\'intval\', $selected_products))) . \'" />', $source, 'the listed members are posted back');
	pg_assert_contains("pg_product_group_form_member_rows(isset(\$_POST['listed_product_ids']) ? \$_POST['listed_product_ids'] : null, ", $source, 'the save splits the rows by the list');
	pg_assert_true(strpos($source, '"DELETE FROM products_groups_xref WHERE product_group = ?"' . "\n") === false, 'no group-wide delete');
}

// The top group's display type is not saved, so its choice is disabled.
function test_product_group_form_root_display_type_disabled()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

	pg_assert_contains("<fieldset' . (\$form['is_root'] ? ' disabled aria-describedby=\"display_type_root_note\"' : '') . '>", $source, 'the fieldset is disabled for the top group');
	pg_assert_contains("'is_root' => false", $source, 'other groups keep the choice');
	pg_assert_contains("'is_root' => (\$parent_id == 0),", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_product_group.php'), 'the edit screen passes the flag');
}

function test_product_group_form_sort_order()
{
	pg_assert_same(0, pg_product_group_form_sort_order(''), 'an empty box is 0');
	pg_assert_same(0, pg_product_group_form_sort_order(null), 'no box is 0');
	pg_assert_same(7, pg_product_group_form_sort_order('7'), 'a whole number');
	pg_assert_same(65535, pg_product_group_form_sort_order(' 65535 '), 'the column maximum');
	pg_assert_same(false, pg_product_group_form_sort_order('-12'), 'a negative number');
	pg_assert_same(false, pg_product_group_form_sort_order('12,5'), 'a decimal');
	pg_assert_same(false, pg_product_group_form_sort_order('65536'), 'past the column');
	pg_assert_same(false, pg_product_group_form_sort_order(array('1')), 'a list');
}

function test_product_group_form_post_problems()
{
	pg_assert_same(array(), pg_product_group_form_post_problems(array('name' => 'Yaz', 'short_description' => 'Kısa')), 'a valid form');
	pg_assert_same(array('name' => 'required'), pg_product_group_form_post_problems(array('name' => '   ')), 'a name of spaces');
	pg_assert_same(array('name' => 'required'), pg_product_group_form_post_problems(array()), 'no name');
	pg_assert_same(array('name' => 'too_long'), pg_product_group_form_post_problems(array('name' => str_repeat('ş', 101))), 'a name past 100 characters');
	pg_assert_same(array(), pg_product_group_form_post_problems(array('name' => str_repeat('ş', 100))), '100 characters fit');
	pg_assert_same(array('short_description' => 'too_long'), pg_product_group_form_post_problems(array('name' => 'Yaz', 'short_description' => str_repeat('a', 108))), 'a short description past 100 characters');

	$post = array(
		'name' => 'Yaz',
		'products' => array('250', '251'),
		'sort_order_product_250' => '7',
		'sort_order_product_251' => '-12',
		'sort_order_product_252' => 'x',
		'sort_order_product_group_9' => '1.5',
		'sort_order_product_group_10' => '',
	);
	pg_assert_same(array('sort_order_product_251' => 'sort_order', 'sort_order_product_group_9' => 'sort_order'), pg_product_group_form_post_problems($post), 'ticked products and child groups only');
}
