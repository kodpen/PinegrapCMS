/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the card of a live page an agent made (file.page of a
 * message, includes/workspace/page_card.php): its name, the agent that made
 * it, Open (the guarded frame in the card), Full screen, View source,
 * Download, the versions, and "Edited by @handle" with Undo after an
 * agent's edit.
 *
 * The page runs only once a person opens it: the frame (live_page.php,
 * sandboxed by file.page.sandbox) is made on Open and removed on Close and
 * when the channel is left (stopAll()). The panel gives the frame its size
 * and takes nothing from it but its height and the data bridge's requests,
 * and only from the frame's own window (includes/fn/guarded_frame.php); a
 * request is answered by the site for this card's canvas (ws_page_call,
 * includes/workspace/page_bridge.php). The line naming the agent sits
 * outside the frame, so nothing in the page can cover it, in full screen
 * too.
 *
 * assets/js/workspace.js calls card() from filePreview() with its own
 * helpers.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The frame's height in the card: what the page reports, within these.
    var MIN_HEIGHT = 120;
    var MAX_HEIGHT = 640;

    // The open frames: { frame, box } by message id. A message drawn again
    // (an edit, a reaction) opens its page again with the newest version.
    var open = {};

    function frameOf(source) {
        var found = null;

        Object.keys(open).forEach(function (id) {
            if (open[id].frame.contentWindow === source) {
                found = open[id];
            }
        });

        return found;
    }

    function isFull(entry) {
        return document.fullscreenElement === entry.box;
    }

    // The panel tells the shell its size; nothing else goes in.
    function sendSize(entry) {
        var win = entry.frame.contentWindow;

        if (win) {
            win.postMessage({ pg: 'guarded-frame', width: entry.frame.clientWidth, height: entry.frame.clientHeight }, '*');
        }
    }

    // The data bridge (includes/workspace/page_bridge.php): the shell passes
    // a page's request; the site is asked for the canvas of the card that
    // made this frame, whatever the request says, and the answer goes back
    // to the same shell. A shell that stopped its page is closed here.
    function bridge(entry, data) {
        if (data.stopped) {
            close(entry.message.id);

            if (entry.onStop) {
                entry.onStop();
            }

            return;
        }

        var id = data.id;
        var answer = function (reply) {
            // The frame may have been closed or opened again meanwhile.
            if ((open[entry.message.id] === entry) && entry.frame.contentWindow) {
                reply.pg = 'canvas-data';
                reply.id = id;
                entry.frame.contentWindow.postMessage(reply, '*');
            }
        };

        entry.h.api('ws_page_call', {
            message_id: entry.message.id,
            call: String(data.call || ''),
            args: JSON.stringify((data.args && (typeof data.args === 'object')) ? data.args : {})
        }).then(function (result) {
            var reply = (result && result.answer) || {};

            answer(reply.ok ? { ok: true, data: reply.data } : { ok: false, error: reply.error || { code: 'failed', text: '' } });
        }).catch(function (error) {
            answer({ ok: false, error: { code: 'failed', text: String((error && error.message) || error || '') } });
        });
    }

    // What the shell posts, taken only from a frame this card made: its
    // height and the bridge's requests. The page itself may post to the
    // panel too, and that is dropped.
    window.addEventListener('message', function (event) {
        var data = event.data;
        var entry = frameOf(event.source);

        if (!entry || !data || (typeof data !== 'object')) {
            return;
        }

        if (data.pg === 'canvas-data') {
            bridge(entry, data);
            return;
        }

        if (data.pg !== 'guarded-frame') {
            return;
        }

        var height = (typeof data.height === 'number') && isFinite(data.height) ? Math.round(data.height) : -1;

        if (!document.contains(entry.frame)) {
            return;
        }

        if ((height >= 0) && !isFull(entry)) {
            entry.frame.style.height = Math.max(MIN_HEIGHT, Math.min(MAX_HEIGHT, height)) + 'px';
            sendSize(entry);
        }
    });

    window.addEventListener('resize', function () {
        Object.keys(open).forEach(function (id) {
            sendSize(open[id]);
        });
    });

    document.addEventListener('fullscreenchange', function () {
        Object.keys(open).forEach(function (id) {
            var entry = open[id];
            var full = isFull(entry);

            entry.box.classList.toggle('bg-body', full);
            entry.box.classList.toggle('d-flex', full);
            entry.box.classList.toggle('flex-column', full);
            entry.frame.style.flex = full ? '1 1 auto' : '';
            entry.frame.style.height = full ? 'auto' : MIN_HEIGHT + 'px';
            sendSize(entry);
        });
    });

    function close(id) {
        var entry = open[id];

        if (!entry) {
            return;
        }

        if (isFull(entry) && document.exitFullscreen) {
            document.exitFullscreen().catch(function () { /* already left */ });
        }

        // Taking the frame out of the document stops the page's script.
        entry.frame.remove();
        entry.box.hidden = true;
        delete open[id];
    }

    // Every open page stops: the channel is left.
    function stopAll() {
        Object.keys(open).forEach(close);
    }

    // onStop: the shell stopped the page at a bound.
    function startFrame(message, box, h, onStop) {
        var page = message.file.page;
        var frame = h.el('iframe', 'd-block w-100 border-0');

        frame.setAttribute('sandbox', page.sandbox);
        frame.setAttribute('referrerpolicy', 'no-referrer');
        frame.title = message.file.name;
        frame.style.height = MIN_HEIGHT + 'px';
        frame.src = page.frame;

        var entry = { frame: frame, box: box, message: message, h: h, onStop: onStop };

        frame.addEventListener('load', function () { sendSize(entry); });
        open[message.id] = entry;
        box.appendChild(frame);
        box.hidden = false;

        return entry;
    }

    function formatTime(seconds) {
        var date = new Date((parseInt(seconds, 10) || 0) * 1000);

        return date.toLocaleString();
    }

    // View source: one version's HTML as the agent wrote it, the versions
    // to choose from, Copy.
    function showSource(message, h, version) {
        var drawer = h.offcanvas('ws-page-source', message.file.name);
        var body = drawer.querySelector('.drawer-body');

        drawer.style.width = 'min(48rem, 100vw)';
        drawer.querySelector('.drawer-title').textContent = message.file.name;
        h.clear(body);
        body.appendChild(h.el('div', 'spinner-border spinner-border-sm'));
        h.showOffcanvas(drawer);

        h.api('ws_page_source', { message_id: message.id, version: version || 0 }).then(function (data) {
            h.clear(body);

            var bar = h.el('div', 'd-flex align-items-center gap-2 mb-3');
            var pick = h.el('select', 'form-control form-control-sm w-auto');

            pick.setAttribute('aria-label', h.t('page_versions'));
            (data.versions || []).slice().reverse().forEach(function (row) {
                var option = h.el('option', '', h.t('page_version', row.version) + ' · '
                    + (row.source === 'restore' ? h.t('page_restored') : '@' + (row.agent || '')) + ' · ' + formatTime(row.created_at));

                option.value = row.version;
                option.selected = (row.version === data.version);
                pick.appendChild(option);
            });
            pick.addEventListener('change', function () {
                showSource(message, h, parseInt(pick.value, 10) || 0);
            });
            bar.appendChild(pick);

            var copy = h.button('btn btn-sm btn-outline theme-secondary ms-auto', h.t('page_copy'), 'bi-clipboard');

            copy.addEventListener('click', function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(data.html).then(function () { h.toast(h.t('page_copied')); }).catch(h.fail);
                }
            });
            bar.appendChild(copy);
            body.appendChild(bar);

            // Shown as text, never as markup.
            var pre = h.el('pre', 'small border rounded p-3 mb-0 text-break');

            pre.style.whiteSpace = 'pre-wrap';
            pre.appendChild(h.el('code', '', data.html));
            body.appendChild(pre);
        }).catch(function (error) {
            h.clear(body);
            h.fail(error);
        });
    }

    // The card. opts.replace(message) draws a message the server sent back.
    function card(message, h, opts) {
        var file = message.file;
        var page = file.page;
        var root = h.el('div', 'ws-page-card border rounded p-3');
        var head = h.el('div', 'd-flex align-items-center gap-2');

        root.dataset.pageMessage = message.id;
        head.appendChild(h.icon('bi-window-stack'));
        head.appendChild(h.el('strong', 'text-truncate', file.name.replace(/\.html$/i, '')));
        root.appendChild(head);

        var maker = h.el('div', 'small fg-secondary mt-1', page.by ? h.t('page_by', page.by) : '');
        root.appendChild(maker);

        if (page.edited_by) {
            var mark = h.el('div', 'small fg-secondary d-flex align-items-center gap-2 mt-1');

            mark.appendChild(h.icon('bi-robot'));
            mark.appendChild(h.el('span', '', h.t('page_edited_by', page.edited_by)));

            if (page.can_undo) {
                var undo = h.button('btn btn-sm btn-text theme-secondary py-0', h.t('page_undo'), 'bi-arrow-counterclockwise');

                undo.addEventListener('click', function () {
                    h.ask(h.t('page_undo_ask'), h.t('page_undo')).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        undo.disabled = true;
                        h.api('ws_page_undo', { message_id: message.id, version: page.version }).then(function (data) {
                            h.toast(h.t('page_undone'));
                            opts.replace(data.message);
                        }).catch(function (error) {
                            undo.disabled = false;
                            h.fail(error);
                        });
                    });
                });
                mark.appendChild(undo);
            }

            root.appendChild(mark);
        }

        var tools = h.el('div', 'd-flex flex-wrap gap-2 mt-2');
        var openButton = h.button('btn btn-sm btn-solid theme-primary', h.t('page_open'), 'bi-play-fill');
        var fullButton = h.button('btn btn-sm btn-outline theme-secondary', h.t('page_full_screen'), 'bi-arrows-fullscreen');
        var sourceButton = h.button('btn btn-sm btn-outline theme-secondary', h.t('page_source'), 'bi-code-slash');
        var download = h.el('a', 'btn btn-sm btn-outline theme-secondary');

        download.href = file.url;
        download.setAttribute('download', file.name);
        download.appendChild(h.icon('bi-download', 'me-1'));
        download.appendChild(document.createTextNode(h.t('page_download')));

        // The frame's box: the agent's line above the frame, so the page can
        // never cover it, in the card and in full screen.
        var box = h.el('div', 'mt-2');
        var line = h.el('div', 'small fg-secondary d-flex align-items-center gap-1 px-1 py-1');

        box.hidden = true;
        line.appendChild(h.icon('bi-robot'));
        line.appendChild(h.el('span', '', h.t('page_by', page.by || '?')));
        box.appendChild(line);

        // Shown when the shell stopped the page at a bound.
        var stopped = h.el('div', 'small fg-danger d-flex align-items-center gap-2 mt-2');

        stopped.hidden = true;
        stopped.appendChild(h.icon('bi-stop-circle'));
        stopped.appendChild(h.el('span', '', h.t('page_stopped')));

        var again = h.button('btn btn-sm btn-text theme-secondary py-0', h.t('page_open_again'), 'bi-arrow-repeat');

        stopped.appendChild(again);

        function onStop() {
            setOpen(false);
            stopped.hidden = false;
        }

        function start() {
            stopped.hidden = true;
            close(message.id);
            startFrame(message, box, h, onStop);
            setOpen(true);
        }

        again.addEventListener('click', start);

        function setOpen(on) {
            openButton.lastChild.textContent = on ? h.t('page_close') : h.t('page_open');
            openButton.firstChild.className = 'bi me-1 ' + (on ? 'bi-stop-fill' : 'bi-play-fill');
        }

        openButton.addEventListener('click', function () {
            if (open[message.id] && (open[message.id].box === box)) {
                close(message.id);
                setOpen(false);
                return;
            }

            start();
        });

        fullButton.hidden = !(box.requestFullscreen && document.fullscreenEnabled);
        fullButton.addEventListener('click', function () {
            if (!open[message.id] || (open[message.id].box !== box)) {
                start();
            }

            box.requestFullscreen().catch(h.fail);
        });

        sourceButton.addEventListener('click', function () {
            showSource(message, h, 0);
        });

        tools.appendChild(openButton);
        tools.appendChild(fullButton);
        tools.appendChild(sourceButton);
        tools.appendChild(download);
        root.appendChild(tools);
        root.appendChild(stopped);
        root.appendChild(box);

        // Drawn again while it was open (an edit, Undo): open again, with
        // the newest version.
        if (open[message.id]) {
            start();
        }

        return root;
    }

    window.PGWsPageCard = { card: card, stopAll: stopAll };
})();
