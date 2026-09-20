<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The API console served at integration.php/docs: a page where a developer
 * holding an application key reads the description and tries a call.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The panel has api_docs.php, but an outside developer has no panel account and
// never will: they hold a key and a secret and nothing else. This page is the
// same console for them, served by the API itself, and it is rendered by the
// same code (console_view.php) so the two cannot show different endpoints.
//
// The page is deliberately a shell. No endpoint, parameter or scope is written
// into it; the endpoint blocks are a second route, /docs/endpoints, that the
// page fetches in the browser with the key the reader typed and that is
// answered under the same rule as /openapi.json - credentials, or none while
// the operator has published the description. So the shell can be handed to
// anyone without giving away anything the description does not already guard.
//
// The credentials never reach the server except inside the calls the reader
// makes. They are kept in the tab's sessionStorage - gone when the tab closes -
// and the page is sent with no-store, a deny-frame header and a policy that
// lets its script talk to this site alone.
//
// The chrome is the panel's own session-less header and footer, so the page
// looks like the panel, follows the same stored colour scheme and picks up the
// backend stylesheet and scripts - the theme switch in the header is handled
// by the same code as the one in the panel's user menu.

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(__FILE__) . '/console_view.php');

// The endpoint blocks as an HTML fragment. Authenticated like /openapi.json,
// because it says the same things in another shape.
function api_console_endpoints($params) {

	$parts = api_console_endpoints_html();

	api_send_html(200, '<div id="frag_nav">' . $parts['nav'] . '</div>'
		. '<div id="frag_panels">' . $parts['panels'] . '</div>'
		. '<div id="frag_count" data-count="' . (int)$parts['count'] . '" data-groups="' . (int)$parts['groups'] . '"></div>');

}

function api_console_page($params) {

	$base_url = api_openapi_base_url();

	// Whether the machine-readable description answers without credentials. The
	// address is printed either way - a developer who cannot find it assumes
	// there is no description - and what changes is what the line says about
	// getting it.
	$console_settings = api_settings();

	$openapi_public = !empty($console_settings['openapi_public']);

	$site_title = defined('TITLE') ? TITLE : 'Pinegrap';

	// Everything the page's own script says to the reader, translated here and
	// handed over as one object so the script holds no prose.
	$strings = array(
		'connecting'         => lang('Connecting…'),
		'enter_credentials'  => lang('Enter your application key and secret to load the description.'),
		'public_description' => lang('The description is public on this site, so it is shown without a key. To try a call, connect with your application key and secret.'),
		'connected'          => lang('Connected. The description was loaded with your credentials.'),
		'forgotten'          => lang('The credentials were removed from this tab.'),
		'both_required'      => lang('Both the application key and the secret are required.'),
		'network_error'      => lang('The request could not be sent: {var:1}'),
		'unexpected_answer'  => lang('The server answered {var:1} without a readable error message.'),
		'connect_first'      => lang('Connect with your application key and secret first — the call is made with them.'),
		'connected_as'       => lang('Connected as'),
		'scopes'             => lang('Permissions'),
		'no_scopes'          => lang('This application holds no permissions.'),
		'api_version'        => lang('API version'),
		'site'               => lang('Site'),
		'server_time'        => lang('Server time'),
		'meta_unavailable'   => lang('Site information could not be read: {var:1}'),
		'endpoints'          => lang('{var:1} endpoints in {var:2} groups')
	);

	$curl_example = 'curl -u APPLICATION_KEY:SECRET_KEY "' . $base_url . '/meta"';

	// The panel's session-less chrome: Bootstrap, the icon font, the backend
	// stylesheet and the theme bootstrap that reads the reader's stored colour
	// scheme before the first paint. Its footer closes two wrappers, opened
	// below around the page.
	$html = output_header_secure(array('title' => lang('API console'), 'icon' => 'setting')) . '
<style>
.con-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
.con-head h1 { font-size: 22px; margin: 0; }
.con-head .con-sub { font-size: 14px; opacity: .6; }
.con-head .con-theme { margin-left: auto; }
.con-base { font-size: 12.5px; word-break: break-all; }
.con-meta dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; margin: 0; font-size: 12.5px; }
.con-meta dt { font-weight: 600; opacity: .7; }
.con-meta dd { margin: 0; word-break: break-word; }
.con-curl { font-size: 12px; white-space: pre-wrap; word-break: break-all; }
#doc_empty { padding: 40px 20px; text-align: center; opacity: .5; font-size: 13px; }
.doc-nav { max-height: calc(100vh - 32px); }
' . api_console_css() . '
</style>
<div class="container-fluid py-4" style="max-width: 1500px">
<div class="con-page">

	<div class="con-head">
		<h1>' . h($site_title) . '</h1>
		<span class="con-sub"><i class="bi bi-terminal me-1"></i>' . lang('API console') . '</span>
		<div class="btn-group btn-group-sm con-theme" role="group" aria-label="' . h(lang('Appearance')) . '">
			<button type="button" id="theme-light" class="btn btn-outline-secondary" data-bs-theme-value="light" title="' . h(lang('Light')) . '" aria-label="' . h(lang('Light')) . '"><i class="bi bi-sun-fill" aria-hidden="true"></i></button>
			<button type="button" id="theme-dark" class="btn btn-outline-secondary" data-bs-theme-value="dark" title="' . h(lang('Dark')) . '" aria-label="' . h(lang('Dark')) . '"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
			<button type="button" id="theme-auto" class="btn btn-outline-secondary" data-bs-theme-value="auto" title="' . h(lang('Auto')) . '" aria-label="' . h(lang('Auto')) . '"><i class="bi bi-circle-half" aria-hidden="true"></i></button>
		</div>
	</div>

	<div class="con-base mb-3"><span class="opacity-50">' . lang('Base address') . ':</span> <code id="con_base">' . h($base_url) . '</code>
		<span class="opacity-50 ms-3">' . lang('Authentication') . ':</span> <code>HTTP Basic</code>
		<span class="opacity-50 ms-1">' . lang('user name: application key, password: secret') . '</span>
		<span class="opacity-50 ms-3">' . lang('OpenAPI description') . ':</span> <code>' . h($base_url . '/openapi.json') . '</code>
		<span class="opacity-50 ms-2">' . ($openapi_public
			? lang('open to anyone')
			: lang('with the key and secret, like every other call')) . '</span></div>

	<div class="row g-3 mb-3">
		<div class="col-lg-7">
			<div class="card h-100">
				<div class="card-body py-3">
					<form id="con_form" autocomplete="off">
						<div class="row g-2 align-items-end">
							<div class="col-md-5">
								<label class="form-label small mb-1" for="con_key">' . lang('Application key') . '</label>
								<input type="text" class="form-control form-control-sm" id="con_key" autocomplete="off" spellcheck="false">
							</div>
							<div class="col-md-5">
								<label class="form-label small mb-1" for="con_secret">' . lang('Secret key') . '</label>
								<input type="password" class="form-control form-control-sm" id="con_secret" autocomplete="off">
							</div>
							<div class="col-md-2 d-flex gap-2">
								<button type="submit" class="btn btn-sm btn-primary flex-grow-1" id="con_connect"><i class="bi bi-plug me-1"></i>' . lang('Connect') . '</button>
								<button type="button" class="btn btn-sm btn-outline-secondary" id="con_forget" title="' . h(lang('Forget')) . '" aria-label="' . h(lang('Forget')) . '"><i class="bi bi-x-lg"></i></button>
							</div>
						</div>
					</form>
					<div id="con_status" class="small mt-3 mb-0 opacity-75"></div>
					<div id="con_meta" class="con-meta mt-3 d-none"></div>
					<p class="small opacity-75 mb-0 mt-3"><i class="bi bi-shield-lock me-1"></i>' . lang('The key and the secret stay in this browser tab only: they are sent with the calls you make from here and nowhere else, and they are gone when the tab closes.') . '</p>
				</div>
			</div>
		</div>
		<div class="col-lg-5">
			<div class="card h-100">
				<div class="card-body py-3 small">
					<p class="mb-2"><i class="bi bi-info-circle me-1"></i>' . lang('Authentication is HTTP Basic: the user name is the application key, the password is its secret. Money is always a whole number of minor units - 1999 is 19.99. Times are ISO-8601 in UTC. Listings are cursor paged: follow page.next_cursor until it is null. A write can be rehearsed before it is made: send X-Dry-Run: true and the endpoint runs every check it would run and answers instead of writing.') . '</p>
					<pre class="con-curl bg-body-tertiary rounded p-2 mb-2">' . h($curl_example) . '</pre>
					<p class="mb-0 opacity-75">' . lang('Where an endpoint says so, DELETE has a POST alias - either the same address answered to POST, or a /delete address - for servers that refuse the DELETE verb before it reaches the application.') . '</p>
				</div>
			</div>
		</div>
	</div>

	<div class="doc-wrap">
		<div class="doc-nav card"><div class="card-body p-2" id="doc_nav"></div></div>
		<div id="doc_main"><div id="doc_empty">' . lang('Enter your application key and secret to load the description.') . '</div></div>
	</div>

<script>
' . api_console_script() . '
</script>
<script>
(function () {

	var BASE = ' . json_encode($base_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
	var T = ' . json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
	var STORE_KEY = "pg_api_console_key";
	var STORE_SECRET = "pg_api_console_secret";

	var credentials = null;

	var keyInput = document.getElementById("con_key");
	var secretInput = document.getElementById("con_secret");
	var status = document.getElementById("con_status");
	var metaBox = document.getElementById("con_meta");
	var nav = document.getElementById("doc_nav");
	var main = document.getElementById("doc_main");

	function fill(template, values) {
		return template.replace(/\{var:(\d+)\}/g, function (all, index) {
			var value = values[parseInt(index, 10) - 1];
			return value === undefined ? "" : String(value);
		});
	}

	function esc(text) {
		return String(text === undefined || text === null ? "" : text)
			.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
	}

	function setStatus(text, kind) {
		status.className = "small mt-3 mb-0 " + (kind === "error" ? "text-danger" : (kind === "ok" ? "text-success" : "opacity-75"));
		status.textContent = text;
	}

	// sessionStorage is per tab and is cleared when the tab closes. It can be
	// unavailable or throw in a private window; the page then simply asks
	// again on the next load.
	function storeRead() {
		try {
			var key = sessionStorage.getItem(STORE_KEY);
			var secret = sessionStorage.getItem(STORE_SECRET);
			if (key && secret) { return { key: key, secret: secret }; }
		} catch (e) {}
		return null;
	}

	function storeWrite(c) {
		try {
			sessionStorage.setItem(STORE_KEY, c.key);
			sessionStorage.setItem(STORE_SECRET, c.secret);
		} catch (e) {}
	}

	function storeClear() {
		try {
			sessionStorage.removeItem(STORE_KEY);
			sessionStorage.removeItem(STORE_SECRET);
		} catch (e) {}
	}

	function request(path, c) {
		// X-Requested-With tells the API this is a script\'s request, so a
		// wrong secret comes back as a readable 401 instead of the browser\'s
		// own login dialog.
		var headers = { "X-Requested-With": "XMLHttpRequest" };
		if (c) { headers["Authorization"] = "Basic " + btoa(c.key + ":" + c.secret); }
		return fetch(BASE + path, { method: "GET", headers: headers }).then(function (response) {
			return response.text().then(function (text) {
				var body = null;
				try { body = JSON.parse(text); } catch (e) {}
				return { response: response, body: body, text: text };
			});
		});
	}

	// The API\'s own error sentence when the body carries one, else a line that
	// names the status so a proxy page or an empty answer is still explained.
	function errorText(result) {
		if (result.body && result.body.error && result.body.error.message) {
			return result.response.status + " · " + result.body.error.message;
		}
		return fill(T.unexpected_answer, [result.response.status]);
	}

	/* ---- the endpoint list ------------------------------------------- */

	// The fragment is server-rendered by the same code as the panel\'s
	// documentation page; this only places its two halves and wires the Try
	// buttons through the shared script with the typed credential.
	function place(text) {
		var holder = document.createElement("template");
		holder.innerHTML = text;
		var fragNav = holder.content.querySelector("#frag_nav");
		var fragPanels = holder.content.querySelector("#frag_panels");
		var fragCount = holder.content.querySelector("#frag_count");
		if (!fragNav || !fragPanels) { return false; }
		nav.innerHTML = fragNav.innerHTML;
		main.innerHTML = "<div class=\'small opacity-50 mb-3\'>" +
			esc(fill(T.endpoints, [fragCount ? fragCount.dataset.count : "", fragCount ? fragCount.dataset.groups : ""])) + "</div>" + fragPanels.innerHTML;
		pgApiConsole.init({
			base: BASE,
			hasCredential: function () { return credentials !== null; },
			getCredential: function () { return Promise.resolve(credentials); },
			missing: T.connect_first
		}, main);
		return true;
	}

	function loadEndpoints(c) {
		return request("/docs/endpoints", c).then(function (result) {
			if (!result.response.ok) {
				return { ok: false, message: errorText(result) };
			}
			return { ok: place(result.text), message: fill(T.unexpected_answer, [result.response.status]) };
		});
	}

	/* ---- connecting ---------------------------------------------------- */

	// The endpoint list first, because that request is what tells whether the
	// key works at all; then the site information, which needs the meta:read
	// permission and is merely shown when it comes.
	function connect(c, remember) {
		setStatus(T.connecting);
		return loadEndpoints(c).then(function (outcome) {
			if (!outcome.ok) {
				setStatus(outcome.message, "error");
				return false;
			}
			credentials = c;
			if (remember) { storeWrite(c); }
			keyInput.value = c.key;
			secretInput.value = c.secret;
			setStatus(T.connected, "ok");
			loadMeta(c);
			return true;
		}).catch(function (error) {
			setStatus(fill(T.network_error, [String(error)]), "error");
			return false;
		});
	}

	function loadMeta(c) {
		metaBox.classList.add("d-none");
		metaBox.innerHTML = "";
		request("/meta", c).then(function (result) {
			var body = result.body;
			if (!result.response.ok || !body) {
				metaBox.innerHTML = "<div class=\'small text-warning\'>" + esc(fill(T.meta_unavailable, [errorText(result)])) + "</div>";
				metaBox.classList.remove("d-none");
				return;
			}
			var scopes = Array.isArray(body.scopes) && body.scopes.length
				? body.scopes.map(function (s) { return "<span class=\'badge text-bg-secondary fw-normal me-1\'>" + esc(s) + "</span>"; }).join("")
				: "<span class=\'opacity-50\'>" + esc(T.no_scopes) + "</span>";
			metaBox.innerHTML =
				"<div class=\'small fw-semibold mb-2\'><i class=\'bi bi-person-check me-1\'></i>" + esc(T.connected_as) + " <code>" + esc(c.key) + "</code></div>" +
				"<dl>" +
				"<dt>" + esc(T.scopes) + "</dt><dd>" + scopes + "</dd>" +
				"<dt>" + esc(T.api_version) + "</dt><dd>" + esc(body.api_version) + "</dd>" +
				"<dt>" + esc(T.site) + "</dt><dd>" + esc(body.site && body.site.title ? body.site.title : "") + "</dd>" +
				"<dt>" + esc(T.server_time) + "</dt><dd>" + esc(body.server_time) + "</dd>" +
				"</dl>";
			metaBox.classList.remove("d-none");
		}).catch(function (error) {
			metaBox.innerHTML = "<div class=\'small text-warning\'>" + esc(fill(T.meta_unavailable, [String(error)])) + "</div>";
			metaBox.classList.remove("d-none");
		});
	}

	document.getElementById("con_form").addEventListener("submit", function (event) {
		event.preventDefault();
		var key = keyInput.value.trim();
		var secret = secretInput.value;
		if (key === "" || secret === "") {
			setStatus(T.both_required, "error");
			return;
		}
		var button = document.getElementById("con_connect");
		button.disabled = true;
		connect({ key: key, secret: secret }, true).then(function () { button.disabled = false; });
	});

	document.getElementById("con_forget").addEventListener("click", function () {
		storeClear();
		credentials = null;
		keyInput.value = "";
		secretInput.value = "";
		metaBox.classList.add("d-none");
		metaBox.innerHTML = "";
		pgApiConsole.reset(main);
		setStatus(T.forgotten);
	});

	/* ---- start --------------------------------------------------------- */

	// Credentials already in this tab are used again; otherwise the list is
	// asked for without any, which only answers while the operator has
	// published the description - then the reader can browse before typing a
	// key, and the Try buttons ask for one.
	var stored = storeRead();

	if (stored) {
		connect(stored, false);
	} else {
		loadEndpoints(null).then(function (outcome) {
			setStatus(outcome.ok ? T.public_description : T.enter_credentials);
		}).catch(function () {
			setStatus(T.enter_credentials);
		});
	}

})();
</script>
' . output_footer_secure();

	api_send_html(200, $html);

}
