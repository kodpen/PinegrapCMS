<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Answer helpers for Cloudflare Tools (cloudflare.php): whether a REST or
 * GraphQL answer failed, the reason to show for it, and the analytics cards
 * built from the GraphQL reads. A GraphQL error arrives as HTTP 200 with an
 * "errors" list and data null, so a 200 alone does not mean the read worked.
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
            return pg_format_number($totals['cached_bytes'] / 1024 / 1024, 2) . ' MB';
        default:
            return '';
    }
}
