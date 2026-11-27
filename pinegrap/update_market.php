<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's country list (pg_market_country_form()) posts here: the
 * country the visitor is priced by is kept in the session, so the market
 * holding it decides the currency on every later page (init.php). An empty
 * choice is "Other countries": the base currency. Back to the page the list
 * was on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
pg_post_body_discarded();

validate_token_field();

$country = $_POST['market_country'] ?? null;

// Only a country one of the enabled markets holds is kept as a code; any
// other value is "Other countries". A site without markets keeps nothing.
if (pg_markets_on() && is_scalar($country)) {
    $country = strtoupper(trim((string) $country));
    $market = pg_market_for_country(pg_market_site_markets(), $country);
    $_SESSION['ecommerce']['market_country'] = ($market !== null) ? $country : '';

    // The cart's order shows the market's currency from now on.
    if (!empty($_SESSION['ecommerce']['order_id'])) {
        $currency_code = ($market !== null) ? $market['currency_code'] : BASE_CURRENCY_CODE;
        db_q("UPDATE orders SET currency_code = ? WHERE id = ? AND status = 'incomplete'",
            array((string) $currency_code, (string) $_SESSION['ecommerce']['order_id']));
    }
}

header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
exit();
