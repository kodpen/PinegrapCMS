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

$api_base_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/integration.php';

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

// Endpoints grouped by resource, in the order the schema declares them.
$groups = array();

foreach (api_schema() as $route) {

	$parts = explode('.', $route['id']);

	$tag = $parts[0];

	if (!isset($groups[$tag])) { $groups[$tag] = array(); }

	$groups[$tag][] = $route;

}

$group_labels = array(
	'meta'           => lang('Site info'),
	'products'       => lang('Products'),
	'product_groups' => lang('Product Groups'),
	'inventory'      => lang('Inventory'),
	'orders'         => lang('Orders'),
	'customers'      => lang('Customers'),
	'pages'          => lang('Pages'),
	'files'          => lang('Files'),
	'offers'         => lang('Offers'),
	'webhooks'       => lang('Webhooks')
);

$nav = '';

$panels = '';

foreach ($groups as $tag => $routes) {

	$label = isset($group_labels[$tag]) ? $group_labels[$tag] : ucfirst($tag);

	$nav .= '<div class="doc-nav-group">' . h($label) . '</div>';

	foreach ($routes as $route) {

		$anchor = 'ep_' . str_replace('.', '_', $route['id']);

		$method_class = strtolower($route['method']);

		$nav .= '<a class="doc-nav-item" href="#' . $anchor . '">
			<span class="doc-m ' . $method_class . '">' . h($route['method']) . '</span>
			<span>' . h($route['summary']) . '</span></a>';

		// Parameters, split the way the caller has to think about them.
		$path_params = '';
		$query_params = '';
		$body_params = '';

		foreach ($route['params'] as $param) {

			$where = isset($param['in']) ? $param['in'] : 'query';

			$type = isset($param['type']) ? $param['type'] : 'string';

			$notes = array();

			if (!empty($param['required'])) { $notes[] = '<b class="text-danger">' . lang('required') . '</b>'; }

			if (isset($param['values'])) { $notes[] = h(implode(' | ', $param['values'])); }

			if (isset($param['min']) || isset($param['max'])) {

				$notes[] = h((isset($param['min']) ? $param['min'] : '') . '–' . (isset($param['max']) ? $param['max'] : ''));

			}

			if (isset($param['default'])) { $notes[] = lang('default') . ' ' . h($param['default']); }

			if (isset($param['description'])) { $notes[] = h($param['description']); }

			$row = '<tr><td><code>' . h($param['name']) . '</code></td>
				<td><span class="doc-type">' . h($type) . '</span></td>
				<td class="doc-note">' . implode(' &middot; ', $notes) . '</td></tr>';

			if ($where === 'path') { $path_params .= $row; }
			elseif ($where === 'body') { $body_params .= $row; }
			else { $query_params .= $row; }

		}

		$tables = '';

		foreach (array(
			array(lang('Path'), $path_params),
			array(lang('Query'), $query_params),
			array(lang('Body'), $body_params)
		) as $set) {

			if ($set[1] === '') { continue; }

			$tables .= '<div class="doc-params"><span class="doc-params-h">' . h($set[0]) . '</span>
				<table class="table table-sm mb-0"><tbody>' . $set[1] . '</tbody></table></div>';

		}

		if ($tables === '') {

			$tables = '<p class="small opacity-50 mb-0">' . lang('This endpoint takes no parameters.') . '</p>';

		}

		$accepts = '';

		if (isset($route['also_accepts'])) {

			$accepts = ' <span class="doc-also">' . lang(array(
				'string' => '{var:1} is accepted as well',
				'vars'   => implode(', ', $route['also_accepts'])
			)) . '</span>';

		}

		$panels .= '
		<div class="doc-ep" id="' . $anchor . '" data-id="' . h($route['id']) . '"
			data-method="' . h($route['method']) . '" data-path="' . h($route['path']) . '">
			<div class="doc-ep-head">
				<span class="doc-m ' . $method_class . '">' . h($route['method']) . '</span>
				<code class="doc-path">' . h($route['path']) . '</code>
				' . ($route['scope'] !== '' ? '<span class="doc-scope"><i class="bi bi-shield-check"></i> ' . h($route['scope']) . '</span>' : '') . '
			</div>
			<div class="doc-ep-body">
				<p class="doc-summary">' . h($route['summary']) . $accepts . '</p>
				' . (isset($route['description']) ? '<p class="doc-desc">' . h($route['description']) . '</p>' : '') . '
				' . $tables . '
				<div class="doc-try">
					<button type="button" class="btn btn-sm btn-outline-primary doc-try-btn">
						<i class="bi bi-play-fill me-1"></i>' . lang('Try it') . '</button>
					<span class="doc-try-hint small opacity-50">' . lang('Uses a temporary credential, not a real secret.') . '</span>
				</div>
				<div class="doc-try-panel d-none"></div>
			</div>
		</div>';

	}

}

echo pg_page_shell(array(
	'title'               => lang('API Documentation'),
	'extra classes'       => 'api-docs',
	'icon'                => 'settings',
	'heading'             => lang('API Documentation'),
	'heading_description' => lang('Every endpoint this site offers, read from the same table the API itself dispatches from.'),
	'breadcrumb'          => array(
		array('label' => lang('Application Access'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api_settings.php'),
		array('label' => lang('API Documentation'))
	)
)) . '

<style>
.doc-wrap { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
/* A grid item defaults to min-width:auto, which means it refuses to become
   narrower than its widest content - so the parameter table with its minimum
   width pushed this column past the screen and took the whole page with it,
   and the overflow-x below never had anything to scroll. Letting the columns
   shrink is what hands the scrolling back to the element that asked for it. */
.doc-wrap > * { min-width: 0; }
.doc-nav { position: sticky; top: 16px; max-height: calc(100vh - 120px); overflow: auto; }
/* One column once the endpoint panels no longer have room beside the index.
   The index stops being sticky at the same point: pinned to the top of a narrow
   screen it would take a third of the height and leave the endpoint it links to
   with no room to be read. */
@media (max-width: 992px) {
	.doc-wrap { grid-template-columns: 1fr; }
	.doc-nav { position: static; max-height: 260px; }
}
.doc-nav-group { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; opacity: .5;
	padding: 12px 10px 5px; }
.doc-nav-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 7px;
	font-size: 12px; text-decoration: none; color: inherit; }
.doc-nav-item:hover { background: var(--bs-tertiary-bg); color: inherit; }
.doc-m { font-size: 9.5px; font-weight: 700; letter-spacing: .03em; padding: 2px 6px; border-radius: 4px;
	background: var(--bs-tertiary-bg); flex: none; min-width: 42px; text-align: center; }
.doc-m.post { color: var(--bs-success); }
.doc-m.get { color: var(--bs-primary); }
.doc-ep { border: 1px solid var(--bs-border-color); border-radius: 10px; margin-bottom: 14px; overflow: hidden; }
.doc-ep-head { display: flex; align-items: center; gap: 10px; padding: 11px 14px; background: var(--bs-tertiary-bg); }
.doc-path { font-size: 13px; font-weight: 600; word-break: break-all; }
.doc-scope { margin-left: auto; font-size: 10.5px; opacity: .7; }
.doc-ep-body { padding: 14px; }
.doc-summary { font-size: 13px; margin-bottom: 6px; }
.doc-also { font-size: 10.5px; opacity: .6; }
.doc-desc { font-size: 12px; opacity: .75; line-height: 1.55; }
.doc-params { margin-top: 12px; }
.doc-params-h { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; opacity: .5; }
/* The parameter table has two fixed columns and one that carries a sentence, so
   below roughly 560px it squeezes rather than overflows - the note column drops
   to a word per line. It scrolls sideways instead, the same as the application
   list does on the screen this page is reached from. */
.doc-params { overflow-x: auto; }
.doc-params table { min-width: 520px; }
.doc-params table td { padding: 5px 8px; font-size: 12px; vertical-align: top; border-color: var(--bs-border-color); }
.doc-params table td:first-child { width: 170px; }
.doc-params table td:nth-child(2) { width: 90px; }
.doc-type { font-size: 10.5px; padding: 1px 6px; border-radius: 4px; border: 1px solid var(--bs-border-color); opacity: .8; }
.doc-note { font-size: 11.5px; opacity: .75; }
.doc-try { display: flex; align-items: center; gap: 10px; margin-top: 14px; }
.doc-try-panel { margin-top: 12px; }
.doc-try-panel pre { background: #1e1e1e; color: #d4d4d4; border-radius: 8px; padding: 12px;
	font-size: 12px; max-height: 340px; overflow: auto; margin: 0; }
.doc-cred { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
@media (max-width: 768px) {
	.doc-cred > .ms-auto { margin-left: 0 !important; width: 100%; flex-wrap: wrap; }
	.doc-cred select { min-width: 0 !important; width: 100%; }
	.doc-ep-head { flex-wrap: wrap; }
	.doc-scope { margin-left: 0; flex-basis: 100%; }
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
				<div class="ms-auto d-flex align-items-end gap-2">
					<div>
						<label class="form-label small mb-1">' . lang('Try as') . '</label>
						<select class="form-select form-select-sm" id="doc_app" style="min-width:220px">' . $app_options . '</select>
					</div>
					<a class="btn btn-sm btn-outline-secondary" href="api_docs.php?openapi=1" target="_blank">
						<i class="bi bi-braces me-1"></i>' . lang('OpenAPI description') . '</a>
				</div>
			</div>
			<p class="small opacity-75 mb-0 mt-3">
				<i class="bi bi-info-circle me-1"></i>'
				. lang('Authentication is HTTP Basic: the user name is the application key, the password is its secret. Money is always a whole number of minor units - 1999 is 19.99. Times are ISO-8601 in UTC. Listings are cursor paged: follow page.next_cursor until it is null.') . '
			</p>
		</div>
	</div>

	<div class="doc-wrap">
		<div class="doc-nav card"><div class="card-body p-2">' . $nav . '</div></div>
		<div>' . $panels . '</div>
	</div>

</main>

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

	function fieldsFor(endpoint) {
		var html = "<div class=\'row g-2 mb-2\'>";
		endpoint.querySelectorAll(".doc-params table tr").forEach(function (row) {
			var name = row.querySelector("td code").textContent;
			var typeCell = row.querySelector(".doc-type");
			var type = typeCell ? typeCell.textContent.trim() : "string";
			html += "<div class=\'col-6 col-lg-4\'><label class=\'form-label small mb-1\'>" + name + "</label>";
			// The declared type travels with the field so send() knows which
			// values need parsing. A list can run long, so it gets a textarea,
			// and the placeholder shows the JSON form it expects.
			if (type === "list") {
				html += "<textarea rows=\'2\' class=\'form-control form-control-sm doc-field\' data-name=\'" + name +
					"\' data-type=\'list\' placeholder=\'[\"a\", \"b\"]\'></textarea></div>";
			} else {
				html += "<input type=\'text\' class=\'form-control form-control-sm doc-field\' data-name=\'" + name +
					"\' data-type=\'" + type + "\'></div>";
			}
		});
		return html + "</div>";
	}

	// A JSON array is taken as written; a plain line such as "a, b" is split
	// on commas for a list of words. Anything else that opens a bracket is a
	// broken JSON attempt and comes back as null so the caller can say so.
	function parseList(text) {
		try {
			var parsed = JSON.parse(text);
			if (Array.isArray(parsed)) { return parsed; }
		} catch (e) {}
		if (text.indexOf("[") > -1) { return null; }
		return text.split(",").map(function (item) { return item.trim(); })
			.filter(function (item) { return item !== ""; });
	}

	document.querySelectorAll(".doc-try-btn").forEach(function (button) {

		var endpoint = button.closest(".doc-ep");
		var panel = endpoint.querySelector(".doc-try-panel");

		button.addEventListener("click", function () {

			if (!document.getElementById("doc_app").value) {
				panel.classList.remove("d-none");
				panel.innerHTML = "<div class=\'alert alert-warning py-2 px-3 small mb-0\'>" +
					' . json_encode(lang('Choose an application at the top first — the call is made with its permissions.'), JSON_UNESCAPED_UNICODE) . ' +
					"</div>";
				return;
			}

			if (panel.classList.contains("d-none")) {
				panel.classList.remove("d-none");
				panel.innerHTML = fieldsFor(endpoint) +
					"<button type=\'button\' class=\'btn btn-sm btn-primary doc-send\'>" +
					' . json_encode(lang('Send'), JSON_UNESCAPED_UNICODE) . ' + "</button>" +
					"<pre class=\'mt-2 d-none\'></pre>";

				panel.querySelector(".doc-send").addEventListener("click", function () {
					send(endpoint, panel, this);
				});
				return;
			}

			panel.classList.add("d-none");

		});

	});

	function send(endpoint, panel, button) {

		var method = endpoint.dataset.method;
		var path = endpoint.dataset.path;
		var output = panel.querySelector("pre");
		var body = {};
		var problem = null;

		panel.querySelectorAll(".doc-field").forEach(function (field) {
			if (field.value === "") { return; }
			var name = field.dataset.name;
			if (path.indexOf("{" + name + "}") > -1) {
				path = path.replace("{" + name + "}", encodeURIComponent(field.value));
			} else if (method === "GET") {
				path += (path.indexOf("?") > -1 ? "&" : "?") + name + "=" + encodeURIComponent(field.value);
			} else if (field.dataset.type === "list") {
				// The router casts booleans and integers from their string
				// form, so those travel as typed; a list has to arrive as a
				// JSON array or validation rejects it, hence the parsing here.
				var list = parseList(field.value);
				if (list === null) {
					problem = ' . json_encode(lang(array(
						'string' => '{var:1} could not be read as a list. Enter a JSON array such as ["a", "b"], or values separated by commas.',
						'vars'   => '%NAME%'
					)), JSON_UNESCAPED_UNICODE) . '.replace("%NAME%", name);
					return;
				}
				body[name] = list;
			} else {
				body[name] = field.value;
			}
		});

		if (problem !== null) {
			output.classList.remove("d-none");
			output.textContent = problem;
			return;
		}

		button.disabled = true;
		output.classList.remove("d-none");
		output.textContent = "…";

		var started = performance.now();

		getCredential().then(function (c) {

			var options = { method: method, headers: {
				"Authorization": "Basic " + btoa(c.key + ":" + c.secret) } };

			if (method !== "GET") {
				options.headers["Content-Type"] = "application/json";
				options.body = JSON.stringify(body);
			}

			return fetch(BASE + path, options).then(function (response) {
				return response.text().then(function (text) {
					var pretty = text;
					try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
					output.textContent = method + " " + path + "\\n" +
						response.status + " · " + Math.round(performance.now() - started) + " ms\\n\\n" + pretty;
				});
			});

		}).catch(function (error) {
			output.textContent = String(error);
		}).finally(function () {
			button.disabled = false;
		});

	}

})();
</script>' . output_footer();
