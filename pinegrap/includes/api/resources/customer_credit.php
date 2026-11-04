<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A customer's store credit: GET /customers/{id}/credit, POST (issue) and
// POST /customers/{id}/credit/{card_id}/void.
//
// Store credit is a gift card bound to a site account (gift_cards.user_id,
// includes/fn/store_credit.php); a customer record reaches it through the
// accounts linked to it (user.user_contact), as the wishlist and the
// subscriptions do. The routes sit under gift_cards:* because the credit is
// money, the same right the staff screen asks for (role below 3 or
// manage_ecommerce); the customers row rule applies on top, so an owner with
// the user role reaches only the customers of its contact groups.
//
// The card's code is never answered: a customer's credit is spent by being
// signed in, and the code of a bound card pays for nobody else.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Answers 503 before the update that adds the store credit columns.
function api_customer_credit_ready_or_fail() {

	if (!function_exists('pg_credit_ready') || !pg_credit_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

// The site accounts linked to a customer record, lowest id first.
function api_customer_credit_accounts($contact_id) {

	$ids = array();

	foreach (api_rows("SELECT user_id FROM user WHERE user_contact = '" . (int)$contact_id . "' ORDER BY user_id") as $row) {

		$ids[] = (int)$row['user_id'];

	}

	return $ids;

}

// The account a new card is given to (pure): the one sent, which must be
// among the customer's, or the only one when none is sent. Answers the
// account id, or the reason it cannot: 'none' (no account), 'choose' (several
// and none sent), 'other' (the one sent is not this customer's).
function api_customer_credit_pick_account(array $accounts, $sent) {

	$sent = (int)$sent;

	if (!$accounts) {

		return 'none';

	}

	if ($sent > 0) {

		return in_array($sent, array_map('intval', $accounts), true) ? $sent : 'other';

	}

	return (count($accounts) === 1) ? (int)reset($accounts) : 'choose';

}

// One card as answered: the My Account reading of it (last day, state) with
// its account and when it was given.
function api_store_credit_card_present(array $card, $now) {

	$view = pg_credit_account_cards(array($card), array(), (int)$now);

	$view = $view[0];

	$issued_by = (int)($card['issued_by'] ?? 0);

	return array(
		'id'          => (int)$view['id'],
		'account_id'  => (int)($card['user_id'] ?? 0),
		'kind'        => (string)$view['kind'],
		'reason'      => (string)$view['reason'],
		'amount'      => (int)$view['amount'],
		'balance'     => (int)$view['balance'],
		'status'      => (string)$view['state'],
		'last_day'    => ($view['last_day'] !== '') ? (string)$view['last_day'] : null,
		'expires_at'  => api_time($card['expires_at'] ?? 0),
		'issued_by'   => ($issued_by > 0) ? $issued_by : null,
		'created_at'  => api_time($card['created_timestamp'] ?? 0)
	);

}

function api_store_credit_card_schema() {

	return array(
		'id'         => 'integer',
		'account_id' => 'integer',
		'kind'       => 'string',
		'reason'     => 'string',
		'amount'     => 'integer',
		'balance'    => 'integer',
		'status'     => 'string',
		'last_day'   => 'string?',
		'expires_at' => 'string?',
		'issued_by'  => 'integer?',
		'created_at' => 'string?'
	);

}

function api_customer_credit_schema() {

	return array(
		'customer_id' => 'integer',
		'account_ids' => 'integer[]',
		'balance'     => 'integer',
		'cards'       => 'StoreCreditCard[]'
	);

}

// The cards of the customer's accounts, newest first: the last
// PG_CREDIT_STAFF_CARDS of each account, as the staff screen lists them.
function api_customer_credit_cards(array $accounts) {

	$cards = array();

	foreach ($accounts as $user_id) {

		foreach (pg_credit_staff_cards($user_id) as $card) {

			$card['user_id'] = (int)$user_id;

			$cards[] = $card;

		}

	}

	usort($cards, function ($a, $b) {

		return (int)$b['id'] - (int)$a['id'];

	});

	return $cards;

}

function api_customers_credit($params) {

	$id = (int)$params['id'];

	api_customer_reachable_or_fail($id, 'contacts.id');

	api_customer_credit_ready_or_fail();

	$now = time();

	$accounts = api_customer_credit_accounts($id);

	$balance = 0;

	foreach ($accounts as $user_id) {

		$balance += pg_credit_balance($user_id, $now);

	}

	$cards = array();

	foreach (api_customer_credit_cards($accounts) as $card) {

		$cards[] = api_store_credit_card_present($card, $now);

	}

	api_ok(array(
		'customer_id' => $id,
		'account_ids' => $accounts,
		'balance'     => $balance,
		'cards'       => $cards
	));

}

// The card as it stands now, read back after a write.
function api_store_credit_card_answer($card_id, $status = 200) {

	$card = api_row("SELECT id, user_id, code, amount, balance, kind, reason, issued_by, expires_at, expiration_date, created_timestamp
		FROM gift_cards WHERE id = '" . (int)$card_id . "' LIMIT 1");

	if ($card === null) {

		api_fail_server();

	}

	api_ok(api_store_credit_card_present($card, time()), $status);

}

// A reason is required: it is shown to the customer and kept on the card.
function api_customer_credit_reason_or_fail($params) {

	$reason = trim((string)($params['reason'] ?? ''));

	if ($reason === '') {

		api_fail_validation(lang('Enter the reason for this store credit.'), 'reason');

	}

	return $reason;

}

function api_customers_credit_issue($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	api_customer_reachable_or_fail($id, 'contacts.id');

	api_customer_credit_ready_or_fail();

	$account = api_customer_credit_pick_account(api_customer_credit_accounts($id), $params['account_id'] ?? 0);

	if ($account === 'none') {

		api_fail(409, 'customer_has_no_account', lang('Store credit belongs to a site account, and this customer has none.'));

	}

	if ($account === 'choose') {

		api_fail_validation(lang('This customer has several site accounts: send account_id to say which one gets the credit.'), 'account_id');

	}

	if ($account === 'other') {

		api_fail_validation(lang('account_id is not one of this customer\'s site accounts.'), 'account_id');

	}

	$amount = (int)$params['amount'];

	$expires = pg_credit_last_day_expires($params['last_day'] ?? '', time());

	if ($expires < 0) {

		api_fail_validation(lang('The last day must be a date in the future, or left empty.'), 'last_day');

	}

	$reason = api_customer_credit_reason_or_fail($params);

	api_dry_run_stop('created', 'store_credit', array(
		'account_id' => $account,
		'amount'     => $amount,
		'last_day'   => ($expires > 0) ? pg_credit_expiration_date($expires) : null
	));

	$owner_id = (int)$app['owner']['id'];

	$card_id = pg_credit_issue($account, $amount, 'manual', $reason, $expires, $owner_id);

	if ($card_id <= 0) {

		api_fail_server();

	}

	log_activity(lang(array(
		'string' => 'Store credit of {var:1} was issued to user #{var:2} through the API by the application {var:3}: {var:4}',
		'vars'   => array(pg_order_log_money($amount), $account, $app['name'], $reason)
	)), $app['owner']['username']);

	api_store_credit_card_answer($card_id, 201);

}

function api_customers_credit_void($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	api_customer_reachable_or_fail($id, 'contacts.id');

	api_customer_credit_ready_or_fail();

	$accounts = api_customer_credit_accounts($id);

	$card = $accounts
		? api_row("SELECT id, user_id, balance FROM gift_cards
			WHERE (id = '" . (int)$params['card_id'] . "') AND (user_id > 0)
				AND user_id IN (" . implode(',', array_map('intval', $accounts)) . ")
			LIMIT 1")
		: null;

	if ($card === null) {

		api_fail_not_found(lang('Store credit'));

	}

	$reason = api_customer_credit_reason_or_fail($params);

	if ((int)$card['balance'] <= 0) {

		api_fail(409, 'credit_not_open', lang('Nothing is left on this store credit card to void.'));

	}

	api_dry_run_stop('voided', 'store_credit', array('id' => (int)$card['id'], 'balance' => (int)$card['balance']));

	if (!pg_credit_void((int)$card['id'], $reason)) {

		api_fail(409, 'credit_not_open', lang('The store credit card could not be voided: it may have been used meanwhile. Check it and try again.'));

	}

	log_activity(lang(array(
		'string' => 'Store credit card #{var:1} of user #{var:2} was voided through the API by the application {var:3}: {var:4}',
		'vars'   => array((int)$card['id'], (int)$card['user_id'], $app['name'], $reason)
	)), $app['owner']['username']);

	api_store_credit_card_answer((int)$card['id']);

}
