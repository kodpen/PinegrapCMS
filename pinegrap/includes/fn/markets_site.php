<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor's market on the site. A site with an enabled market prices a
 * visitor by country: the country chosen in the country list (kept in the
 * session), else the country the visitor is in (pg_geo_country_code()). The
 * market holding that country gives the currency the prices are shown in
 * and the rule that rounds a converted price; a country in no market sees
 * the base currency. The currency picker is hidden on such a site.
 *
 * init.php reads the market once (pg_market_visitor()) and defines
 * VISITOR_MARKET_ID and VISITOR_MARKET_ROUNDING beside the VISITOR_CURRENCY_*
 * constants. A site with no enabled market is exactly as before.
 *
 * Loaded by markets.php.
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
 * An amount in minor units rounded up by a market's rule; a negative amount
 * by its size, so a discount mirrors the price. Pure.
 *
 * none:  as it is
 * whole: up to whole units (12.34 -> 13.00)
 * end99: up to a .99 ending (12.34 -> 12.99, 13.00 -> 13.99)
 *
 * @param int    $minor
 * @param string $rule  pg_market_rounding_code()
 * @return int
 */
function pg_market_round_minor($minor, $rule)
{
    $minor = (int) $minor;
    $size = abs($minor);
    $sign = ($minor < 0) ? -1 : 1;

    if ($size === 0) {
        return 0;
    }

    switch (pg_market_rounding_code($rule)) {
        case 'whole':
            $size = (int) (ceil($size / 100) * 100);
            break;
        case 'end99':
            $size = (int) (ceil(($size + 1) / 100) * 100) - 1;
            break;
    }

    return $sign * $size;
}

/**
 * A base price in a market's currency: at the rate, rounded half away from
 * zero to minor units (never cut), then by the market's rule. Pure.
 *
 * @param int|string   $base_minor Minor units of the base currency
 * @param float|string $rate       Market units for one base unit
 * @param string       $rule
 * @return int  Minor units of the market's currency
 */
function pg_market_price_minor($base_minor, $rate, $rule)
{
    $rate = is_numeric($rate) ? (float) $rate : 0.0;
    $base_minor = is_numeric($base_minor) ? (float) $base_minor : 0.0;

    if ($rate <= 0) {
        return 0;
    }

    return pg_market_round_minor((int) round($base_minor * $rate), $rule);
}

/**
 * The market among $markets that holds a country. Pure.
 *
 * @param array  $markets Rows with 'countries' (two-letter codes)
 * @param string $country
 * @return array|null
 */
function pg_market_for_country(array $markets, $country)
{
    $country = strtoupper(trim((string) $country));

    if ($country === '') {
        return null;
    }

    foreach ($markets as $market) {
        if (in_array($country, (array) $market['countries'], true)) {
            return $market;
        }
    }

    return null;
}

/**
 * The country a visitor is priced by: the one chosen in the country list
 * (the session's 'market_country'; '' is "other countries"), else the one
 * they are in. Pure.
 *
 * @param array  $ecommerce $_SESSION['ecommerce']
 * @param string $geo       pg_geo_country_code()
 * @return array 'country' (two letters or ''), 'chosen' (bool)
 */
function pg_market_visitor_country(array $ecommerce, $geo)
{
    if (array_key_exists('market_country', $ecommerce) && is_scalar($ecommerce['market_country'])) {
        $chosen = strtoupper(trim((string) $ecommerce['market_country']));

        return array('country' => (preg_match('/^[A-Z]{2}$/', $chosen) === 1) ? $chosen : '', 'chosen' => true);
    }

    $geo = strtoupper(trim((string) $geo));

    // T1 is Tor on Cloudflare: no country.
    return array('country' => ((preg_match('/^[A-Z]{2}$/', $geo) === 1) && ($geo !== 'T1')) ? $geo : '', 'chosen' => false);
}

/**
 * The country list's choices: every country of the markets given, by name.
 * A code with no country row is left out. Pure but for the sort.
 *
 * @param array $markets Rows with 'countries' (codes)
 * @param array $names   code => name
 * @return array code => name
 */
function pg_market_country_choices(array $markets, array $names)
{
    $choices = array();

    foreach ($markets as $market) {
        foreach ((array) $market['countries'] as $code) {
            if (isset($names[$code])) {
                $choices[$code] = (string) $names[$code];
            }
        }
    }

    if (class_exists('Collator')) {
        $collator = new Collator(defined('LANGUAGE') ? (string) LANGUAGE : 'en');
        uasort($choices, function ($a, $b) use ($collator) {
            return (int) $collator->compare($a, $b);
        });
    } else {
        asort($choices, SORT_NATURAL | SORT_FLAG_CASE);
    }

    return $choices;
}

/**
 * The enabled markets with their country codes, read once a request.
 *
 * @return array id => id, name, currency_code, rounding, countries (codes)
 */
function pg_market_site_markets()
{
    static $markets = null;

    if ($markets !== null) {
        return $markets;
    }

    $markets = array();

    if (!function_exists('pg_markets_ready') || !pg_markets_ready()) {
        return $markets;
    }

    $rows = db_items(
        "SELECT markets.id, markets.name, markets.currency_code, markets.rounding, countries.code
        FROM markets
        LEFT JOIN market_countries ON market_countries.market_id = markets.id
        LEFT JOIN countries ON countries.id = market_countries.country_id
        WHERE markets.enabled = 1
        ORDER BY markets.sort_order, markets.id");

    foreach ((array) $rows as $row) {
        $id = (int) $row['id'];

        if (!isset($markets[$id])) {
            $markets[$id] = array(
                'id' => $id,
                'name' => (string) $row['name'],
                'currency_code' => strtoupper((string) $row['currency_code']),
                'rounding' => pg_market_rounding_code($row['rounding']),
                'countries' => array(),
            );
        }

        $code = strtoupper(trim((string) $row['code']));

        if ($code !== '') {
            $markets[$id]['countries'][] = $code;
        }
    }

    return $markets;
}

/**
 * Whether the site sells by market: an enabled market exists.
 *
 * @return bool
 */
function pg_markets_on()
{
    return (defined('ECOMMERCE') && ECOMMERCE) && (count(pg_market_site_markets()) > 0);
}

/**
 * The visitor's market, worked out once a request (init.php).
 *
 * @return array 'market' (pg_market_site_markets() row or null), 'country',
 *               'chosen' (the country came from the country list)
 */
function pg_market_visitor()
{
    static $visitor = null;

    if ($visitor !== null) {
        return $visitor;
    }

    $visitor = array('market' => null, 'country' => '', 'chosen' => false);

    if (!pg_markets_on()) {
        return $visitor;
    }

    $ecommerce = (isset($_SESSION['ecommerce']) && is_array($_SESSION['ecommerce'])) ? $_SESSION['ecommerce'] : array();
    $picked = pg_market_visitor_country($ecommerce, function_exists('pg_geo_country_code') ? pg_geo_country_code() : '');

    $visitor = array(
        'market' => pg_market_for_country(pg_market_site_markets(), $picked['country']),
        'country' => $picked['country'],
        'chosen' => $picked['chosen'],
    );

    return $visitor;
}

/**
 * Whether the visitor's prices are a market's: converted and rounded by
 * its rule (init.php found its currency usable).
 *
 * @return bool
 */
function pg_market_pricing()
{
    return defined('VISITOR_MARKET_ID') && (VISITOR_MARKET_ID > 0);
}

/**
 * Notes that the page drawn shows a price for the visitor's country: on a
 * site with markets, a visitor priced by the country they are in (also one
 * in no market, who sees the base currency) gets the kept copy of that
 * country. A country is in one market at most, so every copy is right for
 * each visitor it is served to. A country chosen in the list is in the
 * session, which no cache keeps.
 */
function pg_market_cache_note()
{
    static $noted = false;

    if ($noted || !function_exists('pg_audience_cache_vary') || !pg_markets_on()) {
        return;
    }

    $noted = true;
    $visitor = pg_market_visitor();

    if (!$visitor['chosen']) {
        pg_audience_cache_vary(array('country'));
    }
}

/**
 * A base-currency price shown to the visitor (major units): in a market,
 * converted and rounded by its rule; otherwise pg_visitor_amount(). Never
 * for an amount that is read back (a typed donation): use
 * pg_visitor_amount() there.
 *
 * @param float|int|string $base_amount Major units, base currency
 * @return float
 */
function pg_visitor_price($base_amount)
{
    pg_market_cache_note();
    $display = function_exists('pg_order_display') ? pg_order_display() : null;

    if (($display === null) && pg_market_pricing()) {
        $base_minor = round((is_numeric($base_amount) ? (float) $base_amount : 0.0) * 100);

        return pg_market_price_minor($base_minor, VISITOR_CURRENCY_EXCHANGE_RATE, VISITOR_MARKET_ROUNDING) / 100;
    }

    return pg_visitor_amount($base_amount);
}

/**
 * Whether the site shows the currency picker: multi-currency on and no
 * market (on a site with markets the country list decides the currency).
 *
 * @return bool
 */
function pg_currency_choice_shown()
{
    return defined('ECOMMERCE_MULTICURRENCY') && ECOMMERCE_MULTICURRENCY && !pg_markets_on();
}

/**
 * Prices sent to a page's script in the visitor's currency, so a script
 * that multiplies by pg_market_js_rate() shows each one as the server does.
 * Outside a market, as they are.
 *
 * @param array  $prices Rows or figures (minor units, base)
 * @param string $field  The price's key in a row; '' when $prices are figures
 * @return array
 */
function pg_market_js_prices(array $prices, $field = '')
{
    pg_market_cache_note();

    if (!pg_market_pricing()) {
        return $prices;
    }

    foreach ($prices as $key => $value) {
        if ($field === '') {
            $prices[$key] = pg_market_price_minor($value, VISITOR_CURRENCY_EXCHANGE_RATE, VISITOR_MARKET_ROUNDING);
        } elseif (is_array($value) && isset($value[$field])) {
            $prices[$key][$field] = pg_market_price_minor($value[$field], VISITOR_CURRENCY_EXCHANGE_RATE, VISITOR_MARKET_ROUNDING);
        }
    }

    return $prices;
}

/**
 * The rate a page's script multiplies prices by: 1 in a market, whose
 * prices pg_market_js_prices() sent already converted.
 *
 * @return string
 */
function pg_market_js_rate()
{
    return pg_market_pricing() ? '1' : (string) VISITOR_CURRENCY_EXCHANGE_RATE;
}

/**
 * The country list a visitor changes their market with: the countries of
 * the enabled markets, then "Other countries" (the base currency). Posts to
 * update_market.php. Empty on a site without markets.
 *
 * @param string $send_to Same-host path to return to
 * @param string $style   'classic' (system pages) or 'cart' (the cart widget)
 * @return string HTML
 */
function pg_market_country_form($send_to, $style = 'classic')
{
    if (!pg_markets_on()) {
        return '';
    }

    $names = array();

    foreach ((array) db_items("SELECT name, code FROM countries") as $row) {
        $code = strtoupper(trim((string) $row['code']));
        $names[$code] = function_exists('pg_geo_country_label') ? pg_geo_country_label($row['name'], $code) : (string) $row['name'];
    }

    $choices = pg_market_country_choices(pg_market_site_markets(), $names);
    $visitor = pg_market_visitor();
    $current = ($visitor['market'] !== null) ? $visitor['country'] : '';
    $options = '';

    foreach ($choices as $code => $name) {
        $options .= '<option value="' . h($code) . '"' . (($code === $current) ? ' selected' : '') . '>' . h($name) . '</option>';
    }

    $options .= '<option value=""' . (($current === '') ? ' selected' : '') . '>' . h(lang('Other countries')) . '</option>';

    $action = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software') . '/update_market.php';
    $hidden = get_token_field() . '<input type="hidden" name="send_to" value="' . h($send_to) . '">';

    if ($style === 'cart') {
        return '<form class="pg-cart-currency pg-market-country" method="post" action="' . h($action) . '">' . $hidden
            . '<label class="form-label small mb-1" for="pgMarketCountry">' . h(lang('Country')) . '</label>'
            . '<select class="form-select form-select-sm" id="pgMarketCountry" name="market_country" onchange="this.form.submit()">' . $options . '</select>'
            . '<noscript><button type="submit" class="btn btn-sm btn-outline-secondary mt-1">' . h(lang('Update')) . '</button></noscript>'
            . '</form>';
    }

    return '<form action="' . h($action) . '" method="post" class="currency market_country" style="text-align: right; margin-top: 15px; margin-bottom: 15px">' . $hidden
        . '<label for="software_market_country">' . h(lang('Country')) . '</label> '
        . '<select name="market_country" id="software_market_country" class="software_select">' . $options . '</select> '
        . '<button type="submit" name="submit" value="Update" class="software_input_submit_small_secondary">' . h(lang('Update')) . '</button>'
        . '</form>';
}
