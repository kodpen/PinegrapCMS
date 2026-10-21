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
	// site's own timezone makes of it - the same day the panel shows, not the
	// day UTC would have made of an order placed at half past two in the
	// morning.
	$format = ($interval === 'month') ? '%Y-%m' : '%Y-%m-%d';

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
	$rows = api_rows("SELECT DATE_FORMAT(FROM_UNIXTIME(order_date), '" . $format . "') AS period,
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
