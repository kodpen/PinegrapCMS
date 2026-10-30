<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the public address of an issued invoice's PDF
 * (erp_invoices.public_token_hash, 2026.11.0 step 11.0.65).
 *
 * A marketplace keeps the address of the invoice with the order and shows it
 * to the buyer, who opens it without signing in (erp_invoice.php). The
 * address carries the invoice id and a token of 32 random bytes as hex; only
 * the token's SHA-256 is kept, so the address cannot be read back from the
 * database. A token is made the first time an address is needed; issuing
 * again replaces it, and clearing the hash takes the address back. Nothing
 * is derived from a site secret, so changing one breaks no address (a
 * marketplace asks for the invoice to stay reachable for ten years).
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
 * Whether the 11.0.65 column is there.
 *
 * @return bool
 */
function erp_invoice_public_link_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_invoices', 'public_token_hash');
    }

    return $ready;
}

/**
 * A new token: 32 random bytes as 64 hex characters.
 *
 * @return string
 */
function erp_invoice_public_token_new()
{
    return bin2hex(random_bytes(32));
}

/**
 * The token of an address's t parameter, without the ".pdf" the address ends
 * with (a marketplace can refuse a link whose file is not named as a PDF),
 * or '' when it has not the shape of a token. Pure.
 *
 * @param mixed $value
 * @return string
 */
function erp_invoice_public_token_clean($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = strtolower(preg_replace('/\.pdf$/i', '', trim($value)));

    return (preg_match('/^[0-9a-f]{64}$/D', $value) === 1) ? $value : '';
}

/**
 * What is kept of a token. Pure.
 *
 * @param string $token
 * @return string 64 hex characters
 */
function erp_invoice_public_token_hash($token)
{
    return hash('sha256', (string) $token);
}

/**
 * Why an invoice is not served for a token, or '' when it is. Pure.
 *
 * 'missing' no such invoice (deleted), 'kind' not a sales invoice, 'draft'
 * not issued, 'cancelled', 'none' no public address (never made, or taken
 * back), 'token' a token that is not the invoice's. The page answers every
 * one of them with the same 404.
 *
 * @param array|null $invoice  id, doc_type, status, public_token_hash
 * @param mixed      $token    the t parameter
 * @return string
 */
function erp_invoice_public_refusal($invoice, $token)
{
    if (!is_array($invoice)) {
        return 'missing';
    }

    if ((string) ($invoice['doc_type'] ?? '') !== 'invoice') {
        return 'kind';
    }

    $status = (string) ($invoice['status'] ?? '');

    if ($status === 'draft') {
        return 'draft';
    }

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    $stored = (string) ($invoice['public_token_hash'] ?? '');

    if ($stored === '') {
        return 'none';
    }

    $token = erp_invoice_public_token_clean($token);

    if (($token === '') || !hash_equals($stored, erp_invoice_public_token_hash($token))) {
        return 'token';
    }

    return '';
}

/**
 * The public address of an invoice for a token. Pure but for the site's
 * address constants.
 *
 * @param int    $invoice_id
 * @param string $token
 * @return string
 */
function erp_invoice_public_url($invoice_id, $token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/erp_invoice.php?i=' . (int) $invoice_id . '&t=' . $token . '.pdf';
}

/**
 * Makes a new public address for an issued invoice and answers it, or ''
 * when the invoice cannot have one (not issued, cancelled, not a sales
 * invoice, the column missing). A token made earlier stops working: only
 * its hash was kept, so the address it was in cannot be given again.
 *
 * @param int $invoice_id
 * @return string
 */
function erp_invoice_public_link_issue($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    if (!erp_invoice_public_link_ready() || ($invoice_id <= 0)) {
        return '';
    }

    $token = erp_invoice_public_token_new();
    $hash = erp_invoice_public_token_hash($token);

    db("UPDATE erp_invoices SET public_token_hash = '" . escape($hash) . "'
        WHERE id = '" . $invoice_id . "' AND doc_type = 'invoice' AND status NOT IN ('draft', 'cancelled')");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return '';
    }

    log_activity(lang(array('string' => 'A public address was made for the PDF of invoice {var:1}.', 'vars' => erp_invoice_public_number($invoice_id))), (string) ($_SESSION['sessionusername'] ?? 'ERP'));

    return erp_invoice_public_url($invoice_id, $token);
}

/**
 * Takes an invoice's public address back: the page answers 404 for it from
 * now on, and the next need makes a new one.
 *
 * @param int $invoice_id
 * @return bool whether there was an address to take back
 */
function erp_invoice_public_link_revoke($invoice_id)
{
    if (!erp_invoice_public_link_ready()) {
        return false;
    }

    db("UPDATE erp_invoices SET public_token_hash = '' WHERE id = '" . (int) $invoice_id . "' AND public_token_hash <> ''");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    log_activity(lang(array('string' => 'The public address of the PDF of invoice {var:1} was taken back.', 'vars' => erp_invoice_public_number($invoice_id))), (string) ($_SESSION['sessionusername'] ?? 'ERP'));

    return true;
}

/**
 * The invoice a public address names, or null whatever the reason
 * (erp_invoice_public_refusal()).
 *
 * @param mixed $invoice_id
 * @param mixed $token
 * @return array|null id, full_number
 */
function erp_invoice_public_for_request($invoice_id, $token)
{
    $invoice_id = is_scalar($invoice_id) ? (int) $invoice_id : 0;

    if (!erp_invoice_public_link_ready() || ($invoice_id <= 0) || (erp_invoice_public_token_clean($token) === '')) {
        return null;
    }

    $invoice = db_item("SELECT id, doc_type, status, full_number, public_token_hash FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    return (erp_invoice_public_refusal($invoice, $token) === '') ? $invoice : null;
}

/**
 * Whether an invoice has a public address now.
 *
 * @param int $invoice_id
 * @return bool
 */
function erp_invoice_public_link_exists($invoice_id)
{
    return erp_invoice_public_link_ready()
        && ((string) db_value("SELECT public_token_hash FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1") !== '');
}

/**
 * An invoice's number for a log line.
 *
 * @param int $invoice_id
 * @return string
 */
function erp_invoice_public_number($invoice_id)
{
    return (string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");
}
