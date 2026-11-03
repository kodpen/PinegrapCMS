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
    // The button's link carries the session token; a forged link opened by a
    // signed-in user would otherwise overwrite hand-entered rates.
    validate_token_field();
    $user_id = $user['id'];
}

include_once('liveform.class.php');
$liveform = new liveform('view_currencies');

// The rates are written through the shared refresh, which the API's
// POST /store/currencies/rates runs as well.
$refresh = pg_store_currency_rates_refresh($user_id);
$wanted = $refresh['wanted'];
$updated = $refresh['updated'];

// Neither feed answered: say so once, beside the per-currency marks below.
if ($wanted && !$refresh['reached']) {
    $liveform->mark_error('exchange_rates', lang('The exchange rate services could not be reached, so the exchange rates were not changed.'));
}

foreach ($refresh['failed'] as $code) {
    $liveform->mark_error('currency_' . $code, lang(array(
        'string' => 'Failed to update exchange rate for currency {var:1}.',
        'vars' => array(h($code))
    )));
}

// One Site Log line per run, from the button and the scheduled job alike;
// log_activity() names the signed-in user, a background run has none.
$log_line = pg_exchange_rates_log_line($updated, count($wanted));

if ($log_line) {
    log_activity(pg_log_lang($log_line));
}

// Scheduled-task health: record that this job finished. See pg_cron_ran().
// Placed before the redirect so a run started from the settings screen counts
// too - the rates got refreshed either way, which is what the record means.
pg_cron_ran('update_exchange_rates');

// Redirect if needed
if (($_GET['send_to'] ?? '')) {
    // A notice only for rates really written; failures are already marked above.
    if ($updated > 0) {
        $liveform->add_notice(lang(array(
            'string' => '{var:1} exchange rates were updated.',
            'vars' => array($updated)
        )));
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($_GET['send_to'] ?? '', PATH . SOFTWARE_DIRECTORY . '/view_currencies.php'));
    exit();
}

