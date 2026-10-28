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

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
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
		// The merchant's own "was" price for the article, in minor units, or
		// null when it has none. It is not what the article sells at: an
		// offer still changes the price paid without touching either field.
		'compare_at_price'  => api_product_compare_at_price($row),
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
		// The stars of the product's published reviews from verified
		// purchases (GET /products/{id}/reviews), kept on the row.
		'rating'          => api_product_rating($row),
		// In the catalogue's Recycle Bin: off the shop and out of the listing,
		// but still readable by id until the bin is emptied.
		'recycled'        => (isset($row['recycled']) && ((int)$row['recycled'] === 1)),
		'updated_at'      => api_time($row['timestamp']),
		'updated_at_unix' => (int)$row['timestamp'],
		// What the site already knows about how this product reads to a search
		// engine. Nothing is calculated here; the findings are only carried on
		// the single product endpoint, because they are a query per row.
		'seo'             => api_seo_block('product', $id, $row, $with_issues)
	);

}

// The product's rating as the object carries it: the average of its
// published, starred reviews from verified purchases (two decimals) and how
// many there are; average null while there is none. A site before 11.0.14
// reads zeros (api_product_select()).
function api_product_rating($row) {

	$count = isset($row['rating_count']) ? max(0, (int)$row['rating_count']) : 0;

	return array(
		'average' => ($count > 0) ? round((float)$row['rating_avg'], 2) : null,
		'count'   => $count
	);

}

// products.compare_at_price as the product object carries it: null for none,
// which is also what a site without the column answers (api_product_select()
// reads NULL there) and what a zero put into the column by hand reads as.
function api_product_compare_at_price($row) {

	$value = pg_compare_at_price_value(isset($row['compare_at_price']) ? (string)$row['compare_at_price'] : null);

	return is_int($value) ? api_money($value) : null;

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
			" . (pg_compare_at_price_ready() ? "products.compare_at_price," : "NULL AS compare_at_price,") . "
			products.title, products.short_description, products.brand,
			products.gtin, products.mpn, products.image_name, products.taxable,
			products.shippable, products.free_shipping, products.weight,
			products.inventory, products.inventory_quantity, products.out_of_stock,
			products.tax_rate,
			" . (pg_reviews_ready() ? "products.rating_avg, products.rating_count," : "0 AS rating_avg, 0 AS rating_count,") . "
			" . (api_catalog_bin_columns() ? "products.recycled," : "0 AS recycled,") . "
			products.timestamp,
			" . api_seo_columns('products.') . "
			(SELECT barcode FROM product_barcodes
			  WHERE product_barcodes.product_id = products.id
			  ORDER BY product_barcodes.id ASC LIMIT 1) AS barcode
		FROM products";

}

function api_products_list($params) {

	$where = array();

	// A product in the Recycle Bin is deleted as far as the shop is concerned.
	// It stays readable by id, so a sync that still holds it can see why it
	// went, but it is not listed as part of the catalogue.
	if (api_catalog_bin_columns()) {

		$where[] = "products.recycled = '0'";

	}

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

		$search = escape(escape_like($params['search']));

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

// The name a product is written with. It is the merchant SKU, so it has to be
// there and has to be free; trimmed, as the product screen trims it. Names are
// compared the way the catalogue compares them, which is the collation of the
// column - a case difference is not a different SKU. $except_id is the product
// being renamed, whose own row does not count as taken.
function api_products_name_or_fail($raw, $except_id = 0) {

	$name = trim((string)$raw);

	if ($name === '') {

		api_fail_validation(lang(array('string' => '{var:1} is required', 'vars' => array('name'))), 'name');

	}

	$taken = api_value("SELECT id FROM products
		WHERE name = '" . escape($name) . "'" . (($except_id > 0) ? " AND id != '" . (int)$except_id . "'" : '') . "
		LIMIT 1");

	if ($taken) {

		api_fail(409, 'name_taken', lang(array(
			'string' => 'A product with the name {var:1} already exists (id {var:2}).',
			'vars'   => array($name, (int)$taken)
		)), 'name');

	}

	return $name;

}

// The row a new product starts from before the request's fields are laid
// over it, stamped with the key's owner. POST /product-groups/variant-sets
// starts its variants from it too.
function api_products_create_defaults($owner_id) {

	// Defaults for a product nobody described in full. taxable and shippable
	// are on because that is what the product screen starts from and what a
	// physical article normally is; enabled is off so an import can be looked
	// at before the shop starts selling it.
	return array(
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
		'user'              => $owner_id,

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

	$name = api_products_name_or_fail($params['name']);

	$app = api_current_app();

	$product = api_products_create_defaults((int)$app['owner']['id']);
	$product['name'] = $name;

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

		$product['price'] = (int)$params['price'];

	}

	// Absent, null, empty or 0: the product starts without a "was" price.
	if (array_key_exists('compare_at_price', $params)) {

		$product['compare_at_price'] = api_products_compare_at_price_param($params['compare_at_price']);

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

	$images = api_products_images($params);

	$barcode = api_products_barcode($params);

	$relations = array(
		'group_ids'   => api_products_create_group_ids($params),
		'attributes'  => api_products_create_attributes($params),
		'images'      => ($images === null) ? array() : $images,
		// No form behind this request, so the builder is told not to look for
		// one rather than left to read whatever $_POST happens to hold.
		'submit_form' => array(),
		// The first row of the product's price history (MCP tools included).
		'price_source' => 'api'
	);

	// Everything above is a check on what arrived; the next line puts a product
	// in the catalogue.
	api_dry_run_stop('created', 'product', array('name' => $name));

	$id = (int)pg_pb_create_product($product, $relations);

	if (!$id) {

		api_fail_server(lang('The product could not be created.'));

	}

	if ($barcode !== null) {

		pg_assign_product_barcode($id, $barcode);

	}

	api_products_rescore($id);

	pg_audit_record('create', 'products', $id);

	log_activity(lang(array(
		'string' => 'Product ({var:1}) was added through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_product_select() . " WHERE products.id = '" . $id . "' LIMIT 1");

	api_webhook_enqueue('product.created', array('id' => $id, 'name' => $row['name']));

	api_ok(api_product_present($row, api_product_extras(array($id))), 201);

}

// The compare_at_price a write sent, as products.compare_at_price stores it:
// null for none (JSON null, an empty value or 0), the minor units otherwise.
// The router has kept the figure to whole minor units within the column.
//
// A site that took the code but not the 2026.11.0 upgrade has no column to
// write it to, and is refused before anything is written rather than having
// the field dropped: dropping a field is how an integration writes nothing
// for a year and gets 200 every time.
function api_products_compare_at_price_param($value) {

	if (!pg_compare_at_price_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

	}

	$value = pg_compare_at_price_value($value);

	if ($value === false) {

		api_fail_validation(lang(array('string' => '{var:1} is above the largest accepted value.', 'vars' => 'compare_at_price')), 'compare_at_price');

	}

	return $value;

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

// The images a product is given, as file names from the Files library.
//
// The product row and the gallery store file names, not addresses, because
// files are served from the site root by name. An address is accepted as well,
// as long as it is one of this site's own: the API hands images out as
// addresses, and a client that reads a product and writes it back should not
// have to know the difference. Answers null when the request did not mention
// images, so an update leaves them alone.
function api_products_images($params) {

	if (!array_key_exists('images', $params)) {

		return null;

	}

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$names = array();

	foreach ($params['images'] as $raw) {

		$name = trim((string)$raw);

		if (strpos($name, $base) === 0) {

			$name = rawurldecode(substr($name, strlen($base)));

		}

		if (($name === '') || in_array($name, $names, true)) {

			continue;

		}

		$file = api_row("SELECT id, type FROM files WHERE name = '" . escape($name) . "' LIMIT 1");

		if ($file === null) {

			api_fail_validation(lang(array(
				'string' => 'There is no file named {var:1} in the Files library.',
				'vars'   => array($name)
			)), 'images');

		}

		if (!in_array(strtolower((string)$file['type']), array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'), true)) {

			api_fail_validation(lang(array(
				'string' => 'The file {var:1} is not an image.',
				'vars'   => array($name)
			)), 'images');

		}

		$names[] = $name;

	}

	return $names;

}

// The barcode a request gives a product, checked before anything is written.
//
// Answers null when there is nothing to write: the request did not mention a
// barcode, or the product already carries the same one. A barcode is only ever
// added, never swapped, which is what the product screen does too: a code that
// is already on a printed label has to keep scanning as this product.
function api_products_barcode($params, $product_id = 0) {

	if (!array_key_exists('barcode', $params) || (trim((string)$params['barcode']) === '')) {

		return null;

	}

	$barcode = trim((string)$params['barcode']);

	if (!defined('BARCODE_ENABLED') || !BARCODE_ENABLED) {

		api_fail_validation(lang('Barcodes are switched off on this site.'), 'barcode');

	}

	if ($product_id) {

		$current = api_value("SELECT barcode FROM product_barcodes
			WHERE product_id = '" . (int)$product_id . "' ORDER BY id ASC LIMIT 1");

		if ($current !== null && $current !== false && (string)$current !== '') {

			if ((string)$current === $barcode) {

				return null;

			}

			api_fail_validation(lang(array(
				'string' => 'This product already has the barcode {var:1}. A barcode is not replaced through the API, because labels may already carry it.',
				'vars'   => array((string)$current)
			)), 'barcode');

		}

	}

	$type = defined('BARCODE_DEFAULT_TYPE') ? BARCODE_DEFAULT_TYPE : 'CODE128';

	if (!pg_barcode_matches_format($barcode, $type)) {

		api_fail_validation(lang(array(
			'string' => 'The barcode does not match the site\'s barcode format ({var:1}).',
			'vars'   => array($type)
		)), 'barcode');

	}

	if (api_value("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($barcode) . "' LIMIT 1")) {

		api_fail_validation(lang('Another product already carries this barcode.'), 'barcode');

	}

	return $barcode;

}

// Scores the product's meta half now rather than leaving it to the product
// list or the nightly job: every column it reads may just have changed, and a
// client that reads the product back in the next call should see the score its
// own change produced. The catalogue's shared scorer
// (pg_catalog_seo_rescore()), which the Workspace's product create and the
// group writer use too.
function api_products_rescore($product_id) {

	api_seo_library();

	pg_catalog_seo_rescore('product', $product_id);

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

	// The name is the SKU and is held to the rule POST /products applies: two
	// products of one name leave the import and every lookup by name not
	// knowing which one is meant, and an empty one is a product nobody can
	// find. The product's own name sent again is not checked, so a catalogue
	// that already holds a duplicate (the product screen does not refuse one
	// on edit) still takes an update that resends the whole record.
	if (array_key_exists('name', $params)) {

		$name = trim((string)$params['name']);

		$params['name'] = (($name !== '') && ($name === (string)$existing['name']))
			? $name
			: api_products_name_or_fail($name, $id);

	}

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

		$set[] = "price = '" . (int)$params['price'] . "'";

	}

	// null, an empty value or 0 takes the "was" price off.
	if (array_key_exists('compare_at_price', $params)) {

		$set[] = "compare_at_price = " . pg_compare_at_price_sql(api_products_compare_at_price_param($params['compare_at_price']));

	}

	$group_ids = array_key_exists('group_ids', $params) ? api_products_create_group_ids($params) : null;

	$attributes = array_key_exists('attributes', $params) ? api_products_create_attributes($params) : null;

	$images = api_products_images($params);

	$barcode = api_products_barcode($params, $id);

	// The main photo is a column on the row; the gallery is rewritten below.
	if ($images !== null) {

		$set[] = "image_name = '" . escape($images ? $images[0] : '') . "'";

	}

	// A barcode the product already carries is a field that was sent and
	// needs no write, not an empty request.
	if (empty($set) && ($group_ids === null) && ($attributes === null) && !array_key_exists('barcode', $params)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$app = api_current_app();

	// Every field this endpoint writes feeds the meta half of the SEO score, so
	// the stored analysis is stale after the write. The product screen marks it
	// the same way; leaving it out here is what made a title changed through the
	// API keep the score of the title it replaced.
	$set[] = "seo_analysis_current = '0'";

	api_dry_run_stop('updated', 'product', array('id' => $id, 'fields' => count($set)));

	$audit_before = pg_audit_fetch('products', $id);

	api_exec("UPDATE products SET " . implode(', ', $set) . ",
			user = '" . (int)$app['owner']['id'] . "',
			timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'
		LIMIT 1");

	// A row only when the stored price is not the newest one in the history:
	// the same price sent again adds nothing.
	if (array_key_exists('price', $params)) {

		pg_price_history_record($id, 'api');

	}

	// The links are rewritten, not appended, as the product screen rewrites
	// them: a list sent here is the whole answer, and an empty one clears it.
	if ($images !== null) {

		api_exec("DELETE FROM products_images_xref WHERE product = '" . $id . "'");

		foreach (array_slice($images, 1) as $image) {

			api_exec("INSERT INTO products_images_xref (product, file_name)
				VALUES ('" . $id . "', '" . escape($image) . "')");

		}

	}

	if ($group_ids !== null) {

		api_exec("DELETE FROM products_groups_xref WHERE product = '" . $id . "'");

		foreach ($group_ids as $group_id) {

			api_exec("INSERT INTO products_groups_xref (product, product_group)
				VALUES ('" . $id . "', '" . (int)$group_id . "')");

		}

	}

	if ($attributes !== null) {

		api_exec("DELETE FROM products_attributes_xref WHERE product_id = '" . $id . "'");

		$sort_order = 0;

		foreach ($attributes as $attribute) {

			$sort_order++;

			api_exec("INSERT INTO products_attributes_xref (product_id, attribute_id, option_id, sort_order)
				VALUES ('" . $id . "', '" . (int)$attribute['attribute_id'] . "', '" . (int)$attribute['option_id'] . "', '" . $sort_order . "')");

		}

	}

	if ($barcode !== null) {

		pg_assign_product_barcode($id, $barcode);

	}

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

	api_products_rescore($id);

	pg_audit_record('update', 'products', $id, $audit_before);

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

// Whether the catalogue has its Recycle Bin columns. They arrive with the
// 2026.4.9 upgrade; before it a deleted product is simply gone and nothing here
// pretends otherwise. Asked once per request.
function api_catalog_bin_columns() {

	return pg_schema_has('products', 'recycled') && pg_schema_has('product_groups', 'recycled');

}

// The catalogue's Recycle Bin, as the file manager decides it: the columns, and
// the bin table that holds the restore records.
function api_catalog_bin_ready() {

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/view_folder_and_files_f.php');

	return (pg_catalog_recycle_ready() == true);

}

// Taking a product off the shop.
//
// The Recycle Bin is the default, the same move the catalogue's own Delete
// makes: the product is switched off and flagged, what it was is kept so that
// restoring puts it back as it was, and an operator can undo an application's
// mistake. permanent=true deletes it outright with the whole cleanup the
// product screen performs - images, group links, attributes, barcodes, form
// fields, keywords and the stored findings - which cannot be undone.
//
// Either way the product.deleted event goes out, carrying recycled so a
// receiver can tell the two apart: a binned product is no longer listed, and an
// integration that only learned of it through the listing would never hear
// that it went.
function api_products_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$permanent = !empty($params['permanent']);

	$product = api_row("SELECT id, name, " . (api_catalog_bin_columns() ? "recycled" : "0 AS recycled") . "
		FROM products WHERE id = '" . $id . "' LIMIT 1");

	if ($product === null) {

		api_fail_not_found(lang('Product'));

	}

	if (!$permanent) {

		if (!api_catalog_bin_ready()) {

			api_fail(409, 'recycle_bin_unavailable', lang('This site has no catalogue Recycle Bin. Send permanent=true to delete the product outright.'));

		}

		if ((int)$product['recycled'] === 1) {

			api_fail(409, 'already_recycled', lang('This product is already in the Recycle Bin.'));

		}

		api_dry_run_stop('recycled', 'product', array('id' => $id, 'name' => $product['name']));

		$audit_before = pg_audit_fetch('products', $id);

		// The statements every panel screen writes when it bins a product.
		// "AND recycled = '0'" in them keeps a second request that raced this
		// one from writing recycled_enabled from an enabled that is already
		// forced to 0, which would bring the product back out of the bin
		// unpublished.
		foreach (pg_catalog_bin_statements('product', $id, array($id), 0, (int)$app['owner']['id']) as $statement) {

			api_exec($statement);

		}

		pg_audit_record('recycle', 'products', $id, $audit_before);

		log_activity(lang(array(
			'string' => 'Product ({var:1}) was moved to the Recycle Bin through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($product['name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

		api_webhook_enqueue('product.deleted', array('id' => $id, 'name' => $product['name'], 'recycled' => true));

		if (function_exists('pg_marketplace_product_changed')) {

			pg_marketplace_product_changed($id);

		}

		api_ok(array('id' => $id, 'deleted' => false, 'recycled' => true));

	}

	// A component of a bundle stays until it is taken out of the bundle.
	if (pg_bundle_is_component($id)) {

		api_fail(409, 'product_in_bundle', lang('This product is part of a bundle. Take it out of the bundle before deleting it.'));

	}

	api_dry_run_stop('deleted', 'product', array('id' => $id, 'name' => $product['name']));

	if (!function_exists('pg_pb_delete_product')) {

		$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

		require_once($directory . '/product_builder.php');

	}

	// The product screen's own deletion. It queues product.deleted itself,
	// because every path that removes a product ends on that function.
	pg_pb_delete_product($id);

	api_exec("DELETE FROM recycle_bin WHERE (item_type = 'product') AND (item_id = '" . $id . "')");

	log_activity(lang(array(
		'string' => 'Product ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($product['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true, 'recycled' => false));

}

// The largest price a product takes, in minor units: products.price is a
// signed INT. The single-product routes declare the same bound
// (products.create and products.update in includes/api/schema.php), where the
// router enforces it; a batch item is not a route parameter and is read by
// api_price_batch_price() instead. Past it the session's non-strict mode cut
// the value to the column's top in silence: 3000000000 was answered "ok" and
// stored as 2147483647.
if (!defined('API_PRODUCT_PRICE_MAX')) {
	define('API_PRODUCT_PRICE_MAX', 2147483647);
}

// A batch item's price: whole minor units, as a JSON number or a numeric
// string. Returns the int, null when it is not a whole number, false when it
// is one below 0 or above API_PRODUCT_PRICE_MAX.
function api_price_batch_price($raw) {

	// A decimal is refused rather than rounded: rounding somebody's price
	// quietly is worse than telling them the format is wrong, and this is the
	// endpoint where it would happen a hundred times before anyone noticed.
	if (($raw === null) || !is_numeric($raw) || ((string)(int)$raw !== (string)$raw)) {

		return null;

	}

	$price = (int)$raw;

	if (($price < 0) || ($price > API_PRODUCT_PRICE_MAX)) {

		return false;

	}

	return $price;

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

		$price = api_price_batch_price(isset($item['price']) ? $item['price'] : null);

		if ($price === null) {

			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'invalid',
				'message' => lang('price must be a whole number of minor units.'));

			continue;

		}

		if ($price === false) {

			// The single-product routes answer a price past the top with the
			// router's own sentence; a batch item says the same.
			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'invalid',
				'message' => ((int)$item['price'] < 0)
					? lang('price cannot be negative.')
					: lang(array('string' => '{var:1} is above the largest accepted value.', 'vars' => 'price')));

			continue;

		}

		if (($id <= 0) && ($barcode !== '')) {

			$id = (int)api_value("SELECT product_id FROM product_barcodes
				WHERE barcode = '" . escape($barcode) . "' LIMIT 1");

		}

		$product = ($id > 0)
			? api_row("SELECT id, name, short_description, price FROM products WHERE id = '" . $id . "' LIMIT 1")
			: null;

		if ($product === null) {

			$results[] = array('index' => $index, 'id' => $id, 'barcode' => $barcode, 'status' => 'not_found');

			continue;

		}

		if (((int)$product['price'] !== $price) && (!$dry)) {

			api_exec("UPDATE products SET price = '" . $price . "', timestamp = '" . time() . "'
				WHERE id = '" . (int)$product['id'] . "' LIMIT 1");

			pg_audit('update', 'product', (int)$product['id'], array('price' => $product['price']), array('price' => $price), pg_audit_label_of('products', $product));

			pg_price_history_queue((int)$product['id'], 'api');

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

	pg_price_history_flush();

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

// One row of a product's price history: the price the product's own price
// became at a moment, in the base currency of that moment, and which kind of
// writer changed it (pg_price_history_sources()). The first row of a product
// is 'initial', the price it had when the history began; its changed_at is
// the moment the history began, not the moment that price was set.
function api_price_history_entry_present($row) {

	return array(
		'id'              => (int)$row['id'],
		// 'web' for the shop's own price; a price kept per sales channel is
		// 'pos' or 'marketplace:<code>'.
		'channel'         => (string)$row['channel'],
		'price'           => api_money($row['price']),
		'currency'        => (string)$row['currency'],
		'source'          => (string)$row['source'],
		'changed_at'      => api_time($row['changed_at']),
		'changed_at_unix' => (int)$row['changed_at']
	);

}

// GET /products/{id}/price-history
//
// Every change of the product's own price, oldest first, so a client that
// walks the pages builds the same line the "lowest price of the last 30 days"
// is read from. Cursor paged like the catalogue listing: a product that is
// repriced by a feed every day has hundreds of rows in a year, and an offset
// over a table that is written to between two pages skips rows.
//
// A product in the Recycle Bin keeps its id and its prices, so it answers
// here as it does on GET /products/{id}. A site upgraded without the history
// table answers an empty list rather than an error: the product exists, it
// simply has no history yet.
//
// Offer rows (source 'offer') are the price the open offers gave a visitor
// with no offer code, no account and an empty cart, written by the general job
// within the hour an offer began, ended or changed
// (pg_price_history_offer_pass_run()); the other rows are products.price as
// its writers set it. The newest row at or before a moment is what such a
// visitor paid then.
function api_products_price_history($params) {

	$id = (int)$params['id'];

	$product = api_row("SELECT id FROM products WHERE id = '" . $id . "' LIMIT 1");

	if ($product === null) {

		api_fail_not_found(lang('Product'));

	}

	$channel = isset($params['channel']) ? trim((string)$params['channel']) : '';

	// The channel is written into the statement as it is, so only a name of
	// the known shape passes (pg_price_history_channel()); anything else is a
	// client mistake and says so rather than matching nothing quietly.
	if (($channel !== '') && (pg_price_history_channel($channel) === null)) {

		api_fail_validation(lang('channel must be web, pos or marketplace:<code>.'), 'channel');

	}

	$cursor = null;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$since = isset($params['since']) ? (int)$params['since'] : 0;

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = array();

	if (pg_price_history_ready()) {

		// One row more than asked for; whether it exists says whether there
		// is another page, without a second query.
		$rows = api_rows(pg_price_history_list_sql($id, $channel, $since, $cursor, $limit + 1));

	}

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode($last['changed_at'], $last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_price_history_entry_present($row);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// A product's reviews: its published comments, newest first, as GET /comments
// lists them. Only the product's own (item_type product), as its rating
// counts them; a group of options keeps its own under its id.
function api_products_reviews($params) {

	$id = (int)$params['id'];

	if (api_row("SELECT id FROM products WHERE id = '" . $id . "' LIMIT 1") === null) {

		api_fail_not_found(lang('Product'));

	}

	$list = array(
		'item_type' => 'product',
		'item_id'   => $id,
		'status'    => 'published',
		'limit'     => isset($params['limit']) ? (int)$params['limit'] : 50
	);

	foreach (array('verified', 'cursor') as $key) {

		if (isset($params[$key])) {

			$list[$key] = $params[$key];

		}

	}

	api_comments_list($list);

}

// What api_price_history_entry_present() returns, declared for the OpenAPI
// document.
function api_price_history_entry_schema() {

	return array(
		'id'              => 'integer',
		'channel'         => 'string',
		'price'           => 'integer',
		'currency'        => 'string',
		'source'          => 'string',
		'changed_at'      => 'string?',
		'changed_at_unix' => 'integer'
	);

}

// May this owner read a product's marketplace mappings? The marketplace screen
// (marketplace_settings.php) is the manager role's, and products:read alone
// also reaches a user (role 3) with commerce rights, so the API asks the same
// role the screen does rather than opening the mappings wider than the panel.
function api_product_marketplaces_allowed($owner) {

	return is_array($owner) && isset($owner['role']) && ((string)$owner['role'] !== '') && ((int)$owner['role'] < 3);

}

// One mapping of a product to a marketplace account, as GET
// /products/{id}/marketplaces answers it. remote_code is the marketplace's key
// for the product (the seller code on n11, the barcode on Trendyol); remote_id
// is the id the marketplace gave the listing, null until it did. last_error is
// the last refusal or note on the mapping, null when there is none.
function api_product_marketplace_present($row) {

	return array(
		'account_id'     => (int)$row['account_id'],
		'account_name'   => (string)$row['account_name'],
		'provider'       => (string)$row['provider'],
		'account_status' => (string)$row['account_status'],
		'remote_code'    => (string)$row['remote_code'],
		'remote_id'      => ((string)$row['remote_id'] !== '') ? (string)$row['remote_id'] : null,
		'status'         => (string)$row['status'],
		'last_error'     => ((string)$row['last_error'] !== '') ? (string)$row['last_error'] : null,
		'last_pushed_at' => api_time($row['last_pushed']),
		'created_at'     => api_time($row['created_timestamp'])
	);

}

// GET /products/{id}/marketplaces
//
// One page: a product is matched to a handful of accounts at most. Only the
// account's id, name, provider and status are read from marketplace_accounts;
// its credentials blob is never selected.
function api_products_marketplaces($params) {

	$app = api_current_app();

	if (!api_product_marketplaces_allowed($app['owner'])) {

		api_fail(403, 'forbidden', lang('Marketplace connections are managed from the manager role up.'));

	}

	$id = (int)$params['id'];

	$product = api_row("SELECT id FROM products WHERE id = '" . $id . "' LIMIT 1");

	if ($product === null) {

		api_fail_not_found(lang('Product'));

	}

	$out = array();

	foreach (api_rows("SELECT
			marketplace_product_map.account_id,
			marketplace_product_map.remote_code,
			marketplace_product_map.remote_id,
			marketplace_product_map.status,
			marketplace_product_map.last_pushed,
			marketplace_product_map.last_error,
			marketplace_product_map.created_timestamp,
			marketplace_accounts.name AS account_name,
			marketplace_accounts.provider,
			marketplace_accounts.status AS account_status
		FROM marketplace_product_map
		JOIN marketplace_accounts ON marketplace_accounts.id = marketplace_product_map.account_id
		WHERE marketplace_product_map.product_id = '" . $id . "'
		ORDER BY marketplace_product_map.account_id ASC") as $row) {

		$out[] = api_product_marketplace_present($row);

	}

	api_ok_list($out, max(1, count($out)), null);

}

// What api_product_marketplace_present() returns, declared for the OpenAPI
// document.
function api_product_marketplace_schema() {

	return array(
		'account_id'     => 'integer',
		'account_name'   => 'string',
		'provider'       => 'string',
		'account_status' => 'string',
		'remote_code'    => 'string',
		'remote_id'      => 'string?',
		'status'         => 'string',
		'last_error'     => 'string?',
		'last_pushed_at' => 'string?',
		'created_at'     => 'string?'
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
		// A DEFAULT NULL column that every create path writes, so not nullable
		// (api_openapi_type_spec() says when a field is).
		'name'              => 'string',
		'barcode'           => 'string?',
		'enabled'           => 'boolean',
		'price'             => 'integer',
		// NULL for none: a product made by any path starts without one.
		'compare_at_price'  => 'integer?',
		'title'             => 'string',
		// A nullable column: an import whose sheet has no such column leaves it unset.
		'short_description' => 'string?',
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
		'rating'            => array(
			'average' => 'number?',
			'count'   => 'integer'
		),
		'recycled'        => 'boolean',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer',
		'seo'             => 'Seo'
	);

}
