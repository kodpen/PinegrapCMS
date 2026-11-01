<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's figures: one number with a note and a tone, shared by
 * the greeting card's tiles, the goals and the sign-in chips. Each figure
 * checks its own gate, the rule of the screen its number links to, and
 * answers null to a person who may not see it.
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
 * The eleven figures a tile may show, in the order the tile select lists
 * them, with their labels.
 *
 * @return array key => label
 */
function pg_dashboard_v2_figures()
{
    return array(
        'visits'  => lang('Visits today'),
        'online'  => lang('On the site now'),
        'forms'   => lang('Pending forms'),
        'orders'  => lang('Orders today'),
        'seo'     => lang('Average SEO score'),
        'a11y'    => lang('Accessibility'),
        'tasks'   => lang('Open tasks'),
        'inbox'   => lang('Inbox'),
        'blocked' => lang('Blocked requests'),
        'subs'    => lang('New subscribers'),
        'flows'   => lang('Flow runs'),
    );
}

/**
 * May the person see this figure? The rule of the screen it opens.
 *
 * @param string $key  a key of pg_dashboard_v2_figures()
 * @param array  $user the row validate_user() returned
 * @return bool
 */
function pg_dashboard_v2_figure_allowed($key, $user)
{
    $role = (int) $user['role'];

    switch ((string) $key) {
        case 'visits':
        case 'online':
            return pg_dashboard_v2_gate('visitors', $user);
        case 'forms':
            return defined('FORMS') && (FORMS === true) && ($role < 3);
        case 'orders':
            return pg_dashboard_v2_gate('shop', $user);
        case 'seo':
            return $role < 3;
        case 'a11y':
            // No stored count of accessibility issues exists yet: the check
            // runs on a page's HTML when it is opened. Never offered until
            // one does.
            return false;
        case 'tasks':
        case 'inbox':
        case 'flows':
            return pg_dashboard_v2_gate('workspace', $user);
        case 'blocked':
            return ($role < 3) && function_exists('waf_mode') && (waf_mode() !== 'off');
        case 'subs':
            return $role < 3;
    }

    return false;
}

/**
 * One figure's answer.
 *
 * @param int    $value
 * @param string $note  already translated
 * @param string $tone  good, warn, bad or '' (plain)
 * @param string $url   the list the figure opens, relative to the panel
 * @return array value, text, note, tone, url
 */
function pg_dashboard_v2_figure_answer($value, $note, $tone, $url)
{
    $value = (int) $value;

    return array(
        'value' => $value,
        'text'  => function_exists('pg_format_number') ? pg_format_number($value, 0) : (string) $value,
        'note'  => (string) $note,
        'tone'  => in_array($tone, array('good', 'warn', 'bad'), true) ? $tone : '',
        'url'   => (string) $url,
    );
}

/**
 * The tone of a count compared with the one before it.
 *
 * @param int $now
 * @param int $before
 * @return string good when it grew, '' otherwise
 */
function pg_dashboard_v2_figure_trend($now, $before)
{
    return ((int) $now > (int) $before) ? 'good' : '';
}

/**
 * The start of today in the site's time zone, which the counts of "today"
 * use, as the old dashboard's do.
 *
 * @param int $now
 * @return int
 */
function pg_dashboard_v2_day_start($now)
{
    return (int) strtotime(date('Y-m-d', (int) $now) . ' 00:00:00');
}

/**
 * The figure for the person, or null when they may not see it or it has no
 * answer.
 *
 * @param string $key   a key of pg_dashboard_v2_figures()
 * @param array  $user  the row validate_user() returned
 * @param int    $now   the time counted up to
 * @return array|null see pg_dashboard_v2_figure_answer()
 */
function pg_dashboard_v2_figure($key, $user, $now = 0)
{
    $labels = pg_dashboard_v2_figures();

    if (!isset($labels[$key]) || !pg_dashboard_v2_figure_allowed($key, $user)) {
        return null;
    }

    $now = ($now > 0) ? (int) $now : time();
    $day = pg_dashboard_v2_day_start($now);

    switch ($key) {

        case 'visits':
            $row = db_item("SELECT
                    SUM(CASE WHEN start_timestamp >= " . $day . " THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN start_timestamp < " . $day . " THEN 1 ELSE 0 END) AS before_day
                FROM visitors WHERE start_timestamp >= " . ($day - 86400));
            $today = (int) ($row['today'] ?? 0);
            $before = (int) ($row['before_day'] ?? 0);
            return pg_dashboard_v2_figure_answer($today,
                lang(array('string' => 'yesterday {var:1}', 'vars' => array(pg_dashboard_v2_figure_answer($before, '', '', '')['text']))),
                pg_dashboard_v2_figure_trend($today, $before), 'view_visitor_reports.php');

        case 'online':
            // The twenty minutes the old dashboard's greeting counts as "now".
            return pg_dashboard_v2_figure_answer(
                (int) db_value("SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . ($now - 1200)),
                lang('live'), 'good', 'view_visitor_reports.php');

        case 'forms':
            // Pending: a complete submission of the last 30 days that nobody
            // has changed since it came in. Forms have no answered state of
            // their own; a staff edit moves last_modified_timestamp.
            $row = db_item("SELECT COUNT(*) AS waiting, MIN(submitted_timestamp) AS oldest FROM forms
                WHERE complete = '1' AND last_modified_timestamp <= submitted_timestamp
                AND submitted_timestamp >= " . ($now - 30 * 86400));
            $waiting = (int) ($row['waiting'] ?? 0);
            $note = ($waiting > 0)
                ? lang(array('string' => 'oldest {var:1}', 'vars' => array(get_relative_time(array('timestamp' => (int) $row['oldest'], 'format' => 'plain_text')))))
                : lang('none waiting');
            return pg_dashboard_v2_figure_answer($waiting, $note, ($waiting > 0) ? 'warn' : 'good', 'view_submitted_forms.php');

        case 'orders':
            $row = db_item("SELECT
                    SUM(CASE WHEN order_date >= " . $day . " THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN order_date < " . $day . " THEN 1 ELSE 0 END) AS before_day
                FROM orders WHERE status != 'incomplete' AND order_date >= " . ($day - 86400));
            $today = (int) ($row['today'] ?? 0);
            $before = (int) ($row['before_day'] ?? 0);
            return pg_dashboard_v2_figure_answer($today,
                lang(array('string' => 'yesterday {var:1}', 'vars' => array(pg_dashboard_v2_figure_answer($before, '', '', '')['text']))),
                pg_dashboard_v2_figure_trend($today, $before), 'view_orders.php');

        case 'seo':
            // Only scores that are current: a page changed since it was
            // analysed is left out, as the pages list's filter does.
            $row = db_item("SELECT AVG(seo_score) AS average,
                    SUM(CASE WHEN seo_score < 30 THEN 1 ELSE 0 END) AS critical
                FROM page WHERE seo_analysis_current = 1");
            $critical = (int) ($row['critical'] ?? 0);
            return pg_dashboard_v2_figure_answer((int) round((float) ($row['average'] ?? 0)),
                ($critical > 0)
                    ? lang(array('string' => '{var:1} page{suffix:1} critical', 'vars' => array($critical)))
                    : lang('no critical page'),
                ($critical > 0) ? 'warn' : 'good', 'view_pages.php');

        case 'tasks':
            require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
            $row = db_item("SELECT COUNT(*) AS open_count,
                    SUM(CASE WHEN t.due_date > '0000-00-00' AND t.due_date < '" . e(date('Y-m-d', $now)) . "' THEN 1 ELSE 0 END) AS overdue_count
                FROM ws_tasks t
                INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . (int) $user['id'] . "'
                WHERE t.status IN ('todo', 'doing', 'waiting')");
            $overdue = (int) ($row['overdue_count'] ?? 0);
            return pg_dashboard_v2_figure_answer((int) ($row['open_count'] ?? 0),
                ($overdue > 0) ? lang(array('string' => '{var:1} overdue', 'vars' => array($overdue))) : lang('none overdue'),
                ($overdue > 0) ? 'bad' : 'good', 'workspace_tasks.php');

        case 'inbox':
            require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
            $unread = (int) ws_inbox_unread_count((int) $user['id']);
            return pg_dashboard_v2_figure_answer($unread,
                ($unread > 0) ? lang('unread') : lang('all read'),
                '', 'workspace.php?view=home&inbox=1');

        case 'blocked':
            // SUM(hit_count): one row stands for a whole window of repeats.
            return pg_dashboard_v2_figure_answer(
                (int) db_value("SELECT COALESCE(SUM(hit_count), 0) FROM waf_log
                    WHERE action IN ('block', 'rate', 'ban') AND log_timestamp >= " . ($now - 86400)),
                lang('last 24 hours'), '', 'view_waf_log.php');

        case 'subs':
            // Contacts added this week who accept mail.
            return pg_dashboard_v2_figure_answer(
                (int) db_value("SELECT COUNT(*) FROM contacts WHERE opt_in = '1' AND timestamp >= " . ($now - 7 * 86400)),
                lang('this week'), 'good', 'view_contacts.php');

        case 'flows':
            require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
            $row = db_item("SELECT COUNT(*) AS runs, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
                FROM ws_flow_runs WHERE started_at >= " . $day);
            $failed = (int) ($row['failed'] ?? 0);
            return pg_dashboard_v2_figure_answer((int) ($row['runs'] ?? 0),
                ($failed > 0) ? lang(array('string' => '{var:1} failed', 'vars' => array($failed))) : lang('today'),
                ($failed > 0) ? 'bad' : '', 'workspace_flows.php');
    }

    return null;
}
