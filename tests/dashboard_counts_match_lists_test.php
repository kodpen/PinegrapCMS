<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source scans for the dashboard figures that summarise a list: the Orders
 * card's headline and rows share one status filter and list each order once
 * (widget_8.php), every product count and row on the stock lane, the
 * E-Commerce Summary and the greeting leaves the Recycle Bin out like the
 * products list does (widget_9.php, widget_3.php, brief.php), and the
 * greeting's new-contacts count for a User reads only the groups the contacts
 * list shows them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_counts_match_lists_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The headline series and the list both count status != 'incomplete', and the
// list no longer joins ship_tos (one row per recipient, never read).
function test_dashboard_counts_orders_headline_matches_list()
{
	$source = dashboard_counts_match_lists_test_source('includes/dashboard/widgets/widget_8.php');

	pg_assert_contains("pg_activity_daily('orders', 'orders.order_date', \" AND (orders.status != 'incomplete')\")", $source);
	pg_assert_contains("WHERE orders.status != 'incomplete'", $source);
	pg_assert_false(strpos($source, "orders.status = 'complete'") !== false, 'headline counts complete only');
	pg_assert_false(stripos($source, 'JOIN ship_tos') !== false, 'orders list joins ship_tos');
}

// Every FROM products in the stock lane, the summary card and the greeting's
// stock lines carries the live filter.
function test_dashboard_counts_products_leave_the_bin_out()
{
	$expected = array(
		'includes/dashboard/widgets/widget_9.php' => 4,
		'includes/dashboard/widgets/widget_3.php' => 1,
		'includes/dashboard/brief.php' => 2,
	);

	foreach ($expected as $path => $count) {
		$source = dashboard_counts_match_lists_test_source($path);

		pg_assert_same($count, preg_match_all('/\bFROM products\b/', $source), $path . ' FROM products');
		pg_assert_same($count, substr_count($source, "pg_catalog_bin_live_sql('products')"), $path . ' live filter');
	}
}

// A User's new-contacts count reads only their editable groups, each contact
// once, and nothing when no group is granted.
function test_dashboard_counts_new_contacts_scoped_for_users()
{
	$source = dashboard_counts_match_lists_test_source('includes/dashboard/brief.php');

	pg_assert_contains("get_items_user_can_edit('contact_groups', \$user['id'])", $source);
	pg_assert_contains('COUNT(DISTINCT contacts.id)', $source);
	pg_assert_contains('contacts_contact_groups_xref.contact_group_id IN (', $source);
}
