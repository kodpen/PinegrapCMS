<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: signature fields - capturing one, binding it to the
// document it was drawn under, sealing the record and reading it back.
//
// Loaded by functions.php through require_once, never on its own. What this
// produces is an ordinary electronic signature: it is not a qualified signature
// and does not carry the legal weight of one. Its value is entirely in the
// record around the drawing, which is what the rest of this file is about.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The drawing cannot be larger than this once decoded, and cannot carry more
// points than this. Both are ceilings against a hostile client, not limits a
// person signing with a finger will ever reach: a full-width signature encodes
// to roughly 15-40 KB and a slow, careful signature produces a few thousand
// points.
define('PG_SIGNATURE_MAX_BYTES', 524288);
define('PG_SIGNATURE_MAX_POINTS', 20000);

// Whether the installation has the signature field at all. The table and the
// enum value arrive together in one upgrade step, but code can land before the
// database does (see the upgrade bridge rule), and a field screen that offers a
// type the column cannot store would save a text box without saying so.
function pg_signature_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = false;

        if (db_value("SHOW TABLES LIKE 'form_signatures'")) {
            $column = db_item("SHOW COLUMNS FROM form_fields WHERE Field = 'type'");

            $ready = (is_array($column) && isset($column['Type']) && (stripos($column['Type'], "'signature'") !== false));
        }
    }

    return $ready;
}

// The document the signature is bound to, as one hash.
//
// What is hashed is the form as it stood at the moment of signing - every field
// definition in the order it was shown, the label and, for an information field,
// its content, because that is where a contract or an offer is written - plus
// the answers given and the sentence the person agreed to. Editing the contract
// afterwards, reordering the form or changing an answer all produce a different
// hash, and the stored one no longer matches.
//
// The signature's own value is excluded: it is what is being bound, and it does
// not exist yet when the hash is taken.
function pg_signature_document_hash($page_id, $fields, $values, $consent_text)
{
    $document = array(
        'page_id' => (int) $page_id,
        'fields' => array(),
        'values' => array(),
        'consent' => (string) $consent_text,
    );

    foreach ((array) $fields as $field) {
        if (!is_array($field) || !isset($field['id'])) {
            continue;
        }

        $document['fields'][] = array(
            'id' => (int) $field['id'],
            'name' => isset($field['name']) ? (string) $field['name'] : '',
            'label' => isset($field['label']) ? (string) $field['label'] : '',
            'type' => isset($field['type']) ? (string) $field['type'] : '',
            'required' => isset($field['required']) ? (int) $field['required'] : 0,
            'information' => isset($field['information']) ? (string) $field['information'] : '',
        );
    }

    foreach ((array) $values as $field_id => $value) {
        $document['values'][(string) (int) $field_id] = is_array($value) ? array_map('strval', $value) : (string) $value;
    }

    // Order must not depend on the order the caller happened to build the array
    // in, or the same submission hashes differently on a later read.
    ksort($document['values']);

    return hash('sha256', json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// The seal over a stored record. Anything that can be edited in the database and
// would change what the signature means goes in; the row's own id does not,
// because it is assigned after the fact and carries no meaning.
//
// ENCRYPTION_KEY is the site's key and never leaves the server, so a seal cannot
// be recomputed by someone who only has the database.
function pg_signature_seal($record)
{
    $material = array(
        (int) (isset($record['form_id']) ? $record['form_id'] : 0),
        (int) (isset($record['form_field_id']) ? $record['form_field_id'] : 0),
        (int) (isset($record['file_id']) ? $record['file_id'] : 0),
        (int) (isset($record['page_id']) ? $record['page_id'] : 0),
        (string) (isset($record['document_hash']) ? $record['document_hash'] : ''),
        (string) (isset($record['consent_text']) ? $record['consent_text'] : ''),
        (string) (isset($record['strokes']) ? $record['strokes'] : ''),
        (int) (isset($record['signed_at']) ? $record['signed_at'] : 0),
        (string) (isset($record['ip_address']) ? $record['ip_address'] : ''),
        (string) (isset($record['user_agent']) ? $record['user_agent'] : ''),
        (int) (isset($record['user_id']) ? $record['user_id'] : 0),
        (string) (isset($record['image_hash']) ? $record['image_hash'] : ''),
        (string) (isset($record['stamp_digest']) ? $record['stamp_digest'] : ''),
        (int) (isset($record['stamp_time']) ? $record['stamp_time'] : 0),
        (string) (isset($record['stamp_authority']) ? $record['stamp_authority'] : ''),
        (string) (isset($record['stamp_token']) ? $record['stamp_token'] : ''),
    );

    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '';

    return hash_hmac('sha256', implode("\n", $material), $key);
}

// True when the stored record still seals to the value it was written with.
// hash_equals because the comparison runs against an attacker-supplied database
// value, and a length-sensitive comparison leaks how far a forgery got.
function pg_signature_verify($record)
{
    if (!is_array($record) || empty($record['seal'])) {
        return false;
    }

    return hash_equals((string) $record['seal'], pg_signature_seal($record));
}

// The signature stored for one field of one submission, or false.
function pg_signature_record($form_id, $form_field_id)
{
    if (!pg_signature_ready()) {
        return false;
    }

    $record = db_item("SELECT *
        FROM form_signatures
        WHERE (form_id = '" . e((int) $form_id) . "')
        AND (form_field_id = '" . e((int) $form_field_id) . "')
        LIMIT 1");

    return (is_array($record) && !empty($record)) ? $record : false;
}

// Whether this field of this submission has already been signed. Asked before a
// form is shown for editing and before a value is written; the unique key on the
// table is what actually enforces it.
function pg_signature_exists($form_id, $form_field_id)
{
    return (pg_signature_record($form_id, $form_field_id) !== false);
}

// The drawing, decoded from what the browser sent and re-encoded from its
// pixels.
//
// Re-encoding is the point: a data URL is attacker-controlled, and a file that
// is a valid PNG to the decoder while also being valid PHP or HTML to something
// else stops being both once it has been through GD. The dimensions are checked
// first so a decompression bomb is refused before any memory is spent on it.
//
// Returns the PNG bytes, or false.
function pg_signature_png_from_data_url($data_url)
{
    if (!is_string($data_url) || (strpos($data_url, 'data:image/png;base64,') !== 0)) {
        return false;
    }

    $encoded = substr($data_url, strlen('data:image/png;base64,'));

    // Four base64 characters carry three bytes; refuse before decoding rather
    // than after, so an oversized payload never becomes a string in memory.
    if (strlen($encoded) > (int) (PG_SIGNATURE_MAX_BYTES * 4 / 3) + 8) {
        return false;
    }

    $binary = base64_decode($encoded, true);

    if (($binary === false) || (strlen($binary) > PG_SIGNATURE_MAX_BYTES)) {
        return false;
    }

    $size = @getimagesizefromstring($binary);

    if (!is_array($size) || !isset($size[2]) || ($size[2] !== IMAGETYPE_PNG)) {
        return false;
    }

    if (($size[0] < 1) || ($size[1] < 1) || ($size[0] > 4000) || ($size[1] > 2000)) {
        return false;
    }

    if (function_exists('pg_image_ensure_memory')) {
        pg_image_ensure_memory($size[0], $size[1]);
    }

    $image = @imagecreatefromstring($binary);

    if ($image === false) {
        return false;
    }

    imagesavealpha($image, true);

    ob_start();
    $written = @imagepng($image, null, 9);
    $png = ob_get_clean();

    imagedestroy($image);

    return ($written && is_string($png) && ($png !== '')) ? $png : false;
}

// The stroke data, reduced to what it is allowed to be: a list of strokes, each
// a list of points, each point [x, y, t] with an optional pressure.
//
// It is stored because it is the only part of a signature that a pasted image
// cannot have - the timing and the shape of the hand's movement. It is rebuilt
// here rather than stored as it arrived so that the column cannot be used to
// carry something else into a later reader.
//
// Returns a JSON string, or '' when there is nothing usable.
function pg_signature_clean_strokes($raw)
{
    $strokes = is_string($raw) ? json_decode($raw, true) : $raw;

    if (!is_array($strokes)) {
        return '';
    }

    $clean = array();
    $points = 0;

    foreach ($strokes as $stroke) {
        if (!is_array($stroke)) {
            continue;
        }

        $line = array();

        foreach ($stroke as $point) {
            if (!is_array($point) || (count($point) < 3)) {
                continue;
            }

            if ($points >= PG_SIGNATURE_MAX_POINTS) {
                break 2;
            }

            $entry = array(
                round((float) $point[0], 2),
                round((float) $point[1], 2),
                (int) $point[2],
            );

            if (isset($point[3])) {
                $entry[] = round((float) $point[3], 3);
            }

            $line[] = $entry;
            $points++;
        }

        if (!empty($line)) {
            $clean[] = $line;
        }
    }

    return empty($clean) ? '' : json_encode($clean);
}

// Writes the signature: the drawing as a file, the evidence as a row.
//
// The file is written first. A row pointing at a file that was never created
// would describe a signature nobody can see; a file with no row is an orphan in
// the media folder and nothing more.
//
// $context carries page_id, document_hash, consent_text and strokes. The rest -
// when, who, from where - is taken here and not from the caller: a value the
// request could influence is not evidence of anything.
//
// Returns the new file id, or false.
function pg_signature_store($form_id, $form_field_id, $png, $context = array())
{
    if (!pg_signature_ready() || !is_string($png) || ($png === '')) {
        return false;
    }

    // Refused here as well as by the unique key: reaching the INSERT would mean
    // a file has already been written for a signature that cannot be recorded.
    if (pg_signature_exists($form_id, $form_field_id)) {
        return false;
    }

    $folder_id = (int) db_value("SELECT upload_folder_id FROM form_fields WHERE id = '" . e((int) $form_field_id) . "'");

    $file_name = pg_signature_file_name($form_id, $form_field_id);
    $file_path = FILE_DIRECTORY_PATH . '/' . $file_name;

    // Temp file plus rename, the same as every other writer of a media file: a
    // half-written signature is worse than none, because it looks like one.
    $temp_path = $file_path . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temp_path, $png) === false) {
        return false;
    }

    if (!@rename($temp_path, $file_path)) {
        @unlink($temp_path);
        return false;
    }

    $size = @getimagesize($file_path);
    $width = (is_array($size) && isset($size[0])) ? (int) $size[0] : 0;
    $height = (is_array($size) && isset($size[1])) ? (int) $size[1] : 0;

    // attachment = 1 is what an uploaded form file carries, and a signature is
    // one. optimized = 1 because GD has just written this PNG: offering to
    // optimise it would promise a saving that is not there.
    $dimension_columns = '';
    $dimension_values = '';

    if (db_item("SHOW COLUMNS FROM files WHERE Field = 'image_width'")) {
        $dimension_columns = ', image_width, image_height';
        $dimension_values = ", '" . e($width) . "', '" . e($height) . "'";
    }

    $inserted = db("INSERT INTO files (name, folder, type, size, user, timestamp, design, attachment, optimized" . $dimension_columns . ")
        VALUES (
            '" . e($file_name) . "',
            '" . e($folder_id) . "',
            'png',
            '" . e(strlen($png)) . "',
            '" . e((int) (defined('USER_ID') ? USER_ID : 0)) . "',
            '" . e(time()) . "',
            '0',
            '1',
            '1'" . $dimension_values . ")");

    if ($inserted === false) {
        @unlink($file_path);
        return false;
    }

    $file_id = (int) mysqli_insert_id(db::$con);

    $signed_at = time();
    $image_hash = hash('sha256', $png);
    $document_hash = (string) (isset($context['document_hash']) ? $context['document_hash'] : '');

    // What a third party is asked to vouch for. Every part of it is printed on
    // the receipt, so whoever holds the receipt and the image file can rebuild
    // this value and check the token against it without asking this site
    // anything. A digest only we could reproduce would prove nothing to them.
    $stamp_digest = pg_signature_stamp_digest($document_hash, $image_hash, $signed_at);
    $stamp = pg_signature_request_stamp($stamp_digest);

    $record = array(
        'form_id' => (int) $form_id,
        'form_field_id' => (int) $form_field_id,
        'file_id' => $file_id,
        'image_hash' => $image_hash,
        'page_id' => (int) (isset($context['page_id']) ? $context['page_id'] : 0),
        'document_hash' => $document_hash,
        'consent_text' => (string) (isset($context['consent_text']) ? $context['consent_text'] : ''),
        'strokes' => (string) (isset($context['strokes']) ? $context['strokes'] : ''),
        'signed_at' => $signed_at,
        'ip_address' => function_exists('waf_client_ip') ? (string) waf_client_ip() : (string) (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
        'user_agent' => substr((string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 255),
        'user_id' => (int) (defined('USER_ID') ? USER_ID : 0),
        'stamp_digest' => ($stamp === false) ? '' : $stamp_digest,
        'stamp_time' => ($stamp === false) ? 0 : (int) $stamp['time'],
        'stamp_authority' => ($stamp === false) ? '' : (string) $stamp['authority'],
        'stamp_token' => ($stamp === false) ? '' : (string) $stamp['token'],
    );

    $record['seal'] = pg_signature_seal($record);

    $written = db("INSERT INTO form_signatures (
            form_id, form_field_id, file_id, image_hash, page_id, document_hash,
            consent_text, strokes, signed_at, ip_address, user_agent, user_id,
            stamp_digest, stamp_time, stamp_authority, stamp_token, seal
        ) VALUES (
            '" . e($record['form_id']) . "',
            '" . e($record['form_field_id']) . "',
            '" . e($record['file_id']) . "',
            '" . e($record['image_hash']) . "',
            '" . e($record['page_id']) . "',
            '" . e($record['document_hash']) . "',
            '" . e($record['consent_text']) . "',
            '" . e($record['strokes']) . "',
            '" . e($record['signed_at']) . "',
            '" . e($record['ip_address']) . "',
            '" . e($record['user_agent']) . "',
            '" . e($record['user_id']) . "',
            '" . e($record['stamp_digest']) . "',
            '" . e($record['stamp_time']) . "',
            '" . e($record['stamp_authority']) . "',
            '" . e($record['stamp_token']) . "',
            '" . e($record['seal']) . "')");

    if ($written === false) {
        db("DELETE FROM files WHERE id = '" . e($file_id) . "'");
        @unlink($file_path);
        return false;
    }

    return $file_id;
}

// The name the drawing is stored under. files.name is the public address of the
// file, so it has to be unique and it has to be ASCII (see the IIS rule).
function pg_signature_file_name($form_id, $form_field_id)
{
    $name = 'signature_' . (int) $form_id . '_' . (int) $form_field_id . '_' . substr(bin2hex(random_bytes(8)), 0, 16) . '.png';

    return function_exists('get_unique_name') ? get_unique_name(array('name' => $name, 'type' => 'file')) : $name;
}

// The field as the visitor sees it.
//
// Emitted by the generated page layout as a call rather than as literal markup,
// so the pad can be improved without every custom form page having to have its
// layout regenerated. Takes the field row the layout already has, or an id.
//
// touch-action:none on the canvas is not decoration: without it a drag on a
// phone scrolls the page and nothing can be drawn at all.
function pg_signature_field($field)
{
    if (!is_array($field)) {
        $field = db_item("SELECT * FROM form_fields WHERE id = '" . e((int) $field) . "' LIMIT 1");
    }

    if (!is_array($field) || empty($field['id'])) {
        return '';
    }

    $id = (int) $field['id'];
    $label = isset($field['label']) ? (string) $field['label'] : '';
    $required = !empty($field['required']);

    // A form redisplayed after an error carries what was drawn before.
    $form = function_exists('liveform') ? liveform('custom_form') : null;
    $value = '';
    $strokes = '';

    if (is_object($form) && method_exists($form, 'get_field')) {
        $stored = $form->get_field($id);

        if (is_array($stored) && isset($stored['value']) && is_string($stored['value'])) {
            $value = $stored['value'];
        }

        $stored_strokes = $form->get_field($id . '_strokes');

        if (is_array($stored_strokes) && isset($stored_strokes['value']) && is_string($stored_strokes['value'])) {
            $strokes = $stored_strokes['value'];
        }
    }

    return '
<div class="form-group pg-signature"
    data-pg-signature
    data-pg-empty-text="' . h(lang('Not signed yet')) . '"
    data-pg-signed-text="' . h(lang('Signed')) . '">
    <label for="pg_signature_' . $id . '">' . h($label) . ($required ? ' <span aria-hidden="true">*</span>' : '') . '</label>
    <canvas id="pg_signature_' . $id . '" style="display:block;width:100%;height:170px;border:1px solid #ced4da;border-radius:.25rem;background:#ffffff;touch-action:none;cursor:crosshair"></canvas>
    <div style="display:flex;align-items:center;gap:.75rem;margin-top:.35rem">
        <button type="button" data-pg-signature-clear style="font:inherit;padding:.15rem .6rem;border:1px solid #ced4da;border-radius:.25rem;background:transparent;cursor:pointer">' . h(lang('Clear')) . '</button>
        <small data-pg-signature-status></small>
    </div>
    <input type="hidden" name="' . $id . '" data-pg-signature-value value="' . h($value) . '">
    <input type="hidden" name="' . $id . '_strokes" data-pg-signature-strokes value="' . h($strokes) . '">
</div>';
}

// The capture script, once per page. Same shape as the date picker and the rich
// text editor: the screen that finds such a field appends this to its system
// content.
function pg_signature_includes()
{
    $path = PG_FUNCTIONS_DIR . '/assets/js/signature_pad.js';

    return '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/signature_pad.js?v=' . @filemtime($path) . '" defer></script>';
}

// Whether the document still hashes to what was signed.
//
// The seal answers "was this record edited"; this answers "was the thing that
// was signed edited", which is the question a contract raises. The answers come
// back out of form_data and the field definitions are read as they stand now, so
// an information field someone rewrote after the fact - and an information field
// is exactly where a contract is written - changes the answer.
//
// Returns true, false, or null when there is no hash to compare against.
function pg_signature_document_matches($record)
{
    if (!is_array($record) || empty($record['document_hash'])) {
        return null;
    }

    $fields = db_items("SELECT id, name, label, type, required, information
        FROM form_fields
        WHERE page_id = '" . e((int) $record['page_id']) . "'
        ORDER BY sort_order ASC");

    $rows = db_items("SELECT form_field_id, data
        FROM form_data
        WHERE form_id = '" . e((int) $record['form_id']) . "'
        ORDER BY id ASC");

    $values = array();

    foreach ((array) $rows as $row) {
        $field_id = (int) $row['form_field_id'];

        // The signature's own row holds no value and was not part of the hash.
        if ($field_id === (int) $record['form_field_id']) {
            continue;
        }

        // A field with several rows is a check box group or a multiple pick
        // list, and was hashed as the array it was submitted as.
        if (isset($values[$field_id])) {
            if (!is_array($values[$field_id])) {
                $values[$field_id] = array($values[$field_id]);
            }

            $values[$field_id][] = $row['data'];

        } else {
            $values[$field_id] = $row['data'];
        }
    }

    return hash_equals(
        (string) $record['document_hash'],
        pg_signature_document_hash($record['page_id'], $fields, $values, $record['consent_text']));
}

// Whether the drawing on disk is still the drawing that was signed.
//
// The third question, after the record and the document. Nothing else asks it:
// the seal covers the file's id, not its contents, so replacing the file used to
// change the signature without changing anything that was checked.
//
// Returns true, false, or null for a record written before the digest was kept.
function pg_signature_image_matches($record)
{
    if (!is_array($record) || empty($record['image_hash'])) {
        return null;
    }

    $name = db_value("SELECT name FROM files WHERE id = '" . e((int) $record['file_id']) . "'");

    if (!$name) {
        return false;
    }

    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (!is_file($path)) {
        return false;
    }

    return hash_equals((string) $record['image_hash'], hash_file('sha256', $path));
}

// A stored signature, shown rather than offered. Every screen that displays a
// submitted form uses this: once signed there is no control anywhere, because a
// signature that can be redrawn is not evidence of anything.
function pg_signature_display($record, $options = array())
{
    if (!is_array($record) || empty($record['file_id'])) {
        return '';
    }

    $file = db_item("SELECT name FROM files WHERE id = '" . e((int) $record['file_id']) . "' LIMIT 1");

    if (!is_array($file) || empty($file['name'])) {
        return '';
    }

    $url = OUTPUT_PATH . $file['name'];
    $signed = (int) (isset($record['signed_at']) ? $record['signed_at'] : 0);

    // The download name says what the file is; files.name is a storage name
    // and means nothing to whoever opens it a year later. The download attribute
    // is enough because the file is served from this same origin.
    $download_name = 'imza-' . (int) $record['form_id'] . '-' . (int) $record['form_field_id'] . '-' . date('Y-m-d', $signed) . '.png';

    $output = '
<div class="pg-signature-view">
    <img src="' . h($url) . '" alt="' . h(lang('Signature')) . '" style="max-width:320px;height:auto;display:block;border:1px solid #ced4da;border-radius:.25rem;background:#ffffff">
    <a href="' . h($url) . '" download="' . h($download_name) . '" style="display:inline-block;margin-top:.35rem">' . h(lang('Download')) . '</a>
    <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/signature_receipt.php?form_id=' . (int) $record['form_id'] . '&field_id=' . (int) $record['form_field_id']) . '" target="_blank" style="display:inline-block;margin:.35rem 0 0 1rem">' . h(lang('Evidence receipt')) . '</a>';

    if (empty($options['image_only'])) {
        $verified = pg_signature_verify($record);
        $document = pg_signature_document_matches($record);

        $output .= '
    <small style="display:block;margin-top:.35rem">'
            . h(lang(array(
                'string' => 'Signed on {var:1}',
                // An absolute time, in the site's own day/month order.
                // DATE_FORMAT is a site setting ('month_day'), not a date()
                // pattern, and relative time is no use on a signature.
                'vars' => date(get_date_format_code() . '/Y H:i', $signed),
            )))
            . ' &middot; ' . h($record['ip_address'])
            . ' &middot; ' . h($verified ? lang('Record intact') : lang('Record does not match its seal'))
            . ' &middot; ' . h($document === null
                ? lang('Document not recorded')
                : ($document ? lang('Document unchanged') : lang('Document changed since it was signed')))
            . '</small>';
    }

    $output .= '
</div>';

    return $output;
}


// The signed document, as it can be read back: the fields in the order they
// were shown, and the answers that were given.
//
// This is the same set the hash covers, which is the point - the receipt shows
// what was hashed and nothing else, so "unchanged" means what it says.
function pg_signature_document_parts($record)
{
    $fields = db_items("SELECT id, name, label, type, required, information
        FROM form_fields
        WHERE page_id = '" . e((int) $record['page_id']) . "'
        ORDER BY sort_order ASC");

    $rows = db_items("SELECT form_field_id, data, type
        FROM form_data
        WHERE form_id = '" . e((int) $record['form_id']) . "'
        ORDER BY id ASC");

    $answers = array();

    foreach ((array) $rows as $row) {
        $field_id = (int) $row['form_field_id'];

        if (!isset($answers[$field_id])) {
            $answers[$field_id] = array('values' => array(), 'html' => ($row['type'] === 'html'));
        }

        $answers[$field_id]['values'][] = (string) $row['data'];
    }

    return array('fields' => (array) $fields, 'answers' => $answers);
}

// The receipt: one printable page carrying the signature, the document it was
// given under, and what can still be said about both.
//
// A standalone document on purpose. It is saved, attached to a contract file and
// opened years later by someone who has never seen this panel, so it carries its
// own styling and depends on nothing that has to still be installed.
function pg_signature_receipt_html($record)
{
    if (!is_array($record) || empty($record['id'])) {
        return '';
    }

    $verified = pg_signature_verify($record);
    $document_matches = pg_signature_document_matches($record);
    $parts = pg_signature_document_parts($record);

    $file = db_item("SELECT name FROM files WHERE id = '" . e((int) $record['file_id']) . "' LIMIT 1");
    $image_url = (is_array($file) && !empty($file['name'])) ? (OUTPUT_PATH . $file['name']) : '';

    $form = db_item("SELECT reference_code, submitted_timestamp FROM forms WHERE id = '" . e((int) $record['form_id']) . "' LIMIT 1");
    $page_name = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . e((int) $record['page_id']) . "'");

    $signer = '';

    if (!empty($record['user_id'])) {
        $signer = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . e((int) $record['user_id']) . "'");
    }

    $stamp = date(get_date_format_code() . '/Y H:i:s', (int) $record['signed_at']);

    // Warnings come first and are the only colour on the page. Everything below
    // them describes the document as it stands NOW, and if it no longer matches
    // what was signed the reader has to know that before reading it, not after.
    $warnings = '';

    if ($document_matches === false) {
        $warnings .= '<p class="warn">' . h(lang('This document was changed after it was signed. What is shown below is the version as it stands now, not the version that was signed.')) . '</p>';
    }

    if (!$verified) {
        $warnings .= '<p class="warn">' . h(lang('The stored record does not match its seal, so it was edited after it was written.')) . '</p>';
    }

    $document = '';

    foreach ($parts['fields'] as $field) {
        $field_id = (int) $field['id'];

        // An information field is not a question, it is the text being agreed
        // to - the contract itself - so it is printed as written.
        if ($field['type'] === 'information') {
            $document .= '<div class="prose">' . $field['information'] . '</div>';

            continue;
        }

        // The signature has its own block above; repeating it as an empty answer
        // would read as an unanswered question.
        if ($field['type'] === 'signature') {
            continue;
        }

        $answer = isset($parts['answers'][$field_id]) ? $parts['answers'][$field_id] : null;
        $printed = '';

        if ($answer !== null) {
            $values = array();

            foreach ($answer['values'] as $value) {
                if ($value === '') {
                    continue;
                }

                // A rich text answer is stored as markup and was written by the
                // person signing; it is printed the way every other screen in
                // this software prints it, filtered against the allow-list.
                $values[] = $answer['html'] ? pg_sanitize_rich_text($value) : nl2br(h($value));
            }

            $printed = implode('<br>', $values);
        }

        $document .=
            '<div class="row"><div class="key">' . h($field['label'] !== '' ? $field['label'] : $field['name']) . '</div>'
            . '<div class="value">' . ($printed === '' ? '<span class="muted">&mdash;</span>' : $printed) . '</div></div>';
    }

    $facts = array(
        array(lang('Consent text'), h($record['consent_text'])),
        array(lang('Signed at'), h($stamp)),
        array(lang('IP address'), h($record['ip_address'])),
        array(lang('Signed by'), h($signer !== '' ? $signer : lang('Guest'))),
        array(lang('Browser'), h($record['user_agent'])),
    );

    $image_matches = pg_signature_image_matches($record);

    $checks = array(
        array(lang('Document fingerprint (SHA-256)'), '<span class="mono">' . h($record['document_hash']) . '</span>'),
        array(lang('Document'), h($document_matches === null
            ? lang('Document not recorded')
            : ($document_matches ? lang('Document unchanged') : lang('Document changed since it was signed')))),
        array(lang('Signature file fingerprint (SHA-256)'), $record['image_hash'] === ''
            ? '<span class="muted">&mdash;</span>'
            : '<span class="mono">' . h($record['image_hash']) . '</span>'),
        array(lang('Signature file'), h($image_matches === null
            ? lang('Document not recorded')
            : ($image_matches ? lang('Signature file unchanged') : lang('Signature file changed since it was signed')))),
        array(lang('Record seal'), h($verified ? lang('Record intact') : lang('Record does not match its seal'))),
    );

    // The stamp is the one line here that does not rest on this site's word, so
    // it says who vouched and hands over the token that proves it.
    if (!empty($record['stamp_token'])) {
        $stamp_time = (int) $record['stamp_time'];

        $checks[] = array(lang('Time stamp'),
            h($record['stamp_authority'])
            . ($stamp_time ? ' &middot; ' . h(gmdate('Y-m-d H:i:s', $stamp_time)) . ' UTC' : '')
            . ' &middot; <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/signature_receipt.php?form_id=' . (int) $record['form_id'] . '&field_id=' . (int) $record['form_field_id'] . '&token=1') . '">' . h(lang('Download token')) . '</a>');

        $checks[] = array(lang('Stamped digest'),
            '<span class="mono">' . h($record['stamp_digest']) . '</span>'
            . '<br><small>' . h(lang('SHA-256 of the document fingerprint, the signature file fingerprint and the signing time, each on its own line.')) . '</small>');

    } else {
        $checks[] = array(lang('Time stamp'), '<span class="muted">' . h(lang('Not obtained')) . '</span>');
    }

    $checks[] = array(lang('Reference'), h($record['form_id'] . ' / ' . $record['form_field_id'] . ' / ' . $record['id'])
        . (!empty($form['reference_code']) ? ' &middot; ' . h($form['reference_code']) : ''));

    $fact_rows = '';

    foreach ($facts as $fact) {
        $fact_rows .= '<div class="row"><div class="key">' . h($fact[0]) . '</div><div class="value">' . $fact[1] . '</div></div>';
    }

    $check_rows = '';

    foreach ($checks as $check) {
        $check_rows .= '<div class="row"><div class="key">' . h($check[0]) . '</div><div class="value">' . $check[1] . '</div></div>';
    }

    $language = lang(array('info' => true));

    return '<!doctype html>
<html lang="' . h($language) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . h(lang('Signature Evidence Receipt')) . '</title>
<style>
    :root { color-scheme: light; }
    body { margin: 0; padding: 2rem 1rem; background: #f2f2f2; color: #111;
        font: 14px/1.55 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .sheet { max-width: 46rem; margin: 0 auto; background: #fff; padding: 2.5rem;
        border: 1px solid #ddd; }
    h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
    h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em;
        color: #555; margin: 2rem 0 .6rem; border-bottom: 1px solid #e3e3e3; padding-bottom: .3rem; }
    .origin { color: #555; margin: 0 0 1.5rem; }
    .row { display: flex; gap: 1rem; padding: .4rem 0; border-bottom: 1px solid #f0f0f0; }
    .row:last-child { border-bottom: 0; }
    .key { flex: 0 0 13rem; color: #555; }
    .value { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
    .muted { color: #999; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85em; }
    .prose { margin: .6rem 0 1rem; }
    .prose img { max-width: 100%; height: auto; }
    .warn { border: 1px solid #b23; background: #fdf0f1; color: #7a1220; padding: .7rem .9rem; margin: 0 0 1rem; }
    .signature img { max-width: 22rem; height: auto; display: block; border: 1px solid #ddd; background: #fff; }
    .note { color: #555; font-size: .85rem; margin-top: 2rem; border-top: 1px solid #e3e3e3; padding-top: .8rem; }
    .actions { max-width: 46rem; margin: 1rem auto 0; text-align: right; }
    .actions button { font: inherit; padding: .4rem 1rem; border: 1px solid #bbb; background: #fff; cursor: pointer; }
    /* Two columns need room for both. Below that the label reads as a heading
       over its value, which is the same information in the shape that fits. */
    @media (max-width: 34rem) {
        body { padding: .75rem .5rem; }
        .sheet { padding: 1.25rem; }
        .row { display: block; }
        .key { flex: none; margin-bottom: .1rem; }
    }
    @media print {
        body { background: #fff; padding: 0; }
        .sheet { border: 0; padding: 0; max-width: none; }
        .actions { display: none; }
    }
</style>
</head>
<body>
<div class="sheet">
    <h1>' . h(lang('Signature Evidence Receipt')) . '</h1>
    <p class="origin">' . h(lang(array(
        'string' => 'Produced by {var:1} on {var:2}',
        'vars' => array(HOSTNAME, date(get_date_format_code() . '/Y H:i', time())),
    ))) . ' &middot; ' . h($page_name) . '</p>

    ' . $warnings . '

    <h2>' . h(lang('Signature')) . '</h2>
    <div class="signature">' . ($image_url !== '' ? '<img src="' . h($image_url) . '" alt="' . h(lang('Signature')) . '">' : '<span class="muted">&mdash;</span>') . '</div>
    ' . $fact_rows . '

    <h2>' . h(lang('Signed document')) . '</h2>
    ' . $document . '

    <h2>' . h(lang('Verification')) . '</h2>
    ' . $check_rows . '

    <p class="note">' . h(lang('This is an ordinary electronic signature. It is not a qualified electronic signature and does not carry the legal effect of a handwritten signature.')) . '</p>
</div>
<div class="actions"><button type="button" onclick="window.print()">' . h(lang('Print')) . '</button></div>
</body>
</html>';
}


// ---------------------------------------------------------------------------
// RFC 3161 time stamping
//
// signed_at is this server's clock, which makes it this site's own word about
// when something happened - the one claim in the record that the site itself
// has an interest in. A time stamp is a third party's word about the same
// moment, over a digest that anyone holding the receipt can rebuild.
//
// The request and the reply are DER-encoded ASN.1. PHP's openssl extension does
// not expose the time stamp functions and the openssl command line is off on
// most shared hosting (the disable_functions rule), so the structures are built
// and read here. They are small: the request is one SEQUENCE of four fields.
//
// The token is stored and shown, not verified against its certificate chain
// here. Verification is what an RFC 3161 tool is for, and the receipt hands the
// token over so one can be used - "openssl ts -reply -in <file> -token_in
// -text" reads it, and so does any timestamp checker.
// ---------------------------------------------------------------------------

// The call is made while the visitor waits, which is the one place this software
// says not to make network calls. The reason it is here anyway: a stamp taken
// later attests a later moment, and the moment is the whole point. The cost is
// bounded instead - a short timeout, and a failure that leaves the signature
// stored without a stamp rather than refusing the signature.
define('PG_SIGNATURE_TSA_TIMEOUT', 6);

// The value the authority is asked to vouch for: the document, the bytes of the
// drawing, and the moment. All three are printed on the receipt, so the recipe
// below is reproducible by whoever holds it.
function pg_signature_stamp_digest($document_hash, $image_hash, $signed_at)
{
    return hash('sha256', $document_hash . "\n" . $image_hash . "\n" . (int) $signed_at);
}

// Where to ask. Empty means the site has not chosen an authority and no stamp
// is taken; nothing else about a signature changes.
function pg_signature_tsa_url()
{
    return (defined('SIGNATURE_TSA_URL') && (SIGNATURE_TSA_URL !== '')) ? (string) SIGNATURE_TSA_URL : '';
}

// How the authority wants to be told who is asking. Free ones want nothing;
// commercial ones that sell against an account generally take HTTP basic.
//
// Kamu SM is neither: it carries the customer number and password inside the
// request rather than in a header, and the encoding is in the integration
// documentation a customer receives. It is deliberately not offered as a choice
// until that has been checked against their test server - a scheme that is
// listed but does not work is worse than one that is missing.
function pg_signature_tsa_auth()
{
    return (defined('SIGNATURE_TSA_AUTH') && (SIGNATURE_TSA_AUTH === 'basic')) ? 'basic' : 'none';
}

function pg_signature_der_length($length)
{
    if ($length < 128) {
        return chr($length);
    }

    $bytes = '';

    while ($length > 0) {
        $bytes = chr($length & 0xFF) . $bytes;
        $length >>= 8;
    }

    return chr(0x80 | strlen($bytes)) . $bytes;
}

function pg_signature_der_tlv($tag, $content)
{
    return chr($tag) . pg_signature_der_length(strlen($content)) . $content;
}

// DER integers are signed, so a leading byte with the high bit set would read
// as a negative number; a zero byte in front keeps it positive.
function pg_signature_der_integer_bytes($bytes)
{
    if (($bytes === '') || (ord($bytes[0]) & 0x80)) {
        $bytes = chr(0) . $bytes;
    }

    return pg_signature_der_tlv(0x02, $bytes);
}

// One TLV, or false when the data runs out. $offset moves past what was read.
function pg_signature_der_read($data, &$offset)
{
    if (($offset + 2) > strlen($data)) {
        return false;
    }

    $tag = ord($data[$offset]);
    $offset++;

    $first = ord($data[$offset]);
    $offset++;

    if ($first < 128) {
        $length = $first;

    } else {
        $count = $first & 0x7F;

        // A four byte length is 4 GB; anything longer is not a reply, it is a
        // malformed stream walking off the end of the buffer.
        if (($count === 0) || ($count > 4) || (($offset + $count) > strlen($data))) {
            return false;
        }

        $length = 0;

        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | ord($data[$offset]);
            $offset++;
        }
    }

    if (($offset + $length) > strlen($data)) {
        return false;
    }

    $node = array('tag' => $tag, 'content' => substr($data, $offset, $length), 'length' => $length);
    $offset += $length;

    return $node;
}

// The first GeneralizedTime anywhere in the structure. In a time stamp token
// that is the genTime of the TSTInfo, which is the only one there.
//
// Octet strings are walked into as well as constructed nodes: the signed
// content of a token is an octet string holding more DER, and the time is
// inside it.
function pg_signature_der_find_time($data, $depth = 0)
{
    if ($depth > 12) {
        return '';
    }

    $offset = 0;

    while ($offset < strlen($data)) {
        $node = pg_signature_der_read($data, $offset);

        if ($node === false) {
            return '';
        }

        if ($node['tag'] === 0x18) {
            return $node['content'];
        }

        if (($node['tag'] & 0x20) || ($node['tag'] === 0x04)) {
            $found = pg_signature_der_find_time($node['content'], $depth + 1);

            if ($found !== '') {
                return $found;
            }
        }
    }

    return '';
}

// "20260913034919Z", with optional fractional seconds, as a unix timestamp.
function pg_signature_parse_generalized_time($value)
{
    $value = trim((string) $value);

    if (($dot = strpos($value, '.')) !== false) {
        $value = substr($value, 0, $dot) . 'Z';
    }

    if (!preg_match('/^(\d{14})Z$/', $value, $found)) {
        return 0;
    }

    $time = DateTime::createFromFormat('YmdHis', $found[1], new DateTimeZone('UTC'));

    return ($time === false) ? 0 : (int) $time->getTimestamp();
}

// TimeStampReq: version 1, the sha256 imprint, a nonce, certReq true.
//
// The nonce is what ties the reply to this request and not to a recorded one;
// certReq asks the authority to put its certificate in the token, without which
// nobody can check the signature on it later.
function pg_signature_stamp_request($digest, $nonce)
{
    $algorithm = pg_signature_der_tlv(0x30,
        pg_signature_der_tlv(0x06, "\x60\x86\x48\x01\x65\x03\x04\x02\x01")   // 2.16.840.1.101.3.4.2.1 sha256
        . pg_signature_der_tlv(0x05, ''));

    $imprint = pg_signature_der_tlv(0x30, $algorithm . pg_signature_der_tlv(0x04, $digest));

    return pg_signature_der_tlv(0x30,
        pg_signature_der_integer_bytes(chr(1))
        . $imprint
        . pg_signature_der_integer_bytes($nonce)
        . pg_signature_der_tlv(0x01, chr(0xFF)));
}

// Asks the configured authority to stamp $digest.
//
// Returns array(token, time, authority) or false. Every failure returns false
// and says so in the log: a signature without a stamp is worth keeping, and a
// refused signature is not.
// $settings lets a caller ask with an address and credentials other than the
// ones this request started with. The settings screen needs exactly that: its
// test button runs after the save, when the constants still hold what the page
// was loaded with, and testing the old address would answer the wrong question.
//
// $reason comes back with the failure in words, for a screen that has to say
// what went wrong rather than only write it to the log.
function pg_signature_request_stamp($digest, $settings = null, &$reason = null)
{
    $url = ($settings === null) ? pg_signature_tsa_url() : (string) (isset($settings['url']) ? $settings['url'] : '');
    $auth = ($settings === null) ? pg_signature_tsa_auth() : ((isset($settings['auth']) && ($settings['auth'] === 'basic')) ? 'basic' : 'none');
    $username = ($settings === null)
        ? (defined('SIGNATURE_TSA_USERNAME') ? SIGNATURE_TSA_USERNAME : '')
        : (string) (isset($settings['username']) ? $settings['username'] : '');
    $password = ($settings === null)
        ? (defined('SIGNATURE_TSA_PASSWORD') ? SIGNATURE_TSA_PASSWORD : '')
        : (string) (isset($settings['password']) ? $settings['password'] : '');

    $reason = '';

    if (($url === '') || !function_exists('curl_init')) {
        $reason = 'no address';

        return false;
    }

    $nonce = function_exists('random_bytes') ? random_bytes(8) : pack('N2', mt_rand(), mt_rand());
    $request = pg_signature_stamp_request(hex2bin($digest), $nonce);

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/timestamp-query',
        'Accept: application/timestamp-reply',
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, PG_SIGNATURE_TSA_TIMEOUT);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, PG_SIGNATURE_TSA_TIMEOUT);
    curl_setopt($curl, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($curl);
    }

    // Credentials go only where the site said they should. Sending them to an
    // authority that was never told to expect them puts a password on the wire
    // for nothing.
    if (($auth === 'basic') && ($username !== '')) {
        curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errno = curl_errno($curl);
    $error = curl_error($curl);

    curl_close($curl);

    if (($response === false) || ($response === '')) {
        $reason = 'cURL ' . $errno . ': ' . $error . (function_exists('pg_curl_tls_hint') ? ' ' . pg_curl_tls_hint($errno) : '');
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    if ($status !== 200) {
        $reason = 'HTTP ' . $status;
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    $offset = 0;
    $outer = pg_signature_der_read($response, $offset);

    if (($outer === false) || ($outer['tag'] !== 0x30)) {
        $reason = 'reply is not DER';
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    $inner = 0;
    $status_info = pg_signature_der_read($outer['content'], $inner);

    if (($status_info === false) || ($status_info['tag'] !== 0x30)) {
        $reason = 'reply carries no status';
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    $status_offset = 0;
    $status_value = pg_signature_der_read($status_info['content'], $status_offset);
    $pki_status = (($status_value === false) || ($status_value['content'] === '')) ? -1 : ord($status_value['content']);

    // 0 granted, 1 granted with modifications. Anything else is a refusal.
    if (($pki_status !== 0) && ($pki_status !== 1)) {
        $reason = 'PKIStatus ' . $pki_status;
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    $token = pg_signature_der_read($outer['content'], $inner);

    if ($token === false) {
        $reason = 'reply carries no token';
        pg_signature_stamp_failed($url, $reason);

        return false;
    }

    // The token is stored whole, as it came: it is the evidence, and re-encoding
    // it would break the signature over it.
    $token_der = chr($token['tag']) . pg_signature_der_length($token['length']) . $token['content'];

    return array(
        'token' => base64_encode($token_der),
        'time' => pg_signature_parse_generalized_time(pg_signature_der_find_time($token_der)),
        'authority' => substr($url, 0, 255),
    );
}

// A stamp that could not be taken is written down. Otherwise the only trace is
// a receipt that quietly says nothing about time, and nobody learns that the
// authority has been unreachable for a month.
function pg_signature_stamp_failed($url, $reason)
{
    log_activity(lang(array(
        'string' => 'Time stamp could not be obtained from {var:1}: {var:2}',
        'vars' => array($url, $reason),
    )), 'SYSTEM');
}

// The stored token as its raw bytes, for handing to a verifier.
function pg_signature_stamp_token($record)
{
    if (!is_array($record) || empty($record['stamp_token'])) {
        return '';
    }

    $token = base64_decode((string) $record['stamp_token'], true);

    return ($token === false) ? '' : $token;
}
