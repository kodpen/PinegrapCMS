<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pages section of the Visitors overview: one table per page joining
 * what Google says about it (impressions, clicks, click rate, position from
 * seo_search_daily) with what the site counted (visits as a landing page,
 * forms and orders of those visits, page views from the content rollup) and
 * the page's SEO score, from pg_visitor_pages()
 * (includes/fn/visitor_pages.php). Rows only one side knows stay in. While the site is
 * linked to Search Console every figure covers the Google window (the
 * period's length, ending on the latest day Google has published); else the
 * overview period, without the Google columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/visitor_pages.php');

/**
 * How many pages the table holds.
 */
const PG_VISITORS_PAGES_TOP = 25;

/**
 * How many of them show before its "Show all" button.
 */
const PG_VISITORS_PAGES_ROWS = 10;

/**
 * The column the table is sorted by: clicks (only when linked), visits or
 * score; anything else is clicks when linked, else visits. Pure.
 *
 * @param mixed $asked  the request's value
 * @param bool  $linked
 * @return string
 */
function pg_visitors_pages_sort_key($asked, $linked)
{
    if (($asked === 'visits') || ($asked === 'score')) {
        return $asked;
    }

    return $linked ? 'clicks' : 'visits';
}

/**
 * The SEO score cell: the number in its band's colour, linked to the page's
 * edit screen where its SEO panel is; a dash when there is no score.
 *
 * @param array $row pg_visitor_pages_join() row
 * @return array pg_visitors_table() cell
 */
function pg_visitors_pages_score_cell(array $row)
{
    if ($row['score'] === null) {
        $text = '<span class="fg-secondary">–</span>';
    } else {
        $color = pg_seo_score_color((int) $row['score']);
        $class = ($color['class'] !== '') ? ' fg-' . $color['class'] : '';
        $style = ($color['ink'] !== '') ? ' style="color:' . h($color['ink']) . '"' : '';
        $text = '<span class="fw-semibold' . $class . '"' . $style . '>' . (int) $row['score'] . '</span>';
    }

    if ($row['page_id'] > 0) {
        $text = '<a href="edit_page.php?id=' . (int) $row['page_id'] . '" class="text-decoration-none" title="' . h(lang('SEO Detail')) . '">' . $text . '</a>';
    }

    return array('html' => $text, 'sort' => ($row['score'] === null) ? -1 : (int) $row['score']);
}

/**
 * A figure cell, or a dash when the figure is missing.
 *
 * @param int|float|null $value
 * @param string         $text the figure as shown
 * @return array pg_visitors_table() cell
 */
function pg_visitors_pages_figure($value, $text)
{
    if ($value === null) {
        return array('html' => '–', 'sort' => -1);
    }

    return array('html' => h($text), 'sort' => (string) $value);
}

/**
 * The table.
 *
 * @param array  $rows      pg_visitor_pages_sort() rows, cut to the top
 * @param bool   $linked    with the Google columns
 * @param bool   $ecommerce with the orders column
 * @param string $base      the site's address, ending in '/'
 * @return string HTML
 */
function pg_visitors_pages_table(array $rows, $linked, $ecommerce, $base)
{
    $headings = array(lang('Page'));

    if ($linked) {
        $headings[] = array('label' => lang('Impressions'), 'end' => true);
        $headings[] = array('label' => lang('Clicks'), 'end' => true);
        $headings[] = array('label' => lang('Click rate'), 'end' => true);
        $headings[] = array('label' => lang('Avg. position'), 'end' => true);
    }

    $headings[] = array('label' => lang('Visits'), 'end' => true);
    $headings[] = array('label' => lang('Page views'), 'end' => true);
    $headings[] = array('label' => lang('Forms'), 'end' => true);

    if ($ecommerce) {
        $headings[] = array('label' => lang('Orders'), 'end' => true);
    }

    $headings[] = array('label' => lang('SEO Score'), 'end' => true);

    $body = array();

    foreach ($rows as $row) {
        $key = (string) $row['key'];
        $label = ($key === '') ? lang('Homepage') : '/' . $key;
        $url = $base . (($key === '') ? '' : encode_url_path($key));

        $cells = array(array(
            'html' => '<a href="' . h($url) . '" target="_blank" rel="noopener" class="text-break">' . h($label) . '</a>',
            'sort' => $key,
        ));

        if ($linked) {
            $cells[] = pg_visitors_pages_figure($row['impressions'], pg_format_number((int) $row['impressions'], 0));
            $cells[] = pg_visitors_pages_figure($row['clicks'], pg_format_number((int) $row['clicks'], 0));
            $cells[] = pg_visitors_pages_figure($row['ctr'], pg_format_number((float) $row['ctr'] * 100, 1) . ' %');
            $cells[] = pg_visitors_pages_figure($row['position'], pg_format_number((float) $row['position'], 1));
        }

        $cells[] = array('html' => h(pg_format_number($row['visits'], 0)), 'sort' => $row['visits']);
        $cells[] = array('html' => h(pg_format_number($row['page_views'], 0)), 'sort' => $row['page_views']);
        $cells[] = array('html' => h(pg_format_number($row['forms'], 0)), 'sort' => $row['forms']);

        if ($ecommerce) {
            $cells[] = array('html' => h(pg_format_number($row['orders'], 0)), 'sort' => $row['orders']);
        }

        $cells[] = pg_visitors_pages_score_cell($row);

        $body[] = $cells;
    }

    return pg_visitors_table($headings, $body, array('caption' => lang('Pages and Google'), 'id' => 'pages_table', 'limit' => PG_VISITORS_PAGES_ROWS));
}

/**
 * The sort switch: links that keep the period and change the column.
 *
 * @param array  $period pg_visitor_period()
 * @param string $by     the column now
 * @param bool   $linked
 * @return string HTML
 */
function pg_visitors_pages_sort_links(array $period, $by, $linked)
{
    $choices = array();

    if ($linked) {
        $choices['clicks'] = lang('Clicks');
    }

    $choices['visits'] = lang('Visits');
    $choices['score'] = lang('SEO Score');

    $links = '';

    foreach ($choices as $value => $label) {
        $href = 'visitors.php?' . pg_visitor_period_query($period) . '&pages_sort=' . rawurlencode($value) . '#pages';
        $active = ($value === $by);

        $links .= '<a class="btn btn-sm ' . ($active ? 'btn-solid theme-primary' : 'btn-outline theme-secondary') . '" href="' . h($href) . '"'
            . ($active ? ' aria-current="true"' : '') . '>' . h($label) . '</a>';
    }

    return '<div class="pg-visitors-pages-sort" role="group" aria-label="' . h(lang('Sort by')) . '">'
        . '<span class="pg-visitors-pages-sort-label">' . h(lang('Sort by')) . '</span>' . $links . '</div>';
}

/**
 * The section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_pages(array $period, array $user)
{
    if (!function_exists('pg_visitor_breakdown')) {
        return '';
    }

    $linked = pg_gsc_linked() && (pg_gsc_latest_day() !== '');
    $ecommerce = defined('ECOMMERCE') && ECOMMERCE;
    $site_url = pg_gsc_site_url();

    // Linked: the Google window, so a page's clicks and visits cover the
    // same days. Else the period itself, today up to now.
    if ($linked) {
        $window = pg_visitor_pages_window((string) $period['to'], (int) $period['days']);
        $from = $window['from'];
        $to = $window['to'];
        $answer = pg_visitor_pages($from, $to, true);
    } else {
        $from = (string) $period['from'];
        $to = (string) $period['to'];
        $answer = pg_visitor_pages((int) $period['from_ts'], (int) $period['to_ts'] - 1, false);
    }

    $rows = $answer['rows'];

    if (!$rows) {
        return pg_visitors_section('pages', lang('Pages and Google'), pg_visitors_empty());
    }

    $by = pg_visitors_pages_sort_key(isset($_GET['pages_sort']) ? $_GET['pages_sort'] : '', $linked);
    $top = array_slice(pg_visitor_pages_sort($rows, $by), 0, PG_VISITORS_PAGES_TOP, true);

    $range = prepare_form_data_for_output($from, 'date', false)
        . (($from === $to) ? '' : ' – ' . prepare_form_data_for_output($to, 'date', false));

    $note = $linked
        ? lang(array('string' => 'Every figure covers {var:1}, the days Google has published.', 'vars' => array($range)))
        : lang('The site is not linked to Search Console, so the Google columns are left out.');

    $base = rtrim($site_url !== '' ? $site_url : ((defined('URL_SCHEME') ? URL_SCHEME : '') . (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '') . (defined('PATH') ? PATH : '/')), '/') . '/';

    $body = pg_visitors_pages_sort_links($period, $by, $linked)
        . pg_visitors_pages_table($top, $linked, $ecommerce, $base)
        . '<p class="pg-visitors-pages-note">' . h($note)
        . (!$linked ? ' <a href="search_console.php">' . h(lang('Set up Search Console')) . '</a>' : '')
        . '</p>';

    return pg_visitors_section('pages', lang('Pages and Google'), $body);
}
