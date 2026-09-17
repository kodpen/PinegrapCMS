<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Printable evidence receipt for one signature on one submitted form.
 *
 * GET endpoint, read only, no state change and therefore no CSRF token. The
 * page it prints is what gets attached to a contract file: the signature, the
 * document it was given under, and what can still be said about both.
 *
 * Request: GET form_id=N&field_id=N
 *
 * Access is the same question the submitted form screen asks - may this person
 * see this submission - and is answered the same way, because a receipt is that
 * submission in another shape.
 *
 * Why HTML and not a server-side PDF: this software bundles no PDF library, by
 * decision (see order_invoice_print.php). Print to PDF is universal and adds no
 * dependency.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

if (!pg_signature_ready()) {
    output_error(lang('Sorry, the item could not be found.'), 404);
}

$form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
$field_id = isset($_GET['field_id']) ? (int) $_GET['field_id'] : 0;

$record = ($form_id && $field_id) ? pg_signature_record($form_id, $field_id) : false;

if ($record === false) {
    output_error(lang('Sorry, the item could not be found.'), 404);
}

// The folder the custom form lives in decides who may read the submission, the
// same as on the submitted form screen. Roles above manager have to be given
// both the forms permission and edit access to that folder.
$folder_id = (int) db_value(
    "SELECT page.page_folder
    FROM forms
    LEFT JOIN page ON forms.page_id = page.page_id
    WHERE forms.id = '" . e($form_id) . "'");

if (
    ($user['role'] > 2)
    && (($user['manage_forms'] == false) || (check_edit_access($folder_id) == false))
) {
    log_activity(lang('access denied to view signature receipt'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// The token, handed over as a file so it can be checked with something that is
// not this site. "openssl ts -reply -in <file> -token_in -text" reads it, and so
// does any RFC 3161 verifier: that is the whole point of asking a third party
// for the time in the first place.
if (!empty($_GET['token'])) {

    $token = pg_signature_stamp_token($record);

    if ($token === '') {
        output_error(lang('Sorry, the item could not be found.'), 404);
    }

    header('Content-Type: application/timestamp-reply');
    header('Content-Disposition: attachment; filename="imza-' . $form_id . '-' . $field_id . '.tsr"');
    header('Content-Length: ' . strlen($token));

    print $token;
    exit;
}

print pg_signature_receipt_html($record);
