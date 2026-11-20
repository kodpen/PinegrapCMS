<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's store topic (includes/fn/agent_guide.php): products, stock,
 * orders and the store's settings - the records, the tool for each job, what
 * a write needs first and the frequent mistakes. Tools are named as
 * {tool:route.id}; the order statuses are api_order_statuses(), and every
 * tool of the area marked moves_money is named here, both held by
 * tests/agent_guide_areas_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_store_sections() {

	return array(

		array(
			'id'      => 'records',
			'heading' => 'The records',
			'lines'   => array(
				'Product: an item for sale. name is its SKU and unique; price is in minor units; stock is kept apart from the product.',
				'Product group: a category page, and the parent the variants of one article hang from (sizes, colours).',
				'Order: status is one of incomplete, complete, exported, cancelled (incomplete is a basket); type is online, local (a counter sale) or marketplace. It has lines and a shipment per shipping address.',
				'Store settings: shipping methods, shipping and tax zones, countries and states, currencies.'
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tool for which job',
			'lines'   => array(
				'Catalogue: {tool:products.list} (updated_since for what changed), {tool:products.get}, {tool:products.create}, {tool:products.update}; many prices in one call: {tool:products.prices}.',
				'Stock: {tool:inventory.write} (op set or adjust), many at once {tool:inventory.bulk}; never through the product update.',
				'Groups and variants: {tool:product_groups.list}, {tool:product_groups.variant_sets_create} for one article in several forms.',
				'Orders: {tool:orders.list} (modified_since for what changed), {tool:orders.get}; status and note {tool:orders.update}; tracking numbers {tool:orders.ship}; a return {tool:orders.returns.create}.',
				'Settings: {tool:store.shipping_methods}, {tool:store.zones}, {tool:store.tax_zones}, {tool:store.currencies}, each with its create and update.',
				'These cost or sell, so ask the person before each call: {tool:orders.create} records a counter sale (stock goes down, an invoice may be issued); {tool:orders.labels.create} buys a carrier label.'
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Call the write with dry_run true first and tell the person what it will change.',
				'Send an Idempotency-Key with a create, a stock adjust and a sale, so a retry does not do it twice.',
				'Read the order before {tool:orders.labels.create}: each call buys another label.'
			)
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'A price as 19.99: send 1999. A decimal is refused, never rounded.',
				'A name another product has: 409 name_taken, since the name is the SKU.',
				'Stock sent to {tool:products.update}: it is not written there. A bundle\'s stock follows its products.',
				'group_ids, attributes and images on an update replace the whole list; an empty list removes them all.',
				'canceled with one L: the status is cancelled. An order is never moved back to incomplete.',
				'Tracking numbers on {tool:orders.ship} replace that address\'s list: send all of them each time. The customer is told only with notify true.'
			)
		)

	);

}
