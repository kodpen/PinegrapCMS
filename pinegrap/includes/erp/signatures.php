<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - a signature drawn under a document (8.58): today a quote, signed by
 * the customer on the screen or a tablet in front of them, which marks it
 * accepted and is printed on it.
 *
 * The capture is the one the signature form field uses (includes/fn/
 * signature.php, assets/js/signature_pad.js): the browser sends a PNG data
 * URL, which is decoded and re-encoded through GD before anything is kept.
 * What this produces is an ordinary electronic signature: it is not a
 * qualified signature and does not carry the legal weight of one. Its value
 * is in the record around the drawing:
 *
 *   - the drawing, kept as an ERP document file (files.erp_doc_type
 *     '<doc>_signature', includes/erp/archive.php), served to the ERP right
 *     only;
 *   - document_hash, the SHA-256 of what was signed (the lines and the
 *     totals), so a quote changed afterwards no longer matches;
 *   - image_hash, the SHA-256 of the drawing, so a replaced file no longer
 *     matches;
 *   - seal, an HMAC over the record with the site's key, so an edited row no
 *     longer matches.
 *
 * A signature is evidence: it is never deleted and never redrawn. One per
 * document (UNIQUE (doc_type, doc_id)).
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
 * Whether the 8.58 table is there.
 *
 * @return bool
 */
function erp_signatures_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_signatures', 'seal')
            && function_exists('erp_archive_ready') && erp_archive_ready();
    }

    return $ready;
}

/**
 * The signature of a document, or null.
 *
 * @param string $doc_type  'quote'
 * @param int    $doc_id
 * @return array|null  erp_signatures row plus file_name and username
 */
function erp_signature($doc_type, $doc_id)
{
    if (!erp_signatures_ready() || ((int) $doc_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT s.*, f.name AS file_name, u.user_username AS username
        FROM erp_signatures s
        LEFT JOIN files f ON f.id = s.file_id
        LEFT JOIN user u ON u.user_id = s.user_id
        WHERE s.doc_type = '" . escape((string) $doc_type) . "' AND s.doc_id = '" . (int) $doc_id . "'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * What a quote's signature binds: its lines and its figures, the account,
 * the date it runs to and its number, as one hash. A quote changed after it
 * was signed - a line, a price, the date - hashes differently.
 *
 * @param array $quote  An erp_quotes row
 * @return string  64 hex characters
 */
function erp_signature_document_hash_quote($quote)
{
    $lines = json_decode((string) ($quote['line_data'] ?? ''), true);

    $document = array(
        'line_data' => is_array($lines) ? $lines : array(),
        'subtotal' => (int) ($quote['subtotal'] ?? 0),
        'discount_total' => (int) ($quote['discount_total'] ?? 0),
        'tax_total' => (int) ($quote['tax_total'] ?? 0),
        'withholding_total' => (int) ($quote['withholding_total'] ?? 0),
        'grand_total' => (int) ($quote['grand_total'] ?? 0),
        'currency' => strtoupper((string) ($quote['currency'] ?? '')),
        'account_id' => (int) ($quote['account_id'] ?? 0),
        'valid_until' => (string) ($quote['valid_until'] ?? ''),
        'full_number' => (string) ($quote['full_number'] ?? ''),
    );

    return hash('sha256', json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * The seal over a stored record: everything that would change what the
 * signature means, except the row's own id, which carries no meaning.
 * ENCRYPTION_KEY never leaves the server, so a seal cannot be recomputed by
 * someone who only has the database.
 *
 * @param array $record
 * @return string  64 hex characters
 */
function erp_signature_seal($record)
{
    $material = array(
        (string) ($record['doc_type'] ?? ''),
        (int) ($record['doc_id'] ?? 0),
        (int) ($record['file_id'] ?? 0),
        (string) ($record['image_hash'] ?? ''),
        (string) ($record['document_hash'] ?? ''),
        (string) ($record['signer_name'] ?? ''),
        (int) ($record['signed_at'] ?? 0),
        (string) ($record['ip_address'] ?? ''),
        (string) ($record['user_agent'] ?? ''),
        (int) ($record['user_id'] ?? 0),
    );

    return hash_hmac('sha256', implode("\n", $material), defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');
}

/**
 * Whether the stored record still seals to the value it was written with.
 * hash_equals: the stored value is compared in constant time.
 *
 * @param array $record
 * @return bool
 */
function erp_signature_verify($record)
{
    if (!is_array($record) || ((string) ($record['seal'] ?? '') === '')) {
        return false;
    }

    return hash_equals((string) $record['seal'], erp_signature_seal($record));
}

/**
 * Whether the document still hashes to what was signed.
 *
 * @param array  $record
 * @param string $current_hash  The document's hash as it stands now
 * @return bool|null  null when the record holds no hash to compare with
 */
function erp_signature_document_matches($record, $current_hash)
{
    if (!is_array($record) || ((string) ($record['document_hash'] ?? '') === '')) {
        return null;
    }

    return hash_equals((string) $record['document_hash'], (string) $current_hash);
}

/**
 * Whether the drawing on disk is still the drawing that was signed: the seal
 * covers the file's id, not its contents.
 *
 * @param array $record  From erp_signature()
 * @return bool
 */
function erp_signature_image_matches($record)
{
    if (!is_array($record) || ((string) ($record['file_name'] ?? '') === '') || ((string) ($record['image_hash'] ?? '') === '')) {
        return false;
    }

    $path = FILE_DIRECTORY_PATH . '/' . basename((string) $record['file_name']);

    return is_file($path) && hash_equals((string) $record['image_hash'], (string) hash_file('sha256', $path));
}

/**
 * Keep a signature: the drawing as an ERP document file, the evidence as a
 * row. The file is written first; a row pointing at no file would describe
 * a signature nobody can see.
 *
 * When, from where and by whose session are taken here, not from the
 * caller: a value the request could influence is not evidence.
 *
 * @param string $doc_type  'quote'
 * @param int    $doc_id
 * @param string $png       From pg_signature_png_from_data_url()
 * @param array  $context   signer_name, document_hash, label (the document
 *                          number, for the file name), user_id
 * @return array ['success' => bool, 'error' => string, 'record' => array|null]
 */
function erp_signature_store($doc_type, $doc_id, $png, $context = array())
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'record' => null);
    };

    if (!erp_signatures_ready()) {
        return $fail(lang('Signing documents comes with the software update; run the update to use it.'));
    }

    if (!is_string($png) || ($png === '')) {
        return $fail(lang('The signature could not be read. Sign again.'));
    }

    $signer_name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($context['signer_name'] ?? ''))), 0, 255);

    if ($signer_name === '') {
        return $fail(lang('Write the name of the person signing.'));
    }

    // Asked before the file is written; the unique key is what enforces it.
    if (erp_signature($doc_type, $doc_id) !== null) {
        return $fail(lang('The document is signed already.'));
    }

    $user_id = (int) ($context['user_id'] ?? 0);

    // A new file every time ($again): a file left by an attempt whose row
    // could not be written is not the drawing this signature is about.
    $file = erp_archive_store((string) $doc_type . '_signature', (int) $doc_id, $png, (string) ($context['label'] ?? ''), 'png', $user_id, true);

    if (!is_array($file)) {
        return $fail(lang('The signature could not be saved.'));
    }

    $record = array(
        'doc_type' => (string) $doc_type,
        'doc_id' => (int) $doc_id,
        'file_id' => (int) $file['id'],
        'image_hash' => hash('sha256', $png),
        'document_hash' => (string) ($context['document_hash'] ?? ''),
        'signer_name' => $signer_name,
        'signed_at' => time(),
        'ip_address' => substr(function_exists('waf_client_ip') ? (string) waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'user_id' => $user_id,
    );
    $record['seal'] = erp_signature_seal($record);

    $written = db("INSERT INTO erp_signatures SET
            doc_type = '" . escape($record['doc_type']) . "',
            doc_id = '" . $record['doc_id'] . "',
            file_id = '" . $record['file_id'] . "',
            image_hash = '" . escape($record['image_hash']) . "',
            document_hash = '" . escape($record['document_hash']) . "',
            signer_name = '" . escape($record['signer_name']) . "',
            signed_at = '" . $record['signed_at'] . "',
            ip_address = '" . escape($record['ip_address']) . "',
            user_agent = '" . escape($record['user_agent']) . "',
            user_id = '" . $record['user_id'] . "',
            seal = '" . escape($record['seal']) . "'");

    if ($written === false) {
        db("DELETE FROM files WHERE id = '" . (int) $file['id'] . "'");
        @unlink((string) $file['path']);
        return $fail((erp_signature($doc_type, $doc_id) !== null) ? lang('The document is signed already.') : lang('The signature could not be saved.'));
    }

    $record['id'] = (int) mysqli_insert_id(db::$con);

    return array('success' => true, 'error' => '', 'record' => $record);
}

/**
 * The signature as the document template prints it, or an empty array when
 * the document carries none - or no longer reads as it did when it was
 * signed: a signature printed under figures the customer never saw would
 * say they agreed to them.
 *
 * @param string $doc_type
 * @param int    $doc_id
 * @param string $current_hash  The document's hash as it stands now
 * @return array  image_data_uri, signer_name, signed_at
 */
function erp_signature_document_block($doc_type, $doc_id, $current_hash)
{
    $record = erp_signature($doc_type, $doc_id);

    if (($record === null) || (erp_signature_document_matches($record, $current_hash) === false)) {
        return array();
    }

    // The PDF renderer fetches nothing remote, and the file is served to the
    // ERP right only, so the drawing travels inside the document.
    $data_uri = '';
    $path = FILE_DIRECTORY_PATH . '/' . basename((string) $record['file_name']);

    if (((string) $record['file_name'] !== '') && is_file($path)) {
        $bytes = @file_get_contents($path);
        if (is_string($bytes) && ($bytes !== '')) {
            $data_uri = 'data:image/png;base64,' . base64_encode($bytes);
        }
    }

    return array(
        'image_data_uri' => $data_uri,
        'signer_name' => (string) $record['signer_name'],
        'signed_at' => (string) prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $record['signed_at']), 'date and time', false),
    );
}

/**
 * The drawing pad, in the panel's look. signature_pad.js finds it by its
 * data attributes, the same the signature form field carries, and fills the
 * hidden inputs; pg_signature_includes() prints the script.
 *
 * touch-action: none on the canvas is what lets a finger draw on a phone or
 * a tablet instead of scrolling the page.
 *
 * @param string $name     The hidden input's name; the strokes go in <name>_strokes
 * @param array  $options  id (the canvas id), height (pixels)
 * @return string
 */
function erp_signature_pad_markup($name, $options = array())
{
    $id = (string) ($options['id'] ?? ('erp_signature_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string) $name)));
    $height = max(80, min(400, (int) ($options['height'] ?? 180)));

    return '
<div class="pg-signature" data-pg-signature data-pg-empty-text="' . h(lang('Not signed yet')) . '" data-pg-signed-text="' . h(lang('Signed')) . '">
    <canvas id="' . h($id) . '" class="form-control p-0 bg-white" style="display:block;width:100%;height:' . $height . 'px;touch-action:none;cursor:crosshair"></canvas>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-pg-signature-clear><i class="bi bi-eraser me-1" aria-hidden="true"></i>' . lang('Clear') . '</button>
        <small class="text-body-secondary" data-pg-signature-status></small>
    </div>
    <input type="hidden" name="' . h($name) . '" data-pg-signature-value value="" />
    <input type="hidden" name="' . h($name) . '_strokes" data-pg-signature-strokes value="" />
</div>';
}
