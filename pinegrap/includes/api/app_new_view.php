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

// One starting point as a choice card. A card that opens an application
// already there (the site's one OAuth application) carries its id.
function api_app_new_card_html($card, $checked) {

	$id = 'n_start_' . $card['id'];

	return '
				<div class="form-field form-field-card api-start-card">
					<input class="radio" type="radio" name="start" value="' . h($card['id']) . '" id="' . $id . '"'
						. ' data-kind="' . h($card['kind']) . '" data-existing="' . (int)$card['existing'] . '"'
						. ' aria-describedby="' . $id . '_help ' . $id . '_acts"' . ($checked ? ' checked' : '') . '>
					<label class="api-start-head" for="' . $id . '">
						<span class="api-start-icon" aria-hidden="true"><i class="bi ' . h($card['icon']) . '"></i></span>
						<span class="small fw-bold">' . h($card['title']) . '</span>'
						. (($card['badge'] !== '') ? ' <span class="pg-pill pg-tone-green">' . h($card['badge']) . '</span>' : '') . '
					</label>
					<div class="form-text mt-1" id="' . $id . '_help">' . h($card['text']) . '</div>
					<div class="form-text mt-1 fg-2" id="' . $id . '_acts"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>' . h($card['acts_as']) . '</div>
				</div>';

}

// The whole sheet. $cards is api_app_templates(), $permission_rows the
// permission rows (api_settings_permission_rows() with the 'scope' names),
// $start_note the line naming what the automation card ticks.
function api_app_new_view($cards, $permission_rows, $start_note) {

	$card_html = '';

	$first = true;

	foreach ($cards as $card) {

		$card_html .= api_app_new_card_html($card, $first);

		$first = false;

	}

	return '
<!-- New application -->
<dialog class="drawer drawer-end" id="api_new" aria-labelledby="api_new_title" style="width:min(760px,100%)">
	<form method="post" action="api_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100" id="n_form">
		' . get_token_field() . '
		<input type="hidden" name="api_action" value="create">
		<input type="hidden" name="kind" id="n_kind" value="">

		<div class="drawer-header pb-1 d-block">
			<div class="d-flex align-items-start">
				<h5 class="drawer-title mb-0" id="api_new_title">' . lang('New Application') . '</h5>
				<button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
			</div>
		</div>

		<div class="px-5 pt-3 pb-5 border-bottom">' . api_app_new_steps_html(1) . '</div>

		<div class="drawer-body flex-grow-1 overflow-auto">

			<fieldset id="n_step_1" data-step="1">
				<legend class="form-label pg-field-label">' . lang('What connects to this site?') . '</legend>
				<div class="api-start">' . $card_html . '
				</div>
			</fieldset>

			<div id="n_step_2" data-step="2" hidden>
				<div class="d-flex align-items-center gap-3 mb-5">
					<span class="api-start-icon" aria-hidden="true"><i class="bi" id="n_chosen_icon"></i></span>
					<div class="flex-grow-1 min-width-0">
						<b class="d-block small" id="n_chosen_title"></b>
						<span class="d-block small fg-2" id="n_chosen_acts"></span>
					</div>
					<button type="button" class="pg-button pg-button-quiet pg-button-sm" id="n_change">' . lang('Change') . '</button>
				</div>
				<div class="form-field mb-5">
					<label class="form-label pg-field-label" for="n_name">' . lang('Name') . '</label>
					<input type="text" class="form-control form-control-sm" name="name" id="n_name" maxlength="190" required
						placeholder="' . lang('For example: marketplace sync') . '">
				</div>
				<div class="form-field mb-5">
					<label class="form-label pg-field-label" for="n_description">' . lang('Description') . '</label>
					<input type="text" class="form-control form-control-sm" name="description" id="n_description" maxlength="500">
				</div>
				<div class="alert theme-primary py-3 px-5 small mb-5 d-none" id="n_start_note" role="status">
					<i class="bi bi-magic me-1" aria-hidden="true"></i>' . h($start_note) . '
				</div>
				<div role="group" aria-labelledby="n_scope_title" class="mb-5">
					<div class="form-label pg-field-label" id="n_scope_title">' . lang('Permissions') . '</div>
					' . $permission_rows . '
				</div>
				<details class="api-new-advanced" id="n_advanced">
					<summary class="small fw-bold">' . lang('Advanced') . '</summary>
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

		</div>

		<div class="drawer-footer border-top p-5 d-flex gap-3">
			<button type="button" class="pg-button pg-button-quiet" id="n_back" hidden>' . lang('Back') . '</button>
			<span class="flex-grow-1"></span>
			<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . lang('Cancel') . '</button>
			<button type="button" class="pg-button pg-button-fill" id="n_next"
				data-next="' . h(lang('Next')) . '" data-open="' . h(lang('Open it')) . '">' . lang('Next') . '</button>
			<button type="submit" class="pg-button pg-button-fill" id="n_submit" hidden
				data-server="' . h(lang('Create and show the secret')) . '" data-device="' . h(lang('Create')) . '">' . lang('Create and show the secret') . '</button>
		</div>
	</form>
</dialog>';

}
