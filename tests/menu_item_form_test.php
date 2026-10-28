<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu item form shared by the create and the edit screens
 * (includes/forms/menu_item_form.php): what a posted item links to, and
 * that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_item_form.php');

function test_menu_item_form_link_page_wins()
{
	pg_assert_same(array('link_page_id' => '12', 'link_url' => ''), pg_menu_item_form_link(array('link_page_id' => '12', 'link_url' => 'https://example.com/')), 'a page blanks the URL');
	pg_assert_same(array('link_page_id' => '', 'link_url' => 'https://example.com/'), pg_menu_item_form_link(array('link_page_id' => '', 'link_url' => 'https://example.com/')), 'no page keeps the URL');
	pg_assert_same(array('link_page_id' => '', 'link_url' => '/x'), pg_menu_item_form_link(array('link_page_id' => '0', 'link_url' => '/x')), 'page 0 is no page');
	pg_assert_same(array('link_page_id' => '', 'link_url' => ''), pg_menu_item_form_link(array()), 'nothing posted');
}

function test_menu_item_form_screens_use_shared_form()
{
	foreach (array('add_menu_item.php', 'edit_menu_item.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/menu_item_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_menu_item_form_card(', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_menu_item_form_link(', $source, $file . ' takes the link the shared way');
		pg_assert_contains('pg_menu_item_form_last_sort_order(', $source, $file . ' puts a moved or new item last');
		pg_assert_contains('pg_menu_item_form_touch_menu(', $source, $file . ' stamps the menu');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
	}
}
