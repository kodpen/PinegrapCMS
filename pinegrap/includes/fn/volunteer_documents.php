<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's own document upload ("Upload a new one" on the volunteer's
 * page and My Account's volunteering section): the checks of the typed kind,
 * number and valid-until date, the file (a PDF or a picture whose first
 * bytes match its extension, within the size cap), its files row (attachment
 * 1, folder 0, a volunteer-doc- name, so it is in no File Manager folder and
 * opens only through get_file.php's volunteer door) and the
 * volunteer_documents row. Loaded by volunteer.php and the page markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');

// The largest document file in bytes, never above the server's own limit.
if (!defined('PG_VOLUNTEER_DOC_MAX_BYTES')) {
    define('PG_VOLUNTEER_DOC_MAX_BYTES', 10 * 1024 * 1024);
}

// How many documents one volunteer may keep; an upload past it is refused.
if (!defined('PG_VOLUNTEER_DOC_MAX_COUNT')) {
    define('PG_VOLUNTEER_DOC_MAX_COUNT', 20);
}

/**
 * The extensions a document file may have. Pure.
 *
 * @return array
 */
function pg_volunteer_doc_types()
{
    return array('pdf', 'jpg', 'jpeg', 'png', 'webp');
}

/**
 * The largest document file in bytes: the step's cap, never above the
 * server's. Pure.
 *
 * @param int $file_max bytes, pg_upload_limits()['file_max'] (0: none)
 * @return int
 */
function pg_volunteer_doc_cap($file_max)
{
    $file_max = (int) $file_max;

    return ($file_max > 0) ? min(PG_VOLUNTEER_DOC_MAX_BYTES, $file_max) : PG_VOLUNTEER_DOC_MAX_BYTES;
}

/**
 * Whether a file's first bytes are those of its extension's format, so a
 * page or a script renamed to .pdf or .png is refused. Pure.
 *
 * @param string $head      the file's first bytes (12 are enough)
 * @param string $extension lower case
 * @return bool
 */
function pg_volunteer_doc_sniff($head, $extension)
{
    $head = (string) $head;

    switch ((string) $extension) {
        case 'pdf':
            return strncmp($head, '%PDF-', 5) === 0;
        case 'jpg':
        case 'jpeg':
            return strncmp($head, "\xFF\xD8\xFF", 3) === 0;
        case 'png':
            return strncmp($head, "\x89PNG\r\n\x1A\n", 8) === 0;
        case 'webp':
            return (strncmp($head, 'RIFF', 4) === 0) && (substr($head, 8, 4) === 'WEBP');
    }

    return false;
}

/**
 * The typed part of an upload, checked: the kind must be one the
 * programme's roles need, the number at most 100 characters, the date a
 * real day from today on. Pure.
 *
 * @param array  $input kind, number, valid_until (the posted form)
 * @param array  $kinds pg_volunteer_programme_kinds()
 * @param string $today Y-m-d
 * @return array answer ('' when fine, else doc_kind or doc_date), kind,
 *               number, valid_until
 */
function pg_volunteer_doc_input_check(array $input, array $kinds, $today)
{
    $text = function ($key) use ($input) {
        return is_scalar($input[$key] ?? null) ? trim(preg_replace('/\s+/u', ' ', (string) $input[$key])) : '';
    };
    $kind = $text('kind');
    $number = mb_substr($text('number'), 0, 100);
    $until = $text('valid_until');
    $out = array('answer' => '', 'kind' => $kind, 'number' => $number, 'valid_until' => $until);

    if (($kind === '') || !in_array($kind, array_map('strval', $kinds), true)) {
        $out['answer'] = 'doc_kind';
    } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $until, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        || ($until < (string) $today) || ((int) $m[1] > (int) substr((string) $today, 0, 4) + 50)) {
        $out['answer'] = 'doc_date';
    }

    return $out;
}

/**
 * The file part of an upload, checked: a whole upload, a name the blocklist
 * lets through with one of pg_volunteer_doc_types(), within the cap. Pure
 * but for the uploads' own error messages.
 *
 * @param mixed $upload   one $_FILES entry
 * @param int   $file_max bytes, pg_upload_limits()['file_max']
 * @return array answer ('' when fine, else doc_missing, doc_file or
 *               doc_size), extension
 */
function pg_volunteer_doc_file_check($upload, $file_max)
{
    if (!is_array($upload) || !isset($upload['error'], $upload['name']) || is_array($upload['error']) || is_array($upload['name'])) {
        return array('answer' => 'doc_missing', 'extension' => '');
    }

    $error = (int) $upload['error'];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return array('answer' => 'doc_missing', 'extension' => '');
    }

    if (in_array($error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        return array('answer' => 'doc_size', 'extension' => '');
    }

    if ($error !== UPLOAD_ERR_OK) {
        return array('answer' => 'doc_missing', 'extension' => '');
    }

    $name = pg_basename(str_replace('\\', '/', (string) $upload['name']));
    $dot = mb_strrpos($name, '.');
    $extension = ($dot === false) ? '' : mb_strtolower(mb_substr($name, $dot + 1));

    if (($name === '') || pg_upload_name_blocked($name) || !in_array($extension, pg_volunteer_doc_types(), true)) {
        return array('answer' => 'doc_file', 'extension' => '');
    }

    if ((int) ($upload['size'] ?? 0) > pg_volunteer_doc_cap($file_max)) {
        return array('answer' => 'doc_size', 'extension' => '');
    }

    return array('answer' => '', 'extension' => $extension);
}

/**
 * A document file's stored name: the volunteer-doc- prefix that keeps it out
 * of the panel search and the pickers, the volunteer's id and a random part,
 * so the name tells nothing of the person or the document. Pure.
 *
 * @param int    $volunteer_id
 * @param string $random    hex
 * @param string $extension
 * @return string
 */
function pg_volunteer_doc_file_name($volunteer_id, $random, $extension)
{
    return PG_VOLUNTEER_DOC_PREFIX . (int) $volunteer_id . '-' . preg_replace('/[^a-f0-9]/', '', strtolower((string) $random)) . '.' . (string) $extension;
}

/**
 * Stores a volunteer's own upload: the file into the file directory with its
 * files row, then the document row. Nothing is left behind when a step
 * fails.
 *
 * @param int   $volunteer_id
 * @param array $input  the posted form (kind, number, valid_until)
 * @param mixed $upload $_FILES['file']
 * @return string answer: doc_saved, or the refusal (doc_kind, doc_date,
 *                doc_missing, doc_file, doc_size, doc_full, doc_failed)
 */
function pg_volunteer_doc_save($volunteer_id, array $input, $upload)
{
    $volunteer = pg_volunteer_self_load($volunteer_id);

    if ($volunteer === null) {
        return 'doc_failed';
    }

    $typed = pg_volunteer_doc_input_check($input, pg_volunteer_programme_kinds($volunteer['programme']), date('Y-m-d'));

    if ($typed['answer'] !== '') {
        return $typed['answer'];
    }

    $limits = pg_upload_limits();
    $file = pg_volunteer_doc_file_check($upload, $limits['file_max']);

    if ($file['answer'] !== '') {
        return $file['answer'];
    }

    $head = is_file((string) ($upload['tmp_name'] ?? '')) ? (string) @file_get_contents((string) $upload['tmp_name'], false, null, 0, 12) : '';

    if (!pg_volunteer_doc_sniff($head, $file['extension'])) {
        return 'doc_file';
    }

    if (count($volunteer['documents']) >= PG_VOLUNTEER_DOC_MAX_COUNT) {
        return 'doc_full';
    }

    $name = get_unique_name(array('name' => pg_volunteer_doc_file_name($volunteer_id, bin2hex(random_bytes(6)), $file['extension']), 'type' => 'file'));

    if (!pg_cf_place_upload($upload, FILE_DIRECTORY_PATH . '/' . $name)) {
        return 'doc_failed';
    }

    $user_id = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) ? (int) USER_ID : 0;
    $size = (int) @filesize(FILE_DIRECTORY_PATH . '/' . $name);
    $stored = db_q(
        "INSERT INTO files (name, folder, type, size, user, design, attachment, timestamp)
        VALUES (?, '0', ?, ?, ?, '0', '1', UNIX_TIMESTAMP())",
        array($name, mb_substr($file['extension'], 0, 4), (string) $size, (string) $user_id));
    $file_id = ($stored !== false) ? (int) mysqli_insert_id(db::$con) : 0;

    if ($file_id < 1) {
        @unlink(FILE_DIRECTORY_PATH . '/' . $name);

        return 'doc_failed';
    }

    $saved = db_q(
        "INSERT INTO volunteer_documents (volunteer_id, kind, number, valid_until, file_id, added_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
        array((string) (int) $volunteer_id, $typed['kind'], $typed['number'], $typed['valid_until'], (string) $file_id, (string) $user_id));

    if ($saved === false) {
        pg_cf_remove_placed_uploads(array(array('id' => $file_id, 'name' => $name)));

        return 'doc_failed';
    }

    return 'doc_saved';
}

/**
 * The "Upload a new one" form under "My documents": the kind (those the
 * programme's roles need), the number, the valid-until date and the file,
 * as one multipart POST to volunteer.php. '' when no role needs a document.
 *
 * @param array $form      action, hidden
 * @param array $c         the classes
 * @param array $volunteer id
 * @param array $kinds     pg_volunteer_programme_kinds()
 * @return string HTML
 */
function pg_volunteer_doc_form_html(array $form, array $c, array $volunteer, array $kinds)
{
    if (!$kinds) {
        return '';
    }

    $id = 'vo_doc_' . (int) $volunteer['id'];
    $options = '';

    foreach ($kinds as $kind) {
        $options .= '<option value="' . h((string) $kind) . '">' . h((string) $kind) . '</option>';
    }

    $limits = pg_upload_limits();
    $accept = '.' . implode(',.', pg_volunteer_doc_types()) . ',application/pdf,image/jpeg,image/png,image/webp';

    return '<details class="mt-3"><summary class="' . $c['quiet'] . '">' . h(lang('Upload a new one')) . '</summary>'
        . '<form method="post" action="' . h($form['action']) . '" enctype="multipart/form-data" class="mt-3">' . $form['hidden']
        . '<input type="hidden" name="p" value="' . (int) $volunteer['id'] . '"><input type="hidden" name="action" value="document">'
        . '<div class="mb-3"><label class="' . $c['field_label'] . '" for="' . $id . '_kind">' . h(lang('Document')) . '</label>'
        . '<select class="' . $c['input'] . '" id="' . $id . '_kind" name="kind" required>' . $options . '</select></div>'
        . '<div class="mb-3"><label class="' . $c['field_label'] . '" for="' . $id . '_number">' . h(lang('Document number')) . ' <span class="' . $c['meta'] . '">' . h(lang('(optional)')) . '</span></label>'
        . '<input class="' . $c['input'] . '" type="text" id="' . $id . '_number" name="number" maxlength="100" autocomplete="off"></div>'
        . '<div class="mb-3"><label class="' . $c['field_label'] . '" for="' . $id . '_until">' . h(lang('Valid until')) . '</label>'
        . '<input class="' . $c['input'] . '" type="date" data-pg-date id="' . $id . '_until" name="valid_until" min="' . h(date('Y-m-d')) . '" required></div>'
        . '<div class="mb-3"><label class="' . $c['field_label'] . '" for="' . $id . '_file">' . h(lang('File')) . '</label>'
        . '<input class="' . $c['input'] . '" type="file" id="' . $id . '_file" name="file" accept="' . h($accept) . '" required>'
        . '<p class="' . $c['meta'] . ' mt-2 mb-0">' . h(lang(array('string' => 'A PDF or a picture, at most {var:1}. Only you and the coordinators can open it.', 'vars' => array(convert_bytes_to_string(pg_volunteer_doc_cap($limits['file_max']), 1))))) . '</p></div>'
        . '<button type="submit" class="' . $c['fill'] . '">' . h(lang('Upload')) . '</button></form></details>';
}
