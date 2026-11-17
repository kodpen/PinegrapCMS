<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Content review API (includes/api/resources/content_review.php):
 * the filters per role, the keyset page over the screen's branches, an item
 * as answered, what a body asks of an item (a user-role caller's rows), the
 * dry run's place and the routes and scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/content_review.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// The branches of a filter as the screen builds them, with collections.
function _api_content_review_test_branches($filter, $user_id = 7, $role = 0)
{
	return pg_content_review_list_branches($filter, pg_content_review_list_condition($filter, '2026-11-16', $user_id, $role), array(
		'bin' => array(9), 'records' => true, 'has_tree' => "'0'", 'checked_at' => "'0'", 'traffic' => '', 'views' => 'NULL',
	));
}

function _api_content_review_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The filters: the screen's for each role; a user (role 3) gets mine,
// overdue and soon only.
function test_api_content_review_filters_per_role()
{
	pg_assert_same(array('all', 'overdue', 'soon', 'nobody', 'notset', 'mine'), api_content_review_filters(0));
	pg_assert_same(array('all', 'overdue', 'soon', 'nobody', 'notset', 'mine'), api_content_review_filters(2));
	pg_assert_same(array('mine', 'overdue', 'soon'), api_content_review_filters(3));

	$route = _api_content_review_test_route('content_review.list');
	pg_assert_same(array('all', 'overdue', 'soon', 'nobody', 'notset', 'mine'), $route['params'][0]['values'], 'the route offers the screen\'s filters');
}

// Each filter's branches are paged by their own key, after the cursor.
function test_api_content_review_filters_page_by_key()
{
	pg_assert_same(array('column' => 'cr.item_id', 'kind' => 'page'), api_content_review_branch_key(_api_content_review_test_branches('overdue')[0]['sql']));
	pg_assert_same(array('column' => 'cr.item_id', 'kind' => 'record'), api_content_review_branch_key(_api_content_review_test_branches('overdue')[1]['sql']));

	$all = _api_content_review_test_branches('all');
	pg_assert_same(4, count($all), 'all: dated and undated, pages and records');
	pg_assert_same(array('column' => 'page.page_id', 'kind' => 'page'), api_content_review_branch_key($all[2]['sql']));
	pg_assert_same(array('column' => 'forms.id', 'kind' => 'record'), api_content_review_branch_key($all[3]['sql']));

	$notset = api_content_review_page_sql(_api_content_review_test_branches('notset'), '', 0, 51);
	pg_assert_same(2, count($notset), 'notset: only the undated branches');
	pg_assert_contains(' AND (page.page_id >= ?) ORDER BY page.page_id LIMIT 51', $notset[0]['sql']);
	pg_assert_contains(' AND (forms.id >= ?) ORDER BY forms.id LIMIT 51', $notset[1]['sql']);

	$overdue = api_content_review_page_sql(_api_content_review_test_branches('overdue'), '', 0, 11);
	pg_assert_contains('cr.review_on < ?', $overdue[0]['sql']);
	pg_assert_same(array('2026-11-16', '1'), $overdue[0]['params'], 'today bound, then the first page id');

	pg_assert_same(1, count(api_content_review_page_sql(_api_content_review_test_branches('all'), 'page', 0, 5)) - 1, 'kind page keeps the two page branches');
	pg_assert_same(null, api_content_review_branch_key('SELECT 1'));
}

// The cursor is the last list id: a page's is id * 2, a record's id * 2 + 1.
function test_api_content_review_cursor_first_item()
{
	pg_assert_same(1, api_content_review_first_item('page', 0));
	pg_assert_same(0, api_content_review_first_item('record', 0));
	pg_assert_same(3, api_content_review_first_item('page', 4), 'after page 2, page 3');
	pg_assert_same(2, api_content_review_first_item('record', 4), 'after page 2, record 2 (list id 5)');
	pg_assert_same(3, api_content_review_first_item('page', 5), 'after record 2, page 3');
	pg_assert_same(3, api_content_review_first_item('record', 5), 'after record 2, record 3');

	$one = api_content_review_page_sql(_api_content_review_test_branches('all'), '', 0, 1, array('kind' => 'record', 'item_id' => 12));
	pg_assert_same(2, count($one), 'one record: its dated and undated branch');
	pg_assert_contains(' AND (forms.id = ?)', $one[1]['sql']);
	pg_assert_same('12', end($one[1]['params']));
}

// A user-role (3) caller's rows: every filter it may ask for is narrowed to
// the items it is the person of.
function test_api_content_review_user_role_rows()
{
	foreach (api_content_review_filters(3) as $filter) {
		$branches = api_content_review_page_sql(_api_content_review_test_branches($filter, 41, 3), '', 0, 10);
		pg_assert_true(count($branches) > 0, $filter);

		foreach ($branches as $branch) {
			pg_assert_contains('cr.user_id = ?', $branch['sql'], $filter);
			pg_assert_true(in_array('41', $branch['params'], true), $filter . ': narrowed to the caller');
		}
	}

	$own = array('user_id' => 41, 'review_on' => '2026-11-01', 'interval_months' => 6, 'reviewed_at' => 0);
	$other = array('user_id' => 42) + $own;

	pg_assert_same('', api_content_review_update_plan(array('reviewed' => true), $own, 41, 3)['error'], 'marks its own item');
	pg_assert_same('not_found', api_content_review_update_plan(array('reviewed' => true), $other, 41, 3)['error'], 'another person\'s item is 404');
	pg_assert_same('not_found', api_content_review_update_plan(array('reviewed' => true), null, 41, 3)['error'], 'an item without a row is 404');

	$plan = api_content_review_update_plan(array('review_on' => '2027-01-01'), $own, 41, 3);
	pg_assert_same(array('forbidden', 'review_on'), array($plan['error'], $plan['field']), 'only reviewed');

	pg_assert_true(in_array('content_review:read', api_owner_scopes(array('role' => 3, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false)), true), 'a user may hold the scope');
	pg_assert_true(in_array('content_review:write', api_owner_scopes(array('role' => 0, 'manage_ecommerce' => true, 'manage_contacts' => true, 'manage_forms' => true)), true));
}

// What a body asks: only the fields sent change.
function test_api_content_review_update_plan()
{
	$stored = array('user_id' => 5, 'review_on' => '2026-12-01', 'interval_months' => 3, 'reviewed_at' => 0);

	$plan = api_content_review_update_plan(array('review_on' => '2027-02-28'), $stored, 1, 0);
	pg_assert_same(array('', 5, '2027-02-28', 3, true, false), array($plan['error'], $plan['user_id'], $plan['review_on'], $plan['interval_months'], $plan['save'], $plan['reviewed']));

	$plan = api_content_review_update_plan(array('user_id' => '', 'review_on' => ''), $stored, 1, 0);
	pg_assert_same(array(0, null), array($plan['user_id'], $plan['review_on']), 'null clears');

	pg_assert_same(12, api_content_review_update_plan(array('interval_months' => '12'), null, 1, 0)['interval_months']);
	pg_assert_same('date', api_content_review_update_plan(array('review_on' => '2027-02-30'), $stored, 1, 0)['error']);
	pg_assert_same('nothing', api_content_review_update_plan(array(), $stored, 1, 0)['error']);
	pg_assert_same('nothing', api_content_review_update_plan(array('reviewed' => false), $stored, 1, 0)['error']);

	$present = api_content_review_present(array('item_kind' => 'record', 'item_id' => 12, 'item_name' => 'R-1', 'kind_name' => 'Events',
		'user_id' => 0, 'review_on' => '2026-11-10', 'interval_months' => 6, 'reviewed_at' => 0, 'reviewed_by' => 0), '2026-11-16');
	pg_assert_same(array('kind' => 'record', 'id' => 12, 'name' => 'R-1', 'collection' => 'Events', 'user_id' => null, 'review_on' => '2026-11-10',
		'state' => 'overdue', 'interval_months' => 6, 'reviewed_at' => null, 'reviewed_by' => null), $present);
	pg_assert_same(array_keys(api_content_review_schema()), array_keys($present), 'the declaration');
}

// The dry run: declared, and stopped only after every check, directly above
// the first write.
function test_api_content_review_dry_run()
{
	$route = _api_content_review_test_route('content_review.update');
	pg_assert_true(!empty($route['dry_run']));
	pg_assert_same('content_review:write', $route['scope']);
	pg_assert_same('content_review:read', _api_content_review_test_route('content_review.list')['scope']);
	pg_assert_same(array('PATCH'), $route['also_accepts']);

	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/content_review.php');
	$handler = substr($file, strpos($file, 'function api_content_review_update($params)'));
	$stop = strpos($handler, 'api_dry_run_stop(');

	foreach (array("\$plan['error'] === 'not_found'", "\$plan['error'] === 'forbidden'", "\$plan['error'] === 'date'", 'pg_content_review_user_may_edit(', 'pg_content_review_item_counts(') as $check) {
		pg_assert_true(strpos($handler, $check) < $stop, $check . ' before the stop');
	}

	pg_assert_true($stop < strpos($handler, 'pg_content_review_save('), 'the stop before the first write');
	pg_assert_true($stop < strpos($handler, 'pg_content_review_mark_reviewed('), 'and before marking');
}
