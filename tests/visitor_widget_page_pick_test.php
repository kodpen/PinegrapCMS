<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designed page chosen automatically for an account screen
 * (pg_sw_page_type_widget_url() in includes/fn/widgets_account.php) and for
 * the site's error page (pg_sw_error_page_id() in includes/fn/widgets_error.php):
 * only a page the visitor can open is chosen, a page of the published
 * design first, so a design kept in a private Drafts folder never becomes a
 * password reset link or the site's error screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_pick_viewable_page_prefers_the_published_design()
{
	$pages = array(
		array('page_id' => '1643', 'page_name' => 'parola-belirle', 'page_folder' => '305', 'page_style' => '40'),
		array('page_id' => '1690', 'page_name' => 'parola-belirle-2', 'page_folder' => '305', 'page_style' => '41'),
		array('page_id' => '1700', 'page_name' => 'baska-tasarim', 'page_folder' => '400', 'page_style' => '42'),
		array('page_id' => '1596', 'page_name' => 'sifre-belirle', 'page_folder' => '316', 'page_style' => '39'),
	);
	$open = function ($folder_id) { return (int) $folder_id !== 305; };
	$picked = pg_sw_pick_viewable_page($pages, $open, 39);
	pg_assert_same('sifre-belirle', $picked['page_name'], 'a page of the published design comes first');

	$picked = pg_sw_pick_viewable_page($pages, $open, 77);
	pg_assert_same('baska-tasarim', $picked['page_name'], 'no page of that design: the first open page');

	$picked = pg_sw_pick_viewable_page($pages, $open, 40);
	pg_assert_same('baska-tasarim', $picked['page_name'], 'a draft of the preferred design is still skipped');

	$picked = pg_sw_pick_viewable_page($pages, $open);
	pg_assert_same('baska-tasarim', $picked['page_name'], 'without a design: the first open page');

	$closed = function ($folder_id) { return false; };
	pg_assert_same(null, pg_sw_pick_viewable_page($pages, $closed, 39), 'no open page: none');
	pg_assert_same(null, pg_sw_pick_viewable_page(array(), $open, 39), 'no page at all');
}

function test_signed_out_screens_need_a_public_page()
{
	foreach (array('set password', 'forgot password', 'login', 'registration entrance') as $type) {
		pg_assert_true(pg_sw_page_type_for_signed_out($type), $type);
	}
	foreach (array('change password', 'my account', 'view order', 'logout') as $type) {
		pg_assert_false(pg_sw_page_type_for_signed_out($type), $type);
	}
}

function _visitor_pick_fn_body($file, $name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name);
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return substr($src, (int) $start, ($end === false ? strlen($src) : (int) $end) - (int) $start);
}

function test_account_and_error_pages_use_the_picker()
{
	$body = _visitor_pick_fn_body('widgets_account.php', 'pg_sw_page_type_widget_url');
	pg_assert_contains('pg_sw_pick_viewable_page(', $body, 'account screens pick an open page');
	pg_assert_contains('pg_sw_home_style_id()', $body, 'account screens prefer the published design');
	pg_assert_false(strpos($body, '$pages[0]') !== false, 'account screens take no first page blindly');

	$body = _visitor_pick_fn_body('widgets_error.php', 'pg_sw_error_page_id');
	pg_assert_contains('pg_sw_pick_viewable_page(', $body, 'the error page is an open page');
	pg_assert_contains('pg_sw_home_style_id()', $body, 'the error page prefers the published design');
	pg_assert_contains("'public'", $body, 'the error page must be open to anyone');
	pg_assert_false(strpos($body, '$pages[0]') !== false, 'the error page takes no first page blindly');

	$body = _visitor_pick_fn_body('widgets.php', 'pg_sw_widget_page_index');
	pg_assert_contains('page.page_style', $body, 'the index carries each page\'s design');
}
