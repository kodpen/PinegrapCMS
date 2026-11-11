<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Team and working hours writes: the team members' rows
 * and the Workspace defaults with the pane's Save, then the job of the card
 * button that asked for the save: a department's drawer (ws_department_save)
 * or a department's archive button (ws_department_archive).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (includes/workspace/team_settings.php, ws_settings_defaults_save()), so a
 * form still open there saves the same way. Everything is checked before
 * anything is written: a refused value is marked on its field and nothing is
 * stored.
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

$ws_viewer = ws_viewer($user);

// Team members: only the rows posted are written, so a request without the
// table changes nobody.
$ws_people = ws_settings_people_check($ws_viewer, ws_settings_candidates(), post_value('ws_people'));

foreach ($ws_people['refused'] as $ws_person_id => $ws_sentence) {
    $liveform->mark_error('ws_people[' . (int) $ws_person_id . '][day_hours]', $ws_sentence);
}

// A department's drawer asked for this save: its own fields, by its id.
$ws_department_id = post_value('ws_department_save');
$ws_department = null;

if (($ws_department_id !== null) && is_scalar($ws_department_id)) {
    $ws_department_id = (int) $ws_department_id;
    $ws_department_input = $_POST['ws_dept'][$ws_department_id] ?? array();
    $ws_department_input = is_array($ws_department_input) ? $ws_department_input : array();
    $ws_department = ws_settings_department_check($ws_department_id, $ws_department_input);

    if ($ws_department['error'] !== '') {
        $liveform->mark_error('ws_dept[' . $ws_department_id . '][name]', $ws_department['error']);
    }
}

if ($liveform->check_form_errors()) {
    return;
}

// The card's fields missing from a hand-made request leave the defaults
// alone: an empty box means the default, so a missing one would reset it.
if (isset($_POST['ws_day_hours'], $_POST['ws_default_task_hours'])) {

    $ws_refused = ws_settings_defaults_save(post_value('ws_day_hours'), post_value('ws_workdays'), post_value('ws_default_task_hours'));

    foreach ($ws_refused as $ws_field => $ws_sentence) {
        $liveform->mark_error('ws_' . $ws_field, $ws_sentence);
    }

    if ($ws_refused) {
        return;
    }
}

if ($ws_people['writes']) {
    ws_settings_people_write($ws_people['writes']);
}

if ($ws_department !== null) {
    $ws_saved = ws_settings_department_save($ws_viewer, $ws_department, $ws_department_input, ws_team_ids());

    if ($ws_saved['warning'] !== '') {
        $liveform->add_warning($ws_saved['warning']);
    }

    $liveform->add_notice(lang(array('string' => 'The department was saved: {var:1}.', 'vars' => h($ws_department['name']))));
}

// A department's archive button: the rest of the pane is saved by now, so a
// refusal is a warning, not a mark.
$ws_archive_id = post_value('ws_department_archive');

if (($ws_archive_id !== null) && is_scalar($ws_archive_id)) {
    $ws_archived = ws_settings_department_archive((int) $ws_archive_id);

    if ($ws_archived['error'] !== '') {
        $liveform->add_warning($ws_archived['error']);
    } elseif ($ws_archived['notice'] !== '') {
        $liveform->add_notice($ws_archived['notice']);
    }
}
