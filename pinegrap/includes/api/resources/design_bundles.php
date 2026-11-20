<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Release bundles of the visual designer (includes/fn/page_bundles.php): a
// named list of drafts, new pages and replacements of live pages, published
// and taken back together. An application reads them, adds and removes
// pages, publishes and takes back, through the same functions as the
// Bundles screen (page_bundles.php); making, renaming, timing and deleting a
// bundle stay on the screen.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// A bundle's page. $changed is whether a waiting replacement's live page was
// published by hand since the copy, null when it was not asked. Pure.
function api_design_bundle_page_present($item, $changed = null) {

	$live = (int)$item['live_page_id'];

	return array(
		'id'            => (int)$item['id'],
		'kind'          => ($live > 0) ? 'replacement' : 'new',
		'draft_page_id' => (int)$item['draft_page_id'],
		'draft_name'    => isset($item['draft_name']) ? (string)$item['draft_name'] : '',
		'live_page_id'  => ($live > 0) ? $live : null,
		'live_name'     => (($live > 0) && isset($item['live_name'])) ? (string)$item['live_name'] : null,
		'status'        => (string)$item['status'],
		'changed_since' => ($changed === null) ? null : (bool)$changed,
		'added_at'      => api_time(isset($item['added_at']) ? $item['added_at'] : 0),
		'written_at'    => api_time(isset($item['written_at']) ? $item['written_at'] : 0),
	);

}

function api_design_bundle_page_schema() {

	return array(
		'id'            => 'integer',
		'kind'          => 'string',
		'draft_page_id' => 'integer',
		'draft_name'    => 'string',
		'live_page_id'  => 'integer?',
		'live_name'     => 'string?',
		'status'        => 'string',
		'changed_since' => 'boolean?',
		'added_at'      => 'datetime?',
		'written_at'    => 'datetime?',
	);

}

// A bundle row. $pages are api_design_bundle_page_present() answers, null in
// a list (one bundle's pages are read with it alone). Pure.
function api_design_bundle_present($row, $pages = null) {

	$user = function ($id) {
		return ((int)$id > 0) ? (int)$id : null;
	};

	return array(
		'id'             => (int)$row['id'],
		'name'           => (string)$row['name'],
		'status'         => (string)$row['status'],
		'page_count'     => is_array($pages) ? count($pages) : (int)(isset($row['item_count']) ? $row['item_count'] : 0),
		'publish_at'     => api_time($row['publish_at']),
		'scheduled_by'   => $user($row['scheduled_by']),
		'created_by'     => $user($row['created_by']),
		'created_at'     => api_time($row['created_at']),
		'updated_at'     => api_time($row['updated_at']),
		'published_by'   => $user($row['published_by']),
		'published_at'   => api_time($row['published_at']),
		'reverted_by'    => $user($row['reverted_by']),
		'reverted_at'    => api_time($row['reverted_at']),
		'failed_page_id' => $user($row['failed_page_id']),
		'failed_reason'  => ((string)$row['failed_reason'] !== '') ? (string)$row['failed_reason'] : null,
		'pages'          => is_array($pages) ? array_values($pages) : null,
	);

}

function api_design_bundle_schema() {

	return array(
		'id'             => 'integer',
		'name'           => 'string',
		'status'         => 'string',
		'page_count'     => 'integer',
		'publish_at'     => 'datetime?',
		'scheduled_by'   => 'integer?',
		'created_by'     => 'integer?',
		'created_at'     => 'datetime?',
		'updated_at'     => 'datetime?',
		'published_by'   => 'integer?',
		'published_at'   => 'datetime?',
		'reverted_by'    => 'integer?',
		'reverted_at'    => 'datetime?',
		'failed_page_id' => 'integer?',
		'failed_reason'  => 'string?',
		'pages'          => 'DesignBundlePage[]?',
	);

}

// The choices of a publish for pages changed since, item id => replace or
// leave, from the two lists sent; an id in both is a 422 naming it. Pure.
function api_design_bundle_choices($replace, $leave) {

	$choices = array();

	foreach ((array)$replace as $id) {
		$choices[(int)$id] = 'replace';
	}

	foreach ((array)$leave as $id) {

		if (isset($choices[(int)$id])) {
			return array('error' => (int)$id, 'choices' => array());
		}

		$choices[(int)$id] = 'leave';

	}

	return array('error' => 0, 'choices' => $choices);

}

// What stops a publish plan (pg_page_bundle_publish_plan()): the status, code
// and pages of its refusal, or null when it may be written. A page open in
// the editor comes first, then a page the save refuses, then a page changed
// since with no choice sent. Pure.
function api_design_bundle_publish_refusal($plan) {

	$by_state = array('locked' => array(), 'refused' => array(), 'changed' => array());

	foreach ((array)$plan['pages'] as $page) {

		if (isset($by_state[$page['state']])) {
			$by_state[$page['state']][] = $page;
		}

	}

	$codes = array(
		'locked'  => 'page_open_in_editor',
		'refused' => 'bundle_page_refused',
		'changed' => 'bundle_changed_since',
	);

	foreach ($codes as $state => $code) {

		if ($by_state[$state]) {
			return array('code' => $code, 'pages' => $by_state[$state]);
		}

	}

	if (empty($plan['ok'])) {
		return array('code' => 'bundle_empty', 'pages' => array());
	}

	return null;

}

// The pages of a refusal as the error's errors list: field the item id, code
// the page's state, message its name and reason. Pure.
function api_design_bundle_page_errors($pages) {

	$out = array();

	foreach ((array)$pages as $page) {

		$text = isset($page['text']) ? trim((string)$page['text']) : '';

		$out[] = array(
			'field'   => (string)(int)$page['item_id'],
			'code'    => (string)$page['state'],
			'message' => (string)$page['name'] . (($text !== '') ? ': ' . $text : ''),
		);

	}

	return $out;

}

// The pages a take-back cannot bring back (pg_page_bundle_take_back_plan()'s
// cannot) as the error's errors list, with their reason. Pure.
function api_design_bundle_kept_errors($cannot) {

	$out = array();

	foreach ((array)$cannot as $page) {

		$out[] = array(
			'field'   => (string)(int)$page['item_id'],
			'code'    => (string)$page['reason'],
			'message' => (string)$page['name'] . ': ' . pg_page_bundle_take_back_reason_text($page['reason']),
		);

	}

	return $out;

}

function api_design_bundles_ready() {

	if (!function_exists('pg_page_bundles_ready') || !pg_page_bundles_ready()) {

		api_fail(503, 'service_unavailable', lang('Release bundles are not available yet. Please run the upgrade.'));

	}

}

function api_design_bundle_or_fail($id) {

	$bundle = pg_page_bundle_get($id);

	if (!$bundle) {

		api_fail_not_found(lang('Release bundle'));

	}

	return $bundle;

}

// One bundle with its pages; a waiting replacement says whether its live page
// changed since the copy.
function api_design_bundle_answer($bundle_id) {

	$bundle = api_design_bundle_or_fail($bundle_id);

	$pages = array();

	foreach (pg_page_bundle_items($bundle['id']) as $item) {

		$changed = (((string)$item['status'] === 'waiting') && ((int)$item['live_page_id'] > 0))
			? pg_page_bundle_item_changed($item)
			: null;

		$pages[] = api_design_bundle_page_present($item, $changed);

	}

	return api_design_bundle_present($bundle, $pages);

}

// The application's owner as the bundle functions take a person, refused
// unless they may make and publish bundles (roles 0 and 1).
function api_design_bundle_user($message) {

	$app = api_design_designer_or_fail($message);

	$user = api_design_user_of($app['owner']);

	if (!pg_page_bundle_user_may($user)) {

		api_fail(403, 'forbidden', $message);

	}

	return $user;

}

// Runs a bundle write in the owner's name, as the page save logs through the
// session, and turns "another publish is running" into 409 bundle_busy.
function api_design_bundle_run($user, $action) {

	$done = pg_page_bundle_as_person($user, $action);

	// Deleted on the Bundles screen while this request waited.
	if (!empty($done['gone'])) {

		api_fail_not_found(lang('Release bundle'));

	}

	if (!empty($done['busy'])) {

		api_fail(409, 'bundle_busy', $done['error']);

	}

	return $done;

}

function api_design_bundles_list($params) {

	api_design_bundles_ready();

	$rows = pg_page_bundle_list();

	if (isset($params['status'])) {

		$rows = array_values(array_filter($rows, function ($row) use ($params) {
			return (string)$row['status'] === (string)$params['status'];
		}));

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$rows = array_values(array_filter($rows, function ($row) use ($cursor) {
			return (int)$row['id'] < (int)$cursor['i'];
		}));

	}

	$limit = (int)$params['limit'];

	$next = null;

	if (count($rows) > $limit) {

		$rows = array_slice($rows, 0, $limit);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_bundle_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_design_bundles_get($params) {

	api_design_bundles_ready();

	api_ok(api_design_bundle_answer($params['id']));

}

function api_design_bundles_pages_add($params) {

	api_design_bundles_ready();

	$bundle = api_design_bundle_or_fail($params['id']);

	$user = api_design_bundle_user(lang('Only administrators and designers can change a release bundle.'));

	$live = isset($params['live_page_id']) ? (int)$params['live_page_id'] : 0;

	$draft = isset($params['draft_page_id']) ? (int)$params['draft_page_id'] : 0;

	if (($live > 0) === ($draft > 0)) {

		api_fail_validation(lang('Send either live_page_id, to add a copy of a live page, or draft_page_id, to add a draft.'), ($live > 0) ? 'draft_page_id' : 'live_page_id');

	}

	if ($live > 0) {

		$checked = pg_page_bundle_add_copy_check($bundle, $live);

		$error = $checked['error'];

	} else {

		$error = pg_page_bundle_add_draft_check($bundle, $draft);

	}

	if ($error !== '') {

		api_fail(409, 'bundle_page_refused', $error, ($live > 0) ? 'live_page_id' : 'draft_page_id');

	}

	api_dry_run_stop('added', 'design_bundle_page', array(
		'bundle_id'     => (int)$bundle['id'],
		'kind'          => ($live > 0) ? 'replacement' : 'new',
		'live_page_id'  => ($live > 0) ? $live : null,
		'draft_page_id' => ($draft > 0) ? $draft : null,
	));

	$done = api_design_bundle_run($user, function () use ($bundle, $live, $draft, $user) {
		return ($live > 0)
			? pg_page_bundle_add_copy($bundle, $live, $user)
			: pg_page_bundle_add_draft($bundle, $draft, $user);
	});

	if (!$done['ok']) {

		api_fail(409, 'bundle_page_refused', $done['error'], ($live > 0) ? 'live_page_id' : 'draft_page_id');

	}

	api_ok(api_design_bundle_answer($bundle['id']), 201);

}

function api_design_bundles_pages_remove($params) {

	api_design_bundles_ready();

	$bundle = api_design_bundle_or_fail($params['id']);

	$user = api_design_bundle_user(lang('Only administrators and designers can change a release bundle.'));

	if (!pg_page_bundle_may_change($bundle['status'])) {

		api_fail(409, 'bundle_state', lang('A published bundle cannot be changed.'));

	}

	$found = false;

	foreach (pg_page_bundle_items($bundle['id']) as $item) {

		if (((int)$item['id'] === (int)$params['page_id']) && ((string)$item['status'] === 'waiting')) {
			$found = true;
		}

	}

	if (!$found) {

		api_fail_not_found(lang('Page of the bundle'));

	}

	api_dry_run_stop('removed', 'design_bundle_page', array('bundle_id' => (int)$bundle['id'], 'id' => (int)$params['page_id']));

	$done = api_design_bundle_run($user, function () use ($bundle, $params, $user) {
		return pg_page_bundle_remove_item($bundle, $params['page_id'], $user);
	});

	if (!$done['ok']) {

		api_fail(409, 'bundle_state', $done['error']);

	}

	api_ok(api_design_bundle_answer($bundle['id']));

}

function api_design_bundles_publish($params) {

	api_design_bundles_ready();

	$bundle = api_design_bundle_or_fail($params['id']);

	$user = api_design_bundle_user(lang('Only administrators and designers can publish a release bundle.'));

	if (!pg_page_bundle_holds($bundle['status'])) {

		api_fail(409, 'bundle_state', lang('The bundle cannot make that change now.'));

	}

	$sent = api_design_bundle_choices(
		isset($params['replace']) ? $params['replace'] : array(),
		isset($params['leave']) ? $params['leave'] : array());

	if ($sent['error'] > 0) {

		api_fail_validation(lang(array('string' => 'The page {var:1} is both in replace and in leave; send it in one of them.', 'vars' => array((string)$sent['error']))), 'leave');

	}

	$check = pg_page_bundle_publish_check($bundle, $user, $sent['choices']);

	$refusal = api_design_bundle_publish_refusal($check['plan']);

	if ($refusal !== null) {

		$messages = array(
			'page_open_in_editor'  => lang('Somebody has a page of this bundle open in the Page Design Studio. Try again when they are done.'),
			'bundle_page_refused'  => lang('Some pages of this bundle cannot be published as they are; nothing was published.'),
			'bundle_changed_since' => lang('Some pages were published by hand since they were copied into the bundle. Send each one in replace (Replace anyway) or leave (Leave out).'),
			'bundle_empty'         => ($check['error'] !== '') ? $check['error'] : lang('The bundle has no page to publish.'),
		);

		api_fail(409, $refusal['code'], $messages[$refusal['code']], null, api_design_bundle_page_errors($refusal['pages']));

	}

	api_dry_run_stop('published', 'design_bundle', array(
		'id'    => (int)$bundle['id'],
		'write' => array_values(array_map('intval', $check['plan']['write'])),
		'leave' => array_values(array_map('intval', $check['plan']['leave'])),
		'pages' => array_values($check['plan']['pages']),
	));

	$done = api_design_bundle_run($user, function () use ($bundle, $user, $sent) {
		return pg_page_bundle_publish($bundle, $user, $sent['choices']);
	});

	if (!$done['ok']) {

		// Somebody opened a page or saved one between the check and the write.
		$refusal = isset($done['check']['plan']) ? api_design_bundle_publish_refusal($done['check']['plan']) : null;

		$pages = ($refusal !== null) ? api_design_bundle_page_errors($refusal['pages']) : null;

		api_fail(409, ($done['outcome'] === '') && ($refusal !== null) ? $refusal['code'] : 'bundle_publish_failed', $done['error'], null, $pages);

	}

	api_ok(api_design_bundle_answer($bundle['id']));

}

function api_design_bundles_take_back($params) {

	api_design_bundles_ready();

	$bundle = api_design_bundle_or_fail($params['id']);

	$user = api_design_bundle_user(lang('Only administrators and designers can take back a release bundle.'));

	$check = pg_page_bundle_take_back_check($bundle);

	if ($check['error'] !== '') {

		api_fail(409, 'bundle_state', $check['error']);

	}

	$kept = array_values(array_map('intval', isset($params['kept']) ? (array)$params['kept'] : array()));

	if (!pg_page_bundle_take_back_allowed($check['plan'], $kept)) {

		$message = empty($check['plan']['can'])
			? lang('No page of this bundle can be taken back.')
			: lang('Some pages cannot be taken back. Send each of them in kept to take back the rest.');

		api_fail(409, 'bundle_pages_kept', $message, 'kept', api_design_bundle_kept_errors($check['plan']['cannot']));

	}

	// A page a take-back writes would be put back by the next save of an
	// editor tab that has it open.
	require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

	$open = array();

	foreach ($check['plan']['can'] as $id) {

		$item = $check['items'][$id];

		$page_id = ((int)$item['live_page_id'] > 0) ? (int)$item['live_page_id'] : (int)$item['draft_page_id'];

		if (!pg_collab_may_edit_page('', $page_id)) {

			$open[] = array('field' => (string)(int)$id, 'code' => 'locked', 'message' => ((int)$item['live_page_id'] > 0) ? (string)$item['live_name'] : (string)$item['draft_name']);

		}

	}

	if ($open) {

		api_fail(409, 'page_open_in_editor', lang('Somebody has a page of this bundle open in the Page Design Studio. Try again when they are done.'), null, $open);

	}

	api_dry_run_stop('taken_back', 'design_bundle', array(
		'id'   => (int)$bundle['id'],
		'can'  => array_values(array_map('intval', $check['plan']['can'])),
		'kept' => api_design_bundle_kept_errors($check['plan']['cannot']),
	));

	$done = api_design_bundle_run($user, function () use ($bundle, $user, $kept) {
		return pg_page_bundle_take_back($bundle, $user, $kept);
	});

	if (!$done['ok']) {

		api_fail(409, 'bundle_take_back_failed', $done['error']);

	}

	api_ok(api_design_bundle_answer($bundle['id']));

}
