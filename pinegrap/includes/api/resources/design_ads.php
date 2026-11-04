<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Ads and ad regions over the external API (/design/ads, /design/ad-regions):
// reads under design:read, writes under design:write, with the checks of the
// screens' forms (includes/forms/ad_form.php, ad_region_form.php) and the
// write path the screens call (includes/fn/ads.php). No events.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/tag_name_rule.php');

// A log line in the name of the application's owner.
function api_design_ad_log($text, $name) {

	$app = api_current_app();

	log_activity(lang(array('string' => $text, 'vars' => array($name, $app['name'], $app['api_key']))), $app['owner']['username']);

}

// Rich text keeps the site's links as {path}; the reader is not on the site,
// so they go out as absolute addresses. Pure.
function api_design_ad_html_out($html) {

	$html = (string)$html;

	if (strpos($html, '{path}') === false) {

		return $html;

	}

	return str_replace('{path}', URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH, $html);

}

// The cursor of a list paged by id, or a 400.
function api_design_ad_cursor_or_fail($params) {

	if (!isset($params['cursor']) || ($params['cursor'] === '')) {

		return 0;

	}

	$cursor = api_cursor_decode($params['cursor']);

	if ($cursor === null) {

		api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

	}

	return (int)$cursor['i'];

}

// Rows of a page read with one row more than the limit, and the cursor of
// the next page (null on the last one).
function api_design_ad_page($rows, $limit) {

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	return array($rows, $next);

}

/* ---------------------------------------------------------------------------
   Ads
   --------------------------------------------------------------------------- */

function api_design_ad_present($row) {

	return array(
		'id'             => (int)$row['id'],
		'name'           => (string)$row['name'],
		'ad_region_id'   => (int)$row['ad_region_id'],
		'ad_region_name' => isset($row['ad_region_name']) ? (string)$row['ad_region_name'] : '',
		'content'        => api_design_ad_html_out($row['content']),
		'caption'        => api_design_ad_html_out($row['caption']),
		'label'          => (string)$row['label'],
		'sort_order'     => (int)$row['sort_order'],
		'created_at'     => api_time($row['created_timestamp']),
		'updated_at'     => api_time($row['last_modified_timestamp'])
	);

}

function api_design_ad_schema() {

	return array(
		'id'             => 'integer',
		'name'           => 'string',
		'ad_region_id'   => 'integer',
		'ad_region_name' => 'string',
		'content'        => 'string',
		'caption'        => 'string',
		'label'          => 'string',
		'sort_order'     => 'integer',
		'created_at'     => 'string?',
		'updated_at'     => 'string?'
	);

}

function api_design_ad_select() {

	return "SELECT ads.*, ad_regions.name AS ad_region_name
		FROM ads
		LEFT JOIN ad_regions ON ad_regions.id = ads.ad_region_id";

}

function api_design_ad_load_or_fail($id) {

	$row = api_row(api_design_ad_select() . " WHERE ads.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Ad'));

	}

	return $row;

}

// GET /design/ads: oldest first, cursor paged by id.
function api_design_ads_list($params) {

	$where = array("ads.id > '" . api_design_ad_cursor_or_fail($params) . "'");

	if (isset($params['ad_region_id']) && ((int)$params['ad_region_id'] > 0)) {

		$where[] = "ads.ad_region_id = '" . (int)$params['ad_region_id'] . "'";

	}

	$limit = (int)$params['limit'];

	list($rows, $next) = api_design_ad_page(api_rows(api_design_ad_select() . "
		WHERE " . implode(' AND ', $where) . "
		ORDER BY ads.id ASC
		LIMIT " . ($limit + 1)), $limit);

	api_ok_list(array_map('api_design_ad_present', $rows), $limit, $next);

}

// GET /design/ads/{id}
function api_design_ads_get($params) {

	api_ok(api_design_ad_present(api_design_ad_load_or_fail($params['id'])));

}

// An ad after a create or an update, in the shape of pg_ad_form_values():
// the stored row (or an empty ad) with the fields sent laid over it. Content
// and caption store the site path as {path}. Pure apart from that.
function api_design_ad_values($row, $params, $path = null) {

	$values = array(
		'name'         => ($row === null) ? '' : (string)$row['name'],
		'content'      => ($row === null) ? '' : (string)$row['content'],
		'caption'      => ($row === null) ? '' : (string)$row['caption'],
		'ad_region_id' => ($row === null) ? '' : (string)(int)$row['ad_region_id'],
		'label'        => ($row === null) ? '' : (string)$row['label'],
		'sort_order'   => ($row === null) ? '0' : (string)(int)$row['sort_order']
	);

	foreach (array('name', 'label') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = trim((string)$params[$field]);

		}

	}

	foreach (array('content', 'caption') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = (string)prepare_rich_text_editor_content_for_input((string)$params[$field], $path);

		}

	}

	foreach (array('ad_region_id', 'sort_order') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = (string)(int)$params[$field];

		}

	}

	return $values;

}

// The screen's checks (pg_ad_form_validate()): a name, at most 100
// characters, no other ad's (409 name_taken); a region that exists (422).
function api_design_ad_prepare($params, $row) {

	$values = api_design_ad_values($row, $params);

	$id = ($row === null) ? 0 : (int)$row['id'];

	if ($values['name'] === '') {

		api_fail_validation(lang('Name is required.'), 'name');

	}

	if (pg_name_too_long($values['name'], 100)) {

		api_fail_validation(lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(100))), 'name');

	}

	$taken = pg_ad_name_in_use('ads', $values['name'], ($row === null) ? null : $id);

	if ($taken === null) {

		api_fail_server();

	}

	if ($taken) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	$exists = pg_ad_region_exists((int)$values['ad_region_id']);

	if ($exists === null) {

		api_fail_server();

	}

	if (!$exists) {

		api_fail_validation(lang('The selected Ad Region was not found, so please select a different Ad Region.'), 'ad_region_id');

	}

	return array('id' => $id, 'values' => $values);

}

function api_design_ad_commit($prepared) {

	$app = api_current_app();

	$saved = pg_ad_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_design_ad_log(($prepared['id'] <= 0)
		? 'Ad ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Ad ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /design/ads
function api_design_ads_create($params) {

	$prepared = api_design_ad_prepare($params, null);

	api_dry_run_stop('created', 'ad', array('name' => $prepared['values']['name'], 'ad_region_id' => (int)$prepared['values']['ad_region_id']));

	api_ok(api_design_ad_present(api_design_ad_load_or_fail(api_design_ad_commit($prepared))), 201);

}

// POST /design/ads/{id}
function api_design_ads_update($params) {

	$prepared = api_design_ad_prepare($params, api_design_ad_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'ad', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'ad_region_id' => (int)$prepared['values']['ad_region_id']));

	api_ok(api_design_ad_present(api_design_ad_load_or_fail(api_design_ad_commit($prepared))));

}

// DELETE /design/ads/{id} and its POST twin
function api_design_ads_delete($params) {

	$row = api_design_ad_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'ad', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_ad_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_design_ad_log('Ad ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}

/* ---------------------------------------------------------------------------
   Ad regions
   --------------------------------------------------------------------------- */

function api_design_ad_region_present($row) {

	$dynamic = ((string)$row['display_type'] === 'dynamic');

	return array(
		'id'                  => (int)$row['id'],
		'name'                => (string)$row['name'],
		'tag'                 => '<ad>' . (string)$row['name'] . '</ad>',
		'display_type'        => $dynamic ? 'dynamic' : 'static',
		'transition_type'     => ((string)$row['transition_type'] === 'fade') ? 'fade' : 'slide',
		'transition_duration' => (int)$row['transition_duration'],
		'autoplay'            => ((int)$row['slideshow'] === 1),
		'interval'            => (int)$row['slideshow_interval'],
		'continuous'          => ((int)$row['slideshow_continuous'] === 1),
		'ads'                 => isset($row['ads']) ? (int)$row['ads'] : 0,
		'created_at'          => api_time($row['created_timestamp']),
		'updated_at'          => api_time($row['last_modified_timestamp'])
	);

}

function api_design_ad_region_schema() {

	return array(
		'id'                  => 'integer',
		'name'                => 'string',
		'tag'                 => 'string',
		'display_type'        => 'string',
		'transition_type'     => 'string',
		'transition_duration' => 'integer',
		'autoplay'            => 'boolean',
		'interval'            => 'integer',
		'continuous'          => 'boolean',
		'ads'                 => 'integer',
		'created_at'          => 'string?',
		'updated_at'          => 'string?'
	);

}

function api_design_ad_region_select() {

	return "SELECT ad_regions.*, (SELECT COUNT(*) FROM ads WHERE ads.ad_region_id = ad_regions.id) AS ads
		FROM ad_regions";

}

function api_design_ad_region_load_or_fail($id) {

	$row = api_row(api_design_ad_region_select() . " WHERE ad_regions.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Ad Region'));

	}

	return $row;

}

// GET /design/ad-regions: oldest first, cursor paged by id.
function api_design_ad_regions_list($params) {

	$limit = (int)$params['limit'];

	list($rows, $next) = api_design_ad_page(api_rows(api_design_ad_region_select() . "
		WHERE ad_regions.id > '" . api_design_ad_cursor_or_fail($params) . "'
		ORDER BY ad_regions.id ASC
		LIMIT " . ($limit + 1)), $limit);

	api_ok_list(array_map('api_design_ad_region_present', $rows), $limit, $next);

}

// GET /design/ad-regions/{id}
function api_design_ad_regions_get($params) {

	api_ok(api_design_ad_region_present(api_design_ad_region_load_or_fail($params['id'])));

}

// A region's behaviour fields after a create or an update, in the shape
// pg_ad_region_behavior_errors() checks: the stored row (or the screen's
// defaults: static, slide, continuous play) with the fields sent laid over
// it. A stored 0 duration or interval is the screen's blank. Pure.
function api_design_ad_region_fields($row, $params) {

	$blank_zero = function ($column) use ($row) {

		return (($row === null) || ((int)$row[$column] === 0)) ? '' : (string)(int)$row[$column];

	};

	$fields = array(
		'display_type'         => ($row === null) ? 'static' : (string)$row['display_type'],
		'transition_type'      => ($row === null) ? 'slide' : (string)$row['transition_type'],
		'transition_duration'  => $blank_zero('transition_duration'),
		'slideshow'            => ($row === null) ? '0' : (string)(int)$row['slideshow'],
		'slideshow_interval'   => $blank_zero('slideshow_interval'),
		'slideshow_continuous' => ($row === null) ? '1' : (string)(int)$row['slideshow_continuous']
	);

	foreach (array('display_type' => 'display_type', 'transition_type' => 'transition_type', 'transition_duration' => 'transition_duration', 'interval' => 'slideshow_interval') as $param => $field) {

		if (array_key_exists($param, $params)) {

			$fields[$field] = trim((string)$params[$param]);

		}

	}

	foreach (array('autoplay' => 'slideshow', 'continuous' => 'slideshow_continuous') as $param => $field) {

		if (array_key_exists($param, $params)) {

			$fields[$field] = $params[$param] ? '1' : '0';

		}

	}

	return $fields;

}

// The screen's checks: the name (pg_ad_region_name_error(), 409 name_taken
// when another region has it) and the behaviour
// (pg_ad_region_behavior_errors()).
function api_design_ad_region_prepare($params, $row) {

	$id = ($row === null) ? 0 : (int)$row['id'];

	$name = array_key_exists('name', $params) ? trim((string)$params['name']) : (($row === null) ? '' : (string)$row['name']);

	$error = pg_ad_region_name_error($name, ($row === null) ? null : (string)$row['name'], function ($name) {

		return false;

	});

	if ($error !== '') {

		api_fail_validation($error, 'name');

	}

	$taken = pg_ad_name_in_use('ad_regions', $name, ($row === null) ? null : $id);

	if ($taken === null) {

		api_fail_server();

	}

	if ($taken) {

		api_fail(409, 'name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	$fields = api_design_ad_region_fields($row, $params);

	$errors = pg_ad_region_behavior_errors($fields);

	if ($errors) {

		$field = key($errors);

		api_fail_validation(reset($errors), ($field === 'slideshow_interval') ? 'interval' : $field);

	}

	return array('id' => $id, 'name' => $name, 'behavior' => pg_ad_region_behavior_values($fields));

}

function api_design_ad_region_commit($prepared) {

	$app = api_current_app();

	$saved = pg_ad_region_save($prepared['id'], $prepared['name'], $prepared['behavior'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_design_ad_log(($prepared['id'] <= 0)
		? 'Ad region ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Ad region ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['name']);

	return $saved;

}

// POST /design/ad-regions
function api_design_ad_regions_create($params) {

	$prepared = api_design_ad_region_prepare($params, null);

	api_dry_run_stop('created', 'ad_region', array('name' => $prepared['name']));

	api_ok(api_design_ad_region_present(api_design_ad_region_load_or_fail(api_design_ad_region_commit($prepared))), 201);

}

// POST /design/ad-regions/{id}
function api_design_ad_regions_update($params) {

	$prepared = api_design_ad_region_prepare($params, api_design_ad_region_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'ad_region', array('id' => $prepared['id'], 'name' => $prepared['name']));

	api_ok(api_design_ad_region_present(api_design_ad_region_load_or_fail(api_design_ad_region_commit($prepared))));

}

// DELETE /design/ad-regions/{id} and its POST twin: as on the screen, a
// region that still has ads is kept (409 ad_region_has_ads).
function api_design_ad_regions_delete($params) {

	$row = api_design_ad_region_load_or_fail($params['id']);

	$has_ads = pg_ad_region_has_ads((int)$row['id']);

	if ($has_ads === null) {

		api_fail_server();

	}

	if ($has_ads) {

		api_fail(409, 'ad_region_has_ads', lang('Please delete or remove all ads from this ad region before deleting this ad region.'));

	}

	api_dry_run_stop('deleted', 'ad_region', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_ad_region_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_design_ad_log('Ad region ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}
