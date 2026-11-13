<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Auto dialogs and common regions over the external API
// (/design/auto-dialogs, /design/common-regions): reads under design:read,
// writes under design:write, with the checks of the screens' forms
// (includes/forms/auto_dialog_form.php, common_region_form.php) and the
// write path the screens call (includes/fn/auto_dialogs.php,
// common_regions.php). No events. The paging, link and log helpers are the
// ads' (design_ads.php).

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design_ads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/* ---------------------------------------------------------------------------
   Auto dialogs
   --------------------------------------------------------------------------- */

function api_design_auto_dialog_present($row) {

	return array(
		'id'         => (int)$row['id'],
		'name'       => (string)$row['name'],
		'enabled'    => ((int)$row['enabled'] === 1),
		'url'        => (string)$row['url'],
		'width'      => (int)$row['width'],
		'height'     => (int)$row['height'],
		'delay'      => (int)$row['delay'],
		'frequency'  => (int)$row['frequency'],
		'page'       => (string)$row['page'],
		'created_at' => api_time($row['created_timestamp']),
		'updated_at' => api_time($row['last_modified_timestamp'])
	);

}

function api_design_auto_dialog_schema() {

	return array(
		'id'         => 'integer',
		'name'       => 'string',
		'enabled'    => 'boolean',
		'url'        => 'string',
		'width'      => 'integer',
		'height'     => 'integer',
		'delay'      => 'integer',
		'frequency'  => 'integer',
		'page'       => 'string',
		'created_at' => 'string?',
		'updated_at' => 'string?'
	);

}

function api_design_auto_dialog_load_or_fail($id) {

	$row = api_row("SELECT * FROM auto_dialogs WHERE id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Auto Dialog'));

	}

	return $row;

}

// GET /design/auto-dialogs: oldest first, cursor paged by id.
function api_design_auto_dialogs_list($params) {

	$where = array("id > '" . api_design_ad_cursor_or_fail($params) . "'");

	if (isset($params['enabled'])) {

		$where[] = "enabled = '" . ($params['enabled'] ? 1 : 0) . "'";

	}

	$limit = (int)$params['limit'];

	list($rows, $next) = api_design_ad_page(api_rows("SELECT * FROM auto_dialogs
		WHERE " . implode(' AND ', $where) . "
		ORDER BY id ASC
		LIMIT " . ($limit + 1)), $limit);

	api_ok_list(array_map('api_design_auto_dialog_present', $rows), $limit, $next);

}

// GET /design/auto-dialogs/{id}
function api_design_auto_dialogs_get($params) {

	api_ok(api_design_auto_dialog_present(api_design_auto_dialog_load_or_fail($params['id'])));

}

// An auto dialog after a create or an update, keyed like the screen's
// fields: the stored row (or a new, disabled dialog) with the fields sent
// laid over it. A number sent as null is the screen's blank (0: auto size,
// at once, one time); the page loses a leading slash as on the screen. Pure.
function api_design_auto_dialog_values($row, $params) {

	$values = array(
		'name'      => ($row === null) ? '' : (string)$row['name'],
		'enabled'   => (($row !== null) && ((int)$row['enabled'] === 1)) ? '1' : '0',
		'url'       => ($row === null) ? '' : (string)$row['url'],
		'width'     => ($row === null) ? '0' : (string)(int)$row['width'],
		'height'    => ($row === null) ? '0' : (string)(int)$row['height'],
		'delay'     => ($row === null) ? '0' : (string)(int)$row['delay'],
		'frequency' => ($row === null) ? '0' : (string)(int)$row['frequency'],
		'page'      => ($row === null) ? '' : (string)$row['page']
	);

	foreach (array('name', 'url') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = trim((string)$params[$field]);

		}

	}

	if (array_key_exists('enabled', $params)) {

		$values['enabled'] = $params['enabled'] ? '1' : '0';

	}

	foreach (array_keys(pg_auto_dialog_form_number_limits()) as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = (string)(int)$params[$field];

		}

	}

	if (array_key_exists('page', $params)) {

		$values['page'] = pg_auto_dialog_form_page(trim((string)$params['page']));

	}

	return $values;

}

// The screen's checks (pg_auto_dialog_form_validate()): a name and a URL,
// within their columns (422), the URL a web address or a path on this site
// (422), the numbers within the screen's limits (422), a name no other auto
// dialog has (409 name_taken).
function api_design_auto_dialog_prepare($params, $row) {

	$values = api_design_auto_dialog_values($row, $params);

	$id = ($row === null) ? 0 : (int)$row['id'];

	foreach (array('name' => 100, 'url' => 255) as $field => $max) {

		if ($values[$field] === '') {

			api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => lang(($field === 'name') ? 'Name' : 'URL'))), $field);

		}

		if (mb_strlen($values[$field], 'UTF-8') > $max) {

			api_fail_validation(lang(array('string' => '{var:1} can be at most {var:2} characters long.', 'vars' => array(lang(($field === 'name') ? 'Name' : 'URL'), $max))), $field);

		}

	}

	// The URL opens in an iframe on the site: the screen's address rule (422)
	// on a create and whenever a URL is sent. An update that leaves the URL
	// out keeps a stored one saved before the rule, so that the dialog can
	// still be disabled or renamed; the site never opens such an address
	// (pg_auto_dialogs_for_page()).
	$problem = (($row === null) || array_key_exists('url', $params)) ? pg_auto_dialog_url_problem($values['url']) : '';

	if ($problem !== '') {

		api_fail_validation($problem, 'url');

	}

	if (mb_strlen($values['page'], 'UTF-8') > 100) {

		api_fail_validation(lang(array('string' => '{var:1} can be at most {var:2} characters long.', 'vars' => array(lang('Only on Page'), 100))), 'page');

	}

	foreach (pg_auto_dialog_form_number_limits() as $field => $max) {

		if (pg_auto_dialog_form_whole_number($values[$field], $max) === null) {

			api_fail_validation(lang(array('string' => '{var:1} must be a whole number from {var:2} to {var:3}.', 'vars' => array(lang(ucfirst($field)), 0, $max))), $field);

		}

	}

	$taken = pg_auto_dialog_name_in_use($values['name'], ($row === null) ? null : $id);

	if ($taken === null) {

		api_fail_server();

	}

	if ($taken) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	return array('id' => $id, 'values' => $values);

}

function api_design_auto_dialog_commit($prepared) {

	$app = api_current_app();

	$saved = pg_auto_dialog_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_design_ad_log(($prepared['id'] <= 0)
		? 'Auto dialog ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Auto dialog ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /design/auto-dialogs
function api_design_auto_dialogs_create($params) {

	$prepared = api_design_auto_dialog_prepare($params, null);

	api_dry_run_stop('created', 'auto_dialog', array('name' => $prepared['values']['name'], 'enabled' => ($prepared['values']['enabled'] === '1')));

	api_ok(api_design_auto_dialog_present(api_design_auto_dialog_load_or_fail(api_design_auto_dialog_commit($prepared))), 201);

}

// POST /design/auto-dialogs/{id}
function api_design_auto_dialogs_update($params) {

	$prepared = api_design_auto_dialog_prepare($params, api_design_auto_dialog_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'auto_dialog', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'enabled' => ($prepared['values']['enabled'] === '1')));

	api_ok(api_design_auto_dialog_present(api_design_auto_dialog_load_or_fail(api_design_auto_dialog_commit($prepared))));

}

// DELETE /design/auto-dialogs/{id} and its POST twin
function api_design_auto_dialogs_delete($params) {

	$row = api_design_auto_dialog_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'auto_dialog', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_auto_dialog_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_design_ad_log('Auto dialog ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

/* ---------------------------------------------------------------------------
   Common regions
   --------------------------------------------------------------------------- */

// Only common regions (cregion_designer_type 'no'): a designer region shares
// the table but is a designer's code, and is 404 here.
function api_design_common_region_select() {

	return "SELECT cregion_id AS id, cregion_name AS name, cregion_content AS content, cregion_timestamp
		FROM cregion
		WHERE cregion_designer_type = 'no'";

}

// $component_id: the shared component that draws the region, 0 for none.
function api_design_common_region_present($row, $component_id = 0) {

	return array(
		'id'                  => (int)$row['id'],
		'name'                => (string)$row['name'],
		'tag'                 => '<cregion>' . (string)$row['name'] . '</cregion>',
		'content'             => api_design_ad_html_out($row['content']),
		'shared_component_id' => ((int)$component_id > 0) ? (int)$component_id : null,
		'updated_at'          => api_time($row['cregion_timestamp'])
	);

}

function api_design_common_region_schema() {

	return array(
		'id'                  => 'integer',
		'name'                => 'string',
		'tag'                 => 'string',
		'content'             => 'string',
		'shared_component_id' => 'integer?',
		'updated_at'          => 'string?'
	);

}

function api_design_common_region_load_or_fail($id) {

	$row = api_row(api_design_common_region_select() . " AND cregion_id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Common Region'));

	}

	return $row;

}

// The shared component drawing the region, 0 for none.
function api_design_common_region_component($id) {

	$components = pg_common_region_components(array((int)$id));

	return (int)$components[(int)$id];

}

function api_design_common_region_present_one($row) {

	return api_design_common_region_present($row, api_design_common_region_component($row['id']));

}

// GET /design/common-regions: oldest first, cursor paged by id.
function api_design_common_regions_list($params) {

	$limit = (int)$params['limit'];

	list($rows, $next) = api_design_ad_page(api_rows(api_design_common_region_select() . "
		AND cregion_id > '" . api_design_ad_cursor_or_fail($params) . "'
		ORDER BY cregion_id ASC
		LIMIT " . ($limit + 1)), $limit);

	$components = $rows ? pg_common_region_components(array_map(function ($row) {

		return (int)$row['id'];

	}, $rows)) : array();

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_design_common_region_present($row, isset($components[(int)$row['id']]) ? $components[(int)$row['id']] : 0);

	}

	api_ok_list($out, $limit, $next);

}

// GET /design/common-regions/{id}
function api_design_common_regions_get($params) {

	api_ok(api_design_common_region_present_one(api_design_common_region_load_or_fail($params['id'])));

}

// The first column of the first row of a bound query, null for no row; a
// failed query is a 500 instead of the screen's error page.
function api_design_common_region_lookup($sql, $values) {

	$result = db_q($sql, $values);

	if (!$result) {

		api_fail_server();

	}

	$row = mysqli_fetch_row($result);

	return $row ? $row[0] : null;

}

// The screen's checks: a name (pg_region_form_name_refusal(): required, at
// most 100 characters, 422) that no other common or designer region has
// (pg_cregion_name_taken(), 409 name_taken). A region drawn by its shared
// component takes a rename but no content (409 region_is_shared_component):
// its content is edited in the designer.
function api_design_common_region_prepare($params, $row) {

	$id = ($row === null) ? 0 : (int)$row['id'];

	$name = array_key_exists('name', $params) ? trim((string)$params['name']) : (($row === null) ? '' : (string)$row['name']);

	$error = pg_region_form_name_refusal($name, false);

	if ($error !== '') {

		api_fail_validation($error, 'name');

	}

	if (pg_cregion_name_taken($name, ($row === null) ? null : $id, 'api_design_common_region_lookup')) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	$content = null;

	if (array_key_exists('content', $params)) {

		if (($row !== null) && (api_design_common_region_component($id) > 0)) {

			api_fail(409, 'region_is_shared_component', lang('This common region is a shared component: its content is edited in the Page Design Studio.'), 'content');

		}

		$content = (string)prepare_rich_text_editor_content_for_input((string)$params['content']);

	} elseif ($row === null) {

		$content = '';

	}

	return array('id' => $id, 'name' => $name, 'content' => $content);

}

function api_design_common_region_commit($prepared) {

	$app = api_current_app();

	// The type is kept on an update: only common regions are reached here.
	$saved = pg_cregion_save($prepared['id'], $prepared['name'], $prepared['content'], ($prepared['id'] <= 0) ? 'no' : null, (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_design_ad_log(($prepared['id'] <= 0)
		? 'Common region ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Common region ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['name']);

	return $saved;

}

// POST /design/common-regions
function api_design_common_regions_create($params) {

	$prepared = api_design_common_region_prepare($params, null);

	api_dry_run_stop('created', 'common_region', array('name' => $prepared['name']));

	api_ok(api_design_common_region_present_one(api_design_common_region_load_or_fail(api_design_common_region_commit($prepared))), 201);

}

// POST /design/common-regions/{id}
function api_design_common_regions_update($params) {

	$prepared = api_design_common_region_prepare($params, api_design_common_region_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'common_region', array('id' => $prepared['id'], 'name' => $prepared['name']));

	api_ok(api_design_common_region_present_one(api_design_common_region_load_or_fail(api_design_common_region_commit($prepared))));

}

// DELETE /design/common-regions/{id} and its POST twin: as on the screen
// (#5516), a region drawn by its shared component is kept (409
// region_is_shared_component): the row is the tag's link to the component
// and the component's undo.
function api_design_common_regions_delete($params) {

	$row = api_design_common_region_load_or_fail($params['id']);

	$component_id = api_design_common_region_component((int)$row['id']);

	if ($component_id > 0) {

		api_fail(409, 'region_is_shared_component', lang(array('string' => 'This common region is the shared component "{var:1}". Delete the component first; its content comes back here, then the region can be deleted.', 'vars' => (string)db_value("SELECT name FROM shared_components WHERE id = '" . $component_id . "'"))));

	}

	api_dry_run_stop('deleted', 'common_region', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_cregion_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_design_ad_log('Common region ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}
