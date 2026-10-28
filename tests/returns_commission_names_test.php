<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the returns, recurring commission profile and marketplace
 * products screens: the order's payment method is shown translated, a return
 * request's item names are visible text, the Edit Recurring Commission
 * Profile screen cancels back to the profile list and names its landmark,
 * and the marketplace products filter rows are navigation, not tab lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_commission_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_returns_payment_method_translated()
{
	foreach (array('add_return.php', 'view_return.php') as $path) {
		$source = returns_commission_names_source($path);
		pg_assert_contains("h(pg_payment_method_label(\$order['payment_method']))", $source, $path);
		pg_assert_false(strpos($source, "h((string) \$order['payment_method'])") !== false, $path . ': raw payment method');
	}
}

function test_returns_list_item_names_visible()
{
	$source = returns_commission_names_source('view_returns.php');
	pg_assert_false(strpos($source, "title=\"' . h(implode(', ', \$names))") !== false, 'item names only in a title');
	pg_assert_contains("<div class=\"small fg-secondary\">' . h(implode(', ', \$names)) . '</div>", $source);
}

function test_recurring_commission_profile_edit_names()
{
	$source = returns_commission_names_source('edit_recurring_commission_profile.php');
	pg_assert_contains("'cancel'=>array('enable'=>'true','url'=>'view_recurring_commission_profiles.php')", $source);
	pg_assert_false(strpos($source, 'aria-label="data edit buttons') !== false, 'literal landmark name');
	pg_assert_contains("aria-label=\"' . h(lang('data edit buttons')) . '\"", $source);
	pg_assert_contains('<span class="material-icons me-3" aria-hidden="true">save</span>', $source);
}

function test_recurring_commission_profile_enabled_mark_named()
{
	$profile = array(
		'id' => '3', 'affiliate_code' => 'A1', 'enabled' => '1', 'amount' => '500', 'start_date' => '2026-01-02',
		'period' => 'Weekly', 'number_of_commissions' => '0', 'product_name' => 'Box', 'product_short_description' => '',
		'created_timestamp' => (string) time(), 'last_modified_timestamp' => (string) time(), 'affiliate_name' => 'B',
		'order_number' => '9', 'created_username' => '', 'last_modified_username' => 'ali',
	);
	$html = pg_list_row_html(pg_commission_profile_list_row($profile));

	pg_assert_contains('<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>', $html);
}

function test_marketplace_products_filter_rows_are_navigation()
{
	$source = returns_commission_names_source('marketplace_products.php');
	pg_assert_false(strpos($source, 'role="tablist"') !== false, 'tab list without tabs');
	pg_assert_same(3, substr_count($source, "<nav class=\"mpp-tabs\" aria-label=\"' . h(lang('Filters')) . '\">"), 'three filter rows');
}
