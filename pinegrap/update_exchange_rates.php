<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user_id = (($_GET['send_to'] ?? '')) ? validate_user()['id'] : 0;

include_once('liveform.class.php');
$liveform = new liveform('view_currencies');


// Helper: safer HTTP request using curl
function fetch_url($url) {
    $ch = curl_init($url);
    // Identify this installation on outgoing requests. Sent with no
    // User-Agent, a request looks like an anonymous client to the receiving
    // server's firewall and gets rejected — which is how Pinegrap ended up
    // blocking its own licence and update checks.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Frankfurter supports only these currencies
$frankfurter_supported = array(
    'USD','EUR','GBP','CHF','JPY','AUD','CAD','SEK','NOK','DKK','PLN','HUF','CZK','RON','BGN','HRK'
);

// Get currency list
$query = "SELECT id, code FROM currencies";
$results = mysqli_query(db::$con, $query) or output_error('Query failed.');

while ($row = mysqli_fetch_assoc($results)) {
    $currency_id = $row['id'];
    $currency_code = $row['code'];
    $exchange_rate = null;

    // Skip base currency
    if ($currency_code == BASE_CURRENCY_CODE) {
        $query = "UPDATE currencies SET
                    exchange_rate = '1.00000',
                    last_modified_user_id = '$user_id',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                  WHERE id = '$currency_id'";
        mysqli_query(db::$con, $query);
        continue;
    }

    // Try Frankfurter only if both currencies are supported
    if (in_array(BASE_CURRENCY_CODE, $frankfurter_supported) && in_array($currency_code, $frankfurter_supported)) {
        $url = 'https://api.frankfurter.app/latest?from=' . BASE_CURRENCY_CODE . '&to=' . $currency_code;
        $responseJson = fetch_url($url);
        if ($responseJson !== false) {
            $response = json_decode($responseJson);
            if (isset($response->rates->{$currency_code})) {
                $exchange_rate = trim($response->rates->{$currency_code});
            }
        }
    }

    // Fallback to HexaRate
    if (!$exchange_rate || floatval($exchange_rate) <= 0) {
        $url = 'https://hexarate.paikama.co/api/rates/latest/' . BASE_CURRENCY_CODE . '?target=' . $currency_code;
        $responseJson = fetch_url($url);
        if ($responseJson !== false) {
            $response = json_decode($responseJson);
            if (isset($response->data->mid)) {
                $exchange_rate = trim($response->data->mid);
            }
        }
    }

    // Update DB if valid rate found
    if ($exchange_rate && floatval($exchange_rate) > 0) {
        $query = "UPDATE currencies SET
                    exchange_rate = '" . mysqli_real_escape_string(db::$con, $exchange_rate) . "',
                    last_modified_user_id = '$user_id',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                  WHERE id = '$currency_id'";
        mysqli_query(db::$con, $query);
    } else {
        $liveform->mark_error('currency_' . $currency_code, lang(array(
            'string' => 'Failed to update exchange rate for currency {var:1}',
            'vars' => array($currency_code)
        )));
    }
}

// Scheduled-task health: record that this job finished. See pg_cron_ran().
// Placed before the redirect so a run started from the settings screen counts
// too — the rates got refreshed either way, which is what the record means.
pg_cron_ran('update_exchange_rates');

// Redirect if needed
if (($_GET['send_to'] ?? '')) {
    $liveform->add_notice(lang('The exchange rates have been updated.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . ($_GET['send_to'] ?? ''));
    exit();
}
?>