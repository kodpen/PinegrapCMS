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
				array('name' => 'group_ids',         'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'description' => 'Product groups this product belongs to. A variant is a product in the article\'s group - see the product-groups endpoint.'),
				array('name' => 'attributes',        'in' => 'body', 'type' => 'list', 'of' => array('attribute_id' => 'integer', 'option_id' => 'integer'), 'max_items' => 20, 'description' => 'Objects of {attribute_id, option_id}: what makes this row one variant rather than another. Both ids have to belong together.'),
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
			'description' => 'Only the fields present in the body are written. group_ids, attributes and images replace what the product has; an empty list removes all of them. Group membership can also be changed from the group\'s side with POST /product-groups/{id}/products. Stock is not changed here - use the inventory endpoint, which adjusts atomically. PATCH is accepted as well, but do not send it to an IIS server: WebDAV answers it with a 405 before PHP is reached, and IIS then treats that address as a file that does not exist - the next POST to the same product falls through the site rewrite and comes back as the shop\'s 404 page. Use POST.',
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
				array('name' => 'google_product_category', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The Google product category, for a marketplace or shopping feed that asks for one.'),
				array('name' => 'group_ids',         'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'description' => 'Product groups this product belongs to, replacing the current ones. A product in no group is not shown in the shop\'s catalogue tree.'),
				array('name' => 'attributes',        'in' => 'body', 'type' => 'list', 'of' => array('attribute_id' => 'integer', 'option_id' => 'integer'), 'max_items' => 20, 'description' => 'Objects of {attribute_id, option_id}, replacing the current ones: what makes this row one variant rather than another. Both ids have to belong together.'),
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
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer', 'sku' => 'string', 'barcode' => 'string', 'op' => 'string', 'quantity' => 'integer'), 'max_items' => 200, 'required' => true, 'description' => 'Objects of {id, op, quantity}. sku is accepted as an alias for id; barcode looks the product up by its EAN/UPC.')
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
				array('name' => 'items', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer', 'sku' => 'string', 'barcode' => 'string', 'price' => 'integer'), 'required' => true, 'max_items' => 250, 'description' => 'Up to 250 objects: id, sku or barcode to say which product, and price in minor units.')
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

		array(
			'id'      => 'product_groups.create',
			'method'  => 'POST',
			'path'    => '/product-groups',
			'scope'   => 'products:write',
			'handler' => 'api_product_groups_create',
			'dry_run' => true,
			'returns' => 'ProductGroup',
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
				array('name' => 'keywords',          'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'Words that promote the group in the site search.'),
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
			'description' => 'Moves the group to the catalogue\'s Recycle Bin, where an operator can put it back. Send permanent=true to delete it outright, which cannot be undone. Its products are never deleted with it; they stay wherever else they are filed. The root group is refused (409 root_group_protected), and so is a group that still has groups inside it (409 group_not_empty): a branch of the navigation is not removed from outside in one call. POST /product-groups/{id}/delete does the same for servers that refuse DELETE.',
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
				array('name' => 'tracking_numbers', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'description' => 'The complete list for this address, as a JSON array. An empty list removes the numbers it has.'),
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
				array('name' => 'events', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 20, 'required' => true, 'description' => 'Event names. GET this endpoint to see the list.')
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
				array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'disabled'))
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
				array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('active', 'disabled'))
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
			'description' => 'With its values, whether it was sent or only saved. The form is part of the address: a submission made on another form is answered 404.',
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
			'description' => 'What the submitted-form screen lets staff change, for the system that handles enquiries outside the panel: a help desk closing one, a CRM writing the outcome into the office-use fields the visitor never sees. Answers are addressed by the field name, as they are read, and only text box, text area and e-mail address fields can be written; a choice or an upload is refused. A field the visitor left empty gets its answer now.',
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
			'description' => 'What visitors wrote, newest first. status: published, pending (held for a person to publish) or scheduled (published at publish_at; cancel_if_newer drops it when a newer comment comes first). item_type and item_id say what a comment is about when it is not the page itself: a form record (submitted_form), a calendar event, a product group or a product. Cursor paged.',
			'params'  => array(
				array('name' => 'page_id',       'in' => 'query', 'type' => 'int', 'min' => 1),
				array('name' => 'item_type',     'in' => 'query', 'type' => 'enum', 'values' => array('page', 'submitted_form', 'calendar_event', 'product_group', 'product'), 'description' => 'page: comments about the page itself.'),
				array('name' => 'item_id',       'in' => 'query', 'type' => 'int', 'min' => 1),
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
			'summary' => 'Add a page to a design, as a draft',
			'description' => 'A new page of the design, always kept off the site in the drafts folder: it opens in the Visual Page Editor with the design\'s other pages, and a person publishes it there. It starts as an empty canvas, or with the layout of another page of the same design (copy_from; its title, description and settings are not copied). Fill it with proposals (POST /design/pages/{id}/proposals). folder_id is the folder the page is published to (GET /folders), the top folder when left out. name is the page\'s address: spaces and & become _, and 409 name_taken when a page, file, short link or folder already answers at it. 409 page_has_form when the page to copy has a form of its own (the editor duplicates those).',
			'params'  => array(
				array('name' => 'design_id',        'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The design (GET /design/designs).'),
				array('name' => 'name',             'in' => 'body', 'type' => 'string', 'max_length' => 100, 'required' => true, 'description' => 'The page name, which is its address.'),
				array('name' => 'title',            'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'meta_description', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
				array('name' => 'folder_id',        'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The folder the page is published to.'),
				array('name' => 'copy_from',        'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A page of the same design whose layout the new page starts with.')
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
			'description' => 'The pages made with the Visual Page Editor, the only ones whose layout can be read and changed here: their layout is a tree of elements. A page made with a custom style keeps its content as HTML and is not listed. pending_proposals counts the changes waiting for a person to apply them. Cursor paged.',
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
			'description' => 'The page\'s layout as plain HTML in which every element carries data-pg="ID", the id a change names it by. <pg-keep data-pg="ID" kind="..."> stands for what the page runs rather than shows - a region, a shared component, a system widget, custom code, a Bootstrap component - which a change may move or remove but not rewrite; its data-pg-props are the settings set_props changes. data-pg-bound names what of an element comes from the data. css is the page\'s own CSS block. outline lists every element with its parent, for a page too long to read at once: read one element with node. hash identifies the page as it is now. With request (Claude\'s application only) the page is shown as the editor tab that asked had it, which may not have been saved. 422 for a page that was not made with the Visual Page Editor.',
			'params'  => array(
				array('name' => 'id',         'in' => 'path',  'type' => 'int', 'min' => 1, 'required' => true),
				array('name' => 'node',       'in' => 'query', 'type' => 'string', 'max_length' => 64, 'description' => 'One element (its data-pg id) and what is inside it; the whole page when left out.'),
				array('name' => 'format',     'in' => 'query', 'type' => 'enum', 'values' => array('both', 'html', 'outline'), 'default' => 'both'),
				array('name' => 'request',    'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'A workspace request about this page (Claude\'s application only).'),
				array('name' => 'max_length', 'in' => 'query', 'type' => 'int', 'min' => 2000, 'max' => 2000000, 'description' => 'Cut the HTML at this many bytes; cut says whether it was.')
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
		'dry_run_unsupported'      => array(400, 'This endpoint cannot rehearse a call. Send it without X-Dry-Run.'),

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
