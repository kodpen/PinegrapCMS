<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Page design: the pages of the Visual Page Editor, as an assistant reads them,
// and the changes it proposes for them.
//
// Only the editor's pages are here. Their layout is a tree of nodes, so a
// change can name the element it is about and the page keeps everything the
// change does not touch; a page made with a custom style is HTML in a text
// field, and is answered with 422. The pages endpoints keep describing what
// every page tells search engines and still leave its text out.
//
// Nothing here rewrites a page. A proposal is checked against the page - every
// operation must find what it names and the result must be a valid page - and
// then waits until a person applies it in the editor or from the workspace
// answer it was made under, with their own rights (includes/designer_ai.php).
// That is why design:write is a designer's decision to delegate: it lets an
// application put a change in front of the people who build the site, not
// make one. The one write is a new page, and it is always a draft: it is in
// the editor with the design's other pages and on the site only once a person
// publishes it there.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The pages, the tree view and the proposals.
require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');

// A clean 503 on a site that has not taken the upgrade, instead of a failed
// query.
function api_design_ready() {

	if (!pg_design_ai_ready()) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that carries page design proposals.'));

	}

}

// A page of a visual design, or the right refusal: 404 for no page, 422 for a
// page the editor did not make.
function api_design_page_or_fail($id) {

	$page = pg_design_ai_page($id);

	if ($page) {

		return $page;

	}

	if (pg_design_ai_page_problem($id) === 'not_visual') {

		api_fail_validation(pg_design_ai_page_problem_text('not_visual'), 'id');

	}

	api_fail_not_found(lang('Page'));

}

// The request a call names, when it may: Claude's own application and a request
// about this page that is still open.
function api_design_request_for($request_id, $page) {

	if ((int)$request_id <= 0) {

		return null;

	}

	if (!function_exists('ws_api_is_claude') || !ws_api_is_claude()) {

		api_fail(403, 'forbidden', lang('Only the application Claude works through may answer a workspace request.'));

	}

	$row = pg_design_ai_request($request_id);

	if (!$row || ($row['agent'] !== 'claude')) {

		api_fail_not_found(lang('Request'));

	}

	if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {

		api_fail(409, 'closed', lang('This request is already closed.'));

	}

	if (pg_design_ai_is_design_request($row) && ((int)$row['page_id'] !== (int)$page['page_id'])) {

		api_fail_validation(lang('The request is about another page.'), 'request_id');

	}

	return $row;

}

// Off the site in the drafts folder, waiting for a person to publish it.
function api_design_page_is_draft($folder_id) {

	return pg_page_draft_ready() && pg_page_is_draft((int)$folder_id);

}

function api_design_page_present($row) {

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	return array(
		'id'                => (int)$row['page_id'],
		'name'              => (string)$row['page_name'],
		'url'               => $base . encode_url_path($row['page_name']),
		'title'             => (string)$row['page_title'],
		'folder_id'         => (int)$row['page_folder'],
		'design_id'         => (int)$row['page_style'],
		'design_name'       => (string)$row['style_name'],
		'framework'         => function_exists('pg_design_framework_key') ? pg_design_framework_key(isset($row['style_framework']) ? $row['style_framework'] : '') : 'bootstrap5',
		'pending_proposals' => (int)$row['pending_proposals'],
		'draft'             => api_design_page_is_draft($row['page_folder']),
		'updated_at'        => api_time($row['page_timestamp']),
		'updated_at_unix'   => (int)$row['page_timestamp']
	);

}

function api_design_page_schema() {

	return array(
		'id'                => 'integer',
		'name'              => 'string',
		'url'               => 'string',
		'title'             => 'string',
		'folder_id'         => 'integer',
		'design_id'         => 'integer',
		'design_name'       => 'string',
		'framework'         => 'string',
		'pending_proposals' => 'integer',
		'draft'             => 'boolean',
		'updated_at'        => 'string?',
		'updated_at_unix'   => 'integer'
	);

}

// What api_design_page_present() reads, for the list and for a page just made.
function api_design_page_select() {

	$framework = (function_exists('waf_table_has_column') && waf_table_has_column('style', 'style_framework')) ? 'style.style_framework' : "'bootstrap5' AS style_framework";

	return "SELECT page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style,
			page.page_timestamp, style.style_name, " . $framework . ",
			(SELECT COUNT(*) FROM design_proposals WHERE design_proposals.page_id = page.page_id AND design_proposals.status = 'pending') AS pending_proposals
		FROM page
		INNER JOIN style ON page.page_style = style.style_id";

}

function api_design_pages_list($params) {

	api_design_ready();

	$where = array(
		"page.layout_type = 'system'",
		"page.page_style > 0",
		"(style.style_layout = 'visual_designer' OR (page.page_tree_json IS NOT NULL AND page.page_tree_json <> ''))",
		"page.page_tree_json IS NOT NULL",
		"page.page_tree_json <> ''"
	);

	if (isset($params['design_id'])) {

		$where[] = "page.page_style = '" . (int)$params['design_id'] . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$q = escape($params['q']);

		$where[] = "(page.page_name LIKE '%" . $q . "%' OR page.page_title LIKE '%" . $q . "%')";

	}

	if (isset($params['updated_since'])) {

		$where[] = "page.page_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	$limit = (int)$params['limit'];

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "page.page_id > '" . (int)$cursor['i'] . "'";

	}

	$rows = api_rows(api_design_page_select() . "
		WHERE " . implode(' AND ', $where) . pg_designer_not_binned_sql() . "
		ORDER BY page.page_id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['page_id'], (int)$last['page_id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_page_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// The designs a page can be added to: the editor's own, and the older ones
// whose pages already carry a layout tree (pg_designer_list_designs()).
function api_design_present($row, $counts) {

	$c = isset($counts[(int)$row['style_id']]) ? $counts[(int)$row['style_id']] : array('pages' => 0, 'drafts' => 0, 'home' => 0);

	$state = pg_designer_design_state($c);

	return array(
		'id'              => (int)$row['style_id'],
		'name'            => (string)$row['style_name'],
		'framework'       => function_exists('pg_design_framework_key') ? pg_design_framework_key($row['style_framework']) : 'bootstrap5',
		'template'        => ((string)$row['style_template'] !== '') ? (string)$row['style_template'] : null,
		'pages'           => (int)$c['pages'],
		'drafts'          => (int)$c['drafts'],
		'home'            => ((int)$c['home'] > 0),
		'state'           => $state['state'],
		'updated_at'      => api_time($row['style_timestamp']),
		'updated_at_unix' => (int)$row['style_timestamp']
	);

}

function api_design_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string',
		'framework'       => 'string',
		'template'        => 'string?',
		'pages'           => 'integer',
		'drafts'          => 'integer',
		'home'            => 'boolean',
		'state'           => 'string',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

function api_design_designs_list($params) {

	api_design_ready();

	$has = function ($column) {

		return function_exists('waf_table_has_column') && waf_table_has_column('style', $column);

	};

	$where = array("(style.style_layout = 'visual_designer' OR EXISTS (SELECT 1 FROM page p2 WHERE p2.page_style = style.style_id AND p2.page_tree_json IS NOT NULL AND p2.page_tree_json <> ''))");

	if (isset($params['q']) && $params['q'] !== '') {

		$where[] = "style.style_name LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	$limit = (int)$params['limit'];

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "style.style_id > '" . (int)$cursor['i'] . "'";

	}

	$rows = api_rows("SELECT style.style_id, style.style_name, style.style_timestamp,
			" . ($has('style_framework') ? 'style.style_framework' : "'' AS style_framework") . ",
			" . ($has('style_template') ? 'style.style_template' : "'' AS style_template") . "
		FROM style
		WHERE " . implode(' AND ', $where) . "
		ORDER BY style.style_id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['style_id'], (int)$last['style_id']);

	}

	$counts = pg_designer_design_draft_counts(array_map(function ($row) { return (int)$row['style_id']; }, $rows));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_present($row, $counts);

	}

	api_ok_list($out, $limit, $next);

}

// A page's name is its address: a space or an ampersand becomes an underscore
// as on the Pages screen, a slash at either end is dropped, and what would
// end or break the address is refused.
function api_design_page_name_in($name) {

	$name = trim(str_replace(array(' ', '&'), '_', trim((string)$name)), '/');

	if ($name === '') {

		api_fail_validation(lang('Page name is required.'), 'name');

	}

	if (preg_match('/[?#\\\\<>"%\x00-\x1f\x7f]/', $name)) {

		api_fail_validation(lang('A page name cannot contain ? # % \\ < > " or control characters.'), 'name');

	}

	if ((int)api_value("SELECT COUNT(*) FROM page WHERE page_name = '" . escape($name) . "'") > 0 || pg_designer_page_name_taken_elsewhere($name)) {

		api_fail(409, 'name_taken', lang(array('string' => 'The page name "{var:1}" is already in use.', 'vars' => $name)), 'name');

	}

	if (pg_tr_is_reserved_name($name)) {

		api_fail_validation(lang(array('string' => 'The page name "{var:1}" is reserved for a site language.', 'vars' => $name)), 'name');

	}

	return $name;

}

// A new page of a design, always as a draft: the editor's "new page" (an empty
// canvas) or its "duplicate page" (copy_from). The folder is the one the page
// is published to when a person puts it on the site.
function api_design_pages_create($params) {

	api_design_ready();

	if (!pg_page_draft_ready()) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that keeps pages as drafts.'));

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
	require_once(dirname(__FILE__) . '/folders.php');

	$app = api_current_app();

	$owner = $app['owner'];

	// The design scope is only granted to designers; this holds when the
	// owner's role has changed since.
	if (!pg_designer_is_full($owner)) {

		api_fail(403, 'forbidden', lang('Only designers can add pages to a design.'));

	}

	$design_id = (int)$params['design_id'];

	$visual = pg_visual_design_style_ids();

	$design = api_row("SELECT style_id, style_name FROM style WHERE style_id = '" . $design_id . "' LIMIT 1");

	if ($design === null) {

		api_fail_validation(lang('There is no design with that id.'), 'design_id');

	}

	if (!isset($visual[$design_id]) && (int)api_value("SELECT COUNT(*) FROM page WHERE page_style = '" . $design_id . "' AND page_tree_json IS NOT NULL AND page_tree_json <> ''") === 0) {

		api_fail_validation(lang('The design was not made with the Visual Page Editor.'), 'design_id');

	}

	$name = api_design_page_name_in($params['name']);

	$folder_id = isset($params['folder_id']) ? (int)$params['folder_id'] : api_folder_root_id();

	api_folder_destination_or_fail($folder_id, 'folder_id');

	// The layout of another page of the same design. Its form is not copied:
	// a form's fields and records belong to its page, and the editor makes
	// them when it saves the copy.
	$tree_json = '';

	$copy_from = isset($params['copy_from']) ? (int)$params['copy_from'] : 0;

	if ($copy_from > 0) {

		$source = pg_design_ai_page($copy_from);

		if (!$source) {

			api_fail_validation(lang('There is no page of a visual design with that id.'), 'copy_from');

		}

		if ((int)$source['style_id'] !== $design_id) {

			api_fail_validation(lang('The page to copy belongs to another design.'), 'copy_from');

		}

		if (function_exists('_pg_cf_page_form_widgets') && _pg_cf_page_form_widgets($copy_from)) {

			api_fail(409, 'page_has_form', lang('The page to copy has a form of its own. Duplicate it in the Visual Page Editor, which makes the copy\'s form.'), 'copy_from');

		}

		$tree_json = (string)$source['tree_json'];

	}

	$fields = array(
		'page_id'               => 0,
		'page_name'             => $name,
		'tree_json'             => $tree_json,
		'page_folder'           => $folder_id,
		'page_title'            => isset($params['title']) ? (string)$params['title'] : '',
		'page_meta_description' => isset($params['meta_description']) ? (string)$params['meta_description'] : '',
		'page_search'           => 1,
		'page_search_keywords'  => '',
		'page_sitemap'          => 1,
		'page_home'             => 0,
		'page_noindex'          => 0,
		'page_nofollow'         => 0,
		'page_draft'            => 1,
		'pg_comments'           => 0,
		'pg_comments_label'     => '',
		'pg_comments_allow_new' => 1,
		'pg_comments_rating'    => 0
	);

	$user = array('id' => (int)$owner['id'], 'role' => (int)$owner['role'], 'username' => (string)$owner['username']);

	$check = pg_designer_save_page($design_id, $fields, $user, true);

	if (!$check['ok']) {

		api_fail_validation(implode(' ', $check['errors']), ($copy_from > 0) ? 'copy_from' : null);

	}

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	api_dry_run_stop('created', 'design_page', array(
		'design_id' => $design_id,
		'name'      => $name,
		'url'       => $base . encode_url_path($name),
		'folder_id' => $folder_id,
		'draft'     => true,
		'copy_from' => ($copy_from > 0) ? $copy_from : null
	));

	// The design save writes its log lines in the name of the signed-in
	// person; here that is the owner of the application.
	$had_name = isset($_SESSION) && array_key_exists('sessionusername', $_SESSION);

	$old_name = $had_name ? $_SESSION['sessionusername'] : null;

	$_SESSION['sessionusername'] = (string)$owner['username'];

	$saved = pg_designer_save_page($design_id, $fields, $user, false);

	if ($had_name) {

		$_SESSION['sessionusername'] = $old_name;

	} else {

		unset($_SESSION['sessionusername']);

	}

	if (!$saved['ok'] || (int)$saved['page_id'] <= 0) {

		api_fail_server(implode(' ', $saved['errors']));

	}

	$page_id = (int)$saved['page_id'];

	log_activity(lang(array(
		'string' => 'Page ({var:1}) was added to the design {var:2} as a draft through the API by the application {var:3} (key {var:4}).',
		'vars'   => array($name, $design['style_name'], $app['name'], $app['api_key'])
	)), $owner['username']);

	pg_announce('page.created', array('id' => $page_id, 'name' => $name));

	$row = api_row(api_design_page_select() . " WHERE page.page_id = '" . $page_id . "' LIMIT 1");

	api_ok(api_design_page_present($row), 201);

}

// A page as an assistant reads it. See pg_design_ai_view() for the shape of
// the HTML: data-pg on every element, <pg-keep> for what the page runs rather
// than shows.
function api_design_view_present($page, $tree, $view, $format) {

	$out = array(
		'page_id'   => (int)$page['page_id'],
		'page_name' => (string)$page['page_name'],
		'design_id' => (int)$page['style_id'],
		'framework' => (string)$page['framework'],
		'node_id'   => (string)$view['node_id'],
		'root_id'   => (string)$view['root_id'],
		'hash'      => pg_design_ai_hash($tree),
		'css'       => (string)$view['css'],
		'islands'   => (int)$view['islands'],
		'cut'       => (bool)$view['cut'],
		'html'      => ($format === 'outline') ? '' : (string)$view['html'],
		'outline'   => array(),
		'operations' => pg_design_ai_op_names()
	);

	if ($format !== 'html') {

		foreach ($view['outline'] as $item) {

			$out['outline'][] = api_design_outline_item_present($item);

		}

	}

	return $out;

}

// One element of the outline: where it stands and what it is.
function api_design_outline_item_present($item) {

	return array(
		'id'     => (string)$item['id'],
		'parent' => (string)$item['parent'],
		'depth'  => (int)$item['depth'],
		'type'   => (string)$item['type'],
		'tag'    => (string)$item['tag'],
		'name'   => (string)$item['name'],
		'class'  => (string)$item['class'],
		'text'   => (string)$item['text'],
		'island' => (string)$item['island']
	);

}

function api_design_outline_item_schema() {

	return array(
		'id'     => 'string',
		'parent' => 'string',
		'depth'  => 'integer',
		'type'   => 'string',
		'tag'    => 'string',
		'name'   => 'string',
		'class'  => 'string',
		'text'   => 'string',
		'island' => 'string'
	);

}

function api_design_view_schema() {

	return array(
		'page_id'    => 'integer',
		'page_name'  => 'string',
		'design_id'  => 'integer',
		'framework'  => 'string',
		'node_id'    => 'string',
		'root_id'    => 'string',
		'hash'       => 'string',
		'css'        => 'string',
		'islands'    => 'integer',
		'cut'        => 'boolean',
		'html'       => 'string',
		'outline'    => 'DesignOutlineItem[]',
		'operations' => 'string[]'
	);

}

function api_design_pages_get($params) {

	api_design_ready();

	$page = api_design_page_or_fail($params['id']);

	$request = api_design_request_for(isset($params['request']) ? $params['request'] : 0, $page);

	$tree = pg_design_ai_base_tree($request, $page);

	if (!$tree) {

		api_fail_server(lang('The page could not be read.'));

	}

	$node = isset($params['node']) ? (string)$params['node'] : '';

	$view = pg_design_ai_view($tree, $node, array('max_html' => isset($params['max_length']) ? (int)$params['max_length'] : 0));

	if (!$view['found']) {

		api_fail_not_found(lang('Element'));

	}

	api_ok(api_design_view_present($page, $tree, $view, isset($params['format']) ? (string)$params['format'] : 'both'));

}

function api_design_proposal_out($row) {

	$out = pg_design_ai_proposal_present($row, true);

	return array(
		'id'         => $out['id'],
		'request_id' => ($out['request_id'] > 0) ? $out['request_id'] : null,
		'agent'      => $out['agent'],
		'page_id'    => $out['page_id'],
		'page_name'  => $out['page_name'],
		'design_id'  => $out['style_id'],
		'node_id'    => $out['node_id'],
		'summary'    => $out['summary'],
		'status'     => $out['status'],
		'applied_to' => $out['applied_to'],
		'whole_page' => $out['whole_page'],
		'ops'        => $out['ops'],
		'steps'      => $out['steps'],
		'dropped'    => $out['dropped'],
		'removed'    => $out['removed'],
		'message_id' => ((int)$row['message_id'] > 0) ? (int)$row['message_id'] : null,
		'created_at' => api_time($out['created_at']),
		'decided_at' => ($out['decided_at'] > 0) ? api_time($out['decided_at']) : null
	);

}

function api_design_proposal_schema() {

	return array(
		'id'         => 'integer',
		'request_id' => 'integer?',
		'agent'      => 'string',
		'page_id'    => 'integer',
		'page_name'  => 'string',
		'design_id'  => 'integer',
		'node_id'    => 'string',
		'summary'    => 'string',
		'status'     => 'string',
		'applied_to' => 'string',
		'whole_page' => 'boolean',
		'ops'        => 'object[]',
		'steps'      => 'string[]',
		'dropped'    => 'string[]',
		'removed'    => 'string[]',
		'message_id' => 'integer?',
		'created_at' => 'string?',
		'decided_at' => 'string?'
	);

}

function api_design_proposals_create($params) {

	api_design_ready();

	$page = api_design_page_or_fail($params['id']);

	$request = api_design_request_for(isset($params['request_id']) ? $params['request_id'] : 0, $page);

	$app = api_current_app();

	$owner_id = isset($app['owner']['id']) ? (int)$app['owner']['id'] : 0;

	// Checked in full before anything is kept: every operation has to find
	// what it names, and the page it makes has to be a valid page. The index
	// of the operation that failed is in the field name.
	$check = pg_design_ai_proposal_create(array(
		'page_id' => (int)$page['page_id'],
		'ops'     => $params['ops'],
		'request' => $request,
		'dry_run' => true
	));

	if (!$check['ok']) {

		api_fail_validation($check['error'], ($check['op'] >= 0) ? 'ops[' . $check['op'] . ']' : 'ops');

	}

	api_dry_run_stop('created', 'design_proposal', array(
		'page_id' => (int)$page['page_id'],
		'steps'   => $check['result']['lines'],
		'dropped' => $check['result']['dropped'],
		'removed' => $check['result']['removed']
	));

	$created = pg_design_ai_proposal_create(array(
		'page_id'      => (int)$page['page_id'],
		'ops'          => $params['ops'],
		'summary'      => isset($params['summary']) ? (string)$params['summary'] : '',
		'node_id'      => isset($params['node']) ? (string)$params['node'] : ($request ? (string)$request['node_id'] : ''),
		'request'      => $request,
		'agent'        => $request ? 'claude' : 'app',
		'app_id'       => isset($app['id']) ? (int)$app['id'] : 0,
		'requested_by' => $request ? (int)$request['requested_by'] : $owner_id,
		'channel_id'   => $request ? (int)$request['channel_id'] : 0
	));

	if (!$created['ok']) {

		api_fail_validation($created['error'], 'ops');

	}

	if (function_exists('log_activity')) {

		log_activity(lang(array('string' => 'a change of the page {var:1} was proposed', 'vars' => $page['page_name'])) . ' (' . lang('API') . ': ' . (isset($app['name']) ? (string)$app['name'] : '') . ')', isset($app['owner']['username']) ? (string)$app['owner']['username'] : '');

	}

	api_ok(api_design_proposal_out(pg_design_ai_proposal($created['id'])), 201);

}

function api_design_proposals_list($params) {

	api_design_ready();

	$page = api_design_page_or_fail($params['id']);

	$where = "page_id = '" . (int)$page['page_id'] . "'";

	if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== 'all') {

		$where .= " AND status = '" . escape($params['status']) . "'";

	}

	$out = array();

	foreach (api_rows("SELECT * FROM design_proposals WHERE " . $where . " ORDER BY id DESC LIMIT 50") as $row) {

		$out[] = api_design_proposal_out($row);

	}

	api_ok_list($out, 50);

}

function api_design_proposals_get($params) {

	api_design_ready();

	$row = pg_design_ai_proposal($params['id']);

	if (!$row) {

		api_fail_not_found(lang('Proposal'));

	}

	api_ok(api_design_proposal_out($row));

}
