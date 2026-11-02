<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The My goals card: up to three goals the person set, each a ring with how
 * far they are over its week or month and whether they are ahead of the
 * time gone by. Goals are kept in the person's layout and saved by the card
 * through dashboard_personal_save (includes/dashboard/v2/personal.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

/**
 * What a goal's metric counted from a time on. The caller checks the gate.
 *
 * @param string $metric a key of pg_dashboard_v2_goal_metrics()
 * @param array  $user
 * @param int    $since  unix time
 * @return int revenue in whole units of the base currency, else a count
 */
function pg_dashboard_v2_goal_value($metric, $user, $since)
{
    if ($metric === 'revenue') {
        // Totals are stored in minor units; the goal's target is typed in
        // whole ones.
        return (int) round(((float) db_value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('complete', 'exported') AND order_date >= " . (int) $since)) / 100);
    }

    return (int) pg_dashboard_v2_chip_count($metric, $user, $since);
}

/**
 * A goal's figure as text: money for revenue, a number for the rest.
 *
 * @param string $metric
 * @param int    $value
 * @return string plain text
 */
function pg_dashboard_v2_goal_text($metric, $value)
{
    if ($metric === 'revenue') {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
        $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';
        return html_entity_decode(pg_format_money($value, $symbol), ENT_QUOTES, 'UTF-8');
    }

    return pg_format_number($value, 0);
}

function pg_dashboard_v2_card_goals($user, $period, $compare)
{
    $allowed = pg_dashboard_v2_goal_metrics_allowed($user);
    $metrics = pg_dashboard_v2_goal_metrics();
    $now = time();
    $goals = array();

    // A goal on a metric the person can no longer count stays stored and is
    // only left out here.
    foreach (pg_dashboard_v2_goals_clean(pg_dashboard_v2_personal($user, 'goals'), array_keys($metrics)) as $i => $goal) {
        if (!in_array($goal['metric'], $allowed, true)) {
            continue;
        }

        $span = pg_dashboard_v2_goal_span($goal['span'], $now);
        $value = pg_dashboard_v2_goal_value($goal['metric'], $user, $span['start']);
        $progress = pg_dashboard_v2_goal_progress($value, $goal['target'], ($now - $span['start']) / max(1, $span['end'] - $span['start']));
        $days = max(0, (int) ceil(($span['end'] - $now) / 86400));
        $left = lang(array('string' => '{var:1} day{suffix:1} left', 'vars' => pg_format_number($days, 0), 'suffix' => array(($days === 1) ? '' : 's')));
        $target = pg_dashboard_v2_goal_text($goal['metric'], $goal['target']);

        switch ($progress['state']) {
            case 'done':
                $note = lang('Reached');
                $tone = 'good';
                break;
            case 'ahead':
                $note = lang('On track') . ' · ' . $left;
                $tone = 'good';
                break;
            default:
                $note = lang('Behind') . ' · ' . $left;
                $tone = 'warn';
        }

        $goals[] = array(
            'index'  => $i,
            'name'   => $goal['name'],
            'value'  => pg_dashboard_v2_goal_text($goal['metric'], $value),
            'of'     => lang(array('string' => 'of {var:1}', 'vars' => $target)),
            'pct'    => lang(array('string' => '{var:1}%', 'vars' => pg_format_number($progress['pct'], 0))),
            'ring'   => $progress['ring'],
            'note'   => $note,
            'tone'   => $tone,
            'label'  => lang(array('string' => '{var:1}: {var:2} of {var:3}', 'vars' => array($goal['name'], pg_format_number($progress['pct'], 0) . '%', $target))),
        );
    }

    $choices = array();

    foreach ($allowed as $key) {
        $choices[$key] = $metrics[$key]['label'];
    }

    return array('status' => 'success', 'data' => array(
        'goals'   => $goals,
        'stored'  => pg_dashboard_v2_goals_clean(pg_dashboard_v2_personal($user, 'goals'), array_keys($metrics)),
        'metrics' => $choices,
        'most'    => PG_DASHBOARD_V2_MAX_GOALS,
        'locked'  => pg_dashboard_v2_role_locked((int) $user['role']),
        'labels'  => array(
            'none'   => lang('No goals yet. Set one and see how far you are.'),
            'add'    => lang('Add goal'),
            'name'   => lang('Goal'),
            'metric' => lang('What it counts'),
            'target' => lang('Target'),
            'span'   => lang('Period'),
            'week'   => lang('This week'),
            'month'  => lang('This month'),
            'save'   => lang('Save'),
            'cancel' => lang('Cancel'),
            'remove' => lang('Remove goal {var:1}'),
            'failed' => lang('The change could not be saved.'),
        ),
    ));
}
