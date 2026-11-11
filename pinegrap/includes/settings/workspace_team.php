<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Team and working hours: the departments (each created or
 * changed in a drawer), the team members' rows (job title, working day, taken
 * out of the team), the Workspace defaults (the hours of a working day, the
 * working week, the hours a task with no estimate counts as), and a card
 * linking the working calendar still kept on the Workspace Settings screen.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and workspace_team.save.php writes what they edit. The fields
 * are named ws_* so they stay apart from every other category's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/team_settings.php');

// A drawer sits outside the settings form (the dialog's drawer host, or the
// page's Bootstrap drawer after the form), so its fields and its button join
// the form by its id: the department is saved with the pane's Save path.
$ws_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';
$ws_viewer = ws_viewer($user);
$ws_departments = ws_departments(true, true);
$ws_people_by_id = ws_people(ws_team_ids());

// ── Departments ──
$ws_department_rows = '';

foreach ($ws_departments as $ws_department) {
    $ws_id = (int) $ws_department['id'];
    $ws_channel = ((int) $ws_department['channel_id'] > 0) ? ws_channel($ws_department['channel_id']) : null;
    $ws_archive_label = $ws_department['archived'] ? lang('Bring it back') : lang('Move to the archive');

    $ws_department_rows .= ws_settings_department_row_html($ws_department, $ws_people_by_id, $ws_channel, '
                    <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="drawer" data-bs-target="#pgset-ws-dept-' . $ws_id . '" aria-controls="pgset-ws-dept-' . $ws_id . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), $ws_department['name'])))) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
                    <button type="submit" name="ws_department_archive" value="' . $ws_id . '" class="btn btn-sm btn-text theme-secondary" title="' . h($ws_archive_label) . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array($ws_archive_label, $ws_department['name'])))) . '"><i class="bi ' . ($ws_department['archived'] ? 'bi-arrow-counterclockwise' : 'bi-archive') . '" aria-hidden="true"></i></button>');

    $pg_settings_modals[] = ws_settings_department_drawer($ws_department, ws_settings_department_primary_ids($ws_id), $ws_channel, $ws_form, !empty($pg_settings_in_dialog));
}

$pg_settings_modals[] = ws_settings_department_drawer(null, array(), null, $ws_form, !empty($pg_settings_in_dialog));

if ($ws_department_rows === '') {
    $ws_department_rows = '<div class="ws-empty py-5"><i class="bi bi-diagram-3" aria-hidden="true"></i>' . h(lang('No departments yet. A department groups people so work can be handed to it and its lead sees its board.')) . '</div>';
}

$pg_settings_cards[] = '
    <div id="pgset-ws-departments" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0 ws-grow">' . h(lang('Departments')) . '</span>
                <button type="button" class="btn btn-sm btn-outline theme-primary rounded-pill" data-bs-toggle="drawer" data-bs-target="#pgset-ws-dept-0" aria-controls="pgset-ws-dept-0"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New department')) . '</button>
            </div>
            <div class="card-body">
                ' . $ws_department_rows . '
            </div>
        </div>
    </div>';

// ── Team members ──
$pg_settings_cards[] = '
    <div id="pgset-ws-people" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . h(lang('Team members')) . '
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th>' . h(lang('Team member')) . '</th>
                                <th>' . h(lang('Job title')) . '</th>
                                <th>' . h(lang('Hours a day')) . '</th>
                                <th>' . h(lang('Working days')) . '</th>
                                <th class="text-center">' . h(lang('Not in the team')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . ws_settings_people_rows_html(ws_settings_candidates(), $ws_departments, $ws_viewer, function ($person_id, $field) {
                            return 'ws_people[' . (int) $person_id . '][' . $field . ']';
                        }) . '</tbody>
                    </table>
                </div>
                <div class="form-text">' . h(lang('Every administrator, designer and manager is in the team, unless taken out of it on this screen.')) . ' ' . h(lang('A basic user joins the team when the Workspace right is switched on in their user settings. The same place gives them the right to hand work to others, to see the whole team\'s board and to change these settings.')) . ' <a href="view_users.php">' . h(lang('Users')) . '</a></div>
            </div>
        </div>
    </div>';

// ── Defaults ──
$pg_settings_cards[] = '
    <div id="pgset-ws-defaults" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . h(lang('Defaults')) . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <label class="form-label" for="ws_day_hours">' . h(lang('Hours in a working day')) . '</label>
                        <input class="form-control" type="text" inputmode="decimal" name="ws_day_hours" id="ws_day_hours" value="' . h(ws_settings_hours(WS_DAY_MINUTES)) . '">
                    </div>
                    <div class="col-12">
                        <div class="form-label">' . h(lang('Working days')) . '</div>
                        ' . ws_settings_days_html('ws_workdays', (int) WS_WORKDAYS, 'ws_default_day') . '
                        <div class="form-text"><a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_settings.php#ws-calendar') . '">' . h(lang('The days off are kept in the Working calendar card.')) . '</a></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ws_default_task_hours">' . h(lang('A task with no estimate counts as (hours)')) . '</label>
                        <input class="form-control" type="text" inputmode="decimal" name="ws_default_task_hours" id="ws_default_task_hours" value="' . h(ws_settings_hours(WS_DEFAULT_TASK_MINUTES)) . '">
                        <div class="form-text">' . h(lang('The board spreads a task\'s estimate over the working days between its start and its due date, and warns when a day goes over.')) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── The cards still on Workspace Settings ──
$pg_settings_cards[] = ws_settings_screen_links_card('pgset-ws-team-screen', array(
    array('ws-calendar', 'bi-calendar-x', lang('Working calendar')),
));
