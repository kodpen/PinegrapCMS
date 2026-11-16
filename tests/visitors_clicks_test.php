<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Clicks and scrolling" section of the Visitors overview
 * (includes/visitors/clicks.php) and GET /reports/page-clicks
 * (includes/api/resources/page_clicks.php): labels and addresses sent by
 * visitors' browsers are printed as text, the route asks for no scope beyond
 * visitors:read, and the section says what it would keep while the switch
 * is off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/clicks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/page_clicks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// An element whose label and address hold markup, as a crafted beacon could
// leave them after the fold.
function visitors_clicks_test_element()
{
	return array(
		'area'   => 'main',
		'role'   => 'link',
		'url'    => '/a"><img src=x onerror=alert(1)>',
		'label'  => '<script>alert("x")</script> Buy',
		'clicks' => 7,
		'share'  => 0.35,
	);
}

function visitors_clicks_test_period()
{
	return pg_visitor_period(array('p' => '30d'), strtotime('2026-11-16 12:00:00'));
}

function test_visitors_clicks_section_prints_labels_as_text()
{
	$element = visitors_clicks_test_element();
	$period = visitors_clicks_test_period();

	$list = pg_visitors_table(
		array('Page', 'Views', 'Half', 'Most clicked', 'Share'),
		pg_visitors_clicks_page_rows(array(array('page_id' => 4, 'page_name' => '<b>Home</b>', 'views' => 20, 'half_tenth' => 6, 'top' => $element)), $period, '')
	);

	pg_assert_true(strpos($list, '<script>') === false, 'no script tag from a label in the pages list');
	pg_assert_contains('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Buy', $list, 'the label as text');
	pg_assert_contains('&lt;b&gt;Home&lt;/b&gt;', $list, 'the page name as text');

	$page = pg_visitors_table(
		array('Part', 'Kind', 'Label', 'Address', 'Clicks', 'Share'),
		pg_visitors_clicks_element_rows(array('views' => 20, 'elements' => array($element), 'other' => 3, 'reach' => array()))
	);

	pg_assert_true(strpos($page, '<script>') === false, 'no script tag in a page\'s elements');
	pg_assert_true(strpos($page, '<img') === false, 'no tag from an address');
	pg_assert_contains('&lt;img src=x onerror=alert(1)&gt;', $page, 'the address as text');
	pg_assert_contains(h(lang('Other elements')), $page, '"other" as one line');

	// The name a reader is told falls back from label to address to kind.
	pg_assert_same('<script>alert("x")</script> Buy', pg_visitors_clicks_name($element), 'returned raw, escaped where printed');
	pg_assert_same('/x', pg_visitors_clicks_name(array('label' => ' ', 'url' => '/x', 'role' => 'link')));
	pg_assert_same(lang('Button'), pg_visitors_clicks_name(array('label' => '', 'url' => '', 'role' => 'button')));
}

function test_visitors_clicks_route_gives_labels_as_text()
{
	$element = visitors_clicks_test_element();
	$row = api_page_clicks_row_present(array('page_id' => '4', 'page_name' => '', 'views' => '20', 'half_tenth' => null, 'top' => $element));

	pg_assert_same($element['label'], $row['top']['label'], 'the label unchanged: no markup is made of it');
	pg_assert_same($element['url'], $row['top']['url']);
	pg_assert_same(null, $row['page_name'], 'an empty name is null');
	pg_assert_same(null, $row['half_tenth']);

	$back = json_decode(json_encode($row), true);
	pg_assert_same($element['label'], $back['top']['label'], 'a JSON string: the client gets the text as it was folded');

	$page = api_page_clicks_page_present(4, 'Home', array('views' => 20, 'reach' => pg_page_clicks_reach(array(2 => 10, 10 => 10)), 'elements' => array($element), 'other' => 3));

	pg_assert_same(11, count($page['reach']), 'every tenth 0-10');
	pg_assert_same(array('tenth' => 5, 'share' => 0.5), $page['reach'][5]);
	pg_assert_same($element['label'], $page['elements'][0]['label']);
	pg_assert_same(3, $page['other']);
}

function test_visitors_clicks_route_asks_no_new_scope()
{
	$route = null;

	foreach (api_schema() as $row) {
		if (isset($row['id']) && $row['id'] === 'reports.page_clicks') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/reports/page-clicks', $route['path']);
	pg_assert_same('visitors:read', $route['scope'], 'the scope every visitor report asks for');

	$scopes = api_scopes_all();

	pg_assert_true(api_scope_is_valid('visitors:read'), 'visitors:read is an existing scope');

	foreach ($scopes as $scope) {
		pg_assert_true(stripos($scope, 'click') === false, 'no scope of its own: ' . $scope);
	}

	foreach ($route['params'] as $param) {
		pg_assert_same('query', $param['in'], 'read only: ' . $param['name']);
	}
}

function test_visitors_clicks_off_says_what_is_kept_and_how_to_turn_on()
{
	$manager = pg_visitors_clicks_off(array('role' => 2));
	$user = pg_visitors_clicks_off(array('role' => 3));

	pg_assert_same(2, substr_count($manager, 'pg-visitors-clicks-off-text'), 'two lines');
	pg_assert_contains(h(lang('No cookie, no identifier and nothing about a visitor is kept; signed-in members and visitors who refused statistics are never counted.')), $manager);
	pg_assert_contains(h(pg_settings_link('features', 'pgset-features')), $manager, 'a manager gets the button to the switch');
	pg_assert_true(strpos($user, 'btn') === false, 'no button for whoever cannot change Site Settings');
	pg_assert_contains(h(lang('A manager can turn it on in Site Settings, Features.')), $user);

	pg_assert_false(pg_visitors_clicks_on(), 'off while nothing is stored');

	// Without a database the switch reads off: the section is never hidden.
	$section = pg_visitors_section_clicks(visitors_clicks_test_period(), array('role' => 2));
	pg_assert_contains('id="clicks"', $section, 'the section is drawn while off');
	pg_assert_contains('pg-visitors-clicks-off', $section, 'with its empty state');
}

function test_visitors_clicks_request_and_figures()
{
	pg_assert_same(array('device' => 'mobile', 'page_id' => 12), pg_visitors_clicks_request(array('clicks_device' => 'mobile', 'clicks_page' => '12')));
	pg_assert_same(array('device' => '', 'page_id' => 0), pg_visitors_clicks_request(array('clicks_device' => 'tv', 'clicks_page' => '1 OR 1')));
	pg_assert_same(array('device' => '', 'page_id' => 0), pg_visitors_clicks_request(array('clicks_device' => array('x'), 'clicks_page' => array('1'))));

	$url = pg_visitors_clicks_url(visitors_clicks_test_period(), 12, 'mobile');
	pg_assert_contains('p=30d', $url, 'the period kept');
	pg_assert_contains('clicks_device=mobile&clicks_page=12#clicks', $url);
	pg_assert_same('visitors.php?p=30d#clicks', pg_visitors_clicks_url(visitors_clicks_test_period(), 0, ''));

	pg_assert_same(pg_visitors_percent(60, 0), pg_visitors_clicks_tenth(6));
	pg_assert_same('', pg_visitors_clicks_tenth(null));
	pg_assert_same('–', pg_visitors_clicks_share(null));
	pg_assert_same(lang('Footer'), pg_visitors_clicks_area('footer'));
	pg_assert_same(lang('Page'), pg_visitors_clicks_area('nowhere'));

	$bars = pg_visitors_clicks_reach_rows(pg_page_clicks_reach(array(2 => 10, 10 => 10)));
	pg_assert_same(10, count($bars), 'one bar a tenth, 10 % to 100 %');
	pg_assert_same(100.0, $bars[0]['value']);
	pg_assert_same(50.0, $bars[9]['value']);
	pg_assert_same(array(), pg_visitors_clicks_reach_rows(pg_page_clicks_reach(array())), 'no bars without measurements');
}
