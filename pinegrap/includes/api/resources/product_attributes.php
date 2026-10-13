<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Product attributes: what a variant is told apart by (Size, Colour) and the
// options it comes in (S, M, L). A product carries one option per attribute
// (POST /products, attributes) and a product group lists the attributes its
// variants are picked by.
//
// Made, changed and removed as on the product attribute screens, under the
// products scope that already writes which options a product carries. The
// name is the attribute's code and unique; the label is what the shop shows.
//
// Options are never removed by being left out: an option goes only when its id
// is sent in remove_option_ids, and with it every product's link to it, as the
// attribute screen removes it. Removing an attribute a product or a group uses
// asks for force, because it takes the attribute off all of them at once.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_product_attribute_option_present($row) {

	return array(
		'id'       => (int)$row['id'],
		'label'    => (string)$row['label'],
		// The "no thanks" choice: picking it means the shopper wants none of
		// what the attribute offers.
		'no_value' => ((int)$row['no_value'] === 1)
	);

}

function api_product_attribute_option_schema() {

	return array(
		'id'       => 'integer',
		'label'    => 'string',
		'no_value' => 'boolean'
	);

}

function api_product_attribute_present($row, $extras) {

	$id = (int)$row['id'];

	return array(
		'id'              => $id,
		'name'            => (string)$row['name'],
		'label'           => (string)$row['label'],
		'options'         => isset($extras['options'][$id]) ? $extras['options'][$id] : array(),
		// How many products carry one of its options, and how many product
		// groups pick their variants by it, Recycle Bin included.
		'products'        => isset($extras['products'][$id]) ? (int)$extras['products'][$id] : 0,
		'product_groups'  => isset($extras['product_groups'][$id]) ? (int)$extras['product_groups'][$id] : 0,
		'created_at'      => api_time($row['created_timestamp']),
		'updated_by'      => ((string)$row['last_modified_username'] !== '') ? (string)$row['last_modified_username'] : null,
		'updated_at'      => api_time($row['last_modified_timestamp']),
		'updated_at_unix' => (int)$row['last_modified_timestamp']
	);

}

function api_product_attribute_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string',
		'label'           => 'string',
		'options'         => 'ProductAttributeOption[]',
		'products'        => 'integer',
		'product_groups'  => 'integer',
		'created_at'      => 'string?',
		'updated_by'      => 'string?',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

function api_product_attribute_select() {

	return "SELECT product_attributes.*, last_modified_user.user_username AS last_modified_username
		FROM product_attributes
		LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = product_attributes.last_modified_user_id";

}

// Options and usage for many attributes in three queries, whatever the page
// size.
function api_product_attribute_extras($ids) {

	$extras = array('options' => array(), 'products' => array(), 'product_groups' => array());

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $extras;

	}

	$in = implode(',', $ids);

	foreach (api_rows("SELECT id, product_attribute_id, label, no_value FROM product_attribute_options
		WHERE product_attribute_id IN (" . $in . ")
		ORDER BY product_attribute_id ASC, sort_order ASC, id ASC") as $row) {

		$extras['options'][(int)$row['product_attribute_id']][] = api_product_attribute_option_present($row);

	}

	foreach (api_rows("SELECT attribute_id, COUNT(DISTINCT product_id) AS products FROM products_attributes_xref
		WHERE attribute_id IN (" . $in . ") GROUP BY attribute_id") as $row) {

		$extras['products'][(int)$row['attribute_id']] = (int)$row['products'];

	}

	foreach (api_rows("SELECT attribute_id, COUNT(DISTINCT product_group_id) AS product_groups FROM product_groups_attributes_xref
		WHERE attribute_id IN (" . $in . ") GROUP BY attribute_id") as $row) {

		$extras['product_groups'][(int)$row['attribute_id']] = (int)$row['product_groups'];

	}

	return $extras;

}

function api_product_attribute_load_or_fail($id) {

	$row = api_row(api_product_attribute_select() . " WHERE product_attributes.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Product attribute'));

	}

	return $row;

}

function api_product_attribute_answer($id, $status = 200) {

	$row = api_product_attribute_load_or_fail($id);

	api_ok(api_product_attribute_present($row, api_product_attribute_extras(array((int)$row['id']))), $status);

}

// By id, so a page boundary never moves when an attribute is renamed.
function api_product_attributes_list($params) {

	$where = array();

	if (isset($params['q']) && $params['q'] !== '') {

		$like = escape(escape_like($params['q']));

		$where[] = "(product_attributes.name LIKE '%" . $like . "%' OR product_attributes.label LIKE '%" . $like . "%')";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "product_attributes.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_product_attribute_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY product_attributes.id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	$extras = api_product_attribute_extras($ids);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_product_attribute_present($row, $extras);

	}

	api_ok_list($out, $limit, $next);

}

function api_product_attributes_get($params) {

	api_product_attribute_answer($params['id']);

}

// The options a request sent, checked and tidied, without touching the
// database. Each is {label, no_value} and, when $with_ids, may carry the id of
// an option it changes. Answers array('options' => list, 'problem' => null) or
// the first problem found: array('kind' => shape|label|long|twice, 'index' =>
// position, 'label' => the label concerned).
function api_product_attribute_options_from($raw, $with_ids) {

	$options = array();

	$seen = array();

	foreach (array_values(is_array($raw) ? $raw : array()) as $index => $entry) {

		if (!is_array($entry) || (!$with_ids && array_key_exists('id', $entry))
			|| array_diff(array_keys($entry), array('id', 'label', 'no_value'))) {

			return array('options' => array(), 'problem' => array('kind' => 'shape', 'index' => $index, 'label' => ''));

		}

		$id = 0;

		if (array_key_exists('id', $entry) && ($entry['id'] !== null)) {

			if (!is_numeric($entry['id']) || ((int)$entry['id'] <= 0) || ((string)(int)$entry['id'] !== trim((string)$entry['id']))) {

				return array('options' => array(), 'problem' => array('kind' => 'shape', 'index' => $index, 'label' => ''));

			}

			$id = (int)$entry['id'];

		}

		$label = isset($entry['label']) && is_scalar($entry['label']) ? trim((string)$entry['label']) : '';

		if ($label === '') {

			return array('options' => array(), 'problem' => array('kind' => 'label', 'index' => $index, 'label' => ''));

		}

		if (mb_strlen($label) > 255) {

			return array('options' => array(), 'problem' => array('kind' => 'long', 'index' => $index, 'label' => mb_substr($label, 0, 60)));

		}

		$key = mb_strtolower($label);

		if (isset($seen[$key])) {

			return array('options' => array(), 'problem' => array('kind' => 'twice', 'index' => $index, 'label' => $label));

		}

		$seen[$key] = true;

		$option = array('id' => $id, 'label' => $label);

		// Left out on an option that already exists, its "no thanks" flag
		// stays as it is.
		if (array_key_exists('no_value', $entry)) {

			$option['no_value'] = ($entry['no_value'] === true) || in_array(strtolower(trim((string)$entry['no_value'])), array('1', 'true', 'yes', 'on'), true);

		}

		$options[] = $option;

	}

	return array('options' => $options, 'problem' => null);

}

function api_product_attribute_options_or_fail($raw, $with_ids) {

	$read = api_product_attribute_options_from($raw, $with_ids);

	if ($read['problem'] === null) {

		return $read['options'];

	}

	$problem = $read['problem'];

	$position = $problem['index'] + 1;

	switch ($problem['kind']) {

		case 'label':
			api_fail_validation(lang(array('string' => 'Option {var:1} has no label.', 'vars' => array($position))), 'options');

		case 'long':
			api_fail_validation(lang(array('string' => 'The label of option {var:1} is longer than the {var:2} characters accepted.', 'vars' => array($position, 255))), 'options');

		case 'twice':
			api_fail_validation(lang(array('string' => 'The option {var:1} is given more than once.', 'vars' => array($problem['label']))), 'options');

	}

	api_fail_validation($with_ids
		? lang('Each option must be an object of {id, label, no_value}; id only for an option the attribute already has.')
		: lang('Each option must be an object of {label, no_value}.'), 'options');

}

// What a change to an attribute's options comes to, worked out against the
// options it has, without touching the database. $current is the attribute's
// options in order (id, label, no_value); $sent the checked options of the
// request; $remove the option ids to take away. Answers array('update' =>
// rows to rewrite, 'insert' => rows to add at the end, 'remove' => ids,
// 'problem' => null) or the first problem: array('kind' => foreign|removed|
// twice|empty, 'id' => option id, 'label' => label).
function api_product_attribute_option_plan($current, $sent, $remove) {

	$plan = array('update' => array(), 'insert' => array(), 'remove' => array(), 'problem' => null);

	$by_id = array();

	foreach ($current as $option) {

		$by_id[(int)$option['id']] = $option;

	}

	foreach ($remove as $id) {

		$id = (int)$id;

		if (!isset($by_id[$id])) {

			$plan['problem'] = array('kind' => 'foreign', 'id' => $id, 'label' => '');

			return $plan;

		}

		if (!in_array($id, $plan['remove'], true)) {

			$plan['remove'][] = $id;

		}

	}

	// The labels the attribute ends up with, to keep two options from
	// reading the same in the variant picker.
	$labels = array();

	foreach ($by_id as $id => $option) {

		if (!in_array($id, $plan['remove'], true)) {

			$labels[$id] = trim((string)$option['label']);

		}

	}

	foreach ($sent as $option) {

		$id = (int)$option['id'];

		if ($id > 0) {

			if (!isset($by_id[$id])) {

				$plan['problem'] = array('kind' => 'foreign', 'id' => $id, 'label' => $option['label']);

				return $plan;

			}

			if (in_array($id, $plan['remove'], true)) {

				$plan['problem'] = array('kind' => 'removed', 'id' => $id, 'label' => $option['label']);

				return $plan;

			}

			$labels[$id] = $option['label'];

			$plan['update'][] = array(
				'id'       => $id,
				'label'    => $option['label'],
				'no_value' => array_key_exists('no_value', $option) ? (bool)$option['no_value'] : ((int)$by_id[$id]['no_value'] === 1)
			);

		} else {

			$plan['insert'][] = array(
				'label'    => $option['label'],
				'no_value' => !empty($option['no_value'])
			);

		}

	}

	foreach ($plan['insert'] as $option) {

		$labels[] = $option['label'];

	}

	if (empty($labels)) {

		$plan['problem'] = array('kind' => 'empty', 'id' => 0, 'label' => '');

		return $plan;

	}

	$seen = array();

	foreach ($labels as $label) {

		$key = mb_strtolower($label);

		if (isset($seen[$key])) {

			$plan['problem'] = array('kind' => 'twice', 'id' => 0, 'label' => $label);

			return $plan;

		}

		$seen[$key] = true;

	}

	return $plan;

}

// The attribute's name is its code: the shop, the importers and product
// groups refer to it by name, so a second attribute of the same name would be
// a mapping resolved to the wrong one sooner or later.
function api_product_attribute_name_or_fail($name, $except_id = 0) {

	$name = trim((string)$name);

	if ($name === '') {

		api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => array('name'))), 'name');

	}

	$taken = api_value("SELECT id FROM product_attributes WHERE name = '" . escape($name) . "' AND id <> '" . (int)$except_id . "' LIMIT 1");

	if ($taken) {

		api_fail(409, 'name_taken', lang(array(
			'string' => 'A product attribute with the name {var:1} already exists (id {var:2}).',
			'vars'   => array($name, (int)$taken)
		)), 'name');

	}

	return $name;

}

function api_product_attribute_insert_option($attribute_id, $option, $sort_order) {

	api_exec("INSERT INTO product_attribute_options (product_attribute_id, label, no_value, sort_order)
		VALUES ('" . (int)$attribute_id . "', '" . escape($option['label']) . "', '" . (!empty($option['no_value']) ? '1' : '0') . "', '" . (int)$sort_order . "')");

}

function api_product_attributes_create($params) {

	$app = api_current_app();

	$name = api_product_attribute_name_or_fail($params['name']);

	$label = isset($params['label']) ? (string)$params['label'] : '';

	$options = api_product_attribute_options_or_fail($params['options'], false);

	if (empty($options)) {

		api_fail_validation(lang('Please add an option.'), 'options');

	}

	api_dry_run_stop('created', 'product_attribute', array('name' => $name, 'options' => count($options)));

	$owner_id = (int)$app['owner']['id'];

	api_exec("INSERT INTO product_attributes (name, label, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
		VALUES ('" . escape($name) . "', '" . escape($label) . "', '" . $owner_id . "', UNIX_TIMESTAMP(), '" . $owner_id . "', UNIX_TIMESTAMP())");

	$id = api_insert_id();

	if ($id <= 0) {

		api_fail_server();

	}

	$sort_order = 0;

	foreach ($options as $option) {

		api_product_attribute_insert_option($id, $option, ++$sort_order);

	}

	log_activity(lang(array(
		'string' => 'Product attribute ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_product_attribute_answer($id, 201);

}

// Only what is sent is changed. options changes the options it names by id
// and adds the ones without one at the end; the others stay as they are.
function api_product_attributes_update($params) {

	$app = api_current_app();

	$row = api_product_attribute_load_or_fail($params['id']);

	$id = (int)$row['id'];

	$set = array();

	if (isset($params['name'])) {

		$set['name'] = ((string)$params['name'] === (string)$row['name'])
			? (string)$row['name']
			: api_product_attribute_name_or_fail($params['name'], $id);

	}

	if (isset($params['label'])) {

		$set['label'] = (string)$params['label'];

	}

	$sent = array_key_exists('options', $params) ? api_product_attribute_options_or_fail($params['options'], true) : array();

	$remove = array();

	if (array_key_exists('remove_option_ids', $params)) {

		foreach ((array)$params['remove_option_ids'] as $option_id) {

			if (!is_numeric($option_id) || ((int)$option_id <= 0)) {

				api_fail_validation(lang('remove_option_ids must be a list of option ids.'), 'remove_option_ids');

			}

			$remove[] = (int)$option_id;

		}

	}

	if (empty($set) && empty($sent) && empty($remove)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$current = api_rows("SELECT id, label, no_value FROM product_attribute_options
		WHERE product_attribute_id = '" . $id . "' ORDER BY sort_order ASC, id ASC");

	$plan = api_product_attribute_option_plan($current, $sent, $remove);

	if ($plan['problem'] !== null) {

		$problem = $plan['problem'];

		switch ($problem['kind']) {

			case 'foreign':
				api_fail_validation(lang(array(
					'string' => 'Option {var:1} does not belong to attribute {var:2}.',
					'vars'   => array($problem['id'], $id)
				)), ($problem['label'] === '') ? 'remove_option_ids' : 'options');

			case 'removed':
				api_fail_validation(lang(array(
					'string' => 'Option {var:1} is both changed and removed.',
					'vars'   => array($problem['id'])
				)), 'options');

			case 'empty':
				api_fail_validation(lang('Please add an option.'), 'remove_option_ids');

		}

		api_fail_validation(lang(array(
			'string' => 'The option {var:1} is given more than once.',
			'vars'   => array($problem['label'])
		)), 'options');

	}

	api_dry_run_stop('updated', 'product_attribute', array(
		'id'              => $id,
		'changes'         => array_keys($set),
		'options_changed' => count($plan['update']),
		'options_added'   => count($plan['insert']),
		'options_removed' => count($plan['remove'])
	));

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape($value) . "'";

	}

	$columns[] = "last_modified_user_id = '" . (int)$app['owner']['id'] . "'";

	$columns[] = "last_modified_timestamp = UNIX_TIMESTAMP()";

	api_exec("UPDATE product_attributes SET " . implode(', ', $columns) . " WHERE id = '" . $id . "'");

	foreach ($plan['update'] as $option) {

		api_exec("UPDATE product_attribute_options
			SET label = '" . escape($option['label']) . "', no_value = '" . ($option['no_value'] ? '1' : '0') . "'
			WHERE id = '" . (int)$option['id'] . "' AND product_attribute_id = '" . $id . "'");

	}

	if (!empty($plan['insert'])) {

		$sort_order = (int)api_value("SELECT MAX(sort_order) FROM product_attribute_options WHERE product_attribute_id = '" . $id . "'");

		foreach ($plan['insert'] as $option) {

			api_product_attribute_insert_option($id, $option, ++$sort_order);

		}

	}

	if (!empty($plan['remove'])) {

		$in = implode(',', array_map('intval', $plan['remove']));

		api_exec("DELETE FROM product_attribute_options WHERE id IN (" . $in . ") AND product_attribute_id = '" . $id . "'");

		// The products that came in a removed option lose that link, as on the
		// attribute screen, and a product group whose preselected option it
		// was preselects none rather than an option that is gone.
		api_exec("DELETE FROM products_attributes_xref WHERE option_id IN (" . $in . ")");

		api_exec("UPDATE product_groups_attributes_xref SET default_option_id = '0'
			WHERE attribute_id = '" . $id . "' AND default_option_id IN (" . $in . ")");

	}

	log_activity(lang(array(
		'string' => 'Product attribute ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(isset($set['name']) ? $set['name'] : $row['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_product_attribute_answer($id);

}

// As on the attribute screen: the attribute, its options and every product's
// and product group's link to it. It cannot be undone, and taking an attribute
// off products changes what the shop offers, so one that is in use asks for
// force.
function api_product_attributes_delete($params) {

	$app = api_current_app();

	$row = api_product_attribute_load_or_fail($params['id']);

	$id = (int)$row['id'];

	$extras = api_product_attribute_extras(array($id));

	$products = isset($extras['products'][$id]) ? (int)$extras['products'][$id] : 0;

	$groups = isset($extras['product_groups'][$id]) ? (int)$extras['product_groups'][$id] : 0;

	if ((($products > 0) || ($groups > 0)) && empty($params['force'])) {

		api_fail(409, 'attribute_in_use', lang(array(
			'string' => 'This attribute is on {var:1} products and {var:2} product groups. Send force=true to take it off them and delete it.',
			'vars'   => array($products, $groups)
		)));

	}

	api_dry_run_stop('deleted', 'product_attribute', array('id' => $id, 'products' => $products, 'product_groups' => $groups));

	api_exec("DELETE FROM product_attributes WHERE id = '" . $id . "'");

	api_exec("DELETE FROM product_attribute_options WHERE product_attribute_id = '" . $id . "'");

	api_exec("DELETE FROM products_attributes_xref WHERE attribute_id = '" . $id . "'");

	api_exec("DELETE FROM product_groups_attributes_xref WHERE attribute_id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Product attribute ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true, 'products' => $products, 'product_groups' => $groups));

}
