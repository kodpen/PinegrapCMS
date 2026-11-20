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
require_once(dirname(__FILE__) . '/includes/api/app_access.php');
require_once(dirname(__FILE__) . '/includes/api/app_templates.php');
require_once(dirname(__FILE__) . '/includes/api/app_new_view.php');
require_once(dirname(__FILE__) . '/includes/api/app_permissions_view.php');
require_once(dirname(__FILE__) . '/includes/api/app_guides.php');
require_once(dirname(__FILE__) . '/includes/api/app_drawer_view.php');
require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/schema.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');
require_once(dirname(__FILE__) . '/includes/api/resources/webhooks.php');
require_once(dirname(__FILE__) . '/includes/fn/automation_contract.php');
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
	$api_settings_lists = pg_request_unexpected_lists($_POST, array('scope' => 1, 'hook_events' => 1));

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

			// The starting point picked in the sheet's first step decides the
			// kind; its card is read again here, not trusted from the form.
			// Only its kind and the automation note follow from it: the rows
			// it ticked come in as the posted permissions.
			$start_cards = api_app_templates(api_scope_groups(), array(), array());

			$start = api_app_template_posted(isset($_POST['start']) ? $_POST['start'] : '', $start_cards);

			if ($start !== '') {
				$kind = $start_cards[$start]['kind'];
			}

			// The sheet's Advanced part: the same checks as the drawer's
			// Security tab, before anything is written.
			$too_long = api_app_text_too_long(array(
				'name'         => $liveform->get_field_value('name'),
				'description'  => isset($_POST['description']) ? $_POST['description'] : '',
				'ip_allowlist' => isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : ''
			));

			$bad_ip = api_app_ip_allowlist_bad(isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : '');

			$rate = api_app_rate_limit_in(isset($_POST['rate_limit_per_min']) ? $_POST['rate_limit_per_min'] : '');

			if (($too_long !== '') || ($bad_ip !== '') || ($rate === false)) {

				if ($too_long === 'name') {
					$liveform->add_error(lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => 190)));
				} elseif ($too_long === 'description') {
					$liveform->add_error(lang(array('string' => 'The description can be at most {var:1} characters long.', 'vars' => 500)));
				} elseif ($too_long !== '') {
					$liveform->add_error(lang(array('string' => 'The list of allowed IP addresses can be at most {var:1} characters long. Use a range (CIDR) to cover several addresses.', 'vars' => 500)));
				}

				if ($bad_ip !== '') {
					$liveform->add_error(lang(array('string' => 'Not an IP address or range: {var:1}', 'vars' => h($bad_ip))));
				}

				if ($rate === false) {
					$liveform->add_error(lang('The rate limit is a whole number of requests a minute, from 1 to 6000.'));
				}

				header('Location: ' . $screen_url);

				exit();

			}

			// The site serves one OAuth application, the oldest active one
			// (pg_oauth_app()). A second would be listed with scopes no
			// connection ever gets, so it is refused and the existing one
			// named instead.
			$oauth_refusal = '';

			if ($kind === 'oauth') {

				$oauth_refusal = api_app_oauth_create_refusal($kind, (string)db_value("SELECT name FROM api_apps
					WHERE kind = 'oauth' AND status = 'active'
					ORDER BY id ASC
					LIMIT 1"));

			}

			if ($oauth_refusal !== '') {

				$liveform->add_error($oauth_refusal);

				header('Location: ' . $screen_url);

				exit();

			}

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

			$ip_allowlist = isset($_POST['ip_allowlist']) ? trim((string)$_POST['ip_allowlist']) : '';

			if (($ip_allowlist !== '') || ($rate !== null)) {

				db("UPDATE api_apps SET
						ip_allowlist = '" . escape($ip_allowlist) . "'
						" . (($rate === null) ? '' : ", rate_limit_per_min = '" . (int)$rate . "'") . "
					WHERE id = '" . (int)$made['id'] . "'");

			}

			// The one and only time this pair is readable. It is handed to the
			// next request through the session rather than the query string,
			// because a query string is written to the server's access log.
			$_SESSION['software']['api_new_credentials'] = array(
				'name'   => $liveform->get_field_value('name'),
				'key'    => $made['key'],
				'secret' => $made['secret'],
				'kind'   => $made['kind'],
				// The starting point is not stored: it only adds the note
				// about an automation platform to this one sheet.
				'automation' => (($made['kind'] === 'server') && ($start === 'automation')),
				// The guide step 3 opens with, and the key as one token.
				'start'  => $start,
				'token'  => api_app_token($made['key'], $made['secret'])
			);

			header('Location: ' . $screen_url);

			exit();

		}

	} elseif ($action === 'update' && $app_id > 0) {

		$existing = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' LIMIT 1");

		// The drawer's Name box sits on a tab that may be hidden at Save, so
		// a blank name is refused here rather than by the browser.
		$name = isset($_POST['name']) ? (is_string($_POST['name']) ? trim($_POST['name']) : '') : ($existing ? $existing['name'] : '');

		if ($existing && $name === '') {

			$liveform->add_error(lang('Application Name is a required field!'));

			header('Location: ' . $screen_url);

			exit();

		}

		// A value longer than its column would be cut by MySQL without an
		// error; for the IP allow list a cut range can admit every address
		// (api_app_text_too_long()). Nothing is written.
		$too_long = $existing ? api_app_text_too_long(array(
			'name'         => $name,
			'description'  => isset($_POST['description']) ? $_POST['description'] : '',
			'ip_allowlist' => isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : ''
		)) : '';

		if ($too_long !== '') {

			if ($too_long === 'name') {
				$liveform->add_error(lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => 190)));
			} elseif ($too_long === 'description') {
				$liveform->add_error(lang(array('string' => 'The description can be at most {var:1} characters long.', 'vars' => 500)));
			} else {
				$liveform->add_error(lang(array('string' => 'The list of allowed IP addresses can be at most {var:1} characters long. Use a range (CIDR) to cover several addresses.', 'vars' => 500)));
			}

			header('Location: ' . $screen_url);

			exit();

		}

		// An allow list entry that is no address or range, or a rate limit
		// that is no whole number from 1 to 6000, is refused by name rather
		// than stored: the first would refuse every call made with the key.
		$bad_ip = $existing ? api_app_ip_allowlist_bad(isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : '') : '';
		$rate = $existing ? api_app_rate_limit_in(isset($_POST['rate_limit_per_min']) ? $_POST['rate_limit_per_min'] : '') : null;

		if (($bad_ip !== '') || ($rate === false)) {

			if ($bad_ip !== '') {
				$liveform->add_error(lang(array('string' => 'Not an IP address or range: {var:1}', 'vars' => h($bad_ip))));
			}

			if ($rate === false) {
				$liveform->add_error(lang('The rate limit is a whole number of requests a minute, from 1 to 6000.'));
			}

			header('Location: ' . $screen_url);

			exit();

		}

		if ($existing) {

			$audit_before = pg_audit_fetch('api_apps', $app_id);

			$status = (isset($_POST['status']) && in_array($_POST['status'], array('active', 'disabled'), true))
				? $_POST['status']
				: 'disabled';

			$expires = 0;

			if (isset($_POST['expires_at']) && trim($_POST['expires_at']) !== '') {

				$parsed = strtotime(trim($_POST['expires_at']) . ' 23:59:59');

				if ($parsed !== false) { $expires = (int)$parsed; }

			}

			db("UPDATE api_apps SET
					name = '" . escape($name) . "',
					description = '" . escape(isset($_POST['description']) ? $_POST['description'] : '') . "',
					scopes = '" . escape(json_encode($posted_scopes)) . "',
					ip_allowlist = '" . escape(isset($_POST['ip_allowlist']) ? $_POST['ip_allowlist'] : '') . "',
					status = '" . escape($status) . "',
					expires_at = '" . $expires . "',
					" . (($rate === null) ? '' : "rate_limit_per_min = '" . (int)$rate . "',") . "
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
					'kind'    => 'server',
					'token'   => api_app_token($rotated['key'], $rotated['secret']),
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
				? lang(array('string' => 'Deliveries to {var:1} were switched back on.', 'vars' => h(pg_ops_alert_mask_url($hook['url']))))
				: lang(array('string' => 'Deliveries to {var:1} were stopped.', 'vars' => h(pg_ops_alert_mask_url($hook['url'])))));

			log_activity(lang(array(
				'string' => 'API event subscription ({var:1}) was set to {var:2}.',
				'vars'   => array(pg_ops_alert_mask_url($hook['url']), $new_status)
			)), $_SESSION['sessionusername']);

			// The Webhooks tile on System Status reads these rows.
			pg_system_status_forget();

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

			// The Webhooks tile on System Status reads these rows.
			pg_system_status_forget();

			$liveform->add_notice(lang(array(
				'string' => 'Waiting events for {var:1} will be sent again on the next run of the scheduled task.',
				'vars'   => h(pg_ops_alert_mask_url($hook['url']))
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

			$liveform->add_notice(lang(array('string' => 'The subscription to {var:1} was removed.', 'vars' => h(pg_ops_alert_mask_url($hook['url'])))));

			log_activity(lang(array(
				'string' => 'API event subscription ({var:1}) was removed.',
				'vars'   => pg_ops_alert_mask_url($hook['url'])
			)), $_SESSION['sessionusername']);

		}

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'webhook_create' && $app_id > 0) {

		// "New subscription" on the Events tab: an address a platform gives
		// for incoming webhooks, subscribed with the checks, the limit and the
		// row of POST /webhooks (api_webhook_create_check(),
		// api_webhook_store()). What comes back opens the drawer on that tab
		// again: the form with its error, or the secret once.
		$owner_app = db_item("SELECT id, name FROM api_apps WHERE id = '" . $app_id . "' AND kind = 'server' LIMIT 1");

		$hook_url = isset($_POST['hook_url']) ? (string)$_POST['hook_url'] : '';

		$hook_events = isset($_POST['hook_events']) ? (array)$_POST['hook_events'] : array();

		$back = array('app_id' => $app_id);

		if (!$owner_app) {

			$back['form'] = array('url' => '', 'events' => array(), 'error' => lang('That application could not be found.'));

		} else {

			$checked = api_webhook_create_check($hook_url, $hook_events, api_webhook_held_count($app_id));

			$made = ($checked['refusal'] === null)
				? api_webhook_store($app_id, $checked['url'], $checked['events'])
				: null;

			if ($made === null) {

				$back['form'] = array(
					'url'    => mb_substr(trim($hook_url), 0, 2000),
					'events' => array_values(array_filter(array_map('strval', array_filter($hook_events, 'is_scalar')))),
					'error'  => ($checked['refusal'] !== null) ? $checked['refusal']['message'] : lang('The subscription could not be created.')
				);

			} else {

				// The plain secret goes to the next request only, through the
				// session as an application's own secret does.
				$back['secret'] = array(
					'id'     => $made['id'],
					'host'   => pg_ops_alert_mask_url($checked['url']),
					'secret' => $made['secret']
				);

				log_activity(lang(array(
					'string' => 'API event subscription ({var:1}) was created on the panel for the application {var:2}.',
					'vars'   => array(pg_ops_alert_mask_url($checked['url']), $owner_app['name'])
				)), $_SESSION['sessionusername']);

				// The Webhooks tile on System Status reads these rows.
				pg_system_status_forget();

			}

		}

		$_SESSION['software']['api_hooks_back'] = $back;

		header('Location: ' . $screen_url);

		exit();

	} elseif ($action === 'webhook_sample' && $app_id > 0) {

		// "Send a sample": one of the subscription's own events, its sample
		// queued for this subscription alone with the refusals of POST
		// /webhooks/{id}/test (api_webhook_test_refusal()). Never announced,
		// so no flow, channel or other subscriber hears it.
		$webhook_id = isset($_POST['webhook_id']) ? (int)$_POST['webhook_id'] : 0;

		$hook = db_item("SELECT id, url, events, status FROM api_webhooks
			WHERE id = '" . $webhook_id . "' AND app_id = '" . $app_id . "' LIMIT 1");

		$event = isset($_POST['sample_event']) ? trim((string)$_POST['sample_event']) : '';

		$back = array('app_id' => $app_id);

		if ($hook && ($event !== '')) {

			$payloads = api_webhook_event_payloads();

			$refusal = api_webhook_test_refusal($hook, $event, $payloads);

			$queued = ($refusal === null) ? api_webhook_test_queue((int)$hook['id'], $event, $payloads[$event]['sample']) : null;

			$back['flash'] = array(
				'webhook_id' => (int)$hook['id'],
				'ok'         => ($queued !== null),
				'text'       => ($queued !== null)
					? lang(array('string' => 'The sample of {var:1} was queued; the scheduled task sends it to {var:2}.', 'vars' => array($event, pg_ops_alert_mask_url($hook['url']))))
					: (($refusal !== null) ? $refusal['message'] : lang('The sample could not be queued.'))
			);

		}

		$_SESSION['software']['api_hooks_back'] = $back;

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

			// And its answers to their writers (ws_app_answers).
			if (pg_schema_has('ws_app_answers')) {

				db("DELETE FROM ws_app_answers WHERE app_id = '" . $app_id . "'");

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

// What the Events tab's form or "Send a sample" handed over: the drawer opens
// on that tab again with the form and its error, the new subscription's
// secret (shown once) or the sample's line. Read once and removed.
$hooks_back = null;

if (isset($_SESSION['software']['api_hooks_back'])) {

	$hooks_back = $_SESSION['software']['api_hooks_back'];

	unset($_SESSION['software']['api_hooks_back']);

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

// The most recent calls and errors of each application, for the drawer's log
// tab: one round trip, a bounded subquery per application on the
// (app_id, timestamp) index (api_app_log_sql()), so one busy application does
// not empty the others' lists.
$log_rows = array();

$log_sql = api_app_log_sql(array_map(function ($app) { return $app['id']; }, $apps), 15);

if ($log_sql !== '') {

	$recent = db_items($log_sql);

	if (is_array($recent)) {

		$log_rows = api_app_log_group($recent, 'api_app_log_time_label');

	}

}

// The OAuth application the site serves (pg_oauth_app()); any other active
// OAuth row is shown as not in use.
$oauth_served_id = api_app_oauth_served_id($apps, time());

/* ---------------------------------------------------------------------------
   Presentation helpers
   --------------------------------------------------------------------------- */

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
				'since'    => get_absolute_time(array('timestamp' => (int)$row['created_timestamp'], 'type' => 'date', 'format' => 'plain_text')),
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

	if ($seconds < 3600) {

		$count = (int)($seconds / 60);

		return lang(array('string' => '{var:1} minute{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

	}

	if ($seconds < 86400) {

		$count = (int)($seconds / 3600);

		return lang(array('string' => '{var:1} hour{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

	}

	$count = (int)($seconds / 86400);

	return lang(array('string' => '{var:1} day{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

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

		// Scheme and host only: an address a platform gives carries its
		// secret in the path, and only the form that stores it shows it whole.
		$row = array(
			'id'           => (int)$hook['id'],
			'url'          => pg_ops_alert_mask_url($hook['url']),
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

// The status column's Summary: how many applications are in each state and
// what all of them asked for in the last 24 hours.
$summary_counts = array('on' => 0, 'off' => 0, 'expired' => 0, 'unused' => 0);

$summary_requests = 0;

$summary_failures = 0;

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

	$oauth_unused = api_app_oauth_unused($app, $oauth_served_id);

	if ($oauth_unused && ($status_class !== 'warn')) {

		$status_class = 'warn';

		$status_label = lang('Not in use');

	}

	if ($status_class === 'warn') {

		$summary_counts[($status_label === lang('Not in use')) ? 'unused' : 'expired']++;

	} else {

		$summary_counts[$status_class]++;

	}

	$summary_requests += $stats['requests'];

	$summary_failures += $stats['failures'];

	$signed_in_number = isset($device_rows[(int)$app['id']]) ? count($device_rows[(int)$app['id']]) : 0;

	$app_rows .= '
	<div class="api-row" data-app="' . (int)$app['id'] . '" role="button" tabindex="0">
		<div class="api-name">
			<i class="bi ' . ($is_oauth ? 'bi-plug' : ($is_device ? 'bi-phone' : 'bi-key')) . '"></i>
			<div>
				<b>' . h($app['name']) . '</b>
				' . ($is_oauth
					? '<code>' . lang('Connected apps (OAuth)') . ' &middot; ' . ($oauth_unused
						? lang('Not in use: the oldest OAuth app applies')
						: lang(array('string' => '{var:1} connection{suffix:1}', 'vars' => pg_format_number($signed_in_number, 0), 'suffix' => ($signed_in_number == 1) ? '' : 's'))) . '</code>'
					: ($is_device
					? '<code>' . lang('Team devices') . ' &middot; ' . lang(array('string' => '{var:1} device{suffix:1}', 'vars' => pg_format_number($signed_in_number, 0), 'suffix' => ($signed_in_number == 1) ? '' : 's')) . '</code>'
					: '<code>' . h(substr($app['api_key'], 0, 16)) . '&hellip;</code>')) . '
			</div>
		</div>
		<div class="api-chips">' . $chips . '</div>
		<div><span class="api-status ' . $status_class . '">' . h($status_label) . '</span></div>
		<div class="api-meta">' . h(api_settings_ago($app['last_used_timestamp'])) . '
			<small>' . ($app['last_used_ip'] !== '' ? h($app['last_used_ip']) : '&mdash;') . '</small></div>
		<div class="api-meta">' . ($stats['requests'] > 0 ? lang(array('string' => '{var:1} request{suffix:1}', 'vars' => pg_format_number($stats['requests'], 0), 'suffix' => ($stats['requests'] == 1) ? '' : 's')) : '&mdash;') . '
			<small' . ($stats['failures'] > 0 ? ' class="bad"' : '') . '>'
			. ($stats['failures'] > 0 ? lang(array('string' => '{var:1} error{suffix:1}', 'vars' => pg_format_number($stats['failures'], 0), 'suffix' => ($stats['failures'] == 1) ? '' : 's')) : '&mdash;') . '</small></div>
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
		'created'     => get_absolute_time(array('timestamp' => (int)$app['created_timestamp'], 'type' => 'date', 'format' => 'plain_text')),
		'status'      => $app['status'],
		'ip'          => $app['ip_allowlist'],
		'expires'     => ((int)$app['expires_at'] > 0) ? date('Y-m-d', (int)$app['expires_at']) : '',
		'rate'        => (int)$app['rate_limit_per_min'],
		'scopes'      => $scope_choices,
		'has_secret'  => ($app['api_secret_hash'] !== ''),
		'kind'        => $is_oauth ? 'oauth' : ($is_device ? 'device' : 'server'),
		'locked'      => api_app_row_locked($app['owner_role'], $user['role']),
		'devices'     => isset($device_rows[(int)$app['id']]) ? $device_rows[(int)$app['id']] : array(),
		'unused'      => $oauth_unused,
		'log'         => isset($log_rows[(int)$app['id']]) ? $log_rows[(int)$app['id']]['all'] : array(),
		'log_errors'  => isset($log_rows[(int)$app['id']]) ? $log_rows[(int)$app['id']]['errors'] : array(),
		'webhooks'    => isset($webhook_rows[(int)$app['id']]) ? $webhook_rows[(int)$app['id']] : array(),
		'used'        => api_settings_ago($app['last_used_timestamp']),
		'state'       => $status_label,
		'state_tone'  => ($status_class === 'on') ? 'teal' : (($status_class === 'warn') ? 'amber' : 'gray'),
		'errors'      => (int)$stats['failures']
	);

}

if ($app_rows === '') {

	$app_rows = '<div class="api-empty">' . lang('No application yet. Create one to let an outside system reach this site.') . '</div>';

}

$api_base_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/integration.php';

// The site's address, the first of the three values an integration's secret
// sheet gives: a client finds the API from it through
// /.well-known/pinegrap.json.
$site_address = rtrim(URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH, '/');

$permission_rows = api_settings_permission_rows('scope', 'd_scope');

// The connection guides, built from this site's addresses. The MCP address
// given is the server's own file, which answers on every server; the compact
// list is named for AI assistants.
$mcp_addresses = pg_oauth_resource_addresses();

$mcp_compact = pg_oauth_mcp_compact_addresses();

$guides = api_app_guides(array(
	'site'        => $site_address,
	'api'         => $api_base_url,
	'docs'        => 'api_docs.php',
	'mcp'         => $mcp_addresses['mcp'][1],
	'mcp_compact' => $mcp_compact[1],
	'claude_link' => pg_oauth_claude_connect_link($mcp_addresses['mcp'][1], defined('ORGANIZATION_NAME') ? (string)ORGANIZATION_NAME : ''),
	'start_text'  => function_exists('ws_agent_start_text') ? ws_agent_start_text($api_base_url) : ''
));

// New application's "Automation platform" starting point: the rows it ticks
// (the contract's scopes, api_app_start_choices()) and the line naming them.
// Nothing about the choice is stored; the application is an integration.
$start_choices = api_app_start_choices(pg_automation_contract_scopes(), api_scope_groups());

$start_names = array();

foreach ($start_choices as $group_key => $choice) {

	$group_text = api_settings_group_text($group_key);

	$start_names[] = $group_text[0] . ' (' . (($choice === 'write')
		? (($group_key === 'webhooks') ? lang('Manage') : lang('Write'))
		: lang('Read')) . ')';

}

$start_note = lang(array(
	'string' => 'The {var:1} permissions the platform uses are ticked: {var:2}. They can be changed; a permission left unticked turns that step off on the platform.',
	'vars'   => array(pg_format_number(count($start_choices), 0), implode(', ', $start_names))
));

// The starting points of New application's sheet. The sign-in card opens the
// OAuth application the site serves when there is one.
$start_oauth_app = null;

foreach ($apps as $listed_app) {

	if ((int)$listed_app['id'] === (int)$oauth_served_id) {
		$start_oauth_app = array('id' => (int)$listed_app['id'], 'name' => $listed_app['name']);
	}

}

$start_cards = api_app_templates(api_scope_groups(), pg_automation_contract_scopes(), api_app_money_scopes(api_schema()), $start_oauth_app);

// The Events tab's "New subscription": every event of the catalogue with its
// module and its line, for the list the form searches.
$hook_catalogue = array();

// api_webhook_events() loads the module list the module names are read from.
$hook_events_all = api_webhook_events();

$hook_event_modules = api_webhook_event_modules();

$hook_module_names = array('erp' => lang('ERP'), 'workspace' => lang('Workspace'));

foreach ($hook_events_all as $event_name => $event_text) {

	$event_module = isset($hook_event_modules[$event_name]) ? $hook_event_modules[$event_name] : '';

	$hook_catalogue[] = array(
		'name'   => (string)$event_name,
		'module' => api_app_event_area($event_name, isset($hook_module_names[$event_module]) ? $hook_module_names[$event_module] : ''),
		'text'   => lang((string)$event_text)
	);

}

$hook_bounds = api_webhook_create_bounds();

// The new subscription's signing secret, once: drawn into the Events tab and
// taken out of the page as soon as another tab or application is opened.
$hook_secret_html = '';

if (is_array($hooks_back) && isset($hooks_back['secret']['secret'])) {

	$hook_secret_html = '
				<div class="alert theme-success py-3 px-5 small mb-5" id="d_hook_secret" role="status">
					<b><i class="bi bi-check-circle me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Subscription created: {var:1}', 'vars' => $hooks_back['secret']['host']))) . '</b>
					<div class="mb-3">' . lang('The signing secret is shown only now; once you leave this tab it is not shown again.') . '</div>
					<span class="visually-hidden" id="d_hook_secret_label">' . lang('Signing secret') . '</span>
					<div class="api-secret-box"><code id="d_hook_secret_value">' . h($hooks_back['secret']['secret']) . '</code>
						' . api_settings_copy_button('d_hook_secret_value', 'd_hook_secret_label', 'pg-button pg-button-quiet pg-button-sm') . '</div>
				</div>';

}

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
							class="pg-button pg-button-quiet pg-button-sm">' . lang('Send again') . '</button>'
						: '') . '
					<button type="submit" name="api_action" value="webhook_status"
						class="pg-button pg-button-quiet pg-button-sm">'
						. (($hook['status'] === 'disabled') ? lang('Resume') : lang('Stop')) . '</button>
					<button type="submit" name="api_action" value="webhook_delete"
						class="pg-button pg-button-danger pg-button-sm api-confirm"
						data-confirm="' . h(lang('The subscription will be removed and the application will stop being told.')) . '">'
						. lang('Remove') . '</button>
				</form>') . '
			</div>';

	}

	$webhook_card = '
	<div class="card mt-5" id="events">
		<div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
			<h2 class="pg-title-section mb-0">' . lang('Event notifications') . '</h2>
			<span class="ms-auto pg-text-2 d-none lg:d-inline">'
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
			lang(array('string' => '{var:1} connection{suffix:1}', 'vars' => pg_format_number((int)$client['connections'], 0), 'suffix' => ((int)$client['connections'] == 1) ? '' : 's')),
			lang('Last used') . ' ' . api_settings_ago($client['last_used_timestamp']),
			lang(array('string' => 'Registered on {var:1}', 'vars' => get_absolute_time(array('timestamp' => (int)$client['created_timestamp'], 'type' => 'date', 'format' => 'plain_text'))))
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
						class="pg-button pg-button-quiet pg-button-sm">' . h($client_on ? lang('Turn off') : lang('Turn on')) . '</button>
					<button type="submit" name="api_action" value="oauth_client_delete"
						class="pg-button pg-button-danger pg-button-sm api-confirm"
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
		<div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
			<h2 class="pg-title-section mb-0">' . lang('Connected applications') . '</h2>
			<span class="ms-auto pg-text-2 d-none lg:d-inline">'
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
	'extra classes'        => 'api-access pg-measure-edit',
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
   this screen is scanning them side by side. 45rem (720px at the default text
   size) is what the list column beside the status column holds on a 1280px
   screen without a scrollbar; in rem the threshold grows with the text. The
   last column holds only the chevron icon and stays px. */
.api-scroll { overflow-x: auto; overflow-y: hidden; }
.api-head, .api-row { display: grid; grid-template-columns: 1.9fr 1.5fr .85fr 1.15fr .9fr 34px;
	gap: 14px; align-items: center; min-width: 45rem; }
.api-head { padding: 0 16px 8px; font-size: var(--pg-text-meta); font-weight: var(--pg-weight-strong); color: var(--pg-fg-2); }
.api-row { padding: 13px 16px; border-top: 1px solid var(--pg-border-color); cursor: pointer; }
.api-row:hover, .api-row:focus-visible { background: var(--pg-bg-1); }
/* Inset: .api-scroll clips a ring drawn outside the row. */
.api-row:focus-visible { outline: 2px solid var(--pg-primary-ink); outline-offset: -2px; }
.api-name { display: flex; align-items: center; gap: 9px; min-width: 0; }
.api-name i { opacity: .7; }
.api-name b { font-size: var(--pg-text-2); display: block; }
/* Secondary text colour rather than opacity: faded chips and codes read
   under 3:1 on the light card. */
.api-name code { font-size: var(--pg-text-count); color: var(--pg-fg-2); }
.api-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.api-chip { font-size: var(--pg-text-count); padding: 2px 6px; border-radius: 5px; border: 1px solid var(--pg-border-color); }
.api-chip.w { border-color: var(--pg-primary); color: var(--pg-primary-fg-emphasis); }
.api-chip.muted { color: var(--pg-fg-2); }
.api-status { font-size: var(--pg-text-count); padding: 3px 9px; border-radius: 20px; font-weight: var(--pg-weight-strong);
	border: 1px solid var(--pg-border-color); display: inline-block; }
.api-status.on { color: var(--pg-success-fg-emphasis); border-color: color-mix(in srgb, var(--pg-success) 35%, transparent); }
.api-status.off { color: var(--pg-fg-2); }
.api-status.warn { color: var(--pg-warning-fg-emphasis); border-color: color-mix(in srgb, var(--pg-warning) 35%, transparent); }
.api-meta { font-size: var(--pg-text-meta); color: var(--pg-fg-2); }
.api-meta small { display: block; font-size: var(--pg-text-count); color: var(--pg-fg-2); }
.api-meta small.bad { color: var(--pg-danger-fg-emphasis); }
.api-empty { padding: 26px 16px; text-align: center; color: var(--pg-fg-2); border-top: 1px solid var(--pg-border-color); }

/* One permission per resource, three positions. The row carries the sentence
   that says what it reaches, because "Orders: write" on its own does not. */
.api-perm { display: flex; align-items: center; gap: 12px; padding: 13px 2px;
	border-top: 1px solid var(--pg-border-color); }
.api-perm:first-of-type { border-top: 0; }
/* An icon glyph, not text: it keeps its px size. */
.api-perm > i { font-size: 15px; width: 18px; text-align: center; opacity: .7; }
.api-perm-text { flex: 1; min-width: 0; }
.api-perm-text b { display: block; font-size: var(--pg-text-meta); }
.api-perm-text span { display: block; font-size: var(--pg-text-count); color: var(--pg-fg-2); margin-top: 1px; }
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
.api-seg .btn { --bs-btn-font-size: var(--pg-text-count); --bs-btn-padding-y: .25rem; --bs-btn-padding-x: .6rem;
	--bs-btn-color: var(--pg-fg-2); --bs-btn-border-color: var(--pg-border-color);
	--bs-btn-hover-border-color: var(--pg-border-color); }
.api-seg .btn-check:checked + .btn { color: var(--pg-on-accent); background: var(--pg-primary); border-color: var(--pg-primary); }
/* Write implies read, so the read position shows as held-open rather than
   inviting a click that would silently do nothing. A tint with the primary
   border, not the fill faded: fading the whole button fades its ink too, and
   in the dark theme dark ink at partial opacity sinks into the background. */
.api-seg .btn.implied { color: var(--pg-primary-fg-emphasis); background: var(--pg-primary-bg-subtle);
	border-color: var(--pg-primary); }
.api-seg-none { display: inline-flex; align-items: center; justify-content: center; min-width: 2.125rem;
	font-size: var(--pg-text-count); opacity: .3; border: 1px solid var(--pg-border-color); border-left: 0; }
.api-log { display: grid; grid-template-columns: 2.875rem 1fr auto auto auto; gap: 9px; align-items: center;
	padding: 8px 2px; border-top: 1px solid var(--pg-border-color); font-size: var(--pg-text-meta); }
.api-log:first-child { border-top: 0; }
.api-log .m { font-size: var(--pg-text-count); font-weight: var(--pg-weight-strong); text-align: center; border-radius: 4px; padding: 2px 0;
	background: var(--pg-bg-1); }
.api-log code { font-size: var(--pg-text-count); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.api-log .s { font-size: var(--pg-text-count); font-weight: var(--pg-weight-strong); }
.api-log .s.ok { color: var(--pg-success-fg-emphasis); }
.api-log .s.bad { color: var(--pg-danger-fg-emphasis); }
.api-log .d, .api-log .t { font-size: var(--pg-text-count); color: var(--pg-fg-2); }
.api-hook { border-top: 1px solid var(--pg-border-color); padding: 11px 2px; }
.api-hook:first-child { border-top: 0; }
.api-hook-top { display: flex; align-items: center; gap: 8px; }
.api-hook-url { flex: 1; min-width: 0; font-size: var(--pg-text-meta); word-break: break-all; }
.api-hook-events { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.api-hook-meta { font-size: var(--pg-text-count); opacity: .65; margin-top: 6px; }
/* A blocked subscription prints its line in the danger ink; faded, it reads
   3:1 in the light theme. */
.api-hook-meta.fg-danger { opacity: 1; }
.api-hook-error { font-size: var(--pg-text-count); color: var(--pg-danger-fg-emphasis); margin-top: 5px; word-break: break-word; }
.api-hook-acts { display: flex; gap: 5px; margin-top: 8px; }
.api-secret-box { display: flex; align-items: center; gap: 8px; background: var(--pg-bg-1);
	border: 1px solid var(--pg-border-color); border-radius: 8px; padding: 9px 11px; }
.api-secret-box code { flex: 1; font-size: var(--pg-text-meta); word-break: break-all; }
.api-guide { background: var(--pg-bg-1); border: 1px solid var(--pg-border-color); border-radius: 8px;
	padding: 10px 12px; margin-bottom: 10px; font-size: var(--pg-text-meta); }
.api-guide-title { font-weight: var(--pg-weight-strong); margin-bottom: 6px; }
.api-guide ol, .api-guide ul { padding-left: 18px; }
.api-guide li + li { margin-top: 4px; }
.api-client + .api-client { margin-top: 4px; }
.api-client summary { cursor: pointer; font-weight: var(--pg-weight-medium); }
.api-client[open] summary { margin-bottom: 4px; }
.api-snippet code { white-space: pre; overflow-x: auto; word-break: normal; }
.api-danger-zone { border-top: 1px solid var(--pg-border-color); margin-top: 18px; padding-top: 14px; }
/* The site chat launcher is fixed at z-index 1080, an offcanvas is 1045, so the
   bubble sits on top of whatever the drawer puts in its bottom right corner -
   which here is Save. Hidden while the drawer is open rather than moved: the
   drawer is a modal context, and a chat bubble floating over the button that
   commits the form is not something to nudge sideways. */
body.api-drawer-open .pg-chat-launcher { display: none !important; }
/* On a phone the drawer is a sheet from the bottom, not the full height. */
#api_drawer.drawer-bottom { --pg-drawer-height: 88vh; }
/* Step 3's one-time values: the name column of each row. */
.api-cred-name { flex: 0 0 9.5rem; min-width: 0; }
/* A guide that does not suit the application: its name in the secondary
   ink and its tile grey, still readable; the reason is the first line inside. */
.api-guide-off .pg-set-fold-name { color: var(--pg-fg-2); }
.api-guide-off .pg-icon-tile { --pg-tone: var(--pg-tone-gray); }
#api_drawer.api-locked .api-hook-acts,
#api_drawer.api-locked .api-hook-new,
#api_drawer.api-locked .api-hook-form,
#api_drawer.api-locked .api-hook-sample { display: none; }
/* New application\'s starting points: two cards a row from md up, each with
   its icon tile beside the title. */
.api-start { display: grid; gap: 8px; }
@media (min-width: 768px) { .api-start { grid-template-columns: 1fr 1fr; } }
.api-start .form-field-card { margin-bottom: 0; }
.api-start-head { display: flex; align-items: center; gap: 8px; }
.api-start-head .pg-pill { margin-left: auto; }
.api-start-acts { margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--pg-border-color);
	font-size: var(--pg-text-count); }
.api-start-acts .bi { margin-right: 4px; }
.api-new-advanced { border: 1px solid var(--pg-border-color); border-radius: 8px; padding: 10px 12px; }
.api-new-advanced summary { cursor: pointer; }
/* The Events tab\'s "New subscription" and "Send a sample" boxes. */
.api-hook-form, .api-hook-sample { background: var(--pg-bg-1); border: 1px solid var(--pg-border-color);
	border-radius: 8px; padding: 10px 12px; }
.api-hook-sample { margin-top: 8px; }
.api-hook-address { word-break: break-all; font-family: var(--pg-font-mono); }
.api-event-list { max-height: 18rem; overflow-y: auto; border: 1px solid var(--pg-border-color);
	border-radius: 8px; background: var(--pg-bg-body); }
.api-event { display: flex; gap: 8px; align-items: flex-start; padding: 7px 10px; margin: 0;
	border-top: 1px solid var(--pg-border-color); cursor: pointer; }
.api-event:first-child { border-top: 0; }
.api-event .pg-checkbox { margin-top: 2px; flex: none; }
.api-event-name { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.api-event-text { display: block; color: var(--pg-fg-2); }
.api-event mark { padding: 0; background: var(--pg-warning-bg-subtle); color: inherit; }
.api-event-none { padding: 10px; color: var(--pg-fg-2); }
.api-chip-remove { border: 0; background: none; padding: 0 0 0 4px; color: inherit; line-height: 1; }
</style>

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
	</div></div>

	' . pg_screen_toolbar(array(
		'primary'   => '<button type="button" class="pg-button pg-button-fill" data-bs-toggle="drawer" data-bs-target="#api_new"><i class="bi bi-key" aria-hidden="true"></i>' . lang('New Application') . '</button>',
		'secondary' => array(
			array('label' => lang('API Documentation'), 'url' => 'api_docs.php', 'icon' => 'bi-file-earmark-text'),
			array('label' => lang('API Settings'), 'url' => 'settings_api.php', 'icon' => 'bi-sliders'),
		),
		'search'    => '<div class="input-group input-group-sm pg-toolbar-search" style="max-width:280px">
			<span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
			<input type="text" class="form-control" id="api_filter" aria-label="' . h(lang('Search application')) . '" placeholder="' . lang('Search application') . '" autocomplete="off">
		</div>
		<div class="visually-hidden" id="api_filter_status" role="status"
			data-found="' . h(lang(array('string' => '{var:1} found', 'vars' => '{1}'))) . '"
			data-none="' . h(lang('Nothing found.')) . '"></div>',
	), array('class' => 'mb-5')) . '

	<div class="row">
	<div class="col-12 lg:col-8 2xl:col-9 order-1">
	<div class="card">
		<h2 class="card-header bg-reset border-0 pg-title-section">' . lang('Applications') . '</h2>
		<div class="card-body px-3 py-5">
			<div class="api-scroll">
			<div class="api-head">
				<span>' . lang('Application') . '</span><span>' . lang('Permissions') . '</span>
				<span>' . lang('Status') . '</span><span>' . lang('Last used') . '</span>
				<span>' . lang('24 hours') . '</span><span></span>
			</div>
			<div id="api_rows">' . $app_rows . '</div>
			<div class="api-empty d-none" id="api_filter_empty">' . h(lang('Nothing found.')) . '</div>
			</div>
		</div>
	</div>

	' . $oauth_clients_card . '

	' . $webhook_card . '
	</div>

	<!-- Status column: what is read, not filled. Written after the list so that
	     on a narrow screen the applications come first. -->
	<div class="col-12 lg:col-4 2xl:col-3 order-2">
		<div class="position-sticky mt-5 lg:mt-0" style="top:3.3rem;">
			<div class="card" id="api_summary">
				<h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Summary')) . '</h2>
				<div class="card-body">
					<dl class="pg-kv mb-0">
						<dt>' . h(lang('Applications')) . '</dt>
						<dd>' . pg_format_number(count($apps), 0) . '</dd>
						<dt>' . h(lang('Active')) . '</dt>
						<dd>' . pg_format_number($summary_counts['on'], 0) . '</dd>
						<dt>' . h(lang('Disabled')) . '</dt>
						<dd>' . pg_format_number($summary_counts['off'], 0) . '</dd>
						<dt>' . h(lang('Expired')) . '</dt>
						<dd>' . pg_format_number($summary_counts['expired'], 0) . '</dd>'
						. ($summary_counts['unused'] > 0 ? '
						<dt>' . h(lang('Not in use')) . '</dt>
						<dd>' . pg_format_number($summary_counts['unused'], 0) . '</dd>' : '') . '
						<dt>' . h(lang('Requests in 24 hours')) . '</dt>
						<dd>' . pg_format_number($summary_requests, 0) . '</dd>
						<dt>' . h(lang('Errors in 24 hours')) . '</dt>
						<dd' . ($summary_failures > 0 ? ' class="fg-danger"' : '') . '>' . pg_format_number($summary_failures, 0) . '</dd>
						<dt>' . h(lang('Event notifications')) . '</dt>
						<dd>' . pg_format_number(count($webhook_all), 0) . '</dd>
					</dl>
				</div>
			</div>
			<p class="pg-text-2 mt-5 mb-2">
				<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
				. lang('Every application has its own key and secret. The secret is shown only when it is made.') . '
			</p>
			<p class="pg-text-2 mb-0">'
				. lang('The key is not shown in the list, only its prefix. The secret cannot be read back anywhere.') . '
			</p>
		</div>
	</div>
	</div>
</main>

' . api_app_drawer_view(array(
	'guides'          => $guides,
	'permission_rows' => $permission_rows,
	'hook_secret'     => $hook_secret_html,
	'hook_bounds'     => $hook_bounds,
	'fields_script'   => get_date_picker_format() . pg_fields_script()
)) . '

' . api_app_new_view($start_cards, api_settings_permission_rows('scope', 'n_scope'), $start_note, api_app_connect_step($new_credentials, $site_address, $guides, $start_cards));

echo '
<script>
(function () {

	var APPS = ' . json_encode($app_data, JSON_UNESCAPED_UNICODE) . ';

	var API_BASE = ' . json_encode($api_base_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
	// The drawer\'s head for each kind of application.
	var ACTS_LABEL = ' . json_encode(lang('Acts as:'), JSON_UNESCAPED_UNICODE) . ';
	var KIND_FACE = ' . json_encode(array(
		'server' => array('kind' => lang('Integration'), 'auth' => lang('key and secret'), 'acts' => api_app_acts_text('application'), 'tone' => 'blue', 'icon' => 'bi-key'),
		'oauth'  => array('kind' => lang('Connected apps (OAuth)'), 'auth' => lang('sign-in'), 'acts' => api_app_acts_text('person'), 'tone' => 'violet', 'icon' => 'bi-stars'),
		'device' => array('kind' => lang('Team devices'), 'auth' => lang('sign-in'), 'acts' => api_app_acts_text('person'), 'tone' => 'rose', 'icon' => 'bi-phone')
	), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
	// The Events tab: the catalogue the form searches, the limits of POST
	// /webhooks, and what the last post handed back (no secret: that is in
	// the page once, in the tab itself).
	var HOOK_EVENTS = ' . json_encode($hook_catalogue, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
	var HOOK_LIMIT = ' . (int)api_webhook_subscription_limit() . ';
	var HOOK_MAX_EVENTS = ' . (int)$hook_bounds['events'] . ';
	var HOOKS_BACK = ' . json_encode(is_array($hooks_back) ? array(
		'app_id' => (int)$hooks_back['app_id'],
		'form'   => isset($hooks_back['form']) ? $hooks_back['form'] : null,
		'flash'  => isset($hooks_back['flash']) ? $hooks_back['flash'] : null,
		'secret' => isset($hooks_back['secret'])
	) : null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
	// New application: the starting points, each with the rows it ticks.
	var START_CARDS = ' . json_encode(array_map(function ($card) { $card['choices'] = (object)$card['choices']; return $card; }, $start_cards), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
	var HOOK_TEXT = {};
	HOOK_EVENTS.forEach(function (e) { HOOK_TEXT[e.name] = e.text; });

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
	function renderHooks(hooks, flash) {
		var box = document.getElementById("d_hook_rows");
		document.getElementById("d_hook_count").textContent = hooks.length + " / " + HOOK_LIMIT;
		document.getElementById("d_hook_new").disabled = (hooks.length >= HOOK_LIMIT);
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
				((flash && flash.webhook_id === h.id)
					? "<div class=\'alert theme-" + (flash.ok ? "success" : "danger") + " py-3 px-5 small mt-3 mb-0\' role=\'status\'>" +
						"<i class=\'bi bi-" + (flash.ok ? "check2" : "exclamation-triangle") + " me-1\' aria-hidden=\'true\'></i>" + esc(flash.text) + "</div>"
					: "") +
				"<div class=\'api-hook-acts\'>" +
					((h.given_up || h.held || h.status === "failing")
						? "<button type=\'button\' class=\'pg-button pg-button-quiet pg-button-sm api-hook-act\' data-act=\'webhook_retry\' data-id=\'" + h.id + "\'>" +
							' . json_encode(lang('Send again'), JSON_UNESCAPED_UNICODE) . ' + "</button>"
						: "") +
					// A stopped subscription is sent nothing, a sample neither.
					((h.status !== "disabled")
						? "<button type=\'button\' class=\'pg-button pg-button-quiet pg-button-sm api-sample-open\' data-id=\'" + h.id + "\' aria-expanded=\'false\'>" +
							"<i class=\'bi bi-send\' aria-hidden=\'true\'></i>" + ' . json_encode(lang('Send a sample'), JSON_UNESCAPED_UNICODE) . ' + "</button>"
						: "") +
					"<button type=\'button\' class=\'pg-button pg-button-quiet pg-button-sm api-hook-act\' data-act=\'webhook_status\' data-id=\'" + h.id + "\'>" +
						(h.status === "disabled"
							? ' . json_encode(lang('Resume'), JSON_UNESCAPED_UNICODE) . '
							: ' . json_encode(lang('Stop'), JSON_UNESCAPED_UNICODE) . ') + "</button>" +
					"<button type=\'button\' class=\'pg-button pg-button-danger pg-button-sm api-hook-act\' data-act=\'webhook_delete\' data-id=\'" + h.id + "\'" +
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
					"<button type=\'button\' class=\'pg-button pg-button-danger pg-button-sm api-device-act\' data-id=\'" + d.id + "\'" +
						" data-confirm=\'" + esc(' . json_encode(lang('This device will be signed out and will have to sign in again.'), JSON_UNESCAPED_UNICODE) . ') + "\'>" +
						' . json_encode(lang('Sign out'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
				"</div>" +
			"</div>";
		}).join("");
	}

	var current = null;

	function openApp(id, back) {
		var app = APPS[id];
		if (!app) { return; }
		current = app;
		// The new subscription\'s secret belongs to the request that made it.
		if (!back || !back.secret) { dropHookSecret(); }
		resetHookForm();

		document.getElementById("d_app_id").value = app.id;
		document.getElementById("d_title").textContent = app.name;
		// The head: the kind\'s icon, the kind, how it signs in and who it
		// acts as, the state in the list\'s words.
		var face = KIND_FACE[app.kind] || KIND_FACE.server;
		document.getElementById("d_tile").className = "pg-icon-tile pg-icon-tile-lg pg-tone-" + face.tone;
		document.getElementById("d_tile_icon").className = "bi " + face.icon;
		document.getElementById("d_sub").innerHTML = esc(face.kind) + " &middot; " + esc(face.auth) + " &middot; " +
			esc(ACTS_LABEL) + " <b>" + esc(face.acts) + "</b>";
		var state = document.getElementById("d_state");
		state.className = "pg-pill pg-tone-" + app.state_tone;
		state.textContent = app.state;
		document.getElementById("d_fact_state").textContent = app.state;
		document.getElementById("d_fact_used").textContent = app.used;
		document.getElementById("d_fact_errors").textContent = app.errors;
		document.getElementById("d_fact_owner").textContent = app.owner;
		document.getElementById("d_name").value = app.name;
		document.getElementById("d_description").value = app.description || "";
		document.getElementById("d_key").textContent = app.key;
		// The example carries the real key of the application being looked at,
		// so only the secret is left to fill in. A line with two placeholders
		// invites pasting the secret into the wrong one.
		document.getElementById("dg_curl").textContent =
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

		renderHooks(app.webhooks || [], back ? back.flash : null);

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
		document.getElementById("d_nosecret_block").classList.toggle("d-none", !noSecret);
		paintGuides(app.kind, !keyless && app.has_secret);
		document.getElementById("d_unused_note").classList.toggle("d-none", !app.unused);
		document.getElementById("d_devices_label").classList.toggle("d-none", !keyless);
		document.getElementById("d_hooks_label").classList.toggle("d-none", keyless);
		// The arrow keys of the tab row skip only disabled tabs, so a hidden
		// tab is disabled too; otherwise ArrowLeft/Right opens a tab nobody
		// can see and focus falls out of the row.
		var devicesLabel = document.getElementById("d_devices_label");
		devicesLabel.disabled = !keyless;
		document.getElementById("d_hooks_label").disabled = keyless;
		devicesLabel.textContent = oauth ? devicesLabel.dataset.oauth : devicesLabel.dataset.device;
		renderDevices(app.devices || []);
		// A row whose owner outranks the editor is refused on the server; its
		// Save, new secret, delete and webhook and device buttons are hidden.
		var locked = !!app.locked;
		document.getElementById("d_locked_note").classList.toggle("d-none", !locked);
		document.getElementById("d_save").classList.toggle("d-none", locked);
		document.getElementById("d_rotate").classList.toggle("d-none", locked);
		document.getElementById("d_danger_zone").classList.toggle("d-none", locked);
		document.getElementById("d_danger_label").classList.toggle("d-none", locked);
		paintPermCount();
		drawerElement.classList.toggle("api-locked", locked);
		var firstTab = document.querySelector("#api_drawer .pg-seg-item[data-bs-target=\'" + (back ? "#d_hooks" : "#d_general") + "\']");
		if (firstTab) { pgUI.tab.show(firstTab); }
		document.getElementById("d_foot_connections").hidden = false;
		document.getElementById("d_foot_docs").hidden = true;
		document.getElementById("d_close").hidden = true;
		document.getElementById("d_save").hidden = false;
		if (back && back.form && !locked) {
			openHookForm();
			document.getElementById("d_hook_url").value = back.form.url || "";
			hookChosen = (back.form.events || []).filter(function (name) { return HOOK_TEXT.hasOwnProperty(name); });
			renderHookChosen();
			renderHookList();
			var error = document.getElementById("d_hook_form_error");
			error.textContent = back.form.error || "";
			error.classList.toggle("d-none", !back.form.error);
		}

		document.getElementById("d_delete_ask").classList.remove("d-none");
		document.getElementById("d_delete_confirm").classList.add("d-none");

		// On a phone the drawer is a sheet from the bottom.
		var phone = window.matchMedia("(max-width: 575.98px)").matches;
		drawerElement.classList.toggle("drawer-end", !phone);
		drawerElement.classList.toggle("drawer-bottom", phone);
		pgUI.drawer.show(drawerElement);
	}

	// The Connect tab: each guide that does not suit the application is
	// faded and says why; the first that suits it is open.
	function paintGuides(kind, hasSecret) {
		var list = document.getElementById("d_guides");
		var meta = JSON.parse(list.dataset.guides);
		var first = null;
		list.querySelectorAll(".pg-set-fold-item").forEach(function (item) {
			var guide = meta[item.dataset.pgFold];
			var off = !guide ? "" : (guide.kinds.indexOf(kind) < 0 ? list.dataset.offKind : ((guide.secret && !hasSecret) ? list.dataset.offSecret : ""));
			item.classList.toggle("api-guide-off", off !== "");
			var line = item.querySelector("[data-guide-off]");
			line.textContent = off;
			line.hidden = (off === "");
			if (!off && !first) { first = item; }
		});
		if (first) { window.pgSettingsFoldOpen(first); }
	}

	// The Permissions tab\'s count, beside its name and in the summary.
	function paintPermCount() {
		var rows = document.querySelectorAll("#d_perms .api-perm");
		var on = 0;
		rows.forEach(function (row) {
			var picked = row.querySelector("input[type=radio]:checked");
			if (picked && picked.value !== "") { on++; }
		});
		document.getElementById("d_perms_count").textContent = on;
		var line = document.getElementById("d_sum_perms");
		line.textContent = line.dataset.text.replace("{1}", on).replace("{2}", rows.length);
	}

	// Connect is read, not saved: its foot names the API document and
	// closes; the other tabs keep Save.
	drawerElement.addEventListener("click", function (e) {
		var go = e.target.closest("[data-go-tab]");
		if (go) {
			var tab = document.querySelector("#api_drawer .pg-seg-item[data-bs-target=\'" + go.dataset.goTab + "\']");
			if (tab) { pgUI.tab.show(tab); tab.focus(); }
			return;
		}
		var item = e.target.closest("#api_drawer .pg-seg-item");
		if (!item) { return; }
		var connect = (item.dataset.bsTarget === "#d_connect");
		document.getElementById("d_foot_connections").hidden = connect;
		document.getElementById("d_foot_docs").hidden = !connect;
		document.getElementById("d_close").hidden = !connect;
		document.getElementById("d_save").hidden = connect || !!(current && current.locked);
	});
	drawerElement.addEventListener("change", function (e) {
		if (e.target.closest("#d_perms")) { paintPermCount(); }
	});

	// A masked value (a secret, a token) shows itself while its eye is on.
	document.addEventListener("click", function (e) {
		var eye = e.target.closest(".api-reveal");
		if (!eye) { return; }
		var shown = eye.getAttribute("aria-pressed") !== "true";
		eye.setAttribute("aria-pressed", shown ? "true" : "false");
		document.getElementById(eye.dataset.reveal).hidden = !shown;
		document.querySelector("[data-mask-of=\'" + eye.dataset.reveal + "\']").hidden = shown;
		eye.querySelector("i").className = shown ? "bi bi-eye-slash" : "bi bi-eye";
	});

	pgUI.drawer.on(drawerElement, "show", function () {
		document.body.classList.add("api-drawer-open");
	});

	pgUI.drawer.on(drawerElement, "hidden", function () {
		document.body.classList.remove("api-drawer-open");
		dropHookSecret();
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
		if (current) { renderLog(this.checked ? current.log_errors : current.log, this.checked); }
	});

	// Each step of the delete confirm focuses the button that replaces the
	// one it hid, so focus does not fall to the page top.
	document.getElementById("d_delete_ask").addEventListener("click", function () {
		this.classList.add("d-none");
		document.getElementById("d_delete_confirm").classList.remove("d-none");
		document.querySelector("#d_delete_confirm .pg-button-danger").focus();
	});

	document.getElementById("d_delete_no").addEventListener("click", function () {
		var ask = document.getElementById("d_delete_ask");
		document.getElementById("d_delete_confirm").classList.add("d-none");
		ask.classList.remove("d-none");
		ask.focus();
	});

	// Filter. The rows are a grid, so they are hidden with an inline display;
	// the count is told to screen readers once the typing stops.
	var filter = document.getElementById("api_filter");
	var filterStatus = document.getElementById("api_filter_status");
	var filterEmpty = document.getElementById("api_filter_empty");
	var filterTimer = null;
	// The panel Turkish fold (pgFoldTR), so "iade" finds "İade".
	var fold = window.pgFoldTR || function (s) { return String(s).toLowerCase(); };
	filter.addEventListener("input", function () {
		var needle = fold(this.value).trim();
		var rows = document.querySelectorAll(".api-row");
		var shown = 0;
		rows.forEach(function (row) {
			var match = fold(row.textContent).indexOf(needle) > -1;
			row.style.display = match ? "" : "none";
			if (match) { shown++; }
		});
		filterEmpty.classList.toggle("d-none", needle === "" || rows.length === 0 || shown > 0);
		clearTimeout(filterTimer);
		filterStatus.textContent = "";
		if (needle === "" || rows.length === 0) { return; }
		filterTimer = setTimeout(function () {
			filterStatus.textContent = shown > 0
				? filterStatus.dataset.found.replace("{1}", shown)
				: filterStatus.dataset.none;
		}, 500);
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

	/* The Events tab: "New subscription" and "Send a sample". Both post
	   through the drawer form, like the other subscription buttons. */
	var hookChosen = [];
	var hookSearch = document.getElementById("d_hook_search");

	function dropHookSecret() {
		var note = document.getElementById("d_hook_secret");
		if (note) { note.remove(); }
	}

	// The text with the search marked where the fold keeps its length, so the
	// mark sits on the letters that matched.
	function markText(text, needle) {
		var plain = String(text);
		if (!needle) { return esc(plain); }
		var folded = fold(plain);
		var at = folded.indexOf(needle);
		if (at < 0 || folded.length !== plain.length) { return esc(plain); }
		return esc(plain.slice(0, at)) + "<mark>" + esc(plain.slice(at, at + needle.length)) + "</mark>" + esc(plain.slice(at + needle.length));
	}

	function renderHookList() {
		var needle = fold(hookSearch.value).trim();
		var full = (hookChosen.length >= HOOK_MAX_EVENTS);
		var shown = HOOK_EVENTS.filter(function (e) {
			return !needle || fold(e.name + " " + e.text + " " + e.module).indexOf(needle) > -1;
		});
		var list = document.getElementById("d_hook_events");
		list.innerHTML = shown.length ? shown.map(function (e) {
			var chosen = hookChosen.indexOf(e.name) > -1;
			return "<label class=\'api-event small\'>" +
				"<input class=\'pg-checkbox\' type=\'checkbox\' value=\'" + esc(e.name) + "\'" + (chosen ? " checked" : "") + ((full && !chosen) ? " disabled" : "") + ">" +
				"<span><span class=\'api-event-name\'><code>" + markText(e.name, needle) + "</code>" +
					(e.module ? "<span class=\'pg-pill pg-tone-gray\'>" + esc(e.module) + "</span>" : "") + "</span>" +
				"<span class=\'api-event-text\'>" + markText(e.text, needle) + "</span></span></label>";
		}).join("") : "<div class=\'api-event-none small\'>" + ' . json_encode(lang('Nothing found.'), JSON_UNESCAPED_UNICODE) . ' + "</div>";
		var found = document.getElementById("d_hook_found");
		found.textContent = needle
			? found.dataset.some.replace("{1}", shown.length).replace("{2}", hookSearch.value.trim()).replace("{3}", HOOK_EVENTS.length)
			: found.dataset.all.replace("{1}", HOOK_EVENTS.length);
	}

	// The chosen events as chips, each with its hidden field: the list shows
	// only what the search finds, the chips post every one chosen.
	function renderHookChosen() {
		var chosen = document.getElementById("d_hook_chosen");
		chosen.textContent = chosen.dataset.text.replace("{1}", hookChosen.length);
		document.getElementById("d_hook_chips").innerHTML = hookChosen.map(function (name) {
			return "<span class=\'api-chip\'>" + esc(name) +
				"<input type=\'hidden\' name=\'hook_events[]\' value=\'" + esc(name) + "\'>" +
				"<button type=\'button\' class=\'api-chip-remove\' data-event=\'" + esc(name) + "\' aria-label=\'" +
					esc(' . json_encode(lang('Remove'), JSON_UNESCAPED_UNICODE) . ' + " " + name) + "\'>&times;</button></span>";
		}).join("");
	}

	function openHookForm() {
		document.getElementById("d_hook_form").classList.remove("d-none");
		document.getElementById("d_hook_new").setAttribute("aria-expanded", "true");
		renderHookChosen();
		renderHookList();
		document.getElementById("d_hook_url").focus();
	}

	function resetHookForm() {
		document.getElementById("d_hook_form").classList.add("d-none");
		document.getElementById("d_hook_new").setAttribute("aria-expanded", "false");
		document.getElementById("d_hook_url").value = "";
		document.getElementById("d_hook_form_error").classList.add("d-none");
		document.getElementById("d_hook_unsaved").classList.add("d-none");
		hookSearch.value = "";
		hookChosen = [];
		renderHookChosen();
	}

	document.getElementById("d_hook_new").addEventListener("click", function () {
		if (current && current.locked) { return; }
		openHookForm();
	});

	["d_hook_form_close", "d_hook_form_cancel"].forEach(function (id) {
		document.getElementById(id).addEventListener("click", function () {
			resetHookForm();
			document.getElementById("d_hook_new").focus();
		});
	});

	hookSearch.addEventListener("input", renderHookList);
	// Return in the search box would post the drawer form (Save).
	hookSearch.addEventListener("keydown", function (e) {
		if (e.key === "Enter") { e.preventDefault(); }
	});

	document.getElementById("d_hook_events").addEventListener("change", function (e) {
		if (!e.target.matches("input[type=checkbox]")) { return; }
		var name = e.target.value;
		var at = hookChosen.indexOf(name);
		if (e.target.checked && at < 0 && hookChosen.length < HOOK_MAX_EVENTS) { hookChosen.push(name); }
		if (!e.target.checked && at > -1) { hookChosen.splice(at, 1); }
		renderHookChosen();
		// Only the disabled state of the other boxes changes at the limit.
		var full = (hookChosen.length >= HOOK_MAX_EVENTS);
		this.querySelectorAll("input[type=checkbox]").forEach(function (box) {
			box.disabled = full && !box.checked;
		});
	});

	document.getElementById("d_hook_chips").addEventListener("click", function (e) {
		var button = e.target.closest(".api-chip-remove");
		if (!button) { return; }
		var at = hookChosen.indexOf(button.dataset.event);
		if (at > -1) { hookChosen.splice(at, 1); }
		renderHookChosen();
		renderHookList();
		hookSearch.focus();
	});

	document.getElementById("d_hook_create").addEventListener("click", function () {
		document.getElementById("d_action").value = "webhook_create";
		document.getElementById("d_action").form.submit();
	});

	// Nothing typed is dropped in silence: Save posts the application, not
	// the form, so while the form holds an address or a chosen event Save
	// does not post; it brings the form into view with a line saying the
	// subscription is not created yet. Its other buttons (new secret, delete)
	// post as before.
	function hookFormHolds() {
		return !document.getElementById("d_hook_form").classList.contains("d-none")
			&& (document.getElementById("d_hook_url").value.trim() !== "" || hookChosen.length > 0);
	}

	document.getElementById("d_action").form.addEventListener("submit", function (e) {
		var submitter = e.submitter;
		var saving = !submitter || submitter.id === "d_save" || !submitter.name;
		if (!saving || !hookFormHolds()) { return; }
		e.preventDefault();
		var tab = document.querySelector("#api_drawer .pg-seg-item[data-bs-target=\'#d_hooks\']");
		if (tab) { pgUI.tab.show(tab); }
		document.getElementById("d_hook_unsaved").classList.remove("d-none");
		document.getElementById("d_hook_form").scrollIntoView({ block: "start" });
		document.getElementById("d_hook_create").focus({ preventScroll: true });
	});

	// Leaving the Events tab takes the new subscription\'s secret out.
	document.addEventListener("click", function (e) {
		var tab = e.target.closest("#api_drawer .pg-seg-item");
		if (tab && tab.dataset.bsTarget !== "#d_hooks") { dropHookSecret(); }
	});

	// "Send a sample": one box at a time, under its subscription, with that
	// subscription\'s own events.
	function closeSample() {
		var open = document.querySelector("#d_hook_rows .api-hook-sample");
		if (!open) { return null; }
		var button = document.querySelector(".api-sample-open[data-id=\'" + open.dataset.id + "\']");
		if (button) { button.setAttribute("aria-expanded", "false"); }
		open.remove();
		return button;
	}

	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-sample-open");
		if (!button || !current) { return; }
		var wasOpen = (button.getAttribute("aria-expanded") === "true");
		closeSample();
		if (wasOpen) { return; }
		var hook = (current.webhooks || []).filter(function (h) { return String(h.id) === button.dataset.id; })[0];
		if (!hook) { return; }
		var box = document.createElement("div");
		box.className = "api-hook-sample";
		box.dataset.id = hook.id;
		box.innerHTML =
			"<label class=\'pg-field-label\' for=\'d_sample_select\'>" + ' . json_encode(lang('Send a sample'), JSON_UNESCAPED_UNICODE) . ' + "</label>" +
			"<select class=\'pg-input pg-input-sm mb-3\' id=\'d_sample_select\' aria-describedby=\'d_sample_help\'>" +
			hook.events.map(function (name) {
				return "<option value=\'" + esc(name) + "\'>" + esc(name + (HOOK_TEXT[name] ? " — " + HOOK_TEXT[name] : "")) + "</option>";
			}).join("") + "</select>" +
			"<div class=\'d-flex gap-3 mb-2\'>" +
				"<button type=\'button\' class=\'pg-button pg-button-quiet pg-button-sm\' id=\'d_sample_send\'><i class=\'bi bi-send\' aria-hidden=\'true\'></i>" +
					' . json_encode(lang('Send'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
				"<button type=\'button\' class=\'pg-button pg-button-ghost pg-button-sm\' id=\'d_sample_cancel\'>" + ' . json_encode(lang('Cancel'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
			"</div>" +
			"<div class=\'pg-field-help\' id=\'d_sample_help\'>" + esc(' . json_encode(lang('The chosen event\'s sample goes only to this address, with the header X-Pinegrap-Test: 1; no other subscription, flow or channel hears it.'), JSON_UNESCAPED_UNICODE) . ') + "</div>";
		button.closest(".api-hook").appendChild(box);
		button.setAttribute("aria-expanded", "true");
		document.getElementById("d_sample_select").focus();
	});

	document.addEventListener("click", function (e) {
		if (e.target.closest("#d_sample_cancel")) {
			var opener = closeSample();
			if (opener) { opener.focus(); }
			return;
		}
		if (!e.target.closest("#d_sample_send")) { return; }
		var open = document.querySelector("#d_hook_rows .api-hook-sample");
		document.getElementById("d_action").value = "webhook_sample";
		document.getElementById("d_webhook_id").value = open.dataset.id;
		document.getElementById("d_sample_event").value = document.getElementById("d_sample_select").value;
		document.getElementById("d_action").form.submit();
	});

	/* New application: a drawer in steps. Step 1 picks a starting point,
	   step 2 takes the details and ticks the starting point\'s rows, Create
	   posts; the page then shows the one-time sheet (step 3). Back keeps
	   what was typed. The sign-in card opens the OAuth application the site
	   already serves instead of making a second. */
	var newSheet = document.getElementById("api_new");
	var newForm = document.getElementById("n_form");
	var nextButton = document.getElementById("n_next");
	var createButton = document.getElementById("n_submit");
	var backButton = document.getElementById("n_back");
	var startPicked = "";

	function newStep(number) {
		var done = document.getElementById("n_step_3");
		var rotated = (newSheet.dataset.doneRotated === "1");
		document.getElementById("n_step_1").hidden = (number !== 1);
		document.getElementById("n_step_2").hidden = (number !== 2);
		if (done) { done.hidden = (number !== 3); }
		["n_done_tile", "n_head_done", "n_done_ack_field", "n_done"].forEach(function (id) {
			var node = document.getElementById(id);
			if (node) { node.hidden = (number !== 3); }
		});
		document.getElementById("n_head_tile").hidden = (number === 3);
		document.getElementById("n_head_main").hidden = (number === 3);
		document.getElementById("n_steps_wrap").hidden = (number === 3) && rotated;
		backButton.hidden = (number !== 2);
		nextButton.hidden = (number !== 1);
		createButton.hidden = (number !== 2);
		document.getElementById("n_cancel").hidden = (number !== 1);
		document.getElementById("n_hint_1").hidden = (number !== 1);
		document.getElementById("n_hint_2").hidden = (number !== 2);
		document.getElementById("n_x").hidden = (number === 3) && !!document.getElementById("n_done_ack");
		document.getElementById("n_head_pick").hidden = (number !== 1);
		document.getElementById("n_head_chosen").hidden = (number !== 2);
		var card = (number === 2) ? pickedCard() : null;
		document.getElementById("n_head_tile").className = "pg-icon-tile pg-icon-tile-lg pg-tone-" + (card ? card.tone : "blue");
		document.getElementById("n_head_icon").className = "bi " + (card ? card.icon : "bi-plug");
		document.querySelectorAll("#n_steps .stepper-item").forEach(function (item) {
			var step = parseInt(item.dataset.step, 10);
			item.classList.toggle("active", step <= number);
			item.classList.toggle("theme-success", step < number);
			if (step === number) { item.setAttribute("aria-current", "step"); } else { item.removeAttribute("aria-current"); }
		});
		var focus = (number === 3) ? document.querySelector("#n_step_3 button, #n_done")
			: ((number === 2) ? document.getElementById("n_name") : document.querySelector("#api_new input[name=\'start\']:checked"));
		if (focus) { focus.focus(); }
	}

	function pickedCard() {
		var radio = document.querySelector("#api_new input[name=\'start\']:checked");
		return radio ? START_CARDS[radio.value] : null;
	}

	function syncNext() {
		var card = pickedCard();
		nextButton.querySelector(".pg-btn-label").textContent = (card && card.existing) ? nextButton.dataset.open : nextButton.dataset.next;
	}

	document.querySelectorAll("#api_new input[name=\'start\']").forEach(function (radio) {
		radio.addEventListener("change", syncNext);
	});

	nextButton.addEventListener("click", function () {
		var card = pickedCard();
		if (!card) { return; }
		if (card.existing && APPS[card.existing]) {
			pgUI.drawer.hide(newSheet).then(function () { openApp(card.existing); });
			return;
		}
		document.getElementById("n_kind").value = card.kind;
		document.getElementById("n_chosen_title").textContent = card.title;
		document.getElementById("n_start_note").classList.toggle("d-none", card.id !== "automation");
		// A new starting point ticks its own rows; coming back to the same
		// one keeps what was changed by hand.
		if (startPicked !== card.id) {
			document.querySelectorAll("#api_new .api-perm").forEach(function (row) {
				var choice = card.choices[row.dataset.group] || "";
				var input = row.querySelector("input[value=\'" + choice + "\']");
				if (input) { input.checked = true; }
			});
			paintImplied();
			startPicked = card.id;
		}
		newStep(2);
	});

	backButton.addEventListener("click", function () { newStep(1); });
	document.getElementById("n_change").addEventListener("click", function () { newStep(1); });

	// Return in a field of step 1 moves on rather than posting half a form.
	newForm.addEventListener("submit", function (e) {
		if (!document.getElementById("n_step_2").hidden) { return; }
		e.preventDefault();
		nextButton.click();
	});

	pgUI.drawer.on(newSheet, "show", function () { newStep(document.getElementById("n_step_3") ? 3 : 1); syncNext(); });

	/* Step 3, on the request after Create or a new secret: the sheet opens
	   there by itself. While a secret is shown it closes only once "I have
	   copied the secret" is ticked; closing takes the values out of the
	   page, so the next New Application starts at step 1. */
	var doneAck = document.getElementById("n_done_ack");
	var doneButton = document.getElementById("n_done");
	if (doneAck) {
		doneAck.addEventListener("change", function () { doneButton.disabled = !this.checked; });
		pgUI.drawer.on(newSheet, "hide", function (e) {
			if (document.getElementById("n_step_3") && !doneAck.checked) { e.preventDefault(); }
		});
	}
	if (doneButton) {
		doneButton.addEventListener("click", function () {
			if (!this.disabled) { pgUI.drawer.hide(newSheet); }
		});
		pgUI.drawer.on(newSheet, "hidden", function () {
			var done = document.getElementById("n_step_3");
			if (!done) { return; }
			done.remove();
			var newButton = document.querySelector("[data-bs-target=\"#api_new\"]");
			if (newButton) { newButton.focus(); }
		});
		window.addEventListener("load", function () { pgUI.drawer.show(newSheet); });
	}

	// Back from the Events tab\'s form or a sample: the drawer opens there again.
	if (HOOKS_BACK && APPS[HOOKS_BACK.app_id]) {
		window.addEventListener("load", function () { openApp(HOOKS_BACK.app_id, HOOKS_BACK); });
	}

	// Copy buttons
	document.addEventListener("click", function (e) {
		var button = e.target.closest(".api-copy");
		if (!button) { return; }
		var source = document.querySelector(button.dataset.copy);
		if (!source) { return; }
		// The button keeps its label; only the icon changes and the status
		// beside it says whether the copy happened.
		var status = button.parentNode.querySelector("[role=status]");
		var icon = button.querySelector("i");
		var done = function () {
			icon.className = "bi bi-check2";
			status.textContent = status.getAttribute("data-copied");
			setTimeout(function () {
				icon.className = "bi bi-clipboard";
				status.textContent = "";
			}, 1500);
		};
		// Plain http has no clipboard API and a write can be refused: select
		// the text and copy it the old way; when that fails too, leave it
		// selected and say so.
		var fallback = function () {
			var copied = false;
			var selection = window.getSelection();
			selection.selectAllChildren(source);
			try { copied = document.execCommand("copy"); } catch (error) {}
			if (copied) {
				done();
			} else {
				status.textContent = status.getAttribute("data-refused");
			}
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(source.textContent.trim()).then(done, fallback);
		} else {
			fallback();
		}
	});

})();
</script>' . output_footer();
