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
 * Three modes:
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
 *   mail    a mail's HTML (WM12): the static mode, whose links open in a
 *           new tab (popups that leave the sandbox) and whose pictures
 *           are the mail's own kept files; pictures from outside load
 *           only when the reader asked for them. Only the site's own code
 *           asks for it (pg_guarded_frame_kind()), never an address.
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
 * The bounds of the data bridge between a live page and the panel
 * (pg_guarded_frame_bridge_script()): requests a page may make in a minute
 * before the shell stops it, requests waiting for an answer at once, and
 * the size of a request's arguments and of an answer as JSON text.
 */
define('PG_GUARDED_FRAME_BRIDGE_PER_MINUTE', 120);
define('PG_GUARDED_FRAME_BRIDGE_PENDING', 8);
define('PG_GUARDED_FRAME_BRIDGE_MAX_ARGS', 16384);
define('PG_GUARDED_FRAME_BRIDGE_MAX_ANSWER', 1048576);

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
 * The kind of shell the site's own code asks for: 'mail', 'static', else
 * 'script'. Unlike pg_guarded_frame_mode(), which reads an address and
 * never gives 'mail', it trusts its caller. Pure.
 *
 * @param mixed $mode
 * @return string script, static or mail
 */
function pg_guarded_frame_kind($mode)
{
    return (($mode === 'static') || ($mode === 'mail')) ? $mode : 'script';
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
    if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}\z/', $raw)) {
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
    return is_string($name) && (bool) preg_match('/^[^\/\\\\]+\.html?\z/i', $name);
}

/**
 * The Content-Security-Policy of the shell. The document inside inherits
 * it (srcdoc), so it is the document's policy too. Pure.
 *
 * @param string $mode     script, static or mail
 * @param string $nonce    the static shell's own script and style
 * @param bool   $pictures a mail's pictures from outside may load
 * @return string
 */
function pg_guarded_frame_policy($mode, $nonce, $pictures = false)
{
    $mode = pg_guarded_frame_kind($mode);
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

    // A mail: the static policy, with popups (a link opens a new tab that
    // leaves the sandbox), the site's own pictures (the mail's kept files,
    // which the shell names) and, once asked for, pictures from outside.
    if ($mode === 'mail') {
        return implode('; ', array_merge(array(
            'sandbox allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox',
            "default-src 'none'",
            "script-src 'nonce-" . $nonce . "'",
            "style-src 'unsafe-inline'",
            "img-src 'self' data:" . ($pictures ? ' https: http:' : ''),
            'font-src data:',
            "media-src 'none'",
        ), $closed));
    }

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
 * @param bool   $pictures
 * @return string[]
 */
function pg_guarded_frame_headers($mode, $nonce, $pictures = false)
{
    return array(
        'Content-Type: text/html; charset=utf-8',
        'Content-Security-Policy: ' . pg_guarded_frame_policy($mode, $nonce, $pictures),
        'X-Content-Type-Options: nosniff',
        'X-Frame-Options: SAMEORIGIN',
        'Referrer-Policy: no-referrer',
        'Cache-Control: private, no-store',
        'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=(), display-capture=(), clipboard-read=(), clipboard-write=(), fullscreen=()',
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
    $mode = pg_guarded_frame_kind($mode);

    if ($mode === 'mail') {
        return 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox';
    }

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
    $mode = pg_guarded_frame_kind($mode);

    if ($mode === 'mail') {
        return 'allow-same-origin allow-popups allow-popups-to-escape-sandbox';
    }

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
 * mode, measured in the static and mail ones), as a whole number within
 * bounds. In the mail mode it also dresses the loaded document
 * (pg_guarded_frame_mail_script()). Pure.
 *
 * @param string $mode
 * @param array  $mail files (id => address of the mail's kept picture)
 *                     and pictures (bool), the mail mode only
 * @return string JavaScript
 */
function pg_guarded_frame_shell_script($mode, $mail = array())
{
    $mode = pg_guarded_frame_kind($mode);

    return '(function(){"use strict";var f=document.getElementById("pg-frame"),mode="' . $mode . '",'
        . 'max=' . PG_GUARDED_FRAME_MAX_HEIGHT . ',last=-1,up=window.parent;'
        . 'function n(v){if(typeof v!=="number"||!isFinite(v)||v<0){return -1;}return Math.min(Math.round(v),max);}'
        . 'function out(h){if(h<0||h===last||up===window){return;}last=h;up.postMessage({pg:"guarded-frame",height:h},"*");}'
        . (($mode === 'script') ? pg_guarded_frame_bridge_script() : '')
        . 'window.addEventListener("message",function(e){var d=e.data;'
        . (($mode === 'script') ? 'if(d&&typeof d==="object"&&d.pg==="canvas-data"){bridge(e,d);return;}' : '')
        . 'if(!d||typeof d!=="object"||d.pg!=="guarded-frame"){return;}'
        . 'if(e.source===up&&up!==window){var w=n(d.width),h=n(d.height);'
        . 'if(w<0||h<0||mode!=="script"||!f.contentWindow){return;}'
        . 'f.contentWindow.postMessage({pg:"guarded-frame",width:w,height:h},"*");return;}'
        . 'if(mode==="script"&&f.contentWindow&&e.source===f.contentWindow){out(n(d.height));}});'
        . (($mode === 'mail') ? pg_guarded_frame_mail_script($mail) : '')
        . 'if(mode!=="script"){var measure=function(){var doc=null;try{doc=f.contentDocument;}catch(x){}'
        . 'if(doc&&doc.documentElement){var e=doc.documentElement,v=f.contentWindow,bar=0;'
        // A mail wider than the frame scrolls sideways; its bar is counted.
        . 'if(mode==="mail"&&v){bar=Math.max(0,v.innerHeight-e.clientHeight);}'
        . 'out(n(Math.ceil(e.getBoundingClientRect().height)+bar));}return doc;};'
        . 'f.addEventListener("load",function(){var doc=null;try{doc=f.contentDocument;}catch(x){}'
        . (($mode === 'mail') ? 'if(doc){dress(doc);}' : '') . 'doc=measure();'
        . 'if(doc&&doc.documentElement&&window.ResizeObserver){new ResizeObserver(measure).observe(doc.documentElement);}});}'
        . '})();';
}

/**
 * The shell's part of the data bridge, the script mode only: the page asks
 * {pg: 'canvas-data', id, call, args}; the shell passes a well-formed
 * request to the panel (window.parent) and the panel's answer
 * {pg: 'canvas-data', id, ok, data | error {code, text}} back to the page,
 * only for a request still waiting. The shell names no canvas: the panel
 * adds the one of the card that made this shell, so a page cannot ask in
 * another canvas's name. A request is taken only from the shell's own
 * document frame, an answer only from the parent.
 *
 * Bounds (PG_GUARDED_FRAME_BRIDGE_*): a request with no usable id is
 * dropped; a bad call name or arguments answer invalid; arguments too
 * large, or too many waiting, answer limit; an answer too large reaches
 * the page as limit. More requests in a minute than allowed stop the page:
 * its frame is removed and the panel told {pg: 'canvas-data', stopped:
 * 'limit'}. Defines bridge(e, d) for the shell's listener. Pure.
 *
 * @return string JavaScript
 */
function pg_guarded_frame_bridge_script()
{
    return 'var bp=' . PG_GUARDED_FRAME_BRIDGE_PER_MINUTE . ',bw=' . PG_GUARDED_FRAME_BRIDGE_PENDING . ','
        . 'ba=' . PG_GUARDED_FRAME_BRIDGE_MAX_ARGS . ',bx=' . PG_GUARDED_FRAME_BRIDGE_MAX_ANSWER . ','
        . 'waiting={},times=[],stopped=false;'
        . 'function bsize(v){try{return JSON.stringify(v).length;}catch(x){return Infinity;}}'
        . 'function bkey(id){if(typeof id==="string"&&id.length>0&&id.length<=64){return "s"+id;}'
        . 'if(typeof id==="number"&&isFinite(id)){return "n"+id;}return "";}'
        . 'function bpage(m){if(!stopped&&f.contentWindow){f.contentWindow.postMessage(m,"*");}}'
        . 'function bfail(id,code){bpage({pg:"canvas-data",id:id,ok:false,error:{code:code,text:""}});}'
        . 'function btext(v,n){return (typeof v==="string")?v.slice(0,n):"";}'
        . 'function bridge(e,d){if(up===window||stopped){return;}var k=bkey(d.id);if(!k){return;}'
        // An answer from the panel, for a request still waiting.
        . 'if(e.source===up){if(!waiting[k]){return;}delete waiting[k];'
        . 'if(d.ok===true){var a={pg:"canvas-data",id:d.id,ok:true,data:(d.data===undefined?null:d.data)};'
        . 'if(bsize(a)>bx){bfail(d.id,"limit");return;}bpage(a);return;}'
        . 'var r=(d.error&&typeof d.error==="object")?d.error:{};'
        . 'bpage({pg:"canvas-data",id:d.id,ok:false,error:{code:btext(r.code,32)||"failed",text:btext(r.text,500)}});return;}'
        . 'if(!f.contentWindow||e.source!==f.contentWindow){return;}'
        // A request from the page.
        . 'var now=Date.now();times=times.filter(function(t){return now-t<60000;});times.push(now);'
        . 'if(times.length>bp){stopped=true;waiting={};f.remove();up.postMessage({pg:"canvas-data",stopped:"limit"},"*");return;}'
        . 'if(waiting[k]){bfail(d.id,"invalid");return;}'
        . 'if(typeof d.call!=="string"||!/^[a-z][a-z_]{0,23}$/.test(d.call)){bfail(d.id,"invalid");return;}'
        . 'var args=(d.args===undefined)?{}:d.args;'
        . 'if(!args||typeof args!=="object"||Array.isArray(args)){bfail(d.id,"invalid");return;}'
        . 'var n=bsize(args);if(n>ba){bfail(d.id,"limit");return;}'
        . 'if(Object.keys(waiting).length>=bw){bfail(d.id,"limit");return;}'
        . 'waiting[k]=1;up.postMessage({pg:"canvas-data",id:d.id,call:d.call,args:JSON.parse(JSON.stringify(args))},"*");}';
}

/**
 * The mail mode's part of the shell script: dress(doc), run once the inert
 * document has loaded. A link opens a new tab (an anchor of the mail stays
 * in it); a picture of the mail's own (data-pg-file) gets its kept file's
 * address; once pictures are asked for, an outside address set aside by
 * the cleaner (data-pg-src, data-pg-background, includes/fn/mail_html.php)
 * is put back, unless it is http(s) on the site's own host, which a mail
 * never reaches with the reader's cookies. Pure.
 *
 * @param array $mail files (id => address), pictures (bool)
 * @return string JavaScript
 */
function pg_guarded_frame_mail_script($mail)
{
    $files = array();

    foreach ((array) ($mail['files'] ?? array()) as $id => $url) {
        if (((int) $id > 0) && is_string($url) && ($url !== '')) {
            $files[(string) (int) $id] = $url;
        }
    }

    $config = json_encode(array('files' => (object) $files, 'pictures' => !empty($mail['pictures'])),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

    return 'var cfg=' . (($config !== false) ? $config : '{"files":{},"pictures":false}') . ';'
        . 'function each(doc,s,c){Array.prototype.forEach.call(doc.querySelectorAll(s),c);}'
        . 'function outside(v){var u=null;try{u=new URL(String(v||""));}catch(x){return "";}'
        . 'if((u.protocol!=="https:"&&u.protocol!=="http:")||u.hostname===location.hostname){return "";}return u.href;}'
        . 'function dress(doc){'
        . 'each(doc,"a[href]",function(a){if((a.getAttribute("href")||"").charAt(0)==="#"){a.setAttribute("target","_self");return;}'
        . 'a.setAttribute("target","_blank");a.setAttribute("rel","noopener noreferrer");});'
        . 'each(doc,"img[data-pg-file]",function(i){var k=i.getAttribute("data-pg-file");'
        . 'if(Object.prototype.hasOwnProperty.call(cfg.files,k)){i.setAttribute("src",cfg.files[k]);}});'
        . 'if(!cfg.pictures){return;}'
        . 'each(doc,"img[data-pg-src]",function(i){var u=outside(i.getAttribute("data-pg-src"));if(u){i.setAttribute("src",u);}});'
        . 'each(doc,"[data-pg-background]",function(e){var u=outside(e.getAttribute("data-pg-background"));if(u){e.setAttribute("background",u);}});}';
}

/**
 * The shell document: the stored document in its frame, escaped into
 * srcdoc so nothing it holds is markup of the shell. Pure.
 *
 * @param string $html  the stored document: a live page at most
 *                      PG_GUARDED_FRAME_MAX_BYTES, a mail's at most
 *                      pg_mail_html_max_bytes() and its wrapping
 * @param string $mode  script, static or mail
 * @param string $nonce
 * @param string $title the frame's accessible name
 * @param array  $mail  the mail mode's files and pictures
 * @return string
 */
function pg_guarded_frame_shell($html, $mode, $nonce, $title, $mail = array())
{
    $mode = pg_guarded_frame_kind($mode);
    $document = (string) $html;

    if ($mode === 'script') {
        $document .= pg_guarded_frame_reporter();
    }

    $escape = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $nonced = ($mode !== 'script') ? ' nonce="' . $escape($nonce) . '"' : '';

    return '<!DOCTYPE html>' . "\n"
        . '<html><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow">'
        . '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;background:transparent}'
        . '#pg-frame{display:block;border:0;width:100%;height:100%}</style></head><body>'
        . '<iframe id="pg-frame" title="' . $escape($title) . '" sandbox="' . pg_guarded_frame_inner_sandbox($mode) . '"'
        . ' referrerpolicy="no-referrer" srcdoc="' . $escape($document) . '"></iframe>'
        . '<script' . $nonced . '>' . pg_guarded_frame_shell_script($mode, $mail) . '</script>'
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
 * @param string $mode  script, static or mail
 * @param string $title
 * @param array  $mail  the mail mode's files and pictures
 */
function pg_guarded_frame_send($html, $mode, $title, $mail = array())
{
    $mode = pg_guarded_frame_kind($mode);
    $nonce = pg_guarded_frame_nonce();

    if (function_exists('header_remove')) {
        header_remove('Content-Security-Policy-Report-Only');
    }

    foreach (pg_guarded_frame_headers($mode, $nonce, ($mode === 'mail') && !empty($mail['pictures'])) as $header) {
        header($header, true);
    }

    echo pg_guarded_frame_shell($html, $mode, $nonce, $title, $mail);
}
