<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor report's filter columns, its paid / organic channel and the
 * check of a "day(s) ago" count (view_visitor_report.php).
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
 * (pg_visitor_channel_sql(): with no flag set nothing is paid). For a filter
 * the values are the option values 'pay_per_click', 'organic', 'neither';
 * for a grouping the English names the screen translates, '' for neither.
 *
 * @param bool $filter
 * @return string
 */
function pg_visitor_report_channel_sql($filter)
{
    $channel = pg_visitor_channel_sql('visitors.');

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
