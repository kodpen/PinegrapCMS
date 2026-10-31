<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Analytics 4 figures for the dashboard: the GA4 Data API report of
 * the last 28 days against the 28 before, read on a schedule and kept for the
 * Google Analytics card (includes/dashboard/widgets/widget_32.php).
 *
 * A site Google account connected for the google_analytics purpose
 * (includes/connect/purposes/google_analytics.php, analytics.readonly) picks
 * one GA4 property from the Admin API's account summaries on Settings › SEO ›
 * Visitor Tracking (includes/settings/seo_analytics.php).
 *
 * Nothing has a table or a column of its own: the chosen property and the
 * last report are one sealed value in connect_accounts as the service entry
 * "google_analytics" (pg_connect_service_get/put()), typed by
 * pg_ga_state_parse(). Without the encryption key nothing can be connected,
 * so nothing is stored either.
 *
 * Google is called by google_analytics_job.php once the report is older than
 * PG_GA_TTL, and behind the settings buttons; never while the dashboard is
 * drawn. The pure parts are tested in tests/google_analytics_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

if (!defined('PG_GA_DATA_API')) {
    define('PG_GA_DATA_API', 'https://analyticsdata.googleapis.com/v1beta');
}

if (!defined('PG_GA_ADMIN_API')) {
    define('PG_GA_ADMIN_API', 'https://analyticsadmin.googleapis.com/v1beta');
}

// How long a report is kept before the job reads it again. GA4 settles a
// day's figures over several hours, so a few readings a day are enough.
if (!defined('PG_GA_TTL')) {
    define('PG_GA_TTL', 21600);
}

// Rows kept for the top pages and the top sources.
if (!defined('PG_GA_TOP_ROWS')) {
    define('PG_GA_TOP_ROWS', 5);
}

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

// The metrics of the totals report, in the order the card shows them.
function pg_ga_metrics()
{
    return array('activeUsers', 'sessions', 'screenPageViews', 'keyEvents', 'engagementRate');
}

// The reported days: $days up to yesterday, and the same number before them.
// Dates are counted at noon so a daylight saving change never skips a day.
function pg_ga_period($today, $days = 28)
{
    $days = max(1, (int) $days);
    $end = strtotime((string) $today . ' 12:00:00') - 86400;

    return array(
        'from'          => date('Y-m-d', strtotime(date('Y-m-d', $end) . ' 12:00:00 -' . ($days - 1) . ' days')),
        'to'            => date('Y-m-d', $end),
        'previous_from' => date('Y-m-d', strtotime(date('Y-m-d', $end) . ' 12:00:00 -' . ((2 * $days) - 1) . ' days')),
        'previous_to'   => date('Y-m-d', strtotime(date('Y-m-d', $end) . ' 12:00:00 -' . $days . ' days')),
    );
}

// The four runReport requests of one batchRunReports call: the totals of both
// periods, active users per day, the most viewed pages and the sources that
// brought the most sessions.
function pg_ga_report_requests($period, $limit = PG_GA_TOP_ROWS)
{
    $current = array(array('startDate' => $period['from'], 'endDate' => $period['to']));
    $metrics = array();

    foreach (pg_ga_metrics() as $metric) {
        $metrics[] = array('name' => $metric);
    }

    $top = function ($dimension, $metric) use ($current, $limit) {
        return array(
            'dateRanges' => $current,
            'dimensions' => array(array('name' => $dimension)),
            'metrics'    => array(array('name' => $metric)),
            'orderBys'   => array(array('metric' => array('metricName' => $metric), 'desc' => true)),
            'limit'      => (int) $limit,
        );
    };

    return array(
        array(
            'dateRanges' => array(
                array('startDate' => $period['from'], 'endDate' => $period['to'], 'name' => 'current'),
                array('startDate' => $period['previous_from'], 'endDate' => $period['previous_to'], 'name' => 'previous'),
            ),
            'metrics' => $metrics,
        ),
        array(
            'dateRanges' => $current,
            'dimensions' => array(array('name' => 'date')),
            'metrics'    => array(array('name' => 'activeUsers')),
            'orderBys'   => array(array('dimension' => array('dimensionName' => 'date'))),
            'limit'      => 100,
        ),
        $top('pagePath', 'screenPageViews'),
        $top('sessionSourceMedium', 'sessions'),
    );
}

// One report's rows as (first dimension value, metric name => value) pairs.
function pg_ga_report_rows($report)
{
    $names = array();
    $rows = array();

    if (!is_array($report) || !isset($report['metricHeaders']) || !is_array($report['metricHeaders'])) {
        return $rows;
    }

    foreach ($report['metricHeaders'] as $header) {
        $names[] = (is_array($header) && isset($header['name'])) ? (string) $header['name'] : '';
    }

    foreach ((isset($report['rows']) && is_array($report['rows'])) ? $report['rows'] : array() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $values = array();

        foreach ($names as $index => $name) {
            $values[$name] = isset($row['metricValues'][$index]['value']) ? (float) $row['metricValues'][$index]['value'] : 0.0;
        }

        $rows[] = array(
            'key'    => isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : '',
            'values' => $values,
        );
    }

    return $rows;
}

// A metric as kept: counts whole, the engagement rate a fraction to four places.
function pg_ga_metric_value($metric, $value)
{
    return ($metric === 'engagementRate') ? round((float) $value, 4) : (int) round((float) $value);
}

// A batchRunReports answer as the card's report.
function pg_ga_report_parse($data, $period)
{
    $reports = (is_array($data) && isset($data['reports']) && is_array($data['reports'])) ? array_values($data['reports']) : array();
    $report = array(
        'from'          => $period['from'],
        'to'            => $period['to'],
        'previous_from' => $period['previous_from'],
        'previous_to'   => $period['previous_to'],
        'now'           => array(),
        'before'        => array(),
        'daily'         => array(),
        'pages'         => array(),
        'sources'       => array(),
        'empty'         => true,
    );

    foreach (pg_ga_metrics() as $metric) {
        $report['now'][$metric] = pg_ga_metric_value($metric, 0);
        $report['before'][$metric] = pg_ga_metric_value($metric, 0);
    }

    // Google adds the date range dimension itself: the range's name, or
    // date_range_N when it has none.
    foreach (pg_ga_report_rows(isset($reports[0]) ? $reports[0] : null) as $row) {
        $side = in_array($row['key'], array('current', 'date_range_0'), true) ? 'now'
            : (in_array($row['key'], array('previous', 'date_range_1'), true) ? 'before' : '');

        if ($side === '') {
            continue;
        }

        foreach (pg_ga_metrics() as $metric) {
            $report[$side][$metric] = pg_ga_metric_value($metric, isset($row['values'][$metric]) ? $row['values'][$metric] : 0);
        }
    }

    for ($day = strtotime($period['from'] . ' 12:00:00'); date('Y-m-d', $day) <= $period['to']; $day = strtotime(date('Y-m-d', $day) . ' 12:00:00 +1 day')) {
        $report['daily'][date('Y-m-d', $day)] = 0;
    }

    foreach (pg_ga_report_rows(isset($reports[1]) ? $reports[1] : null) as $row) {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $row['key'], $match)) {
            $date = $match[1] . '-' . $match[2] . '-' . $match[3];

            if (isset($report['daily'][$date])) {
                $report['daily'][$date] = (int) round(isset($row['values']['activeUsers']) ? $row['values']['activeUsers'] : 0);
            }
        }
    }

    foreach (array(2 => array('pages', 'screenPageViews'), 3 => array('sources', 'sessions')) as $index => $list) {
        foreach (pg_ga_report_rows(isset($reports[$index]) ? $reports[$index] : null) as $row) {
            if ($row['key'] !== '') {
                $report[$list[0]][] = array(
                    'name'  => function_exists('mb_substr') ? mb_substr($row['key'], 0, 300) : substr($row['key'], 0, 300),
                    'value' => (int) round(isset($row['values'][$list[1]]) ? $row['values'][$list[1]] : 0),
                );
            }
        }
    }

    $report['empty'] = ($report['now']['activeUsers'] + $report['now']['sessions'] + $report['now']['screenPageViews']) === 0;

    return $report;
}

// How far a count moved against the period before, as a fraction; null when
// the period before had none, since no percentage can be drawn from nothing.
function pg_ga_change($now, $before)
{
    if ((float) $before <= 0) {
        return null;
    }

    return ((float) $now - (float) $before) / (float) $before;
}

// The card's five figures: counts with their change as a fraction, the
// engagement rate in percent with its change in percentage points.
function pg_ga_card_figures($report)
{
    $figures = array();

    foreach (pg_ga_metrics() as $metric) {
        $now = isset($report['now'][$metric]) ? $report['now'][$metric] : 0;
        $before = isset($report['before'][$metric]) ? $report['before'][$metric] : 0;

        if ($metric === 'engagementRate') {
            $figures[$metric] = array(
                'value'  => round((float) $now * 100, 1),
                'kind'   => 'rate',
                'change' => ((float) $before > 0) ? round(((float) $now - (float) $before) * 100, 1) : null,
            );
        } else {
            $figures[$metric] = array('value' => (int) $now, 'kind' => 'count', 'change' => pg_ga_change($now, $before));
        }
    }

    return $figures;
}

// Whether the kept report is old enough to be read again. A reading dated
// in the future (a clock set back) is read again too.
function pg_ga_due($fetched_at, $now, $ttl = PG_GA_TTL)
{
    $fetched_at = (int) $fetched_at;

    return ($fetched_at <= 0) || ($fetched_at > (int) $now) || (((int) $now - $fetched_at) >= (int) $ttl);
}

// A GA4 property id, digits only, from "properties/123" or "123"; '' otherwise.
function pg_ga_property_id($value)
{
    $value = trim((string) $value);

    if (strpos($value, 'properties/') === 0) {
        $value = substr($value, 11);
    }

    return preg_match('/^\d{1,20}$/D', $value) ? $value : '';
}

// The properties of an accountSummaries.list answer: id => "property (account)".
function pg_ga_properties_from_api($data)
{
    $properties = array();

    foreach ((is_array($data) && isset($data['accountSummaries']) && is_array($data['accountSummaries'])) ? $data['accountSummaries'] : array() as $account) {
        if (!is_array($account) || !isset($account['propertySummaries']) || !is_array($account['propertySummaries'])) {
            continue;
        }

        $account_name = isset($account['displayName']) ? trim((string) $account['displayName']) : '';

        foreach ($account['propertySummaries'] as $property) {
            $id = is_array($property) && isset($property['property']) ? pg_ga_property_id($property['property']) : '';

            if ($id === '') {
                continue;
            }

            $name = isset($property['displayName']) ? trim((string) $property['displayName']) : '';
            $properties[$id] = (($name !== '') ? $name : $id) . (($account_name !== '') ? ' (' . $account_name . ')' : '');
        }
    }

    return $properties;
}

// The stored state, every key present and typed.
function pg_ga_state_parse($stored)
{
    $state = array(
        'connection_id' => 0,
        'property'      => '',
        'property_name' => '',
        'fetched_at'    => 0,
        'fetch_error'   => '',
        'report'        => array(),
    );

    if (!is_array($stored)) {
        return $state;
    }

    foreach (array('connection_id', 'fetched_at') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = (int) $stored[$key];
        }
    }

    foreach (array('property_name', 'fetch_error') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = (string) $stored[$key];
        }
    }

    $state['property'] = isset($stored['property']) && is_scalar($stored['property']) ? pg_ga_property_id($stored['property']) : '';

    if (isset($stored['report']) && is_array($stored['report']) && !empty($stored['report']['now'])
        && is_array($stored['report']['now']) && isset($stored['report']['daily']) && is_array($stored['report']['daily'])) {
        $state['report'] = $stored['report'];
    }

    return $state;
}

/* ---------------------------------------------------------------------------
   State
   --------------------------------------------------------------------------- */

// Connections can be stored and sealed: the tables and the encryption key.
function pg_ga_ready()
{
    return function_exists('pg_connect_schema_ready') && pg_connect_schema_ready() && pg_connect_can_seal();
}

function pg_ga_state()
{
    return pg_ga_state_parse(pg_ga_ready() ? pg_connect_service_get('google_analytics') : null);
}

// Writes changes into a fresh read, so a job run meanwhile keeps its report.
function pg_ga_state_save($changes)
{
    $state = pg_ga_state();

    foreach ($changes as $key => $value) {
        if (array_key_exists($key, $state)) {
            $state[$key] = $value;
        }
    }

    $state = pg_ga_state_parse($state);
    pg_connect_service_put('google_analytics', 'Google Analytics', $state);

    return $state;
}

// A property is chosen: the card is drawn and the job reads.
function pg_ga_linked($state = null)
{
    if (!pg_ga_ready()) {
        return false;
    }

    $state = is_array($state) ? $state : pg_ga_state();

    return ($state['property'] !== '') && ($state['connection_id'] > 0);
}

// The Google account the figures are read through: the one chosen, while it
// still serves the purpose, else the newest site account connected for it.
function pg_ga_connection($state = null)
{
    if (!pg_ga_ready()) {
        return null;
    }

    $state = is_array($state) ? $state : pg_ga_state();

    if ($state['connection_id'] > 0) {
        $account = pg_connect_account($state['connection_id']);

        if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'google')
            && in_array('google_analytics', $account['purposes'], true)) {
            return $account;
        }
    }

    $accounts = pg_connect_accounts(array('provider' => 'google', 'user_id' => 0, 'purpose' => 'google_analytics'));

    return $accounts ? $accounts[0] : null;
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

function pg_ga_call($connection_id, $method, $url, $body = null)
{
    $options = array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 40,
        'max_body' => 4194304,
    );

    if ($body !== null) {
        $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options['headers'][] = 'Content-Type: application/json';
    }

    $result = pg_connect_api($connection_id, $method, $url, $options);
    $data = is_array($result['data']) ? $result['data'] : array();
    $error = '';

    if (!$result['ok']) {
        $error = isset($data['error']['message']) && is_string($data['error']['message'])
            ? pg_connect_clip('HTTP ' . (int) $result['status'] . ': ' . $data['error']['message'])
            : pg_connect_clip(($result['error'] !== '') ? $result['error'] : 'HTTP ' . (int) $result['status']);
    }

    return array('ok' => $result['ok'], 'data' => $data, 'error' => $error);
}

// The GA4 properties the account can read, every page of accountSummaries.
function pg_ga_properties($connection_id)
{
    $properties = array();
    $token = '';

    for ($page = 0; $page < 10; $page++) {
        $call = pg_ga_call($connection_id, 'GET', PG_GA_ADMIN_API . '/accountSummaries?pageSize=200' . (($token !== '') ? '&pageToken=' . rawurlencode($token) : ''));

        if (!$call['ok']) {
            return array('ok' => false, 'properties' => $properties, 'error' => $call['error']);
        }

        $properties += pg_ga_properties_from_api($call['data']);
        $token = isset($call['data']['nextPageToken']) ? (string) $call['data']['nextPageToken'] : '';

        if ($token === '') {
            break;
        }
    }

    return array('ok' => true, 'properties' => $properties, 'error' => '');
}

// Reads the report again when the kept one is older than the cache window,
// or at once when forced.
function pg_ga_refresh($force = false)
{
    $state = pg_ga_state();

    if (!pg_ga_linked($state) || (!$force && !pg_ga_due($state['fetched_at'], time()))) {
        return array('ok' => true, 'ran' => false, 'error' => '');
    }

    $connection = pg_ga_connection($state);

    if (!is_array($connection)) {
        $error = lang('The Google account connected for Google Analytics is no longer there.');
        pg_ga_state_save(array('fetched_at' => time(), 'fetch_error' => $error));

        return array('ok' => false, 'ran' => true, 'error' => $error);
    }

    $period = pg_ga_period(date('Y-m-d'));
    $call = pg_ga_call($connection['id'], 'POST', PG_GA_DATA_API . '/properties/' . $state['property'] . ':batchRunReports', array('requests' => pg_ga_report_requests($period)));

    if (!$call['ok']) {
        pg_ga_state_save(array('fetched_at' => time(), 'fetch_error' => $call['error']));

        return array('ok' => false, 'ran' => true, 'error' => $call['error']);
    }

    pg_ga_state_save(array('fetched_at' => time(), 'fetch_error' => '', 'report' => pg_ga_report_parse($call['data'], $period)));

    return array('ok' => true, 'ran' => true, 'error' => '');
}

// Adds the job to the ones the general job runs, when the general job is the
// one running them. A site that schedules each job on its own keeps its own.
function pg_ga_job_switch_on()
{
    if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return;
    }

    $list = pg_cron_dispatch_list();

    if (!in_array('google_analytics_job', $list, true)) {
        $list[] = 'google_analytics_job';
        db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");
    }
}

// What the Visitor Tracking card asked for: choose a property, read the
// figures now, or stop using the property. Choosing one reads at once.
// Returns (kind, text) pairs; the texts may carry Google's words.
function pg_ga_save_from_screen($input)
{
    $messages = array();

    if (!pg_ga_ready()) {
        return $messages;
    }

    $state = pg_ga_state();

    if (!empty($input['unlink'])) {
        pg_ga_state_save(array('property' => '', 'property_name' => '', 'fetched_at' => 0, 'fetch_error' => '', 'report' => array()));
        $messages[] = array('notice', lang('Google Analytics figures are no longer read. The Google account stays connected until you remove it under Connected accounts.'));

        return $messages;
    }

    $chosen = pg_ga_property_id(isset($input['property']) ? $input['property'] : '');
    $refresh = !empty($input['refresh']);

    if (($chosen !== '') && ($chosen !== $state['property'])) {
        $connection = pg_ga_connection($state);
        $offered = is_array($connection) ? pg_ga_properties($connection['id']) : array('ok' => false, 'properties' => array(), 'error' => '');

        if (!isset($offered['properties'][$chosen])) {
            $messages[] = array('error', lang('The Google Analytics property could not be chosen.') . (($offered['error'] !== '') ? ' ' . $offered['error'] : ''));

            return $messages;
        }

        pg_ga_state_save(array(
            'connection_id' => $connection['id'], 'property' => $chosen, 'property_name' => $offered['properties'][$chosen],
            'fetched_at' => 0, 'fetch_error' => '', 'report' => array(),
        ));
        pg_ga_job_switch_on();
        $refresh = true;
    }

    if ($refresh) {
        $result = pg_ga_refresh(true);

        $messages[] = $result['ok']
            ? array('notice', lang('The Google Analytics figures were read.'))
            : array('error', lang(array('string' => 'The Google Analytics figures could not be read: {var:1}', 'vars' => $result['error'])));
    }

    return $messages;
}
