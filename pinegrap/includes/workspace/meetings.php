<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - meetings on the plan: the video call of a plan item, its
 * meeting note, and the decisions and actions brought from that note into a
 * channel.
 *
 * A plan item carries the address of its call (ws_events.meeting_url): a
 * Jitsi room the form makes up, or a Google Meet, Teams or Zoom link pasted
 * in. The site never talks to those services; the address is only opened in
 * a new tab, from the item's Join button, the calendar and the board. A timed
 * item with a link tells its people WS_MEETING_REMIND_MINUTES before it
 * starts (reminders.php).
 *
 * The meeting note is an ordinary note (notes.php) written from a template:
 * a line tagging the item, its time and link, then four titled blocks -
 * attendees (a checklist, ticked for who came), agenda, decisions and action
 * items (tables). It belongs to whoever opened it and is shared like any
 * other note; it is found again by the item's tag in its body. From a note
 * with those blocks the decisions and the actions can be brought into a
 * channel: each decision as a decision message (kept on the Decisions tab),
 * each action as a task read the way /task reads one (people with @, a day,
 * ~estimate, !priority) - shown first, written only on confirmation. A row
 * that became a task gets the task's tag in the note, so it is not brought
 * twice; a decision already in that channel word for word is left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most decisions and actions one bringing over writes (the channel's rate limit is 20 a minute). */
define('WS_MEETING_TRANSFER_MAX', 15);

/**
 * A meeting link as it is kept: a web address of up to 500 characters, '' for
 * none, or false when it is not one. Whatever service it is - a Jitsi room,
 * Google Meet, Teams, Zoom - it is only ever opened in a new tab.
 *
 * @param mixed $value
 * @return string|false
 */
function ws_meeting_url_clean($value)
{
    $url = trim((string) $value);

    if ($url === '') {
        return '';
    }

    if ((mb_strlen($url) > 500) || !preg_match('#^https?://[^\s<>"]+$#i', $url) || (filter_var($url, FILTER_VALIDATE_URL) === false)) {
        return false;
    }

    return $url;
}

/**
 * Writes a plan item's meeting link and arms its notice:
 * WS_MEETING_REMIND_MINUTES before a timed item with a link starts. A changed
 * time or link arms it again; an all-day item, or one without a link, has none.
 *
 * @param int        $event_id
 * @param array|null $current   the row before the save, null for a new item
 * @param string     $url
 * @param int        $starts_at 0 for an all-day item
 */
function ws_meeting_link_set($event_id, $current, $url, $starts_at)
{
    $remind_at = (($url !== '') && ((int) $starts_at > 0)) ? max(1, (int) $starts_at - WS_MEETING_REMIND_MINUTES * 60) : 0;
    $rearm = !is_array($current) || ((int) $current['remind_at'] !== $remind_at);

    db("UPDATE ws_events SET meeting_url = '" . e($url) . "', remind_at = '" . $remind_at . "'"
        . ($rearm ? ", reminded_at = 0" : '') . "
        WHERE id = '" . (int) $event_id . "'");
}

/**
 * The people a plan item is about who are still on the team: those named,
 * the department's or everybody's.
 *
 * @param array $event ws_events row
 * @return int[]
 */
function ws_event_people_ids($event)
{
    if ($event['scope'] === 'company') {
        return ws_team_ids();
    }

    $ids = array();
    $named = ($event['scope'] === 'department')
        ? db_values("SELECT user_id FROM ws_department_members WHERE department_id = '" . (int) $event['department_id'] . "'")
        : db_values("SELECT user_id FROM ws_event_people WHERE event_id = '" . (int) $event['id'] . "'");

    foreach ((array) $named as $user_id) {
        if (ws_is_team_member($user_id)) {
            $ids[(int) $user_id] = (int) $user_id;
        }
    }

    return array_values($ids);
}

/* ---------------------------------------------------------------------------
   The meeting note
   --------------------------------------------------------------------------- */

/**
 * The titles of the blocks a meeting note is read by, in the screen's
 * language and in English (a note written before the language changed).
 *
 * @param string $which decisions | actions
 * @return string[] lower case
 */
function ws_meeting_block_titles($which)
{
    $key = ($which === 'decisions') ? 'Decisions' : 'Action items';

    return array_values(array_unique(array(ws_lower(lang($key)), ws_lower($key))));
}

/**
 * The body of a new meeting note for a plan item.
 *
 * @param array $event ws_events row
 * @return string
 */
function ws_meeting_note_body($event)
{
    $all_day = ((int) $event['all_day'] === 1);
    $when = date('d.m.Y', (int) $event['starts_at']) . ($all_day ? '' : ' ' . date('H:i', (int) $event['starts_at']) . '–' . date('H:i', (int) $event['ends_at']));
    $lines = array('<#plan:' . (int) $event['id'] . '> · ' . $when);

    if ((string) ($event['meeting_url'] ?? '') !== '') {
        $lines[] = lang('Join') . ': ' . $event['meeting_url'];
    }

    // Who was asked, ticked for who came. A whole department is its tag; the
    // whole company is nobody by name.
    $attendees = array();

    if ($event['scope'] === 'department') {
        $attendees[] = '- [ ] <@dept:' . (int) $event['department_id'] . '>';
    } elseif ($event['scope'] === 'people') {
        foreach ((array) db_values("SELECT user_id FROM ws_event_people WHERE event_id = '" . (int) $event['id'] . "' ORDER BY user_id") as $user_id) {
            $attendees[] = '- [ ] <@user:' . (int) $user_id . '>';
        }
    }

    if (!empty($attendees)) {
        $lines[] = '';
        $lines[] = '::: ' . lang('Attendees');
        $lines = array_merge($lines, $attendees);
    }

    $lines[] = '';
    $lines[] = ws_template_note_table(lang('Agenda'), array(lang('Topic'), lang('Note')));
    $lines[] = '';
    $lines[] = ws_template_note_table(lang('Decisions'), array(lang('Decision')));
    $lines[] = '';
    $lines[] = ws_template_note_table(lang('Action items'), array(lang('Action item'), lang('Who'), lang('Due')));

    return implode("\n", $lines);
}

/**
 * The meeting note of a plan item for this person: the newest note they can
 * read that tags the item - their own first - or a new one written from the
 * template.
 *
 * @param array $viewer
 * @param int   $event_id
 * @return array ok, error, note_id, created
 */
function ws_meeting_note_open($viewer, $event_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'note_id' => 0, 'created' => false);
    };

    if (!$viewer['member'] || !function_exists('ws_notes_ready') || !ws_notes_ready()) {
        return $fail(lang('Access denied.'));
    }

    $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) $event_id . "'");

    if (!is_array($event)) {
        return $fail(lang('Invalid request.'));
    }

    $tag = '<#plan:' . (int) $event['id'] . '>';
    $found = 0;

    foreach ((array) db_items("SELECT * FROM ws_notes WHERE body LIKE '%" . e(escape_like($tag)) . "%' ORDER BY (user_id = '" . (int) $viewer['id'] . "') DESC, updated_at DESC LIMIT 20") as $note) {
        if (ws_note_access($viewer, $note) !== '') {
            $found = (int) $note['id'];
            break;
        }
    }

    if ($found > 0) {
        return array('ok' => true, 'error' => '', 'note_id' => $found, 'created' => false);
    }

    $saved = ws_note_save($viewer, array(
        'title' => mb_substr($event['title'] . ' · ' . date('d.m.Y', (int) $event['starts_at']), 0, 200),
        'body'  => ws_meeting_note_body($event),
    ));

    return $saved['ok']
        ? array('ok' => true, 'error' => '', 'note_id' => (int) $saved['note_id'], 'created' => true)
        : $fail($saved['error']);
}

/**
 * The rows of a titled block of a note: a table's rows under its header, or a
 * checklist's items, with the line each sits on.
 *
 * @param string[] $lines
 * @param string[] $titles lower case
 * @return array[] line, cells (a checklist item is one cell)
 */
function ws_meeting_block_rows($lines, $titles)
{
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $title = ws_block_title_line($lines[$i]);

        if (($title === null) || !in_array(ws_lower($title), $titles, true) || !ws_block_starts($lines, $i + 1)) {
            continue;
        }

        $rows = array();

        if (preg_match('/^\s*\|.*\|\s*$/', $lines[$i + 1])) {
            for ($j = $i + 3; ($j < $count) && preg_match('/^\s*\|.*\|\s*$/', $lines[$j]); $j++) {
                $rows[] = array('line' => $j, 'cells' => ws_table_cells($lines[$j]), 'table' => true);
            }
        } else {
            for ($j = $i + 1; ($j < $count) && preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $lines[$j], $match); $j++) {
                $rows[] = array('line' => $j, 'cells' => array(trim($match[2])), 'table' => false);
            }
        }

        return $rows;
    }

    return array();
}

/**
 * Reads a meeting note: its decisions and its action items, each as it would
 * be brought into a channel, and whether it already was.
 *
 * @param array      $viewer
 * @param array      $note
 * @param array|null $channel the channel it would go to (for decisions already there)
 * @return array decisions (index, text, plain, done), actions (index, line,
 *               parsed task, title, people, due, error, done)
 */
function ws_meeting_note_read($viewer, $note, $channel)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $note['body']);
    $out = array('decisions' => array(), 'actions' => array());

    foreach (ws_meeting_block_rows($lines, ws_meeting_block_titles('decisions')) as $index => $row) {
        $text = trim(implode(' — ', array_filter($row['cells'], 'strlen')));

        if ($text === '') {
            continue;
        }

        $done = is_array($channel) && (bool) db_value("SELECT id FROM ws_messages
            WHERE channel_id = '" . (int) $channel['id'] . "' AND kind = 'decision' AND deleted_at = 0 AND body = '" . e($text) . "'
            LIMIT 1");

        $out['decisions'][] = array('index' => $index, 'text' => $text, 'plain' => ws_plain_text($viewer, $text), 'done' => $done);
    }

    foreach (ws_meeting_block_rows($lines, ws_meeting_block_titles('actions')) as $index => $row) {
        $text = trim(implode(' ', array_filter($row['cells'], 'strlen')));

        if ($text === '') {
            continue;
        }

        $parsed = ws_command_parse_task($text);

        // Brought over already: the row carries its task's tag.
        $done = (bool) preg_match('/<#task:[0-9]{1,10}>/', $text);
        $parsed['refs'] = array_values(array_filter($parsed['refs'], function ($ref) {
            return strpos($ref, '<#task:') !== 0;
        }));

        if (empty($parsed['assignees']) && ($parsed['department_id'] === 0)) {
            $parsed['assignees'] = array((int) $viewer['id']);
        }

        // Checked here as ws_task_create() will check it, so a row that
        // cannot become a task is shown as such before anything is written.
        $error = '';

        if (!ws_can_assign_to($viewer, $parsed['assignees'])) {
            $error = lang('You may only give tasks to yourself or to the people in the departments you lead.');
        } else {
            $check = ws_task_validate($viewer, ws_meeting_task_data($parsed, $channel));

            if (!$check['ok']) {
                $error = $check['error'];
            }
        }

        $out['actions'][] = array(
            'index'  => $index,
            'line'   => $row['line'],
            'table'  => $row['table'],
            'cells'  => $row['cells'],
            'task'   => $parsed,
            'title'  => $parsed['title'],
            'people' => array_values(ws_people($parsed['assignees'])),
            'due'    => $parsed['due_date'] ? ws_task_due_label(array('due_date' => $parsed['due_date'], 'status' => 'todo')) : lang('No due date'),
            'error'  => $error,
            'done'   => $done,
        );
    }

    return $out;
}

/**
 * The task an action item would become, as ws_task_create() takes it.
 *
 * @param array      $parsed  ws_command_parse_task()
 * @param array|null $channel
 * @return array
 */
function ws_meeting_task_data($parsed, $channel)
{
    return array(
        'title'            => $parsed['title'],
        'priority'         => $parsed['priority'],
        'start_date'       => $parsed['start_date'],
        'due_date'         => $parsed['due_date'],
        'estimate_minutes' => $parsed['estimate_minutes'],
        'department_id'    => $parsed['department_id'],
        'channel_id'       => is_array($channel) ? (int) $channel['id'] : 0,
        'assignees'        => $parsed['assignees'],
        'refs'             => $parsed['refs'],
    );
}

/**
 * What the meeting note screen shows before bringing it over: the read
 * note without the parts only the server uses.
 *
 * @param array $read ws_meeting_note_read()
 * @return array
 */
function ws_meeting_note_preview($read)
{
    return array(
        'decisions' => array_map(function ($row) {
            return array('index' => $row['index'], 'text' => $row['plain'], 'done' => $row['done']);
        }, $read['decisions']),
        'actions'   => array_map(function ($row) {
            return array('index' => $row['index'], 'title' => $row['title'], 'people' => $row['people'], 'due' => $row['due'], 'error' => $row['error'], 'done' => $row['done']);
        }, $read['actions']),
        'max'       => WS_MEETING_TRANSFER_MAX,
    );
}

/**
 * Brings the chosen decisions and actions of a meeting note into a channel,
 * and tags each row that became a task with it in the note.
 *
 * @param array $viewer
 * @param array $note      ws_note_for(..., 'edit')
 * @param array $channel
 * @param int[] $decisions indexes from ws_meeting_note_read()
 * @param int[] $actions   indexes from ws_meeting_note_read()
 * @return array ok, error, decisions (written), tasks (written), failed (texts not written, with why)
 */
function ws_meeting_note_transfer($viewer, $note, $channel, $decisions, $actions)
{
    $out = array('ok' => true, 'error' => '', 'decisions' => 0, 'tasks' => 0, 'failed' => array());

    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.')) + $out;
    }

    $decisions = array_map('intval', (array) $decisions);
    $actions = array_map('intval', (array) $actions);

    if ((count($decisions) + count($actions)) > WS_MEETING_TRANSFER_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'Bring at most {var:1} at a time.', 'vars' => WS_MEETING_TRANSFER_MAX))) + $out;
    }

    $read = ws_meeting_note_read($viewer, $note, $channel);
    $lines = preg_split('/\r\n|\r|\n/', (string) $note['body']);
    $tagged = false;

    foreach ($read['decisions'] as $row) {
        if (!in_array($row['index'], $decisions, true) || $row['done']) {
            continue;
        }

        $sent = ws_message_send($viewer, $channel, $row['text'], array('kind' => 'decision'));

        if ($sent['ok']) {
            $out['decisions']++;
        } else {
            $out['failed'][] = $row['plain'] . ': ' . $sent['error'];
        }
    }

    foreach ($read['actions'] as $row) {
        if (!in_array($row['index'], $actions, true) || $row['done']) {
            continue;
        }

        if ($row['error'] !== '') {
            $out['failed'][] = $row['title'] . ': ' . $row['error'];
            continue;
        }

        $created = ws_task_create($viewer, ws_meeting_task_data($row['task'], $channel));

        if (!$created['ok']) {
            $out['failed'][] = $row['title'] . ': ' . $created['error'];
            continue;
        }

        $sent = ws_message_send($viewer, $channel, '', array('kind' => 'task', 'task_id' => $created['task_id']));

        if ($sent['ok']) {
            db("UPDATE ws_tasks SET source_message_id = '" . (int) $sent['message_id'] . "' WHERE id = '" . (int) $created['task_id'] . "'");
        }

        // The row says which task it became.
        $tag = '<#task:' . (int) $created['task_id'] . '>';

        if ($row['table']) {
            $cells = $row['cells'];
            $cells[0] = trim($cells[0] . ' ' . $tag);
            $lines[$row['line']] = '| ' . implode(' | ', array_map(function ($cell) {
                return str_replace('|', '\\|', $cell);
            }, $cells)) . ' |';
        } else {
            $lines[$row['line']] = rtrim($lines[$row['line']]) . ' ' . $tag;
        }

        $tagged = true;
        $out['tasks']++;
    }

    if ($tagged) {
        ws_note_save($viewer, array('note_id' => (int) $note['id'], 'body' => implode("\n", $lines)));
    }

    return $out;
}

/**
 * The texts the meeting parts of the screens show.
 *
 * @return array key => text
 */
function ws_meetings_js_strings()
{
    return array(
        'meeting_link'            => lang('Meeting link'),
        'meeting_link_help'       => lang('Google Meet, Teams, Zoom or any web address. Its people are told ten minutes before it starts.'),
        'meeting_jitsi'           => lang('Make a Jitsi room'),
        'meeting_join'            => lang('Join'),
        'meeting_note'            => lang('Meeting note'),
        'meeting_note_help'       => lang('Opens the meeting note: attendees, agenda, decisions and action items.'),
        'meeting_transfer'        => lang('Bring to a channel'),
        'meeting_transfer_title'  => lang('Decisions and action items'),
        'meeting_transfer_help'   => lang('Decisions are written in the channel and kept on its Decisions tab. Action items become tasks: people with @, a day such as friday or 26.10, ~2h, !urgent.'),
        'meeting_transfer_none'   => lang('There is nothing under Decisions or Action items yet.'),
        'meeting_transfer_done'   => lang('Already in the channel'),
        'meeting_transfer_task'   => lang('Already a task'),
        'meeting_transfer_go'     => lang('Bring over'),
        'meeting_transfer_result' => ws_js_template('{var:1} decision(s) and {var:2} task(s) brought to the channel.', 2),
        'meeting_transfer_failed' => lang('Some could not be brought over:'),
        'meeting_channel'         => lang('Channel'),
        'meeting_decisions'       => lang('Decisions'),
        'meeting_actions'         => lang('Action items'),
    );
}
