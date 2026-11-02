<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Google Search card: clicks, impressions, click rate and average
 * position over the period with their change against the period before,
 * and the queries that brought the most clicks. Read from the figures the
 * Search Console job downloads (includes/fn/search_console.php), the ones
 * widget 31 reads; Google publishes them two or three days late, so the
 * period ends on the last day with figures, not today.
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
 * The days a period index covers: the last day with figures, 7 or 28 days
 * (Search Console's own spans).
 *
 * @param int $period
 * @return int
 */
function pg_dashboard_v2_gsearch_days($period)
{
    $days = array(0 => 1, 1 => 7, 2 => 28);

    return $days[(int) $period] ?? 7;
}

/**
 * The change of an average position. A smaller position is better, so the
 * tone is the other way round from a count; null without one to compare.
 *
 * @param float $now
 * @param float $before
 * @return array|null text, tone
 */
function pg_dashboard_v2_position_change($now, $before)
{
    if (((float) $now <= 0) || ((float) $before <= 0)) {
        return null;
    }

    $moved = round((float) $before - (float) $now, 1);

    if ($moved > 0) {
        return array('tone' => 'good', 'text' => lang(array('string' => 'up {var:1} places', 'vars' => pg_format_number($moved, 1))));
    }

    if ($moved < 0) {
        return array('tone' => 'bad', 'text' => lang(array('string' => 'down {var:1} places', 'vars' => pg_format_number(-$moved, 1))));
    }

    return array('tone' => '', 'text' => lang('no change'));
}

function pg_dashboard_v2_card_gsearch($user, $period, $compare)
{
    if (((int) $user['role'] >= 3) && empty($user['manage_visitors'])) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

    if (!pg_gsc_linked()) {
        return array('status' => 'denied');
    }

    $latest = pg_gsc_latest_day();

    if ($latest === '') {
        return array('status' => 'success', 'data' => array(
            'empty' => lang('No figures yet. Google publishes them two or three days late; the daily job downloads them as they come.'),
        ));
    }

    $range = pg_gsc_period($latest, pg_dashboard_v2_gsearch_days($period));
    $now = pg_gsc_totals($range['from'], $range['to']);
    $before = pg_gsc_totals($range['previous_from'], $range['previous_to']);

    $tile = function ($label, $text, $change) {
        return array(
            'label' => $label,
            'text'  => $text,
            'note'  => $change ? $change['text'] : '',
            'tone'  => $change ? $change['tone'] : '',
        );
    };

    $tiles = array(
        $tile(lang('Clicks'), pg_format_number($now['clicks'], 0), pg_dashboard_v2_change($now['clicks'], $before['clicks'])),
        $tile(lang('Impressions'), pg_format_number($now['impressions'], 0), pg_dashboard_v2_change($now['impressions'], $before['impressions'])),
        $tile(lang('Click rate'), lang(array('string' => '{var:1}%', 'vars' => pg_format_number($now['ctr'] * 100, 1))), null),
        $tile(lang('Average position'), ($now['impressions'] > 0) ? pg_format_number($now['position'], 1) : '–', pg_dashboard_v2_position_change($now['position'], $before['position'])),
    );

    $rows = array();

    foreach (pg_gsc_top('query', $range['from'], $range['to'], 3) as $query) {
        $rows[] = array(
            'text'  => (string) $query['name'],
            'note'  => lang(array('string' => 'Average position {var:1}', 'vars' => pg_format_number($query['position'], 1))),
            'value' => lang(array('string' => '{var:1} clicks', 'vars' => pg_format_number($query['clicks'], 0))),
        );
    }

    return array('status' => 'success', 'data' => array(
        'tiles'      => $tiles,
        'range'      => prepare_form_data_for_output($range['from'], 'date', false) . ' – ' . prepare_form_data_for_output($range['to'], 'date', false),
        'rows_title' => lang('Top searches'),
        'rows'       => $rows,
        'none'       => lang('No search brought a click in this period.'),
        'url'        => 'search_console.php',
        'link'       => lang('Google Search Console'),
    ));
}
