<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: the E-mails list (workspace_mail.php). The e-mail
 * conversations of every channel the reader may read, newest mail first,
 * in three views (Incoming, Sent, Not sent), narrowed by status, holder, an
 * overdue first answer, the mailbox and a search, a page at a time; each
 * row opens the conversation in the reading pane (mail_pane.php), or its
 * card in its channel without the script. Who may read a channel decides
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

/** The conversations one page of the list shows; Load more brings the next. */
define('WS_MAIL_LIST_PAGE', 50);

/** The longest search the list takes, in characters. */
define('WS_MAIL_LIST_QUERY_MAX', 100);

/**
 * The list's filters as asked in the address, each a known word. Pure.
 *
 * @param array $query view (incoming | sent | unsent | all), status (open |
 *                     waiting | closed | all; open in Incoming, else all by
 *                     default), holder (all | me | none), overdue (1), box
 *                     (a mailbox id), q (the search), before (the cursor)
 * @return array view, status, holder, overdue (bool), box (int), q, before
 *               (null or [time, id])
 */
function ws_mail_list_filters($query)
{
    $word = function ($key, $known, $default) use ($query) {
        $value = is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';

        return in_array($value, $known, true) ? $value : $default;
    };

    $view = $word('view', array('incoming', 'sent', 'unsent', 'all'), 'incoming');
    $search = is_scalar($query['q'] ?? null) ? trim(mb_substr(trim((string) $query['q']), 0, WS_MAIL_LIST_QUERY_MAX)) : '';

    return array(
        'view'    => $view,
        'status'  => $word('status', array('open', 'waiting', 'closed', 'all'), ($view === 'incoming') ? 'open' : 'all'),
        'holder'  => $word('holder', array('all', 'me', 'none'), 'all'),
        'overdue' => !empty($query['overdue']) && ((string) $query['overdue'] === '1'),
        'box'     => (is_scalar($query['box'] ?? null) && ctype_digit((string) $query['box'])) ? (int) $query['box'] : 0,
        'q'       => $search,
        'before'  => ws_mail_list_cursor($query['before'] ?? ''),
    );
}

/**
 * A page's cursor as the address carries it ("time-id"). Pure.
 *
 * @param mixed $value
 * @return int[]|null [newest mail time, conversation id]
 */
function ws_mail_list_cursor($value)
{
    if (!is_scalar($value) || !preg_match('/^([0-9]{1,10})-([0-9]{1,10})$/', (string) $value, $match)) {
        return null;
    }

    return array((int) $match[1], (int) $match[2]);
}

/**
 * The address words of a list, defaults left out. Pure.
 *
 * @param array $filters ws_mail_list_filters()
 * @param array $change  words to set ('' or null drops one)
 * @return string the query string, without "?"
 */
function ws_mail_list_query($filters, $change = array())
{
    $words = array(
        'view'    => ($filters['view'] !== 'incoming') ? $filters['view'] : '',
        'status'  => ($filters['status'] !== (($filters['view'] === 'incoming') ? 'open' : 'all')) ? $filters['status'] : '',
        'holder'  => ($filters['holder'] !== 'all') ? $filters['holder'] : '',
        'overdue' => $filters['overdue'] ? '1' : '',
        'box'     => ($filters['box'] > 0) ? (string) $filters['box'] : '',
        'q'       => $filters['q'],
    );

    foreach ($change as $key => $value) {
        $words[$key] = (string) $value;
    }

    return http_build_query(array_filter($words, 'strlen'));
}

/**
 * The conditions the list's query reads conversations by, for a query that
 * names ws_mail_conversations without an alias. Pure but for the escaping
 * of the search.
 *
 * @param array  $filters     ws_mail_list_filters()
 * @param int    $viewer_id
 * @param int[]  $channel_ids the channels the reader may read
 * @param string $charset     the connection charset the search folds with
 *                            (ws_search_like_sql()), '' for the plain match
 * @return string SQL after WHERE, '' when nothing can match
 */
function ws_mail_list_where($filters, $viewer_id, $channel_ids, $charset = '')
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
    if (!empty($filters['overdue'])) {
        $where[] = 'first_reply_at = 0';
        $where[] = "status <> 'closed'";
    }

    if ((int) ($filters['box'] ?? 0) > 0) {
        $where[] = "box_id = '" . (int) $filters['box'] . "'";
    }

    // An answer written before the send state is sent ('').
    $out = "EXISTS (SELECT 1 FROM ws_mail_items i WHERE i.conversation_id = ws_mail_conversations.id AND i.direction = 'out' AND i.send_state IN ";
    $view = (string) ($filters['view'] ?? 'all');

    if ($view === 'incoming') {
        $where[] = 'last_in_at > 0';
    } elseif ($view === 'sent') {
        $where[] = $out . "('', 'sent'))";
    } elseif ($view === 'unsent') {
        $where[] = $out . "('queued', 'failed'))";
    }

    // The subject and customer, or the names, addresses and text of a mail.
    $search = (string) ($filters['q'] ?? '');

    if ($search !== '') {
        $where[] = '(' . ws_search_like_sql($search, array('subject', 'customer_name', 'customer_email'), $charset)
            . ' OR EXISTS (SELECT 1 FROM ws_mail_items i WHERE i.conversation_id = ws_mail_conversations.id AND '
            . ws_search_like_sql($search, array('i.from_name', 'i.from_email', 'i.to_list', 'i.cc_list', 'i.body_text'), $charset) . '))';
    }

    if (!empty($filters['before'])) {
        $last = 'GREATEST(last_in_at, last_out_at)';
        list($time, $id) = $filters['before'];
        $where[] = '(' . $last . " < '" . (int) $time . "' OR (" . $last . " = '" . (int) $time . "' AND id < '" . (int) $id . "'))";
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
    static $cache = array();

    $key = (int) ($viewer['id'] ?? 0);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $out = array();

    foreach ((array) db_values("SELECT DISTINCT channel_id FROM ws_mail_conversations") as $channel_id) {
        $channel = ws_channel((int) $channel_id);

        if ($channel && ws_can_read_channel($viewer, $channel)) {
            $out[] = (int) $channel_id;
        }
    }

    return $cache[$key] = $out;
}

/**
 * The mailboxes whose conversations the reader sees, for the filter.
 *
 * @param array $viewer
 * @return array id => name
 */
function ws_mail_list_boxes($viewer)
{
    $channel_ids = ws_mail_list_channels($viewer);
    $out = array();

    if (!$channel_ids) {
        return $out;
    }

    foreach ((array) db_items("SELECT id, name FROM ws_mail_boxes WHERE id IN (SELECT DISTINCT box_id FROM ws_mail_conversations
        WHERE channel_id IN (" . implode(',', array_map('intval', $channel_ids)) . ")) ORDER BY name") as $box) {
        $out[(int) $box['id']] = (string) $box['name'];
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
 * One page of the conversations, newest mail first, each with its box's
 * name and target, its channel's name, its holder's name and what its row
 * shows of its mails (ws_mail_list_mails()).
 *
 * @param array $viewer
 * @param array $filters ws_mail_list_filters()
 * @param int   $now
 * @return array rows (conversation rows with box_name, channel_name,
 *               assignee_name, first_reply_hours, overdue, mails, snippet,
 *               unread, unsent), next (the next page's cursor, '' for none)
 */
function ws_mail_list($viewer, $filters, $now)
{
    $charset = function_exists('pg_list_search_connection_charset') ? pg_list_search_connection_charset() : '';
    $where = ws_mail_list_where($filters, (int) $viewer['id'], ws_mail_list_channels($viewer), $charset);

    if ($where === '') {
        return array('rows' => array(), 'next' => '');
    }

    // Overdue rows are found among more, since the target is per box.
    $limit = $filters['overdue'] ? WS_MAIL_LIST_PAGE * 5 : WS_MAIL_LIST_PAGE + 1;
    $rows = (array) db_items("SELECT * FROM ws_mail_conversations WHERE " . $where . "
        ORDER BY GREATEST(last_in_at, last_out_at) DESC, id DESC LIMIT " . $limit);

    $boxes = array();

    foreach ((array) db_items("SELECT id, name, settings FROM ws_mail_boxes") as $box) {
        $boxes[(int) $box['id']] = array('name' => (string) $box['name'], 'hours' => ws_mail_first_reply_hours($box));
    }

    $out = array();
    $seen = null;
    $more = false;

    foreach ($rows as $row) {
        if (count($out) >= WS_MAIL_LIST_PAGE) {
            $more = true;
            break;
        }

        $seen = $row;
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
    }

    // A full window of overdue candidates may have more behind it.
    if (!$more && $filters['overdue'] && (count($rows) >= $limit)) {
        $more = true;
    }

    return array(
        'rows' => ws_mail_list_mails((int) $viewer['id'], $out),
        'next' => ($more && $seen) ? max((int) $seen['last_in_at'], (int) $seen['last_out_at']) . '-' . (int) $seen['id'] : '',
    );
}

/**
 * What a row shows of its conversation's mails: how many, the newest's
 * first words, whether it is unread for the reader and whether a mail of
 * it waits to be sent or was not sent.
 *
 * @param int     $viewer_id
 * @param array[] $rows ws_mail_conversations rows
 * @return array[] the rows with mails, snippet, unread (bool), unsent ('' | waiting | failed)
 */
function ws_mail_list_mails($viewer_id, $rows)
{
    $ids = array_map(function ($row) {
        return (int) $row['id'];
    }, $rows);

    if (!$ids) {
        return $rows;
    }

    $reads = ws_mail_reads_ready();
    $stats = array();
    $newest = array();

    foreach ((array) db_items("SELECT conversation_id, COUNT(*) AS mails, MAX(id) AS last_id,
            MAX(CASE WHEN direction = 'in' THEN id ELSE 0 END) AS last_in_id"
            . ($reads ? ", MAX(send_state = 'failed') AS failed, MAX(send_state = 'queued') AS queued" : '') . "
        FROM ws_mail_items WHERE conversation_id IN (" . implode(',', $ids) . ") GROUP BY conversation_id") as $stat) {
        $stats[(int) $stat['conversation_id']] = $stat;
    }

    $last_ids = array_filter(array_map(function ($stat) {
        return (int) $stat['last_id'];
    }, $stats));

    if ($last_ids) {
        foreach ((array) db_items("SELECT conversation_id, LEFT(body_text, 240) AS snippet FROM ws_mail_items WHERE id IN (" . implode(',', $last_ids) . ")") as $item) {
            $newest[(int) $item['conversation_id']] = (string) $item['snippet'];
        }
    }

    $floor = $reads ? ws_mail_read_floor($viewer_id) : PHP_INT_MAX;
    $marks = $reads ? ws_mail_read_marks($viewer_id, $ids) : array();

    foreach ($rows as $index => $row) {
        $id = (int) $row['id'];
        $stat = $stats[$id] ?? array();

        $rows[$index] += array(
            'mails'   => (int) ($stat['mails'] ?? 0),
            'snippet' => trim((string) preg_replace('/\s+/u', ' ', $newest[$id] ?? '')),
            'unread'  => ws_mail_unread((int) ($stat['last_in_id'] ?? 0), (int) ($marks[$id] ?? 0), $floor),
            'unsent'  => !empty($stat['failed']) ? 'failed' : (!empty($stat['queued']) ? 'waiting' : ''),
        );
    }

    return $rows;
}

/**
 * The rows of a page of the list. A row opens the conversation's card in
 * its channel; the screen's script opens it in the pane instead.
 *
 * @param array[] $rows ws_mail_list() rows
 * @param string  $base the panel's address, ending in /
 * @param int     $now
 * @return string HTML
 */
function ws_mail_list_rows_html($rows, $base, $now)
{
    $html = '';

    foreach ($rows as $row) {
        $url = $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'];
        $subject = (trim((string) $row['subject']) !== '') ? (string) $row['subject'] : lang('(no subject)');
        $who = (trim((string) $row['customer_name']) !== '') ? (string) $row['customer_name'] : (string) $row['customer_email'];
        $last = max((int) $row['last_in_at'], (int) $row['last_out_at']);
        $unsent = (string) ($row['unsent'] ?? '');
        $classes = 'ws-mail-row' . (!empty($row['unread']) ? ' ws-mail-unread' : '') . (!empty($row['overdue']) ? ' ws-mail-overdue' : '');

        $html .= '<a href="' . h($url) . '" class="' . $classes . '" data-ws-mail-row="' . (int) $row['id'] . '">'
            . '<span class="ws-mail-row-head"><span class="ws-mail-who">' . h($who) . '</span>'
            . (!empty($row['unread']) ? '<span class="visually-hidden">' . h(lang('Unread')) . '</span>' : '')
            . (((int) ($row['mails'] ?? 0) > 1) ? '<span class="ws-count" title="' . h(lang('E-mails')) . '">' . (int) $row['mails'] . '</span>' : '')
            . '<span class="ws-mail-time">' . h(ws_time_label($last)) . '</span></span>'
            . '<span class="ws-mail-subject">' . h($subject) . '</span>'
            . ((string) ($row['snippet'] ?? '') !== '' ? '<span class="ws-mail-snippet">' . h((string) $row['snippet']) . '</span>' : '')
            . '<span class="ws-mail-row-meta">'
            . (($unsent === 'failed') ? '<span class="badge theme-danger">' . h(lang('Not sent')) . '</span>' : '')
            . (($unsent === 'waiting') ? '<span class="badge theme-secondary">' . h(lang('Waiting to be sent')) . '</span>' : '')
            . (((string) $row['box_name'] !== '') ? '<span>' . h((string) $row['box_name']) . '</span>' : '')
            . '<span>' . h('#' . (string) $row['channel_name']) . '</span>'
            . '<span>' . ws_mail_status_html($row, (string) $row['assignee_name'], (int) $row['first_reply_hours'], $now) . '</span>'
            . '</span></a>';
    }

    return $html;
}

/**
 * The list, or a line saying it is empty, with Load more under it while
 * another page follows.
 *
 * @param array  $list    ws_mail_list()
 * @param array  $filters ws_mail_list_filters()
 * @param string $base    the panel's address, ending in /
 * @param int    $now
 * @return string HTML
 */
function ws_mail_list_html($list, $filters, $base, $now)
{
    if (!$list['rows']) {
        return '<p class="fg-secondary my-5 px-3">' . h(lang('No e-mail conversation matches.')) . '</p>';
    }

    return '<div class="ws-mail-rows" data-ws-mail-rows>' . ws_mail_list_rows_html($list['rows'], $base, $now) . '</div>'
        . ws_mail_list_more_html($filters, $list['next']);
}

/**
 * Load more: a link to the next page, which the screen's script reads in
 * place under the rows.
 *
 * @param array  $filters ws_mail_list_filters()
 * @param string $next    the next page's cursor, '' for none
 * @return string HTML
 */
function ws_mail_list_more_html($filters, $next)
{
    if ((string) $next === '') {
        return '';
    }

    return '<div class="ws-mail-more"><a class="btn btn-sm btn-outline theme-secondary" data-ws-mail-more href="workspace_mail.php?'
        . h(ws_mail_list_query($filters, array('before' => $next))) . '">' . h(lang('Load more')) . '</a></div>';
}

/**
 * The screen toolbar: the views, Status, Held by and Mailbox as selects
 * (each keeping the other words), "First answer overdue" as a toggle, the
 * search and Mark all as read.
 *
 * @param array $filters ws_mail_list_filters()
 * @param array $boxes   ws_mail_list_boxes()
 * @return string HTML
 */
function ws_mail_list_filters_html($filters, $boxes = array())
{
    $statuses = ws_mail_statuses() + array('all' => lang('All'));
    $holders = array('all' => lang('Anybody'), 'me' => lang('Held by me'), 'none' => lang('Held by nobody'));
    $keep = array('view', 'status', 'holder', 'overdue', 'box', 'q');

    $overdue = '<a class="btn btn-sm btn-text theme-secondary' . ($filters['overdue'] ? ' active' : '') . '" aria-pressed="' . ($filters['overdue'] ? 'true' : 'false') . '"'
        . ' href="workspace_mail.php?' . h(ws_mail_list_query($filters, array('overdue' => $filters['overdue'] ? '' : '1'))) . '"><i class="bi bi-alarm me-1" aria-hidden="true"></i>' . h(lang('First answer overdue')) . '</a>';

    $search = array(
        pg_filter_select('status', $statuses, $filters['status'], array('label' => lang('Status'), 'icon' => 'inbox', 'keep' => $keep)),
        pg_filter_select('holder', $holders, $filters['holder'], array('label' => lang('Held by'), 'icon' => 'person', 'keep' => $keep)),
    );

    if (count($boxes) > 1) {
        $search[] = pg_filter_select('box', array('' => lang('All mailboxes')) + $boxes, ($filters['box'] > 0) ? (string) $filters['box'] : '',
            array('label' => lang('Mailbox'), 'icon' => 'mailbox', 'keep' => $keep));
    }

    $search[] = $overdue;

    // The search keeps every other word; a new search starts at page one.
    $hidden = '';

    foreach (array('view', 'status', 'holder', 'overdue', 'box') as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key]) && ((string) $_GET[$key] !== '')) {
            $hidden .= '<input type="hidden" name="' . h($key) . '" value="' . h((string) $_GET[$key]) . '">';
        }
    }

    $search[] = '<form method="get" class="pg-toolbar-filter disable_shortcut" role="search">' . $hidden
        . '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">'
        . '<span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>'
        . '<input type="search" class="form-ghost" name="q" value="' . h($filters['q']) . '" maxlength="' . WS_MAIL_LIST_QUERY_MAX . '"'
        . ' placeholder="' . h(lang('Search e-mails')) . '" aria-label="' . h(lang('Search e-mails')) . '"></label></form>';

    $views = '';

    foreach (array('incoming' => array(lang('Incoming'), 'inbox'), 'sent' => array(lang('Sent'), 'send'), 'unsent' => array(lang('Not sent'), 'exclamation-circle')) as $view => $label) {
        // A view starts with its own default status.
        $query = ws_mail_list_query(array_merge($filters, array('view' => $view, 'status' => ($view === 'incoming') ? 'open' : 'all')), array('overdue' => ''));
        $active = ($filters['view'] === $view);
        $views .= '<a class="btn btn-sm btn-text theme-secondary' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="page"' : '')
            . ' href="workspace_mail.php' . (($query !== '') ? '?' . h($query) : '') . '"><i class="bi bi-' . $label[1] . ' me-1" aria-hidden="true"></i>' . h($label[0]) . '</a>';
    }

    return pg_screen_toolbar(array(
        'secondary' => '<button type="button" class="btn btn-sm btn-text theme-secondary" data-ws-mail-read-all><i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . h(lang('Mark all as read')) . '</button>',
        'search'    => $search,
        'view'      => '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">' . $views . '</div>',
    ));
}
