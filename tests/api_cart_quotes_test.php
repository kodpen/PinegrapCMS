<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's cart quotes (includes/api/resources/
 * cart_quotes.php) and their webhook events (includes/fn/cart_quotes.php):
 * the five routes under the order scopes, every write rehearsable and the
 * link kept out of the Idempotency-Key record; what a quote looks like over
 * the API; the state filter as the quote's own state reads it; the last day
 * a new quote's link works; the shared checks the screens and the API meet
 * before anything is written; and where each cart_quote.* event is
 * announced.
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

// The outbound webhook module's gate asks for init.php. Behind the gate the
// file only declares functions, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/cart_quotes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape, so the
// presenter can be read without it.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// The cart quote routes of the table, by id.
function api_cart_quotes_test_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'cart_quotes.') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

// A quote as api_cart_quote_select() reads it: its row and whom it is for.
function api_cart_quotes_test_row(array $changes = array())
{
	return array_merge(array(
		'id' => '12', 'token_hash' => str_repeat('a', 64), 'token_hint' => 'Ab3dE9', 'contact_id' => '41', 'valid_until' => '0',
		'status' => 'open', 'order_id' => '0', 'created_by' => '3', 'created_at' => '1750000000', 'updated_at' => '1750000100',
		'contact_found' => '41', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'company' => 'Kodpen',
	), $changes);
}

// The source of a loaded function with its comments taken out.
function api_cart_quotes_test_code($function)
{
	return implode('', array_map('pg_test_source_text', pg_test_source_tokens(pg_test_function_source($function))));
}

// Read under orders:read, written under orders:write: the cart quote screens
// open to the same right as the order screens. Every write can be rehearsed;
// the two answers that carry the link keep it out of the Idempotency-Key
// record; a new quote answers 201.
function test_api_cart_quotes_routes()
{
	$routes = api_cart_quotes_test_routes();

	pg_assert_same(array('cart_quotes.list', 'cart_quotes.get', 'cart_quotes.create', 'cart_quotes.cancel', 'cart_quotes.link'), array_keys($routes), 'the five routes');

	$expect = array(
		'cart_quotes.list'   => array('GET', '/cart-quotes', 'orders:read'),
		'cart_quotes.get'    => array('GET', '/cart-quotes/{id}', 'orders:read'),
		'cart_quotes.create' => array('POST', '/cart-quotes', 'orders:write'),
		'cart_quotes.cancel' => array('POST', '/cart-quotes/{id}/cancel', 'orders:write'),
		'cart_quotes.link'   => array('POST', '/cart-quotes/{id}/link', 'orders:write'),
	);

	foreach ($expect as $id => $want) {
		$route = $routes[$id] ?? array();

		pg_assert_same($want, array($route['method'] ?? '', $route['path'] ?? '', $route['scope'] ?? ''), $id);
		pg_assert_true(function_exists((string) ($route['handler'] ?? '')), $id . ': its handler exists');
		pg_assert_false(isset($route['mcp']) && ($route['mcp'] === false), $id . ': an MCP tool');

		if ($want[0] === 'POST') {
			pg_assert_true(!empty($route['dry_run']), $id . ': rehearsable');
		}
	}

	pg_assert_same(201, $routes['cart_quotes.create']['status'] ?? null, 'a new quote answers 201');
	pg_assert_same(array('link'), $routes['cart_quotes.create']['idempotency_redact'] ?? null, 'the new quote\'s link is kept in no Idempotency-Key record');
	pg_assert_same(array('link'), $routes['cart_quotes.link']['idempotency_redact'] ?? null, 'nor a new link');
	pg_assert_false(isset($routes['cart_quotes.create']['idempotency_restore']), 'and cannot be read back: only its hash is kept');

	$lines = null;

	foreach ($routes['cart_quotes.create']['params'] as $param) {
		if ($param['name'] === 'lines') {
			$lines = $param;
		}
	}

	pg_assert_same(PG_CART_QUOTE_MAX_LINES, $lines['max_items'] ?? null, 'lines: at most as many as a quote holds');
	pg_assert_same(array('product_id' => 'integer', 'quantity' => 'integer'), $lines['of'] ?? null, 'lines: objects of product_id and quantity');
}

// A quote over the API: where it stands now, whom it is for, its lines with
// the products as the catalogue has them, and the link only when the answer
// made one.
function test_api_cart_quotes_present()
{
	$now = 1760000000;
	$lines = array(
		array('cart_quote_id' => '12', 'product_id' => '7', 'quantity' => '2', 'found' => '7', 'name' => 'KUPA-01', 'short_description' => 'Kupa'),
		array('cart_quote_id' => '12', 'product_id' => '8', 'quantity' => '1', 'found' => null, 'name' => null, 'short_description' => null),
	);

	$quote = api_cart_quote_present(api_cart_quotes_test_row(), $lines, null, $now);

	pg_assert_same(12, $quote['id']);
	pg_assert_same('open', $quote['state']);
	pg_assert_same(41, $quote['contact_id']);
	pg_assert_same('Ayşe Yılmaz', $quote['contact_name'], 'the contact\'s name');
	pg_assert_same(null, $quote['valid_until'], 'no end');
	pg_assert_same(null, $quote['order_id'], 'no cart filled yet');
	pg_assert_same('Ab3dE9', $quote['token_hint']);
	pg_assert_same(null, $quote['link'], 'no link on a read');
	pg_assert_same(3, $quote['created_by']);
	pg_assert_same(gmdate('Y-m-d\TH:i:s\Z', 1750000100), $quote['updated_at']);
	pg_assert_same(array(
		array('product_id' => 7, 'quantity' => 2, 'name' => 'KUPA-01', 'short_description' => 'Kupa'),
		array('product_id' => 8, 'quantity' => 1, 'name' => null, 'short_description' => null),
	), $quote['lines'], 'a product that is gone keeps its id and quantity');
	pg_assert_false(strpos(json_encode($quote), str_repeat('a', 64)) !== false, 'never the token\'s hash');

	pg_assert_same('https://shop.example/q', api_cart_quote_present(api_cart_quotes_test_row(), array(), 'https://shop.example/q', $now)['link'], 'the link of the answer that made it');
	pg_assert_same('expired', api_cart_quote_present(api_cart_quotes_test_row(array('valid_until' => (string) ($now - 1))), array(), null, $now)['state'], 'open, past its last day');
	pg_assert_same('Kodpen', api_cart_quote_present(api_cart_quotes_test_row(array('first_name' => '', 'last_name' => ' ')), array(), null, $now)['contact_name'], 'a contact without a name: the company');
	pg_assert_same(null, api_cart_quote_present(api_cart_quotes_test_row(array('contact_found' => null, 'first_name' => null, 'last_name' => null, 'company' => null)), array(), null, $now)['contact_name'], 'a contact that is gone');

	$nobody = api_cart_quote_present(api_cart_quotes_test_row(array('contact_id' => '0', 'contact_found' => null, 'first_name' => null, 'last_name' => null, 'company' => null, 'created_by' => '0')), array(), null, $now);

	pg_assert_same(array(null, null, null), array($nobody['contact_id'], $nobody['contact_name'], $nobody['created_by']), 'made for nobody named');
}

// The state filter reads a row as pg_cart_quote_state() does.
function test_api_cart_quotes_state_filter()
{
	$now = 1760000000;

	pg_assert_same("(q.status = 'open' AND (q.valid_until = 0 OR q.valid_until >= '1760000000'))", api_cart_quote_state_clause('open', $now), 'open: no end, or its last day still running');
	pg_assert_same("(q.status = 'expired' OR (q.status = 'open' AND NOT (q.valid_until = 0 OR q.valid_until >= '1760000000')))", api_cart_quote_state_clause('expired', $now), 'expired: stored so, or open past its last day');
	pg_assert_same("(q.status = 'ordered')", api_cart_quote_state_clause('ordered', $now));
	pg_assert_same("(q.status NOT IN ('open', 'ordered', 'expired'))", api_cart_quote_state_clause('cancelled', $now), 'cancelled, and a status the state reads as cancelled');
	pg_assert_same('', api_cart_quote_state_clause('used', $now), 'no such state');

	foreach (api_cart_quotes_test_routes()['cart_quotes.list']['params'] as $param) {
		if ($param['name'] === 'state') {
			pg_assert_same(array('open', 'expired', 'ordered', 'cancelled'), $param['values'], 'the filter offers the four states');
		}
	}
}

// A new quote's link works through the end of the day sent; nothing sent
// gives the screen's 30 days, an empty value no end.
function test_api_cart_quotes_last_day()
{
	$now = mktime(15, 30, 0, 10, 29, 2026);

	pg_assert_same(mktime(23, 59, 59, 11, 28, 2026), api_cart_quote_until_in(array(), $now), 'nothing sent: 30 days from today, to the end of that day');
	pg_assert_same(0, api_cart_quote_until_in(array('valid_until' => ''), $now), 'empty: no end');
	pg_assert_same(mktime(23, 59, 59, 12, 31, 2026), api_cart_quote_until_in(array('valid_until' => mktime(0, 0, 0, 12, 31, 2026)), $now), 'a day: the whole day');
	pg_assert_same(mktime(23, 59, 59, 12, 31, 2026), api_cart_quote_until_in(array('valid_until' => mktime(9, 15, 0, 12, 31, 2026)), $now), 'a moment: the whole day it falls on');
	pg_assert_same(30, PG_CART_QUOTE_DEFAULT_DAYS, 'the screen\'s default');
}

// The screens and the API meet one set of refusals: the writers call the
// shared checks first, the new cart quote screen keeps only its date parsing,
// and each API write stops a rehearsal after the check and before the write.
function test_api_cart_quotes_share_the_checks_and_stop_before_the_write()
{
	pg_assert_contains('$checked = pg_cart_quote_create_check($contact_id, $lines, $valid_until);', api_cart_quotes_test_code('pg_cart_quote_create'), 'a new quote is checked first');
	pg_assert_contains("pg_cart_quote_open_check(\$quote_id, 'cancel')", api_cart_quotes_test_code('pg_cart_quote_cancel'), 'a cancel is checked first');
	pg_assert_contains("pg_cart_quote_open_check(\$quote_id, 'link')", api_cart_quotes_test_code('pg_cart_quote_new_link'), 'a new link is checked first');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_cart_quote.php');

	pg_assert_false(strpos($screen, 'SELECT COUNT(*) FROM contacts') !== false, 'the screen no longer looks the contact up itself');
	pg_assert_false(strpos($screen, '($until < time())') !== false, 'nor checks the past day itself');
	pg_assert_contains("in_array(\$result['field'], array('contact_id', 'valid_until'), true)", $screen, 'it marks the field the check names');

	$writes = array(
		'api_cart_quotes_create' => array('pg_cart_quote_create_check(', 'pg_cart_quote_create('),
		'api_cart_quotes_cancel' => array('pg_cart_quote_open_check(', 'pg_cart_quote_cancel('),
		'api_cart_quotes_link'   => array('pg_cart_quote_open_check(', 'pg_cart_quote_new_link('),
	);

	foreach ($writes as $handler => $calls) {
		$code = api_cart_quotes_test_code($handler);
		$check = strpos($code, $calls[0]);
		$stop = strpos($code, 'api_dry_run_stop(');
		$write = strpos($code, $calls[1] . '$');

		pg_assert_true(($check !== false) && ($stop !== false) && ($write !== false), $handler . ': check, stop and write are there');
		pg_assert_true(($check < $stop) && ($stop < $write), $handler . ': the rehearsal stops after the check and before the write');
	}
}

// The four events are in the catalogue, so pg_announce() does not drop them.
function test_api_cart_quotes_events_are_in_the_catalogue()
{
	$events = api_webhook_events();

	foreach (array('cart_quote.created', 'cart_quote.applied', 'cart_quote.ordered', 'cart_quote.cancelled') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is a webhook event');
	}
}

// Each event is announced where its change is written, once that write
// stands: a new quote once its lines are in; a cancel once the row moved; a
// cart filled only when the quote still open took it and a line went in; an
// order for each quote that moved to ordered. A new link is not announced.
function test_api_cart_quotes_events_are_announced_where_the_change_is_written()
{
	$create = api_cart_quotes_test_code('pg_cart_quote_create');
	pg_assert_true(strpos($create, "pg_cart_quote_announce('cart_quote.created', \$id);") > strpos($create, 'INSERT INTO cart_quote_items'), 'created, after the lines');

	$cancel = api_cart_quotes_test_code('pg_cart_quote_cancel');
	pg_assert_true(strpos($cancel, "pg_cart_quote_announce('cart_quote.cancelled'") > strpos($cancel, 'mysqli_affected_rows(db::$con) < 1'), 'cancelled, once the row moved');

	$apply = api_cart_quotes_test_code('pg_cart_quote_apply');
	pg_assert_contains("if (\$remembered && (\$added > 0)) {\n        pg_cart_quote_announce('cart_quote.applied', (int) \$quote['id'], array('added' => \$added));", $apply, 'applied, for a cart the open quote took with a line in it');
	pg_assert_true(strpos($apply, "'again' => true") < strpos($apply, 'pg_cart_quote_announce('), 'the same cart again returns first');

	$ordered = api_cart_quotes_test_code('pg_cart_quote_order_completed');
	pg_assert_contains("pg_cart_quote_announce('cart_quote.ordered', (int) \$quote_id, array('order_number' => \$order_number));", $ordered, 'ordered, with the order\'s number');
	pg_assert_true(strpos($ordered, 'continue;') < strpos($ordered, 'pg_cart_quote_announce('), 'only a quote that moved');

	pg_assert_false(strpos(api_cart_quotes_test_code('pg_cart_quote_new_link'), 'pg_cart_quote_announce(') !== false, 'a new link is not announced');
}
