<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The shop's side of a marketplace account: every product of the shop with
// what the account knows of it (marketplace_products.php, the shop view).
//
// A product is matched on an account when marketplace_product_map has a row
// for the pair, and the row's remote_code is what stock and price are sent to.
// These functions list the products with that row beside them, write and
// remove rows, and queue sends. A row written here is the same row the
// catalogue view writes when a suggestion is accepted (remote_products.php).

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/listings.php');
require_once(dirname(__FILE__) . '/remote_products.php');

/* ----------------------------------------------------------------- pure */

// The WHERE fragment of one of the shop view's filters; an unknown name adds
// nothing.
function mp_matches_filter_sql($filter) {

	switch ((string) $filter) {

		case 'matched':
			return " AND (marketplace_product_map.id IS NOT NULL)";

		case 'unmatched':
			return " AND (marketplace_product_map.id IS NULL)";

		case 'errors':
			return " AND (marketplace_product_map.status = 'error')";

		default:
			return '';

	}

}

// The quantity a product is sent with: zero when its stock is not tracked
// (a marketplace has no "not tracked"), when it is switched off here or
// flagged out of stock; the same rule mp_product_row() sends by.
function mp_matches_quantity($product) {

	if (empty($product['inventory']) || empty($product['enabled']) || !empty($product['out_of_stock'])) {

		return 0;

	}

	return max(0, (int) $product['inventory_quantity']);

}

// Where a matched product's listing stands on a marketplace whose connector
// reads approvals: on (approved, its id is known), rejected (with the
// marketplace's reason, written behind $rejected_prefix), error (a send was
// refused), waiting (not approved yet); '' when the product is not matched.
function mp_matches_listing_state($row, $rejected_prefix) {

	if (!isset($row['map_status']) || $row['map_status'] === null || $row['map_status'] === '') {

		return '';

	}

	if ((string) $row['remote_id'] !== '') {

		return 'on';

	}

	if ($row['map_status'] === 'error') {

		return (($rejected_prefix !== '') && (strpos((string) $row['map_error'], $rejected_prefix) === 0)) ? 'rejected' : 'error';

	}

	return 'waiting';

}

// Product ids out of a posted list: whole and positive, each once, in order.
function mp_matches_ids($values) {

	if (!is_array($values)) {

		return array();

	}

	$ids = array();

	foreach ($values as $value) {

		if (is_scalar($value) && preg_match('/^\d+$/', (string) $value) && (int) $value > 0) {

			$ids[(int) $value] = (int) $value;

		}

	}

	return array_values($ids);

}

/* -------------------------------------------------------------- reading */

// FROM and WHERE of the shop view: every product outside the Recycle Bin,
// with the account's map row when there is one (one at most: the pair is
// unique).
function mp_matches_from_sql($account_id) {

	return "FROM products
		LEFT JOIN marketplace_product_map
			ON (marketplace_product_map.product_id = products.id)
			AND (marketplace_product_map.account_id = '" . e((int) $account_id) . "')
		WHERE 1 = 1" . pg_catalog_bin_live_sql('products');

}

function mp_matches_search_sql($search) {

	$search = trim((string) $search);

	if ($search === '') {

		return '';

	}

	$like = "'%" . e(escape_like($search)) . "%'";

	return " AND ((products.name LIKE " . $like . ") OR (products.short_description LIKE " . $like . ")
		OR (products.title LIKE " . $like . ") OR (marketplace_product_map.remote_code LIKE " . $like . ")
		OR (products.id IN (SELECT product_id FROM product_barcodes WHERE barcode LIKE " . $like . ")))";

}

// How many products each filter holds.
function mp_matches_counts($account_id) {

	$counts = array();

	foreach (array('all', 'matched', 'unmatched', 'errors') as $filter) {

		$counts[$filter] = (int) db_value("SELECT COUNT(*) " . mp_matches_from_sql($account_id) . mp_matches_filter_sql($filter));

	}

	return $counts;

}

// One page of the shop view: the products with their map row's columns
// (map_id, remote_code, remote_id, map_status, map_error, last_pushed and,
// once its column exists, commission).
function mp_matches_page($account_id, $filter, $search, $page, $per_page) {

	$where = mp_matches_from_sql($account_id) . mp_matches_filter_sql($filter) . mp_matches_search_sql($search);

	$total = (int) db_value("SELECT COUNT(*) " . $where);

	$rows = db_items("SELECT products.id, products.name, products.short_description, products.title, products.image_name,
			products.price, products.taxable, products.tax_rate, products.inventory, products.inventory_quantity,
			products.out_of_stock, products.enabled,
			marketplace_product_map.id AS map_id, marketplace_product_map.remote_code, marketplace_product_map.remote_id,
			marketplace_product_map.status AS map_status, marketplace_product_map.last_error AS map_error,
			marketplace_product_map.last_pushed"
			. (mp_commission_ready() ? ", marketplace_product_map.commission" : '') . "
		" . $where . "
		ORDER BY (marketplace_product_map.status = 'error') DESC, products.short_description ASC, products.name ASC, products.id ASC
		LIMIT " . (max(0, (int) $page - 1) * (int) $per_page) . ", " . (int) $per_page);

	return array('rows' => $rows, 'total' => $total);

}

// One product of the shop view, or null.
function mp_matches_row($account_id, $product_id) {

	$page = db_items("SELECT products.id, products.name, products.short_description, products.title, products.image_name,
			products.price, products.taxable, products.tax_rate, products.inventory, products.inventory_quantity,
			products.out_of_stock, products.enabled,
			marketplace_product_map.id AS map_id, marketplace_product_map.remote_code, marketplace_product_map.remote_id,
			marketplace_product_map.status AS map_status, marketplace_product_map.last_error AS map_error,
			marketplace_product_map.last_pushed"
			. (mp_commission_ready() ? ", marketplace_product_map.commission" : '') . "
		" . mp_matches_from_sql($account_id) . " AND (products.id = '" . e((int) $product_id) . "')");

	return $page ? $page[0] : null;

}

/* -------------------------------------------------------------- writing */

// The code a product is matched under when none is typed: its first barcode
// on a marketplace keyed by barcode (Trendyol), else its own code. Most shops
// list under the same code on both sides.
function mp_matches_own_code($account, $product) {

	return mp_provider_keys_by_barcode($account['provider'])
		? mp_product_first_barcode((int) $product['id'])
		: trim((string) $product['name']);

}

// Match a product under $code ('' for its own code), or change the code it
// is matched under. Answers mp_ok() or mp_error() with the reason it was
// refused.
function mp_matches_set_code($account, $product_id, $code) {

	$account_id = (int) $account['id'];

	$product = db_item("SELECT id, name FROM products
		WHERE (id = '" . e((int) $product_id) . "')" . pg_catalog_bin_live_sql('products'));

	if (!$product) {

		return mp_error(lang('That product was not found. Choose one from the list.'));

	}

	$code = mb_substr(trim((string) $code), 0, 255);

	if ($code === '') {

		$code = mp_matches_own_code($account, $product);

	}

	if ($code === '') {

		return mp_error(lang(array(
			'string' => 'This product has no barcode. Type the barcode it is listed under on {var:1}.',
			'vars'   => array(mp_provider_name($account['provider']))
		)));

	}

	// A match left by a product deleted since holds the code for nobody: it
	// sends nothing and no view lists it, so it goes before the check below
	// instead of refusing the code for ever.
	mp_matches_clear_stale($account_id, $code, false);

	// Another product sending to the same code would overwrite this one's
	// stock over there on every send.
	$other = db_item("SELECT marketplace_product_map.product_id, products.name
		FROM marketplace_product_map
		LEFT JOIN products ON products.id = marketplace_product_map.product_id
		WHERE (marketplace_product_map.account_id = '" . e($account_id) . "')
		  AND (marketplace_product_map.product_id != '" . e((int) $product['id']) . "')
		  AND " . mp_remote_products_map_code_sql($code) . "
		LIMIT 1");

	if ($other) {

		// One in the Recycle Bin is not listed in the shop view; the account
		// screen removes the matches of binned products.
		$binned = !db_value("SELECT id FROM products
			WHERE (id = '" . e((int) $other['product_id']) . "')" . pg_catalog_bin_live_sql('products'));

		return mp_error(lang(array(
			'string' => $binned
				? '{var:1} is already matched to {var:2}, which is in the Recycle Bin. Remove its match on the marketplace screen, or restore it.'
				: '{var:1} is already matched to {var:2} on this marketplace.',
			'vars'   => array($code, ($other['name'] !== null) ? $other['name'] : '#' . (int) $other['product_id'])
		)));

	}

	$existing = db_item("SELECT id, remote_code FROM marketplace_product_map
		WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e((int) $product['id']) . "')");

	if ($existing) {

		// Matched again: the listing's id (Trendyol's content id) is
		// forgotten and read again for the code now written, which may be
		// another listing over there.
		db("UPDATE marketplace_product_map
			SET remote_code = '" . e($code) . "', remote_id = '', status = 'active', last_error = ''
			WHERE id = '" . e((int) $existing['id']) . "'");

	} else {

		db("INSERT IGNORE INTO marketplace_product_map
			(account_id, product_id, remote_code, status, created_timestamp)
			VALUES ('" . e($account_id) . "', '" . e((int) $product['id']) . "', '" . e($code) . "', 'active', UNIX_TIMESTAMP())");

	}

	// The product is taken now: a catalogue row that was offered it is
	// offered nothing until the suggestions are worked out again.
	if (mp_remote_products_ready()) {

		db("UPDATE marketplace_remote_products
			SET suggested_product_id = '0', suggestion_score = '0', suggestion_reason = '', suggestion_sure = '0'
			WHERE (account_id = '" . e($account_id) . "') AND (suggested_product_id = '" . e((int) $product['id']) . "')");

	}

	return mp_ok(array('code' => $code));

}

// Match each product under its own code; answers matched and refused
// (product id => reason).
function mp_matches_own_codes($account, $product_ids) {

	$matched = 0;

	$refused = array();

	foreach ((array) $product_ids as $product_id) {

		// A product already matched keeps the code it has.
		$mapped = db_value("SELECT id FROM marketplace_product_map
			WHERE (account_id = '" . e((int) $account['id']) . "') AND (product_id = '" . e((int) $product_id) . "')");

		if ($mapped) {

			continue;

		}

		$result = mp_matches_set_code($account, $product_id, '');

		if ($result['ok']) {

			$matched++;

		} else {

			$refused[(int) $product_id] = $result['error'];

		}

	}

	return array('matched' => $matched, 'refused' => $refused);

}

// Remove the matches of these products on the account. Nothing is changed
// on the marketplace; the products are simply no longer sent to.
function mp_matches_remove($account, $product_ids) {

	$product_ids = mp_matches_ids($product_ids);

	if (!$product_ids) {

		return 0;

	}

	$removed = (int) db_value("SELECT COUNT(*) FROM marketplace_product_map
		WHERE (account_id = '" . e((int) $account['id']) . "') AND (product_id IN (" . implode(',', $product_ids) . "))");

	db("DELETE FROM marketplace_product_map
		WHERE (account_id = '" . e((int) $account['id']) . "') AND (product_id IN (" . implode(',', $product_ids) . "))");

	return $removed;

}

// Queue the stock and price of these products on this account, first in
// line; null for every product it has matched. Answers how many are waiting
// to go now: only an active account sends, and only an active match.
//
// This account only, not through mp_enqueue(), which queues every account
// the product is matched on and pulls a waiting row's run time forward past
// its lease and its retry wait. A product already waiting keeps its row.
function mp_matches_send($account, $product_ids = null) {

	if (($account['status'] !== 'active') || !mp_can($account['provider'], 'push_stock_price')) {

		return 0;

	}

	$account_id = (int) $account['id'];

	$where = "WHERE (account_id = '" . e($account_id) . "') AND (status = 'active')";

	if ($product_ids !== null) {

		$product_ids = mp_matches_ids($product_ids);

		if (!$product_ids) {

			return 0;

		}

		$where .= " AND (product_id IN (" . implode(',', $product_ids) . "))";

	}

	$active = array_map('intval', (array) db_values("SELECT product_id FROM marketplace_product_map " . $where));

	foreach ($active as $product_id) {

		db("INSERT INTO marketplace_sync_queue
			(account_id, product_id, operation, priority, status, run_after, created_timestamp, updated_timestamp)
			SELECT '" . e($account_id) . "', '" . e($product_id) . "', 'stock_price', 1, 'waiting', 0,
				UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
			FROM DUAL
			WHERE NOT EXISTS (SELECT 1 FROM marketplace_sync_queue
				WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e($product_id) . "')
				  AND (operation = 'stock_price') AND (status = 'waiting'))");

	}

	return count($active);

}

/* --------------------------------------------- matches of products gone */

// The WHERE fragment of an account's matches whose product was deleted, or
// with $binned also those whose product is in the Recycle Bin. Neither is
// listed in the shop view.
function mp_matches_stale_sql($account_id, $binned = true) {

	$live = $binned ? pg_catalog_bin_live_sql('products') : '';

	return "WHERE (marketplace_product_map.account_id = '" . e((int) $account_id) . "')
		AND ((products.id IS NULL)" . (($live !== '') ? " OR NOT (1 = 1" . $live . ")" : '') . ")";

}

// How many of an account's matches belong to deleted or binned products.
function mp_matches_stale_count($account_id) {

	return (int) db_value("SELECT COUNT(*) FROM marketplace_product_map
		LEFT JOIN products ON products.id = marketplace_product_map.product_id
		" . mp_matches_stale_sql($account_id));

}

// Remove those matches (with $code, only the ones on that code; with
// $binned false, only the deleted products'). Answers how many went.
function mp_matches_clear_stale($account_id, $code = null, $binned = true) {

	$ids = array_map('intval', (array) db_values("SELECT marketplace_product_map.id FROM marketplace_product_map
		LEFT JOIN products ON products.id = marketplace_product_map.product_id
		" . mp_matches_stale_sql($account_id, $binned)
		. (($code !== null) ? " AND " . mp_remote_products_map_code_sql($code) : '')));

	if ($ids) {

		db("DELETE FROM marketplace_product_map WHERE id IN (" . implode(',', $ids) . ")");

	}

	return count($ids);

}

// Queue a listing update (title, description, pictures) for the approved
// listings among these products, on a marketplace whose connector updates
// listings. Answers how many were queued.
function mp_matches_listing_update($account_id, $product_ids) {

	$product_ids = mp_matches_ids($product_ids);

	if (!$product_ids) {

		return 0;

	}

	$approved = array_map('intval', (array) db_values("SELECT product_id FROM marketplace_product_map
		WHERE (account_id = '" . e((int) $account_id) . "') AND (remote_id <> '')
		  AND (product_id IN (" . implode(',', $product_ids) . "))"));

	foreach ($approved as $product_id) {

		db("INSERT INTO marketplace_sync_queue
			(account_id, product_id, operation, status, priority, attempts, run_after, created_timestamp, updated_timestamp)
			SELECT '" . e((int) $account_id) . "', '" . e($product_id) . "', 'listing_update', 'waiting', 3, 0, 0,
				UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
			FROM DUAL
			WHERE NOT EXISTS (SELECT 1 FROM marketplace_sync_queue
				WHERE (account_id = '" . e((int) $account_id) . "') AND (product_id = '" . e($product_id) . "')
				  AND (operation = 'listing_update') AND (status = 'waiting'))");

	}

	return count($approved);

}
