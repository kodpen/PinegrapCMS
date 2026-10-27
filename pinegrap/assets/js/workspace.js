/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the screens: channels (workspace.php), tasks
 * (workspace_tasks.php), the planning board (workspace_board.php), the work
 * calendar (workspace_calendar.php), the decision timeline
 * (workspace_timeline.php) and the Workspace drawer on the order, contact
 * and product screens. The flows screen (workspace_flows.php) is drawn by
 * workspace_flow.js, which is started from here.
 *
 * Served minified as part of assets/js/workspace.min.js, together with the
 * workspace_*.js files it leans on (pg_js_bundles(), includes/fn/output.php).
 * After editing any of them run `php tools/build_js.php`; CI fails while the
 * .min.js no longer matches.
 *
 * Every text comes from the #ws-config block (translated on the PHP side);
 * this file holds no visible literal. Anything the server renders as HTML
 * (message bodies, task cards, tag chips) was escaped there; everything
 * else is written with textContent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('ws-config');

    if (!configNode) {
        return;
    }

    var CFG;

    try {
        CFG = JSON.parse(configNode.textContent || '{}');
    } catch (error) {
        return;
    }

    var S = CFG.strings || {};
    var BOOT = null;

    // ── Small helpers ──────────────────────────────────────────────────

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    // A finger rather than a mouse: the keyboard is on the screen, so the
    // composer is not focused on its own and Enter starts a new line.
    function touchScreen() {
        return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((text !== undefined) && (text !== null) && (text !== '')) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name, extra) {
        var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    // Plays a one-off motion class from its start again; the keyframes in
    // backend.src.css run only under prefers-reduced-motion: no-preference,
    // so without them the class is a harmless leftover.
    function playMotion(node, className) {
        if (!node) {
            return;
        }

        node.classList.remove(className);
        void node.offsetWidth;
        node.classList.add(className);
        node.addEventListener('animationend', function () { node.classList.remove(className); }, { once: true });
    }

    // An icon with a small mark in its corner (ws_icon_badged_html() in
    // includes/workspace/channel_icons.php); the plain icon without one.
    function badgedIcon(name, badge, extra) {
        if (!badge) {
            return icon(name, extra);
        }

        var node = el('span', 'ws-icon-badged' + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        node.appendChild(el('i', 'bi ' + name));
        node.appendChild(el('i', 'bi ' + badge + ' ws-icon-badge'));
        return node;
    }

    // A channel's icon: the one it was given, with the mark of its kind in
    // the corner (a lock, a door, a person), or the one its kind gives.
    function channelIcon(channel, extra) {
        var name = channel.icon || (channel.kind === 'private' ? 'bi-lock' : (channel.contact_id ? 'bi-person-vcard' : 'bi-hash'));

        return badgedIcon(name, channel.icon_badge || '', extra);
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';

        if (iconName) {
            node.appendChild(icon(iconName, label ? 'me-1' : ''));
        }

        if (label) {
            node.appendChild(document.createTextNode(label));
        }

        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
        }

        return node;
    }

    // "2026-10-16" as people write it: 16.10.2026.
    function dmy(value) {
        var parts = String(value || '').split('-');

        return (parts.length === 3) ? (parts[2] + '.' + parts[1] + '.' + parts[0]) : '';
    }

    // Server-rendered markup: escaped on the PHP side before it got here.
    function setHtml(node, markup) {
        node.innerHTML = markup || '';
        return node;
    }

    function clear(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    // ── Drawings ───────────────────────────────────────────────────────

    // Plain shapes, no words, coloured by the panel's theme (.pg-tour-art in
    // assets/css/pg_tour.css, loaded on every panel screen), so a drawing
    // reads the same in every language and in light and dark alike. The
    // channel screen's tour (workspace_tour.js) draws its steps with the
    // same kit, read from window.PGWsArt. Only numbers and fixed class
    // names go into the markup.
    var ART = (function () {
        function rect(x, y, w, h, r, cls) {
            return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="' + r + '" class="' + cls + '"/>';
        }

        function circle(cx, cy, r, cls) {
            return '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" class="' + cls + '"/>';
        }

        function path(d, cls) {
            return '<path d="' + d + '" class="' + cls + '"/>';
        }

        // A line of text, as a rounded bar.
        function bar(x, y, w, cls) {
            return rect(x, y, w, 5, 2.5, cls || 'a-line');
        }

        // A "#" sign.
        function hash(x, y, cls) {
            return path('M' + (x + 2) + ' ' + y + 'l-1 8M' + (x + 6) + ' ' + y + 'l-1 8M' + x + ' ' + (y + 2.5) + 'h8M' + (x - 1) + ' ' + (y + 5.5) + 'h8', cls || 'a-stroke');
        }

        // A padlock.
        function lock(x, y, cls) {
            return path('M' + (x + 1.5) + ' ' + (y + 4) + 'v-1.5a2.5 2.5 0 0 1 5 0v1.5', cls || 'a-stroke') + rect(x, y + 4, 8, 5.5, 1.2, 'a-ink');
        }

        function face(cx, cy, r, cls) {
            return circle(cx, cy, r, cls || 'a-acc-soft') + circle(cx, cy - r * 0.25, r * 0.38, 'a-ink') + path('M' + (cx - r * 0.6) + ' ' + (cy + r * 0.72) + 'a' + (r * 0.6) + ' ' + (r * 0.5) + ' 0 0 1 ' + (r * 1.2) + ' 0', 'a-ink');
        }

        // A round badge with a tick in it.
        function tick(cx, cy, r, cls) {
            return circle(cx, cy, r, cls || 'a-ok') + path('M' + (cx - r * 0.45) + ' ' + cy + 'l' + (r * 0.32) + ' ' + (r * 0.32) + ' ' + (r * 0.6) + '-' + (r * 0.6), 'a-stroke-white');
        }

        // The tour's drawings are 300 by 120; an empty list's are 120 by 80.
        function svg(body, viewBox, extra) {
            return '<svg class="pg-tour-art' + (extra ? ' ' + extra : '') + '" viewBox="' + (viewBox || '0 0 300 120') + '" role="img" aria-hidden="true" focusable="false">' + body + '</svg>';
        }

        // What an empty list shows above its line.
        var EMPTY = {
            channels: function () {
                var out = path('M34 12h40a12 12 0 0 1 12 12v20a12 12 0 0 1-12 12h-32l-10 9v-9.4a12 12 0 0 1-10-11.6v-20a12 12 0 0 1 12-12z', 'a-panel');

                out += hash(36, 25, 'a-stroke-acc') + bar(48, 26.5, 26, 'a-acc');
                out += bar(36, 39, 38);
                out += circle(90, 18, 10, 'a-acc') + path('M90 13v10M85 18h10', 'a-stroke-white');
                out += circle(100, 50, 2, 'a-warn') + circle(16, 30, 1.8, 'a-acc-soft');

                return out;
            },

            tasks: function () {
                var out = rect(28, 8, 60, 64, 9, 'a-panel');

                for (var i = 0; i < 3; i++) {
                    var y = 19 + (i * 15);

                    out += rect(36, y, 9, 9, 2.5, 'a-acc-soft') + path('M38 ' + (y + 4.5) + 'l2 2 3.5-3.5', 'a-stroke-acc');
                    out += bar(50, y + 2, 28 - (i * 6));
                }

                out += tick(88, 60, 11);
                out += circle(102, 24, 2, 'a-warn') + circle(18, 56, 1.8, 'a-acc-soft');

                return out;
            },

            inbox: function () {
                var out = path('M28 44l9-22h46l9 22v20a5 5 0 0 1-5 5h-54a5 5 0 0 1-5-5z', 'a-panel');

                out += path('M28 44h19l4 7h18l4-7h19', 'a-stroke');
                out += tick(90, 18, 10);
                out += circle(22, 22, 2, 'a-warn') + circle(104, 48, 1.8, 'a-acc-soft') + circle(60, 10, 1.6, 'a-acc');

                return out;
            },

            notes: function () {
                var out = rect(32, 8, 52, 64, 7, 'a-panel');

                out += bar(40, 18, 26, 'a-ink');
                out += bar(40, 29, 36) + bar(40, 38, 30) + bar(40, 47, 22);
                out += path('M70 68l20-20 6 6-20 20h-6z', 'a-acc') + path('M86 52l6 6', 'a-stroke-white');
                out += circle(100, 22, 2, 'a-warn') + circle(20, 50, 1.8, 'a-acc-soft');

                return out;
            }
        };

        function empty(kind) {
            return EMPTY[kind] ? svg(EMPTY[kind](), '0 0 120 80', 'ws-empty-art') : '';
        }

        return { rect: rect, circle: circle, path: path, bar: bar, hash: hash, lock: lock, face: face, tick: tick, svg: svg, empty: empty };
    })();

    window.PGWsArt = ART;

    // An empty list: a small drawing (channels, tasks, inbox, notes) and one
    // line saying so.
    function emptyState(kind, text, extra) {
        var box = el('div', 'ws-empty ws-empty-friendly' + (extra ? ' ' + extra : ''));

        setHtml(box, ART.empty(kind));
        box.appendChild(el('div', 'ws-empty-line', text));

        return box;
    }

    // In place of a "Loading…" line: grey rows standing for what is on its
    // way; the word stays for screen readers. Removed by whatever clears the
    // box it sits in once the answer is there.
    function skeleton(rows, extra) {
        var box = el('div', 'ws-skeleton' + (extra ? ' ' + extra : ''));

        box.setAttribute('role', 'status');
        box.setAttribute('aria-busy', 'true');
        box.appendChild(el('span', 'visually-hidden', t('loading')));

        for (var i = 0; i < (rows || 3); i++) {
            var row = el('div', 'ws-skeleton-row');

            row.setAttribute('aria-hidden', 'true');
            row.appendChild(el('span', 'ws-skeleton-dot'));
            row.appendChild(el('span', 'ws-skeleton-bar'));
            box.appendChild(row);
        }

        return box;
    }

    // options.keepalive: the request is finished even when the page is left
    // meanwhile (a save as the page closes); browsers refuse such a body over
    // 64 KiB.
    function api(action, data, options) {
        var payload = { action: action, token: CFG.token };

        Object.keys(data || {}).forEach(function (key) {
            payload[key] = data[key];
        });

        return fetch(CFG.api_url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: !!(options && options.keepalive),
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(t('error_generic'));
            });
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                var error = new Error((json && json.message) ? json.message : t('error_generic'));
                error.field = json ? json.field : '';
                error.code = (json && json.code) ? json.code : '';
                error.stale = !!(json && json.stale);
                throw error;
            }

            return json.data || {};
        });
    }

    var toastRoot = null;

    function toast(message, kind, action) {
        if (!toastRoot) {
            toastRoot = el('div', 'toast-container position-fixed bottom-0 start-0 p-5');
            toastRoot.style.zIndex = 1090;
            document.body.appendChild(toastRoot);
        }

        var box = el('div', 'toast align-items-center border-0 text-bg-' + (kind || 'dark'));
        box.setAttribute('role', 'status');

        var row = el('div', 'd-flex');
        var text = el('div', 'toast-body', message);

        // A way to what was just done: the note kept, the channel made.
        if (action && action.href) {
            var go = el('a', 'ms-3 fw-semibold fg-reset', action.label);
            go.href = action.href;
            text.appendChild(go);
        }

        row.appendChild(text);

        var close = button('btn-close fg-white me-3 m-auto', '', '', t('close'));
        close.setAttribute('data-bs-dismiss', 'toast');
        row.appendChild(close);
        box.appendChild(row);
        toastRoot.appendChild(box);

        // Bootstrap's Toast: hides after five seconds, pauses while hovered
        // or focused, and the close button dismisses it.
        var instance = window.pgUI ? pgUI.toast.get(box, { delay: 5000 }) : null;

        if (instance) {
            pgUI.toast.on(box, 'hidden', function () { box.remove(); });
            instance.show();
        } else {
            box.classList.add('show');
            close.addEventListener('click', function () { box.remove(); });
            setTimeout(function () { box.remove(); }, 5000);
        }
    }

    function fail(error) {
        toast((error && error.message) ? error.message : t('error_generic'), 'danger');
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var self = this;
            var args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    // One modal reused for every question the screens ask. askUp holds from
    // its show until its hidden event: while it fades out it is no longer
    // open, yet it cannot be opened again.
    var dialog = null;
    var askUp = false;

    function ask(message, okLabel, danger, details) {
        return new Promise(function (resolve) {
            if (!dialog) {
                dialog = el('dialog', 'dialog ws-ask-modal pg-dialog-centered');
                dialog.id = 'ws-ask';
                dialog.tabIndex = -1;
                dialog.innerHTML = '<div class="dialog-body"><p class="mb-3" data-ws-ask-text></p><div data-ws-ask-details></div></div>'
                    + '<div class="dialog-footer"><button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-dismiss="dialog" data-ws-ask-cancel></button>'
                    + '<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-ws-ask-ok></button></div>';
                document.body.appendChild(dialog);

                pgUI.dialog.on(dialog, 'show', function () {
                    askUp = true;
                });
                pgUI.dialog.on(dialog, 'hidden', function () {
                    askUp = false;
                });
            }

            dialog.querySelector('[data-ws-ask-text]').textContent = message;
            var detailBox = clear(dialog.querySelector('[data-ws-ask-details]'));

            if (details) {
                detailBox.appendChild(details);
            }

            var ok = dialog.querySelector('[data-ws-ask-ok]');
            var cancel = dialog.querySelector('[data-ws-ask-cancel]');

            ok.textContent = okLabel || t('ok');
            ok.className = 'btn btn-sm rounded-pill px-5 ' + (danger ? 'btn-outline theme-warning' : 'btn-solid theme-primary');
            cancel.textContent = t('cancel');

            var modal = pgUI.dialog.get(dialog);
            var answered = false;

            ok.onclick = function () {
                answered = true;
                modal.hide();
                resolve(true);
            };

            pgUI.dialog.on(dialog, 'hidden', function () {

                if (!answered) {
                    resolve(false);
                }
            }, { once: true });

            modal.show();
        });
    }

    // Once the question dialog has closed, so the next question can open it
    // again (it cannot while it is still fading out).
    function askClosed() {
        return new Promise(function (resolve) {
            if (!dialog || !askUp) {
                resolve();
                return;
            }

            pgUI.dialog.on(dialog, 'hidden', function () {
                resolve();
            }, { once: true });
        });
    }

    function absoluteUrl(url) {
        try {
            return new URL(url, window.location.href).href;
        } catch (error) {
            return url;
        }
    }

    // The clipboard API needs a secure page; elsewhere, or when the browser
    // refuses it, the text is copied from a selected field, and when even
    // that is refused it opens in a box to be copied by hand.
    function copyText(text) {
        var done = function () { toast(t('copied'), 'success'); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
            return;
        }

        fallback();

        function fallback() {
            if (copyFromField(text)) {
                done();
            } else {
                textSheet(text, true);
            }
        }
    }

    // execCommand('copy') copies the selection, so the text goes into a field
    // for a moment. iOS selects a field's text only with setSelectionRange
    // and zooms in on one under 16px. An open modal or drawer takes the focus
    // back from anything outside it, so the field goes inside that.
    function copyFromField(text, host) {
        var area = el('textarea');
        var copied = false;

        area.value = text;
        area.setAttribute('readonly', '');
        area.style.cssText = 'position:fixed;top:0;left:-9999px;font-size:16px;';
        (host || document.querySelector('.dialog[open], .drawer[open]') || document.body).appendChild(area);
        area.select();
        area.setSelectionRange(0, text.length);

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        area.remove();

        return copied;
    }

    // What a message says as it reads on the screen: its lines, list items and
    // table rows each on a line of their own. innerText follows the layout,
    // so the markup is laid out off screen for a moment.
    function readableText(markup) {
        var box = el('div');

        box.innerHTML = markup || '';

        // A form's card under the text is drawn from the submission; it is
        // not part of what was written.
        Array.prototype.forEach.call(box.querySelectorAll('.ws-form-card'), function (card) {
            card.parentNode.removeChild(card);
        });

        box.style.cssText = 'position:fixed;top:0;left:-9999px;width:40rem;';
        document.body.appendChild(box);

        var text = box.innerText || box.textContent || '';

        box.remove();

        return text.replace(/[ \t\u00a0]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    var copyModal = null;

    // A text in a box to select and copy by hand. On a phone the long press
    // on a message opens its menu, so it cannot also select words there; and
    // a browser that refuses to copy still lets the person copy from here.
    function textSheet(text, refused) {
        if (!copyModal) {
            copyModal = el('dialog', 'dialog ws-copy-modal pg-dialog-centered');
            copyModal.tabIndex = -1;
            copyModal.innerHTML = '<div class="dialog-header py-3"><h5 class="dialog-title"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
                + '<div class="dialog-body"><p class="small fg-secondary mb-3" data-ws-copy-note></p><textarea class="form-control ws-copy-area" readonly></textarea></div>'
                + '<div class="dialog-footer py-3"><button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-ws-copy-all></button></div>';
            document.body.appendChild(copyModal);
        }

        var modal = pgUI.dialog.get(copyModal);
        var body = copyModal.querySelector('.dialog-body');
        var note = copyModal.querySelector('[data-ws-copy-note]');
        var area = copyModal.querySelector('.ws-copy-area');
        var all = clear(copyModal.querySelector('[data-ws-copy-all]'));

        copyModal.querySelector('.dialog-title').textContent = t('select_text');
        copyModal.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        note.textContent = refused ? t('copy_refused') : t('copy_select_help');
        area.value = text;
        area.rows = Math.min(10, Math.max(3, text.split('\n').length + 1));
        all.appendChild(icon('bi-copy', 'me-1'));
        all.appendChild(document.createTextNode(t('copy_all')));

        // Everything selected, for the person to copy with the system's own
        // menu when the browser refuses the button too.
        function byHand() {
            note.textContent = t('copy_refused');
            area.focus();
            area.setSelectionRange(0, text.length);
        }

        function copied() {
            modal.hide();
            toast(t('copied'), 'success');
        }

        all.onclick = function () {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(copied, function () {
                    if (copyFromField(text, body)) {
                        copied();
                    } else {
                        byHand();
                    }
                });
            } else if (copyFromField(text, body)) {
                copied();
            } else {
                byHand();
            }
        };

        modal.show();
    }

    function addDays(date, days) {
        var day = new Date(date + 'T12:00:00');
        day.setDate(day.getDate() + days);

        return day.getFullYear() + '-' + String(day.getMonth() + 1).padStart(2, '0') + '-' + String(day.getDate()).padStart(2, '0');
    }

    // Pictures open in the panel's own viewer (zoom, swipe, the others of the
    // conversation beside it); a new tab when the viewer is not there.
    function viewImages(images, start) {
        if (window.pgLightbox && typeof window.pgLightbox.open === 'function') {
            window.pgLightbox.open(images, start || 0);
            return;
        }

        window.open(images[start || 0].src, '_blank', 'noopener');
    }

    // The panel's image editor waits in a template on the channel screen
    // (includes/workspace/screen.php) and is loaded the first time a picture
    // is edited: its styles, then its scripts one after the other.
    var imageEditorLoading = null;

    function loadImageEditor() {
        if (window.openImageEditor && window.imageEditorAvailable && window.imageEditorAvailable()) {
            return Promise.resolve();
        }

        if (imageEditorLoading) {
            return imageEditorLoading;
        }

        var template = document.getElementById('ws-image-editor-assets');

        if (!template || !template.content) {
            return Promise.reject(new Error(t('file_editor_missing')));
        }

        var chain = Promise.resolve();

        Array.prototype.forEach.call(template.content.childNodes, function (node) {
            if (node.nodeType !== 1) {
                return;
            }

            if (node.tagName === 'LINK') {
                var sheet = document.createElement('link');
                sheet.rel = 'stylesheet';
                sheet.href = node.getAttribute('href');
                document.head.appendChild(sheet);
                return;
            }

            if (node.tagName !== 'SCRIPT') {
                return;
            }

            chain = chain.then(function () {
                return new Promise(function (resolve, reject) {
                    var script = document.createElement('script');

                    if (node.getAttribute('src')) {
                        script.src = node.getAttribute('src');
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    } else {
                        script.text = node.textContent;
                        document.head.appendChild(script);
                        resolve();
                    }
                });
            });
        });

        imageEditorLoading = chain.then(function () {
            if (!(window.openImageEditor && window.imageEditorAvailable && window.imageEditorAvailable())) {
                throw new Error(t('file_editor_missing'));
            }
        });

        imageEditorLoading.catch(function () {
            imageEditorLoading = null;
        });

        return imageEditorLoading;
    }

    // The drawing code of the Word and Excel preview (assets/js/pg_office_view.js),
    // fetched the first time such a file is opened.
    var officeViewLoading = null;

    function loadOfficeView() {
        if (window.pgOfficeView) {
            return Promise.resolve();
        }

        if (officeViewLoading) {
            return officeViewLoading;
        }

        if (!CFG.office || !CFG.office.script) {
            return Promise.reject(new Error(t('error_generic')));
        }

        officeViewLoading = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = CFG.office.script;
            script.onload = function () { window.pgOfficeView ? resolve() : reject(new Error(t('error_generic'))); };
            script.onerror = function () { reject(new Error(t('error_generic'))); };
            document.head.appendChild(script);
        });

        officeViewLoading.catch(function () {
            officeViewLoading = null;
        });

        return officeViewLoading;
    }

    // A PDF, a video, or a Word or Excel file, large, without leaving the screen.
    //
    // An Excel workbook or a Word document is offered for editing to whoever
    // may write in its channel (ws_file_office with edit): the changed cells
    // or paragraphs are saved over the person's own file, the old one kept
    // as an earlier version, or sent as an edited copy of somebody else's
    // (ws_file_office_save), the way a text file is; onSaved hears the
    // answer.
    var previewModal = null;
    var previewSheets = null;
    var OFFICE_EDIT_NAME = /\.(xlsx|xlsm|xltx|xltm|docx|docm|dotx|dotm)$/i;

    function previewFile(file, onSaved) {
        if ((file.kind || '') === 'image' || file.image) {
            viewImages([{ src: file.url, alt: file.name, caption: file.name }], 0);
            return;
        }

        if (!previewModal) {
            previewModal = el('dialog', 'dialog ws-preview-modal dialog-xl pg-dialog-centered lg-down:dialog-fullscreen');
            previewModal.id = 'ws-preview';
            previewModal.tabIndex = -1;
            previewModal.innerHTML = '<div class="dialog-header py-3"><h5 class="dialog-title text-truncate"></h5>'
                + '<a class="btn btn-sm btn-text theme-secondary ms-auto" data-ws-open target="_blank" rel="noopener"></a>'
                + '<a class="btn btn-sm btn-text theme-secondary" data-ws-download></a>'
                + '<button type="button" class="btn-close ms-1" data-bs-dismiss="dialog"></button></div>'
                + '<div class="dialog-body p-0"></div><div class="dialog-footer py-3 d-none"></div>';
            document.body.appendChild(previewModal);

            // Changed cells or paragraphs are not thrown away by a click
            // beside the window or the cross without asking.
            pgUI.dialog.on(previewModal, 'hide', function (event) {
                var state = previewSheets;

                if (state && !state.saving && state.view && state.view.count() > 0
                    && !window.confirm(t((state.view.kind === 'docx') ? 'file_paragraphs_unsaved' : 'file_cells_unsaved'))) {
                    event.preventDefault();
                }
            });

            pgUI.dialog.on(previewModal, 'hidden', function () {
                clear(previewModal.querySelector('.dialog-body'));
                clear(previewModal.querySelector('.dialog-footer')).classList.add('d-none');
                previewSheets = null;
            });
        }

        previewSheets = null;
        clear(previewModal.querySelector('.dialog-footer')).classList.add('d-none');

        previewModal.querySelector('.dialog-title').textContent = file.name;
        previewModal.querySelector('.btn-close').setAttribute('aria-label', t('close'));

        var open = clear(previewModal.querySelector('[data-ws-open]'));
        open.href = file.url;
        open.appendChild(icon('bi-box-arrow-up-right', 'me-1'));
        open.appendChild(document.createTextNode(t('open_in_new_tab')));

        var save = clear(previewModal.querySelector('[data-ws-download]'));
        save.href = file.url;
        save.setAttribute('download', file.name);
        save.appendChild(icon('bi-download', 'me-1'));
        save.appendChild(document.createTextNode(t('download')));

        var body = clear(previewModal.querySelector('.dialog-body'));

        if ((file.kind || '') === 'video') {
            var video = el('video', 'ws-preview-video');
            video.src = file.url;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            body.appendChild(video);
        } else if (file.office && file.message_id) {
            // Read on the server, drawn here (ws_file_office).
            var box = el('div', 'ws-preview-office office-view');
            box.appendChild(skeleton(3));
            body.appendChild(box);

            var editing = OFFICE_EDIT_NAME.test(file.name || '');

            Promise.all([api('ws_file_office', { message_id: file.message_id, edit: editing ? 1 : 0 }), loadOfficeView()]).then(function (results) {
                if (!box.isConnected) {
                    return;
                }

                var data = results[0];
                var writable = editing && !!(data.can_replace || data.can_send);
                var state = { view: null, saving: false };
                var save = null;

                state.view = window.pgOfficeView.render(box, data.office, (CFG.office && CFG.office.strings) || {}, writable ? {
                    editable: true,
                    onChange: function (count) {
                        if (save) {
                            save.disabled = !count;
                        }
                    },
                    onSave: function () {
                        if (save && !save.disabled) {
                            save.click();
                        }
                    }
                } : null);

                if (!writable || !state.view) {
                    return;
                }

                previewSheets = state;

                var footer = previewModal.querySelector('.dialog-footer');
                footer.classList.remove('d-none');
                footer.appendChild(el('div', 'ws-grow small fg-secondary', data.can_replace ? t('file_text_mine_help') : t('file_text_copy_help')));

                save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', data.can_replace ? t('file_text_save') : t('file_text_send'), data.can_replace ? 'bi-check2' : 'bi-send');
                save.disabled = true;

                save.addEventListener('click', function () {
                    if (!state.view.count()) {
                        toast(t('file_text_unchanged'));
                        return;
                    }

                    save.disabled = true;
                    state.saving = true;

                    var request = {
                        message_id: file.message_id,
                        office_version: state.view.version,
                        as: data.can_replace ? 'replace' : 'send'
                    };

                    request[(state.view.kind === 'docx') ? 'paragraphs' : 'cells'] = state.view.edits();

                    api('ws_file_office_save', request).then(function (result) {
                        pgUI.dialog.hide(previewModal);
                        toast(data.can_replace ? t('file_replaced') : t('file_copy_sent'), 'success');

                        if (typeof onSaved === 'function') {
                            onSaved(result);
                        }
                    }).catch(function (error) {
                        state.saving = false;
                        save.disabled = false;
                        fail(error);
                    });
                });

                footer.appendChild(save);
            }).catch(function (error) {
                if (box.isConnected) {
                    clear(box);
                    box.appendChild(el('div', 'ws-empty', (error && error.message) ? error.message : t('error_generic')));
                }
            });
        } else {
            var frame = el('iframe', 'ws-preview-frame');
            frame.src = file.url;
            frame.title = file.name;
            body.appendChild(frame);
        }

        pgUI.dialog.show(previewModal);
    }

    // ── Context menus ──────────────────────────────────────────────────
    //
    // Right click (or a long press on a touch screen) on a message, a channel,
    // a task, a day, a tag: the things that can be done with it, where the
    // pointer is. Drawn with the panel's own context menu look.

    var ctxNode = null;
    var ctxOpenedAt = 0;

    function closeCtx() {
        if (ctxNode) {
            ctxNode.remove();
            ctxNode = null;
        }
    }

    // "above" opens it over the point, for buttons at the bottom of the screen.
    function ctxMenu(x, y, items, above) {
        closeCtx();

        var list = (items || []).filter(function (item, index, all) {
            if (!item) {
                return false;
            }

            // No divider at either end, and never two in a row.
            if (item === '-') {
                var previous = all.slice(0, index).filter(Boolean);
                var next = all.slice(index + 1).filter(Boolean);

                return previous.length && next.length && (previous[previous.length - 1] !== '-') && (next[0] !== '-');
            }

            return true;
        });

        if (!list.length) {
            return;
        }

        var menu = el('div', 'ws-ctx contextmenu popover shadow-lg backdrop');
        var body = el('div', 'popover-body');

        menu.setAttribute('role', 'menu');

        list.forEach(function (item) {
            if (item === '-') {
                body.appendChild(el('hr', 'divider my-1'));
                return;
            }

            if (item.header) {
                body.appendChild(el('div', 'ws-ctx-header', item.header));
                return;
            }

            // A row of emoji at the top of a message's menu.
            if (item.emojis) {
                var row = el('div', 'ws-ctx-emoji');

                item.emojis.forEach(function (emoji) {
                    var pick = el('button', 'ws-ctx-emoji-item' + ((item.mine || []).indexOf(emoji) !== -1 ? ' active' : ''), emoji);

                    pick.type = 'button';
                    pick.title = t('react_with', emoji);
                    pick.setAttribute('aria-label', pick.title);
                    pick.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        closeCtx();
                        item.action(emoji);
                    });
                    row.appendChild(pick);
                });

                if (item.onMore) {
                    var more = el('button', 'ws-ctx-emoji-item');

                    more.type = 'button';
                    more.appendChild(icon('bi-emoji-smile'));
                    more.title = t('add_reaction');
                    more.setAttribute('aria-label', more.title);
                    more.addEventListener('click', function (event) {
                        var box = menu.getBoundingClientRect();

                        event.preventDefault();
                        event.stopPropagation();
                        item.onMore(box.left, box.top);
                    });
                    row.appendChild(more);
                }

                body.appendChild(row);
                return;
            }

            var entry = el('button', 'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate ws-ctx-item' + (item.danger ? ' ws-ctx-danger' : '') + (item.checked ? ' ws-ctx-checked' : ''));
            entry.type = 'button';
            entry.setAttribute('role', 'menuitem');
            entry.appendChild(icon(item.checked ? 'bi-check2' : (item.icon || 'bi-dot'), 'me-3'));
            entry.appendChild(document.createTextNode(item.label));
            entry.disabled = !!item.disabled;
            entry.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeCtx();
                item.action();
            });
            body.appendChild(entry);
        });

        menu.appendChild(body);
        menu.style.left = '0px';
        menu.style.top = '0px';
        document.body.appendChild(menu);

        var width = menu.offsetWidth;
        var height = menu.offsetHeight;

        if (above) {
            y -= height;
        }

        menu.style.left = Math.max(8, Math.min(x, window.innerWidth - width - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(y, window.innerHeight - height - 8)) + 'px';

        ctxNode = menu;
        ctxOpenedAt = Date.now();

        var first = menu.querySelector('.ws-ctx-item:not([disabled])');

        if (first) {
            first.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        if (ctxNode && !ctxNode.contains(event.target) && (Date.now() - ctxOpenedAt > 350)) {
            closeCtx();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!ctxNode) {
            return;
        }

        if (event.key === 'Escape') {
            closeCtx();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            var entries = Array.prototype.slice.call(ctxNode.querySelectorAll('.ws-ctx-item:not([disabled])'));
            var index = entries.indexOf(document.activeElement);

            if (!entries.length) {
                return;
            }

            event.preventDefault();
            index = (index + (event.key === 'ArrowDown' ? 1 : -1) + entries.length) % entries.length;
            entries[index].focus();
        }
    });

    window.addEventListener('resize', closeCtx);
    document.addEventListener('scroll', function (event) {
        if (ctxNode && !ctxNode.contains(event.target) && (Date.now() - ctxOpenedAt > 350)) {
            closeCtx();
        }
    }, true);

    // ── Emoji ──────────────────────────────────────────────────────────

    var EMOJI = {
        smileys: '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 😉 😊 😇 🥰 😍 🤩 😘 😋 😛 😜 🤪 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 😌 😔 😪 😴 😷 🤒 🤕 🤢 🥵 🥶 😵 🤯 🥳 😎 🤓 🧐 😕 😟 🙁 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠 🤬 😈 💀 💩 🤡 👻 👽 🤖',
        people: '👍 👎 👌 🤌 🤏 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 👏 🙌 👐 🤲 🤝 🙏 ✍️ 💪 🧠 👀 👁️ 👂 👃 👶 🧒 👦 👧 🧑 👨 👩 🧓 👴 👵 🙋 🙅 🙆 🤷 🤦 🙇 💁 🧑‍💻 🧑‍🔧 🧑‍🍳 🧑‍🏫 🕵️ 🦸 🧙',
        nature: '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🐔 🐧 🐦 🐤 🦆 🦅 🦉 🐺 🐴 🦄 🐝 🐛 🦋 🐌 🐞 🐢 🐍 🐙 🐬 🐳 🐟 🌵 🎄 🌲 🌳 🌴 🌱 🌿 🍀 🍁 🍂 🌷 🌹 🌻 🌼 🌸 💐 🌞 🌝 🌙 ⭐ 🌟 ✨ ⚡ 🔥 🌈 ☀️ ⛅ ☁️ 🌧️ ⛈️ ❄️ ☃️ 🌊 💧',
        food: '🍏 🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🍒 🍑 🥭 🍍 🥥 🥝 🍅 🥑 🥦 🥕 🌽 🌶️ 🥔 🥐 🍞 🧀 🥚 🍳 🥞 🥓 🍔 🍟 🍕 🌭 🥪 🌮 🥙 🍝 🍜 🍲 🍣 🍱 🍩 🍪 🎂 🍰 🧁 🍫 🍬 🍭 🍯 ☕ 🍵 🧃 🥤 🍺 🍻 🥂 🍷',
        activity: '🎉 🎊 🎈 🎁 🏆 🥇 🥈 🥉 🏅 🎖️ ⚽ 🏀 🏈 ⚾ 🎾 🏐 🏉 🎱 🏓 🏸 ⛳ 🏹 🎣 🥊 🛹 ⛸️ 🎿 🎫 🎟️ 🎪 🎭 🎨 🎬 🎤 🎧 🎼 🎹 🥁 🎷 🎺 🎸 🎻 🎲 🎯 🎳 🎮 🧩',
        travel: '🚗 🚕 🚙 🚌 🚎 🏎️ 🚓 🚑 🚒 🚐 🚚 🚛 🚜 🛵 🚲 🛴 🚨 🚦 🚧 ⚓ ⛽ 🚢 ⛵ ✈️ 🛫 🛬 🚀 🛸 🚁 🚂 🚆 🚇 🚊 🗺️ 🗽 🏰 🏯 🏟️ 🎡 🏖️ 🏝️ ⛰️ 🏔️ 🏕️ 🏠 🏡 🏢 🏬 🏭 🏥 🏦 🏨 🏪 🏫 ⛪ 🕌',
        objects: '⌚ 📱 💻 ⌨️ 🖥️ 🖨️ 🖱️ 💾 💿 📷 📸 📹 🎥 📞 ☎️ 📺 📻 ⏰ ⏳ ⌛ 🔋 🔌 💡 🔦 🕯️ 💸 💵 💶 💰 💳 💎 ⚖️ 🔧 🔨 🛠️ ⚙️ 🔩 🧰 🧲 🔑 🗝️ 🔒 🔓 📦 📫 📮 📝 📄 📃 📑 📊 📈 📉 🗂️ 📅 📆 📌 📍 📎 🖇️ 📏 📐 ✂️ 🗃️ 🗄️ 🗑️ 📚 📖 🔖 🏷️ ✏️ 🖊️ 🖋️ 🔍 🔎',
        symbols: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 ✅ ☑️ ✔️ ❌ ❎ ➕ ➖ ➗ ✖️ ❓ ❔ ❗ ❕ 💯 🔴 🟠 🟡 🟢 🔵 🟣 ⚫ ⚪ 🟥 🟧 🟨 🟩 🟦 🟪 ⬛ ⬜ 🔶 🔷 ▶️ ⏸️ ⏹️ ⏺️ ⏭️ 🔁 🔄 ⬆️ ⬇️ ⬅️ ➡️ ↩️ ↪️ 🆕 🆗 🆙 🆒 🆓 🚫 ⛔ ⚠️ 🔔 🔕 📣 💬 💭 🗯️ ♻️ 🏁 🚩'
    };

    // Words to find an emoji by, in English and Turkish.
    var EMOJI_WORDS = {
        '😀': 'grin smile happy sırıt gülümse mutlu',
        '😃': 'smile happy open gülümse mutlu',
        '😄': 'smile laugh happy gülümse gül mutlu',
        '😁': 'beam grin teeth sırıt gülümse',
        '😆': 'laugh squint haha gül kahkaha',
        '😅': 'sweat laugh relief terle gül rahatla',
        '🤣': 'rofl rolling laugh yerlere gül kahkaha',
        '😂': 'joy tears laugh gözyaşı gül kahkaha',
        '🙂': 'slight smile gülümse hafif',
        '😉': 'wink göz kırp',
        '😊': 'blush smile happy utangaç gülümse mutlu',
        '😇': 'angel halo innocent melek masum',
        '🥰': 'love hearts adore aşk kalp sevgi',
        '😍': 'heart eyes love aşık kalp göz',
        '🤩': 'star struck wow yıldız hayran vay',
        '😘': 'kiss heart öpücük öp',
        '😋': 'yum tasty delicious lezzetli nefis',
        '😛': 'tongue dil',
        '😜': 'wink tongue silly dil şaka',
        '🤪': 'zany crazy silly çılgın deli',
        '🤗': 'hug sarıl kucakla',
        '🤭': 'giggle oops kıkırda',
        '🤫': 'shush quiet secret sus sessiz sır',
        '🤔': 'think hmm düşün acaba',
        '🤐': 'zipper mouth secret ağzı fermuarlı sır',
        '🤨': 'raised eyebrow doubt şüphe kaş',
        '😐': 'neutral expressionless nötr ifadesiz',
        '😑': 'expressionless ifadesiz',
        '😶': 'no mouth speechless dilsiz sessiz',
        '😏': 'smirk sırıt',
        '😒': 'unamused bored sıkıl hoşnutsuz',
        '🙄': 'eye roll göz devir',
        '😬': 'grimace awkward yüz buruş garip',
        '😌': 'relieved calm rahatla sakin',
        '😔': 'pensive sad üzgün düşünceli',
        '😪': 'sleepy uykulu',
        '😴': 'sleep tired zzz uyu yorgun',
        '😷': 'mask sick maske hasta',
        '🤒': 'fever sick thermometer ateş hasta',
        '🤕': 'hurt injured bandage yaralı',
        '🤢': 'nauseated sick mide bulan hasta',
        '🥵': 'hot heat terle sıcak',
        '🥶': 'cold freeze soğuk don',
        '😵': 'dizzy baş dön sersem',
        '🤯': 'mind blown exploding beyin yak şok',
        '🥳': 'party celebrate parti kutla',
        '😎': 'cool sunglasses havalı gözlük',
        '🤓': 'nerd glasses inek gözlük',
        '🧐': 'monocle inspect incele',
        '😕': 'confused kafası karış',
        '😟': 'worried endişe',
        '🙁': 'frown sad üzgün',
        '😮': 'open mouth surprised şaşır',
        '😯': 'hushed surprised şaşkın',
        '😲': 'astonished shock şok şaşkın',
        '😳': 'flushed embarrassed utan kızar',
        '🥺': 'pleading puppy eyes rica yalvar',
        '😦': 'frowning open mouth üzgün',
        '😧': 'anguished acı',
        '😨': 'fearful scared kork',
        '😰': 'anxious sweat endişe kaygı',
        '😥': 'sad relieved disappointed üzgün',
        '😢': 'cry tear ağla gözyaşı',
        '😭': 'sob crying loud hüngür ağla',
        '😱': 'scream fear çığlık kork',
        '😖': 'confounded şaşkın',
        '😣': 'persevere sabret',
        '😞': 'disappointed hayal kırıklığı',
        '😓': 'downcast sweat bitkin',
        '😩': 'weary yorgun bitkin',
        '😫': 'tired yorgun',
        '🥱': 'yawn esne',
        '😤': 'triumph huff öfke burnundan',
        '😡': 'angry mad rage kızgın öfke',
        '😠': 'angry mad kızgın',
        '🤬': 'cursing swear küfür öfke',
        '😈': 'devil evil şeytan',
        '💀': 'skull dead kafatası ölü',
        '💩': 'poop kaka',
        '🤡': 'clown palyaço',
        '👻': 'ghost hayalet',
        '👽': 'alien uzaylı',
        '🤖': 'robot bot robot',
        '👍': 'thumbs up like ok yes approve beğen onay evet tamam',
        '👎': 'thumbs down dislike no beğenme hayır',
        '👌': 'ok perfect tamam mükemmel',
        '🤌': 'pinched fingers italian parmak',
        '🤏': 'pinch small az küçük',
        '✌️': 'victory peace zafer barış',
        '🤞': 'fingers crossed luck şans dilek',
        '🤟': 'love you seni seviyorum',
        '🤘': 'rock horns metal rock',
        '🤙': 'call me ara telefon',
        '👈': 'left sol',
        '👉': 'right sağ',
        '👆': 'up yukarı',
        '👇': 'down aşağı',
        '☝️': 'point up işaret yukarı',
        '✋': 'raised hand stop high five el dur çak',
        '🤚': 'raised back hand el',
        '🖐️': 'hand palm el avuç',
        '🖖': 'vulcan spock',
        '👋': 'wave hello bye hi selam merhaba hoşça kal',
        '👏': 'clap applause alkış tebrik',
        '🙌': 'raise hands celebrate yaşasın kutla',
        '👐': 'open hands açık el',
        '🤲': 'palms up avuç',
        '🤝': 'handshake deal agree el sıkış anlaşma',
        '🙏': 'pray thanks please dua teşekkür lütfen rica',
        '✍️': 'write yaz',
        '💪': 'muscle strong güçlü kas',
        '🧠': 'brain smart beyin akıllı',
        '👀': 'eyes look see bak gör göz gördüm',
        '👁️': 'eye göz',
        '👂': 'ear listen kulak dinle',
        '👃': 'nose burun',
        '👶': 'baby bebek',
        '🧒': 'child çocuk',
        '👦': 'boy oğlan erkek çocuk',
        '👧': 'girl kız',
        '🧑': 'person kişi insan',
        '👨': 'man adam erkek',
        '👩': 'woman kadın',
        '🧓': 'older person yaşlı',
        '👴': 'old man yaşlı adam dede',
        '👵': 'old woman yaşlı kadın nine',
        '🙋': 'raise hand question el kaldır soru',
        '🙅': 'no gesture hayır',
        '🙆': 'ok gesture tamam',
        '🤷': 'shrug dunno bilmiyorum omuz',
        '🤦': 'facepalm eyvah',
        '🙇': 'bow özür eğil',
        '💁': 'info desk tipping bilgi',
        '🧑‍💻': 'developer coder programmer yazılımcı geliştirici bilgisayar',
        '🧑‍🔧': 'mechanic technician tamirci teknisyen',
        '🧑‍🍳': 'cook chef aşçı şef',
        '🧑‍🏫': 'teacher öğretmen',
        '🕵️': 'detective spy dedektif',
        '🦸': 'superhero kahraman',
        '🧙': 'wizard magic büyücü',
        '🐶': 'dog köpek',
        '🐱': 'cat kedi',
        '🐭': 'mouse fare',
        '🐹': 'hamster hamster',
        '🐰': 'rabbit bunny tavşan',
        '🦊': 'fox tilki',
        '🐻': 'bear ayı',
        '🐼': 'panda panda',
        '🐨': 'koala koala',
        '🐯': 'tiger kaplan',
        '🦁': 'lion aslan',
        '🐮': 'cow inek',
        '🐷': 'pig domuz',
        '🐸': 'frog kurbağa',
        '🐵': 'monkey maymun',
        '🐔': 'chicken tavuk',
        '🐧': 'penguin penguen',
        '🐦': 'bird kuş',
        '🐤': 'chick civciv',
        '🦆': 'duck ördek',
        '🦅': 'eagle kartal',
        '🦉': 'owl baykuş',
        '🐺': 'wolf kurt',
        '🐴': 'horse at',
        '🦄': 'unicorn tek boynuzlu',
        '🐝': 'bee arı',
        '🐛': 'bug caterpillar tırtıl böcek',
        '🦋': 'butterfly kelebek',
        '🐌': 'snail salyangoz',
        '🐞': 'ladybug uğur böceği',
        '🐢': 'turtle kaplumbağa',
        '🐍': 'snake yılan',
        '🐙': 'octopus ahtapot',
        '🐬': 'dolphin yunus',
        '🐳': 'whale balina',
        '🐟': 'fish balık',
        '🌵': 'cactus kaktüs',
        '🎄': 'christmas tree yılbaşı ağacı',
        '🌲': 'evergreen tree çam ağaç',
        '🌳': 'tree ağaç',
        '🌴': 'palm tree palmiye',
        '🌱': 'seedling sprout filiz fide',
        '🌿': 'herb leaf ot yaprak',
        '🍀': 'clover luck yonca şans',
        '🍁': 'maple leaf autumn akçaağaç yaprak sonbahar',
        '🍂': 'fallen leaves autumn yaprak sonbahar',
        '🌷': 'tulip lale çiçek',
        '🌹': 'rose gül çiçek',
        '🌻': 'sunflower ayçiçeği',
        '🌼': 'blossom flower çiçek',
        '🌸': 'cherry blossom kiraz çiçeği',
        '💐': 'bouquet flowers buket çiçek',
        '🌞': 'sun face güneş',
        '🌝': 'full moon dolunay ay',
        '🌙': 'moon night ay gece',
        '⭐': 'star yıldız',
        '🌟': 'glowing star parlayan yıldız',
        '✨': 'sparkles shine new parıltı ışıltı yeni',
        '⚡': 'lightning fast şimşek yıldırım hızlı',
        '🔥': 'fire hot lit ateş yangın alev',
        '🌈': 'rainbow gökkuşağı',
        '☀️': 'sun sunny güneş güneşli',
        '⛅': 'cloudy sun parçalı bulutlu',
        '☁️': 'cloud bulut',
        '🌧️': 'rain yağmur',
        '⛈️': 'storm thunder fırtına gök gürültü',
        '❄️': 'snow snowflake kar tanesi',
        '☃️': 'snowman kardan adam',
        '🌊': 'wave sea ocean dalga deniz',
        '💧': 'drop water damla su',
        '🍏': 'green apple yeşil elma',
        '🍎': 'apple red elma',
        '🍐': 'pear armut',
        '🍊': 'orange tangerine portakal mandalina',
        '🍋': 'lemon limon',
        '🍌': 'banana muz',
        '🍉': 'watermelon karpuz',
        '🍇': 'grapes üzüm',
        '🍓': 'strawberry çilek',
        '🍒': 'cherry kiraz',
        '🍑': 'peach şeftali',
        '🥭': 'mango mango',
        '🍍': 'pineapple ananas',
        '🥥': 'coconut hindistan cevizi',
        '🥝': 'kiwi kivi',
        '🍅': 'tomato domates',
        '🥑': 'avocado avokado',
        '🥦': 'broccoli brokoli',
        '🥕': 'carrot havuç',
        '🌽': 'corn mısır',
        '🌶️': 'pepper hot acı biber',
        '🥔': 'potato patates',
        '🥐': 'croissant kruvasan',
        '🍞': 'bread ekmek',
        '🧀': 'cheese peynir',
        '🥚': 'egg yumurta',
        '🍳': 'cooking fried egg sahanda yumurta',
        '🥞': 'pancakes pankek krep',
        '🥓': 'bacon pastırma',
        '🍔': 'burger hamburger',
        '🍟': 'fries patates kızartması',
        '🍕': 'pizza pizza',
        '🌭': 'hot dog sosisli',
        '🥪': 'sandwich sandviç',
        '🌮': 'taco tako',
        '🥙': 'pita kebab dürüm',
        '🍝': 'spaghetti pasta makarna',
        '🍜': 'ramen noodle erişte çorba',
        '🍲': 'stew pot yahni tencere',
        '🍣': 'sushi suşi',
        '🍱': 'bento bento',
        '🍩': 'doughnut donut halka tatlı',
        '🍪': 'cookie kurabiye',
        '🎂': 'birthday cake doğum günü pasta',
        '🍰': 'cake pasta kek',
        '🧁': 'cupcake kek',
        '🍫': 'chocolate çikolata',
        '🍬': 'candy şeker',
        '🍭': 'lollipop lolipop',
        '🍯': 'honey bal',
        '☕': 'coffee kahve',
        '🍵': 'tea çay',
        '🧃': 'juice meyve suyu',
        '🥤': 'soft drink içecek',
        '🍺': 'beer bira',
        '🍻': 'beers cheers şerefe bira',
        '🥂': 'clink champagne cheers şerefe kadeh',
        '🍷': 'wine şarap',
        '🎉': 'party tada celebrate kutla parti tebrik',
        '🎊': 'confetti konfeti kutla',
        '🎈': 'balloon balon',
        '🎁': 'gift present hediye',
        '🏆': 'trophy win champion kupa kazan şampiyon',
        '🥇': 'gold medal first altın madalya birinci',
        '🥈': 'silver medal second gümüş madalya ikinci',
        '🥉': 'bronze medal third bronz madalya üçüncü',
        '🏅': 'medal madalya',
        '🎖️': 'military medal nişan madalya',
        '⚽': 'soccer football futbol top',
        '🏀': 'basketball basketbol',
        '🏈': 'american football amerikan futbolu',
        '⚾': 'baseball beyzbol',
        '🎾': 'tennis tenis',
        '🏐': 'volleyball voleybol',
        '🏉': 'rugby ragbi',
        '🎱': 'billiards 8 ball bilardo',
        '🏓': 'ping pong masa tenisi',
        '🏸': 'badminton badminton',
        '⛳': 'golf golf',
        '🏹': 'archery bow okçuluk yay',
        '🎣': 'fishing balık tut',
        '🥊': 'boxing boks',
        '🛹': 'skateboard kaykay',
        '⛸️': 'ice skate paten',
        '🎿': 'ski kayak',
        '🎫': 'ticket bilet',
        '🎟️': 'admission tickets bilet',
        '🎪': 'circus tent sirk',
        '🎭': 'theater mask tiyatro',
        '🎨': 'art palette sanat resim',
        '🎬': 'film clapper sinema film',
        '🎤': 'microphone mikrofon şarkı',
        '🎧': 'headphones kulaklık müzik',
        '🎼': 'music score nota müzik',
        '🎹': 'piano piyano',
        '🥁': 'drum davul',
        '🎷': 'saxophone saksafon',
        '🎺': 'trumpet trompet',
        '🎸': 'guitar gitar',
        '🎻': 'violin keman',
        '🎲': 'dice game zar oyun',
        '🎯': 'target goal dart hedef',
        '🎳': 'bowling bovling',
        '🎮': 'game controller oyun',
        '🧩': 'puzzle jigsaw yapboz bulmaca',
        '🚗': 'car araba',
        '🚕': 'taxi taksi',
        '🚙': 'suv jeep araç',
        '🚌': 'bus otobüs',
        '🚎': 'trolleybus troleybüs',
        '🏎️': 'racing car yarış arabası',
        '🚓': 'police car polis',
        '🚑': 'ambulance ambulans',
        '🚒': 'fire engine itfaiye',
        '🚐': 'minibus minibüs',
        '🚚': 'truck delivery kamyon teslimat kargo',
        '🚛': 'lorry tır',
        '🚜': 'tractor traktör',
        '🛵': 'scooter motorsiklet',
        '🚲': 'bicycle bike bisiklet',
        '🛴': 'kick scooter scooter',
        '🚨': 'siren alarm siren alarm',
        '🚦': 'traffic light trafik ışığı',
        '🚧': 'construction under way yapım çalışma',
        '⚓': 'anchor çapa',
        '⛽': 'fuel gas benzin yakıt',
        '🚢': 'ship gemi',
        '⛵': 'sailboat yelkenli',
        '✈️': 'airplane flight uçak uçuş',
        '🛫': 'departure kalkış',
        '🛬': 'arrival iniş',
        '🚀': 'rocket launch ship roket fırlat',
        '🛸': 'ufo ufo',
        '🚁': 'helicopter helikopter',
        '🚂': 'train locomotive tren',
        '🚆': 'train tren',
        '🚇': 'metro subway metro',
        '🚊': 'tram tramvay',
        '🗺️': 'map world harita',
        '🗽': 'statue of liberty özgürlük heykeli',
        '🏰': 'castle kale şato',
        '🏯': 'japanese castle kale',
        '🏟️': 'stadium stadyum',
        '🎡': 'ferris wheel dönme dolap',
        '🏖️': 'beach umbrella plaj',
        '🏝️': 'island ada',
        '⛰️': 'mountain dağ',
        '🏔️': 'snow mountain karlı dağ',
        '🏕️': 'camping kamp',
        '🏠': 'house home ev',
        '🏡': 'house garden bahçeli ev',
        '🏢': 'office building ofis bina',
        '🏬': 'department store mağaza',
        '🏭': 'factory fabrika',
        '🏥': 'hospital hastane',
        '🏦': 'bank banka',
        '🏨': 'hotel otel',
        '🏪': 'store shop dükkan market',
        '🏫': 'school okul',
        '⛪': 'church kilise',
        '🕌': 'mosque cami',
        '⌚': 'watch time saat',
        '📱': 'phone mobile telefon cep',
        '💻': 'laptop computer dizüstü bilgisayar',
        '⌨️': 'keyboard klavye',
        '🖥️': 'desktop computer masaüstü bilgisayar',
        '🖨️': 'printer yazıcı',
        '🖱️': 'mouse fare',
        '💾': 'floppy save disket kaydet',
        '💿': 'cd disk disk',
        '📷': 'camera kamera fotoğraf',
        '📸': 'camera flash photo fotoğraf',
        '📹': 'video camera video kamera',
        '🎥': 'movie camera film kamera',
        '📞': 'phone receiver telefon ahize ara',
        '☎️': 'telephone telefon',
        '📺': 'tv television televizyon',
        '📻': 'radio radyo',
        '⏰': 'alarm clock çalar saat alarm',
        '⏳': 'hourglass time waiting kum saati bekle',
        '⌛': 'hourglass done kum saati',
        '🔋': 'battery pil batarya',
        '🔌': 'plug electric fiş priz',
        '💡': 'idea bulb light fikir ampul',
        '🔦': 'flashlight el feneri',
        '🕯️': 'candle mum',
        '💸': 'money wings spend para harca',
        '💵': 'dollar money para dolar',
        '💶': 'euro money para avro',
        '💰': 'money bag para kesesi',
        '💳': 'credit card kart kredi ödeme',
        '💎': 'gem diamond elmas mücevher',
        '⚖️': 'scales balance law terazi adalet',
        '🔧': 'wrench tool anahtar alet',
        '🔨': 'hammer çekiç',
        '🛠️': 'tools araç gereç alet',
        '⚙️': 'gear settings çark ayar dişli',
        '🔩': 'nut bolt somun cıvata',
        '🧰': 'toolbox alet çantası',
        '🧲': 'magnet mıknatıs',
        '🔑': 'key anahtar',
        '🗝️': 'old key eski anahtar',
        '🔒': 'lock locked kilit kilitli',
        '🔓': 'unlock açık kilit',
        '📦': 'package box parcel paket kutu kargo',
        '📫': 'mailbox posta kutusu',
        '📮': 'postbox posta',
        '📝': 'memo note write not yaz',
        '📄': 'page document sayfa belge',
        '📃': 'page curl sayfa belge',
        '📑': 'bookmark tabs sekme',
        '📊': 'chart bar graph grafik çizelge',
        '📈': 'chart up increase grafik artış yükseliş',
        '📉': 'chart down decrease grafik düşüş',
        '🗂️': 'dividers folders ayraç klasör',
        '📅': 'calendar date takvim tarih',
        '📆': 'calendar spiral takvim',
        '📌': 'pushpin pin raptiye iğne',
        '📍': 'location pin konum',
        '📎': 'paperclip ataş',
        '🖇️': 'linked paperclips ataş',
        '📏': 'ruler cetvel',
        '📐': 'triangle ruler gönye',
        '✂️': 'scissors cut makas kes',
        '🗃️': 'card box kart kutusu',
        '🗄️': 'file cabinet dosya dolabı',
        '🗑️': 'trash wastebasket çöp kutusu',
        '📚': 'books library kitap',
        '📖': 'book open kitap',
        '🔖': 'bookmark ayraç',
        '🏷️': 'label tag etiket',
        '✏️': 'pencil kalem',
        '🖊️': 'pen tükenmez kalem',
        '🖋️': 'fountain pen dolma kalem',
        '🔍': 'search magnifier ara büyüteç',
        '🔎': 'search magnifier ara büyüteç',
        '❤️': 'red heart love kalp aşk sevgi kırmızı',
        '🧡': 'orange heart turuncu kalp',
        '💛': 'yellow heart sarı kalp',
        '💚': 'green heart yeşil kalp',
        '💙': 'blue heart mavi kalp',
        '💜': 'purple heart mor kalp',
        '🖤': 'black heart siyah kalp',
        '🤍': 'white heart beyaz kalp',
        '🤎': 'brown heart kahverengi kalp',
        '💔': 'broken heart kırık kalp',
        '❣️': 'heart exclamation kalp ünlem',
        '💕': 'two hearts iki kalp',
        '💞': 'revolving hearts dönen kalpler',
        '💓': 'beating heart atan kalp',
        '💗': 'growing heart büyüyen kalp',
        '💖': 'sparkling heart parlayan kalp',
        '💘': 'cupid arrow heart kalp ok',
        '💝': 'gift heart hediye kalp',
        '✅': 'check done yes tick onay tamam bitti',
        '☑️': 'ballot check onay kutusu',
        '✔️': 'check mark tick onay işaret',
        '❌': 'cross no wrong çarpı hayır yanlış',
        '❎': 'cross mark çarpı',
        '➕': 'plus add artı ekle',
        '➖': 'minus eksi çıkar',
        '➗': 'divide böl',
        '✖️': 'multiply times çarpı',
        '❓': 'question soru',
        '❔': 'white question soru',
        '❗': 'exclamation important ünlem önemli',
        '❕': 'white exclamation ünlem',
        '💯': 'hundred 100 perfect yüz mükemmel',
        '🔴': 'red circle kırmızı daire',
        '🟠': 'orange circle turuncu daire',
        '🟡': 'yellow circle sarı daire',
        '🟢': 'green circle yeşil daire',
        '🔵': 'blue circle mavi daire',
        '🟣': 'purple circle mor daire',
        '⚫': 'black circle siyah daire',
        '⚪': 'white circle beyaz daire',
        '🟥': 'red square kırmızı kare',
        '🟧': 'orange square turuncu kare',
        '🟨': 'yellow square sarı kare',
        '🟩': 'green square yeşil kare',
        '🟦': 'blue square mavi kare',
        '🟪': 'purple square mor kare',
        '⬛': 'black square siyah kare',
        '⬜': 'white square beyaz kare',
        '🔶': 'orange diamond turuncu elmas',
        '🔷': 'blue diamond mavi elmas',
        '▶️': 'play oynat başlat',
        '⏸️': 'pause duraklat',
        '⏹️': 'stop durdur',
        '⏺️': 'record kayıt',
        '⏭️': 'next skip sonraki atla',
        '🔁': 'repeat tekrar yinele',
        '🔄': 'refresh arrows yenile döngü',
        '⬆️': 'up arrow yukarı ok',
        '⬇️': 'down arrow aşağı ok',
        '⬅️': 'left arrow sol ok',
        '➡️': 'right arrow sağ ok',
        '↩️': 'return back geri dön',
        '↪️': 'forward ileri',
        '🆕': 'new yeni',
        '🆗': 'ok tamam',
        '🆙': 'up yukarı',
        '🆒': 'cool havalı',
        '🆓': 'free ücretsiz',
        '🚫': 'no forbidden yasak',
        '⛔': 'no entry giriş yok',
        '⚠️': 'warning caution uyarı dikkat',
        '🔔': 'bell notification zil bildirim',
        '🔕': 'bell off mute sessiz zil',
        '📣': 'megaphone announce duyuru',
        '💬': 'speech bubble comment chat konuşma yorum',
        '💭': 'thought bubble düşünce',
        '🗯️': 'anger bubble öfke',
        '♻️': 'recycle geri dönüşüm',
        '🏁': 'finish flag bitiş bayrak',
        '🚩': 'flag red triangle bayrak'
    };

    var EMOJI_TABS = [
        ['recent', 'bi-clock-history'],
        ['smileys', '😀'],
        ['people', '👍'],
        ['nature', '🌿'],
        ['food', '☕'],
        ['activity', '🎉'],
        ['travel', '🚗'],
        ['objects', '💡'],
        ['symbols', '❤️']
    ];

    var EMOJI_RECENT_KEY = 'ws-emoji-recent';

    // Lower case, with the Turkish letters as their plain Latin ones, so
    // "gulen" finds "gülen" and "I" finds "ı".
    function foldText(value) {
        return String(value || '').toLocaleLowerCase('tr')
            .replace(/ı/g, 'i').replace(/\u0307/g, '').replace(/ş/g, 's').replace(/ğ/g, 'g')
            .replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c')
            .replace(/â/g, 'a').replace(/î/g, 'i').replace(/û/g, 'u');
    }

    var emojiIndex = null;

    // The emoji with a word starting with every word typed, in the order of
    // the groups. The group's own name counts as a word too.
    function findEmoji(query) {
        var words = foldText(query).split(/\s+/).filter(Boolean);

        if (!words.length) {
            return [];
        }

        if (!emojiIndex) {
            emojiIndex = [];

            EMOJI_TABS.forEach(function (entry) {
                if (!EMOJI[entry[0]]) {
                    return;
                }

                EMOJI[entry[0]].split(' ').forEach(function (emoji) {
                    emojiIndex.push([emoji, ' ' + foldText((EMOJI_WORDS[emoji] || '') + ' ' + t('emoji_' + entry[0])).replace(/\s+/g, ' ')]);
                });
            });
        }

        return emojiIndex.filter(function (item) {
            return words.every(function (word) {
                return item[1].indexOf(' ' + word) !== -1;
            });
        }).map(function (item) {
            return item[0];
        });
    }

    function recentEmoji() {
        try {
            var list = JSON.parse(window.localStorage.getItem(EMOJI_RECENT_KEY) || '[]');

            return Array.isArray(list) ? list.filter(function (item) { return typeof item === 'string'; }).slice(0, 24) : [];
        } catch (error) {
            return [];
        }
    }

    function rememberEmoji(emoji) {
        try {
            var list = recentEmoji().filter(function (item) { return item !== emoji; });

            list.unshift(emoji);
            window.localStorage.setItem(EMOJI_RECENT_KEY, JSON.stringify(list.slice(0, 24)));
        } catch (error) {
            // A private window keeps no list; the picker works the same.
        }
    }

    // The ":" emoji list at the caret, from what is written before it
    // (chips as U+FFFC): ":" and at least two letters at a line's start,
    // after a space or a chip. Never after a letter or digit, so "::", a
    // time ("10:30") and a URL ("https://") do not open it. Answers null or
    // { start, word }.
    function colonEmojiAt(before) {
        var match = /(^|[\s￼]):([A-Za-zÇĞİÖŞÜÂÎÛçğıöşüâîû]{2,20})$/.exec(before);

        return match ? { start: before.length - match[2].length - 1, word: match[2] } : null;
    }

    // The matches (findEmoji()) with the recent ones first, in the recent
    // order, then the rest in the picker's; at most limit.
    function colonEmojiList(found, recent, limit) {
        var first = recent.filter(function (emoji) { return found.indexOf(emoji) !== -1; });

        return first.concat(found.filter(function (emoji) { return first.indexOf(emoji) === -1; })).slice(0, limit);
    }

    // ── "Rewrite with AI" in the composer ──
    // The draft keeps the tags of the person's text (<@user:12>); one the
    // model made up names nobody the person picked, so it is dropped.
    function aiRewriteTokens(text, known) {
        return String(text || '').replace(/<[@#][a-z_]+:[0-9]{1,10}>/g, function (token) {
            return (known.indexOf(token) !== -1) ? token : '';
        });
    }

    var aiRewriteSeq = 0;

    // ── How near the composer's text is to the server's limit ──
    // WS_MESSAGE_MAX (CFG.message_max) counts the posted body after trim()
    // in characters as mb_strlen() does: code points, so a surrogate pair is
    // one. The count shows from METER_FROM of the limit.
    var METER_FROM = 0.9;

    // { count, max, state: none | near | over, block } for the text as it is
    // posted. block: past the limit and no command reads the text first
    // (commands is false where none runs, as when a message is changed; a
    // single leading / is one, "//" posts the text).
    function messageMeter(text, max, commands) {
        var body = String(text || '').replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
        var count = body.replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, '.').length;
        var limit = parseInt(max, 10) || 0;
        var state = 'none';

        if (limit > 0) {
            state = (count > limit) ? 'over' : ((count >= Math.ceil(limit * METER_FROM)) ? 'near' : 'none');
        }

        return {
            count: count,
            max: limit,
            state: state,
            block: (state === 'over') && !(commands && /^\/(?!\/)/.test(body))
        };
    }

    // ── The composer's unsent text, kept on this browser ──
    // One slot per site, viewer, channel and discussion, so two people on one
    // browser or two sites on one host never read each other's text. Only
    // the stored markup and its chips' labels are kept, never files.
    var DRAFT_PREFIX = 'ws-draft:';
    var DRAFT_DAYS = 14;
    var DRAFT_KEEP = 50;

    function draftKey(site, viewer, channelId, threadId) {
        var thread = parseInt(threadId, 10) || 0;

        return DRAFT_PREFIX + String(site || '') + '|' + (parseInt(viewer, 10) || 0)
            + ':c' + (parseInt(channelId, 10) || 0) + (thread ? ':t' + thread : '');
    }

    // A kept value as { raw, labels, at } while it is younger than DRAFT_DAYS
    // and holds words; null otherwise.
    function draftFresh(stored, now) {
        var value = null;

        try {
            value = JSON.parse(String(stored || ''));
        } catch (error) {
            return null;
        }

        if (!value || (typeof value.raw !== 'string') || (value.raw.trim() === '') || (typeof value.at !== 'number')) {
            return null;
        }

        if (!(value.at > now - DRAFT_DAYS * 86400000)) {
            return null;
        }

        var labels = (value.labels && (typeof value.labels === 'object') && !Array.isArray(value.labels)) ? value.labels : {};

        return { raw: value.raw, labels: labels, at: value.at };
    }

    // The slots to remove from [{ key, at }]: every expired one, then the
    // oldest beyond DRAFT_KEEP.
    function draftPrune(entries, now) {
        var limit = now - DRAFT_DAYS * 86400000;
        var live = [];
        var drop = [];

        entries.forEach(function (entry) {
            if (entry.at > limit) {
                live.push(entry);
            } else {
                drop.push(entry.key);
            }
        });

        live.sort(function (a, b) { return b.at - a.at; });
        live.slice(DRAFT_KEEP).forEach(function (entry) { drop.push(entry.key); });

        return drop;
    }

    function draftRead(key) {
        try {
            var value = draftFresh(window.localStorage.getItem(key), Date.now());

            if (!value) {
                window.localStorage.removeItem(key);
            }

            return value;
        } catch (error) {
            return null;
        }
    }

    // Keeps the text, or drops the slot when it is empty; the others are
    // pruned first so a full storage still takes the newest.
    function draftWrite(key, raw, labels) {
        try {
            var store = window.localStorage;

            if (String(raw || '').trim() === '') {
                store.removeItem(key);
                return;
            }

            var now = Date.now();
            var entries = [{ key: key, at: now }];

            for (var i = 0; i < store.length; i++) {
                var name = store.key(i);

                if (name && (name !== key) && (name.indexOf(DRAFT_PREFIX) === 0)) {
                    var kept = draftFresh(store.getItem(name), now);

                    entries.push({ key: name, at: kept ? kept.at : 0 });
                }
            }

            draftPrune(entries, now).forEach(function (name) { store.removeItem(name); });
            store.setItem(key, JSON.stringify({ raw: raw, labels: labels || {}, at: now }));
        } catch (error) {
            // Blocked storage or a full quota: the box works the same,
            // nothing is kept.
        }
    }

    // A box that floats like a context menu and closes like one: a click
    // elsewhere, Escape, a scroll or a resize. "above" places it over the
    // point instead of under it (for the composer, at the bottom).
    function floatBox(x, y, content, className, above) {
        closeCtx();

        var box = el('div', 'ws-ctx contextmenu popover shadow-lg backdrop ws-float' + (className ? ' ' + className : ''));

        box.appendChild(content);
        box.style.left = '0px';
        box.style.top = '0px';
        document.body.appendChild(box);

        var width = box.offsetWidth;
        var height = box.offsetHeight;

        if (above) {
            y -= height;
        }

        box.style.left = Math.max(8, Math.min(x, window.innerWidth - width - 8)) + 'px';
        box.style.top = Math.max(8, Math.min(y, window.innerHeight - height - 8)) + 'px';

        ctxNode = box;
        ctxOpenedAt = Date.now();

        return box;
    }

    // The emoji picker: what was used lately and the quick set first, every
    // group one tab away.
    function emojiPicker(x, y, onPick, above) {
        var content = el('div', 'ws-emoji');
        var search = el('input', 'form-control form-control-sm ws-emoji-search');
        var tabs = el('div', 'ws-emoji-tabs');
        var title = el('div', 'ws-emoji-title');
        var grid = el('div', 'ws-emoji-grid');
        var current = 'recent';
        var shown = [];

        search.type = 'search';
        search.placeholder = t('emoji_search');
        search.setAttribute('aria-label', t('emoji_search'));
        search.autocomplete = 'off';
        search.spellcheck = false;

        function pick(emoji) {
            rememberEmoji(emoji);
            closeCtx();
            onPick(emoji);
        }

        function draw(list) {
            shown = list;
            clear(grid);

            list.forEach(function (emoji) {
                var item = el('button', 'ws-emoji-item', emoji);

                item.type = 'button';
                item.addEventListener('click', function (event) {
                    event.stopPropagation();
                    pick(emoji);
                });
                grid.appendChild(item);
            });

            grid.scrollTop = 0;
        }

        function show(key) {
            current = key;
            var list = (key === 'recent') ? recentEmoji() : EMOJI[key].split(' ');

            if (key === 'recent') {
                ((BOOT && BOOT.reactions) || []).forEach(function (emoji) {
                    if (list.indexOf(emoji) === -1) {
                        list.push(emoji);
                    }
                });
            }

            Array.prototype.forEach.call(tabs.children, function (tab) {
                tab.classList.toggle('active', tab.getAttribute('data-key') === key);
            });

            title.textContent = t('emoji_' + key);
            draw(list);
        }

        // Typing searches every group; an empty box goes back to the tab.
        search.addEventListener('input', function () {
            var query = search.value.trim();

            if (!query) {
                show(current);
                return;
            }

            var found = findEmoji(query);

            Array.prototype.forEach.call(tabs.children, function (tab) {
                tab.classList.remove('active');
            });

            title.textContent = found.length ? t('emoji_found') : t('emoji_none');
            draw(found);
        });

        search.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter') && search.value.trim() && shown.length) {
                event.preventDefault();
                pick(shown[0]);
            }
        });

        EMOJI_TABS.forEach(function (entry) {
            var tab = el('button', 'ws-emoji-tab');

            tab.type = 'button';
            tab.setAttribute('data-key', entry[0]);
            tab.title = t('emoji_' + entry[0]);
            tab.setAttribute('aria-label', tab.title);

            if (entry[1].indexOf('bi-') === 0) {
                tab.appendChild(icon(entry[1]));
            } else {
                tab.textContent = entry[1];
            }

            tab.addEventListener('click', function (event) {
                event.stopPropagation();
                search.value = '';
                show(entry[0]);
            });
            tabs.appendChild(tab);
        });

        content.appendChild(search);
        content.appendChild(tabs);
        content.appendChild(title);
        content.appendChild(grid);
        show('recent');

        var box = floatBox(x, y, content, 'ws-emoji-picker', above);

        // Straight to typing with a keyboard; a phone keeps its keyboard
        // closed until the box is tapped.
        if (!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches)) {
            search.focus({ preventScroll: true });
        }

        return box;
    }

    // The way back to the channel list on a phone, where the list and the
    // channel take turns on the screen. A back arrow with its name, so it is
    // not read as a second main menu button under the panel's own.
    function channelsToggle(root) {
        var toggle = el('button', 'ws-channels-toggle md:d-none');

        toggle.type = 'button';
        toggle.title = t('channels_show');
        toggle.setAttribute('aria-label', toggle.title);
        toggle.appendChild(icon('bi-chevron-left'));
        toggle.appendChild(el('span', 'ws-channels-toggle-label', t('channels')));
        toggle.addEventListener('click', function () { root.classList.add('ws-show-side'); });

        return toggle;
    }

    // Gives a node its menu: right click, and a long press on a phone. A part
    // inside it with a menu of its own (a tag, a reaction) answers for itself.
    function onContext(node, build) {
        var timer = null;
        var startX = 0;
        var startY = 0;

        node.setAttribute('data-ws-menu', '');

        node.addEventListener('contextmenu', function (event) {
            if (event.target.closest('input, textarea, select, [contenteditable="true"]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            // Android raises its own contextmenu after our long press.
            if (node.wsLongPress && (Date.now() - node.wsLongPress < 900)) {
                return;
            }

            ctxMenu(event.clientX, event.clientY, build(event));
        });

        node.addEventListener('touchstart', function (event) {
            if (event.touches.length !== 1) {
                return;
            }

            var inner = (event.target && event.target.closest) ? event.target.closest('[data-ws-menu]') : null;

            if (inner && (inner !== node)) {
                return;
            }

            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;

            timer = setTimeout(function () {
                timer = null;
                node.wsLongPress = Date.now();
                ctxMenu(startX, startY, build(event));
            }, 550);
        }, { passive: true });

        node.addEventListener('touchmove', function (event) {
            if (timer && ((Math.abs(event.touches[0].clientX - startX) > 10) || (Math.abs(event.touches[0].clientY - startY) > 10))) {
                clearTimeout(timer);
                timer = null;
            }
        }, { passive: true });

        node.addEventListener('touchend', function () {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        }, { passive: true });

        // The tap that ends a long press is not also a click on the node.
        node.addEventListener('click', function (event) {
            if (node.wsLongPress && (Date.now() - node.wsLongPress < 700)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    }

    // Tags inside a message, and the head of a lone tag's card: open the
    // record, see where else it came up.
    function chipMenus(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.ws-chip[data-ws-ref], .ws-tag-card-head[data-ws-ref]'), function (chip) {
            onContext(chip, function () {
                var parts = chip.getAttribute('data-ws-ref').split(':');
                var type = parts[0];
                var id = parseInt(parts[1], 10);
                var label = chip.textContent.trim();
                var items = [];

                if (chip.href) {
                    items.push({ icon: 'bi-box-arrow-up-right', label: t('open_record'), action: function () { window.location.href = chip.href; } });
                    items.push({ icon: 'bi-window-stack', label: t('open_in_new_tab'), action: function () { window.open(chip.href, '_blank', 'noopener'); } });
                }

                if (type === 'task') {
                    items.push({ icon: 'bi-check2-square', label: t('open_task'), action: function () { taskDrawer.open(id, null, null); } });
                } else if ((BOOT.ref_types[type] || {}).record) {
                    items.push({ icon: 'bi-clipboard2-check', label: t('record_workspace'), action: function () { openRecord(type, id, label); } });

                    if ((BOOT.quick_edit || []).indexOf(type) !== -1) {
                        items.push({ icon: 'bi-pencil-square', label: t('qe_edit'), action: function () { openRecord(type, id, label, { edit: true }); } });
                    }

                    items.push({ icon: 'bi-check2-square', label: t('new_task_about'), action: function () {
                        taskDrawer.open(0, { refs: [{ type: type, id: id, label: label, icon: (BOOT.ref_types[type] || {}).icon, token: '<#' + type + ':' + id + '>' }], title: label }, null);
                    } });

                    // A sales quote opens the quote editor filled from it,
                    // as its own screen's Copy does; the address is given
                    // only to those who may write ERP documents.
                    if ((type === 'erp_quote') && chip.href && CFG.urls.erp_quote_copy) {
                        items.push({ icon: 'bi-files', label: t('erp_quote_copy'), action: function () { window.location.href = CFG.urls.erp_quote_copy + '?copy=' + id; } });
                    }
                }

                items.push('-');
                items.push({ icon: 'bi-clipboard', label: t('copy_text'), action: function () { copyText(label); } });

                if (chip.href) {
                    items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () { copyText(absoluteUrl(chip.getAttribute('href'))); } });
                }

                return items;
            });
        });
    }

    // A one-to-one conversation goes through the panel's chat balloon, which
    // already keeps them; the workspace does not keep a second copy.
    function directMessage(userId) {
        if (typeof window.pgChatOpenWith === 'function') {
            window.pgChatOpenWith(userId);
            return;
        }

        toast(t('chat_unavailable'), 'warning');
    }

    // A person: write to them, give them work, plan their day.
    // A person's account in the panel (edit_user.php), for staff who may
    // change it: an administrator anyone, a designer or manager somebody of
    // a lower role - the rule that screen applies. '' for anybody else, and
    // for a guest, an application or somebody no longer here.
    function personEditUrl(person) {
        var mine = parseInt(CFG.me_role, 10);

        if (!person || !person.id || person.guest || person.gone || !CFG.urls.edit_user || isNaN(mine) || (mine > 2)) {
            return '';
        }

        if ((mine !== 0) && !(mine < parseInt(person.role, 10))) {
            return '';
        }

        return CFG.urls.edit_user + '?id=' + person.id;
    }

    // A picture or a name that opens the person's account, where the reader
    // may open it; left as it is otherwise.
    function personLink(node, person) {
        var url = personEditUrl(person);

        if (!url) {
            return node;
        }

        var link = el('a', 'ws-person-link');
        link.href = url;
        link.title = t('edit_user_title', person.name);
        link.appendChild(node);

        return link;
    }

    function personMenu(member, extra) {
        var items = [{ header: member.name + (member.title ? ' · ' + member.title : '') }];
        var account = personEditUrl(member);

        if (member.id !== BOOT.me.id) {
            items.push({ icon: 'bi-chat-dots', label: t('direct_message'), action: function () { directMessage(member.id); } });
        }

        items.push({ icon: 'bi-check2-square', label: t('give_task'), action: function () { taskDrawer.open(0, { assignees: [member.id] }, null); } });
        items.push({ icon: 'bi-calendar-plus', label: t('add_plan_item'), action: function () { eventForm(null, { people: [member.id], start: CFG.today }, null); } });
        items.push('-');
        items.push({ icon: 'bi-calendar3', label: t('their_calendar'), action: function () { window.location.href = CFG.urls.calendar + '?person=' + member.id; } });
        items.push({ icon: 'bi-calendar-week', label: t('their_board'), action: function () { window.location.href = CFG.urls.board + '?person=' + member.id; } });

        if (account) {
            items.push({ icon: 'bi-person-gear', label: t('edit_user'), action: function () { window.location.href = account; } });
        }

        (extra || []).forEach(function (item) { items.push(item); });

        return items;
    }

    // What can be done with a task wherever it is drawn: a list row, a card
    // in a channel, a bar on the board, a line in the calendar.
    function taskMenu(task, onChange, extra) {
        var items = [];
        var changed = function () {
            if (onChange) {
                onChange(task.id);
            }
        };
        var status = function (value) {
            api('ws_task_status', { task_id: task.id, status: value }).then(function () {
                toast(t('task_saved'), 'success');
                changed();
            }).catch(fail);
        };
        var date = task.due_date || task.date || '';

        items.push({ header: (task.number ? task.number + ' · ' : '') + task.title });
        items.push({ icon: 'bi-box-arrow-up-right', label: t('open_task'), action: function () { taskDrawer.open(task.id, null, onChange); } });

        if (task.open !== false && task.status !== 'done' && task.status !== 'cancelled') {
            items.push('-');

            if (task.status !== 'doing') {
                items.push({ icon: 'bi-play-circle', label: t('start_work'), action: function () {
                    // Waiting for open tasks: asked first (workspace_task_links.js).
                    (window.PGWsTaskLinks ? window.PGWsTaskLinks.confirmStart(task.blocked_by || [], partHelpers()) : Promise.resolve(true)).then(function (yes) {
                        if (yes) {
                            status('doing');
                        }
                    });
                } });
            }

            if (task.status !== 'waiting') {
                items.push({ icon: 'bi-pause-circle', label: t('mark_waiting'), action: function () { status('waiting'); } });
            }

            items.push({ icon: 'bi-check2-circle', label: t('mark_done'), action: function () { status('done'); } });

            if (date) {
                items.push('-');
                items.push({ icon: 'bi-calendar-plus', label: t('postpone_day'), action: function () { moveTask({ task_id: task.id, date: addDays(date, 1) }, false, changed); } });
                items.push({ icon: 'bi-calendar-week', label: t('postpone_week'), action: function () { moveTask({ task_id: task.id, date: addDays(date, 7) }, false, changed); } });
            }
        } else {
            items.push({ icon: 'bi-arrow-counterclockwise', label: t('reopen'), action: function () { status('todo'); } });
        }

        (extra || []).forEach(function (item) { items.push(item); });

        items.push('-');
        items.push({ icon: 'bi-hash', label: t('copy_number'), action: function () { copyText(task.number || String(task.id)); } });
        items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () { copyText(absoluteUrl(CFG.urls.tasks + '?task=' + task.id)); } });

        return items;
    }

    // Moves a task to another day (and person), asking first when that
    // clashes with somebody's day. The channel board hands a task to other
    // people the same way, through ws_task_save (action).
    function moveTask(move, force, onDone, action) {
        move.force = force ? 1 : 0;

        api(action || 'ws_task_move', move).then(function (result) {
            if (result.needs_confirm) {
                if (result.check.hard && !result.check.may_override) {
                    ask(t('leave_blocks'), t('ok'), false, warningList(result.check));
                    return;
                }

                ask(t('move_despite'), t('move_anyway'), true, warningList(result.check)).then(function (yes) {
                    if (yes) {
                        moveTask(move, true, onDone, action);
                    }
                });
                return;
            }

            toast(t('task_saved'), 'success');

            if (onDone) {
                onDone();
            }
        }).catch(fail);
    }

    // The newest copy of a repeating task moved to another day: that copy
    // only ('one'), or the copies after it as well ('following'); '' when
    // the move was called off.
    function askSeries() {
        var box = el('div', 'ws-ask-choice');
        var inputs = {};

        [['one', t('move_repeat_one')], ['following', t('move_repeat_following')]].forEach(function (item, index) {
            var line = el('div', 'form-field');
            var input = el('input', 'radio');
            input.type = 'radio';
            input.name = 'ws-series-choice';
            input.value = item[0];
            input.id = 'ws-series-' + item[0];
            input.checked = (index === 0);

            var label = el('label', '', item[1]);
            label.htmlFor = input.id;

            line.appendChild(input);
            line.appendChild(label);
            box.appendChild(line);
            inputs[item[0]] = input;
        });

        box.appendChild(el('div', 'form-text', t('move_repeat_help')));

        return ask(t('move_repeat_ask'), t('move_repeat_ok'), false, box).then(function (yes) {
            var choice = yes ? (inputs.following.checked ? 'following' : 'one') : '';

            // A clash on the new day asks again, in the same dialog.
            return askClosed().then(function () {
                return choice;
            });
        });
    }

    function person(id) {
        var list = (BOOT && BOOT.people) || [];

        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) {
                return list[i];
            }
        }

        return null;
    }

    function department(id) {
        var list = (BOOT && BOOT.departments) || [];

        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) {
                return list[i];
            }
        }

        return null;
    }

    // The eight tones a person's letters are drawn on, in the order
    // pg_avatar_theme_class() gives them (includes/fn/contacts.php).
    var AVATAR_THEMES = ['theme-primary', 'theme-info', 'theme-warning', 'theme-accent', 'theme-danger', 'theme-secondary', 'theme-success', 'theme-inverse'];

    function avatarInitials(name) {
        // A handle (@wiki) is drawn by its name, not by its sign.
        var words = String(name || '').replace(/^[@#]+/, '').trim().split(/\s+/).filter(Boolean);

        if (!words.length) {
            return '?';
        }

        return (words[0].charAt(0) + ((words.length > 1) ? words[words.length - 1].charAt(0) : '')).toLocaleUpperCase();
    }

    // A person as a Bootstrap 6 Avatar, the twin of pg_avatar_markup(): the
    // photo when there is one, else the letters on the person's tone; a login
    // without a name is a grey rounded square. A picture of letters
    // (data:image/svg+xml) is drawn as letters, so no <img> goes out without
    // a real address. presence adds the online / away / offline dot.
    function avatar(personData, size, presence) {
        var data = personData || {};
        var src = data.avatar ? String(data.avatar) : '';
        var kind = data.avatar_kind || '';
        var face = el('span', 'avatar');

        if (src && (src.indexOf('data:image/svg+xml') !== 0) && (kind !== 'contact') && (kind !== 'user')) {
            var img = el('img', 'avatar-img');
            img.src = src;
            img.alt = '';
            img.loading = 'lazy';
            face.appendChild(img);
        } else {
            if (kind === 'user') {
                face.classList.add('avatar-subtle', 'theme-secondary', 'pg-avatar-user');
            } else {
                face.classList.add(AVATAR_THEMES[((parseInt(data.tone, 10) || 0) % 8 + 8) % 8]);
            }

            face.textContent = data.initials ? String(data.initials) : avatarInitials(data.name);
        }

        if (size) {
            face.style.setProperty('--bs-avatar-size', size);
        }

        if (presence && (['online', 'away', 'offline'].indexOf(data.presence) !== -1)) {
            face.appendChild(el('span', 'avatar-status status-' + data.presence));
        }

        face.setAttribute('aria-hidden', 'true');

        return face;
    }

    // Overlapping faces in an .avatar-stack. The stack is row-reverse (the
    // last child sits leftmost and on top), so the faces go in backwards
    // and the first person still reads first.
    function avatarStack(className, people, max) {
        var stack = el('span', 'avatar-stack ws-avatars' + (className ? ' ' + className : ''));

        (people || []).slice(0, max || 4).reverse().forEach(function (person) {
            var face = avatar(person);

            if (person && person.name) {
                face.title = person.name;
            }

            stack.appendChild(face);
        });

        return stack;
    }

    function params() {
        var out = {};

        window.location.search.replace(/^\?/, '').split('&').forEach(function (pair) {
            if (!pair) {
                return;
            }

            var parts = pair.split('=');
            out[decodeURIComponent(parts[0])] = decodeURIComponent((parts[1] || '').replace(/\+/g, ' '));
        });

        return out;
    }

    // A person's own account on an MCP server (includes/workspace/mcp.php):
    // the service's sign-in, then back to the channel, or to the task whose
    // drawer offered it.
    function mcpConnect(serverId, channelId, done, taskId) {
        api('ws_mcp_connect', { server_id: serverId, channel_id: channelId || 0, task_id: taskId || 0 }).then(function (data) {
            if (data.url) {
                window.location.href = data.url;
            }
        }).catch(function (error) {
            fail(error);

            if (done) {
                done();
            }
        });
    }

    function offcanvas(id, title) {
        var existing = document.getElementById(id);

        if (existing) {
            return existing;
        }

        var node = el('dialog', 'drawer drawer-end');
        node.id = id;
        node.tabIndex = -1;
        node.style.width = 'min(34rem, 100vw)';
        node.innerHTML = '<div class="drawer-header border-bottom"><h5 class="drawer-title"></h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="drawer"></button></div>'
            + '<div class="drawer-body"></div><div class="drawer-footer d-none p-5 border-top d-flex gap-3 justify-content-end"></div>';
        node.querySelector('.drawer-title').textContent = title || '';
        node.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        document.body.appendChild(node);

        // The panel's chat bubble sits above offcanvas panels; it steps aside
        // while one of ours is open.
        pgUI.drawer.on(node, 'show', function () {
            document.body.classList.add('ws-drawer-open');
        });
        pgUI.drawer.on(node, 'hidden', function () {
            document.body.classList.remove('ws-drawer-open');
        });

        return node;
    }

    function showOffcanvas(node) {
        pgUI.drawer.show(node);
    }

    function hideOffcanvas(node) {
        var instance = pgUI.drawer.find(node);

        if (instance) {
            instance.hide();
        }
    }

    // The time on a task's due date and its e-mail reminder, a box of the task
    // drawer (includes/workspace/reminders.php). The number is read in the
    // unit beside it and sent as minutes; an empty number is no reminder. A
    // repeating task asks whether every copy is reminded; an earlier copy of
    // a series does not, since its repeat is set on the newest one.
    function reminderField(task, dueInput, repeatField) {
        var conf = CFG.reminders || {};
        var info = task || {};
        var dayTime = conf.day_time || '09:00';
        var wrap = el('div', 'ws-when ws-remind mb-5');
        var head = el('div', 'ws-when-head');

        head.appendChild(icon('bi-bell'));
        head.appendChild(document.createTextNode(t('remind_head')));
        wrap.appendChild(head);

        var time = el('input', 'form-control form-control-sm');
        time.type = 'time';
        time.id = nextId('ws-task-time-');
        time.value = info.due_time || '';

        var amount = el('input', 'form-control form-control-sm');
        amount.type = 'number';
        amount.min = '0';
        amount.step = '1';
        amount.id = nextId('ws-task-remind-');
        amount.setAttribute('inputmode', 'numeric');

        var unit = select([['1', t('remind_unit_minutes')], ['60', t('remind_unit_hours')], ['1440', t('remind_unit_days')]], '60');
        unit.setAttribute('aria-label', t('remind_before'));

        var start = (info.remind_minutes === null || info.remind_minutes === undefined) ? null : parseInt(info.remind_minutes, 10);

        if (start !== null && !isNaN(start)) {
            var size = (start > 0 && start % 1440 === 0) ? 1440 : ((start > 0 && start % 60 === 0) ? 60 : 1);
            unit.value = String(size);
            amount.value = String(start / size);
        } else {
            start = null;
        }

        var group = el('div', 'input-group input-group-sm');
        group.appendChild(amount);
        group.appendChild(unit);

        var row = el('div', 'row g-3');
        [[formRow(t('remind_time'), time), 'col-5'], [formRow(t('remind_before'), group), 'col-7']].forEach(function (pair) {
            var cell = el('div', pair[1]);
            pair[0].classList.remove('mb-5');
            pair[0].classList.add('mb-3');
            cell.appendChild(pair[0]);
            row.appendChild(cell);
        });
        row.querySelectorAll('label')[1].htmlFor = amount.id;
        wrap.appendChild(row);

        var eachWrap = el('div', 'form-field mb-1 d-none');
        var each = el('input', 'check');
        each.type = 'checkbox';
        each.id = nextId('ws-task-remind-each-');
        each.checked = (task && task.recurring) ? !!info.remind_each : true;
        var eachLabel = el('label', 'small', t('remind_each'));
        eachLabel.htmlFor = each.id;
        eachWrap.appendChild(each);
        eachWrap.appendChild(eachLabel);
        wrap.appendChild(eachWrap);

        var note = el('div', 'ws-when-summary');
        note.setAttribute('aria-live', 'polite');
        wrap.appendChild(note);

        function minutes() {
            var raw = String(amount.value).trim();
            var count = parseInt(raw, 10);

            if (raw === '' || isNaN(count) || count < 0) {
                return null;
            }

            return count * parseInt(unit.value, 10);
        }

        function repeating() {
            var rule = repeatField ? repeatField.value() : null;

            return !!(rule && rule.frequency && rule.frequency !== 'none');
        }

        function pad(number) {
            return (number < 10 ? '0' : '') + number;
        }

        function draw() {
            var wanted = minutes();

            eachWrap.classList.toggle('d-none', wanted === null || !repeating());
            clear(note);
            note.classList.remove('ws-when-warn');

            if (wanted === null) {
                note.appendChild(document.createTextNode(t('remind_none')));
                return;
            }

            if (!dueInput.value) {
                note.classList.add('ws-when-warn');
                note.appendChild(document.createTextNode(t('remind_needs_due')));
                return;
            }

            // Sent already, and nothing it depends on changed since.
            if (task && info.reminded && wanted === start && (time.value || '') === (info.due_time || '') && dueInput.value === (task.due_date || '')) {
                note.appendChild(document.createTextNode(t('remind_sent', info.reminded_label)));
                return;
            }

            var day = dueInput.value.split('-');
            var clock = (time.value || dayTime).split(':');
            var due = new Date(+day[0], +day[1] - 1, +day[2], +clock[0], +clock[1], 0);
            var at = new Date(due.getTime() - wanted * 60000);
            var now = Date.now();

            if (due.getTime() <= now) {
                note.classList.add('ws-when-warn');
                note.appendChild(document.createTextNode(t('remind_past')));
                return;
            }

            note.appendChild(document.createTextNode(at.getTime() <= now
                ? t('remind_goes_now')
                : t('remind_goes_at', pad(at.getDate()) + '.' + pad(at.getMonth() + 1) + '.' + at.getFullYear() + ' ' + pad(at.getHours()) + ':' + pad(at.getMinutes()))));

            if (!time.value) {
                note.appendChild(el('div', 'fg-secondary', t('remind_day_time', dayTime)));
            }
        }

        [time, amount, unit, dueInput].forEach(function (input) {
            input.addEventListener('change', draw);
            input.addEventListener('input', draw);
        });

        // The repeat box switches itself on and off from several controls.
        if (repeatField && repeatField.node) {
            repeatField.node.addEventListener('change', draw);
            repeatField.node.addEventListener('click', function () {
                window.setTimeout(draw, 0);
            });
        }

        draw();

        return {
            node: wrap,
            value: function () {
                var wanted = minutes();
                var out = { due_time: time.value || '', remind_minutes: (wanted === null) ? '' : wanted };

                if (repeating()) {
                    out.remind_each = each.checked ? 1 : 0;
                }

                return out;
            }
        };
    }

    function formRow(label, control, help) {
        var row = el('div', 'mb-5');
        var caption = el('label', 'form-label', label);

        if (control.id) {
            caption.htmlFor = control.id;
        }

        row.appendChild(caption);
        row.appendChild(control);

        if (help) {
            row.appendChild(el('div', 'form-text', help));
        }

        return row;
    }

    function select(options, value) {
        var node = el('select', 'form-control form-control-sm');

        options.forEach(function (option) {
            var item = el('option', '', option[1]);
            item.value = option[0];

            if (String(option[0]) === String(value)) {
                item.selected = true;
            }

            node.appendChild(item);
        });

        return node;
    }

    var uid = 0;

    function nextId(prefix) {
        uid += 1;
        return prefix + uid;
    }

    // ── Progress bars and tag fields ───────────────────────────────────

    // How far a checklist has got, as a thin bar.
    function progressBar(progress, small) {
        var bar = el('div', 'progress ws-progress' + (small ? ' ws-progress-sm' : '') + (progress.complete ? ' ws-progress-full' : ''));
        var fill = el('div', 'progress-bar');

        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', '100');
        bar.setAttribute('aria-valuenow', String(progress.percent));
        fill.style.width = progress.percent + '%';
        bar.appendChild(fill);

        return bar;
    }

    // A text box outside the channel composer that still picks people with
    // @ and records with # (a task's notes): the composer's picker, lent to
    // it. Slash commands stay in the channel.
    function tokenField(textarea, wrap) {
        var field = {
            input: textarea,
            labels: [],
            picker: null,
            composerWrap: wrap,
            noCommands: true,
            autosize: function () {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 220) + 'px';
            }
        };

        ['pickerCheck', 'recordQuery', 'openPicker', 'drawPicker', 'pickerKey', 'pick', 'closePicker', 'tokens', 'insertTrigger', 'beforeCaret'].forEach(function (name) {
            field[name] = app[name];
        });

        textarea.addEventListener('input', function () {
            field.autosize();
            field.pickerCheck();
        });
        textarea.addEventListener('click', function () { field.pickerCheck(); });
        textarea.addEventListener('keydown', function (event) {
            // Escape closes the picker, not the drawer around it.
            if (field.picker && field.pickerKey(event)) {
                event.stopPropagation();
            }
        });
        textarea.addEventListener('blur', function () {
            setTimeout(function () { field.closePicker(); }, 150);
        });

        return field;
    }

    // The same for the formatted box (assets/js/workspace_editor.js), as
    // the task's description and notes use it: tags are chips, lists and
    // tables are cards, the text is kept as a message's markup. value()
    // gives the markup, set() puts a stored one back, dirty() says whether it
    // was changed since. null without the formatted box.
    function blockIcon(kind) {
        return { table: 'bi-table', checklist: 'bi-ui-checks', code: 'bi-code-slash', calc: 'bi-calculator' }[kind] || 'bi-bookmark';
    }

    // "From elsewhere": the titled blocks the person can read, by their
    // title; the one chosen comes back as markup (includes/workspace/blocks.php).
    function blockPuller(onPick) {
        var node = offcanvas('ws-block-pull', t('pull_title'));
        var body = clear(node.querySelector('.drawer-body'));
        var search = el('input', 'form-control form-control-sm mb-3');
        var list = el('div', 'ws-pull-list');
        var serial = 0;

        node.querySelector('.drawer-footer').classList.add('d-none');
        search.type = 'search';
        search.placeholder = t('pull_search');
        search.setAttribute('aria-label', search.placeholder);
        body.appendChild(el('p', 'small fg-secondary', t('pull_help')));
        body.appendChild(search);
        body.appendChild(list);

        function load() {
            var asked = ++serial;

            api('ws_block_search', { q: search.value }).then(function (data) {
                if (asked !== serial) {
                    return;
                }

                clear(list);

                if (!data.items.length) {
                    list.appendChild(el('div', 'ws-empty', t('pull_none')));
                }

                data.items.forEach(function (item) {
                    var row = el('button', 'ws-pull-item');
                    var words = el('span', 'ws-pull-words');

                    row.type = 'button';
                    row.appendChild(icon(blockIcon(item.kind), 'ws-pull-icon'));
                    words.appendChild(el('b', '', item.title));
                    words.appendChild(el('span', 'small fg-secondary', t('pull_kind_' + item.kind) + ' · ' + item.where + ' · ' + item.author + ' · ' + item.time));
                    row.appendChild(words);
                    row.addEventListener('click', function () {
                        api('ws_block_pull', { id: item.id }).then(function (pulled) {
                            hideOffcanvas(node);
                            onPick(pulled);
                        }).catch(fail);
                    });
                    list.appendChild(row);
                });
            }).catch(fail);
        }

        search.addEventListener('input', debounce(load, 250));
        showOffcanvas(node);
        load();
        setTimeout(function () { search.focus(); }, 250);
    }

    // A file of the file manager, picked to go into a channel as a copy
    // (includes/workspace/library.php): its folders to walk, a search over
    // every folder the person may open, and the files changed last where it
    // starts. A file a channel would refuse is listed with the reason and
    // cannot be picked.
    function libraryPicker(onPick) {
        var node = offcanvas('ws-library', t('lib_from_manager'));
        var body = clear(node.querySelector('.drawer-body'));
        var search = el('input', 'form-control form-control-sm mb-3');
        var trail = el('nav', 'ws-lib-trail');
        var list = el('div', 'ws-pull-list');
        var serial = 0;
        var folder = 0;

        node.querySelector('.drawer-footer').classList.add('d-none');
        search.type = 'search';
        search.placeholder = t('lib_search');
        search.setAttribute('aria-label', search.placeholder);
        trail.setAttribute('aria-label', t('lib_top'));
        body.appendChild(el('p', 'small fg-secondary', t('lib_help')));
        body.appendChild(search);
        body.appendChild(trail);
        body.appendChild(list);

        function fileIcon(file) {
            var byExt = {
                pdf: 'bi-file-earmark-pdf', doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word',
                xls: 'bi-file-earmark-spreadsheet', xlsx: 'bi-file-earmark-spreadsheet', csv: 'bi-file-earmark-spreadsheet', ods: 'bi-file-earmark-spreadsheet',
                ppt: 'bi-file-earmark-slides', pptx: 'bi-file-earmark-slides', zip: 'bi-file-earmark-zip', txt: 'bi-file-earmark-text', md: 'bi-file-earmark-text'
            };
            var byKind = { image: 'bi-file-earmark-image', video: 'bi-file-earmark-play', audio: 'bi-file-earmark-music' };

            return byExt[file.ext] || byKind[file.kind] || 'bi-file-earmark';
        }

        function crumb(label, id, current) {
            var step = el(current ? 'span' : 'button', current ? 'ws-lib-crumb fw-semibold' : 'ws-lib-crumb btn btn-link btn-sm p-0', label);

            if (!current) {
                step.type = 'button';
                step.addEventListener('click', function () { open(id); });
            } else {
                step.setAttribute('aria-current', 'page');
            }

            return step;
        }

        function folderRow(item) {
            var row = el('button', 'ws-pull-item ws-lib-row');

            row.type = 'button';
            row.appendChild(icon('bi-folder2', 'ws-pull-icon'));
            row.appendChild(el('span', 'ws-lib-name', item.name));
            row.appendChild(icon('bi-chevron-right', 'ms-auto fg-3'));
            row.addEventListener('click', function () { open(item.id); });

            return row;
        }

        function fileRow(file, withPlace) {
            var row = el('button', 'ws-pull-item ws-lib-row');
            var words = el('span', 'ws-pull-words');
            var facts = [withPlace ? file.where : '', file.size, file.time].filter(Boolean);

            row.type = 'button';

            if (file.thumb) {
                var picture = el('img', 'ws-lib-thumb');

                // A picture that will not load shows the file's icon instead.
                picture.addEventListener('error', function () {
                    picture.replaceWith(icon(fileIcon(file), 'ws-pull-icon'));
                });
                picture.alt = '';
                picture.loading = 'lazy';
                picture.src = file.thumb;
                row.appendChild(picture);
            } else {
                row.appendChild(icon(fileIcon(file), 'ws-pull-icon'));
            }

            words.appendChild(el('span', 'ws-lib-name', file.name));
            words.appendChild(el('span', 'small fg-secondary', file.refused || facts.join(' · ')));
            row.appendChild(words);

            if (file.refused) {
                row.disabled = true;
                row.classList.add('ws-lib-refused');
            } else {
                row.addEventListener('click', function () {
                    hideOffcanvas(node);
                    onPick(file);
                });
            }

            return row;
        }

        function draw(data) {
            clear(trail);
            clear(list);

            // Another folder opens at its top, not where the last one was left.
            body.scrollTop = 0;

            if (data.nothing) {
                search.disabled = true;
                list.appendChild(el('div', 'ws-empty', t('lib_nothing')));
                return;
            }

            if (!data.search) {
                if (data.several) {
                    trail.appendChild(crumb(t('lib_top'), 0, !data.folder));
                }

                data.trail.forEach(function (step, index) {
                    if (trail.firstChild) {
                        trail.appendChild(icon('bi-chevron-right', 'ws-lib-sep'));
                    }

                    trail.appendChild(crumb(step.name, step.id, index === data.trail.length - 1));
                });
            }

            if (data.recent.length) {
                list.appendChild(el('div', 'ws-lib-section', t('lib_recent')));
                data.recent.forEach(function (file) { list.appendChild(fileRow(file, true)); });
                list.appendChild(el('div', 'ws-lib-section', data.folder ? data.folder.name : t('lib_top')));
            }

            data.folders.forEach(function (item) { list.appendChild(folderRow(item)); });
            data.files.forEach(function (file) { list.appendChild(fileRow(file, data.search)); });

            if (!data.folders.length && !data.files.length) {
                list.appendChild(el('div', 'ws-empty', t(data.search ? 'lib_no_match' : 'lib_empty')));
            }

            if (data.more) {
                list.appendChild(el('div', 'small fg-secondary', t('lib_more')));
            }
        }

        function load() {
            var asked = ++serial;

            api('ws_library_browse', { folder_id: folder, q: search.value.trim() }).then(function (data) {
                if (asked === serial) {
                    draw(data);
                }
            }).catch(fail);
        }

        function open(id) {
            folder = id;
            search.value = '';
            load();
        }

        clear(list).appendChild(skeleton(3));
        search.addEventListener('input', debounce(load, 250));
        showOffcanvas(node);
        load();
        setTimeout(function () { search.focus(); }, 250);
    }

    function richField(wrap, options) {
        if (!window.PGWsEditor) {
            return null;
        }

        var field = null;
        var editor = window.PGWsEditor.create({
            placeholder: options.placeholder || '',
            previews: !!options.previews,
            slashInserts: options.slashInserts,
            pasteFiles: options.pasteFiles,
            onInput: function () {
                field.pickerCheck();
            },
            onKeydown: function (event) {
                // Escape closes the picker, not the drawer around it.
                if (field.picker && field.pickerKey(event)) {
                    event.stopPropagation();
                    return true;
                }

                return options.onKeydown ? !!options.onKeydown(event) : false;
            }
        });
        var baseline = '';

        field = {
            rich: editor,
            input: editor.node,
            labels: [],
            picker: null,
            composerWrap: wrap,
            noCommands: true,
            autosize: function () {},
            value: function () {
                return editor.markup();
            },
            set: function (markup, labels) {
                editor.setMarkup(String(markup || ''), labels || {});
                baseline = editor.markup();

                // setMarkup leaves the caret in the box; it is not focused.
                if (document.activeElement !== editor.node) {
                    window.getSelection().removeAllRanges();
                }
            },
            dirty: function () {
                return editor.markup() !== baseline;
            },
            // Buttons under the box (PGWsEditor.toolbar()): people, records,
            // emoji and, where the text may hold them, lists, a quote and a
            // checklist. Callers
            // add their own (save, cancel) to the row it answers.
            tools: function (withList) {
                var mention = button('btn btn-sm btn-ghost', '', 'bi-at', t('mention'));
                mention.addEventListener('click', function () { field.insertTrigger('@'); });

                var tag = button('btn btn-sm btn-ghost', '', 'bi-hash', t('tag_record'));
                tag.addEventListener('click', function () { field.insertTrigger('#'); });

                var emoji = button('btn btn-sm btn-ghost', '', 'bi-emoji-smile', t('insert_emoji'));
                emoji.addEventListener('click', function (event) {
                    var box = emoji.getBoundingClientRect();

                    event.stopPropagation();
                    emojiPicker(box.left, box.bottom + 4, function (picked) { editor.insertText(picked); });
                });
                // ":" and two letters suggest emoji where the button is.
                field.colonEmoji = true;

                return window.PGWsEditor.toolbar(editor, [mention, tag, emoji].concat(withList ? ['bullets', 'numbers', 'quote', 'checklist'] : []), { className: 'ws-note-actions' });
            }
        };

        ['pickerCheck', 'recordQuery', 'openPicker', 'drawPicker', 'placePicker', 'pickerKey', 'pick', 'closePicker', 'tokens', 'insertTrigger', 'beforeCaret', 'insertPulled'].forEach(function (name) {
            field[name] = app[name];
        });

        editor.node.classList.add('ws-rich-field');
        editor.node.addEventListener('click', function () { field.pickerCheck(); });
        editor.node.addEventListener('blur', function () {
            setTimeout(function () { field.closePicker(); }, 150);
        });

        return field;
    }

    // ── Picking people ─────────────────────────────────────────────────

    function peoplePicker(selected, allowed) {
        var box = el('div', 'ws-people-select');
        var chosen = {};

        (selected || []).forEach(function (id) { chosen[id] = true; });

        ((BOOT && BOOT.people) || []).forEach(function (member) {
            if (allowed && allowed.indexOf(member.id) === -1) {
                return;
            }

            var toggle = el('button', 'ws-person-toggle' + (chosen[member.id] ? ' active' : ''));
            toggle.type = 'button';
            toggle.setAttribute('aria-pressed', chosen[member.id] ? 'true' : 'false');
            toggle.appendChild(avatar(member));
            toggle.appendChild(document.createTextNode(member.name));
            toggle.addEventListener('click', function () {
                chosen[member.id] = !chosen[member.id];
                toggle.classList.toggle('active', chosen[member.id]);
                toggle.setAttribute('aria-pressed', chosen[member.id] ? 'true' : 'false');
                box.dispatchEvent(new Event('change'));
            });
            box.appendChild(toggle);
        });

        box.value = function () {
            return Object.keys(chosen).filter(function (id) { return chosen[id]; }).map(Number);
        };

        box.set = function (ids) {
            chosen = {};
            (ids || []).forEach(function (id) { chosen[id] = true; });

            Array.prototype.forEach.call(box.children, function (toggle, index) {
                var list = ((BOOT && BOOT.people) || []).filter(function (member) {
                    return !allowed || allowed.indexOf(member.id) !== -1;
                });
                var member = list[index];

                if (member) {
                    toggle.classList.toggle('active', !!chosen[member.id]);
                }
            });
        };

        return box;
    }

    function departmentOptions(emptyLabel) {
        var options = [[0, emptyLabel || t('no_department')]];

        ((BOOT && BOOT.departments) || []).forEach(function (item) {
            options.push([item.id, item.name]);
        });

        return options;
    }

    // ── Record search (a type and a query, for fields outside the composer) ──

    function refSearchField(types, onPick, placeholder) {
        var wrap = el('div', 'ws-ref-search-box');
        var group = el('div', 'input-group input-group-sm');
        var typeSelect = el('select', 'form-control form-control-sm');
        var input = el('input', 'form-control form-control-sm');
        var list = null;

        typeSelect.style.maxWidth = '9rem';

        if (Object.keys(types).length > 1) {
            var every = el('option', '', t('all_types'));
            every.value = 'all';
            typeSelect.appendChild(every);
        }

        Object.keys(types).forEach(function (key) {
            var option = el('option', '', types[key].label);
            option.value = key;
            typeSelect.appendChild(option);
        });

        input.type = 'search';
        input.placeholder = placeholder || t('search_records');
        input.setAttribute('aria-label', input.placeholder);

        group.appendChild(typeSelect);
        group.appendChild(input);
        wrap.appendChild(group);

        function closeList() {
            if (list) {
                list.remove();
                list = null;
            }
        }

        var run = debounce(function () {
            var query = input.value.trim();

            if (query === '') {
                closeList();
                return;
            }

            var ask = { type: typeSelect.value, q: query };

            // "All" among the kinds this field offers, not every kind.
            if (typeSelect.value === 'all') {
                ask.types = Object.keys(types);
            }

            api('ws_ref_search', ask).then(function (data) {
                closeList();
                list = el('div', 'ws-picker');
                var inner = el('div', 'ws-picker-list');

                if (!data.items.length) {
                    inner.appendChild(el('div', 'ws-picker-empty', t('nothing_found')));
                }

                data.items.forEach(function (item) {
                    var row = el('button', 'ws-picker-item');
                    row.type = 'button';
                    row.appendChild(badgedIcon(item.icon || 'bi-dot', item.badge || ''));
                    row.appendChild(el('span', 'ws-picker-label', item.label));
                    row.appendChild(el('span', 'ws-picker-meta', item.meta));
                    row.addEventListener('click', function () {
                        onPick(item);
                        input.value = '';
                        closeList();
                    });
                    inner.appendChild(row);
                });

                list.appendChild(inner);
                wrap.appendChild(list);
            }).catch(fail);
        }, 250);

        input.addEventListener('input', run);
        typeSelect.addEventListener('change', run);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeList();
            }
        });
        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                closeList();
            }
        });

        wrap.setType = function (type) {
            typeSelect.value = type;
        };

        return wrap;
    }

    // How a picked tag reads in the composer: its label behind the sigil,
    // without doubling a sigil the label already carries (#channel, @person).
    function shownLabel(sigil, label) {
        label = String(label || '');
        return (label.charAt(0) === sigil) ? label : sigil + label;
    }

    function chipNode(ref, onRemove) {
        var chip = el(ref.url ? 'a' : 'span', 'ws-chip ws-chip-' + ref.type);

        if (ref.url) {
            chip.href = ref.url;
        }

        chip.appendChild(badgedIcon(ref.icon || 'bi-dot', ref.badge || ''));
        chip.appendChild(el('span', '', ref.label));

        if (onRemove) {
            var remove = button('', '', 'bi-x', t('remove'));
            remove.addEventListener('click', function (event) {
                event.preventDefault();
                onRemove();
            });
            chip.appendChild(remove);
        }

        return chip;
    }

    // ── Warnings from the board check ──────────────────────────────────

    function warningList(check, onSuggest) {
        var box = el('div');

        (check.items || []).forEach(function (item) {
            var row = el('div', 'ws-warning ws-warning-' + item.level);
            row.appendChild(icon(item.level === 'hard' ? 'bi-calendar-x' : (item.level === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle')));
            row.appendChild(el('span', '', item.message));
            box.appendChild(row);
        });

        if (onSuggest && check.suggestions && check.suggestions.length) {
            var line = el('div', 'd-flex flex-wrap gap-1 mt-3 align-items-center');
            line.appendChild(el('span', 'small fg-secondary me-1', t('lighter_people')));

            check.suggestions.forEach(function (suggestion) {
                var pick = button('btn btn-sm btn-outline theme-secondary', suggestion.label, 'bi-person-plus');
                pick.addEventListener('click', function () { onSuggest(suggestion); });
                line.appendChild(pick);
            });

            box.appendChild(line);
        }

        return box;
    }

    // The helpers lent to the files that draw parts of the screens on their
    // own (assets/js/workspace_task_time.js, workspace_task_links.js,
    // workspace_task_delegate.js).
    function partHelpers() {
        return {
            api: api, ask: ask, toast: toast, fail: fail, t: t, el: el, icon: icon, button: button,
            clear: clear, select: select, avatar: avatar, avatarStack: avatarStack, nextId: nextId, debounce: debounce,
            mcpConnect: mcpConnect,
            boot: function () { return BOOT; },
            openTask: function (taskId, onSaved) { taskDrawer.open(taskId, null, onSaved || taskDrawer.onSaved); }
        };
    }

    // ── The task drawer, shared by every screen ────────────────────────

    var taskDrawer = {
        node: null,
        task: null,
        onSaved: null,
        fields: {},
        refs: [],

        build: function () {
            if (this.node) {
                return;
            }

            this.node = offcanvas('ws-task-drawer', t('task'));
        },

        open: function (taskId, defaults, onSaved) {
            var self = this;

            self.build();
            self.onSaved = onSaved || null;

            if (taskId) {
                api('ws_task_get', { task_id: taskId }).then(function (data) {
                    self.render(data.task, {});
                    showOffcanvas(self.node);
                }).catch(fail);
            } else {
                self.render(null, defaults || {});
                showOffcanvas(self.node);
            }
        },

        render: function (task, defaults) {
            var self = this;
            var body = clear(self.node.querySelector('.drawer-body'));
            var footer = clear(self.node.querySelector('.drawer-footer'));
            var editable = !task || task.can_edit;
            var rights = BOOT.me.rights;

            self.task = task;
            // Records only: the people on the task have their own field.
            self.refs = (task ? task.refs : (defaults.refs || [])).filter(function (ref) {
                return !ref.token || ref.token.indexOf('<#') === 0;
            });
            self.node.querySelector('.drawer-title').textContent = task ? (task.number + ' · ' + t('task')) : t('new_task');

            footer.classList.remove('d-none');

            if (task) {
                var info = el('div', 'd-flex flex-wrap gap-3 align-items-center small fg-secondary mb-5');
                info.appendChild(el('span', 'badge theme-secondary', task.status_label));
                info.appendChild(el('span', '', t('created_by', task.creator, task.created_label)));

                if (task.channel) {
                    var channelLink = el('a', 'pg-link-body-emphasis', '#' + task.channel.name);
                    channelLink.href = task.channel.url;
                    info.appendChild(channelLink);
                }

                if (task.completed_label) {
                    info.appendChild(el('span', 'fg-success', t('completed_on', task.completed_label)));
                }

                body.appendChild(info);

                if (editable && task.open) {
                    var quick = el('div', 'd-flex flex-wrap gap-1 mb-5');

                    Object.keys(BOOT.statuses).forEach(function (status) {
                        if (status === task.status) {
                            return;
                        }

                        var move = button('btn btn-sm ' + (status === 'done' ? 'btn-outline theme-success' : 'btn-text theme-secondary'), BOOT.statuses[status]);
                        move.addEventListener('click', function () { self.setStatus(status); });
                        quick.appendChild(move);
                    });

                    body.appendChild(quick);
                } else if (editable) {
                    var reopen = button('btn btn-sm btn-text theme-secondary mb-5', t('reopen'), 'bi-arrow-counterclockwise');
                    reopen.addEventListener('click', function () { self.setStatus('todo'); });
                    body.appendChild(reopen);
                }

                // Progress and checklists; filled in after the form below is
                // drawn (and, for a reader, locked), since ticking is not
                // editing the task.
                self.workBox = el('div', 'ws-task-work d-none');
                body.appendChild(self.workBox);
            } else {
                self.workBox = null;
            }

            self.defaults = defaults || {};

            if (!task && self.defaults.checklist_message_id) {
                var linked = el('div', 'ws-task-link-note');
                linked.appendChild(icon('bi-list-check', 'me-3'));
                linked.appendChild(document.createTextNode(t('list_link_info', self.defaults.list_count || 0)));
                body.appendChild(linked);
            }

            var f = self.fields = {};

            f.title = el('input', 'form-control');
            f.title.id = nextId('ws-task-title-');
            f.title.maxLength = 255;
            f.title.value = task ? task.title : (defaults.title || '');
            f.title.required = true;
            body.appendChild(formRow(t('title'), f.title));

            // Formatted like a message where it can be changed: tags are
            // chips, a checklist is a card. A description left as it was is
            // sent back as it was stored.
            var descStart = task ? task.description : (defaults.description || '');
            var descWrap = el('div', 'ws-note-compose ws-desc-field');
            var descRich = editable ? richField(descWrap, { placeholder: t('description') }) : null;

            if (descRich) {
                descRich.input.id = nextId('ws-task-desc-');
                descRich.set(descStart, task ? task.description_labels : null);
                descWrap.appendChild(descRich.input);
                descWrap.appendChild(descRich.tools(true));
                f.description = descRich;
            } else {
                var descArea = el('textarea', 'form-control');
                descArea.id = nextId('ws-task-desc-');
                descArea.rows = 3;
                descArea.value = descStart;
                descWrap.appendChild(descArea);
                f.description = {
                    input: descArea,
                    value: function () { return descArea.value; },
                    set: function (markup) { descArea.value = markup || ''; },
                    dirty: function () { return descArea.value !== descStart; }
                };
            }

            f.descriptionStart = descStart;
            body.appendChild(formRow(t('description'), descWrap, descRich ? t('description_help_rich') : t('description_help')));
            descWrap.previousSibling.htmlFor = f.description.input.id;

            var grid = el('div', 'row g-3');

            function col(content) {
                var cell = el('div', 'col-6');
                cell.appendChild(content);
                grid.appendChild(cell);
            }

            f.start = el('input', 'form-control form-control-sm');
            f.start.type = 'date';
            f.start.id = nextId('ws-task-start-');
            f.start.value = task ? (task.start_date || '') : (defaults.start_date || '');

            f.due = el('input', 'form-control form-control-sm');
            f.due.type = 'date';
            f.due.id = nextId('ws-task-due-');
            f.due.value = task ? (task.due_date || '') : (defaults.due_date || '');

            // Made here and placed in the grid below: the dates box counts
            // the department's days off.
            f.department = select(departmentOptions(), task ? task.department_id : (defaults.department_id || 0));
            f.department.id = nextId('ws-task-dept-');

            // The dates and the repeat are one box, drawn by
            // assets/js/workspace_recurrence.js: the repeat is read off the
            // due date and sent with the task as `recurrence`.
            var dateRows = { start: f.start, due: f.due, startRow: formRow(t('start_date'), f.start), dueRow: formRow(t('due_date'), f.due), department: f.department };

            if (window.PGWsRecurrence && CFG.recurrence && CFG.recurrence.ready) {
                f.repeat = window.PGWsRecurrence.field(task, defaults, editable, {
                    api: api,
                    ask: ask,
                    toast: toast,
                    done: function () {
                        hideOffcanvas(self.node);

                        if (self.onSaved) {
                            self.onSaved(task ? task.id : 0);
                        }
                    },
                    open: function (taskId) {
                        self.open(taskId, null, self.onSaved);
                    }
                }, dateRows);
                body.appendChild(f.repeat.node);
            } else {
                col(dateRows.startRow);
                col(dateRows.dueRow);
            }

            // The time on the due date and the e-mail reminder, under the
            // dates (includes/workspace/reminders.php).
            var remindLater = null;

            if (CFG.reminders && CFG.reminders.ready) {
                f.remind = reminderField(task, f.due, f.repeat || null);

                if (f.repeat) {
                    body.appendChild(f.remind.node);
                } else {
                    remindLater = f.remind.node;
                }
            }

            f.estimate = el('input', 'form-control form-control-sm');
            f.estimate.type = 'number';
            f.estimate.min = '0';
            f.estimate.step = '0.25';
            f.estimate.id = nextId('ws-task-est-');
            f.estimate.value = task && task.estimate_minutes ? String(Math.round(task.estimate_minutes / 15) / 4) : (defaults.estimate_hours || '');
            col(formRow(t('estimate_hours'), f.estimate, t('estimate_help')));

            f.priority = select(Object.keys(BOOT.priorities).map(function (key) { return [key, BOOT.priorities[key]]; }), task ? task.priority : (defaults.priority || 'normal'));
            f.priority.id = nextId('ws-task-prio-');
            col(formRow(t('priority'), f.priority));

            col(formRow(t('department'), f.department));

            if (!task) {
                var channelOptions = [[0, t('no_channel')]];

                (BOOT.channels || []).forEach(function (channel) {
                    if (channel.joined || channel.kind === 'public') {
                        channelOptions.push([channel.id, '#' + channel.name]);
                    }
                });

                f.channel = select(channelOptions, defaults.channel_id || 0);
                f.channel.id = nextId('ws-task-channel-');

                var channelRow = formRow(t('post_card_in'), f.channel);

                // Opened from a discussion: the card goes to the discussion
                // too, as long as it stays in that discussion's channel.
                if (defaults.thread_id) {
                    var threadNote = el('div', 'form-text', t('task_card_thread_note'));
                    var noteShown = function () {
                        threadNote.classList.toggle('d-none', String(f.channel.value) !== String(defaults.channel_id));
                    };

                    channelRow.appendChild(threadNote);
                    f.channel.addEventListener('change', noteShown);
                    noteShown();
                }

                col(channelRow);
            }

            body.appendChild(grid);

            if (remindLater) {
                body.appendChild(remindLater);
            }

            // Who is on it. Somebody without the assign right sees only the
            // people they may hand work to: themselves and their departments.
            var allowed = null;

            if (!rights.assign) {
                allowed = [BOOT.me.id];

                (BOOT.departments || []).forEach(function (item) {
                    if (rights.leads.indexOf(item.id) !== -1) {
                        allowed = allowed.concat(item.members);
                    }
                });

                if (task) {
                    task.assignees.forEach(function (member) { allowed.push(member.id); });
                }
            }

            f.people = peoplePicker(task ? task.assignees.map(function (member) { return member.id; }) : (defaults.assignees || [BOOT.me.id]), allowed);
            body.appendChild(formRow(t('people'), f.people, t('people_help')));

            var refsWrap = el('div', 'mb-5');
            refsWrap.appendChild(el('label', 'form-label', t('about_records')));
            f.refs = el('div', 'ws-refs-field mb-3');
            refsWrap.appendChild(f.refs);
            refsWrap.appendChild(refSearchField(BOOT.ref_types, function (item) {
                self.refs.push({ token: item.token, type: item.type, id: item.id, label: item.label, icon: item.icon, badge: item.badge || '', url: '' });
                self.drawRefs();
            }));
            body.appendChild(refsWrap);
            self.drawRefs();

            f.warnings = el('div', 'mb-3');
            body.appendChild(f.warnings);

            var recheck = debounce(function () { self.check(); }, 400);

            [f.start, f.due, f.estimate, f.department].forEach(function (input) {
                input.addEventListener('change', recheck);
            });
            f.people.addEventListener('change', recheck);

            if (!editable) {
                Array.prototype.forEach.call(body.querySelectorAll('input, textarea, select, .ws-person-toggle'), function (input) {
                    input.disabled = true;
                });
            }

            // The assistant the task is handed to, its runs and answers
            // (workspace_task_delegate.js): drawn again with the notes.
            self.delegatePart = null;

            if (task && task.delegation && window.PGWsTaskDelegate) {
                self.delegatePart = window.PGWsTaskDelegate.drawer(task, partHelpers(), function () { self.changed(); });
                body.appendChild(self.delegatePart.node);
            }

            // The time spent and the tasks it waits for: their own rights,
            // their own requests (workspace_task_time.js, workspace_task_links.js).
            if (task && task.time && window.PGWsTaskTime) {
                body.appendChild(window.PGWsTaskTime.drawer(task, partHelpers(), function () { self.changed(); }));
            }

            if (task && task.links && window.PGWsTaskLinks) {
                body.appendChild(window.PGWsTaskLinks.drawer(task, partHelpers(), function () { self.changed(); }));
            }

            // The customer asked to approve the task (workspace_task_approval.js).
            if (task && task.approvals && window.PGWsTaskApproval) {
                body.appendChild(window.PGWsTaskApproval.drawer(task, partHelpers()));
            }

            // Notes: anyone who can see the task may add one.
            self.notesBox = (task && task.work) ? el('div', 'ws-task-notes') : null;

            if (self.notesBox) {
                body.appendChild(self.notesBox);
            }

            if (task) {
                self.drawWork(task);
            }

            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', task ? t('save') : t('create_task'), 'bi-check2');
            save.disabled = !editable;
            save.addEventListener('click', function () { self.save(false); });

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'drawer');

            footer.appendChild(cancel);
            footer.appendChild(save);

            if (editable) {
                self.check();
            }
        },

        drawRefs: function () {
            var self = this;
            var box = clear(self.fields.refs);

            self.refs.forEach(function (ref, index) {
                box.appendChild(chipNode(ref, function () {
                    self.refs.splice(index, 1);
                    self.drawRefs();
                }));
            });

            if (!self.refs.length) {
                box.appendChild(el('span', 'small fg-secondary', t('no_records')));
            }
        },

        values: function () {
            var f = this.fields;
            var hours = parseFloat(String(f.estimate.value).replace(',', '.'));
            var data = {
                title: f.title.value.trim(),
                description: f.description.dirty() ? f.description.value() : f.descriptionStart,
                start_date: f.start.value,
                due_date: f.due.value,
                estimate_minutes: isNaN(hours) ? 0 : Math.round(hours * 60),
                priority: f.priority.value,
                department_id: parseInt(f.department.value, 10) || 0,
                assignees: f.people.value(),
                refs: this.refs.map(function (ref) { return ref.token || ('<#' + ref.type + ':' + ref.id + '>'); })
            };

            if (f.channel) {
                data.channel_id = parseInt(f.channel.value, 10) || 0;

                if (this.defaults && this.defaults.thread_id) {
                    data.thread_id = this.defaults.thread_id;
                }
            }

            if (f.remind) {
                var remind = f.remind.value();

                Object.keys(remind).forEach(function (key) {
                    data[key] = remind[key];
                });
            }

            // Nothing to send from an earlier copy of a series: its repeat
            // is changed on the newest one.
            var repeat = f.repeat ? f.repeat.value() : null;

            if (repeat) {
                data.recurrence = repeat;
            }

            if (this.task) {
                data.task_id = this.task.id;
            } else if (this.defaults && this.defaults.checklist_message_id) {
                data.checklist_message_id = this.defaults.checklist_message_id;
            }

            return data;
        },

        check: function () {
            var self = this;
            var data = self.values();

            api('ws_task_check', data).then(function (result) {
                var box = clear(self.fields.warnings);

                if (result.check.items.length) {
                    box.appendChild(warningList(result.check, function (suggestion) {
                        var ids = self.fields.people.value();

                        if (ids.indexOf(suggestion.user_id) === -1) {
                            ids.push(suggestion.user_id);
                            self.fields.people.set(ids);
                            self.check();
                        }
                    }));
                }
            }).catch(function () {});
        },

        save: function (force) {
            var self = this;
            var data = self.values();

            if (data.title === '') {
                self.fields.title.focus();
                toast(t('title_required'), 'warning');
                return;
            }

            data.force = force ? 1 : 0;

            api('ws_task_save', data).then(function (result) {
                if (result.needs_confirm) {
                    var hard = result.check.hard;

                    if (hard && !result.check.may_override) {
                        ask(t('leave_blocks'), t('ok'), false, warningList(result.check));
                        return;
                    }

                    ask(t('save_despite'), t('save_anyway'), true, warningList(result.check)).then(function (yes) {
                        if (yes) {
                            self.save(true);
                        }
                    });
                    return;
                }

                hideOffcanvas(self.node);
                toast(self.task ? t('task_saved') : t('task_created'), 'success');

                if (self.onSaved) {
                    self.onSaved(result.task_id);
                }
            }).catch(function (error) {
                fail(error);

                if (error.field && self.fields[error.field === 'assignees' ? 'people' : error.field]) {
                    var field = self.fields[error.field === 'assignees' ? 'people' : error.field];

                    if (field.focus) {
                        field.focus();
                    }
                }
            });
        },

        // The work box: how far the checklist has got, the task's own list
        // and the channel list it carries; then the notes.
        drawWork: function (task) {
            var self = this;

            self.task = task;

            // A note that called an assistant started a run.
            if (self.delegatePart && task.delegation) {
                self.delegatePart.update(task.delegation);
            }

            if (self.workBox) {
                var box = clear(self.workBox);
                var list = task.checklist || {};
                var progress = list.progress;

                if (progress) {
                    var head = el('div', 'ws-task-work-head');
                    head.appendChild(el('span', 'fw-semibold', t('progress')));
                    head.appendChild(el('span', 'ws-grow'));
                    head.appendChild(el('span', 'fg-secondary', t('progress_text', progress.done, progress.total, progress.percent)));
                    box.appendChild(head);
                    box.appendChild(progressBar(progress));

                    if (progress.complete && task.open && task.can_edit) {
                        var hint = el('div', 'ws-task-work-done');
                        hint.appendChild(icon('bi-check2-all', 'me-1'));
                        hint.appendChild(el('span', 'ws-grow', t('all_items_done')));

                        var finish = button('btn btn-sm btn-solid theme-success rounded-pill px-5', t('mark_done'), 'bi-check2-circle');
                        finish.addEventListener('click', function () { self.setStatus('done'); });
                        hint.appendChild(finish);
                        box.appendChild(hint);
                    }
                }

                if (list.message) {
                    var part = el('div', 'ws-task-list');
                    var caption = el('div', 'ws-task-list-head');

                    caption.appendChild(icon('bi-chat-left-text', 'me-1'));

                    if (list.message.url) {
                        caption.appendChild(el('span', 'ws-grow', t('channel_list', list.message.channel, list.message.author)));

                        var goto = el('a', 'small', t('go_to_message'));
                        goto.href = list.message.url;
                        caption.appendChild(goto);
                    } else {
                        caption.appendChild(el('span', 'ws-grow', t('list_hidden')));
                    }

                    part.appendChild(caption);

                    if (list.message.html) {
                        var items = el('div', 'ws-msg-body');
                        setHtml(items, list.message.html);
                        chipMenus(items);
                        self.bindTicks(items, 'message', list.message.id);
                        part.appendChild(items);
                    }

                    box.appendChild(part);
                }

                if (list.own_html) {
                    var own = el('div', 'ws-task-list');
                    var ownHead = el('div', 'ws-task-list-head');

                    ownHead.appendChild(icon('bi-list-check', 'me-1'));
                    ownHead.appendChild(el('span', 'ws-grow', t('task_own_list')));
                    own.appendChild(ownHead);

                    var ownItems = el('div', 'ws-msg-body');
                    setHtml(ownItems, list.own_html);
                    chipMenus(ownItems);
                    self.bindTicks(ownItems, 'task', task.id);
                    own.appendChild(ownItems);

                    if (task.can_edit) {
                        own.appendChild(self.itemAdder(task));
                    }

                    box.appendChild(own);
                }

                box.classList.toggle('d-none', !box.firstChild);
            }

            if (self.notesBox) {
                self.drawNotes(task);
            }
        },

        // One more item for the task's own list, written straight into it.
        // A description changed in the form and not saved yet is saved
        // first, so neither overwrites the other.
        itemAdder: function (task) {
            var self = this;
            var row = el('div', 'ws-item-add input-group input-group-sm');
            var input = el('input', 'form-control');
            var add = button('btn btn-outline theme-secondary', '', 'bi-plus-lg', t('add_item'));

            input.type = 'text';
            input.maxLength = 500;
            input.placeholder = t('add_item_placeholder');
            input.setAttribute('aria-label', t('add_item'));

            function submit() {
                var text = input.value.trim();
                var field = self.fields && self.fields.description;

                if (text === '') {
                    input.focus();
                    return;
                }

                if (field && field.dirty()) {
                    toast(t('save_description_first'), 'warning');
                    return;
                }

                input.disabled = true;
                add.disabled = true;

                api('ws_task_item_add', { task_id: task.id, text: text }).then(function (data) {
                    if (field) {
                        field.set(data.task.description, data.task.description_labels);
                        self.fields.descriptionStart = data.task.description;
                    }

                    self.drawWork(data.task);
                    self.changed();

                    var again = self.workBox && self.workBox.querySelector('.ws-item-add input');

                    if (again) {
                        again.focus();
                    }
                }).catch(function (error) {
                    input.disabled = false;
                    add.disabled = false;
                    fail(error);
                });
            }

            input.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter') && !event.isComposing) {
                    event.preventDefault();
                    submit();
                }
            });
            add.addEventListener('click', submit);

            row.appendChild(input);
            row.appendChild(add);

            return row;
        },

        bindTicks: function (container, kind, id) {
            var self = this;

            Array.prototype.forEach.call(container.querySelectorAll('input[data-ws-check]'), function (input) {
                input.addEventListener('change', function () {
                    var item = parseInt(input.getAttribute('data-ws-check'), 10);
                    var checked = input.checked ? 1 : 0;
                    var request = (kind === 'task')
                        ? api('ws_task_tick', { task_id: id, item: item, checked: checked })
                        : api('ws_check', { message_id: id, item: item, checked: checked }).then(function () {
                            return api('ws_task_get', { task_id: self.task.id });
                        });

                    input.disabled = true;

                    request.then(function (data) {
                        self.drawWork(data.task);
                        self.changed();
                    }).catch(function (error) {
                        input.checked = !input.checked;
                        input.disabled = false;
                        fail(error);
                    });
                });
            });
        },

        // Something on the task changed without the form being saved: the
        // list or card behind the drawer draws it again.
        changed: function () {
            if (this.onSaved && this.task) {
                this.onSaved(this.task.id);
            }
        },

        drawNotes: function (task) {
            var self = this;
            var box = clear(self.notesBox);
            var head = el('div', 'ws-task-notes-head');

            head.appendChild(icon('bi-journal-text', 'me-1'));
            head.appendChild(el('span', 'fw-semibold', t('notes')));

            if (task.notes.length) {
                head.appendChild(el('span', 'badge theme-secondary ms-3', String(task.notes.length)));
            }

            box.appendChild(head);

            var list = el('div', 'ws-task-note-list');

            if (!task.notes.length) {
                list.appendChild(el('div', 'small fg-secondary', t('no_notes')));
            }

            task.notes.forEach(function (note) {
                list.appendChild(self.noteNode(note));
            });

            box.appendChild(list);
            box.appendChild(self.noteComposer(null));
        },

        noteNode: function (note) {
            var self = this;
            var row = el('div', 'ws-task-note');
            var face = avatar(note.sender);
            var main = el('div', 'ws-task-note-main');
            var meta = el('div', 'ws-task-note-meta');
            var text = el('div', 'ws-msg-body');

            row.setAttribute('data-ws-note', note.id);
            face.classList.add('ws-task-note-avatar');
            row.appendChild(face);

            meta.appendChild(el('b', '', note.sender ? note.sender.name : ''));
            meta.appendChild(el('span', '', note.time));

            if (note.edited) {
                meta.appendChild(el('span', '', t('edited')));
            }

            if (note.can_edit || note.can_delete) {
                var tools = el('span', 'ws-task-note-tools');

                if (note.can_edit) {
                    var edit = button('btn btn-sm btn-text theme-secondary py-0 px-1', '', 'bi-pencil', t('edit'));
                    edit.addEventListener('click', function () {
                        main.replaceChild(self.noteComposer(note), text);
                        tools.remove();
                    });
                    tools.appendChild(edit);
                }

                if (note.can_delete) {
                    var remove = button('btn btn-sm btn-text theme-secondary py-0 px-1 ws-tool-danger', '', 'bi-trash', t('delete'));
                    remove.addEventListener('click', function () {
                        ask(t('delete_note_confirm'), t('delete'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            api('ws_task_note_delete', { note_id: note.id }).then(function (data) {
                                self.drawWork(data.task);
                                self.changed();
                            }).catch(fail);
                        });
                    });
                    tools.appendChild(remove);
                }

                meta.appendChild(tools);
            }

            main.appendChild(meta);
            setHtml(text, note.html);
            chipMenus(text);
            main.appendChild(text);
            row.appendChild(main);

            return row;
        },

        // The box that writes a note, or changes one.
        noteComposer: function (note) {
            var self = this;
            var wrap = el('div', 'ws-note-compose');
            var actions = el('div', 'ws-note-actions');

            // The formatted box, like the channel's; Ctrl+Enter saves.
            var rich = richField(wrap, {
                placeholder: t('note_placeholder'),
                onKeydown: function (event) {
                    if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey) && !event.isComposing) {
                        event.preventDefault();
                        submit();
                        return true;
                    }

                    return false;
                }
            });

            if (rich) {
                rich.input.setAttribute('aria-label', note ? t('edit') : t('add_note'));
                wrap.appendChild(rich.input);

                if (note) {
                    rich.set(note.raw || '', note.labels || {});
                }

                actions = rich.tools(false);
                actions.appendChild(el('span', 'ws-grow'));
            }

            var input = rich ? rich.input : el('textarea', 'form-control form-control-sm');

            if (!rich) {
                input.rows = 2;
                input.maxLength = 4000;
                input.placeholder = t('note_placeholder');
                input.setAttribute('aria-label', note ? t('edit') : t('add_note'));
                wrap.appendChild(input);
            }

            var field = rich || tokenField(input, wrap);

            // A new note can ask an assistant or an MCP server's alias about
            // the task; a changed one asks nobody.
            field.asksAssistants = !note;

            if (note && !rich) {
                var value = note.raw || '';

                Object.keys(note.labels || {}).forEach(function (token) {
                    var shown = shownLabel(token.charAt(1), note.labels[token]);

                    value = value.split(token).join(shown);
                    field.labels.push({ label: shown, token: token });
                });

                input.value = value;
            }

            if (!rich) {
                var mention = button('btn btn-sm btn-text theme-secondary', '', 'bi-at', t('mention'));
                mention.addEventListener('click', function () { field.insertTrigger('@'); });
                actions.appendChild(mention);

                var tag = button('btn btn-sm btn-text theme-secondary', '', 'bi-hash', t('tag_record'));
                tag.addEventListener('click', function () { field.insertTrigger('#'); });
                actions.appendChild(tag);

                actions.appendChild(el('span', 'ws-grow'));
            }

            if (note) {
                var cancel = button('btn btn-sm btn-ghost', t('cancel'));
                cancel.addEventListener('click', function () { self.drawNotes(self.task); });
                actions.appendChild(cancel);
            }

            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', note ? t('save') : t('add_note'), note ? 'bi-check2' : 'bi-plus-lg');

            function submit() {
                var body = (rich ? rich.value() : field.tokens(input.value)).trim();

                if (body === '') {
                    input.focus();
                    return;
                }

                save.disabled = true;

                api(note ? 'ws_task_note_edit' : 'ws_task_note_add', note ? { note_id: note.id, body: body } : { task_id: self.task.id, body: body }).then(function (data) {
                    self.drawWork(data.task);
                    self.changed();

                    // The note was kept, but the assistant it called could
                    // not be asked: why.
                    if (data.notice) {
                        toast(data.notice, 'warning');
                    }
                }).catch(function (error) {
                    save.disabled = false;
                    fail(error);
                });
            }

            save.addEventListener('click', submit);

            // Ctrl+Enter saves; a plain Enter is a new line.
            if (!rich) {
                input.addEventListener('keydown', function (event) {
                    if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey) && !event.defaultPrevented) {
                        event.preventDefault();
                        submit();
                    }
                });
            }

            actions.appendChild(save);
            wrap.appendChild(actions);

            if (note) {
                setTimeout(function () {
                    field.autosize();
                    input.focus();

                    if (rich) {
                        rich.rich.caretToEnd();
                    }
                }, 30);
            }

            return wrap;
        },

        setStatus: function (status) {
            var self = this;

            // Started while it waits for open tasks: asked, not refused.
            if ((status === 'doing') && window.PGWsTaskLinks && self.task.blocked_by && self.task.blocked_by.length && !self.startAnyway) {
                window.PGWsTaskLinks.confirmStart(self.task.blocked_by, partHelpers()).then(function (yes) {
                    if (yes) {
                        self.startAnyway = true;
                        self.setStatus(status);
                    }
                });
                return;
            }

            self.startAnyway = false;

            api('ws_task_status', { task_id: self.task.id, status: status }).then(function () {
                toast(t('task_saved'), 'success');
                self.open(self.task.id, null, self.onSaved);

                if (self.onSaved) {
                    self.onSaved(self.task.id);
                }
            }).catch(fail);
        }
    };

    // ── The channel form (create and edit) ─────────────────────────────

    // A colour from the palette (includes/workspace/groups.php), or none.
    function colorPicker(value) {
        var box = el('div', 'ws-swatches');
        var current = parseInt(value, 10) || 0;
        var swatches = [];

        box.setAttribute('role', 'radiogroup');

        [{ id: 0, hex: '', name: t('color_none') }].concat(CFG.palette || []).forEach(function (color) {
            var swatch = el('button', 'ws-swatch' + (color.id ? '' : ' ws-swatch-none'));
            swatch.type = 'button';
            swatch.title = color.name;
            swatch.setAttribute('role', 'radio');
            swatch.setAttribute('aria-label', color.name);

            if (color.hex) {
                swatch.style.background = color.hex;
            } else {
                swatch.appendChild(icon('bi-slash-circle'));
            }

            swatch.addEventListener('click', function () {
                current = color.id;
                mark();
            });

            swatches.push({ node: swatch, id: color.id });
            box.appendChild(swatch);
        });

        function mark() {
            swatches.forEach(function (item) {
                item.node.classList.toggle('active', item.id === current);
                item.node.setAttribute('aria-checked', item.id === current ? 'true' : 'false');
            });
        }

        mark();

        box.value = function () {
            return current;
        };

        box.set = function (value) {
            current = parseInt(value, 10) || 0;
            mark();
        };

        return box;
    }

    // An icon of the channel's own from the curated list
    // (includes/workspace/channel_icons.php), or none: then the icon its
    // kind gives, which the first cell shows. A radiogroup: Tab reaches the
    // chosen icon, the arrow keys, Home and End move through the ones the
    // search leaves, and Enter in the search takes the first of them.
    function iconPicker(value, kindIcon) {
        var box = el('div', 'ws-icon-picker');
        var search = el('input', 'form-control form-control-sm ws-icon-search');
        var grid = el('div', 'ws-icon-grid');
        var empty = el('div', 'ws-icon-empty d-none', t('icon_nothing'));
        var current = value || '';
        var cells = [];

        search.type = 'search';
        search.placeholder = t('icon_search');
        search.setAttribute('aria-label', t('icon_search'));
        search.autocomplete = 'off';
        search.spellcheck = false;

        grid.setAttribute('role', 'radiogroup');
        grid.setAttribute('aria-label', t('icon'));

        [{ icon: '', label: t('icon_none'), words: '' }].concat(CFG.channel_icons || []).forEach(function (choice) {
            var cell = el('button', 'ws-icon-choice' + (choice.icon ? '' : ' ws-icon-choice-none'));

            cell.type = 'button';
            cell.title = choice.label;
            cell.setAttribute('role', 'radio');
            cell.setAttribute('aria-label', choice.label);
            cell.appendChild(icon(choice.icon || kindIcon || 'bi-hash'));
            cell.addEventListener('click', function () {
                choose(choice.icon, false);
            });

            cells.push({ node: cell, icon: choice.icon, words: ' ' + foldText(choice.label + ' ' + choice.words).replace(/[\s,]+/g, ' ') });
            grid.appendChild(cell);
        });

        function shown() {
            return cells.filter(function (cell) {
                return !cell.node.classList.contains('d-none');
            });
        }

        // Only one cell is in the Tab order: the chosen one, or the first
        // shown when the search hides it.
        function mark() {
            var visible = shown();
            var reachable = null;

            cells.forEach(function (cell) {
                var on = (cell.icon === current);

                cell.node.classList.toggle('active', on);
                cell.node.setAttribute('aria-checked', on ? 'true' : 'false');

                if (on && !cell.node.classList.contains('d-none')) {
                    reachable = cell;
                }
            });

            reachable = reachable || visible[0] || null;

            cells.forEach(function (cell) {
                cell.node.tabIndex = (cell === reachable) ? 0 : -1;
            });
        }

        function choose(name, focus) {
            current = name || '';
            mark();

            if (focus) {
                cells.forEach(function (cell) {
                    if (cell.icon === current) {
                        cell.node.focus();
                    }
                });
            }
        }

        // Every word typed must start a word of the icon, in either language.
        search.addEventListener('input', function () {
            var words = foldText(search.value).split(/[\s,]+/).filter(Boolean);
            var found = 0;

            cells.forEach(function (cell) {
                var keep = !cell.icon || words.every(function (word) {
                    return cell.words.indexOf(' ' + word) !== -1;
                });

                cell.node.classList.toggle('d-none', !keep);
                found += (keep && cell.icon) ? 1 : 0;
            });

            empty.classList.toggle('d-none', found > 0);
            mark();
        });

        search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var first = shown().filter(function (cell) { return cell.icon; })[0];

            if (search.value.trim() && first) {
                choose(first.icon, true);
            }
        });

        grid.addEventListener('keydown', function (event) {
            var visible = shown();
            var at = -1;

            visible.forEach(function (cell, index) {
                if (cell.node === document.activeElement) {
                    at = index;
                }
            });

            if ((at === -1) || !visible.length) {
                return;
            }

            // As many cells as share the first one's line make a row.
            var columns = visible.filter(function (cell) {
                return cell.node.offsetTop === visible[0].node.offsetTop;
            }).length || 1;
            var moves = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: columns, ArrowUp: -columns };
            var next;

            if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = visible.length - 1;
            } else if (moves[event.key]) {
                next = Math.max(0, Math.min(visible.length - 1, at + moves[event.key]));
            } else {
                return;
            }

            event.preventDefault();
            choose(visible[next].icon, true);
        });

        box.appendChild(search);
        box.appendChild(grid);
        box.appendChild(empty);
        mark();

        box.value = function () {
            return current;
        };

        // The icon the kind gives, drawn on "none": a new channel's changes
        // with the kind chosen above it.
        box.setKindIcon = function (name) {
            var none = cells[0].node;

            clear(none);
            none.appendChild(icon(name || 'bi-hash'));
        };

        return box;
    }

    // The versions of a channel's conversation (includes/workspace/eras.php):
    // the current one, the earlier ones to read, and clearing it for staff.
    function eraSection(channel, node) {
        var box = el('div', 'mb-5 ws-era-section');
        var list = el('div', 'ws-era-list');

        box.appendChild(el('div', 'form-label', t('era_list')));
        list.appendChild(skeleton(2, 'ws-skeleton-sm'));
        box.appendChild(list);

        function draw(data) {
            clear(list);

            var current = el('div', 'ws-era-row ws-era-current');
            var currentText = el('div', 'flex-grow-1 min-w-0');
            current.appendChild(icon('bi-chat-square-text', 'fg-secondary'));
            currentText.appendChild(el('div', 'fw-semibold', t('era_current')));

            if (data.since_text) {
                currentText.appendChild(el('div', 'small fg-secondary', t('era_current_since', data.since_text)));
            }

            current.appendChild(currentText);
            list.appendChild(current);

            if (!data.eras.length) {
                list.appendChild(el('div', 'small fg-secondary mb-3', t('era_none')));
            }

            data.eras.forEach(function (era) {
                var row = el('div', 'ws-era-row');
                var info = el('div', 'flex-grow-1 min-w-0');

                row.appendChild(icon('bi-clock-history', 'fg-secondary'));
                info.appendChild(el('div', 'fw-semibold text-truncate', era.label));
                info.appendChild(el('div', 'small fg-secondary', t('era_meta', era.from_text, era.to_text, era.count, era.cleared_by || '—')));
                row.appendChild(info);

                if (data.can_clear) {
                    var rename = button('btn btn-sm btn-text theme-secondary', '', 'bi-pencil', t('era_rename'));
                    rename.addEventListener('click', function () {
                        var input = el('input', 'form-control form-control-sm');
                        input.maxLength = 160;
                        input.value = era.title || '';
                        input.placeholder = t('era_title');

                        ask(t('era_rename') + ': ' + era.label, t('save'), false, input).then(function (yes) {
                            if (yes) {
                                api('ws_channel_era_rename', { channel_id: channel.id, era_id: era.id, title: input.value }).then(load).catch(fail);
                            }
                        });
                    });
                    row.appendChild(rename);
                }

                var read = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('era_open'), 'bi-book');
                read.addEventListener('click', function () {
                    if (node) {
                        hideOffcanvas(node);
                    }

                    app.open(channel.id, 0, era.id);
                });
                row.appendChild(read);
                list.appendChild(row);
            });

            if (data.can_clear) {
                var clearBox = el('div', 'mt-3');
                var clearButton = button('btn btn-sm btn-outline theme-warning rounded-pill px-5', t('era_clear'), 'bi-eraser');
                clearButton.addEventListener('click', function () {
                    app.clearConversation(channel, function () {
                        if (node) {
                            hideOffcanvas(node);
                        }
                    });
                });
                clearBox.appendChild(clearButton);
                clearBox.appendChild(el('div', 'form-text', t('era_clear_help')));
                list.appendChild(clearBox);
            }
        }

        function load() {
            api('ws_channel_eras', { channel_id: channel.id }).then(draw).catch(function (error) {
                clear(list);
                list.appendChild(el('div', 'small fg-danger', error.message));
            });
        }

        load();

        return box;
    }

    function channelForm(channel, defaults, onDone) {
        var node = offcanvas('ws-channel-form', '');
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));

        footer.classList.remove('d-none');
        node.querySelector('.drawer-title').textContent = channel ? t('edit_channel') : t('new_channel');
        defaults = defaults || {};

        var name = el('input', 'form-control');
        name.id = nextId('ws-ch-name-');
        name.maxLength = 80;
        name.value = channel ? channel.name : (defaults.name || '');
        body.appendChild(formRow(t('channel_name'), name, t('channel_name_help')));

        var kind = null;

        if (!channel) {
            kind = el('div', 'mb-5');
            kind.appendChild(el('div', 'form-label', t('channel_kind')));

            [['public', t('kind_public'), t('kind_public_help')], ['private', t('kind_private'), t('kind_private_help')]].forEach(function (option, index) {
                var wrap = el('div', 'form-field');
                var radio = el('input', 'radio');
                radio.type = 'radio';
                radio.name = 'ws_channel_kind';
                radio.value = option[0];
                radio.id = nextId('ws-ch-kind-');
                radio.checked = (defaults.kind ? defaults.kind === option[0] : index === 0);
                var label = el('label', '', option[1]);
                label.htmlFor = radio.id;
                wrap.appendChild(radio);
                wrap.appendChild(label);
                wrap.appendChild(el('div', 'form-text mt-0', option[2]));
                kind.appendChild(wrap);
            });

            body.appendChild(kind);

            // A conversation with somebody who has no account is a room of
            // its own, made by its own form.
            if ((CFG.guests || {}).can_host && !defaults.note_id) {
                var toGuest = button('btn btn-link btn-sm p-0 mb-5 text-decoration-none', t('guest_start'), 'bi-door-open');

                toGuest.addEventListener('click', function () {
                    pgUI.drawer.on(node, 'hidden', function () {
                        guestForm(onDone);
                    }, { once: true });
                    hideOffcanvas(node);
                });
                body.appendChild(toGuest);
            }
        }

        var topic = el('input', 'form-control');
        topic.id = nextId('ws-ch-topic-');
        topic.maxLength = 255;
        topic.value = channel ? channel.topic : (defaults.topic || '');
        body.appendChild(formRow(t('channel_topic'), topic));

        // The customer the channel is about: a contact, a user or a current
        // account, with the records tied to it through its contact.
        var shownCustomer = channel && channel.customer ? channel.customer.customer : null;
        var customerType = shownCustomer ? shownCustomer.type : (defaults.customer_type || (defaults.contact_id ? 'contact' : ''));
        var customerId = shownCustomer ? shownCustomer.id : (defaults.customer_id || defaults.contact_id || 0);
        var customerRef = shownCustomer || (customerId ? {
            type: customerType,
            id: customerId,
            label: defaults.customer_label || defaults.contact_label || '',
            icon: (BOOT.ref_types[customerType] || {}).icon || 'bi-person-vcard'
        } : null);
        var customerLinks = channel && channel.customer ? channel.customer.links : null;
        var contactBox = el('div', 'mb-5');
        contactBox.appendChild(el('label', 'form-label', t('customer')));
        var contactShown = el('div', 'ws-refs-field mb-3');
        var linksShown = el('div', 'ws-customer-links');
        contactBox.appendChild(contactShown);
        contactBox.appendChild(linksShown);

        function drawLinks() {
            clear(linksShown);

            if (!customerId || !customerLinks) {
                return;
            }

            linksShown.appendChild(el('div', 'ws-customer-links-title', t('cust_links')));

            if (!customerLinks.length) {
                linksShown.appendChild(el('div', 'small fg-secondary', t('cust_links_none')));
                return;
            }

            customerLinks.forEach(function (link) {
                var row = el('div', 'ws-customer-link');
                var chip = row.appendChild(chipNode(link));

                // The form stays open: the record opens beside it.
                if (link.url) {
                    chip.target = '_blank';
                    chip.rel = 'noopener';
                }

                row.appendChild(el('span', 'small fg-secondary', link.kind));
                linksShown.appendChild(row);
            });
        }

        function drawContact() {
            clear(contactShown);

            if (customerId && customerRef) {
                contactShown.appendChild(chipNode({ type: customerRef.type, label: customerRef.label, icon: customerRef.icon }, function () {
                    customerType = '';
                    customerId = 0;
                    customerRef = null;
                    customerLinks = null;
                    drawContact();
                }));
            } else {
                contactShown.appendChild(el('span', 'small fg-secondary', t('no_customer')));
            }

            drawLinks();
        }

        function loadLinks() {
            if (!CFG.customer || !customerId) {
                return;
            }

            var asked = customerType + ':' + customerId;

            api('ws_customer_links', { type: customerType, id: customerId }).then(function (data) {
                if (asked === customerType + ':' + customerId) {
                    customerLinks = data.customer ? data.customer.links : [];
                    drawLinks();
                }
            }).catch(function () {});
        }

        drawContact();

        // Opened from a record's screen: its ties are asked for.
        if (!customerLinks && customerId) {
            loadLinks();
        }

        var customerKinds = {};

        (CFG.customer ? ['contact', 'user_account', 'erp_account'] : ['contact']).forEach(function (type) {
            if (BOOT.ref_types[type]) {
                customerKinds[type] = BOOT.ref_types[type];
            }
        });

        if (Object.keys(customerKinds).length) {
            contactBox.appendChild(refSearchField(customerKinds, function (item) {
                customerType = item.type;
                customerId = item.id;
                customerRef = { type: item.type, id: item.id, label: item.label, icon: item.icon };
                customerLinks = null;
                drawContact();
                loadLinks();
            }, (Object.keys(customerKinds).length > 1) ? t('cust_search') : t('search_contacts')));
        }

        contactBox.appendChild(el('div', 'form-text', CFG.customer ? t('cust_help') : t('customer_help')));
        body.appendChild(contactBox);

        var dept = select(departmentOptions(), channel && channel.department ? channel.department.id : (defaults.department_id || 0));
        dept.id = nextId('ws-ch-dept-');
        body.appendChild(formRow(t('department'), dept));

        // The channel's colour in the list, the same for everybody.
        var colors = null;

        if ((CFG.palette || []).length) {
            colors = colorPicker(channel ? channel.color : (defaults.color || 0));
            body.appendChild(formRow(t('color'), colors, t('color_help')));
        }

        // Its own icon beside the colour, the same for everybody; whoever may
        // rename the channel may choose it.
        var icons = null;

        if ((CFG.channel_icons || []).length) {
            icons = iconPicker(channel && channel.icon_chosen ? channel.icon : (defaults.icon || ''),
                channel ? channel.kind_icon : ((defaults.kind === 'private') ? 'bi-lock' : 'bi-hash'));
            body.appendChild(formRow(t('icon'), icons, t('icon_help')));

            // A new channel's "none" follows the kind picked above.
            if (kind) {
                kind.addEventListener('change', function () {
                    var picked = kind.querySelector('input:checked');

                    icons.setKindIcon((picked && (picked.value === 'private')) ? 'bi-lock' : 'bi-hash');
                });
            }
        }

        var members = null;

        if (!channel) {
            members = peoplePicker(defaults.members || []);
            body.appendChild(formRow(t('invite_people'), members));
        }

        // Made from a group's menu: it goes into that group.
        if (!channel && defaults.group_id && app.findGroup(defaults.group_id)) {
            body.insertBefore(el('div', 'alert theme-secondary small py-3', t('grp_new_channel_in', app.findGroup(defaults.group_id).name)), body.firstChild);
        }

        // Hearing about the records tied to the channel (workspace_events.js).
        var watch = (channel && window.PGWsEvents) ? window.PGWsEvents.watchField(channel) : null;

        if (watch) {
            body.appendChild(watch.node);
        }

        if (channel && CFG.eras) {
            body.appendChild(eraSection(channel, node));
        }

        // Opened from a note, the note is its first message.
        if (!channel && defaults.note_id) {
            body.insertBefore(el('div', 'alert theme-info small py-3', t('notes_channel_help')), body.firstChild);
        }

        // A new channel can be made from a template
        // (assets/js/workspace_templates.js): it suggests the fields above
        // and is applied once the channel is made.
        var template = null;

        if (!channel && !defaults.note_id && window.PGWsTemplates && CFG.templates && CFG.templates.ready) {
            template = window.PGWsTemplates.picker({ api: api }, function (picked) {
                window.PGWsTemplates.suggest(picked, { name: name, kind: kind, department: dept, colors: colors });
            });
            body.insertBefore(template.node, body.firstChild);
        }

        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', channel ? t('save') : t('create_channel'), 'bi-check2');
        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(cancel);
        footer.appendChild(save);

        save.addEventListener('click', function () {
            var data = {
                name: name.value,
                topic: topic.value,
                department_id: parseInt(dept.value, 10) || 0
            };

            if (CFG.customer) {
                data.customer_type = customerType;
                data.customer_id = customerId;
            } else {
                data.contact_id = (customerType === 'contact') ? customerId : 0;
            }

            if (colors) {
                data.color = colors.value();
            }

            if (icons) {
                data.icon = icons.value();
            }

            if (watch) {
                data.watch = watch.value();
            }

            if (!channel && defaults.group_id) {
                data.group_id = defaults.group_id;
            }

            var request;

            if (channel) {
                data.channel_id = channel.id;
                request = api('ws_channel_update', data);
            } else {
                var chosen = kind.querySelector('input:checked');
                data.kind = chosen ? chosen.value : 'public';
                data.members = members.value();

                if (defaults.note_id) {
                    data.note_id = defaults.note_id;
                    request = api('ws_note_channel', data);
                } else {
                    if (template && template.value()) {
                        data.template_id = template.value();
                    }

                    request = api('ws_channel_create', data);
                }
            }

            request.then(function (result) {
                hideOffcanvas(node);

                if (result.warning) {
                    toast(result.warning, 'warning');
                } else if (!defaults.note_id) {
                    toast(result.notice || (channel ? t('channel_saved') : t('channel_created')), 'success');
                }

                if (onDone) {
                    onDone(channel ? channel.id : result.channel_id);
                }
            }).catch(function (error) {
                fail(error);

                if (error.field === 'name') {
                    name.focus();
                }
            });
        });

        showOffcanvas(node);
        setTimeout(function () { name.focus(); }, 300);
    }

    // ── Talking with a guest (includes/workspace/guests.php) ────────────
    //
    // A room of its own for a conversation with somebody who has no account.
    // The guest comes in by a link the staff member sends them; its address
    // is shown once, when it is made.

    // How the guest's link opens: once, or until a time chosen here.
    function guestLinkFields(durations) {
        var box = el('div', 'mb-1');
        var group = nextId('ws_guest_mode_');
        var mode = 'once';

        box.appendChild(el('div', 'form-label', t('guest_mode')));

        var duration = select((durations || []).map(function (option) { return [option.v, option.t]; }), '604800');
        duration.id = nextId('ws-guest-duration-');

        var durationRow = el('div', 'ws-guest-duration d-none');
        var durationLabel = el('label', 'form-label small mb-1', t('guest_duration'));
        durationLabel.htmlFor = duration.id;
        durationRow.appendChild(durationLabel);
        durationRow.appendChild(duration);

        [['once', t('guest_mode_once')], ['timed', t('guest_mode_timed')]].forEach(function (option, index) {
            var wrap = el('div', 'form-field');
            var radio = el('input', 'radio');
            radio.type = 'radio';
            radio.name = group;
            radio.value = option[0];
            radio.id = nextId('ws-guest-mode-');
            radio.checked = (index === 0);
            radio.addEventListener('change', function () {
                mode = radio.value;
                durationRow.classList.toggle('d-none', mode !== 'timed');
            });

            var label = el('label', '', option[1]);
            label.htmlFor = radio.id;
            wrap.appendChild(radio);
            wrap.appendChild(label);
            box.appendChild(wrap);
        });

        box.appendChild(durationRow);

        box.value = function () {
            return { mode: mode, duration: (mode === 'timed') ? (parseInt(duration.value, 10) || 0) : 0 };
        };

        return box;
    }

    // The address of a new link, this once, with a way to copy it. qr is the
    // same address as SVG markup drawn by the server (pg_qr_svg()); nothing
    // else goes into that string, so it is inserted as it is.
    function guestLinkShow(url, qr) {
        // The question before it is still on its way out.
        if (dialog && askUp) {
            pgUI.dialog.on(dialog, 'hidden', function () {
                guestLinkShow(url, qr);
            }, { once: true });
            return;
        }

        var details = el('div');

        if (qr) {
            var qrRow = el('div', 'text-center mb-5');
            var qrBox = el('div', 'd-inline-block border rounded-4 p-3 bg-white');
            qrBox.style.width = '200px';
            qrBox.innerHTML = qr;
            qrRow.appendChild(qrBox);
            details.appendChild(qrRow);
        }

        var group = el('div', 'input-group');
        var field = el('input', 'form-control font-monospace');
        field.type = 'text';
        field.readOnly = true;
        field.value = url;
        field.setAttribute('aria-label', t('guest_link_help'));
        field.addEventListener('focus', function () { field.select(); });

        var copy = button('btn btn-outline theme-primary', t('guest_copy'), 'bi-clipboard');
        copy.addEventListener('click', function () {
            var done = function () {
                clear(copy);
                copy.appendChild(icon('bi-check2', 'me-1'));
                copy.appendChild(document.createTextNode(t('guest_copied')));
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done, function () { field.select(); });
            } else {
                field.select();
            }
        });

        group.appendChild(field);
        group.appendChild(copy);
        details.appendChild(group);

        ask(t('guest_link_help'), t('guest_link_done'), false, details);
    }

    function guestForm(onDone) {
        var conf = CFG.guests || {};
        var node = offcanvas('ws-guest-form', '');
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));

        footer.classList.remove('d-none');
        node.querySelector('.drawer-title').textContent = t('guest_start');

        body.appendChild(el('div', 'alert theme-info small py-3', t('guest_start_help')));

        var name = el('input', 'form-control');
        name.id = nextId('ws-guest-name-');
        name.maxLength = 60;
        name.autocomplete = 'off';
        body.appendChild(formRow(t('guest_name'), name));

        var topic = el('input', 'form-control');
        topic.id = nextId('ws-guest-topic-');
        topic.maxLength = 120;
        body.appendChild(formRow(t('guest_topic'), topic));

        var link = guestLinkFields(conf.durations);
        link.classList.add('mb-5');
        body.appendChild(link);

        // Staff only; the one who starts it is in it anyway.
        var staff = ((BOOT && BOOT.people) || []).filter(function (member) {
            return (parseInt(member.role, 10) <= 2) && (member.id !== CFG.me);
        }).map(function (member) { return member.id; });
        var members = peoplePicker([], staff);

        if (staff.length) {
            body.appendChild(formRow(t('guest_staff'), members));
        }

        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('guest_create'), 'bi-door-open');
        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(cancel);
        footer.appendChild(save);

        save.addEventListener('click', function () {
            var chosen = link.value();

            save.disabled = true;

            api('ws_guest_start', {
                guest_name: name.value,
                topic: topic.value,
                mode: chosen.mode,
                duration: chosen.duration,
                members: staff.length ? members.value() : []
            }).then(function (result) {
                save.disabled = false;
                hideOffcanvas(node);

                if (onDone) {
                    onDone(result.channel_id);
                }

                guestLinkShow(result.url, result.qr || '');
            }).catch(function (error) {
                save.disabled = false;
                fail(error);

                if (error.field === 'guest_name') {
                    name.focus();
                }
            });
        });

        showOffcanvas(node);
        setTimeout(function () { name.focus(); }, 300);
    }

    // A channel of the team shared with somebody outside it (guests.php):
    // who it is for, whether they only read, and how the link opens.
    function shareForm(channel, onDone) {
        var conf = CFG.guests || {};
        var node = offcanvas('ws-share-form', '');
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));
        var access = 'read';
        var group = nextId('ws_share_access_');

        footer.classList.remove('d-none');
        node.querySelector('.drawer-title').textContent = t('share_start') + ' · #' + channel.name;
        body.appendChild(el('div', 'alert theme-info small py-3', t('share_help')));

        var name = el('input', 'form-control');
        name.id = nextId('ws-share-name-');
        name.maxLength = 60;
        name.autocomplete = 'off';
        body.appendChild(formRow(t('guest_name'), name));

        var choices = el('div', 'mb-5');
        choices.appendChild(el('div', 'form-label', t('share_access')));

        [['read', t('share_read'), t('share_read_help'), 'bi-eye'], ['write', t('share_write'), t('share_write_help'), 'bi-chat-dots']].forEach(function (option) {
            var wrap = el('div', 'form-field ws-share-choice');
            var radio = el('input', 'radio');
            radio.type = 'radio';
            radio.name = group;
            radio.value = option[0];
            radio.id = nextId('ws-share-access-');
            radio.checked = (option[0] === access);
            radio.addEventListener('change', function () { access = radio.value; });

            var label = el('label', '');
            label.htmlFor = radio.id;
            label.appendChild(icon(option[3], 'me-1'));
            label.appendChild(document.createTextNode(option[1]));
            wrap.appendChild(radio);
            wrap.appendChild(label);
            wrap.appendChild(el('div', 'form-text mt-0', option[2]));
            choices.appendChild(wrap);
        });

        body.appendChild(choices);

        var link = guestLinkFields(conf.durations);
        body.appendChild(link);

        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('share_create'), 'bi-link-45deg');
        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(cancel);
        footer.appendChild(save);

        save.addEventListener('click', function () {
            var chosen = link.value();

            save.disabled = true;

            api('ws_channel_share', {
                channel_id: channel.id,
                guest_name: name.value,
                access: access,
                mode: chosen.mode,
                duration: chosen.duration
            }).then(function (result) {
                save.disabled = false;
                hideOffcanvas(node);

                if (onDone) {
                    onDone();
                }

                guestLinkShow(result.url, result.qr || '');
            }).catch(function (error) {
                save.disabled = false;
                fail(error);

                if (error.field === 'guest_name') {
                    name.focus();
                }
            });
        });

        showOffcanvas(node);
        setTimeout(function () { name.focus(); }, 300);
    }

    // ── The inbox ──────────────────────────────────────────────────────

    function openInbox(onChange) {
        var node = offcanvas('ws-inbox', t('inbox'));
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));

        footer.classList.remove('d-none');

        var all = button('btn btn-sm btn-text theme-secondary', t('mark_all_read'), 'bi-check2-all');
        all.addEventListener('click', function () {
            api('ws_inbox_read', { ids: 'all' }).then(function () {
                hideOffcanvas(node);

                if (onChange) {
                    onChange();
                }
            }).catch(fail);
        });
        footer.appendChild(all);

        body.appendChild(skeleton(3));
        showOffcanvas(node);

        api('ws_inbox').then(function (data) {
            clear(body);

            if (!data.items.length) {
                body.appendChild(emptyState('inbox', t('inbox_empty')));
                return;
            }

            data.items.forEach(function (item) {
                var row = el('a', 'ws-record-item' + (item.unread ? ' fw-semibold' : ''));
                row.href = item.url;

                var head = el('div', 'd-flex gap-3 align-items-center');
                head.appendChild(icon(item.icon || 'bi-bell'));
                head.appendChild(el('span', 'flex-grow-1', item.title));
                head.appendChild(el('small', 'fg-secondary', item.time));
                row.appendChild(head);

                if (item.body) {
                    row.appendChild(el('small', '', item.body));
                }

                // A meeting about to start: its call, one tap away.
                if (item.join) {
                    var join = el('span', 'btn btn-sm btn-solid theme-primary rounded-pill align-self-start mt-1', t('meeting_join'));
                    join.insertBefore(icon('bi-camera-video', 'me-1'), join.firstChild);
                    join.setAttribute('role', 'button');
                    join.addEventListener('click', function (clicked) {
                        clicked.preventDefault();
                        clicked.stopPropagation();
                        api('ws_inbox_read', { ids: [item.id] }).catch(function () {});
                        joinMeeting(item.join);
                    });
                    row.appendChild(join);
                }

                row.addEventListener('click', function () {
                    api('ws_inbox_read', { ids: [item.id] }).catch(function () {});
                });

                body.appendChild(row);
            });
        }).catch(fail);
    }

    // A card of the overview: a title, a link to the whole list, a body.
    function homeCard(title, iconName, moreUrl, moreLabel) {
        var node = el('section', 'card ws-home-card');
        var head = el('div', 'card-header d-flex align-items-center gap-3');
        var heading = el('h3', 'h6 mb-0 flex-grow-1');
        heading.appendChild(icon(iconName, 'me-1'));
        heading.appendChild(document.createTextNode(title));
        head.appendChild(heading);

        if (moreUrl) {
            var more = el('a', 'small text-decoration-none text-nowrap', moreLabel);
            more.href = moreUrl;
            more.appendChild(icon('bi-arrow-right-short'));
            head.appendChild(more);
        }

        node.appendChild(head);

        var body = el('div', 'card-body p-3');
        node.appendChild(body);

        return { node: node, body: body };
    }

    // What the feature files draw with (workspace_approvals.js,
    // workspace_acks.js, workspace_voice.js): the screen's own helpers, so
    // their parts look like the rest of it.
    function featureHelpers() {
        return {
            api: api, t: t, el: el, icon: icon, button: button, avatar: avatar, avatarStack: avatarStack, clear: clear,
            offcanvas: offcanvas, showOffcanvas: showOffcanvas, hideOffcanvas: hideOffcanvas,
            formRow: formRow, select: select, nextId: nextId, ask: ask, fail: fail, toast: toast,
            richField: richField, peoplePicker: peoplePicker, homeCard: homeCard, touchScreen: touchScreen,
            saLabelled: saLabelled, saText: saText, saChannelSelect: saChannelSelect, cfg: CFG,
            openTask: function (taskId) { taskDrawer.open(taskId, null, taskDrawer.onSaved); }
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    // Channels screen
    // ═══════════════════════════════════════════════════════════════════

    var app = {
        root: null,
        side: null,
        center: null,
        channel: null,
        messages: [],
        lastId: 0,
        sinceTs: 0,
        tab: 'messages',
        reply: null,
        editing: null,
        labels: [],
        pending: null,
        timer: null,
        busy: false,
        // An earlier version of the conversation open on the screen
        // (includes/workspace/eras.php), or null for the current one.
        era: null,
        // Every discussion listed in the sidebar, not only the first few.
        threadsAll: false,
        fit: function () {},

        start: function (root) {
            var self = this;

            self.root = root;
            root.classList.add('ws-app');
            root.innerHTML = '';

            self.side = el('aside', 'ws-side');
            self.center = el('section', 'ws-center');
            root.appendChild(self.side);
            root.appendChild(self.center);
            self.bindDrop();
            self.bindFit();

            self.drawSide();

            var query = params();
            var target = parseInt(query.channel || CFG.open_channel || 0, 10);

            // Straight into the first pinned channel; without one, or when it
            // is asked for, the overview.
            if (!target && query.view !== 'home' && query.view !== 'scheduled') {
                var pinned = BOOT.channels.filter(function (channel) { return channel.joined && channel.pinned; });
                target = pinned.length ? pinned[0].id : 0;
            }

            // The channel's board asked for in the address (?view=board).
            if (target && (query.view === 'board')) {
                self.pendingTab = 'board';
            }

            // A discussion asked for in the address opens beside its channel.
            if (target && query.thread) {
                self.pendingThread = { id: parseInt(query.thread, 10), message: 0 };
            }

            if (target) {
                self.open(target, parseInt(query.message || 0, 10), parseInt(query.era || 0, 10));
            } else if ((query.view === 'scheduled') && (CFG.scheduled || CFG.scheduled_messages)) {
                self.showScheduled(false, (query.part === 'messages') ? 'messages' : '');
            } else {
                self.showHome(false);
            }

            // Back from connecting an account to an MCP server, or asked by a
            // link in a message to offer it (mcp_ask.php).
            if (CFG.mcp && CFG.mcp.flash) {
                toast(CFG.mcp.flash.text, (CFG.mcp.flash.kind === 'error') ? 'danger' : 'success');
            }

            if (CFG.mcp && CFG.mcp.connect) {
                ask(t('mcp_connect_ask', CFG.mcp.connect.alias), t('mcp_connect_me')).then(function (yes) {
                    if (yes) {
                        mcpConnect(CFG.mcp.connect.server_id, target);
                    }
                });
            }

            // What the rail of the other screens links to.
            if (query.inbox) {
                openInbox(function () { self.sync(); });
            } else if (query.panel === 'archived') {
                self.showArchived();
            } else if ((query.panel === 'audit') && BOOT.me.rights.staff) {
                self.showAudit();
            }

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    self.sync();
                }

                self.schedule();
            });

            window.addEventListener('focus', function () { self.sync(); });

            document.addEventListener('click', function (event) {
                var statusButton = event.target.closest('[data-ws-task-status]');
                var openButton = event.target.closest('[data-ws-task-open]');

                if (statusButton && self.root.contains(statusButton)) {
                    var taskId = parseInt(statusButton.getAttribute('data-ws-task'), 10);
                    var blocked = statusButton.getAttribute('data-ws-blocked') || '';

                    // A card that waits for open tasks asks before it starts.
                    (window.PGWsTaskLinks ? window.PGWsTaskLinks.confirmStart(blocked, partHelpers()) : Promise.resolve(true)).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        api('ws_task_status', { task_id: taskId, status: statusButton.getAttribute('data-ws-task-status') }).then(function (data) {
                            self.replaceTaskCard(taskId, data.html);
                            self.sync();
                        }).catch(fail);
                    });
                }

                if (openButton && self.root.contains(openButton)) {
                    taskDrawer.open(parseInt(openButton.getAttribute('data-ws-task-open'), 10), null, function () { self.sync(); });
                }
            });
        },

        // ── Sidebar ──

        drawSide: function () {
            var self = this;
            var before = self.side.querySelector('.ws-side-scroll');
            var beforeSearch = self.side.querySelector('.ws-side-head input[type="search"]');
            var keep = {
                top: before ? before.scrollTop : 0,
                search: beforeSearch ? beforeSearch.value : '',
                focused: !!(beforeSearch && document.activeElement === beforeSearch)
            };
            var side = clear(self.side);

            var head = el('div', 'ws-side-head');
            var search = el('input', 'form-control form-control-sm rounded-pill');
            search.type = 'search';
            search.placeholder = t('search_messages');
            search.setAttribute('aria-label', search.placeholder);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    self.search(search.value);
                }
            });
            head.appendChild(search);

            var add = button('btn btn-sm btn-solid theme-primary rounded-pill', '', 'bi-plus-lg', t('new_channel'));
            add.addEventListener('click', function () {
                channelForm(null, {}, function (id) { self.reloadChannels(id); });
            });

            // The workspace's own tour (includes/workspace/tour.php), played
            // again from here.
            if (window.PgTour && window.PG_TOUR && window.PG_TOUR.key) {
                var tour = button('btn btn-sm btn-text theme-secondary rounded-pill', '', 'bi-signpost-split', t('tour_start'));

                tour.setAttribute('data-ws-tour', '1');
                tour.addEventListener('click', function () {
                    // The tour points at links in these sections.
                    var shutParts = self.closedSections();

                    if (shutParts.work || shutParts.records) {
                        self.closeSection('work', false);
                        self.closeSection('records', false);
                        self.drawSide();
                    }

                    window.PgTour.start(window.PG_TOUR.key);
                });
                head.appendChild(tour);
            }

            // The search box of the whole workspace (workspace_palette.js).
            if (window.PGWsPalette) {
                var find = button('btn btn-sm btn-text theme-secondary rounded-pill', '', 'bi-search', t('pal_shortcut'));

                find.setAttribute('data-ws-palette', '1');
                head.appendChild(find);
            }

            head.appendChild(add);
            side.appendChild(head);

            var scroll = el('div', 'ws-side-scroll');
            side.appendChild(scroll);

            // Each part of the workspace has its own colour in the list; the
            // channels take theirs.
            var navKeys = {
                'bi-house': 'home', 'bi-inbox': 'inbox', 'bi-check2-square': 'tasks', 'bi-journal-text': 'notes',
                'bi-calendar-week': 'board', 'bi-calendar3': 'calendar', 'bi-alarm': 'scheduled', 'bi-clock-history': 'timeline',
                'bi-archive': 'archived', 'bi-shield-lock': 'audit', 'bi-sliders': 'settings', 'bi-diagram-3': 'flows'
            };

            function link(box, label, iconName, handler, badge, active) {
                var item = el('button', 'ws-channel-link' + (active ? ' active' : ''));
                item.type = 'button';

                if (navKeys[iconName]) {
                    item.setAttribute('data-ws-nav', navKeys[iconName]);
                }

                item.appendChild(icon(iconName));
                item.appendChild(el('span', 'ws-channel-name', label));

                if (badge) {
                    item.appendChild(el('span', 'ws-count ws-count-mention', badge));
                }

                item.addEventListener('click', function () {
                    self.root.classList.remove('ws-show-side');
                    handler();
                });
                box.appendChild(item);
            }

            var closedParts = self.closedSections();

            // A heading that opens and closes its section; closed, it still
            // says how much is new inside.
            function sectionToggle(head, key, title, iconName, shut, badge) {
                var toggle = el('button', 'ws-side-toggle');

                toggle.type = 'button';
                toggle.setAttribute('aria-expanded', shut ? 'false' : 'true');
                toggle.title = shut ? t('side_expand') : t('side_collapse');
                toggle.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-group-chevron'));

                if (iconName) {
                    toggle.appendChild(icon(iconName));
                }

                toggle.appendChild(el('span', 'ws-side-toggle-name', title));
                toggle.addEventListener('click', function () {
                    self.closeSection(key, !shut);
                    self.drawSide();
                });
                head.appendChild(toggle);

                if (shut && badge) {
                    head.appendChild(badge);
                }
            }

            function countBadge(count, mention) {
                return count ? el('span', 'ws-count' + (mention ? ' ws-count-mention' : ''), count > 99 ? '99+' : count) : null;
            }

            // What is new in a list of channels: the mentions when there are
            // any, otherwise the unread messages of the channels not muted.
            function channelsBadge(list) {
                var mentions = 0;
                var unread = 0;

                list.forEach(function (channel) {
                    mentions += channel.mentions || 0;

                    if (channel.notify !== 'none') {
                        unread += channel.unread || 0;
                    }
                });

                return mentions ? countBadge(mentions, true) : countBadge(unread, false);
            }

            // A section of links; null when it is closed and nothing goes in.
            function group(title, key) {
                var box = el('div', 'ws-side-section');
                var shut = !!(key && closedParts[key]);

                if (title && key) {
                    var head = el('div', 'ws-side-title');

                    sectionToggle(head, key, title, '', shut, null);
                    box.appendChild(head);
                } else if (title) {
                    box.appendChild(el('div', 'ws-side-title', title));
                }

                scroll.appendChild(box);
                return shut ? null : box;
            }

            var top = group('');
            link(top, t('home'), 'bi-house', function () { self.showHome(true); }, 0, self.view === 'home');
            link(top, t('inbox'), 'bi-inbox', function () {
                openInbox(function () { self.sync(); });
            }, BOOT.inbox || 0);

            // Groups of channels (includes/workspace/groups.php): a channel in
            // a group is listed under it, one group inside another; a pinned
            // channel stays pinned.
            var groups = BOOT.groups || [];
            var groupKnown = {};
            var manage = !!(CFG.groups && CFG.groups.manage);

            groups.forEach(function (group) { groupKnown[group.id] = group; });

            function inGroup(channel) {
                return !channel.pinned && channel.group_id && groupKnown[channel.group_id];
            }

            var joined = BOOT.channels.filter(function (channel) { return channel.joined; });
            var pinned = joined.filter(function (channel) { return channel.pinned; });
            var mine = joined.filter(function (channel) { return !channel.pinned && !inGroup(channel); });
            var others = BOOT.channels.filter(function (channel) { return !channel.joined && !inGroup(channel); });
            var byGroup = {};

            BOOT.channels.forEach(function (channel) {
                if (inGroup(channel)) {
                    (byGroup[channel.group_id] = byGroup[channel.group_id] || []).push(channel);
                }
            });

            function channelLink(channel, box, sortable) {
                var link = el('button', 'ws-channel-link'
                    + ((self.channel && self.channel.id === channel.id) ? ' active' : '')
                    + (channel.unread ? ' ws-unread' : '')
                    + (channel.joined ? '' : ' ws-notjoined'));
                link.type = 'button';
                link.setAttribute('data-ws-channel', channel.id);

                // The channel's own colour on its sign.
                if (channel.hex) {
                    link.style.setProperty('--ws-ch', channel.hex);
                    link.setAttribute('data-ws-colored', '1');
                }

                if (sortable) {
                    self.makeSortable(link, box, channel);
                } else if (manage) {
                    // Not in the person's own order, but staff can still drag
                    // it into a group.
                    self.makeDraggable(link, channel);
                }

                link.appendChild(channelIcon(channel, 'ws-ch-icon'));
                link.appendChild(el('span', 'ws-channel-name', channel.name));

                if (channel.notify === 'none') {
                    link.appendChild(icon('bi-bell-slash', 'fg-secondary'));
                }

                if (channel.mentions) {
                    link.appendChild(el('span', 'ws-count ws-count-mention', channel.mentions));
                } else if (channel.unread && channel.notify !== 'none') {
                    link.appendChild(el('span', 'ws-count', channel.unread > 99 ? '99+' : channel.unread));
                }

                // A pin at the end of the line, shown on hover (and always
                // on a pinned channel).
                if (channel.joined) {
                    var pin = el('span', 'ws-pin' + (channel.pinned ? ' ws-pinned' : ''));
                    pin.setAttribute('role', 'button');
                    pin.title = channel.pinned ? t('unpin') : t('pin');
                    pin.appendChild(icon(channel.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle'));
                    pin.addEventListener('click', function (event) {
                        event.stopPropagation();
                        self.pinChannel(channel, !channel.pinned);
                    });
                    link.appendChild(pin);
                }

                link.addEventListener('click', function () {
                    self.root.classList.remove('ws-show-side');
                    self.open(channel.id, 0);
                });

                onContext(link, function () { return self.channelActions(channel); });

                return link;
            }

            function section(title, list, sortable, iconName, dropOut, key) {
                var box = el('div', 'ws-side-section' + (sortable ? ' ws-sortable' : ''));
                var head = el('div', 'ws-side-title');
                var shut = !!closedParts[key];

                sectionToggle(head, key, title, iconName, shut, channelsBadge(list));
                box.appendChild(head);

                // A channel dropped on the heading comes out of its group.
                if (dropOut && manage) {
                    self.dropTarget(head, 0, null);
                }

                // Closed, a section still shows the one that is on the screen.
                if (shut) {
                    list.forEach(function (channel) {
                        if (self.channel && self.channel.id === channel.id) {
                            box.appendChild(channelLink(channel, box, false));
                        }
                    });
                } else {
                    list.forEach(function (channel) {
                        box.appendChild(channelLink(channel, box, sortable));
                    });

                    if (!list.length) {
                        box.appendChild(emptyState('channels', t('no_channels'), 'ws-empty-side'));
                    }
                }

                scroll.appendChild(box);
            }

            var closed = self.closedGroups();
            var children = self.groupTree();

            function groupBox(group, parent, depth) {
                var box = el('div', 'ws-side-group' + (depth ? ' ws-side-subgroup' : ''));
                var head = el('div', 'ws-side-title ws-group-title');
                var inner = el('div', 'ws-group-inner ws-sortable');
                var shut = !!closed[group.id];
                var toggleButton = el('button', 'ws-group-toggle');
                var channels = byGroup[group.id] || [];
                var kids = children[group.id] || [];

                box.setAttribute('data-ws-group-box', group.id);

                if (group.hex) {
                    box.style.setProperty('--ws-group', group.hex);
                }

                toggleButton.type = 'button';
                toggleButton.setAttribute('aria-expanded', shut ? 'false' : 'true');
                toggleButton.title = shut ? t('grp_expand') : t('grp_collapse');
                toggleButton.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-group-chevron'));
                toggleButton.appendChild(icon(shut ? 'bi-folder2' : 'bi-folder2-open', 'ws-group-icon'));
                toggleButton.appendChild(el('span', 'ws-group-name', group.name));

                // Closed, a group still says there is something new inside.
                var unread = self.groupUnread(group.id);

                if (shut && unread) {
                    toggleButton.appendChild(el('span', 'ws-count', unread > 99 ? '99+' : unread));
                }

                if (manage && group.access) {
                    var key = el('span', 'ws-group-access');
                    key.title = t('grp_access');
                    key.appendChild(icon('bi-key'));
                    key.appendChild(document.createTextNode(String(group.access)));
                    toggleButton.appendChild(key);
                }

                toggleButton.addEventListener('click', function () {
                    self.closeGroup(group.id, !shut);
                    self.drawSide();
                });
                head.appendChild(toggleButton);
                head.setAttribute('data-ws-group', group.id);

                if (manage) {
                    var more = button('btn btn-sm btn-text theme-secondary ws-group-more', '', 'bi-three-dots', t('more'));
                    more.addEventListener('click', function (event) {
                        event.stopPropagation();
                        var rect = more.getBoundingClientRect();
                        ctxMenu(rect.left, rect.bottom, self.groupActions(group));
                    });
                    head.appendChild(more);
                    onContext(head, function () { return self.groupActions(group); });
                    self.makeGroupDraggable(head, group);
                    self.dropTarget(head, group.id, group);
                }

                box.appendChild(head);

                if (!shut) {
                    channels.forEach(function (channel) {
                        inner.appendChild(channelLink(channel, inner, channel.joined));
                    });

                    kids.forEach(function (child) {
                        groupBox(child, inner, depth + 1);
                    });

                    if (!channels.length && !kids.length) {
                        var empty = el('div', 'small fg-secondary px-3 ws-group-empty', manage ? t('grp_empty') : t('no_channels'));

                        if (manage) {
                            self.dropTarget(empty, group.id, null);
                        }

                        inner.appendChild(empty);
                    }

                    box.appendChild(inner);
                }

                parent.appendChild(box);
            }

            if (pinned.length) {
                section(t('pinned'), pinned, true, 'bi-pin-angle', false, 'pinned');
            }

            // The open discussions the person is in (threads.php); none, no
            // section. The open one and those of the open channel come
            // first, then the unread ones, then the latest; a few are shown
            // until the rest are asked for.
            var THREADS_SHOWN = 5;
            var openThread = self.openThreadId();
            var hereId = self.channel ? self.channel.id : 0;
            var rank = function (item) {
                return (item.thread.id === openThread) ? 0 : ((item.thread.channel_id === hereId) ? 1 : (item.thread.unread ? 2 : 3));
            };
            var threads = (BOOT.threads || []).map(function (thread, index) {
                return { thread: thread, index: index };
            }).sort(function (a, b) {
                return (rank(a) - rank(b)) || (a.index - b.index);
            }).map(function (item) { return item.thread; });

            if (threads.length) {
                var talks = el('div', 'ws-side-section ws-side-threads');
                var talksHead = el('div', 'ws-side-title');
                // Closed, it lists none, not even the open one: that one has
                // its own panel beside the channel and opens again with the
                // address (&thread=), so keeping it left the section looking
                // open after every click and every reload.
                var talksShut = !!closedParts.threads;
                var talksUnread = threads.reduce(function (sum, thread) { return sum + (thread.unread || 0); }, 0);

                sectionToggle(talksHead, 'threads', t('th_section'), 'bi-chat-square-dots', talksShut, countBadge(talksUnread, false));
                talks.appendChild(talksHead);

                (talksShut ? [] : (self.threadsAll ? threads : threads.slice(0, THREADS_SHOWN))).forEach(function (thread) {
                    var item = el('button', 'ws-channel-link ws-thread-link' + (self.openThreadId() === thread.id ? ' active' : '') + (thread.unread ? ' ws-unread' : ''));
                    item.type = 'button';
                    item.title = thread.title + ' · #' + thread.channel;
                    item.appendChild(icon('bi-chat-square-dots', 'ws-ch-icon'));

                    var names = el('span', 'ws-channel-name');
                    names.appendChild(el('span', 'ws-thread-link-title', thread.title));
                    names.appendChild(el('small', 'ws-thread-link-channel', '#' + thread.channel));
                    item.appendChild(names);

                    if (thread.unread) {
                        item.appendChild(el('span', 'ws-count', thread.unread > 99 ? '99+' : thread.unread));
                    }

                    item.addEventListener('click', function () {
                        self.root.classList.remove('ws-show-side');
                        self.threadGo({ id: thread.id, channel_id: thread.channel_id, mine: true });
                    });
                    talks.appendChild(item);
                });

                if (!talksShut && (threads.length > THREADS_SHOWN)) {
                    var more = button('btn btn-sm btn-link pg-link-secondary ws-side-more', self.threadsAll ? t('th_show_fewer') : t('th_show_all', threads.length));

                    more.addEventListener('click', function () {
                        self.threadsAll = !self.threadsAll;
                        self.drawSide();
                    });
                    talks.appendChild(more);
                }

                scroll.appendChild(talks);
            }

            if ((children[0] || []).length || manage) {
                var groupSection = el('div', 'ws-side-section ws-side-groups');

                (children[0] || []).forEach(function (group) {
                    groupBox(group, groupSection, 0);
                });

                if (manage) {
                    var newGroup = button('btn btn-sm btn-link pg-link-secondary ws-group-new', t('grp_new'), 'bi-folder-plus');
                    newGroup.addEventListener('click', function () { self.groupForm(null, {}); });
                    groupSection.appendChild(newGroup);
                }

                scroll.appendChild(groupSection);
            }

            section(t('my_channels'), mine, true, '', true, 'mine');

            if (others.length) {
                section(t('other_channels'), others, false, '', true, 'others');
            }

            var work = group(t('nav_work'), 'work');

            if (work) {
                link(work, t('my_tasks'), 'bi-check2-square', function () { window.location.href = CFG.urls.tasks; });

                if (CFG.notes) {
                    link(work, t('nav_notes'), 'bi-journal-text', function () { window.location.href = CFG.urls.notes; });
                }
                link(work, t('planning_board'), 'bi-calendar-week', function () { window.location.href = CFG.urls.board; });
                link(work, t('work_calendar'), 'bi-calendar3', function () { window.location.href = CFG.urls.calendar; });

                if (CFG.flows && CFG.flows.nav) {
                    link(work, t('fl_nav'), 'bi-diagram-3', function () { window.location.href = CFG.urls.flows; });
                }

                // Staff's scheduled actions, until Flows lists them; their
                // scheduled messages stay here.
                var actionsHere = !!CFG.scheduled && !(CFG.flows && CFG.flows.scheduled);

                if (actionsHere || CFG.scheduled_messages) {
                    link(work, actionsHere ? t('sa_title') : t('sm_title'), 'bi-alarm', function () { self.showScheduled(true); }, 0, self.view === 'scheduled');
                }
            }

            var records = group(t('nav_records'), 'records');

            if (records) {
                link(records, t('nav_timeline'), 'bi-clock-history', function () { window.location.href = CFG.urls.timeline; });
                link(records, t('archived_channels'), 'bi-archive', function () { self.showArchived(); });

                if (BOOT.me.rights.staff) {
                    link(records, t('private_channels_audit'), 'bi-shield-lock', function () { self.showAudit(); });
                }
            }

            // The settings stay at the foot of the sidebar, however long the
            // list of channels grows.
            if (BOOT.me.rights.settings) {
                var foot = el('div', 'ws-side-foot');
                link(foot, t('workspace_settings'), 'bi-sliders', function () { window.location.href = CFG.urls.settings; });
                side.appendChild(foot);
            }

            scroll.scrollTop = keep.top;

            if (keep.search) {
                search.value = keep.search;
            }

            if (keep.focused) {
                search.focus();
            }
        },

        // ── The pinned message (includes/workspace/pins.php) ──

        pinClosedHere: function (pin) {
            try {
                return !!window.localStorage.getItem('ws-pin-closed:' + this.channel.id + ':' + pin.id);
            } catch (error) {
                return false;
            }
        },

        drawPinBar: function () {
            var self = this;
            var holder = self.pinHolder;
            var pin = self.channel ? self.channel.pin : null;

            if (!holder) {
                return;
            }

            clear(holder);

            if (!pin || pin.hidden || self.era || self.pinClosedHere(pin)) {
                return;
            }

            var bar = el('div', 'ws-pin-bar');
            var text = el('button', 'ws-pin-text');

            text.type = 'button';
            text.title = t('pin_go');
            bar.appendChild(icon('bi-pin-angle-fill', 'ws-pin-icon'));
            text.appendChild(el('b', 'me-1', (pin.sender ? pin.sender.name : '') + ':'));
            text.appendChild(document.createTextNode(pin.text));
            text.addEventListener('click', function () { self.jumpTo(pin.id, true); });
            bar.appendChild(text);

            if (pin.pinned_by) {
                bar.appendChild(el('span', 'ws-pin-by', t('pin_by', pin.pinned_by)));
            }

            // Its read receipt, or a way to ask for one (workspace_acks.js).
            var pinAck = window.PGWsAcks ? window.PGWsAcks.pinNode(pin, self, featureHelpers()) : null;

            if (pinAck) {
                bar.appendChild(pinAck);
            }

            var close = button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', t('pin_close'));
            close.addEventListener('click', function () {
                if (self.channel.joined) {
                    api('ws_pin_hide', { channel_id: self.channel.id }).catch(function () {});
                    pin.hidden = true;
                } else {
                    try {
                        window.localStorage.setItem('ws-pin-closed:' + self.channel.id + ':' + pin.id, '1');
                    } catch (error) {
                        // Not kept: it shows again next time.
                    }
                }

                clear(holder);
            });
            bar.appendChild(close);
            holder.appendChild(bar);
        },

        // message null takes the pin off.
        pinMessage: function (message) {
            var self = this;
            var channel = self.channel;
            var run = function () {
                api('ws_pin', { channel_id: channel.id, message_id: message ? message.id : 0 }).then(function (data) {
                    channel.pin = data.pin;
                    self.drawPinBar();
                    self.sync();
                }).catch(fail);
            };

            if (message && channel.pin && channel.pin.id !== message.id) {
                ask(t('pin_replace'), t('pin_message')).then(function (yes) {
                    if (yes) {
                        run();
                    }
                });
                return;
            }

            run();
        },

        // ── Messages chosen together (includes/workspace/forward.php) ──

        // Only messages written by people or applications are chosen.
        selectable: function (message) {
            return !!message && !message.deleted && (message.sender_kind === 'user' || message.sender_kind === 'app' || message.sender_kind === 'guest')
                && ['message', 'note', 'decision'].indexOf(message.kind) !== -1;
        },

        // The conversation as it is drawn gets its marks; it is not drawn
        // again, so the reader stays where they were.
        markSelectable: function () {
            var self = this;

            if (!self.pane) {
                return;
            }

            self.pane.classList.toggle('ws-pane-selecting', !!self.selecting);

            Array.prototype.forEach.call(self.pane.querySelectorAll('.ws-msg[data-ws-message]'), function (node) {
                var message = self.findMessage(parseInt(node.getAttribute('data-ws-message'), 10));
                var on = !!self.selecting && self.selectable(message);

                node.classList.toggle('ws-msg-choosable', on);
                node.classList.toggle('ws-msg-selected', on && self.selecting.ids.indexOf(message.id) !== -1);
            });
        },

        startSelect: function (message) {
            this.selecting = { ids: (message && this.selectable(message)) ? [message.id] : [] };
            this.markSelectable();
            this.drawSelectBar();
        },

        endSelect: function () {
            this.selecting = null;
            this.markSelectable();
            this.drawSelectBar();
        },

        toggleSelect: function (message, node) {
            var ids = this.selecting.ids;
            var at = ids.indexOf(message.id);

            if (!this.selectable(message)) {
                return;
            }

            if (at !== -1) {
                ids.splice(at, 1);
            } else if (ids.length >= ((CFG.forwards && CFG.forwards.max) || 20)) {
                toast(t('sel_max', (CFG.forwards && CFG.forwards.max) || 20), 'warning');
                return;
            } else {
                ids.push(message.id);
            }

            node.classList.toggle('ws-msg-selected', at === -1);
            this.drawSelectBar();
        },

        selectedMessages: function () {
            var self = this;

            return (self.selecting ? self.selecting.ids : []).map(function (id) { return self.findMessage(id); }).filter(Boolean).sort(function (a, b) { return a.id - b.id; });
        },

        drawSelectBar: function () {
            var self = this;
            var wrap = self.composerWrap;

            if (!wrap) {
                return;
            }

            if (self.selBar && self.selBar.parentNode) {
                self.selBar.parentNode.removeChild(self.selBar);
            }

            wrap.classList.toggle('ws-selecting', !!self.selecting);

            if (!self.selecting) {
                return;
            }

            var count = self.selecting.ids.length;
            var bar = el('div', 'ws-select-bar');
            var none = (count === 0);

            bar.appendChild(el('span', 'ws-select-count', count ? t('sel_count', count) : t('sel_help')));

            if (self.channel.can_post && !self.era) {
                var reply = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('sel_reply'), 'bi-reply');
                reply.disabled = none;
                reply.addEventListener('click', function () { self.replyToSelected(); });
                bar.appendChild(reply);
            }

            var forward = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('sel_forward'), 'bi-forward');
            forward.disabled = none;
            forward.addEventListener('click', function () { self.forwardForm(self.selectedMessages()); });
            bar.appendChild(forward);

            var copy = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('sel_copy'), 'bi-copy');
            copy.disabled = none;
            copy.addEventListener('click', function () { self.copySelected(); });
            bar.appendChild(copy);

            if (CFG.notes) {
                var note = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('sel_to_note'), 'bi-journal-plus');
                note.disabled = none;
                note.addEventListener('click', function () { self.noteForwardForm(self.selectedMessages()); });
                bar.appendChild(note);
            }

            var done = button('btn btn-sm btn-text theme-secondary ms-auto', t('sel_cancel'), 'bi-x-lg');
            done.addEventListener('click', function () { self.endSelect(); });
            bar.appendChild(done);

            self.selBar = bar;
            wrap.insertBefore(bar, wrap.firstChild);
        },

        replyToSelected: function () {
            var chosen = this.selectedMessages();

            if (!chosen.length) {
                return;
            }

            this.reply = chosen[0];
            this.replyMore = chosen.slice(1);
            this.editing = null;
            this.selecting = null;
            this.markSelectable();
            this.drawSelectBar();
            this.drawReplyBar();

            if (this.input) {
                this.input.focus();
            }
        },

        // The chosen messages as text to paste elsewhere, oldest first, each
        // under its sender and its full date and time: the labels on the
        // screen ("Yesterday 21:18") mean nothing once pasted.
        copySelected: function () {
            var chosen = this.selectedMessages();

            if (!chosen.length) {
                return;
            }

            var text = chosen.map(function (message) {
                var date = new Date(message.timestamp * 1000);
                var shape = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                var when;

                // In the site's time zone, like the labels on the screen; a
                // zone the browser does not know falls back to its own.
                try {
                    when = date.toLocaleString(CFG.locale || undefined, Object.assign({ timeZone: CFG.timezone || undefined }, shape));
                } catch (error) {
                    when = date.toLocaleString(CFG.locale || undefined, shape);
                }

                var lines = [readableText(message.html)];

                if (message.file) {
                    lines.push(t('sel_copy_file', message.file.name));
                }

                return '[' + when + '] ' + (message.sender ? message.sender.name : '') + ':\n' + lines.filter(Boolean).join('\n');
            }).join('\n\n');

            copyText(text);
            this.endSelect();
        },

        forwardForm: function (messages) {
            var self = this;
            var node = offcanvas('ws-forward', t('fwd_title'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));
            var source = self.channel;

            footer.classList.remove('d-none');

            // Never into a room a guest reads.
            var channels = (BOOT.channels || []).filter(function (item) {
                return !item.archived && (item.joined || item.kind === 'public') && (item.kind !== 'guest');
            }).map(function (item) {
                return [item.id, (item.kind === 'private' ? '🔒 ' : '#') + item.name];
            });
            var target = select(channels, (source && source.kind !== 'guest') ? source.id : (channels[0] || [0])[0]);
            var comment = el('textarea', 'form-control form-control-sm');

            target.id = nextId('ws-fwd-to-');
            comment.rows = 3;
            comment.maxLength = 2000;

            var list = el('div', 'ws-fwd-preview');

            messages.forEach(function (message) {
                var line = el('div', 'ws-fwd-preview-line');
                line.appendChild(el('b', 'me-1', (message.sender ? message.sender.name : '') + ':'));
                line.appendChild(document.createTextNode(self.plain(message.html) || (message.file ? message.file.name : '')));
                list.appendChild(line);
            });

            body.appendChild(list);
            body.appendChild(formRow(t('fwd_to'), target));
            body.appendChild(formRow(t('fwd_comment'), comment));

            if (source && source.kind === 'private') {
                body.appendChild(el('div', 'alert theme-warning small py-3', t('fwd_private')));
            }

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            var send = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('sel_forward'), 'bi-forward');

            cancel.setAttribute('data-bs-dismiss', 'drawer');
            footer.appendChild(cancel);
            footer.appendChild(send);

            send.addEventListener('click', function () {
                send.disabled = true;

                api('ws_forward', {
                    target: 'channel',
                    channel_id: parseInt(target.value, 10) || 0,
                    message_ids: messages.map(function (message) { return message.id; }),
                    comment: comment.value
                }).then(function (data) {
                    hideOffcanvas(node);
                    toast(t('fwd_sent'), 'success');
                    self.endSelect();

                    if (self.channel && data.channel_id === self.channel.id) {
                        self.sync(true);
                    } else {
                        self.reloadChannels(0);
                    }
                }).catch(function (error) {
                    send.disabled = false;
                    fail(error);
                });
            });

            showOffcanvas(node);
        },

        noteForwardForm: function (messages) {
            var self = this;
            var node = offcanvas('ws-forward-note', t('fwd_note_title'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));
            var slot = el('div');
            var comment = el('textarea', 'form-control form-control-sm');
            var picker = null;

            footer.classList.remove('d-none');
            comment.rows = 3;
            slot.appendChild(skeleton(2, 'ws-skeleton-sm'));
            body.appendChild(formRow(t('fwd_note_pick'), slot));
            body.appendChild(formRow(t('fwd_comment'), comment));

            api('ws_notes').then(function (data) {
                var options = [[0, t('fwd_note_new')]];

                (data.mine || []).concat((data.shared || []).filter(function (note) { return note.access === 'edit'; })).forEach(function (note) {
                    options.push([note.id, note.name]);
                });

                clear(slot);
                picker = select(options, 0);
                slot.appendChild(picker);
            }).catch(function (error) {
                clear(slot);
                slot.appendChild(el('div', 'small fg-danger', error.message));
            });

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('sel_to_note'), 'bi-journal-plus');

            cancel.setAttribute('data-bs-dismiss', 'drawer');
            footer.appendChild(cancel);
            footer.appendChild(save);

            save.addEventListener('click', function () {
                save.disabled = true;

                api('ws_forward', {
                    target: 'note',
                    note_id: picker ? (parseInt(picker.value, 10) || 0) : 0,
                    message_ids: messages.map(function (message) { return message.id; }),
                    comment: comment.value
                }).then(function (data) {
                    hideOffcanvas(node);
                    self.endSelect();

                    toast(t('fwd_note_done'), 'success', { href: CFG.urls.notes + '?note=' + data.note_id, label: t('fwd_open_note') });
                }).catch(function (error) {
                    save.disabled = false;
                    fail(error);
                });
            });

            showOffcanvas(node);
        },

        // The copies a forward carries: where from, who, when, the words.
        forwardsNode: function (message) {
            var self = this;
            var list = el('div', 'ws-fwd-list');

            message.forwards.forEach(function (card) {
                var box = el('div', 'ws-fwd');
                var head = el('div', 'ws-fwd-head');

                head.appendChild(icon('bi-forward', 'me-1'));
                head.appendChild(document.createTextNode(card.channel ? t('fwd_from', card.channel.name) : t('fwd_from_hidden')));

                if (card.can_open) {
                    var open = button('btn btn-sm btn-link py-0 ms-auto', t('fwd_open'), 'bi-box-arrow-up-right');
                    open.addEventListener('click', function (event) {
                        event.stopPropagation();

                        if (self.channel && card.channel.id === self.channel.id) {
                            self.jumpTo(card.source_id, true);
                        } else {
                            self.flashAfterOpen = [card.source_id];
                            self.open(card.channel.id, card.source_id);
                        }
                    });
                    head.appendChild(open);
                }

                box.appendChild(head);

                var who = el('div', 'ws-fwd-who');
                who.appendChild(avatar(card.sender || { name: '?' }, '1.4rem'));
                who.appendChild(el('b', '', card.sender ? card.sender.name : ''));
                who.appendChild(el('span', 'fg-secondary', card.time));
                box.appendChild(who);

                var words = el('div', 'ws-fwd-body');
                setHtml(words, card.html);
                chipMenus(words);
                box.appendChild(words);

                if (card.file_name) {
                    var file = el('div', 'ws-fwd-file small fg-secondary');
                    file.appendChild(icon('bi-paperclip', 'me-1'));
                    file.appendChild(document.createTextNode(card.file_name + ' · ' + t('fwd_file')));
                    box.appendChild(file);
                }

                list.appendChild(box);
            });

            return list;
        },

        // ── Groups of channels (includes/workspace/groups.php) ──

        groupTree: function () {
            var children = {};

            (BOOT.groups || []).forEach(function (group) {
                (children[group.parent_id] = children[group.parent_id] || []).push(group);
            });

            Object.keys(children).forEach(function (key) {
                children[key].sort(function (a, b) { return (a.sort - b.sort) || a.name.localeCompare(b.name); });
            });

            return children;
        },

        findGroup: function (id) {
            return (BOOT.groups || []).filter(function (group) { return group.id === id; })[0] || null;
        },

        groupSiblings: function (group) {
            return (this.groupTree()[group.parent_id] || []);
        },

        groupTreeIds: function (id) {
            var children = this.groupTree();
            var out = [];
            var queue = [id];

            while (queue.length) {
                var next = queue.shift();

                if (out.indexOf(next) === -1) {
                    out.push(next);
                    (children[next] || []).forEach(function (child) { queue.push(child.id); });
                }
            }

            return out;
        },

        groupDepth: function (id) {
            var depth = 0;
            var group = this.findGroup(id);

            while (group && depth < 50) {
                depth += 1;
                group = this.findGroup(group.parent_id);
            }

            return depth;
        },

        groupStats: function (id) {
            var tree = this.groupTreeIds(id);

            return {
                groups: Math.max(0, tree.length - 1),
                channels: (BOOT.channels || []).filter(function (channel) { return tree.indexOf(channel.group_id) !== -1; }).length
            };
        },

        groupUnread: function (id) {
            var tree = this.groupTreeIds(id);

            return (BOOT.channels || []).reduce(function (sum, channel) {
                return sum + ((tree.indexOf(channel.group_id) !== -1 && !channel.pinned && channel.joined && channel.notify !== 'none') ? (channel.unread || 0) : 0);
            }, 0);
        },

        // Tree options for a select, indented; one group and what is inside
        // it left out (a group cannot go inside itself).
        groupOptions: function (skipId) {
            var children = this.groupTree();
            var out = [];

            (function walk(parent, depth) {
                (children[parent] || []).forEach(function (group) {
                    if (group.id === skipId) {
                        return;
                    }

                    out.push([group.id, new Array(depth + 1).join('   ') + group.name]);
                    walk(group.id, depth + 1);
                });
            })(0, 0);

            return out;
        },

        // Which groups are closed in the list: this person's, on this browser.
        closedGroups: function () {
            try {
                return JSON.parse(window.localStorage.getItem('ws-groups-closed') || '{}') || {};
            } catch (error) {
                return {};
            }
        },

        closeGroup: function (id, shut) {
            var closed = this.closedGroups();

            if (shut) {
                closed[id] = 1;
            } else {
                delete closed[id];
            }

            try {
                window.localStorage.setItem('ws-groups-closed', JSON.stringify(closed));
            } catch (error) {
                // Not kept: the group opens again next time.
            }
        },

        // Which sections of the sidebar are closed: this person's, on this
        // browser.
        closedSections: function () {
            try {
                return JSON.parse(window.localStorage.getItem('ws-side-closed') || '{}') || {};
            } catch (error) {
                return {};
            }
        },

        closeSection: function (key, shut) {
            var closed = this.closedSections();

            if (shut) {
                closed[key] = 1;
            } else {
                delete closed[key];
            }

            try {
                window.localStorage.setItem('ws-side-closed', JSON.stringify(closed));
            } catch (error) {
                // Not kept: the section opens again next time.
            }
        },

        groupActions: function (group) {
            var self = this;
            var items = [{ header: group.name }];

            if (!CFG.groups || !CFG.groups.manage) {
                return items;
            }

            items.push({ icon: 'bi-pencil', label: t('grp_edit'), action: function () { self.groupForm(group); } });
            items.push({ icon: 'bi-key', label: t('grp_access') + (group.access ? ' (' + group.access + ')' : ''), action: function () { self.groupAccess(group); } });
            items.push('-');
            items.push({ icon: 'bi-plus-lg', label: t('new_channel'), action: function () {
                channelForm(null, { group_id: group.id }, function (id) { self.reloadChannels(id); });
            } });

            if (self.groupDepth(group.id) < CFG.groups.depth) {
                items.push({ icon: 'bi-folder-plus', label: t('grp_new_inside'), action: function () { self.groupForm(null, { parent_id: group.id }); } });
            }

            items.push('-');

            var siblings = self.groupSiblings(group);
            var at = siblings.indexOf(group);

            if (at > 0) {
                items.push({ icon: 'bi-arrow-up', label: t('grp_move_up'), action: function () { self.moveGroup(group, group.parent_id, siblings[at - 1].id, true); } });
            }

            if (at !== -1 && at < siblings.length - 1) {
                items.push({ icon: 'bi-arrow-down', label: t('grp_move_down'), action: function () { self.moveGroup(group, group.parent_id, siblings[at + 2] ? siblings[at + 2].id : 0, true); } });
            }

            if (group.parent_id) {
                items.push({ icon: 'bi-box-arrow-up-left', label: t('grp_move_top'), action: function () { self.moveGroup(group, 0, 0); } });
            }

            items.push('-');
            items.push({ icon: 'bi-folder-x', label: t('grp_remove'), danger: true, action: function () {
                ask(t('grp_remove_confirm', group.name), t('grp_remove'), true).then(function (yes) {
                    if (yes) {
                        api('ws_group_remove', { group_id: group.id }).then(function (data) {
                            BOOT.groups = data.groups;
                            BOOT.channels = data.channels;
                            self.drawSide();
                        }).catch(fail);
                    }
                });
            } });

            return items;
        },

        groupForm: function (group, defaults) {
            var self = this;
            var node = offcanvas('ws-group-form', group ? t('grp_edit') : t('grp_new'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));

            defaults = defaults || {};
            footer.classList.remove('d-none');

            var name = el('input', 'form-control');
            name.id = nextId('ws-grp-name-');
            name.maxLength = 80;
            name.value = group ? group.name : '';
            body.appendChild(formRow(t('grp_name'), name));

            var colors = colorPicker(group ? group.color : (defaults.color || 0));
            body.appendChild(formRow(t('color'), colors));

            var parent = select([[0, t('grp_top')]].concat(self.groupOptions(group ? group.id : -1)), group ? group.parent_id : (defaults.parent_id || 0));
            parent.id = nextId('ws-grp-parent-');
            body.appendChild(formRow(t('grp_parent'), parent, t('grp_levels', CFG.groups.depth)));

            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('save'), 'bi-check2');
            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'drawer');
            footer.appendChild(cancel);
            footer.appendChild(save);

            save.addEventListener('click', function () {
                var parentId = parseInt(parent.value, 10) || 0;
                var run = function () {
                    api('ws_group_save', { id: group ? group.id : 0, name: name.value, color: colors.value(), parent_id: parentId }).then(function (data) {
                        hideOffcanvas(node);
                        toast(t('grp_saved'), 'success');
                        BOOT.groups = data.groups;
                        self.reloadChannels(0);
                    }).catch(function (error) {
                        fail(error);

                        if (error.field === 'name') {
                            name.focus();
                        }
                    });
                };

                // Into another group: say what goes with it first.
                if (group && parentId !== group.parent_id) {
                    var target = self.findGroup(parentId);
                    var stats = self.groupStats(group.id);

                    ask(target ? t('grp_into', group.name, target.name, stats.groups, stats.channels) : t('grp_to_top', group.name), t('save')).then(function (yes) {
                        if (yes) {
                            run();
                        }
                    });
                    return;
                }

                run();
            });

            showOffcanvas(node);
            setTimeout(function () { name.focus(); }, 300);
        },

        groupAccess: function (group) {
            var self = this;
            var node = offcanvas('ws-group-access', t('grp_access'));
            var body = clear(node.querySelector('.drawer-body'));

            node.querySelector('.drawer-footer').classList.add('d-none');
            body.appendChild(skeleton(2, 'ws-skeleton-sm'));
            showOffcanvas(node);

            function personName(id) {
                var person = (BOOT.people || []).filter(function (item) { return item.id === id; })[0];
                return person ? person.name : '';
            }

            function load() {
                api('ws_group_access', { group_id: group.id }).then(draw).catch(fail);
            }

            function draw(data) {
                clear(body);

                var head = el('div', 'ws-record-title mb-1');
                head.appendChild(icon('bi-folder2', 'me-1'));
                head.appendChild(document.createTextNode(data.path));
                body.appendChild(head);
                body.appendChild(el('p', 'small fg-secondary mb-3', t('grp_access_help')));
                body.appendChild(el('div', 'small mb-5', t('grp_reach', data.reach.groups, data.reach.channels, data.reach.private)));

                var list = el('div', 'mb-5');

                if (!data.people.length) {
                    list.appendChild(el('div', 'small fg-secondary', t('grp_access_none')));
                }

                data.people.forEach(function (person) {
                    var row = el('div', 'd-flex align-items-center gap-3 py-1');
                    var text = el('div', 'flex-grow-1 min-w-0');
                    var member = (BOOT.people || []).filter(function (item) { return item.id === person.id; })[0];

                    row.appendChild(avatar(member || { name: person.name }));
                    text.appendChild(el('div', 'text-truncate', person.name));

                    if (person.granted_by) {
                        text.appendChild(el('div', 'small fg-secondary', t('grp_access_given_by', person.granted_by)));
                    }

                    row.appendChild(text);

                    var take = button('btn btn-sm btn-text theme-secondary', '', 'bi-x-lg', t('grp_access_take'));
                    take.addEventListener('click', function () {
                        ask(t('grp_access_take_confirm', data.path, person.name), t('grp_access_take'), true).then(function (yes) {
                            if (yes) {
                                api('ws_group_access_revoke', { group_id: group.id, user_ids: [person.id] }).then(function (result) {
                                    toast(t('grp_access_taken', result.removed), 'success');
                                    BOOT.groups = result.groups;
                                    load();
                                    self.reloadChannels(0);
                                }).catch(fail);
                            }
                        });
                    });
                    row.appendChild(take);
                    list.appendChild(row);
                });

                body.appendChild(list);

                if (data.above.length) {
                    body.appendChild(el('div', 'small fg-secondary mb-1', t('grp_access_above')));
                    var above = el('div', 'mb-5 small');

                    data.above.forEach(function (person) {
                        above.appendChild(el('div', '', person.name + ' · ' + person.group));
                    });

                    body.appendChild(above);
                }

                var holding = data.people.map(function (person) { return person.id; });
                var allowed = (BOOT.people || []).map(function (person) { return person.id; }).filter(function (id) { return holding.indexOf(id) === -1; });
                var picker = peoplePicker([], allowed);
                body.appendChild(formRow(t('grp_access_give'), picker));

                var give = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('grp_access_give'), 'bi-key');
                give.addEventListener('click', function () {
                    var ids = picker.value();

                    if (!ids.length) {
                        toast(t('grp_access_pick'), 'warning');
                        return;
                    }

                    // The counts come from the server: the private channels a
                    // member of staff does not see are in them too.
                    api('ws_group_access', { group_id: group.id, user_ids: ids }).then(function (preview) {
                        var names = ids.map(personName).filter(Boolean).join(', ');

                        return ask(t('grp_access_confirm', names, preview.path, preview.reach.groups, preview.reach.channels, preview.reach.private, preview.joining), t('grp_access_give')).then(function (yes) {
                            if (!yes) {
                                return null;
                            }

                            return api('ws_group_access_grant', { group_id: group.id, user_ids: ids }).then(function (result) {
                                toast(t('grp_access_done', result.added), 'success');
                                BOOT.groups = result.groups;
                                load();
                                self.reloadChannels(0);
                            });
                        });
                    }).catch(fail);
                });
                body.appendChild(give);
            }

            load();
        },

        // simple: moved up or down from the menu, with nothing to explain.
        moveGroup: function (group, parentId, beforeId, simple) {
            var self = this;
            var run = function () {
                api('ws_group_move', { group_id: group.id, parent_id: parentId, before_id: beforeId || 0 }).then(function (data) {
                    BOOT.groups = data.groups;
                    BOOT.channels = data.channels;
                    self.drawSide();
                }).catch(function (error) {
                    // A refused move is mostly a list someone else changed
                    // meanwhile: draw it again as the server has it.
                    fail(error);
                    self.reloadChannels(0);
                });
            };

            if (simple || parentId === group.parent_id) {
                run();
                return;
            }

            var target = self.findGroup(parentId);
            var stats = self.groupStats(group.id);

            ask(target ? t('grp_into', group.name, target.name, stats.groups, stats.channels) : t('grp_to_top', group.name), t('ok')).then(function (yes) {
                if (yes) {
                    run();
                }
            });
        },

        drawMovePreview: function (box, data) {
            clear(box);

            if (data.private && data.path) {
                box.appendChild(el('div', 'mb-1', t('grp_channel_private')));
            }

            if (data.joining.length) {
                box.appendChild(el('div', 'mb-1', t('grp_channel_joining', data.joining.join(', '))));
            }

            if (data.leaving.length) {
                box.appendChild(el('div', 'mb-1', t('grp_channel_leaving', data.leaving.join(', '))));
            }
        },

        // asked: the person has already said yes (in the picker's question).
        moveChannelGroup: function (channel, groupId, asked) {
            var self = this;
            var run = function () {
                api('ws_channel_group', { channel_id: channel.id, group_id: groupId }).then(function () {
                    self.reloadChannels(0);
                }).catch(fail);
            };

            if (asked) {
                run();
                return;
            }

            var group = self.findGroup(groupId);
            var from = self.findGroup(channel.group_id || 0);

            api('ws_channel_group', { channel_id: channel.id, group_id: groupId, preview: 1 }).then(function (data) {
                var details = el('div', 'small fg-secondary');
                self.drawMovePreview(details, data);

                ask(group ? t('grp_channel_into', channel.name, data.path || group.name) : t('grp_channel_out', channel.name, from ? from.name : ''), t('grp_move_channel'), false, details).then(function (yes) {
                    if (yes) {
                        run();
                    }
                });
            }).catch(fail);
        },

        pickGroupFor: function (channel) {
            var self = this;
            var picker = select([[0, t('grp_no_group')]].concat(self.groupOptions(-1)), channel.group_id || 0);
            var info = el('div', 'small fg-secondary mt-3');
            var details = el('div');
            var serial = 0;

            details.appendChild(picker);
            details.appendChild(info);

            function preview() {
                var asked = ++serial;

                clear(info);
                api('ws_channel_group', { channel_id: channel.id, group_id: parseInt(picker.value, 10) || 0, preview: 1 }).then(function (data) {
                    if (asked === serial) {
                        self.drawMovePreview(info, data);
                    }
                }).catch(function (error) {
                    info.textContent = error.message;
                });
            }

            picker.addEventListener('change', preview);
            preview();

            ask(t('grp_move_channel') + ': #' + channel.name, t('grp_move_channel'), false, details).then(function (yes) {
                var target = parseInt(picker.value, 10) || 0;

                if (yes && target !== (channel.group_id || 0)) {
                    self.moveChannelGroup(channel, target, true);
                }
            });
        },

        // A channel that is not in the person's own order: staff can still
        // drag it into a group.
        makeDraggable: function (link, channel) {
            var self = this;

            link.draggable = true;

            link.addEventListener('dragstart', function (event) {
                self.dragging = { id: channel.id, box: null, channel: channel };
                link.classList.add('ws-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'ws-channel:' + channel.id);
            });

            link.addEventListener('dragend', function () {
                link.classList.remove('ws-dragging');
                self.dragging = null;
                self.clearDropMarks();
            });
        },

        makeGroupDraggable: function (head, group) {
            var self = this;

            head.draggable = true;

            head.addEventListener('dragstart', function (event) {
                if (self.dragging) {
                    return;
                }

                self.draggingGroup = group;
                head.classList.add('ws-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'ws-group:' + group.id);
                event.stopPropagation();
            });

            head.addEventListener('dragend', function () {
                head.classList.remove('ws-dragging');
                self.draggingGroup = null;
                self.clearDropMarks();
            });
        },

        clearDropMarks: function () {
            Array.prototype.forEach.call(this.side.querySelectorAll('.ws-drop-into, .ws-drop-before, .ws-drop-after'), function (node) {
                node.classList.remove('ws-drop-into', 'ws-drop-before', 'ws-drop-after');
            });
        },

        // Something dropped here: a channel goes into the group (0: out of
        // its group), a group goes above, below or inside this one. Every
        // move into a group asks first, saying what goes where.
        dropTarget: function (node, groupId, group) {
            var self = this;

            function place(event) {
                if (!self.draggingGroup || !group) {
                    return 'into';
                }

                var rect = node.getBoundingClientRect();
                var y = (event.clientY - rect.top) / Math.max(1, rect.height);

                return (y < 0.3) ? 'before' : ((y > 0.7) ? 'after' : 'into');
            }

            function allowed() {
                if (self.dragging && self.dragging.channel) {
                    return (self.dragging.channel.group_id || 0) !== groupId && !(groupId === 0 && !self.dragging.channel.group_id);
                }

                if (self.draggingGroup && group) {
                    return self.draggingGroup.id !== group.id && self.groupTreeIds(self.draggingGroup.id).indexOf(group.id) === -1;
                }

                return false;
            }

            node.addEventListener('dragover', function (event) {
                if (!allowed()) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                event.dataTransfer.dropEffect = 'move';
                self.clearDropMarks();
                node.classList.add('ws-drop-' + place(event));
            });

            node.addEventListener('dragleave', function () {
                node.classList.remove('ws-drop-into', 'ws-drop-before', 'ws-drop-after');
            });

            node.addEventListener('drop', function (event) {
                if (!allowed()) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                var where = place(event);
                self.clearDropMarks();

                if (self.dragging && self.dragging.channel) {
                    var channel = self.dragging.channel;

                    // No reordering on dragend: the list is drawn again.
                    self.dragging = null;
                    self.moveChannelGroup(channel, groupId, false);
                    return;
                }

                var moving = self.draggingGroup;
                self.draggingGroup = null;

                if (where === 'into') {
                    self.moveGroup(moving, group.id, 0);
                    return;
                }

                var siblings = self.groupSiblings(group).filter(function (item) { return item.id !== moving.id; });
                var at = siblings.indexOf(group);
                var before = (where === 'before') ? group.id : (siblings[at + 1] ? siblings[at + 1].id : 0);

                self.moveGroup(moving, group.parent_id, before);
            });
        },

        clearConversation: function (channel, after) {
            var self = this;
            var title = el('input', 'form-control form-control-sm');
            var details = el('div');

            title.id = nextId('ws-era-title-');
            title.maxLength = 160;
            details.appendChild(formRow(t('era_title'), title, t('era_title_help')));

            ask(t('era_clear_confirm'), t('era_clear'), true, details).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_channel_clear', { channel_id: channel.id, title: title.value }).then(function () {
                    toast(t('era_cleared'), 'success');

                    if (after) {
                        after();
                    }

                    self.open(channel.id, 0);
                }).catch(fail);
            });
        },

        showEras: function (channel) {
            var node = offcanvas('ws-eras', t('era_list'));
            var body = clear(node.querySelector('.drawer-body'));

            node.querySelector('.drawer-footer').classList.add('d-none');
            body.appendChild(eraSection(channel, node));
            showOffcanvas(node);
        },

        reloadChannels: function (openId) {
            var self = this;

            api('ws_channels').then(function (data) {
                BOOT.channels = data.channels;
                BOOT.groups = data.groups || [];
                self.drawSide();

                if (openId) {
                    self.open(openId, 0);
                }
            }).catch(fail);
        },

        showArchived: function () {
            var self = this;
            var node = offcanvas('ws-archived', t('archived_channels'));
            var body = clear(node.querySelector('.drawer-body'));
            node.querySelector('.drawer-footer').classList.add('d-none');
            showOffcanvas(node);

            api('ws_channels', { archived: 1 }).then(function (data) {
                if (!data.archived.length) {
                    body.appendChild(el('div', 'ws-empty', t('no_archived')));
                }

                data.archived.forEach(function (channel) {
                    var row = el('button', 'ws-channel-link');
                    row.type = 'button';
                    row.appendChild(icon(channel.kind === 'private' ? 'bi-lock' : 'bi-hash'));
                    row.appendChild(el('span', 'ws-channel-name', channel.name));
                    row.addEventListener('click', function () {
                        hideOffcanvas(node);
                        self.open(channel.id, 0);
                    });
                    body.appendChild(row);
                });
            }).catch(fail);
        },

        showAudit: function () {
            var self = this;
            var node = offcanvas('ws-audit', t('private_channels_audit'));
            var body = clear(node.querySelector('.drawer-body'));
            node.querySelector('.drawer-footer').classList.add('d-none');
            body.appendChild(el('p', 'small fg-secondary', t('audit_help')));
            showOffcanvas(node);

            api('ws_audit_list').then(function (data) {
                if (!data.channels.length) {
                    body.appendChild(el('div', 'ws-empty', t('no_private_channels')));
                }

                data.channels.forEach(function (channel) {
                    var row = el('div', 'd-flex align-items-center gap-3 py-3 border-bottom');
                    var text = el('div', 'flex-grow-1');
                    text.appendChild(el('div', 'fw-semibold', channel.name));

                    if (channel.topic) {
                        text.appendChild(el('div', 'small', channel.topic));
                    }

                    text.appendChild(el('small', 'fg-secondary', t('audit_row', channel.owner, channel.members)));
                    row.appendChild(icon(channel.locked ? 'bi-lock-fill' : 'bi-lock'));
                    row.appendChild(text);

                    // A member of staff is in it: it is listed, its button
                    // stays greyed out.
                    if (channel.locked) {
                        var lockedButton = button('btn btn-sm btn-outline theme-secondary', t('open_for_audit'), 'bi-eye-slash');
                        lockedButton.disabled = true;
                        lockedButton.title = t('audit_locked');

                        var lockedWrap = el('span', 'd-inline-block');
                        lockedWrap.tabIndex = 0;
                        lockedWrap.title = t('audit_locked');
                        lockedWrap.appendChild(lockedButton);
                        row.appendChild(lockedWrap);
                        body.appendChild(row);
                        return;
                    }

                    var openButton = button('btn btn-sm ' + (channel.opened ? 'btn-text theme-secondary' : 'btn-outline theme-warning'), channel.opened ? t('open') : t('open_for_audit'), 'bi-eye');
                    openButton.addEventListener('click', function () {
                        var go = function () {
                            hideOffcanvas(node);
                            self.open(channel.id, 0);
                        };

                        if (channel.opened) {
                            go();
                            return;
                        }

                        ask(t('audit_confirm', channel.name), t('open_for_audit'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            api('ws_channel_audit', { channel_id: channel.id }).then(go).catch(fail);
                        });
                    });
                    row.appendChild(openButton);
                    body.appendChild(row);
                });
            }).catch(fail);
        },

        // ── The overview ──

        // The scheduled screen: staff's scheduled actions, and everybody's own
        // scheduled messages. Somebody who is not staff sees their messages
        // only, and cannot write a scheduled action here; nor can staff once
        // Flows lists their actions (CFG.flows.scheduled), who keep their
        // messages here.
        showScheduled: function (asked, part) {
            var self = this;
            var staff = !!CFG.scheduled && !(CFG.flows && CFG.flows.scheduled);

            part = staff ? (part || self.scheduledPart || 'actions') : 'messages';
            self.scheduledPart = part;

            self.stop();
            // The discussion beside the channel goes with it.
            self.closeThread();
            self.channel = null;
            self.view = 'scheduled';
            self.drawSide();

            var center = clear(self.center);
            var head = el('div', 'ws-head');
            head.appendChild(channelsToggle(self.root));

            var title = el('div', 'ws-head-title');
            var h = el('h2');
            h.appendChild(icon('bi-alarm', 'me-1 fg-secondary'));
            h.appendChild(document.createTextNode(staff ? t('sa_title') : t('sm_title')));
            title.appendChild(h);
            head.appendChild(title);
            center.appendChild(head);

            // Staff switch between the two lists.
            if (staff && CFG.scheduled_messages) {
                var parts = el('div', 'ws-tabs');
                parts.setAttribute('role', 'tablist');

                [['actions', t('sa_title')], ['messages', t('sm_title')]].forEach(function (option) {
                    var tab = button(part === option[0] ? 'active' : '', option[1]);
                    tab.setAttribute('role', 'tab');
                    tab.addEventListener('click', function () { self.showScheduled(false, option[0]); });
                    parts.appendChild(tab);
                });

                center.appendChild(parts);
            }

            var body = el('div', 'ws-pane ws-sa-view');
            center.appendChild(body);

            if (asked && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', CFG.urls.workspace + '?view=scheduled');
            }

            if (part === 'messages') {
                var mine = el('div', 'ws-sm-list');

                // Staff find their scheduled actions in Flows.
                if (CFG.scheduled && !staff) {
                    var moved = el('a', 'small d-inline-block mb-3', t('fl_sa_moved'));
                    moved.href = CFG.urls.flows + '?view=scheduled';
                    moved.insertBefore(icon('bi-diagram-3', 'me-1'), moved.firstChild);
                    body.appendChild(moved);
                }

                body.appendChild(el('p', 'small fg-secondary', t('sm_mine_help')));
                body.appendChild(mine);
                scheduledMessagesList(mine, 0, null, function (item) {
                    if (item.channel) {
                        self.pendingScheduledEdit = item;
                        self.open(item.channel.id, 0);
                    }
                });

                return;
            }

            scheduledList(body, head);
        },

        showHome: function (asked) {
            var self = this;

            self.stop();
            // The discussion beside the channel goes with it.
            self.closeThread();
            self.channel = null;
            self.view = 'home';
            self.drawSide();

            var center = clear(self.center);
            var head = el('div', 'ws-head');
            head.appendChild(channelsToggle(self.root));

            var title = el('div', 'ws-head-title');
            var h = el('h2');
            h.appendChild(icon('bi-house', 'me-1 fg-secondary'));
            h.appendChild(document.createTextNode(t('home')));
            title.appendChild(h);
            var date = el('div', 'ws-topic');
            title.appendChild(date);
            head.appendChild(title);
            center.appendChild(head);

            var body = el('div', 'ws-home');
            body.appendChild(skeleton(3));
            center.appendChild(body);

            if (asked && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', CFG.urls.workspace + '?view=home');
            }

            var serial = self.homeSerial = (self.homeSerial || 0) + 1;

            api('ws_home').then(function (data) {
                if ((serial !== self.homeSerial) || (self.view !== 'home')) {
                    return;
                }

                date.textContent = data.date;
                self.drawHome(body, data);
            }).catch(function (error) {
                clear(body);
                fail(error);
            });
        },

        drawHome: function (body, data) {
            var self = this;

            clear(body);

            var layout = el('div', 'row g-5');
            var main = el('div', 'xl:col-8');
            var side = el('div', 'xl:col-4');
            layout.appendChild(main);
            layout.appendChild(side);

            body.appendChild(el('h3', 'ws-home-hello', t('home_hello', data.name)));
            body.appendChild(layout);

            var pair = el('div', 'row g-5');
            var tasksCol = el('div', 'md:col-6');
            var talkCol = el('div', 'md:col-6');
            pair.appendChild(tasksCol);
            pair.appendChild(talkCol);
            main.appendChild(pair);

            // Coming up: the person's open tasks, the nearest due date first.
            var tasks = homeCard(t('home_tasks'), 'bi-check2-square', CFG.urls.tasks, t('my_tasks'));

            if (data.tasks_open) {
                var counts = el('div', 'ws-home-counts');
                counts.appendChild(el('span', '', t('home_tasks_count', data.tasks_open)));

                if (data.tasks_overdue) {
                    counts.appendChild(el('span', 'fg-danger', t('home_tasks_overdue', data.tasks_overdue)));
                }

                // The pressing ones lead the list; a link opens all of them.
                if (data.tasks_hot) {
                    var hotLink = el('a', 'ws-home-hot');
                    hotLink.href = CFG.urls.tasks + '?urgent=1';
                    hotLink.appendChild(icon('bi-fire', 'me-1'));
                    hotLink.appendChild(document.createTextNode(t('home_tasks_hot', data.tasks_hot)));
                    counts.appendChild(hotLink);
                }

                tasks.body.appendChild(counts);
            }

            if (!data.tasks.length) {
                tasks.body.appendChild(el('div', 'ws-home-empty', t('home_tasks_empty')));
            }

            data.tasks.forEach(function (task) {
                var hot = (task.priority === 'urgent' || task.priority === 'high');
                var row = button('ws-home-row' + (hot ? ' ws-home-row-hot ws-home-row-' + task.priority : ''));
                row.appendChild(icon(task.overdue ? 'bi-exclamation-circle' : (hot ? 'bi-fire' : 'bi-circle'), task.overdue ? 'fg-danger' : (hot ? 'ws-home-hot-icon' : 'fg-secondary')));

                var text = el('span', 'ws-home-row-text');
                var titleLine = el('span', 'ws-home-row-title', task.title);

                if (hot) {
                    titleLine.appendChild(el('span', 'ws-home-priority ws-home-priority-' + task.priority, (BOOT.priorities || {})[task.priority] || task.priority));
                }

                text.appendChild(titleLine);
                text.appendChild(el('small', task.overdue ? 'fg-danger' : 'fg-secondary', task.number + ' · ' + task.due_label));
                row.appendChild(text);
                row.addEventListener('click', function () {
                    taskDrawer.open(task.id, null, function () { self.showHome(false); });
                });
                tasks.body.appendChild(row);
            });

            tasksCol.appendChild(tasks.node);

            // Written to the person: replies to their messages and mentions.
            var talk = homeCard(t('home_talk'), 'bi-chat-left-text');

            if (!data.talk.length) {
                talk.body.appendChild(el('div', 'ws-home-empty', t('home_talk_empty')));
            }

            data.talk.forEach(function (item) {
                var row = button('ws-home-row' + (item.unread ? ' ws-home-unread' : ''));
                var face = el('span', 'ws-home-face');
                face.appendChild(avatar(item.sender));
                row.appendChild(face);

                var text = el('span', 'ws-home-row-text');
                var meta = el('small', 'ws-home-row-meta');
                meta.appendChild(el('b', '', item.sender ? item.sender.name : ''));
                meta.appendChild(document.createTextNode(' ' + (item.kind === 'reply' ? t('home_reply') : t('home_mention')) + ' · '));
                meta.appendChild(icon(item.channel.private ? 'bi-lock' : 'bi-hash'));
                meta.appendChild(document.createTextNode(item.channel.name + ' · ' + item.time));
                text.appendChild(meta);
                text.appendChild(el('span', 'ws-home-row-quote', item.text));
                row.appendChild(text);
                row.addEventListener('click', function () { self.open(item.channel.id, item.id); });
                talk.body.appendChild(row);
            });

            talkCol.appendChild(talk.node);

            // Approvals and read receipts waiting for the person, when there
            // are any (workspace_approvals.js, workspace_acks.js).
            [window.PGWsApprovals, window.PGWsAcks].forEach(function (feature) {
                var waiting = feature ? feature.home(data, self, featureHelpers()) : null;

                if (waiting) {
                    main.appendChild(waiting);
                }
            });

            // What this place is for: open for somebody who has not written
            // anything yet, folded for the others.
            var about = el('details', 'card ws-home-about');
            about.open = !!data.newcomer;

            var summary = el('summary', 'card-header');
            summary.appendChild(icon('bi-info-circle', 'me-1'));
            summary.appendChild(document.createTextNode(t('home_about')));
            about.appendChild(summary);

            var aboutBody = el('div', 'card-body');
            aboutBody.appendChild(el('p', 'mb-5', t('home_about_text')));

            var tiles = el('div', 'row g-3');
            var tileList = [
                ['bi-hash', t('channels'), t('home_about_channels')],
                ['bi-check2-square', t('nav_work'), t('home_about_tasks')],
                ['bi-patch-check', t('nav_timeline'), t('home_about_decisions')]
            ];

            if (data.claude) {
                tileList.push(['bi-stars', 'Claude', t('home_about_claude')]);
            }

            if (data.ai) {
                tileList.push(['bi-cpu', 'Pinegrap AI', t('home_about_ai')]);
            }

            tileList.forEach(function (tile) {
                var col = el('div', 'sm:col-6');
                var box = el('div', 'ws-home-tile');
                var name = el('div', 'ws-home-tile-title');
                name.appendChild(icon(tile[0], 'me-1'));
                name.appendChild(document.createTextNode(tile[1]));
                box.appendChild(name);
                box.appendChild(el('div', 'small fg-secondary', tile[2]));
                col.appendChild(box);
                tiles.appendChild(col);
            });

            aboutBody.appendChild(tiles);
            about.appendChild(aboutBody);
            main.appendChild(about);

            // A way to start, always in sight.
            var start = el('div', 'ws-home-start');
            start.appendChild(el('span', 'ws-home-start-text', t('home_start')));

            var created = function (id) { self.reloadChannels(id); };
            var publicButton = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('home_new_channel'), 'bi-hash');
            publicButton.addEventListener('click', function () { channelForm(null, { kind: 'public' }, created); });
            start.appendChild(publicButton);

            var privateButton = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('home_new_private'), 'bi-lock');
            privateButton.addEventListener('click', function () { channelForm(null, { kind: 'private' }, created); });
            start.appendChild(privateButton);

            if ((CFG.guests || {}).can_host) {
                var guestButton = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('guest_start'), 'bi-door-open');
                guestButton.addEventListener('click', function () { guestForm(created); });
                start.appendChild(guestButton);
            }
            main.appendChild(start);

            // Beside it: the latest decisions, unfiltered.
            var decisions = homeCard(t('home_decisions'), 'bi-clock-history', CFG.urls.timeline, t('nav_timeline'));

            if (!data.decisions.length) {
                decisions.body.appendChild(el('div', 'ws-home-empty', t('home_decisions_empty')));
            }

            var line = el('div', 'ws-home-tl');

            data.decisions.forEach(function (item) {
                var row = button('ws-home-tl-item' + (item.claude ? ' ws-tl-by-claude' : ''));
                var dot = el('span', 'ws-home-tl-dot');
                dot.appendChild(icon(item.claude ? 'bi-stars' : 'bi-patch-check'));
                row.appendChild(dot);

                var text = el('span', 'ws-home-row-text');
                var meta = el('small', 'ws-home-row-meta');
                meta.appendChild(icon(item.channel.private ? 'bi-lock' : 'bi-hash'));
                meta.appendChild(document.createTextNode(item.channel.name + ' · ' + item.day_label + ' ' + item.time));
                text.appendChild(meta);

                var quote = el('span', 'ws-home-row-quote ws-msg-body');
                setHtml(quote, item.html);
                text.appendChild(quote);
                row.appendChild(text);
                row.addEventListener('click', function (event) {
                    if (event.target.closest('a')) {
                        return;
                    }

                    self.open(item.channel.id, item.id);
                });
                line.appendChild(row);
            });

            decisions.body.appendChild(line);
            side.appendChild(decisions.node);

            // Public channels the person is not in yet.
            if (data.join.length) {
                var join = homeCard(t('home_join'), 'bi-door-open');

                data.join.forEach(function (channel) {
                    var row = el('div', 'ws-home-row ws-home-join');
                    row.appendChild(icon('bi-hash', 'fg-secondary'));

                    var text = el('span', 'ws-home-row-text');
                    text.appendChild(el('span', 'ws-home-row-title', channel.name));
                    text.appendChild(el('small', 'fg-secondary', (channel.topic ? channel.topic + ' · ' : '') + t('home_members', channel.members)));
                    row.appendChild(text);

                    var joinButton = button('btn btn-sm btn-outline theme-primary rounded-pill', t('join'));
                    joinButton.addEventListener('click', function () {
                        joinButton.disabled = true;
                        api('ws_channel_join', { channel_id: channel.id }).then(function () {
                            self.reloadChannels(channel.id);
                        }).catch(function (error) {
                            joinButton.disabled = false;
                            fail(error);
                        });
                    });
                    row.appendChild(joinButton);
                    join.body.appendChild(row);
                });

                side.appendChild(join.node);
            }
        },

        // ── Discussions (threads.php) ──

        // The address says which channel, and which discussion beside it.
        syncAddress: function () {
            if (!this.channel || this.era || !window.history || !window.history.replaceState) {
                return;
            }

            var thread = this.openThreadId();

            window.history.replaceState(null, '', CFG.urls.workspace + '?channel=' + this.channel.id + ((this.tab === 'board') ? '&view=board' : '') + (thread ? '&thread=' + thread : ''));
        },

        // Has the channel a Board tab (assets/js/workspace_board_channel.js)?
        // Not a room a guest talks in, and not an earlier version of the
        // conversation.
        hasBoard: function (channel) {
            return !!window.PGWsChannelBoard && !!channel && (channel.kind !== 'guest') && (channel.kind !== 'thread') && !channel.guest && !this.era;
        },

        // The discussion open beside the channel, 0 for none.
        openThreadId: function () {
            return (this.threadView && this.threadView.channel) ? this.threadView.channel.id : 0;
        },

        // Starts a discussion about a message: a title (the first words of
        // the message when left empty), then the discussion opens beside the
        // channel.
        startThread: function (message) {
            var self = this;
            var box = el('div');
            var title = el('input', 'form-control');

            title.maxLength = 80;
            title.placeholder = self.plain(message.html).slice(0, 80);
            title.id = nextId('ws-thread-title-');
            box.appendChild(el('p', 'small fg-secondary', t('th_start_help')));
            box.appendChild(formRow(t('th_title'), title, t('th_title_help')));

            ask(t('th_start'), t('th_create'), false, box).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_thread_start', { message_id: message.id, title: title.value }).then(function (data) {
                    self.openThread(data.thread_id, 0);
                    self.sync();
                }).catch(fail);
            });

            setTimeout(function () { title.focus(); }, 300);
        },

        // To a discussion from the bar under its message or the sidebar:
        // joined first when the reader is staff not yet in it; its channel
        // first when another one is open.
        threadGo: function (thread) {
            var self = this;

            if (!thread.mine && thread.can_join) {
                api('ws_thread_join', { thread_id: thread.id }).then(function () {
                    toast(t('th_joined'), 'success');
                    self.threadGo({ id: thread.id, channel_id: thread.channel_id, mine: true });
                    self.sync();
                }).catch(fail);
                return;
            }

            if (!self.channel || (self.channel.id !== thread.channel_id)) {
                self.pendingThread = { id: thread.id, message: 0 };
                self.open(thread.channel_id, 0);
                return;
            }

            self.openThread(thread.id, 0);
        },

        openThread: function (threadId, messageId) {
            if (!this.threadView) {
                this.threadView = makeThreadView(this);
            }

            this.threadView.load(threadId, messageId || 0);
        },

        closeThread: function () {
            if (this.threadView) {
                this.threadView.close();
            }
        },

        // Under a message: the discussion about it, how far it has got, and
        // the way in.
        threadBar: function (message) {
            var self = this;
            var thread = message.thread;
            var bar = el(thread.mine || thread.can_join ? 'button' : 'div', 'ws-thread-bar' + (thread.closed ? ' ws-thread-bar-closed' : '') + (thread.unread ? ' ws-thread-bar-news' : ''));

            if (thread.mine || thread.can_join) {
                bar.type = 'button';
                bar.addEventListener('click', function (event) {
                    event.stopPropagation();
                    self.threadGo(thread);
                });
            }

            bar.appendChild(avatarStack('ws-thread-faces', thread.people, 4));

            var text = el('span', 'ws-thread-bar-text');
            text.appendChild(el('b', '', t('th_bar', thread.title)));

            var facts = [t('th_bar_count', thread.count)];

            if (thread.last) {
                facts.push(t('th_bar_last', thread.last));
            }

            text.appendChild(el('span', 'ws-thread-bar-facts', facts.join(' · ')));
            bar.appendChild(text);

            if (thread.closed) {
                bar.appendChild(el('span', 'badge rounded-pill theme-secondary', t('th_bar_closed')));
            } else if (thread.unread) {
                bar.appendChild(el('span', 'ws-count', thread.unread > 99 ? '99+' : thread.unread));
            }

            if (thread.mine) {
                bar.appendChild(el('span', 'ws-thread-bar-go', t('th_open')));
            } else if (thread.can_join) {
                bar.appendChild(el('span', 'ws-thread-bar-go', t('th_join')));
            }

            var wrap = el('div', 'ws-thread-wrap');
            wrap.appendChild(bar);

            // An assistant was asked in it: said here, where the channel reads.
            if ((thread.assistants || []).length) {
                var note = el('div', 'ws-thread-ai');
                note.appendChild(icon('bi-stars', 'me-1'));
                note.appendChild(document.createTextNode(t('th_bar_assistants', thread.assistants.join(', '))));
                wrap.appendChild(note);
            }

            return wrap;
        },

        drawEmptyCenter: function () {
            var center = clear(this.center);
            var empty = el('div', 'ws-empty m-auto');
            empty.appendChild(icon('bi-chat-square-text'));
            empty.appendChild(document.createTextNode(t('pick_channel')));
            center.appendChild(empty);
        },

        // ── One channel ──

        open: function (channelId, messageId, eraId) {
            var self = this;

            self.stop();
            self.pending = null;
            self.reply = null;
            self.replyMore = null;
            self.selecting = null;
            self.editing = null;
            self.editingScheduled = null;
            self.labels = [];

            api('ws_channel_open', { channel_id: channelId, message_id: messageId || 0, era_id: eraId || 0 }).then(function (data) {
                // A discussion asked for as a channel: its channel, with the
                // discussion beside it.
                if (data.thread_of) {
                    self.pendingThread = { id: data.thread_of.thread_id, message: data.thread_of.message_id };
                    self.open(data.thread_of.channel_id, 0);
                    return;
                }

                // Another channel: a discussion of the one before closes.
                if (self.threadView && self.threadView.channel && (self.threadView.channel.thread || {}).channel_id !== channelId) {
                    self.closeThread();
                }

                self.view = 'channel';
                self.channel = data.channel;
                self.era = data.era || null;
                self.messages = data.messages;
                self.briefing = data.briefing || null;

                // Opening a channel reads it; the sidebar says so at once
                // rather than on the next sync. (An earlier version of its
                // conversation marks nothing read.)
                (BOOT.channels || []).forEach(function (item) {
                    if (item.id === channelId && !self.era) {
                        item.unread = 0;
                        item.mentions = 0;
                    }
                });

                self.lastId = data.last_id;
                self.sinceTs = data.now;
                self.hasMore = data.has_more;
                self.tab = ((self.pendingTab === 'board') && !messageId && self.hasBoard(data.channel)) ? 'board' : 'messages';
                self.pendingTab = null;
                self.drawCenter();
                self.drawSide();

                if (messageId) {
                    var target = self.center.querySelector('[data-ws-message="' + messageId + '"]');

                    if (target) {
                        if (self.flashAfterOpen) {
                            self.flash(self.flashAfterOpen);
                        } else {
                            target.classList.add('ws-msg-highlight');
                        }

                        target.scrollIntoView({ block: 'center' });
                    }
                } else {
                    self.scrollToEnd();
                }

                self.flashAfterOpen = null;

                if (self.pendingThread) {
                    var asked = self.pendingThread;

                    self.pendingThread = null;
                    self.openThread(asked.id, asked.message);
                }

                // A scheduled message picked on the scheduled screen to be
                // changed: its text goes into this channel's box.
                if (self.pendingScheduledEdit) {
                    var waiting = self.pendingScheduledEdit;

                    self.pendingScheduledEdit = null;
                    window.setTimeout(function () { self.editScheduled(waiting); }, 80);
                }

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', CFG.urls.workspace + '?channel=' + channelId + (self.era ? '&era=' + self.era.id : '') + ((self.tab === 'board') ? '&view=board' : ''));
                }

                self.schedule();
            }).catch(function (error) {
                fail(error);
                self.drawEmptyCenter();
            });
        },

        guestBar: function (channel) {
            var self = this;
            var info = channel.guest;
            var bar = el('div', 'alert rounded-0 m-0 py-3 small d-flex flex-wrap align-items-center gap-3 ws-guest-bar ' + (info.ended ? 'theme-secondary' : 'theme-warning'));

            bar.appendChild(icon('bi-door-open', 'fs-lg'));

            var text = el('div', 'flex-grow-1 ws-guest-bar-text');
            text.appendChild(el('div', 'fw-semibold', info.ended ? t('guest_ended', info.name) : t('guest_banner', info.name)));

            var state = el('div', 'ws-guest-state');

            if (info.online && !info.ended) {
                state.appendChild(el('span', 'ws-guest-online', t('guest_online')));
            }

            state.appendChild(el('span', '', info.label));

            if (info.seen && !info.online) {
                state.appendChild(el('span', '', t('guest_seen', info.seen)));
            }

            text.appendChild(state);

            if (!info.ended) {
                text.appendChild(el('div', 'fg-secondary', t('guest_hidden')));
            }

            bar.appendChild(text);

            if (info.can_host) {
                var tools = el('div', 'd-flex flex-wrap gap-3');
                var relink = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('guest_relink'), 'bi-link-45deg');
                relink.addEventListener('click', function () { self.guestRelink(channel); });
                tools.appendChild(relink);
                bar.appendChild(tools);

                if (!info.ended) {
                    var end = button('btn btn-sm btn-outline theme-danger rounded-pill px-5', t('guest_end'), 'bi-door-closed');

                    end.addEventListener('click', function () {
                        ask(t('guest_end_confirm'), t('guest_end'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            api('ws_guest_end', { channel_id: channel.id }).then(function () {
                                toast(t('guest_ended_toast'), 'success');
                                self.reloadChannels(channel.id);
                            }).catch(fail);
                        });
                    });
                    tools.appendChild(end);
                }
            }

            return bar;
        },

        // Who outside the team reads this channel, and for staff who may
        // manage it, their links.
        shareBar: function (channel) {
            var self = this;
            var info = channel.shares;
            var open = info.guests.filter(function (guest) { return guest.open; });
            var bar = el('div', 'alert rounded-0 m-0 py-3 small d-flex flex-wrap align-items-center gap-3 ws-share-bar ' + (open.length ? 'theme-warning' : 'theme-secondary'));

            bar.appendChild(icon('bi-box-arrow-up-right', 'fs-md'));

            var names = open.map(function (guest) {
                return guest.name + ' (' + (guest.access === 'read' ? t('share_read_badge') : t('share_write_badge')) + ')';
            });

            var text = el('div', 'flex-grow-1 ws-share-bar-text');
            text.appendChild(el('span', 'fw-semibold', open.length ? t('share_bar', names.join(', ')) : t('share_bar_closed')));

            open.filter(function (guest) { return guest.online; }).forEach(function (guest) {
                text.appendChild(el('span', 'ws-guest-online ms-3', guest.name + ' · ' + t('guest_online')));
            });

            bar.appendChild(text);

            if (info.can_host) {
                var manage = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('share_manage'), 'bi-gear');
                manage.addEventListener('click', function () { self.shareManage(channel); });
                bar.appendChild(manage);
            }

            return bar;
        },

        shareManage: function (channel) {
            var self = this;
            var info = channel.shares || { guests: [], durations: [] };
            var node = offcanvas('ws-share-list', t('share_start') + ' · #' + channel.name);
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));

            footer.classList.remove('d-none');

            info.guests.forEach(function (guest) {
                var row = el('div', 'ws-share-row');
                var head = el('div', 'd-flex align-items-center gap-3');

                head.appendChild(icon(guest.access === 'read' ? 'bi-eye' : 'bi-chat-dots', 'fg-secondary'));
                head.appendChild(el('b', 'flex-grow-1', guest.name));
                head.appendChild(el('span', 'badge rounded-pill ' + (guest.open ? 'theme-warning' : 'theme-secondary'), guest.access === 'read' ? t('share_read') : t('share_write')));
                row.appendChild(head);
                row.appendChild(el('div', 'small fg-secondary', guest.label + (guest.seen ? ' · ' + t('guest_seen', guest.seen) : '')));

                var tools = el('div', 'd-flex flex-wrap gap-1 mt-1');
                var relink = button('btn btn-sm btn-text theme-secondary', t('guest_relink'), 'bi-link-45deg');
                relink.addEventListener('click', function () {
                    var fields = guestLinkFields(info.durations);

                    hideOffcanvas(node);
                    ask(t('guest_relink_help'), t('guest_relink_make'), false, fields).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        var chosen = fields.value();

                        api('ws_share_relink', { channel_id: channel.id, guest_id: guest.id, mode: chosen.mode, duration: chosen.duration }).then(function (result) {
                            self.reloadChannels(channel.id);
                            guestLinkShow(result.url, result.qr || '');
                        }).catch(fail);
                    });
                });
                tools.appendChild(relink);

                if (guest.open) {
                    var end = button('btn btn-sm btn-text theme-secondary fg-danger', t('share_end'), 'bi-x-circle');
                    end.addEventListener('click', function () {
                        hideOffcanvas(node);
                        ask(t('share_end_confirm', guest.name), t('share_end'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            api('ws_share_end', { channel_id: channel.id, guest_id: guest.id }).then(function () {
                                toast(t('share_ended_toast'), 'success');
                                self.reloadChannels(channel.id);
                            }).catch(fail);
                        });
                    });
                    tools.appendChild(end);
                }

                row.appendChild(tools);
                body.appendChild(row);
            });

            if (channel.can_share) {
                var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('share_add'), 'bi-plus-lg');
                add.addEventListener('click', function () {
                    hideOffcanvas(node);
                    shareForm(channel, function () { self.reloadChannels(channel.id); });
                });
                footer.appendChild(add);
            } else {
                footer.classList.add('d-none');
            }

            showOffcanvas(node);
        },

        guestRelink: function (channel) {
            var self = this;
            var fields = guestLinkFields(channel.guest.durations);

            ask(t('guest_relink_help'), t('guest_relink_make'), false, fields).then(function (yes) {
                if (!yes) {
                    return;
                }

                var chosen = fields.value();

                api('ws_guest_relink', { channel_id: channel.id, mode: chosen.mode, duration: chosen.duration }).then(function (result) {
                    self.reloadChannels(channel.id);
                    guestLinkShow(result.url, result.qr || '');
                }).catch(fail);
            });
        },

        drawCenter: function () {
            var self = this;
            var channel = self.channel;
            var center = clear(self.center);

            // Header: name, topic, the customer, the people, the tools.
            var head = el('div', 'ws-head');
            head.appendChild(channelsToggle(self.root));

            // The channel's colour on its icon tile and a thin stripe.
            if (channel.hex) {
                head.style.setProperty('--ws-ch', channel.hex);
                head.setAttribute('data-ws-colored', '1');
            }

            var title = el('div', 'ws-head-title');
            var h = el('h2');
            var tile = el('span', 'ws-head-icon');
            tile.setAttribute('aria-hidden', 'true');
            tile.appendChild(channelIcon(channel));
            h.appendChild(tile);
            h.appendChild(document.createTextNode(channel.name));
            title.appendChild(h);

            var topic = el('div', 'ws-topic');

            // The customer, and beside it the records tied to it.
            if (channel.customer) {
                var who = el('span', 'ws-customer');

                setHtml(who, channel.customer.customer.html);

                channel.customer.links.forEach(function (link) {
                    var tie = el('span', 'ws-customer-tie');

                    setHtml(tie, link.html);
                    tie.title = link.kind;
                    who.appendChild(tie);
                });

                topic.appendChild(who);
                topic.appendChild(document.createTextNode(' '));
            } else if (channel.contact) {
                setHtml(topic, channel.contact.html);
                topic.appendChild(document.createTextNode(' '));
            }

            // How the work stands, from the latest status update
            // (workspace_status.js).
            var statusPill = window.PGWsStatus ? window.PGWsStatus.pill(channel, self, featureHelpers()) : null;

            if (statusPill) {
                topic.appendChild(statusPill);
            }

            if (channel.department) {
                topic.appendChild(el('span', 'badge me-1', channel.department.name)).style.background = channel.department.color;
            }

            topic.appendChild(document.createTextNode(channel.topic || ''));
            title.appendChild(topic);
            head.appendChild(title);

            var actions = el('div', 'ws-head-actions');
            var faces = button('btn btn-sm btn-text theme-secondary d-inline-flex align-items-center gap-1', '', '', t('members'));
            faces.appendChild(avatarStack('', channel.members, 4));
            faces.appendChild(el('span', 'small', channel.members.length));
            faces.addEventListener('click', function () { self.showMembers(); });
            actions.appendChild(faces);

            var addTask = button('btn btn-sm btn-text theme-secondary', '', 'bi-check2-square', t('new_task'));
            addTask.addEventListener('click', function () {
                var about = channel.customer ? channel.customer.customer : null;
                var refs = about
                    ? [{ type: about.type, id: about.id, label: about.label, icon: about.icon, token: '<#' + about.type + ':' + about.id + '>' }]
                    : (channel.contact ? [{ type: 'contact', id: channel.contact.id, label: channel.contact.label, icon: 'bi-person-vcard', token: '<#contact:' + channel.contact.id + '>' }] : []);

                taskDrawer.open(0, { channel_id: channel.id, department_id: channel.department ? channel.department.id : 0, refs: refs }, function () { self.sync(); });
            });
            actions.appendChild(addTask);

            actions.appendChild(self.channelMenu());
            head.appendChild(actions);
            center.appendChild(head);

            if (channel.audit) {
                var auditBar = el('div', 'alert theme-warning rounded-0 m-0 py-3 small');
                auditBar.appendChild(icon('bi-shield-lock', 'me-1'));
                auditBar.appendChild(document.createTextNode(t('audit_bar')));
                center.appendChild(auditBar);
            } else if (!channel.joined && channel.kind === 'public' && !self.era) {
                var joinBar = el('div', 'alert theme-info rounded-0 m-0 py-3 small d-flex align-items-center gap-3');
                joinBar.appendChild(el('span', 'flex-grow-1', t('not_joined')));
                var join = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('join'));
                join.addEventListener('click', function () {
                    api('ws_channel_join', { channel_id: channel.id }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                });
                joinBar.appendChild(join);
                center.appendChild(joinBar);
            }

            // A room a guest reads: who, where their link stands, and the
            // staff's ways to give a new one or end the conversation.
            center.classList.toggle('ws-guest-room', !!channel.guest && !channel.guest.ended);

            if (channel.guest) {
                center.appendChild(self.guestBar(channel));
            }

            // A channel of the team that people outside it read.
            if (channel.shares) {
                center.appendChild(self.shareBar(channel));
            }

            // An earlier version of the conversation: read as it was, with
            // the way back to the current one.
            if (self.era) {
                var eraBar = el('div', 'alert theme-secondary rounded-0 m-0 py-3 small d-flex align-items-center gap-3 ws-era-bar');
                eraBar.appendChild(icon('bi-clock-history'));
                eraBar.appendChild(el('span', 'flex-grow-1', t('era_bar', self.era.label, self.era.from_text, self.era.to_text)));
                var eraBack = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('era_back'), 'bi-arrow-return-left');
                eraBack.addEventListener('click', function () { self.open(channel.id, 0); });
                eraBar.appendChild(eraBack);
                center.appendChild(eraBar);
            }

            var tabs = el('div', 'ws-tabs');
            tabs.setAttribute('role', 'tablist');
            self.tabsNode = tabs;

            var tabList = [['messages', t('tab_messages')], ['decisions', t('tab_decisions')], ['tasks', t('tab_tasks')], ['files', t('tab_files')], ['summary', t('tab_summary')]];

            if (self.hasBoard(channel)) {
                tabList.push(['board', t('tab_board')]);
            }

            tabList.forEach(function (tab) {
                var item = button(self.tab === tab[0] ? 'active' : '', tab[1]);
                item.setAttribute('role', 'tab');
                item.setAttribute('data-ws-tab', tab[0]);
                item.addEventListener('click', function () {
                    self.tab = tab[0];
                    self.drawCenter();
                    self.syncAddress();

                    if (tab[0] === 'messages') {
                        self.scrollToEnd();
                    }
                });
                tabs.appendChild(item);
            });

            center.appendChild(tabs);
            self.drawTabMarks();

            window.requestAnimationFrame(function () {
                var active = tabs.querySelector('.active');

                if (active && (tabs.scrollWidth > tabs.clientWidth)) {
                    tabs.scrollLeft = Math.max(0, active.offsetLeft - 16);
                }
            });

            if (self.tab === 'messages') {
                var brief = self.briefingNode();

                if (brief) {
                    center.appendChild(brief);
                }

                // The pinned message, under the greeting.
                self.pinHolder = el('div', 'ws-pin-holder');
                center.appendChild(self.pinHolder);
                self.drawPinBar();
            }

            var pane = el('div', 'ws-pane ws-pane-' + self.tab);
            center.appendChild(pane);
            self.pane = pane;

            // A picture that finishes loading under the newest line pushes
            // it out of view; whoever was reading at the end stays there.
            // (The conversation is no longer pulled down on every look.)
            self.atEnd = true;
            self.unseenBelow = 0;
            pane.addEventListener('scroll', function () {
                self.atEnd = self.nearEnd();
                self.drawJump();
            }, { passive: true });
            pane.addEventListener('load', function (event) {
                if (self.atEnd && event.target && (event.target.tagName === 'IMG')) {
                    self.scrollToEnd();
                }
            }, true);

            if (self.tab === 'messages') {
                self.drawMessages();
                center.appendChild(self.composer());
                self.composerWrap.appendChild(self.jumpButton());
                self.drawSelectBar();
                self.fit();
            } else if (self.tab === 'decisions') {
                self.loadList('ws_channel_decisions', t('no_decisions'));
            } else if (self.tab === 'files') {
                self.drawFiles();
            } else if (self.tab === 'tasks') {
                self.drawTasks();
            } else if ((self.tab === 'board') && self.hasBoard(channel)) {
                window.PGWsChannelBoard.mount(pane, channel, {
                    openMessage: function (messageId) { self.open(channel.id, messageId); },
                    openSummary: function () {
                        self.tab = 'summary';
                        self.drawCenter();
                        self.syncAddress();
                    },
                    taskDefaults: function () { return self.taskDefaults(); },
                    refresh: function () { self.sync(); }
                });
            } else {
                self.drawSummary();
            }
        },

        // The marks on the tabs: how many decisions and open tasks there are,
        // and whether the summary is written, so a tab says it holds
        // something before it is opened.
        drawTabMarks: function () {
            var self = this;
            var counts = (self.channel && self.channel.tab_counts) || null;

            if (!self.tabsNode || !counts) {
                return;
            }

            [['decisions', counts.decisions, 'tab_decisions_count'], ['tasks', counts.tasks, 'tab_tasks_count'], ['summary', counts.summary ? 1 : 0, 'tab_summary_written']].forEach(function (mark) {
                var tab = self.tabsNode.querySelector('[data-ws-tab="' + mark[0] + '"]');
                var was = tab ? tab.querySelector('.ws-tab-mark') : null;

                if (!tab) {
                    return;
                }

                if (was) {
                    was.remove();
                }

                tab.removeAttribute('title');

                if (!mark[1]) {
                    return;
                }

                var badge = el('span', 'ws-tab-mark' + (mark[0] === 'summary' ? ' ws-tab-dot' : ''), mark[0] === 'summary' ? '' : (mark[1] > 99 ? '99+' : String(mark[1])));

                badge.setAttribute('aria-hidden', 'true');
                tab.appendChild(badge);
                tab.title = (mark[0] === 'summary') ? t(mark[2]) : t(mark[2], mark[1]);
            });
        },

        channelMenu: function () {
            var self = this;
            var channel = self.channel;
            var wrap = el('div', 'pg-menu-wrap');
            var toggle = button('btn btn-sm btn-text theme-secondary', '', 'bi-three-dots-vertical', t('more'));
            toggle.setAttribute('data-bs-toggle', 'menu');
            toggle.setAttribute('data-bs-placement', 'bottom-end');
            wrap.appendChild(toggle);

            var menu = el('ul', 'menu pg-menu-end');

            function item(label, iconName, handler) {
                var li = el('li');
                var link = button('menu-item pg-link-body-emphasis', label, iconName);
                link.addEventListener('click', handler);
                li.appendChild(link);
                menu.appendChild(li);
            }

            function divider() {
                var li = el('li');
                li.appendChild(el('hr', 'menu-divider'));
                menu.appendChild(li);
            }

            if (channel.can_manage) {
                item(t('edit_channel'), 'bi-pencil', function () {
                    channelForm(channel, {}, function (id) { self.reloadChannels(id); });
                });
            }

            // A sales quote to the channel's customer: the quote editor on the
            // current account the server found for it. The address comes
            // with the channel, only for whoever may write ERP documents and
            // only when the customer is or is tied to such an account.
            if (channel.sales_quote_url) {
                item(t('sales_quote_new'), 'bi-file-earmark-plus', function () { window.location.href = channel.sales_quote_url; });
                divider();
            }

            if (channel.bulk_products && !channel.archived) {
                item(t('bulk_make_products'), 'bi-box-seam', function () { self.bulkMakeProducts({}); });
                divider();
            }

            if (channel.joined) {
                [['all', t('notify_all')], ['mentions', t('notify_mentions')], ['none', t('notify_none')]].forEach(function (option) {
                    item(option[1] + (channel.notify === option[0] ? ' ✓' : ''), option[0] === 'none' ? 'bi-bell-slash' : 'bi-bell', function () {
                        api('ws_channel_notify', { channel_id: channel.id, notify: option[0] }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                    });
                });
                divider();
            }

            if (BOOT.claude && channel.claude && channel.can_manage && !channel.archived) {
                item(t('claude_allow') + (channel.claude.allowed ? ' ✓' : ''), 'bi-stars', function () {
                    var allow = !channel.claude.allowed;
                    var asked = (allow && channel.kind === 'private') ? ask(t('claude_allow_private'), t('claude_allow'), false) : Promise.resolve(true);

                    asked.then(function (yes) {
                        if (yes) {
                            api('ws_channel_claude', { channel_id: channel.id, allowed: allow ? 1 : 0 }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                        }
                    });
                });
                divider();
            }

            if (BOOT.ai && channel.ai && channel.can_manage && !channel.archived) {
                item(t('ai_allow') + (channel.ai.allowed ? ' ✓' : ''), 'bi-cpu', function () {
                    var allow = !channel.ai.allowed;
                    var asked = (allow && channel.kind === 'private') ? ask(t('ai_allow_private'), t('ai_allow'), false) : Promise.resolve(true);

                    asked.then(function (yes) {
                        if (yes) {
                            api('ws_channel_ai', { channel_id: channel.id, allowed: allow ? 1 : 0 }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                        }
                    });
                });
                divider();
            }

            if (channel.kind === 'public' && channel.can_change_kind) {
                item(t('make_private'), 'bi-lock', function () {
                    ask(t('make_private_confirm', (channel.members || []).length), t('make_private'), true).then(function (yes) {
                        if (yes) {
                            api('ws_channel_make_private', { channel_id: channel.id }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                        }
                    });
                });
            }

            if (channel.kind === 'private' && channel.can_change_kind) {
                item(t('make_public'), 'bi-unlock', function () {
                    ask(t('make_public_confirm'), t('make_public'), true).then(function (yes) {
                        if (yes) {
                            api('ws_channel_make_public', { channel_id: channel.id }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                        }
                    });
                });
            }

            if (channel.can_group) {
                item(t('grp_move_channel'), 'bi-folder-symlink', function () { self.pickGroupFor(channel); });
            }

            // Channel templates (assets/js/workspace_templates.js).
            if (window.PGWsTemplates && CFG.templates && CFG.templates.ready && !self.era && (channel.kind === 'public' || channel.kind === 'private')) {
                if (channel.can_post && !channel.archived) {
                    item(t('tpl_apply'), 'bi-layout-text-window', function () {
                        window.PGWsTemplates.applyDialog(channel, { api: api, toast: toast, fail: fail, done: function () { self.reloadChannels(channel.id); } });
                    });
                }

                if (CFG.templates.manage) {
                    item(t('tpl_from_channel'), 'bi-box-arrow-in-down', function () {
                        window.PGWsTemplates.fromChannel(channel, { api: api, fail: fail });
                    });
                }
            }

            // Shared with somebody outside the team (guests.php).
            if (channel.can_share && !self.era) {
                item(t('share_start'), 'bi-box-arrow-up-right', function () {
                    if (channel.shares && channel.shares.guests.length) {
                        self.shareManage(channel);
                    } else {
                        shareForm(channel, function () { self.reloadChannels(channel.id); });
                    }
                });
            }

            if (CFG.eras && channel.eras) {
                item(t('era_list') + ' (' + channel.eras + ')', 'bi-clock-history', function () { self.showEras(channel); });
            }

            if (channel.can_clear && !self.era) {
                item(t('era_clear'), 'bi-eraser', function () { self.clearConversation(channel); });
            }

            if (channel.can_manage) {
                item(channel.archived ? t('unarchive') : t('archive'), 'bi-archive', function () {
                    api('ws_channel_archive', { channel_id: channel.id, archive: channel.archived ? 0 : 1 }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                });
            }

            if (channel.joined) {
                item(t('leave_channel'), 'bi-box-arrow-right', function () {
                    ask(channel.kind === 'private' ? t('leave_private_confirm') : t('leave_confirm'), t('leave_channel'), true).then(function (yes) {
                        if (yes) {
                            api('ws_channel_leave', { channel_id: channel.id }).then(function () { self.reloadChannels(0); self.drawEmptyCenter(); }).catch(fail);
                        }
                    });
                });
            }

            // A group's divider is drawn before it is known whether any item
            // follows it ("New sales quote" may be the reader's only item):
            // the menu never ends on one.
            if (menu.lastElementChild && menu.lastElementChild.querySelector('.menu-divider')) {
                menu.removeChild(menu.lastElementChild);
            }

            wrap.appendChild(menu);

            return wrap;
        },

        showMembers: function () {
            var self = this;
            var channel = self.channel;
            var node = offcanvas('ws-members', t('members'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));

            footer.classList.add('d-none');

            // Claude, where it can be asked: listed, but no person to open.
            if (BOOT.claude && channel.claude && channel.claude.available) {
                var claudeRow = el('div', 'd-flex align-items-center gap-3 py-3 border-bottom');
                var claudeFace = avatar({ avatar: BOOT.claude.avatar }, '2rem');
                claudeRow.appendChild(claudeFace);

                var claudeText = el('div', 'flex-grow-1');
                claudeText.appendChild(el('div', 'fw-semibold', BOOT.claude.name));
                claudeText.appendChild(el('small', 'fg-secondary', t('claude_member')));
                claudeRow.appendChild(claudeText);
                body.appendChild(claudeRow);
            }

            if (BOOT.ai && channel.ai && channel.ai.available) {
                var aiRow = el('div', 'd-flex align-items-center gap-3 py-3 border-bottom');
                var aiFace = avatar({ avatar: BOOT.ai.avatar }, '2rem');
                aiRow.appendChild(aiFace);

                var aiText = el('div', 'flex-grow-1');
                aiText.appendChild(el('div', 'fw-semibold', BOOT.ai.name));
                aiText.appendChild(el('small', 'fg-secondary', t('ai_member')));
                aiRow.appendChild(aiText);
                body.appendChild(aiRow);
            }

            channel.members.forEach(function (member) {
                var row = el('div', 'd-flex align-items-center gap-3 py-3 border-bottom');
                onContext(row, function () { return personMenu(member); });
                var memberFace = avatar(member, '2rem', true);
                row.appendChild(personLink(memberFace, member));
                var text = el('div', 'flex-grow-1');
                text.appendChild(personLink(el('div', 'fw-semibold', member.name), member));
                text.appendChild(el('small', 'fg-secondary', (member.id === channel.owner_id ? t('owner') + ' · ' : '') + (member.title || '')));
                row.appendChild(text);
                row.appendChild(el('span', 'badge rounded-pill ' + (member.presence === 'online' ? 'theme-success' : 'theme-secondary'), t('presence_' + member.presence)));

                if (channel.can_manage && member.id !== channel.owner_id && member.id !== BOOT.me.id) {
                    var remove = button('btn btn-sm btn-text theme-secondary', '', 'bi-x-lg', t('remove'));
                    remove.addEventListener('click', function () {
                        api('ws_channel_member_remove', { channel_id: channel.id, user_id: member.id }).then(function () {
                            hideOffcanvas(node);
                            self.open(channel.id, 0);
                        }).catch(fail);
                    });
                    row.appendChild(remove);
                }

                body.appendChild(row);
            });

            if (channel.can_post && (channel.kind === 'public' || channel.joined)) {
                var inside = channel.members.map(function (member) { return member.id; });
                var outside = BOOT.people.filter(function (member) { return inside.indexOf(member.id) === -1; }).map(function (member) { return member.id; });

                if (outside.length) {
                    body.appendChild(el('div', 'form-label mt-5', t('add_people')));
                    var picker = peoplePicker([], outside);
                    body.appendChild(picker);

                    footer.classList.remove('d-none');
                    var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('add_people'), 'bi-person-plus');
                    add.addEventListener('click', function () {
                        var ids = picker.value();

                        if (!ids.length) {
                            return;
                        }

                        api('ws_channel_members_add', { channel_id: channel.id, user_ids: ids }).then(function () {
                            hideOffcanvas(node);
                            self.open(channel.id, 0);
                        }).catch(fail);
                    });
                    footer.appendChild(add);
                }
            }

            showOffcanvas(node);
        },

        // ── Messages ──

        drawMessages: function () {
            var self = this;
            var pane = clear(self.pane);

            if (self.hasMore) {
                var older = button('btn btn-sm btn-text theme-secondary d-block mx-auto mb-3', t('older_messages'), 'bi-arrow-up');
                older.addEventListener('click', function () { self.loadOlder(older); });
                pane.appendChild(older);
            }

            if (!self.messages.length) {
                var empty = el('div', 'ws-empty');
                empty.appendChild(icon('bi-chat-dots'));
                empty.appendChild(document.createTextNode(t('no_messages')));
                pane.appendChild(empty);
            }

            var previous = null;

            // A message deleted leaves no trace in the conversation.
            self.messages.forEach(function (message) {
                if (message.deleted) {
                    return;
                }

                self.appendMessage(message, previous, pane);
                previous = message;
            });
        },

        // The newest message still on the screen.
        lastShown: function () {
            for (var i = this.messages.length - 1; i >= 0; i--) {
                if (!this.messages[i].deleted) {
                    return this.messages[i];
                }
            }

            return null;
        },

        // Takes a message off the screen without a trace, and draws the one
        // after it again: it gets its name and time back if it followed on.
        removeMessageNode: function (messageId) {
            var node = this.center ? this.center.querySelector('[data-ws-message="' + messageId + '"]') : null;
            var index = -1;
            var i;

            if (!node) {
                return;
            }

            var before = node.previousElementSibling;
            var after = node.nextElementSibling;

            node.remove();

            // A day line with nothing left under it goes as well.
            if (before && before.classList.contains('ws-day') && (!after || after.classList.contains('ws-day'))) {
                before.remove();
            }

            for (i = 0; i < this.messages.length; i++) {
                if (this.messages[i].id === messageId) {
                    index = i;
                }
            }

            var next = null;
            var previous = null;

            for (i = index + 1; i < this.messages.length; i++) {
                if (!this.messages[i].deleted) {
                    next = this.messages[i];
                    break;
                }
            }

            for (i = index - 1; i >= 0; i--) {
                if (!this.messages[i].deleted) {
                    previous = this.messages[i];
                    break;
                }
            }

            if (!next) {
                return;
            }

            if (previous && (this.dayKey(previous) !== this.dayKey(next))) {
                previous = null;
            }

            var nextNode = this.center.querySelector('[data-ws-message="' + next.id + '"]');

            if (nextNode) {
                nextNode.parentNode.replaceChild(this.messageNode(next, previous), nextNode);
            }
        },

        dayKey: function (message) {
            var date = new Date(message.timestamp * 1000);
            return date.getFullYear() + '-' + date.getMonth() + '-' + date.getDate();
        },

        appendMessage: function (message, previous, pane) {
            var self = this;

            pane = pane || self.pane;

            if (!previous || self.dayKey(previous) !== self.dayKey(message)) {
                var date = new Date(message.timestamp * 1000);
                pane.appendChild(el('div', 'ws-day', date.toLocaleDateString(CFG.locale || undefined, { weekday: 'long', day: 'numeric', month: 'long' })));
                previous = null;
            }

            pane.appendChild(self.messageNode(message, previous));
        },

        messageNode: function (message, previous) {
            var self = this;

            if (message.kind === 'system') {
                var line = el('div', 'ws-msg-system');
                line.setAttribute('data-ws-message', message.id);
                setHtml(line, message.html);
                line.appendChild(el('span', 'ms-3', message.time));

                return line;
            }

            var follow = previous && previous.kind !== 'system' && previous.sender && message.sender
                && previous.sender.id === message.sender.id && previous.sender_kind === message.sender_kind
                && (message.timestamp - previous.timestamp) < 300 && !message.parent_id
                && message.kind === 'message' && previous.kind !== 'task';

            var node = el('div', 'ws-msg' + (follow ? ' ws-msg-follow' : '') + (message.deleted ? ' ws-msg-deleted' : '')
                + ((message.kind === 'decision' || message.kind === 'note') ? ' ws-msg-' + message.kind : ''));
            node.setAttribute('data-ws-message', message.id);

            var face = el('div', 'ws-msg-avatar');
            var person = (message.sender_kind === 'user') ? message.sender : null;
            face.appendChild(personLink(avatar(message.sender), person));
            node.appendChild(face);

            var main = el('div', 'ws-msg-main');
            var meta = el('div', 'ws-msg-meta');
            meta.appendChild(personLink(el('b', '', message.sender ? message.sender.name : ''), person));

            if (message.sender_kind === 'guest') {
                meta.appendChild(el('span', 'badge rounded-pill ws-guest-badge', t('guest_badge')));
            }

            // An MCP agent's answer, under the agent's own name: who asked and
            // through which tool are on its mark (mcp_agent.php).
            if (message.sender && message.sender.mcp) {
                var agentMark = el('span', 'badge rounded-pill bg-subtle-info fg-emphasis-info', message.sender.title);
                agentMark.title = message.sender.about || '';
                meta.appendChild(agentMark);
            }

            meta.appendChild(el('span', '', message.time));

            if (message.kind === 'decision' || message.kind === 'note') {
                var flag = el('span', 'ws-msg-flag ws-flag-' + message.kind);
                flag.appendChild(icon(message.kind === 'decision' ? 'bi-patch-check' : 'bi-sticky'));
                flag.appendChild(document.createTextNode(message.kind === 'decision' ? t('decision') : t('note')));
                flag.title = message.marked;

                // The trace of a record change: shown as kept for good.
                if (message.locked) {
                    flag.appendChild(icon('bi-lock-fill', 'ms-1'));
                    flag.title = message.marked + ' · ' + t('change_locked');
                }

                meta.appendChild(flag);
            }

            // A decision, a note or a task that was decided in a discussion.
            if (message.from_thread) {
                var origin = el(message.from_thread.open ? 'button' : 'span', 'ws-msg-from-thread');
                origin.appendChild(icon('bi-chat-square-dots', 'me-1'));
                origin.appendChild(document.createTextNode(t('th_from', message.from_thread.title)));

                if (message.from_thread.open) {
                    origin.type = 'button';
                    origin.addEventListener('click', function (event) {
                        event.stopPropagation();
                        app.openThread(message.from_thread.thread_id, 0);
                    });
                }

                meta.appendChild(origin);
            }

            // The message pinned to the top of the channel.
            if (self.channel && self.channel.pin && self.channel.pin.id === message.id) {
                var pinned = icon('bi-pin-angle-fill', 'ws-msg-pinned');
                pinned.title = t('pin_title');
                meta.appendChild(pinned);
            }

            main.appendChild(meta);

            if (message.parent_id) {
                var parent = self.findMessage(message.parent_id);
                var quote = el('button', 'ws-msg-quote');
                quote.type = 'button';
                quote.title = t('reply_go_to');
                quote.appendChild(icon('bi-reply', 'me-1'));
                quote.appendChild(document.createTextNode((parent && !parent.deleted) ? ((parent.sender ? parent.sender.name + ': ' : '') + self.quoteText(parent)) : t('earlier_message')));

                // The message answered: brought into view and lit up for a
                // moment, so which one it was is plain. One deleted since is
                // nowhere to go.
                quote.addEventListener('click', function (event) {
                    var answered = self.findMessage(message.parent_id);

                    event.stopPropagation();

                    if (!answered || !answered.deleted) {
                        self.jumpTo(message.parent_id, true);
                    }
                });
                main.appendChild(quote);
            }

            // An answer to several: the others it answers, in their order.
            (message.quotes || []).forEach(function (other) {
                var more = el('button', 'ws-msg-quote ws-msg-quote-more');
                more.type = 'button';
                more.title = t('reply_go_to');
                more.appendChild(icon('bi-reply', 'me-1'));
                more.appendChild(document.createTextNode(other.deleted ? t('earlier_message') : ((other.sender ? other.sender + ': ' : '') + other.text)));
                more.addEventListener('click', function (event) {
                    event.stopPropagation();

                    if (!other.deleted) {
                        self.jumpTo(other.id, true);
                    }
                });
                main.appendChild(more);
            });

            var body = el('div', 'ws-msg-body');

            if (message.deleted) {
                body.textContent = t('message_deleted');
            } else if (message.note_card) {
                body.appendChild(self.noteCardNode(message.note_card));
            } else if (message.scheduled) {
                // A scheduled action written here: its card, as it is now.
                body.appendChild(scheduledCard(message.scheduled, function (fresh) {
                    message.scheduled = fresh;
                    self.replaceMessage(message);
                }));
            } else {
                setHtml(body, message.html);
                chipMenus(body);
                self.bindChecks(body, message);
            }

            // Changed after it was sent: said at its end, where it shows
            // also under a run of messages that hides the name and time.
            if (message.edited && !message.deleted) {
                var edited = el('span', 'ws-msg-edited');
                edited.appendChild(icon('bi-pencil'));
                edited.appendChild(document.createTextNode(' ' + t('edited')));
                edited.title = message.edited_time ? t('edited_at', message.edited_time) : t('edited');

                var last = body.lastElementChild;

                if (last && /^(P|DIV|LI)$/.test(last.tagName) && !last.querySelector('table, pre, .ws-block')) {
                    last.appendChild(edited);
                } else {
                    body.appendChild(edited);
                }
            }

            main.appendChild(body);

            if (message.forwards && message.forwards.length) {
                main.appendChild(self.forwardsNode(message));
            }

            if (message.poll) {
                main.appendChild(self.pollNode(message));
            }

            // An approval request (workspace_approvals.js).
            if (message.approval && window.PGWsApprovals) {
                main.appendChild(window.PGWsApprovals.card(message, self, featureHelpers()));
            }

            // A status update's colour, or a check-in's card
            // (workspace_status.js).
            if (message.status_card && window.PGWsStatus) {
                var statusCard = window.PGWsStatus.card(message, self, featureHelpers(), node);

                if (statusCard) {
                    main.appendChild(statusCard);
                }
            }

            if (message.list_tasks && message.list_tasks.length) {
                main.appendChild(self.listTasksNode(message));
            }

            if (message.file) {
                var fileBox = el('div', 'ws-msg-file');
                fileBox.appendChild(self.filePreview(message));

                if (message.file.versions) {
                    var versions = button('ws-file-versions', t('file_versions', message.file.versions), 'bi-clock-history');
                    versions.addEventListener('click', function (event) {
                        event.stopPropagation();
                        self.fileVersions(message, versions);
                    });
                    fileBox.appendChild(versions);
                }

                // A CSV or spreadsheet of the channel made into product
                // drafts, for whoever may make products here.
                if (self.channel && self.channel.bulk_products && /\.(csv|xlsx|xlsm|xls|ods)$/i.test(message.file.name || '')) {
                    var makeProducts = button('ws-file-versions', t('bulk_make_products'), 'bi-box-seam');
                    makeProducts.addEventListener('click', function (event) {
                        event.stopPropagation();
                        self.bulkMakeProducts({ message_id: message.id });
                    });
                    fileBox.appendChild(makeProducts);
                }

                main.appendChild(fileBox);
            }

            if (message.task_html) {
                var card = el('div');
                setHtml(card, message.task_html);
                self.bindCard(card.firstElementChild);
                main.appendChild(card);
            }

            if (message.drafts && message.drafts.length) {
                main.appendChild(self.draftsNode(message));
            }

            if (message.changes && message.changes.length) {
                main.appendChild(self.changesNode(message));
            }

            if (message.design && message.design.length) {
                main.appendChild(self.designNode(message));
            }

            if (message.reactions && message.reactions.length) {
                main.appendChild(self.reactionRow(message));
            }

            // A read receipt asked for (workspace_acks.js).
            if (message.ack && window.PGWsAcks) {
                main.appendChild(window.PGWsAcks.node(message, self, featureHelpers()));
            }

            // Somebody mentioned here who is not in the channel: one click
            // brings them in, and the mention reaches them.
            if (message.invite && message.invite.length && !message.deleted) {
                main.appendChild(self.inviteNode(message));
            }

            if (message.claude) {
                main.appendChild(self.claudeStateNode(message));
            }

            // A discussion about this message (threads.php).
            if (message.thread && !self.isThread) {
                main.appendChild(self.threadBar(message));
            }

            node.appendChild(main);

            if (!message.deleted && message.kind !== 'task') {
                node.appendChild(self.messageTools(message));
                onContext(node, function (event) { return self.messageActions(message, event); });

                // Choosing messages: a tap chooses or lets go.
                if (self.selecting && self.selectable(message)) {
                    node.classList.toggle('ws-msg-selected', self.selecting.ids.indexOf(message.id) !== -1);
                    node.classList.add('ws-msg-choosable');
                }

                // No hover on a phone: a tap on the message shows its tools.
                node.addEventListener('click', function (event) {
                    if (self.selecting) {
                        if (!event.target.closest('a')) {
                            event.preventDefault();
                            self.toggleSelect(message, node);
                        }

                        return;
                    }

                    if (event.target.closest('a, button, input, label, .ws-task-card, .ws-poll, .ws-approval, .ws-ack, .ws-checkin')) {
                        return;
                    }

                    Array.prototype.forEach.call(self.pane.querySelectorAll('.ws-msg-active'), function (other) {
                        if (other !== node) {
                            other.classList.remove('ws-msg-active');
                        }
                    });

                    node.classList.toggle('ws-msg-active');
                });
            }

            return node;
        },

        plain: function (markup) {
            var box = document.createElement('div');
            box.innerHTML = markup || '';

            // A form's card under the text is drawn from the submission
            // (includes/workspace/watch.php); it is not part of what was written.
            Array.prototype.forEach.call(box.querySelectorAll('.ws-form-card'), function (card) {
                card.parentNode.removeChild(card);
            });

            var text = (box.textContent || '').replace(/\s+/g, ' ').trim();
            return (text.length > 90) ? text.slice(0, 89) + '…' : text;
        },

        // What a quote of a message says: its words, or for a file alone its
        // name - "Voice message" for a recording (workspace_voice.js).
        quoteText: function (message) {
            var text = this.plain(message.html);
            var file = message.file;

            if (text || !file) {
                return text;
            }

            return ((file.kind === 'audio') && window.PGWsVoice && window.PGWsVoice.isVoice(file.name)) ? t('voice_message') : (file.name || '');
        },

        findMessage: function (id) {
            for (var i = 0; i < this.messages.length; i++) {
                if (this.messages[i].id === id) {
                    return this.messages[i];
                }
            }

            return null;
        },

        // What can be done with a message: the hover bar shows the first
        // ones, the context menu all of them.
        messageActions: function (message, event) {
            var self = this;
            var items = [];
            var checkItem = (event && event.target && event.target.closest) ? event.target.closest('.ws-check') : null;

            if (message.can_react) {
                items.push({
                    emojis: (BOOT.reactions || []).slice(0, 6),
                    mine: (message.reactions || []).filter(function (group) { return group.mine; }).map(function (group) { return group.emoji; }),
                    action: function (emoji) { self.react(message, emoji); },
                    onMore: function (x, y) {
                        emojiPicker(x, y, function (emoji) { self.react(message, emoji, true); });
                    }
                });
            }

            // A checklist item becomes a task of its own, read as /task
            // reads it; the server offers it only on an item without one.
            var makeButton = checkItem ? checkItem.querySelector('[data-ws-item-task]') : null;

            if (makeButton) {
                items.push({ icon: 'bi-check2-square', label: t('task_from_item'), action: function () {
                    self.itemTask(message, makeButton);
                } });
                items.push('-');
            }

            if (self.channel.can_post) {
                items.push({ icon: 'bi-reply', label: t('reply'), tool: true, action: function () {
                    self.reply = message;
                    self.drawReplyBar();
                    self.input.focus();
                } });

                items.push({ icon: 'bi-check2-square', label: t('task_from_message'), tool: true, action: function () {
                    taskDrawer.open(0, Object.assign(self.taskDefaults(), {
                        title: self.plain(message.html).slice(0, 200),
                        description: message.raw || ''
                    }), function () { self.sync(); });
                } });
            }

            // The text as it reads, with its line breaks, to paste elsewhere.
            // It is a tool so a phone shows it with a tap on the message; a
            // phone can also open it in a box to select a part of it, since
            // the long press there opens this menu instead of selecting.
            if (message.html) {
                items.push({ icon: 'bi-copy', label: t('copy_text'), tool: true, action: function () {
                    copyText(readableText(message.html));
                } });

                if (touchScreen()) {
                    items.push({ icon: 'bi-cursor-text', label: t('select_text'), action: function () {
                        textSheet(readableText(message.html), false);
                    } });
                }
            }

            // A room of its own beside the channel to talk this message over.
            if (CFG.threads && !self.isThread && !self.era && !message.deleted && !message.past
                && ['message', 'decision', 'note', 'task'].indexOf(message.kind) !== -1) {
                if (message.thread && (message.thread.mine || message.thread.can_join)) {
                    items.push({ icon: 'bi-chat-square-dots', label: message.thread.mine ? t('th_open') : t('th_join'), tool: true, action: function () { self.threadGo(message.thread); } });
                } else if (!message.thread && self.channel.can_post) {
                    items.push({ icon: 'bi-chat-square-dots', label: t('th_start'), tool: true, action: function () { self.startThread(message); } });
                }
            }

            // A note card is a note already: it opens, and those who may
            // change the note edit it on the notes screen.
            if (message.note_card && !message.deleted) {
                items.push({ icon: 'bi-eye', label: t('notes_card_open'), tool: true, action: function () { openNoteView(message.note_card.id); } });

                if (noteCardEditable(message.note_card)) {
                    items.push({ icon: 'bi-pencil-square', label: t('notes_card_edit'), tool: true, action: function () { openNoteEdit(message.note_card.id); } });
                }
            } else if (CFG.notes && !message.deleted && (message.sender_kind === 'user' || message.sender_kind === 'app')
                && ['message', 'note', 'decision'].indexOf(message.kind) !== -1) {
                // Anybody's message, kept as a copy in the person's own notes.
                items.push({ icon: 'bi-journal-plus', label: t('notes_save_message'), tool: true, action: function () { self.keepAsNote(message); } });
            }

            if (message.has_list && self.channel.can_post) {
                if (message.list_tasks && message.list_tasks.length) {
                    message.list_tasks.forEach(function (task) {
                        items.push({ icon: 'bi-list-check', label: t('open_list_task', task.number), action: function () {
                            taskDrawer.open(task.id, null, function () { self.sync(); });
                        } });
                    });
                } else {
                    items.push({ icon: 'bi-list-check', label: t('list_to_task'), action: function () { self.listToTask(message); } });
                }
            }

            if (message.can_mark && self.channel.can_post) {
                items.push({ icon: 'bi-patch-check', label: message.kind === 'decision' ? t('unmark') : t('mark_decision'), tool: true, action: function () {
                    self.mark(message, message.kind === 'decision' ? 'message' : 'decision');
                } });
                items.push({ icon: 'bi-sticky', label: message.kind === 'note' ? t('unmark') : t('mark_channel_note'), action: function () {
                    self.mark(message, message.kind === 'note' ? 'message' : 'note');
                } });
            }

            // Read receipts asked for, or no longer (workspace_acks.js).
            if (message.ack_can_request && window.PGWsAcks && !self.era) {
                items.push({ icon: message.ack ? 'bi-eye-slash' : 'bi-eye', label: message.ack ? t('ack_unrequest') : t('ack_request'), action: function () {
                    window.PGWsAcks.request(message, !message.ack, self, featureHelpers());
                } });
            }

            // Pinned to the top of the channel, one message at a time.
            if (self.channel.can_pin && !message.past && !message.deleted && !self.era) {
                var isPinned = !!(self.channel.pin && self.channel.pin.id === message.id);

                items.push({ icon: isPinned ? 'bi-pin-angle-fill' : 'bi-pin-angle', label: isPinned ? t('unpin_message') : t('pin_message'), action: function () {
                    self.pinMessage(isPinned ? null : message);
                } });
            }

            // Several messages at once: answered together, forwarded, kept in
            // a note.
            if (CFG.forwards && !message.deleted && (message.sender_kind === 'user' || message.sender_kind === 'app' || message.sender_kind === 'guest')
                && ['message', 'note', 'decision'].indexOf(message.kind) !== -1) {
                items.push({ icon: 'bi-check2-all', label: t('sel_start'), action: function () { self.startSelect(message); } });
            }

            items.push('-');
            items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () {
                copyText(absoluteUrl(CFG.urls.workspace + '?channel=' + self.channel.id + '&message=' + message.id));
            } });

            if (!message.deleted && message.kind !== 'system') {
                items.push({ icon: 'bi-eye', label: t('seen_by'), action: function () { self.seenBy(message); } });
            }

            if (message.file) {
                items.push({ icon: message.file.image ? 'bi-arrows-fullscreen' : 'bi-box-arrow-up-right', label: message.file.image ? t('view_image') : t('open_file'), action: function () {
                    if (message.file.image) {
                        self.showImage(message.file.url);
                    } else {
                        window.open(message.file.url, '_blank', 'noopener');
                    }
                } });

                // A picture is edited in the image editor: in place in the
                // person's own message, as a copy of their own otherwise.
                if (message.file.image && self.channel.can_post) {
                    items.push({ icon: 'bi-brush', label: message.mine ? t('file_edit_image') : t('file_edit_send'), tool: true, action: function () { self.editImage(message); } });
                }

                if (message.file.text) {
                    items.push({ icon: 'bi-file-earmark-text', label: t('file_edit_text'), tool: true, action: function () { self.openText(message); } });
                }
            }

            if (message.can_edit || message.can_delete || message.can_hide) {
                items.push('-');
            }

            if (message.can_edit) {
                items.push({ icon: 'bi-pencil', label: t('edit'), tool: true, action: function () { self.editMessage(message); } });
            }

            if (message.can_delete || message.can_hide) {
                items.push({ icon: 'bi-trash', label: t('delete'), tool: true, danger: true, action: function () { self.deleteMessage(message); } });
            }

            return items;
        },

        // Deleting: in a private channel of one's own there is nobody else,
        // so the message simply goes; elsewhere for oneself only, or - one's
        // own, or as staff - for everyone. Either way it leaves no trace.
        deleteMessage: function (message) {
            var self = this;
            var alone = self.channel.kind === 'private' && (self.channel.members || []).length <= 1;
            var scopes = [];

            if (message.can_hide && !alone) {
                scopes.push(['me', t('delete_for_me'), t('delete_for_me_help')]);
            }

            if (message.can_delete) {
                scopes.push(['everyone', alone ? t('delete') : t('delete_for_everyone'), alone ? '' : t('delete_for_everyone_help')]);
            }

            if (!scopes.length) {
                return;
            }

            var chosen = scopes[scopes.length - 1][0];
            var details = null;

            if (scopes.length > 1) {
                details = el('div', 'ws-delete-scopes');

                scopes.forEach(function (scope) {
                    var id = nextId('ws-del-');
                    var row = el('div', 'form-field');
                    var radio = el('input', 'radio');
                    radio.type = 'radio';
                    radio.name = 'ws-delete-scope';
                    radio.id = id;
                    radio.checked = (scope[0] === chosen);
                    radio.addEventListener('change', function () { chosen = scope[0]; });

                    var label = el('label', '', scope[1]);
                    label.htmlFor = id;
                    row.appendChild(radio);
                    row.appendChild(label);

                    if (scope[2]) {
                        row.appendChild(el('div', 'small fg-secondary', scope[2]));
                    }

                    details.appendChild(row);
                });
            }

            ask(scopes.length > 1 ? t('delete_which') : (chosen === 'me' ? t('delete_for_me_confirm') : t('delete_confirm')), t('delete'), true, details).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_delete', { message_id: message.id, scope: chosen }).then(function () {
                    message.deleted = true;
                    message.html = '';
                    message.file = null;
                    self.replaceMessage(message);
                }).catch(fail);
            });
        },

        messageTools: function (message) {
            var self = this;
            var tools = el('div', 'ws-msg-tools');

            if (message.can_react) {
                (BOOT.reactions || []).slice(0, 3).forEach(function (emoji) {
                    var quick = el('button', 'ws-tool-emoji', emoji);

                    quick.type = 'button';
                    quick.title = t('react_with', emoji);
                    quick.setAttribute('aria-label', quick.title);
                    quick.addEventListener('click', function () { self.react(message, emoji); });
                    tools.appendChild(quick);
                });

                var pick = button('', '', 'bi-emoji-smile', t('add_reaction'));
                pick.addEventListener('click', function (event) {
                    var box = pick.getBoundingClientRect();

                    event.stopPropagation();
                    emojiPicker(box.left, box.bottom + 4, function (emoji) { self.react(message, emoji, true); });
                });
                tools.appendChild(pick);
            }

            this.messageActions(message).forEach(function (item) {
                if (item === '-' || !item.tool || item.emojis) {
                    return;
                }

                var tool = button(item.danger ? 'ws-tool-danger' : '', '', item.icon, item.label);
                tool.addEventListener('click', item.action);
                tools.appendChild(tool);
            });

            var more = button('', '', 'bi-three-dots', t('more'));
            more.addEventListener('click', function (event) {
                var box = more.getBoundingClientRect();
                event.stopPropagation();
                ctxMenu(box.left, box.bottom + 4, this.messageActions(message));
            }.bind(this));
            tools.appendChild(more);

            return tools;
        },

        // The channel's greeting: what happened since the last visit, what is
        // waiting, and the one thing worth doing. Closed, it stays closed for
        // the rest of the day in this channel.
        briefingNode: function () {
            var self = this;
            var brief = self.briefing;
            var storeKey = 'ws-brief-closed';

            if (!brief) {
                return null;
            }

            try {
                if (window.localStorage.getItem(storeKey + ':' + brief.key)) {
                    return null;
                }
            } catch (error) {
                // No storage: the greeting simply shows.
            }

            var box = el('div', 'ws-brief');
            var badge = el('div', 'ws-brief-icon');
            badge.appendChild(icon(brief.icon || 'bi-stars'));
            box.appendChild(badge);

            var text = el('div', 'ws-grow');
            text.appendChild(el('div', 'ws-brief-greeting', brief.greeting));

            (brief.lines || []).forEach(function (line) {
                text.appendChild(el('div', 'ws-brief-line', line));
            });

            // Pinegrap AI's summary of what was missed, written once asked
            // for and kept on the greeting while the channel stays open.
            var buttons = el('div', 'ws-brief-buttons');

            if (brief.recap) {
                var recapBox = el('div', 'ws-brief-recap');
                var showRecap = function () {
                    clear(recapBox);
                    recapBox.appendChild(el('div', 'ws-brief-recap-text', brief.recap.text));
                    recapBox.appendChild(el('div', 'ws-brief-recap-by', t('brief_recap_by')));
                };

                text.appendChild(recapBox);

                if (brief.recap.text) {
                    showRecap();
                } else {
                    var channelId = self.channel.id;
                    var ask = button('btn btn-sm ws-brief-action', t('brief_recap'), 'bi-stars');
                    ask.addEventListener('click', function () {
                        ask.disabled = true;
                        clear(ask);
                        ask.appendChild(el('span', 'spinner-border spinner-border-sm me-1'));
                        ask.appendChild(document.createTextNode(t('brief_recap_wait')));

                        api('ws_brief_recap', { channel_id: channelId, since_id: brief.recap.since_id }).then(function (data) {
                            brief.recap.text = data.text || '';
                            ask.remove();
                            showRecap();
                        }).catch(function (error) {
                            ask.disabled = false;
                            clear(ask);
                            ask.appendChild(icon('bi-stars', 'me-1'));
                            ask.appendChild(document.createTextNode(t('brief_recap')));
                            fail(error);
                        });
                    });
                    buttons.appendChild(ask);
                }
            }

            text.appendChild(buttons);

            if (brief.advice && brief.advice.action !== 'none') {
                var go = button('btn btn-sm ws-brief-action', brief.advice.label, brief.advice.icon);
                go.addEventListener('click', function () {
                    if (brief.advice.action === 'tab') {
                        self.tab = brief.advice.target;
                        self.drawCenter();
                    } else if (brief.advice.action === 'message') {
                        var target = self.center.querySelector('[data-ws-message="' + brief.advice.target + '"]');

                        if (target) {
                            target.classList.add('ws-msg-highlight');
                            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        } else {
                            self.open(self.channel.id, brief.advice.target);
                        }
                    }
                });
                buttons.appendChild(go);
            } else if (brief.advice) {
                text.appendChild(el('div', 'ws-brief-hint', brief.advice.label));
            }

            box.appendChild(text);

            var close = button('btn-close ws-brief-close', '', '', t('close'));
            close.addEventListener('click', function () {
                try {
                    window.localStorage.setItem(storeKey + ':' + brief.key, '1');
                } catch (error) {
                    // Nothing to remember it in; it closes for now.
                }

                box.remove();
            });
            box.appendChild(close);

            return box;
        },

        // A file as it is shown in the conversation: a picture to open in the
        // viewer, a video and a sound that play in place, a PDF with a
        // preview, anything else as a link.
        filePreview: function (message) {
            var self = this;
            var file = message.file;
            var kind = file.kind || (file.image ? 'image' : 'file');

            if (kind === 'image') {
                var link = el('a', 'ws-image-link');
                link.href = file.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.title = t('view_image');

                var img = el('img');
                img.src = file.url;
                img.alt = file.name;
                img.loading = 'lazy';
                link.appendChild(img);
                link.appendChild(el('span', 'ws-image-name', file.name));
                link.addEventListener('click', function (event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
                        return;
                    }

                    event.preventDefault();
                    self.showImage(file.url);
                });

                return link;
            }

            // A sound, a voice message among them: play and pause, the time
            // and the speed (assets/js/workspace_voice.js); the browser's own
            // player below without it. Any sound may be answered with a
            // recording from its own microphone (voiceReply()).
            if ((kind === 'audio') && window.PGWsVoice) {
                return window.PGWsVoice.player(file, featureHelpers(), {
                    reply: self.voiceReplyOffered(message) ? function () { self.voiceReply(message); } : null
                });
            }

            if (kind === 'video' || kind === 'audio') {
                var box = el('div', 'ws-media ws-media-' + kind);
                var player = el(kind);
                player.src = file.url;
                player.controls = true;
                player.preload = 'metadata';

                if (kind === 'video') {
                    player.playsInline = true;
                }

                box.appendChild(player);

                var bar = el('div', 'ws-media-bar');
                bar.appendChild(icon(kind === 'video' ? 'bi-film' : 'bi-music-note-beamed'));
                bar.appendChild(el('span', 'ws-grow text-truncate', file.name));

                if (kind === 'video') {
                    var large = button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-arrows-fullscreen', t('preview'));
                    large.addEventListener('click', function () { previewFile(file); });
                    bar.appendChild(large);
                }

                var save = el('a', 'btn btn-sm btn-text theme-secondary py-0');
                save.href = file.url;
                save.setAttribute('download', file.name);
                save.title = t('download');
                save.appendChild(icon('bi-download'));
                bar.appendChild(save);
                box.appendChild(bar);

                return box;
            }

            var card = el('div', 'ws-file-card');
            card.appendChild(el('span', 'ws-file-ext ws-ext-' + (file.ext || 'file'), file.ext || '?'));

            var text = el('div', 'ws-grow');
            var name = el('a', 'ws-file-name');
            name.href = file.url;
            name.target = '_blank';
            name.rel = 'noopener';
            name.textContent = file.name;
            text.appendChild(name);
            card.appendChild(text);

            if (kind === 'pdf' || file.office) {
                var look = button('btn btn-sm btn-outline theme-secondary', t('preview'), 'bi-eye');
                look.addEventListener('click', function () { previewFile(file, function (result) { self.fileEdited(result); }); });
                card.appendChild(look);
            }

            if (file.text) {
                var read = button('btn btn-sm btn-outline theme-secondary', t('file_edit_text'), 'bi-file-earmark-text');
                read.addEventListener('click', function () { self.openText(message); });
                card.appendChild(read);
            }

            var get = el('a', 'btn btn-sm btn-text theme-secondary');
            get.href = file.url;
            get.setAttribute('download', file.name);
            get.title = t('download');
            get.appendChild(icon('bi-download'));
            card.appendChild(get);

            return card;
        },

        // A note shared in the channel: its name, its first words, who it
        // belongs to and who changed it last; it opens as it is now.
        noteCardNode: function (card) {
            var box = el('div', 'ws-nb-msg-card');
            var top = el('div', 'ws-nb-msg-card-head');

            top.appendChild(icon('bi-journal-text'));
            top.appendChild(el('span', 'ws-nb-msg-card-kind', t('notes_card')));
            top.appendChild(el('b', 'ws-grow text-truncate', card.name));
            box.appendChild(top);

            if (card.html) {
                var text = el('div', 'ws-nb-msg-card-text ws-nb-msg-card-html');
                setHtml(text, card.html);
                chipMenus(text);
                box.appendChild(text);
            } else if (card.excerpt) {
                box.appendChild(el('div', 'ws-nb-msg-card-text', card.excerpt));
            }

            var foot = el('div', 'ws-nb-msg-card-foot');

            if (card.owner) {
                foot.appendChild(el('span', '', t('notes_owner', card.owner.name)));
            }

            if (card.updated_by) {
                foot.appendChild(el('span', '', t('notes_last_change', card.updated_by.name, card.updated)));
            }

            var read = button('btn btn-sm btn-outline theme-secondary rounded-pill py-0 px-3 ms-auto', t('notes_card_open'), 'bi-eye');
            read.addEventListener('click', function (event) {
                event.stopPropagation();
                openNoteView(card.id);
            });
            foot.appendChild(read);

            if (noteCardEditable(card)) {
                var edit = button('btn btn-sm btn-outline theme-secondary rounded-pill py-0 px-3', t('edit'), 'bi-pencil-square');
                edit.addEventListener('click', function (event) {
                    event.stopPropagation();
                    openNoteEdit(card.id);
                });
                foot.appendChild(edit);
            }

            box.appendChild(foot);

            return box;
        },

        // The line under a message that mentions people who are not in the
        // channel.
        inviteNode: function (message) {
            var self = this;
            var box = el('div', 'ws-msg-invite');
            var names = message.invite.map(function (member) { return member.name; }).join(', ');

            box.appendChild(icon('bi-person-exclamation'));
            box.appendChild(el('span', 'ws-grow', t('mention_not_here', names)));

            var invite = button('btn btn-sm btn-outline theme-primary rounded-pill py-0 px-3', t('mention_invite'), 'bi-person-plus', t('mention_invite_help'));
            invite.addEventListener('click', function (event) {
                event.stopPropagation();
                invite.disabled = true;

                api('ws_mention_invite', { message_id: message.id }).then(function (data) {
                    toast(t('mention_invited', names), 'success');

                    if (data.message) {
                        self.replaceMessage(data.message);
                    }

                    self.sync();
                }).catch(function (error) {
                    invite.disabled = false;
                    fail(error);
                });
            });
            box.appendChild(invite);

            return box;
        },

        // A copy of the message in the person's own notes.
        keepAsNote: function (message) {
            api('ws_note_from_message', { message_id: message.id }).then(function (data) {
                toast(data.existing ? t('notes_kept_before') : t('notes_kept'), data.existing ? 'dark' : 'success', {
                    label: t('notes_open'),
                    href: CFG.urls.notes + '?note=' + data.note_id
                });
            }).catch(fail);
        },

        // The image editor on a picture of the conversation. In the person's
        // own message the edited picture takes the old one's place (which
        // stays as an earlier version); on anybody else's it is sent as the
        // person's own message in reply, the original left as it was.
        editImage: function (message) {
            var self = this;
            var mine = !!message.mine;

            toast(t('file_editor_loading'));

            loadImageEditor().then(function () {
                window.openImageEditor({
                    src: message.file.url,
                    fileName: message.file.name,
                    token: CFG.token,
                    saveUrl: CFG.urls.file_save + '?message=' + message.id + '&as=' + (mine ? 'replace' : 'send'),
                    saveLabel: mine ? t('file_edit_put') : t('file_edit_send'),
                    allowReplace: false,
                    allowCopy: true,
                    onSaved: function () {
                        toast(mine ? t('file_replaced') : t('file_copy_sent'), 'success');
                        self.sync(!mine);
                    }
                });

                // The picture keeps the format the editor gives it, so the
                // format picker of the editor's footer is not offered here.
                var format = document.getElementById('pgImageEditorType');

                if (format) {
                    format.classList.add('d-none');

                    if (format.previousElementSibling) {
                        format.previousElementSibling.classList.add('d-none');
                    }
                }
            }).catch(function () {
                toast(t('file_editor_missing'), 'danger');
            });
        },

        // A file of the conversation was edited: the message that now carries
        // the edited file is drawn again, or, for an edited copy sent as a new
        // message, the conversation is brought up to date.
        fileEdited: function (result) {
            if (result && result.message) {
                this.replaceMessage(result.message);
            } else {
                this.sync(true);
            }
        },

        // A text file of the conversation in a window: read it, and save it
        // over the person's own file or send an edited copy of anybody
        // else's. A Markdown file also shows as it reads, figures worked out.
        openText: function (message) {
            var self = this;

            api('ws_file_text', { message_id: message.id }).then(function (data) {
                var node = el('dialog', 'dialog ws-text-modal dialog-xl dialog-scrollable md-down:dialog-fullscreen');
                node.tabIndex = -1;
                node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
                    + '<div class="dialog-body"></div><div class="dialog-footer"></div>';
                node.querySelector('.dialog-title').appendChild(icon('bi-file-earmark-text', 'me-3'));
                node.querySelector('.dialog-title').appendChild(document.createTextNode(data.name));
                node.querySelector('.btn-close').setAttribute('aria-label', t('close'));

                var body = node.querySelector('.dialog-body');
                var footer = node.querySelector('.dialog-footer');
                var area = el('textarea', 'form-control ws-text-area');
                var view = el('div', 'ws-msg-body ws-text-view d-none');
                var writable = !!(data.can_replace || data.can_send);

                area.value = data.text;
                area.spellcheck = false;
                area.readOnly = !writable;
                area.setAttribute('aria-label', data.name);

                if (data.markdown) {
                    var tabs = el('div', 'btn-group btn-group-sm mb-3 ws-text-tabs');
                    var write = button('btn btn-outline theme-secondary active', t('file_text_write'), 'bi-pencil');
                    var read = button('btn btn-outline theme-secondary', t('file_text_view'), 'bi-eye');

                    write.addEventListener('click', function () {
                        write.classList.add('active');
                        read.classList.remove('active');
                        area.classList.remove('d-none');
                        view.classList.add('d-none');
                        area.focus();
                    });

                    read.addEventListener('click', function () {
                        read.classList.add('active');
                        write.classList.remove('active');
                        view.classList.remove('d-none');
                        area.classList.add('d-none');
                        setHtml(view, '');
                        view.appendChild(skeleton(2, 'ws-skeleton-sm'));

                        api('ws_calc', { body: area.value, markdown: true }).then(function (result) {
                            setHtml(view, result.html);
                        }).catch(fail);
                    });

                    tabs.appendChild(write);
                    tabs.appendChild(read);
                    body.appendChild(tabs);
                }

                body.appendChild(area);
                body.appendChild(view);

                footer.appendChild(el('div', 'ws-grow small fg-secondary', data.can_replace ? t('file_text_mine_help') : (data.can_send ? t('file_text_copy_help') : t('file_text_readonly'))));

                var close = button('btn btn-sm btn-text theme-secondary', t('close'));
                close.setAttribute('data-bs-dismiss', 'dialog');
                footer.appendChild(close);

                var instance = null;

                if (writable) {
                    var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', data.can_replace ? t('file_text_save') : t('file_text_send'), data.can_replace ? 'bi-check2' : 'bi-send');

                    save.addEventListener('click', function () {
                        if (area.value === data.text) {
                            toast(t('file_text_unchanged'));
                            return;
                        }

                        save.disabled = true;

                        api('ws_file_text_save', { message_id: message.id, text: area.value, as: data.can_replace ? 'replace' : 'send' }).then(function (result) {
                            instance.hide();
                            toast(data.can_replace ? t('file_replaced') : t('file_copy_sent'), 'success');

                            if (result.message) {
                                self.replaceMessage(result.message);
                            } else {
                                self.sync(true);
                            }
                        }).catch(function (error) {
                            save.disabled = false;
                            fail(error);
                        });
                    });

                    footer.appendChild(save);

                    area.addEventListener('keydown', function (event) {
                        if ((event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S')) {
                            event.preventDefault();
                            save.click();
                        }
                    });
                }

                document.body.appendChild(node);
                instance = pgUI.dialog.get(node);
                pgUI.dialog.on(node, 'hidden', function () { node.remove(); });
                pgUI.dialog.on(node, 'shown', function () { area.focus(); });
                instance.show();
            }).catch(fail);
        },

        // The files an edited file replaced, newest first.
        fileVersions: function (message, anchor) {
            api('ws_file_history', { message_id: message.id }).then(function (data) {
                var box = anchor.getBoundingClientRect();
                var items = [{ header: t('file_versions_title') }];

                (data.versions || []).forEach(function (version) {
                    items.push({
                        icon: 'bi-file-earmark',
                        label: version.name + ' · ' + version.time + (version.by ? ' · ' + t('file_versions_by', version.by) : ''),
                        action: function () {
                            if (version.url) {
                                window.open(version.url, '_blank', 'noopener');
                            }
                        }
                    });
                });

                ctxMenu(box.left, box.bottom + 4, items);
            }).catch(fail);
        },

        // Every picture in the conversation, the clicked one first in view.
        showImage: function (url) {
            var images = [];
            var start = 0;

            this.messages.forEach(function (message) {
                if (message.file && message.file.image && !message.deleted) {
                    if (message.file.url === url) {
                        start = images.length;
                    }

                    images.push({ src: message.file.url, alt: message.file.name, caption: (message.sender ? message.sender.name + ' · ' : '') + message.time + ' · ' + message.file.name });
                }
            });

            if (!images.length) {
                images.push({ src: url, alt: '', caption: '' });
            }

            viewImages(images, start);
        },

        mark: function (message, kind) {
            var self = this;

            api('ws_mark', { message_id: message.id, kind: kind }).then(function (data) {
                self.replaceMessage(data.message);
                self.sync();
                toast(kind === 'message' ? t('unmarked') : (kind === 'decision' ? t('marked_decision') : t('marked_note')), 'success');
            }).catch(fail);
        },

        // "synced" is a copy the sync brought; anything else is the answer
        // to something done here, and a sync already on its way with an
        // older copy must not draw over it.
        replaceMessage: function (message, synced) {
            var previous = null;

            if (!synced) {
                this.localAt = this.localAt || {};
                this.localAt[message.id] = Date.now();
            }

            for (var i = 0; i < this.messages.length; i++) {
                if (this.messages[i].id === message.id) {
                    this.messages[i] = message;
                    previous = null;

                    for (var k = i - 1; k >= 0; k--) {
                        if (!this.messages[k].deleted) {
                            previous = this.messages[k];
                            break;
                        }
                    }
                }
            }

            // Deleted: it goes from the conversation without a trace.
            if (message.deleted) {
                this.removeMessageNode(message.id);
                return;
            }

            // The message under a day line starts its group again.
            if (previous && (this.dayKey(previous) !== this.dayKey(message))) {
                previous = null;
            }

            var node = this.center.querySelector('[data-ws-message="' + message.id + '"]');

            if (node) {
                var fresh = this.messageNode(message, previous);
                node.parentNode.replaceChild(fresh, node);
            }
        },

        // Where a message is: in view it is highlighted, otherwise the
        // channel opens there.
        jumpTo: function (messageId, flash) {
            var target = this.center.querySelector('[data-ws-message="' + messageId + '"]');

            if (target) {
                if (flash) {
                    this.flash([messageId]);
                } else {
                    target.classList.add('ws-msg-highlight');
                }

                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            } else {
                if (flash) {
                    this.flashAfterOpen = [messageId];
                }

                this.open(this.channel.id, messageId);
            }
        },

        // Who of the channel has read as far as the message, and who not yet.
        seenBy: function (message) {
            api('ws_message_seen', { message_id: message.id }).then(function (data) {
                var node = offcanvas('ws-seen', t('seen_by'));
                var body = clear(node.querySelector('.drawer-body'));

                node.querySelector('.drawer-footer').classList.add('d-none');

                function group(title, people, iconName, emptyText) {
                    var head = el('div', 'ws-record-title mt-3');
                    head.appendChild(icon(iconName, 'me-1'));
                    head.appendChild(document.createTextNode(title + ' · ' + people.length));
                    body.appendChild(head);

                    if (!people.length) {
                        body.appendChild(el('div', 'small fg-secondary mb-3', emptyText));
                        return;
                    }

                    var list = el('div', 'ws-seen-list');

                    people.forEach(function (member) {
                        var row = el('div', 'ws-seen-row');
                        row.appendChild(personLink(avatar(member, '1.6rem'), member));
                        row.appendChild(personLink(el('span', '', member.name), member));
                        list.appendChild(row);
                    });

                    body.appendChild(list);
                }

                group(t('seen_by_read'), data.seen || [], 'bi-check2-all', t('seen_by_none'));
                group(t('seen_by_not_yet'), data.unseen || [], 'bi-clock', t('seen_by_everyone'));
                body.appendChild(el('div', 'small fg-secondary mt-5', t('seen_by_help')));
                showOffcanvas(node);
            }).catch(fail);
        },

        // Lights messages up for a moment: the ones a reply answers.
        flash: function (ids) {
            var self = this;

            (ids || []).forEach(function (id) {
                var node = self.center.querySelector('[data-ws-message="' + id + '"]');

                if (!node) {
                    return;
                }

                node.classList.remove('ws-msg-flash');
                void node.offsetWidth;
                node.classList.add('ws-msg-flash');
                window.setTimeout(function () { node.classList.remove('ws-msg-flash'); }, 2600);
            });
        },

        // ── Reactions ──

        reactionRow: function (message) {
            var self = this;
            var row = el('div', 'ws-reactions');

            message.reactions.forEach(function (group) {
                var chip = el('button', 'ws-react' + (group.mine ? ' ws-react-mine' : '') + (message.can_react ? '' : ' ws-react-readonly'));

                chip.type = 'button';
                chip.appendChild(el('span', 'ws-react-emoji', group.emoji));
                chip.appendChild(el('span', 'ws-react-count', String(group.count)));
                chip.title = t('reacted_by', group.who.join(', '), group.emoji);
                chip.setAttribute('aria-label', chip.title);
                chip.setAttribute('aria-pressed', group.mine ? 'true' : 'false');

                if (message.can_react) {
                    chip.addEventListener('click', function () { self.react(message, group.emoji); });
                }

                // Who reacted, for a screen without hover: a long press (or a
                // right click) lists them.
                onContext(chip, function () {
                    var items = [{ header: group.emoji + ' ' + group.count }];

                    group.who.forEach(function (name) {
                        items.push({ icon: 'bi-person', label: name, disabled: true, action: function () {} });
                    });

                    if (message.can_react) {
                        items.push('-');
                        items.push({
                            icon: group.mine ? 'bi-x-circle' : 'bi-plus-circle',
                            label: group.mine ? t('reaction_take_back') : t('react_with', group.emoji),
                            action: function () { self.react(message, group.emoji); }
                        });
                    }

                    return items;
                });

                row.appendChild(chip);
            });

            if (message.can_react) {
                var add = button('ws-react ws-react-add', '', 'bi-emoji-smile', t('add_reaction'));

                add.addEventListener('click', function (event) {
                    var box = add.getBoundingClientRect();

                    event.stopPropagation();
                    emojiPicker(box.left, box.bottom + 4, function (emoji) { self.react(message, emoji, true); });
                });
                row.appendChild(add);
            }

            return row;
        },

        // Leaves or takes back an emoji; "on" leaves it whatever was there.
        react: function (message, emoji, on) {
            var self = this;
            var data = { message_id: message.id, emoji: emoji };

            if (on) {
                data.on = 1;
            }

            api('ws_react', data).then(function (result) {
                self.replaceMessage(result.message);

                // The chip just left or taken back bounces.
                var node = self.center.querySelector('[data-ws-message="' + result.message.id + '"]');

                Array.prototype.forEach.call(node ? node.querySelectorAll('.ws-react') : [], function (chip) {
                    var face = chip.querySelector('.ws-react-emoji');

                    if (face && face.textContent === emoji) {
                        playMotion(chip, 'ws-bounce');
                    }
                });
            }).catch(fail);
        },

        // ── Checklists ──

        // Where a task made on this screen is posted, and whose department it
        // takes: this channel.
        taskDefaults: function () {
            return { channel_id: this.channel.id, department_id: this.channel.department ? this.channel.department.id : 0 };
        },

        // The whole list becomes one task; the list stays here and both
        // places tick the same items.
        listToTask: function (message) {
            var self = this;
            var box = document.createElement('div');

            box.innerHTML = message.html || '';

            var first = box.querySelector('.ws-text');
            var title = first ? (first.textContent || '').replace(/\s+/g, ' ').trim().replace(/[:：]$/, '') : '';

            taskDrawer.open(0, Object.assign(self.taskDefaults(), {
                title: (title || t('list_task_title')).slice(0, 200),
                checklist_message_id: message.id,
                list_count: box.querySelectorAll('input[data-ws-check]').length
            }), function () { self.sync(true); });
        },

        // ── Claude ──

        claudeStateNode: function (message) {
            var self = this;
            var state = message.claude;
            var line = el('div', 'ws-claude-state ws-claude-' + state.status);

            line.appendChild(icon(state.icon));
            line.appendChild(el('span', '', state.label));

            // A request to an MCP agent can be stopped while it waits or
            // runs, by the one who asked or an administrator (mcp_agent.php).
            if (state.cancel) {
                var stop = el('a', 'ms-1', t('mcp_agent_stop'));

                stop.href = '#';
                stop.addEventListener('click', function (event) {
                    event.preventDefault();

                    ask(t('mcp_agent_stop_ask'), t('mcp_agent_stop'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        api('ws_mcp_agent_cancel', { request_id: state.id }).then(function () {
                            self.sync();
                        }).catch(fail);
                    });
                });

                line.appendChild(stop);
            }

            return line;
        },

        // Pinegrap AI answers one model call at a time, carried on by the
        // screens waiting for it (includes/workspace/ai.php): one call per
        // screen at a time, and not more often than every few seconds.
        kickAi: function (now) {
            var self = this;

            if (self.aiKicking || (!now && (Date.now() - (self.aiKickAt || 0)) < 4000)) {
                return;
            }

            self.aiKicking = true;
            self.aiKickAt = Date.now();

            api('ws_ai_kick').then(function () {
                self.aiKicking = false;
                self.sync();
            }).catch(function () {
                self.aiKicking = false;
            });
        },

        // An MCP server that is an agent itself answers a message at a time,
        // sent by the screens waiting for it (includes/workspace/mcp_agent.php).
        kickAgent: function (now) {
            var self = this;

            if (self.agentKicking || (!now && (Date.now() - (self.agentKickAt || 0)) < 4000)) {
                return;
            }

            self.agentKicking = true;
            self.agentKickAt = Date.now();

            api('ws_mcp_agent_kick').then(function () {
                self.agentKicking = false;
                self.sync();
            }).catch(function () {
                self.agentKicking = false;
            });
        },

        // Product drafts as one bulk card (bulk_sources.php): from a file of
        // the channel, or from a list pasted into the question window. The
        // card waits under a message of the person's own until they apply it.
        bulkMakeProducts: function (source) {
            var self = this;
            var channel = self.channel;
            var text = null;
            var asked = Promise.resolve(true);

            if (!source.message_id) {
                text = el('textarea', 'form-control form-control-sm');
                text.rows = 8;
                text.placeholder = t('bulk_make_placeholder');
                text.setAttribute('aria-label', t('bulk_make_products'));
                asked = ask(t('bulk_make_intro'), t('bulk_make_go'), false, text);
                window.setTimeout(function () { text.focus(); }, 200);
            }

            asked.then(function (yes) {
                if (!yes || (text && !text.value.trim())) {
                    return;
                }

                api('ws_bulk_create_propose', {
                    channel_id: channel.id,
                    message_id: source.message_id || 0,
                    text: text ? text.value : ''
                }).then(function (data) {
                    toast(t('bulk_make_done', data.count) + ((data.ignored && data.ignored.length) ? ' ' + t('bulk_make_ignored', data.ignored.join(', ')) : ''), 'success');
                    self.sync(true);
                }).catch(fail);
            });
        },

        // Record changes an answer proposes: what each field holds and would
        // hold. Only the person who asked applies them, with their own rights.
        // A bulk change being applied: the next slice, one at a time, until
        // it is done; the card is drawn again by the sync.
        kickBulk: function (changeId) {
            var self = this;

            self.bulkRunning = self.bulkRunning || {};

            if (self.bulkRunning[changeId]) {
                return;
            }

            self.bulkRunning[changeId] = true;

            api('ws_ai_bulk_step', { change_id: changeId }).then(function (data) {
                window.setTimeout(function () {
                    self.bulkRunning[changeId] = false;
                    self.sync();
                }, (data.status === 'busy') ? 2000 : 300);
            }).catch(function () {
                self.bulkRunning[changeId] = false;
            });
        },

        changesNode: function (message) {
            var self = this;
            var box = el('div', 'ws-drafts ws-changes');

            // A message to an MCP server's own agent whose tool writes waits
            // under itself for its asker's approval (mcp_agent.php).
            var agent = message.changes.every(function (change) { return change.agent; });

            // Scheduled actions Pinegrap AI drafted (scheduled_draft.php) are
            // opened in their form and saved there, not applied.
            var drafts = message.changes.every(function (change) { return change.action === 'draft'; });

            // Changes an application proposed (proposals.php) are headed by
            // its name.
            var proposer = message.changes[0].proposer || '';

            box.appendChild(el('div', 'ws-drafts-title', proposer ? t('proposal_changes', proposer) : t(agent ? 'mcp_agent_changes' : (drafts ? 'ai_scheduled' : ((message.sender && message.sender.ai) ? 'ai_changes' : 'claude_changes')))));

            message.changes.forEach(function (change) {
                var card = el('div', 'ws-draft ws-change ws-change-' + change.status + ' ws-change-do-' + change.action);
                var head = el('div', 'ws-draft-head');
                var icons = { create: 'bi-plus-square', 'delete': 'bi-trash3', add: 'bi-box-arrow-in-right', remove: 'bi-box-arrow-right', bulk: 'bi-collection', run: 'bi-play-circle', draft: 'bi-alarm' };

                head.appendChild(icon(change.status === 'applied' ? 'bi-check2-circle' : (icons[change.action] || 'bi-pencil-square')));

                // A record that is not made yet, or is gone, is named by the
                // proposal itself.
                var what = el('span', 'ws-change-record');

                if (change.record_html) {
                    setHtml(what, change.record_html);
                } else if (change.record_label) {
                    what.appendChild(el('b', '', change.record_label));
                }

                head.appendChild(what);

                if (change.action_label) {
                    head.appendChild(el('span', 'ws-change-action', change.action_label));
                }

                head.appendChild(el('span', 'ws-change-type', change.type_label));
                card.appendChild(head);

                // Many records at once (bulk.php): how many, a few of them
                // below, and while it is applied how far it has got.
                if (change.bulk) {
                    var reach = el('div', 'ws-bulk-reach');
                    reach.appendChild(el('b', '', t('bulk_records', change.bulk.total)));

                    if (change.fields.length) {
                        reach.appendChild(document.createTextNode(' · ' + t('bulk_sample')));
                    }

                    card.appendChild(reach);
                }

                if (change.hidden) {
                    card.appendChild(el('div', 'ws-draft-meta', t('change_hidden', change.count)));
                } else {
                    var list = el('dl', 'ws-change-fields');

                    change.fields.forEach(function (field) {
                        list.appendChild(el('dt', '', field.label));

                        var value = el('dd');

                        if (field.matrix) {
                            // A variant set: its attributes, a row per variant
                            // and the price each will be written with.
                            var wrap = el('div', 'table-responsive');
                            var table = el('table', 'table table-sm table-borderless mb-0 ws-change-matrix');
                            var headRow = el('tr');

                            field.matrix.head.forEach(function (name, index) {
                                var th = el('th', (index === field.matrix.head.length - 1) ? 'text-end' : '', name);
                                th.setAttribute('scope', 'col');
                                headRow.appendChild(th);
                            });

                            table.appendChild(el('thead')).appendChild(headRow);

                            var body = table.appendChild(el('tbody'));

                            field.matrix.rows.forEach(function (row) {
                                var tr = el('tr');

                                row.options.forEach(function (option) {
                                    tr.appendChild(el('td', '', option));
                                });

                                tr.appendChild(el('td', 'font-monospace', row.sku));

                                var price = el('td', 'text-end text-nowrap', row.price);

                                if (row.manual) {
                                    var mark = icon('bi-pencil', 'ms-1 fg-secondary');
                                    mark.setAttribute('title', field.matrix.manual);
                                    price.appendChild(mark);
                                    price.appendChild(el('span', 'visually-hidden', ' (' + field.matrix.manual + ')'));
                                }

                                tr.appendChild(price);
                                body.appendChild(tr);
                            });

                            wrap.appendChild(table);
                            value.appendChild(wrap);
                        } else if (field.html) {
                            // Records the change names: products, a parent group.
                            var chips = el('span', 'ws-change-to');
                            setHtml(chips, field.html);
                            value.appendChild(chips);
                        } else if ((field.to === '') && (field.from !== '')) {
                            // What a deleted record held.
                            value.appendChild(el('span', 'ws-change-held', field.from));
                        } else {
                            if (field.from !== '') {
                                value.appendChild(el('span', 'ws-change-from', field.from));
                                value.appendChild(icon('bi-arrow-right', 'ws-change-arrow'));
                            }

                            value.appendChild(el('span', 'ws-change-to', field.to));
                        }

                        list.appendChild(value);
                    });

                    card.appendChild(list);
                }

                if (change.reason) {
                    card.appendChild(el('div', 'ws-draft-text', change.reason));
                }

                if (change.bulk && change.bulk.progress && (change.status === 'applying' || change.status === 'applied' || change.status === 'failed')) {
                    var progress = change.bulk.progress;
                    var share = progress.total ? Math.round((progress.done + progress.skipped + progress.failed) * 100 / progress.total) : 100;
                    var meter = el('div', 'progress ws-bulk-progress');
                    var fill = el('div', 'progress-bar' + (change.status === 'applying' ? ' progress-bar-striped progress-bar-animated' : ''));

                    meter.setAttribute('role', 'progressbar');
                    meter.setAttribute('aria-valuenow', String(share));
                    meter.setAttribute('aria-valuemin', '0');
                    meter.setAttribute('aria-valuemax', '100');
                    fill.style.width = share + '%';
                    meter.appendChild(fill);
                    card.appendChild(meter);
                    card.appendChild(el('div', 'ws-draft-meta', (change.status === 'applying' ? t('bulk_running') + ' ' + t('bulk_progress', progress.done + progress.skipped + progress.failed, progress.total) + ' · ' : '')
                        + t(change.bulk.create ? 'bulk_create_result' : 'bulk_result', progress.done, progress.skipped, progress.failed)));

                    (progress.errors || []).forEach(function (error) {
                        card.appendChild(el('div', 'small fg-emphasis-danger', error));
                    });

                    // The one who applied it carries the work on from here.
                    if (change.status === 'applying' && change.bulk.mine) {
                        self.kickBulk(change.id);
                    }
                }

                if (change.warning && change.status === 'pending') {
                    var warning = el('div', 'ws-change-warning');
                    warning.appendChild(icon('bi-exclamation-triangle', 'me-1'));
                    warning.appendChild(document.createTextNode(change.warning));
                    card.appendChild(warning);
                }

                var actions = el('div', 'ws-draft-actions');

                if (change.can_apply) {
                    var apply = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('change_apply'), 'bi-check2');

                    apply.addEventListener('click', function () {
                        var question = change.bulk ? t(change.bulk.delete ? 'bulk_delete_warning' : (change.bulk.create ? 'bulk_create_warning' : 'bulk_warning'), change.bulk.total) : change.warning;
                        var go = question ? ask(question, t('change_apply'), true) : Promise.resolve(true);

                        go.then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            apply.disabled = true;

                            if (dismiss) {
                                dismiss.disabled = true;
                            }

                            api('ws_ai_change_apply', { change_id: change.id }).then(function () {
                                toast(t(change.agent ? 'mcp_agent_sent' : 'change_done'), 'success');
                                self.sync();
                            }).catch(function (error) {
                                fail(error);
                                self.sync();
                            });
                        });
                    });

                    actions.appendChild(apply);
                }

                // A drafted scheduled action opens as a new flow of the one
                // who asked, on the Flows canvas; what the flow keeps
                // otherwise is told before it opens.
                if (change.draft) {
                    var open = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('sa_draft_open'), 'bi-diagram-3');

                    open.addEventListener('click', function () {
                        open.disabled = true;

                        api('ws_flow_from_draft', { change_id: change.id }).then(function (result) {
                            var go = function () {
                                window.location.href = CFG.urls.flows + '?flow=' + result.flow_id;
                            };

                            if (!result.left || !result.left.length) {
                                go();
                                return;
                            }

                            var list = el('ul', 'mb-0');

                            result.left.forEach(function (line) {
                                list.appendChild(el('li', '', line));
                            });

                            ask(t('sa_draft_flow_left'), t('sa_draft_flow_see'), false, list).then(function (yes) {
                                if (yes) {
                                    go();
                                } else {
                                    self.sync();
                                }
                            });
                        }).catch(function (error) {
                            open.disabled = false;
                            fail(error);
                        });
                    });

                    actions.appendChild(open);
                }

                var dismiss = null;

                if (change.can_dismiss) {
                    dismiss = button('btn btn-sm btn-text theme-secondary', t('change_dismiss'), 'bi-x-lg');

                    dismiss.addEventListener('click', function () {
                        dismiss.disabled = true;
                        api('ws_ai_change_dismiss', { change_id: change.id }).then(function () { self.sync(); }).catch(function (error) {
                            dismiss.disabled = false;
                            fail(error);
                        });
                    });

                    actions.appendChild(dismiss);
                }

                // A call of an MCP server's tool (mcp_ask.php) runs with the
                // asker's own account, connected from here.
                if (change.connect) {
                    var connect = button('btn btn-sm btn-outline theme-primary rounded-pill px-5', t(change.connect.again ? 'mcp_connect_again' : 'mcp_connect_me'), 'bi-box-arrow-in-right');

                    connect.addEventListener('click', function () {
                        connect.disabled = true;
                        mcpConnect(change.connect.server_id, self.channel ? self.channel.id : 0, function () {
                            connect.disabled = false;
                        });
                    });

                    actions.appendChild(connect);
                }

                if (actions.childNodes.length) {
                    card.appendChild(actions);
                }

                if (change.status === 'pending' && change.draft) {
                    // The one who asked has the form to open: nothing to wait for.
                } else if (change.status === 'pending' && !change.can_apply) {
                    card.appendChild(el('div', 'ws-draft-done', change.blocked || (change.no_right ? t('change_no_right') : (change.proposer ? t('proposal_change_waiting', change.asker, change.proposer) : t(change.action === 'draft' ? 'sa_draft_waiting' : 'change_waiting', change.asker)))));
                } else if (change.status === 'pending' && change.account) {
                    var account = el('div', 'ws-draft-done', t('mcp_account', change.account.alias) + ' ');
                    var off = el('a', '', t('mcp_disconnect_me'));

                    off.href = '#';
                    off.addEventListener('click', function (event) {
                        event.preventDefault();

                        ask(t('mcp_disconnect_ask', change.account.alias), t('mcp_disconnect_me'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            api('ws_mcp_disconnect_me', { server_id: change.account.server_id }).then(function () {
                                toast(t('mcp_disconnected'), 'success');
                                self.sync();
                            }).catch(fail);
                        });
                    });

                    account.appendChild(off);
                    card.appendChild(account);
                } else if (change.status === 'applied') {
                    var done = el('div', 'ws-draft-done', t(change.action === 'draft' ? (change.flow_id ? 'sa_draft_flowed' : 'sa_draft_saved') : 'change_applied', change.decided_by, change.decided) + ' ');

                    if (change.flow_id) {
                        var flow = el('a', '', t('sa_draft_flow_see'));
                        flow.href = CFG.urls.flows + '?flow=' + change.flow_id;
                        done.appendChild(flow);
                    } else if (change.decision_id) {
                        var see = el('a', '', t(change.action === 'draft' ? 'sa_draft_see' : 'change_see_decision'));
                        see.href = '#';
                        see.addEventListener('click', function (event) {
                            event.preventDefault();
                            self.jumpTo(change.decision_id);
                        });
                        done.appendChild(see);
                    }

                    card.appendChild(done);
                } else if (change.status === 'dismissed') {
                    card.appendChild(el('div', 'ws-draft-done', t('change_dismissed', change.decided_by)));
                } else if (change.status === 'stale') {
                    card.appendChild(el('div', 'ws-draft-done fg-emphasis-warning', change.proposer ? t('proposal_change_stale', change.proposer) : t((message.sender && message.sender.ai) ? 'ai_change_stale' : 'change_stale')));
                } else if (change.status === 'failed') {
                    card.appendChild(el('div', 'ws-draft-done fg-emphasis-danger', t('change_failed', change.error)));
                }

                box.appendChild(card);
            });

            return box;
        },

        // Page changes an answer proposes (includes/designer_ai.php): what they
        // do in a line or two, a preview in the editor, and - for the person
        // who asked - applying it to the saved page or taking it back.
        designNode: function (message) {
            var self = this;
            var box = el('div', 'ws-drafts ws-changes ws-design');

            box.appendChild(el('div', 'ws-drafts-title', t((message.sender && message.sender.ai) ? 'design_title_ai' : 'design_title_claude')));

            message.design.forEach(function (proposal) {
                var card = el('div', 'ws-draft ws-change ws-change-' + proposal.status);
                var head = el('div', 'ws-draft-head');

                head.appendChild(icon(proposal.status === 'applied' ? 'bi-check2-circle' : 'bi-palette'));
                head.appendChild(el('b', 'ws-change-record', proposal.page_name || ('#' + proposal.page_id)));
                head.appendChild(el('span', 'ws-change-type', t('design_changes', proposal.ops_count) + (proposal.whole_page ? ' · ' + t('design_whole') : '')));
                card.appendChild(head);

                if (proposal.summary) {
                    card.appendChild(el('div', 'ws-draft-text', proposal.summary));
                }

                if (proposal.removed && proposal.removed.length) {
                    var removed = el('div', 'ws-change-warning');
                    removed.appendChild(icon('bi-exclamation-triangle', 'me-1'));
                    removed.appendChild(document.createTextNode(t('design_removes', proposal.removed.join(', '))));
                    card.appendChild(removed);
                }

                if (proposal.dropped && proposal.dropped.length) {
                    card.appendChild(el('div', 'ws-draft-meta', t('design_dropped', proposal.dropped.join(', '))));
                }

                var actions = el('div', 'ws-draft-actions');

                if (proposal.can_open && proposal.edit_url) {
                    var preview = el('a', 'btn btn-sm btn-text theme-secondary', '');
                    preview.href = proposal.edit_url;
                    preview.target = '_blank';
                    preview.rel = 'noopener';
                    preview.appendChild(icon('bi-eye', 'me-1'));
                    preview.appendChild(document.createTextNode(t('design_open')));
                    actions.appendChild(preview);
                }

                var buttons = [];
                var act = function (label, iconName, name, question, done) {
                    var b = button(name === 'apply' ? 'btn btn-sm btn-solid theme-primary rounded-pill px-5' : 'btn btn-sm btn-text theme-secondary', label, iconName);

                    b.addEventListener('click', function () {
                        var go = question ? ask(question, label, name !== 'dismiss') : Promise.resolve(true);

                        go.then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            buttons.forEach(function (other) { other.disabled = true; });

                            api('ws_ai_design_' + name, { proposal_id: proposal.id }).then(function () {
                                if (done) {
                                    toast(done, 'success');
                                }

                                self.sync();
                            }).catch(function (error) {
                                fail(error);
                                self.sync();
                            });
                        });
                    });

                    buttons.push(b);
                    actions.appendChild(b);
                };

                if (proposal.can_apply) {
                    act(t('design_apply'), 'bi-check2', 'apply', t('design_apply_ask'), t('design_done'));
                }

                if (proposal.can_revert) {
                    act(t('design_revert'), 'bi-arrow-counterclockwise', 'revert', '', t('design_undone'));
                }

                if (proposal.can_dismiss) {
                    act(t('design_dismiss'), 'bi-x-lg', 'dismiss', '', '');
                }

                if (actions.childNodes.length) {
                    card.appendChild(actions);
                }

                if (proposal.gone) {
                    card.appendChild(el('div', 'ws-draft-done fg-emphasis-warning', t('design_gone')));
                } else if (proposal.status === 'pending' && !proposal.can_apply) {
                    card.appendChild(el('div', 'ws-draft-done', t('design_waiting', proposal.asker)));
                } else if (proposal.status === 'applied') {
                    card.appendChild(el('div', 'ws-draft-done', t(proposal.applied_to === 'page' ? 'design_applied_page' : 'design_applied_editor', proposal.decider)));
                } else if (proposal.status === 'reverted') {
                    card.appendChild(el('div', 'ws-draft-done', t('design_reverted', proposal.decider)));
                } else if (proposal.status === 'dismissed') {
                    card.appendChild(el('div', 'ws-draft-done', t('design_dismissed', proposal.decider)));
                } else if (proposal.status === 'stale') {
                    card.appendChild(el('div', 'ws-draft-done fg-emphasis-warning', t('design_stale')));
                }

                box.appendChild(card);
            });

            return box;
        },

        draftsNode: function (message) {
            var self = this;
            var box = el('div', 'ws-drafts');

            box.appendChild(el('div', 'ws-drafts-title', t((message.sender && message.sender.ai) ? 'ai_drafts' : 'claude_drafts')));

            message.drafts.forEach(function (draft) {
                var card = el('div', 'ws-draft ws-draft-' + draft.status);
                var head = el('div', 'ws-draft-head');

                head.appendChild(icon(draft.status === 'accepted' ? 'bi-check2-square' : 'bi-square'));
                head.appendChild(el('b', '', draft.title));
                card.appendChild(head);

                var meta = [];

                if (draft.people.length) {
                    meta.push(draft.people.join(', '));
                }

                if (draft.due) {
                    meta.push(draft.due);
                }

                if (draft.priority && draft.priority !== 'normal') {
                    meta.push(draft.priority_label);
                }

                if (meta.length) {
                    card.appendChild(el('div', 'ws-draft-meta', meta.join(' · ')));
                }

                if (draft.description) {
                    card.appendChild(el('div', 'ws-draft-text', draft.description));
                }

                if (draft.can_decide) {
                    var actions = el('div', 'ws-draft-actions');
                    var accept = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('claude_draft_open'), 'bi-plus-lg');
                    var dismiss = button('btn btn-sm btn-text theme-secondary', t('claude_draft_dismiss'), 'bi-x-lg');

                    accept.addEventListener('click', function () {
                        accept.disabled = dismiss.disabled = true;
                        api('ws_ai_draft_accept', { draft_id: draft.id }).then(function () {
                            toast(t('claude_draft_opened'), 'success');
                            self.sync();
                        }).catch(function (error) {
                            accept.disabled = dismiss.disabled = false;
                            fail(error);
                        });
                    });

                    dismiss.addEventListener('click', function () {
                        accept.disabled = dismiss.disabled = true;
                        api('ws_ai_draft_dismiss', { draft_id: draft.id }).then(function () { self.sync(); }).catch(function (error) {
                            accept.disabled = dismiss.disabled = false;
                            fail(error);
                        });
                    });

                    actions.appendChild(accept);
                    actions.appendChild(dismiss);
                    card.appendChild(actions);
                } else if (draft.status === 'accepted') {
                    var opened = button('btn btn-sm btn-link p-0 ws-draft-done', t('claude_draft_accepted', draft.task_number, draft.decided_by));
                    opened.addEventListener('click', function () {
                        taskDrawer.open(draft.task_id, null, function () { self.sync(); });
                    });
                    card.appendChild(opened);
                } else if (draft.status === 'dismissed') {
                    card.appendChild(el('div', 'ws-draft-done', t('claude_draft_dismissed', draft.decided_by)));
                }

                box.appendChild(card);
            });

            return box;
        },

        listTasksNode: function (message) {
            var self = this;
            var row = el('div', 'ws-list-tasks');

            message.list_tasks.forEach(function (task) {
                var chip = button('ws-list-task' + (task.open ? '' : ' ws-list-task-closed'), '', 'bi-list-check');

                chip.appendChild(el('span', 'ws-list-task-number', task.number));
                chip.appendChild(el('span', 'ws-list-task-title', task.title));

                if (task.progress) {
                    chip.appendChild(progressBar(task.progress, true));
                    chip.appendChild(el('span', 'ws-list-task-share', t('percent', task.progress.percent)));
                }

                chip.title = task.number + ' · ' + task.title + ' · ' + task.status;
                chip.addEventListener('click', function () {
                    taskDrawer.open(task.id, null, function () { self.sync(); });
                });
                row.appendChild(chip);
            });

            return row;
        },

        // Makes one checklist item a task (ws_check_task); the redrawn
        // message shows its tag beside the item.
        itemTask: function (message, button) {
            var self = this;

            if (button.disabled) {
                return;
            }

            button.disabled = true;

            api('ws_check_task', {
                message_id: message.id,
                item: parseInt(button.getAttribute('data-ws-item-task'), 10)
            }).then(function (result) {
                self.replaceMessage(result.message);
            }).catch(function (error) {
                button.disabled = false;
                fail(error);
            });
        },

        bindChecks: function (body, message) {
            var self = this;

            Array.prototype.forEach.call(body.querySelectorAll('[data-ws-item-task]'), function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.itemTask(message, button);
                });
            });

            Array.prototype.forEach.call(body.querySelectorAll('input[data-ws-check]'), function (box) {
                box.addEventListener('change', function () {
                    box.disabled = true;

                    api('ws_check', {
                        message_id: message.id,
                        item: parseInt(box.getAttribute('data-ws-check'), 10),
                        checked: box.checked ? 1 : 0
                    }).then(function (result) {
                        self.replaceMessage(result.message);
                    }).catch(function (error) {
                        box.checked = !box.checked;
                        box.disabled = false;
                        fail(error);
                    });
                });
            });
        },

        // ── Polls ──

        pollNode: function (message) {
            var self = this;
            var poll = message.poll;
            var card = el('div', 'ws-poll' + (poll.closed ? ' ws-poll-closed' : ''));
            var head = el('div', 'ws-poll-head');
            // Shares are of the people who voted, so a multiple-choice poll
            // reads "3 of 4 people", not a share of every tick.
            var voters = poll.voters || 0;

            head.appendChild(icon('bi-bar-chart-line'));
            head.appendChild(el('span', 'fw-semibold', t('poll_title')));

            if (poll.multiple) {
                head.appendChild(el('span', 'ws-poll-tag', t('poll_multiple')));
            }

            if (poll.anonymous) {
                head.appendChild(el('span', 'ws-poll-tag', t('poll_anonymous')));
            }

            head.appendChild(el('span', 'ws-poll-when', poll.closed ? t('poll_closed_on', poll.closed_on)
                : (poll.closes ? t('poll_closes', poll.closes) : t('poll_open'))));
            card.appendChild(head);

            var list = el('div', 'ws-poll-options');
            var group = nextId('ws-poll-' + poll.id + '-');

            poll.options.forEach(function (option) {
                var row = el('label', 'ws-poll-option' + (option.mine ? ' ws-poll-mine' : '') + ((poll.closed && option.leading) ? ' ws-poll-winner' : ''));
                var share = voters ? Math.round(option.count * 100 / voters) : 0;
                var bar = el('span', 'ws-poll-bar');
                var input = el('input', poll.multiple ? 'check' : 'radio');

                bar.style.width = share + '%';
                row.appendChild(bar);

                input.type = poll.multiple ? 'checkbox' : 'radio';
                input.name = group;
                input.value = String(option.id);
                input.checked = !!option.mine;
                input.disabled = !poll.can_vote;
                input.addEventListener('change', function () { self.vote(message, list); });
                row.appendChild(input);

                row.appendChild(el('span', 'ws-poll-label', option.label));

                if (option.voters && option.voters.length) {
                    var faces = avatarStack('ws-avatars-sm', option.voters, 4);

                    row.title = option.voters.map(function (voter) { return voter.name; }).join(', ');
                    row.appendChild(faces);
                }

                row.appendChild(el('span', 'ws-poll-count', voters ? t('poll_share', option.count, share) : '0'));
                list.appendChild(row);
            });

            card.appendChild(list);

            var foot = el('div', 'ws-poll-foot');
            foot.appendChild(el('span', 'ws-grow', t('poll_voters', poll.voters)));

            if (poll.voted && poll.can_vote) {
                var undo = button('btn btn-sm btn-text theme-secondary', t('poll_withdraw'), 'bi-arrow-counterclockwise');
                undo.addEventListener('click', function () { self.vote(message, []); });
                foot.appendChild(undo);
            }

            if (poll.result_id) {
                var result = button('btn btn-sm btn-text theme-secondary', t('poll_result'), 'bi-patch-check');
                result.addEventListener('click', function () { self.jumpTo(poll.result_id); });
                foot.appendChild(result);
            }

            if (poll.can_close) {
                var shut = button('btn btn-sm btn-outline theme-secondary', t('poll_close'), 'bi-lock');
                shut.addEventListener('click', function () {
                    ask(t('poll_close_confirm'), t('poll_close')).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        api('ws_poll_close', { poll_id: poll.id }).then(function (data) {
                            self.replaceMessage(data.message);
                            self.sync(true);
                        }).catch(fail);
                    });
                });
                foot.appendChild(shut);
            }

            card.appendChild(foot);

            return card;
        },

        // Sends the ticked options; an empty list takes the vote back.
        vote: function (message, choice) {
            var self = this;
            var ids = Array.isArray(choice) ? choice : Array.prototype.map.call(choice.querySelectorAll('input:checked'), function (input) {
                return parseInt(input.value, 10);
            });

            api('ws_poll_vote', { poll_id: message.poll.id, option_ids: ids }).then(function (data) {
                self.replaceMessage(data.message);
            }).catch(function (error) {
                fail(error);
                self.sync();
            });
        },

        pollForm: function (defaults, onCreated, editing) {
            var self = this;
            var poll = editing ? editing.poll : null;
            var node = offcanvas('ws-poll-form', t('new_poll'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));
            var questionLabels = [];

            node.querySelector('.drawer-title').textContent = poll ? t('edit_poll') : t('new_poll');
            defaults = defaults || {};
            footer.classList.remove('d-none');

            // Editing starts from the poll as it stands. Tags in the question
            // are shown by name and turned back into tags when it is saved.
            if (poll) {
                var raw = editing.raw || '';

                Object.keys(editing.labels || {}).forEach(function (token) {
                    var shown = shownLabel(token.charAt(1), editing.labels[token]);

                    raw = raw.split(token).join(shown);
                    questionLabels.push({ label: shown, token: token });
                });

                defaults = {
                    question: raw,
                    options: poll.options.map(function (option) {
                        return { id: option.id, label: option.label, count: option.count };
                    })
                };
            }

            var question = el('textarea', 'form-control form-control-sm');
            question.rows = 2;
            question.maxLength = 1000;
            question.id = nextId('ws-poll-q-');
            question.value = defaults.question || '';
            body.appendChild(formRow(t('poll_question'), question));

            var optionsWrap = el('div');
            var optionsBox = el('div', 'ws-poll-form-options');
            var addOption = button('btn btn-sm btn-text theme-secondary', t('poll_add_option'), 'bi-plus-lg');

            function renumber() {
                Array.prototype.forEach.call(optionsBox.children, function (line, index) {
                    var input = line.querySelector('input');
                    input.placeholder = t('poll_option_n', index + 1);
                    input.setAttribute('aria-label', input.placeholder);
                });

                addOption.disabled = optionsBox.children.length >= 10;
            }

            function optionField(value) {
                var option = (value && typeof value === 'object') ? value : { id: 0, label: value || '', count: 0 };
                var voted = (option.count > 0);
                var line = el('div', 'input-group input-group-sm mb-1');
                var input = el('input', 'form-control' + (voted ? ' fg-secondary' : ''));
                var remove = button('btn btn-outline theme-secondary', '', voted ? 'bi-lock' : 'bi-x-lg', voted ? t('poll_option_voted') : t('remove'));

                input.maxLength = 255;
                input.value = option.label || '';
                line.setAttribute('data-option-id', option.id || 0);
                line.appendChild(input);
                line.appendChild(remove);

                // What somebody voted for stays as they saw it.
                if (voted) {
                    input.readOnly = true;
                    input.title = t('poll_option_voted');
                    remove.disabled = true;
                }

                remove.addEventListener('click', function () {
                    if (optionsBox.children.length > 2) {
                        line.remove();
                        renumber();
                    } else {
                        input.value = '';
                    }
                });

                // Enter goes to the next option, and past the last one adds one.
                input.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' || event.isComposing) {
                        return;
                    }

                    event.preventDefault();

                    if (line.nextElementSibling) {
                        line.nextElementSibling.querySelector('input').focus();
                    } else if (optionsBox.children.length < 10) {
                        optionField('').querySelector('input').focus();
                    }
                });

                optionsBox.appendChild(line);
                renumber();

                return line;
            }

            var given = (defaults.options || []).slice(0, 10);

            while (given.length < 2) {
                given.push('');
            }

            given.forEach(function (value) { optionField(value); });

            addOption.addEventListener('click', function () {
                if (optionsBox.children.length < 10) {
                    optionField('').querySelector('input').focus();
                }
            });

            optionsWrap.appendChild(optionsBox);
            optionsWrap.appendChild(addOption);
            body.appendChild(formRow(t('poll_options'), optionsWrap, t('poll_options_help')));

            function toggle(label, help) {
                var wrap = el('div', 'form-field mb-3');
                var input = el('input', '');
                var caption = el('label', '', label);

                input.type = 'checkbox';
                input.id = nextId('ws-poll-switch-');
                input.setAttribute('role', 'switch');
                caption.htmlFor = input.id;
                wrap.appendChild(el('div', 'switch')).appendChild(input);
                wrap.appendChild(caption);
                wrap.appendChild(el('div', 'form-text mt-0', help));
                body.appendChild(wrap);

                return input;
            }

            var multiple = toggle(t('poll_multiple_label'), t('poll_multiple_help'));
            var anonymous = toggle(t('poll_anonymous_label'), t('poll_anonymous_help'));

            if (poll) {
                multiple.checked = !!poll.multiple;
                anonymous.checked = !!poll.anonymous;

                // A secret vote must not turn public, nor a second answer be
                // taken away, once people have voted.
                if (poll.voters > 0) {
                    multiple.disabled = true;
                    anonymous.disabled = true;
                    body.appendChild(el('div', 'form-text mt-0 mb-3', t('poll_locked_voted')));
                }
            }

            var grid = el('div', 'row g-3 mt-1');
            var dateCell = el('div', 'col-7');
            var timeCell = el('div', 'col-5');
            var date = el('input', 'form-control form-control-sm');
            var time = el('input', 'form-control form-control-sm');

            date.type = 'date';
            date.id = nextId('ws-poll-date-');
            date.min = CFG.today;
            time.type = 'time';
            time.id = nextId('ws-poll-time-');
            time.value = '18:00';

            if (poll && poll.closes_date) {
                date.value = poll.closes_date;
                time.value = poll.closes_time || '18:00';
            }

            dateCell.appendChild(formRow(t('poll_closes_label'), date, t('poll_closes_help')));
            timeCell.appendChild(formRow(t('poll_closes_time'), time));
            grid.appendChild(dateCell);
            grid.appendChild(timeCell);
            body.appendChild(grid);

            var outcome = el('div', 'ws-poll-note');
            outcome.appendChild(icon('bi-patch-check', 'me-1'));
            outcome.appendChild(document.createTextNode(t('poll_result_help')));
            body.appendChild(outcome);

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'drawer');
            footer.appendChild(cancel);

            var create = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', poll ? t('save') : t('start_poll'), poll ? 'bi-check2' : 'bi-send');
            create.addEventListener('click', function () {
                var options = Array.prototype.map.call(optionsBox.querySelectorAll('input'), function (input) {
                    return input.value.trim();
                }).filter(Boolean);

                var settings = {
                    multiple: multiple.checked ? 1 : 0,
                    anonymous: anonymous.checked ? 1 : 0,
                    closes_date: date.value,
                    closes_time: time.value || '18:00'
                };

                create.disabled = true;

                var request;

                if (poll) {
                    var text = question.value;

                    questionLabels.slice().sort(function (a, b) { return b.label.length - a.label.length; }).forEach(function (item) {
                        if (text.indexOf(item.label) !== -1) {
                            text = text.split(item.label).join(item.token);
                        }
                    });

                    // Each option with the one it was, so votes stay with it.
                    settings.poll_id = poll.id;
                    settings.question = text;
                    settings.options = Array.prototype.map.call(optionsBox.children, function (line) {
                        return { id: parseInt(line.getAttribute('data-option-id') || '0', 10), label: line.querySelector('input').value.trim() };
                    }).filter(function (option) { return option.label !== ''; });

                    request = api('ws_poll_edit', settings);
                } else {
                    settings.channel_id = self.channel.id;
                    settings.question = question.value;
                    settings.options = options;

                    request = api('ws_poll_create', settings);
                }

                request.then(function (data) {
                    create.disabled = false;
                    hideOffcanvas(node);

                    if (poll && data.message) {
                        self.replaceMessage(data.message);
                    }

                    if (onCreated) {
                        onCreated();
                    }

                    self.sync(true);
                }).catch(function (error) {
                    create.disabled = false;
                    fail(error);
                });
            });
            footer.appendChild(create);

            showOffcanvas(node);

            if (!touchScreen()) {
                setTimeout(function () { ((question.value && !poll) ? optionsBox.querySelector('input') : question).focus(); }, 250);
            }
        },

        // ── Writing tables, checklists and emoji into the composer ──

        // A titled block pulled from elsewhere: the block, and where it came
        // from under it.
        insertPulled: function (data) {
            var from = '_' + String(data.from || '').replace(/_/g, ' ') + '_';

            if (this.rich) {
                this.rich.insertBlock(data.kind, data.markup);
                this.rich.insertText(from);
            } else if (this.insertBlock) {
                this.insertBlock(data.markup + '\n' + from, 0, 0);
            }

            if (this.autosize) {
                this.autosize();
            }
        },

        insertText: function (text) {
            if (this.rich) {
                this.rich.insertText(text);
                return;
            }

            var input = this.input;
            var start = input.selectionStart;
            var end = input.selectionEnd;

            input.value = input.value.slice(0, start) + text + input.value.slice(end);
            input.focus();
            input.setSelectionRange(start + text.length, start + text.length);
            this.autosize();
        },

        // A block of lines at the cursor, on lines of its own; the part
        // between from and to is selected so it can be typed over.
        insertBlock: function (text, from, to) {
            var input = this.input;
            var start = input.selectionStart;
            var end = input.selectionEnd;
            var before = input.value.slice(0, start);
            var after = input.value.slice(end);
            var lead = ((before === '') || /\n$/.test(before)) ? '' : '\n';
            var tail = ((after === '') || /^\n/.test(after)) ? '' : '\n';
            var base = before.length + lead.length;

            input.value = before + lead + text + tail + after;
            input.focus();
            input.setSelectionRange(base + from, base + to);
            this.autosize();
        },

        insertTable: function (rows, columns) {
            var self = this;

            // The rich box designs the table in its window, then keeps it
            // as a card.
            if (self.rich) {
                window.PGWsEditor.openTable({ rows: rows, columns: columns }, function (markup) {
                    if (markup !== '') {
                        self.rich.insertBlock('table', markup);
                    }
                }, { scope: function () { return self.rich.markup(); } });
                return;
            }

            var head = [];
            var line = [];
            var empty = [];

            for (var i = 0; i < columns; i++) {
                head.push(t('table_column', i + 1));
                line.push('---');
                empty.push(' ');
            }

            var text = '| ' + head.join(' | ') + ' |\n| ' + line.join(' | ') + ' |';

            for (var r = 0; r < rows; r++) {
                text += '\n| ' + empty.join(' | ') + ' |';
            }

            this.insertBlock(text, 2, 2 + head[0].length);
        },

        insertChecklist: function () {
            var self = this;

            if (self.rich) {
                window.PGWsEditor.openChecklist('', function (markup) {
                    if (markup !== '') {
                        self.rich.insertBlock('checklist', markup);
                    }
                });
                return;
            }

            this.insertBlock('- [ ] ', 6, 6);
        },

        // A calculation block: "Name = expression" lines, worked out by the
        // server. The rich box writes it in its window and keeps it as a card.
        insertCalc: function () {
            var self = this;

            if (self.rich) {
                window.PGWsEditor.openCalc('', function (markup) {
                    if (markup !== '') {
                        self.rich.insertBlock('code', markup);
                    }
                }, { scope: function () { return self.rich.markup(); } });
                return;
            }

            this.insertBlock('```hesap\n\n```', 9, 9);
        },

        // Shift+Enter in a checklist starts the next item; on an empty item
        // it ends the list.
        continueList: function () {
            var input = this.input;
            var at = input.selectionStart;

            if (at !== input.selectionEnd) {
                return false;
            }

            var lineStart = input.value.lastIndexOf('\n', at - 1) + 1;
            var match = /^(\s*[-*] \[[ xX]\] )(.*)$/.exec(input.value.slice(lineStart, at));

            if (!match) {
                return false;
            }

            if (match[2].trim() === '') {
                input.value = input.value.slice(0, lineStart) + input.value.slice(at);
                input.setSelectionRange(lineStart, lineStart);
            } else {
                var prefix = match[1].replace(/\[[xX]\]/, '[ ]');

                input.value = input.value.slice(0, at) + '\n' + prefix + input.value.slice(at);
                input.setSelectionRange(at + 1 + prefix.length, at + 1 + prefix.length);
            }

            this.autosize();

            return true;
        },

        tablePicker: function (x, y) {
            var self = this;
            var content = el('div', 'ws-table-pick');
            var caption = el('div', 'ws-table-pick-size', t('insert_table'));
            var grid = el('div', 'ws-table-pick-grid');
            var cells = [];

            function mark(rows, columns) {
                cells.forEach(function (cell) {
                    cell.classList.toggle('active', (cell.wsRows <= rows) && (cell.wsColumns <= columns));
                });

                caption.textContent = t('table_size', columns, rows);
            }

            for (var r = 1; r <= 6; r++) {
                for (var c = 1; c <= 6; c++) {
                    (function (rows, columns) {
                        var cell = el('button', 'ws-table-pick-cell');

                        cell.type = 'button';
                        cell.wsRows = rows;
                        cell.wsColumns = columns;
                        cell.setAttribute('aria-label', t('table_size', columns, rows));
                        cell.addEventListener('mouseenter', function () { mark(rows, columns); });
                        cell.addEventListener('focus', function () { mark(rows, columns); });
                        cell.addEventListener('click', function (event) {
                            event.stopPropagation();
                            closeCtx();
                            self.insertTable(rows, columns);
                        });
                        cells.push(cell);
                        grid.appendChild(cell);
                    })(r, c);
                }
            }

            content.appendChild(caption);
            content.appendChild(grid);
            floatBox(x, y, content, 'ws-table-picker', true);
        },

        replaceTaskCard: function (taskId, markup) {
            var self = this;

            Array.prototype.forEach.call(this.root.querySelectorAll('[data-ws-task-card="' + taskId + '"]'), function (card) {
                var holder = document.createElement('div');
                holder.innerHTML = markup;

                if (holder.firstElementChild) {
                    var fresh = holder.firstElementChild;
                    card.parentNode.replaceChild(fresh, card);
                    self.bindCard(fresh);
                }
            });
        },

        // A task card in the conversation gets the task's menu.
        bindCard: function (card) {
            var self = this;

            if (!card || !card.hasAttribute('data-ws-task-card')) {
                return;
            }

            onContext(card, function () {
                var title = card.querySelector('.ws-task-card-title');
                var number = card.querySelector('.ws-task-number');

                return taskMenu({
                    id: parseInt(card.getAttribute('data-ws-task-card'), 10),
                    number: number ? number.textContent : '',
                    title: title ? title.textContent : '',
                    status: card.getAttribute('data-ws-status') || '',
                    due_date: card.getAttribute('data-ws-due') || ''
                }, function () { self.sync(); });
            });
        },

        pinChannel: function (channel, pinned) {
            var self = this;

            api('ws_channel_pin', { channel_id: channel.id, pinned: pinned ? 1 : 0 }).then(function () {
                toast(pinned ? t('pinned_done') : t('unpinned_done'), 'success');
                self.reloadChannels(0);
            }).catch(fail);
        },

        // Channels one is in can be dragged into any order within their
        // section; the order is the person's own.
        makeSortable: function (link, box, channel) {
            var self = this;

            link.draggable = true;

            link.addEventListener('dragstart', function (event) {
                self.dragging = { id: channel.id, box: box, channel: channel };
                link.classList.add('ws-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'ws-channel:' + channel.id);
            });

            link.addEventListener('dragend', function () {
                link.classList.remove('ws-dragging');

                if (self.dragging) {
                    self.dragging = null;
                    self.saveOrder();
                }

                self.clearDropMarks();
            });

            link.addEventListener('dragover', function (event) {
                var drag = self.dragging;

                if (!drag || drag.box !== box || drag.id === channel.id) {
                    return;
                }

                event.preventDefault();

                var moving = box.querySelector('[data-ws-channel="' + drag.id + '"]');
                var rect = link.getBoundingClientRect();
                var after = (event.clientY - rect.top) > (rect.height / 2);

                if (moving) {
                    box.insertBefore(moving, after ? link.nextSibling : link);
                }
            });

            link.addEventListener('drop', function (event) {
                event.preventDefault();
            });
        },

        saveOrder: function () {
            var self = this;
            var ids = [];

            Array.prototype.forEach.call(self.side.querySelectorAll('.ws-sortable [data-ws-channel]'), function (node) {
                ids.push(parseInt(node.getAttribute('data-ws-channel'), 10));
            });

            // The list as it now stands, so the next draw does not jump back.
            var position = {};

            ids.forEach(function (id, index) { position[id] = index + 1; });
            BOOT.channels.forEach(function (item) {
                if (position[item.id]) {
                    item.sort = position[item.id];
                }
            });
            BOOT.channels.sort(function (a, b) {
                return ((b.pinned ? 1 : 0) - (a.pinned ? 1 : 0)) || ((a.sort || 9999) - (b.sort || 9999)) || a.name.localeCompare(b.name);
            });

            api('ws_channel_order', { ids: ids }).catch(fail);
        },

        // A channel in the sidebar: open it, how loud it is, leave it.
        channelActions: function (channel) {
            var self = this;
            var items = [{ header: '#' + channel.name }];
            var reload = function () { self.reloadChannels(self.channel ? self.channel.id : 0); };
            var mayManage = channel.owner || BOOT.me.rights.staff;

            items.push({ icon: 'bi-box-arrow-in-right', label: t('open_channel'), action: function () { self.open(channel.id, 0); } });

            // The channel's settings, from where it is listed.
            if (mayManage && !channel.archived) {
                items.push({ icon: 'bi-gear', label: t('channel_settings'), action: function () {
                    var shown = (self.channel && self.channel.id === channel.id) ? Promise.resolve({ channel: self.channel }) : api('ws_channel_get', { channel_id: channel.id });

                    shown.then(function (data) {
                        if (!data.channel.can_manage) {
                            toast(t('channel_manage_denied'), 'warning');
                            return;
                        }

                        channelForm(data.channel, {}, function (id) { self.reloadChannels(self.channel ? self.channel.id : id); });
                    }).catch(fail);
                } });
            }

            if (CFG.groups && CFG.groups.manage && !channel.archived) {
                items.push({ icon: 'bi-folder-symlink', label: t('grp_move_channel'), action: function () { self.pickGroupFor(channel); } });
            }

            items.push({ icon: channel.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle', label: channel.pinned ? t('unpin') : t('pin'), action: function () { self.pinChannel(channel, !channel.pinned); } });
            items.push({ icon: 'bi-window-stack', label: t('open_in_new_tab'), action: function () { window.open(CFG.urls.workspace + '?channel=' + channel.id, '_blank', 'noopener'); } });

            if (!channel.joined && channel.kind === 'public') {
                items.push({ icon: 'bi-person-plus', label: t('join'), action: function () {
                    api('ws_channel_join', { channel_id: channel.id }).then(reload).catch(fail);
                } });
            }

            if (channel.joined) {
                items.push('-');
                [['all', t('notify_all'), 'bi-bell'], ['mentions', t('notify_mentions'), 'bi-at'], ['none', t('notify_none'), 'bi-bell-slash']].forEach(function (option) {
                    items.push({ icon: option[2], label: option[1], checked: channel.notify === option[0], action: function () {
                        api('ws_channel_notify', { channel_id: channel.id, notify: option[0] }).then(reload).catch(fail);
                    } });
                });
            }

            items.push('-');
            items.push({ icon: 'bi-check2-square', label: t('new_task'), action: function () {
                taskDrawer.open(0, { channel_id: channel.id, department_id: channel.department_id || 0 }, function () { self.sync(); });
            } });
            items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () { copyText(absoluteUrl(CFG.urls.workspace + '?channel=' + channel.id)); } });

            if (mayManage || channel.joined) {
                items.push('-');
            }

            if (mayManage && !channel.archived) {
                items.push({ icon: 'bi-archive', label: t('archive'), action: function () {
                    api('ws_channel_archive', { channel_id: channel.id, archive: 1 }).then(reload).catch(fail);
                } });
            }

            if (channel.joined) {
                items.push({ icon: 'bi-box-arrow-right', label: t('leave_channel'), danger: true, action: function () {
                    ask(channel.kind === 'private' ? t('leave_private_confirm') : t('leave_confirm'), t('leave_channel'), true).then(function (yes) {
                        if (yes) {
                            api('ws_channel_leave', { channel_id: channel.id }).then(function () {
                                if (self.channel && self.channel.id === channel.id) {
                                    self.channel = null;
                                    self.drawEmptyCenter();
                                }

                                self.reloadChannels(0);
                            }).catch(fail);
                        }
                    });
                } });
            }

            return items;
        },

        loadOlder: function (trigger) {
            var self = this;
            var first = self.messages.length ? self.messages[0].id : 0;

            api('ws_messages_before', { channel_id: self.channel.id, before_id: first, era_id: self.era ? self.era.id : 0 }).then(function (data) {
                var height = self.pane.scrollHeight;

                self.messages = data.messages.concat(self.messages);
                self.hasMore = data.has_more;
                self.drawMessages();
                self.pane.scrollTop = self.pane.scrollHeight - height;
            }).catch(fail);

            trigger.disabled = true;
        },

        scrollToEnd: function () {
            if (this.pane) {
                this.pane.scrollTop = this.pane.scrollHeight;
            }
        },

        nearEnd: function () {
            return !this.pane || ((this.pane.scrollHeight - this.pane.scrollTop - this.pane.clientHeight) < 120);
        },

        // Scrolled up from the newest line: an arrow back down, and beside
        // it how many lines came in meanwhile.
        jumpButton: function () {
            var self = this;
            var jump = el('button', 'ws-jump-end');
            jump.type = 'button';
            jump.hidden = true;
            jump.title = t('jump_to_end');
            jump.setAttribute('aria-label', t('jump_to_end'));
            jump.appendChild(icon('bi-arrow-down'));
            self.jumpCount = el('span', 'ws-jump-count');
            self.jumpCount.hidden = true;
            jump.appendChild(self.jumpCount);
            jump.addEventListener('click', function () {
                self.unseenBelow = 0;
                self.scrollToEnd();
                self.atEnd = true;
                self.drawJump();
            });
            self.jump = jump;

            return jump;
        },

        drawJump: function () {
            if (!this.jump) {
                return;
            }

            if (this.atEnd) {
                this.unseenBelow = 0;
            }

            this.jump.hidden = !!this.atEnd;
            this.jumpCount.hidden = !this.unseenBelow;
            this.jumpCount.textContent = this.unseenBelow > 99 ? '99+' : String(this.unseenBelow || '');
            this.jump.classList.toggle('ws-jump-news', this.unseenBelow > 0);
            this.jump.title = this.unseenBelow ? t('jump_new_count', this.unseenBelow) : t('jump_to_end');
        },

        // ── Composer ──

        composer: function () {
            var self = this;
            var wrap = el('div', 'ws-composer-wrap');

            // The box this one replaces writes its waiting draft first.
            self.flushDraft();
            self.composerWrap = wrap;
            self.meter = null;

            if (!self.channel.can_post) {
                wrap.appendChild(el('div', 'small fg-secondary text-center py-3', self.era ? t('era_readonly') : (self.channel.audit ? t('audit_readonly') : (self.channel.archived ? t('archived_readonly') : t('cannot_post')))));
                return wrap;
            }

            // The writer's own messages waiting to be posted here.
            self.scheduledBar = el('div');
            wrap.appendChild(self.scheduledBar);
            self.drawScheduledBar();

            self.previewBox = el('div');
            wrap.appendChild(self.previewBox);

            self.replyBar = el('div');
            wrap.appendChild(self.replyBar);

            // While a command is written: its usage, the argument expected
            // now highlighted (drawCommandStrip()).
            self.commandStrip = el('div');
            wrap.appendChild(self.commandStrip);

            var box = el('div', 'ws-composer');

            // The keys the box leaves to the conversation: the picker's,
            // Tab in a command's arguments, Escape, and Arrow Up in an empty
            // box to change one's last message. true when used.
            function keys(event) {
                if (self.picker && self.pickerKey(event)) {
                    return true;
                }

                if ((event.key === 'Tab') && !event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey && self.commandTab()) {
                    event.preventDefault();
                    return true;
                }

                if (event.key === 'Escape') {
                    self.cancelCompose();
                    return true;
                }

                if ((event.key === 'ArrowUp') && self.inputEmpty()) {
                    var mine = self.messages.filter(function (message) { return message.can_edit; });

                    if (mine.length) {
                        event.preventDefault();
                        self.editMessage(mine[mine.length - 1]);
                        return true;
                    }
                }

                return false;
            }

            // Formatting, tags as chips and tables, lists and code as cards
            // (assets/js/workspace_editor.js); a plain box without it.
            self.rich = window.PGWsEditor ? window.PGWsEditor.create({
                placeholder: t('composer_placeholder', self.channel.name),
                onKeydown: keys,
                onEnter: function () { self.send(); }
            }) : null;

            var input = self.rich ? self.rich.node : el('textarea');

            if (!self.rich) {
                input.rows = 1;
                input.placeholder = t('composer_placeholder', self.channel.name);
                input.setAttribute('aria-label', input.placeholder);
            }

            self.input = input;
            box.appendChild(input);

            // The row under the box, in order: the inserts, then the box's own
            // marks and steps, then Send (or the microphone in its place).
            var parts = [];

            // A file from this device, or one of the file manager's.
            var attach = button('btn btn-sm btn-ghost', '', 'bi-paperclip', t('attach'));
            attach.addEventListener('click', function (event) {
                var box = attach.getBoundingClientRect();

                if (!CFG.library) {
                    self.attach();
                    return;
                }

                event.stopPropagation();
                ctxMenu(box.left, box.top - 6, [
                    { icon: 'bi-laptop', label: t('lib_from_device'), action: function () { self.attach(); } },
                    { icon: 'bi-folder2-open', label: t('lib_from_manager'), action: function () { self.attachFromLibrary(); } }
                ], true);
            });
            parts.push(attach);

            var mention = button('btn btn-sm btn-ghost', '', 'bi-at', t('mention'));
            mention.addEventListener('click', function () { self.insertTrigger('@'); });
            parts.push(mention);

            var record = button('btn btn-sm btn-ghost', '', 'bi-hash', t('tag_record'));
            record.addEventListener('click', function () { self.insertTrigger('#'); });
            parts.push(record);

            var emoji = button('btn btn-sm btn-ghost', '', 'bi-emoji-smile', t('insert_emoji'));
            emoji.addEventListener('click', function (event) {
                var box = emoji.getBoundingClientRect();

                event.stopPropagation();
                emojiPicker(box.left, box.top - 6, function (picked) { self.insertText(picked); }, true);
            });
            parts.push(emoji);
            // ":" and two letters suggest emoji in this box too.
            self.colonEmoji = true;

            // A table, a calculation, a checklist or a poll: the things that are
            // more than a line.
            var insert = button('btn btn-sm btn-ghost', '', 'bi-plus-circle', t('insert'));
            insert.addEventListener('click', function (event) {
                var box = insert.getBoundingClientRect();

                event.stopPropagation();
                ctxMenu(box.left, box.top - 6, [
                    { icon: 'bi-table', label: t('insert_table'), action: function () { self.tablePicker(box.left, box.top - 6); } },
                    { icon: 'bi-calculator', label: t('insert_calc'), action: function () { self.insertCalc(); } },
                    { icon: 'bi-ui-checks', label: t('insert_checklist'), action: function () { self.insertChecklist(); } },
                    self.rich ? { icon: 'bi-code-slash', label: t('insert_code'), action: function () { self.insertCode(); } } : null,
                    BOOT.interact ? { icon: 'bi-bar-chart-line', label: t('start_poll'), action: function () { self.pollForm({}); } } : null,
                    (CFG.approvals && window.PGWsApprovals) ? { icon: 'bi-patch-question', label: t('apv_new'), action: function () {
                        window.PGWsApprovals.form(self, featureHelpers());
                    } } : null,
                    (CFG.status && window.PGWsStatus && !self.isThread) ? { icon: 'bi-activity', label: t('st_new'), action: function () {
                        window.PGWsStatus.form(self, featureHelpers());
                    } } : null,
                    (CFG.status && window.PGWsStatus && !self.isThread) ? { icon: 'bi-person-raised-hand', label: t('ci_new'), action: function () {
                        window.PGWsStatus.checkinForm(self, featureHelpers());
                    } } : null,
                    CFG.blocks ? { icon: 'bi-box-arrow-in-down', label: t('pull_title'), action: function () {
                        blockPuller(function (data) { self.insertPulled(data); });
                    } } : null,
                    (CFG.scheduled && !self.isThread) ? { icon: 'bi-alarm', label: t('sa_new'), action: function () {
                        scheduledForm({ channel_id: self.channel.id }, null, function () { self.sync(true); });
                    } } : null
                ], true);
            });
            parts.push(insert);

            var command = button('btn btn-sm btn-ghost', '', 'bi-slash-square', t('commands'));
            command.addEventListener('click', function () {
                // Empty: a / to start a command. Only a / (or its list is
                // open): the second press takes it back.
                if (self.inputEmpty() || (self.picker && self.pickerMode === 'command') || (self.inputText().trim() === '/')) {
                    self.insertTrigger('/');
                } else {
                    self.showHelp();
                }
            });
            parts.push(command);

            // Bold, italic, strikethrough, lists, undo and redo of the box
            // itself (PGWsEditor.toolbar()); the stylesheet leaves them out
            // where the row has no room for them, the bar over a selection
            // still formats there.
            parts.push(['bold', 'italic', 'strike'], ['bullets', 'numbers', 'quote'], ['undo', 'redo']);

            self.aiRewrite = self.rich ? self.aiRewriteButton(wrap, box) : null;

            if (self.aiRewrite) {
                parts.push(self.aiRewrite);
            }

            parts.push(el('span', 'ws-grow ws-composer-hint d-none lg:d-inline', t('composer_hint')));

            // The count near the message limit (drawMeter()), empty while
            // the text is short; the hidden region beside it speaks only when
            // the count appears or crosses the limit.
            self.meter = el('span', 'ws-composer-meter');
            self.meterLive = el('span', 'visually-hidden');
            self.meterLive.setAttribute('aria-live', 'polite');
            self.meterState = 'none';
            parts.push(self.meter, self.meterLive);

            var send = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('send'), 'bi-send');
            send.addEventListener('click', function () { self.send(); });
            self.sendButton = send;

            // Beside it, the times the message can wait for instead
            // (scheduled_messages.php).
            if (CFG.scheduled_messages && !self.era) {
                var sendGroup = el('div', 'btn-group ws-send-group');
                var later = button('btn btn-sm btn-solid theme-primary rounded-end-pill ws-send-later', '', 'bi-clock', t('sm_send_later'));

                send.classList.remove('rounded-pill');
                send.classList.add('rounded-start-pill');
                later.addEventListener('click', function (event) {
                    var place = later.getBoundingClientRect();
                    var items = [{ header: t('sm_schedule') }];

                    event.stopPropagation();

                    if (self.inputEmpty() || self.editing) {
                        self.input.focus();
                        return;
                    }

                    smPresets().forEach(function (preset) {
                        items.push({ icon: preset.icon, label: preset.label, action: function () { self.scheduleSend(preset.when); } });
                    });

                    items.push('-');
                    items.push({ icon: 'bi-calendar-event', label: t('sm_custom'), action: function () {
                        smAskWhen('', '').then(function (when) {
                            if (when) {
                                self.scheduleSend(when);
                            }
                        });
                    } });

                    ctxMenu(Math.max(8, place.right - 240), place.top - 6, items, true);
                });

                sendGroup.appendChild(send);
                sendGroup.appendChild(later);
                parts.push(sendGroup);
            } else {
                parts.push(send);
            }

            // A voice message (assets/js/workspace_voice.js): the microphone
            // stands where Send (with its second half) is while the box is
            // empty; voiceSwap() trades them.
            self.sendNode = sendGroup || send;
            self.voiceMic = null;
            self.voiceBox = null;

            if (window.PGWsVoice && window.PGWsVoice.supported() && !self.era) {
                var mic = button('btn btn-sm btn-solid theme-primary rounded-pill px-5 ws-voice-mic', '', 'bi-mic', t('voice_record'));

                mic.addEventListener('click', function () { self.voiceRecord(); });
                parts.push(mic);
                self.voiceMic = mic;
                self.voiceBox = box;
            }

            var tools = null;

            if (self.rich) {
                tools = window.PGWsEditor.toolbar(self.rich, parts, {
                    className: 'ws-composer-tools',
                    groupClass: 'ws-composer-format',
                    label: t('tool_bar_message')
                });
            } else {
                tools = el('div', 'ws-composer-tools');
                parts.forEach(function (item) {
                    if (item && item.nodeType) {
                        tools.appendChild(item);
                    }
                });
            }

            box.appendChild(tools);
            wrap.appendChild(box);
            self.voiceSwap();

            input.addEventListener('input', function () {
                self.autosize();
                self.pickerCheck();
                self.keepDraft();
            });
            input.addEventListener('click', function () { self.pickerCheck(); });
            input.addEventListener('blur', function () { self.flushDraft(); });
            self.restoreDraft();

            // The plain box handles its own keys; the rich one calls keys()
            // and sends on Enter itself.
            if (!self.rich) {
                input.addEventListener('keydown', function (event) {
                    if (keys(event)) {
                        return;
                    }

                    if ((event.key === 'Enter') && (event.shiftKey || touchScreen()) && !event.isComposing && self.continueList()) {
                        event.preventDefault();
                        return;
                    }

                    if ((event.key === 'Enter') && !event.shiftKey && !event.isComposing && !touchScreen()) {
                        event.preventDefault();
                        self.send();
                    }
                });
            }

            if (!touchScreen()) {
                setTimeout(function () { input.focus(); }, 50);
            }

            return wrap;
        },

        // What is in the box, posted at a time rather than now.
        scheduleSend: function (when) {
            var self = this;
            var raw = self.inputText();

            if (self.busy || (raw.trim() === '') || self.meterRefuses()) {
                return;
            }

            // A scheduled message being changed takes the new time with its text.
            var changing = self.editingScheduled;
            var data = changing
                ? { id: changing.id, body: self.tokens(raw) }
                : { channel_id: self.channel.id, body: self.tokens(raw), parent_id: self.reply ? self.reply.id : 0 };

            Object.keys(when || {}).forEach(function (key) { data[key] = when[key]; });
            self.busy = true;

            api('ws_scheduled_message_save', data).then(function (result) {
                self.busy = false;
                self.clearInput();
                self.reply = null;
                self.replyMore = null;
                self.editingScheduled = null;
                self.drawReplyBar();

                if (!changing) {
                    self.channel.scheduled_mine = (self.channel.scheduled_mine || 0) + 1;
                }

                self.drawScheduledBar();
                toast(t('sm_saved', result.item ? result.item.when : ''), 'success');
            }).catch(function (error) {
                self.busy = false;
                fail(error);
            });
        },

        // Above the box: that the writer has messages waiting here, and the
        // way to see them.
        drawScheduledBar: function () {
            var self = this;
            var bar = self.scheduledBar ? clear(self.scheduledBar) : null;
            var count = (self.channel && self.channel.scheduled_mine) || 0;

            if (!bar || !count) {
                return;
            }

            var row = el('button', 'ws-sm-bar');
            row.type = 'button';
            row.appendChild(icon('bi-clock'));
            row.appendChild(el('span', 'flex-grow-1 text-truncate', (count === 1) ? t('sm_bar_one', '…') : t('sm_bar', count)));
            row.appendChild(el('span', 'ws-sm-bar-link', t('sm_show')));
            row.addEventListener('click', function () { self.showScheduledMessages(); });
            bar.appendChild(row);

            // One waiting: when, in the line itself.
            if (count === 1) {
                api('ws_scheduled_messages', { channel_id: self.channel.id }).then(function (data) {
                    var text = row.querySelector('.text-truncate');

                    if (text && data.items.length) {
                        text.textContent = t('sm_bar_one', data.items[0].when);
                    }
                }).catch(function () {});
            }
        },

        showScheduledMessages: function () {
            var self = this;
            var channel = self.channel;
            var node = offcanvas('ws-sm-list', t('sm_title'));
            var body = clear(node.querySelector('.drawer-body'));
            var list = el('div', 'ws-sm-list');

            node.querySelector('.drawer-footer').classList.add('d-none');
            body.appendChild(el('p', 'small fg-secondary', t('sm_mine_help')));
            body.appendChild(list);

            scheduledMessagesList(list, channel.id, function () {
                self.sync();
            }, function (item) {
                hideOffcanvas(node);
                self.editScheduled(item);
            });

            showOffcanvas(node);
        },

        // A scheduled message's text, back in the box to be changed; its time
        // stays.
        editScheduled: function (item) {
            var self = this;

            if (!self.input) {
                return;
            }

            self.editing = null;
            self.reply = null;
            self.editingScheduled = item;

            if (self.rich) {
                self.rich.focus();
                self.rich.setMarkup(item.raw || '', item.labels || {});
            } else {
                self.labels = [];
                Object.keys(item.labels || {}).forEach(function (token) {
                    var shown = shownLabel(token.charAt(1), item.labels[token]);

                    self.labels.push({ label: shown, token: token });
                });
                self.input.value = (item.raw || '').replace(/<[@#][a-z_]+:\d+>/g, function (token) {
                    return item.labels[token] ? shownLabel(token.charAt(1), item.labels[token]) : token;
                });
                self.input.focus();
            }

            self.drawReplyBar();
            self.autosize();
        },

        autosize: function () {
            var input = this.input;

            // The rich box grows by itself, up to its CSS height.
            if (!this.rich) {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 192) + 'px';
            }

            this.voiceSwap();
            this.aiRewriteSwap();
            this.drawMeter();
            this.fit();
        },

        // "Rewrite with AI" (assets/js/pg_ai_write.js, the ai_write panel
        // action, target ws_message): the draft panel opens over the box with
        // the tone, "Try again" and "Use this text". The person's text and
        // the channel's name are all the model reads. null where the screen
        // offers no model (CFG.ai_rewrite) or the box is the plain one.
        aiRewriteButton: function (wrap, box) {
            var self = this;

            if (!CFG.ai_rewrite || !window.PinegrapAiWrite) {
                return null;
            }

            var id = 'ws_ai_rewrite_' + (++aiRewriteSeq);
            var node = button('btn btn-sm btn-ghost pg-ai-write-btn ws-ai-rewrite d-none', '', 'bi-stars', t('tool_ai_rewrite'));

            node.setAttribute('data-pg-ai-write', JSON.stringify({ target: 'ws_message', record: 'message', record_id: 0, field: id, format: 'message' }));
            node.setAttribute('aria-expanded', 'false');
            node.setAttribute('aria-controls', 'pg_ai_draft_' + id);
            node.pgAiWrite = {
                context: function () {
                    return { text: self.inputText(), channel: self.channel ? String(self.channel.name || '') : '' };
                },
                place: function (panel) {
                    wrap.insertBefore(panel, box);
                },
                use: function (text) {
                    self.aiRewriteUse(text);
                }
            };

            // The browser's model is found after the box is drawn.
            document.addEventListener('pg:ai-write-ready', function () { self.aiRewriteSwap(); }, { once: true });

            return node;
        },

        // Shown while the box holds text and a model writes in this browser.
        aiRewriteSwap: function () {
            if (this.aiRewrite) {
                this.aiRewrite.classList.toggle('d-none', !window.PinegrapAiWrite.ready || this.inputEmpty());
            }
        },

        // The draft in place of the text, as one step Ctrl+Z takes back; the
        // tags keep the names they were picked with. The meter, the draft
        // and the size follow from the box's input event.
        aiRewriteUse: function (text) {
            var labels = {};

            Array.prototype.forEach.call(this.rich.node.querySelectorAll('[data-token]'), function (chip) {
                labels[chip.getAttribute('data-token')] = String(chip.textContent || '').trim();
            });

            this.rich.focus();
            this.rich.setMarkup(aiRewriteTokens(text, Object.keys(labels)), labels, true);
        },

        // The text as it is posted against the server's limit
        // (messageMeter()): "n / max" from 90 %, the warning colour past it.
        drawMeter: function () {
            var meter = messageMeter(this.tokens(this.inputText()), CFG.message_max, !this.editing && !this.editingScheduled);

            if (!this.meter) {
                return meter;
            }

            this.meter.textContent = (meter.state === 'none') ? '' : (meter.count + ' / ' + meter.max);
            this.meter.classList.toggle('is-over', meter.state === 'over');

            if (meter.state !== this.meterState) {
                this.meterState = meter.state;
                this.meterLive.textContent = (meter.state === 'over') ? t('meter_over', meter.count - meter.max, meter.max)
                    : ((meter.state === 'near') ? t('meter_near', meter.max - meter.count) : '');
            }

            return meter;
        },

        // Past the limit the server would refuse the text: its own words,
        // without the round trip. true when refused.
        meterRefuses: function () {
            if (!this.drawMeter().block) {
                return false;
            }

            toast(t('msg_too_long', CFG.message_max), 'danger');

            return true;
        },

        // What is in the box, as the markup a message is stored in.
        inputText: function () {
            return this.rich ? this.rich.markup() : this.input.value;
        },

        inputEmpty: function () {
            return this.rich ? this.rich.isEmpty() : (this.input.value === '');
        },

        // The microphone in place of Send while the box is empty. Not while a
        // message is changed; beside a reply bar it answers that message
        // (voiceSend()), but not several at once, nor an assistant the reply
        // would ask (voiceAnswers()).
        voiceSwap: function () {
            var mic = this.voiceMic;

            if (!mic || !this.sendNode || !this.input) {
                return;
            }

            var show = this.inputEmpty() && !this.editing && !this.editingScheduled && !this.era
                && !!this.channel && !!this.channel.can_post
                && (!this.reply || (!(this.replyMore && this.replyMore.length) && this.voiceAnswers(this.reply)));

            mic.classList.toggle('d-none', !show);
            this.sendNode.classList.toggle('d-none', show);
        },

        // Whether a recording may answer this message. An answer to Claude,
        // Pinegrap AI or an MCP agent asks it, as the reply bar says; a
        // recording asks nobody, so it does not stand in for one.
        voiceAnswers: function (message) {
            var sender = (message && message.sender) || {};
            var channel = this.channel || {};

            return !((sender.claude && channel.claude && channel.claude.available)
                || (sender.ai && channel.ai && channel.ai.available) || sender.mcp);
        },

        // Whether a sound in the conversation gets the microphone that
        // answers it: where the writing box records, in the conversation
        // itself (not the Decisions or Files list).
        voiceReplyOffered: function (message) {
            return !!(window.PGWsVoice && window.PGWsVoice.supported()) && (this.tab === 'messages') && !this.era
                && !!this.channel && !!this.channel.can_post && !message.deleted && this.voiceAnswers(message);
        },

        // Records into the writing box (the microphone in place of Send, or
        // a sound's own); Stop sends it with voiceSend().
        voiceRecord: function () {
            var self = this;
            var box = self.voiceBox;

            if (!window.PGWsVoice || !box || !box.isConnected) {
                return;
            }

            window.PGWsVoice.start({
                box: box,
                send: function (file) { self.voiceSend(file); },
                done: function () {
                    self.voiceSwap();

                    if (!touchScreen() && self.input && self.input.isConnected) {
                        self.input.focus();
                    }
                }
            }, featureHelpers());
        },

        // The microphone on a sound: the sound becomes the message the box
        // answers, as Reply makes it, and the recording starts at once. A
        // tap on it while messages are being chosen chooses the message.
        voiceReply: function (message) {
            var self = this;

            if (self.selecting || !window.PGWsVoice || window.PGWsVoice.busy() || !self.voiceBox || !self.voiceBox.isConnected) {
                return;
            }

            if (self.editing || self.editingScheduled) {
                toast(t('voice_busy'), 'warning');
                return;
            }

            self.reply = self.findMessage(message.id) || message;
            self.replyMore = null;
            self.drawReplyBar();
            self.voiceRecord();
        },

        // A recording leaves as a file of its own: the words in the box stay
        // there, and it answers the message the reply bar shows when it is
        // sent. The bar closes once it is posted.
        voiceSend: function (file) {
            var self = this;
            var parent = (self.reply && self.voiceAnswers(self.reply)) ? self.reply : null;

            self.uploadFiles([file], {
                body: '',
                parent_id: parent ? parent.id : 0,
                sent: function () {
                    if (parent && (self.reply === parent)) {
                        self.reply = null;
                        self.replyMore = null;
                        self.drawReplyBar();
                    }
                }
            });
        },

        clearInput: function () {
            if (this.rich) {
                this.rich.clear();
            } else if (this.input) {
                this.input.value = '';
            }

            this.labels = [];
            this.argDismissed = null;
            this.dropDraft();

            if (this.commandStrip) {
                clear(this.commandStrip);
            }

            this.autosize();
        },

        // Empties the box only while it still holds the text that was sent.
        // A card's button sends the text the card was drawn from, so a draft
        // typed into the box after the card appeared was never sent and stays.
        clearSent: function (sent) {
            if (this.tokens(this.inputText()) === sent) {
                this.clearInput();
            }
        },

        // ── The unsent text of this box (draftKey(), draftWrite()) ──
        // A channel's slot, or a discussion's beside its channel; none where
        // nothing can be posted.
        draftSlot: function () {
            var channel = this.channel;

            if (!channel || !channel.can_post) {
                return '';
            }

            if (this.isThread) {
                return draftKey(CFG.api_url, CFG.me, channel.thread ? channel.thread.channel_id : 0, channel.id);
            }

            return draftKey(CFG.api_url, CFG.me, channel.id, 0);
        },

        // The tags' labels of the box, so the chips come back with their names.
        draftLabels: function () {
            var labels = {};

            if (this.rich) {
                Array.prototype.forEach.call(this.rich.node.querySelectorAll('[data-token]'), function (chip) {
                    labels[chip.getAttribute('data-token')] = chip.textContent;
                });
            } else {
                (this.labels || []).forEach(function (item) { labels[item.token] = item.label; });
            }

            return labels;
        },

        // Saves the box once typing pauses, or at once from flushDraft().
        // The slot is taken now, so a save after another channel opened
        // still writes this one's. A box changing a sent or scheduled
        // message keeps no draft.
        keepDraft: function () {
            var self = this;
            var key = self.draftSlot();

            if (!key || self.editing || self.editingScheduled) {
                return;
            }

            clearTimeout(self.draftTimer);
            self.draftPending = function () {
                self.draftPending = null;

                if (!self.input || self.editing || self.editingScheduled) {
                    return;
                }

                draftWrite(key, self.inputEmpty() ? '' : self.tokens(self.inputText()), self.draftLabels());
            };
            self.draftTimer = setTimeout(function () {
                if (self.draftPending) {
                    self.draftPending();
                }
            }, 400);
        },

        flushDraft: function () {
            clearTimeout(this.draftTimer);

            if (this.draftPending) {
                this.draftPending();
            }
        },

        // The box was sent or emptied: its slot goes. Not while it changes a
        // sent or scheduled message, which never held the draft.
        dropDraft: function () {
            if (this.editing || this.editingScheduled) {
                return;
            }

            clearTimeout(this.draftTimer);
            this.draftPending = null;

            var key = this.draftSlot();

            if (key) {
                draftWrite(key, '', null);
            }
        },

        // The kept text back into a box opened empty.
        restoreDraft: function () {
            var self = this;
            var key = self.draftSlot();
            var kept = (key && self.input && self.inputEmpty()) ? draftRead(key) : null;

            if (!kept) {
                return;
            }

            if (self.rich) {
                self.rich.setMarkup(kept.raw, kept.labels);
            } else {
                self.labels = [];
                Object.keys(kept.labels).forEach(function (token) {
                    self.labels.push({ label: shownLabel(token.charAt(1), kept.labels[token]), token: token });
                });
                self.input.value = kept.raw.replace(/<[@#][a-z_]+:\d+>/g, function (token) {
                    return kept.labels[token] ? shownLabel(token.charAt(1), kept.labels[token]) : token;
                });
            }

            self.autosize();
        },

        // What was typed before the caret: the pickers read it. Chips and
        // cards of the rich box count as U+FFFC.
        beforeCaret: function () {
            if (this.rich) {
                return this.rich.beforeCaret();
            }

            return this.input.value.slice(0, this.input.selectionStart);
        },

        insertCode: function () {
            var self = this;

            window.PGWsEditor.openCode('', function (markup) {
                if (markup !== '') {
                    self.rich.insertBlock('code', markup);
                }
            });
        },

        // On a phone the channel screen is the part of the window that can
        // be seen: under the panel's top bar and above the on-screen keyboard.
        // The keyboard shrinks the visual viewport only, not 100vh, so the
        // size is taken from there; without it the browser scrolls the page
        // to reach the composer and the header and tabs leave the screen.
        bindFit: function () {
            var self = this;
            var query = window.matchMedia ? window.matchMedia('(max-width: 767.98px)') : null;
            var bar = document.querySelector('.software-navbar');
            var frame = 0;
            var tallest = 0;
            var lastWidth = 0;

            function apply() {
                var root = self.root;
                var body = document.body;

                frame = 0;

                if (!query || !query.matches) {
                    root.style.removeProperty('--ws-fit-top');
                    root.style.removeProperty('--ws-fit-h');
                    body.classList.remove('ws-keyboard');
                    return;
                }

                var view = window.visualViewport;
                var offset = view ? view.offsetTop : 0;
                var height = view ? view.height : window.innerHeight;
                var top = Math.max(offset, bar ? bar.getBoundingClientRect().height : 0);
                var focused = document.activeElement;
                var typing = !!focused && root.contains(focused) && (/^(TEXTAREA|INPUT)$/.test(focused.tagName) || focused.isContentEditable);

                // The keyboard is up when a field in here has the focus and
                // the window is well short of its height at this width.
                if (window.innerWidth !== lastWidth) {
                    lastWidth = window.innerWidth;
                    tallest = 0;
                }

                tallest = Math.max(tallest, height);

                // A conversation read to its end stays at its end when the
                // keyboard takes half of the screen.
                var pane = (self.tab === 'messages') ? self.pane : null;
                var atEnd = !!pane && ((pane.scrollHeight - pane.scrollTop - pane.clientHeight) < 48);

                root.style.setProperty('--ws-fit-top', Math.round(top) + 'px');
                root.style.setProperty('--ws-fit-h', Math.max(220, Math.round(offset + height - top)) + 'px');
                body.classList.toggle('ws-keyboard', typing && ((tallest - height) > 120));

                if (atEnd) {
                    pane.scrollTop = pane.scrollHeight;
                }

                if (self.composerWrap && self.composerWrap.isConnected) {
                    body.style.setProperty('--ws-composer-h', Math.round(self.composerWrap.getBoundingClientRect().height) + 'px');
                }
            }

            self.fit = function () {
                if (!frame) {
                    frame = window.setTimeout(apply, 16);
                }
            };

            window.addEventListener('resize', self.fit);
            window.addEventListener('orientationchange', self.fit);
            self.root.addEventListener('focusin', self.fit);
            self.root.addEventListener('focusout', self.fit);

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', self.fit);
                window.visualViewport.addEventListener('scroll', self.fit);
            }

            apply();
        },

        drawReplyBar: function () {
            var self = this;
            var bar = clear(self.replyBar);

            self.voiceSwap();

            if (!self.reply && !self.editing && !self.editingScheduled) {
                return;
            }

            if (self.editingScheduled) {
                var waiting = el('div', 'ws-reply-bar');
                waiting.appendChild(icon('bi-clock'));
                waiting.appendChild(el('span', '', t('sm_editing') + ' · ' + t('sm_at', self.editingScheduled.when)));

                var stop = button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', t('cancel'));
                stop.addEventListener('click', function () { self.cancelCompose(); });
                waiting.appendChild(stop);
                bar.appendChild(waiting);
                return;
            }

            var row = el('div', 'ws-reply-bar');
            row.appendChild(icon(self.editing ? 'bi-pencil' : 'bi-reply'));
            row.appendChild(el('span', '', self.editing ? t('editing_message')
                : ((self.replyMore && self.replyMore.length) ? t('reply_many', self.replyMore.length + 1) : t('replying_to', self.reply.sender ? self.reply.sender.name : '', self.quoteText(self.reply)))));

            // An answer to Claude goes to Claude.
            if (!self.editing && self.reply.sender && self.reply.sender.claude && self.channel.claude && self.channel.claude.available) {
                row.appendChild(el('span', 'fg-secondary ms-1', t('claude_reply_hint')));
            } else if (!self.editing && self.reply.sender && self.reply.sender.ai && self.channel.ai && self.channel.ai.available) {
                row.appendChild(el('span', 'fg-secondary ms-1', t('ai_reply_hint')));
            } else if (!self.editing && self.reply.sender && self.reply.sender.mcp) {
                row.appendChild(el('span', 'fg-secondary ms-1', t('mcp_reply_hint', self.reply.sender.mcp)));
            }
            var cancel = button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', t('cancel'));
            cancel.addEventListener('click', function () { self.cancelCompose(); });
            row.appendChild(cancel);
            bar.appendChild(row);
        },

        cancelCompose: function () {
            if (this.picker) {
                this.closePicker();
                return;
            }

            if (this.editing || this.editingScheduled) {
                this.clearInput();
            }

            this.reply = null;
            this.replyMore = null;
            this.editing = null;
            this.editingScheduled = null;
            this.pending = null;

            if (this.previewBox) {
                clear(this.previewBox);
            }

            this.drawReplyBar();
            this.autosize();
        },

        // An open poll is changed in its own form, question and options
        // together; every other message in the writing box.
        editMessage: function (message) {
            if (message.poll && message.poll.can_edit) {
                this.pollForm(null, null, message);
                return;
            }

            this.startEdit(message);
        },

        startEdit: function (message) {
            var self = this;
            var text = message.raw || '';

            self.labels = [];

            Object.keys(message.labels || {}).forEach(function (token) {
                var shown = shownLabel(token.charAt(1), message.labels[token]);

                text = text.split(token).join(shown);
                self.labels.push({ label: shown, token: token });
            });

            self.editing = message;
            self.reply = null;

            // The rich box reads the stored text itself: tags back into chips,
            // tables and lists back into cards.
            if (self.rich) {
                self.labels = [];
                self.rich.focus();
                self.rich.setMarkup(message.raw || '', message.labels || {});
            } else {
                self.input.value = text;
                self.input.focus();
            }

            self.drawReplyBar();
            self.autosize();
        },

        // What was typed, with every picked tag turned back into its token.
        tokens: function (text) {
            var labels = this.labels.slice().sort(function (a, b) { return b.label.length - a.label.length; });

            labels.forEach(function (item) {
                if (text.indexOf(item.label) !== -1) {
                    text = text.split(item.label).join(item.token);
                }
            });

            return text;
        },

        send: function (options) {
            var self = this;
            var raw = self.inputText();

            if (self.busy) {
                return;
            }

            if (raw.trim() === '' && !options) {
                return;
            }

            // A card's button sends its own text, which the box does not hold.
            if (!options && self.meterRefuses()) {
                return;
            }

            // The argument popup does not outlive the line it was for: it
            // stays away until the next argument, or until Tab asks for it.
            if (self.picker && (self.pickerMode === 'arg')) {
                self.argDismissed = self.argument ? self.argumentKey(self.argument) : null;
                self.closePicker();
            }

            var body = self.tokens(raw);
            self.busy = true;

            // A scheduled message's new text; its time stays.
            if (self.editingScheduled) {
                var waiting = self.editingScheduled;

                api('ws_scheduled_message_save', { id: waiting.id, body: body, date: waiting.date, time: waiting.time }).then(function (data) {
                    self.busy = false;
                    self.editingScheduled = null;
                    self.clearInput();
                    self.drawReplyBar();
                    toast(t('sm_saved', data.item ? data.item.when : waiting.when), 'success');
                }).catch(function (error) {
                    self.busy = false;
                    fail(error);
                });
                return;
            }

            if (self.editing) {
                var editing = self.editing;

                api('ws_edit', { message_id: editing.id, body: body }).then(function (data) {
                    self.busy = false;
                    self.replaceMessage(data.message);
                    self.clearInput();
                    self.editing = null;
                    self.drawReplyBar();
                    self.autosize();
                }).catch(function (error) {
                    self.busy = false;
                    fail(error);
                });
                return;
            }

            // Enter (or Send) on a task preview still on screen, with the
            // text it was drawn from, creates that task as its button does.
            // A changed text is shown back again, and a clash with somebody's
            // leave is overridden only from the card's own button.
            var pending = self.pending;

            if (!options && pending && pending.confirm && pending.card && pending.card.isConnected && !pending.hard && (body === pending.body)) {
                options = pending.confirm();
            }

            var data = { channel_id: self.channel.id, body: body, parent_id: self.reply ? self.reply.id : 0 };

            if (self.reply && self.replyMore && self.replyMore.length) {
                data.quote_ids = self.replyMore.map(function (item) { return item.id; });
            }

            Object.keys(options || {}).forEach(function (key) { data[key] = options[key]; });

            if (self.sendButton && self.sendButton.isConnected) {
                playMotion(self.sendButton, 'ws-pop');
            }

            api('ws_send', data).then(function (result) {
                self.busy = false;

                if (result.command && result.command.type === 'preview') {
                    self.pending = { body: data.body };
                    self.drawPreview(result.command);
                    return;
                }

                // /price, /stock: the old value beside the new, written only
                // from the card.
                if (result.command && result.command.type === 'record_preview') {
                    self.pending = { body: body };
                    self.drawRecordPreview(result.command);
                    return;
                }

                // /product: every value the product would be made with,
                // made only from the card.
                if (result.command && result.command.type === 'create_preview') {
                    self.pending = { body: body };
                    self.drawCreatePreview(result.command);
                    return;
                }

                // /poll with only a question: the rest is asked in the form.
                if (result.command && result.command.type === 'poll_form') {
                    self.pollForm({ question: result.command.question, options: result.command.options }, function () {
                        self.clearInput();
                    });
                    return;
                }

                if (result.command && result.command.type === 'ephemeral') {
                    var box = el('div', 'ws-ephemeral');
                    setHtml(box, result.command.html);
                    var close = button('btn btn-sm btn-text theme-secondary float-end py-0', '', 'bi-x-lg', t('close'));
                    close.addEventListener('click', function () { box.remove(); });
                    box.insertBefore(close, box.firstChild);
                    self.pane.appendChild(box);
                    self.scrollToEnd();

                    // A command that does not exist: nothing was posted, and
                    // the text stays in the box to be put right.
                    if (result.command.keep) {
                        return;
                    }
                }

                if (result.command && result.command.type === 'done' && result.command.task_id) {
                    toast(t('task_created'), 'success');
                }

                if (result.command && result.command.type === 'done' && result.command.saved) {
                    toast(t(result.command.created ? 'qc_created' : 'qe_saved'), 'success');
                }

                // A card's button names the text it sends; a different one in
                // the box was not sent and is kept.
                if (options && (typeof options.body === 'string')) {
                    self.clearSent(options.body);
                } else {
                    self.clearInput();
                }

                self.reply = null;
                self.replyMore = null;
                self.pending = null;
                clear(self.previewBox);
                self.drawReplyBar();
                self.autosize();
                self.sync(true);

                // The message asked Claude: start the run now, beside the
                // conversation. When this call is lost the next screen that
                // opens, or the hourly job, starts it.
                if (result.claude) {
                    api('ws_claude_kick').then(function () { self.sync(); }).catch(function () {});
                }

                if (result.ai) {
                    self.kickAi(true);
                }

                if (result.agent) {
                    self.kickAgent(true);
                }
            }).catch(function (error) {
                self.busy = false;
                fail(error);
            });
        },

        drawPreview: function (command) {
            var self = this;
            var box = clear(self.previewBox);
            var preview = command.preview;
            var card = el('div', 'ws-preview');

            card.appendChild(el('div', 'fw-semibold', t('task_preview')));

            var list = el('dl');

            function row(label, value, markup) {
                list.appendChild(el('dt', '', label));
                var dd = el('dd');

                if (markup) {
                    setHtml(dd, value);
                } else {
                    dd.textContent = value;
                }

                list.appendChild(dd);
            }

            row(t('title'), preview.title);
            row(t('people'), preview.people.map(function (member) { return member.name; }).join(', ') || '—');

            if (preview.department) {
                row(t('department'), preview.department);
            }

            row(t('due_date'), preview.start ? (preview.start + ' → ' + preview.due) : preview.due);
            row(t('estimate'), preview.estimate);
            row(t('priority'), preview.priority);

            if (preview.refs_html) {
                row(t('about_records'), preview.refs_html, true);
            }

            card.appendChild(list);

            if (command.check && command.check.items.length) {
                card.appendChild(warningList(command.check, function (suggestion) {
                    self.send({ confirm: 0, assignees: command.assignees.concat([suggestion.user_id]) });
                }));
            }

            var actions = el('div', 'd-flex flex-wrap gap-3 mt-3');
            var hard = !!(command.check && command.check.hard);
            var pending = self.pending;

            // What send() needs to create this task from Enter: the card it
            // belongs to, whether a leave clash holds it, and the options of
            // the Create button, with the text the preview was drawn from.
            pending.card = card;
            pending.hard = hard;
            pending.confirm = function () {
                return { confirm: 1, assignees: command.assignees, body: pending.body };
            };

            if (!hard) {
                var create = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('create_task'), 'bi-check2');
                create.addEventListener('click', function () { self.send(pending.confirm()); });
                actions.appendChild(create);
            } else if (command.may_override) {
                var force = button('btn btn-sm btn-outline theme-warning', t('create_anyway'), 'bi-exclamation-triangle');
                force.addEventListener('click', function () {
                    var options = pending.confirm();

                    options.force = 1;
                    self.send(options);
                });
                actions.appendChild(force);
            }

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.addEventListener('click', function () {
                self.pending = null;
                clear(self.previewBox);
            });
            actions.appendChild(cancel);
            card.appendChild(actions);

            // Enter creates only what the Create button would; a touch
            // screen's Enter starts a new line instead.
            if (!hard && !touchScreen()) {
                card.appendChild(el('div', 'small fg-secondary mt-1', t('preview_keys')));
            }

            box.appendChild(card);
        },

        drawRecordPreview: function (command) {
            var self = this;
            var box = clear(self.previewBox);
            var preview = command.preview;
            var card = el('div', 'ws-preview');

            card.appendChild(el('div', 'fw-semibold', t('qe_command_title')));

            var list = el('dl');
            list.appendChild(el('dt', '', t('qe_record')));
            var record = el('dd');
            setHtml(record, preview.record_html);
            list.appendChild(record);
            list.appendChild(el('dt', '', preview.label));
            list.appendChild(el('dd', '', preview.from + ' \u2192 ' + preview.to));
            card.appendChild(list);

            if (preview.note) {
                card.appendChild(el('div', 'small fg-secondary mb-1', preview.note));
            }

            // Kept in this channel as a decision, as from the record drawer.
            var tick = el('div', 'form-field mb-1');
            var decision = el('input', 'check');
            decision.type = 'checkbox';
            decision.checked = true;
            decision.id = nextId('ws-cmd-decision-');
            var tickLabel = el('label', 'small', t('qe_decision'));
            tickLabel.htmlFor = decision.id;
            tick.appendChild(decision);
            tick.appendChild(tickLabel);
            card.appendChild(tick);

            // Save writes the text this card was drawn from, not what the box
            // holds by then, which nobody has seen as a card.
            var drawn = self.pending.body;
            var actions = el('div', 'd-flex flex-wrap gap-3 mt-3');
            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('qe_save'), 'bi-check2');
            save.addEventListener('click', function () {
                self.send({ confirm: 1, body: drawn, from: command.from, decision: decision.checked ? 1 : 0 });
            });
            actions.appendChild(save);

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.addEventListener('click', function () {
                self.pending = null;
                clear(self.previewBox);
            });
            actions.appendChild(cancel);
            card.appendChild(actions);
            box.appendChild(card);
        },

        // The /product card: the values the product is made with, the "On
        // sale" tick (off by default), the decision tick, and "All fields",
        // which opens the drawer's new mode with the same values. As on the
        // /price card, Enter draws the card again; only Create writes.
        drawCreatePreview: function (command) {
            var self = this;
            var box = clear(self.previewBox);
            var preview = command.preview;
            var card = el('div', 'ws-preview');

            card.appendChild(el('div', 'fw-semibold', t('qc_command_title')));

            var list = el('dl');
            (preview.rows || []).forEach(function (item) {
                list.appendChild(el('dt', '', item.label));
                var value = el('dd', '', item.html ? '' : item.text);

                if (item.html) {
                    setHtml(value, item.html);
                }

                list.appendChild(value);
            });
            card.appendChild(list);

            var tick = function (label, checked, prefix) {
                var wrap = el('div', 'form-field mb-1');
                var input = el('input', 'check');
                input.type = 'checkbox';
                input.checked = checked;
                input.id = nextId(prefix);
                var text = el('label', 'small', label);
                text.htmlFor = input.id;
                wrap.appendChild(input);
                wrap.appendChild(text);
                card.appendChild(wrap);

                return input;
            };

            var onSale = tick(preview.on_sale, false, 'ws-cmd-sale-');
            card.appendChild(el('div', 'small fg-secondary mb-1', t('qc_unticked')));
            var decision = tick(t('qe_decision'), true, 'ws-cmd-decision-');

            // Create makes what the card shows: the text it was drawn from,
            // not what the box holds by then, as on the /task card.
            var drawn = self.pending.body;
            var actions = el('div', 'd-flex flex-wrap gap-3 mt-3');
            var create = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('qc_create'), 'bi-plus-lg');
            create.addEventListener('click', function () {
                self.send({ confirm: 1, body: drawn, decision: decision.checked ? 1 : 0, on_sale: onSale.checked ? 1 : 0 });
            });
            actions.appendChild(create);

            var all = button('btn btn-sm btn-outline theme-secondary', t('qc_all_fields'), 'bi-layout-sidebar-inset-reverse');
            all.addEventListener('click', function () {
                var values = {};

                Object.keys(command.values || {}).forEach(function (name) { values[name] = command.values[name]; });
                values.enabled = onSale.checked;

                openRecord(preview.kind, 0, '', {
                    values: values,
                    labels: command.labels || {},
                    // The decision goes where the card's own Create puts it:
                    // this composer's channel, also a discussion beside one,
                    // and only if the card's decision tick is still ticked.
                    channelId: self.channel.id,
                    decision: decision.checked,
                    // Made from the drawer: the line that asked for it goes,
                    // a draft typed since the card appeared stays.
                    onCreated: function () {
                        self.pending = null;
                        clear(self.previewBox);
                        self.clearSent(drawn);
                        self.autosize();
                    }
                });
            });
            actions.appendChild(all);

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.addEventListener('click', function () {
                self.pending = null;
                clear(self.previewBox);
            });
            actions.appendChild(cancel);
            card.appendChild(actions);
            box.appendChild(card);
        },

        showHelp: function () {
            var box = el('div', 'ws-ephemeral');
            box.appendChild(el('div', 'ws-ephemeral-title', t('commands')));
            var list = el('dl', 'ws-help');

            (BOOT.commands || []).forEach(function (item) {
                var dt = el('dt');
                dt.appendChild(el('code', '', item.command));
                list.appendChild(dt);
                var dd = el('dd', '', item.description);

                if (item.usage) {
                    dd.appendChild(el('br'));
                    dd.appendChild(el('code', 'ws-help-usage', item.usage));
                }

                dd.appendChild(el('br'));
                dd.appendChild(el('span', 'fg-secondary', item.example));
                list.appendChild(dd);
            });

            box.appendChild(list);
            var close = button('btn btn-sm btn-text theme-secondary float-end py-0', '', 'bi-x-lg', t('close'));
            close.addEventListener('click', function () { box.remove(); });
            box.insertBefore(close, box.firstChild);
            this.pane.appendChild(box);
            this.scrollToEnd();
        },

        attach: function () {
            var self = this;
            var input = document.createElement('input');

            input.type = 'file';
            input.multiple = true;
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            document.body.appendChild(input);

            input.addEventListener('change', function () {
                var files = Array.prototype.slice.call(input.files || []);
                input.remove();
                self.uploadFiles(files);
            });

            input.click();
        },

        // A copy of a file of the file manager, the text in the composer
        // going with it as it does with an upload.
        attachFromLibrary: function () {
            var self = this;

            if (!self.channel || !self.channel.can_post) {
                return;
            }

            libraryPicker(function (file) {
                var body = self.input ? self.tokens(self.inputText()) : '';

                api('ws_library_attach', { channel_id: self.channel.id, file_id: file.id, body: body }).then(function () {
                    if (body !== '') {
                        self.clearInput();
                    }

                    self.sync(true);
                }).catch(fail);
            });
        },

        // Files picked, dropped or pasted: one after the other, the text in
        // the composer going with the first. What the file manager refuses
        // (PHP, .htaccess, web.config, shell scripts, programs) the server
        // refuses here too, and says so. options (a recording, voiceSend()):
        // body sent instead of the box's text, which then stays in the box;
        // parent_id, the message the files answer; sent(), once any is posted.
        uploadFiles: function (files, options) {
            var self = this;
            var settings = options || {};
            var queue = (files || []).slice();
            var total = queue.length;
            var done = 0;
            var ownBody = (typeof settings.body === 'string');
            var body = ownBody ? settings.body : (self.input ? self.tokens(self.inputText()) : '');

            if (!total || !self.channel || !self.channel.can_post) {
                return;
            }

            function next() {
                var file = queue.shift();

                if (!file) {
                    if (done > 0) {
                        if (settings.sent) {
                            settings.sent();
                        }

                        self.sync(true);
                    }

                    return;
                }

                if (file.size > BOOT.upload.max) {
                    toast(t('file_too_large_named', file.name, Math.floor(BOOT.upload.max / 1048576)), 'warning');
                    next();
                    return;
                }

                var reader = new FileReader();

                reader.onload = function () {
                    toast((total > 1) ? t('uploading_count', done + 1, total) : t('uploading'), 'secondary');

                    api('ws_attach', {
                        channel_id: self.channel.id,
                        name: file.name || ('image-' + Date.now() + '.png'),
                        data: reader.result,
                        body: body,
                        parent_id: settings.parent_id || 0
                    }).then(function () {
                        done++;

                        if (body !== '') {
                            body = '';

                            if (!ownBody) {
                                self.clearInput();
                            }
                        }

                        next();
                    }).catch(function (error) {
                        fail(error);
                        next();
                    });
                };

                reader.onerror = function () {
                    toast(t('error_generic'), 'danger');
                    next();
                };

                reader.readAsDataURL(file);
            }

            next();
        },

        // Files dragged onto the conversation are posted into it; a picture
        // pasted into the composer too.
        bindDrop: function () {
            var self = this;
            var center = self.center;
            var depth = 0;
            var overlay = null;

            function hasFiles(event) {
                var types = event.dataTransfer ? Array.prototype.slice.call(event.dataTransfer.types || []) : [];
                return types.indexOf('Files') !== -1;
            }

            function show() {
                if (overlay || !self.channel || !self.channel.can_post) {
                    return;
                }

                overlay = el('div', 'ws-drop-overlay');
                var inner = el('div', 'ws-drop-inner');
                inner.appendChild(icon('bi-cloud-arrow-up'));
                inner.appendChild(el('b', '', t('drop_here', self.channel.name)));
                inner.appendChild(el('small', '', t('drop_rules')));
                overlay.appendChild(inner);
                center.appendChild(overlay);
            }

            function hide() {
                depth = 0;

                if (overlay) {
                    overlay.remove();
                    overlay = null;
                }
            }

            center.addEventListener('dragenter', function (event) {
                if (!hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                depth++;
                show();
            });

            center.addEventListener('dragover', function (event) {
                if (hasFiles(event)) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = (self.channel && self.channel.can_post) ? 'copy' : 'none';
                }
            });

            center.addEventListener('dragleave', function (event) {
                if (!hasFiles(event)) {
                    return;
                }

                depth--;

                if (depth <= 0) {
                    hide();
                }
            });

            center.addEventListener('drop', function (event) {
                if (!hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                hide();
                self.uploadFiles(Array.prototype.slice.call(event.dataTransfer.files || []));
            });

            center.addEventListener('paste', function (event) {
                if (!event.target.closest('.ws-composer')) {
                    return;
                }

                var files = Array.prototype.slice.call((event.clipboardData && event.clipboardData.files) || []);

                if (files.length) {
                    event.preventDefault();
                    self.uploadFiles(files);
                }
            });
        },

        // ── The tag picker in the composer ──

        // The @, # and / buttons. A second press takes the sign back - with
        // the search typed after it, when its list is open - instead of
        // adding one more: pressed three times the box read "# # #".
        insertTrigger: function (sigil) {
            var mode = { '@': 'people', '#': 'record', '/': 'command' }[sigil];
            var input = this.input;
            var before = this.rich ? (this.rich.restore(), this.rich.beforeCaret()) : input.value.slice(0, input.selectionStart);
            var count = 0;

            if (this.picker && (this.pickerMode === mode) && (before.charAt(this.pickerStart) === sigil)) {
                count = before.length - this.pickerStart;
            } else if (before.slice(-1) === sigil) {
                count = 1;
            }

            if (count > 0) {
                // The space the button put before the sign goes with it.
                var added = this.triggerAdded;

                if (added && (added.sigil === sigil) && added.space && (before.slice(0, before.length - count) === added.before + ' ')) {
                    count += 1;
                }

                this.triggerAdded = null;
                this.closePicker();

                if (this.rich) {
                    this.rich.replaceTyped(count, { text: '' });
                    this.rich.node.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    var rest = input.value.slice(input.selectionEnd);

                    input.value = before.slice(0, before.length - count) + rest;
                    input.selectionStart = input.selectionEnd = before.length - count;
                    input.focus();
                }

                this.autosize();
                return;
            }

            var space = this.rich ? ((before && !/[\s\uFFFC]$/.test(before)) ? ' ' : '') : ((before && !/\s$/.test(before)) ? ' ' : '');

            this.triggerAdded = { sigil: sigil, space: space !== '', before: before };

            if (this.rich) {
                this.rich.insertText(space + sigil);
                this.pickerCheck();
                return;
            }

            var start = input.selectionStart;
            var after = input.value.slice(input.selectionEnd);

            input.value = before + space + sigil + after;
            input.selectionStart = input.selectionEnd = start + space.length + 1;
            input.focus();
            this.pickerCheck();
        },

        pickerCheck: function () {
            var self = this;
            var before = self.beforeCaret();
            var match;

            // A list's first item starting with "/" is words, no command.
            if (!self.noCommands && !(self.rich && self.rich.inListItem()) && (match = /^\/([^\s]*)$/.exec(before))) {
                self.argDismissed = null;
                self.drawCommandStrip(null);
                self.openPicker('command', match[1], before.length - match[0].length);
                return;
            }

            // After "/name ": the argument the command expects next
            // (commandArgument()), shown in the strip above the box. Only the
            // channel's composer reads commands.
            var argument = (self.noCommands || (self.rich && self.rich.inListItem())) ? null : self.commandArgument(before);

            if (!self.noCommands) {
                if (!argument) {
                    self.argDismissed = null;
                }

                self.drawCommandStrip(argument);
            }

            // ":" and two letters suggest emoji in a box with the emoji
            // button; not while a word is composed, nor in code, a link, a
            // cell or a card. Escape leaves that ":" alone (emojiSkip).
            var colon = (self.colonEmoji && self.rich && !self.rich.node.wsComposing && !self.rich.caretBlocked()) ? colonEmojiAt(before) : null;

            if (!colon) {
                self.emojiSkip = -1;
            } else if ((colon.start !== self.emojiSkip) && findEmoji(colon.word).length) {
                self.openPicker('emoji', colon.word, colon.start);
                return;
            }

            // A chip right before the sigil counts as a word boundary.
            if ((match = /(^|[\s\uFFFC])@([^\s@#<>\uFFFC]{0,30})$/.exec(before))) {
                self.openPicker('people', match[2], before.length - match[2].length - 1);
                return;
            }

            if ((match = /(^|[\s\uFFFC])#([^\n#@<>\uFFFC]{0,40})$/.exec(before)) && self.recordQuery(match[2], before.length - match[2].length - 1)) {
                self.openPicker('record', match[2], before.length - match[2].length - 1);
                return;
            }

            // Its suggestions, unless Escape sent them away for this argument.
            if (argument && self.argumentSource(argument) && (self.argDismissed !== self.argumentKey(argument))) {
                self.argument = argument;
                self.openPicker('arg', argument.query, argument.start);
                return;
            }

            self.closePicker();
        },

        // \u2500\u2500 The argument popup after a command \u2500\u2500
        //
        // The registry (BOOT.commands, includes/workspace/commands.php
        // ws_commands()) gives each command its arguments in order, each
        // with a kind and where its values come from. After "/name " the
        // popup offers values for the argument expected next: people from
        // BOOT.people, tasks and products from ws_ref_search (only what the
        // person may see), the days, estimates and priorities of
        // BOOT.command_values. Tab writes the first row (or the one picked
        // with the arrows, as Enter then does) and a space, and the popup
        // opens for the next argument; Enter with no row picked sends the
        // line as it is; a space moves on; Escape closes it until the next
        // argument, and Tab brings it back.

        // The registry entry a typed name belongs to: any of its names, in
        // Turkish lower case or plain lower case, as the server reads it.
        commandEntry: function (typed) {
            var lower = typed.toLocaleLowerCase('tr');
            var plain = typed.toLowerCase();
            var found = null;

            (BOOT.commands || []).forEach(function (item) {
                (item.names || []).forEach(function (name) {
                    if (!found && ((name.toLocaleLowerCase('tr') === lower) || (name.toLowerCase() === plain))) {
                        found = item;
                    }
                });
            });

            return found;
        },

        // Where the writer is in a command, from what is written before the
        // caret: the entry, the name as typed, the word being typed (query)
        // and where it starts, and the argument expected now (index, arg).
        // Written in order, each word or tag fills the next argument; one
        // that takes several keeps the rest. A free text first (/task's
        // title) takes every word, until the arguments the parser reads
        // anywhere are offered (extras) once it has one; a poll's options
        // start at the first |. null outside a command, before the space
        // after its name, and while a message or a scheduled one is edited.
        commandArgument: function (before) {
            var self = this;

            if (self.noCommands || self.editing || self.editingScheduled || !BOOT || !BOOT.commands) {
                return null;
            }

            var match = /^\/(\S+)(\s+)([\s\S]*)$/.exec(before);
            var entry = match ? self.commandEntry('/' + match[1]) : null;

            if (!entry) {
                return null;
            }

            var rest = match[3];
            var word = /[^\s\uFFFC]*$/.exec(rest)[0];
            var done = rest.slice(0, rest.length - word.length).replace(/\uFFFC/g, ' \uFFFC ').split(/\s+/).filter(Boolean);
            var args = entry.args || [];
            var state = { entry: entry, typed: '/' + match[1], query: word, start: before.length - word.length, index: -1, arg: null, extras: null };

            if (!args.length) {
                return state;
            }

            if (args[0].kind === 'text') {
                var anywhere = args.filter(function (arg) { return arg.anywhere; });
                var options = args.filter(function (arg) { return arg.kind === 'options'; })[0];

                if (anywhere.length && done.length) {
                    state.index = 'extras';
                    state.extras = self.commandExtras(anywhere, done);
                } else if (options && (rest.indexOf('|') !== -1)) {
                    state.index = args.indexOf(options);
                    state.arg = options;
                } else {
                    state.index = 0;
                    state.arg = args[0];
                }

                return state;
            }

            var index = 0;

            done.forEach(function () {
                if ((index < args.length) && !args[index].multiple) {
                    index++;
                }
            });

            state.index = index;
            state.arg = args[index] || null;

            return state;
        },

        // Which of the arguments read anywhere (/task's people, day,
        // estimate, priority, records) the line does not hold yet: the tags
        // are looked for in the whole text, the words before the caret.
        commandExtras: function (args, done) {
            var markup = this.tokens(this.inputText());
            var days = ((BOOT.command_values && BOOT.command_values.dates) || []).map(function (item) { return foldText(item.value); });
            var isDay = function (word) {
                return (days.indexOf(foldText(word)) !== -1) || /^\+\d{1,3}\D*$/.test(word) || /^\d{1,2}[.\/]\d{1,2}([.\/]\d{2,4})?(-\d{1,2}[.\/]\d{1,2}([.\/]\d{2,4})?)?$/.test(word);
            };
            var starts = function (sign) {
                return done.some(function (word) { return word.charAt(0) === sign; });
            };

            return args.filter(function (arg) {
                switch (arg.suggest) {
                    case 'people_departments':
                    case 'people':
                        return !/<@(user|dept):\d+>/.test(markup);
                    case 'records':
                        return !/<#[a-z_]+:\d+>/.test(markup);
                    case 'dates':
                        return !done.some(isDay);
                    case 'estimates':
                        return !starts('~');
                    case 'priorities':
                        return !starts('!');
                }

                return false;
            });
        },

        // Where the popup finds values for the argument: its registry
        // source, 'extras' for /task's missing parts, '' for none (a free
        // text, an amount, a quantity, a poll's options).
        argumentSource: function (argument) {
            if (argument.extras) {
                return argument.extras.length ? 'extras' : '';
            }

            return (argument.arg && argument.arg.suggest) || '';
        },

        // What Escape sends the popup away for: one argument of one
        // command; /task's parts all together.
        argumentKey: function (argument) {
            return argument.entry.key + ':' + argument.index;
        },

        // Tab in a command with no popup open (or after Escape): the
        // suggestions for the argument at the caret. false when there are
        // none, and Tab does what it always does.
        commandTab: function () {
            var argument = this.commandArgument(this.beforeCaret());

            if (!argument || !this.argumentSource(argument)) {
                return false;
            }

            this.argDismissed = null;
            this.pickerCheck();

            return !!this.picker;
        },

        // The popup's rows for the argument expected now (this.argument).
        openArgument: function (query) {
            var self = this;
            var argument = self.argument;
            var source = self.argumentSource(argument);
            var values = BOOT.command_values || {};
            var typed = foldText(query);
            var first = query.charAt(0);

            self.argTicket = null;
            clearTimeout(self.pickerTimer);

            // Before anything is typed the first values, then every one that
            // starts with what is typed.
            var fixed = function (list, icon) {
                return (list || []).filter(function (item) {
                    return typed ? (foldText(item.value).indexOf(typed) === 0) : item.top;
                }).map(function (item) {
                    return { label: item.value, meta: item.meta, icon: icon, insert: item.value + ' ' };
                });
            };

            // A fixed list that has nothing for the word typed closes: the
            // word belongs to the text (a title), not to the list.
            var show = function (items) {
                if (!items.length) {
                    self.closePicker();
                    return;
                }

                self.drawPicker(items);
            };

            if (source === 'extras') {
                var rows = [];

                // The parts with a sign of their own come as the sign, which
                // opens its own list: @ the people, # the records, ~ and !
                // their values.
                argument.extras.forEach(function (arg) {
                    if (arg.suggest === 'people_departments' || arg.suggest === 'people') {
                        if (!query) {
                            rows.push({ label: '@', meta: t('cmd_extra_people'), icon: 'bi-at', insert: '@' });
                        }
                    } else if (arg.suggest === 'dates') {
                        if ((first !== '~') && (first !== '!')) {
                            rows = rows.concat(fixed(values.dates, 'bi-calendar-event'));
                        }
                    } else if (arg.suggest === 'estimates') {
                        if (!query) {
                            rows.push({ label: '~', meta: t('cmd_extra_estimate'), icon: 'bi-hourglass-split', insert: '~' });
                        } else if (first === '~') {
                            rows = rows.concat(fixed(values.estimates, 'bi-hourglass-split'));
                        }
                    } else if (arg.suggest === 'priorities') {
                        if (!query) {
                            rows.push({ label: '!', meta: t('cmd_extra_priority'), icon: 'bi-flag', insert: '!' });
                        } else if (first === '!') {
                            rows = rows.concat(fixed(values.priorities, 'bi-flag'));
                        }
                    } else if ((arg.suggest === 'records') && !query) {
                        rows.push({ label: '#', meta: t('tag_record'), icon: 'bi-hash', insert: '#' });
                    }
                });

                show(rows);
                return;
            }

            if (source === 'dates') {
                show(fixed(values.dates, 'bi-calendar-event'));
                return;
            }

            if (source === 'estimates') {
                show(fixed(values.estimates, 'bi-hourglass-split'));
                return;
            }

            if (source === 'priorities') {
                show(fixed(values.priorities, 'bi-flag'));
                return;
            }

            if (source === 'statuses') {
                show(fixed(values.statuses, 'bi-circle-half'));
                return;
            }

            // The team, as the @ picker lists it, without whoever the line
            // already names; never the site's members.
            if (source === 'people' || source === 'people_departments') {
                var needle = query.toLocaleLowerCase();
                var markup = self.tokens(self.inputText());
                var people = [];
                var fits = function (text) {
                    return !needle || String(text || '').toLocaleLowerCase().indexOf(needle) !== -1;
                };

                (BOOT.people || []).forEach(function (member) {
                    if ((markup.indexOf('<@user:' + member.id + '>') === -1) && (fits(member.name) || fits(member.username))) {
                        people.push({ label: member.name, meta: member.title, avatar: member.avatar, face: member, token: '<@user:' + member.id + '>', shown: shownLabel('@', member.name) });
                    }
                });

                if (source === 'people_departments') {
                    (BOOT.departments || []).forEach(function (item) {
                        if ((markup.indexOf('<@dept:' + item.id + '>') === -1) && fits(item.name)) {
                            people.push({ label: item.name, meta: t('department'), icon: 'bi-people', token: '<@dept:' + item.id + '>', shown: shownLabel('@', item.name) });
                        }
                    });
                }

                if (!people.length && !query) {
                    self.closePicker();
                    return;
                }

                self.drawPicker(people.slice(0, 8), t('nothing_found'));
                return;
            }

            // Tasks and records from the search the # picker uses: only what
            // the person may see. A task is written as its number (T-42), a
            // record as its tag.
            var type = { tasks: 'task', products: 'product', records: 'all' }[source];

            if (!type || ((type !== 'all') && !(BOOT.ref_types && BOOT.ref_types[type]))) {
                self.closePicker();
                return;
            }

            if ((type === 'all') && (query.trim().length < 2)) {
                self.drawPicker([], t('type_to_search'));
                return;
            }

            // Only the latest search draws: an answer for an earlier popup
            // (another command, the same empty query) is dropped.
            var ticket = {};

            self.argTicket = ticket;
            self.drawPicker(null);

            clearTimeout(self.pickerTimer);
            self.pickerTimer = setTimeout(function () {
                api('ws_ref_search', { type: type, q: query }).then(function (data) {
                    if (!self.picker || (self.pickerMode !== 'arg') || (self.argTicket !== ticket)) {
                        return;
                    }

                    self.drawPicker((data.items || []).map(function (item) {
                        if (type === 'task') {
                            return { label: item.label, meta: item.meta, icon: item.icon || 'bi-check2-square', insert: String(item.label).split(' \u00B7 ')[0] + ' ' };
                        }

                        return { label: item.label, meta: item.meta, icon: item.icon, token: item.token, shown: shownLabel('#', item.label) };
                    }), t('nothing_found'));
                }).catch(function () {});
            }, 220);
        },

        // Above the box while a command is written: the name as typed and
        // its arguments, <required> and [optional], the one expected now
        // highlighted, then the command's example.
        drawCommandStrip: function (argument) {
            var strip = this.commandStrip;

            if (!strip) {
                return;
            }

            clear(strip);

            if (!argument) {
                return;
            }

            var entry = argument.entry;
            var row = el('div', 'ws-cmd-strip');
            var usage = el('span', 'ws-cmd-usage');

            usage.appendChild(el('span', 'ws-cmd-name', argument.typed));

            (entry.args || []).forEach(function (arg, index) {
                var current = (argument.index === index) || (!!argument.extras && (argument.extras.indexOf(arg) !== -1));

                usage.appendChild(document.createTextNode(' '));
                usage.appendChild(el('span', 'ws-cmd-arg' + (current ? ' ws-cmd-current' : ''), arg.optional ? '[' + arg.label + ']' : '<' + arg.label + '>'));
            });

            row.appendChild(usage);

            if (entry.example) {
                row.appendChild(el('span', 'ws-cmd-example', t('cmd_example', entry.example)));
            }

            strip.appendChild(row);
            this.commandLive(argument);
        },

        // Under the usage of a command the registry marks live (/task,
        // /status): what sending the line now would do, read by the parser
        // that runs it (ws_command_suggest), and what it cannot read, such
        // as a typed @name no team member, or several, answer to. The last
        // answer stays on screen until the next one comes.
        commandLive: function (argument) {
            var self = this;
            var body = self.inputText();

            if (!self.channel || !argument.entry.live) {
                return;
            }

            if (self.cmdLive) {
                self.drawCommandLive(self.cmdLive);
            }

            if (self.cmdLive && (self.cmdLive.body === body)) {
                return;
            }

            var channelId = self.channel.id;

            clearTimeout(self.cmdLiveTimer);
            self.cmdLiveTimer = setTimeout(function () {
                api('ws_command_suggest', { channel_id: channelId, body: body }).then(function (data) {
                    if (!self.channel || (self.channel.id !== channelId)) {
                        return;
                    }

                    self.cmdLive = { body: body, line: data.line || '', warnings: data.warnings || [] };

                    if (self.inputText() === body) {
                        self.drawCommandLive(self.cmdLive);
                    }
                }).catch(function () {});
            }, 300);
        },

        drawCommandLive: function (live) {
            var strip = this.commandStrip;

            // An empty strip: the command was sent or taken out meanwhile.
            if (!strip || !strip.firstChild) {
                return;
            }

            var old = strip.querySelector('.ws-cmd-live');

            if (old) {
                old.remove();
            }

            if (!live.line && !live.warnings.length) {
                return;
            }

            var row = el('div', 'ws-cmd-strip ws-cmd-live flex-column align-items-start gap-0');

            if (live.line) {
                row.appendChild(el('span', 'fg-body', live.line));
            }

            live.warnings.forEach(function (text) {
                row.appendChild(el('span', 'fg-emphasis-warning', text));
            });

            strip.appendChild(row);
        },

        // Is what follows a # still a search? Not once it is a tag that was
        // already picked (the text typed after it is the message), and a space
        // only belongs to a search that starts with a type ("#sip 1045").
        recordQuery: function (query, start) {
            // In the rich box a picked tag is a chip, not text to look at.
            var typed = this.rich ? '' : this.input.value.slice(start);
            var picked = this.labels.some(function (item) {
                return (item.label.charAt(0) === '#') && (typed.indexOf(item.label) === 0);
            });

            if (picked) {
                return false;
            }

            var words = query.split(' ');

            if (words.length === 1) {
                return true;
            }

            if (words.length > 3) {
                return false;
            }

            return Object.keys(BOOT.ref_types).some(function (key) {
                return BOOT.ref_types[key].prefixes.indexOf(words[0].toLocaleLowerCase()) !== -1;
            });
        },

        openPicker: function (mode, query, start) {
            var self = this;

            if (!self.picker) {
                self.picker = el('div', 'ws-picker');
                self.picker.setAttribute('role', 'listbox');
                self.composerWrap.appendChild(self.picker);
                self.pickerType = 'all';
            }

            self.pickerMode = mode;
            self.pickerStart = start;
            self.pickerQuery = query;

            if (mode === 'arg') {
                self.openArgument(query);
                return;
            }

            // The emoji matching the word after ":", the recent ones first.
            if (mode === 'emoji') {
                self.picker.setAttribute('aria-label', t('emoji_suggest'));
                self.drawPicker(colonEmojiList(findEmoji(query), recentEmoji(), 8).map(function (emoji) {
                    return { emoji: emoji, glyph: emoji, label: (EMOJI_WORDS[emoji] || '').split(' ').slice(0, 3).join(' ') };
                }));
                return;
            }

            // Every name a command answers to, Turkish or English: the name
            // as typed first ("/gö" shows /görev), then with the Turkish
            // letters folded ("/go" finds it too, "/YARDIM" finds /yardım).
            // With nothing typed, the name in the screen's language.
            if (mode === 'command') {
                var typed = ('/' + query).toLocaleLowerCase('tr');
                var typedFolded = foldText(typed);
                var commands = [];

                (BOOT.commands || []).forEach(function (item) {
                    var aliases = item.command.split(' · ');
                    var names = (item.names && item.names.length) ? item.names : aliases;
                    var match = item.name || aliases[0];

                    if (query !== '') {
                        match = null;

                        names.forEach(function (name) {
                            if (!match && name.toLocaleLowerCase('tr').indexOf(typed) === 0) {
                                match = name;
                            }
                        });

                        names.forEach(function (name) {
                            if (!match && foldText(name).indexOf(typedFolded) === 0) {
                                match = name;
                            }
                        });
                    }

                    if (match) {
                        commands.push({ label: match, meta: item.description, detail: item.example, icon: 'bi-slash-square', insert: match + ' ' });
                    }
                });

                self.drawPicker(commands);
                return;
            }

            if (mode === 'people') {
                var needle = query.toLocaleLowerCase();
                var items = [];

                // Claude and Pinegrap AI as a note asks them: a tag once
                // connected, before that the name as text, which answers with
                // how to set it up.
                var noteAssistants = function () {
                    if (BOOT.claude && (!needle || 'claude'.indexOf(needle) === 0)) {
                        items.push(BOOT.claude.token
                            ? { label: 'Claude', meta: t('claude_meta'), icon: 'bi-stars', token: BOOT.claude.token, shown: '@Claude' }
                            : { label: 'Claude', meta: t('claude_not_ready'), icon: 'bi-stars', insert: '@Claude ' });
                    }

                    if (BOOT.ai && (!needle || 'ai'.indexOf(needle) === 0 || 'pinegrap'.indexOf(needle) === 0)) {
                        items.push(BOOT.ai.token
                            ? { label: BOOT.ai.name, meta: BOOT.ai.handle, icon: 'bi-cpu', token: BOOT.ai.token, shown: BOOT.ai.handle }
                            : { label: BOOT.ai.name, meta: t('ai_not_ready'), icon: 'bi-cpu', insert: BOOT.ai.handle + ' ' });
                    }
                };

                // Other services' MCP servers, by their alias: written as
                // text, and Pinegrap AI answers with the server's tools, or
                // the server's own agent answers. Drawn with the avatar it
                // has on its answers (ws_mcp_boot_entry()).
                var mcpAliases = function () {
                    (BOOT.mcp || []).forEach(function (server) {
                        if (!needle || server.alias.indexOf(needle) === 0 || server.name.toLocaleLowerCase().indexOf(needle) !== -1) {
                            items.push({ label: '@' + server.alias, meta: (server.name ? server.name + ' · ' : '') + t(server.agent ? 'mcp_meta_agent' : 'mcp_meta'), avatar: server.avatar || '', face: { avatar: server.avatar || '', initials: server.initials, tone: server.tone, name: server.alias }, icon: server.icon || 'bi-plug', insert: '@' + server.alias + ' ' });
                        }
                    });
                };

                // In a note @ asks Claude; people are tagged with #.
                if (self.notesMode) {
                    noteAssistants();
                    self.drawPicker(items);
                    return;
                }

                // A new note on a task asks an assistant about the task by
                // its name (includes/workspace/delegation.php): the
                // assistants and the aliases come before the people.
                if (self.asksAssistants) {
                    noteAssistants();
                    mcpAliases();
                }

                // Claude: a tag where it may be asked; elsewhere, or before it
                // is connected, "@Claude" as text, which answers with how to
                // set it up or open the channel to it.
                if (BOOT.claude && self.channel && (!needle || 'claude'.indexOf(needle) === 0)) {
                    if (BOOT.claude.token && self.channel.claude && self.channel.claude.available) {
                        items.push({ label: 'Claude', meta: t('claude_meta'), icon: 'bi-stars', token: BOOT.claude.token, shown: '@Claude' });
                    } else {
                        items.push({ label: 'Claude', meta: BOOT.claude.ready ? t('claude_closed_here') : t('claude_not_ready'), icon: 'bi-stars', insert: '@Claude ' });
                    }
                }

                // Pinegrap AI the same way, with @ai.
                if (BOOT.ai && self.channel && (!needle || 'ai'.indexOf(needle) === 0 || 'pinegrap'.indexOf(needle) === 0)) {
                    if (BOOT.ai.token && self.channel.ai && self.channel.ai.available) {
                        items.push({ label: BOOT.ai.name, meta: BOOT.ai.handle, icon: 'bi-cpu', token: BOOT.ai.token, shown: BOOT.ai.handle });
                    } else {
                        items.push({ label: BOOT.ai.name, meta: BOOT.ai.ready ? t('ai_closed_here') : t('ai_not_ready'), icon: 'bi-cpu', insert: BOOT.ai.handle + ' ' });
                    }
                }

                if (self.channel) {
                    mcpAliases();

                    // Outside applications a mention here reaches, by their
                    // handle or name (app_handles.php); before the people,
                    // as the list is cut at ten.
                    (self.channel.apps || []).forEach(function (app) {
                        if (!needle || app.shown.slice(1).indexOf(needle) === 0 || app.meta.toLocaleLowerCase().indexOf(needle) === 0) {
                            items.push({ label: app.label, meta: app.meta, icon: app.icon, token: app.token, shown: app.shown });
                        }
                    });
                }

                BOOT.people.forEach(function (member) {
                    if (!needle || member.name.toLocaleLowerCase().indexOf(needle) !== -1 || member.username.toLocaleLowerCase().indexOf(needle) !== -1) {
                        items.push({ label: member.name, meta: member.title, avatar: member.avatar, face: member, token: '<@user:' + member.id + '>', shown: shownLabel('@', member.name) });
                    }
                });

                BOOT.departments.forEach(function (item) {
                    if (!needle || item.name.toLocaleLowerCase().indexOf(needle) !== -1) {
                        items.push({ label: item.name, meta: t('department'), icon: 'bi-people', token: '<@dept:' + item.id + '>', shown: shownLabel('@', item.name) });
                    }
                });

                self.drawPicker(items.slice(0, 10));
                return;
            }

            // Records: "#sip 1045" picks the type by its prefix; otherwise the
            // highlighted type is searched.
            var type = self.pickerType;
            var search = query;
            var prefix = /^(\S+)\s(.*)$/.exec(query);

            if (prefix) {
                Object.keys(BOOT.ref_types).forEach(function (key) {
                    if (BOOT.ref_types[key].prefixes.indexOf(prefix[1].toLocaleLowerCase()) !== -1) {
                        type = key;
                        search = prefix[2];
                    }
                });
            }

            self.pickerType = type;

            // In a note the people come first, from the first letter.
            var people = [];

            if (self.notesMode && (type === 'all')) {
                var wanted = search.trim().toLocaleLowerCase();

                BOOT.people.forEach(function (member) {
                    if ((people.length < 5) && (!wanted || member.name.toLocaleLowerCase().indexOf(wanted) !== -1 || member.username.toLocaleLowerCase().indexOf(wanted) !== -1)) {
                        people.push({ label: member.name, meta: t('notes_person'), avatar: member.avatar, face: member, token: '<@user:' + member.id + '>', shown: shownLabel('@', member.name) });
                    }
                });
            }

            // Every kind at once needs a couple of letters to go on.
            if ((type === 'all') && (search.trim().length < 2)) {
                clearTimeout(self.pickerTimer);
                self.drawPicker(people, people.length ? '' : t('type_to_search'));
                return;
            }

            self.drawPicker(people.length ? people : null);

            clearTimeout(self.pickerTimer);
            self.pickerTimer = setTimeout(function () {
                var asked = search;

                // Titled blocks come with every kind of record, after them
                // (includes/workspace/blocks.php).
                var blocks = (CFG.blocks && (type === 'all')) ? api('ws_block_search', { q: search }).catch(function () { return { items: [] }; }) : Promise.resolve({ items: [] });

                Promise.all([api('ws_ref_search', { type: type, q: search }), blocks]).then(function (results) {
                    if (!self.picker || self.pickerMode !== 'record' || asked !== search) {
                        return;
                    }

                    var records = results[0].items.map(function (item) {
                        return { label: item.label, meta: item.meta, icon: item.icon, badge: item.badge || '', token: item.token, shown: shownLabel('#', item.label) };
                    });
                    var titled = (results[1].items || []).slice(0, 5).map(function (item) {
                        return { label: item.title, meta: t('pull_kind_' + item.kind) + ' · ' + item.where + ' · ' + item.author, icon: blockIcon(item.kind), block: item.id };
                    });

                    self.drawPicker(people.concat(records, titled));
                }).catch(function () {});
            }, 220);
        },

        drawPicker: function (items, emptyText) {
            var self = this;
            var picker = clear(self.picker);

            if (self.pickerMode === 'record') {
                var types = el('div', 'ws-picker-types');
                var kinds = ['all'].concat(Object.keys(BOOT.ref_types));

                kinds.forEach(function (key) {
                    var chip = button(key === self.pickerType ? 'active' : '', (key === 'all') ? t('all_types') : BOOT.ref_types[key].label);
                    chip.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        self.pickerType = key;
                        self.openPicker('record', self.pickerQuery.replace(/^\S+\s/, ''), self.pickerStart);
                    });
                    types.appendChild(chip);
                });

                picker.appendChild(types);

                // The row scrolls sideways; keep the chosen kind in view.
                var active = types.querySelector('.active');

                if (active) {
                    window.requestAnimationFrame(function () {
                        types.scrollLeft = Math.max(0, active.offsetLeft - 24);
                    });
                }
            }

            var list = el('div', 'ws-picker-list');
            picker.appendChild(list);

            if (items === null) {
                list.appendChild(el('div', 'ws-picker-empty', t('searching')));
                self.pickerItems = [];

                if (self.notesMode || self.caretPicker) {
                    self.placePicker();
                }

                return;
            }

            // The argument popup after a command picks no row by itself, so
            // Enter still sends the line; the row Tab would write is marked.
            var argMode = (self.pickerMode === 'arg');

            self.pickerItems = items;
            self.pickerIndex = argMode ? -1 : 0;

            if (!items.length) {
                list.appendChild(el('div', 'ws-picker-empty', emptyText || t('nothing_found')));
            }

            items.forEach(function (item, index) {
                var row = el('button', 'ws-picker-item' + ((index === 0) ? (argMode ? ' ws-picker-next' : ' active') : ''));
                row.type = 'button';

                if (item.glyph) {
                    row.appendChild(el('span', 'ws-picker-emoji', item.glyph));
                } else if (item.avatar) {
                    row.appendChild(avatar(item.face || { avatar: item.avatar, name: item.label }));
                } else {
                    row.appendChild(badgedIcon(item.icon || 'bi-dot', item.badge || ''));
                }

                // A second, muted line under the label (a command's example).
                if (item.detail) {
                    var text = el('span', 'ws-picker-text');

                    text.appendChild(el('span', 'ws-picker-label', item.label));
                    text.appendChild(el('span', 'ws-picker-detail', item.detail));
                    row.appendChild(text);
                } else {
                    row.appendChild(el('span', 'ws-picker-label', item.label));
                }

                if (item.meta) {
                    row.appendChild(el('span', 'ws-picker-meta', item.meta));
                }

                row.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    self.pick(item);
                });
                list.appendChild(row);
            });

            if (argMode && items.length && !touchScreen()) {
                picker.appendChild(el('div', 'ws-picker-keys', t('cmd_keys')));
            }

            if (self.notesMode || self.caretPicker) {
                self.placePicker();
            }
        },

        // In a long writing box (a note) the picker opens at the caret, not at
        // the box's edge.
        placePicker: function () {
            var picker = this.picker;
            var selection = window.getSelection();

            if (!picker || !selection || !selection.rangeCount) {
                return;
            }

            var rect = selection.getRangeAt(0).getBoundingClientRect();

            if (!rect || (!rect.width && !rect.height && !rect.left && !rect.top)) {
                rect = this.input.getBoundingClientRect();
            }

            picker.style.position = 'fixed';
            picker.style.bottom = 'auto';

            var width = picker.offsetWidth;
            var height = picker.offsetHeight;
            var top = rect.bottom + 6;

            if (top + height > window.innerHeight - 8) {
                top = Math.max(8, rect.top - height - 6);
            }

            picker.style.top = top + 'px';
            picker.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
        },

        pickerKey: function (event) {
            var items = this.pickerItems || [];
            var argMode = (this.pickerMode === 'arg');

            if (event.key === 'Escape') {
                event.preventDefault();

                // A command's suggestions stay away until its next argument.
                if (argMode && this.argument) {
                    this.argDismissed = this.argumentKey(this.argument);
                }

                // An emoji list stays away for this ":".
                if (this.pickerMode === 'emoji') {
                    this.emojiSkip = this.pickerStart;
                }

                this.closePicker();
                return true;
            }

            if (!items.length) {
                return false;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();

                if (argMode && (this.pickerIndex < 0)) {
                    this.pickerIndex = (event.key === 'ArrowDown') ? 0 : items.length - 1;
                } else {
                    this.pickerIndex = (this.pickerIndex + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                }

                Array.prototype.forEach.call(this.picker.querySelectorAll('.ws-picker-item'), function (row, index) {
                    row.classList.toggle('active', index === this.pickerIndex);

                    if (argMode) {
                        row.classList.toggle('ws-picker-next', index === this.pickerIndex);
                    }
                }, this);

                return true;
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
                // After a command Enter writes only a row picked with the
                // arrows, otherwise the line is sent; Shift+Tab is the
                // browser's.
                if (argMode && (((event.key === 'Enter') && (this.pickerIndex < 0)) || event.shiftKey)) {
                    return false;
                }

                event.preventDefault();
                this.pick(items[Math.max(0, this.pickerIndex)]);
                return true;
            }

            return false;
        },

        pick: function (item) {
            // An emoji in place of the ":word", as one step of the box's
            // undo: one Ctrl+Z brings the ":word" back.
            if (item.emoji && this.rich) {
                var rich = this.rich;
                var word = Math.max(0, this.beforeCaret().length - this.pickerStart);

                rememberEmoji(item.emoji);
                this.closePicker();
                rich.change(function () {
                    rich.replaceTyped(word, { text: item.emoji });
                    return true;
                });
                return;
            }

            // A titled block: what was typed goes, the block comes in its place.
            if (item.block) {
                var self = this;

                if (this.rich) {
                    this.rich.replaceTyped(Math.max(0, this.beforeCaret().length - this.pickerStart), { text: '' });
                } else {
                    var typed = this.input;
                    var at = typed.selectionStart;

                    typed.value = typed.value.slice(0, this.pickerStart) + typed.value.slice(at);
                    typed.selectionStart = typed.selectionEnd = this.pickerStart;
                }

                this.closePicker();

                api('ws_block_pull', { id: item.block }).then(function (data) { self.insertPulled(data); }).catch(fail);
                return;
            }

            // The rich box: what was typed becomes a chip, or the command's text.
            if (this.rich) {
                var count = Math.max(0, this.beforeCaret().length - this.pickerStart);

                this.rich.replaceTyped(count, item.token
                    ? { token: item.token, shown: item.shown, icon: item.avatar ? 'bi-person' : (item.icon || '') }
                    : { text: item.insert || '' });
                this.closePicker();
                this.autosize();

                // In the channel's composer a pick inside a command opens the
                // popup for the argument after it (after a command's name:
                // its first argument).
                if (!this.noCommands) {
                    this.pickerCheck();
                }

                return;
            }

            var input = this.input;
            var caret = input.selectionStart;
            var before = input.value.slice(0, this.pickerStart);
            var after = input.value.slice(caret);
            var inserted = item.insert || (item.shown + ' ');

            input.value = before + inserted + after;
            input.selectionStart = input.selectionEnd = before.length + inserted.length;

            if (item.token) {
                this.labels.push({ label: item.shown, token: item.token });
            }

            this.closePicker();
            this.autosize();
            input.focus();

            if (!this.noCommands) {
                this.pickerCheck();
            }
        },

        closePicker: function () {
            if (this.picker) {
                this.picker.remove();
                this.picker = null;
            }

            this.pickerItems = [];
        },

        // ── Other tabs ──

        loadList: function (action, emptyText) {
            var self = this;
            var pane = self.pane;

            pane.appendChild(skeleton(2, 'ws-skeleton-sm'));

            api(action, { channel_id: self.channel.id }).then(function (data) {
                clear(pane);

                if (!data.messages.length) {
                    var empty = el('div', 'ws-empty');
                    empty.appendChild(icon(action === 'ws_channel_files' ? 'bi-paperclip' : 'bi-patch-check'));
                    empty.appendChild(document.createTextNode(emptyText));
                    pane.appendChild(empty);
                    return;
                }

                data.messages.forEach(function (message) {
                    var node = self.messageNode(message, null);
                    var jump = button('btn btn-sm btn-text theme-secondary', t('show_in_conversation'), 'bi-arrow-right-short');
                    jump.addEventListener('click', function () { self.open(self.channel.id, message.id); });
                    node.querySelector('.ws-msg-meta').appendChild(jump);
                    pane.appendChild(node);
                });
            }).catch(fail);
        },

        // The channel's files: the pictures as a gallery that opens in the
        // viewer, the rest as a list.
        drawFiles: function () {
            var self = this;
            var pane = self.pane;

            pane.appendChild(skeleton(2, 'ws-skeleton-sm'));

            api('ws_channel_files', { channel_id: self.channel.id }).then(function (data) {
                clear(pane);

                var withFile = data.messages.filter(function (message) { return !!message.file; });
                var images = withFile.filter(function (message) { return message.file.image; });
                var videos = withFile.filter(function (message) { return message.file.kind === 'video'; });
                var others = withFile.filter(function (message) { return !message.file.image && message.file.kind !== 'video'; });

                if (!withFile.length) {
                    var empty = el('div', 'ws-empty');
                    empty.appendChild(icon('bi-images'));
                    empty.appendChild(document.createTextNode(t('no_files')));
                    pane.appendChild(empty);
                    return;
                }

                var gallery = images.map(function (message) {
                    return { src: message.file.url, alt: message.file.name, caption: (message.sender ? message.sender.name + ' · ' : '') + message.time + ' · ' + message.file.name };
                });

                var fileMenu = function (message, index) {
                    var items = [{ header: message.file.name }];

                    if (message.file.image) {
                        items.push({ icon: 'bi-arrows-fullscreen', label: t('view_image'), action: function () { viewImages(gallery, index); } });
                    }

                    if (message.file.kind === 'video' || message.file.kind === 'pdf' || message.file.office) {
                        items.push({ icon: 'bi-eye', label: t('preview'), action: function () { previewFile(message.file, function (result) { self.fileEdited(result); }); } });
                    }

                    items.push({ icon: 'bi-box-arrow-up-right', label: t('open_in_new_tab'), action: function () { window.open(message.file.url, '_blank', 'noopener'); } });
                    items.push({ icon: 'bi-download', label: t('download'), action: function () {
                        var get = el('a');
                        get.href = message.file.url;
                        get.setAttribute('download', message.file.name);
                        document.body.appendChild(get);
                        get.click();
                        get.remove();
                    } });
                    items.push({ icon: 'bi-chat-left-text', label: t('show_in_conversation'), action: function () { self.open(self.channel.id, message.id); } });

                    if (message.file.image && self.channel.can_post) {
                        items.push({ icon: 'bi-brush', label: message.mine ? t('file_edit_image') : t('file_edit_send'), action: function () { self.editImage(message); } });
                    }

                    if (message.file.text) {
                        items.push({ icon: 'bi-file-earmark-text', label: t('file_edit_text'), action: function () { self.openText(message); } });
                    }

                    items.push('-');
                    items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () { copyText(absoluteUrl(message.file.url)); } });

                    return items;
                };

                if (images.length) {
                    pane.appendChild(el('div', 'ws-section-title', t('images') + ' · ' + images.length));
                    var grid = el('div', 'ws-gallery');

                    images.forEach(function (message, index) {
                        var tile = el('button', 'ws-gallery-tile');
                        tile.type = 'button';
                        tile.title = message.file.name;

                        var img = el('img');
                        img.src = message.file.url;
                        img.alt = message.file.name;
                        img.loading = 'lazy';
                        tile.appendChild(img);

                        var caption = el('span', 'ws-gallery-caption');
                        caption.appendChild(el('b', '', message.file.name));
                        caption.appendChild(el('span', '', (message.sender ? message.sender.name + ' · ' : '') + message.time));
                        tile.appendChild(caption);

                        tile.addEventListener('click', function () { viewImages(gallery, index); });
                        onContext(tile, function () { return fileMenu(message, index); });
                        grid.appendChild(tile);
                    });

                    pane.appendChild(grid);
                }

                if (videos.length) {
                    pane.appendChild(el('div', 'ws-section-title', t('videos') + ' · ' + videos.length));
                    var reel = el('div', 'ws-gallery');

                    videos.forEach(function (message) {
                        var tile = el('button', 'ws-gallery-tile ws-gallery-video');
                        tile.type = 'button';
                        tile.title = message.file.name;

                        var still = el('video');
                        still.src = message.file.url + '#t=0.5';
                        still.preload = 'metadata';
                        still.muted = true;
                        tile.appendChild(still);
                        tile.appendChild(icon('bi-play-circle-fill', 'ws-gallery-play'));

                        var caption = el('span', 'ws-gallery-caption');
                        caption.appendChild(el('b', '', message.file.name));
                        caption.appendChild(el('span', '', (message.sender ? message.sender.name + ' · ' : '') + message.time));
                        tile.appendChild(caption);

                        tile.addEventListener('click', function () { previewFile(message.file); });
                        onContext(tile, function () { return fileMenu(message, 0); });
                        reel.appendChild(tile);
                    });

                    pane.appendChild(reel);
                }

                if (others.length) {
                    pane.appendChild(el('div', 'ws-section-title', t('documents') + ' · ' + others.length));
                    var list = el('div', 'ws-file-list');

                    others.forEach(function (message) {
                        var row = el('div', 'ws-file-row');
                        var link = el('a', 'ws-file-link');
                        link.href = message.file.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.appendChild(el('span', 'ws-file-ext', message.file.ext));
                        link.appendChild(el('span', 'text-truncate', message.file.name));
                        row.appendChild(link);
                        row.appendChild(el('small', 'fg-secondary ws-grow', (message.sender ? message.sender.name + ' · ' : '') + message.time));

                        if (message.file.kind === 'pdf' || message.file.kind === 'audio' || message.file.office) {
                            var look = button('btn btn-sm btn-text theme-secondary', '', message.file.kind === 'audio' ? 'bi-play-circle' : 'bi-eye', t('preview'));
                            look.addEventListener('click', function () {
                                if (message.file.kind === 'pdf' || message.file.office) {
                                    previewFile(message.file, function (result) { self.fileEdited(result); });
                                } else {
                                    var sound = el('audio');
                                    sound.src = message.file.url;
                                    sound.controls = true;
                                    sound.autoplay = true;
                                    row.appendChild(sound);
                                    look.remove();
                                }
                            });
                            row.appendChild(look);
                        }

                        var jump = button('btn btn-sm btn-text theme-secondary', '', 'bi-chat-left-text', t('show_in_conversation'));
                        jump.addEventListener('click', function () { self.open(self.channel.id, message.id); });
                        row.appendChild(jump);

                        onContext(row, function () { return fileMenu(message, 0); });
                        list.appendChild(row);
                    });

                    pane.appendChild(list);
                }
            }).catch(fail);
        },

        drawTasks: function () {
            var self = this;
            var pane = self.pane;
            var bar = el('div', 'pg-toolbar mb-3');
            var status = select([['open', t('open_tasks')], ['closed', t('closed_tasks')], ['all', t('all_tasks')]], self.taskStatus || 'open');
            status.classList.add('w-auto');
            status.addEventListener('change', function () {
                self.taskStatus = status.value;
                self.drawCenter();
            });
            bar.appendChild(status);

            var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('new_task'), 'bi-plus-lg');
            add.addEventListener('click', function () {
                taskDrawer.open(0, self.taskDefaults(), function () { self.drawCenter(); });
            });
            bar.appendChild(add);
            pane.appendChild(bar);

            var list = el('div');
            pane.appendChild(list);

            api('ws_channel_tasks', { channel_id: self.channel.id, status: self.taskStatus || 'open' }).then(function (data) {
                taskRows(list, data.tasks, function () { self.drawCenter(); });
            }).catch(fail);
        },

        drawSummary: function () {
            var self = this;
            var pane = self.pane;
            var channel = self.channel;
            var view = el('div', 'ws-summary-view');

            if (channel.summary) {
                setHtml(view, channel.summary_html);
            } else {
                var empty = el('div', 'ws-empty');
                empty.appendChild(icon('bi-journal-text'));
                empty.appendChild(document.createTextNode(t('summary_empty')));
                view.appendChild(empty);
            }

            pane.appendChild(view);

            if (channel.summary_updated) {
                pane.appendChild(el('div', 'small fg-secondary mt-3', channel.summary_updated));
            }

            if (channel.can_post) {
                var edit = button('btn btn-sm btn-outline theme-secondary mt-5', t('edit_summary'), 'bi-pencil');
                edit.addEventListener('click', function () {
                    clear(pane);
                    pane.appendChild(el('p', 'small fg-secondary', t('summary_help')));

                    // The formatted box the notes use: @ and # open the
                    // pickers, tags are chips, lists and tables are cards.
                    var wrap = el('div', 'ws-note-compose ws-summary-field');
                    var rich = richField(wrap, { placeholder: t('summary_help'), previews: true });
                    var area = null;

                    if (rich) {
                        rich.caretPicker = true;
                        rich.set(channel.summary || '', channel.summary_labels || {});
                        wrap.appendChild(rich.input);
                        wrap.appendChild(rich.tools(true));
                    } else {
                        area = el('textarea', 'form-control');
                        area.rows = 14;
                        area.value = channel.summary || '';
                        wrap.appendChild(area);
                    }

                    pane.appendChild(wrap);

                    var actions = el('div', 'd-flex gap-3 mt-3');
                    var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('save'), 'bi-check2');
                    save.addEventListener('click', function () {
                        api('ws_channel_summary', { channel_id: channel.id, summary: rich ? rich.value() : area.value }).then(function (data) {
                            self.channel = data.channel;
                            self.drawCenter();
                            toast(t('summary_saved'), 'success');
                        }).catch(fail);
                    });
                    var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
                    cancel.addEventListener('click', function () { self.drawCenter(); });
                    actions.appendChild(save);
                    actions.appendChild(cancel);
                    pane.appendChild(actions);
                    (rich ? rich.input : area).focus();
                });
                pane.appendChild(edit);
            }

            // The customer the channel is about and where they stand, loaded
            // when the tab is opened (workspace_customer.js).
            if (window.PGWsCustomer && channel.customer) {
                pane.appendChild(window.PGWsCustomer.card(channel, partHelpers()));
            }

            // The time spent on the channel's tasks, loaded when the tab is
            // opened (workspace_task_time.js).
            if (window.PGWsTaskTime && CFG.task_time && CFG.task_time.ready) {
                pane.appendChild(window.PGWsTaskTime.channelCard(channel, partHelpers()));
            }
        },

        search: function (query) {
            var self = this;

            if (query.trim().length < 2) {
                return;
            }

            api('ws_search', { q: query }).then(function (data) {
                var node = offcanvas('ws-search', t('search_results'));
                var body = clear(node.querySelector('.drawer-body'));
                node.querySelector('.drawer-footer').classList.add('d-none');

                if (!data.messages.length) {
                    body.appendChild(el('div', 'ws-empty', t('nothing_found')));
                }

                data.messages.forEach(function (message) {
                    var row = el('button', 'ws-record-item w-100 text-start border-0 bg-transparent');
                    row.type = 'button';
                    var head = el('div', 'd-flex gap-3');
                    head.appendChild(el('b', '', '#' + message.channel));
                    head.appendChild(el('span', 'fg-secondary', (message.sender ? message.sender.name + ' · ' : '') + message.time));
                    row.appendChild(head);
                    row.appendChild(el('small', '', self.plain(message.html)));
                    row.addEventListener('click', function () {
                        hideOffcanvas(node);
                        self.open(message.channel_id, message.id);
                    });
                    body.appendChild(row);
                });

                showOffcanvas(node);
            }).catch(fail);
        },

        // ── Staying current ──

        schedule: function () {
            var self = this;

            clearTimeout(self.timer);

            if (document.hidden || !self.channel) {
                return;
            }

            var every = (CFG.poll && CFG.poll.active) ? CFG.poll.active : 4;

            // An answer from Claude is on its way: look twice as often, so it
            // shows up soon after it is written.
            var waiting = (self.tab === 'messages') && (self.messages || []).some(function (message) {
                return message.claude && /^(queued|sent|running)$/.test(message.claude.status);
            });

            // Pinegrap AI works only while somebody carries it on.
            if (waiting && (self.messages || []).some(function (message) {
                return message.claude && message.claude.agent === 'ai' && /^(queued|sent|running)$/.test(message.claude.status);
            })) {
                self.kickAi();
            }

            // So does an MCP agent: a message waits until a screen sends it,
            // and an agent working in the background until a screen asks
            // about its task.
            if (waiting && (self.messages || []).some(function (message) {
                return message.claude && message.claude.agent === 'mcp' && /^(queued|sent|running)$/.test(message.claude.status);
            })) {
                self.kickAgent();
            }

            self.timer = setTimeout(function () {
                self.sync(false, true);
            }, (waiting ? Math.min(2, every) : every) * 1000);
        },

        stop: function () {
            clearTimeout(this.timer);
            this.timer = null;
        },

        // timed: the look the timer takes; any other is asked for by
        // something done on the screen, after which the tabs are counted.
        sync: function (scroll, timed) {
            var self = this;

            if (!timed) {
                self.countsAt = 0;
            }

            // A look is under way: one more follows as soon as it is back
            // (right after sending, the new line must not wait for the timer).
            if (self.syncing) {
                self.syncAgain = true;
                self.syncAgainScroll = self.syncAgainScroll || !!scroll;
                return;
            }

            if (!self.channel) {
                self.schedule();
                return;
            }

            self.syncing = true;

            var asked = Date.now();

            // The marks on the tabs are counted again every half minute, and
            // at once after something here may have changed them.
            var counts = !self.era && ((asked - (self.countsAt || 0)) > 30000);

            if (counts) {
                self.countsAt = asked;
            }

            api('ws_sync', {
                channel_id: self.channel.id,
                since_id: self.lastId,
                since_ts: self.sinceTs,
                era_id: self.era ? self.era.id : 0,
                read: (document.hasFocus() && !self.era) ? 1 : 0,
                counts: (counts && !self.isThread) ? 1 : 0,
                board: ((self.tab === 'board') && !self.isThread) ? 1 : 0,
                panel: self.isThread ? 1 : 0
            }).then(function (data) {
                self.syncing = false;

                if (data.gone) {
                    toast(t('channel_gone'), 'warning');

                    if (self.isThread) {
                        self.close();
                        return;
                    }

                    self.channel = null;
                    self.reloadChannels(0);
                    self.drawEmptyCenter();
                    return;
                }

                var stick = scroll || self.nearEnd();
                var grew = false;
                var arrived = 0;

                if (self.tab === 'messages' && data.messages.length) {
                    var empty = self.pane.querySelector('.ws-empty');

                    if (empty) {
                        empty.remove();
                    }

                    data.messages.forEach(function (message) {
                        if (self.findMessage(message.id)) {
                            return;
                        }

                        var previous = self.lastShown();
                        self.messages.push(message);

                        if (message.deleted) {
                            return;
                        }

                        self.appendMessage(message, previous);
                        playMotion(self.pane.lastElementChild, 'ws-msg-in');
                        grew = true;

                        if (!message.mine && message.kind !== 'system') {
                            arrived++;
                        }
                    });
                }

                if (data.messages.length) {
                    self.lastId = data.messages[data.messages.length - 1].id;
                }

                // Messages already on the screen that changed: a reaction, a
                // tick, a vote, an edit.
                (data.changed || []).forEach(function (message) {
                    var known = self.findMessage(message.id);

                    if (known && ((self.localAt || {})[message.id] || 0) < asked && (JSON.stringify(known) !== JSON.stringify(message))) {
                        self.replaceMessage(message, true);
                        grew = true;
                    }
                });

                (data.tasks || []).forEach(function (task) {
                    self.replaceTaskCard(task.id, task.html);
                    grew = true;
                });

                self.sinceTs = data.now;

                if ((data.scheduled_mine !== undefined) && self.channel && (data.scheduled_mine !== (self.channel.scheduled_mine || 0))) {
                    self.channel.scheduled_mine = data.scheduled_mine;
                    self.drawScheduledBar();
                }

                if (data.tab_counts && self.channel && (JSON.stringify(data.tab_counts) !== JSON.stringify(self.channel.tab_counts || null))) {
                    self.channel.tab_counts = data.tab_counts;
                    self.drawTabMarks();
                }

                // The board open on the tab is read again when one of its
                // cards changed (workspace_board_channel.js).
                if (data.board_stamp && (self.tab === 'board') && window.PGWsChannelBoard) {
                    window.PGWsChannelBoard.stamp(data.board_stamp);
                }

                // The pinned message, as somebody may have changed it.
                if (data.pin !== undefined && self.channel && (JSON.stringify(data.pin) !== JSON.stringify(self.channel.pin || null))) {
                    self.channel.pin = data.pin;
                    self.drawPinBar();
                }

                // Who outside the team reads along, as it changes.
                if ((data.shares !== undefined) && self.channel && !self.channel.guest && (JSON.stringify(data.shares) !== JSON.stringify(self.channel.shares || null))) {
                    var shareWas = self.center.querySelector('.ws-share-bar');

                    self.channel.shares = data.shares;

                    var shareBefore = self.center.querySelector('.ws-era-bar, .ws-tabs');

                    if (shareWas && data.shares) {
                        shareWas.replaceWith(self.shareBar(self.channel));
                    } else if (shareWas) {
                        shareWas.remove();
                    } else if (data.shares && shareBefore) {
                        shareBefore.before(self.shareBar(self.channel));
                    }
                }

                // A guest's link and whether they are here, as they change.
                if (data.guest && self.channel && self.channel.guest && (JSON.stringify(data.guest) !== JSON.stringify(self.channel.guest))) {
                    var guestWas = self.center.querySelector('.ws-guest-bar');

                    self.channel.guest = data.guest;

                    if (guestWas) {
                        guestWas.replaceWith(self.guestBar(self.channel));
                    }
                }

                // A scheduled action's time has come: the screen starts the run.
                if (data.scheduled_due) {
                    scheduledTick();
                }

                // The sidebar is the channel screen's: a discussion beside it
                // leaves it alone.
                if (!self.isThread) {
                    BOOT.channels = data.channels;
                    BOOT.groups = data.groups || [];
                    BOOT.threads = data.threads || [];
                    BOOT.inbox = data.inbox;

                    // The channel list is drawn again only when it changed: every
                    // few seconds from scratch it dropped a half-typed search, the
                    // list's scroll and the pointer's hover.
                    var sideKey = JSON.stringify([data.channels, data.groups || [], data.threads || [], data.inbox, self.channel ? self.channel.id : 0, self.openThreadId()]);

                    if (!self.dragging && (sideKey !== self.sideKey)) {
                        self.sideKey = sideKey;
                        self.drawSide();
                    }
                }

                // Down to the newest line only when there is a new line (or
                // after sending): someone reading a little above it stays put.
                if (stick && self.tab === 'messages' && (scroll || grew)) {
                    self.scrollToEnd();
                } else if (self.tab === 'messages' && arrived) {
                    self.unseenBelow = (self.unseenBelow || 0) + arrived;
                    self.atEnd = false;
                    self.drawJump();
                }

                self.afterSync();
            }).catch(function () {
                self.syncing = false;
                self.afterSync();
            });
        },

        // The next look: at once when one was asked for meanwhile, otherwise
        // on the timer.
        afterSync: function () {
            var self = this;

            if (self.syncAgain) {
                var scroll = self.syncAgainScroll;

                self.syncAgain = false;
                self.syncAgainScroll = false;
                self.sync(scroll);
                return;
            }

            self.schedule();
        }
    };

    // ═══════════════════════════════════════════════════════════════════
    // A discussion beside the channel (includes/workspace/threads.php)
    // ═══════════════════════════════════════════════════════════════════

    // The panel on the right of the channel screen: a discussion's own
    // conversation, drawn and kept current by the channel screen's own
    // methods (it is made from the screen with Object.create, so a message,
    // the writing box, the pickers and the polls work there as they do in
    // the channel). Its state is its own; the sidebar stays the screen's.
    // On a phone it covers the channel until it is closed.
    function makeThreadView(main) {
        var view = Object.create(main);
        var panel = el('aside', 'ws-thread-panel');

        panel.setAttribute('aria-label', t('th_section'));
        main.root.appendChild(panel);

        // Everything a channel screen keeps while it is open, the panel's own.
        var fresh = {
            isThread: true,
            main: main,
            panel: panel,
            center: panel,
            channel: null,
            messages: [],
            lastId: 0,
            sinceTs: 0,
            hasMore: false,
            tab: 'messages',
            reply: null,
            replyMore: null,
            editing: null,
            editingScheduled: null,
            labels: [],
            meter: null,
            pending: null,
            timer: null,
            busy: false,
            era: null,
            view: 'thread',
            briefing: null,
            pinHolder: null,
            pane: null,
            composerWrap: null,
            input: null,
            rich: null,
            sendNode: null,
            voiceMic: null,
            voiceBox: null,
            picker: null,
            pickerMode: null,
            pickerItems: null,
            selecting: null,
            syncing: false,
            syncAgain: false,
            syncAgainScroll: false,
            atEnd: true,
            unseenBelow: 0,
            localAt: {},
            sideKey: null,
            dragging: false,
            countsAt: 0,
            scheduledBar: null,
            previewBox: null,
            replyBar: null,
            tabsNode: null,
            jump: null,
            jumpCount: null,
            flashAfterOpen: null,
            pendingThread: null,
            pendingScheduledEdit: null,
            draftTimer: null,
            draftPending: null
        };

        Object.keys(fresh).forEach(function (key) { view[key] = fresh[key]; });

        // A task made in a discussion is a task of the channel beside it: the
        // card is posted in the channel, and in the discussion as well.
        view.taskDefaults = function () {
            var parentId = this.channel.thread ? (this.channel.thread.channel_id || 0) : 0;
            var parent = (main.channel && main.channel.id === parentId) ? main.channel : null;

            return {
                channel_id: parentId,
                thread_id: this.channel.id,
                department_id: parent && parent.department ? parent.department.id : (this.channel.department ? this.channel.department.id : 0)
            };
        };

        // The panel's width: dragged on its left edge, kept on this browser.
        // The channel beside it never gets narrower than CENTER_MIN; on a
        // phone the panel covers the channel and is not resized.
        var THREAD_MIN = 320;
        var CENTER_MIN = 420;
        var WIDTH_KEY = 'ws-thread-w';
        var phone = window.matchMedia ? window.matchMedia('(max-width: 767.98px)') : null;
        var resizer = el('div', 'ws-thread-resizer');
        var resizing = false;
        var dragRight = 0;
        var dragWidth = 0;

        function clampWidth(px) {
            var side = main.side.getBoundingClientRect().width;
            var max = Math.max(THREAD_MIN, main.root.clientWidth - side - CENTER_MIN);

            return Math.min(max, Math.max(THREAD_MIN, Math.round(px)));
        }

        // The width given, as far as it fits; 0 when nothing was set.
        function applyWidth(px) {
            if (phone && phone.matches) {
                return 0;
            }

            var width = clampWidth(px);

            main.root.style.setProperty('--ws-thread-w', width + 'px');

            return width;
        }

        function storedWidth() {
            try {
                return parseInt(window.localStorage.getItem(WIDTH_KEY) || '0', 10) || 0;
            } catch (error) {
                return 0;
            }
        }

        function storeWidth(px) {
            try {
                if (px > 0) {
                    window.localStorage.setItem(WIDTH_KEY, String(px));
                } else {
                    window.localStorage.removeItem(WIDTH_KEY);
                }
            } catch (error) {
                // Not kept: the panel opens at its usual width next time.
            }
        }

        function resetWidth() {
            main.root.style.removeProperty('--ws-thread-w');
            storeWidth(0);
        }

        function endResize() {
            if (!resizing) {
                return;
            }

            resizing = false;
            main.root.classList.remove('ws-thread-resizing');

            if (dragWidth) {
                storeWidth(dragWidth);
            }
        }

        resizer.setAttribute('role', 'separator');
        resizer.setAttribute('aria-orientation', 'vertical');
        resizer.setAttribute('tabindex', '0');
        resizer.title = t('th_resize');
        resizer.setAttribute('aria-label', resizer.title);

        resizer.addEventListener('pointerdown', function (event) {
            if ((event.button !== 0) || (phone && phone.matches)) {
                return;
            }

            event.preventDefault();
            resizing = true;
            dragRight = panel.getBoundingClientRect().right;
            dragWidth = 0;
            resizer.setPointerCapture(event.pointerId);
            main.root.classList.add('ws-thread-resizing');
        });

        resizer.addEventListener('pointermove', function (event) {
            if (resizing) {
                dragWidth = applyWidth(dragRight - event.clientX);
            }
        });

        resizer.addEventListener('pointerup', endResize);
        resizer.addEventListener('pointercancel', endResize);
        resizer.addEventListener('dblclick', resetWidth);

        // Left widens, right narrows; Shift takes bigger steps, Home goes
        // back to the usual width.
        resizer.addEventListener('keydown', function (event) {
            if ((event.key === 'ArrowLeft') || (event.key === 'ArrowRight')) {
                var step = (event.shiftKey ? 64 : 16) * ((event.key === 'ArrowLeft') ? 1 : -1);
                var width = applyWidth(panel.getBoundingClientRect().width + step);

                event.preventDefault();

                if (width) {
                    storeWidth(width);
                }
            } else if (event.key === 'Home') {
                event.preventDefault();
                resetWidth();
            }
        });

        // A narrower window takes the kept width down as far as it must; a
        // wider one gives it back.
        window.addEventListener('resize', function () {
            var wanted = storedWidth();

            if (wanted && main.root.classList.contains('ws-thread-open')) {
                applyWidth(wanted);
            }
        });

        view.load = function (threadId, messageId) {
            var self = this;

            self.stop();
            self.reply = null;
            self.replyMore = null;
            self.editing = null;
            self.editingScheduled = null;
            self.selecting = null;
            self.labels = [];

            api('ws_channel_open', { channel_id: threadId, message_id: messageId || 0, as_thread: 1 }).then(function (data) {
                self.channel = data.channel;
                self.messages = data.messages;
                self.lastId = data.last_id;
                self.sinceTs = data.now;
                self.hasMore = data.has_more;
                main.root.classList.add('ws-thread-open');

                if (storedWidth()) {
                    applyWidth(storedWidth());
                }

                self.drawCenter();

                var target = messageId ? panel.querySelector('[data-ws-message="' + messageId + '"]') : null;

                if (target) {
                    target.classList.add('ws-msg-highlight');
                    target.scrollIntoView({ block: 'center' });
                } else {
                    self.scrollToEnd();
                }

                // What it was about has been read; the sidebar says so.
                (BOOT.threads || []).forEach(function (item) {
                    if (item.id === threadId) {
                        item.unread = 0;
                    }
                });

                main.drawSide();
                main.syncAddress();
                self.schedule();
            }).catch(function (error) {
                fail(error);
                self.close();
            });
        };

        view.close = function () {
            this.stop();
            this.channel = null;
            this.messages = [];
            endResize();
            clear(panel);
            main.root.classList.remove('ws-thread-open');
            main.root.style.removeProperty('--ws-thread-w');
            main.drawSide();
            main.syncAddress();
        };

        // Another channel or another discussion asked for from inside the
        // panel goes to the channel screen.
        view.open = function (channelId, messageId) {
            if (this.channel && (channelId === this.channel.id)) {
                this.load(channelId, messageId);
                return;
            }

            main.open(channelId, messageId);
        };

        view.reloadChannels = function () {
            var self = this;

            main.reloadChannels(0);

            if (self.channel) {
                self.load(self.channel.id, 0);
            }
        };

        view.drawCenter = function () {
            var self = this;
            var channel = self.channel;
            var thread = channel.thread || {};

            // Drawn again with the panel; a drag going on ends here.
            endResize();

            var center = clear(panel);

            center.appendChild(resizer);

            // Head: what it is about, who is in it, its tools.
            var head = el('div', 'ws-head ws-thread-head');
            var title = el('div', 'ws-head-title');
            var h = el('h2');
            h.appendChild(icon('bi-chat-square-dots', 'me-1 fg-secondary'));
            h.appendChild(document.createTextNode(channel.name));
            title.appendChild(h);

            var where = el('div', 'ws-topic');
            var parent = (BOOT.channels || []).filter(function (item) { return item.id === thread.channel_id; })[0];
            where.textContent = t('th_in', parent ? parent.name : '');
            title.appendChild(where);
            head.appendChild(title);

            var actions = el('div', 'ws-head-actions');
            var faces = button('btn btn-sm btn-text theme-secondary d-inline-flex align-items-center gap-1', '', '', t('th_people'));
            faces.appendChild(avatarStack('', channel.members, 4));
            faces.appendChild(el('span', 'small', (channel.members || []).length));
            faces.addEventListener('click', function () { self.showPeople(); });
            actions.appendChild(faces);
            actions.appendChild(self.threadMenu());

            var shut = button('btn btn-sm btn-text theme-secondary', '', 'bi-x-lg', t('th_close_panel'));
            shut.addEventListener('click', function () { self.close(); });
            actions.appendChild(shut);
            head.appendChild(actions);
            center.appendChild(head);

            if (thread.closed) {
                var closed = el('div', 'alert theme-secondary rounded-0 m-0 py-3 small');
                closed.appendChild(icon('bi-archive', 'me-1'));
                closed.appendChild(document.createTextNode(thread.closed_text));
                center.appendChild(closed);
            }

            // The message it talks over, as the channel shows it.
            var about = main.findMessage(thread.message_id);
            var source = el('div', 'ws-thread-source');
            var sourceHead = el('div', 'ws-thread-source-head');

            sourceHead.appendChild(el('span', '', t('th_about')));

            var go = button('btn btn-sm btn-link p-0', t('th_go_message'), 'bi-arrow-left-short');
            go.addEventListener('click', function () {
                if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
                    self.close();
                }

                main.jumpTo(thread.message_id, true);
            });
            sourceHead.appendChild(go);
            source.appendChild(sourceHead);

            if (about && !about.deleted) {
                var quote = el('div', 'ws-thread-source-body');
                quote.appendChild(el('b', '', about.sender ? about.sender.name : ''));
                quote.appendChild(el('span', 'ws-thread-source-time', about.time));

                var text = el('div', 'ws-msg-body');
                setHtml(text, about.html);
                quote.appendChild(text);
                source.appendChild(quote);
            } else {
                source.appendChild(el('div', 'small fg-secondary', channel.name));
            }

            center.appendChild(source);

            var pane = el('div', 'ws-pane ws-pane-messages ws-thread-pane');
            center.appendChild(pane);
            self.pane = pane;
            self.atEnd = true;
            self.unseenBelow = 0;
            pane.addEventListener('scroll', function () {
                self.atEnd = self.nearEnd();
                self.drawJump();
            }, { passive: true });

            self.drawMessages();

            if (!self.messages.filter(function (message) { return message.kind !== 'system'; }).length) {
                pane.appendChild(el('div', 'ws-empty small', t('th_empty')));
            }

            if (channel.can_post) {
                center.appendChild(self.composer());
                self.composerWrap.appendChild(self.jumpButton());
            } else {
                var readonly = el('div', 'ws-composer-wrap');
                readonly.appendChild(el('div', 'small fg-secondary text-center py-3', thread.closed ? t('th_readonly') : t('cannot_post')));
                center.appendChild(readonly);
            }
        };

        view.threadMenu = function () {
            var self = this;
            var channel = self.channel;
            var thread = channel.thread || {};
            var more = button('btn btn-sm btn-text theme-secondary', '', 'bi-three-dots-vertical', t('more'));

            more.addEventListener('click', function (event) {
                var place = more.getBoundingClientRect();

                event.stopPropagation();
                ctxMenu(Math.max(8, place.right - 240), place.bottom + 4, [
                    (channel.can_post && !thread.closed) ? { icon: 'bi-pencil', label: t('th_rename'), action: function () { self.rename(); } } : null,
                    (channel.can_post && !thread.closed) ? { icon: 'bi-person-plus', label: t('th_add_people'), action: function () { self.showPeople(); } } : null,
                    '-',
                    (thread.mine && !thread.closed) ? { icon: 'bi-check2-circle', label: t('th_conclude'), action: function () { self.conclude(); } } : null,
                    thread.mine ? { icon: 'bi-box-arrow-right', label: t('th_leave'), danger: true, action: function () { self.leave(); } } : null
                ]);
            });

            return more;
        };

        view.rename = function () {
            var self = this;
            var field = el('input', 'form-control');

            field.maxLength = 80;
            field.value = self.channel.name;

            ask(t('th_rename_title'), t('save'), false, field).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_thread_rename', { thread_id: self.channel.id, title: field.value }).then(function () {
                    self.load(self.channel.id, 0);
                    main.sync();
                }).catch(fail);
            });
        };

        view.conclude = function () {
            var self = this;

            ask(t('th_conclude_confirm'), t('th_conclude'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_thread_close', { thread_id: self.channel.id }).then(function () {
                    toast(t('th_concluded'), 'success');
                    self.load(self.channel.id, 0);
                    main.sync();
                }).catch(fail);
            });
        };

        view.leave = function () {
            var self = this;

            ask(t('th_leave_confirm'), t('th_leave'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_thread_leave', { thread_id: self.channel.id }).then(function () {
                    self.close();
                    main.sync();
                }).catch(fail);
            });
        };

        // The people in it, and the people of the channel who could be
        // brought in.
        view.showPeople = function () {
            var self = this;
            var channel = self.channel;
            var node = offcanvas('ws-thread-people', t('th_people'));
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));

            footer.classList.add('d-none');

            (channel.members || []).forEach(function (member) {
                var row = el('div', 'd-flex align-items-center gap-3 py-3 border-bottom');
                var face = avatar(member, '2rem');
                row.appendChild(face);
                row.appendChild(el('div', 'flex-grow-1 fw-semibold', member.name));
                body.appendChild(row);
            });

            var parent = main.channel;

            if (channel.can_post && parent && (parent.id === (channel.thread || {}).channel_id)) {
                var inside = (channel.members || []).map(function (member) { return member.id; });
                var readers = (parent.kind === 'public') ? BOOT.people : (parent.members || []);
                var outside = readers.filter(function (member) { return inside.indexOf(member.id) === -1; }).map(function (member) { return member.id; });

                if (outside.length) {
                    body.appendChild(el('div', 'form-label mt-5', t('th_add_people')));
                    body.appendChild(el('div', 'form-text mt-0 mb-3', t('th_add_people_help')));

                    var picker = peoplePicker([], outside);
                    body.appendChild(picker);
                    footer.classList.remove('d-none');

                    var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('th_add_people'), 'bi-person-plus');
                    add.addEventListener('click', function () {
                        var ids = picker.value();

                        if (!ids.length) {
                            return;
                        }

                        api('ws_channel_members_add', { channel_id: channel.id, user_ids: ids }).then(function () {
                            hideOffcanvas(node);
                            self.load(channel.id, 0);
                        }).catch(fail);
                    });
                    footer.appendChild(add);
                }
            }

            showOffcanvas(node);
        };

        return view;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Scheduled actions (includes/workspace/scheduled.php): staff only
    // ═══════════════════════════════════════════════════════════════════

    // A search box for one record of one kind (#-tags' search): the id and
    // the name it is shown with.
    function saRecordPicker(tag, id, label) {
        var box = el('div', 'ws-sa-picker');
        var input = el('input', 'form-control form-control-sm');
        var list = el('div', 'ws-sa-picker-list');
        var chosen = { id: id || 0, label: label || '' };

        input.type = 'search';
        input.placeholder = t('sa_record_search');
        input.value = chosen.label;
        list.hidden = true;
        box.appendChild(input);
        box.appendChild(list);

        var search = debounce(function () {
            var query = input.value.trim();

            api('ws_ref_search', { type: box.tag, q: query }).then(function (data) {
                clear(list);
                list.hidden = !(data.items || []).length;

                (data.items || []).forEach(function (item) {
                    var row = button('ws-sa-picker-item', item.label, item.icon || 'bi-dot');

                    if (item.meta) {
                        row.appendChild(el('small', 'fg-secondary ms-auto', item.meta));
                    }

                    row.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        chosen = { id: item.id, label: item.label };
                        input.value = item.label;
                        list.hidden = true;
                        box.dispatchEvent(new Event('change'));
                    });
                    list.appendChild(row);
                });
            }).catch(function () {});
        }, 250);

        box.tag = tag;
        input.addEventListener('input', function () {
            chosen = { id: 0, label: '' };
            search();
        });
        input.addEventListener('focus', search);
        input.addEventListener('blur', function () {
            setTimeout(function () { list.hidden = true; }, 150);
        });

        box.value = function () { return chosen.id; };
        box.setTag = function (next) {
            if (next !== box.tag) {
                box.tag = next;
                chosen = { id: 0, label: '' };
                input.value = '';
            }
        };

        return box;
    }

    // A value of a field, by the kind of value the field holds.
    function saValueInput(meta, value) {
        var input;
        var limit = meta && meta.limit ? meta.limit : null;

        meta = meta || { kind: 'text', values: [] };

        if (meta.kind === 'enum') {
            var labels = (meta.values || []).map(function (item) {
                return [item, (meta.labels && meta.labels[item]) ? meta.labels[item] : item];
            });
            input = select(labels, value || (meta.values || [''])[0]);
        } else if (meta.kind === 'bool') {
            input = select([['1', t('sa_yes')], ['0', t('sa_no')]], (value === true || value === 1 || value === '1') ? '1' : '0');
        } else if (meta.kind === 'lines') {
            // One line each; the server also takes them separated by commas.
            input = el('textarea', 'form-control form-control-sm');
            input.rows = 3;
            input.placeholder = t('qe_lines_help');
            input.value = Array.isArray(value) ? value.join('\n') : (value || '');
        } else if ((meta.kind === 'long' && !(limit && limit.max_length && limit.max_length <= 255))
            || ((meta.kind === 'long' || meta.kind === 'text')
                && /[\r\n]/.test(value === undefined || value === null ? '' : String(value)))) {
            // A text field, or a long field of 255 characters or less, is one line,
            // unless its value already holds a line break (a panel textarea stores
            // one in a meta description): a one-line input drops it, and saving any
            // other field of the record would send the value back without it.
            input = el('textarea', 'form-control form-control-sm');
            input.rows = 3;
            input.value = value || '';
        } else {
            input = el('input', 'form-control form-control-sm');
            input.type = (meta.kind === 'date') ? 'date'
                : ((meta.kind === 'datetime') ? 'datetime-local'
                : ((meta.kind === 'int' || meta.kind === 'money' || meta.kind === 'decimal') ? 'number'
                : ((meta.kind === 'email') ? 'email' : 'text')));

            if (meta.kind === 'money') {
                input.step = '0.01';
                input.placeholder = t('sa_money_help');
                input.value = (value !== undefined && value !== null && value !== '') ? (parseInt(value, 10) / 100).toFixed(2) : '';
            } else if (meta.kind === 'decimal') {
                input.step = (limit && limit.decimals) ? String(Math.pow(10, -limit.decimals)) : 'any';
                input.placeholder = t('qe_decimal_help');
                input.value = (value === undefined || value === null) ? '' : String(value);
            } else if (meta.kind === 'datetime') {
                // "YYYY-MM-DD HH:MM:SS" as the record keeps it, "YYYY-MM-DDTHH:MM" as the field takes it.
                input.value = value ? String(value).replace(' ', 'T').slice(0, 16) : '';
            } else if (meta.kind === 'date') {
                // The zero date is no date; the input would refuse it anyway.
                input.value = (value === undefined || value === null || String(value).indexOf('0000-00-00') === 0) ? '' : String(value);
            } else {
                input.value = (value === undefined || value === null) ? '' : String(value);
            }

            if (meta.kind === 'keywords') {
                input.placeholder = t('qe_keywords_help');
            }
        }

        // The limits the catalogue sets, so the browser says no before the server does.
        if (limit) {
            if (limit.max_length && input.tagName !== 'SELECT') {
                input.maxLength = limit.max_length;
            }

            if (meta.kind === 'int' || meta.kind === 'decimal') {
                if (limit.min !== undefined && limit.min !== null) { input.min = String(limit.min); }
                if (limit.max !== undefined && limit.max !== null) { input.max = String(limit.max); }
            }

            if (meta.kind === 'money' && limit.max) {
                input.max = (limit.max / 100).toFixed(2);
            }
        }

        input.classList.add('ws-sa-value');
        input.read = function () {
            if (meta.kind === 'money') {
                return input.value === '' ? '' : Math.round(parseFloat(String(input.value).replace(',', '.')) * 100);
            }

            if (meta.kind === 'bool') {
                return input.value === '1';
            }

            if (meta.kind === 'int') {
                return input.value === '' ? '' : parseInt(input.value, 10);
            }

            if (meta.kind === 'decimal') {
                return String(input.value).replace(',', '.');
            }

            return input.value;
        };

        return input;
    }

    function saLabelled(label, node, help) {
        var wrap = el('div', 'ws-sa-field');
        wrap.appendChild(el('label', 'form-label small mb-1', label));
        wrap.appendChild(node);

        if (help) {
            wrap.appendChild(el('div', 'form-text mt-0', help));
        }

        return wrap;
    }

    // Days of the week as toggles, Monday first.
    function saDayPicker(days) {
        var box = el('div', 'ws-sa-days');
        var names = (CFG.recurrence && CFG.recurrence.weekdays) || {};
        var chosen = {};

        (days || []).forEach(function (day) { chosen[day] = true; });

        [1, 2, 3, 4, 5, 6, 7].forEach(function (day) {
            var toggle = button('btn btn-sm ' + (chosen[day] ? 'btn-solid theme-secondary' : 'btn-outline theme-secondary'), names[day] || String(day));
            toggle.setAttribute('aria-pressed', chosen[day] ? 'true' : 'false');
            toggle.addEventListener('click', function () {
                chosen[day] = !chosen[day];
                toggle.className = 'btn btn-sm ' + (chosen[day] ? 'btn-solid theme-secondary' : 'btn-outline theme-secondary');
                toggle.setAttribute('aria-pressed', chosen[day] ? 'true' : 'false');
            });
            box.appendChild(toggle);
        });

        box.value = function () {
            return [1, 2, 3, 4, 5, 6, 7].filter(function (day) { return chosen[day]; });
        };

        return box;
    }

    // A channel to write in; "none" first when it may be left out.
    function saChannelSelect(value, noneLabel) {
        var channels = (BOOT.channels || []).filter(function (item) { return !item.archived; }).map(function (item) {
            return [item.id, (item.kind === 'private' ? '🔒 ' : '#') + item.name];
        });

        if (noneLabel) {
            channels.unshift([0, noneLabel]);
        }

        var fallback = noneLabel ? 0 : ((app.channel && app.channel.id) || (channels[0] || [0])[0]);

        return select(channels, (value !== undefined && value !== null && value !== '') ? value : fallback);
    }

    function saNumber(value, min, max, placeholder) {
        var input = el('input', 'form-control form-control-sm');

        input.type = 'number';
        input.min = String(min);

        if (max !== undefined && max !== null) {
            input.max = String(max);
        }

        input.value = (value === undefined || value === null) ? '' : String(value);

        if (placeholder) {
            input.placeholder = placeholder;
        }

        return input;
    }

    function saText(value, placeholder, maxLength) {
        var input = el('input', 'form-control form-control-sm');

        input.type = 'text';
        input.value = value || '';

        if (placeholder) {
            input.placeholder = placeholder;
        }

        if (maxLength) {
            input.maxLength = maxLength;
        }

        return input;
    }

    function saArea(value, rows, placeholder) {
        var area = el('textarea', 'form-control form-control-sm');

        area.rows = rows || 3;
        area.value = value || '';

        if (placeholder) {
            area.placeholder = placeholder;
        }

        return area;
    }

    // A count, and what it is counted for: one channel's tasks, or a limit.
    function saMetricPicker(key, param) {
        var metrics = (CFG.scheduled || {}).metrics || {};
        var keys = Object.keys(metrics);
        var wrap = el('div', 'ws-sa-metric');
        var which = select(keys.map(function (item) { return [item, metrics[item].label]; }), key || keys[0]);
        var scopeBox = el('div');
        var scope = null;

        function draw() {
            var info = metrics[which.value] || {};

            clear(scopeBox);
            scope = null;

            if (info.scope === 'channel') {
                scope = saChannelSelect((which.value === key) ? (param || 0) : 0, t('sa_metric_all'));
                scopeBox.appendChild(saLabelled(t('sa_metric_channel'), scope));
            } else if (info.scope === 'threshold') {
                scope = saNumber((which.value === key && param !== undefined) ? param : 5, 0, 1000000);
                scopeBox.appendChild(saLabelled(t('sa_metric_threshold'), scope));
            }
        }

        which.addEventListener('change', function () {
            draw();
            wrap.dispatchEvent(new Event('change'));
        });

        wrap.appendChild(which);
        wrap.appendChild(scopeBox);
        draw();

        wrap.value = function () {
            return { key: which.value, param: scope ? (parseInt(scope.value, 10) || 0) : 0 };
        };

        wrap.kind = function () {
            return (metrics[which.value] || {}).kind || 'count';
        };

        return wrap;
    }

    // One condition: a record's value, a count, a working day, some days of
    // the week, some hours of the day.
    function saConditionRow(rule, onRemove) {
        var row = el('div', 'ws-sa-condition');
        var config = CFG.scheduled || {};
        var removeButton = button('btn btn-sm btn-text theme-secondary ws-sa-remove', '', 'bi-x-lg', t('delete'));

        removeButton.addEventListener('click', function () {
            row.remove();

            if (onRemove) {
                onRemove();
            }
        });

        if (rule.type === 'workday') {
            row.appendChild(icon('bi-briefcase', 'me-1'));
            row.appendChild(el('span', 'flex-grow-1', t('sa_rule_workday')));
            row.appendChild(removeButton);
            row.read = function () { return { type: 'workday' }; };

            return row;
        }

        if (rule.type === 'weekdays') {
            var days = saDayPicker(rule.days || [1, 2, 3, 4, 5]);
            var dayGrid = el('div', 'flex-grow-1');

            dayGrid.appendChild(saLabelled(t('sa_rule_weekdays'), days));
            row.appendChild(icon('bi-calendar-week', 'me-1 mt-7'));
            row.appendChild(dayGrid);
            row.appendChild(removeButton);
            row.read = function () { return { type: 'weekdays', days: days.value() }; };

            return row;
        }

        if (rule.type === 'hours') {
            var from = el('input', 'form-control form-control-sm');
            var to = el('input', 'form-control form-control-sm');
            var hourGrid = el('div', 'ws-sa-condition-grid');

            from.type = 'time';
            to.type = 'time';
            from.value = rule.from || '09:00';
            to.value = rule.to || '18:00';
            hourGrid.appendChild(saLabelled(t('sa_from'), from));
            hourGrid.appendChild(saLabelled(t('sa_to_time'), to));
            row.appendChild(icon('bi-clock', 'me-1 mt-7'));
            row.appendChild(hourGrid);
            row.appendChild(removeButton);
            row.read = function () { return { type: 'hours', from: from.value, to: to.value }; };

            return row;
        }

        if (rule.type === 'metric') {
            var picker = saMetricPicker(rule.metric, rule.param);
            var ops = select((config.counts || ['gt']).map(function (key) { return [key, (config.operators || {})[key] || key]; }), rule.op || 'gt');
            var money = function () { return picker.kind() === 'money'; };
            var amount = saNumber('', 0, null);
            var metricGrid = el('div', 'ws-sa-condition-grid');

            amount.step = 'any';

            if (rule.value !== undefined) {
                amount.value = ((config.metrics || {})[rule.metric] || {}).kind === 'money' ? (rule.value / 100).toFixed(2) : String(rule.value);
            } else {
                amount.value = '0';
            }

            metricGrid.appendChild(saLabelled(t('sa_metric'), picker));
            metricGrid.appendChild(saLabelled(t('sa_operator'), ops));
            metricGrid.appendChild(saLabelled(t('sa_value'), amount, money() ? t('sa_money_help') : ''));
            row.appendChild(icon('bi-123', 'me-1 mt-7'));
            row.appendChild(metricGrid);
            row.appendChild(removeButton);

            row.read = function () {
                var pick = picker.value();

                return { type: 'metric', metric: pick.key, param: pick.param, op: ops.value, value: String(amount.value).replace(',', '.') };
            };

            return row;
        }

        var kinds = Object.keys(config.records || {}).map(function (key) { return [key, config.records[key].label]; });
        var kind = select(kinds, rule.record || (kinds[0] || [''])[0]);
        var recordPicker = saRecordPicker('', rule.id || 0, rule.label || '');
        var fieldBox = el('div');
        var operator = select(Object.keys(config.operators || {}).map(function (key) { return [key, config.operators[key]]; }), rule.op || 'eq');
        var valueBox = el('div');
        var value = null;
        var fieldSelect = null;

        function fields() {
            var info = (config.records || {})[kind.value] || { fields: {} };

            return Object.keys(info.fields).filter(function (name) { return info.fields[name].column; }).map(function (name) {
                return [name, info.fields[name].label];
            });
        }

        function drawValue() {
            var info = (config.records || {})[kind.value] || { fields: {} };
            var meta = info.fields[fieldSelect.value];
            var same = (rule.field === fieldSelect.value) && (rule.op === operator.value);

            clear(valueBox);

            // "Within the next days" takes a number of days.
            if (operator.value === 'within') {
                value = saNumber(same ? rule.value : 7, 0, 3650);
                value.read = function () { return value.value; };
                valueBox.appendChild(value);
                valueBox.appendChild(el('div', 'form-text mt-0', t('sa_days_value')));
            } else {
                value = saValueInput(meta, (rule.field === fieldSelect.value) ? rule.value : '');
                valueBox.appendChild(value);
            }

            valueBox.hidden = (['empty', 'not_empty', 'overdue'].indexOf(operator.value) !== -1);
        }

        function drawFields() {
            var info = (config.records || {})[kind.value];

            recordPicker.setTag(info ? info.tag : '');
            clear(fieldBox);
            fieldSelect = select(fields(), rule.field || '');
            fieldSelect.addEventListener('change', drawValue);
            fieldBox.appendChild(fieldSelect);
            drawValue();
        }

        kind.addEventListener('change', drawFields);
        operator.addEventListener('change', drawValue);

        var grid = el('div', 'ws-sa-condition-grid');
        grid.appendChild(saLabelled(t('sa_record_kind'), kind));
        grid.appendChild(saLabelled(t('sa_record'), recordPicker));
        grid.appendChild(saLabelled(t('sa_field'), fieldBox));
        grid.appendChild(saLabelled(t('sa_operator'), operator));
        grid.appendChild(saLabelled(t('sa_value'), valueBox));

        row.appendChild(grid);
        row.appendChild(removeButton);
        recordPicker.tag = ((config.records || {})[kind.value] || {}).tag || '';
        drawFields();

        row.read = function () {
            return {
                type: 'record',
                record: kind.value,
                id: recordPicker.value(),
                field: fieldSelect ? fieldSelect.value : '',
                op: operator.value,
                value: (value && !valueBox.hidden) ? value.read() : ''
            };
        };

        return row;
    }

    // The kinds of action, grouped as the form lists them.
    function saActionTypes(allowNone) {
        var config = CFG.scheduled || {};
        var chains = !!config.chains;
        var groups = [
            [t('sa_group_team'), [['post', t('sa_do_post')], ['email', t('sa_do_email')]].concat(chains ? [['notify', t('sa_do_notify')], ['task', t('sa_do_task')], ['task_digest', t('sa_do_task_digest')]] : [])
                .concat((config.approvals && window.PGWsApprovals) ? [['approval', t('sa_do_approval')]] : [])
                .concat((CFG.status && window.PGWsStatus) ? [['checkin', t('sa_do_checkin')]] : [])],
            [t('sa_group_records'), Object.keys(config.changes || {}).length ? [['change', t('sa_do_change')]] : []],
            [t('sa_group_web'), chains ? [['report', t('sa_do_report')], ['web_check', t('sa_do_web_check')], ['webhook', t('sa_do_webhook')]] : []],
            [t('sa_group_chain'), chains ? [['trigger', t('sa_do_trigger')]] : []]
        ];
        var node = el('select', 'form-control form-control-sm');

        if (allowNone) {
            var none = el('option', '', t('sa_do_nothing'));
            none.value = 'none';
            node.appendChild(none);
        }

        groups.forEach(function (group) {
            if (!group[1].length) {
                return;
            }

            var box = el('optgroup');
            box.label = group[0];

            group[1].forEach(function (item) {
                var option = el('option', '', item[1]);
                option.value = item[0];
                box.appendChild(option);
            });

            node.appendChild(box);
        });

        return node;
    }

    // What is done: in the team, to a record, on the web, or the next step of
    // a chain. selfId: the action being changed, so it can start itself again.
    // state: what the form says around it (join: started by somebody joining
    // a channel, so "the channel they joined" can be written in).
    function saActionEditor(action, allowNone, selfId, state) {
        var config = CFG.scheduled || {};
        var box = el('div', 'ws-sa-action');

        action = action || null;
        state = state || {};

        var type = saActionTypes(allowNone);
        var detail = el('div', 'ws-sa-action-detail');
        var reader = function () { return null; };

        type.value = action ? action.type : (allowNone ? 'none' : 'post');

        if (!type.value) {
            type.value = 'post';
        }

        box.appendChild(type);
        box.appendChild(detail);

        function given(kind, fallback) {
            return (action && action.type === kind) ? action : fallback;
        }

        function post() {
            var data = given('post', { channel_id: state.join ? -1 : null, body: '' });
            var channel = saChannelSelect(data.channel_id, '');

            // Started by a join: the channel the newcomer came into.
            if (state.join) {
                var joined = el('option', '', t('sa_joined_channel'));

                joined.value = '-1';
                channel.insertBefore(joined, channel.firstChild);
                channel.value = String((data.channel_id === null || data.channel_id === undefined) ? -1 : data.channel_id);
            } else if (String(channel.value) === '-1') {
                channel.selectedIndex = 0;
            }
            var wrap = el('div', 'ws-note-compose ws-sa-body');
            var rich = richField(wrap, { placeholder: t('sa_message'), previews: true });
            var area = null;

            detail.appendChild(saLabelled(t('sa_channel'), channel));

            if (rich) {
                rich.caretPicker = true;
                rich.set(data.body || '', {});
                wrap.appendChild(rich.input);
                wrap.appendChild(rich.tools(true));
            } else {
                area = saArea(data.body, 4);
                wrap.appendChild(area);
            }

            detail.appendChild(saLabelled(t('sa_message'), wrap));

            reader = function () {
                return { type: 'post', channel_id: parseInt(channel.value, 10) || 0, body: rich ? rich.value() : area.value };
            };
        }

        function email() {
            var data = given('email', { to: [], subject: '', body: '', page_id: 0 });
            var to = saText((data.to || []).join(', '));
            var subject = saText(data.subject, '', 250);
            var body = saArea(data.body, 5);
            var modes = el('div', 'btn-group btn-group-sm ws-sa-modes');
            var mode = data.page_id ? 'page' : 'text';
            var textBox = el('div');
            var pageBox = el('div');
            var page = saRecordPicker('page', data.page_id || 0, data.page_label || (data.page_id ? '#' + data.page_id : ''));

            [['text', t('sa_email_text')], ['page', t('sa_email_page')]].forEach(function (item) {
                var toggle = button('btn btn-outline theme-secondary' + (mode === item[0] ? ' active' : ''), item[1]);
                toggle.addEventListener('click', function () {
                    mode = item[0];
                    Array.prototype.forEach.call(modes.children, function (node) { node.classList.toggle('active', node === toggle); });
                    textBox.hidden = (mode !== 'text');
                    pageBox.hidden = (mode !== 'page');
                });
                modes.appendChild(toggle);
            });

            detail.appendChild(saLabelled(t('sa_to'), to, t('sa_to_help')));
            detail.appendChild(saLabelled(t('sa_subject'), subject));
            detail.appendChild(modes);
            textBox.appendChild(saLabelled(t('sa_email_text'), body));
            pageBox.appendChild(saLabelled(t('sa_page'), page, t('sa_page_help')));
            textBox.hidden = (mode !== 'text');
            pageBox.hidden = (mode !== 'page');
            detail.appendChild(textBox);
            detail.appendChild(pageBox);

            reader = function () {
                return {
                    type: 'email',
                    to: to.value,
                    subject: subject.value,
                    body: (mode === 'text') ? body.value : '',
                    page_id: (mode === 'page') ? page.value() : 0
                };
            };
        }

        function notify() {
            var data = given('notify', { user_ids: [BOOT.me.id], text: '' });
            var people = peoplePicker(data.user_ids || []);
            var text = saArea(data.text, 2);

            text.maxLength = 500;
            detail.appendChild(saLabelled(t('sa_people'), people));
            detail.appendChild(saLabelled(t('sa_notice'), text));

            reader = function () {
                return { type: 'notify', user_ids: people.value(), text: text.value };
            };
        }

        function task() {
            var data = given('task', { title: '', description: '', priority: 'normal', assignees: [BOOT.me.id], channel_id: 0, due_in: '' });
            var title = saText(data.title, '', 200);
            var description = saArea(data.description, 3);
            var people = peoplePicker(data.assignees || []);
            var priority = select(Object.keys(BOOT.priorities || {}).map(function (key) { return [key, BOOT.priorities[key]]; }), data.priority || 'normal');
            var due = saNumber(data.due_in, 0, 3650);
            var channel = saChannelSelect(data.channel_id || 0, t('sa_no_channel'));
            var grid = el('div', 'ws-sa-condition-grid');

            detail.appendChild(saLabelled(t('sa_task_title'), title));
            detail.appendChild(saLabelled(t('sa_task_text'), description));
            detail.appendChild(saLabelled(t('sa_task_people'), people));
            grid.appendChild(saLabelled(t('sa_task_priority'), priority));
            grid.appendChild(saLabelled(t('sa_task_due'), due, t('sa_task_due_help')));
            grid.appendChild(saLabelled(t('sa_task_channel'), channel));
            detail.appendChild(grid);

            reader = function () {
                return {
                    type: 'task',
                    title: title.value,
                    description: description.value,
                    assignees: people.value(),
                    priority: priority.value,
                    due_in: due.value,
                    channel_id: parseInt(channel.value, 10) || 0
                };
            };
        }

        // Each person their tasks by e-mail, or one channel's tasks.
        function taskDigest() {
            var data = given('task_digest', { mode: 'mine', channel_id: 0, user_ids: [], only: 'open', subject: '', intro: '', missed: 0, ai: 0 });
            var modes = el('div', 'btn-group btn-group-sm ws-sa-modes');
            var mode = data.mode || 'mine';
            var channel = saChannelSelect(data.channel_id || 0, t('sa_digest_all_channels'));
            var channelBox = saLabelled(t('sa_channel'), channel);
            var people = peoplePicker(data.user_ids || []);
            var only = select(Object.keys(config.digest_only || {}).map(function (key) { return [key, config.digest_only[key]]; }), data.only || 'open');
            var subject = saText(data.subject, t('sa_digest_subject_placeholder'), 250);
            var intro = saArea(data.intro, 2);
            var peopleHelp = el('div', 'form-text mt-0');

            function showMode() {
                peopleHelp.textContent = (mode === 'channel') ? t('sa_digest_people_channel') : t('sa_digest_people_mine');
                channel.options[0].hidden = (mode === 'channel');

                if ((mode === 'channel') && (channel.value === '0') && channel.options.length > 1) {
                    channel.selectedIndex = 1;
                }
            }

            [['mine', t('sa_digest_mine')], ['channel', t('sa_digest_channel')]].forEach(function (item) {
                var toggle = button('btn btn-outline theme-secondary' + (mode === item[0] ? ' active' : ''), item[1]);
                toggle.addEventListener('click', function () {
                    mode = item[0];
                    Array.prototype.forEach.call(modes.children, function (node) { node.classList.toggle('active', node === toggle); });
                    showMode();
                });
                modes.appendChild(toggle);
            });

            detail.appendChild(modes);
            detail.appendChild(channelBox);
            detail.appendChild(saLabelled(t('sa_digest_which'), only));

            var peopleBox = saLabelled(t('sa_people'), people);
            peopleBox.appendChild(peopleHelp);
            detail.appendChild(peopleBox);
            detail.appendChild(saLabelled(t('sa_subject'), subject));
            detail.appendChild(saLabelled(t('sa_digest_intro'), intro, t('sa_digest_intro_help')));

            // What each person missed, and Pinegrap AI's few sentences on it
            // where it is set up.
            function digestSwitch(label, help, checked) {
                var wrap = el('div', 'form-field mb-3');
                var input = el('input', '');
                var caption = el('label', 'small', label);

                input.type = 'checkbox';
                input.id = nextId('ws-sa-digest-');
                input.setAttribute('role', 'switch');
                input.checked = !!checked;
                caption.htmlFor = input.id;
                wrap.appendChild(el('div', 'switch')).appendChild(input);
                wrap.appendChild(caption);
                wrap.appendChild(el('div', 'form-text mt-0', help));
                detail.appendChild(wrap);

                return input;
            }

            var missed = digestSwitch(t('sa_digest_missed'), t('sa_digest_missed_help'), data.missed);
            var ai = config.recap_ai ? digestSwitch(t('sa_digest_ai'), t('sa_digest_ai_help'), data.ai) : null;

            function showAi() {
                if (ai) {
                    ai.closest('.form-field').classList.toggle('d-none', !missed.checked);
                }
            }

            missed.addEventListener('change', showAi);
            showAi();
            showMode();

            reader = function () {
                return {
                    type: 'task_digest',
                    mode: mode,
                    channel_id: parseInt(channel.value, 10) || 0,
                    user_ids: people.value(),
                    only: only.value,
                    subject: subject.value,
                    intro: intro.value,
                    missed: missed.checked ? 1 : 0,
                    // Kept as it was where Pinegrap AI is not set up now.
                    ai: (missed.checked && (ai ? ai.checked : !!data.ai)) ? 1 : 0
                };
            };
        }

        function webhook() {
            var data = given('webhook', { url: '', format: 'text', text: '{{name}}: {{result}}' });
            var url = saText(data.url, 'https://hooks.slack.com/services/…', 2000);
            var format = select(Object.keys(config.formats || {}).map(function (key) { return [key, config.formats[key]]; }), data.format || 'text');
            var text = saArea(data.text, 3);

            url.type = 'url';
            detail.appendChild(saLabelled(t('sa_url'), url, t('sa_webhook_help')));
            detail.appendChild(saLabelled(t('sa_format'), format));
            detail.appendChild(saLabelled(t('sa_webhook_text'), text));

            reader = function () {
                return { type: 'webhook', url: url.value.trim(), format: format.value, text: text.value };
            };
        }

        function webCheck() {
            var data = given('web_check', { url: config.site || 'https://', mode: 'status', redirect: false, contains: '', max_ms: '', days: 14 });
            var url = saText(data.url, 'https://', 2000);
            var mode = select([['status', t('sa_check_status')], ['ssl', t('sa_check_ssl')]], data.mode || 'status');
            var statusBox = el('div');
            var sslBox = el('div');
            var redirectWrap = el('div', 'form-field mb-3');
            var redirect = el('input', 'check');
            var redirectLabel = el('label', 'small', t('sa_check_redirect'));
            var contains = saText(data.contains, '', 200);
            var maxMs = saNumber(data.max_ms || '', 0, 60000);
            var days = saNumber(data.days || 14, 1, 365);

            url.type = 'url';
            redirect.type = 'checkbox';
            redirect.id = nextId('ws-sa-redirect-');
            redirect.checked = !!data.redirect;
            redirectLabel.htmlFor = redirect.id;
            redirectWrap.appendChild(redirect);
            redirectWrap.appendChild(redirectLabel);

            detail.appendChild(saLabelled(t('sa_url'), url));
            detail.appendChild(saLabelled(t('sa_check_mode'), mode));
            statusBox.appendChild(redirectWrap);
            statusBox.appendChild(saLabelled(t('sa_check_contains'), contains));
            statusBox.appendChild(saLabelled(t('sa_check_max_ms'), maxMs, t('sa_check_max_help')));
            sslBox.appendChild(saLabelled(t('sa_check_days'), days));
            detail.appendChild(statusBox);
            detail.appendChild(sslBox);
            detail.appendChild(el('div', 'form-text', t('sa_check_help')));

            function show() {
                statusBox.hidden = (mode.value !== 'status');
                sslBox.hidden = (mode.value !== 'ssl');
            }

            mode.addEventListener('change', show);
            show();

            reader = function () {
                return {
                    type: 'web_check',
                    url: url.value.trim(),
                    mode: mode.value,
                    redirect: redirect.checked ? 1 : 0,
                    contains: contains.value,
                    max_ms: maxMs.value,
                    days: days.value
                };
            };
        }

        function report() {
            var metrics = config.metrics || {};
            var data = given('report', { title: '', metrics: [], channel_id: null, to: [] });
            var chosen = {};
            var scopeChannel = 0;
            var threshold = 5;

            (data.metrics || []).forEach(function (item) {
                chosen[item.key] = true;

                if ((metrics[item.key] || {}).scope === 'channel') {
                    scopeChannel = item.param || 0;
                }

                if ((metrics[item.key] || {}).scope === 'threshold') {
                    threshold = item.param || 0;
                }
            });

            var title = saText(data.title, t('sa_tpl_digest_title'), 160);
            var list = el('div', 'ws-sa-metric-list');
            var boxes = {};

            Object.keys(metrics).forEach(function (key) {
                var wrap = el('div', 'form-field');
                var check = el('input', 'check');
                var label = el('label', 'small', metrics[key].label);

                check.type = 'checkbox';
                check.id = nextId('ws-sa-metric-');
                check.checked = !!chosen[key];
                label.htmlFor = check.id;
                wrap.appendChild(check);
                wrap.appendChild(label);
                list.appendChild(wrap);
                boxes[key] = check;
            });

            var tasksOf = saChannelSelect(scopeChannel, t('sa_metric_all'));
            var limit = saNumber(threshold, 0, 1000000);
            var channel = saChannelSelect(data.channel_id, t('sa_no_channel'));
            var to = saText((data.to || []).join(', '));
            var grid = el('div', 'ws-sa-condition-grid');

            if (data.channel_id === null && app.channel) {
                channel.value = String(app.channel.id);
            }

            detail.appendChild(saLabelled(t('sa_report_title'), title));
            detail.appendChild(saLabelled(t('sa_report_metrics'), list));
            grid.appendChild(saLabelled(t('sa_metric_channel'), tasksOf));

            if (Object.keys(metrics).some(function (key) { return metrics[key].scope === 'threshold'; })) {
                grid.appendChild(saLabelled(t('sa_metric_threshold'), limit));
            }

            detail.appendChild(grid);
            detail.appendChild(saLabelled(t('sa_channel'), channel));
            detail.appendChild(saLabelled(t('sa_report_to'), to, t('sa_to_help')));

            reader = function () {
                var picked = Object.keys(boxes).filter(function (key) { return boxes[key].checked; }).map(function (key) {
                    var scope = metrics[key].scope;

                    return { key: key, param: scope === 'channel' ? (parseInt(tasksOf.value, 10) || 0) : (scope === 'threshold' ? (parseInt(limit.value, 10) || 0) : 0) };
                });

                return { type: 'report', title: title.value, metrics: picked, channel_id: parseInt(channel.value, 10) || 0, to: to.value };
            };
        }

        function trigger() {
            var data = given('trigger', { target_id: 0, delay: 0 });
            var slot = el('div');
            var delay = saNumber(data.delay || 0, 0, 10080);
            var target = null;

            slot.appendChild(skeleton(2, 'ws-skeleton-sm'));
            detail.appendChild(saLabelled(t('sa_target'), slot));
            detail.appendChild(saLabelled(t('sa_delay'), delay, t('sa_delay_help')));
            detail.appendChild(el('div', 'form-text', t('sa_chain_help', config.depth || 8)));

            api('ws_scheduled_targets').then(function (result) {
                var items = (result.items || []).map(function (item) {
                    return [item.id, item.name + (item.id === selfId ? ' ↺' : '') + (item.status !== 'active' ? ' · ' + t('sa_state_' + item.status) : '')];
                });

                clear(slot);

                if (!items.length) {
                    slot.appendChild(el('div', 'small fg-secondary', t('sa_target_none')));
                    return;
                }

                target = select(items, data.target_id || items[0][0]);
                slot.appendChild(target);
            }).catch(function (error) {
                clear(slot);
                slot.appendChild(el('div', 'small fg-danger', error.message));
            });

            reader = function () {
                return { type: 'trigger', target_id: target ? (parseInt(target.value, 10) || 0) : 0, delay: delay.value };
            };
        }

        function change() {
            var data = given('change', { record: '', action: 'update', id: 0, fields: {} });
            var kinds = Object.keys(config.changes).map(function (key) { return [key, config.changes[key].label]; });
            var kind = select(kinds, data.record || (kinds[0] || [''])[0]);
            var picker = saRecordPicker((config.changes[kind.value] || {}).tag || '', data.id || 0, data.label || (data.id ? '#' + data.id : ''));
            var verbBox = el('div');
            var verb = null;
            var fieldsBox = el('div', 'ws-sa-fields');
            var addField = button('btn btn-sm btn-text theme-secondary', t('sa_add_field'), 'bi-plus-lg');
            var rows = [];

            function info() {
                return config.changes[kind.value] || { fields: {}, actions: ['update'] };
            }

            function fieldRow(name, value) {
                var fields = Object.keys(info().fields).filter(function (key) { return info().fields[key].column || key === 'cancellation_reason'; });
                var row = el('div', 'ws-sa-field-row');
                var which = select(fields.map(function (key) { return [key, info().fields[key].label]; }), name || fields[0]);
                var slot = el('div', 'flex-grow-1');
                var input = null;
                var remove = button('btn btn-sm btn-text theme-secondary', '', 'bi-x-lg', t('delete'));
                var entry = { row: row };

                function draw() {
                    clear(slot);
                    input = saValueInput(info().fields[which.value], (which.value === name) ? value : '');
                    slot.appendChild(input);
                }

                which.addEventListener('change', draw);
                remove.addEventListener('click', function () {
                    row.remove();
                    rows = rows.filter(function (item) { return item !== entry; });
                });

                row.appendChild(which);
                row.appendChild(slot);
                row.appendChild(remove);
                draw();

                entry.read = function () { return [which.value, input.read()]; };
                rows.push(entry);
                fieldsBox.appendChild(row);
            }

            function drawVerb() {
                clear(verbBox);
                verb = select(info().actions.map(function (key) { return [key, key === 'delete' ? t('sa_verb_delete') : t('sa_verb_update')]; }), data.action || 'update');
                verb.addEventListener('change', function () {
                    fieldsBox.hidden = addField.hidden = (verb.value === 'delete');
                });
                verbBox.appendChild(verb);
                fieldsBox.hidden = addField.hidden = (verb.value === 'delete');
            }

            function reset() {
                picker.setTag(info().tag);
                clear(fieldsBox);
                rows = [];
                drawVerb();
            }

            kind.addEventListener('change', function () {
                data = { record: kind.value, action: 'update', id: 0, fields: {} };
                reset();
                fieldRow('', '');
            });
            addField.addEventListener('click', function () { fieldRow('', ''); });

            detail.appendChild(saLabelled(t('sa_record_kind'), kind));
            detail.appendChild(saLabelled(t('sa_record'), picker));
            detail.appendChild(saLabelled(t('sa_change_verb'), verbBox));
            detail.appendChild(fieldsBox);
            detail.appendChild(addField);
            drawVerb();

            var names = Object.keys(data.fields || {});

            if (names.length) {
                names.forEach(function (key) { fieldRow(key, data.fields[key]); });
            } else {
                fieldRow('', '');
            }

            reader = function () {
                var fields = {};

                if (verb.value !== 'delete') {
                    rows.forEach(function (entry) {
                        var pair = entry.read();

                        if (pair[0]) {
                            fields[pair[0]] = pair[1];
                        }
                    });
                }

                return { type: 'change', record: kind.value, action: verb.value, id: picker.value(), fields: fields };
            };
        }

        // An approval request in a channel (workspace_approvals.js).
        function approval() {
            reader = window.PGWsApprovals.scheduledEditor(detail, given('approval', null), featureHelpers());
        }

        // A check-in in a channel (workspace_status.js).
        function checkin() {
            reader = window.PGWsStatus.scheduledEditor(detail, given('checkin', null), featureHelpers());
        }

        var editors = { post: post, email: email, notify: notify, task: task, task_digest: taskDigest, webhook: webhook, web_check: webCheck, report: report, trigger: trigger, change: change, approval: approval, checkin: checkin };

        function draw() {
            clear(detail);
            reader = function () { return null; };

            if (editors[type.value]) {
                editors[type.value]();
            }
        }

        // The first drawing fills in what was there; a choice of another
        // kind starts from nothing.
        type.addEventListener('change', function () {
            action = null;
            draw();
        });
        draw();

        box.read = function () { return reader(); };

        // The form's "when" changed: a message to write is drawn again, so
        // "the channel they joined" comes or goes, keeping what was typed.
        box.setJoin = function (join) {
            if (!!state.join === !!join) {
                return;
            }

            state.join = !!join;

            if (type.value === 'post') {
                action = reader();
                draw();
            }
        };

        return box;
    }

    // One follow-up: when it is done, and what.
    function saFollowRow(item, selfId, onRemove, state) {
        var config = CFG.scheduled || {};
        var row = el('div', 'ws-sa-follow');
        var head = el('div', 'ws-sa-follow-head');
        var on = select(Object.keys(config.outcomes || {}).map(function (key) { return [key, config.outcomes[key]]; }), (item && item.on) || 'done');
        var removeButton = button('btn btn-sm btn-text theme-secondary ws-sa-remove', '', 'bi-x-lg', t('delete'));
        var editor = saActionEditor(item ? item.do : null, false, selfId, { join: !!(state && state.join) });

        row.editor = editor;

        head.appendChild(icon('bi-arrow-return-right', 'me-1'));
        head.appendChild(on);
        head.appendChild(removeButton);
        row.appendChild(head);
        row.appendChild(editor);

        removeButton.addEventListener('click', function () {
            row.remove();

            if (onRemove) {
                onRemove();
            }
        });

        row.read = function () {
            return { on: on.value, do: editor.read() };
        };

        return row;
    }

    // Ready-made actions that fill the form: the usual needs of a team and a
    // site, from the counts the site keeps.
    function saTemplates(context) {
        var config = CFG.scheduled || {};
        var metrics = config.metrics || {};
        var out = [];

        if (!config.chains) {
            return out;
        }

        var has = function (key) { return !!metrics[key]; };
        var channelId = (context && context.channel_id) || (app.channel && app.channel.id) || 0;
        var me = BOOT.me ? BOOT.me.id : 0;
        var today = CFG.today;
        var tomorrow = addDays(CFG.today, 1);
        var monday = (function () {
            var day = new Date(tomorrow + 'T12:00:00');

            while (day.getDay() !== 1) {
                day.setDate(day.getDate() + 1);
            }

            return day.getFullYear() + '-' + String(day.getMonth() + 1).padStart(2, '0') + '-' + String(day.getDate()).padStart(2, '0');
        })();
        var firstOfMonth = (function () {
            var day = new Date(today + 'T12:00:00');

            day.setMonth(day.getMonth() + 1, 1);

            return day.getFullYear() + '-' + String(day.getMonth() + 1).padStart(2, '0') + '-01';
        })();

        // Where a text goes: the channel it is written in, or the person.
        var tell = function (text) {
            return channelId ? { type: 'post', channel_id: channelId, body: text } : { type: 'notify', user_ids: [me], text: text };
        };

        var digest = ['tasks_open', 'tasks_overdue', 'tasks_due_today', 'orders_today', 'sales_today', 'forms_new', 'comments_pending', 'visitors_yesterday']
            .filter(has).map(function (key) { return { key: key, param: 0 }; });

        // Started by something happening on the site (workspace_events.js).
        if (window.PGWsEvents && window.PGWsEvents.ready) {
            out = out.concat(window.PGWsEvents.templates({ channelId: channelId, me: me, tell: tell }));
        }

        // A channel greets the people who join it and asks who they are.
        if (config.join) {
            out.push({ key: 'welcome', label: t('sa_tpl_welcome'), icon: 'bi-person-plus', help: t('sa_tpl_welcome_help'), data: {
                name: t('sa_tpl_welcome'),
                rules: [{ type: 'join', channel_id: channelId }],
                action: { type: 'post', channel_id: channelId ? channelId : -1, body: t('sa_tpl_welcome_text') },
                follow: []
            } });
        }

        // Every Monday, each person the open tasks they are on.
        out.push({ key: 'task_mail', label: t('sa_tpl_task_mail'), icon: 'bi-envelope-check', help: t('sa_tpl_task_mail_help'), data: {
            name: t('sa_tpl_task_mail'),
            rules: [{ type: 'at', date: monday, time: '09:00', repeat: 'weekly', every: 1, days: [1] }],
            action: { type: 'task_digest', mode: channelId ? 'channel' : 'mine', channel_id: channelId, user_ids: [], only: 'open', subject: t('sa_tpl_task_mail_subject'), intro: '' },
            follow: []
        } });

        // Every working morning, each person what they missed and the tasks
        // due this week: to the one who sets it up, others can be added.
        out.push({ key: 'morning', label: t('sa_tpl_morning'), icon: 'bi-sunrise', help: t('sa_tpl_morning_help'), data: {
            name: t('sa_tpl_morning'),
            rules: [{ type: 'at', date: tomorrow, time: '08:30', repeat: 'workdays' }],
            action: { type: 'task_digest', mode: 'mine', channel_id: 0, user_ids: [me], only: 'week', subject: t('sa_tpl_morning_subject'), intro: '', missed: 1, ai: config.recap_ai ? 1 : 0 },
            follow: []
        } });

        out.push({ key: 'digest', label: t('sa_tpl_digest'), icon: 'bi-clipboard-data', data: {
            name: t('sa_tpl_digest'),
            rules: [{ type: 'at', date: tomorrow, time: '09:00', repeat: 'workdays' }],
            action: { type: 'report', title: t('sa_tpl_digest_title'), metrics: digest, channel_id: channelId, to: [] },
            follow: []
        } });

        var down = [{ on: 'failed', do: { type: 'notify', user_ids: [me], text: t('sa_tpl_uptime_down') } }];

        if (config.email) {
            down.push({ on: 'failed', do: { type: 'email', to: [config.email], subject: t('sa_tpl_uptime_down'), body: '{{result}}\n{{date}} {{time}}' } });
        }

        out.push({ key: 'uptime', label: t('sa_tpl_uptime'), icon: 'bi-activity', data: {
            name: t('sa_tpl_uptime'),
            rules: [{ type: 'at', date: today, time: '00:00', repeat: 'minutes', every: 15 }],
            action: { type: 'web_check', url: config.site || 'https://', mode: 'status', redirect: 1, contains: '', max_ms: 5000 },
            follow: down
        } });

        out.push({ key: 'ssl', label: t('sa_tpl_ssl'), icon: 'bi-shield-lock', data: {
            name: t('sa_tpl_ssl'),
            rules: [{ type: 'at', date: monday, time: '09:00', repeat: 'weekly', every: 1, days: [1] }],
            action: { type: 'web_check', url: config.site || 'https://', mode: 'ssl', days: 21 },
            follow: [{ on: 'failed', do: tell(t('sa_tpl_ssl_warn')) }]
        } });

        out.push({ key: 'overdue', label: t('sa_tpl_overdue'), icon: 'bi-exclamation-diamond', data: {
            name: t('sa_tpl_overdue'),
            rules: [{ type: 'at', date: tomorrow, time: '10:00', repeat: 'workdays' }, { type: 'metric', metric: 'tasks_overdue', param: 0, op: 'gt', value: 0 }],
            action: tell(t('sa_tpl_overdue_text')),
            follow: []
        } });

        if (has('forms_new')) {
            out.push({ key: 'forms', label: t('sa_tpl_forms'), icon: 'bi-ui-checks', data: {
                name: t('sa_tpl_forms'),
                rules: [{ type: 'at', date: today, time: '00:00', repeat: 'hourly', every: 1 }, { type: 'metric', metric: 'forms_new', param: 0, op: 'gt', value: 0 }, { type: 'hours', from: '08:00', to: '20:00' }],
                action: tell(t('sa_tpl_forms_text')),
                follow: []
            } });
        }

        if (has('stock_low')) {
            out.push({ key: 'stock', label: t('sa_tpl_stock'), icon: 'bi-box-seam', data: {
                name: t('sa_tpl_stock'),
                rules: [{ type: 'at', date: tomorrow, time: '08:30', repeat: 'daily', every: 1 }, { type: 'metric', metric: 'stock_low', param: 5, op: 'gt', value: 0 }],
                action: { type: 'notify', user_ids: [me], text: t('sa_tpl_stock_text') },
                follow: []
            } });
        }

        if (has('orders_new')) {
            out.push({ key: 'orders', label: t('sa_tpl_orders'), icon: 'bi-bag-check', data: {
                name: t('sa_tpl_orders'),
                rules: [{ type: 'at', date: today, time: '00:00', repeat: 'hourly', every: 1 }, { type: 'metric', metric: 'orders_new', param: 0, op: 'gt', value: 0 }],
                action: { type: 'webhook', url: '', format: 'text', text: t('sa_tpl_orders_text') },
                follow: []
            } });
        }

        if (has('invoices_overdue')) {
            out.push({ key: 'invoices', label: t('sa_tpl_invoices'), icon: 'bi-receipt', data: {
                name: t('sa_tpl_invoices'),
                rules: [{ type: 'at', date: monday, time: '09:30', repeat: 'weekly', every: 1, days: [1] }, { type: 'metric', metric: 'invoices_overdue', param: 0, op: 'gt', value: 0 }],
                action: tell(t('sa_tpl_invoices_text')),
                follow: []
            } });
        }

        out.push({ key: 'monthly', label: t('sa_tpl_monthly'), icon: 'bi-calendar-month', data: {
            name: t('sa_tpl_monthly'),
            rules: [{ type: 'at', date: firstOfMonth, time: '09:00', repeat: 'monthly', every: 1 }],
            action: { type: 'task', title: t('sa_tpl_monthly_task'), description: '', priority: 'high', assignees: [me], channel_id: channelId, due_in: 5 },
            follow: []
        } });

        out.push({ key: 'chain', label: t('sa_tpl_chain'), icon: 'bi-diagram-3', data: {
            name: t('sa_tpl_chain'),
            rules: [{ type: 'trigger' }],
            action: tell(t('sa_tpl_chain_text')),
            follow: []
        } });

        return out;
    }

    // The form: a new action (context: channel_id or note_id) or one being
    // changed (item from the server); preset fills a new one from a ready
    // one (saTemplates()). It reads as a flow, top to bottom: when it starts,
    // what must hold, what it does, what follows; beside it, the server says
    // in words what it will do and when it runs next.
    function scheduledForm(context, item, onDone, preset) {
        var config = CFG.scheduled;

        if (!config) {
            return;
        }

        var data = item ? item.data : ((preset && preset.data) || { name: '', rules: [], action: null, follow: [] });
        var selfId = item ? item.id : 0;
        var node = offcanvas('ws-sa-form', '');
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));
        var state = { join: false };

        node.classList.add('ws-sa-offcanvas');
        node.querySelector('.drawer-title').textContent = item ? t('sa_edit') : t('sa_new_long');
        footer.classList.remove('d-none');

        // ── in a sentence: Pinegrap AI fills the form in ──

        if (!item && config.draft) {
            body.appendChild(saSentenceBox(context, onDone, preset));
        }

        // ── a ready one: cards to start from ──

        var templates = item ? [] : saTemplates(context);

        if (templates.length) {
            var gallery = el('div', 'ws-sa-gallery');
            var galleryTitle = el('div', 'ws-sa-gallery-title', t('sa_templates'));

            [{ key: '', label: t('sa_template_none'), icon: 'bi-plus-lg', help: t('sa_template_none_help') }].concat(templates).forEach(function (template) {
                var card = el('button', 'ws-sa-template' + (((preset ? preset.key : '') === template.key) ? ' active' : ''));
                card.type = 'button';
                card.appendChild(icon(template.icon || 'bi-alarm', 'ws-sa-template-icon'));

                var text = el('span', 'ws-sa-template-text');
                text.appendChild(el('b', '', template.label));

                if (template.help) {
                    text.appendChild(el('small', '', template.help));
                }

                card.appendChild(text);
                card.addEventListener('click', function () {
                    scheduledForm(context, null, onDone, template.key ? template : null);
                });
                gallery.appendChild(card);
            });

            // Open on a blank form; once one is picked it folds away.
            var details = el('details', 'ws-sa-gallery-wrap');
            details.open = !preset;
            var summary = el('summary', '');
            summary.appendChild(galleryTitle);
            details.appendChild(summary);
            details.appendChild(gallery);
            body.appendChild(details);
        }

        var name = el('input', 'form-control');
        name.maxLength = 160;
        name.value = data.name || '';
        body.appendChild(formRow(t('sa_name'), name, t('sa_name_help')));

        // The flow: numbered steps down a rail.
        var flow = el('div', 'ws-sa-flow');
        body.appendChild(flow);

        function step(number, iconName, title, help) {
            var wrap = el('div', 'pg-step ws-sa-step');
            var rail = el('div', 'pg-step-rail');
            var nodeMark = el('span', 'pg-step-node');
            var box = el('div', 'ws-sa-section');
            var head = el('div', 'ws-sa-section-title');

            nodeMark.appendChild(icon(iconName));
            nodeMark.title = String(number);
            rail.appendChild(nodeMark);
            wrap.appendChild(rail);
            head.appendChild(el('span', 'ws-sa-step-number', String(number)));
            head.appendChild(document.createTextNode(title));
            box.appendChild(head);

            if (help) {
                box.appendChild(el('div', 'form-text mt-0 mb-3', help));
            }

            wrap.appendChild(box);
            flow.appendChild(wrap);

            return box;
        }

        // ── 1 · when ──

        var time = null;
        var whenMode = 'time';
        var joinRule = null;
        var eventRule = null;
        var events = !!(window.PGWsEvents && window.PGWsEvents.ready);

        (data.rules || []).forEach(function (rule) {
            if (rule.type === 'at') {
                time = rule;
            }

            if (rule.type === 'trigger') {
                whenMode = 'trigger';
            }

            if (rule.type === 'join') {
                whenMode = 'join';
                joinRule = rule;
            }

            if (rule.type === 'event') {
                whenMode = 'event';
                eventRule = rule;
            }
        });

        if (((whenMode === 'join') && !config.join) || ((whenMode === 'event') && !events)) {
            whenMode = 'time';
        }

        var tomorrow = addDays(CFG.today, 1);
        var rules = step(1, 'bi-lightning', t('sa_step_when'), t('sa_step_when_help'));
        var modes = el('div', 'btn-group btn-group-sm ws-sa-modes mb-3 flex-wrap');
        var timeBox = el('div');
        var triggerBox = el('div', 'ws-sa-when-trigger small fg-secondary', t('sa_when_trigger_help'));
        var joinBox = el('div', 'ws-sa-when-join');

        if (config.chains) {
            [['time', t('sa_when_time'), 'bi-alarm'], ['trigger', t('sa_when_trigger'), 'bi-diagram-3']]
                .concat(config.join ? [['join', t('sa_when_join'), 'bi-person-plus']] : [])
                .concat(events ? [['event', t('sa_when_event'), window.PGWsEvents.icon]] : [])
                .forEach(function (option) {
                    var toggle = button('btn btn-outline theme-secondary' + (whenMode === option[0] ? ' active' : ''), option[1], option[2]);
                    toggle.addEventListener('click', function () {
                        whenMode = option[0];
                        Array.prototype.forEach.call(modes.children, function (other) { other.classList.toggle('active', other === toggle); });
                        showWhen();
                        preview();
                    });
                    modes.appendChild(toggle);
                });
            rules.appendChild(modes);
        }

        var whenRow = el('div', 'ws-sa-when');
        var date = el('input', 'form-control form-control-sm');
        var clock = el('input', 'form-control form-control-sm');
        var repeat = select(Object.keys(config.repeats).filter(function (key) {
            return config.chains || ['none', 'daily', 'workdays', 'weekly', 'monthly'].indexOf(key) !== -1;
        }).map(function (key) { return [key, config.repeats[key]]; }), time ? time.repeat : 'none');
        var every = saNumber(time && time.every ? time.every : 1, 1, 720);
        var everyUnit = el('span', 'small fg-secondary ms-1');
        var everyWrap = el('div', 'd-flex align-items-center');
        var everyBox = saLabelled(t('sa_every'), everyWrap);
        var days = saDayPicker(time && time.days ? time.days : []);
        var daysBox = saLabelled(t('sa_on_days'), days);
        var until = el('input', 'form-control form-control-sm');
        var untilBox = saLabelled(t('sa_until'), until, t('sa_until_help'));

        date.type = 'date';
        date.value = time ? time.date : tomorrow;
        clock.type = 'time';
        clock.value = time ? time.time : '09:00';
        until.type = 'date';
        until.value = (time && time.until) || '';
        every.style.maxWidth = '6rem';
        everyWrap.appendChild(every);
        everyWrap.appendChild(everyUnit);

        whenRow.appendChild(icon('bi-alarm', 'ws-sa-when-icon'));
        whenRow.appendChild(saLabelled(t('sa_date'), date));
        whenRow.appendChild(saLabelled(t('sa_time'), clock));
        whenRow.appendChild(saLabelled(t('sa_repeat'), repeat));
        timeBox.appendChild(whenRow);

        var repeatRow = el('div', 'ws-sa-when-more');
        repeatRow.appendChild(everyBox);
        repeatRow.appendChild(untilBox);
        timeBox.appendChild(repeatRow);
        daysBox.classList.add('ws-sa-when-days');
        timeBox.appendChild(daysBox);

        // Somebody joins: one channel, or any public one.
        var joinChannel = saChannelSelect(joinRule ? (joinRule.channel_id || 0) : ((context && context.channel_id) || 0), t('sa_join_any'));
        joinBox.appendChild(saLabelled(t('sa_join_channel'), joinChannel, t('sa_join_help')));

        rules.appendChild(timeBox);
        rules.appendChild(triggerBox);
        rules.appendChild(joinBox);

        // Something happens on the site: which event, for which form or status.
        var eventBox = events ? window.PGWsEvents.whenBox(eventRule) : null;

        if (eventBox) {
            rules.appendChild(eventBox.node);
        }

        var units = { minutes: 'sa_every_minutes', hourly: 'sa_every_hours', daily: 'sa_every_days', weekly: 'sa_every_weeks', monthly: 'sa_every_months', yearly: 'sa_every_years' };
        var actionEditors = [];

        function showWhen() {
            var value = repeat.value;

            timeBox.hidden = (whenMode !== 'time');
            triggerBox.hidden = (whenMode !== 'trigger');
            joinBox.hidden = (whenMode !== 'join');

            if (eventBox) {
                eventBox.node.hidden = (whenMode !== 'event');
            }

            repeatRow.hidden = (value === 'none');
            everyBox.hidden = !units[value];
            everyUnit.textContent = units[value] ? t(units[value]) : '';
            daysBox.hidden = (value !== 'weekly');
            every.min = (value === 'minutes') ? String(config.min_minutes || 5) : '1';

            if (value === 'minutes' && (parseInt(every.value, 10) || 0) < (config.min_minutes || 5)) {
                every.value = String(config.min_minutes || 5);
            }

            state.join = (whenMode === 'join');
            actionEditors.forEach(function (editor) { editor.setJoin(state.join); });
        }

        repeat.addEventListener('change', showWhen);

        // ── 2 · only if ──

        var checks = step(2, 'bi-funnel', t('sa_step_if'), t('sa_rules_help'));
        var conditions = el('div', 'ws-sa-conditions');
        checks.appendChild(conditions);

        (data.rules || []).forEach(function (rule) {
            if (['at', 'trigger', 'join', 'event'].indexOf(rule.type) === -1) {
                conditions.appendChild(saConditionRow(rule));
            }
        });

        var addRule = button('btn btn-sm btn-text theme-secondary', t('sa_add_rule'), 'bi-plus-lg');
        addRule.addEventListener('click', function (event) {
            var box = addRule.getBoundingClientRect();
            var add = function (rule) { return function () { conditions.appendChild(saConditionRow(rule)); preview(); }; };

            event.stopPropagation();
            ctxMenu(box.left, box.bottom + 4, [
                Object.keys(config.records || {}).length ? { icon: 'bi-database-check', label: t('sa_rule_record'), action: add({ type: 'record' }) } : null,
                (config.chains && Object.keys(config.metrics || {}).length) ? { icon: 'bi-123', label: t('sa_rule_metric'), action: add({ type: 'metric' }) } : null,
                { icon: 'bi-briefcase', label: t('sa_rule_workday'), action: add({ type: 'workday' }) },
                config.chains ? { icon: 'bi-calendar-week', label: t('sa_rule_weekdays'), action: add({ type: 'weekdays' }) } : null,
                config.chains ? { icon: 'bi-clock', label: t('sa_rule_hours'), action: add({ type: 'hours' }) } : null
            ]);
        });
        checks.appendChild(addRule);

        // ── 3 · do ──

        var doing = step(3, 'bi-lightning-charge', t('sa_step_do'), t('sa_action_help'));
        var actionEditor = saActionEditor(data.action, false, selfId, { join: whenMode === 'join' });
        actionEditors.push(actionEditor);
        doing.appendChild(actionEditor);

        // ── 4 · afterwards ──

        var follows = el('div', 'ws-sa-follows');
        var thenEditor = null;

        if (config.chains) {
            var after = step(4, 'bi-signpost-split', t('sa_follow'), t('sa_follow_help'));
            var addFollow = button('btn btn-sm btn-text theme-secondary', t('sa_add_follow'), 'bi-plus-lg');
            var countFollow = function () {
                addFollow.hidden = (follows.children.length >= (config.max_follow || 5));
                actionEditors = [actionEditor].concat(Array.prototype.map.call(follows.children, function (row) { return row.editor; }).filter(Boolean));
            };

            after.appendChild(follows);

            (data.follow || []).forEach(function (follow) {
                follows.appendChild(saFollowRow(follow, selfId, countFollow, state));
            });

            addFollow.addEventListener('click', function () {
                follows.appendChild(saFollowRow(null, selfId, countFollow, state));
                countFollow();
            });
            after.appendChild(addFollow);
            countFollow();
        } else {
            var then = step(4, 'bi-signpost-split', t('sa_then'), t('sa_then_help'));
            thenEditor = saActionEditor((data.follow && data.follow[0]) ? data.follow[0].do : null, true, selfId, { join: whenMode === 'join' });
            actionEditors.push(thenEditor);
            then.appendChild(thenEditor);
        }

        showWhen();

        // ── the words filled in when it runs ──

        if (config.placeholders) {
            var words = el('details', 'ws-sa-words small mt-5');
            var wordsSummary = el('summary', 'fg-secondary', t('sa_placeholders'));
            var table = el('div', 'ws-sa-words-list');

            words.appendChild(wordsSummary);
            Object.keys(config.placeholders).forEach(function (key) {
                var line = el('div');
                line.appendChild(el('code', '', key));
                line.appendChild(document.createTextNode(' ' + config.placeholders[key]));
                table.appendChild(line);
            });

            var keys = el('div', 'fg-secondary mt-1', Object.keys(config.metrics || {}).join(', '));
            table.appendChild(keys);
            words.appendChild(table);
            body.appendChild(words);
        }

        // ── what it will do, in words, as it is filled in ──

        var outline = el('div', 'ws-sa-outline');
        body.insertBefore(outline, flow);

        function request() {
            var list = [];

            if (whenMode === 'trigger') {
                list.push({ type: 'trigger' });
            } else if (whenMode === 'join') {
                list.push({ type: 'join', channel_id: parseInt(joinChannel.value, 10) || 0 });
            } else if ((whenMode === 'event') && eventBox) {
                list.push(eventBox.read());
            } else {
                var at = { type: 'at', date: date.value, time: clock.value, repeat: repeat.value };

                if (repeat.value !== 'none') {
                    at.every = parseInt(every.value, 10) || 1;
                    at.until = until.value;

                    if (repeat.value === 'weekly') {
                        at.days = days.value();
                    }
                }

                list.push(at);
            }

            Array.prototype.forEach.call(conditions.children, function (row) {
                if (row.read) {
                    list.push(row.read());
                }
            });

            var out = {
                id: item ? item.id : 0,
                name: name.value,
                rules: list,
                main_action: actionEditor.read(),
                channel_id: (context && context.channel_id) || 0,
                note_id: (context && context.note_id) || 0
            };

            // Opened from a draft of Pinegrap AI in a channel: saving it
            // marks the draft's card.
            if (!item && preset && preset.change_id) {
                out.draft_change_id = preset.change_id;
            }

            if (config.chains) {
                out.follow = Array.prototype.map.call(follows.children, function (row) { return row.read(); }).filter(function (follow) { return follow.do; });
            } else {
                out.then_action = thenEditor ? thenEditor.read() : null;
            }

            return out;
        }

        var previewSerial = 0;

        // A form that was opened empty says nothing is missing until
        // somebody has started filling it in.
        var touched = !!(item || preset);

        var preview = debounce(function () {
            var serial = ++previewSerial;
            var asked = request();

            if (!asked.name.trim()) {
                asked.name = '-';
            }

            api('ws_scheduled_preview', asked).then(function (result) {
                if (serial !== previewSerial) {
                    return;
                }

                clear(outline);
                outline.classList.remove('ws-sa-outline-error');

                var line = function (iconName, label, text) {
                    var row = el('div', 'ws-sa-outline-row');
                    row.appendChild(icon(iconName));
                    row.appendChild(el('b', '', label));
                    row.appendChild(el('span', '', text));
                    outline.appendChild(row);
                };

                line('bi-lightning', t('sa_step_when'), result.when);

                (result.conditions || []).forEach(function (text) { line('bi-funnel', t('sa_step_if'), text); });

                line('bi-lightning-charge', t('sa_step_do'), result.action);

                (result.follow || []).forEach(function (follow) { line('bi-arrow-return-right', follow.label, follow.text); });

                if ((result.next || []).length) {
                    line('bi-calendar-check', t('sa_next_runs'), result.next.join(' · '));
                }
            }).catch(function (error) {
                if (serial !== previewSerial) {
                    return;
                }

                clear(outline);

                if (!touched) {
                    return;
                }

                outline.classList.add('ws-sa-outline-error');
                outline.appendChild(icon('bi-exclamation-circle', 'me-1'));
                outline.appendChild(document.createTextNode(error.message));
            });
        }, 500);

        var changed = function () {
            touched = true;
            preview();
        };

        body.addEventListener('input', changed);
        body.addEventListener('change', changed);
        body.addEventListener('click', function (event) {
            if (event.target.closest('.ws-sa-days, .ws-sa-modes, .ws-sa-remove, .ws-sa-picker-item')) {
                changed();
            }
        });
        preview();

        body.appendChild(el('div', 'small fg-secondary mt-5', t('sa_help')));

        // ── saving ──

        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(cancel);

        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', item ? t('save') : t('sa_save'), item ? 'bi-check2' : 'bi-alarm');
        save.addEventListener('click', function () {
            save.disabled = true;

            api('ws_scheduled_save', request()).then(function (result) {
                hideOffcanvas(node);
                toast(t('sa_saved'), 'success');

                if (onDone) {
                    onDone(result.item);
                }
            }).catch(function (error) {
                save.disabled = false;
                fail(error);
            });
        });
        footer.appendChild(save);

        showOffcanvas(node);

        // An empty form starts in the sentence box where there is one.
        if (!touchScreen() && !item && !preset) {
            setTimeout(function () { (body.querySelector('.ws-sa-sentence textarea') || name).focus(); }, 250);
        }
    }

    // What the action should do, in the person's words. Pinegrap AI turns it
    // into the form's steps (includes/workspace/scheduled_draft.php) and the
    // form opens again with them, the way a ready-made one does; nothing is
    // saved until the person saves. A form filled in this way keeps the
    // sentence and says what was left out or left open.
    function saSentenceBox(context, onDone, preset) {
        var drafted = !!(preset && preset.key === 'sentence');
        var box = el('div', 'ws-sa-sentence');
        var area = saArea(drafted ? preset.sentence : '', 2, t('sa_sentence_placeholder'));
        var row = el('div', 'ws-sa-sentence-row');
        var go = button('btn btn-sm btn-outline theme-primary', t('sa_sentence_fill'), 'bi-stars');
        var said = el('div', 'ws-sa-sentence-said small');

        area.maxLength = 500;
        box.appendChild(el('label', 'form-label small mb-1', t('sa_sentence')));
        row.appendChild(area);
        row.appendChild(go);
        box.appendChild(row);
        box.appendChild(said);

        function say(text, notes, error) {
            clear(said);
            said.classList.toggle('fg-danger', !!error);

            if (text) {
                said.appendChild(el('div', '', text));
            }

            if ((notes || []).length) {
                var list = el('ul', 'mb-0 ps-5');

                notes.forEach(function (note) { list.appendChild(el('li', '', note)); });
                said.appendChild(list);
            }
        }

        say(drafted ? t('sa_sentence_done') : t('sa_sentence_help'), drafted ? preset.notes : []);

        function fill() {
            var text = area.value.trim();

            if (!text) {
                area.focus();
                return;
            }

            go.disabled = true;
            area.readOnly = true;
            say(t('sa_sentence_working'));

            api('ws_scheduled_draft', { text: text, channel_id: (context && context.channel_id) || 0 }).then(function (result) {
                scheduledForm(context, null, onDone, { key: 'sentence', sentence: text, data: result.draft, notes: result.notes || [], change_id: (preset && preset.change_id) || 0 });
            }).catch(function (error) {
                go.disabled = false;
                area.readOnly = false;
                say(error.message, [], true);
            });
        }

        go.addEventListener('click', fill);
        area.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                fill();
            }
        });

        // Typing here is not filling the form in: the summary beside the
        // steps waits for the steps.
        ['input', 'change'].forEach(function (name) {
            box.addEventListener(name, function (event) { event.stopPropagation(); });
        });

        return box;
    }

    // Every scheduled action, for staff, by state: the scheduled screen's
    // list and the Scheduled actions section of Flows (workspace_flow.js,
    // through PGWsKit). New goes into bar, unless options.add is false: in
    // Flows a new rule is a flow, started from New flow. The list is read
    // again while it is still on the page.
    function scheduledList(body, bar, options) {
        if (!options || (options.add !== false)) {
            var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('sa_new_long'), 'bi-plus-lg');
            add.addEventListener('click', function () {
                scheduledForm({}, null, function () { load(); });
            });
            bar.appendChild(add);
        }

        var filters = el('div', 'ws-sa-filters');
        var list = el('div', 'ws-sa-list');
        var status = '';

        [['', t('sa_all')], ['active', t('sa_state_active')], ['paused', t('sa_state_paused')], ['done', t('sa_state_done')], ['failed', t('sa_state_failed')], ['cancelled', t('sa_state_cancelled')]].forEach(function (option) {
            var chip = button('btn btn-sm rounded-pill ' + (option[0] === status ? 'btn-solid theme-secondary' : 'btn-outline theme-secondary'), option[1]);
            chip.addEventListener('click', function () {
                status = option[0];
                Array.prototype.forEach.call(filters.children, function (node) {
                    node.className = 'btn btn-sm rounded-pill ' + (node === chip ? 'btn-solid theme-secondary' : 'btn-outline theme-secondary');
                });
                load();
            });
            filters.appendChild(chip);
        });

        body.appendChild(filters);
        body.appendChild(el('p', 'small fg-secondary', t('sa_help')));
        body.appendChild(list);

        function load() {
            api('ws_scheduled_list', { status: status }).then(function (data) {
                if (!document.body.contains(list)) {
                    return;
                }

                clear(list);

                if (!data.items.length) {
                    var empty = el('div', 'ws-empty');
                    empty.appendChild(icon('bi-alarm'));
                    empty.appendChild(document.createTextNode(t('sa_empty')));
                    list.appendChild(empty);
                }

                data.items.forEach(function (item) {
                    var wrap = el('div', 'ws-sa-list-item');
                    var where = el('div', 'ws-sa-where');

                    if (item.channel) {
                        var go = el('a', '', '#' + item.channel.name);
                        go.href = CFG.urls.workspace + '?channel=' + item.channel.id + (item.message_id ? '&message=' + item.message_id : '');
                        where.appendChild(document.createTextNode(t('sa_in_channel') + ' '));
                        where.appendChild(go);
                    } else if (item.note) {
                        var note = el('a', '', item.note.name);
                        note.href = CFG.urls.notes + '?note=' + item.note.id;
                        where.appendChild(document.createTextNode(t('sa_in_note') + ' '));
                        where.appendChild(note);
                    }

                    wrap.appendChild(where);
                    wrap.appendChild(scheduledCard(item, function () { load(); }));
                    list.appendChild(wrap);
                });
            }).catch(fail);
        }

        load();
    }

    // The card of one scheduled action: in its channel, in its note and in
    // the list. onChange(item) is told of the new state after a button.
    function scheduledCard(item, onChange) {
        var card = el('div', 'ws-sa-card ws-sa-' + item.status);
        var head = el('div', 'ws-sa-card-head');
        var eventIcon = (window.PGWsEvents && window.PGWsEvents.icon) || 'bi-broadcast';
        var whenIcon = item.joins ? 'bi-person-plus' : (item.on_event ? eventIcon : (item.trigger_only ? 'bi-diagram-3' : 'bi-alarm'));

        head.appendChild(icon(whenIcon, 'ws-sa-card-icon'));
        head.appendChild(el('b', 'ws-sa-card-name', item.name));
        head.appendChild(el('span', 'ws-sa-state ws-sa-state-' + item.status, item.status_label));
        card.appendChild(head);

        var when = el('div', 'ws-sa-line ws-sa-when-line');
        when.appendChild(icon(item.joins ? 'bi-person-plus' : (item.on_event ? eventIcon : (item.trigger_only ? 'bi-diagram-3' : (item.repeats ? 'bi-arrow-repeat' : 'bi-calendar-event'))), 'me-1'));
        when.appendChild(document.createTextNode(item.when + (item.next ? ' · ' + t('sa_next') + ': ' + item.next : '')));
        card.appendChild(when);

        if (item.staff) {
            (item.conditions || []).forEach(function (text) {
                var line = el('div', 'ws-sa-line');
                line.appendChild(icon('bi-funnel', 'me-1'));
                line.appendChild(document.createTextNode(text));
                card.appendChild(line);
            });

            var doLine = el('div', 'ws-sa-line ws-sa-do');
            doLine.appendChild(icon('bi-lightning-charge', 'me-1'));
            doLine.appendChild(document.createTextNode(item.action_text));
            card.appendChild(doLine);

            // What follows, by how the action went.
            (item.follow || []).forEach(function (follow) {
                var line = el('div', 'ws-sa-line ws-sa-do ws-sa-follow-line ws-sa-on-' + follow.on);
                line.appendChild(icon(follow.target ? 'bi-diagram-3' : 'bi-arrow-return-right', 'me-1'));
                line.appendChild(el('b', 'me-1', follow.label + ':'));
                line.appendChild(document.createTextNode(follow.text));
                card.appendChild(line);
            });

            // Which actions start this one.
            if ((item.starters || []).length) {
                var startLine = el('div', 'ws-sa-line fg-secondary');
                startLine.appendChild(icon('bi-diagram-3', 'me-1'));
                startLine.appendChild(document.createTextNode(t('sa_started_by') + ': ' + item.starters.map(function (starter) { return starter.name; }).join(', ')));
                card.appendChild(startLine);
            }
        } else {
            card.appendChild(el('div', 'ws-sa-line fg-secondary', t('sa_staff_only')));
        }

        if (item.last) {
            var last = el('div', 'ws-sa-line ws-sa-last ws-sa-last-' + item.last.status);
            last.appendChild(icon(item.last.status === 'done' ? 'bi-check2-circle' : (item.last.status === 'skipped' ? 'bi-skip-forward' : 'bi-exclamation-triangle'), 'me-1'));
            last.appendChild(document.createTextNode(t('sa_last') + ': ' + item.last.label + ' · ' + item.last.time + (item.last.text ? ' · ' + item.last.text : '')));
            card.appendChild(last);
        }

        if (!item.staff) {
            return card;
        }

        var meta = el('div', 'ws-sa-meta');

        if (item.creator) {
            meta.appendChild(el('span', '', t('sa_by') + ' ' + item.creator.name + ' · ' + item.created));
        }

        if (item.runs) {
            meta.appendChild(el('span', '', t('sa_runs') + ': ' + item.runs));
        }

        card.appendChild(meta);

        var tools = el('div', 'ws-sa-tools');
        var refresh = function (data) {
            if (onChange) {
                onChange(data.item);
            }
        };

        if (item.can_edit) {
            var edit = button('btn btn-sm btn-text theme-secondary', t('edit'), 'bi-pencil');
            edit.addEventListener('click', function () {
                scheduledForm(null, item, function (fresh) { refresh({ item: fresh }); });
            });
            tools.appendChild(edit);
        }

        if (item.status === 'active' || item.status === 'paused') {
            var pause = button('btn btn-sm btn-text theme-secondary', item.status === 'active' ? t('sa_pause') : t('sa_resume'), item.status === 'active' ? 'bi-pause' : 'bi-play');
            pause.addEventListener('click', function () {
                api('ws_scheduled_status', { id: item.id, status: item.status === 'active' ? 'paused' : 'active' }).then(refresh).catch(fail);
            });
            tools.appendChild(pause);
        }

        if (item.can_edit) {
            var run = button('btn btn-sm btn-text theme-secondary', t('sa_run_now'), 'bi-lightning');
            run.addEventListener('click', function () {
                ask(t('sa_run_now_confirm'), t('sa_run_now')).then(function (yes) {
                    if (yes) {
                        run.disabled = true;
                        api('ws_scheduled_run_now', { id: item.id }).then(refresh).catch(function (error) {
                            run.disabled = false;
                            fail(error);
                        });
                    }
                });
            });
            tools.appendChild(run);
        }

        // A copy to start a new one from.
        var copy = button('btn btn-sm btn-text theme-secondary', t('sa_duplicate'), 'bi-copy');
        copy.addEventListener('click', function () {
            var data = JSON.parse(JSON.stringify(item.data || {}));

            data.name = t('sa_copy_of', data.name || item.name);
            scheduledForm(item.channel ? { channel_id: item.channel.id } : {}, null, function (fresh) { refresh({ item: fresh }); }, { key: '', data: data });
        });
        tools.appendChild(copy);

        // The action drawn read-only on the flow canvas: in the Scheduled
        // actions section of Flows, where workspace_flow.js is loaded.
        if ((CFG.mode === 'flows') && window.PGWsFlow && window.PGWsFlow.showAction) {
            var canvas = button('btn btn-sm btn-text theme-secondary', t('sa_show_canvas'), 'bi-diagram-3');
            canvas.addEventListener('click', function () {
                scheduledGraph(item.id);
            });
            tools.appendChild(canvas);
        }

        var history = button('btn btn-sm btn-text theme-secondary', t('sa_history'), 'bi-clock-history');
        var historyBox = el('div', 'ws-sa-history');
        historyBox.hidden = true;
        history.addEventListener('click', function () {
            if (!historyBox.hidden) {
                historyBox.hidden = true;
                return;
            }

            api('ws_scheduled_get', { id: item.id }).then(function (data) {
                clear(historyBox);
                historyBox.hidden = false;

                if (!(data.item.history || []).length) {
                    historyBox.appendChild(el('div', 'small fg-secondary', t('sa_no_history')));
                }

                (data.item.history || []).forEach(function (run) {
                    var line = el('div', 'ws-sa-history-row ws-sa-last-' + run.status);
                    line.appendChild(el('b', '', run.label));
                    line.appendChild(el('span', 'fg-secondary', run.time + (run.by_hand ? ' · ' + t('sa_by_hand') : '') + (run.source ? ' · ' + t('sa_started_by') + ' ' + run.source : '')));
                    line.appendChild(el('span', '', run.text));
                    historyBox.appendChild(line);
                });
            }).catch(fail);
        });
        tools.appendChild(history);

        if (item.status === 'active' || item.status === 'paused' || item.status === 'failed') {
            var cancelButton = button('btn btn-sm btn-text theme-secondary fg-danger', t('sa_cancel'), 'bi-x-circle');
            cancelButton.addEventListener('click', function () {
                ask(t('sa_cancel_confirm'), t('sa_cancel'), true).then(function (yes) {
                    if (yes) {
                        api('ws_scheduled_status', { id: item.id, status: 'cancelled' }).then(refresh).catch(fail);
                    }
                });
            });
            tools.appendChild(cancelButton);
        }

        card.appendChild(tools);
        card.appendChild(historyBox);

        return card;
    }

    // A scheduled action on the flow canvas (ws_scheduled_get answers its
    // graph); Edit there opens the form, and the action saved is drawn again.
    function scheduledGraph(id) {
        api('ws_scheduled_get', { id: id }).then(function (data) {
            window.PGWsFlow.showAction(data.item, data.graph, {
                edit: data.item.can_edit ? function () {
                    scheduledForm(null, data.item, function (fresh) { scheduledGraph(fresh.id); });
                } : null
            });
        }).catch(fail);
    }

    // The screen's run: started when ws_sync says something is due, not
    // more than once in twenty seconds.
    var scheduledTickAt = 0;

    function scheduledTick() {
        var now = Date.now();

        if (now - scheduledTickAt < 20000) {
            return;
        }

        scheduledTickAt = now;
        api('ws_tick').catch(function () {});
    }

    // ═══════════════════════════════════════════════════════════════════
    // Scheduled messages (includes/workspace/scheduled_messages.php): written
    // in the box now, posted later; each person's own
    // ═══════════════════════════════════════════════════════════════════

    // The times offered beside the send button. Days come from the server's
    // date; "in an hour" is counted on the server's clock.
    function smPresets() {
        var tomorrow = addDays(CFG.today, 1);
        var monday = CFG.today;

        do {
            monday = addDays(monday, 1);
        } while (new Date(monday + 'T12:00:00').getDay() !== 1);

        var out = [
            { label: t('sm_in_hour'), icon: 'bi-hourglass-split', when: { in_minutes: 60 } },
            { label: t('sm_tomorrow_morning', '09:00'), icon: 'bi-sunrise', when: { date: tomorrow, time: '09:00' } }
        ];

        if (monday !== tomorrow) {
            out.push({ label: t('sm_next_monday', '09:00'), icon: 'bi-calendar-week', when: { date: monday, time: '09:00' } });
        }

        return out;
    }

    // A date and a time, for "Choose a time…" and for changing one.
    function smWhenFields(date, time) {
        var box = el('div', 'row g-3');
        var dateInput = el('input', 'form-control form-control-sm');
        var timeInput = el('input', 'form-control form-control-sm');
        var dateCol = el('div', 'col-7');
        var timeCol = el('div', 'col-5');
        var dateLabel = el('label', 'form-label small mb-1', t('sm_date'));
        var timeLabel = el('label', 'form-label small mb-1', t('sm_time'));

        dateInput.type = 'date';
        dateInput.id = nextId('ws-sm-date-');
        dateInput.min = CFG.today;
        dateInput.value = date || addDays(CFG.today, 1);
        timeInput.type = 'time';
        timeInput.id = nextId('ws-sm-time-');
        timeInput.value = time || '09:00';
        dateLabel.htmlFor = dateInput.id;
        timeLabel.htmlFor = timeInput.id;
        dateCol.appendChild(dateLabel);
        dateCol.appendChild(dateInput);
        timeCol.appendChild(timeLabel);
        timeCol.appendChild(timeInput);
        box.appendChild(dateCol);
        box.appendChild(timeCol);

        box.value = function () {
            return { date: dateInput.value, time: timeInput.value };
        };

        return box;
    }

    // Asks for a date and a time; resolves with { date, time } or null.
    function smAskWhen(date, time) {
        var fields = smWhenFields(date, time);

        return askClosed().then(function () {
            return ask(t('sm_custom_title'), t('sm_save'), false, fields);
        }).then(function (yes) {
            return yes ? fields.value() : null;
        });
    }

    // One scheduled message, as a card: where and when it posts, what it
    // says, and what can be done with it. onChange() is told after a change;
    // onEdit(item) (on the channel screen) takes its text into the box.
    function scheduledMessageCard(item, onChange, onEdit) {
        var card = el('div', 'ws-sm-card' + (item.status === 'failed' ? ' ws-sm-failed' : ''));
        var head = el('div', 'ws-sm-head');

        head.appendChild(icon(item.status === 'failed' ? 'bi-send-exclamation' : 'bi-clock', 'ws-sm-icon'));
        head.appendChild(el('b', '', item.status === 'failed' ? t('sm_failed') : t('sm_at', item.when)));

        if (item.channel) {
            var where = el('a', 'ws-sm-where', t('sm_in', item.channel.name));
            where.href = CFG.urls.workspace + '?channel=' + item.channel.id;
            head.appendChild(where);
        }

        card.appendChild(head);

        if (item.parent_id) {
            card.appendChild(el('div', 'ws-sm-reply small fg-secondary', t('sm_reply')));
        }

        var body = el('div', 'ws-sm-body ws-msg-body');
        setHtml(body, item.html);
        card.appendChild(body);

        if (item.error) {
            card.appendChild(el('div', 'small fg-danger mt-1', item.error));
        }

        var tools = el('div', 'ws-sm-tools');
        var sendNow = button('btn btn-sm btn-text theme-secondary', t('sm_send_now'), 'bi-send');
        sendNow.addEventListener('click', function () {
            sendNow.disabled = true;
            api('ws_scheduled_message_send', { id: item.id }).then(function () {
                toast(t('sm_sent'), 'success');
                onChange();
            }).catch(function (error) {
                sendNow.disabled = false;
                fail(error);
            });
        });
        tools.appendChild(sendNow);

        var move = button('btn btn-sm btn-text theme-secondary', t('sm_reschedule'), 'bi-calendar-event');
        move.addEventListener('click', function () {
            smAskWhen(item.date, item.time).then(function (when) {
                if (!when) {
                    return;
                }

                api('ws_scheduled_message_save', { id: item.id, body: item.raw, date: when.date, time: when.time }).then(function (data) {
                    toast(t('sm_saved', data.item ? data.item.when : ''), 'success');
                    onChange();
                }).catch(fail);
            });
        });
        tools.appendChild(move);

        if (onEdit) {
            var edit = button('btn btn-sm btn-text theme-secondary', t('sm_edit_text'), 'bi-pencil');
            edit.addEventListener('click', function () { onEdit(item); });
            tools.appendChild(edit);
        }

        var remove = button('btn btn-sm btn-text theme-secondary fg-danger', t('sm_delete'), 'bi-trash');
        remove.addEventListener('click', function () {
            ask(t('sm_delete_confirm'), t('sm_delete'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_scheduled_message_delete', { id: item.id }).then(function () {
                    toast(t('sm_deleted'), 'success');
                    onChange();
                }).catch(fail);
            });
        });
        tools.appendChild(remove);
        card.appendChild(tools);

        return card;
    }

    // The list of one's scheduled messages: of a channel (channelId), or of
    // every channel (0), into a box.
    function scheduledMessagesList(box, channelId, onChange, onEdit) {
        clear(box);
        box.appendChild(skeleton(2, 'ws-skeleton-sm'));

        api('ws_scheduled_messages', { channel_id: channelId || 0 }).then(function (data) {
            clear(box);

            if (!data.items.length) {
                var empty = el('div', 'ws-empty');
                empty.appendChild(icon('bi-clock'));
                empty.appendChild(document.createTextNode(t('sm_empty')));
                box.appendChild(empty);
            }

            data.items.forEach(function (item) {
                box.appendChild(scheduledMessageCard(item, function () {
                    scheduledMessagesList(box, channelId, onChange, onEdit);

                    if (onChange) {
                        onChange();
                    }
                }, onEdit));
            });
        }).catch(fail);
    }

    // ── Task rows (the tasks screen, a channel's task tab, drawers) ────

    function taskRows(container, tasks, onChange) {
        clear(container);

        if (!tasks.length) {
            container.appendChild(emptyState('tasks', t('no_tasks')));
            return;
        }

        tasks.forEach(function (task) {
            var row = el('div', 'ws-list-row' + (task.open ? '' : ' ws-row-done'));
            row.tabIndex = 0;
            row.setAttribute('role', 'button');
            row.appendChild(el('span', 'ws-status-dot ws-s-' + task.status));

            var main = el('div', 'ws-grow');
            var title = el('div', 'ws-row-title');
            title.appendChild(el('span', 'fg-secondary me-1', task.number));

            if (task.recurring) {
                var repeats = icon('bi-arrow-repeat', 'fg-secondary me-1');
                repeats.title = t('repeating');
                title.appendChild(repeats);
            }

            title.appendChild(document.createTextNode(task.title));
            main.appendChild(title);

            var sub = el('div', 'ws-row-sub');
            sub.appendChild(el('span', task.overdue ? 'fg-danger' : '', task.due_label));
            sub.appendChild(document.createTextNode(' · ' + task.status_label + (task.estimate_label ? ' · ' + task.estimate_label : '')));

            if (window.PGWsTaskTime) {
                window.PGWsTaskTime.rowTags(sub, task, partHelpers());
            }

            if (window.PGWsTaskLinks) {
                window.PGWsTaskLinks.rowTag(sub, task, partHelpers());
            }

            if (window.PGWsTaskDelegate) {
                window.PGWsTaskDelegate.rowTag(sub, task, partHelpers());
            }

            main.appendChild(sub);
            row.appendChild(main);

            if (task.progress) {
                var share = el('span', 'ws-row-progress');
                share.appendChild(progressBar(task.progress, true));
                share.appendChild(el('span', '', t('percent', task.progress.percent)));
                share.title = t('progress_text', task.progress.done, task.progress.total, task.progress.percent);
                row.appendChild(share);
            }

            if (task.notes_count) {
                var notes = el('span', 'ws-row-notes');
                notes.appendChild(icon('bi-journal-text', 'me-1'));
                notes.appendChild(document.createTextNode(String(task.notes_count)));
                notes.title = t('notes_count', task.notes_count);
                row.appendChild(notes);
            }

            if (task.priority === 'urgent' || task.priority === 'high') {
                row.appendChild(el('span', 'badge theme-danger', BOOT.priorities[task.priority]));
            }

            row.appendChild(avatarStack('', task.assignees, 4));

            if (task.can_edit && task.open) {
                var done = button('btn btn-sm btn-text theme-secondary', '', 'bi-check2-circle', t('mark_done'));
                done.addEventListener('click', function (event) {
                    event.stopPropagation();
                    api('ws_task_status', { task_id: task.id, status: 'done' }).then(function () {
                        toast(t('task_saved'), 'success');
                        onChange();
                    }).catch(fail);
                });
                row.appendChild(done);
            }

            function openTask() {
                taskDrawer.open(task.id, null, onChange);
            }

            row.addEventListener('click', openTask);
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    openTask();
                }
            });

            onContext(row, function () {
                var people = task.assignees.map(function (member) {
                    return { icon: 'bi-chat-dots', label: t('direct_message_to', member.name), action: function () { directMessage(member.id); } };
                }).filter(function (item, index) { return index < 3 && task.assignees[index].id !== BOOT.me.id; });

                return taskMenu(task, onChange, people.length ? ['-'].concat(people) : []);
            });

            container.appendChild(row);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Tasks screen
    // ═══════════════════════════════════════════════════════════════════

    // How pressing a task is, as the screens filter by it: every one, urgent
    // and high together, or one priority.
    function priorityFilter(value, onChange) {
        var labels = BOOT.priorities || {};
        var options = [['', t('all_priorities')], ['hot', t('priority_hot')]];

        ['urgent', 'high', 'normal', 'low'].forEach(function (key) {
            options.push([key, labels[key] || key]);
        });

        var node = select(options, value || '');
        node.classList.add('w-auto');
        node.setAttribute('aria-label', t('priority'));
        node.addEventListener('change', function () { onChange(node.value); });

        return node;
    }

    function priorityMatch(filter, priority) {
        if (!filter) {
            return true;
        }

        priority = priority || 'normal';

        return (filter === 'hot') ? (priority === 'urgent' || priority === 'high') : (priority === filter);
    }

    function startTasks(root) {
        clear(root);

        var state = { scope: 'mine', status: 'open', search: '', department_id: 0, priority: '', soon: 0 };
        var bar = el('nav', 'pg-toolbar navigation mb-5');
        var list = el('div', 'card');
        var listBody = el('div', 'card-body p-0');

        list.appendChild(listBody);
        root.appendChild(bar);
        root.appendChild(list);

        var add = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('new_task'), 'bi-plus-circle');
        add.addEventListener('click', function () { taskDrawer.open(0, {}, load); });
        bar.appendChild(add);

        var scopes = el('div', 'btn-group btn-group-sm');
        var scopeList = [['mine', t('scope_mine')], ['created', t('scope_created')], ['department', t('scope_department')]];

        if (BOOT.me.rights.board) {
            scopeList.push(['all', t('scope_all')]);
        }

        // The reader's own time, week by week (workspace_task_time.js).
        if (window.PGWsTaskTime && CFG.task_time && CFG.task_time.ready) {
            scopeList.push(['time', t('tt_my_time')]);
        }

        scopeList.forEach(function (scope) {
            var item = button('btn btn-text theme-secondary' + (state.scope === scope[0] ? ' active' : ''), scope[1]);
            item.addEventListener('click', function () {
                state.scope = scope[0];
                Array.prototype.forEach.call(scopes.children, function (child) { child.classList.remove('active'); });
                item.classList.add('active');
                load();
            });
            scopes.appendChild(item);
        });

        bar.appendChild(scopes);

        var status = select([['open', t('open_tasks')], ['closed', t('closed_tasks')], ['all', t('all_tasks')]], state.status);
        status.classList.add('w-auto');
        status.addEventListener('change', function () {
            state.status = status.value;
            load();
        });
        bar.appendChild(status);

        var dept = select(departmentOptions(t('all_departments')), 0);
        dept.classList.add('w-auto');
        dept.addEventListener('change', function () {
            state.department_id = parseInt(dept.value, 10) || 0;

            if (state.department_id) {
                state.scope = 'department';
            }

            load();
        });
        bar.appendChild(dept);

        var priority = priorityFilter('', function (value) {
            state.priority = value;
            load();
        });
        bar.appendChild(priority);

        // At a glance: the urgent and high ones due within a week, overdue
        // included.
        var soon = button('btn btn-sm btn-outline theme-danger rounded-pill', t('urgent_soon'), 'bi-fire');
        soon.setAttribute('aria-pressed', 'false');
        soon.title = t('urgent_soon_help');
        soon.addEventListener('click', function () {
            var on = !state.soon;

            state.soon = on ? 7 : 0;
            state.priority = on ? 'hot' : '';
            state.status = on ? 'open' : state.status;
            priority.value = state.priority;
            status.value = state.status;
            soon.classList.toggle('active', on);
            soon.setAttribute('aria-pressed', on ? 'true' : 'false');
            load();
        });
        bar.appendChild(soon);

        var grow = el('div', 'pg-toolbar-grow');
        bar.appendChild(grow);

        var searchGroup = el('div', 'input-group input-group-sm rounded-pill pg-toolbar-search');
        var search = el('input', 'form-control');
        search.type = 'search';
        search.placeholder = t('search_tasks');
        search.setAttribute('aria-label', search.placeholder);
        search.addEventListener('input', debounce(function () {
            state.search = search.value;
            load();
        }, 300));
        searchGroup.appendChild(search);
        bar.appendChild(searchGroup);

        // Only the answer to the latest question is drawn: a filter changed
        // quickly would otherwise let an earlier, slower answer win.
        var serial = 0;

        function load() {
            var mine = ++serial;

            if (state.scope === 'time') {
                window.PGWsTaskTime.myTime(listBody, partHelpers());
                return;
            }

            api('ws_tasks', state).then(function (data) {
                if (mine === serial) {
                    taskRows(listBody, data.tasks, load);
                }
            }).catch(fail);
        }

        load();

        var query = params();

        if (query.task) {
            taskDrawer.open(parseInt(query.task, 10), null, load);
        } else if (query['new']) {
            taskDrawer.open(0, {}, load);
        }

        // Back from connecting an account to an MCP server from a task's
        // drawer (includes/workspace/actions.php, ws_mcp_connect).
        if (CFG.mcp && CFG.mcp.flash) {
            toast(CFG.mcp.flash.text, (CFG.mcp.flash.kind === 'error') ? 'danger' : 'success');
        }

        // Linked from the overview: the pressing ones straight away.
        if (query.urgent) {
            soon.click();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Planning board
    // ═══════════════════════════════════════════════════════════════════

    function startBoard(root) {
        clear(root);

        var state = { from: CFG.board_from, days: 7, department_id: 0, only_me: false, person_id: parseInt(params().person || 0, 10) || 0, priority: '' };
        var lastData = null;
        var bar = el('nav', 'pg-toolbar navigation mb-5');
        var wrap = el('div', 'ws-board-wrap');
        var legend = el('div', 'd-flex flex-wrap gap-5 small fg-secondary mt-3');

        root.appendChild(bar);
        root.appendChild(wrap);
        root.appendChild(legend);

        var addEvent = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('add_plan_item'), 'bi-calendar-plus');
        addEvent.addEventListener('click', function () { eventForm(null, {}, load); });
        bar.appendChild(addEvent);

        var addTask = button('btn btn-sm btn-text theme-secondary', t('new_task'), 'bi-check2-square');
        addTask.addEventListener('click', function () { taskDrawer.open(0, {}, load); });
        bar.appendChild(addTask);

        var nav = el('div', 'btn-group btn-group-sm');
        var prev = button('btn btn-text theme-secondary', '', 'bi-chevron-left', t('previous'));
        var today = button('btn btn-text theme-secondary', t('today'));
        var next = button('btn btn-text theme-secondary', '', 'bi-chevron-right', t('next'));

        function shift(days) {
            var date = new Date(state.from + 'T12:00:00');
            date.setDate(date.getDate() + days);
            state.from = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            load();
        }

        prev.addEventListener('click', function () { shift(-state.days); });
        next.addEventListener('click', function () { shift(state.days); });
        today.addEventListener('click', function () {
            state.from = CFG.board_from;
            load();
        });

        nav.appendChild(prev);
        nav.appendChild(today);
        nav.appendChild(next);
        bar.appendChild(nav);

        var range = el('span', 'fw-semibold small');
        bar.appendChild(range);

        var span = el('div', 'btn-group btn-group-sm');

        [[7, t('one_week')], [14, t('two_weeks')]].forEach(function (option) {
            var item = button('btn btn-text theme-secondary' + (state.days === option[0] ? ' active' : ''), option[1]);
            item.addEventListener('click', function () {
                state.days = option[0];
                Array.prototype.forEach.call(span.children, function (child) { child.classList.remove('active'); });
                item.classList.add('active');
                load();
            });
            span.appendChild(item);
        });

        bar.appendChild(span);

        var dept = select(departmentOptions(t('all_departments')), 0);
        dept.classList.add('w-auto');
        dept.addEventListener('change', function () {
            state.department_id = parseInt(dept.value, 10) || 0;
            load();
        });
        bar.appendChild(dept);

        var meWrap = el('div', 'form-field mb-0');
        var me = el('input', '');
        me.type = 'checkbox';
        me.id = 'ws-board-only-me';
        var meLabel = el('label', 'small', t('only_me'));
        meLabel.htmlFor = me.id;
        me.addEventListener('change', function () {
            state.only_me = me.checked;
            load();
        });
        meWrap.appendChild(el('div', 'switch')).appendChild(me);
        meWrap.appendChild(meLabel);
        bar.appendChild(meWrap);

        // Only the tasks of a priority on the board; the day's load still
        // counts all of them.
        bar.appendChild(priorityFilter('', function (value) {
            state.priority = value;

            if (lastData) {
                draw(lastData);
            }
        }));

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var conflicts = button('btn btn-sm btn-outline theme-secondary', t('conflicts'), 'bi-exclamation-triangle');
        conflicts.addEventListener('click', showConflicts);
        bar.appendChild(conflicts);

        [['ws-swatch-ok', t('legend_ok')], ['ws-swatch-full', t('legend_full')], ['ws-swatch-over', t('legend_over')], ['ws-swatch-away', t('legend_away')]].forEach(function (item) {
            var entry = el('span', 'd-inline-flex align-items-center gap-1');
            entry.appendChild(el('span', 'ws-legend-swatch ' + item[0]));
            entry.appendChild(document.createTextNode(item[1]));
            legend.appendChild(entry);
        });

        var dragged = null;

        function load() {
            api('ws_board', state).then(function (data) {
                lastData = data;
                draw(data);
            }).catch(fail);
        }

        function draw(data) {
            var board = clear(wrap);
            var grid = el('div', 'ws-board');
            grid.style.gridTemplateColumns = 'var(--ws-board-person, 14rem) repeat(' + data.days.length + ', minmax(var(--ws-board-day, 7.5rem), 1fr))';
            board.appendChild(grid);

            range.textContent = data.days[0].label + ' – ' + data.days[data.days.length - 1].label;

            grid.appendChild(el('div', 'ws-board-head', t('person')));

            data.days.forEach(function (day) {
                grid.appendChild(el('div', 'ws-board-head' + (day.today ? ' ws-today' : '') + (day.weekend ? ' ws-weekend' : ''), day.label));
            });

            if (!data.rows.length) {
                var empty = el('div', 'ws-empty');
                empty.style.gridColumn = '1 / -1';
                empty.textContent = t('board_empty');
                grid.appendChild(empty);
            }

            data.rows.forEach(function (row) {
                var who = el('div', 'ws-board-person');
                who.appendChild(avatar(row.person));
                var text = el('div', 'ws-grow');
                text.appendChild(el('b', '', row.person.name));
                text.appendChild(el('small', '', t('day_capacity', Math.round(row.capacity.day_minutes / 60 * 10) / 10) + (row.unscheduled ? ' · ' + t('undated', row.unscheduled) : '')));

                // Staff see the time spent beside what was planned.
                if (row.time && window.PGWsTaskTime) {
                    text.appendChild(window.PGWsTaskTime.boardTag(row.time, partHelpers()));
                }

                who.appendChild(text);
                onContext(who, function () { return personMenu(row.person); });
                grid.appendChild(who);

                row.cells.forEach(function (cell, index) {
                    var day = data.days[index];
                    var away = cell.leave || cell.holiday;
                    var box = el('div', 'ws-board-cell' + (day.weekend ? ' ws-weekend' : '') + (away ? ' ws-cell-away' : ''));

                    cell.events.forEach(function (event) {
                        var item = button('ws-board-item ' + ((event.kind === 'leave' || event.kind === 'holiday') ? 'ws-item-leave' : 'ws-item-event'), (event.time ? event.time + ' ' : '') + event.title);
                        item.title = event.title;

                        if (event.meeting_url) {
                            item.insertBefore(icon('bi-camera-video', 'me-1'), item.firstChild);
                        }

                        var openEvent = function () {
                            api('ws_event_get', { event_id: event.id }).then(function (result) {
                                eventForm(result.event, {}, load);
                            }).catch(fail);
                        };

                        item.addEventListener('click', openEvent);
                        onContext(item, function () {
                            return [
                                { header: event.title },
                                event.meeting_url ? { icon: 'bi-camera-video', label: t('meeting_join'), action: function () { joinMeeting(event.meeting_url); } } : null,
                                { icon: 'bi-pencil', label: t('edit'), action: openEvent },
                                { icon: 'bi-trash', label: t('delete'), danger: true, action: function () {
                                    ask(t('delete_item_confirm'), t('delete'), true).then(function (yes) {
                                        if (yes) {
                                            api('ws_event_delete', { event_id: event.id }).then(load).catch(fail);
                                        }
                                    });
                                } }
                            ].filter(Boolean);
                        });
                        box.appendChild(item);
                    });

                    cell.tasks.forEach(function (task) {
                        if (!priorityMatch(state.priority, task.priority)) {
                            return;
                        }

                        // A copy of a repeating task that is not handed out
                        // yet: shown where it will run, not moved from here.
                        if (task.upcoming) {
                            var coming = button('ws-board-item ws-item-upcoming' + (task.due ? ' ws-item-due' : '') + (task.waits_for ? ' ws-item-waits' : ''), task.title, 'bi-arrow-repeat');
                            coming.title = t('upcoming_copy', dmy(task.due_date)) + (task.moved_from ? ' (' + t('moved_from', dmy(task.moved_from)) + ')' : '') + ' · ' + task.repeat + ' · ' + t('minutes_short', task.minutes) + '\n' + (task.waits_for ? t('upcoming_waits', task.waits_for) + '\n' : '') + t('upcoming_hint');
                            coming.addEventListener('click', function () { taskDrawer.open(task.id, null, load); });
                            onContext(coming, function () {
                                return [
                                    { header: task.title },
                                    { icon: 'bi-box-arrow-up-right', label: t('open_task'), action: function () { taskDrawer.open(task.id, null, load); } }
                                ];
                            });
                            box.appendChild(coming);

                            return;
                        }

                        var item = button('ws-board-item' + (task.due ? ' ws-item-due' : '') + (task.priority === 'urgent' ? ' ws-item-urgent' : ''), task.title);
                        item.title = task.title + ' · ' + t('minutes_short', task.minutes) + (task.estimated ? '' : ' · ' + t('not_estimated'));

                        if (task.progress) {
                            item.classList.add('ws-item-progress');
                            item.style.setProperty('--ws-progress', task.progress.percent + '%');
                            item.title += ' · ' + t('progress_text', task.progress.done, task.progress.total, task.progress.percent);
                        }
                        item.draggable = true;
                        item.addEventListener('dragstart', function (event) {
                            dragged = { task_id: task.id, from_user_id: row.person.id, latest: !!task.series_latest, due_date: task.due_date || '' };
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', String(task.id));
                        });
                        item.addEventListener('click', function () { taskDrawer.open(task.id, null, load); });
                        onContext(item, function () {
                            var others = data.rows.filter(function (other) { return other.person.id !== row.person.id; }).slice(0, 8).map(function (other) {
                                return { icon: 'bi-arrow-left-right', label: t('hand_to', other.person.name), action: function () {
                                    moveTask({ task_id: task.id, date: task.due_date || day.date, from_user_id: row.person.id, to_user_id: other.person.id }, false, load);
                                } };
                            });

                            return taskMenu({ id: task.id, title: task.title, status: task.status, due_date: task.due_date || '' }, load, others.length ? ['-'].concat(others) : []);
                        });
                        box.appendChild(item);
                    });

                    if (cell.capacity > 0 || cell.minutes > 0) {
                        var level = cell.load > 100 ? ' ws-bar-over' : (cell.load >= 80 ? ' ws-bar-full' : '');
                        var bar = el('div', 'ws-board-bar' + level);
                        var fill = el('span');
                        fill.style.width = Math.min(100, cell.load) + '%';
                        bar.appendChild(fill);
                        box.appendChild(bar);
                        box.appendChild(el('span', 'ws-board-pct', cell.load > 998 ? '!' : t('percent', cell.load)));
                        box.title = t('cell_title', Math.round(cell.minutes / 6) / 10, Math.round(cell.capacity / 6) / 10);
                    } else if (away) {
                        box.appendChild(el('span', 'ws-board-pct', cell.holiday ? t('holiday') : t('away')));
                    }

                    var plus = button('ws-board-add', '', 'bi-plus', t('add_here'));
                    plus.addEventListener('click', function (event) {
                        event.stopPropagation();
                        addHere(row.person, day.date, plus);
                    });
                    box.appendChild(plus);

                    onContext(box, function () {
                        return [
                            { header: row.person.name + ' · ' + day.label },
                            { icon: 'bi-check2-square', label: t('new_task_here'), action: function () {
                                taskDrawer.open(0, { assignees: [row.person.id], due_date: day.date }, load);
                            } },
                            { icon: 'bi-calendar-plus', label: t('add_plan_item'), action: function () {
                                eventForm(null, { people: [row.person.id], start: day.date }, load);
                            } },
                            { icon: 'bi-airplane', label: t('mark_leave_day'), action: function () {
                                api('ws_event_save', { kind: 'leave', scope: 'people', people: [row.person.id], start: day.date, end: day.date, all_day: 1 }).then(function () {
                                    toast(t('plan_saved'), 'success');
                                    load();
                                }).catch(fail);
                            } },
                            '-'
                        ].concat(personMenu(row.person).slice(1));
                    });

                    box.addEventListener('dragover', function (event) {
                        if (dragged) {
                            event.preventDefault();
                            box.classList.add('ws-drop');
                        }
                    });
                    box.addEventListener('dragleave', function () { box.classList.remove('ws-drop'); });
                    box.addEventListener('drop', function (event) {
                        event.preventDefault();
                        box.classList.remove('ws-drop');

                        if (!dragged) {
                            return;
                        }

                        var move = { task_id: dragged.task_id, date: day.date, from_user_id: dragged.from_user_id, to_user_id: row.person.id };
                        var repeating = dragged.latest && (dragged.due_date !== day.date);
                        dragged = null;

                        if (!repeating) {
                            moveTask(move, false);
                            return;
                        }

                        askSeries().then(function (series) {
                            if (series) {
                                move.series = series;
                                moveTask(move, false);
                            }
                        });
                    });

                    grid.appendChild(box);
                });
            });

            // On a narrow screen the week scrolls sideways: start at today.
            var today = grid.querySelector('.ws-board-head.ws-today');

            if (today && (board.scrollWidth > board.clientWidth)) {
                var nameColumn = grid.firstChild ? grid.firstChild.offsetWidth : 0;
                board.scrollLeft = Math.max(0, today.getBoundingClientRect().left - board.getBoundingClientRect().left + board.scrollLeft - nameColumn);
            }
        }

        function moveTask(move, force) {
            move.force = force ? 1 : 0;

            api('ws_task_move', move).then(function (result) {
                if (result.needs_confirm) {
                    if (result.check.hard && !result.check.may_override) {
                        ask(t('leave_blocks'), t('ok'), false, warningList(result.check));
                        return;
                    }

                    ask(t('move_despite'), t('move_anyway'), true, warningList(result.check)).then(function (yes) {
                        if (yes) {
                            moveTask(move, true);
                        }
                    });
                    return;
                }

                load();
            }).catch(fail);
        }

        function addHere(member, date, anchor) {
            var menu = el('div', 'menu show');
            menu.style.position = 'absolute';
            menu.style.zIndex = 30;
            menu.style.top = '1.6rem';
            menu.style.right = '0';

            var task = button('menu-item pg-link-body-emphasis', t('new_task'), 'bi-check2-square');
            task.addEventListener('click', function () {
                menu.remove();
                taskDrawer.open(0, { assignees: [member.id], due_date: date }, load);
            });

            var item = button('menu-item pg-link-body-emphasis', t('add_plan_item'), 'bi-calendar-plus');
            item.addEventListener('click', function () {
                menu.remove();
                eventForm(null, { people: [member.id], start: date }, load);
            });

            menu.appendChild(task);
            menu.appendChild(item);
            anchor.parentNode.appendChild(menu);

            setTimeout(function () {
                document.addEventListener('click', function close(event) {
                    if (!menu.contains(event.target)) {
                        menu.remove();
                        document.removeEventListener('click', close);
                    }
                });
            }, 0);
        }

        function showConflicts() {
            var node = offcanvas('ws-conflicts', t('conflicts'));
            var body = clear(node.querySelector('.drawer-body'));
            node.querySelector('.drawer-footer').classList.add('d-none');
            body.appendChild(el('p', 'small fg-secondary', t('conflicts_help')));
            showOffcanvas(node);

            var to = new Date(state.from + 'T12:00:00');
            to.setDate(to.getDate() + 13);

            api('ws_conflicts', { from: state.from, to: to.toISOString().slice(0, 10) }).then(function (data) {
                if (!data.conflicts.length) {
                    var empty = el('div', 'ws-empty');
                    empty.appendChild(icon('bi-emoji-smile'));
                    empty.appendChild(document.createTextNode(t('no_conflicts')));
                    body.appendChild(empty);
                    return;
                }

                data.conflicts.forEach(function (item) {
                    var row = el('div', 'ws-conflict');
                    row.appendChild(el('span', 'ws-load ' + (item.type === 'leave' ? 'ws-load-away' : 'ws-load-over'), item.type === 'leave' ? t('away') : t('percent', item.load)));
                    var text = el('div');
                    text.appendChild(el('div', 'fw-semibold', item.name + ' · ' + item.day));
                    text.appendChild(el('small', 'fg-secondary', item.tasks.map(function (task) { return task.title; }).join(', ')));
                    row.appendChild(text);
                    body.appendChild(row);
                });
            }).catch(fail);
        }

        load();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Month calendar
    // ═══════════════════════════════════════════════════════════════════

    function startCalendar(root) {
        clear(root);

        var query = params();
        var state = {
            month: /^\d{4}-\d{2}$/.test(query.month || '') ? query.month : '',
            person_id: parseInt(query.person || 0, 10) || 0,
            department_id: 0,
            show: { tasks: true, talk: true, events: true },
            priority: '',
            status: 'all'
        };
        var data = null;

        // The tasks the filters let through: priority and open / closed.
        function taskShown(task) {
            if (!priorityMatch(state.priority, task.priority)) {
                return false;
            }

            if (state.status === 'open') {
                return !!task.open;
            }

            if (state.status === 'closed') {
                return !task.open;
            }

            return true;
        }

        var bar = el('nav', 'pg-toolbar navigation mb-5 ws-cal-toolbar');
        var layout = el('div', 'row g-7');
        var mainCol = el('div', 'xl:col-9');
        var sideCol = el('div', 'xl:col-3');
        var calCard = el('div', 'ws-cal card');
        var side = el('div', 'ws-cal-side');

        mainCol.appendChild(calCard);
        sideCol.appendChild(side);
        layout.appendChild(mainCol);
        layout.appendChild(sideCol);
        root.appendChild(bar);
        root.appendChild(layout);

        // ── toolbar ──

        var nav = el('div', 'btn-group btn-group-sm');
        var prev = button('btn btn-text theme-secondary', '', 'bi-chevron-left', t('previous_month'));
        var now = button('btn btn-text theme-secondary', t('this_month'));
        var next = button('btn btn-text theme-secondary', '', 'bi-chevron-right', t('next_month'));

        prev.addEventListener('click', function () { if (data) { state.month = data.prev; load(); } });
        next.addEventListener('click', function () { if (data) { state.month = data.next; load(); } });
        now.addEventListener('click', function () { state.month = ''; load(); });
        nav.appendChild(prev);
        nav.appendChild(now);
        nav.appendChild(next);
        bar.appendChild(nav);

        var title = el('h2', 'ws-cal-title');
        bar.appendChild(title);

        var personSelect = select([[0, t('everyone')]].concat(BOOT.people.map(function (member) { return [member.id, member.name]; })), state.person_id);
        personSelect.classList.add('w-auto');
        personSelect.setAttribute('aria-label', t('person'));
        personSelect.addEventListener('change', function () {
            state.person_id = parseInt(personSelect.value, 10) || 0;
            load();
        });
        bar.appendChild(personSelect);

        var dept = select(departmentOptions(t('all_departments')), 0);
        dept.classList.add('w-auto');
        dept.addEventListener('change', function () {
            state.department_id = parseInt(dept.value, 10) || 0;
            load();
        });
        bar.appendChild(dept);

        bar.appendChild(priorityFilter('', function (value) {
            state.priority = value;
            draw();
        }));

        var statusFilter = select([['all', t('all_tasks')], ['open', t('open_tasks')], ['closed', t('closed_tasks')]], 'all');
        statusFilter.classList.add('w-auto');
        statusFilter.addEventListener('change', function () {
            state.status = statusFilter.value;
            draw();
        });
        bar.appendChild(statusFilter);

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var shows = el('div', 'btn-group btn-group-sm');
        shows.setAttribute('role', 'group');

        [['tasks', t('tasks'), 'bi-check2-square'], ['talk', t('conversations'), 'bi-chat-dots'], ['events', t('plan_items'), 'bi-calendar-event']].forEach(function (option) {
            var toggle = button('btn btn-text theme-secondary active', option[1], option[2]);
            toggle.setAttribute('aria-pressed', 'true');
            toggle.addEventListener('click', function () {
                state.show[option[0]] = !state.show[option[0]];
                toggle.classList.toggle('active', state.show[option[0]]);
                toggle.setAttribute('aria-pressed', state.show[option[0]] ? 'true' : 'false');
                draw();
            });
            shows.appendChild(toggle);
        });

        bar.appendChild(shows);

        // ── loading and drawing ──

        function load() {
            api('ws_calendar', { month: state.month, person_id: state.person_id, department_id: state.department_id }).then(function (result) {
                data = result;
                state.month = result.month;
                state.person_id = result.person_id || 0;
                personSelect.value = String(state.person_id);

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', CFG.urls.calendar + '?month=' + result.month + (state.person_id ? '&person=' + state.person_id : ''));
                }

                draw();
                drawSide();
            }).catch(fail);
        }

        function peopleNames(ids) {
            return (ids || []).map(function (id) {
                var member = person(id);
                return member ? member.name : '';
            }).filter(Boolean).join(', ');
        }

        // What a coming copy of a repeating task says about itself.
        function comingText(task) {
            return t('upcoming_copy', dmy(task.due_date)) + (task.moved_from ? ' (' + t('moved_from', dmy(task.moved_from)) + ')' : '') + ' · ' + task.repeat
                + (task.waits_for ? ' · ' + t('upcoming_waits', task.waits_for) : '');
        }

        // seg: one day of a task that covers several - whether it goes on
        // into the day before and the day after in the same row, and whether
        // this piece carries the title (the first day, and the first day of
        // every week row it reaches).
        function taskChip(task, seg) {
            var chip = el('button', 'ws-cal-task' + (task.upcoming ? ' ws-cal-upcoming' : '') + (task.waits_for ? ' ws-cal-waits' : '') + (task.overdue ? ' ws-cal-late' : '') + (task.open ? '' : ' ws-cal-closed') + ((task.priority === 'urgent' || task.priority === 'high') ? ' ws-cal-hot' : '')
                + (seg ? ' ws-cal-span' + (seg.left ? ' ws-cal-span-l' : '') + (seg.right ? ' ws-cal-span-r' : '') + (seg.label ? '' : ' ws-cal-span-rest') : ''));
            chip.type = 'button';

            if (seg && !seg.label) {
                chip.appendChild(el('span', 'ws-cal-task-title visually-hidden', task.title));
            } else {
                chip.appendChild(task.upcoming ? icon('bi-arrow-repeat') : el('span', 'ws-status-dot ws-s-' + task.status));
                chip.appendChild(el('span', 'ws-cal-task-title', task.title));

                chip.appendChild(avatarStack('ws-avatars-sm', (task.people || []).map(person).filter(Boolean), 3));
            }

            chip.title = task.upcoming
                ? comingText(task) + ' · ' + task.title + (task.people && task.people.length ? ' · ' + peopleNames(task.people) : '') + '\n' + t('upcoming_hint')
                : task.number + ' · ' + task.title + (task.people && task.people.length ? ' · ' + peopleNames(task.people) : '') + ' · ' + BOOT.statuses[task.status]
                    + (task.span_start ? '\n' + t('task_span', dmy(task.span_start), dmy(task.span_end)) : '');
            chip.addEventListener('click', function (event) {
                event.stopPropagation();
                taskDrawer.open(task.id, null, load);
            });
            onContext(chip, function () { return comingMenu(task) || taskMenu(task, load); });

            return chip;
        }

        // A coming copy is not a task yet: the only thing to do with it is
        // to open the task it repeats.
        function comingMenu(task) {
            if (!task.upcoming) {
                return null;
            }

            return [
                { header: task.title },
                { icon: 'bi-box-arrow-up-right', label: t('open_task'), action: function () { taskDrawer.open(task.id, null, load); } }
            ];
        }

        function draw() {
            if (!data) {
                return;
            }

            title.textContent = data.label;

            var box = clear(calCard);
            var head = el('div', 'ws-cal-head');

            data.weekdays.forEach(function (name, index) {
                head.appendChild(el('div', index >= 5 ? 'ws-cal-weekend' : '', name));
            });

            box.appendChild(head);

            var grid = el('div', 'ws-cal-grid');

            data.weeks.forEach(function (week) {
                // The tasks that run across days, each given a lane of its
                // own for the week so its pieces line up into one bar.
                var weekFrom = week[0].date;
                var weekTo = week[week.length - 1].date;
                var lanes = [];

                if (state.show.tasks) {
                    (data.spans || []).filter(function (task) {
                        return task.span_start <= weekTo && task.span_end >= weekFrom && taskShown(task);
                    }).sort(function (a, b) {
                        return a.span_start < b.span_start ? -1 : (a.span_start > b.span_start ? 1 : (a.span_end > b.span_end ? -1 : (a.span_end < b.span_end ? 1 : a.id - b.id)));
                    }).forEach(function (task) {
                        var from = task.span_start < weekFrom ? weekFrom : task.span_start;
                        var lane = 0;

                        while (lane < lanes.length && lanes[lane].end >= from) {
                            lane++;
                        }

                        if (lane === lanes.length) {
                            lanes.push({ end: '', tasks: [] });
                        }

                        lanes[lane].end = task.span_end;
                        lanes[lane].tasks.push(task);
                    });
                }

                week.forEach(function (day, column) {
                    var cell = el('div', 'ws-cal-day' + (day.in_month ? '' : ' ws-cal-out') + (day.today ? ' ws-cal-today' : '') + (day.weekend ? ' ws-cal-weekend' : ''));
                    cell.tabIndex = 0;
                    cell.setAttribute('role', 'button');
                    cell.setAttribute('aria-label', day.label);

                    var top = el('div', 'ws-cal-day-top');
                    top.appendChild(el('span', 'ws-cal-num', day.day));

                    if (state.show.talk && day.messages > 0) {
                        var talk = el('span', 'ws-cal-talk');
                        talk.appendChild(icon('bi-chat-dots'));
                        talk.appendChild(document.createTextNode(' ' + day.messages));
                        talk.title = day.talk.map(function (item) { return '#' + item.name + ': ' + item.count; }).join('\n');
                        top.appendChild(talk);
                    }

                    cell.appendChild(top);

                    var shown = 0;
                    var limit = 4;
                    var hidden = 0;

                    // Lanes first, an empty gap where a lane has nothing on
                    // this day, so a bar keeps its height across the row.
                    var used = 0;

                    lanes.forEach(function (lane, index) {
                        if (lane.tasks.some(function (task) { return task.span_start <= day.date && task.span_end >= day.date; })) {
                            used = index + 1;
                        }
                    });

                    lanes.slice(0, used).forEach(function (lane) {
                        var task = null;

                        lane.tasks.forEach(function (item) {
                            if (item.span_start <= day.date && item.span_end >= day.date) {
                                task = item;
                            }
                        });

                        if (shown >= limit) {
                            if (task) {
                                hidden++;
                            }

                            return;
                        }

                        if (task) {
                            cell.appendChild(taskChip(task, {
                                left: column > 0 && task.span_start < day.date,
                                right: column < week.length - 1 && task.span_end > day.date,
                                label: task.span_start === day.date || column === 0
                            }));
                        } else {
                            cell.appendChild(el('div', 'ws-cal-lane-gap'));
                        }

                        shown++;
                    });

                    if (state.show.events) {
                        day.events.forEach(function (event) {
                            if (shown >= limit) {
                                hidden++;
                                return;
                            }

                            var item = el('div', 'ws-cal-event ws-kind-' + event.kind, (event.time ? event.time + ' ' : '') + event.title);
                            item.title = event.title + (event.people && event.people.length ? ' · ' + peopleNames(event.people) : '');

                            if (event.meeting_url) {
                                item.insertBefore(icon('bi-camera-video', 'me-1'), item.firstChild);
                            }

                            cell.appendChild(item);
                            shown++;
                        });
                    }

                    if (state.show.tasks) {
                        day.tasks.filter(taskShown).forEach(function (task) {
                            if (shown >= limit) {
                                hidden++;
                                return;
                            }

                            cell.appendChild(taskChip(task));
                            shown++;
                        });
                    }

                    if (state.show.talk) {
                        day.channels.forEach(function (channel) {
                            if (shown >= limit) {
                                hidden++;
                                return;
                            }

                            var item = el('a', 'ws-cal-channel');
                            item.href = CFG.urls.workspace + '?channel=' + channel.id;
                            item.appendChild(icon(channel['private'] ? 'bi-lock' : 'bi-plus-circle'));
                            item.appendChild(document.createTextNode(' #' + channel.name));
                            item.title = t('channel_opened_by', channel.name, channel.by);
                            item.addEventListener('click', function (event) { event.stopPropagation(); });
                            cell.appendChild(item);
                            shown++;
                        });
                    }

                    if (hidden > 0) {
                        cell.appendChild(el('div', 'ws-cal-more', t('more_count', hidden)));
                    }

                    cell.addEventListener('click', function () { openDay(day); });
                    cell.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            openDay(day);
                        }
                    });
                    onContext(cell, function () { return dayMenu(day); });

                    grid.appendChild(cell);
                });
            });

            box.appendChild(grid);
        }

        function forWhom() {
            return state.person_id ? [state.person_id] : [BOOT.me.id];
        }

        function dayMenu(day) {
            return [
                { header: day.label },
                { icon: 'bi-list-ul', label: t('show_day'), action: function () { openDay(day); } },
                '-',
                { icon: 'bi-check2-square', label: t('new_task_here'), action: function () { taskDrawer.open(0, { due_date: day.date, assignees: forWhom() }, load); } },
                { icon: 'bi-calendar-plus', label: t('add_plan_item'), action: function () { eventForm(null, { start: day.date, people: forWhom() }, load); } },
                { icon: 'bi-airplane', label: t('mark_leave_day'), action: function () {
                    api('ws_event_save', { kind: 'leave', scope: 'people', people: forWhom(), start: day.date, end: day.date, all_day: 1 }).then(function () {
                        toast(t('plan_saved'), 'success');
                        load();
                    }).catch(fail);
                } },
                '-',
                { icon: 'bi-calendar-week', label: t('open_board_week'), action: function () { window.location.href = CFG.urls.board + (state.person_id ? '?person=' + state.person_id : ''); } }
            ];
        }

        function openDay(day) {
            var node = offcanvas('ws-day', '');
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));

            node.querySelector('.drawer-title').textContent = day.label;
            footer.classList.remove('d-none');

            var addEvent = button('btn btn-sm btn-text theme-secondary', t('add_plan_item'), 'bi-calendar-plus');
            addEvent.addEventListener('click', function () {
                hideOffcanvas(node);
                eventForm(null, { start: day.date, people: forWhom() }, load);
            });

            var addTask = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('new_task'), 'bi-check2-square');
            addTask.addEventListener('click', function () {
                hideOffcanvas(node);
                taskDrawer.open(0, { due_date: day.date, assignees: forWhom() }, load);
            });

            footer.appendChild(addEvent);
            footer.appendChild(addTask);

            function section(label, iconName) {
                var box = el('div', 'ws-record-section');
                var head = el('div', 'ws-record-title');
                head.appendChild(icon(iconName, 'me-1'));
                head.appendChild(document.createTextNode(label));
                box.appendChild(head);
                body.appendChild(box);
                return box;
            }

            var dayTasks = (data.spans || []).filter(function (task) {
                return task.span_start <= day.date && task.span_end >= day.date;
            }).concat(day.tasks).filter(taskShown);
            var tasks = section(t('tasks') + ' · ' + dayTasks.length, 'bi-check2-square');

            if (!dayTasks.length) {
                tasks.appendChild(el('div', 'small fg-secondary', t('no_tasks')));
            }

            dayTasks.forEach(function (task) {
                var row = el('div', 'ws-list-row' + (task.open ? '' : ' ws-row-done') + (task.upcoming ? ' ws-row-upcoming' : ''));
                row.appendChild(task.upcoming ? icon('bi-arrow-repeat', 'fg-secondary') : el('span', 'ws-status-dot ws-s-' + task.status));

                var main = el('div', 'ws-grow');
                var line = el('div', 'ws-row-title');

                if (!task.upcoming) {
                    line.appendChild(el('span', 'fg-secondary me-1', task.number));
                }

                line.appendChild(document.createTextNode(task.title));
                main.appendChild(line);
                main.appendChild(el('div', 'ws-row-sub' + (task.overdue ? ' fg-danger' : ''), (task.upcoming ? comingText(task) : BOOT.statuses[task.status]) + (task.span_start ? ' · ' + t('task_span', dmy(task.span_start), dmy(task.span_end)) : '') + (task.people.length ? ' · ' + peopleNames(task.people) : '')));
                row.appendChild(main);

                row.addEventListener('click', function () {
                    hideOffcanvas(node);
                    taskDrawer.open(task.id, null, load);
                });
                onContext(row, function () { return comingMenu(task) || taskMenu(task, load); });
                tasks.appendChild(row);
            });

            if (day.events.length) {
                var events = section(t('plan_items') + ' · ' + day.events.length, 'bi-calendar-event');

                day.events.forEach(function (event) {
                    var row = el('div', 'ws-list-row');
                    row.appendChild(el('span', 'ws-cal-event-dot ws-kind-' + event.kind));

                    var main = el('div', 'ws-grow');
                    main.appendChild(el('div', 'ws-row-title', (event.time ? event.time + ' · ' : '') + event.title));
                    main.appendChild(el('div', 'ws-row-sub', (CFG.event_kinds[event.kind] || '') + (event.people.length ? ' · ' + peopleNames(event.people) : '')));
                    row.appendChild(main);

                    if (event.meeting_url) {
                        var join = el('a', 'btn btn-sm btn-outline theme-primary rounded-pill text-nowrap', t('meeting_join'));
                        join.href = event.meeting_url;
                        join.target = '_blank';
                        join.rel = 'noopener noreferrer';
                        join.insertBefore(icon('bi-camera-video', 'me-1'), join.firstChild);
                        join.addEventListener('click', function (clicked) { clicked.stopPropagation(); });
                        row.appendChild(join);
                    }

                    row.addEventListener('click', function () {
                        api('ws_event_get', { event_id: event.id }).then(function (result) {
                            hideOffcanvas(node);
                            eventForm(result.event, {}, load);
                        }).catch(fail);
                    });
                    events.appendChild(row);
                });
            }

            if (day.channels.length || day.talk.length) {
                var talk = section(t('conversations') + ' · ' + day.messages, 'bi-chat-dots');

                day.channels.forEach(function (channel) {
                    var link = el('a', 'ws-record-item');
                    link.href = CFG.urls.workspace + '?channel=' + channel.id;
                    link.appendChild(icon('bi-plus-circle', 'me-1'));
                    link.appendChild(document.createTextNode(t('channel_opened_by', channel.name, channel.by)));
                    talk.appendChild(link);
                });

                day.talk.forEach(function (item) {
                    var link = el('a', 'ws-record-item d-flex align-items-center gap-3');
                    link.href = CFG.urls.workspace + '?channel=' + item.id;
                    link.appendChild(icon(item['private'] ? 'bi-lock' : 'bi-hash'));
                    link.appendChild(el('span', 'ws-grow', item.name));
                    link.appendChild(el('span', 'badge rounded-pill theme-secondary', t('messages_count', item.count)));
                    talk.appendChild(link);
                });
            }

            showOffcanvas(node);
        }

        function drawSide() {
            var box = clear(side);
            var totals = data.totals;

            // The month in figures.
            var month = el('div', 'card mb-5');
            var monthBody = el('div', 'card-body');
            monthBody.appendChild(el('h3', 'ws-side-card-title', t('month_in_figures')));

            var figures = el('div', 'ws-figures');

            [[totals.tasks, t('fig_tasks'), ''], [totals.done, t('fig_done'), 'ws-fig-ok'], [totals.open, t('fig_open'), ''], [totals.overdue, t('fig_overdue'), totals.overdue ? 'ws-fig-bad' : ''], [totals.channels, t('fig_channels'), ''], [totals.messages, t('fig_messages'), '']].forEach(function (item) {
                var cell = el('div', 'ws-figure ' + item[2]);
                cell.appendChild(el('b', '', String(item[0])));
                cell.appendChild(el('span', '', item[1]));
                figures.appendChild(cell);
            });

            monthBody.appendChild(figures);

            if (data.busiest) {
                monthBody.appendChild(el('div', 'small fg-secondary mt-3', t('busiest_day', data.busiest.label, data.busiest.tasks, data.busiest.messages)));
            }

            if (totals.unassigned) {
                monthBody.appendChild(el('div', 'small fg-emphasis-warning mt-1', t('unassigned_count', totals.unassigned)));
            }

            if (totals.upcoming) {
                monthBody.appendChild(el('div', 'small fg-secondary mt-1', t('upcoming_count', totals.upcoming)));
            }

            month.appendChild(monthBody);
            box.appendChild(month);

            // Who carries the most, who the least.
            if (data.most || data.least) {
                var extremes = el('div', 'card mb-5');
                var extremesBody = el('div', 'card-body');
                extremesBody.appendChild(el('h3', 'ws-side-card-title', t('who_carries')));

                [[data.most, t('most_tasks'), 'bi-graph-up-arrow', 'ws-extreme-most'], [data.least, t('least_tasks'), 'bi-graph-down-arrow', 'ws-extreme-least']].forEach(function (item) {
                    if (!item[0]) {
                        return;
                    }

                    var row = el('button', 'ws-extreme ' + item[3]);
                    row.type = 'button';
                    row.appendChild(icon(item[2], 'ws-extreme-icon'));
                    row.appendChild(avatar(item[0].person));

                    var text = el('div', 'ws-grow');
                    text.appendChild(el('small', '', item[1]));
                    text.appendChild(el('b', '', item[0].person.name));
                    row.appendChild(text);
                    row.appendChild(el('span', 'ws-extreme-count', t('task_count', item[0].total)));
                    row.addEventListener('click', function () {
                        state.person_id = item[0].person.id;
                        load();
                    });
                    onContext(row, function () { return personMenu(item[0].person); });
                    extremesBody.appendChild(row);
                });

                extremes.appendChild(extremesBody);
                box.appendChild(extremes);
            }

            // Everyone in view, with a bar.
            var people = el('div', 'card mb-5');
            var peopleBody = el('div', 'card-body');
            var peopleHead = el('div', 'd-flex align-items-center mb-3');
            peopleHead.appendChild(el('h3', 'ws-side-card-title ws-grow mb-0', t('people_this_month')));

            if (state.person_id) {
                var all = button('btn btn-sm btn-text theme-secondary py-0', t('everyone'), 'bi-x');
                all.addEventListener('click', function () {
                    state.person_id = 0;
                    load();
                });
                peopleHead.appendChild(all);
            }

            peopleBody.appendChild(peopleHead);

            var max = 1;

            data.people.forEach(function (item) { max = Math.max(max, item.total); });

            if (!data.people.length) {
                peopleBody.appendChild(el('div', 'small fg-secondary', t('board_empty')));
            }

            data.people.forEach(function (item) {
                var row = el('button', 'ws-person-row' + (state.person_id === item.person.id ? ' active' : ''));
                row.type = 'button';
                row.appendChild(avatar(item.person));

                var text = el('div', 'ws-grow');
                var line = el('div', 'd-flex gap-3');
                line.appendChild(el('span', 'ws-grow text-truncate', item.person.name));
                line.appendChild(el('b', '', String(item.total)));
                text.appendChild(line);

                var meter = el('div', 'ws-meter');
                var done = el('span', 'ws-meter-done');
                var open = el('span', 'ws-meter-open');
                done.style.width = (item.done / max * 100) + '%';
                open.style.width = ((item.total - item.done) / max * 100) + '%';
                meter.appendChild(done);
                meter.appendChild(open);
                text.appendChild(meter);
                text.appendChild(el('small', 'fg-secondary', t('person_figures', item.done, item.open, item.overdue, item.minutes_label)));
                row.appendChild(text);

                row.addEventListener('click', function () {
                    state.person_id = (state.person_id === item.person.id) ? 0 : item.person.id;
                    load();
                });
                onContext(row, function () { return personMenu(item.person); });
                peopleBody.appendChild(row);
            });

            people.appendChild(peopleBody);
            box.appendChild(people);

            // The channels that talked the most.
            if (data.top_channels.length) {
                var channels = el('div', 'card');
                var channelsBody = el('div', 'card-body');
                channelsBody.appendChild(el('h3', 'ws-side-card-title', t('busiest_channels')));

                data.top_channels.forEach(function (item) {
                    var link = el('a', 'ws-record-item d-flex align-items-center gap-3');
                    link.href = CFG.urls.workspace + '?channel=' + item.id;
                    link.appendChild(icon(item['private'] ? 'bi-lock' : 'bi-hash'));
                    link.appendChild(el('span', 'ws-grow text-truncate', item.name));
                    link.appendChild(el('span', 'badge rounded-pill theme-secondary', String(item.count)));
                    channelsBody.appendChild(link);
                });

                channels.appendChild(channelsBody);
                box.appendChild(channels);
            }
        }

        load();

        // Opened from a meeting's notice (?event=): the item, with its Join.
        if (parseInt(query.event || 0, 10) > 0) {
            api('ws_event_get', { event_id: parseInt(query.event, 10) }).then(function (result) {
                eventForm(result.event, {}, load);
            }).catch(fail);
        }
    }

    // A Jitsi room nobody can guess: a few letters of the title and a
    // random tail. meet.jit.si opens it on first visit; nothing is set up.
    function jitsiRoom(title) {
        var map = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'Ç': 'C', 'Ğ': 'G', 'İ': 'I', 'Ö': 'O', 'Ş': 'S', 'Ü': 'U' };
        var words = String(title || '').replace(/[çğıöşüÇĞİÖŞÜ]/g, function (letter) { return map[letter]; })
            .replace(/[^A-Za-z0-9]+/g, ' ').trim().split(' ').filter(Boolean).slice(0, 4);
        var alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        var tail = '';
        var bytes = new Uint8Array(10);

        (window.crypto || window.msCrypto).getRandomValues(bytes);

        for (var i = 0; i < bytes.length; i++) {
            tail += alphabet.charAt(bytes[i] % alphabet.length);
        }

        return 'https://meet.jit.si/' + (words.length ? words.join('-') : 'Meeting') + '-' + tail;
    }

    // Opens a plan item's video call in a new tab.
    function joinMeeting(url) {
        var opened = window.open(url, '_blank', 'noopener');

        if (opened) {
            opened.opener = null;
        }
    }

    // The plan item form: a meeting, a visit, leave, a holiday.
    function eventForm(event, defaults, onDone) {
        var node = offcanvas('ws-event-form', '');
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));
        var rights = BOOT.me.rights;

        defaults = defaults || {};
        footer.classList.remove('d-none');
        node.querySelector('.drawer-title').textContent = event ? t('edit_plan_item') : t('add_plan_item');

        var kind = select(Object.keys(CFG.event_kinds).map(function (key) { return [key, CFG.event_kinds[key]]; }), event ? event.kind : (defaults.kind || 'meeting'));
        kind.id = nextId('ws-ev-kind-');
        body.appendChild(formRow(t('kind'), kind));

        var title = el('input', 'form-control form-control-sm');
        title.id = nextId('ws-ev-title-');
        title.maxLength = 255;
        title.value = event ? event.title : '';
        body.appendChild(formRow(t('title'), title, t('event_title_help')));

        var scopes = [['people', t('scope_people')]];

        if (rights.leads.length || rights.settings) {
            scopes.push(['department', t('scope_dept')]);
        }

        if (rights.settings) {
            scopes.push(['company', t('scope_company')]);
        }

        var scope = select(scopes, event ? event.scope : 'people');
        scope.id = nextId('ws-ev-scope-');
        body.appendChild(formRow(t('who'), scope));

        var peopleRow = el('div');
        var people = peoplePicker(event ? event.people : (defaults.people || [BOOT.me.id]));
        peopleRow.appendChild(formRow(t('scope_people'), people));
        body.appendChild(peopleRow);

        var deptRow = el('div');
        var dept = select(departmentOptions(), event ? event.department_id : 0);
        dept.id = nextId('ws-ev-dept-');
        deptRow.appendChild(formRow(t('department'), dept));
        body.appendChild(deptRow);

        function scopeView() {
            peopleRow.classList.toggle('d-none', scope.value !== 'people');
            deptRow.classList.toggle('d-none', scope.value !== 'department');
        }

        scope.addEventListener('change', scopeView);
        scopeView();

        var grid = el('div', 'row g-3');

        function col(content, size) {
            var cell = el('div', size || 'col-6');
            cell.appendChild(content);
            grid.appendChild(cell);
            return cell;
        }

        var start = el('input', 'form-control form-control-sm');
        start.type = 'date';
        start.id = nextId('ws-ev-start-');
        start.value = event ? event.start : (defaults.start || CFG.today);
        col(formRow(t('start_date'), start));

        var end = el('input', 'form-control form-control-sm');
        end.type = 'date';
        end.id = nextId('ws-ev-end-');
        end.value = event ? event.end : (defaults.start || CFG.today);
        col(formRow(t('end_date'), end));

        var allDayWrap = el('div', 'form-field mb-3');
        var allDay = el('input', '');
        allDay.type = 'checkbox';
        allDay.id = nextId('ws-ev-allday-');
        allDay.checked = event ? (String(event.all_day) === '1') : false;
        var allDayLabel = el('label', '', t('all_day'));
        allDayLabel.htmlFor = allDay.id;
        allDayWrap.appendChild(el('div', 'switch')).appendChild(allDay);
        allDayWrap.appendChild(allDayLabel);
        col(allDayWrap, 'col-12');

        var startTime = el('input', 'form-control form-control-sm');
        startTime.type = 'time';
        startTime.id = nextId('ws-ev-st-');
        startTime.value = event ? event.start_time : '09:00';
        var startTimeCell = col(formRow(t('start_time'), startTime));

        var endTime = el('input', 'form-control form-control-sm');
        endTime.type = 'time';
        endTime.id = nextId('ws-ev-et-');
        endTime.value = event ? event.end_time : '10:00';
        var endTimeCell = col(formRow(t('end_time'), endTime));

        function timeView() {
            var whole = allDay.checked || kind.value === 'leave' || kind.value === 'holiday';
            startTimeCell.classList.toggle('d-none', whole);
            endTimeCell.classList.toggle('d-none', whole);
        }

        allDay.addEventListener('change', timeView);
        kind.addEventListener('change', function () {
            if (kind.value === 'holiday' && rights.settings) {
                scope.value = 'company';
                scopeView();
            }

            timeView();
        });
        timeView();

        body.appendChild(grid);

        var note = el('textarea', 'form-control form-control-sm');
        note.id = nextId('ws-ev-note-');
        note.rows = 2;
        note.maxLength = 500;
        note.value = event ? event.note : '';
        body.appendChild(formRow(t('note'), note));

        // The video call (includes/workspace/meetings.php): a Jitsi room made
        // up here, or a link pasted in; Join opens it in a new tab.
        var meeting = null;

        if (CFG.meetings) {
            var meetingRow = el('div');
            var meetingGroup = el('div', 'input-group input-group-sm');
            meeting = el('input', 'form-control');
            meeting.type = 'url';
            meeting.id = nextId('ws-ev-meeting-');
            meeting.maxLength = 500;
            meeting.placeholder = 'https://';
            meeting.value = event ? (event.meeting_url || '') : '';
            meetingGroup.appendChild(meeting);

            var jitsi = button('btn btn-outline theme-secondary', '', 'bi-magic', t('meeting_jitsi'));
            jitsi.addEventListener('click', function () {
                meeting.value = jitsiRoom(title.value);
                joinView();
            });
            meetingGroup.appendChild(jitsi);

            var join = el('a', 'btn btn-solid theme-primary', t('meeting_join'));
            join.target = '_blank';
            join.rel = 'noopener noreferrer';
            join.insertBefore(icon('bi-camera-video', 'me-1'), join.firstChild);
            meetingGroup.appendChild(join);

            var joinView = function () {
                var url = meeting.value.trim();

                join.classList.toggle('d-none', !/^https?:\/\/\S+$/i.test(url));
                join.href = url || '#';
            };

            meeting.addEventListener('input', joinView);
            joinView();

            if (event && !event.can_edit) {
                meeting.readOnly = true;
                jitsi.classList.add('d-none');
            }

            meetingRow.appendChild(formRow(t('meeting_link'), meetingGroup, t('meeting_link_help')));
            body.appendChild(meetingRow);

            var meetingView = function () {
                meetingRow.classList.toggle('d-none', (kind.value === 'leave' || kind.value === 'holiday') && !meeting.value);
            };

            kind.addEventListener('change', meetingView);
            meetingView();

            // The meeting note: found again, or written from the template.
            if (event && CFG.notes) {
                var noteButton = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('meeting_note'), 'bi-journal-text');
                noteButton.title = t('meeting_note_help');
                noteButton.addEventListener('click', function () {
                    noteButton.disabled = true;
                    api('ws_meeting_note', { event_id: event.id }).then(function (data) {
                        window.location.href = CFG.urls.notes + '?note=' + data.note_id;
                    }).catch(function (error) {
                        noteButton.disabled = false;
                        fail(error);
                    });
                });
                body.appendChild(noteButton);
            }
        }

        if (event && event.can_edit) {
            var remove = button('btn btn-sm btn-outline theme-warning me-auto', t('delete'), 'bi-trash');
            remove.addEventListener('click', function () {
                ask(t('delete_item_confirm'), t('delete'), true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    api('ws_event_delete', { event_id: event.id }).then(function () {
                        hideOffcanvas(node);

                        if (onDone) {
                            onDone();
                        }
                    }).catch(fail);
                });
            });
            footer.appendChild(remove);
        }

        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'drawer');
        footer.appendChild(cancel);

        if (!event || event.can_edit) {
            var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('save'), 'bi-check2');
            save.addEventListener('click', function () {
                api('ws_event_save', {
                    id: event ? event.id : 0,
                    kind: kind.value,
                    title: title.value,
                    scope: scope.value,
                    people: people.value(),
                    department_id: parseInt(dept.value, 10) || 0,
                    start: start.value,
                    end: end.value || start.value,
                    all_day: allDay.checked ? 1 : 0,
                    start_time: startTime.value,
                    end_time: endTime.value,
                    note: note.value,
                    meeting_url: meeting ? meeting.value.trim() : undefined
                }).then(function () {
                    hideOffcanvas(node);
                    toast(t('plan_saved'), 'success');

                    if (onDone) {
                        onDone();
                    }
                }).catch(fail);
            });
            footer.appendChild(save);
        }

        showOffcanvas(node);
    }

    // ═══════════════════════════════════════════════════════════════════
    // The Workspace drawer on the order, contact and product screens
    // ═══════════════════════════════════════════════════════════════════

    function openRecord(type, id, label, options) {
        // No record yet: the new mode, an empty form that makes one.
        if (!(id > 0)) {
            openRecordNew(type, options);
            return;
        }

        var node = offcanvas('ws-record', t('workspace'));
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));
        var token = '<#' + type + ':' + id + '>';
        var ref = { type: type, id: id, label: label || '', icon: (BOOT.ref_types[type] || {}).icon || 'bi-dot', token: token };
        // The record's fields, edited right here (includes/workspace/quick_edit.php);
        // drawn first, loaded beside the rest, opened out when asked to edit.
        var fieldsBox = el('div');
        var reopen = function () { openRecord(type, id, label, options); };

        node.classList.add('ws-record-drawer');
        node.querySelector('.drawer-title').textContent = t('workspace') + (label ? ' · ' + label : '');
        footer.classList.remove('d-none');
        body.appendChild(fieldsBox);
        body.appendChild(skeleton(2, 'ws-skeleton-sm'));
        showOffcanvas(node);

        quickEditLoad(fieldsBox, type, id, !!(options && options.edit), reopen);

        var newTask = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('new_task'), 'bi-check2-square');
        newTask.addEventListener('click', function () {
            hideOffcanvas(node);
            taskDrawer.open(0, { refs: [ref], title: label || '' }, reopen);
        });
        footer.appendChild(newTask);

        // A contact, a user or a current account can be the customer of a
        // channel of its own.
        if ((type === 'contact') || (CFG.customer && ((type === 'user_account') || (type === 'erp_account')))) {
            var newChannel = button('btn btn-sm btn-outline theme-secondary', t('customer_channel'), 'bi-hash');
            newChannel.addEventListener('click', function () {
                hideOffcanvas(node);
                channelForm(null, {
                    name: label || '',
                    customer_type: type,
                    customer_id: id,
                    customer_label: label || '',
                    contact_id: (type === 'contact') ? id : 0,
                    contact_label: (type === 'contact') ? (label || '') : '',
                    kind: 'public'
                }, function (channelId) {
                    window.location.href = CFG.urls.workspace + '?channel=' + channelId;
                });
            });
            footer.appendChild(newChannel);
        }

        api('ws_record_refs', { type: type, id: id }).then(function (data) {
            clear(body);
            body.appendChild(fieldsBox);

            if (data.channels && data.channels.length) {
                var channels = el('div', 'ws-record-section');
                channels.appendChild(el('div', 'ws-record-title', t('customer_channels')));

                data.channels.forEach(function (channel) {
                    var link = el('a', 'ws-record-item');
                    link.href = channel.url;
                    link.appendChild(icon(channel['private'] ? 'bi-lock' : 'bi-hash', 'me-1'));
                    link.appendChild(document.createTextNode(channel.name));
                    channels.appendChild(link);
                });

                body.appendChild(channels);
            }

            var tasks = el('div', 'ws-record-section');
            tasks.appendChild(el('div', 'ws-record-title', t('tasks')));
            var taskList = el('div');
            tasks.appendChild(taskList);
            body.appendChild(tasks);

            if (data.tasks.length) {
                taskRows(taskList, data.tasks, reopen);
            } else {
                taskList.appendChild(el('div', 'small fg-secondary', t('no_tasks')));
            }

            var talk = el('div', 'ws-record-section');
            talk.appendChild(el('div', 'ws-record-title', t('conversations')));

            if (!data.messages.length) {
                talk.appendChild(el('div', 'small fg-secondary', t('not_discussed')));
            }

            data.messages.forEach(function (message) {
                var link = el('a', 'ws-record-item');
                link.href = message.url;
                var head = el('div', 'd-flex gap-3 align-items-center');
                var where = el('b');
                where.appendChild(icon(message['private'] ? 'bi-lock' : 'bi-hash', 'me-1'));
                where.appendChild(document.createTextNode(message.channel));
                head.appendChild(where);
                head.appendChild(el('span', 'fg-secondary', message.sender + ' · ' + message.time));
                link.appendChild(head);
                link.appendChild(el('small', '', message.excerpt));
                talk.appendChild(link);
            });

            body.appendChild(talk);

            // Share the record in a channel, with a line of text.
            if (data.post_channels.length) {
                var share = el('div', 'ws-record-section');
                share.appendChild(el('div', 'ws-record-title', t('share_in_channel')));
                var target = select(data.post_channels.map(function (channel) {
                    return [channel.id, '#' + channel.name + (channel.kind === 'private' ? ' (' + t('kind_private') + ')' : '')];
                }), 0);
                share.appendChild(target);
                var note = el('textarea', 'form-control form-control-sm mt-3');
                note.rows = 2;
                note.placeholder = t('share_placeholder');
                share.appendChild(note);
                var send = button('btn btn-sm btn-outline theme-secondary mt-3', t('share'), 'bi-send');
                send.addEventListener('click', function () {
                    api('ws_send', { channel_id: parseInt(target.value, 10), body: (note.value.trim() ? note.value.trim() + ' ' : '') + token }).then(function () {
                        toast(t('shared'), 'success');
                        reopen();
                    }).catch(fail);
                });
                share.appendChild(send);
                body.appendChild(share);
            }
        }).catch(fail);
    }

    // The fields of the record, edited in the drawer with no assistant in
    // between. What the server lists is drawn as inputs; a save sends only
    // what changed, with the values the drawer was given, so a record
    // somebody else wrote meanwhile is not written over.
    function quickEditLoad(box, type, id, open, reopen) {
        api('ws_record_fields', { type: type, id: id }).then(function (data) {
            clear(box);
            box.appendChild(quickEditSection(type, id, data, open, reopen));
        }).catch(function (error) {
            clear(box);

            // No right to the kind: nothing to show. A record that cannot
            // be written (one in the Recycle Bin) says why.
            if (error && error.code && error.code !== 'rights') {
                var section = el('div', 'ws-record-section');
                section.appendChild(el('div', 'ws-record-title', t('qe_title')));
                section.appendChild(el('div', 'small fg-secondary', error.message));
                box.appendChild(section);
            }
        });
    }

    // Does what the input holds differ from what the record held?
    function quickEditChanged(meta, original, value) {
        var lines = function (text) {
            return (Array.isArray(text) ? text : String(text === null || text === undefined ? '' : text).split(/[\r\n,]+/))
                .map(function (line) { return line.trim(); }).filter(function (line) { return line !== ''; });
        };

        if (meta.kind === 'lines') {
            return lines(original).join('\n') !== lines(value).join('\n');
        }

        if (meta.kind === 'keywords') {
            return lines(original).map(function (word) { return word.toLocaleLowerCase(); }).join(',')
                !== lines(value).map(function (word) { return word.toLocaleLowerCase(); }).join(',');
        }

        if (meta.kind === 'decimal') {
            var a = String(original === null || original === undefined ? '' : original);
            var b = String(value === null || value === undefined ? '' : value);
            return (a === '' || b === '') ? (a !== b) : (parseFloat(a) !== parseFloat(b));
        }

        if (meta.kind === 'datetime') {
            return String(original || '').replace(' ', 'T').slice(0, 16) !== String(value || '').replace(' ', 'T').slice(0, 16);
        }

        // A date column keeps "no date" as the zero date, which a date input
        // cannot show: it empties itself, so the two are the same value.
        if (meta.kind === 'date') {
            var day = function (text) {
                text = String(text === null || text === undefined ? '' : text).trim();
                return (text.indexOf('0000-00-00') === 0) ? '' : text;
            };
            return day(original) !== day(value);
        }

        if (meta.kind === 'long' || meta.kind === 'text') {
            return String(original === null || original === undefined ? '' : original).replace(/\r\n/g, '\n') !== String(value === null || value === undefined ? '' : value).replace(/\r\n/g, '\n');
        }

        return String(original === null || original === undefined ? '' : original) !== String(value === null || value === undefined ? '' : value);
    }

    function quickEditSection(type, id, data, open, reopen) {
        var section = el('div', 'ws-record-section ws-qe');
        var head = el('div', 'd-flex align-items-center justify-content-between');
        var title = el('div', 'ws-record-title mb-0', t('qe_title'));
        var toggle = button('btn btn-sm btn-text theme-secondary', open ? t('close') : t('qe_edit'), open ? 'bi-chevron-up' : 'bi-pencil-square');
        var form = el('div', 'ws-qe-form mt-3' + (open ? '' : ' d-none'));
        var fields = data.fields.slice();
        var short = data.short || [];
        var inputs = {};
        var originals = {};
        var metas = {};
        var rows = {};

        if (data.stock) {
            fields.push(data.stock);
        }

        head.appendChild(title);
        head.appendChild(toggle);
        section.appendChild(head);

        // A glance at the record while the form is closed: the first few
        // values, as the card prints them.
        var glance = el('div', 'ws-qe-glance small fg-secondary' + (open ? ' d-none' : ''));
        short.slice(0, 4).forEach(function (name) {
            fields.forEach(function (meta) {
                var empty = meta.value === '' || meta.value === null || meta.value === undefined || (Array.isArray(meta.value) && !meta.value.length);

                if (meta.name === name && meta.column && !empty) {
                    glance.appendChild(el('span', 'ws-qe-glance-item', meta.label + ': ' + meta.shown));
                }
            });
        });
        section.appendChild(glance);

        toggle.addEventListener('click', function () {
            var opening = form.classList.contains('d-none');
            form.classList.toggle('d-none', !opening);
            glance.classList.toggle('d-none', opening);
            toggle.textContent = '';
            toggle.appendChild(icon(opening ? 'bi-chevron-up' : 'bi-pencil-square', 'me-1'));
            toggle.appendChild(document.createTextNode(opening ? t('close') : t('qe_edit')));
        });

        // The fields changed most often first, the rest behind a link.
        var shortBox = el('div');
        var moreBox = el('div', 'd-none');
        var inShort = function (name) { return short.indexOf(name) !== -1; };
        var ordered = fields.filter(function (meta) { return inShort(meta.name); }).sort(function (a, b) { return short.indexOf(a.name) - short.indexOf(b.name); })
            .concat(fields.filter(function (meta) { return !inShort(meta.name); }));

        ordered.forEach(function (meta) {
            var input = saValueInput(meta, meta.value);
            var help = '';

            if (meta.name === 'quantity' && data.stock && !data.stock.tracked) {
                help = t('qe_stock_off');
            }

            var row = saLabelled(meta.label, input, help);
            row.classList.add('ws-qe-row');
            inputs[meta.name] = input;
            originals[meta.name] = meta.value;
            metas[meta.name] = meta;
            rows[meta.name] = row;
            (inShort(meta.name) ? shortBox : moreBox).appendChild(row);
        });

        // A field that goes with another (the reason for cancelling an
        // order) is shown only while the other asks for it.
        var dependent = function () {
            Object.keys(rows).forEach(function (name) {
                if (!metas[name].column) {
                    var status = inputs.status ? inputs.status.read() : '';
                    rows[name].classList.toggle('d-none', status !== 'cancelled');
                }
            });
        };

        if (inputs.status) {
            inputs.status.addEventListener('change', dependent);
        }

        dependent();
        form.appendChild(shortBox);

        if (moreBox.childNodes.length) {
            var more = button('btn btn-sm btn-text theme-secondary mb-3', short.length ? t('qe_more') : t('qe_fewer'), 'bi-chevron-down');

            if (!short.length) {
                moreBox.classList.remove('d-none');
            }

            more.addEventListener('click', function () {
                var showing = moreBox.classList.contains('d-none');
                moreBox.classList.toggle('d-none', !showing);
                more.textContent = '';
                more.appendChild(icon(showing ? 'bi-chevron-up' : 'bi-chevron-down', 'me-1'));
                more.appendChild(document.createTextNode(showing ? t('qe_fewer') : t('qe_more')));
            });

            form.appendChild(more);
            form.appendChild(moreBox);
        }

        // Kept in the channel the drawer was opened from, as a decision.
        var decision = null;
        var channelId = (CFG.mode === 'channels' && app.channel && app.channel.id) ? app.channel.id : 0;

        if (channelId) {
            var tick = el('div', 'form-field mt-1 mb-3');
            decision = el('input', 'check');
            decision.type = 'checkbox';
            decision.checked = true;
            decision.id = nextId('ws-qe-decision-');
            var tickLabel = el('label', 'small', t('qe_decision'));
            tickLabel.htmlFor = decision.id;
            tick.appendChild(decision);
            tick.appendChild(tickLabel);
            form.appendChild(tick);
        }

        var bar = el('div', 'd-flex align-items-center gap-3 mt-1');
        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('qe_save'), 'bi-check2');
        var status = el('span', 'small fg-secondary');
        bar.appendChild(save);
        bar.appendChild(status);
        form.appendChild(bar);

        save.addEventListener('click', function () {
            var changed = {};
            var from = {};
            var any = false;

            Object.keys(inputs).forEach(function (name) {
                if (rows[name].classList.contains('d-none')) {
                    return;
                }

                var value = inputs[name].read();

                // A field with no column is sent whenever it is shown.
                if (!metas[name].column ? (String(value) !== '') : quickEditChanged(metas[name], originals[name], value)) {
                    changed[name] = value;
                    any = true;

                    if (metas[name].column) {
                        from[name] = originals[name];
                    }
                }
            });

            if (!any) {
                status.textContent = t('qe_nothing');
                return;
            }

            save.disabled = true;
            status.textContent = '';

            api('ws_record_fields_save', {
                type: type, id: id, fields: changed, from: from,
                channel_id: channelId, decision: !!(decision && decision.checked)
            }).then(function () {
                toast(t('qe_saved'), 'success');
                reopen();
            }).catch(function (error) {
                save.disabled = false;

                if (error && error.stale) {
                    clear(status);
                    status.appendChild(document.createTextNode(t('qe_stale') + ' '));
                    var again = el('a', 'fw-semibold', t('qe_reload'));
                    again.href = '#';
                    again.addEventListener('click', function (event) { event.preventDefault(); reopen(); });
                    status.appendChild(again);
                    return;
                }

                status.textContent = (error && error.message) ? error.message : t('error_generic');
            });
        });

        section.appendChild(form);

        return section;
    }

    // The record drawer's new mode: an empty product or product group
    // (includes/workspace/quick_edit.php, ws_quick_create_*), opened from
    // the search box or a /product card. It is made off sale unless "On
    // sale" is ticked; once written, the drawer shows the new record.
    // options.values fill the form (a /product card's), options.labels name
    // the records they pick, options.channelId is the channel its decision
    // goes to (the card's composer), options.decision false starts the
    // decision tick unticked (the card's own tick), options.onCreated is
    // told the new record.
    function openRecordNew(type, options) {
        var node = offcanvas('ws-record', t('workspace'));
        var body = clear(node.querySelector('.drawer-body'));
        var footer = clear(node.querySelector('.drawer-footer'));

        options = options || {};
        node.classList.add('ws-record-drawer');
        node.querySelector('.drawer-title').textContent = t(type === 'product_group' ? 'qc_new_group' : 'qc_new_product');
        footer.classList.add('d-none');
        body.appendChild(skeleton(2, 'ws-skeleton-sm'));
        showOffcanvas(node);

        api('ws_record_fields', { type: type, id: 0 }).then(function (data) {
            clear(body);

            if (data.title) {
                node.querySelector('.drawer-title').textContent = data.title;
            }

            body.appendChild(quickCreateSection(type, data, options, function (created) {
                if (options.onCreated) {
                    options.onCreated(created);
                }

                openRecord(type, created.id, created.name);
            }));
        }).catch(function (error) {
            clear(body);
            body.appendChild(el('div', 'small fg-secondary', (error && error.message) ? error.message : t('error_generic')));
        });
    }

    // The form of the new mode: the fields the server lists, the ones made
    // most often first. Only what differs from the value a field starts
    // with is sent (the writer's starting row keeps the rest); the on-sale
    // tick is always sent, unticked by default.
    function quickCreateSection(type, data, options, done) {
        var section = el('div', 'ws-record-section ws-qe');
        var values = options.values || {};
        var labels = options.labels || {};
        var short = data.short || [];
        var required = data.required || [];
        var inputs = {};
        var metas = {};
        var shortBox = el('div');
        var moreBox = el('div', 'd-none');
        var inShort = function (name) { return short.indexOf(name) !== -1; };
        var ordered = data.fields.filter(function (meta) { return inShort(meta.name); }).sort(function (a, b) { return short.indexOf(a.name) - short.indexOf(b.name); })
            .concat(data.fields.filter(function (meta) { return !inShort(meta.name); }));
        var help = function (name) {
            if (type === 'product' && name === 'name') {
                return t('qc_name_help');
            }

            return (type === 'product' && name === 'quantity') ? t('qc_quantity_help') : '';
        };

        section.appendChild(el('div', 'ws-record-title', t('qe_title')));

        ordered.forEach(function (meta) {
            var given = Object.prototype.hasOwnProperty.call(values, meta.name) ? values[meta.name] : meta.value;
            var label = meta.label + ((required.indexOf(meta.name) !== -1) ? ' *' : '');
            var input;
            var row;

            if (meta.name === 'enabled') {
                row = el('div', 'form-field mb-3');
                input = el('input', 'check');
                input.type = 'checkbox';
                input.checked = given === true;
                input.id = nextId('ws-qc-enabled-');
                var tickLabel = el('label', 'small', meta.label);
                tickLabel.htmlFor = input.id;
                row.appendChild(input);
                row.appendChild(tickLabel);
                row.appendChild(el('div', 'form-text mt-0', t('qc_unticked')));
                input.read = function () { return input.checked; };
            } else if (meta.kind === 'ids') {
                // One record, picked among those the person may tag.
                var first = (Array.isArray(given) && given.length) ? parseInt(given[0], 10) : 0;
                var picker = saRecordPicker(meta.tag, first, first ? (labels[meta.name] || ('#' + first)) : '');
                picker.read = function () {
                    var id = picker.value();
                    return id ? [id] : [];
                };
                input = picker;
                row = saLabelled(label, picker, '');
            } else {
                input = saValueInput(meta, given);
                row = saLabelled(label, input, help(meta.name));
            }

            row.classList.add('ws-qe-row');
            inputs[meta.name] = input;
            metas[meta.name] = meta;
            (inShort(meta.name) ? shortBox : moreBox).appendChild(row);
        });

        section.appendChild(shortBox);

        if (moreBox.childNodes.length) {
            var more = button('btn btn-sm btn-text theme-secondary mb-3', t('qe_more'), 'bi-chevron-down');

            more.addEventListener('click', function () {
                var showing = moreBox.classList.contains('d-none');
                moreBox.classList.toggle('d-none', !showing);
                more.textContent = '';
                more.appendChild(icon(showing ? 'bi-chevron-up' : 'bi-chevron-down', 'me-1'));
                more.appendChild(document.createTextNode(showing ? t('qe_fewer') : t('qe_more')));
            });

            section.appendChild(more);
            section.appendChild(moreBox);
        }

        // Kept in the channel the drawer was opened from, as a decision: the
        // card's composer when a /product card opened it, else the main view.
        var decision = null;
        var channelId = (options.channelId > 0) ? options.channelId : ((CFG.mode === 'channels' && app.channel && app.channel.id) ? app.channel.id : 0);

        if (channelId) {
            var tick = el('div', 'form-field mt-1 mb-3');
            decision = el('input', 'check');
            decision.type = 'checkbox';
            // A /product card hands over its own tick; otherwise ticked.
            decision.checked = options.decision !== false;
            decision.id = nextId('ws-qc-decision-');
            var decisionLabel = el('label', 'small', t('qe_decision'));
            decisionLabel.htmlFor = decision.id;
            tick.appendChild(decision);
            tick.appendChild(decisionLabel);
            section.appendChild(tick);
        }

        var bar = el('div', 'd-flex align-items-center gap-3 mt-1');
        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('qc_create'), 'bi-plus-lg');
        var status = el('span', 'small fg-secondary');
        bar.appendChild(save);
        bar.appendChild(status);
        section.appendChild(bar);

        save.addEventListener('click', function () {
            var fields = {};

            Object.keys(inputs).forEach(function (name) {
                var value = inputs[name].read();

                if (name === 'enabled') {
                    fields[name] = value;
                } else if (metas[name].kind === 'ids') {
                    if (value.length) {
                        fields[name] = value;
                    }
                } else if (quickEditChanged(metas[name], metas[name].value, value)) {
                    fields[name] = value;
                }
            });

            save.disabled = true;
            status.textContent = '';

            api('ws_record_create', {
                type: type, fields: fields,
                channel_id: channelId, decision: !!(decision && decision.checked)
            }).then(function (created) {
                save.disabled = false;
                toast(t('qc_created'), 'success');
                done(created);
            }).catch(function (error) {
                save.disabled = false;
                status.textContent = (error && error.message) ? error.message : t('error_generic');
            });
        });

        return section;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Decision timeline
    // ═══════════════════════════════════════════════════════════════════

    // Every decision in the channels this person may read, newest first and
    // grouped by day; the filters sit in the address, so a view can be kept
    // and passed on.
    function startTimeline(root) {
        clear(root);

        var query = params();
        var dateOf = function (value) { return /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? value : ''; };
        var state = {
            channel_id: parseInt(query.channel || 0, 10) || 0,
            department_id: parseInt(query.department || 0, 10) || 0,
            person_id: parseInt(query.person || 0, 10) || 0,
            from: dateOf(query.from),
            to: dateOf(query.to),
            search: String(query.q || '').slice(0, 100),
            notes: query.notes === '1'
        };
        var next = '';
        var loading = false;
        var serial = 0;
        var day = { key: '', list: null };

        var bar = el('nav', 'pg-toolbar navigation mb-5');
        var layout = el('div', 'row g-7');
        var mainCol = el('div', 'xl:col-9');
        var sideCol = el('div', 'xl:col-3 d-none xl:d-block');
        var hiddenBox = el('div');
        var summary = el('div', 'ws-tl-summary');
        var list = el('div', 'ws-tl');
        var moreWrap = el('div', 'ws-tl-more d-none');
        var side = el('div', 'card ws-tl-side');

        list.setAttribute('aria-live', 'polite');
        mainCol.appendChild(hiddenBox);
        mainCol.appendChild(summary);
        mainCol.appendChild(list);
        mainCol.appendChild(moreWrap);
        sideCol.appendChild(side);
        layout.appendChild(mainCol);
        layout.appendChild(sideCol);
        root.appendChild(bar);
        root.appendChild(layout);

        // ── toolbar ──

        var channelSelect = select([[0, t('tl_all_channels')]], 0);
        channelSelect.classList.add('w-auto');
        channelSelect.setAttribute('aria-label', t('tl_all_channels'));
        channelSelect.addEventListener('change', function () {
            state.channel_id = parseInt(channelSelect.value, 10) || 0;
            load(true);
        });
        bar.appendChild(channelSelect);

        var deptSelect = null;

        if (BOOT.departments && BOOT.departments.length) {
            deptSelect = select(departmentOptions(t('all_departments')), state.department_id);
            deptSelect.classList.add('w-auto');
            deptSelect.setAttribute('aria-label', t('all_departments'));
            deptSelect.addEventListener('change', function () {
                state.department_id = parseInt(deptSelect.value, 10) || 0;
                load(true);
            });
            bar.appendChild(deptSelect);
        }

        var personSelect = select([[0, t('everyone')]].concat(BOOT.people.map(function (member) { return [member.id, member.name]; })), state.person_id);
        personSelect.classList.add('w-auto');
        personSelect.setAttribute('aria-label', t('person'));
        personSelect.addEventListener('change', function () {
            state.person_id = parseInt(personSelect.value, 10) || 0;
            load(true);
        });
        bar.appendChild(personSelect);

        // The two days stay side by side when the toolbar wraps.
        var dates = el('div', 'd-flex gap-1');
        var dateField = function (key, label) {
            var input = el('input', 'form-control form-control-sm w-auto');
            input.type = 'date';
            input.value = state[key];
            input.title = label;
            input.setAttribute('aria-label', label);
            input.addEventListener('change', function () {
                state[key] = dateOf(input.value);
                load(true);
            });
            dates.appendChild(input);
            return input;
        };

        var fromInput = dateField('from', t('tl_from'));
        var toInput = dateField('to', t('tl_to'));
        bar.appendChild(dates);

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var notesToggle = button('btn btn-sm btn-text theme-secondary' + (state.notes ? ' active' : ''), t('tl_notes'), 'bi-sticky');
        notesToggle.setAttribute('aria-pressed', state.notes ? 'true' : 'false');
        notesToggle.addEventListener('click', function () {
            state.notes = !state.notes;
            notesToggle.classList.toggle('active', state.notes);
            notesToggle.setAttribute('aria-pressed', state.notes ? 'true' : 'false');
            load(true);
        });
        bar.appendChild(notesToggle);

        var searchGroup = el('div', 'input-group input-group-sm rounded-pill pg-toolbar-search');
        var search = el('input', 'form-control');
        search.type = 'search';
        search.value = state.search;
        search.placeholder = t('tl_search');
        search.setAttribute('aria-label', search.placeholder);
        search.addEventListener('input', debounce(function () {
            state.search = search.value.trim();
            load(true);
        }, 300));
        searchGroup.appendChild(search);
        bar.appendChild(searchGroup);

        var moreButton = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('tl_more'), 'bi-arrow-down');
        moreButton.addEventListener('click', function () { load(false); });
        moreWrap.appendChild(moreButton);

        // The next page comes on its own as the end of the list scrolls in.
        if (window.IntersectionObserver) {
            new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting && next && !loading) {
                    load(false);
                }
            }, { rootMargin: '400px 0px' }).observe(moreWrap);
        }

        function filtered() {
            return !!(state.channel_id || state.department_id || state.person_id || state.from || state.to || state.search);
        }

        function clearFilters() {
            state.channel_id = 0;
            state.department_id = 0;
            state.person_id = 0;
            state.from = '';
            state.to = '';
            state.search = '';
            channelSelect.value = '0';
            personSelect.value = '0';
            fromInput.value = '';
            toInput.value = '';
            search.value = '';

            if (deptSelect) {
                deptSelect.value = '0';
            }

            load(true);
        }

        function remember() {
            if (!(window.history && window.history.replaceState)) {
                return;
            }

            var parts = [];
            var add = function (key, value) {
                if (value) {
                    parts.push(key + '=' + encodeURIComponent(value));
                }
            };

            add('channel', state.channel_id);
            add('department', state.department_id);
            add('person', state.person_id);
            add('from', state.from);
            add('to', state.to);
            add('q', state.search);
            add('notes', state.notes ? '1' : '');

            window.history.replaceState(null, '', CFG.urls.timeline + (parts.length ? '?' + parts.join('&') : ''));
        }

        // ── the first page's extras: channels, counts, closed channels ──

        function drawChannels(channels) {
            clear(channelSelect);
            channelSelect.appendChild(el('option', '', t('tl_all_channels')));
            channelSelect.firstChild.value = '0';

            var archived = null;

            channels.forEach(function (channel) {
                var option = el('option', '', '#' + channel.name);
                option.value = channel.id;

                if (channel.archived) {
                    if (!archived) {
                        archived = el('optgroup');
                        archived.label = t('tl_archived_group');
                        channelSelect.appendChild(archived);
                    }

                    archived.appendChild(option);
                } else {
                    channelSelect.appendChild(option);
                }
            });

            channelSelect.value = String(state.channel_id);

            // A channel from the address that is not readable falls back to all.
            if (channelSelect.value !== String(state.channel_id)) {
                channelSelect.value = '0';
            }
        }

        function drawSide(channels) {
            clear(side);

            var head = el('div', 'card-header');
            head.appendChild(el('h2', 'h6 mb-0', t('tl_by_channel')));
            side.appendChild(head);

            var counted = channels.filter(function (channel) { return channel.count > 0; });

            counted.sort(function (a, b) { return (b.count - a.count) || a.name.localeCompare(b.name); });

            if (!counted.length) {
                side.appendChild(el('div', 'card-body small fg-secondary', t('tl_empty')));
                return;
            }

            var group = el('div', 'list-group list-group-flush');

            counted.forEach(function (channel) {
                var row = button('list-group-item list-group-item-action d-flex align-items-center gap-3' + (state.channel_id === channel.id ? ' active' : ''));
                row.appendChild(icon(channel.private ? 'bi-lock' : 'bi-hash'));
                row.appendChild(el('span', 'text-truncate flex-grow-1', channel.name));

                if (channel.archived) {
                    var archivedIcon = icon('bi-archive');
                    archivedIcon.title = t('tl_archived');
                    row.appendChild(archivedIcon);
                }

                row.appendChild(el('span', 'badge rounded-pill badge-subtle theme-secondary', channel.count));
                row.setAttribute('aria-pressed', state.channel_id === channel.id ? 'true' : 'false');
                row.addEventListener('click', function () {
                    state.channel_id = (state.channel_id === channel.id) ? 0 : channel.id;
                    channelSelect.value = String(state.channel_id);
                    load(true);
                });
                group.appendChild(row);
            });

            side.appendChild(group);
        }

        function drawSummary(data) {
            clear(summary);
            summary.appendChild(el('span', '', t(state.notes ? 'tl_total_notes' : 'tl_total', data.total || 0)));

            if (filtered()) {
                var reset = button('btn btn-sm btn-link p-0', t('tl_clear'), 'bi-x-circle');
                reset.addEventListener('click', clearFilters);
                summary.appendChild(reset);
            }
        }

        // Staff hear how many decisions sit in private channels they are not
        // in; what those say is only read after opening one for inspection.
        function drawHidden(hidden) {
            clear(hiddenBox);

            if (!hidden || !hidden.length) {
                return;
            }

            var total = hidden.reduce(function (sum, channel) { return sum + channel.count; }, 0);
            var box = el('details', 'ws-tl-hidden alert theme-secondary py-3 px-5');
            var line = el('summary');
            line.appendChild(icon('bi-shield-lock', 'me-1'));
            line.appendChild(document.createTextNode(t('tl_hidden', hidden.length, total)));
            box.appendChild(line);

            var rows = el('ul', 'list-unstyled mb-3 mt-3');

            hidden.forEach(function (channel) {
                var row = el('li', 'd-flex flex-wrap align-items-center gap-3 py-1');
                var name = el('span', 'fw-semibold');
                name.appendChild(icon('bi-lock', 'me-1'));
                name.appendChild(document.createTextNode(channel.name));

                if (channel.archived) {
                    var archivedIcon = icon('bi-archive', 'ms-1');
                    archivedIcon.title = t('tl_archived');
                    name.appendChild(archivedIcon);
                }

                row.appendChild(name);
                row.appendChild(el('span', 'fg-secondary', t('tl_hidden_row', channel.count, channel.owner)));

                var open = button('btn btn-sm ' + (channel.locked ? 'btn-outline theme-secondary' : 'btn-outline theme-warning') + ' ms-auto', t('open_for_audit'), channel.locked ? 'bi-eye-slash' : 'bi-eye');

                if (channel.locked) {
                    open.disabled = true;
                    open.title = t('audit_locked');
                }

                open.addEventListener('click', function () {
                    if (channel.locked) {
                        return;
                    }

                    ask(t('audit_confirm', channel.name), t('open_for_audit'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        api('ws_channel_audit', { channel_id: channel.id }).then(function () {
                            load(true);
                        }).catch(fail);
                    });
                });
                row.appendChild(open);
                rows.appendChild(row);
            });

            box.appendChild(rows);
            box.appendChild(el('div', 'small fg-secondary', t('tl_hidden_help')));
            hiddenBox.appendChild(box);
        }

        // ── one decision ──

        function itemNode(item) {
            var node = el('article', 'ws-tl-item ws-tl-' + item.kind + (item.claude ? ' ws-tl-by-claude' : ''));
            node.setAttribute('data-ws-message', item.id);

            node.appendChild(el('div', 'ws-tl-time', item.time));

            var line = el('div', 'ws-tl-line');
            var dot = el('span', 'ws-tl-dot');
            dot.appendChild(icon(item.kind === 'note' ? 'bi-sticky' : (item.claude ? 'bi-stars' : 'bi-patch-check')));
            line.appendChild(dot);
            node.appendChild(line);

            var card = el('div', 'card ws-tl-card');
            var head = el('div', 'ws-tl-head');

            var channel = el('a', 'ws-tl-channel');
            channel.href = CFG.urls.workspace + '?channel=' + item.channel.id;
            channel.appendChild(icon(item.channel.private ? 'bi-lock' : 'bi-hash'));
            channel.appendChild(document.createTextNode(item.channel.name));

            if (item.channel.private) {
                channel.title = t('tl_private');
            }

            if (item.channel.archived) {
                var archivedIcon = icon('bi-archive', 'ms-1');
                archivedIcon.title = t('tl_archived');
                channel.appendChild(archivedIcon);
            }

            head.appendChild(channel);

            var flag = el('span', 'ws-msg-flag ws-flag-' + item.kind);
            flag.appendChild(icon(item.kind === 'decision' ? 'bi-patch-check' : 'bi-sticky'));
            flag.appendChild(document.createTextNode(item.kind === 'decision' ? t('decision') : t('note')));

            // The trace of a record change: shown as kept for good.
            if (item.locked) {
                flag.appendChild(icon('bi-lock-fill', 'ms-1'));
                flag.title = t('change_locked');
            }

            head.appendChild(flag);

            if (item.claude) {
                var proposal = el('span', 'ws-tl-ai');
                proposal.appendChild(icon('bi-stars'));
                proposal.appendChild(document.createTextNode((item.claude.proposer ? t('tl_proposal', item.claude.proposer) : t(item.claude.agent === 'ai' ? 'tl_ai' : 'tl_claude')) + ' · ' + item.claude.type_label));
                head.appendChild(proposal);
            }

            card.appendChild(head);

            var body = el('div', 'ws-msg-body ws-tl-body');
            setHtml(body, item.html);
            chipMenus(body);
            card.appendChild(body);

            if (item.file) {
                var file = el('a', 'ws-tl-file');
                file.href = item.file.url;
                file.target = '_blank';
                file.rel = 'noopener';

                if (item.file.image) {
                    var picture = el('img');
                    picture.src = item.file.url;
                    picture.alt = item.file.name;
                    picture.loading = 'lazy';
                    file.appendChild(picture);
                } else {
                    file.appendChild(icon('bi-paperclip', 'me-1'));
                    file.appendChild(document.createTextNode(item.file.name));
                }

                card.appendChild(file);
            }

            var foot = el('div', 'ws-tl-foot');
            var who = el('span', 'ws-tl-who');

            if (item.sender) {
                who.appendChild(avatar(item.sender));
                who.appendChild(el('span', '', item.sender.name));
            }

            foot.appendChild(who);

            if (item.marked_by) {
                foot.appendChild(el('span', '', t('tl_marked_by', item.marked_by)));
            }

            if (item.edited) {
                foot.appendChild(el('span', '', t('edited')));
            }

            var jump = el('a', 'btn btn-sm btn-text theme-secondary ms-auto');
            jump.href = item.url;
            jump.appendChild(icon('bi-arrow-right-short', 'me-1'));
            jump.appendChild(document.createTextNode(t('show_in_conversation')));
            foot.appendChild(jump);

            card.appendChild(foot);
            node.appendChild(card);

            return node;
        }

        function append(item) {
            if (item.day !== day.key) {
                var section = el('section', 'ws-tl-day');
                section.appendChild(el('h2', 'ws-tl-date', item.day_label));
                day = { key: item.day, list: el('div', 'ws-tl-list') };
                section.appendChild(day.list);
                list.appendChild(section);
            }

            day.list.appendChild(itemNode(item));
        }

        function emptyNode() {
            var empty = el('div', 'ws-empty');
            empty.appendChild(icon('bi-patch-check'));
            empty.appendChild(document.createTextNode(filtered() ? t('tl_empty') : t('tl_empty_all')));

            if (filtered()) {
                var reset = button('btn btn-sm btn-outline theme-secondary rounded-pill px-5 mt-3', t('tl_clear'), 'bi-x-circle');
                reset.addEventListener('click', clearFilters);
                empty.appendChild(el('br'));
                empty.appendChild(reset);
            }

            return empty;
        }

        // ── loading ──

        function load(reset) {
            if (!reset && (loading || !next)) {
                return;
            }

            var mine = ++serial;
            var payload = {
                channel_id: state.channel_id,
                department_id: state.department_id,
                person_id: state.person_id,
                from: state.from,
                to: state.to,
                search: state.search,
                notes: state.notes ? 1 : 0
            };

            if (!reset) {
                payload.cursor = next;
            } else {
                remember();
                list.setAttribute('aria-busy', 'true');
            }

            loading = true;
            moreButton.disabled = true;

            api('ws_timeline', payload).then(function (data) {
                if (mine !== serial) {
                    return;
                }

                loading = false;
                moreButton.disabled = false;

                if (reset) {
                    clear(list);
                    day = { key: '', list: null };
                    drawChannels(data.channels || []);
                    drawSide(data.channels || []);
                    drawSummary(data);
                    drawHidden(data.hidden || []);
                    list.removeAttribute('aria-busy');

                    if (!data.items.length) {
                        list.appendChild(emptyNode());
                    }
                }

                data.items.forEach(append);
                next = data.next || '';
                moreWrap.classList.toggle('d-none', !next);
            }).catch(function (error) {
                if (mine === serial) {
                    loading = false;
                    moreButton.disabled = false;
                    list.removeAttribute('aria-busy');
                }

                fail(error);
            });
        }

        load(true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // My notes
    // ═══════════════════════════════════════════════════════════════════

    // The people who can see a note, as a row of faces: its owner, then
    // those it is shared with (a pencil for those who may edit).
    function noteFaces(note, limit) {
        var box = el('div', 'ws-nb-faces');
        var people = [];

        if (note.owner) {
            people.push({ person: note.owner, role: t('notes_owner', note.owner.name) });
        }

        (note['with'] || []).forEach(function (share) {
            people.push({ person: share.person, role: share.person.name + ' · ' + (share.can_edit ? t('notes_can_edit') : t('notes_can_view')), edit: share.can_edit });
        });

        people.slice(0, limit || 5).forEach(function (entry) {
            var face = el('span', 'ws-nb-face' + (entry.edit ? ' ws-nb-face-edit' : ''));
            face.appendChild(avatar(entry.person));
            face.title = entry.role;
            box.appendChild(face);
        });

        if (people.length > (limit || 5)) {
            box.appendChild(el('span', 'ws-nb-face-more', '+' + (people.length - (limit || 5))));
        }

        (note['in'] || []).forEach(function (channel) {
            var tag = el('span', 'ws-nb-face-channel', '#' + (channel.name || '?'));
            tag.title = t('notes_can_view');
            box.appendChild(tag);
        });

        return box;
    }

    // A note card whose note the person may change.
    function noteCardEditable(card) {
        return !!card && (card.access === 'owner' || card.access === 'edit');
    }

    // The note on the notes screen, open for writing (the link the read
    // view's "Open the note" follows).
    function openNoteEdit(noteId) {
        window.location.href = CFG.urls.notes + '?note=' + noteId;
    }

    // A note shared in a channel, read where it was shared: as it is now.
    function openNoteView(noteId) {
        api('ws_note_get', { note_id: noteId }).then(function (data) {
            var note = data.note;
            var node = el('dialog', 'dialog ws-nb-view dialog-lg dialog-scrollable md-down:dialog-fullscreen');
            node.tabIndex = -1;
            node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
                + '<div class="dialog-body"></div><div class="dialog-footer justify-content-start small fg-secondary"></div>';
            node.querySelector('.dialog-title').appendChild(icon('bi-journal-text', 'me-3'));
            node.querySelector('.dialog-title').appendChild(document.createTextNode(note.name));
            node.querySelector('.btn-close').setAttribute('aria-label', t('close'));

            var body = node.querySelector('.dialog-body');
            var html = el('div', 'ws-msg-body ws-nb-read');
            setHtml(html, note.html);
            chipMenus(html);
            body.appendChild(html);

            var footer = node.querySelector('.dialog-footer');
            footer.appendChild(noteFaces(note, 6));

            if (note.updated_by) {
                footer.appendChild(el('span', '', t('notes_last_change', note.updated_by.name, note.updated)));
            }

            if (note.access === 'owner' || note.access === 'edit') {
                var edit = el('a', 'btn btn-sm btn-outline theme-secondary rounded-pill ms-auto', t('notes_open'));
                edit.href = CFG.urls.notes + '?note=' + note.id;
                footer.appendChild(edit);
            }

            document.body.appendChild(node);
            pgUI.dialog.on(node, 'hidden', function () { node.remove(); });
            pgUI.dialog.show(node);
        }).catch(fail);
    }

    // Everybody's own notes and the ones shared with them: the lists on the
    // left, the note open on the right, written in the workspace's writing
    // box. A note saves itself a moment after the last keystroke.
    function startNotes(root) {
        clear(root);

        if (!CFG.notes) {
            root.appendChild(el('div', 'alert theme-warning', t('notes_not_ready')));
            return;
        }

        var query = params();
        var lists = { mine: [], shared: [] };
        var current = null;     // the note open, with its text
        var field = null;       // the writing box of the open note
        var dirty = false;
        var saving = null;
        var saveTimer = 0;
        var search = '';
        var pollTimer = 0;
        var claudeWaiting = false;
        var aiKicking = false;
        var aiKickAt = 0;

        var bar = el('nav', 'pg-toolbar navigation mb-5');
        var layout = el('div', 'row g-7 ws-nb');
        var listCol = el('div', 'lg:col-4 xl:col-3 ws-nb-list-col');
        var paneCol = el('div', 'lg:col-8 xl:col-9 ws-nb-pane-col');
        var list = el('div', 'ws-nb-list');
        var pane = el('div', 'ws-nb-pane');

        listCol.appendChild(list);
        paneCol.appendChild(pane);
        layout.appendChild(listCol);
        layout.appendChild(paneCol);
        root.appendChild(bar);
        root.appendChild(layout);

        // ── toolbar ──

        var newNote = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('notes_new'), 'bi-plus-lg');
        newNote.addEventListener('click', function () { startNew(); });
        bar.appendChild(newNote);

        // My Notes pinned to the panel menu, right under the workspace: the
        // person's own choice, shown once the list says it can be made.
        var menuPin = button('btn btn-sm btn-text theme-secondary d-none ws-nb-menu-pin', '', 'bi-pin-angle');
        var menuPinned = false;

        function drawMenuPin() {
            menuPin.innerHTML = '';
            menuPin.appendChild(icon(menuPinned ? 'bi-pin-angle-fill' : 'bi-pin-angle', 'me-1'));
            menuPin.appendChild(document.createTextNode(menuPinned ? t('notes_menu_unpin') : t('notes_menu_pin')));
            menuPin.classList.toggle('active', menuPinned);
            menuPin.setAttribute('aria-pressed', menuPinned ? 'true' : 'false');
        }

        // The row in the menu, added or taken away without a reload; the
        // next page draws it on the server the same way (includes/fn/output.php).
        function drawMenuRow(svg) {
            // The pinned row's panel, if open, goes back to the rows first:
            // without its row it would stay open, and be kept for the tab.
            var openPanel = document.querySelector('#menu_panel_24.is-open [data-pg-menu-back]');

            if (openPanel && !menuPinned) {
                openPanel.click();
            }

            Array.prototype.forEach.call(document.querySelectorAll('#menu_item_24'), function (row) {
                row.remove();
            });

            Array.prototype.forEach.call(document.querySelectorAll('#menu_item_23'), function (workspace) {
                if (!menuPinned) {
                    workspace.classList.add('active');
                    return;
                }

                var row = el('a', 'list-group-item list-group-item-action border-0 no-popover active');
                var label = el('span', 'list-group-text', t('notes_menu_label'));
                // The panel of the pinned row (My Notes, Add Note) is drawn by
                // the server whenever the notes are open to this person, pinned
                // or not (output_menu()), so the new row opens it like any other.
                var panel = document.getElementById('menu_panel_24');

                row.id = 'menu_item_24';
                row.href = CFG.urls.notes;
                row.title = t('notes_menu_label');

                if (workspace.getAttribute('target')) {
                    row.setAttribute('target', workspace.getAttribute('target'));
                }

                row.innerHTML = svg || '';

                if (!row.firstChild) {
                    row.appendChild(icon('bi-journal-text', 'me-3 workspace-color'));
                }

                row.appendChild(label);

                if (panel) {
                    row.classList.add('pg-menu-parent');
                    row.setAttribute('data-pg-menu-panel', panel.id);
                    row.setAttribute('aria-controls', panel.id);
                    row.setAttribute('aria-expanded', 'false');
                    row.appendChild(el('span', 'pg-menu-hint'));
                    row.appendChild(icon('bi-chevron-right', 'pg-menu-chevron'));
                }
                workspace.classList.remove('active');
                workspace.parentNode.insertBefore(row, workspace.nextSibling);
            });
        }

        menuPin.addEventListener('click', function () {
            var wanted = !menuPinned;

            menuPin.disabled = true;

            api('ws_note_menu_pin', { pinned: wanted ? 1 : 0 }).then(function (data) {
                menuPinned = !!data.pinned;
                drawMenuPin();
                drawMenuRow(data.icon || '');
                toast(menuPinned ? t('notes_menu_pinned') : t('notes_menu_unpinned'));
            }).catch(fail).then(function () {
                menuPin.disabled = false;
            });
        });

        bar.appendChild(menuPin);

        // Every note as a zip that opens as a vault in Obsidian and the like.
        if (CFG.urls.notes_export) {
            var exportAll = button('btn btn-sm btn-text theme-secondary', t('notes_export_all'), 'bi-file-earmark-zip');
            exportAll.addEventListener('click', function () { window.location.href = CFG.urls.notes_export + '?all=1'; });
            bar.appendChild(exportAll);
        }

        // Notes from another app: Markdown and text files, or a zip of them
        // (workspace_notes_import.php).
        if (CFG.urls.notes_import && CFG.notes_import) {
            var importNotes = button('btn btn-sm btn-text theme-secondary', t('notes_import'), 'bi-box-arrow-in-down');
            importNotes.addEventListener('click', function () { save().then(openImport); });
            bar.appendChild(importNotes);
        }

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var searchGroup = el('div', 'input-group input-group-sm rounded-pill pg-toolbar-search');
        var searchInput = el('input', 'form-control');
        searchInput.type = 'search';
        searchInput.placeholder = t('notes_search');
        searchInput.setAttribute('aria-label', searchInput.placeholder);
        searchInput.addEventListener('input', debounce(function () {
            search = searchInput.value.trim();
            loadList();
        }, 300));
        searchGroup.appendChild(searchInput);
        bar.appendChild(searchGroup);

        // ── the lists ──

        function noteMenu(note) {
            var items = [{ header: note.name }];
            var mine = (note.access === 'owner');

            items.push({ icon: 'bi-box-arrow-in-right', label: t('notes_open'), action: function () { openNote(note.id); } });

            if (mine) {
                items.push({ icon: note.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle', label: note.pinned ? t('notes_unpin') : t('notes_pin'), action: function () { pin(note); } });
                items.push('-');
                items.push({ icon: 'bi-person-plus', label: t('notes_share_people'), action: function () { sharePeople(note); } });
                items.push({ icon: 'bi-hash', label: t('notes_share_channel'), action: function () { shareChannel(note); } });
                items.push({ icon: 'bi-plus-square', label: t('notes_to_channel'), action: function () { toChannel(note); } });
            }

            items.push({ icon: 'bi-link-45deg', label: t('notes_copy_link'), action: function () { copyText(absoluteUrl(CFG.urls.notes + '?note=' + note.id)); } });

            // The note as a file for another app, or as a page to print
            // (workspace_notes_export.php).
            if (CFG.urls.notes_export) {
                items.push('-');
                items.push({ icon: 'bi-filetype-md', label: t('notes_export_md'), action: function () { window.location.href = CFG.urls.notes_export + '?note=' + note.id; } });
                items.push({ icon: 'bi-printer', label: t('notes_print'), action: function () { window.open(CFG.urls.notes_export + '?note=' + note.id + '&print=1', '_blank', 'noopener'); } });
            }

            // A meeting note's decisions and action items (meetings.php).
            if (CFG.meetings && (mine || note.access === 'edit')) {
                items.push({ icon: 'bi-box-arrow-in-right', label: t('meeting_transfer'), action: function () { meetingTransfer(note); } });
            }

            items.push('-');

            if (mine) {
                items.push({ icon: 'bi-trash', label: t('notes_delete'), danger: true, action: function () { removeNote(note); } });
            } else {
                items.push({ icon: 'bi-box-arrow-left', label: t('notes_leave'), danger: true, action: function () { leaveNote(note); } });
            }

            return items;
        }

        function drawSection(title, notes, emptyText) {
            var section = el('div', 'ws-nb-section');
            var head = el('div', 'ws-nb-section-title');

            head.appendChild(el('span', '', title));

            if (notes.length) {
                head.appendChild(el('span', 'ws-nb-count', String(notes.length)));
            }

            section.appendChild(head);

            if (!notes.length) {
                section.appendChild(el('div', 'ws-nb-section-empty', emptyText));
            }

            notes.forEach(function (note) {
                // What is new is new until the note is opened; the open one
                // is being read.
                var open = current && current.id === note.id;
                var unread = !open && !!note.unread;
                var item = el('div', 'ws-nb-item' + (open ? ' active' : '') + (unread ? ' ws-nb-item-unread' : ''));
                item.tabIndex = 0;
                item.setAttribute('role', 'button');

                var top = el('div', 'ws-nb-item-head');
                top.appendChild(icon(note.access === 'owner' ? (note.source ? 'bi-chat-square-quote' : 'bi-journal-text') : 'bi-people'));
                top.appendChild(el('span', 'ws-nb-item-name', note.name));

                if (unread) {
                    var dot = el('span', 'ws-nb-item-dot');
                    dot.title = t('notes_news');
                    dot.setAttribute('aria-label', t('notes_news'));
                    top.appendChild(dot);
                }

                if (note.pinned) {
                    var pinMark = icon('bi-pin-angle-fill', 'ws-nb-item-pin');
                    pinMark.title = t('notes_pinned');
                    top.appendChild(pinMark);
                }

                top.appendChild(el('span', 'ws-nb-item-time', note.updated));

                var more = button('ws-nb-item-more', '', 'bi-three-dots', t('more'));
                more.addEventListener('click', function (event) {
                    var box = more.getBoundingClientRect();

                    event.stopPropagation();
                    ctxMenu(box.left, box.bottom + 4, noteMenu(note));
                });
                top.appendChild(more);
                item.appendChild(top);

                if (note.excerpt && note.excerpt !== note.name) {
                    item.appendChild(el('div', 'ws-nb-item-excerpt', note.excerpt));
                }

                var meta = el('div', 'ws-nb-item-meta');

                if ((note['with'] || []).length || (note['in'] || []).length || note.access !== 'owner') {
                    meta.appendChild(noteFaces(note, 4));
                }

                if (note.access !== 'owner' && note.owner) {
                    meta.appendChild(el('span', 'text-truncate', t('notes_owner', note.owner.name)));
                } else if (note.source && note.source.channel) {
                    meta.appendChild(el('span', 'text-truncate', '#' + note.source.channel.name));
                }

                // Claude: still on a line asked in it, or its answer waiting
                // to be written in when the note is opened.
                if (note.claude_state === 'answered' || note.claude_state === 'waiting') {
                    var claudeTag = el('span', 'ws-nb-item-claude ws-nb-item-claude-' + note.claude_state);
                    claudeTag.appendChild(icon('bi-stars'));
                    claudeTag.appendChild(document.createTextNode(' ' + t(note.claude_state === 'answered' ? 'notes_claude_answered' : 'notes_claude_working')));
                    meta.appendChild(claudeTag);
                }

                if (meta.childNodes.length) {
                    item.appendChild(meta);
                }

                item.addEventListener('click', function () {
                    if (!current || current.id !== note.id) {
                        openNote(note.id);
                    }
                });
                item.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        openNote(note.id);
                    }
                });
                onContext(item, function () { return noteMenu(note); });

                section.appendChild(item);
            });

            return section;
        }

        function drawList() {
            clear(list);

            if (!lists.mine.length && !lists.shared.length && !search) {
                var intro = el('div', 'ws-nb-intro');
                intro.appendChild(emptyState('notes', t('notes_none')));
                intro.appendChild(el('div', 'small fg-secondary', t('notes_intro')));
                list.appendChild(intro);
            }

            list.appendChild(drawSection(t('notes_mine'), lists.mine, search ? t('notes_empty_search') : t('notes_empty')));
            list.appendChild(drawSection(t('notes_shared'), lists.shared, search ? t('notes_empty_search') : t('notes_empty_shared')));
        }

        function loadList() {
            return api('ws_notes', { search: search }).then(function (data) {
                lists.mine = data.mine || [];
                lists.shared = data.shared || [];

                if (data.menu) {
                    menuPinned = !!data.menu.pinned;
                    menuPin.classList.toggle('d-none', !data.menu.ready);
                    drawMenuPin();
                }

                drawList();
            }).catch(fail);
        }

        function listed(note) {
            ['mine', 'shared'].forEach(function (key) {
                lists[key] = lists[key].map(function (item) { return (item.id === note.id) ? note : item; });
            });
        }

        function remember(noteId) {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', CFG.urls.notes + (noteId ? '?note=' + noteId : ''));
            }
        }

        // ── saving ──

        var status = null;
        var lastChange = null;

        function setStatus(state) {
            if (!status) {
                return;
            }

            status.textContent = state === 'saving' ? t('notes_saving') : (state === 'unsaved' ? t('notes_unsaved') : (state === 'saved' ? t('notes_saved') : ''));
            status.className = 'ws-nb-status ws-nb-status-' + state;
        }

        function drawLastChange() {
            if (!lastChange) {
                return;
            }

            lastChange.textContent = (current && current.updated_by) ? t('notes_last_change', current.updated_by.name, current.updated) : '';
        }

        function payload(force) {
            return {
                note_id: current.id || 0,
                title: current.titleInput ? current.titleInput.value : current.title,
                body: field ? field.value() : (current.body || ''),
                // The revision catches any change since it was read, the
                // person's own on another screen or device included; base is
                // for a site whose schema step has not run yet.
                revision: force ? 0 : (current.revision || 0),
                base: force ? 0 : (current.updated_at || 0)
            };
        }

        function changed() {
            if (!current || !editable()) {
                return;
            }

            dirty = true;
            setStatus('unsaved');
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () { save(); }, 900);
        }

        function editable() {
            return current && (current.access === 'owner' || current.access === 'edit' || !current.id);
        }

        function save(force) {
            clearTimeout(saveTimer);

            if (!current || !dirty || !editable()) {
                return saving || Promise.resolve();
            }

            if (saving) {
                return saving.then(function () { return save(force); });
            }

            var note = current;
            var sent = payload(force);

            dirty = false;
            setStatus('saving');

            saving = api('ws_note_save', sent).then(function (data) {
                saving = null;

                var wasNew = !note.id;

                ['id', 'name', 'updated', 'updated_at', 'revision', 'updated_by', 'created', 'created_at', 'versions', 'access', 'owner', 'with', 'in', 'pinned', 'excerpt'].forEach(function (key) {
                    note[key] = data.note[key];
                });

                if (wasNew) {
                    lists.mine.unshift(data.note);
                } else {
                    listed(data.note);
                }

                drawList();

                if (note === current) {
                    if (wasNew) {
                        remember(note.id);
                        drawHead();
                    }

                    drawLastChange();
                    setStatus(dirty ? 'unsaved' : 'saved');
                }

                return (dirty && note === current) ? save() : null;
            }).catch(function (error) {
                saving = null;

                if (note === current) {
                    dirty = true;
                    setStatus('unsaved');
                }

                if (error.field === 'conflict') {
                    conflict(error.message);
                } else {
                    fail(error);
                }
            });

            return saving;
        }

        // Somebody else saved the note since it was opened here.
        function conflict(message) {
            var box = pane.querySelector('.ws-nb-conflict');

            if (box) {
                return;
            }

            box = el('div', 'alert theme-warning d-flex flex-wrap align-items-center gap-3 py-3 ws-nb-conflict');
            box.appendChild(el('span', 'me-auto', message || t('notes_conflict')));

            var load = button('btn btn-sm btn-outline theme-secondary rounded-pill', t('notes_conflict_load'));
            load.addEventListener('click', function () {
                dirty = false;
                box.remove();
                openNote(current.id, true);
            });
            box.appendChild(load);

            var keep = button('btn btn-sm btn-solid theme-warning rounded-pill', t('notes_conflict_keep'));
            keep.addEventListener('click', function () {
                box.remove();
                dirty = true;
                save(true);
            });
            box.appendChild(keep);

            var card = pane.querySelector('.ws-nb-card');

            if (card) {
                card.insertBefore(box, card.querySelector('.ws-nb-body'));
            }
        }

        // What was typed in the last second still reaches the server when the
        // page is left.
        window.addEventListener('beforeunload', function () {
            if (current && dirty && editable() && navigator.sendBeacon) {
                var data = payload(false);
                data.action = 'ws_note_save';
                data.token = CFG.token;

                try {
                    navigator.sendBeacon(CFG.api_url, new Blob([JSON.stringify(data)], { type: 'application/json' }));
                } catch (error) {
                    // Nothing more can be done while the page is closing.
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S') && current && editable()) {
                event.preventDefault();
                dirty = true;
                save();
            }
        });

        // Whether the stored note moved on from the one held here: by its
        // revision, which a pin or an import's link rewrite moves too, else
        // (before the schema step) by the time of its last change.
        function newer(fresh, held) {
            if (fresh.revision && held.revision) {
                return fresh.revision > held.revision;
            }

            return fresh.updated_at > held.updated_at;
        }

        // Somebody else's change comes in while the note is open and nothing
        // was typed here since.
        function poll() {
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, claudeWaiting ? 5000 : 15000);

            if (!current || !current.id || dirty || saving || document.hidden) {
                return;
            }

            var note = current;

            api('ws_note_get', { note_id: note.id, seen: 1 }).then(function (data) {
                if (note !== current || dirty || saving) {
                    return;
                }

                var fresh = data.note;

                if (newer(fresh, note)) {
                    var who = fresh.updated_by ? fresh.updated_by.name : '';

                    current = fresh;
                    listed(fresh);
                    drawList();
                    drawNote();

                    if (fresh.updated_by && fresh.updated_by.id !== CFG.me) {
                        toast(t('notes_changed_outside', who));
                    }
                }

                claudeCheck(fresh.claude || []);
            }).catch(function () {});
        }

        // ── Claude in the note ──

        // The tags that ask an assistant in a note: Claude's and Pinegrap
        // AI's, whichever are connected.
        function assistantTokens() {
            return [BOOT.claude && BOOT.claude.token, BOOT.ai && BOOT.ai.token].filter(function (token) { return !!token; });
        }

        // Pinegrap AI answers only while a screen carries its work on.
        function kickAiFromNote() {
            if (aiKicking || (Date.now() - aiKickAt) < 4000) {
                return;
            }

            aiKicking = true;
            aiKickAt = Date.now();

            api('ws_ai_kick').then(function () {
                aiKicking = false;
                poll();
            }).catch(function () {
                aiKicking = false;
            });
        }

        // The lines that ask Claude are sent when one of them is finished with
        // Enter; the same line is never asked twice.
        function askClaude() {
            var tokens = assistantTokens();

            if (!current || !field || !tokens.length) {
                return;
            }

            var lines = field.value().split('\n').filter(function (line) {
                return tokens.some(function (token) { return line.indexOf(token) !== -1; });
            });

            if (!lines.length) {
                return;
            }

            save().then(function () {
                if (!current || !current.id) {
                    return;
                }

                return api('ws_note_claude', { note_id: current.id, lines: lines }).then(function (data) {
                    claudeCheck(data.claude || []);
                });
            }).catch(fail);
        }

        // Answers that came back go into the note under the line that asked;
        // requests still under way show that Claude is on it.
        function claudeCheck(requests) {
            var waiting = requests.filter(function (request) { return ['queued', 'sent', 'running'].indexOf(request.status) !== -1; });
            var done = requests.filter(function (request) { return ['answered', 'failed'].indexOf(request.status) !== -1; });
            var note = current;
            var line = pane.querySelector('.ws-nb-claude');

            claudeWaiting = waiting.length > 0;

            var aiWaiting = waiting.filter(function (request) { return request.agent === 'ai'; });

            if (aiWaiting.length) {
                kickAiFromNote();
            }

            if (line) {
                line.classList.toggle('d-none', !claudeWaiting);

                // Only Pinegrap AI on it: its own words on the line.
                var words = line.querySelector('.ws-nb-claude-words');

                if (words) {
                    words.textContent = (aiWaiting.length && aiWaiting.length === waiting.length) ? t('notes_ai_waiting') : t('notes_claude_waiting');
                }
            }

            if (claudeWaiting) {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(poll, 5000);
            }

            if (!done.length || !note || !editable()) {
                return;
            }

            save().then(function () {
                var chain = Promise.resolve(null);

                done.forEach(function (request) {
                    chain = chain.then(function () {
                        return api('ws_note_claude_deliver', { note_id: note.id, request_id: request.id }).then(function (data) {
                            if (request.status === 'failed' && request.error) {
                                toast(request.error, 'warning');
                            }

                            return data;
                        });
                    });
                });

                return chain;
            }).then(function (data) {
                if (data && data.note && note === current && !dirty) {
                    current = data.note;
                    listed(data.note);
                    drawList();
                    drawNote();
                }
            }).catch(function () {});
        }

        // ── the note ──

        var headNode = null;
        var infoNode = null;
        var headButtons = {};

        function placeholder() {
            clear(pane);

            var box = el('div', 'ws-nb-placeholder');
            box.appendChild(icon('bi-journal-richtext'));
            box.appendChild(el('div', '', t('notes_pick')));
            pane.appendChild(box);
            root.classList.remove('ws-nb-open');
        }

        function drawHead() {
            if (!headNode || !current) {
                return;
            }

            clear(headNode);

            var back = button('btn btn-sm btn-text theme-secondary lg:d-none', '', 'bi-arrow-left', t('notes_back'));
            back.addEventListener('click', function () {
                save();
                current = null;
                field = null;
                remember(0);
                drawList();
                placeholder();
            });
            headNode.appendChild(back);

            if (current.id) {
                headNode.appendChild(noteFaces(current, 5));
            }

            var title = el('input', 'form-control ws-nb-title');
            title.type = 'text';
            title.maxLength = 200;
            title.placeholder = t('notes_title');
            // Drawn again after a new note's first save: what was typed in
            // the meantime stays, rather than the title the note opened with.
            title.value = (current.titleInput ? current.titleInput.value : current.title) || '';
            title.readOnly = !editable();
            title.setAttribute('aria-label', t('notes_title'));
            title.addEventListener('input', changed);
            current.titleInput = title;
            headNode.appendChild(title);

            status = el('span', 'ws-nb-status');
            headNode.appendChild(status);
            setStatus(dirty ? 'unsaved' : (current.id && editable() ? 'saved' : ''));

            if (current.access === 'owner' || !current.id) {
                var share = button('btn btn-sm btn-text theme-secondary', t('notes_share'), 'bi-send');
                share.disabled = !current.id;
                share.addEventListener('click', function (event) {
                    var box = share.getBoundingClientRect();

                    event.stopPropagation();
                    ctxMenu(box.left, box.bottom + 4, [
                        { icon: 'bi-person-plus', label: t('notes_share_people'), action: function () { sharePeople(current); } },
                        { icon: 'bi-hash', label: t('notes_share_channel'), action: function () { shareChannel(current); } },
                        { icon: 'bi-plus-square', label: t('notes_to_channel'), action: function () { toChannel(current); } }
                    ]);
                });
                headNode.appendChild(share);
                headButtons.share = share;
            }

            // A meeting note (one that tags a plan item and has decisions or
            // action items): its own button to bring them into a channel.
            if (CFG.meetings && current.id && editable() && /<#plan:\d+>/.test(current.body || '')
                && ((current.body || '').indexOf('::: ' + t('meeting_decisions')) !== -1 || (current.body || '').indexOf('::: ' + t('meeting_actions')) !== -1)) {
                var transfer = button('btn btn-sm btn-text theme-secondary', t('meeting_transfer'), 'bi-box-arrow-in-right');
                transfer.addEventListener('click', function () { meetingTransfer(current); });
                headNode.appendChild(transfer);
            }

            // What the note held before: kept on the server, put back as a
            // step the person can undo.
            if (current.id && current.versions) {
                var versions = button('btn btn-sm btn-text theme-secondary', '', 'bi-clock-history', t('notes_versions'));
                versions.addEventListener('click', function () { showVersions(current); });
                headNode.appendChild(versions);
            }

            var more = button('btn btn-sm btn-text theme-secondary', '', 'bi-three-dots', t('more'));
            more.disabled = !current.id;
            more.addEventListener('click', function (event) {
                var box = more.getBoundingClientRect();

                event.stopPropagation();
                ctxMenu(box.left, box.bottom + 4, noteMenu(current));
            });
            headNode.appendChild(more);
            headButtons.more = more;

            drawInfo();
        }

        // Who changed it last, where it came from, who can see it.
        function drawInfo() {
            clear(infoNode);

            lastChange = el('span', 'ws-nb-info-part');
            infoNode.appendChild(lastChange);
            drawLastChange();

            if (current.id && current.created) {
                infoNode.appendChild(el('span', 'ws-nb-info-part', t('notes_created', current.created)));
            }

            if (current.access !== 'owner' && current.owner && current.id) {
                infoNode.appendChild(el('span', 'ws-nb-info-part', t('notes_owner', current.owner.name)));
            }

            if (current.source) {
                var from = el('span', 'ws-nb-info-part');
                from.appendChild(icon('bi-chat-square-quote'));
                from.appendChild(document.createTextNode(current.source.sender ? t('notes_from', current.source.sender.name) : t('notes_go_source')));

                if (current.source.channel) {
                    from.appendChild(document.createTextNode(' ' + t('notes_from_in', '#' + current.source.channel.name)));
                }

                if (current.source.url) {
                    var go = el('a', 'ms-1', t('notes_go_source'));
                    go.href = current.source.url;
                    from.appendChild(go);
                }

                infoNode.appendChild(from);
            }

            (current['in'] || []).forEach(function (channel) {
                if (!channel.name) {
                    return;
                }

                var shared = el('span', 'ws-nb-info-part');
                shared.appendChild(icon('bi-hash'));

                var link = el('a', '', channel.name);
                link.href = channel.url;
                shared.appendChild(link);
                infoNode.appendChild(shared);
            });

            if (current.channel) {
                var made = el('span', 'ws-nb-info-part');
                made.appendChild(icon('bi-plus-square'));
                made.appendChild(document.createTextNode(t('notes_channel_made') + ': '));

                var channelLink = el('a', '', '#' + current.channel.name);
                channelLink.href = current.channel.url;
                made.appendChild(channelLink);
                infoNode.appendChild(made);
            }

            infoNode.classList.toggle('d-none', !infoNode.textContent.trim());
        }

        // The note's earlier versions: one is looked at on the right and may
        // be put back into the writing box, where it is saved as any change
        // and Ctrl+Z takes it back out.
        function showVersions(note) {
            var node = el('dialog', 'dialog ws-nb-versions dialog-xl dialog-scrollable md-down:dialog-fullscreen');
            node.tabIndex = -1;
            node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
                + '<div class="dialog-body"><div class="ws-nb-versions-grid"><div class="list-group ws-nb-versions-list"></div><div class="ws-nb-versions-view"></div></div></div>'
                + '<div class="dialog-footer"></div>';
            node.querySelector('.dialog-title').appendChild(icon('bi-clock-history', 'me-3'));
            node.querySelector('.dialog-title').appendChild(document.createTextNode(t('notes_versions') + ' · ' + note.name));
            node.querySelector('.btn-close').setAttribute('aria-label', t('close'));

            var list = node.querySelector('.ws-nb-versions-list');
            var view = node.querySelector('.ws-nb-versions-view');
            var footer = node.querySelector('.dialog-footer');
            var chosen = null;

            view.appendChild(el('div', 'small fg-secondary', t('notes_version_pick')));

            var close = button('btn btn-sm btn-text theme-secondary', t('close'));
            close.setAttribute('data-bs-dismiss', 'dialog');
            footer.appendChild(close);

            var restore = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('notes_version_restore'), 'bi-arrow-counterclockwise');
            restore.disabled = true;
            restore.classList.toggle('d-none', !editable());
            footer.appendChild(restore);

            restore.addEventListener('click', function () {
                if (!chosen || !field || current !== note || !editable()) {
                    return;
                }

                field.rich.setMarkup(chosen.body || '', chosen.labels || {}, true);
                toast(t('notes_version_restored'));
                pgUI.dialog.hide(node);
            });

            api('ws_note_get', { note_id: note.id, versions: 1 }).then(function (data) {
                var versions = data.versions || [];

                if (!versions.length) {
                    list.appendChild(el('div', 'small fg-secondary p-3', t('notes_versions_none')));
                    return;
                }

                versions.forEach(function (version) {
                    var item = button('list-group-item list-group-item-action ws-nb-version');
                    var top = el('div', 'd-flex justify-content-between gap-3');

                    top.appendChild(el('strong', '', version.saved));
                    top.appendChild(el('span', 'small fg-secondary', t('notes_version_size', version.size)));
                    item.appendChild(top);

                    if (version.by) {
                        item.appendChild(el('div', 'small fg-secondary text-truncate', version.by.name));
                    }

                    item.title = version.saved_full || '';

                    item.addEventListener('click', function () {
                        Array.prototype.forEach.call(list.querySelectorAll('.active'), function (other) {
                            other.classList.remove('active');
                        });

                        item.classList.add('active');

                        api('ws_note_get', { note_id: note.id, version: version.id }).then(function (got) {
                            chosen = got.version;
                            clear(view);

                            if (chosen.title) {
                                view.appendChild(el('div', 'fw-semibold mb-3', chosen.title));
                            }

                            var html = el('div', 'ws-msg-body ws-nb-read');
                            setHtml(html, chosen.html || '');
                            chipMenus(html);
                            view.appendChild(html);
                            restore.disabled = false;
                        }).catch(fail);
                    });

                    list.appendChild(item);
                });
            }).catch(fail);

            document.body.appendChild(node);
            pgUI.dialog.on(node, 'hidden', function () { node.remove(); });
            pgUI.dialog.show(node);
        }

        // Notes brought in from another app, in the steps of
        // workspace_notes_import.php: the files are picked into a list and
        // checked as the server would check them, sent and read through,
        // the person sees what each would become and unticks what they do
        // not want, the rest is written a batch at a time, then the links
        // between the notes are pointed at them.
        function openImport() {
            var limits = CFG.notes_import || {};
            var types = limits.types || [];
            var maxMb = Math.max(1, Math.floor((limits.max || 0) / 1048576));
            // Each file's part headers travel in the body too. The 64 KB the
            // server's request_max keeps covers one file's, not those of
            // hundreds; a file on its own is held to the cap by its size, as
            // upload_max_filesize holds it.
            var partBytes = 1024;
            var kindIcons = { md: 'bi-markdown', markdown: 'bi-markdown', txt: 'bi-file-earmark-text', zip: 'bi-file-earmark-zip', enex: 'bi-journal-text' };
            var node = el('dialog', 'dialog ws-nb-import dialog-lg dialog-scrollable md-down:dialog-fullscreen');
            // Each { file, folder }, kept between the steps: a cancelled or
            // refused upload goes back to the same list.
            var picked = [];
            // The step on screen: pick, send, read, preview or run.
            var phase = 'pick';
            var uploadXhr = null;
            // When Upload was pressed, for the footer's gate below.
            var pressed = 0;
            var stage = '';
            var rows = [];
            var mode = 'update';
            var capacity = 0;
            var running = false;
            var closed = false;

            node.tabIndex = -1;
            node.setAttribute('data-bs-backdrop', 'static');
            // As the file manager's upload window: Escape pressed by habit
            // does not stop an upload.
            node.setAttribute('data-bs-keyboard', 'false');
            node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
                + '<div class="dialog-body"></div><div class="dialog-footer"></div>';
            node.querySelector('.dialog-title').appendChild(icon('bi-box-arrow-in-down', 'me-3'));
            node.querySelector('.dialog-title').appendChild(document.createTextNode(t('notes_import_title')));

            var closeX = node.querySelector('.btn-close');
            var body = node.querySelector('.dialog-body');
            var footer = node.querySelector('.dialog-footer');

            closeX.setAttribute('aria-label', t('close'));

            // Upload's place in the footer is taken at once by Cancel upload,
            // which gets the focus, then by Close and by the preview's
            // Import: the second tap of a double tap, or a second press of
            // Enter, would stop the upload it started, close the dialog or
            // import unseen. Clicks there are dropped for 800 ms after
            // Upload, longer than a double tap or press. A held key repeats
            // its keydown, which a button takes as one more press.
            footer.addEventListener('click', function (event) {
                if (Date.now() - pressed < 800) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);
            footer.addEventListener('keydown', function (event) {
                if (event.repeat && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                }
            }, true);

            // The JSON steps after the upload.
            function send(payload) {
                return fetch(CFG.urls.notes_import, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (response) {
                    return response.json().catch(function () {
                        throw new Error(t('error_generic'));
                    });
                }, function () {
                    // No answer at all: the browser's own words ("Failed to
                    // fetch") are not the person's language.
                    throw new Error(t('notes_import_request_failed'));
                }).then(function (json) {
                    if (!json || json.status !== 'success') {
                        throw new Error((json && json.message) ? json.message : t('error_generic'));
                    }

                    return json.data || {};
                });
            }

            // The upload step as XMLHttpRequest: fetch() says nothing while
            // the body is on its way, and the person would watch a spinner
            // for minutes. The listeners go on before send(), or no progress
            // is reported.
            function sendFiles(form, onProgress, onSent) {
                var xhr = new XMLHttpRequest();
                var done = new Promise(function (resolve, reject) {
                    xhr.open('POST', CFG.urls.notes_import);
                    xhr.upload.addEventListener('progress', function (event) {
                        onProgress(event.lengthComputable ? event.loaded : 0, event.lengthComputable ? event.total : 0);
                    });
                    xhr.upload.addEventListener('load', onSent);
                    xhr.addEventListener('load', function () {
                        var json = null;

                        try {
                            json = JSON.parse(xhr.responseText);
                        } catch (error) {
                            json = null;
                        }

                        if (json && json.status === 'success') {
                            resolve(json.data || {});
                            return;
                        }

                        // Not an answer of ours: a web server or proxy refused
                        // the body before PHP saw it (nginx answers 413, IIS
                        // without its request limit rule 404.13), or a
                        // gateway gave up waiting (504, 524).
                        reject(new Error((json && json.message) ? json.message
                            : ((xhr.status === 413 || xhr.status === 404) ? t('notes_import_too_large_server') : t('error_generic'))));
                    });
                    // No answer: the connection was lost, or the browser
                    // would not send a file (changed on disk since it was
                    // picked).
                    xhr.addEventListener('error', function () {
                        var error = new Error(t('notes_import_request_failed'));

                        error.network = true;
                        reject(error);
                    });
                    xhr.addEventListener('abort', function () {
                        var error = new Error('');

                        error.aborted = true;
                        reject(error);
                    });
                    xhr.send(form);
                });

                return { xhr: xhr, done: done };
            }

            function closeButton(label) {
                var close = button('btn btn-sm btn-text theme-secondary', label || t('cancel'));
                close.setAttribute('data-bs-dismiss', 'dialog');
                return close;
            }

            // Leaving the page while the files travel, are read or are
            // written asks first, as the file manager does. The listener
            // stands only for those steps: one left in place keeps the page
            // out of the browser's back-forward cache.
            function leaveGuard(event) {
                event.preventDefault();
                event.returnValue = t('notes_import_leave');

                return event.returnValue;
            }

            // ── the files ──

            // Sizes in the list and while the files travel, as the file
            // manager writes them (uploadSizeLabel() in view_folders.php).
            // The unit letters are not translated: the server's
            // convert_bytes_to_string() writes the same ones in every
            // language.
            function sizeLabel(bytes) {
                var steps = [[1073741824, ' GB', 1], [1048576, ' MB', 1], [1024, ' KB', 0]];

                for (var i = 0; i < steps.length; i++) {
                    if (bytes >= steps[i][0]) {
                        return (bytes / steps[i][0]).toLocaleString(CFG.locale || undefined, { minimumFractionDigits: steps[i][2], maximumFractionDigits: steps[i][2] }) + steps[i][1];
                    }
                }

                return bytes + ' B';
            }

            // The kind the server reads a file as: its last extension, in
            // lower case (ws_note_import_stage()).
            function kindOf(name) {
                var dot = String(name).lastIndexOf('.');

                return (dot === -1) ? '' : String(name).slice(dot + 1).toLowerCase();
            }

            // What was dropped. A folder arrives as a File as well, passes
            // for one by its name ("Vault.md") and fails the request as a
            // network error, so it is told apart by its entry, which the
            // browser lends only while the drop lasts. Its contents are not
            // walked: PHP keeps only the base names, and max_file_uploads
            // would cut a vault short.
            function dropped(transfer) {
                var items = Array.prototype.slice.call((transfer && transfer.items) || []);
                var out = [];

                if (!items.length || !items[0].webkitGetAsEntry) {
                    return Array.prototype.slice.call((transfer && transfer.files) || []).map(function (file) {
                        return { file: file, folder: false };
                    });
                }

                items.forEach(function (item) {
                    var file = (item.kind === 'file') ? item.getAsFile() : null;
                    var entry = file ? item.webkitGetAsEntry() : null;

                    if (file) {
                        out.push({ file: file, folder: !!(entry && entry.isDirectory) });
                    }
                });

                return out;
            }

            // Tries each file's first byte. A file changed on disk after it
            // was picked can no longer be read through what was picked
            // (Chromium: NotReadableError), and the browser fails every
            // request that carries it (net::ERR_UPLOAD_FILE_CHANGED), so it
            // is marked stale: left out, and replaced when it is picked
            // again. Folders are not passed in. Resolves once each file has
            // answered.
            function probe(entries) {
                return Promise.all(entries.map(function (entry) {
                    return new Promise(function (resolve) {
                        var reader = new FileReader();

                        reader.onload = function () { resolve(); };
                        reader.onerror = function () {
                            entry.stale = true;
                            resolve();
                        };
                        reader.readAsArrayBuffer(entry.file.slice(0, 1));
                    });
                }));
            }

            // Whether two files of one size hold the same bytes, read a
            // megabyte at a time and stopped at the first difference. A file
            // that cannot be read counts as the same, as name, size and time
            // alone decided before.
            function sameBytes(one, two) {
                var step = 1048576;

                function read(blob) {
                    return new Promise(function (resolve, reject) {
                        var reader = new FileReader();

                        reader.onload = function () { resolve(new Uint8Array(reader.result)); };
                        reader.onerror = function () { reject(reader.error); };
                        reader.readAsArrayBuffer(blob);
                    });
                }

                function from(at) {
                    if (at >= one.size) {
                        return Promise.resolve(true);
                    }

                    return Promise.all([read(one.slice(at, at + step)), read(two.slice(at, at + step))]).then(function (parts) {
                        if (parts[0].length !== parts[1].length) {
                            return false;
                        }

                        for (var i = 0; i < parts[0].length; i++) {
                            if (parts[0][i] !== parts[1][i]) {
                                return false;
                            }
                        }

                        return from(at + step);
                    });
                }

                return (one === two) ? Promise.resolve(true) : from(0).catch(function () { return true; });
            }

            // The first of the rows whose file holds what file holds.
            function firstSame(rows, file) {
                if (!rows.length) {
                    return Promise.resolve(null);
                }

                return sameBytes(rows[0].file, file).then(function (same) {
                    return same ? rows[0] : firstSame(rows.slice(1), file);
                });
            }

            // Adds to what is picked already. Each file becomes a note of
            // its own, so a file of a listed name gets a row of its own too:
            // todo.md from two folders are two notes, and the server stages
            // both. The same file again (name, size, time and bytes) takes
            // its row; two copies of one name, size and time that hold
            // different text are two rows. A listed file of that name is
            // replaced only when the browser can no longer read it: it
            // changed on disk, and this is the file picked again. The files
            // of one pick or drop are matched only against the rows listed
            // before it, never with each other.
            function add(entries) {
                var namesakes = picked.filter(function (have) {
                    return !have.folder && entries.some(function (entry) {
                        return entry.file.name === have.file.name;
                    });
                });

                probe(namesakes).then(function () {
                    var known = picked.slice();
                    var taken = [];
                    var chain = Promise.resolve();

                    entries.forEach(function (entry) {
                        chain = chain.then(function () {
                            var file = entry.file;
                            var listed = known.filter(function (have) {
                                return have.file.name === file.name && taken.indexOf(have) === -1;
                            });
                            var alike = listed.filter(function (have) {
                                return !have.stale && !have.folder && have.file.size === file.size && have.file.lastModified === file.lastModified;
                            });

                            return firstSame(alike, file).then(function (row) {
                                row = row || listed.filter(function (have) {
                                    return have.stale;
                                })[0];

                                if (row) {
                                    taken.push(row);
                                    picked[picked.indexOf(row)] = entry;
                                } else {
                                    picked.push(entry);
                                }
                            });
                        });
                    });

                    // Upload pressed while the files were read: the list
                    // shows them when it comes back.
                    chain.then(function () {
                        if (phase === 'pick') {
                            drawPick().focus();
                        }
                    });
                });
            }

            // What the server would do with the picked files, asked before a
            // byte is sent. A folder, a file of another kind or one over the
            // limit is left out, as the file manager does, and so is one the
            // browser can no longer read (probe()). Over the files
            // that go: none left, then PHP's file count (max_file_uploads,
            // checked only here; the stage never sees the dropped files),
            // then the rest of ws_note_import_stage()'s rules in its order.
            // The first that fails holds the upload back.
            function check() {
                var result = { files: [], total: 0, over: 0, blocked: 0, stale: 0, error: '' };
                var zips = 0;
                var exports = 0;

                picked.forEach(function (entry) {
                    var kind = kindOf(entry.file.name);

                    entry.state = '';

                    if (entry.folder || types.indexOf(kind) === -1) {
                        entry.state = 'blocked';
                        result.blocked++;
                    } else if (entry.stale) {
                        entry.state = 'stale';
                        result.stale++;
                    } else if (limits.max && entry.file.size > limits.max) {
                        entry.state = 'over';
                        result.over++;
                    } else {
                        result.files.push(entry.file);
                        result.total += entry.file.size;
                        zips += (kind === 'zip') ? 1 : 0;
                        exports += (kind === 'enex') ? 1 : 0;
                    }
                });

                var count = result.files.length;

                if (!count) {
                    result.error = result.blocked ? t('notes_import_choose') : (result.over ? t('notes_import_too_large', maxMb) : '');
                } else if (limits.files && count > limits.files) {
                    // The server takes only so many files in one request and
                    // drops the rest without a word.
                    result.error = t('notes_import_too_many', limits.files);
                } else if (limits.max && result.total + ((count > 1) ? count * partBytes : 0) > limits.max) {
                    result.error = t('notes_import_too_large', maxMb);
                } else if (zips && count > 1) {
                    result.error = t('notes_import_zip_alone');
                } else if (exports && exports < count) {
                    result.error = t('notes_import_enex_alone');
                }

                return result;
            }

            // The drop area, then the picked files as the file manager lists
            // them (renderUploadPicked() in view_folders.php): what will not
            // be sent is said before the upload, while it can still be
            // changed. A failed upload comes back here with its message and
            // the same list, so Upload tries again. Returns what takes the
            // focus after a change: Upload, or the picker while nothing can
            // be sent (the redraw removed the control that had it).
            function drawPick(error) {
                var result = check();

                phase = 'pick';
                clear(body);
                clear(footer);

                body.appendChild(el('p', 'small fg-secondary', t('notes_import_intro')));

                var drop = el('label', 'ws-nb-import-drop');
                var input = el('input', 'visually-hidden');

                input.type = 'file';
                input.multiple = true;
                input.accept = types.map(function (kind) { return '.' + kind; }).join(',');
                drop.appendChild(icon('bi-cloud-arrow-up'));
                drop.appendChild(el('span', 'fw-semibold', t('notes_import_pick')));
                drop.appendChild(el('span', 'small fg-secondary', t('notes_import_limit', maxMb)));

                // Said up front: at PHP's default of 20 files a request it is
                // the limit people meet first.
                if (limits.files) {
                    drop.appendChild(el('span', 'small fg-secondary', t('notes_import_too_many', limits.files)));
                }

                drop.appendChild(input);

                // The files are taken before the list is drawn again, which
                // puts a new, empty picker in place.
                input.addEventListener('change', function () {
                    add(Array.prototype.slice.call(input.files || []).map(function (file) {
                        return { file: file, folder: false };
                    }));
                });

                drop.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    drop.classList.add('ws-nb-import-over');
                });
                drop.addEventListener('dragleave', function () { drop.classList.remove('ws-nb-import-over'); });
                drop.addEventListener('drop', function (event) {
                    event.preventDefault();
                    drop.classList.remove('ws-nb-import-over');
                    add(dropped(event.dataTransfer));
                });

                body.appendChild(drop);

                if (picked.length) {
                    var head = el('div', 'd-flex align-items-baseline gap-3 mt-5 mb-1');
                    var heading = t('notes_import_picked', picked.length, sizeLabel(result.total));
                    var clearAll = button('btn btn-sm btn-link p-0', t('notes_import_clear'));
                    var list = el('div', 'list-group ws-nb-import-list');

                    if (result.over) {
                        heading += ' — ' + t('notes_import_over', result.over);
                    }

                    if (result.blocked) {
                        heading += ' — ' + t('notes_import_blocked', result.blocked);
                    }

                    if (result.stale) {
                        heading += ' — ' + t('notes_import_stale', result.stale);
                    }

                    clearAll.addEventListener('click', function () {
                        picked = [];
                        drawPick().focus();
                    });

                    head.appendChild(el('span', 'small fw-semibold flex-grow-1', heading));
                    head.appendChild(clearAll);

                    // What a row left out says in place of its size; one over
                    // the limit keeps its size, in red.
                    var reasons = { blocked: t('notes_import_not_allowed'), stale: t('notes_import_unreadable') };

                    picked.forEach(function (entry) {
                        var row = el('div', 'list-group-item d-flex gap-3 align-items-start' + (entry.state ? ' ws-nb-import-row ws-nb-import-off' : ''));
                        var main = el('div', 'ws-nb-import-main');
                        var line = el('div', 'ws-nb-import-path');
                        var glyph = entry.folder ? 'bi-folder-x' : (entry.state === 'blocked' ? 'bi-file-earmark-x' : (kindIcons[kindOf(entry.file.name)] || 'bi-file-earmark'));

                        main.appendChild(el('div', 'fw-semibold text-truncate', entry.file.name));

                        if (entry.state) {
                            line.appendChild(el('span', 'ws-nb-import-kind ws-nb-import-error', reasons[entry.state] || sizeLabel(entry.file.size)));
                        } else {
                            line.textContent = sizeLabel(entry.file.size);
                        }

                        main.appendChild(line);
                        row.appendChild(icon(glyph, 'mt-1'));
                        row.appendChild(main);
                        list.appendChild(row);
                    });

                    body.appendChild(head);
                    body.appendChild(list);
                }

                // A refused upload is said ahead of the list's own rule: it
                // names the files that could not be read, which the rule
                // does not. It is read out once; the rule is drawn again
                // with every change, and would be read out again each time.
                // The next change redraws with no refusal, so the rule's
                // message comes back.
                var said = error || result.error;

                if (said) {
                    var notice = el('div', 'alert theme-danger small mt-5 mb-0', said);

                    if (error) {
                        notice.setAttribute('role', 'alert');
                    }

                    body.appendChild(notice);
                }

                var go = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('notes_import_upload'), 'bi-cloud-arrow-up');

                go.disabled = !result.files.length || !!result.error;
                go.addEventListener('click', upload);
                footer.appendChild(closeButton());
                footer.appendChild(go);

                return go.disabled ? input : go;
            }

            // ── the upload ──

            // The bytes on their way: the file manager's label and a bar
            // that moves with them, then the reading look once the last byte
            // has left. Cancel goes back to the list with the files kept.
            function drawSending() {
                phase = 'send';
                clear(body);
                clear(footer);

                var row = el('div', 'd-flex justify-content-between gap-3 small fg-secondary mb-3');
                var left = el('span');
                var label = el('span', '', t('uploading'));
                var percent = el('span');
                var bytes = el('span');
                var bar = progressBar({ percent: 0 });
                var fill = bar.firstChild;
                var stop = button('btn btn-sm btn-text theme-secondary', t('notes_import_cancel_upload'));

                // The step is announced, not every percent.
                label.setAttribute('role', 'status');
                bar.setAttribute('aria-label', t('uploading'));
                left.appendChild(label);
                left.appendChild(document.createTextNode(' '));
                left.appendChild(percent);
                row.appendChild(left);
                row.appendChild(bytes);
                body.appendChild(row);
                body.appendChild(bar);

                // The list comes back once, where the request ends.
                stop.addEventListener('click', function () {
                    if (uploadXhr) {
                        uploadXhr.abort();
                    }
                });
                footer.appendChild(stop);
                stop.focus();

                // No figures to show (a browser that cannot tell the size,
                // or the server at work): a moving bar.
                function moving() {
                    fill.className = 'progress-bar progress-bar-striped progress-bar-animated';
                    fill.style.width = '100%';
                    bar.removeAttribute('aria-valuenow');
                    percent.textContent = '';
                    bytes.textContent = '';
                }

                return {
                    progress: function (loaded, total) {
                        if (!total) {
                            moving();
                            return;
                        }

                        var share = Math.min(100, Math.round(100 * loaded / total));

                        fill.style.width = share + '%';
                        bar.setAttribute('aria-valuenow', String(share));
                        percent.textContent = t('percent', share);
                        bytes.textContent = t('notes_import_sent', sizeLabel(loaded), sizeLabel(total));
                    },
                    // All sent; the server stages and reads the files. Closing
                    // now lets the request finish, and its stage is dropped
                    // when the answer comes.
                    reading: function () {
                        var close = closeButton();

                        phase = 'read';
                        label.textContent = t('notes_import_reading');
                        bar.setAttribute('aria-label', t('notes_import_reading'));
                        moving();
                        clear(footer);
                        footer.appendChild(close);
                        close.focus();
                    }
                };
            }

            function upload() {
                var result = check();

                if (!result.files.length || result.error) {
                    return;
                }

                pressed = Date.now();

                // The entries that go: check() left the state of each empty.
                var going = picked.filter(function (entry) { return !entry.state; });
                var form = new FormData();

                form.append('step', 'upload');
                form.append('token', CFG.token);
                result.files.forEach(function (file) { form.append('files[]', file, file.name); });

                var view = drawSending();

                // A progress or load event may still come after a cancel or
                // after the answer (the order differs between browsers); it
                // must not draw over the list or the preview.
                var request = sendFiles(form, function (loaded, total) {
                    if (phase === 'send' && request.xhr === uploadXhr) {
                        view.progress(loaded, total);
                    }
                }, function () {
                    if (phase === 'send' && request.xhr === uploadXhr) {
                        view.reading();
                    }
                });

                uploadXhr = request.xhr;
                window.addEventListener('beforeunload', leaveGuard);

                request.done.then(function (data) {
                    // Closed while the files were read: nothing is kept.
                    if (closed) {
                        send({ step: 'discard', token: CFG.token, stage: data.stage || '' }).catch(function () {});
                        return;
                    }

                    stage = data.stage || '';
                    rows = data.rows || [];
                    capacity = data.capacity || 0;
                    drawPreview(data);
                }).catch(function (error) {
                    if (closed) {
                        return;
                    }

                    // An answer is said as it came. Cancelled: nothing is
                    // lost, so nothing is said.
                    if (!error.network) {
                        drawPick(error.aborted ? '' : error.message).focus();
                        return;
                    }

                    // A file changed on disk since it was picked fails each
                    // try the same way. Those the browser cannot read are
                    // named, and marked and left out in the list; picking
                    // one again puts it back.
                    return probe(going).then(function () {
                        var unread = going.filter(function (entry) { return entry.stale; }).map(function (entry) {
                            return t('notes_import_cannot_read', entry.file.name);
                        });

                        if (!closed) {
                            drawPick(unread.length ? unread.join(' ') : error.message).focus();
                        }
                    });
                }).then(function () {
                    uploadXhr = null;
                    window.removeEventListener('beforeunload', leaveGuard);
                });
            }

            // ── what each file would become ──

            // A row writes a new note when it is not one exported from here
            // that the person may bring up to date, or when they asked for
            // copies.
            function makesNote(row) {
                return row.kind === 'new' || (mode === 'new' && (row.kind === 'update' || row.kind === 'same'));
            }

            function chosenNew() {
                return rows.filter(function (row) { return row.checked && makesNote(row); }).length;
            }

            // Ticked rows that would make more notes than the person may
            // still keep are unticked, the last ones first.
            function fit() {
                var room = capacity;

                rows.forEach(function (row) {
                    if (row.checked && makesNote(row)) {
                        row.checked = (room > 0);
                        room--;
                    }
                });
            }

            function drawPreview(data) {
                phase = 'preview';
                clear(body);
                clear(footer);

                var counts = { 'new': 0, update: 0, same: 0, error: 0 };

                rows.forEach(function (row) {
                    counts[row.kind] = (counts[row.kind] || 0) + 1;
                    row.checked = (row.kind === 'new' || row.kind === 'update');
                });

                fit();

                var head = el('div', 'd-flex flex-wrap align-items-center gap-3 mb-3');
                var found = el('span', 'fw-semibold me-auto', t('notes_import_found', rows.length));

                // The reading step's button had the focus and went with the
                // redraw. The heading takes it, below, and is read out; not
                // Import, which a stray Enter would press unseen.
                found.tabIndex = -1;
                head.appendChild(found);

                ['new', 'update', 'same', 'error'].forEach(function (kind) {
                    if (counts[kind]) {
                        head.appendChild(el('span', 'badge rounded-pill ws-nb-import-badge ws-nb-import-' + kind, t('notes_import_count_' + kind, counts[kind])));
                    }
                });

                body.appendChild(head);

                if (data.more) {
                    body.appendChild(el('div', 'alert theme-warning small py-3', t('notes_import_more', data.more, rows.length)));
                }

                // Files exported from here: brought up to date, or copies.
                if (counts.update || counts.same) {
                    var modeRow = el('div', 'd-flex flex-wrap align-items-center gap-3 mb-3');
                    var select = el('select', 'form-control form-control-sm w-auto');
                    var modeLabel = el('label', 'small fg-secondary', t('notes_import_mode'));

                    select.id = 'ws-nb-import-mode';
                    modeLabel.htmlFor = select.id;

                    [['update', t('notes_import_mode_update')], ['new', t('notes_import_mode_new')]].forEach(function (option) {
                        var item = el('option', '', option[1]);
                        item.value = option[0];
                        select.appendChild(item);
                    });

                    select.value = mode;
                    select.addEventListener('change', function () {
                        mode = select.value;

                        rows.forEach(function (row) {
                            if (row.kind === 'same') {
                                row.checked = false;
                            }
                        });

                        fit();
                        drawRows();
                    });

                    modeRow.appendChild(modeLabel);
                    modeRow.appendChild(select);
                    body.appendChild(modeRow);
                }

                var roomNote = el('div', 'small fg-emphasis-warning mb-3 d-none');
                body.appendChild(roomNote);

                var all = el('label', 'form-field small mb-1');
                var allBox = el('input', 'check');
                allBox.type = 'checkbox';
                all.appendChild(allBox);
                all.appendChild(el('span', '', t('notes_import_all')));
                body.appendChild(all);

                var list = el('div', 'list-group ws-nb-import-list');
                body.appendChild(list);

                var go = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', '', 'bi-box-arrow-in-down');
                var cancel = closeButton();

                footer.appendChild(cancel);
                footer.appendChild(go);

                allBox.addEventListener('change', function () {
                    var room = capacity;

                    rows.forEach(function (row) {
                        if (!selectable(row)) {
                            return;
                        }

                        if (!allBox.checked) {
                            row.checked = false;
                        } else if (makesNote(row)) {
                            row.checked = (room > 0);
                            room--;
                        } else {
                            row.checked = true;
                        }
                    });

                    drawRows();
                });

                go.addEventListener('click', run);

                function selectable(row) {
                    return row.kind !== 'error' && (row.kind !== 'same' || mode === 'new');
                }

                function state(row) {
                    if (row.kind === 'error') {
                        return row.error || t('notes_import_kind_error');
                    }

                    if (mode === 'new' && row.note) {
                        return t('notes_import_kind_copy');
                    }

                    if (row.kind === 'update') {
                        return t('notes_import_kind_update', row.name);
                    }

                    return row.kind === 'same' ? t('notes_import_kind_same') : t('notes_import_kind_new');
                }

                function drawRows() {
                    clear(list);

                    rows.forEach(function (row) {
                        var item = el('label', 'list-group-item ws-nb-import-row' + (selectable(row) ? '' : ' ws-nb-import-off'));
                        var box = el('input', 'check');
                        var main = el('div', 'ws-nb-import-main');
                        var line = el('div', 'ws-nb-import-state');

                        box.type = 'checkbox';
                        box.checked = !!row.checked && selectable(row);
                        box.disabled = !selectable(row);
                        box.addEventListener('change', function () {
                            if (box.checked && makesNote(row) && chosenNew() >= capacity) {
                                box.checked = false;
                                roomNote.classList.remove('d-none');
                                return;
                            }

                            row.checked = box.checked;
                            sum();
                        });

                        main.appendChild(el('div', 'fw-semibold text-truncate', row.title));

                        if (row.path !== row.title) {
                            main.appendChild(el('div', 'ws-nb-import-path', row.path));
                        }

                        line.appendChild(el('span', 'ws-nb-import-kind ws-nb-import-' + ((mode === 'new' && row.note && row.kind !== 'error') ? 'new' : row.kind), state(row)));

                        if (row.files && selectable(row)) {
                            line.appendChild(document.createTextNode(' · '));
                            line.appendChild(icon('bi-paperclip'));
                            line.appendChild(document.createTextNode(t('notes_import_files', row.files)));
                        }

                        main.appendChild(line);

                        (row.warnings || []).forEach(function (warning) {
                            main.appendChild(el('div', 'ws-nb-import-warn', warning));
                        });

                        item.appendChild(box);
                        item.appendChild(main);
                        list.appendChild(item);
                    });

                    sum();
                }

                // The button says how many notes go; the capacity line shows
                // once the person's notes would run over their limit.
                function sum() {
                    var chosen = rows.filter(function (row) { return row.checked && selectable(row); }).length;
                    var selectableCount = rows.filter(selectable).length;

                    clear(go);
                    go.appendChild(icon('bi-box-arrow-in-down', 'me-1'));
                    go.appendChild(document.createTextNode(t('notes_import_go', chosen)));
                    go.disabled = (chosen === 0);

                    roomNote.textContent = t('notes_import_capacity', capacity, limits.limit || '');
                    roomNote.classList.toggle('d-none', rows.filter(function (row) { return selectable(row) && makesNote(row); }).length <= capacity);

                    allBox.checked = selectableCount > 0 && chosen === selectableCount;
                    allBox.indeterminate = chosen > 0 && chosen < selectableCount;
                    allBox.disabled = selectableCount === 0;
                }

                drawRows();
                found.focus();
            }

            // ── writing ──

            function run() {
                var skip = rows.filter(function (row) { return !row.checked; }).map(function (row) { return row.i; });
                var failed = [];

                phase = 'run';
                running = true;
                window.addEventListener('beforeunload', leaveGuard);
                closeX.classList.add('d-none');
                clear(body);
                clear(footer);

                var label = el('div', 'small fg-secondary mb-3', t('notes_import_running', 0, rows.length));
                var bar = el('div', 'progress');
                var fill = el('div', 'progress-bar');

                bar.setAttribute('role', 'progressbar');
                fill.style.width = '0%';
                bar.appendChild(fill);
                body.appendChild(label);
                body.appendChild(bar);

                function next() {
                    return send({ step: 'import', token: CFG.token, stage: stage, mode: mode, skip: skip }).then(function (data) {
                        failed = failed.concat(data.failed || []);
                        label.textContent = t('notes_import_running', data.next || 0, data.total || rows.length);
                        fill.style.width = (data.total ? Math.round(100 * data.next / data.total) : 100) + '%';

                        return data.done ? link() : next();
                    });
                }

                function link() {
                    label.textContent = t('notes_import_linking');

                    return send({ step: 'finish', token: CFG.token, stage: stage }).then(function (data) {
                        return data.done ? data : link();
                    });
                }

                next().then(function (data) {
                    stage = '';
                    drawResult(data, failed);
                }).catch(function (error) {
                    drawResult(null, failed, error.message);
                }).then(function () {
                    running = false;
                    window.removeEventListener('beforeunload', leaveGuard);
                    closeX.classList.remove('d-none');
                    loadList();
                });
            }

            function drawResult(data, failed, error) {
                clear(body);
                clear(footer);

                if (data) {
                    var counts = data.counts || {};
                    var done = el('div', 'd-flex align-items-start gap-3 mb-3');

                    done.appendChild(icon('bi-check-circle-fill', 'fg-success fs-lg'));
                    done.appendChild(el('div', '', t('notes_import_done', counts.added || 0, counts.updated || 0, counts.same || 0)));
                    body.appendChild(done);
                }

                if (error) {
                    body.appendChild(el('div', 'alert theme-danger small', error));
                }

                if (failed.length) {
                    body.appendChild(el('div', 'small fw-semibold mt-3', t('notes_import_failed')));

                    var list = el('ul', 'small mb-0');

                    failed.forEach(function (item) {
                        list.appendChild(el('li', '', item.title + ': ' + item.error));
                    });

                    body.appendChild(list);
                }

                footer.appendChild(closeButton(t('close')));

                if (data && data.first) {
                    var open = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('notes_open'), 'bi-box-arrow-in-right');

                    open.addEventListener('click', function () {
                        pgUI.dialog.hide(node);
                        openNote(data.first, true);
                    });
                    footer.appendChild(open);
                }

                // The Import button run() removed had the focus. What came
                // of the import takes it and is read out.
                var said = body.firstChild;

                if (said) {
                    said.tabIndex = -1;
                    said.focus();
                }
            }

            // Closed before the import ran: the staged upload is dropped.
            pgUI.dialog.on(node, 'hide', function (event) {
                if (running) {
                    event.preventDefault();
                }
            });

            pgUI.dialog.on(node, 'hidden', function () {
                closed = true;
                window.removeEventListener('beforeunload', leaveGuard);

                // Closed while the files travel: the request stops and
                // nothing is staged. Once they are all sent it is left to
                // answer, so that its stage can be dropped.
                if (uploadXhr && phase === 'send') {
                    uploadXhr.abort();
                }

                if (stage) {
                    send({ step: 'discard', token: CFG.token, stage: stage }).catch(function () {});
                }

                node.remove();
            });

            drawPick();
            document.body.appendChild(node);
            pgUI.dialog.show(node);
        }

        // Files dropped on the note or picked: kept for those who may read it,
        // and linked where the caret was.
        function attach(files) {
            // Taken now: the list empties as soon as the picker is reset or
            // the drop is over, before the note has been saved.
            var chosen = Array.prototype.slice.call(files || [], 0, 10);

            if (!current || !editable() || !field || !chosen.length) {
                return;
            }

            save().then(function () {
                if (!current.id) {
                    dirty = true;
                    return save();
                }
            }).then(function () {
                chosen.forEach(function (file) {
                    if (BOOT.upload && BOOT.upload.max && file.size > BOOT.upload.max) {
                        toast(file.name + ': ' + t('file_too_large', Math.floor(BOOT.upload.max / 1048576)), 'danger');
                        return;
                    }

                    var reader = new FileReader();

                    toast(t('notes_uploading') + ' ' + file.name);

                    reader.onload = function () {
                        api('ws_note_file', { note_id: current.id, name: file.name, data: String(reader.result || '') }).then(function (data) {
                            field.rich.insertLink(data.file.name, data.file.url);
                        }).catch(fail);
                    };
                    reader.readAsDataURL(file);
                });
            }).catch(fail);
        }

        var scheduledStrip = null;

        function drawScheduled(items) {
            if (!scheduledStrip) {
                return;
            }

            clear(scheduledStrip);
            scheduledStrip.hidden = !items.length;

            items.forEach(function (item) {
                scheduledStrip.appendChild(scheduledCard(item, function () { refreshScheduled(); }));
            });
        }

        function refreshScheduled() {
            if (!current || !current.id) {
                return;
            }

            var note = current;

            api('ws_note_get', { note_id: note.id }).then(function (data) {
                if (note === current) {
                    current.scheduled = data.note.scheduled || [];
                    drawScheduled(current.scheduled);
                }
            }).catch(fail);
        }

        // The note's row of tools (PGWsEditor.toolbar()): undo and redo, the
        // marks, the cards, tags and assistants, files and emoji. The marks
        // and the steps are the box's own, so they are undone like typing.
        function toolbar() {
            var rich = field.rich;
            var tool = function (iconName, label, action) {
                var node = button('btn btn-sm btn-ghost', '', iconName, label);
                node.addEventListener('click', action);
                return node;
            };
            var scope = function () { return field ? field.value() : ''; };
            var blocks = ['checklist', 'table', 'calc', 'codeblock'];

            if (CFG.blocks) {
                blocks.push(tool('bi-box-arrow-in-down', t('pull_title'), function () {
                    blockPuller(function (data) { field.insertPulled(data); });
                }));
            }

            var tags = [tool('bi-hash', t('notes_tag'), function () { field.insertTrigger('#'); })];

            if (BOOT.claude || BOOT.ai) {
                tags.push(tool(BOOT.claude ? 'bi-stars' : 'bi-cpu', t(BOOT.claude ? 'notes_claude' : 'notes_ai'), function () { field.insertTrigger('@'); }));
            }

            // A scheduled action written in the note (staff).
            if (CFG.scheduled && current && current.id && editable()) {
                tags.push(tool('bi-alarm', t('sa_new'), function () {
                    scheduledForm({ note_id: current.id }, null, function () { refreshScheduled(); });
                }));
            }

            var picker = el('input');
            picker.type = 'file';
            picker.multiple = true;
            picker.className = 'd-none';
            picker.addEventListener('change', function () {
                attach(picker.files);
                picker.value = '';
            });

            var emoji = tool('bi-emoji-smile', t('notes_emoji'), function (event) {
                var box = emoji.getBoundingClientRect();

                event.stopPropagation();
                emojiPicker(box.left, box.bottom + 4, function (picked) { rich.insertText(picked); });
            });

            return window.PGWsEditor.toolbar(rich, [
                ['undo', 'redo'],
                ['bold', 'italic', 'strike', 'highlight'],
                ['bullets', 'numbers', 'quote'],
                blocks,
                tags,
                [picker, tool('bi-paperclip', t('notes_attach'), function () { picker.click(); }), emoji]
            ], {
                className: 'ws-nb-tools',
                groupClass: 'ws-nb-tool-group',
                label: t('tool_bar_note'),
                insertLabel: t('insert_to_note'),
                scope: scope
            });
        }

        // A line that ends with "=" shows what it comes to, worked out by the
        // server as in a message.
        var calcSerial = 0;

        function showResults() {
            if (!field) {
                return;
            }

            var lines = field.rich.lines();
            var texts = lines.map(function (line) { return line.text; });
            var mine = ++calcSerial;

            if (!texts.some(function (text) { return /=\s*$/.test(text); })) {
                field.rich.showResults(lines, []);
                return;
            }

            api('ws_calc', { lines: texts }).then(function (data) {
                if (mine === calcSerial && field) {
                    field.rich.showResults(field.rich.lines(), data.lines || []);
                }
            }).catch(function () {});
        }

        var resultsLater = debounce(showResults, 400);

        function drawNote() {
            clear(pane);
            root.classList.add('ws-nb-open');

            var card = el('div', 'card ws-nb-card');
            headNode = el('div', 'card-header ws-nb-head');
            infoNode = el('div', 'ws-nb-info d-none');

            var body = el('div', 'card-body ws-nb-body');

            card.appendChild(headNode);
            card.appendChild(infoNode);
            card.appendChild(body);
            pane.appendChild(card);
            field = null;
            drawHead();

            if (!editable()) {
                body.appendChild(el('div', 'alert theme-secondary border small py-3', t('notes_read_only')));

                var read = el('div', 'ws-msg-body ws-nb-read');
                setHtml(read, current.html || '');
                chipMenus(read);
                body.appendChild(read);
                return;
            }

            var wrap = el('div', 'ws-nb-editor');

            field = richField(wrap, {
                placeholder: t('notes_text'),
                previews: true,
                // "/" lists the inserts of the box (PGWsEditor).
                slashInserts: { insertLabel: t('insert_to_note') },
                // A picture pasted into the note is attached, as a dropped one.
                pasteFiles: function (files) { attach(files); },
                onKeydown: function (event) {
                    if (event.key === 'Enter' && !event.shiftKey && assistantTokens().length) {
                        setTimeout(function () {
                            if (field && assistantTokens().some(function (token) { return field.value().indexOf(token) !== -1; })) {
                                askClaude();
                            }
                        }, 0);
                    }

                    return false;
                }
            });

            if (!field) {
                return;
            }

            field.notesMode = true;
            field.rich.node.classList.add('ws-nb-text');
            field.rich.node.addEventListener('input', function () {
                changed();
                resultsLater();
            });

            body.appendChild(toolbar());

            // The scheduled actions written in this note, above its text.
            if (CFG.scheduled) {
                scheduledStrip = el('div', 'ws-sa-strip');
                body.appendChild(scheduledStrip);
                drawScheduled(current.scheduled || []);
            }

            wrap.appendChild(field.rich.node);
            body.appendChild(wrap);

            var claudeLine = el('div', 'ws-nb-claude small fg-secondary d-none');
            claudeLine.appendChild(el('span', 'spinner-border spinner-border-sm me-3'));
            claudeLine.appendChild(el('span', 'ws-nb-claude-words', t('notes_claude_waiting')));
            body.appendChild(claudeLine);

            field.set(current.body || '', current.labels || {});
            showResults();

            // Files dropped on the note are attached to it.
            ['dragenter', 'dragover'].forEach(function (name) {
                card.addEventListener(name, function (event) {
                    if (event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') !== -1) {
                        event.preventDefault();
                        card.classList.add('ws-nb-dropping');
                    }
                });
            });

            card.addEventListener('dragleave', function (event) {
                if (!card.contains(event.relatedTarget)) {
                    card.classList.remove('ws-nb-dropping');
                }
            });

            card.addEventListener('drop', function (event) {
                card.classList.remove('ws-nb-dropping');

                if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                    event.preventDefault();
                    attach(event.dataTransfer.files);
                }
            });

            card.setAttribute('data-drop-label', t('notes_drop'));

            claudeCheck(current.claude || []);
        }

        function openNote(noteId, fresh) {
            (fresh ? Promise.resolve() : save()).then(function () {
                return api('ws_note_get', { note_id: noteId, seen: 1 });
            }).then(function (data) {
                current = data.note;
                current.unread = false;
                dirty = false;
                remember(current.id);
                listed(current);
                drawList();
                drawNote();
                poll();
            }).catch(fail);
        }

        function startNew() {
            save().then(function () {
                current = { id: 0, title: '', body: '', access: 'owner', pinned: false, source: null, 'with': [], 'in': [], owner: null, updated_by: null };
                dirty = false;
                remember(0);
                drawList();
                drawNote();

                window.setTimeout(function () {
                    if (current && current.titleInput) {
                        current.titleInput.focus();
                    }
                }, 50);
            });
        }

        function refresh(note) {
            return api('ws_note_get', { note_id: note.id }).then(function (data) {
                listed(data.note);
                drawList();

                if (current && current.id === note.id) {
                    ['with', 'in', 'pinned', 'channel'].forEach(function (key) { current[key] = data.note[key]; });
                    drawHead();
                }
            }).catch(function () {});
        }

        // ── sharing, pinning, leaving ──

        function pin(note) {
            api('ws_note_save', { note_id: note.id, pinned: note.pinned ? 0 : 1 }).then(function (data) {
                if (current && current.id === note.id) {
                    current.pinned = !note.pinned;

                    // The pin moved the revision on: the text's next save
                    // must not be refused as a change made meanwhile.
                    if (data && data.note) {
                        current.revision = Math.max(current.revision || 0, data.note.revision || 0);
                    }
                }

                loadList();
            }).catch(fail);
        }

        function sharesList(note) {
            var box = el('div', 'ws-nb-shares');

            box.appendChild(el('div', 'form-label mb-1', t('notes_shares')));

            if (!(note['with'] || []).length && !(note['in'] || []).length) {
                box.appendChild(el('div', 'small fg-secondary', t('notes_only_you')));
            }

            (note['with'] || []).concat((note['in'] || []).map(function (channel) { return { channel: channel, share_id: channel.share_id }; })).forEach(function (share) {
                var row = el('div', 'ws-nb-share-row');

                if (share.person) {
                    row.appendChild(avatar(share.person));
                    row.appendChild(el('span', 'ws-grow text-truncate', share.person.name));
                    row.appendChild(el('span', 'small fg-secondary', share.can_edit ? t('notes_can_edit') : t('notes_can_view')));
                } else {
                    row.appendChild(icon('bi-hash'));
                    row.appendChild(el('span', 'ws-grow text-truncate', share.channel.name || '?'));
                    row.appendChild(el('span', 'small fg-secondary', t('notes_can_view')));
                }

                if (share.share_id) {
                    var remove = button('btn btn-sm btn-text theme-secondary ws-tool-danger', '', 'bi-x-lg', t('notes_unshare'));
                    remove.addEventListener('click', function () {
                        api('ws_note_unshare', { note_id: note.id, share_id: share.share_id }).then(function () {
                            row.remove();
                            refresh(note);
                        }).catch(fail);
                    });
                    row.appendChild(remove);
                }

                box.appendChild(row);
            });

            return box;
        }

        function sharePeople(note) {
            var others = ((BOOT && BOOT.people) || []).map(function (member) { return member.id; }).filter(function (id) { return id !== CFG.me; });
            var details = el('div');
            var picker = peoplePicker([], others);
            var right = select([[0, t('notes_can_view')], [1, t('notes_can_edit')]], 1);

            right.classList.add('w-auto', 'mt-3');
            details.appendChild(picker);
            details.appendChild(right);
            details.appendChild(el('div', 'form-text', t('notes_share_people_help')));
            details.appendChild(sharesList(note));

            save().then(function () {
                return ask(t('notes_share_people'), t('notes_share'), false, details);
            }).then(function (yes) {
                if (!yes || !picker.value().length) {
                    return;
                }

                api('ws_note_share', { note_id: note.id, user_ids: picker.value(), can_edit: right.value === '1' }).then(function (data) {
                    toast(t('notes_shared_ok', data.shared), 'success');
                    refresh(note);
                }).catch(fail);
            });
        }

        function shareChannel(note) {
            var channels = ((BOOT && BOOT.channels) || []).filter(function (channel) {
                return channel.joined || channel.kind === 'public';
            });
            var details = el('div');
            var choice = select(channels.map(function (channel) {
                return [channel.id, (channel.kind === 'private' ? '🔒 ' : '#') + channel.name];
            }), channels.length ? channels[0].id : 0);

            details.appendChild(choice);
            details.appendChild(el('div', 'form-text', t('notes_share_channel_help')));
            details.appendChild(sharesList(note));

            save().then(function () {
                return ask(t('notes_share_channel'), t('notes_share'), false, details);
            }).then(function (yes) {
                if (!yes || !choice.value) {
                    return;
                }

                api('ws_note_share', { note_id: note.id, channel_id: parseInt(choice.value, 10) }).then(function (data) {
                    toast(t('notes_shared_channel_ok'), 'success', { label: t('open_channel'), href: CFG.urls.workspace + '?channel=' + data.channel_id + '&message=' + data.message_id });
                    refresh(note);
                }).catch(fail);
            });
        }

        // A meeting note's decisions and action items, brought into a
        // channel (includes/workspace/meetings.php): read and shown first,
        // each one chosen, written on confirmation.
        function meetingTransfer(note) {
            var channels = ((BOOT && BOOT.channels) || []).filter(function (channel) {
                return !channel.archived && (channel.joined || channel.kind === 'public');
            });
            var node = offcanvas('ws-meeting-transfer', '');
            var body = clear(node.querySelector('.drawer-body'));
            var footer = clear(node.querySelector('.drawer-footer'));
            var choice = select(channels.map(function (channel) {
                return [channel.id, (channel.kind === 'private' ? '🔒 ' : '#') + channel.name];
            }), channels.length ? channels[0].id : 0);
            var list = el('div');
            var picked = [];

            node.querySelector('.drawer-title').textContent = t('meeting_transfer_title');
            choice.id = nextId('ws-mt-channel-');
            body.appendChild(formRow(t('meeting_channel'), choice));
            body.appendChild(el('div', 'form-text mb-5', t('meeting_transfer_help')));
            body.appendChild(list);

            footer.classList.remove('d-none');

            var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'drawer');
            footer.appendChild(cancel);

            var go = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('meeting_transfer_go'), 'bi-box-arrow-in-right');
            footer.appendChild(go);

            function row(group, item, text, sub, blocked) {
                var line = el('div', 'form-field mb-3');
                var box = el('input', 'check');
                var label = el('label', '');

                box.type = 'checkbox';
                box.id = nextId('ws-mt-item-');
                box.checked = !blocked;
                box.disabled = !!blocked;
                label.htmlFor = box.id;
                label.appendChild(el('span', blocked ? 'fg-secondary' : '', text));

                if (sub) {
                    label.appendChild(el('div', 'small ' + (item.error ? 'fg-danger' : 'fg-secondary'), sub));
                }

                line.appendChild(box);
                line.appendChild(label);
                picked.push({ group: group, index: item.index, box: box });

                return line;
            }

            function load() {
                go.disabled = true;
                picked = [];
                clear(list);
                list.appendChild(skeleton(2, 'ws-skeleton-sm'));

                api('ws_meeting_transfer', { note_id: note.id, channel_id: parseInt(choice.value, 10) || 0 }).then(function (data) {
                    clear(list);

                    if (!data.decisions.length && !data.actions.length) {
                        list.appendChild(el('div', 'ws-empty', t('meeting_transfer_none')));
                        return;
                    }

                    if (data.decisions.length) {
                        list.appendChild(el('div', 'form-label mt-3', t('meeting_decisions')));
                        data.decisions.forEach(function (item) {
                            list.appendChild(row('decisions', item, item.text, item.done ? t('meeting_transfer_done') : '', item.done));
                        });
                    }

                    if (data.actions.length) {
                        list.appendChild(el('div', 'form-label mt-3', t('meeting_actions')));
                        data.actions.forEach(function (item) {
                            var sub = item.done ? t('meeting_transfer_task') : (item.error || [item.people.map(function (who) { return who.name; }).join(', '), item.due].filter(Boolean).join(' · '));
                            list.appendChild(row('actions', item, item.title || '…', sub, item.done || !!item.error));
                        });
                    }

                    go.disabled = !channels.length;
                }).catch(fail);
            }

            choice.addEventListener('change', load);

            go.addEventListener('click', function () {
                var chosen = { decisions: [], actions: [] };
                var channelId = parseInt(choice.value, 10) || 0;

                picked.forEach(function (item) {
                    if (item.box.checked && !item.box.disabled) {
                        chosen[item.group].push(item.index);
                    }
                });

                if (!chosen.decisions.length && !chosen.actions.length) {
                    hideOffcanvas(node);
                    return;
                }

                go.disabled = true;

                api('ws_meeting_transfer', { note_id: note.id, channel_id: channelId, confirm: 1, decisions: chosen.decisions, actions: chosen.actions }).then(function (data) {
                    hideOffcanvas(node);
                    toast(t('meeting_transfer_result', data.decisions, data.tasks), 'success', { label: t('open_channel'), href: CFG.urls.workspace + '?channel=' + channelId });

                    if (data.failed && data.failed.length) {
                        toast(t('meeting_transfer_failed') + ' ' + data.failed.join(' · '), 'warning');
                    }

                    // The rows that became tasks carry their tags now.
                    openNote(note.id, true);
                }).catch(function (error) {
                    go.disabled = false;
                    fail(error);
                });
            });

            save().then(function () {
                showOffcanvas(node);
                load();
            });
        }

        function toChannel(note) {
            save().then(function () {
                channelForm(null, { name: note.name, note_id: note.id }, function (channelId) {
                    toast(t('channel_created'), 'success', { label: t('open_channel'), href: CFG.urls.workspace + '?channel=' + channelId });
                    refresh(note);
                });
            });
        }

        function forget(note) {
            ['mine', 'shared'].forEach(function (key) {
                lists[key] = lists[key].filter(function (item) { return item.id !== note.id; });
            });

            if (current && current.id === note.id) {
                current = null;
                field = null;
                dirty = false;
                remember(0);
                placeholder();
            }

            drawList();
        }

        function removeNote(note) {
            ask(t('notes_delete_confirm'), t('notes_delete'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                clearTimeout(saveTimer);

                api('ws_note_delete', { note_id: note.id }).then(function () {
                    forget(note);
                    toast(t('notes_deleted'), 'success');
                }).catch(fail);
            });
        }

        function leaveNote(note) {
            ask(t('notes_leave_confirm'), t('notes_leave'), true).then(function (yes) {
                if (!yes) {
                    return;
                }

                api('ws_note_unshare', { note_id: note.id, share_id: 0 }).then(function () {
                    forget(note);
                    toast(t('notes_left'), 'success');
                }).catch(fail);
            });
        }

        // ── start ──

        placeholder();

        loadList().then(function () {
            var wanted = parseInt(query.note || 0, 10) || 0;

            if (wanted) {
                openNote(wanted);
            } else if (query['new']) {
                startNew();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
                loadList();
            }
        });

        // The list shows what changed in the other notes - somebody wrote in
        // one, Claude answered in one - without the page being reloaded.
        window.setInterval(function () {
            if (!document.hidden) {
                loadList();
            }
        }, 30000);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Start
    // ═══════════════════════════════════════════════════════════════════

    // What the screens kept in files of their own lean on here
    // (workspace_palette.js, workspace_board_channel.js, workspace_flow.js):
    // the small helpers, the dialogs and menus, the people picker the note
    // share uses, the task drawer, the planning board's way of moving a task,
    // and the channel screen as it stands.
    function shareKit() {
        window.PGWsKit = {
            cfg: CFG,
            t: t,
            el: el,
            icon: icon,
            button: button,
            select: select,
            avatar: avatar,
            avatarStack: avatarStack,
            peoplePicker: peoplePicker,
            api: api,
            toast: toast,
            fail: fail,
            ask: ask,
            clear: clear,
            debounce: debounce,
            onContext: onContext,
            taskMenu: taskMenu,
            moveTask: moveTask,
            askSeries: askSeries,
            scheduledList: scheduledList,
            priorityFilter: priorityFilter,
            directMessage: directMessage,
            openTask: function (taskId, defaults, onSaved) {
                taskDrawer.open(taskId, defaults, onSaved);
            },
            // The record drawer's new mode: a new product or product group.
            openCreate: function (type, options) {
                openRecord(type, 0, '', options || {});
            },
            // The channel open on the channel screen, with the screen's ways
            // to open another and to write into its box; null elsewhere.
            here: function () {
                if ((CFG.mode !== 'channels') || !app.root || !app.channel || (app.view !== 'channel')) {
                    return null;
                }

                return {
                    id: app.channel.id,
                    name: app.channel.name,
                    canPost: !!app.channel.can_post && !app.era,
                    openChannel: function (channelId, messageId) { app.open(channelId, messageId || 0); },
                    insert: function (text) {
                        if (app.tab !== 'messages') {
                            app.tab = 'messages';
                            app.drawCenter();
                            app.syncAddress();
                        }

                        if (app.input) {
                            app.insertText(text);
                        }
                    }
                };
            }
        };
    }

    function start() {
        shareKit();

        // Ctrl+K (⌘K) opens the workspace's own search box on its screens,
        // in place of the panel's search; it is taken before the panel's
        // handler on the document sees it.
        if (CFG.mode !== 'record') {
            window.addEventListener('keydown', function (event) {
                if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey || (String(event.key).toLowerCase() !== 'k') || !window.PGWsPalette) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (window.PGWsPalette.isOpen()) {
                    window.PGWsPalette.close();
                } else {
                    window.PGWsPalette.open();
                }
            }, true);
        }

        // The inbox links of the rail and its drawer are drawn with the page,
        // so they are taken over at once rather than once the screen has
        // loaded: a click before that would leave for the channels screen
        // and open the inbox there, which is the long way round. The inbox
        // needs nothing the start-up brings.
        document.addEventListener('click', function (event) {
            var inboxLink = event.target.closest('[data-ws-inbox]');
            var paletteLink = event.target.closest('[data-ws-palette]');

            // The magnifier of the rail, its drawer and the channel list.
            if (paletteLink && window.PGWsPalette) {
                event.preventDefault();

                var navDrawer = document.getElementById('ws-nav-drawer');

                if (navDrawer && window.pgUI && pgUI.drawer.isOpen(navDrawer)) {
                    pgUI.drawer.hide(navDrawer);
                }

                window.PGWsPalette.open();
                return;
            }

            if (!inboxLink) {
                return;
            }

            event.preventDefault();

            var drawer = document.getElementById('ws-nav-drawer');

            if (drawer && window.pgUI && pgUI.drawer.isOpen(drawer)) {
                pgUI.drawer.hide(drawer);
            }

            openInbox(function () {});
        });

        api('ws_bootstrap').then(function (data) {
            BOOT = data;

            var root = document.getElementById('ws-root');

            if (CFG.mode === 'channels' && root) {
                app.start(root);
            } else if (CFG.mode === 'tasks' && root) {
                startTasks(root);
            } else if (CFG.mode === 'board' && root) {
                startBoard(root);
            } else if (CFG.mode === 'calendar' && root) {
                startCalendar(root);
            } else if (CFG.mode === 'timeline' && root) {
                startTimeline(root);
            } else if (CFG.mode === 'notes' && root) {
                startNotes(root);
            } else if (CFG.mode === 'flows' && root && window.PGWsFlow) {
                // The canvas lives in workspace_flow.js; it takes the team
                // from here for its people picker.
                window.PGWsFlow.start(root, BOOT);
            }

            // What runs where no scheduler does (repeating tasks, requests to
            // Claude that waited), once the screen is up rather than before.
            window.setTimeout(function () {
                api('ws_tick').catch(function () {});
            }, 1500);

            function recordTrigger(trigger) {
                var parts = trigger.getAttribute('data-ws-record').split(':');
                openRecord(parts[0], parseInt(parts[1], 10), trigger.getAttribute('data-ws-label') || '');
            }

            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-ws-record]');

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                recordTrigger(trigger);
            });

            // On a record's screen this script is fetched by the click on its
            // Workspace button (ws_record_loader(), screen.php): from here on
            // the listener above answers, and the click that brought the
            // script in is answered now.
            window.PGWsRecordReady = true;

            if (window.PGWsPendingRecord) {
                var pending = window.PGWsPendingRecord;

                window.PGWsPendingRecord = null;
                recordTrigger(pending);
            }
        }).catch(function (error) {
            var root = document.getElementById('ws-root');

            if (root) {
                clear(root);
                root.appendChild(el('div', 'alert theme-warning ws-boot m-5', error.message));
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
