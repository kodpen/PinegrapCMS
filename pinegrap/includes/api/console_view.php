<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The endpoint documentation and the "Try it" machinery shared by the panel's
 * api_docs.php and the session-less console at integration.php/docs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Two screens show this API's endpoints: the panel's documentation page and the
// console the API serves to an outside developer. They are one renderer here so
// they cannot drift apart - a parameter added to the route table appears on
// both, and a fix to how a list field is read applies to both.
//
// Everything on this side reads includes/api/schema.php, the same table the
// router dispatches from. The two pages keep only their own chrome: the panel
// shell and application picker on one side, the standalone document and the
// connect card on the other. What differs between them in behaviour is where
// the credential for a call comes from, and that is handed in as a function.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The endpoint blocks and the index beside them, grouped by resource in the
// order the schema declares them. Returns array('nav' => html, 'panels' => html,
// 'count' => endpoints, 'groups' => groups). $try_hint is a sentence shown
// beside the Try button, or '' for none.
function api_console_endpoints_html($try_hint = '') {

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
		'forms'          => lang('Forms'),
		'seo'            => lang('SEO'),
		'system'         => lang('System'),
		'reports'        => lang('Reports'),
		'offers'         => lang('Offers'),
		'webhooks'       => lang('Webhooks')
	);

	$nav = '';

	$panels = '';

	$count = 0;

	foreach ($groups as $tag => $routes) {

		$label = isset($group_labels[$tag]) ? $group_labels[$tag] : ucfirst($tag);

		$nav .= '<div class="doc-nav-group">' . h($label) . '</div>';

		$panels .= '<div class="doc-group-h">' . h($label) . '</div>';

		foreach ($routes as $route) {

			$count++;

			$anchor = 'ep_' . str_replace('.', '_', $route['id']);

			$method_class = strtolower($route['method']);

			$nav .= '<a class="doc-nav-item" href="#' . $anchor . '">
				<span class="doc-m ' . $method_class . '">' . h($route['method']) . '</span>
				<span>' . h($route['summary']) . '</span></a>';

			// Parameters, split the way the caller has to think about them. The
			// declared type, place and allowed values ride on the row so the Try
			// panel is built from the table without a second copy of the schema.
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

				$values = isset($param['values']) ? ' data-values="' . h(json_encode(array_values($param['values']))) . '"' : '';

				$row = '<tr data-name="' . h($param['name']) . '" data-where="' . h($where) . '" data-type="' . h($type) . '"' . $values . '>
					<td><code>' . h($param['name']) . '</code></td>
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

			// The call as one line to paste, on every endpoint rather than in a
			// paragraph at the top of the page. The -u flag is the whole
			// authentication story: somebody reading one endpoint should not
			// have to go looking for how to sign in, and "which method?" is the
			// first question an integrator asks and the last one a reference
			// page tends to answer.
			$curl = 'curl -u APPLICATION_KEY:SECRET_KEY';

			if ($route['method'] !== 'GET') {

				$curl .= ' -X ' . $route['method'];

			}

			if ($body_params !== '') {

				$curl .= ' -H "Content-Type: application/json" -d \'{...}\'';

			}

			$curl .= ' "' . api_openapi_base_url() . $route['path'] . '"';

			$hint = ($try_hint === '') ? '' : '<span class="doc-try-hint small opacity-50">' . h($try_hint) . '</span>';

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
					<div class="doc-curl"><code>' . h($curl) . '</code></div>
					<div class="doc-try">
						<button type="button" class="btn btn-sm btn-outline-primary doc-try-btn">
							<i class="bi bi-play-fill me-1"></i>' . lang('Try it') . '</button>
						' . $hint . '
					</div>
					<div class="doc-try-panel d-none"></div>
				</div>
			</div>';

		}

	}

	// What the site can tell an application about, with its own place in the
	// list on the left. Until now the catalogue lived in the source and in the
	// panel's subscription form, so an integrator reading these pages could not
	// find out what there was to subscribe to.
	$nav .= '<div class="doc-nav-group">' . h(lang('Events')) . '</div>
		<a class="doc-nav-item" href="#ep_events">
			<span class="doc-m post">~</span>
			<span>' . h(lang('What this site can tell you')) . '</span></a>';

	$panels .= '<div class="doc-group-h">' . h(lang('Events')) . '</div>' . api_console_events_html();

	// The failures, last, with their own place in the list on the left. Neither
	// block is counted as an endpoint - neither is one - so "31 endpoints"
	// keeps meaning what it says.
	$nav .= '<div class="doc-nav-group">' . h(lang('Errors')) . '</div>
		<a class="doc-nav-item" href="#ep_errors">
			<span class="doc-m del">!</span>
			<span>' . h(lang('Codes and the answer shape')) . '</span></a>';

	$panels .= '<div class="doc-group-h">' . h(lang('Errors')) . '</div>' . api_console_errors_html();

	return array('nav' => $nav, 'panels' => $panels, 'count' => $count, 'groups' => count($groups));

}

// The event catalogue, as one block both pages print under the endpoint list.
//
// A webhook is only worth registering if you know what can arrive at it, and
// the names were readable nowhere but the source and the panel's own form. Each
// line says what the event really covers, including where it does not fire: a
// bulk import writes thousands of contacts and stays quiet on purpose, and an
// integrator needs to read that here rather than discover it in production.
function api_console_events_html() {

	require_once(dirname(__FILE__) . '/outbound/webhooks.php');

	$rows = '';

	foreach (api_webhook_events() as $event => $description) {

		$rows .= '<tr>
			<td><code>' . h($event) . '</code></td>
			<td class="doc-note">' . h($description) . '</td></tr>';

	}

	return '<div class="doc-ep" id="ep_events">
		<div class="doc-ep-head">
			<span class="doc-m post">' . h(lang('Events')) . '</span>
			<code class="doc-path">' . h(lang('Registered with POST /webhooks')) . '</code>
		</div>
		<div class="doc-ep-body">
			<p class="doc-desc">' . h(lang('Rather than ask this API on a timer, an application can register an address and be told. The site posts a JSON body carrying the event name, created_at as a UTC instant, and the few fields in data that identify the thing, and signs it with the subscription secret in the X-Pinegrap-Signature header. Delivery is queued and sent by the scheduled task, so nothing an outside server does can slow down a checkout. Fetch the object itself when you need more than the identifiers.')) . '</p>
			<div class="doc-curl"><code>{"event": "order.created", "created_at": "2026-01-25T09:14:03Z", "data": {"id": 1042}}</code></div>
			<div class="doc-params mt-3"><span class="doc-params-h">' . h(lang('Events')) . '</span>
				<table class="table table-sm mb-0"><tbody>' . $rows . '</tbody></table></div>
		</div>
	</div>';

}

// The failures, as one block both pages print under the endpoint list.
//
// An integrator meets these before they meet half the endpoints, and until now
// the only way to learn what a code meant was to cause it. The envelope is
// printed too: every failure has the same four fields, and the request_id is
// what the operator needs to find the call in the site\'s API log.
function api_console_errors_html() {

	$rows = '';

	foreach (api_error_catalogue() as $code => $entry) {

		$rows .= '<tr>
			<td><code>' . h($code) . '</code></td>
			<td><span class="doc-type">' . (int)$entry[0] . '</span></td>
			<td class="doc-note">' . h($entry[1]) . '</td></tr>';

	}

	return '<div class="doc-ep" id="ep_errors">
		<div class="doc-ep-head">
			<span class="doc-m del">' . h(lang('Errors')) . '</span>
			<code class="doc-path">' . h(lang('Every failure answers with the same shape')) . '</code>
		</div>
		<div class="doc-ep-body">
			<p class="doc-desc">' . h(lang('A failure carries the code to branch on, a message written for a person, the request_id that identifies the call in this site\'s API log, and - when the failure is about how the call was made - the address of this page. The message may be reworded or translated; the code will not be.')) . '</p>
			<div class="doc-curl"><code>{"error": {"code": "validation_failed", "message": "...", "field": "limit", "request_id": "8f2c...", "docs": "..."}}</code></div>
			<div class="doc-params mt-3"><span class="doc-params-h">' . h(lang('Codes')) . '</span>
				<table class="table table-sm mb-0"><tbody>' . $rows . '</tbody></table></div>
			<p class="doc-desc mt-3">' . h(lang('Rate limit: every answer carries X-RateLimit-Limit, X-RateLimit-Remaining and X-RateLimit-Reset, and a refused call carries Retry-After. A 409 is reported by the endpoint that can produce it and is named in that endpoint\'s description.')) . '</p>
		</div>
	</div>';

}

// The stylesheet for the blocks above, without the <style> tags. Both pages
// print it inside their own style element beside their page-specific rules.
function api_console_css() {

	return '
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
.doc-m.patch, .doc-m.put { color: var(--bs-warning-text-emphasis); }
.doc-m.delete { color: var(--bs-danger); }
.doc-group-h { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; opacity: .55; margin: 22px 0 10px; }
.doc-group-h:first-child { margin-top: 0; }
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
/* The paste-me line. Quiet enough not to compete with the parameter table,
   wide enough to select in one drag, and it wraps rather than scrolls: a
   command that is cut off at the right edge is copied cut off too. */
.doc-curl { margin-top: 12px; background: var(--bs-tertiary-bg); border-radius: 6px;
	padding: 7px 10px; overflow-wrap: anywhere; }
.doc-curl code { font-size: 11.5px; color: var(--bs-body-color); opacity: .75; }
.doc-try { display: flex; align-items: center; gap: 10px; margin-top: 14px; }
.doc-try-panel { margin-top: 12px; }
.doc-try-panel pre { background: #1e1e1e; color: #d4d4d4; border-radius: 8px; padding: 12px;
	font-size: 12px; max-height: 380px; overflow: auto; margin: 0; white-space: pre-wrap; word-break: break-word; }
.doc-problem { font-size: 12px; }
@media (max-width: 768px) {
	.doc-ep-head { flex-wrap: wrap; }
	.doc-scope { margin-left: 0; flex-basis: 100%; }
}
';

}

// The script for the Try panels, without the <script> tags. It defines one
// global, pgApiConsole, and does nothing until a page calls
//
//   pgApiConsole.init({
//       base:          "https://site/pinegrap/integration.php",
//       hasCredential: function () { return true|false; },
//       getCredential: function () { return Promise.resolve({key: "", secret: ""}); },
//       missing:       "sentence shown when hasCredential() is false"
//   });
//
// Where the credential comes from is the one thing the two pages do
// differently: the panel fetches a fifteen-minute temporary one for the chosen
// application, the public console returns what was typed into its connect
// card. Everything from the field list to the response print-out is here.
function api_console_script() {

	return '
window.pgApiConsole = (function () {

	var T = {
		send:      ' . json_encode(lang('Send'), JSON_UNESCAPED_UNICODE) . ',
		sending:   ' . json_encode(lang('Sending…'), JSON_UNESCAPED_UNICODE) . ',
		empty:     ' . json_encode(lang('(empty)'), JSON_UNESCAPED_UNICODE) . ',
		noContent: ' . json_encode(lang('(no content)'), JSON_UNESCAPED_UNICODE) . ',
		ms:        ' . json_encode(lang('ms'), JSON_UNESCAPED_UNICODE) . ',
		rateLimit: ' . json_encode(lang('Rate limit'), JSON_UNESCAPED_UNICODE) . ',
		retry:     ' . json_encode(lang(array('string' => 'Retry after {var:1} s', 'vars' => '%VALUE%')), JSON_UNESCAPED_UNICODE) . ',
		pathMissing: ' . json_encode(lang(array('string' => '{var:1} is required: it is part of the address.', 'vars' => '%NAME%')), JSON_UNESCAPED_UNICODE) . ',
		listUnreadable: ' . json_encode(lang(array(
			'string' => '{var:1} could not be read as a list. Enter a JSON array such as ["a", "b"], or values separated by commas.',
			'vars'   => '%NAME%'
		)), JSON_UNESCAPED_UNICODE) . ',
		noCredential: ' . json_encode(lang('No credential is available for this call.'), JSON_UNESCAPED_UNICODE) . '
	};

	var settings = null;

	function esc(text) {
		return String(text === undefined || text === null ? "" : text)
			.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#39;");
	}

	// One input per parameter row of the endpoint\'s tables. The declared type
	// travels with the field so send() knows which values need parsing: a list
	// gets a textarea whose placeholder shows the JSON form it expects, an
	// enumeration and a switch get a select so a typo is not possible.
	function fieldsFor(endpoint) {
		var html = "<div class=\'row g-2 mb-2\'>";
		endpoint.querySelectorAll(".doc-params table tr").forEach(function (row) {
			var name = row.dataset.name;
			var type = row.dataset.type || "string";
			var where = row.dataset.where || "query";
			var attributes = " data-name=\'" + esc(name) + "\' data-type=\'" + esc(type) + "\' data-where=\'" + esc(where) + "\'";
			html += "<div class=\'col-6 col-lg-4\'><label class=\'form-label small mb-1\'>" + esc(name) +
				" <span class=\'opacity-50\'>" + esc(where) + "</span></label>";
			if (type === "list") {
				html += "<textarea rows=\'2\' class=\'form-control form-control-sm doc-field\'" + attributes +
					" placeholder=\'[&quot;a&quot;, &quot;b&quot;]\'></textarea>";
			} else if (type === "enum") {
				var values = [];
				try { values = JSON.parse(row.dataset.values || "[]"); } catch (e) {}
				html += "<select class=\'form-select form-select-sm doc-field\'" + attributes + "><option value=\'\'>" + esc(T.empty) + "</option>";
				values.forEach(function (value) { html += "<option value=\'" + esc(value) + "\'>" + esc(value) + "</option>"; });
				html += "</select>";
			} else if (type === "bool") {
				html += "<select class=\'form-select form-select-sm doc-field\'" + attributes + "><option value=\'\'>" + esc(T.empty) +
					"</option><option value=\'true\'>true</option><option value=\'false\'>false</option></select>";
			} else {
				html += "<input type=\'text\' class=\'form-control form-control-sm doc-field\'" + attributes + " autocomplete=\'off\' spellcheck=\'false\'>";
			}
			html += "</div>";
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
		if (text.indexOf("[") > -1 || text.indexOf("{") > -1) { return null; }
		return text.split(",").map(function (item) { return item.trim(); })
			.filter(function (item) { return item !== ""; });
	}

	function showProblem(panel, text) {
		var box = panel.querySelector(".doc-problem");
		box.textContent = text;
		box.classList.remove("d-none");
	}

	function openPanel(endpoint, panel) {
		panel.classList.remove("d-none");
		panel.innerHTML = fieldsFor(endpoint) +
			"<button type=\'button\' class=\'btn btn-sm btn-primary doc-send\'>" + esc(T.send) + "</button>" +
			"<div class=\'doc-problem text-danger mt-2 d-none\'></div>" +
			"<pre class=\'mt-2 d-none\'></pre>";
		panel.querySelector(".doc-send").addEventListener("click", function () {
			send(endpoint, panel, this);
		});
	}

	function toggle(endpoint) {
		var panel = endpoint.querySelector(".doc-try-panel");

		if (!settings.hasCredential()) {
			panel.classList.remove("d-none");
			panel.innerHTML = "<div class=\'alert alert-warning py-2 px-3 small mb-0\'>" + esc(settings.missing) + "</div>";
			return;
		}

		if (panel.classList.contains("d-none") || !panel.querySelector(".doc-send")) {
			openPanel(endpoint, panel);
			return;
		}

		panel.classList.add("d-none");
	}

	function send(endpoint, panel, button) {

		var method = endpoint.dataset.method;
		var path = endpoint.dataset.path;
		var output = panel.querySelector("pre");
		var body = {};
		var query = [];
		var hasBody = false;
		var problem = null;

		panel.querySelector(".doc-problem").classList.add("d-none");

		panel.querySelectorAll(".doc-field").forEach(function (field) {
			if (problem !== null) { return; }
			var name = field.dataset.name;
			var where = field.dataset.where;
			var value = field.value;

			// A path placeholder has to be filled: the address is not an
			// address without it, and the router would answer 404 for a
			// literal "{id}" rather than say which value was missing.
			if (where === "path") {
				if (value.trim() === "") {
					problem = T.pathMissing.replace("%NAME%", name);
					return;
				}
				path = path.replace("{" + name + "}", encodeURIComponent(value.trim()));
				return;
			}

			if (value === "") { return; }

			if (where === "query" || method === "GET") {
				query.push(encodeURIComponent(name) + "=" + encodeURIComponent(value));
			} else if (field.dataset.type === "list") {
				// The router casts booleans and integers from their string
				// form, so those travel as typed; a list has to arrive as a
				// JSON array or validation rejects it, hence the parsing here.
				var list = parseList(value);
				if (list === null) {
					problem = T.listUnreadable.replace("%NAME%", name);
					return;
				}
				body[name] = list;
				hasBody = true;
			} else {
				body[name] = value;
				hasBody = true;
			}
		});

		if (problem !== null) {
			showProblem(panel, problem);
			return;
		}

		if (query.length) { path += "?" + query.join("&"); }

		button.disabled = true;
		output.classList.remove("d-none");
		output.textContent = T.sending;

		var started = performance.now();

		settings.getCredential().then(function (c) {

			if (!c) { throw new Error(T.noCredential); }

			// X-Requested-With keeps a 401 readable: without it the browser
			// answers the challenge header with its own login dialog.
			var options = { method: method, headers: {
				"Authorization": "Basic " + btoa(c.key + ":" + c.secret),
				"X-Requested-With": "XMLHttpRequest" } };

			if (method !== "GET" && hasBody) {
				options.headers["Content-Type"] = "application/json";
				options.body = JSON.stringify(body);
			}

			return fetch(settings.base + path, options).then(function (response) {
				return response.text().then(function (text) {
					var pretty = text;
					try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
					if (response.status === 204 || pretty === "") { pretty = T.noContent; }

					var lines = [method + " " + path,
						response.status + " · " + Math.round(performance.now() - started) + " " + T.ms];

					// The rate limit counters and the wait the API asks for
					// are the headers an integrator has to read, so they are
					// printed where they can be seen.
					var limit = response.headers.get("X-RateLimit-Limit");
					var remaining = response.headers.get("X-RateLimit-Remaining");
					var reset = response.headers.get("X-RateLimit-Reset");
					var retry = response.headers.get("Retry-After");
					if (limit !== null || remaining !== null) {
						lines.push(T.rateLimit + ": " + (remaining === null ? "?" : remaining) + "/" + (limit === null ? "?" : limit) +
							(reset !== null ? " · " + new Date(parseInt(reset, 10) * 1000).toISOString() : ""));
					}
					if (retry !== null) { lines.push(T.retry.replace("%VALUE%", retry)); }
					var requestId = response.headers.get("X-Request-Id");
					if (requestId !== null) { lines.push("X-Request-Id: " + requestId); }

					output.textContent = lines.join("\\n") + "\\n\\n" + pretty;
				});
			});

		}).catch(function (error) {
			output.textContent = String(error && error.message ? error.message : error);
		}).then(function () {
			button.disabled = false;
		});

	}

	// Wires every Try button under root (the document when omitted). Safe to
	// call again after new blocks were inserted: a button is bound once.
	function init(options, root) {
		settings = options;
		(root || document).querySelectorAll(".doc-try-btn").forEach(function (button) {
			if (button.dataset.bound) { return; }
			button.dataset.bound = "1";
			button.addEventListener("click", function () { toggle(button.closest(".doc-ep")); });
		});
	}

	// Closes and empties every Try panel - for when the credential is gone.
	function reset(root) {
		(root || document).querySelectorAll(".doc-try-panel").forEach(function (panel) {
			panel.classList.add("d-none");
			panel.innerHTML = "";
		});
	}

	return { init: init, reset: reset, parseList: parseList };

})();
';

}
