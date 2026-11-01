<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designed system pages picked from the pages carrying a
 * widget (includes/fn/widgets.php, widgets_account.php, widgets_error.php):
 * the account links (password reset e-mail, Change Password, order links)
 * and the site's error page come only from pages the visitor can open, a
 * page of the published design first, so a design kept as a draft in a
 * private folder never becomes the page they send to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows as pg_sw_widget_pages() hands them: title order, so the drafts of the
// two unpublished designs (folder 305, private) come before the café pages.
function _system_page_pick_rows()
{
	return array(
		array('page_id' => '1643', 'page_name' => 'parola-belirle', 'page_folder' => '305', 'page_style' => '40'),
		array('page_id' => '1690', 'page_name' => 'parola-belirle-2', 'page_folder' => '305', 'page_style' => '41'),
		array('page_id' => '1700', 'page_name' => 'sifre-belirle-eski', 'page_folder' => '316', 'page_style' => '12'),
		array('page_id' => '1596', 'page_name' => 'sifre-belirle', 'page_folder' => '316', 'page_style' => '30'),
	);
}

function test_first_viewable_page_prefers_the_given_design()
{
	$open = function ($folder_id) { return (int) $folder_id !== 305; };
	$picked = pg_sw_first_viewable_page(_system_page_pick_rows(), $open, array(30));
	pg_assert_same('sifre-belirle', $picked['page_name'], 'the page of the published design comes first');

	$picked = pg_sw_first_viewable_page(_system_page_pick_rows(), $open, array(0, 30));
	pg_assert_same('sifre-belirle', $picked['page_name'], 'an unknown design (0) is passed over');

	$picked = pg_sw_first_viewable_page(_system_page_pick_rows(), $open, array(99));
	pg_assert_same('sifre-belirle-eski', $picked['page_name'], 'no page of that design: the first open page');

	$picked = pg_sw_first_viewable_page(_system_page_pick_rows(), $open, array(40));
	pg_assert_same('sifre-belirle-eski', $picked['page_name'], 'a draft of the preferred design is still skipped');

	$picked = pg_sw_first_viewable_page(_system_page_pick_rows(), $open);
	pg_assert_same('sifre-belirle-eski', $picked['page_name'], 'without a preference the order is kept');
}

function test_error_page_pick_takes_a_public_page_of_the_published_design()
{
	$rows = array(
		array('page_id' => '1658', 'page_folder' => '305', 'page_style' => '40'),
		array('page_id' => '1705', 'page_folder' => '305', 'page_style' => '41'),
		array('page_id' => '1612', 'page_folder' => '316', 'page_style' => '30'),
		array('page_id' => '1500', 'page_folder' => '316', 'page_style' => '12'),
	);
	$public = function ($folder_id) { return (int) $folder_id === 316; };
	pg_assert_same(1612, pg_sw_error_page_pick($rows, $public, 30), 'the published design');
	pg_assert_same(1500, pg_sw_error_page_pick($rows, $public, 99), 'else the lowest page id, not the title order');
	pg_assert_same(1500, pg_sw_error_page_pick($rows, $public, 0), 'no home page');
	pg_assert_same(0, pg_sw_error_page_pick(array_slice($rows, 0, 2), $public, 40), 'only drafts: none');
	pg_assert_same(0, pg_sw_error_page_pick(array(), $public, 30), 'no page at all');
}

// The account links and the error page take their page through the picker,
// which reads the folder of every candidate.
function test_system_page_pickers_check_access()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	$start = strpos($src, 'function pg_sw_page_type_widget_url(');
	pg_assert_true($start !== false, 'pg_sw_page_type_widget_url');
	$body = substr($src, (int) $start, (int) strpos($src, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains('pg_sw_first_viewable_page(', $body, 'account links use the picker');
	pg_assert_false(strpos($body, '$pages[0]') !== false, 'account links take no first page themselves');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_error.php');
	$start = strpos($src, 'function pg_sw_error_page_id(');
	$body = substr($src, (int) $start, (int) strpos($src, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains('pg_sw_error_page_pick(', $body, 'the error page uses the picker');
	pg_assert_contains("=== 'public'", $body, 'the error page must be open to anyone');
}
