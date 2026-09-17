<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Opening a listing on a marketplace.
//
// Sending stock and price needs a code and nothing else. Opening the listing in
// the first place needs the marketplace's own taxonomy - which of their
// categories the article belongs to, and then whatever that category demands -
// and none of it exists in this shop's catalogue, because it is theirs.
//
// The unit is the article, not the product. A product group here is the shirt;
// its products are the sizes. The category and the mandatory attributes belong
// to the shirt, so they are mapped once per group and every variant inherits
// them; only the attribute marked as a variant differs between the rows, and
// that comes from the product's own attributes.
//
// A product that belongs to no article is not listed from here yet: a category
// would have to be chosen per product rather than per article, which is a
// different screen.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');

/* ------------------------------------------------------------- the taxonomy */

// Fetch and store the marketplace's category tree.
//
// Cached against the provider rather than the account: two shops on the same
// marketplace see the same categories, and this is a large download. Rows are
// replaced rather than added to, so a category they retired stops being
// offered.
function mp_categories_refresh($account) {

	$provider = (string) $account['provider'];

	if (!mp_can($provider, 'category_tree')) {

		return mp_error(lang(array('string' => '{var:1} cannot do this.', 'vars' => array(mp_provider_name($provider)))));

	}

	$result = mp_call($provider, 'category_tree', array($account));

	if (!$result['ok']) {

		return $result;

	}

	$rows = (array) $result['data']['categories'];

	$now = time();

	foreach ($rows as $row) {

		// One statement per category, which is thousands of them - but this
		// runs when an operator presses a button, once every few months, and a
		// bulk INSERT of that size is a single statement that either lands or
		// does not.
		db("INSERT INTO marketplace_categories
			(provider, remote_id, parent_id, name, path, is_leaf, updated_timestamp)
			VALUES (
				'" . e($provider) . "',
				'" . e(mb_substr((string)$row['remote_id'], 0, 64)) . "',
				'" . e(mb_substr((string)$row['parent_id'], 0, 64)) . "',
				'" . e(mb_substr((string)$row['name'], 0, 255)) . "',
				'" . e(mb_substr((string)$row['path'], 0, 500)) . "',
				'" . ((int)$row['is_leaf'] ? '1' : '0') . "',
				'" . e($now) . "')
			ON DUPLICATE KEY UPDATE
				parent_id = VALUES(parent_id),
				name      = VALUES(name),
				path      = VALUES(path),
				is_leaf   = VALUES(is_leaf),
				updated_timestamp = VALUES(updated_timestamp)");

	}

	// Anything not seen in this pass is gone from their tree.
	db("DELETE FROM marketplace_categories
		WHERE (provider = '" . e($provider) . "') AND (updated_timestamp < '" . e($now) . "')");

	db("UPDATE marketplace_accounts SET categories_fetched = UNIX_TIMESTAMP()
		WHERE id = '" . e((int)$account['id']) . "'");

	return mp_ok(array('count' => count($rows)));

}

// What a category demands, from the cache or from them.
//
// Fetched on demand and only for a category somebody picked. Asking for every
// category's attributes would be tens of thousands of calls against a limit
// counted per minute, to answer a question nobody asked.
function mp_category_attributes($account, $category_id, $force = false) {

	$provider = (string) $account['provider'];

	$category_id = (string) $category_id;

	if (!$force) {

		$cached = (array) db_items("SELECT * FROM marketplace_category_attributes
			WHERE (provider = '" . e($provider) . "') AND (category_id = '" . e($category_id) . "')
			ORDER BY sort_order ASC, id ASC");

		if ($cached) {

			return mp_ok(array('attributes' => mp_category_attributes_decode($cached)));

		}

	}

	if (!mp_can($provider, 'category_attributes')) {

		return mp_ok(array('attributes' => array()));

	}

	$result = mp_call($provider, 'category_attributes', array($account, $category_id));

	if (!$result['ok']) {

		return $result;

	}

	$rows = (array) $result['data']['attributes'];

	$now = time();

	foreach ($rows as $row) {

		db("INSERT INTO marketplace_category_attributes
			(provider, category_id, attribute_id, name, mandatory, is_variant,
			 custom_allowed, sort_order, values_json, updated_timestamp)
			VALUES (
				'" . e($provider) . "',
				'" . e($category_id) . "',
				'" . e(mb_substr((string)$row['attribute_id'], 0, 64)) . "',
				'" . e(mb_substr((string)$row['name'], 0, 255)) . "',
				'" . ((int)$row['mandatory'] ? '1' : '0') . "',
				'" . ((int)$row['is_variant'] ? '1' : '0') . "',
				'" . ((int)$row['custom_allowed'] ? '1' : '0') . "',
				'" . e((int)$row['sort_order']) . "',
				'" . e(json_encode($row['values'])) . "',
				'" . e($now) . "')
			ON DUPLICATE KEY UPDATE
				name = VALUES(name), mandatory = VALUES(mandatory),
				is_variant = VALUES(is_variant), custom_allowed = VALUES(custom_allowed),
				sort_order = VALUES(sort_order), values_json = VALUES(values_json),
				updated_timestamp = VALUES(updated_timestamp)");

	}

	$stored = (array) db_items("SELECT * FROM marketplace_category_attributes
		WHERE (provider = '" . e($provider) . "') AND (category_id = '" . e($category_id) . "')
		ORDER BY sort_order ASC, id ASC");

	return mp_ok(array('attributes' => mp_category_attributes_decode($stored)));

}

function mp_category_attributes_decode($rows) {

	foreach ($rows as $index => $row) {

		$values = json_decode((string)$row['values_json'], true);

		$rows[$index]['values'] = is_array($values) ? $values : array();

	}

	return $rows;

}

/* --------------------------------------------------------------- the article */

// The articles this shop could list.
//
// A product group is two different things in this catalogue and only one of
// them is an article. A group that carries attributes is the article - the
// chair, whose products are its colours. A group that carries none is a shelf
// in the shop's navigation, and the products under it have nothing to do with
// each other; offering one of those as something to list produced an "article"
// of twenty-five unrelated products, all of which would have gone to the
// marketplace as options of one listing.
//
// So the attribute cross-reference is what decides, the same test the API's
// product-groups endpoint calls variants_only.
function mp_listing_articles($account_id, $limit = 300) {

	$account_id = (int) $account_id;

	$groups = (array) db_items("SELECT product_groups.id, product_groups.name, product_groups.title,
			COUNT(products_groups_xref.product) AS variants,
			marketplace_category_map.remote_category_id, marketplace_category_map.attributes_json,
			marketplace_category_map.last_error,
			marketplace_categories.path AS category_path
		FROM product_groups
		INNER JOIN products_groups_xref ON products_groups_xref.product_group = product_groups.id
		LEFT JOIN marketplace_category_map
			ON  (marketplace_category_map.group_id = product_groups.id)
			AND (marketplace_category_map.account_id = '" . e($account_id) . "')
		LEFT JOIN marketplace_categories
			ON  (marketplace_categories.remote_id = marketplace_category_map.remote_category_id)
			AND (marketplace_categories.provider = (SELECT provider FROM marketplace_accounts WHERE id = '" . e($account_id) . "'))
		WHERE product_groups.id IN (SELECT product_group_id FROM product_groups_attributes_xref)
		GROUP BY product_groups.id
		ORDER BY product_groups.name ASC
		LIMIT " . (int)$limit);

	return $groups;

}

// One article's rows, ready for the connector.
//
// Answers array('ok', 'items', 'error'). Refuses rather than sending something
// the marketplace will reject per SKU hours later: a missing category, a
// mandatory attribute nobody filled in, or a product with no picture are all
// things this shop can see before the call is made.
function mp_listing_items($account, $group_id) {

	$account_id = (int) $account['id'];

	$group_id = (int) $group_id;

	$map = db_item("SELECT * FROM marketplace_category_map
		WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

	if (!$map || $map['remote_category_id'] === '') {

		return array('ok' => false, 'items' => array(),
			'error' => lang('This article has no marketplace category yet.'));

	}

	$group = db_item("SELECT id, name, title, short_description, full_description
		FROM product_groups WHERE id = '" . e($group_id) . "'");

	if (!$group) {

		return array('ok' => false, 'items' => array(), 'error' => lang('That article no longer exists.'));

	}

	$chosen = json_decode((string)$map['attributes_json'], true);

	$chosen = is_array($chosen) ? $chosen : array();

	$definitions = mp_category_attributes($account, $map['remote_category_id']);

	$definitions = $definitions['ok'] ? (array)$definitions['data']['attributes'] : array();

	// Everything the category insists on, that is not something the variants
	// themselves answer, has to have been filled in on the mapping screen.
	foreach ($definitions as $definition) {

		if (empty($definition['mandatory']) || !empty($definition['is_variant'])) {

			continue;

		}

		$id = (string) $definition['attribute_id'];

		if (!isset($chosen[$id]) || trim((string)$chosen[$id]) === '') {

			return array('ok' => false, 'items' => array(), 'error' => lang(array(
				'string' => 'The marketplace requires {var:1} for this category, and it has not been filled in.',
				'vars'   => array((string)$definition['name'])
			)));

		}

	}

	$products = (array) db_items("SELECT products.id, products.name, products.title,
			products.short_description, products.full_description, products.price,
			products.inventory, products.inventory_quantity, products.out_of_stock,
			products.enabled, products.tax_rate, products.image_name
		FROM products
		INNER JOIN products_groups_xref ON products_groups_xref.product = products.id
		WHERE products_groups_xref.product_group = '" . e($group_id) . "'
		ORDER BY products.id ASC");

	if (!$products) {

		return array('ok' => false, 'items' => array(), 'error' => lang('This article has no products.'));

	}

	$items = array();

	foreach ($products as $product) {

		$images = mp_listing_images((int)$product['id'], $product['image_name']);

		if (!$images) {

			return array('ok' => false, 'items' => array(), 'error' => lang(array(
				'string' => '{var:1} has no picture. The marketplace will not list a product without one.',
				'vars'   => array((string)$product['name'])
			)));

		}

		$attributes = mp_listing_attributes($definitions, $chosen, (int)$product['id']);

		if ($attributes === null) {

			return array('ok' => false, 'items' => array(), 'error' => lang(array(
				'string' => '{var:1} does not carry the option this category uses to tell the variants apart.',
				'vars'   => array((string)$product['name'])
			)));

		}

		// A listing needs a quantity, and a product that does not track stock
		// has none. Sending zero would open the listing already sold out and it
		// would stay that way, because nothing here would ever send another
		// number; inventing one would be putting a figure the shop never
		// counted in front of a buyer. So it is refused, and the operator is
		// told the one thing that fixes it.
		if (empty($product['inventory'])) {

			return array('ok' => false, 'items' => array(), 'error' => lang(array(
				'string' => '{var:1} does not track stock. A marketplace listing needs a quantity - turn stock tracking on for it first.',
				'vars'   => array((string)$product['name'])
			)));

		}

		$quantity = max(0, (int)$product['inventory_quantity']);

		if (empty($product['enabled']) || !empty($product['out_of_stock'])) {

			$quantity = 0;

		}

		$description = ($product['full_description'] !== '')
			? $product['full_description']
			: (($group['full_description'] !== '') ? $group['full_description'] : (string)$product['short_description']);

		$items[] = array(
			'product_id'  => (int)$product['id'],
			'code'        => (string)$product['name'],
			'group_code'  => (string)$group['name'],
			'title'       => ($product['title'] !== '') ? (string)$product['title'] : (string)$group['title'],
			'description' => (string)$description,
			'category_id' => (string)$map['remote_category_id'],
			'price_major' => mp_price_major((int)$product['price']),
			'quantity'    => $quantity,
			'tax_rate'    => ($product['tax_rate'] === null) ? null : (float)$product['tax_rate'],
			'images'      => $images,
			'attributes'  => $attributes
		);

	}

	return array('ok' => true, 'items' => $items, 'error' => '');

}

// A product's pictures as addresses the marketplace can fetch.
//
// They download the file themselves, so a path is no use - it has to be a URL
// this site serves, and it has to be https because they refuse anything else.
//
// The same base the API's product reads build (includes/api/resources/products.php),
// and encode_url_path() for the same reason: a file called "kirmizi gomlek.jpg"
// is a valid file name here and an invalid URL out there.
function mp_listing_images($product_id, $image_name) {

	$base = (defined('URL_SCHEME') ? URL_SCHEME : 'https://')
		. (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '')
		. (defined('PATH') ? PATH : '/');

	$names = array();

	$image_name = trim((string)$image_name);

	if ($image_name !== '') {

		$names[] = $image_name;

	}

	// No ORDER BY: products_images_xref has no id and no sort column - it is two
	// columns and nothing else - so the gallery comes back in whatever order the
	// rows were written, which is the order they were added. The main image is
	// already first, and that is the one every marketplace treats as the listing
	// photo.
	foreach ((array) db_items("SELECT file_name FROM products_images_xref
		WHERE product = '" . e((int)$product_id) . "'") as $row) {

		$name = trim((string)$row['file_name']);

		if (($name !== '') && !in_array($name, $names, true)) {

			$names[] = $name;

		}

	}

	$urls = array();

	foreach ($names as $name) {

		$urls[] = $base . (function_exists('encode_url_path') ? encode_url_path($name) : rawurlencode($name));

	}

	return $urls;

}

// The attribute values one product sends.
//
// Two sources, and the split is the whole reason the mapping is per article:
// what the category demands of the article was answered once on the mapping
// screen, and what tells the variants apart is read from the product itself.
//
// Answers null when a variant attribute has no answer on this product, because
// that is not something to send half of - the marketplace would put every
// variant on one page as the same thing.
function mp_listing_attributes($definitions, $chosen, $product_id) {

	$own = array();

	foreach ((array) db_items("SELECT product_attributes.name AS attribute_name,
			product_attribute_options.label AS option_label
		FROM products_attributes_xref
		INNER JOIN product_attributes ON product_attributes.id = products_attributes_xref.attribute_id
		INNER JOIN product_attribute_options ON product_attribute_options.id = products_attributes_xref.option_id
		WHERE products_attributes_xref.product_id = '" . e((int)$product_id) . "'") as $row) {

		$own[] = array('name' => (string)$row['attribute_name'], 'value' => (string)$row['option_label']);

	}

	$attributes = array();

	foreach ((array)$definitions as $definition) {

		$id = (string) $definition['attribute_id'];

		if (!empty($definition['is_variant'])) {

			$value = mp_listing_variant_value($definition, $own);

			if ($value === null) {

				if (!empty($definition['mandatory'])) {

					return null;

				}

				continue;

			}

			$attributes[] = $value;

			continue;

		}

		if (!isset($chosen[$id]) || trim((string)$chosen[$id]) === '') {

			continue;

		}

		$attributes[] = mp_listing_value($definition, (string)$chosen[$id]);

	}

	return $attributes;

}

// Match one of this product's own options to the marketplace's variant attribute.
//
// By name first - a shop whose attribute is called "Renk" and a category whose
// variant attribute is called "Renk" are talking about the same thing - and
// then by the value alone, because a shop that calls it "Colour" still sends
// "Kırmızı", and that is what the marketplace matches on anyway.
function mp_listing_variant_value($definition, $own) {

	$wanted = mb_strtolower(trim((string)$definition['name']));

	foreach ($own as $option) {

		if (mb_strtolower(trim($option['name'])) === $wanted) {

			return mp_listing_value($definition, $option['value']);

		}

	}

	// No attribute of that name here. If exactly one of this product's options
	// has a value the category knows, that is the one.
	foreach ($own as $option) {

		foreach ((array)$definition['values'] as $value) {

			if (mb_strtolower(trim((string)$value['value'])) === mb_strtolower(trim($option['value']))) {

				return array('id' => (string)$definition['attribute_id'], 'value_id' => (string)$value['id'], 'custom_value' => '');

			}

		}

	}

	return null;

}

// One value, as an id from their list where it matches and as free text where
// the category allows it. A category that allows neither refuses the SKU, which
// is better than guessing an id.
function mp_listing_value($definition, $text) {

	$text = trim((string)$text);

	foreach ((array)$definition['values'] as $value) {

		if ((string)$value['id'] === $text) {

			return array('id' => (string)$definition['attribute_id'], 'value_id' => (string)$value['id'], 'custom_value' => '');

		}

		if (mb_strtolower((string)$value['value']) === mb_strtolower($text)) {

			return array('id' => (string)$definition['attribute_id'], 'value_id' => (string)$value['id'], 'custom_value' => '');

		}

	}

	return array('id' => (string)$definition['attribute_id'], 'value_id' => '', 'custom_value' => $text);

}

/* ---------------------------------------------------------------- sending */

// Put an article on the queue to be listed.
//
// The same queue the stock pushes use, under a different operation, so it gets
// the same batching, the same lease and the same task polling. One row per
// product, because that is what the queue is keyed on; they are gathered back
// into one call when the batch goes out.
function mp_listing_enqueue($account_id, $group_id, $priority = 3) {

	$account_id = (int) $account_id;

	$mapped = db_value("SELECT id FROM marketplace_category_map
		WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e((int)$group_id) . "')
		  AND (remote_category_id != '')");

	if (!$mapped) {

		return 0;

	}

	$products = (array) db_items("SELECT products.id
		FROM products
		INNER JOIN products_groups_xref ON products_groups_xref.product = products.id
		WHERE products_groups_xref.product_group = '" . e((int)$group_id) . "'");

	$written = 0;

	foreach ($products as $row) {

		$product_id = (int) $row['id'];

		$waiting = db_value("SELECT id FROM marketplace_sync_queue
			WHERE (account_id = '" . e($account_id) . "')
			  AND (product_id = '" . e($product_id) . "')
			  AND (operation = 'create')
			  AND (status = 'waiting')
			LIMIT 1");

		if ($waiting) {

			continue;

		}

		db("INSERT INTO marketplace_sync_queue
			(account_id, product_id, operation, priority, status, run_after, created_timestamp, updated_timestamp)
			VALUES ('" . e($account_id) . "', '" . e($product_id) . "', 'create',
				'" . e((int)$priority) . "', 'waiting', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");

		$written++;

	}

	return $written;

}

// Which articles have listing work waiting, for the dispatcher.
//
// Joined against the category mapping, not just against the groups a product
// sits in. A product belongs to every group above it in the shop tree, so
// queueing four gift cards produced three "articles" - the one somebody mapped
// and two shelves nobody did. The dispatcher would then have tried to list the
// shelves and marked the rows failed for a mapping the operator was never asked
// for.
function mp_listing_pending_groups($account_id) {

	$account_id = (int) $account_id;

	return (array) db_items("SELECT DISTINCT marketplace_category_map.group_id
		FROM marketplace_sync_queue
		INNER JOIN products_groups_xref ON products_groups_xref.product = marketplace_sync_queue.product_id
		INNER JOIN marketplace_category_map
			ON  (marketplace_category_map.group_id = products_groups_xref.product_group)
			AND (marketplace_category_map.account_id = '" . e($account_id) . "')
		WHERE (marketplace_sync_queue.account_id = '" . e($account_id) . "')
		  AND (marketplace_sync_queue.operation = 'create')
		  AND (marketplace_sync_queue.status = 'waiting')
		  AND (marketplace_sync_queue.run_after <= UNIX_TIMESTAMP())
		  AND (marketplace_category_map.remote_category_id != '')");

}
