<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - notes brought in from other note apps. The notes screen talks
 * to this address in steps; every answer is JSON ({status, message, data}):
 *
 *   POST multipart: step=upload, token, files[]
 *        stages the upload and answers what each note file would become
 *   POST JSON: { step: 'import', token, stage, mode, skip }
 *        writes the next batch and answers how far the import has got
 *   POST JSON: { step: 'finish', token, stage }
 *        points the links between the notes at them and removes the stage
 *   POST JSON: { step: 'discard', token, stage }
 *        removes the stage, nothing written
 *
 * The work is done in includes/workspace/notes_import.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * Answers and stops.
 *
 * @param array $data
 */
function ws_notes_import_answer($data)
{
    echo encode_json(array('status' => 'success', 'message' => '', 'data' => $data));
    exit();
}

/**
 * Answers with an error and stops; the screen shows the message.
 *
 * @param string $message
 * @param int    $status
 */
function ws_notes_import_refuse($message, $status = 200)
{
    http_response_code($status);
    echo encode_json(array('status' => 'error', 'message' => $message));
    exit();
}

/**
 * The files of a multipart upload named files[], one entry a file.
 *
 * @param mixed $field $_FILES['files']
 * @return array[] name, tmp_name, size, error
 */
function ws_notes_import_files($field)
{
    if (!is_array($field) || !isset($field['name'])) {
        return array();
    }

    if (!is_array($field['name'])) {
        return array($field);
    }

    $out = array();

    foreach (array_keys($field['name']) as $key) {
        if ((int) ($field['error'][$key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $out[] = array(
            'name'     => (string) $field['name'][$key],
            'tmp_name' => (string) ($field['tmp_name'][$key] ?? ''),
            'size'     => (int) ($field['size'][$key] ?? 0),
            'error'    => (int) ($field['error'][$key] ?? UPLOAD_ERR_NO_FILE),
        );
    }

    return $out;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ws_notes_import_refuse(lang('Invalid request.'), 405);
}

if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') === 0) {
    // A body over post_max_size reaches the script empty.
    if (pg_post_body_discarded()) {
        ws_notes_import_refuse(lang(array('string' => 'The upload is larger than {var:1} MB.', 'vars' => max(1, (int) floor(ws_note_import_upload_max() / 1048576)))), 413);
    }

    $request = $_POST;
} else {
    $request = json_decode((string) file_get_contents('php://input'), true);
}

if (!is_array($request)) {
    ws_notes_import_refuse(lang('Invalid request.'));
}

// Neither body fills validate_token_field()'s field the same way, so the
// token is compared here.
$token = (string) ($request['token'] ?? '');

if ((($_SESSION['software']['token'] ?? '') === '') || !hash_equals((string) $_SESSION['software']['token'], $token)) {
    ws_notes_import_refuse(lang('Sorry, we could not accept your request because it appears that your session expired or you logged out.'), 403);
}

if (!ws_ready() || !ws_enabled()) {
    ws_notes_import_refuse(lang('The workspace is not switched on.'));
}

$viewer = ws_viewer($user);

if (!$viewer['member']) {
    ws_notes_import_refuse(lang('Access denied.'), 403);
}

if (!function_exists('ws_notes_ready') || !ws_notes_ready()) {
    ws_notes_import_refuse(lang('The workspace is not installed yet: the database has to be updated first.'));
}

$step = (string) ($request['step'] ?? '');

// ── the upload: staged, then read through ───────────────────────────────

if ($step === 'upload') {
    ws_note_import_sweep();

    // One import at a time a session: an earlier stage goes.
    $earlier = (string) ($_SESSION['software']['ws_notes_import'] ?? '');

    if ($earlier !== '') {
        ws_note_import_remove(ws_note_import_stage_dir($earlier));
    }

    // The new stage's name is kept before the files are staged and read,
    // and the session let go: laying out an Evernote export or reading a
    // large zip takes a while, and every other request of this person
    // waits on the session's lock meanwhile. A stage that then fails
    // leaves a name with no folder, which the later steps answer as
    // expired.
    $upload_token = bin2hex(random_bytes(16));
    $_SESSION['software']['ws_notes_import'] = $upload_token;

    session_write_close();

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $staged = ws_note_import_stage($viewer, ws_notes_import_files($_FILES['files'] ?? null), $upload_token);

    if (!$staged['ok']) {
        ws_notes_import_refuse($staged['error']);
    }

    $stage = ws_note_import_plan($viewer, ws_note_import_load($viewer, $staged['token']));

    if (empty($stage['rows'])) {
        ws_note_import_remove($stage['dir']);
        ws_notes_import_refuse(lang('No notes were found in the upload: it holds no Markdown (.md) or text (.txt) files.'));
    }

    if (!ws_note_import_save($stage)) {
        ws_note_import_remove($stage['dir']);
        ws_notes_import_refuse(lang('The file could not be saved.'));
    }

    $rows = array();

    foreach ($stage['rows'] as $row) {
        $rows[] = array(
            'i'        => (int) $row['i'],
            'path'     => $row['path'],
            'title'    => $row['title'],
            'kind'     => $row['kind'],
            'note'     => (int) $row['note'],
            'name'     => $row['name'],
            'error'    => $row['error'],
            'warnings' => $row['warnings'],
            'files'    => (int) $row['files'],
        );
    }

    ws_notes_import_answer(array(
        'stage'    => $staged['token'],
        'name'     => $stage['name'],
        'rows'     => $rows,
        'capacity' => (int) $stage['capacity'],
        'more'     => (int) $stage['more'],
    ));
}

// ── the steps after it, on the stage this session made ──────────────────

$stage_token = (string) ($request['stage'] ?? '');
$session_stage = (string) ($_SESSION['software']['ws_notes_import'] ?? '');

session_write_close();

if (($session_stage === '') || !hash_equals($session_stage, $stage_token) || !ws_note_import_load($viewer, $stage_token)) {
    ws_notes_import_refuse(lang('The upload has expired. Start again.'));
}

$dir = ws_note_import_stage_dir($stage_token);

if ($step === 'discard') {
    ws_note_import_remove($dir);
    ws_notes_import_answer(array());
}

if (!in_array($step, array('import', 'finish'), true)) {
    ws_notes_import_refuse(lang('Invalid request.'));
}

// One request at a time works on a stage: a second one waits, then reads
// what the first wrote.
$lock = @fopen($dir . '/stage.lock', 'c');

if ($lock) {
    flock($lock, LOCK_EX);
}

$stage = ws_note_import_load($viewer, $stage_token);

if (!$stage) {
    ws_notes_import_refuse(lang('The upload has expired. Start again.'));
}

if (function_exists('set_time_limit')) {
    @set_time_limit(120);
}

if ($step === 'import') {
    $progress = ws_note_import_batch($viewer, $stage, (string) ($request['mode'] ?? 'update'), (array) ($request['skip'] ?? array()));

    if (!ws_note_import_save($stage)) {
        ws_notes_import_refuse(lang('The file could not be saved.'));
    }

    ws_notes_import_answer($progress);
}

// finish: only once every row has been through a batch.
if (!isset($stage['mode']) || ((int) $stage['next'] < count((array) ($stage['rows'] ?? array())))) {
    ws_notes_import_refuse(lang('Invalid request.'));
}

$result = ws_note_import_finish($viewer, $stage);

if (!$result['done']) {
    ws_note_import_save($stage);
    ws_notes_import_answer($result);
}

if ($lock) {
    flock($lock, LOCK_UN);
    fclose($lock);
}

ws_note_import_remove($stage['dir']);
ws_notes_import_answer($result);
