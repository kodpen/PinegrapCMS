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
//   money     whole number of minor units, honours 'min' and 'max'; a decimal
//             is refused rather than rounded, because rounding someone's price
//             silently is worse than telling them the format is wrong
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
			'status'  => 201,
			'summary' => 'Add a product',
			'description' => 'Creates one product. name is the merchant SKU and has to be free: a name already in the catalogue is refused with 409 rather than quietly stored under a different one. Stock may be set here because the row does not exist yet and nothing can be racing it - afterwards it belongs to the inventory endpoint. Send an Idempotency-Key so a retried call does not leave two products behind.',
			'params'  => array(
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true, 'description' => 'The product id / SKU. Unique across the catalogue.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool',   'description' => 'Published. Off by default, so an import can be checked before it goes live.'),
				array('name' => 'price',             'in' => 'body', 'type' => 'money',  'min' => 0, 'max' => 2147483647, 'description' => 'Minor units. 1999 is 19.99.'),
				array('name' => 'compare_at_price',  'in' => 'body', 'type' => 'money',  'nullable' => true, 'min' => 0, 'max' => 2147483647, 'description' => 'The merchant\'s own earlier or list price of the article (a "was" price), in minor units like price. null, an empty value or 0 means none, which is also what leaving it out gives. It is not what the article sells at, and an offer does not change it.'),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'full_description',  'in' => 'body', 'type' => 'string'),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'brand',             'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'gtin',              'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'mpn',               'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'taxable',           'in' => 'body', 'type' => 'bool',   'description' => 'On by default, which is what the product screen starts from.'),
				array('name' => 'tax_rate',          'in' => 'body', 'type' => 'decimal', 'nullable' => true, 'min' => 0, 'max' => 100, 'description' => 'The article\'s own tax rate, as a percentage. Leave it out to follow the tax zone; zero is a different answer and means zero-rated.'),
				array('name' => 'shippable',         'in' => 'body', 'type' => 'bool',   'description' => 'On by default.'),
				array('name' => 'free_shipping',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'weight',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'length',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'width',             'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'height',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'notes',             'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'details',           'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long detail text the product page shows under the description.'),
				array('name' => 'google_product_category', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The Google product category, for a marketplace or shopping feed that asks for one.'),
				array('name' => 'inventory',         'in' => 'body', 'type' => 'bool',   'description' => 'Track stock for this product.'),
				array('name' => 'inventory_quantity','in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 1000000, 'description' => 'Opening stock. Only meaningful with inventory on.'),
				array('name' => 'group_ids',         'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'description' => 'Product groups this product belongs to. A variant is a product in the article\'s group - see the product-groups endpoint.'),
				array('name' => 'attributes',        'in' => 'body', 'type' => 'list', 'of' => array('attribute_id' => 'integer', 'option_id' => 'integer'), 'max_items' => 20, 'description' => 'Objects of {attribute_id, option_id}: what makes this row one variant rather than another. Both ids have to belong together; GET /product-attributes lists them.'),
				array('name' => 'images',            'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 50, 'description' => 'Images from the Files library, by file name or by the url the API gives for it. The first is the main photo, the rest the gallery in this order. Upload a new image with POST /files first.'),
				array('name' => 'barcode',           'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'The EAN or UPC printed on the box, in the site\'s barcode format. Only on a site with barcodes switched on; a code another product carries is refused.')
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
			'description' => 'Only the fields present in the body are written. name is the merchant SKU, as on create: a name another product has is refused with 409 name_taken and an empty one with 422; sending the product\'s own name again is fine. group_ids, attributes and images replace what the product has; an empty list removes all of them. Group membership can also be changed from the group\'s side with POST /product-groups/{id}/products. Stock is not changed here - use the inventory endpoint, which adjusts atomically. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached, and IIS then treats that address as a file that does not exist - the next POST to the same product falls through the site rewrite and comes back as the shop\'s 404 page. Use POST.',
			'params'  => array(
				array('name' => 'id',                'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'The product id / SKU. Unique across the catalogue.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'price',             'in' => 'body', 'type' => 'money', 'min' => 0, 'max' => 2147483647, 'description' => 'Minor units. 1999 is 19.99.'),
				array('name' => 'compare_at_price',  'in' => 'body', 'type' => 'money', 'nullable' => true, 'min' => 0, 'max' => 2147483647, 'description' => 'The merchant\'s own earlier or list price of the article (a "was" price), in minor units like price. Send null, an empty value or 0 to take it off. It is not what the article sells at, and an offer does not change it.'),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'full_description',  'in' => 'body', 'type' => 'string'),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 500),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'brand',             'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'gtin',              'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'mpn',               'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'taxable',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'tax_rate',          'in' => 'body', 'type' => 'decimal', 'nullable' => true, 'min' => 0, 'max' => 100, 'description' => 'The article\'s own tax rate, as a percentage. Send an empty value to clear it, which puts the product back on the rate of the tax zone; zero is a different answer and means zero-rated.'),
				array('name' => 'shippable',         'in' => 'body', 'type' => 'bool'),
				array('name' => 'free_shipping',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'weight',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'length',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'width',             'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'height',            'in' => 'body', 'type' => 'decimal', 'min' => 0, 'max' => 99999999.9999),
				array('name' => 'notes',             'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'details',           'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long detail text the product page shows under the description.'),
				array('name' => 'google_product_category', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The Google product category, for a marketplace or shopping feed that asks for one.'),
				array('name' => 'group_ids',         'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'description' => 'Product groups this product belongs to, replacing the current ones. A product in no group is not shown in the shop\'s catalogue tree.'),
				array('name' => 'attributes',        'in' => 'body', 'type' => 'list', 'of' => array('attribute_id' => 'integer', 'option_id' => 'integer'), 'max_items' => 20, 'description' => 'Objects of {attribute_id, option_id}, replacing the current ones: what makes this row one variant rather than another. Both ids have to belong together; GET /product-attributes lists them.'),
				array('name' => 'images',            'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 50, 'description' => 'Images from the Files library, by file name or by the url the API gives for it, replacing the current ones. The first is the main photo, the rest the gallery in this order. Upload a new image with POST /files first.'),
				array('name' => 'barcode',           'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'Gives a product that has no barcode yet its code. A barcode already on the product is not replaced, because labels may already carry it; that is changed on the product screen.')
			)
		),

		/* ----- Inventory ---------------------------------------------------- */

		array(
			'id'      => 'products.delete',
			'method'  => 'DELETE',
			'path'    => '/products/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_products_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a product',
			'description' => 'Moves the product to the catalogue\'s Recycle Bin, where an operator can put it back: it is switched off, leaves the listing and stays readable by id with recycled=true. Send permanent=true to delete it outright with the whole cleanup the product screen performs, which cannot be undone. Both send product.deleted, with recycled saying which it was. 409 recycle_bin_unavailable on a site without the bin, 409 already_recycled for a product already in it. A default IIS install answers DELETE itself before PHP is reached: POST /products/{id}/delete does the same thing and always arrives.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'query', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the Recycle Bin.')
			)
		),

		array(
			'id'      => 'products.delete_post',
			'method'  => 'POST',
			'path'    => '/products/{id}/delete',
			'scope'   => 'products:write',
			'handler' => 'api_products_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a product, addressed as a POST',
			'description' => 'The same deletion as DELETE /products/{id}, for clients and servers that cannot send DELETE. It has an address of its own because POST /products/{id} is already the update.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'body', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the Recycle Bin.')
			)
		),

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
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer', 'sku' => 'string', 'barcode' => 'string', 'op' => 'string', 'quantity' => 'integer'), 'max_items' => 200, 'required' => true, 'description' => 'Objects of {id, op, quantity}. sku is accepted as an alias for id; barcode looks the product up by its EAN/UPC. quantity is a whole number from -1000000 to 1000000, as for one product; an item outside that is reported as invalid and the others are applied.')
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
			'description' => 'The twin of the batch stock endpoint: one call for a repricing run instead of one call per product. Answers 200 with an outcome per item - ok, not_found or invalid - and applied says how many landed, because a product the other side has not noticed was deleted is data rather than a failure. Prices are whole minor units from 0 to 2147483647, the bound the single-product routes declare; a decimal is refused rather than rounded and a figure past the bound rather than cut. Only the price is written.',
			'params'  => array(
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer', 'sku' => 'string', 'barcode' => 'string', 'price' => 'integer'), 'required' => true, 'max_items' => 250, 'description' => 'Up to 250 objects: id, sku or barcode to say which product, and price in minor units (0 to 2147483647).')
			)
		),

		array(
			'id'      => 'products.price_history',
			'method'  => 'GET',
			'path'    => '/products/{id}/price-history',
			'scope'   => 'products:read',
			'handler' => 'api_products_price_history',
			'returns' => array('list' => 'PriceHistoryEntry'),
			'summary' => 'The price history of a product',
			'description' => 'Every change of the price the product sold at over the last thirteen months, oldest first and cursor paged: the price it became, in which currency, when, and what changed it (initial, panel, import, api, offer, erp). The first row of a product is initial, the price it had when the history started on this site. Rows older than thirteen months are removed by the site\'s scheduled job, except the few that still say what the price was at that point, so a longer history starts with those. An offer row is the price the open offers gave a visitor with no offer code, no account and an empty cart, recorded by the site\'s scheduled job within the hour an offer began, ended or changed; the other rows are the product\'s own price as its writers set it. The newest row at or before a moment is what such a visitor paid then (a price set while an offer runs is followed by its offer row within the hour). Pass since to read only the changes at or after a moment, which is how a client keeps a copy in step. A product in the Recycle Bin still answers; a site without the history table answers an empty list.',
			'params'  => array(
				array('name' => 'id',      'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'since',   'in' => 'query', 'type' => 'datetime', 'description' => 'Only changes recorded at or after this moment.'),
				array('name' => 'channel', 'in' => 'query', 'type' => 'string', 'max_length' => 64, 'description' => 'One sales channel: web (the shop\'s own price), pos, or marketplace:<code>. Every channel when left out.'),
				array('name' => 'limit',   'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
				array('name' => 'cursor',  'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.')
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
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Words that promote the group in the site search.'),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'description',       'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long description; full_description on the record.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool', 'description' => 'Published. Changing this reaches the child groups and the products in them.')
			)
		),

		array(
			'id'      => 'product_groups.create',
			'method'  => 'POST',
			'path'    => '/product-groups',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_create',
			'dry_run' => true,
			'returns' => 'ProductGroup',
			'status'  => 201,
			'summary' => 'Add a product group',
			'description' => 'A new category page under the parent named, or under the top of the catalogue when none is. It starts switched off unless enabled=true is sent, so a category an import is still filling does not appear in the navigation half-empty. The address is made from the short description or the name by the catalogue\'s own rule. A name another group already has is refused with 409 name_taken: the name is the group\'s code in the panel. Existing groups are not moved through the API.',
			'params'  => array(
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true),
				array('name' => 'parent_id',         'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The group to file it under. Omitted: the top of the catalogue.'),
				array('name' => 'enabled',           'in' => 'body', 'type' => 'bool', 'description' => 'Published. Off when not sent.'),
				array('name' => 'title',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'description',       'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long description; full_description on the record.'),
				array('name' => 'meta_description',  'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_keywords',     'in' => 'body', 'type' => 'string', 'max_length' => 2000),
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Words that promote the group in the site search.'),
				array('name' => 'product_ids',       'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 250, 'description' => 'Products to file in it. Each has to exist and not be in the Recycle Bin.')
			)
		),

		array(
			'id'      => 'product_groups.products',
			'method'  => 'POST',
			'path'    => '/product-groups/{id}/products',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_products',
			'dry_run' => true,
			'returns' => 'ProductGroup',
			'summary' => 'Put products into a group, or take them out',
			'description' => 'Both lists in one call, so re-filing a category\'s products leaves no moment where they are in neither group. A product added that is already there, or removed that is not, is not an error; added and removed in the answer count what actually changed. Products are never deleted here, only their link to this group.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'add',    'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 250, 'description' => 'Product ids to put into the group. Each has to exist and not be in the Recycle Bin.'),
				array('name' => 'remove', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 250, 'description' => 'Product ids to take out of the group.')
			)
		),

		array(
			'id'      => 'product_groups.delete',
			'method'  => 'DELETE',
			'path'    => '/product-groups/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a product group',
			'description' => 'Moves the group to the catalogue\'s Recycle Bin, where an operator can put it back. Send permanent=true to delete it outright, which cannot be undone. Its products are never deleted with it; they stay wherever else they are filed. The root group is refused (409 root_group_protected), and so is a group that still has groups inside it (409 group_not_empty; with permanent=true, groups of it in the Recycle Bin count too): a branch of the navigation is not removed from outside in one call. POST /product-groups/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'query', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the Recycle Bin.')
			)
		),

		array(
			'id'      => 'product_groups.delete_post',
			'method'  => 'POST',
			'path'    => '/product-groups/{id}/delete',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a product group, addressed as a POST',
			'description' => 'The same deletion as DELETE /product-groups/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'body', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the Recycle Bin.')
			)
		),

		/* ----- Product attributes ------------------------------------------ */

		array(
			'id'      => 'product_attributes.list',
			'method'  => 'GET',
			'path'    => '/product-attributes',
			'scope'   => 'products:read',
			'handler' => 'api_product_attributes_list',
			'returns' => array('list' => 'ProductAttribute'),
			'summary' => 'Product attributes',
			'description' => 'What variants are told apart by (Size, Colour), each with its options in the order the shop shows them, by id, cursor paged. name is the attribute\'s unique code, label what the shop shows. An option with no_value is the "no thanks" choice. products and product_groups count where it is used. These are the attribute_id and option_id values POST /products takes in attributes.',
			'params'  => array(
				array('name' => 'q',      'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the name or the label.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'product_attributes.get',
			'method'  => 'GET',
			'path'    => '/product-attributes/{id}',
			'scope'   => 'products:read',
			'handler' => 'api_product_attributes_get',
			'returns' => 'ProductAttribute',
			'summary' => 'One product attribute',
			'description' => 'The same fields as the list, for one attribute.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'product_attributes.create',
			'method'  => 'POST',
			'path'    => '/product-attributes',
			'scope'   => 'products:write',
			'handler' => 'api_product_attributes_create',
			'dry_run' => true,
			'returns' => 'ProductAttribute',
			'status'  => 201,
			'summary' => 'Create a product attribute',
			'description' => 'An attribute with its options, as the attribute screen makes it. The name has to be free (409 name_taken names the attribute that has it), and at least one option is needed. Then give products their option with POST /products or POST /products/{id}, attributes.',
			'params'  => array(
				array('name' => 'name',    'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true, 'description' => 'The attribute\'s unique code, such as size.'),
				array('name' => 'label',   'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'What the shop shows above the options, such as Choose a size.'),
				array('name' => 'options', 'in' => 'body', 'type' => 'list', 'of' => array('label' => 'string', 'no_value' => 'boolean'), 'max_items' => 100, 'required' => true, 'description' => 'Objects of {label, no_value}, in the order the shop shows them. Labels are distinct; no_value marks the "no thanks" choice.')
			)
		),

		array(
			'id'      => 'product_attributes.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/product-attributes/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_product_attributes_update',
			'dry_run' => true,
			'returns' => 'ProductAttribute',
			'summary' => 'Change a product attribute',
			'description' => 'Only what is sent is changed. options changes the options named by id and adds the ones without an id at the end; options left out stay as they are, so adding XL to a size attribute is options=[{"label":"XL"}]. An option goes only when its id is in remove_option_ids, and the products that came in it lose that option, as on the attribute screen. The attribute keeps at least one option, and no two options may read the same. Reordering options stays on the attribute screen. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',                'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'label',             'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'options',           'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer?', 'label' => 'string', 'no_value' => 'boolean'), 'max_items' => 100, 'description' => 'Objects of {id, label, no_value}, each with its label: with the id of one of its options to rename it or change no_value, without one to add an option.'),
				array('name' => 'remove_option_ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'description' => 'Ids of its options to remove, together with every product\'s link to them.')
			)
		),

		array(
			'id'      => 'product_attributes.delete',
			'method'  => 'DELETE',
			'path'    => '/product-attributes/{id}',
			'scope'   => 'products:write',
			'handler' => 'api_product_attributes_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'products' => 'integer', 'product_groups' => 'integer'),
			'summary' => 'Delete a product attribute',
			'description' => 'Removes the attribute and its options and takes it off every product and product group, as the attribute screen does; it cannot be undone. An attribute that a product or a product group uses is refused with 409 attribute_in_use unless force=true is sent. The answer counts the products and groups it was taken off. POST /product-attributes/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id',    'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'force', 'in' => 'query', 'type' => 'bool', 'description' => 'Delete it even though products or product groups use it.')
			)
		),

		array(
			'id'      => 'product_attributes.delete_post',
			'method'  => 'POST',
			'path'    => '/product-attributes/{id}/delete',
			'scope'   => 'products:write',
			'handler' => 'api_product_attributes_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'products' => 'integer', 'product_groups' => 'integer'),
			'summary' => 'Delete a product attribute, addressed as a POST',
			'description' => 'The same deletion as DELETE /product-attributes/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id',    'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'force', 'in' => 'body', 'type' => 'bool', 'description' => 'Delete it even though products or product groups use it.')
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
			'description' => 'The status and the internal note. Cancelling records who did it and why, the same way the order screen does. Moving a cancelled order to complete or exported is the order screen\'s Restore: the cancellation and an unfinished refund flag are cleared and the gift cards the cancellation expired get their date back. incomplete is the basket\'s status and is not accepted: an order is not turned back into a basket, and a basket (status incomplete) cannot be completed, exported or cancelled here - 422. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached, and IIS then treats that address as a file that does not exist - the next POST to the same product falls through the site rewrite and comes back as the shop\'s 404 page. Use POST.',
			'params'  => array(
				array('name' => 'id',                  'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'status',              'in' => 'body', 'type' => 'enum', 'values' => array('complete', 'exported', 'cancelled')),
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
				array('name' => 'tracking_numbers', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'description' => 'The complete list for this address, as a JSON array. An empty list removes the numbers it has; left out, the numbers stay as they are, so a call that only reports a date keeps them.'),
				array('name' => 'ship_to_id',       'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Which shipping address, from the shipments block of GET /orders/{id}. Optional while the order ships to one address; required when it ships to several.'),
				array('name' => 'carrier',          'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'Written only when the address has no shipping method code yet: that code is the method the customer chose and paid for, and a fulfilment system naming the carrier it used must not overwrite it. Any text is accepted, but one of yurtici, surat, aras, mng, ptt, ups, fedex or usps is what turns the tracking number into a link the customer can follow.'),
				array('name' => 'ship_date',        'in' => 'body', 'type' => 'datetime', 'description' => 'Kept as a date. Nothing in the store reads it as proof of dispatch.'),
				array('name' => 'delivery_date',    'in' => 'body', 'type' => 'datetime'),
				array('name' => 'notify',           'in' => 'body', 'type' => 'bool', 'description' => 'true sends the store\'s "your order has shipped" mail to the customer and, for an order placed with an account, also starts the store\'s review request e-mail. Left out it does not: a backfill of last month\'s shipments must not mail a month of customers.')
			)
		),

		array(
			'id'      => 'orders.documents',
			'method'  => 'GET',
			'path'    => '/orders/{id}/documents',
			'scope'   => 'orders:read',
			'handler' => 'api_orders_documents',
			'returns' => array('list' => 'OrderDocument'),
			'summary' => 'The legal documents kept with an order',
			'description' => 'The pre-information form and the distance sales contract the customer accepted at checkout, each as the HTML that was shown and stored with the order, with when it was accepted and from which address. The copy never changes after the order is placed, so it is the record of what was agreed to even after the templates are edited. One page, at most one document per type; an order placed before the documents were switched on, or on a store that never switched them on, has an empty list. Use ?fields=type,accepted,accepted_at to skip the HTML.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'orders.returns',
			'method'  => 'GET',
			'path'    => '/orders/{id}/returns',
			'scope'   => 'orders:read',
			'handler' => 'api_orders_returns',
			'returns' => array('list' => 'OrderReturn'),
			'summary' => 'The return requests of an order',
			'description' => 'Every return request of the order, newest first, each with the order lines it sends back (order_item_id is the id of a line in GET /orders/{id}), its status and when each status was reached. status is requested, approved, rejected, shipped (the goods are on their way back), received, refunded or closed; source says who asked: customer (My Account), panel or api. The amount and the way a return was refunded are not kept on the return: they come only with the order.return.refunded event (amount, refund_method, and unclear when the payment service gave no clear answer). One page, as an order has few returns; an order with none, or a site not yet updated to the version that keeps returns, has an empty list.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'orders.returns.create',
			'method'  => 'POST',
			'path'    => '/orders/{id}/returns',
			'scope'   => 'orders:write',
			'handler' => 'api_orders_returns_create',
			'dry_run' => true,
			'returns' => 'OrderReturn',
			'status'  => array(201, 200),
			'summary' => 'Record a return request',
			'description' => 'Records a request to send back goods of one delivery of a completed or exported order, as the customer asks for one in My Account, with source api. It is recorded for the shop, so the customer\'s timing rules are not checked: neither the withdrawal period, nor that the goods have left, nor that a delivery date is known. The panel shows whether the request came in time, and staff decide; a delivery not yet sent is normally cancelled instead. No more of a line can be asked for than is left on it after the order\'s other returns. Staff are told on the panel bell; the customer is mailed only when notify is true. Announced as order.return.requested. The same request sent again by the same account within 60 seconds is answered with the return it repeats, with 200 instead of 201, and nothing else happens. 409 order_not_returnable: the order is not complete or exported. 409 no_shipping_address: none of the order\'s goods went to an address, so there is nothing to send back. 409 return_in_progress: another return of the order is being saved; send the call again in a moment. 503 service_unavailable: the site has not been updated to the version that keeps returns.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'ship_to_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The delivery the goods came in, from the shipments block of GET /orders/{id}. Optional while the order\'s goods went to one address; required when they went to several.'),
				array('name' => 'items',      'in' => 'body', 'type' => 'list', 'of' => array('order_item_id' => 'integer', 'quantity' => 'integer'), 'max_items' => 200, 'required' => true, 'description' => 'Objects of {order_item_id, quantity}: lines of that delivery, each once - the lines in items of GET /orders/{id} whose ship_to_id is the delivery\'s (a line with a null ship_to_id went to no address and cannot be returned) - and how many of each go back, at most what is left on the line after the order\'s other returns.'),
				array('name' => 'reason',     'in' => 'body', 'type' => 'enum', 'values' => array_keys(pg_return_reasons()), 'required' => true, 'description' => 'Why the goods go back.'),
				array('name' => 'note',       'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'The customer\'s words. Required with reason other.'),
				array('name' => 'notify',     'in' => 'body', 'type' => 'bool', 'description' => 'true sends the customer the "we received your return request" mail My Account sends. Left out it does not.')
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
			'description' => 'From the manager role up an application reaches every customer; one whose owner has the user role reaches only those filed in the contact groups given to that account, as on the contact screens, and every customers route answers 404 for the rest.',
			'params'  => array(
				array('name' => 'search',        'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name, the email address or the member number.'),
				array('name' => 'email',         'in' => 'query', 'type' => 'string', 'max_length' => 190),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'customers.delete',
			'method'  => 'DELETE',
			'path'    => '/customers/{id}',
			'scope'   => 'customers:write',
			'handler' => 'api_customers_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a customer',
			'description' => 'Removes the address-book record with its contact groups and opt-in rows, as the contact screen does - for an erasure request received outside the panel. It cannot be undone. Orders keep their own copy of the billing and shipping details. A contact that is a user account\'s own is refused (409 customer_has_account), and so is one linked to an ERP account (409 customer_has_erp_account). Sends customer.deleted. POST /customers/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'customers.delete_post',
			'method'  => 'POST',
			'path'    => '/customers/{id}/delete',
			'scope'   => 'customers:write',
			'handler' => 'api_customers_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a customer, addressed as a POST',
			'description' => 'The same deletion as DELETE /customers/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Store set-up ------------------------------------------------- */

		array(
			'id'      => 'store.shipping_methods',
			'method'  => 'GET',
			'path'    => '/store/shipping-methods',
			'scope'   => 'orders:read',
			'handler' => 'api_store_shipping_methods_list',
			'returns' => array('list' => 'StoreShippingMethod'),
			'summary' => 'Shipping methods',
			'description' => 'Every shipping method, in one page, with what it charges and when it is offered. Money is in minor units. What a recipient pays is the base rate (the tier whose from_subtotal the recipient\'s subtotal has reached), plus for each item primary_weight_rate x the product\'s primary weight points, secondary_weight_rate x its secondary weight points and item_rate, each times the quantity (first_item_excluded leaves the first item out of that charge), plus the same four rates of the zone the address is in, plus a carrier\'s live quote where realtime_rate is on. A method is offered while enabled, inside available_from/available_until, on the days in offered_on until their cut-off time, and for the address types it allows; a protected method is offered to the store\'s staff only.',
			'params'  => array(
				array('name' => 'enabled', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'store.shipping_method_get',
			'method'  => 'GET',
			'path'    => '/store/shipping-methods/{id}',
			'scope'   => 'orders:read',
			'handler' => 'api_store_shipping_methods_get',
			'returns' => 'StoreShippingMethod',
			'summary' => 'One shipping method',
			'description' => 'The same object as in the list.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'store.zones',
			'method'  => 'GET',
			'path'    => '/store/zones',
			'scope'   => 'orders:read',
			'handler' => 'api_store_zones_list',
			'returns' => array('list' => 'StoreZone'),
			'summary' => 'Shipping zones',
			'description' => 'The areas shipping methods ship to (zone_ids on a method), each with the countries (ISO codes) and states it covers and the rates it adds to the method\'s own. One page.',
			'params'  => array()
		),

		array(
			'id'      => 'store.tax_zones',
			'method'  => 'GET',
			'path'    => '/store/tax-zones',
			'scope'   => 'orders:read',
			'handler' => 'api_store_tax_zones_list',
			'returns' => array('list' => 'StoreTaxZone'),
			'summary' => 'Tax zones',
			'description' => 'The areas tax is charged in, each with its rate as a percentage and the countries and states it covers. An address in no tax zone is charged no tax. GET /meta carries the rate of the store\'s own country. One page.',
			'params'  => array()
		),

		array(
			'id'      => 'store.countries',
			'method'  => 'GET',
			'path'    => '/store/countries',
			'scope'   => 'orders:read',
			'handler' => 'api_store_countries_list',
			'returns' => array('list' => 'StoreCountry'),
			'summary' => 'Countries and their states',
			'description' => 'The countries an address can name, with their states, as the address forms offer them. default is the country the forms select first. One page.',
			'params'  => array(
				array('name' => 'code', 'in' => 'query', 'type' => 'string', 'max_length' => 50, 'description' => 'Only the country with this code.')
			)
		),

		array(
			'id'      => 'store.currencies',
			'method'  => 'GET',
			'path'    => '/store/currencies',
			'scope'   => 'orders:read',
			'handler' => 'api_store_currencies_list',
			'returns' => array('list' => 'StoreCurrency'),
			'summary' => 'Currencies',
			'description' => 'The currencies a visitor can see prices in, the base currency first. exchange_rate is how much of the currency one unit of the base currency buys. Orders are always charged in the base currency. One page.',
			'params'  => array()
		),

		array(
			'id'      => 'store.feeds',
			'method'  => 'GET',
			'path'    => '/store/feeds',
			'scope'   => 'products:read',
			'handler' => 'api_store_feeds_list',
			'returns' => array('list' => 'StoreFeed'),
			'summary' => 'Product feeds',
			'description' => 'The product feeds the store publishes for shopping platforms: openai_jsonl and openai_csv (the OpenAI product feed) and google_tsv (Google Merchant Center), always in that order. url is the private address a platform reads the feed from (null until the feed has one); give it to the platform, never publish it. The feeds are switched on and set up under Settings, SEO, Product Feeds and rebuilt once a day. state is off, ok, stale (never built, or not for two days) or failing (the last build failed with error; the file built before is still served). products counts the products in the last good build; skipped says how many were left out and why (no_address, donation, recurring, no_price, not_in_catalog, excluded_group, no_title, no_brand, no_image). One page.',
			'params'  => array()
		),

		/* ----- E-mail campaigns -------------------------------------------- */

		array(
			'id'      => 'campaigns.list',
			'method'  => 'GET',
			'path'    => '/campaigns',
			'scope'   => 'campaigns:read',
			'handler' => 'api_campaigns_list',
			'returns' => array('list' => 'Campaign'),
			'summary' => 'E-mail campaigns',
			'description' => 'Newest first, cursor paged, with how far sending has got (recipients: total, sent, failed - the job gave up after its retries - and pending). status: ready (waiting for send_at, or being sent), paused, cancelled or complete. type: manual (sent to contact groups) or automatic (made by a campaign profile when an order, reservation or form came in; profile_id, action and action_item_id say which). An account with the e-mail right but the user role sees only the campaigns it created, as on the campaign screens.',
			'params'  => array(
				array('name' => 'status',        'in' => 'query', 'type' => 'enum', 'values' => array('ready', 'paused', 'cancelled', 'complete')),
				array('name' => 'type',          'in' => 'query', 'type' => 'enum', 'values' => array('manual', 'automatic')),
				array('name' => 'purpose',       'in' => 'query', 'type' => 'enum', 'values' => array('commercial', 'transactional')),
				array('name' => 'profile_id',    'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the campaigns this campaign profile made.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the subject.'),
				array('name' => 'created_since', 'in' => 'query', 'type' => 'datetime'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'campaigns.get',
			'method'  => 'GET',
			'path'    => '/campaigns/{id}',
			'scope'   => 'campaigns:read',
			'handler' => 'api_campaigns_get',
			'returns' => 'Campaign',
			'summary' => 'One e-mail campaign',
			'description' => 'With the message (body: the text of a plain-text campaign, or for an HTML one the page as it was rendered when the campaign was made) and, for a manual campaign, the contact groups it was put together from: contact_group_ids it went to, excluded_contact_group_ids whose members were left out.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'campaigns.recipients',
			'method'  => 'GET',
			'path'    => '/campaigns/{id}/recipients',
			'scope'   => 'campaigns:read',
			'handler' => 'api_campaigns_recipients',
			'returns' => array('list' => 'CampaignRecipient'),
			'summary' => 'Who a campaign goes to',
			'description' => 'In the order the job sends them, cursor paged: the address, the contact it came from, sent, pending or failed, how many attempts were made and why the last one did not go through.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('sent', 'pending', 'failed')),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'campaigns.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/campaigns/{id}',
			'scope'   => 'campaigns:write',
			'handler' => 'api_campaigns_update',
			'dry_run' => true,
			'returns' => 'Campaign',
			'summary' => 'Hold, release, cancel or correct a campaign',
			'description' => 'Only what is sent is changed, as on the campaign screen. status paused holds it, ready lets the scheduled e-mail job send it (again), cancelled stops it; recipients already sent stay sent. Nothing is sent by this call: a ready campaign goes out when the job reaches it, and where the site runs no job it waits for Send Campaign on its screen. A sent (complete) campaign cannot be changed (409 campaign_complete). The body of an HTML campaign is the page as it was rendered and cannot be changed (409 campaign_body_is_a_page); bcc is for automatic campaigns only (409 campaign_bcc_automatic_only). A campaign is put together, recipients and all, on its own screen. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'status',     'in' => 'body', 'type' => 'enum', 'values' => array('ready', 'paused', 'cancelled')),
				array('name' => 'subject',    'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'body',       'in' => 'body', 'type' => 'string', 'max_length' => 200000, 'description' => 'The text of a plain-text campaign.'),
				array('name' => 'from_name',  'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'from_email', 'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'reply_to',   'in' => 'body', 'type' => 'string', 'max_length' => 100, 'nullable' => true, 'description' => 'An empty string removes it.'),
				array('name' => 'bcc',        'in' => 'body', 'type' => 'string', 'max_length' => 100, 'nullable' => true, 'description' => 'Automatic campaigns only. An empty string removes it.'),
				array('name' => 'send_at',    'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'When the job may start sending it. An empty string means as soon as the job runs.'),
				array('name' => 'purpose',    'in' => 'body', 'type' => 'enum', 'values' => array('commercial', 'transactional'), 'description' => 'commercial skips contacts who have opted out and carries the organisation and e-mail preferences footer; transactional goes to every recipient.')
			)
		),

		array(
			'id'      => 'campaigns.delete',
			'method'  => 'DELETE',
			'path'    => '/campaigns/{id}',
			'scope'   => 'campaigns:write',
			'handler' => 'api_campaigns_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a campaign',
			'description' => 'Removes the campaign with its recipients and contact groups, as the campaign screens do; whatever was already sent stays sent and nothing more goes out. It cannot be undone. POST /campaigns/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'campaigns.delete_post',
			'method'  => 'POST',
			'path'    => '/campaigns/{id}/delete',
			'scope'   => 'campaigns:write',
			'handler' => 'api_campaigns_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a campaign, addressed as a POST',
			'description' => 'The same deletion as DELETE /campaigns/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Contact groups ---------------------------------------------- */

		array(
			'id'      => 'contact_groups.list',
			'method'  => 'GET',
			'path'    => '/contact-groups',
			'scope'   => 'contact_groups:read',
			'handler' => 'api_contact_groups_list',
			'returns' => array('list' => 'ContactGroup'),
			'summary' => 'Contact groups',
			'description' => 'The lists campaigns are sent to, by id, cursor paged, each with how many contacts it holds. email_subscription says whether campaigns may go to it and its members choose whether they get them; subscription_type open offers it to every contact on the e-mail preferences page, closed only to its members. An account with the contacts right but the user role sees only the groups given to it, as on the contact screens.',
			'params'  => array(
				array('name' => 'email_subscription', 'in' => 'query', 'type' => 'bool', 'description' => 'Only the groups that take e-mail subscriptions, or only the ones that do not.'),
				array('name' => 'q',      'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the name or the description.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'contact_groups.get',
			'method'  => 'GET',
			'path'    => '/contact-groups/{id}',
			'scope'   => 'contact_groups:read',
			'handler' => 'api_contact_groups_get',
			'returns' => 'ContactGroup',
			'summary' => 'One contact group',
			'description' => 'The same fields as the list, for one group.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'contact_groups.create',
			'method'  => 'POST',
			'path'    => '/contact-groups',
			'scope'   => 'contact_groups:write',
			'handler' => 'api_contact_groups_create',
			'dry_run' => true,
			'returns' => 'ContactGroup',
			'status'  => 201,
			'summary' => 'Create a contact group',
			'description' => 'An empty group, as the contact group screen makes it. The name has to be free, whatever its case (409 name_taken names the group that has it). Only an account from the manager role up may create one (403 forbidden otherwise), as on the contact group screens. Put contacts in with POST /contact-groups/{id}/members.',
			'params'  => array(
				array('name' => 'name',               'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true),
				array('name' => 'description',        'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Shown with the group on the e-mail preferences page when it takes subscriptions.'),
				array('name' => 'email_subscription', 'in' => 'body', 'type' => 'bool', 'description' => 'Campaigns may be sent to it, and its members choose whether they get them. Off when left out.'),
				array('name' => 'subscription_type',  'in' => 'body', 'type' => 'enum', 'values' => array('open', 'closed'), 'description' => 'open offers the group to every contact on the e-mail preferences page, closed only to its members. open when left out.')
			)
		),

		array(
			'id'      => 'contact_groups.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/contact-groups/{id}',
			'scope'   => 'contact_groups:write',
			'handler' => 'api_contact_groups_update',
			'dry_run' => true,
			'returns' => 'ContactGroup',
			'summary' => 'Rename or change a contact group',
			'description' => 'Only what is sent is changed. A new name has to be free (409 name_taken). Switching email_subscription off keeps the members\' choices for the group\'s e-mail, so switching it back on finds them again; subscription_type is kept while it is off. An account with the user role may change the groups given to it, as on the group screen. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',                 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',               'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'description',        'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'email_subscription', 'in' => 'body', 'type' => 'bool'),
				array('name' => 'subscription_type',  'in' => 'body', 'type' => 'enum', 'values' => array('open', 'closed'))
			)
		),

		array(
			'id'      => 'contact_groups.delete',
			'method'  => 'DELETE',
			'path'    => '/contact-groups/{id}',
			'scope'   => 'contact_groups:write',
			'handler' => 'api_contact_groups_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a contact group',
			'description' => 'Only an empty group is removed, as on the contact group screen: one that still holds contacts is refused with 409 contact_group_has_members, so take them out first with POST /contact-groups/{id}/members. The accounts it was given to, the members\' choices kept for it and the campaigns\' record of having gone to it go with it; campaigns already sent stay sent. It cannot be undone. POST /contact-groups/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'contact_groups.delete_post',
			'method'  => 'POST',
			'path'    => '/contact-groups/{id}/delete',
			'scope'   => 'contact_groups:write',
			'handler' => 'api_contact_groups_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a contact group, addressed as a POST',
			'description' => 'The same deletion as DELETE /contact-groups/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'contact_groups.members',
			'method'  => 'GET',
			'path'    => '/contact-groups/{id}/members',
			'scope'   => 'contact_groups:read',
			'handler' => 'api_contact_groups_members',
			'returns' => array('list' => 'ContactGroupMember'),
			'summary' => 'Who is in a contact group',
			'description' => 'By contact id, cursor paged: the contact, its own switch for all e-mail campaigns (email_opt_in) and, in a group that takes subscriptions, its choice for this group\'s e-mail (opt_in; a contact that never chose counts as opted in, as the campaign screen reads it). A campaign put together from the group reaches a member only when both are on. The full address book record is GET /customers/{id}.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'email',  'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Only the member with this exact address.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'contact_groups.members_update',
			'method'  => 'POST',
			'path'    => '/contact-groups/{id}/members',
			'scope'   => 'contact_groups:write',
			'handler' => 'api_contact_groups_members_update',
			'dry_run' => true,
			'returns' => 'ContactGroup',
			'summary' => 'Put contacts into a group, or take them out',
			'description' => 'Both lists in one call, as the contact screen ticks a group. A contact added that is already there, or removed that is not, is not an error; added and removed in the answer count what actually changed, and the contacts that actually moved are announced as contact_group.members_changed. Contacts are never deleted here, only their place in this group. opt_in records the choice for this group\'s e-mail of every contact in add, members already there included, so an unsubscribe from a newsletter tool is add=[id] with opt_in=false; it is ignored for a group that takes no subscriptions. Without it nothing is written: a new member counts as opted in, and a contact who opted out before and is put back stays opted out. Taking a contact out keeps its choice, as the contact screen does. Find a contact by address with GET /customers?email=, or make one with POST /customers first.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'add',    'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 250, 'description' => 'Contact ids to put into the group. Each has to exist.'),
				array('name' => 'remove', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 250, 'description' => 'Contact ids to take out of the group.'),
				array('name' => 'opt_in', 'in' => 'body', 'type' => 'bool', 'description' => 'The choice for this group\'s e-mail, written for every contact in add. Only for a group that takes subscriptions.')
			)
		),

		/* ----- Site log ----------------------------------------------------- */

		array(
			'id'      => 'site_log.list',
			'method'  => 'GET',
			'path'    => '/site-log',
			'scope'   => 'site_log:read',
			'handler' => 'api_site_log_list',
			'returns' => array('list' => 'SiteLogEntry'),
			'summary' => 'Site log',
			'description' => 'What was done on the site, newest first, cursor paged, as the Site Log screen lists it: the sentence it was logged with (in the site\'s language), the account that did it and the address it came from. user is null for what visitors and the site\'s own jobs did. Read only. The PHP error logs on the same screen are not here.',
			'params'  => array(
				array('name' => 'from',   'in' => 'query', 'type' => 'datetime'),
				array('name' => 'to',     'in' => 'query', 'type' => 'datetime'),
				array('name' => 'user',   'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Only what this username did.'),
				array('name' => 'q',      'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the description.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		/* ----- Gift cards -------------------------------------------------- */

		array(
			'id'      => 'gift_cards.list',
			'method'  => 'GET',
			'path'    => '/gift-cards',
			'scope'   => 'gift_cards:read',
			'handler' => 'api_gift_cards_list',
			'returns' => array('list' => 'GiftCard'),
			'summary' => 'Gift cards',
			'description' => 'Newest first, cursor paged. Money is in minor units. status: active (balance left and not expired), used (nothing left) or expired (past expires_on, whatever is left on it). A card bought in an order carries order_id, from_name, recipient_email, message and delivery_date. code is the money itself: whoever holds it can spend the balance at checkout.',
			'params'  => array(
				array('name' => 'status',        'in' => 'query', 'type' => 'enum', 'values' => array('active', 'used', 'expired')),
				array('name' => 'order_id',      'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the cards bought in this order.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the code (with or without its dashes), the notes, who it is from or the recipient\'s e-mail address.'),
				array('name' => 'created_since', 'in' => 'query', 'type' => 'datetime'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'gift_cards.get',
			'method'  => 'GET',
			'path'    => '/gift-cards/{id}',
			'scope'   => 'gift_cards:read',
			'handler' => 'api_gift_cards_get',
			'returns' => 'GiftCard',
			'summary' => 'One gift card',
			'description' => 'With redemptions: each order the card was spent in, how much, and the balance before and after.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'gift_cards.create',
			'method'  => 'POST',
			'path'    => '/gift-cards',
			'scope'   => 'gift_cards:write',
			'handler' => 'api_gift_cards_create',
			'dry_run' => true,
			'returns' => 'GiftCard',
			'status'  => 201,
			'summary' => 'Issue a gift card',
			'description' => 'One card worth amount, with a new sixteen-digit code, as the gift cards screen issues it - for a prize, a refund or a card sold outside the store. The answer carries the code; nothing is mailed to anyone.',
			'params'  => array(
				array('name' => 'amount',     'in' => 'body', 'type' => 'money', 'min' => 1, 'max' => 4294967295, 'required' => true, 'description' => 'Minor units, greater than zero. The balance starts at the same amount.'),
				array('name' => 'expires_on', 'in' => 'body', 'type' => 'datetime', 'description' => 'The last day it can be spent. Left out, it never expires.'),
				array('name' => 'notes',      'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'For the staff only.')
			)
		),

		array(
			'id'      => 'gift_cards.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/gift-cards/{id}',
			'scope'   => 'gift_cards:write',
			'handler' => 'api_gift_cards_update',
			'dry_run' => true,
			'returns' => 'GiftCard',
			'summary' => 'Change a gift card',
			'description' => 'Only what is sent is changed: the balance, the expiry and the notes, as on the gift cards screen. The amount it was issued for and its code stay. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'balance',    'in' => 'body', 'type' => 'money', 'min' => 0, 'max' => 4294967295, 'description' => 'Minor units, zero or more.'),
				array('name' => 'expires_on', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'An empty string removes the expiry.'),
				array('name' => 'notes',      'in' => 'body', 'type' => 'string', 'max_length' => 2000)
			)
		),

		array(
			'id'      => 'gift_cards.delete',
			'method'  => 'DELETE',
			'path'    => '/gift-cards/{id}',
			'scope'   => 'gift_cards:write',
			'handler' => 'api_gift_cards_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a gift card',
			'description' => 'Removes the card, as the gift cards screen does; its code stops working at once and it cannot be undone. The orders it was spent in keep their record of the spend. POST /gift-cards/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'gift_cards.delete_post',
			'method'  => 'POST',
			'path'    => '/gift-cards/{id}/delete',
			'scope'   => 'gift_cards:write',
			'handler' => 'api_gift_cards_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a gift card, addressed as a POST',
			'description' => 'The same deletion as DELETE /gift-cards/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Commissions -------------------------------------------------- */

		array(
			'id'      => 'commissions.list',
			'method'  => 'GET',
			'path'    => '/commissions',
			'scope'   => 'commissions:read',
			'handler' => 'api_commissions_list',
			'returns' => array('list' => 'Commission'),
			'summary' => 'Affiliate commissions',
			'description' => 'What the affiliate program owes, cursor paged and ordered by when each commission last changed, so updated_since picks up the ones whose status moved since the last sync. Money is in minor units. status: pending, payable, ineligible or paid, set on the commissions screen; paying the affiliate happens outside the site. A commission on an order carries order_id; a recurring one is written once a period from its recurring profile and carries period and product_name. affiliate_contact_id is the affiliate\'s record under GET /customers/{id}. Read only.',
			'params'  => array(
				array('name' => 'status',         'in' => 'query', 'type' => 'enum', 'values' => array('pending', 'payable', 'ineligible', 'paid')),
				array('name' => 'affiliate_code', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Only the commissions of this affiliate.'),
				array('name' => 'reference_code', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'The commission with this reference code.'),
				array('name' => 'order_id',       'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the commissions on this order.'),
				array('name' => 'created_since',  'in' => 'query', 'type' => 'datetime', 'description' => 'Only commissions written at or after this moment.'),
				array('name' => 'created_before', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only commissions written before this moment.'),
				array('name' => 'updated_since',  'in' => 'query', 'type' => 'datetime', 'description' => 'Only commissions written or changed at or after this moment.'),
				array('name' => 'limit',          'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',         'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'commissions.get',
			'method'  => 'GET',
			'path'    => '/commissions/{id}',
			'scope'   => 'commissions:read',
			'handler' => 'api_commissions_get',
			'returns' => 'Commission',
			'summary' => 'One affiliate commission',
			'description' => 'The same fields as the list, for one commission.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Calendars ---------------------------------------------------- */

		array(
			'id'      => 'calendars.list',
			'method'  => 'GET',
			'path'    => '/calendars',
			'scope'   => 'calendars:read',
			'handler' => 'api_calendars_list',
			'returns' => array('list' => 'Calendar'),
			'summary' => 'The site\'s calendars',
			'description' => 'Every calendar the application\'s owner may open - all of them for roles 0 to 2, the assigned ones for a user with the calendars right - with how many events each holds. One page; a site has a handful. 503 service_unavailable where the site does not run its calendars.',
			'params'  => array()
		),

		array(
			'id'      => 'calendars.events',
			'method'  => 'GET',
			'path'    => '/calendars/events',
			'scope'   => 'calendars:read',
			'handler' => 'api_calendar_events_list',
			'returns' => array('list' => 'CalendarEvent'),
			'summary' => 'Events on the calendars',
			'description' => 'In date order, cursor paged. A repeating event is answered once, with its first start and end and its recurrence rule: unit (day, week, month, year), times (how many repeats follow the first), days (the weekdays a daily or weekly repeat is limited to) and month_by (date or weekday). skipped_occurrences numbers the repeats that were cancelled. Times are the site\'s wall-clock times written with its offset. Only events on calendars the owner may open are listed.',
			'params'  => array(
				array('name' => 'calendar_id',   'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only events on this calendar.'),
				array('name' => 'published',     'in' => 'query', 'type' => 'bool'),
				array('name' => 'upcoming',      'in' => 'query', 'type' => 'bool', 'description' => 'Only events that have not ended yet, and repeating events.'),
				array('name' => 'starts_after',  'in' => 'query', 'type' => 'datetime', 'description' => 'First start at or after this moment.'),
				array('name' => 'starts_before', 'in' => 'query', 'type' => 'datetime', 'description' => 'First start before this moment.'),
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime'),
				array('name' => 'search',        'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the name or the location.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'calendars.event_get',
			'method'  => 'GET',
			'path'    => '/calendars/events/{id}',
			'scope'   => 'calendars:read',
			'handler' => 'api_calendar_events_get',
			'returns' => 'CalendarEvent',
			'summary' => 'One event',
			'description' => 'With its long description. An event on none of the calendars the owner may open is answered 404.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'calendars.event_create',
			'method'  => 'POST',
			'path'    => '/calendars/events',
			'scope'   => 'calendars:write',
			'handler' => 'api_calendar_events_create',
			'dry_run' => true,
			'returns' => 'CalendarEvent',
			'status'  => 201,
			'summary' => 'Add an event',
			'description' => 'A one-off event on the calendars named, unpublished unless published=true is sent. An all-day event runs from the start of its first day to the end of its last; any other event has to end after it starts. Times without an offset are read as the site\'s wall clock. Repeats, reservations, rooms and the page a visitor goes to next are set on the event\'s own screen. Publishing needs the publishing right where the owner is not a manager: 403 forbidden otherwise.',
			'params'  => array(
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true),
				array('name' => 'start',             'in' => 'body', 'type' => 'datetime', 'required' => true),
				array('name' => 'end',               'in' => 'body', 'type' => 'datetime', 'required' => true, 'description' => 'For an all-day event, the last day it runs on.'),
				array('name' => 'all_day',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'calendar_ids',      'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'required' => true, 'description' => 'The calendars it goes on: at least one, each one the owner may open.'),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'description',       'in' => 'body', 'type' => 'string', 'max_length' => 20000, 'description' => 'The long description, as HTML. Scripts, event handlers and the like are removed.'),
				array('name' => 'location',          'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'Where it happens, as words. The rooms it books are set on its screen.'),
				array('name' => 'show_start_time',   'in' => 'body', 'type' => 'bool', 'description' => 'Show the clock time with the start date. On when not sent.'),
				array('name' => 'show_end_time',     'in' => 'body', 'type' => 'bool', 'description' => 'Show the clock time with the end date. On when not sent.'),
				array('name' => 'published',         'in' => 'body', 'type' => 'bool', 'description' => 'Off when not sent.'),
				array('name' => 'unpublish_days',    'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 65535, 'description' => 'Days after it ends that it leaves the published calendar. 45 when not sent.')
			)
		),

		array(
			'id'      => 'calendars.event_update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/calendars/events/{id}',
			'scope'   => 'calendars:write',
			'handler' => 'api_calendar_events_update',
			'dry_run' => true,
			'returns' => 'CalendarEvent',
			'summary' => 'Change an event',
			'description' => 'Only what is sent is changed. The times are worked out again from what is sent and what is stored whenever start, end or all_day is sent. A one-off event that books a room is checked against the room\'s other bookings (409 room_taken); a repeating one that books a room is moved on its own screen (409 repeating_event_books_room). calendar_ids replaces the calendars the owner may open; the others keep the event. A published event is closed to an owner without the publishing right (403). PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',                'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',              'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'start',             'in' => 'body', 'type' => 'datetime'),
				array('name' => 'end',               'in' => 'body', 'type' => 'datetime'),
				array('name' => 'all_day',           'in' => 'body', 'type' => 'bool'),
				array('name' => 'calendar_ids',      'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50),
				array('name' => 'short_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'description',       'in' => 'body', 'type' => 'string', 'max_length' => 20000),
				array('name' => 'location',          'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'show_start_time',   'in' => 'body', 'type' => 'bool'),
				array('name' => 'show_end_time',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'published',         'in' => 'body', 'type' => 'bool'),
				array('name' => 'unpublish_days',    'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 65535)
			)
		),

		array(
			'id'      => 'calendars.event_delete',
			'method'  => 'DELETE',
			'path'    => '/calendars/events/{id}',
			'scope'   => 'calendars:write',
			'handler' => 'api_calendar_events_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Remove an event',
			'description' => 'With every repeat, its place on every calendar, the rooms it booked and the reservation spots left, as on the event screen. Reservations already sold stay with their orders.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'calendars.event_delete_post',
			'method'  => 'POST',
			'path'    => '/calendars/events/{id}/delete',
			'scope'   => 'calendars:write',
			'handler' => 'api_calendar_events_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Remove an event, addressed as a POST',
			'description' => 'The same removal as DELETE /calendars/events/{id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
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
			'status'  => 201,
			'summary' => 'Subscribe to events',
			'description' => 'The signing secret is returned once, here. Each delivery carries X-Pinegrap-Signature: t=<unix>,v1=HMAC-SHA256(t + "." + body) - check it, and refuse a timestamp older than a few minutes. With channel_group_id the subscription hears only the workspace events of the channels in that group of channels and in the groups inside it, with the same body, signature and retries as any other.',
			'params'  => array(
				array('name' => 'url',    'in' => 'body', 'type' => 'string', 'max_length' => 500, 'required' => true, 'description' => 'https address to call. It is checked now, not at delivery time.'),
				array('name' => 'events', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'required' => true, 'description' => 'Event names. GET this endpoint to see the list.'),
				array('name' => 'channel_group_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'Workspace: narrow the subscription to one group of channels (GET /workspace/channel-groups, a group the owner of the application sees). Every event must then be a workspace event; the channel\'s group is read when the event happens. 0, null or left out: the whole site.')
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
			'status'  => 204,
			'summary' => 'Remove a subscription',
			'description' => 'POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'webhooks.get',
			'method'  => 'GET',
			'path'    => '/webhooks/{id}',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_get',
			'returns' => 'Webhook',
			'summary' => 'One of your event subscriptions',
			'description' => 'With what is still queued for it and the last delivery error on file. Another application\'s subscription is answered 404, the same as one that does not exist.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'webhooks.update',
			'method'  => 'PATCH',
			'path'    => '/webhooks/{id}',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_update',
			'dry_run' => true,
			'returns' => 'Webhook',
			'summary' => 'Change a subscription',
			'description' => 'Move the address, change the events, or switch deliveries off (disabled) and on again (active) without losing the signing secret. A subscription the site marked failing is switched back on with status=active; what is still waiting is sent again by POST /webhooks/{id}/retry, not by this call. A disabled subscription receives nothing, and the events queued for it are dropped. A plain POST to /webhooks/{id} is not this call - it has removed the subscription since the first release - so a client that cannot send PATCH, or talks to an IIS server, uses POST /webhooks/{id}/update.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'url',    'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'https address to call. It is checked now, not at delivery time.'),
				array('name' => 'events', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'description' => 'Event names; replaces the whole list. GET /webhooks lists them.'),
				array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'disabled')),
				array('name' => 'channel_group_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'Workspace: narrow the subscription to one group of channels, or take the narrowing off with 0 or null; left out, it stays as it is. A narrowed subscription listens to workspace events only. Removing the group switches its subscriptions off (disabled); switching one back on asks for a group that is there.')
			)
		),

		array(
			'id'      => 'webhooks.update_post',
			'method'  => 'POST',
			'path'    => '/webhooks/{id}/update',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_update',
			'dry_run' => true,
			'returns' => 'Webhook',
			'summary' => 'Change a subscription, addressed as a POST',
			'description' => 'The same change as PATCH /webhooks/{id}, for clients and servers that cannot send PATCH. It has an address of its own because POST /webhooks/{id} removes the subscription.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'url',    'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'https address to call. It is checked now, not at delivery time.'),
				array('name' => 'events', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'description' => 'Event names; replaces the whole list. GET /webhooks lists them.'),
				array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'disabled')),
				array('name' => 'channel_group_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'Workspace: narrow the subscription to one group of channels, or take the narrowing off with 0 or null; left out, it stays as it is. A narrowed subscription listens to workspace events only. Removing the group switches its subscriptions off (disabled); switching one back on asks for a group that is there.')
			)
		),

		array(
			'id'      => 'webhooks.retry',
			'method'  => 'POST',
			'path'    => '/webhooks/{id}/retry',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_retry',
			'dry_run' => true,
			'returns' => 'Webhook',
			'summary' => 'Send again what is waiting',
			'description' => 'For a receiver that is answering again: the subscription goes back to active and every queued event, including the ones that ran out of attempts, gets its full set of attempts back. The scheduled task sends them on its next run. requeued says how many.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'webhooks.test',
			'method'  => 'POST',
			'path'    => '/webhooks/{id}/test',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_test',
			'dry_run' => true,
			'returns' => array('webhook_id' => 'integer', 'delivery_id' => 'integer', 'event' => 'string', 'queued' => 'boolean'),
			'summary' => 'Send a test event',
			'description' => 'Queues one webhook.test event for this subscription only, signed and sent by the scheduled task exactly like a real one, so the receiver can be checked without waiting for an order. delivery_id is the X-Pinegrap-Delivery header it will carry. Refused with 409 webhook_disabled while the subscription is switched off.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'webhooks.rotate_secret',
			'method'  => 'POST',
			'path'    => '/webhooks/{id}/rotate-secret',
			'scope'   => 'webhooks:manage',
			'handler' => 'api_webhooks_rotate_secret',
			'dry_run' => true,
			'returns' => 'Webhook',
			'summary' => 'Renew the signing secret',
			'description' => 'The new secret is returned once, here, and the old one stops verifying at once: deliveries are signed when they are sent, so anything still queued goes out under the new secret as well.',
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
			'status'  => 201,
			'summary' => 'Add a customer',
			'description' => 'The plain fields of the address book record. Needs at least a name, a company or an e-mail address - a row with none of those is not a customer. Nothing is de-duplicated: two people at one address is a real thing and neither the table nor the panel refuses it, so an integration that wants an upsert looks first with GET /customers?email=. Membership, the affiliate record and the ERP columns are not written here, and contact groups only as contact_group_id says.',
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
				array('name' => 'opt_in',      'in' => 'body', 'type' => 'bool', 'description' => 'Consent to be mailed. Send true only where you actually hold it; it is also the choice recorded for the e-mail of the group in contact_group_id, which is otherwise recorded as not opted in.'),
				array('name' => 'contact_group_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The contact group the customer is filed in (GET /contact-groups); left out, none. An application whose owner has the user role reaches only customers in the groups given to that account, as on the contact screens, so its customer is always filed in one of them: the one sent, or the only one it has; with several, this is required.')
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
			'description' => 'Only the fields you send are changed. The same set the create takes, without contact_group_id: groups are changed through POST /contact-groups/{id}/members.',
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
			'description' => 'Orders and money added up by period, so a chart does not have to be drawn by paging through forty thousand orders. Completed and exported orders count as sales; an incomplete one is an abandoned basket and a cancelled one is money that went back - ask for either with status, which is a real question for a shop measuring abandonment. Amounts are minor units. Defaults to the last thirty days by day. Only periods that hold orders come back, at most 400: a window whose orders fall on more days (or months) is refused with 422 rather than answered short, so totals always cover the whole window.',
			'params'  => array(
				array('name' => 'from',     'in' => 'query', 'type' => 'datetime', 'description' => 'Start of the window. Defaults to thirty days before to.'),
				array('name' => 'to',       'in' => 'query', 'type' => 'datetime', 'description' => 'End of the window. Defaults to now.'),
				array('name' => 'interval', 'in' => 'query', 'type' => 'enum', 'values' => array('day', 'month'), 'default' => 'day'),
				array('name' => 'status',   'in' => 'query', 'type' => 'enum', 'values' => array('incomplete', 'complete', 'exported', 'cancelled'), 'description' => 'One status instead of the two that count as sales.')
			)
		),

		array(
			'id'      => 'reports.traffic',
			'method'  => 'GET',
			'path'    => '/reports/traffic',
			'scope'   => 'visitors:read',
			'handler' => 'api_reports_traffic',
			'returns' => 'TrafficReport',
			'summary' => 'Visits and page views by day or hour',
			'description' => 'From the hourly counters the Welcome screen draws its traffic chart with, so any window costs the same. A visit is a visitor session started in that period; page views are the pages it opened. Every day (or hour) of the window is in periods, empty ones as zeros, by the site\'s own clock. Defaults to the last thirty days by day. A window is at most 400 days by day and 31 by hour. visitor_tracking false means the site is not counting visits, so the figures stay at zero.',
			'params'  => array(
				array('name' => 'from',     'in' => 'query', 'type' => 'datetime', 'description' => 'First day of the window. Defaults to twenty-nine days before to.'),
				array('name' => 'to',       'in' => 'query', 'type' => 'datetime', 'description' => 'Last day of the window, counted whole. Defaults to today.'),
				array('name' => 'interval', 'in' => 'query', 'type' => 'enum', 'values' => array('day', 'hour'), 'default' => 'day')
			)
		),

		array(
			'id'      => 'reports.top_content',
			'method'  => 'GET',
			'path'    => '/reports/top-content',
			'scope'   => 'visitors:read',
			'handler' => 'api_reports_top_content',
			'returns' => array('list' => 'TopContent'),
			'summary' => 'Most viewed pages and items',
			'description' => 'The pages, products, product groups and form records opened most in the window, most views first, with the title and the address they are opened at. One page. Defaults to the last thirty days; a window is at most 400 days.',
			'params'  => array(
				array('name' => 'from',  'in' => 'query', 'type' => 'datetime', 'description' => 'First day of the window. Defaults to twenty-nine days before to.'),
				array('name' => 'to',    'in' => 'query', 'type' => 'datetime', 'description' => 'Last day of the window, counted whole. Defaults to today.'),
				array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 10)
			)
		),

		array(
			'id'      => 'reports.visitors',
			'method'  => 'GET',
			'path'    => '/reports/visitors',
			'scope'   => 'visitors:read',
			'handler' => 'api_reports_visitors',
			'returns' => 'VisitorBreakdown',
			'summary' => 'Visits by source, campaign, landing page or place',
			'description' => 'Visits started on the days of the window counted by one thing about them, largest first, with what they led to: page views, forms submitted, orders created, checked out and completed, and the completed order total in minor units - the figures the visitor report screen sums. by: referrer_host, search_engine, search_terms (what a search engine sent), channel (pay_per_click when the tracking code carries the pay-per-click flag of the SEO settings, organic when a search engine referred it, otherwise neither), landing_page, tracking_code, utm_source, utm_medium, utm_campaign, utm_term, utm_content, country, state, city, form (the custom form submitted), site_search_terms or device (desktop, mobile or tablet, from the browser). A null value is a visit without one: no referrer is a direct visit. totals covers every visit in the window, not only the rows returned. Only totals are given; a single visit is not. Defaults to the last thirty days; a window is at most 400 days. Days older than the visitor retention period are answered from the daily summary: summary_to is the last such day of the window (null when none), the totals of those days stay exact, and a value outside the top 100 of its day is counted in the totals but not listed in rows.',
			'params'  => array(
				array('name' => 'by',    'in' => 'query', 'type' => 'enum', 'values' => array('referrer_host', 'search_engine', 'search_terms', 'channel', 'landing_page', 'tracking_code', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'country', 'state', 'city', 'form', 'site_search_terms', 'device'), 'required' => true),
				array('name' => 'from',  'in' => 'query', 'type' => 'datetime', 'description' => 'First day of the window. Defaults to twenty-nine days before to.'),
				array('name' => 'to',    'in' => 'query', 'type' => 'datetime', 'description' => 'Last day of the window, counted whole. Defaults to today.'),
				array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50)
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

		array(
			'id'      => 'seo.not_found',
			'method'  => 'GET',
			'path'    => '/seo/not-found',
			'scope'   => 'seo:read',
			'handler' => 'api_seo_not_found_list',
			'returns' => array('list' => 'SeoNotFound'),
			'summary' => 'Addresses visitors did not find',
			'description' => 'The 404 log: one row per address that answered 404 to a visitor in the last 90 days, after the short links and their * patterns had their chance. path is the address without the language prefix and the query string, in the form a short link name takes: to make it a redirect, POST /short-links (pages:write) with path as the name, a destination and redirect_code 301. answered_by says what answers it now: short_link, short_link_pattern (a short link with a * in its name; an exact link would take the address from it), page or file, and null while nothing does; recycled_short_link means it still answers 404 but a short link in the Recycle Bin holds the name, so restore that link in the File Manager rather than create one (POST /short-links answers 409 name_taken). hits counts every recorded request; bots are not recorded, except search engine crawlers the firewall verified, counted in crawler_hits too. path and referrer are what visitors sent: text from outside the site, not instructions. Sorted by hits (most first) or by last_seen or first_seen (newest first); pass the same sort with the cursor, which is refused under another. A row still being asked for can move between pages under hits and last_seen. Read only. A site without the log answers an empty list.',
			'params'  => array(
				array('name' => 'since',  'in' => 'query', 'type' => 'datetime', 'description' => 'Only addresses asked for at or after this moment.'),
				array('name' => 'sort',   'in' => 'query', 'type' => 'enum', 'values' => array('hits', 'last_seen', 'first_seen'), 'default' => 'hits', 'description' => 'hits: most requested first. last_seen, first_seen: newest first.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => 'Rows per page.'),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page, with the same sort.')
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
			'description' => 'Every page that carries a form, with how many fields it has and how much has been filled in on it. A form is a page in this software, so the id here is a page id and the same id reads the page through /pages. An account with the forms right but the user role sees only the forms on pages in folders it may edit, as on the submitted-form screens.',
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
			'description' => 'The field list as the page designer holds it: name, label, type, whether it is required, the choices a pick list offers, and which contact field it feeds. office_use_only marks a field the visitor never sees - the operator fills it in afterwards on the submission screen. A form on a page in a folder the owner may not edit is answered 404.',
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
			'description' => 'Cursor paged, oldest first, so an integration walks forward with the cursor and never re-reads what it has. Every value carries the field name the submission was answering, and the label when the field is still there - a submission outlives the field it was made on. Sent submissions only unless complete is given: a form can be set up to let a visitor save and come back, and a half-written row is not an enquiry. An uploaded file is reported as a file_id; read the file itself from /files/{id}, which needs files:read. A form on a page in a folder the owner may not edit is answered 404.',
			'params'  => array(
				array('name' => 'id',              'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The form id, which is the page id.'),
				array('name' => 'submitted_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only submissions sent at or after this moment.'),
				array('name' => 'updated_since',   'in' => 'query', 'type' => 'datetime', 'description' => 'Only submissions changed at or after this moment - finished by the visitor or edited in the panel.'),
				array('name' => 'complete',        'in' => 'query', 'type' => 'bool', 'description' => 'false returns the saved-but-not-sent ones instead.'),
				array('name' => 'reference_code',  'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'The code the visitor was shown after sending.'),
				array('name' => 'limit',           'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50, 'description' => '1-250. Rows per page.'),
				array('name' => 'cursor',          'in' => 'query', 'type' => 'string', 'max_length' => 255, 'description' => 'next_cursor from the previous page.')
			)
		),

		array(
			'id'      => 'forms.submission_get',
			'method'  => 'GET',
			'path'    => '/forms/{id}/submissions/{submission_id}',
			'scope'   => 'forms:read',
			'handler' => 'api_form_submission_get',
			'returns' => 'FormSubmission',
			'summary' => 'One submission',
			'description' => 'With its values, whether it was sent or only saved. The form is part of the address: a submission made on another form is answered 404, and so is one made on a form in a folder the owner may not edit.',
			'params'  => array(
				array('name' => 'id',            'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The form id, which is the page id.'),
				array('name' => 'submission_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'forms.submission_update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/forms/{id}/submissions/{submission_id}',
			'scope'   => 'forms:write',
			'handler' => 'api_form_submission_update',
			'dry_run' => true,
			'returns' => 'FormSubmission',
			'summary' => 'Mark a submission complete, or fill in its text answers',
			'description' => 'What the submitted-form screen lets staff change, for the system that handles enquiries outside the panel: a help desk closing one, a CRM writing the outcome into the office-use fields the visitor never sees. Answers are addressed by the field name, as they are read, and only text box, text area and e-mail address fields can be written; a choice or an upload is refused. A field the visitor left empty gets its answer now. A form on a page in a folder the owner may not edit is answered 404.',
			'params'  => array(
				array('name' => 'id',            'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The form id, which is the page id.'),
				array('name' => 'submission_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'complete',      'in' => 'body', 'type' => 'bool'),
				array('name' => 'values',        'in' => 'body', 'type' => 'list', 'of' => array('name' => 'string', 'value' => 'string'), 'max_items' => 50, 'description' => 'Objects of {name, value}: the field name and its new answer. Up to 10000 characters each; an empty value clears it.')
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
			'description' => 'The title, the description, the keywords, the site search settings, the indexing switches, the comment switches and the folder. Only what is sent is changed. A page closed to search engines cannot be in the site map at the same time and the request is refused rather than quietly corrected; nofollow only applies while noindex is on. A move to another folder may keep the page as open as it is or close it further, never open it up: a move that would serve the page to more people answers 409 access_widening_refused. The home page, the pages the storefront and the account area run on, drafts and pages in the recycle bin are not moved from here. The meta half of the score is recalculated here, the structure half stays with the nightly job. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached. Use POST.',
			'params'  => array(
				array('name' => 'id',               'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'title',            'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'search',           'in' => 'body', 'type' => 'bool', 'description' => 'Whether the page is included in the site search.'),
				array('name' => 'search_keywords',  'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 50, 'description' => 'Words that promote the page in the site search. A list of words; send an empty list to clear it.'),
				array('name' => 'sitemap',          'in' => 'body', 'type' => 'bool', 'description' => 'Whether the page is listed in the site map.'),
				array('name' => 'noindex',          'in' => 'body', 'type' => 'bool', 'description' => 'Closes the page to search engines: a noindex robots tag, blocked in robots.txt and left out of the site map.'),
				array('name' => 'nofollow',         'in' => 'body', 'type' => 'bool', 'description' => 'Qualifies noindex. Only accepted while noindex is on.'),
				array('name' => 'meta_keywords',    'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'The keywords meta tag, as one comma separated string.'),
				array('name' => 'comments',         'in' => 'body', 'type' => 'bool', 'description' => 'Whether the page shows comments at all.'),
				array('name' => 'comments_open',    'in' => 'body', 'type' => 'bool', 'description' => 'Whether visitors may add new comments.'),
				array('name' => 'comments_publish', 'in' => 'body', 'type' => 'bool', 'description' => 'Publish a new comment at once instead of holding it for moderation.'),
				array('name' => 'comments_login',   'in' => 'body', 'type' => 'bool', 'description' => 'Only signed-in visitors may comment.'),
				array('name' => 'folder_id',        'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Move the page into this folder (GET /folders). Refused when the folder would serve it to more people than its current one.')
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

		/* ----- Folders ------------------------------------------------------ */

		array(
			'id'      => 'folders.list',
			'method'  => 'GET',
			'path'    => '/folders',
			'scope'   => 'pages:read',
			'handler' => 'api_folders_list',
			'returns' => array('list' => 'Folder'),
			'summary' => 'List folders',
			'description' => 'The tree pages and files are filed in. access is the rule the folder is served under - public, guest, registration, membership or private - whether written on it or inherited from above (access_inherited). system names the drafts folder and the recycle bin, which belong to their own screens; whatever is inside the bin is left out. folders, pages and files count what sits directly in each folder. Cursor paged by id.',
			'params'  => array(
				array('name' => 'parent_id',     'in' => 'query', 'type' => 'int', 'min' => 0, 'description' => 'Only the folders directly inside this one; 0 for the top folder.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the folder name.'),
				array('name' => 'archived',      'in' => 'query', 'type' => 'bool', 'description' => 'Limit to archived folders, or to the rest.'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200),
				array('name' => 'include_count', 'in' => 'query', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'folders.get',
			'method'  => 'GET',
			'path'    => '/folders/{id}',
			'scope'   => 'pages:read',
			'handler' => 'api_folders_get',
			'returns' => 'Folder',
			'summary' => 'One folder',
			'description' => 'A folder inside the recycle bin is answered too, with recycled true.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'folders.create',
			'method'  => 'POST',
			'path'    => '/folders',
			'scope'   => 'pages:write',
			'handler' => 'api_folders_create',
			'dry_run' => true,
			'returns' => 'Folder',
			'status'  => 201,
			'summary' => 'Create a folder',
			'description' => 'A new, empty folder. Left without access it inherits the rule of its parent. A private folder admits the people given access to it on the folder screen; until then only staff open what is in it. Nothing is created inside the drafts folder or the recycle bin.',
			'params'  => array(
				array('name' => 'name',      'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true),
				array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The folder it goes in. The top folder when left out.'),
				array('name' => 'access',    'in' => 'body', 'type' => 'enum', 'values' => array('inherit', 'public', 'guest', 'registration', 'membership', 'private'), 'default' => 'inherit'),
				array('name' => 'order',     'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 99999, 'description' => 'Where it sorts among its siblings.'),
				array('name' => 'archived',  'in' => 'body', 'type' => 'bool', 'description' => 'Archived folders are struck out of the everyday views.')
			)
		),

		array(
			'id'      => 'folders.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/folders/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_folders_update',
			'dry_run' => true,
			'returns' => 'Folder',
			'summary' => 'Change a folder',
			'description' => 'Rename it, sort it, archive it, move it under another parent, or give it a stricter access rule. Only what is sent is changed. A change that would serve the folder to more people than today - a looser access type, or a move out from under a stricter parent - answers 409 access_widening_refused: opening part of the site up is done on the folder screen. The top folder is not moved, and the drafts folder and the recycle bin are not changed here. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',      'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Move it into this folder. Not into itself or a folder inside it.'),
				array('name' => 'access',    'in' => 'body', 'type' => 'enum', 'values' => array('inherit', 'public', 'guest', 'registration', 'membership', 'private')),
				array('name' => 'order',     'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 99999),
				array('name' => 'archived',  'in' => 'body', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'folders.delete',
			'method'  => 'DELETE',
			'path'    => '/folders/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_folders_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete an empty folder',
			'description' => 'Only a folder with nothing in it - no folder, page or file - is deleted, as on the folder screen; otherwise 409 folder_not_empty. Move or delete what is inside first through its own endpoint. The top folder, the drafts folder and the recycle bin are refused. A default IIS install answers DELETE itself before PHP is reached: POST /folders/{id}/delete does the same thing and always arrives.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'folders.delete_post',
			'method'  => 'POST',
			'path'    => '/folders/{id}/delete',
			'scope'   => 'pages:write',
			'handler' => 'api_folders_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete an empty folder, addressed as a POST',
			'description' => 'The same deletion as DELETE /folders/{id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Menus -------------------------------------------------------- */

		array(
			'id'      => 'menus.list',
			'method'  => 'GET',
			'path'    => '/menus',
			'scope'   => 'pages:read',
			'handler' => 'api_menus_list',
			'returns' => array('list' => 'Menu'),
			'summary' => 'Menus',
			'description' => 'The site\'s menus without their items (GET /menus/{id} has them). A design places a menu by its name. Cursor paged.',
			'params'  => array(
				array('name' => 'q',      'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the menu name.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'menus.get',
			'method'  => 'GET',
			'path'    => '/menus/{id}',
			'scope'   => 'pages:read',
			'handler' => 'api_menus_get',
			'returns' => 'Menu',
			'summary' => 'A menu and its items',
			'description' => 'items in the order the site draws them: each item followed by the items under it (parent_id, level), siblings by order. url is where the item leads: the linked page\'s address, or link_url. security hides the item from visitors who may not open its page.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'menus.create',
			'method'  => 'POST',
			'path'    => '/menus',
			'scope'   => 'pages:write',
			'handler' => 'api_menus_create',
			'dry_run' => true,
			'returns' => 'Menu',
			'status'  => 201,
			'summary' => 'Add a menu',
			'description' => 'An empty menu, shown nowhere until a designer places it in a design. Only for an application whose owner is an administrator or a designer (403 otherwise), as on the menus screen. The name is how designs place the menu: letters, numbers and dashes, unique (409 name_taken), and it is not changed through the API afterwards. Menus are not removed through the API either: the menus screen shows where a menu is used.',
			'params'  => array(
				array('name' => 'name',                        'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true),
				array('name' => 'effect',                      'in' => 'body', 'type' => 'enum', 'values' => array('none', 'popup', 'accordion'), 'description' => 'How the items under an item open.'),
				array('name' => 'first_level_popup_position',  'in' => 'body', 'type' => 'enum', 'values' => array('top', 'bottom', 'left', 'right')),
				array('name' => 'second_level_popup_position', 'in' => 'body', 'type' => 'enum', 'values' => array('top', 'bottom', 'left', 'right')),
				array('name' => 'class',                       'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'CSS class of the menu.'),
				array('name' => 'active_item_class',           'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'CSS class of the item of the page being shown.')
			)
		),

		array(
			'id'      => 'menus.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/menus/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_menus_update',
			'dry_run' => true,
			'returns' => 'Menu',
			'summary' => 'Change how a menu is drawn',
			'description' => 'The effect, the pop-up positions and the classes. Only for an application whose owner is an administrator or a designer (403 otherwise). The name stays: designs place the menu by it. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',                          'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'effect',                      'in' => 'body', 'type' => 'enum', 'values' => array('none', 'popup', 'accordion')),
				array('name' => 'first_level_popup_position',  'in' => 'body', 'type' => 'enum', 'values' => array('top', 'bottom', 'left', 'right')),
				array('name' => 'second_level_popup_position', 'in' => 'body', 'type' => 'enum', 'values' => array('top', 'bottom', 'left', 'right')),
				array('name' => 'class',                       'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'active_item_class',           'in' => 'body', 'type' => 'string', 'max_length' => 255)
			)
		),

		array(
			'id'      => 'menus.items.create',
			'method'  => 'POST',
			'path'    => '/menus/{id}/items',
			'scope'   => 'pages:write',
			'handler' => 'api_menu_items_create',
			'dry_run' => true,
			'returns' => 'MenuItem',
			'status'  => 201,
			'summary' => 'Add a menu item',
			'description' => 'It links to a page (page_id) or to an address (url), or to nothing (a heading for the items under it). A page in the Recycle Bin or kept off the site as a draft is refused; an address must be http, https, mailto or tel, or a path on this site. It goes last among its siblings, or after the one named in after (0: first).',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',       'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true, 'description' => 'The text of the item.'),
				array('name' => 'parent_id',  'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The item it goes under; 0 or left out for the top level.'),
				array('name' => 'page_id',    'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The page it leads to.'),
				array('name' => 'url',        'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The address it leads to, when it does not lead to a page.'),
				array('name' => 'new_window', 'in' => 'body', 'type' => 'bool', 'description' => 'Open the link in a new window.'),
				array('name' => 'security',   'in' => 'body', 'type' => 'bool', 'description' => 'Show the item only to visitors who may open its page.'),
				array('name' => 'class',      'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'after',      'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The sibling it goes after; 0 puts it first.')
			)
		),

		array(
			'id'      => 'menus.items.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/menus/{id}/items/{item_id}',
			'scope'   => 'pages:write',
			'handler' => 'api_menu_items_update',
			'dry_run' => true,
			'returns' => 'MenuItem',
			'summary' => 'Change a menu item',
			'description' => 'Only what is sent is changed. A page_id clears the address and a url clears the page; page_id 0 removes the page link and url "" the address. A new parent_id moves the item with everything under it, last among its new siblings unless after says where; it cannot go under itself or an item under it. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'item_id',    'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',       'in' => 'body', 'type' => 'string', 'max_length' => 100),
				array('name' => 'parent_id',  'in' => 'body', 'type' => 'int', 'min' => 0),
				array('name' => 'page_id',    'in' => 'body', 'type' => 'int', 'min' => 0),
				array('name' => 'url',        'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'new_window', 'in' => 'body', 'type' => 'bool'),
				array('name' => 'security',   'in' => 'body', 'type' => 'bool'),
				array('name' => 'class',      'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'after',      'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The sibling it goes after; 0 puts it first.')
			)
		),

		array(
			'id'      => 'menus.items.delete',
			'method'  => 'DELETE',
			'path'    => '/menus/{id}/items/{item_id}',
			'scope'   => 'pages:write',
			'handler' => 'api_menu_items_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'removed' => 'integer'),
			'summary' => 'Remove a menu item',
			'description' => 'The item and every item under it, as on the menu item screen; removed counts them. Try it with X-Dry-Run first to see the count.',
			'params'  => array(
				array('name' => 'id',      'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'item_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'menus.items.delete_post',
			'method'  => 'POST',
			'path'    => '/menus/{id}/items/{item_id}/delete',
			'scope'   => 'pages:write',
			'handler' => 'api_menu_items_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'removed' => 'integer'),
			'summary' => 'Remove a menu item, addressed as a POST',
			'description' => 'The same removal as DELETE /menus/{id}/items/{item_id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id',      'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'item_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Comments ----------------------------------------------------- */

		array(
			'id'      => 'comments.list',
			'method'  => 'GET',
			'path'    => '/comments',
			'scope'   => 'comments:read',
			'handler' => 'api_comments_list',
			'returns' => array('list' => 'Comment'),
			'summary' => 'Comments',
			'description' => 'What visitors wrote, newest first. status: published, pending (held for a person to publish) or scheduled (published at publish_at; cancel_if_newer drops it when a newer comment comes first). item_type and item_id say what a comment is about when it is not the page itself: a form record (submitted_form), a calendar event, a product group or a product. parent_id is the comment a reply answers: null for a comment that answers none, and for a reply whose comment was removed. Cursor paged.',
			'params'  => array(
				array('name' => 'page_id',       'in' => 'query', 'type' => 'int', 'min' => 1),
				array('name' => 'item_type',     'in' => 'query', 'type' => 'enum', 'values' => array('page', 'submitted_form', 'calendar_event', 'product_group', 'product'), 'description' => 'page: comments about the page itself.'),
				array('name' => 'item_id',       'in' => 'query', 'type' => 'int', 'min' => 1),
				array('name' => 'parent_id',     'in' => 'query', 'type' => 'int', 'min' => 0, 'description' => '0: comments that answer no other comment. An id: the replies to that comment.'),
				array('name' => 'status',        'in' => 'query', 'type' => 'enum', 'values' => array('all', 'published', 'pending', 'scheduled'), 'default' => 'all'),
				array('name' => 'featured',      'in' => 'query', 'type' => 'bool'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the name or the message.'),
				array('name' => 'created_since', 'in' => 'query', 'type' => 'datetime'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'comments.get',
			'method'  => 'GET',
			'path'    => '/comments/{id}',
			'scope'   => 'comments:read',
			'handler' => 'api_comments_get',
			'returns' => 'Comment',
			'summary' => 'A comment',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'comments.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/comments/{id}',
			'scope'   => 'comments:write',
			'handler' => 'api_comments_update',
			'dry_run' => true,
			'returns' => 'Comment',
			'summary' => 'Moderate a comment',
			'description' => 'Publish it or hold it (published), feature it or not (featured). Either value of published ends a schedule. Publishing a comment that was not published sends what the comments screen sends: the e-mail to the people watching the page and, on a form record, to the person who submitted it; the dry run says whether it would (emails). The visitor\'s words are not changed here. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'published', 'in' => 'body', 'type' => 'bool'),
				array('name' => 'featured',  'in' => 'body', 'type' => 'bool')
			)
		),

		array(
			'id'      => 'comments.delete',
			'method'  => 'DELETE',
			'path'    => '/comments/{id}',
			'scope'   => 'comments:write',
			'handler' => 'api_comments_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Remove a comment',
			'description' => 'For good, with its attached file unless another comment shares it (file_deleted in the dry run), as on the comments screen.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'comments.delete_post',
			'method'  => 'POST',
			'path'    => '/comments/{id}/delete',
			'scope'   => 'comments:write',
			'handler' => 'api_comments_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Remove a comment, addressed as a POST',
			'description' => 'The same removal as DELETE /comments/{id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Short links -------------------------------------------------- */

		array(
			'id'      => 'short_links.list',
			'method'  => 'GET',
			'path'    => '/short-links',
			'scope'   => 'pages:read',
			'handler' => 'api_short_links_list',
			'returns' => array('list' => 'ShortLink'),
			'summary' => 'Short links',
			'description' => 'Newest first, cursor paged. mode: permanent (opens for good under its name), timed (under its name until expires_at) or once (a generated address, spent by the first visit). status: open, used or expired. address is the link a visitor opens; a one-time link\'s address is not kept and only token_hint shows how it starts, and a name with a * (is_pattern) stands for many addresses and has none. target is where it leads. redirect_code is how it answers: 0 shows the destination at the link\'s address, 301 or 302 sends the visitor to the destination\'s own address. recycled=true lists the ones in the recycle bin instead.',
			'params'  => array(
				array('name' => 'destination_type', 'in' => 'query', 'type' => 'enum', 'values' => array('page', 'product_group', 'product', 'url', 'file')),
				array('name' => 'page_id',          'in' => 'query', 'type' => 'int', 'min' => 1),
				array('name' => 'mode',             'in' => 'query', 'type' => 'enum', 'values' => array('permanent', 'timed', 'once')),
				array('name' => 'q',                'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the name, the address it leads to or the tracking code.'),
				array('name' => 'recycled',         'in' => 'query', 'type' => 'bool'),
				array('name' => 'limit',            'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',           'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'short_links.get',
			'method'  => 'GET',
			'path'    => '/short-links/{id}',
			'scope'   => 'pages:read',
			'handler' => 'api_short_links_get',
			'returns' => 'ShortLink',
			'summary' => 'One short link',
			'description' => 'With how often it was opened (use_count and last_used_at count the visits of timed and one-time links).',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'short_links.create',
			'method'  => 'POST',
			'path'    => '/short-links',
			'scope'   => 'pages:write',
			'handler' => 'api_short_links_create',
			'dry_run' => true,
			'returns' => 'ShortLink',
			'status'  => 201,
			'summary' => 'Add a short link',
			'description' => 'A name a visitor types (letters of any language, numbers and . _ - / [ ] ~ ( ) + , ! = @; spaces become _) that leads to a page, a product group or product on a catalog page, a file or another address. An old address can be sent as the name: only its path is kept, so a page that moved can keep its old address with redirect_code 301. A * in the name stands for any part of an address and is tried only where no page answers; such a link always sends visitors on (301 unless 302 is sent), and a url destination can reuse what each * matched as $1 to $9. The name is refused with 409 name_taken when a page, file, folder or other short link already answers at it, and an address that leads back to the link, round a loop of links or down more than 10 of them is refused with 422. mode=timed needs duration, one of 3600, 86400, 259200, 604800, 2592000 or 7776000 seconds. mode=once takes no name: the answer carries token_url, the generated address, which is shown this once and kept nowhere. A timed or one-time link answers 302 even with redirect_code 301.',
			'params'  => array(
				array('name' => 'name',             'in' => 'body', 'type' => 'string', 'max_length' => 2048, 'description' => 'Required unless mode is once. At most 191 characters once only the path is kept (100 before the 2026.11.0 upgrade).'),
				array('name' => 'mode',             'in' => 'body', 'type' => 'enum', 'values' => array('permanent', 'timed', 'once'), 'default' => 'permanent'),
				array('name' => 'duration',         'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'For timed: how long it opens, in seconds, counted from now.'),
				array('name' => 'destination_type', 'in' => 'body', 'type' => 'enum', 'values' => array('page', 'product_group', 'product', 'url', 'file'), 'required' => true),
				array('name' => 'page_id',          'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The page it opens: the page itself, or the catalog (or catalog detail) page a product group or product is shown on.'),
				array('name' => 'product_group_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'product_id',       'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'url',              'in' => 'body', 'type' => 'string', 'max_length' => 2048, 'description' => 'For url: a web address (http or https) or a path on this site, at most 2048 characters (255 before the 2026.11.0 upgrade).'),
				array('name' => 'file_id',          'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'tracking_code',    'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'Added as ?t= when the link opens a page, unless the visitor already brings one.'),
				array('name' => 'redirect_code',    'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 302, 'description' => 'One of 0, 301 or 302. 0 (the default) shows the destination at the link\'s address; 301 or 302 sends the visitor to the destination\'s own address with that status, with the visitor\'s query string.'),
			)
		),

		array(
			'id'      => 'short_links.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/short-links/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_short_links_update',
			'dry_run' => true,
			'returns' => 'ShortLink',
			'summary' => 'Change a short link',
			'description' => 'Only what is sent is changed. A new name is the new address and the old one stops answering; a one-time link has no name to change. Any destination field sent re-reads the destination with the stored values for what is not sent, and empties the columns the type does not use. A new name, destination or redirect_code is checked as the link will stand, the way create checks it. renew gives a timed link a new time, counted from now. A link in the recycle bin is restored in the file manager first (409 already_recycled). PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',               'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'name',             'in' => 'body', 'type' => 'string', 'max_length' => 2048, 'description' => 'As on create: an old address keeps only its path, at most 191 characters.'),
				array('name' => 'renew',            'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'For a timed link: a new duration in seconds, as for duration on create.'),
				array('name' => 'destination_type', 'in' => 'body', 'type' => 'enum', 'values' => array('page', 'product_group', 'product', 'url', 'file')),
				array('name' => 'page_id',          'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The page it opens: the page itself, or the catalog (or catalog detail) page a product group or product is shown on.'),
				array('name' => 'product_group_id', 'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'product_id',       'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'url',              'in' => 'body', 'type' => 'string', 'max_length' => 2048, 'description' => 'For url: a web address (http or https) or a path on this site, at most 2048 characters (255 before the 2026.11.0 upgrade).'),
				array('name' => 'file_id',          'in' => 'body', 'type' => 'int', 'min' => 1),
				array('name' => 'tracking_code',    'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'Added as ?t= when the link opens a page, unless the visitor already brings one.'),
				array('name' => 'redirect_code',    'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 302, 'description' => 'As on create: 0, 301 or 302. A link with a * in its name always sends visitors on, so 0 makes it 301.'),
			)
		),

		array(
			'id'      => 'short_links.delete',
			'method'  => 'DELETE',
			'path'    => '/short-links/{id}',
			'scope'   => 'pages:write',
			'handler' => 'api_short_links_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a short link',
			'description' => 'Moves it to the recycle bin, where an operator can put it back; its address stops answering at once. Send permanent=true to delete it outright, which cannot be undone.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'query', 'type' => 'bool', 'description' => 'Delete outright instead of moving to the recycle bin.')
			)
		),

		array(
			'id'      => 'short_links.delete_post',
			'method'  => 'POST',
			'path'    => '/short-links/{id}/delete',
			'scope'   => 'pages:write',
			'handler' => 'api_short_links_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean', 'recycled' => 'boolean'),
			'summary' => 'Delete a short link, addressed as a POST',
			'description' => 'The same delete as DELETE /short-links/{id}, for clients that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id',        'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'permanent', 'in' => 'body',  'type' => 'bool', 'description' => 'Delete outright instead of moving to the recycle bin.')
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
			'status'  => 201,
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
			'id'      => 'files.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/files/{id}',
			'scope'   => 'files:write',
			'handler' => 'api_files_update',
			'dry_run' => true,
			'returns' => 'File',
			'summary' => 'Change a file\'s description',
			'description' => 'The description is what the site uses as an image\'s alternative text and what the file manager shows beside the name. Only that is writable: the name is the file\'s address, which pages refer to, and the folder is the operator\'s filing. Only files the read endpoints show can be changed.',
			'params'  => array(
				array('name' => 'id',          'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 500)
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
			'description' => 'An offer is a campaign rule applied to the cart at checkout - a discount, a gift product, free shipping - and not a sales quote. offer_status is what the offer is doing today, derived from the enabled switch and the date range; incomplete marks a saved offer that cannot do anything at checkout yet. The conditions and the results are on the single-offer endpoint. Cursor paged.',
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
			'id'      => 'offers.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/offers/{id}',
			'scope'   => 'offers:write',
			'handler' => 'api_offers_update',
			'dry_run' => true,
			'returns' => 'Offer',
			'summary' => 'Switch an offer on or off, reword it or move its dates',
			'description' => 'The offer\'s own row, the way the offer editor writes it: dates are YYYY-MM-DD, an empty end_date is an offer that runs until it is switched off, and the end may not come before the start. The conditions and the results are not writable here; an offer the editor marks incomplete stays incomplete, and switching it on does not make it do anything at checkout.',
			'params'  => array(
				array('name' => 'id',          'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'enabled',     'in' => 'body', 'type' => 'bool'),
				array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The message the offer shows.'),
				array('name' => 'start_date',  'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD.'),
				array('name' => 'end_date',    'in' => 'body', 'type' => 'string', 'max_length' => 10, 'nullable' => true, 'description' => 'YYYY-MM-DD, or empty or null for an offer with no end.')
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
		),

		/* ----- Key codes ---------------------------------------------------- */

		array(
			'id'      => 'key_codes.list',
			'method'  => 'GET',
			'path'    => '/key-codes',
			'scope'   => 'key_codes:read',
			'handler' => 'api_key_codes_list',
			'returns' => array('list' => 'KeyCode'),
			'summary' => 'Key codes',
			'description' => 'Codes a shopper types instead of an offer\'s own code, one per customer, card or mailing, so orders an offer brought in can be told apart. Newest first, cursor paged. offer_code is the offer it stands for (GET /offers?code=). status is what it does at checkout today: active, disabled (switched off; a single-use code is switched off by the order that uses it), expired (past expires_on) or offer_inactive (no offer with its offer code runs today). report says what an order records: the key code itself, or the offer code. The code is redeemable: whoever holds it gets the offer.',
			'params'  => array(
				array('name' => 'offer_code', 'in' => 'query', 'type' => 'string', 'max_length' => 50, 'description' => 'Only the codes that stand for this offer code.'),
				array('name' => 'status',     'in' => 'query', 'type' => 'enum', 'values' => array('active', 'disabled', 'expired', 'offer_inactive')),
				array('name' => 'single_use', 'in' => 'query', 'type' => 'bool'),
				array('name' => 'q',          'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Matches the code or the notes.'),
				array('name' => 'limit',      'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',     'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'key_codes.get',
			'method'  => 'GET',
			'path'    => '/key-codes/{id}',
			'scope'   => 'key_codes:read',
			'handler' => 'api_key_codes_get',
			'returns' => 'KeyCode',
			'summary' => 'One key code',
			'description' => 'The same fields as the list, for one code.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'key_codes.create',
			'method'  => 'POST',
			'path'    => '/key-codes',
			'scope'   => 'key_codes:write',
			'handler' => 'api_key_codes_create',
			'dry_run' => true,
			'returns' => array('data' => 'KeyCode[]'),
			'status'  => 201,
			'summary' => 'Create key codes',
			'description' => 'One code of your own choosing, or quantity new ten-character codes, for an existing offer code, as the key code screen makes them. The answer lists what was made under data, even when that is one code; nothing is mailed to anyone. A code already in use, or one that is an offer\'s own code (checkout would apply that offer and never read the key code), is refused. Single-use codes are what an offer hands out one per customer.',
			'params'  => array(
				array('name' => 'offer_code', 'in' => 'body', 'type' => 'string', 'max_length' => 50, 'required' => true, 'description' => 'The code of the offer it stands for. The offer has to exist.'),
				array('name' => 'code',       'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'A code of your own, for one key code. Left out, codes are made.'),
				array('name' => 'quantity',   'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 1, 'description' => 'How many codes to make.'),
				array('name' => 'enabled',    'in' => 'body', 'type' => 'bool', 'description' => 'On unless false is sent.'),
				array('name' => 'single_use', 'in' => 'body', 'type' => 'bool', 'description' => 'Switched off by the first order that uses it. Off unless true is sent.'),
				array('name' => 'report',     'in' => 'body', 'type' => 'enum', 'values' => array('key_code', 'offer_code'), 'description' => 'What an order records: this code (the default) or the offer code.'),
				array('name' => 'expires_on', 'in' => 'body', 'type' => 'datetime', 'description' => 'The last day it can be used. Left out, it never expires.'),
				array('name' => 'notes',      'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'For the staff only, such as who it was given to.')
			)
		),

		array(
			'id'      => 'key_codes.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/key-codes/{id}',
			'scope'   => 'key_codes:write',
			'handler' => 'api_key_codes_update',
			'dry_run' => true,
			'returns' => 'KeyCode',
			'summary' => 'Change a key code',
			'description' => 'Only what is sent is changed, as on the key code screen; enabled=true switches a used single-use code back on. A new code goes through the same checks as on creation. PATCH is accepted as well, but do not send it to an IIS server. Use POST.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'code',       'in' => 'body', 'type' => 'string', 'max_length' => 50),
				array('name' => 'offer_code', 'in' => 'body', 'type' => 'string', 'max_length' => 50, 'description' => 'The offer has to exist.'),
				array('name' => 'enabled',    'in' => 'body', 'type' => 'bool'),
				array('name' => 'single_use', 'in' => 'body', 'type' => 'bool'),
				array('name' => 'report',     'in' => 'body', 'type' => 'enum', 'values' => array('key_code', 'offer_code')),
				array('name' => 'expires_on', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'An empty string removes the expiry.'),
				array('name' => 'notes',      'in' => 'body', 'type' => 'string', 'max_length' => 2000)
			)
		),

		array(
			'id'      => 'key_codes.delete',
			'method'  => 'DELETE',
			'path'    => '/key-codes/{id}',
			'scope'   => 'key_codes:write',
			'handler' => 'api_key_codes_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a key code',
			'description' => 'Removes the code, as the key code screen does; it stops working at once and it cannot be undone. Orders that used it keep what they recorded. POST /key-codes/{id}/delete does the same for servers that refuse DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'key_codes.delete_post',
			'method'  => 'POST',
			'path'    => '/key-codes/{id}/delete',
			'scope'   => 'key_codes:write',
			'handler' => 'api_key_codes_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'deleted' => 'boolean'),
			'summary' => 'Delete a key code, addressed as a POST',
			'description' => 'The same deletion as DELETE /key-codes/{id}, for clients and servers that cannot send DELETE.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Page design (Visual Page Editor pages) --------------------- */

		array(
			'id'      => 'design.designs.list',
			'method'  => 'GET',
			'path'    => '/design/designs',
			'scope'   => 'design:read',
			'handler' => 'api_design_designs_list',
			'returns' => array('list' => 'Design'),
			'summary' => 'Designs of the Visual Page Editor',
			'description' => 'The designs a page can be added to (POST /design/pages). pages and drafts count the design\'s pages outside the Recycle Bin and those of them kept off the site; home says whether the home page is one of them. state: empty (no pages), live (no drafts), draft (every page that can be a draft is one) or mixed. template is the design template the design started from. Cursor paged.',
			'params'  => array(
				array('name' => 'q',      'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the design name.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'design.pages.create',
			'method'  => 'POST',
			'path'    => '/design/pages',
			'scope'   => 'design:write',
			'handler' => 'api_design_pages_create',
			'dry_run' => true,
			'returns' => 'DesignPage',
			'status'  => 201,
			'summary' => 'Add a page to a design, as a draft',
			'description' => 'A new page of the design, always kept off the site in the drafts folder: it opens in the Visual Page Editor with the design\'s other pages, and a person publishes it there. It starts as an empty canvas, or with the layout of another page of the same design (copy_from; its title, description and settings are not copied). Fill it with proposals (POST /design/pages/{id}/proposals). folder_id is the folder the page is published to (GET /folders), the top folder when left out. name is the page\'s address: spaces and & become _, and 409 name_taken when a page, file, short link or folder already answers at it. 409 page_has_form when the page to copy has a form of its own (the editor duplicates those). publish_at schedules the draft: the site\'s general job puts it on the site at that time, and unpublish_at takes it back to the drafts later; a time that has already come is refused, since a publish time would put the page live at once and the page is not on the site to come off it. The times can be changed later with POST /design/pages/{id}.',
			'params'  => array(
				array('name' => 'design_id',        'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The design (GET /design/designs).'),
				array('name' => 'name',             'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true, 'description' => 'The page name, which is its address.'),
				array('name' => 'title',            'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'folder_id',        'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The folder the page is published to.'),
				array('name' => 'copy_from',        'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A page of the same design whose layout the new page starts with.'),
				array('name' => 'publish_at',       'in' => 'body', 'type' => 'string', 'max_length' => 40, 'nullable' => true, 'description' => 'When the draft goes on the site: ISO 8601 with an offset or Z (2026-10-21T09:30:00+03:00), or a unix time. Fractions of a second are dropped. A time without an offset is refused, as the site\'s time zone is not assumed. Has to be ahead.'),
				array('name' => 'unpublish_at',     'in' => 'body', 'type' => 'string', 'max_length' => 40, 'nullable' => true, 'description' => 'When the page comes off the site again, back to the drafts. Same forms as publish_at; after it when both are set, and ahead.')
			)
		),

		array(
			'id'      => 'design.pages.list',
			'method'  => 'GET',
			'path'    => '/design/pages',
			'scope'   => 'design:read',
			'handler' => 'api_design_pages_list',
			'returns' => array('list' => 'DesignPage'),
			'summary' => 'Pages of the visual designs',
			'description' => 'The pages made with the Visual Page Editor, the only ones whose layout can be read and changed here: their layout is a tree of elements. A page made with a custom style keeps its content as HTML and is not listed. pending_proposals counts the changes waiting for a person to apply them. draft says the page is kept off the site; publish_at and unpublish_at are the times it is scheduled to go on and come off (POST /design/pages/{id}), null for none. Cursor paged.',
			'params'  => array(
				array('name' => 'design_id',     'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the pages of this design.'),
				array('name' => 'q',             'in' => 'query', 'type' => 'string', 'max_length' => 190, 'description' => 'Matches the page name or its title.'),
				array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime'),
				array('name' => 'limit',         'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',        'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'design.pages.get',
			'method'  => 'GET',
			'path'    => '/design/pages/{id}',
			'scope'   => 'design:read',
			'handler' => 'api_design_pages_get',
			'returns' => 'DesignView',
			'summary' => 'A page as an assistant reads it',
			'description' => 'The page\'s layout as plain HTML in which every element carries data-pg="ID", the id a change names it by. <pg-keep data-pg="ID" kind="..."> stands for what the page runs rather than shows - a region, a shared component, a system widget, custom code, a Bootstrap component - which a change may move or remove but not rewrite; its data-pg-props are the settings set_props changes. data-pg-bound names what of an element comes from the data. css is the page\'s own CSS block. outline lists every element with its parent, for a page too long to read at once: read one element with node. hash identifies the page as it is now. draft, publish_at and unpublish_at are as on the page list. With request (Claude\'s application only) the page is shown as the editor tab that asked had it, which may not have been saved. 422 for a page that was not made with the Visual Page Editor.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'node',       'in' => 'query', 'type' => 'string', 'max_length' => 64, 'description' => 'One element (its data-pg id) and what is inside it; the whole page when left out.'),
				array('name' => 'format',     'in' => 'query', 'type' => 'enum', 'values' => array('both', 'html', 'outline'), 'default' => 'both'),
				array('name' => 'request',    'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'A workspace request about this page (Claude\'s application only).'),
				array('name' => 'max_length', 'in' => 'query', 'type' => 'int', 'min' => 2000, 'max' => 2000000, 'description' => 'Cut the HTML at this many bytes; cut says whether it was.')
			)
		),

		array(
			'id'      => 'design.pages.update',
			'method'  => 'POST',
			'also_accepts' => array('PATCH'),
			'path'    => '/design/pages/{id}',
			'scope'   => 'design:write',
			'handler' => 'api_design_pages_update',
			'dry_run' => true,
			'returns' => 'DesignPage',
			'summary' => 'Set when a page goes on the site and comes off it',
			'description' => 'The two times of the Schedule section in the Visual Page Editor\'s Page Settings; nothing else of the page changes here. Send either or both, null or "" clears one, and one left out keeps its stored value. A publish time ahead keeps the page a draft until then - a page that is on the site now is taken off it at once and comes back at that time. A time that has already come is carried out by this request: a passed publish_at publishes the page now, a passed unpublish_at takes it off now. The site\'s general job carries out the times that come later, about once a minute, and clears each one as it does. unpublish_at has to come after publish_at when both are set. The home page stays on the site and takes neither time. 409 page_open_in_editor while somebody has the page open in the editor: their next save would put their copy of the times back. The dry run answers with the state the page would be left in and the times as they would be stored. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached. Use POST.',
			'params'  => array(
				array('name' => 'id',           'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'publish_at',   'in' => 'body', 'type' => 'string', 'max_length' => 40, 'nullable' => true, 'description' => 'When the page goes on the site: ISO 8601 with an offset or Z (2026-10-21T09:30:00+03:00), or a unix time. Fractions of a second are dropped. A time without an offset is refused, as the site\'s time zone is not assumed. null or "" clears it.'),
				array('name' => 'unpublish_at', 'in' => 'body', 'type' => 'string', 'max_length' => 40, 'nullable' => true, 'description' => 'When the page comes off the site, back to the drafts. Same forms as publish_at; null or "" clears it.')
			)
		),

		array(
			'id'      => 'design.revisions.list',
			'method'  => 'GET',
			'path'    => '/design/pages/{id}/revisions',
			'scope'   => 'design:read',
			'handler' => 'api_design_revisions_list',
			'returns' => array('list' => 'DesignRevision'),
			'summary' => 'Published revisions of a page',
			'description' => 'One revision for every publish of the page, newest first: the editor\'s saves, the external API, the importers and the AI editor (source publish, restore, import or ai). A save that changed nothing the revision records writes none, and so does a change of the times that leaves the page where it was; one that moves it on or off the site writes one, since the revision records the page\'s folder. name and title are the page\'s at that publish. The layout is not returned; put a revision back with POST /design/pages/{id}/revisions/{no}/restore. The site keeps the last revisions of each page and the recent ones (Site Settings, Features, Page Revisions), so old numbers disappear. Cursor paged.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'design.revisions.restore',
			'method'  => 'POST',
			'path'    => '/design/pages/{id}/revisions/{no}/restore',
			'scope'   => 'design:write',
			'handler' => 'api_design_revisions_restore',
			'dry_run' => true,
			'returns' => 'DesignRestore',
			'summary' => 'Put a published revision of a page back',
			'description' => 'The layout of that revision becomes the page\'s again, as when a designer loads it from the editor\'s History panel and publishes it: a new publish, on the site at once unless the page is a draft, recorded as a revision of its own with the source restore (revision_no). The page\'s name, title, folder, settings, draft state and times stay as they are now; only the layout comes back. When the page already has that layout nothing is written and changed is false. 409 page_open_in_editor while somebody has the page open in the editor: restore it there instead, from the History panel. 422 when the revision\'s layout is no longer a valid page.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'no', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The revision number (GET /design/pages/{id}/revisions).')
			)
		),

		// Share links of a design's prototype (includes/fn/prototype_links.php).
		// Never MCP tools: a public link to unpublished pages is minted by a
		// person or an integration they set up, not by an AI app.
		array(
			'id'      => 'design.prototype_links.list',
			'method'  => 'GET',
			'path'    => '/design/designs/{id}/prototype-links',
			'scope'   => 'design:read',
			'handler' => 'api_design_prototype_links_list',
			'mcp'     => false,
			'returns' => array('list' => 'DesignPrototypeLink'),
			'summary' => 'Share links of a design\'s prototype',
			'description' => 'The links that open the design\'s draft pages as a clickable prototype, newest first. audience: anyone (whoever has the link) or team (signed-in administrators, designers and managers). state: open, expired or revoked; revoked links are listed only with revoked=true. hint is how the link\'s address starts; the address itself is answered only when the link is made, and the Visual Page Editor shows it again. Only for an application whose owner is an administrator or a designer (403 otherwise). Cursor paged.',
			'params'  => array(
				array('name' => 'id',      'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'revoked', 'in' => 'query', 'type' => 'bool', 'default' => false, 'description' => 'List the revoked links as well.'),
				array('name' => 'limit',   'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50),
				array('name' => 'cursor',  'in' => 'query', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'design.prototype_links.create',
			'method'  => 'POST',
			'path'    => '/design/designs/{id}/prototype-links',
			'scope'   => 'design:write',
			'handler' => 'api_design_prototype_links_create',
			'dry_run' => true,
			'mcp'     => false,
			// The address opens the prototype: not kept in the
			// Idempotency-Key record (includes/api/idempotency.php).
			'idempotency_redact' => array('url'),
			'returns' => 'DesignPrototypeLinkCreated',
			'status'  => 201,
			'summary' => 'Share a design\'s prototype by a link',
			'description' => 'A new link that opens the design\'s draft pages (scheduled ones too) as a clickable prototype, always as they were saved last. audience anyone opens for whoever has the link and needs duration, and draws no draft that goes live in a folder asking for a sign-in; team opens for signed-in administrators, designers and managers, and runs for good without one. duration is one of 3600, 86400, 259200, 604800, 2592000 or 7776000 seconds. start_page_id is the draft it opens on, the design\'s first draft when left out. An optional password (6 to 128 characters) is asked before the prototype opens; ten wrong answers lock it for fifteen minutes. The answer carries url, the link\'s address, and the link. A retry answered from its Idempotency-Key record carries url null: the address is not kept with the key, and the Visual Page Editor shows it again. 422 when the design has no draft pages. Only for an application whose owner is an administrator or a designer (403 otherwise).',
			'params'  => array(
				array('name' => 'id',            'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'audience',      'in' => 'body', 'type' => 'enum', 'values' => array('anyone', 'team'), 'required' => true, 'description' => 'anyone: whoever has the link; team: signed-in administrators, designers and managers.'),
				array('name' => 'duration',      'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'How long the link opens, in seconds from now. Required for anyone.'),
				array('name' => 'start_page_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A draft page of the design the prototype opens on.'),
				array('name' => 'password',      'in' => 'body', 'type' => 'string', 'max_length' => 128, 'secret' => true, 'description' => 'Asked before the prototype opens. 6 to 128 characters, taken as sent (not trimmed); spaces alone are refused.')
			)
		),

		array(
			'id'      => 'design.prototype_links.revoke',
			'method'  => 'POST',
			'path'    => '/design/designs/{id}/prototype-links/{link_id}/revoke',
			'scope'   => 'design:write',
			'handler' => 'api_design_prototype_links_revoke',
			'dry_run' => true,
			'mcp'     => false,
			'returns' => 'DesignPrototypeLink',
			'summary' => 'Revoke a share link of a design\'s prototype',
			'description' => 'The link stops opening the prototype at once and answers as an unknown one does; it cannot be turned back on. Revoking a revoked link changes nothing. Only for an application whose owner is an administrator or a designer (403 otherwise).',
			'params'  => array(
				array('name' => 'id',      'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'link_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The link (GET /design/designs/{id}/prototype-links).')
			)
		),

		array(
			'id'      => 'design.proposals.create',
			'method'  => 'POST',
			'path'    => '/design/pages/{id}/proposals',
			'scope'   => 'design:write',
			'handler' => 'api_design_proposals_create',
			'dry_run' => true,
			'returns' => 'DesignProposal',
			'status'  => 201,
			'summary' => 'Propose a change of a page',
			'description' => 'Nothing on the page changes here. The operations are checked against the page - each must find what it names, and the page they make must be valid - and kept as a proposal that a designer previews and applies, in the Visual Page Editor or under the workspace answer it was made for. A failing operation is refused with 422 and its index in field (ops[2]). Operations, in order: update {node, text, classes, add_classes, remove_classes, style, attrs, name}; set_props {node, props} for a <pg-keep> part; replace {node, html}; insert {parent + index, or before / after, html}; remove {node}; move {node, parent + index, or before / after}; page {html} for the whole page; css {css} for the page\'s own CSS block ("" removes it). In html, an element that keeps its data-pg stays that element, with its settings, notes and data; one without is new; a <pg-keep> left out is removed. Script, event handlers, frames, forms and <style> are removed from html, and CSS loses @import, script and outside url() - dropped says what went. removed names the parts the page runs that the change takes away. With request_id (Claude\'s application only) the proposal answers that workspace request: it is shown in the editor that asked, or under the answer in the channel.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'ops',        'in' => 'body', 'type' => 'list', 'of' => array('op' => 'string'), 'max_items' => 40, 'required' => true, 'description' => 'The operations, as objects with "op".'),
				array('name' => 'summary',    'in' => 'body', 'type' => 'string', 'max_length' => 1000, 'description' => 'One or two sentences on what the change does, for the person who applies it.'),
				array('name' => 'node',       'in' => 'body', 'type' => 'string', 'max_length' => 64, 'description' => 'The element the change is about, when there is one.'),
				array('name' => 'request_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The workspace request this proposal answers.')
			)
		),

		array(
			'id'      => 'design.proposals.list',
			'method'  => 'GET',
			'path'    => '/design/pages/{id}/proposals',
			'scope'   => 'design:read',
			'handler' => 'api_design_proposals_list',
			'returns' => array('list' => 'DesignProposal'),
			'summary' => 'Proposed changes of a page',
			'description' => 'The latest 50, newest first. status pending is waiting for a person; applied says where (applied_to editor: onto an open tab, saved from there; page: onto the saved page); stale no longer fits the page; reverted was taken back.',
			'params'  => array(
				array('name' => 'id',     'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'pending', 'applied', 'dismissed', 'stale', 'failed', 'reverted'), 'default' => 'all')
			)
		),

		array(
			'id'      => 'design.proposals.get',
			'method'  => 'GET',
			'path'    => '/design/proposals/{id}',
			'scope'   => 'design:read',
			'handler' => 'api_design_proposals_get',
			'returns' => 'DesignProposal',
			'summary' => 'One proposed change',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Signing in from a device ------------------------------------ */

		array(
			'id'      => 'auth.login',
			'method'  => 'POST',
			'path'    => '/auth/login',
			'scope'   => '',
			'public'  => true,
			'handler' => 'api_auth_login',
			'returns' => 'AuthSession',
			'status'  => 201,
			'summary' => 'Sign a person in on a device',
			'description' => 'For an app a staff member signs in to with their own panel account, not for an integration: an integration uses its application key and secret. Needs a Team devices application switched on in API settings; client_key names one, and without it the site\'s first active one is used, so a general app only needs the site address. The sign-in screen\'s own limits apply in the same counters - after a few failures the answer is 429 until the wait is over or the person signs in on the website once. Only an account with panel rights can sign in. An account that uses two-step verification also sends the current code from its authenticator app, or one of its recovery codes, in otp; without it the answer is 401 mfa_required, and a wrong code is 401 unauthorized. An account whose role the site requires two-step verification for, and which has not set it up yet, is refused with 403 mfa_setup_required until the person sets it up on the website. The answer holds an access token for the Authorization: Bearer header (one hour) and a refresh token for POST /auth/refresh (thirty days, replaced every time it is used). Every call made with the access token acts as this person, capped by the application\'s permissions.',
			'params'  => array(
				array('name' => 'username',    'in' => 'body', 'type' => 'string', 'max_length' => 190, 'required' => true, 'description' => 'User name or e-mail address, as on the sign-in screen.'),
				array('name' => 'password',    'in' => 'body', 'type' => 'string', 'max_length' => 1000, 'required' => true),
				array('name' => 'device_name', 'in' => 'body', 'type' => 'string', 'max_length' => 100, 'description' => 'What the person will recognise in their device list, such as "Ayşe\'s phone".'),
				array('name' => 'platform',    'in' => 'body', 'type' => 'enum', 'values' => array('ios', 'android', 'web', 'desktop', 'other'), 'default' => 'other'),
				array('name' => 'app_version', 'in' => 'body', 'type' => 'string', 'max_length' => 40),
				array('name' => 'client_key',  'in' => 'body', 'type' => 'string', 'max_length' => 64, 'description' => 'The key of the Team devices application to sign in through. Not a secret.'),
				array('name' => 'otp',         'in' => 'body', 'type' => 'string', 'max_length' => 20, 'description' => 'The current code from the authenticator app, or a recovery code, for an account that uses two-step verification. Without it such an account is answered 401 mfa_required.')
			)
		),

		array(
			'id'      => 'auth.refresh',
			'method'  => 'POST',
			'path'    => '/auth/refresh',
			'scope'   => '',
			'public'  => true,
			'handler' => 'api_auth_refresh',
			'returns' => 'AuthSession',
			'summary' => 'Renew a device\'s tokens',
			'description' => 'Exchanges the refresh token for a new access token and a new refresh token; the old refresh token stops working. Keep the new one before doing anything else. A retry within a minute - the connection dropped before the answer arrived - is answered again; the same old token presented later signs the device out, because by then somebody else holds a copy. A 401 here means sign in again.',
			'params'  => array(
				array('name' => 'refresh_token', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'required' => true)
			)
		),

		array(
			'id'      => 'auth.logout',
			'method'  => 'POST',
			'path'    => '/auth/logout',
			'scope'   => '',
			'public'  => true,
			'handler' => 'api_auth_logout',
			'returns' => array('signed_out' => 'boolean'),
			'summary' => 'Sign this device out',
			'description' => 'Ends the device the token belongs to, with its push registration. Send the access token in the Authorization header - an expired one still names its device - or the refresh token in the body. Always answers signed_out: true, whether or not the token was still known.',
			'params'  => array(
				array('name' => 'refresh_token', 'in' => 'body', 'type' => 'string', 'max_length' => 200)
			)
		),

		array(
			'id'      => 'auth.me',
			'method'  => 'GET',
			'path'    => '/auth/me',
			'scope'   => '',
			'handler' => 'api_auth_me',
			'returns' => 'Me',
			'summary' => 'Who is calling, and what it may do',
			'description' => 'The person behind the call - for a device the one who signed in, for an application key its owner - with the permissions in force for this call, the modules switched on, and the unread notification count when the account permission is held. An app reads this after signing in to decide which screens to show.',
			'params'  => array()
		),

		array(
			'id'      => 'auth.devices.list',
			'method'  => 'GET',
			'path'    => '/auth/devices',
			'scope'   => 'account:read',
			'handler' => 'api_auth_devices_list',
			'returns' => array('list' => 'Device'),
			'summary' => 'The devices this person is signed in on',
			'description' => 'Most recently used first, up to a hundred. current marks the device making the call.',
			'params'  => array()
		),

		array(
			'id'      => 'auth.devices.delete',
			'method'  => 'DELETE',
			'path'    => '/auth/devices/{id}',
			'scope'   => 'account:write',
			'handler' => 'api_auth_devices_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'signed_out' => 'boolean'),
			'summary' => 'Sign one of this person\'s devices out',
			'description' => 'Only the caller\'s own devices; another person\'s id is answered 404. A default IIS install answers DELETE itself before PHP is reached: POST /auth/devices/{id}/delete does the same thing.',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		array(
			'id'      => 'auth.devices.delete_post',
			'method'  => 'POST',
			'path'    => '/auth/devices/{id}/delete',
			'scope'   => 'account:write',
			'handler' => 'api_auth_devices_delete',
			'dry_run' => true,
			'returns' => array('id' => 'integer', 'signed_out' => 'boolean'),
			'summary' => 'Sign one of this person\'s devices out, addressed as a POST',
			'params'  => array(
				array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true)
			)
		),

		/* ----- Notifications ------------------------------------------------ */

		array(
			'id'      => 'notifications.list',
			'method'  => 'GET',
			'path'    => '/notifications',
			'scope'   => 'account:read',
			'handler' => 'api_notifications_list',
			'returns' => array('list' => 'Notification'),
			'summary' => 'This person\'s notifications',
			'description' => 'The panel\'s bell for the same person, newest first: the same rows, the same wording, and only the kinds this account may see. Reading the list does not mark anything read. A page can come back shorter than limit with a next_cursor when many rows in between are not for this person; follow the cursor as usual. After a push arrives, ask for unread=true.',
			'params'  => array(
				array('name' => 'unread', 'in' => 'query', 'type' => 'bool',     'description' => 'Only what this person has not read yet.'),
				array('name' => 'since',  'in' => 'query', 'type' => 'datetime', 'description' => 'Only notifications created at or after this moment.'),
				array('name' => 'limit',  'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 30, 'description' => 'Rows per page.'),
				array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.')
			)
		),

		array(
			'id'      => 'notifications.count',
			'method'  => 'GET',
			'path'    => '/notifications/unread-count',
			'scope'   => 'account:read',
			'handler' => 'api_notifications_count',
			'returns' => array('unread' => 'integer'),
			'summary' => 'How many notifications are unread',
			'description' => 'The number on the bell. Cheap enough to poll; send If-None-Match with the last ETag and an unchanged count answers 304.',
			'params'  => array()
		),

		array(
			'id'      => 'notifications.read',
			'method'  => 'POST',
			'path'    => '/notifications/read',
			'scope'   => 'account:write',
			'handler' => 'api_notifications_read',
			'dry_run' => true,
			'returns' => array('updated' => 'integer', 'unread' => 'integer'),
			'summary' => 'Mark notifications read',
			'description' => 'For this person only - the others\' bells are not touched. Send ids, or all: true for everything unread. An id this person cannot see is skipped rather than refused. unread in the answer is the count afterwards.',
			'params'  => array(
				array('name' => 'ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 500, 'description' => 'Notification ids.'),
				array('name' => 'all', 'in' => 'body', 'type' => 'bool', 'description' => 'Everything unread.')
			)
		),

		array(
			'id'      => 'notifications.unread',
			'method'  => 'POST',
			'path'    => '/notifications/unread',
			'scope'   => 'account:write',
			'handler' => 'api_notifications_unread',
			'dry_run' => true,
			'returns' => array('updated' => 'integer', 'unread' => 'integer'),
			'summary' => 'Mark notifications unread again',
			'params'  => array(
				array('name' => 'ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 500, 'required' => true, 'description' => 'Notification ids.')
			)
		),

		/* ----- Push ---------------------------------------------------------- */

		array(
			'id'      => 'push.config',
			'method'  => 'GET',
			'path'    => '/push/config',
			'scope'   => 'account:read',
			'handler' => 'api_push_config',
			'returns' => array('available' => 'boolean', 'public_key' => 'string?', 'known' => 'boolean'),
			'summary' => 'What a device needs to register for push',
			'description' => 'The site\'s VAPID public key for PushManager.subscribe(). Pass endpoint to ask whether this site still holds a registration for it - an operator may have ended it from the sessions screen while the device still thinks it is registered.',
			'params'  => array(
				array('name' => 'endpoint', 'in' => 'query', 'type' => 'string', 'max_length' => 500)
			)
		),

		array(
			'id'      => 'push.subscribe',
			'method'  => 'POST',
			'path'    => '/push/subscriptions',
			'scope'   => 'account:write',
			'handler' => 'api_push_subscribe',
			'dry_run' => true,
			'returns' => array('subscribed' => 'boolean'),
			'status'  => 201,
			'summary' => 'Register this device for push',
			'description' => 'Web push, the same the installed panel uses. Send the subscription\'s endpoint and its two keys. The push carries no text: it wakes the device, which then asks GET /notifications?unread=true what to show, so nothing a notification says passes through the push service. A registration made with a device token ends when the device is signed out.',
			'params'  => array(
				array('name' => 'endpoint',     'in' => 'body', 'type' => 'string', 'max_length' => 500, 'required' => true, 'description' => 'PushSubscription.endpoint, an https address at the push service.'),
				array('name' => 'p256dh',       'in' => 'body', 'type' => 'string', 'max_length' => 255, 'required' => true, 'description' => 'PushSubscription.keys.p256dh'),
				array('name' => 'auth',         'in' => 'body', 'type' => 'string', 'max_length' => 64,  'required' => true, 'description' => 'PushSubscription.keys.auth'),
				array('name' => 'old_endpoint', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'The endpoint this one replaces, when the browser rotated it.')
			)
		),

		array(
			'id'      => 'push.unsubscribe',
			'method'  => 'POST',
			'path'    => '/push/subscriptions/delete',
			'scope'   => 'account:write',
			'handler' => 'api_push_unsubscribe',
			'dry_run' => true,
			'returns' => array('subscribed' => 'boolean'),
			'summary' => 'Stop push to this device',
			'description' => 'Removes this person\'s registration for the endpoint. Another account\'s registration is left alone.',
			'params'  => array(
				array('name' => 'endpoint', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'required' => true)
			)
		),

		array(
			'id'      => 'push.test',
			'method'  => 'POST',
			'path'    => '/push/test',
			'scope'   => 'account:write',
			'handler' => 'api_push_test',
			'returns' => array('devices' => 'integer', 'delivered' => 'integer'),
			'summary' => 'Wake this person\'s devices',
			'description' => 'Sends a push to every registration this person holds and says how many the push services accepted. Each device then shows whatever its bell holds.',
			'params'  => array()
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
		'dry_run_unsupported'      => array(400, 'This endpoint cannot rehearse a call. Send it without X-Dry-Run or dry_run.'),

		'credentials_missing'      => array(401, 'No key and secret were sent. Authentication is HTTP Basic.'),
		'unauthorized'             => array(401, 'The key is unknown or the secret is wrong. The two are not told apart on purpose.'),
		'token_expired'            => array(401, 'The device\'s access token has run out. Renew it with POST /auth/refresh and repeat the call.'),
		'mfa_required'             => array(401, 'The account uses two-step verification. POST /auth/login wants the code from the authenticator app, or a recovery code, in otp.'),

		'insufficient_scope'       => array(403, 'The application does not hold the permission this endpoint needs.'),
		'application_disabled'     => array(403, 'The application was switched off in the panel.'),
		'application_expired'      => array(403, 'The application reached the date it was set to stop working.'),
		'owner_unavailable'        => array(403, 'The account that owns the application is gone or cannot be used.'),
		'device_signin_unavailable' => array(403, 'Signing in from a device is not switched on: no active Team devices application.'),
		'mfa_setup_required'       => array(403, 'The site requires two-step verification for this account\'s role and the account has not set it up. The person sets it up on the website first, then signs in again.'),
		'https_required'           => array(403, 'The site refuses API calls over plain HTTP.'),
		'ip_not_allowed'           => array(403, 'The address the call came from is not on the application allow list.'),
		'address_blocked'          => array(403, 'The firewall refused the address the call came from.'),
		'forbidden'                => array(403, 'The call is not allowed for a reason the other codes do not cover.'),

		'not_found'                => array(404, 'No record with that id.'),
		'no_path'                  => array(404, 'The address carried no resource. Try /meta.'),
		'unknown_endpoint'         => array(404, 'No endpoint answers that address.'),

		'method_not_allowed'       => array(405, 'The address exists but not with that verb.'),

		'idempotency_key_reused'   => array(409, 'The same Idempotency-Key was used for a different request.'),
		'idempotency_in_progress'  => array(409, 'A request with the same Idempotency-Key is still being carried out. Retry-After says when to send it again; the answer is then the first request\'s.'),

		'file_too_large'           => array(413, 'The upload is over the limit reported by /meta.'),

		'validation_failed'        => array(422, 'A parameter is missing, the wrong type or out of range. The field is named in the answer.'),
		'period_locked'            => array(422, 'The date falls in a period whose books are locked: nothing dated there can be issued, cancelled or paid. Date it after the lock, or have the lock moved in the panel.'),
		'credit_limit'             => array(422, 'The sale would take the customer past the credit limit the store enforces. Take a payment first, or raise the limit on the account.'),

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
