<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Menus: the site's navigation and the items in it.
//
// The rights follow the menu screens. The items are a manager's work (the
// pages scope, roles 0 to 2); a menu itself - adding one, its effect and its
// classes - is a designer's, as on view_menus.php, so those writes also need
// an owner of role 0 or 1.
//
// A menu is placed in a design by its name (<menu>name</menu>, the editor's
// menu element), so the name is never changed here and a menu is never
// removed here: either would leave every design that shows it with an empty
// spot, and only the menus screen says where a menu is used.
//
// An item links to a page or to an address. A page in the Recycle Bin or kept
// off the site as a draft is not linked to, and an address may only be a web,
// mail or phone address, or one on this site.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The column's values and the API's names for them.
function api_menu_effects() {

	return array('' => 'none', 'Pop-up' => 'popup', 'Accordion' => 'accordion');

}

function api_menu_positions() {

	return array('Top' => 'top', 'Bottom' => 'bottom', 'Left' => 'left', 'Right' => 'right');

}

function api_menu_select() {

	return "SELECT menus.id, menus.name, menus.effect, menus.first_level_popup_position,
			menus.second_level_popup_position, menus.class, menus.active_item_class,
			menus.last_modified_timestamp,
			(SELECT COUNT(*) FROM menu_items WHERE menu_items.menu_id = menus.id) AS items_count
		FROM menus";

}

function api_menu_present($row, $items = null) {

	$effects = api_menu_effects();

	$positions = api_menu_positions();

	$menu = array(
		'id'                          => (int)$row['id'],
		'name'                        => (string)$row['name'],
		'effect'                      => isset($effects[$row['effect']]) ? $effects[$row['effect']] : 'none',
		'first_level_popup_position'  => isset($positions[$row['first_level_popup_position']]) ? $positions[$row['first_level_popup_position']] : 'bottom',
		'second_level_popup_position' => isset($positions[$row['second_level_popup_position']]) ? $positions[$row['second_level_popup_position']] : 'right',
		'class'                       => (string)$row['class'],
		'active_item_class'           => (string)$row['active_item_class'],
		'items_count'                 => (int)$row['items_count'],
		'updated_at'                  => api_time($row['last_modified_timestamp']),
		'updated_at_unix'             => (int)$row['last_modified_timestamp']
	);

	if ($items !== null) {

		$menu['items'] = $items;

	}

	return $menu;

}

function api_menu_schema() {

	return array(
		'id'                          => 'integer',
		'name'                        => 'string',
		'effect'                      => 'string',
		'first_level_popup_position'  => 'string',
		'second_level_popup_position' => 'string',
		'class'                       => 'string',
		'active_item_class'           => 'string',
		'items_count'                 => 'integer',
		'updated_at'                  => 'string?',
		'updated_at_unix'             => 'integer',
		'items'                       => 'MenuItem[]'
	);

}

function api_menu_item_present($row) {

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$page_name = isset($row['page_name']) ? (string)$row['page_name'] : '';

	$url = null;

	if (((int)$row['link_page_id'] > 0) && ($page_name !== '')) {

		$url = $base . encode_url_path($page_name);

	} elseif ((string)$row['link_url'] !== '') {

		$url = (string)$row['link_url'];

	}

	return array(
		'id'              => (int)$row['id'],
		'menu_id'         => (int)$row['menu_id'],
		'parent_id'       => ((int)$row['parent_id'] > 0) ? (int)$row['parent_id'] : null,
		'level'           => isset($row['level']) ? (int)$row['level'] : 0,
		'order'           => (int)$row['sort_order'],
		'name'            => (string)$row['name'],
		'page_id'         => ((int)$row['link_page_id'] > 0) ? (int)$row['link_page_id'] : null,
		'page_name'       => ($page_name !== '') ? $page_name : null,
		'link_url'        => (string)$row['link_url'],
		'url'             => $url,
		'new_window'      => ((string)$row['link_target'] === 'New Window'),
		'security'        => ((int)$row['security'] === 1),
		'class'           => (string)$row['class'],
		'updated_at'      => api_time($row['last_modified_timestamp']),
		'updated_at_unix' => (int)$row['last_modified_timestamp']
	);

}

function api_menu_item_schema() {

	return array(
		'id'              => 'integer',
		'menu_id'         => 'integer',
		'parent_id'       => 'integer?',
		'level'           => 'integer',
		'order'           => 'integer',
		'name'            => 'string',
		'page_id'         => 'integer?',
		'page_name'       => 'string?',
		'link_url'        => 'string',
		'url'             => 'string?',
		'new_window'      => 'boolean',
		'security'        => 'boolean',
		'class'           => 'string',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

// Every item of a menu in the order the site draws them: each item followed by
// the items under it, siblings by their order. An item whose parent is gone is
// not drawn on the site and is not listed.
function api_menu_items_ordered($menu_id) {

	$rows = api_rows("SELECT menu_items.id, menu_items.menu_id, menu_items.parent_id, menu_items.name,
			menu_items.sort_order, menu_items.link_page_id, menu_items.link_url, menu_items.link_target,
			menu_items.security, menu_items.class, menu_items.last_modified_timestamp,
			page.page_name
		FROM menu_items
		LEFT JOIN page ON page.page_id = menu_items.link_page_id
		WHERE menu_items.menu_id = '" . (int)$menu_id . "'
		ORDER BY menu_items.sort_order ASC, menu_items.id ASC");

	$children = array();

	foreach ($rows as $row) {

		$children[(int)$row['parent_id']][] = $row;

	}

	$out = array();

	$seen = array();

	// Depth first, without recursion: a stack of (row, level), last pushed is
	// drawn first, so siblings go on in reverse.
	$stack = array();

	foreach (array_reverse(isset($children[0]) ? $children[0] : array()) as $row) {

		$stack[] = array($row, 0);

	}

	while (!empty($stack)) {

		list($row, $level) = array_pop($stack);

		$id = (int)$row['id'];

		if (isset($seen[$id])) {

			continue;

		}

		$seen[$id] = true;

		$row['level'] = $level;

		$out[] = $row;

		foreach (array_reverse(isset($children[$id]) ? $children[$id] : array()) as $child) {

			$stack[] = array($child, $level + 1);

		}

	}

	return $out;

}

function api_menu_load_or_fail($id) {

	$row = api_row(api_menu_select() . " WHERE menus.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Menu'));

	}

	return $row;

}

// The menu and every item, as GET /menus/{id} answers.
function api_menu_answer($id, $status = 200) {

	$items = array();

	foreach (api_menu_items_ordered($id) as $row) {

		$items[] = api_menu_item_present($row);

	}

	api_ok(api_menu_present(api_menu_load_or_fail($id), $items), $status);

}

// One item as it now stands, with its place in the tree.
function api_menu_item_answer($menu_id, $item_id, $status = 200) {

	foreach (api_menu_items_ordered($menu_id) as $row) {

		if ((int)$row['id'] === (int)$item_id) {

			api_ok(api_menu_item_present($row), $status);

		}

	}

	$row = api_row("SELECT menu_items.*, page.page_name
		FROM menu_items
		LEFT JOIN page ON page.page_id = menu_items.link_page_id
		WHERE menu_items.id = '" . (int)$item_id . "' LIMIT 1");

	api_ok(api_menu_item_present($row), $status);

}

function api_menus_list($params) {

	$where = array();

	if (isset($params['q']) && $params['q'] !== '') {

		$where[] = "menus.name LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "menus.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_menu_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY menus.id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_menu_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_menus_get($params) {

	api_menu_answer((int)$params['id']);

}

// Adding a menu and changing how it is drawn are the menus screen's, which
// opens to designers only.
function api_menu_designer_or_fail() {

	$app = api_current_app();

	if ((int)$app['owner']['role'] > 1) {

		api_fail(403, 'forbidden', lang('Only designers can add menus or change how a menu is drawn.'));

	}

}

// The menu settings a write sends, as columns.
function api_menu_settings_in($params) {

	$set = array();

	if (isset($params['effect'])) {

		$set['effect'] = array_search($params['effect'], api_menu_effects(), true);

	}

	foreach (array('first_level_popup_position', 'second_level_popup_position') as $key) {

		if (isset($params[$key])) {

			$set[$key] = array_search($params[$key], api_menu_positions(), true);

		}

	}

	foreach (array('class', 'active_item_class') as $key) {

		if (isset($params[$key])) {

			$set[$key] = trim((string)$params[$key]);

		}

	}

	return $set;

}

function api_menus_create($params) {

	api_menu_designer_or_fail();

	$app = api_current_app();

	// The menus screen's rule: the theme designer cannot style a name with
	// anything else in it.
	$name = trim((string)$params['name']);

	if (($name === '') || preg_match('/[^A-Za-z0-9-]/', $name)) {

		api_fail_validation(lang('Please only enter letters, numbers, or dashes for the name.'), 'name');

	}

	if ((int)api_value("SELECT COUNT(*) FROM menus WHERE name = '" . escape($name) . "'") > 0) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	$set = array_merge(array(
		'effect'                      => '',
		'first_level_popup_position'  => 'Bottom',
		'second_level_popup_position' => 'Right',
		'class'                       => '',
		'active_item_class'           => ''
	), api_menu_settings_in($params));

	api_dry_run_stop('created', 'menu', array('name' => $name));

	$owner_id = (int)$app['owner']['id'];

	api_exec("INSERT INTO menus (name, effect, first_level_popup_position, second_level_popup_position,
			class, active_item_class, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
		VALUES ('" . escape($name) . "', '" . escape($set['effect']) . "', '" . escape($set['first_level_popup_position']) . "',
			'" . escape($set['second_level_popup_position']) . "', '" . escape($set['class']) . "', '" . escape($set['active_item_class']) . "',
			'" . $owner_id . "', UNIX_TIMESTAMP(), '" . $owner_id . "', UNIX_TIMESTAMP())");

	$id = api_insert_id();

	log_activity(lang(array(
		'string' => 'Menu ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_menu_answer($id, 201);

}

function api_menus_update($params) {

	api_menu_designer_or_fail();

	$app = api_current_app();

	$id = (int)$params['id'];

	$menu = api_menu_load_or_fail($id);

	$set = api_menu_settings_in($params);

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'menu', array('id' => $id, 'name' => $menu['name'], 'changes' => array_keys($set)));

	$sql = array();

	foreach ($set as $column => $value) {

		$sql[] = $column . " = '" . escape($value) . "'";

	}

	api_exec("UPDATE menus SET " . implode(', ', $sql) . ",
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Menu ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($menu['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_menu_answer($id);

}

function api_menu_item_load_or_fail($menu_id, $item_id) {

	$row = api_row("SELECT * FROM menu_items WHERE id = '" . (int)$item_id . "' AND menu_id = '" . (int)$menu_id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Menu item'));

	}

	return $row;

}

// A page an item may link to: one that is on the site.
function api_menu_link_page_or_fail($page_id) {

	$row = api_row("SELECT page_id, page_folder FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1");

	if ($row === null) {

		api_fail_validation(lang('There is no page with that id.'), 'page_id');

	}

	if (in_array((int)$row['page_folder'], array_map('intval', pg_recycle_bin_folder_ids()), true)) {

		api_fail_validation(lang('The page is in the Recycle Bin.'), 'page_id');

	}

	if (pg_page_draft_ready() && pg_page_is_draft($row['page_folder'])) {

		api_fail_validation(lang('The page is a draft and is not on the site yet.'), 'page_id');

	}

	return (int)$row['page_id'];

}

// An address an item may link to: http, https, mailto or tel, or one on this
// site (a path, or an anchor). Anything a browser would run is refused.
function api_menu_link_url_or_fail($url) {

	$url = trim((string)$url);

	if ($url === '') {

		return '';

	}

	if (preg_match('/[\x00-\x20\x7f]/', $url)) {

		api_fail_validation(lang('The address cannot contain spaces or control characters.'), 'url');

	}

	if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $found) && !in_array(strtolower($found[1]), array('http', 'https', 'mailto', 'tel'), true)) {

		api_fail_validation(lang('The address must be a web, e-mail or phone address, or a path on this site.'), 'url');

	}

	if (strpos($url, '//') === 0 || strpos($url, '\\') !== false) {

		api_fail_validation(lang('The address must be a web, e-mail or phone address, or a path on this site.'), 'url');

	}

	return $url;

}

// Where an item goes among its siblings: after the sibling named, first for 0,
// last when nothing is said.
function api_menu_item_after_or_fail($menu_id, $parent_id, $item_id, $after) {

	if (($after === null) || ((int)$after === 0)) {

		return $after === null ? null : 0;

	}

	$after = (int)$after;

	if ($after === (int)$item_id) {

		api_fail_validation(lang('An item cannot be placed after itself.'), 'after');

	}

	$sibling = api_value("SELECT id FROM menu_items
		WHERE id = '" . $after . "' AND menu_id = '" . (int)$menu_id . "' AND parent_id = '" . (int)$parent_id . "' LIMIT 1");

	if (!$sibling) {

		api_fail_validation(lang('after must be an item with the same parent.'), 'after');

	}

	return $after;

}

// Writes the order of one level of the menu: 1, 2, 3... with the item put
// where $after says (null leaves it where its order already puts it).
function api_menu_item_reorder($menu_id, $parent_id, $item_id = 0, $after = null) {

	$ids = array();

	foreach (api_rows("SELECT id FROM menu_items
		WHERE menu_id = '" . (int)$menu_id . "' AND parent_id = '" . (int)$parent_id . "'
		ORDER BY sort_order ASC, id ASC") as $row) {

		$ids[] = (int)$row['id'];

	}

	$item_id = (int)$item_id;

	if (($item_id > 0) && ($after !== null)) {

		$ids = array_values(array_diff($ids, array($item_id)));

		$at = ($after === 0) ? 0 : (array_search((int)$after, $ids, true) + 1);

		array_splice($ids, $at, 0, array($item_id));

	}

	foreach ($ids as $i => $id) {

		api_exec("UPDATE menu_items SET sort_order = '" . ($i + 1) . "' WHERE id = '" . $id . "'");

	}

}

function api_menu_touch($menu_id) {

	$app = api_current_app();

	api_exec("UPDATE menus SET last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . (int)$menu_id . "'");

}

// The link a write sends, as the two columns. A page clears the address and
// an address clears the page, as on the menu item screen.
function api_menu_item_link_in($params, $current = null) {

	$has_page = isset($params['page_id']) && ((int)$params['page_id'] > 0);

	$has_url = isset($params['url']) && (trim((string)$params['url']) !== '');

	if ($has_page && $has_url) {

		api_fail_validation(lang('Give either page_id or url, not both.'), 'url');

	}

	$link = array();

	if ($has_page) {

		$link['link_page_id'] = api_menu_link_page_or_fail($params['page_id']);
		$link['link_url'] = '';

	} elseif ($has_url) {

		$link['link_url'] = api_menu_link_url_or_fail($params['url']);
		$link['link_page_id'] = 0;

	} else {

		if (isset($params['page_id'])) {

			$link['link_page_id'] = 0;

		}

		if (isset($params['url'])) {

			$link['link_url'] = '';

		}

	}

	return $link;

}

function api_menu_items_create($params) {

	$app = api_current_app();

	$menu_id = (int)$params['id'];

	$menu = api_menu_load_or_fail($menu_id);

	$name = trim((string)$params['name']);

	if ($name === '') {

		api_fail_validation(lang('Name is required.'), 'name');

	}

	$parent_id = isset($params['parent_id']) ? (int)$params['parent_id'] : 0;

	if ($parent_id > 0) {

		api_menu_item_load_or_fail($menu_id, $parent_id);

	}

	$link = array_merge(array('link_page_id' => 0, 'link_url' => ''), api_menu_item_link_in($params));

	$after = api_menu_item_after_or_fail($menu_id, $parent_id, 0, isset($params['after']) ? (int)$params['after'] : null);

	api_dry_run_stop('created', 'menu_item', array('menu_id' => $menu_id, 'name' => $name, 'parent_id' => ($parent_id > 0) ? $parent_id : null));

	$owner_id = (int)$app['owner']['id'];

	$last = (int)api_value("SELECT MAX(sort_order) FROM menu_items WHERE menu_id = '" . $menu_id . "' AND parent_id = '" . $parent_id . "'");

	api_exec("INSERT INTO menu_items (menu_id, parent_id, name, sort_order, link_page_id, link_url, link_target,
			security, class, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
		VALUES ('" . $menu_id . "', '" . $parent_id . "', '" . escape($name) . "', '" . ($last + 1) . "',
			'" . (int)$link['link_page_id'] . "', '" . escape($link['link_url']) . "',
			'" . (!empty($params['new_window']) ? 'New Window' : 'Same Window') . "',
			'" . (!empty($params['security']) ? 1 : 0) . "', '" . escape(isset($params['class']) ? trim((string)$params['class']) : '') . "',
			'" . $owner_id . "', UNIX_TIMESTAMP(), '" . $owner_id . "', UNIX_TIMESTAMP())");

	$item_id = api_insert_id();

	api_menu_item_reorder($menu_id, $parent_id, $item_id, $after);

	api_menu_touch($menu_id);

	log_activity(lang(array(
		'string' => 'Menu item ({var:1}) on menu ({var:2}) was created through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($name, $menu['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_menu_item_answer($menu_id, $item_id, 201);

}

function api_menu_items_update($params) {

	$app = api_current_app();

	$menu_id = (int)$params['id'];

	$menu = api_menu_load_or_fail($menu_id);

	$item_id = (int)$params['item_id'];

	$item = api_menu_item_load_or_fail($menu_id, $item_id);

	$set = array();

	if (isset($params['name'])) {

		$name = trim((string)$params['name']);

		if ($name === '') {

			api_fail_validation(lang('Name is required.'), 'name');

		}

		$set['name'] = $name;

	}

	$old_parent = (int)$item['parent_id'];

	$parent_id = $old_parent;

	if (isset($params['parent_id'])) {

		$parent_id = (int)$params['parent_id'];

		if ($parent_id > 0) {

			api_menu_item_load_or_fail($menu_id, $parent_id);

			// Not under itself: walk up from the new parent.
			$walk = $parent_id;

			$guard = 0;

			while (($walk > 0) && ($guard++ < 1000)) {

				if ($walk === $item_id) {

					api_fail_validation(lang('An item cannot be moved under itself or an item under it.'), 'parent_id');

				}

				$walk = (int)api_value("SELECT parent_id FROM menu_items WHERE id = '" . $walk . "' LIMIT 1");

			}

		}

		$set['parent_id'] = $parent_id;

	}

	$set = array_merge($set, api_menu_item_link_in($params));

	if (isset($params['new_window'])) {

		$set['link_target'] = $params['new_window'] ? 'New Window' : 'Same Window';

	}

	if (isset($params['security'])) {

		$set['security'] = $params['security'] ? 1 : 0;

	}

	if (isset($params['class'])) {

		$set['class'] = trim((string)$params['class']);

	}

	$moved = ($parent_id !== $old_parent);

	$after = api_menu_item_after_or_fail($menu_id, $parent_id, $item_id, isset($params['after']) ? (int)$params['after'] : null);

	// A new parent without a place puts the item last there.
	if ($moved && ($after === null)) {

		$last = (int)api_value("SELECT id FROM menu_items
			WHERE menu_id = '" . $menu_id . "' AND parent_id = '" . $parent_id . "' AND id <> '" . $item_id . "'
			ORDER BY sort_order DESC, id DESC LIMIT 1");

		$after = $last;

	}

	if (empty($set) && ($after === null)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'menu_item', array('id' => $item_id, 'menu_id' => $menu_id, 'name' => $item['name'], 'changes' => array_merge(array_keys($set), ($after !== null) ? array('order') : array())));

	if (!empty($set)) {

		$sql = array();

		foreach ($set as $column => $value) {

			$sql[] = $column . " = '" . escape($value) . "'";

		}

		api_exec("UPDATE menu_items SET " . implode(', ', $sql) . ",
				last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . $item_id . "'");

	}

	api_menu_item_reorder($menu_id, $parent_id, $item_id, $after);

	if ($moved) {

		api_menu_item_reorder($menu_id, $old_parent);

	}

	api_menu_touch($menu_id);

	log_activity(lang(array(
		'string' => 'Menu item ({var:1}) on menu ({var:2}) was changed through the API by the application {var:3} (key {var:4}).',
		'vars'   => array(isset($set['name']) ? $set['name'] : $item['name'], $menu['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_menu_item_answer($menu_id, $item_id);

}

// The item and every item under it, as on the menu item screen.
function api_menu_items_delete($params) {

	$app = api_current_app();

	$menu_id = (int)$params['id'];

	$menu = api_menu_load_or_fail($menu_id);

	$item_id = (int)$params['item_id'];

	$item = api_menu_item_load_or_fail($menu_id, $item_id);

	$ids = array($item_id);

	$queue = array($item_id);

	while (!empty($queue) && (count($ids) < 10000)) {

		$parent = array_shift($queue);

		foreach (api_rows("SELECT id FROM menu_items WHERE menu_id = '" . $menu_id . "' AND parent_id = '" . (int)$parent . "'") as $row) {

			if (!in_array((int)$row['id'], $ids, true)) {

				$ids[] = (int)$row['id'];

				$queue[] = (int)$row['id'];

			}

		}

	}

	api_dry_run_stop('deleted', 'menu_item', array('id' => $item_id, 'menu_id' => $menu_id, 'name' => $item['name'], 'removed' => count($ids)));

	api_exec("DELETE FROM menu_items WHERE id IN (" . implode(',', $ids) . ")");

	api_menu_item_reorder($menu_id, (int)$item['parent_id']);

	api_menu_touch($menu_id);

	log_activity(lang(array(
		'string' => 'Menu item ({var:1}) on menu ({var:2}) was deleted through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($item['name'], $menu['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $item_id, 'deleted' => true, 'removed' => count($ids)));

}
