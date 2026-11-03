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
 *               already significant, null when it cannot be said)
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
 * the control, beyond chance), 'more' (not beyond chance yet, a number of
 * views would tell), 'even' (no difference to tell), 'none' (no views yet).
 * Pure.
 *
 * @param array $r pg_experiment_significance()
 * @return string
 */
function pg_experiment_verdict_kind(array $r)
{
    if ($r['p'] === null && $r['z'] === null && (float) $r['rate_a'] === 0.0 && (float) $r['rate_b'] === 0.0 && $r['needed'] === null) {
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
    $lift = ($r['lift'] === null) ? '' : number_format(abs($r['lift']) * 100, 1);

    switch (pg_experiment_verdict_kind($r)) {
        case 'ahead':
            return ($lift === '')
                ? lang(array('string' => '{var:1} is ahead, and the difference is unlikely to be chance.', 'vars' => $key))
                : lang(array('string' => '{var:1} is ahead by {var:2} %, and the difference is unlikely to be chance.', 'vars' => array($key, $lift)));
        case 'behind':
            return lang(array('string' => '{var:1} is behind by {var:2} %, and the difference is unlikely to be chance.', 'vars' => array($key, $lift)));
        case 'more':
            return lang(array('string' => 'Not enough visits yet: about {var:1} more for each version.', 'vars' => number_format((int) $r['needed'])));
        case 'even':
            return lang('Both versions convert at the same rate so far.');
    }

    return lang('No visits counted yet.');
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
