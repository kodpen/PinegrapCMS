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

// The Claude and Pinegrap AI cards make an application key, the Claude card
// also rotates its secret (includes/api/keys.php).
define('PG_API_PANEL', true);

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!$viewer['settings']) {
    log_activity(lang('access denied to the workspace settings'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('liveform.class.php');
$liveform = new liveform('workspace_settings');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_settings.php';

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

    // Every field the cards post holds one value, except the ones drawn as
    // lists below; a person's row carries one value per field but its days.
    // One posted as a list (name[]=x) saved a department called "Array", and
    // department_id[]=x or template_id[]=x was read as record 1. Nothing is
    // written, so every setting stays as it was.
    // tests/workspace_settings_lists_test.php checks every list a card draws
    // (a field named x[]) against this one.
    $ws_settings_lists = pg_request_unexpected_lists($_POST, array(
        'workdays'      => 1,
        'members'       => 1,
        'leads'         => 1,
        'primary'       => 1,
        'people'        => 3,
        'mcp_tool'      => 1,
        'route_match'   => 1,
        'route_channel' => 1,
        'github_events' => 1,
    ));

    foreach ((is_array($_POST['people'] ?? null) ? $_POST['people'] : array()) as $person_id => $row) {
        foreach (pg_request_unexpected_lists($row, array('workdays' => 1)) as $name) {
            $ws_settings_lists[] = 'people[' . $person_id . '][' . $name . ']';
        }
    }

    if ($ws_settings_lists) {
        $liveform->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $ws_settings_lists)))));
        go($self_url);
    }

    $action = (string) ($_POST['ws_action'] ?? '');
    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $anchor = '';

    switch ($action) {

        case 'defaults':
            $anchor = '#ws-defaults';

            // The Defaults card is in the Site Settings dialog now (Team and
            // working hours); a form still open on this screen saves the same
            // way, through the same checks.
            $refused = ws_settings_defaults_save($_POST['day_hours'] ?? '', $_POST['workdays'] ?? array(), $_POST['default_task_hours'] ?? '');

            foreach ($refused as $sentence) {
                $liveform->add_error($sentence);
            }

            if ($refused) {
                break;
            }

            log_activity(lang('the workspace defaults were changed'), $username);
            $liveform->add_notice(lang('The defaults were saved.'));
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
            // in the team are taken. A lead or somebody whose own department
            // this is belongs to it, ticked as a member or not.
            $leads = array_map('intval', (array) ($_POST['leads'] ?? array()));
            $primary = array_map('intval', (array) ($_POST['primary'] ?? array()));
            $members = array_values(array_unique(array_intersect(array_merge(array_map('intval', (array) ($_POST['members'] ?? array())), $leads, $primary), $team_ids)));

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
            $liveform->add_notice(lang(array('string' => 'The department was saved: {var:1}.', 'vars' => h($name))));
            $anchor = '#ws-departments';
            break;

        case 'department_archive':
            $department = ws_department((int) ($_POST['department_id'] ?? 0));

            if ($department) {
                $archive = !$department['archived'];

                // Saving refuses a second active department of one name; bringing
                // one back must not make that pair either.
                if (!$archive && ((int) db_value("SELECT COUNT(*) FROM ws_departments WHERE name = '" . e($department['name']) . "' AND id <> '" . (int) $department['id'] . "' AND archived = 0") > 0)) {
                    $liveform->add_error(lang('There is already a department with this name.') . ' ' . lang('Rename one of them first.'));
                    $anchor = '#ws-departments';
                    break;
                }

                db("UPDATE ws_departments SET archived = '" . ($archive ? 1 : 0) . "' WHERE id = '" . (int) $department['id'] . "'");
                log_activity($archive
                    ? lang(array('string' => 'workspace department ({var:1}) was archived', 'vars' => $department['name']))
                    : lang(array('string' => 'workspace department ({var:1}) was brought back', 'vars' => $department['name'])), $username);
                $liveform->add_notice($archive
                    ? lang(array('string' => 'The department was archived: {var:1}. Its tasks keep it; new ones cannot choose it.', 'vars' => h($department['name'])))
                    : lang(array('string' => 'The department is back: {var:1}.', 'vars' => h($department['name']))));
            }

            $anchor = '#ws-departments';
            break;

        case 'people':
            $rows = (array) ($_POST['people'] ?? array());
            $writes = array();
            $anchor = '#ws-people';

            foreach ($rows as $person_id => $row) {
                $person_id = (int) $person_id;

                if (!isset($candidates[$person_id]) || !is_array($row)) {
                    continue;
                }

                // A row of a higher rank is drawn read-only; one posted anyway
                // is left as it is.
                if (!ws_settings_may_edit_person($viewer['role'], $candidates[$person_id]['role'])) {
                    continue;
                }

                $title = mb_substr(trim((string) ($row['title'] ?? '')), 0, 100);
                $day = ws_settings_minutes($row['day_hours'] ?? '', 0, 1440);
                $mask = ws_settings_mask($row['workdays'] ?? array());

                // Unreadable or out-of-range hours stop the whole save, so
                // nothing is turned into the default unasked.
                if ($day === null) {
                    $liveform->add_error(lang(array('string' => '{var:1}: enter the hours as a number from 0 to {var:2}.', 'vars' => array(h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Hours a day'), $candidates[$person_id]['name'])))), 24))));
                    continue;
                }

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

                $writes[$person_id] = "('" . $person_id . "', '" . e($title) . "', '" . $day . "', '" . $mask . "', '" . ($excluded ? 1 : 0) . "', '" . time() . "')";
            }

            if ($liveform->check_form_errors()) {
                break;
            }

            foreach ($writes as $values) {
                db("INSERT INTO ws_profiles (user_id, title, day_minutes, workdays, excluded, updated_at)
                    VALUES " . $values . "
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        day_minutes = VALUES(day_minutes),
                        workdays = VALUES(workdays),
                        excluded = VALUES(excluded),
                        updated_at = VALUES(updated_at)");
            }

            $saved = count($writes);

            log_activity(lang('the workspace people settings were changed'), $username);
            $liveform->add_notice(lang(array('string' => 'Saved for {var:1} people.', 'vars' => $saved)));
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

        // Channel templates (includes/workspace/templates.php): archived or
        // brought back; they are written on their own screen.
        case 'template_archive':
            ws_templates_settings_post($viewer, $liveform);
            $anchor = '#ws-templates';
            break;

        // Asking Claude in the channels (includes/workspace/claude.php).
        case 'claude':
        case 'claude_test':
        case 'claude_key_create':
        case 'claude_key_reset':
            ws_claude_settings_post($viewer, $action, $liveform, $user);
            $anchor = '#ws-claude';
            break;

        // The agent registry (includes/workspace/agents_settings.php).
        case 'agent_save':
        case 'agent_delete':
        case 'agent_test':
            ws_agents_settings_post($viewer, $action, $liveform);
            $anchor = '#ws-agents';
            break;

        // An agent's request from its activity (includes/workspace/agent_activity.php).
        case 'agent_request_cancel':
        case 'agent_request_retry':
        case 'agent_request_knock':
            ws_agent_activity_post($viewer, $action, $liveform);
            $anchor = '#ws-agents';
            break;

        // Bulk changes the assistants propose (includes/workspace/bulk.php).
        case 'bulk':
            ws_bulk_settings_post($viewer, $liveform);
            $anchor = '#ws-bulk';
            break;

        // Pinegrap AI in the channels (includes/workspace/ai.php).
        case 'ai':
        case 'ai_test':
        case 'ai_key_create':
            if (function_exists('ws_ai_settings_post')) {
                ws_ai_settings_post($viewer, $action, $liveform, $user);
            }

            $anchor = '#ws-ai';
            break;

        // Transcripts of voice messages (includes/workspace/voice.php).
        case 'voice':
            ws_voice_settings_post($viewer, $liveform);
            $anchor = '#ws-voice';
            break;

        // Mailboxes, their connection test and an .eml brought in by hand
        // (includes/workspace/mail_settings.php).
        case 'mail_box_save':
        case 'mail_eml':
        case 'mail_test':
            ws_mail_settings_post($viewer, $action, $liveform);
            $anchor = '#ws-mail';
            break;

        // Other services' MCP servers under an alias
        // (includes/workspace/mcp.php).
        case 'mcp_save':
        case 'mcp_refresh':
        case 'mcp_toggle':
        case 'mcp_delete':
        case 'mcp_tools':
        case 'mcp_connect':
        case 'mcp_disconnect':
        case 'mcp_catalogue_add':
        case 'mcp_probe':
        case 'mcp_connect_me':
        case 'mcp_disconnect_me':
            $anchor = ws_mcp_settings_post($viewer, $action, $liveform);
            break;

        // GitHub repositories linked to channels
        // (includes/workspace/github_links.php).
        case 'github_link_save':
        case 'github_link_remove':
            ws_github_links_settings_post($viewer, $action, $liveform);
            $anchor = '#ws-github';
            break;
    }

    // A refused card form gets what was typed back, and the page shows the
    // error inside that form (ws_settings_refused_script()).
    if ($liveform->check_form_errors()) {
        $_SESSION['software']['ws_settings_refused'] = ws_settings_refused_fields($_POST);
    }

    go($self_url . $anchor);
}

// Back from connecting an MCP server's sign-in (connect.php).
ws_mcp_settings_returned($viewer, $liveform);

// The key and secret Create or Reset secret on the Claude card handed over,
// if this request follows one. Read once and removed at once: the window that
// shows them appears exactly once, and the page carrying the secret is not
// kept by the browser or a proxy. Nothing has been sent yet, so the header
// still goes out.
$assistant_credentials = $_SESSION['software']['ws_assistant_credentials'] ?? null;
unset($_SESSION['software']['ws_assistant_credentials']);

if ($assistant_credentials !== null) {
    header('Cache-Control: no-store');
}

// What a refused card form posted, read once.
$refused_fields = $_SESSION['software']['ws_settings_refused'] ?? null;
unset($_SESSION['software']['ws_settings_refused']);

/**
 * The page's own script: a refused card form is filled again with what was
 * typed (ws_settings_refused_fields()), opened if it sits in a closed part,
 * and the error moves from the top of the page into that form, or into the
 * card the page was sent back to; and a department row's Team lead or Own
 * department tick ticks In the department too, as the save counts them.
 *
 * @param array|null $fields
 * @return string HTML
 */
function ws_settings_refused_script($fields)
{
    $json = is_array($fields) ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null';

    $script = <<<'JS'
(function () {
    var holder = document.getElementById('ws-settings-refused');
    var fields = null;
    var form = null;

    try {
        fields = holder ? JSON.parse(holder.textContent) : null;
    } catch (error) {
        fields = null;
    }

    // The form the refused save came from: the same action and, for a
    // department, mailbox or calendar, the same record. A one-button row
    // form has nothing to fill.
    if (fields && fields.ws_action) {
        document.querySelectorAll('#content form').forEach(function (candidate) {
            var action = candidate.querySelector('input[type="hidden"][name="ws_action"]');

            if (form || !action || (action.value !== fields.ws_action) || candidate.classList.contains('d-inline')) {
                return;
            }

            var same = ['box_id', 'department_id', 'feed_id'].every(function (key) {
                var input = candidate.querySelector('input[type="hidden"][name="' + key + '"]');

                return !input || !(key in fields) || (input.value === fields[key]);
            });

            if (same) {
                form = candidate;
            }
        });
    }

    if (form) {
        var seen = {};

        Array.prototype.forEach.call(form.elements, function (field) {
            var name = field.name;
            var list = /\[\]$/.test(name || '');
            var value = fields[name];

            if (!name || field.disabled || /^(hidden|file|password|submit|button|reset)$/.test(field.type)) {
                return;
            }

            // An unticked box is not posted at all.
            if ((field.type === 'checkbox') || (field.type === 'radio')) {
                field.checked = list ? (Array.isArray(value) && (value.indexOf(field.value) !== -1)) : (value === field.value);
                return;
            }

            if (list) {
                seen[name] = seen[name] || 0;
                value = Array.isArray(value) ? value[seen[name]] : undefined;
                seen[name]++;
            }

            if (typeof value === 'string') {
                field.value = value;

                // Fields that hang on a choice follow it (the mailbox form).
                if (field.tagName === 'SELECT') {
                    field.dispatchEvent(new Event('change', {bubbles: true}));
                }
            }
        });

        var collapse = form.closest('.collapse');

        if (collapse) {
            collapse.classList.add('show');

            document.querySelectorAll('[data-bs-target="#' + collapse.id + '"]').forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            });
        }
    }

    // The error, printed at the top of the page, goes where the save was
    // made: the browser comes back scrolled to that card.
    var alert = document.querySelector('#content .software_error');
    var place = form;

    if (!place && (location.hash.length > 1)) {
        place = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    }

    if (alert && place) {
        var body = place.classList.contains('card') ? place.querySelector('.card-body') : null;

        if (body) {
            body.insertBefore(alert, body.firstChild);
        } else if (place === form) {
            form.insertBefore(alert, form.firstChild);
        } else {
            place.parentNode.insertBefore(alert, place);
        }

        window.addEventListener('load', function () {
            alert.scrollIntoView({block: 'center'});
        });
    }

    document.addEventListener('change', function (event) {
        var box = event.target;
        var row = (box.matches && box.matches('.ws-dept-form input[type="checkbox"]')) ? box.closest('tr') : null;
        var member = row ? row.querySelector('input[name="members[]"]') : null;

        if (!member) {
            return;
        }

        if (box !== member) {
            member.checked = member.checked || box.checked;
            return;
        }

        if (!member.checked) {
            row.querySelectorAll('input[name="leads[]"], input[name="primary[]"]').forEach(function (other) {
                other.checked = false;
            });
        }
    });
})();
JS;

    return '<script type="application/json" id="ws-settings-refused">' . $json . '</script>' . "\n<script>\n" . $script . "\n</script>";
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

    $archive_label = $department['archived'] ? lang('Bring it back') : lang('Move to the archive');

    $output_departments .= '
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
            <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="collapse" data-bs-target="#ws-dept-' . $id . '" aria-expanded="false" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), $department['name'])))) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            <form method="post" action="' . h($self_url) . '" class="d-inline">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="department_archive">
                <input type="hidden" name="department_id" value="' . $id . '">
                <button type="submit" class="btn btn-sm btn-text theme-secondary" title="' . h($archive_label) . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array($archive_label, $department['name'])))) . '"><i class="bi ' . ($department['archived'] ? 'bi-arrow-counterclockwise' : 'bi-archive') . '" aria-hidden="true"></i></button>
            </form>
        </div>
        <div class="collapse" id="ws-dept-' . $id . '">
            ' . ws_settings_department_form($self_url, $department, $primary_ids, $channel) . '
        </div>';
}

if ($output_departments === '') {
    $output_departments = '<div class="ws-empty py-5"><i class="bi bi-diagram-3" aria-hidden="true"></i>' . h(lang('No departments yet. A department groups people so work can be handed to it and its lead sees its board.')) . '</div>';
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
                    <div class="form-field mb-0">
                        <input class="check" type="checkbox" name="members[]" value="' . $person_id . '" id="' . $prefix . '_m' . $person_id . '"' . ($in ? ' checked' : '') . '>
                        <label class="" for="' . $prefix . '_m' . $person_id . '">' . h($person['name']) . ($person['title'] !== '' ? ' <span class="fg-secondary small">' . h($person['title']) . '</span>' : '') . '</label>
                    </div>
                </td>
                <td class="text-center"><input class="check" type="checkbox" name="leads[]" value="' . $person_id . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Team lead'), $person['name'])))) . '"' . (in_array($person_id, $leads, true) ? ' checked' : '') . '></td>
                <td class="text-center"><input class="check" type="checkbox" name="primary[]" value="' . $person_id . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Own department'), $person['name'])))) . '"' . (in_array($person_id, $primary_ids, true) ? ' checked' : '') . '></td>
            </tr>';
    }

    return '
        <form method="post" action="' . h($self_url) . '" class="ws-dept-form">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="department">
            <input type="hidden" name="department_id" value="' . $id . '">
            <div class="row g-3 mb-5">
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
                <div class="switch"><input class="" type="checkbox" name="create_channel" value="1" id="' . $prefix . '_channel"' . ($department ? '' : ' checked') . '></div>
                <label class="" for="' . $prefix . '_channel">' . h(lang('Open a channel for the department')) . '</label>
            </div>') . '
            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h($department ? lang('Save') : lang('Create the department')) . '</button>
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
            $own_departments[] = '<span class="badge rounded-pill" style="background:' . h($departments[$department_id]['color']) . ';color:' . pg_readable_ink_on($departments[$department_id]['color']) . '">' . h($departments[$department_id]['name']) . '</span>';
        }
    }

    // A higher rank's row is shown, not changed (ws_settings_may_edit_person()).
    $locked = !ws_settings_may_edit_person($viewer['role'], $person['role']);
    $locked_attr = $locked ? ' disabled' : '';

    $output_people .= '
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
            <td><input class="form-control form-control-sm" type="text" name="people[' . $person_id . '][title]" maxlength="100" value="' . h($person['title']) . '"' . $locked_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Job title'), $person['name'])))) . '"></td>
            <td style="width:6rem"><input class="form-control form-control-sm" type="text" inputmode="decimal" name="people[' . $person_id . '][day_hours]" value="' . h(ws_settings_hours($day)) . '"' . $locked_attr . ' aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Hours a day'), $person['name'])))) . '"></td>
            <td>' . ws_settings_days_html('people[' . $person_id . '][workdays]', $mask, 'ws_p' . $person_id, lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Working days'), $person['name']))), $locked) . '</td>
            <td class="text-center">
                <div class="form-field d-inline-block mb-0">
                    <div class="switch"><input class="" type="checkbox" name="people[' . $person_id . '][excluded]" value="1" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Not in the team'), $person['name'])))) . '"'
                        . ($person['excluded'] ? ' checked' : '')
                        . ((($person_id === (int) $viewer['id']) || $locked) ? ' disabled' : '') . '></div>
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
<div class="ws-shell-main">
    ' . ws_screen_messages($liveform) . '
    ' . pg_screen_toolbar(array(
        // Opens the new department form at the top of the Departments card.
        'primary'   => '<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-bs-toggle="collapse" data-bs-target="#ws-dept-new" aria-controls="ws-dept-new" aria-expanded="false"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New department')) . '</button>',
        'secondary' => array(
            array('label' => lang('Channels'), 'url' => 'workspace.php', 'icon' => 'bi-chat-square-text'),
            array('label' => lang('Planning Board'), 'url' => 'workspace_board.php', 'icon' => 'bi-calendar-week'),
        ),
    ), array('class' => 'mb-5')) . '
    <div class="row g-7">
        <div class="xl:col-8">
            <div class="card mb-7" id="ws-departments">
                <div class="card-header d-flex align-items-center gap-3">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>' . h(lang('Departments')) . '</h2>
                </div>
                <div class="card-body">
                    <div class="collapse mb-5" id="ws-dept-new">
                        ' . ws_settings_department_form($self_url, null, array(), null) . '
                    </div>
                    ' . $output_departments . '
                </div>
            </div>

            <form method="post" action="' . h($self_url) . '" class="card" id="ws-people">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="people">
                <div class="card-header d-flex align-items-center gap-3">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-people me-1" aria-hidden="true"></i>' . h(lang('Team members')) . '</h2>
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>
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

            ' . ws_templates_settings_card($self_url, $viewer) . '

            ' . ws_holidays_settings_card($self_url) . '

            ' . ws_claude_settings_card($self_url, $viewer, $assistant_credentials) . '

            ' . ws_agents_settings_card($self_url, $viewer) . '

            ' . (function_exists('ws_ai_settings_card') ? ws_ai_settings_card($self_url, $viewer) : '') . '

            ' . ws_voice_settings_card($self_url, $viewer) . '

            ' . ws_mail_settings_card($self_url, $viewer) . '

            ' . ws_mcp_settings_card($self_url, $viewer, $liveform) . '

            ' . ws_github_links_settings_card($self_url, $viewer) . '
        </div>

        <div class="xl:col-4">
            <div class="card mb-7" id="ws-defaults">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('Defaults')) . '</h2></div>
                <div class="card-body small">
                    <p>' . h(lang('The hours of a working day, the working week and the hours of a task with no estimate are kept in the Site Settings now.')) . '</p>
                    <a class="btn btn-sm btn-outline theme-secondary" href="' . h(pg_settings_link('workspace_team', 'pgset-ws-defaults')) . '"><i class="bi bi-gear me-1" aria-hidden="true"></i>' . h(lang('Open the defaults')) . '</a>
                </div>
            </div>

            ' . ws_bulk_settings_card($self_url, $viewer) . '

            <div class="card">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('Who is in the team')) . '</h2></div>
                <div class="card-body small">
                    <p>' . h(lang('Every administrator, designer and manager is in the team, unless taken out of it on this screen.')) . '</p>
                    <p>' . h(lang('A basic user joins the team when the Workspace right is switched on in their user settings. The same place gives them the right to hand work to others, to see the whole team\'s board and to change these settings.')) . '</p>
                    <a class="btn btn-sm btn-outline theme-secondary" href="view_users.php"><i class="bi bi-person-gear me-1" aria-hidden="true"></i>' . h(lang('Users')) . '</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</main>' .
ws_settings_refused_script($refused_fields) .
output_footer();

$liveform->remove_form();
