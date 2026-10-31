<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu item form shared by the create and the edit screens
 * (includes/forms/menu_item_form.php): what a posted item links to, which
 * addresses the URL box refuses, and that both screens and the external API
 * use the shared form and rule.
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
	pg_assert_same(array('link_page_id' => '', 'link_url' => '/x'), pg_menu_item_form_link(array('link_url' => "  /x\n")), 'the URL is trimmed');
}

function test_menu_item_form_url_problem_refuses()
{
	$space = lang('The address cannot contain spaces or control characters.');
	$scheme = lang('The address must be a web, e-mail or phone address, or a path on this site.');

	pg_assert_same($scheme, pg_menu_item_url_problem('javascript:alert(1)'), 'javascript: is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('JavaScript:alert(1)'), 'the scheme is read in any case');
	pg_assert_same($space, pg_menu_item_url_problem(" java\tscript:alert(1)"), 'a tab inside the scheme is refused');
	pg_assert_same($space, pg_menu_item_url_problem("/a\x00b"), 'a control character is refused');
	pg_assert_same($space, pg_menu_item_url_problem('/a b'), 'a space is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('data:text/html,<script>alert(1)</script>'), 'data: is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('vbscript:x'), 'another scheme is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('//evil.example/'), 'a protocol-relative address is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('/\\evil.example/'), 'a backslash is refused');
}

function test_menu_item_form_url_problem_keeps()
{
	foreach (array('', '   ', 'https://example.com/a?b=1#c', 'http://example.com', 'mailto:info@example.com', 'tel:+905551112233', '/path/page', 'page', '#anchor', '?q=1', ' /trimmed ') as $url) {
		pg_assert_same('', pg_menu_item_url_problem($url), var_export($url, true) . ' is kept');
	}
}

function test_menu_item_form_api_uses_shared_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/menus.php');

	pg_assert_contains('includes/forms/menu_item_form.php', $source, 'the API loads the shared rule');
	pg_assert_contains('pg_menu_item_url_problem(', $source, 'the API checks the URL the shared way');
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
		pg_assert_contains('pg_menu_item_url_problem(', $source, $file . ' checks the URL');
	}
}
