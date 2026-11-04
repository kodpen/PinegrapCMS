<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Google search section of the Visitors overview: clicks, impressions,
 * click rate and average position with their change, clicks and impressions
 * by day, the top searches and pages, and the searches worth working on.
 * Read from the figures the Search Console job downloads into
 * seo_search_daily (includes/fn/search_console.php). Google publishes them
 * two or three days late, so the section's window has the overview period's
 * length but ends on the latest day with figures when the period runs past
 * it; the dates are said under the tiles. A site not linked to Search
 * Console gets a small card pointing to search_console.php instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

// How many rows each list shows.
const PG_VISITORS_GOOGLE_TOP = 10;

// How many searches the opportunity rules look at, most clicks first.
const PG_VISITORS_GOOGLE_POOL = 500;

// The positions of a search that is close to the first page: the bottom of
// page one and the whole of page two.
const PG_VISITORS_GOOGLE_NEAR_FROM = 8;
const PG_VISITORS_GOOGLE_NEAR_TO = 20;

/**
 * The days the section reads: as many as the overview period has, ending on
 * the period's last day or on the latest day with figures when that comes
 * first. Pure.
 *
 * @param array  $period pg_visitor_period()
 * @param string $latest Y-m-d, pg_gsc_latest_day()
 * @return array from, to, previous_from, previous_to, days, early (true when
 *               the window ends before the period does)
 */
function pg_visitors_google_window(array $period, $latest)
{
    $days = max(1, (int) $period['days']);
    $end = ((string) $latest !== '' && (string) $latest < (string) $period['to']) ? (string) $latest : (string) $period['to'];
    $window = pg_gsc_period($end, $days);

    $window['days'] = $days;
    $window['early'] = ($end !== (string) $period['to']);

    return $window;
}

/**
 * The median of a list of numbers; 0 for an empty list. Pure.
 *
 * @param array $numbers
 * @return float
 */
function pg_visitors_google_median(array $numbers)
{
    $numbers = array_map('floatval', array_values($numbers));

    if (!$numbers) {
        return 0.0;
    }

    sort($numbers);

    $middle = intdiv(count($numbers), 2);

    return (count($numbers) % 2) ? $numbers[$middle] : (($numbers[$middle - 1] + $numbers[$middle]) / 2);
}

/**
 * The searches worth working on, most impressions first. Pure.
 *
 *   low_ctr  shown more often than the median search of the list, clicked
 *            at under half the site's click rate: the title and the
 *            description do not win the click
 *   near     an average position from 8 to 20 with impressions: a little
 *            more weight puts the page on the first page or higher on it
 *
 * @param array $queries  pg_gsc_top('query', ...) rows
 * @param float $site_ctr the site's click rate over the window, a fraction
 * @param int   $limit    rows per list
 * @return array low_ctr, near
 */
function pg_visitors_google_opportunities(array $queries, $site_ctr, $limit = PG_VISITORS_GOOGLE_TOP)
{
    $median = pg_visitors_google_median(array_column($queries, 'impressions'));
    $low = array();
    $near = array();

    foreach ($queries as $query) {
        $impressions = (int) $query['impressions'];

        if ($impressions <= 0) {
            continue;
        }

        if (($impressions > $median) && ((float) $site_ctr > 0) && ((float) $query['ctr'] < ((float) $site_ctr / 2))) {
            $low[] = $query;
        }

        if (((float) $query['position'] >= PG_VISITORS_GOOGLE_NEAR_FROM) && ((float) $query['position'] <= PG_VISITORS_GOOGLE_NEAR_TO)) {
            $near[] = $query;
        }
    }

    $by_impressions = function ($a, $b) {
        return ((int) $b['impressions'] - (int) $a['impressions']) ?: strcmp((string) $a['name'], (string) $b['name']);
    };

    usort($low, $by_impressions);
    usort($near, $by_impressions);

    return array(
        'low_ctr' => array_slice($low, 0, max(1, (int) $limit)),
        'near'    => array_slice($near, 0, max(1, (int) $limit)),
    );
}

/**
 * The Search Console performance report of a property, filtered on the
 * searches containing a query, over a range. Pure.
 *
 * @param string $property the linked property (pg_gsc_settings())
 * @param string $query
 * @param string $from     Y-m-d
 * @param string $to       Y-m-d
 * @return string URL
 */
function pg_visitors_google_query_url($property, $query, $from, $to)
{
    return 'https://search.google.com/search-console/performance/search-analytics?' . http_build_query(array(
        'resource_id' => (string) $property,
        'query'       => '*' . (string) $query,
        'start_date'  => str_replace('-', '', (string) $from),
        'end_date'    => str_replace('-', '', (string) $to),
    ), '', '&', PHP_QUERY_RFC3986);
}

/**
 * A fraction as a percent with one decimal.
 *
 * @param float $fraction
 * @return string
 */
function pg_visitors_google_percent($fraction)
{
    return pg_visitors_percent((float) $fraction * 100, 1);
}

/**
 * The card a site not linked to Search Console gets.
 *
 * @return string HTML
 */
function pg_visitors_google_unlinked()
{
    $body = '
        <div class="pg-visitors-google-unlinked">
            <i class="bi bi-google" aria-hidden="true"></i>
            <p class="mb-0">' . h(lang('Link the site to Google Search Console to see here the searches that bring visitors, how often Google shows the pages and where they rank.')) . '</p>
            <a class="btn btn-sm btn-outline theme-primary" href="search_console.php">' . h(lang('Set up Search Console')) . '</a>
        </div>';

    return pg_visitors_section('google', lang('Google Search'), $body);
}

/**
 * A search as a link to it in Search Console.
 *
 * @param array  $row      pg_gsc_top() row
 * @param string $property
 * @param array  $window   pg_visitors_google_window()
 * @return array a pg_visitors_table() cell
 */
function pg_visitors_google_query_cell(array $row, $property, array $window)
{
    return array(
        'sort' => (string) $row['name'],
        'html' => '<a href="' . h(pg_visitors_google_query_url($property, $row['name'], $window['from'], $window['to'])) . '" target="_blank" rel="noopener" class="text-break">'
            . h($row['name']) . ' <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i>'
            . '<span class="visually-hidden"> ' . h(lang('(opens in Search Console)')) . '</span></a>',
    );
}

/**
 * A figure cell of a pg_visitors_table() row.
 *
 * @param string    $text
 * @param int|float $sort
 * @return array
 */
function pg_visitors_google_figure($text, $sort)
{
    return array('html' => h($text), 'sort' => (string) $sort);
}

/**
 * The section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_google(array $period, array $user)
{
    if (!pg_gsc_linked()) {
        return pg_visitors_google_unlinked();
    }

    $aside = '<a href="search_console.php">' . h(lang('Search Console')) . '</a>';
    $latest = pg_gsc_latest_day();

    if ($latest === '') {
        return pg_visitors_section('google', lang('Google Search'), pg_visitors_empty(lang('No figures yet. Google publishes them two or three days late; the daily job downloads them as they come.')), $aside);
    }

    $window = pg_visitors_google_window($period, $latest);
    $now = pg_gsc_totals($window['from'], $window['to']);

    if ($now['impressions'] <= 0) {
        return pg_visitors_section('google', lang('Google Search'), pg_visitors_empty(), $aside);
    }

    // Days never downloaded would count as zero, so the window before is
    // compared with only when the download reaches back to its start.
    $before = null;

    if ($period['compare'] && pg_gsc_comparable(pg_gsc_oldest_day(), $window['previous_from'])) {
        $before = pg_gsc_totals($window['previous_from'], $window['previous_to']);
    }

    $change = function ($key, $lower_is_better = false) use ($period, $now, $before) {
        if (!$period['compare']) {
            return '';
        }

        if (($key === 'position') && (!is_array($before) || ($before['impressions'] <= 0))) {
            return pg_visitors_change($now['position'], null);
        }

        return pg_visitors_change($now[$key], is_array($before) ? $before[$key] : null, $lower_is_better);
    };

    $tiles = '<div class="pg-visitors-tiles">'
        . pg_visitors_tile(lang('Clicks'), pg_format_number($now['clicks'], 0), $change('clicks'))
        . pg_visitors_tile(lang('Impressions'), pg_format_number($now['impressions'], 0), $change('impressions'))
        . pg_visitors_tile(lang('Click rate'), pg_visitors_google_percent($now['ctr']), $change('ctr'))
        . pg_visitors_tile(lang('Average position'), pg_format_number($now['position'], 1), $change('position', true))
        . '</div>';

    $range = function ($from, $to) {
        $text = prepare_form_data_for_output($from, 'date', false);

        return ($from === $to) ? $text : $text . ' – ' . prepare_form_data_for_output($to, 'date', false);
    };

    $note = $window['early']
        ? lang(array('string' => 'Google figures for {var:1}: Google publishes them two or three days late, so they end on the latest day it has published.', 'vars' => array($range($window['from'], $window['to']))))
        : lang(array('string' => 'Google figures for {var:1}.', 'vars' => array($range($window['from'], $window['to']))));

    if ($period['compare']) {
        $note .= ' ' . (is_array($before)
            ? lang(array('string' => 'Compared with {var:1}.', 'vars' => array($range($window['previous_from'], $window['previous_to']))))
            : lang('The days before were not downloaded, so there is nothing to compare with.'));
    }

    // Day labels follow the site's date order (month first or day first).
    $day_label_format = (DATE_FORMAT == 'month_day') ? 'm/d' : 'd.m';
    $labels = array();
    $clicks = array();
    $impressions = array();

    foreach (pg_gsc_daily($window['from'], $window['to']) as $day => $values) {
        $labels[] = date($day_label_format, strtotime($day . ' 12:00:00'));
        $clicks[] = $values['clicks'];
        $impressions[] = $values['impressions'];
    }

    $chart = pg_visitors_chart('google_chart', array(
        'labels'  => $labels,
        'series'  => array(
            array('label' => lang('Clicks'), 'data' => $clicks),
            array('label' => lang('Impressions'), 'data' => $impressions, 'axis' => 'right', 'dashed' => true),
        ),
        'caption' => lang('Clicks and impressions by day'),
    ));

    $settings = pg_gsc_settings();
    $property = $settings['property'];
    $site_url = pg_gsc_site_url();

    $figure_headings = array(
        array('label' => lang('Clicks'), 'end' => true),
        array('label' => lang('Impressions'), 'end' => true),
        array('label' => lang('Click rate'), 'end' => true),
        array('label' => lang('Avg. position'), 'end' => true),
    );

    $figures = function (array $row) {
        return array(
            pg_visitors_google_figure(pg_format_number($row['clicks'], 0), (int) $row['clicks']),
            pg_visitors_google_figure(pg_format_number($row['impressions'], 0), (int) $row['impressions']),
            pg_visitors_google_figure(pg_visitors_google_percent($row['ctr']), sprintf('%.4f', $row['ctr'])),
            pg_visitors_google_figure(pg_format_number($row['position'], 1), sprintf('%.2f', $row['position'])),
        );
    };

    $queries = pg_gsc_top('query', $window['from'], $window['to'], PG_VISITORS_GOOGLE_POOL);

    $query_rows = array();

    foreach (array_slice($queries, 0, PG_VISITORS_GOOGLE_TOP) as $row) {
        $query_rows[] = array_merge(array(pg_visitors_google_query_cell($row, $property, $window)), $figures($row));
    }

    $page_rows = array();

    foreach (pg_gsc_top('page', $window['from'], $window['to'], PG_VISITORS_GOOGLE_TOP) as $row) {
        $page_rows[] = array_merge(array(array(
            'sort' => (string) $row['name'],
            'html' => '<a href="' . h($row['name']) . '" target="_blank" rel="noopener" class="text-break">' . h(pg_gsc_page_label($row['name'], $site_url)) . '</a>',
        )), $figures($row));
    }

    $chances = pg_visitors_google_opportunities($queries, $now['ctr']);

    $chance_rows = function (array $rows) use ($property, $window, $figures) {
        $out = array();

        foreach ($rows as $row) {
            $out[] = array_merge(array(pg_visitors_google_query_cell($row, $property, $window)), $figures($row));
        }

        return $out;
    };

    $block = function ($title, $text, $table) {
        return pg_visitors_block($title, ($text !== '' ? '<p class="pg-visitors-google-hint">' . h($text) . '</p>' : '') . $table, 'pg-visitors-google-block');
    };

    $body = $tiles . '
        <p class="pg-visitors-google-note">' . h($note) . '</p>
        ' . $chart . '
        <div class="pg-visitors-google-grid">
            ' . $block(lang('Top searches'), '', pg_visitors_table(array_merge(array(lang('Search query')), $figure_headings), $query_rows, array('caption' => lang('Top searches'), 'id' => 'google_queries', 'limit' => PG_VISITORS_LIST_ROWS))) . '
            ' . $block(lang('Top pages'), '', pg_visitors_table(array_merge(array(lang('Page')), $figure_headings), $page_rows, array('caption' => lang('Top pages'), 'id' => 'google_pages', 'limit' => PG_VISITORS_LIST_ROWS))) . '
            ' . $block(lang('Shown often, rarely clicked'), lang('Searches shown more often than most whose click rate is under half the site\'s: a better title and description can win the click.'), pg_visitors_table(array_merge(array(lang('Search query')), $figure_headings), $chance_rows($chances['low_ctr']), array('caption' => lang('Shown often, rarely clicked'), 'id' => 'google_low_ctr', 'limit' => PG_VISITORS_LIST_ROWS))) . '
            ' . $block(lang('Close to the first page'), lang('Searches at an average position from 8 to 20: a little more work can move the page up.'), pg_visitors_table(array_merge(array(lang('Search query')), $figure_headings), $chance_rows($chances['near']), array('caption' => lang('Close to the first page'), 'id' => 'google_near', 'limit' => PG_VISITORS_LIST_ROWS))) . '
        </div>
        <p class="pg-visitors-google-hint">' . h(lang('Searches Google keeps anonymous for privacy are in the totals but not in the list of searches, so the list adds up to less.')) . '</p>';

    return pg_visitors_section('google', lang('Google Search'), $body, $aside);
}
