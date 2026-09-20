<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Reading the catalogue and changing it.
//
// Three things are deliberately different from the surface this replaces.
//
// A product is addressed by its id or its barcode, never by its name. The old
// endpoint looked products up with WHERE name = ..., so two products sharing a
// name updated whichever one the database happened to return first, and renaming
// a product in the panel silently broke every integration pointing at it.
//
// The response is an explicit list of fields rather than SELECT *. Internal
// columns - accounting identifiers, the recycle bin flag, the search score -
// used to be handed out with everything else, and any column added to the table
// changed the shape of the response without anyone deciding that it should.
//
// Stock is not writable here. It has its own endpoint because it is the one
// field two systems change at the same moment, and it has to be adjusted by the
// database rather than read into PHP and written back.

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// The shape of a product on the wire. One place, so the list and the single
// reads cannot drift apart.
function api_product_present($row, $extras = array(), $with_issues = false) {

	$id = (int)$row['id'];

	return array(
		'id'                => (int)$row['id'],
		// The merchant SKU is the product id.
		//
		// The catalogue has no separate SKU column - the id is what the shop
		// identifies a product by - and a marketplace connector has to send
		// something in that field. Saying so here, instead of leaving the
		// integrator to work it out, is the whole point of carrying it twice.
		'sku'               => (string)$row['id'],
		'name'              => $row['name'],
		// The barcode is a different thing from the SKU: an EAN or UPC that
		// identifies the article in the world, not in this shop. Optional, and
		// null on most products.
		'barcode'           => (isset($row['barcode']) && $row['barcode'] !== '') ? $row['barcode'] : null,
		'enabled'           => ((int)$row['enabled'] === 1),
		'price'             => api_money($row['price']),
		'title'             => $row['title'],
		'short_description' => $row['short_description'],
		'brand'             => $row['brand'],
		'gtin'              => $row['gtin'],
		'mpn'               => $row['mpn'],
		'images'            => isset($extras['images'][$id]) ? $extras['images'][$id] : array(),
		// What makes this row a variant rather than a product on its own.
		// Empty on a product that has no attributes, which is most catalogues.
		'attributes'        => isset($extras['attributes'][$id]) ? $extras['attributes'][$id] : array(),
		// The groups this product sits in. A marketplace listing needs the
		// parent that gathers the variants, and this is it.
		'group_ids'         => isset($extras['groups'][$id]) ? $extras['groups'][$id] : array(),
		'taxable'           => ((int)$row['taxable'] === 1),
		// The article's own rate, as a percentage. Null means the product has
		// no rate of its own and the tax zone of the delivery address decides -
		// meta.ecommerce.default_tax_rate is what that comes to here. Zero is a
		// different answer: zero-rated, taxed at 0% wherever tax applies.
		'tax_rate'          => ($row['tax_rate'] === null) ? null : (float)$row['tax_rate'],
		'shippable'         => ((int)$row['shippable'] === 1),
		'free_shipping'     => ((int)$row['free_shipping'] === 1),
		'weight'            => ($row['weight'] > 0) ? (float)$row['weight'] : null,
		'inventory' => array(
			'tracked'      => ((int)$row['inventory'] === 1),
			'quantity'     => (int)$row['inventory_quantity'],
			'out_of_stock' => ((int)$row['out_of_stock'] === 1)
		),
		'updated_at'      => api_time($row['timestamp']),
		'updated_at_unix' => (int)$row['timestamp'],
		// What the site already knows about how this product reads to a search
		// engine. Nothing is calculated here; the findings are only carried on
		// the single product endpoint, because they are a query per row.
		'seo'             => api_seo_block('product', $id, $row, $with_issues)
	);

}

// Everything that hangs off a product, for a whole page of them at once.
//
// Three queries for the page instead of three per row. The list is allowed to
// return 250 products, and a per-row lookup for images, attributes and groups
// would be 750 round trips to render one page - the classic N+1, and the reason
// a listing endpoint that looks fine on a demo catalogue falls over on a real
// one.
function api_product_extras($ids) {

	$extras = array('images' => array(), 'attributes' => array(), 'groups' => array());

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $extras;

	}

	$in = implode(',', $ids);

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	// Images. The main one is on the product row and comes first, because that
	// is the photo every marketplace treats as the listing image; the gallery
	// follows in the order the shop put it in.
	$main = api_rows("SELECT id, image_name FROM products WHERE id IN (" . $in . ") AND image_name != ''");

	foreach ($main as $row) {

		$extras['images'][(int)$row['id']] = array($base . encode_url_path($row['image_name']));

	}

	$gallery = api_rows("SELECT product, file_name FROM products_images_xref
		WHERE product IN (" . $in . ") AND file_name != ''");

	foreach ($gallery as $row) {

		$key = (int)$row['product'];

		if (!isset($extras['images'][$key])) { $extras['images'][$key] = array(); }

		$url = $base . encode_url_path($row['file_name']);

		if (!in_array($url, $extras['images'][$key], true)) {

			$extras['images'][$key][] = $url;

		}

	}

	// Attributes. Each product row in this catalogue is one variant, and these
	// are the choices that variant stands for - Size: Large, Colour: Red. The
	// attribute carries both a name and a label because the shop uses one
	// internally and shows the other.
	$attributes = api_rows("SELECT products_attributes_xref.product_id,
			product_attributes.id AS attribute_id,
			product_attributes.name AS attribute_name,
			product_attributes.label AS attribute_label,
			product_attribute_options.id AS option_id,
			product_attribute_options.label AS option_label
		FROM products_attributes_xref
		INNER JOIN product_attributes
			ON product_attributes.id = products_attributes_xref.attribute_id
		INNER JOIN product_attribute_options
			ON product_attribute_options.id = products_attributes_xref.option_id
		WHERE products_attributes_xref.product_id IN (" . $in . ")
		ORDER BY products_attributes_xref.product_id ASC, products_attributes_xref.sort_order ASC");

	foreach ($attributes as $row) {

		$key = (int)$row['product_id'];

		if (!isset($extras['attributes'][$key])) { $extras['attributes'][$key] = array(); }

		$extras['attributes'][$key][] = array(
			'attribute_id' => (int)$row['attribute_id'],
			'name'         => $row['attribute_name'],
			'label'        => $row['attribute_label'],
			'option_id'    => (int)$row['option_id'],
			'option'       => $row['option_label']
		);

	}

	$groups = api_rows("SELECT product, product_group FROM products_groups_xref
		WHERE product IN (" . $in . ")
		ORDER BY product ASC, sort_order ASC");

	foreach ($groups as $row) {

		$key = (int)$row['product'];

		if (!isset($extras['groups'][$key])) { $extras['groups'][$key] = array(); }

		$extras['groups'][$key][] = (int)$row['product_group'];

	}

	return $extras;

}

// The columns every product read asks for, with the barcode pulled in as the
// sku. A subquery rather than a join, because product_barcodes is unique on the
// barcode and not on the product: a product carrying two barcodes would come
// back as two products through a join, which would break the row counts and the
// cursor along with them.
function api_product_select() {

	return "SELECT products.id, products.name, products.enabled, products.price,
			products.title, products.short_description, products.brand,
			products.gtin, products.mpn, products.image_name, products.taxable,
			products.shippable, products.free_shipping, products.weight,
			products.inventory, products.inventory_quantity, products.out_of_stock,
			products.tax_rate,
			products.timestamp,
			" . api_seo_columns('products.') . "
			(SELECT barcode FROM product_barcodes
			  WHERE product_barcodes.product_id = products.id
			  ORDER BY product_barcodes.id ASC LIMIT 1) AS barcode
		FROM products";

}

function api_products_list($params) {

	$where = array();

	// The incremental sync filter. Everything else on this endpoint is a
	// convenience; this is the one an integration actually runs on a schedule.
	if (isset($params['updated_since'])) {

		$where[] = "products.timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	// sku is the product id, so it is looked up as one. A non-numeric sku cannot
	// match anything rather than being cast to zero and matching nothing
	// silently - the answer is the same, but this way is deliberate.
	if (isset($params['sku']) && $params['sku'] !== '') {

		$where[] = ctype_digit((string)$params['sku'])
			? "products.id = '" . (int)$params['sku'] . "'"
			: "0";

	}

	if (isset($params['barcode']) && $params['barcode'] !== '') {

		$where[] = "products.id IN (SELECT product_id FROM product_barcodes
			WHERE barcode = '" . escape($params['barcode']) . "')";

	}

	if (isset($params['search']) && $params['search'] !== '') {

		$search = escape($params['search']);

		$where[] = "(products.name LIKE '%" . $search . "%' OR products.short_description LIKE '%" . $search . "%')";

	}

	if (isset($params['enabled'])) {

		$where[] = "products.enabled = '" . ($params['enabled'] ? '1' : '0') . "'";

	}

	if (isset($params['in_stock'])) {

		$where[] = "products.out_of_stock = '" . ($params['in_stock'] ? '0' : '1') . "'";

	}

	// The cursor is kept apart from the filters because the count, when it is
	// asked for, answers "how many rows match" and not "how many are left after
	// the point you have reached".
	//
	// Ordering is by timestamp then id and the pair is compared as a pair, so
	// rows written in the same second are still walked exactly once. Offset
	// paging over a catalogue that is being edited skips rows and repeats
	// others, which is how a sync quietly loses products.
	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(products.timestamp > '" . $cursor['v'] . "'
			OR (products.timestamp = '" . $cursor['v'] . "' AND products.id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	// One row more than asked for. Whether that extra row exists is what says
	// there is another page, without a second COUNT query.
	$rows = api_rows(api_product_select() . $where_sql . "
		ORDER BY products.timestamp ASC, products.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode($last['timestamp'], $last['id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM products"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$extras = api_product_extras(array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_product_present($row, $extras);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_products_get($params) {

	$row = api_row(api_product_select() . " WHERE products.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Product'));

	}

	api_ok(api_product_present($row, api_product_extras(array($row['id'])), true));

}

// Add a product.
//
// The row and its cross-references are written by pg_pb_create_product()
// (product_builder.php), the same function the product screen calls, so a
// product made here is the same shape as one made by hand: the SEO address
// name, the tag cloud keywords, the out-of-stock flag and the group and
// attribute links are all filled in by the code that owns them rather than by a
// second INSERT that would drift away from it.
//
// Three things this endpoint does that the screen does not have to:
//
//   The name is checked before the write. get_unique_name() renames a taken SKU
//   to NAME[1] and says nothing, which is a reasonable answer to an operator
//   looking at the screen and a terrible one to a marketplace that will look
//   for the SKU it sent and not find it.
//
//   The owner is passed in. The screens run inside a session and the builder
//   reads USER_ID from it; the API has no session, so the application owner is
//   written instead - the same attribution api_products_update() makes.
//
//   The form-field source is passed in as an empty array. The builder reads
//   the posted custom-form rows out of $_POST by default, and this request has
//   no form behind it.
function api_products_create($params) {

	require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/product_builder.php');

	$name = trim((string)$params['name']);

	if ($name === '') {

		api_fail_validation(lang(array('string' => '{var:1} is required', 'vars' => array('name'))), 'name');

	}

	// Names are compared the way the catalogue compares them, which is the
	// collation of the column - a case difference is not a different SKU.
	$taken = api_value("SELECT id FROM products WHERE name = '" . escape($name) . "' LIMIT 1");

	if ($taken) {

		api_fail(409, 'name_taken', lang(array(
			'string' => 'A product with the name {var:1} already exists (id {var:2}).',
			'vars'   => array($name, (int)$taken)
		)), 'name');

	}

	$app = api_current_app();

	// Defaults for a product nobody described in full. taxable and shippable
	// are on because that is what the product screen starts from and what a
	// physical article normally is; enabled is off so an import can be looked
	// at before the shop starts selling it.
	$product = array(
		'name'              => $name,
		'enabled'           => '0',
		'price'             => 0,
		'taxable'           => '1',
		'shippable'         => '1',
		'free_shipping'     => '0',
		'title'             => '',
		'short_description' => '',
		'meta_description'  => '',
		'brand'             => '',
		'gtin'              => '',
		'mpn'               => '',
		'keywords'          => '',
		'weight'            => 0,
		'length'            => 0,
		'width'             => 0,
		'height'            => 0,
		'inventory'         => '0',
		'inventory_quantity'=> 0,
		'user'              => (int)$app['owner']['id'],

		// NOT NULL text columns with no database default. MySQL is asked for a
		// permissive sql_mode elsewhere in the application and would fill these
		// in itself, but a row that only inserts because the mode is loose is a
		// row that stops inserting the day somebody tightens it.
		'full_description'      => '',
		'meta_keywords'         => '',
		'notes'                 => '',
		'details'               => '',
		'seo_analysis'          => '',
		'code'                  => '',
		'out_of_stock_message'  => '',
		'order_receipt_message' => '',
		'add_comment_message'   => '',
		'gift_card_email_body'  => '',
		'google_product_category' => ''
	);

	// The same whitelist the update uses, and the second gate for the same
	// reason: the validator has already refused an unknown parameter, and this
	// decides what a known one is allowed to touch.
	$text_columns = array(
		'title'             => 'title',
		'short_description' => 'short_description',
		'full_description'  => 'full_description',
		'meta_description'  => 'meta_description',
		'meta_keywords'     => 'meta_keywords',
		'keywords'          => 'keywords',
		'brand'             => 'brand',
		'gtin'              => 'gtin',
		'mpn'               => 'mpn',
		'notes'             => 'notes',
		'details'           => 'details',
		'google_product_category' => 'google_product_category'
	);

	$boolean_columns = array(
		'enabled'       => 'enabled',
		'taxable'       => 'taxable',
		'shippable'     => 'shippable',
		'free_shipping' => 'free_shipping',
		'inventory'     => 'inventory'
	);

	$decimal_columns = array(
		'weight' => 'weight',
		'length' => 'length',
		'width'  => 'width',
		'height' => 'height'
	);

	foreach ($text_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$product[$column] = $params[$param];

		}

	}

	foreach ($boolean_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$product[$column] = $params[$param] ? '1' : '0';

		}

	}

	foreach ($decimal_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$product[$column] = (float)$params[$param];

		}

	}

	if (array_key_exists('price', $params)) {

		if ((int)$params['price'] < 0) {

			api_fail_validation(lang('price cannot be negative.'), 'price');

		}

		$product['price'] = (int)$params['price'];

	}

	// Absent means "follow the tax zone", which is the column default. Present
	// and empty means the same thing said out loud, and both write NULL.
	if (array_key_exists('tax_rate', $params)) {

		$product['tax_rate'] = parse_tax_rate($params['tax_rate']);

	} else {

		$product['tax_rate'] = NULL;

	}

	if (array_key_exists('inventory_quantity', $params)) {

		$product['inventory_quantity'] = (int)$params['inventory_quantity'];

	}

	// The shop address the product is reachable at.
	//
	// pg_pb_create_product() falls back to the short description and then to
	// the name, which is the right order for the screen: an operator who has
	// not typed an address has usually typed a description. Through the API the
	// title is the better source - it is the customer facing name of the
	// article, where the name is a warehouse code - so it is offered first and
	// the builder's own chain still catches a product that has no title.
	if (($product['title'] !== '') && ($product['title'] !== null)) {

		$product['address_name'] = $product['title'];

	}

	$relations = array(
		'group_ids'   => api_products_create_group_ids($params),
		'attributes'  => api_products_create_attributes($params),
		// No form behind this request, so the builder is told not to look for
		// one rather than left to read whatever $_POST happens to hold.
		'submit_form' => array()
	);

	// Everything above is a check on what arrived; the next line puts a product
	// in the catalogue.
	api_dry_run_stop('created', 'product', array('name' => $name));

	$id = (int)pg_pb_create_product($product, $relations);

	if (!$id) {

		api_fail_server(lang('The product could not be created.'));

	}

	log_activity(lang(array(
		'string' => 'Product ({var:1}) was added through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_product_select() . " WHERE products.id = '" . $id . "' LIMIT 1");

	api_webhook_enqueue('product.created', array('id' => $id, 'name' => $row['name']));

	api_ok(api_product_present($row, api_product_extras(array($id))), 201);

}

// The groups a new product is filed under. Every id has to exist: a group that
// is not there is a mapping mistake on the integration side, and storing the
// link anyway would leave a product hanging off nothing.
function api_products_create_group_ids($params) {

	if (!array_key_exists('group_ids', $params)) {

		return array();

	}

	$ids = array();

	foreach ($params['group_ids'] as $raw) {

		$group_id = (int)$raw;

		if (!$group_id or in_array($group_id, $ids, true)) {

			continue;

		}

		if (!api_value("SELECT id FROM product_groups WHERE id = '" . $group_id . "' LIMIT 1")) {

			api_fail_validation(lang(array(
				'string' => 'There is no product group with the id {var:1}.',
				'vars'   => array($group_id)
			)), 'group_ids');

		}

		$ids[] = $group_id;

	}

	return $ids;

}

// What makes the new row one variant rather than another.
//
// The pair is checked, not just the two halves: an option belongs to exactly
// one attribute, and a product carrying option "Red" under attribute "Size"
// would list correctly in the panel and mean nothing on a marketplace. One
// value per attribute, because two answers to "what size is it" is not a thing
// the shop can act on.
function api_products_create_attributes($params) {

	if (!array_key_exists('attributes', $params)) {

		return array();

	}

	$pairs = array();
	$seen  = array();

	foreach ($params['attributes'] as $index => $entry) {

		if (!is_array($entry)) {

			api_fail_validation(lang('Each attribute must be an object of {attribute_id, option_id}.'), 'attributes');

		}

		$attribute_id = isset($entry['attribute_id']) ? (int)$entry['attribute_id'] : 0;
		$option_id    = isset($entry['option_id'])    ? (int)$entry['option_id']    : 0;

		if (!$attribute_id or !$option_id) {

			api_fail_validation(lang('Each attribute must be an object of {attribute_id, option_id}.'), 'attributes');

		}

		if (isset($seen[$attribute_id])) {

			api_fail_validation(lang(array(
				'string' => 'Attribute {var:1} was sent twice. A product carries one option per attribute.',
				'vars'   => array($attribute_id)
			)), 'attributes');

		}

		// product_attribute_options.product_attribute_id is the owning attribute -
		// the xref calls the same thing attribute_id, which is why this reads
		// like a typo and is not one.
		$belongs = api_value(
			"SELECT id FROM product_attribute_options
			WHERE (id = '" . $option_id . "') AND (product_attribute_id = '" . $attribute_id . "') LIMIT 1");

		if (!$belongs) {

			api_fail_validation(lang(array(
				'string' => 'Option {var:1} does not belong to attribute {var:2}.',
				'vars'   => array($option_id, $attribute_id)
			)), 'attributes');

		}

		$seen[$attribute_id] = true;

		$pairs[] = array('attribute_id' => $attribute_id, 'option_id' => $option_id);

	}

	return $pairs;

}

function api_products_update($params) {

	$id = (int)$params['id'];

	$existing = api_row("SELECT id, name FROM products WHERE id = '" . $id . "' LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Product'));

	}

	// Which parameter writes which column, and how. Anything not named here
	// cannot be written through this endpoint no matter what arrives in the
	// body - the validator has already refused unknown parameters, and this is
	// the second gate.
	$text_columns = array(
		'name'              => 'name',
		'title'             => 'title',
		'short_description' => 'short_description',
		'full_description'  => 'full_description',
		'meta_description'  => 'meta_description',
		'meta_keywords'     => 'meta_keywords',
		'keywords'          => 'keywords',
		'brand'             => 'brand',
		'gtin'              => 'gtin',
		'mpn'               => 'mpn',
		'notes'             => 'notes',
		'details'           => 'details',
		'google_product_category' => 'google_product_category'
	);

	$boolean_columns = array(
		'enabled'       => 'enabled',
		'taxable'       => 'taxable',
		'shippable'     => 'shippable',
		'free_shipping' => 'free_shipping'
	);

	$decimal_columns = array(
		'weight' => 'weight',
		'length' => 'length',
		'width'  => 'width',
		'height' => 'height'
	);

	$set = array();

	// tax_rate is nullable and an empty string clears it, which is how an
	// integration says "follow the tax zone" again. The other decimals have no
	// null state, so they stay in the plain group.
	if (array_key_exists('tax_rate', $params)) {

		$rate = parse_tax_rate($params['tax_rate']);

		$set[] = ($rate === NULL) ? "tax_rate = NULL" : "tax_rate = '" . escape($rate) . "'";

	}


	foreach ($text_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$set[] = $column . " = '" . escape($params[$param]) . "'";

		}

	}

	foreach ($boolean_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$set[] = $column . " = '" . ($params[$param] ? '1' : '0') . "'";

		}

	}

	foreach ($decimal_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$set[] = $column . " = '" . (float)$params[$param] . "'";

		}

	}

	if (array_key_exists('price', $params)) {

		if ((int)$params['price'] < 0) {

			api_fail_validation(lang('price cannot be negative.'), 'price');

		}

		$set[] = "price = '" . (int)$params['price'] . "'";

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$app = api_current_app();

	// Every field this endpoint writes feeds the meta half of the SEO score, so
	// the stored analysis is stale after the write. The product screen marks it
	// the same way; leaving it out here is what made a title changed through the
	// API keep the score of the title it replaced.
	$set[] = "seo_analysis_current = '0'";

	api_dry_run_stop('updated', 'product', array('id' => $id, 'fields' => count($set)));


	api_exec("UPDATE products SET " . implode(', ', $set) . ",
			user = '" . (int)$app['owner']['id'] . "',
			timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'
		LIMIT 1");

	// Site search keeps its own index of the promoted keywords. The panel syncs
	// it on every save and the values are read back rather than taken from the
	// request, because a request that changed only the title still has to leave
	// the index agreeing with the row.
	$indexed = api_row("SELECT keywords, enabled FROM products WHERE id = '" . $id . "' LIMIT 1");

	// The sync lives with the product screen's own writer. That file is a
	// function library with an include guard and draws nothing, so it is pulled
	// in here rather than having a second copy of the same difference walk.
	if (!function_exists('pg_pb_sync_tag_cloud_keywords')) {

		$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

		require_once($directory . '/product_builder.php');

	}

	if ($indexed !== null) {

		pg_pb_sync_tag_cloud_keywords($id, $indexed['keywords'], ((int)$indexed['enabled'] === 1));

	}

	log_activity(lang(array(
		'string' => 'Product ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($existing['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_product_select() . " WHERE products.id = '" . $id . "' LIMIT 1");

	api_webhook_enqueue('product.updated', array('id' => $id, 'name' => $row['name']));

	if (function_exists('pg_marketplace_product_changed')) {

		pg_marketplace_product_changed($id);

	}

	api_ok(api_product_present($row, api_product_extras(array($id)), true));

}

// Prices in a batch.
//
// The twin of the stock endpoint, and it exists for the same reason: a
// marketplace that repriced a hundred products overnight should not have to
// make a hundred calls, and the shop whose price list is driven from outside
// cannot afford the round trips either. The batch answers 200 with one outcome
// per item - a product that has been deleted since the other side last synced
// is data, not a transport failure - and the caller reads `applied` to know how
// many landed.
//
// Only the price. Everything else about a product is written through
// POST /products/{id}, one product at a time, because the rest of the record is
// not something a machine changes a hundred at a time without looking.
function api_products_prices($params) {

	$app = api_current_app();

	// A dry run is worth more here than anywhere else in this API: two hundred
	// prices arrive in one call, and the answer says which of them would have
	// been refused before a single one is written.
	$dry = api_dry_run_requested();

	$items = $params['items'];

	$results = array();

	$applied = 0;

	foreach ($items as $index => $item) {

		if (!is_array($item)) {

			$results[] = array('index' => $index, 'status' => 'invalid', 'message' => lang('Each item must be an object.'));

			continue;

		}

		// sku is the product id under another name in this catalogue, so it
		// feeds the same field; a barcode needs a lookup.
		$id = isset($item['id']) ? (int)$item['id'] : 0;

		if (($id <= 0) && isset($item['sku']) && ctype_digit(trim((string)$item['sku']))) {

			$id = (int)trim($item['sku']);

		}

		$barcode = isset($item['barcode']) ? trim((string)$item['barcode']) : '';

		if (($id <= 0) && ($barcode === '')) {

			$results[] = array('index' => $index, 'status' => 'invalid', 'message' => lang('Each item needs an id, an sku or a barcode.'));

			continue;

		}

		// Minor units, whole. A decimal is refused rather than rounded: rounding
		// somebody's price quietly is worse than telling them the format is
		// wrong, and this is the endpoint where it would happen a hundred times
		// before anyone noticed.
		if (!isset($item['price']) || !is_numeric($item['price']) || ((string)(int)$item['price'] !== (string)$item['price'])) {

			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'invalid',
				'message' => lang('price must be a whole number of minor units.'));

			continue;

		}

		if ((int)$item['price'] < 0) {

			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'invalid',
				'message' => lang('price cannot be negative.'));

			continue;

		}

		if (($id <= 0) && ($barcode !== '')) {

			$id = (int)api_value("SELECT product_id FROM product_barcodes
				WHERE barcode = '" . escape($barcode) . "' LIMIT 1");

		}

		$product = ($id > 0)
			? api_row("SELECT id, name, price FROM products WHERE id = '" . $id . "' LIMIT 1")
			: null;

		if ($product === null) {

			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'not_found');

			continue;

		}

		$price = (int)$item['price'];

		if (((int)$product['price'] !== $price) && (!$dry)) {

			api_exec("UPDATE products SET price = '" . $price . "', timestamp = '" . time() . "'
				WHERE id = '" . (int)$product['id'] . "' LIMIT 1");

			api_webhook_enqueue('product.updated', array('id' => (int)$product['id'], 'name' => $product['name']));

			// The same fact, going the other way: a shop whose prices are driven
			// by one marketplace has to have the others told.
			if (function_exists('pg_marketplace_product_changed')) {

				pg_marketplace_product_changed((int)$product['id']);

			}

		}

		$applied++;

		$results[] = array(
			'index'    => $index,
			'id'       => (int)$product['id'],
			'barcode'  => $barcode,
			'status'   => 'ok',
			'price'    => api_money($price),
			'previous' => api_money($product['price'])
		);

	}

	// Every item has been resolved and judged by now, and nothing has been
	// written. The per-item list goes out with the answer, so a caller learns
	// which rows are wrong without having changed the shop to find out.
	api_dry_run_stop('updated', 'prices', array(
		'applied' => $applied,
		'total'   => count($items),
		'items'   => $results
	));

	log_activity(lang(array(
		'string' => 'Prices for {var:1} of {var:2} products were changed through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($applied, count($items), $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array(
		'applied' => $applied,
		'total'   => count($items),
		'items'   => $results
	));

}

// What the batch price endpoint answers with.
function api_price_batch_schema() {

	return array(
		'applied' => 'integer',
		'total'   => 'integer',
		'items'   => array(array(
			'index'    => 'integer',
			'id'       => 'integer',
			'barcode'  => 'string',
			'status'   => 'string',
			'message'  => 'string',
			'price'    => 'integer',
			'previous' => 'integer'
		))
	);

}

// What api_product_present() returns, declared for the OpenAPI document.
//
// Beside the presenter on purpose: a field added above without a line here is
// a field no generated client can see, and tools/check_api_schema.php compares
// the two lists so that stays a build error rather than a support question.
function api_product_schema() {

	return array(
		'id'                => 'integer',
		'sku'               => 'string',
		'name'              => 'string',
		'barcode'           => 'string?',
		'enabled'           => 'boolean',
		'price'             => 'integer',
		'title'             => 'string',
		'short_description' => 'string',
		'brand'             => 'string',
		'gtin'              => 'string',
		'mpn'               => 'string',
		'images'            => 'string[]',
		'attributes'        => array(array(
			'attribute_id' => 'integer',
			'name'         => 'string',
			'label'        => 'string',
			'option_id'    => 'integer',
			'option'       => 'string'
		)),
		'group_ids'         => 'integer[]',
		'taxable'           => 'boolean',
		'tax_rate'          => 'number?',
		'shippable'         => 'boolean',
		'free_shipping'     => 'boolean',
		'weight'            => 'number?',
		'inventory'         => array(
			'tracked'      => 'boolean',
			'quantity'     => 'integer',
			'out_of_stock' => 'boolean'
		),
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer',
		'seo'             => 'Seo'
	);

}
