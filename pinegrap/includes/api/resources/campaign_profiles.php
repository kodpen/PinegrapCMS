<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Campaign profiles: the campaign made by itself when something happens (a
// reservation, a form, an order, a booking, another campaign sent). Read under
// campaigns:read, written under campaigns:write with the checks of the
// profile form (includes/forms/email_campaign_profile_form.php) and the write
// path the screens call (includes/fn/email_campaign_profiles.php). An account
// with the e-mail right but the user role reaches only the profiles it
// created, as on the profile screens. No events.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');

// The condition that keeps a query to the profiles an owner may reach: all of
// them from the manager role up, its own below that. Pure.
function api_campaign_profile_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return "email_campaign_profiles.created_user_id = '" . (int)$owner['id'] . "'";

}

// Every action a profile may have, as the import and the form accept them.
function api_campaign_profile_actions() {

	return array_merge(
		array('calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent'),
		pg_email_campaign_profile_commerce_actions(),
		pg_email_campaign_profile_booking_actions(),
		pg_email_campaign_profile_group_actions()
	);

}

// A stored schedule time as HH:MM, null for 00:00:00 ("send at any time").
// Pure.
function api_campaign_profile_time_out($stored) {

	$stored = (string)$stored;

	if (($stored === '') || ($stored === '00:00:00')) {

		return null;

	}

	return substr($stored, 0, 5);

}

// A schedule time sent as HH:MM (24-hour clock) as it is stored; '' or null
// clears it. A chosen midnight is stored as 00:01:00, since 00:00:00 means
// "any time" (pg_email_campaign_profile_schedule_time()). null when the text
// is no time. Pure.
function api_campaign_profile_time_in($sent) {

	$sent = trim((string)$sent);

	if ($sent === '') {

		return '00:00:00';

	}

	if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $sent) !== 1) {

		return null;

	}

	return ($sent === '00:00') ? '00:01:00' : $sent . ':00';

}

function api_campaign_profile_present($row) {

	$item_field = pg_email_campaign_profile_action_item_field((string)$row['action']);

	return array(
		'id'              => (int)$row['id'],
		'name'            => (string)$row['name'],
		'enabled'         => ((int)$row['enabled'] === 1),
		'action'          => (string)$row['action'],
		// The calendar event, custom form page, followed profile or product
		// the action is tied to; null for an action tied to no record.
		'action_item_id'  => (($item_field !== '') && ((int)$row['action_item_id'] > 0)) ? (int)$row['action_item_id'] : null,
		'subject'         => (string)$row['subject'],
		'format'          => (string)$row['format'],
		'body'            => (string)$row['body'],
		'page_id'         => (((string)$row['format'] === 'html') && ((int)$row['page_id'] > 0)) ? (int)$row['page_id'] : null,
		'from_name'       => (string)$row['from_name'],
		'from_email'      => (string)$row['from_email_address'],
		'reply_to'        => ((string)$row['reply_email_address'] !== '') ? (string)$row['reply_email_address'] : null,
		'bcc'             => ((string)$row['bcc_email_address'] !== '') ? (string)$row['bcc_email_address'] : null,
		'schedule_time'   => api_campaign_profile_time_out($row['schedule_time']),
		'schedule_length' => (int)$row['schedule_length'],
		'schedule_unit'   => (string)$row['schedule_unit'],
		'schedule_period' => (string)$row['schedule_period'],
		'schedule_base'   => (string)$row['schedule_base'],
		'purpose'         => (string)$row['purpose'],
		'created_by'      => ((string)($row['created_username'] ?? '') !== '') ? (string)$row['created_username'] : null,
		'created_at'      => api_time($row['created_timestamp']),
		'updated_at'      => api_time($row['last_modified_timestamp'])
	);

}

function api_campaign_profile_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string',
		'enabled'         => 'boolean',
		'action'          => 'string',
		'action_item_id'  => 'integer?',
		'subject'         => 'string',
		'format'          => 'string',
		'body'            => 'string',
		'page_id'         => 'integer?',
		'from_name'       => 'string',
		'from_email'      => 'string',
		'reply_to'        => 'string?',
		'bcc'             => 'string?',
		'schedule_time'   => 'string?',
		'schedule_length' => 'integer',
		'schedule_unit'   => 'string',
		'schedule_period' => 'string',
		'schedule_base'   => 'string',
		'purpose'         => 'string',
		'created_by'      => 'string?',
		'created_at'      => 'string?',
		'updated_at'      => 'string?'
	);

}

// A profile after a create or an update, as the columns the form saves: the
// stored row (or a new profile's starting values) with the fields sent laid
// over it, the item, period and base settled by the action as the form
// settles them (pg_email_campaign_profile_action_values()). schedule_time is
// null when the time sent is no HH:MM. Pure.
function api_campaign_profile_values($row, $params) {

	$values = array(
		'name'                => '',
		'enabled'             => '1',
		'action'              => '',
		'action_item_id'      => '0',
		'subject'             => '',
		'format'              => 'plain_text',
		'body'                => '',
		'page_id'             => '0',
		'from_name'           => '',
		'from_email_address'  => '',
		'reply_email_address' => '',
		'bcc_email_address'   => '',
		'schedule_time'       => '00:00:00',
		'schedule_length'     => '0',
		'schedule_unit'       => 'days',
		'schedule_period'     => '',
		'schedule_base'       => '',
		'purpose'             => 'commercial'
	);

	if ($row !== null) {

		foreach (array_keys($values) as $column) {

			$values[$column] = (string)$row[$column];

		}

	}

	$map = array(
		'name'            => 'name',
		'action'          => 'action',
		'subject'         => 'subject',
		'format'          => 'format',
		'body'            => 'body',
		'from_name'       => 'from_name',
		'from_email'      => 'from_email_address',
		'reply_to'        => 'reply_email_address',
		'bcc'             => 'bcc_email_address',
		'schedule_unit'   => 'schedule_unit',
		'schedule_period' => 'schedule_period',
		'schedule_base'   => 'schedule_base',
		'purpose'         => 'purpose'
	);

	foreach ($map as $field => $column) {

		if (array_key_exists($field, $params)) {

			$values[$column] = ($column === 'body') ? (string)$params[$field] : trim((string)$params[$field]);

		}

	}

	if (array_key_exists('enabled', $params)) {

		$values['enabled'] = $params['enabled'] ? '1' : '0';

	}

	foreach (array('action_item_id', 'page_id', 'schedule_length') as $field) {

		if (array_key_exists($field, $params)) {

			$values[$field] = ((string)$params[$field] === '') ? '0' : (string)(int)$params[$field];

		}

	}

	if (array_key_exists('schedule_time', $params)) {

		$values['schedule_time'] = api_campaign_profile_time_in($params['schedule_time']);

	}

	$item_field = pg_email_campaign_profile_action_item_field($values['action']);

	$settled = pg_email_campaign_profile_action_values($values['action'], array(
		$item_field       => $values['action_item_id'],
		'schedule_period' => $values['schedule_period'],
		'schedule_base'   => $values['schedule_base']
	));

	if ($settled['action_item_id'] !== '') {

		$values['action_item_id'] = $settled['action_item_id'];
		$values['schedule_period'] = $settled['schedule_period'];
		$values['schedule_base'] = $settled['schedule_base'];

	}

	return $values;

}

function api_campaign_profile_load_or_fail($id) {

	$app = api_current_app();

	$filter = api_campaign_profile_owner_filter($app['owner']);

	$row = api_row("SELECT email_campaign_profiles.*, created_user.user_username AS created_username
		FROM email_campaign_profiles
		LEFT JOIN user AS created_user ON created_user.user_id = email_campaign_profiles.created_user_id
		WHERE email_campaign_profiles.id = '" . (int)$id . "'" . (($filter === '') ? '' : ' AND ' . $filter) . "
		LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Campaign Profile'));

	}

	return $row;

}

function api_campaign_profile_answer($id, $status_code = 200) {

	api_ok(api_campaign_profile_present(api_campaign_profile_load_or_fail($id)), $status_code);

}

// GET /campaigns/profiles: by id, newest first, cursor paged.
function api_campaign_profiles_list($params) {

	$app = api_current_app();

	$where = array();

	$filter = api_campaign_profile_owner_filter($app['owner']);

	if ($filter !== '') {

		$where[] = $filter;

	}

	if (isset($params['action'])) {

		$where[] = "email_campaign_profiles.action = '" . escape($params['action']) . "'";

	}

	if (isset($params['enabled'])) {

		$where[] = "email_campaign_profiles.enabled = '" . ($params['enabled'] ? 1 : 0) . "'";

	}

	if (isset($params['q']) && ($params['q'] !== '')) {

		$where[] = "email_campaign_profiles.name LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "email_campaign_profiles.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT email_campaign_profiles.*, created_user.user_username AS created_username
		FROM email_campaign_profiles
		LEFT JOIN user AS created_user ON created_user.user_id = email_campaign_profiles.created_user_id"
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY email_campaign_profiles.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_campaign_profile_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// GET /campaigns/profiles/{id}
function api_campaign_profiles_get($params) {

	api_campaign_profile_answer((int)$params['id']);

}

// The checked profile of a create or an update, before anything is written:
// the form's rules (pg_email_campaign_profile_values_problem()), the owner's
// access to the item and the page, the ring rule of a "campaign sent"
// trigger, and a name no other profile has (409 campaign_profile_name_taken).
function api_campaign_profile_prepare($params, $row) {

	$app = api_current_app();

	$owner = $app['owner'];

	$id = ($row === null) ? 0 : (int)$row['id'];

	$values = api_campaign_profile_values($row, $params);

	if ($values['schedule_time'] === null) {

		api_fail_validation(lang('schedule_time must be a time in HH:MM form (24-hour clock).'), 'schedule_time');

	}

	foreach (array('subject' => lang('Subject'), 'from_name' => lang('From Name')) as $column => $label) {

		if ($values[$column] === '') {

			api_fail_validation(lang(array('string' => '{var:1} is required', 'vars' => $label)), $column);

		}

	}

	if (in_array($values['action'], pg_email_campaign_profile_booking_actions(), true) && !(function_exists('pg_booking_ready') && pg_booking_ready())) {

		api_fail(503, 'service_unavailable', lang('Booking actions wait for the database update that adds bookings.'), 'action');

	}

	if (in_array($values['action'], pg_email_campaign_profile_group_actions(), true) && !pg_email_campaign_profile_group_action_ready()) {

		api_fail(503, 'service_unavailable', lang('The contact group action waits for the database update that adds it.'), 'action');

	}

	$commerce = defined('ECOMMERCE') && (ECOMMERCE === true) && (((int)$owner['role'] < 3) || ($owner['manage_ecommerce'] == true));

	// The time is checked above in its own form; the form's check reads the
	// site's clock format.
	$problem = pg_email_campaign_profile_values_problem(array('schedule_time' => '') + $values, $commerce);

	if ($problem === '') {

		$problem = pg_email_campaign_profile_owner_access_problem($values, (int)$owner['id'], (int)$owner['role']);

	}

	if (($problem === '') && ($values['action'] === 'email_campaign_sent') && pg_email_campaign_profile_closes_ring($id, $values['action_item_id'], pg_email_campaign_profile_follow_map())) {

		$problem = lang('A campaign profile cannot follow itself or a profile that follows it.');

	}

	if ($problem !== '') {

		api_fail_validation($problem);

	}

	if (pg_email_campaign_profile_name_taken($values['name'], $id)) {

		api_fail(409, 'campaign_profile_name_taken', lang('The name that you entered is already in use, so please enter a different name.'), 'name');

	}

	return array('id' => $id, 'values' => $values);

}

function api_campaign_profile_commit($prepared) {

	$app = api_current_app();

	$saved = pg_email_campaign_profile_save($prepared['id'], $prepared['values'], (int)$app['owner']['id']);

	if ($saved <= 0) {

		api_fail_server();

	}

	api_store_region_log(($prepared['id'] <= 0)
		? 'Campaign profile ({var:1}) was created through the API by the application {var:2} (key {var:3}).'
		: 'Campaign profile ({var:1}) was changed through the API by the application {var:2} (key {var:3}).', $prepared['values']['name']);

	return $saved;

}

// POST /campaigns/profiles
function api_campaign_profiles_create($params) {

	$prepared = api_campaign_profile_prepare($params, null);

	api_dry_run_stop('created', 'campaign_profile', array('name' => $prepared['values']['name'], 'action' => $prepared['values']['action']));

	api_campaign_profile_answer(api_campaign_profile_commit($prepared), 201);

}

// POST /campaigns/profiles/{id}
function api_campaign_profiles_update($params) {

	$prepared = api_campaign_profile_prepare($params, api_campaign_profile_load_or_fail($params['id']));

	api_dry_run_stop('updated', 'campaign_profile', array('id' => $prepared['id'], 'name' => $prepared['values']['name'], 'action' => $prepared['values']['action']));

	api_campaign_profile_answer(api_campaign_profile_commit($prepared));

}

// DELETE /campaigns/profiles/{id} and its POST twin
function api_campaign_profiles_delete($params) {

	$row = api_campaign_profile_load_or_fail($params['id']);

	api_dry_run_stop('deleted', 'campaign_profile', array('id' => (int)$row['id'], 'name' => (string)$row['name']));

	if (!pg_email_campaign_profile_delete((int)$row['id'])) {

		api_fail_server();

	}

	api_store_region_log('Campaign profile ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).', (string)$row['name']);

	api_ok(array('id' => (int)$row['id'], 'deleted' => true));

}
