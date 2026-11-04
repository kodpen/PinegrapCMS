<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pages section of the Visitors overview: one table per page joining
 * what Google says about it (impressions, clicks, click rate, position from
 * seo_search_daily) with what the site counted (visits as a landing page,
 * forms and orders of those visits, page views from the content rollup) and
 * the page's SEO score. A Google address and a site page meet on
 * pg_visitor_page_key(). Rows only one side knows stay in. While the site is
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');
require_once(PG_FUNCTIONS_DIR . '/seo.php');

/**
 * How many pages the table shows.
 */
const PG_VISITORS_PAGES_TOP = 25;

/**
 * How deep each source is read before the join, so a page in the top of one
 * side finds its figures on the other.
 */
const PG_VISITORS_PAGES_DEPTH = 1000;

/**
 * The key a page is known by on both sides: the path under the site root
 * without scheme, host, query, fragment, slashes at either end or an .html
 * ending, decoded and lower-cased. The root, its aliases (index.php, a bare
 * host) and every name in $home_names are ''. A Google address is first
 * cut to its path under the property with pg_gsc_page_label(). Pure.
 *
 * @param string $address    a page name, a path or an address
 * @param array  $home_names the names of the pages marked as home
 * @return string
 */
function pg_visitor_page_key($address, array $home_names = array())
{
    $key = trim((string) $address);

    // A scheme and host, or a protocol-relative host, is cut down to the
    // path.
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//[^/]*(.*)$#is', $key, $match)) {
        $key = $match[1];
    }

    $key = preg_replace('/[?#].*$/s', '', $key);
    $key = trim(rawurldecode($key));
    $key = trim($key, '/');
    $key = preg_replace('/\.html?$/i', '', $key);
    $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);

    // The root's aliases, as pg_home_page_group_expression() lists them;
    // 'example.com/' is a value some installs recorded for the root.
    if (in_array($key, array('', 'index.php', 'index', 'example.com'), true)) {
        return '';
    }

    foreach ($home_names as $name) {
        $name = trim((string) $name, " /\t\n\r");
        $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

        if (($name !== '') && ($key === $name)) {
            return '';
        }
    }

    return $key;
}

/**
 * The rows of the table, one per page key, in the order the keys first
 * appear: Google pages, then landing pages, then viewed pages. Pure.
 *
 *   key, clicks, impressions, ctr, position (null without a Google row),
 *   visits, forms, orders (of the visits that began on the page),
 *   page_views, page_id (0 when no site page has the key), score (null
 *   when the page was never scored)
 *
 * @param array  $google     pg_gsc_top('page') rows: name, clicks, impressions, position
 * @param array  $landing    pg_visitor_breakdown('landing_page_name') rows
 * @param array  $views      array('page_name', 'views') rows of the content rollup
 * @param array  $pages      array('page_id', 'page_name', 'page_home', 'seo_score', 'scored') rows
 * @param string $site_url   pg_gsc_site_url()
 * @param array  $home_names the names of the pages marked as home
 * @return array key => row
 */
function pg_visitors_pages_join(array $google, array $landing, array $views, array $pages, $site_url, array $home_names)
{
    $rows = array();

    $row = function ($key) use (&$rows) {
        if (!isset($rows[$key])) {
            $rows[$key] = array(
                'key' => (string) $key, 'clicks' => null, 'impressions' => null, 'ctr' => null, 'position' => null,
                'weighted' => 0.0, 'visits' => 0, 'forms' => 0, 'orders' => 0, 'page_views' => 0,
                'page_id' => 0, 'score' => null,
            );
        }
    };

    foreach ($google as $item) {
        $key = pg_visitor_page_key(pg_gsc_page_label((string) $item['name'], $site_url), $home_names);
        $row($key);
        $impressions = (int) $item['impressions'];
        $rows[$key]['clicks'] = (int) $rows[$key]['clicks'] + (int) $item['clicks'];
        $rows[$key]['impressions'] = (int) $rows[$key]['impressions'] + $impressions;
        $rows[$key]['weighted'] += (float) $item['position'] * $impressions;
    }

    foreach ($landing as $item) {
        $key = pg_visitor_page_key((string) $item['value'], $home_names);
        $row($key);
        $rows[$key]['visits'] += isset($item['visits']) ? (int) $item['visits'] : 0;
        $rows[$key]['forms'] += isset($item['forms_submitted']) ? (int) $item['forms_submitted'] : 0;
        $rows[$key]['orders'] += isset($item['orders_completed']) ? (int) $item['orders_completed'] : 0;
    }

    foreach ($views as $item) {
        $key = pg_visitor_page_key((string) $item['page_name'], $home_names);
        $row($key);
        $rows[$key]['page_views'] += (int) $item['views'];
    }

    // A page joins the rows the figures made; a page without figures adds none.
    foreach ($pages as $page) {
        $key = ((string) $page['page_home'] === 'yes') ? '' : pg_visitor_page_key((string) $page['page_name'], $home_names);

        if (!isset($rows[$key]) || ($rows[$key]['page_id'] && ($key === ''))) {
            continue;
        }

        $rows[$key]['page_id'] = (int) $page['page_id'];
        $rows[$key]['score'] = !empty($page['scored']) ? (int) $page['seo_score'] : null;
    }

    foreach ($rows as $key => $item) {
        if ($item['impressions'] !== null) {
            $rows[$key]['ctr'] = pg_gsc_ctr($item['clicks'], $item['impressions']);
            $rows[$key]['position'] = ($item['impressions'] > 0) ? ($item['weighted'] / $item['impressions']) : 0.0;
        }

        unset($rows[$key]['weighted']);
    }

    return $rows;
}

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
 * The rows largest first by one column, a row without the figure last,
 * then by visits and by key. Pure.
 *
 * @param array  $rows pg_visitors_pages_join()
 * @param string $by   clicks, visits or score
 * @return array
 */
function pg_visitors_pages_sort(array $rows, $by)
{
    uasort($rows, function ($a, $b) use ($by) {
        $x = $a[$by];
        $y = $b[$by];

        if (($x === null) !== ($y === null)) {
            return ($x === null) ? 1 : -1;
        }

        if ($x !== $y) {
            return ($x > $y) ? -1 : 1;
        }

        if ($a['visits'] !== $b['visits']) {
            return ($a['visits'] > $b['visits']) ? -1 : 1;
        }

        return strcmp((string) $a['key'], (string) $b['key']);
    });

    return $rows;
}

/**
 * The names of the pages marked as home.
 *
 * @return array
 */
function pg_visitors_pages_home_names()
{
    $names = array();

    foreach ((array) db_items("SELECT page_name FROM page WHERE page_home = 'yes'") as $row) {
        $names[] = (string) $row['page_name'];
    }

    return $names;
}

/**
 * Page views of a window by recorded page name, from the content rollup.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @return array list of array('page_name', 'views')
 */
function pg_visitors_pages_views($first, $last)
{
    if (!function_exists('pg_visitor_rollup_ready') || !pg_visitor_rollup_ready()) {
        return array();
    }

    $rows = db_items("SELECT page_name, SUM(views) AS views
        FROM visitor_content_hourly
        WHERE stat_date >= '" . e($first) . "' AND stat_date <= '" . e($last) . "'
        GROUP BY page_name
        ORDER BY views DESC
        LIMIT " . PG_VISITORS_PAGES_DEPTH);

    return is_array($rows) ? $rows : array();
}

/**
 * The site pages whose names are among the keys, and the home pages, with
 * their SEO scores.
 *
 * @param array $keys page keys
 * @return array list of array('page_id', 'page_name', 'page_home', 'seo_score', 'scored')
 */
function pg_visitors_pages_records(array $keys)
{
    $names = array();

    foreach ($keys as $key) {
        if (($key !== '') && (strpos((string) $key, '/') === false)) {
            $names[] = "'" . e((string) $key) . "'";
        }
    }

    $checked = pg_seo_schema_ready() ? 'seo_checked_at' : "'0' AS seo_checked_at";

    $rows = db_items("SELECT page_id, page_name, page_home, seo_score, " . $checked . ", seo_analysis_current
        FROM page
        WHERE (page_home = 'yes')" . ($names ? " OR (page_name IN (" . implode(', ', array_slice($names, 0, PG_VISITORS_PAGES_DEPTH)) . "))" : '') . "
        ORDER BY page_id");

    $out = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        // A row that predates seo_checked_at falls back to the current flag.
        if (!pg_seo_schema_ready()) {
            unset($row['seo_checked_at']);
        }

        $row['scored'] = pg_seo_row_scored($row);
        $out[] = $row;
    }

    return $out;
}

/**
 * The SEO score cell: the number in its band's colour, linked to the page's
 * edit screen where its SEO panel is; a dash when there is no score.
 *
 * @param array $row pg_visitors_pages_join() row
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
 * @param array  $rows      pg_visitors_pages_sort() rows, cut to the top
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

    return pg_visitors_table($headings, $body, array('caption' => lang('Pages and Google'), 'id' => 'pages_table'));
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

    $linked = pg_gsc_linked();
    $latest = $linked ? pg_gsc_latest_day() : '';
    $linked = $linked && ($latest !== '');
    $ecommerce = defined('ECOMMERCE') && ECOMMERCE;
    $site_url = pg_gsc_site_url();

    // Linked: the Google window, so a page's clicks and visits cover the
    // same days. Else the period itself.
    if ($linked) {
        $end = ($latest < (string) $period['to']) ? $latest : (string) $period['to'];
        $window = pg_gsc_period($end, max(1, (int) $period['days']));
        $from = $window['from'];
        $to = $window['to'];
        $landing = pg_visitor_breakdown('landing_page_name', $from, $to, PG_VISITORS_PAGES_DEPTH);
        $google = pg_gsc_top('page', $from, $to, PG_VISITORS_PAGES_DEPTH);
    } else {
        $from = (string) $period['from'];
        $to = (string) $period['to'];
        $landing = pg_visitor_breakdown('landing_page_name', (int) $period['from_ts'], (int) $period['to_ts'] - 1, PG_VISITORS_PAGES_DEPTH);
        $google = array();
    }

    $home_names = pg_visitors_pages_home_names();
    $landing_rows = is_array($landing) ? $landing['rows'] : array();
    $views = pg_visitors_pages_views($from, $to);
    $rows = pg_visitors_pages_join($google, $landing_rows, $views, array(), $site_url, $home_names);

    if (!$rows) {
        return pg_visitors_section('pages', lang('Pages and Google'), pg_visitors_empty());
    }

    $by = pg_visitors_pages_sort_key(isset($_GET['pages_sort']) ? $_GET['pages_sort'] : '', $linked);

    // The scores are read for the pages the figures name, then joined in so
    // the table can be sorted on them.
    $rows = pg_visitors_pages_join($google, $landing_rows, $views, pg_visitors_pages_records(array_keys($rows)), $site_url, $home_names);

    $top = array_slice(pg_visitors_pages_sort($rows, $by), 0, PG_VISITORS_PAGES_TOP, true);

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
