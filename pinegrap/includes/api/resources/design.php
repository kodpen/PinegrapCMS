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
// No layout is written here. A proposal is checked against the page - every
// operation must find what it names and the result must be a valid page - and
// then waits until a person applies it in the editor or from the workspace
// answer it was made under, with their own rights (includes/designer_ai.php).
// That is why design:write is a designer's decision to delegate: it lets an
// application put a change in front of the people who build the site, not
// make one. A new page is always a draft: it is in the editor with the
// design's other pages and on the site only once it is published.
//
// Three writes go through the design save itself, in the name of the owner of
// the application, each refused while somebody has the page open in the
// editor (their next save would put their tab back over it): a new page, the
// times a page goes on the site and comes off it (includes/fn/page_schedule.php),
// and a published revision put back (includes/fn/page_revisions.php). A
// restore is a new publish of a layout a person published before, recorded
// with the source 'restore'; the page keeps its settings and its times.

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
		'publish_at'        => api_time(isset($row['publish_at']) ? $row['publish_at'] : 0),
		'unpublish_at'      => api_time(isset($row['unpublish_at']) ? $row['unpublish_at'] : 0),
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
		'publish_at'        => 'string?',
		'unpublish_at'      => 'string?',
		'updated_at'        => 'string?',
		'updated_at_unix'   => 'integer'
	);

}

// The two schedule columns (2026.11.0, 11.0.17), or zeros where the upgrade
// has not run, so the select never names a column that is not there.
function api_design_schedule_columns() {

	return pg_page_schedule_ready()
		? 'page.publish_at, page.unpublish_at,'
		: '0 AS publish_at, 0 AS unpublish_at,';

}

// What api_design_page_present() reads, for the list and for a page just made.
function api_design_page_select() {

	$framework = (function_exists('waf_table_has_column') && waf_table_has_column('style', 'style_framework')) ? 'style.style_framework' : "'bootstrap5' AS style_framework";

	return "SELECT page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style,
			page.page_timestamp, " . api_design_schedule_columns() . " style.style_name, " . $framework . ",
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

		$q = escape(escape_like($params['q']));

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

// The owner of the application, when they may change pages of a design. The
// design scope is only granted to designers; this holds when the owner's role
// has changed since.
function api_design_designer_or_fail($message) {

	require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

	$app = api_current_app();

	if (!pg_designer_is_full($app['owner'])) {

		api_fail(403, 'forbidden', $message);

	}

	return $app;

}

// A saved page written from here would be put back by the next save of an
// editor tab that has it open, so the write waits until nobody has it open.
function api_design_page_free_or_fail($page) {

	require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

	if (!pg_collab_may_edit_page('', $page['page_id'])) {

		api_fail(409, 'page_open_in_editor', lang('Somebody has this page open in the Visual Page Editor. Try again when they are done, or make the change there.'));

	}

}

// The owner of the application as the design save takes a person.
function api_design_user_of($owner) {

	return array('id' => (int)$owner['id'], 'role' => (int)$owner['role'], 'username' => (string)$owner['username']);

}

// The design save, in the name of the owner of the application: it writes its
// log lines in the name of the signed-in person.
function api_design_save_as_owner($design_id, $fields, $owner) {

	$user = api_design_user_of($owner);

	$had_name = isset($_SESSION) && array_key_exists('sessionusername', $_SESSION);

	$old_name = $had_name ? $_SESSION['sessionusername'] : null;

	$_SESSION['sessionusername'] = (string)$owner['username'];

	$saved = pg_designer_save_page($design_id, $fields, $user, false);

	if ($had_name) {

		$_SESSION['sessionusername'] = $old_name;

	} else {

		unset($_SESSION['sessionusername']);

	}

	return $saved;

}

// The publish time and the time to come off the site as sent, read as unix
// times: only the ones in the request, 0 for a clear (null or "").
function api_design_times_in($params) {

	$sent = array();

	foreach (array('publish_at', 'unpublish_at') as $field) {

		if (!array_key_exists($field, $params)) {

			continue;

		}

		$parsed = pg_page_schedule_parse_absolute($params[$field]);

		if ($parsed['error'] === 'offset') {

			api_fail_validation(lang(array('string' => '{var:1} needs its offset from UTC, as in 2026-10-21T09:30:00+03:00, or Z: the site\'s time zone is not assumed. A unix time is accepted as well.', 'vars' => $field)), $field);

		}

		if (!$parsed['ok']) {

			api_fail_validation(lang(array('string' => '{var:1} is not a date and time. Send ISO 8601 with an offset or Z, or a unix time.', 'vars' => $field)), $field);

		}

		$sent[$field] = $parsed['time'];

	}

	if ($sent && (!pg_page_schedule_ready() || !pg_page_draft_ready())) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that schedules pages.'));

	}

	return $sent;

}

// The times checked against the page as the write will leave it; refused
// with the time it is about (pg_page_schedule_request_plan()).
function api_design_times_plan_or_fail($sent, $stored, $draft, $home, $name, $creating) {

	$check = pg_page_schedule_request_plan($sent, $stored, $draft, $home, time(), $creating);

	if ($check['error'] === 'home') {

		api_fail_validation(lang('The home page stays on the site, so it takes no publish time and no time to come off it.'), $check['field']);

	}

	if (($check['error'] === 'passed') && ($check['field'] === 'unpublish_at')) {

		api_fail_validation(lang('unpublish_at has passed. A new page starts as a draft, off the site, so its time to come off it has to be ahead.'), $check['field']);

	}

	if ($check['error'] === 'passed') {

		api_fail_validation(lang('publish_at has passed. A new page starts as a draft, so its publish time has to be ahead.'), $check['field']);

	}

	if ($check['error'] !== '') {

		api_fail_validation(pg_page_schedule_message($check['error'], $name), $check['field']);

	}

	return $check['plan'];

}

// A new page of a design, always as a draft: the editor's "new page" (an empty
// canvas) or its "duplicate page" (copy_from). The folder is the one the page
// is published to when a person puts it on the site.
function api_design_pages_create($params) {

	api_design_ready();

	if (!pg_page_draft_ready()) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that keeps pages as drafts.'));

	}

	require_once(dirname(__FILE__) . '/folders.php');

	$app = api_design_designer_or_fail(lang('Only designers can add pages to a design.'));

	$owner = $app['owner'];

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

	// When the draft goes on the site and comes off it again. A new page has
	// no stored times and is not the home page.
	$times = api_design_times_in($params);

	$plan = $times ? api_design_times_plan_or_fail($times, array(), true, false, $name, true) : null;

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

	foreach ($times as $field => $time) {

		$fields['page_' . $field] = $time;

	}

	$user = api_design_user_of($owner);

	$check = pg_designer_save_page($design_id, $fields, $user, true);

	if (!$check['ok']) {

		api_fail_validation(implode(' ', $check['errors']), ($copy_from > 0) ? 'copy_from' : null);

	}

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	api_dry_run_stop('created', 'design_page', array(
		'design_id'    => $design_id,
		'name'         => $name,
		'url'          => $base . encode_url_path($name),
		'folder_id'    => $folder_id,
		'draft'        => true,
		'publish_at'   => api_time($plan ? $plan['publish_at'] : 0),
		'unpublish_at' => api_time($plan ? $plan['unpublish_at'] : 0),
		'copy_from'    => ($copy_from > 0) ? $copy_from : null
	));

	$saved = api_design_save_as_owner($design_id, $fields, $owner);

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
		'page_id'      => (int)$page['page_id'],
		'page_name'    => (string)$page['page_name'],
		'design_id'    => (int)$page['style_id'],
		'framework'    => (string)$page['framework'],
		'draft'        => api_design_page_is_draft($page['page_folder']),
		'publish_at'   => api_time(isset($page['publish_at']) ? $page['publish_at'] : 0),
		'unpublish_at' => api_time(isset($page['unpublish_at']) ? $page['unpublish_at'] : 0),
		'node_id'      => (string)$view['node_id'],
		'root_id'      => (string)$view['root_id'],
		'hash'         => pg_design_ai_hash($tree),
		'css'          => (string)$view['css'],
		'islands'      => (int)$view['islands'],
		'cut'          => (bool)$view['cut'],
		'html'         => ($format === 'outline') ? '' : (string)$view['html'],
		'outline'      => array(),
		'operations'   => pg_design_ai_op_names()
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
		'page_id'      => 'integer',
		'page_name'    => 'string',
		'design_id'    => 'integer',
		'framework'    => 'string',
		'draft'        => 'boolean',
		'publish_at'   => 'string?',
		'unpublish_at' => 'string?',
		'node_id'      => 'string',
		'root_id'      => 'string',
		'hash'         => 'string',
		'css'          => 'string',
		'islands'      => 'integer',
		'cut'          => 'boolean',
		'html'         => 'string',
		'outline'      => 'DesignOutlineItem[]',
		'operations'   => 'string[]'
	);

}

// When a page goes on the site and comes off it, as stored (0 for none).
function api_design_page_times($page_id) {

	$row = pg_page_schedule_ready()
		? api_row("SELECT publish_at, unpublish_at FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1")
		: null;

	return array(
		'publish_at'   => $row ? (int)$row['publish_at'] : 0,
		'unpublish_at' => $row ? (int)$row['unpublish_at'] : 0
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

	$page = array_merge($page, api_design_page_times($page['page_id']));

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

// When a page goes on the site and comes off it again. The times go through
// the design save like the editor's Page Settings › Schedule: a publish time
// ahead keeps the page a draft until then (and takes a page on the site off it
// now), a time that has come is carried out by this save, the general job
// carries out the rest (pg_page_schedule_run()).
function api_design_pages_update($params) {

	api_design_ready();

	$page = api_design_page_or_fail($params['id']);

	$app = api_design_designer_or_fail(lang('Only designers can set when a page goes on the site.'));

	$times = api_design_times_in($params);

	if (!$times) {

		api_fail_validation(lang('Send publish_at, unpublish_at or both.'));

	}

	$stored = api_row("SELECT page_home, publish_at, unpublish_at FROM page WHERE page_id = '" . (int)$page['page_id'] . "' LIMIT 1");

	$plan = api_design_times_plan_or_fail($times, $stored, api_design_page_is_draft($page['page_folder']),
		((string)$stored['page_home'] === 'yes'), $page['page_name'], false);

	api_design_page_free_or_fail($page);

	$fields = pg_design_ai_page_fields($page);

	if (!$fields) {

		api_fail_server(lang('The page could not be read.'));

	}

	// A time not sent keeps the stored one to the second; the editor's copy
	// of it is rounded to the minute.
	unset($fields['page_publish_at'], $fields['page_unpublish_at']);

	foreach ($times as $field => $time) {

		$fields['page_' . $field] = $time;

	}

	$owner = $app['owner'];

	$user = api_design_user_of($owner);

	$check = pg_designer_save_page($page['style_id'], $fields, $user, true);

	if (!$check['ok']) {

		api_fail_validation(implode(' ', $check['errors']));

	}

	api_dry_run_stop('updated', 'design_page', array(
		'id'           => (int)$page['page_id'],
		'draft'        => (bool)$check['draft'],
		'publish_at'   => api_time($plan['publish_at']),
		'unpublish_at' => api_time($plan['unpublish_at']),
		'warnings'     => array_values($check['warnings'])
	));

	$saved = api_design_save_as_owner($page['style_id'], $fields, $owner);

	if (!$saved['ok']) {

		api_fail_server(implode(' ', $saved['errors']));

	}

	log_activity(lang(array(
		'string' => 'The publish times of the page ({var:1}) were set through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($page['page_name'], $app['name'], $app['api_key'])
	)), $owner['username']);

	pg_announce('page.updated', array('id' => (int)$page['page_id'], 'name' => (string)$page['page_name']));

	$row = api_row(api_design_page_select() . " WHERE page.page_id = '" . (int)$page['page_id'] . "' LIMIT 1");

	api_ok(api_design_page_present($row));

}

// A clean 503 where the revisions table is not there yet.
function api_design_revisions_ready() {

	if (!pg_page_revisions_ready()) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that keeps page revisions.'));

	}

}

// One published revision of a page, without its layout. name and title are the
// page's as they were when it was published.
function api_design_revision_present($row) {

	$settings = isset($row['settings']) && is_array($row['settings']) ? $row['settings'] : array();

	return array(
		'revision_no'     => (int)$row['revision_no'],
		'source'          => (string)$row['source'],
		'name'            => isset($settings['page_name']) ? (string)$settings['page_name'] : null,
		'title'           => isset($settings['page_title']) ? (string)$settings['page_title'] : null,
		'user_id'         => ((int)$row['user_id'] > 0) ? (int)$row['user_id'] : null,
		'user'            => ((string)$row['user_username'] !== '') ? (string)$row['user_username'] : null,
		'size'            => (int)$row['size'],
		'created_at'      => api_time($row['created_at']),
		'created_at_unix' => (int)$row['created_at']
	);

}

function api_design_revision_schema() {

	return array(
		'revision_no'     => 'integer',
		'source'          => 'string',
		'name'            => 'string?',
		'title'           => 'string?',
		'user_id'         => 'integer?',
		'user'            => 'string?',
		'size'            => 'integer',
		'created_at'      => 'string?',
		'created_at_unix' => 'integer'
	);

}

function api_design_revisions_list($params) {

	api_design_ready();

	api_design_revisions_ready();

	$page = api_design_page_or_fail($params['id']);

	$limit = (int)$params['limit'];

	$before = 0;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$before = (int)$cursor['i'];

	}

	$rows = pg_page_revisions_list($page['page_id'], $limit + 1, $before);

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['revision_no'], (int)$last['revision_no']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_revision_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// What a restore answers with: the page as it is now (api_design_page_present()),
// the revision it was put back to and the revision the restore itself was
// recorded as.
function api_design_restore_present($page, $restored_from, $revision_no, $changed, $warnings) {

	return array(
		'page'          => $page,
		'restored_from' => (int)$restored_from,
		'revision_no'   => ((int)$revision_no > 0) ? (int)$revision_no : null,
		'changed'       => (bool)$changed,
		'warnings'      => array_values((array)$warnings)
	);

}

function api_design_restore_schema() {

	return array(
		'page'          => 'DesignPage',
		'restored_from' => 'integer',
		'revision_no'   => 'integer?',
		'changed'       => 'boolean',
		'warnings'      => 'string[]'
	);

}

// Puts a published revision's layout back on the page, as the editor's
// History panel does when the loaded revision is published again: a new
// publish, recorded with the source 'restore'. The page's settings, its
// draft state and its times stay as they are now.
function api_design_revisions_restore($params) {

	api_design_ready();

	api_design_revisions_ready();

	$page = api_design_page_or_fail($params['id']);

	$app = api_design_designer_or_fail(lang('Only designers can restore a revision of a page.'));

	$revision = pg_page_revision_get($page['page_id'], $params['no']);

	if (!$revision) {

		api_fail_not_found(lang('Revision'));

	}

	api_design_page_free_or_fail($page);

	$fields = pg_design_ai_page_fields($page);

	if (!$fields) {

		api_fail_server(lang('The page could not be read.'));

	}

	unset($fields['page_publish_at'], $fields['page_unpublish_at']);

	$fields['tree_json']       = (string)$revision['tree_json'];
	$fields['revision_source'] = 'restore';

	// The page already has this layout: nothing to put back, and nothing is
	// written or recorded.
	$changed = ((string)$page['tree_json'] !== (string)$revision['tree_json']);

	$owner = $app['owner'];

	$user = api_design_user_of($owner);

	$check = pg_designer_save_page($page['style_id'], $fields, $user, true);

	if (!$check['ok']) {

		api_fail_validation(implode(' ', $check['errors']), 'no');

	}

	api_dry_run_stop('restored', 'design_page', array(
		'id'            => (int)$page['page_id'],
		'restored_from' => (int)$revision['revision_no'],
		'changed'       => $changed,
		'draft'         => (bool)$check['draft'],
		'warnings'      => array_values($check['warnings'])
	));

	$warnings = array();

	$revision_no = 0;

	if ($changed) {

		$saved = api_design_save_as_owner($page['style_id'], $fields, $owner);

		if (!$saved['ok']) {

			api_fail_server(implode(' ', $saved['errors']));

		}

		$warnings = $saved['warnings'];

		$revision_no = isset($saved['revision_no']) ? (int)$saved['revision_no'] : 0;

		log_activity(lang(array(
			'string' => 'Page ({var:1}) was restored to revision {var:2} through the API by the application {var:3} (key {var:4}).',
			'vars'   => array($page['page_name'], (int)$revision['revision_no'], $app['name'], $app['api_key'])
		)), $owner['username']);

		pg_announce('page.updated', array('id' => (int)$page['page_id'], 'name' => (string)$page['page_name']));

	}

	$row = api_row(api_design_page_select() . " WHERE page.page_id = '" . (int)$page['page_id'] . "' LIMIT 1");

	api_ok(api_design_restore_present(api_design_page_present($row), $revision['revision_no'], $revision_no, $changed, $warnings));

}

// Share links of a design's prototype (includes/fn/prototype_links.php):
// listed, made and revoked by an application whose owner is an administrator
// or a designer, as in the editor's Share prototype dialog. None of the three
// routes is an MCP tool ('mcp' => false): no AI app mints a public link to
// pages that are not on the site. The address of a link is answered once,
// when it is made; the editor can show it again to a designer.
function api_design_prototype_links_ready() {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

	if (!pg_proto_links_ready()) {

		api_fail(503, 'service_unavailable', lang('This site has not been upgraded to the version that shares prototypes.'));

	}

}

// The design a link belongs to, or 404.
function api_design_prototype_design_or_fail($id) {

	$name = pg_proto_link_design_name($id);

	if ($name === null) {

		api_fail_not_found(lang('Design'));

	}

	return $name;

}

// One link, with nothing that opens it (pg_proto_link_summary()).
function api_design_prototype_link_present($row, $now) {

	$link = pg_proto_link_summary($row, $now);

	return array(
		'id'              => $link['id'],
		'design_id'       => $link['style_id'],
		'audience'        => $link['audience'],
		'start_page_id'   => ($link['start_page_id'] > 0) ? $link['start_page_id'] : null,
		'hint'            => $link['hint'],
		'password'        => $link['password'],
		'password_locked' => $link['locked'],
		'state'           => $link['state'],
		'expires_at'      => api_time($link['expires_at']),
		'uses'            => $link['uses'],
		'last_used_at'    => api_time($link['last_used_at']),
		'created_by'      => ($link['created_by'] > 0) ? $link['created_by'] : null,
		'created_at'      => api_time($link['created_at']),
		'revoked_at'      => api_time($link['revoked_at'])
	);

}

// What api_design_prototype_link_present() returns.
function api_design_prototype_link_schema() {

	return array(
		'id'              => 'integer',
		'design_id'       => 'integer',
		'audience'        => 'string',
		'start_page_id'   => 'integer?',
		'hint'            => 'string',
		'password'        => 'boolean',
		'password_locked' => 'boolean',
		'state'           => 'string',
		'expires_at'      => 'string?',
		'uses'            => 'integer',
		'last_used_at'    => 'string?',
		'created_by'      => 'integer?',
		'created_at'      => 'string?',
		'revoked_at'      => 'string?'
	);

}

// What a new link answers with: its address, shown this once (null on a
// retry answered from its Idempotency-Key record, which keeps no address),
// and the link.
function api_design_prototype_link_created_schema() {

	return array(
		'url'  => 'string?',
		'link' => 'DesignPrototypeLink'
	);

}

// The password of a new link as the plan is handed it, from the body as sent:
// the router trims a string, which would make one of spaces alone no password
// at all and drop the spaces around another, while the editor and the
// password form take it as typed. Not sent or null is no password; a number
// is read as its digits, as the router reads one for a text field; anything
// else (a list, an object, true or false) goes on as it came, and the plan
// refuses it instead of the link being made with no password.
function api_design_prototype_link_password($input) {

	if (!is_array($input) || !array_key_exists('password', $input) || ($input['password'] === null)) {

		return '';

	}

	$value = $input['password'];

	return (is_int($value) || is_float($value)) ? (string)$value : $value;

}

// The line the Site Log adds after a link's own words: the application.
function api_design_prototype_via($app) {

	return ' ' . lang(array('string' => '(through the API by the application {var:1})', 'vars' => array(isset($app['name']) ? (string)$app['name'] : '')));

}

function api_design_prototype_links_list($params) {

	api_design_prototype_links_ready();

	api_design_designer_or_fail(lang('Only administrators and designers can share a prototype.'));

	$design_id = (int)$params['id'];

	api_design_prototype_design_or_fail($design_id);

	$where = array("style_id = '" . $design_id . "'");

	if (empty($params['revoked'])) {

		$where[] = "revoked_at = 0";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT * FROM prototype_links WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$now = time();

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_prototype_link_present($row, $now);

	}

	api_ok_list($out, $limit, $next);

}

function api_design_prototype_links_create($params) {

	api_design_prototype_links_ready();

	$app = api_design_designer_or_fail(lang('Only administrators and designers can share a prototype.'));

	$design_id = (int)$params['id'];

	api_design_prototype_design_or_fail($design_id);

	$draft_ids = array();

	foreach (pg_proto_design_pages($design_id) as $page) {

		$draft_ids[] = (int)$page['id'];

	}

	$now = time();

	// The password as sent, not as the router trimmed it.
	$password = api_design_prototype_link_password(api_request_input());

	$plan = pg_proto_link_plan(array(
		'audience'      => isset($params['audience']) ? $params['audience'] : '',
		'start_page_id' => isset($params['start_page_id']) ? (int)$params['start_page_id'] : 0,
		'duration'      => isset($params['duration']) ? (int)$params['duration'] : 0,
		'password'      => $password
	), $draft_ids, array_keys(pg_proto_link_durations()), $now);

	if (!$plan['ok']) {

		api_fail_validation(pg_proto_link_error_message($plan['error']), ($plan['error'] === 'no_drafts') ? 'id' : $plan['field']);

	}

	api_dry_run_stop('created', 'design_prototype_link', array(
		'design_id'     => $design_id,
		'audience'      => $plan['link']['audience'],
		'start_page_id' => ($plan['link']['start_page_id'] > 0) ? $plan['link']['start_page_id'] : null,
		'expires_at'    => api_time($plan['link']['expires_at']),
		'password'      => ($plan['link']['password'] !== '')
	));

	$owner = $app['owner'];

	$made = pg_proto_link_issue($design_id, $plan['link'], (int)$owner['id'], (string)$owner['username'], api_design_prototype_via($app));

	if ($made === null) {

		api_fail_server(lang('The link could not be made.'));

	}

	api_ok(array('url' => pg_proto_link_url($made['token']), 'link' => api_design_prototype_link_present($made['row'], $now)), 201);

}

function api_design_prototype_links_revoke($params) {

	api_design_prototype_links_ready();

	$app = api_design_designer_or_fail(lang('Only administrators and designers can share a prototype.'));

	$design_id = (int)$params['id'];

	api_design_prototype_design_or_fail($design_id);

	$row = pg_proto_link_get($params['link_id']);

	if (($row === null) || ((int)$row['style_id'] !== $design_id)) {

		api_fail_not_found(lang('Link'));

	}

	$now = time();

	api_dry_run_stop('revoked', 'design_prototype_link', array(
		'id'      => (int)$row['id'],
		'changed' => ((int)$row['revoked_at'] === 0)
	));

	$owner = $app['owner'];

	pg_proto_link_revoke($row, (int)$owner['id'], (string)$owner['username'], api_design_prototype_via($app));

	api_ok(api_design_prototype_link_present(pg_proto_link_get($row['id']), $now));

}
