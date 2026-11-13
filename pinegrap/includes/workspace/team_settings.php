<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the team cards of the settings: departments (created, changed,
 * archived) and the team members' rows (job title, working day, taken out of
 * the team). The checks and the writes are here once, for the Team and
 * working hours category of the Site Settings dialog
 * (includes/settings/workspace_team.php) and for workspace_settings.php,
 * which keeps working as an address until the old screen goes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Everybody who could be in the team, the ones taken out of it included, so
 * they can be brought back.
 *
 * @return array[] person id => brief with day_minutes, workdays, excluded
 */
function ws_settings_candidates()
{
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

    return $candidates;
}

/**
 * Checks the posted team member rows and works out what to write. A row of
 * someone unknown, of a higher rank (ws_settings_may_edit_person()) or not
 * an array is skipped; an unreadable or out-of-range day is refused, and a
 * refusal stops the whole save so nothing turns into the default unasked.
 * A value equal to the default is stored as "the default", so the person
 * follows it when the default changes later. Nobody takes themselves out of
 * the team: it would lock them out of the screen they are standing on.
 * Pure but for the constants WS_DAY_MINUTES and WS_WORKDAYS.
 *
 * @param array $viewer     ['id' => int, 'role' => int]
 * @param array $candidates ws_settings_candidates()
 * @param mixed $rows       person id => ['title', 'day_hours', 'workdays' => [], 'excluded']
 * @return array ['writes' => person id => [title, day, mask, excluded], 'refused' => person id => sentence]
 */
function ws_settings_people_check($viewer, $candidates, $rows)
{
    $writes = array();
    $refused = array();

    foreach ((is_array($rows) ? $rows : array()) as $person_id => $row) {
        $person_id = (int) $person_id;

        if (!isset($candidates[$person_id]) || !is_array($row)) {
            continue;
        }

        if (!ws_settings_may_edit_person($viewer['role'], $candidates[$person_id]['role'])) {
            continue;
        }

        $day = ws_settings_minutes($row['day_hours'] ?? '', 0, 1440);

        if ($day === null) {
            $refused[$person_id] = lang(array('string' => '{var:1}: enter the hours as a number from 0 to {var:2}.', 'vars' => array(h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Hours a day'), $candidates[$person_id]['name'])))), 24)));
            continue;
        }

        if ($day === (int) WS_DAY_MINUTES) {
            $day = 0;
        }

        $mask = ws_settings_mask($row['workdays'] ?? array());

        if (($mask === (int) WS_WORKDAYS) || ($mask === 0)) {
            $mask = 0;
        }

        $writes[$person_id] = array(
            'title'    => mb_substr(trim((string) ($row['title'] ?? '')), 0, 100),
            'day'      => $day,
            'mask'     => $mask,
            'excluded' => ($person_id !== (int) $viewer['id']) && !empty($row['excluded']),
        );
    }

    return array('writes' => $writes, 'refused' => $refused);
}

/**
 * Writes the rows ws_settings_people_check() accepted.
 *
 * @param array $writes
 * @return int rows written
 */
function ws_settings_people_write($writes)
{
    foreach ($writes as $person_id => $write) {
        db("INSERT INTO ws_profiles (user_id, title, day_minutes, workdays, excluded, updated_at)
            VALUES ('" . (int) $person_id . "', '" . e($write['title']) . "', '" . (int) $write['day'] . "', '" . (int) $write['mask'] . "', '" . ($write['excluded'] ? 1 : 0) . "', '" . time() . "')
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                day_minutes = VALUES(day_minutes),
                workdays = VALUES(workdays),
                excluded = VALUES(excluded),
                updated_at = VALUES(updated_at)");
    }

    return count($writes);
}

/**
 * The team members table's rows. $name gives a field's name for a person
 * (the old screen posts people[id][x], the dialog ws_people[id][x], so its
 * names stay apart from every other category's).
 *
 * @param array    $candidates  ws_settings_candidates()
 * @param array    $departments ws_departments(true, true)
 * @param array    $viewer
 * @param callable $name        function (int $person_id, string $field): string
 * @param string   $form        a form attribute for every field, or ''
 * @return string HTML
 */
function ws_settings_people_rows_html($candidates, $departments, $viewer, $name, $form = '')
{
    $form_attr = ($form !== '') ? ' form="' . h($form) . '"' : '';
    $html = '';

    foreach ($candidates as $person) {
        $person_id = (int) $person['id'];
        $day = ($person['day_minutes'] > 0) ? $person['day_minutes'] : (int) WS_DAY_MINUTES;
        $mask = ($person['workdays'] > 0) ? $person['workdays'] : (int) WS_WORKDAYS;
        $own_departments = array();

        foreach (ws_user_department_ids($person_id) as $department_id) {
            if (isset($departments[$department_id]) && !$departments[$department_id]['archived']) {
                $own_departments[] = '<span class="badge rounded-pill" style="background:' . h($departments[$department_id]['color']) . ';color:' . pg_readable_ink_on($departments[$department_id]['color']) . '">' . h($departments[$department_id]['name']) . '</span>';
            }
        }

        // A higher rank's row is shown, not changed.
        $locked = !ws_settings_may_edit_person($viewer['role'], $person['role']);
        $locked_attr = $locked ? ' disabled' : '';

        $html .= '
            <tr' . ($person['excluded'] ? ' class="opacity-50"' : '') . '>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        ' . pg_avatar_markup(array('name' => '') + $person, '', 'pg-avatar-28') . '
                        <div>
                            <div class="fw-semibold">' . h($person['name']) . '</div>
                            <div class="small">' . implode(' ', $own_departments) . '</div>
                        </div>
                    </div>
                </td>
                <td><input class="form-control form-control-sm" type="text" name="' . h($name($person_id, 'title')) . '" maxlength="100" value="' . h($person['title']) . '"' . $locked_attr . $form_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Job title'), $person['name'])))) . '"></td>
                <td style="width:6rem"><input class="form-control form-control-sm" type="text" inputmode="decimal" name="' . h($name($person_id, 'day_hours')) . '" value="' . h(ws_settings_hours($day)) . '"' . $locked_attr . $form_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Hours a day'), $person['name'])))) . '"></td>
                <td>' . ws_settings_days_html($name($person_id, 'workdays'), $mask, 'ws_p' . $person_id, lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Working days'), $person['name']))), $locked) . '</td>
                <td class="text-center">
                    <div class="form-field d-inline-block mb-0">
                        <div class="switch"><input class="" type="checkbox" name="' . h($name($person_id, 'excluded')) . '" value="1"' . $form_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Not in the team'), $person['name'])))) . '"'
                            . ($person['excluded'] ? ' checked' : '')
                            . ((($person_id === (int) $viewer['id']) || $locked) ? ' disabled' : '') . '></div>
                    </div>
                </td>
            </tr>';
    }

    return $html;
}

/**
 * Checks a department save: the department exists when an id is given, the
 * name is not empty and no other active department has it.
 *
 * @param int   $department_id 0 for a new one
 * @param array $input         ['name', 'color']
 * @return array ['error' => string, 'current' => array|null, 'name' => string, 'color' => string]
 */
function ws_settings_department_check($department_id, $input)
{
    $department_id = (int) $department_id;
    $current = ($department_id > 0) ? ws_department($department_id) : null;
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? ''))), 0, 100);
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($input['color'] ?? '')) ? strtolower($input['color']) : '#6c757d';
    $error = '';

    if (($department_id > 0) && !$current) {
        $error = lang('That department could not be found.');
    } elseif ($name === '') {
        $error = lang('A department needs a name.');
    } elseif ((int) db_value("SELECT COUNT(*) FROM ws_departments WHERE name = '" . e($name) . "' AND id <> '" . $department_id . "' AND archived = 0") > 0) {
        $error = lang('There is already a department with this name.');
    }

    return array('error' => $error, 'current' => $current, 'name' => $name, 'color' => $color);
}

/**
 * Writes a department ws_settings_department_check() accepted: its name and
 * colour, its members, their leads and whose own department it is (only
 * people in the team; a lead or an own department counts as a member), and
 * its channel, opened when asked for and kept up with its people.
 *
 * @param array $viewer
 * @param array $checked  ws_settings_department_check()
 * @param array $input    ['members' => [], 'leads' => [], 'primary' => [], 'create_channel']
 * @param int[] $team_ids
 * @return array ['department_id' => int, 'warning' => string]
 */
function ws_settings_department_save($viewer, $checked, $input, $team_ids)
{
    $current = $checked['current'];
    $name = $checked['name'];
    $color = $checked['color'];
    $department_id = $current ? (int) $current['id'] : 0;
    $warning = '';

    if ($current) {
        db("UPDATE ws_departments SET name = '" . e($name) . "', color = '" . e($color) . "' WHERE id = '" . $department_id . "'");
    } else {
        $sort = (int) db_value("SELECT MAX(sort) FROM ws_departments") + 1;

        db("INSERT INTO ws_departments (name, color, sort, created_at)
            VALUES ('" . e($name) . "', '" . e($color) . "', '" . $sort . "', '" . time() . "')");

        $department_id = (int) mysqli_insert_id(db::$con);
    }

    $leads = array_map('intval', (array) ($input['leads'] ?? array()));
    $primary = array_map('intval', (array) ($input['primary'] ?? array()));
    $members = array_values(array_unique(array_intersect(array_merge(array_map('intval', (array) ($input['members'] ?? array())), $leads, $primary), $team_ids)));

    db("DELETE FROM ws_department_members WHERE department_id = '" . $department_id . "'");

    foreach ($members as $member_id) {
        $is_primary = in_array($member_id, $primary, true);

        // Nobody has two departments of their own: marking this one takes
        // the mark off the other.
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

    // Nobody is taken out of the channel here: a conversation someone was
    // in stays theirs to leave.
    $channel_id = $current ? (int) $current['channel_id'] : 0;
    $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

    if (!$channel && !empty($input['create_channel'])) {
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
            $warning = lang(array('string' => 'The department was saved, but its channel could not be opened: {var:1}', 'vars' => $created['error']));
        }
    } elseif ($channel && !empty($members)) {
        ws_channel_add_members($viewer, $channel, $members, false);
    }

    // The mailboxes kept in no channel that name the department as their
    // readers follow its people (mail_room.php).
    ws_mail_rooms_follow_department($department_id);

    log_activity(lang(array('string' => 'workspace department ({var:1}) was saved', 'vars' => $name)), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('department_id' => $department_id, 'warning' => $warning);
}

/**
 * Moves a department to the archive, or brings it back unless another active
 * department has its name.
 *
 * @param int $department_id
 * @return array ['error' => string, 'notice' => string]; both empty for an unknown department
 */
function ws_settings_department_archive($department_id)
{
    $department = ws_department((int) $department_id);

    if (!$department) {
        return array('error' => '', 'notice' => '');
    }

    $archive = !$department['archived'];

    // Saving refuses a second active department of one name; bringing one
    // back must not make that pair either.
    if (!$archive && ((int) db_value("SELECT COUNT(*) FROM ws_departments WHERE name = '" . e($department['name']) . "' AND id <> '" . (int) $department['id'] . "' AND archived = 0") > 0)) {
        return array('error' => lang('There is already a department with this name.') . ' ' . lang('Rename one of them first.'), 'notice' => '');
    }

    db("UPDATE ws_departments SET archived = '" . ($archive ? 1 : 0) . "' WHERE id = '" . (int) $department['id'] . "'");

    log_activity($archive
        ? lang(array('string' => 'workspace department ({var:1}) was archived', 'vars' => $department['name']))
        : lang(array('string' => 'workspace department ({var:1}) was brought back', 'vars' => $department['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('error' => '', 'notice' => $archive
        ? lang(array('string' => 'The department was archived: {var:1}. Its tasks keep it; new ones cannot choose it.', 'vars' => h($department['name'])))
        : lang(array('string' => 'The department is back: {var:1}.', 'vars' => h($department['name']))));
}

/**
 * The fields a department is created or changed with: name, colour, who is
 * in it, its leads, whose own department it is and, while it has none, its
 * channel. $name gives a field's name (the old screen posts name, members[];
 * the dialog ws_dept[id][name], ws_dept[id][members][]).
 *
 * @param array|null $department
 * @param int[]      $primary_ids
 * @param array|null $channel
 * @param callable   $name function (string $field): string
 * @param string     $form a form attribute for every field, or ''
 * @return string HTML
 */
function ws_settings_department_fields($department, $primary_ids, $channel, $name, $form = '')
{
    $id = $department ? (int) $department['id'] : 0;
    $members = $department ? $department['members'] : array();
    $leads = $department ? $department['leads'] : array();
    $prefix = 'ws_d' . $id;
    $form_attr = ($form !== '') ? ' form="' . h($form) . '"' : '';

    $rows = '';

    foreach (ws_team_members() as $person) {
        $person_id = (int) $person['id'];

        $rows .= '
            <tr>
                <td>
                    <div class="form-field mb-0">
                        <input class="check" type="checkbox" name="' . h($name('members') . '[]') . '" value="' . $person_id . '" id="' . $prefix . '_m' . $person_id . '"' . $form_attr . (in_array($person_id, $members, true) ? ' checked' : '') . '>
                        <label class="" for="' . $prefix . '_m' . $person_id . '">' . h($person['name']) . ($person['title'] !== '' ? ' <span class="fg-secondary small">' . h($person['title']) . '</span>' : '') . '</label>
                    </div>
                </td>
                <td class="text-center"><input class="check" type="checkbox" name="' . h($name('leads') . '[]') . '" value="' . $person_id . '"' . $form_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Team lead'), $person['name'])))) . '"' . (in_array($person_id, $leads, true) ? ' checked' : '') . '></td>
                <td class="text-center"><input class="check" type="checkbox" name="' . h($name('primary') . '[]') . '" value="' . $person_id . '"' . $form_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Own department'), $person['name'])))) . '"' . (in_array($person_id, $primary_ids, true) ? ' checked' : '') . '></td>
            </tr>';
    }

    return '
            <div class="row g-3 mb-5">
                <div class="col">
                    <label class="form-label" for="' . $prefix . '_name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="' . h($name('name')) . '" id="' . $prefix . '_name" maxlength="100"' . (($form === '') ? ' required' : '') . $form_attr . ' value="' . h($department ? $department['name'] : '') . '">
                </div>
                <div class="col-auto">
                    <label class="form-label" for="' . $prefix . '_color">' . h(lang('Colour')) . '</label>
                    <input class="form-control form-control-sm form-control-color" type="color" name="' . h($name('color')) . '" id="' . $prefix . '_color"' . $form_attr . ' value="' . h($department ? $department['color'] : '#6c757d') . '">
                </div>
            </div>
            <div class="table-responsive ws-dept-people">
                <table class="table table-sm align-middle mb-3">
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
            <div class="form-text mb-3">' . h(lang('A lead sees the department\'s board and may hand its work around and overrule a clash with leave. Somebody in several departments is counted in their own one first.')) . '</div>
            ' . ($channel ? '' : '
            <div class="form-field mb-5">
                <div class="switch"><input class="" type="checkbox" name="' . h($name('create_channel')) . '" value="1" id="' . $prefix . '_channel"' . $form_attr . ($department ? '' : ' checked') . '></div>
                <label class="" for="' . $prefix . '_channel">' . h(lang('Open a channel for the department')) . '</label>
            </div>');
}

/**
 * One department's line in a list: swatch, name, people, leads, channel, and
 * the actions the caller draws.
 *
 * @param array  $department ws_departments(true, true) entry
 * @param array  $people_by_id
 * @param array|null $channel
 * @param string $actions HTML
 * @return string HTML
 */
function ws_settings_department_row_html($department, $people_by_id, $channel, $actions)
{
    $lead_names = array();

    foreach ($department['leads'] as $member_id) {
        $lead_names[] = $people_by_id[$member_id]['name'] ?? ws_person_name($member_id);
    }

    return '
        <div class="ws-dept-row' . ($department['archived'] ? ' opacity-50' : '') . '">
            <span class="ws-dept-swatch" style="background:' . h($department['color']) . '"></span>
            <div class="ws-grow">
                <div class="fw-semibold">' . h($department['name']) . ($department['archived'] ? ' <span class="badge theme-secondary ms-1">' . h(lang('In the archive')) . '</span>' : '') . '</div>
                <div class="small fg-secondary">'
                    . h(lang(array('string' => '{var:1} people', 'vars' => count($department['members']))))
                    . (!empty($lead_names) ? ' · ' . h(lang(array('string' => 'Lead: {var:1}', 'vars' => implode(', ', $lead_names)))) : '')
                    . ($channel ? ' · <a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h($channel['name']) . '</a>' : '') . '
                </div>
            </div>
            ' . $actions . '
        </div>';
}

/**
 * The people whose own department this is.
 *
 * @param int $department_id
 * @return int[]
 */
function ws_settings_department_primary_ids($department_id)
{
    return array_map('intval', (array) db_values("SELECT user_id FROM ws_department_members WHERE department_id = '" . (int) $department_id . "' AND is_primary = 1"));
}

/**
 * A department's drawer in the Team and working hours category of the Site
 * Settings: its fields and its Save button, which join the settings form by
 * its id (the drawer sits outside it). The button is a named submit button
 * (ws_department_save = the department id, 0 for a new one): the pane is
 * saved first, then includes/settings/workspace_team.save.php writes this
 * department. Field names carry the id, so every drawer's stay apart.
 *
 * @param array|null $department
 * @param int[]      $primary_ids
 * @param array|null $channel
 * @param string     $form      the settings form's id
 * @param bool       $in_dialog
 * @return string HTML (pg_settings_drawer())
 */
function ws_settings_department_drawer($department, $primary_ids, $channel, $form, $in_dialog)
{
    $id = $department ? (int) $department['id'] : 0;

    $body = ws_settings_department_fields($department, $primary_ids, $channel, function ($field) use ($id) {
        return 'ws_dept[' . $id . '][' . $field . ']';
    }, $form) . '
            <button type="submit" name="ws_department_save" value="' . $id . '" form="' . h($form) . '" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h($department ? lang('Save') : lang('Create the department')) . '</button>';

    $title = $department ? h($department['name']) : h(lang('New department'));

    return pg_settings_drawer('pgset-ws-dept-' . $id, $title, $body, $in_dialog);
}
