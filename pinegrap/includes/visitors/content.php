<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The content section of the Visitors overview: the most viewed pages,
 * products, product groups and form entries from the content rollup
 * (visitor_content_hourly), labelled and linked as the dashboard's content
 * list does (pg_visitor_label_content_rows()), and the landing pages with
 * their visits, forms and orders from pg_visitor_breakdown(), every name of
 * the home page folded into one row. Each row carries the earlier period's
 * figure.
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
 * How many rows a list shows.
 */
const PG_VISITORS_CONTENT_TOP = 10;

/**
 * How deep an earlier list, and a list that is folded before it is cut, is
 * read, so a row new in the top still finds its earlier figure.
 */
const PG_VISITORS_CONTENT_DEPTH = 200;

/**
 * The content lists: the rollup's item type ('page' for a row without an
 * item) and the list's heading. Products and groups only with commerce on.
 *
 * @param bool $ecommerce
 * @return array type => label
 */
function pg_visitors_content_kinds($ecommerce)
{
    $kinds = array('page' => lang('Pages'));

    if ($ecommerce) {
        $kinds['product'] = lang('Products');
        $kinds['product_group'] = lang('Product groups');
    }

    $kinds['form'] = lang('Form entries');

    return $kinds;
}

/**
 * The window of a rollup read: first day, last day and the last hour of the
 * last day counted, from a first moment and the moment after the last one,
 * by the site's clock. Pure.
 *
 * @param int $from_ts
 * @param int $to_ts excluded
 * @return array array('first' => Y-m-d, 'last' => Y-m-d, 'last_hour' => 0-23)
 */
function pg_visitors_content_window($from_ts, $to_ts)
{
    $end = max((int) $from_ts, (int) $to_ts - 1);

    return array(
        'first'     => date('Y-m-d', (int) $from_ts),
        'last'      => date('Y-m-d', $end),
        'last_hour' => (int) date('G', $end),
    );
}

/**
 * The most viewed content of one kind in a window, largest first. A page is
 * one row per page (by its id, by its recorded name when it has none); an
 * item is one row per item, whichever page showed it.
 *
 * @param string $kind   a key of pg_visitors_content_kinds()
 * @param array  $window pg_visitors_content_window()
 * @param int    $limit
 * @return array list of array('page_id', 'page_name', 'item_type', 'item_id', 'views')
 */
function pg_visitors_content_read($kind, array $window, $limit)
{
    if (!function_exists('pg_visitor_rollup_ready') || !pg_visitor_rollup_ready()) {
        return array();
    }

    if ($kind === 'page') {
        $where = "item_type = ''";
        $group = "page_id, CASE WHEN page_id > 0 THEN '' ELSE page_name END";
        $item = "'' AS item_type, 0 AS item_id";
    } else {
        $where = "item_type = '" . e($kind) . "' AND item_id > 0";
        $group = "item_id";
        $item = "'" . e($kind) . "' AS item_type, item_id";
    }

    $result = db_q(
        "SELECT MAX(page_id) AS page_id, MAX(page_name) AS page_name, " . $item . ", SUM(views) AS views
         FROM visitor_content_hourly
         WHERE " . $where . "
           AND stat_date >= ?
           AND (stat_date < ? OR (stat_date = ? AND stat_hour <= ?))
         GROUP BY " . $group . "
         ORDER BY views DESC, page_id ASC, item_id ASC
         LIMIT ?",
        array((string) $window['first'], (string) $window['last'], (string) $window['last'], (int) $window['last_hour'], max(1, (int) $limit))
    );

    $rows = array();

    if ($result) {
        while ($row = @mysqli_fetch_assoc($result)) {
            $row['views'] = (int) $row['views'];
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * What one content row is, to find it in the other period: the item, else
 * the page id, else the recorded page name. Pure.
 *
 * @param array $row
 * @return string
 */
function pg_visitors_content_key(array $row)
{
    $type = isset($row['item_type']) ? (string) $row['item_type'] : '';
    $id = isset($row['item_id']) ? (int) $row['item_id'] : 0;

    if (($type !== '') && ($id > 0)) {
        return $type . ':' . $id;
    }

    $page_id = isset($row['page_id']) ? (int) $row['page_id'] : 0;

    if ($page_id > 0) {
        return 'page:' . $page_id;
    }

    $name = isset($row['page_name']) ? trim((string) $row['page_name']) : '';

    return 'name:' . (function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name));
}

/**
 * The rows of a content list with the earlier period's views beside each
 * one, cut to the limit. Pure.
 *
 * @param array      $rows        labelled rows now (label, url, views, ...)
 * @param array|null $before_rows the earlier period's rows, null when not compared
 * @param int        $limit
 * @return array list of array('label', 'url', 'views', 'before' => int|null)
 */
function pg_visitors_content_rows(array $rows, $before_rows, $limit)
{
    $before = null;

    if (is_array($before_rows)) {
        $before = array();

        foreach ($before_rows as $row) {
            $key = pg_visitors_content_key($row);
            $before[$key] = (isset($before[$key]) ? $before[$key] : 0) + (int) $row['views'];
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $key = pg_visitors_content_key($row);

        $out[] = array(
            'label'  => isset($row['label']) ? (string) $row['label'] : '',
            'url'    => isset($row['url']) ? (string) $row['url'] : '',
            'views'  => (int) $row['views'],
            'before' => is_array($before) ? (isset($before[$key]) ? $before[$key] : 0) : null,
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The names the home page is recorded under, lower-cased: the root's
 * aliases and the name of every page marked as home.
 *
 * @return array
 */
function pg_visitors_content_home_names()
{
    $names = array('', '/', 'index.php', 'example.com/');
    $rows = db_items("SELECT page_name FROM page WHERE page_home = 'yes'");

    foreach (is_array($rows) ? $rows : array() as $row) {
        $names[] = (string) $row['page_name'];
    }

    return pg_visitors_content_lower_names($names);
}

/**
 * Names trimmed and lower-cased, without repeats. Pure.
 *
 * @param array $names
 * @return array
 */
function pg_visitors_content_lower_names(array $names)
{
    $out = array();

    foreach ($names as $name) {
        $name = trim((string) $name);
        $out[] = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    return array_values(array_unique($out));
}

/**
 * Landing page rows added up by page: every home name is one row with the
 * value '', the rest meet without regard to case, the first spelling kept;
 * largest first, then by name. Pure.
 *
 * @param array $rows       pg_visitor_breakdown('landing_page_name') rows
 * @param array $home_names pg_visitors_content_home_names()
 * @return array lower-cased value => array('value', 'visits', 'forms', 'orders')
 */
function pg_visitors_content_fold_landing(array $rows, array $home_names)
{
    $out = array();

    foreach ($rows as $row) {
        $value = trim((string) $row['value']);
        $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        if (in_array($key, $home_names, true)) {
            $key = '';
            $value = '';
        }

        if (!isset($out[$key])) {
            $out[$key] = array('value' => $value, 'visits' => 0, 'forms' => 0, 'orders' => 0);
        }

        $out[$key]['visits'] += isset($row['visits']) ? (int) $row['visits'] : 0;
        $out[$key]['forms'] += isset($row['forms_submitted']) ? (int) $row['forms_submitted'] : 0;
        $out[$key]['orders'] += isset($row['orders_completed']) ? (int) $row['orders_completed'] : 0;
    }

    uasort($out, function ($a, $b) {
        if ($a['visits'] !== $b['visits']) {
            return ($a['visits'] > $b['visits']) ? -1 : 1;
        }

        return strcmp($a['value'], $b['value']);
    });

    return $out;
}

/**
 * The landing page list: the home page folded, the earlier period's visits
 * beside each page, cut to the limit. Pure.
 *
 * @param array      $rows        pg_visitor_breakdown('landing_page_name') rows now
 * @param array|null $before_rows the earlier period's rows, null when not compared
 * @param array      $home_names  pg_visitors_content_home_names()
 * @param int        $limit
 * @return array list of array('value', 'home' => bool, 'visits', 'forms',
 *               'orders', 'before' => int|null)
 */
function pg_visitors_content_landing_rows(array $rows, $before_rows, array $home_names, $limit)
{
    $before = is_array($before_rows) ? pg_visitors_content_fold_landing($before_rows, $home_names) : null;
    $out = array();

    foreach (pg_visitors_content_fold_landing($rows, $home_names) as $key => $row) {
        if ($row['visits'] <= 0) {
            continue;
        }

        $key = (string) $key;

        $out[] = $row + array(
            'home'   => ($key === ''),
            'before' => is_array($before) ? (isset($before[$key]) ? $before[$key]['visits'] : 0) : null,
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The address of a landing page on the site.
 *
 * @param string $value the recorded name, '' for the home page
 * @return string
 */
function pg_visitors_content_landing_url($value)
{
    $base = (defined('URL_SCHEME') ? URL_SCHEME : '')
        . (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '')
        . (defined('PATH') ? PATH : '/');

    return $base . (($value !== '') ? encode_url_path(ltrim($value, '/')) : '');
}

/**
 * A linked name cell: the text, a link to the address when there is one.
 *
 * @param string $label
 * @param string $url
 * @return array pg_visitors_table() cell
 */
function pg_visitors_content_name_cell($label, $url)
{
    $text = '<span class="text-break">' . h($label) . '</span>';

    if ($url !== '') {
        $text = '<a href="' . h($url) . '" target="_blank" rel="noopener">' . $text . '</a>';
    }

    return array('html' => $text);
}

/**
 * One content list as a small table: the name, views and, when compared,
 * the change (the earlier views on hover). The first rows show; the rest
 * wait behind "Show all".
 *
 * @param string $title
 * @param array  $rows    pg_visitors_content_rows()
 * @param bool   $compare
 * @return string HTML
 */
function pg_visitors_content_table($title, array $rows, $compare)
{
    $headings = array(lang('Item'), array('label' => lang('Views'), 'end' => true));

    if ($compare) {
        $headings[] = pg_visitors_change_heading();
    }

    $body = array();

    foreach ($rows as $row) {
        $cells = array(
            pg_visitors_content_name_cell($row['label'], $row['url']),
            array('html' => h(pg_format_number($row['views'], 0)), 'sort' => $row['views']),
        );

        if ($compare) {
            $cells[] = pg_visitors_change_cell($row['views'], (int) $row['before']);
        }

        $body[] = $cells;
    }

    return pg_visitors_block($title, pg_visitors_table($headings, $body, array(
        'caption'    => $title,
        'limit'      => PG_VISITORS_LIST_ROWS,
        'hide_first' => true,
    )), 'pg-visitors-content-list');
}

/**
 * The landing page table: the page, visits, forms, orders with commerce on
 * and, when compared, the change (the earlier visits on hover).
 *
 * @param array $rows      pg_visitors_content_landing_rows()
 * @param bool  $compare
 * @param bool  $ecommerce
 * @return string HTML
 */
function pg_visitors_content_landing_table(array $rows, $compare, $ecommerce)
{
    $title = lang('Landing pages');
    $headings = array(lang('Page'), array('label' => lang('Visits'), 'end' => true), array('label' => lang('Forms'), 'end' => true));

    if ($ecommerce) {
        $headings[] = array('label' => lang('Orders'), 'end' => true);
    }

    if ($compare) {
        $headings[] = pg_visitors_change_heading();
    }

    $body = array();

    foreach ($rows as $row) {
        $label = $row['home'] ? lang('Homepage') : $row['value'];

        $cells = array(
            pg_visitors_content_name_cell($label, pg_visitors_content_landing_url($row['value'])),
            array('html' => h(pg_format_number($row['visits'], 0)), 'sort' => $row['visits']),
            array('html' => h(pg_format_number($row['forms'], 0)), 'sort' => $row['forms']),
        );

        if ($ecommerce) {
            $cells[] = array('html' => h(pg_format_number($row['orders'], 0)), 'sort' => $row['orders']);
        }

        if ($compare) {
            $cells[] = pg_visitors_change_cell($row['visits'], (int) $row['before']);
        }

        $body[] = $cells;
    }

    return pg_visitors_block($title, pg_visitors_table($headings, $body, array(
        'caption' => $title,
        'limit'   => PG_VISITORS_LIST_ROWS,
    )), 'pg-visitors-content-list pg-visitors-content-landing');
}

/**
 * The content section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_content(array $period, array $user)
{
    if (!function_exists('pg_visitor_breakdown') || !function_exists('pg_visitor_label_content_rows')) {
        return '';
    }

    $compare = !empty($period['compare']);
    $ecommerce = defined('ECOMMERCE') && ECOMMERCE;

    $window = pg_visitors_content_window((int) $period['from_ts'], (int) $period['to_ts']);
    $previous = pg_visitors_content_window((int) $period['previous_from_ts'], (int) $period['previous_to_ts']);

    $lists = '';

    foreach (pg_visitors_content_kinds($ecommerce) as $kind => $title) {
        $rows = pg_visitor_label_content_rows(pg_visitors_content_read($kind, $window, PG_VISITORS_CONTENT_TOP));

        // A kind with no view in the period is left out, so a site without
        // a catalog or form items shows no empty tables for them.
        if (!$rows) {
            continue;
        }

        $rows = pg_visitors_content_rows(
            $rows,
            $compare ? pg_visitors_content_read($kind, $previous, PG_VISITORS_CONTENT_DEPTH) : null,
            PG_VISITORS_CONTENT_TOP
        );

        $lists .= pg_visitors_content_table($title, $rows, $compare);
    }

    // The breakdown's last moment is counted; the period's to_ts is not.
    $landing = pg_visitor_breakdown('landing_page_name', (int) $period['from_ts'], (int) $period['to_ts'] - 1, PG_VISITORS_CONTENT_DEPTH);
    $landing_before = $compare
        ? pg_visitor_breakdown('landing_page_name', (int) $period['previous_from_ts'], (int) $period['previous_to_ts'] - 1, PG_VISITORS_CONTENT_DEPTH)
        : null;

    $landing_rows = pg_visitors_content_landing_rows(
        is_array($landing) ? $landing['rows'] : array(),
        $compare ? (is_array($landing_before) ? $landing_before['rows'] : array()) : null,
        pg_visitors_content_home_names(),
        PG_VISITORS_CONTENT_TOP
    );

    if (($lists === '') && !$landing_rows) {
        return pg_visitors_section('content', lang('Content'), pg_visitors_empty());
    }

    // The landing pages under the lists, not in their grid: a list alone
    // then takes the whole width.
    $body = (($lists !== '') ? '<div class="pg-visitors-content-lists">' . $lists . '</div>' : '')
        . pg_visitors_content_landing_table($landing_rows, $compare, $ecommerce);

    return pg_visitors_section('content', lang('Content'), $body);
}
