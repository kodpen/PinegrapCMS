<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The results of page experiments (experiments.php): views, conversions and
 * rate per variant from the hourly rollup experiment_hourly, the lift against
 * the live page, a two-proportion z-test told as a plain sentence, and the
 * daily rates for the chart.
 *
 * The figures are sums of experiment_hourly; nothing here reads a visit.
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
 * The two-sided p of a standard normal z: the chance of a difference at
 * least this large when there is none. The error function by Abramowitz and
 * Stegun 7.1.26 (error below 1.5e-7). Pure.
 *
 * @param float $z
 * @return float
 */
function pg_experiment_two_sided_p($z)
{
    $x = abs((float) $z) / sqrt(2.0);
    $t = 1.0 / (1.0 + 0.3275911 * $x);
    $poly = $t * (0.254829592 + $t * (-0.284496736 + $t * (1.421413741 + $t * (-1.453152027 + $t * 1.061405429))));
    $erf = 1.0 - $poly * exp(-$x * $x);

    return max(0.0, min(1.0, 1.0 - $erf));
}

/**
 * Compares a variant with the control by a two-proportion z-test. Goals are
 * held inside 0 and the views. Pure.
 *
 * @param int $views_a the control's views
 * @param int $goals_a
 * @param int $views_b the variant's views
 * @param int $goals_b
 * @return array rate_a, rate_b (0-1), lift (relative to a, null without a
 *               rate of a), z, p (null without views on both), significant
 *               (p below 0.05), needed (more views each variant needs at
 *               these rates for 95 % confidence and 80 % power; 0 when
 *               already significant, null when it cannot be said),
 *               views_a, goals_a, views_b, goals_b (as counted)
 */
function pg_experiment_significance($views_a, $goals_a, $views_b, $goals_b)
{
    $views_a = max(0, (int) $views_a);
    $views_b = max(0, (int) $views_b);
    $goals_a = max(0, min((int) $goals_a, $views_a));
    $goals_b = max(0, min((int) $goals_b, $views_b));

    $rate_a = ($views_a > 0) ? $goals_a / $views_a : 0.0;
    $rate_b = ($views_b > 0) ? $goals_b / $views_b : 0.0;

    $out = array(
        'rate_a' => (float) $rate_a,
        'rate_b' => (float) $rate_b,
        'lift' => ($rate_a > 0) ? ($rate_b - $rate_a) / $rate_a : null,
        'z' => null,
        'p' => null,
        'significant' => false,
        'needed' => null,
        'views_a' => $views_a,
        'goals_a' => $goals_a,
        'views_b' => $views_b,
        'goals_b' => $goals_b,
    );

    if (($views_a === 0) || ($views_b === 0)) {
        return $out;
    }

    $pooled = ($goals_a + $goals_b) / ($views_a + $views_b);
    $se = sqrt($pooled * (1 - $pooled) * (1 / $views_a + 1 / $views_b));

    if ($se > 0) {
        $out['z'] = ($rate_b - $rate_a) / $se;
        $out['p'] = pg_experiment_two_sided_p($out['z']);
        $out['significant'] = ($out['p'] < 0.05);
    }

    if ($out['significant']) {
        $out['needed'] = 0;
    } elseif (abs($rate_b - $rate_a) > 0) {
        // Views per variant for 95 % confidence (1.96) and 80 % power (0.84)
        // if the rates stay as they are.
        $n = pow(1.96 + 0.84, 2) * ($rate_a * (1 - $rate_a) + $rate_b * (1 - $rate_b)) / pow($rate_b - $rate_a, 2);
        $out['needed'] = max(0, (int) ceil($n) - min($views_a, $views_b));
    }

    return $out;
}

/**
 * Which sentence a result gives: 'ahead' or 'behind' (the variant against
 * the control, beyond chance), 'more' (not beyond chance yet: a number of
 * views would tell, or one version has no views or nobody converted yet),
 * 'even' (no difference to tell), 'none' (no views on either version).
 * Pure.
 *
 * @param array $r pg_experiment_significance()
 * @return string
 */
function pg_experiment_verdict_kind(array $r)
{
    if (isset($r['views_a'], $r['views_b'])) {
        $views_a = (int) $r['views_a'];
        $views_b = (int) $r['views_b'];

        if ($views_a + $views_b === 0) {
            return 'none';
        }

        // One side without views, or views without a single conversion, is
        // too early to call: equal zero rates are not "the same rate".
        if (($views_a === 0) || ($views_b === 0) || ((int) ($r['goals_a'] ?? 0) + (int) ($r['goals_b'] ?? 0) === 0)) {
            return 'more';
        }
    } elseif ($r['p'] === null && $r['z'] === null && (float) $r['rate_a'] === 0.0 && (float) $r['rate_b'] === 0.0 && $r['needed'] === null) {
        return 'none';
    }

    if (!empty($r['significant'])) {
        return ((float) $r['rate_b'] > (float) $r['rate_a']) ? 'ahead' : 'behind';
    }

    return ($r['needed'] === null) ? 'even' : 'more';
}

/**
 * The plain sentence of a result for a variant against the control.
 *
 * @param array  $r   pg_experiment_significance()
 * @param string $key the variant's key, as shown (B)
 * @return string translated, not escaped
 */
function pg_experiment_verdict_sentence(array $r, $key)
{
    $key = strtoupper((string) $key);
    $lift = ($r['lift'] === null) ? '' : pg_experiment_number(abs($r['lift']) * 100, 1);

    switch (pg_experiment_verdict_kind($r)) {
        case 'ahead':
            return ($lift === '')
                ? lang(array('string' => '{var:1} is ahead, and the difference is unlikely to be chance.', 'vars' => $key))
                : lang(array('string' => '{var:1} is ahead by {var:2} %, and the difference is unlikely to be chance.', 'vars' => array($key, $lift)));
        case 'behind':
            return lang(array('string' => '{var:1} is behind by {var:2} %, and the difference is unlikely to be chance.', 'vars' => array($key, $lift)));
        case 'more':
            if ($r['needed'] !== null) {
                return lang(array('string' => 'Not enough visits yet: about {var:1} more for each version.', 'vars' => pg_experiment_number((int) $r['needed'], 0)));
            }

            if (((int) ($r['views_a'] ?? 0) > 0) && ((int) ($r['views_b'] ?? 0) > 0)) {
                return lang('No conversions counted yet.');
            }

            return lang('Not enough visits yet.');
        case 'even':
            return lang('Both versions convert at the same rate so far.');
    }

    return lang('No visits counted yet.');
}

/**
 * A figure with the panel language's decimal and thousands separators.
 *
 * @param float|int $value
 * @param int       $decimals
 * @return string not escaped
 */
function pg_experiment_number($value, $decimals = 1)
{
    return function_exists('pg_format_number')
        ? pg_format_number($value, $decimals)
        : number_format((float) $value, (int) $decimals);
}

/**
 * A percentage for display: the figure in the panel language's format and
 * the percent sign where that language puts it (37.5% / %37,5).
 *
 * @param float|int $percent already times 100
 * @param int       $decimals
 * @return string translated, not escaped
 */
function pg_experiment_percent($percent, $decimals = 1)
{
    return lang(array('string' => '{var:1}%', 'vars' => pg_experiment_number($percent, $decimals)));
}

/**
 * The daily conversion rate (percent, one decimal) of each variant from
 * hourly rows, every day of the range present; a day without views has no
 * rate (null). Pure.
 *
 * @param array  $rows day, variant_id, views, goals (hourly or daily sums)
 * @param array  $variant_ids in the order shown
 * @param string $from Y-m-d
 * @param string $to   Y-m-d
 * @return array days, rates (variant id => list in day order)
 */
function pg_experiment_daily_series(array $rows, array $variant_ids, $from, $to)
{
    $days = array();
    $ts = strtotime((string) $from . ' 12:00:00');
    $end = strtotime((string) $to . ' 12:00:00');

    // At most 400 days, stepped by calendar day (daylight saving safe).
    while (($ts !== false) && ($end !== false) && ($ts <= $end) && (count($days) < 400)) {
        $days[] = date('Y-m-d', $ts);
        $ts = strtotime('+1 day', $ts);
    }

    $sums = array();

    foreach ($rows as $row) {
        $day = (string) ($row['day'] ?? '');
        $id = (int) ($row['variant_id'] ?? 0);

        if (!isset($sums[$id][$day])) {
            $sums[$id][$day] = array(0, 0);
        }

        $sums[$id][$day][0] += (int) ($row['views'] ?? 0);
        $sums[$id][$day][1] += (int) ($row['goals'] ?? 0);
    }

    $rates = array();

    foreach ($variant_ids as $id) {
        $id = (int) $id;
        $rates[$id] = array();

        foreach ($days as $day) {
            $s = isset($sums[$id][$day]) ? $sums[$id][$day] : array(0, 0);
            $rates[$id][] = ($s[0] > 0) ? round(min($s[1], $s[0]) * 100 / $s[0], 1) : null;
        }
    }

    return array('days' => $days, 'rates' => $rates);
}

/**
 * The results rows of an experiment's variants: each variant's figures and,
 * for every variant but the control, the comparison with it. The control is
 * the variant without a draft (the live page), else the first. Pure.
 *
 * @param array $variants key => [id, draft_page_id, views, goals, ...]
 * @return array key => the variant with rate, lift, result (null for the
 *               control), control (bool)
 */
function pg_experiment_results_rows(array $variants)
{
    $control = function_exists('pg_experiment_control_key') ? pg_experiment_control_key($variants) : '';

    if (($control === '') && $variants) {
        $control = (string) key($variants);
    }

    $base = isset($variants[$control]) ? $variants[$control] : array('views' => 0, 'goals' => 0);
    $rows = array();

    foreach ($variants as $key => $variant) {
        $views = max(0, (int) ($variant['views'] ?? 0));
        $goals = max(0, min((int) ($variant['goals'] ?? 0), $views));
        $is_control = ((string) $key === $control);
        $result = $is_control ? null : pg_experiment_significance($base['views'] ?? 0, $base['goals'] ?? 0, $views, $goals);

        $variant['views'] = $views;
        $variant['goals'] = $goals;
        $variant['rate'] = ($views > 0) ? $goals / $views : null;
        $variant['lift'] = $result ? $result['lift'] : null;
        $variant['result'] = $result;
        $variant['control'] = $is_control;
        $rows[(string) $key] = $variant;
    }

    return $rows;
}

/**
 * The days the chart covers: from the start (or the creation) to the end, or
 * today while it runs; at most the last 90 days. Pure.
 *
 * @param array $experiment started_at, ended_at, created_at
 * @param int   $now
 * @return array from, to (Y-m-d)
 */
function pg_experiment_chart_range(array $experiment, $now)
{
    $start = (int) ($experiment['started_at'] ?? 0) ?: (int) ($experiment['created_at'] ?? 0) ?: (int) $now;
    $end = (int) ($experiment['ended_at'] ?? 0) ?: (int) $now;
    $end = max($end, $start);
    $start = max($start, strtotime('-89 days', $end));

    return array('from' => date('Y-m-d', $start), 'to' => date('Y-m-d', $end));
}

/**
 * The daily rates as an inline SVG line chart, one polyline per variant; the
 * figures are in the table the screen prints beside it, so the picture is
 * aria-hidden. Pure.
 *
 * @param array $series pg_experiment_daily_series()
 * @param array $colors variant id => CSS colour (a var(--pg-…) token)
 * @return string '' when no day has a rate; a series of 0 % days draws
 */
function pg_experiment_chart_svg(array $series, array $colors)
{
    $max = 0.0;
    $any = false;

    foreach ($series['rates'] as $list) {
        foreach ($list as $rate) {
            if ($rate !== null) {
                $any = true;
                $max = max($max, (float) $rate);
            }
        }
    }

    $count = count($series['days']);

    if (!$any || ($count === 0)) {
        return '';
    }

    $width = 600;
    $height = 160;
    // A 0 % series still draws, as a flat line at the bottom.
    $top = max(5, ceil($max / 5) * 5);
    $step = ($count > 1) ? $width / ($count - 1) : 0;
    $lines = '';

    foreach ($series['rates'] as $id => $list) {
        $points = array();

        foreach ($list as $i => $rate) {
            if ($rate !== null) {
                $points[] = round($i * $step, 1) . ',' . round($height - ($rate / $top) * $height, 1);
            }
        }

        if ($points) {
            $color = isset($colors[$id]) ? (string) $colors[$id] : 'currentColor';
            $lines .= '<polyline fill="none" stroke="' . h($color) . '" stroke-width="2" vector-effect="non-scaling-stroke" points="' . implode(' ', $points) . '"/>';

            // A lone point as a zero-length round-capped line: the SVG is
            // stretched (preserveAspectRatio none) and a non-scaling stroke
            // keeps it round, where a circle would turn into an ellipse.
            if (count($points) === 1) {
                $lines .= '<polyline fill="none" stroke="' . h($color) . '" stroke-width="6" stroke-linecap="round" vector-effect="non-scaling-stroke" points="' . $points[0] . ' ' . $points[0] . '"/>';
            }
        }
    }

    return '<svg class="w-100" viewBox="-4 -4 ' . ($width + 8) . ' ' . ($height + 8) . '" preserveAspectRatio="none" height="' . $height . '" aria-hidden="true" focusable="false">'
        . '<line x1="0" y1="' . $height . '" x2="' . $width . '" y2="' . $height . '" stroke="var(--pg-border-color)" stroke-width="1" vector-effect="non-scaling-stroke"/>'
        . $lines . '</svg>';
}

/**
 * Every experiment of the site with its page and its summed views and
 * goals, newest first.
 *
 * @return array id, page_id, name, status, goal_kind, goal_target, started_at,
 *               ended_at, page_name, page_folder, views, goals
 */
function pg_experiment_results_list()
{
    if (!function_exists('pg_experiment_sheet_ready') || !pg_experiment_sheet_ready()) {
        return array();
    }

    $rows = (array) db_items(
        "SELECT experiments.id, experiments.page_id, experiments.name, experiments.status, experiments.goal_kind,
            experiments.goal_target, experiments.started_at, experiments.ended_at, page.page_name, page.page_folder
        FROM experiments
        LEFT JOIN page ON page.page_id = experiments.page_id
        ORDER BY experiments.id DESC");

    $sums = array();

    if ($rows && pg_schema_has('experiment_hourly')) {
        foreach ((array) db_items("SELECT experiment_id, SUM(views) AS views, SUM(goals) AS goals FROM experiment_hourly GROUP BY experiment_id") as $sum) {
            $sums[(int) $sum['experiment_id']] = $sum;
        }
    }

    foreach ($rows as $i => $row) {
        $rows[$i]['views'] = isset($sums[(int) $row['id']]) ? (int) $sums[(int) $row['id']]['views'] : 0;
        $rows[$i]['goals'] = isset($sums[(int) $row['id']]) ? (int) $sums[(int) $row['id']]['goals'] : 0;
    }

    return $rows;
}

/**
 * The hourly rows of an experiment summed per day and variant over a range.
 *
 * @param int    $experiment_id
 * @param string $from Y-m-d
 * @param string $to   Y-m-d
 * @return array day, variant_id, views, goals
 */
function pg_experiment_results_days($experiment_id, $from, $to)
{
    if (!pg_schema_has('experiment_hourly')) {
        return array();
    }

    $result = db_q(
        "SELECT day, variant_id, SUM(views) AS views, SUM(goals) AS goals
        FROM experiment_hourly
        WHERE experiment_id = ? AND day BETWEEN ? AND ?
        GROUP BY day, variant_id
        ORDER BY day",
        array((int) $experiment_id, (string) $from, (string) $to));

    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}
