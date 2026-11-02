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
// pending to payable, ineligible or paid; POST /commissions/{id} makes the
// same move through the screen's own write (pg_commission_status_save()).
// Paying an affiliate happens outside the site. The recurring profiles are
// read here too, and a contact is approved as an affiliate the way the
// approval link's screen does it (pg_affiliate_approve()).

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');

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

// The status write of edit_commission.php: the same function, so the cancel
// marker is cleared and commission.status_changed announced as on the screen.
function api_commissions_update($params) {

	api_commissions_catch_up();

	$row = api_row(api_commission_select() . " WHERE commissions.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Commission'));

	}

	// The router checked the enum; the screen's own check keeps the write
	// honest should the two lists ever drift apart.
	$status = pg_commission_posted_status($params['status']);

	if ($status === '') {

		api_fail_validation(lang('Please select a status.'), 'status');

	}

	api_dry_run_stop('updated', 'commission', array('id' => (int)$row['id'], 'status' => $status, 'previous_status' => (string)$row['status']));

	$app = api_current_app();

	if (pg_commission_status_save((int)$row['id'], $status, (int)$app['owner']['id']) === false) {

		api_fail_server();

	}

	log_activity(lang(array('string' => 'Commission ({var:1}) was set to {var:2} through the API by the application {var:3} (key {var:4}).', 'vars' => array($row['reference_code'], $status, $app['name'], $app['api_key']))), $app['owner']['username']);

	api_commissions_get(array('id' => (int)$row['id']));

}

/* ----- Recurring commission profiles ---------------------------------- */

// A profile is written at checkout for a recurring product bought with an
// affiliate's code; the daily check (update_recurring_commissions()) writes
// one commission per period from it while it is enabled. The screen only
// switches it on and off.
function api_commission_recurring_profile_select() {

	return "SELECT recurring_commission_profiles.*, orders.order_number,
			created_user.user_username AS created_username,
			last_modified_user.user_username AS last_modified_username
		FROM recurring_commission_profiles
		LEFT JOIN orders ON orders.id = recurring_commission_profiles.order_id
		LEFT JOIN user AS created_user ON created_user.user_id = recurring_commission_profiles.created_user_id
		LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = recurring_commission_profiles.last_modified_user_id";

}

function api_commission_recurring_profile_present($row, $affiliates = array()) {

	$key = api_commission_code_key($row['affiliate_code']);

	$affiliate = isset($affiliates[$key]) ? $affiliates[$key] : null;

	$limit = (int)$row['number_of_commissions'];

	$product = (string)$row['product_name'];

	$product_description = (string)$row['product_short_description'];

	$order_number = isset($row['order_number']) ? (string)$row['order_number'] : '';

	return array(
		'id'                   => (int)$row['id'],
		'affiliate_code'       => (string)$row['affiliate_code'],
		'affiliate_name'       => (($affiliate !== null) && ((string)$affiliate['affiliate_name'] !== '')) ? (string)$affiliate['affiliate_name'] : null,
		'affiliate_contact_id' => ($affiliate !== null) ? (int)$affiliate['id'] : null,
		'enabled'              => ((int)$row['enabled'] === 1),
		'amount'               => api_money($row['amount']),
		'start_date'           => (string)$row['start_date'],
		// monthly or yearly.
		'period'               => (string)$row['period'],
		// How many commissions the profile writes in all; null is without
		// limit (0 on the screen, shown as Unlimited).
		'commission_limit'     => ($limit > 0) ? $limit : null,
		'order_id'             => ((int)$row['order_id'] > 0) ? (int)$row['order_id'] : null,
		'order_number'         => ($order_number !== '') ? $order_number : null,
		'order_item_id'        => ((int)$row['order_item_id'] > 0) ? (int)$row['order_item_id'] : null,
		'product_name'         => ($product !== '') ? $product : null,
		'product_description'  => ($product_description !== '') ? $product_description : null,
		// Switched off by the order's cancellation (a restore switches it
		// back on); false on a site whose database update has not run.
		'disabled_by_cancel'   => !empty($row['disabled_by_cancel']),
		'created_by'           => ((string)$row['created_username'] !== '') ? (string)$row['created_username'] : null,
		'created_at'           => api_time($row['created_timestamp']),
		'updated_by'           => ((string)$row['last_modified_username'] !== '') ? (string)$row['last_modified_username'] : null,
		'updated_at'           => api_time($row['last_modified_timestamp']),
		'updated_at_unix'      => (int)$row['last_modified_timestamp']
	);

}

function api_commission_recurring_profile_schema() {

	return array(
		'id'                   => 'integer',
		'affiliate_code'       => 'string',
		'affiliate_name'       => 'string?',
		'affiliate_contact_id' => 'integer?',
		'enabled'              => 'boolean',
		'amount'               => 'integer',
		'start_date'           => 'string',
		'period'               => 'string',
		'commission_limit'     => 'integer?',
		'order_id'             => 'integer?',
		'order_number'         => 'string?',
		'order_item_id'        => 'integer?',
		'product_name'         => 'string?',
		'product_description'  => 'string?',
		'disabled_by_cancel'   => 'boolean',
		'created_by'           => 'string?',
		'created_at'           => 'string?',
		'updated_by'           => 'string?',
		'updated_at'           => 'string?',
		'updated_at_unix'      => 'integer'
	);

}

// The filters of the list as conditions, apart from the cursor.
function api_commission_recurring_profiles_where($params) {

	$where = array();

	if (isset($params['affiliate_code']) && $params['affiliate_code'] !== '') {

		$where[] = "recurring_commission_profiles.affiliate_code = '" . escape($params['affiliate_code']) . "'";

	}

	if (isset($params['enabled'])) {

		$where[] = "recurring_commission_profiles.enabled = '" . ($params['enabled'] ? 1 : 0) . "'";

	}

	if (isset($params['order_id'])) {

		$where[] = "recurring_commission_profiles.order_id = '" . (int)$params['order_id'] . "'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "recurring_commission_profiles.last_modified_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	return $where;

}

// Ordered as the commissions are: by when each was last changed, then by id.
function api_commission_recurring_profiles_list($params) {

	$where = api_commission_recurring_profiles_where($params);

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "(recurring_commission_profiles.last_modified_timestamp > '" . (int)$cursor['v'] . "'
			OR (recurring_commission_profiles.last_modified_timestamp = '" . (int)$cursor['v'] . "' AND recurring_commission_profiles.id > '" . (int)$cursor['i'] . "'))";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_commission_recurring_profile_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY recurring_commission_profiles.last_modified_timestamp ASC, recurring_commission_profiles.id ASC
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

		$out[] = api_commission_recurring_profile_present($row, $affiliates);

	}

	api_ok_list($out, $limit, $next);

}

function api_commission_recurring_profiles_get($params) {

	$row = api_row(api_commission_recurring_profile_select() . " WHERE recurring_commission_profiles.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Recurring commission profile'));

	}

	api_ok(api_commission_recurring_profile_present($row, api_commission_affiliates(array($row['affiliate_code']))));

}

/* ----- Affiliates ----------------------------------------------------- */

// The affiliate side of a contact record. The contact's commission rate and
// code are written by a person on the contact screen (POST /customers/{id}
// leaves them alone); the approval is the one move an outside system makes,
// through the same function as the approval link's screen.
function api_affiliate_columns() {

	return 'contacts.id, contacts.email_address, contacts.affiliate_name, contacts.affiliate_code, contacts.affiliate_approved, contacts.affiliate_commission_rate';

}

function api_affiliate_present($row) {

	$name = (string)$row['affiliate_name'];

	$code = (string)$row['affiliate_code'];

	return array(
		// The contact's id, as GET /customers/{id} has it.
		'id'              => (int)$row['id'],
		'email'           => (string)$row['email_address'],
		'affiliate_name'  => ($name !== '') ? $name : null,
		// Made at the approval when the affiliate chose none.
		'affiliate_code'  => ($code !== '') ? $code : null,
		'approved'        => ((int)$row['affiliate_approved'] === 1),
		// Per cent, two decimals, as the contact screen shows it.
		'commission_rate' => round((float)$row['affiliate_commission_rate'], 2)
	);

}

function api_affiliate_schema() {

	return array(
		'id'              => 'integer',
		'email'           => 'string',
		'affiliate_name'  => 'string?',
		'affiliate_code'  => 'string?',
		'approved'        => 'boolean',
		'commission_rate' => 'number'
	);

}

// The customers row rule applies (api_customer_reachable_or_fail()), as the
// contact screen's own check does; approve_affiliate.php asks for the
// contacts right, which customers:write stands for.
function api_affiliates_approve($params) {

	$id = (int)$params['id'];

	$row = api_customer_reachable_or_fail($id, api_affiliate_columns());

	if (!defined('AFFILIATE_PROGRAM') || AFFILIATE_PROGRAM != true) {

		api_fail(409, 'affiliate_program_off', lang('The affiliate program is switched off.'));

	}

	if ((int)$row['affiliate_approved'] === 1) {

		api_fail(409, 'affiliate_already_approved', lang('The affiliate is already approved.'));

	}

	if ((string)$row['email_address'] === '') {

		api_fail_validation(lang('The contact has no e-mail address for the welcome e-mail.'));

	}

	api_dry_run_stop('approved', 'affiliate', array('id' => $id, 'affiliate_code' => ((string)$row['affiliate_code'] !== '') ? (string)$row['affiliate_code'] : null));

	$app = api_current_app();

	$done = pg_affiliate_approve($id, (int)$app['owner']['id']);

	if ($done === 'already_approved') {

		api_fail(409, 'affiliate_already_approved', lang('The affiliate is already approved.'));

	}

	if (!$done) {

		api_fail_server();

	}

	log_activity(lang(array('string' => 'Affiliate ({var:1}) was approved through the API by the application {var:2} (key {var:3}).', 'vars' => array($done['affiliate_code'], $app['name'], $app['api_key']))), $app['owner']['username']);

	api_ok(api_affiliate_present(api_row("SELECT " . api_affiliate_columns() . " FROM contacts WHERE contacts.id = '" . $id . "' LIMIT 1")));

}
