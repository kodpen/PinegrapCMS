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

/* ----- Writes ------------------------------------------------------------- */

// Opening, changing and closing accounts, through the user screens' own write
// functions (includes/fn/user_write.php). Only an application whose owner is
// an administrator holds users:write, and even then it writes only the
// accounts below its owner: never an administrator, never its owner's own
// row, and no route takes a password. A new account is mailed its single-use
// set password link; a reset stays in the panel.

// The rights the user screens offer a role-3 account on this site, by the
// modules switched on; a test passes the modules itself. The roles above
// hold every right by their role and are offered none.
function api_user_permissions_offered($modules = null) {

	if ($modules === null) {

		$modules = array(
			'forms'           => (FORMS == true),
			'calendars'       => (CALENDARS == true),
			'ecommerce'       => (ECOMMERCE == true),
			'offline_payment' => (ECOMMERCE_OFFLINE_PAYMENT == true),
			'erp'             => (defined('ERP_ENABLED') && ERP_ENABLED && pg_user_has_erp_columns()),
			'erp_readonly'    => (defined('ERP_ENABLED') && ERP_ENABLED && pg_user_has_erp_readonly_column()),
			'workspace'       => pg_user_permission_ws_available()
		);

	}

	$on = function ($key) use ($modules) {

		return !empty($modules[$key]);

	};

	$offered = array('manage_contacts', 'manage_emails', 'manage_visitors', 'create_pages', 'delete_pages');

	if ($on('forms')) {

		$offered[] = 'manage_forms';

	}

	if ($on('calendars')) {

		$offered[] = 'manage_calendars';
		$offered[] = 'publish_calendar_events';

	}

	if ($on('ecommerce')) {

		$offered[] = 'manage_ecommerce';
		$offered[] = 'view_card_data';
		$offered[] = 'manage_ecommerce_reports';

		if ($on('offline_payment')) {

			$offered[] = 'set_offline_payment';

		}

	}

	if ($on('erp')) {

		$offered[] = 'manage_erp';
		$offered[] = 'manage_erp_cash';
		$offered[] = 'manage_erp_settings';

		if ($on('erp_readonly')) {

			$offered[] = 'manage_erp_readonly';

		}

	}

	if ($on('workspace')) {

		$offered[] = 'manage_workspace';
		$offered[] = 'manage_workspace_assign';
		$offered[] = 'manage_workspace_board';
		$offered[] = 'manage_workspace_settings';

	}

	return $offered;

}

// The role a write may give, as user.user_role holds it, or null: strictly
// below the owner's own, so an administrator's application writes designers,
// managers and users and nobody writes an administrator.
function api_user_write_role($code, $owner_role) {

	$role = array_search((string)$code, api_user_roles(), true);

	if (($role === false) || ((int)$role <= (int)$owner_role)) {

		return null;

	}

	return (int)$role;

}

// An account's stored values as the fields of the write functions, so an
// update that sends one field writes every other back as it was. The rights
// and page types are copied as stored ('yes'/'no', 1/0).
function api_user_fields_from_row($row) {

	$fields = array(
		'username'      => (string)$row['user_username'],
		'email'         => (string)$row['user_email'],
		'role'          => (int)$row['user_role'],
		'home_page'     => (string)(int)$row['user_home'],
		'badge'         => (string)$row['user_badge'],
		'badge_label'   => (string)$row['user_badge_label'],
		'reward_points' => (string)(int)$row['user_reward_points'],
		// The balance read here, as the edit screen posts it: the update
		// writes user_reward_points only when the request changed it, so an
		// order or a conversion to store credit landing meanwhile is kept.
		'reward_points_original' => (string)(int)$row['user_reward_points'],
		'password_hint' => isset($row['user_password_hint']) ? (string)$row['user_password_hint'] : ''
	);

	foreach (api_user_permission_columns() as $field => $column) {

		if (isset($row[$column[0]])) {

			$fields[$field] = (string)$row[$column[0]];

		}

	}

	foreach (api_user_page_types() as $type) {

		$fields['set_page_type_' . $type] = isset($row['user_set_page_type_' . $type]) ? (string)$row['user_set_page_type_' . $type] : '';

	}

	return $fields;

}

// The fields of a new account before anything is sent: what the create screen
// starts with ticked.
function api_user_fields_new() {

	return array(
		'role'                    => 3,
		'create_pages'            => '1',
		'delete_pages'            => '1',
		'publish_calendar_events' => 'yes'
	);

}

// Lays the sent values over $fields. Answers array('fields' => ...) or
// array('error' => message, 'field' => name). $offered is what
// api_user_permissions_offered() lists, $page_types the page types of the
// site; $owner_role bounds the role.
function api_user_fields_apply($fields, $params, $owner_role, $offered, $page_types) {

	foreach (array('username', 'email', 'badge_label') as $name) {

		if (isset($params[$name])) {

			$fields[$name] = trim((string)$params[$name]);

		}

	}

	if (isset($params['role'])) {

		$role = api_user_write_role($params['role'], $owner_role);

		if ($role === null) {

			return array('error' => lang('An application cannot give an account this role.'), 'field' => 'role');

		}

		$fields['role'] = $role;

	}

	if (array_key_exists('start_page_id', $params)) {

		$fields['home_page'] = (string)(int)$params['start_page_id'];

	}

	if (isset($params['badge'])) {

		$fields['badge'] = $params['badge'] ? '1' : '0';

	}

	if (isset($params['reward_points'])) {

		$fields['reward_points'] = (string)(int)$params['reward_points'];

	}

	if (isset($params['permissions'])) {

		if ((int)$fields['role'] !== 3) {

			return array('error' => lang('Only an account with the user role takes permissions; the other roles hold every right by their role.'), 'field' => 'permissions');

		}

		$columns = api_user_permission_columns();

		foreach ($params['permissions'] as $key => $value) {

			if ($key === 'page_types') {

				if (!is_array($value) || (!empty($value) && array_values($value) !== $value)) {

					return array('error' => lang('permissions.page_types must be a list.'), 'field' => 'permissions');

				}

				foreach ($value as $type) {

					if (!is_string($type) || !in_array($type, $page_types, true)) {

						return array('error' => lang(array('string' => 'The page type {var:1} is not offered on this site.', 'vars' => is_scalar($type) ? (string)$type : '')), 'field' => 'permissions');

					}

				}

				foreach ($page_types as $type) {

					$fields['set_page_type_' . $type] = in_array($type, $value, true) ? '1' : '0';

				}

				continue;

			}

			if (!in_array((string)$key, $offered, true) || !isset($columns[$key])) {

				return array('error' => lang(array('string' => 'The right {var:1} is not offered on this site.', 'vars' => (string)$key)), 'field' => 'permissions');

			}

			if (!is_bool($value)) {

				return array('error' => lang(array('string' => 'permissions.{var:1} must be true or false.', 'vars' => (string)$key)), 'field' => 'permissions');

			}

			if ($columns[$key][1] === 'enum') {

				$fields[$key] = $value ? 'yes' : 'no';

			} else {

				$fields[$key] = $value ? '1' : '0';

			}

		}

	}

	return array('fields' => $fields);

}

// The owner of the calling application when it may write accounts at all:
// the scope ceiling already keeps users:write at role 0, and the handler
// checks it again rather than trust the ceiling alone.
function api_user_writer_or_fail() {

	$owner = api_current_app()['owner'];

	if ((int)$owner['role'] !== 0) {

		api_fail(403, 'forbidden', lang('Only an application whose owner is an administrator writes accounts.'));

	}

	return $owner;

}

// Whether an application may change or delete this account: never an
// administrator's, never its owner's own, and only an account below the
// owner's role.
function api_user_row_writable($row, $owner) {

	$role = (int)$row['user_role'];

	return ($role !== 0) && ((int)$row['user_id'] !== (int)$owner['id']) && ($role > (int)$owner['role']);

}

// The account an update or a delete names, loaded with the columns the write
// functions need; 404 when there is none, 403 for an administrator or the
// owner's own account.
function api_user_writable_row_or_fail($id, $owner) {

	$optional = api_user_optional_columns();

	$columns = array('user_id', 'user_username', 'user_email', 'user_role', 'user_home', 'user_badge', 'user_badge_label', 'user_reward_points', 'user_password_hint');

	foreach (api_user_permission_columns() as $column) {

		if (($column[2] === '') || !empty($optional[$column[2]])) {

			$columns[] = $column[0];

		}

	}

	foreach (api_user_page_types() as $type) {

		$columns[] = 'user_set_page_type_' . $type;

	}

	$row = api_row("SELECT " . implode(', ', $columns) . " FROM user WHERE user_id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('User'));

	}

	if (!api_user_row_writable($row, $owner)) {

		api_fail(403, 'forbidden', lang('An application does not change or delete an administrator or its owner\'s own account.'));

	}

	return $row;

}

// The checks every write makes on the fields it will save: a username, a
// valid e-mail address, both free (409 username_taken, email_taken), a start
// page that is a page.
function api_user_fields_check_or_fail($fields, $id) {

	$problem = pg_user_write_problem($fields['username'] ?? '', $fields['email'] ?? '', $id);

	if (($problem === 'username_taken') || ($problem === 'email_taken')) {

		api_fail(409, $problem, pg_user_write_problem_message($problem) . '.');

	}

	if ($problem !== '') {

		api_fail_validation(pg_user_write_problem_message($problem) . '.', ($problem === 'username_required') ? 'username' : 'email');

	}

	$page = (int)($fields['home_page'] ?? 0);

	if (($page > 0) && (api_row("SELECT page_id FROM page WHERE page_id = '" . $page . "' LIMIT 1") === null)) {

		api_fail_validation(lang('The start page does not exist.'), 'start_page_id');

	}

}

// The answer of a write: the account as GET /users/{id} shows it.
function api_user_answer($id, $status = 200) {

	$row = api_row(api_users_select_sql(api_user_optional_columns()) . " WHERE user.user_id = '" . (int)$id . "' LIMIT 1");

	$two_step = api_users_two_step(array($row));

	api_ok(api_user_present($row, isset($two_step[(int)$id]) ? $two_step[(int)$id] : null), $status);

}

// What user.created, user.updated and user.deleted carry: never a hash, a
// second-factor secret or a token.
function api_user_event_payload($id, $fields) {

	return array(
		'id'       => (int)$id,
		'username' => (string)($fields['username'] ?? ''),
		'email'    => (string)($fields['email'] ?? ''),
		'role'     => api_user_role_code($fields['role'] ?? 3)
	);

}

function api_users_create($params) {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

	$app = api_current_app();

	$owner = api_user_writer_or_fail();

	$applied = api_user_fields_apply(api_user_fields_new(), $params, $owner['role'], api_user_permissions_offered(), pg_user_write_site_page_types());

	if (isset($applied['error'])) {

		api_fail_validation($applied['error'], $applied['field']);

	}

	$fields = $applied['fields'];

	api_user_fields_check_or_fail($fields, 0);

	if (isset($params['customer_id'])) {

		if (api_row("SELECT id FROM contacts WHERE id = '" . (int)$params['customer_id'] . "' LIMIT 1") === null) {

			api_fail_validation(lang('The customer does not exist.'), 'customer_id');

		}

		$fields['contact_id'] = (string)(int)$params['customer_id'];

	}

	api_dry_run_stop('created', 'user', array('username' => $fields['username'], 'role' => api_user_role_code($fields['role'])));

	$created = pg_user_create($fields, (int)$owner['id']);

	$id = (int)$created['id'];

	// The account learns of itself by the set password link; no password
	// leaves the site.
	pg_user_new_account_mail($id, $fields);

	log_activity(lang(array(
		'string' => 'User ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($fields['username'], $app['name'], $app['api_key'])
	)), $owner['username']);

	pg_announce('user.created', api_user_event_payload($id, $fields));

	api_user_answer($id, 201);

}

function api_users_update($params) {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

	$app = api_current_app();

	$owner = api_user_writer_or_fail();

	$id = (int)$params['id'];

	$row = api_user_writable_row_or_fail($id, $owner);

	$sent = $params;

	unset($sent['id']);

	if (empty($sent)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$applied = api_user_fields_apply(api_user_fields_from_row($row), $params, $owner['role'], api_user_permissions_offered(), pg_user_write_site_page_types());

	if (isset($applied['error'])) {

		api_fail_validation($applied['error'], $applied['field']);

	}

	$fields = $applied['fields'];

	api_user_fields_check_or_fail($fields, $id);

	// The "Account enabled" switch of the edit screen, with its own guard
	// (pg_user_form_may_disable()).
	if (isset($params['disabled'])) {

		if (!pg_user_has_disabled_at()) {

			api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

		}

		$fields['account_enabled_drawn'] = '1';

		$fields['account_enabled'] = $params['disabled'] ? '' : '1';

	}

	api_dry_run_stop('updated', 'user', array('id' => $id));

	// Folder and item access is not part of the API and stays as it is.
	pg_user_update($id, $fields, array('id' => (int)$owner['id'], 'role' => (int)$owner['role']), false);

	log_activity(lang(array(
		'string' => 'User ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($fields['username'], $app['name'], $app['api_key'])
	)), $owner['username']);

	pg_announce('user.updated', api_user_event_payload($id, $fields));

	api_user_answer($id);

}

function api_users_delete($params) {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');

	$app = api_current_app();

	$owner = api_user_writer_or_fail();

	$id = (int)$params['id'];

	$row = api_user_writable_row_or_fail($id, $owner);

	api_dry_run_stop('deleted', 'user', array('id' => $id));

	pg_user_delete($id);

	log_activity(lang(array(
		'string' => 'User ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['user_username'], $app['name'], $app['api_key'])
	)), $owner['username']);

	pg_announce('user.deleted', api_user_event_payload($id, array(
		'username' => $row['user_username'],
		'email'    => $row['user_email'],
		'role'     => $row['user_role']
	)));

	api_ok(array('id' => $id, 'deleted' => true));

}
