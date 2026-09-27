<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Signing in from a device, and the caller's own account: who it is, what it
// may do, its devices, its notifications and its push registration.
//
// Everything here is about the person behind the call. For a device that is the
// person who signed in on it; for an application key it is the application's
// owner, which is why an application only reaches these endpoints once the
// operator has given it the account permission - an integration syncing stock
// has no business reading its owner's bell.

if (!defined('PG_API_ENTRY')) {
	exit;
}

/* ---------------------------------------------------------------------------
   Signing in
   --------------------------------------------------------------------------- */

// The raw value of a body field. Tokens are read from here rather than from the
// validated parameters, so that what is hashed is exactly what was sent.
function api_account_raw_input($name) {

	$input = api_request_input();

	return (isset($input[$name]) && is_scalar($input[$name])) ? (string)$input[$name] : '';

}

function api_auth_login($params) {

	if (!api_devices_ready()) {

		api_fail(503, 'not_installed', lang('The API is not installed on this site yet. Run the software upgrade to finish it.'));

	}

	$app = api_device_app(isset($params['client_key']) ? $params['client_key'] : '');

	api_device_app_check($app);

	api_current_app($app);

	$username = trim((string)$params['username']);

	// Trimmed, the way the sign-in screen's form trims it (liveform) - which
	// is also how every password set through the panel was trimmed before it
	// was hashed. Taken as sent, a space pasted at the end would fail here and
	// pass on the website.
	$password = trim(api_account_raw_input('password'));

	if ($password === '') {

		api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => 'password')), 'password');

	}

	// The sign-in screen's own limits, counted in the same buckets: a password
	// list is not cheaper to try here than there. Locked out, the throttle
	// answers 429 itself (pg_login_throttle_deny()).
	pg_login_throttle_guard($username);

	// From a few failures on this account, the sign-in screen asks a question
	// before it looks at the password. A device cannot be asked one, and
	// letting it skip the question would make this the weaker of the two
	// doors, so the attempt waits instead - or the person signs in once on the
	// site, which answers the question and clears the account's count.
	if (api_auth_question_owed($username)) {

		$limits = pg_login_throttle_limits();

		api_extra_headers('Retry-After', (string)(int)$limits['window']);

		api_fail(429, 'rate_limited', lang(array(
			'string' => 'Too many failed sign-in attempts. Please wait {var:1} minutes, or sign in on the website once, and try again.',
			'vars'   => (int)($limits['window'] / 60)
		)));

	}

	$user_id = validate_login($username, $password);

	if ($user_id === false) {

		pg_login_record_failure($username);

		log_activity(lang(array(
			'string' => 'access denied from a device (email or username: {var:1})',
			'vars'   => $username
		)), lang('UNKNOWN'));

		// No Basic challenge on this one: the credentials were a person's, and a
		// browser-based client would otherwise put up its own login dialog.
		api_fail(401, 'unauthorized', lang('The username or password is not correct.'));

	}

	pg_login_throttle_pass($username);

	$owner = api_load_owner((int)$user_id);

	if (($owner === null) || !api_device_account_has_rights($owner)) {

		api_fail(403, 'forbidden', lang('This account has no rights that can be used from a device.'));

	}

	$platform = isset($params['platform']) ? $params['platform'] : 'other';

	$name = (isset($params['device_name']) && $params['device_name'] !== '')
		? $params['device_name']
		: ucfirst($platform);

	$issued = api_device_issue($app, (int)$user_id, $name, $platform,
		isset($params['app_version']) ? $params['app_version'] : '');

	log_activity(lang(array(
		'string' => 'signed in from a device ({var:1})',
		'vars'   => $name
	)), $owner['username']);

	$row = api_row("SELECT api_devices.*, api_apps.name AS app_name
		FROM api_devices
		LEFT JOIN api_apps ON api_apps.id = api_devices.app_id
		WHERE api_devices.id = '" . (int)$issued['device_id'] . "' LIMIT 1");

	api_ok(api_auth_session_present($issued, $row, $owner), 201);

}

// Whether this account is past the point where the sign-in screen asks its
// question.
//
// Only the account's own count is asked here. The screen also asks everyone
// behind an address that has failed a few times, which costs a person a second
// on the website; on a device it would be a refusal, and it would turn one
// mistyped password in an office into fifteen minutes without the app for
// everybody sharing that address. The address still closes at its lockout,
// which pg_login_throttle_guard() applies above.
function api_auth_question_owed($username) {

	$after = pg_login_captcha_after();

	if ($after <= 0) {

		return false;

	}

	$subjects = pg_login_throttle_subjects($username);

	if (!isset($subjects['u'])) {

		return false;

	}

	$limits = pg_login_throttle_limits();

	return (waf_rate_hits($subjects['u'], 'failu', $limits['window']) >= $after);

}

function api_auth_refresh($params) {

	if (!api_devices_ready()) {

		api_fail(401, 'unauthorized', lang('The refresh token is not valid. Sign in again.'));

	}

	$rotated = api_device_rotate(api_account_raw_input('refresh_token'));

	$row = $rotated['row'];

	// Signing in is not all that is checked: the application must still be
	// switched on and the account still able to use a device. Otherwise a
	// refresh token would outlive the operator's decision by a month.
	$app = api_row("SELECT id, name, api_key, owner_user_id, scopes, ip_allowlist,
			status, expires_at, rate_limit_per_min, kind
		FROM api_apps WHERE id = '" . (int)$row['app_id'] . "' AND kind = 'device' LIMIT 1");

	api_device_app_check($app);

	api_current_app($app);

	$owner = api_load_owner((int)$row['user_id']);

	if (($owner === null) || !api_device_account_has_rights($owner)) {

		api_device_revoke((int)$row['id']);

		api_fail(403, 'forbidden', lang('This account has no rights that can be used from a device.'));

	}

	$row['app_name'] = $app['name'];

	api_ok(api_auth_session_present($rotated, $row, $owner));

}

// Signs this device out. Answers with a token of either kind, so an app whose
// access token has run out does not have to renew it only to throw it away;
// an expired access token still names its device.
function api_auth_logout($params) {

	$device_id = 0;

	if (api_devices_ready()) {

		$authorization = api_header('Authorization');

		if (stripos($authorization, 'bearer ') === 0) {

			$device_id = (int)api_value("SELECT id FROM api_devices
				WHERE access_hash = '" . escape(api_secret_hash(trim(substr($authorization, 7)))) . "' LIMIT 1");

		}

		$refresh = api_account_raw_input('refresh_token');

		if (($device_id === 0) && ($refresh !== '')) {

			$device_id = (int)api_value("SELECT id FROM api_devices
				WHERE refresh_hash = '" . escape(api_secret_hash(trim($refresh))) . "' LIMIT 1");

		}

	}

	if ($device_id > 0) {

		// Logged under the device's application, like every other call the
		// device made; the route itself carries no credentials to say so.
		api_current_app(array('id' => (int)api_value("SELECT app_id FROM api_devices WHERE id = '" . $device_id . "' LIMIT 1")));

		api_device_revoke($device_id);

	}

	// The same answer either way. Signing out a token that is already gone is
	// the outcome the caller wanted, and telling the two apart would say which
	// tokens exist.
	api_ok(array('signed_out' => true));

}

function api_auth_session_present($issued, $row, $owner) {

	$now = time();

	return array(
		'token_type'         => 'Bearer',
		'access_token'       => $issued['access_token'],
		'expires_in'         => max(0, (int)$issued['access_expires'] - $now),
		'expires_at'         => api_time($issued['access_expires']),
		'refresh_token'      => $issued['refresh_token'],
		'refresh_expires_in' => max(0, (int)$issued['refresh_expires'] - $now),
		'refresh_expires_at' => api_time($issued['refresh_expires']),
		'device'             => api_device_present($row, (int)$row['id']),
		'user'               => array(
			'id'       => (int)$owner['id'],
			'username' => (string)$owner['username']
		)
	);

}

function api_auth_session_schema() {

	return array(
		'token_type'         => 'string',
		'access_token'       => 'string',
		'expires_in'         => 'integer',
		'expires_at'         => 'string',
		'refresh_token'      => 'string',
		'refresh_expires_in' => 'integer',
		'refresh_expires_at' => 'string',
		'device'             => 'Device',
		'user'               => array('id' => 'integer', 'username' => 'string')
	);

}

/* ---------------------------------------------------------------------------
   Who is calling
   --------------------------------------------------------------------------- */

function api_auth_me($params) {

	$app = api_current_app();

	$owner = $app['owner'];

	$user = api_row("SELECT user.user_id, user.user_username, user.user_email, user.user_role,
			user.timezone, contacts.first_name, contacts.last_name
		FROM user
		LEFT JOIN contacts ON contacts.id = user.user_contact
		WHERE user.user_id = '" . (int)$owner['id'] . "' LIMIT 1");

	$device = api_current_device();

	$unread = null;

	if (api_has_scope(api_current_scopes(), 'account:read')) {

		$unread = api_notifications_unread_count($owner);

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

	// Asked for by id: the name the request carries has the person and the
	// device folded into it for the activity log.
	$app_name = (string)api_value("SELECT name FROM api_apps WHERE id = '" . (int)$app['id'] . "' LIMIT 1");

	api_ok(array(
		'kind' => ($device !== null) ? 'device' : 'application',
		'user' => array(
			'id'         => (int)$user['user_id'],
			'username'   => (string)$user['user_username'],
			'email'      => (string)$user['user_email'],
			'first_name' => (string)$user['first_name'],
			'last_name'  => (string)$user['last_name'],
			'role'       => (int)$user['user_role'],
			'role_name'  => pg_user_role_name((int)$user['user_role']),
			'timezone'   => (string)$user['timezone']
		),
		'application' => array(
			'id'   => (int)$app['id'],
			'name' => $app_name,
			'kind' => isset($app['kind']) ? (string)$app['kind'] : 'server'
		),
		'device'   => ($device !== null) ? api_device_present(array_merge($device, array('app_name' => $app_name)), (int)$device['id']) : null,
		'scopes'   => array_values(api_current_scopes()),
		'features' => array_merge(api_meta_features(), array(
			'ecommerce' => (defined('ECOMMERCE') && ECOMMERCE === true),
			'workspace' => (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED == true),
			'push'      => (pg_push_available() && pg_push_schema_available())
		)),
		'unread_notifications' => $unread,
		'server_time' => api_time(time())
	));

}

function api_auth_me_schema() {

	return array(
		'kind'        => 'string',
		'user'        => array(
			'id'         => 'integer',
			'username'   => 'string',
			'email'      => 'string',
			'first_name' => 'string',
			'last_name'  => 'string',
			'role'       => 'integer',
			'role_name'  => 'string',
			'timezone'   => 'string'
		),
		'application' => array('id' => 'integer', 'name' => 'string', 'kind' => 'string'),
		'device'      => 'Device?',
		'scopes'      => 'string[]',
		'features'    => 'object',
		'unread_notifications' => 'integer?',
		'server_time' => 'string'
	);

}

/* ---------------------------------------------------------------------------
   Devices
   --------------------------------------------------------------------------- */

function api_auth_devices_list($params) {

	$app = api_current_app();

	$device = api_current_device();

	$rows = api_devices_ready()
		? api_rows("SELECT api_devices.*, api_apps.name AS app_name
			FROM api_devices
			LEFT JOIN api_apps ON api_apps.id = api_devices.app_id
			WHERE api_devices.user_id = '" . (int)$app['owner']['id'] . "'
			ORDER BY GREATEST(api_devices.last_used_timestamp, api_devices.created_timestamp) DESC, api_devices.id DESC
			LIMIT 100")
		: array();

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_device_present($row, ($device !== null) ? (int)$device['id'] : 0);

	}

	api_ok_list($out, 100);

}

function api_auth_devices_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_devices_ready()
		? api_row("SELECT id, name FROM api_devices
			WHERE id = '" . $id . "' AND user_id = '" . (int)$app['owner']['id'] . "' LIMIT 1")
		: null;

	if ($row === null) {

		api_fail_not_found(lang('Device'));

	}

	api_dry_run_stop('deleted', 'device', array('id' => $id));

	api_device_revoke($id);

	log_activity(lang(array(
		'string' => 'a device was signed out ({var:1})',
		'vars'   => $row['name']
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'signed_out' => true));

}

/* ---------------------------------------------------------------------------
   Notifications
   ---------------------------------------------------------------------------
   The panel's bell, for the same person. Who may see which kind is decided by
   pg_notification_visible() and nowhere else, so a notification that never
   reaches someone's bell never reaches their app either.
   --------------------------------------------------------------------------- */

function api_notifications_load() {

	require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

}

// The rights the visibility ladder asks about, from the caller's account. The
// owner row already holds the flags as booleans (api_load_owner()).
function api_notifications_rights($owner) {

	$role = (int)$owner['role'];

	return array(
		'id'               => (int)$owner['id'],
		'role'             => $role,
		'manage_ecommerce' => (($role < 3) || !empty($owner['manage_ecommerce'])),
		'manage_forms'     => (($role < 3) || !empty($owner['manage_forms'])),
		'manage_erp'       => (($role < 3) || !empty($owner['manage_erp'])),
		'manage_erp_cash'  => (($role < 3) || !empty($owner['manage_erp_cash']))
	);

}

function api_notifications_unread_count($owner) {

	api_notifications_load();

	$rights = api_notifications_rights($owner);

	$count = 0;

	foreach (pg_notification_unread_rows($rights['id']) as $notification) {

		if (pg_notification_visible($notification, $rights)) {

			$count++;

		}

	}

	return $count;

}

// What the row says, as plain text: the panel's wording (pg_notification_display)
// with its markup taken off, because an app draws text, not HTML.
function api_notification_present($row, $read) {

	$display = pg_notification_display($row);

	// Decoded until it stops changing, at most twice: some rows store the
	// currency sign as an entity already (&#8378;), which the display then
	// escapes a second time for the panel's markup.
	$plain = function ($html) {

		$text = strip_tags(str_ireplace(array('<br>', '<br/>', '<br />'), "\n", (string)$html));

		for ($pass = 0; $pass < 2; $pass++) {

			$decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

			if ($decoded === $text) {

				break;

			}

			$text = $decoded;

		}

		return trim($text);

	};

	$url = (string)$display['url'];

	if (($url === '') || ($url === '#!')) {

		$url = null;

	} elseif (!preg_match('#^https?://#i', $url)) {

		$url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . ltrim($url, '/');

	}

	// What the notification is about, for an app that opens its own screen
	// rather than the panel's address.
	$subject = array('type' => null, 'id' => null);

	$action = (string)$row['action'];

	$pairs = api_notification_subjects();

	if (isset($pairs[$action]) && isset($row[$pairs[$action][1]]) && ((int)$row[$pairs[$action][1]] > 0)) {

		$subject = array('type' => $pairs[$action][0], 'id' => (int)$row[$pairs[$action][1]]);

	}

	return array(
		'id'          => (int)$row['id'],
		'action'      => ($display['action'] !== '') ? (string)$display['action'] : $action,
		'type'        => isset($row['type']) ? (string)$row['type'] : '',
		'title'       => $plain($display['title']),
		'body'        => $plain(pg_notification_body($display)),
		'url'         => $url,
		'subject'     => $subject,
		'read'        => (bool)$read,
		'created_at'  => api_time($row['timestamp'])
	);

}

// Which record each kind of notification is about: the type an app names it
// by, and the column of the notification row that holds its id.
function api_notification_subjects() {

	return array(
		'new_order'     => array('order', 'order_id'),
		'out_stock'     => array('product', 'product_id'),
		'form_submited' => array('form_submission', 'form_id'),
		'new_comment'   => array('comment', 'comment_id'),
		'erp_money'     => array('erp_receipt', 'form_id'),
		'erp_low_stock' => array('product', 'product_id'),
		'workspace'     => array('workspace_inbox', 'reference_id')
	);

}

function api_notification_schema() {

	return array(
		'id'         => 'integer',
		'action'     => 'string',
		'type'       => 'string',
		'title'      => 'string',
		'body'       => 'string',
		'url'        => 'string?',
		'subject'    => array('type' => 'string?', 'id' => 'integer?'),
		'read'       => 'boolean',
		'created_at' => 'string'
	);

}

// Newest first, cursor paged on (timestamp, id).
//
// The visibility ladder runs in PHP - a comment notification is visible to
// whoever may edit the page it was left on, which is not a column - so rows are
// read in batches and filtered until the page is full. The batch bound keeps a
// person who may see almost nothing from walking the whole table in one call:
// a short page with a cursor is a correct answer, and the next call goes on
// from there.
function api_notifications_list($params) {

	api_notifications_load();

	$app = api_current_app();

	$rights = api_notifications_rights($app['owner']);

	$user_id = $rights['id'];

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$unread_only = !empty($params['unread']);

	$reads = pg_notification_reads_available();

	$where = array();

	if (pg_notification_targets_available()) {

		$where[] = "notifications.target_user_id IN (0, '" . $user_id . "')";

	}

	if ($unread_only) {

		$where[] = $reads ? "notification_reads.notification_id IS NULL" : "notifications.readed = 0";

	}

	if (isset($params['since']) && $params['since'] !== null) {

		$where[] = "notifications.timestamp >= '" . (int)$params['since'] . "'";

	}

	$position = null;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$position = api_cursor_decode($params['cursor']);

		if ($position === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

	}

	$join = $reads
		? " LEFT JOIN notification_reads
			ON notification_reads.notification_id = notifications.id
			AND notification_reads.user_id = '" . $user_id . "'"
		: '';

	$read_column = $reads ? ", (notification_reads.notification_id IS NOT NULL) AS is_read" : ", notifications.readed AS is_read";

	$out = array();

	$more = false;

	$last = null;

	for ($batch = 0; $batch < 8; $batch++) {

		$batch_where = $where;

		if ($position !== null) {

			$batch_where[] = "((notifications.timestamp < '" . $position['v'] . "')
				OR (notifications.timestamp = '" . $position['v'] . "' AND notifications.id < '" . $position['i'] . "'))";

		}

		$size = max(50, $limit * 2);

		$rows = api_rows("SELECT notifications.*" . $read_column . "
			FROM notifications" . $join
			. (empty($batch_where) ? '' : " WHERE " . implode(' AND ', $batch_where)) . "
			ORDER BY notifications.timestamp DESC, notifications.id DESC
			LIMIT " . $size);

		foreach ($rows as $row) {

			$position = array('v' => (int)$row['timestamp'], 'i' => (int)$row['id']);

			if (!pg_notification_visible($row, $rights)) {

				continue;

			}

			if (count($out) >= $limit) {

				$more = true;

				break 2;

			}

			$out[] = api_notification_present($row, (int)$row['is_read'] === 1);

			$last = $position;

		}

		if (count($rows) < $size) {

			break;

		}

		// The batch bound was reached with rows still left to read: the page
		// ends here and the cursor carries on from the last row looked at, not
		// the last row shown, so the invisible ones are not read twice.
		if ($batch === 7) {

			$more = true;

			$last = $position;

		}

	}

	$next = ($more && ($last !== null)) ? api_cursor_encode($last['v'], $last['i']) : null;

	api_ok_list($out, $limit, $next);

}

function api_notifications_count($params) {

	$app = api_current_app();

	api_ok(array('unread' => api_notifications_unread_count($app['owner'])));

}

// The notifications a read or unread call may touch. Only rows the caller may
// see: an id that belongs to somebody else's bell is skipped, not an error, so
// a client holding a stale list is not refused for it.
function api_notifications_targets($params, $rights, $read) {

	$ids = array();

	if (!empty($params['all']) && $read) {

		foreach (pg_notification_unread_rows($rights['id']) as $row) {

			if (pg_notification_visible($row, $rights)) {

				$ids[] = (int)$row['id'];

			}

		}

	} else {

		$wanted = array();

		foreach ((isset($params['ids']) && is_array($params['ids'])) ? $params['ids'] : array() as $id) {

			if (is_numeric($id) && ((int)$id > 0)) {

				$wanted[(int)$id] = true;

			}

		}

		if (empty($wanted)) {

			api_fail_validation(lang('Send ids, or all set to true.'), 'ids');

		}

		$rows = api_rows("SELECT * FROM notifications
			WHERE id IN (" . implode(',', array_keys($wanted)) . ")");

		foreach ($rows as $row) {

			if (pg_notification_visible($row, $rights)) {

				$ids[] = (int)$row['id'];

			}

		}

	}

	return $ids;

}

function api_notifications_read($params) {

	api_notifications_load();

	$app = api_current_app();

	$rights = api_notifications_rights($app['owner']);

	$ids = api_notifications_targets($params, $rights, true);

	api_dry_run_stop('marked_read', 'notification', array('count' => count($ids)));

	pg_notification_mark_read($ids, $rights['id']);

	api_ok(array(
		'updated' => count($ids),
		'unread'  => api_notifications_unread_count($app['owner'])
	));

}

function api_notifications_unread($params) {

	api_notifications_load();

	$app = api_current_app();

	$rights = api_notifications_rights($app['owner']);

	$ids = api_notifications_targets($params, $rights, false);

	api_dry_run_stop('marked_unread', 'notification', array('count' => count($ids)));

	foreach ($ids as $id) {

		pg_notification_mark_unread($id, $rights['id']);

	}

	api_ok(array(
		'updated' => count($ids),
		'unread'  => api_notifications_unread_count($app['owner'])
	));

}

/* ---------------------------------------------------------------------------
   Push
   ---------------------------------------------------------------------------
   The site's own web push, the one the installed panel uses. A push carries no
   text: it wakes the device, and the device asks GET /notifications?unread=true
   what to show, over its own token. Nothing a notification says passes through
   the push service.
   --------------------------------------------------------------------------- */

function api_push_load() {

	require_once(PG_FUNCTIONS_DIR . '/includes/push.php');

}

function api_push_config($params) {

	api_push_load();

	$key = pg_push_vapid_public_key();

	$known = false;

	if (isset($params['endpoint']) && $params['endpoint'] !== '') {

		$known = pg_push_subscription_exists($params['endpoint']);

	}

	api_ok(array(
		'available'  => ($key !== ''),
		'public_key' => ($key !== '') ? $key : null,
		'known'      => $known
	));

}

function api_push_subscribe($params) {

	api_push_load();

	$app = api_current_app();

	// The two keys of the browser's subscription (PushSubscription.keys), sent
	// beside the endpoint rather than nested, so each is checked like any other
	// field.
	$p256dh = (string)$params['p256dh'];

	$auth = (string)$params['auth'];

	if (pg_push_vapid_public_key() === '') {

		api_fail(503, 'service_unavailable', lang('This server cannot send push notifications.'));

	}

	api_dry_run_stop('created', 'push_subscription', array());

	if (isset($params['old_endpoint']) && $params['old_endpoint'] !== '') {

		pg_push_subscription_delete($params['old_endpoint']);

	}

	$saved = pg_push_subscription_save((int)$app['owner']['id'], $params['endpoint'], $p256dh, $auth,
		mb_substr(api_header('User-Agent'), 0, 255));

	if (!$saved) {

		api_fail_validation(lang('The endpoint must be an https address of a push service.'), 'endpoint');

	}

	// A registration made from a device goes when the device is signed out.
	$device = api_current_device();

	if (($device !== null) && pg_push_has_selector()) {

		api_exec("UPDATE push_subscriptions SET auth_selector = 'api:" . (int)$device['id'] . "'
			WHERE endpoint_hash = '" . escape(hash('sha256', trim((string)$params['endpoint']))) . "'");

	}

	api_ok(array('subscribed' => true), 201);

}

function api_push_unsubscribe($params) {

	api_push_load();

	$app = api_current_app();

	// Only a registration of this person's is removed. The endpoint is the
	// browser's address and is not secret to whoever holds the browser, but it
	// is no business of another account's token.
	$exists = (int)api_value("SELECT COUNT(*) FROM push_subscriptions
		WHERE endpoint_hash = '" . escape(hash('sha256', trim((string)$params['endpoint']))) . "'
		AND user_id = '" . (int)$app['owner']['id'] . "'");

	api_dry_run_stop('deleted', 'push_subscription', array('found' => ($exists > 0)));

	if ($exists > 0) {

		pg_push_subscription_delete($params['endpoint']);

	}

	api_ok(array('subscribed' => false));

}

function api_push_test($params) {

	api_push_load();

	$app = api_current_app();

	// Wakes this person's own registrations and nobody else's; each then shows
	// whatever its bell holds, because the push carries no text.
	$sent = pg_push_notify_user((int)$app['owner']['id']);

	$delivered = 0;

	foreach ($sent as $result) {

		if (!empty($result['ok'])) {

			$delivered++;

		}

	}

	api_ok(array('devices' => count($sent), 'delivered' => $delivered));

}
