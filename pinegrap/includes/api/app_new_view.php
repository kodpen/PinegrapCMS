<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The markup of New application's sheet in Application Access: a drawer in
 * three steps, 1 What connects (the starting points of api_app_templates()),
 * 2 Details and permissions, 3 Connect (the one-time sheet the page shows
 * once the application is made).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

// The step line on top of the sheet; $current is 1, 2 or 3. A finished step
// is green, the current one takes the primary colour, a later one is grey
// (ui/step-rail.md). The script moves the classes as the sheet moves on.
function api_app_new_steps_html($current) {

	$labels = array(1 => lang('What connects'), 2 => lang('Details and permissions'), 3 => lang('Connect'));

	$html = '<ol class="stepper stepper-horizontal mb-0" id="n_steps">';

	foreach ($labels as $number => $label) {

		$class = ($number < $current) ? ' active theme-success' : (($number === $current) ? ' active' : '');

		$html .= '<li class="stepper-item' . $class . '" data-step="' . $number . '"'
			. (($number === $current) ? ' aria-current="step"' : '') . '>' . h($label) . '</li>';

	}

	return $html . '</ol>';

}

// One starting point as a choice card: its icon tile, title, a badge or
// nothing, what it is for, who it acts as and whether a secret comes with
// it. A card that opens an application already there (the site's one OAuth
// application) carries its id.
function api_app_new_card_html($card, $checked) {

	$id = 'n_start_' . $card['id'];

	$secret = $card['secret']
		? '<i class="bi bi-key" aria-hidden="true"></i>' . lang('Key and secret')
		: '<i class="bi bi-shield-check" aria-hidden="true"></i>' . lang('No secret');

	return '
				<div class="form-field form-field-card api-start-card">
					<input class="radio" type="radio" name="start" value="' . h($card['id']) . '" id="' . $id . '"'
						. ' data-kind="' . h($card['kind']) . '" data-existing="' . (int)$card['existing'] . '"'
						. ' aria-describedby="' . $id . '_help ' . $id . '_acts"' . ($checked ? ' checked' : '') . '>
					<label class="api-start-head" for="' . $id . '">
						<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . h($card['tone']) . '" aria-hidden="true"><i class="bi ' . h($card['icon']) . '"></i></span>
						<span class="fw-bold">' . h($card['title']) . '</span>'
						. (($card['badge'] !== '') ? ' <span class="pg-pill pg-tone-teal">' . h($card['badge']) . '</span>' : '') . '
					</label>
					<div class="form-text mt-1" id="' . $id . '_help">' . h($card['text']) . '</div>
					<div class="api-start-acts" id="' . $id . '_acts">
						<div>' . lang('Acts as:') . ' <b>' . h($card['acts_as']) . '</b></div>
						<div class="fg-2">' . $secret . '</div>
					</div>
				</div>';

}

// The whole sheet. $cards is api_app_templates(), $permission_rows the
// permission rows (api_settings_permission_rows() with the 'scope' names),
// $start_note the line naming what the automation card ticks. $connect is
// step 3 on the request that follows Create or a new secret, null on the
// others: title, sub (the line under it), tone, icon, html
// (api_app_new_connect_html()), ack (a secret is shown, so Close waits for
// "I have copied it"), rotated (no step line: nothing was picked).
// $agent_html is the agent's fields for "Agent in a channel"
// (api_app_agent_sheet_html()), '' when that card is not offered.
function api_app_new_view($cards, $permission_rows, $start_note, $connect = null, $agent_html = '') {

	$card_html = '';

	$first = true;

	foreach ($cards as $card) {

		$card_html .= api_app_new_card_html($card, $first);

		$first = false;

	}

	$done = is_array($connect);

	$done_head = $done ? '
				<div class="flex-grow-1 min-width-0" id="n_head_done">
					<h5 class="drawer-title mb-0">' . h($connect['title']) . '</h5>
					<p class="small fg-2 mb-0 mt-1">' . $connect['sub'] . '</p>
				</div>' : '';

	$done_foot = $done ? '
			<span class="d-flex align-items-center gap-2 order-first me-auto" id="n_done_ack_field">' . ($connect['ack'] ? '
				<input class="pg-checkbox" type="checkbox" id="n_done_ack">
				<label class="small" for="n_done_ack">' . lang('I have copied the secret') . '</label>' : '') . '
			</span>
			<button type="button" class="pg-button pg-button-fill" id="n_done"' . ($connect['ack'] ? ' disabled' : '') . '>' . lang('Close') . '</button>' : '';

	return '
<!-- New application -->
<dialog class="drawer drawer-end" id="api_new" aria-labelledby="api_new_title" style="width:min(760px,100%)"'
	. ($done ? ' data-done="1" data-done-rotated="' . (empty($connect['rotated']) ? '0' : '1') . '"' : '') . '>
	<form method="post" action="api_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100" id="n_form">
		' . get_token_field() . '
		<input type="hidden" name="api_action" value="create">
		<input type="hidden" name="kind" id="n_kind" value="">

		<div class="drawer-header pb-1 d-block">
			<div class="d-flex align-items-start gap-3">
				<span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" id="n_head_tile" aria-hidden="true"><i class="bi bi-plug" id="n_head_icon"></i></span>
				<div class="flex-grow-1 min-width-0" id="n_head_main">
					<h5 class="drawer-title mb-0" id="api_new_title">' . lang('New Application') . '</h5>
					<p class="small fg-2 mb-0 mt-1" id="n_head_pick">' . lang('What connects to this site? Pick one and the rest is prepared for it.') . '</p>
					<p class="small fg-2 mb-0 mt-1" id="n_head_chosen" hidden>
						<span id="n_chosen_title"></span> &middot;
						<button type="button" class="pg-button pg-button-ghost pg-button-sm" id="n_change">' . lang('Change') . '</button>
					</p>
				</div>
				' . ($done ? '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . h($connect['tone']) . '" id="n_done_tile" aria-hidden="true"><i class="bi ' . h($connect['icon']) . '"></i></span>' . $done_head : '') . '
				<button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" id="n_x" aria-label="' . h(lang('Close')) . '"></button>
			</div>
		</div>

		<div class="px-5 pt-3 pb-5 border-bottom" id="n_steps_wrap">' . api_app_new_steps_html(1) . '</div>

		<div class="drawer-body flex-grow-1 overflow-auto">

			<fieldset id="n_step_1" data-step="1">
				<legend class="visually-hidden">' . lang('What connects to this site?') . '</legend>
				<div class="api-start">' . $card_html . '
				</div>
				<div class="alert theme-primary py-3 px-5 small mt-5 mb-0">
					<i class="bi bi-info-circle me-1" aria-hidden="true"></i><b>' . lang('Why does "Acts as" matter?') . '</b>
					' . lang('A connection made by signing in writes as the person who signed in. A key writes as the application itself.') . '
				</div>
			</fieldset>

			<div id="n_step_2" data-step="2" hidden>
				<div class="row g-5 mb-5">
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="n_name">' . lang('Application name') . '</label>
						<input type="text" class="form-control form-control-sm" name="name" id="n_name" maxlength="190" required
							placeholder="' . lang('For example: marketplace sync') . '">
					</div>
					<div class="col-12 sm:col-6 form-field">
						<label class="form-label pg-field-label" for="n_description">' . lang('Description') . ' <span class="fg-2 fw-normal">' . lang('optional') . '</span></label>
						<input type="text" class="form-control form-control-sm" name="description" id="n_description" maxlength="500"
							placeholder="' . h(lang('What is it used for?')) . '">
					</div>
				</div>
				' . $agent_html . '
				<div role="group" aria-labelledby="n_scope_title" class="mb-5">
					<div class="form-label pg-field-label" id="n_scope_title"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>' . lang('Permissions') . '</div>
					<div class="alert theme-primary py-3 px-5 small mb-3 d-none" id="n_start_note" role="status">
						<i class="bi bi-magic me-1" aria-hidden="true"></i>' . h($start_note) . '
					</div>
					' . $permission_rows . '
				</div>
				<details class="api-new-advanced" id="n_advanced">
					<summary class="small"><i class="bi bi-sliders me-1" aria-hidden="true"></i><b>' . lang('Advanced') . '</b> <span class="small fg-2">' . lang('IP list and rate limit') . '</span></summary>
					<div class="form-field mt-3 mb-5">
						<label class="form-label pg-field-label" for="n_ip">' . lang('Allowed addresses') . '</label>
						<input type="text" class="form-control form-control-sm" name="ip_allowlist" id="n_ip" maxlength="500" aria-describedby="n_ip_help">
						<div class="form-text" id="n_ip_help">'
							. lang('Leave empty to let this application connect from anywhere. Once an address is added, requests from anywhere else are refused.')
							. '<br>' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . '</div>
					</div>
					<div class="form-field">
						<label class="form-label pg-field-label" for="n_rate">' . lang('Requests per minute') . '</label>
						<input type="number" class="form-control form-control-sm" name="rate_limit_per_min" id="n_rate" min="1" max="6000" aria-describedby="n_rate_help">
						<div class="form-text" id="n_rate_help">' . lang('When an application asks for more than this in one minute, the rest of that minute is refused and it is told how long to wait.') . '</div>
					</div>
				</details>
			</div>
' . ($done ? $connect['html'] : '') . '
		</div>

		<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">
			<button type="button" class="pg-button pg-button-ghost" id="n_back" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i>' . lang('Back') . '</button>
			<span class="small fg-2" id="n_hint_1"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . lang('The choice is only a start; the permissions can be changed in the next step.') . '</span>
			<span class="flex-grow-1"></span>
			<span class="small fg-2" id="n_hint_2" hidden>' . lang('Nothing is saved before Create.') . '</span>
			<button type="button" class="pg-button pg-button-ghost" id="n_cancel" data-bs-dismiss="drawer">' . lang('Cancel') . '</button>
			<button type="button" class="pg-button pg-button-fill" id="n_next"
				data-next="' . h(lang('Continue')) . '" data-open="' . h(lang('Open it')) . '"><span class="pg-btn-label">' . lang('Continue') . '</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
			<button type="submit" class="pg-button pg-button-fill" id="n_submit" hidden>' . lang('Create') . '</button>' . $done_foot . '
		</div>
	</form>
</dialog>';

}
