<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Key codes: codes a shopper types instead of an offer's own code - one per
// customer, per card, per mailing - so that the orders an offer brought in can
// be told apart.
//
// A key code stands for an offer code (offer_code). At checkout the typed code
// is looked up among the offers first and among the key codes only when no
// offer has it, so a key code that equals an offer code is never read; such a
// code is refused here. A single-use code is switched off by the order that
// uses it. report says what the order records: the key code itself, or the
// offer code it stands for.
//
// Read, made one or many at a time, changed and removed, as on the key code
// screens. Like a gift card's, the code is worth something at checkout, so it
// travels only under its own scope.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_key_code_date($value) {

	$value = (string)$value;

	return (($value === '') || (strpos($value, '0000-00-00') === 0)) ? null : substr($value, 0, 10);

}

// What the code does at checkout today. disabled: switched off, which is also
// what a single-use code becomes once an order has used it; expired: past its
// expiry date; offer_inactive: no offer with its offer code is running today;
// otherwise active.
function api_key_code_status($row, $today = null) {

	$today = ($today === null) ? date('Y-m-d') : $today;

	if ((int)$row['enabled'] !== 1) {

		return 'disabled';

	}

	$expires = api_key_code_date($row['expiration_date']);

	if (($expires !== null) && ($expires < $today)) {

		return 'expired';

	}

	return ((int)$row['offer_running'] > 0) ? 'active' : 'offer_inactive';

}

// A day as Y-m-d and nothing else, for the queries that compare against it.
function api_key_code_day($today) {

	return preg_replace('/[^0-9-]/', '', (string)$today);

}

// The same four states as a WHERE clause on key_codes, in the same order of
// precedence as api_key_code_status().
function api_key_code_status_clause($status, $today) {

	$today = api_key_code_day($today);

	$expired = "(key_codes.expiration_date <> '0000-00-00' AND key_codes.expiration_date < '" . $today . "')";

	$running = "EXISTS (SELECT 1 FROM offers WHERE offers.code = key_codes.offer_code AND offers.status = 'enabled'
		AND offers.start_date <= '" . $today . "' AND offers.end_date >= '" . $today . "')";

	switch ($status) {

		case 'disabled':
			return "key_codes.enabled <> '1'";

		case 'expired':
			return "key_codes.enabled = '1' AND " . $expired;

		case 'offer_inactive':
			return "key_codes.enabled = '1' AND NOT " . $expired . " AND NOT " . $running;

		case 'active':
			return "key_codes.enabled = '1' AND NOT " . $expired . " AND " . $running;

	}

	return '';

}

// offer_running counts the offers with the code's offer code that run today;
// several offers may share one code.
function api_key_code_select($today = null) {

	$today = api_key_code_day(($today === null) ? date('Y-m-d') : $today);

	return "SELECT key_codes.*, last_modified_user.user_username AS last_modified_username,
			(SELECT COUNT(*) FROM offers WHERE offers.code = key_codes.offer_code AND offers.status = 'enabled'
				AND offers.start_date <= '" . $today . "' AND offers.end_date >= '" . $today . "') AS offer_running
		FROM key_codes
		LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = key_codes.user";

}

function api_key_code_present($row) {

	return array(
		'id'              => (int)$row['id'],
		'code'            => (string)$row['code'],
		// The offer it stands for; GET /offers?code= finds it.
		'offer_code'      => (string)$row['offer_code'],
		'status'          => api_key_code_status($row),
		'enabled'         => ((int)$row['enabled'] === 1),
		'single_use'      => ((int)$row['single_use'] === 1),
		// key_code: the order records this code; offer_code: the order records
		// the offer code, as if the shopper had typed that.
		'report'          => ((string)$row['report'] === 'offer_code') ? 'offer_code' : 'key_code',
		'expires_on'      => api_key_code_date($row['expiration_date']),
		'notes'           => (string)$row['notes'],
		'updated_by'      => ((string)$row['last_modified_username'] !== '') ? (string)$row['last_modified_username'] : null,
		'updated_at'      => api_time($row['timestamp']),
		'updated_at_unix' => (int)$row['timestamp']
	);

}

function api_key_code_schema() {

	return array(
		'id'              => 'integer',
		'code'            => 'string',
		'offer_code'      => 'string',
		'status'          => 'string',
		'enabled'         => 'boolean',
		'single_use'      => 'boolean',
		'report'          => 'string',
		'expires_on'      => 'string?',
		'notes'           => 'string',
		'updated_by'      => 'string?',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

function api_key_code_load_or_fail($id) {

	$row = api_row(api_key_code_select() . " WHERE key_codes.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Key Code'));

	}

	return $row;

}

// Newest first.
function api_key_codes_list($params) {

	$where = array();

	$today = date('Y-m-d');

	if (isset($params['offer_code']) && $params['offer_code'] !== '') {

		$where[] = "key_codes.offer_code = '" . escape($params['offer_code']) . "'";

	}

	if (isset($params['status'])) {

		$where[] = api_key_code_status_clause($params['status'], $today);

	}

	if (isset($params['single_use'])) {

		$where[] = "key_codes.single_use = '" . ($params['single_use'] ? '1' : '0') . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$like = escape(escape_like($params['q']));

		$where[] = "(key_codes.code LIKE '%" . $like . "%' OR key_codes.notes LIKE '%" . $like . "%')";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "key_codes.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_key_code_select($today)
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY key_codes.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_key_code_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_key_codes_get($params) {

	api_ok(api_key_code_present(api_key_code_load_or_fail($params['id'])));

}

// Ten characters from digits and capitals, as the key code screen makes them,
// so a code read off a card cannot be mistyped as another one.
function api_key_code_random($length = 10) {

	$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	$code = '';

	for ($i = 0; $i < $length; $i++) {

		$code .= $characters[random_int(0, 35)];

	}

	return $code;

}

// A code no key code and no offer has yet, or '' when fifty tries found none.
function api_key_code_generate() {

	for ($attempt = 0; $attempt < 50; $attempt++) {

		$code = api_key_code_random();

		if (!api_value("SELECT id FROM key_codes WHERE code = '" . escape($code) . "' LIMIT 1")
			&& !api_value("SELECT id FROM offers WHERE code = '" . escape($code) . "' LIMIT 1")) {

			return $code;

		}

	}

	return '';

}

// A code typed by the caller: not another key code's, and not an offer's own
// code, which checkout would apply as the offer without ever reading the key
// code. Codes compare as the database compares them, so case does not make a
// second code.
function api_key_code_own_or_fail($code, $except_id = 0) {

	if ($code === '') {

		api_fail_validation(lang('Key Code is required.'), 'code');

	}

	if (api_value("SELECT id FROM key_codes WHERE code = '" . escape($code) . "' AND id <> '" . (int)$except_id . "' LIMIT 1")) {

		api_fail_validation(lang('Sorry, the key code that you entered is already in use, so please enter a different key code.'), 'code');

	}

	if (api_value("SELECT id FROM offers WHERE code = '" . escape($code) . "' LIMIT 1")) {

		api_fail_validation(lang('This is an offer\'s own code. Checkout would apply that offer and never read the key code.'), 'code');

	}

	return $code;

}

function api_key_code_offer_or_fail($offer_code) {

	if (($offer_code === '') || !api_value("SELECT id FROM offers WHERE code = '" . escape($offer_code) . "' LIMIT 1")) {

		api_fail_validation(lang('Sorry, we cannot find an offer for the offer code that you entered.'), 'offer_code');

	}

	return $offer_code;

}

// A day the code stops working after, or '0000-00-00' for never.
function api_key_code_expiry_in($params) {

	if (!isset($params['expires_on']) || ($params['expires_on'] === '')) {

		return '0000-00-00';

	}

	return date('Y-m-d', (int)$params['expires_on']);

}

// One code with a code of the caller's choosing, or a number of new codes for
// the same offer, as the key code screen makes them. The answer lists what was
// made, under data, even when that is one code.
function api_key_codes_create($params) {

	$app = api_current_app();

	$offer_code = api_key_code_offer_or_fail((string)$params['offer_code']);

	$quantity = (int)$params['quantity'];

	$own = isset($params['code']) ? (string)$params['code'] : '';

	if (($own !== '') && ($quantity > 1)) {

		api_fail_validation(lang('A code of your own can be given to one key code only. Leave code out to have the codes made.'), 'code');

	}

	if ($own !== '') {

		api_key_code_own_or_fail($own);

	}

	$enabled = isset($params['enabled']) ? (bool)$params['enabled'] : true;

	$single_use = !empty($params['single_use']);

	$report = (isset($params['report']) && $params['report'] === 'offer_code') ? 'offer_code' : 'key_code';

	$expires = api_key_code_expiry_in($params);

	$notes = isset($params['notes']) ? (string)$params['notes'] : '';

	api_dry_run_stop('created', 'key_code', array(
		'quantity'   => $quantity,
		'offer_code' => $offer_code,
		'code'       => ($own !== '') ? $own : null,
		'expires_on' => api_key_code_date($expires)
	));

	$owner_id = (int)$app['owner']['id'];

	$ids = array();

	$codes = array();

	for ($i = 0; $i < $quantity; $i++) {

		$code = ($own !== '') ? $own : api_key_code_generate();

		if ($code === '') {

			break;

		}

		api_exec("INSERT INTO key_codes (code, offer_code, enabled, expiration_date, notes, single_use, report, user, timestamp)
			VALUES ('" . escape($code) . "', '" . escape($offer_code) . "', '" . ($enabled ? '1' : '0') . "',
				'" . escape($expires) . "', '" . escape($notes) . "', '" . ($single_use ? '1' : '0') . "',
				'" . $report . "', '" . $owner_id . "', UNIX_TIMESTAMP())");

		$id = api_insert_id();

		if ($id <= 0) {

			break;

		}

		$ids[] = $id;

		$codes[] = $code;

	}

	if (empty($ids)) {

		api_fail_server();

	}

	if (count($ids) === 1) {

		log_activity(lang(array(
			'string' => 'Key code ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($codes[0], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

	} else {

		log_activity(lang(array(
			'string' => '{var:1} key codes for the offer code {var:2} were created through the API by the application {var:3} (key {var:4}).',
			'vars'   => array(count($ids), $offer_code, $app['name'], $app['api_key'])
		)), $app['owner']['username']);

	}

	$out = array();

	foreach (api_rows(api_key_code_select() . " WHERE key_codes.id IN (" . implode(',', array_map('intval', $ids)) . ")
		ORDER BY key_codes.id ASC") as $row) {

		$out[] = api_key_code_present($row);

	}

	api_ok(array('data' => $out), 201);

}

// Only what is sent is changed, as on the key code screen.
function api_key_codes_update($params) {

	$app = api_current_app();

	$row = api_key_code_load_or_fail($params['id']);

	$id = (int)$row['id'];

	$set = array();

	// The code it already has is checked against nothing: it may predate the
	// rule against offer codes, and sending it back changes nothing.
	if (isset($params['code'])) {

		$set['code'] = ((string)$params['code'] === (string)$row['code'])
			? (string)$row['code']
			: api_key_code_own_or_fail((string)$params['code'], $id);

	}

	if (isset($params['offer_code'])) {

		$set['offer_code'] = api_key_code_offer_or_fail((string)$params['offer_code']);

	}

	if (isset($params['enabled'])) {

		$set['enabled'] = $params['enabled'] ? '1' : '0';

	}

	if (isset($params['single_use'])) {

		$set['single_use'] = $params['single_use'] ? '1' : '0';

	}

	if (isset($params['report'])) {

		$set['report'] = ($params['report'] === 'offer_code') ? 'offer_code' : 'key_code';

	}

	if (array_key_exists('expires_on', $params)) {

		$set['expiration_date'] = api_key_code_expiry_in($params);

	}

	if (isset($params['notes'])) {

		$set['notes'] = (string)$params['notes'];

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'key_code', array('id' => $id, 'changes' => array_keys($set)));

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape($value) . "'";

	}

	api_exec("UPDATE key_codes SET " . implode(', ', $columns) . ",
			user = '" . (int)$app['owner']['id'] . "', timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Key code ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(isset($set['code']) ? $set['code'] : $row['code'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(api_key_code_present(api_key_code_load_or_fail($id)));

}

// As on the key code screen. Orders that used the code keep it as they
// recorded it.
function api_key_codes_delete($params) {

	$app = api_current_app();

	$row = api_key_code_load_or_fail($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'key_code', array('id' => $id, 'code' => (string)$row['code']));

	api_exec("DELETE FROM key_codes WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Key code ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['code'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
