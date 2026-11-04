<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ads and ad regions over the external API (/design/ads,
 * /design/ad-regions) and the write path the four screens share with it
 * (includes/fn/ads.php): the route rows and their scopes, partial updates
 * laid over the stored row, the screens' checks before the write, the
 * region that keeps its ads.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design_ads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_design_ads_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_design_ads_test_body($relative, $name)
{
	$source = api_design_ads_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function api_design_ads_test_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function test_api_design_ads_routes()
{
	$routes = api_design_ads_test_routes();

	foreach (array('ads' => 'ads', 'ad-regions' => 'ad_regions') as $path => $key) {
		$expected = array(
			'list'        => array('GET', '/design/' . $path, 'design:read'),
			'get'         => array('GET', '/design/' . $path . '/{id}', 'design:read'),
			'create'      => array('POST', '/design/' . $path, 'design:write'),
			'update'      => array('POST', '/design/' . $path . '/{id}', 'design:write'),
			'delete'      => array('DELETE', '/design/' . $path . '/{id}', 'design:write'),
			'delete_post' => array('POST', '/design/' . $path . '/{id}/delete', 'design:write'),
		);

		foreach ($expected as $action => $row) {
			$id = 'design.' . $key . '.' . $action;

			pg_assert_true(isset($routes[$id]), $id . ' is declared');

			if (!isset($routes[$id])) {
				continue;
			}

			pg_assert_same($row[0], $routes[$id]['method'], $id . ' method');
			pg_assert_same($row[1], $routes[$id]['path'], $id . ' path');
			pg_assert_same($row[2], $routes[$id]['scope'], $id . ' scope');
			pg_assert_true(function_exists($routes[$id]['handler']), $routes[$id]['handler'] . '() exists');

			if ($row[2] === 'design:write') {
				pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' can be rehearsed');
			}
		}

		pg_assert_same(array('PATCH'), $routes['design.' . $key . '.update']['also_accepts'], 'design.' . $key . '.update also takes PATCH');
		pg_assert_same(201, $routes['design.' . $key . '.create']['status'], 'design.' . $key . '.create answers 201');
	}
}

// varchar(100) names, varchar(255) label: a longer value would be cut in
// silence by the non-strict session.
function test_api_design_ads_text_limits_fit_the_columns()
{
	$routes = api_design_ads_test_routes();
	$limits = array('name' => 100, 'label' => 255);

	foreach (array('design.ads.create', 'design.ads.update', 'design.ad_regions.create', 'design.ad_regions.update') as $id) {
		foreach ($routes[$id]['params'] as $param) {
			if (isset($limits[$param['name']])) {
				pg_assert_same($limits[$param['name']], $param['max_length'], $id . ' ' . $param['name'] . ' fits its column');
			}
		}
	}
}

function test_api_design_ad_values_lay_the_sent_fields_over_the_row()
{
	$row = array('id' => 4, 'name' => 'Spring', 'content' => '<a href="{path}sale">Sale</a>', 'caption' => '', 'ad_region_id' => 7, 'label' => 'Tab', 'sort_order' => 3);

	pg_assert_same(
		array('name' => 'Spring', 'content' => '<a href="{path}sale">Sale</a>', 'caption' => '', 'ad_region_id' => '7', 'label' => 'Tab', 'sort_order' => '3'),
		api_design_ad_values($row, array(), '/site/'),
		'nothing sent keeps the stored ad');

	pg_assert_same(
		array('name' => 'Summer', 'content' => '<a href="{path}sale">Sale</a>', 'caption' => '<img src="{path}b.png">', 'ad_region_id' => '9', 'label' => 'Tab', 'sort_order' => '0'),
		api_design_ad_values($row, array('name' => ' Summer ', 'caption' => '<img src="/site/b.png">', 'ad_region_id' => 9, 'sort_order' => 0), '/site/'),
		'the sent fields change, the caption keeps the site path as {path}');

	pg_assert_same(
		array('name' => 'New', 'content' => '', 'caption' => '', 'ad_region_id' => '2', 'label' => '', 'sort_order' => '0'),
		api_design_ad_values(null, array('name' => 'New', 'ad_region_id' => 2), '/site/'),
		'a create starts from an empty ad in random order');
}

function test_api_design_ad_present_expands_the_site_path()
{
	$out = api_design_ad_present(array('id' => '4', 'name' => 'Spring', 'ad_region_id' => '7', 'ad_region_name' => 'side', 'content' => '<a href="/sale">x</a>', 'caption' => '', 'label' => '', 'sort_order' => '0', 'created_timestamp' => 0, 'last_modified_timestamp' => 0));

	pg_assert_same('<a href="/sale">x</a>', $out['content'], 'content without the placeholder goes out as stored');
	pg_assert_contains("str_replace('{path}', URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH", api_design_ads_test_body('includes/api/resources/design_ads.php', 'api_design_ad_html_out'), 'the {path} placeholder goes out as an absolute address');
	pg_assert_same(array_keys(api_design_ad_schema()), array_keys($out), 'the presenter builds the declared fields');
}

function test_api_design_ad_region_fields_defaults_and_partial_update()
{
	$created = api_design_ad_region_fields(null, array('name' => 'side'));

	pg_assert_same(array('static', 'slide', '0', '0', '0', '1'), pg_ad_region_behavior_values($created), 'a create takes the screen defaults: static, slide, continuous play');
	pg_assert_same(array(), pg_ad_region_behavior_errors($created), 'the defaults pass the screen checks');

	$row = array('id' => 3, 'name' => 'side', 'display_type' => 'dynamic', 'transition_type' => 'fade', 'transition_duration' => 0, 'slideshow' => 1, 'slideshow_interval' => 0, 'slideshow_continuous' => 0);
	$kept = api_design_ad_region_fields($row, array());

	pg_assert_same(array(), pg_ad_region_behavior_errors($kept), 'a stored 0 interval with autoplay is the screen blank, not a refusal');
	pg_assert_same(array('dynamic', 'fade', '0', '1', '0', '0'), pg_ad_region_behavior_values($kept), 'nothing sent keeps the stored behaviour');

	$changed = api_design_ad_region_fields($row, array('interval' => 5, 'autoplay' => false, 'continuous' => true));

	pg_assert_same(array('dynamic', 'fade', '0', '0', '5', '1'), pg_ad_region_behavior_values($changed), 'the sent interval, autoplay and continuous play change');

	$wrong = api_design_ad_region_fields($row, array('transition_type' => 'spin'));

	pg_assert_true(isset(pg_ad_region_behavior_errors($wrong)['transition_type']), 'the screen check refuses a wrong transition');
}

function test_api_design_ads_check_before_dry_run_before_write()
{
	$file = 'includes/api/resources/design_ads.php';

	foreach (array('api_design_ads_create', 'api_design_ads_update', 'api_design_ad_regions_create', 'api_design_ad_regions_update') as $handler) {
		$body = api_design_ads_test_body($file, $handler);

		pg_assert_true(strpos($body, '_prepare(') !== false && strpos($body, '_prepare(') < strpos($body, 'api_dry_run_stop('), $handler . ' checks before the dry run stop');
		pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, '_commit('), $handler . ' stops a dry run before the write');
	}

	$body = api_design_ads_test_body($file, 'api_design_ad_regions_delete');

	pg_assert_contains("api_fail(409, 'ad_region_has_ads'", $body, 'a region with ads is kept');
	pg_assert_true(strpos($body, 'pg_ad_region_has_ads(') < strpos($body, 'api_dry_run_stop(') && strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_ad_region_delete('), 'region delete: ads check, dry run stop, delete');

	$body = api_design_ads_test_body($file, 'api_design_ads_delete');

	pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_ad_delete('), 'ad delete stops a dry run before the write');

	pg_assert_contains("api_fail(409, 'name_taken'", api_design_ads_test_body($file, 'api_design_ad_prepare'), 'an ad name in use is 409');
	pg_assert_contains("api_fail(409, 'name_taken'", api_design_ads_test_body($file, 'api_design_ad_region_prepare'), 'a region name in use is 409');
	pg_assert_contains('pg_ad_region_name_error(', api_design_ads_test_body($file, 'api_design_ad_region_prepare'), 'a region name follows the screen rule');
}

function test_api_design_ads_screens_share_the_write_path()
{
	foreach (array('add_ad.php', 'edit_ad.php', 'add_ad_region.php', 'edit_ad_region.php') as $file) {
		$source = api_design_ads_test_source($file);

		pg_assert_false(preg_match('/\b(INSERT|UPDATE|DELETE)\b[^;]*\b(ads|ad_regions|users_ad_regions_xref)\b/', $source) === 1, $file . ' keeps no SQL write of its own');
	}

	pg_assert_contains('pg_ad_region_save(0, ', api_design_ads_test_source('add_ad_region.php'), 'create region writes through pg_ad_region_save()');
	pg_assert_contains('pg_ad_region_save($id, $new_name, ', api_design_ads_test_source('edit_ad_region.php'), 'edit region writes the region it checked at the top, a manager without a name');
	pg_assert_contains('pg_ad_region_delete($id)', api_design_ads_test_source('edit_ad_region.php'), 'edit region deletes the region it checked at the top');
	pg_assert_contains('pg_ad_region_has_ads($id)', api_design_ads_test_source('edit_ad_region.php'), 'edit region asks the shared ads check');
	pg_assert_contains('users_ad_regions_xref', api_design_ads_test_body('includes/fn/ads.php', 'pg_ad_region_delete'), 'a region delete takes the users\' access with it');
}
