<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - e-Fatura threshold warning. A seller in Turkey outside e-Fatura must
 * join it from the first day of the seventh month of the year after its gross
 * sales reached a threshold: a lower one for sales over the internet and a
 * general one for every sale. This file weighs the calendar year's sales
 * against both thresholds; the amounts are kept in minor units (kurus).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Share of a threshold, in percent, at which the warning starts.
 */
const ERP_EFATURA_THRESHOLD_NEAR = 80;

/**
 * Weighs a year's gross sales against the thresholds.
 *
 * A threshold of 0 or less is switched off and gets no row. The threshold
 * amount itself counts as reached ("or more"). A negative sum (returns larger
 * than sales) is read as nothing sold.
 *
 * @param array      $sales   'web' (sales over the internet) and 'all' (every
 *                            sale), minor units
 * @param array      $limits  'web' and 'general' thresholds, minor units
 * @param int|string $year    the calendar year the sales belong to
 *
 * @return array  one row per threshold switched on, internet first: key
 *                ('web'|'general'), limit, sales, share (whole percent, cut
 *                down so that 79.9 does not read as 80), state
 *                ('reached'|'near'|'below'), deadline (Y-m-d the move falls
 *                due if the threshold is reached this year)
 */
function erp_efatura_threshold_rows($sales, $limits, $year)
{
    $rows = array();
    $deadline = sprintf('%04d-07-01', ((int) $year) + 1);

    foreach (array('web' => 'web', 'general' => 'all') as $key => $sales_key) {

        $limit = (int) ($limits[$key] ?? 0);

        if ($limit <= 0) {
            continue;
        }

        $amount = max(0, (int) ($sales[$sales_key] ?? 0));

        // Whole minor units on both sides: 3 Milyon TL is 3e8 kurus, so the
        // products stay far inside a 64-bit integer.
        if ($amount >= $limit) {
            $state = 'reached';
        } elseif (($amount * 100) >= ($limit * ERP_EFATURA_THRESHOLD_NEAR)) {
            $state = 'near';
        } else {
            $state = 'below';
        }

        $rows[] = array(
            'key'      => $key,
            'limit'    => $limit,
            'sales'    => $amount,
            'share'    => intdiv($amount * 100, $limit),
            'state'    => $state,
            'deadline' => $deadline,
        );
    }

    return $rows;
}

/**
 * The most urgent state among the rows: 'reached', 'near', 'below', or ''
 * when no threshold is switched on.
 *
 * @param array $rows  erp_efatura_threshold_rows()
 *
 * @return string
 */
function erp_efatura_threshold_worst($rows)
{
    $order = array('' => 0, 'below' => 1, 'near' => 2, 'reached' => 3);
    $worst = '';

    foreach ($rows as $row) {
        $state = (string) ($row['state'] ?? '');

        if (isset($order[$state]) && ($order[$state] > $order[$worst])) {
            $worst = $state;
        }
    }

    return $worst;
}
