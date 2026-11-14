<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends, Calendar view: what is waiting to be sent, drawn by
 * day and hour. Scheduled e-mail and SMS campaigns, the next runs of the
 * automation flows that start at a time and send a message, and the e-mails
 * waiting under Send later. It only reads: nothing is sent or moved from it.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_campaigns.php');

/**
 * How many runs of one flow a calendar draws at most: a flow repeating every
 * few minutes would fill a month with thousands of chips.
 */
define('PG_MESSAGES_CALENDAR_FLOW_CAP', 100);

/**
 * How many sends a month cell lists before "N more".
 */
define('PG_MESSAGES_CALENDAR_MONTH_SHOWN', 4);

/**
 * The kinds a calendar draws, each with its English label, icon and tone.
 *
 * @return array kind => label, icon, tone
 */
function pg_messages_calendar_kinds()
{
    return array(
        'campaign' => array('label' => 'E-mail campaign', 'icon' => 'bi-envelope', 'tone' => 'blue'),
        'sms'      => array('label' => 'SMS campaign', 'icon' => 'bi-chat-dots', 'tone' => 'amber'),
        'flow'     => array('label' => 'Automatic send', 'icon' => 'bi-diagram-3', 'tone' => 'violet'),
        'mail'     => array('label' => 'Send later', 'icon' => 'bi-clock', 'tone' => 'teal'),
    );
}

/**
 * The calendar's view and day read from the address (pure): week (default)
 * or month, and a day in it as Y-m-d; a day that is not a real date falls
 * back to today.
 *
 * @param array  $get
 * @param string $today Y-m-d
 * @return array view, date
 */
function pg_messages_calendar_filters($get, $today)
{
    $view = (isset($get['cal']) && ($get['cal'] === 'month')) ? 'month' : 'week';
    $date = (isset($get['date']) && is_string($get['date'])) ? $get['date'] : '';

    if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $date, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        $date = (string) $today;
    }

    return array('view' => $view, 'date' => $date);
}

/**
 * The days a view draws, Monday first (pure): the week of the date, or the
 * whole weeks that hold the date's month. Each day says whether it is in the
 * month asked (a week's days always are) and whether it is a weekend.
 *
 * @param string $view week or month
 * @param string $date Y-m-d
 * @return array[] date, day (number), in_range, weekend
 */
function pg_messages_calendar_days($view, $date)
{
    $anchor = new DateTime($date . ' 12:00:00');
    $month = $anchor->format('Y-m');

    if ($view === 'month') {
        $first = new DateTime($month . '-01 12:00:00');
        $last = new DateTime($first->format('Y-m-t') . ' 12:00:00');
    } else {
        $first = clone $anchor;
        $last = clone $anchor;
    }

    $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
    $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
    $days = array();

    for ($day = clone $first; $day <= $last; $day->modify('+1 day')) {
        $days[] = array(
            'date'     => $day->format('Y-m-d'),
            'day'      => (int) $day->format('j'),
            'in_range' => ($view !== 'month') || ($day->format('Y-m') === $month),
            'weekend'  => ((int) $day->format('N') >= 6),
        );
    }

    return $days;
}

/**
 * The first moment of the first day and the last of the last (pure, in the
 * zone PHP runs in).
 *
 * @param array[] $days pg_messages_calendar_days()
 * @return int[] from, to
 */
function pg_messages_calendar_bounds($days)
{
    $first = reset($days);
    $last = end($days);

    return array((int) strtotime($first['date'] . ' 00:00:00'), (int) strtotime($last['date'] . ' 23:59:59'));
}

/**
 * The day a view moves to with the previous and next buttons (pure).
 *
 * @param string $view week or month
 * @param string $date Y-m-d
 * @param int    $step -1 or 1
 * @return string Y-m-d
 */
function pg_messages_calendar_step($view, $date, $step)
{
    $day = new DateTime(($view === 'month') ? substr($date, 0, 7) . '-01 12:00:00' : $date . ' 12:00:00');
    $day->modify((($step < 0) ? '-' : '+') . (($view === 'month') ? '1 month' : '7 days'));

    return $day->format('Y-m-d');
}

/**
 * The runs of a flow inside a period (pure but for $next_run): the kept next
 * run first, then each following one the rule gives, at most $cap.
 *
 * @param int      $next     the anchor's next_run_at
 * @param int      $from
 * @param int      $to
 * @param int      $cap
 * @param callable $next_run function (int $after): int, 0 for no more
 * @return int[]
 */
function pg_messages_calendar_flow_times($next, $from, $to, $cap, $next_run)
{
    $times = array();
    $at = (int) $next;

    // The kept run lies before the period: the first one in it.
    if (($at > 0) && ($at < $from)) {
        $at = (int) call_user_func($next_run, $from - 1);
    }

    while (($at > 0) && ($at <= $to) && (count($times) < $cap)) {
        $times[] = $at;
        $following = (int) call_user_func($next_run, $at);
        $at = ($following > $at) ? $following : 0;
    }

    return $times;
}

/**
 * Marks the sends that go to a contact group another send reaches in the
 * same hour of the same day (pure). Each send gets clash: the names of the
 * groups it shares, empty for none.
 *
 * @param array[] $entries each at (unix time) and groups (id => name)
 * @return array[] the same entries, in the same order, with clash
 */
function pg_messages_calendar_clashes($entries)
{
    $seen = array();

    foreach ($entries as $index => $entry) {
        foreach (array_keys((array) ($entry['groups'] ?? array())) as $group_id) {
            $seen[date('Y-m-d H', (int) $entry['at']) . '|' . (int) $group_id][] = $index;
        }
    }

    foreach ($entries as $index => $entry) {
        $entries[$index]['clash'] = array();
        $hour = date('Y-m-d H', (int) $entry['at']);

        foreach ((array) ($entry['groups'] ?? array()) as $group_id => $name) {
            if (count($seen[$hour . '|' . (int) $group_id]) > 1) {
                $entries[$index]['clash'][] = (string) $name;
            }
        }
    }

    return $entries;
}

/**
 * The sends of each day by hour (pure): date => hour (00-23) => entries,
 * each list in time order, then by title. Days without a send are left out;
 * a send outside the days given is dropped.
 *
 * @param array[] $entries each at
 * @param array[] $days    pg_messages_calendar_days()
 * @return array
 */
function pg_messages_calendar_bucket($entries, $days)
{
    $known = array_flip(array_column($days, 'date'));

    usort($entries, function ($a, $b) {
        return ((int) $a['at'] <=> (int) $b['at']) ?: strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    $out = array();

    foreach ($entries as $entry) {
        $date = date('Y-m-d', (int) $entry['at']);

        if (isset($known[$date])) {
            $out[$date][date('H', (int) $entry['at'])][] = $entry;
        }
    }

    return $out;
}

/**
 * One send as a chip of the calendar: its time and title, a link to its own
 * screen, the clash mark when it has one.
 *
 * @param array $entry
 * @param bool  $time  whether the clock is written before the title
 * @return string
 */
function pg_messages_calendar_chip_html($entry, $time)
{
    $kinds = pg_messages_calendar_kinds();
    $kind = $kinds[$entry['kind']] ?? $kinds['campaign'];
    $clock = date('H:i', (int) $entry['at']);
    $title = lang($kind['label']) . ' · ' . $clock . ' · ' . $entry['title'];
    $mark = '';

    if (!empty($entry['clash'])) {
        $shared = lang(array('string' => 'Another send reaches {var:1} in the same hour.', 'vars' => array(implode(', ', $entry['clash']))));
        $title .= ' · ' . $shared;
        $mark = '<span class="bi bi-exclamation-triangle-fill pg-sendcal-clash-mark" aria-hidden="true"></span><span class="visually-hidden">' . h($shared) . '</span>';
    }

    return '<a class="ws-cal-event pg-sendcal-item pg-tone-' . h($kind['tone']) . (empty($entry['clash']) ? '' : ' is-clash') . '" href="' . h($entry['url']) . '" title="' . h($title) . '">'
        . '<span class="bi ' . h($kind['icon']) . ' pg-sendcal-icon" aria-hidden="true"></span>'
        . '<span class="ws-cal-task-title">' . ($time ? '<span class="pg-sendcal-time">' . h($clock) . '</span> ' : '') . h($entry['title']) . '</span>'
        . $mark . '</a>';
}

/**
 * The calendar grid: the weekday heads and a cell a day. A week lists each
 * day's sends under their hour; a month lists the first few with their
 * clock and "N more", which opens that day's week. A day's number opens its
 * week too.
 *
 * @param string  $view     week or month
 * @param array[] $days     pg_messages_calendar_days()
 * @param array   $buckets  pg_messages_calendar_bucket()
 * @param string  $today    Y-m-d
 * @param array   $weekdays 1..7 => short name
 * @param string  $self     the screen's address with the view kept, without date
 * @return string
 */
function pg_messages_calendar_grid_html($view, $days, $buckets, $today, $weekdays, $self)
{
    $head = '';

    foreach (array_values($weekdays) as $index => $name) {
        $head .= '<div' . (($index >= 5) ? ' class="ws-cal-weekend"' : '') . '>' . h($name) . '</div>';
    }

    $cells = '';
    $week_url = function ($date) use ($self) {
        return $self . ((strpos($self, '?') === false) ? '?' : '&') . 'date=' . $date;
    };

    foreach ($days as $day) {
        $hours = $buckets[$day['date']] ?? array();
        $label = ($weekdays[(int) date('N', (int) strtotime($day['date'] . ' 12:00:00'))] ?? '') . ' ' . prepare_form_data_for_output($day['date'], 'date');
        $body = '';

        if ($view === 'week') {
            foreach ($hours as $hour => $entries) {
                $chips = '';

                foreach ($entries as $entry) {
                    $chips .= pg_messages_calendar_chip_html($entry, true);
                }

                $body .= '<div class="pg-sendcal-hour"><div class="pg-sendcal-hour-label pg-text-meta">' . h($hour . ':00') . '</div>' . $chips . '</div>';
            }
        } else {
            $all = $hours ? array_merge(...array_values($hours)) : array();

            foreach (array_slice($all, 0, PG_MESSAGES_CALENDAR_MONTH_SHOWN) as $entry) {
                $body .= pg_messages_calendar_chip_html($entry, true);
            }

            if (count($all) > PG_MESSAGES_CALENDAR_MONTH_SHOWN) {
                $more = count($all) - PG_MESSAGES_CALENDAR_MONTH_SHOWN;
                $body .= '<a class="ws-cal-more" href="' . h(str_replace('cal=month', 'cal=week', $week_url($day['date']))) . '">'
                    . h(lang(array('string' => '{var:1} more', 'vars' => array(pg_format_number($more, 0))))) . '</a>';
            }
        }

        $classes = 'ws-cal-day pg-sendcal-day' . ($day['in_range'] ? '' : ' ws-cal-out') . (($day['date'] === $today) ? ' ws-cal-today' : '')
            . ($day['weekend'] ? ' ws-cal-weekend' : '') . (($hours || ($day['date'] === $today)) ? '' : ' is-empty');

        $cells .= '<div class="' . $classes . '" role="listitem" aria-label="' . h($label) . '">'
            . '<div class="ws-cal-day-top"><a class="ws-cal-num" href="' . h(str_replace('cal=month', 'cal=week', $week_url($day['date']))) . '" title="' . h($label) . '">' . (int) $day['day'] . '</a>'
            . '<span class="pg-sendcal-day-name pg-text-meta">' . h($label) . '</span></div>'
            . $body . '</div>';
    }

    return '<div id="ws-sendcal" class="ws-cal pg-sendcal pg-sendcal-' . h($view) . '">'
        . '<div class="ws-cal-head" aria-hidden="true">' . $head . '</div>'
        . '<div class="ws-cal-grid" role="list">' . $cells . '</div></div>';
}

/**
 * The kinds' key under the calendar.
 *
 * @return string
 */
function pg_messages_calendar_legend_html()
{
    $items = '';

    foreach (pg_messages_calendar_kinds() as $kind) {
        $items .= '<span class="pg-sendcal-legend-item pg-tone-' . h($kind['tone']) . '"><span class="bi ' . h($kind['icon']) . ' pg-sendcal-icon" aria-hidden="true"></span>' . h(lang($kind['label'])) . '</span>';
    }

    return '<div class="pg-sendcal-legend pg-text-meta">' . $items
        . '<span class="pg-sendcal-legend-item"><span class="bi bi-exclamation-triangle-fill pg-sendcal-clash-mark" aria-hidden="true"></span>' . h(lang('Same group in the same hour')) . '</span></div>';
}

/**
 * The included contact groups of the given campaigns, one read.
 *
 * @param string $xref   contact_groups_email_campaigns_xref or contact_groups_sms_campaigns_xref
 * @param string $column email_campaign_id or sms_campaign_id
 * @param int[]  $ids
 * @return array campaign id => group id => name
 */
function pg_messages_calendar_campaign_groups($xref, $column, $ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));
    $out = array();

    if (empty($ids)) {
        return $out;
    }

    // Both names come from the callers in this file, never from a request.
    foreach ((array) db_items("SELECT x." . $column . " AS campaign_id, contact_groups.id, contact_groups.name
        FROM " . $xref . " AS x
        INNER JOIN contact_groups ON contact_groups.id = x.contact_group_id
        WHERE x." . $column . " IN (" . implode(',', $ids) . ") AND x.type = 'included'") as $row) {
        $out[(int) $row['campaign_id']][(int) $row['id']] = (string) $row['name'];
    }

    return $out;
}

/**
 * The manual e-mail campaigns ready to send inside the period; a role 3
 * user's own only.
 *
 * @param array $user
 * @param int   $from
 * @param int   $to
 * @return array[] entries
 */
function pg_messages_calendar_email_campaigns($user, $from, $to)
{
    $own = ((int) $user['role'] < 3) ? '' : " AND created_user_id = '" . (int) $user['id'] . "'";
    $rows = (array) db_items("SELECT id, subject, start_time FROM email_campaigns
        WHERE type = 'manual' AND status = 'ready'
            AND start_time BETWEEN '" . e(date('Y-m-d H:i:s', $from)) . "' AND '" . e(date('Y-m-d H:i:s', $to)) . "'" . $own);
    $groups = pg_messages_calendar_campaign_groups('contact_groups_email_campaigns_xref', 'email_campaign_id', array_column($rows, 'id'));
    $out = array();

    foreach ($rows as $row) {
        $at = pg_messages_campaign_start_time($row['start_time']);

        if ($at > 0) {
            $out[] = array(
                'kind' => 'campaign',
                'at' => $at,
                'title' => ((string) $row['subject'] !== '') ? (string) $row['subject'] : '[' . lang('No name') . ']',
                'url' => 'edit_email_campaign.php?id=' . (int) $row['id'],
                'groups' => $groups[(int) $row['id']] ?? array(),
            );
        }
    }

    return $out;
}

/**
 * The SMS campaigns ready to send inside the period; a role 3 user's own
 * only.
 *
 * @param array $user
 * @param int   $from
 * @param int   $to
 * @return array[] entries
 */
function pg_messages_calendar_sms_campaigns($user, $from, $to)
{
    if (!function_exists('pg_sms_campaigns_ready') || !pg_sms_campaigns_ready()) {
        return array();
    }

    $own = ((int) $user['role'] < 3) ? '' : " AND created_by = '" . (int) $user['id'] . "'";
    $rows = (array) db_items("SELECT id, name, start_at FROM sms_campaigns
        WHERE status = 'ready' AND start_at BETWEEN '" . (int) $from . "' AND '" . (int) $to . "'" . $own);
    $groups = pg_messages_calendar_campaign_groups('contact_groups_sms_campaigns_xref', 'sms_campaign_id', array_column($rows, 'id'));
    $out = array();

    foreach ($rows as $row) {
        $out[] = array(
            'kind' => 'sms',
            'at' => (int) $row['start_at'],
            'title' => ((string) $row['name'] !== '') ? (string) $row['name'] : '[' . lang('No name') . ']',
            'url' => 'edit_sms_campaign.php?id=' . (int) $row['id'],
            'groups' => $groups[(int) $row['id']] ?? array(),
        );
    }

    return $out;
}

/**
 * The runs inside the period of the live flows the person reaches that
 * start at a time and send a message.
 *
 * @param array $user
 * @param int   $from
 * @param int   $to
 * @return array[] entries
 */
function pg_messages_calendar_flows($user, $from, $to)
{
    if (!function_exists('pg_flows_user_may') || !pg_flows_user_may($user)) {
        return array();
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_automatic.php');

    if (!function_exists('ws_scheduled_anchor_ready') || !ws_scheduled_anchor_ready()) {
        return array();
    }

    $viewer = ws_viewer($user);
    $flows = ws_flows_with_converted($viewer, ws_flows_list($viewer));
    $live = array();

    foreach (pg_messages_automatic_rows(array_filter((array) $flows, function ($flow) {
        return (string) ($flow['status'] ?? '') === 'live';
    }), ws_flows_start_graphs(array_filter((array) $flows, function ($flow) {
        return (string) ($flow['status'] ?? '') === 'live';
    }))) as $row) {
        $live[(int) $row['flow']['id']] = $row['flow'];
    }

    $ids = empty($live) ? array() : ws_flows_next_run_ids($live, ws_flows_starts($viewer, array_values($live)));

    if (empty($ids)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT flow_id, rules, next_run_at FROM ws_scheduled_actions
        WHERE flow_id IN (" . implode(',', array_map('intval', $ids)) . ") AND anchor IN ('hidden', 'converted') AND status = 'active' AND next_run_at > 0
        ORDER BY id") as $anchor) {
        $flow_id = (int) $anchor['flow_id'];
        $rules = json_decode((string) $anchor['rules'], true);
        $rule = (is_array($rules) && isset($rules['type'])) ? $rules : (is_array($rules) ? reset($rules) : null);

        if (!isset($live[$flow_id]) || !is_array($rule) || ((string) ($rule['type'] ?? '') !== 'at')) {
            continue;
        }

        $times = pg_messages_calendar_flow_times((int) $anchor['next_run_at'], $from, $to, PG_MESSAGES_CALENDAR_FLOW_CAP, function ($after) use ($rule) {
            return ws_scheduled_next_run($rule, $after);
        });
        $name = ((string) $live[$flow_id]['name'] !== '') ? (string) $live[$flow_id]['name'] : '[' . lang('No name') . ']';

        foreach ($times as $at) {
            $out[] = array('kind' => 'flow', 'at' => $at, 'title' => $name, 'url' => 'workspace_flows.php?flow=' . $flow_id, 'groups' => array());
        }

        unset($live[$flow_id]);
    }

    return $out;
}

/**
 * The e-mails waiting under Send later inside the period, in the
 * conversations of the channels the person reads.
 *
 * @param array $user
 * @param int   $from
 * @param int   $to
 * @return array[] entries
 */
function pg_messages_calendar_later_mails($user, $from, $to)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if (!function_exists('ws_mail_later_ready') || !ws_mail_later_ready()) {
        return array();
    }

    $viewer = ws_viewer($user);

    if (empty($viewer['member'])) {
        return array();
    }

    $channels = ws_mail_list_channels($viewer);

    if (empty($channels)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT ws_mail_items.subject, ws_mail_items.send_at, ws_mail_items.conversation_id
        FROM ws_mail_items
        INNER JOIN ws_mail_conversations ON ws_mail_conversations.id = ws_mail_items.conversation_id
        WHERE ws_mail_items.send_state = '" . e(WS_MAIL_LATER_STATE) . "'
            AND ws_mail_items.send_at BETWEEN '" . (int) $from . "' AND '" . (int) $to . "'
            AND ws_mail_conversations.channel_id IN (" . implode(',', array_map('intval', $channels)) . ")") as $row) {
        $out[] = array(
            'kind' => 'mail',
            'at' => (int) $row['send_at'],
            'title' => ((string) $row['subject'] !== '') ? (string) $row['subject'] : lang('(no subject)'),
            'url' => 'messages_mail.php?conversation=' . (int) $row['conversation_id'],
            'groups' => array(),
        );
    }

    return $out;
}

/**
 * Everything waiting to be sent inside the period that the person may see,
 * the clashes marked.
 *
 * @param array $user validate_user()
 * @param int   $from
 * @param int   $to
 * @return array[] entries: kind, at, title, url, groups, clash
 */
function pg_messages_calendar_entries($user, $from, $to)
{
    return pg_messages_calendar_clashes(array_merge(
        pg_messages_calendar_email_campaigns($user, $from, $to),
        pg_messages_calendar_sms_campaigns($user, $from, $to),
        pg_messages_calendar_flows($user, $from, $to),
        pg_messages_calendar_later_mails($user, $from, $to)
    ));
}

/**
 * The weekdays' short names, Monday first.
 *
 * @return array 1..7 => name
 */
function pg_messages_calendar_weekdays()
{
    return array(1 => lang('Mon'), 2 => lang('Tue'), 3 => lang('Wed'), 4 => lang('Thu'), 5 => lang('Fri'), 6 => lang('Sat'), 7 => lang('Sun'));
}

/**
 * A month's heading: its name and the year ("November 2026").
 *
 * @param string $date Y-m-d, any day of the month
 * @return string
 */
function pg_messages_calendar_month_title($date)
{
    $names = array(lang('January'), lang('February'), lang('March'), lang('April'), lang('May'), lang('June'),
        lang('July'), lang('August'), lang('September'), lang('October'), lang('November'), lang('December'));

    return $names[(int) substr($date, 5, 2) - 1] . ' ' . substr($date, 0, 4);
}

/**
 * The Sends tab's view switch: the list or the calendar.
 *
 * @param bool $calendar whether the calendar is open
 * @return string
 */
function pg_messages_calendar_switch_html($calendar)
{
    return pg_messages_seg_filter('view', array('' => lang('List'), 'calendar' => lang('Calendar')), $calendar ? 'calendar' : '', '', 'messages_sends.php', array(), lang('View'));
}

/**
 * The calendar view of the Sends tab: its toolbar (the switch, week or
 * month, previous, today, next and the days shown), the clash notice, the
 * grid and the key.
 *
 * @param array $user  validate_user()
 * @param array $get   the address's values
 * @param int   $now
 * @return string
 */
function pg_messages_calendar_body($user, $get, $now)
{
    $today = date('Y-m-d', $now);
    $filters = pg_messages_calendar_filters($get, $today);
    $days = pg_messages_calendar_days($filters['view'], $filters['date']);
    list($from, $to) = pg_messages_calendar_bounds($days);
    $entries = pg_messages_calendar_entries($user, max($from, $now - 3600), $to);
    $buckets = pg_messages_calendar_bucket($entries, $days);
    $file = 'messages_sends.php';
    $base = array('view' => 'calendar', 'cal' => ($filters['view'] === 'month') ? 'month' : '');
    $url = function ($date) use ($file, $base) {
        return $file . '?' . http_build_query(array_filter($base + array('date' => $date), 'strlen'));
    };

    $in_range = array_values(array_filter($days, function ($day) {
        return $day['in_range'];
    }));
    $range = ($filters['view'] === 'month') ? pg_messages_calendar_month_title($filters['date'])
        : prepare_form_data_for_output($in_range[0]['date'], 'date') . ' – ' . prepare_form_data_for_output($in_range[count($in_range) - 1]['date'], 'date');

    $nav = '<div class="pg-sendcal-nav" role="group" aria-label="' . h(lang('Days shown')) . '">'
        . '<a class="pg-button pg-button-quiet pg-button-sm pg-button-icon" href="' . h($url(pg_messages_calendar_step($filters['view'], $filters['date'], -1))) . '" title="' . h(lang('Previous')) . '"><span class="bi bi-chevron-left" aria-hidden="true"></span><span class="visually-hidden">' . h(lang('Previous')) . '</span></a>'
        . '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url('')) . '">' . h(lang('Today')) . '</a>'
        . '<a class="pg-button pg-button-quiet pg-button-sm pg-button-icon" href="' . h($url(pg_messages_calendar_step($filters['view'], $filters['date'], 1))) . '" title="' . h(lang('Next')) . '"><span class="bi bi-chevron-right" aria-hidden="true"></span><span class="visually-hidden">' . h(lang('Next')) . '</span></a>'
        . '<span class="pg-sendcal-range pg-text-2">' . h($range) . '</span></div>';

    $keep = array('view' => 'calendar', 'date' => ($filters['date'] === $today) ? '' : $filters['date']);
    $tools = '<div class="pg-messages-filters">'
        . pg_messages_calendar_switch_html(true)
        . pg_messages_seg_filter('cal', array('' => lang('Week'), 'month' => lang('Month')), ($filters['view'] === 'month') ? 'month' : '', '', $file, $keep, lang('Calendar'))
        . $nav . '</div>';

    $clashes = count(array_filter($entries, function ($entry) {
        return !empty($entry['clash']);
    }));
    $notes = $clashes ? pg_messages_note(lang(array('string' => '{var:1} send(s) reach a contact group that another send reaches in the same hour; they are marked.', 'vars' => array(pg_format_number($clashes, 0)))), '', '', 'amber') : '';

    if (empty($buckets)) {
        $notes .= '<p class="pg-text-meta pg-sendcal-empty">' . h(lang('Nothing is waiting to be sent in these days.')) . '</p>';
    }

    $self = $file . '?' . http_build_query(array_filter($base, 'strlen'));

    return pg_screen_toolbar(array('grow' => $tools), array('class' => 'pg-messages-toolbar'))
        . $notes
        . pg_messages_calendar_grid_html($filters['view'], $days, $buckets, $today, pg_messages_calendar_weekdays(), $self)
        . pg_messages_calendar_legend_html();
}
