<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The authorization endpoint: the page where a person signed in on the site
 * lets an outside application act for them, or refuses.
 *
 * Two kinds of failure are told apart, as RFC 6749 4.1.2.1 requires. Until
 * the client and its redirect address are known to be genuine, nothing is
 * sent anywhere: the page says what is wrong, because redirecting to an
 * address that was never registered would turn this endpoint into an open
 * redirect. After that, every refusal goes back to the client with an error
 * code, so the application can tell its user what happened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_OAUTH_ENTRY')) {
	exit;
}

// A page with nothing but a message, for the failures that cannot go back to
// the client. $detail is the technical reason, in English, for whoever
// develops the application.
function pg_oauth_authorize_page_error($message, $detail = '') {

	http_response_code(400);

	pg_oauth_authorize_headers();

	echo output_header_secure(array('title' => lang('Connect an application')))
		. '<main class="container py-4 py-md-5">
			<div class="card mx-auto shadow-sm" style="max-width: 30rem;">
				<div class="card-body p-4">
					<h1 class="h5 mb-3"><i class="bi bi-plug me-2" aria-hidden="true"></i>' . h(lang('Connect an application')) . '</h1>
					<div class="alert alert-danger mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . h($message) . '</div>'
						. (($detail !== '') ? '<p class="small text-muted mt-3 mb-0"><code>' . h($detail) . '</code></p>' : '') . '
				</div>
			</div>
		</main>'
		. output_footer_secure();

	exit();

}

// The consent screen is never drawn inside another site's frame: a page that
// can be framed can be clicked through by a person who cannot see it.
function pg_oauth_authorize_headers() {

	if (headers_sent()) {

		return;

	}

	header('X-Frame-Options: DENY');

	header("Content-Security-Policy: frame-ancestors 'none'", false);

	header('Cache-Control: no-store');

	header('Referrer-Policy: no-referrer');

}

// Sends the answer back to the client and stops. state is returned exactly as
// it came; iss names this server (RFC 9207), so a client talking to several
// cannot be handed a code from the wrong one.
function pg_oauth_authorize_return($redirect_uri, $params, $state) {

	if ($state !== '') {

		$params['state'] = $state;

	}

	$params['iss'] = pg_oauth_issuer();

	header('Location: ' . pg_oauth_redirect_with($redirect_uri, $params), true, 302);

	exit();

}

function pg_oauth_authorize() {

	$post = ($_SERVER['REQUEST_METHOD'] === 'POST');

	$request = $post ? $_POST : $_GET;

	$param = function ($name) use ($request) {

		return (isset($request[$name]) && is_string($request[$name])) ? $request[$name] : '';

	};

	// Is there anything to connect to at all.
	if (!pg_oauth_ready() || !api_settings()['enabled'] || !pg_oauth_active()) {

		pg_oauth_authorize_page_error(lang('Connecting applications is not switched on for this site.'));

	}

	if (api_settings()['require_https'] && !pg_request_is_https()) {

		pg_oauth_authorize_page_error(lang('This API requires a secure (https) connection.'));

	}

	$signed_in = isset($_SESSION['sessionuserid']) && ((string)$_SESSION['sessionuserid'] !== '');

	// A client that names itself with the address of its metadata document is
	// read from that address, which only happens for somebody signed in: the
	// person signs in first, on the site's own page, and comes back here.
	// Nothing goes to the client on the way, so this is no open redirect.
	if (pg_oauth_client_id_is_url($param('client_id')) && !$signed_in) {

		if ($post) {

			pg_oauth_authorize_page_error(lang('Your session has ended. Start again from the application.'));

		}

		header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/index.php?send_to=' . urlencode(get_request_uri()));

		exit();

	}

	// The client and where it wants the answer. Nothing is sent to the client
	// until both are known to be its own.
	$resolved = pg_oauth_client_resolve($param('client_id'));

	$client = $resolved['client'];

	if ($client === null) {

		// A client registered long ago and swept as unused still holds its
		// old client_id and sends it here; only adding it again makes it
		// register anew.
		pg_oauth_authorize_page_error(lang('The application asking to connect is not known to this site. Start again from the application; if this keeps happening, remove the server there and add it again.'), $resolved['error']);

	}

	if ($client['status'] !== 'active') {

		pg_oauth_authorize_page_error(lang('This application has been turned off on this site and cannot connect. Ask the site\'s administrator if you need it.'));

	}

	$registered = pg_oauth_client_redirect_uris($client);

	// As the client sent it: the token request has to repeat it only when it
	// was sent (OAuth 2.1 4.1.3), so the code keeps this spelling.
	$presented_redirect = $param('redirect_uri');

	$redirect_uri = $presented_redirect;

	if (($redirect_uri === '') && (count($registered) === 1)) {

		$redirect_uri = $registered[0];

	}

	if (!pg_oauth_redirect_uri_matches($registered, $redirect_uri)) {

		pg_oauth_authorize_page_error(lang('The return address does not belong to the application asking to connect.'));

	}

	$state = mb_substr($param('state'), 0, 1000);

	// From here a refusal goes back to the client. Its description is for the
	// client's developer and RFC 6749 4.1.2.1 keeps it to printable ASCII, so
	// it is written in English; the person reads the client's own message.
	$fail = function ($error, $description) use ($redirect_uri, $state) {

		pg_oauth_authorize_return($redirect_uri, array(
			'error'             => $error,
			'error_description' => $description
		), $state);

	};

	if ($param('response_type') !== 'code') {

		$fail('unsupported_response_type', 'Only response_type=code is supported.');

	}

	// PKCE on every request, S256 only (OAuth 2.1 4.1.1).
	$challenge = $param('code_challenge');

	if (!pg_oauth_challenge_is_valid($challenge) || ($param('code_challenge_method') !== 'S256')) {

		$fail('invalid_request', 'A code_challenge with code_challenge_method=S256 is required.');

	}

	// The resource the tokens are for. Stored in the site's own spelling, so
	// the token endpoint and the resource compare like with like.
	$resource = '';

	if ($param('resource') !== '') {

		$key = pg_oauth_resource_key($param('resource'));

		if ($key === '') {

			$fail('invalid_target', 'The resource is not one this site serves.');

		}

		$resources = pg_oauth_resources();

		$resource = rtrim($resources[$key], '/');

	}

	// Who is connecting. Somebody who is not signed in goes to the sign-in
	// screen, two-step verification included, and comes back here.
	if (!$signed_in) {

		if ($post) {

			pg_oauth_authorize_page_error(lang('Your session has ended. Start again from the application.'));

		}

		header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/index.php?send_to=' . urlencode(get_request_uri()));

		exit();

	}

	validate_user();

	$user_id = (int)$_SESSION['sessionuserid'];

	$owner = api_load_owner($user_id);

	if (($owner === null) || !api_device_account_has_rights($owner)) {

		$fail('access_denied', 'This account cannot connect applications.');

	}

	$app = pg_oauth_app();

	$available = pg_oauth_available_scopes($app, $owner);

	// No scope asked for means everything this person may hand over; the
	// screen lists it and they choose. Values the site does not know are
	// passed over (pg_oauth_scope_requested()).
	$asked = pg_oauth_scope_requested($param('scope'), api_scopes_all(), $available);

	if (empty($asked)) {

		$fail('invalid_scope', 'None of the requested scopes can be granted by this account.');

	}

	if ($post) {

		validate_token_field();

		if ((isset($_POST['pg_oauth_action']) ? (string)$_POST['pg_oauth_action'] : '') !== 'allow') {

			$fail('access_denied', 'The person declined the connection.');

		}

		$chosen = (isset($_POST['grant']) && is_array($_POST['grant']))
			? array_values(array_intersect($asked, array_map('strval', $_POST['grant'])))
			: array();

		if (empty($chosen)) {

			$fail('access_denied', 'No permission was granted.');

		}

		$code = pg_oauth_code_issue($client, $user_id, $presented_redirect, $chosen, $resource, $challenge);

		pg_oauth_authorize_return($redirect_uri, array('code' => $code), $state);

	}

	pg_oauth_authorize_consent($client, $owner, $asked, $redirect_uri, array(
		'client_id'             => (string)$client['client_id'],
		'redirect_uri'          => $presented_redirect,
		'response_type'         => 'code',
		'state'                 => $state,
		'code_challenge'        => $challenge,
		'code_challenge_method' => 'S256',
		'scope'                 => $param('scope'),
		'resource'              => $param('resource')
	));

}

// The consent screen itself. $redirect_uri is where the answer goes; $hidden
// is the request as it came, posted back with the person's choice.
function pg_oauth_authorize_consent($client, $owner, $asked, $redirect_uri, $hidden) {

	pg_oauth_authorize_headers();

	$name = pg_oauth_client_name($client);

	$site = (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '')) ? ORGANIZATION_NAME : pg_oauth_host();

	$destination = pg_oauth_redirect_destination($redirect_uri);

	$return_host = parse_url($redirect_uri, PHP_URL_HOST);

	// A program on this computer or an application on this device is said so
	// in words; its address alone reads like a web site.
	$destination_labels = array(
		'device' => lang('A program on this computer'),
		'app'    => lang('An application on this device')
	);

	$destination_html = (isset($destination_labels[$destination['kind']])
			? h($destination_labels[$destination['kind']]) . '<br/>'
			: '')
		. '<code>' . h($destination['text']) . '</code>';

	// One line per permission, grouped the way the API settings screen shows
	// them, every box ticked to begin with: the person narrows what the
	// application asked for, never widens it.
	$rows = '';

	foreach (api_scope_groups() as $group) {

		$halves = array(
			'read'  => lang('Read only'),
			'write' => ($group['read'] === '') ? lang('Manage') : lang('Read and write')
		);

		foreach ($halves as $half => $label) {

			$scope = $group[$half];

			if (($scope === '') || !in_array($scope, $asked, true)) {

				continue;

			}

			$id = 'grant_' . preg_replace('/[^a-z0-9]+/', '_', $scope);

			$rows .= '
				<div class="form-check mb-1">
					<input class="form-check-input" type="checkbox" name="grant[]" value="' . h($scope) . '" id="' . h($id) . '" checked="checked"/>
					<label class="form-check-label" for="' . h($id) . '">' . h(lang($group['label'])) . ' <span class="text-muted">&middot; ' . h($label) . '</span></label>
				</div>';

		}

	}

	$fields = get_token_field();

	foreach ($hidden as $field => $value) {

		$fields .= '<input type="hidden" name="' . h($field) . '" value="' . h($value) . '"/>';

	}

	$warning = function ($text) {

		return '<div class="alert alert-warning small" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . h($text) . '</div>';

	};

	$unverified = ((string)$client['registered_by'] === 'dynamic')
		? $warning(lang('This application registered itself with the site. Connect it only if you started this from the application yourself.'))
		: '';

	// A client identified by its metadata document is known by the host that
	// published the document, which https vouches for; the name in the
	// document is whatever its author wrote. The answer going to another host
	// is allowed but worth a second look.
	$identity = pg_oauth_client_identity_host($client);

	$identity_row = '';

	if ($identity !== '') {

		$identity_row = '<dt>' . h(lang('Identified by')) . '</dt><dd><code>' . h($identity) . '</code></dd>';

		$redirect_scheme = strtolower((string)parse_url($redirect_uri, PHP_URL_SCHEME));

		if (($redirect_scheme === 'https') && is_string($return_host)
			&& (strtolower($return_host) !== $identity)
			&& (substr(strtolower($return_host), -strlen('.' . $identity)) !== '.' . $identity)) {

			$unverified = $warning(lang('The answer goes to a different address than the one this application is identified by. Connect it only if you expected that.'));

		}

	}

	$title = lang(array('string' => 'Connect {var:1}', 'vars' => $name));

	echo output_header_secure(array('title' => $title))
		. '<main class="container py-4 py-md-5">
			<div class="card mx-auto shadow-sm" style="max-width: 30rem;">
				<div class="card-body p-4">
					<h1 class="h5 mb-3"><i class="bi bi-plug me-2" aria-hidden="true"></i>' . h($title) . '</h1>
					<p class="mb-3">' . h(lang(array(
						'string' => '{var:1} wants to use {var:2} on your behalf.',
						'vars'   => array($name, $site)
					))) . '</p>
					' . $unverified . '
					<dl class="small mb-3">
						' . $identity_row . '
						<dt>' . h(lang('Signed in as')) . '</dt>
						<dd>' . h($owner['username']) . '</dd>
						<dt>' . h(lang('Answer goes to')) . '</dt>
						<dd>' . $destination_html . '</dd>
					</dl>
					<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/oauth.php/authorize') . '">
						' . $fields . '
						<p class="fw-semibold mb-2">' . h(lang('It will be able to:')) . '</p>
						<div class="border rounded p-3 mb-3">' . $rows . '</div>
						<p class="small text-muted">' . h(lang('It never gets more than your own account may do. You can end the connection at any time on the Connected applications screen, in your account menu on the panel.')) . '</p>
						<button type="submit" name="pg_oauth_action" value="allow" class="btn btn-primary w-100">' . h(lang('Allow')) . '</button>
						<button type="submit" name="pg_oauth_action" value="deny" class="btn btn-outline-secondary w-100 mt-2">' . h(lang('Cancel')) . '</button>
					</form>
				</div>
			</div>
		</main>'
		. output_footer_secure();

	exit();

}
