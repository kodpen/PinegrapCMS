<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's work as a board: its tasks, decisions and files as
 * cards in columns, on a tab of the channel beside the conversation. The
 * cards are grouped by status (to do, in progress, waiting, done), by kind
 * (tasks, decisions, files, the pinned message and the summary), by the
 * people on them or by when they are due.
 *
 * The tasks are the channel's tasks as ws_tasks_list() gives them, so a card
 * is shown only to somebody who may see the task; the decisions and the files
 * are the messages the Decisions and Files tabs read. A finished task stays
 * on the board for WS_CHANNEL_BOARD_DONE_DAYS days, the older ones a click
 * away. Cards are sorted by priority, then by due date: there is no order of
 * the reader's own.
 *
 * Moving a card is done by the actions that already change a task
 * (ws_task_status, ws_task_save, ws_task_move), with their checks and their
 * questions; what is here only reads. The screen side is
 * assets/js/workspace_board_channel.js.
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
 * How many days a finished task stays in the Done column.
 */
define('WS_CHANNEL_BOARD_DONE_DAYS', 14);

/**
 * The ways the board groups its cards; the first is the one it opens with.
 *
 * @return array key => label
 */
function ws_channel_board_groupings()
{
    return array(
        'status' => lang('By status'),
        'type'   => lang('By kind'),
        'person' => lang('By person'),
        'date'   => lang('By date'),
    );
}

/**
 * Has this channel a board? Not a discussion beside a channel, and not a
 * room a guest talks in.
 *
 * @param array $channel
 * @return bool
 */
function ws_channel_board_available($channel)
{
    return is_array($channel) && !in_array((string) $channel['kind'], array('thread', 'guest'), true);
}

/**
 * The last day of the week a day falls in (Sunday; the week starts on
 * Monday).
 *
 * @param string $today Y-m-d
 * @return string Y-m-d
 */
function ws_channel_board_week_end($today)
{
    $noon = strtotime($today . ' 12:00:00');

    return date('Y-m-d', strtotime('+' . (7 - (int) date('N', $noon)) . ' days', $noon));
}

/**
 * The date column a due date falls in.
 *
 * @param string|null $due   Y-m-d, or empty for none
 * @param string      $today Y-m-d
 * @return string overdue | today | week (after today, to Sunday) | later | none
 */
function ws_channel_board_date_bucket($due, $today)
{
    $due = (string) $due;

    if (($due === '') || ($due === '0000-00-00')) {
        return 'none';
    }

    if ($due < $today) {
        return 'overdue';
    }

    if ($due === $today) {
        return 'today';
    }

    return ($due <= ws_channel_board_week_end($today)) ? 'week' : 'later';
}

/**
 * The due date a card dropped on a date column gets: today, tomorrow (while
 * tomorrow is still this week), the Monday after this week, or none. Null
 * where nothing can be dropped: the overdue column, and this week on a
 * Sunday.
 *
 * @param string $bucket
 * @param string $today  Y-m-d
 * @return string|null Y-m-d, '' to take the due date off
 */
function ws_channel_board_drop_date($bucket, $today)
{
    $noon = strtotime($today . ' 12:00:00');
    $end = ws_channel_board_week_end($today);

    switch ($bucket) {
        case 'today':
            return $today;

        case 'week':
            $tomorrow = date('Y-m-d', strtotime('+1 day', $noon));

            return ($tomorrow <= $end) ? $tomorrow : null;

        case 'later':
            return date('Y-m-d', strtotime('+1 day', strtotime($end . ' 12:00:00')));

        case 'none':
            return '';
    }

    return null;
}

/**
 * The order of the cards in a column: the more pressing first, then the
 * sooner due (those without a date last), then the newer.
 *
 * @param array $a a card: priority, due_date, id
 * @param array $b
 * @return int
 */
function ws_channel_board_compare($a, $b)
{
    $rank = array('urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3);
    $pa = $rank[$a['priority'] ?? 'normal'] ?? 2;
    $pb = $rank[$b['priority'] ?? 'normal'] ?? 2;

    if ($pa !== $pb) {
        return $pa <=> $pb;
    }

    $da = (string) ($a['due_date'] ?? '');
    $db = (string) ($b['due_date'] ?? '');

    if ($da !== $db) {
        if ($da === '') {
            return 1;
        }

        if ($db === '') {
            return -1;
        }

        return strcmp($da, $db);
    }

    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
}

/**
 * Something that changes whenever a card of the board would: the channel's
 * tasks, its decisions and files, its pinned message and its summary. The
 * channel screen's look (ws_sync) carries it while the board is open, and the
 * board is read again when it moves. Two indexed reads.
 *
 * @param array $channel
 * @return string
 */
function ws_channel_board_stamp($channel)
{
    $channel_id = (int) $channel['id'];

    $tasks = db_item("SELECT COUNT(*) AS n, COALESCE(MAX(updated_at), 0) AS t FROM ws_tasks WHERE channel_id = '" . $channel_id . "'");
    $messages = db_item("SELECT COUNT(*) AS n, COALESCE(MAX(id), 0) AS m, COALESCE(MAX(marked_at), 0) AS t FROM ws_messages
        WHERE channel_id = '" . $channel_id . "' AND deleted_at = 0 AND (kind = 'decision' OR file_id > 0)");

    return md5(implode(':', array(
        (int) ($tasks['n'] ?? 0), (int) ($tasks['t'] ?? 0),
        (int) ($messages['n'] ?? 0), (int) ($messages['m'] ?? 0), (int) ($messages['t'] ?? 0),
        (int) ($channel['pinned_message_id'] ?? 0), (int) ($channel['summary_updated_at'] ?? 0),
    )));
}

/**
 * A person as a card shows them.
 *
 * @param array $person ws_people() row
 * @return array
 */
function ws_channel_board_face($person)
{
    return array(
        'id'          => (int) $person['id'],
        'name'        => (string) $person['name'],
        'avatar'      => (string) $person['avatar'],
        'avatar_kind' => (string) ($person['avatar_kind'] ?? ''),
    );
}

/**
 * The channel's tasks as cards, filtered.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $options priority, person (a user id, -1 nobody), mine, q
 * @return array[]
 */
function ws_channel_board_tasks($viewer, $channel, $options)
{
    $briefs = ws_tasks_list($viewer, array(
        'scope'      => 'channel',
        'channel_id' => (int) $channel['id'],
        'status'     => 'all',
        'search'     => (string) ($options['q'] ?? ''),
        'priority'   => (string) ($options['priority'] ?? ''),
        'limit'      => 500,
    ));

    if (empty($briefs)) {
        return array();
    }

    // What the brief does not carry: when it was finished, the reminder and
    // the series it belongs to.
    $ids = array_map(function ($brief) { return (int) $brief['id']; }, $briefs);
    $rows = array();

    foreach ((array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $rows[(int) $row['id']] = $row;
    }

    // The newest copies of running series: moving one to another day asks
    // whether the copies after it go along (ws_recurrence_moved()).
    $newest = array();
    $series = array();

    foreach ($rows as $row) {
        if ((int) ($row['recurrence_id'] ?? 0) > 0) {
            $series[(int) $row['recurrence_id']] = true;
        }
    }

    if (!empty($series) && function_exists('ws_recurrence_ready') && ws_recurrence_ready()) {
        foreach ((array) db_values("SELECT last_task_id FROM ws_task_recurrences
            WHERE status = 'active' AND id IN (" . implode(',', array_keys($series)) . ")") as $task_id) {
            $newest[(int) $task_id] = true;
        }
    }

    $me = (int) $viewer['id'];
    $person = (int) ($options['person'] ?? 0);
    $priorities = ws_task_priorities();
    $out = array();

    foreach ($briefs as $brief) {
        $row = $rows[(int) $brief['id']] ?? array();
        $people = array_map('ws_channel_board_face', $brief['assignees']);
        $people_ids = array_map(function ($face) { return $face['id']; }, $people);

        if (!empty($options['mine']) && !in_array($me, $people_ids, true)) {
            continue;
        }

        if ((($person > 0) && !in_array($person, $people_ids, true)) || (($person < 0) && !empty($people_ids))) {
            continue;
        }

        $out[] = array(
            'kind'           => 'task',
            'id'             => (int) $brief['id'],
            'number'         => (string) $brief['number'],
            'title'          => (string) $brief['title'],
            'status'         => (string) $brief['status'],
            'status_label'   => (string) $brief['status_label'],
            'priority'       => (string) $brief['priority'],
            'priority_label' => (string) ($priorities[$brief['priority']] ?? ''),
            'open'           => (bool) $brief['open'],
            'due_date'       => $brief['due_date'],
            'due_label'      => (string) $brief['due_label'],
            'overdue'        => (bool) $brief['overdue'],
            'assignees'      => $people,
            'progress'       => $brief['progress'],
            'notes_count'    => (int) $brief['notes_count'],
            'recurring'      => (bool) $brief['recurring'],
            'series_latest'  => isset($newest[(int) $brief['id']]),
            'reminder'       => (($row['remind_minutes'] ?? null) !== null) && ((int) ($row['remind_at'] ?? 0) > 0),
            'completed_at'   => (int) ($row['completed_at'] ?? 0),
            'can_edit'       => !empty($brief['can_edit']),
        );
    }

    return $out;
}

/**
 * The channel's decisions as cards: the first line, who kept it and when.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $q
 * @return array[]
 */
function ws_channel_board_decisions($viewer, $channel, $q)
{
    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND kind = 'decision' AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC
        LIMIT 100");

    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $rows));
    $people = ws_people(array_unique(array_map(function ($row) { return (int) $row['marked_by']; }, $rows)));
    $out = array();

    foreach ($rows as $row) {
        if (isset($hidden[(int) $row['id']])) {
            continue;
        }

        $lines = preg_split('/\r?\n/', trim((string) $row['body']));
        $first = '';

        foreach ($lines as $line) {
            $first = ws_plain_excerpt($viewer, $line, 160);

            if ($first !== '') {
                break;
            }
        }

        if (($q !== '') && (mb_stripos(ws_plain_text($viewer, (string) $row['body']), $q) === false)) {
            continue;
        }

        $by = $people[(int) $row['marked_by']]['name'] ?? '';
        $when = ws_time_label((int) $row['marked_at'] ?: (int) $row['created_at']);

        $out[] = array(
            'kind'       => 'decision',
            'id'         => (int) $row['id'],
            'title'      => $first,
            'sub'        => ($by !== '') ? lang(array('string' => 'Kept by {var:1}, {var:2}', 'vars' => array($by, $when))) : $when,
            'message_id' => (int) $row['id'],
        );
    }

    return $out;
}

/**
 * The files posted in the channel as cards: the name, a small picture, who
 * posted it.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $q
 * @return array[]
 */
function ws_channel_board_files($viewer, $channel, $q)
{
    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND file_id > 0 AND deleted_at = 0
        ORDER BY id DESC
        LIMIT 100");

    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $rows));
    $senders = array();
    $files = array();

    foreach ($rows as $row) {
        if ($row['sender_kind'] === 'user') {
            $senders[] = (int) $row['sender_id'];
        }

        $files[] = (int) $row['file_id'];
    }

    $people = ws_people(array_unique($senders));
    $stored = array();

    if (!empty($files)) {
        foreach ((array) db_items("SELECT id, name FROM files WHERE id IN (" . implode(',', array_unique($files)) . ")") as $file) {
            $stored[(int) $file['id']] = (string) $file['name'];
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $name = (string) $row['file_name'];

        if (isset($hidden[(int) $row['id']]) || !isset($stored[(int) $row['file_id']])
            || (($q !== '') && (mb_stripos($name, $q) === false))) {
            continue;
        }

        $kind = ws_file_kind($name);
        $url = PATH . encode_url_path($stored[(int) $row['file_id']]);
        $by = ws_palette_sender_name($row, $people);
        $when = ws_time_label($row['created_at']);

        $out[] = array(
            'kind'       => 'file',
            'id'         => (int) $row['id'],
            'title'      => $name,
            'sub'        => ($by !== '') ? lang(array('string' => 'Posted by {var:1}, {var:2}', 'vars' => array($by, $when))) : $when,
            'url'        => $url,
            'file_kind'  => $kind,
            'ext'        => strtolower((string) pathinfo($name, PATHINFO_EXTENSION)),
            'thumb'      => ($kind === 'image') ? $url : '',
            'message_id' => (int) $row['id'],
        );
    }

    return $out;
}

/**
 * The board of a channel.
 *
 * @param array $viewer
 * @param array $channel  one the person may read
 * @param array $options  group_by, priority, person, mine, q, cancelled (show
 *                        the cancelled tasks), done_all (every finished task)
 * @return array group_by, columns (key, title, icon, drop, add, items, more), counts, members, stamp
 */
function ws_channel_board($viewer, $channel, $options = array())
{
    $group_by = (string) ($options['group_by'] ?? 'status');

    if (!isset(ws_channel_board_groupings()[$group_by])) {
        $group_by = 'status';
    }

    $q = trim(mb_substr((string) ($options['q'] ?? ''), 0, 100));
    $options['q'] = $q;
    $today = date('Y-m-d');
    $tasks = ws_channel_board_tasks($viewer, $channel, $options);
    $cutoff = strtotime('-' . WS_CHANNEL_BOARD_DONE_DAYS . ' days', strtotime($today . ' 00:00:00'));
    $done_hidden = 0;

    usort($tasks, 'ws_channel_board_compare');

    $member_ids = array_map('intval', (array) db_values("SELECT user_id FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY role = 'owner' DESC, joined_at"));
    $members = array_map('ws_channel_board_face', array_values(ws_people($member_ids)));

    $open = array_values(array_filter($tasks, function ($task) { return $task['open']; }));
    $columns = array();

    $column = function ($key, $title, $icon, $drop, $add, $items) {
        return array('key' => $key, 'title' => $title, 'icon' => $icon, 'drop' => $drop, 'add' => $add, 'items' => array_values($items), 'more' => 0);
    };

    switch ($group_by) {
        case 'status':
            $icons = array('todo' => 'bi-circle', 'doing' => 'bi-play-circle', 'waiting' => 'bi-pause-circle', 'done' => 'bi-check2-circle', 'cancelled' => 'bi-x-circle');

            foreach (ws_task_statuses() as $status => $label) {
                if (($status === 'cancelled') && empty($options['cancelled'])) {
                    continue;
                }

                $items = array_filter($tasks, function ($task) use ($status) { return $task['status'] === $status; });
                $more = 0;

                if (($status === 'done') && empty($options['done_all'])) {
                    $recent = array_filter($items, function ($task) use ($cutoff) { return $task['completed_at'] >= $cutoff; });
                    $more = count($items) - count($recent);
                    $items = $recent;
                    $done_hidden = $more;
                }

                $entry = $column($status, $label, $icons[$status], $status, ws_task_is_open($status) ? array('status' => $status) : null, $items);
                $entry['more'] = $more;
                $columns[] = $entry;
            }
            break;

        case 'person':
            $faces = array();

            foreach ($members as $face) {
                $faces[$face['id']] = $face;
            }

            // People on the channel's tasks who are not in it get a column
            // too, after the members.
            foreach ($open as $task) {
                foreach ($task['assignees'] as $face) {
                    if (!isset($faces[$face['id']])) {
                        $faces[$face['id']] = $face;
                    }
                }
            }

            foreach ($faces as $user_id => $face) {
                $items = array_filter($open, function ($task) use ($user_id) {
                    return in_array($user_id, array_map(function ($person) { return $person['id']; }, $task['assignees']), true);
                });

                $entry = $column('person:' . $user_id, $face['name'], 'bi-person', $user_id, array('assignees' => array($user_id)), $items);
                $entry['person'] = $face;
                $columns[] = $entry;
            }

            $columns[] = $column('person:0', lang('Nobody yet'), 'bi-person-dash', 0, array('assignees' => array()), array_filter($open, function ($task) {
                return empty($task['assignees']);
            }));
            break;

        case 'date':
            $titles = array(
                'overdue' => array(lang('Overdue'), 'bi-exclamation-circle'),
                'today'   => array(lang('Today'), 'bi-calendar-day'),
                'week'    => array(lang('This week'), 'bi-calendar-week'),
                'later'   => array(lang('Later'), 'bi-calendar-plus'),
                'none'    => array(lang('No due date'), 'bi-calendar-x'),
            );

            foreach ($titles as $bucket => $title) {
                $drop = ws_channel_board_drop_date($bucket, $today);
                $items = array_filter($open, function ($task) use ($bucket, $today) {
                    return ws_channel_board_date_bucket($task['due_date'], $today) === $bucket;
                });

                $columns[] = $column('date:' . $bucket, $title[0], $title[1], $drop, ($drop === null) ? null : array('due_date' => $drop), $items);
            }
            break;

        case 'type':
            $columns[] = $column('type:tasks', lang('Tasks'), 'bi-check2-square', null, array(), $open);
            $columns[] = $column('type:decisions', lang('Decisions'), 'bi-patch-check', null, null, ws_channel_board_decisions($viewer, $channel, $q));
            $columns[] = $column('type:files', lang('Files'), 'bi-paperclip', null, null, ws_channel_board_files($viewer, $channel, $q));

            $notes = array();
            $pin = ws_channel_pin_present($viewer, $channel, ws_channel_membership($channel['id'], $viewer['id']));

            if ($pin && (($q === '') || (mb_stripos($pin['text'], $q) !== false))) {
                $notes[] = array(
                    'kind'       => 'pin',
                    'id'         => (int) $pin['id'],
                    'title'      => (string) $pin['text'],
                    'sub'        => implode(' · ', array_filter(array((string) ($pin['sender']['name'] ?? ''), (string) $pin['time']), 'strlen')),
                    'message_id' => (int) $pin['id'],
                );
            }

            $summary = trim((string) $channel['summary']);

            if (($summary !== '') && (($q === '') || (mb_stripos(ws_plain_text($viewer, $summary), $q) !== false))) {
                $notes[] = array(
                    'kind'  => 'summary',
                    'id'    => (int) $channel['id'],
                    'title' => ws_plain_excerpt($viewer, $summary, 280),
                    'sub'   => ((int) $channel['summary_updated_at'] > 0)
                        ? lang(array('string' => 'Updated by {var:1}, {var:2}', 'vars' => array(ws_person_name($channel['summary_updated_by']), ws_time_label($channel['summary_updated_at']))))
                        : '',
                );
            }

            $columns[] = $column('type:pinned', lang('Pinned message and summary'), 'bi-pin-angle', null, null, $notes);
            break;
    }

    $counts = array('tasks' => count($open), 'done_hidden' => $done_hidden);

    foreach ($columns as $entry) {
        $counts[$entry['key']] = count($entry['items']);
    }

    return array(
        'group_by' => $group_by,
        'today'    => $today,
        'columns'  => $columns,
        'counts'   => $counts,
        'members'  => $members,
        'can_post' => ws_can_post_channel($viewer, $channel),
        'stamp'    => ws_channel_board_stamp($channel),
    );
}

/**
 * The words the channel board needs (assets/js/workspace_board_channel.js).
 *
 * @return array key => text
 */
function ws_channel_board_js_strings()
{
    $strings = array(
        'tab_board'        => lang('Board'),
        'cb_group'         => lang('Group the cards'),
        'cb_all_people'    => lang('Everybody'),
        'cb_nobody'        => lang('Nobody on it'),
        'cb_mine'          => lang('Only mine'),
        'cb_search'        => lang('Search the cards'),
        'cb_cancelled'     => lang('Show the cancelled ones'),
        'cb_more_done'     => ws_js_template('{var:1} finished earlier', 1),
        'cb_empty'         => lang('Nothing here.'),
        'cb_add'           => lang('New task in this column'),
        'cb_column'        => lang('Column'),
        'cb_notes'         => ws_js_template('{var:1} notes', 1),
        'cb_reminder'      => lang('A reminder is set'),
        'cb_repeating'     => lang('Repeating task'),
        'cb_open_message'  => lang('Go to the message'),
        'cb_open_summary'  => lang('Open the summary'),
        'cb_open_file'     => lang('Open the file'),
        'cb_no_drop'       => lang('A card cannot be put in this column.'),
        'cb_no_date_repeat' => lang('A repeating task keeps its due date: change its repeat in the task instead.'),
        'cb_lifted'        => ws_js_template('{var:1} picked up. Left and right arrows choose a column, Enter puts it down, Esc leaves it where it was.', 1),
        'cb_target'        => ws_js_template('Over the column {var:1}', 1),
        'cb_dropped'       => ws_js_template('Put in the column {var:1}', 1),
        'cb_cancel_move'   => lang('Left where it was.'),
        'cb_count'         => ws_js_template('{var:1} cards', 1),
    );

    foreach (ws_channel_board_groupings() as $key => $label) {
        $strings['cb_by_' . $key] = $label;
    }

    return $strings;
}
