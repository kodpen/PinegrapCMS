<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's conversation leaving as a file:
 *
 *   GET workspace_channel_export.php?channel=4                   Markdown
 *   GET workspace_channel_export.php?channel=4&print=1           a page to print
 *   GET ...&from=2026-11-01&to=2026-11-09                        a date range
 *
 * The person signed in gets what they may read of the channel
 * (ws_can_read_channel()) and sees on its screen; the export is built in
 * includes/workspace/channel_export.php. Each export writes an audit line:
 * a channel may hold customers' data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_export.php');

$viewer = ws_screen_gate($user);
$back = ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.';

$channel = ws_channel((int) ($_GET['channel'] ?? 0));

if (!$channel || !ws_can_read_channel($viewer, $channel)) {
    output_error(lang('That channel could not be found.') . $back, 404);
}

$range = ws_channel_export_range((string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? ''));

if (!$range['ok']) {
    output_error(h($range['error']) . $back);
}

// The reads below are long enough that the session need not be held.
session_write_close();

$export = ws_channel_export_collect($viewer, $channel, $range);

if (!$export['ok']) {
    output_error(h($export['error']) . $back);
}

$print = !empty($_GET['print']);
$by = ws_person_name((int) $viewer['id']);
$now = time();
$head = array('channel' => (string) $channel['name'], 'range' => ws_channel_export_range_label($range), 'by' => $by, 'at' => $now);

pg_audit('export', 'ws_channel', (int) $channel['id'], array(), array(
    'format'   => $print ? 'print' : 'markdown',
    'from'     => $range['from_date'],
    'to'       => $range['to_date'],
    'messages' => (int) $export['count'],
), (string) $channel['name']);

/**
 * Sends the Markdown file the browser saves, and stops.
 *
 * @param string $name    the file name offered
 * @param string $content
 */
function ws_channel_export_send($name, $content)
{
    // The name goes out twice: ASCII for old browsers, UTF-8 for the rest.
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', str_replace('"', '', $name));

    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit();
}

if (!$print) {
    $base = (defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING : '';

    ws_channel_export_send(ws_channel_export_name($channel['name']) . '.md',
        ws_channel_export_markdown($head, $export['messages'], $export['decisions'], $export['files'], $export['refs'], $base));
}

// ── the conversation as a page to print ─────────────────────────────────
//
// Its own small page rather than the panel's, as a note's print page:
// nothing to hide at print time, and the words drawn as the channel screen
// draws them.

$messages_html = '';
$day = '';

foreach ($export['messages'] as $message) {
    $date = date('d.m.Y', (int) $message['timestamp']);

    if ($date !== $day) {
        $day = $date;
        $messages_html .= '<h3 class="day">' . h($date) . '</h3>';
    }

    $messages_html .= ws_channel_export_print_message($message, 'H:i');
}

if ($messages_html === '') {
    $messages_html = '<p class="meta">' . h(lang('No messages in this range.')) . '</p>';
}

$decisions_html = '';

foreach ($export['decisions'] as $decision) {
    $decisions_html .= ws_channel_export_print_message($decision, 'd.m.Y H:i');
}

$files_html = '';

foreach ($export['files'] as $file) {
    if (!empty($file['file']['name'])) {
        $files_html .= '<li><a href="' . h((string) $file['file']['url']) . '">' . h((string) $file['file']['name']) . '</a> — '
            . h(ws_channel_export_sender($file) . ', ' . date('d.m.Y', (int) $file['timestamp'])) . '</li>';
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

echo '<!DOCTYPE html>
<html lang="' . h(substr((string) lang(array('info' => '')), 0, 2)) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . h($head['channel']) . '</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; padding: 2.5rem 3rem; max-width: 52rem; font: 15px/1.55 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1f2328; background: #fff; }
  h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
  h2 { font-size: 1.25rem; margin: 2rem 0 .75rem; padding-bottom: .25rem; border-bottom: 1px solid #d0d7de; }
  h3.day { font-size: .9rem; color: #6b7280; margin: 1.5rem 0 .5rem; }
  .meta { color: #6b7280; font-size: .85rem; }
  .tools { position: fixed; top: .75rem; right: .75rem; }
  .tools button { font: inherit; padding: .4rem .9rem; border: 1px solid #d0d7de; border-radius: 999px; background: #f6f8fa; cursor: pointer; }
  .msg { margin: 0 0 .9rem; break-inside: avoid; }
  .who { font-size: .9rem; }
  .who strong { color: #1f2328; }
  .thread { margin: .5rem 0 0 .25rem; padding-left: .8rem; border-left: 3px solid #d0d7de; }
  .thread-title { font-weight: 600; font-size: .85rem; color: #57606a; margin-bottom: .4rem; }
  .file a, .files a, .body a { color: #0a58ca; text-decoration: none; }
  .body p { margin: 0 0 .5rem; }
  .body table { border-collapse: collapse; margin: .5rem 0; width: 100%; }
  .body th, .body td { border: 1px solid #d0d7de; padding: .3rem .55rem; text-align: left; vertical-align: top; }
  .body pre, .body code { font: .9em/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .body pre { background: #f6f8fa; padding: .6rem .8rem; border-radius: .4rem; white-space: pre-wrap; }
  .body .ws-chip { display: inline-block; padding: 0 .35em; border: 1px solid #d0d7de; border-radius: .35em; background: #f6f8fa; color: inherit; }
  .body i.bi, .body button, .body .ws-code-copy { display: none; }
  .body .ws-quote { margin: .5rem 0; padding-left: .8rem; border-left: 3px solid #d0d7de; color: #57606a; }
  .body .ws-checklist { list-style: none; padding-left: 0; }
  .body img { max-width: 100%; max-height: 20rem; height: auto; }
  @media print {
    body { padding: 0; max-width: none; }
    .tools { display: none; }
    a { color: inherit !important; }
  }
</style>
</head>
<body>
<div class="tools"><button type="button" onclick="window.print()">' . h(lang('Print')) . '</button></div>
<h1>' . h($head['channel']) . '</h1>
<div class="meta">' . h(lang(array('string' => 'Range: {var:1}', 'vars' => $head['range']))) . '<br>'
    . h(lang(array('string' => 'Exported by {var:1} on {var:2}', 'vars' => array($by, date('d.m.Y H:i', $now))))) . '</div>'
    . (($decisions_html !== '') ? '<h2>' . h(lang('Decisions')) . '</h2>' . $decisions_html : '')
    . '<h2>' . h(lang('Messages')) . '</h2>' . $messages_html
    . (($files_html !== '') ? '<h2>' . h(lang('Files')) . '</h2><ul class="files">' . $files_html . '</ul>' : '') . '
<script>window.addEventListener("load", function () { window.print(); });</script>
</body>
</html>';
