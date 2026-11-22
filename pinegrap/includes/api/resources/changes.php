<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GET /changes/deleted: which records were deleted since a moment, read from
 * the audit trail's delete lines (audit_log). A client that keeps its own copy
 * of a list reads the changes with updated_since and the removals here; only
 * the type, the id and the time of a delete are answered.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Every type the route knows, by its API resource name: the audit_log
// entity_type its deletes are written under, the scope that reads it, whether
// its endpoints narrow rows for the user role (3), and whether every path that
// deletes one writes a delete line.
//
// Covered: products (pg_pb_delete_product() is the only DELETE and writes the
// line) and offers (_pg_offer_delete(), likewise). Not covered, so a client
// reads those lists whole again:
//   orders     pg_local_order_discard() deletes an incomplete counter sale
//              with no line;
//   customers  a personal data erasure deletes contacts under one erase line
//              for the person, not one delete line per contact;
//   pages      a design template install deletes the starter pages with no
//              line;
//   files      many paths delete a file row with no line (attachments, form
//              uploads, theme files, signatures, pictures);
//   users      an import replace (includes/import/write_user.php) deletes with
//              no line.
function api_changes_deleted_types() {

	return array(
		'products'  => array('entity' => 'product', 'scope' => 'products:read',  'row_rule' => false, 'covered' => true),
		'offers'    => array('entity' => 'offer',   'scope' => 'offers:read',    'row_rule' => false, 'covered' => true),
		'orders'    => array('entity' => 'order',   'scope' => 'orders:read',    'row_rule' => false, 'covered' => false),
		'customers' => array('entity' => 'contact', 'scope' => 'customers:read', 'row_rule' => true,  'covered' => false),
		'pages'     => array('entity' => 'page',    'scope' => 'pages:read',     'row_rule' => false, 'covered' => false),
		'files'     => array('entity' => 'file',    'scope' => 'files:read',     'row_rule' => false, 'covered' => false),
		'users'     => array('entity' => 'user',    'scope' => 'users:read',     'row_rule' => false, 'covered' => false)
	);

}

// The names GET /meta lists: covered, and known but not covered.
function api_changes_deleted_coverage() {

	$out = array('covered' => array(), 'not_covered' => array());

	foreach (api_changes_deleted_types() as $name => $type) {

		$out[$type['covered'] ? 'covered' : 'not_covered'][] = $name;

	}

	return $out;

}

// The types asked for, as a list of names. Empty input means every covered
// type. Answers array('types' => [...]) or array('error' => sentence).
function api_changes_deleted_parse_types($raw) {

	$known = api_changes_deleted_types();
	$raw = trim((string)$raw);

	if ($raw === '') {

		return array('types' => array_keys(array_filter($known, function ($type) {
			return $type['covered'];
		})));

	}

	$names = array();

	foreach (explode(',', $raw) as $name) {

		$name = strtolower(trim($name));

		if ($name === '') {

			continue;

		}

		if (!isset($known[$name])) {

			return array('error' => lang(array('string' => '{var:1} is not a type this list knows.', 'vars' => $name)));

		}

		if (!$known[$name]['covered']) {

			return array('error' => lang(array('string' => 'The deletes of {var:1} are not all recorded, so they are not listed here. Read that list whole again.', 'vars' => $name)));

		}

		$names[$name] = true;

	}

	if (!$names) {

		return array('error' => lang('Name at least one type.'));

	}

	return array('types' => array_keys($names));

}

// The types of $names this caller reads: its scopes hold the type's read
// scope, and for the user role (3) the type has no row rule narrower than
// the scope (its rows could not be told apart here).
function api_changes_deleted_readable(array $names, $scopes, $role) {

	$known = api_changes_deleted_types();
	$out = array();

	foreach ($names as $name) {

		if (!isset($known[$name]) || !$known[$name]['covered']) {

			continue;

		}

		if (!api_has_scope($scopes, $known[$name]['scope'])) {

			continue;

		}

		if (((int)$role >= 3) && $known[$name]['row_rule']) {

			continue;

		}

		$out[] = $name;

	}

	return $out;

}

// The earliest since the trail can answer for: the later of the retention
// cutoff (0 when every line is kept) and its oldest line. An empty trail
// knows nothing before now.
function api_changes_deleted_floor($cutoff, $oldest, $now) {

	$oldest = (int)$oldest;

	if ($oldest <= 0) {

		return (int)$now;

	}

	return max((int)$cutoff, $oldest);

}

// Whether a since can be answered: not before the floor.
function api_changes_deleted_since_ok($since, $floor) {

	return (int)$since >= (int)$floor;

}

function api_changes_deleted_present($row) {

	$by_entity = array();

	foreach (api_changes_deleted_types() as $name => $type) {

		$by_entity[$type['entity']] = $name;

	}

	return array(
		'type'       => isset($by_entity[(string)$row['entity_type']]) ? $by_entity[(string)$row['entity_type']] : (string)$row['entity_type'],
		'id'         => (int)$row['entity_id'],
		'deleted_at' => api_time($row['created_at'])
	);

}

function api_changes_deleted_schema() {

	return array(
		'type'       => 'string',
		'id'         => 'integer',
		'deleted_at' => 'datetime?'
	);

}

function api_changes_deleted_list($params) {

	if (!pg_audit_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$parsed = api_changes_deleted_parse_types(isset($params['types']) ? $params['types'] : '');

	if (isset($parsed['error'])) {

		api_fail_validation($parsed['error'], 'types');

	}

	$app = api_current_app();
	$role = isset($app['owner']['role']) ? (int)$app['owner']['role'] : 3;
	$types = api_changes_deleted_readable($parsed['types'], api_current_scopes(), $role);

	$since = (int)$params['since'];
	$now = time();
	$oldest = (int)api_value("SELECT MIN(created_at) FROM audit_log");
	$floor = api_changes_deleted_floor(pg_privacy_retention_cutoff(pg_audit_retention_years(), $now), $oldest, $now);

	if (!api_changes_deleted_since_ok($since, $floor)) {

		api_fail(410, 'since_too_old', lang('The audit trail does not reach back to since. Read the lists whole again, then ask from the time of that read.'), 'since');

	}

	$limit = (int)$params['limit'];
	$rows = array();
	$next = null;

	if ($types) {

		$known = api_changes_deleted_types();
		$entities = array();

		foreach ($types as $name) {

			$entities[] = "'" . $known[$name]['entity'] . "'";

		}

		$where = array(
			"action = 'delete'",
			"entity_type IN (" . implode(', ', $entities) . ")",
			"entity_id > 0",
			"created_at >= '" . $since . "'"
		);

		if (isset($params['cursor']) && ($params['cursor'] !== '')) {

			$cursor = api_cursor_decode($params['cursor']);

			if ($cursor === null) {

				api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

			}

			$where[] = "(created_at > '" . (int)$cursor['v'] . "' OR (created_at = '" . (int)$cursor['v'] . "' AND id > '" . (int)$cursor['i'] . "'))";

		}

		$rows = api_rows("SELECT id, created_at, entity_type, entity_id
			FROM audit_log
			WHERE (" . implode(') AND (', $where) . ")
			ORDER BY created_at ASC, id ASC
			LIMIT " . ($limit + 1));

		if (count($rows) > $limit) {

			array_pop($rows);

			$last = end($rows);

			$next = api_cursor_encode((int)$last['created_at'], (int)$last['id']);

		}

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_changes_deleted_present($row);

	}

	api_ok_list($out, $limit, $next, null, array('types' => $types));

}
