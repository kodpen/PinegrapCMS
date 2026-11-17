<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Content review over the API: the pages and collection records that have a
// person and a "review by" date, with the Content review screen's filters
// (view_content_review.php), and one item's person, date, interval and
// "reviewed".
//
// The list reads the screen's own branches (pg_content_review_list_branches(),
// includes/fn/content_review_list.php) and pages each by its item table's
// key, so no branch is sorted whole; the writes go through the rules of
// includes/fn/content_review.php. A user-role (3) owner reads and marks only
// the items they are the person of, as on the screen.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review_list.php');

// The filters a role may ask for, pure: the screen's.
function api_content_review_filters($role) {

	return array_keys(pg_content_review_list_filters((int)$role));

}

// The item key a branch of pg_content_review_list_branches() pages by, and
// its kind, pure. A branch that drives from content_review pages by
// cr.item_id (its primary key with item_kind); an undated branch by its
// item table's key. null for a branch this reader does not know.
function api_content_review_branch_key($sql) {

	$sql = (string)$sql;

	if (strpos($sql, 'FROM content_review AS cr JOIN page ') !== false) {

		return array('column' => 'cr.item_id', 'kind' => 'page');

	}

	if (strpos($sql, 'FROM content_review AS cr JOIN forms ') !== false) {

		return array('column' => 'cr.item_id', 'kind' => 'record');

	}

	if (strpos($sql, ' FROM page LEFT JOIN content_review ') !== false) {

		return array('column' => 'page.page_id', 'kind' => 'page');

	}

	if (strpos($sql, ' FROM forms JOIN custom_form_pages ') !== false) {

		return array('column' => 'forms.id', 'kind' => 'record');

	}

	return null;

}

// The smallest item id of a kind whose list id (pg_content_review_list_id())
// comes after a cursor's, pure: a page's list id is id * 2, a record's
// id * 2 + 1.
function api_content_review_first_item($kind, $after) {

	$after = max(0, (int)$after);

	return ($kind === 'record') ? intdiv($after + 1, 2) : intdiv($after, 2) + 1;

}

// The statements of one page of the list, pure: each branch narrowed to the
// items after the cursor (or to one item), in its key's order, at most
// $limit rows each. The caller merges them by row_id and keeps $limit.
//
// $only: array('kind', 'item_id') for one item, null for a page.
function api_content_review_page_sql(array $branches, $kind, $after, $limit, $only = null) {

	$out = array();

	foreach ($branches as $branch) {

		$key = api_content_review_branch_key($branch['sql']);

		if ($key === null || (($kind !== '') && ($key['kind'] !== $kind))) {

			continue;

		}

		if ($only !== null) {

			if ($key['kind'] !== $only['kind']) {

				continue;

			}

			$where = ' AND (' . $key['column'] . ' = ?)';
			$value = (string)(int)$only['item_id'];

		} else {

			$where = ' AND (' . $key['column'] . ' >= ?)';
			$value = (string)api_content_review_first_item($key['kind'], $after);

		}

		$out[] = array(
			'sql'    => $branch['sql'] . $where . ' ORDER BY ' . $key['column'] . ' LIMIT ' . max(1, (int)$limit),
			'params' => array_merge($branch['params'], array($value))
		);

	}

	return $out;

}

// The branches of a filter for an owner, with the parts the screen gives them.
function api_content_review_branches($filter, $owner) {

	require_once(PG_FUNCTIONS_DIR . '/seo.php');

	return pg_content_review_list_branches($filter, pg_content_review_list_condition($filter, pg_content_review_today(), (int)$owner['id'], (int)$owner['role']), array(
		'bin'        => function_exists('pg_recycle_bin_folder_ids') ? pg_recycle_bin_folder_ids() : array(),
		'records'    => function_exists('pg_coll_ready') && pg_coll_ready(),
		'has_tree'   => "'0'",
		'checked_at' => "'0'",
		'traffic'    => '',
		'views'      => 'NULL'
	));

}

// Runs the statements of api_content_review_page_sql() and merges their rows
// by list id.
function api_content_review_rows(array $statements, $limit) {

	$rows = array();

	foreach ($statements as $statement) {

		foreach (api_rows((string)pg_db_bind($statement['sql'], $statement['params'], 'escape')) as $row) {

			$rows[] = $row;

		}

	}

	usort($rows, function ($a, $b) {

		return (int)$a['row_id'] - (int)$b['row_id'];

	});

	return array_slice($rows, 0, max(1, (int)$limit));

}

// One item as the API shows it, pure.
function api_content_review_present($row, $today) {

	$review_on = (isset($row['review_on']) && (string)$row['review_on'] !== '') ? (string)$row['review_on'] : null;

	return array(
		'kind'            => (string)$row['item_kind'],
		'id'              => (int)$row['item_id'],
		'name'            => (string)$row['item_name'],
		'collection'      => ((string)$row['item_kind'] === 'record') ? (string)$row['kind_name'] : null,
		'user_id'         => ((int)$row['user_id'] > 0) ? (int)$row['user_id'] : null,
		'review_on'       => $review_on,
		'state'           => pg_content_review_state($review_on, $today),
		'interval_months' => (int)$row['interval_months'],
		'reviewed_at'     => api_time((int)$row['reviewed_at']),
		'reviewed_by'     => ((int)$row['reviewed_by'] > 0) ? (int)$row['reviewed_by'] : null
	);

}

// What api_content_review_present() returns, declared for the OpenAPI document.
function api_content_review_schema() {

	return array(
		'kind'            => 'string',
		'id'              => 'integer',
		'name'            => 'string',
		'collection'      => 'string?',
		'user_id'         => 'integer?',
		'review_on'       => 'date?',
		'state'           => 'string',
		'interval_months' => 'integer',
		'reviewed_at'     => 'datetime?',
		'reviewed_by'     => 'integer?'
	);

}

// GET /content-review
function api_content_review_list($params) {

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	if (!pg_content_review_ready()) {

		api_ok_list(array(), $limit, null);

	}

	$owner = api_current_app()['owner'];
	$filters = api_content_review_filters((int)$owner['role']);
	$filter = isset($params['filter']) ? (string)$params['filter'] : $filters[0];

	if (!in_array($filter, $filters, true)) {

		api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('filter', implode(', ', $filters)))), 'filter');

	}

	$after = 0;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$after = (int)$cursor['i'];

	}

	$kind = isset($params['kind']) ? (string)$params['kind'] : '';
	$rows = api_content_review_rows(api_content_review_page_sql(api_content_review_branches($filter, $owner), $kind, $after, $limit + 1), $limit + 1);
	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$today = pg_content_review_today();
	$out = array();

	foreach ($rows as $row) {

		$out[] = api_content_review_present($row, $today);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = (int)$rows[count($rows) - 1]['row_id'];
		$next_cursor = api_cursor_encode($last, $last);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// One item as the API shows it, read now; null when it does not count (gone,
// in the Recycle Bin, a record whose form is no collection).
function api_content_review_item($kind, $item_id, $owner) {

	$rows = api_content_review_rows(api_content_review_page_sql(api_content_review_branches('all', array('id' => (int)$owner['id'], 'role' => 0)), '', 0, 1, array('kind' => $kind, 'item_id' => (int)$item_id)), 1);

	return $rows ? $rows[0] : null;

}

// What a body asks of an item, pure: the fields sent, laid over the stored
// row ("only the fields sent change"), and the first refusal. A user-role
// (3) owner may only send reviewed, and only for their own item.
//
// $sent: the validated body (user_id, review_on, interval_months, reviewed).
// $stored: the content_review row or null. Answers array('error' =>
// '' | forbidden | not_found | date | nothing, 'field', 'user_id',
// 'review_on', 'interval_months', 'save' (person, date or interval sent),
// 'reviewed').
function api_content_review_update_plan(array $sent, $stored, $owner_id, $role) {

	$plan = array(
		'error'           => '',
		'field'           => null,
		'user_id'         => $stored ? (int)$stored['user_id'] : 0,
		'review_on'       => ($stored && (string)$stored['review_on'] !== '') ? (string)$stored['review_on'] : null,
		'interval_months' => $stored ? pg_content_review_interval($stored['interval_months']) : 0,
		'save'            => false,
		'reviewed'        => !empty($sent['reviewed'])
	);

	if ((int)$role >= 3) {

		if (!$stored || ((int)$stored['user_id'] !== (int)$owner_id)) {

			$plan['error'] = 'not_found';

			return $plan;

		}

		foreach (array('user_id', 'review_on', 'interval_months') as $field) {

			if (array_key_exists($field, $sent)) {

				$plan['error'] = 'forbidden';
				$plan['field'] = $field;

				return $plan;

			}

		}

	}

	if (array_key_exists('user_id', $sent)) {

		$plan['user_id'] = ($sent['user_id'] === '') ? 0 : max(0, (int)$sent['user_id']);
		$plan['save'] = true;

	}

	if (array_key_exists('review_on', $sent)) {

		$date = ($sent['review_on'] === '') ? null : pg_content_review_parse_date((string)$sent['review_on']);
		$plan['review_on'] = $date ? $date->format('Y-m-d') : null;
		$plan['save'] = true;

		if (($sent['review_on'] !== '') && ($date === null)) {

			$plan['error'] = 'date';
			$plan['field'] = 'review_on';

			return $plan;

		}

	}

	if (array_key_exists('interval_months', $sent)) {

		$plan['interval_months'] = pg_content_review_interval((int)$sent['interval_months']);
		$plan['save'] = true;

	}

	if (!$plan['save'] && !$plan['reviewed']) {

		$plan['error'] = 'nothing';

	}

	return $plan;

}

// POST /content-review/{kind}/{id}
function api_content_review_update($params) {

	$owner = api_current_app()['owner'];

	if (!pg_content_review_ready()) {

		api_fail(503, 'service_unavailable', lang('Content review waits for a database update. Run the update, then try again.'));

	}

	$kind = (string)$params['kind'];
	$item_id = (int)$params['id'];
	$facts = pg_content_review_item_facts($kind, $item_id);

	if (!pg_content_review_item_counts($kind, $facts)) {

		api_fail_not_found(lang('Content review item'));

	}

	$sent = array();

	foreach (array('user_id', 'review_on', 'interval_months', 'reviewed') as $field) {

		if (array_key_exists($field, $params)) {

			$sent[$field] = $params[$field];

		}

	}

	$stored = pg_content_review_get($kind, $item_id);
	$plan = api_content_review_update_plan($sent, $stored, (int)$owner['id'], (int)$owner['role']);

	if ($plan['error'] === 'not_found') {

		api_fail_not_found(lang('Content review item'));

	}

	if ($plan['error'] === 'forbidden') {

		api_fail(403, 'forbidden', lang('A user can only mark their own items reviewed.'), $plan['field']);

	}

	if ($plan['error'] === 'date') {

		api_fail_validation(lang('review_on must be a date written as YYYY-MM-DD.'), 'review_on');

	}

	if ($plan['error'] === 'nothing') {

		api_fail_validation(lang('Send at least one of user_id, review_on, interval_months or reviewed.'));

	}

	if ($plan['save'] && ($plan['user_id'] > 0) && !pg_content_review_user_may_edit($plan['user_id'], $kind, $facts)) {

		api_fail_validation(lang('The person must be a panel user who may edit this item.'), 'user_id');

	}

	api_dry_run_stop('update', 'content_review', array(
		'kind'            => $kind,
		'id'              => $item_id,
		'user_id'         => ($plan['user_id'] > 0) ? $plan['user_id'] : null,
		'review_on'       => $plan['review_on'],
		'interval_months' => $plan['interval_months'],
		'reviewed'        => $plan['reviewed']
	));

	if ($plan['save'] && (pg_content_review_save($kind, $item_id, $plan['user_id'], $plan['review_on'], $plan['interval_months']) !== '')) {

		api_fail(500, 'server_error', lang('The content review could not be saved.'));

	}

	if ($plan['reviewed'] && (pg_content_review_mark_reviewed($kind, $item_id, (int)$owner['id']) !== '')) {

		api_fail(500, 'server_error', lang('The content review could not be saved.'));

	}

	$row = api_content_review_item($kind, $item_id, $owner);

	if (!$row) {

		api_fail_not_found(lang('Content review item'));

	}

	api_ok(api_content_review_present($row, pg_content_review_today()));

}
