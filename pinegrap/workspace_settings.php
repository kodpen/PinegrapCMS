<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the settings: departments (who is in which, who leads it, which
 * one is somebody's own), each person's working day, the defaults the
 * planning board counts with, and the working calendar (days off). Who is
 * in the team at all is decided on the user screens: every staff account,
 * and the basic users given the Workspace right there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!$viewer['settings']) {
    log_activity(lang('access denied to the workspace settings'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('liveform.class.php');
$liveform = new liveform('workspace_settings');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_settings.php';

/**
 * Hours as typed ("7,5", "8") in minutes, inside a range.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int  0 when nothing usable was typed
 */
function ws_settings_minutes($value, $min, $max)
{
    $text = str_replace(',', '.', trim((string) $value));

    if (($text === '') || !is_numeric($text)) {
        return 0;
    }

    return max($min, min($max, (int) round(((float) $text) * 60)));
}

/**
 * The ticked weekdays (1 = Monday) as the bit mask the board reads.
 *
 * @param mixed $days
 * @return int
 */
function ws_settings_mask($days)
{
    $mask = 0;

    foreach ((array) $days as $day) {
        $day = (int) $day;

        if (($day >= 1) && ($day <= 7)) {
            $mask |= (1 << ($day - 1));
        }
    }

    return $mask;
}

/**
 * Seven small toggles for the working days.
 *
 * @param string $name  the field name, [] is added
 * @param int    $mask
 * @param string $id_prefix
 * @return string
 */
function ws_settings_days_html($name, $mask, $id_prefix)
{
    $output = '<div class="btn-group btn-group-sm ws-days" role="group" aria-label="' . h(lang('Working days')) . '">';

    foreach (ws_weekday_short_names() as $day => $label) {
        $id = $id_prefix . '_' . $day;

        $output .= '<input type="checkbox" class="btn-check" name="' . h($name) . '[]" value="' . $day . '" id="' . h($id) . '" autocomplete="off"'
            . (($mask & (1 << ($day - 1))) ? ' checked' : '') . '>'
            . '<label class="btn btn-outline-secondary" for="' . h($id) . '">' . h($label) . '</label>';
    }

    return $output . '</div>';
}

/**
 * Minutes as the hours a field shows: "8", "7.5".
 *
 * @param int $minutes
 * @return string
 */
function ws_settings_hours($minutes)
{
    $hours = round(((int) $minutes) / 60, 2);

    return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
}

// Everybody who could be in the team, the ones taken out of it included, so
// they can be brought back.
$candidates = array();

foreach ((array) db_items(ws_people_select() . "
    WHERE (u.user_role < 3 OR u.manage_workspace = 1)
    ORDER BY c.first_name, c.last_name, u.user_username
    LIMIT 1000") as $row) {
    $brief = ws_person_brief($row);
    $brief['day_minutes'] = (int) ($row['day_minutes'] ?? 0);
    $brief['workdays'] = (int) ($row['workdays'] ?? 0);
    $brief['excluded'] = ((int) db_value("SELECT excluded FROM ws_profiles WHERE user_id = '" . (int) $row['id'] . "'") === 1);
    $candidates[(int) $row['id']] = $brief;
}

$team_ids = ws_team_ids();

if ($_POST) {

    validate_token_field();

    $action = (string) ($_POST['ws_action'] ?? '');
    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $anchor = '';

    switch ($action) {

        case 'defaults':
            $day = ws_settings_minutes($_POST['day_hours'] ?? '', 30, 1440);
            $mask = ws_settings_mask($_POST['workdays'] ?? array());
            $default = ws_settings_minutes($_POST['default_task_hours'] ?? '', 5, 2400);

            db("UPDATE config SET
                ws_day_minutes = '" . (($day > 0) ? $day : 480) . "',
                ws_workdays = '" . (($mask > 0) ? $mask : 31) . "',
                ws_default_task_minutes = '" . (($default > 0) ? $default : 60) . "'");

            log_activity(lang('the workspace defaults were changed'), $username);
            $liveform->add_notice(lang('The defaults were saved.'));
            $anchor = '#ws-defaults';
            break;

        case 'department':
            $department_id = (int) ($_POST['department_id'] ?? 0);
            $current = ($department_id > 0) ? ws_department($department_id) : null;
            $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? ''))), 0, 100);
            $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($_POST['color'] ?? '')) ? strtolower($_POST['color']) : '#6c757d';

            if (($department_id > 0) && !$current) {
                $liveform->add_error(lang('That department could not be found.'));
                break;
            }

            if ($name === '') {
                $liveform->add_error(lang('A department needs a name.'));
                break;
            }

            if ((int) db_value("SELECT COUNT(*) FROM ws_departments WHERE name = '" . e($name) . "' AND id <> '" . $department_id . "' AND archived = 0") > 0) {
                $liveform->add_error(lang('There is already a department with this name.'));
                break;
            }

            if ($current) {
                db("UPDATE ws_departments SET name = '" . e($name) . "', color = '" . e($color) . "' WHERE id = '" . $department_id . "'");
            } else {
                $sort = (int) db_value("SELECT MAX(sort) FROM ws_departments") + 1;

                db("INSERT INTO ws_departments (name, color, sort, created_at)
                    VALUES ('" . e($name) . "', '" . e($color) . "', '" . $sort . "', '" . time() . "')");

                $department_id = (int) mysqli_insert_id(db::$con);
            }

            // Members, their leads and whose own department this is. Only people
            // in the team are taken.
            $members = array_values(array_intersect(array_map('intval', (array) ($_POST['members'] ?? array())), $team_ids));
            $leads = array_map('intval', (array) ($_POST['leads'] ?? array()));
            $primary = array_map('intval', (array) ($_POST['primary'] ?? array()));

            db("DELETE FROM ws_department_members WHERE department_id = '" . $department_id . "'");

            foreach ($members as $member_id) {
                $is_primary = in_array($member_id, $primary, true);

                // Nobody has two departments of their own: marking this one
                // takes the mark off the other.
                if ($is_primary) {
                    db("UPDATE ws_department_members SET is_primary = 0 WHERE user_id = '" . $member_id . "'");
                }

                // Somebody's first department is theirs until they say otherwise.
                if (!$is_primary && ((int) db_value("SELECT COUNT(*) FROM ws_department_members WHERE user_id = '" . $member_id . "' AND is_primary = 1") === 0)) {
                    $is_primary = true;
                }

                db("INSERT INTO ws_department_members (department_id, user_id, is_lead, is_primary)
                    VALUES ('" . $department_id . "', '" . $member_id . "', '" . (in_array($member_id, $leads, true) ? 1 : 0) . "', '" . ($is_primary ? 1 : 0) . "')");
            }

            // The department's channel: opened when asked for, and kept up with
            // the department's people (nobody is taken out of it here - a
            // conversation someone was in stays theirs to leave).
            $channel_id = $current ? (int) $current['channel_id'] : 0;
            $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

            if (!$channel && !empty($_POST['create_channel'])) {
                $created = ws_channel_create($viewer, array(
                    'name'          => $name,
                    'kind'          => 'public',
                    'topic'         => lang(array('string' => 'The {var:1} department', 'vars' => $name)),
                    'department_id' => $department_id,
                    'members'       => $members,
                ));

                if ($created['ok']) {
                    db("UPDATE ws_departments SET channel_id = '" . (int) $created['channel_id'] . "' WHERE id = '" . $department_id . "'");
                } else {
                    $liveform->add_warning(lang(array('string' => 'The department was saved, but its channel could not be opened: {var:1}', 'vars' => $created['error'])));
                }
            } elseif ($channel && !empty($members)) {
                ws_channel_add_members($viewer, $channel, $members, false);
            }

            log_activity(lang(array('string' => 'workspace department ({var:1}) was saved', 'vars' => $name)), $username);
            $liveform->add_notice(lang(array('string' => 'The department was saved: {var:1}.', 'vars' => $name)));
            $anchor = '#ws-departments';
            break;

        case 'department_archive':
            $department = ws_department((int) ($_POST['department_id'] ?? 0));

            if ($department) {
                $archive = !$department['archived'];

                db("UPDATE ws_departments SET archived = '" . ($archive ? 1 : 0) . "' WHERE id = '" . (int) $department['id'] . "'");
                log_activity($archive
                    ? lang(array('string' => 'workspace department ({var:1}) was archived', 'vars' => $department['name']))
                    : lang(array('string' => 'workspace department ({var:1}) was brought back', 'vars' => $department['name'])), $username);
                $liveform->add_notice($archive
                    ? lang(array('string' => 'The department was archived: {var:1}. Its tasks keep it; new ones cannot choose it.', 'vars' => $department['name']))
                    : lang(array('string' => 'The department is back: {var:1}.', 'vars' => $department['name'])));
            }

            $anchor = '#ws-departments';
            break;

        case 'people':
            $rows = (array) ($_POST['people'] ?? array());
            $saved = 0;

            foreach ($rows as $person_id => $row) {
                $person_id = (int) $person_id;

                if (!isset($candidates[$person_id]) || !is_array($row)) {
                    continue;
                }

                $title = mb_substr(trim((string) ($row['title'] ?? '')), 0, 100);
                $day = ws_settings_minutes($row['day_hours'] ?? '', 0, 1440);
                $mask = ws_settings_mask($row['workdays'] ?? array());

                // A value equal to the default is stored as "the default", so
                // the person follows it when the default changes later.
                if ($day === (int) WS_DAY_MINUTES) {
                    $day = 0;
                }

                if (($mask === (int) WS_WORKDAYS) || ($mask === 0)) {
                    $mask = 0;
                }

                // Nobody takes themselves out: it would lock them out of the
                // screen they are standing on.
                $excluded = ($person_id !== (int) $viewer['id']) && !empty($row['excluded']);

                db("INSERT INTO ws_profiles (user_id, title, day_minutes, workdays, excluded, updated_at)
                    VALUES ('" . $person_id . "', '" . e($title) . "', '" . $day . "', '" . $mask . "', '" . ($excluded ? 1 : 0) . "', '" . time() . "')
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        day_minutes = VALUES(day_minutes),
                        workdays = VALUES(workdays),
                        excluded = VALUES(excluded),
                        updated_at = VALUES(updated_at)");

                $saved++;
            }

            log_activity(lang('the workspace people settings were changed'), $username);
            $liveform->add_notice(lang(array('string' => 'Saved for {var:1} people.', 'vars' => $saved)));
            $anchor = '#ws-people';
            break;

        // The working calendar: days off by hand and from calendar
        // addresses (includes/workspace/workdays.php).
        case 'holiday_add':
        case 'holiday_remove':
        case 'feed_add':
        case 'feed_sync':
        case 'feed_remove':
            ws_holidays_settings_post($viewer, $action, $liveform);
            $anchor = '#ws-calendar';
            break;

        // A day of a calendar left out or counted again: back to the open
        // list of that calendar's days.
        case 'feed_day':
            ws_holidays_settings_post($viewer, $action, $liveform);
            $anchor = '?feed=' . (int) ($_POST['feed_id'] ?? 0) . '#ws-feed-' . (int) ($_POST['feed_id'] ?? 0);
            break;

        // Asking Claude in the channels (includes/workspace/claude.php).
        case 'claude':
        case 'claude_test':
            ws_claude_settings_post($viewer, $action, $liveform);
            $anchor = '#ws-claude';
            break;
    }

    go($self_url . $anchor);
}

// ── Output ────────────────────────────────────────────────────────────

$departments = ws_departments(true, true);
$people_by_id = ws_people($team_ids);

$output_departments = '';

foreach ($departments as $department) {
    $id = (int) $department['id'];
    $member_names = array();
    $lead_names = array();

    foreach ($department['members'] as $member_id) {
        $member_names[] = $people_by_id[$member_id]['name'] ?? ws_person_name($member_id);
    }

    foreach ($department['leads'] as $member_id) {
        $lead_names[] = $people_by_id[$member_id]['name'] ?? ws_person_name($member_id);
    }

    $primary_ids = array_map('intval', (array) db_values("SELECT user_id FROM ws_department_members WHERE department_id = '" . $id . "' AND is_primary = 1"));
    $channel = ((int) $department['channel_id'] > 0) ? ws_channel($department['channel_id']) : null;

    $output_departments .= '
        <div class="ws-dept-row' . ($department['archived'] ? ' opacity-50' : '') . '">
            <span class="ws-dept-swatch" style="background:' . h($department['color']) . '"></span>
            <div class="ws-grow">
                <div class="fw-semibold">' . h($department['name']) . ($department['archived'] ? ' <span class="badge text-bg-secondary ms-1">' . h(lang('In the archive')) . '</span>' : '') . '</div>
                <div class="small text-body-secondary">'
                    . h(lang(array('string' => '{var:1} people', 'vars' => count($department['members']))))
                    . (!empty($lead_names) ? ' · ' . h(lang(array('string' => 'Lead: {var:1}', 'vars' => implode(', ', $lead_names)))) : '')
                    . ($channel ? ' · <a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h($channel['name']) . '</a>' : '') . '
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="collapse" data-bs-target="#ws-dept-' . $id . '" aria-expanded="false"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            <form method="post" action="' . h($self_url) . '" class="d-inline">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="department_archive">
                <input type="hidden" name="department_id" value="' . $id . '">
                <button type="submit" class="btn btn-sm btn-ghost" title="' . h($department['archived'] ? lang('Bring it back') : lang('Move to the archive')) . '"><i class="bi ' . ($department['archived'] ? 'bi-arrow-counterclockwise' : 'bi-archive') . '" aria-hidden="true"></i></button>
            </form>
        </div>
        <div class="collapse" id="ws-dept-' . $id . '">
            ' . ws_settings_department_form($self_url, $department, $primary_ids, $channel) . '
        </div>';
}

if ($output_departments === '') {
    $output_departments = '<div class="ws-empty py-3"><i class="bi bi-diagram-3" aria-hidden="true"></i>' . h(lang('No departments yet. A department groups people so work can be handed to it and its lead sees its board.')) . '</div>';
}

/**
 * The form a department is created or changed with.
 *
 * @param string     $self_url
 * @param array|null $department
 * @param int[]      $primary_ids
 * @param array|null $channel
 * @return string
 */
function ws_settings_department_form($self_url, $department, $primary_ids, $channel)
{
    $id = $department ? (int) $department['id'] : 0;
    $members = $department ? $department['members'] : array();
    $leads = $department ? $department['leads'] : array();
    $prefix = 'ws_d' . $id;

    $rows = '';

    foreach (ws_team_members() as $person) {
        $person_id = (int) $person['id'];
        $in = in_array($person_id, $members, true);

        $rows .= '
            <tr>
                <td>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="members[]" value="' . $person_id . '" id="' . $prefix . '_m' . $person_id . '"' . ($in ? ' checked' : '') . '>
                        <label class="form-check-label" for="' . $prefix . '_m' . $person_id . '">' . h($person['name']) . ($person['title'] !== '' ? ' <span class="text-body-secondary small">' . h($person['title']) . '</span>' : '') . '</label>
                    </div>
                </td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="leads[]" value="' . $person_id . '" aria-label="' . h(lang('Team lead')) . '"' . (in_array($person_id, $leads, true) ? ' checked' : '') . '></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="primary[]" value="' . $person_id . '" aria-label="' . h(lang('Own department')) . '"' . (in_array($person_id, $primary_ids, true) ? ' checked' : '') . '></td>
            </tr>';
    }

    return '
        <form method="post" action="' . h($self_url) . '" class="ws-dept-form">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="department">
            <input type="hidden" name="department_id" value="' . $id . '">
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label" for="' . $prefix . '_name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="name" id="' . $prefix . '_name" maxlength="100" required value="' . h($department ? $department['name'] : '') . '">
                </div>
                <div class="col-auto">
                    <label class="form-label" for="' . $prefix . '_color">' . h(lang('Colour')) . '</label>
                    <input class="form-control form-control-sm form-control-color" type="color" name="color" id="' . $prefix . '_color" value="' . h($department ? $department['color'] : '#6c757d') . '">
                </div>
            </div>
            <div class="table-responsive ws-dept-people">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr>
                            <th>' . h(lang('In the department')) . '</th>
                            <th class="text-center">' . h(lang('Team lead')) . '</th>
                            <th class="text-center">' . h(lang('Own department')) . '</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
            <div class="form-text mb-2">' . h(lang('A lead sees the department\'s board and may hand its work around and overrule a clash with leave. Somebody in several departments is counted in their own one first.')) . '</div>
            ' . ($channel ? '' : '
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="create_channel" value="1" id="' . $prefix . '_channel"' . ($department ? '' : ' checked') . '>
                <label class="form-check-label" for="' . $prefix . '_channel">' . h(lang('Open a channel for the department')) . '</label>
            </div>') . '
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h($department ? lang('Save') : lang('Create the department')) . '</button>
        </form>';
}

// People: title, working day, taken out of the team.
$output_people = '';

foreach ($candidates as $person) {
    $person_id = (int) $person['id'];
    $day = ($person['day_minutes'] > 0) ? $person['day_minutes'] : (int) WS_DAY_MINUTES;
    $mask = ($person['workdays'] > 0) ? $person['workdays'] : (int) WS_WORKDAYS;
    $own_departments = array();

    foreach (ws_user_department_ids($person_id) as $department_id) {
        if (isset($departments[$department_id]) && !$departments[$department_id]['archived']) {
            $own_departments[] = '<span class="badge rounded-pill" style="background:' . h($departments[$department_id]['color']) . '">' . h($departments[$department_id]['name']) . '</span>';
        }
    }

    $output_people .= '
        <tr' . ($person['excluded'] ? ' class="opacity-50"' : '') . '>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <img src="' . h($person['avatar']) . '" alt="" class="rounded-circle" width="28" height="28" loading="lazy">
                    <div>
                        <div class="fw-semibold">' . h($person['name']) . '</div>
                        <div class="small">' . implode(' ', $own_departments) . '</div>
                    </div>
                </div>
            </td>
            <td><input class="form-control form-control-sm" type="text" name="people[' . $person_id . '][title]" maxlength="100" value="' . h($person['title']) . '" aria-label="' . h(lang('Job title')) . '"></td>
            <td style="width:6rem"><input class="form-control form-control-sm" type="text" inputmode="decimal" name="people[' . $person_id . '][day_hours]" value="' . h(ws_settings_hours($day)) . '" aria-label="' . h(lang('Hours a day')) . '"></td>
            <td>' . ws_settings_days_html('people[' . $person_id . '][workdays]', $mask, 'ws_p' . $person_id) . '</td>
            <td class="text-center">
                <div class="form-check form-switch d-inline-block mb-0">
                    <input class="form-check-input" type="checkbox" name="people[' . $person_id . '][excluded]" value="1" aria-label="' . h(lang('Not in the team')) . '"'
                        . ($person['excluded'] ? ' checked' : '')
                        . (($person_id === (int) $viewer['id']) ? ' disabled' : '') . '>
                </div>
            </td>
        </tr>';
}

echo
pg_page_shell([
        'title' => lang('Workspace Settings'),
        'extra classes' => 'workspace workspace_settings',
        'icon' => 'workspace',
        'heading' => lang('Workspace Settings'),
        'heading_description' => lang('Departments, working days and what the planning board counts with.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
<div class="ws-shell">
    ' . ws_screen_rail($viewer, 'settings') . '
<div class="ws-shell-main">
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <nav id="button_bar" class="pg-toolbar navigation mb-3" aria-label="' . lang('Button Bar') . '">
        <a class="btn btn-sm btn-ghost" href="workspace.php"><i class="bi bi-chat-square-text me-1" aria-hidden="true"></i>' . h(lang('Channels')) . '</a>
        <a class="btn btn-sm btn-ghost" href="workspace_board.php"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>' . h(lang('Planning Board')) . '</a>
    </nav>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4" id="ws-departments">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>' . h(lang('Departments')) . '</h2>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#ws-dept-new"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New department')) . '</button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-3" id="ws-dept-new">
                        ' . ws_settings_department_form($self_url, null, array(), null) . '
                    </div>
                    ' . $output_departments . '
                </div>
            </div>

            <form method="post" action="' . h($self_url) . '" class="card" id="ws-people">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="people">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-people me-1" aria-hidden="true"></i>' . h(lang('Team members')) . '</h2>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>' . h(lang('Team member')) . '</th>
                                    <th>' . h(lang('Job title')) . '</th>
                                    <th>' . h(lang('Hours a day')) . '</th>
                                    <th>' . h(lang('Working days')) . '</th>
                                    <th class="text-center">' . h(lang('Not in the team')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_people . '</tbody>
                        </table>
                    </div>
                </div>
            </form>

            ' . ws_holidays_settings_card($self_url) . '

            ' . ws_claude_settings_card($self_url, $viewer) . '
        </div>

        <div class="col-xl-4">
            <form method="post" action="' . h($self_url) . '" class="card mb-4" id="ws-defaults">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="defaults">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('Defaults')) . '</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="ws_day_hours">' . h(lang('Hours in a working day')) . '</label>
                        <input class="form-control form-control-sm" type="text" inputmode="decimal" name="day_hours" id="ws_day_hours" value="' . h(ws_settings_hours(WS_DAY_MINUTES)) . '">
                    </div>
                    <div class="mb-3">
                        <div class="form-label">' . h(lang('Working days')) . '</div>
                        ' . ws_settings_days_html('workdays', (int) WS_WORKDAYS, 'ws_default_day') . '
                        <div class="form-text"><a href="#ws-calendar">' . h(lang('The days off are kept in the Working calendar card.')) . '</a></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ws_default_task_hours">' . h(lang('A task with no estimate counts as (hours)')) . '</label>
                        <input class="form-control form-control-sm" type="text" inputmode="decimal" name="default_task_hours" id="ws_default_task_hours" value="' . h(ws_settings_hours(WS_DEFAULT_TASK_MINUTES)) . '">
                        <div class="form-text">' . h(lang('The board spreads a task\'s estimate over the working days between its start and its due date, and warns when a day goes over.')) . '</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>
                </div>
            </form>

            <div class="card">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('Who is in the team')) . '</h2></div>
                <div class="card-body small">
                    <p>' . h(lang('Every administrator, designer and manager is in the team, unless taken out of it on this screen.')) . '</p>
                    <p>' . h(lang('A basic user joins the team when the Workspace right is switched on in their user settings. The same place gives them the right to hand work to others, to see the whole team\'s board and to change these settings.')) . '</p>
                    <a class="btn btn-sm btn-outline-secondary" href="view_users.php"><i class="bi bi-person-gear me-1" aria-hidden="true"></i>' . h(lang('Users')) . '</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</main>' .
output_footer();

$liveform->remove_form();
