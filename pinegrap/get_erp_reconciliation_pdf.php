<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the account reconciliation letter as a PDF (or, with ?html=1, the
 * HTML it is made from).
 *
 * ?id= is the account. as_of, from (Y-m-d) and reply_days shape the letter;
 * anything missing or unusable takes the default (today, the first of the
 * year, seven days). The letter screen posts the same fields with its form
 * token, so a bookmarked address and a filled-in form produce the same page.
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

if ($_POST) {
    validate_token_field();
}

$account_id = (int) ($_REQUEST['id'] ?? 0);
$preview_template = null;

// A draft template from the settings form: the settings gate, tried on the
// account that moved last when none is named.
if ($_POST && isset($_POST['template'])) {
    if (!validate_erp_access($user, 'settings')) {
        exit();
    }

    $preview_template = str_replace("\r\n", "\n", (string) $_POST['template']);

    if ($account_id <= 0) {
        $account_id = (int) db_value("SELECT account_id FROM erp_account_transactions ORDER BY id DESC LIMIT 1");
    }
}

$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(($preview_template !== null) ? lang('There is no account movement to preview the letter with yet.') : lang('The account could not be found.'));
    exit();
}

// The screen's dates come in the site's format; a link's come as Y-m-d. Both
// end up as Y-m-d or empty, and empty means the default.
$read_date = function ($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    return validate_date($value) ? (string) prepare_form_data_for_input($value, 'date') : '';
};

$options = erp_reconciliation_options(array(
    'as_of' => $read_date($_REQUEST['as_of'] ?? ''),
    'from' => $read_date($_REQUEST['from'] ?? ''),
    'reply_days' => isset($_REQUEST['reply_days']) ? (int) $_REQUEST['reply_days'] : 7,
));

$html = erp_reconciliation_html($account_id, $options, $preview_template);

if ($html === false) {
    output_error(lang('The account could not be found.'));
    exit();
}

if (($preview_template !== null) || !empty($_REQUEST['html'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo $html;
    exit();
}

$pdf = erp_invoice_pdf($html);

if ($pdf === false) {
    output_error(lang('The PDF library is not installed.'));
    exit();
}

$file_name = erp_reconciliation_file_name($account_id, $options['as_of']);
$disposition = !empty($_REQUEST['download']) ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $file_name . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo $pdf;
exit();
