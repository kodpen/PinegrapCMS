<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The server of the guarded frame's browser proofs
 * (tests/js/guarded_frame_proofs.js), a router for PHP's built-in server.
 * It answers the shell exactly as includes/fn/guarded_frame.php builds it,
 * around the pages below, and plays two more parts:
 *
 *   http://127.0.0.1:<port>/panel?page=<name>&mode=<mode>[&second=<name>]
 *       the panel: a cookie and storage of its own, the shell framed with
 *       the sandbox the module names (a second one for canvas 22 when
 *       asked), the reference listener of the messages: the shell's height,
 *       and the data bridge answered as the page card does, for the canvas
 *       of the shell that asked
 *   http://127.0.0.1:<port>/shell?page=<name>&mode=<mode>
 *       the shell, refused unless a frame asks for it
 *   http://127.0.0.1:<port>/action   a panel action: any request is a leak
 *   http://localhost:<port>/...      the outside: any request is a leak
 *
 * Every request but the panel's and the shell's is written to the log file
 * named by PG_GUARDED_FRAME_LOG.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_FUNCTIONS_DIR', dirname(__DIR__, 2) . '/pinegrap');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
$panel = 'http://127.0.0.1:' . $port;
$outside = 'http://localhost:' . $port;
$mode = pg_guarded_frame_mode($_GET['mode'] ?? null);
$page = (string) ($_GET['page'] ?? '');

$on_panel = (strpos($host, '127.0.0.1') === 0);

if ($on_panel && ($path === '/panel')) {
    header('Content-Type: text/html; charset=utf-8');
    header('Set-Cookie: pgsecret=panel-cookie; Path=/; SameSite=Lax');
    $src = '/shell?page=' . rawurlencode($page) . '&mode=' . $mode;
    $second = preg_replace('/[^a-z_]/', '', (string) ($_GET['second'] ?? ''));
    $src2 = '/shell?page=' . rawurlencode($second) . '&mode=' . $mode;
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Panel</title></head><body>'
        . '<p id="line">Made by an agent</p>'
        . '<iframe id="shell" style="width:640px;height:200px;border:0" sandbox="' . pg_guarded_frame_panel_sandbox($mode) . '" src="' . htmlspecialchars($src, ENT_QUOTES) . '"></iframe>'
        . (($second !== '') ? '<iframe id="shell2" style="width:640px;height:200px;border:0" sandbox="' . pg_guarded_frame_panel_sandbox($mode) . '" src="' . htmlspecialchars($src2, ENT_QUOTES) . '"></iframe>' : '')
        . '<script>'
        . 'localStorage.setItem("pgsecret","panel-storage");sessionStorage.setItem("pgsecret","panel-session");'
        . 'window.__heights=[];window.__ignored=[];window.__calls=[];window.__stopped=[];'
        . 'var shell=document.getElementById("shell"),shell2=document.getElementById("shell2");'
        // The reference card: a canvas id per shell it made, never taken
        // from the request; who answers the reader and that canvas.
        . 'function canvasOf(w){if(w===shell.contentWindow){return 11;}if(shell2&&w===shell2.contentWindow){return 22;}return 0;}'
        . 'window.addEventListener("message",function(e){var d=e.data,c=canvasOf(e.source);'
        . 'if(c&&d&&d.pg==="canvas-data"){if(d.stopped){window.__stopped.push(c);return;}'
        . 'window.__calls.push({canvas:c,call:d.call,args:JSON.stringify(d.args)});'
        . 'e.source.postMessage({pg:"canvas-data",id:d.id,ok:true,data:{name:"Reader",canvas:c}},"*");return;}'
        . 'if(e.source===shell.contentWindow&&d&&d.pg==="guarded-frame"&&typeof d.height==="number"){window.__heights.push(d.height);shell.style.height=Math.min(d.height,600)+"px";return;}'
        . 'window.__ignored.push(JSON.stringify(d));});'
        . 'shell.addEventListener("load",function(){shell.contentWindow.postMessage({pg:"guarded-frame",width:640,height:480},"*");});'
        . '</script></body></html>';
    exit;
}

if ($on_panel && ($path === '/shell')) {
    if (!pg_guarded_frame_request_framed($_SERVER)) {
        http_response_code(404);
        echo 'That page could not be found.';
        exit;
    }

    $file = __DIR__ . '/guarded_frame_pages/' . preg_replace('/[^a-z_]/', '', $page) . '.html';

    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }

    $html = strtr((string) file_get_contents($file), array('%PANEL%' => $panel, '%OUTSIDE%' => $outside, '%WS%' => 'ws://localhost:' . $port));
    pg_guarded_frame_send($html, $mode, 'Live page');
    exit;
}

$log = getenv('PG_GUARDED_FRAME_LOG');

if ($log) {
    file_put_contents($log, json_encode(array(
        'host'   => $host,
        'path'   => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'cookie' => isset($_COOKIE['pgsecret']),
    )) . "\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');
echo 'reached';
