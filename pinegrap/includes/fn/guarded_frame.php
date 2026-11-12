<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guarded frame: how a document the panel did not write (an agent's
 * live page, a mail's HTML) is shown inside the panel without reaching it.
 *
 * The panel frames one answer of the site, the shell. The shell holds the
 * document in a frame of its own (srcdoc, so no second request is needed
 * and no cookie decides it) and passes two messages:
 *
 *   panel -> shell   {pg: 'guarded-frame', width, height}  the size the
 *                    panel gives; in the script mode handed to the page
 *   shell -> panel   {pg: 'guarded-frame', height}         the document's
 *                    height; nothing else ever leaves the shell
 *
 * Two modes:
 *   script  a live page. The shell runs in an opaque origin (the policy's
 *           sandbox and the panel's frame without allow-same-origin), the
 *           page in another one inside it, and the page's script runs
 *           under the shell's policy, which srcdoc inherits: no request
 *           leaves it, no form, popup, download or move of a window.
 *           The site appends a few lines that report the page's height.
 *   static  a mail's HTML. No script of the document runs at all (its
 *           frame has no allow-scripts, and the policy allows only the
 *           shell's own script by nonce); the shell keeps the site's
 *           origin so it can measure the inert document beside it.
 *
 * The pure parts are tested in tests/guarded_frame_test.php; what a browser
 * enforces is proven in tests/js/guarded_frame_proofs.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The largest document the frame shows, in bytes (500 KB).
 */
define('PG_GUARDED_FRAME_MAX_BYTES', 512000);

/**
 * The tallest height the shell reports, in pixels: a page that claims more
 * is cut there; the panel bounds it further in the card.
 */
define('PG_GUARDED_FRAME_MAX_HEIGHT', 100000);

/**
 * The mode asked for: 'static' only when asked by that exact word, else
 * 'script'. Pure.
 *
 * @param mixed $raw
 * @return string script or static
 */
function pg_guarded_frame_mode($raw)
{
    return ($raw === 'static') ? 'static' : 'script';
}

/**
 * The id of a stored page from the address: digits only, no sign, no
 * leading zero, no space, within a database id; anything else is 0. Pure.
 *
 * @param mixed $raw
 * @return int 0 when refused
 */
function pg_guarded_frame_page_id($raw)
{
    if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}$/', $raw)) {
        return 0;
    }

    $id = (int) $raw;

    return ($id <= 2147483647) ? $id : 0;
}

/**
 * Is this stored file name a page the frame shows: .html or .htm? Pure.
 *
 * @param mixed $name
 * @return bool
 */
function pg_guarded_frame_is_page_name($name)
{
    return is_string($name) && (bool) preg_match('/^[^\/\\\\]+\.html?$/i', $name);
}

/**
 * The Content-Security-Policy of the shell. The document inside inherits
 * it (srcdoc), so it is the document's policy too. Pure.
 *
 * @param string $mode  script or static
 * @param string $nonce the static shell's own script and style
 * @return string
 */
function pg_guarded_frame_policy($mode, $nonce)
{
    $closed = array(
        "connect-src 'none'",
        "frame-src 'none'",
        "child-src 'none'",
        "worker-src 'none'",
        "object-src 'none'",
        "manifest-src 'none'",
        "form-action 'none'",
        "base-uri 'none'",
        "frame-ancestors 'self'",
    );

    if ($mode === 'static') {
        return implode('; ', array_merge(array(
            'sandbox allow-scripts allow-same-origin',
            "default-src 'none'",
            "script-src 'nonce-" . $nonce . "'",
            "style-src 'unsafe-inline'",
            'img-src data:',
            'font-src data:',
            "media-src 'none'",
        ), $closed));
    }

    // The page's own inline script runs, so the shell's has no nonce: with
    // one, 'unsafe-inline' would be ignored.
    return implode('; ', array_merge(array(
        'sandbox allow-scripts',
        "default-src 'none'",
        "script-src 'unsafe-inline'",
        "style-src 'unsafe-inline'",
        'img-src data: blob:',
        'font-src data:',
        'media-src data: blob:',
    ), $closed));
}

/**
 * The headers of the shell's answer. Pure.
 *
 * @param string $mode
 * @param string $nonce
 * @return string[]
 */
function pg_guarded_frame_headers($mode, $nonce)
{
    return array(
        'Content-Type: text/html; charset=utf-8',
        'Content-Security-Policy: ' . pg_guarded_frame_policy($mode, $nonce),
        'X-Content-Type-Options: nosniff',
        'X-Frame-Options: SAMEORIGIN',
        'Referrer-Policy: no-referrer',
        'Cache-Control: private, no-store',
        'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=(), bluetooth=(), display-capture=(), clipboard-read=(), clipboard-write=(), fullscreen=()',
        'X-Robots-Tag: noindex, nofollow',
    );
}

/**
 * The sandbox attribute the panel gives the frame it puts the shell in.
 * Pure.
 *
 * @param string $mode
 * @return string
 */
function pg_guarded_frame_panel_sandbox($mode)
{
    return ($mode === 'static') ? 'allow-scripts allow-same-origin' : 'allow-scripts';
}

/**
 * The sandbox attribute of the document's own frame inside the shell. Pure.
 *
 * @param string $mode
 * @return string
 */
function pg_guarded_frame_inner_sandbox($mode)
{
    return ($mode === 'static') ? 'allow-same-origin' : 'allow-scripts';
}

/**
 * The lines the site appends to a live page: its height, posted to the
 * shell whenever it changes. A page may silence or fake them; the shell
 * takes a number and nothing else. Pure.
 *
 * @return string
 */
function pg_guarded_frame_reporter()
{
    return '<script>(function(){var l=-1;function s(){var e=document.documentElement;'
        . 'var h=e?Math.ceil(e.getBoundingClientRect().height):0;if(h!==l){l=h;'
        . 'window.parent.postMessage({pg:"guarded-frame",height:h},"*");}}'
        . 'if(window.ResizeObserver&&document.documentElement){new ResizeObserver(s).observe(document.documentElement);}'
        . 'window.addEventListener("load",s);s();})();</script>';
}

/**
 * The shell's own script: takes the panel's size and hands it to a live
 * page; reports the document's height (the page's own report in the script
 * mode, measured in the static one), as a whole number within bounds. Pure.
 *
 * @param string $mode
 * @return string JavaScript
 */
function pg_guarded_frame_shell_script($mode)
{
    return '(function(){"use strict";var f=document.getElementById("pg-frame"),mode="' . (($mode === 'static') ? 'static' : 'script') . '",'
        . 'max=' . PG_GUARDED_FRAME_MAX_HEIGHT . ',last=-1,up=window.parent;'
        . 'function n(v){if(typeof v!=="number"||!isFinite(v)||v<0){return -1;}return Math.min(Math.round(v),max);}'
        . 'function out(h){if(h<0||h===last||up===window){return;}last=h;up.postMessage({pg:"guarded-frame",height:h},"*");}'
        . 'window.addEventListener("message",function(e){var d=e.data;'
        . 'if(!d||typeof d!=="object"||d.pg!=="guarded-frame"){return;}'
        . 'if(e.source===up&&up!==window){var w=n(d.width),h=n(d.height);'
        . 'if(w<0||h<0||mode!=="script"||!f.contentWindow){return;}'
        . 'f.contentWindow.postMessage({pg:"guarded-frame",width:w,height:h},"*");return;}'
        . 'if(mode==="script"&&f.contentWindow&&e.source===f.contentWindow){out(n(d.height));}});'
        . 'if(mode==="static"){var measure=function(){var doc=null;try{doc=f.contentDocument;}catch(x){}'
        . 'if(doc&&doc.documentElement){out(n(Math.ceil(doc.documentElement.getBoundingClientRect().height)));}return doc;};'
        . 'f.addEventListener("load",function(){var doc=measure();'
        . 'if(doc&&doc.documentElement&&window.ResizeObserver){new ResizeObserver(measure).observe(doc.documentElement);}});}'
        . '})();';
}

/**
 * The shell document: the stored document in its frame, escaped into
 * srcdoc so nothing it holds is markup of the shell. Pure.
 *
 * @param string $html  the stored document, at most PG_GUARDED_FRAME_MAX_BYTES
 * @param string $mode  script or static
 * @param string $nonce
 * @param string $title the frame's accessible name
 * @return string
 */
function pg_guarded_frame_shell($html, $mode, $nonce, $title)
{
    $mode = pg_guarded_frame_mode($mode);
    $document = (string) $html;

    if ($mode === 'script') {
        $document .= pg_guarded_frame_reporter();
    }

    $escape = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $nonced = ($mode === 'static') ? ' nonce="' . $escape($nonce) . '"' : '';

    return '<!DOCTYPE html>' . "\n"
        . '<html><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow">'
        . '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;background:transparent}'
        . '#pg-frame{display:block;border:0;width:100%;height:100%}</style></head><body>'
        . '<iframe id="pg-frame" title="' . $escape($title) . '" sandbox="' . pg_guarded_frame_inner_sandbox($mode) . '"'
        . ' referrerpolicy="no-referrer" srcdoc="' . $escape($document) . '"></iframe>'
        . '<script' . $nonced . '>' . pg_guarded_frame_shell_script($mode) . '</script>'
        . '</body></html>';
}

/**
 * A fresh nonce for one answer.
 *
 * @return string
 */
function pg_guarded_frame_nonce()
{
    return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}

/**
 * Was this request made by a frame? A browser that names the destination
 * and names another one is refused: the shell is shown only inside the
 * panel, never as a page of its own. Pure.
 *
 * @param array $server $_SERVER
 * @return bool
 */
function pg_guarded_frame_request_framed($server)
{
    $dest = isset($server['HTTP_SEC_FETCH_DEST']) ? strtolower((string) $server['HTTP_SEC_FETCH_DEST']) : '';

    return ($dest === 'iframe') || ($dest === 'frame');
}

/**
 * Sends the shell with its headers, replacing any policy sent before (the
 * firewall's own, which would loosen or break it).
 *
 * @param string $html
 * @param string $mode
 * @param string $title
 */
function pg_guarded_frame_send($html, $mode, $title)
{
    $mode = pg_guarded_frame_mode($mode);
    $nonce = pg_guarded_frame_nonce();

    if (function_exists('header_remove')) {
        header_remove('Content-Security-Policy-Report-Only');
    }

    foreach (pg_guarded_frame_headers($mode, $nonce) as $header) {
        header($header, true);
    }

    echo pg_guarded_frame_shell($html, $mode, $nonce, $title);
}
