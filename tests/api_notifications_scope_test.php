<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's bell and the caller's scopes
 * (api_notifications_rights(), includes/api/resources/account.php, and
 * pg_notification_visible(), includes/notifications.php): an order notice
 * reaches a caller only when its effective scopes hold orders:read, while the
 * panel and the push sender, which pass no such key, answer as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

// The order kinds are asked only while the store is on.
if (!defined('ECOMMERCE')) {
	define('ECOMMERCE', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

// A person with the e-commerce right, below manager.
function api_notifications_scope_test_owner()
{
	return array(
		'id'               => 7,
		'role'             => 3,
		'manage_ecommerce' => true,
		'manage_forms'     => false,
		'manage_erp'       => false,
		'manage_erp_cash'  => false
	);
}

// One unread row of each order kind, plus a kind the scopes do not touch.
function api_notifications_scope_test_rows()
{
	return array(
		array('id' => 1, 'action' => 'new_order', 'target_user_id' => 0),
		array('id' => 2, 'action' => 'order_return', 'target_user_id' => 0),
		array('id' => 3, 'action' => 'out_stock', 'target_user_id' => 0),
		array('id' => 4, 'action' => 'workspace', 'target_user_id' => 99)
	);
}

function api_notifications_scope_test_visible_ids($rights)
{
	$ids = array();

	foreach (api_notifications_scope_test_rows() as $row) {

		if (pg_notification_visible($row, $rights)) {
			$ids[] = $row['id'];
		}

	}

	return $ids;
}

// The rights carry whether the caller's scopes hold orders:read.
function test_api_notifications_scope_rights_carry_orders_read()
{
	$owner = api_notifications_scope_test_owner();

	$with = api_notifications_rights($owner, array('account:read', 'orders:read'));
	$without = api_notifications_rights($owner, array('account:read', 'products:read'));

	pg_assert_true($with['orders_read'], 'with orders:read');
	pg_assert_false($without['orders_read'], 'without orders:read');
	pg_assert_true($without['manage_ecommerce'], 'the person keeps the right');
}

// The list: without orders:read the order notices are left out.
function test_api_notifications_scope_list_follows_orders_read()
{
	$owner = api_notifications_scope_test_owner();

	pg_assert_same(array(1, 2, 3), api_notifications_scope_test_visible_ids(
		api_notifications_rights($owner, array('account:read', 'orders:read'))
	), 'with orders:read');

	pg_assert_same(array(3), api_notifications_scope_test_visible_ids(
		api_notifications_rights($owner, array('account:read'))
	), 'without orders:read');
}

// The unread count: the same rows, counted.
function test_api_notifications_scope_count_follows_orders_read()
{
	$owner = api_notifications_scope_test_owner();
	$rows = api_notifications_scope_test_rows();

	pg_assert_same(3, api_notifications_visible_count($rows,
		api_notifications_rights($owner, array('account:read', 'orders:read'))), 'with orders:read');

	pg_assert_same(1, api_notifications_visible_count($rows,
		api_notifications_rights($owner, array('account:read'))), 'without orders:read');
}

// The panel and the push sender pass no orders_read key: unchanged.
function test_api_notifications_scope_panel_rights_unchanged()
{
	$rights = api_notifications_scope_test_owner();

	pg_assert_same(array(1, 2, 3), api_notifications_scope_test_visible_ids($rights), 'no key');

	$rights['manage_ecommerce'] = false;

	pg_assert_same(array(), api_notifications_scope_test_visible_ids($rights), 'no e-commerce right');
}
