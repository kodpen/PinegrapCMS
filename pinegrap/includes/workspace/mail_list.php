<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the E-mails list (workspace_mail.php). The e-mail
 * conversations of every channel the reader may read, newest mail first,
 * narrowed by status, holder and an overdue first answer; each row opens
 * the conversation's card in its channel. Who may read a channel decides
 * which conversations appear (ws_can_read_channel()), never the box.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most conversations one page of the list shows. */
define('WS_MAIL_LIST_MAX', 100);

/**
 * The list's filters as asked in the address, each a known word. Pure.
 *
 * @param array $query status (open | waiting | closed | all), holder (all | me | none), overdue (1)
 * @return array status, holder, overdue (bool)
 */
function ws_mail_list_filters($query)
{
    $word = function ($key, $known, $default) use ($query) {
        $value = is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';

        return in_array($value, $known, true) ? $value : $default;
    };

    return array(
        'status'  => $word('status', array('open', 'waiting', 'closed', 'all'), 'open'),
        'holder'  => $word('holder', array('all', 'me', 'none'), 'all'),
        'overdue' => !empty($query['overdue']) && ((string) $query['overdue'] === '1'),
    );
}

/**
 * The conditions the list's query reads conversations by. Pure.
 *
 * @param array $filters     ws_mail_list_filters()
 * @param int   $viewer_id
 * @param int[] $channel_ids the channels the reader may read
 * @return string SQL after WHERE, '' when nothing can match
 */
function ws_mail_list_where($filters, $viewer_id, $channel_ids)
{
    $channel_ids = array_values(array_filter(array_map('intval', (array) $channel_ids)));

    if (!$channel_ids) {
        return '';
    }

    $where = array('channel_id IN (' . implode(',', $channel_ids) . ')', 'message_id > 0');

    if ($filters['status'] !== 'all') {
        $where[] = "status = '" . $filters['status'] . "'";
    }

    if ($filters['holder'] === 'me') {
        $where[] = "assignee_id = '" . (int) $viewer_id . "'";
    } elseif ($filters['holder'] === 'none') {
        $where[] = 'assignee_id = 0';
    }

    // Overdue is worked out per box afterwards; only the unanswered can be.
    if ($filters['overdue']) {
        $where[] = 'first_reply_at = 0';
        $where[] = "status <> 'closed'";
    }

    return implode(' AND ', $where);
}

/**
 * The channels that hold e-mail conversations and the reader may read.
 *
 * @param array $viewer
 * @return int[]
 */
function ws_mail_list_channels($viewer)
{
    $out = array();

    foreach ((array) db_values("SELECT DISTINCT channel_id FROM ws_mail_conversations") as $channel_id) {
        $channel = ws_channel((int) $channel_id);

        if ($channel && ws_can_read_channel($viewer, $channel)) {
            $out[] = (int) $channel_id;
        }
    }

    return $out;
}

/**
 * Whether the E-mails place is in the reader's list: a box writes into a
 * channel, one of its routes' included, that they may read.
 *
 * @param array $viewer
 * @return bool
 */
function ws_mail_list_visible($viewer)
{
    if (!ws_mail_ready() || empty($viewer['member'])) {
        return false;
    }

    foreach ((array) db_items("SELECT id, channel_id, settings FROM ws_mail_boxes ORDER BY id LIMIT 50") as $box) {
        $channel_ids = array((int) $box['channel_id']);

        foreach (ws_mail_routes($box) as $route) {
            $channel_ids[] = (int) $route['channel_id'];
        }

        foreach (array_unique($channel_ids) as $channel_id) {
            $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

            if ($channel && ws_can_read_channel($viewer, $channel)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The conversations the list shows, newest mail first, each with its box's
 * name and target, its channel's name and its holder's name.
 *
 * @param array $viewer
 * @param array $filters ws_mail_list_filters()
 * @param int   $now
 * @return array[] conversation rows with box_name, channel_name, assignee_name, first_reply_hours, overdue
 */
function ws_mail_list($viewer, $filters, $now)
{
    $where = ws_mail_list_where($filters, (int) $viewer['id'], ws_mail_list_channels($viewer));

    if ($where === '') {
        return array();
    }

    // Overdue rows are found among more, since the target is per box.
    $rows = (array) db_items("SELECT * FROM ws_mail_conversations WHERE " . $where . "
        ORDER BY GREATEST(last_in_at, last_out_at) DESC, id DESC
        LIMIT " . ($filters['overdue'] ? WS_MAIL_LIST_MAX * 5 : WS_MAIL_LIST_MAX));

    $boxes = array();

    foreach ((array) db_items("SELECT id, name, settings FROM ws_mail_boxes") as $box) {
        $boxes[(int) $box['id']] = array('name' => (string) $box['name'], 'hours' => ws_mail_first_reply_hours($box));
    }

    $out = array();

    foreach ($rows as $row) {
        $box = $boxes[(int) $row['box_id']] ?? array('name' => '', 'hours' => 0);
        $overdue = ws_mail_first_reply_overdue($row, $box['hours'], $now);

        if ($filters['overdue'] && !$overdue) {
            continue;
        }

        $channel = ws_channel((int) $row['channel_id']);

        $out[] = $row + array(
            'box_name'          => $box['name'],
            'channel_name'      => $channel ? (string) $channel['name'] : '',
            'assignee_name'     => ((int) $row['assignee_id'] > 0) ? ws_person_name((int) $row['assignee_id']) : '',
            'first_reply_hours' => $box['hours'],
            'overdue'           => $overdue,
        );

        if (count($out) >= WS_MAIL_LIST_MAX) {
            break;
        }
    }

    return $out;
}

/**
 * The list as a table, or a line saying it is empty.
 *
 * @param array[] $rows ws_mail_list()
 * @param string  $base the panel's address, ending in /
 * @param int     $now
 * @return string HTML
 */
function ws_mail_list_html($rows, $base, $now)
{
    if (!$rows) {
        return '<p class="fg-secondary my-5">' . h(lang('No e-mail conversation matches.')) . '</p>';
    }

    $html = '<div class="table-responsive"><table class="table table-sm align-middle ws-mail-list">
        <thead><tr>
            <th scope="col">' . h(lang('Subject')) . '</th>
            <th scope="col">' . h(lang('Customer')) . '</th>
            <th scope="col">' . h(lang('Status')) . '</th>
            <th scope="col">' . h(lang('Channel')) . '</th>
            <th scope="col">' . h(lang('Last e-mail')) . '</th>
        </tr></thead><tbody>';

    foreach ($rows as $row) {
        $url = $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'];
        $subject = (trim((string) $row['subject']) !== '') ? (string) $row['subject'] : lang('(no subject)');
        $customer = (trim((string) $row['customer_name']) !== '') ? (string) $row['customer_name'] : (string) $row['customer_email'];
        $last = max((int) $row['last_in_at'], (int) $row['last_out_at']);

        $html .= '<tr' . (!empty($row['overdue']) ? ' class="ws-mail-overdue"' : '') . '>
            <td><a href="' . h($url) . '" class="fw-semibold">' . h($subject) . '</a>'
                . (((string) $row['box_name'] !== '') ? '<div class="small fg-secondary">' . h((string) $row['box_name']) . '</div>' : '') . '</td>
            <td>' . h($customer) . (($customer !== (string) $row['customer_email']) ? '<div class="small fg-secondary">' . h((string) $row['customer_email']) . '</div>' : '') . '</td>
            <td class="small">' . ws_mail_status_html($row, (string) $row['assignee_name'], (int) $row['first_reply_hours'], $now) . '</td>
            <td class="small">' . h('#' . (string) $row['channel_name']) . '</td>
            <td class="small text-nowrap">' . h(ws_time_label($last)) . '</td>
        </tr>';
    }

    return $html . '</tbody></table></div>';
}

/**
 * The filters as links, the chosen one marked.
 *
 * @param array $filters ws_mail_list_filters()
 * @return string HTML
 */
function ws_mail_list_filters_html($filters)
{
    $link = function ($changes, $label, $on) use ($filters) {
        $query = array_merge($filters, $changes);
        $query['overdue'] = $query['overdue'] ? '1' : '';

        return '<a class="btn btn-sm ' . ($on ? 'btn-solid theme-primary' : 'btn-outline theme-secondary') . ' rounded-pill"'
            . ($on ? ' aria-current="true"' : '') . ' href="workspace_mail.php?' . h(http_build_query(array_filter($query, 'strlen'))) . '">' . h($label) . '</a>';
    };

    $statuses = ws_mail_statuses() + array('all' => lang('All'));
    $holders = array('all' => lang('Anybody'), 'me' => lang('Held by me'), 'none' => lang('Held by nobody'));
    $html = '<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="' . h(lang('Status')) . '">';

    foreach ($statuses as $key => $label) {
        $html .= $link(array('status' => $key), $label, $filters['status'] === $key);
    }

    $html .= '</div><div class="d-flex flex-wrap gap-2 mb-5" role="group" aria-label="' . h(lang('Held by')) . '">';

    foreach ($holders as $key => $label) {
        $html .= $link(array('holder' => $key), $label, $filters['holder'] === $key);
    }

    return $html . $link(array('overdue' => !$filters['overdue']), lang('First answer overdue'), $filters['overdue']) . '</div>';
}
