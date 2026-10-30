<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for list columns that show a label for a stored code and sort by
 * that label (pg_list_label_order() and pg_list_sort_items() in
 * includes/fn/list_paging.php): the FIELD() order, codes kept out of the
 * query, the panel language's collation, the link picker's sort in PHP and
 * the screens that use them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/waf_log_list.php');

// The codes come out in the order of their labels, not of the codes.
function test_list_label_order_orders_codes_by_label()
{
	$labels = array('checkbox' => 'Onay Kutusu', 'quantity' => 'Miktar', 'donation' => 'Bağış', 'autoselect' => 'Otomatik Seçim');

	pg_assert_same("FIELD(products.selection_type, 'donation', 'quantity', 'checkbox', 'autoselect')", pg_list_label_order('products.selection_type', $labels));
}

// Spaces, hyphens and an empty code (a NULL read through IFNULL) pass; a
// code that could break out of the quotes never reaches the query.
function test_list_label_order_drops_unsafe_codes()
{
	$labels = array('' => 'One-Time', 'Every Two Weeks' => 'Every Two Weeks', 'would-ban' => 'Would ban', "x', SLEEP(1), '" => 'Aaa', 'a\\b' => 'Aab', 'ç' => 'Aac');

	pg_assert_same("FIELD(x, 'Every Two Weeks', '', 'would-ban')", pg_list_label_order('x', $labels));
	pg_assert_same('x', pg_list_label_order('x', array("'" => 'A')), 'no safe code leaves the column as it is');
	pg_assert_same('x', pg_list_label_order('x', array()));
}

// With intl a letter with a diacritic sorts among its base letter; the
// Turkish statuses read Beklemede, Ödenebilir, Ödenmiş, Uygun Değil.
function test_list_label_order_uses_the_language_collation()
{
	$labels = array('pending' => 'Beklemede', 'payable' => 'Ödenebilir', 'ineligible' => 'Uygun Değil', 'paid' => 'Ödenmiş');
	$order = pg_list_label_order('commissions.status', $labels);

	if (class_exists('Collator')) {
		pg_assert_same("FIELD(commissions.status, 'pending', 'payable', 'paid', 'ineligible')", $order);
	} else {
		pg_assert_contains("'pending'", $order);
	}

	pg_assert_true(pg_geo_label_compare('arnavutluk', 'Brezilya') < 0, 'case does not matter');
	pg_assert_same(0, pg_geo_label_compare('Same', 'Same'));
}

// The sort maps of the label columns read the label order (English panel).
function test_list_label_order_in_the_sort_maps()
{
	if (lang(array('info' => '')) !== 'en') {
		return;
	}

	$_SESSION['software']['list_state']['view_commissions'] = array('sort' => 'status', 'order' => 'desc');
	pg_assert_same("FIELD(commissions.status, 'ineligible', 'paid', 'payable', 'pending') desc, commissions.id desc", pg_commission_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_commissions'] = array('sort' => 'frequency', 'order' => 'asc');
	pg_assert_contains("FIELD(IFNULL(recurring_commission_profiles.period, ''), 'Every Four Weeks', 'Every Two Weeks', 'Monthly', '', 'Quarterly',", pg_commission_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_recurring_commission_profiles'] = array('sort' => 'frequency', 'order' => 'asc');
	pg_assert_contains("FIELD(IFNULL(recurring_commission_profiles.period, ''), 'Every Four Weeks'", pg_commission_profile_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_waf_log'] = array('sort' => 'action', 'order' => 'asc');
	pg_assert_same("FIELD(action, 'ban', 'block', 'rate', 'log', 'would-ban', 'would-block', 'would-rate') asc, id asc", pg_waf_log_list_sort()['order_by']);

	unset($_SESSION['software']['list_state']['view_commissions'], $_SESSION['software']['list_state']['view_recurring_commission_profiles'], $_SESSION['software']['list_state']['view_waf_log']);

	pg_assert_contains("'calendar view', 'catalog', 'catalog detail', 'change password', 'custom form'", pg_list_label_order('page.page_type', get_page_type_names()));
}

// Every page type has its name in the map get_page_type_name() reads.
function test_page_type_names_map()
{
	$names = get_page_type_names();

	pg_assert_same(40, count($names));
	pg_assert_same(lang('Shopping Cart'), get_page_type_name('shopping cart'));
	pg_assert_same(null, get_page_type_name('no such type'));
}

// The link picker sorts in PHP the way the database lists sort: text
// without case and in language order, page types by name, numbers as
// numbers, ties by the first key.
function test_list_sort_items()
{
	$items = array(
		array('url' => 'zeta', 'page_type' => 'billing information', 'size' => '9'),
		array('url' => 'Çanta', 'page_type' => 'shopping cart', 'size' => '10'),
		array('url' => 'alpha', 'page_type' => 'catalog', 'size' => '100'),
		array('url' => 'Beta', 'page_type' => 'catalog', 'size' => '9'),
	);
	$keys = array('url' => 'url', 'page_type' => 'page_type', 'size' => 'size');
	$labels = array('page_type' => array('billing information' => 'Fatura Bilgileri', 'shopping cart' => 'Alışveriş Sepeti', 'catalog' => 'Katalog'));

	$urls = function ($sorted) {
		return array_column($sorted, 'url');
	};

	$by_url = $urls(pg_list_sort_items($items, array(array('sort' => 'url', 'order' => 'asc')), $keys));

	if (class_exists('Collator')) {
		pg_assert_same(array('alpha', 'Beta', 'Çanta', 'zeta'), $by_url, 'case and diacritics do not push a name after Z');
	} else {
		pg_assert_same(array('alpha', 'Beta'), array_slice($by_url, 0, 2), 'case does not matter');
	}

	pg_assert_same(array('Çanta', 'zeta', 'alpha', 'Beta'), $urls(pg_list_sort_items($items, array(array('sort' => 'page_type', 'order' => 'asc')), $keys, array(), $labels)), 'page type by its name, ties by URL');
	pg_assert_same(array('alpha', 'Beta', 'zeta', 'Çanta'), $urls(pg_list_sort_items($items, array(array('sort' => 'page_type', 'order' => 'desc')), $keys, array(), $labels)), 'descending reverses the level, not the tie');
	pg_assert_same(array('Beta', 'zeta', 'Çanta', 'alpha'), $urls(pg_list_sort_items($items, array(array('sort' => 'size', 'order' => 'asc')), $keys, array('size'))), 'sizes as numbers');
	pg_assert_same(array('alpha', 'Çanta', 'Beta', 'zeta'), $urls(pg_list_sort_items($items, array(array('sort' => 'size', 'order' => 'desc'), array('sort' => 'url', 'order' => 'asc')), $keys, array('size'))), 'a second level');
	pg_assert_same(array(), pg_list_sort_items(array(), array(array('sort' => 'url', 'order' => 'asc')), $keys));
}

// The screens sort their label columns through the helpers.
function test_list_label_sort_screens()
{
	$checks = array(
		'view_pages.php' => "pg_list_label_order('page.page_type', get_page_type_names())",
		'includes/fn/product_list.php' => "pg_list_label_order('products.selection_type', pg_product_list_selection_types())",
		'view_email_campaign_profiles.php' => "pg_list_label_order('email_campaign_profiles.action', \$action_names)",
		'view_short_links.php' => "pg_list_label_order('short_links.destination_type', \$destination_type_names)",
		'editor_select_page_or_file.php' => "pg_list_sort_items(",
	);

	foreach ($checks as $file => $needle) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($needle, $source, $file);
	}

	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/editor_select_page_or_file.php'), 'array_multisort') === false, 'no byte-order sort left in the link picker');
}
