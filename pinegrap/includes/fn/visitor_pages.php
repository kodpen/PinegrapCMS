<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pages joined with Google: for each page of the site, what Google says
 * about it (impressions, clicks, click rate, position from
 * seo_search_daily) beside what the site counted (visits that began on it,
 * the forms and orders of those visits, page views from the content rollup)
 * and its SEO score. A Google address and a site page meet on
 * pg_visitor_page_key(). Read by the pages section of the Visitors overview
 * and by GET /reports/pages; loaded by them, not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');
require_once(PG_FUNCTIONS_DIR . '/seo.php');

/**
 * How deep each source is read before the join, so a page in the top of one
 * side finds its figures on the other.
 */
const PG_VISITOR_PAGES_DEPTH = 1000;

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
function pg_visitor_pages_join(array $google, array $landing, array $views, array $pages, $site_url, array $home_names)
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
 * The rows largest first by one column, a row without the figure last,
 * then by visits and by key. Pure.
 *
 * @param array  $rows pg_visitor_pages_join()
 * @param string $by   clicks, visits or score
 * @return array
 */
function pg_visitor_pages_sort(array $rows, $by)
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
function pg_visitor_pages_home_names()
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
function pg_visitor_pages_views($first, $last)
{
    if (!function_exists('pg_visitor_rollup_ready') || !pg_visitor_rollup_ready()) {
        return array();
    }

    $rows = db_items("SELECT page_name, SUM(views) AS views
        FROM visitor_content_hourly
        WHERE stat_date >= '" . e($first) . "' AND stat_date <= '" . e($last) . "'
        GROUP BY page_name
        ORDER BY views DESC
        LIMIT " . PG_VISITOR_PAGES_DEPTH);

    return is_array($rows) ? $rows : array();
}

/**
 * The site pages whose names are among the keys, and the home pages, with
 * their SEO scores.
 *
 * @param array $keys page keys
 * @return array list of array('page_id', 'page_name', 'page_home', 'seo_score', 'scored')
 */
function pg_visitor_pages_records(array $keys)
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
        WHERE (page_home = 'yes')" . ($names ? " OR (page_name IN (" . implode(', ', array_slice($names, 0, PG_VISITOR_PAGES_DEPTH)) . "))" : '') . "
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
 * The Google window for a period: its number of days, ending on its last
 * day or on the latest day Google has published, whichever comes first.
 *
 * @param string $to   Y-m-d, the period's last day
 * @param int    $days the period's length
 * @return array from, to (Y-m-d) and the rest of pg_gsc_period()
 */
function pg_visitor_pages_window($to, $days)
{
    $latest = pg_gsc_latest_day();
    $end = (($latest !== '') && ($latest < (string) $to)) ? $latest : (string) $to;

    return pg_gsc_period($end, max(1, (int) $days));
}

/**
 * The pages of a window, joined, with their scores.
 *
 * @param string|int $from   Y-m-d or first moment
 * @param string|int $to     Y-m-d or last moment counted
 * @param bool       $google with Google's figures (the site is linked)
 * @return array 'rows' => pg_visitor_pages_join() rows, 'summary_days',
 *               'raw_days', 'summary_to' of the visit figures
 */
function pg_visitor_pages($from, $to, $google)
{
    $first = is_int($from) ? date('Y-m-d', $from) : (string) $from;
    $last = is_int($to) ? date('Y-m-d', $to) : (string) $to;

    $landing = pg_visitor_breakdown('landing_page_name', $from, $to, PG_VISITOR_PAGES_DEPTH);
    $landing_rows = is_array($landing) ? $landing['rows'] : array();
    $google_rows = $google ? pg_gsc_top('page', $first, $last, PG_VISITOR_PAGES_DEPTH) : array();
    $views = pg_visitor_pages_views($first, $last);
    $site_url = pg_gsc_site_url();
    $home_names = pg_visitor_pages_home_names();

    $rows = pg_visitor_pages_join($google_rows, $landing_rows, $views, array(), $site_url, $home_names);

    // The scores are read for the pages the figures name.
    if ($rows) {
        $rows = pg_visitor_pages_join($google_rows, $landing_rows, $views, pg_visitor_pages_records(array_keys($rows)), $site_url, $home_names);
    }

    return array(
        'rows'         => $rows,
        'summary_days' => is_array($landing) ? (int) $landing['summary_days'] : 0,
        'raw_days'     => is_array($landing) ? (int) $landing['raw_days'] : 0,
        'summary_to'   => is_array($landing) ? $landing['summary_to'] : null,
    );
}
