<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's tasks as a board: cards in columns, on a tab of the
 * channel beside the conversation. The cards are grouped by status (to do,
 * in progress, waiting, done), by the people on them or by when they are
 * due. The board holds tasks only: decisions, files, the pinned message and
 * the summary have their own tabs.
 *
 * The tasks are the channel's tasks as ws_tasks_list() gives them, so a card
 * is shown only to somebody who may see the task. A finished task stays
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
 * How many days back "Done in the last 7 days" counts.
 */
define('WS_CHANNEL_BOARD_FIGURE_DONE_DAYS', 7);

/**
 * The four figures above the board, in the order they stand: open tasks,
 * due this week (today to Sunday), overdue, done in the last seven days.
 *
 * @return string[]
 */
function ws_channel_board_figure_keys()
{
    return array('open', 'week', 'overdue', 'done');
}

/**
 * The figures a card counts in. Pure.
 *
 * @param array  $card  status, open, due_date (Y-m-d or empty), completed_at
 * @param string $today Y-m-d
 * @param int    $now   timestamp
 * @return string[] keys of ws_channel_board_figure_keys()
 */
function ws_channel_board_card_figures($card, $today, $now)
{
    $out = array();

    if (!empty($card['open'])) {
        $out[] = 'open';
        $bucket = ws_channel_board_date_bucket($card['due_date'] ?? '', $today);

        if (($bucket === 'today') || ($bucket === 'week')) {
            $out[] = 'week';
        } elseif ($bucket === 'overdue') {
            $out[] = 'overdue';
        }
    } elseif (((string) ($card['status'] ?? '') === 'done')
        && ((int) ($card['completed_at'] ?? 0) >= ((int) $now - (WS_CHANNEL_BOARD_FIGURE_DONE_DAYS * 86400)))) {
        $out[] = 'done';
    }

    return $out;
}

/**
 * The four figures of a set of cards. Pure.
 *
 * @param array[] $cards
 * @param string  $today Y-m-d
 * @param int     $now   timestamp
 * @return array key => count
 */
function ws_channel_board_figures($cards, $today, $now)
{
    $counts = array_fill_keys(ws_channel_board_figure_keys(), 0);

    foreach ($cards as $card) {
        foreach (ws_channel_board_card_figures($card, $today, $now) as $key) {
            $counts[$key]++;
        }
    }

    return $counts;
}

/**
 * The cards a figure chosen as a filter keeps; an empty or unknown figure
 * keeps them all. Pure.
 *
 * @param array[] $cards
 * @param string  $figure
 * @param string  $today  Y-m-d
 * @param int     $now    timestamp
 * @return array[] a list
 */
function ws_channel_board_figure_filter($cards, $figure, $today, $now)
{
    if (!in_array((string) $figure, ws_channel_board_figure_keys(), true)) {
        return array_values($cards);
    }

    return array_values(array_filter($cards, function ($card) use ($figure, $today, $now) {
        return in_array($figure, ws_channel_board_card_figures($card, $today, $now), true);
    }));
}

/**
 * Task briefs (ws_tasks_list()) narrowed to a figure, for the List view;
 * when the figure is "done" their finishing time is read, which the brief
 * does not carry.
 *
 * @param array[] $briefs
 * @param string  $figure
 * @return array[]
 */
function ws_channel_board_list_figure($briefs, $figure)
{
    $briefs = array_values((array) $briefs);

    if (empty($briefs) || !in_array((string) $figure, ws_channel_board_figure_keys(), true)) {
        return $briefs;
    }

    $finished = array();

    if ($figure === 'done') {
        $ids = array_map(function ($brief) { return (int) $brief['id']; }, $briefs);

        foreach ((array) db_items("SELECT id, completed_at FROM ws_tasks WHERE id IN (" . implode(',', $ids) . ")") as $row) {
            $finished[(int) $row['id']] = (int) $row['completed_at'];
        }
    }

    $today = date('Y-m-d');
    $now = time();

    return array_values(array_filter($briefs, function ($brief) use ($figure, $finished, $today, $now) {
        $brief['completed_at'] = $finished[(int) $brief['id']] ?? 0;

        return in_array($figure, ws_channel_board_card_figures($brief, $today, $now), true);
    }));
}

/**
 * The tone of a card's due chip: gray for a later day, "soon" (amber) for
 * today and tomorrow, "late" (rose) with the days late, "done" (green) on a
 * closed task. Pure.
 *
 * @param string|null $due   Y-m-d, or empty for none
 * @param bool        $open
 * @param string      $today Y-m-d
 * @return array tone ('', soon, late, done), late (days)
 */
function ws_channel_board_due_chip($due, $open, $today)
{
    $due = (string) $due;

    if (($due === '') || ($due === '0000-00-00')) {
        return array('tone' => '', 'late' => 0);
    }

    if (!$open) {
        return array('tone' => 'done', 'late' => 0);
    }

    $bucket = ws_channel_board_date_bucket($due, $today);

    if ($bucket === 'overdue') {
        $days = (int) round((strtotime($today . ' 12:00:00') - strtotime($due . ' 12:00:00')) / 86400);

        return array('tone' => 'late', 'late' => max(1, $days));
    }

    $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime($today . ' 12:00:00')));

    return array('tone' => (($due === $today) || ($due === $tomorrow)) ? 'soon' : '', 'late' => 0);
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
 * tasks. The channel screen's look (ws_sync) carries it while the board is
 * open, and the board is read again when it moves. One indexed read.
 *
 * @param array $channel
 * @return string
 */
function ws_channel_board_stamp($channel)
{
    $channel_id = (int) $channel['id'];

    $tasks = db_item("SELECT COUNT(*) AS n, COALESCE(MAX(updated_at), 0) AS t FROM ws_tasks WHERE channel_id = '" . $channel_id . "'");

    return md5(implode(':', array((int) ($tasks['n'] ?? 0), (int) ($tasks['t'] ?? 0))));
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
    $today = date('Y-m-d');
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

        $chip = ws_channel_board_due_chip($brief['due_date'], !empty($brief['open']), $today);

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
            'due_tone'       => $chip['tone'],
            'due_late'       => $chip['late'],
            'assignees'      => $people,
            'progress'       => $brief['progress'],
            'notes_count'    => (int) $brief['notes_count'],
            'recurring'      => (bool) $brief['recurring'],
            'series_latest'  => isset($newest[(int) $brief['id']]),
            'reminder'       => (($row['remind_minutes'] ?? null) !== null) && ((int) ($row['remind_at'] ?? 0) > 0),
            'completed_at'   => (int) ($row['completed_at'] ?? 0),
            'can_edit'       => !empty($brief['can_edit']),
            // The assistant or agent holding the task (delegation.php).
            'delegate'        => (string) ($brief['delegate'] ?? ''),
            'delegate_name'   => (string) ($brief['delegate_name'] ?? ''),
            'delegate_icon'   => (string) ($brief['delegate_icon'] ?? ''),
            'delegate_avatar' => (string) ($brief['delegate_avatar'] ?? ''),
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
 *                        the cancelled tasks), done_all (every finished task),
 *                        figure (a key of ws_channel_board_figure_keys(): only
 *                        its tasks), figures_only (the figures and the stamp,
 *                        for the List view)
 * @return array group_by, figure, figures, columns (key, title, icon, drop, add, items, more), counts, members, stamp
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
    $now = time();
    $tasks = ws_channel_board_tasks($viewer, $channel, $options);

    // The figures count what the toolbar lets through; the figure chosen
    // then narrows the cards, so the other figures keep their counts.
    $figures = ws_channel_board_figures($tasks, $today, $now);
    $figure = in_array((string) ($options['figure'] ?? ''), ws_channel_board_figure_keys(), true) ? (string) $options['figure'] : '';

    if (!empty($options['figures_only'])) {
        return array('figure' => $figure, 'figures' => $figures, 'stamp' => ws_channel_board_stamp($channel));
    }

    $tasks = ws_channel_board_figure_filter($tasks, $figure, $today, $now);
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
    }

    $counts = array('tasks' => count($open), 'done_hidden' => $done_hidden);

    foreach ($columns as $entry) {
        $counts[$entry['key']] = count($entry['items']);
    }

    return array(
        'group_by' => $group_by,
        'figure'   => $figure,
        'figures'  => $figures,
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
        'cb_view'          => lang('Board or list'),
        'cb_view_board'    => lang('Board'),
        'cb_view_list'     => lang('List'),
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
        'cb_no_drop'       => lang('A card cannot be put in this column.'),
        'cb_no_date_repeat' => lang('A repeating task keeps its due date: change its repeat in the task instead.'),
        'cb_lifted'        => ws_js_template('{var:1} picked up. Left and right arrows choose a column, Enter puts it down, Esc leaves it where it was.', 1),
        'cb_target'        => ws_js_template('Over the column {var:1}', 1),
        'cb_dropped'       => ws_js_template('Put in the column {var:1}', 1),
        'cb_cancel_move'   => lang('Left where it was.'),
        'cb_count'         => ws_js_template('{var:1} cards', 1),
        'cb_figures'       => lang('What the board holds'),
        'cb_figure_help'   => lang('Show only these tasks. Press again to show them all.'),
        'cb_fig_open'      => lang('Open tasks'),
        'cb_fig_week'      => lang('Due this week'),
        'cb_fig_overdue'   => lang('Overdue'),
        'cb_fig_done'      => lang('Done in the last 7 days'),
        'cb_late'          => ws_js_template('{var:1} days late', 1),
        'cb_late_one'      => lang('1 day late'),
        'cb_done'          => lang('Done'),
        'cb_drop_status'   => ws_js_template('Drop here: status becomes “{var:1}”', 1),
        'cb_drop_person'   => ws_js_template('Drop here: goes to {var:1}', 1),
        'cb_drop_nobody'   => lang('Drop here: nobody on it'),
        'cb_drop_date'     => ws_js_template('Drop here: due date becomes “{var:1}”', 1),
        'cb_drop_no_date'  => lang('Drop here: no due date'),
        // The Automations drawer (channel_board_rules.php).
        'cb_auto'          => lang('Automations'),
        'cb_auto_on'       => ws_js_template('{var:1} rules are on', 1),
        'cb_auto_title'    => lang('Board automations'),
        'cb_auto_lead'     => lang('Choose what happens when a task gets somewhere.'),
        'cb_auto_ready'    => lang('Ready rules'),
        'cb_auto_flows'    => lang('Flows of this channel'),
        'cb_auto_own'      => lang('Your own rule'),
        'cb_auto_build'    => lang('Build your own flow'),
        'cb_auto_build_line' => lang('Opens Flows with this channel chosen'),
        'cb_auto_ran'      => ws_js_template('Ran {var:1} times this week', 1),
        'cb_auto_ran_one'  => lang('Ran once this week'),
        'cb_auto_turn_on'  => lang('Turn on'),
        'cb_auto_choose'   => lang('Choose a person'),
        'cb_auto_open'     => ws_js_template('Open in Flows: {var:1}', 1),
        'cb_auto_draft'    => lang('Not published yet: open it in Flows to publish it.'),
        'cb_auto_owner'    => lang('Only its owner can turn it on or off.'),
        'cb_auto_runs'     => lang('Last runs'),
        'cb_auto_no_runs'  => lang('No rule has run yet.'),
    );

    foreach (ws_channel_board_groupings() as $key => $label) {
        $strings['cb_by_' . $key] = $label;
    }

    return $strings;
}
