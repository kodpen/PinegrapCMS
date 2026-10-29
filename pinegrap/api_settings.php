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
require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');
require_once(dirname(__FILE__) . '/includes/api/devices.php');
require_once(dirname(__FILE__) . '/includes/oauth/server.php');

$liveform = new liveform('api_settings');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/api_settings.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	// Every field here holds one value, except the permission rows
	// (scope[resource]). One posted as a list (name[]=x) stopped the request
	// in escape() or trim(), and app_id[]=x was read as application 1, which
	// the update and delete then changed. Nothing is written, so every
	// application stays as it was.
	$api_settings_lists = pg_request_unexpected_lists($_POST, array('scope' => 1));

	if ($api_settings_lists) {

		$liveform->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $api_settings_lists)))));

		header('Location: ' . $screen_url);

		exit();

	}

	// liveform reads field values from the session, not from $_POST, so the
	// post has to be handed to it before anything is validated or read back.
	$liveform->add_fields_to_session();

	$action = isset($_POST['api_action']) ? $_POST['api_action'] : '';

	$app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

	// Every action on an existing row (save, new secret, delete, its webhooks
	// and devices) is refused when the row's owner outranks the editor: the
	// key calls the API with the owner's rights as the cap
	// (api_app_row_locked()). Checked here, once, so no branch can miss it.
	if ($app_id > 0 && $action !== 'create') {

		$rank_row = db_item("SELECT user.user_role AS owner_role
			FROM api_apps
			LEFT JOIN user ON api_apps.owner_user_id = user.user_id
			WHERE api_apps.id = '" . $app_id . "' LIMIT 1");

		if ($rank_row && api_app_row_locked($rank_row['owner_role'], $user['role'])) {

			$liveform->add_error(lang('This application belongs to an account above yours, so only that account or one above it can change it.'));

			header('Location: ' . $screen_url);

			exit();

		}

	}

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

			// A team devices application is where staff sign in from their own
			// phones (includes/api/devices.php). It is given no secret at all:
			// the app it is typed into cannot keep one, and without a secret
			// the key cannot be used as HTTP Basic credentials either. An OAuth
			// application (includes/oauth/) is the same kind of switch for
			// outside applications a person connects, and has none either.
			$kind = api_app_kind(isset($_POST['kind']) ? $_POST['kind'] : '');

			$made = api_app_create(
				$liveform->get_field_value('name'),
				isset($_POST['description']) ? $_POST['description'] : '',
				$posted_scopes,
				(int)$user['id'],
				($kind === 'server'),
				$_SESSION['sessionusername'],
				$kind
			);

			// No row was written: there is no key to show.
			if (!$made) {

				$liveform->add_error(lang('The application could not be created.'));

				header('Location: ' . $screen_url);

				exit();

			}

			// The one and only time this pair is readable. It is handed to the
			// next request through the session rather than the query string,
			// because a query string is written to the server's access log.
			$_SESSION['software']['api_new_credentials'] = array(
				'name'   => $liveform->get_field_value('name'),
				'key'    => $made['key'],
				'secret' => $made['secret'],
				'kind'   => $made['kind']
			);

			header('Location: ' . $screen_url);

			exit();

		}

	} elseif ($action === 'update' && $app_id > 0) {

		$existing = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		if ($existing) {

			$audit_before = pg_audit_fetch('api_apps', $app_id);

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

			pg_audit_record('update', 'api_apps', $app_id, $audit_before);

			$liveform->add_notice(lang('The application was saved.'));

			log_activity(lang(array(
				'string' => 'API application ({var:1}) was changed.',
				'vars'   => $existing['name']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'rotate' && $app_id > 0) {

		$existing = db_item("SELECT id, api_secret_hash, kind FROM api_apps
			WHERE id = '" . $app_id . "' AND kind = 'server' LIMIT 1");

		// A key made without a secret (Pinegrap AI's, made in the Workspace
		// settings) is never given one: this screen is open to roles 0-2, and
		// a secret would let whoever issued it call the API with the rights of
		// the key's owner (api_app_secret_can_rotate()).
		if ($existing && !api_app_secret_can_rotate($existing)) {

			$liveform->add_error(lang('This application was made without a secret and is never given one, so it cannot sign in.'));

		} elseif ($existing) {

			// The outgoing secret keeps answering for a day. Issuing a new one
			// and expecting every integration to be updated in the same instant
			// is not how a live site works.
			$rotated = api_app_rotate_secret($app_id, 86400, $_SESSION['sessionusername']);

			if ($rotated) {

				$_SESSION['software']['api_new_credentials'] = array(
					'name'    => $rotated['name'],
					'key'     => $rotated['key'],
					'secret'  => $rotated['secret'],
					'rotated' => true
				);

			} else {

				$liveform->add_error(lang('That application could not be found.'));

			}

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
				? lang(array('string' => 'Deliveries to {var:1} were switched back on.', 'vars' => h($hook['url'])))
				: lang(array('string' => 'Deliveries to {var:1} were stopped.', 'vars' => h($hook['url']))));

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
				'vars'   => h($hook['url'])
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

			$liveform->add_notice(lang(array('string' => 'The subscription to {var:1} was removed.', 'vars' => h($hook['url']))));

			log_activity(lang(array(
				'string' => 'API event subscription ({var:1}) was removed.',
				'vars'   => $hook['url']
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'device_revoke' && $app_id > 0) {

		// Signing one person's device out from here: a lost phone, or someone
		// who has left. The next call that device makes is refused and it has
		// to sign in again.
		$device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;

		$device = db_item("SELECT api_devices.id, api_devices.name, user.user_username
			FROM api_devices
			LEFT JOIN user ON user.user_id = api_devices.user_id
			WHERE api_devices.id = '" . $device_id . "' AND api_devices.app_id = '" . $app_id . "' LIMIT 1");

		if ($device) {

			api_device_revoke((int)$device['id']);

			pg_audit('revoke', 'api_device', (int)$device['id'], array(), array(), $device['user_username'] . ' / ' . $device['name']);

			$liveform->add_notice(lang(array(
				'string' => '{var:1} was signed out on {var:2}.',
				'vars'   => array(h($device['user_username']), h($device['name']))
			)));

			log_activity(lang(array(
				'string' => 'a device of {var:1} was signed out ({var:2})',
				'vars'   => array($device['user_username'], $device['name'])
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif (in_array($action, array('oauth_client_status', 'oauth_client_delete'), true) && pg_oauth_ready()) {

		// An application that connects with OAuth, as opposed to the operator's
		// OAuth application above it: turned off, it can neither start a
		// connection nor use the ones it has; removed, its connections go with
		// it.
		$client_row_id = isset($_POST['oauth_client_row']) ? (int)$_POST['oauth_client_row'] : 0;

		$client = db_item("SELECT id, name, status FROM oauth_clients WHERE id = '" . $client_row_id . "' LIMIT 1");

		if ($client) {

			$client_name = ((string)$client['name'] !== '') ? (string)$client['name'] : lang('Unnamed application');

			if ($action === 'oauth_client_delete') {

				pg_oauth_client_delete((int)$client['id']);

				pg_audit('delete', 'oauth_client', (int)$client['id'], array(), array(), $client_name);

				$liveform->add_notice(lang(array('string' => '{var:1} was removed, and its connections were ended.', 'vars' => h($client_name))));

				log_activity(lang(array(
					'string' => 'OAuth application ({var:1}) was removed.',
					'vars'   => $client_name
				)), $_SESSION['sessionusername']);

			} else {

				$turn_on = ($client['status'] !== 'active');

				pg_oauth_client_set_status((int)$client['id'], $turn_on);

				pg_audit('update', 'oauth_client', (int)$client['id'], array('status' => $client['status']), array('status' => $turn_on ? 'active' : 'disabled'), $client_name);

				if ($turn_on) {

					$liveform->add_notice(lang(array('string' => '{var:1} was turned on again.', 'vars' => h($client_name))));

					log_activity(lang(array(
						'string' => 'OAuth application ({var:1}) was turned on.',
						'vars'   => $client_name
					)), $_SESSION['sessionusername']);

				} else {

					$liveform->add_notice(lang(array(
						'string' => '{var:1} was turned off. It cannot connect, and its connections are refused until it is turned on again.',
						'vars'   => h($client_name)
					)));

					log_activity(lang(array(
						'string' => 'OAuth application ({var:1}) was turned off.',
						'vars'   => $client_name
					)), $_SESSION['sessionusername']);

				}

			}

		}

		header('Location: ' . $screen_url . '#oauth_clients');

		exit();

	} elseif ($action === 'delete' && $app_id > 0) {

		$existing = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		if ($existing) {

			$audit_before = pg_audit_fetch('api_apps', $app_id);

			db("DELETE FROM api_apps WHERE id = '" . $app_id . "'");

			pg_audit_record('delete', 'api_apps', $app_id, $audit_before);

			// The devices signed in through it are signed out with it.
			api_devices_revoke_app($app_id);

			// The subscriptions and whatever they had queued go with it. A
			// subscription left behind is one this screen cannot show, because
			// the list is drawn per application, and until the dispatcher
			// learned to check it kept being delivered to.
			api_webhooks_delete_for_app($app_id);

			// Its Workspace / commands go with it, so the names are free again.
			if (pg_schema_has('ws_app_commands')) {

				db("DELETE FROM ws_app_commands WHERE app_id = '" . $app_id . "'");

			}

			$liveform->add_notice(lang(array('string' => 'The application {var:1} was deleted.', 'vars' => h($existing['name']))));

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
$apps = db_items("SELECT api_apps.*, user.user_username AS owner_username, user.user_role AS owner_role
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
function api_settings_group_text($key, $group = array()) {

	$text = array(
		'products'  => array(lang('Products'),   lang('Reading the catalogue; writing products, product groups and product attributes, and deleting them'), 'bi-box-seam'),
		'inventory' => array(lang('Inventory'),  lang('Setting a quantity and adjusting it - reading is under Products'), 'bi-grid-3x3-gap'),
		'orders'    => array(lang('Orders'),     lang('Reading orders and their lines, and the shipping methods, zones, taxes, countries and currencies they are computed with; writing status and notes'), 'bi-cart3'),
		'customers' => array(lang('Customers'),  lang('The people who buy (address book records); writing includes deleting one'), 'bi-person-lines-fill'),
		'pages'     => array(lang('Pages'),      lang('Page details, comment settings, folders, menus and short links'), 'bi-file-earmark'),
		'design'    => array(lang('Page design'), lang('The layout of the Visual Page Editor\'s pages; writing means proposing a change a designer applies, adding a page as a draft, setting when a page goes on the site and comes off it, putting back a published revision, or sharing the drafts as a prototype by a link'), 'bi-palette'),
		'comments'  => array(lang('Comments'),   lang('What visitors wrote under pages, records, products and events; writing means publishing, holding, featuring or removing one'), 'bi-chat-left-text'),
		'offers'    => array(lang('Offers'),     lang('Reading campaign rules; writing means switching one on or off, moving its dates or removing it, not creating it'), 'bi-file-earmark-text'),
		'key_codes' => array(lang('Key Codes'),  lang('The codes shoppers type instead of an offer\'s own code; writing makes codes for an existing offer, changes or removes one'), 'bi-key'),
		'gift_cards' => array(lang('Gift Cards'), lang('The cards, their codes, balances and where they were spent; writing issues a card, changes its balance or expiry, or removes it'), 'bi-gift'),
		'commissions' => array(lang('Commissions'), lang('What the affiliate program owes each affiliate, with its status and the order it came from - read only'), 'bi-cash-coin'),
		'files'     => array(lang('Files'),      lang('Reading the file list; uploading new files to one folder and describing them'), 'bi-folder'),
		'forms'     => array(lang('Forms'),      lang('Which forms the site has and what visitors filled in; writing marks a submission complete and fills in its text answers'), 'bi-ui-checks'),
		'calendars' => array(lang('Calendars'),  lang('The calendars and the events on them; writing adds, changes, publishes or removes an event, not its repeats or reservations'), 'bi-calendar-event'),
		'seo'       => array(lang('SEO'),        lang('What the analysis found across the site and the addresses visitors did not find, as lists to work through'), 'bi-graph-up-arrow'),
		'webhooks'  => array(lang('Webhooks'),   lang('Registering, changing and testing an address for event notifications'), 'bi-megaphone'),
		'meta'      => array(lang('Site info'),  lang('Currency, tax and order statuses - read only'), 'bi-info-circle'),
		'system'    => array(lang('System'),     lang('The health score and the checks behind it, for your own monitoring, and the audit trail'), 'bi-activity'),
		'visitors'  => array(lang('Visitors'),   lang('Visits and page views, the most viewed content and where visits came from, as totals - never a single visit'), 'bi-bar-chart'),
		'site_log'  => array(lang('Site Log'),   lang('Who did what on the site and from which address - read only'), 'bi-journal-text'),
		'campaigns' => array(lang('Campaigns'),  lang('E-mail campaigns, how far sending has got and who they go to; writing holds, releases, cancels, corrects or removes one, not putting one together'), 'bi-envelope'),
		'contact_groups' => array(lang('Contact Groups'), lang('The lists campaigns go to and who is on them; writing makes, renames and removes empty groups, puts contacts in or takes them out and records their e-mail choice'), 'bi-people'),
		'account'   => array(lang('Own account'), lang('Notifications, signed-in devices and push registration of the account the calls act for'), 'bi-person-badge')
	);

	if (isset($text[$key])) {

		return $text[$key];

	}

	// A group a module contributed (see includes/api/modules.php). It carries
	// its own wording, because this screen cannot know what an ERP permission
	// covers - and "Erp" with an empty line under it is what the operator would
	// otherwise be asked to grant.
	return array(
		isset($group['label']) && ($group['label'] !== '') ? $group['label'] : ucfirst($key),
		isset($group['description']) ? $group['description'] : '',
		isset($group['icon']) && ($group['icon'] !== '') ? $group['icon'] : 'bi-key'
	);

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

// Who is signed in through each team devices application, for the drawer's
// Devices tab. One query for the screen, grouped in PHP, like the log above.
$device_rows = array();

if (api_devices_ready()) {

	$signed_in = db_items("SELECT api_devices.id, api_devices.app_id, api_devices.name, api_devices.platform,
			api_devices.app_version, api_devices.created_timestamp, api_devices.last_used_timestamp,
			api_devices.last_used_ip, user.user_username
		FROM api_devices
		LEFT JOIN user ON user.user_id = api_devices.user_id
		ORDER BY GREATEST(api_devices.last_used_timestamp, api_devices.created_timestamp) DESC
		LIMIT 500");

	if (is_array($signed_in)) {

		foreach ($signed_in as $row) {

			$key = (int)$row['app_id'];

			if (!isset($device_rows[$key])) { $device_rows[$key] = array(); }

			$device_rows[$key][] = array(
				'id'       => (int)$row['id'],
				'user'     => (string)$row['user_username'],
				'name'     => (string)$row['name'],
				'platform' => (string)$row['platform'],
				'version'  => (string)$row['app_version'],
				'since'    => date('d.m.Y', (int)$row['created_timestamp']),
				'used_text' => api_settings_ago($row['last_used_timestamp']),
				'ip'       => (string)$row['last_used_ip']
			);

		}

	}

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

// The same subscriptions, flat and in one list. The drawer answers "what is
// this application subscribed to"; the card at the foot of the screen answers
// the other question - "what is this site telling, and how do I stop it" - and
// that one cannot be answered per application, because a subscription whose
// application has gone belongs to no drawer at all. Both are drawn from this
// one read, so they cannot disagree.
$webhook_all = array();

$hooks = db_items("SELECT id, app_id, url, events, status, last_success, last_failure"
	. (api_webhook_channel_groups_ready() ? ", channel_group_id" : "") . "
	FROM api_webhooks
	ORDER BY app_id ASC, id ASC");

$queue_state = array();

if (is_array($hooks) && !empty($hooks)) {

	// waiting: still on the ladder, or held for a failing subscription until
	// it is retried (attempts 0, api_webhook_queue_hold()); held counts those
	// on their own, because only Retry sends them. given_up: out of attempts,
	// and the reason the subscription went to 'failing'. The last error
	// carried on the newest row with an attempt on it is what actually tells
	// the operator what is wrong; a held row has never been tried and carries
	// none, so it must not stand in for it.
	$queue = db_items("SELECT webhook_id,
			SUM(CASE WHEN next_attempt_at > 0 OR attempts = 0 THEN 1 ELSE 0 END) AS waiting,
			SUM(CASE WHEN next_attempt_at = 0 AND attempts > 0 THEN 1 ELSE 0 END) AS given_up,
			SUM(CASE WHEN attempts = 0 AND next_attempt_at = 0 THEN 1 ELSE 0 END) AS held,
			MAX(CASE WHEN attempts > 0 THEN id END) AS newest
		FROM api_webhook_queue
		GROUP BY webhook_id");

	if (is_array($queue)) {

		$newest_ids = array();

		foreach ($queue as $row) {

			$queue_state[(int)$row['webhook_id']] = array(
				'waiting'  => (int)$row['waiting'],
				'given_up' => (int)$row['given_up'],
				'held'     => (int)$row['held'],
				'error'    => ''
			);

			if ((int)$row['newest'] > 0) {

				$newest_ids[] = (int)$row['newest'];

			}

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
			: array('waiting' => 0, 'given_up' => 0, 'held' => 0, 'error' => '');

		$row = array(
			'id'           => (int)$hook['id'],
			'url'          => $hook['url'],
			'events'       => $events,
			'status'       => $hook['status'],
			'last_success' => ((int)$hook['last_success'] > 0) ? api_settings_ago($hook['last_success']) : '',
			'last_failure' => ((int)$hook['last_failure'] > 0) ? api_settings_ago($hook['last_failure']) : '',
			'waiting'      => $state['waiting'],
			'given_up'     => $state['given_up'],
			'held'         => $state['held'],
			'error'        => $state['error'],
			'channel_group_id' => isset($hook['channel_group_id']) ? (int)$hook['channel_group_id'] : 0
		);

		$webhook_rows[$key][] = $row;

		$row['app_id'] = $key;

		$webhook_all[] = $row;

	}

}

// Who registered each of them. The application list above leaves out the
// documentation screen's temporary credentials, and an application that has
// been deleted is in no list at all, so the names are asked for by id: this
// card has to be able to say "a credential that is gone" rather than show an
// address with nothing beside it.
$webhook_app_names = array();

if (!empty($webhook_all)) {

	$wanted = array();

	foreach ($webhook_all as $row) {

		$wanted[(int)$row['app_id']] = true;

	}

	$named = db_items("SELECT api_apps.id, api_apps.name, api_apps.status, user.user_role AS owner_role
		FROM api_apps
		LEFT JOIN user ON api_apps.owner_user_id = user.user_id
		WHERE api_apps.id IN (" . implode(',', array_keys($wanted)) . ")");

	if (is_array($named)) {

		foreach ($named as $row) {

			$webhook_app_names[(int)$row['id']] = array(
				'name'   => $row['name'],
				'status' => $row['status'],
				'locked' => api_app_row_locked($row['owner_role'], $user['role'])
			);

		}

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

		$label = api_settings_group_text($group_key, $group);

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

	$is_oauth = (isset($app['kind']) && ($app['kind'] === 'oauth'));

	$is_device = (isset($app['kind']) && ($app['kind'] === 'device'));

	$signed_in_count = pg_format_number(isset($device_rows[(int)$app['id']]) ? count($device_rows[(int)$app['id']]) : 0, 0);

	$app_rows .= '
	<div class="api-row" data-app="' . (int)$app['id'] . '" role="button" tabindex="0">
		<div class="api-name">
			<i class="bi ' . ($is_oauth ? 'bi-plug' : ($is_device ? 'bi-phone' : 'bi-key')) . '"></i>
			<div>
				<b>' . h($app['name']) . '</b>
				' . ($is_oauth
					? '<code>' . lang('Connected apps (OAuth)') . ' &middot; ' . $signed_in_count . ' ' . lang('connections') . '</code>'
					: ($is_device
					? '<code>' . lang('Team devices') . ' &middot; ' . $signed_in_count . ' ' . lang('devices') . '</code>'
					: '<code>' . h(substr($app['api_key'], 0, 16)) . '&hellip;</code>')) . '
			</div>
		</div>
		<div class="api-chips">' . $chips . '</div>
		<div><span class="api-status ' . $status_class . '">' . h($status_label) . '</span></div>
		<div class="api-meta">' . h(api_settings_ago($app['last_used_timestamp'])) . '
			<small>' . ($app['last_used_ip'] !== '' ? h($app['last_used_ip']) : '&mdash;') . '</small></div>
		<div class="api-meta">' . ($stats['requests'] > 0 ? pg_format_number($stats['requests'], 0) . ' ' . lang('requests') : '&mdash;') . '
			<small' . ($stats['failures'] > 0 ? ' class="bad"' : '') . '>'
			. ($stats['failures'] > 0 ? pg_format_number($stats['failures'], 0) . ' ' . lang('errors') : '&mdash;') . '</small></div>
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
		'kind'        => $is_oauth ? 'oauth' : ($is_device ? 'device' : 'server'),
		'locked'      => api_app_row_locked($app['owner_role'], $user['role']),
		'devices'     => isset($device_rows[(int)$app['id']]) ? $device_rows[(int)$app['id']] : array(),
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

		$text = api_settings_group_text($group_key, $group);

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
			<div class="api-perm-text"><b id="' . $id . '_label">' . h($text[0]) . '</b><span>' . h($text[1]) . '</span></div>
			<div class="api-seg btn-group btn-group-sm" role="group" aria-labelledby="' . $id . '_label">
				<input type="radio" class="btn-check" name="' . $name . '" id="' . $id . '_none" value="" checked>
				<label class="btn" for="' . $id . '_none">' . lang('None') . '</label>
				' . $read . '
				' . $write . '
			</div>
		</div>';

	}

	return $html;

}

/* ---------------------------------------------------------------------------
   The OAuth connection guide, drawn in the drawer and in the sheet shown once
   the application is made
   --------------------------------------------------------------------------- */

function api_settings_oauth_guide($id_prefix, $button_class) {

	// The MCP server answers at two addresses: the file in the software
	// directory, reachable on any server, and the site's own address, which
	// only works where the site's URL rewriting hands unknown paths to the
	// router. The file comes first because it is the one that never fails.
	$addresses = pg_oauth_resource_addresses();

	$mcp_file = $addresses['mcp'][1];

	$mcp_short = $addresses['mcp'][0];

	$metadata_url = URL_SCHEME . pg_oauth_host() . PATH . '.well-known/' . ((PATH === '/') ? 'oauth-authorization-server' : 'openid-configuration');

	$box = function ($id, $value) use ($button_class) {
		return '<div class="api-secret-box mb-1"><code id="' . $id . '">' . h($value) . '</code>
			<button type="button" class="btn btn-sm ' . $button_class . ' api-copy" data-copy="#' . $id . '">' . lang('Copy') . '</button></div>';
	};

	// Each application is set up its own way: a menu, a command or a
	// configuration file. The steps and the text to paste, with this site's
	// address already in it, one folded entry per application. Product names
	// are names and are not translated; the configuration keys are what each
	// application reads and are written exactly.
	$json_address = json_encode($mcp_file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	$clients = array(
		'claude' => array(
			'name'    => lang('Claude (web, desktop and mobile)'),
			'steps'   => lang('Settings > Connectors > Add custom connector. Paste the address, then press Connect.'),
			'snippet' => ''
		),
		'claude_code' => array(
			'name'    => 'Claude Code',
			'steps'   => lang('Run this in a terminal, then type /mcp in Claude Code and choose the server to sign in.'),
			'snippet' => 'claude mcp add --transport http pinegrap ' . $mcp_file
		),
		'chatgpt' => array(
			'name'    => 'ChatGPT',
			'steps'   => lang('Settings > Apps & Connectors > Advanced settings: switch developer mode on. Then press Create, paste the address as the MCP server URL and choose OAuth as the authentication.'),
			'snippet' => ''
		),
		'vscode' => array(
			'name'    => 'VS Code (GitHub Copilot)',
			'steps'   => lang('Command Palette > MCP: Add Server > HTTP, then paste the address; or put this in .vscode/mcp.json. VS Code asks you to sign in the first time the server starts.'),
			'snippet' => "{\n  \"servers\": {\n    \"pinegrap\": {\n      \"type\": \"http\",\n      \"url\": " . $json_address . "\n    }\n  }\n}"
		),
		'cursor' => array(
			'name'    => 'Cursor',
			'steps'   => lang('Add a new MCP server in Cursor\'s settings, or put this in ~/.cursor/mcp.json. Then press Connect next to the server.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"url\": " . $json_address . "\n    }\n  }\n}"
		),
		'windsurf' => array(
			'name'    => 'Windsurf',
			'steps'   => lang('Put this in ~/.codeium/windsurf/mcp_config.json, refresh the MCP servers and sign in when asked.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"serverUrl\": " . $json_address . "\n    }\n  }\n}"
		),
		'gemini' => array(
			'name'    => 'Gemini CLI',
			'steps'   => lang('Put this in ~/.gemini/settings.json, then run /mcp auth pinegrap in Gemini CLI.'),
			'snippet' => "{\n  \"mcpServers\": {\n    \"pinegrap\": {\n      \"httpUrl\": " . $json_address . "\n    }\n  }\n}"
		),
		'codex' => array(
			'name'    => 'Codex',
			'steps'   => lang('Run these in a terminal. The second opens the sign-in in your browser.'),
			'snippet' => 'codex mcp add pinegrap --url ' . $mcp_file . "\n" . 'codex mcp login pinegrap'
		),
		'other' => array(
			'name'    => lang('Other applications'),
			'steps'   => lang('An application that asks for a server address works with the address above. One that can only start a program on the computer reaches the server through the mcp-remote bridge, which needs Node.js and opens the sign-in in the browser: give it this as the command.'),
			'snippet' => 'npx -y mcp-remote ' . $mcp_file
		)
	);

	$client_rows = '';

	foreach ($clients as $key => $client) {

		$client_rows .= '
			<details class="api-client">
				<summary>' . h($client['name']) . '</summary>
				<p class="mb-1">' . h($client['steps']) . '</p>'
				. (($client['snippet'] !== '')
					? '<div class="api-secret-box api-snippet mb-1"><code id="' . $id_prefix . '_c_' . $key . '">' . h($client['snippet']) . '</code>
						<button type="button" class="btn btn-sm ' . $button_class . ' api-copy" data-copy="#' . $id_prefix . '_c_' . $key . '">' . lang('Copy') . '</button></div>'
					: '') . '
			</details>';

	}

	return '
	<label class="form-label small">' . lang('MCP server address') . '</label>
	' . $box($id_prefix . '_mcp', $mcp_file) . '
	<div class="form-text mb-5">' . lang('Give this address to an AI application that speaks MCP, such as Claude or ChatGPT. It is the server\'s own file, so it works on every server.') . '</div>

	<label class="form-label small">' . lang('Short address') . '</label>
	' . $box($id_prefix . '_mcp_short', $mcp_short) . '
	<div class="form-text mb-5">' . lang('The same server at the site\'s own address. It works wherever the site\'s page addresses work; if an application cannot reach it, use the address above.') . '</div>

	<label class="form-label small">' . lang('Discovery address') . '</label>
	' . $box($id_prefix . '_meta', $metadata_url) . '
	<div class="form-text mb-5">' . lang('Applications find this on their own from the MCP server address. It is shown for checking the setup.') . '</div>

	<div class="api-guide">
		<div class="api-guide-title"><i class="bi bi-signpost-2 me-1"></i>' . lang('How to connect') . '</div>
		<ol class="mb-0">
			<li>' . lang('Add the MCP server address in the application, exactly as shown: the steps for each application are below. The application compares it with the address the site names in its answers, so another spelling of the host, with or without www, does not connect.') . '</li>
			<li>' . lang('The application opens this site\'s sign-in page. The person signs in with their own panel account; the application never sees the password.') . '</li>
			<li>' . lang('The site lists what the application asks for, and the person agrees or refuses.') . '</li>
			<li>' . lang('The tools appear in the application, and the connection is listed on this application\'s Connections tab, where it can be ended.') . '</li>
		</ol>
	</div>

	<div class="api-guide">
		<div class="api-guide-title"><i class="bi bi-window-stack me-1"></i>' . lang('Steps for each application') . '</div>
		' . $client_rows . '
	</div>

	<div class="api-guide">
		<div class="api-guide-title"><i class="bi bi-tools me-1"></i>' . lang('What the application can do') . '</div>
		<ul class="mb-0">
			<li>' . lang('Every permission area becomes a set of tools. Read gives the list and view tools; Write also gives the tools that create, change and delete.') . '</li>
			<li>' . lang('A person gets at most what their own panel account may do, and never more than the permissions chosen here.') . '</li>
			<li>' . lang('Narrowing the permissions takes effect at once. After widening them, connect again in the application so the person can agree to the new ones.') . '</li>
			<li>' . lang('Some applications use only a limited number of tools at a time, VS Code and Cursor among them. If one warns about too many tools, untick the areas you do not need on the consent screen, or switch tools off in the application.') . '</li>
		</ul>
	</div>';

}


$api_base_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/integration.php';

$permission_rows = api_settings_permission_rows('scope', 'd_scope');

/* ---------------------------------------------------------------------------
   Event notifications, site wide
   --------------------------------------------------------------------------- */

// Drawn on the screen rather than in the drawer, and drawn server side.
//
// The drawer's Events tab answers a question about one application. This
// answers the question the dashboard asks - the status tile says "3 active" and
// the operator has to be able to see which three and switch one off - and that
// list cannot live under an application, because the subscription that most
// needs stopping is the one whose application is not there any more.
//
// The card is not drawn at all on a site with no subscriptions. It is not a
// feature with nothing in it yet; it is a feature that site is not using, and
// an empty box would be one more thing to read past on every visit.
$webhook_card = '';

// The names of the channel groups the subscriptions are narrowed to.
$webhook_group_names = array();

$webhook_group_ids = array();

foreach ($webhook_all as $hook) {

	if ($hook['channel_group_id'] > 0) {

		$webhook_group_ids[$hook['channel_group_id']] = $hook['channel_group_id'];

	}

}

if (!empty($webhook_group_ids) && pg_schema_has('ws_channel_groups')) {

	foreach ((array)db_items("SELECT id, name FROM ws_channel_groups WHERE id IN (" . implode(',', $webhook_group_ids) . ")") as $group_row) {

		$webhook_group_names[(int)$group_row['id']] = (string)$group_row['name'];

	}

}

if (!empty($webhook_all)) {

	$hook_labels = array(
		'active'   => lang('Active'),
		'failing'  => lang('Failing'),
		'disabled' => lang('Stopped')
	);

	$hook_classes = array('active' => 'on', 'failing' => 'warn', 'disabled' => 'off');

	$hook_blocks = '';

	foreach ($webhook_all as $hook) {

		$hook_app_id = (int)$hook['app_id'];

		$owner_app = isset($webhook_app_names[$hook_app_id]) ? $webhook_app_names[$hook_app_id] : null;

		// Whether anything actually leaves the site for this address. The status
		// column answers half of it: the dispatcher also skips a subscription
		// whose application was deleted or switched off, and a row showing
		// "Active" beside a line saying nothing is delivered is the screen
		// arguing with itself.
		$orphan = ($owner_app === null);

		$blocked = ($orphan || $owner_app['status'] !== 'active');

		if ($orphan) {

			$owner = lang('The application that registered this is gone; nothing is delivered.');

		} elseif (strpos($owner_app['name'], '__test__') === 0) {

			// The documentation screen's credential is replaced every time that
			// page is opened, so a subscription registered through it is about
			// to lose its owner. Saying where it came from explains why it is
			// here at all.
			$owner = lang('Registered while trying a call on the documentation screen');

		} else {

			$owner = lang(array(
				'string' => 'Registered by {var:1}',
				'vars'   => $owner_app['name']
			));

			if ($blocked) {

				$owner .= ' - ' . lang('that application is switched off, so nothing is delivered.');

			}

		}

		$meta = array();

		if ($hook['last_success'] !== '') {

			$meta[] = lang('Last delivered') . ' ' . $hook['last_success'];

		}

		if ($hook['last_failure'] !== '') {

			$meta[] = lang('Last failure') . ' ' . $hook['last_failure'];

		}

		if ($hook['waiting'] > 0) {

			$meta[] = pg_format_number($hook['waiting'], 0) . ' ' . lang('waiting');

		}

		if ($hook['given_up'] > 0) {

			$meta[] = pg_format_number($hook['given_up'], 0) . ' ' . lang('given up');

		}

		$chips = '';

		// A subscription narrowed to a group of workspace channels hears only
		// those channels; the chip says which group, or that it was removed
		// (which switched the subscription off).
		if ($hook['channel_group_id'] > 0) {

			$group_name = isset($webhook_group_names[$hook['channel_group_id']])
				? $webhook_group_names[$hook['channel_group_id']]
				: lang('a group that was removed');

			$chips .= '<span class="api-chip"><i class="bi bi-collection me-1"></i>' . h(lang('Channel group') . ': ' . $group_name) . '</span>';

		}

		foreach ($hook['events'] as $event_name) {

			$chips .= '<span class="api-chip">' . h($event_name) . '</span>';

		}

		if ($blocked && $hook['status'] !== 'disabled') {

			$status_class = 'warn';

			$status_label = lang('Not delivered');

		} else {

			$status_class = isset($hook_classes[$hook['status']]) ? $hook_classes[$hook['status']] : 'off';

			$status_label = isset($hook_labels[$hook['status']]) ? $hook_labels[$hook['status']] : $hook['status'];

		}

		$hook_blocks .= '
			<div class="api-hook">
				<div class="api-hook-top">
					<span class="api-hook-url">' . h($hook['url']) . '</span>
					<span class="api-status ' . $status_class . '">' . h($status_label) . '</span>
				</div>
				<div class="api-hook-events">' . $chips . '</div>
				<div class="api-hook-meta' . ($blocked ? ' fg-danger' : '') . '">
					<i class="bi bi-' . ($blocked ? 'exclamation-triangle' : 'key') . ' me-1"></i>' . h($owner)
					. ($meta ? ' &middot; ' . h(implode(' · ', $meta)) : '') . '</div>'
				. ($hook['error'] !== ''
					? '<div class="api-hook-error"><i class="bi bi-exclamation-triangle me-1"></i>' . h($hook['error']) . '</div>'
					: '')
				// A subscription of a row whose owner outranks the editor gets no
				// buttons; the server refuses those actions anyway.
				. (($owner_app !== null && $owner_app['locked']) ? '' : '
				<form method="post" action="api_settings.php" class="api-hook-acts disable_shortcut">
					' . get_token_field() . '
					<input type="hidden" name="app_id" value="' . $hook_app_id . '">
					<input type="hidden" name="webhook_id" value="' . (int)$hook['id'] . '">'
					. (($hook['given_up'] > 0 || $hook['held'] > 0 || $hook['status'] === 'failing')
						? '<button type="submit" name="api_action" value="webhook_retry"
							class="btn btn-sm btn-outline theme-primary">' . lang('Send again') . '</button>'
						: '') . '
					<button type="submit" name="api_action" value="webhook_status"
						class="btn btn-sm btn-outline theme-secondary">'
						. (($hook['status'] === 'disabled') ? lang('Resume') : lang('Stop')) . '</button>
					<button type="submit" name="api_action" value="webhook_delete"
						class="btn btn-sm btn-outline theme-danger api-confirm"
						data-confirm="' . h(lang('The subscription will be removed and the application will stop being told.')) . '">'
						. lang('Remove') . '</button>
				</form>') . '
			</div>';

	}

	$webhook_card = '
	<div class="card mt-5" id="events">
		<div class="card-header d-flex align-items-center">
			<i class="bi bi-send me-3"></i><b>' . lang('Event notifications') . '</b>
			<span class="ms-auto small opacity-75 d-none lg:d-inline">'
				. lang('Every address this site is telling, whoever registered it.') . '</span>
		</div>
		<div class="card-body px-5 py-3">' . $hook_blocks . '
		</div>
	</div>';

}

// The applications that connect through OAuth: the clients that registered
// themselves or named themselves with a metadata document. Drawn on the
// screen like the event card, because they belong to the site's OAuth server
// rather than to one application row, and drawn only once OAuth is in use - an
// OAuth application exists, or a client is still on record from when one did.
$oauth_clients_card = '';

$oauth_in_use = false;

foreach ($apps as $listed_app) {

	if (isset($listed_app['kind']) && ($listed_app['kind'] === 'oauth')) {

		$oauth_in_use = true;

	}

}

$oauth_clients = pg_oauth_clients_overview();

if ($oauth_in_use || !empty($oauth_clients)) {

	// A loopback address is a program on the person's own computer whatever
	// port it names; an app scheme and a web address read for themselves.
	$on_this_computer = lang('A program on this computer');

	$client_blocks = '';

	foreach ($oauth_clients as $client) {

		$client_on = ($client['status'] === 'active');

		$identity = pg_oauth_client_identity_host($client);

		if ($identity !== '') {

			$origin = lang('Identified by') . ' <code>' . h($identity) . '</code>';

		} elseif ($client['registered_by'] === 'dynamic') {

			$origin = h(lang('Registered itself'));

		} else {

			$origin = h(lang('Added by the site'));

		}

		// Where its answers go, each place once: a client registers several
		// addresses of the same kind (VS Code lists four).
		$places = array();

		foreach (pg_oauth_client_redirect_uris($client) as $uri) {

			$destination = pg_oauth_redirect_destination($uri);

			$place = ($destination['kind'] === 'device') ? $on_this_computer : $destination['text'];

			$places[$place] = '<span class="api-chip">' . h($place) . '</span>';

		}

		$meta = array(
			pg_format_number((int)$client['connections'], 0) . ' ' . lang('connections'),
			lang('Last used') . ' ' . api_settings_ago($client['last_used_timestamp']),
			lang(array('string' => 'Registered on {var:1}', 'vars' => date('d.m.Y', (int)$client['created_timestamp'])))
		);

		if ((string)$client['software_version'] !== '') {

			$meta[] = (string)$client['software_version'];

		}

		$client_blocks .= '
			<div class="api-hook">
				<div class="api-hook-top">
					<span class="api-hook-url"><b>' . h(pg_oauth_client_name($client)) . '</b>
						<span class="small opacity-75 ms-1">' . $origin . '</span></span>
					<span class="api-status ' . ($client_on ? 'on' : 'off') . '">'
						. h($client_on ? lang('Active') : lang('Turned off')) . '</span>
				</div>
				' . (!empty($places) ? '<div class="api-hook-events">' . implode('', $places) . '</div>' : '') . '
				<div class="api-hook-meta">' . h(implode(' · ', $meta)) . '</div>
				<form method="post" action="api_settings.php" class="api-hook-acts disable_shortcut">
					' . get_token_field() . '
					<input type="hidden" name="oauth_client_row" value="' . (int)$client['id'] . '">
					<button type="submit" name="api_action" value="oauth_client_status"
						class="btn btn-sm btn-outline theme-secondary">' . h($client_on ? lang('Turn off') : lang('Turn on')) . '</button>
					<button type="submit" name="api_action" value="oauth_client_delete"
						class="btn btn-sm btn-outline theme-danger api-confirm"
						data-confirm="' . h(lang('The application will be removed and every connection it has will be ended. If it registered itself, it can register again.')) . '">'
						. h(lang('Remove')) . '</button>
				</form>
			</div>';

	}

	if ($client_blocks === '') {

		$client_blocks = '<p class="small fg-2 py-5 mb-0">' . h(lang('No application has connected yet.')) . '</p>';

	}

	$oauth_clients_card = '
	<div class="card mt-5" id="oauth_clients">
		<div class="card-header d-flex align-items-center">
			<i class="bi bi-plug me-3"></i><b>' . lang('Connected applications') . '</b>
			<span class="ms-auto small opacity-75 d-none lg:d-inline">'
				. lang('Applications people connected with OAuth, and where each sends its answers.') . '</span>
		</div>
		<div class="card-body px-5 py-3">' . $client_blocks . '
			<div class="alert theme-primary py-3 px-5 small mt-3 mb-1">
				<i class="bi bi-info-circle me-1"></i>'
				. lang('Turning an application off stops it at once and keeps its connections for when it is turned on again. Removing it ends its connections. An application that registered itself can register again, and each person is then asked again on the consent screen.') . '
			</div>
		</div>
	</div>';

}

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
.api-head { padding: 0 16px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--pg-fg-2); }
.api-row { padding: 13px 16px; border-top: 1px solid var(--pg-border-color); cursor: pointer; }
.api-row:hover, .api-row:focus-visible { background: var(--pg-bg-1); outline: 0; }
.api-name { display: flex; align-items: center; gap: 9px; min-width: 0; }
.api-name i { opacity: .7; }
.api-name b { font-size: 13px; display: block; }
/* Secondary text colour rather than opacity: faded chips and codes read
   under 3:1 on the light card. */
.api-name code { font-size: 10.5px; color: var(--pg-fg-2); }
.api-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.api-chip { font-size: 10px; padding: 2px 6px; border-radius: 5px; border: 1px solid var(--pg-border-color); }
.api-chip.w { border-color: var(--pg-primary); color: var(--pg-primary-fg-emphasis); }
.api-chip.muted { color: var(--pg-fg-2); }
.api-status { font-size: 10.5px; padding: 3px 9px; border-radius: 20px; font-weight: 600;
	border: 1px solid var(--pg-border-color); display: inline-block; }
.api-status.on { color: var(--pg-success-fg-emphasis); border-color: color-mix(in srgb, var(--pg-success) 35%, transparent); }
.api-status.off { color: var(--pg-fg-2); }
.api-status.warn { color: var(--pg-warning-fg-emphasis); border-color: color-mix(in srgb, var(--pg-warning) 35%, transparent); }
.api-meta { font-size: 11.5px; color: var(--pg-fg-2); }
.api-meta small { display: block; font-size: 10.5px; color: var(--pg-fg-2); }
.api-meta small.bad { color: var(--pg-danger-fg-emphasis); }
.api-empty { padding: 26px 16px; text-align: center; color: var(--pg-fg-2); border-top: 1px solid var(--pg-border-color); }

/* One permission per resource, three positions. The row carries the sentence
   that says what it reaches, because "Orders: write" on its own does not. */
.api-perm { display: flex; align-items: center; gap: 12px; padding: 13px 2px;
	border-top: 1px solid var(--pg-border-color); }
.api-perm:first-of-type { border-top: 0; }
.api-perm > i { font-size: 15px; width: 18px; text-align: center; opacity: .7; }
.api-perm-text { flex: 1; min-width: 0; }
.api-perm-text b { display: block; font-size: 12.5px; }
.api-perm-text span { display: block; font-size: 10.5px; color: var(--pg-fg-2); margin-top: 1px; }
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
	--bs-btn-color: var(--pg-fg-2); --bs-btn-border-color: var(--pg-border-color);
	--bs-btn-hover-border-color: var(--pg-border-color); }
.api-seg .btn-check:checked + .btn { color: var(--pg-on-accent); background: var(--pg-primary); border-color: var(--pg-primary); }
/* Write implies read, so the read position shows as held-open rather than
   inviting a click that would silently do nothing. A tint with the primary
   border, not the fill faded: fading the whole button fades its ink too, and
   in the dark theme dark ink at partial opacity sinks into the background. */
.api-seg .btn.implied { color: var(--pg-primary-fg-emphasis); background: var(--pg-primary-bg-subtle);
	border-color: var(--pg-primary); }
.api-seg-none { display: inline-flex; align-items: center; justify-content: center; min-width: 34px;
	font-size: 11px; opacity: .3; border: 1px solid var(--pg-border-color); border-left: 0; }
.api-log { display: grid; grid-template-columns: 46px 1fr auto auto auto; gap: 9px; align-items: center;
	padding: 8px 2px; border-top: 1px solid var(--pg-border-color); font-size: 11.5px; }
.api-log:first-child { border-top: 0; }
.api-log .m { font-size: 9.5px; font-weight: 700; text-align: center; border-radius: 4px; padding: 2px 0;
	background: var(--pg-bg-1); }
.api-log code { font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.api-log .s { font-size: 10.5px; font-weight: 600; }
.api-log .s.ok { color: var(--pg-success-fg-emphasis); }
.api-log .s.bad { color: var(--pg-danger-fg-emphasis); }
.api-log .d, .api-log .t { font-size: 10.5px; color: var(--pg-fg-2); }
.api-hook { border-top: 1px solid var(--pg-border-color); padding: 11px 2px; }
.api-hook:first-child { border-top: 0; }
.api-hook-top { display: flex; align-items: center; gap: 8px; }
.api-hook-url { flex: 1; min-width: 0; font-size: 12px; word-break: break-all; }
.api-hook-events { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.api-hook-meta { font-size: 10.5px; opacity: .65; margin-top: 6px; }
/* A blocked subscription prints its line in the danger ink; faded, it reads
   3:1 in the light theme. */
.api-hook-meta.fg-danger { opacity: 1; }
.api-hook-error { font-size: 10.5px; color: var(--pg-danger-fg-emphasis); margin-top: 5px; word-break: break-word; }
.api-hook-acts { display: flex; gap: 5px; margin-top: 8px; }
.api-secret-box { display: flex; align-items: center; gap: 8px; background: var(--pg-bg-1);
	border: 1px solid var(--pg-border-color); border-radius: 8px; padding: 9px 11px; }
.api-secret-box code { flex: 1; font-size: 12px; word-break: break-all; }
.api-guide { background: var(--pg-bg-1); border: 1px solid var(--pg-border-color); border-radius: 8px;
	padding: 10px 12px; margin-bottom: 10px; font-size: 12.5px; }
.api-guide-title { font-weight: 600; margin-bottom: 6px; }
.api-guide ol, .api-guide ul { padding-left: 18px; }
.api-guide li + li { margin-top: 4px; }
.api-client + .api-client { margin-top: 4px; }
.api-client summary { cursor: pointer; font-weight: 500; }
.api-client[open] summary { margin-bottom: 4px; }
.api-snippet code { white-space: pre; overflow-x: auto; word-break: normal; }
.api-danger-zone { border-top: 1px solid var(--pg-border-color); margin-top: 18px; padding-top: 14px; }
/* The site chat launcher is fixed at z-index 1080, an offcanvas is 1045, so the
   bubble sits on top of whatever the drawer puts in its bottom right corner -
   which here is Save. Hidden while the drawer is open rather than moved: the
   drawer is a modal context, and a chat bubble floating over the button that
   commits the form is not something to nudge sideways. */
body.api-drawer-open .pg-chat-launcher { display: none !important; }
#api_drawer.api-locked .api-hook-acts { display: none; }
</style>

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
	</div></div>

	<div class="d-flex flex-wrap align-items-center gap-3 mb-5">
		<div class="input-group input-group-sm" style="max-width:280px">
			<span class="input-group-text"><i class="bi bi-search"></i></span>
			<input type="text" class="form-control" id="api_filter" aria-label="' . h(lang('Search application')) . '" placeholder="' . lang('Search application') . '" autocomplete="off">
		</div>
		<div class="flex-grow-1"></div>
		<a class="btn btn-sm btn-outline theme-secondary" href="api_docs.php">
			<i class="bi bi-file-earmark-text me-1"></i>' . lang('API Documentation') . '</a>
		<a class="btn btn-sm btn-outline theme-secondary" href="settings_api.php">
			<i class="bi bi-sliders me-1"></i>' . lang('API Settings') . '</a>
		<button type="button" class="btn btn-sm btn-solid theme-primary" data-bs-toggle="dialog" data-bs-target="#api_new">
			<i class="bi bi-key me-1"></i>' . lang('New Application') . '</button>
	</div>

	<div class="card">
		<div class="card-header d-flex align-items-center">
			<i class="bi bi-key me-3"></i><b>' . lang('Applications') . '</b>
			<span class="ms-auto small opacity-75 d-none lg:d-inline">'
				. lang('Every application has its own key and secret. The secret is shown only when it is made.') . '</span>
		</div>
		<div class="card-body px-3 py-5">
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

	' . $oauth_clients_card . '

	' . $webhook_card . '

	<p class="small fg-2 mt-5">
		<i class="bi bi-info-circle me-1"></i>'
		. lang('The key is not shown in the list, only its prefix. The secret cannot be read back anywhere.') . '
	</p>
</main>

<!-- The drawer. One of them, filled from the row that was clicked: a drawer per
     application would put every application\'s settings into the page at once. -->
<dialog class="drawer drawer-end" id="api_drawer" aria-labelledby="d_title" style="width:min(520px,100%)">
	<form method="post" action="api_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100">
		' . get_token_field() . '
		<input type="hidden" name="api_action" value="update" id="d_action">
		<input type="hidden" name="webhook_id" value="" id="d_webhook_id">
		<input type="hidden" name="device_id" value="" id="d_device_id">
		<input type="hidden" name="app_id" id="d_app_id" value="">

		<div class="drawer-header pb-1 d-block">
			<div class="d-flex align-items-start">
				<h5 class="drawer-title mb-0" id="d_title"></h5>
				<button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
			</div>
			<p class="small opacity-75 mb-0 mt-1" id="d_sub"></p>
		</div>

		<ul class="nav nav-tabs px-5" role="tablist">
			<li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#d_general" type="button">' . lang('General') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_perms" type="button">' . lang('Permissions') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_security" type="button">' . lang('Security') . '</button></li>
			<li class="nav-item" id="d_devices_tab"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_devices" type="button" id="d_devices_label"
				data-device="' . h(lang('Devices')) . '" data-oauth="' . h(lang('Connections')) . '">' . lang('Devices') . '</button></li>
			<li class="nav-item" id="d_hooks_tab"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_hooks" type="button">' . lang('Events') . '</button></li>
			<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#d_log" type="button">' . lang('Log') . '</button></li>
		</ul>

		<div class="drawer-body tab-content flex-grow-1 overflow-auto">

			<div class="tab-pane fade show active" id="d_general">
				<div class="mb-5">
					<label class="form-label small">' . lang('Name') . '</label>
					<input type="text" class="form-control form-control-sm" name="name" id="d_name" maxlength="190">
				</div>
				<div class="mb-5">
					<label class="form-label small">' . lang('Description') . '</label>
					<input type="text" class="form-control form-control-sm" name="description" id="d_description" maxlength="500">
				</div>
				<div class="mb-5">
					<label class="form-label small">' . lang('API Key') . '</label>
					<div class="api-secret-box"><code id="d_key"></code>
						<button type="button" class="btn btn-sm btn-outline theme-secondary api-copy" data-copy="#d_key">' . lang('Copy') . '</button></div>
					<div class="form-text">' . lang('Public identifier. Safe to share with the integrator.') . '</div>
				</div>
				<div class="mb-5" id="d_secret_block">
					<label class="form-label small">' . lang('Secret') . '</label>
					<div class="api-secret-box"><code id="d_hint" class="fg-2"></code>
						<button type="submit" class="btn btn-sm btn-outline theme-warning" name="api_action" value="rotate" id="d_rotate">
							<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Issue a new secret') . '</button></div>
					<div class="form-text">'
						. lang('Only the last four characters are kept for display. A new secret is shown once; the previous one keeps working for 24 hours.') . '</div>
				</div>
				<div class="mb-5 d-none" id="d_nosecret_block">
					<label class="form-label small">' . lang('Secret') . '</label>
					<div class="form-text mt-0">' . h(lang('This application was made without a secret and is never given one, so it cannot sign in.')) . '</div>
				</div>
				<div class="alert theme-warning py-3 px-5 small mb-5 d-none" id="d_locked_note">
					<i class="bi bi-lock me-1"></i>' . h(lang('This application belongs to an account above yours, so only that account or one above it can change it.')) . '
				</div>
				<div class="mb-5" id="d_device_block">
					<label class="form-label small">' . lang('Signing in') . '</label>
					<div class="api-secret-box"><code id="d_login">POST ' . h($api_base_url) . '/auth/login</code>
						<button type="button" class="btn btn-sm btn-outline theme-secondary api-copy" data-copy="#d_login">' . lang('Copy') . '</button></div>
					<div class="form-text">'
						. lang('Staff sign in on the app with their own user name and password. Each person gets at most what their own account may do, and never more than the permissions chosen here. The key is not a secret; the app sends it as client_key, and can leave it out while this is the only team devices application.') . '</div>
				</div>
				<div class="mb-5" id="d_oauth_block">
					' . api_settings_oauth_guide('d_oauth', 'btn-outline theme-secondary') . '
				</div>
				<div class="mb-5" id="d_auth_block">
					<label class="form-label small">' . lang('Authentication') . '</label>
					<div class="api-secret-box"><code id="d_curl">curl -u APPLICATION_KEY:SECRET_KEY "'
						. h($api_base_url) . '/meta"</code>
						<button type="button" class="btn btn-sm btn-outline theme-secondary api-copy" data-copy="#d_curl">' . lang('Copy') . '</button></div>
					<div class="form-text">'
						. lang('HTTP Basic: the user name is the key above, the password is the secret. This line is the whole handshake - hand it to the integrator with the key.') . '</div>
				</div>
				<div class="form-field">
					<div class="switch"><input class="" type="checkbox" name="status" value="active" id="d_status"></div>
					<label class="small" for="d_status">' . lang('Active') . '</label>
				</div>
			</div>

			<div class="tab-pane fade" id="d_perms">
				' . $permission_rows . '
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-3">
					<i class="bi bi-info-circle me-1"></i>
					<b>' . lang('Write covers read.') . '</b> '
					. lang('Choosing Write holds the read position open; the record keeps only what you chose.') . '
				</div>
				<div class="alert theme-secondary py-3 px-5 small mb-0">
					<i class="bi bi-shield-check me-1"></i>'
					. lang('An application can never do more than the account that owns it. Narrowing the owner narrows the application immediately.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_security">
				<div class="mb-5">
					<label class="form-label small">' . lang('Allowed addresses') . '</label>
					<input type="text" class="form-control tagin min-height-tagin" name="ip_allowlist" id="d_ip" data-pg-chips
						data-placeholder="' . lang('Add an address') . '" value="">' . get_date_picker_format() . pg_fields_script() . '
					<div class="form-text">'
						. lang('Leave empty to let this application connect from anywhere. Once an address is added, requests from anywhere else are refused.')
						. '<br>' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . '</div>
				</div>
				<div class="row g-5">
					<div class="col-6">
						<label class="form-label small" for="d_expires">' . lang('Expires on') . '</label>
						<input type="date" data-pg-date class="form-control form-control-sm" name="expires_at" id="d_expires">
						<div class="form-text">' . lang('After this date the key stops working. Leave empty for a key that does not expire.') . '</div>
					</div>
					<div class="col-6">
						<label class="form-label small">' . lang('Requests per minute') . '</label>
						<input type="number" class="form-control form-control-sm" name="rate_limit_per_min" id="d_rate" min="1" max="6000">
						<div class="form-text">' . lang('When an application asks for more than this in one minute, the rest of that minute is refused and it is told how long to wait.') . '</div>
					</div>
				</div>
				<div class="api-danger-zone" id="d_danger_zone">
					<button type="button" class="btn btn-sm btn-outline theme-danger" id="d_delete_ask">
						<i class="bi bi-trash me-1"></i>' . lang('Delete this application') . '</button>
					<div id="d_delete_confirm" class="d-none">
						<p class="small mb-3">' . lang('Any integration using this key stops working immediately. This cannot be undone.') . '</p>
						<button type="submit" class="btn btn-sm btn-solid theme-danger" name="api_action" value="delete">' . lang('Yes, delete') . '</button>
						<button type="button" class="btn btn-sm btn-outline theme-secondary" id="d_delete_no">' . lang('Cancel') . '</button>
					</div>
				</div>
			</div>

			<div class="tab-pane fade" id="d_devices">
				<div class="d-flex align-items-center mb-3">
					<span class="small opacity-75">' . lang('Who is signed in through this application') . '</span>
				</div>
				<div id="d_device_rows"></div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('Signing a device out ends it at once; the person has to sign in again. A new password, or signing out everywhere, signs out all of that person\'s devices too.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_hooks">
				<div class="d-flex align-items-center mb-3">
					<span class="small opacity-75">' . lang('Addresses this application asked to be told at') . '</span>
				</div>
				<div id="d_hook_rows"></div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('The application registers these itself through the API, so it can move its own address without asking you. Here you can stop one, send what is waiting again, or remove it.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_log">
				<div class="d-flex align-items-center mb-3">
					<span class="small opacity-75">' . lang('Most recent requests') . '</span>
					<div class="form-field ms-auto">
						<div class="switch"><input class="" type="checkbox" id="d_log_errors"></div>
						<label class="small" for="d_log_errors">' . lang('Errors only') . '</label>
					</div>
				</div>
				<div id="d_log_rows"></div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('403 means a permission the application does not hold was tried; 409 means the same operation was sent twice and was not applied again.') . '
				</div>
			</div>

		</div>

		<div class="drawer-footer border-top p-5 d-flex gap-3">
			<span class="flex-grow-1"></span>
			<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bs-dismiss="drawer">' . lang('Cancel') . '</button>
			<button type="submit" class="btn btn-sm btn-solid theme-success" id="d_save">' . lang('Save') . '</button>
		</div>
	</form>
</dialog>

<!-- New application -->
<dialog class="dialog dialog-lg dialog-scrollable" id="api_new" aria-labelledby="api_new_title">
		<form method="post" action="api_settings.php" autocomplete="off" class="d-contents disable_shortcut">
			' . get_token_field() . '
			<input type="hidden" name="api_action" value="create">
			<div class="dialog-header">
				<h5 class="dialog-title" id="api_new_title"><i class="bi bi-key me-3"></i>' . lang('New Application') . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
			</div>
			<div class="dialog-body">
				<div class="mb-5">
					<label class="form-label small" for="n_name">' . lang('Name') . '</label>
					<input type="text" class="form-control form-control-sm" name="name" id="n_name" maxlength="190" required
						placeholder="' . lang('For example: marketplace sync') . '">
				</div>
				<div class="mb-5">
					<label class="form-label small" for="n_description">' . lang('Description') . '</label>
					<input type="text" class="form-control form-control-sm" name="description" id="n_description" maxlength="500">
				</div>
				<div class="mb-7">
					<label class="form-label small">' . lang('Who connects') . '</label>
					<div class="form-field">
						<input class="radio" type="radio" name="kind" value="server" id="n_kind_server" checked>
						<label class="small" for="n_kind_server"><b>' . lang('An integration') . '</b><br>
							<span class="opacity-75">' . lang('An outside system calls with a key and a secret, with the rights of the account that creates it.') . '</span></label>
					</div>
					<div class="form-field mt-3">
						<input class="radio" type="radio" name="kind" value="device" id="n_kind_device">
						<label class="small" for="n_kind_device"><b>' . lang('Team devices') . '</b><br>
							<span class="opacity-75">' . lang('Staff sign in on a mobile app with their own panel account. There is no secret; the permissions below are the most any device may reach.') . '</span></label>
					</div>
					<div class="form-field mt-3">
						<input class="radio" type="radio" name="kind" value="oauth" id="n_kind_oauth">
						<label class="small" for="n_kind_oauth"><b>' . lang('Connected apps (OAuth)') . '</b><br>
							<span class="opacity-75">' . lang('A person connects an outside application, such as an MCP client, after signing in on the site and agreeing to what it may do. There is no secret; the permissions below are the most any connection may reach.') . '</span></label>
					</div>
				</div>
				<label class="form-label small">' . lang('Permissions') . '</label>
				' . api_settings_permission_rows('scope', 'n_scope') . '
			</div>
			<div class="dialog-footer">
				<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
				<button type="submit" class="btn btn-sm btn-solid theme-primary" id="n_submit"
					data-server="' . h(lang('Create and show the secret')) . '" data-device="' . h(lang('Create')) . '">' . lang('Create and show the secret') . '</button>
			</div>
		</form>
</dialog>';

/* An OAuth application has nothing to reveal either. What the operator needs is
   the address an outside application is pointed at. */
if (($new_credentials !== null) && isset($new_credentials['kind']) && ($new_credentials['kind'] === 'oauth')) {

	echo '
<div class="pg-open-sheet" id="api_secret_sheet" style="background:rgba(0,0,0,.6)">
	<dialog class="dialog dialog-lg pg-dialog-centered" id="api_sheet_oauth" aria-labelledby="api_sheet_oauth_title" open>
			<div class="dialog-header border-0 pb-0">
				<h5 class="dialog-title" id="api_sheet_oauth_title"><i class="bi bi-plug me-3 fg-primary"></i>' . lang('Connecting applications with OAuth is switched on') . '</h5>
			</div>
			<div class="dialog-body">
				<p class="mb-5">' . lang('Outside applications can now ask a person to connect. The person signs in on this site, sees what the application asks for and agrees or refuses; the application never sees the password. Nothing here has to be kept secret.') . '</p>

				' . api_settings_oauth_guide('nc', 'btn-outline theme-primary') . '

				<div class="alert theme-primary py-3 px-5 small mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('Applications register themselves the first time they connect. Each connection appears on this application\'s Connections tab, where it can be ended; a new password, or signing out everywhere, ends a person\'s connections too.') . '
				</div>
			</div>
			<div class="dialog-footer">
				<button type="button" class="btn btn-sm btn-solid theme-success" id="nc_close">' . lang('Close') . '</button>
			</div>
	</dialog>
</div>';

	$new_credentials = null;

}

/* A team devices application has nothing to reveal once: its key is not a
   secret and stays readable in the drawer. What the operator needs on the way
   out of the form is what to hand the app developer. */
if (($new_credentials !== null) && isset($new_credentials['kind']) && ($new_credentials['kind'] === 'device')) {

	echo '
<div class="pg-open-sheet" id="api_secret_sheet" style="background:rgba(0,0,0,.6)">
	<dialog class="dialog dialog-lg pg-dialog-centered" id="api_sheet_device" aria-labelledby="api_sheet_device_title" open>
			<div class="dialog-header border-0 pb-0">
				<h5 class="dialog-title" id="api_sheet_device_title"><i class="bi bi-phone me-3 fg-primary"></i>' . lang('Signing in from devices is switched on') . '</h5>
			</div>
			<div class="dialog-body">
				<p class="mb-5">' . lang('Staff can now sign in on the app with their own user name and password. Nothing here has to be kept secret.') . '</p>

				<label class="form-label small text-uppercase opacity-75">' . lang('Sign-in address') . '</label>
				<div class="api-secret-box mb-5">
					<code id="nc_login">POST ' . h($api_base_url) . '/auth/login</code>
					<button type="button" class="btn btn-sm btn-outline theme-primary api-copy" data-copy="#nc_login">' . lang('Copy') . '</button>
				</div>

				<label class="form-label small text-uppercase opacity-75">client_key</label>
				<div class="api-secret-box mb-5">
					<code id="nc_key">' . h($new_credentials['key']) . '</code>
					<button type="button" class="btn btn-sm btn-outline theme-primary api-copy" data-copy="#nc_key">' . lang('Copy') . '</button>
				</div>

				<div class="alert theme-primary py-3 px-5 small mb-0">
					<i class="bi bi-info-circle me-1"></i>'
					. lang('The app can leave client_key out while this is the only team devices application. Signed-in devices appear on its Devices tab, where each can be signed out.') . '
				</div>
			</div>
			<div class="dialog-footer">
				<button type="button" class="btn btn-sm btn-solid theme-success" id="nc_close">' . lang('Close') . '</button>
			</div>
	</dialog>
</div>';

	$new_credentials = null;

}

/* The one-time reveal. Rendered only on the request that follows the write that
   made the secret, and the session entry it came from is already gone. */
if ($new_credentials !== null) {

	echo '
<div class="pg-open-sheet" id="api_secret_sheet" style="background:rgba(0,0,0,.6)">
	<dialog class="dialog dialog-lg pg-dialog-centered" id="api_sheet_secret" aria-labelledby="api_sheet_secret_title" open>
			<div class="dialog-header border-0 pb-0">
				<h5 class="dialog-title" id="api_sheet_secret_title"><i class="bi bi-key me-3 fg-warning"></i>'
					. ((isset($new_credentials['rotated']) && $new_credentials['rotated'])
						? lang('A new secret was issued')
						: lang('The application was created')) . '</h5>
			</div>
			<div class="dialog-body">
				<p class="mb-5"><b>' . lang('Once this window is closed the secret is never shown again.') . '</b><br>
					<span class="small opacity-75">'
					. lang('Only a verification hash is stored. If it is lost, issue a new one.') . '</span></p>

				<label class="form-label small text-uppercase opacity-75">' . lang('Key') . '</label>
				<div class="api-secret-box mb-5">
					<code id="nc_key">' . h($new_credentials['key']) . '</code>
					<button type="button" class="btn btn-sm btn-outline theme-primary api-copy" data-copy="#nc_key">' . lang('Copy') . '</button>
				</div>

				<label class="form-label small text-uppercase opacity-75">' . lang('Secret') . '</label>
				<div class="api-secret-box mb-5">
					<code id="nc_secret">' . h($new_credentials['secret']) . '</code>
					<button type="button" class="btn btn-sm btn-outline theme-primary api-copy" data-copy="#nc_secret">' . lang('Copy') . '</button>
				</div>

				<div class="alert theme-warning py-3 px-5 small mb-0">
					<i class="bi bi-lock me-1"></i>'
					. lang('Keep the secret like a password. Do not put it in a URL, an email or a shared file - server logs record those too.') . '
					<br>' . lang('Connecting:') . ' <code>Authorization: Basic base64(' . lang('key') . ':' . lang('secret') . ')</code>
				</div>
			</div>
			<div class="dialog-footer">
				<div class="form-field me-auto">
					<input class="check" type="checkbox" id="nc_ack">
					<label class="small" for="nc_ack">' . lang('I have copied it and stored it somewhere safe') . '</label>
				</div>
				<button type="button" class="btn btn-sm btn-solid theme-success disabled" id="nc_close">' . lang('Close') . '</button>
			</div>
	</dialog>
</div>';

}

echo '
<script>
(function () {

	var APPS = ' . json_encode($app_data, JSON_UNESCAPED_UNICODE) . ';

	var API_BASE = ' . json_encode($api_base_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';

	// This runs while the page is parsed; Bootstrap is reached through pgUI
	// only when a row is opened, so a Bootstrap that loads later (6 is a
	// deferred module) is in place by then.
	var drawerElement = document.getElementById("api_drawer");

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
			box.innerHTML = "<p class=\'small fg-2 py-5 mb-0\'>" +
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
			box.innerHTML = "<p class=\'small fg-2 py-5 mb-0\'>" +
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
					((h.given_up || h.held || h.status === "failing")
						? "<button type=\'button\' class=\'btn btn-sm btn-outline theme-primary api-hook-act\' data-act=\'webhook_retry\' data-id=\'" + h.id + "\'>" +
							' . json_encode(lang('Send again'), JSON_UNESCAPED_UNICODE) . ' + "</button>"
						: "") +
					"<button type=\'button\' class=\'btn btn-sm btn-outline theme-secondary api-hook-act\' data-act=\'webhook_status\' data-id=\'" + h.id + "\'>" +
						(h.status === "disabled"
							? ' . json_encode(lang('Resume'), JSON_UNESCAPED_UNICODE) . '
							: ' . json_encode(lang('Stop'), JSON_UNESCAPED_UNICODE) . ') + "</button>" +
					"<button type=\'button\' class=\'btn btn-sm btn-outline theme-danger api-hook-act\' data-act=\'webhook_delete\' data-id=\'" + h.id + "\'" +
						" data-confirm=\'" + ' . json_encode(lang('The subscription will be removed and the application will stop being told.'), JSON_UNESCAPED_UNICODE) . ' + "\'>" +
						' . json_encode(lang('Remove'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
				"</div>" +
			"</div>";
		}).join("");
	}

	// The people signed in through a team devices application, most recently
	// active first, each with the button that signs that one device out.
	function renderDevices(devices) {
		var box = document.getElementById("d_device_rows");
		if (!devices.length) {
			box.innerHTML = "<p class=\'small fg-2 py-5 mb-0\'>" +
				' . json_encode(lang('Nobody has signed in through this application yet.'), JSON_UNESCAPED_UNICODE) . ' + "</p>";
			return;
		}
		box.innerHTML = devices.map(function (d) {
			var meta = [d.platform, d.version, d.ip].filter(Boolean).map(esc);
			meta.push(' . json_encode(lang('since'), JSON_UNESCAPED_UNICODE) . ' + " " + esc(d.since));
			return "<div class=\'api-hook\'>" +
				"<div class=\'api-hook-top\'>" +
					"<span class=\'api-hook-url\'><b>" + esc(d.user) + "</b> &middot; " + esc(d.name) + "</span>" +
					"<span class=\'small opacity-75\'>" + esc(d.used_text) + "</span>" +
				"</div>" +
				"<div class=\'api-hook-meta\'>" + meta.join(" &middot; ") + "</div>" +
				"<div class=\'api-hook-acts\'>" +
					"<button type=\'button\' class=\'btn btn-sm btn-outline theme-danger api-device-act\' data-id=\'" + d.id + "\'" +
						" data-confirm=\'" + esc(' . json_encode(lang('This device will be signed out and will have to sign in again.'), JSON_UNESCAPED_UNICODE) . ') + "\'>" +
						' . json_encode(lang('Sign out'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
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
		// The example carries the real key of the application being looked at,
		// so only the secret is left to fill in. A line with two placeholders
		// invites pasting the secret into the wrong one.
		document.getElementById("d_curl").textContent =
			"curl -u " + app.key + ":SECRET_KEY \"" + API_BASE + "/meta\"";
		document.getElementById("d_hint").textContent = app.hint
			? ("•••••••• " + app.hint)
			: ' . json_encode(lang('No secret issued yet'), JSON_UNESCAPED_UNICODE) . ';
		document.getElementById("d_status").checked = (app.status === "active");
		// One drawer serves every application, so the chips are redrawn with
		// the addresses of the application being opened.
		pgChips.set("#d_ip", (app.ip || "").split(/[\s,;]+/).filter(Boolean));
		window.pgDatePicker.set(document.getElementById("d_expires"), app.expires || "");
		document.getElementById("d_rate").value = app.rate;

		Object.keys(app.scopes).forEach(function (group) {
			setRadio("scope[" + group + "]", app.scopes[group]);
		});
		paintImplied();

		document.getElementById("d_log_errors").checked = false;
		renderLog(app.log, false);

		renderHooks(app.webhooks || []);

		// A team devices application has no secret to rotate and no curl line
		// to hand over, and a phone never registers event subscriptions; what it
		// has instead is the sign-in address and the list of devices.
		//
		// An OAuth application is the same, with the discovery address in place
		// of the sign-in one and its connections in place of devices.
		var device = (app.kind === "device");
		var oauth = (app.kind === "oauth");
		var keyless = device || oauth;
		// A server key made without a secret (Pinegrap AI\'s) is never given
		// one, so it gets neither the rotate button nor the curl line.
		var noSecret = !keyless && !app.has_secret;
		document.getElementById("d_secret_block").classList.toggle("d-none", keyless || noSecret);
		document.getElementById("d_auth_block").classList.toggle("d-none", keyless || noSecret);
		document.getElementById("d_nosecret_block").classList.toggle("d-none", !noSecret);
		document.getElementById("d_device_block").classList.toggle("d-none", !device);
		document.getElementById("d_oauth_block").classList.toggle("d-none", !oauth);
		document.getElementById("d_devices_tab").classList.toggle("d-none", !keyless);
		document.getElementById("d_hooks_tab").classList.toggle("d-none", keyless);
		var devicesLabel = document.getElementById("d_devices_label");
		devicesLabel.textContent = oauth ? devicesLabel.dataset.oauth : devicesLabel.dataset.device;
		renderDevices(app.devices || []);
		// A row whose owner outranks the editor is refused on the server; its
		// Save, new secret, delete and webhook and device buttons are hidden.
		var locked = !!app.locked;
		document.getElementById("d_locked_note").classList.toggle("d-none", !locked);
		document.getElementById("d_save").classList.toggle("d-none", locked);
		document.getElementById("d_rotate").classList.toggle("d-none", locked);
		document.getElementById("d_danger_zone").classList.toggle("d-none", locked);
		drawerElement.classList.toggle("api-locked", locked);
		var firstTab = document.querySelector("#api_drawer .nav-link[data-bs-target=\'#d_general\']");
		if (firstTab) { pgUI.tab.show(firstTab); }

		document.getElementById("d_delete_ask").classList.remove("d-none");
		document.getElementById("d_delete_confirm").classList.add("d-none");

		pgUI.drawer.show(drawerElement);
	}

	pgUI.drawer.on(drawerElement, "show", function () {
		document.body.classList.add("api-drawer-open");
	});

	pgUI.drawer.on(drawerElement, "hidden", function () {
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
		var button = e.target.closest(".api-device-act");
		if (!button) { return; }
		if (!window.confirm(button.dataset.confirm)) { return; }
		document.getElementById("d_action").value = "device_revoke";
		document.getElementById("d_device_id").value = button.dataset.id;
		document.getElementById("d_action").form.submit();
	});

	// The create button says what happens next: an integration is shown its
	// secret once, a team devices application has none.
	document.querySelectorAll("#api_new input[name=\'kind\']").forEach(function (radio) {
		radio.addEventListener("change", function () {
			var submit = document.getElementById("n_submit");
			submit.textContent = (this.value === "server") ? submit.dataset.server : submit.dataset.device;
		});
	});

	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-hook-act");
		if (!button) { return; }
		if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) { return; }
		document.getElementById("d_action").value = button.dataset.act;
		document.getElementById("d_webhook_id").value = button.dataset.id;
		document.getElementById("d_action").form.submit();
	});

	// The site-wide card posts its own forms rather than driving the drawer, so
	// its irreversible button asks here instead of in the handler above.
	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-confirm");
		if (!button) { return; }
		if (!window.confirm(button.dataset.confirm)) { e.preventDefault(); }
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
	// The team devices sheet has no secret and so no acknowledgement to wait for.
	var ack = document.getElementById("nc_ack");
	var closeButton = document.getElementById("nc_close");
	if (closeButton) {
		if (ack) {
			ack.addEventListener("change", function () { closeButton.classList.toggle("disabled", !this.checked); });
		}
		closeButton.addEventListener("click", function () {
			if (this.classList.contains("disabled")) { return; }
			document.getElementById("api_secret_sheet").remove();
		});
	}

})();
</script>' . output_footer();
