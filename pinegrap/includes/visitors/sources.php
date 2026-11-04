<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sources section of the Visitors overview: the channel split as one
 * stacked bar and a list with shares and changes, then the top referring
 * hosts, search engines, search terms, UTM source, medium and campaign,
 * tracking and affiliate codes, each with the earlier period's figure, and
 * a line saying which days came from the daily summary. Every figure comes
 * from pg_visitor_breakdown() (includes/fn/visitor_figures.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * How many values a top list shows.
 */
const PG_VISITORS_SOURCES_TOP = 10;

/**
 * The channel names as the screen shows them, in the order the channel rule
 * tries them.
 *
 * @return array channel => label
 */
function pg_visitors_sources_channel_labels()
{
    return array(
        'paid'     => lang('Paid'),
        'email'    => lang('E-mail'),
        'ai'       => lang('AI assistants'),
        'organic'  => lang('Organic search'),
        'social'   => lang('Social'),
        'referral' => lang('Referral'),
        'direct'   => lang('Direct'),
    );
}

/**
 * The top lists after the channels: the dimension and its heading. The
 * affiliate codes only with the affiliate program on.
 *
 * @param bool $affiliate
 * @return array dimension => label
 */
function pg_visitors_sources_lists($affiliate)
{
    $lists = array(
        'referring_host_name'     => lang('Referring sites'),
        'referring_search_engine' => lang('Search engines'),
        'referring_search_terms'  => lang('Search terms'),
        'utm_source'              => lang('UTM source'),
        'utm_medium'              => lang('UTM medium'),
        'utm_campaign'            => lang('UTM campaign'),
        'tracking_code'           => lang('Tracking codes'),
    );

    if ($affiliate) {
        $lists['affiliate_code'] = lang('Affiliate codes');
    }

    return $lists;
}

/**
 * Whether a top list is one of the campaign tags and codes, folded under
 * "Campaigns and codes". Pure.
 *
 * @param string $dimension a key of pg_visitors_sources_lists()
 * @return bool
 */
function pg_visitors_sources_is_campaign($dimension)
{
    return in_array($dimension, array('utm_source', 'utm_medium', 'utm_campaign', 'tracking_code', 'affiliate_code'), true);
}

/**
 * Visits by lower-cased value, as pg_visitor_merge_rows() groups them. Pure.
 *
 * @param array $rows list of array('value', 'visits')
 * @return array lower-cased value => visits
 */
function pg_visitors_sources_visits_by_value(array $rows)
{
    $map = array();

    foreach ($rows as $row) {
        $value = (string) $row['value'];
        $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $map[$key] = (isset($map[$key]) ? $map[$key] : 0) + (int) $row['visits'];
    }

    return $map;
}

/**
 * The channel rows of a period: every channel the rule knows, in its order,
 * then any other value the summary holds; a channel with no visit in either
 * period is left out. Pure.
 *
 *   channel  the name
 *   visits   visits now
 *   share    share of the channels' visits now, in percent with one decimal
 *   before   visits in the earlier period, null when not compared
 *
 * @param array      $rows        pg_visitor_breakdown('channel') rows now
 * @param array|null $before_rows the earlier period's rows, null when not compared
 * @param array      $order       pg_visitor_channels()
 * @return array
 */
function pg_visitors_sources_channel_rows(array $rows, $before_rows, array $order)
{
    $now = pg_visitors_sources_visits_by_value($rows);
    $before = is_array($before_rows) ? pg_visitors_sources_visits_by_value($before_rows) : null;

    $names = $order;

    foreach (array_merge(array_keys($now), is_array($before) ? array_keys($before) : array()) as $name) {
        if (!in_array((string) $name, $names, true)) {
            $names[] = (string) $name;
        }
    }

    $total = array_sum($now);
    $out = array();

    foreach ($names as $name) {
        $visits = isset($now[$name]) ? $now[$name] : 0;
        $earlier = is_array($before) ? (isset($before[$name]) ? $before[$name] : 0) : null;

        if (($visits === 0) && !$earlier) {
            continue;
        }

        $out[] = array(
            'channel' => $name,
            'visits'  => $visits,
            'share'   => ($total > 0) ? round($visits / $total * 100, 1) : 0.0,
            'before'  => $earlier,
        );
    }

    return $out;
}

/**
 * The rows of a top list with the earlier period's visits beside each one;
 * a visit without the value ('') is not a source and is left out. Pure.
 *
 * @param array      $rows        pg_visitor_breakdown() rows now
 * @param array|null $before_rows the earlier period's rows, null when not compared
 * @param int        $limit
 * @return array list of array('value', 'visits', 'before' => int|null)
 */
function pg_visitors_sources_top_rows(array $rows, $before_rows, $limit)
{
    $before = is_array($before_rows) ? pg_visitors_sources_visits_by_value($before_rows) : null;
    $out = array();

    foreach ($rows as $row) {
        $value = trim((string) $row['value']);

        if ($value === '') {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        $out[] = array(
            'value'  => $value,
            'visits' => (int) $row['visits'],
            'before' => is_array($before) ? (isset($before[$key]) ? $before[$key] : 0) : null,
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The line saying which days of the period came from the daily summary and
 * which from the visit records; '' when there is no day at all. Pure.
 *
 * @param array $note 'summary_days', 'raw_days', 'summary_to' (Y-m-d or null)
 * @return string text
 */
function pg_visitors_sources_days_text(array $note)
{
    $summary = isset($note['summary_days']) ? (int) $note['summary_days'] : 0;
    $raw = isset($note['raw_days']) ? (int) $note['raw_days'] : 0;
    $to = isset($note['summary_to']) ? (string) $note['summary_to'] : '';

    if (($summary === 0) || ($to === '')) {
        return ($raw > 0) ? lang('Every day of this period was counted from the visit records.') : '';
    }

    $date = prepare_form_data_for_output($to, 'date', false);

    if ($raw === 0) {
        return lang(array(
            'string' => 'Every day of this period came from the daily summary, the last one {var:1}.',
            'vars'   => array($date),
        ));
    }

    return lang(array(
        'string' => '{var:1} days up to {var:2} came from the daily summary, {var:3} from the visit records.',
        'vars'   => array(pg_format_number($summary, 0), $date, pg_format_number($raw, 0)),
    ));
}

/**
 * The channel split as one bar of segments, each as wide as its share.
 *
 * @param array $rows   pg_visitors_sources_channel_rows()
 * @param array $labels pg_visitors_sources_channel_labels()
 * @return string HTML
 */
function pg_visitors_sources_stack(array $rows, array $labels)
{
    $segments = '';

    foreach ($rows as $row) {
        if ($row['visits'] <= 0) {
            continue;
        }

        $name = isset($labels[$row['channel']]) ? $labels[$row['channel']] : $row['channel'];
        $class = preg_replace('/[^a-z0-9_]/', '', strtolower($row['channel']));
        $share = pg_visitors_percent($row['share'], 1);

        $segments .= '<span class="pg-visitors-sources-segment is-' . h($class) . '" style="width:' . h(sprintf('%.1F', $row['share'])) . '%" title="' . h($name . ': ' . $share) . '"></span>';
    }

    return '<div class="pg-visitors-sources-stack" aria-hidden="true">' . $segments . '</div>';
}

/**
 * The channel list: a swatch matching the bar, the name, visits, share and,
 * when compared, the change (the earlier visits on hover).
 *
 * @param array $rows    pg_visitors_sources_channel_rows()
 * @param array $labels  pg_visitors_sources_channel_labels()
 * @param bool  $compare
 * @return string HTML
 */
function pg_visitors_sources_channel_table(array $rows, array $labels, $compare)
{
    $headings = array(lang('Channel'), array('label' => lang('Visits'), 'end' => true), array('label' => lang('Share'), 'end' => true));

    if ($compare) {
        $headings[] = pg_visitors_change_heading();
    }

    $body = array();

    foreach ($rows as $row) {
        $name = isset($labels[$row['channel']]) ? $labels[$row['channel']] : $row['channel'];
        $class = preg_replace('/[^a-z0-9_]/', '', strtolower($row['channel']));

        $cells = array(
            array('html' => '<span class="pg-visitors-sources-swatch is-' . h($class) . '" aria-hidden="true"></span>' . h($name)),
            array('html' => h(pg_format_number($row['visits'], 0)), 'sort' => $row['visits']),
            array('html' => h(pg_visitors_percent($row['share'], 1)), 'sort' => $row['share']),
        );

        if ($compare) {
            $cells[] = pg_visitors_change_cell($row['visits'], ($row['before'] !== null) ? (int) $row['before'] : null);
        }

        $body[] = $cells;
    }

    return pg_visitors_table($headings, $body, array('caption' => lang('Visits by channel')));
}

/**
 * One top list as a small table: the value, visits and, when compared, the
 * change (the earlier visits on hover). The first rows show; the rest wait
 * behind "Show all".
 *
 * @param string $title
 * @param array  $rows    pg_visitors_sources_top_rows()
 * @param bool   $compare
 * @return string HTML; '' for a list with no row
 */
function pg_visitors_sources_top_table($title, array $rows, $compare)
{
    // An empty list says nothing a reader needs: it is left out.
    if (!$rows) {
        return '';
    }

    $headings = array(lang('Value'), array('label' => lang('Visits'), 'end' => true));

    if ($compare) {
        $headings[] = pg_visitors_change_heading();
    }

    $body = array();

    foreach ($rows as $row) {
        $cells = array(
            array('html' => '<span class="text-break">' . h($row['value']) . '</span>'),
            array('html' => h(pg_format_number($row['visits'], 0)), 'sort' => $row['visits']),
        );

        if ($compare) {
            $cells[] = pg_visitors_change_cell($row['visits'], ($row['before'] !== null) ? (int) $row['before'] : null);
        }

        $body[] = $cells;
    }

    return pg_visitors_block($title, pg_visitors_table($headings, $body, array(
        'caption'    => $title,
        'limit'      => PG_VISITORS_LIST_ROWS,
        'hide_first' => true,
    )), 'pg-visitors-sources-list');
}

/**
 * The sources section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_sources(array $period, array $user)
{
    if (!function_exists('pg_visitor_breakdown')) {
        return '';
    }

    $from = (int) $period['from_ts'];
    $to = (int) $period['to_ts'] - 1;
    $compare = !empty($period['compare']);
    $previous_from = (int) $period['previous_from_ts'];
    $previous_to = (int) $period['previous_to_ts'] - 1;

    $read = function ($dimension, $limit, $earlier) use ($from, $to, $previous_from, $previous_to) {
        $answer = $earlier
            ? pg_visitor_breakdown($dimension, $previous_from, $previous_to, $limit)
            : pg_visitor_breakdown($dimension, $from, $to, $limit);

        return is_array($answer) ? $answer : array('rows' => array(), 'summary_days' => 0, 'raw_days' => 0, 'summary_to' => null);
    };

    $labels = pg_visitors_sources_channel_labels();
    $channels = $read('channel', 50, false);
    $channel_rows = pg_visitors_sources_channel_rows(
        $channels['rows'],
        $compare ? $read('channel', 50, true)['rows'] : null,
        pg_visitor_channels()
    );

    $has_visits = false;

    foreach ($channel_rows as $row) {
        $has_visits = $has_visits || ($row['visits'] > 0);
    }

    if ($has_visits) {
        $split = pg_visitors_sources_stack($channel_rows, $labels)
            . pg_visitors_sources_channel_table($channel_rows, $labels, $compare);
    } else {
        $split = pg_visitors_empty();
    }

    $lists = '';
    $campaign_lists = '';
    $campaign_count = 0;
    $affiliate = defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM;

    foreach (pg_visitors_sources_lists($affiliate) as $dimension => $title) {
        // One more than shown, as a visit without the value is dropped; the
        // earlier list is read deeper so a value new in the top still finds
        // its earlier figure.
        $rows = pg_visitors_sources_top_rows(
            $read($dimension, PG_VISITORS_SOURCES_TOP + 1, false)['rows'],
            $compare ? $read($dimension, 200, true)['rows'] : null,
            PG_VISITORS_SOURCES_TOP
        );

        $table = pg_visitors_sources_top_table($title, $rows, $compare);

        // The campaign tags and codes are folded under one heading: most
        // visits carry none, and six small lists of them crowded the
        // section's sites and searches.
        if (pg_visitors_sources_is_campaign($dimension)) {
            $campaign_lists .= $table;
            $campaign_count += ($table !== '') ? 1 : 0;
        } else {
            $lists .= $table;
        }
    }

    if ($campaign_count > 0) {
        $lists .= '
            <details class="pg-visitors-group">
                <summary><i class="bi bi-chevron-right pg-visitors-group-chevron" aria-hidden="true"></i>' . h(lang('Campaigns and codes')) . ' <span class="pg-visitors-group-count">' . h(pg_format_number($campaign_count, 0)) . '</span></summary>
                <div class="pg-visitors-sources-lists">' . $campaign_lists . '</div>
            </details>';
    }

    $days = pg_visitors_sources_days_text($channels);

    $body = '
        <div class="pg-visitors-sources-split">' . $split . '</div>
        <div class="pg-visitors-sources-lists">' . $lists . '</div>
        ' . ($days !== '' ? '<p class="pg-visitors-note">' . h($days) . '</p>' : '');

    return pg_visitors_section('sources', lang('Sources'), $body);
}
