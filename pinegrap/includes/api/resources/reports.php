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
