<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connected accounts: the outside accounts this site works with (Google,
 * Microsoft, ...), the application each provider knows the site by, and the
 * buttons that connect, check and end them (includes/fn/connect.php).
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

	if ($action === 'save_app') {

		$provider_key = isset($_POST['provider']) ? (string) $_POST['provider'] : '';

		if (isset($providers[$provider_key])) {

			$provider = $providers[$provider_key];

			$options = array();

			foreach ($provider['options'] as $option_key => $option) {

				$value = (isset($_POST['option_' . $option_key]) && is_string($_POST['option_' . $option_key]))
					? trim($_POST['option_' . $option_key])
					: '';

				$options[$option_key] = ($value !== '') ? mb_substr($value, 0, 100) : (string) $option['default'];

			}

			$client_id = (isset($_POST['client_id']) && is_string($_POST['client_id'])) ? trim($_POST['client_id']) : '';
			$client_secret = (isset($_POST['client_secret']) && is_string($_POST['client_secret'])) ? trim($_POST['client_secret']) : '';

			if (mb_strlen($client_id) > 255) {

				$liveform->add_error(lang('The client ID is too long.'));

			} elseif (pg_connect_app_save($provider_key, $client_id, $client_secret, $options, !empty($_POST['enabled']), (int) $user['id'])) {

				$liveform->add_notice(lang(array('string' => 'The {var:1} application was saved.', 'vars' => $provider['label'])));

				log_activity(lang(array('string' => 'changed the {var:1} application of connected accounts', 'vars' => $provider['label'])), $_SESSION['sessionusername']);

			} else {

				$liveform->add_error(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));

			}

		}

	} elseif ($action === 'check') {

		$account = pg_connect_account(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

		if (is_array($account) && ($account['user_id'] === 0)) {

			$check = pg_connect_access_token($account['id'], array(), true);

			if ($check['ok']) {

				$liveform->add_notice(lang(array('string' => '{var:1} works.', 'vars' => h($account['label']))));

			} else {

				$liveform->add_error(lang(array('string' => '{var:1} does not work: {var:2}', 'vars' => array(h($account['label']), h($check['error'])))));

			}

		}

	} elseif ($action === 'disconnect') {

		$account = pg_connect_account(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

		if (is_array($account) && ($account['user_id'] === 0)) {

			pg_connect_disconnect($account['id']);

			$liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => h($account['label']))));

			log_activity(lang(array(
				'string' => 'disconnected the {var:1} account {var:2}',
				'vars'   => array(isset($providers[$account['provider']]) ? $providers[$account['provider']]['label'] : $account['provider'], $account['label']))),
				$_SESSION['sessionusername']);

		}

	}

	header('Location: ' . $screen_url);

	exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$flash = pg_connect_flash_take();

if (is_array($flash)) {

	if ($flash['kind'] === 'error') {
		$liveform->add_error(h($flash['text']));
	} else {
		$liveform->add_notice(h($flash['text']));
	}

}

$can_seal = pg_connect_can_seal();

$redirect_uri = pg_connect_redirect_uri();

function connections_ago($timestamp) {

	$timestamp = (int) $timestamp;

	return ($timestamp > 0)
		? strip_tags(get_relative_time(array('timestamp' => $timestamp)))
		: lang('Never');

}

function connections_status_badge($status) {

	if ($status === 'active') {
		return '<span class="badge theme-success fw-normal">' . h(lang('Working')) . '</span>';
	}

	if ($status === 'reauth') {
		return '<span class="badge theme-warning fw-normal">' . h(lang('Connect again')) . '</span>';
	}

	return '<span class="badge theme-secondary fw-normal">' . h(lang('Off')) . '</span>';

}

$cards = '';

foreach ($providers as $provider_key => $provider) {

	$app = pg_connect_app($provider_key);
	$ready = pg_connect_app_ready($app);

	if ($ready) {
		$app_badge = '<span class="badge theme-success fw-normal">' . h(lang('Ready')) . '</span>';
	} elseif (is_array($app) && !$app['enabled']) {
		$app_badge = '<span class="badge theme-secondary fw-normal">' . h(lang('Off')) . '</span>';
	} else {
		$app_badge = '<span class="badge badge-subtle theme-secondary border fw-normal">' . h(lang('Not set up')) . '</span>';
	}

	// The accounts.

	$account_rows = '';

	foreach (pg_connect_accounts(array('provider' => $provider_key, 'user_id' => 0)) as $account) {

		$purpose_labels = array();

		foreach ($account['purposes'] as $purpose_key) {
			$purpose = pg_connect_purpose($purpose_key);
			$purpose_labels[] = is_array($purpose) ? $purpose['label'] : $purpose_key;
		}

		$meta = array(
			lang(array('string' => 'Connected on {var:1}', 'vars' => get_absolute_time(array('timestamp' => (int)$account['created_timestamp'], 'type' => 'date')))),
			lang('Last used') . ' ' . connections_ago($account['last_used_timestamp']),
		);

		$account_rows .= '
			<li class="list-group-item px-0">
				<div class="d-flex align-items-start gap-3">
					<i class="bi bi-person-circle fs-lg opacity-75" aria-hidden="true"></i>
					<div class="flex-grow-1 min-w-0">
						<div class="fw-semibold text-truncate">' . h($account['label']) . '</div>
						<div class="small fg-secondary">' . h(implode(' · ', $purpose_labels)) . '</div>
						<div class="small fg-secondary">' . h(implode(' · ', $meta)) . '</div>
						' . (($account['last_error'] !== '')
							? '<div class="small fg-danger mt-1">' . h($account['last_error']) . '</div>'
							: '') . '
					</div>
					' . connections_status_badge($account['status']) . '
				</div>
				<div class="d-flex flex-wrap gap-3 mt-3 ms-7 ps-3">
					' . (($account['status'] === 'reauth')
						? '<button type="button" class="btn btn-sm btn-solid theme-primary connections-connect"
							data-provider="' . h($provider_key) . '" data-connection="' . $account['id'] . '"' . ($ready ? '' : ' disabled') . '>
							<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Connect again')) . '</button>'
						: '<button type="button" class="btn btn-sm btn-outline theme-secondary connections-action"
							data-action="check" data-id="' . $account['id'] . '">
							<i class="bi bi-activity me-1" aria-hidden="true"></i>' . h(lang('Check')) . '</button>') . '
					<button type="button" class="btn btn-sm btn-outline theme-danger connections-action"
						data-action="disconnect" data-id="' . $account['id'] . '"
						data-confirm="' . h(lang(array('string' => 'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.', 'vars' => $account['label']))) . '">
						<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
				</div>
			</li>';

	}

	// The application.

	$option_fields = '';

	foreach ($provider['options'] as $option_key => $option) {

		$value = (is_array($app) && isset($app['options'][$option_key])) ? (string) $app['options'][$option_key] : (string) $option['default'];

		$option_fields .= '
			<div class="col-12 md:col-6">
				<label for="option_' . h($provider_key . '_' . $option_key) . '" class="form-label">' . h($option['label']) . '</label>
				<input type="text" class="form-control form-control-sm" id="option_' . h($provider_key . '_' . $option_key) . '"
					name="option_' . h($option_key) . '" value="' . h($value) . '" maxlength="100" autocomplete="off">
				<div class="form-text">' . h($option['help']) . '</div>
			</div>';

	}

	$setup_steps = '';

	foreach ($provider['setup'] as $step) {
		$setup_steps .= '<li>' . h($step) . '</li>';
	}

	$secret_stored = is_array($app) && $app['secret_stored'] && ($app['source'] === 'connect');

	$app_form = '
		<form method="post" action="connections.php" class="mt-3">
			' . get_token_field() . '
			<input type="hidden" name="connections_action" value="save_app">
			<input type="hidden" name="provider" value="' . h($provider_key) . '">
			' . ((is_array($app) && ($app['source'] === 'signin'))
				? '<div class="alert theme-info small py-3">' . h(lang('This is the application of Sign in with Google (Security settings). Add the redirect address below to it in Google Cloud Console, or save an application of its own here.')) . '</div>'
				: '') . '
			<ol class="small ps-5 mb-5">' . $setup_steps . '</ol>
			<label class="form-label" for="redirect_' . h($provider_key) . '">' . h(lang('Redirect address')) . '</label>
			<div class="input-group input-group-sm mb-5">
				<input type="text" class="form-control font-monospace" id="redirect_' . h($provider_key) . '" value="' . h($redirect_uri) . '" readonly>
				<button type="button" class="btn btn-outline theme-secondary connections-copy" data-target="redirect_' . h($provider_key) . '">
					<i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
			</div>
			<div class="row g-3">
				<div class="col-12 md:col-6">
					<label for="client_id_' . h($provider_key) . '" class="form-label">' . h(lang('Client ID')) . '</label>
					<input type="text" class="form-control form-control-sm" id="client_id_' . h($provider_key) . '" name="client_id"
						value="' . h(is_array($app) ? $app['client_id'] : '') . '" maxlength="255" autocomplete="off">
				</div>
				<div class="col-12 md:col-6">
					<label for="client_secret_' . h($provider_key) . '" class="form-label">' . h(lang('Client secret')) . '</label>
					<input type="password" class="form-control form-control-sm" id="client_secret_' . h($provider_key) . '" name="client_secret"
						value="" autocomplete="new-password"
						placeholder="' . h($secret_stored ? lang('Stored. Leave empty to keep it.') : '') . '">
				</div>
				' . $option_fields . '
				<div class="col-12">
					<div class="form-field">
						<div class="switch"><input class="" type="checkbox" role="switch" name="enabled" value="1" id="enabled_' . h($provider_key) . '"'
							. ((!is_array($app) || $app['enabled']) ? ' checked' : '') . '></div>
						<label class="" for="enabled_' . h($provider_key) . '">' . h(lang('Connections through this application are allowed')) . '</label>
					</div>
				</div>
			</div>
			<div class="d-flex flex-wrap align-items-center gap-3 mt-5">
				<button type="submit" name="submit_save" class="btn btn-sm btn-solid theme-primary"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
				<a class="btn btn-sm btn-outline theme-secondary" href="' . h($provider['console_url']) . '" target="_blank" rel="noopener">
					<i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Open the {var:1} console', 'vars' => $provider['label']))) . '</a>
			</div>
		</form>';

	$cards .= '
		<div class="col-12 2xl:col-6">
			<div class="card h-100">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span class="text-uppercase h5 fg-primary fw-bold mb-0" lang="en"><i class="bi ' . h($provider['icon']) . ' me-3" aria-hidden="true"></i>' . h($provider['label']) . '</span>
					' . $app_badge . '
				</div>
				<div class="card-body">
					' . (($account_rows !== '')
						? '<ul class="list-group list-group-flush mb-5">' . $account_rows . '</ul>'
						: '<p class="small fg-secondary">' . h(lang(array('string' => 'No {var:1} account is connected.', 'vars' => $provider['label']))) . '</p>') . '
					<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 connections-connect"
						data-provider="' . h($provider_key) . '" data-connection="0"' . (($ready && $can_seal) ? '' : ' disabled') . '>
						<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $provider['label']))) . '</button>
					' . ($ready ? '' : '<div class="form-text">' . h(lang('Set up the application below first.')) . '</div>') . '
					<details class="mt-7"' . ($ready ? '' : ' open') . '>
						<summary class="fw-semibold">' . h(lang('Application')) . '</summary>
						' . $app_form . '
					</details>
				</div>
			</div>
		</div>';

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

echo pg_page_shell(array(
	'title'               => lang('Connected accounts'),
	'icon'                => 'settings',
	'heading'             => lang('Connected accounts'),
	'heading_description' => lang('Outside accounts this site works with, such as Google or Microsoft. Each provider knows the site by an application you register there; features ask the connected account for what they need.'),
)) . '

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
		' . ($can_seal
			? ''
			: '<div class="alert theme-danger">' . h(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.')) . '</div>') . '
	</div></div>

	<div class="row g-5">' . $cards . '</div>

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

	// Buttons post through forms outside the cards, so that no card holds a
	// form inside another and each destructive one asks first.
	document.addEventListener("click", function (e) {

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
			form.elements.connection_id.value = connect.dataset.connection;
			form.submit();
			return;
		}

		var copy = e.target.closest(".connections-copy");

		if (copy) {
			var field = document.getElementById(copy.dataset.target);
			field.select();
			if (navigator.clipboard) { navigator.clipboard.writeText(field.value); } else { document.execCommand("copy"); }
		}

	});

})();
</script>' . output_footer();
