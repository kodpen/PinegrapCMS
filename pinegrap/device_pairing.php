<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pair a device: the signed-in person shows a one-time code as a QR, and the
 * team app scans it and signs in as them through POST /auth/pair, with no
 * password typed on the phone (includes/api/pairing.php).
 *
 * The code lives five minutes and works once. It is drawn only in the answer
 * to the POST that made it, never stored in the clear, and the answer is sent
 * with no-store so a browser cache or a back button does not keep it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

// Any signed-in team member: whether the account has anything a device could
// be used for is asked below (api_device_account_has_rights()), as the sign-in
// endpoints ask it.
$user = validate_user();

define('PG_API_PANEL', true);

require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/devices.php');
require_once(dirname(__FILE__) . '/includes/api/keys.php');
require_once(dirname(__FILE__) . '/includes/api/openapi.php');

header('Cache-Control: no-store, max-age=0');

$liveform = new liveform('device_pairing');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/device_pairing.php';

$user_id = (int)$user['id'];

$app = api_pairing_ready() ? api_pairing_device_app() : null;

// Signed in as another user, a code would hand that person's account to a
// device for thirty days.
$refusal = api_pairing_ready()
	? api_pairing_refusal(!empty($_SESSION['software']['logged_in_as_different_user']), $app,
		api_device_account_has_rights($user), time())
	: lang('The API is not installed on this site yet. Run the software upgrade to finish it.');

$shown = null;

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	$action = isset($_POST['pairing_action']) ? (string)$_POST['pairing_action'] : '';

	if (($action === 'cancel') && api_pairing_ready()) {

		api_pairing_cancel($user_id);

		header('Location: ' . $screen_url);

		exit();

	}

	if (($action === 'show') && ($refusal === '')) {

		$shown = api_pairing_create((int)$app['id'], $user_id, function_exists('waf_client_ip') ? waf_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : ''));

		if ($shown === null) {

			$liveform->add_error(lang('Too many pairing codes were made in the last hour. Please wait and try again.'));

		} else {

			log_activity(lang('showed a device pairing code'), $_SESSION['sessionusername']);

		}

	}

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

if ($refusal !== '') {

	$body = pg_empty_state('bi-phone', lang('Pair a device'), $refusal);

} elseif ($shown !== null) {

	$payload = api_pairing_payload(api_openapi_base_url(), $shown['code']);

	$body = '
	<div class="card" style="max-width:520px">
		<div class="card-body text-center">
			<div class="d-inline-block p-5 bg-white rounded-3 mb-5" id="pairing_qr">'
				. pg_qr_svg($payload, 260, array('label' => lang('Pairing code'))) . '
			</div>
			<p class="mb-2">' . h(lang('Open the team app, choose Scan QR code and point the camera at this code.')) . '</p>
			<p class="small fg-secondary mb-5" id="pairing_left" data-expires="' . (int)$shown['expires'] . '" data-now="' . time() . '"
				data-expired="' . h(lang('This code has run out. Show a new one.')) . '">'
				. h(lang(array('string' => 'Works once, for {var:1} minutes.', 'vars' => (int)(api_pairing_lifetime() / 60)))) . '</p>
			<form method="post" action="device_pairing.php" class="d-flex flex-wrap justify-content-center gap-3">
				' . get_token_field() . '
				<button type="submit" name="pairing_action" value="show" class="btn btn-sm btn-solid theme-primary">
					<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('New code')) . '</button>
				<button type="submit" name="pairing_action" value="cancel" class="btn btn-sm btn-outline theme-secondary">
					<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Cancel')) . '</button>
			</form>
		</div>
	</div>

	<script>
	(function () {

		// Counts down on the browser\'s clock from the server\'s own reading, so
		// a wrong clock on the computer does not shorten or lengthen it.
		var line = document.getElementById("pairing_left");
		var left = parseInt(line.dataset.expires, 10) - parseInt(line.dataset.now, 10);
		var started = Date.now();

		var tick = function () {
			var remaining = left - Math.floor((Date.now() - started) / 1000);
			if (remaining <= 0) {
				line.textContent = line.dataset.expired;
				document.getElementById("pairing_qr").classList.add("opacity-25");
				return;
			}
			var minutes = Math.floor(remaining / 60);
			var seconds = remaining % 60;
			line.textContent = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
			window.setTimeout(tick, 1000);
		};

		tick();

	})();
	</script>';

} else {

	$body = '
	<div class="card" style="max-width:520px">
		<div class="card-body">
			<p>' . h(lang('A pairing code signs the team app in as you without typing your password on the phone. The app gets at most what your own account may do.')) . '</p>
			<p class="small fg-secondary">' . h(lang('Show the code only to a device you are holding. A new password, or signing out everywhere, signs that device out again.')) . '</p>
			<form method="post" action="device_pairing.php">
				' . get_token_field() . '
				<button type="submit" name="pairing_action" value="show" class="btn btn-sm btn-solid theme-primary">
					<i class="bi bi-qr-code me-1" aria-hidden="true"></i>' . h(lang('Show a pairing code')) . '</button>
			</form>
		</div>
	</div>';

}

echo pg_page_shell(array(
	'title'               => lang('Pair a device'),
	'icon'                => 'account',
	'heading'             => lang('Pair a device'),
	'heading_description' => lang('Sign the team app in on your phone by scanning a code.'),
)) . '

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
	</div></div>

	' . $body . '
</main>' . output_footer();
