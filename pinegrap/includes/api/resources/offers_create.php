<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Creating an offer and duplicating one (POST /offers, POST /offers/{id}/duplicate).
//
// The offer editor owns the rules: _pg_offer_validate() reads its payload and
// _pg_offer_save() writes the seven tables in step. These handlers only turn
// the API's shape - the one GET /offers/{id} answers, snake_case types,
// amount or percentage, minor units - into that payload, refuse what the
// editor would silently bend (a fraction cast to a whole number, an unknown
// mode reset to the default, an amount on a result that only takes a
// percentage), and then hand it over. What the editor refuses is answered
// 422 with the editor's own message.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The API's condition and result type names and the editor's stored ones.
function api_offer_condition_type_map() {

	return array(
		'subtotal'      => 'subtotal',
		'products'      => 'products',
		'product_group' => 'product group',
		'cart_quantity' => 'cart quantity',
		'usage_limit'   => 'usage limit',
		'new_customer'  => 'new customer'
	);

}

function api_offer_action_type_map() {

	return array(
		'discount_order'    => 'discount order',
		'discount_product'  => 'discount product',
		'add_product'       => 'add product',
		'discount_shipping' => 'discount shipping'
	);

}

// The keys each condition and result type takes besides type.
function api_offer_condition_keys() {

	return array(
		'subtotal'      => array('amount'),
		'products'      => array('product_ids', 'quantity'),
		'product_group' => array('group_ids', 'quantity'),
		'cart_quantity' => array('quantity'),
		'usage_limit'   => array('total_limit', 'customer_limit'),
		'new_customer'  => array('mode', 'days')
	);

}

function api_offer_action_keys() {

	return array(
		'discount_order'    => array('amount', 'percentage', 'tiers'),
		'discount_product'  => array('target', 'product_id', 'group_id', 'amount', 'percentage'),
		'add_product'       => array('product_id', 'quantity', 'amount', 'percentage'),
		'discount_shipping' => array('percentage', 'shipping_method_ids')
	);

}

// A whole number as JSON or a form sends it, or null: the editor casts with
// (int), which would read 2.5 as 2 and "abc" as 0.
function api_offer_whole($value) {

	if (is_int($value)) {

		return $value;

	}

	if (is_float($value)) {

		return (floor($value) === $value && abs($value) <= PHP_INT_MAX) ? (int)$value : null;

	}

	if (is_string($value) && preg_match('/^-?\d{1,18}$/', trim($value))) {

		return (int)trim($value);

	}

	return null;

}

// A list of record ids, or null when it is not a list of whole numbers above 0.
function api_offer_id_list($value) {

	if (!is_array($value) || (!empty($value) && array_values($value) !== $value)) {

		return null;

	}

	$ids = array();

	foreach ($value as $item) {

		$id = api_offer_whole($item);

		if ($id === null || $id < 1) {

			return null;

		}

		$ids[] = $id;

	}

	return array_values(array_unique($ids));

}

function api_offer_is_object($value) {

	return is_array($value) && (empty($value) || array_values($value) !== $value);

}

// The message for row $index (0-based) of conditions or actions, numbered from 1.
function api_offer_row_message($list, $index, $message) {

	$vars = array((int)$index + 1, $message);

	if ($list === 'conditions') {

		return lang(array('string' => 'Condition {var:1}: {var:2}', 'vars' => $vars));

	}

	return lang(array('string' => 'Result {var:1}: {var:2}', 'vars' => $vars));

}

// Reads one condition into the editor's shape. Returns array(condition, error).
function api_offer_condition_in($row) {

	$types = api_offer_condition_type_map();

	if (!api_offer_is_object($row) || !isset($row['type']) || !is_string($row['type']) || !isset($types[$row['type']])) {

		return array(null, lang(array('string' => 'type must be one of: {var:1}', 'vars' => implode(', ', array_keys($types)))));

	}

	$type = $row['type'];

	$keys = api_offer_condition_keys();

	foreach (array_keys($row) as $key) {

		if ($key !== 'type' && !in_array($key, $keys[$type], true)) {

			return array(null, lang(array('string' => '{var:1} is not a field of this condition type.', 'vars' => $key)));

		}

	}

	$out = array('type' => $types[$type]);

	foreach (array('amount', 'quantity', 'total_limit', 'customer_limit', 'days') as $field) {

		if (!array_key_exists($field, $row) || $row[$field] === null) {

			continue;

		}

		$number = api_offer_whole($row[$field]);

		if ($number === null || $number < 0) {

			return array(null, lang(array('string' => '{var:1} must be a whole number of at least 0.', 'vars' => $field)));

		}

		$out[$field] = $number;

	}

	foreach (array('product_ids', 'group_ids') as $field) {

		if (array_key_exists($field, $row)) {

			$ids = api_offer_id_list($row[$field]);

			if ($ids === null) {

				return array(null, lang(array('string' => '{var:1} must be a list of ids.', 'vars' => $field)));

			}

			$out[$field] = $ids;

		}

	}

	// A products or group condition with no count asks for one of them.
	if (in_array($type, array('products', 'product_group'), true) && !isset($out['quantity'])) {

		$out['quantity'] = 1;

	}

	if ($type === 'new_customer') {

		$mode = isset($row['mode']) ? $row['mode'] : 'no_orders';

		if (!is_string($mode) || !array_key_exists($mode, _pg_offer_new_customer_modes())) {

			return array(null, lang(array('string' => 'mode must be one of: {var:1}', 'vars' => implode(', ', array_keys(_pg_offer_new_customer_modes())))));

		}

		$out['mode'] = $mode;

	}

	return array($out, '');

}

// Reads one result into the editor's shape. Returns array(action, error).
function api_offer_action_in($row) {

	$types = api_offer_action_type_map();

	if (!api_offer_is_object($row) || !isset($row['type']) || !is_string($row['type']) || !isset($types[$row['type']])) {

		return array(null, lang(array('string' => 'type must be one of: {var:1}', 'vars' => implode(', ', array_keys($types)))));

	}

	$type = $row['type'];

	$keys = api_offer_action_keys();

	foreach (array_keys($row) as $key) {

		if ($key !== 'type' && !in_array($key, $keys[$type], true)) {

			return array(null, lang(array('string' => '{var:1} is not a field of this result type.', 'vars' => $key)));

		}

	}

	$out = array('id' => 0, 'type' => $types[$type], 'unit' => 'percent', 'value' => 0);

	$amount = (array_key_exists('amount', $row) && $row['amount'] !== null);

	$percentage = (array_key_exists('percentage', $row) && $row['percentage'] !== null);

	if ($amount && $percentage) {

		return array(null, lang('Send either amount or percentage, not both.'));

	}

	if ($amount) {

		$value = api_offer_whole($row['amount']);

		if ($value === null || $value < 0) {

			return array(null, lang('amount must be a whole number of minor units of at least 0.'));

		}

		$out['unit'] = 'amount';

		$out['value'] = $value;

	} elseif ($percentage) {

		$value = api_offer_whole($row['percentage']);

		if ($value === null || $value < 0) {

			return array(null, lang('percentage must be a whole number from 0 to 100.'));

		}

		$out['value'] = $value;

	}

	if (isset($row['tiers'])) {

		if ($amount) {

			return array(null, lang('A tiered discount gives percentages; send tiers without amount.'));

		}

		if (!is_array($row['tiers']) || array_values($row['tiers']) !== $row['tiers']) {

			return array(null, lang('tiers must be a list of {min_subtotal, percentage} objects.'));

		}

		$out['tiers'] = array();

		foreach ($row['tiers'] as $tier) {

			$min = (api_offer_is_object($tier) && isset($tier['min_subtotal'])) ? api_offer_whole($tier['min_subtotal']) : null;

			$percent = (api_offer_is_object($tier) && isset($tier['percentage'])) ? api_offer_whole($tier['percentage']) : null;

			if ($min === null || $percent === null || $min < 0 || count($tier) !== 2) {

				return array(null, lang('tiers must be a list of {min_subtotal, percentage} objects.'));

			}

			$out['tiers'][] = array('min' => $min, 'percent' => $percent);

		}

		if (empty($out['tiers'])) {

			unset($out['tiers']);

		}

	}

	// A discount with neither unit is a discount of nothing; only a gift may
	// leave both out, and is then added at its full price.
	if (!$amount && !$percentage && empty($out['tiers']) && $type !== 'add_product') {

		return array(null, lang('Send amount or percentage.'));

	}

	if ($type === 'discount_product') {

		$target = isset($row['target']) ? $row['target'] : 'product';

		if (!in_array($target, array('product', 'group', 'cheapest'), true)) {

			return array(null, lang('target must be one of: product, group, cheapest'));

		}

		$out['target'] = $target;

	}

	foreach (array('product_id', 'group_id', 'quantity') as $field) {

		if (array_key_exists($field, $row) && $row[$field] !== null) {

			$number = api_offer_whole($row[$field]);

			if ($number === null || $number < 1) {

				return array(null, lang(array('string' => '{var:1} must be a whole number of at least 1.', 'vars' => $field)));

			}

			$out[$field] = $number;

		}

	}

	if (array_key_exists('shipping_method_ids', $row)) {

		$ids = api_offer_id_list($row['shipping_method_ids']);

		if ($ids === null) {

			return array(null, lang('shipping_method_ids must be a list of ids.'));

		}

		$out['shipping_method_ids'] = $ids;

	}

	return array($out, '');

}

// The editor's payload for a new offer: $base is _pg_offer_new_state() - an
// empty offer, or the copy of the offer being duplicated - and the sent fields
// are laid over it. Returns array(payload, error, field); error is '' when the
// body could be read.
function api_offer_payload($params, $base, $today) {

	$offer = $base['offer'];

	$offer['id'] = 0;

	foreach (array('code', 'description') as $field) {

		if (array_key_exists($field, $params)) {

			$offer[$field] = trim((string)$params[$field]);

		}

	}

	foreach (array('enabled', 'require_code', 'multiple_recipients', 'only_apply_best_offer') as $field) {

		if (array_key_exists($field, $params)) {

			$offer[$field] = $params[$field] ? 1 : 0;

		}

	}

	if (array_key_exists('scope', $params)) {

		$offer['scope'] = ($params['scope'] === 'recipient') ? 'recipient' : 'order';

	}

	// A date sent makes the period a range: the editor's open period starts
	// today at the latest, and a client may want an open-ended offer that
	// starts next month, which the editor stores the same way - a range that
	// ends on the open end date.
	if (array_key_exists('start_date', $params) || array_key_exists('end_date', $params)) {

		$start = array_key_exists('start_date', $params) ? trim((string)$params['start_date']) : (string)$offer['start_date'];

		$end = array_key_exists('end_date', $params) ? trim((string)$params['end_date']) : (string)$offer['end_date'];

		if ($start === '') {

			$start = $today;

		}

		if ($end === '') {

			$end = PG_OFFER_OPEN_END_DATE;

		}

		if (!_pg_offer_valid_date($start)) {

			return array(null, lang('start_date must be a date as YYYY-MM-DD.'), 'start_date');

		}

		if (!_pg_offer_valid_date($end)) {

			return array(null, lang('end_date must be a date as YYYY-MM-DD, or empty for an offer with no end.'), 'end_date');

		}

		if ($start > $end) {

			return array(null, lang('The end date must not be before the start date.'), 'end_date');

		}

		$offer['period'] = 'range';

		$offer['start_date'] = $start;

		$offer['end_date'] = $end;

	}

	if (array_key_exists('upsell', $params)) {

		$upsell = $params['upsell'];

		foreach (array_keys($upsell) as $key) {

			if (!in_array($key, array('enabled', 'message', 'trigger_subtotal', 'trigger_quantity', 'button_label'), true)) {

				return array(null, lang(array('string' => '{var:1} is not a field of upsell.', 'vars' => $key)), 'upsell');

			}

		}

		if (array_key_exists('enabled', $upsell)) {

			$offer['upsell']['enabled'] = (!empty($upsell['enabled']) && $upsell['enabled'] !== 'false') ? 1 : 0;

		}

		foreach (array('message', 'button_label') as $field) {

			if (array_key_exists($field, $upsell)) {

				$offer['upsell'][$field] = trim((string)$upsell[$field]);

			}

		}

		foreach (array('trigger_subtotal', 'trigger_quantity') as $field) {

			if (array_key_exists($field, $upsell)) {

				$number = api_offer_whole($upsell[$field]);

				if ($number === null || $number < 0 || $number > PG_OFFER_MAX_NUMBER) {

					return array(null, lang(array('string' => 'upsell.{var:1} must be a whole number from 0 to 2147483647.', 'vars' => $field)), 'upsell');

				}

				$offer['upsell'][$field] = $number;

			}

		}

	}

	$conditions = $base['conditions'];

	if (array_key_exists('conditions', $params)) {

		$conditions = array();

		foreach (array_values($params['conditions']) as $index => $row) {

			list($condition, $error) = api_offer_condition_in($row);

			if ($error !== '') {

				return array(null, api_offer_row_message('conditions', $index, $error), 'conditions');

			}

			$conditions[] = $condition;

		}

	}

	$actions = $base['actions'];

	if (array_key_exists('actions', $params)) {

		$actions = array();

		foreach (array_values($params['actions']) as $index => $row) {

			list($action, $error) = api_offer_action_in($row);

			if ($error !== '') {

				return array(null, api_offer_row_message('actions', $index, $error), 'actions');

			}

			$actions[] = $action;

		}

	}

	return array(array('offer' => $offer, 'conditions' => $conditions, 'actions' => $actions), '', null);

}

// The first error _pg_offer_validate() gave, as array(message, field): its
// keys name editor rows ("offer.dates", "actions.1.value"), the API names the
// body field and numbers the row in the message.
function api_offer_editor_error($errors) {

	foreach ($errors as $key => $message) {

		$parts = explode('.', (string)$key);

		if ($parts[0] === 'conditions' || $parts[0] === 'actions') {

			return array(api_offer_row_message($parts[0], isset($parts[1]) ? (int)$parts[1] : 0, $message), $parts[0]);

		}

		$fields = array(
			'offer.code'                => 'code',
			'offer.description'         => 'description',
			'offer.dates'               => 'end_date',
			'offer.upsell.message'      => 'upsell',
			'offer.upsell.button_label' => 'upsell'
		);

		return array($message, isset($fields[$key]) ? $fields[$key] : null);

	}

	return array('', null);

}

// The records a condition or a shipping discount names have to exist: the
// editor picks them from lists, so it checks only the products and groups of
// its results. Returns array(message, field) or null.
function api_offer_missing_refs($payload) {

	$tables = array('product_ids' => 'products', 'group_ids' => 'product_groups', 'shipping_method_ids' => 'shipping_methods');

	foreach (array('conditions', 'actions') as $list) {

		foreach ($payload[$list] as $index => $row) {

			foreach ($tables as $field => $table) {

				if (empty($row[$field])) {

					continue;

				}

				$ids = array_map('intval', $row[$field]);

				$found = (int)api_value("SELECT COUNT(*) FROM " . $table . " WHERE id IN (" . implode(',', $ids) . ")");

				if ($found !== count($ids)) {

					return array(api_offer_row_message($list, $index, lang(array('string' => '{var:1} names a record that does not exist.', 'vars' => $field))), $list);

				}

			}

		}

	}

	return null;

}

// Every check a new offer passes before it is written: the records it names,
// the editor's own checks, the key code clash. Returns the editor's clean
// offer; a refusal ends the request.
function api_offer_create_checked($payload) {

	$missing = api_offer_missing_refs($payload);

	if ($missing !== null) {

		api_fail_validation($missing[0], $missing[1]);

	}

	$checked = _pg_offer_validate($payload);

	if (!empty($checked['errors'])) {

		list($message, $field) = api_offer_editor_error($checked['errors']);

		api_fail_validation($message, $field);

	}

	if (_pg_offer_code_key_code_error($checked['clean']['offer']['code'], null) !== '') {

		api_fail(409, 'code_used_by_key_code', lang('A key code already uses this code. Checkout would apply the offer and the key code would stop working.'));

	}

	return $checked['clean'];

}

// Writes the checked offer through the editor's save and returns it as
// GET /offers/{id} answers it.
function api_offer_create_write($clean, $activity) {

	$app = api_current_app();

	$id = _pg_offer_save($clean, $app['owner'], $app['owner']['username'], $activity);

	$row = ($id > 0) ? api_row(api_offer_select() . " WHERE offers.id = '" . (int)$id . "' LIMIT 1") : null;

	$state = ($row === null) ? false : _pg_offer_load($id);

	if ($state === false) {

		api_fail_server();

	}

	return api_offer_present($row, $state, true);

}

function api_offers_create($params) {

	api_offer_library();

	$app = api_current_app();

	list($payload, $error, $field) = api_offer_payload($params, _pg_offer_new_state(), date('Y-m-d'));

	if ($error !== '') {

		api_fail_validation($error, $field);

	}

	$clean = api_offer_create_checked($payload);

	$code = $clean['offer']['code'];

	api_dry_run_stop('created', 'offer', array('code' => $code));

	api_ok(api_offer_create_write($clean, lang(array(
		'string' => 'Offer ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($code, $app['name'], $app['api_key'])
	))), 201);

}

// The offer screen's Duplicate: the source's settings, conditions and results
// under a new code, written at once since a request has no editor to open.
function api_offers_duplicate($params) {

	api_offer_library();

	$app = api_current_app();

	$source_id = (int)$params['id'];

	$base = _pg_offer_new_state($source_id);

	if (empty($base['meta']['duplicate_of'])) {

		api_fail_not_found(lang('Offer'));

	}

	list($payload, $error, $field) = api_offer_payload($params, $base, date('Y-m-d'));

	if ($error !== '') {

		api_fail_validation($error, $field);

	}

	$clean = api_offer_create_checked($payload);

	$code = $clean['offer']['code'];

	api_dry_run_stop('created', 'offer', array('code' => $code, 'duplicate_of' => $source_id));

	api_ok(api_offer_create_write($clean, lang(array(
		'string' => 'Offer ({var:1}) was duplicated from offer {var:2} through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($code, $source_id, $app['name'], $app['api_key'])
	))), 201);

}
