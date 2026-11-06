<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for auto dialogs and common regions over the external API
 * (/design/auto-dialogs, /design/common-regions) and the write path the
 * four screens share with it (includes/fn/auto_dialogs.php,
 * common_regions.php): the route rows and their scopes, partial updates laid
 * over the stored row, the checks before the dry run stop and the write, a
 * region drawn by its shared component that is kept (#5516).
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialogs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/common_regions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design_dialogs_regions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_design_dialogs_regions_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_design_dialogs_regions_test_body($relative, $name)
{
	$source = api_design_dialogs_regions_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function api_design_dialogs_regions_test_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function test_api_design_dialogs_regions_routes()
{
	$routes = api_design_dialogs_regions_test_routes();

	foreach (array('auto-dialogs' => 'auto_dialogs', 'common-regions' => 'common_regions') as $path => $key) {
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

// The varchar columns and the screen's number limits: a longer text would be
// cut in silence by the non-strict session, a bigger number clamped.
function test_api_design_dialogs_regions_limits_fit_the_columns()
{
	$routes = api_design_dialogs_regions_test_routes();
	$texts = array('name' => 100, 'url' => 255, 'page' => 100);

	foreach (array('design.auto_dialogs.create', 'design.auto_dialogs.update', 'design.common_regions.create', 'design.common_regions.update') as $id) {
		foreach ($routes[$id]['params'] as $param) {
			if (isset($texts[$param['name']])) {
				pg_assert_same($texts[$param['name']], $param['max_length'], $id . ' ' . $param['name'] . ' fits its column');
			}
		}
	}

	$limits = pg_auto_dialog_form_number_limits();

	foreach (array('design.auto_dialogs.create', 'design.auto_dialogs.update') as $id) {
		foreach ($routes[$id]['params'] as $param) {
			if (isset($limits[$param['name']])) {
				pg_assert_same(0, $param['min'], $id . ' ' . $param['name'] . ' from 0');
				pg_assert_same($limits[$param['name']], $param['max'], $id . ' ' . $param['name'] . ' within the screen limit');
			}
		}
	}
}

function test_api_design_auto_dialog_values_lay_the_sent_fields_over_the_row()
{
	$row = array('id' => 4, 'name' => 'Welcome', 'enabled' => 1, 'url' => '/welcome', 'width' => 400, 'height' => 0, 'delay' => 5, 'frequency' => 24, 'page' => 'home');

	pg_assert_same(
		array('name' => 'Welcome', 'enabled' => '1', 'url' => '/welcome', 'width' => '400', 'height' => '0', 'delay' => '5', 'frequency' => '24', 'page' => 'home'),
		api_design_auto_dialog_values($row, array()),
		'nothing sent keeps the stored dialog');

	pg_assert_same(
		array('name' => 'Hello', 'enabled' => '0', 'url' => '/welcome', 'width' => '0', 'height' => '0', 'delay' => '5', 'frequency' => '24', 'page' => 'shop'),
		api_design_auto_dialog_values($row, array('name' => ' Hello ', 'enabled' => false, 'width' => null, 'page' => '/shop')),
		'the sent fields change; null is the blank 0; the page loses its leading slash');

	$created = api_design_auto_dialog_values(null, array('name' => 'New', 'url' => '/x'));

	pg_assert_same('0', $created['enabled'], 'a create starts disabled, as on the screen');
	pg_assert_same(8, count(array_values($created)), 'the eight values pg_auto_dialog_save() writes');
	pg_assert_same(array('name', 'enabled', 'url', 'width', 'height', 'delay', 'frequency', 'page'), array_keys($created), 'in the screen\'s INSERT/UPDATE order');
}

function test_api_design_auto_dialog_present_builds_the_declared_fields()
{
	$out = api_design_auto_dialog_present(array('id' => '4', 'name' => 'Welcome', 'enabled' => '1', 'url' => '/welcome', 'width' => '400', 'height' => '0', 'delay' => '5', 'frequency' => '0', 'page' => '', 'created_timestamp' => 0, 'last_modified_timestamp' => 0));

	pg_assert_same(true, $out['enabled'], 'enabled is a boolean');
	pg_assert_same(400, $out['width'], 'width is a number');
	pg_assert_same(array_keys(api_design_auto_dialog_schema()), array_keys($out), 'the presenter builds the declared fields');
}

function test_api_design_common_region_present()
{
	$row = array('id' => '7', 'name' => 'footer', 'content' => '<p>x</p>', 'cregion_timestamp' => 0);
	$plain = api_design_common_region_present($row, 0);

	pg_assert_same('<cregion>footer</cregion>', $plain['tag'], 'the tag a page style places');
	pg_assert_same(null, $plain['shared_component_id'], 'no component: null');
	pg_assert_same(12, api_design_common_region_present($row, 12)['shared_component_id'], 'the component drawing the region');
	pg_assert_same(array_keys(api_design_common_region_schema()), array_keys($plain), 'the presenter builds the declared fields');
	pg_assert_contains("cregion_designer_type = 'no'", api_design_common_region_select(), 'designer regions are not reached');
}

function test_api_design_dialogs_regions_check_before_dry_run_before_write()
{
	$file = 'includes/api/resources/design_dialogs_regions.php';

	foreach (array('api_design_auto_dialogs_create', 'api_design_auto_dialogs_update', 'api_design_common_regions_create', 'api_design_common_regions_update') as $handler) {
		$body = api_design_dialogs_regions_test_body($file, $handler);

		pg_assert_true(strpos($body, '_prepare(') !== false && strpos($body, '_prepare(') < strpos($body, 'api_dry_run_stop('), $handler . ' checks before the dry run stop');
		pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, '_commit('), $handler . ' stops a dry run before the write');
	}

	$body = api_design_dialogs_regions_test_body($file, 'api_design_auto_dialogs_delete');

	pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_auto_dialog_delete('), 'auto dialog delete stops a dry run before the write');

	$body = api_design_dialogs_regions_test_body($file, 'api_design_common_regions_delete');

	pg_assert_contains("api_fail(409, 'region_is_shared_component'", $body, 'a region drawn by its component is kept');
	pg_assert_true(strpos($body, 'api_design_common_region_component(') < strpos($body, 'api_dry_run_stop(') && strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_cregion_delete('), 'region delete: component check, dry run stop, delete');

	$body = api_design_dialogs_regions_test_body($file, 'api_design_common_region_prepare');

	pg_assert_contains("api_fail(409, 'name_taken'", $body, 'a region name in use is 409');
	pg_assert_contains('pg_region_form_name_refusal(', $body, 'a region name follows the screen rule');
	pg_assert_contains('pg_cregion_name_taken(', $body, 'the name is checked against common and designer regions');
	pg_assert_contains("api_fail(409, 'region_is_shared_component'", $body, 'no content for a region drawn by its component');

	$body = api_design_dialogs_regions_test_body($file, 'api_design_auto_dialog_prepare');

	pg_assert_contains("api_fail(409, 'name_taken'", $body, 'an auto dialog name in use is 409');
	pg_assert_contains('pg_auto_dialog_form_whole_number(', $body, 'the numbers follow the screen rule');
}

function test_api_design_dialogs_regions_screens_share_the_write_path()
{
	foreach (array('add_auto_dialog.php', 'edit_auto_dialog.php', 'includes/forms/auto_dialog_form.php') as $file) {
		pg_assert_false(preg_match('/\b(INSERT|UPDATE|DELETE)\b[^;]*\bauto_dialogs\b/', api_design_dialogs_regions_test_source($file)) === 1, $file . ' keeps no SQL write of its own');
	}

	foreach (array('add_common_region.php', 'edit_common_region.php') as $file) {
		pg_assert_false(preg_match('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(cregion|users_common_regions_xref)\b/', api_design_dialogs_regions_test_source($file)) === 1, $file . ' keeps no SQL write of its own');
	}

	pg_assert_contains('pg_auto_dialog_delete($id)', api_design_dialogs_regions_test_source('edit_auto_dialog.php'), 'edit auto dialog deletes the dialog it checked at the top');
	pg_assert_contains('pg_auto_dialog_name_in_use(', api_design_dialogs_regions_test_body('includes/forms/auto_dialog_form.php', 'pg_auto_dialog_form_validate'), 'the screen asks the shared name check');
	pg_assert_contains('pg_cregion_delete($id)', api_design_dialogs_regions_test_source('edit_common_region.php'), 'edit common region deletes the region it checked at the top');
	pg_assert_contains('users_common_regions_xref', api_design_dialogs_regions_test_body('includes/fn/common_regions.php', 'pg_cregion_delete'), 'a region delete takes the users\' access with it');
}
