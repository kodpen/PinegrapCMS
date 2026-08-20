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
$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_key_codes');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_key_codes'][$key] = trim($value);
    }
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_key_codes']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_key_codes']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_key_codes']['sort'] ?? '')) {
    case lang('Key Code'):
        $sort_column = 'key_codes.code';
        break;

    case lang('Offer Code'):
        $sort_column = 'key_codes.offer_code';
        break;

    case lang('Offer Message'):
        $sort_column = 'offers.description';
        break;

    case lang('Enabled'):
        $sort_column = 'key_codes.enabled';
        break;

    case lang('Expiration Date'):
        $sort_column = 'key_codes.expiration_date';
        break;

    case lang('Notes'):
        $sort_column = 'key_codes.notes';
        break;

    case lang('Single-Use'):
        $sort_column = 'key_codes.single_use';
        break;

    case lang('Report'):
        $sort_column = 'key_codes.report';
        break;

    case lang('Last Modified'):
        $sort_column = 'key_codes.timestamp';
        break;

    default:
        $sort_column = 'key_codes.timestamp';
        $_SESSION['software']['ecommerce']['view_key_codes']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_key_codes']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_key_codes']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_key_codes']['order'] = 'asc';
}

$all_key_codes = db_value("SELECT COUNT(*) FROM key_codes");

// If user requested to export, then export them.
if (($_GET['submit_data'] ?? '') == 'Export Key Codes') {

    header("Content-type: text/csv");
    header("Content-disposition: attachment; filename=key_codes.csv");

    // Output column headings for CSV data.
    echo
        '"key_code",' .
        '"offer_code",' .
        '"enabled",' .
        '"expiration_date",' .
        '"notes",' .
        '"single_use",' .
        '"report",' .
        '"last_modified",' .
        '"last_modified_username"' . "\n";

    // Get all key codes.
    $key_codes = db_items(
        "SELECT
            key_codes.code,
            key_codes.offer_code,
            key_codes.enabled,
            key_codes.expiration_date,
            key_codes.notes,
            key_codes.single_use,
            key_codes.report,
            key_codes.timestamp AS last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM key_codes
        LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id
        ORDER BY $sort_column " . e(($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? '')));

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'n/j';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j/n';
    }

    // Loop through the key codes in order to output CSV data.
    foreach ($key_codes as $key_code) {

        $expiration_date = '';

        if ($key_code['expiration_date'] != '0000-00-00') {
            $expiration_date = $key_code['expiration_date'];
        }

        echo
            '"' . escape_csv($key_code['code']) . '",' .
            '"' . escape_csv($key_code['offer_code']) . '",' .
            '"' . $key_code['enabled'] . '",' .
            '"' . $expiration_date . '",' .
            '"' . escape_csv($key_code['notes']) . '",' .
            '"' . $key_code['single_use'] . '",' .
            '"' . $key_code['report'] . '",' .
            '"' . date($month_and_day_format . '/Y g:i:s A T', $key_code['last_modified_timestamp']) . '",' .
            '"' . escape_csv($key_code['last_modified_username']) . '"' . "\n";
    }

    exit;

// Otherwise the user did not select to export gift cards, so just list gift cards.
} else {

    // Get all key codes.
    $key_codes = db_items(
        "SELECT
            key_codes.id,
            key_codes.code,
            key_codes.offer_code,
            key_codes.enabled,
            key_codes.expiration_date,
            key_codes.notes,
            key_codes.single_use,
            key_codes.report,
            last_modified_user.user_username AS last_modified_username,
            key_codes.timestamp AS last_modified_timestamp,
            offers.id AS offer_id,
            offers.description AS offer_description,
            offers.status AS offer_status,
            offers.start_date AS offer_start_date,
            offers.end_date AS offer_end_date
        FROM key_codes
        LEFT JOIN offers ON key_codes.offer_code = offers.code
        LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id
        ORDER BY $sort_column " . e(($_SESSION['software']['ecommerce']['view_key_codes']['order'] ?? '')) . "");

    // Get the current date so that later we can figure out if key codes have expired.
    $current_date = date('Y-m-d');

    // Loop through the key codes in order to set the status for each one,
    // based on enabled and expiration date fields.
    foreach ($key_codes as $key => $key_code) {

        // Default to not active, so that the template can always read this key.
        $key_codes[$key]['status_enabled'] = false;

        // If this key code is active, then store that, so a color can be used to indicate that.
        if (
            $key_code['enabled']
            &&
            (
                ($key_code['expiration_date'] == '0000-00-00')
                || ($key_code['expiration_date'] >= $current_date)
            )
            && ($key_code['offer_status'] == 'enabled')
            && ($key_code['offer_start_date'] <= $current_date)
            && ($key_code['offer_end_date'] >= $current_date)
        ) {
            $key_codes[$key]['status_enabled'] = true;
        }
    }

    echo pg_page_shell([
        'title'=> lang('All Key Codes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Key Codes'),
        'auto_main'=>false,
    ]);

    require('assets/templates/view_key_codes.php');

    echo output_footer();

    $liveform->remove();
}