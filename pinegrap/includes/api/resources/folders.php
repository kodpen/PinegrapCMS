<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Folders: the tree pages and files are filed in, and the access rule each
// part of the site is served under.
//
// A folder is what decides who may open a page - public, guest, registration,
// membership or private, written on the folder or inherited from the one
// above it. That makes the one change an application may never make on its
// own a widening one: a folder that turns public, or a folder moved out from
// under a private parent, serves everything in it to anyone the moment the
// row is written. Writes here therefore only ever keep a folder's access as
// it is or narrow it; opening a part of the site up is done on the folder
// screen, by a person who can see what is in it.
//
// The recycle bin and the drafts folder are folders too, but they belong to
// the screens that fill them: neither is listed as a place to file things,
// neither may be renamed, moved or removed from here, and nothing is created
// or moved into them through this endpoint. A folder inside the bin is
// answered by id with recycled true and left out of the listing.
//
// Removal is for an empty folder only, as on the folder screen: what is in a
// folder goes first, through its own endpoint, so nothing is ever deleted as
// a side effect of tidying the tree.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Access types from the most open to the most closed. The order is what
// "widening" means below: a change may move a folder to the right, never to
// the left.
function api_folder_access_ranks() {

	return array(
		'public'       => 0,
		'guest'        => 1,
		'registration' => 2,
		'membership'   => 3,
		'private'      => 4
	);

}

// The whole tree in one query, keyed by id: parent, own access type. The
// effective access of a folder, the walk to the root and the cycle check all
// read this rather than querying once per step.
function api_folder_tree($refresh = false) {

	static $tree = null;

	if (($tree === null) || $refresh) {

		$tree = array();

		foreach (api_rows("SELECT folder_id, folder_parent, folder_access_control_type FROM folder") as $row) {

			$tree[(int)$row['folder_id']] = array(
				'parent' => (int)$row['folder_parent'],
				'access' => (string)$row['folder_access_control_type']
			);

		}

	}

	return $tree;

}

// The access a folder is served under: its own type, or the first one found
// on the way up. The root counts as public, as get_access_control_type()
// has it. $tree is api_folder_tree()'s shape; $overrides lets a write ask
// what the answer would be after it (folder_id => array('parent', 'access')).
// Pure, so the rule can be tested without a database.
function api_folder_access_walk($tree, $folder_id, $overrides = array()) {

	$current = (int)$folder_id;

	$visited = array();

	while ($current > 0 && !isset($visited[$current])) {

		$visited[$current] = true;

		$node = isset($overrides[$current]) ? $overrides[$current] : (isset($tree[$current]) ? $tree[$current] : null);

		if ($node === null) {

			break;

		}

		if ($node['access'] !== '') {

			return $node['access'];

		}

		$current = (int)$node['parent'];

	}

	return 'public';

}

function api_folder_effective_access($folder_id, $overrides = array()) {

	return api_folder_access_walk(api_folder_tree(), $folder_id, $overrides);

}

// Whether a change from one access type to another serves the folder to more
// people. A type this list does not know counts as the most closed before
// the change and the most open after it, so it is never taken for safe.
function api_folder_access_widens($before, $after) {

	$ranks = api_folder_access_ranks();

	$before = isset($ranks[$before]) ? $ranks[$before] : max($ranks);

	$after = isset($ranks[$after]) ? $ranks[$after] : 0;

	return $after < $before;

}

// The aclfolder rows of the user role (3), keyed by folder and user. Roles
// above it read every private folder whatever their rows say, so their rows
// never decide whether a move opens a folder to more people.
function api_folder_acl() {

	$acl = array();

	foreach (api_rows("SELECT aclfolder.aclfolder_folder, aclfolder.aclfolder_user, aclfolder.aclfolder_rights, aclfolder.expiration_date
		FROM aclfolder
		INNER JOIN user ON user.user_id = aclfolder.aclfolder_user
		WHERE user.user_role >= 3") as $row) {

		$acl[(int)$row['aclfolder_folder']][(int)$row['aclfolder_user']] = array(
			'rights'  => (int)$row['aclfolder_rights'],
			'expires' => (string)$row['expiration_date']
		);

	}

	return $acl;

}

// Who may read a private folder, as check_private_access() decides it: for
// each user the nearest row on the way up decides - edit rights or an
// unexpired view row let them in, an expired view row keeps them out. The
// answer is user id => last day of access ('9999-12-31' for no end), users
// without access left out. $tree, $overrides as in api_folder_access_walk();
// $acl is api_folder_acl()'s shape; $today is 'Y-m-d'. Pure.
function api_folder_readers_walk($tree, $acl, $folder_id, $today, $overrides = array()) {

	$decided = array();

	$current = (int)$folder_id;

	$visited = array();

	while ($current > 0 && !isset($visited[$current])) {

		$visited[$current] = true;

		if (isset($acl[$current])) {

			foreach ($acl[$current] as $user_id => $row) {

				if (isset($decided[$user_id])) {

					continue;

				}

				if ($row['rights'] === 2) {

					$decided[$user_id] = '9999-12-31';

				} elseif ($row['rights'] === 1) {

					$expires = $row['expires'];

					if (($expires === '') || ($expires === '0000-00-00')) {

						$decided[$user_id] = '9999-12-31';

					} else {

						$decided[$user_id] = ($expires < $today) ? false : $expires;

					}

				}

			}

		}

		$node = isset($overrides[$current]) ? $overrides[$current] : (isset($tree[$current]) ? $tree[$current] : null);

		if ($node === null) {

			break;

		}

		$current = (int)$node['parent'];

	}

	$readers = array();

	foreach ($decided as $user_id => $until) {

		if ($until !== false) {

			$readers[$user_id] = $until;

		}

	}

	ksort($readers);

	return $readers;

}

// Whether a private folder's readers after a change include someone who
// could not read it before, or someone for longer than before. Pure.
function api_folder_readers_widen($before, $after) {

	foreach ($after as $user_id => $until) {

		if (!isset($before[$user_id]) || ($until > $before[$user_id])) {

			return true;

		}

	}

	return false;

}

// Whether $candidate is $folder_id itself or anywhere below it. Pure, like
// the walk above.
function api_folder_within($tree, $candidate, $folder_id) {

	$current = (int)$candidate;

	$visited = array();

	while ($current > 0 && !isset($visited[$current])) {

		if ($current === (int)$folder_id) {

			return true;

		}

		$visited[$current] = true;

		$current = isset($tree[$current]) ? (int)$tree[$current]['parent'] : 0;

	}

	return false;

}

// The folders that belong to other screens: the bin with everything in it,
// and the drafts folder.
function api_folder_bin_ids() {

	return function_exists('pg_recycle_bin_folder_ids') ? array_map('intval', pg_recycle_bin_folder_ids()) : array();

}

function api_folder_drafts_id() {

	return function_exists('pg_page_draft_folder_id') ? (int)pg_page_draft_folder_id(false) : 0;

}

function api_folder_root_id() {

	return (int)api_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");

}

function api_folder_select() {

	return "SELECT folder.folder_id, folder.folder_name, folder.folder_parent, folder.folder_level,
			folder.folder_order, folder.folder_access_control_type, folder.folder_archived,
			folder.folder_timestamp
		FROM folder";

}

// How many folders, pages and files sit directly in each of the given
// folders: three grouped queries for a whole page of the listing.
function api_folder_counts($ids) {

	$counts = array();

	$ids = array_values(array_unique(array_map('intval', $ids)));

	foreach ($ids as $id) {

		$counts[$id] = array('folders' => 0, 'pages' => 0, 'files' => 0);

	}

	if (empty($ids)) {

		return $counts;

	}

	$in = implode(',', $ids);

	$queries = array(
		'folders' => "SELECT folder_parent AS id, COUNT(*) AS n FROM folder WHERE folder_parent IN (" . $in . ") GROUP BY folder_parent",
		'pages'   => "SELECT page_folder AS id, COUNT(*) AS n FROM page WHERE page_folder IN (" . $in . ") GROUP BY page_folder",
		'files'   => "SELECT folder AS id, COUNT(*) AS n FROM files WHERE folder IN (" . $in . ") GROUP BY folder"
	);

	foreach ($queries as $kind => $sql) {

		foreach (api_rows($sql) as $row) {

			$counts[(int)$row['id']][$kind] = (int)$row['n'];

		}

	}

	return $counts;

}

function api_folder_present($row, $counts) {

	$id = (int)$row['folder_id'];

	$system = null;

	if ($id === api_folder_drafts_id()) {

		$system = 'drafts';

	} elseif (function_exists('pg_recycle_bin_folder_id') && ($id === (int)pg_recycle_bin_folder_id())) {

		$system = 'recycle_bin';

	}

	$own = (string)$row['folder_access_control_type'];

	$count = isset($counts[$id]) ? $counts[$id] : array('folders' => 0, 'pages' => 0, 'files' => 0);

	return array(
		'id'               => $id,
		'name'             => (string)$row['folder_name'],
		'parent_id'        => ((int)$row['folder_parent'] > 0) ? (int)$row['folder_parent'] : null,
		'level'            => (int)$row['folder_level'],
		'order'            => (int)$row['folder_order'],
		'access'           => api_folder_effective_access($id),
		'access_inherited' => ($own === ''),
		'archived'         => ((int)$row['folder_archived'] === 1),
		'system'           => $system,
		'recycled'         => in_array($id, api_folder_bin_ids(), true),
		'folders'          => (int)$count['folders'],
		'pages'            => (int)$count['pages'],
		'files'            => (int)$count['files'],
		'updated_at'       => api_time($row['folder_timestamp']),
		'updated_at_unix'  => (int)$row['folder_timestamp']
	);

}

// What api_folder_present() returns, declared for the OpenAPI document.
function api_folder_schema() {

	return array(
		'id'               => 'integer',
		'name'             => 'string',
		'parent_id'        => 'integer?',
		'level'            => 'integer',
		'order'            => 'integer',
		'access'           => 'string',
		'access_inherited' => 'boolean',
		'archived'         => 'boolean',
		'system'           => 'string?',
		'recycled'         => 'boolean',
		'folders'          => 'integer',
		'pages'            => 'integer',
		'files'            => 'integer',
		'updated_at'       => 'string?',
		'updated_at_unix'  => 'integer'
	);

}

function api_folder_load_or_fail($id) {

	$row = api_row(api_folder_select() . " WHERE folder.folder_id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Folder'));

	}

	return $row;

}

function api_folder_answer($id, $status_code = 200) {

	api_folder_tree(true);

	$row = api_folder_load_or_fail($id);

	api_ok(api_folder_present($row, api_folder_counts(array((int)$row['folder_id']))), $status_code);

}

function api_folders_list($params) {

	$where = array();

	if (isset($params['parent_id'])) {

		$where[] = "folder.folder_parent = '" . (int)$params['parent_id'] . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$where[] = "folder.folder_name LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	if (isset($params['archived'])) {

		$where[] = "folder.folder_archived = '" . ($params['archived'] ? '1' : '0') . "'";

	}

	// Whatever is in the recycle bin is deleted as far as the site goes.
	$bin = api_folder_bin_ids();

	if (!empty($bin)) {

		$where[] = "folder.folder_id NOT IN (" . implode(',', $bin) . ")";

	}

	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "folder.folder_id > '" . (int)$cursor['i'] . "'";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_folder_select()
		. (empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where)) . "
		ORDER BY folder.folder_id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode(0, $last['folder_id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM folder"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['folder_id'];

	}

	$counts = api_folder_counts($ids);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_folder_present($row, $counts);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_folders_get($params) {

	$row = api_folder_load_or_fail($params['id']);

	api_ok(api_folder_present($row, api_folder_counts(array((int)$row['folder_id']))));

}

// A folder something may be filed in from here: it exists, it is not the
// bin or inside it, it is not the drafts folder, and the owner may edit it.
function api_folder_destination_or_fail($folder_id, $field) {

	$app = api_current_app();

	$folder_id = (int)$folder_id;

	$tree = api_folder_tree();

	if (!isset($tree[$folder_id])) {

		api_fail_validation(lang('There is no folder with that id.'), $field);

	}

	if (in_array($folder_id, api_folder_bin_ids(), true)) {

		api_fail_validation(lang('A folder in the recycle bin cannot be used here.'), $field);

	}

	if (($folder_id === api_folder_drafts_id()) && ($folder_id > 0)) {

		api_fail_validation(lang('The drafts folder is filled by the Visual Page Editor and cannot be used here.'), $field);

	}

	if (!pg_folder_edit_access($folder_id, $app['owner']['id'], $app['owner']['role'])) {

		api_fail(403, 'forbidden', lang('The account that owns this application cannot edit this folder.'));

	}

	return $folder_id;

}

// The folder's access type as the column stores it: '' inherits.
function api_folder_access_in($value) {

	return ($value === 'inherit') ? '' : (string)$value;

}

function api_folders_create($params) {

	$app = api_current_app();

	$name = trim((string)$params['name']);

	if ($name === '') {

		api_fail_validation(lang('The folder must have a name.'), 'name');

	}

	$parent_id = isset($params['parent_id']) ? (int)$params['parent_id'] : api_folder_root_id();

	api_folder_destination_or_fail($parent_id, 'parent_id');

	$access = isset($params['access']) ? api_folder_access_in($params['access']) : '';

	$level = (int)api_value("SELECT folder_level FROM folder WHERE folder_id = '" . $parent_id . "'") + 1;

	api_dry_run_stop('created', 'folder', array(
		'name'      => $name,
		'parent_id' => $parent_id,
		'access'    => ($access !== '') ? $access : api_folder_effective_access($parent_id)
	));

	// Styles are left to inherit (0), which is what a person who may not pick
	// one gets on the folder screen: a design belongs to the pages, and the
	// folder default only matters to the older page styles.
	api_exec("INSERT INTO folder (
			folder_name,
			folder_parent,
			folder_level,
			folder_order,
			folder_access_control_type,
			folder_archived,
			folder_style,
			mobile_style_id,
			folder_timestamp,
			folder_user)
		VALUES (
			'" . escape($name) . "',
			'" . $parent_id . "',
			'" . $level . "',
			'" . (isset($params['order']) ? (int)$params['order'] : 0) . "',
			'" . escape($access) . "',
			'" . (!empty($params['archived']) ? 1 : 0) . "',
			'0',
			'0',
			UNIX_TIMESTAMP(),
			'" . (int)$app['owner']['id'] . "')");

	$id = (int)api_insert_id();

	log_activity(lang(array(
		'string' => 'Folder ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_folder_answer($id, 201);

}

// Levels below a moved folder, put right in one pass over the tree rather
// than a query per folder per level.
function api_folder_relevel($folder_id, $level) {

	$children = array();

	foreach (api_folder_tree(true) as $id => $node) {

		$children[(int)$node['parent']][] = (int)$id;

	}

	$stack = array(array((int)$folder_id, (int)$level));

	$visited = array();

	while (!empty($stack)) {

		list($id, $at) = array_pop($stack);

		if (isset($visited[$id])) {

			continue;

		}

		$visited[$id] = true;

		if (empty($children[$id])) {

			continue;

		}

		$ids = array_map('intval', $children[$id]);

		api_exec("UPDATE folder SET folder_level = '" . ($at + 1) . "' WHERE folder_id IN (" . implode(',', $ids) . ")");

		foreach ($ids as $child) {

			$stack[] = array($child, $at + 1);

		}

	}

}

function api_folders_update($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_folder_load_or_fail($id);

	$parent = (int)$row['folder_parent'];

	$system = ($id === api_folder_drafts_id()) || in_array($id, api_folder_bin_ids(), true);

	if ($system) {

		api_fail(409, 'system_folder_protected', lang('The recycle bin and the drafts folder are kept by their own screens and are not changed through the API.'));

	}

	if (!pg_folder_edit_access($id, $app['owner']['id'], $app['owner']['role'])) {

		api_fail(403, 'forbidden', lang('The account that owns this application cannot edit this folder.'));

	}

	$set = array();

	if (array_key_exists('name', $params)) {

		$name = trim((string)$params['name']);

		if ($name === '') {

			api_fail_validation(lang('The folder must have a name.'), 'name');

		}

		$set[] = "folder_name = '" . escape($name) . "'";

	}

	if (array_key_exists('order', $params)) {

		$set[] = "folder_order = '" . (int)$params['order'] . "'";

	}

	if (array_key_exists('archived', $params)) {

		$set[] = "folder_archived = '" . ($params['archived'] ? 1 : 0) . "'";

	}

	$new_parent = $parent;

	if (array_key_exists('parent_id', $params) && ((int)$params['parent_id'] !== $parent)) {

		if ($parent === 0) {

			api_fail_validation(lang('The top folder cannot be moved.'), 'parent_id');

		}

		$new_parent = api_folder_destination_or_fail($params['parent_id'], 'parent_id');

		// A folder moved into itself or below itself would leave the branch
		// hanging from nothing; the folder screen refuses the same.
		if (api_folder_within(api_folder_tree(), $new_parent, $id)) {

			api_fail_validation(lang('A folder cannot be moved into itself or into a folder inside it.'), 'parent_id');

		}

	}

	$tree = api_folder_tree();

	$own = (string)$tree[$id]['access'];

	$new_own = array_key_exists('access', $params) ? api_folder_access_in($params['access']) : $own;

	if (($new_parent !== $parent) || ($new_own !== $own)) {

		// Asked of the tree as it would be after the write. Only this folder
		// needs asking: what is below it either has its own type, which the
		// change does not touch, or inherits this folder's.
		$before = api_folder_effective_access($id);

		$after = api_folder_effective_access($id, array($id => array('parent' => $new_parent, 'access' => $new_own)));

		if (api_folder_access_widens($before, $after)) {

			api_fail(409, 'access_widening_refused', lang(array(
				'string' => 'This change would serve the folder as {var:1} instead of {var:2}, which opens what is in it to more people. Change it on the folder screen.',
				'vars'   => array(get_access_control_type_name($after), get_access_control_type_name($before))
			)), array_key_exists('access', $params) ? 'access' : 'parent_id');

		}

		// A move also changes who reads the private folders in the branch:
		// their aclfolder lists are inherited from above. Each folder of the
		// branch that is private before and after must keep or narrow its
		// readers; one that turns private from another type is narrowed.
		if ($new_parent !== $parent) {

			$change = array($id => array('parent' => $new_parent, 'access' => $new_own));

			$acl = api_folder_acl();

			$today = date('Y-m-d');

			foreach (array_keys($tree) as $branch_id) {

				if (!api_folder_within($tree, $branch_id, $id)
					|| (api_folder_access_walk($tree, $branch_id) !== 'private')
					|| (api_folder_access_walk($tree, $branch_id, $change) !== 'private')) {

					continue;

				}

				if (api_folder_readers_widen(
					api_folder_readers_walk($tree, $acl, $branch_id, $today),
					api_folder_readers_walk($tree, $acl, $branch_id, $today, $change)
				)) {

					api_fail(409, 'access_widening_refused', lang('This change would let people open folders in this branch who cannot open them now. Change it on the folder screen.'), 'parent_id');

				}

			}

		}

		if ($new_own !== $own) {

			$set[] = "folder_access_control_type = '" . escape($new_own) . "'";

		}

	}

	$level = (int)$row['folder_level'];

	if ($new_parent !== $parent) {

		$level = (int)api_value("SELECT folder_level FROM folder WHERE folder_id = '" . $new_parent . "'") + 1;

		$set[] = "folder_parent = '" . $new_parent . "'";

		$set[] = "folder_level = '" . $level . "'";

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'folder', array('id' => $id, 'fields' => count($set)));

	api_exec("UPDATE folder SET " . implode(', ', $set) . ",
			folder_timestamp = UNIX_TIMESTAMP(),
			folder_user = '" . (int)$app['owner']['id'] . "'
		WHERE folder_id = '" . $id . "'
		LIMIT 1");

	if ($new_parent !== $parent) {

		api_folder_relevel($id, $level);

	}

	log_activity(lang(array(
		'string' => 'Folder ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['folder_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_folder_answer($id);

}

function api_folders_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_folder_load_or_fail($id);

	if ((int)$row['folder_parent'] === 0) {

		api_fail(409, 'system_folder_protected', lang('The top folder cannot be deleted.'));

	}

	if (($id === api_folder_drafts_id()) || in_array($id, api_folder_bin_ids(), true)) {

		api_fail(409, 'system_folder_protected', lang('The recycle bin and the drafts folder are kept by their own screens and are not changed through the API.'));

	}

	if (!pg_folder_edit_access($id, $app['owner']['id'], $app['owner']['role'])) {

		api_fail(403, 'forbidden', lang('The account that owns this application cannot edit this folder.'));

	}

	$counts = api_folder_counts(array($id));

	if (($counts[$id]['folders'] + $counts[$id]['pages'] + $counts[$id]['files']) > 0) {

		api_fail(409, 'folder_not_empty', lang('Please delete all folders, pages, and files in this folder before deleting this folder.'));

	}

	api_dry_run_stop('deleted', 'folder', array('id' => $id, 'name' => $row['folder_name']));

	// The folder screen's delete: the access list rows go with the folder.
	api_exec("DELETE FROM aclfolder WHERE aclfolder_folder = '" . $id . "'");

	api_exec("DELETE FROM folder WHERE folder_id = '" . $id . "' LIMIT 1");

	log_activity(lang(array(
		'string' => 'Folder ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['folder_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
