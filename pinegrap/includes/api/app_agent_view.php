<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Application Access's Agent tab and the "Agent in a channel" starting
 * point: an integration made an agent that answers as itself in Workspace
 * channels. The tab of the edit drawer shows the agent's fields (handle,
 * name, role, door), its start text and a searchable list of the channels
 * the editor may manage; the sheet's step 2 takes the same fields for a new
 * application. Both save through includes/workspace/agent_app_setup.php, so
 * the Agents card's and "Agents in this channel"'s rules apply. Drawn only
 * while the Workspace is on, for an administrator.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

// Whether the Agent tab and the starting point are drawn for this person:
// the Workspace and its agents are there, and the person is an
// administrator (an agent is added by role 0 alone).
function api_app_agent_ready($user) {

	// The module is loaded here, not by every panel page.
	if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
	}

	return function_exists('ws_agent_app_setup_refusal') && ws_enabled() && ws_agents_ready() && ((int)$user['role'] === 0);

}

// The application as ws_agent_app_setup_refusal() reads it, or null.
function api_app_agent_app($app_id, $user) {

	$row = db_item("SELECT api_apps.id, api_apps.kind, api_apps.api_secret_hash, user.user_role AS owner_role
		FROM api_apps
		LEFT JOIN user ON api_apps.owner_user_id = user.user_id
		WHERE api_apps.id = '" . (int)$app_id . "' AND api_apps.status <> 'revoked' LIMIT 1");

	if (!$row) {
		return null;
	}

	return array(
		'id'         => (int)$row['id'],
		'kind'       => api_app_kind($row['kind']),
		'has_secret' => ((string)$row['api_secret_hash'] !== ''),
		'locked'     => api_app_row_locked($row['owner_role'], $user['role'])
	);

}

// The fields of the agent: handle, name, role and the door as two described
// choices. $prefix keeps the ids of the drawer and the sheet apart.
function api_app_agent_fields_html($prefix, $roles) {

	$options = '<option value="">' . h(lang('No role')) . '</option>';

	foreach ($roles as $key => $label) {
		$options .= '<option value="' . h($key) . '">' . h($label) . '</option>';
	}

	$door = function ($value, $title, $text, $checked) use ($prefix) {
		$id = $prefix . '_door_' . $value;

		return '
					<div class="form-field form-field-card col-12 sm:col-6">
						<input class="radio" type="radio" name="agent_door_kind" value="' . $value . '" id="' . $id . '" aria-describedby="' . $id . '_help"' . ($checked ? ' checked' : '') . '>
						<label class="fw-bold" for="' . $id . '">' . h($title) . '</label>
						<div class="form-text mt-1" id="' . $id . '_help">' . h($text) . '</div>
					</div>';
	};

	return '
				<div class="row g-5 mb-5">
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="' . $prefix . '_handle">' . lang('Handle') . '</label>
						<input type="text" class="form-control form-control-sm" name="agent_handle" id="' . $prefix . '_handle" maxlength="32" autocomplete="off" spellcheck="false" placeholder="console" aria-describedby="' . $prefix . '_handle_help">
						<div class="form-text" id="' . $prefix . '_handle_help">' . lang('People call the agent with it in a channel.') . '</div>
					</div>
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="' . $prefix . '_name">' . lang('Agent name') . '</label>
						<input type="text" class="form-control form-control-sm" name="agent_name" id="' . $prefix . '_name" maxlength="100">
					</div>
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="' . $prefix . '_role">' . lang('Role') . '</label>
						<select class="pg-input pg-input-sm" name="agent_role" id="' . $prefix . '_role">' . $options . '</select>
					</div>
				</div>
				<div class="pg-field-label mb-2" id="' . $prefix . '_door_label">' . lang('Door') . '</div>
				<div class="row g-3 mb-3" role="radiogroup" aria-labelledby="' . $prefix . '_door_label">'
					. $door('poll', lang('Poll door'), lang('The agent asks the site for work itself. Nothing on its side has to be reachable.'), true)
					. $door('push', lang('Push door'), lang('The site knocks on an address of the agent\'s when there is work.'), false) . '
				</div>
				<div class="form-field mb-5" data-agent-push hidden>
					<label class="form-label pg-field-label" for="' . $prefix . '_door_url">' . lang('Push address') . '</label>
					<input type="url" class="form-control form-control-sm" name="agent_door_url" id="' . $prefix . '_door_url" maxlength="500" placeholder="https://">
					<div class="form-text">' . lang('Its signing token is set on the agent in Site Settings › Assistants and agents.') . '</div>
				</div>';

}

// The searchable list of channels: a tick adds the agent's row with the
// default wake rule (its @handle), an untick takes it out; each row links
// to the channel for the rest of its settings.
function api_app_agent_channels_html($prefix, $channels) {

	if (!$channels) {
		return '<p class="pg-text-meta mb-0">' . lang('There is no channel you manage yet.') . '</p>';
	}

	$rows = '';

	foreach ($channels as $channel) {

		$id = $prefix . '_ch_' . (int)$channel['id'];

		$rows .= '
					<div class="pg-card-inner-row" data-channel-name="' . h(mb_strtolower($channel['name'])) . '">
						<input class="pg-checkbox" type="checkbox" name="agent_channels[]" value="' . (int)$channel['id'] . '" id="' . $id . '">
						<label class="flex-grow-1 min-w-0 pg-list-row-title" for="' . $id . '"><i class="bi ' . (($channel['kind'] === 'private') ? 'bi-lock' : 'bi-hash') . ' me-1" aria-hidden="true"></i>' . h($channel['name']) . '</label>
						<a class="pg-button pg-button-ghost pg-button-sm" href="workspace.php?channel=' . (int)$channel['id'] . '" target="_blank" rel="noopener" aria-label="' . h(lang(array('string' => 'Open #{var:1}', 'vars' => $channel['name']))) . '">' . lang('Open the channel') . '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
					</div>';

	}

	return '
				<input type="hidden" name="agent_channels_sent" value="1">
				<span class="pg-input mb-3">
					<input type="search" id="' . $prefix . '_ch_search" autocomplete="off" data-agent-search placeholder="' . h(lang('Search channels')) . '" aria-label="' . h(lang('Search channels')) . '">
					<span class="pg-input-end"><i class="bi bi-search" aria-hidden="true"></i></span>
				</span>
				<div class="pg-card-inner api-agent-channels" role="group" aria-label="' . h(lang('Channels')) . '">' . $rows . '
				</div>
				<p class="pg-text-meta mt-2 mb-0">' . lang('The agent wakes when its @handle is called. Open a channel to change when it wakes and what it may do by itself.') . '</p>';

}

// The tab's button for the drawer's tab row.
function api_app_agent_tab_button() {

	return '
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_agent" type="button" id="d_agent_label" aria-controls="d_agent" aria-selected="false">' . lang('AI agent') . '</button>';

}

// The Agent tab. $start_text is ws_agent_start_text() for this site.
function api_app_agent_tab_html($roles, $channels, $start_text) {

	return '
			<div class="tab-pane fade" id="d_agent" role="tabpanel" aria-labelledby="d_agent_label">
				<div class="alert theme-warning py-3 px-5 small mb-5" id="da_refused" hidden></div>
				<div id="da_body">
					<div class="pg-card-inner mb-5">
						<div class="pg-card-inner-row">
							<span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-robot"></i></span>
							<div class="flex-grow-1 min-w-0">
								<div class="pg-list-row-title" id="da_title" data-new="' . h(lang('Make this application an agent')) . '"></div>
								<div class="pg-text-meta" id="da_line" data-new="' . h(lang('An agent answers as itself in Workspace channels when its @handle is called. It signs in with this application\'s key and secret.')) . '"></div>
							</div>
							<span class="pg-pill" id="da_state" hidden></span>
						</div>
					</div>

					<div class="pg-text-meta mb-2">' . lang('The agent') . '</div>
					' . api_app_agent_fields_html('da', $roles) . '

					<div id="da_start_block" hidden>
						<div class="pg-text-meta mb-2" id="da_start_label">' . lang('Start text') . '</div>
						<div class="api-snippet mb-2">' . api_app_value_box('da_start', $start_text, 'da_start_label') . '</div>
						<p class="pg-text-meta mb-5">' . lang('Give it to the AI session with PINEGRAP_KEY and PINEGRAP_SECRET; it reads its instructions from the site.') . '</p>
					</div>

					<div class="pg-text-meta mb-2">' . lang('Channels') . '</div>
					' . api_app_agent_channels_html('da', $channels) . '

					<div class="d-flex justify-content-end mt-5">
						<button type="submit" class="pg-button pg-button-fill" name="api_action" value="agent_setup" id="da_save"
							data-new="' . h(lang('Make it an agent')) . '" data-saved="' . h(lang('Save the agent')) . '">' . lang('Save the agent') . '</button>
					</div>
				</div>
			</div>';

}

// The sheet's step 2 for "Agent in a channel": the agent's fields and the
// channels, shown by the script only while that card is picked.
function api_app_agent_sheet_html($roles, $channels) {

	return '
				<fieldset class="mb-5" id="n_agent" hidden disabled>
					<legend class="form-label pg-field-label"><i class="bi bi-robot me-1" aria-hidden="true"></i>' . lang('The agent') . '</legend>
					' . api_app_agent_fields_html('na', $roles) . '
					<div class="pg-field-label mb-2">' . lang('Channels') . '</div>
					' . api_app_agent_channels_html('na', $channels) . '
				</fieldset>';

}

// What the tab shows for each integration, by application id: the agent
// (id, handle, name, role, door, its state's words and tone) or null, its
// channels, and the refusal's sentence ('' when it may be saved).
function api_app_agent_tab_data($app_data, $user) {

	$out = array();

	$agents = array();

	foreach ($app_data as $id => $app) {

		if ($app['kind'] !== 'server') {
			continue;
		}

		$agent = ws_agent_app_setup_agent($id);

		if ($agent) {
			$agents[$id] = $agent;
		}

	}

	$counts = ($agents && function_exists('ws_agent_request_counts')) ? ws_agent_request_counts(array_values($agents), time()) : array();

	foreach ($app_data as $id => $app) {

		if ($app['kind'] !== 'server') {
			continue;
		}

		$refusal = ws_agent_app_setup_refusal(true, (int)$user['role'], $app);

		$agent = isset($agents[$id]) ? $agents[$id] : null;

		$state = $agent ? ws_agents_settings_state($agent, $counts, time()) : null;

		$out[$id] = array(
			'agent'    => $agent ? array(
				'id'        => $agent['id'],
				'handle'    => $agent['handle'],
				'name'      => $agent['name'],
				'role'      => $agent['role'],
				'door_kind' => $agent['door_kind'],
				'door_url'  => $agent['door_url'],
				'words'     => $state['words'],
				'tone'      => $state['tone']
			) : null,
			'channels' => $agent ? ws_agent_app_setup_agent_channels($agent['id']) : array(),
			'refusal'  => ($refusal === '') ? '' : ws_agent_app_setup_refusal_text($refusal)
		);

	}

	return $out;

}

// The Agent tab's POST branch: checks, writes and leaves its sentences on
// the screen; the drawer opens again on the tab.
function api_app_agent_setup_post($user, $app_id, $post, $liveform) {

	$_SESSION['software']['api_agent_back'] = (int)$app_id;

	// Loads the module when it is on; the check names who may otherwise.
	if (!api_app_agent_ready($user) && !function_exists('ws_agent_app_setup_check')) {
		$liveform->add_error(lang('An agent answers in Workspace channels: switch the Workspace on first.'));
		return;
	}

	$viewer = ws_viewer($user);

	$posted = ws_agent_app_setup_posted($post);

	$ok = ws_agent_app_setup_check($viewer, api_app_agent_app($app_id, $user), $posted);

	if (!$ok['ok']) {
		$liveform->add_error(h($ok['error']));
		return;
	}

	$messages = ws_agent_app_setup_messages(ws_agent_app_setup_write($viewer, $ok), $ok['checked']['values']['handle']);

	foreach ($messages['notices'] as $notice) {
		$liveform->add_notice($notice);
	}

	foreach ($messages['errors'] as $error) {
		$liveform->add_error($error);
	}

}

// After Create on "Agent in a channel": the agent, then its channel rows,
// each by its own function. The application stays when a later part is
// refused, and the sentence says which part failed and where to finish it.
function api_app_agent_after_create($user, $made, $post, $liveform) {

	$viewer = ws_viewer($user);

	$posted = ws_agent_app_setup_posted($post);

	$ok = ws_agent_app_setup_check($viewer, array('id' => (int)$made['id'], 'kind' => $made['kind'], 'has_secret' => ($made['secret'] !== ''), 'locked' => false), $posted);

	if (!$ok['ok']) {
		$liveform->add_error(lang(array('string' => 'The application was created, but the agent was not: {var:1} Finish it on the application\'s AI agent tab.', 'vars' => h($ok['error']))));
		return;
	}

	$messages = ws_agent_app_setup_messages(ws_agent_app_setup_write($viewer, $ok), $ok['checked']['values']['handle']);

	foreach ($messages['errors'] as $error) {
		$liveform->add_error($error);
	}

}

// The tab's and the sheet's script: fills the tab from the application the
// drawer opened (the pg:api-app-open event), shows the push address with a
// push door, filters the channels, shows the sheet's agent fields while its
// card is picked, and opens the tab again after its save.
function api_app_agent_script($data, $back_id) {

	return '
<script>
(function () {
	var AGENTS = ' . json_encode((object)$data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';
	var BACK = ' . (int)$back_id . ';
	var pane = document.getElementById("d_agent");
	var tab = document.getElementById("d_agent_label");

	function pushShown(root) {
		var push = root.querySelector("input[name=agent_door_kind][value=push]");
		var box = root.querySelector("[data-agent-push]");
		if (push && box) { box.hidden = !push.checked; }
	}

	function setField(id, value) {
		var el = document.getElementById(id);
		if (el) { el.value = value || ""; }
	}

	document.addEventListener("pg:api-app-open", function (e) {
		var app = e.detail;
		var row = AGENTS[app.id];
		tab.hidden = !row;
		tab.disabled = !row;
		if (!row) { return; }
		var agent = row.agent;
		var refused = document.getElementById("da_refused");
		refused.textContent = row.refusal;
		refused.hidden = (row.refusal === "");
		document.getElementById("da_body").hidden = (row.refusal !== "") && !agent;
		document.getElementById("da_save").hidden = (row.refusal !== "");
		var title = document.getElementById("da_title");
		var line = document.getElementById("da_line");
		var state = document.getElementById("da_state");
		title.textContent = agent ? (agent.name + " @" + agent.handle) : title.dataset["new"];
		line.textContent = agent ? "" : line.dataset["new"];
		line.hidden = !!agent;
		state.hidden = !agent;
		if (agent) { state.className = "pg-pill " + agent.tone; state.textContent = agent.words; }
		setField("da_handle", agent ? agent.handle : "");
		setField("da_name", agent ? agent.name : app.name);
		setField("da_role", agent ? agent.role : "");
		setField("da_door_url", agent ? agent.door_url : "");
		var door = (agent && agent.door_kind === "push") ? "push" : "poll";
		pane.querySelectorAll("input[name=agent_door_kind]").forEach(function (r) { r.checked = (r.value === door); });
		pushShown(pane);
		document.getElementById("da_start_block").hidden = !agent;
		pane.querySelectorAll("input[name=\'agent_channels[]\']").forEach(function (box) {
			box.checked = row.channels.indexOf(parseInt(box.value, 10)) !== -1;
		});
		var save = document.getElementById("da_save");
		save.textContent = agent ? save.dataset.saved : save.dataset["new"];
	});

	document.addEventListener("change", function (e) {
		if (e.target.name === "agent_door_kind") { pushShown(e.target.closest("#d_agent, #n_agent")); }
		if (e.target.name === "start") {
			var sheet = document.getElementById("n_agent");
			if (sheet) {
				var on = (e.target.value === "agent");
				sheet.hidden = !on;
				sheet.disabled = !on;
				var name = document.getElementById("na_name");
				if (on && name && name.value === "") { name.value = document.getElementById("n_name").value; }
			}
		}
	});

	document.addEventListener("input", function (e) {
		if (!e.target.hasAttribute("data-agent-search")) { return; }
		var words = e.target.value.trim().toLowerCase();
		e.target.closest("#d_agent, #n_agent").querySelectorAll("[data-channel-name]").forEach(function (row) {
			row.hidden = (words !== "") && (row.dataset.channelName.indexOf(words) === -1);
		});
	});

	if (BACK && window.pgApiOpenApp) {
		window.addEventListener("load", function () {
			window.pgApiOpenApp(BACK);
			pgUI.tab.show(tab);
			document.getElementById("d_save").hidden = true;
			document.getElementById("d_close").hidden = false;
		});
	}
})();
</script>';

}
