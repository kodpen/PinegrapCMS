<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Google Analytics card: active users, sessions, engagement rate and
 * views with their change against the 28 days before, and the traffic
 * sources as one stacked bar. Read from the report the hourly reading keeps
 * (pg_ga_state()), the one widget 32 shows; that report always covers 28
 * days, so the card has no period of its own.
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
 * Traffic sources as shares of one bar: the first $keep sources by
 * sessions and the rest folded into one "other" part. Shares are whole
 * percentages that add up to 100 (the largest remainder takes the
 * rounding).
 *
 * @param array  $sources rows of name and value
 * @param int    $keep
 * @param string $other   the folded part's name
 * @return array rows of name, value, share
 */
function pg_dashboard_v2_source_shares($sources, $keep, $other)
{
    $rows = array();
    $rest = 0;
    $total = 0;

    foreach ((array) $sources as $i => $source) {
        $value = max(0, (int) ($source['value'] ?? 0));
        $total += $value;

        if (count($rows) < $keep) {
            $rows[] = array('name' => (string) ($source['name'] ?? ''), 'value' => $value);
        } else {
            $rest += $value;
        }
    }

    if ($rest > 0) {
        $rows[] = array('name' => $other, 'value' => $rest);
    }

    if ($total <= 0) {
        return array();
    }

    $given = 0;
    $remainders = array();

    foreach ($rows as $i => $row) {
        $exact = ($row['value'] * 100) / $total;
        $rows[$i]['share'] = (int) floor($exact);
        $given += $rows[$i]['share'];
        $remainders[$i] = $exact - floor($exact);
    }

    arsort($remainders);

    foreach (array_keys($remainders) as $i) {
        if ($given >= 100) {
            break;
        }
        $rows[$i]['share']++;
        $given++;
    }

    return $rows;
}

function pg_dashboard_v2_card_ga($user, $period, $compare)
{
    if (((int) $user['role'] >= 3) && empty($user['manage_visitors'])) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

    $state = pg_ga_state();

    if (!pg_ga_linked($state)) {
        return array('status' => 'denied');
    }

    $report = $state['report'];

    if (!$report) {
        return array('status' => 'success', 'data' => array(
            'empty' => ($state['fetch_error'] !== '')
                ? lang(array('string' => 'The Google Analytics figures could not be read: {var:1}', 'vars' => $state['fetch_error']))
                : lang('No figures yet. They are read from Google Analytics within the hour.'),
        ));
    }

    $labels = array(
        'activeUsers'     => lang('Active Users'),
        'sessions'        => lang('Sessions'),
        'engagementRate'  => lang('Engagement rate'),
        'screenPageViews' => lang('Views'),
    );

    $figures = pg_ga_card_figures($report);
    $tiles = array();

    foreach ($labels as $metric => $label) {
        if (!isset($figures[$metric])) {
            continue;
        }

        $figure = $figures[$metric];
        $note = '';
        $tone = '';

        if ($figure['kind'] === 'rate') {
            $text = lang(array('string' => '{var:1}%', 'vars' => pg_format_number($figure['value'], 1)));

            if ($figure['change'] !== null) {
                $moved = round((float) $figure['change'], 1);
                $tone = ($moved > 0) ? 'good' : (($moved < 0) ? 'bad' : '');
                $note = ($moved == 0) ? lang('no change') : lang(array(
                    'string' => ($moved > 0) ? 'up {var:1} points' : 'down {var:1} points',
                    'vars'   => pg_format_number(abs($moved), 1),
                ));
            }
        } else {
            $text = pg_format_number($figure['value'], 0);

            if ($figure['change'] !== null) {
                $pct = (int) round((float) $figure['change'] * 100);
                $tone = ($pct > 0) ? 'good' : (($pct < 0) ? 'bad' : '');
                $note = ($pct === 0) ? lang('no change') : lang(array(
                    'string' => ($pct > 0) ? 'up {var:1}%' : 'down {var:1}%',
                    'vars'   => pg_format_number(abs($pct), 0),
                ));
            }
        }

        $tiles[] = array('label' => $label, 'text' => $text, 'note' => $note, 'tone' => $tone);
    }

    $shares = array();

    foreach (pg_dashboard_v2_source_shares($report['sources'], 3, lang('Other')) as $row) {
        $shares[] = array(
            'text'  => $row['name'],
            'share' => $row['share'],
            'note'  => lang(array('string' => '{var:1}%', 'vars' => pg_format_number($row['share'], 0))),
        );
    }

    return array('status' => 'success', 'data' => array(
        'tiles'         => $tiles,
        'range'         => prepare_form_data_for_output($report['from'], 'date', false) . ' – ' . prepare_form_data_for_output($report['to'], 'date', false),
        'sources_title' => lang('Traffic sources'),
        'sources'       => $shares,
        'url'           => ((int) $user['role'] < 3) ? pg_settings_link('privacy', 'pgset-analytics') : '',
        'link'          => (string) $state['property_name'],
    ));
}
