<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The markup of Application Access's edit drawer: its head (the
 * application's icon, name, kind and who it acts as), the tabs, the Overview
 * and Connect tabs, and the connection guides of includes/api/app_guides.php
 * drawn as folded rows of numbered steps, here and in step 3 of New
 * application's sheet. One drawer serves every application; the screen's
 * script fills it from the row that was clicked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(__FILE__) . '/../settings/fold.php');

// A Copy button for the text of a <code>, with its status beside it. Every
// button says only "Copy", so it is named by itself and the caption of what
// it copies ("Copy Key"). The status sits in the same box: the drawer is a
// modal dialog, so a region outside it would never be heard.
function api_settings_copy_button($id, $caption_id, $button_class) {

	return '<button type="button" class="' . $button_class . ' api-copy" id="' . $id . '_copy" data-copy="#' . $id . '" aria-labelledby="' . $id . '_copy ' . $caption_id . '"><i class="bi bi-clipboard" aria-hidden="true"></i>' . lang('Copy') . '</button>'
		. '<span class="visually-hidden" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span>';

}

// A value with its Copy button. A masked value (a secret, a token) shows dots
// and an eye button that shows it; Copy copies the value either way.
function api_app_value_box($id, $value, $label_id, $masked = false) {

	$copy = api_settings_copy_button($id, $label_id, 'pg-button pg-button-quiet pg-button-sm');

	if (!$masked) {
		return '<div class="pg-start-copy"><code id="' . $id . '">' . h($value) . '</code>' . $copy . '</div>';
	}

	return '<div class="pg-start-copy"><code id="' . $id . '" hidden>' . h($value) . '</code><code aria-hidden="true" data-mask-of="' . $id . '">'
		. str_repeat('&bull;', 24) . '</code>'
		. '<button type="button" class="pg-icon-button pg-icon-button-sm api-reveal" data-reveal="' . $id . '" aria-pressed="false" aria-label="' . h(lang('Show')) . '"><i class="bi bi-eye" aria-hidden="true"></i></button>'
		. $copy . '</div>';

}

// Who a connection acts as, in the words every guide and head uses.
function api_app_acts_text($acts) {

	return ($acts === 'person') ? lang('the person who signs in') : lang('the application itself');

}

// A guide's numbered steps (Bootstrap's stepper, .pg-stepper-cards), the MCP
// clients' own steps and the notes. $prefix keeps the ids of the drawer and
// the sheet apart; $skip leaves out steps by value id (the sheet's step 3
// gives the credentials in its own first step).
function api_app_guide_body_html($guide, $prefix, $skip = array()) {

	$steps = '';

	foreach ($guide['steps'] as $step) {

		if (($step['value_id'] !== '') && in_array($step['value_id'], $skip, true)) {
			continue;
		}

		$id = $prefix . '_' . $step['value_id'];

		$steps .= '
				<li class="stepper-item"><div>
					<div class="pg-list-row-title"' . (($step['value_id'] !== '') ? ' id="' . $id . '_label"' : '') . '>' . h($step['title']) . '</div>
					<div class="pg-text-meta">' . h($step['text']) . '</div>'
					. (($step['value'] !== '') ? '<div class="mt-2' . ((strpos($step['value'], "\n") !== false) ? ' api-snippet' : '') . '">' . api_app_value_box($id, $step['value'], $id . '_label') . '</div>' : '')
					. (($step['link'] !== '') ? '<div class="mt-2"><a class="pg-button pg-button-quiet pg-button-sm" href="' . h($step['link']) . '"'
						. ((strpos($step['link'], 'http') === 0) ? ' target="_blank" rel="noopener"' : '') . '><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' . h($step['link_label']) . '</a></div>' : '') . '
				</div></li>';

	}

	$clients = '';

	foreach ($guide['clients'] as $key => $client) {

		$id = $prefix . '_c_' . $key;

		$clients .= '
				<details class="api-client">
					<summary id="' . $id . '_label">' . h($client['name']) . '</summary>
					<p class="mb-1">' . h($client['steps']) . '</p>'
					. (($client['snippet'] !== '') ? '<div class="api-snippet mb-1">' . api_app_value_box($id, $client['snippet'], $id . '_label') . '</div>' : '') . '
				</details>';

	}

	$notes = '';

	foreach ($guide['notes'] as $note) {
		$notes .= '<li>' . h($note) . '</li>';
	}

	return ((isset($guide['intro']) && ($guide['intro'] !== '')) ? '
			<p class="pg-text-meta mb-2">' . h($guide['intro']) . '</p>' : '') . '
			<p class="pg-text-2 mb-3"><i class="bi ' . (($guide['acts'] === 'person') ? 'bi-person' : 'bi-box') . ' me-1" aria-hidden="true"></i>'
				. lang('Acts as:') . ' <b>' . h(api_app_acts_text($guide['acts'])) . '</b></p>
			<p class="alert theme-warning py-2 px-4 small mb-3" data-guide-off hidden></p>
			<ol class="stepper pg-stepper-cards mb-0">' . $steps . '
			</ol>'
			. (($clients !== '') ? '
			<div class="api-guide mt-3 mb-0">
				<div class="api-guide-title"><i class="bi bi-window-stack me-1" aria-hidden="true"></i>' . lang('Steps for each application') . '</div>' . $clients . '
			</div>' : '')
			. (($notes !== '') ? '<ul class="pg-text-meta mt-3 mb-0 ps-4">' . $notes . '</ul>' : '');

}

// The guides as folded rows, one open at a time: icon tile, title, one line,
// a pill naming who it acts as, a chevron. $open is the id of the row drawn
// open ('' for none).
function api_app_guides_fold_html($guides, $prefix, $open, $skip = array()) {

	$html = '';

	foreach ($guides as $guide) {

		$html .= pg_settings_fold_row(array(
			'key'  => $prefix . '-' . str_replace('_', '-', $guide['id']),
			'name' => $guide['title'],
			'icon' => $guide['icon'],
			'tone' => $guide['tone'],
			'line' => $guide['line'],
			'pill' => ($guide['acts'] === 'person')
				? array(lang('Person who signs in'), 'pg-tone-violet')
				: array(lang('Application'), 'pg-tone-teal'),
			'html' => api_app_guide_body_html($guide, $prefix, $skip)
		), $guide['id'] === $open);

	}

	return $html;

}

// The drawer. $parts:
//   guides          api_app_guides()
//   permission_rows the permission picker for the drawer
//   hook_secret     the Events tab's one-time signing secret ('' for none)
//   hook_bounds     api_webhook_store()'s limits (url, events)
//   fields_script   the date and chips fields' script
//   agent_tab       the AI agent tab's button and agent_pane its pane
//                   (app_agent_view.php), '' or missing when not offered
function api_app_drawer_view($parts) {

	$guide_meta = array();

	foreach ($parts['guides'] as $guide) {
		$guide_meta['dg-' . str_replace('_', '-', $guide['id'])] = array('kinds' => $guide['kinds'], 'secret' => !empty($guide['secret']));
	}

	$hook_bounds = $parts['hook_bounds'];

	return '
<!-- The drawer. One of them, filled from the row that was clicked: a drawer per
     application would put every application\'s settings into the page at once. -->
<dialog class="drawer drawer-end" id="api_drawer" aria-labelledby="d_title" style="width:min(760px,100%)">
	<form method="post" action="api_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100">
		<!-- Return in a text field presses the first submit button of the form.
		     This one stands first and has no name, so Return posts the hidden
		     api_action=update and saves; it never issues a new secret or deletes
		     the application. A hidden button would still be the default one. -->
		<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
		' . get_token_field() . '
		<input type="hidden" name="api_action" value="update" id="d_action">
		<input type="hidden" name="webhook_id" value="" id="d_webhook_id">
		<input type="hidden" name="sample_event" value="" id="d_sample_event">
		<input type="hidden" name="device_id" value="" id="d_device_id">
		<input type="hidden" name="app_id" id="d_app_id" value="">

		<div class="drawer-header pb-1 d-block">
			<div class="d-flex align-items-start gap-3">
				<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" id="d_tile" aria-hidden="true"><i class="bi bi-key" id="d_tile_icon"></i></span>
				<div class="flex-grow-1 min-w-0">
					<h5 class="drawer-title mb-0" id="d_title"></h5>
					<p class="small fg-2 mb-0 mt-1" id="d_sub"></p>
				</div>
				<span class="pg-pill pg-tone-teal" id="d_state"></span>
				<button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
			</div>
		</div>

		<div class="px-5 pt-3"><div class="pg-seg pg-list-seg" role="tablist">
			<button class="pg-seg-item active" role="tab" data-bs-toggle="tab" data-bs-target="#d_general" type="button" id="d_general_label" aria-controls="d_general" aria-selected="true">' . lang('Overview') . '</button>
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_connect" type="button" id="d_connect_label" aria-controls="d_connect" aria-selected="false">' . lang('Connect') . '</button>
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_perms" type="button" id="d_perms_label" aria-controls="d_perms" aria-selected="false">' . lang('Permissions') . ' <span class="pg-seg-count" id="d_perms_count"></span></button>
			' . (isset($parts['agent_tab']) ? $parts['agent_tab'] : '') . '
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_hooks" type="button" id="d_hooks_label" aria-controls="d_hooks" aria-selected="false">' . lang('Events') . '</button>
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_devices" type="button" id="d_devices_label" aria-controls="d_devices" aria-selected="false"
				data-device="' . h(lang('Devices')) . '" data-oauth="' . h(lang('Connections')) . '">' . lang('Devices') . '</button>
			<button class="pg-seg-item" role="tab" data-bs-toggle="tab" data-bs-target="#d_log" type="button" id="d_log_label" aria-controls="d_log" aria-selected="false">' . lang('Logs') . '</button>
		</div></div>

		<div class="drawer-body tab-content flex-grow-1 overflow-auto">

			' . api_app_drawer_overview_html($parts['fields_script']) . '

			<div class="tab-pane fade" id="d_connect" role="tabpanel" aria-labelledby="d_connect_label">
				<div class="pg-text-meta mb-2">' . lang('How will you connect?') . '</div>
				<div class="pg-set-fold" data-pg-folds id="d_guides" data-guides="' . h(json_encode($guide_meta)) . '"
					data-off-kind="' . h(lang('This guide is for another kind of application, so it does not work with this one.')) . '"
					data-off-secret="' . h(lang('This application was made without a secret, so it cannot connect this way.')) . '">'
					. api_app_guides_fold_html($parts['guides'], 'dg', '') . '
				</div>
				<p class="pg-text-meta mt-3 mb-0">' . lang('Guides that do not suit this application\'s kind are faded and say why.') . '</p>
			</div>

			<div class="tab-pane fade" id="d_perms" role="tabpanel" aria-labelledby="d_perms_label">
				' . $parts['permission_rows'] . '
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-3">
					<i class="bi bi-info-circle me-1" aria-hidden="true"></i>
					<b>' . lang('Write covers read.') . '</b> '
					. lang('Choosing Write holds the read position open; the record keeps only what you chose.') . '
				</div>
				<div class="alert theme-secondary py-3 px-5 small mb-0">
					<i class="bi bi-shield-check me-1" aria-hidden="true"></i>'
					. lang('An application can never do more than the account that owns it. Narrowing the owner narrows the application immediately.') . '
				</div>
			</div>

			<div class="tab-pane fade" id="d_devices" role="tabpanel" aria-labelledby="d_devices_label">
				<div class="d-flex align-items-center mb-3">
					<span class="small opacity-75">' . lang('Who is signed in through this application') . '</span>
				</div>
				<div id="d_device_rows"></div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
					. lang('Signing a device out ends it at once; the person has to sign in again. A new password, or signing out everywhere, signs out all of that person\'s devices too.') . '
				</div>
			</div>

			' . api_app_drawer_hooks_html($parts['hook_secret'], $hook_bounds) . '

			' . (isset($parts['agent_pane']) ? $parts['agent_pane'] : '') . '

			<div class="tab-pane fade" id="d_log" role="tabpanel" aria-labelledby="d_log_label">
				<div class="d-flex align-items-center mb-3">
					<span class="small opacity-75">' . lang('Most recent requests') . '</span>
					<div class="form-field ms-auto">
						<div class="switch"><input class="" type="checkbox" id="d_log_errors"></div>
						<label class="small" for="d_log_errors">' . lang('Errors only') . '</label>
					</div>
				</div>
				<div id="d_log_rows"></div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
					. lang('403 means a permission the application does not hold was tried; 409 means the same operation was sent twice and was not applied again.') . '
				</div>
			</div>

		</div>

		<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">
			<a class="pg-text-meta fg-2 text-decoration-none" href="connections.php" id="d_foot_connections"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('For the services the site reaches: Connections') . '</a>
			<a class="pg-text-meta fg-2 text-decoration-none" href="api_docs.php" id="d_foot_docs" hidden><i class="bi bi-book me-1" aria-hidden="true"></i>' . lang('API Documentation') . '</a>
			<span class="flex-grow-1"></span>
			<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer" id="d_close" hidden>' . lang('Close') . '</button>
			<button type="submit" class="pg-button pg-button-fill" id="d_save">' . lang('Save') . '</button>
		</div>
	</form>
</dialog>';

}

// The Overview tab: the facts (the figure cards' compact form), the details, the switch and the key, a
// summary that leads to the other tabs with Advanced folded in it, and the
// danger rows at the foot.
function api_app_drawer_overview_html($fields_script) {

	$fact = function ($id, $label) {
		return '<div class="pg-card pg-figure"><div class="pg-figure-label">' . h($label) . '</div><b class="pg-figure-value" id="' . $id . '"></b></div>';
	};

	return '
			<div class="tab-pane fade show active" id="d_general" role="tabpanel" aria-labelledby="d_general_label">
				<div class="pg-figures pg-figures-sm mb-5">'
					. $fact('d_fact_state', lang('Status'))
					. $fact('d_fact_used', lang('Last used'))
					. $fact('d_fact_errors', lang('Errors in 24 hours'))
					. $fact('d_fact_owner', lang('Owner')) . '
				</div>

				<div class="alert theme-warning py-3 px-5 small mb-5 d-none" id="d_locked_note">
					<i class="bi bi-lock me-1" aria-hidden="true"></i>' . h(lang('This application belongs to an account above yours, so only that account or one above it can change it.')) . '
				</div>
				<div class="alert theme-warning py-3 px-5 small mb-5 d-none" id="d_unused_note">
					<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h(lang('Not in use: the oldest OAuth app applies. The site serves one OAuth app, so the scopes chosen here reach no connection; change them on the app in use, or delete this one.')) . '
				</div>

				<div class="pg-text-meta mb-2">' . lang('Details') . '</div>
				<div class="row g-5 mb-5">
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="d_name">' . lang('Application name') . '</label>
						<input type="text" class="form-control form-control-sm" name="name" id="d_name" maxlength="190">
					</div>
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="d_description">' . lang('Description') . ' <span class="fg-2 fw-normal">' . lang('optional') . '</span></label>
						<input type="text" class="form-control form-control-sm" name="description" id="d_description" maxlength="500">
					</div>
				</div>

				<div class="pg-card-inner mb-5">
					<div class="pg-card-inner-row">
						<div class="flex-grow-1 min-w-0">
							<label class="pg-list-row-title d-block" for="d_status">' . lang('Active') . '</label>
							<div class="pg-text-meta">' . lang('Turning it off refuses the key at once; the settings stay.') . '</div>
						</div>
						<input type="checkbox" class="pg-switch" name="status" value="active" id="d_status">
					</div>
					<div class="pg-card-inner-row" id="d_key_row">
						<div class="flex-grow-1 min-w-0">
							<div class="pg-list-row-title" id="d_key_label">' . lang('Key') . '</div>
							<code class="pg-text-meta text-break" id="d_key"></code>
						</div>
						' . api_settings_copy_button('d_key', 'd_key_label', 'pg-button pg-button-quiet pg-button-sm') . '
					</div>
					<div class="pg-card-inner-row d-none" id="d_nosecret_block">
						<div class="pg-text-meta">' . h(lang('This application was made without a secret and is never given one, so it cannot sign in.')) . '</div>
					</div>
				</div>

				<div class="pg-text-meta mb-2">' . lang('Summary') . '</div>
				<div class="pg-card-inner mb-5">
					<div class="pg-card-inner-row">
						<span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
						<div class="flex-grow-1 min-w-0">
							<div class="pg-list-row-title">' . lang('Permissions') . '</div>
							<div class="pg-text-meta" id="d_sum_perms" data-text="' . h(lang(array('string' => '{var:1} of {var:2} chosen', 'vars' => array('{1}', '{2}')))) . '"></div>
						</div>
						<button type="button" class="pg-button pg-button-ghost pg-button-sm" data-go-tab="#d_perms">' . lang('Go to permissions') . '<i class="bi bi-chevron-right" aria-hidden="true"></i></button>
					</div>
					' . pg_settings_fold_row(array(
						'key'  => 'd-advanced',
						'name' => lang('Advanced'),
						'icon' => 'bi-sliders',
						'tone' => 'pg-tone-gray',
						'line' => lang('IP list, end date and rate limit'),
						'pill' => null,
						'html' => api_app_drawer_advanced_html($fields_script)
					), false) . '
				</div>

				<div class="pg-text-meta fg-danger mb-2" id="d_danger_label">' . lang('Danger') . '</div>
				<div class="pg-card-inner" id="d_danger_zone">
					<div class="pg-card-inner-row" id="d_secret_block">
						<div class="flex-grow-1 min-w-0">
							<div class="pg-list-row-title">' . lang('New secret') . '</div>
							<div class="pg-text-meta"><code id="d_hint" class="fg-2"></code> &middot; ' . lang('The old secret works one more day, then closes.') . '</div>
						</div>
						<button type="submit" class="pg-button pg-button-quiet pg-button-sm" name="api_action" value="rotate" id="d_rotate">
							<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>' . lang('Issue a new one') . '</button>
					</div>
					<div class="pg-card-inner-row">
						<div class="flex-grow-1 min-w-0">
							<div class="pg-list-row-title">' . lang('Delete the application') . '</div>
							<div class="pg-text-meta">' . lang('Any integration using this key stops working immediately. This cannot be undone.') . '</div>
						</div>
						<button type="button" class="pg-button pg-button-danger pg-button-sm" id="d_delete_ask">
							<i class="bi bi-trash" aria-hidden="true"></i>' . lang('Delete') . '</button>
						<span id="d_delete_confirm" class="d-none text-nowrap">
							<button type="button" class="pg-button pg-button-quiet pg-button-sm" id="d_delete_no">' . lang('Cancel') . '</button>
							<button type="submit" class="pg-button pg-button-danger pg-button-sm ms-2" name="api_action" value="delete">' . lang('Yes, delete') . '</button>
						</span>
					</div>
				</div>
			</div>';

}

// Advanced, folded in the summary: the IP list, the end date, the rate limit.
function api_app_drawer_advanced_html($fields_script) {

	return '
						<div class="form-field mb-5">
							<label class="form-label pg-field-label" for="d_ip">' . lang('Allowed addresses') . '</label>
							<input type="text" class="form-control tagin min-height-tagin" name="ip_allowlist" id="d_ip" data-pg-chips
								data-placeholder="' . lang('Add an address') . '" value="">' . $fields_script . '
							<div class="form-text">'
								. lang('Leave empty to let this application connect from anywhere. Once an address is added, requests from anywhere else are refused.')
								. '<br>' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . '</div>
						</div>
						<div class="row g-5">
							<div class="col-12 sm:col-6 form-field">
								<label class="form-label pg-field-label" for="d_expires">' . lang('Expires on') . '</label>
								<input type="date" data-pg-date class="form-control form-control-sm" name="expires_at" id="d_expires">
								<div class="form-text">' . lang('After this date the key stops working. Leave empty for a key that does not expire.') . '</div>
							</div>
							<div class="col-12 sm:col-6 form-field">
								<label class="form-label pg-field-label" for="d_rate">' . lang('Requests per minute') . '</label>
								<input type="number" class="form-control form-control-sm" name="rate_limit_per_min" id="d_rate" min="1" max="6000">
								<div class="form-text">' . lang('When an application asks for more than this in one minute, the rest of that minute is refused and it is told how long to wait.') . '</div>
							</div>
						</div>';

}

// The Events tab, moved here unchanged: the subscriptions, New subscription
// and its one-time signing secret.
function api_app_drawer_hooks_html($hook_secret_html, $hook_bounds) {

	return '
			<div class="tab-pane fade" id="d_hooks" role="tabpanel" aria-labelledby="d_hooks_label">
				<div class="d-flex align-items-center flex-wrap gap-3 mb-3">
					<span class="small opacity-75">' . lang('Addresses this application asked to be told at') . ' &middot; <span class="text-nowrap" id="d_hook_count"></span></span>
					<button type="button" class="pg-button pg-button-quiet pg-button-sm ms-auto api-hook-new" id="d_hook_new" aria-expanded="false" aria-controls="d_hook_form">
						<i class="bi bi-plus-lg" aria-hidden="true"></i>' . lang('New subscription') . '</button>
				</div>
				<div id="d_hook_secret_slot">' . $hook_secret_html . '</div>
				<div class="api-hook-form mb-5 d-none" id="d_hook_form" role="group" aria-labelledby="d_hook_form_title">
					<div class="d-flex align-items-center mb-3">
						<b class="small" id="d_hook_form_title">' . lang('New subscription') . '</b>
						<button type="button" class="pg-icon-button pg-icon-button-sm ms-auto" id="d_hook_form_close" aria-label="' . h(lang('Close')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
					</div>
					<div class="alert theme-danger py-3 px-5 small mb-3 d-none" id="d_hook_form_error" role="alert"></div>
					<div class="alert theme-warning py-3 px-5 small mb-3 d-none" id="d_hook_unsaved" role="alert">
						<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . lang('The subscription is not created yet: create it or close the form.') . '
					</div>
					<div class="mb-5">
						<label class="pg-field-label" for="d_hook_url">' . lang('Address') . '</label>
						<textarea class="pg-input api-hook-address" name="hook_url" id="d_hook_url" rows="3" maxlength="' . (int)$hook_bounds['url'] . '"
							spellcheck="false" autocomplete="off" aria-describedby="d_hook_url_help" placeholder="https://"></textarea>
						<div class="pg-field-help" id="d_hook_url_help">' . lang('The address the platform gives for incoming webhooks. It is checked when it is saved: an https address that resolves and lies outside this network.') . '</div>
					</div>
					<div class="mb-3">
						<label class="pg-field-label" for="d_hook_search">' . lang('Webhook events') . '</label>
						<span class="pg-input">
							<input type="search" id="d_hook_search" autocomplete="off" aria-controls="d_hook_events"
								placeholder="' . h(lang('Search events')) . '">
							<span class="pg-input-end"><i class="bi bi-search" aria-hidden="true"></i></span>
						</span>
					</div>
					<div class="api-event-list" id="d_hook_events" role="group" aria-label="' . h(lang('Webhook events')) . '"></div>
					<div class="d-flex flex-wrap gap-3 small fg-2 mt-2">
						<span id="d_hook_found" aria-live="polite"
							data-all="' . h(lang(array('string' => '{var:1} events in the catalogue', 'vars' => '{1}'))) . '"
							data-some="' . h(lang(array('string' => '{var:1} events for "{var:2}" · {var:3} in the catalogue', 'vars' => array('{1}', '{2}', '{3}')))) . '"></span>
						<span class="ms-auto" id="d_hook_chosen"
							data-text="' . h(lang(array('string' => '{var:1} chosen · at most {var:2}', 'vars' => array('{1}', (int)$hook_bounds['events'])))) . '"></span>
					</div>
					<div class="api-hook-events mb-3" id="d_hook_chips"></div>
					<div class="alert theme-primary py-3 px-5 small mb-3">
						<i class="bi bi-key me-1" aria-hidden="true"></i>' . lang('Once it is saved the subscription\'s signing secret is shown once. Paste it into the platform if it checks the signature.') . '
					</div>
					<div class="d-flex gap-3 justify-content-end">
						<button type="button" class="pg-button pg-button-ghost pg-button-sm" id="d_hook_form_cancel">' . lang('Cancel') . '</button>
						<button type="button" class="pg-button pg-button-fill pg-button-sm" id="d_hook_create">' . lang('Create the subscription') . '</button>
					</div>
				</div>
				<div id="d_hook_rows"></div>
				<div class="api-guide mt-5 mb-0">
					<div class="api-guide-title"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>' . lang('Automation platforms') . '</div>
					<ul class="mb-0">
						<li>' . lang('With the platform\'s own app: the app connects with the site\'s address, the key and the secret, and opens and removes its subscriptions itself.') . '</li>
						<li>' . lang('Without an app: paste the address the platform gives for incoming webhooks into New subscription and choose the events it should hear.') . '</li>
						<li>' . lang('Here you can stop a subscription, send what is waiting again, send a sample or remove it.') . '</li>
					</ul>
				</div>
			</div>';

}

// Step 3 of New application's sheet, once the application is made: what was
// made, the one-time values as the first numbered step, the steps of the
// starting point's guide, and the other guides that suit it folded below.
// $made: name, key, secret, kind, rotated, token. $guide: the starting
// point's guide; $others: the other guides that suit the kind.
function api_app_new_connect_html($made, $site_address, $guide, $others) {

	$has_secret = ($made['secret'] !== '');

	$rows = '';

	$row = function ($id, $label, $hint, $value, $masked) {
		return '
					<div class="pg-card-inner-row api-cred-row">
						<div class="api-cred-name"><div class="pg-list-row-title" id="' . $id . '_label">' . h($label) . '</div><div class="pg-text-meta">' . h($hint) . '</div></div>
						<div class="flex-grow-1 min-w-0">' . api_app_value_box($id, $value, $id . '_label', $masked) . '</div>
					</div>';
	};

	if ($made['kind'] === 'server') {
		$rows .= $row('nc_site', lang('Site address'), lang('The API is found here'), $site_address, false);
	}

	$rows .= $row('nc_key', ($made['kind'] === 'device') ? 'client_key' : lang('Key'), ($guide['id'] === 'agent') ? 'PINEGRAP_KEY' : lang('Not a secret'), $made['key'], false);

	if ($has_secret) {
		$rows .= $row('nc_secret', lang('Secret'), ($guide['id'] === 'agent') ? 'PINEGRAP_SECRET' : 'HTTP Basic', $made['secret'], true);
	}

	if ($made['token'] !== '') {
		$rows .= $row('nc_token', lang('One token'), 'Authorization: Bearer', $made['token'], true);
	}

	$first = ($made['kind'] === 'oauth') ? '' : '
				<li class="stepper-item"><div>
					<div class="pg-list-row-title">' . ($has_secret ? lang('Copy the key and the secret where the client reads them') : lang('Give the client the key')) . '</div>
					<div class="pg-text-meta">' . ($has_secret
						? lang('A client that asks for a single token takes the token instead.')
						: lang('The key is not a secret: it stays readable on the application\'s Overview.')) . '</div>
					<div class="pg-card-inner mt-2">' . $rows . '
					</div>
				</div></li>';

	// The guide's own steps follow the first; a step that only repeats a
	// value the first step gives is left out.
	$body = api_app_guide_body_html($guide, 'ng', array('site'));

	$body = preg_replace('~<ol class="stepper pg-stepper-cards mb-0">~', '<ol class="stepper pg-stepper-cards mb-0">' . $first, $body, 1);

	$other_rows = api_app_guides_fold_html($others, 'no', '');

	$other_names = array();

	foreach ($others as $other) {
		$other_names[] = $other['title'];
	}

	return '
			<div id="n_step_3" data-step="3">
				<div class="alert theme-success py-3 px-5 small mb-3" role="status">
					<i class="bi bi-check-circle me-1" aria-hidden="true"></i><b>' . (!empty($made['rotated']) ? lang('A new secret was issued') : lang('The application was created')) . '</b>
				</div>'
				. ($has_secret ? '
				<div class="alert theme-warning py-3 px-5 small mb-5">
					<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . lang('The secret and the token are shown only now. If they are lost, issue a new one.') . '
				</div>' : '')
				. (!empty($made['automation']) ? '
				<div class="alert theme-primary py-3 px-5 small mb-5">
					<i class="bi bi-lightning me-1" aria-hidden="true"></i>' . lang('An automation platform asks for these three values when it connects: the site\'s address, the key and the secret. Copy them in order into the platform\'s connect window.') . '
				</div>' : '') . '
				' . $body
				. (($other_rows !== '') ? '
				<details class="pg-start-advanced mt-5" id="n_other_guides">
					<summary class="small"><i class="bi bi-signpost-2 me-1" aria-hidden="true"></i><b>' . lang('Connecting another way?') . '</b> <span class="small fg-2">' . h(implode(', ', $other_names)) . '</span></summary>
					<div class="pg-set-fold mt-3" data-pg-folds>' . $other_rows . '
					</div>
				</details>' : '') . '
			</div>';

}

// Step 3 for the sheet from what Create or a new secret handed over through
// the session, or null when nothing was: the starting point's guide (or the
// kind's), the other guides that suit the application, the head's words.
function api_app_connect_step($made, $site_address, $guides, $cards) {

	if (!is_array($made)) {
		return null;
	}

	$made = array_merge(array('name' => '', 'key' => '', 'secret' => '', 'kind' => 'server', 'token' => '', 'start' => '', 'rotated' => false, 'automation' => false), $made);

	$made['secret'] = (string)$made['secret'];

	$made['token'] = (string)$made['token'];

	$card = isset($cards[$made['start']]) ? $cards[$made['start']] : null;

	$kind_face = array(
		'server' => array(lang('Integration'), 'blue', 'bi-key', 'key_secret'),
		'oauth'  => array(lang('Connected apps (OAuth)'), 'violet', 'bi-stars', 'mcp_signin'),
		'device' => array(lang('Team devices'), 'rose', 'bi-phone', 'devices')
	);

	$face = isset($kind_face[$made['kind']]) ? $kind_face[$made['kind']] : $kind_face['server'];

	$guide = api_app_guide_for($guides, $card ? $card['guide'] : $face[3], $made['kind']);

	$others = array();

	foreach ($guides as $other) {

		if (($other['id'] !== $guide['id']) && (api_app_guide_fit($other, $made['kind'], $made['secret'] !== '') === '')) {
			$others[] = $other;
		}

	}

	return array(
		'title'   => $made['name'],
		'sub'     => h($card ? $card['title'] : $face[0]) . ' &middot; ' . lang('Acts as:') . ' <b>' . h(api_app_acts_text($guide['acts'])) . '</b>',
		'tone'    => $card ? $card['tone'] : $face[1],
		'icon'    => $card ? $card['icon'] : $face[2],
		'html'    => api_app_new_connect_html($made, $site_address, $guide, $others),
		'ack'     => ($made['secret'] !== ''),
		'rotated' => !empty($made['rotated'])
	);

}
