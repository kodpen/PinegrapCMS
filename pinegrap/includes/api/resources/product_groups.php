<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The parent a set of variants hangs from.
//
// This catalogue has no separate variant table: every row in products is one
// buyable thing, and a product group is what gathers the ones that are the same
// article in different sizes or colours. A marketplace needs both halves - it
// lists one article and offers the choices underneath it - so an integration
// that can only see /products sees fifteen unrelated gift cards where the shop
// means one product with fifteen amounts.
//
// So this endpoint answers the question /products cannot: which rows belong
// together, and along which axis they differ.
//
// Groups are how the shop's own navigation is built, and an integration
// reshaping them would move pages around on the live site. So the writes stop
// short of the tree: a group's own text and its publishing can be changed, a
// new group can be filed under a parent that exists, products can be put into
// and taken out of a group, and a group with no groups beneath it can be
// deleted. Moving an existing group to another parent, or re-ordering the
// tree, stays on the catalogue screen.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// The shape of a group on the wire. $full adds the long text and the option
// lists, which the listing leaves out: a page of fifty groups carrying every
// option of every attribute is a large answer to a question nobody asked.
function api_product_group_present($row, $extras = array(), $full = false, $with_issues = false) {

	$id = (int)$row['id'];

	$out = array(
		'id'          => $id,
		'name'        => $row['name'],
		// The address the group has on the site. Useful to a connector that
		// wants to point the marketplace listing back at the shop.
		'slug'        => $row['address_name'],
		'enabled'     => ((int)$row['enabled'] === 1),
		'parent_id'   => (int)$row['parent_id'],
		'sort_order'  => (int)$row['sort_order'],
		'title'       => $row['title'],
		'short_description' => $row['short_description'],
		'image'       => (!empty($row['image_name']))
			? (URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($row['image_name']))
			: null,
		// How the shop itself presents the group: browse is a category page,
		// select is a single article with the variants chosen on it. A
		// connector that has to guess which groups are listable articles should
		// start here rather than with the attribute list.
		'display_type' => $row['display_type'],
		'variant_selection' => ((int)$row['attributes'] === 1),
		// The axes the variants differ along, in the order the shop shows them.
		// Empty means the group is a plain category.
		'attributes'   => isset($extras['attributes'][$id]) ? $extras['attributes'][$id] : array(),
		// The products in this group. These are the variants.
		'product_ids'  => isset($extras['products'][$id]) ? $extras['products'][$id] : array(),
		// In the catalogue's Recycle Bin: off the shop and out of the listing,
		// still readable by id until the bin is emptied.
		'recycled'        => (isset($row['recycled']) && ((int)$row['recycled'] === 1)),
		'updated_at'      => api_time($row['timestamp']),
		'updated_at_unix' => (int)$row['timestamp'],
		'seo'             => api_seo_block('product_group', $id, $row, $with_issues)
	);

	if ($full) {

		$out['description']      = $row['full_description'];
		$out['meta_description'] = $row['meta_description'];
		$out['meta_keywords']    = $row['meta_keywords'];
		$out['keywords']         = $row['keywords'];

	}

	return $out;

}

// Everything that hangs off a group, for a whole page of them at once. Three
// queries for the page, five when the option lists are asked for - never one
// per row, for the same reason the product listing does not.
function api_product_group_extras($ids, $with_options = false) {

	$extras = array('attributes' => array(), 'products' => array());

	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (empty($ids)) {

		return $extras;

	}

	$in = implode(',', $ids);

	// A binned product keeps its group links so that restoring it puts it back
	// where it was, but it is off the shop: GET /products does not list it and
	// adding it to a group is refused. A connector building variants from
	// product_ids would publish it, so it is left out here, and with it the
	// options only it carries drop out of in_use below.
	$products = api_rows("SELECT products_groups_xref.product, products_groups_xref.product_group
		FROM products_groups_xref
		INNER JOIN products ON products.id = products_groups_xref.product
		WHERE products_groups_xref.product_group IN (" . $in . ")"
		. (api_catalog_bin_columns() ? " AND products.recycled = '0'" : '') . "
		ORDER BY products_groups_xref.product_group ASC, products_groups_xref.sort_order ASC");

	$product_ids = array();

	foreach ($products as $row) {

		$key = (int)$row['product_group'];

		if (!isset($extras['products'][$key])) { $extras['products'][$key] = array(); }

		$extras['products'][$key][] = (int)$row['product'];

		$product_ids[(int)$row['product']] = true;

	}

	// The attribute order is the group's own, not the attribute table's: the
	// same Size attribute may come first on one article and second on another,
	// and a variant matrix built in the wrong order reads as a different
	// product.
	//
	// Ordered by sort_order and read once per attribute, because the table
	// carries no unique key and a group can end up holding the same attribute
	// twice - the sample catalogue does. Two identical axes would multiply out
	// into a variant matrix with every combination listed twice.
	$attributes = api_rows("SELECT product_groups_attributes_xref.product_group_id,
			product_groups_attributes_xref.default_option_id,
			product_groups_attributes_xref.sort_order,
			product_attributes.id AS attribute_id,
			product_attributes.name AS attribute_name,
			product_attributes.label AS attribute_label
		FROM product_groups_attributes_xref
		INNER JOIN product_attributes
			ON product_attributes.id = product_groups_attributes_xref.attribute_id
		WHERE product_groups_attributes_xref.product_group_id IN (" . $in . ")
		ORDER BY product_groups_attributes_xref.product_group_id ASC,
			product_groups_attributes_xref.sort_order ASC,
			product_attributes.id ASC");

	$attribute_ids = array();

	$seen = array();

	foreach ($attributes as $row) {

		$key = (int)$row['product_group_id'];

		$attribute_id = (int)$row['attribute_id'];

		if (isset($seen[$key . ':' . $attribute_id])) {

			continue;

		}

		$seen[$key . ':' . $attribute_id] = true;

		if (!isset($extras['attributes'][$key])) { $extras['attributes'][$key] = array(); }

		$attribute_ids[$attribute_id] = true;

		$item = array(
			'attribute_id'      => $attribute_id,
			'name'              => $row['attribute_name'],
			'label'             => $row['attribute_label'],
			'default_option_id' => (int)$row['default_option_id'],
			'sort_order'        => (int)$row['sort_order']
		);

		if ($with_options) { $item['options'] = array(); }

		$extras['attributes'][$key][] = $item;

	}

	// The option lists, once per attribute rather than once per group that uses
	// it, then handed to every group that does.
	if ($with_options && !empty($attribute_ids)) {

		// Which options a product in this catalogue actually carries. An
		// attribute defines every size the shop has ever sold; this group may
		// only stock three of them, and a marketplace listing built from the
		// full list offers sizes that cannot be bought.
		$in_use = array();

		if (!empty($product_ids)) {

			$rows = api_rows("SELECT DISTINCT option_id FROM products_attributes_xref
				WHERE product_id IN (" . implode(',', array_keys($product_ids)) . ")");

			foreach ($rows as $row) {

				$in_use[(int)$row['option_id']] = true;

			}

		}

		$options = array();

		$rows = api_rows("SELECT id, product_attribute_id, label, no_value, sort_order
			FROM product_attribute_options
			WHERE product_attribute_id IN (" . implode(',', array_keys($attribute_ids)) . ")
			ORDER BY product_attribute_id ASC, sort_order ASC, id ASC");

		foreach ($rows as $row) {

			$key = (int)$row['product_attribute_id'];

			if (!isset($options[$key])) { $options[$key] = array(); }

			$options[$key][] = array(
				'id'         => (int)$row['id'],
				'label'      => $row['label'],
				// The shop's "no choice made" entry. It is not a variant, and a
				// connector that sends it to a marketplace publishes a size
				// called nothing.
				'no_value'   => ((int)$row['no_value'] === 1),
				// Whether a product in this group is on this option. The whole
				// list is returned either way, because the connector may be
				// mapping the shop vocabulary onto the marketplace's own and
				// needs to see all of it.
				'in_use'     => isset($in_use[(int)$row['id']]),
				'sort_order' => (int)$row['sort_order']
			);

		}

		foreach ($extras['attributes'] as $group_id => $list) {

			foreach ($list as $index => $item) {

				$attribute_id = $item['attribute_id'];

				$extras['attributes'][$group_id][$index]['options'] =
					isset($options[$attribute_id]) ? $options[$attribute_id] : array();

			}

		}

	}

	return $extras;

}

function api_product_group_select() {

	return "SELECT id, name, address_name, enabled, parent_id, sort_order,
			title, short_description, full_description, meta_description,
			meta_keywords, keywords, image_name, display_type, attributes,
			" . (api_catalog_bin_columns() ? "recycled," : "0 AS recycled,") . "
			" . api_seo_columns() . "
			timestamp
		FROM product_groups";

}

function api_product_groups_list($params) {

	$where = array();

	// A binned group is deleted as far as the shop is concerned; it is read by
	// id, not listed.
	if (api_catalog_bin_columns()) {

		$where[] = "recycled = '0'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['enabled'])) {

		$where[] = "enabled = '" . ($params['enabled'] ? '1' : '0') . "'";

	}

	// Zero is the top of the tree and a real answer, so the parameter is tested
	// for presence rather than for truth.
	if (isset($params['parent_id'])) {

		$where[] = "parent_id = '" . (int)$params['parent_id'] . "'";

	}

	// Groups that actually gather variants. This is the filter a marketplace
	// connector runs: it wants the articles, not the shop's navigation tree.
	if (!empty($params['variants_only'])) {

		$where[] = "id IN (SELECT product_group_id FROM product_groups_attributes_xref)";

	}

	if (isset($params['search']) && $params['search'] !== '') {

		$search = escape(escape_like($params['search']));

		$where[] = "(name LIKE '%" . $search . "%' OR short_description LIKE '%" . $search . "%')";

	}

	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(timestamp > '" . $cursor['v'] . "'
			OR (timestamp = '" . $cursor['v'] . "' AND id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_product_group_select() . $where_sql . "
		ORDER BY timestamp ASC, id ASC
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

		$total = (int)api_value("SELECT COUNT(*) FROM product_groups"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$extras = api_product_group_extras(array_map(function ($row) { return $row['id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_product_group_present($row, $extras);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_product_groups_get($params) {

	$id = (int)$params['id'];

	$row = api_row(api_product_group_select() . " WHERE id = '" . $id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Product group'));

	}

	$out = api_product_group_present($row, api_product_group_extras(array($id), true), true, true);

	// The groups directly beneath this one, so a connector can walk the tree
	// without a second listing call per level.
	$children = api_rows("SELECT id FROM product_groups
		WHERE parent_id = '" . $id . "'" . (api_catalog_bin_columns() ? " AND recycled = '0'" : '') . "
		ORDER BY sort_order ASC, id ASC");

	$out['child_ids'] = array();

	foreach ($children as $child) {

		$out['child_ids'][] = (int)$child['id'];

	}

	api_ok($out);

}

function api_product_groups_update($params) {

	api_seo_library();

	$id = (int)$params['id'];

	$existing = api_row("SELECT id, name, enabled FROM product_groups WHERE id = '" . $id . "' LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Product group'));

	}

	// What a group's own record says about itself. The tree - parent_id and
	// sort_order - is deliberately not writable: it is the shop's navigation,
	// and moving a group from outside would move pages on a live site. The
	// address is not writable either; it is produced from the name by the same
	// rule the catalogue screens use.
	$text_columns = array(
		'title'             => 'title',
		'meta_description'  => 'meta_description',
		'meta_keywords'     => 'meta_keywords',
		'keywords'          => 'keywords',
		'short_description' => 'short_description',
		'description'       => 'full_description'
	);

	$set = array();

	foreach ($text_columns as $param => $column) {

		if (array_key_exists($param, $params)) {

			$set[] = $column . " = '" . escape($params[$param]) . "'";

		}

	}

	// Publishing is not one column. It runs down the tree, so it is only sent
	// on when the value actually changes - the same check the group screen
	// makes before calling the same function.
	$publish = null;

	if (array_key_exists('enabled', $params)) {

		$wanted = $params['enabled'] ? 1 : 0;

		if ($wanted !== (int)$existing['enabled']) {

			$publish = $wanted;

		}

	}

	// Sent and unchanged is not the same as not sent. A client that writes the
	// value the group already has gets its record back rather than an error:
	// a retry of a call that succeeded should not look like a failure.
	if (empty($set) && !array_key_exists('enabled', $params)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	// Above: the group exists and the body is one this endpoint can carry out.
	// Below: a write, and - if publishing changed - a write that runs down the
	// whole tree underneath it.
	api_dry_run_stop('updated', 'product_group', array(
		'id'      => $id,
		'fields'  => count($set),
		'publish' => ($publish === null) ? null : ($publish === 1)
	));

	$app = api_current_app();

	if (empty($set) && ($publish === null)) {

		$unchanged = api_row(api_product_group_select() . " WHERE id = '" . $id . "' LIMIT 1");

		$out = api_product_group_present($unchanged, api_product_group_extras(array($id), true), true, true);

		$out['affected'] = array('groups' => 0, 'products' => 0);

		api_ok($out);

	}

	if (!empty($set)) {

		// Every field above feeds the meta half of the SEO score.
		$set[] = "seo_analysis_current = '0'";

		api_exec("UPDATE product_groups SET " . implode(', ', $set) . ",
				user = '" . (int)$app['owner']['id'] . "',
				timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "'
			LIMIT 1");

	}

	$affected = array('groups' => 0, 'products' => 0);

	if ($publish !== null) {

		$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

		require_once($directory . '/update_product_group_status.php');

		// The owner is passed in because that writer stamps the rows with
		// USER_ID, and this entry point has no session for that constant to
		// have been filled from.
		$items = update_product_group_status(array(
			'id'     => $id,
			'status' => ($publish === 1) ? 'enabled' : 'disabled',
			'user'   => (int)$app['owner']['id']));

		foreach ((array)$items as $item) {

			if ($item['type'] === 'product_group') {

				$affected['groups']++;

			} else {

				$affected['products']++;

			}

		}

	}

	// A search results page that gathers this group builds its keyword cloud
	// from the catalogue underneath it, and this group's keywords are part of
	// that. Only this group's pages are rebuilt: the parent case the group
	// screen also handles is for a group that was moved, which cannot happen
	// here.
	api_product_group_clouds($id);

	if (pg_seo_schema_ready()) {

		pg_seo_recalculate('product_group', array($id));

	}

	log_activity(lang(array(
		'string' => 'Product group ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($existing['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_webhook_enqueue('product_group.updated', array('id' => $id, 'name' => $existing['name']));

	$row = api_row(api_product_group_select() . " WHERE id = '" . $id . "' LIMIT 1");

	$out = api_product_group_present($row, api_product_group_extras(array($id), true), true, true);

	$out['affected'] = $affected;

	api_ok($out);

}

// The search results pages whose keyword cloud is built from a catalogue that
// takes in this group: the shared rebuild (pg_catalog_group_clouds()), run
// through this API's own query helpers.
function api_product_group_clouds($group_id, $clear_only = false) {

	return pg_catalog_group_clouds($group_id, $clear_only, array('api' => true));

}

// A list of product ids from the body: each one has to be a product that is
// on the shop. A missing id is a mapping mistake on the integration side, and
// a binned one is a product the shop has let go of.
function api_product_group_product_ids($raw, $field) {

	$ids = array();

	foreach ((array)$raw as $value) {

		$product_id = (int)$value;

		if (($product_id > 0) && !in_array($product_id, $ids, true)) {

			$ids[] = $product_id;

		}

	}

	if (empty($ids)) {

		return array();

	}

	$found = array();

	foreach (api_rows("SELECT id FROM products WHERE id IN (" . implode(',', $ids) . ")"
		. (api_catalog_bin_columns() ? " AND recycled = '0'" : '')) as $row) {

		$found[] = (int)$row['id'];

	}

	foreach ($ids as $product_id) {

		if (!in_array($product_id, $found, true)) {

			api_fail_validation(lang(array(
				'string' => 'There is no product with the id {var:1}.',
				'vars'   => array($product_id)
			)), $field);

		}

	}

	return $ids;

}

// The products a group holds today, by id.
function api_product_group_member_ids($group_id) {

	$ids = array();

	foreach (api_rows("SELECT product FROM products_groups_xref WHERE product_group = '" . (int)$group_id . "'") as $row) {

		$ids[] = (int)$row['product'];

	}

	return $ids;

}

// What POST /product-groups hands the catalogue's group writer
// (pg_catalog_group_create()) from the request: the wire's description is the
// group's full description. $name is trimmed, $parent_id and $product_ids are
// the ones the handler checked.
function api_product_group_create_data($params, $name, $parent_id, $product_ids) {

	return array(
		'name'              => $name,
		'enabled'           => !empty($params['enabled']),
		'parent_id'         => $parent_id,
		'short_description' => isset($params['short_description']) ? (string)$params['short_description'] : '',
		'full_description'  => isset($params['description']) ? (string)$params['description'] : '',
		'keywords'          => isset($params['keywords']) ? (string)$params['keywords'] : '',
		'title'             => isset($params['title']) ? (string)$params['title'] : '',
		'meta_description'  => isset($params['meta_description']) ? (string)$params['meta_description'] : '',
		'meta_keywords'     => isset($params['meta_keywords']) ? (string)$params['meta_keywords'] : '',
		'product_ids'       => $product_ids
	);

}

// A new group, filed under a parent that exists.
//
// Written by the catalogue's one group writer, pg_catalog_group_create()
// (includes/fn/ecommerce.php), which the Workspace shares: a browse page with
// variant selection on, an address made from the short description or the
// name by the catalogue's own rule, the rich text prepared the way the editor
// prepares it, scored for SEO and announced as product_group.created. It
// starts switched off unless enabled is sent, so a category an import is
// still filling does not appear in the shop's navigation half-empty.
//
// The name is checked before the write, as it is for a product: it is the
// group's code in the panel, and a second group of the same name is a mapping
// that will be resolved to the wrong one sooner or later.
function api_product_groups_create($params) {

	$app = api_current_app();

	$name = trim((string)$params['name']);

	if ($name === '') {

		api_fail_validation(lang(array('string' => '{var:1} is required', 'vars' => array('name'))), 'name');

	}

	$taken = api_value("SELECT id FROM product_groups WHERE name = '" . escape($name) . "' LIMIT 1");

	if ($taken) {

		api_fail(409, 'name_taken', lang(array(
			'string' => 'A product group with the name {var:1} already exists (id {var:2}).',
			'vars'   => array($name, (int)$taken)
		)), 'name');

	}

	// Under the top of the catalogue when no parent is named, which is where
	// the catalogue screen files a group too.
	$parent_id = isset($params['parent_id']) ? (int)$params['parent_id'] : 0;

	if ($parent_id <= 0) {

		$parent_id = pg_catalog_group_top_id(array('api' => true));

	} else {

		$parent = api_row("SELECT id" . (api_catalog_bin_columns() ? ", recycled" : ", 0 AS recycled") . "
			FROM product_groups WHERE id = '" . $parent_id . "' LIMIT 1");

		if (($parent === null) || ((int)$parent['recycled'] === 1)) {

			api_fail_validation(lang(array(
				'string' => 'There is no product group with the id {var:1}.',
				'vars'   => array($parent_id)
			)), 'parent_id');

		}

	}

	$product_ids = array_key_exists('product_ids', $params)
		? api_product_group_product_ids($params['product_ids'], 'product_ids')
		: array();

	api_dry_run_stop('created', 'product_group', array('name' => $name, 'parent_id' => $parent_id, 'products' => count($product_ids)));

	$id = pg_catalog_group_create(api_product_group_create_data($params, $name, $parent_id, $product_ids), $app['owner'], array('api' => true));

	if ($id <= 0) {

		api_fail_server(lang('The product group could not be created.'));

	}

	log_activity(lang(array(
		'string' => 'Product group ({var:1}) was added through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_product_group_select() . " WHERE id = '" . $id . "' LIMIT 1");

	api_ok(api_product_group_present($row, api_product_group_extras(array($id), true), true), 201);

}

// Putting products into a group and taking them out.
//
// One call does both, so a connector that re-files a category's products
// does it in one request rather than in a remove and an add with a moment
// between them where the products are in neither. Adding a product that is
// already there, or removing one that is not, is not an error: the answer is
// the group as it now is, which is what the caller wanted to reach.
function api_product_groups_products($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$group = api_row("SELECT id, name" . (api_catalog_bin_columns() ? ", recycled" : ", 0 AS recycled") . "
		FROM product_groups WHERE id = '" . $id . "' LIMIT 1");

	if (($group === null) || ((int)$group['recycled'] === 1)) {

		api_fail_not_found(lang('Product group'));

	}

	$add = array_key_exists('add', $params) ? api_product_group_product_ids($params['add'], 'add') : array();

	// A product being taken out need not be on the shop any more: removing a
	// binned product from a group is tidying, not a mistake.
	$remove = array();

	foreach (array_key_exists('remove', $params) ? (array)$params['remove'] : array() as $value) {

		if (((int)$value > 0) && !in_array((int)$value, $remove, true)) { $remove[] = (int)$value; }

	}

	if (empty($add) && empty($remove)) {

		api_fail_validation(lang('Send the product ids to add, to remove, or both.'));

	}

	if (array_intersect($add, $remove)) {

		api_fail_validation(lang('A product cannot be added to and removed from a group in the same call.'), 'remove');

	}

	$members = api_product_group_member_ids($id);

	$to_add = array_values(array_diff($add, $members));

	$to_remove = array_values(array_intersect($remove, $members));

	api_dry_run_stop('updated', 'product_group', array('id' => $id, 'added' => count($to_add), 'removed' => count($to_remove)));

	foreach ($to_add as $product_id) {

		api_exec("INSERT INTO products_groups_xref (product, product_group, sort_order)
			VALUES ('" . $product_id . "', '" . $id . "', '0')");

	}

	if (!empty($to_remove)) {

		api_exec("DELETE FROM products_groups_xref
			WHERE product_group = '" . $id . "' AND product IN (" . implode(',', $to_remove) . ")");

	}

	if (!empty($to_add) || !empty($to_remove)) {

		api_exec("UPDATE product_groups SET user = '" . (int)$app['owner']['id'] . "', timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $id . "' LIMIT 1");

		api_product_group_clouds($id);

		log_activity(lang(array(
			'string' => 'Products of product group ({var:1}) were changed through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($group['name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

		api_webhook_enqueue('product_group.updated', array('id' => $id, 'name' => $group['name']));

		foreach (array_merge($to_add, $to_remove) as $product_id) {

			if (function_exists('pg_marketplace_product_changed')) {

				pg_marketplace_product_changed($product_id);

			}

		}

	}

	$row = api_row(api_product_group_select() . " WHERE id = '" . $id . "' LIMIT 1");

	$out = api_product_group_present($row, api_product_group_extras(array($id), true), true);

	$out['added'] = count($to_add);

	$out['removed'] = count($to_remove);

	api_ok($out);

}

// Deleting a group.
//
// The Recycle Bin is the default, as it is for a product: the group is
// switched off and flagged with what it was, and an operator can put it back.
// permanent=true performs edit_product_group.php's delete - the group, its
// links to products, images, attributes and short links - which cannot be
// undone. The products themselves are never deleted with a group; they stay
// wherever else they are filed.
//
// Two refusals either way. The root is what the whole catalogue hangs from,
// and the group screen refuses it too. A group with groups inside it is
// refused rather than taken with its subtree (for a permanent delete, also a
// group whose subgroups are in the Recycle Bin): the catalogue screen bins a
// subtree because an operator is looking at it, and an application deleting a
// branch of the shop's navigation in one call is exactly the reshaping this
// resource stays out of.
function api_product_groups_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$permanent = !empty($params['permanent']);

	$group = api_row("SELECT id, name, parent_id" . (api_catalog_bin_columns() ? ", recycled" : ", 0 AS recycled") . "
		FROM product_groups WHERE id = '" . $id . "' LIMIT 1");

	if ($group === null) {

		api_fail_not_found(lang('Product group'));

	}

	if ((int)$group['parent_id'] === 0) {

		api_fail(409, 'root_group_protected', lang('This product group could not be deleted because it is the root product group.'));

	}

	if ((int)api_value("SELECT COUNT(*) FROM product_groups WHERE parent_id = '" . $id . "'"
		. (api_catalog_bin_columns() ? " AND recycled = '0'" : '')) > 0) {

		api_fail(409, 'group_not_empty', lang('This product group has groups inside it. Move or delete those first.'));

	}

	// A group in the Recycle Bin can still be restored under this one, so a
	// permanent delete counts those too: deleted outright, this group would
	// leave them pointing at a parent that no longer exists.
	if ($permanent && api_catalog_bin_columns()
		&& ((int)api_value("SELECT COUNT(*) FROM product_groups WHERE parent_id = '" . $id . "' AND recycled = '1'") > 0)) {

		api_fail(409, 'group_not_empty', lang('This product group has groups inside it in the Recycle Bin. Delete those from the Recycle Bin first.'));

	}

	if (!$permanent) {

		if (!api_catalog_bin_ready()) {

			api_fail(409, 'recycle_bin_unavailable', lang('This site has no catalogue Recycle Bin. Send permanent=true to delete the product group outright.'));

		}

		if ((int)$group['recycled'] === 1) {

			api_fail(409, 'already_recycled', lang('This product group is already in the Recycle Bin.'));

		}

		api_dry_run_stop('recycled', 'product_group', array('id' => $id, 'name' => $group['name']));

		// The statements every panel screen writes when it bins a group. The
		// group alone: one with groups inside it was refused above.
		foreach (pg_catalog_bin_statements('product_group', $id, array($id), (int)$group['parent_id'], (int)$app['owner']['id']) as $statement) {

			api_exec($statement);

		}

		log_activity(lang(array(
			'string' => 'Product group ({var:1}) was moved to the Recycle Bin through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($group['name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

		api_webhook_enqueue('product_group.deleted', array('id' => $id, 'name' => $group['name'], 'recycled' => true));

		api_ok(array('id' => $id, 'deleted' => false, 'recycled' => true));

	}

	api_dry_run_stop('deleted', 'product_group', array('id' => $id, 'name' => $group['name']));

	$pages = api_product_group_clouds($id, true);

	api_exec("DELETE FROM product_groups_images_xref WHERE product_group = '" . $id . "'");
	api_exec("DELETE FROM product_groups WHERE id = '" . $id . "'");
	api_exec("DELETE FROM products_groups_xref WHERE product_group = '" . $id . "'");
	api_exec("DELETE FROM product_groups_attributes_xref WHERE product_group_id = '" . $id . "'");
	api_exec("DELETE FROM short_links WHERE (destination_type = 'product_group') AND (product_group_id = '" . $id . "')");
	api_exec("DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id = '" . $id . "')");

	foreach ($pages as $page) {

		update_tag_cloud_keywords_for_search_results_page_product_group($page['page_id'], $page['product_group_id']);

	}

	log_activity(lang(array(
		'string' => 'Product group ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($group['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_webhook_enqueue('product_group.deleted', array('id' => $id, 'name' => $group['name'], 'recycled' => false));

	api_ok(array('id' => $id, 'deleted' => true, 'recycled' => false));

}

// What api_product_group_present() returns, declared for the OpenAPI document.
// The last five fields are added only by the single-group endpoint, which is
// why they are optional in the document rather than absent from it.
function api_product_group_schema() {

	return array(
		'id'                => 'integer',
		// A DEFAULT NULL column that every create path writes, so not nullable
		// (api_openapi_type_spec() says when a field is).
		'name'              => 'string',
		'slug'              => 'string',
		'enabled'           => 'boolean',
		'parent_id'         => 'integer',
		'sort_order'        => 'integer',
		'title'             => 'string',
		'short_description' => 'string',
		'image'             => 'string?',
		'display_type'      => 'string',
		'variant_selection' => 'boolean',
		'attributes'        => array(array(
			'attribute_id'      => 'integer',
			'name'              => 'string',
			'label'             => 'string',
			'default_option_id' => 'integer',
			'sort_order'        => 'integer',
			'options'           => array(array(
				'option_id' => 'integer',
				'label'     => 'string'
			))
		)),
		'product_ids'     => 'integer[]',
		'recycled'        => 'boolean',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer',
		'seo'             => 'Seo',
		'description'      => 'string',
		'meta_description' => 'string',
		'meta_keywords'    => 'string',
		'keywords'         => 'string',
		'child_ids'        => 'integer[]',
		'affected'         => array('groups' => 'integer', 'products' => 'integer'),
		'added'            => 'integer',
		'removed'          => 'integer'
	);

}
