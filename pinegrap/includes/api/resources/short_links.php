<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Short links: a short address on the site that leads to a page, a product
// group, a product, a file or another address, as the short links screen and
// the file manager make them.
//
// A link opens for good under a name, until a time under a name, or once under
// a generated address. The generated address is a token of which only the hash
// is kept, so it is handed back once, in the answer that created it, and never
// again.
//
// The name shares one namespace with pages, files and folders: a name already
// answered by any of them is refused. A guest's way into the workspace is a
// short link too, made and ended there, and it is not reached from here.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The short link helpers live with the file manager, which shows short links
// beside files.
function api_short_links_helpers() {

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/view_folder_and_files_f.php');

}

function api_short_link_types() {

	return array('page', 'product_group', 'product', 'url', 'file');

}

// The whole row is read, so the columns later upgrades added come along where
// they exist and the query is the same before them.
function api_short_link_select() {

	return "SELECT short_links.*,
			page.page_name,
			product_groups.address_name AS product_group_address_name,
			products.address_name AS product_address_name,
			files.name AS file_name
		FROM short_links
		LEFT JOIN page ON short_links.page_id = page.page_id
		LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
		LEFT JOIN products ON short_links.product_id = products.id
		LEFT JOIN files ON short_links.file_id = files.id";

}

function api_short_link_present($row) {

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$name = (string)$row['name'];

	$type = (string)$row['destination_type'];

	$mode = isset($row['link_mode']) ? (string)$row['link_mode'] : 'permanent';

	$state = pg_short_link_state($row);

	$on_page = in_array($type, array('page', 'product_group', 'product'), true);

	$stamp = function ($key) use ($row) {

		return (isset($row[$key]) && ((int)$row[$key] > 0)) ? api_time($row[$key]) : null;

	};

	// An address without a scheme is on this site, completed the way the
	// router completes it.
	if ($type === 'url') {

		$url = (string)$row['url'];

		if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url)) {

			$target = $url;

		} else {

			$target = URL_SCHEME . HOSTNAME_SETTING . ((substr($url, 0, 1) === '/') ? '' : PATH) . $url;

		}

	} elseif (($type === 'file') && ((string)$row['file_name'] === '')) {

		$target = null;

	} else {

		$target = $base . pg_short_link_destination($row);

	}

	return array(
		'id'               => (int)$row['id'],
		'name'             => ($name !== '') ? $name : null,
		// The address a visitor opens. A one-time link's is a token that was
		// shown once and is not kept; token_hint is how its first characters
		// read.
		'address'          => ($name !== '') ? $base . encode_url_path($name) : null,
		'token_hint'       => (($name === '') && isset($row['token_hint']) && ((string)$row['token_hint'] !== '')) ? (string)$row['token_hint'] : null,
		'mode'             => $mode,
		'status'           => $state['status'],
		'expires_at'       => $stamp('expires_at'),
		'used_at'          => $stamp('used_at'),
		'use_count'        => isset($row['use_count']) ? (int)$row['use_count'] : 0,
		'last_used_at'     => $stamp('last_used_at'),
		'destination_type' => $type,
		'page_id'          => $on_page ? (int)$row['page_id'] : null,
		'page_name'        => $on_page && ($row['page_name'] !== null) ? (string)$row['page_name'] : null,
		'product_group_id' => ($type === 'product_group') ? (int)$row['product_group_id'] : null,
		'product_id'       => ($type === 'product') ? (int)$row['product_id'] : null,
		'url'              => ($type === 'url') ? (string)$row['url'] : null,
		'file_id'          => ($type === 'file') ? (int)$row['file_id'] : null,
		'file_name'        => ($type === 'file') && ((string)$row['file_name'] !== '') ? (string)$row['file_name'] : null,
		'target'           => $target,
		'tracking_code'    => (string)$row['tracking_code'],
		'recycled'         => !empty($row['recycled']),
		'created_at'       => api_time($row['created_timestamp']),
		'updated_at'       => api_time($row['last_modified_timestamp']),
		'updated_at_unix'  => (int)$row['last_modified_timestamp']
	);

}

function api_short_link_schema() {

	return array(
		'id'               => 'integer',
		'name'             => 'string?',
		'address'          => 'string?',
		'token_hint'       => 'string?',
		'mode'             => 'string',
		'status'           => 'string',
		'expires_at'       => 'string?',
		'used_at'          => 'string?',
		'use_count'        => 'integer',
		'last_used_at'     => 'string?',
		'destination_type' => 'string',
		'page_id'          => 'integer?',
		'page_name'        => 'string?',
		'product_group_id' => 'integer?',
		'product_id'       => 'integer?',
		'url'              => 'string?',
		'file_id'          => 'integer?',
		'file_name'        => 'string?',
		'target'           => 'string?',
		'tracking_code'    => 'string',
		'recycled'         => 'boolean',
		'created_at'       => 'string?',
		'updated_at'       => 'string?',
		'updated_at_unix'  => 'integer',
		// Only in the answer that created a one-time link.
		'token_url'        => 'string?'
	);

}

function api_short_link_load_or_fail($id) {

	$row = api_row(api_short_link_select() . "
		WHERE short_links.id = '" . (int)$id . "' AND short_links.destination_type <> 'workspace_guest'
		LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Short link'));

	}

	return $row;

}

// A link in the Recycle Bin is restored in the file manager before it is
// changed.
function api_short_link_live_or_fail($id) {

	$row = api_short_link_load_or_fail($id);

	if (!empty($row['recycled'])) {

		api_fail(409, 'already_recycled', lang('This short link is in the recycle bin. Restore it in the file manager before changing it.'));

	}

	return $row;

}

// Newest first.
function api_short_links_list($params) {

	api_short_links_helpers();

	$where = array("short_links.destination_type <> 'workspace_guest'");

	$live = pg_short_link_live_filter(!empty($params['recycled']));

	if ($live !== '') {

		$where[] = $live;

	} elseif (!empty($params['recycled'])) {

		// No bin before the upgrade that added it, so nothing is in it.
		$where[] = '0';

	}

	if (isset($params['destination_type'])) {

		$where[] = "short_links.destination_type = '" . escape($params['destination_type']) . "'";

	}

	if (isset($params['page_id'])) {

		$where[] = "short_links.page_id = '" . (int)$params['page_id'] . "'";

	}

	if (isset($params['mode'])) {

		if (pg_short_link_modes_ready()) {

			$where[] = "short_links.link_mode = '" . escape($params['mode']) . "'";

		} elseif ($params['mode'] !== 'permanent') {

			$where[] = '0';

		}

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$q = escape(escape_like($params['q']));

		$where[] = "(short_links.name LIKE '%" . $q . "%' OR short_links.url LIKE '%" . $q . "%' OR short_links.tracking_code LIKE '%" . $q . "%')";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "short_links.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_short_link_select() . "
		WHERE " . implode(' AND ', $where) . "
		ORDER BY short_links.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_short_link_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_short_links_get($params) {

	api_short_links_helpers();

	api_ok(api_short_link_present(api_short_link_load_or_fail($params['id'])));

}

// A name a visitor types: the screen's character rule, and free across pages,
// files, folders and other short links.
function api_short_link_name_or_fail($raw, $ignore_id = 0) {

	$name = pg_short_link_clean_name($raw);

	if ($name === '') {

		api_fail_validation(lang('The name may only contain letters, numbers, periods, underscores, hyphens, forward slashes and square brackets.'), 'name');

	}

	$check = array('name' => $name);

	if ($ignore_id > 0) {

		$check['ignore_item_id'] = $ignore_id;
		$check['ignore_item_type'] = 'short_link';

	}

	if (check_name_availability($check) == false) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	return $name;

}

// An address the link sends visitors on to: a web address or a path on this
// site. The visitor's browser is sent there by a redirect, so nothing else
// is taken.
function api_short_link_url_or_fail($url) {

	$url = trim((string)$url);

	if (preg_match('/[\x00-\x20\x7f]/', $url)) {

		api_fail_validation(lang('Sorry, you may not enter a space in the URL.'), 'url');

	}

	if ((preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $found) && !in_array(strtolower($found[1]), array('http', 'https'), true))
		|| (strpos($url, '//') === 0) || (strpos($url, '\\') !== false)) {

		api_fail_validation(lang('The address must be a web address or a path on this site.'), 'url');

	}

	return $url;

}

// Where the link leads, read and checked the way the file manager reads it.
// $row is the stored link on a change: what is not sent is kept from it.
function api_short_link_destination_in($params, $row = null) {

	$fields = array('destination_type', 'page_id', 'product_group_id', 'product_id', 'url', 'file_id');

	$current = array(
		'destination_type' => ($row !== null) ? (string)$row['destination_type'] : '',
		'page_id'          => ($row !== null) ? (int)$row['page_id'] : 0,
		'product_group_id' => ($row !== null) ? (int)$row['product_group_id'] : 0,
		'product_id'       => ($row !== null) ? (int)$row['product_id'] : 0,
		'url'              => ($row !== null) ? (string)$row['url'] : '',
		'file_id'          => ($row !== null) ? (int)$row['file_id'] : 0
	);

	foreach ($fields as $field) {

		if (isset($params[$field])) {

			$current[$field] = $params[$field];

		}

	}

	if ($current['destination_type'] === 'url') {

		$current['url'] = api_short_link_url_or_fail($current['url']);

	}

	// The file manager names the page by what it is to the destination: a
	// catalog page or a catalog detail page. Either is stored as page_id.
	$request = $current;

	if ($current['destination_type'] === 'product_group') {

		$request['catalog_page_id'] = $current['page_id'];

	}

	if ($current['destination_type'] === 'product') {

		$request['catalog_detail_page_id'] = $current['page_id'];

	}

	// A change may keep the product or group the link already points at while
	// it is in the Recycle Bin; nothing else in the bin is taken.
	$read = pg_short_link_read_request($request, null, $row);

	if (isset($read['error'])) {

		$field = in_array($read['error'], array('catalog_page_id', 'catalog_detail_page_id'), true) ? 'page_id' : $read['error'];

		api_fail_validation($read['message'], $field);

	}

	// Every destination column is written, the ones this type does not use
	// emptied, so a link that changed type does not keep an id it no longer
	// means.
	return array_merge(
		array('destination_type' => $read['type'], 'page_id' => (int)$read['page_id'], 'product_group_id' => 0, 'product_id' => 0, 'url' => '', 'file_id' => 0),
		$read['columns']
	);

}

function api_short_link_duration_or_fail($seconds, $field) {

	$durations = pg_short_link_durations();

	if (!isset($durations[(int)$seconds])) {

		api_fail_validation(lang(array(
			'string' => 'Choose how long the link is valid, in seconds: {var:1}.',
			'vars'   => array(implode(', ', array_keys($durations)))
		)), $field);

	}

	return (int)$seconds;

}

function api_short_links_create($params) {

	api_short_links_helpers();

	$app = api_current_app();

	$mode = isset($params['mode']) ? $params['mode'] : 'permanent';

	if (($mode !== 'permanent') && !pg_short_link_modes_ready()) {

		api_fail(409, 'link_modes_unavailable', lang('One-time and timed links come with an upgrade this site has not run yet.'), 'mode');

	}

	$expires = 0;

	if ($mode === 'timed') {

		if (!isset($params['duration'])) {

			api_short_link_duration_or_fail(0, 'duration');

		}

		$expires = time() + api_short_link_duration_or_fail($params['duration'], 'duration');

	}

	$name = '';

	if ($mode === 'once') {

		if (isset($params['name']) && $params['name'] !== '') {

			api_fail_validation(lang('A one-time link gets a generated address, so it takes no name.'), 'name');

		}

	} else {

		if (!isset($params['name']) || trim((string)$params['name']) === '') {

			api_fail_validation(lang('Name is required.'), 'name');

		}

		$name = api_short_link_name_or_fail($params['name']);

	}

	$set = api_short_link_destination_in($params);

	$set['name'] = $name;

	$set['tracking_code'] = isset($params['tracking_code']) ? (string)$params['tracking_code'] : '';

	$token = '';

	if (pg_short_link_modes_ready()) {

		$set['link_mode'] = $mode;
		$set['expires_at'] = $expires;

	}

	api_dry_run_stop('created', 'short_link', array(
		'name'             => ($name !== '') ? $name : null,
		'mode'             => $mode,
		'destination_type' => $set['destination_type'],
		'expires_at'       => ($expires > 0) ? api_time($expires) : null
	));

	if ($mode === 'once') {

		$token = pg_short_link_new_token();

		$set['token_hash'] = hash('sha256', $token);
		$set['token_hint'] = substr($token, 0, 6);

	}

	$owner_id = (int)$app['owner']['id'];

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape((string)$value) . "'";

	}

	api_exec("INSERT INTO short_links SET " . implode(', ', $columns) . ",
		created_user_id = '" . $owner_id . "', created_timestamp = UNIX_TIMESTAMP(),
		last_modified_user_id = '" . $owner_id . "', last_modified_timestamp = UNIX_TIMESTAMP()");

	$id = api_insert_id();

	if ($id <= 0) {

		api_fail_server();

	}

	log_activity(lang(array(
		'string' => 'Short link ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(($token !== '') ? substr($token, 0, 6) . '…' : $name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$out = api_short_link_present(api_short_link_load_or_fail($id));

	// The whole address of a one-time link, this once: only its hash is kept.
	if ($token !== '') {

		$out['token_url'] = URL_SCHEME . HOSTNAME_SETTING . PATH . $token;

	}

	api_ok($out, 201);

}

function api_short_links_update($params) {

	api_short_links_helpers();

	$app = api_current_app();

	$row = api_short_link_live_or_fail($params['id']);

	$id = (int)$row['id'];

	$set = array();

	if (isset($params['name'])) {

		if ((string)$row['name'] === '') {

			api_fail_validation(lang('A link with a generated address has no name to change.'), 'name');

		}

		$name = api_short_link_name_or_fail($params['name'], $id);

		if ($name !== (string)$row['name']) {

			$set['name'] = $name;

		}

	}

	foreach (array('destination_type', 'page_id', 'product_group_id', 'product_id', 'url', 'file_id') as $field) {

		if (isset($params[$field])) {

			$set = array_merge($set, api_short_link_destination_in($params, $row));

			break;

		}

	}

	if (isset($params['tracking_code'])) {

		$set['tracking_code'] = (string)$params['tracking_code'];

	}

	// A timed link given a new time, counted from now.
	if (isset($params['renew'])) {

		if (!isset($row['link_mode']) || ((string)$row['link_mode'] !== 'timed')) {

			api_fail_validation(lang('Only a timed link can be given a new time.'), 'renew');

		}

		$set['expires_at'] = time() + api_short_link_duration_or_fail($params['renew'], 'renew');

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'short_link', array(
		'id'      => $id,
		'changes' => array_keys($set)
	));

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape((string)$value) . "'";

	}

	api_exec("UPDATE short_links SET " . implode(', ', $columns) . ",
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	// The log says what the link is called now: the name is the address, and
	// the old one no longer answers.
	$label = isset($set['name']) ? $set['name'] : (((string)$row['name'] !== '') ? (string)$row['name'] : (string)$row['token_hint'] . '…');

	log_activity(lang(array(
		'string' => 'Short link ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($label, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(api_short_link_present(api_short_link_load_or_fail($id)));

}

// To the Recycle Bin, as the file manager does, from where an operator can put
// it back; permanent=true deletes it outright.
function api_short_links_delete($params) {

	api_short_links_helpers();

	$app = api_current_app();

	$row = api_short_link_load_or_fail($params['id']);

	$id = (int)$row['id'];

	$permanent = !empty($params['permanent']);

	$label = ((string)$row['name'] !== '') ? (string)$row['name'] : (isset($row['token_hint']) ? (string)$row['token_hint'] : '') . '…';

	if (!$permanent) {

		if (!pg_short_link_recycle_ready()) {

			api_fail(409, 'recycle_bin_unavailable', lang('This site has no recycle bin for short links. Send permanent=true to delete the short link outright.'));

		}

		if (!empty($row['recycled'])) {

			api_fail(409, 'already_recycled', lang('This short link is already in the recycle bin.'));

		}

		api_dry_run_stop('recycled', 'short_link', array('id' => $id, 'name' => $label));

		api_exec("UPDATE short_links SET recycled = '1' WHERE id = '" . $id . "'");

		// Replaced rather than added to, so a link that reaches here twice
		// does not leave two restore records behind.
		api_exec("DELETE FROM recycle_bin WHERE (item_type = 'short_link') AND (item_id = '" . $id . "')");

		api_exec("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
			VALUES ('short_link', '" . $id . "', '0', UNIX_TIMESTAMP(), '" . (int)$app['owner']['id'] . "')");

		log_activity(lang(array(
			'string' => 'Short link ({var:1}) was moved to the recycle bin through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($label, $app['name'], $app['api_key'])
		)), $app['owner']['username']);

		api_ok(array('id' => $id, 'deleted' => true, 'recycled' => true));

	}

	api_dry_run_stop('deleted', 'short_link', array('id' => $id, 'name' => $label));

	api_exec("DELETE FROM short_links WHERE id = '" . $id . "'");

	if (pg_recycle_ready()) {

		api_exec("DELETE FROM recycle_bin WHERE (item_type = 'short_link') AND (item_id = '" . $id . "')");

	}

	log_activity(lang(array(
		'string' => 'Short link ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($label, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true, 'recycled' => false));

}
