<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Files.
//
// An upload is the one thing an outside system can leave on this server, so
// this endpoint is deliberately the narrowest one in the API:
//
//   - it writes into one folder, the one the operator chose, and the request
//     cannot name another;
//   - it accepts images and PDFs and nothing else, and it looks inside the file
//     rather than trusting the name it was given;
//   - it lists only what the site already serves to anyone, so a private folder
//     is not a directory an application can read;
//   - deleting moves the file to the recycle bin. The file stays on disk and
//     its address keeps working, because a page or a product refers to a file
//     by name and an application removing the wrong one should not take part
//     of the site down with it.
//
// The name rules, the collision suffix and the blocked extension list are the
// panel's own (includes/fn/files.php), reused rather than restated.

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/http.php');
require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// What may be uploaded. SVG is not here on purpose: it is markup, it can carry
// script, and the site serves it inline.
function api_files_allowed_types() {

	return array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf');

}

// The image dimension columns arrive with an upgrade, so they are named only
// when they are there.
function api_files_dimensions_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = (api_row("SHOW COLUMNS FROM files LIKE 'image\\_width'") !== null);

	}

	return $ready;

}

// The folder an application's uploads are filed in. Zero means the operator has
// not chosen one, and the shared helper falls back to the top folder - the same
// fallback the chat and product upload settings use.
function api_files_upload_folder() {

	return (int)pg_default_upload_folder('api_upload_folder_id');

}

function api_file_select() {

	$dimensions = api_files_dimensions_ready()
		? "files.image_width, files.image_height,"
		: "NULL AS image_width, NULL AS image_height,";

	return "SELECT files.id, files.name, files.folder, files.type, files.size,
			files.description, files.design, files.user, files.timestamp,
			" . $dimensions . "
			folder.folder_access_control_type
		FROM files
		LEFT JOIN folder ON folder.folder_id = files.folder";

}

function api_file_present($row) {

	return array(
		'id'          => (int)$row['id'],
		'name'        => $row['name'],
		// Files are served from the site root by name; there is no directory in
		// the address because there is none on disk either.
		'url'         => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($row['name']),
		'folder_id'   => (int)$row['folder'],
		'type'        => $row['type'],
		'size'        => (int)$row['size'],
		'description' => $row['description'],
		'width'       => ($row['image_width'] === null) ? null : (int)$row['image_width'],
		'height'      => ($row['image_height'] === null) ? null : (int)$row['image_height'],
		// A design file belongs to the theme rather than to the content, and
		// only a designer or an administrator may remove one.
		'design'      => ((int)$row['design'] === 1),
		'uploaded_at'      => api_time($row['timestamp']),
		'uploaded_at_unix' => (int)$row['timestamp']
	);

}

// Only what the site hands to any visitor.
//
// A folder can be guest, private, registration or membership, and the panel
// decides those per user through the access control list. An application is not
// a user and has no list of its own, so the honest line is the public one: it
// reads what the site is already publishing.
function api_files_public_clause() {

	return "(folder.folder_access_control_type = 'public')";

}

function api_files_list($params) {

	$where = array(api_files_public_clause());

	if (isset($params['updated_since'])) {

		$where[] = "files.timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['folder_id'])) {

		$where[] = "files.folder = '" . (int)$params['folder_id'] . "'";

	}

	if (isset($params['type']) && $params['type'] !== '') {

		$where[] = "files.type = '" . escape(ltrim(strtolower($params['type']), '.')) . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$term = escape($params['q']);

		$where[] = "(files.name LIKE '%" . $term . "%' OR files.description LIKE '%" . $term . "%')";

	}

	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(files.timestamp > '" . $cursor['v'] . "'
			OR (files.timestamp = '" . $cursor['v'] . "' AND files.id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_file_select() . " WHERE " . implode(' AND ', $paged_where) . "
		ORDER BY files.timestamp ASC, files.id ASC
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

		$total = (int)api_value("SELECT COUNT(*) FROM files
			LEFT JOIN folder ON folder.folder_id = files.folder
			WHERE " . implode(' AND ', $where));

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_file_present($row);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_files_get($params) {

	$row = api_row(api_file_select() . " WHERE files.id = '" . (int)$params['id'] . "'
		AND " . api_files_public_clause() . "
		LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('File'));

	}

	api_ok(api_file_present($row));

}

// The largest upload this endpoint accepts, in bytes.
//
// PHP's own ceilings decide it: a body larger than post_max_size never reaches
// this code at all, and base64 costs a third on top of the bytes it carries.
function api_files_size_limit() {

	$limits = function_exists('pg_upload_limits') ? pg_upload_limits() : array();

	$candidates = array();

	foreach (array('file_max', 'json_max') as $key) {

		if (!empty($limits[$key])) {

			$candidates[] = (int)$limits[$key];

		}

	}

	// A ceiling of its own as well, because these two can be enormous on a host
	// that raised them for something else, and an API that accepts a 500 MB
	// body has a memory problem rather than a feature.
	$candidates[] = 33554432;

	return min($candidates);

}

// Whether the bytes are what the extension says they are.
//
// The panel's checks are about the name: the blocked list, the neutralised
// double extension, the collision suffix. None of them look inside the file,
// which is fine for an operator uploading from their own desktop and not fine
// for an address on the internet.
function api_files_content_matches($extension, $path) {

	$images = array(
		'jpg'  => array(IMAGETYPE_JPEG),
		'jpeg' => array(IMAGETYPE_JPEG),
		'png'  => array(IMAGETYPE_PNG),
		'gif'  => array(IMAGETYPE_GIF)
	);

	if (defined('IMAGETYPE_WEBP')) {

		$images['webp'] = array(IMAGETYPE_WEBP);

	}

	if (isset($images[$extension])) {

		$info = @getimagesize($path);

		return (is_array($info) && isset($info[2]) && in_array($info[2], $images[$extension], true));

	}

	if ($extension === 'webp') {

		// A build without IMAGETYPE_WEBP cannot be asked what this is, so the
		// container is read directly: RIFF....WEBP.
		$handle = @fopen($path, 'rb');

		if (!$handle) {

			return false;

		}

		$head = (string)fread($handle, 12);

		fclose($handle);

		return (substr($head, 0, 4) === 'RIFF') && (substr($head, 8, 4) === 'WEBP');

	}

	if ($extension === 'pdf') {

		$handle = @fopen($path, 'rb');

		if (!$handle) {

			return false;

		}

		$head = (string)fread($handle, 5);

		fclose($handle);

		return ($head === '%PDF-');

	}

	return false;

}

function api_files_create($params) {

	$app = api_current_app();

	$has_content = (isset($params['content_base64']) && $params['content_base64'] !== '');

	$has_url = (isset($params['source_url']) && $params['source_url'] !== '');

	if ($has_content === $has_url) {

		api_fail_validation(lang('Send either content_base64 or source_url, not both and not neither.'));

	}

	$limit = api_files_size_limit();

	$bytes = '';

	if ($has_content) {

		$bytes = base64_decode((string)$params['content_base64'], true);

		if ($bytes === false) {

			api_fail_validation(lang('content_base64 is not valid base64.'), 'content_base64');

		}

	} else {

		// Fetched through the outbound client, which resolves the name once,
		// pins the address it checked and refuses anything on this network.
		$outcome = api_http_request('GET', (string)$params['source_url'], array(
			'timeout'  => 20,
			'max_body' => $limit + 1,
			'agent'    => 'file-upload'
		));

		if (!$outcome['ok']) {

			api_fail_validation(lang(array(
				'string' => 'The address could not be read: {var:1}',
				'vars'   => ($outcome['error'] !== '') ? $outcome['error'] : ('HTTP ' . $outcome['status'])
			)), 'source_url');

		}

		$bytes = $outcome['body'];

	}

	if (strlen($bytes) === 0) {

		api_fail_validation(lang('The file is empty.'));

	}

	if (strlen($bytes) > $limit) {

		api_fail(413, 'file_too_large', lang(array(
			'string' => 'The file is larger than the {var:1} bytes this site accepts.',
			'vars'   => $limit
		)));

	}

	// The name decides the extension, and the extension decides what the file
	// is allowed to be. A name that was not sent is taken from the address.
	$name = isset($params['name']) ? trim((string)$params['name']) : '';

	if (($name === '') && $has_url) {

		$path_part = (string)@parse_url((string)$params['source_url'], PHP_URL_PATH);

		$name = trim(basename($path_part));

	}

	if ($name === '') {

		api_fail_validation(lang('name is required.'), 'name');

	}

	$extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));

	if (!in_array($extension, api_files_allowed_types(), true)) {

		api_fail_validation(lang(array(
			'string' => 'Only these file types are accepted: {var:1}',
			'vars'   => implode(', ', api_files_allowed_types())
		)), 'name');

	}

	// The panel's own refusal list, which is also what stops a dotfile and a
	// double extension.
	if (function_exists('pg_upload_name_blocked') && pg_upload_name_blocked($name)) {

		api_fail_validation(function_exists('pg_upload_blocked_message')
			? pg_upload_blocked_message()
			: lang('This file name is not accepted.'), 'name');

	}

	$temporary = tempnam(sys_get_temp_dir(), 'pgapi');

	if (($temporary === false) || (@file_put_contents($temporary, $bytes) === false)) {

		api_fail_server(lang('The upload could not be written.'));

	}

	if (!api_files_content_matches($extension, $temporary)) {

		@unlink($temporary);

		api_fail_validation(lang('The contents of the file do not match the type its name claims.'), 'name');

	}

	// Same name rules as an upload through the panel: the reserved names, the
	// neutralised extension, the ASCII fold, and a [1] suffix when the name is
	// already taken by a file or a page.
	$file_name = function_exists('prepare_file_name') ? prepare_file_name($name) : $name;

	$file_name = function_exists('get_unique_name')
		? get_unique_name(array('name' => $file_name, 'type' => 'file'))
		: $file_name;

	$destination = FILE_DIRECTORY_PATH . '/' . $file_name;

	if (!@copy($temporary, $destination)) {

		@unlink($temporary);

		api_fail_server(lang('The upload could not be written.'));

	}

	@unlink($temporary);

	$width = null;

	$height = null;

	$optimized = 0;

	// The product profile is the one the shop screens use: scaled to the
	// operator's dimension and re-encoded at their quality. Anything else is
	// stored byte for byte, the same as an upload through the file manager.
	if (isset($params['image_profile']) && ($params['image_profile'] === 'product')
		&& function_exists('pg_process_image_file') && function_exists('pg_image_settings')) {

		$settings = pg_image_settings();

		if (!empty($settings['product_optimize'])) {

			$report = pg_process_image_file($destination, array(
				'max_dimension' => $settings['product_max_dimension'],
				'min_dimension' => $settings['product_min_dimension'],
				'quality'       => $settings['resize_quality']
			));

			if (isset($report['status']) && ($report['status'] === 'success')) {

				$width  = isset($report['width']) ? (int)$report['width'] : null;

				$height = isset($report['height']) ? (int)$report['height'] : null;

				$optimized = 1;

			}

		}

	}

	// A PDF has none, which is why this is allowed to find nothing.
	if ($width === null) {

		$size = @getimagesize($destination);

		if (is_array($size) && !empty($size[0])) {

			$width  = (int)$size[0];

			$height = (int)$size[1];

		}

	}

	@clearstatcache(true, $destination);

	$folder = api_files_upload_folder();

	$columns = "name, folder, description, type, size, user, timestamp";

	$values = "'" . escape($file_name) . "',
		'" . $folder . "',
		'" . escape(isset($params['description']) ? $params['description'] : '') . "',
		'" . escape($extension) . "',
		'" . (int)filesize($destination) . "',
		'" . (int)$app['owner']['id'] . "',
		UNIX_TIMESTAMP()";

	if (api_files_dimensions_ready() && ($width !== null)) {

		$columns .= ", optimized, image_width, image_height, optimization_percent";

		$values .= ", '" . $optimized . "', '" . $width . "', '" . $height . "', '0'";

	}

	api_exec("INSERT INTO files (" . $columns . ") VALUES (" . $values . ")");

	$id = api_insert_id();

	log_activity(lang(array(
		'string' => 'File ({var:1}) was uploaded through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($file_name, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_webhook_enqueue('file.created', array('id' => $id, 'name' => $file_name));

	$row = api_row(api_file_select() . " WHERE files.id = '" . $id . "' LIMIT 1");

	api_ok(api_file_present($row), 201);

}

function api_files_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$file = api_row("SELECT id, name, folder, design FROM files WHERE id = '" . $id . "' LIMIT 1");

	if ($file === null) {

		api_fail_not_found(lang('File'));

	}

	// A design file is part of the theme. The file manager only lets a designer
	// or an administrator remove one, and an application never does more than
	// the account that owns it.
	if (((int)$file['design'] === 1) && ((int)$app['owner']['role'] > 1)) {

		api_fail(403, 'forbidden', lang('A design file can only be removed by a designer or an administrator.'));

	}

	if (!pg_folder_edit_access($file['folder'], $app['owner']['id'], $app['owner']['role'])) {

		api_fail(403, 'forbidden', lang('The account that owns this application cannot edit this folder.'));

	}

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/view_folder_and_files_f.php');

	// There is no outright delete here on purpose: the file stays on disk, its
	// address keeps answering, and an operator can put it back. A page that
	// refers to it by name is not broken by an application's mistake.
	if (!pg_recycle_ready()) {

		api_fail(409, 'recycle_bin_unavailable', lang('This site has no recycle bin, so a file cannot be removed through the API.'));

	}

	$bin_id = (int)pg_recycle_folder_id(true);

	if ($bin_id <= 0) {

		api_fail(409, 'recycle_bin_unavailable', lang('This site has no recycle bin, so a file cannot be removed through the API.'));

	}

	if (pg_recycle_is_inside($file['folder'])) {

		api_fail(409, 'already_recycled', lang('This file is already in the recycle bin.'));

	}

	$original_folder = (int)$file['folder'];

	api_exec("UPDATE files SET folder = '" . $bin_id . "' WHERE id = '" . $id . "' LIMIT 1");

	// The name goes out of circulation with the row, so the same file can be
	// uploaded again without coming back as "photo[1].jpg" while the old one
	// waits in the bin.
	pg_recycle_park_name('file', $id, array('id' => (int)$app['owner']['id']));

	api_exec("DELETE FROM recycle_bin WHERE (item_type = 'file') AND (item_id = '" . $id . "')");

	api_exec("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
		VALUES ('file', '" . $id . "', '" . $original_folder . "', UNIX_TIMESTAMP(), '" . (int)$app['owner']['id'] . "')");

	log_activity(lang(array(
		'string' => 'File ({var:1}) was moved to the recycle bin through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($file['name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array(
		'id'        => $id,
		'recycled'  => true,
		'folder_id' => $bin_id
	));

}
