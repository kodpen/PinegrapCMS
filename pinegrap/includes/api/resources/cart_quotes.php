<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Cart quotes: the products and quantities staff send a customer as a link
// that fills their cart at the store's prices of the day it is used
// (includes/fn/cart_quotes.php).
//
// Read and written under the order scopes: the cart quote screens open to the
// same right as the order screens. A quote is made, cancelled or given a new
// link here as on those screens, through the same checks
// (pg_cart_quote_create_check(), pg_cart_quote_open_check()). The link is in
// the answer that makes it and nowhere else: only its hash is kept.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The columns a quote is read with: its row and whom it is for.
function api_cart_quote_select() {

	return "SELECT q.*, c.id AS contact_found, c.first_name, c.last_name, c.company
		FROM cart_quotes AS q
		LEFT JOIN contacts AS c ON c.id = q.contact_id";

}

// One line: the product as the catalogue has it now, null where the product
// is gone.
function api_cart_quote_line_present($line) {

	$found = ($line['found'] ?? null) !== null;

	return array(
		'product_id'        => (int)$line['product_id'],
		'quantity'          => (int)$line['quantity'],
		'name'              => $found ? (string)$line['name'] : null,
		'short_description' => $found ? (string)$line['short_description'] : null
	);

}

function api_cart_quote_line_schema() {

	return array(
		'product_id'        => 'integer',
		'quantity'          => 'integer',
		'name'              => 'string?',
		'short_description' => 'string?'
	);

}

// $link is the address only the answers that make one carry; $lines the
// quote's lines as api_cart_quote_lines() reads them.
function api_cart_quote_present($row, $lines = array(), $link = null, $now = null) {

	$now = ($now === null) ? time() : (int)$now;

	$contact_id = (int)$row['contact_id'];

	$person = trim(trim((string)$row['first_name']) . ' ' . trim((string)$row['last_name']));

	$name = ($person !== '') ? $person : trim((string)$row['company']);

	$out = array();

	foreach ($lines as $line) {

		$out[] = api_cart_quote_line_present($line);

	}

	return array(
		'id'              => (int)$row['id'],
		'status'          => (string)$row['status'],
		// Where it stands now: an open quote past valid_until is expired.
		'state'           => pg_cart_quote_state($row, $now),
		'contact_id'      => ($contact_id > 0) ? $contact_id : null,
		// As the cart quote list names the contact; null for nobody named or
		// a contact that is gone, or one with neither a name nor a company.
		'contact_name'    => (($contact_id > 0) && (($row['contact_found'] ?? null) !== null) && ($name !== '')) ? $name : null,
		'valid_until'     => api_time($row['valid_until']),
		'order_id'        => ((int)$row['order_id'] > 0) ? (int)$row['order_id'] : null,
		'token_hint'      => (string)$row['token_hint'],
		'link'            => ($link === null) ? null : (string)$link,
		'lines'           => $out,
		'created_by'      => ((int)$row['created_by'] > 0) ? (int)$row['created_by'] : null,
		'created_at'      => api_time($row['created_at']),
		'updated_at'      => api_time($row['updated_at']),
		'updated_at_unix' => (int)$row['updated_at']
	);

}

function api_cart_quote_schema() {

	return array(
		'id'              => 'integer',
		'status'          => 'string',
		'state'           => 'string',
		'contact_id'      => 'integer?',
		'contact_name'    => 'string?',
		'valid_until'     => 'string?',
		'order_id'        => 'integer?',
		'token_hint'      => 'string',
		'link'            => 'string?',
		'lines'           => 'CartQuoteLine[]',
		'created_by'      => 'integer?',
		'created_at'      => 'string?',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

// The lines of several quotes in one read, by quote id, in the order they
// were made.
function api_cart_quote_lines($quote_ids) {

	$ids = array_values(array_unique(array_filter(array_map('intval', (array)$quote_ids))));

	$out = array();

	if (empty($ids)) {

		return $out;

	}

	foreach (api_rows("SELECT i.cart_quote_id, i.product_id, i.quantity, p.id AS found, p.name, p.short_description
		FROM cart_quote_items AS i
		LEFT JOIN products AS p ON p.id = i.product_id
		WHERE i.cart_quote_id IN (" . implode(',', $ids) . ")
		ORDER BY i.id ASC") as $line) {

		$out[(int)$line['cart_quote_id']][] = $line;

	}

	return $out;

}

// The condition of the state filter, as pg_cart_quote_state() reads a row:
// an open quote past its last day is expired, a status it does not know
// reads as cancelled. '' for a state there is none of.
function api_cart_quote_state_clause($state, $now) {

	$now = (int)$now;

	$running = "(q.valid_until = 0 OR q.valid_until >= '" . $now . "')";

	switch ($state) {

		case 'open':
			return "(q.status = 'open' AND " . $running . ")";

		case 'expired':
			return "(q.status = 'expired' OR (q.status = 'open' AND NOT " . $running . "))";

		case 'ordered':
			return "(q.status = 'ordered')";

		case 'cancelled':
			return "(q.status NOT IN ('open', 'ordered', 'expired'))";

	}

	return '';

}

// The last moment the link of a new quote works: the end of the day sent, in
// the site's time zone; no end for an empty value; the screen's default, 30
// days from today, when nothing is sent.
function api_cart_quote_until_in($params, $now) {

	if (!array_key_exists('valid_until', $params)) {

		return (int)pg_cart_quote_until(date('Y-m-d', strtotime('+' . PG_CART_QUOTE_DEFAULT_DAYS . ' days', (int)$now)));

	}

	if ($params['valid_until'] === '') {

		return 0;

	}

	return (int)pg_cart_quote_until(date('Y-m-d', (int)$params['valid_until']));

}

function api_cart_quote_ready_or_fail() {

	if (!pg_cart_quotes_ready()) {

		api_fail(503, 'service_unavailable', lang('Cart quotes are not installed yet: the database has to be updated first.'));

	}

}

// A refusal of the shared checks, answered as the API answers it.
function api_cart_quote_refuse($checked) {

	$code = (string)$checked['code'];

	if ($code === 'not_installed') {

		api_fail(503, 'service_unavailable', $checked['error']);

	}

	if ($code === 'not_found') {

		api_fail_not_found(lang('Cart quote'));

	}

	if ($code === 'not_open') {

		api_fail(409, 'cart_quote_not_open', $checked['error']);

	}

	if ((string)($checked['field'] ?? '') !== '') {

		api_fail_validation($checked['error'], $checked['field']);

	}

	api_fail_server();

}

function api_cart_quote_load_or_fail($id) {

	api_cart_quote_ready_or_fail();

	$row = api_row(api_cart_quote_select() . " WHERE q.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Cart quote'));

	}

	return $row;

}

function api_cart_quote_answer($id, $status = 200, $link = null) {

	$row = api_cart_quote_load_or_fail($id);

	$lines = api_cart_quote_lines(array((int)$row['id']));

	api_ok(api_cart_quote_present($row, $lines[(int)$row['id']] ?? array(), $link), $status);

}

// Newest first.
function api_cart_quotes_list($params) {

	api_cart_quote_ready_or_fail();

	$now = time();

	$where = array();

	if (isset($params['state'])) {

		$where[] = api_cart_quote_state_clause($params['state'], $now);

	}

	if (isset($params['contact_id'])) {

		$where[] = "q.contact_id = '" . (int)$params['contact_id'] . "'";

	}

	if (isset($params['order_id'])) {

		$where[] = "q.order_id = '" . (int)$params['order_id'] . "'";

	}

	if (isset($params['created_since'])) {

		$where[] = "q.created_at >= '" . (int)$params['created_since'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "q.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_cart_quote_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY q.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$lines = api_cart_quote_lines(array_column($rows, 'id'));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_cart_quote_present($row, $lines[(int)$row['id']] ?? array(), null, $now);

	}

	api_ok_list($out, $limit, $next);

}

function api_cart_quotes_get($params) {

	api_cart_quote_answer($params['id']);

}

function api_cart_quotes_create($params) {

	$app = api_current_app();

	$now = time();

	$contact_id = isset($params['contact_id']) ? (int)$params['contact_id'] : 0;

	$until = api_cart_quote_until_in($params, $now);

	$checked = pg_cart_quote_create_check($contact_id, $params['lines'], $until, $now);

	if (!$checked['ok']) {

		api_cart_quote_refuse($checked);

	}

	api_dry_run_stop('created', 'cart_quote', array(
		'contact_id'  => ($checked['contact_id'] > 0) ? $checked['contact_id'] : null,
		'valid_until' => api_time($checked['valid_until']),
		'lines'       => $checked['lines']
	));

	$result = pg_cart_quote_create($checked['contact_id'], $checked['lines'], $checked['valid_until'], (int)$app['owner']['id']);

	if (!$result['ok']) {

		api_cart_quote_refuse($result);

	}

	log_activity(lang(array(
		'string' => 'Cart quote {var:1} was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array('#' . (int)$result['id'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_cart_quote_answer($result['id'], 201, pg_cart_quote_url($result['token']));

}

function api_cart_quotes_cancel($params) {

	$app = api_current_app();

	$checked = pg_cart_quote_open_check($params['id'], 'cancel');

	if (!$checked['ok']) {

		api_cart_quote_refuse($checked);

	}

	$id = (int)$checked['quote']['id'];

	api_dry_run_stop('cancelled', 'cart_quote', array('id' => $id));

	// Ordered, cancelled or past its last day between the check and the
	// write.
	if (!pg_cart_quote_cancel($id)) {

		api_fail(409, 'cart_quote_not_open', lang('Only an open cart quote can be cancelled.'));

	}

	log_activity(lang(array(
		'string' => 'Cart quote {var:1} was cancelled through the API by the application {var:2} (key {var:3}).',
		'vars'   => array('#' . $id, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_cart_quote_answer($id);

}

function api_cart_quotes_link($params) {

	$app = api_current_app();

	$checked = pg_cart_quote_open_check($params['id'], 'link');

	if (!$checked['ok']) {

		api_cart_quote_refuse($checked);

	}

	$id = (int)$checked['quote']['id'];

	api_dry_run_stop('updated', 'cart_quote', array('id' => $id, 'changes' => array('link')));

	$token = pg_cart_quote_new_link($id);

	if ($token === '') {

		api_fail(409, 'cart_quote_not_open', lang('Only an open cart quote can be given a new link.'));

	}

	log_activity(lang(array(
		'string' => 'Cart quote {var:1} was given a new link through the API by the application {var:2} (key {var:3}).',
		'vars'   => array('#' . $id, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_cart_quote_answer($id, 200, pg_cart_quote_url($token));

}
