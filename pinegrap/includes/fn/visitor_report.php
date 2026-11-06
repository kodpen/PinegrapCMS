<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor report's filter columns, its paid / organic channel, the
 * check of a "day(s) ago" count, its period presets and its export columns
 * (view_visitor_report.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The SQL a filter field compares, or '' for an empty or unknown field: such
 * a filter adds nothing to the report, so a report saved with one still
 * opens. $from and $to bound the visits a date is read over in the site's
 * own time (pg_site_time_sql()).
 *
 * @param mixed    $field the filter's field
 * @param int|null $from
 * @param int|null $to
 * @return string
 */
function pg_visitor_report_filter_column($field, $from = null, $to = null)
{
    if (!is_string($field) || ($field === '')) {
        return '';
    }

    $columns = array(
        'site_search_terms', 'currency_code', 'referring_host_name', 'referring_search_engine',
        'referring_search_terms', 'first_visit', 'landing_page_name', 'tracking_code', 'affiliate_code',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'page_views', 'custom_form_submitted', 'custom_form_name', 'order_created', 'order_retrieved',
        'order_checked_out', 'order_completed', 'city', 'state', 'zip_code', 'country',
    );

    if (in_array($field, $columns, true)) {
        return 'visitors.' . $field;
    }

    switch ($field) {
        case 'date':
            return pg_site_time_sql('visitors.start_timestamp', '%Y-%m-%d', $from, $to);

        case 'http_referer':
            return pg_visitor_text_sql('http_referer');

        case 'pay_per_click_organic':
            return pg_visitor_report_channel_sql(true);

        // 0 once the general job took the address out after its period
        // (includes/fn/visitor_ip.php), or for an IPv6 visit: empty, as on
        // the order report, rather than 0.0.0.0.
        case 'ip_address':
            return 'CASE WHEN (visitors.ip_address = 0) THEN "" ELSE INET_NTOA(visitors.ip_address) END';
    }

    return '';
}

/**
 * The paid / organic channel of a visit by the daily summary's rule
 * (pg_visitor_paid_organic_sql(): with no flag set nothing is paid). For a filter
 * the values are the option values 'pay_per_click', 'organic', 'neither';
 * for a grouping the English names the screen translates, '' for neither.
 *
 * @param bool $filter
 * @return string
 */
function pg_visitor_report_channel_sql($filter)
{
    $channel = pg_visitor_paid_organic_sql('visitors.');

    if ($filter) {
        return "CASE " . $channel . " WHEN '' THEN 'neither' ELSE " . $channel . " END";
    }

    return "CASE " . $channel . " WHEN 'pay_per_click' THEN 'Pay Per Click' WHEN 'organic' THEN 'Organic' ELSE '' END";
}

/**
 * Whether a "day(s) ago" count can be stored: a whole number from 0 up to
 * what the INT UNSIGNED column holds; empty means today. Pure.
 *
 * @param mixed $text the submitted count
 * @return bool
 */
function pg_visitor_report_days_ago_valid($text)
{
    if (!is_scalar($text)) {
        return false;
    }

    $text = trim((string) $text);

    if ($text === '') {
        return true;
    }

    return ctype_digit($text) && (strlen($text) <= 10) && ((float) $text <= 4294967295);
}

/**
 * The period presets the report offers, in the order the bar shows them.
 * The keys are the overview's `p` values (pg_visitor_period()), so a link
 * from the overview opens the same days here. Pure.
 *
 * @return array
 */
function pg_visitor_report_preset_keys()
{
    return array('today', 'yesterday', '7d', '30d', '90d', 'month', 'last_month');
}

/**
 * The days a preset covers by the site's clock, as the start_* / stop_*
 * parts the report keeps; null for an unknown key. The last 7, 30 or 90
 * days end today, this month runs from its first day through today. Pure.
 *
 * @param mixed $key
 * @param int   $now timestamp
 * @return array|null
 */
function pg_visitor_report_preset_range($key, $now)
{
    if (!is_string($key) || !in_array($key, pg_visitor_report_preset_keys(), true)) {
        return null;
    }

    $now = (int) $now;
    $today = date('Y-m-d', $now);
    // Days are stepped from noon, so a daylight saving change never skips one.
    $shift = function ($date, $days) {
        return date('Y-m-d', strtotime($date . ' 12:00:00 ' . (($days < 0) ? '-' : '+') . abs($days) . ' days'));
    };

    switch ($key) {
        case 'today':
            $from = $to = $today;
            break;

        case 'yesterday':
            $from = $to = $shift($today, -1);
            break;

        case 'month':
            $from = substr($today, 0, 8) . '01';
            $to = $today;
            break;

        case 'last_month':
            $to = $shift(substr($today, 0, 8) . '01', -1);
            $from = substr($to, 0, 8) . '01';
            break;

        default:
            $to = $today;
            $from = $shift($today, 1 - (int) $key);
    }

    return pg_list_period_range_from_dates($from, $to);
}

/**
 * The range a request asks for: a preset (`p`), a custom range
 * (`p=custom` with `from` / `to` as Y-m-d; reversed ends are swapped, one
 * end is one day), or the start_* / stop_* parts of an address saved before
 * the presets. Null when it asks for none or for no real date, so the range
 * on screen stays. Pure.
 *
 * @param array $request $_GET
 * @param int   $now     timestamp
 * @return array|null
 */
function pg_visitor_report_requested_range($request, $now)
{
    $request = is_array($request) ? $request : array();
    $key = (isset($request['p']) && is_string($request['p'])) ? $request['p'] : '';

    if ($key === 'custom') {
        $dates = array();

        foreach (array('from', 'to') as $end) {
            $text = (isset($request[$end]) && is_string($request[$end])) ? $request[$end] : '';

            if ($text === '') {
                continue;
            }

            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $parts) || ((int) $parts[1] < 1970) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return null;
            }

            $dates[] = $text;
        }

        if (count($dates) === 0) {
            return null;
        }

        sort($dates);

        return pg_list_period_range_from_dates($dates[0], $dates[count($dates) - 1]);
    }

    if ($key !== '') {
        return pg_visitor_report_preset_range($key, $now);
    }

    if (!isset($request['start_month'])) {
        return null;
    }

    // A part sent as an array (?start_year[]=x) counts as not given: cast to
    // int it would read 1, a real year.
    $range = array();

    foreach (array('start_month', 'start_day', 'start_year', 'stop_month', 'stop_day', 'stop_year') as $part) {
        $range[$part] = (isset($request[$part]) && is_scalar($request[$part])) ? (int) $request[$part] : 0;
    }

    if (!checkdate($range['start_month'], $range['start_day'], $range['start_year'])
        || !checkdate($range['stop_month'], $range['stop_day'], $range['stop_year'])) {
        return null;
    }

    return pg_list_period_range_from_dates(
        sprintf('%04d-%02d-%02d', $range['start_year'], $range['start_month'], $range['start_day']),
        sprintf('%04d-%02d-%02d', $range['stop_year'], $range['stop_month'], $range['stop_day'])
    );
}

/**
 * The preset whose days a range covers, '' when none does. Pure.
 *
 * @param array $range start_* / stop_* parts
 * @param int   $now   timestamp
 * @return string
 */
function pg_visitor_report_active_preset($range, $now)
{
    // Read through the saved-address path, so unpadded parts compare as dates.
    $range = pg_visitor_report_requested_range(array_diff_key(is_array($range) ? $range : array(), array('p' => 1)), $now);

    foreach (pg_visitor_report_preset_keys() as $key) {
        if (($range !== null) && ($range === pg_visitor_report_preset_range($key, $now))) {
            return $key;
        }
    }

    return '';
}

/**
 * One visit's export columns: number, date, page views, form submitted,
 * then the order flags and total only with e-commerce on. Pure.
 *
 * @param array $row       in that order
 * @param bool  $ecommerce
 * @return array
 */
function pg_visitor_report_export_columns($row, $ecommerce)
{
    $columns = array_values($row);

    return $ecommerce ? $columns : array_slice($columns, 0, 4);
}

/**
 * One export line (RFC 4180, as spreadsheets read it), with its line break:
 * a cell holding a quote, comma, line break or edge space is quoted with its
 * quotes doubled. Written by hand rather than with fputcsv(), whose backslash
 * escape leaves the quote after a backslash undoubled: a visitor-set
 * referrer or utm_* value `x\",=1+1` then ended its cell early and put
 * `=1+1` unguarded in the next one. Pure.
 *
 * @param array $cells
 * @return string
 */
function pg_visitor_report_csv_line($cells)
{
    $quoted = array();

    foreach ($cells as $text) {
        $text = (string) $text;

        if (($text !== trim($text)) || (strpbrk($text, "\",\r\n") !== false)) {
            $text = '"' . str_replace('"', '""', $text) . '"';
        }

        $quoted[] = $text;
    }

    return implode(',', $quoted) . "\n";
}
