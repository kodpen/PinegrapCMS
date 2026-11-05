<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Subscriptions across every customer: the recurring payments the store
// charges from stored cards (view_subscriptions.php), for a billing or
// support system that wants them all rather than one customer's.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The rows an owner with the user role (3) reaches: the subscriptions of the
// customers in its contact groups, the rule of the customers routes
// (api_customer_owner_filter()); an account that is no contact is outside.
// Roles 0-2 reach every row.
function api_subscription_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return pg_contact_owner_sql($owner['id'], 'u.user_contact');

}

// The filters of the list as conditions, apart from the cursor, with the row
// rule of the owner last.
function api_subscriptions_where($params, $owner) {

	$where = array();

	// The router checked the enum; the list of stored statuses is the one the
	// screen filters by, so no escaping is needed.
	if (isset($params['status']) && in_array((string)$params['status'], pg_subscription_statuses(), true)) {

		$where[] = "s.status = '" . (string)$params['status'] . "'";

	}

	if (isset($params['customer_id'])) {

		$where[] = "u.user_contact = '" . (int)$params['customer_id'] . "'";

	}

	if (isset($params['product_id'])) {

		$where[] = "s.product_id = '" . (int)$params['product_id'] . "'";

	}

	if (isset($params['order_id'])) {

		$where[] = "s.order_id = '" . (int)$params['order_id'] . "'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "s.updated_at >= '" . (int)$params['updated_since'] . "'";

	}

	$rule = api_subscription_owner_filter($owner);

	if ($rule !== '') {

		$where[] = $rule;

	}

	return $where;

}

// By when each was last changed, then by id, so updated_since walks forward
// without repeating a row, and a subscription whose status or charge moves
// comes round again at the end.
function api_subscriptions_list($params) {

	$where = api_subscriptions_where($params, api_current_app()['owner']);

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "(s.updated_at > '" . (int)$cursor['v'] . "'
			OR (s.updated_at = '" . (int)$cursor['v'] . "' AND s.id > '" . (int)$cursor['i'] . "'))";

	}

	$limit = (int)$params['limit'];

	$rows = pg_subscriptions_ready()
		? api_rows(api_customer_subscriptions_select_sql()
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
			ORDER BY s.updated_at ASC, s.id ASC
			LIMIT " . ($limit + 1))
		: array();

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['updated_at'], (int)$last['id']);

	}

	$out = array();

	$order_data = api_subscription_order_data_shown(api_current_scopes());

	foreach ($rows as $row) {

		$out[] = api_subscription_present($row, $order_data);

	}

	api_ok_list($out, $limit, $next);

}

function api_subscriptions_get($params) {

	$where = array("s.id = '" . (int)$params['id'] . "'");

	$rule = api_subscription_owner_filter(api_current_app()['owner']);

	if ($rule !== '') {

		$where[] = $rule;

	}

	$row = pg_subscriptions_ready()
		? api_row(api_customer_subscriptions_select_sql() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1')
		: null;

	if ($row === null) {

		api_fail_not_found(lang('Subscription'));

	}

	api_ok(api_subscription_present($row, api_subscription_order_data_shown(api_current_scopes())));

}
