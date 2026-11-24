<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address of a share link (includes/workspace/share_links.php): one item
 * of an agent channel, read by whoever holds the address, with no account
 * and no session. The token in the address is the credential, and it opens
 * this one item.
 *
 *   GET workspace_share.php?t=<token>
 *
 * A file is sent with its type: a picture, sound, video or PDF shown in the
 * page, anything else (and anything a browser could run) downloaded. Every
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

header('Content-Type: ' . $answer['mime']);
header('Content-Disposition: ' . $answer['disposition']);
pg_send_nosniff();
header('Content-Length: ' . filesize($answer['path']));

readfile($answer['path']);
exit;
