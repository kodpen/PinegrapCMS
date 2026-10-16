<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Commissions: what the affiliate program owes the affiliates who brought an
// order in.
//
// One is written when an order placed with an affiliate's code carries
// commissionable products, and one per period for a recurring product, from
// its recurring commission profile. The commissions screen then moves it from
// pending to payable, ineligible or paid. Read only here: the status is
// changed on that screen, and paying an affiliate happens outside the site.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_commission_statuses() {

	return array('pending', 'payable', 'ineligible', 'paid');

}

function api_commission_select() {

	return "SELECT commissions.*, orders.order_number,
			recurring_commission_profiles.period,
			recurring_commission_profiles.product_name,
			recurring_commission_profiles.product_short_description,
			created_user.user_username AS created_username,
			last_modified_user.user_username AS last_modified_username
		FROM commissions
		LEFT JOIN orders ON orders.id = commissions.order_id
		LEFT JOIN recurring_commission_profiles ON recurring_commission_profiles.id = commissions.recurring_commission_profile_id
		LEFT JOIN user AS created_user ON created_user.user_id = commissions.created_user_id
		LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = commissions.last_modified_user_id";

}

// The recurring commissions that have fallen due are written when someone
// opens the commissions screen or their own account page, at most once a day.
// Asked here too, so the API never reports fewer commissions than the screen
// would show at the same moment.
function api_commissions_catch_up() {

	if (function_exists('update_recurring_commissions') && defined('LAST_RECURRING_COMMISSION_CHECK_TIMESTAMP')) {

		update_recurring_commissions();

	}

}

// The affiliate behind each code, for many codes in one query. The code is
// looked up the way the commissions screen joins it; should two contacts carry
// the same code, the older one is the affiliate.
function api_commission_affiliates($codes) {

	$keys = array();

	foreach ($codes as $code) {

		$code = (string)$code;

		if ($code !== '') {

			$keys[api_commission_code_key($code)] = "'" . escape($code) . "'";

		}

	}

	$affiliates = array();

	if (empty($keys)) {

		return $affiliates;

	}

	foreach (api_rows("SELECT id, affiliate_code, affiliate_name FROM contacts
		WHERE affiliate_code IN (" . implode(',', $keys) . ")
		ORDER BY id ASC") as $row) {

		$key = api_commission_code_key($row['affiliate_code']);

		if (!isset($affiliates[$key])) {

			$affiliates[$key] = $row;

		}

	}

	return $affiliates;

}

// The column compares without regard to case, so the lookup does too.
function api_commission_code_key($code) {

	return function_exists('mb_strtolower') ? mb_strtolower((string)$code, 'UTF-8') : strtolower((string)$code);

}

function api_commission_present($row, $affiliates = array()) {

	$key = api_commission_code_key($row['affiliate_code']);

	$affiliate = isset($affiliates[$key]) ? $affiliates[$key] : null;

	$profile_id = (int)$row['recurring_commission_profile_id'];

	$period = isset($row['period']) ? (string)$row['period'] : '';

	$product = isset($row['product_name']) ? (string)$row['product_name'] : '';

	$product_description = isset($row['product_short_description']) ? (string)$row['product_short_description'] : '';

	$order_number = isset($row['order_number']) ? (string)$row['order_number'] : '';

	return array(
		'id'                   => (int)$row['id'],
		// The code the screens and the affiliate's account page show.
		'reference_code'       => (string)$row['reference_code'],
		// pending, payable, ineligible or paid.
		'status'               => (string)$row['status'],
		'amount'               => api_money($row['amount']),
		'affiliate_code'       => (string)$row['affiliate_code'],
		'affiliate_name'       => (($affiliate !== null) && ((string)$affiliate['affiliate_name'] !== '')) ? (string)$affiliate['affiliate_name'] : null,
		// The affiliate's contact, for GET /customers/{id}.
		'affiliate_contact_id' => ($affiliate !== null) ? (int)$affiliate['id'] : null,
		'order_id'             => ((int)$row['order_id'] > 0) ? (int)$row['order_id'] : null,
		'order_number'         => ($order_number !== '') ? $order_number : null,
		// Written from a recurring commission profile, once a period, rather
		// than for the order itself.
		'recurring'            => ($profile_id > 0),
		'recurring_profile_id' => ($profile_id > 0) ? $profile_id : null,
		// monthly or yearly; null for a commission on an order.
		'period'               => ($period !== '') ? $period : null,
		'product_name'         => ($product !== '') ? $product : null,
		'product_description'  => ($product_description !== '') ? $product_description : null,
		'created_by'           => ((string)$row['created_username'] !== '') ? (string)$row['created_username'] : null,
		'created_at'           => api_time($row['created_timestamp']),
		'updated_by'           => ((string)$row['last_modified_username'] !== '') ? (string)$row['last_modified_username'] : null,
		'updated_at'           => api_time($row['last_modified_timestamp']),
		'updated_at_unix'      => (int)$row['last_modified_timestamp']
	);

}

function api_commission_schema() {

	return array(
		'id'                   => 'integer',
		'reference_code'       => 'string',
		'status'               => 'string',
		'amount'               => 'integer',
		'affiliate_code'       => 'string',
		'affiliate_name'       => 'string?',
		'affiliate_contact_id' => 'integer?',
		'order_id'             => 'integer?',
		'order_number'         => 'string?',
		'recurring'            => 'boolean',
		'recurring_profile_id' => 'integer?',
		'period'               => 'string?',
		'product_name'         => 'string?',
		'product_description'  => 'string?',
		'created_by'           => 'string?',
		'created_at'           => 'string?',
		'updated_by'           => 'string?',
		'updated_at'           => 'string?',
		'updated_at_unix'      => 'integer'
	);

}

// The filters of the list as conditions, apart from the cursor.
function api_commissions_where($params) {

	$where = array();

	if (isset($params['status'])) {

		$where[] = "commissions.status = '" . escape($params['status']) . "'";

	}

	if (isset($params['affiliate_code']) && $params['affiliate_code'] !== '') {

		$where[] = "commissions.affiliate_code = '" . escape($params['affiliate_code']) . "'";

	}

	if (isset($params['reference_code']) && $params['reference_code'] !== '') {

		$where[] = "commissions.reference_code = '" . escape($params['reference_code']) . "'";

	}

	if (isset($params['order_id'])) {

		$where[] = "commissions.order_id = '" . (int)$params['order_id'] . "'";

	}

	if (isset($params['created_since'])) {

		$where[] = "commissions.created_timestamp >= '" . (int)$params['created_since'] . "'";

	}

	if (isset($params['created_before'])) {

		$where[] = "commissions.created_timestamp < '" . (int)$params['created_before'] . "'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "commissions.last_modified_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	return $where;

}

// By when each was last changed, then by id, so updated_since walks forward
// without repeating a row, and a commission whose status moves comes round
// again at the end.
function api_commissions_list($params) {

	api_commissions_catch_up();

	$where = api_commissions_where($params);

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "(commissions.last_modified_timestamp > '" . (int)$cursor['v'] . "'
			OR (commissions.last_modified_timestamp = '" . (int)$cursor['v'] . "' AND commissions.id > '" . (int)$cursor['i'] . "'))";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_commission_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY commissions.last_modified_timestamp ASC, commissions.id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['last_modified_timestamp'], (int)$last['id']);

	}

	$codes = array();

	foreach ($rows as $row) {

		$codes[] = (string)$row['affiliate_code'];

	}

	$affiliates = api_commission_affiliates($codes);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_commission_present($row, $affiliates);

	}

	api_ok_list($out, $limit, $next);

}

function api_commissions_get($params) {

	api_commissions_catch_up();

	$row = api_row(api_commission_select() . " WHERE commissions.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Commission'));

	}

	api_ok(api_commission_present($row, api_commission_affiliates(array($row['affiliate_code']))));

}
