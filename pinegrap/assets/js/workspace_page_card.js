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
 * includes/workspace/page_bridge.php). Before a page's first write in the
 * reader's name the card asks them (ws_page_consent,
 * includes/workspace/canvas_consents.php). The line naming the agent sits
 * outside the frame, so nothing in the page can cover it, in full screen
 * too.
 *
 * assets/js/workspace.js calls card() from filePreview() with its own
 * helpers, and joined() for a canvas with a drawing and a page
 * (file.joined, includes/workspace/canvas_card.php).
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

        function ask() {
            entry.h.api('ws_page_call', {
                message_id: entry.message.id,
                call: String(data.call || ''),
                args: JSON.stringify((data.args && (typeof data.args === 'object')) ? data.args : {})
            }).then(function (result) {
                var reply = (result && result.answer) || {};
                var question = result && result.ask;

                // A write waiting for the reader's answer: the panel asks,
                // never the page; Allow sends the same request again.
                if (question && (question.state === 'ask') && entry.onAsk) {
                    entry.onAsk(question).then(function (allowed) {
                        if (allowed) {
                            ask();
                        } else {
                            answer({ ok: false, error: reply.error });
                        }
                    });
                    return;
                }

                if (question && (question.state === 'no') && entry.onRefused) {
                    entry.onRefused(question);
                }

                answer(reply.ok ? { ok: true, data: reply.data } : { ok: false, error: reply.error || { code: 'failed', text: '' } });
            }).catch(function (error) {
                answer({ ok: false, error: { code: 'failed', text: String((error && error.message) || error || '') } });
            });
        }

        ask();
    }

    // The first-write question, drawn by the panel over the page: the
    // tables with what the page does to each, Don't allow and Allow. The
    // answer is kept for this page version (ws_page_consent); the promise
    // gives whether the page may write now. One question at a time a card:
    // writes asked meanwhile wait for the same answer.
    function consentDialog(message, h, question) {
        var dialog = document.createElement('dialog');
        var narrow = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
        var name = question.name || message.file.name.replace(/\.html$/i, '');

        dialog.className = 'ws-page-consent border rounded-3 p-4 bg-body fg-body';
        dialog.setAttribute('aria-labelledby', 'ws-page-consent-' + message.id);
        // On a phone a sheet from the bottom, both buttons full width.
        dialog.style.maxWidth = narrow ? '100vw' : '28rem';
        dialog.style.width = narrow ? '100vw' : 'calc(100vw - 2rem)';

        if (narrow) {
            dialog.style.margin = 'auto 0 0';
            dialog.style.borderBottomLeftRadius = '0';
            dialog.style.borderBottomRightRadius = '0';
        }

        var title = h.el('h2', 'h6 mb-3', h.t('page_consent_title', name));

        title.id = 'ws-page-consent-' + message.id;
        dialog.appendChild(title);

        var list = h.el('div', 'd-flex flex-column gap-2 mb-3');

        (question.tables || []).forEach(function (table) {
            var writes = (table.access === 'write');
            var row = h.el('div', 'd-flex align-items-center gap-2 border rounded px-3 py-2');
            var text = h.el('div', 'flex-grow-1');

            text.style.minWidth = '0';
            row.appendChild(h.icon('bi-table'));
            text.appendChild(h.el('div', 'text-truncate', table.name));
            text.appendChild(h.el('div', 'small fg-secondary', writes ? h.t('page_consent_write') : h.t('page_consent_read')));
            row.appendChild(text);
            row.appendChild(h.el('span', 'badge rounded-pill ' + (writes ? 'badge-subtle theme-warning' : 'badge-subtle theme-secondary'),
                writes ? h.t('page_consent_writes') : h.t('page_consent_reads')));
            list.appendChild(row);
        });
        dialog.appendChild(list);
        dialog.appendChild(h.el('p', 'small fg-secondary mb-4', h.t('page_consent_hint', name)));

        var buttons = h.el('div', 'd-flex gap-2 ' + (narrow ? '' : 'justify-content-end'));
        var deny = h.button('btn btn-sm btn-text theme-secondary' + (narrow ? ' flex-fill' : ''), h.t('page_consent_deny'));
        var allow = h.button('btn btn-sm btn-solid theme-primary' + (narrow ? ' flex-fill' : ''), h.t('page_consent_allow'));

        buttons.appendChild(deny);
        buttons.appendChild(allow);
        dialog.appendChild(buttons);
        document.body.appendChild(dialog);

        return new Promise(function (resolve) {
            var done = false;

            function finish(yes) {
                if (done) {
                    return;
                }

                done = true;
                allow.disabled = true;
                deny.disabled = true;
                h.api('ws_page_consent', { message_id: message.id, answer: yes ? 'yes' : 'no', version: question.version }).then(function () {
                    resolve(yes);
                }).catch(function (error) {
                    h.fail(error);
                    resolve(false);
                }).then(function () {
                    if (dialog.open) {
                        dialog.close();
                    }

                    dialog.remove();
                });
            }

            allow.addEventListener('click', function () { finish(true); });
            deny.addEventListener('click', function () { finish(false); });
            // Escape is no answer: nothing is kept and the write is refused.
            dialog.addEventListener('cancel', function (event) {
                event.preventDefault();
                finish(false);
            });

            if (dialog.showModal) {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
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

    // onStop: the shell stopped the page at a bound. hooks.onAsk(question)
    // asks the first-write question (a promise of whether it was allowed);
    // hooks.onRefused(question) shows that the reader said no.
    function startFrame(message, box, h, onStop, hooks) {
        var page = message.file.page;
        var frame = h.el('iframe', 'd-block w-100 border-0');

        frame.setAttribute('sandbox', page.sandbox);
        frame.setAttribute('referrerpolicy', 'no-referrer');
        frame.title = message.file.name;
        frame.style.height = MIN_HEIGHT + 'px';
        frame.src = page.frame;

        var entry = { frame: frame, box: box, message: message, h: h, onStop: onStop,
            onAsk: hooks && hooks.onAsk, onRefused: hooks && hooks.onRefused };

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

    // Bind a table: the tables the person may change, each bound for
    // reading or for reading and writing, or taken off
    // (includes/workspace/canvas_tables.php). The card is drawn again from
    // the message the server sends back.
    function showBind(message, h, opts) {
        var drawer = h.offcanvas('ws-page-bind', h.t('page_bind'));
        var body = drawer.querySelector('.drawer-body');

        drawer.querySelector('.drawer-title').textContent = h.t('page_bind');
        h.clear(body);
        body.appendChild(h.el('div', 'spinner-border spinner-border-sm'));
        h.showOffcanvas(drawer);

        function send(action, params) {
            params.message_id = message.id;
            h.api(action, params).then(function (data) {
                opts.replace(data.message);
                showBind(data.message, h, opts);
            }).catch(h.fail);
        }

        h.api('ws_page_tables', { message_id: message.id }).then(function (data) {
            h.clear(body);

            var tables = data.tables || [];

            if (!tables.length) {
                body.appendChild(h.el('p', 'fg-secondary small mb-0', h.t('page_bind_none')));
                return;
            }

            var list = h.el('div', 'd-flex flex-column gap-2');

            tables.forEach(function (table) {
                // The name keeps at least 12rem: on a narrow drawer it takes
                // its own line and the switch and Remove wrap under it.
                var row = h.el('div', 'd-flex flex-wrap align-items-center gap-2 border rounded px-3 py-2');
                var name = h.el('div', 'd-flex align-items-center gap-2');
                var segment = h.el('div', 'btn-group btn-group-sm ms-auto');

                name.style.flex = '1 1 12rem';
                name.style.minWidth = '0';
                name.appendChild(h.icon('bi-table'));
                name.appendChild(h.el('span', 'text-truncate', table.name));
                row.appendChild(name);

                [['read', h.t('page_bind_read')], ['write', h.t('page_bind_write')]].forEach(function (pair) {
                    var on = (table.bound === pair[0]);
                    var button = h.button('btn btn-sm ' + (on ? 'btn-solid theme-primary' : 'btn-outline theme-secondary'), pair[1]);

                    button.setAttribute('aria-pressed', on ? 'true' : 'false');
                    button.addEventListener('click', function () {
                        if (!on) {
                            send('ws_page_bind', { table_id: table.id, access: pair[0] });
                        }
                    });
                    segment.appendChild(button);
                });
                row.appendChild(segment);

                if (table.bound) {
                    var remove = h.button('btn btn-sm btn-text theme-secondary', h.t('page_unbind'), 'bi-x-lg');

                    remove.addEventListener('click', function () {
                        send('ws_page_unbind', { table_id: table.id });
                    });
                    row.appendChild(remove);
                }

                list.appendChild(row);
            });
            body.appendChild(list);
        }).catch(function (error) {
            h.clear(body);
            h.fail(error);
        });
    }

    // The line of tables the canvas uses: each with what the page may do
    // with it; a table the reader does not reach shows nameless. Bind a
    // table for whoever may write in the channel.
    function tablesLine(message, h, opts) {
        var page = message.file.page;
        var tables = page.tables || [];

        if (!tables.length && !page.can_bind) {
            return null;
        }

        var line = h.el('div', 'd-flex flex-wrap align-items-center gap-2 mt-2 small');

        line.appendChild(h.el('span', 'fg-secondary', h.t('page_tables')));

        tables.forEach(function (table) {
            var chip = h.el('span', 'badge badge-subtle theme-secondary d-inline-flex align-items-center gap-1');

            chip.appendChild(h.icon('bi-table'));
            chip.appendChild(h.el('span', '', table.reached ? table.name : h.t('page_table_hidden')));
            chip.appendChild(h.el('span', 'fg-secondary', table.access === 'write' ? h.t('page_table_write') : h.t('page_table_read')));
            line.appendChild(chip);
        });

        if (page.can_bind) {
            var bind = h.button('btn btn-sm btn-text theme-secondary py-0', h.t('page_bind'), 'bi-plus-lg');

            bind.addEventListener('click', function () {
                showBind(message, h, opts);
            });
            line.appendChild(bind);
        }

        return line;
    }

    // The card. opts.replace(message) draws a message the server sent back.
    function card(message, h, opts) {
        var file = message.file;
        var page = file.page;
        var root = h.el('div', 'ws-page-card border rounded p-3');
        var head = h.el('div', 'd-flex align-items-center gap-2');

        root.dataset.pageMessage = message.id;

        // Inside the joined card (joined()) the card's own name and maker
        // line give way to the joined card's.
        if (opts.bare) {
            root.className = 'ws-page-card';
        } else {
            head.appendChild(h.icon('bi-window-stack'));
            head.appendChild(h.el('strong', 'text-truncate', file.name.replace(/\.html$/i, '')));
            root.appendChild(head);
            root.appendChild(h.el('div', 'small fg-secondary mt-1', page.by ? h.t('page_by', page.by) : ''));
        }

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

        var tables = tablesLine(message, h, opts);

        if (tables) {
            root.appendChild(tables);
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

        // The reader said no to the page's writes: an amber band, with
        // Change to answer again.
        var refused = h.el('div', 'alert theme-warning small d-flex flex-wrap align-items-center gap-2 py-2 px-3 mt-2 mb-0');
        var refusedQuestion = null;
        var asking = null;

        refused.hidden = true;
        refused.appendChild(h.icon('bi-shield-exclamation'));
        refused.appendChild(h.el('span', '', h.t('page_consent_refused')));

        var change = h.button('btn btn-sm btn-text theme-secondary py-0 ms-auto', h.t('page_consent_change'));

        refused.appendChild(change);

        function onAsk(question) {
            if (!asking) {
                asking = consentDialog(message, h, question).then(function (yes) {
                    asking = null;
                    refusedQuestion = question;
                    refused.hidden = yes;

                    return yes;
                });
            }

            return asking;
        }

        function onRefused(question) {
            refusedQuestion = question;
            refused.hidden = false;
        }

        change.addEventListener('click', function () {
            if (refusedQuestion) {
                onAsk(refusedQuestion);
            }
        });

        function start() {
            stopped.hidden = true;
            close(message.id);
            startFrame(message, box, h, onStop, { onAsk: onAsk, onRefused: onRefused });
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

        // A page added beside a drawing has no address of its own here.
        if (!opts.bare) {
            tools.appendChild(download);
        }
        root.appendChild(tools);
        root.appendChild(stopped);
        root.appendChild(refused);
        root.appendChild(box);

        // Drawn again while it was open (an edit, Undo): open again, with
        // the newest version.
        if (open[message.id]) {
            start();
        }

        return root;
    }

    // The tab each joined card shows, by message id, kept while the
    // conversation draws the card again.
    var tabs = {};

    function partLabel(h, part) {
        return (part === 'page') ? h.t('canvas_part_page') : h.t('canvas_part_drawing');
    }

    // The one list of versions over both parts (canvas_card.php): All, Page
    // or Drawing; a page version shows its source, any older version is put
    // back as the newest one (ws_canvas_restore).
    function showVersions(message, pageMessage, h, opts, filter) {
        var joinedCard = message.file.joined;
        var drawer = h.offcanvas('ws-canvas-versions', h.t('page_versions'));
        var body = drawer.querySelector('.drawer-body');
        var versions = joinedCard.versions || [];
        var counts = { all: versions.length, page: 0, drawing: 0 };

        filter = filter || 'all';
        drawer.querySelector('.drawer-title').textContent = h.t('page_versions');
        h.clear(body);

        versions.forEach(function (row) {
            counts[row.part] = (counts[row.part] || 0) + 1;
        });

        var segment = h.el('div', 'btn-group btn-group-sm mb-3');

        segment.setAttribute('role', 'group');
        [['all', h.t('canvas_all')], ['page', h.t('canvas_part_page')], ['drawing', h.t('canvas_part_drawing')]].forEach(function (pair) {
            var on = (pair[0] === filter);
            var choice = h.button('btn btn-sm ' + (on ? 'btn-solid theme-primary' : 'btn-outline theme-secondary'), pair[1] + ' ' + counts[pair[0]]);

            choice.setAttribute('aria-pressed', on ? 'true' : 'false');
            choice.addEventListener('click', function () {
                showVersions(message, pageMessage, h, opts, pair[0]);
            });
            segment.appendChild(choice);
        });
        body.appendChild(segment);

        var list = h.el('div', 'd-flex flex-column gap-2');

        versions.forEach(function (row) {
            if ((filter !== 'all') && (row.part !== filter)) {
                return;
            }

            var item = h.el('div', 'd-flex align-items-start gap-2 border rounded px-3 py-2');
            var text = h.el('div', 'flex-grow-1');
            var top = h.el('div', 'd-flex align-items-center gap-2');
            var who = (row.source === 'restore') ? h.t('page_restored') : (row.by ? '@' + row.by : '');

            text.style.minWidth = '0';
            item.appendChild(h.icon(row.part === 'page' ? 'bi-window' : 'bi-vector-pen'));
            top.appendChild(h.el('strong', 'small', h.t('canvas_part_version', partLabel(h, row.part), row.version)));
            top.appendChild(h.el('span', 'small fg-secondary ms-auto', formatTime(row.created_at)));
            text.appendChild(top);

            if (who) {
                text.appendChild(h.el('div', 'small fg-secondary text-truncate', who));
            }

            var actions = h.el('div', 'd-flex flex-wrap gap-2 mt-1');

            if (row.current) {
                actions.appendChild(h.el('span', 'badge badge-subtle theme-success', h.t('canvas_now')));
            }

            if (row.part === 'page') {
                var show = h.button('btn btn-sm btn-outline theme-secondary py-0', h.t('canvas_show'));

                show.addEventListener('click', function () {
                    h.hideOffcanvas(drawer);
                    showSource(pageMessage, h, row.version);
                });
                actions.appendChild(show);
            }

            if (!row.current && joinedCard.can_restore) {
                var restore = h.button('btn btn-sm btn-text theme-secondary py-0', h.t('canvas_restore'), 'bi-arrow-counterclockwise');

                restore.addEventListener('click', function () {
                    h.ask(h.t('canvas_restore_ask'), h.t('canvas_restore')).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        restore.disabled = true;
                        h.api('ws_canvas_restore', { message_id: message.id, part: row.part, version: row.version }).then(function (data) {
                            h.toast(h.t('canvas_restored'));
                            opts.replace(data.message);
                            showVersions(data.message, pageMessage, h, opts, filter);
                        }).catch(function (error) {
                            restore.disabled = false;
                            h.fail(error);
                        });
                    });
                });
                actions.appendChild(restore);
            }

            if (actions.childNodes.length) {
                text.appendChild(actions);
            }

            item.appendChild(text);
            list.appendChild(item);
        });
        body.appendChild(list);
        h.showOffcanvas(drawer);
    }

    // The joined card of a canvas with a drawing and a live page
    // (file.joined, includes/workspace/canvas_card.php): its name, the
    // parts as chips, the agent's line, a switch between Drawing and Page,
    // and one Versions drawer. The page tab holds the page card itself, so
    // its frame, bridge and first-write question stay as they are; leaving
    // the tab stops the page.
    function joined(message, h, opts) {
        var joinedCard = message.file.joined;
        var page = joinedCard.page;
        var root = h.el('div', 'ws-page-card ws-canvas-card border rounded p-3');
        var head = h.el('div', 'd-flex flex-wrap align-items-center gap-2');
        var name = h.el('strong', 'text-truncate', joinedCard.name);
        var chips = h.el('div', 'd-flex flex-wrap gap-1 ms-auto');
        var pageMessage = { id: message.id, file: { name: joinedCard.name + '.html', url: '', page: page } };

        root.dataset.pageMessage = message.id;
        head.appendChild(h.icon('bi-easel2'));
        name.style.minWidth = '0';
        head.appendChild(name);

        [['bi-vector-pen', h.t('canvas_part_drawing')], ['bi-window', h.t('canvas_part_page')]].concat(
            (page.tables || []).length ? [['bi-table', h.t('canvas_part_data', page.tables.length)]] : []
        ).forEach(function (pair) {
            var chip = h.el('span', 'badge badge-subtle theme-secondary d-inline-flex align-items-center gap-1');

            chip.appendChild(h.icon(pair[0]));
            chip.appendChild(h.el('span', '', pair[1]));
            chips.appendChild(chip);
        });
        head.appendChild(chips);
        root.appendChild(head);

        // The agent's line, outside any frame.
        var maker = h.el('div', 'small fg-secondary d-flex align-items-center gap-1 mt-1');

        maker.appendChild(h.icon('bi-robot'));
        maker.appendChild(h.el('span', '', h.t('page_by', page.by || '?')));
        root.appendChild(maker);

        var segment = h.el('div', 'btn-group btn-group-sm mt-2');
        var stage = h.el('div', 'mt-2');
        var tab = tabs[message.id] || (open[message.id] ? 'page' : 'drawing');

        segment.setAttribute('role', 'group');
        segment.setAttribute('aria-label', joinedCard.name);

        function show(which) {
            tab = which;
            tabs[message.id] = which;

            // The page runs only while its tab is shown.
            if (which !== 'page') {
                close(message.id);
            }

            Array.prototype.forEach.call(segment.children, function (choice) {
                var on = (choice.dataset.tab === which);

                choice.className = 'btn btn-sm ' + (on ? 'btn-solid theme-primary' : 'btn-outline theme-secondary');
                choice.setAttribute('aria-pressed', on ? 'true' : 'false');
            });

            h.clear(stage);

            if (which === 'page') {
                stage.appendChild(card(pageMessage, h, { replace: opts.replace, bare: true }));
                return;
            }

            var link = h.el('a', 'ws-image-link');
            var img = h.el('img');

            link.href = joinedCard.drawing.url;
            link.target = '_blank';
            link.rel = 'noopener';
            img.src = joinedCard.drawing.url;
            img.alt = joinedCard.name;
            img.loading = 'lazy';
            link.appendChild(img);
            link.addEventListener('click', function (event) {
                if (opts.showImage && !(event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1)) {
                    event.preventDefault();
                    opts.showImage(joinedCard.drawing.url);
                }
            });

            // An agent's drawing still blank: this browser draws its picture
            // (assets/js/workspace_canvas_picture.js) for the drawing part.
            if (joinedCard.drawing.pending && window.PGWsCanvasPicture) {
                window.PGWsCanvasPicture.mount({ id: message.id, file: { canvas_pending: joinedCard.drawing.pending, canvas_part: 'drawing', url: joinedCard.drawing.url } }, img, link, h);
            }

            stage.appendChild(link);
        }

        [['drawing', 'bi-vector-pen'], ['page', 'bi-window']].forEach(function (pair) {
            var choice = h.button('btn btn-sm', partLabel(h, pair[0]), pair[1]);

            choice.dataset.tab = pair[0];
            choice.addEventListener('click', function () {
                if (tab !== pair[0]) {
                    show(pair[0]);
                }
            });
            segment.appendChild(choice);
        });
        root.appendChild(segment);
        root.appendChild(stage);

        var foot = h.el('div', 'd-flex flex-wrap gap-2 mt-2');
        var versionsButton = h.button('btn btn-sm btn-text theme-secondary', h.t('page_versions'), 'bi-clock-history');

        versionsButton.addEventListener('click', function () {
            showVersions(message, pageMessage, h, opts, 'all');
        });
        foot.appendChild(versionsButton);
        root.appendChild(foot);
        show(tab);

        return root;
    }

    window.PGWsPageCard = { card: card, joined: joined, stopAll: stopAll };
})();
