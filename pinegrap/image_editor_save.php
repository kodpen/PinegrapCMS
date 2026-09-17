<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Where the image editor writes.
 *
 * The editor itself is a modal that any screen can open
 * (assets/js/image_editor_modal.js); this is the one place the picture it
 * produces reaches the disk. It takes a JSON body and answers with JSON, so a
 * refusal is something the caller can read rather than an error page it has to
 * guess at.
 *
 *   { token, mode: 'replace'|'copy', type: ''|'jpg'|'png'|'webp',
 *     data: '<data: URL>', file_id | file_name, folder_id }
 *       -> { status, mode, name, url, width, height, size }
 *
 * 'replace' writes over the file the row names. 'copy' makes a new row beside
 * it. A body with a folder and no file is a picture that does not exist yet --
 * the blank canvas the file manager offers -- and is always a copy.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

header('Content-Type: application/json; charset=utf-8');


/**
 * Answer and stop.
 *
 * Every exit from here is JSON, including the refusals. output_error() draws a
 * page with a "go back" link in it, which a fetch() reads as a syntax error and
 * reports to the operator as "unexpected token <".
 */
function image_editor_respond($payload, $status = 200)
{
    http_response_code($status);
    echo encode_json($payload);
    exit();
}

function image_editor_refuse($message, $status = 200)
{
    image_editor_respond(array('status' => 'error', 'message' => $message), $status);
}


/**
 * A data: URL, decoded onto the disk in pieces.
 *
 * base64_decode() on the whole string would hold the picture three times over
 * at once -- the encoded string, the decoded string and the copy fwrite() is
 * given -- and a photograph out of the editor is measured in megabytes. The
 * chunk is a multiple of four so that every piece is a whole number of base64
 * groups and only the last one can carry padding.
 *
 * @param string $data
 * @param string $destination
 * @return int|false bytes written, or false
 */
function image_editor_write_base64($data, $destination)
{
    $offset = strpos($data, ',');
    $offset = ($offset === false) ? 0 : ($offset + 1);

    $length = strlen($data);
    $handle = @fopen($destination, 'wb');

    if ($handle === false) {
        return false;
    }

    $written = 0;
    $chunk_size = 65532;

    while ($offset < $length) {

        $chunk = base64_decode(substr($data, $offset, $chunk_size), true);

        if (($chunk === false) || (fwrite($handle, $chunk) === false)) {
            fclose($handle);
            @unlink($destination);
            return false;
        }

        $written = $written + strlen($chunk);
        $offset = $offset + $chunk_size;
    }

    fclose($handle);

    return $written;
}


// The body is JSON, so $_POST is empty whatever happens and
// pg_post_body_discarded() -- which reads exactly that -- would call every
// request discarded. The raw stream is the only thing that can tell an upload
// the server threw away from one it never received.
$image_editor_body = file_get_contents('php://input');

if (trim((string) $image_editor_body) === '') {

    $image_editor_sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    // Something was sent and nothing arrived: PHP dropped the body before this
    // script ran, which is what post_max_size does.
    if ($image_editor_sent > 0) {
        $limits = pg_upload_limits();

        image_editor_refuse(
            lang(array(
                'string' => 'The image is larger than this server accepts in one request ({var:1}).',
                'vars' => convert_bytes_to_string($limits['post_max']))),
            413);
    }

    image_editor_refuse(lang('Invalid request.'));
}

$image_editor_request = json_decode($image_editor_body, true);

if (!is_array($image_editor_request)) {
    image_editor_refuse(lang('Invalid request.'));
}

// The CSRF check is done here rather than through validate_token_field(): that
// one reads $_POST, which a JSON body never fills, and answers with a page.
$image_editor_token = (string) ($image_editor_request['token'] ?? '');

if ((($_SESSION['software']['token'] ?? '') === '') || (hash_equals((string) $_SESSION['software']['token'], $image_editor_token) == false)) {
    image_editor_refuse(lang('Sorry, we could not accept your request because it appears that your session expired or you logged out.'), 403);
}

$image_editor_mode = (($image_editor_request['mode'] ?? '') === 'replace') ? 'replace' : 'copy';
$image_editor_type = mb_strtolower(trim((string) ($image_editor_request['type'] ?? '')));
$image_editor_data = (string) ($image_editor_request['data'] ?? '');
$image_editor_file_id = (int) ($image_editor_request['file_id'] ?? 0);
$image_editor_file_name = trim((string) ($image_editor_request['file_name'] ?? ''));
$image_editor_folder_id = (int) ($image_editor_request['folder_id'] ?? 0);

if (strpos($image_editor_data, 'base64,') === false) {
    image_editor_refuse(lang('Invalid request.'));
}

// Only what the editor can produce. The list is short on purpose: this writes
// into the web root, and "whatever extension the caller asked for" is how a
// drawing tool becomes an upload tool.
$image_editor_formats = array('jpg', 'jpeg', 'png', 'webp', 'gif');

if (($image_editor_type !== '') && (in_array($image_editor_type, $image_editor_formats, true) == false)) {
    image_editor_refuse(lang('Invalid request.'));
}


// ── What is being written over, if anything ─────────────────────────────
//
// The id wins over the name: a name is looked up across the whole site and two
// folders may hold one, while the id is the row the caller was looking at.
$image_editor_row = false;

if ($image_editor_file_id > 0) {
    $image_editor_row = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($image_editor_file_id) . "' LIMIT 1");
} elseif ($image_editor_file_name !== '') {
    $image_editor_row = db_item("SELECT id, name, folder, design FROM files WHERE name = '" . e($image_editor_file_name) . "' LIMIT 1");
}

// No row and no folder is a request about nothing.
if ((!$image_editor_row) && ($image_editor_folder_id <= 0)) {
    image_editor_refuse(lang('Sorry, we could not find the file.'));
}

$image_editor_target_folder = $image_editor_row ? (int) $image_editor_row['folder'] : $image_editor_folder_id;

if (check_edit_access($image_editor_target_folder) == false) {
    image_editor_refuse(lang('Access denied'), 403);
}

// A design file is refused in every role, which is what image_editor_edit.php
// does at its own door. Two entry points to one file cannot give two answers.
if (($image_editor_row) && ($image_editor_row['design'] == 1)) {
    image_editor_refuse(lang('Access denied'), 403);
}

// Nothing on disk to write over: the blank canvas is always a new file.
if (!$image_editor_row) {
    $image_editor_mode = 'copy';
}


// ── The name it will have ───────────────────────────────────────────────

$image_editor_source_name = $image_editor_row ? (string) $image_editor_row['name'] : $image_editor_file_name;

if (trim($image_editor_source_name) === '') {
    $image_editor_source_name = lang('New image') . '.png';
}

$image_editor_dot = mb_strrpos($image_editor_source_name, '.');
$image_editor_base = ($image_editor_dot === false) ? $image_editor_source_name : mb_substr($image_editor_source_name, 0, $image_editor_dot);
$image_editor_old_type = ($image_editor_dot === false) ? '' : mb_strtolower(mb_substr($image_editor_source_name, $image_editor_dot + 1));

// An empty type means "the one it already has", which is what the editor's
// footer calls Same format.
$image_editor_new_type = ($image_editor_type !== '') ? $image_editor_type : $image_editor_old_type;

if (in_array($image_editor_new_type, $image_editor_formats, true) == false) {
    $image_editor_new_type = 'png';
}

// An animated GIF is never written over: both image libraries read the first
// frame and write a still one back, and nothing on the screen would say why the
// animation stopped. The editor's footer already refuses to offer Replace on
// one; this is the same rule on the side that does the writing.
if (
    ($image_editor_mode === 'replace')
    && ($image_editor_old_type === 'gif')
    && pg_image_is_animated_gif(FILE_DIRECTORY_PATH . '/' . $image_editor_source_name)
) {
    image_editor_refuse(lang('An animated GIF cannot be replaced. Save it as a new file instead.'));
}

if ($image_editor_mode === 'replace') {

    $image_editor_name = ($image_editor_new_type === $image_editor_old_type)
        ? $image_editor_source_name
        : ($image_editor_base . '.' . $image_editor_new_type);

    // Replacing with a different format is a rename, and files.name is the
    // address -- so the new name has to be free before anything is written.
    if ($image_editor_name !== $image_editor_source_name) {
        $image_editor_name = prepare_file_name($image_editor_name);
        $image_editor_name = get_unique_name(array('name' => $image_editor_name, 'type' => 'file'));
    }

} else {

    $image_editor_name = prepare_file_name($image_editor_base . '.' . $image_editor_new_type);
    $image_editor_name = get_unique_name(array('name' => $image_editor_name, 'type' => 'file'));
}

if (pg_upload_name_blocked($image_editor_name)) {
    image_editor_refuse(pg_upload_blocked_message($image_editor_name));
}


// ── Writing it ──────────────────────────────────────────────────────────
//
// To a temporary file first and then renamed into place. Writing straight over
// the picture leaves half a file behind when the disk fills or the request is
// cut, and files.name is the address every page resolves -- so the file that
// answers it must never be a partial one.
$image_editor_final_path = FILE_DIRECTORY_PATH . '/' . $image_editor_name;
$image_editor_temp_path = $image_editor_final_path . '.' . getmypid() . '.tmp';

$image_editor_written = image_editor_write_base64($image_editor_data, $image_editor_temp_path);

if ($image_editor_written === false) {
    @unlink($image_editor_temp_path);
    image_editor_refuse(lang('Sorry, the file could not be saved.'));
}

// Asked of the bytes rather than of the caller: the editor is told what to
// produce but the file is what everything downstream reads.
$image_editor_size = @getimagesize($image_editor_temp_path);
$image_editor_width = isset($image_editor_size[0]) ? (int) $image_editor_size[0] : 0;
$image_editor_height = isset($image_editor_size[1]) ? (int) $image_editor_size[1] : 0;

if (($image_editor_width <= 0) || ($image_editor_height <= 0)) {
    @unlink($image_editor_temp_path);
    image_editor_refuse(lang('Sorry, the file could not be saved.'));
}

if (@rename($image_editor_temp_path, $image_editor_final_path) == false) {
    @unlink($image_editor_temp_path);
    image_editor_refuse(lang('Sorry, the file could not be saved.'));
}

$image_editor_bytes = (int) @filesize($image_editor_final_path);

// The dimension columns arrived with 2026.4.4 and are simply absent on a
// database that took the code without the upgrade.
$image_editor_has_dimensions = (bool) db_item("SHOW COLUMNS FROM files WHERE Field = 'image_width'");

$image_editor_dimension_sql = $image_editor_has_dimensions
    ? ("image_width = '" . e($image_editor_width) . "', image_height = '" . e($image_editor_height) . "',")
    : '';


if ($image_editor_mode === 'replace') {

    // The old file goes only once the new one is in place, and only when the
    // format change gave it a different name.
    if ($image_editor_name !== $image_editor_source_name) {
        @unlink(FILE_DIRECTORY_PATH . '/' . $image_editor_source_name);
    }

    // optimized = 0: these are fresh bytes, and whatever was measured about the
    // ones they replaced is no longer true of them.
    db(
        "UPDATE files
        SET name = '" . e($image_editor_name) . "',
            type = '" . e($image_editor_new_type) . "',
            size = '" . e($image_editor_bytes) . "',
            " . $image_editor_dimension_sql . "
            optimized = '0',
            user = '" . e($user['id']) . "',
            timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . e($image_editor_row['id']) . "'");

    $image_editor_id = (int) $image_editor_row['id'];

    log_activity(
        lang(array('string' => 'file ({var:1}) was updated via Image Editor', 'vars' => h($image_editor_name))),
        $_SESSION['sessionusername']);

} else {

    db(
        "INSERT INTO files (name, folder, type, size, " . ($image_editor_has_dimensions ? 'image_width, image_height, ' : '') . "user, design, optimized, timestamp)
        VALUES (
            '" . e($image_editor_name) . "',
            '" . e($image_editor_target_folder) . "',
            '" . e($image_editor_new_type) . "',
            '" . e($image_editor_bytes) . "',
            " . ($image_editor_has_dimensions ? ("'" . e($image_editor_width) . "', '" . e($image_editor_height) . "', ") : '') . "
            '" . e($user['id']) . "',
            '0',
            '0',
            UNIX_TIMESTAMP())");

    $image_editor_id = (int) mysqli_insert_id(db::$con);

    log_activity(
        lang(array('string' => 'file ({var:1}) was created via Image Editor', 'vars' => h($image_editor_name))),
        $_SESSION['sessionusername']);
}


image_editor_respond(array(
    'status' => 'success',
    'mode' => $image_editor_mode,
    'id' => $image_editor_id,
    'name' => $image_editor_name,
    // Stamped, because the browser has been told it may keep a file for a week
    // and the address has not changed.
    'url' => PATH . $image_editor_name . '?v=' . time(),
    'width' => $image_editor_width,
    'height' => $image_editor_height,
    'size' => $image_editor_bytes));
