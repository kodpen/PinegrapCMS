<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address of a share link (includes/workspace/share_links.php): one item
 * of an agent channel, read by whoever holds the address, with no account
 * and no session. The token in the address is the credential, and it opens
 * this one item.
 *
 *   GET workspace_share.php?t=<token>[&raw=1]
 *
 * A file is sent with its type: a picture, sound, video or PDF shown in the
 * page, anything else (and anything a browser could run) downloaded. A
 * channel's note is a read-only page, and with &raw=1 its Markdown. Every
 * fault (a bad shape, an unknown token, a link ended, taken back or closed)
 * is the same 404 line, and a token no link has counts against the address.
 * Counted on the firewall's sensitive allowance (waf_sensitive_scripts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session and no cookie: the token is the credential, and a panel
// session in the same browser must never decide what the address sends.
define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Nothing is indexed, kept by a browser or a proxy, framed by another site
// or passed on as the referrer (the token is in the address).
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');
header('X-Frame-Options: DENY');

$answer = function_exists('ws_share_link_answer') ? ws_share_link_answer($_GET['t'] ?? '') : null;

if (!is_array($answer)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    pg_send_nosniff();

    echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h(lang('This link is not valid.')) . '</title>
</head>
<body>
<p>' . h(lang('This link is not valid.')) . '</p>
</body>
</html>';
    exit;
}

ws_share_link_count_open($answer['link']);

// A note: its read-only page, or with raw=1 its Markdown.
if ($answer['kind'] === 'note') {
    pg_send_nosniff();

    if ((string) ($_GET['raw'] ?? '') === '1') {
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: ' . pg_http_content_disposition($answer['name'] . '.md', true));
        echo ws_share_link_note_markdown($answer['note']);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo ws_share_link_note_page($answer);
    exit;
}

// A table or a checklist: its read-only page, or with raw=1 its CSV or
// Markdown.
if (($answer['kind'] === 'table') || ($answer['kind'] === 'list')) {
    pg_send_nosniff();

    if ((string) ($_GET['raw'] ?? '') === '1') {
        $table = ($answer['kind'] === 'table');
        header('Content-Type: ' . ($table ? 'text/csv' : 'text/markdown') . '; charset=utf-8');
        header('Content-Disposition: ' . pg_http_content_disposition($answer['name'] . ($table ? '.csv' : '.md'), true));
        echo $table ? "\xEF\xBB\xBF" . ws_share_link_table_raw($answer) : ws_share_link_list_raw($answer);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo ws_share_link_text_page($answer);
    exit;
}

header('Content-Type: ' . $answer['mime']);
header('Content-Disposition: ' . $answer['disposition']);
pg_send_nosniff();
header('Content-Length: ' . filesize($answer['path']));

readfile($answer['path']);
exit;
