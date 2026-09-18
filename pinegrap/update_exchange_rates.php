<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Refresh the store's exchange rates from the public feeds.
 *
 * Two outputs from one fetch. currencies.exchange_rate is what the store shows
 * a visitor and is overwritten every run, as it always was. currency_rates is
 * the dated history the ERP books against: today's row for the base and every
 * currency the store lists or the ERP offers, kept in the ledger's direction
 * (base units per unit of the currency). Fetching happens in
 * includes/fn/currency_rates.php; this script only decides what to ask for
 * and where to write it.
 *
 * Runs from the job dispatcher, from a crontab line, or from the Update
 * Exchange Rates button on the currencies screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may manage currencies, the same gate view_currencies.php uses; without
// it an anonymous GET rewrote every exchange rate from the public feeds.
if (pg_cron_is_background_run()) {
    $user_id = 0;
} else {
    $user = validate_user();
    validate_ecommerce_access($user);
    $user_id = $user['id'];
}

include_once('liveform.class.php');
$liveform = new liveform('view_currencies');

$base_code = strtoupper((string) BASE_CURRENCY_CODE);
$today = date('Y-m-d');

// The store's own list, keyed by code.
$store_currencies = array();

foreach ((array) db_items("SELECT id, code FROM currencies") as $row) {
    $store_currencies[strtoupper(trim((string) $row['code']))] = (int) $row['id'];
}

// The ERP's document currencies, which the store need not list for sale.
$wanted = array_keys($store_currencies);

if (defined('ERP_FX_CURRENCIES') && (trim((string) ERP_FX_CURRENCIES) !== '')) {
    foreach (explode(',', (string) ERP_FX_CURRENCIES) as $code) {
        $wanted[] = strtoupper(trim($code));
    }
}

$wanted = array_values(array_unique(array_filter($wanted, function ($code) use ($base_code) {
    return ($code !== '') && ($code !== $base_code);
})));

// The base currency is 1 to itself, in both tables.
if (isset($store_currencies[$base_code])) {
    db("UPDATE currencies SET
            exchange_rate = '1.00000',
            last_modified_user_id = '" . (int) $user_id . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $store_currencies[$base_code] . "'");
}

if (pg_currency_rates_table_exists()) {
    pg_currency_rate_store($today, $base_code, $base_code, 1, 'base');
}

$fetched = pg_currency_rates_fetch($base_code, $wanted);

foreach ($wanted as $code) {

    if (!isset($fetched[$code])) {
        $liveform->mark_error('currency_' . $code, lang(array(
            'string' => 'Failed to update exchange rate for currency {var:1}',
            'vars' => array($code)
        )));
        continue;
    }

    // Units of the currency per base unit, as the feeds and the store both count.
    $store_rate = (float) $fetched[$code]['rate'];

    if (isset($store_currencies[$code])) {
        db("UPDATE currencies SET
                exchange_rate = '" . escape(number_format($store_rate, 5, '.', '')) . "',
                last_modified_user_id = '" . (int) $user_id . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $store_currencies[$code] . "'");
    }

    // The history keeps the reciprocal: base units per unit of the currency,
    // which is what an amount on a document is multiplied by.
    if (pg_currency_rates_table_exists()) {
        pg_currency_rate_store($today, $base_code, $code, 1 / $store_rate, (string) $fetched[$code]['source']);
    }
}

// Scheduled-task health: record that this job finished. See pg_cron_ran().
// Placed before the redirect so a run started from the settings screen counts
// too - the rates got refreshed either way, which is what the record means.
pg_cron_ran('update_exchange_rates');

// Redirect if needed
if (($_GET['send_to'] ?? '')) {
    $liveform->add_notice(lang('The exchange rates have been updated.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . ($_GET['send_to'] ?? ''));
    exit();
}

