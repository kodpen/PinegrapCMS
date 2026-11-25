<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The agency summary of the launch journey: the "What we need from you"
 * lines the agency writes (table launch_summary_lines), the signed link the
 * customer opens them by (launch_summary.php), the customer's text answers,
 * the files a file line asks for, and the bell line an answer gives the
 * line's writer.
 *
 * A line's answer_kind says what it asks for: 0 text, 1 one file, 2 several
 * files. A file line keeps the ids of the files sent as a JSON list in its
 * answer. The files are rows of the files table in the private folder "From
 * the customer", served only by get_file.php to a signed-in panel user; their
 * type is read from their first bytes, never from their name.
 *
 * The link's state is the "summary" key of the journey's config_text row
 * (PG_LAUNCH_JOURNEY_ROW): when it was issued, the sha256 of its token, when
 * it ends and when it was withdrawn. The token is an HMAC of the issue time
 * under the site's key, so the same link can be copied again from the
 * drawer; a new link ends the old one. The customer's page shows only the
 * lines and the steps' states, never a setting's value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_LAUNCH_SUMMARY_LINE_MAX', 500);
define('PG_LAUNCH_SUMMARY_ANSWER_MAX', 5000);
define('PG_LAUNCH_SUMMARY_LINES_MAX', 50);
define('PG_LAUNCH_SUMMARY_FILES_MAX', 20);
define('PG_LAUNCH_SUMMARY_KIND_TEXT', 0);
define('PG_LAUNCH_SUMMARY_KIND_ONE', 1);
define('PG_LAUNCH_SUMMARY_KIND_SEVERAL', 2);

/**
 * How long a new link works, in days, as the drawer offers it; the first is
 * the one picked.
 *
 * @return int[]
 */
function pg_launch_summary_expiry_days()
{
    return array(30, 7, 14, 90);
}

/**
 * A link's token: HMAC-SHA256 of its issue time under the site's key, 64
 * hex; '' without a key or an issue time. Pure.
 *
 * @param int    $issued_at
 * @param string $key
 * @return string
 */
function pg_launch_summary_token($issued_at, $key)
{
    $issued_at = (int) $issued_at;
    $key = (string) $key;

    if (($issued_at <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'launch-summary:' . $issued_at, $key);
}

/**
 * The link's stored state, made whole, from the journey's row. Pure.
 *
 * @param string $json the config_text value
 * @return array issued_at, hash, expires_at, withdrawn_at
 */
function pg_launch_summary_link_read($json)
{
    $row = json_decode((string) $json, true);
    $link = (is_array($row) && isset($row['summary']) && is_array($row['summary'])) ? $row['summary'] : array();
    $hash = isset($link['hash']) ? (string) $link['hash'] : '';

    return array(
        'issued_at'    => isset($link['issued_at']) ? max(0, (int) $link['issued_at']) : 0,
        'hash'         => (preg_match('/^[0-9a-f]{64}$/', $hash) === 1) ? $hash : '',
        'expires_at'   => isset($link['expires_at']) ? max(0, (int) $link['expires_at']) : 0,
        'withdrawn_at' => isset($link['withdrawn_at']) ? max(0, (int) $link['withdrawn_at']) : 0,
    );
}

/**
 * The journey's row with the link's state replaced, every other key kept as
 * it was. Pure.
 *
 * @param string $json
 * @param array  $link issued_at, hash, expires_at, withdrawn_at
 * @return string
 */
function pg_launch_summary_link_change($json, $link)
{
    $row = json_decode((string) $json, true);
    $row = is_array($row) ? $row : array();
    $row['summary'] = pg_launch_summary_link_read(json_encode(array('summary' => $link)));

    return (string) json_encode($row);
}

/**
 * A new link's stored state. Pure.
 *
 * @param int    $now
 * @param int    $days one of pg_launch_summary_expiry_days(), else the first
 * @param string $key
 * @return array issued_at, hash, expires_at, withdrawn_at; hash '' without a key
 */
function pg_launch_summary_link_new($now, $days, $key)
{
    $days = in_array((int) $days, pg_launch_summary_expiry_days(), true) ? (int) $days : pg_launch_summary_expiry_days()[0];
    $token = pg_launch_summary_token($now, $key);

    return array(
        'issued_at'    => (int) $now,
        'hash'         => ($token !== '') ? hash('sha256', $token) : '',
        'expires_at'   => (int) $now + $days * 86400,
        'withdrawn_at' => 0,
    );
}

/**
 * What a token opens: invalid (no link, or not this one), withdrawn,
 * expired or open. A withdrawn link keeps its hash, so its page says so
 * until a new link replaces it. Pure.
 *
 * @param array  $link  pg_launch_summary_link_read()
 * @param string $token from the address
 * @param int    $now
 * @return string
 */
function pg_launch_summary_link_state($link, $token, $now)
{
    $token = (string) $token;

    if (($link['hash'] === '') || (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || !hash_equals($link['hash'], hash('sha256', $token))) {
        return 'invalid';
    }

    if ($link['withdrawn_at'] > 0) {
        return 'withdrawn';
    }

    return ($link['expires_at'] > (int) $now) ? 'open' : 'expired';
}

/**
 * A line's text as kept: one line, spaces folded, at most 500 characters;
 * '' for nothing. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_launch_summary_line_clean($text)
{
    $text = is_string($text) ? trim(preg_replace('/\s+/u', ' ', $text)) : '';

    return mb_substr($text, 0, PG_LAUNCH_SUMMARY_LINE_MAX, 'UTF-8');
}

/**
 * An answer as kept: trimmed, line breaks kept, at most 5,000 characters.
 * Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_launch_summary_answer_clean($text)
{
    $text = is_string($text) ? trim(str_replace(array("\r\n", "\r"), "\n", $text)) : '';

    return mb_substr($text, 0, PG_LAUNCH_SUMMARY_ANSWER_MAX, 'UTF-8');
}

/**
 * A line's kind as kept: 0 text, 1 one file, 2 several files; anything else
 * is text. Pure.
 *
 * @param mixed $kind
 * @return int
 */
function pg_launch_summary_kind_clean($kind)
{
    $kind = is_numeric($kind) ? (int) $kind : 0;

    return in_array($kind, array(PG_LAUNCH_SUMMARY_KIND_ONE, PG_LAUNCH_SUMMARY_KIND_SEVERAL), true) ? $kind : PG_LAUNCH_SUMMARY_KIND_TEXT;
}

/**
 * The most files a line of this kind holds: 0 for a text line. Pure.
 *
 * @param int $kind
 * @return int
 */
function pg_launch_summary_kind_files_max($kind)
{
    $kind = pg_launch_summary_kind_clean($kind);

    if ($kind === PG_LAUNCH_SUMMARY_KIND_ONE) {
        return 1;
    }

    return ($kind === PG_LAUNCH_SUMMARY_KIND_SEVERAL) ? PG_LAUNCH_SUMMARY_FILES_MAX : 0;
}

/**
 * The file ids a file line's answer holds: positive, each once, in the order
 * sent; an answer that is not a JSON list holds none. Pure.
 *
 * @param mixed $answer
 * @return int[]
 */
function pg_launch_summary_file_ids($answer)
{
    $list = is_string($answer) ? json_decode($answer, true) : null;
    $ids = array();

    if (!is_array($list)) {
        return $ids;
    }

    foreach ($list as $id) {
        $id = (is_int($id) || (is_string($id) && ctype_digit($id))) ? (int) $id : 0;

        if (($id > 0) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * The extension a sent file is kept under, read from its first bytes:
 * jpg, png, gif and webp pictures, PDF, zip and office documents. An office
 * document's container (zip for docx, xlsx, pptx and the OpenDocument kinds,
 * the compound file for doc, xls and ppt) is read from the bytes and the
 * kind inside it from the name; a name that does not fit the container
 * keeps it as zip, or is refused for a compound file. Anything else, svg
 * among it, is refused with ''. Pure.
 *
 * @param string $head the file's first bytes (16 are enough)
 * @param string $name the name it was sent under
 * @return string
 */
function pg_launch_summary_file_type($head, $name)
{
    $head = (string) $head;
    $dot = strrpos((string) $name, '.');
    $named = ($dot === false) ? '' : strtolower(substr((string) $name, $dot + 1));

    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }

    if (strncmp($head, "\x89PNG\r\n\x1A\n", 8) === 0) {
        return 'png';
    }

    if ((strncmp($head, 'GIF87a', 6) === 0) || (strncmp($head, 'GIF89a', 6) === 0)) {
        return 'gif';
    }

    if ((strncmp($head, 'RIFF', 4) === 0) && (substr($head, 8, 4) === 'WEBP')) {
        return 'webp';
    }

    if (strncmp($head, '%PDF-', 5) === 0) {
        return 'pdf';
    }

    if ((strncmp($head, "PK\x03\x04", 4) === 0) || (strncmp($head, "PK\x05\x06", 4) === 0)) {
        return in_array($named, array('docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'), true) ? $named : 'zip';
    }

    if (strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0) {
        return in_array($named, array('doc', 'xls', 'ppt'), true) ? $named : '';
    }

    return '';
}

/**
 * The name a sent file is stored under: the name it came with, cut to its
 * last segment and 60 characters, a random part so the address cannot be
 * guessed, and the extension read from its bytes, never its own. Pure but
 * for the random part when none is given.
 *
 * @param string $name      the name it was sent under
 * @param string $extension pg_launch_summary_file_type()
 * @param string $random    8 hex characters ('' to make them)
 * @return string
 */
function pg_launch_summary_file_name($name, $extension, $random = '')
{
    $stem = pg_file_name_stem(pg_basename(str_replace('\\', '/', (string) $name)));
    $stem = trim(preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $stem), '-_');
    $stem = mb_substr(($stem !== '') ? $stem : 'file', 0, 60, 'UTF-8');
    $random = (preg_match('/^[0-9a-f]{8}$/', (string) $random) === 1) ? (string) $random : bin2hex(random_bytes(4));

    return $stem . '-' . $random . '.' . $extension;
}

/**
 * Whether files may be added to a line now: '' when they may, else link
 * (the link is not open), kind (the line asks for text), none (nothing was
 * chosen) or count (the line would hold more than its kind takes: one, or
 * 20). Pure.
 *
 * @param string $state  pg_launch_summary_link_state()
 * @param int    $kind   the line's answer_kind
 * @param int    $have   files the line holds now
 * @param int    $adding files sent with this request
 * @return string
 */
function pg_launch_summary_upload_gate($state, $kind, $have, $adding)
{
    if ($state !== 'open') {
        return 'link';
    }

    $max = pg_launch_summary_kind_files_max($kind);

    if ($max === 0) {
        return 'kind';
    }

    if ((int) $adding <= 0) {
        return 'none';
    }

    return ((int) $have + (int) $adding > $max) ? 'count' : '';
}

/**
 * The entries of one multiple file field ($_FILES['files']), one array per
 * file chosen; a field with nothing chosen gives none. Pure.
 *
 * @param mixed $field
 * @return array list of name, tmp_name, size, error
 */
function pg_launch_summary_uploads($field)
{
    $out = array();

    if (!is_array($field) || !isset($field['name'], $field['tmp_name'], $field['size'], $field['error'])) {
        return $out;
    }

    $names = (array) $field['name'];

    foreach (array_keys($names) as $i) {
        $error = (int) (((array) $field['error'])[$i] ?? UPLOAD_ERR_NO_FILE);

        if (($error === UPLOAD_ERR_NO_FILE) || is_array($names[$i])) {
            continue;
        }

        $out[] = array(
            'name'     => (string) $names[$i],
            'tmp_name' => (string) (((array) $field['tmp_name'])[$i] ?? ''),
            'size'     => (int) (((array) $field['size'])[$i] ?? 0),
            'error'    => $error,
        );
    }

    return $out;
}

/**
 * The names of the set-up card's steps that are done, in the card's order:
 * the only state of the site the customer's page shows. Pure.
 *
 * @param array $rows pg_launch_journey_card()['rows']
 * @return string[]
 */
function pg_launch_summary_done_steps($rows)
{
    $done = array();

    foreach (pg_launch_summary_steps($rows) as $step) {
        if ($step['done']) {
            $done[] = $step['name'];
        }
    }

    return $done;
}

/**
 * Every step the set-up card shows, each with its name and whether it is
 * done: the done ones first, then the open ones, each group in the card's
 * order. A step hidden on the card is not among the rows, so it is not
 * listed. Nothing else of a row is kept: never its line, a blocker's text
 * or a setting's value. Pure.
 *
 * @param array $rows pg_launch_journey_card()['rows']
 * @return array list of name, done
 */
function pg_launch_summary_steps($rows)
{
    $done = array();
    $open = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['name'])) {
            continue;
        }

        if (!empty($row['done'])) {
            $done[] = array('name' => (string) $row['name'], 'done' => true);
        } else {
            $open[] = array('name' => (string) $row['name'], 'done' => false);
        }
    }

    return array_merge($done, $open);
}

/**
 * The drawer's words about the link. Pure.
 *
 * @param array $link
 * @param int   $now
 * @return array state (none, open, expired, withdrawn), text
 */
function pg_launch_summary_link_face($link, $now)
{
    if ($link['hash'] === '') {
        return array('state' => 'none', 'text' => lang('No link yet. Make one to send to the customer.'));
    }

    if ($link['withdrawn_at'] > 0) {
        return array('state' => 'withdrawn', 'text' => lang('Withdrawn. Make a new link to share the summary again.'));
    }

    if ($link['expires_at'] <= (int) $now) {
        return array('state' => 'expired', 'text' => lang('Ended. Make a new link to share the summary again.'));
    }

    return array('state' => 'open', 'text' => lang(array('string' => 'Works until {var:1}', 'vars' => array(pg_launch_summary_date($link['expires_at'])))));
}

/**
 * A day as the panel writes dates, as plain text.
 *
 * @param int $time
 * @return string
 */
function pg_launch_summary_date($time)
{
    // get_absolute_time() answers a <time> element; the drawer prints text.
    return function_exists('get_absolute_time') ? trim(html_entity_decode(strip_tags((string) get_absolute_time(array('timestamp' => (int) $time, 'size' => 'long'))), ENT_QUOTES, 'UTF-8')) : date('Y-m-d', (int) $time);
}

/**
 * Whether the lines table is there (the 2026.11.0 upgrade ran).
 *
 * @return bool
 */
function pg_launch_summary_ready()
{
    return pg_schema_has('launch_summary_lines');
}

/**
 * The lines, in their order.
 *
 * @return array rows of launch_summary_lines
 */
function pg_launch_summary_lines()
{
    if (!pg_launch_summary_ready()) {
        return array();
    }

    $kind = pg_launch_summary_kind_ready() ? 'answer_kind' : "'0' AS answer_kind";
    $rows = db_items("SELECT id, line_text, " . $kind . ", answer, answered_at, created_by FROM launch_summary_lines ORDER BY sort_order, id");

    return is_array($rows) ? $rows : array();
}

/**
 * Whether lines can ask for files (the 2026.11.0 step 11.0.278 ran).
 *
 * @return bool
 */
function pg_launch_summary_kind_ready()
{
    return pg_schema_has('launch_summary_lines', 'answer_kind');
}

/**
 * One line, null when it is not there.
 *
 * @param int $id
 * @return array|null
 */
function pg_launch_summary_line($id)
{
    if (((int) $id <= 0) || !pg_launch_summary_ready()) {
        return null;
    }

    $kind = pg_launch_summary_kind_ready() ? 'answer_kind' : "'0' AS answer_kind";
    $row = db_item("SELECT id, line_text, " . $kind . ", answer, answered_at, created_by FROM launch_summary_lines WHERE id = '" . (int) $id . "'");

    return $row ? $row : null;
}

/**
 * Sets what a line asks for. A line that changes kind starts unanswered:
 * a text answer is not a file list, and the files a file line held stay in
 * the "From the customer" folder.
 *
 * @param int $id
 * @param int $kind
 * @return bool
 */
function pg_launch_summary_line_kind($id, $kind)
{
    $kind = pg_launch_summary_kind_clean($kind);
    $line = pg_launch_summary_kind_ready() ? pg_launch_summary_line($id) : null;

    if (!$line) {
        return false;
    }

    if ((int) $line['answer_kind'] === $kind) {
        return true;
    }

    return (bool) db_q("UPDATE launch_summary_lines SET answer_kind = ?, answer = NULL, answered_at = 0, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array($kind, (int) $id));
}

/**
 * The files a file line holds now: the ids of its answer that are still
 * rows of the files table, in the order sent. A file deleted in the file
 * manager is left out.
 *
 * @param array $line a launch_summary_lines row
 * @return array list of id, name, size
 */
function pg_launch_summary_line_files($line)
{
    $ids = pg_launch_summary_file_ids($line['answer'] ?? null);

    if (!$ids) {
        return array();
    }

    $rows = db_items("SELECT id, name, size FROM files WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
    $by_id = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        $by_id[(int) $row['id']] = array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'size' => (int) $row['size']);
    }

    $files = array();

    foreach ($ids as $id) {
        if (isset($by_id[$id])) {
            $files[] = $by_id[$id];
        }
    }

    return $files;
}

/**
 * Writes a file line's list; an empty list makes it unanswered again.
 *
 * @param int   $id
 * @param int[] $ids
 * @return bool
 */
function pg_launch_summary_line_files_store($id, $ids)
{
    if (!$ids) {
        return (bool) db_q("UPDATE launch_summary_lines SET answer = NULL, answered_at = 0, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array((int) $id));
    }

    return (bool) db_q("UPDATE launch_summary_lines SET answer = ?, answered_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ?",
        array(json_encode(array_values(array_map('intval', $ids))), (int) $id));
}

/**
 * The folder the customer's files are kept in: "From the customer" under the
 * top folder of the file manager, private, made on the first file. Private,
 * so get_file.php serves its files only to a signed-in panel user.
 *
 * @return int 0 when the file manager has no top folder
 */
function pg_launch_summary_folder_id()
{
    $top = function_exists('getPublicRootFolderId') ? (int) getPublicRootFolderId() : 0;

    if ($top <= 0) {
        $top = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    }

    if ($top <= 0) {
        return 0;
    }

    $quoted = array();

    foreach (array_unique(array(lang('From the customer'), 'From the customer')) as $name) {
        $quoted[] = "'" . e($name) . "'";
    }

    // Only a private folder of that name: one an operator made public is not
    // used for the customer's files.
    $existing = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '" . $top . "' AND folder_name IN (" . implode(', ', $quoted) . ")
        AND folder_access_control_type = 'private' AND folder_archived = '0' ORDER BY folder_id LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . $top . "'");

    db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type, folder_archived, folder_timestamp, folder_user)
        VALUES ('" . e(lang('From the customer')) . "', '" . $top . "', '" . ($level + 1) . "', '0', 'private', '0', UNIX_TIMESTAMP(), '0')");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Keeps the files the customer sent for a file line and tells the line's
 * writer. Each file is checked on its own: the server's upload limit, its
 * type read from its first bytes; a refused one is named and the rest kept.
 * The link's state and the line's kind are checked here too, whatever the
 * page drew.
 *
 * @param string $state   pg_launch_summary_link_state() of the request's link
 * @param int    $id      the line
 * @param array  $uploads pg_launch_summary_uploads()
 * @return array answer ('' when something was kept, else link, kind, none,
 *               count, missing or failed), kept (count), refused (messages)
 */
function pg_launch_summary_files_add($state, $id, $uploads)
{
    $out = array('answer' => '', 'kept' => 0, 'refused' => array());
    $line = pg_launch_summary_kind_ready() ? pg_launch_summary_line($id) : null;

    if (!$line) {
        $out['answer'] = ($state === 'open') ? 'missing' : 'link';
        return $out;
    }

    $have = pg_launch_summary_line_files($line);
    $gate = pg_launch_summary_upload_gate($state, (int) $line['answer_kind'], count($have), count($uploads));

    if ($gate !== '') {
        $out['answer'] = $gate;
        return $out;
    }

    $folder = pg_launch_summary_folder_id();

    if ($folder <= 0) {
        $out['answer'] = 'failed';
        return $out;
    }

    $limits = pg_upload_limits();
    $ids = array();

    foreach ($have as $file) {
        $ids[] = $file['id'];
    }

    foreach ($uploads as $upload) {
        $name = pg_basename(str_replace('\\', '/', $upload['name']));
        $refusal = pg_upload_file_refusal($upload, $limits['file_max']);

        if ($refusal !== '') {
            $out['refused'][] = $refusal;
            continue;
        }

        if (!is_uploaded_file($upload['tmp_name'])) {
            $out['refused'][] = lang(array('string' => 'The server could not accept {var:1}. Please try again.', 'vars' => array($name)));
            continue;
        }

        $handle = @fopen($upload['tmp_name'], 'rb');
        $head = $handle ? (string) fread($handle, 16) : '';

        if ($handle) {
            fclose($handle);
        }

        $extension = pg_launch_summary_file_type($head, $name);

        if ($extension === '') {
            $out['refused'][] = lang(array('string' => '{var:1} was not sent: send pictures (JPG, PNG, GIF, WebP), PDF, office documents or ZIP files.', 'vars' => array($name)));
            continue;
        }

        $stored = pg_file_store_upload($upload['tmp_name'], pg_launch_summary_file_name($name, $extension), $upload['size'], array(
            'folder'      => $folder,
            'description' => mb_substr(lang('From the customer') . ': ' . (string) $line['line_text'], 0, 250, 'UTF-8'),
            'file_max'    => $limits['file_max'],
        ));

        if (empty($stored['ok'])) {
            $out['refused'][] = (string) $stored['message'];
            continue;
        }

        $ids[] = (int) $stored['file']['id'];
        $out['kept']++;
    }

    if ($out['kept'] === 0) {
        $out['answer'] = 'failed';
        return $out;
    }

    pg_launch_summary_line_files_store($id, $ids);
    pg_launch_summary_bell($line, count($ids));

    return $out;
}

/**
 * Deletes a file the customer sent for a line, from the line's list and from
 * the file manager. A file that is not on the line is left alone.
 *
 * @param string $state   pg_launch_summary_link_state() of the request's link
 * @param int    $id      the line
 * @param int    $file_id
 * @return string removed, link or missing
 */
function pg_launch_summary_file_remove($state, $id, $file_id)
{
    if ($state !== 'open') {
        return 'link';
    }

    $line = pg_launch_summary_kind_ready() ? pg_launch_summary_line($id) : null;

    if (!$line || (pg_launch_summary_kind_files_max((int) $line['answer_kind']) === 0)) {
        return 'missing';
    }

    $ids = array();
    $found = null;

    foreach (pg_launch_summary_line_files($line) as $file) {
        if ($file['id'] === (int) $file_id) {
            $found = $file;
        } else {
            $ids[] = $file['id'];
        }
    }

    if ($found === null) {
        return 'missing';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
    pg_delete_file_record($found);
    log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => array($found['name']))), lang('Customer'));
    pg_launch_summary_line_files_store($id, $ids);

    return 'removed';
}

/**
 * Adds a line at the end.
 *
 * @param string $text
 * @param int    $user_id its writer, whose bell hears the answer
 * @return bool false for an empty line, a full list or a failed write
 */
function pg_launch_summary_line_add($text, $user_id)
{
    $text = pg_launch_summary_line_clean($text);

    if (($text === '') || !pg_launch_summary_ready() || (count(pg_launch_summary_lines()) >= PG_LAUNCH_SUMMARY_LINES_MAX)) {
        return false;
    }

    $order = (int) db_value("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM launch_summary_lines");

    return (bool) db_q("INSERT INTO launch_summary_lines (line_text, answer, answered_at, sort_order, created_by, created_at, updated_at)
        VALUES (?, NULL, 0, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array($text, $order, (int) $user_id));
}

/**
 * Removes a line, its answer with it.
 *
 * @param int $id
 * @return bool
 */
function pg_launch_summary_line_remove($id)
{
    if (((int) $id <= 0) || !pg_launch_summary_ready()) {
        return false;
    }

    return (bool) db_q("DELETE FROM launch_summary_lines WHERE id = ?", array((int) $id));
}

/**
 * Keeps the customer's answer to a line and tells the line's writer. An
 * answer sent again unchanged writes nothing.
 *
 * @param int    $id
 * @param string $answer
 * @return string saved, same, empty, kind or missing
 */
function pg_launch_summary_answer_save($id, $answer)
{
    $answer = pg_launch_summary_answer_clean($answer);

    if ($answer === '') {
        return 'empty';
    }

    $line = pg_launch_summary_line($id);

    if (!$line) {
        return 'missing';
    }

    // A file line takes files only (pg_launch_summary_files_add()).
    if (pg_launch_summary_kind_clean($line['answer_kind']) !== PG_LAUNCH_SUMMARY_KIND_TEXT) {
        return 'kind';
    }

    if ((string) $line['answer'] === $answer) {
        return 'same';
    }

    db_q("UPDATE launch_summary_lines SET answer = ?, answered_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ?", array($answer, (int) $id));
    pg_launch_summary_bell($line);

    return 'saved';
}

/**
 * The bell line for an answer, addressed to the line's writer: at most one
 * an hour for a line, so an answer changed a few times is told once. For a
 * file line form_id carries how many files the line holds.
 *
 * @param array $line  id, line_text, created_by
 * @param int   $files the files a file line holds now; 0 for a text answer
 * @return bool whether a line was written
 */
function pg_launch_summary_bell($line, $files = 0)
{
    $writer = (int) $line['created_by'];

    if ($writer <= 0) {
        return false;
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (!pg_notification_targets_available()) {
        return false;
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded((string) (int) $line['id'], 'launch-summary-bell', 1, 3600)) {
        return false;
    }

    return (bool) db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, form_id, user, readed, timestamp)
        VALUES ('launch_answer', 'info', ?, ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
        array(mb_substr((string) $line['line_text'], 0, 250, 'UTF-8'), $writer, (int) $line['id'], max(0, (int) $files)));
}

/**
 * The link's stored state.
 *
 * @return array pg_launch_summary_link_read()
 */
function pg_launch_summary_link()
{
    return pg_launch_summary_link_read(pg_config_text(PG_LAUNCH_JOURNEY_ROW));
}

/**
 * Writes the link's state into the journey's row, read fresh.
 *
 * @param array $link
 * @return bool
 */
function pg_launch_summary_link_store($link)
{
    $current = pg_schema_has('config_text')
        ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_LAUNCH_JOURNEY_ROW) . "'")
        : '';

    return pg_config_text_save(PG_LAUNCH_JOURNEY_ROW, pg_launch_summary_link_change($current, $link));
}

/**
 * The customer's address of a link, '' with no working link or no key.
 *
 * @param array $link
 * @return string
 */
function pg_launch_summary_url($link)
{
    $token = pg_launch_summary_token($link['issued_at'], pg_booking_key());

    if (($token === '') || !hash_equals((string) $link['hash'], hash('sha256', $token))) {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/launch_summary.php?t=' . $token;
}

/**
 * What the drawer draws: the lines and the link.
 *
 * @return array lines (id, text, kind, answer, files, answered), link
 *               (state, text, url), days, ready, kinds (whether a line can
 *               ask for files)
 */
function pg_launch_summary_drawer_data()
{
    $now = time();
    $lines = array();

    foreach (pg_launch_summary_lines() as $row) {
        $kind = pg_launch_summary_kind_clean($row['answer_kind']);
        $files = array();

        if ($kind !== PG_LAUNCH_SUMMARY_KIND_TEXT) {
            foreach (pg_launch_summary_line_files($row) as $file) {
                $files[] = array('name' => $file['name'], 'size' => convert_bytes_to_string($file['size'], 'auto'), 'url' => PATH . encode_url_path($file['name']));
            }
        }

        $lines[] = array(
            'id'       => (int) $row['id'],
            'text'     => (string) $row['line_text'],
            'kind'     => $kind,
            'answer'   => ($kind === PG_LAUNCH_SUMMARY_KIND_TEXT) ? (string) $row['answer'] : '',
            'files'    => $files,
            'answered' => ((int) $row['answered_at'] > 0) ? lang(array('string' => 'Answered {var:1}', 'vars' => array(pg_launch_summary_date($row['answered_at'])))) : lang('Not answered yet'),
        );
    }

    $link = pg_launch_summary_link();
    $face = pg_launch_summary_link_face($link, $now);

    return array(
        'ready' => pg_launch_summary_ready() && (pg_booking_key() !== ''),
        'lines' => $lines,
        'full'  => (count($lines) >= PG_LAUNCH_SUMMARY_LINES_MAX),
        'link'  => $face + array('url' => ($face['state'] === 'open') ? pg_launch_summary_url($link) : ''),
        'days'  => pg_launch_summary_expiry_days(),
        'kinds' => pg_launch_summary_kind_ready(),
    );
}

/**
 * The bell's words for an answer: the line's text from the row's title.
 *
 * @param array $notification
 * @return array title, short, brief, url
 */
function pg_launch_summary_bell_display($notification)
{
    $line = isset($notification['title']) ? (string) $notification['title'] : '';
    $files = isset($notification['form_id']) ? (int) $notification['form_id'] : 0;

    if ($files > 0) {
        return array(
            'title' => lang(array('string' => 'The customer sent files for "{var:1}" ({var:2} in all)', 'vars' => array($line, $files))),
            'short' => lang('The customer sent files'),
            'brief' => $line,
            'url'   => 'welcome.php#launch-summary',
        );
    }

    return array(
        'title' => lang(array('string' => 'The customer answered "{var:1}"', 'vars' => array($line))),
        'short' => lang('The customer answered'),
        'brief' => $line,
        'url'   => 'welcome.php#launch-summary',
    );
}
