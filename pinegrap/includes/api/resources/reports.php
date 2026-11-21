<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Sales, added up.
//
// Every figure here can be worked out from /orders, and that is exactly the
// problem: a shop with forty thousand orders cannot be asked to page through
// all of them to draw one chart. The database can add them up in one statement,
// so it does.
//
// What counts as a sale is a decision rather than a fact, so it is written down
// here rather than assumed: complete and exported orders. An incomplete order
// is an abandoned basket, and a cancelled one is money that went back. Both are
// available through the status parameter for a caller who wants them - a shop
// measuring its abandonment rate is asking a real question.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

function api_reports_sales($params) {

	// A month of days, when nothing was asked for: a useful answer rather than
	// the whole history of the shop.
	$to = isset($params['to']) ? (int)$params['to'] : time();

	$from = isset($params['from']) ? (int)$params['from'] : ($to - (30 * 86400));

	if ($from > $to) {

		api_fail_validation(lang('from is after to.'), 'from');

	}

	$interval = isset($params['interval']) ? (string)$params['interval'] : 'day';

	// The order date is a unix column, so the grouping is done on the date the
	// site's own zone makes of it - the same day the panel shows, not the day
	// UTC would have made of an order placed at half past two in the morning.
	// The session's time_zone is only the offset of the moment of the request,
	// so the zone's offsets over the window are worked out here and added to
	// each order date: an order on the other side of a clock change keeps its
	// own day.
	$period = api_reports_sales_period_sql(api_reports_sales_zone_offsets($from, $to, date_default_timezone_get()), $interval);

	$statuses = array('complete', 'exported');

	if (isset($params['status']) && ($params['status'] !== '')) {

		$statuses = array((string)$params['status']);

	}

	$in = array();

	foreach ($statuses as $status) {

		$in[] = "'" . escape($status) . "'";

	}

	$where = "WHERE (status IN (" . implode(',', $in) . "))
		AND (order_date >= '" . $from . "') AND (order_date <= '" . $to . "')";

	$max = api_reports_sales_max_periods();

	// One row past the most an answer carries tells a window that holds more
	// apart from one that holds exactly that many.
	$rows = api_rows("SELECT " . $period . " AS period,
			COUNT(*) AS orders,
			SUM(subtotal) AS subtotal,
			SUM(discount) AS discount,
			SUM(tax) AS tax,
			SUM(shipping) AS shipping,
			SUM(surcharge) AS surcharge,
			SUM(total) AS total
		FROM orders
		" . $where . "
		GROUP BY period
		ORDER BY period ASC
		LIMIT " . ($max + 1));

	$tally = api_reports_sales_tally($rows, $max);

	// Cut at the limit, the answer gave the requested window back with only
	// its first periods in it and totals added up from those alone: a chart of
	// 500 days of sales read as 400. Only periods that hold orders come back, so
	// a long window over a quiet shop still fits; one that does not is refused
	// rather than answered short.
	if ($tally === null) {

		if ($interval === 'month') {

			$message = lang(array('string' => 'Orders fall in more than {var:1} months of this window. Ask for a shorter one.', 'vars' => $max));

		} else {

			$message = lang(array('string' => 'Orders fall on more than {var:1} days of this window. Ask for a shorter one, or by month.', 'vars' => $max));

		}

		api_fail_validation($message, 'from');

	}

	api_ok(array(
		'from'     => api_time($from),
		'to'       => api_time($to),
		'interval' => ($interval === 'month') ? 'month' : 'day',
		'statuses' => $statuses,
		// Minor units, like every other amount this API reports, and the code
		// is on /meta beside the rest of the store's money settings.
		'totals'   => $tally['totals'],
		'periods'  => $tally['periods']
	));

}

// The most periods one sales report carries.
function api_reports_sales_max_periods() {

	return 400;

}

// The site zone's offsets from UTC over a window, as stretches: array(first
// second, offset in seconds), the first starting at $from. A zone without
// clock changes is one stretch. An unknown zone name reads as the zone PHP
// runs in.
function api_reports_sales_zone_offsets($from, $to, $zone_name) {

	try {

		$zone = new DateTimeZone($zone_name);

	} catch (Exception $e) {

		$zone = new DateTimeZone(date_default_timezone_get());

	}

	$stretches = array();

	$transitions = $zone->getTransitions($from, $to);

	if (!is_array($transitions) || !$transitions) {

		$moment = new DateTime('@' . $from);

		return array(array($from, $zone->getOffset($moment)));

	}

	foreach ($transitions as $transition) {

		$start = max($from, (int)$transition['ts']);

		$offset = (int)$transition['offset'];

		// The first entry repeats the offset in force at $from, and some PHP
		// versions list a change twice: keep only real changes.
		if ($stretches && ($stretches[count($stretches) - 1][1] === $offset)) {

			continue;

		}

		$stretches[] = array($start, $offset);

	}

	return $stretches;

}

// The period of an order as an SQL expression: the order date plus the offset
// of its stretch, read as a date on a fixed start rather than through
// FROM_UNIXTIME, so neither the session's time_zone nor MySQL's named zone
// tables (missing on many hosts) take part.
function api_reports_sales_period_sql($stretches, $interval) {

	$offset = (string)(int)$stretches[0][1];

	for ($i = 1; $i < count($stretches); $i++) {

		$offset = 'CASE WHEN order_date >= ' . (int)$stretches[$i][0] . ' THEN ' . (int)$stretches[$i][1] . ' ELSE ' . $offset . ' END';

	}

	$format = ($interval === 'month') ? '%Y-%m' : '%Y-%m-%d';

	return "DATE_FORMAT(DATE_ADD(CAST('1970-01-01 00:00:00' AS DATETIME), INTERVAL (order_date + " . $offset . ") SECOND), '" . $format . "')";

}

// The periods of a sales report and the totals of the whole window, out of the
// grouped rows. Null when there are more rows than one answer carries: the
// totals would then cover part of the window only.
function api_reports_sales_tally($rows, $max) {

	if (count($rows) > $max) {

		return null;

	}

	$periods = array();

	$totals = array('orders' => 0, 'subtotal' => 0, 'discount' => 0, 'tax' => 0,
		'shipping' => 0, 'surcharge' => 0, 'total' => 0);

	foreach ($rows as $row) {

		$period = array(
			'period'    => (string)$row['period'],
			'orders'    => (int)$row['orders'],
			'subtotal'  => api_money($row['subtotal']),
			'discount'  => api_money($row['discount']),
			'tax'       => api_money($row['tax']),
			'shipping'  => api_money($row['shipping']),
			'surcharge' => api_money($row['surcharge']),
			'total'     => api_money($row['total'])
		);

		foreach ($totals as $key => $value) {

			$totals[$key] = $value + $period[$key];

		}

		$periods[] = $period;

	}

	return array('periods' => $periods, 'totals' => $totals);

}

// What api_reports_sales() answers with, declared for the OpenAPI document.
function api_sales_report_schema() {

	$figures = array(
		'orders'    => 'integer',
		'subtotal'  => 'integer',
		'discount'  => 'integer',
		'tax'       => 'integer',
		'shipping'  => 'integer',
		'surcharge' => 'integer',
		'total'     => 'integer'
	);

	return array(
		'from'     => 'string?',
		'to'       => 'string?',
		'interval' => 'string',
		'statuses' => 'string[]',
		'totals'   => $figures,
		'periods'  => array(array_merge(array('period' => 'string'), $figures))
	);

}

// Page speed as visitors' browsers measured it (includes/fn/page_vitals.php):
// the site, each day and the slowest pages of a window, from the hourly
// rollup.
function api_reports_vitals($params) {

	if (!pg_vitals_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$window = api_visitors_window($params, 400);

	$device = isset($params['device']) ? (string)$params['device'] : '';

	$order = isset($params['order']) ? (string)$params['order'] : 'lcp';

	$limit = isset($params['limit']) ? (int)$params['limit'] : 10;

	$days = array();

	foreach (pg_vitals_trend($window['first'], $window['last'], $device) as $day) {

		$days[] = array('day' => $day['day']) + api_vitals_figures_present($day);

	}

	$pages = array();

	foreach (pg_vitals_pages($window['first'], $window['last'], $order, $limit, $device) as $page) {

		$pages[] = array(
			'page_id'   => $page['page_id'],
			'page_name' => ($page['page_name'] !== '') ? $page['page_name'] : null
		) + api_vitals_figures_present($page);

	}

	api_ok(array(
		'from'   => $window['first'],
		'to'     => $window['last'],
		'device' => ($device !== '') ? $device : null,
		'order'  => $order,
		'site'   => api_vitals_figures_present(pg_vitals_site($window['first'], $window['last'], $device)),
		'days'   => $days,
		'pages'  => $pages
	));

}

// The figures of pg_vitals_figures(), as the API gives them.
function api_vitals_figures_present($figures) {

	$out = array('views' => (int)$figures['views']);

	foreach (array('lcp', 'inp', 'cls') as $metric) {

		$out[$metric] = array(
			'samples'     => (int)$figures[$metric]['samples'],
			'p75'         => $figures[$metric]['p75'],
			'mean'        => $figures[$metric]['mean'],
			'good_share'  => $figures[$metric]['good_share'],
			'needs_share' => $figures[$metric]['needs_share'],
			'poor_share'  => $figures[$metric]['poor_share']
		);

	}

	return $out;

}

// What api_vitals_figures_present() returns.
function api_vitals_figures_schema() {

	$figure = array(
		'samples'     => 'integer',
		'p75'         => 'integer?',
		'mean'        => 'integer?',
		'good_share'  => 'number?',
		'needs_share' => 'number?',
		'poor_share'  => 'number?'
	);

	return array('views' => 'integer', 'lcp' => $figure, 'inp' => $figure, 'cls' => $figure);

}

// What api_reports_vitals() returns.
function api_vitals_report_schema() {

	$figures = api_vitals_figures_schema();

	return array(
		'from'   => 'string',
		'to'     => 'string',
		'device' => 'string?',
		'order'  => 'string',
		'site'   => $figures,
		'days'   => array(array('day' => 'string') + $figures),
		'pages'  => array(array('page_id' => 'integer', 'page_name' => 'string?') + $figures)
	);

}

// Recovered carts (includes/fn/cart_recovery_figures.php): the left carts of
// a window of whole site days, the touched ones, those that came back and
// those a touch recovered, in all, by channel and by message.
function api_reports_cart_recovery($params) {

	if (!function_exists('pg_cart_recovery_ready') || !pg_cart_recovery_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$window = api_visitors_window($params, 400);

	$from = strtotime($window['first'] . ' 00:00:00');

	$to = strtotime($window['last'] . ' 00:00:00 +1 day');

	api_ok(api_cart_recovery_present(pg_cart_recovery_period($from, $to), $window['first'], $window['last']));

}

// The answer's shape from pg_cart_recovery_period(). Pure.
function api_cart_recovery_present($figures, $first, $last) {

	$out = array(
		'from'      => (string)$first,
		'to'        => (string)$last,
		'left'      => (int)$figures['left'],
		'untouched' => (int)$figures['untouched'],
		'reached'   => (int)$figures['reached'],
		'returned'  => (int)$figures['returned'],
		'recovered' => (int)$figures['recovered'],
		'revenue'   => api_money($figures['revenue']),
		'channels'  => array(),
		'messages'  => array()
	);

	foreach ($figures['channels'] as $channel => $row) {

		$out['channels'][] = array(
			'channel'   => (string)$channel,
			'reached'   => (int)$row['reached'],
			'returned'  => (int)$row['returned'],
			'recovered' => (int)$row['recovered'],
			'revenue'   => api_money($row['revenue'])
		);

	}

	foreach ($figures['messages'] as $row) {

		$out['messages'][] = array(
			'source_type' => (string)$row['source_type'],
			'source_id'   => (int)$row['source_id'],
			'channel'     => (string)$row['channel'],
			'label'       => isset($row['label']) ? (string)$row['label'] : '',
			'sent'        => (int)$row['sent'],
			'reached'     => (int)$row['reached'],
			'returned'    => (int)$row['returned'],
			'recovered'   => (int)$row['recovered'],
			'revenue'     => api_money($row['revenue'])
		);

	}

	return $out;

}

function api_cart_recovery_report_schema() {

	$figures = array(
		'reached'   => 'integer',
		'returned'  => 'integer',
		'recovered' => 'integer',
		'revenue'   => 'integer'
	);

	return array_merge(array(
		'from'      => 'string',
		'to'        => 'string',
		'left'      => 'integer',
		'untouched' => 'integer'
	), $figures, array(
		'channels'  => array(array_merge(array('channel' => 'string'), $figures)),
		'messages'  => array(array_merge(array('source_type' => 'string', 'source_id' => 'integer', 'channel' => 'string', 'label' => 'string', 'sent' => 'integer'), $figures))
	));

}

// The Customers report (includes/fn/customer_values.php): the summary the
// general job rebuilds once a day, never a sum over the orders at the call.
// A top customer's name is read from their last order when answered, and
// only for a key that may also read customers.
function api_reports_customer_value($params) {

	$summary = pg_customer_value_summary();

	list(, $pass) = pg_customer_value_pass_read();

	$names_shown = api_has_scope(api_current_scopes(), 'customers:read');

	$names = array();

	if (($summary !== null) && $names_shown) {

		$names = pg_customer_value_order_names(array_merge(
			array_column((array)$summary['top']['all'], 0),
			array_column((array)$summary['top']['year'], 0)
		));

	}

	api_ok(api_customer_value_present($summary, $pass, $names_shown, $names));

}

// The answer of GET /reports/customer-value. Pure: the summary, the pass the
// job is on, whether names are given and the names read for it.
function api_customer_value_present($summary, $pass, $names_shown, $names) {

	if ($summary === null) {

		return array(
			'ready'       => false,
			'as_of'       => null,
			'progress'    => api_customer_value_progress($pass),
			'names_shown' => (bool)$names_shown,
			'currency'    => null,
			'customers'   => 0,
			'orders'      => 0,
			'not_placed'  => 0,
			'apart'       => array(),
			'periods'     => array(),
			'months'      => array(),
			'cohorts'     => array(),
			'groups'      => array(),
			'top'         => array('all' => array(), 'year' => array())
		);

	}

	$apart = array();

	foreach ((array)$summary['apart'] as $unit => $row) {

		$apart[] = array('currency' => (string)$unit, 'orders' => (int)$row[0], 'value' => (int)$row[1]);

	}

	$periods = array();

	foreach ((array)$summary['tiles'] as $key => $tile) {

		$periods[] = array(
			'period'              => (string)$key,
			'customers'           => (int)$tile[0],
			'repeat_customers'    => (int)$tile[1],
			'orders'              => (int)$tile[2],
			'value'               => api_money($tile[3]),
			'days_between_orders' => (float)$tile[4]
		);

	}

	$months = array();

	foreach ((array)$summary['months'] as $key => $row) {

		$months[] = array(
			'month'            => (string)$key,
			'first_orders'     => (int)$row[0],
			'first_value'      => api_money($row[1]),
			'returning_orders' => (int)$row[2],
			'returning_value'  => api_money($row[3])
		);

	}

	// A cohort answers only the months after it that have begun by the
	// summary's month, as the screen leaves the others blank: a month not
	// begun yet has no figure, not a zero.
	$cohorts = array();

	$last = pg_customer_value_month((int)$summary['at']);

	foreach ((array)$summary['cohorts'] as $key => $row) {

		$first = ((int)substr((string)$key, 0, 4) * 12) + (int)substr((string)$key, 5, 2) - 1;

		$cohorts[] = array(
			'month'         => (string)$key,
			'customers'     => (int)$row[0],
			'ordered_again' => array_map('intval', array_slice(array_values((array)$row), 1, max(0, min(12, $last - $first))))
		);

	}

	$ages = array('within_30_days', '31_to_90_days', '91_to_180_days', '181_to_365_days', 'over_365_days');

	$bands = array('1', '2-3', '4+');

	$groups = array();

	foreach ($ages as $age => $age_key) {

		foreach ($bands as $band => $band_key) {

			$cell = isset($summary['cells'][$age][$band]) ? $summary['cells'][$age][$band] : array(0, 0);

			$groups[] = array(
				'last_order' => $age_key,
				'orders'     => $band_key,
				'group'      => pg_customer_value_cell_group($age, $band),
				'customers'  => (int)$cell[0],
				'value'      => api_money($cell[1])
			);

		}

	}

	$top = array();

	foreach (array('all', 'year') as $list) {

		$top[$list] = array();

		foreach (array_values((array)$summary['top'][$list]) as $i => $row) {

			$number = (int)$row[0];

			$top[$list][] = array(
				'rank'              => $i + 1,
				'last_order_number' => $number,
				'name'              => ($names_shown && isset($names[$number])) ? (string)$names[$number]['name'] : null,
				'orders'            => (int)$row[2],
				'value'             => api_money($row[1])
			);

		}

	}

	return array(
		'ready'       => true,
		'as_of'       => api_time($summary['at']),
		'progress'    => api_customer_value_progress($pass),
		'names_shown' => (bool)$names_shown,
		'currency'    => (string)$summary['unit'],
		'customers'   => (int)$summary['customers'],
		'orders'      => (int)$summary['orders'],
		'not_placed'  => (int)$summary['unplaced'],
		'apart'       => $apart,
		'periods'     => $periods,
		'months'      => $months,
		'cohorts'     => $cohorts,
		'groups'      => $groups,
		'top'         => $top
	);

}

// How far the rebuild under way has read; zeros when none is.
function api_customer_value_progress($pass) {

	$under_way = ((int)$pass['started'] > 0);

	return array('read' => $under_way ? (int)$pass['done'] : 0, 'total' => $under_way ? (int)$pass['total'] : 0);

}

// What api_reports_customer_value() answers with, declared for the OpenAPI
// document.
function api_customer_value_report_schema() {

	$top = array(array(
		'rank'              => 'integer',
		'last_order_number' => 'integer',
		'name'              => 'string?',
		'orders'            => 'integer',
		'value'             => 'integer'
	));

	return array(
		'ready'       => 'boolean',
		'as_of'       => 'datetime?',
		'progress'    => array('read' => 'integer', 'total' => 'integer'),
		'names_shown' => 'boolean',
		'currency'    => 'string?',
		'customers'   => 'integer',
		'orders'      => 'integer',
		'not_placed'  => 'integer',
		'apart'       => array(array('currency' => 'string', 'orders' => 'integer', 'value' => 'integer')),
		'periods'     => array(array('period' => 'string', 'customers' => 'integer', 'repeat_customers' => 'integer', 'orders' => 'integer', 'value' => 'integer', 'days_between_orders' => 'number')),
		'months'      => array(array('month' => 'string', 'first_orders' => 'integer', 'first_value' => 'integer', 'returning_orders' => 'integer', 'returning_value' => 'integer')),
		'cohorts'     => array(array('month' => 'string', 'customers' => 'integer', 'ordered_again' => 'integer[]')),
		'groups'      => array(array('last_order' => 'string', 'orders' => 'string', 'group' => 'string', 'customers' => 'integer', 'value' => 'integer')),
		'top'         => array('all' => $top, 'year' => $top)
	);

}

// Customer rhythm (includes/fn/customer_rhythm_*.php): the customers per
// stage, read from the table the nightly pass writes, and its customers a
// page at a time, most expected value first; with contact_id the one row the
// contact card shows and the products due for it. Read only: nothing is
// worked out again or told at the call. A name is given only to a key that
// may also read customers and, for the user role (3), only on a contact in
// its reach, as the customers routes narrow theirs.
function api_reports_customer_rhythm($params) {

	$commerce = defined('ECOMMERCE') && (ECOMMERCE === true);

	$ready = $commerce && pg_customer_rhythm_ready();

	$summary = $ready ? pg_customer_rhythm_summary() : null;

	$pass = array('lease' => 0, 'started' => 0, 'done' => 0, 'total' => 0);

	if ($ready) {

		list(, $pass) = pg_customer_rhythm_pass_read();

	}

	$names_shown = api_has_scope(api_current_scopes(), 'customers:read');

	$app = api_current_app();

	$name_rule = ((int)$app['owner']['role'] < 3) ? '' : pg_contact_owner_sql((int)$app['owner']['id'], 'customer_rhythm.contact_id');

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$query = array('limit' => $limit, 'names' => $names_shown, 'name_rule' => $name_rule, 'stage' => api_customer_rhythm_stage_filter(isset($params['stage']) ? $params['stage'] : ''));

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$query['cursor'] = api_cursor_decode($params['cursor']);

		if ($query['cursor'] === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$currency = defined('BASE_CURRENCY_CODE') ? (string)BASE_CURRENCY_CODE : null;

	if ($summary === null) {

		api_ok_list(array(), $limit, null, null, array('rhythm' => api_customer_rhythm_present(null, $pass, $names_shown, $currency, array(), array())));

	}

	$now = time();

	$due = array();

	$rows = array();

	if (isset($params['contact_id'])) {

		// The card's row: the contact's e-mail key, else its contact key, else
		// the latest row naming it.
		$contact = pg_customer_rhythm_select("SELECT id, email_address FROM contacts WHERE id = ?", array((int)$params['contact_id']));

		$row = $contact ? pg_customer_rhythm_contact_row((int)$contact[0]['id'], (string)$contact[0]['email_address']) : null;

		if ($row !== null) {

			$query['id'] = (int)$row['id'];

			$query['stage'] = null;

			unset($query['cursor']);

			list($sql, $values) = api_customer_rhythm_page_query($query);

			$rows = pg_customer_rhythm_select($sql, $values);

			$due = api_customer_rhythm_due($row, $summary, $now);

		}

	} else {

		list($sql, $values) = api_customer_rhythm_page_query($query);

		$rows = pg_customer_rhythm_select($sql, $values);

	}

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = $rows[count($rows) - 1];

		$next = api_cursor_encode((int)$last['expected_value'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_customer_rhythm_customer_present($row, $names_shown, $now);

	}

	api_ok_list($out, $limit, $next, null, array('rhythm' => api_customer_rhythm_present($summary, $pass, $names_shown, $currency, pg_customer_rhythm_stage_totals(), $due)));

}

// The stage words a caller filters by and is answered with: the six stages,
// then none for a customer the store has no interval to judge yet. Pure.
function api_customer_rhythm_stage_keys() {

	$keys = array_values(array_filter(array_keys(pg_customer_rhythm_stage_codes()), 'strlen'));

	$keys[] = 'none';

	return $keys;

}

// A stage code as its word. Pure.
function api_customer_rhythm_stage_key($code) {

	$name = pg_customer_rhythm_stage_name((int)$code);

	return ($name === '') ? 'none' : $name;

}

// The stage code a stage word filters by, null for no filter. Pure.
function api_customer_rhythm_stage_filter($stage) {

	$stage = is_scalar($stage) ? (string)$stage : '';

	if ($stage === 'none') {

		return 0;

	}

	$code = pg_customer_rhythm_stage_code($stage);

	return ($code > 0) ? $code : null;

}

// One page of customer_rhythm rows, most expected value first, with the
// number of the last order; names joined only when they are shown.
// name_rule is '' when every name may be read, else the condition a row's
// contact must meet for its name: it marks names, it never narrows the rows,
// as the figures are order data. Never the customer key. Pure.
//
// $query: stage (code or null), cursor (v, i) or null, id, limit, names,
// name_rule.
function api_customer_rhythm_page_query($query) {

	$select = 'customer_rhythm.id, customer_rhythm.contact_id, customer_rhythm.orders, customer_rhythm.first_order_at, customer_rhythm.last_order_at, customer_rhythm.last_order_id, customer_rhythm.interval_days, customer_rhythm.avg_order, customer_rhythm.stage, customer_rhythm.stage_before, customer_rhythm.stage_at, customer_rhythm.chance, customer_rhythm.expected_value, customer_rhythm.next_due_at, customer_rhythm.reminded_at, customer_rhythm.held_back, customer_rhythm.came_back_order_id, orders.order_number';

	$from = 'customer_rhythm LEFT JOIN orders ON orders.id = customer_rhythm.last_order_id';

	if (!empty($query['names'])) {

		$rule = isset($query['name_rule']) ? (string)$query['name_rule'] : '';

		$select .= ', contacts.first_name AS contact_first_name, contacts.last_name AS contact_last_name, orders.billing_first_name, orders.billing_last_name, orders.billing_company, '
			. (($rule === '') ? '1' : '(customer_rhythm.contact_id <> 0 AND ' . $rule . ')') . ' AS name_ok';

		$from .= ' LEFT JOIN contacts ON contacts.id = customer_rhythm.contact_id';

	}

	$where = array();

	$params = array();

	if (isset($query['id'])) {

		$where[] = '(customer_rhythm.id = ?)';

		$params[] = (int)$query['id'];

	}

	if (isset($query['stage']) && ($query['stage'] !== null)) {

		$where[] = '(customer_rhythm.stage = ?)';

		$params[] = (int)$query['stage'];

	}

	if (!empty($query['cursor'])) {

		$where[] = '((customer_rhythm.expected_value < ?) OR ((customer_rhythm.expected_value = ?) AND (customer_rhythm.id < ?)))';

		$params[] = (int)$query['cursor']['v'];

		$params[] = (int)$query['cursor']['v'];

		$params[] = (int)$query['cursor']['i'];

	}

	$sql = 'SELECT ' . $select . ' FROM ' . $from
		. ($where ? ' WHERE ' . implode(' AND ', $where) : '')
		. ' ORDER BY customer_rhythm.expected_value DESC, customer_rhythm.id DESC LIMIT ' . ((int)$query['limit'] + 1);

	return array($sql, $params);

}

// The products due for one customer now, as the contact card works them out,
// with their names.
function api_customer_rhythm_due($row, $summary, $now) {

	$due = pg_customer_rhythm_contact_due($row, $summary, pg_customer_rhythm_settings(), $now);

	if (!$due) {

		return array();

	}

	$names = array();

	foreach (pg_customer_rhythm_select("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', array_map('intval', array_column($due, 'product_id'))) . ")") as $item) {

		$names[(int)$item['id']] = ((string)$item['short_description'] !== '') ? (string)$item['short_description'] : (string)$item['name'];

	}

	foreach ($due as $i => $product) {

		$due[$i]['name'] = isset($names[(int)$product['product_id']]) ? $names[(int)$product['product_id']] : '';

	}

	return $due;

}

// One customer of GET /reports/customer-rhythm. Pure: the row as
// api_customer_rhythm_page_query() reads it, whether names are shown, now.
function api_customer_rhythm_customer_present($row, $names_shown, $now) {

	$name = null;

	if ($names_shown && !empty($row['name_ok'])) {

		$name = pg_customer_rhythm_name($row);

		$name = ($name !== '') ? $name : null;

	}

	return array(
		'id'                    => (int)$row['id'],
		'contact_id'            => ((int)$row['contact_id'] > 0) ? (int)$row['contact_id'] : null,
		'name'                  => $name,
		'stage'                 => api_customer_rhythm_stage_key($row['stage']),
		'stage_before'          => api_customer_rhythm_stage_key($row['stage_before']),
		'stage_changed_at'      => api_time($row['stage_at']),
		'orders'                => (int)$row['orders'],
		'first_order_at'        => api_time($row['first_order_at']),
		'last_order_at'         => api_time($row['last_order_at']),
		'last_order_number'     => ((int)$row['order_number'] > 0) ? (int)$row['order_number'] : null,
		'days_since_last_order' => pg_customer_rhythm_days_since($row, $now),
		'interval_days'         => (int)$row['interval_days'],
		'own_interval'          => ((int)$row['orders'] > 1),
		'chance'                => (int)$row['chance'],
		'expected_value'        => (int)$row['expected_value'],
		'average_order'         => (int)$row['avg_order'],
		'next_due_at'           => api_time($row['next_due_at']),
		'reminded_at'           => api_time($row['reminded_at']),
		'held_back'             => ((int)$row['held_back'] === 1),
		'came_back_order_id'    => ((int)$row['came_back_order_id'] > 0) ? (int)$row['came_back_order_id'] : null
	);

}

// What GET /reports/customer-rhythm answers beside its page. Pure: the
// summary (null before the first pass), the pass under way, whether names
// are shown, the base currency, the counts per stage code and the products
// due for the one contact asked for.
function api_customer_rhythm_present($summary, $pass, $names_shown, $currency, $totals, $due) {

	$stages = array();

	if ($summary !== null) {

		foreach (pg_customer_rhythm_stage_rows($totals) as $row) {

			$stages[] = array('stage' => ($row['stage'] === '') ? 'none' : $row['stage'], 'customers' => (int)$row['customers'], 'expected_value' => (int)$row['value']);

		}

	}

	$products = array();

	foreach ((array)$due as $product) {

		$products[] = array(
			'product_id'     => (int)$product['product_id'],
			'name'           => (string)$product['name'],
			'quantity'       => (int)$product['quantity'],
			'last_bought_at' => api_time($product['last']),
			'due_at'         => api_time($product['due'])
		);

	}

	return array(
		'ready'             => ($summary !== null),
		'as_of'             => ($summary !== null) ? api_time($summary['at']) : null,
		'progress'          => api_customer_value_progress($pass),
		'names_shown'       => (bool)$names_shown,
		'currency'          => ($currency !== null) ? (string)$currency : null,
		'store_median_days' => (($summary !== null) && ($summary['median'] !== null)) ? (float)$summary['median'] : null,
		'customers'         => ($summary !== null) ? (int)$summary['customers'] : 0,
		'stages'            => $stages,
		'due'               => $products
	);

}

// What api_customer_rhythm_present() answers with, declared for the OpenAPI
// document.
function api_customer_rhythm_report_schema() {

	return array(
		'ready'             => 'boolean',
		'as_of'             => 'datetime?',
		'progress'          => array('read' => 'integer', 'total' => 'integer'),
		'names_shown'       => 'boolean',
		'currency'          => 'string?',
		'store_median_days' => 'number?',
		'customers'         => 'integer',
		'stages'            => array(array('stage' => 'string', 'customers' => 'integer', 'expected_value' => 'integer')),
		'due'               => array(array('product_id' => 'integer', 'name' => 'string', 'quantity' => 'integer', 'last_bought_at' => 'datetime?', 'due_at' => 'datetime?'))
	);

}

// What api_customer_rhythm_customer_present() answers with, declared for the
// OpenAPI document.
function api_customer_rhythm_customer_schema() {

	return array(
		'id'                    => 'integer',
		'contact_id'            => 'integer?',
		'name'                  => 'string?',
		'stage'                 => 'string',
		'stage_before'          => 'string',
		'stage_changed_at'      => 'datetime?',
		'orders'                => 'integer',
		'first_order_at'        => 'datetime?',
		'last_order_at'         => 'datetime?',
		'last_order_number'     => 'integer?',
		'days_since_last_order' => 'integer',
		'interval_days'         => 'integer',
		'own_interval'          => 'boolean',
		'chance'                => 'integer',
		'expected_value'        => 'integer',
		'average_order'         => 'integer',
		'next_due_at'           => 'datetime?',
		'reminded_at'           => 'datetime?',
		'held_back'             => 'boolean',
		'came_back_order_id'    => 'integer?'
	);

}

// The sales watch (includes/fn/sales_watch.php): per channel the orders it
// expects in the next 12 hours and what the last 12 brought, when its pass
// last ran, the sales alerts of the last 7 days and the products it took off
// sale. A read only: nothing is judged or told at the call, and the norm
// comes from the same cache the pass keeps.
function api_reports_sales_watch($params) {

	$now = time();

	$commerce = defined('ECOMMERCE') && (ECOMMERCE === true);

	$settings = pg_sales_watch_settings();

	$state = pg_ops_alert_state();

	$channels = array();

	if ($commerce) {

		$list = pg_sales_watch_channels($now);

		$data = pg_sales_watch_norm_data($now);

		$since = $now;

		foreach ($list as $channel) {

			$since = min($since, (int)$channel['end']);

		}

		$since -= 12 * 3600;

		$times = array();

		foreach ((array)db_items(
			"SELECT order_date
			FROM orders
			WHERE
				(order_date > '" . $since . "')
				AND (type = 'online')
				AND (status <> 'incomplete')") as $row) {

			$times['site'][] = (int)$row['order_date'];

		}

		if (count($list) > 1) {

			foreach ((array)db_items(
				"SELECT DISTINCT marketplace_order_map.account_id, orders.id, orders.order_date
				FROM orders
				INNER JOIN marketplace_order_map ON marketplace_order_map.order_id = orders.id
				WHERE (orders.order_date > '" . $since . "')") as $row) {

				$times['mp_' . (int)$row['account_id']][] = (int)$row['order_date'];

			}

		}

		foreach ($list as $key => $channel) {

			$paused = 0;

			foreach (array($key, 'store') as $pause_key) {

				if (isset($settings['paused'][$pause_key])) {

					$paused = max($paused, (int)$settings['paused'][$pause_key]);

				}

			}

			$channels[] = api_sales_watch_channel_present(
				$key,
				$channel,
				isset($data['channels'][$key]) ? $data['channels'][$key] : array(),
				(int)$data['cut'],
				isset($times[$key]) ? $times[$key] : array(),
				$paused,
				$now
			);

		}

	}

	$codes = pg_ops_alert_codes();

	$sales_codes = array();

	foreach ($codes as $code => $alert) {

		if (isset($alert['group']) && ($alert['group'] === 'sales')) {

			$sales_codes[] = "'" . escape($code) . "'";

		}

	}

	$alerts = array();

	if ($sales_codes) {

		foreach ((array)db_items(
			"SELECT order_total AS code, title, timestamp, product_id
			FROM notifications
			WHERE
				(action = 'ops')
				AND (order_total IN (" . implode(', ', $sales_codes) . "))
				AND (timestamp >= '" . ($now - (7 * 86400)) . "')
			ORDER BY timestamp DESC, id DESC
			LIMIT 200") as $row) {

			$alerts[] = api_sales_watch_alert_present($row, $codes);

		}

	}

	$record = pg_sales_watch_closed_normalize((string)pg_config_text('sales_watch_closed'));

	$products = array();

	if ($record['closed']) {

		foreach ((array)db_items(
			"SELECT id, name, enabled FROM products
			WHERE id IN ('" . implode("', '", array_map('intval', array_keys($record['closed']))) . "')" . pg_catalog_bin_live_sql('products')) as $row) {

			$products[(int)$row['id']] = array('name' => (string)$row['name'], 'enabled' => ((int)$row['enabled'] === 1));

		}

	}

	$alerts_on = array();

	foreach (pg_sales_watch_alert_names() as $code) {

		if (!empty($settings[$code])) {

			$alerts_on[] = $code;

		}

	}

	api_ok(array(
		'watching'       => $commerce && pg_ops_alerts_enabled() && (bool)$alerts_on,
		'alerts_on'      => $alerts_on,
		'level'          => (string)$settings['level'],
		'close_products' => (bool)$settings['close_products'],
		'last_pass'      => api_time(is_array($state) ? $state['pass'] : 0),
		'channels'       => $channels,
		'alerts'         => $alerts,
		'closed'         => api_sales_watch_closed_present($record['closed'], $products)
	));

}

// One channel of GET /reports/sales-watch. Pure: $channel is a row of
// pg_sales_watch_channels() (label, end: where its orders are known up to),
// $counts its hourly counts before the hour index $cut, $times its order
// times, $paused the end of its pause (or the store's), 0 for none.
function api_sales_watch_channel_present($key, $channel, $counts, $cut, $times, $paused, $now) {

	$end = (int)$channel['end'];

	$norm = pg_sales_watch_norm($counts, $cut);

	$orders = 0;

	foreach ($times as $time) {

		if (((int)$time > ($end - (12 * 3600))) && ((int)$time <= $end)) {

			$orders++;

		}

	}

	$hours = array();

	if ($norm !== null) {

		$first = intdiv((int)$now, 3600);

		for ($hour = $first; $hour < ($first + 12); $hour++) {

			$hours[] = array(
				'from'     => api_time($hour * 3600),
				'expected' => round(pg_sales_watch_expected_hour($norm, $hour), 2)
			);

		}

	}

	return array(
		'key'                    => (string)$key,
		'label'                  => (string)$channel['label'],
		'learnt'                 => ($norm !== null),
		'data_until'             => api_time($end),
		'paused_until'           => ((int)$paused > (int)$now) ? api_time($paused) : null,
		'last_12_hours_orders'   => $orders,
		'last_12_hours_expected' => ($norm !== null) ? round(pg_sales_watch_expected($norm, $end - (12 * 3600), $end), 2) : null,
		'next_12_hours_expected' => ($norm !== null) ? round(pg_sales_watch_expected($norm, (int)$now, (int)$now + (12 * 3600)), 2) : null,
		'next_hours'             => $hours
	);

}

// One sales alert, a notifications row (action 'ops', order_total the code).
// Pure.
function api_sales_watch_alert_present($row, $codes) {

	$code = (string)$row['code'];

	$title = (string)$row['title'];

	return array(
		'code'       => $code,
		'label'      => isset($codes[$code]['label']) ? (string)$codes[$code]['label'] : $code,
		// A row with nothing to add carries its own code as its text.
		'detail'     => ($title !== $code) ? $title : '',
		'at'         => api_time($row['timestamp']),
		'product_id' => ((int)$row['product_id'] > 0) ? (int)$row['product_id'] : null
	);

}

// The products the watch took off sale that still are, newest first. Pure:
// $closed is the record's closed list, $products id => name and enabled for
// those that still exist. One put back on sale since the last pass is left
// out here; the next pass takes it off the record.
function api_sales_watch_closed_present($closed, $products) {

	uasort($closed, function ($a, $b) {

		return (int)$b['at'] - (int)$a['at'];

	});

	$out = array();

	foreach ($closed as $id => $entry) {

		if (!isset($products[$id]) || $products[$id]['enabled']) {

			continue;

		}

		$out[] = array(
			'product_id' => (int)$id,
			'name'       => (string)$products[$id]['name'],
			'closed_at'  => api_time($entry['at']),
			'price'      => api_money($entry['price']),
			'reason'     => (string)$entry['reason']
		);

	}

	return $out;

}

// The ad services' events (includes/fn/ad_conversions_log.php): per service
// the counts of the last 30 days and the newest refusals. A read only.
function api_reports_ad_conversions($params) {

	api_ok(api_ad_conversions_present(pg_ad_conversion_report(time())));

}

// GET /reports/ad-conversions' answer from pg_ad_conversion_report(). Pure.
function api_ad_conversions_present($report) {

	$services = array();

	foreach ($report['services'] as $service) {

		$errors = array();

		foreach ($service['errors'] as $error) {
			$errors[] = array(
				'at'          => api_time($error['at']),
				'event'       => (string)$error['event'],
				'status_code' => ((int)$error['status_code'] > 0) ? (int)$error['status_code'] : null,
				'error'       => (string)$error['error']
			);
		}

		$days = array();

		foreach ($service['days'] as $day) {
			$days[] = array(
				'day'              => (string)$day['day'],
				'sent'             => (int)$day['sent'],
				'failed'           => (int)$day['failed'],
				'sent_with_person' => (int)$day['sent_with_person'],
				'share'            => ($day['share'] === null) ? null : (int)$day['share']
			);
		}

		$services[] = array(
			'service'   => (string)$service['service'],
			'name'      => (string)$service['name'],
			'connected' => (bool)$service['connected'],
			'events'    => array_values(array_map('strval', $service['events'])),
			'waiting'   => (int)$service['waiting'],
			'totals'    => array(
				'sent'             => (int)$service['totals']['sent'],
				'failed'           => (int)$service['totals']['failed'],
				'sent_with_person' => (int)$service['totals']['sent_with_person'],
				'share'            => ($service['totals']['share'] === null) ? null : (int)$service['totals']['share']
			),
			'days'      => $days,
			'errors'    => $errors
		);

	}

	return array('services' => $services);

}

// What api_reports_ad_conversions() answers with, declared for the OpenAPI
// document.
function api_ad_conversions_report_schema() {

	$counts = array(
		'sent'             => 'integer',
		'failed'           => 'integer',
		'sent_with_person' => 'integer',
		'share'            => 'integer?'
	);

	return array(
		'services' => array(array(
			'service'   => 'string',
			'name'      => 'string',
			'connected' => 'boolean',
			'events'    => 'string[]',
			'waiting'   => 'integer',
			'totals'    => $counts,
			'days'      => array(array('day' => 'date') + $counts),
			'errors'    => array(array(
				'at'          => 'datetime?',
				'event'       => 'string',
				'status_code' => 'integer?',
				'error'       => 'string'
			))
		))
	);

}

// What api_reports_sales_watch() answers with, declared for the OpenAPI
// document.
function api_sales_watch_report_schema() {

	return array(
		'watching'       => 'boolean',
		'alerts_on'      => 'string[]',
		'level'          => 'string',
		'close_products' => 'boolean',
		'last_pass'      => 'datetime?',
		'channels'       => array(array(
			'key'                    => 'string',
			'label'                  => 'string',
			'learnt'                 => 'boolean',
			'data_until'             => 'datetime?',
			'paused_until'           => 'datetime?',
			'last_12_hours_orders'   => 'integer',
			'last_12_hours_expected' => 'number?',
			'next_12_hours_expected' => 'number?',
			'next_hours'             => array(array('from' => 'datetime', 'expected' => 'number'))
		)),
		'alerts'         => array(array(
			'code'       => 'string',
			'label'      => 'string',
			'detail'     => 'string',
			'at'         => 'datetime?',
			'product_id' => 'integer?'
		)),
		'closed'         => array(array(
			'product_id' => 'integer',
			'name'       => 'string',
			'closed_at'  => 'datetime?',
			'price'      => 'integer',
			'reason'     => 'string'
		))
	);

}

// GET /reports/delivery: the carrier scorecard of one month, or of the twelve
// months ending with it, with the shipping methods whose typed transit days
// differ from what their carrier measured. Read from the parcel rows as the
// Carrier Scorecard screen reads them; nothing is judged or changed.
function api_reports_delivery($params) {

	$now = time();

	$month = isset($params['month']) ? (string)$params['month'] : '';

	if (($month !== '') && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {

		api_fail_validation(lang('month must be a month written as YYYY-MM.'), 'month');

	}

	$month = pg_delivery_scorecard_month($month, $now);

	$year = (isset($params['period']) && ($params['period'] === 'year'));

	$shipping = defined('ECOMMERCE') && (ECOMMERCE === true) && defined('ECOMMERCE_SHIPPING') && (ECOMMERCE_SHIPPING === true);

	$months = $year ? pg_delivery_scorecard_months($month) : array($month);

	$view = pg_delivery_scorecard_month_view(array(), $month);

	$arrival = array();

	$summary = null;

	if ($shipping) {

		$from = pg_delivery_scorecard_bounds($months[0])[0];

		$to = pg_delivery_scorecard_bounds($month)[1];

		$state = pg_delivery_scorecard_read($from, $to, !$year);

		$view = $year
			? pg_delivery_scorecard_year_view($state, $months)
			: pg_delivery_scorecard_month_view($state, $month);

		$arrival = pg_delivery_tower_arrival_checks_read();

		$summary = pg_delivery_tower_summary();

	}

	$names = array();

	foreach ($view['carriers'] as $row) {

		$names[$row['carrier']] = ($row['carrier'] === '') ? '' : pg_delivery_scorecard_carrier_name($row['carrier']);

	}

	api_ok(api_delivery_report_present($view, $year, $months, $names, $arrival, $shipping, is_array($summary) ? (int)$summary['at'] : 0));

}

// One scorecard figure set as the API gives it: the shares and days are null
// under PG_DELIVERY_TOWER_MIN_PARCELS parcels (enough false), as the screen's
// "Too few parcels". Pure.
function api_delivery_figures_present($figures) {

	$enough = !empty($figures['enough']);

	$value = function ($key) use ($figures, $enough) {

		return ($enough && isset($figures[$key]) && ($figures[$key] !== null)) ? round((float)$figures[$key], 1) : null;

	};

	return array(
		'parcels'  => (int)$figures['parcels'],
		'enough'   => $enough,
		'on_time'  => $value('on_time'),
		'median'   => $value('median'),
		'problem'  => $value('problem'),
		'returned' => $value('returned')
	);

}

// The answer of GET /reports/delivery. Pure: $view is
// pg_delivery_scorecard_month_view() or _year_view(), $names carrier key =>
// name, $arrival pg_delivery_tower_arrival_checks_read(), $measured_at when
// the nightly summary the arrival check reads was made (0 for none).
function api_delivery_report_present($view, $year, $months, $names, $arrival, $shipping, $measured_at) {

	$carriers = array();

	foreach ($view['carriers'] as $row) {

		$carrier = (string)$row['carrier'];

		$provinces = array();

		$by_month = array();

		if ($year) {

			foreach ($months as $key) {

				$by_month[] = array('month' => (string)$key, 'figures' => api_delivery_figures_present($row['months'][$key]));

			}

		} else {

			foreach ($row['provinces'] as $province) {

				$provinces[] = array('name' => (string)$province['name'], 'figures' => api_delivery_figures_present($province['figures']));

			}

		}

		$carriers[] = array(
			'carrier'   => ($carrier === '') ? null : $carrier,
			'name'      => isset($names[$carrier]) ? (string)$names[$carrier] : $carrier,
			'figures'   => api_delivery_figures_present($row['figures']),
			'provinces' => $provinces,
			'months'    => $by_month
		);

	}

	$methods = array();

	foreach ($arrival as $check) {

		$methods[] = array(
			'method_id' => (int)$check['id'],
			'name'      => (string)$check['name'],
			'measured'  => round((float)$check['check']['measured'], 1),
			'set'       => (int)$check['check']['set']
		);

	}

	return array(
		'shipping'    => (bool)$shipping,
		'period'      => $year ? 'year' : 'month',
		'from_month'  => (string)$months[0],
		'to_month'    => (string)$months[count($months) - 1],
		'min_parcels' => PG_DELIVERY_TOWER_MIN_PARCELS,
		'total'       => api_delivery_figures_present($view['total']),
		'carriers'    => $carriers,
		'arrival'     => $methods,
		'measured_at' => api_time($measured_at)
	);

}

// What api_reports_delivery() answers with, declared for the OpenAPI
// document.
function api_delivery_report_schema() {

	$figures = array(
		'parcels'  => 'integer',
		'enough'   => 'boolean',
		'on_time'  => 'number?',
		'median'   => 'number?',
		'problem'  => 'number?',
		'returned' => 'number?'
	);

	return array(
		'shipping'    => 'boolean',
		'period'      => 'string',
		'from_month'  => 'string',
		'to_month'    => 'string',
		'min_parcels' => 'integer',
		'total'       => $figures,
		'carriers'    => array(array(
			'carrier'   => 'string?',
			'name'      => 'string',
			'figures'   => $figures,
			'provinces' => array(array('name' => 'string', 'figures' => $figures)),
			'months'    => array(array('month' => 'string', 'figures' => $figures))
		)),
		'arrival'     => array(array(
			'method_id' => 'integer',
			'name'      => 'string',
			'measured'  => 'number',
			'set'       => 'integer'
		)),
		'measured_at' => 'datetime?'
	);

}
