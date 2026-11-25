<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Preview on a phone" on the phone: the page a phone link opens
 * (prototype.php?link=<token> for a link pg_proto_link_is_phone() marks).
 *
 * The page is drawn at the phone's own width under one thin line, the
 * viewer's whole chrome there: the page's name, when it was last redrawn,
 * and the page list. The line asks the changed-since address
 * (pg_proto_phone_watch_query(), includes/fn/prototype_phone.php) whether
 * the draft was saved since it was drawn, only while the tab is seen and as
 * often as the answer says (every 10 seconds, every 30 after ten minutes
 * without a save, not at all an hour after the drawing); a save draws the
 * page again in place, keeping the place scrolled to. The asking counts no
 * use of the link and never carries its token.
 *
 * Loaded by prototype.php for a phone link's viewer, and by the tests.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_PROTOTYPE_ENTRY')) {
    exit;
}

/**
 * The page list's rows: the design's drafts in tab order, each with its
 * label, the viewer's address of it, whether it is the one shown and whether
 * the link cannot show it (a page for signed-in visitors on a link for
 * anyone). Pure.
 *
 * @param array[] $pages   pg_proto_design_pages()
 * @param int     $current the page shown
 * @param string  $viewer  the viewer's base address (the link's)
 * @param int[]   $locked  the ids of the pages the link does not show
 * @return array[] id, label, url, current, locked
 */
function pg_proto_phone_view_rows($pages, $current, $viewer, $locked)
{
    $closed = array();

    foreach ((array) $locked as $id) {
        $closed[(int) $id] = true;
    }

    $rows = array();

    foreach ((array) $pages as $page) {
        $id = (int) $page['id'];
        $rows[] = array(
            'id' => $id,
            'label' => ((string) $page['title'] !== '') ? (string) $page['title'] : (string) $page['name'],
            'url' => pg_proto_viewer_url($viewer, $id),
            'current' => ($id === (int) $current),
            'locked' => isset($closed[$id]),
        );
    }

    return $rows;
}

/**
 * What the body of the phone's page holds: 'frame' the drawn page; 'locked'
 * a page the link does not show; 'gone' a page asked for that is no longer
 * a draft of the design; 'error' the page cannot be drawn. Only 'frame' is
 * watched. Pure.
 *
 * @param string $error    why nothing can be drawn ('' for nothing)
 * @param string $frame_url
 * @param bool   $fallback the page asked for is not among the drafts
 * @param bool   $shown    the link shows the page
 * @return string
 */
function pg_proto_phone_view_body($error, $frame_url, $fallback, $shown)
{
    if ((string) $error !== '' || (string) $frame_url === '') {
        return 'error';
    }

    if ($fallback) {
        return 'gone';
    }

    return $shown ? 'frame' : 'locked';
}

/**
 * The phone's page: the thin line, the page list, the body, and the script
 * that asks whether the draft changed. The token is only in the viewer's own
 * addresses (the page list, the redraw), as on every share link's viewer;
 * the asking address names the link by its id.
 *
 * @param array $view lang, software, design, rows (pg_proto_phone_view_rows()),
 *                    current, body (pg_proto_phone_view_body()), frame_url,
 *                    error, watch_url ('' for none), since (the drawn
 *                    page's last save), base (the viewer's address of the
 *                    link), here (its address of the page shown), link_id
 * @return string
 */
function pg_proto_phone_shell_html($view)
{
    $rows = isset($view['rows']) ? (array) $view['rows'] : array();
    $body = isset($view['body']) ? (string) $view['body'] : 'error';
    $software = isset($view['software']) ? (string) $view['software'] : '';
    $design = isset($view['design']) ? (string) $view['design'] : '';
    $watch = ($body === 'frame') && isset($view['watch_url']) ? (string) $view['watch_url'] : '';
    $name = $design;

    foreach ($rows as $row) {
        if (!empty($row['current'])) {
            $name = (string) $row['label'];
        }
    }

    // A page asked for that is gone keeps no name of its own here; the
    // script puts back the one the line had before the redraw.
    if ($body === 'gone') {
        $name = $design;
    }

    $list = '';

    foreach ($rows as $row) {
        $mark = !empty($row['locked']) ? '<span class="bi bi-lock" aria-hidden="true"></span>' : (!empty($row['current']) ? '<span class="bi bi-check-lg" aria-hidden="true"></span>' : '');
        $list .= '<a class="pp-row' . (!empty($row['current']) ? ' pp-on' : '') . (!empty($row['locked']) ? ' pp-off' : '') . '" href="' . h((string) $row['url']) . '"'
            . (!empty($row['current']) ? ' aria-current="page"' : '')
            . (!empty($row['locked']) ? ' title="' . h(lang('This page is for signed-in visitors')) . '"' : '')
            . '><span class="pp-label">' . h((string) $row['label']) . '</span>' . $mark . '</a>';
    }

    if ($body === 'frame') {
        $main = '<iframe id="pg-proto-frame" class="pp-frame" src="' . h((string) $view['frame_url']) . '"'
            . ' title="' . h(lang('Prototype')) . '"'
            . ' sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"'
            . ' referrerpolicy="no-referrer"></iframe>';
    } elseif ($body === 'locked') {
        $main = '<div class="pp-msg"><span class="bi bi-lock" aria-hidden="true"></span><b>' . h(lang('This page is for signed-in visitors')) . '</b><span>' . h(lang('Ask for a team link to see it on your phone.')) . '</span></div>';
    } elseif ($body === 'gone') {
        $main = '<div class="pp-msg"><b>' . h(lang('This page is no longer in the design.')) . '</b></div>';
    } else {
        $main = '<div class="pp-msg"><b>' . h(((string) (isset($view['error']) ? $view['error'] : '') !== '') ? (string) $view['error'] : lang('The page could not be drawn.')) . '</b></div>';
    }

    $config = array(
        'watch' => $watch,
        'since' => isset($view['since']) ? (int) $view['since'] : 0,
        'here' => isset($view['here']) ? (string) $view['here'] : '',
        'base' => isset($view['base']) ? (string) $view['base'] : '',
        'key' => 'pg-proto-phone-' . (isset($view['link_id']) ? (int) $view['link_id'] : 0),
        'body' => $body,
        'now' => lang('Updated just now'),
        'ago' => lang('Updated {n} min ago'),
        'paused' => lang('Paused'),
        'offline' => lang('No connection. Trying again…'),
    );

    return '<!DOCTYPE html>
<html lang="' . h(isset($view['lang']) ? (string) $view['lang'] : 'en') . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h($name !== '' ? $name : lang('Prototype')) . '</title>
<link rel="stylesheet" href="' . h($software) . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . (string) @filemtime(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<style>
/* The line is dark on every site, whatever the site\'s theme, so it never
   reads as part of the page; it stays on top while the page scrolls in the
   frame under it. */
html, body { height: 100%; }
body.pp { display: flex; flex-direction: column; margin: 0; overflow: hidden; background: #fff; font: 14px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; }
.pp-line { position: relative; z-index: 3; flex: none; display: flex; align-items: center; gap: 8px; height: 32px; padding: 0 0 0 12px; background: #14171c; color: #e6edf3; font-size: 12px; box-shadow: 0 1px 0 rgba(0,0,0,.35); transition: box-shadow .2s; }
.pp-line.pp-new { box-shadow: inset 0 -2px 0 #58a6ff; }
.pp-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 0 1 auto; }
.pp-words { color: #8b949e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1 1 auto; }
.pp-line.pp-warn .pp-words { color: #f5b54a; }
.pp-update { flex: none; display: inline-flex; align-items: center; height: 22px; padding: 0 9px; border: 0; border-radius: 6px; font: inherit; font-size: 11.5px; font-weight: 600; color: #fff; background: #2563eb; cursor: pointer; }
.pp-update[hidden] { display: none; }
/* The list button\'s touch area is 44 px wide and reaches 12 px below the line. */
.pp-list { flex: none; align-self: flex-start; width: 44px; height: 44px; margin: 0 0 -12px; padding: 0 0 12px; border: 0; background: transparent; color: #c9d1d9; font-size: 15px; cursor: pointer; }
.pp-list span { display: grid; place-items: center; height: 32px; }
.pp-line.pp-open .pp-list span { background: #262c34; color: #fff; }
.pp-sheet { position: absolute; z-index: 4; left: 0; right: 0; top: 32px; max-height: calc(100% - 48px); overflow-y: auto; padding: 6px 8px 8px; background: #14171c; color: #e6edf3; font-size: 14px; box-shadow: 0 14px 30px rgba(0,0,0,.4); border-radius: 0 0 12px 12px; }
.pp-sheet[hidden], .pp-dim[hidden] { display: none; }
.pp-dim { position: absolute; z-index: 2; left: 0; right: 0; top: 32px; bottom: 0; background: rgba(0,0,0,.45); }
.pp-row { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 10px; border-radius: 8px; color: inherit; text-decoration: none; }
.pp-row .pp-label { flex: 1 1 auto; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-row.pp-on { background: rgba(88,166,255,.14); }
.pp-row.pp-on .bi { color: #58a6ff; }
.pp-row.pp-off { color: #6e7681; }
.pp-row.pp-off .bi { font-size: 13px; }
.pp-frame { flex: 1 1 auto; width: 100%; border: 0; background: #fff; }
.pp-msg { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 0 34px; text-align: center; background: #f6f7f9; color: #4b5563; line-height: 1.5; }
.pp-msg .bi { font-size: 26px; color: #9aa3ae; }
.pp-msg b { font-size: 16px; font-weight: 600; color: #1f2937; }
@media (prefers-reduced-motion: reduce) { .pp-line { transition: none; } }
</style>
</head>
<body class="pp">
<div class="pp-line" id="pp-line">
<span class="pp-name" id="pp-name">' . h($name) . '</span>
<span class="pp-words" id="pp-words" role="status">' . (($watch !== '') ? h(lang('Updated just now')) : '') . '</span>
<button type="button" class="pp-update" id="pp-update" hidden>' . h(lang('Update')) . '</button>
<button type="button" class="pp-list" id="pp-list" aria-expanded="' . (($body === 'gone') ? 'true' : 'false') . '" aria-controls="pp-sheet" aria-label="' . h(lang('Pages')) . '"><span class="bi bi-list-ul" aria-hidden="true"></span></button>
</div>
<div class="pp-dim" id="pp-dim"' . (($body === 'gone') ? '' : ' hidden') . '></div>
<nav class="pp-sheet" id="pp-sheet" aria-label="' . h(lang('Pages')) . '"' . (($body === 'gone') ? '' : ' hidden') . '>' . $list . '</nav>
' . $main . '
<script type="application/json" id="pp-config">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>
<script>' . pg_proto_phone_shell_script() . '</script>
</body>
</html>';
}

/**
 * The phone page's script: the page list, the asking and the redraw.
 *
 * The asking runs only while the tab is seen; it waits as long as the
 * answer's `next` says and stops at its `stop` ("Paused", with "Update").
 * Coming back to the tab asks at once. A failed request says "No
 * connection" and tries again after the same wait. A save draws the page
 * again by opening the viewer's address of it in place (the history entry is
 * replaced, and an address naming a page counts no use of the link), which
 * signs a new frame and a new watch; the place scrolled to is asked of the
 * frame before and handed back to it after. A 404 (the link ended, the page
 * gone, the session changed) draws the page again once, and the viewer then
 * says what is left; a second 404 straight after that stops the asking.
 *
 * @return string
 */
function pg_proto_phone_shell_script()
{
    return '(function () {
    var cfg = {};
    try { cfg = JSON.parse(document.getElementById("pp-config").textContent) || {}; } catch (e) {}
    var line = document.getElementById("pp-line");
    var words = document.getElementById("pp-words");
    var update = document.getElementById("pp-update");
    var list = document.getElementById("pp-list");
    var sheet = document.getElementById("pp-sheet");
    var dim = document.getElementById("pp-dim");
    var nameEl = document.getElementById("pp-name");
    var frame = document.getElementById("pg-proto-frame");
    var drawn = Date.now(), timer = 0, wait = 10, stopped = !cfg.watch, busy = false, asked = 0;
    var kept = null;
    function store(v) { try { if (v) window.sessionStorage.setItem(cfg.key, JSON.stringify(v)); else window.sessionStorage.removeItem(cfg.key); } catch (e) {} }
    try { kept = JSON.parse(window.sessionStorage.getItem(cfg.key) || "null"); } catch (e) {}
    store(null);
    // What the page before this redraw left: the place scrolled to, whether
    // a save drew it, and whether a 404 did.
    kept = (kept && typeof kept === "object" && Date.now() - (+kept.at || 0) < 60000) ? kept : {};
    var place = (+kept.y > 0) ? +kept.y : 0, afterGone = !!kept.gone;
    // A reload of the browser opens the page shown, not the link\'s first
    // address, so it counts no use of the link.
    try { if (cfg.here) window.history.replaceState(null, "", cfg.here); } catch (e) {}
    if (cfg.body === "gone" && typeof kept.name === "string" && kept.name) nameEl.textContent = kept.name;
    function say(text, warn) {
        words.textContent = text;
        line.classList.toggle("pp-warn", !!warn);
    }
    function ago() {
        if (stopped || !cfg.watch) return;
        var min = Math.floor((Date.now() - drawn) / 60000);
        say(min < 1 ? cfg.now : String(cfg.ago).replace("{n}", min), false);
    }
    function pause() {
        stopped = true;
        clearTimeout(timer);
        say(cfg.paused, true);
        update.hidden = false;
    }
    function where(done) {
        // The frame has an opaque origin: the place scrolled to is asked of
        // it, and the answer waited for a moment only.
        var finished = false;
        function end(y) { if (finished) return; finished = true; window.removeEventListener("message", on); done(y); }
        function on(e) { if (frame && e.source === frame.contentWindow && e.data && e.data.pgPrototype === "where") end(+e.data.y || 0); }
        if (!frame || !frame.contentWindow) { done(0); return; }
        window.addEventListener("message", on);
        try { frame.contentWindow.postMessage({ pgPrototype: "where" }, "*"); } catch (e) { end(0); }
        setTimeout(function () { end(0); }, 300);
    }
    function redraw(flash, gone) {
        if (busy || !cfg.here) return;
        busy = true;
        clearTimeout(timer);
        where(function (y) {
            store({ y: y, flash: !!flash, gone: !!gone, name: nameEl.textContent, at: Date.now() });
            window.location.replace(cfg.here);
        });
    }
    function schedule() {
        clearTimeout(timer);
        if (stopped || document.visibilityState === "hidden") return;
        timer = setTimeout(ask, Math.max(10, wait) * 1000);
    }
    function ask() {
        clearTimeout(timer);
        if (stopped || busy || document.visibilityState === "hidden") return;
        asked++;
        var url = cfg.watch + "&since=" + (cfg.since > 0 ? cfg.since : "");
        fetch(url, { credentials: "same-origin", cache: "no-store", headers: { "Accept": "application/json" } }).then(function (r) {
            if (r.status === 404) return { gone: true };
            if (!r.ok) throw new Error("status");
            return r.json();
        }).then(function (a) {
            if (a && a.gone) {
                // Drawn again once: the viewer says what is left of the link
                // or the page. A 404 straight after such a redraw stops.
                if (afterGone && asked === 1) pause(); else redraw(false, true);
                return;
            }
            if (!a || a.ok !== true) throw new Error("answer");
            if (a.changed) { redraw(true, false); return; }
            if (a.stop) { pause(); return; }
            wait = +a.next || 10;
            ago();
            schedule();
        }).catch(function () {
            say(cfg.offline, true);
            schedule();
        });
    }
    function start() {
        if (busy) return;
        // Asking again after the pause takes a new watch: the page is drawn
        // again with it.
        redraw(false, false);
    }
    update.addEventListener("click", start);
    var pullY = -1;
    line.addEventListener("touchstart", function (e) { pullY = (stopped && e.touches.length === 1) ? e.touches[0].clientY : -1; }, { passive: true });
    line.addEventListener("touchend", function (e) {
        if (pullY >= 0 && e.changedTouches.length && e.changedTouches[0].clientY - pullY > 60) start();
        pullY = -1;
    }, { passive: true });
    function open(on) {
        sheet.hidden = !on;
        dim.hidden = !on;
        line.classList.toggle("pp-open", on);
        list.setAttribute("aria-expanded", on ? "true" : "false");
    }
    list.addEventListener("click", function () { open(sheet.hidden); });
    dim.addEventListener("click", function () { open(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !sheet.hidden) open(false); });
    if (cfg.body === "gone") line.classList.add("pp-open");
    // A click on a link to another page of the prototype, handed over by the
    // frame: the page number and the item of the view only.
    function go(d) {
        var page = String(d.page || "");
        if (!/^[0-9]+$/.test(page) || !cfg.base) return;
        var url = cfg.base + (cfg.base.indexOf("?") === -1 ? "?" : "&") + "page=" + page;
        ["sub", "r", "anchor"].forEach(function (k) {
            if (typeof d[k] === "string" && d[k] !== "") url += "&" + k + "=" + encodeURIComponent(d[k]);
        });
        window.location.href = url;
    }
    function back() {
        if (!place || !frame || !frame.contentWindow) return;
        try { frame.contentWindow.postMessage({ pgPrototype: "scroll", y: place }, "*"); } catch (e) {}
    }
    window.addEventListener("message", function (e) {
        if (!frame || e.source !== frame.contentWindow || !e.data) return;
        if (e.data.pgPrototype === "ready") back();
        else if (e.data.pgPrototype === "go") go(e.data);
    });
    // Handed back when the frame is ready and again once its pictures are
    // in, which can make the page longer; not after that.
    if (frame) frame.addEventListener("load", function () { back(); place = 0; });
    if (kept.flash && cfg.watch) {
        line.classList.add("pp-new");
        setTimeout(function () { line.classList.remove("pp-new"); }, 2000);
    }
    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "hidden") { clearTimeout(timer); return; }
        // Coming back to the tab asks at once.
        if (!stopped) ask();
    });
    if (cfg.watch) schedule();
})();';
}
