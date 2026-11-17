<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connections: every outside service the site works with, from the
 * registry (pg_connections_registry()), in groups with its state read from
 * what is stored, what needs attention on top, and a link to the one place
 * of each one's settings (includes/fn/connections_screen.php). The account
 * providers (Google, Microsoft, ...) keep here the application each knows
 * the site by and the buttons that connect, check and end their accounts
 * (includes/fn/connect.php).
 *
 * The reverse direction - outside applications that connected to this site -
 * is Application Access (api_settings.php) and each person's Connected
 * applications (connected_apps.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

if (pg_post_body_discarded()) {
	output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_connect_schema_ready()) {
	output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('connections');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/connections.php';

// A provider that only signs people in (accounts => false: Apple) has no
// account for the site to connect; its application is set up with the
// sign-in settings instead.
$providers = array_filter(pg_connect_providers(), function ($provider) {
	return !(isset($provider['accounts']) && ($provider['accounts'] === false));
});

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	$action = isset($_POST['connections_action']) ? (string) $_POST['connections_action'] : '';

	// The provider whose panel opens again after the write.
	$return_provider = '';

	if ($action === 'save_app') {

		$provider_key = isset($_POST['provider']) ? (string) $_POST['provider'] : '';

		if (isset($providers[$provider_key])) {

			$provider = $providers[$provider_key];
			$return_provider = $provider_key;

			$options = array();
			$option_error = '';
			$option_error_key = '';

			foreach ($provider['options'] as $option_key => $option) {

				$value = (isset($_POST['option_' . $option_key]) && is_string($_POST['option_' . $option_key]))
					? trim($_POST['option_' . $option_key])
					: '';

				$options[$option_key] = ($value !== '') ? mb_substr($value, 0, 100) : (string) $option['default'];

				// A value the provider cannot read is refused, never kept
				// and replaced by the default at connection time.
				if (($option_error === '') && !pg_connect_option_valid($option, $options[$option_key])) {
					$option_error = isset($option['invalid']) ? $option['invalid'] : $option['label'];
					$option_error_key = $option_key;
				}

			}

			$client_id = (isset($_POST['client_id']) && is_string($_POST['client_id'])) ? trim($_POST['client_id']) : '';
			$client_secret = (isset($_POST['client_secret']) && is_string($_POST['client_secret'])) ? trim($_POST['client_secret']) : '';

			// What was typed, for the form after a refused save; without it the
			// redirect below drew the stored values and everything typed was
			// lost. The secret is never kept here.
			$draft = array(
				'provider'   => $provider_key,
				'client_id'  => mb_substr($client_id, 0, 1000),
				'options'    => $options,
				'invalid'    => $option_error_key,
				'enabled'    => !empty($_POST['enabled']),
				'had_secret' => ($client_secret !== '')
			);

			if (mb_strlen($client_id) > 255) {

				$liveform->add_error(lang('The client ID is too long.'));

				$draft['invalid'] = 'client_id';

				$_SESSION['software']['connections_draft'] = $draft;

			} elseif ($option_error !== '') {

				$liveform->add_error(h($option_error));

				$_SESSION['software']['connections_draft'] = $draft;

			} elseif (pg_connect_app_save($provider_key, $client_id, $client_secret, $options, !empty($_POST['enabled']), (int) $user['id'])) {

				$liveform->add_notice(lang(array('string' => 'The {var:1} application was saved.', 'vars' => $provider['label'])));

				log_activity(lang(array('string' => 'changed the {var:1} application of connected accounts', 'vars' => $provider['label'])), $_SESSION['sessionusername']);

			} else {

				$liveform->add_error(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));

				$_SESSION['software']['connections_draft'] = $draft;

			}

		}

	} elseif ($action === 'remove_app') {

		$return_provider = 'google';

		// Only Google has an application to fall back to (Sign in with
		// Google); for the others removing it would just stop every account.
		if (pg_signin_google_app() !== null) {

			pg_connect_app_remove('google');

			$liveform->add_notice(lang('Google features use the Sign in with Google application again.'));

			log_activity(lang(array('string' => 'removed the {var:1} application of connected accounts', 'vars' => 'Google')), $_SESSION['sessionusername']);

		}

	} elseif ($action === 'check') {

		$account = pg_connect_account(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

		if (is_array($account) && ($account['user_id'] === 0)) {

			$return_provider = $account['provider'];
			$check = pg_connect_check($account);

			if ($check['ok']) {

				$liveform->add_notice(lang(array('string' => '{var:1} works.', 'vars' => h($account['label']))));

			} else {

				$liveform->add_error(lang(array('string' => '{var:1} does not work: {var:2}', 'vars' => array(h($account['label']), h($check['error'])))));

			}

		}

	} elseif ($action === 'disconnect') {

		$account = pg_connect_account(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

		if (is_array($account) && ($account['user_id'] === 0)) {

			$return_provider = $account['provider'];
			pg_connect_disconnect($account['id']);

			$liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => h($account['label']))));

			log_activity(lang(array(
				'string' => 'disconnected the {var:1} account {var:2}',
				'vars'   => array(isset($providers[$account['provider']]) ? $providers[$account['provider']]['label'] : $account['provider'], $account['label']))),
				$_SESSION['sessionusername']);

		}

	}

	header('Location: ' . $screen_url . (($return_provider !== '') ? '#connections-' . rawurlencode($return_provider) : ''));

	exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

$flash = pg_connect_flash_take();

if (is_array($flash)) {

	if ($flash['kind'] === 'error') {
		$liveform->add_error(h($flash['text']));
	} else {
		$liveform->add_notice(h($flash['text']));
	}

}

$can_seal = pg_connect_can_seal();

// The values of a save that was refused, shown once in that provider's form.
$draft = null;

if (isset($_SESSION['software']['connections_draft']) && is_array($_SESSION['software']['connections_draft'])) {

	$draft = $_SESSION['software']['connections_draft'];

	unset($_SESSION['software']['connections_draft']);

}

$redirect_uri = pg_connect_redirect_uri();

$query = (isset($_GET['q']) && is_string($_GET['q'])) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$filter = (isset($_GET['state']) && in_array($_GET['state'], array('attention', 'connected', 'not_connected'), true)) ? $_GET['state'] : '';

function connections_ago($timestamp) {

	$timestamp = (int) $timestamp;

	return ($timestamp > 0)
		? strip_tags(get_relative_time(array('timestamp' => $timestamp)))
		: lang('Never');

}

function connections_status_badge($status) {

	if ($status === 'active') {
		return '<span class="pg-pill pg-tone-teal">' . h(lang('Working')) . '</span>';
	}

	if ($status === 'reauth') {
		return '<span class="pg-pill pg-tone-amber">' . h(lang('Connect again')) . '</span>';
	}

	return '<span class="pg-pill pg-tone-gray">' . h(lang('Off')) . '</span>';

}

// A row's state as its pill; a row whose state cannot be read says so in
// plain text, with no pill.
function connections_state_pill($state) {

	$pills = array(
		'attention'     => array('amber', lang('Needs attention')),
		'connected'     => array('teal', lang('Connected')),
		'not_connected' => array('gray', lang('Not connected')),
		'off'           => array('gray', lang('Off')),
	);

	if (!isset($pills[$state])) {
		return '<span class="pg-text-meta text-nowrap">' . h(lang('State cannot be read')) . '</span>';
	}

	return '<span class="pg-pill pg-tone-' . $pills[$state][0] . '">' . h($pills[$state][1]) . '</span>';

}

/* The places the user may open: the Site Settings categories and the
   System tools screens, each under its own gate. */

$open_categories = array();
$category_list = pg_settings_categories();

foreach (pg_settings_categories_for($user, null, $category_list) as $category_key => $category) {
	$open_categories[$category_key] = true;
}

$tool_labels = array();

foreach (pg_settings_tool_groups($user) as $tool_group) {
	foreach ((isset($tool_group['items']) ? $tool_group['items'] : array()) as $item) {
		if (!isset($item['confirm'])) {
			$tool_labels[(string) strtok((string) $item['url'], '?')] = (string) $item['label'];
		}
	}
}

$is_manager = ((int) $user['role'] <= 2);

// Where a place is, in words, and its address.
function connections_place($place, $category_list, $tool_labels) {

	if (preg_match('~^#settings/([a-z_]+)/(pgset-[a-z0-9-]+)$~', $place, $match)) {

		$category = isset($category_list[$match[1]]) ? $category_list[$match[1]] : array('label' => $match[1], 'sections' => array());
		$card = isset($category['sections'][$match[2]]) ? $category['sections'][$match[2]] : '';

		return array(
			'label' => implode(' › ', array_filter(array(lang('Site Settings'), $category['label'], $card), 'strlen')),
			'url'   => pg_settings_link($match[1], $match[2]),
		);

	}

	if ($place === 'search_console.php') {
		return array('label' => lang('Google Search Console'), 'url' => $place);
	}

	return array(
		'label' => lang('System tools') . ' › ' . (isset($tool_labels[$place]) ? $tool_labels[$place] : $place),
		'url'   => $place,
	);

}

/* The rows: the account providers first (what this screen sets up itself),
   then every service of the registry the user may see. */

$facts = pg_connections_screen_facts();
$registry = pg_connections_registry();
$group_labels = array('providers' => lang('Account providers'));

foreach (pg_connections_groups() as $group_key => $group_label) {
	$group_labels[$group_key] = lang($group_label);
}

$group_icons = array(
	'providers' => array('person-badge', 'blue'), 'payments' => array('credit-card', 'shop'), 'shipping' => array('truck', 'shop'),
	'marketplaces' => array('shop', 'shop'), 'edocuments' => array('receipt', 'accounting'), 'email' => array('envelope', 'team'),
	'messaging' => array('chat-dots', 'team'), 'storage' => array('cloud-arrow-up', 'blue'), 'signin' => array('box-arrow-in-right', 'system'),
	'calendar' => array('calendar3', 'team'), 'analytics' => array('graph-up', 'reports'), 'maps' => array('geo-alt', 'teal'),
	'ai' => array('stars', 'violet'), 'protection' => array('shield-check', 'system'), 'other' => array('plug', 'gray'), 'mcp' => array('tools', 'violet'),
);

$rows = array();
$provider_uses = array();

foreach ($registry as $id => $service) {

	if (!pg_connections_screen_may_see($service['place'], $open_categories, $tool_labels, $is_manager)) {
		continue;
	}

	$place = connections_place($service['place'], $category_list, $tool_labels);
	$uses = lang($service['uses']);

	$rows[$id] = array(
		'kind'   => 'service',
		'name'   => $service['name'],
		'group'  => $service['group'],
		'state'  => pg_connections_screen_state($service, $facts),
		'line'   => $place['label'],
		'uses'   => $uses,
		'url'    => $place['url'],
		'search' => array($service['name'], $group_labels[$service['group']], $uses, $service['uses'], $place['label']),
	);

	if (isset($service['state']['kind']) && ($service['state']['kind'] === 'account')) {
		$provider_uses[$service['state']['provider']][] = $service['name'];
	}

}

$site_accounts = array();

foreach ($facts['accounts'] as $account) {
	if ($account['user_id'] === 0) {
		$site_accounts[$account['provider']][] = $account;
	}
}

$provider_apps = array();

foreach ($providers as $provider_key => $provider) {

	$app = pg_connect_app($provider_key);
	$provider_apps[$provider_key] = $app;

	if (pg_connect_app_ready($app)) {
		$app_state = 'ready';
		$app_text = lang('Ready');
	} elseif (is_array($app) && !$app['enabled']) {
		$app_state = 'off';
		$app_text = lang('Off');
	} else {
		$app_state = 'not_set_up';
		$app_text = lang('Not set up');
	}

	$accounts = isset($site_accounts[$provider_key]) ? $site_accounts[$provider_key] : array();

	$line = lang('Application') . ': ' . $app_text;

	if ($accounts) {
		$line .= ' · ' . lang(array('string' => '{var:1} connected', 'vars' => pg_format_number(count($accounts), 0)));
	}

	$uses = isset($provider_uses[$provider_key]) ? implode(', ', $provider_uses[$provider_key]) : '';

	$rows['provider_' . $provider_key] = array(
		'kind'     => 'provider',
		'provider' => $provider_key,
		'name'     => $provider['label'],
		'icon'     => $provider['icon'],
		'group'    => 'providers',
		'state'    => pg_connections_screen_provider_state($accounts, $app_state),
		'line'     => $line,
		'uses'     => $uses,
		'search'   => array($provider['label'], $group_labels['providers'], $uses),
	);

}

$counts = pg_connections_screen_counts(pg_connections_screen_search($rows, $query));
$shown = pg_connections_screen_filter(pg_connections_screen_search($rows, $query), $filter);
$order = pg_connections_screen_order($shown, array_keys($group_labels));
$folding = ($query === '') && ($filter === '');

/* A provider's panel: what the screen did before it became the list, for
   each provider its accounts and its application. */

function connections_provider_panel($provider_key, $provider, $app, $accounts, $provider_draft, $can_seal, $redirect_uri) {

	$ready = pg_connect_app_ready($app);

	// What connecting asks for: the base account, or for a provider that is
	// only of use to one feature (GitHub: channel links) that feature's
	// permission, so the account is not connected without it.
	$connect_purpose = !empty($provider['connect_purpose']) ? (string) $provider['connect_purpose'] : 'account';

	$account_rows = '';

	foreach ($accounts as $account) {

		$purpose_labels = array();

		foreach ($account['purposes'] as $purpose_key) {
			$purpose = pg_connect_purpose($purpose_key);
			$purpose_labels[] = is_array($purpose) ? $purpose['label'] : $purpose_key;
		}

		$meta = array(
			lang(array('string' => 'Connected on {var:1}', 'vars' => get_absolute_time(array('timestamp' => (int)$account['created_timestamp'], 'type' => 'date', 'format' => 'plain_text')))),
			lang('Last used') . ' ' . connections_ago($account['last_used_timestamp']),
		);

		$account_rows .= '
			<li class="list-group-item px-0">
				<div class="d-flex align-items-start gap-3">
					<i class="bi bi-person-circle fs-lg opacity-75" aria-hidden="true"></i>
					<div class="flex-grow-1 min-w-0">
						<div class="fw-semibold text-truncate">' . h($account['label']) . '</div>
						<div class="pg-text-meta">' . h(implode(' · ', $purpose_labels)) . '</div>
						<div class="pg-text-meta">' . h(implode(' · ', $meta)) . '</div>
						' . (($account['last_error'] !== '')
							? '<div class="small fg-danger mt-1">' . h($account['last_error']) . '</div>'
							: '') . '
					</div>
					' . connections_status_badge($account['status']) . '
				</div>
				<div class="d-flex flex-wrap gap-3 mt-3 ms-7 ps-3">
					' . (($account['status'] === 'reauth')
						? '<button type="button" class="pg-button pg-button-sm connections-connect"
							data-provider="' . h($provider_key) . '" data-purpose="' . h($connect_purpose) . '" data-connection="' . $account['id'] . '"' . ($ready ? '' : ' disabled') . '>
							<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Connect again')) . '</button>'
						: '<button type="button" class="pg-button pg-button-sm connections-action"
							data-action="check" data-id="' . $account['id'] . '">
							<i class="bi bi-activity me-1" aria-hidden="true"></i>' . h(lang('Check')) . '</button>') . '
					<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action"
						data-action="disconnect" data-id="' . $account['id'] . '"
						data-confirm="' . h(lang(array('string' => 'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.', 'vars' => $account['label']))) . '">
						<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
				</div>
			</li>';

	}

	$option_fields = '';

	foreach ($provider['options'] as $option_key => $option) {

		if (is_array($provider_draft) && isset($provider_draft['options'][$option_key])) {
			$value = (string) $provider_draft['options'][$option_key];
		} else {
			$value = (is_array($app) && isset($app['options'][$option_key])) ? (string) $app['options'][$option_key] : (string) $option['default'];
		}

		$option_bad = is_array($provider_draft) && ($provider_draft['invalid'] === $option_key);

		$option_fields .= '
			<div class="col-12 md:col-6">
				<div class="form-field">
					<label class="form-label" for="option_' . h($provider_key . '_' . $option_key) . '">' . h($option['label']) . '</label>
					<input type="text" class="form-control' . ($option_bad ? ' is-invalid' : '') . '" id="option_' . h($provider_key . '_' . $option_key) . '"
						name="option_' . h($option_key) . '" value="' . h($value) . '" maxlength="100" autocomplete="off"' . ($option_bad ? ' aria-invalid="true"' : '') . '>
					<div class="form-text">' . h($option['help']) . '</div>
				</div>
			</div>';

	}

	$setup_steps = '';

	foreach ($provider['setup'] as $step) {
		$setup_steps .= '<li>' . h($step) . '</li>';
	}

	// The Sign in with Google application's secret counts as stored: saving
	// the form unchanged keeps it (pg_connect_app_save()).
	$secret_stored = is_array($app) && $app['secret_stored'];

	$option_invalid = is_array($app) && isset($app['options_valid']) && !$app['options_valid'];

	if ($ready) {
		$hint = '';
	} elseif ($option_invalid) {
		$hint = lang('The directory (tenant) must be common, organizations, consumers, a domain name or a directory ID.');
	} elseif (is_array($app) && !$app['enabled']) {
		$hint = lang('Connections through this application are off. Turn them on with the switch below.');
	} else {
		$hint = lang('Set up the application below first.');
	}

	$client_id_value = is_array($provider_draft) ? $provider_draft['client_id'] : (is_array($app) ? $app['client_id'] : '');

	$client_id_bad = is_array($provider_draft) && ($provider_draft['invalid'] === 'client_id');

	$enabled_checked = is_array($provider_draft) ? $provider_draft['enabled'] : (!is_array($app) || $app['enabled']);

	$can_fall_back = ($provider_key === 'google') && is_array($app) && ($app['source'] === 'connect') && (pg_signin_google_app() !== null);

	$app_form = '
		<form method="post" action="connections.php" class="mt-3">
			' . get_token_field() . '
			<input type="hidden" name="connections_action" value="save_app">
			<input type="hidden" name="provider" value="' . h($provider_key) . '">
			' . ((is_array($app) && ($app['source'] === 'signin'))
				? '<div class="alert theme-info small py-3">' . h(lang('This is the application of Sign in with Google (Security settings). Add the redirect address below to it in Google Cloud Console, or save an application of its own here.')) . '</div>'
				: '') . '
			<ol class="small ps-5 mb-5">' . $setup_steps . '</ol>
			<div class="form-field mb-5">
				<label class="form-label" for="redirect_' . h($provider_key) . '">' . h(lang('Redirect address')) . '</label>
				<div class="input-group">
					<input type="text" class="form-control font-monospace" id="redirect_' . h($provider_key) . '" value="' . h($redirect_uri) . '" readonly>
					<button type="button" class="btn btn-outline theme-secondary connections-copy" data-target="redirect_' . h($provider_key) . '">
						<i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
				</div>
				<div class="form-text mb-0 connections-copy-status" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></div>
			</div>
			<div class="row g-5">
				<div class="col-12 md:col-6">
					<div class="form-field">
						<label class="form-label" for="client_id_' . h($provider_key) . '">' . h(lang('Client ID')) . '</label>
						<input type="text" class="form-control' . ($client_id_bad ? ' is-invalid' : '') . '" id="client_id_' . h($provider_key) . '" name="client_id"
							value="' . h($client_id_value) . '" maxlength="255" autocomplete="off"' . ($client_id_bad ? ' aria-invalid="true"' : '') . '>
					</div>
				</div>
				<div class="col-12 md:col-6">
					<div class="form-field">
						<label class="form-label" for="client_secret_' . h($provider_key) . '">' . h(lang('Client secret')) . '</label>
						<input type="password" class="form-control" id="client_secret_' . h($provider_key) . '" name="client_secret"
							value="" autocomplete="new-password"
							placeholder="' . h($secret_stored ? lang('Stored. Leave empty to keep it.') : '') . '">
						' . ((is_array($provider_draft) && $provider_draft['had_secret'])
							? '<div class="form-text fg-warning">' . h(lang('The secret you entered was not saved. Enter it again.')) . '</div>'
							: '') . '
					</div>
				</div>
				' . $option_fields . '
				<div class="col-12">
					<div class="form-field">
						<div class="switch"><input class="" type="checkbox" role="switch" name="enabled" value="1" id="enabled_' . h($provider_key) . '"'
							. ($enabled_checked ? ' checked' : '') . '></div>
						<label class="" for="enabled_' . h($provider_key) . '">' . h(lang('Connections through this application are allowed')) . '</label>
					</div>
				</div>
			</div>
			<div class="d-flex flex-wrap align-items-center gap-3 mt-5">
				<button type="submit" name="submit_save" class="pg-button pg-button-sm"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
				<a class="pg-button pg-button-sm pg-button-quiet" href="' . h($provider['console_url']) . '" target="_blank" rel="noopener">
					<i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Open the {var:1} console', 'vars' => $provider['label']))) . '</a>
				' . ($can_fall_back
					? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-action" data-action="remove_app" data-id="0"
						data-confirm="' . h(lang('The application saved here is removed and Google features use the Sign in with Google application of the Security settings again. Connected accounts stay.')) . '">
						<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Use the Sign in with Google application')) . '</button>'
					: '') . '
			</div>
		</form>';

	return '
		<div class="connections-panel" id="connections-' . h($provider_key) . '"' . (is_array($provider_draft) ? ' data-open="1"' : '') . ' hidden>
			<h3 class="pg-group-label mb-2">' . h(lang('Connected accounts')) . '</h3>
			' . (($account_rows !== '')
				? '<ul class="list-group list-group-flush mb-5">' . $account_rows . '</ul>'
				: '<p class="pg-text-2">' . h(lang(array('string' => 'No {var:1} account is connected.', 'vars' => $provider['label']))) . '</p>') . '
			<button type="button" class="pg-button pg-button-fill pg-button-sm connections-connect"
				data-provider="' . h($provider_key) . '" data-purpose="' . h($connect_purpose) . '" data-connection="0"' . (($ready && $can_seal) ? '' : ' disabled') . '>
				<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $provider['label']))) . '</button>
			' . (($hint === '') ? '' : '<div class="form-text">' . h($hint) . '</div>') . '
			<details class="mt-7"' . (($ready && !is_array($provider_draft)) ? '' : ' open') . '>
				<summary class="fw-semibold">' . h(lang('Application')) . '</summary>
				' . $app_form . '
			</details>
		</div>';

}

/* The attention block: every connected account that stopped working, the
   site's own with Connect again, a person's own with who reconnects it. */

$attention_rows = '';
$attention_count = 0;

foreach ($facts['accounts'] as $account) {

	if (($account['status'] !== 'reauth') || !isset($providers[$account['provider']])) {
		continue;
	}

	$attention_count++;

	$used_by = array();

	foreach ($registry as $service) {
		if (isset($service['state']['kind']) && ($service['state']['kind'] === 'account') && ($service['state']['provider'] === $account['provider'])
			&& in_array($service['state']['purpose'], $account['purposes'], true)) {
			$used_by[] = $service['name'];
		}
	}

	$meta = array();

	if ($used_by) {
		$meta[] = lang(array('string' => 'Used by: {var:1}', 'vars' => implode(', ', $used_by)));
	}

	$meta[] = lang('Last used') . ' ' . connections_ago($account['last_used_timestamp']);

	$provider = $providers[$account['provider']];

	$attention_rows .= '
		<div class="pg-card-inner-row connections-row">
			<span class="pg-icon-tile pg-icon-tile-lg pg-tone-amber" aria-hidden="true"><i class="bi ' . h($provider['icon']) . '"></i></span>
			<div class="connections-row-main">
				<div class="connections-row-name" lang="en">' . h($provider['label']) . '</div>
				<div class="pg-text-2 text-break">' . h(lang(array('string' => '{var:1} must be connected again', 'vars' => $account['label']))) . '</div>
				<div class="pg-text-meta">' . h(implode(' · ', $meta)) . '</div>
			</div>
			<div class="connections-row-side">
				' . (($account['user_id'] === 0)
					? '<button type="button" class="pg-button pg-button-sm connections-connect" data-provider="' . h($account['provider']) . '"
						data-purpose="' . h(!empty($provider['connect_purpose']) ? $provider['connect_purpose'] : 'account') . '" data-connection="' . $account['id'] . '"'
						. ((pg_connect_app_ready($provider_apps[$account['provider']]) && $can_seal) ? '' : ' disabled') . '>' . h(lang('Connect again')) . '</button>'
					: '<span class="pg-text-meta">' . h(lang('A person\'s own account: they connect it again.')) . '</span>') . '
			</div>
		</div>';

}

/* The groups. */

$groups_html = '';

foreach ($order as $group_key => $ids) {

	$states = array();

	foreach ($ids as $id) {
		$states[] = $rows[$id]['state'];
	}

	$fold_at = $folding ? pg_connections_screen_fold_at($states) : count($ids);
	$group_rows = '';

	foreach ($ids as $index => $id) {

		$row = $rows[$id];
		$hidden = ($index >= $fold_at) ? ' hidden' : '';

		$tile = ($row['kind'] === 'provider')
			? '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi ' . h($row['icon']) . '"></i></span>'
			: '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . $group_icons[$group_key][1] . '" aria-hidden="true"><i class="bi bi-' . $group_icons[$group_key][0] . '"></i></span>';

		$inner = $tile . '
				<span class="connections-row-main">
					<span class="connections-row-name">' . h($row['name']) . '</span>
					<span class="pg-text-meta d-block">' . h($row['line']) . '</span>
					' . (($row['uses'] !== '') ? '<span class="pg-text-2 d-block">' . h($row['uses']) . '</span>' : '') . '
				</span>
				<span class="connections-row-side">' . connections_state_pill($row['state']) . '</span>';

		if ($row['kind'] === 'provider') {

			$provider_key = $row['provider'];
			$provider_draft = (is_array($draft) && ($draft['provider'] === $provider_key)) ? $draft : null;

			$group_rows .= '
			<div class="connections-provider"' . $hidden . '>
				<button type="button" class="pg-card-inner-row connections-row connections-toggle" aria-expanded="false" aria-controls="connections-' . h($provider_key) . '">'
					. $inner . '<i class="bi bi-chevron-down connections-row-chevron" aria-hidden="true"></i>
				</button>
				' . connections_provider_panel($provider_key, $providers[$provider_key], $provider_apps[$provider_key],
					isset($site_accounts[$provider_key]) ? $site_accounts[$provider_key] : array(), $provider_draft, $can_seal, $redirect_uri) . '
			</div>';

		} else {

			$group_rows .= '
			<a class="pg-card-inner-row connections-row" href="' . h($row['url']) . '"' . $hidden . '>'
				. $inner . '<i class="bi bi-chevron-right connections-row-chevron" aria-hidden="true"></i>
			</a>';

		}

	}

	if ($fold_at < count($ids)) {
		$group_rows .= '
			<div class="pg-card-inner-row">
				<button type="button" class="pg-gather-toggle connections-more">'
					. h(lang(array('string' => 'Show all ({var:1} more)', 'vars' => pg_format_number(count($ids) - $fold_at, 0))))
					. ' <i class="bi bi-chevron-down" aria-hidden="true"></i></button>
			</div>';
	}

	$groups_html .= '
		<section class="mb-6" aria-labelledby="connections-group-' . h($group_key) . '">
			<h2 class="pg-group-label mb-2 ms-1" id="connections-group-' . h($group_key) . '">' . h($group_labels[$group_key]) . '<span class="pg-count">' . count($ids) . '</span></h2>
			<div class="pg-card-inner connections-list">' . $group_rows . '</div>
		</section>';

}

if ($groups_html === '') {
	$groups_html = '
		<div class="pg-card p-6 text-center">
			<p class="pg-title-status mb-2">' . h(($query !== '')
				? lang(array('string' => 'No service matches “{var:1}”.', 'vars' => $query))
				: lang('No service is in this state.')) . '</p>
			<p class="pg-text-2">' . h(lang('The search looks in the names, groups, uses and places of the services.')) . '</p>
			<a class="pg-button pg-button-sm" href="connections.php">' . h(lang('Clear search')) . '</a>
		</div>';
}

/* The search and the state filter, both in the address. */

$filter_link = function ($value) use ($query) {
	$params = array_filter(array('q' => $query, 'state' => $value), 'strlen');
	return 'connections.php' . ($params ? '?' . http_build_query($params) : '');
};

$segments = '';

foreach (array('' => lang('All'), 'attention' => lang('Needs attention'), 'connected' => lang('Connected'), 'not_connected' => lang('Not connected')) as $value => $label) {
	$segments .= '<a class="pg-seg-item' . (($filter === $value) ? ' is-active" aria-current="true' : '') . '" href="' . h($filter_link($value)) . '">'
		. h($label) . '<span class="pg-seg-count pg-tabular">' . (int) $counts[($value === '') ? 'all' : $value] . '</span></a>';
}

$search_form = '
	<form method="get" action="connections.php" class="disable_shortcut" role="search">
		' . (($filter !== '') ? '<input type="hidden" name="state" value="' . h($filter) . '">' : '') . '
		<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
			<span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
			<input type="search" class="form-ghost" name="q" value="' . h($query) . '" maxlength="100"
				placeholder="' . h(lang('Search for a service, group or use')) . '" aria-label="' . h(lang('Search for a service, group or use')) . '">
		</label>
	</form>';

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

// No save bar: each provider saves its own application through its own form.
echo pg_page_shell(array(
	'title'               => lang('Connections'),
	'extra classes'       => 'pg-measure-edit',
	'icon'                => 'settings',
	'heading'             => lang('Connections'),
	'heading_description' => lang('The outside services the site works with: what is connected, what stopped, and the one place of each one\'s settings.'),
	'breadcrumb'          => array(
		array('label' => lang('System tools'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/system_tools.php'),
		array('label' => lang('Connections')),
	),
)) . '

<main id="content" class="container-fluid">
	' . pg_screen_toolbar(array(
		'search' => $search_form,
		'view'   => '<div class="pg-seg pg-list-seg" role="group" aria-label="' . h(lang('Filter by state')) . '">' . $segments . '</div>',
	), array('label' => lang('Connections'))) . '
	<p class="pg-text-meta mb-5">' . h(lang(array('string' => '{var:1} services whose state cannot be read are listed only under All.', 'vars' => pg_format_number($counts['none'], 0)))) . '</p>

	<div class="row"><div class="col-12">
		' . $liveform_messages . '
		' . ($can_seal
			? ''
			: '<div class="alert theme-danger">' . h(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.')) . '</div>') . '
	</div></div>

	<div class="row g-5">
		<div class="col-12 lg:col-8 order-1">
			' . ((($attention_rows !== '') && ($filter !== 'connected') && ($filter !== 'not_connected'))
				? '<section class="pg-card connections-attention p-5 mb-6" aria-labelledby="connections-attention-title">
					<h2 class="pg-title-section mb-1" id="connections-attention-title">' . h(lang('Needs attention')) . '<span class="pg-count">' . (int) $attention_count . '</span></h2>
					<p class="pg-card-sub mb-4">' . h(lang('These connections stopped working; the features that use them wait until they are connected again.')) . '</p>
					<div class="pg-card-inner">' . $attention_rows . '</div>
				</section>'
				: '') . '
			' . $groups_html . '
		</div>
	</div>

	<form method="post" action="connections.php" id="connections_form" class="disable_shortcut d-none">
		' . get_token_field() . '
		<input type="hidden" name="connections_action" value="" id="connections_action">
		<input type="hidden" name="connection_id" value="" id="connections_id">
	</form>

	<form method="post" action="connect.php" id="connections_connect_form" class="disable_shortcut d-none">
		' . pg_connect_start_fields('', 'account', 'connections.php') . '
	</form>
</main>

<script>
(function () {

	// A provider row opens its panel under it: its accounts and application.
	var openPanel = function (toggle, open) {
		var panel = document.getElementById(toggle.getAttribute("aria-controls"));
		toggle.setAttribute("aria-expanded", open ? "true" : "false");
		panel.hidden = !open;
	};

	// The panel a refused save, an address (#connections-google) or the
	// return from a provider names is open when the screen opens.
	document.querySelectorAll(".connections-toggle").forEach(function (toggle) {
		var id = toggle.getAttribute("aria-controls");
		var panel = document.getElementById(id);
		if (panel.dataset.open === "1" || window.location.hash === "#" + id) {
			toggle.closest(".connections-provider").hidden = false;
			openPanel(toggle, true);
		}
	});

	// Buttons post through forms outside the panels, so that no panel holds
	// a form inside another and each destructive one asks first.
	document.addEventListener("click", function (e) {

		var toggle = e.target.closest(".connections-toggle");

		if (toggle) {
			openPanel(toggle, toggle.getAttribute("aria-expanded") !== "true");
			return;
		}

		var more = e.target.closest(".connections-more");

		if (more) {
			var list = more.closest(".connections-list");
			list.querySelectorAll(":scope > [hidden]").forEach(function (row) { row.hidden = false; });
			more.parentNode.remove();
			return;
		}

		var action = e.target.closest(".connections-action");

		if (action) {
			if (action.dataset.confirm && !window.confirm(action.dataset.confirm)) { return; }
			document.getElementById("connections_action").value = action.dataset.action;
			document.getElementById("connections_id").value = action.dataset.id;
			document.getElementById("connections_form").submit();
			return;
		}

		var connect = e.target.closest(".connections-connect");

		if (connect) {
			var form = document.getElementById("connections_connect_form");
			form.elements.provider.value = connect.dataset.provider;
			form.elements.purpose.value = connect.dataset.purpose || "account";
			form.elements.connection_id.value = connect.dataset.connection;
			form.submit();
			return;
		}

		var copy = e.target.closest(".connections-copy");

		if (copy) {
			// The button keeps its label; only the icon changes and the
			// visible status under the field says whether the copy happened.
			var field = document.getElementById(copy.dataset.target);
			var status = copy.closest(".input-group").nextElementSibling;
			var icon = copy.querySelector("i");
			var done = function () {
				icon.className = "bi bi-check2 me-1";
				status.textContent = status.getAttribute("data-copied");
				setTimeout(function () {
					icon.className = "bi bi-clipboard me-1";
					status.textContent = "";
				}, 1500);
			};
			// Plain http has no clipboard API and a write can be refused:
			// copy the selected text the old way; when that fails too, leave
			// it selected and say so.
			var fallback = function () {
				var copied = false;
				field.focus();
				field.select();
				try { copied = document.execCommand("copy"); } catch (error) {}
				if (copied) {
					done();
				} else {
					icon.className = "bi bi-exclamation-triangle me-1";
					status.textContent = status.getAttribute("data-refused");
				}
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(field.value).then(done, fallback);
			} else {
				fallback();
			}
		}

	});

})();
</script>' . output_footer();
