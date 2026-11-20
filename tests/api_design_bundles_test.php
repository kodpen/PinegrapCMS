<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the release bundle endpoints of the page design API
 * (includes/api/resources/design_bundles.php): the objects they answer
 * with, the refusals a publish and a take-back answer, the routes and the
 * order of the checks before the first write. No database is used.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design_bundles.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function api_design_bundles_test_route($id)
{
	foreach (api_schema() as $route) {
		if (isset($route['id']) && $route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The source of a function, for the order of its checks.
function api_design_bundles_test_body($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function api_design_bundles_test_row()
{
	return array(
		'id' => 7, 'name' => 'Spring', 'status' => 'open', 'item_count' => 3,
		'publish_at' => 0, 'scheduled_by' => 0, 'created_by' => 2, 'created_at' => 1763000000,
		'updated_at' => 1763000100, 'published_by' => 0, 'published_at' => 0,
		'reverted_by' => 0, 'reverted_at' => 0, 'failed_page_id' => 0, 'failed_reason' => '',
	);
}

function test_api_design_bundle_present_list_and_detail()
{
	$row = api_design_bundles_test_row();
	$listed = api_design_bundle_present($row);

	pg_assert_same(3, $listed['page_count'], 'the list counts the pages');
	pg_assert_same(null, $listed['pages'], 'the list carries no pages');
	pg_assert_same(null, $listed['publish_at'], 'no time is null');
	pg_assert_same(null, $listed['published_by'], 'nobody published it yet');
	pg_assert_same(null, $listed['failed_reason'], 'no failure is null');
	pg_assert_same(2, $listed['created_by'], 'who made it');
	pg_assert_same(array_keys(api_design_bundle_schema()), array_keys($listed), 'every field is declared');

	$row['failed_page_id'] = 12;
	$row['failed_reason'] = 'The page could not be saved.';
	$one = api_design_bundle_present($row, array(array('id' => 1)));

	pg_assert_same(1, $one['page_count'], 'one bundle counts what it carries');
	pg_assert_same(12, $one['failed_page_id'], 'the page a publish stopped at');
	pg_assert_same('The page could not be saved.', $one['failed_reason'], 'its reason as kept');
}

function test_api_design_bundle_page_present_new_and_replacement()
{
	$new = api_design_bundle_page_present(array('id' => 4, 'draft_page_id' => 30, 'live_page_id' => 0, 'draft_name' => 'offer', 'live_name' => '', 'status' => 'waiting', 'added_at' => 0, 'written_at' => 0));

	pg_assert_same('new', $new['kind'], 'a draft of its own is a new page');
	pg_assert_same(null, $new['live_page_id'], 'no live page');
	pg_assert_same(null, $new['live_name'], 'no live name');
	pg_assert_same(null, $new['changed_since'], 'not asked for a new page');
	pg_assert_same(array_keys(api_design_bundle_page_schema()), array_keys($new), 'every field is declared');

	$replacement = api_design_bundle_page_present(array('id' => 5, 'draft_page_id' => 31, 'live_page_id' => 9, 'draft_name' => 'home-copy', 'live_name' => 'home', 'status' => 'waiting'), true);

	pg_assert_same('replacement', $replacement['kind'], 'a copy of a live page');
	pg_assert_same(9, $replacement['live_page_id'], 'the page it stands for');
	pg_assert_same('home', $replacement['live_name'], 'its name');
	pg_assert_same(true, $replacement['changed_since'], 'published by hand since');
}

function test_api_design_bundle_choices()
{
	$sent = api_design_bundle_choices(array(3, '4'), array(5));

	pg_assert_same(0, $sent['error'], 'no clash');
	pg_assert_same(array(3 => 'replace', 4 => 'replace', 5 => 'leave'), $sent['choices'], 'item id => choice');

	$clash = api_design_bundle_choices(array(3), array(3));

	pg_assert_same(3, $clash['error'], 'an id in both lists is named');
	pg_assert_same(array(), $clash['choices'], 'and nothing is chosen');
}

function test_api_design_bundle_publish_refusal_order()
{
	$page = function ($id, $state, $text = '') {
		return array('item_id' => $id, 'name' => 'p' . $id, 'replacement' => true, 'state' => $state, 'text' => $text);
	};

	$plan = array('ok' => false, 'pages' => array($page(1, 'changed'), $page(2, 'refused', 'No.'), $page(3, 'locked')));
	$refusal = api_design_bundle_publish_refusal($plan);

	pg_assert_same('page_open_in_editor', $refusal['code'], 'a page open in the editor comes first');
	pg_assert_same(array(3), array_column($refusal['pages'], 'item_id'), 'only the open page is listed');

	$plan['pages'] = array($page(1, 'changed'), $page(2, 'refused', 'No.'));
	pg_assert_same('bundle_page_refused', api_design_bundle_publish_refusal($plan)['code'], 'then a page the save refuses');

	$plan['pages'] = array($page(1, 'changed'), $page(2, 'ready'));
	pg_assert_same('bundle_changed_since', api_design_bundle_publish_refusal($plan)['code'], 'then a page changed since without a choice');

	pg_assert_same('bundle_empty', api_design_bundle_publish_refusal(array('ok' => false, 'pages' => array()))['code'], 'nothing to publish');

	pg_assert_same(null, api_design_bundle_publish_refusal(array('ok' => true, 'pages' => array($page(1, 'ready'), $page(2, 'left_out')))), 'a plan that may be written');
}

function test_api_design_bundle_page_errors_shape()
{
	$errors = api_design_bundle_page_errors(array(
		array('item_id' => 4, 'name' => 'home', 'state' => 'refused', 'text' => 'A block is broken.'),
		array('item_id' => 5, 'name' => 'about', 'state' => 'locked', 'text' => ''),
	));

	pg_assert_same(array('field' => '4', 'code' => 'refused', 'message' => 'home: A block is broken.'), $errors[0], 'the item id, its state and the reason');
	pg_assert_same('about', $errors[1]['message'], 'a page without a reason by its name');
	pg_assert_same(array('field', 'code', 'message'), array_keys($errors[1]), 'the shape of the Error schema\'s errors');
}

function test_api_design_bundle_kept_errors_carry_the_reason()
{
	if (!function_exists('pg_page_bundle_take_back_reason_text')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bundles.php');
	}

	$errors = api_design_bundle_kept_errors(array(array('item_id' => 8, 'name' => 'home', 'reason' => 'changed')));

	pg_assert_same('8', $errors[0]['field'], 'the item id');
	pg_assert_same('changed', $errors[0]['code'], 'the reason as code');
	pg_assert_contains('home: ', $errors[0]['message'], 'the page by its name');
}

function test_api_design_bundle_routes()
{
	$reads = array('design.bundles.list', 'design.bundles.get');
	$writes = array('design.bundles.pages.add', 'design.bundles.pages.delete', 'design.bundles.pages.delete_post', 'design.bundles.publish', 'design.bundles.take_back');

	foreach ($reads as $id) {
		$route = api_design_bundles_test_route($id);
		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same('design:read', $route['scope'], $id . ' reads under design:read');
	}

	foreach ($writes as $id) {
		$route = api_design_bundles_test_route($id);
		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same('design:write', $route['scope'], $id . ' writes under design:write');
		pg_assert_true(!empty($route['dry_run']), $id . ' can be rehearsed');
		pg_assert_true(function_exists($route['handler']), $id . ' has its handler');
	}

	pg_assert_same('POST', api_design_bundles_test_route('design.bundles.pages.delete_post')['method'], 'the delete has a POST twin');
	pg_assert_same(201, api_design_bundles_test_route('design.bundles.pages.add')['status'], 'an added page answers 201');

	$publish = api_design_bundles_test_route('design.bundles.publish')['description'];

	foreach (array('page_open_in_editor', 'bundle_page_refused', 'bundle_changed_since', 'bundle_empty', 'bundle_state', 'bundle_busy', 'bundle_publish_failed') as $code) {
		pg_assert_contains($code, $publish, 'the publish names ' . $code);
	}

	$take_back = api_design_bundles_test_route('design.bundles.take_back')['description'];

	foreach (array('page_open_in_editor', 'bundle_pages_kept', 'bundle_state', 'bundle_busy', 'bundle_take_back_failed') as $code) {
		pg_assert_contains($code, $take_back, 'the take-back names ' . $code);
	}

	$objects = api_openapi_objects();
	pg_assert_true(isset($objects['DesignBundle']) && isset($objects['DesignBundlePage']), 'both objects are registered');
}

function test_api_design_bundle_checks_come_before_the_dry_run_stop()
{
	$publish = api_design_bundles_test_body('api_design_bundles_publish');
	$stop = strpos($publish, 'api_dry_run_stop(');

	pg_assert_true(strpos($publish, 'pg_page_bundle_publish_check(') < $stop, 'the publish checks every page first');
	pg_assert_true(strpos($publish, 'api_design_bundle_publish_refusal(') < $stop, 'and refuses before the stop');
	pg_assert_true($stop < strpos($publish, 'pg_page_bundle_publish('), 'the write comes after the stop');

	$take_back = api_design_bundles_test_body('api_design_bundles_take_back');
	$stop = strpos($take_back, 'api_dry_run_stop(');

	pg_assert_true(strpos($take_back, 'pg_page_bundle_take_back_allowed(') < $stop, 'the kept pages are checked first');
	pg_assert_true(strpos($take_back, 'pg_collab_may_edit_page(') < $stop, 'and the editor locks');
	pg_assert_true($stop < strpos($take_back, 'pg_page_bundle_take_back('), 'the write comes after the stop');

	$add = api_design_bundles_test_body('api_design_bundles_pages_add');
	$stop = strpos($add, 'api_dry_run_stop(');

	pg_assert_true(strpos($add, 'pg_page_bundle_add_copy_check(') < $stop && strpos($add, 'pg_page_bundle_add_draft_check(') < $stop, 'the add checks come first');
	pg_assert_true($stop < strpos($add, 'pg_page_bundle_add_copy('), 'the write comes after the stop');
}
