<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the recorded exchange rate for a currency on a date.
 *
 * The invoice editor asks here when the issue date or the currency changes,
 * so the empty rate box can show what it will be filled with. Read only; the
 * rate itself comes from the history update_exchange_rates.php writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$currency = strtoupper(trim((string) ($_GET['currency'] ?? '')));
$date = trim((string) ($_GET['date'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$response = array('currency' => $currency, 'date' => $date, 'found' => false, 'rate' => '', 'rate_date' => '', 'source' => '');

if (($currency !== '') && erp_fx_currency_allowed($currency)) {
    $known = erp_fx_rate_for($currency, $date);

    if (is_array($known)) {
        $response['found'] = true;
        $response['rate'] = erp_fx_rate_out((float) $known['rate']);
        $response['rate_date'] = (string) $known['rate_date'];
        $response['source'] = (string) $known['source'];
    }
}

echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
