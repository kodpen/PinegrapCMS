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

// Asks the provider whether one of the site's accounts still works, and says so.
function connections_check_account($account, $liveform) {

	$check = pg_connect_check($account);

	if ($check['ok']) {

		$liveform->add_notice(lang(array('string' => '{var:1} works.', 'vars' => h($account['label']))));

	} else {

		$liveform->add_error(lang(array('string' => '{var:1} does not work: {var:2}', 'vars' => array(h($account['label']), h($check['error'])))));

	}

}

// The Workspace's view of the user, for the GitHub repository links, whose
// gate is the Workspace settings right; null when the Workspace is off.
function connections_workspace_viewer($user) {

	if (!(defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php'))) {
		return null;
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

	return (function_exists('ws_ready') && ws_ready()) ? ws_viewer($user) : null;

}

// May the user change an AI provider's connection? Its key came from the
// assistants' card, so it keeps that card's gate: the Assistants and agents
// category of Site Settings and the Workspace's administrator, who alone
// changes the assistants.
function connections_ai_may_manage($user) {

	if (!defined('PG_SETTINGS_MENU')) {
		define('PG_SETTINGS_MENU', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	$categories = pg_settings_categories_for($user, null, pg_settings_categories());

	if (!isset($categories[pg_ai_connection_category()])) {
		return false;
	}

	$viewer = connections_workspace_viewer($user);

	return is_array($viewer) && ((int) $viewer['role'] === 0) && function_exists('ws_model_providers');

}

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	$action = isset($_POST['connections_action']) ? (string) $_POST['connections_action'] : '';

	// A GitHub repository link saved or removed on GitHub's page, under the
	// gate of the card it came from.
	$ws_action = (isset($_POST['ws_action']) && is_string($_POST['ws_action'])) ? $_POST['ws_action'] : '';

	if (in_array($ws_action, array('github_link_save', 'github_link_remove'), true)) {

		$ws_viewer = connections_workspace_viewer($user);

		if (is_array($ws_viewer) && ws_github_links_ready() && ws_github_links_can_manage($ws_viewer)) {
			ws_github_links_settings_post($ws_viewer, $ws_action, $liveform);
		}

		header('Location: ' . $screen_url . '?service=github#connections-repositories');
		exit();

	}

	// An AI provider's connection: its key saved, tried or removed, or an
	// assistant's own key given up for it.
	if (in_array($action, array('ai_save', 'ai_remove', 'ai_use_connection'), true)) {

		$ai_service = (isset($_POST['connections_return']) && is_string($_POST['connections_return'])) ? $_POST['connections_return'] : '';
		$ai_services = pg_ai_connection_services();

		if (isset($ai_services[$ai_service]) && connections_ai_may_manage($user)) {

			$ai_provider = $ai_services[$ai_service];
			$ai_name = ws_model_provider_name($ai_provider);

			if ($action === 'ai_save') {

				$ai_key = (isset($_POST['ai_key']) && is_string($_POST['ai_key'])) ? trim($_POST['ai_key']) : '';
				$ai_saved = false;

				if (($ai_key === '') && !pg_ai_connection_ready($ai_provider)) {
					$liveform->add_error(lang('Paste the service\'s API key.'));
				} elseif (($ai_key !== '') && !pg_ai_connection_key_shape_ok($ai_key)) {
					$liveform->add_error(lang('The key is 8 to 512 characters without spaces.'));
				} elseif (($ai_key !== '') && !pg_ai_connection_save($ai_provider, $ai_name, $ai_key)) {
					$liveform->add_error(lang('This site has no encryption key, so it cannot keep the key.'));
				} else {
					$ai_saved = true;

					if ($ai_key !== '') {
						$liveform->add_notice(lang(array('string' => 'The {var:1} key was saved.', 'vars' => $ai_name)));
						log_activity(lang(array('string' => 'changed the {var:1} connection', 'vars' => $ai_name)), $_SESSION['sessionusername']);
					}
				}

				// Save and try: the key just saved, or the stored one, is
				// shown to the provider.
				if ($ai_saved && !empty($_POST['submit_try'])) {

					$ai_try = pg_ai_connection_try($ai_provider, pg_ai_connection_key($ai_provider));

					if ($ai_try['ok'] === false) {
						$liveform->add_error(h($ai_try['message']));
					} else {
						$liveform->add_notice(h($ai_try['message']));
					}

				}

			} elseif ($action === 'ai_remove') {

				pg_ai_connection_remove($ai_provider);

				$liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => h($ai_name))));
				log_activity(lang(array('string' => 'removed the {var:1} connection', 'vars' => $ai_name)), $_SESSION['sessionusername']);

			} else {

				$ai_agent = ws_model_agent_row(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

				if (is_array($ai_agent) && ((string) $ai_agent['provider'] === $ai_provider) && pg_ai_connection_ready($ai_provider)) {

					db("UPDATE ws_agents SET key_sealed = NULL, updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $ai_agent['id'] . "' AND kind = 'model'");

					$liveform->add_notice(lang(array('string' => '{var:1} uses the connection\'s key now.', 'vars' => h($ai_agent['name']))));
					log_activity(lang(array('string' => 'set the assistant {var:1} to use the {var:2} connection', 'vars' => array($ai_agent['name'], $ai_name))), $_SESSION['sessionusername']);

				}

			}

		}

	}

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

				// Save and try: each of the site's accounts of the provider is
				// checked through the application just saved.
				if (!empty($_POST['submit_try'])) {

					$tried = 0;

					foreach (pg_connect_accounts(array('provider' => $provider_key, 'user_id' => 0)) as $account) {

						$tried++;
						connections_check_account($account, $liveform);

					}

					if ($tried === 0) {
						$liveform->add_notice(lang('No account is connected yet, so nothing was tried.'));
					}

				}

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
			connections_check_account($account, $liveform);

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

	// A write from a service's page goes back to that page.
	$return_service = (isset($_POST['connections_return']) && is_string($_POST['connections_return'])
		&& preg_match('/^[a-z0-9_]{1,60}$/', $_POST['connections_return'])) ? $_POST['connections_return'] : '';

	if ($return_service !== '') {
		header('Location: ' . $screen_url . '?service=' . $return_service);
		exit();
	}

	// A provider whose application is set up on a service's page returns there.
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

	$page_service = array_search($return_provider, pg_connections_screen_page_providers(), true);

	if ($page_service !== false) {
		header('Location: ' . $screen_url . '?service=' . $page_service);
		exit();
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
		return '<span class="pg-pill pg-tone-amber">' . h(lang('Must be connected again')) . '</span>';
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
$ai_services = pg_ai_connection_services();

foreach ($registry as $id => $service) {

	if (!pg_connections_screen_may_see($service['place'], $open_categories, $tool_labels, $is_manager)) {
		continue;
	}

	// An AI provider's connection keeps the gate of the assistants' card its
	// key came from.
	if (isset($ai_services[$id]) && empty($open_categories[pg_ai_connection_category()])) {
		continue;
	}

	$place = connections_place($service['place'], $category_list, $tool_labels);
	$uses = lang($service['uses']);

	// A name is source text: a product's own name stays, a descriptive one
	// ("Sign in with Google") is translated.
	$name = lang($service['name']);

	$rows[$id] = array(
		'kind'   => 'service',
		'name'   => $name,
		'group'  => $service['group'],
		'state'  => pg_connections_screen_state($service, $facts),
		'line'   => $place['label'],
		'uses'   => $uses,
		'url'    => $place['url'],
		'search' => array($name, $service['name'], $group_labels[$service['group']], $uses, $service['uses'], $place['label']),
	);

	if (isset($service['state']['kind']) && ($service['state']['kind'] === 'account')) {
		$provider_uses[$service['state']['provider']][] = $name;
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

// A service's own page (connections.php?service=<key>); a key the user may
// not see, or an account provider's, leads back to the list.
$page_id = pg_connections_screen_page_id(isset($_GET['service']) ? $_GET['service'] : null, $rows);

if (isset($_GET['service']) && ($page_id === '')) {
	header('Location: ' . $screen_url);
	exit();
}

// The seven mail routes are one row on the list, opening Site Settings ›
// E-mail sending; a route's name still finds it, and a route's own page
// stays reachable by its address.
$list_rows = pg_connections_screen_fold_mail($rows, array(
	'kind'   => 'settings',
	'name'   => lang('E-mail sending'),
	'group'  => 'email',
	'line'   => lang('Site Settings') . ' › ' . lang('E-mail sending'),
	'uses'   => '',
	'url'    => pg_settings_link('mail_sending'),
	'search' => array(lang('E-mail sending'), 'E-mail sending', $group_labels['email']),
));

$counts = pg_connections_screen_counts(pg_connections_screen_search($list_rows, $query));
$shown = pg_connections_screen_filter(pg_connections_screen_search($list_rows, $query), $filter);
$order = pg_connections_screen_order($shown, array_keys($group_labels));
$folding = ($query === '') && ($filter === '');

/* An AI provider's page: "Connection details" holds its key for a user
   who may change the assistants, with Remove the key under Status; "Where
   it is used" lists the assistants of the provider, each with the key it
   calls with and, when it keeps one of its own, "Use the connection's
   key". */

function connections_ai_page($page, $id, $name, $provider, $may_manage, $can_seal) {

	$ready = pg_ai_connection_ready($provider);
	$agents_url = pg_settings_link(pg_ai_connection_category(), 'pgset-ws-agents');

	if ($may_manage && in_array('details', $page['cards'], true)) {

		$terms = function_exists('ws_model_provider_terms') ? ws_model_provider_terms($provider) : '';

		$page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang(array('string' => 'The {var:1} key every assistant of this provider uses, unless it keeps one of its own.', 'vars' => $name))) . '</p>
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="ai_save">
				<input type="hidden" name="connections_return" value="' . h($id) . '">
				<div>
					<label class="pg-field-label" for="connections_ai_key">' . h(lang('API key')) . '</label>
					<input type="password" class="pg-input font-monospace" id="connections_ai_key" name="ai_key" value="" maxlength="512" autocomplete="new-password"
						placeholder="' . h($ready ? lang('Stored. Leave empty to keep it.') : '') . '">
					' . (($terms !== '') ? '<div class="pg-field-help">' . h(lang($terms)) . '</div>' : '') . '
				</div>
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" name="submit_save" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
					<button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save and try')) . '</button>
				</div>
			</form>
		</section>';

		if ($ready) {
			$page['status_extra'] = '
			<div class="mt-4">
				<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action" data-action="ai_remove" data-id="0"
					data-confirm="' . h(lang(array('string' => 'The {var:1} key is removed: the assistants without a key of their own stop until it is typed again.', 'vars' => $name))) . '">
					<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
			</div>';
		}

	}

	$rows = '';

	foreach (pg_ai_connection_users($provider, function_exists('ws_model_agents') ? ws_model_agents() : array()) as $agent) {

		if ($agent['own']) {
			$line = lang('Uses its own key');
			$side = $may_manage && $ready
				? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-action" data-action="ai_use_connection" data-id="' . $agent['id'] . '"
					data-confirm="' . h(lang(array('string' => '{var:1} gives up its own key and calls with the connection\'s key.', 'vars' => $agent['name']))) . '">' . h(lang('Use the connection\'s key')) . '</button>'
				: connections_state_pill('connected');
		} else {
			$line = lang('Uses the connection\'s key');
			$side = connections_state_pill($ready ? 'connected' : 'not_connected');
		}

		$rows .= '
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<a class="connections-row-name" href="' . h($agents_url) . '">' . h($agent['name']) . '</a>
						<span class="pg-text-meta d-block">' . h(lang('Workspace assistant')) . ' · ' . h($line) . '</span>
					</span>
					<span class="connections-row-side">' . $side . '</span>
				</div>';

	}

	$page['uses'] = ($rows !== '') ? $rows : '
				<a class="pg-card-inner-row connections-row" href="' . h($agents_url) . '">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(lang('Workspace assistants')) . '</span>
						<span class="pg-text-meta d-block">' . h(lang('No assistant uses it yet.')) . '</span>
					</span>
					<i class="bi bi-chevron-right connections-row-chevron" aria-hidden="true"></i>
				</a>';

	return $page;

}

/* Why a provider's application cannot connect yet; '' when it can. */

function connections_app_hint($app) {

	$option_invalid = is_array($app) && isset($app['options_valid']) && !$app['options_valid'];

	if (pg_connect_app_ready($app)) {
		$hint = '';
	} elseif ($option_invalid) {
		$hint = lang('The directory (tenant) must be common, organizations, consumers, a domain name or a directory ID.');
	} elseif (is_array($app) && !$app['enabled']) {
		$hint = lang('Connections through this application are off. Turn them on with the switch below.');
	} else {
		$hint = lang('Set up the application below first.');
	}

	return $hint;

}

/* A provider's application: the redirect address, the client and its
   options, saved by save_app. $return_service is the service page the save
   returns to ('' in the provider's drawer). */

function connections_app_form($provider_key, $provider, $app, $provider_draft, $can_seal, $redirect_uri, $return_service = '') {

	$ready = pg_connect_app_ready($app);

	$option_fields = '';

	foreach ($provider['options'] as $option_key => $option) {

		if (is_array($provider_draft) && isset($provider_draft['options'][$option_key])) {
			$value = (string) $provider_draft['options'][$option_key];
		} else {
			$value = (is_array($app) && isset($app['options'][$option_key])) ? (string) $app['options'][$option_key] : (string) $option['default'];
		}

		$option_bad = is_array($provider_draft) && ($provider_draft['invalid'] === $option_key);

		$option_fields .= '
			<div>
				<label class="pg-field-label" for="option_' . h($provider_key . '_' . $option_key) . '">' . h($option['label']) . '</label>
				<input type="text" class="pg-input' . ($option_bad ? ' is-invalid' : '') . '" id="option_' . h($provider_key . '_' . $option_key) . '"
					name="option_' . h($option_key) . '" value="' . h($value) . '" maxlength="100" autocomplete="off"' . ($option_bad ? ' aria-invalid="true"' : '') . '>
				<div class="pg-field-help">' . h($option['help']) . '</div>
			</div>';

	}

	$setup_steps = '';

	foreach ($provider['setup'] as $step) {
		$setup_steps .= '<li>' . h($step) . '</li>';
	}

	// The Sign in with Google application's secret counts as stored: saving
	// the form unchanged keeps it (pg_connect_app_save()).
	$secret_stored = is_array($app) && $app['secret_stored'];

	$client_id_value = is_array($provider_draft) ? $provider_draft['client_id'] : (is_array($app) ? $app['client_id'] : '');

	$client_id_bad = is_array($provider_draft) && ($provider_draft['invalid'] === 'client_id');

	$enabled_checked = is_array($provider_draft) ? $provider_draft['enabled'] : (!is_array($app) || $app['enabled']);

	$can_fall_back = ($provider_key === 'google') && is_array($app) && ($app['source'] === 'connect') && (pg_signin_google_app() !== null);

	$field_id = function ($name) use ($provider_key) {
		return h($name . '_' . $provider_key);
	};

	// On a service's page Save is the one filled button and Save and try
	// checks the site's accounts after the save; in the drawer Connect is.
	$buttons = ($return_service !== '')
		? '<button type="submit" name="submit_save" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
				<button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save and try')) . '</button>'
		: '<button type="submit" name="submit_save" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>';

	return '
		<form method="post" action="connections.php" class="connections-app-form">
			' . get_token_field() . '
			<input type="hidden" name="connections_action" value="save_app">
			<input type="hidden" name="provider" value="' . h($provider_key) . '">
			' . (($return_service !== '') ? '<input type="hidden" name="connections_return" value="' . h($return_service) . '">' : '') . '
			<div class="d-flex align-items-center gap-3">
				<input type="checkbox" class="pg-switch" role="switch" name="enabled" value="1" id="' . $field_id('enabled') . '"' . ($enabled_checked ? ' checked' : '') . '>
				<label class="pg-text-2" for="' . $field_id('enabled') . '">' . h(lang('Connections through this application are allowed')) . '</label>
			</div>
			' . ((is_array($app) && ($app['source'] === 'signin'))
				? '<div class="alert theme-info small py-3 mb-0">' . h(lang('This is the application of Sign in with Google (Security settings). Add the redirect address below to it in Google Cloud Console, or save an application of its own here.')) . '</div>'
				: '') . '
			' . ($ready
				? '<details><summary class="pg-text-2">' . h(lang('How to set it up')) . '</summary><ol class="pg-text-2 ps-5 mt-3 mb-0">' . $setup_steps . '</ol></details>'
				: '<ol class="pg-text-2 ps-5 mb-0">' . $setup_steps . '</ol>') . '
			<div>
				<label class="pg-field-label" for="' . $field_id('redirect') . '">' . h(lang('Redirect address')) . '</label>
				<div class="d-flex align-items-center gap-2">
					<input type="text" class="pg-input flex-grow-1 min-w-0" id="' . $field_id('redirect') . '" value="' . h($redirect_uri) . '" readonly>
					<button type="button" class="pg-button pg-button-quiet pg-button-sm connections-copy" data-target="' . $field_id('redirect') . '">
						<i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
				</div>
				<div class="pg-field-help connections-copy-status" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></div>
			</div>
			<div>
				<label class="pg-field-label" for="' . $field_id('client_id') . '">' . h(lang('Client ID')) . '</label>
				<input type="text" class="pg-input' . ($client_id_bad ? ' is-invalid' : '') . '" id="' . $field_id('client_id') . '" name="client_id"
					value="' . h($client_id_value) . '" maxlength="255" autocomplete="off"' . ($client_id_bad ? ' aria-invalid="true"' : '') . '>
			</div>
			<div>
				<label class="pg-field-label" for="' . $field_id('client_secret') . '">' . h(lang('Client secret')) . '</label>
				<input type="password" class="pg-input" id="' . $field_id('client_secret') . '" name="client_secret"
					value="" autocomplete="new-password"
					placeholder="' . h($secret_stored ? lang('Stored. Leave empty to keep it.') : '') . '">
				' . ((is_array($provider_draft) && $provider_draft['had_secret'])
					? '<div class="pg-field-help pg-field-error">' . h(lang('The secret you entered was not saved. Enter it again.')) . '</div>'
					: '') . '
			</div>
			' . $option_fields . '
			<div class="d-flex flex-wrap align-items-center gap-3">
				' . $buttons . '
				<a class="pg-button pg-button-sm pg-button-quiet" href="' . h($provider['console_url']) . '" target="_blank" rel="noopener">
					<i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Open the {var:1} console', 'vars' => $provider['label']))) . '</a>
				' . ($can_fall_back
					? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-action" data-action="remove_app" data-id="0"
						data-confirm="' . h(lang('The application saved here is removed and Google features use the Sign in with Google application of the Security settings again. Connected accounts stay.')) . '">
						<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Use the Sign in with Google application')) . '</button>'
					: '') . '
			</div>
		</form>';

}

/* A provider's drawer: its application and its accounts, with the buttons
   that connect, check and end them. */

function connections_provider_drawer($provider_key, $provider, $app, $accounts, $provider_draft, $can_seal, $redirect_uri) {

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
						<div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
							<span class="fw-semibold text-break">' . pg_connections_screen_address_html($account['label']) . '</span>
							' . connections_status_badge($account['status']) . '
						</div>
						<div class="pg-text-meta">' . h(implode(' · ', $purpose_labels)) . '</div>
						<div class="pg-text-meta">' . h(implode(' · ', $meta)) . '</div>
						' . (($account['last_error'] !== '')
							? '<div class="alert theme-warning small py-3 mt-2 mb-0 text-break">' . h($account['last_error']) . '</div>'
							: '') . '
					</div>
				</div>
				<div class="d-flex flex-wrap gap-3 mt-3 ms-7 ps-3">
					' . (($account['status'] === 'reauth')
						? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-connect"
							data-provider="' . h($provider_key) . '" data-purpose="' . h($connect_purpose) . '" data-connection="' . $account['id'] . '"' . ($ready ? '' : ' disabled') . '>
							<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Connect again')) . '</button>'
						: '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-action"
							data-action="check" data-id="' . $account['id'] . '">
							<i class="bi bi-activity me-1" aria-hidden="true"></i>' . h(lang('Check')) . '</button>') . '
					<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action"
						data-action="disconnect" data-id="' . $account['id'] . '"
						data-confirm="' . h(lang(array('string' => 'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.', 'vars' => $account['label']))) . '">
						<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
				</div>
			</li>';

	}

	$app_form = connections_app_form($provider_key, $provider, $app, $provider_draft, $can_seal, $redirect_uri);
	$hint = connections_app_hint($app);

	if ($ready) {
		$app_pill = '<span class="pg-pill pg-tone-teal">' . h(lang('Ready')) . '</span>';
	} elseif (is_array($app) && !$app['enabled']) {
		$app_pill = '<span class="pg-pill pg-tone-gray">' . h(lang('Off')) . '</span>';
	} else {
		$app_pill = '<span class="pg-pill pg-tone-gray">' . h(lang('Not set up')) . '</span>';
	}

	$sub = lang('Account provider') . (count($accounts) ? ' · ' . lang(array('string' => '{var:1} connected', 'vars' => pg_format_number(count($accounts), 0))) : '');

	return '
		<dialog class="drawer drawer-end connections-drawer" id="connections-' . h($provider_key) . '" aria-labelledby="connections-' . h($provider_key) . '-title"' . (is_array($provider_draft) ? ' data-open="1"' : '') . '>
			<div class="drawer-header">
				<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi ' . h($provider['icon']) . '"></i></span>
				<div class="connections-drawer-head">
					<h2 class="pg-title-status mb-0" id="connections-' . h($provider_key) . '-title" lang="en">' . h($provider['label']) . '</h2>
					<div class="pg-text-meta">' . h($sub) . '</div>
				</div>
				<button type="button" class="pg-icon-button" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '" title="' . h(lang('Close')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
			</div>
			<div class="drawer-body">
				<section class="pg-report-section">
					<h3 class="pg-report-section-title d-flex align-items-center gap-3">' . h(lang('Application')) . ' ' . $app_pill . '</h3>
					' . (($hint === '') ? '' : '<p class="pg-field-help mt-0">' . h($hint) . '</p>') . '
					' . $app_form . '
				</section>
				<section class="pg-report-section">
					<h3 class="pg-report-section-title">' . h(lang('Connected accounts')) . '<span class="pg-count">' . count($accounts) . '</span></h3>
					' . (($account_rows !== '')
						? '<ul class="list-group list-group-flush mb-0">' . $account_rows . '</ul>'
						: '<p class="pg-text-2 mb-0">' . h(lang(array('string' => 'No {var:1} account is connected.', 'vars' => $provider['label']))) . '</p>') . '
				</section>
			</div>
			<div class="drawer-footer">
				<button type="button" class="pg-button pg-button-fill connections-connect"
					data-provider="' . h($provider_key) . '" data-purpose="' . h($connect_purpose) . '" data-connection="0"' . (($ready && $can_seal) ? '' : ' disabled') . '>
					<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $provider['label']))) . '</button>
				<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>
			</div>
		</dialog>';

}

/* A service's own page, its cards in pg_connections_screen_page_cards()'s
   order. "Status": its state, the accounts it stands on and their last
   error where one is stored, Connect again and Disconnect for the site's
   accounts, and the one place of its settings. "Connection details": the
   application of a provider whose page this is ($page['details']). "Where
   it is used", read from the registry, with $page['note'] under it. Then
   the card of what the service holds ($page['own']: GitHub's repository
   links). A service whose settings are still elsewhere has only Status and
   Where it is used. */

function connections_service_page($id, $service, $row, $tile, $facts, $providers, $provider_apps, $can_seal, $page) {

	$state = isset($service['state']) ? $service['state'] : null;
	$accounts = pg_connections_screen_accounts_for($state, $facts['accounts']);
	$extra = '';

	$facts_html = '<dt>' . h(lang('Status')) . '</dt><dd>' . connections_state_pill($row['state']) . '</dd>';

	if ($row['state'] === 'none') {
		$facts_html .= '<dt>' . h(lang('Why it shows no state')) . '</dt><dd>' . h(lang($service['no_state'])) . '</dd>';
	}

	if (pg_connections_screen_keeps_errors($state)) {

		$provider_key = (string) $state['provider'];
		$ready = isset($provider_apps[$provider_key]) && pg_connect_app_ready($provider_apps[$provider_key]);
		$account_rows = '';

		foreach ($accounts as $account) {

			$buttons = '';

			// Only the site's own accounts are reconnected or ended here; a
			// person's own account is theirs.
			if ($account['user_id'] === 0) {
				$buttons = '
					<div class="d-flex flex-wrap gap-3 mt-3">
						' . (($account['status'] === 'reauth')
							? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-connect" data-provider="' . h($provider_key) . '"
								data-purpose="' . h($state['purpose']) . '" data-connection="' . $account['id'] . '"' . (($ready && $can_seal) ? '' : ' disabled') . '>
								<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Connect again')) . '</button>'
							: '') . '
						<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action"
							data-action="disconnect" data-id="' . $account['id'] . '"
							data-confirm="' . h(lang(array('string' => 'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.', 'vars' => $account['label']))) . '">
							<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
					</div>';
			} else {
				$buttons = '<div class="pg-text-meta">' . h(lang('A person\'s own account: they connect it again.')) . '</div>';
			}

			$account_rows .= '
				<div class="pg-card-inner-row d-block">
					<div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
						<span class="fw-semibold text-break">' . pg_connections_screen_address_html($account['label']) . '</span>
						' . connections_status_badge($account['status']) . '
					</div>
					' . $buttons . '
				</div>';

		}

		$error = pg_connections_screen_last_error($accounts);

		$extra = '
			<div class="mt-4">
				<div class="pg-field-label">' . h(lang('Account')) . '</div>
				' . (($account_rows !== '')
					? '<div class="pg-card-inner connections-list">' . $account_rows . '</div>'
					: '<div class="pg-text-2">' . h(lang('None connected')) . '</div>') . '
			</div>
			<div class="mt-4">
				<div class="pg-field-label">' . h(lang('Last error')) . '</div>
				' . (($error !== '')
					? '<div class="alert theme-warning small py-3 mb-0 text-break">' . h($error) . '</div>'
					: '<div class="pg-text-2">' . h(lang('No error on file')) . '</div>') . '
			</div>';

		if ($page['details'] !== '') {
			// The application is set up on this page, so its accounts are
			// connected here too.
			$extra .= '
			<div class="mt-4">
				<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-connect" data-provider="' . h($provider_key) . '"
					data-purpose="' . h($state['purpose']) . '" data-connection="0"' . (($ready && $can_seal) ? '' : ' disabled') . '>
					<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $providers[$provider_key]['label']))) . '</button>
			</div>';
		} elseif (isset($providers[$provider_key])) {
			$extra .= '
			<p class="pg-text-meta mt-4 mb-0">' . h(lang(array('string' => 'Accounts are connected in the {var:1} panel of the Connections list.', 'vars' => $providers[$provider_key]['label']))) . '
				<a href="connections.php#connections-' . h($provider_key) . '">' . h(lang('Connections')) . ' › ' . h($providers[$provider_key]['label']) . '</a></p>';
		}

	}

	$on_card = (substr($service['place'], 0, 1) === '#');

	$extra .= $page['status_extra'];

	// Where its settings are, unless they are on this page.
	$place = (($page['details'] !== '') || $page['here']) ? '' : '
			<div class="mt-5">
				<p class="pg-text-2 mb-1">' . h($on_card
					? lang('This service\'s settings are on its card in Site Settings; here only its state is shown.')
					: lang('This service\'s settings are on a screen of their own; here only its state is shown.')) . '</p>
				<p class="pg-text-meta mb-3">' . h($row['line']) . '</p>
				<a class="pg-button pg-button-sm pg-button-quiet" href="' . h($row['url']) . '">' . h(lang('Open its settings')) . ' <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>
			</div>';

	// The use's row leads to where it is set up: the card below on this
	// page, else the place of its settings; with no card the viewer may
	// open, it is no link.
	$use_inner = '
					<span class="connections-row-main">
						<span class="connections-row-name">' . h($row['uses']) . '</span>
						<span class="pg-text-meta d-block">' . h(($page['own'] !== '') ? lang('On this page') : $row['line']) . '</span>
					</span>
					<span class="connections-row-side">' . connections_state_pill($row['state']) . '</span>';

	if ($page['uses'] !== '') {
		$use_row = $page['uses'];
	} elseif ($page['own'] !== '') {
		$use_row = '<a class="pg-card-inner-row connections-row" href="#connections-repositories">' . $use_inner . '
					<i class="bi bi-chevron-right connections-row-chevron" aria-hidden="true"></i></a>';
	} elseif ($page['details'] !== '') {
		$use_row = '<div class="pg-card-inner-row connections-row">' . $use_inner . '</div>';
	} else {
		$use_row = '<a class="pg-card-inner-row connections-row" href="' . h($row['url']) . '">' . $use_inner . '
					<i class="bi bi-chevron-right connections-row-chevron" aria-hidden="true"></i></a>';
	}

	$cards = array(
		'status' => '
		<section class="pg-card p-5 mb-6" aria-labelledby="connections-page-status">
			<div class="d-flex align-items-center gap-3 mb-4">
				' . $tile . '
				<h2 class="pg-title-section mb-0" id="connections-page-status">' . h(lang('Status')) . '</h2>
			</div>
			<dl class="pg-kv mb-0">' . $facts_html . '</dl>' . $extra . $place . '
		</section>',
		'details' => $page['details'],
		'uses' => '
		<section class="pg-card p-5 mb-6" aria-labelledby="connections-page-uses">
			<h2 class="pg-title-section mb-4" id="connections-page-uses">' . h(lang('Where it is used')) . '</h2>
			<div class="pg-card-inner connections-list">
				' . $use_row . '
			</div>' . $page['note'] . '
		</section>',
		'repositories' => $page['own'],
	);

	$html = '';

	foreach ($page['cards'] as $card) {
		$html .= isset($cards[$card]) ? $cards[$card] : '';
	}

	return $html;

}

/* The attention block: every connected account that stopped working, the
   site's own with Connect again, a person's own with who reconnects it. */

$attention_rows = '';

foreach ($facts['accounts'] as $account) {

	if (($account['status'] !== 'reauth') || !isset($providers[$account['provider']])) {
		continue;
	}

	$used_by = array();

	foreach ($registry as $service) {
		if (isset($service['state']['kind']) && ($service['state']['kind'] === 'account') && ($service['state']['provider'] === $account['provider'])
			&& in_array($service['state']['purpose'], $account['purposes'], true)) {
			$used_by[] = lang($service['name']);
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
				<div class="pg-text-2 text-break">' . pg_connections_screen_address_html(lang(array('string' => '{var:1} must be connected again', 'vars' => $account['label']))) . '</div>
				<div class="pg-text-meta">' . h(implode(' · ', $meta)) . '</div>
			</div>
			<div class="connections-row-side">
				' . (($account['user_id'] === 0)
					? '<button type="button" class="pg-button pg-button-sm pg-button-quiet connections-connect" data-provider="' . h($account['provider']) . '"
						data-purpose="' . h(!empty($provider['connect_purpose']) ? $provider['connect_purpose'] : 'account') . '" data-connection="' . $account['id'] . '"'
						. ((pg_connect_app_ready($provider_apps[$account['provider']]) && $can_seal) ? '' : ' disabled') . '>' . h(lang('Connect again')) . '</button>'
					: '<span class="pg-text-meta">' . h(lang('A person\'s own account: they connect it again.')) . '</span>') . '
			</div>
		</div>';

}

/* The groups, and the drawers their rows open. */

$groups_html = '';
$drawers = '';

foreach ($order as $group_key => $ids) {

	$states = array();

	foreach ($ids as $id) {
		$states[] = $list_rows[$id]['state'];
	}

	// The providers are the screen's own rows and never fold.
	$fold_at = ($folding && ($group_key !== 'providers')) ? pg_connections_screen_fold_at($states) : count($ids);
	$group_rows = '';

	foreach ($ids as $index => $id) {

		$row = $list_rows[$id];
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
				<span class="connections-row-side">' . connections_state_pill($row['state']) . '
					<span class="pg-button pg-button-sm pg-button-quiet">' . h((($row['kind'] !== 'settings') && (pg_connections_screen_action($row['state']) === 'connect')) ? lang('Connect') : lang('Manage')) . '</span>
				</span>';

		$page_service = ($row['kind'] === 'provider') ? array_search($row['provider'], pg_connections_screen_page_providers(), true) : false;

		if (($page_service !== false) && isset($rows[$page_service])) {

			// The provider's application is set up on its service's page.
			$group_rows .= '
			<a class="pg-card-inner-row connections-row" href="' . h(pg_connections_screen_page_url($page_service)) . '"' . $hidden . '>'
				. $inner . '
			</a>';

		} elseif ($row['kind'] === 'provider') {

			$provider_key = $row['provider'];
			$provider_draft = (is_array($draft) && ($draft['provider'] === $provider_key)) ? $draft : null;

			$group_rows .= '
			<button type="button" class="pg-card-inner-row connections-row connections-open" data-drawer="connections-' . h($provider_key) . '" aria-haspopup="dialog"' . $hidden . '>'
				. $inner . '
			</button>';

			$drawers .= connections_provider_drawer($provider_key, $providers[$provider_key], $provider_apps[$provider_key],
				isset($site_accounts[$provider_key]) ? $site_accounts[$provider_key] : array(), $provider_draft, $can_seal, $redirect_uri);

		} elseif ($row['kind'] === 'settings') {

			// The folded mail routes open their Site Settings category.
			$group_rows .= '
			<a class="pg-card-inner-row connections-row" href="' . h($row['url']) . '"' . $hidden . '>'
				. $inner . '
			</a>';

		} else {

			// A service opens its own page.
			$group_rows .= '
			<a class="pg-card-inner-row connections-row" href="' . h(pg_connections_screen_page_url($id)) . '"' . $hidden . '>'
				. $inner . '
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
			<a class="pg-button pg-button-sm pg-button-quiet" href="connections.php">' . h(lang('Clear search')) . '</a>
		</div>';
}

/* The other side: what connects to the site, counted as Application
   Access counts it, for a user who may open that screen. Nothing of it is
   copied here. */

$other_side = '';

if (isset($tool_labels['api_settings.php'])) {

	$other = pg_connections_screen_other_side();

	$other_side = '
		<div class="position-sticky" style="top:3.3rem;">
			<section class="pg-card p-5" id="connections-other-side" aria-labelledby="connections-other-side-title">
				<h2 class="pg-title-status mb-1" id="connections-other-side-title">' . h(lang('What connects to the site')) . '</h2>
				<p class="pg-card-sub mb-4">' . h(lang('Applications that connect to the site with a key, a device or OAuth are managed in Application Access.')) . '</p>
				<dl class="pg-kv mb-5">
					<dt>' . h(lang('Applications')) . '</dt><dd class="pg-tabular">' . pg_format_number($other['applications'], 0) . '</dd>
					<dt>' . h(lang('Devices')) . '</dt><dd class="pg-tabular">' . pg_format_number($other['devices'], 0) . '</dd>
					<dt>' . h(lang('Connected applications')) . '</dt><dd class="pg-tabular">' . pg_format_number($other['connected'], 0) . '</dd>
					<dt>' . h(lang('Webhook subscriptions')) . '</dt><dd class="pg-tabular">' . pg_format_number($other['webhooks'], 0) . '</dd>
				</dl>
				<a class="pg-button pg-button-quiet" href="api_settings.php">' . h(lang('Open Application Access')) . ' <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
			</section>
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
		<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search connections-search">
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

$messages = '
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
		' . ($can_seal
			? ''
			: '<div class="alert theme-danger">' . h(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.')) . '</div>') . '
	</div></div>';

if ($page_id !== '') {

	$page_row = $rows[$page_id];
	$page_group = $page_row['group'];

	$shell = pg_page_shell(array(
		'title'               => $page_row['name'] . ' - ' . lang('Connections'),
		'extra classes'       => 'pg-measure-edit',
		'icon'                => 'settings',
		'heading'             => $page_row['name'],
		'heading_description' => $group_labels[$page_group] . ' · ' . $page_row['uses'],
		'breadcrumb'          => array(
			array('label' => lang('System tools'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/system_tools.php'),
			array('label' => lang('Connections'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/connections.php'),
			array('label' => $page_row['name']),
		),
	));

	$page_providers = pg_connections_screen_page_providers();
	$page_provider = isset($page_providers[$page_id]) ? $page_providers[$page_id] : '';
	$ws_viewer = ($page_id === 'github') ? connections_workspace_viewer($user) : null;

	$page = array(
		'cards'   => pg_connections_screen_page_cards($page_id, array(
			'manager'      => $is_manager,
			'repositories' => is_array($ws_viewer) && ws_github_links_ready() && ws_github_links_can_manage($ws_viewer),
			'ai'           => isset($ai_services[$page_id]) && connections_ai_may_manage($user),
		)),
		'details'      => '',
		'note'         => '',
		'own'          => '',
		'uses'         => '',
		'status_extra' => '',
		'here'         => isset($ai_services[$page_id]),
	);

	// An AI provider's page: its key, and the assistants that use it.
	if (isset($ai_services[$page_id])) {
		$page = connections_ai_page($page, $page_id, $page_row['name'], $ai_services[$page_id], connections_ai_may_manage($user), $can_seal);
	}

	if (in_array('details', $page['cards'], true) && isset($providers[$page_provider])) {

		$hint = connections_app_hint($provider_apps[$page_provider]);

		$page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang(array('string' => 'The {var:1} application the site connects its accounts through.', 'vars' => $providers[$page_provider]['label']))) . '</p>
			' . (($hint === '') ? '' : '<p class="pg-field-help mt-0">' . h($hint) . '</p>') . '
			' . connections_app_form($page_provider, $providers[$page_provider], $provider_apps[$page_provider],
				(is_array($draft) && ($draft['provider'] === $page_provider)) ? $draft : null, $can_seal, $redirect_uri, $page_id) . '
		</section>';

	}

	if (in_array('repositories', $page['cards'], true)) {
		$page['own'] = ws_github_links_page_card(pg_connections_screen_page_url($page_id), $ws_viewer);
	}

	// The GitHub MCP connector is a connection of its own, with its own
	// token: the account connected here only manages repository webhooks.
	if ($page_id === 'github') {
		$page['note'] = '
			<p class="pg-text-meta mt-4 mb-0">' . h(lang('The GitHub MCP connector of the Workspace\'s assistants is a connection of its own, with its own token.')) . '
				' . (isset($rows['mcp_github'])
					? '<a href="' . h(pg_connections_screen_page_url('mcp_github')) . '">' . h(lang('GitHub MCP connector')) . '</a>'
					: '') . '</p>';
	}

	$main = $messages . '
	<div class="row g-5">
		<div class="col-12 lg:col-8 2xl:col-9">
			' . connections_service_page($page_id, $registry[$page_id], $page_row,
				'<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . $group_icons[$page_group][1] . '" aria-hidden="true"><i class="bi bi-' . $group_icons[$page_group][0] . '"></i></span>',
				$facts, $providers, $provider_apps, $can_seal, $page) . '
		</div>
	</div>';

	$drawers = '';
	$connect_return = pg_connections_screen_page_url($page_id);

} else {

	// No save bar: each provider saves its own application through its own form.
	$shell = pg_page_shell(array(
		'title'               => lang('Connections'),
		'extra classes'       => 'pg-measure-edit',
		'icon'                => 'settings',
		'heading'             => lang('Connections'),
		'heading_description' => lang('The outside services the site works with: what is connected, what stopped, and the one place of each one\'s settings.'),
		'breadcrumb'          => array(
			array('label' => lang('System tools'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/system_tools.php'),
			array('label' => lang('Connections')),
		),
	));

	$main = pg_screen_toolbar(array(
			'grow' => '<div class="connections-find">' . $search_form
				. '<div class="pg-seg pg-list-seg" role="group" aria-label="' . h(lang('Filter by state')) . '">' . $segments . '</div></div>',
		), array('label' => lang('Connections'))) . '
		' . (($counts['none'] > 0)
			? '<p class="pg-text-meta mb-5">' . h(lang(array('string' => '{var:1} services whose state cannot be read are listed only under All.', 'vars' => pg_format_number($counts['none'], 0)))) . '</p>'
			: '') . '

		' . $messages . '

		<div class="row g-5">
			<div class="col-12 lg:col-8 2xl:col-9 order-1">
				' . ((($attention_rows !== '') && ($query === '') && ($filter !== 'connected') && ($filter !== 'not_connected'))
					? '<section class="pg-card connections-attention p-5 mb-6" aria-labelledby="connections-attention-title">
						<h2 class="pg-title-section mb-1" id="connections-attention-title">' . h(lang('Needs attention')) . '</h2>
						<p class="pg-card-sub mb-4">' . h(lang('These connections stopped working; the features that use them wait until they are connected again.')) . '</p>
						<div class="pg-card-inner">' . $attention_rows . '</div>
					</section>'
					: '') . '
				' . $groups_html . '
			</div>
			<div class="col-12 lg:col-4 2xl:col-3 order-2">' . $other_side . '</div>
		</div>';

	$connect_return = 'connections.php';

}

echo $shell . '

<main id="content" class="container-fluid">
	' . $main . '

	' . $drawers . '

	<form method="post" action="connections.php" id="connections_form" class="disable_shortcut d-none">
		' . get_token_field() . '
		<input type="hidden" name="connections_action" value="" id="connections_action">
		<input type="hidden" name="connection_id" value="" id="connections_id">
		' . (($page_id !== '') ? '<input type="hidden" name="connections_return" value="' . h($page_id) . '">' : '') . '
	</form>

	<form method="post" action="connect.php" id="connections_connect_form" class="disable_shortcut d-none">
		' . pg_connect_start_fields('', 'account', $connect_return) . '
	</form>
</main>

<script>
(function () {

	var phone = window.matchMedia("(max-width: 575.98px)");

	// A provider row opens its drawer; on a phone the drawer is a sheet
	// from the bottom.
	var openDrawer = function (id) {
		var drawer = document.getElementById(id);
		if (!drawer || !window.pgUI) { return false; }
		drawer.classList.toggle("drawer-end", !phone.matches);
		drawer.classList.toggle("drawer-bottom", phone.matches);
		pgUI.drawer.show(drawer);
		return true;
	};

	// The drawer a refused save, an address (#connections-google) or the
	// return from a provider names opens with the screen.
	var openNamed = function () {
		var named = window.location.hash.match(/^#(connections-[a-z0-9_]+)$/);
		document.querySelectorAll("dialog.connections-drawer").forEach(function (drawer) {
			if (drawer.dataset.open === "1" || (named && drawer.id === named[1])) {
				delete drawer.dataset.open;
				pgUI.ready(function () { openDrawer(drawer.id); });
			}
		});
	};

	if (window.pgUI) {
		openNamed();
		window.addEventListener("hashchange", openNamed);
	}

	// Caught before the panel\'s own link handlers, which would open the
	// Site Settings window behind the drawer.
	document.addEventListener("click", function (e) {
		var open = e.target.closest(".connections-open");
		if (open && openDrawer(open.dataset.drawer)) {
			e.preventDefault();
			e.stopPropagation();
		}
	}, true);

	// On a narrow screen the filter scrolls sideways: the chosen one is
	// brought into view.
	var chosen = document.querySelector(".connections-find .pg-seg-item.is-active");
	if (chosen) {
		var seg = chosen.parentNode;
		var past = chosen.getBoundingClientRect().right - seg.getBoundingClientRect().right;
		if (past > 0) {
			seg.scrollLeft += past + 8;
		}
	}

	// Buttons post through forms outside the panels, so that no panel holds
	// a form inside another and each destructive one asks first.
	document.addEventListener("click", function (e) {


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
			var status = copy.parentNode.nextElementSibling;
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
