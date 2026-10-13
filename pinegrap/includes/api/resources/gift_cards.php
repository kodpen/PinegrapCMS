<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Gift cards: a sixteen-digit code worth an amount, spent at checkout until
// its balance runs out or it expires.
//
// A card is issued on the gift cards screen or bought in an order (then it
// carries the order, who it is from and who it is for). Here a card is
// issued, its balance, expiry and notes are changed, and it is removed, as on
// that screen. The code is the money, so it travels only under its own scope.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_gift_card_date($value) {

	$value = (string)$value;

	return (($value === '') || (strpos($value, '0000-00-00') === 0)) ? null : substr($value, 0, 10);

}

// expired: past its expiry date, whatever is left on it; used: nothing left;
// otherwise active.
function api_gift_card_status($row, $today = null) {

	$today = ($today === null) ? date('Y-m-d') : $today;

	$expires = api_gift_card_date($row['expiration_date']);

	if (($expires !== null) && ($expires < $today)) {

		return 'expired';

	}

	return ((int)$row['balance'] > 0) ? 'active' : 'used';

}

function api_gift_card_present($row) {

	$code = (string)$row['code'];

	return array(
		'id'              => (int)$row['id'],
		'code'            => $code,
		// The code as the screens and e-mails print it: 1234-5678-9012-3456.
		'code_display'    => output_gift_card_code($code),
		'amount'          => api_money($row['amount']),
		'balance'         => api_money($row['balance']),
		'status'          => api_gift_card_status($row),
		'expires_on'      => api_gift_card_date($row['expiration_date']),
		'notes'           => (string)$row['notes'],
		// Set when the card was bought in an order.
		'order_id'        => ((int)$row['order_id'] > 0) ? (int)$row['order_id'] : null,
		'from_name'       => ((string)$row['from_name'] !== '') ? (string)$row['from_name'] : null,
		'recipient_email' => ((string)$row['recipient_email_address'] !== '') ? (string)$row['recipient_email_address'] : null,
		'message'         => ((string)$row['message'] !== '') ? (string)$row['message'] : null,
		'delivery_date'   => api_gift_card_date($row['delivery_date']),
		'created_at'      => api_time($row['created_timestamp']),
		'updated_at'      => api_time($row['last_modified_timestamp']),
		'updated_at_unix' => (int)$row['last_modified_timestamp']
	);

}

function api_gift_card_schema() {

	return array(
		'id'              => 'integer',
		'code'            => 'string',
		'code_display'    => 'string',
		'amount'          => 'integer',
		'balance'         => 'integer',
		'status'          => 'string',
		'expires_on'      => 'string?',
		'notes'           => 'string',
		'order_id'        => 'integer?',
		'from_name'       => 'string?',
		'recipient_email' => 'string?',
		'message'         => 'string?',
		'delivery_date'   => 'string?',
		'created_at'      => 'string?',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer',
		// The single-card read adds where it was spent.
		'redemptions'     => 'GiftCardRedemption[]'
	);

}

// One spend of a card at checkout.
function api_gift_card_redemption_present($row) {

	return array(
		'order_id'    => (int)$row['order_id'],
		'amount'      => api_money($row['amount']),
		'old_balance' => api_money($row['old_balance']),
		'new_balance' => api_money($row['new_balance']),
		'ordered_at'  => ((int)$row['order_date'] > 0) ? api_time($row['order_date']) : null
	);

}

function api_gift_card_redemption_schema() {

	return array(
		'order_id'    => 'integer',
		'amount'      => 'integer',
		'old_balance' => 'integer',
		'new_balance' => 'integer',
		'ordered_at'  => 'string?'
	);

}

function api_gift_card_load_or_fail($id) {

	$row = api_row("SELECT * FROM gift_cards WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Gift Card'));

	}

	return $row;

}

function api_gift_card_answer($id, $status = 200) {

	$row = api_gift_card_load_or_fail($id);

	$out = api_gift_card_present($row);

	$out['redemptions'] = array();

	foreach (api_rows("SELECT applied_gift_cards.order_id, applied_gift_cards.amount, applied_gift_cards.old_balance,
			applied_gift_cards.new_balance, orders.order_date
		FROM applied_gift_cards
		LEFT JOIN orders ON orders.id = applied_gift_cards.order_id
		WHERE applied_gift_cards.gift_card_id = '" . (int)$row['id'] . "'
		ORDER BY applied_gift_cards.id ASC") as $redemption) {

		$out['redemptions'][] = api_gift_card_redemption_present($redemption);

	}

	api_ok($out, $status);

}

// Newest first.
function api_gift_cards_list($params) {

	$where = array();

	$today = date('Y-m-d');

	if (isset($params['status'])) {

		$expired = "(expiration_date <> '0000-00-00' AND expiration_date < '" . $today . "')";

		switch ($params['status']) {

			case 'expired':
				$where[] = $expired;
				break;

			case 'active':
				$where[] = "balance > 0 AND NOT " . $expired;
				break;

			case 'used':
				$where[] = "balance = 0 AND NOT " . $expired;
				break;

		}

	}

	if (isset($params['order_id'])) {

		$where[] = "order_id = '" . (int)$params['order_id'] . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		// A code is typed with or without its dashes.
		$digits = preg_replace('/[\s-]+/', '', $params['q']);

		$q = escape(escape_like($params['q']));

		$where[] = "(code LIKE '%" . escape(escape_like($digits)) . "%' OR notes LIKE '%" . $q . "%'
			OR from_name LIKE '%" . $q . "%' OR recipient_email_address LIKE '%" . $q . "%')";

	}

	if (isset($params['created_since'])) {

		$where[] = "created_timestamp >= '" . (int)$params['created_since'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT * FROM gift_cards"
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_gift_card_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_gift_cards_get($params) {

	api_gift_card_answer($params['id']);

}

// A day the card stops working on, or '' for never.
function api_gift_card_expiry_in($params) {

	if (!isset($params['expires_on']) || ($params['expires_on'] === '')) {

		return '0000-00-00';

	}

	return date('Y-m-d', (int)$params['expires_on']);

}

function api_gift_cards_create($params) {

	$app = api_current_app();

	$amount = (int)$params['amount'];

	$expires = api_gift_card_expiry_in($params);

	$notes = isset($params['notes']) ? (string)$params['notes'] : '';

	api_dry_run_stop('created', 'gift_card', array('amount' => $amount, 'expires_on' => api_gift_card_date($expires)));

	$code = generate_gift_card_code();

	$owner_id = (int)$app['owner']['id'];

	api_exec("INSERT INTO gift_cards (code, amount, balance, notes, message, expiration_date,
			created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
		VALUES ('" . escape($code) . "', '" . $amount . "', '" . $amount . "', '" . escape($notes) . "', '',
			'" . escape($expires) . "', '" . $owner_id . "', UNIX_TIMESTAMP(), '" . $owner_id . "', UNIX_TIMESTAMP())");

	$id = api_insert_id();

	if ($id <= 0) {

		api_fail_server();

	}

	log_activity(lang(array(
		'string' => 'Gift card ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(output_gift_card_code($code), $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_gift_card_answer($id, 201);

}

function api_gift_cards_update($params) {

	$app = api_current_app();

	$row = api_gift_card_load_or_fail($params['id']);

	$id = (int)$row['id'];

	$set = array();

	if (isset($params['balance'])) {

		$set['balance'] = (string)(int)$params['balance'];

	}

	if (array_key_exists('expires_on', $params)) {

		$set['expiration_date'] = api_gift_card_expiry_in($params);

	}

	if (isset($params['notes'])) {

		$set['notes'] = (string)$params['notes'];

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'gift_card', array('id' => $id, 'changes' => array_keys($set)));

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape($value) . "'";

	}

	api_exec("UPDATE gift_cards SET " . implode(', ', $columns) . ",
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Gift card ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(output_gift_card_code($row['code']), $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_gift_card_answer($id);

}

// As on the gift cards screen. The orders it was spent in keep their record of
// the spend, code included.
function api_gift_cards_delete($params) {

	$app = api_current_app();

	$row = api_gift_card_load_or_fail($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'gift_card', array('id' => $id, 'balance' => api_money($row['balance'])));

	api_exec("DELETE FROM gift_cards WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Gift card ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(output_gift_card_code($row['code']), $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
