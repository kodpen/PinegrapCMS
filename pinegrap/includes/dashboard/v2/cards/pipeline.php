<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pipeline card: four step boxes from the first contact to the result,
 * named after the person's preset (Request line, Reader line, Sales line,
 * Project line). Each box has a label, the period's figure, a note, an
 * eight-point line of the period cut in eight equal spans, an optional flag
 * and a link opening its list on the counted period where the list can
 * take one.
 *
 * Every step is read behind its own gate; a step the person may not see is
 * left out, so the card may show fewer than four boxes. Visits come from
 * the hourly visitor rollup, never from raw visitor rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

/**
 * The points of a step's line.
 */
if (!defined('PG_DASHBOARD_V2_PIPELINE_POINTS')) {
    define('PG_DASHBOARD_V2_PIPELINE_POINTS', 8);
}

/**
 * Each preset's line: its title, the line under it and its four steps. The
 * Simple preset has no pipeline of its own; a pipeline added to it takes
 * the first default's.
 *
 * @return array preset => array(title, sub, steps)
 */
function pg_dashboard_v2_pipeline_presets()
{
    return array(
        'corporate' => array(
            'title' => lang('Request line'),
            'sub'   => lang('From a visit to a meeting; a box opens its list on the period'),
            'steps' => array('visits', 'forms', 'contacts', 'meetings'),
        ),
        'blog' => array(
            'title' => lang('Reader line'),
            'sub'   => lang('From a visit to a subscriber; a box opens its list on the period'),
            'steps' => array('visits', 'views', 'comments', 'subscribers'),
        ),
        'ecommerce' => array(
            'title' => lang('Sales line'),
            'sub'   => lang('From a visit to a delivery; a box opens its list on the period'),
            'steps' => array('visits', 'carts', 'orders', 'delivered'),
        ),
        'agency' => array(
            'title' => lang('Project line'),
            'sub'   => lang('From the work in hand to going live'),
            'steps' => array('edited', 'prototypes', 'approvals', 'going_live'),
        ),
    );
}

/**
 * The preset whose line the card shows: the stored preset, the first
 * default for Simple or an unknown one. Pure.
 *
 * @param string $preset
 * @param bool   $shop
 * @return string
 */
function pg_dashboard_v2_pipeline_preset($preset, $shop)
{
    $presets = pg_dashboard_v2_pipeline_presets();

    if (is_string($preset) && isset($presets[$preset])) {
        return $preset;
    }

    return $shop ? 'ecommerce' : 'corporate';
}

/**
 * The period counted and the one compared with it. The period runs from
 * the start of its first day to now (today, the last 7 or 30 days, days
 * stepped by calendar so a clock change does not shift them) and is cut in
 * eight equal spans for the line. The compared period is as long and ends
 * where the period starts (compare 0) or a year before it (compare 1);
 * compare 2 has none. Pure.
 *
 * @param int $per     0 today, 1 7 days, 2 30 days
 * @param int $compare 0 previous period, 1 last year, 2 off
 * @param int $now     unix time
 * @return array start, stop, edges (nine times), before (array(start, stop) or null)
 */
function pg_dashboard_v2_pipeline_window($per, $compare, $now)
{
    $now = (int) $now;
    $midnight = strtotime(date('Y-m-d', $now));
    $days = array(0 => 0, 1 => 6, 2 => 29);
    $start = pg_dashboard_days_back($midnight, $days[min(2, max(0, (int) $per))]);

    $edges = array();
    for ($i = 0; $i <= PG_DASHBOARD_V2_PIPELINE_POINTS; $i++) {
        $edges[] = $start + (int) round(($now - $start) * $i / PG_DASHBOARD_V2_PIPELINE_POINTS);
    }

    $before = null;
    if ((int) $compare === 0) {
        $before = array($start - ($now - $start), $start);
    } elseif ((int) $compare === 1) {
        $before = array(strtotime('-1 year', $start), strtotime('-1 year', $now));
    }

    return array('start' => $start, 'stop' => $now, 'edges' => $edges, 'before' => $before);
}

/**
 * A SQL list of one SUM per span of the edges for a unix time column: the
 * column is compared with constants, so its index applies. Pure.
 *
 * @param string $column a column name, already safe
 * @param int[]  $edges  nine times, oldest first
 * @return string
 */
function pg_dashboard_v2_pipeline_sums_sql($column, $edges)
{
    $sums = array();

    for ($i = 0; $i < count($edges) - 1; $i++) {
        // The last span has no upper edge: a row written while the card is
        // read still counts.
        $upper = ($i === count($edges) - 2) ? '' : ' AND ' . $column . ' < ' . (int) $edges[$i + 1];
        $sums[] = 'SUM(CASE WHEN ' . $column . ' >= ' . (int) $edges[$i] . $upper . ' THEN 1 ELSE 0 END) AS s' . $i;
    }

    return implode(', ', $sums);
}

/**
 * Rows per span of a table's unix time column.
 *
 * @param string $from   the FROM part, already safe
 * @param string $column a column name, already safe
 * @param string $where  more WHERE conditions starting with AND, already escaped
 * @param int[]  $edges
 * @return int[] one count per span
 */
function pg_dashboard_v2_pipeline_spans($from, $column, $where, $edges)
{
    $row = db_item(
        'SELECT ' . pg_dashboard_v2_pipeline_sums_sql($column, $edges) . '
         FROM ' . $from . '
         WHERE ' . $column . ' >= ' . (int) $edges[0] . ' ' . $where);

    $out = array();
    for ($i = 0; $i < count($edges) - 1; $i++) {
        $out[] = (int) ($row['s' . $i] ?? 0);
    }

    return $out;
}

/**
 * One rollup column summed per span of the edges, an hour counted in the
 * span its start falls in. Pure.
 *
 * @param array  $stats  pg_visitor_stats_range(): date => hour => columns
 * @param int[]  $edges
 * @param string $column
 * @return int[]
 */
function pg_dashboard_v2_pipeline_hours($stats, $edges, $column)
{
    $count = count($edges) - 1;
    $out = array_fill(0, max(0, $count), 0);

    foreach ($stats as $date => $hours) {
        foreach ($hours as $hour => $values) {
            $time = strtotime($date . ' ' . sprintf('%02d', (int) $hour) . ':00:00');

            if (($time === false) || ($time < $edges[0] - 3599) || ($time >= $edges[$count])) {
                continue;
            }

            // The hour the period starts in counts in the first span.
            $span = 0;
            while (($span < $count - 1) && ($time >= $edges[$span + 1])) {
                $span++;
            }

            $out[$span] += (int) ($values[$column] ?? 0);
        }
    }

    return $out;
}

/**
 * A rollup column's total over a period.
 *
 * @param array  $period array(start, stop)
 * @param string $column
 * @return int
 */
function pg_dashboard_v2_pipeline_rollup_total($period, $column)
{
    $edges = array((int) $period[0], (int) $period[1]);

    return array_sum(pg_dashboard_v2_pipeline_hours(
        pg_visitor_stats_range(date('Y-m-d', $edges[0]), date('Y-m-d', $edges[1])), $edges, $column));
}

/**
 * One step box. Pure.
 *
 * @param string     $key
 * @param string     $label
 * @param int[]      $points one count per span
 * @param string     $note
 * @param string     $url
 * @param array|null $flag  text, tone (warn or bad); null for none
 * @param int|null   $value the figure when it is not the sum of the points
 * @param string     $tone  the note's tone: good, bad or ''
 * @return array key, label, value, note, tone, points, flag, url
 */
function pg_dashboard_v2_pipeline_box($key, $label, $points, $note, $url, $flag = null, $value = null, $tone = '')
{
    $points = array_map('intval', array_values($points));

    return array(
        'key'    => $key,
        'label'  => $label,
        'value'  => pg_format_number(($value === null) ? array_sum($points) : (int) $value, 0),
        'note'   => $note,
        'tone'   => $tone,
        'points' => $points,
        'flag'   => (is_array($flag) && ($flag['text'] ?? '') !== '') ? array('text' => $flag['text'], 'tone' => $flag['tone'] ?? 'warn') : null,
        'url'    => $url,
    );
}

/**
 * A flag for a count, or none when the count is zero. Pure.
 *
 * @param int    $count
 * @param string $string an English lang() string with {var:1} and {suffix:1}
 * @param string $tone
 * @return array|null
 */
function pg_dashboard_v2_pipeline_flag($count, $string, $tone)
{
    $count = (int) $count;

    if ($count <= 0) {
        return null;
    }

    return array(
        'text' => lang(array('string' => $string, 'vars' => pg_format_number($count, 0), 'suffix' => array(($count === 1) ? '' : 's'))),
        'tone' => $tone,
    );
}

/**
 * The gate of each step, a key of pg_dashboard_v2_figure_gate().
 *
 * @return array step => gate
 */
function pg_dashboard_v2_pipeline_gates()
{
    return array(
        'visits'      => 'visitors',
        'views'       => 'visitors',
        'forms'       => 'forms',
        'contacts'    => 'staff',
        'meetings'    => 'workspace',
        'comments'    => 'staff',
        'subscribers' => 'staff',
        'carts'       => 'shop',
        'orders'      => 'shop',
        'delivered'   => 'shop',
        'edited'      => 'staff',
        'prototypes'  => 'manager_area',
        'approvals'   => 'workspace',
        'going_live'  => 'staff',
    );
}

/**
 * One step's box, or null when the person may not see it or its data is
 * missing on this site.
 *
 * @param string $key
 * @param array  $user
 * @param array  $win pg_dashboard_v2_pipeline_window()
 * @return array|null
 */
function pg_dashboard_v2_pipeline_step($key, $user, $win)
{
    $gates = pg_dashboard_v2_pipeline_gates();

    if (!isset($gates[$key]) || !pg_dashboard_v2_figure_gate($gates[$key], $user)) {
        return null;
    }

    $edges = $win['edges'];
    $start = $win['start'];
    $stop = $win['stop'];
    $user_id = (int) $user['id'];
    $count = function ($string, $n) {
        return lang(array('string' => $string, 'vars' => pg_format_number((int) $n, 0), 'suffix' => array(((int) $n === 1) ? '' : 's')));
    };

    switch ($key) {
        case 'visits':
        case 'views':
            $stats = pg_visitor_stats_range(date('Y-m-d', $start), date('Y-m-d', $stop));
            $points = pg_dashboard_v2_pipeline_hours($stats, $edges, 'visitors');
            $total = array_sum($points);

            if ($key === 'views') {
                $views = pg_dashboard_v2_pipeline_hours($stats, $edges, 'page_views');
                $note = ($total > 0)
                    ? lang(array('string' => '{var:1} pages per visit', 'vars' => pg_format_number(array_sum($views) / $total, 1)))
                    : lang('Pages read in the period');
                return pg_dashboard_v2_pipeline_box($key, lang('Page views'), $views, $note, 'view_visitor_reports.php');
            }

            $change = ($win['before'] !== null) ? pg_dashboard_v2_change($total, pg_dashboard_v2_pipeline_rollup_total($win['before'], 'visitors')) : null;
            return pg_dashboard_v2_pipeline_box($key, lang('Visits'), $points,
                ($change !== null) ? $change['text'] : lang('Visitors in the period'),
                'view_visitor_reports.php', null, null, ($change !== null) ? $change['tone'] : '');

        case 'forms':
            return pg_dashboard_v2_pipeline_box($key, lang('Forms'),
                pg_dashboard_v2_pipeline_spans('forms', 'submitted_timestamp', "AND complete = '1'", $edges),
                lang('Contact, quote and application forms'),
                pg_dashboard_list_url('view_submitted_forms.php', array('advanced_filters' => 'false', 'custom_form' => '[All]', 'query' => ''), $start, $stop));

        case 'contacts':
            return pg_dashboard_v2_pipeline_box($key, lang('New contacts'),
                pg_dashboard_v2_pipeline_spans('contacts', 'timestamp', '', $edges),
                lang('Added to contacts'),
                pg_dashboard_list_url('view_contacts.php', array('advanced_filters' => 'false', 'contact_group' => '[' . lang('All') . ']'), $start, $stop));

        case 'meetings':
            // Tasks the person opened or was given: a task in a channel they
            // are not in is never counted.
            $mine = "AND (ws_tasks.creator_id = '" . $user_id . "' OR EXISTS (SELECT 1 FROM ws_task_assignees a WHERE a.task_id = ws_tasks.id AND a.user_id = '" . $user_id . "'))";
            $signals = pg_dashboard_v2_ws_signals($user);
            return pg_dashboard_v2_pipeline_box($key, lang('Meetings'),
                pg_dashboard_v2_pipeline_spans('ws_tasks', 'ws_tasks.created_at', "AND ws_tasks.status != 'cancelled' " . $mine, $edges),
                lang('Opened as tasks'), 'workspace_tasks.php',
                pg_dashboard_v2_pipeline_flag($signals['overdue'] ?? 0, '{var:1} overdue', 'bad'));

        case 'comments':
            $live = pg_comment_live_page_sql(pg_recycle_bin_folder_ids());
            $waiting = (int) db_value("SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id WHERE comments.published = '0'" . $live);
            return pg_dashboard_v2_pipeline_box($key, lang('Engagement'),
                pg_dashboard_v2_pipeline_spans('comments LEFT JOIN page ON comments.page_id = page.page_id', 'comments.created_timestamp', $live, $edges),
                lang('Comments'), 'view_comments.php',
                pg_dashboard_v2_pipeline_flag($waiting, '{var:1} waiting for approval', 'warn'));

        case 'subscribers':
            return pg_dashboard_v2_pipeline_box($key, lang('Subscribers'),
                pg_dashboard_v2_pipeline_spans('contacts', 'timestamp', "AND opt_in = '1' AND email_address != ''", $edges),
                lang('Joined the newsletter'),
                pg_dashboard_list_url('view_contacts.php', array('filter' => 'my_subscribers', 'advanced_filters' => 'false', 'contact_group' => '[' . lang('All') . ']'), $start, $stop));

        case 'carts':
            // Every order started in the period; the ones still incomplete
            // are baskets left without ordering.
            $points = pg_dashboard_v2_pipeline_spans('orders', 'order_date', '', $edges);
            $left = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'incomplete' AND order_date >= " . (int) $start);
            return pg_dashboard_v2_pipeline_box($key, lang('Carts'), $points,
                $count('{var:1} left without ordering', $left),
                'view_orders.php?reset=true&status=incomplete');

        case 'orders':
            $points = pg_dashboard_v2_pipeline_spans('orders', 'order_date', "AND status != 'incomplete'", $edges);
            $average = (int) round((float) db_value("SELECT AVG(total) FROM orders WHERE status != 'incomplete' AND order_date >= " . (int) $start));
            return pg_dashboard_v2_pipeline_box($key, lang('Orders'), $points,
                (array_sum($points) > 0) ? lang(array('string' => 'Average {var:1}', 'vars' => prepare_amount($average / 100))) : lang('Placed in the period'),
                pg_dashboard_list_url('view_orders.php', array('reset' => 'true'), $start, $stop),
                pg_dashboard_v2_pipeline_flag(pg_dashboard_v2_pipeline_unshipped($start), '{var:1} not shipped', 'warn'));

        case 'delivered':
            if (!pg_schema_has('shipping_tracking_numbers', 'delivered_at')) {
                return null;
            }
            $refunds = pg_schema_has('orders', 'refund_status')
                ? (int) db_value("SELECT COUNT(*) FROM orders WHERE refund_status IN ('manual_required', 'failed', 'pending')")
                : 0;
            return pg_dashboard_v2_pipeline_box($key, lang('Delivered'),
                pg_dashboard_v2_pipeline_spans('shipping_tracking_numbers', 'delivered_at', '', $edges),
                lang('From shipment tracking'), 'view_orders.php?reset=true',
                pg_dashboard_v2_pipeline_flag($refunds, '{var:1} refund request{suffix:1}', 'bad'));

        case 'edited':
            return pg_dashboard_v2_pipeline_box($key, lang('In work'),
                pg_dashboard_v2_pipeline_spans('page', 'page_timestamp', '', $edges),
                lang('Pages edited'), 'view_pages.php');

        case 'prototypes':
            if (!pg_schema_has('prototype_links', 'created_at')) {
                return null;
            }
            $opened = (int) db_value("SELECT COUNT(*) FROM prototype_links WHERE revoked_at = 0 AND last_used_at >= " . (int) $start);
            // The viewer needs a design: the one shared last.
            $style = (int) db_value("SELECT style_id FROM prototype_links WHERE revoked_at = 0 ORDER BY created_at DESC LIMIT 1");
            return pg_dashboard_v2_pipeline_box($key, lang('Prototypes'),
                pg_dashboard_v2_pipeline_spans('prototype_links', 'created_at', '', $edges),
                lang('Shared by a link'), ($style > 0) ? 'prototype.php?style=' . $style : '',
                pg_dashboard_v2_pipeline_flag($opened, '{var:1} opened', 'warn'));

        case 'approvals':
            // Approvals asked of the person in the period; the flag is how
            // many of all of theirs still wait.
            $from = "ws_approvals INNER JOIN ws_approval_people p ON p.approval_id = ws_approvals.id AND p.user_id = '" . $user_id . "'";
            $signals = pg_dashboard_v2_ws_signals($user);
            $url = (string) ($signals['approval_url'] ?? '');
            return pg_dashboard_v2_pipeline_box($key, lang('Approval'),
                pg_dashboard_v2_pipeline_spans($from, 'ws_approvals.created_at', '', $edges),
                lang('Asked for your approval'), ($url !== '') ? $url : 'workspace.php',
                pg_dashboard_v2_pipeline_flag($signals['approvals'] ?? 0, '{var:1} waiting on you', 'bad'));

        case 'going_live':
            if (!pg_schema_has('page', 'publish_at')) {
                return null;
            }
            // The coming seven days, cut in eight spans like the period.
            $week = array();
            for ($i = 0; $i <= PG_DASHBOARD_V2_PIPELINE_POINTS; $i++) {
                $week[] = $stop + (int) round(7 * 86400 * $i / PG_DASHBOARD_V2_PIPELINE_POINTS);
            }
            return pg_dashboard_v2_pipeline_box($key, lang('Going live'),
                pg_dashboard_v2_pipeline_spans('page', 'publish_at', 'AND publish_at < ' . (int) $week[PG_DASHBOARD_V2_PIPELINE_POINTS], $week),
                lang('Pages set to publish this week'), 'view_pages.php');
    }

    return null;
}

/**
 * Online orders placed since a time that still have items to ship, read
 * as the greeting counts pending shipments.
 *
 * @param int $since
 * @return int
 */
function pg_dashboard_v2_pipeline_unshipped($since)
{
    $saved = pg_schema_has('order_items', 'saved_for_later') ? ' AND (order_items.saved_for_later = 0)' : '';

    return (int) db_value(
        "SELECT COUNT(*) FROM (
            SELECT orders.id
            FROM orders
            INNER JOIN ship_tos ON (ship_tos.order_id = orders.id) AND (ship_tos.complete = '1')
            INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
            WHERE
                (orders.status IN ('complete', 'exported'))
                AND (orders.type = 'online')
                AND (orders.order_date >= " . (int) $since . ")
                $saved
            GROUP BY orders.id
            HAVING SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) > 0
        ) AS pending");
}

/**
 * The preset stored in the person's layout.
 *
 * @param array $user
 * @param bool  $shop
 * @return string
 */
function pg_dashboard_v2_pipeline_stored_preset($user, $shop)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');

    $row = pg_dashboard_v2_row($user['id']);
    $layout = (is_array($row) && isset($row['layout'])) ? json_decode((string) $row['layout'], true) : null;

    return pg_dashboard_v2_pipeline_preset(is_array($layout) ? ($layout['preset'] ?? '') : '', $shop);
}

function pg_dashboard_v2_card_pipeline($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('all', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $shop = defined('ECOMMERCE') && (ECOMMERCE === true);
    $line = pg_dashboard_v2_pipeline_presets()[pg_dashboard_v2_pipeline_stored_preset($user, $shop)];
    $win = pg_dashboard_v2_pipeline_window($period, $compare, time());

    $steps = array();
    foreach ($line['steps'] as $key) {
        $step = pg_dashboard_v2_pipeline_step($key, $user, $win);
        if ($step !== null) {
            $steps[] = $step;
        }
    }

    return array('status' => 'success', 'data' => array(
        'title' => $line['title'],
        'sub'   => $line['sub'],
        'steps' => $steps,
        'empty' => empty($steps) ? lang('Nothing on this line is open to you.') : '',
    ));
}
