<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the documents as they were issued, kept on disk.
 *
 * An invoice or a delivery note is rendered from its row and a template, and
 * the template, the logo and the seller's address can all change after the
 * document went out. The copy kept here is the PDF as it was on the day it
 * was issued; the screens and the file manager open that copy, and a live
 * rendering is only made for a document that has none yet (one issued before
 * this existed - its first opening keeps it) or on request (?html=1, the
 * template preview).
 *
 * Also kept: the provider's official copy of an e-document once GİB has
 * accepted it (it no longer changes, and fetching it again costs a call and
 * needs the provider to be up), and every reconciliation letter that was
 * e-mailed, with the letter that went.
 *
 * Storage is the file manager's own: the file directory (FILE_DIRECTORY_PATH)
 * and a row in `files`, with no folder (folder = 0, which no listing shows)
 * and files.erp_doc_type / erp_doc_id saying which document it is (4.65).
 * The name carries a random part, and get_file.php refuses an ERP file to
 * anyone without the ERP right: an invoice is personal data, and a file in
 * the file directory is otherwise served to whoever knows its address.
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
 * Whether the 4.65 columns are there.
 *
 * @return bool
 */
function erp_archive_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('files', 'erp_doc_type');
    }

    return $ready;
}

/**
 * The kept file of a document, or null.
 *
 * @param string $doc_type  'invoice' | 'waybill' | 'edoc' | 'reconciliation' | 'expense'
 * @param int    $doc_id
 * @return array|null  files row plus 'path'
 */
function erp_archive_file($doc_type, $doc_id)
{
    if (!erp_archive_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM files
        WHERE erp_doc_type = '" . escape((string) $doc_type) . "' AND erp_doc_id = '" . (int) $doc_id . "'
        ORDER BY id DESC LIMIT 1");

    if (!is_array($row)) {
        return null;
    }

    $row['path'] = FILE_DIRECTORY_PATH . '/' . $row['name'];

    // A row whose file has gone is no copy; the caller renders again.
    return is_file($row['path']) ? $row : null;
}

/**
 * Keeps a document's bytes. A document that already has a copy keeps it:
 * the first copy is the one that was issued.
 *
 * $again is for the one kind of document that is a picture of paper rather
 * than something the ERP printed: an expense's receipt, photographed again.
 * The new copy is kept beside the earlier one and stands in for it (the
 * newest row is the document's file); the earlier one stays, as the
 * document's history.
 *
 * @param string $doc_type
 * @param int    $doc_id
 * @param string $bytes
 * @param string $label      The document number, for the file name
 * @param string $extension  'pdf'
 * @param int    $user_id
 * @param bool   $again      Keep a new copy even though one is kept
 * @return array|null  files row plus 'path', or null when it could not be kept
 */
function erp_archive_store($doc_type, $doc_id, $bytes, $label, $extension = 'pdf', $user_id = 0, $again = false)
{
    if (!erp_archive_ready() || ((string) $bytes === '')) {
        return null;
    }

    $existing = erp_archive_file($doc_type, $doc_id);

    if (($existing !== null) && !$again) {
        return $existing;
    }

    // erp-<type>-<number>-<random>.pdf: the prefix is what get_file.php looks
    // for before it asks the database, and the random part keeps the address
    // from being guessed from the invoice number.
    $label = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $label), '_');
    $name = 'erp-' . preg_replace('/[^a-z]/', '', (string) $doc_type) . '-' . (($label !== '') ? $label : (int) $doc_id)
        . '-' . substr(bin2hex(random_bytes(8)), 0, 12) . '.' . preg_replace('/[^a-z0-9]/', '', strtolower((string) $extension));

    if (function_exists('get_unique_name')) {
        $name = get_unique_name(array('name' => $name, 'type' => 'file'));
    }

    $directory = FILE_DIRECTORY_PATH;
    $path = $directory . '/' . $name;

    // is_writable() is not asked: on Windows it reads the folder's read-only
    // attribute, which says nothing about whether PHP may write in it. The
    // write itself is the answer.
    if (!is_dir($directory)) {
        return null;
    }

    // Temp file plus rename, as every other writer of the file directory
    // does: a half-written PDF is worse than none, because it looks like one.
    $temp = $path . '.part';

    if ((@file_put_contents($temp, (string) $bytes) === false) || !@rename($temp, $path)) {
        @unlink($temp);
        return null;
    }

    $inserted = db("INSERT INTO files (name, folder, type, size, user, timestamp, description, design, attachment, optimized, erp_doc_type, erp_doc_id)
        VALUES (
            '" . escape($name) . "',
            '0',
            '" . escape(substr(strtolower((string) $extension), 0, 4)) . "',
            '" . (int) strlen((string) $bytes) . "',
            '" . (int) $user_id . "',
            '" . time() . "',
            '',
            '0',
            '0',
            '1',
            '" . escape((string) $doc_type) . "',
            '" . (int) $doc_id . "')");

    if ($inserted === false) {
        @unlink($path);
        return null;
    }

    return erp_archive_file($doc_type, $doc_id);
}

/**
 * Every copy kept for a document, newest first: the one in use, then the
 * ones it replaced. A row whose file has gone from the disk is listed with
 * on_disk false rather than left out, so the history does not skip a step.
 *
 * @param string $doc_type
 * @param int    $doc_id
 * @return array  files rows plus 'path', 'on_disk' and 'username'
 */
function erp_archive_files($doc_type, $doc_id)
{
    if (!erp_archive_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT f.*, u.user_username AS username
        FROM files f
        LEFT JOIN user u ON u.user_id = f.user
        WHERE f.erp_doc_type = '" . escape((string) $doc_type) . "' AND f.erp_doc_id = '" . (int) $doc_id . "'
        ORDER BY f.id DESC");

    foreach ($rows as $key => $row) {
        $rows[$key]['path'] = FILE_DIRECTORY_PATH . '/' . $row['name'];
        $rows[$key]['on_disk'] = is_file($rows[$key]['path']);
    }

    return $rows;
}

/**
 * Keeps an issued invoice's PDF. A draft has no document; a cancelled one
 * keeps the copy it was issued with.
 *
 * @param int $invoice_id
 * @param int $user_id
 * @return array|null
 */
function erp_archive_invoice($invoice_id, $user_id = 0)
{
    $invoice = db_item("SELECT id, full_number, status FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice) || ((string) $invoice['status'] === 'draft') || ((string) $invoice['full_number'] === '')) {
        return null;
    }

    $existing = erp_archive_file('invoice', (int) $invoice_id);

    if ($existing !== null) {
        return $existing;
    }

    $html = erp_invoice_html((int) $invoice_id);
    $pdf = ($html !== false) ? erp_invoice_pdf($html) : false;

    return ($pdf !== false) ? erp_archive_store('invoice', (int) $invoice_id, $pdf, (string) $invoice['full_number'], 'pdf', $user_id) : null;
}

/**
 * Keeps an issued delivery note's PDF. A cancelled note is not kept: its
 * rendering carries the cancelled mark, the kept copy would not.
 *
 * @param int $waybill_id
 * @param int $user_id
 * @return array|null
 */
function erp_archive_waybill($waybill_id, $user_id = 0)
{
    $waybill = db_item("SELECT id, full_number, status FROM erp_waybills WHERE id = '" . (int) $waybill_id . "' LIMIT 1");

    if (!is_array($waybill) || ((string) $waybill['status'] !== 'issued')) {
        return null;
    }

    $existing = erp_archive_file('waybill', (int) $waybill_id);

    if ($existing !== null) {
        return $existing;
    }

    $html = erp_waybill_html((int) $waybill_id);
    $pdf = ($html !== false) ? erp_invoice_pdf($html) : false;

    return ($pdf !== false) ? erp_archive_store('waybill', (int) $waybill_id, $pdf, (string) $waybill['full_number'], 'pdf', $user_id) : null;
}

/**
 * Keeps the provider's copy of an e-document, once GİB has accepted it and
 * only when what came back is a PDF (İşbaşı hands an HTML rendering back for
 * a document GİB has not got yet).
 *
 * @param int    $invoice_id
 * @param string $bytes  What the provider returned
 * @param string $mime
 * @return array|null
 */
function erp_archive_edoc($invoice_id, $bytes, $mime)
{
    $invoice = db_item("SELECT id, full_number, gib_number, edoc_status FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice) || ((string) $invoice['edoc_status'] !== 'accepted')
        || (strpos((string) $mime, 'application/pdf') === false) || (strncmp((string) $bytes, '%PDF', 4) !== 0)) {
        return null;
    }

    $label = ((string) $invoice['gib_number'] !== '') ? (string) $invoice['gib_number'] : (string) $invoice['full_number'];

    return erp_archive_store('edoc', (int) $invoice_id, $bytes, $label, 'pdf', 0);
}

/**
 * Records a reconciliation letter that was e-mailed, with the PDF that went.
 *
 * @param int    $account_id
 * @param array  $options  From erp_reconciliation_options()
 * @param string $sent_to
 * @param string $pdf
 * @param int    $user_id
 * @return int  The log row, or 0
 */
function erp_archive_reconciliation($account_id, $options, $sent_to, $pdf, $user_id = 0)
{
    if (!erp_archive_ready() || !function_exists('waf_table_has_column') || !waf_table_has_column('erp_reconciliation_log', 'account_id')) {
        return 0;
    }

    $balance = erp_reconciliation_balance((int) $account_id, (string) $options['as_of']);
    $reference = erp_reconciliation_reference((int) $account_id, (string) $options['as_of']);

    $inserted = db("INSERT INTO erp_reconciliation_log (account_id, reference, as_of, from_date, reply_days, balance_base, sent_to, created_by, created_at)
        VALUES (
            '" . (int) $account_id . "',
            '" . escape(mb_substr((string) $reference, 0, 64)) . "',
            '" . escape((string) $options['as_of']) . "',
            '" . escape(((string) ($options['from'] ?? '') !== '') ? (string) $options['from'] : '0000-00-00') . "',
            '" . (int) ($options['reply_days'] ?? 0) . "',
            '" . (int) ($balance['base'] ?? 0) . "',
            '" . escape(mb_substr((string) $sent_to, 0, 255)) . "',
            '" . (int) $user_id . "',
            '" . time() . "')");

    if ($inserted === false) {
        return 0;
    }

    $log_id = (int) mysqli_insert_id(db::$con);
    erp_archive_store('reconciliation', $log_id, $pdf, $reference, 'pdf', $user_id);

    return $log_id;
}

/**
 * Sends a kept file to the browser and stops.
 *
 * @param array  $file         From erp_archive_file()
 * @param string $name         The file name the browser saves it as
 * @param bool   $download
 * @return void
 */
function erp_archive_send($file, $name, $download = false)
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $name) . '"');
    header('Content-Length: ' . filesize($file['path']));
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');
    readfile($file['path']);
    exit();
}

/**
 * Asks for a document to be kept once this request is over.
 *
 * Called where the document is announced (erp_event_invoice(),
 * erp_event_waybill()), which is inside the writer's transaction; the
 * rendering waits for the end of the request, when what was committed can
 * be read back, and a writer that rolled back leaves nothing to keep.
 *
 * @param string $doc_type  'invoice' | 'waybill'
 * @param int    $doc_id
 * @return void
 */
function erp_archive_defer($doc_type, $doc_id)
{
    static $queue = null;

    if (!erp_archive_ready() || ((int) $doc_id <= 0)) {
        return;
    }

    if ($queue === null) {
        $queue = array();
        register_shutdown_function(function () use (&$queue) {
            foreach ($queue as $key => $entry) {
                if ($entry[0] === 'invoice') {
                    erp_archive_invoice($entry[1], $entry[2]);
                } elseif ($entry[0] === 'waybill') {
                    erp_archive_waybill($entry[1], $entry[2]);
                }
            }
        });
    }

    $queue[$doc_type . ':' . (int) $doc_id] = array((string) $doc_type, (int) $doc_id, (defined('USER_ID') ? (int) USER_ID : 0));
}
