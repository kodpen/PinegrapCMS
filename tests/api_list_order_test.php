<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the newest-first walk of a cursor-paged list
 * (includes/api/list_order.php) and the lists that take it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/list_order.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/automation_contract.php');

// The cursor helpers live in includes/api/response.php, which loads only behind
// the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_cursor_encode')) {
	function api_cursor_encode($sort_value, $id)
	{
		return rtrim(strtr(base64_encode(json_encode(array('v' => (int) $sort_value, 'i' => (int) $id))), '+/', '-_'), '=');
	}
}

if (!function_exists('api_cursor_decode')) {
	function api_cursor_decode($cursor)
	{
		$decoded = json_decode((string) base64_decode(strtr((string) $cursor, '-_', '+/')), true);

		return (is_array($decoded) && isset($decoded['v'], $decoded['i'])) ? array('v' => (int) $decoded['v'], 'i' => (int) $decoded['i']) : null;
	}
}

// Pages through $rows (each array('v', 'i')) the way a handler does: sorted,
// the rows after the cursor, $limit at a time, the next cursor made from the
// last row. Returns the ids in the order they were handed out.
function api_list_order_test_walk($rows, $limit, $descending)
{
	usort($rows, function ($a, $b) use ($descending) {
		return api_list_compare($a, $b, $descending);
	});

	$seen = array();
	$cursor = null;

	for ($pages = 0; $pages < 100; $pages++) {
		$page = array();

		foreach ($rows as $row) {
			if (($cursor === null) || api_list_after($row['v'], $row['i'], $cursor, $descending)) {
				$page[] = $row;
			}

			if (count($page) > $limit) {
				break;
			}
		}

		$more = (count($page) > $limit);
		$page = array_slice($page, 0, $limit);

		foreach ($page as $row) {
			$seen[] = $row['i'];
		}

		if (!$more) {
			break;
		}

		$last = $page[count($page) - 1];
		$cursor = api_list_cursor_decode(api_list_cursor_encode($last['v'], $last['i'], $descending), $descending);
	}

	return $seen;
}

// Rows with many on the same moment, so the id has to break the ties.
function api_list_order_test_rows()
{
	$rows = array();

	foreach (array(7, 3, 12, 5, 9, 1, 10, 4, 8, 2, 11, 6) as $n => $id) {
		$rows[] = array('v' => 1000 + intdiv($n, 4) * ((($n % 2) === 0) ? 1 : 0), 'i' => $id);
	}

	return $rows;
}

function test_api_list_newest_walk_holds_every_row_once()
{
	$rows = api_list_order_test_rows();

	foreach (array(1, 2, 3, 5, 12, 50) as $limit) {
		$ids = api_list_order_test_walk($rows, $limit, true);

		pg_assert_same(count($rows), count($ids), 'limit ' . $limit . ': every row is handed out');
		pg_assert_same(count($ids), count(array_unique($ids)), 'limit ' . $limit . ': no row twice');

		$expected = $rows;
		usort($expected, function ($a, $b) {
			if ($a['v'] !== $b['v']) {
				return $b['v'] <=> $a['v'];
			}

			return $b['i'] <=> $a['i'];
		});

		pg_assert_same(array_column($expected, 'i'), $ids, 'limit ' . $limit . ': newest value first, a tie by the higher id');
	}
}

function test_api_list_oldest_walk_is_unchanged()
{
	$rows = api_list_order_test_rows();
	$ids = api_list_order_test_walk($rows, 4, false);

	$expected = $rows;
	usort($expected, function ($a, $b) {
		if ($a['v'] !== $b['v']) {
			return $a['v'] <=> $b['v'];
		}

		return $a['i'] <=> $b['i'];
	});

	pg_assert_same(array_column($expected, 'i'), $ids, 'oldest value first, a tie by the lower id');
}

// A walk by the id alone (v is 0 on every row) pages the same way.
function test_api_list_walk_by_id_alone()
{
	$rows = array();

	foreach (array(5, 1, 9, 3, 7) as $id) {
		$rows[] = array('v' => 0, 'i' => $id);
	}

	pg_assert_same(array(9, 7, 5, 3, 1), api_list_order_test_walk($rows, 2, true), 'newest id first');
	pg_assert_same(array(1, 3, 5, 7, 9), api_list_order_test_walk($rows, 2, false), 'oldest id first');
}

function test_api_list_cursor_belongs_to_its_walk()
{
	$oldest = api_list_cursor_encode(1000, 7, false);
	$newest = api_list_cursor_encode(1000, 7, true);

	pg_assert_same(api_cursor_encode(1000, 7), $oldest, 'the default walk keeps the cursor it always had');
	pg_assert_same('n.' . $oldest, $newest, 'a newest-first cursor is marked');
	pg_assert_same(array('v' => 1000, 'i' => 7), api_list_cursor_decode($oldest, false), 'read under its walk');
	pg_assert_same(array('v' => 1000, 'i' => 7), api_list_cursor_decode($newest, true), 'read under its walk');
	pg_assert_same(null, api_list_cursor_decode($oldest, true), 'an oldest-first cursor is refused newest first');
	pg_assert_same(null, api_list_cursor_decode($newest, false), 'a newest-first cursor is refused oldest first');
	pg_assert_same(null, api_list_cursor_decode('n.!!', true), 'an unreadable cursor is refused');
}

function test_api_list_sql()
{
	$cursor = array('v' => 1000, 'i' => 7);

	pg_assert_same("(p.timestamp > '1000' OR (p.timestamp = '1000' AND p.id > '7'))",
		api_list_keyset_sql('p.timestamp', 'p.id', $cursor, false), 'oldest first, past the cursor');
	pg_assert_same("(p.timestamp < '1000' OR (p.timestamp = '1000' AND p.id < '7'))",
		api_list_keyset_sql('p.timestamp', 'p.id', $cursor, true), 'newest first, before the cursor');
	pg_assert_same("c.id > '7'", api_list_keyset_sql('', 'c.id', $cursor, false), 'by the id alone');
	pg_assert_same("c.id < '7'", api_list_keyset_sql('', 'c.id', $cursor, true), 'by the id alone, newest first');
	pg_assert_same('p.timestamp ASC, p.id ASC', api_list_order_sql('p.timestamp', 'p.id', false), 'the default order');
	pg_assert_same('p.timestamp DESC, p.id DESC', api_list_order_sql('p.timestamp', 'p.id', true), 'newest first');
	pg_assert_same('c.id DESC', api_list_order_sql('', 'c.id', true), 'by the id alone');
	pg_assert_same(false, api_list_descending(array()), 'oldest first without sort');
	pg_assert_same(false, api_list_descending(array('sort' => 'oldest')), 'oldest first by name');
	pg_assert_same(true, api_list_descending(array('sort' => 'newest')), 'newest first by name');
}

// Every list the contract names says how to read it newest first, and each
// one that takes sort=newest declares it with oldest as the default and walks
// both ways through list_order.php.
function test_api_list_contract_lists_say_newest()
{
	$handlers = array(
		'customers.list'     => array('includes/api/resources/customers.php', 'api_customers_list'),
		'forms.submissions'  => array('includes/api/resources/forms.php', 'api_form_submissions'),
		'products.list'      => array('includes/api/resources/products.php', 'api_products_list'),
		'subscriptions.list' => array('includes/api/resources/subscriptions.php', 'api_subscriptions_list'),
		'erp.invoices.list'  => array('includes/erp/api_resources.php', 'erp_api_invoices_list'),
		'erp.quotes.list'    => array('includes/erp/api_resources.php', 'erp_api_quotes_list'),
	);

	foreach (pg_automation_contract() as $row) {
		if ($row['kind'] !== 'trigger') {
			continue;
		}

		// The Workspace task list waits for its files to be free.
		if ($row['list'] === 'workspace.tasks.list') {
			continue;
		}

		pg_assert_true(is_array($row['newest']), $row['key'] . ' says how to list ' . $row['list'] . ' newest first');

		if (isset($handlers[$row['list']]) && is_array($row['newest'])) {
			pg_assert_same(array('sort' => 'newest'), $row['newest'], $row['key'] . ' asks sort=newest');
		}
	}

	foreach ($handlers as $route => $where) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $where[0]);
		$start = strpos($source, 'function ' . $where[1] . '(');
		pg_assert_true($start !== false, $where[1] . ' is found');
		$body = substr($source, (int) $start, 4000);

		pg_assert_true(strpos($body, 'api_list_descending($params)') !== false || strpos($body, 'erp_api_page(') !== false,
			$route . ' reads the sort');
		pg_assert_true(strpos($source, "list_order.php'") !== false, $where[0] . ' loads list_order.php');
	}
}
