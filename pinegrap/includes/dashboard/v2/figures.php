<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's figures: the eleven a greeting tile may show, the
 * counts the "since your last sign-in" chips show, and the shortcut pool.
 * Each figure is one function behind its gate, shared by tiles, goals and
 * chips; the gates are the old dashboard's rules for the widget that shows
 * the same rows, so the two dashboards never disagree on who sees what.
 *
 * pg_dashboard_v2_figure_shape() and the other pure helpers carry no
 * database call; the readers (pg_dashboard_v2_figure_raw(),
 * pg_dashboard_v2_chip_count()) do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The gates a figure, chip or shortcut may name: every card gate and four of
 * the figures' own.
 *
 * @return string[]
 */
function pg_dashboard_v2_figure_gates()
{
    return array_merge(pg_dashboard_v2_gates(), array('forms', 'waf', 'flows', 'manager_area'));
}

/**
 * May the person see what is behind this gate?
 *
 * forms:        Forms switched on and a staff role, as the brief counts them
 *               (a User sees only their own folders' forms on the list).
 * waf:          a staff role and the firewall not off.
 * flows:        a workspace member with a staff role.
 * manager_area: the screens validate_area_access($user, 'manager') lets in.
 *
 * @param string $gate
 * @param array  $user
 * @return bool
 */
function pg_dashboard_v2_figure_gate($gate, $user)
{
    $role = (int) $user['role'];

    switch ((string) $gate) {
        case 'forms':
            return defined('FORMS') && (FORMS === true) && ($role < 3);
        case 'waf':
            return ($role < 3) && function_exists('waf_mode') && (waf_mode() !== 'off');
        case 'flows':
            return ($role < 3) && pg_dashboard_v2_gate('workspace', $user);
        case 'manager_area':
            return $role < 3;
    }

    return pg_dashboard_v2_gate($gate, $user);
}

/**
 * The eleven figures a tile may show, in the tile select's order.
 *
 * @return array key => array(label, gate, url)
 */
function pg_dashboard_v2_figures()
{
    return array(
        'visits'      => array('label' => lang('Visits today'), 'gate' => 'visitors', 'url' => 'view_visitor_reports.php'),
        'online'      => array('label' => lang('Online now'), 'gate' => 'visitors', 'url' => 'view_visitor_reports.php'),
        'forms'       => array('label' => lang('Pending forms'), 'gate' => 'forms', 'url' => 'view_submitted_forms.php'),
        'orders'      => array('label' => lang('Orders today'), 'gate' => 'shop', 'url' => 'view_orders.php'),
        'seo'         => array('label' => lang('Average SEO score'), 'gate' => 'staff', 'url' => 'view_pages.php'),
        'a11y'        => array('label' => lang('Accessibility issues'), 'gate' => 'staff', 'url' => 'view_accessibility.php'),
        'tasks'       => array('label' => lang('Open tasks'), 'gate' => 'workspace', 'url' => 'workspace_tasks.php'),
        'inbox'       => array('label' => lang('Inbox'), 'gate' => 'workspace', 'url' => 'workspace.php'),
        'blocks'      => array('label' => lang('Blocked requests (24 h)'), 'gate' => 'waf', 'url' => 'view_waf_log.php'),
        'subscribers' => array('label' => lang('New subscribers (week)'), 'gate' => 'staff', 'url' => 'view_contacts.php?filter=my_subscribers'),
        'flows'       => array('label' => lang('Flow runs (today)'), 'gate' => 'flows', 'url' => 'workspace_flows.php'),
    );
}

/**
 * @param string $key
 * @param array  $user
 * @return bool
 */
function pg_dashboard_v2_figure_allowed($key, $user)
{
    $figures = pg_dashboard_v2_figures();

    return isset($figures[$key]) && pg_dashboard_v2_figure_gate($figures[$key]['gate'], $user);
}

/**
 * The figure keys the person may see, in select order.
 *
 * @param array $user
 * @return string[]
 */
function pg_dashboard_v2_figures_allowed($user)
{
    $keys = array();

    foreach (array_keys(pg_dashboard_v2_figures()) as $key) {
        if (pg_dashboard_v2_figure_allowed($key, $user)) {
            $keys[] = $key;
        }
    }

    return $keys;
}

/**
 * A figure for a tile, a goal or a chip: null when the person may not see
 * it, else its label, number, note, tone and list address.
 *
 * @param string $key
 * @param array  $user
 * @return array|null label, value, note, tone, url
 */
function pg_dashboard_v2_figure($key, $user)
{
    if (!pg_dashboard_v2_figure_allowed($key, $user)) {
        return null;
    }

    $shape = pg_dashboard_v2_figure_shape($key, pg_dashboard_v2_figure_raw($key, $user));
    $figures = pg_dashboard_v2_figures();

    return array(
        'key'   => $key,
        'label' => $figures[$key]['label'],
        'value' => $shape['value'],
        'note'  => $shape['note'],
        'tone'  => $shape['tone'],
        'url'   => $figures[$key]['url'],
    );
}

/**
 * A day against the day before: the note "▲ yesterday 2" in a tone.
 *
 * @param int $today
 * @param int $yesterday
 * @return array note, tone
 */
function pg_dashboard_v2_figure_compare($today, $yesterday)
{
    if (($today === 0) && ($yesterday === 0)) {
        return array('note' => '', 'tone' => 'plain');
    }

    $text = lang(array('string' => 'yesterday {var:1}', 'vars' => pg_format_number($yesterday, 0)));

    if ($today > $yesterday) {
        return array('note' => '▲ ' . $text, 'tone' => 'good');
    }

    if ($today < $yesterday) {
        return array('note' => '▼ ' . $text, 'tone' => 'warn');
    }

    return array('note' => $text, 'tone' => 'plain');
}

/**
 * Raw counts to number, note and tone. Pure: the counts come from
 * pg_dashboard_v2_figure_raw().
 *
 * @param string $key
 * @param array  $raw
 * @return array|null value, note, tone; null for an unknown key
 */
function pg_dashboard_v2_figure_shape($key, $raw)
{
    $n = function ($name) use ($raw) {
        return (int) ($raw[$name] ?? 0);
    };
    $out = function ($value, $note, $tone) {
        return array('value' => (int) $value, 'note' => (string) $note, 'tone' => $tone);
    };

    switch ((string) $key) {
        case 'visits':
        case 'orders':
            $compare = pg_dashboard_v2_figure_compare($n('today'), $n('yesterday'));
            return $out($n('today'), $compare['note'], $compare['tone']);

        case 'online':
            $note = ($n('staff') > 0)
                ? lang(array('string' => '{var:1} colleague{suffix:1} signed in', 'vars' => pg_format_number($n('staff'), 0), 'suffix' => array(($n('staff') === 1) ? '' : 's')))
                : '';
            return $out($n('visitors'), $note, ($n('visitors') > 0) ? 'good' : 'plain');

        case 'forms':
            if ($n('pending') === 0) {
                return $out(0, '', 'plain');
            }
            $oldest = $n('oldest');
            $note = ($oldest > 0)
                ? lang(array('string' => 'oldest {var:1}', 'vars' => get_relative_time(array('timestamp' => $oldest, 'format' => 'plain_text'))))
                : '';
            return $out($n('pending'), $note, 'warn');

        case 'seo':
            $critical = $n('critical');
            $note = ($critical > 0)
                ? lang(array('string' => '{var:1} page{suffix:1} critical', 'vars' => pg_format_number($critical, 0), 'suffix' => array(($critical === 1) ? '' : 's')))
                : '';
            if (($critical > 0) || ($n('average') < 50)) {
                $tone = 'warn';
            } else {
                $tone = ($n('average') >= 80) ? 'good' : 'plain';
            }
            return $out($n('average'), $note, $tone);

        case 'a11y':
            if ($n('issues') === 0) {
                return $out(0, '', 'good');
            }
            return $out($n('issues'), lang(array('string' => 'on {var:1} page{suffix:1}', 'vars' => pg_format_number($n('pages'), 0), 'suffix' => array(($n('pages') === 1) ? '' : 's'))), 'warn');

        case 'tasks':
            if ($n('overdue') > 0) {
                return $out($n('open'), lang(array('string' => '{var:1} overdue', 'vars' => pg_format_number($n('overdue'), 0))), 'bad');
            }
            return $out($n('open'), '', 'plain');

        case 'inbox':
            $note = ($n('mentions') > 0)
                ? lang(array('string' => '{var:1} mention{suffix:1}', 'vars' => pg_format_number($n('mentions'), 0), 'suffix' => array(($n('mentions') === 1) ? '' : 's')))
                : '';
            return $out($n('unread'), $note, ($n('unread') > 0) ? 'warn' : 'plain');

        case 'blocks':
            return $out($n('count'), '', 'plain');

        case 'subscribers':
            return $out($n('count'), '', ($n('count') > 0) ? 'good' : 'plain');

        case 'flows':
            if ($n('failed') > 0) {
                return $out($n('runs'), lang(array('string' => '{var:1} failed', 'vars' => pg_format_number($n('failed'), 0))), 'bad');
            }
            return $out($n('runs'), ($n('runs') > 0) ? lang('no failures') : '', ($n('runs') > 0) ? 'good' : 'plain');
    }

    return null;
}

/**
 * The person's workspace signals, read once per request: tasks and inbox
 * share them.
 *
 * @param array $user
 * @return array ws_dashboard_signals()
 */
function pg_dashboard_v2_ws_signals($user)
{
    static $signals = array();

    $id = (int) $user['id'];

    if (!isset($signals[$id])) {
        $rights = pg_dashboard_v2_gate('workspace', $user) ? pg_dashboard_workspace_rights($user) : null;
        $signals[$id] = ($rights !== null) ? ws_dashboard_signals($rights) : array();
    }

    return $signals[$id];
}

/**
 * Sum one rollup column from the hour a time falls in onwards. Pure.
 *
 * @param array  $stats  pg_visitor_stats_range(): date => hour => columns
 * @param int    $since  unix time
 * @param string $column
 * @return int
 */
function pg_dashboard_v2_hours_since($stats, $since, $column)
{
    $from_date = date('Y-m-d', (int) $since);
    $from_hour = (int) date('G', (int) $since);
    $sum = 0;

    foreach ($stats as $date => $hours) {
        foreach ($hours as $hour => $values) {
            if (((string) $date > $from_date) || (((string) $date === $from_date) && ((int) $hour >= $from_hour))) {
                $sum += (int) ($values[$column] ?? 0);
            }
        }
    }

    return $sum;
}

/**
 * The raw counts behind a figure, each read the way the old dashboard's
 * widget or brief reads the same rows. The caller checks the gate.
 *
 * @param string $key
 * @param array  $user
 * @return array
 */
function pg_dashboard_v2_figure_raw($key, $user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $now = time();
    $midnight = strtotime(date('Y-m-d', $now));

    switch ((string) $key) {
        case 'visits':
            // The hourly rollup, as the Visitor Summaries widget reads it.
            $stats = pg_visitor_stats_range(date('Y-m-d', $midnight - 86400), date('Y-m-d', $midnight));
            return array(
                'today'     => pg_dashboard_v2_hours_since($stats, $midnight, 'visitors'),
                'yesterday' => array_sum(array_column($stats[date('Y-m-d', $midnight - 86400)] ?? array(), 'visitors')),
            );

        case 'online':
            // 20 minutes, the span the Online Engagement widget counts.
            return array(
                'visitors' => (int) db_value("SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . (int) ($now - 1200)),
                'staff'    => (int) db_value("SELECT COUNT(*) FROM user WHERE user_online_timestamp >= " . (int) ($now - 1200) . " AND user_role < 3 AND user_id != '" . (int) $user['id'] . "'"),
            );

        case 'forms':
            $row = db_item("SELECT COUNT(*) AS pending, MIN(submitted_timestamp) AS oldest FROM forms WHERE complete = '1' AND submitted_timestamp >= " . (int) ($now - 86400));
            return array('pending' => (int) ($row['pending'] ?? 0), 'oldest' => (int) ($row['oldest'] ?? 0));

        case 'orders':
            // status != 'incomplete': a basket somebody left is not an order.
            $row = db_item(
                "SELECT
                    SUM(CASE WHEN order_date >= " . (int) $midnight . " THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN order_date < " . (int) $midnight . " THEN 1 ELSE 0 END) AS yesterday
                 FROM orders
                 WHERE status != 'incomplete' AND order_date >= " . (int) ($midnight - 86400));
            return array('today' => (int) ($row['today'] ?? 0), 'yesterday' => (int) ($row['yesterday'] ?? 0));

        case 'seo':
            // Only current analyses: a page changed since it was scored is not
            // counted, as the pages list's own filter does.
            $row = db_item("SELECT ROUND(AVG(seo_score)) AS average, SUM(CASE WHEN seo_score < 30 THEN 1 ELSE 0 END) AS critical FROM page WHERE seo_analysis_current = 1");
            return array('average' => (int) ($row['average'] ?? 0), 'critical' => (int) ($row['critical'] ?? 0));

        case 'a11y':
            $row = db_item("SELECT COALESCE(SUM(occurrences), 0) AS issues, COUNT(DISTINCT entity_id) AS pages FROM seo_issue WHERE entity_type = 'page' AND LEFT(code, 5) = 'a11y_'");
            return array('issues' => (int) ($row['issues'] ?? 0), 'pages' => (int) ($row['pages'] ?? 0));

        case 'tasks':
            $signals = pg_dashboard_v2_ws_signals($user);
            return array('open' => (int) ($signals['open'] ?? 0), 'overdue' => (int) ($signals['overdue'] ?? 0));

        case 'inbox':
            $signals = pg_dashboard_v2_ws_signals($user);
            return array('unread' => (int) ($signals['inbox'] ?? 0), 'mentions' => (int) ($signals['mentions'] ?? 0));

        case 'blocks':
            return array('count' => pg_dashboard_v2_chip_count('blocks', $user, $now - 86400));

        case 'subscribers':
            return array('count' => pg_dashboard_v2_chip_count('subscribers', $user, pg_dashboard_days_back($midnight, 6)));

        case 'flows':
            $row = db_item("SELECT COUNT(*) AS runs, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed FROM ws_flow_runs WHERE started_at >= " . (int) $midnight);
            return array('runs' => (int) ($row['runs'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0));
    }

    return array();
}

/**
 * The counts a "since your last sign-in" chip may show.
 *
 * @return array key => array(gate, tone, url)
 */
function pg_dashboard_v2_chip_list()
{
    return array(
        'visits'      => array('gate' => 'visitors', 'tone' => 'good', 'url' => 'view_visitor_reports.php'),
        'orders'      => array('gate' => 'shop', 'tone' => 'good', 'url' => 'view_orders.php'),
        'forms'       => array('gate' => 'forms', 'tone' => 'warn', 'url' => 'view_submitted_forms.php'),
        'comments'    => array('gate' => 'staff', 'tone' => 'plain', 'url' => 'view_comments.php'),
        'subscribers' => array('gate' => 'staff', 'tone' => 'good', 'url' => 'view_contacts.php?filter=my_subscribers'),
        'files'       => array('gate' => 'files', 'tone' => 'plain', 'url' => 'view_files.php'),
        'blocks'      => array('gate' => 'waf', 'tone' => 'plain', 'url' => 'view_waf_log.php'),
        'tasks_done'  => array('gate' => 'workspace', 'tone' => 'good', 'url' => 'workspace_tasks.php'),
    );
}

/**
 * The chip's text for a count.
 *
 * @param string $key
 * @param int    $count
 * @return string
 */
function pg_dashboard_v2_chip_text($key, $count)
{
    $number = pg_format_number($count, 0);
    $suffix = array(($count === 1) ? '' : 's');

    switch ((string) $key) {
        case 'visits':
            return lang(array('string' => '+{var:1} visit{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'orders':
            return lang(array('string' => '{var:1} new order{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'forms':
            return lang(array('string' => '{var:1} new form{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'comments':
            return lang(array('string' => '{var:1} comment{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'subscribers':
            return lang(array('string' => '{var:1} new subscriber{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'files':
            return lang(array('string' => '{var:1} file{suffix:1} uploaded', 'vars' => $number, 'suffix' => $suffix));
        case 'blocks':
            return lang(array('string' => '{var:1} block{suffix:1}', 'vars' => $number, 'suffix' => $suffix));
        case 'tasks_done':
            return lang(array('string' => '{var:1} task{suffix:1} done', 'vars' => $number, 'suffix' => $suffix));
    }

    return $number;
}

/**
 * Counts to chips, in the given order: unknown keys and zero counts are
 * dropped. Pure.
 *
 * @param array $counts key => int
 * @return array list of array(key, text, tone, url)
 */
function pg_dashboard_v2_chips_shape($counts)
{
    $list = pg_dashboard_v2_chip_list();
    $chips = array();

    foreach ($counts as $key => $count) {
        if (!isset($list[$key]) || ((int) $count <= 0)) {
            continue;
        }

        $chips[] = array(
            'key'  => $key,
            'text' => pg_dashboard_v2_chip_text($key, (int) $count),
            'tone' => $list[$key]['tone'],
            'url'  => $list[$key]['url'],
        );
    }

    return $chips;
}

/**
 * What happened after a time for one chip. The caller checks the gate.
 *
 * @param string $key
 * @param array  $user
 * @param int    $since unix time
 * @return int
 */
function pg_dashboard_v2_chip_count($key, $user, $since)
{
    $since = (int) $since;

    switch ((string) $key) {
        case 'visits':
            return pg_dashboard_v2_hours_since(pg_visitor_stats_range(date('Y-m-d', $since), date('Y-m-d')), $since, 'visitors');
        case 'orders':
            return (int) db_value("SELECT COUNT(*) FROM orders WHERE status != 'incomplete' AND order_date >= " . $since);
        case 'forms':
            return (int) db_value("SELECT COUNT(*) FROM forms WHERE complete = '1' AND submitted_timestamp >= " . $since);
        case 'comments':
            return (int) db_value("SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id WHERE comments.created_timestamp >= " . $since . pg_comment_live_page_sql(pg_recycle_bin_folder_ids()));
        case 'subscribers':
            return (int) db_value("SELECT COUNT(*) FROM contacts WHERE opt_in = '1' AND email_address != '' AND timestamp >= " . $since);
        case 'files':
            return (int) db_value("SELECT COUNT(*) FROM files WHERE timestamp >= " . $since);
        case 'blocks':
            // SUM(hit_count): one row is a whole window of repeats.
            return (int) db_value("SELECT COALESCE(SUM(hit_count), 0) FROM waf_log WHERE action IN ('block', 'rate', 'ban') AND log_timestamp >= " . $since);
        case 'tasks_done':
            return (int) db_value(
                "SELECT COUNT(*) FROM ws_tasks t
                 INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . (int) $user['id'] . "'
                 WHERE t.status = 'done' AND t.completed_at >= " . $since);
    }

    return 0;
}

/**
 * The chips for the person since their previous sign-in, in the preset's
 * order; none when there was no previous sign-in.
 *
 * @param string[] $keys  the preset's chip keys
 * @param array    $user
 * @param int      $since signed_in_before, 0 when unknown
 * @return array pg_dashboard_v2_chips_shape()
 */
function pg_dashboard_v2_chips($keys, $user, $since)
{
    if ((int) $since <= 0) {
        return array();
    }

    $list = pg_dashboard_v2_chip_list();
    $counts = array();

    foreach ($keys as $key) {
        if (isset($list[$key]) && pg_dashboard_v2_figure_gate($list[$key]['gate'], $user)) {
            $counts[$key] = pg_dashboard_v2_chip_count($key, $user, $since);
        }
    }

    return pg_dashboard_v2_chips_shape($counts);
}

/**
 * Stored tiles to the ones drawn: a figure the person may not see turns into
 * the next allowed one not already shown; four, none twice, fewer only when
 * fewer figures are allowed. Pure.
 *
 * @param string[] $stored
 * @param string[] $allowed in select order
 * @return string[]
 */
function pg_dashboard_v2_tiles_resolve($stored, $allowed)
{
    $tiles = array();
    foreach ($stored as $key) {
        if (in_array($key, $allowed, true) && !in_array($key, $tiles, true)) {
            $tiles[] = $key;
        } else {
            $tiles[] = null;
        }
    }

    $spare = array_values(array_diff($allowed, array_filter($tiles, 'is_string')));

    foreach ($tiles as $i => $key) {
        if ($key === null) {
            $tiles[$i] = array_shift($spare);
        }
    }

    $tiles = array_values(array_filter($tiles, 'is_string'));

    // The card always has four tiles when four figures are allowed.
    while ((count($tiles) < 4) && !empty($spare)) {
        $tiles[] = array_shift($spare);
    }

    return array_slice($tiles, 0, 4);
}

/**
 * The shortcut pool: every preset's shortcuts and the nine "+ Add shortcut"
 * offers. count names what is shown after the label ("Forms · 4").
 *
 * @return array key => array(label, icon, url, gate, count)
 */
function pg_dashboard_v2_shortcuts()
{
    $s = function ($label, $icon, $url, $gate, $count = '') {
        return array('label' => $label, 'icon' => $icon, 'url' => $url, 'gate' => $gate, 'count' => $count);
    };

    return array(
        'new_page'        => $s(lang('New page'), 'bi-file-earmark-plus', 'add_page.php', 'files'),
        'upload_file'     => $s(lang('Upload file'), 'bi-upload', 'add_file.php', 'files'),
        'add_user'        => $s(lang('Add user'), 'bi-person-plus', 'add_user.php', 'manager_area'),
        'start_flow'      => $s(lang('Start flow'), 'bi-diagram-3', 'workspace_flows.php', 'flows'),
        'get_report'      => $s(lang('Get report'), 'bi-file-earmark-bar-graph', 'view_visitor_reports.php', 'visitors'),
        'back_up'         => $s(lang('Back up'), 'bi-cloud-arrow-up', 'backups.php', 'manager_area'),
        'visual_editor'   => $s(lang('Visual Editor'), 'bi-palette', 'view_styles.php', 'designer'),
        'forms'           => $s(lang('Forms'), 'bi-ui-checks', 'view_submitted_forms.php', 'forms', 'forms'),
        'orders'          => $s(lang('Orders'), 'bi-cart4', 'view_orders.php', 'shop', 'orders'),
        'edit_page'       => $s(lang('Edit page'), 'bi-pencil-square', 'view_pages.php', 'files'),
        'team_task'       => $s(lang('Task for the team'), 'bi-person-check', 'workspace_tasks.php', 'workspace'),
        'announcement'    => $s(lang('Publish announcement'), 'bi-megaphone', 'workspace.php', 'workspace'),
        'write_post'      => $s(lang('Write a post'), 'bi-pencil', 'add_page.php', 'files'),
        'comments'        => $s(lang('Comments'), 'bi-chat-square-quote', 'view_comments.php', 'staff', 'comments'),
        'newsletter'      => $s(lang('Send newsletter'), 'bi-envelope-paper', 'add_email_campaign.php', 'staff'),
        'products'        => $s(lang('Products'), 'bi-box-seam', 'view_products.php', 'shop'),
        'campaign'        => $s(lang('Campaign'), 'bi-tag', 'view_offers.php', 'shop'),
        'designs'         => $s(lang('Designs'), 'bi-vector-pen', 'view_design_files.php', 'designer'),
        'share_prototype' => $s(lang('Share prototype'), 'bi-share', 'prototype.php', 'manager_area'),
        'client_tasks'    => $s(lang('Client tasks'), 'bi-briefcase', 'workspace_tasks.php', 'workspace'),
    );
}

/**
 * What "+ Add shortcut" offers, in order: the next unused allowed one is
 * taken.
 *
 * @return string[]
 */
function pg_dashboard_v2_shortcut_offer()
{
    return array('new_page', 'upload_file', 'add_user', 'start_flow', 'get_report', 'back_up', 'visual_editor', 'forms', 'orders');
}

/**
 * Stored shortcuts to the ones drawn: known and allowed, none twice, six at
 * most. Pure.
 *
 * @param string[] $stored
 * @param string[] $allowed
 * @return string[]
 */
function pg_dashboard_v2_shortcuts_pick($stored, $allowed)
{
    $pick = array();

    foreach ($stored as $key) {
        if (in_array($key, $allowed, true) && !in_array($key, $pick, true)) {
            $pick[] = $key;
        }
    }

    return array_slice($pick, 0, 6);
}

/**
 * The shortcut keys the person may open.
 *
 * @param array $user
 * @return string[]
 */
function pg_dashboard_v2_shortcuts_allowed($user)
{
    $keys = array();

    foreach (pg_dashboard_v2_shortcuts() as $key => $shortcut) {
        if (pg_dashboard_v2_figure_gate($shortcut['gate'], $user)) {
            $keys[] = $key;
        }
    }

    return $keys;
}

/**
 * The count after a shortcut's label, 0 for none.
 *
 * @param string $count the shortcut's count key
 * @param array  $user
 * @return int
 */
function pg_dashboard_v2_shortcut_count($count, $user)
{
    if ($count === 'comments') {
        return (int) db_value("SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id WHERE comments.published = '0'" . pg_comment_live_page_sql(pg_recycle_bin_folder_ids()));
    }

    if ($count !== '') {
        $figure = pg_dashboard_v2_figure($count, $user);
        return ($figure !== null) ? (int) $figure['value'] : 0;
    }

    return 0;
}
