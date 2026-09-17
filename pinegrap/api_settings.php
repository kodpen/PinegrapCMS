<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Application access: who may reach this site from outside, and what they may see.
//
// The screen this replaces printed every application's API key into the page,
// decrypted, so anyone who could open it could read every integration's
// credentials out of the page source. It also had no edit: changing a permission
// meant deleting the application and making a new key, which breaks a live
// integration to change one checkbox.
//
// Here the key is a public identifier and the secret is shown once, at the moment
// it is made, and never again - the row afterwards can only show its last four
// characters. Everything else is editable in place, including a secret rotation
// that leaves the previous secret working for a day so a running sync can be
// moved across without a gap.

include('init.php');
include_once('liveform.class.php');

$user = validate_user();
validate_area_access($user, 'manager');

define('PG_API_PANEL', true);

require_once(dirname(__FILE__) . '/includes/api/keys.php');
require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/schema.php');

$liveform = new liveform('api_settings');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/api_settings.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	// liveform reads field values from the session, not from $_POST, so the
	// post has to be handed to it before anything is validated or read back.
	$liveform->add_fields_to_session();

	$action = isset($_POST['api_action']) ? $_POST['api_action'] : '';

	$app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

	// What the permission rows posted, turned back into scope strings.
	//
	// The form offers one control per resource with three positions, so the only
	// values that can arrive are the ones the row was drawn from. They are still
	// checked against the catalogue: a form post is text like any other, and the
	// screen this replaces stored whatever string it was handed.
	$posted_scopes = array();

	if (isset($_POST['scope']) && is_array($_POST['scope'])) {

		foreach (api_scope_groups() as $group_key => $group) {

			$choice = isset($_POST['scope'][$group_key]) ? $_POST['scope'][$group_key] : '';

			if ($choice === 'read' && $group['read'] !== '') {

				$posted_scopes[] = $group['read'];

			} elseif ($choice === 'write' && $group['write'] !== '') {

				$posted_scopes[] = $group['write'];

			}

		}

	}

	$posted_scopes = api_scopes_normalise($posted_scopes);

	if ($action === 'create') {

		$liveform->validate_required_field('name', lang('Application Name is a required field!'));

		if ($liveform->check_form_errors() == false) {

			$key    = api_generate_key();
			$secret = api_generate_secret();

			db("INSERT INTO api_apps
				(name, description, api_key, api_secret_hash, api_secret_hint, owner_user_id,
				 scopes, status, rate_limit_per_min, created_user_id, created_timestamp, updated_timestamp)
				VALUES (
					'" . escape($liveform->get_field_value('name')) . "',
					'" . escape(isset($_POST['description']) ? $_POST['description'] : '') . "',
					'" . escape($key) . "',
					'" . escape(api_secret_hash($secret)) . "',
					'" . escape(api_secret_hint($secret)) . "',
					'" . (int)$user['id'] . "',
					'" . escape(json_encode($posted_scopes)) . "',
					'active',
					'120',
					'" . (int)$user['id'] . "',
					UNIX_TIMESTAMP(),
					UNIX_TIMESTAMP()
				)");

			// The one and only time this pair is readable. It is handed to the
			// next request through the session rather than the query string,
			// because a query string is written to the server's access log.
			$_SESSION['software']['api_new_credentials'] = array(
				'name'   => $liveform->get_field_value('name'),
				'key'    => $key,
				'secret' => $secret
			);

			log_activity(lang(array(
				'string' => 'API application ({var:1}) was created.',
				'vars'   => $liveform->get_field_value('name')
			)), $_SESSION['sessionusername']);

			header('Location: ' . $screen_url);

			exit();

		}

	} elseif ($action === 'update' && $app_id > 0) {

		$existing = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		if ($existing) {

			$status = (isset($_POST['status']) && in_array($_POST['status'], array('active', 'disabled'), true))
				? $_POST['status']
				: 'disabled';

			$rate = isset($_POST['rate_limit_per_min']) ? (int)$_POST['rate_limit_per_min'] : 120;

			if ($rate < 1) { $rate = 1; }

			if ($rate > 6000) { $rate = 6000; }

			$expires = 0;

			if (isset($_POST['expires_at']) && trim($_POST['expires_at']) !== '') {

				$parsed = strtotime(trim($_POST['expires_at']) . ' 23:59:59');

				if ($parsed !== false) { $expires = (int)$parsed; }

			}

			db("UPDATE api_apps SET
					name = '" . escape(isset($_POST['name']) ? $_POST['name'] : $existing['name']) . "',
					description = '" . escape(isset($_POST['description']) ? $_POST['description'] : '') . "',
					scopes = '" . escape(json_encode($posted_scopes)) . "',
					ip_allowlist = '" . escape(isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : '') . "',
					status = '" . escape($status) . "',
					expires_at = '" . $expires . "',
					rate_limit_per_min = '" . $rate . "',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . $app_id . "'");

			$liveform->add_notice(lang('The application was saved.'));

			log_activity(lang(array(
				'string' => 'API application ({var:1}) was changed.',
				'vars'   => $existing['name']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'rotate' && $app_id > 0) {

		$existing = db_item("SELECT id, name, api_key, api_secret_hash FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		if ($existing) {

			$secret = api_generate_secret();

			// The outgoing secret keeps answering for a day. Issuing a new one
			// and expecting every integration to be updated in the same instant
			// is not how a live site works.
			db("UPDATE api_apps SET
					api_secret_prev_hash = '" . escape($existing['api_secret_hash']) . "',
					api_secret_prev_expires = UNIX_TIMESTAMP() + 86400,
					api_secret_hash = '" . escape(api_secret_hash($secret)) . "',
					api_secret_hint = '" . escape(api_secret_hint($secret)) . "',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . $app_id . "'");

			$_SESSION['software']['api_new_credentials'] = array(
				'name'    => $existing['name'],
				'key'     => $existing['api_key'],
				'secret'  => $secret,
				'rotated' => true
			);

			log_activity(lang(array(
				'string' => 'A new secret was issued for the API application ({var:1}).',
				'vars'   => $existing['name']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'webhook_status' && $app_id > 0) {

		// Switching a subscription off rather than deleting it: an endpoint that
		// has started answering nonsense should stop being called now, and the
		// integrator's registration should still be there when they fix it.
		// The dispatcher drops queued events for a disabled subscription.
		$webhook_id = isset($_POST['webhook_id']) ? (int)$_POST['webhook_id'] : 0;

		$hook = db_item("SELECT id, url, status FROM api_webhooks
			WHERE id = '" . $webhook_id . "' AND app_id = '" . $app_id . "' LIMIT 1");

		if ($hook) {

			$new_status = ($hook['status'] === 'disabled') ? 'active' : 'disabled';

			db("UPDATE api_webhooks SET status = '" . escape($new_status) . "'
				WHERE id = '" . (int)$hook['id'] . "'");

			$liveform->add_notice(($new_status === 'active')
				? lang(array('string' => 'Deliveries to {var:1} were switched back on.', 'vars' => $hook['url']))
				: lang(array('string' => 'Deliveries to {var:1} were stopped.', 'vars' => $hook['url'])));

			log_activity(lang(array(
				'string' => 'API event subscription ({var:1}) was set to {var:2}.',
				'vars'   => array($hook['url'], $new_status)
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'webhook_retry' && $app_id > 0) {

		// The operator has fixed their endpoint and wants what is waiting to go
		// now. Attempts go back to zero so the whole ladder is available again -
		// a row that has run out of attempts is invisible to the dispatcher, and
		// leaving the count where it was would let it try once and give up.
		$webhook_id = isset($_POST['webhook_id']) ? (int)$_POST['webhook_id'] : 0;

		$hook = db_item("SELECT id, url FROM api_webhooks
			WHERE id = '" . $webhook_id . "' AND app_id = '" . $app_id . "' LIMIT 1");

		if ($hook) {

			db("UPDATE api_webhooks SET status = 'active' WHERE id = '" . (int)$hook['id'] . "'");

			db("UPDATE api_webhook_queue
				SET attempts = '0', next_attempt_at = UNIX_TIMESTAMP(), last_error = ''
				WHERE webhook_id = '" . (int)$hook['id'] . "'");

			$liveform->add_notice(lang(array(
				'string' => 'Waiting events for {var:1} will be sent again on the next run of the scheduled task.',
				'vars'   => $hook['url']
			)));

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'webhook_delete' && $app_id > 0) {

		$webhook_id = isset($_POST['webhook_id']) ? (int)$_POST['webhook_id'] : 0;

		$hook = db_item("SELECT id, url FROM api_webhooks
			WHERE id = '" . $webhook_id . "' AND app_id = '" . $app_id . "' LIMIT 1");

		if ($hook) {

			db("DELETE FROM api_webhooks WHERE id = '" . (int)$hook['id'] . "'");

			db("DELETE FROM api_webhook_queue WHERE webhook_id = '" . (int)$hook['id'] . "'");

			$liveform->add_notice(lang(array('string' => 'The subscription to {var:1} was removed.', 'vars' => $hook['url'])));

			log_activity(lang(array(
				'string' => 'API event subscription ({var:1}) was removed.',
				'vars'   => $hook['url']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'delete' && $app_id > 0) {

		$existing = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		if ($existing) {

			db("DELETE FROM api_apps WHERE id = '" . $app_id . "'");

			db("DELETE FROM api_webhooks WHERE app_id = '" . $app_id . "'");

			$liveform->add_notice(lang(array('string' => 'The application {var:1} was deleted.', 'vars' => $existing['name'])));

			log_activity(lang(array(
				'string' => 'API application ({var:1}) was deleted.',
				'vars'   => $existing['name']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	}

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

// The credentials handed over by the write above, if this request follows one.
// Read once and removed: the point of the sheet is that it appears exactly once.
$new_credentials = null;

if (isset($_SESSION['software']['api_new_credentials'])) {

	$new_credentials = $_SESSION['software']['api_new_credentials'];

	unset($_SESSION['software']['api_new_credentials']);

}

// The documentation screen's temporary credentials live in this table too, so
// they are kept out of the list: they are not integrations, they expire on their
// own, and an operator seeing one would reasonably wonder what it is.
$apps = db_items("SELECT api_apps.*, user.user_username AS owner_username
	FROM api_apps
	LEFT JOIN user ON api_apps.owner_user_id = user.user_id
	WHERE api_apps.name NOT LIKE '\\_\\_test\\_\\_%'
	ORDER BY api_apps.name ASC");

if (!is_array($apps)) { $apps = array(); }

// Twenty-four hours of traffic per application, in one grouped query rather than
// one query per row: the list is drawn on every visit and a per-row count turns
// a page into as many queries as there are integrations.
$traffic = array();

$since = time() - 86400;

$rows = db_items("SELECT app_id,
		COUNT(*) AS requests,
		SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS failures
	FROM api_request_log
	WHERE timestamp >= '" . $since . "'
	GROUP BY app_id");

if (is_array($rows)) {

	foreach ($rows as $row) {

		$traffic[(int)$row['app_id']] = array(
			'requests' => (int)$row['requests'],
			'failures' => (int)$row['failures']
		);

	}

}

// The most recent calls, for the drawer's log tab. One query for the whole
// screen, grouped in PHP afterwards: a query per application would turn this
// page into as many round trips as there are integrations, and the tab is only
// ever looked at for one of them at a time.
$log_rows = array();

$recent = db_items("SELECT app_id, method, path, status_code, error_code, duration_ms, timestamp
	FROM api_request_log
	WHERE app_id > 0
	ORDER BY id DESC
	LIMIT 300");

if (is_array($recent)) {

	foreach ($recent as $row) {

		$key = (int)$row['app_id'];

		if (!isset($log_rows[$key])) { $log_rows[$key] = array(); }

		if (count($log_rows[$key]) >= 15) { continue; }

		$log_rows[$key][] = array(
			'method'   => $row['method'],
			'path'     => $row['path'],
			'status'   => (int)$row['status_code'],
			'error'    => $row['error_code'],
			'duration' => (int)$row['duration_ms'],
			'time'     => date('H:i', (int)$row['timestamp'])
		);

	}

}

/* ---------------------------------------------------------------------------
   Presentation helpers
   --------------------------------------------------------------------------- */

// Short Turkish-friendly labels for the resource rows, and the sentence under
// each one saying what it actually reaches.
function api_settings_group_text($key) {

	$text = array(
		'products'  => array(lang('Products'),   lang('Reading the catalogue; writing name, price and description'), 'bi-box-seam'),
		'inventory' => array(lang('Inventory'),  lang('Setting a quantity and adjusting it - reading is under Products'), 'bi-grid-3x3-gap'),
		'orders'    => array(lang('Orders'),     lang('Reading orders and their lines; writing status and notes'), 'bi-cart3'),
		'customers' => array(lang('Customers'),  lang('The people who buy (address book records)'), 'bi-person-lines-fill'),
		'pages'     => array(lang('Pages'),      lang('Page title and meta fields'), 'bi-file-earmark'),
		'offers'    => array(lang('Offers'),     lang('Offer records'), 'bi-file-earmark-text'),
		'files'     => array(lang('Files'),      lang('Reading the file list; uploading new files to one folder'), 'bi-folder'),
		'webhooks'  => array(lang('Webhooks'),   lang('Registering an address for event notifications'), 'bi-megaphone'),
		'meta'      => array(lang('Site info'),  lang('Currency, tax and order statuses - read only'), 'bi-info-circle')
	);

	return isset($text[$key]) ? $text[$key] : array(ucfirst($key), '', 'bi-key');

}

// Which of the three positions a stored scope set puts a resource in.
function api_settings_group_choice($granted, $group) {

	if ($group['write'] !== '' && in_array($group['write'], $granted, true)) {

		return 'write';

	}

	if ($group['read'] !== '' && in_array($group['read'], $granted, true)) {

		return 'read';

	}

	return '';

}

function api_settings_ago($timestamp) {

	$timestamp = (int)$timestamp;

	if ($timestamp <= 0) {

		return lang('Never');

	}

	$seconds = time() - $timestamp;

	if ($seconds < 60) { return lang('Just now'); }

	if ($seconds < 3600) { return lang(array('string' => '{var:1} minutes ago', 'vars' => (int)($seconds / 60))); }

	if ($seconds < 86400) { return lang(array('string' => '{var:1} hours ago', 'vars' => (int)($seconds / 3600))); }

	return lang(array('string' => '{var:1} days ago', 'vars' => (int)($seconds / 86400)));

}

// Event subscriptions, and how their queues are doing. Applications register
// these themselves through the API - an integrator whose endpoint moves should
// not have to ask an operator to retype it - so this screen is where an operator
// SEES them and can stop one, not where they are normally made.
//
// Two queries for the whole page rather than two per application, the same
// reason the request log is read in one pass.
$webhook_rows = array();

$hooks = db_items("SELECT id, app_id, url, events, status, last_success, last_failure
	FROM api_webhooks
	ORDER BY app_id ASC, id ASC");

$queue_state = array();

if (is_array($hooks) && !empty($hooks)) {

	// waiting: still on the ladder. given_up: out of attempts, and the reason the
	// subscription went to 'failing'. The last error carried on the newest row is
	// what actually tells the operator what is wrong.
	$queue = db_items("SELECT webhook_id,
			SUM(CASE WHEN next_attempt_at > 0 THEN 1 ELSE 0 END) AS waiting,
			SUM(CASE WHEN next_attempt_at = 0 THEN 1 ELSE 0 END) AS given_up,
			MAX(id) AS newest
		FROM api_webhook_queue
		GROUP BY webhook_id");

	if (is_array($queue)) {

		$newest_ids = array();

		foreach ($queue as $row) {

			$queue_state[(int)$row['webhook_id']] = array(
				'waiting'  => (int)$row['waiting'],
				'given_up' => (int)$row['given_up'],
				'error'    => ''
			);

			$newest_ids[] = (int)$row['newest'];

		}

		if (!empty($newest_ids)) {

			$errors = db_items("SELECT webhook_id, last_error, last_status_code
				FROM api_webhook_queue WHERE id IN (" . implode(',', $newest_ids) . ")");

			if (is_array($errors)) {

				foreach ($errors as $row) {

					$key = (int)$row['webhook_id'];

					if (!isset($queue_state[$key])) { continue; }

					$queue_state[$key]['error'] = ($row['last_error'] !== '')
						? $row['last_error']
						: (((int)$row['last_status_code'] > 0) ? 'HTTP ' . (int)$row['last_status_code'] : '');

				}

			}

		}

	}

	foreach ($hooks as $hook) {

		$key = (int)$hook['app_id'];

		if (!isset($webhook_rows[$key])) { $webhook_rows[$key] = array(); }

		$events = json_decode((string)$hook['events'], true);

		if (!is_array($events)) { $events = array(); }

		$state = isset($queue_state[(int)$hook['id']])
			? $queue_state[(int)$hook['id']]
			: array('waiting' => 0, 'given_up' => 0, 'error' => '');

		$webhook_rows[$key][] = array(
			'id'           => (int)$hook['id'],
			'url'          => $hook['url'],
			'events'       => $events,
			'status'       => $hook['status'],
			'last_success' => ((int)$hook['last_success'] > 0) ? api_settings_ago($hook['last_success']) : '',
			'last_failure' => ((int)$hook['last_failure'] > 0) ? api_settings_ago($hook['last_failure']) : '',
			'waiting'      => $state['waiting'],
			'given_up'     => $state['given_up'],
			'error'        => $state['error']
		);

	}

}

/* ---------------------------------------------------------------------------
   The rows
   --------------------------------------------------------------------------- */

$app_rows = '';

$app_data = array();

foreach ($apps as $app) {

	$granted = json_decode((string)$app['scopes'], true);

	if (!is_array($granted)) { $granted = array(); }

	$chips = '';

	foreach (api_scope_groups() as $group_key => $group) {

		$choice = api_settings_group_choice($granted, $group);

		if ($choice === '') { continue; }

		$label = api_settings_group_text($group_key);

		$chips .= '<span class="api-chip' . ($choice === 'write' ? ' w' : '') . '">' . h($label[0]) . '</span>';

	}

	if ($chips === '') {

		$chips = '<span class="api-chip muted">' . lang('No permission') . '</span>';

	}

	$status_class = ($app['status'] === 'active') ? 'on' : 'off';

	$status_label = ($app['status'] === 'active') ? lang('Active') : lang('Disabled');

	if ((int)$app['expires_at'] > 0 && (int)$app['expires_at'] < time()) {

		$status_class = 'warn';

		$status_label = lang('Expired');

	}

	$stats = isset($traffic[(int)$app['id']]) ? $traffic[(int)$app['id']] : array('requests' => 0, 'failures' => 0);

	$app_rows .= '
	<div class="api-row" data-app="' . (int)$app['id'] . '" role="button" tabindex="0">
		<div class="api-name">
			<i class="bi bi-key"></i>
			<div>
				<b>' . h($app['name']) . '</b>
				<code>' . h(substr($app['api_key'], 0, 16)) . '&hellip;</code>
			</div>
		</div>
		<div class="api-chips">' . $chips . '</div>
		<div><span class="api-status ' . $status_class . '">' . h($status_label) . '</span></div>
		<div class="api-meta">' . h(api_settings_ago($app['last_used_timestamp'])) . '
			<small>' . ($app['last_used_ip'] !== '' ? h($app['last_used_ip']) : '&mdash;') . '</small></div>
		<div class="api-meta">' . ($stats['requests'] > 0 ? number_format($stats['requests'], 0, ',', '.') . ' ' . lang('requests') : '&mdash;') . '
			<small' . ($stats['failures'] > 0 ? ' class="bad"' : '') . '>'
			. ($stats['failures'] > 0 ? number_format($stats['failures'], 0, ',', '.') . ' ' . lang('errors') : '&mdash;') . '</small></div>
		<div class="text-end"><i class="bi bi-chevron-right"></i></div>
	</div>';

	// Everything the drawer needs, and nothing more. No secret is in here: the
	// stored value is a hash and could not be shown even if this wanted to.
	$scope_choices = array();

	foreach (api_scope_groups() as $group_key => $group) {

		$scope_choices[$group_key] = api_settings_group_choice($granted, $group);

	}

	$app_data[(int)$app['id']] = array(
		'id'          => (int)$app['id'],
		'name'        => $app['name'],
		'description' => $app['description'],
		'key'         => $app['api_key'],
		'hint'        => $app['api_secret_hint'],
		'owner'       => $app['owner_username'],
		'created'     => date('d.m.Y', (int)$app['created_timestamp']),
		'status'      => $app['status'],
		'ip'          => $app['ip_allowlist'],
		'expires'     => ((int)$app['expires_at'] > 0) ? date('Y-m-d', (int)$app['expires_at']) : '',
		'rate'        => (int)$app['rate_limit_per_min'],
		'scopes'      => $scope_choices,
		'has_secret'  => ($app['api_secret_hash'] !== ''),
		'log'         => isset($log_rows[(int)$app['id']]) ? $log_rows[(int)$app['id']] : array(),
		'webhooks'    => isset($webhook_rows[(int)$app['id']]) ? $webhook_rows[(int)$app['id']] : array()
	);

}

if ($app_rows === '') {

	$app_rows = '<div class="api-empty">' . lang('No application yet. Create one to let an outside system reach this site.') . '</div>';

}

/* ---------------------------------------------------------------------------
   The permission rows, drawn once and reused by the drawer and the new-app form
   --------------------------------------------------------------------------- */

function api_settings_permission_rows($prefix, $id_prefix = '') {

	// The rows are drawn in two places on one page. The posted name has to be
	// the same in both, the element ids must not be: a duplicate id makes a
	// label drive the first copy of the control instead of its own.
	if ($id_prefix === '') { $id_prefix = $prefix; }

	$html = '';

	foreach (api_scope_groups() as $group_key => $group) {

		$text = api_settings_group_text($group_key);

		$name = $prefix . '[' . $group_key . ']';

		$id = $id_prefix . '_' . $group_key;

		// A resource with only one half offers a dash in the other position, so
		// every row keeps the same three-column shape and the missing half reads
		// as "not offered" rather than as an option that failed to draw.
		$read = ($group['read'] !== '')
			? '<input type="radio" class="btn-check" name="' . $name . '" id="' . $id . '_read" value="read">
			   <label class="btn" for="' . $id . '_read">' . lang('Read') . '</label>'
			: '<span class="api-seg-none">&mdash;</span>';

		$write = ($group['write'] !== '')
			? '<input type="radio" class="btn-check" name="' . $name . '" id="' . $id . '_write" value="write">
			   <label class="btn" for="' . $id . '_write">' . ($group_key === 'webhooks' ? lang('Manage') : lang('Write')) . '</label>'
			: '<span class="api-seg-none">&mdash;</span>';

		$html .= '
		<div class="api-perm" data-group="' . h($group_key) . '">
			<i class="bi ' . $text[2] . '"></i>
			<div class="api-perm-text"><b>' . h($text[0]) . '</b><span>' . h($text[1]) . '</span></div>
			<div class="api-seg btn-group btn-group-sm" role="group">
				<input type="radio" class="btn-check" name="' . $name . '" id="' . $id . '_none" value="" checked>
				<label class="btn" for="' . $id . '_none">' . lang('None') . '</label>
				' . $read . '
				' . $write . '
			</div>
		</div>';

	}

	return $html;

}


$api_base_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/integration.php';

$permission_rows = api_settings_permission_rows('scope', 'd_scope');

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

// Read once, then emptied. Nothing in liveform clears a warning or a notice on
// its own, so one shown here would be shown on every later visit to the screen.
// Field values are not needed past this point: every write ends in a redirect.
$liveform_messages = $liveform->output_errors() . $liveform->output_notices() . $liveform->get_warnings();

$liveform->remove_form();

echo pg_page_shell(array(
	'title'                => lang('Application Access'),
	'extra classes'        => 'api-access',
	'icon'                 => 'settings',
	'heading'              => lang('Application Access'),
	'heading_description'  => lang('Give an outside system a key, choose what it may see, and watch what it asks for.'),
)) . '

<style>
/* The list reads as rows rather than a table: a table cell cannot hold the
   two-line name-and-key pair without the row heights fighting each other. */
/* The list scrolls sideways rather than squeezing.
   Six columns of real content do not fit a phone, and letting them try is what
   put the permission chips on top of the application name. A minimum width
   keeps the proportions the columns were designed with and hands the operator a
   scrollbar instead - which is also what a spreadsheet does with the same
   problem. Stacking each row into a block was the other option and was rejected
   here: five applications would become thirty lines, and the whole point of
   this screen is scanning them side by side. */
.api-scroll { overflow-x: auto; overflow-y: hidden; }
.api-head, .api-row { display: grid; grid-template-columns: 1.9fr 1.5fr .85fr 1.15fr .9fr 34px;
	gap: 14px; align-items: center; min-width: 900px; }
.api-head { padding: 0 16px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; opacity: .55; }
.api-row { padding: 13px 16px; border-top: 1px solid var(--bs-border-color); cursor: pointer; }
.api-row:hover, .api-row:focus-visible { background: var(--bs-tertiary-bg); outline: 0; }
.api-name { display: flex; align-items: center; gap: 9px; min-width: 0; }
.api-name i { opacity: .7; }
.api-name b { font-size: 13px; display: block; }
.api-name code { font-size: 10.5px; opacity: .55; }
.api-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.api-chip { font-size: 10px; padding: 2px 6px; border-radius: 5px; border: 1px solid var(--bs-border-color); opacity: .85; }
.api-chip.w { border-color: var(--bs-primary); color: var(--bs-primary); }
.api-chip.muted { opacity: .45; }
.api-status { font-size: 10.5px; padding: 3px 9px; border-radius: 20px; font-weight: 600;
	border: 1px solid var(--bs-border-color); display: inline-block; }
.api-status.on { color: var(--bs-success); border-color: rgba(var(--bs-success-rgb), .35); }
.api-status.off { opacity: .5; }
.api-status.warn { color: var(--bs-warning); border-color: rgba(var(--bs-warning-rgb), .35); }
.api-meta { font-size: 11.5px; opacity: .8; }
.api-meta small { display: block; font-size: 10.5px; opacity: .7; }
.api-meta small.bad { color: var(--bs-danger); opacity: 1; }
.api-empty { padding: 26px 16px; text-align: center; opacity: .6; border-top: 1px solid var(--bs-border-color); }

/* One permission per resource, three positions. The row carries the sentence
   that says what it reaches, because "Orders: write" on its own does not. */
.api-perm { display: flex; align-items: center; gap: 12px; padding: 13px 2px;
	border-top: 1px solid var(--bs-border-color); }
.api-perm:first-of-type { border-top: 0; }
.api-perm > i { font-size: 15px; width: 18px; text-align: center; opacity: .7; }
.api-perm-text { flex: 1; min-width: 0; }
.api-perm-text b { display: block; font-size: 12.5px; }
.api-perm-text span { display: block; font-size: 10.5px; opacity: .6; margin-top: 1px; }
/* Below this the sentence under the resource name and the three-way control
   cannot share a line without one of them wrapping into the other. The control
   drops beneath the text and keeps its full width, which is the one part of
   this row that must stay tappable. */
@media (max-width: 520px) {
	.api-perm { flex-wrap: wrap; }
	.api-perm-text { flex: 1 1 100%; }
	.api-seg { margin-left: 30px; }
}
.api-seg { flex: none; }
.api-seg .btn { --bs-btn-font-size: 11px; --bs-btn-padding-y: .25rem; --bs-btn-padding-x: .6rem;
	--bs-btn-color: var(--bs-secondary-color); --bs-btn-border-color: var(--bs-border-color);
	--bs-btn-hover-border-color: var(--bs-border-color); }
.api-seg .btn-check:checked + .btn { color: #fff; background: var(--bs-primary); border-color: var(--bs-primary); }
/* Write implies read, so the read position shows as held-open rather than
   inviting a click that would silently do nothing. */
.api-seg .btn.implied { color: #fff; background: var(--bs-primary); border-color: var(--bs-primary); opacity: .45; }
.api-seg-none { display: inline-flex; align-items: center; justify-content: center; min-width: 34px;
	font-size: 11px; opacity: .3; border: 1px solid var(--bs-border-color); border-left: 0; }
.api-log { display: grid; grid-template-columns: 46px 1fr auto auto auto; gap: 9px; align-items: center;
	padding: 8px 2px; border-top: 1px solid var(--bs-border-color); font-size: 11.5px; }
.api-log:first-child { border-top: 0; }
.api-log .m { font-size: 9.5px; font-weight: 700; text-align: center; border-radius: 4px; padding: 2px 0;
	background: var(--bs-tertiary-bg); }
.api-log code { font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.api-log .s { font-size: 10.5px; font-weight: 600; }
.api-log .s.ok { color: var(--bs-success); }
.api-log .s.bad { color: var(--bs-danger); }
.api-log .d, .api-log .t { font-size: 10.5px; opacity: .55; }
.api-hook { border-top: 1px solid var(--bs-border-color); padding: 11px 2px; }
.api-hook:first-child { border-top: 0; }
.api-hook-top { display: flex; align-items: center; gap: 8px; }
.api-hook-url { flex: 1; min-width: 0; font-size: 12px; word-break: break-all; }
.api-hook-events { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.api-hook-meta { font-size: 10.5px; opacity: .65; margin-top: 6px; }
.api-hook-error { font-size: 10.5px; color: var(--bs-danger); margin-top: 5px; word-break: break-word; }
.api-hook-acts { display: flex; gap: 5px; margin-top: 8px; }
.api-secret-box { display: flex; align-items: center; gap: 8px; background: var(--bs-tertiary-bg);
	border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 9px 11px; }
.api-secret-box code { flex: 1; font-size: 12px; word-break: break-all; }
.api-danger-zone { border-top: 1px solid var(--bs-border-color); margin-top: 18px; padding-top: 14px; }
/* The site chat launcher is fixed at z-index 1080, an offcanvas is 1045, so the
   bubble sits on top of whatever the drawer puts in its bottom right corner -
   which here is Save. Hidden while the drawer is open rather than moved: the
   drawer is a modal context, and a chat bubble floating over the button that
   commits the form is not something to nudge sideways. */
body.api-drawer-open .pg-chat-launcher { display: none !important; }
</style>

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
	</div></div>

	<div class="d-flex align-items-center gap-2 mb-3">
		<div class="input-group input-group-sm" style="max-width:280px">
			<span class="input-group-text"><i class="bi bi-search"></i></span>
			<input type="text" class="form-control" id="api_filter" placeholder="' . lang('Search application') . '" autocomplete="off">
		</div>
		<div class="flex-grow-1"></div>
		<a class="btn btn-sm btn-outline-secondary" href="api_docs.php">
			<i class="bi bi-file-earmark-text me-1"></i>' . lang('API Documentation') . '</a>
		<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#api_new">
			<i class="bi bi-key me-1"></i>' . lang('New Application') . '</button>
	</div>

	<div class="card">
		<div class="card-header d-flex align-items-center">
			<i class="bi bi-key me-2"></i><b>' . lang('Applications') . '</b>
			<span class="ms-auto small opacity-75 d-none d-lg-inline">'
				. lang('Every application has its own key and secret. The secret is shown only when it is made.') . '</span>
		</div>
		<div class="card-body px-2 py-3">
			<div class="api-scroll">
			<div class="api-head">
				<span>' . lang('Application') . '</span><span>' . lang('Permissions') . '</span>
				<span>' . lang('Status') . '</span><span>' . lang('Last used') . '</span>
				<span>' . lang('24 hours') . '</span><span></span>
			</div>
			<div id="api_rows">' . $app_rows . '</div>
			</div>
		</div>
	</div>

	<p class="small opacity-50 mt-3">
		<i class="bi bi-info-circle me-1"></i>'
		. lang('The key is not shown in the list, only its prefix. The secret cannot be read back anywhere.') . '
	</p>
</main>

<!-- The drawer. One of them, filled from the row that was clicked: a drawer per
     application would put every application\'s settings into the page at once. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="api_drawer" style="width:min(520px,100%)">
	<form method="post" action="api_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100">
		' . get_token_field() . '
		<input type="hidden" name="api_action" value="update" id="d_action">
		<input type="hidden" name="webhook_id" value="" id="d_webhook_id">
		<input type="hidden" name="app_id" id="d_app_id" value="">

		<div class="offcanvas-header pb-1 d-block">
			<div class="d-flex align-items-start">
				<h5 class="offcanvas-title mb-0" id="d_title"></h5>
				<button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas"></button>
			</div>
			<p class="small opacity-75 mb-0 mt-1" id="d_sub"></p>
		</div>

		<ul class="nav nav-tabs px-3" role="tablist">
			<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#d_general" type="button">' . lang('General') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_perms" type="button">' . lang('Permissions') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_security" type="button">' . lang('Security') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_hooks" type="button">' . lang('Events') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_log" type="button">' . lang('Log') . '</button></li>
		</ul>

		<div class="offcanvas-body tab-content flex-grow-1 overflow-auto">

			<div class="tab-pane fade show active" id="d_general">
				<div class="mb-3">
					<label class="form-label small">' . lang('Name') . '</label>
					<input type="text" class="form-control form-control-sm" name="name" id="d_name" maxlength="190">
				</div>
				<div class="mb-3">
					<label class="form-label small">' . lang('Description') . '</label>
					<input type="text" class="form-control form-control-sm" name="description" id="d_description" maxlength="500">
				</div>
				<div class="mb-3">
					<label class="form-label small">' . lang('API Key') . '</label>
					<div class="api-secret-box"><code id="d_key"></code>
						<button type="button" class="btn btn-sm btn-outline-secondary api-copy" data-copy="#d_key">' . lang('Copy') . '</button></div>
					<div class="form-text">' . lang('Public identifier. Safe to share with the integrator.') . '</div>
				</div>
				<div class="mb-3">
					<label class="form-label small">' . lang('Secret') . '</label>
					<div class="api-secret-box"><code id="d_hint" class="opacity-50"></code>
						<button type="submit" class="btn btn-sm btn-outline-warning" name="api_action" value="rotate">
							<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Issue a new secret') . '</button></div>
					<div class="form-text">'
						. lang('Only the last four characters are kept for display. A new secret is shown once; the previous one keeps working for 24 hours.') . '</div>
				</div>
				<div class="form-check form-switch">
					<input class="form-check-input" type="checkbox" name="status" value="active" id="d_status">
					<label class="form-check-label small" for="d_status">' . lang('Active') . '</label>
				</div>
			</div>

			<div class="tab-pane fade" id="d_perms">
				' . $permission_rows . '
				<div class="alert alert-primary py-2 px-3 small mt-3 mb-2">
					<i class="bi bi-info-circle me-1"></i>
					<b>' . lang('Write covers read.') . '</b> '
					. lang('Choosing Write holds the read position open; the record keeps only what you chose.') . '
				</div>
				<div class="alert alert-secondary py-2 px-3 small mb-0">
					<i class="bi bi-shield-check me-1"></i>'
					. lang('An application can never do more than the account that owns it. Narrowing the owner narrows the application immediately.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_security">
				<div class="mb-3">
					<label class="form-label small">' . lang('Allowed addresses') . '</label>
					<input type="text" class="form-control tagin min-height-tagin" name="ip_allowlist" id="d_ip"
						data-placeholder="' . lang('Add an address') . '" value="">
					<div class="form-text">'
						. lang('Leave empty to let this application connect from anywhere. Once an address is added, requests from anywhere else are refused.')
						. '<br>' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . '</div>
				</div>
				<div class="row g-3">
					<div class="col-6">
						<label class="form-label small">' . lang('Expires on') . '</label>
						<input type="date" class="form-control form-control-sm" name="expires_at" id="d_expires">
						<div class="form-text">' . lang('After this date the key stops working. Leave empty for a key that does not expire.') . '</div>
					</div>
					<div class="col-6">
						<label class="form-label small">' . lang('Requests per minute') . '</label>
						<input type="number" class="form-control form-control-sm" name="rate_limit_per_min" id="d_rate" min="1" max="6000">
						<div class="form-text">' . lang('When an application asks for more than this in one minute, the rest of that minute is refused and it is told how long to wait.') . '</div>
					</div>
				</div>
				<div class="api-danger-zone">
					<button type="button" class="btn btn-sm btn-outline-danger" id="d_delete_ask">
						<i class="bi bi-trash me-1"></i>' . lang('Delete this application') . '</button>
					<div id="d_delete_confirm" class="d-none">
						<p class="small mb-2">' . lang('Any integration using this key stops working immediately. This cannot be undone.') . '</p>
						<button type="submit" class="btn btn-sm btn-danger" name="api_action" value="delete">' . lang('Yes, delete') . '</button>
						<button type="button" class="btn btn-sm btn-outline-secondary" id="d_delete_no">' . lang('Cancel') . '</button>
					</div>
				</div>
			</div>

			<div class="tab-pane fade" id="d_hooks">
				<div class="d-flex align-items-center mb-2">
					<span class="small opacity-75">' . lang('Addresses this application asked to be told at') . '</span>
				</div>
				<div id="d_hook_rows"></div>
				<div class="alert alert-primary py-2 px-3 small mt-3 mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('The application registers these itself through the API, so it can move its own address without asking you. Here you can stop one, send what is waiting again, or remove it.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_log">
				<div class="d-flex align-items-center mb-2">
					<span class="small opacity-75">' . lang('Most recent requests') . '</span>
					<div class="form-check form-switch ms-auto">
						<input class="form-check-input" type="checkbox" id="d_log_errors">
						<label class="form-check-label small" for="d_log_errors">' . lang('Errors only') . '</label>
					</div>
				</div>
				<div id="d_log_rows"></div>
				<div class="alert alert-primary py-2 px-3 small mt-3 mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('403 means a permission the application does not hold was tried; 409 means the same operation was sent twice and was not applied again.') . '
				</div>
			</div>

		</div>

		<div class="offcanvas-footer border-top p-3 d-flex gap-2">
			<span class="flex-grow-1"></span>
			<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">' . lang('Cancel') . '</button>
			<button type="submit" class="btn btn-sm btn-success">' . lang('Save') . '</button>
		</div>
	</form>
</div>

<!-- New application -->
<div class="modal fade" id="api_new" tabindex="-1">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<form method="post" action="api_settings.php" autocomplete="off" class="modal-content disable_shortcut">
			' . get_token_field() . '
			<input type="hidden" name="api_action" value="create">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-key me-2"></i>' . lang('New Application') . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label class="form-label small">' . lang('Name') . ' <span class="text-danger">*</span></label>
					<input type="text" class="form-control form-control-sm" name="name" maxlength="190" required
						placeholder="' . lang('For example: marketplace sync') . '">
				</div>
				<div class="mb-4">
					<label class="form-label small">' . lang('Description') . '</label>
					<input type="text" class="form-control form-control-sm" name="description" maxlength="500">
				</div>
				<label class="form-label small">' . lang('Permissions') . '</label>
				' . api_settings_permission_rows('scope', 'n_scope') . '
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
				<button type="submit" class="btn btn-sm btn-primary">' . lang('Create and show the secret') . '</button>
			</div>
		</form>
	</div>
</div>';

/* The one-time reveal. Rendered only on the request that follows the write that
   made the secret, and the session entry it came from is already gone. */
if ($new_credentials !== null) {

	echo '
<div class="modal fade show" id="api_secret_sheet" tabindex="-1" style="display:block; background:rgba(0,0,0,.6)">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header border-0 pb-0">
				<h5 class="modal-title"><i class="bi bi-key me-2 text-warning"></i>'
					. ((isset($new_credentials['rotated']) && $new_credentials['rotated'])
						? lang('A new secret was issued')
						: lang('The application was created')) . '</h5>
			</div>
			<div class="modal-body">
				<p class="mb-3"><b>' . lang('Once this window is closed the secret is never shown again.') . '</b><br>
					<span class="small opacity-75">'
					. lang('Only a verification hash is stored. If it is lost, issue a new one.') . '</span></p>

				<label class="form-label small text-uppercase opacity-75">' . lang('Key') . '</label>
				<div class="api-secret-box mb-3">
					<code id="nc_key">' . h($new_credentials['key']) . '</code>
					<button type="button" class="btn btn-sm btn-outline-primary api-copy" data-copy="#nc_key">' . lang('Copy') . '</button>
				</div>

				<label class="form-label small text-uppercase opacity-75">' . lang('Secret') . '</label>
				<div class="api-secret-box mb-3">
					<code id="nc_secret">' . h($new_credentials['secret']) . '</code>
					<button type="button" class="btn btn-sm btn-outline-primary api-copy" data-copy="#nc_secret">' . lang('Copy') . '</button>
				</div>

				<div class="alert alert-warning py-2 px-3 small mb-0">
					<i class="bi bi-lock me-1"></i>'
					. lang('Keep the secret like a password. Do not put it in a URL, an email or a shared file - server logs record those too.') . '
					<br>' . lang('Connecting:') . ' <code>Authorization: Basic base64(' . lang('key') . ':' . lang('secret') . ')</code>
				</div>
			</div>
			<div class="modal-footer">
				<div class="form-check me-auto">
					<input class="form-check-input" type="checkbox" id="nc_ack">
					<label class="form-check-label small" for="nc_ack">' . lang('I have copied it and stored it somewhere safe') . '</label>
				</div>
				<button type="button" class="btn btn-sm btn-success disabled" id="nc_close">' . lang('Close') . '</button>
			</div>
		</div>
	</div>
</div>';

}

echo '
<script>
(function () {

	var APPS = ' . json_encode($app_data, JSON_UNESCAPED_UNICODE) . ';

	var drawerElement = document.getElementById("api_drawer");

	var drawer = new bootstrap.Offcanvas(drawerElement);

	function setRadio(name, value) {
		var chosen = document.querySelector("#api_drawer [name=\'" + name + "\'][value=\'" + value + "\']");
		if (chosen) { chosen.checked = true; }
	}

	// The read position is held open, not checked, when write is chosen: the
	// rule lives in one place on the server and this only shows it.
	function paintImplied() {
		document.querySelectorAll(".api-perm").forEach(function (row) {
			var write = row.querySelector("input[value=\'write\']");
			var readLabel = row.querySelector("input[value=\'read\']")
				? row.querySelector("input[value=\'read\']").nextElementSibling : null;
			if (!readLabel) { return; }
			readLabel.classList.toggle("implied", !!(write && write.checked));
		});
	}

	function renderLog(rows, errorsOnly) {
		var box = document.getElementById("d_log_rows");
		var shown = rows.filter(function (r) { return !errorsOnly || r.status >= 400; });
		if (!shown.length) {
			box.innerHTML = "<p class=\'small opacity-50 py-3 mb-0\'>" +
				' . json_encode(lang('No request recorded yet.'), JSON_UNESCAPED_UNICODE) . ' + "</p>";
			return;
		}
		box.innerHTML = shown.map(function (r) {
			var ok = r.status < 400;
			return "<div class=\'api-log\'>" +
				"<span class=\'m\'>" + r.method + "</span>" +
				"<code>" + r.path.replace(/&/g, "&amp;").replace(/</g, "&lt;") + "</code>" +
				"<span class=\'s " + (ok ? "ok" : "bad") + "\'>" + r.status + "</span>" +
				"<span class=\'d\'>" + r.duration + " ms</span>" +
				"<span class=\'t\'>" + r.time + "</span></div>";
		}).join("");
	}

	function esc(value) {
		return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#39;");
	}

	// One subscription per block rather than a table: the address is long, the
	// event list is variable, and the failure text is the point when there is
	// one - a grid would truncate exactly the part that matters.
	function renderHooks(hooks) {
		var box = document.getElementById("d_hook_rows");
		if (!hooks.length) {
			box.innerHTML = "<p class=\'small opacity-50 py-3 mb-0\'>" +
				' . json_encode(lang('This application is not subscribed to any event.'), JSON_UNESCAPED_UNICODE) . ' + "</p>";
			return;
		}
		var labels = {
			active:   ' . json_encode(lang('Active'), JSON_UNESCAPED_UNICODE) . ',
			failing:  ' . json_encode(lang('Failing'), JSON_UNESCAPED_UNICODE) . ',
			disabled: ' . json_encode(lang('Stopped'), JSON_UNESCAPED_UNICODE) . '
		};
		var classes = { active: "on", failing: "warn", disabled: "off" };
		box.innerHTML = hooks.map(function (h) {
			var meta = [];
			if (h.last_success) { meta.push(' . json_encode(lang('Last delivered'), JSON_UNESCAPED_UNICODE) . ' + " " + h.last_success); }
			if (h.last_failure) { meta.push(' . json_encode(lang('Last failure'), JSON_UNESCAPED_UNICODE) . ' + " " + h.last_failure); }
			if (h.waiting)      { meta.push(h.waiting + " " + ' . json_encode(lang('waiting'), JSON_UNESCAPED_UNICODE) . '); }
			if (h.given_up)     { meta.push(h.given_up + " " + ' . json_encode(lang('given up'), JSON_UNESCAPED_UNICODE) . '); }
			return "<div class=\'api-hook\'>" +
				"<div class=\'api-hook-top\'>" +
					"<span class=\'api-hook-url\'>" + esc(h.url) + "</span>" +
					"<span class=\'api-status " + (classes[h.status] || "off") + "\'>" +
						(labels[h.status] || esc(h.status)) + "</span>" +
				"</div>" +
				"<div class=\'api-hook-events\'>" + h.events.map(function (name) {
					return "<span class=\'api-chip\'>" + esc(name) + "</span>";
				}).join("") + "</div>" +
				(meta.length ? "<div class=\'api-hook-meta\'>" + meta.join(" &middot; ") + "</div>" : "") +
				(h.error ? "<div class=\'api-hook-error\'><i class=\'bi bi-exclamation-triangle me-1\'></i>" + esc(h.error) + "</div>" : "") +
				"<div class=\'api-hook-acts\'>" +
					((h.given_up || h.status === "failing")
						? "<button type=\'button\' class=\'btn btn-sm btn-outline-primary api-hook-act\' data-act=\'webhook_retry\' data-id=\'" + h.id + "\'>" +
							' . json_encode(lang('Send again'), JSON_UNESCAPED_UNICODE) . ' + "</button>"
						: "") +
					"<button type=\'button\' class=\'btn btn-sm btn-outline-secondary api-hook-act\' data-act=\'webhook_status\' data-id=\'" + h.id + "\'>" +
						(h.status === "disabled"
							? ' . json_encode(lang('Resume'), JSON_UNESCAPED_UNICODE) . '
							: ' . json_encode(lang('Stop'), JSON_UNESCAPED_UNICODE) . ') + "</button>" +
					"<button type=\'button\' class=\'btn btn-sm btn-outline-danger api-hook-act\' data-act=\'webhook_delete\' data-id=\'" + h.id + "\'" +
						" data-confirm=\'" + ' . json_encode(lang('The subscription will be removed and the application will stop being told.'), JSON_UNESCAPED_UNICODE) . ' + "\'>" +
						' . json_encode(lang('Remove'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
				"</div>" +
			"</div>";
		}).join("");
	}

	var current = null;

	function openApp(id) {
		var app = APPS[id];
		if (!app) { return; }
		current = app;

		document.getElementById("d_app_id").value = app.id;
		document.getElementById("d_title").textContent = app.name;
		document.getElementById("d_sub").innerHTML =
			' . json_encode(lang('Owner'), JSON_UNESCAPED_UNICODE) . ' + " <b>" + app.owner + "</b> &middot; " + app.created;
		document.getElementById("d_name").value = app.name;
		document.getElementById("d_description").value = app.description || "";
		document.getElementById("d_key").textContent = app.key;
		document.getElementById("d_hint").textContent = app.hint
			? ("•••••••• " + app.hint)
			: ' . json_encode(lang('No secret issued yet'), JSON_UNESCAPED_UNICODE) . ';
		document.getElementById("d_status").checked = (app.status === "active");
		// tagin() builds its chips from the value the input holds at the moment
		// it is called, and offers no way to be told that value changed. So the
		// wrapper from the previous application is removed and it is set up
		// again. One drawer serving every application is what makes this
		// necessary, and is still cheaper than putting one drawer per
		// application into the page.
		var ip = document.getElementById("d_ip");
		var previous = ip.nextElementSibling;
		if (previous && previous.classList.contains("tagin-wrapper")) { previous.remove(); }
		ip.value = (app.ip || "").split(/[\s,;]+/).filter(Boolean).join(",");
		if (typeof tagin === "function") { tagin(ip); }
		document.getElementById("d_expires").value = app.expires || "";
		document.getElementById("d_rate").value = app.rate;

		Object.keys(app.scopes).forEach(function (group) {
			setRadio("scope[" + group + "]", app.scopes[group]);
		});
		paintImplied();

		document.getElementById("d_log_errors").checked = false;
		renderLog(app.log, false);

		renderHooks(app.webhooks || []);

		document.getElementById("d_delete_ask").classList.remove("d-none");
		document.getElementById("d_delete_confirm").classList.add("d-none");

		drawer.show();
	}

	drawerElement.addEventListener("show.bs.offcanvas", function () {
		document.body.classList.add("api-drawer-open");
	});

	drawerElement.addEventListener("hidden.bs.offcanvas", function () {
		document.body.classList.remove("api-drawer-open");
	});

	document.querySelectorAll(".api-row").forEach(function (row) {
		row.addEventListener("click", function () { openApp(row.dataset.app); });
		row.addEventListener("keydown", function (e) {
			if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openApp(row.dataset.app); }
		});
	});

	document.addEventListener("change", function (e) {
		if (e.target.name && e.target.name.indexOf("scope[") === 0) { paintImplied(); }
	});

	document.getElementById("d_log_errors").addEventListener("change", function () {
		if (current) { renderLog(current.log, this.checked); }
	});

	document.getElementById("d_delete_ask").addEventListener("click", function () {
		this.classList.add("d-none");
		document.getElementById("d_delete_confirm").classList.remove("d-none");
	});

	document.getElementById("d_delete_no").addEventListener("click", function () {
		document.getElementById("d_delete_confirm").classList.add("d-none");
		document.getElementById("d_delete_ask").classList.remove("d-none");
	});

	// Filter
	var filter = document.getElementById("api_filter");
	filter.addEventListener("input", function () {
		var needle = this.value.toLowerCase();
		document.querySelectorAll(".api-row").forEach(function (row) {
			row.style.display = row.textContent.toLowerCase().indexOf(needle) > -1 ? "" : "none";
		});
	});

	// The subscription buttons post through the form the drawer already has: a
	// form inside a form is not valid HTML, and that one already carries the
	// application id and the token this needs.
	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-hook-act");
		if (!button) { return; }
		if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) { return; }
		document.getElementById("d_action").value = button.dataset.act;
		document.getElementById("d_webhook_id").value = button.dataset.id;
		document.getElementById("d_action").form.submit();
	});

	// Copy buttons
	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-copy");
		if (!button) { return; }
		var source = document.querySelector(button.dataset.copy);
		if (!source) { return; }
		navigator.clipboard.writeText(source.textContent.trim()).then(function () {
			var original = button.innerHTML;
			button.innerHTML = "<i class=\'bi bi-check\'></i>";
			setTimeout(function () { button.innerHTML = original; }, 1500);
		});
	});

	// The one-time sheet closes only once the operator says they have the secret.
	var ack = document.getElementById("nc_ack");
	if (ack) {
		var closeButton = document.getElementById("nc_close");
		ack.addEventListener("change", function () { closeButton.classList.toggle("disabled", !this.checked); });
		closeButton.addEventListener("click", function () {
			if (this.classList.contains("disabled")) { return; }
			document.getElementById("api_secret_sheet").remove();
		});
	}

})();
</script>' . output_footer();
