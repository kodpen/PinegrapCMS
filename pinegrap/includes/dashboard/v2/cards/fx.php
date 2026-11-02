<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Exchange rates card: each currency the store takes besides its base,
 * with what one unit costs in the base currency, the change over the last
 * seven days and a line of those days. The currencies are the ones
 * dashboard widget 16 lists (pg_dashboard_currencies()); the days come from
 * the dated currency_rates history the daily rate job writes.
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
 * The card's rows. A day the feeds published nothing (a weekend, a holiday)
 * takes the last earlier rate, as an invoice of that day would; days before
 * the first recorded rate are left out of the line.
 *
 * @param array $currencies pg_dashboard_currencies() rows
 * @param array $history    code => Y-m-d => base units per unit
 * @param array $dates      the seven days, oldest first
 * @param string $base_symbol
 * @return array[] code, name, rate (text), change (text), tone, points
 */
function pg_dashboard_v2_fx_rows($currencies, $history, $dates, $base_symbol)
{
    $rows = array();

    foreach ($currencies as $currency) {
        if ((int) $currency['base'] === 1) {
            continue;
        }

        $code = strtoupper((string) $currency['code']);
        $days = (array) ($history[$code] ?? array());
        ksort($days);

        $points = array();

        foreach ($dates as $date) {
            $rate = null;

            foreach ($days as $day => $value) {
                if ($day <= $date) {
                    $rate = (float) $value;
                }
            }

            if ($rate !== null) {
                $points[] = $rate;
            }
        }

        $stored = (float) $currency['exchange_rate'];
        $now = ($stored > 0) ? (1 / $stored) : (empty($points) ? 0 : end($points));

        $change = '';
        $tone = '';

        if ((count($points) > 1) && ($points[0] > 0)) {
            $pct = round(((end($points) - $points[0]) / $points[0]) * 100, 2);

            if ($pct > 0) {
                $change = lang(array('string' => 'up {var:1}%', 'vars' => pg_format_number($pct, 2)));
                $tone = 'up';
            } elseif ($pct < 0) {
                $change = lang(array('string' => 'down {var:1}%', 'vars' => pg_format_number(-$pct, 2)));
                $tone = 'down';
            } else {
                $change = lang('no change');
            }
        }

        $rows[] = array(
            'code'   => $code,
            'name'   => pg_currency_label((string) $currency['name']),
            'rate'   => ($now > 0) ? pg_format_number($now, 4) . ' ' . $base_symbol : '-',
            'change' => $change,
            'tone'   => $tone,
            'points' => $points,
            'url'    => 'edit_currency.php?id=' . (int) $currency['id'],
        );
    }

    return $rows;
}

/**
 * The recorded rates of the last days, code => date => rate. Read from a
 * week before the first day too, so the first day has a rate to carry.
 *
 * @param string $base_code
 * @param array  $dates oldest first
 * @return array
 */
function pg_dashboard_v2_fx_history($base_code, $dates)
{
    $history = array();

    if (($base_code === '') || empty($dates) || !pg_currency_rates_table_exists()) {
        return $history;
    }

    $from = date('Y-m-d', strtotime('-7 days', strtotime((string) reset($dates))));

    foreach ((array) db_items("SELECT rate_date, currency_code, rate FROM currency_rates
        WHERE base_code = '" . escape($base_code) . "'
            AND rate_date >= '" . escape($from) . "'
            AND rate_date <= '" . escape((string) end($dates)) . "'") as $row) {
        $history[strtoupper((string) $row['currency_code'])][(string) $row['rate_date']] = (float) $row['rate'];
    }

    return $history;
}

function pg_dashboard_v2_card_fx($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_16.php');

    $currencies = pg_dashboard_currencies();
    $base_code = '';

    foreach ($currencies as $currency) {
        if ((int) $currency['base'] === 1) {
            $base_code = strtoupper((string) $currency['code']);
        }
    }

    $dates = array();

    for ($i = 6; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime('-' . $i . ' days'));
    }

    $rows = pg_dashboard_v2_fx_rows($currencies, pg_dashboard_v2_fx_history($base_code, $dates), $dates, html_entity_decode((string) BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return array('status' => 'success', 'data' => array(
        'note'       => lang('Updated every day · last 7 days'),
        'rows'       => $rows,
        'line_label' => lang('Rate over the last 7 days'),
        'empty'      => empty($rows) ? lang('No other currencies are set up.') : '',
        'url'        => 'view_currencies.php',
    ));
}
