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

// The name is the page's address. IIS decodes the path before PHP reads it:
// a "?" (%3F) cut the name at the router as the query string, and "+" or "%"
// with two hex digits is a double escape IIS answers with 404.11. They become
// underscores as spaces do; "#" (%23) reaches the router whole and is kept.
function test_page_form_clean_name_address_characters()
{
	pg_assert_same('pg-test-soru_isareti', pg_page_form_clean_name('pg-test-soru?isareti', 'standard')['name']);
	pg_assert_same('100__pamuk', pg_page_form_clean_name('100% pamuk', 'standard')['name']);
	pg_assert_same('pg-test/alt_yol_x=1#y_20_z.php', pg_page_form_clean_name('pg-test/alt yol?x=1#y%20&z.php', 'standard')['name']);
	pg_assert_same('', pg_page_form_clean_name('pg-test-soru?isareti', 'standard')['error'], 'cleaned, not refused');
	pg_assert_same('pg-test-diyez#isareti', pg_page_form_clean_name('pg-test-diyez#isareti', 'standard')['name']);
	pg_assert_same('çay_ğ', pg_page_form_clean_name('çay?ğ', 'standard')['name'], 'letters are kept');
	pg_assert_same('pg-test-arti_isaret', pg_page_form_clean_name('pg-test-arti+isaret', 'standard')['name'], 'a plus sign');
	pg_assert_same('C__', pg_page_form_clean_name('C++', 'standard')['name']);
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

function test_page_form_number_errors()
{
	$cf = function ($fields) {
		return array_keys(pg_page_form_number_errors('custom form', $fields));
	};
	pg_assert_same(array(), $cf(array('custom_form_quiz' => '1', 'custom_form_quiz_pass_percentage' => '100')));
	pg_assert_same(array(), $cf(array('custom_form_quiz' => '1', 'custom_form_quiz_pass_percentage' => '')), 'an empty box keeps the default');
	pg_assert_same(array('custom_form_quiz_pass_percentage'), $cf(array('custom_form_quiz' => '1', 'custom_form_quiz_pass_percentage' => '150')), 'over 100 is refused, not cut to 127');
	pg_assert_same(array(), $cf(array('custom_form_quiz_pass_percentage' => '150')), 'read only while the quiz is on');
	pg_assert_same(array('custom_form_membership_days'), $cf(array('custom_form_membership' => '1', 'custom_form_membership_days' => '-5')), 'negative days are refused');
	pg_assert_same(array('custom_form_private_days', 'custom_form_offer_days'), $cf(array('custom_form_private' => '1', 'custom_form_private_days' => '1.5', 'custom_form_offer' => '1', 'custom_form_offer_days' => 'x')));
	pg_assert_same(array(), $cf(array('custom_form_membership' => '1', 'custom_form_membership_days' => '0')), 'zero days are allowed');

	pg_assert_same(array('photo_gallery_thumbnail_max_size'), array_keys(pg_page_form_number_errors('photo gallery', array('photo_gallery_thumbnail_max_size' => '0'))));
	pg_assert_same(array('photo_gallery_thumbnail_max_size'), array_keys(pg_page_form_number_errors('photo gallery', array('photo_gallery_thumbnail_max_size' => '-50'))));
	pg_assert_same(array(), pg_page_form_number_errors('photo gallery', array('photo_gallery_thumbnail_max_size' => '100')));
	pg_assert_same(array(), pg_page_form_number_errors('standard', array('photo_gallery_thumbnail_max_size' => '0')), 'other types are not read');
}

function test_page_form_number_errors_whole_boxes()
{
	$catalog = function ($fields) {
		return array_keys(pg_page_form_number_errors('catalog', $fields));
	};
	pg_assert_same(array(), $catalog(array('catalog_number_of_featured_items' => '0', 'catalog_number_of_new_items' => '99', 'catalog_number_of_columns' => '4', 'catalog_image_width' => '0', 'catalog_image_height' => '1200')));
	pg_assert_same(array(), $catalog(array('catalog_number_of_columns' => '')), 'an empty box keeps the default');
	pg_assert_same(array('catalog_number_of_featured_items', 'catalog_number_of_new_items'), $catalog(array('catalog_number_of_featured_items' => '-1', 'catalog_number_of_new_items' => '1,5')), 'a minus sign and a decimal are refused, not cut');
	pg_assert_same(array('catalog_number_of_columns'), $catalog(array('catalog_number_of_columns' => '0')), 'a catalog has one column or more');
	pg_assert_same(array('catalog_image_width', 'catalog_image_height'), $catalog(array('catalog_image_width' => '-50', 'catalog_image_height' => '12.50')));
	pg_assert_same(array('catalog_number_of_featured_items'), $catalog(array('catalog_number_of_featured_items' => '300')), 'over the box is refused, not cut to 255');

	pg_assert_same(array('form_view_directory_summary_days', 'form_view_directory_summary_maximum_number_of_results'), array_keys(pg_page_form_number_errors('form view directory', array('form_view_directory_summary_days' => '-1', 'form_view_directory_summary_maximum_number_of_results' => '2.5'))));
	pg_assert_same(array('calendar_view_number_of_upcoming_events'), array_keys(pg_page_form_number_errors('calendar view', array('calendar_view_number_of_upcoming_events' => '-3'))));
	pg_assert_same(array(), pg_page_form_number_errors('calendar view', array('calendar_view_number_of_upcoming_events' => '5')));
	pg_assert_same(array('photo_gallery_number_of_columns'), array_keys(pg_page_form_number_errors('photo gallery', array('photo_gallery_number_of_columns' => '0'))));
	pg_assert_same(array(), pg_page_form_number_errors('standard', array('catalog_number_of_columns' => '-1')), 'other types are not read');
}

function test_page_form_folder_verdict()
{
	// 1 root, 2 the Workspace, 3 below it, 4 the bin, 5 below the bin, 6 a folder
	$rows = array(
		1 => array('folder_parent' => 0),
		2 => array('folder_parent' => 1),
		3 => array('folder_parent' => 2),
		4 => array('folder_parent' => 1),
		5 => array('folder_parent' => 4),
		6 => array('folder_parent' => 1),
	);
	pg_assert_same('', pg_page_form_folder_verdict('6', $rows, 2, 4));
	pg_assert_same('', pg_page_form_folder_verdict('1', $rows, 2, 4), 'the root takes pages');
	pg_assert_same('missing', pg_page_form_folder_verdict('999999', $rows, 2, 4), 'a deleted folder');
	pg_assert_same('missing', pg_page_form_folder_verdict('', $rows, 2, 4), 'no folder');
	pg_assert_same('missing', pg_page_form_folder_verdict('0', $rows, 2, 4));
	pg_assert_same('missing', pg_page_form_folder_verdict('6x', $rows, 2, 4));
	pg_assert_same('missing', pg_page_form_folder_verdict(array('6'), $rows, 2, 4));
	pg_assert_same('missing', pg_page_form_folder_verdict('5', $rows, 2, 4), 'a binned folder');
	pg_assert_same('workspace', pg_page_form_folder_verdict('3', $rows, 2, 4));
	pg_assert_same('workspace', pg_page_form_folder_verdict('2', $rows, 2, 4));
	pg_assert_same('', pg_page_form_folder_verdict('3', $rows, 2, 4, 3), 'a page keeps its stored folder');
	pg_assert_same('missing', pg_page_form_folder_verdict('999998', $rows, 2, 4, 999998), 'a stored folder that is gone is not kept');
	pg_assert_same('', pg_page_form_folder_verdict('3', $rows, 0, 0), 'no Workspace, no bin');
}

function test_page_form_style_ok()
{
	$plain = array('style_type' => 'system', 'style_layout' => 'two_column');
	$custom = array('style_type' => 'custom', 'style_layout' => '');
	$mobile = array('style_type' => 'system', 'style_layout' => 'one_column_mobile');
	$design = array('style_type' => 'custom', 'style_layout' => 'visual_designer');

	pg_assert_true(pg_page_form_style_ok('0', 'desktop', null, 0, true), 'inherit');
	pg_assert_true(pg_page_form_style_ok('', 'desktop', null, 0, true), 'an empty value inherits');
	pg_assert_true(pg_page_form_style_ok('7', 'desktop', $plain, 0, true));
	pg_assert_true(!pg_page_form_style_ok('99999', 'desktop', null, 0, true), 'a deleted style');
	pg_assert_true(!pg_page_form_style_ok('7x', 'desktop', $plain, 0, true));
	pg_assert_true(!pg_page_form_style_ok('8', 'desktop', $design, 0, true), 'a visual-editor design is not offered');
	pg_assert_true(pg_page_form_style_ok('8', 'desktop', $design, 8, true), 'the stored design is kept');
	pg_assert_true(!pg_page_form_style_ok('9', 'desktop', $mobile, 0, true), 'a mobile layout is not a desktop style');
	pg_assert_true(pg_page_form_style_ok('9', 'mobile', $mobile, 0, true));
	pg_assert_true(pg_page_form_style_ok('10', 'mobile', $custom, 0, true));
	pg_assert_true(!pg_page_form_style_ok('7', 'mobile', $plain, 0, true), 'only custom and mobile styles while the mobile site is on');
	pg_assert_true(pg_page_form_style_ok('7', 'mobile', $plain, 0, false), 'every style while it is off');
	pg_assert_true(!pg_page_form_style_ok('99999', 'mobile', null, 0, false), 'a deleted mobile style');
}

function test_page_form_sitemap_type()
{
	pg_assert_true(pg_page_form_sitemap_type('standard'));
	pg_assert_true(pg_page_form_sitemap_type('catalog detail'));
	pg_assert_true(!pg_page_form_sitemap_type('login'));
	pg_assert_true(!pg_page_form_sitemap_type('logout'));
	pg_assert_true(!pg_page_form_sitemap_type('checkout'));
}
