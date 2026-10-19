<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connected applications: the outside applications the signed-in person let
 * act for them with OAuth (an MCP client such as Claude, Cursor or VS Code),
 * what each was allowed, when it was last used, and a button that ends it.
 *
 * Every person sees only their own connections. The operator's view of the
 * applications themselves is on Application Access (api_settings.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

$user = validate_user();

define('PG_API_PANEL', true);

require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/devices.php');
require_once(dirname(__FILE__) . '/includes/oauth/server.php');

$liveform = new liveform('connected_apps');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/connected_apps.php';

$user_id = (int)$user['id'];

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	$action = isset($_POST['connection_action']) ? (string)$_POST['connection_action'] : '';

	if ($action === 'end') {

		$ended = pg_oauth_connection_end($user_id, isset($_POST['connection_id']) ? (int)$_POST['connection_id'] : 0);

		if ($ended !== null) {

			$liveform->add_notice(lang(array('string' => 'The connection of {var:1} was ended.', 'vars' => h($ended))));

			log_activity(lang(array(
				'string' => 'ended the OAuth connection of an application ({var:1})',
				'vars'   => $ended
			)), $_SESSION['sessionusername']);

		}

	} elseif ($action === 'end_all') {

		$count = 0;

		foreach (pg_oauth_connections_of_user($user_id) as $connection) {

			if (pg_oauth_connection_end($user_id, (int)$connection['id']) !== null) {

				$count++;

			}

		}

		if ($count > 0) {

			$liveform->add_notice(lang(array('string' => '{var:1} connections were ended.', 'vars' => $count)));

			log_activity(lang('ended all of their OAuth connections'), $_SESSION['sessionusername']);

		}

	}

	header('Location: ' . $screen_url);

	exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$connections = pg_oauth_connections_of_user($user_id);

// Which address a connection's tokens work at, in words. The resource is the
// address the application asked for when it connected (RFC 8707).
function connected_apps_resource_label($resource) {

	$key = ((string)$resource !== '') ? pg_oauth_resource_key($resource) : 'integration';

	return ($key === 'mcp') ? lang('MCP server') : lang('API');

}

// The address a connection's tokens work at, as its path on this site. The
// MCP server answers at two addresses, and an application connected at both
// showed two cards that read alike.
function connected_apps_resource_address($resource) {

	$resource = (string)$resource;

	if ($resource === '') {

		$resources = pg_oauth_resources();

		$resource = $resources['integration'];

	}

	$path = (string)parse_url($resource, PHP_URL_PATH);

	return ($path !== '') ? $path : $resource;

}

function connected_apps_ago($timestamp) {

	$timestamp = (int)$timestamp;

	return ($timestamp > 0)
		? strip_tags(get_relative_time(array('timestamp' => $timestamp)))
		: lang('Never');

}

$blocks = '';

foreach ($connections as $connection) {

	$name = ((string)$connection['name'] !== '') ? (string)$connection['name'] : lang('Unnamed application');

	$client_on = ((string)$connection['status'] === 'active');

	$identity = ((string)$connection['client_id'] !== '')
		? pg_oauth_client_identity_host($connection)
		: '';

	$scopes = json_decode((string)$connection['scopes'], true);

	$lines = '';

	foreach (pg_oauth_scope_lines(is_array($scopes) ? $scopes : array()) as $line) {

		$lines .= '<li>' . h($line[0]) . ' <span class="text-muted">&middot; ' . h($line[1]) . '</span></li>';

	}

	$meta = array(
		lang(array('string' => 'Connected on {var:1}', 'vars' => date('d.m.Y', (int)$connection['created_timestamp']))),
		lang('Last used') . ' ' . connected_apps_ago($connection['last_used_timestamp'])
	);

	if ((string)$connection['last_used_ip'] !== '') {

		$meta[] = (string)$connection['last_used_ip'];

	}

	$blocks .= '
		<div class="col-12 col-lg-6 col-xxl-4">
			<div class="card h-100">
				<div class="card-body">
					<div class="d-flex align-items-start gap-2 mb-2">
						<i class="bi bi-plug fs-5 opacity-75" aria-hidden="true"></i>
						<div class="flex-grow-1 min-w-0">
							<div class="fw-semibold">' . h($name) . '</div>
							<div class="small text-muted">'
								. (($identity !== '') ? h(lang('Identified by')) . ' <code>' . h($identity) . '</code> &middot; ' : '')
								. h(connected_apps_resource_label($connection['resource']))
								. ' <code>' . h(connected_apps_resource_address($connection['resource'])) . '</code></div>
						</div>
						' . ($client_on
							? ''
							: '<span class="badge text-bg-secondary fw-normal">' . h(lang('Turned off by the site')) . '</span>') . '
					</div>
					<p class="small fw-semibold mb-1">' . h(lang('It may:')) . '</p>
					' . (($lines !== '')
						? '<ul class="small mb-2 ps-3">' . $lines . '</ul>'
						: '<p class="small text-muted mb-2">' . h(lang('Nothing any more: the permissions it was given were taken away.')) . '</p>') . '
					<p class="small text-muted mb-3">' . h(implode(' · ', $meta)) . '</p>
					<button type="button" class="btn btn-sm btn-outline-danger connected-apps-end"
						data-id="' . (int)$connection['id'] . '"
						data-confirm="' . h(lang(array('string' => '{var:1} will no longer be able to act for you. To use it again, connect it again from the application.', 'vars' => $name))) . '">
						<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('End connection')) . '</button>
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
	'title'               => lang('Connected applications'),
	'icon'                => 'account',
	'heading'             => lang('Connected applications'),
	'heading_description' => lang('Outside applications you let act for you, such as an AI assistant. Each can do only what you allowed, and never more than your own account.'),
)) . '

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
	</div></div>

	' . (($blocks === '')
		? pg_empty_state('bi-plug', lang('No application is connected'),
			lang('When you connect an application such as Claude, ChatGPT, Cursor or VS Code to this site and allow it on the consent screen, it appears here.'))
		: '<div class="row g-3">' . $blocks . '</div>
	<div class="d-flex flex-wrap align-items-center gap-2 mt-4">
		<button type="button" class="btn btn-sm btn-outline-danger connected-apps-end" data-id="all"
			data-confirm="' . h(lang('Every application listed here will be disconnected.')) . '">
			<i class="bi bi-x-octagon me-1" aria-hidden="true"></i>' . h(lang('End all connections')) . '</button>
		<span class="small text-muted">' . h(lang('A new password, or signing out everywhere, also ends every connection.')) . '</span>
	</div>') . '

	<form method="post" action="connected_apps.php" id="connected_apps_form" class="disable_shortcut d-none">
		' . get_token_field() . '
		<input type="hidden" name="connection_action" value="end" id="connected_apps_action">
		<input type="hidden" name="connection_id" value="" id="connected_apps_id">
	</form>
</main>

<script>
(function () {

	// The buttons post through one form outside the cards, so that no card
	// holds a form of its own and each asks before anything is ended.
	document.addEventListener("click", function (e) {
		var button = e.target.closest(".connected-apps-end");
		if (!button) { return; }
		if (!window.confirm(button.dataset.confirm)) { return; }
		var all = (button.dataset.id === "all");
		document.getElementById("connected_apps_action").value = all ? "end_all" : "end";
		document.getElementById("connected_apps_id").value = all ? "" : button.dataset.id;
		document.getElementById("connected_apps_form").submit();
	});

})();
</script>' . output_footer();
