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
// Nothing here writes a page. A proposal is checked against the page - every
// operation must find what it names and the result must be a valid page - and
// then waits until a person applies it in the editor or from the workspace
// answer it was made under, with their own rights (includes/designer_ai.php).
// That is why design:write is a designer's decision to delegate: it lets an
// application put a change in front of the people who build the site, not
// make one.

if (!defined('PG_API_ENTRY')) {
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
		'updated_at'        => 'string?',
		'updated_at_unix'   => 'integer'
	);

}

function api_design_pages_list($params) {

	api_design_ready();

	$framework = (function_exists('waf_table_has_column') && waf_table_has_column('style', 'style_framework')) ? 'style.style_framework' : "'bootstrap5' AS style_framework";

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

		$where[] = "page.page_id > '" . (int)$cursor['id'] . "'";

	}

	$rows = api_rows("SELECT page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style,
			page.page_timestamp, style.style_name, " . $framework . ",
			(SELECT COUNT(*) FROM design_proposals WHERE design_proposals.page_id = page.page_id AND design_proposals.status = 'pending') AS pending_proposals
		FROM page
		INNER JOIN style ON page.page_style = style.style_id
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
