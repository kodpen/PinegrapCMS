<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - money.
 *
 * Every multiplication and every rounding in the module happens here. Amounts
 * are whole kurus in integers, the way the rest of the software stores money;
 * rates are decimals because they are rates. Nothing in the module is allowed
 * to multiply an amount by a rate on its own - one rounding rule in one place
 * is what makes a ledger tie to the documents drawn from it.
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
 * Read an amount typed by an operator and return whole kurus.
 *
 * Accepts both separators in either role: 1.234,56 and 1,234.56 both mean the
 * same amount, and the last separator with two digits behind it is the decimal
 * one. Anything that is not a digit or a separator is dropped, so a pasted
 * currency symbol does not turn the figure into zero.
 *
 * @param string|int|float $value
 * @return int  Kurus, negative when the input carried a minus sign
 */
function erp_kurus($value)
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return 0;
    }

    $negative = (strpos($raw, '-') !== false);
    $digits = preg_replace('/[^0-9.,]/', '', $raw);

    if ($digits === '') {
        return 0;
    }

    $last_dot = strrpos($digits, '.');
    $last_comma = strrpos($digits, ',');
    $separator = '';

    if (($last_dot !== false) || ($last_comma !== false)) {
        $position = max(($last_dot === false) ? -1 : $last_dot, ($last_comma === false) ? -1 : $last_comma);
        // Two digits behind the last separator makes it the decimal point.
        // Three makes it a thousands separator, which is why 1.234 is one
        // thousand two hundred and thirty four and not one and a bit.
        if ((strlen($digits) - $position - 1) <= 2) {
            $separator = substr($digits, $position, 1);
        }
    }

    if ($separator === '') {
        $whole = preg_replace('/[^0-9]/', '', $digits);
        $fraction = '';
    } else {
        $parts = explode($separator, $digits);
        $fraction = array_pop($parts);
        $whole = preg_replace('/[^0-9]/', '', implode('', $parts));
        $fraction = preg_replace('/[^0-9]/', '', $fraction);
    }

    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
    $kurus = ((int) $whole * 100) + (int) $fraction;

    return $negative ? -$kurus : $kurus;
}

/**
 * Apply a percentage to an amount.
 *
 * The module's only multiplication of money by a rate. Rounding is half away
 * from zero, which is what round() does and what the order side has always
 * done, so a figure worked out here matches the one on the order it came from.
 *
 * @param int   $kurus
 * @param float $rate   Percentage, e.g. 20 for 20%
 * @return int  Kurus
 */
function erp_apply_rate($kurus, $rate)
{
    return (int) round(((int) $kurus) * ((float) $rate) / 100);
}

/**
 * Convert an amount into the base currency at a given exchange rate.
 *
 * The one place a document-currency figure becomes a base-currency one. The
 * ledger, the settlements and the invoice header all call this, so the same
 * amount at the same rate is the same number of kurus everywhere.
 *
 * @param int   $kurus          Amount in the document currency
 * @param float $exchange_rate  Base units per unit of the document currency
 * @return int  Kurus of the base currency
 */
function erp_to_base($kurus, $exchange_rate)
{
    return (int) round(((int) $kurus) * ((float) $exchange_rate));
}

/**
 * A line's amount from its unit price and quantity.
 *
 * Quantities are decimals (1.5 metres), so this is the module's one
 * multiplication of money by a quantity.
 *
 * @param int   $unit_price  Kurus
 * @param float $quantity
 * @return int  Kurus
 */
function erp_line_total($unit_price, $quantity)
{
    return (int) round(((int) $unit_price) * ((float) $quantity));
}

/**
 * Split an amount across lines in proportion to their weights.
 *
 * Used wherever a figure held once on a document has to be shown per line - an
 * order discount, say, which sits on the order header but has to appear against
 * the lines on the invoice.
 *
 * The parts always add up to the total. Each line gets its floor share, and the
 * kurus left over go to the lines with the largest remainders, biggest first;
 * rounding each line on its own would leave the document a kurus or two short
 * of itself.
 *
 * @param int   $total    Kurus to distribute
 * @param array $weights  key => weight (line totals, usually)
 * @return array  key => kurus, summing exactly to $total
 */
function erp_allocate($total, $weights)
{
    $total = (int) $total;
    $shares = array();

    $sum = 0;
    foreach ($weights as $key => $weight) {
        $weight = (int) $weight;
        $shares[$key] = 0;
        $sum += $weight;
    }

    if (($sum <= 0) || empty($weights)) {
        // Nothing to go on. The whole amount lands on the first line rather
        // than disappearing, because a document that does not add up is worse
        // than one with an odd-looking line.
        $keys = array_keys($weights);
        if (!empty($keys)) {
            $shares[$keys[0]] = $total;
        }
        return $shares;
    }

    $remainders = array();
    $allocated = 0;

    foreach ($weights as $key => $weight) {
        $exact = ($total * (int) $weight) / $sum;
        $floor = (int) floor($exact);
        $shares[$key] = $floor;
        $remainders[$key] = $exact - $floor;
        $allocated += $floor;
    }

    $left = $total - $allocated;

    if ($left !== 0) {
        $step = ($left > 0) ? 1 : -1;
        arsort($remainders);
        $order = array_keys($remainders);
        if ($left < 0) {
            $order = array_reverse($order);
        }
        $index = 0;
        $count = count($order);
        while (($left !== 0) && ($count > 0)) {
            $shares[$order[$index % $count]] += $step;
            $left -= $step;
            $index++;
        }
    }

    return $shares;
}

/**
 * Format kurus for the screen, in the base currency.
 *
 * @param int  $kurus
 * @param bool $show_sign  true keeps a leading minus on negative amounts
 * @return string
 */
function erp_money_out($kurus, $show_sign = true)
{
    return erp_money_out_currency($kurus, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $show_sign);
}

/**
 * Format kurus for the screen, in a named currency.
 *
 * The symbol comes from the currencies table (the base symbol for the base
 * currency; the ISO code when the store does not list the currency). No
 * conversion happens here: the amount is already in that currency. The
 * store-side price helpers multiply by the visitor's exchange rate, which is
 * right for a shop window and wrong for a ledger - an operator whose browser
 * had picked another currency for the shop would otherwise read every ERP
 * figure converted, under the wrong symbol.
 *
 * @param int    $kurus
 * @param string $currency_code
 * @param bool   $show_sign  true keeps a leading minus on negative amounts
 * @return string  Plain text, for callers to put through h()
 */
function erp_money_out_currency($kurus, $currency_code, $show_sign = true)
{
    static $symbols = null;

    $kurus = (int) $kurus;
    $currency_code = strtoupper(trim((string) $currency_code));

    if ($symbols === null) {
        $symbols = array();
        foreach ((array) db_items("SELECT code, symbol FROM currencies") as $row) {
            $symbols[strtoupper(trim((string) $row['code']))] = (string) $row['symbol'];
        }
    }

    $symbol = '';
    $suffix = '';

    if (defined('BASE_CURRENCY_CODE') && ($currency_code === strtoupper((string) BASE_CURRENCY_CODE))) {
        $symbol = (string) BASE_CURRENCY_SYMBOL;
    } elseif (isset($symbols[$currency_code]) && (trim($symbols[$currency_code]) !== '')) {
        $symbol = $symbols[$currency_code];
    } else {
        $suffix = ($currency_code !== '') ? (' ' . $currency_code) : '';
    }

    // An entity such as &euro; is written as the character it stands for:
    // the output is plain text that callers escape themselves.
    if (($symbol !== '') && (mb_substr($symbol, 0, 1) === '&')) {
        $symbol = html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $output = $symbol . number_format(abs($kurus) / 100, 2, '.', ',') . $suffix;

    return (($kurus < 0) && $show_sign) ? ('-' . $output) : $output;
}
