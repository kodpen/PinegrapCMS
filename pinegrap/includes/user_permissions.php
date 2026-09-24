<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Access privileges, drawn once for both add_user.php and edit_user.php.
//
// The two screens carried the same eight rights groups inline, each as a heading
// plus a 300px scrolling box of checkboxes, all inside one card. That put ~150
// checkboxes in the page flow and made the page long enough that the scroll
// wheel landed inside a box instead of on the page.
//
// Here each group is one row - a gate switch, a title, a live summary of what is
// selected, and a button that opens the group's own offcanvas panel. The panels
// hold exactly the markup the screens used to render inline, so the field names,
// values and the multiselect-checkbox behaviour are unchanged and the POST
// handlers on both screens still read what they always read.
//
// The panels are rendered inside the screen's <form>; Bootstrap's offcanvas
// moves nothing in the DOM, so their inputs post with everything else.

// One row's worth of markup. $group is a normalised array built by
// pg_user_permission_ui() below.
function pg_user_permission_row($group)
{
	// A group either owns a real column (the gate posts and is the permission
	// itself) or owns none (the gate is a UI gate over its selections, and
	// backend.src.js remembers what it cleared so turning it back on restores).
	$gate_name  = ($group['gate_field'] !== '')
		? ' name="' . h($group['gate_field']) . '" value="' . h($group['gate_value']) . '"'
		: '';
	$gate_id    = 'pgperm_gate_' . $group['id'];
	$gate_extra = ($group['gate_field'] !== '') ? '' : ' data-pg-gate-ui="1"';

	// What a gate with no column of its own actually switches: one list of
	// checkboxes, or a set of fields that live in the panel. Page types are a
	// counter on the content row but not what grants the access, which is why
	// the driving scope is named rather than inferred from the counters.
	if (isset($group['gate_scope']) && ($group['gate_scope'] !== '')) {
		$gate_extra .= ' data-pg-gate-scope="' . h($group['gate_scope']) . '"';
	}

	if (isset($group['gate_fields']) && ($group['gate_fields'] !== '')) {
		$gate_extra .= ' data-pg-gate-fields="' . h($group['gate_fields']) . '"';
	}

	$output_summary = '';

	foreach ($group['counters'] as $counter) {
		$output_summary .=
			'<span class="pg-perm-pill" data-pg-count-for="' . h($counter['scope']) . '"'
			. ' data-pg-unit-one="' . h($counter['one']) . '"'
			. ' data-pg-unit-many="' . h($counter['many']) . '"></span>';
	}

	// Nothing to pick means no button and no empty-selection wording: the gate
	// is the whole permission (visitor reports is the only one today).
	if ($group['panel'] === '') {
		$output_summary = '<span class="pg-perm-none">' . h(lang('No extra selection needed')) . '</span>';
		$output_button  = '';
	} else {
		$output_button =
			'<button type="button" class="btn btn-sm btn-ghost pg-perm-open"'
			. ' data-bs-toggle="offcanvas" data-bs-target="#pgperm_panel_' . h($group['id']) . '"'
			. ' aria-controls="pgperm_panel_' . h($group['id']) . '">'
			. h(lang('Select')) . '<i class="bi bi-chevron-right ms-1"></i></button>';
	}

	return '
			<div class="pg-perm" data-pg-perm="' . h($group['id']) . '">
				<div class="form-check form-switch pg-perm-gate-box">
					<input class="form-check-input pg-perm-gate" type="checkbox" role="switch"' . $gate_name . $gate_extra
						. ' id="' . $gate_id . '"' . ($group['gate_on'] ? ' checked="checked"' : '') . ' />
					<label class="visually-hidden" for="' . $gate_id . '">' . h($group['title']) . '</label>
				</div>
				<span class="pg-perm-icon" style="--pg-perm-color:' . h($group['color']) . '"><i class="bi ' . h($group['icon']) . '"></i></span>
				<div class="pg-perm-text">
					<b>' . h($group['title']) . '</b>
					<span>' . h($group['description']) . '</span>
				</div>
				<div class="pg-perm-summary">' . $output_summary . '</div>
				' . $output_button . '
			</div>';
}

// One group's offcanvas panel.
function pg_user_permission_panel($group)
{
	if ($group['panel'] === '') {
		return '';
	}

	return '
		<div class="offcanvas offcanvas-end pg-perm-panel" tabindex="-1" id="pgperm_panel_' . h($group['id']) . '" aria-labelledby="pgperm_title_' . h($group['id']) . '">
			<div class="offcanvas-header">
				<span class="pg-perm-icon" style="--pg-perm-color:' . h($group['color']) . '"><i class="bi ' . h($group['icon']) . '"></i></span>
				<h5 class="offcanvas-title ms-2" id="pgperm_title_' . h($group['id']) . '">' . h($group['title']) . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>
			</div>
			<div class="offcanvas-body">
				<p class="pg-perm-panel-lead">' . h($group['panel_lead']) . '</p>
				' . $group['panel'] . '
			</div>
			<div class="offcanvas-footer">
				<div class="pg-perm-panel-count" data-pg-panel-count="' . h($group['id']) . '"></div>
				<button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-dismiss="offcanvas">' . h(lang('Done')) . '</button>
			</div>
		</div>';
}

// A block inside a panel: an optional heading, a scrollable list of checkboxes
// and its own "select all" switch. $scope names the counter this list feeds.
function pg_user_permission_list($scope, $heading, $items, $checker_id)
{
	$output_heading = ($heading !== '')
		? '<div class="pg-perm-block-title">' . h($heading) . '</div>'
		: '';

	return '
				<div class="pg-perm-block">
					' . $output_heading . '
					<div class="card multiselect-checkbox-container pg-perm-list" data-pg-count="' . h($scope) . '">
						<div class="card-header border-0 bg-reset">
							<div class="form-check form-switch mb-0">
								<input id="' . h($checker_id) . '" class="form-check-input multiselect-checkbox-checker" title="' . h(lang('Select/Deselect All')) . '" type="checkbox">
								<label for="' . h($checker_id) . '" class="form-check-label">' . h(lang('Select All')) . '</label>
							</div>
							<span class="pg-perm-list-count" data-pg-list-count="' . h(lang(array('string' => '{var:1} of {var:2} selected', 'vars' => array('{n}', '{m}')))) . '"></span>
						</div>
						<div class="card-body">
							' . $items . '
						</div>
					</div>
				</div>';
}

// A block inside a panel that is a plain group of switches rather than a list.
function pg_user_permission_switches($heading, $switches)
{
	return '
				<div class="pg-perm-block">
					<div class="pg-perm-block-title">' . h($heading) . '</div>
					<div class="pg-perm-switches">
						' . $switches . '
					</div>
				</div>';
}

// One switch inside a panel. $value keeps each field's existing posted value -
// some of these columns store 'yes' and some store '1', and the POST handlers
// on both screens compare against those exact strings.
function pg_user_permission_switch($name, $value, $checked, $label, $help = '')
{
	$output_help = ($help !== '')
		? '<div class="form-text">' . h($help) . '</div>'
		: '';

	return '
						<div class="pg-perm-switch">
							<div class="form-check form-switch mb-0">
								<input class="form-check-input" type="checkbox" role="switch" id="' . h($name) . '" name="' . h($name) . '" value="' . h($value) . '"' . ($checked ? ' checked="checked"' : '') . ' />
								<label class="form-check-label" for="' . h($name) . '">' . h($label) . '</label>
							</div>
							' . $output_help . '
						</div>';
}

// Whether the workspace row is drawn at all: the module switched on and the
// four columns in place. The three screens that edit rights ask this before
// they build the panel, so a site between its new files and its upgrade never
// posts a column that is not there.
function pg_user_permission_ws_available()
{
	return (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED
		&& function_exists('pg_user_has_ws_columns') && pg_user_has_ws_columns());
}

// The three workspace rights that sit behind the manage_workspace gate. The
// gate itself is drawn by the row.
function pg_user_permission_ws_switches($values = array())
{
	$on = function ($field) use ($values) {
		return !empty($values[$field]);
	};

	return pg_user_permission_switch('manage_workspace_assign', '1', $on('manage_workspace_assign'), lang('Assign work to others'), lang('Create tasks for colleagues and hand out a department\'s work.'))
		. pg_user_permission_switch('manage_workspace_board', '1', $on('manage_workspace_board'), lang('See the team board'), lang('Everyone\'s load, the company plan and the list of conflicts.'))
		. pg_user_permission_switch('manage_workspace_settings', '1', $on('manage_workspace_settings'), lang('Change workspace settings'), lang('Departments, working hours and capacity.'));
}

// The workspace columns of one account, or zeros where they do not exist yet.
// Read on their own so the screens' long SELECTs never have to name them.
function pg_user_permission_ws_values($user_id)
{
	$values = array(
		'manage_workspace'          => 0,
		'manage_workspace_assign'   => 0,
		'manage_workspace_board'    => 0,
		'manage_workspace_settings' => 0,
	);

	if (function_exists('pg_user_has_ws_columns') && pg_user_has_ws_columns() && ((int) $user_id > 0)) {
		$row = db_item("SELECT manage_workspace, manage_workspace_assign, manage_workspace_board, manage_workspace_settings
			FROM user WHERE user_id = '" . (int) $user_id . "'");

		if (is_array($row)) {
			foreach ($values as $field => $unused) {
				$values[$field] = (int) $row[$field];
			}
		}
	}

	return $values;
}

// The posted workspace rights as SQL, for the INSERT and the UPDATE of the
// three screens. Empty when the columns are not there yet.
function pg_user_permission_ws_sql($mode)
{
	if (!function_exists('pg_user_has_ws_columns') || !pg_user_has_ws_columns()) {
		return '';
	}

	$fields = array('manage_workspace', 'manage_workspace_assign', 'manage_workspace_board', 'manage_workspace_settings');
	$posted = array();

	foreach ($fields as $field) {
		$posted[$field] = !empty($_POST[$field]) ? 1 : 0;
	}

	// The three rights mean nothing without the gate.
	if ($posted['manage_workspace'] === 0) {
		$posted['manage_workspace_assign'] = 0;
		$posted['manage_workspace_board'] = 0;
		$posted['manage_workspace_settings'] = 0;
	}

	if ($mode === 'columns') {
		return implode(', ', $fields) . ',';
	}

	if ($mode === 'values') {
		return "'" . implode("', '", $posted) . "',";
	}

	$set = array();

	foreach ($posted as $field => $value) {
		$set[] = $field . " = '" . $value . "'";
	}

	return implode(', ', $set) . ',';
}

/**
 * Build the whole block: the rows and the offcanvas panels that go with them.
 *
 * $config:
 *   values   array  current state of the permission columns, keyed by field name.
 *   panels   array  pre-built checkbox markup per selection area. The caller
 *                   builds these because add and edit call the same generators
 *                   with different arguments (edit passes the user's selections).
 *   counts   array  how many boxes are ticked in each area right now. Only used
 *                   to decide the initial gate state; the visible numbers are
 *                   written by backend.src.js from the checkboxes themselves,
 *                   so the two can never disagree.
 *
 * Returns array('rows' => …, 'panels' => …).
 */
function pg_user_permission_ui($config)
{
	$values = isset($config['values']) ? $config['values'] : array();
	$panels = isset($config['panels']) ? $config['panels'] : array();
	$counts = isset($config['counts']) ? $config['counts'] : array();

	$value = function ($field, $on) use ($values) {
		return isset($values[$field]) && ((string) $values[$field] === (string) $on);
	};

	$count = function ($key) use ($counts) {
		return isset($counts[$key]) ? (int) $counts[$key] : 0;
	};

	$groups = array();

	// Content and forms. No column of its own: what the user may reach is the
	// folder list, so the gate is a UI gate over that selection.
	$content_panel =
		pg_user_permission_list('edit_folders', lang('Folders the User may view and edit'), $panels['edit_tree'], 'multiselect-checkbox-checker-0')
		. pg_user_permission_switches(lang('In the selected folders the User may also'),
			pg_user_permission_switch('create_pages', '1', $value('create_pages', '1'), lang('Create and duplicate pages'))
			. pg_user_permission_switch('delete_pages', '1', $value('delete_pages', '1'), lang('Delete pages'))
			. (isset($panels['manage_forms_switch']) ? $panels['manage_forms_switch'] : ''))
		. pg_user_permission_list('page_types', lang('Page types the User may set'), $panels['page_types'], 'multiselect-checkbox-checker-1');

	$groups[] = array(
		'id'          => 'content',
		'icon'        => 'bi-file-earmark-text',
		'color'       => '#64b5f6',
		'title'       => lang('Content and Forms'),
		'description' => lang('Pages, files and custom forms in the selected folders'),
		'panel_lead'  => lang('The User only reaches the folders selected here.'),
		'gate_field'  => '',
		'gate_value'  => '',
		'gate_on'     => ($count('edit_folders') > 0),
		'gate_scope'  => 'edit_folders',
		'panel'       => $content_panel,
		'counters'    => array(
			array('scope' => 'edit_folders', 'one' => lang('folder'),    'many' => lang('folders')),
			array('scope' => 'page_types',   'one' => lang('page type'), 'many' => lang('page types')),
		),
	);

	// Shared content: common regions and menus, two lists in one panel.
	$groups[] = array(
		'id'          => 'shared',
		'icon'        => 'bi-layout-text-window',
		'color'       => '#ff4c87',
		'title'       => lang('Shared Content'),
		'description' => lang('Common regions and menu items'),
		'panel_lead'  => lang('Content that appears on more than one page.'),
		'gate_field'  => '',
		'gate_value'  => '',
		'gate_on'     => (($count('common_regions') + $count('menus')) > 0),
		'panel'       =>
			pg_user_permission_list('common_regions', lang('Common Regions the User may edit'), $panels['common_regions'], 'multiselect-checkbox-checker-2')
			. pg_user_permission_list('menus', lang('Menus whose items the User may edit'), $panels['menus'], 'multiselect-checkbox-checker-3'),
		'counters'    => array(
			array('scope' => 'common_regions', 'one' => lang('region'), 'many' => lang('regions')),
			array('scope' => 'menus',          'one' => lang('menu'),   'many' => lang('menus')),
		),
	);

	// Contacts and campaigns. Two columns, either of which opens the contact
	// group list, so the gate mirrors "either is on" and the two live in the
	// panel where the list they unlock is.
	$contacts_on = $value('manage_contacts', 'yes') || $value('manage_emails', 'yes');

	$groups[] = array(
		'id'          => 'contacts',
		'icon'        => 'bi-people',
		'color'       => '#ff8e6c',
		'title'       => lang('Contacts and Campaigns'),
		'description' => lang('Contact groups, import and export, e-mail campaigns'),
		'panel_lead'  => lang('Both rights apply only to the contact groups selected here.'),
		'gate_field'  => '',
		'gate_value'  => '',
		'gate_on'     => $contacts_on,
		'gate_fields' => 'manage_contacts,manage_emails',
		'panel'       =>
			pg_user_permission_switches(lang('The User may'),
				pg_user_permission_switch('manage_contacts', 'yes', $value('manage_contacts', 'yes'), lang('View, edit, import and export contacts'))
				. pg_user_permission_switch('manage_emails', 'yes', $value('manage_emails', 'yes'), lang('Send e-mail campaigns')))
			. pg_user_permission_list('contact_groups', lang('Contact Groups'), $panels['contact_groups'], 'multiselect-checkbox-checker-5'),
		'counters'    => array(
			array('scope' => 'contact_groups', 'one' => lang('contact group'), 'many' => lang('contact groups')),
		),
	);

	// Calendars, only when the module is on.
	if (isset($panels['calendars'])) {
		$groups[] = array(
			'id'          => 'calendars',
			'icon'        => 'bi-calendar3',
			'color'       => '#4dd0e1',
			'title'       => lang('Calendars'),
			'description' => lang('Adding and publishing events'),
			'panel_lead'  => lang('The User may add events to the calendars selected here.'),
			'gate_field'  => 'manage_calendars',
			'gate_value'  => 'yes',
			'gate_on'     => $value('manage_calendars', 'yes'),
			'panel'       =>
				pg_user_permission_list('calendars', '', $panels['calendars'], 'multiselect-checkbox-checker-4')
				. pg_user_permission_switches(lang('In the selected calendars the User may also'),
					pg_user_permission_switch('publish_calendar_events', 'yes', $value('publish_calendar_events', 'yes'), lang('Publish events'))),
			'counters'    => array(
				array('scope' => 'calendars', 'one' => lang('calendar'), 'many' => lang('calendars')),
			),
		);
	}

	// Visitor reports: one column, nothing to pick.
	$groups[] = array(
		'id'          => 'visitors',
		'icon'        => 'bi-bar-chart',
		'color'       => '#9aa3ab',
		'title'       => lang('Visitor Reports'),
		'description' => lang('Viewing and managing all visitor reports'),
		'panel_lead'  => '',
		'gate_field'  => 'manage_visitors',
		'gate_value'  => 'yes',
		'gate_on'     => $value('manage_visitors', 'yes'),
		'panel'       => '',
		'counters'    => array(),
	);

	// Commerce, only when the module is on. The gate is the column; the three
	// finer rights sit in the panel behind it.
	if (isset($panels['commerce_switches'])) {
		$groups[] = array(
			'id'          => 'commerce',
			'icon'        => 'bi-shop',
			'color'       => '#b7ee78',
			'title'       => lang('Commerce'),
			'description' => lang('Products, shipping, tax and orders'),
			'panel_lead'  => lang('These rights apply to the whole store; there is no per-product selection.'),
			'gate_field'  => 'manage_ecommerce',
			'gate_value'  => 'yes',
			'gate_on'     => $value('manage_ecommerce', 'yes'),
			'panel'       => pg_user_permission_switches(lang('Beyond managing the store the User may'), $panels['commerce_switches']),
			'counters'    => array(),
		);
	}

	// ERP, only when the module is on. The gate column is prefix-less TINYINT, so
	// gate_value is '1' rather than the 'yes' the older user_manage_* columns use.
	if (isset($panels['erp_switches'])) {
		$groups[] = array(
			'id'          => 'erp',
			'icon'        => 'bi-receipt',
			'color'       => '#8fd3ff',
			'title'       => lang('ERP'),
			'description' => lang('Accounts, cash and invoicing'),
			'panel_lead'  => lang('The till and the module settings are separate rights, because seeing what a customer owes is not the same as seeing what is in the bank.'),
			'gate_field'  => 'manage_erp',
			'gate_value'  => '1',
			'gate_on'     => $value('manage_erp', '1'),
			'panel'       => pg_user_permission_switches(lang('Within the ERP module the User may'), $panels['erp_switches']),
			'counters'    => array(),
		);
	}

	// The workspace, only when the module is on. Same column shape as the ERP:
	// prefix-less TINYINT, so the gate value is '1'.
	if (isset($panels['workspace_switches'])) {
		$groups[] = array(
			'id'          => 'workspace',
			'icon'        => 'bi-clipboard2-check',
			'color'       => '#9575cd',
			'title'       => lang('Workspace'),
			'description' => lang('Channels, tasks and the planning board'),
			'panel_lead'  => lang('Every team member can talk in the channels and plan their own work. Handing work to others, seeing the whole team\'s board and changing the settings are separate rights.'),
			'gate_field'  => 'manage_workspace',
			'gate_value'  => '1',
			'gate_on'     => $value('manage_workspace', '1'),
			'panel'       => pg_user_permission_switches(lang('Within the workspace the User may'), $panels['workspace_switches']),
			'counters'    => array(),
		);
	}

	// Ads, only when the module is on.
	if (isset($panels['ad_regions'])) {
		$groups[] = array(
			'id'          => 'ads',
			'icon'        => 'bi-badge-ad',
			'color'       => '#ff4a3d',
			'title'       => lang('Ads'),
			'description' => lang('Ads within the selected ad regions'),
			'panel_lead'  => lang('The User may edit the ads inside the regions selected here.'),
			'gate_field'  => '',
			'gate_value'  => '',
			'gate_on'     => ($count('ad_regions') > 0),
			'gate_scope'  => 'ad_regions',
			'panel'       => pg_user_permission_list('ad_regions', '', $panels['ad_regions'], 'multiselect-checkbox-checker-6'),
			'counters'    => array(
				array('scope' => 'ad_regions', 'one' => lang('ad region'), 'many' => lang('ad regions')),
			),
		);
	}

	// Private content: view rights on folders, each with an optional expiry.
	$groups[] = array(
		'id'          => 'private',
		'icon'        => 'bi-lock',
		'color'       => '#e57373',
		'title'       => lang('Private Content Access'),
		'description' => lang('Viewing pages and files in private folders'),
		'panel_lead'  => lang('For selected folders, you can enter an optional expiration date.'),
		'gate_field'  => '',
		'gate_value'  => '',
		'gate_on'     => ($count('view_folders') > 0),
		'gate_scope'  => 'view_folders',
		'panel'       => pg_user_permission_list('view_folders', '', $panels['view_tree'], 'multiselect-checkbox-checker-7'),
		'counters'    => array(
			array('scope' => 'view_folders', 'one' => lang('folder'), 'many' => lang('folders')),
		),
	);

	$output_rows   = '';
	$output_panels = '';

	foreach ($groups as $group) {
		$output_rows   .= pg_user_permission_row($group);
		$output_panels .= pg_user_permission_panel($group);
	}

	return array(
		'rows'   => $output_rows,
		'panels' => $output_panels,
		'total'  => count($groups),
	);
}

/**
 * The role picker as cards rather than a picklist.
 *
 * The list of roles an editor may hand out is the same rule select_user_role()
 * applies: an administrator may create administrators and designers, everybody
 * else may only go down to manager. Kept in step with that function by hand -
 * it returns <option> markup and cannot be reused here.
 *
 * A manager gets the cards read-only and a hidden role field alongside, exactly
 * as the disabled picklist worked: a disabled control posts nothing.
 */
function pg_user_role_cards($selected_role, $editor_role, $disabled = false)
{
	$roles = array();

	if ((string) $editor_role === '0') {
		$roles[] = array('0', lang('Administrator'), lang('Reaches everything'),     'bi-shield-fill-check');
		$roles[] = array('1', lang('Designer'),      lang('Design and content'),     'bi-brush');
	}

	$roles[] = array('2', lang('Manager'), lang('Content and commerce'),  'bi-briefcase');
	$roles[] = array('3', lang('User'),    lang('Only granted rights'),   'bi-person');

	$output = '';

	foreach ($roles as $role) {

		$checked = ((string) $role[0] === (string) $selected_role);

		$output .= '
					<div class="pg-role">
						<input type="radio" class="btn-check" name="role" id="pgrole_' . h($role[0]) . '" value="' . h($role[0]) . '"'
			. ($checked ? ' checked="checked"' : '')
			. ($disabled ? ' disabled="disabled"' : '')
			. ' onchange="change_user_role(this.value)" />
						<label class="pg-role-card" for="pgrole_' . h($role[0]) . '">
							<b><i class="bi ' . h($role[3]) . '"></i>' . h($role[1]) . '</b>
							<span>' . h($role[2]) . '</span>
						</label>
					</div>';
	}

	return '<div class="pg-roles">' . $output . '
				</div>';
}

/**
 * The notice that replaces the rows for every role above "user": those roles
 * carry all rights, so there is nothing to pick. Naming what is covered is the
 * point - the block used to simply disappear, which read as a bug.
 */
function pg_user_permission_everything()
{
	$areas = array(
		lang('Content and Forms'),
		lang('Shared Content'),
		lang('Contacts and Campaigns'),
		lang('Calendars'),
		lang('Visitor Reports'),
		lang('Commerce'),
		lang('ERP'),
		lang('Private Content Access'),
	);

	$output_areas = '';

	foreach ($areas as $area) {
		$output_areas .= '<span>' . h($area) . '</span>';
	}

	return '
			<div class="alert alert-warning mb-0">
				<div class="pg-perm-everything">
					<i class="bi bi-shield-fill-check"></i>
					<div>
						<b>' . h(lang('This role carries every right.')) . '</b>
						<p class="mb-0 small">' . h(lang('Rights are not picked one by one for this role. To limit them, choose the User role.')) . '</p>
						<div class="pg-perm-everything-list">' . $output_areas . '</div>
					</div>
				</div>
			</div>';
}
