<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the timeline: the decisions taken in every channel a person may
 * read, newest first, on one screen (workspace_timeline.php) and for the
 * external API (GET /workspace/decisions). Notes join them when asked for.
 *
 * A decision sits on the day it was taken: the moment its message was marked
 * as a decision, or written as one. A private channel the person is not in is
 * never read here. Staff, who may open such a channel for inspection, are told
 * how many decisions each one holds - never what they say - and opening one
 * goes through the usual inspection path, which leaves its trace in the
 * channel and in the activity log.
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
 * Decisions on one page of the screen.
 */
define('WS_TIMELINE_PAGE', 30);

/**
 * When a decision was taken, as SQL over ws_messages: the moment it was
 * marked, or when it was written for a message that was a decision from the
 * start and never carried a mark time.
 */
define('WS_TIMELINE_MOMENT', 'IF(marked_at > 0, marked_at, created_at)');

/**
 * The channels whose decisions a person may read, archived ones included,
 * keyed by id.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_timeline_channels($viewer)
{
    if (!$viewer['member']) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT c.id, c.name, c.kind, c.department_id, c.contact_id, c.archived_at, m.user_id AS my_member
        FROM ws_channels c
        LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
        ORDER BY (c.archived_at > 0), c.name") as $row) {

        $readable = ($row['kind'] === 'public')
            || ($row['my_member'] !== null)
            || (($viewer['role'] < 3) && ws_audit_open($row['id']));

        if ($readable) {
            $out[(int) $row['id']] = $row;
        }
    }

    return $out;
}

/**
 * The filters a timeline request may carry, cleaned.
 *
 * @param array $input notes, channel_id, department_id, person_id, from, to, search
 * @return array
 */
function ws_timeline_filters($input)
{
    $input = is_array($input) ? $input : array();

    $date = function ($value) {
        $value = trim((string) $value);

        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return '';
        }

        return $value;
    };

    $filters = array(
        'notes'         => !empty($input['notes']),
        'channel_id'    => max(0, (int) ($input['channel_id'] ?? 0)),
        'department_id' => max(0, (int) ($input['department_id'] ?? 0)),
        'person_id'     => max(0, (int) ($input['person_id'] ?? 0)),
        'from'          => $date($input['from'] ?? ''),
        'to'            => $date($input['to'] ?? ''),
        'search'        => trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($input['search'] ?? '')), 0, 100)),
    );

    // A range given the wrong way round is read the right way round.
    if (($filters['from'] !== '') && ($filters['to'] !== '') && ($filters['from'] > $filters['to'])) {
        list($filters['from'], $filters['to']) = array($filters['to'], $filters['from']);
    }

    return $filters;
}

/**
 * The ids of the channels a request looks at: the readable ones, narrowed to
 * the channel or the department asked for.
 *
 * @param array[] $channels ws_timeline_channels()
 * @param array   $filters  ws_timeline_filters()
 * @return int[]
 */
function ws_timeline_channel_ids($channels, $filters)
{
    $ids = array();

    foreach ($channels as $id => $channel) {
        if (($filters['channel_id'] > 0) && ((int) $id !== $filters['channel_id'])) {
            continue;
        }

        if (($filters['department_id'] > 0) && ((int) $channel['department_id'] !== $filters['department_id'])) {
            continue;
        }

        $ids[] = (int) $id;
    }

    return $ids;
}

/**
 * The WHERE conditions of a timeline read.
 *
 * @param int[] $channel_ids not empty
 * @param array $filters
 * @param bool  $content the person and the words asked for too; left out
 *                       where only the number of decisions may be told
 * @return string[]
 */
function ws_timeline_where($channel_ids, $filters, $content = true)
{
    $where = array(
        "channel_id IN (" . implode(',', array_map('intval', $channel_ids)) . ")",
        "kind IN ('decision'" . ($filters['notes'] ? ", 'note'" : '') . ")",
        "deleted_at = 0",
    );

    if ($filters['from'] !== '') {
        $where[] = WS_TIMELINE_MOMENT . " >= '" . (int) strtotime($filters['from'] . ' 00:00:00') . "'";
    }

    if ($filters['to'] !== '') {
        $where[] = WS_TIMELINE_MOMENT . " < '" . (int) strtotime($filters['to'] . ' 00:00:00 +1 day') . "'";
    }

    if ($content && ($filters['person_id'] > 0)) {
        $person = (int) $filters['person_id'];
        $where[] = "(marked_by = '" . $person . "' OR (sender_kind = 'user' AND sender_id = '" . $person . "'))";
    }

    if ($content && ($filters['search'] !== '')) {
        $where[] = "body LIKE '%" . e(escape_like($filters['search'])) . "%'";
    }

    return $where;
}

/**
 * One page of decisions, newest first.
 *
 * @param array         $viewer
 * @param array         $filters ws_timeline_filters()
 * @param array|null    $cursor  array('v' => moment, 'i' => id) of the last row handed out
 * @param int           $limit
 * @param callable|null $channel_filter keeps a channel when it returns true
 * @return array rows (ws_messages rows with tl_at), next (a cursor or null),
 *               channels (readable, keyed by id), counts (decisions per
 *               channel for the whole filter; first page only)
 */
function ws_timeline($viewer, $filters, $cursor = null, $limit = WS_TIMELINE_PAGE, $channel_filter = null)
{
    $channels = ws_timeline_channels($viewer);

    if ($channel_filter !== null) {
        $channels = array_filter($channels, $channel_filter);
    }

    $result = array('rows' => array(), 'next' => null, 'channels' => $channels, 'counts' => array());
    $ids = ws_timeline_channel_ids($channels, $filters);

    if (empty($ids)) {
        return $result;
    }

    $where = ws_timeline_where($ids, $filters);

    if ($cursor === null) {
        foreach ((array) db_items("SELECT channel_id, COUNT(*) AS n FROM ws_messages
            WHERE " . implode(' AND ', $where) . "
            GROUP BY channel_id") as $row) {
            $result['counts'][(int) $row['channel_id']] = (int) $row['n'];
        }
    } else {
        $where[] = "(" . WS_TIMELINE_MOMENT . " < '" . (int) $cursor['v'] . "' OR (" . WS_TIMELINE_MOMENT . " = '" . (int) $cursor['v'] . "' AND id < '" . (int) $cursor['i'] . "'))";
    }

    $limit = max(1, min(200, (int) $limit));

    $rows = (array) db_items("SELECT *, " . WS_TIMELINE_MOMENT . " AS tl_at FROM ws_messages
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tl_at DESC, id DESC
        LIMIT " . ($limit + 1));

    if (count($rows) > $limit) {
        array_pop($rows);
        $last = $rows[count($rows) - 1];
        $result['next'] = array('v' => (int) $last['tl_at'], 'i' => (int) $last['id']);
    }

    $result['rows'] = $rows;

    return $result;
}

/**
 * The private channels a staff member is not in and has not opened, with how
 * many decisions each holds under the filter. Only the channel, the department
 * and the dates narrow the count: the person and the words asked for would
 * tell what those decisions are about.
 *
 * @param array $viewer
 * @param array $filters
 * @return array[] id, name, owner, count, archived
 */
function ws_timeline_hidden($viewer, $filters)
{
    if (($viewer['role'] >= 3) || !$viewer['member'] || ($filters['channel_id'] > 0)) {
        return array();
    }

    $channels = array();

    foreach ((array) db_items("SELECT c.id, c.name, c.department_id, c.owner_user_id, c.archived_at
        FROM ws_channels c
        LEFT JOIN ws_channel_members me ON me.channel_id = c.id AND me.user_id = '" . (int) $viewer['id'] . "'
        WHERE c.kind = 'private' AND me.user_id IS NULL
        ORDER BY c.name") as $row) {

        if (ws_audit_open($row['id'])) {
            continue;
        }

        if (($filters['department_id'] > 0) && ((int) $row['department_id'] !== $filters['department_id'])) {
            continue;
        }

        $channels[(int) $row['id']] = $row;
    }

    if (empty($channels)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT channel_id, COUNT(*) AS n FROM ws_messages
        WHERE " . implode(' AND ', ws_timeline_where(array_keys($channels), $filters, false)) . "
        GROUP BY channel_id") as $row) {

        $channel = $channels[(int) $row['channel_id']];

        $out[] = array(
            'id'       => (int) $channel['id'],
            'name'     => (string) $channel['name'],
            'owner'    => ws_person_name($channel['owner_user_id']),
            'count'    => (int) $row['n'],
            'archived' => ((int) $channel['archived_at'] > 0),
        );
    }

    usort($out, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $out;
}

/**
 * The record changes Claude proposed that became these decisions, by the
 * decision's message id.
 *
 * @param int[] $message_ids
 * @return array[] id => type, type_label, action, record_id
 */
function ws_timeline_claude_changes($message_ids)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !function_exists('ws_changes_ready') || !ws_changes_ready()) {
        return array();
    }

    $types = ws_change_types();
    $out = array();

    foreach ((array) db_items("SELECT decision_message_id, record_type, action, record_id FROM ws_ai_changes
        WHERE decision_message_id IN (" . implode(',', $message_ids) . ") AND status = 'applied'") as $row) {

        $out[(int) $row['decision_message_id']] = array(
            'type'       => (string) $row['record_type'],
            'type_label' => (string) ($types[$row['record_type']]['label'] ?? $row['record_type']),
            'action'     => (string) ($row['action'] ?? 'update'),
            'record_id'  => ((int) $row['record_id'] > 0) ? (int) $row['record_id'] : null,
        );
    }

    return $out;
}

/**
 * A day as the timeline heads it: "Today", "Yesterday", "22 September,
 * Tuesday", with the year once it is not this year's.
 *
 * @param string $date Y-m-d
 * @return string
 */
function ws_timeline_day_label($date)
{
    $time = strtotime($date . ' 12:00:00');

    if ($time === false) {
        return '';
    }

    if ($date === date('Y-m-d')) {
        return lang('Today');
    }

    if ($date === date('Y-m-d', strtotime('-1 day'))) {
        return lang('Yesterday');
    }

    $weekdays = array(
        1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'),
        5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'),
    );

    $label = (int) date('j', $time) . ' ' . ws_recurrence_month_names()[(int) date('n', $time)];

    if (date('Y', $time) !== date('Y')) {
        $label .= ' ' . date('Y', $time);
    }

    return $label . ', ' . $weekdays[(int) date('N', $time)];
}

/**
 * The decisions as the screen draws them. Checklists are drawn as they stand,
 * without their boxes to tick: ticking belongs to the conversation.
 *
 * @param array   $viewer
 * @param array[] $rows     ws_timeline() rows
 * @param array[] $channels ws_timeline() channels
 * @return array[]
 */
function ws_timeline_items($viewer, $rows, $channels)
{
    $tokens = array();
    $people_ids = array();
    $ids = array();

    foreach ($rows as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));
        $ids[] = (int) $row['id'];

        if ($row['sender_kind'] === 'user') {
            $people_ids[] = (int) $row['sender_id'];
        }

        if ((int) $row['marked_by'] > 0) {
            $people_ids[] = (int) $row['marked_by'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people(array_unique($people_ids));
    $checks = ws_checks_map($ids);
    $claude = ws_timeline_claude_changes($ids);
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $out = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $channel = $channels[(int) $row['channel_id']] ?? null;
        $at = (int) $row['tl_at'];
        $day = date('Y-m-d', $at);
        $sender = null;

        if ($row['sender_kind'] === 'user') {
            $sender = $people[(int) $row['sender_id']] ?? null;
        } elseif ($row['sender_kind'] === 'app') {
            $sender = ws_app_sender((int) $row['sender_id']);
        }

        $marked_by = (int) $row['marked_by'];
        $file = null;

        if ((int) $row['file_id'] > 0) {
            $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'");

            if ($stored !== '') {
                $file = array(
                    'name'  => (string) $row['file_name'],
                    'url'   => PATH . encode_url_path($stored),
                    'image' => (ws_file_kind($row['file_name']) === 'image'),
                );
            }
        }

        $out[] = array(
            'id'        => $id,
            'kind'      => (string) $row['kind'],
            'html'      => ws_render_body($row['body'], $refs, $checks[$id] ?? array(), false),
            'file'      => $file,
            'sender'    => $sender,
            // Who took it as a decision, when that was not the writer.
            'marked_by' => (($marked_by > 0) && !(($row['sender_kind'] === 'user') && ((int) $row['sender_id'] === $marked_by)))
                ? ($people[$marked_by]['name'] ?? '')
                : '',
            'locked'    => !empty($row['locked']),
            'edited'    => ((int) $row['edited_at'] > 0),
            'day'       => $day,
            'day_label' => ws_timeline_day_label($day),
            'time'      => date('H:i', $at),
            'timestamp' => $at,
            'channel'   => array(
                'id'       => (int) $row['channel_id'],
                'name'     => (string) ($channel['name'] ?? ''),
                'private'  => (($channel['kind'] ?? '') === 'private'),
                'archived' => ((int) ($channel['archived_at'] ?? 0) > 0),
            ),
            'claude'    => $claude[$id] ?? null,
            'url'       => $base . '?channel=' . (int) $row['channel_id'] . '&message=' . $id,
        );
    }

    return $out;
}

/**
 * The screen's answer for one request: a page of decisions, and on the first
 * page the channels, the counts and the private channels left out.
 *
 * @param array $viewer
 * @param array $request filters and cursor ("moment.id" of the last row shown)
 * @return array
 */
function ws_timeline_page($viewer, $request)
{
    $filters = ws_timeline_filters($request);
    $cursor = null;

    if (preg_match('/^([0-9]{1,12})\.([0-9]{1,12})$/', (string) ($request['cursor'] ?? ''), $parts)) {
        $cursor = array('v' => (int) $parts[1], 'i' => (int) $parts[2]);
    }

    $result = ws_timeline($viewer, $filters, $cursor);

    $page = array(
        'items' => ws_timeline_items($viewer, $result['rows'], $result['channels']),
        'next'  => ($result['next'] !== null) ? ($result['next']['v'] . '.' . $result['next']['i']) : '',
    );

    if ($cursor === null) {
        $channels = array();

        foreach ($result['channels'] as $id => $channel) {
            $channels[] = array(
                'id'       => (int) $id,
                'name'     => (string) $channel['name'],
                'private'  => ($channel['kind'] === 'private'),
                'archived' => ((int) $channel['archived_at'] > 0),
                'count'    => (int) ($result['counts'][$id] ?? 0),
            );
        }

        $page['channels'] = $channels;
        $page['total'] = array_sum($result['counts']);
        $page['hidden'] = ws_timeline_hidden($viewer, $filters);
    }

    return $page;
}

/**
 * The texts of the timeline screen.
 *
 * @return array key => text
 */
function ws_timeline_js_strings()
{
    return array(
        'tl_all_channels'   => lang('All channels'),
        'tl_archived_group' => lang('Archived channels'),
        'tl_from'           => lang('Start date'),
        'tl_to'             => lang('End date'),
        'tl_notes'          => lang('Notes too'),
        'tl_search'         => lang('Search the decisions'),
        'tl_empty'          => lang('No decision matches these filters.'),
        'tl_empty_all'      => lang('No decision has been taken yet. A message marked as a decision in a channel shows here.'),
        'tl_more'           => lang('Older decisions'),
        'tl_total'          => ws_js_template('{var:1} decision(s)', 1),
        'tl_total_notes'    => ws_js_template('{var:1} decision(s) and note(s)', 1),
        'tl_by_channel'     => lang('By channel'),
        'tl_marked_by'      => ws_js_template('Marked by {var:1}', 1),
        'tl_claude'         => lang('On Claude\'s proposal'),
        'tl_archived'       => lang('Archived channel'),
        'tl_private'        => lang('Private channel'),
        'tl_hidden'         => ws_js_template('{var:1} private channel(s) you are not in hold {var:2} decision(s). What they say is not shown here.', 2),
        'tl_hidden_row'     => ws_js_template('{var:1} decision(s) · owner {var:2}', 2),
        'tl_hidden_help'    => lang('Opening one for inspection leaves a line in the channel and in the activity log; its decisions then join this list for as long as you are signed in.'),
        'tl_clear'          => lang('Clear filters'),
    );
}
