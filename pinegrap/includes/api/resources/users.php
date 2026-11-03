<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The panel's accounts (view_users.php, edit_user.php), read only: who they
// are, their role and rights, when they were last online and whether they
// sign in with a second factor, for an HR, SSO or audit system. No password,
// hint, token, PIN, key or second-factor secret is ever read.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The roles as the API names them, by the number user.user_role holds.
function api_user_roles() {

	return array(0 => 'administrator', 1 => 'designer', 2 => 'manager', 3 => 'user');

}

function api_user_role_code($role) {

	$roles = api_user_roles();

	return isset($roles[(int)$role]) ? $roles[(int)$role] : 'user';

}

// The rows an owner reaches, as on the users screen: an administrator every
// account, anyone else only the accounts with less power. The scope ceiling
// keeps the owner at role 0 or 1.
function api_user_owner_filter($owner) {

	$role = (int)$owner['role'];

	return ($role === 0) ? '' : 'user.user_role > ' . $role;

}

// The filters of the list as conditions, apart from the cursor, with the row
// rule of the owner last.
function api_users_where($params, $owner) {

	$where = array();

	if (isset($params['role'])) {

		$role = array_search((string)$params['role'], api_user_roles(), true);

		if ($role !== false) {

			$where[] = "user.user_role = '" . (int)$role . "'";

		}

	}

	if (isset($params['email']) && (string)$params['email'] !== '') {

		$where[] = "user.user_email = '" . escape((string)$params['email']) . "'";

	}

	if (isset($params['customer_id'])) {

		$where[] = "user.user_contact = '" . (int)$params['customer_id'] . "'";

	}

	$rule = api_user_owner_filter($owner);

	if ($rule !== '') {

		$where[] = $rule;

	}

	return $where;

}

// The rights a user row carries, field => column, and whether the column is
// an ENUM('no','yes') (the inherited user_ ones) or a TINYINT. The ERP and
// Workspace ones are added by an upgrade, so each group names its probe.
function api_user_permission_columns() {

	return array(
		'manage_contacts'          => array('user_manage_contacts', 'enum', ''),
		'manage_emails'            => array('user_manage_emails', 'enum', ''),
		'manage_ecommerce'         => array('user_manage_ecommerce', 'enum', ''),
		'manage_ecommerce_reports' => array('manage_ecommerce_reports', 'tinyint', ''),
		'view_card_data'           => array('user_view_card_data', 'tinyint', ''),
		'set_offline_payment'      => array('user_set_offline_payment', 'tinyint', ''),
		'manage_forms'             => array('user_manage_forms', 'enum', ''),
		'manage_calendars'         => array('user_manage_calendars', 'enum', ''),
		'publish_calendar_events'  => array('user_publish_calendar_events', 'enum', ''),
		'manage_visitors'          => array('user_manage_visitors', 'enum', ''),
		'create_pages'             => array('user_create_pages', 'tinyint', ''),
		'delete_pages'             => array('user_delete_pages', 'tinyint', ''),
		'manage_erp'               => array('manage_erp', 'tinyint', 'erp'),
		'manage_erp_cash'          => array('manage_erp_cash', 'tinyint', 'erp'),
		'manage_erp_settings'      => array('manage_erp_settings', 'tinyint', 'erp'),
		'manage_erp_readonly'      => array('manage_erp_readonly', 'tinyint', 'erp_readonly'),
		'manage_workspace'         => array('manage_workspace', 'tinyint', 'workspace'),
		'manage_workspace_assign'  => array('manage_workspace_assign', 'tinyint', 'workspace'),
		'manage_workspace_board'   => array('manage_workspace_board', 'tinyint', 'workspace'),
		'manage_workspace_settings' => array('manage_workspace_settings', 'tinyint', 'workspace')
	);

}

// The page types a user may give a page (user_set_page_type_<type>).
function api_user_page_types() {

	return array(
		'email_a_friend', 'photo_gallery', 'custom_form', 'custom_form_confirmation', 'form_list_view',
		'form_item_view', 'form_view_directory', 'folder_view', 'calendar_view', 'calendar_event_view',
		'catalog', 'catalog_detail', 'express_order', 'order_form', 'shopping_cart',
		'shipping_address_and_arrival', 'shipping_method', 'billing_information', 'order_preview', 'order_receipt'
	);

}

// Which upgrade-added columns the select may name, from the probes; a test
// passes the answers itself.
function api_user_optional_columns($present = null) {

	if ($present === null) {

		$present = array(
			'created'      => pg_auth_column_exists('user', 'user_created'),
			'google'       => pg_auth_column_exists('user', 'user_google_id'),
			'erp'          => pg_user_has_erp_columns(),
			'erp_readonly' => pg_user_has_erp_readonly_column(),
			'workspace'    => pg_user_has_ws_columns()
		);

	}

	return array(
		'created'      => !empty($present['created']),
		'google'       => !empty($present['google']),
		'erp'          => !empty($present['erp']),
		'erp_readonly' => !empty($present['erp_readonly']),
		'workspace'    => !empty($present['workspace'])
	);

}

// The columns read, by name: never user.*, so a secret column cannot reach
// the presenter by accident.
function api_users_select_sql($optional) {

	$columns = array(
		'user.user_id', 'user.user_username', 'user.user_email', 'user.user_role', 'user.user_home',
		'user.user_contact', 'user.timezone', 'user.user_timestamp', 'user.user_online_timestamp'
	);

	if ($optional['created']) {

		$columns[] = 'user.user_created';

	}

	// Whether the account is linked to Google, never the id itself.
	if ($optional['google']) {

		$columns[] = "(user.user_google_id IS NOT NULL AND user.user_google_id <> '') AS google_linked";

	}

	foreach (api_user_permission_columns() as $column) {

		if (($column[2] === '') || $optional[$column[2]]) {

			$columns[] = 'user.' . $column[0];

		}

	}

	foreach (api_user_page_types() as $type) {

		$columns[] = 'user.user_set_page_type_' . $type;

	}

	return 'SELECT ' . implode(', ', $columns) . ' FROM user';

}

// The second-factor state of a page of accounts, from what was read for the
// whole page at once: the accounts with a confirmed authenticator key, the
// passkey count of each and the role of each against the role at or below
// which a factor is required (99 for none).
function api_user_two_step_state($ids, $totp_on, $passkeys, $roles, $required_role) {

	$out = array();

	foreach ($ids as $id) {

		$id = (int)$id;

		$authenticator = !empty($totp_on[$id]);

		$count = isset($passkeys[$id]) ? (int)$passkeys[$id] : 0;

		$out[$id] = array(
			'enabled'       => $authenticator || ($count > 0),
			'authenticator' => $authenticator,
			'passkeys'      => $count,
			'required'      => ((int)$required_role < 99) && isset($roles[$id]) && ((int)$roles[$id] <= (int)$required_role)
		);

	}

	return $out;

}

// The second-factor state of the rows, two queries for the page.
function api_users_two_step($rows) {

	$ids = array();

	$roles = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['user_id'];

		$roles[(int)$row['user_id']] = (int)$row['user_role'];

	}

	if (empty($ids)) {

		return array();

	}

	$in = implode(',', $ids);

	$totp_on = array();

	if (pg_mfa_table_exists()) {

		foreach (api_rows("SELECT user_id FROM user_mfa WHERE user_id IN (" . $in . ") AND enabled_at > 0 AND totp_secret <> ''") as $row) {

			$totp_on[(int)$row['user_id']] = true;

		}

	}

	$passkeys = array();

	if (pg_passkeys_table_exists()) {

		foreach (api_rows("SELECT user_id, COUNT(*) AS passkeys FROM user_passkeys WHERE user_id IN (" . $in . ") GROUP BY user_id") as $row) {

			$passkeys[(int)$row['user_id']] = (int)$row['passkeys'];

		}

	}

	// A required role counts only where a factor can be set up, as sign-in
	// asks it (pg_mfa_required_for_user()).
	$required_role = pg_mfa_available() ? pg_mfa_required_role() : 99;

	return api_user_two_step_state($ids, $totp_on, $passkeys, $roles, $required_role);

}

// What api_user_present() returns.
function api_user_schema() {

	return array(
		'id'             => 'integer',
		'username'       => 'string',
		'email'          => 'string?',
		'role'           => 'string',
		'customer_id'    => 'integer?',
		'start_page_id'  => 'integer?',
		'timezone'       => 'string?',
		'permissions'    => 'UserPermissions',
		'two_step'       => 'UserTwoStep',
		'google_linked'  => 'boolean',
		'last_online_at' => 'datetime?',
		'created_at'     => 'datetime?',
		'updated_at'     => 'datetime?'
	);

}

// What the permissions field of api_user_present() holds.
function api_user_permissions_schema() {

	$schema = array();

	foreach (array_keys(api_user_permission_columns()) as $field) {

		$schema[$field] = 'boolean';

	}

	$schema['page_types'] = 'string[]';

	return $schema;

}

// What the two_step field of api_user_present() holds.
function api_user_two_step_schema() {

	return array(
		'enabled'       => 'boolean',
		'authenticator' => 'boolean',
		'passkeys'      => 'integer',
		'required'      => 'boolean'
	);

}

// What the permissions field of a user holds, from its row: a right whose
// column the upgrade has not added yet reads false.
function api_user_permissions_present($row) {

	$rights = array();

	foreach (api_user_permission_columns() as $field => $column) {

		$value = isset($row[$column[0]]) ? strtolower(trim((string)$row[$column[0]])) : '';

		$rights[$field] = ($column[1] === 'enum') ? ($value === 'yes') : ((int)$value === 1);

	}

	$rights['page_types'] = array();

	foreach (api_user_page_types() as $type) {

		if (isset($row['user_set_page_type_' . $type]) && (int)$row['user_set_page_type_' . $type] === 1) {

			$rights['page_types'][] = $type;

		}

	}

	return $rights;

}

// What the two_step field of a user holds, from its entry of
// api_users_two_step(); null reads as no factor.
function api_user_two_step_present($two_step) {

	if (!is_array($two_step)) {

		$two_step = array('enabled' => false, 'authenticator' => false, 'passkeys' => 0, 'required' => false);

	}

	return array(
		'enabled'       => (bool)$two_step['enabled'],
		'authenticator' => (bool)$two_step['authenticator'],
		'passkeys'      => (int)$two_step['passkeys'],
		'required'      => (bool)$two_step['required']
	);

}

// A user row as the API shows it. $two_step is the account's entry of
// api_users_two_step(), null when none was read.
function api_user_present($row, $two_step) {

	return array(
		'id'             => (int)$row['user_id'],
		'username'       => (string)$row['user_username'],
		'email'          => ((string)$row['user_email'] !== '') ? (string)$row['user_email'] : null,
		'role'           => api_user_role_code($row['user_role']),
		'customer_id'    => ((int)$row['user_contact'] > 0) ? (int)$row['user_contact'] : null,
		'start_page_id'  => ((int)$row['user_home'] > 0) ? (int)$row['user_home'] : null,
		'timezone'       => ((string)$row['timezone'] !== '') ? (string)$row['timezone'] : null,
		'permissions'    => api_user_permissions_present($row),
		'two_step'       => api_user_two_step_present($two_step),
		'google_linked'  => !empty($row['google_linked']),
		'last_online_at' => api_time(isset($row['user_online_timestamp']) ? $row['user_online_timestamp'] : 0),
		'created_at'     => api_time(isset($row['user_created']) ? $row['user_created'] : 0),
		'updated_at'     => api_time(isset($row['user_timestamp']) ? $row['user_timestamp'] : 0)
	);

}

// Paged by id: user_timestamp does not move on a sign-in or a second-factor
// change, so there is no updated_since.
function api_users_list($params) {

	$where = api_users_where($params, api_current_app()['owner']);

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "user.user_id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_users_select_sql(api_user_optional_columns())
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY user.user_id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['user_id'], (int)$last['user_id']);

	}

	$two_step = api_users_two_step($rows);

	$out = array();

	foreach ($rows as $row) {

		$id = (int)$row['user_id'];

		$out[] = api_user_present($row, isset($two_step[$id]) ? $two_step[$id] : null);

	}

	api_ok_list($out, $limit, $next);

}

function api_users_get($params) {

	$id = (int)$params['id'];

	$where = array("user.user_id = '" . $id . "'");

	$rule = api_user_owner_filter(api_current_app()['owner']);

	if ($rule !== '') {

		$where[] = $rule;

	}

	$row = api_row(api_users_select_sql(api_user_optional_columns()) . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1');

	if ($row === null) {

		api_fail_not_found(lang('User'));

	}

	$two_step = api_users_two_step(array($row));

	api_ok(api_user_present($row, isset($two_step[$id]) ? $two_step[$id] : null));

}
