<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a note, or all of a person's notes, leaving as files:
 *
 *   GET workspace_notes_export.php?note=12          one note as Markdown
 *   GET workspace_notes_export.php?note=12&print=1  the note as a page to print
 *   GET workspace_notes_export.php?all=1            every note as a zip (a vault)
 *
 * The person signed in gets only what they may read (includes/workspace/
 * notes.php); the files are made in includes/workspace/notes_io.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!function_exists('ws_notes_ready') || !ws_notes_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

// The reads below are long enough that the session need not be held.
session_write_close();

/**
 * Sends a file the browser saves, and stops.
 *
 * @param string $name     the file name offered
 * @param string $mime
 * @param string $content  the bytes, or '' to read $path
 * @param string $path     a file to stream and then delete
 */
function ws_notes_export_send($name, $mime, $content = '', $path = '')
{
    // A file that is not there is an error page, not an empty download;
    // checked before the attachment headers are set.
    if (($path !== '') && !is_file($path)) {
        output_error(lang('The file could not be saved.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // The name goes out twice: ASCII for old browsers, UTF-8 for the rest.
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', str_replace('"', '', $name));

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');

    if ($path !== '') {
        header('Content-Length: ' . (int) filesize($path));
        readfile($path);
        @unlink($path);
        exit();
    }

    header('Content-Length: ' . strlen($content));
    echo $content;
    exit();
}

// ── everything, as a vault ──────────────────────────────────────────────

if (!empty($_GET['all'])) {
    $zip_path = tempnam(sys_get_temp_dir(), 'pg_notes_');

    if ($zip_path === false) {
        output_error(lang('The file could not be saved.'));
    }

    $built = ws_note_export_vault($viewer, $zip_path);

    if (!$built['ok']) {
        @unlink($zip_path);
        output_error(h($built['error']) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    ws_notes_export_send('notes-' . date('Y-m-d') . '.zip', 'application/zip', '', $zip_path);
}

// ── one note ────────────────────────────────────────────────────────────

$note = ws_note_for($viewer, (int) ($_GET['note'] ?? 0), 'view');

if ($note === null) {
    output_error(lang('That note could not be found.'), 404);
}

$refs = ws_refs_resolve($viewer, ws_tokens((string) $note['body']));
$title = ws_note_title($viewer, $note);

if (empty($_GET['print'])) {
    $extra = array('source' => ws_note_export_source($viewer, $note));
    $taken = array();

    ws_notes_export_send(ws_note_export_name($title, (int) $note['id'], $taken) . '.md', 'text/markdown; charset=utf-8', ws_note_export_markdown($viewer, $note, $refs, array(), $extra));
}

// ── the note as a page to print ─────────────────────────────────────────
//
// Its own small page rather than the panel's: nothing to hide at print
// time, and the words are drawn exactly as the note screen draws them
// (ws_render_body()), only with the page's own type and table rules.

$author = ws_person_name((int) $note['user_id']);
$changed = ws_person_name((int) $note['updated_by']);
$when = date('d.m.Y H:i', (int) $note['updated_at']);
$meta = array();

if ($author !== '') {
    $meta[] = $author;
}

if (($changed !== '') && ($changed !== $author)) {
    $meta[] = lang(array('string' => 'last changed by {var:1}', 'vars' => $changed));
}

$meta[] = $when;

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo '<!DOCTYPE html>
<html lang="' . h(substr((string) lang(array('info' => '')), 0, 2)) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . h($title) . '</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; padding: 2.5rem 3rem; max-width: 52rem; font: 15px/1.55 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1f2328; background: #fff; }
  h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
  .meta { color: #6b7280; font-size: .85rem; margin-bottom: 1.5rem; }
  .tools { position: fixed; top: .75rem; right: .75rem; }
  .tools button { font: inherit; padding: .4rem .9rem; border: 1px solid #d0d7de; border-radius: 999px; background: #f6f8fa; cursor: pointer; }
  .body p { margin: 0 0 .75rem; }
  .body table { border-collapse: collapse; margin: .75rem 0; width: 100%; }
  .body th, .body td { border: 1px solid #d0d7de; padding: .3rem .55rem; text-align: left; vertical-align: top; }
  .body th { background: #f6f8fa; }
  .body pre, .body code { font: .9em/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .body pre { background: #f6f8fa; padding: .6rem .8rem; border-radius: .4rem; overflow: auto; white-space: pre-wrap; }
  .body a { color: #0a58ca; text-decoration: none; }
  .body .ws-chip { display: inline-block; padding: 0 .35em; border: 1px solid #d0d7de; border-radius: .35em; background: #f6f8fa; color: inherit; }
  .body .ws-chip i { display: none; }
  .body ul, .body ol { margin: .5rem 0; padding-left: 1.5rem; }
  .body blockquote { margin: .5rem 0; padding-left: .8rem; border-left: 3px solid #d0d7de; color: #57606a; }
  .body .ws-checklist { list-style: none; padding-left: 0; margin: .5rem 0; }
  .body .ws-check label { display: inline-flex; gap: .45em; align-items: baseline; }
  .body .ws-checked .ws-check-text { text-decoration: line-through; color: #6b7280; }
  .body .ws-check-who { display: block; font-size: .8em; color: #6b7280; margin-left: 1.6em; }
  .body .ws-code-head { font-size: .8em; color: #6b7280; }
  .body .ws-code-copy { display: none; }
  .body img { max-width: 100%; height: auto; }
  .body .ws-block-title { font-weight: 700; margin: 1rem 0 .25rem; }
  @media print {
    body { padding: 0; max-width: none; }
    .tools { display: none; }
    .body a { color: inherit; }
  }
</style>
</head>
<body>
<div class="tools"><button type="button" onclick="window.print()">' . h(lang('Print')) . '</button></div>
<h1>' . h($title) . '</h1>
<div class="meta">' . h(implode(' · ', $meta)) . '</div>
<div class="body ws-body">' . ws_render_body((string) $note['body'], $refs) . '</div>
<script>window.addEventListener("load", function () { window.print(); });</script>
</body>
</html>';
