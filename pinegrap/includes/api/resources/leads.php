<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The visitor journey (includes/fn/lead_*.php) read from outside: one
// customer's journey and interest score, the hot contacts (also an MCP read
// tool) and the Lead sources report. Read only: nothing is scored, tied or
// told at the call. Only visits of browsers that accepted marketing cookies
// are tied to a contact, and a contact with panel work has no journey.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Whether the journey's tables are there.
function api_lead_ready() {

	return function_exists('pg_lead_ready') && pg_lead_ready();

}

// Whether the scores table is there.
function api_lead_scores_ready() {

	return api_lead_ready() && pg_schema_has('contact_scores', 'scored_at');

}

// A list of reason rows ({kind, points}) as the API gives them, with the score
// they belong to. Pure.
function api_lead_reason_list($list, $score) {

	$out = array();

	foreach (is_array($list) ? $list : array() as $reason) {

		if (is_array($reason) && isset($reason['kind']) && ((string)$reason['kind'] !== '')) {

			$out[] = array('score' => $score, 'kind' => (string)$reason['kind'], 'points' => round((float)($reason['points'] ?? 0), 1));

		}

	}

	return $out;

}

// One customer's interest and fit scores from its contact_scores row, null
// when the contact was never scored (no visit tied, panel work, or the job has
// not reached it yet). Pure but for api_time().
function api_lead_score_present($contact_id, $row, $threshold) {

	$scored = is_array($row);

	$reasons = $scored ? json_decode((string)$row['reasons'], true) : null;

	$topics = array();

	foreach (($scored && is_array($list = json_decode((string)$row['topics'], true))) ? $list : array() as $topic) {

		if (is_array($topic) && ((string)($topic['name'] ?? '') !== '')) {

			$topics[] = array(
				'id'     => (int)($topic['id'] ?? 0),
				'kind'   => (string)($topic['kind'] ?? ''),
				'name'   => (string)$topic['name'],
				'weight' => round((float)($topic['weight'] ?? 0), 1)
			);

		}

	}

	return array(
		'id'            => (int)$contact_id,
		'scored'        => $scored,
		'interest'      => $scored ? (int)$row['interest'] : null,
		'fit'           => $scored ? (int)$row['fit'] : null,
		'threshold'     => (int)$threshold,
		'hot'           => $scored && ((int)$row['interest'] >= (int)$threshold),
		'hot_at'        => $scored ? api_time((int)$row['hot_at']) : null,
		'reasons'       => array_merge(
			api_lead_reason_list(is_array($reasons) ? ($reasons['interest'] ?? null) : null, 'interest'),
			api_lead_reason_list(is_array($reasons) ? ($reasons['fit'] ?? null) : null, 'fit')
		),
		'topics'        => $topics,
		'last_visit_at' => $scored ? api_time((int)$row['last_visit_at']) : null,
		'scored_at'     => $scored ? api_time((int)$row['scored_at']) : null
	);

}

// What api_lead_score_present() answers with, declared for the OpenAPI
// document.
function api_lead_score_schema() {

	return array(
		'id'            => 'integer',
		'scored'        => 'boolean',
		'interest'      => 'integer?',
		'fit'           => 'integer?',
		'threshold'     => 'integer',
		'hot'           => 'boolean',
		'hot_at'        => 'datetime?',
		'reasons'       => array(array('score' => 'string', 'kind' => 'string', 'points' => 'number')),
		'topics'        => array(array('id' => 'integer', 'kind' => 'string', 'name' => 'string', 'weight' => 'number')),
		'last_visit_at' => 'datetime?',
		'scored_at'     => 'datetime?'
	);

}

// One journey row as the API gives it: the same fields on every kind, null
// where the kind has none. Pure but for api_time().
function api_lead_journey_entry_present($row) {

	$kind = (string)$row['kind'];

	$pages = null;

	if ($kind === 'visit') {

		$pages = array();

		foreach ((array)($row['pages'] ?? array()) as $page) {

			$pages[] = array(
				'page'      => (string)$page[0],
				'seconds'   => (int)$page[1],
				'item_type' => isset($page[2]) ? (string)$page[2] : null,
				'item_id'   => isset($page[3]) ? (int)$page[3] : null
			);

		}

	}

	return array(
		'kind'     => $kind,
		'id'       => (int)$row['id'],
		'at'       => api_time((int)$row['at']),
		'name'     => isset($row['name']) ? (string)$row['name'] : null,
		'pages'    => $pages,
		'minutes'  => ($kind === 'visit') ? (int)($row['minutes'] ?? 0) : null,
		'amount'   => ($kind === 'order') ? (int)($row['amount'] ?? 0) : null,
		'complete' => ($kind === 'course') ? !empty($row['complete']) : null
	);

}

// What api_lead_journey_entry_present() answers with.
function api_lead_journey_entry_schema() {

	return array(
		'kind'     => 'string',
		'id'       => 'integer',
		'at'       => 'datetime?',
		'name'     => 'string?',
		'pages'    => array(array('page' => 'string', 'seconds' => 'integer', 'item_type' => 'string?', 'item_id' => 'integer?')),
		'minutes'  => 'integer?',
		'amount'   => 'integer?',
		'complete' => 'boolean?'
	);

}

// Where the contact first came from, null with no visit tied. Pure but for
// api_time().
function api_lead_first_touch_present($first) {

	if (!is_array($first)) {

		return null;

	}

	return array(
		'channel'      => (string)$first['channel'],
		'campaign'     => (string)$first['campaign'],
		'landing_page' => (string)$first['landing_page'],
		'at'           => api_time((int)$first['at'])
	);

}

// What api_lead_first_touch_present() answers with.
function api_lead_first_touch_schema() {

	return array(
		'channel'      => 'string',
		'campaign'     => 'string',
		'landing_page' => 'string',
		'at'           => 'datetime?'
	);

}

// The journey's rights for an application: forms with forms:read (for the
// user role only on pages in folders the owner may edit, as the forms routes
// read them), orders with orders:read on a commerce site.
function api_lead_journey_rights($owner, $scopes, $commerce) {

	$rights = array(
		'orders' => $commerce && api_has_scope($scopes, 'orders:read'),
		'forms'  => api_has_scope($scopes, 'forms:read')
	);

	if ($rights['forms'] && ((int)$owner['role'] >= 3)) {

		$folders = array_flip(array_map('intval', (array)get_folders_that_user_has_access_to((int)$owner['id'])));

		$rights['folder'] = function ($folder) use ($folders) {

			return isset($folders[(int)$folder]);

		};

	}

	return $rights;

}

// GET /customers/{id}/journey: the customer's visits and acts, newest first,
// up to 200 of each source, a page at a time; first_touch says where it first
// came from.
function api_customers_journey($params) {

	$contact_id = (int)api_customer_reachable_or_fail($params['id'], 'contacts.id')['id'];

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$offset = 0;

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$offset = max(0, (int)$cursor['i']);

	}

	$journey = null;

	if (api_lead_ready() && !pg_lead_journey_contact_is_staff($contact_id)) {

		$app = api_current_app();

		$journey = pg_lead_journey_read($contact_id, api_lead_journey_rights($app['owner'], api_current_scopes(), defined('ECOMMERCE') && (ECOMMERCE === true)));

	}

	$rows = ($journey === null) ? array() : $journey['rows'];

	$page = array_slice($rows, $offset, $limit);

	$next = ((count($rows) > ($offset + $limit)) && $page) ? api_cursor_encode(0, $offset + $limit) : null;

	$out = array();

	foreach ($page as $row) {

		$out[] = api_lead_journey_entry_present($row);

	}

	api_ok_list($out, $limit, $next, null, array('first_touch' => api_lead_first_touch_present(($journey === null) ? null : $journey['first'])));

}

// GET /customers/{id}/score: the interest and fit scores the general job last
// worked out, with their reasons and interests.
function api_customers_score($params) {

	$contact_id = (int)api_customer_reachable_or_fail($params['id'], 'contacts.id')['id'];

	$row = null;

	if (api_lead_scores_ready() && !pg_lead_journey_contact_is_staff($contact_id)) {

		$row = api_row("SELECT interest, fit, reasons, topics, hot_at, last_visit_at, scored_at FROM contact_scores WHERE contact_id = '" . $contact_id . "'");

	}

	$settings = pg_lead_settings();

	api_ok(api_lead_score_present($contact_id, $row, $settings['threshold']));

}

// One hot contact: the customer.hot event's fields (pg_lead_event_payload())
// with the name, company and e-mail, when it turned hot and who follows it
// up. $followed_by is null with the Workspace off. Pure but for api_time().
function api_lead_hot_present($row, $threshold, $followed_by) {

	$reasons = json_decode((string)$row['reasons'], true);

	$topics = json_decode((string)$row['topics'], true);

	$event = pg_lead_event_payload(array(
		'contact_id'    => $row['id'],
		'interest'      => $row['interest'],
		'fit'           => $row['fit'],
		'reasons'       => (is_array($reasons) && isset($reasons['interest']) && is_array($reasons['interest'])) ? $reasons['interest'] : array(),
		'topics'        => is_array($topics) ? $topics : array(),
		'last_visit_at' => $row['last_visit_at']
	), $threshold);

	return array(
		'id'            => $event['id'],
		'first_name'    => $row['first_name'],
		'last_name'     => $row['last_name'],
		'company'       => $row['company'],
		'email'         => $row['email_address'],
		'score'         => $event['score'],
		'fit'           => $event['fit'],
		'threshold'     => $event['threshold'],
		'reasons'       => $event['reasons'],
		'topic'         => $event['topic'],
		'topics'        => $event['topics'],
		'last_visit_at' => $event['last_visit_at'],
		'hot_at'        => api_time((int)$row['hot_at']),
		'followed_by'   => ($followed_by === null) ? null : array_values(array_map('intval', $followed_by))
	);

}

// What api_lead_hot_present() answers with.
function api_lead_hot_schema() {

	return array(
		'id'            => 'integer',
		'first_name'    => 'string?',
		'last_name'     => 'string?',
		'company'       => 'string?',
		'email'         => 'string?',
		'score'         => 'integer',
		'fit'           => 'integer',
		'threshold'     => 'integer',
		'reasons'       => 'string[]',
		'topic'         => 'string',
		'topics'        => 'string[]',
		'last_visit_at' => 'datetime?',
		'hot_at'        => 'datetime?',
		'followed_by'   => 'integer[]?'
	);

}

// The hot list's page: highest score first, then the newest id, as a keyset
// on both. Pure.
function api_lead_hot_page_sql($owner_sql, $cursor, $limit) {

	$where = array('contact_scores.hot = 1');

	if ($owner_sql !== '') {

		$where[] = $owner_sql;

	}

	if (is_array($cursor)) {

		$where[] = "(contact_scores.interest < '" . (int)$cursor['v'] . "' OR (contact_scores.interest = '" . (int)$cursor['v'] . "' AND contacts.id < '" . (int)$cursor['i'] . "'))";

	}

	return "SELECT contacts.id, contacts.first_name, contacts.last_name, contacts.company, contacts.email_address,
			contact_scores.interest, contact_scores.fit, contact_scores.reasons, contact_scores.topics,
			contact_scores.hot_at, contact_scores.last_visit_at
		FROM contact_scores
		INNER JOIN contacts ON contacts.id = contact_scores.contact_id
		WHERE " . implode(' AND ', $where) . "
		ORDER BY contact_scores.interest DESC, contacts.id DESC
		LIMIT " . ((int)$limit + 1);

}

// GET /customers/hot: the Hot contacts view of the contacts list, a page at a
// time, with followed_by as its "Followed by" column reads it.
function api_customers_hot($params) {

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$cursor = null;

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$settings = pg_lead_settings();

	if (!api_lead_scores_ready() || !pg_schema_has('contact_scores', 'hot')) {

		api_ok_list(array(), $limit, null, null, array('threshold' => (int)$settings['threshold']));

	}

	$app = api_current_app();

	$rows = api_rows(api_lead_hot_page_sql(api_customer_owner_filter($app['owner']), $cursor, $limit));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = $rows[count($rows) - 1];

		$next = api_cursor_encode((int)$last['interest'], (int)$last['id']);

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

	$workspace_on = pg_contact_list_workspace_on();

	$followers = pg_contact_list_followers(array_column($rows, 'id'), $workspace_on);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_lead_hot_present($row, $settings['threshold'], $workspace_on ? ($followers[(int)$row['id']] ?? array()) : null);

	}

	api_ok_list($out, $limit, $next, null, array('threshold' => (int)$settings['threshold']));

}

// One group of the Lead sources report. $revenue_shown false gives revenue
// null. Pure but for the label's lang().
function api_lead_source_present($by, $group, $revenue_shown) {

	return array(
		'key'       => (string)$group['key'],
		'label'     => pg_lead_sources_label($by, (string)$group['key'], !empty($group['gone'])),
		'leads'     => (int)$group['leads'],
		'converted' => (int)$group['converted'],
		'share'     => ($group['share'] === null) ? null : (int)$group['share'],
		'customers' => (int)$group['customers'],
		'revenue'   => $revenue_shown ? (int)$group['revenue'] : null
	);

}

// What api_lead_source_present() answers with.
function api_lead_source_schema() {

	return array(
		'key'       => 'string',
		'label'     => 'string',
		'leads'     => 'integer',
		'converted' => 'integer',
		'share'     => 'integer?',
		'customers' => 'integer',
		'revenue'   => 'integer?'
	);

}

// The report around the groups. Pure.
function api_lead_sources_report_present($window, $touch, $by, $totals, $ready, $revenue_shown, $currency) {

	return array(
		'ready'     => (bool)$ready,
		'from'      => $window['first'],
		'to'        => $window['last'],
		'touch'     => $touch,
		'by'        => $by,
		'currency'  => $revenue_shown ? $currency : null,
		'totals'    => array(
			'leads'     => (int)$totals['leads'],
			'converted' => (int)$totals['converted'],
			'share'     => ($totals['share'] === null) ? null : (int)$totals['share'],
			'customers' => (int)$totals['customers'],
			'revenue'   => $revenue_shown ? (int)$totals['revenue'] : null
		)
	);

}

// What api_lead_sources_report_present() answers with.
function api_lead_sources_report_schema() {

	return array(
		'ready'    => 'boolean',
		'from'     => 'date',
		'to'       => 'date',
		'touch'    => 'string',
		'by'       => 'string',
		'currency' => 'string?',
		'totals'   => array('leads' => 'integer', 'converted' => 'integer', 'share' => 'integer?', 'customers' => 'integer', 'revenue' => 'integer?')
	);

}

// GET /reports/lead-sources: the Lead sources report over a window of days,
// grouped by the first or last touch's channel, campaign or landing page.
// Revenue only on a commerce site and to a key that also holds orders:read.
function api_reports_lead_sources($params) {

	$window = api_visitors_window($params, 400);

	$touch = pg_lead_sources_choice($params['touch'] ?? '', array('first', 'last'));

	$by = pg_lead_sources_choice($params['by'] ?? '', array('channel', 'campaign', 'landing_page'));

	$revenue_shown = defined('ECOMMERCE') && (ECOMMERCE === true) && api_has_scope(api_current_scopes(), 'orders:read');

	$from = strtotime($window['first'] . ' 00:00:00');

	$to = strtotime($window['last'] . ' 00:00:00 +1 day');

	$groups = pg_lead_sources_group(pg_lead_sources_read($from, $to, $touch), $by);

	$out = array();

	foreach ($groups as $group) {

		$out[] = api_lead_source_present($by, $group, $revenue_shown);

	}

	$currency = defined('BASE_CURRENCY_CODE') ? (string)BASE_CURRENCY_CODE : null;

	api_ok_list($out, max(1, count($out)), null, null, array(
		'report' => api_lead_sources_report_present($window, $touch, $by, pg_lead_sources_totals($groups), api_lead_ready(), $revenue_shown, $currency)
	));

}
