<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Every endpoint, described once.
//
// This table is the only place an endpoint is declared. Four things read it and
// therefore cannot disagree with each other: the router dispatches from it, the
// input validator takes each parameter's type and bounds from it, the OpenAPI
// document is generated from it, and the permission screen builds its scope list
// from it.
//
// That single source is the fix for a specific failure. The old surface kept its
// endpoint list twice - once as a switch in the endpoint file, once as a PHP
// array in the settings page that drove the test tool - and the two drifted, so
// the tool offered fields the endpoint did not read and hid ones it did. Anyone
// testing through it was testing a description of the API rather than the API.
//
// Adding an endpoint means adding a row here and writing its handler. Nothing
// else has to be touched.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_version() {

	return 1;

}

// Parameter types the validator understands:
//
//   int       whole number, honours 'min' and 'max'
//   string    trimmed, honours 'max_length'
//   bool      1/0, true/false, yes/no, on/off
//   enum      one of 'values'
//   datetime  ISO-8601, YYYY-MM-DD or unix, stored as unix
//   money     whole number of minor units; a decimal is refused rather than
//             rounded, because rounding someone's price silently is worse than
//             telling them the format is wrong
//   list      array of objects, for the bulk endpoints
function api_schema() {

	$routes = array(

		/* ----- Site information ------------------------------------------- */

		array(
			'id'      => 'meta.read',
			'method'  => 'GET',
			'path'    => '/meta',
			'scope'   => 'meta:read',
			'handler' => 'api_meta_read',
			'returns' => 'Meta',
			'summary' => 'Site information',
			'description' => 'Store name, currency, tax setting, the order status vocabulary and the API version, plus which modules are switched on, whether the firewall is blocking, whether the scheduled jobs are actually running, and what an upload may weigh. An integration reads this once at start-up instead of hard coding any of it - and reads the jobs list when a figure this API reports has stopped moving, because half of them are produced by a job rather than by a request.',
			'params'  => array()
		),

		/* ----- Products ---------------------------------------------------- */

		array(
			'id'      => 'products.list',
			'method'  => 'GET',
			'path'    => '/products',
			'scope'   => 'products:read',
			'handler' => 'api_products_list',
			'returns' => array('list' => 'Product'),
			'summary' => 'List products',
			'description' => 'Cursor paged. Pass updated_since to fetch only what changed, which is how a marketplace keeps itself in step without reading the whole catalogue every time.',
			'params'  => array(
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only products changed at or after this moment.'),
				array('name' => 'sku',           'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'The product id. This catalogue has no separate SKU column, so the id is the merchant SKU.'),
				array('name' => 'barcode',       'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Exact match on the product barcode (EAN/UPC), which is a different thing from the SKU.'),
				array('name' => 'search',        'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name or the short description.'),
				array('name' => 'enabled',       'in' => 'query', 'type' => 'bool',   'description' => 'Limit to published or unpublished products.'),
				array('name' => 'in_stock',      'in' => 'query', 'type' => 'bool',   'description' => 'Limit to products that are or are not out of stock.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int',    'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool',   'description' => 'Also count every matching row. Costs an extra query, so it is off by default.')
			)
		),

		array(
			'id'      => 'products.get',
			'method'  => 'GET',
			'path'    => '/products/{id}',
			'scope'   => 'products:read',
			'handler' => 'api_products_get',
			'returns' => 'Product',
			'summary' => 'One product',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'products.create',
			'method'  => 'POST',
			'path'    => '/products',
			'scope'   => 'products:write',
			'handler' => 'api_products_create',
			'dry_run' => true,
			'returns' => 'Product',
			'summary' => 'Add a product',
			'description' => 'Creates one product. name is the merchant SKU and has to be free: a name already in the catalogue is refused with 409 rather than quietly stored under a different one. Stock may be set here because the row does not exist yet and nothing can be racing it - afterwards it belongs to the inventory endpoint. Send an Idempotency-Key so a retried call does not leave two products behind.',
			'params'  => array(
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 255, 'required' => true, 'description' => 'The product id / SKU. Unique across the catalogue.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool',   'description' => 'Published. Off by default, so an import can be checked before it goes live.'),
				array('name' => 'price',             'in' => 'body', 'type' => 'money',  'description' => 'Minor units. 1999 is 19.99.'),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'full_description',  'in' => 'body', 'type' => 'string'),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'brand',             'in' => 'body', 'type' => 'string', 'max_length' => 190),
				array('name' => 'gtin',              'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'mpn',               'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'taxable',           'in' => 'body', 'type' => 'bool',   'description' => 'On by default, which is what the product screen starts from.'),
				array('name' => 'tax_rate',          'in' => 'body', 'type' => 'decimal', 'nullable' => true, 'min' => 0, 'max' => 100, 'description' => 'The article\'s own tax rate, as a percentage. Leave it out to follow the tax zone; zero is a different answer and means zero-rated.'),
				array('name' => 'shippable',         'in' => 'body', 'type' => 'bool',   'description' => 'On by default.'),
				array('name' => 'free_shipping',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'weight',            'in' => 'body', 'type' => 'decimal', 'min' => 0),
				array('name' => 'length',            'in' => 'body', 'type' => 'decimal', 'min' => 0),
				array('name' => 'width',             'in' => 'body', 'type' => 'decimal', 'min' => 0),
				array('name' => 'height',            'in' => 'body', 'type' => 'decimal', 'min' => 0),
				array('name' => 'notes',             'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'details',           'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long detail text the product page shows under the description.'),
				array('name' => 'google_product_category', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The Google product category, for a marketplace or shopping feed that asks for one.'),
				array('name' => 'inventory',         'in' => 'body', 'type' => 'bool',   'description' => 'Track stock for this product.'),
				array('name' => 'inventory_quantity','in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 1000000, 'description' => 'Opening stock. Only meaningful with inventory on.'),
				array('name' => 'group_ids',         'in' => 'body', 'type' => 'list', 'max_items' => 50, 'description' => 'Product groups this product belongs to. A variant is a product in the article\'s group - see the product-groups endpoint.'),
				array('name' => 'attributes',        'in' => 'body', 'type' => 'list', 'max_items' => 20, 'description' => 'Objects of {attribute_id, option_id}: what makes this row one variant rather than another. Both ids have to belong together.')
			)
		),

		array(
			'id'      => 'products.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/products/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_products_update',
			'dry_run' => true,
			'returns' => 'Product',
			'summary' => 'Change a product',
			'description' => 'Only the fields present in the body are written. Stock is not changed here - use the inventory endpoint, which adjusts atomically. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached, and IIS then treats that address as a file that does not exist - the next POST to the same product falls through the site rewrite and comes back as the shop\'s 404 page. Use POST.',
			'params'  => array(
				array('name' => 'id',                'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'price',             'in' => 'body', 'type' => 'money', 'description' => 'Minor units. 1999 is 19.99.'),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'full_description',  'in' => 'body', 'type' => 'string'),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'brand',             'in' => 'body', 'type' => 'string', 'max_length' => 190),
				array('name' => 'gtin',              'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'mpn',               'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'taxable',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'tax_rate',          'in' => 'body', 'type' => 'decimal', 'nullable' => true, 'min' => 0, 'max' => 100, 'description' => 'The article\'s own tax rate, as a percentage. Send an empty value to clear it, which puts the product back on the rate of the tax zone; zero is a different answer and means zero-rated.'),
				array('name' => 'shippable',         'in' => 'body', 'type' => 'bool'),
				array('name' => 'free_shipping',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'weight',            'in' => 'body', 'type' => 'decimal'),
				array('name' => 'length',            'in' => 'body', 'type' => 'decimal'),
				array('name' => 'width',             'in' => 'body', 'type' => 'decimal'),
				array('name' => 'height',            'in' => 'body', 'type' => 'decimal'),
				array('name' => 'notes',             'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'details',           'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long detail text the product page shows under the description.'),
				array('name' => 'google_product_category', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The Google product category, for a marketplace or shopping feed that asks for one.')
			)
		),

		/* ----- Inventory ---------------------------------------------------- */

		array(
			'id'      => 'inventory.write',
			'method'  => 'POST',
			'path'    => '/products/{id}/inventory',
			'scope'   => 'inventory:write',
			'handler' => 'api_inventory_write',
			'dry_run' => true,
			'returns' => 'Inventory',
			'summary' => 'Set or adjust stock',
			'description' => 'op=set writes an absolute quantity; op=adjust moves it by a signed amount and is applied by the database in one statement, so two channels selling the same item at the same moment cannot both write the same result. Send an Idempotency-Key so a retried adjustment is not applied twice.',
			'params'  => array(
				array('name' => 'id',       'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'op',       'in' => 'body', 'type' => 'enum', 'values' => array('set', 'adjust'), 'required' => true),
				array('name' => 'quantity', 'in' => 'body', 'type' => 'int', 'min' => -1000000, 'max' => 1000000, 'required' => true, 'description' => 'The new quantity for set, the signed change for adjust.')
			)
		),

		array(
			'id'      => 'inventory.bulk',
			'method'  => 'POST',
			'path'    => '/products/inventory',
			'scope'   => 'inventory:write',
			'handler' => 'api_inventory_bulk',
			'dry_run' => true,
			'returns' => 'InventoryBatch',
			'summary' => 'Set or adjust stock for many products',
			'description' => 'Up to 200 items in one call, because marketplaces synchronise stock in batches rather than one product at a time. Each item is reported on separately: one bad product id does not throw the rest away, and an adjust against a product that does not track stock is reported as not_tracked rather than switching tracking on.',
			'params'  => array(
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'max_items' => 200, 'required' => true, 'description' => 'Objects of {id, op, quantity}. sku is accepted as an alias for id; barcode looks the product up by its EAN/UPC.')
			)
		),

		array(
			'id'      => 'products.prices',
			'method'  => 'POST',
			'path'    => '/products/prices',
			'scope'   => 'products:write',
			'handler' => 'api_products_prices',
			'dry_run' => true,
			'returns' => 'PriceBatch',
			'summary' => 'Set prices for many products',
			'description' => 'The twin of the batch stock endpoint: one call for a repricing run instead of one call per product. Answers 200 with an outcome per item - ok, not_found or invalid - and applied says how many landed, because a product the other side has not noticed was deleted is data rather than a failure. Prices are whole minor units; a decimal is refused rather than rounded. Only the price is written.',
			'params'  => array(
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'required' => true, 'max_items' => 250, 'description' => 'Up to 250 objects: id, sku or barcode to say which product, and price in minor units.')
			)
		),

		/* ----- Product groups ----------------------------------------------- */

		array(
			'id'      => 'product_groups.list',
			'method'  => 'GET',
			'path'    => '/product-groups',
			'scope'   => 'products:read',
			'handler' => 'api_product_groups_list',
			'returns' => array('list' => 'ProductGroup'),
			'summary' => 'List product groups',
			'description' => 'A product group is the parent a set of variants hangs from: this catalogue has no variant table, so the products in one group are the sizes or colours of a single article. Pass variants_only to skip the shop navigation tree and get just the groups that gather variants.',
			'params'  => array(
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only groups changed at or after this moment.'),
				array('name' => 'enabled',       'in' => 'query', 'type' => 'bool',   'description' => 'Limit to published or unpublished groups.'),
				array('name' => 'parent_id',     'in' => 'query', 'type' => 'int',    'min' => 0, 'description' => 'Direct children of this group. Zero is the top of the tree.'),
				array('name' => 'variants_only', 'in' => 'query', 'type' => 'bool',   'description' => 'Only groups that carry attributes, which is to say only the ones that are an article with variants.'),
				array('name' => 'search',        'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name or the short description.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int',    'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool',   'description' => 'Also count every matching row. Costs an extra query, so it is off by default.')
			)
		),

		array(
			'id'      => 'product_groups.get',
			'method'  => 'GET',
			'path'    => '/product-groups/{id}',
			'scope'   => 'products:read',
			'handler' => 'api_product_groups_get',
			'returns' => 'ProductGroup',
			'summary' => 'One product group',
			'description' => 'Adds the long description, the child groups, and every option of every attribute the group uses - which together are the variant matrix a marketplace listing is built from.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'product_groups.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/product-groups/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_update',
			'dry_run' => true,
			'returns' => 'ProductGroup',
			'summary' => 'Change a product group',
			'description' => 'The text a group is found by and the published switch. The tree itself - the parent and the order - is not writable here: it is the shop\'s own navigation, and moving a group from outside moves pages on a live site. Publishing is not one row either: it runs down to the child groups and the products in them, and the answer reports how many records it reached. A product that is also published in an enabled group elsewhere in the catalogue is left alone when unpublishing, exactly as on the group screen.',
			'params'  => array(
				array('name' => 'id',                'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'Words that promote the group in the site search.'),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'description',       'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long description; full_description on the record.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool', 'description' => 'Published. Changing this reaches the child groups and the products in them.')
			)
		),

		/* ----- Orders ------------------------------------------------------- */

		array(
			'id'      => 'orders.list',
			'method'  => 'GET',
			'path'    => '/orders',
			'scope'   => 'orders:read',
			'handler' => 'api_orders_list',
			'returns' => array('list' => 'Order'),
			'summary' => 'List orders',
			'description' => 'Cursor paged and ordered by the order date, so updated_since walks new orders in one direction without repeating any.',
			'params'  => array(
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only orders placed at or after this moment.'),
				array('name' => 'date_to',       'in' => 'query', 'type' => 'datetime'),
				array('name' => 'status',        'in' => 'query', 'type' => 'enum', 'values' => array('incomplete', 'complete', 'exported', 'cancelled')),
				array('name' => 'order_number',  'in' => 'query', 'type' => 'string', 'max_length' => 100),
				array('name' => 'email',         'in' => 'query', 'type' => 'string', 'max_length' => 190),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'orders.get',
			'method'  => 'GET',
			'path'    => '/orders/{id}',
			'scope'   => 'orders:read',
			'handler' => 'api_orders_get',
			'returns' => 'Order',
			'summary' => 'One order, with its lines',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'orders.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/orders/{id}',
			'scope'   => 'orders:write',
			'handler' => 'api_orders_update',
			'dry_run' => true,
			'returns' => 'Order',
			'summary' => 'Change an order',
			'description' => 'The status and the internal note. Cancelling records who did it and why, the same way the order screen does. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached, and IIS then treats that address as a file that does not exist - the next POST to the same product falls through the site rewrite and comes back as the shop\'s 404 page. Use POST.',
			'params'  => array(
				array('name' => 'id',                  'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'status',              'in' => 'body', 'type' => 'enum', 'values' => array('incomplete', 'complete', 'exported', 'cancelled')),
				array('name' => 'cancellation_reason', 'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'notes',               'in' => 'body', 'type' => 'string', 'max_length' => 2000)
			)
		),

		array(
			'id'      => 'orders.ship',
			'method'  => 'POST',
			'path'    => '/orders/{id}/shipment',
			'scope'   => 'orders:write',
			'handler' => 'api_orders_ship',
			'dry_run' => true,
			'returns' => 'Order',
			'summary' => 'Record a shipment',
			'description' => 'The tracking numbers for one of the order\'s shipping addresses, and optionally the dates. A tracking number is what makes an order count as shipped in this store - the ship date is a planned dispatch date on many configurations, so nothing is read from it. The list replaces what that address holds, which is what the order screen does with the same field: send everything you know each time and the call can be repeated without collecting duplicates. The customer is told only when notify is true.',
			'params'  => array(
				array('name' => 'id',               'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'tracking_numbers', 'in' => 'body', 'type' => 'list', 'max_items' => 20, 'description' => 'The complete list for this address, as a JSON array. An empty list removes the numbers it has.'),
				array('name' => 'ship_to_id',       'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Which shipping address, from the shipments block of GET /orders/{id}. Optional while the order ships to one address; required when it ships to several.'),
				array('name' => 'carrier',          'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'Written only when the address has no shipping method code yet: that code is the method the customer chose and paid for, and a fulfilment system naming the carrier it used must not overwrite it. Any text is accepted, but one of yurtici, surat, aras, mng, ptt, ups, fedex or usps is what turns the tracking number into a link the customer can follow.'),
				array('name' => 'ship_date',        'in' => 'body', 'type' => 'datetime', 'description' => 'Kept as a date. Nothing in the store reads it as proof of dispatch.'),
				array('name' => 'delivery_date',    'in' => 'body', 'type' => 'datetime'),
				array('name' => 'notify',           'in' => 'body', 'type' => 'bool', 'description' => 'true sends the store\'s "your order has shipped" mail to the customer. Left out it does not: a backfill of last month\'s shipments must not mail a month of customers.')
			)
		),

		/* ----- Customers ---------------------------------------------------- */

		array(
			'id'      => 'customers.list',
			'method'  => 'GET',
			'path'    => '/customers',
			'scope'   => 'customers:read',
			'handler' => 'api_customers_list',
			'returns' => array('list' => 'Customer'),
			'summary' => 'List customers',
			'params'  => array(
				array('name' => 'search',        'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name, the email address or the member number.'),
				array('name' => 'email',         'in' => 'query', 'type' => 'string', 'max_length' => 190),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		/* ----- Webhooks ----------------------------------------------------- */

		array(
			'id'      => 'webhooks.list',
			'method'  => 'GET',
			'path'    => '/webhooks',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_list',
			'returns' => array('data' => 'Webhook[]', 'events' => 'string[]'),
			'summary' => 'Your event subscriptions',
			'description' => 'Only this application\'s own subscriptions. The answer also lists every event that can be subscribed to, and reports for each subscription what is still queued and the last delivery error on file, so a receiver that stopped answering can be diagnosed without reading the server\'s tables.',
			'params'  => array()
		),

		array(
			'id'      => 'webhooks.create',
			'method'  => 'POST',
			'path'    => '/webhooks',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_create',
			'dry_run' => true,
			'returns' => 'Webhook',
			'summary' => 'Subscribe to events',
			'description' => 'The signing secret is returned once, here. Each delivery carries X-Pinegrap-Signature: t=<unix>,v1=HMAC-SHA256(t + "." + body) - check it, and refuse a timestamp older than a few minutes.',
			'params'  => array(
				array('name' => 'url',    'in' => 'body', 'type' => 'string', 'max_length' => 500, 'required' => true, 'description' => 'https address to call. It is checked now, not at delivery time.'),
				array('name' => 'events', 'in' => 'body', 'type' => 'list', 'max_items' => 20, 'required' => true, 'description' => 'Event names. GET this endpoint to see the list.')
			)
		),

		array(
			'id'      => 'webhooks.delete',
			'method'  => 'DELETE',
			'also_accepts' => array('POST'),
			'path'    => '/webhooks/{id}',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_delete',
			'dry_run' => true,
			'summary' => 'Remove a subscription',
			'description' => 'POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'customers.get',
			'method'  => 'GET',
			'path'    => '/customers/{id}',
			'scope'   => 'customers:read',
			'handler' => 'api_customers_get',
			'returns' => 'Customer',
			'summary' => 'One customer',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'customers.create',
			'method'  => 'POST',
			'path'    => '/customers',
			'scope'   => 'customers:write',
			'handler' => 'api_customers_create',
			'dry_run' => true,
			'returns' => 'Customer',
			'summary' => 'Add a customer',
			'description' => 'The plain fields of the address book record. Needs at least a name, a company or an e-mail address - a row with none of those is not a customer. Nothing is de-duplicated: two people at one address is a real thing and neither the table nor the panel refuses it, so an integration that wants an upsert looks first with GET /customers?email=. Membership, contact groups, the affiliate record and the ERP columns are not written here.',
			'params'  => array(
				array('name' => 'salutation',  'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'first_name',  'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'last_name',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'company',     'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'title',       'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'email',       'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'phone',       'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'Stored as the mobile number, which is the one a courier and a marketplace both ask for.'),
				array('name' => 'address_1',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'address_2',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'city',        'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'state',       'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'zip',         'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'country',     'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'A note on the record, as the panel shows it.'),
				array('name' => 'opt_in',      'in' => 'body', 'type' => 'bool', 'description' => 'Consent to be mailed. Send true only where you actually hold it; this endpoint does not subscribe anyone to a contact group.')
			)
		),

		array(
			'id'      => 'customers.update',
			'method'  => 'POST',
			'path'    => '/customers/{id}',
			'scope'   => 'customers:write',
			'handler' => 'api_customers_update',
			'dry_run' => true,
			'returns' => 'Customer',
			'summary' => 'Change a customer',
			'description' => 'Only the fields you send are changed. The same set the create takes.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'salutation',  'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'first_name',  'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'last_name',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'company',     'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'title',       'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'email',       'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'phone',       'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'Stored as the mobile number, which is the one a courier and a marketplace both ask for.'),
				array('name' => 'address_1',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'address_2',   'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'city',        'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'state',       'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'zip',         'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'country',     'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'A note on the record, as the panel shows it.'),
				array('name' => 'opt_in',      'in' => 'body', 'type' => 'bool', 'description' => 'Consent to be mailed. Send true only where you actually hold it; this endpoint does not subscribe anyone to a contact group.')
			)
		),

		/* ----- Operations --------------------------------------------------- */

		array(
			'id'      => 'system.status',
			'method'  => 'GET',
			'path'    => '/system/status',
			'scope'   => 'system:read',
			'handler' => 'api_system_status',
			'returns' => 'SystemStatus',
			'summary' => 'Health score and the checks behind it',
			'description' => 'What the dashboard gauge is built from: the score out of a hundred and every check with its state, so the operator can put their own monitoring in front of it instead of opening the panel to find out that a scheduled job stopped moving three days ago. key is the English name to branch on; title, value and message are written for a person and follow the site language. The answer is the cached one the panel also reads, at most ten minutes old, and checked_at says how old - the checks behind it scan files and certificates and no site would survive computing them once a minute.',
			'params'  => array()
		),

		array(
			'id'      => 'reports.sales',
			'method'  => 'GET',
			'path'    => '/reports/sales',
			'scope'   => 'orders:read',
			'handler' => 'api_reports_sales',
			'returns' => 'SalesReport',
			'summary' => 'Sales by day or month',
			'description' => 'Orders and money added up by period, so a chart does not have to be drawn by paging through forty thousand orders. Completed and exported orders count as sales; an incomplete one is an abandoned basket and a cancelled one is money that went back - ask for either with status, which is a real question for a shop measuring abandonment. Amounts are minor units. Defaults to the last thirty days by day, and at most 400 periods come back.',
			'params'  => array(
				array('name' => 'from',     'in' => 'query', 'type' => 'datetime', 'description' => 'Start of the window. Defaults to thirty days before to.'),
				array('name' => 'to',       'in' => 'query', 'type' => 'datetime', 'description' => 'End of the window. Defaults to now.'),
				array('name' => 'interval', 'in' => 'query', 'type' => 'enum', 'values' => array('day', 'month'), 'default' => 'day'),
				array('name' => 'status',   'in' => 'query', 'type' => 'enum', 'values' => array('incomplete', 'complete', 'exported', 'cancelled'), 'description' => 'One status instead of the two that count as sales.')
			)
		),

		/* ----- SEO ---------------------------------------------------------- */

		array(
			'id'      => 'seo.issues',
			'method'  => 'GET',
			'path'    => '/seo/issues',
			'scope'   => 'seo:read',
			'handler' => 'api_seo_issues_list',
			'returns' => array('list' => 'SeoIssue'),
			'summary' => 'What the SEO analysis found',
			'description' => 'Every finding on the site in one cursor-paged list, with the record each one is about. The same findings ride on the record itself through /pages, /products and /product-groups; this is the other direction - "show me everything that is wrong" - which is how the work is actually done on a site with four hundred pages. occurrences counts repeats of one finding on one record: eleven images with no alt text is one finding of eleven, not eleven findings. Nothing is written here: a finding is cleared by fixing what it is about and letting the next analysis run notice.',
			'params'  => array(
				array('name' => 'entity_type', 'in' => 'query', 'type' => 'enum', 'values' => array('page', 'product', 'product_group'), 'description' => 'Limit to one kind of record.'),
				array('name' => 'entity_id',   'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Limit to one record, with entity_type.'),
				array('name' => 'severity',    'in' => 'query', 'type' => 'enum', 'values' => array('error', 'warning', 'notice')),
				array('name' => 'code',        'in' => 'query', 'type' => 'string', 'max_length' => 48, 'description' => 'One check, by its code - the way to work through a single kind of problem across the site.'),
				array('name' => 'limit',       'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',      'in' => 'query', 'type' => 'string', 'max_length' => 255)
			)
		),

		/* ----- Forms -------------------------------------------------------- */

		array(
			'id'      => 'forms.list',
			'method'  => 'GET',
			'path'    => '/forms',
			'scope'   => 'forms:read',
			'handler' => 'api_forms_list',
			'returns' => array('list' => 'Form'),
			'summary' => 'List forms',
			'description' => 'Every page that carries a form, with how many fields it has and how much has been filled in on it. A form is a page in this software, so the id here is a page id and the same id reads the page through /pages.',
			'params'  => array(
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => '1-250. Rows per page.'),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 255, 'description' => 'next_cursor from the previous page.')
			)
		),

		array(
			'id'      => 'forms.get',
			'method'  => 'GET',
			'path'    => '/forms/{id}',
			'scope'   => 'forms:read',
			'handler' => 'api_forms_get',
			'returns' => 'Form',
			'summary' => 'One form, with its fields',
			'description' => 'The field list as the page designer holds it: name, label, type, whether it is required, the choices a pick list offers, and which contact field it feeds. office_use_only marks a field the visitor never sees - the operator fills it in afterwards on the submission screen.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The form id, which is the page id.')
			)
		),

		array(
			'id'      => 'forms.submissions',
			'method'  => 'GET',
			'path'    => '/forms/{id}/submissions',
			'scope'   => 'forms:read',
			'handler' => 'api_form_submissions',
			'returns' => array('list' => 'FormSubmission'),
			'summary' => 'What came in on a form',
			'description' => 'Cursor paged, oldest first, so an integration walks forward with the cursor and never re-reads what it has. Every value carries the field name the submission was answering, and the label when the field is still there - a submission outlives the field it was made on. Sent submissions only unless complete is given: a form can be set up to let a visitor save and come back, and a half-written row is not an enquiry. An uploaded file is reported as a file_id; read the file itself from /files/{id}, which needs files:read.',
			'params'  => array(
				array('name' => 'id',              'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The form id, which is the page id.'),
				array('name' => 'submitted_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only submissions sent at or after this moment.'),
				array('name' => 'complete',        'in' => 'query', 'type' => 'bool', 'description' => 'false returns the saved-but-not-sent ones instead.'),
				array('name' => 'reference_code',  'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'The code the visitor was shown after sending.'),
				array('name' => 'limit',           'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => '1-250. Rows per page.'),
				array('name' => 'cursor',          'in' => 'query', 'type' => 'string', 'max_length' => 255, 'description' => 'next_cursor from the previous page.')
			)
		),

		/* ----- Pages -------------------------------------------------------- */

		array(
			'id'      => 'pages.list',
			'method'  => 'GET',
			'path'    => '/pages',
			'scope'   => 'pages:read',
			'handler' => 'api_pages_list',
			'returns' => array('list' => 'Page'),
			'summary' => 'List pages',
			'description' => 'The search settings of every page and the scores already on file. Cursor paged, and max_score narrows the listing to the pages that are scoring worst, which is where work on a site starts. The page text is not returned: fetch the address in the url field. The findings are on the single page endpoint.',
			'params'  => array(
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only pages changed at or after this moment.'),
				array('name' => 'folder_id',     'in' => 'query', 'type' => 'int', 'min' => 0, 'description' => 'Pages in one folder.'),
				array('name' => 'type',          'in' => 'query', 'type' => 'string', 'max_length' => 50, 'description' => 'Page type, for example standard or catalog detail.'),
				array('name' => 'home',          'in' => 'query', 'type' => 'bool', 'description' => 'Limit to the home page, or to everything else.'),
				array('name' => 'sitemap',       'in' => 'query', 'type' => 'bool', 'description' => 'Limit to pages that are, or are not, in the site map.'),
				array('name' => 'search',        'in' => 'query', 'type' => 'bool', 'description' => 'Limit to pages that are, or are not, included in the site search.'),
				array('name' => 'noindex',       'in' => 'query', 'type' => 'bool', 'description' => 'Limit to pages that are, or are not, closed to search engines.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the address or the title.'),
				array('name' => 'max_score',     'in' => 'query', 'type' => 'int', 'min' => 0, 'max' => 100, 'description' => 'Only pages scoring this or lower.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool', 'description' => 'Also count every matching row. Costs an extra query, so it is off by default.')
			)
		),

		array(
			'id'      => 'pages.get',
			'method'  => 'GET',
			'path'    => '/pages/{id}',
			'scope'   => 'pages:read',
			'handler' => 'api_pages_get',
			'returns' => 'Page',
			'summary' => 'One page, with its SEO findings',
			'description' => 'Adds the list of what is wrong with the page: the meta checks from the score record and the structure and link findings from the nightly analysis, each with a stable code and a sentence. Nothing is rendered or recalculated to answer this - the seo block reports when each half was last examined and whether it is waiting for a recalculation.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'pages.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/pages/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_pages_update',
			'dry_run' => true,
			'returns' => 'Page',
			'summary' => 'Change a page',
			'description' => 'The title, the description, the site search settings and the indexing switches. Only what is sent is changed. A page closed to search engines cannot be in the site map at the same time and the request is refused rather than quietly corrected; nofollow only applies while noindex is on. The meta half of the score is recalculated here, the structure half stays with the nightly job. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached. Use POST.',
			'params'  => array(
				array('name' => 'id',               'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'title',            'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'search',           'in' => 'body', 'type' => 'bool', 'description' => 'Whether the page is included in the site search.'),
				array('name' => 'search_keywords',  'in' => 'body', 'type' => 'list', 'max_items' => 50, 'description' => 'Words that promote the page in the site search. A list of words; send an empty list to clear it.'),
				array('name' => 'sitemap',          'in' => 'body', 'type' => 'bool', 'description' => 'Whether the page is listed in the site map.'),
				array('name' => 'noindex',          'in' => 'body', 'type' => 'bool', 'description' => 'Closes the page to search engines: a noindex robots tag, blocked in robots.txt and left out of the site map.'),
				array('name' => 'nofollow',         'in' => 'body', 'type' => 'bool', 'description' => 'Qualifies noindex. Only accepted while noindex is on.')
			)
		),

		array(
			'id'      => 'pages.delete',
			'method'  => 'DELETE',
			'path'    => '/pages/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_pages_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean', 'folder_id' => 'integer?'),
			'summary' => 'Delete a page',
			'description' => 'Moves the page to the recycle bin, where an operator can put it back. Send permanent=true to delete it outright with the whole cleanup the pages screen performs - regions, form tables, comments, short links and the stored findings - which cannot be undone. The home page and the pages the storefront and the account area are built from are refused either way. A default IIS install answers DELETE itself before PHP is reached: POST /pages/{id}/delete does the same thing and always arrives.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'query', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the recycle bin.')
			)
		),

		array(
			'id'      => 'pages.delete_post',
			'method'  => 'POST',
			'path'    => '/pages/{id}/delete',
			'scope'   => 'pages:write',
			'handler' => 'api_pages_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean', 'folder_id' => 'integer?'),
			'summary' => 'Delete a page, addressed as a POST',
			'description' => 'The same deletion as DELETE /pages/{id}, for clients that cannot send DELETE. It has an address of its own rather than sharing the one above, because POST /pages/{id} is already the update - a verb alias there would have made the update and the deletion the same request.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'body', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the recycle bin.')
			)
		),

		/* ----- Files -------------------------------------------------------- */

		array(
			'id'      => 'files.list',
			'method'  => 'GET',
			'path'    => '/files',
			'scope'   => 'files:read',
			'handler' => 'api_files_list',
			'returns' => array('list' => 'File'),
			'summary' => 'List files',
			'description' => 'The files the site serves to anyone. A folder that is guest, private, registration or membership is not listed: the panel decides those per user through the access control list, and an application is not a user.',
			'params'  => array(
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only files uploaded at or after this moment.'),
				array('name' => 'folder_id',     'in' => 'query', 'type' => 'int', 'min' => 0, 'description' => 'Files in one folder.'),
				array('name' => 'type',          'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'Extension without the dot, for example jpg.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name or the description.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'files.get',
			'method'  => 'GET',
			'path'    => '/files/{id}',
			'scope'   => 'files:read',
			'handler' => 'api_files_get',
			'returns' => 'File',
			'summary' => 'One file',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'files.create',
			'method'  => 'POST',
			'path'    => '/files',
			'scope'   => 'files:write',
			'handler' => 'api_files_create',
			'dry_run' => true,
			'returns' => 'File',
			'summary' => 'Upload a file',
			'description' => 'Images and PDFs only, and the bytes have to be what the name claims - the file is opened and checked rather than trusted. SVG is not accepted: it is markup, it can carry script, and the site serves it inline. The folder is the one the operator chose for applications and cannot be named in the request. Send the bytes as content_base64, or a source_url for this site to fetch - that fetch resolves the name once, pins the address it checked and refuses anything on this network.',
			'params'  => array(
				array('name' => 'name',           'in' => 'body', 'type' => 'string', 'max_length' => 190, 'description' => 'File name. The extension is optional: when it is left out it is taken from the address, or from the content when the address has none. The whole name is taken from source_url when it is left out. A name already in use gets a [1] suffix rather than replacing anything.'),
				array('name' => 'content_base64', 'in' => 'body', 'type' => 'string', 'description' => 'The file itself, base64 encoded. Send this or source_url.'),
				array('name' => 'source_url',     'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'An address for this site to fetch the file from. Send this or content_base64.'),
				array('name' => 'description',    'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'image_profile',  'in' => 'body', 'type' => 'enum', 'values' => array('product'), 'description' => 'product scales the image to the shop\'s own dimension and quality, the same as an upload from the product screen. Left out, the file is stored byte for byte.')
			)
		),

		array(
			'id'      => 'files.delete',
			'method'  => 'DELETE',
			'path'    => '/files/{id}',
			'scope'   => 'files:write',
			'handler' => 'api_files_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean?', 'recycled' => 'boolean', 'folder_id' => 'integer?'),
			'summary' => 'Remove a file',
			'description' => 'Moves the file to the recycle bin. The file stays on disk and its address keeps answering, because a page or a product refers to a file by name: an application removing the wrong one should not take part of the site down. There is no outright delete here; that stays in the file manager. A default IIS install answers DELETE itself before PHP is reached, so POST /files/{id}/delete does the same thing.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'files.delete_post',
			'method'  => 'POST',
			'path'    => '/files/{id}/delete',
			'scope'   => 'files:write',
			'handler' => 'api_files_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean?', 'recycled' => 'boolean', 'folder_id' => 'integer?'),
			'summary' => 'Remove a file, addressed as a POST',
			'description' => 'The same removal as DELETE /files/{id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Offers ------------------------------------------------------- */

		array(
			'id'      => 'offers.list',
			'method'  => 'GET',
			'path'    => '/offers',
			'scope'   => 'offers:read',
			'handler' => 'api_offers_list',
			'returns' => array('list' => 'Offer'),
			'summary' => 'List offers',
			'description' => 'An offer is a campaign rule applied to the cart at checkout - a discount, a gift product, free shipping - and not a sales quote; this resource is read only. offer_status is what the offer is doing today, derived from the enabled switch and the date range; incomplete marks a saved offer that cannot do anything at checkout yet. The conditions and the results are on the single-offer endpoint. Cursor paged.',
			'params'  => array(
				array('name' => 'status',        'in' => 'query', 'type' => 'enum', 'values' => array('active', 'scheduled', 'expired', 'disabled'), 'description' => 'Offers in one derived status: running today, not started yet, past their end date, or switched off.'),
				array('name' => 'code',          'in' => 'query', 'type' => 'string', 'max_length' => 50, 'description' => 'Exact match on the offer code.'),
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only offers saved at or after this moment.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'offers.get',
			'method'  => 'GET',
			'path'    => '/offers/{id}',
			'scope'   => 'offers:read',
			'handler' => 'api_offers_get',
			'returns' => 'Offer',
			'summary' => 'One offer, with its conditions and results',
			'description' => 'Adds the conditions the cart has to meet and what the offer then does, each flattened into readable objects: products and groups are named, amounts are minor units, percentages are 0-100, and an open-ended offer reports end_date null.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'offers.delete',
			'method'  => 'DELETE',
			'path'    => '/offers/{id}',
			'scope'   => 'offers:write',
			'handler' => 'api_offers_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'code' => 'string', 'deleted' => 'boolean', 'offer_status' => 'string'),
			'summary' => 'Delete an offer',
			'description' => 'The only write this resource has: offers can be read and removed, not created or edited. An offer is a rule tree - the conditions the cart has to meet and the results it then gets - and building one belongs with the editor that knows those rules. Removal takes the offer, its conditions, and the rule and result rows no other offer still uses. There is no recycle bin for offers, here or in the panel, so this cannot be undone. An offer whose offer_status is active is refused with 409 offer_active unless force=true is sent: an operator removing a live campaign is looking at the shop while they do it, and an application is not. A default IIS install answers DELETE itself before PHP is reached: POST /offers/{id}/delete does the same thing and always arrives.',
			'params'  => array(
				array('name' => 'id',    'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'force', 'in' => 'query', 'type' => 'bool', 'description' => 'Remove the offer even though it is running today.')
			)
		),

		array(
			'id'      => 'offers.delete_post',
			'method'  => 'POST',
			'path'    => '/offers/{id}/delete',
			'scope'   => 'offers:write',
			'handler' => 'api_offers_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'code' => 'string', 'deleted' => 'boolean', 'offer_status' => 'string'),
			'summary' => 'Delete an offer, addressed as a POST',
			'description' => 'The same removal as DELETE /offers/{id}, for clients that cannot send DELETE. It has an address of its own rather than sharing the one above, so that a verb alias could never turn some future POST /offers/{id} into a deletion.',
			'params'  => array(
				array('name' => 'id',    'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'force', 'in' => 'body', 'type' => 'bool', 'description' => 'Remove the offer even though it is running today.')
			)
		)

	);

	// What the modules add. Their rows are written the same way and are
	// appended, so the description, the console and the router see one list.
	// The seam exists so that a module's own endpoints are not written into this
	// file by a second pair of hands - see includes/api/modules.php.
	require_once(dirname(__FILE__) . '/modules.php');

	return array_merge($routes, api_module_contributions('api_routes'));

}

// Every code this API answers with, in one place.
//
// The code is the part a client branches on - the message is written for a
// person and may be translated or reworded, the code may not. Collected here so
// the documentation can print the list, and so that
// tools/check_api_schema.php can fail a build that invents a code without
// saying what it means.
//
// Not included: the 409s, which are named at the endpoint that can produce
// them, because 'order_already_shipped' means nothing on a page about pages.
function api_error_catalogue() {

	return array(

		'invalid_json'             => array(400, 'The body was not JSON, or was not an object.'),
		'invalid_cursor'           => array(400, 'The cursor was not one this API issued. Start the listing again without it.'),
		'invalid_idempotency_key'  => array(400, 'Idempotency-Key must be 8 to 255 characters.'),
		'invalid_dry_run'          => array(400, 'X-Dry-Run must be true or false.'),
		'dry_run_unsupported'      => array(400, 'This endpoint cannot rehearse a call. Send it without X-Dry-Run.'),

		'credentials_missing'      => array(401, 'No key and secret were sent. Authentication is HTTP Basic.'),
		'unauthorized'             => array(401, 'The key is unknown or the secret is wrong. The two are not told apart on purpose.'),

		'insufficient_scope'       => array(403, 'The application does not hold the permission this endpoint needs.'),
		'application_disabled'     => array(403, 'The application was switched off in the panel.'),
		'application_expired'      => array(403, 'The application reached the date it was set to stop working.'),
		'owner_unavailable'        => array(403, 'The account that owns the application is gone or cannot be used.'),
		'https_required'           => array(403, 'The site refuses API calls over plain HTTP.'),
		'ip_not_allowed'           => array(403, 'The address the call came from is not on the application allow list.'),
		'address_blocked'          => array(403, 'The firewall refused the address the call came from.'),
		'forbidden'                => array(403, 'The call is not allowed for a reason the other codes do not cover.'),

		'not_found'                => array(404, 'No record with that id.'),
		'no_path'                  => array(404, 'The address carried no resource. Try /meta.'),
		'unknown_endpoint'         => array(404, 'No endpoint answers that address.'),

		'method_not_allowed'       => array(405, 'The address exists but not with that verb.'),

		'idempotency_key_reused'   => array(409, 'The same Idempotency-Key was used for a different request.'),

		'file_too_large'           => array(413, 'The upload is over the limit reported by /meta.'),

		'validation_failed'        => array(422, 'A parameter is missing, the wrong type or out of range. The field is named in the answer.'),

		'rate_limited'             => array(429, 'Too many calls. Retry-After says how long to wait.'),

		'server_error'             => array(500, 'Something failed inside the site. The request_id identifies it in the log.'),

		'api_disabled'             => array(503, 'The API is switched off for this site.'),
		'not_installed'            => array(503, 'The site is not finished installing.'),
		'service_unavailable'      => array(503, 'The site is up but cannot answer right now.')

	);

}

// Routes with no scope requirement. The description of the API is not the API,
// but it does name every endpoint and parameter, so it is only served without
// credentials when the operator has deliberately published it - as JSON at
// /openapi.json and as the console's rendered endpoint list at /docs/endpoints.
// The console page itself is an empty shell around that list - it holds no
// endpoint of its own - so it is served to anyone. None of these appear in the
// generated document: api_openapi_build() reads api_schema() alone.
function api_open_routes() {

	return array(

		array(
			'id'      => 'openapi',
			'method'  => 'GET',
			'path'    => '/openapi.json',
			'scope'   => '',
			'handler' => 'api_openapi_document',
			'summary' => 'Machine-readable description of this API',
			'params'  => array()
		),

		array(
			'id'      => 'docs',
			'method'  => 'GET',
			'path'    => '/docs',
			'scope'   => '',
			'handler' => 'api_console_page',
			'summary' => 'Interactive console for this API',
			'description' => 'A page for a developer who holds an application key. Enter the key and the secret in the browser: the description is fetched with them and every endpoint can be called from the page, with the answer shown as it came back. Nothing is stored on the server - the credentials stay in the browser tab and are gone when it closes.',
			'params'  => array()
		),

		array(
			'id'      => 'docs.endpoints',
			'method'  => 'GET',
			'path'    => '/docs/endpoints',
			'scope'   => '',
			'handler' => 'api_console_endpoints',
			'summary' => 'The console\'s endpoint list, rendered',
			'description' => 'The same description as /openapi.json, as the HTML the console places on its page. Served under the same rule.',
			'params'  => array()
		)

	);

}
