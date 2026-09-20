<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The API documentation, and a place to try a call.
//
// Every endpoint on this page is read from includes/api/schema.php - the same
// table the router dispatches from and the OpenAPI document is generated from.
// That is deliberate and it is the fix for a specific failure: the screen this
// replaces kept its own copy of the endpoint list, so it offered fields the
// endpoint did not read and hid ones it did, and anybody testing through it was
// testing a description of the API rather than the API.
//
// Trying a call does not use a real application's secret. The secret is stored
// as a hash and could not be shown even if this wanted to; instead the page asks
// for a temporary credential that carries the chosen application's permissions
// and expires in fifteen minutes. A live key therefore never has to be pasted
// into a browser to check whether an endpoint works.

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

define('PG_API_PANEL', true);

require_once(dirname(__FILE__) . '/includes/api/keys.php');
require_once(dirname(__FILE__) . '/includes/api/scopes.php');
require_once(dirname(__FILE__) . '/includes/api/schema.php');
require_once(dirname(__FILE__) . '/includes/api/openapi.php');
require_once(dirname(__FILE__) . '/includes/api/console_view.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');

$api_base_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/integration.php';

// Whether /openapi.json answers without credentials. The address is printed on
// the page either way - a developer who cannot find it assumes there is no
// description - and what changes is what the line beside it says.
$openapi_public = ((int)db_value("SELECT api_openapi_public FROM config LIMIT 1") === 1);

/* ---------------------------------------------------------------------------
   JSON endpoints used by this screen
   --------------------------------------------------------------------------- */

// The machine-readable description, for anyone who wants to load this API into
// their own tooling. Served from the panel, behind the panel's own session.
if (isset($_GET['openapi'])) {

	header('Content-Type: application/json; charset=utf-8');

	print json_encode(api_openapi_build(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

	exit();

}

// A credential for trying a call, valid for fifteen minutes.
//
// One row per operator, replaced each time: a page left open overnight does not
// leave a working key behind, and there is never a pile of them. It is owned by
// the operator asking, so it is capped by their own rights the same as any other
// application, and its permissions are copied from the application being tried
// so the answer here matches the answer that application would get.
if (isset($_POST['test_credential'])) {

	validate_token_field();

	header('Content-Type: application/json; charset=utf-8');

	$source_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

	$scopes = array();

	if ($source_id > 0) {

		$source = db_item("SELECT scopes FROM api_apps WHERE id = '" . $source_id . "' LIMIT 1");

		if ($source) {

			$decoded = json_decode((string)$source['scopes'], true);

			if (is_array($decoded)) { $scopes = api_scopes_normalise($decoded); }

		}

	}

	$name = '__test__' . (int)$user['id'];

	$key    = api_generate_test_key();
	$secret = api_generate_secret();

	// Whatever the previous credential registered goes with it. Trying a call
	// here runs the real endpoint, so a POST /webhooks made from this screen
	// stores a real subscription owned by a credential that is replaced the next
	// time somebody opens the page - and a subscription whose application is
	// gone is one no screen lists and nobody can stop.
	$stale = db_items("SELECT id FROM api_apps WHERE name = '" . escape($name) . "'");

	if (is_array($stale)) {

		foreach ($stale as $stale_app) {

			api_webhooks_delete_for_app((int)$stale_app['id']);

		}

	}

	db("DELETE FROM api_apps WHERE name = '" . escape($name) . "'");

	db("INSERT INTO api_apps
		(name, description, api_key, api_secret_hash, api_secret_hint, owner_user_id,
		 scopes, status, expires_at, rate_limit_per_min, created_user_id, created_timestamp, updated_timestamp)
		VALUES (
			'" . escape($name) . "',
			'" . escape('Temporary credential for the documentation screen.') . "',
			'" . escape($key) . "',
			'" . escape(api_secret_hash($secret)) . "',
			'" . escape(api_secret_hint($secret)) . "',
			'" . (int)$user['id'] . "',
			'" . escape(json_encode($scopes)) . "',
			'active',
			UNIX_TIMESTAMP() + 900,
			'240',
			'" . (int)$user['id'] . "',
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP()
		)");

	print json_encode(array(
		'key'        => $key,
		'secret'     => $secret,
		'scopes'     => $scopes,
		'expires_in' => 900
	), JSON_UNESCAPED_UNICODE);

	exit();

}

/* ---------------------------------------------------------------------------
   The page
   --------------------------------------------------------------------------- */

$apps = db_items("SELECT id, name, api_key, scopes FROM api_apps
	WHERE status = 'active' AND name NOT LIKE '\\_\\_test\\_\\_%'
	ORDER BY name ASC");

if (!is_array($apps)) { $apps = array(); }

$app_options = '<option value="">— ' . lang('Select an application') . ' —</option>';

foreach ($apps as $app) {

	$app_options .= '<option value="' . (int)$app['id'] . '">' . h($app['name']) . '</option>';

}

// The endpoint blocks and the index come from the renderer shared with the
// console the API serves at integration.php/docs, so the two screens cannot
// show different endpoints.
$endpoints = api_console_endpoints_html(lang('Uses a temporary credential, not a real secret.'));

$nav = $endpoints['nav'];

$panels = $endpoints['panels'];

$console_url = $api_base_url . '/docs';

echo pg_page_shell(array(
	'title'               => lang('API Documentation'),
	'extra classes'       => 'api-docs',
	'icon'                => 'setting',
	'heading'             => lang('API Documentation'),
	'heading_description' => lang('Every endpoint this site offers, read from the same table the API itself dispatches from.'),
	'breadcrumb'          => array(
		array('label' => lang('Application Access'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api_settings.php'),
		array('label' => lang('API Documentation'))
	)
)) . '

<style>
' . api_console_css() . '
.doc-cred { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
@media (max-width: 768px) {
	.doc-cred > .ms-auto { margin-left: 0 !important; width: 100%; flex-wrap: wrap; }
	.doc-cred select { min-width: 0 !important; width: 100%; }
}
</style>

<main id="content" class="container-fluid">

	<div class="card mb-3">
		<div class="card-body py-3">
			<div class="doc-cred">
				<div>
					<label class="form-label small mb-1">' . lang('Base address') . '</label>
					<div><code>' . h($api_base_url) . '</code></div>
				</div>
				<div>
					<label class="form-label small mb-1">' . lang('OpenAPI description') . '</label>
					<div><code>' . h($api_base_url . '/openapi.json') . '</code>
						<span class="opacity-50 ms-1">' . ($openapi_public
							? lang('open to anyone')
							: lang('with the key and secret, like every other call')) . '</span></div>
				</div>
				<div class="ms-auto d-flex align-items-end gap-2">
					<div>
						<label class="form-label small mb-1">' . lang('Try as') . '</label>
						<select class="form-select form-select-sm" id="doc_app" style="min-width:220px">' . $app_options . '</select>
					</div>
					<a class="btn btn-sm btn-outline-secondary" href="api_docs.php?openapi=1" target="_blank"
						title="' . h(lang('Opens the same document through this panel, with your own session instead of a key.')) . '">
						<i class="bi bi-braces me-1"></i>' . lang('View') . '</a>
				</div>
			</div>
			<p class="small opacity-75 mb-0 mt-3">
				<i class="bi bi-info-circle me-1"></i>'
				. lang('Authentication is HTTP Basic: the user name is the application key, the password is its secret. Money is always a whole number of minor units - 1999 is 19.99. Times are ISO-8601 in UTC. Listings are cursor paged: follow page.next_cursor until it is null.') . '
			</p>
			<p class="small opacity-75 mb-0 mt-2">
				<i class="bi bi-terminal me-1"></i>'
				. lang(array(
					'string' => 'Developers without a panel account can use the console at {var:1}.',
					'vars'   => '<a href="' . h($console_url) . '" target="_blank" rel="noopener">' . h($console_url) . '</a>'
				)) . '
			</p>
		</div>
	</div>

	<div class="doc-wrap">
		<div class="doc-nav card"><div class="card-body p-2">' . $nav . '</div></div>
		<div>' . $panels . '</div>
	</div>

</main>

<script>
' . api_console_script() . '
</script>
<script>
(function () {

	var BASE = ' . json_encode($api_base_url, JSON_UNESCAPED_UNICODE) . ';
	var TOKEN = ' . json_encode(isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '', JSON_UNESCAPED_UNICODE) . ';
	var credential = null;

	// The temporary credential is fetched on demand and kept in this closure
	// only: it is never written into the page, so a screenshot of this screen
	// carries no working key.
	function getCredential() {
		var appId = document.getElementById("doc_app").value;
		if (credential && credential.appId === appId && credential.until > Date.now()) {
			return Promise.resolve(credential);
		}
		var body = new URLSearchParams({ test_credential: "1", app_id: appId, token: TOKEN });
		return fetch("api_docs.php", { method: "POST", body: body,
			headers: { "Content-Type": "application/x-www-form-urlencoded" } })
			.then(function (r) { return r.json(); })
			.then(function (c) {
				credential = { appId: appId, key: c.key, secret: c.secret, until: Date.now() + (c.expires_in - 30) * 1000 };
				return credential;
			});
	}

	pgApiConsole.init({
		base: BASE,
		hasCredential: function () { return document.getElementById("doc_app").value !== ""; },
		getCredential: getCredential,
		missing: ' . json_encode(lang('Choose an application at the top first — the call is made with its permissions.'), JSON_UNESCAPED_UNICODE) . '
	});

})();
</script>' . output_footer();
