<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GET /reports/page-clicks: the clicks on links and buttons and the scroll
 * depth of the site's pages, from the day rollup of includes/fn/page_clicks.php,
 * the figures the "Clicks and scrolling" section of Visitors shows. Without
 * page_id the period's pages with their views, the tenth half the views
 * reach and their most clicked element; with it that page's elements, the
 * share of views reaching each tenth and the clicks counted as "other".
 * Labels and addresses were sent by visitors' browsers: they are text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

function api_reports_page_clicks($params) {

	if (!function_exists('pg_page_clicks_ready') || !pg_page_clicks_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$window = api_visitors_window($params, 400);

	$device = isset($params['device']) ? (string)$params['device'] : '';

	$page_id = isset($params['page_id']) ? (int)$params['page_id'] : 0;

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$page = null;

	$pages = array();

	if ($page_id > 0) {

		$name = db_value("SELECT page_name FROM page WHERE page_id = '" . $page_id . "'");

		if (($name === null) || ($name === false)) {

			api_fail_not_found(lang('Page'));

		}

		$page = api_page_clicks_page_present($page_id, (string)$name, pg_page_clicks_page($page_id, $window['first'], $window['last'], $device, $limit));

	} else {

		foreach (pg_page_clicks_pages($window['first'], $window['last'], $device, $limit) as $row) {

			$top = pg_page_clicks_page((int)$row['page_id'], $window['first'], $window['last'], $device, 1);

			$row['top'] = $top['elements'] ? $top['elements'][0] : null;

			$pages[] = api_page_clicks_row_present($row);

		}

	}

	api_ok(api_page_clicks_present($window['first'], $window['last'], $device, $page_id, $pages, $page));

}

// The answer's frame. Pure.
function api_page_clicks_present($first, $last, $device, $page_id, $pages, $page) {

	return array(
		'from'    => $first,
		'to'      => $last,
		'device'  => ($device !== '') ? $device : null,
		'page_id' => ($page_id > 0) ? (int)$page_id : null,
		'pages'   => $pages,
		'page'    => $page
	);

}

// One element as the answer gives it: label and address as text. Pure.
function api_page_clicks_element_present($element) {

	return array(
		'area'   => (string)$element['area'],
		'role'   => (string)$element['role'],
		'url'    => (string)$element['url'],
		'label'  => (string)$element['label'],
		'clicks' => (int)$element['clicks'],
		'share'  => ($element['share'] === null) ? null : (float)$element['share']
	);

}

// A page of the period's list. Pure.
function api_page_clicks_row_present($row) {

	return array(
		'page_id'    => (int)$row['page_id'],
		'page_name'  => ((string)$row['page_name'] !== '') ? (string)$row['page_name'] : null,
		'views'      => (int)$row['views'],
		'half_tenth' => ($row['half_tenth'] === null) ? null : (int)$row['half_tenth'],
		'top'        => !empty($row['top']) ? api_page_clicks_element_present($row['top']) : null
	);

}

// One page's figures (pg_page_clicks_page()). Pure.
function api_page_clicks_page_present($page_id, $name, $figures) {

	$reach = array();

	for ($tenth = 0; $tenth <= 10; $tenth++) {

		$share = isset($figures['reach'][$tenth]) ? $figures['reach'][$tenth] : null;

		$reach[] = array('tenth' => $tenth, 'share' => ($share === null) ? null : (float)$share);

	}

	$elements = array();

	foreach ($figures['elements'] as $element) {

		$elements[] = api_page_clicks_element_present($element);

	}

	return array(
		'page_id'   => (int)$page_id,
		'page_name' => ($name !== '') ? $name : null,
		'views'     => (int)$figures['views'],
		'reach'     => $reach,
		'elements'  => $elements,
		'other'     => (int)$figures['other']
	);

}

// What api_reports_page_clicks() returns.
function api_page_clicks_report_schema() {

	return array(
		'from'    => 'string',
		'to'      => 'string',
		'device'  => 'string?',
		'page_id' => 'integer?',
		'pages'   => array(array(
			'page_id'    => 'integer',
			'page_name'  => 'string?',
			'views'      => 'integer',
			'half_tenth' => 'integer?',
			'top'        => 'PageClicksElement?'
		)),
		'page'    => 'PageClicksPage?'
	);

}

// What api_page_clicks_element_present() returns.
function api_page_clicks_element_schema() {

	return array(
		'area'   => 'string',
		'role'   => 'string',
		'url'    => 'string',
		'label'  => 'string',
		'clicks' => 'integer',
		'share'  => 'number?'
	);

}

// What api_page_clicks_page_present() returns.
function api_page_clicks_page_schema() {

	return array(
		'page_id'   => 'integer',
		'page_name' => 'string?',
		'views'     => 'integer',
		'reach'     => array(array('tenth' => 'integer', 'share' => 'number?')),
		'elements'  => 'PageClicksElement[]',
		'other'     => 'integer'
	);

}
