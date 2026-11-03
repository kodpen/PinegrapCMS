<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Firewall card: blocked requests, bots and failed sign-ins for the
 * period, the blocked requests as bars (an hour each for today, a day each
 * for 7 and 30 days, a bar well above the rest marked), and what was
 * blocked most. Read from the same table and the same buckets as widgets 21
 * and 22: hit_count summed, never rows counted, since identical events are
 * folded into five-minute buckets. Staff only (role < 3): the log holds
 * visitor addresses and attack payloads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * Bars from grouped rows: slot (0 = the oldest) and hits.
 *
 * @param array $rows
 * @param int   $count
 * @return int[]
 */
function pg_dashboard_v2_slot_bars($rows, $count)
{
    $bars = array_fill(0, (int) $count, 0);

    foreach ((array) $rows as $row) {
        $slot = (int) $row['slot'];

        if (($slot >= 0) && ($slot < $count)) {
            $bars[$slot] += (int) $row['hits'];
        }
    }

    return $bars;
}

/**
 * Which bars stand well above the rest: more than twice the average of the
 * bars that have any hits, and at least five. A quiet day with one spike
 * marks the spike; an even day marks nothing.
 *
 * @param int[] $bars
 * @return bool[]
 */
function pg_dashboard_v2_hot_bars($bars)
{
    $busy = array_filter((array) $bars, function ($value) {
        return $value > 0;
    });

    $line = empty($busy) ? 0 : (2 * array_sum($busy) / count($busy));
    $hot = array();

    foreach ((array) $bars as $value) {
        $hot[] = (count($busy) > 1) && ($value >= 5) && ($value > $line);
    }

    return $hot;
}

/**
 * The name of what was blocked: a bot's or a scanner's own name, else the
 * rule category in words (the names widget 22 uses).
 *
 * @param string $source
 * @param string $category
 * @return string
 */
function pg_dashboard_v2_waf_source($source, $category)
{
    $names = array(
        'sqli'     => lang('SQL Injection'),
        'xss'      => lang('Cross-site Scripting'),
        'lfi'      => lang('Path Traversal'),
        'rce'      => lang('Command Injection'),
        'protocol' => lang('Protocol Abuse'),
        'bot'      => lang('Bots'),
        'tool'     => lang('Scanners'),
        'rate'     => lang('Rate Limit'),
        'iplist'   => lang('IP List'),
        'country'  => lang('Country Restriction'),
        'ban'      => lang('Bans'),
        'login'    => lang('Sign-in Lockouts'),
    );

    $source = (string) $source;

    if (in_array($category, array('bot', 'tool'), true)) {
        return ($source === mb_strtolower($source, 'UTF-8')) ? mb_convert_case($source, MB_CASE_TITLE, 'UTF-8') : $source;
    }

    return $names[$source] ?? $source;
}

function pg_dashboard_v2_card_firewall($user, $period, $compare)
{
    if ((int) $user['role'] >= 3) {
        return array('status' => 'denied');
    }

    if (!pg_schema_has('waf_log')) {
        return array('status' => 'success', 'data' => array(
            'empty' => lang('The firewall tables do not exist yet. Please run the software upgrade to create them.'),
        ));
    }

    $now = time();
    $mode = function_exists('waf_mode') ? waf_mode() : 'off';

    // In monitor mode nothing was blocked; what blocking would have stopped
    // is the figure that means something, as on widget 21.
    $actions = ($mode === 'monitor') ? "'would-block','would-rate','would-ban'" : "'block','rate','ban'";

    if ((int) $period === 0) {
        $count = 24;
        $size = 3600;
        $from = ($now - ($now % 3600)) - (23 * 3600);
        $bars_label = lang('Blocked requests per hour, last 24 hours');
    } else {
        $count = ((int) $period === 1) ? 7 : 30;
        $size = 86400;
        $from = mktime(0, 0, 0, (int) date('n', $now), (int) date('j', $now) - ($count - 1), (int) date('Y', $now));
        $bars_label = ($count === 7) ? lang('Blocked requests per day, last 7 days') : lang('Blocked requests per day, last 30 days');
    }

    $totals = db_item(
        "SELECT
            COALESCE(SUM(CASE WHEN action IN (" . $actions . ") THEN hit_count ELSE 0 END), 0) AS blocked,
            COALESCE(SUM(CASE WHEN category IN ('bot','tool') THEN hit_count ELSE 0 END), 0) AS bots
         FROM waf_log WHERE log_timestamp >= " . (int) $from);

    $blocked = (int) ($totals['blocked'] ?? 0);
    $bots = (int) ($totals['bots'] ?? 0);

    // Failed sign-ins: the sign-in throttle counts each one against the
    // account in waf_rate (scope "failu"), the only place a single failure
    // is kept. A bucket keeps its latest window only, so this is the
    // failures of the windows still open in the period: a floor, never more
    // than happened.
    $failed = pg_schema_has('waf_rate')
        ? (int) db_value("SELECT COALESCE(SUM(hits), 0) FROM waf_rate WHERE bucket_key LIKE '%|failu' AND window_start >= " . (int) $from)
        : 0;

    // FLOOR on the difference from the first slot: daily slots drift by an
    // hour across a clock change, which a bar can carry.
    $bars = pg_dashboard_v2_slot_bars(db_items(
        "SELECT FLOOR((log_timestamp - " . (int) $from . ") / " . $size . ") AS slot, SUM(hit_count) AS hits
         FROM waf_log WHERE log_timestamp >= " . (int) $from . " AND action IN (" . $actions . ")
         GROUP BY slot"), $count);

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/tips.php');
    $tips = pg_dashboard_v2_tips(pg_dashboard_v2_slot_labels($from, $size, $count), $bars, function ($n) use ($mode) {
        return ($mode === 'monitor')
            ? lang(array('string' => '{var:1} would be blocked', 'vars' => pg_format_number($n, 0)))
            : lang(array('string' => '{var:1} blocked', 'vars' => pg_format_number($n, 0)));
    });

    $rows = array();

    foreach ((array) db_items(
        "SELECT CASE WHEN category IN ('bot','tool') AND matched <> '' THEN matched ELSE category END AS source,
                category, SUM(hit_count) AS hits
         FROM waf_log
         WHERE log_timestamp >= " . (int) $from . " AND action IN (" . $actions . ")
         GROUP BY source, category
         ORDER BY hits DESC, source ASC, category ASC
         LIMIT 4") as $row) {
        $rows[] = array(
            'text'  => pg_dashboard_v2_waf_source($row['source'], $row['category']),
            'value' => (int) $row['hits'],
            'note'  => pg_format_number((int) $row['hits'], 0),
        );
    }

    return array('status' => 'success', 'data' => array(
        'tiles' => array(
            array('label' => ($mode === 'monitor') ? lang('Would block') : lang('Blocked'), 'text' => pg_format_number($blocked, 0)),
            array('label' => lang('Bots'), 'text' => pg_format_number($bots, 0)),
            array('label' => lang('Failed sign-ins'), 'text' => pg_format_number($failed, 0), 'tone' => ($failed > 0) ? 'bad' : ''),
        ),
        'mode'       => ($mode === 'off') ? lang('The firewall is off: only sign-in lockouts are recorded.') : '',
        'bars'       => $bars,
        'hot'        => pg_dashboard_v2_hot_bars($bars),
        'bars_label' => $bars_label,
        'tips'       => $tips,
        'rows_title' => lang('Most blocked'),
        'rows'       => $rows,
        'url'        => 'view_waf_log.php',
        'url_text'   => lang('Security log'),
    ));
}
