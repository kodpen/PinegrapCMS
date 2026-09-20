<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Pages: the search surface of the site, not its content.
//
// What an outside system - a marketplace tool, a reporting job, an automated
// assistant - asks of a page is what the search engines are told about it: the
// title, the description, whether it is in the site map, whether it is closed
// to indexing, and what it is already scoring. That is what this endpoint
// answers and what it writes.
//
// The page's own text is deliberately not here. Producing it means rendering
// the page in process, which costs a full request's worth of queries per row,
// and every client that wants the text can fetch the address in the "url"
// field over HTTP like any other reader.
//
// Nothing in the read path calculates a score. Both halves of the SEO record
// are written elsewhere - the meta half by the save below and by the nightly
// score job, the structure and link halves by the nightly analysis job - and
// the freshness of each half is reported rather than hidden, so a client that
// has just written a title is not left wondering why the structure score did
// not move.

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

// Page types that may appear in the site map. The same list the page screen
// applies; a type outside it is refused rather than quietly written as zero,
// because an API that accepts a value and stores another one is worse than one
// that says no.
function api_page_sitemap_types() {

	return array(
		'standard', 'folder view', 'photo gallery', 'custom form', 'form list view',
		'form item view', 'form view directory', 'calendar view', 'calendar event view',
		'catalog', 'catalog detail', 'express order', 'order form', 'shopping cart',
		'search results'
	);

}

// Page types the storefront and the account area need in order to work. The
// panel has no guard against deleting these - an operator who removes the
// shopping cart page knows what they are doing - but an application acting on
// its own is a different matter, so they are refused here.
function api_page_system_types() {

	return array(
		'change password', 'set password', 'email a friend', 'error', 'forgot password',
		'login', 'logout', 'membership confirmation', 'membership entrance', 'my account',
		'my account profile', 'email preferences', 'view order', 'update address book',
		'custom form confirmation', 'shopping cart', 'shipping address and arrival',
		'shipping method', 'billing information', 'order preview', 'order receipt',
		'order form', 'registration confirmation', 'registration entrance',
		'affiliate sign up form', 'affiliate sign up confirmation', 'affiliate welcome'
	);

}

function api_page_select() {

	api_seo_library();

	// The indexing switches arrive with an upgrade, so they are named only when
	// they are there: selecting a column that does not exist is a failed query
	// and this endpoint has to keep working on an installation that is behind.
	$noindex = pg_page_noindex_ready()
		? "page.noindex, page.nofollow,"
		: "'0' AS noindex, '0' AS nofollow,";

	return "SELECT page.page_id, page.page_name, page.page_folder, page.page_home,
			page.page_type, page.layout_type, page.page_title, page.page_meta_description,
			page.page_search, page.page_search_keywords, page.sitemap,
			" . $noindex . "
			page.page_timestamp,
			" . api_seo_columns('page.', true) . "
			page.page_user
		FROM page";

}

// The stored keyword field is one comma separated string, which is what the
// tag cloud writer parses. It crosses the wire as a list, because a client
// building one from a language model or a spreadsheet should not have to know
// how this column is packed.
function api_page_keywords_out($stored) {

	$stored = trim((string)$stored);

	if ($stored === '') {

		return array();

	}

	$out = array();

	foreach (explode(',', $stored) as $keyword) {

		$keyword = trim($keyword);

		if ($keyword !== '') {

			$out[] = $keyword;

		}

	}

	return array_values(array_unique($out));

}

function api_page_keywords_in($list, $name) {

	$out = array();

	foreach ($list as $keyword) {

		if (is_array($keyword) || is_object($keyword)) {

			api_fail_validation(lang(array('string' => '{var:1} must be a list of words.', 'vars' => $name)), $name);

		}

		$keyword = trim(str_replace(',', ' ', (string)$keyword));

		if ($keyword !== '') {

			$out[] = $keyword;

		}

	}

	$out = array_values(array_unique($out));

	$joined = implode(',', $out);

	// The column is a longtext, but a keyword list that long is a mistake on
	// the client's side rather than something to store.
	if (mb_strlen($joined) > 1000) {

		api_fail_validation(lang(array(
			'string' => '{var:1} is longer than the {var:2} characters accepted.',
			'vars'   => array($name, 1000)
		)), $name);

	}

	return $joined;

}

function api_page_present($row, $with_issues = false) {

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$out = array(
		'id'               => (int)$row['page_id'],
		'name'             => $row['page_name'],
		'url'              => $base . encode_url_path($row['page_name']),
		'folder_id'        => (int)$row['page_folder'],
		'type'             => $row['page_type'],
		'home'             => ($row['page_home'] === 'yes'),
		'title'            => $row['page_title'],
		'meta_description' => $row['page_meta_description'],
		'search'           => ((int)$row['page_search'] === 1),
		'search_keywords'  => api_page_keywords_out($row['page_search_keywords']),
		'sitemap'          => ((int)$row['sitemap'] === 1)
	);

	api_seo_library();

	// An installation that has not run the upgrade has no such columns, and
	// reporting them as false would tell a client the page is open to indexing
	// when the site cannot express the setting at all.
	if (pg_page_noindex_ready()) {

		$out['noindex']  = ((int)$row['noindex'] === 1);

		$out['nofollow'] = ((int)$row['nofollow'] === 1);

	}

	$out['layout_type']     = $row['layout_type'];
	$out['updated_at']      = api_time($row['page_timestamp']);
	$out['updated_at_unix'] = (int)$row['page_timestamp'];
	$out['seo']             = api_seo_block('page', $row['page_id'], $row, $with_issues);

	return $out;

}

function api_pages_list($params) {

	api_seo_library();

	$where = array();

	if (isset($params['updated_since'])) {

		$where[] = "page.page_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['folder_id'])) {

		$where[] = "page.page_folder = '" . (int)$params['folder_id'] . "'";

	}

	if (isset($params['type']) && $params['type'] !== '') {

		$where[] = "page.page_type = '" . escape($params['type']) . "'";

	}

	if (isset($params['home'])) {

		$where[] = $params['home'] ? "page.page_home = 'yes'" : "page.page_home != 'yes'";

	}

	if (isset($params['sitemap'])) {

		$where[] = "page.sitemap = '" . ($params['sitemap'] ? '1' : '0') . "'";

	}

	if (isset($params['search'])) {

		$where[] = "page.page_search = '" . ($params['search'] ? '1' : '0') . "'";

	}

	if (isset($params['noindex'])) {

		if (!pg_page_noindex_ready()) {

			api_fail_validation(lang('This site has not been upgraded to the version that carries the noindex setting.'), 'noindex');

		}

		$where[] = "page.noindex = '" . ($params['noindex'] ? '1' : '0') . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$term = escape($params['q']);

		$where[] = "(page.page_name LIKE '%" . $term . "%' OR page.page_title LIKE '%" . $term . "%')";

	}

	// Only the pages that carry a score below the given number. A client that
	// is working through a site fixes the worst first, and asking for that
	// here costs nothing extra.
	if (isset($params['max_score'])) {

		$where[] = "page.seo_score <= '" . (int)$params['max_score'] . "'";

	}

	// The cursor is kept apart from the filters so the optional count answers
	// the whole result rather than the tail of it. Timestamp and id are
	// compared as a pair, so pages written in the same second neither repeat
	// nor go missing across a page boundary.
	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "(page.page_timestamp > '" . $cursor['v'] . "'
			OR (page.page_timestamp = '" . $cursor['v'] . "' AND page.page_id > '" . $cursor['i'] . "'))";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_page_select() . $where_sql . "
		ORDER BY page.page_timestamp ASC, page.page_id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode($last['page_timestamp'], $last['page_id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM page"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$out = array();

	foreach ($rows as $row) {

		// The findings are left out of a listing on purpose: they are a query
		// per page, and a client walking a site reads them for the handful it
		// decided to work on.
		$out[] = api_page_present($row, false);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_pages_get($params) {

	$row = api_row(api_page_select() . " WHERE page.page_id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Page'));

	}

	api_ok(api_page_present($row, true));

}

function api_pages_update($params) {

	api_seo_library();

	$id = (int)$params['id'];

	$noindex_ready = pg_page_noindex_ready();

	$existing = api_row("SELECT page_id, page_name, page_type, page_title, page_meta_description,
			page_search, page_search_keywords, sitemap, seo_analysis_current,
			" . ($noindex_ready ? "noindex, nofollow" : "'0' AS noindex, '0' AS nofollow") . "
		FROM page
		WHERE page_id = '" . $id . "'
		LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Page'));

	}

	$set = array();

	$touched_score = false;

	if (array_key_exists('title', $params)) {

		$set[] = "page_title = '" . escape($params['title']) . "'";

		$touched_score = true;

	}

	if (array_key_exists('meta_description', $params)) {

		$set[] = "page_meta_description = '" . escape($params['meta_description']) . "'";

		$touched_score = true;

	}

	$search = (int)$existing['page_search'];

	if (array_key_exists('search', $params)) {

		$search = $params['search'] ? 1 : 0;

		$set[] = "page_search = '" . $search . "'";

		$touched_score = true;

	}

	$keywords = (string)$existing['page_search_keywords'];

	if (array_key_exists('search_keywords', $params)) {

		$keywords = api_page_keywords_in($params['search_keywords'], 'search_keywords');

		$set[] = "page_search_keywords = '" . escape($keywords) . "'";

	}

	// The three indexing switches are decided together, because they contradict
	// one another one way and depend on one another the other way.
	$noindex = (int)$existing['noindex'];

	$nofollow = (int)$existing['nofollow'];

	$sitemap = (int)$existing['sitemap'];

	if ((array_key_exists('noindex', $params) || array_key_exists('nofollow', $params)) && !$noindex_ready) {

		api_fail_validation(
			lang('This site has not been upgraded to the version that carries the noindex setting.'),
			array_key_exists('noindex', $params) ? 'noindex' : 'nofollow');

	}

	if (array_key_exists('noindex', $params)) {

		$noindex = $params['noindex'] ? 1 : 0;

	}

	if (array_key_exists('nofollow', $params)) {

		$nofollow = $params['nofollow'] ? 1 : 0;

	}

	if (array_key_exists('sitemap', $params)) {

		$sitemap = $params['sitemap'] ? 1 : 0;

	}

	// A page closed to search engines has no business in the site map. The page
	// screen forces that quietly; here a request that asks for both is refused,
	// because a client that sent sitemap true and was answered false would have
	// no way of telling that from a failure to save.
	if (($noindex === 1) && ($sitemap === 1) && array_key_exists('sitemap', $params) && $params['sitemap']) {

		api_fail_validation(
			lang('A page that is closed to search engines cannot be in the site map.'),
			'sitemap');

	}

	if (($nofollow === 1) && ($noindex === 0)) {

		api_fail_validation(
			lang('nofollow only applies to a page that is closed to search engines.'),
			'nofollow');

	}

	if (($sitemap === 1) && !in_array($existing['page_type'], api_page_sitemap_types(), true)) {

		api_fail_validation(lang(array(
			'string' => 'A page of type {var:1} cannot be included in the site map.',
			'vars'   => $existing['page_type']
		)), 'sitemap');

	}

	if ($noindex === 1) {

		$sitemap = 0;

		$nofollow = ($nofollow === 1) ? 1 : 0;

	} else {

		$nofollow = 0;

	}

	if (array_key_exists('sitemap', $params) || (array_key_exists('noindex', $params) && ($sitemap !== (int)$existing['sitemap']))) {

		$set[] = "sitemap = '" . $sitemap . "'";

		$touched_score = true;

	}

	if ($noindex_ready && (array_key_exists('noindex', $params) || array_key_exists('nofollow', $params))) {

		$set[] = "noindex = '" . $noindex . "'";

		$set[] = "nofollow = '" . $nofollow . "'";

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$app = api_current_app();

	// The meta half of the score is read off these columns, so the record is
	// marked stale in the same statement that changes them. The structure half
	// is not affected by anything written here and keeps its own freshness.
	if ($touched_score && ((int)$existing['seo_analysis_current'] === 1)) {

		$set[] = "seo_analysis_current = '0'";

	}

	api_dry_run_stop('updated', 'page', array('id' => $id, 'fields' => count($set)));

	api_exec("UPDATE page SET " . implode(', ', $set) . ",
			page_timestamp = UNIX_TIMESTAMP(),
			page_user = '" . (int)$app['owner']['id'] . "'
		WHERE page_id = '" . $id . "'
		LIMIT 1");

	// Site search keeps its own index of the promoted keywords, and it is
	// maintained by comparing the old list with the new one. Skipping this
	// leaves the site searchable by words the page no longer carries.
	update_tag_cloud_keywords_for_page(
		$id,
		$search,
		$keywords,
		$existing['page_search'],
		$existing['page_search_keywords']);

	// Score it now rather than leaving it to the nightly job: every column the
	// meta half reads was just written, and a client that reads the page back
	// in the next call should see the number its own change produced.
	if (pg_seo_schema_ready()) {

		pg_seo_recalculate('page', array($id));

	}

	log_activity(lang(array(
		'string' => 'Page ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($existing['page_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_webhook_enqueue('page.updated', array('id' => $id, 'name' => $existing['page_name']));

	$row = api_row(api_page_select() . " WHERE page.page_id = '" . $id . "' LIMIT 1");

	api_ok(api_page_present($row, true));

}

function api_pages_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$permanent = !empty($params['permanent']);

	$page = api_row("SELECT page_id, page_name, page_folder, page_type, page_home
		FROM page
		WHERE page_id = '" . $id . "'
		LIMIT 1");

	if ($page === null) {

		api_fail_not_found(lang('Page'));

	}

	// Two refusals the panel does not make. An operator deleting the home page
	// or the shopping cart page is making a decision; an application doing it
	// is an accident with no undo.
	if ($page['page_home'] === 'yes') {

		api_fail(409, 'home_page_protected', lang('The home page cannot be deleted through the API.'));

	}

	if (in_array($page['page_type'], api_page_system_types(), true)) {

		api_fail(409, 'system_page_protected', lang(array(
			'string' => 'A page of type {var:1} is part of how the site works and cannot be deleted through the API.',
			'vars'   => $page['page_type']
		)));

	}

	// Folder rights, judged for the account that owns the application rather
	// than for a signed-in operator: this entry point has no session.
	if (!pg_folder_edit_access($page['page_folder'], $app['owner']['id'], $app['owner']['role'])) {

		api_fail(403, 'forbidden', lang('The account that owns this application cannot edit this folder.'));

	}

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/view_folder_and_files_f.php');

	if (!$permanent) {

		// The bin is the default. The page keeps its row and its content, stops
		// being served, and an operator can put it back.
		if (!pg_recycle_ready()) {

			api_fail(409, 'recycle_bin_unavailable', lang('This site has no recycle bin. Send permanent=true to delete the page outright.'));

		}

		$bin_id = (int)pg_recycle_folder_id(true);

		if ($bin_id <= 0) {

			api_fail(409, 'recycle_bin_unavailable', lang('This site has no recycle bin. Send permanent=true to delete the page outright.'));

		}

		if (pg_recycle_is_inside($page['page_folder'])) {

			api_fail(409, 'already_recycled', lang('This page is already in the recycle bin.'));

		}

		api_dry_run_stop('recycled', 'page', array('id' => $id, 'name' => $page['page_name'], 'folder_id' => $bin_id));

		api_exec("UPDATE page SET
				page_folder = '" . $bin_id . "',
				page_timestamp = UNIX_TIMESTAMP(),
				page_user = '" . (int)$app['owner']['id'] . "'
			WHERE page_id = '" . $id . "'
			LIMIT 1");

		// The address goes out of circulation with the page, so a new page can
		// take the name while this one waits in the bin.
		pg_recycle_park_name('page', $id, array('id' => (int)$app['owner']['id']));

		api_exec("DELETE FROM recycle_bin WHERE (item_type = 'page') AND (item_id = '" . $id . "')");

		api_exec("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
			VALUES ('page', '" . $id . "', '" . (int)$page['page_folder'] . "', UNIX_TIMESTAMP(), '" . (int)$app['owner']['id'] . "')");

		log_activity(lang(array(
			'string' => 'Page ({var:1}) was moved to the recycle bin through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($page['page_name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

		api_ok(array(
			'id'        => $id,
			'deleted'   => false,
			'recycled'  => true,
			'folder_id' => $bin_id
		));

	}

	// Outright deletion, with the whole cleanup the pages screen performs:
	// regions, form tables, comments and their attachments, short links, the
	// stored SEO findings and the layout file. The owner's role is what the
	// refusals inside are judged against; pages:write is only ever issued to a
	// designer or above, which is past the role the page delete right applies
	// to.
	// The refusals above - the home page, a system page, the folder right -
	// are the whole of what can be checked without deleting. What the cleanup
	// itself would refuse is not knowable until it runs.
	api_dry_run_stop('deleted', 'page', array('id' => $id, 'name' => $page['page_name']));

	$result = pg_delete_page_record($id, array(
		'id'           => (int)$app['owner']['id'],
		'role'         => (int)$app['owner']['role'],
		'delete_pages' => true
	));

	if (!is_array($result) || ($result['status'] !== 'success')) {

		$message = (is_array($result) && ($result['message'] !== '')) ? $result['message'] : lang('This page could not be deleted.');

		api_fail(409, 'page_delete_refused', $message);

	}

	log_activity(lang(array(
		'string' => 'Page ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($page['page_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array(
		'id'       => $id,
		'deleted'  => true,
		'recycled' => false
	));

}

// What api_page_present() returns, declared for the OpenAPI document.
function api_page_schema() {

	return array(
		'id'               => 'integer',
		'name'             => 'string',
		'url'              => 'string',
		'folder_id'        => 'integer',
		'type'             => 'string',
		'home'             => 'boolean',
		'title'            => 'string',
		'meta_description' => 'string',
		'search'           => 'boolean',
		'search_keywords'  => 'string[]',
		'sitemap'          => 'boolean',
		'noindex'          => 'boolean',
		'nofollow'         => 'boolean',
		'layout_type'      => 'string',
		'updated_at'       => 'string?',
		'updated_at_unix'  => 'integer',
		'seo'              => 'Seo'
	);

}
