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

// The values of a save that was refused, shown once in that provider's form.
$draft = null;

if (isset($_SESSION['software']['connections_draft']) && is_array($_SESSION['software']['connections_draft'])) {

	$draft = $_SESSION['software']['connections_draft'];

	unset($_SESSION['software']['connections_draft']);

}

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
$summary_rows = '';

foreach ($providers as $provider_key => $provider) {

	$app = pg_connect_app($provider_key);
	$ready = pg_connect_app_ready($app);

	$provider_draft = (is_array($draft) && ($draft['provider'] === $provider_key)) ? $draft : null;

	if ($ready) {
		$app_badge = '<span class="badge theme-success fw-normal">' . h(lang('Ready')) . '</span>';
	} elseif (is_array($app) && !$app['enabled']) {
		$app_badge = '<span class="badge theme-secondary fw-normal">' . h(lang('Off')) . '</span>';
	} else {
		$app_badge = '<span class="badge badge-subtle theme-secondary border fw-normal">' . h(lang('Not set up')) . '</span>';
	}

	// The accounts.

	$account_rows = '';
	$account_count = 0;
	$reauth_count = 0;

	foreach (pg_connect_accounts(array('provider' => $provider_key, 'user_id' => 0)) as $account) {

		$account_count++;

		if ($account['status'] === 'reauth') {
			$reauth_count++;
		}

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
				<button type="submit" name="submit_save" class="btn btn-sm btn-solid theme-primary"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
				<a class="btn btn-sm btn-outline theme-secondary" href="' . h($provider['console_url']) . '" target="_blank" rel="noopener">
					<i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Open the {var:1} console', 'vars' => $provider['label']))) . '</a>
				' . ($can_fall_back
					? '<button type="button" class="btn btn-sm btn-outline theme-secondary connections-action" data-action="remove_app" data-id="0"
						data-confirm="' . h(lang('The application saved here is removed and Google features use the Sign in with Google application of the Security settings again. Connected accounts stay.')) . '">
						<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Use the Sign in with Google application')) . '</button>'
					: '') . '
			</div>
		</form>';

	$cards .= '
			<div class="card mb-5" id="connections-' . h($provider_key) . '">
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
					' . (($hint === '') ? '' : '<div class="form-text">' . h($hint) . '</div>') . '
					<details class="mt-7"' . (($ready && !is_array($provider_draft)) ? '' : ' open') . '>
						<summary class="fw-semibold">' . h(lang('Application')) . '</summary>
						' . $app_form . '
					</details>
				</div>
			</div>';

	// The Summary row of the status column: the application's state and how
	// many accounts work or wait to be connected again, a jump to the card.
	$summary_rows .= '
					<li class="list-group-item px-0 d-flex align-items-center gap-3">
						<a class="flex-grow-1 min-w-0 text-truncate pg-link-body-emphasis" href="#connections-' . h($provider_key) . '" lang="en">'
							. '<i class="bi ' . h($provider['icon']) . ' me-2" aria-hidden="true"></i>' . h($provider['label']) . '</a>
						' . (($account_count > 0)
							? '<span class="small fg-secondary text-nowrap">' . h(lang(array('string' => '{var:1} connected', 'vars' => pg_format_number($account_count, 0)))) . '</span>'
							: '') . '
						' . (($reauth_count > 0)
							? '<span class="badge theme-warning fw-normal" title="' . h(lang('Connect again')) . '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> ' . (int) $reauth_count . '<span class="visually-hidden"> ' . h(lang('Connect again')) . '</span></span>'
							: '') . '
						' . $app_badge . '
					</li>';

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

// The wide layout without its save bar: each provider card saves its own
// application, so there is no one form for a shared bar to submit.
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

	<div class="row g-5">
		<div class="col-12 lg:col-8 2xl:col-9 order-1">' . $cards . '</div>
		<div class="col-12 lg:col-4 2xl:col-3 order-2">
			<div class="position-sticky" style="top:3.3rem;">
				<div class="card mb-5" id="connections-summary">
					<h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Summary')) . '</h2>
					<div class="card-body pt-0">
						<ul class="list-group list-group-flush">' . $summary_rows . '</ul>
						<p class="small fg-secondary mt-3 mb-0">' . h(lang('A provider is ready once its application is set up; then its accounts can be connected.')) . '</p>
					</div>
				</div>
			</div>
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
