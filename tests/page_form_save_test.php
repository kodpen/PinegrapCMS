<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the save half of the page form shared by add_page.php and
 * edit_page.php (includes/forms/page_form.php): the cleaned name, the search
 * engine switches and their SQL, the redirect to the form designer, and that
 * both screens call the shared helpers instead of their own copies.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

function test_page_form_clean_name()
{
	pg_assert_same(array('name' => 'my_page_x', 'error' => '', 'too_long' => false), pg_page_form_clean_name('my page&x', 'standard'));
	pg_assert_same('a/b', pg_page_form_clean_name('a/b', 'standard')['name'], 'a slash is kept on other types');
	pg_assert_same('', pg_page_form_clean_name('a/b', 'standard')['error']);
	pg_assert_true(pg_page_form_clean_name('a/b', 'catalog')['error'] !== '', 'catalog refuses a slash');
	pg_assert_true(pg_page_form_clean_name('a/b', 'catalog detail')['error'] !== '', 'catalog detail refuses a slash');
	pg_assert_same('', pg_page_form_clean_name('shop', 'catalog')['error']);
}

function test_page_form_clean_name_length()
{
	$fits = str_repeat('ş', 100);
	pg_assert_same('', pg_page_form_clean_name($fits, 'standard')['error'], '100 characters fit, counted as characters');
	$long = pg_page_form_clean_name($fits . 'a', 'standard');
	pg_assert_true($long['too_long'], '101 characters are too long');
	pg_assert_true($long['error'] !== '', 'a too long name is refused');
	pg_assert_same(null, pg_page_form_bulk_name('a/b', 'catalog'), 'a slash is still refused in bulk');
	pg_assert_same($fits . 'a', pg_page_form_bulk_name($fits . 'a', 'standard'), 'bulk edit checks the length itself');
}

function test_page_subnav_link_escapes()
{
	pg_assert_same('<a href="/x%22onmouseover%3D%22a">x&quot;onmouseover=&quot;a</a>', pg_page_subnav_link('x"onmouseover="a', '/'));
	pg_assert_same('<a href="/pg-%3Ci%3Ex">pg-&lt;i&gt;x</a>', pg_page_subnav_link('pg-<i>x', '/'));
}

function test_page_rename_notice()
{
	$none = pg_page_rename_notice(array('notes' => array(), 'warnings' => array()), 'https://x/shop', 'https://x/pinegrap/edit_page.php?id=1');
	pg_assert_same(array('location' => 'https://x/shop', 'form' => '', 'notes' => array(), 'warnings' => array()), $none, 'nothing to say keeps the target');

	$site = pg_page_rename_notice(array('notes' => array('a <b>'), 'warnings' => array()), 'https://x/shop', 'https://x/pinegrap/edit_page.php?id=1');
	pg_assert_same('https://x/pinegrap/edit_page.php?id=1', $site['location'], 'a site page target comes back to Edit Page');
	pg_assert_same('edit_page', $site['form']);
	pg_assert_same(array('a &lt;b&gt;'), $site['notes'], 'messages are escaped');

	$panel = pg_page_rename_notice(array('notes' => array(), 'warnings' => array('w')), 'https://x/pinegrap/view_fields.php?page_id=1', 'e');
	pg_assert_same('view_fields', $panel['form'], 'a panel screen target keeps the message');
	pg_assert_same('https://x/pinegrap/view_fields.php?page_id=1', $panel['location']);
	pg_assert_same(array('w'), $panel['warnings']);
}

function test_page_form_index_flags()
{
	pg_assert_same(array('sitemap' => 1, 'noindex' => 0, 'nofollow' => 0), pg_page_form_index_flags(true, array()));
	pg_assert_same(array('sitemap' => 0, 'noindex' => 0, 'nofollow' => 0), pg_page_form_index_flags(false, array('nofollow' => '1')), 'nofollow only with noindex');
	pg_assert_same(array('sitemap' => 0, 'noindex' => 1, 'nofollow' => 1), pg_page_form_index_flags(1, array('noindex' => '1', 'nofollow' => '1')), 'noindex leaves the site map');
	pg_assert_same(array('sitemap' => 0, 'noindex' => 1, 'nofollow' => 0), pg_page_form_index_flags(1, array('noindex' => '1')));
}

function test_page_form_noindex_sql()
{
	pg_assert_same(array('columns' => '', 'values' => '', 'set' => ''), pg_page_form_noindex_sql(array('noindex' => 1, 'nofollow' => 1), false), 'no columns yet');

	$sql = pg_page_form_noindex_sql(array('noindex' => 1, 'nofollow' => 0), true);
	pg_assert_same("noindex, nofollow,", $sql['columns']);
	pg_assert_same("'1', '0',", $sql['values']);
	pg_assert_same("noindex = '1', nofollow = '0',", $sql['set']);
}

function test_page_form_designer_redirect_new_page()
{
	pg_assert_same('', pg_page_form_designer_redirect('custom form', array()), 'a new custom form');
	pg_assert_same(false, pg_page_form_designer_redirect('standard', array()));
	pg_assert_same('', pg_page_form_designer_redirect('shipping address and arrival', array('shipping_address_and_arrival_form' => '1')));
	pg_assert_same(false, pg_page_form_designer_redirect('shipping address and arrival', array()));
	pg_assert_same('', pg_page_form_designer_redirect('billing information', array('billing_information_form' => '1')));
	pg_assert_same('&form_type=shipping', pg_page_form_designer_redirect('express order', array('express_order_shipping_form' => '1', 'express_order_form' => '1')), 'shipping first');
	pg_assert_same('&form_type=billing', pg_page_form_designer_redirect('express order', array('express_order_form' => '1')));
	pg_assert_same(false, pg_page_form_designer_redirect('express order', array()));
}

function test_page_form_designer_redirect_edited_page()
{
	$stored = array('type' => 'custom form', 'shipping_address_and_arrival_form' => '1', 'billing_information_form' => '0', 'shipping_form' => '1', 'form' => '0');

	pg_assert_same(false, pg_page_form_designer_redirect('custom form', array(), $stored), 'already a custom form');
	pg_assert_same('', pg_page_form_designer_redirect('custom form', array(), array('type' => 'standard')), 'switched to a custom form');
	pg_assert_same(false, pg_page_form_designer_redirect('shipping address and arrival', array('shipping_address_and_arrival_form' => '1'), $stored), 'form was on already');
	pg_assert_same('', pg_page_form_designer_redirect('billing information', array('billing_information_form' => '1'), $stored), 'switched on now');
	pg_assert_same('&form_type=billing', pg_page_form_designer_redirect('express order', array('express_order_shipping_form' => '1', 'express_order_form' => '1'), $stored), 'only the form switched on now');
	pg_assert_same(false, pg_page_form_designer_redirect('express order', array('express_order_shipping_form' => '1'), $stored));
}

function test_page_form_save_wiring()
{
	foreach (array('add_page.php', 'edit_page.php') as $path) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);

		foreach (array('pg_page_form_clean_name(', 'pg_page_type_custom_form_errors(', 'pg_page_form_index_flags(', 'pg_page_form_noindex_sql(', 'pg_page_form_designer_redirect(') as $call) {
			pg_assert_same(1, substr_count($source, $call), $path . ' calls ' . $call);
		}

		pg_assert_same(0, substr_count($source, 'pg_cf_legacy_settings_errors('), $path . ' has no copy of the custom form check');
		pg_assert_same(0, substr_count($source, 'FROM custom_form_pages WHERE'), $path . ' has no copy of the form name query');
		pg_assert_same(0, substr_count($source, "set_page_type_"), $path . ' reads the type rights through pg_page_type_user_may_set()');
	}

	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_page.php');
	pg_assert_true(strpos($add, "/includes/fn/page_type_pane.php'") !== false, 'add_page.php loads the rights');
	pg_assert_same(1, substr_count($add, 'pg_page_type_user_may_set('));
	pg_assert_true(strpos($add, 'pg_page_form_designer_redirect($type, $_POST)') !== false, 'a new page redirects for every form that is on');
}

function test_bracket_suffix_name()
{
	pg_assert_same('a[1]', pg_bracket_suffix_name('a'));
	pg_assert_same('a[3]', pg_bracket_suffix_name('a[2]'));
	$hundred = 'pg-' . str_repeat('ğ', 97);
	pg_assert_same(100, mb_strlen(pg_bracket_suffix_name($hundred, 100), 'UTF-8'), 'a page copy fits the column');
	pg_assert_same(mb_substr($hundred, 0, 97, 'UTF-8') . '[1]', pg_bracket_suffix_name($hundred, 100), 'the base is cut, not the suffix');
	pg_assert_same(mb_substr($hundred, 0, 96, 'UTF-8') . '[10]', pg_bracket_suffix_name(mb_substr($hundred, 0, 97, 'UTF-8') . '[9]', 100));
	pg_assert_same($hundred . '[1]', pg_bracket_suffix_name($hundred), 'no limit for other types');
}
