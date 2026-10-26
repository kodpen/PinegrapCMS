<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A marketplace's own catalogue, read in so the shop's products can be matched
// to it in bulk (marketplace_products.php).
//
// The read is the operator's, on a button, a page of their catalogue at a
// time: the screen asks for the next stretch until the connector says the
// catalogue ended, so a store with ten thousand products there needs no
// request longer than a few seconds. Every row a read stores carries the read's
// start as fetched_timestamp; the read that reaches the end removes the rows
// it did not see (taken off the marketplace since), then works out the
// suggestions (matching.php) for the rows nobody matched yet.
//
// A match is a marketplace_product_map row, the same row the account screen's
// form writes, with the remote row's code. Nothing is sent when one is made.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/matching.php');

// Whether the table is there: files land before the upgrade that adds it.
function mp_remote_products_ready() {

	return function_exists('pg_schema_has') && pg_schema_has('marketplace_remote_products');

}

// Whether this account's marketplace can list its catalogue.
function mp_remote_products_supported($account) {

	return mp_can($account['provider'], 'remote_products') && mp_remote_products_ready();

}

/* ------------------------------------------------------------------ the read */

// One stretch of a read: pages from $page on, for about $seconds.
//
// $run is the read's start (unix seconds), the same number for every stretch
// of one read. Answers data: done, next (the page to ask for next), pages,
// total, read (rows stored by this stretch), and when done the counts of
// mp_remote_products_finish().
function mp_remote_products_read($account, $page, $run, $seconds = 12) {

	if (!mp_remote_products_supported($account)) {

		return mp_error(lang(array(
			'string' => '{var:1} cannot do this.',
			'vars'   => array(mp_provider_name($account['provider']))
		)));

	}

	$started = microtime(true);

	$page = max(0, (int) $page);

	$read = 0;

	$pages = 0;

	$total = 0;

	// A ceiling well past any real store, so a marketplace that never says
	// "last" cannot keep the screen asking for ever.
	while ($page < 2000) {

		$result = mp_call($account['provider'], 'remote_products', array($account, $page));

		if (!$result['ok']) {

			// What earlier pages stored stays; asking again goes on from here.
			$result['data'] = array('next' => $page);

			return $result;

		}

		$read += mp_remote_products_store((int) $account['id'], $result['data']['products'], $run);

		$pages = (int) $result['data']['pages'];

		$total = (int) $result['data']['total'];

		if (!empty($result['data']['last'])) {

			return mp_ok(array_merge(array(
				'done'  => true,
				'next'  => $page + 1,
				'pages' => $pages,
				'total' => $total,
				'read'  => $read
			), mp_remote_products_finish($account, $run)));

		}

		$page++;

		if ((microtime(true) - $started) >= $seconds) {

			break;

		}

	}

	return mp_ok(array('done' => false, 'next' => $page, 'pages' => $pages, 'total' => $total, 'read' => $read));

}

// Store one page of rows; answers how many were written.
//
// One statement per page. A row already there keeps its suggestion and its
// dismissal and takes the new title, price, picture and the read's stamp.
function mp_remote_products_store($account_id, $rows, $run) {

	$values = array();

	foreach ((array) $rows as $row) {

		$code = mb_substr(trim((string) $row['remote_code']), 0, 255);

		if ($code === '') {

			continue;

		}

		$values[$code] = "('" . e((int) $account_id) . "', '" . e($code) . "', '"
			. e(mb_substr((string) $row['remote_id'], 0, 64)) . "', '"
			. e(mb_substr((string) $row['group_code'], 0, 255)) . "', '"
			. e(mb_substr((string) $row['barcode'], 0, 64)) . "', '"
			. e(mb_substr((string) $row['title'], 0, 500)) . "', '"
			. e(mb_substr((string) $row['image_url'], 0, 2000)) . "', '"
			. e(max(-2147483648, min(2147483647, (int) $row['price']))) . "', '"
			. e(max(-2147483648, min(2147483647, (int) $row['quantity']))) . "', '"
			. e(mb_substr((string) $row['status'], 0, 60)) . "', '"
			. e((int) $run) . "')";

	}

	if (!$values) {

		return 0;

	}

	db("INSERT INTO marketplace_remote_products
		(account_id, remote_code, remote_id, group_code, barcode, title, image_url, price, quantity,
		 remote_status, fetched_timestamp)
		VALUES " . implode(",\n", $values) . "
		ON DUPLICATE KEY UPDATE
			remote_id = VALUES(remote_id), group_code = VALUES(group_code), barcode = VALUES(barcode),
			title = VALUES(title), image_url = VALUES(image_url), price = VALUES(price),
			quantity = VALUES(quantity), remote_status = VALUES(remote_status),
			fetched_timestamp = VALUES(fetched_timestamp)");

	return count($values);

}

// The end of a read: forget what the marketplace no longer lists, then work
// out the suggestions. Answers removed, suggested, sure.
function mp_remote_products_finish($account, $run) {

	$account_id = (int) $account['id'];

	$removed = (int) db_value("SELECT COUNT(*) FROM marketplace_remote_products
		WHERE (account_id = '" . e($account_id) . "') AND (fetched_timestamp < '" . e((int) $run) . "')");

	if ($removed) {

		db("DELETE FROM marketplace_remote_products
			WHERE (account_id = '" . e($account_id) . "') AND (fetched_timestamp < '" . e((int) $run) . "')");

	}

	return array_merge(array('removed' => $removed), mp_remote_products_suggest($account));

}

// When this account's catalogue was last read in full, or 0.
function mp_remote_products_read_at($account_id) {

	return (int) db_value("SELECT MAX(fetched_timestamp) FROM marketplace_remote_products
		WHERE account_id = '" . e((int) $account_id) . "'");

}

/* ------------------------------------------------------------ suggestions */

// Work the suggestions out again for every row nobody matched or dismissed.
// Answers suggested and sure.
function mp_remote_products_suggest($account) {

	$account_id = (int) $account['id'];

	$taken = array();

	$mapped_codes = array();

	foreach (db_items("SELECT product_id, remote_code FROM marketplace_product_map
		WHERE account_id = '" . e($account_id) . "'") as $map) {

		$taken[] = (int) $map['product_id'];

		$mapped_codes[] = $map['remote_code'];

	}

	$remote = array();

	$rows = db_items("SELECT id, remote_code, barcode, title, price FROM marketplace_remote_products
		WHERE (account_id = '" . e($account_id) . "') AND (dismissed = '0')");

	foreach (mp_remote_products_unmatched_rows($rows, $mapped_codes) as $row) {

		$remote[] = array(
			'key'     => (string) $row['id'],
			'code'    => $row['remote_code'],
			'barcode' => $row['barcode'],
			'title'   => $row['title'],
			'price'   => (int) $row['price']
		);

	}

	$suggested = $remote ? mp_match_suggest($remote, mp_remote_products_shop_catalogue(), $taken) : array();

	db("UPDATE marketplace_remote_products
		SET suggested_product_id = '0', suggestion_score = '0', suggestion_reason = '', suggestion_sure = '0'
		WHERE (account_id = '" . e($account_id) . "') AND (suggested_product_id != '0')");

	foreach ($suggested as $id => $suggestion) {

		db("UPDATE marketplace_remote_products SET
				suggested_product_id = '" . e((int) $suggestion['product_id']) . "',
				suggestion_score = '" . e((int) $suggestion['score']) . "',
				suggestion_reason = '" . e($suggestion['reason']) . "',
				suggestion_sure = '" . ($suggestion['sure'] ? '1' : '0') . "'
			WHERE (id = '" . e((int) $id) . "') AND (account_id = '" . e($account_id) . "')");

	}

	return array('suggested' => count($suggested), 'sure' => mp_match_sure_count($suggested));

}

// The shop's products as the matcher takes them: every product outside the
// Recycle Bin, with its barcodes and the price its checkout charges.
function mp_remote_products_shop_catalogue() {

	$barcodes = array();

	foreach (db_items("SELECT product_id, barcode FROM product_barcodes WHERE barcode != ''") as $row) {

		$barcodes[(int) $row['product_id']][] = $row['barcode'];

	}

	$products = array();

	foreach (db_items("SELECT id, name, short_description, title, price, taxable, tax_rate FROM products
		WHERE 1 = 1" . pg_catalog_bin_live_sql('products')) as $row) {

		$id = (int) $row['id'];

		$products[] = array(
			'id'       => $id,
			'code'     => $row['name'],
			'title'    => mp_remote_products_shop_name($row, false),
			'barcodes' => isset($barcodes[$id]) ? $barcodes[$id] : array(),
			'price'    => mp_product_price($row)
		);

	}

	return $products;

}

// The closest shop products to one remote row, for the picker: the matcher's
// candidates without the one-to-one rule, products matched elsewhere on the
// account left out.
function mp_remote_products_similar($account, $row, $limit = 12) {

	$taken = db_values("SELECT product_id FROM marketplace_product_map
		WHERE account_id = '" . e((int) $account['id']) . "'");

	$index = mp_match_index(mp_remote_products_shop_catalogue(), array_fill_keys(array_map('intval', (array) $taken), true));

	$found = mp_match_candidates(array(
		'code'    => $row['remote_code'],
		'barcode' => $row['barcode'],
		'title'   => $row['title'],
		'price'   => (int) $row['price']
	), $index, $limit, 15);

	return array_keys($found);

}

/* ------------------------------------------------------- comparing codes */

// A marketplace code is the seller's own text and is compared byte for byte:
// "ABC-1" and "abc-1" are two products there. marketplace_remote_products
// keeps the code in utf8mb4_bin; marketplace_product_map (2026.4.4) keeps it
// in the case- and accent-blind utf8mb4_unicode_ci, so every comparison with
// the map is made twice: once in the map column's own collation, which lets
// its index (idx_code) find the rows, and once on the bytes, which drops a
// twin. A plain = between the two columns would be decided in utf8mb4_bin
// and could not use the map's index.

// Whether two codes are the same code.
function mp_remote_products_same_code($a, $b) {

	return (string) $a === (string) $b;

}

// The remote rows whose code no map row of the account carries, exactly.
function mp_remote_products_unmatched_rows($rows, $mapped_codes) {

	$mapped = array();

	foreach ((array) $mapped_codes as $code) {

		$mapped[(string) $code] = true;

	}

	$left = array();

	foreach ((array) $rows as $row) {

		if (!isset($mapped[(string) $row['remote_code']])) {

			$left[] = $row;

		}

	}

	return $left;

}

// The WHERE fragment that finds the map rows carrying exactly $code.
function mp_remote_products_map_code_sql($code) {

	$code = e((string) $code);

	return "(marketplace_product_map.remote_code = '" . $code . "')"
		. " AND (CAST(marketplace_product_map.remote_code AS BINARY) = CAST('" . $code . "' AS BINARY))";

}

// The join condition between a map row and a remote row of the same code.
function mp_remote_products_code_join_sql() {

	return "(marketplace_product_map.account_id = marketplace_remote_products.account_id)
		  AND (marketplace_product_map.remote_code = marketplace_remote_products.remote_code COLLATE utf8mb4_unicode_ci)
		  AND (CAST(marketplace_product_map.remote_code AS BINARY) = CAST(marketplace_remote_products.remote_code AS BINARY))";

}

/* -------------------------------------------------------------- the rows */

// The SQL that says a remote row is matched on its account.
function mp_remote_products_matched_sql() {

	return "EXISTS (SELECT 1 FROM marketplace_product_map WHERE " . mp_remote_products_code_join_sql() . ")";

}

// The WHERE fragment of one of the screen's filters.
function mp_remote_products_filter_sql($filter) {

	$matched = mp_remote_products_matched_sql();

	switch ($filter) {

		case 'matched':
			return " AND " . $matched;

		case 'unmatched':
			return " AND NOT " . $matched . " AND (dismissed = '0') AND (suggested_product_id = '0')";

		case 'dismissed':
			return " AND NOT " . $matched . " AND (dismissed = '1')";

		case 'all':
			return '';

		default:
			return " AND NOT " . $matched . " AND (dismissed = '0') AND (suggested_product_id != '0')";

	}

}

// How many rows each filter holds, and how many suggestions are sure.
function mp_remote_products_counts($account_id) {

	$counts = array();

	$where = "WHERE (account_id = '" . e((int) $account_id) . "')";

	foreach (array('suggested', 'unmatched', 'matched', 'dismissed', 'all') as $filter) {

		$counts[$filter] = (int) db_value("SELECT COUNT(*) FROM marketplace_remote_products "
			. $where . mp_remote_products_filter_sql($filter));

	}

	$counts['sure'] = (int) db_value("SELECT COUNT(*) FROM marketplace_remote_products "
		. $where . mp_remote_products_filter_sql('suggested') . " AND (suggestion_sure = '1')");

	return $counts;

}

// One page of a filter's rows, with the shop product each one is matched or
// suggested to (matched_product_id, the product row under 'product').
function mp_remote_products_page($account_id, $filter, $search, $page, $per_page) {

	$where = "WHERE (account_id = '" . e((int) $account_id) . "')" . mp_remote_products_filter_sql($filter);

	$search = trim((string) $search);

	if ($search !== '') {

		$like = "'%" . e(escape_like($search)) . "%'";

		$where .= " AND ((title LIKE " . $like . ") OR (remote_code LIKE " . $like . ") OR (barcode LIKE " . $like . "))";

	}

	$order = ($filter === 'suggested' || $filter === '')
		? 'suggestion_sure DESC, suggestion_score DESC, title ASC, id ASC'
		: 'title ASC, id ASC';

	$total = (int) db_value("SELECT COUNT(*) FROM marketplace_remote_products " . $where);

	$rows = db_items("SELECT marketplace_remote_products.*,
			(SELECT MIN(product_id) FROM marketplace_product_map
				WHERE " . mp_remote_products_code_join_sql() . ") AS matched_product_id
		FROM marketplace_remote_products " . $where . "
		ORDER BY " . $order . "
		LIMIT " . (max(0, (int) $page - 1) * (int) $per_page) . ", " . (int) $per_page);

	$ids = array();

	foreach ($rows as $row) {

		foreach (array('matched_product_id', 'suggested_product_id') as $column) {

			if ((int) $row[$column] > 0) {

				$ids[] = (int) $row[$column];

			}

		}

	}

	$products = mp_remote_products_shop_rows($ids);

	foreach ($rows as $i => $row) {

		$product_id = (int) $row['matched_product_id'] ?: (int) $row['suggested_product_id'];

		$rows[$i]['product'] = isset($products[$product_id]) ? $products[$product_id] : null;

	}

	return array('rows' => $rows, 'total' => $total);

}

// A shop product's name as people read it: the short description, which is
// the name the shop shows, else the page title. With $code_last the code
// (products.name) stands in when both are empty.
function mp_remote_products_shop_name($product, $code_last = true) {

	foreach (array('short_description', 'title') as $column) {

		$name = isset($product[$column]) ? trim(html_entity_decode(strip_tags((string) $product[$column]), ENT_QUOTES, 'UTF-8')) : '';

		if ($name !== '') {

			return $name;

		}

	}

	return $code_last && isset($product['name']) ? (string) $product['name'] : '';

}

// Shop products by id, with what the screen shows of each.
function mp_remote_products_shop_rows($ids) {

	$ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

	if (!$ids) {

		return array();

	}

	$products = array();

	foreach (db_items("SELECT id, name, short_description, title, image_name, price, taxable, tax_rate, inventory,
			inventory_quantity, out_of_stock, enabled
		FROM products WHERE id IN (" . implode(',', $ids) . ")") as $row) {

		$products[(int) $row['id']] = $row;

	}

	return $products;

}

// Shop products for the picker's search box: by code, title or barcode, the
// Recycle Bin left out.
function mp_remote_products_search_shop($search, $limit = 24) {

	$search = trim((string) $search);

	$where = "WHERE 1 = 1" . pg_catalog_bin_live_sql('products');

	if ($search !== '') {

		$like = "'%" . e(escape_like($search)) . "%'";

		$where .= " AND ((products.name LIKE " . $like . ") OR (products.short_description LIKE " . $like . ")
			OR (products.title LIKE " . $like . ")
			OR (products.id IN (SELECT product_id FROM product_barcodes WHERE barcode LIKE " . $like . ")))";

	}

	return db_values("SELECT products.id FROM products " . $where . "
		ORDER BY products.short_description ASC, products.id ASC
		LIMIT " . (int) $limit);

}

// One remote row of an account, or null.
function mp_remote_product($account_id, $id) {

	$row = db_item("SELECT * FROM marketplace_remote_products
		WHERE (id = '" . e((int) $id) . "') AND (account_id = '" . e((int) $account_id) . "')");

	return $row ? $row : null;

}

/* ----------------------------------------------------------------- answers */

// Match a remote row to a shop product. Answers mp_ok() or mp_error() with the
// reason it was refused.
function mp_remote_products_accept($account, $id, $product_id) {

	$account_id = (int) $account['id'];

	$row = mp_remote_product($account_id, $id);

	if (!$row) {

		return mp_error(lang('That marketplace product is no longer in the list. Read the products again.'));

	}

	$product = db_item("SELECT id, name FROM products
		WHERE (id = '" . e((int) $product_id) . "')" . pg_catalog_bin_live_sql('products'));

	if (!$product) {

		return mp_error(lang('That product was not found. Choose one from the list.'));

	}

	$mapped = db_item("SELECT id, remote_code FROM marketplace_product_map
		WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e((int) $product['id']) . "')");

	if ($mapped) {

		if (mp_remote_products_same_code($mapped['remote_code'], $row['remote_code'])) {

			return mp_ok(array('product_id' => (int) $product['id']));

		}

		return mp_error(lang(array(
			'string' => '{var:1} is already matched to {var:2} on this marketplace.',
			'vars'   => array($product['name'], $mapped['remote_code'])
		)));

	}

	// The code taken by another product, a deleted one included: its row
	// still sends to that code until it is removed.
	$other = db_item("SELECT marketplace_product_map.product_id, products.name
		FROM marketplace_product_map
		LEFT JOIN products ON products.id = marketplace_product_map.product_id
		WHERE (marketplace_product_map.account_id = '" . e($account_id) . "')
		  AND " . mp_remote_products_map_code_sql($row['remote_code']) . "
		LIMIT 1");

	if ($other) {

		return mp_error(lang(array(
			'string' => '{var:1} is already matched to {var:2} on this marketplace.',
			'vars'   => array($row['remote_code'], ($other['name'] !== null) ? $other['name'] : '#' . (int) $other['product_id'])
		)));

	}

	// IGNORE: a second click that arrives first leaves the pair already
	// written, which is the answer the operator wanted anyway.
	db("INSERT IGNORE INTO marketplace_product_map
		(account_id, product_id, remote_code, remote_id, status, created_timestamp)
		VALUES ('" . e($account_id) . "', '" . e((int) $product['id']) . "', '" . e($row['remote_code']) . "',
			'" . e($row['remote_id']) . "', 'active', UNIX_TIMESTAMP())");

	// The product is taken now: another row that was offered it is offered
	// nothing until the suggestions are worked out again.
	db("UPDATE marketplace_remote_products
		SET suggested_product_id = '0', suggestion_score = '0', suggestion_reason = '', suggestion_sure = '0'
		WHERE (account_id = '" . e($account_id) . "') AND (suggested_product_id = '" . e((int) $product['id']) . "')
		  AND (id != '" . e((int) $row['id']) . "')");

	db("UPDATE marketplace_remote_products SET dismissed = '0' WHERE id = '" . e((int) $row['id']) . "'");

	return mp_ok(array('product_id' => (int) $product['id']));

}

// Accept the current suggestion of each of these rows (all sure ones when
// $ids is null). Answers matched and refused (id => reason).
function mp_remote_products_accept_suggestions($account, $ids = null) {

	$account_id = (int) $account['id'];

	$where = "WHERE (account_id = '" . e($account_id) . "')" . mp_remote_products_filter_sql('suggested');

	if ($ids === null) {

		$where .= " AND (suggestion_sure = '1')";

	} else {

		$ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

		if (!$ids) {

			return array('matched' => 0, 'refused' => array());

		}

		$where .= " AND (id IN (" . implode(',', $ids) . "))";

	}

	$matched = 0;

	$refused = array();

	foreach (db_items("SELECT id, suggested_product_id FROM marketplace_remote_products " . $where) as $row) {

		$result = mp_remote_products_accept($account, (int) $row['id'], (int) $row['suggested_product_id']);

		if ($result['ok']) {

			$matched++;

		} else {

			$refused[(int) $row['id']] = $result['error'];

		}

	}

	return array('matched' => $matched, 'refused' => $refused);

}

// Undo a match: the product map rows that carry this row's code go.
function mp_remote_products_unmatch($account, $id) {

	$row = mp_remote_product((int) $account['id'], $id);

	if (!$row) {

		return mp_error(lang('That marketplace product is no longer in the list. Read the products again.'));

	}

	db("DELETE FROM marketplace_product_map
		WHERE (account_id = '" . e((int) $account['id']) . "') AND " . mp_remote_products_map_code_sql($row['remote_code']));

	return mp_ok();

}

// The operator's answer that the shop does not sell this one (or, with
// $dismissed false, that it is worth a look after all).
function mp_remote_products_dismiss($account, $id, $dismissed = true) {

	$row = mp_remote_product((int) $account['id'], $id);

	if (!$row) {

		return mp_error(lang('That marketplace product is no longer in the list. Read the products again.'));

	}

	db("UPDATE marketplace_remote_products SET dismissed = '" . ($dismissed ? '1' : '0') . "'
		WHERE id = '" . e((int) $row['id']) . "'");

	return mp_ok();

}
