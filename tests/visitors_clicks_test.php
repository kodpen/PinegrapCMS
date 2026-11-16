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
	pg_assert_true(strpos($page, 'Other elements') === false, '"other" is no row of the table');

	$other = pg_visitors_clicks_other(array('views' => 20, 'other' => 3));
	pg_assert_same(1, substr_count($other, '<p class="pg-visitors-note">'), '"other" as one line under the table');
	pg_assert_contains(h(pg_visitors_clicks_share(0.15)), $other, 'with its share of the views');
	pg_assert_same('', pg_visitors_clicks_other(array('views' => 20, 'other' => 0)), 'no line without other clicks');

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
	$period = visitors_clicks_test_period();
	$manager = pg_visitors_clicks_off(array('role' => 2), $period);
	$user = pg_visitors_clicks_off(array('role' => 3), $period);

	pg_assert_same(2, substr_count($manager, 'pg-visitors-clicks-off-text'), 'two lines');
	pg_assert_contains(h(lang('No cookie, no identifier and nothing about a visitor is kept; signed-in members and visitors who refused statistics are never counted.')), $manager);
	pg_assert_contains('<form method="post" action="visitors_clicks_switch.php"', $manager, 'a manager gets the button, a form post');
	pg_assert_contains('name="token"', $manager, 'with the form token');
	pg_assert_contains('name="back" value="p=30d"', $manager, 'and the way back to the period');
	pg_assert_contains(h(lang('Turn on')), $manager, 'the button says what it does');

	// A User (role 3) given Visitors sees the two lines and no button.
	pg_assert_same(2, substr_count($user, 'pg-visitors-clicks-off-text'), 'the two lines for a User too');
	pg_assert_true(strpos($user, '<form') === false && strpos($user, 'btn') === false, 'no button for a User');
	pg_assert_contains(h(lang('A manager can turn it on in Site Settings, Features.')), $user);

	// Without a database the switch reads off: the section is never hidden.
	$section = pg_visitors_section_clicks($period, array('role' => 2));
	pg_assert_contains('id="clicks"', $section, 'the section is drawn while off');
	pg_assert_contains('pg-visitors-clicks-off', $section, 'with its empty state');
}

// Who may turn the switch on: the Features save's right, role up to 2;
// never a User (role 3), whatever Visitors right they hold.
function test_visitors_clicks_switch_right_is_the_features_right()
{
	pg_assert_true(pg_visitors_clicks_may_switch(array('role' => 0)));
	pg_assert_true(pg_visitors_clicks_may_switch(array('role' => '1')));
	pg_assert_true(pg_visitors_clicks_may_switch(array('role' => 2)));
	pg_assert_false(pg_visitors_clicks_may_switch(array('role' => 3, 'manage_visitors' => 1)), 'a User with the Visitors right');
	pg_assert_false(pg_visitors_clicks_may_switch(array()), 'no role');
	pg_assert_false(pg_visitors_clicks_may_switch(array('role' => 'x')));

	// The post: the Features right first, then the token, then the module's
	// saver; it never writes the setting itself.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/visitors_clicks_switch.php');
	$right = strpos($source, "validate_area_access(\$user, 'manager');");
	$post = strpos($source, "!== 'POST'");
	$token = strpos($source, 'validate_token_field();');
	$save = strpos($source, 'pg_page_clicks_switch_save(true)');

	pg_assert_true($right !== false && $post !== false && $token !== false && $save !== false, 'every check is there');
	pg_assert_true(($right < $post) && ($post < $token) && ($token < $save), 'the right, the method and the token come before the write');
	pg_assert_true(strpos($source, 'validate_visitors_access') === false, 'not the Visitors screen\'s right');
	pg_assert_true(strpos($source, 'pg_config_text_save') === false, 'the setting is written only by the module');
}

// The switch's one reader and saver in the page clicks module: the setting
// '1' on, removed off; missing reads off.
function test_visitors_clicks_switch_reader_and_saver()
{
	pg_assert_same('page_clicks', PG_PAGE_CLICKS_SWITCH);
	pg_assert_same('1', pg_page_clicks_switch_value(true));
	pg_assert_same('', pg_page_clicks_switch_value(false), 'off removes the row');
	pg_assert_false(pg_page_clicks_switch_on(), 'off while nothing is stored');

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_clicks.php');
	pg_assert_contains('pg_config_text_save(PG_PAGE_CLICKS_SWITCH, pg_page_clicks_switch_value((bool) $on))', $module, 'the saver writes the one setting');
	pg_assert_same(1, substr_count($module, 'pg_config_text_save('), 'one write of the switch');

	$section = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/visitors/clicks.php');
	pg_assert_contains('pg_page_clicks_switch_on()', $section, 'the section asks the module');
	pg_assert_true(strpos($section, "pg_config_text('page_clicks')") === false, 'and reads no copy of its own');
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
