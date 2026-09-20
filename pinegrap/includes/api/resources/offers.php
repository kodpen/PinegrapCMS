<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Offers: the campaign rules the cart is checked against at checkout.
//
// An offer here is a discount rule - a percentage off the order, a gift product
// dropped in the basket, free shipping on a method - and not a sales quote sent
// to a customer. What an outside system asks of one is whether it is running,
// what the cart has to contain for it to apply, and what it then does, which is
// what these two endpoints answer.
//
// Read only. Writing an offer means writing seven tables in step with each
// other, and the editor that does so is built around a screen, not a request.
//
// The row references the tables use between themselves - the rule id, the
// action ids, the upsell page id, the user who last saved - stay inside. A
// client sees the offer as one object with its conditions and its results
// flattened into readable objects: products and groups are named, amounts are
// minor units, percentages are whole numbers.
//
// Nothing is computed on read beyond what the offers screen itself derives:
// the status from the enabled switch and the date range, and the incomplete
// flag for an offer that is saved but cannot do anything at checkout.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// The offer screen's loader is the one reading of the seven offer tables, and
// the API reuses it rather than restating the joins. The file holds only
// function definitions and a guarded constant, so it is safe to include from
// an entry point that has no session.
function api_offer_library() {

	static $loaded = false;

	if ($loaded) {

		return;

	}

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/edit_offer_f.php');

	$loaded = true;

}

function api_offer_select() {

	return "SELECT offers.id, offers.code, offers.description, offers.require_code, offers.status,
			offers.start_date, offers.end_date, offers.scope, offers.multiple_recipients,
			offers.only_apply_best_offer, offers.upsell, offers.upsell_message,
			offers.upsell_trigger_subtotal, offers.upsell_trigger_quantity,
			offers.upsell_action_button_label, offers.timestamp
		FROM offers";

}

function api_offer_statuses() {

	return array('active', 'scheduled', 'expired', 'disabled');

}

// The derived status as a WHERE condition. Today's date comes from PHP rather
// than CURDATE(), so the filter agrees with _pg_offer_status() and with the
// offers screen even when the database runs in another timezone.
function api_offer_status_clause($status) {

	$today = escape(date('Y-m-d'));

	switch ($status) {

		case 'disabled':

			return "(offers.status != 'enabled')";

		case 'scheduled':

			return "(offers.status = 'enabled' AND offers.start_date > '" . $today . "')";

		case 'expired':

			return "(offers.status = 'enabled' AND offers.start_date <= '" . $today . "' AND offers.end_date < '" . $today . "')";

	}

	return "(offers.status = 'enabled' AND offers.start_date <= '" . $today . "' AND offers.end_date >= '" . $today . "')";

}

function api_offer_present($row, $state, $with_details) {

	api_offer_library();

	$out = array(
		'id'                    => (int)$row['id'],
		'code'                  => (string)$row['code'],
		'description'           => (string)$row['description'],
		'enabled'               => ($row['status'] === 'enabled'),
		'offer_status'          => _pg_offer_status($row),
		'incomplete'            => _pg_offer_is_incomplete($state),
		'require_code'          => ((int)$row['require_code'] === 1),
		'start_date'            => (string)$row['start_date'],
		// The stored end of an open-ended offer is a far-off date the screen
		// never shows; a client should not have to know which date that is.
		'end_date'              => ($row['end_date'] === PG_OFFER_OPEN_END_DATE) ? null : (string)$row['end_date'],
		'scope'                 => ($row['scope'] === 'recipient') ? 'recipient' : 'order',
		'multiple_recipients'   => ((int)$row['multiple_recipients'] === 1),
		'only_apply_best_offer' => ((int)$row['only_apply_best_offer'] === 1),
		'upsell' => array(
			'enabled'          => ((int)$row['upsell'] === 1),
			'message'          => (string)$row['upsell_message'],
			'trigger_subtotal' => api_money($row['upsell_trigger_subtotal']),
			'trigger_quantity' => (int)$row['upsell_trigger_quantity'],
			'button_label'     => (string)$row['upsell_action_button_label']
		),
		'updated_at'            => api_time($row['timestamp']),
		'updated_at_unix'       => (int)$row['timestamp']
	);

	if ($with_details) {

		$lookups = api_offer_lookups($state);

		$out['conditions'] = api_offer_conditions_out($state['conditions'], $lookups);

		$out['actions']    = api_offer_actions_out($state['actions'], $lookups);

	}

	return $out;

}

/* ---------------------------------------------------------------------------
   Referenced records
   ---------------------------------------------------------------------------
   Conditions and results point at products, product groups and shipping
   methods by id. Every id the offer refers to is collected first and each
   table is read once, so presenting an offer costs at most three queries on
   top of the loader's own.
   --------------------------------------------------------------------------- */

function api_offer_lookups($state) {

	$product_ids = array();

	$group_ids = array();

	$method_ids = array();

	foreach ($state['conditions'] as $condition) {

		if (($condition['type'] === 'products') && !empty($condition['product_ids'])) {

			$product_ids = array_merge($product_ids, $condition['product_ids']);

		}

		if (($condition['type'] === 'product group') && !empty($condition['group_ids'])) {

			$group_ids = array_merge($group_ids, $condition['group_ids']);

		}

	}

	foreach ($state['actions'] as $action) {

		if (!empty($action['product_id'])) {

			$product_ids[] = $action['product_id'];

		}

		if (!empty($action['group_id'])) {

			$group_ids[] = $action['group_id'];

		}

		if (!empty($action['shipping_method_ids'])) {

			$method_ids = array_merge($method_ids, $action['shipping_method_ids']);

		}

	}

	return array(
		'products' => api_offer_rows_by_id("SELECT id, name, short_description FROM products", $product_ids),
		'groups'   => api_offer_rows_by_id("SELECT id, name FROM product_groups", $group_ids),
		'methods'  => api_offer_rows_by_id("SELECT id, name, code FROM shipping_methods", $method_ids)
	);

}

// Rows of one table for a set of ids, keyed by id. An empty set costs no query.
function api_offer_rows_by_id($select, $ids) {

	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

	if (empty($ids)) {

		return array();

	}

	$out = array();

	foreach (api_rows($select . " WHERE id IN (" . implode(',', $ids) . ")") as $row) {

		$out[(int)$row['id']] = $row;

	}

	return $out;

}

// A product the offer points at. "name" is the merchant's own identifier, as
// the products endpoint reports it; "title" is the text shown to shoppers. A
// product that has since been deleted is still listed by its id with the
// names null, because the offer still refers to it and hiding the reference
// would misreport what the offer does.
function api_offer_product_ref($id, $lookups) {

	$id = (int)$id;

	$row = isset($lookups['products'][$id]) ? $lookups['products'][$id] : null;

	return array(
		'id'    => $id,
		'name'  => ($row === null) ? null : (string)$row['name'],
		'title' => ($row === null) ? null : (string)$row['short_description']
	);

}

function api_offer_group_ref($id, $lookups) {

	$id = (int)$id;

	$row = isset($lookups['groups'][$id]) ? $lookups['groups'][$id] : null;

	return array(
		'id'   => $id,
		'name' => ($row === null) ? null : (string)$row['name']
	);

}

function api_offer_shipping_method_ref($id, $lookups) {

	$id = (int)$id;

	$row = isset($lookups['methods'][$id]) ? $lookups['methods'][$id] : null;

	return array(
		'id'   => $id,
		'name' => ($row === null) ? null : (string)$row['name'],
		'code' => ($row === null) ? null : (string)$row['code']
	);

}

function api_offer_refs($ids, $lookups, $builder) {

	$out = array();

	foreach ($ids as $id) {

		$out[] = call_user_func($builder, $id, $lookups);

	}

	return $out;

}

/* ---------------------------------------------------------------------------
   Conditions and results
   ---------------------------------------------------------------------------
   The stored type names carry spaces ('discount order', 'product group');
   they cross the wire as snake_case identifiers, which is what a client
   switches on.
   --------------------------------------------------------------------------- */

function api_offer_conditions_out($conditions, $lookups) {

	$out = array();

	foreach ($conditions as $condition) {

		switch ($condition['type']) {

			case 'subtotal':

				$out[] = array(
					'type'   => 'subtotal',
					'amount' => api_money($condition['amount'])
				);

				break;

			case 'products':

				$out[] = array(
					'type'     => 'products',
					'quantity' => (int)$condition['quantity'],
					'products' => api_offer_refs($condition['product_ids'], $lookups, 'api_offer_product_ref')
				);

				break;

			case 'product group':

				$out[] = array(
					'type'     => 'product_group',
					'quantity' => (int)$condition['quantity'],
					'groups'   => api_offer_refs($condition['group_ids'], $lookups, 'api_offer_group_ref')
				);

				break;

			case 'cart quantity':

				$out[] = array(
					'type'     => 'cart_quantity',
					'quantity' => (int)$condition['quantity']
				);

				break;

			case 'usage limit':

				// Zero on either side means that side is not limited.
				$out[] = array(
					'type'           => 'usage_limit',
					'total_limit'    => ((int)$condition['total_limit'] > 0) ? (int)$condition['total_limit'] : null,
					'customer_limit' => ((int)$condition['customer_limit'] > 0) ? (int)$condition['customer_limit'] : null
				);

				break;

			case 'new customer':

				// The day count only means something when the condition looks
				// at how recently the customer registered.
				$out[] = array(
					'type' => 'new_customer',
					'mode' => (string)$condition['mode'],
					'days' => ($condition['mode'] === 'no_orders') ? null : (int)$condition['days']
				);

				break;

		}

	}

	return $out;

}

// A discount is stored as one value with a unit; on the wire it is two fields
// of which exactly one is set, so a client never has to look at a unit.
function api_offer_discount_fields($action) {

	if ($action['unit'] === 'amount') {

		return array('amount' => api_money($action['value']), 'percentage' => null);

	}

	return array('amount' => null, 'percentage' => (int)$action['value']);

}

function api_offer_actions_out($actions, $lookups) {

	$out = array();

	foreach ($actions as $action) {

		switch ($action['type']) {

			case 'discount order':

				$tiers = array();

				foreach ($action['tiers'] as $tier) {

					$tiers[] = array(
						'min_subtotal' => api_money($tier['min']),
						'percentage'   => (int)$tier['percent']
					);

				}

				$out[] = array_merge(
					array('type' => 'discount_order'),
					api_offer_discount_fields($action),
					array('tiers' => $tiers)
				);

				break;

			case 'discount product':

				$target = in_array($action['target'], array('group', 'cheapest'), true) ? $action['target'] : 'product';

				$out[] = array_merge(
					array(
						'type'    => 'discount_product',
						'target'  => $target,
						'product' => (($target === 'product') && ($action['product_id'] > 0)) ? api_offer_product_ref($action['product_id'], $lookups) : null,
						'group'   => (($target === 'group') && ($action['group_id'] > 0)) ? api_offer_group_ref($action['group_id'], $lookups) : null
					),
					api_offer_discount_fields($action)
				);

				break;

			case 'add product':

				// The discount here is the one applied to the added product.
				$out[] = array_merge(
					array(
						'type'     => 'add_product',
						'product'  => ($action['product_id'] > 0) ? api_offer_product_ref($action['product_id'], $lookups) : null,
						'quantity' => (int)$action['quantity']
					),
					api_offer_discount_fields($action)
				);

				break;

			case 'discount shipping':

				$out[] = array(
					'type'             => 'discount_shipping',
					'percentage'       => (int)$action['value'],
					'shipping_methods' => api_offer_refs($action['shipping_method_ids'], $lookups, 'api_offer_shipping_method_ref')
				);

				break;

		}

	}

	return $out;

}

/* ---------------------------------------------------------------------------
   Endpoints
   --------------------------------------------------------------------------- */

function api_offers_list($params) {

	api_offer_library();

	$where = array();

	if (isset($params['status']) && $params['status'] !== '') {

		$where[] = api_offer_status_clause($params['status']);

	}

	if (isset($params['code']) && $params['code'] !== '') {

		$where[] = "offers.code = '" . escape($params['code']) . "'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "offers.timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	// The cursor is kept apart from the filters so the optional count answers
	// the whole result rather than the tail of it. Timestamp and id are
	// compared as a pair, so offers saved in the same second neither repeat
	// nor go missing across a page boundary.
	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(offers.timestamp > '" . $cursor['v'] . "'
			OR (offers.timestamp = '" . $cursor['v'] . "' AND offers.id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_offer_select() . $where_sql . "
		ORDER BY offers.timestamp ASC, offers.id ASC
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

		$total = (int)api_value("SELECT COUNT(*) FROM offers"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$out = array();

	foreach ($rows as $row) {

		// The incomplete flag needs the conditions and the results, so each
		// row is loaded in full. The offers screen does the same for every
		// row it lists; a site has tens of offers, not thousands.
		$state = _pg_offer_load($row['id']);

		if ($state === false) {

			continue;

		}

		$out[] = api_offer_present($row, $state, false);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_offers_get($params) {

	api_offer_library();

	$row = api_row(api_offer_select() . " WHERE offers.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Offer'));

	}

	$state = _pg_offer_load($row['id']);

	if ($state === false) {

		api_fail_not_found(lang('Offer'));

	}

	api_ok(api_offer_present($row, $state, true));

}

// Taking an offer off the shop.
//
// The only write this resource has, and it is a removal rather than an edit.
// An offer is a rule tree - conditions the cart has to meet, results the cart
// then gets - and building one belongs with the editor that knows those rules.
// Removing one needs none of that knowledge, and it is the thing an
// integration that manages campaigns actually needs: a season that is over
// should not have to be cleared by hand.
//
// There is no recycle bin for offers, in the panel or here, so the deletion is
// final. The same cleanup the offers screen performs is reused rather than
// copied: the offer row, its condition rows, and the rule and result rows that
// no other offer is still pointing at.
//
// An offer that is running today is refused unless the caller says force. The
// panel has no such refusal, and that is the difference between an operator
// and an application: an operator deleting a live campaign is a decision made
// while looking at the shop, and an integration doing it is a discount that
// vanished from the checkout with nobody having decided anything.
function api_offers_delete($params) {

	api_offer_library();

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_row("SELECT id, code, status, start_date, end_date FROM offers WHERE id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Offer'));

	}

	$status = _pg_offer_status($row);

	if (($status === 'active') && empty($params['force'])) {

		api_fail(409, 'offer_active', lang(array(
			'string' => 'The offer {var:1} is running today. Send force=true to remove it anyway.',
			'vars'   => (string)$row['code']
		)));

	}

	api_dry_run_stop('deleted', 'offer', array(
		'id'           => $id,
		'code'         => (string)$row['code'],
		'offer_status' => $status
	));

	$deleted = _pg_offer_delete($id, $app['owner']['username'], lang(array(
		'string' => 'Offer ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['code'], $app['name'], $app['api_key'])
	)));

	if (!$deleted) {

		api_fail_not_found(lang('Offer'));

	}

	api_ok(array(
		'id'           => $id,
		'code'         => (string)$row['code'],
		'deleted'      => true,
		'offer_status' => $status
	));

}

// What api_offer_present() returns, declared for the OpenAPI document.
function api_offer_schema() {

	return array(
		'id'                    => 'integer',
		'code'                  => 'string',
		'description'           => 'string',
		'enabled'               => 'boolean',
		'offer_status'          => 'string',
		'incomplete'            => 'boolean',
		'require_code'          => 'boolean',
		'start_date'            => 'string',
		'end_date'              => 'string?',
		'scope'                 => 'string',
		'multiple_recipients'   => 'boolean',
		'only_apply_best_offer' => 'boolean',
		'upsell' => array(
			'enabled'          => 'boolean',
			'message'          => 'string',
			'trigger_subtotal' => 'integer',
			'trigger_quantity' => 'integer',
			'button_label'     => 'string'
		),
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer',
		// The single-offer endpoint adds these two. Each entry carries a type
		// and the fields that type needs - a subtotal condition has an amount,
		// a products condition has a quantity and a product list - so they are
		// declared as objects rather than as one shape they do not share. The
		// endpoint description lists the types.
		'conditions' => 'object[]',
		'actions'    => 'object[]'
	);

}
