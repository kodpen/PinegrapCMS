<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Team and working hours: the Workspace defaults (the hours
 * of a working day, the working week, the hours a task with no estimate
 * counts as), and a card linking the team cards still kept on the Workspace
 * Settings screen.
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
    array('ws-departments', 'bi-diagram-3', lang('Departments')),
    array('ws-people', 'bi-people', lang('Team members')),
    array('ws-calendar', 'bi-calendar-x', lang('Working calendar')),
));
