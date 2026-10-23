<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What the SEO analysis found, across the whole site, and the addresses
// visitors asked for that nothing answers (the 404 log,
// includes/fn/not_found_log.php).
//
// The findings are already on every record this API returns - a page, a product
// and a group each carry their own seo block with their own issues. What was
// missing is the other way round: "show me everything that is wrong", which is
// how somebody actually works through them. A site with four hundred pages
// cannot be audited one GET at a time.
//
// Read-only, and it stays that way: a finding is produced by the analyser, and
// the way to clear one is to fix what it is about - through the page, product
// or group endpoint - and let the next analysis run notice.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

// One finding, with enough of the record attached to act on it.
function api_seo_issue_present($row, $names) {

	$type = (string)$row['entity_type'];

	$id = (int)$row['entity_id'];

	$key = $type . ':' . $id;

	return array(
		'entity_type' => $type,
		'entity_id'   => $id,
		'name'        => isset($names[$key]) ? $names[$key]['name'] : null,
		'url'         => isset($names[$key]) ? $names[$key]['url'] : null,
		'code'        => (string)$row['code'],
		'severity'    => (string)$row['severity'],
		// How many times the same thing was found on that record: eleven images
		// with no alt text is one finding with eleven occurrences, not eleven
		// findings.
		'occurrences' => (int)$row['occurrences'],
		// Which pass wrote it - the document itself, its links, its structured
		// data - so a caller can work through one kind at a time.
		'source'      => (string)$row['source'],
		// Whatever the check had to say beyond its code: a percentage, a word
		// count, the offending address. Free text by design.
		'detail'      => (string)$row['detail']
	);

}

// The names and addresses of the records a page of findings points at, read one
// query per kind rather than one per finding.
function api_seo_issue_names($rows) {

	$wanted = array('page' => array(), 'product' => array(), 'product_group' => array());

	foreach ($rows as $row) {

		$type = (string)$row['entity_type'];

		if (isset($wanted[$type])) {

			$wanted[$type][(int)$row['entity_id']] = true;

		}

	}

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$names = array();

	if (!empty($wanted['page'])) {

		foreach (api_rows("SELECT page_id, page_name FROM page
			WHERE page_id IN (" . implode(',', array_keys($wanted['page'])) . ")") as $row) {

			$names['page:' . (int)$row['page_id']] = array(
				'name' => $row['page_name'],
				'url'  => $base . encode_url_path($row['page_name'])
			);

		}

	}

	if (!empty($wanted['product'])) {

		foreach (api_rows("SELECT id, name FROM products
			WHERE id IN (" . implode(',', array_keys($wanted['product'])) . ")") as $row) {

			$names['product:' . (int)$row['id']] = array('name' => $row['name'], 'url' => null);

		}

	}

	if (!empty($wanted['product_group'])) {

		foreach (api_rows("SELECT id, name FROM product_groups
			WHERE id IN (" . implode(',', array_keys($wanted['product_group'])) . ")") as $row) {

			$names['product_group:' . (int)$row['id']] = array('name' => $row['name'], 'url' => null);

		}

	}

	return $names;

}

function api_seo_issues_list($params) {

	// The analyser's own library, loaded the same way the page and product
	// endpoints load it - it is a root file, not part of the API's own set.
	api_seo_library();

	// The findings table arrives with an upgrade. On an installation that has
	// not run it there is nothing to report, which is not the same as an error.
	if (!function_exists('pg_seo_structure_schema_ready') || !pg_seo_structure_schema_ready()) {

		api_ok_list(array(), 0, null);

	}

	$where = array();

	if (isset($params['entity_type']) && $params['entity_type'] !== '') {

		$where[] = "entity_type = '" . escape($params['entity_type']) . "'";

	}

	if (isset($params['entity_id']) && ((int)$params['entity_id'] > 0)) {

		$where[] = "entity_id = '" . (int)$params['entity_id'] . "'";

	}

	if (isset($params['severity']) && $params['severity'] !== '') {

		$where[] = "severity = '" . escape($params['severity']) . "'";

	}

	if (isset($params['code']) && $params['code'] !== '') {

		$where[] = "code = '" . escape($params['code']) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id > '" . $cursor['i'] . "'";

	}

	$where_sql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows("SELECT id, entity_type, entity_id, code, severity, occurrences, source, detail
		FROM seo_issue" . $where_sql . "
		ORDER BY id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$names = api_seo_issue_names($rows);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_seo_issue_present($row, $names);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// One address of the 404 log. path is a short link name as it is stored, so it
// is the name to give POST /short-links to make the address a redirect; what
// answers it now, if anything, is in answered_by. path and referrer are what
// visitors asked for and came from: text from outside the site.
function api_seo_not_found_present($row, $base, $answers) {

	$path = (string)$row['path'];

	return array(
		'id'           => (int)$row['id'],
		'path'         => $path,
		'url'          => $base . encode_url_path($path),
		'hits'         => (int)$row['hit_count'],
		'crawler_hits' => (int)$row['crawler_hits'],
		'referrer'     => ((string)$row['referrer'] !== '') ? (string)$row['referrer'] : null,
		'answered_by'  => api_seo_not_found_answered_by(isset($answers[$path]) ? $answers[$path] : null),
		'first_seen'   => api_time($row['first_seen']),
		'last_seen'    => api_time($row['last_seen'])
	);

}

// What answers a logged address now, as the API names it, from
// pg_not_found_log_answers(): null while nothing does.
function api_seo_not_found_answered_by($answer) {

	$names = array('redirect' => 'short_link', 'pattern' => 'short_link_pattern', 'binned' => 'recycled_short_link', 'page' => 'page', 'file' => 'file');

	return (is_array($answer) && isset($names[$answer['state']])) ? $names[$answer['state']] : null;

}

// A cursor of GET /seo/not-found starts with the sort it was made for (h., l.
// or f.; base64url has no dot): its value is a hit count or a time, and read
// under another sort it would end the list early or start it again.
function api_seo_not_found_cursor_mark($sort) {

	$marks = array('hits' => 'h.', 'last_seen' => 'l.', 'first_seen' => 'f.');

	return isset($marks[$sort]) ? $marks[$sort] : 'h.';

}

// The cursor without its sort mark, or null when it was made for another sort.
function api_seo_not_found_cursor_parts($cursor, $sort) {

	$cursor = (string)$cursor;

	$mark = api_seo_not_found_cursor_mark($sort);

	return (strpos($cursor, $mark) === 0) ? (string)substr($cursor, strlen($mark)) : null;

}

// GET /seo/not-found: the 404 log, keyset paged on the sort the caller chose.
function api_seo_not_found_list($params) {

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	// The table arrives with the 2026.11.0 step (11.0.30); before it there is
	// nothing to report, which is not an error.
	if (!function_exists('pg_not_found_log_ready') || !pg_not_found_log_ready()) {

		api_ok_list(array(), $limit, null);

	}

	$sort = isset($params['sort']) ? (string)$params['sort'] : 'hits';

	$cursor = null;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$parts = api_seo_not_found_cursor_parts($params['cursor'], $sort);

		$cursor = ($parts === null) ? null : api_cursor_decode($parts);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$rows = api_rows(pg_not_found_log_page_sql($sort, isset($params['since']) ? (int)$params['since'] : 0, $cursor, $limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	$answers = pg_not_found_log_answers(array_column($rows, 'path'));

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_seo_not_found_present($row, $base, $answers);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_seo_not_found_cursor_mark($sort) . api_cursor_encode((int)$last[pg_not_found_log_sort_column($sort)], (int)$last['id']);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// What api_seo_not_found_present() returns, declared for the OpenAPI document.
function api_seo_not_found_schema() {

	return array(
		'id'           => 'integer',
		'path'         => 'string',
		'url'          => 'string',
		'hits'         => 'integer',
		'crawler_hits' => 'integer',
		'referrer'     => 'string?',
		'answered_by'  => 'string?',
		'first_seen'   => 'string',
		'last_seen'    => 'string'
	);

}

// What api_seo_issue_present() returns, declared for the OpenAPI document.
function api_seo_issue_schema() {

	return array(
		'entity_type' => 'string',
		'entity_id'   => 'integer',
		'name'        => 'string?',
		'url'         => 'string?',
		'code'        => 'string',
		'severity'    => 'string',
		'occurrences' => 'integer',
		'source'      => 'string',
		'detail'      => 'string'
	);

}
