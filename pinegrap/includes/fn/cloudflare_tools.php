<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Answer helpers for Cloudflare Tools (cloudflare.php): whether a REST or
 * GraphQL answer failed, the reason to show for it, and the analytics cards
 * built from the GraphQL reads. A GraphQL error arrives as HTTP 200 with an
 * "errors" list and data null, so a 200 alone does not mean the read worked.
 * Also the Cache Rule that keeps a copy of a picture per format (WebP at a
 * picture's own address); the check that confirms it needs no token and is
 * in includes/fn/image_vary_check.php.
 * Loaded by cloudflare.php only.
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
 * Whether a cf_request() / cf_graphql() answer ({status, json}) failed.
 *
 * @param array $answer
 * @param bool  $graphql true for a GraphQL answer
 * @return bool
 */
function pg_cloudflare_answer_failed($answer, $graphql = false)
{
    if (!is_array($answer) || ($answer['status'] ?? 0) !== 200) {
        return true;
    }

    $json = $answer['json'] ?? null;

    if (!is_array($json)) {
        return true;
    }

    if ($graphql) {
        return !empty($json['errors']) || !isset($json['data']) || !is_array($json['data']);
    }

    return empty($json['success']);
}

/**
 * The reason to show for a failed answer: Cloudflare's own first error
 * message, else the HTTP status, else that nothing answered (a timeout or no
 * connection). Not escaped: the caller escapes it for HTML.
 *
 * @param array $answer
 * @return string
 */
function pg_cloudflare_answer_reason($answer)
{
    $message = $answer['json']['errors'][0]['message'] ?? '';

    if (is_string($message) && trim($message) !== '') {
        return trim($message);
    }

    $status = (int) ($answer['status'] ?? 0);

    if ($status > 0) {
        return lang(array('string' => 'Cloudflare answered with HTTP {var:1}.', 'vars' => $status));
    }

    return lang('Cloudflare did not answer.');
}

/**
 * A Cloudflare setting value (development_mode, security_level, ...) as a word
 * in the panel language, for the notice and the Site Log line after a
 * toggle. A value this list does not know is returned as it came, so a new
 * Cloudflare value still reads; anything but a string gives ''. Not escaped.
 *
 * @param mixed $value the value sent to Cloudflare, such as 'on' or 'under_attack'
 * @return string
 */
function pg_cloudflare_setting_value_label($value)
{
    if (!is_string($value)) {
        return '';
    }

    switch ($value) {
        case 'on':
            return lang('on');
        case 'off':
            return lang('off');
        case 'under_attack':
            return lang('under attack');
        case 'essentially_off':
            return lang('essentially off');
        case 'low':
            return lang('low');
        case 'medium':
            return lang('medium');
        case 'high':
            return lang('high');
    }

    return $value;
}

/**
 * Totals of the three analytics reads: the daily request groups, the
 * adaptive visit groups and the daily uniques fallback (null when it was not
 * sent). 'error' is the reason the request totals could not be read,
 * 'uniques_error' the reason no visitor read answered; both '' on success.
 *
 * @param array      $main
 * @param array      $uniques
 * @param array|null $uniques_fallback
 * @return array
 */
function pg_cloudflare_analytics_totals($main, $uniques, $uniques_fallback)
{
    $totals = array(
        'requests'        => 0,
        'bandwidth'       => 0,
        'uniques'         => 0,
        'cached_requests' => 0,
        'cached_bytes'    => 0,
        'error'           => '',
        'uniques_error'   => '',
    );

    if (pg_cloudflare_answer_failed($main, true)) {
        $totals['error'] = pg_cloudflare_answer_reason($main);
    } else {
        $groups = $main['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'] ?? array();
        foreach ((is_array($groups) ? $groups : array()) as $day) {
            $totals['requests']        += (int) ($day['sum']['requests'] ?? 0);
            $totals['bandwidth']       += (int) ($day['sum']['bytes'] ?? 0);
            $totals['cached_requests'] += (int) ($day['sum']['cachedRequests'] ?? 0);
            $totals['cached_bytes']    += (int) ($day['sum']['cachedBytes'] ?? 0);
        }
    }

    $uniques_failed = pg_cloudflare_answer_failed($uniques, true);

    if (!$uniques_failed) {
        $groups = $uniques['json']['data']['viewer']['zones'][0]['httpRequestsAdaptiveGroups'] ?? array();
        foreach ((is_array($groups) ? $groups : array()) as $row) {
            $totals['uniques'] += (int) ($row['sum']['visits'] ?? 0);
        }
    }

    if ($uniques_fallback !== null) {
        if (pg_cloudflare_answer_failed($uniques_fallback, true)) {
            if ($uniques_failed) {
                $totals['uniques_error'] = pg_cloudflare_answer_reason($uniques_fallback);
            }
        } else {
            $groups = $uniques_fallback['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'] ?? array();
            foreach ((is_array($groups) ? $groups : array()) as $row) {
                $totals['uniques'] += (int) ($row['uniq']['uniques'] ?? 0);
            }
        }
    } elseif ($uniques_failed) {
        $totals['uniques_error'] = pg_cloudflare_answer_reason($uniques);
    }

    return $totals;
}

/**
 * One analytics card's value, or a dash when its read failed, so a refused
 * read is never shown as 0.
 *
 * @param array  $totals pg_cloudflare_analytics_totals()
 * @param string $area   requests, uniques, bandwidth, percent_cached or cached_bytes
 * @return string
 */
function pg_cloudflare_analytics_card($totals, $area)
{
    $failed = ($area === 'uniques') ? ($totals['uniques_error'] !== '') : ($totals['error'] !== '');

    if ($failed) {
        return '—';
    }

    switch ($area) {
        case 'requests':
            return pg_format_number($totals['requests'], 0);
        case 'uniques':
            return pg_format_number($totals['uniques'], 0);
        case 'bandwidth':
            return pg_format_number($totals['bandwidth'] / 1000 / 1000, 2) . ' MB';
        case 'percent_cached':
            return ($totals['bandwidth'] > 0)
                ? pg_format_number(($totals['cached_bytes'] / $totals['bandwidth']) * 100, 2) . ' %'
                : '0 %';
        case 'cached_bytes':
            return pg_format_number($totals['cached_bytes'] / 1000 / 1000, 2) . ' MB';
        default:
            return '';
    }
}

/**
 * The dates and group limit of an analytics day read. A preset period runs
 * from N days ago to today, which is N + 1 dates; the limit counts them all,
 * so Cloudflare does not leave one date out (it returns the groups in no set
 * order). A custom range keeps its own dates.
 *
 * @param int         $days       the preset period's length
 * @param string|null $start_date Y-m-d of a custom range, or null
 * @param string|null $end_date   Y-m-d of a custom range, or null
 * @param int         $now        the current time
 * @return array start, end (Y-m-d) and limit (the number of dates, at least 1)
 */
function pg_cloudflare_analytics_range($days, $start_date, $end_date, $now)
{
    if (is_string($start_date) && is_string($end_date) && ($start_date !== '') && ($end_date !== '')) {
        $start = $start_date;
        $end   = $end_date;
    } else {
        $start = date('Y-m-d', strtotime('-' . (int) $days . ' days', (int) $now));
        $end   = date('Y-m-d', (int) $now);
    }

    // Counted on UTC midnights, so a clock change in the range does not
    // leave a 23- or 25-hour day.
    $from = strtotime($start . ' 00:00:00 UTC');
    $to   = strtotime($end . ' 00:00:00 UTC');
    $limit = (($from !== false) && ($to !== false)) ? (int) round(($to - $from) / 86400) + 1 : 1;

    return array('start' => $start, 'end' => $end, 'limit' => max(1, $limit));
}

/**
 * The ref that marks the Cache Rule Pinegrap writes for WebP at a picture's
 * own address, so the rule is found again among the zone's other rules.
 *
 * @return string
 */
function pg_cloudflare_image_vary_ref()
{
    return 'pinegrap_webp_same_address';
}

/**
 * The Cache Rule (phase http_request_cache_settings) that makes Cloudflare
 * keep one copy of a JPEG or PNG per format: Accept is normalised to
 * image/webp or nothing, so a browser that reads WebP and one that does not
 * each get their own cached copy (get_file.php answers with Vary: Accept).
 * Other headers named in Vary pass through as Cloudflare treats them.
 * The extensions are the ones get_file.php negotiates.
 *
 * @return array
 */
function pg_cloudflare_image_vary_rule()
{
    return array(
        'ref'         => pg_cloudflare_image_vary_ref(),
        'description' => 'Pinegrap: WebP at the picture\'s own address',
        'expression'  => '(http.request.uri.path.extension in {"jpg" "jpeg" "jpe" "png"})',
        'action'      => 'set_cache_settings',
        'action_parameters' => array(
            'cache' => true,
            'vary'  => array(
                'default' => array('action' => 'passthrough'),
                'headers' => array(
                    'accept' => array('action' => 'normalize', 'media_types' => array('image/webp'))))),
        'enabled'     => true);
}

/**
 * Whether a rule Cloudflare holds does what pg_cloudflare_image_vary_rule()
 * asks: same expression and action, enabled, Accept normalised with
 * image/webp among its media types.
 *
 * @param mixed $rule a rule of the entrypoint ruleset
 * @return bool
 */
function pg_cloudflare_image_vary_rule_current($rule)
{
    $wanted = pg_cloudflare_image_vary_rule();

    if (!is_array($rule)
        || (($rule['expression'] ?? '') !== $wanted['expression'])
        || (($rule['action'] ?? '') !== $wanted['action'])
        || (isset($rule['enabled']) && ($rule['enabled'] !== true))) {
        return false;
    }

    $accept = $rule['action_parameters']['vary']['headers']['accept'] ?? null;

    return is_array($accept)
        && (($accept['action'] ?? '') === 'normalize')
        && is_array($accept['media_types'] ?? null)
        && in_array('image/webp', $accept['media_types'], true);
}

/**
 * What writing the rule takes, from the read of the zone's
 * http_request_cache_settings entrypoint ({status, json} of cf_request()):
 * create (no entrypoint yet, HTTP 404: PUT it with the rule), add (POST the
 * rule to the ruleset), update (PATCH the rule found by its ref), none (it is
 * in place) or failed (the read failed: no permission, no answer).
 *
 * @param array $answer
 * @return array do, ruleset_id, rule_id
 */
function pg_cloudflare_image_vary_plan($answer)
{
    $plan = array('do' => 'failed', 'ruleset_id' => '', 'rule_id' => '');

    if (is_array($answer) && (($answer['status'] ?? 0) === 404)) {
        $plan['do'] = 'create';
        return $plan;
    }

    if (pg_cloudflare_answer_failed($answer)) {
        return $plan;
    }

    $ruleset = $answer['json']['result'] ?? null;
    $ruleset_id = is_array($ruleset) ? ($ruleset['id'] ?? '') : '';

    // A ruleset id is hex; anything else is refused before it can become
    // part of an API path.
    if (!is_string($ruleset_id) || !preg_match('/^[a-f0-9]{32}$/', $ruleset_id)) {
        return $plan;
    }

    $plan['ruleset_id'] = $ruleset_id;
    $plan['do'] = 'add';

    foreach ((is_array($ruleset['rules'] ?? null) ? $ruleset['rules'] : array()) as $rule) {

        if (!is_array($rule) || (($rule['ref'] ?? '') !== pg_cloudflare_image_vary_ref())) {
            continue;
        }

        if (pg_cloudflare_image_vary_rule_current($rule)) {
            $plan['do'] = 'none';
            return $plan;
        }

        $rule_id = $rule['id'] ?? '';

        if (is_string($rule_id) && preg_match('/^[a-f0-9]{32}$/', $rule_id)) {
            $plan['do'] = 'update';
            $plan['rule_id'] = $rule_id;
        }

        return $plan;
    }

    return $plan;
}

/**
 * The last value of a header in a raw header block (pg_curl_run() with
 * response_headers), '' when it is not there. After a redirect or a 100
 * Continue the block holds several answers; the last one counts.
 *
 * @param string $headers
 * @param string $name
 * @return string
 */
function pg_cloudflare_header_value($headers, $name)
{
    $value = '';
    $name = strtolower((string) $name);

    foreach (preg_split('/\r?\n/', (string) $headers) as $line) {

        $colon = strpos($line, ':');

        if (($colon !== false) && (strtolower(trim(substr($line, 0, $colon))) === $name)) {
            $value = trim(substr($line, $colon + 1));
        }
    }

    return $value;
}
