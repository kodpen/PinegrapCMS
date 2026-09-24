/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the screens: channels (workspace.php), tasks
 * (workspace_tasks.php), the planning board (workspace_board.php), the work
 * calendar (workspace_calendar.php), the decision timeline
 * (workspace_timeline.php) and the Workspace drawer on the order, contact
 * and product screens.
 *
 * Served as it is, without a minified twin, like the ERP's editor scripts.
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

    function api(action, data) {
        var payload = { action: action, token: CFG.token };

        Object.keys(data || {}).forEach(function (key) {
            payload[key] = data[key];
        });

        return fetch(CFG.api_url, {
            method: 'POST',
            credentials: 'same-origin',
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
                throw error;
            }

            return json.data || {};
        });
    }

    var toastRoot = null;

    function toast(message, kind, action) {
        if (!toastRoot) {
            toastRoot = el('div', 'toast-container position-fixed bottom-0 start-0 p-3');
            toastRoot.style.zIndex = 1090;
            document.body.appendChild(toastRoot);
        }

        var box = el('div', 'toast align-items-center border-0 show text-bg-' + (kind || 'dark'));
        box.setAttribute('role', 'status');

        var row = el('div', 'd-flex');
        var text = el('div', 'toast-body', message);

        // A way to what was just done: the note kept, the channel made.
        if (action && action.href) {
            var go = el('a', 'ms-2 fw-semibold text-reset', action.label);
            go.href = action.href;
            text.appendChild(go);
        }

        row.appendChild(text);

        var close = button('btn-close btn-close-white me-2 m-auto', '', '', t('close'));
        close.addEventListener('click', function () { box.remove(); });
        row.appendChild(close);
        box.appendChild(row);
        toastRoot.appendChild(box);

        setTimeout(function () { box.remove(); }, 5000);
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

    // One modal reused for every question the screens ask.
    var dialog = null;

    function ask(message, okLabel, danger, details) {
        return new Promise(function (resolve) {
            if (!dialog) {
                dialog = el('div', 'modal fade ws-ask-modal');
                dialog.id = 'ws-ask';
                dialog.tabIndex = -1;
                dialog.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
                    + '<div class="modal-body"><p class="mb-2" data-ws-ask-text></p><div data-ws-ask-details></div></div>'
                    + '<div class="modal-footer"><button type="button" class="btn btn-sm btn-ghost" data-bs-dismiss="modal" data-ws-ask-cancel></button>'
                    + '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-ws-ask-ok></button></div></div></div>';
                document.body.appendChild(dialog);
            }

            dialog.querySelector('[data-ws-ask-text]').textContent = message;
            var detailBox = clear(dialog.querySelector('[data-ws-ask-details]'));

            if (details) {
                detailBox.appendChild(details);
            }

            var ok = dialog.querySelector('[data-ws-ask-ok]');
            var cancel = dialog.querySelector('[data-ws-ask-cancel]');

            ok.textContent = okLabel || t('ok');
            ok.className = 'btn btn-sm rounded-pill px-3 ' + (danger ? 'btn-outline-warning' : 'btn-primary');
            cancel.textContent = t('cancel');

            var modal = bootstrap.Modal.getOrCreateInstance(dialog);
            var answered = false;

            ok.onclick = function () {
                answered = true;
                modal.hide();
                resolve(true);
            };

            dialog.addEventListener('hidden.bs.modal', function onHidden() {
                dialog.removeEventListener('hidden.bs.modal', onHidden);

                if (!answered) {
                    resolve(false);
                }
            });

            modal.show();
        });
    }

    // Once the question dialog has closed, so the next question can open it
    // again (it cannot while it is still fading out).
    function askClosed() {
        return new Promise(function (resolve) {
            if (!dialog || (dialog.style.display !== 'block')) {
                resolve();
                return;
            }

            dialog.addEventListener('hidden.bs.modal', function onClosed() {
                dialog.removeEventListener('hidden.bs.modal', onClosed);
                resolve();
            });
        });
    }

    function absoluteUrl(url) {
        try {
            return new URL(url, window.location.href).href;
        } catch (error) {
            return url;
        }
    }

    function copyText(text) {
        var done = function () { toast(t('copied'), 'success'); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallback(); });
            return;
        }

        fallback();

        function fallback() {
            var area = el('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                fail(error);
            }

            area.remove();
        }
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

    // A PDF or a video, large, without leaving the screen.
    var previewModal = null;

    function previewFile(file) {
        if ((file.kind || '') === 'image' || file.image) {
            viewImages([{ src: file.url, alt: file.name, caption: file.name }], 0);
            return;
        }

        if (!previewModal) {
            previewModal = el('div', 'modal fade ws-preview-modal');
            previewModal.id = 'ws-preview';
            previewModal.tabIndex = -1;
            previewModal.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down"><div class="modal-content">'
                + '<div class="modal-header py-2"><h5 class="modal-title text-truncate"></h5>'
                + '<a class="btn btn-sm btn-ghost ms-auto" data-ws-open target="_blank" rel="noopener"></a>'
                + '<a class="btn btn-sm btn-ghost" data-ws-download></a>'
                + '<button type="button" class="btn-close ms-1" data-bs-dismiss="modal"></button></div>'
                + '<div class="modal-body p-0"></div></div></div>';
            document.body.appendChild(previewModal);

            previewModal.addEventListener('hidden.bs.modal', function () {
                clear(previewModal.querySelector('.modal-body'));
            });
        }

        previewModal.querySelector('.modal-title').textContent = file.name;
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

        var body = clear(previewModal.querySelector('.modal-body'));

        if ((file.kind || '') === 'video') {
            var video = el('video', 'ws-preview-video');
            video.src = file.url;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            body.appendChild(video);
        } else {
            var frame = el('iframe', 'ws-preview-frame');
            frame.src = file.url;
            frame.title = file.name;
            body.appendChild(frame);
        }

        bootstrap.Modal.getOrCreateInstance(previewModal).show();
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

            var entry = el('button', 'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate ws-ctx-item' + (item.danger ? ' ws-ctx-danger' : '') + (item.checked ? ' ws-ctx-checked' : ''));
            entry.type = 'button';
            entry.setAttribute('role', 'menuitem');
            entry.appendChild(icon(item.checked ? 'bi-check2' : (item.icon || 'bi-dot'), 'me-2'));
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

    // Tags inside a message: open the record, see where else it came up.
    function chipMenus(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.ws-chip[data-ws-ref]'), function (chip) {
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
                    items.push({ icon: 'bi-check2-square', label: t('new_task_about'), action: function () {
                        taskDrawer.open(0, { refs: [{ type: type, id: id, label: label, icon: (BOOT.ref_types[type] || {}).icon, token: '<#' + type + ':' + id + '>' }], title: label }, null);
                    } });
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
    function personMenu(member, extra) {
        var items = [{ header: member.name + (member.title ? ' · ' + member.title : '') }];

        if (member.id !== BOOT.me.id) {
            items.push({ icon: 'bi-chat-dots', label: t('direct_message'), action: function () { directMessage(member.id); } });
        }

        items.push({ icon: 'bi-check2-square', label: t('give_task'), action: function () { taskDrawer.open(0, { assignees: [member.id] }, null); } });
        items.push({ icon: 'bi-calendar-plus', label: t('add_plan_item'), action: function () { eventForm(null, { people: [member.id], start: CFG.today }, null); } });
        items.push('-');
        items.push({ icon: 'bi-calendar3', label: t('their_calendar'), action: function () { window.location.href = CFG.urls.calendar + '?person=' + member.id; } });
        items.push({ icon: 'bi-calendar-week', label: t('their_board'), action: function () { window.location.href = CFG.urls.board + '?person=' + member.id; } });

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
                items.push({ icon: 'bi-play-circle', label: t('start_work'), action: function () { status('doing'); } });
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
    // clashes with somebody's day.
    function moveTask(move, force, onDone) {
        move.force = force ? 1 : 0;

        api('ws_task_move', move).then(function (result) {
            if (result.needs_confirm) {
                if (result.check.hard && !result.check.may_override) {
                    ask(t('leave_blocks'), t('ok'), false, warningList(result.check));
                    return;
                }

                ask(t('move_despite'), t('move_anyway'), true, warningList(result.check)).then(function (yes) {
                    if (yes) {
                        moveTask(move, true, onDone);
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

    function avatar(personData, size) {
        var img = el('img');
        img.src = (personData && personData.avatar) ? personData.avatar : '';
        img.alt = '';
        img.loading = 'lazy';

        if (size) {
            img.style.width = size;
            img.style.height = size;
        }

        return img;
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

    function offcanvas(id, title) {
        var existing = document.getElementById(id);

        if (existing) {
            return existing;
        }

        var node = el('div', 'offcanvas offcanvas-end');
        node.id = id;
        node.tabIndex = -1;
        node.style.width = 'min(34rem, 100vw)';
        node.innerHTML = '<div class="offcanvas-header border-bottom"><h5 class="offcanvas-title"></h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>'
            + '<div class="offcanvas-body"></div><div class="offcanvas-footer d-none p-3 border-top d-flex gap-2 justify-content-end"></div>';
        node.querySelector('.offcanvas-title').textContent = title || '';
        node.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        document.body.appendChild(node);

        // The panel's chat bubble sits above offcanvas panels; it steps aside
        // while one of ours is open.
        node.addEventListener('show.bs.offcanvas', function () {
            document.body.classList.add('ws-drawer-open');
        });
        node.addEventListener('hidden.bs.offcanvas', function () {
            document.body.classList.remove('ws-drawer-open');
        });

        return node;
    }

    function showOffcanvas(node) {
        bootstrap.Offcanvas.getOrCreateInstance(node).show();
    }

    function hideOffcanvas(node) {
        var instance = bootstrap.Offcanvas.getInstance(node);

        if (instance) {
            instance.hide();
        }
    }

    function formRow(label, control, help) {
        var row = el('div', 'mb-3');
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
        var node = el('select', 'form-select form-select-sm');

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
    function richField(wrap, options) {
        if (!window.PGWsEditor) {
            return null;
        }

        var field = null;
        var editor = window.PGWsEditor.create({
            placeholder: options.placeholder || '',
            previews: !!options.previews,
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
            // Buttons under the box: people, records, emoji and, where the
            // text may hold one, a checklist.
            tools: function (withList) {
                var row = el('div', 'ws-note-actions');

                var mention = button('btn btn-sm btn-ghost', '', 'bi-at', t('mention'));
                mention.addEventListener('click', function () { field.insertTrigger('@'); });
                row.appendChild(mention);

                var tag = button('btn btn-sm btn-ghost', '', 'bi-hash', t('tag_record'));
                tag.addEventListener('click', function () { field.insertTrigger('#'); });
                row.appendChild(tag);

                var emoji = button('btn btn-sm btn-ghost', '', 'bi-emoji-smile', t('insert_emoji'));
                emoji.addEventListener('click', function (event) {
                    var box = emoji.getBoundingClientRect();

                    event.stopPropagation();
                    emojiPicker(box.left, box.bottom + 4, function (picked) { editor.insertText(picked); });
                });
                row.appendChild(emoji);

                if (withList) {
                    var list = button('btn btn-sm btn-ghost', '', 'bi-ui-checks', t('insert_checklist'));
                    list.addEventListener('click', function () {
                        window.PGWsEditor.openChecklist('', function (markup) {
                            if (markup !== '') {
                                editor.insertBlock('checklist', markup);
                            }
                        });
                    });
                    row.appendChild(list);
                }

                return row;
            }
        };

        ['pickerCheck', 'recordQuery', 'openPicker', 'drawPicker', 'placePicker', 'pickerKey', 'pick', 'closePicker', 'tokens', 'insertTrigger', 'beforeCaret'].forEach(function (name) {
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
        var typeSelect = el('select', 'form-select form-select-sm');
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

            api('ws_ref_search', { type: typeSelect.value, q: query }).then(function (data) {
                closeList();
                list = el('div', 'ws-picker');
                var inner = el('div', 'ws-picker-list');

                if (!data.items.length) {
                    inner.appendChild(el('div', 'ws-picker-empty', t('nothing_found')));
                }

                data.items.forEach(function (item) {
                    var row = el('button', 'ws-picker-item');
                    row.type = 'button';
                    row.appendChild(icon(item.icon || 'bi-dot'));
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

        chip.appendChild(icon(ref.icon || 'bi-dot'));
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
            var line = el('div', 'd-flex flex-wrap gap-1 mt-2 align-items-center');
            line.appendChild(el('span', 'small text-body-secondary me-1', t('lighter_people')));

            check.suggestions.forEach(function (suggestion) {
                var pick = button('btn btn-sm btn-outline-secondary', suggestion.label, 'bi-person-plus');
                pick.addEventListener('click', function () { onSuggest(suggestion); });
                line.appendChild(pick);
            });

            box.appendChild(line);
        }

        return box;
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
            var body = clear(self.node.querySelector('.offcanvas-body'));
            var footer = clear(self.node.querySelector('.offcanvas-footer'));
            var editable = !task || task.can_edit;
            var rights = BOOT.me.rights;

            self.task = task;
            // Records only: the people on the task have their own field.
            self.refs = (task ? task.refs : (defaults.refs || [])).filter(function (ref) {
                return !ref.token || ref.token.indexOf('<#') === 0;
            });
            self.node.querySelector('.offcanvas-title').textContent = task ? (task.number + ' · ' + t('task')) : t('new_task');

            footer.classList.remove('d-none');

            if (task) {
                var info = el('div', 'd-flex flex-wrap gap-2 align-items-center small text-body-secondary mb-3');
                info.appendChild(el('span', 'badge text-bg-secondary', task.status_label));
                info.appendChild(el('span', '', t('created_by', task.creator, task.created_label)));

                if (task.channel) {
                    var channelLink = el('a', 'link-body-emphasis', '#' + task.channel.name);
                    channelLink.href = task.channel.url;
                    info.appendChild(channelLink);
                }

                if (task.completed_label) {
                    info.appendChild(el('span', 'text-success', t('completed_on', task.completed_label)));
                }

                body.appendChild(info);

                if (editable && task.open) {
                    var quick = el('div', 'd-flex flex-wrap gap-1 mb-3');

                    Object.keys(BOOT.statuses).forEach(function (status) {
                        if (status === task.status) {
                            return;
                        }

                        var move = button('btn btn-sm ' + (status === 'done' ? 'btn-outline-success' : 'btn-ghost'), BOOT.statuses[status]);
                        move.addEventListener('click', function () { self.setStatus(status); });
                        quick.appendChild(move);
                    });

                    body.appendChild(quick);
                } else if (editable) {
                    var reopen = button('btn btn-sm btn-ghost mb-3', t('reopen'), 'bi-arrow-counterclockwise');
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
                linked.appendChild(icon('bi-list-check', 'me-2'));
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

            var grid = el('div', 'row g-2');

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
                col(formRow(t('post_card_in'), f.channel));
            }

            body.appendChild(grid);

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

            var refsWrap = el('div', 'mb-3');
            refsWrap.appendChild(el('label', 'form-label', t('about_records')));
            f.refs = el('div', 'ws-refs-field mb-2');
            refsWrap.appendChild(f.refs);
            refsWrap.appendChild(refSearchField(BOOT.ref_types, function (item) {
                self.refs.push({ token: item.token, type: item.type, id: item.id, label: item.label, icon: item.icon, url: '' });
                self.drawRefs();
            }));
            body.appendChild(refsWrap);
            self.drawRefs();

            f.warnings = el('div', 'mb-2');
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

            // Notes: anyone who can see the task may add one.
            self.notesBox = (task && task.work) ? el('div', 'ws-task-notes') : null;

            if (self.notesBox) {
                body.appendChild(self.notesBox);
            }

            if (task) {
                self.drawWork(task);
            }

            var save = button('btn btn-sm btn-primary rounded-pill px-3', task ? t('save') : t('create_task'), 'bi-check2');
            save.disabled = !editable;
            save.addEventListener('click', function () { self.save(false); });

            var cancel = button('btn btn-sm btn-ghost', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'offcanvas');

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
                box.appendChild(el('span', 'small text-body-secondary', t('no_records')));
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

            if (self.workBox) {
                var box = clear(self.workBox);
                var list = task.checklist || {};
                var progress = list.progress;

                if (progress) {
                    var head = el('div', 'ws-task-work-head');
                    head.appendChild(el('span', 'fw-semibold', t('progress')));
                    head.appendChild(el('span', 'ws-grow'));
                    head.appendChild(el('span', 'text-body-secondary', t('progress_text', progress.done, progress.total, progress.percent)));
                    box.appendChild(head);
                    box.appendChild(progressBar(progress));

                    if (progress.complete && task.open && task.can_edit) {
                        var hint = el('div', 'ws-task-work-done');
                        hint.appendChild(icon('bi-check2-all', 'me-1'));
                        hint.appendChild(el('span', 'ws-grow', t('all_items_done')));

                        var finish = button('btn btn-sm btn-success rounded-pill px-3', t('mark_done'), 'bi-check2-circle');
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
            var add = button('btn btn-outline-secondary', '', 'bi-plus-lg', t('add_item'));

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
                head.appendChild(el('span', 'badge text-bg-secondary ms-2', String(task.notes.length)));
            }

            box.appendChild(head);

            var list = el('div', 'ws-task-note-list');

            if (!task.notes.length) {
                list.appendChild(el('div', 'small text-body-secondary', t('no_notes')));
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
            face.className = 'ws-task-note-avatar';
            row.appendChild(face);

            meta.appendChild(el('b', '', note.sender ? note.sender.name : ''));
            meta.appendChild(el('span', '', note.time));

            if (note.edited) {
                meta.appendChild(el('span', '', t('edited')));
            }

            if (note.can_edit || note.can_delete) {
                var tools = el('span', 'ws-task-note-tools');

                if (note.can_edit) {
                    var edit = button('btn btn-sm btn-ghost py-0 px-1', '', 'bi-pencil', t('edit'));
                    edit.addEventListener('click', function () {
                        main.replaceChild(self.noteComposer(note), text);
                        tools.remove();
                    });
                    tools.appendChild(edit);
                }

                if (note.can_delete) {
                    var remove = button('btn btn-sm btn-ghost py-0 px-1 ws-tool-danger', '', 'bi-trash', t('delete'));
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
                var mention = button('btn btn-sm btn-ghost', '', 'bi-at', t('mention'));
                mention.addEventListener('click', function () { field.insertTrigger('@'); });
                actions.appendChild(mention);

                var tag = button('btn btn-sm btn-ghost', '', 'bi-hash', t('tag_record'));
                tag.addEventListener('click', function () { field.insertTrigger('#'); });
                actions.appendChild(tag);

                actions.appendChild(el('span', 'ws-grow'));
            }

            if (note) {
                var cancel = button('btn btn-sm btn-ghost', t('cancel'));
                cancel.addEventListener('click', function () { self.drawNotes(self.task); });
                actions.appendChild(cancel);
            }

            var save = button('btn btn-sm btn-primary rounded-pill px-3', note ? t('save') : t('add_note'), note ? 'bi-check2' : 'bi-plus-lg');

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

    function channelForm(channel, defaults, onDone) {
        var node = offcanvas('ws-channel-form', '');
        var body = clear(node.querySelector('.offcanvas-body'));
        var footer = clear(node.querySelector('.offcanvas-footer'));

        footer.classList.remove('d-none');
        node.querySelector('.offcanvas-title').textContent = channel ? t('edit_channel') : t('new_channel');
        defaults = defaults || {};

        var name = el('input', 'form-control');
        name.id = nextId('ws-ch-name-');
        name.maxLength = 80;
        name.value = channel ? channel.name : (defaults.name || '');
        body.appendChild(formRow(t('channel_name'), name, t('channel_name_help')));

        var kind = null;

        if (!channel) {
            kind = el('div', 'mb-3');
            kind.appendChild(el('div', 'form-label', t('channel_kind')));

            [['public', t('kind_public'), t('kind_public_help')], ['private', t('kind_private'), t('kind_private_help')]].forEach(function (option, index) {
                var wrap = el('div', 'form-check');
                var radio = el('input', 'form-check-input');
                radio.type = 'radio';
                radio.name = 'ws_channel_kind';
                radio.value = option[0];
                radio.id = nextId('ws-ch-kind-');
                radio.checked = (defaults.kind ? defaults.kind === option[0] : index === 0);
                var label = el('label', 'form-check-label', option[1]);
                label.htmlFor = radio.id;
                wrap.appendChild(radio);
                wrap.appendChild(label);
                wrap.appendChild(el('div', 'form-text mt-0', option[2]));
                kind.appendChild(wrap);
            });

            body.appendChild(kind);
        }

        var topic = el('input', 'form-control');
        topic.id = nextId('ws-ch-topic-');
        topic.maxLength = 255;
        topic.value = channel ? channel.topic : (defaults.topic || '');
        body.appendChild(formRow(t('channel_topic'), topic));

        // The customer the channel is about, from the address book.
        var contactId = channel && channel.contact ? channel.contact.id : (defaults.contact_id || 0);
        var contactLabel = channel && channel.contact ? channel.contact.label : (defaults.contact_label || '');
        var contactBox = el('div', 'mb-3');
        contactBox.appendChild(el('label', 'form-label', t('customer')));
        var contactShown = el('div', 'ws-refs-field mb-2');
        contactBox.appendChild(contactShown);

        function drawContact() {
            clear(contactShown);

            if (contactId) {
                contactShown.appendChild(chipNode({ type: 'contact', label: contactLabel, icon: 'bi-person-vcard' }, function () {
                    contactId = 0;
                    contactLabel = '';
                    drawContact();
                }));
            } else {
                contactShown.appendChild(el('span', 'small text-body-secondary', t('no_customer')));
            }
        }

        drawContact();

        if (BOOT.ref_types.contact) {
            var only = {};
            only.contact = BOOT.ref_types.contact;
            contactBox.appendChild(refSearchField(only, function (item) {
                contactId = item.id;
                contactLabel = item.label;
                drawContact();
            }, t('search_contacts')));
        }

        contactBox.appendChild(el('div', 'form-text', t('customer_help')));
        body.appendChild(contactBox);

        var dept = select(departmentOptions(), channel && channel.department ? channel.department.id : (defaults.department_id || 0));
        dept.id = nextId('ws-ch-dept-');
        body.appendChild(formRow(t('department'), dept));

        var members = null;

        if (!channel) {
            members = peoplePicker(defaults.members || []);
            body.appendChild(formRow(t('invite_people'), members));
        }

        // Opened from a note, the note is its first message.
        if (!channel && defaults.note_id) {
            body.insertBefore(el('div', 'alert alert-info small py-2', t('notes_channel_help')), body.firstChild);
        }

        var save = button('btn btn-sm btn-primary rounded-pill px-3', channel ? t('save') : t('create_channel'), 'bi-check2');
        var cancel = button('btn btn-sm btn-ghost', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'offcanvas');
        footer.appendChild(cancel);
        footer.appendChild(save);

        save.addEventListener('click', function () {
            var data = {
                name: name.value,
                topic: topic.value,
                contact_id: contactId,
                department_id: parseInt(dept.value, 10) || 0
            };

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
                    request = api('ws_channel_create', data);
                }
            }

            request.then(function (result) {
                hideOffcanvas(node);

                if (result.warning) {
                    toast(result.warning, 'warning');
                } else if (!defaults.note_id) {
                    toast(channel ? t('channel_saved') : t('channel_created'), 'success');
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

    // ── The inbox ──────────────────────────────────────────────────────

    function openInbox(onChange) {
        var node = offcanvas('ws-inbox', t('inbox'));
        var body = clear(node.querySelector('.offcanvas-body'));
        var footer = clear(node.querySelector('.offcanvas-footer'));

        footer.classList.remove('d-none');

        var all = button('btn btn-sm btn-ghost', t('mark_all_read'), 'bi-check2-all');
        all.addEventListener('click', function () {
            api('ws_inbox_read', { ids: 'all' }).then(function () {
                hideOffcanvas(node);

                if (onChange) {
                    onChange();
                }
            }).catch(fail);
        });
        footer.appendChild(all);

        body.appendChild(el('div', 'text-body-secondary small', t('loading')));
        showOffcanvas(node);

        api('ws_inbox').then(function (data) {
            clear(body);

            if (!data.items.length) {
                var empty = el('div', 'ws-empty');
                empty.appendChild(icon('bi-inbox'));
                empty.appendChild(document.createTextNode(t('inbox_empty')));
                body.appendChild(empty);
                return;
            }

            data.items.forEach(function (item) {
                var row = el('a', 'ws-record-item' + (item.unread ? ' fw-semibold' : ''));
                row.href = item.url;

                var head = el('div', 'd-flex gap-2 align-items-center');
                head.appendChild(icon(item.icon || 'bi-bell'));
                head.appendChild(el('span', 'flex-grow-1', item.title));
                head.appendChild(el('small', 'text-body-secondary', item.time));
                row.appendChild(head);

                if (item.body) {
                    row.appendChild(el('small', '', item.body));
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
        var head = el('div', 'card-header d-flex align-items-center gap-2');
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

        var body = el('div', 'card-body p-2');
        node.appendChild(body);

        return { node: node, body: body };
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
            if (!target && query.view !== 'home') {
                var pinned = BOOT.channels.filter(function (channel) { return channel.joined && channel.pinned; });
                target = pinned.length ? pinned[0].id : 0;
            }

            if (target) {
                self.open(target, parseInt(query.message || 0, 10));
            } else {
                self.showHome(false);
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

                    api('ws_task_status', { task_id: taskId, status: statusButton.getAttribute('data-ws-task-status') }).then(function (data) {
                        self.replaceTaskCard(taskId, data.html);
                        self.sync();
                    }).catch(fail);
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

            var add = button('btn btn-sm btn-primary rounded-pill', '', 'bi-plus-lg', t('new_channel'));
            add.addEventListener('click', function () {
                channelForm(null, {}, function (id) { self.reloadChannels(id); });
            });
            head.appendChild(add);
            side.appendChild(head);

            var scroll = el('div', 'ws-side-scroll');
            side.appendChild(scroll);

            function link(box, label, iconName, handler, badge, active) {
                var item = el('button', 'ws-channel-link' + (active ? ' active' : ''));
                item.type = 'button';
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

            function group(title) {
                var box = el('div', 'ws-side-section');

                if (title) {
                    box.appendChild(el('div', 'ws-side-title', title));
                }

                scroll.appendChild(box);
                return box;
            }

            var top = group('');
            link(top, t('home'), 'bi-house', function () { self.showHome(true); }, 0, self.view === 'home');
            link(top, t('inbox'), 'bi-inbox', function () {
                openInbox(function () { self.sync(); });
            }, BOOT.inbox || 0);

            var joined = BOOT.channels.filter(function (channel) { return channel.joined; });
            var pinned = joined.filter(function (channel) { return channel.pinned; });
            var mine = joined.filter(function (channel) { return !channel.pinned; });
            var others = BOOT.channels.filter(function (channel) { return !channel.joined; });

            function section(title, list, sortable, iconName) {
                var box = el('div', 'ws-side-section' + (sortable ? ' ws-sortable' : ''));
                var head = el('div', 'ws-side-title');
                var label = el('span');

                if (iconName) {
                    label.appendChild(icon(iconName, 'me-1'));
                }

                label.appendChild(document.createTextNode(title));
                head.appendChild(label);
                box.appendChild(head);

                list.forEach(function (channel) {
                    var link = el('button', 'ws-channel-link'
                        + ((self.channel && self.channel.id === channel.id) ? ' active' : '')
                        + (channel.unread ? ' ws-unread' : '')
                        + (channel.joined ? '' : ' ws-notjoined'));
                    link.type = 'button';
                    link.setAttribute('data-ws-channel', channel.id);

                    if (sortable) {
                        self.makeSortable(link, box, channel);
                    }
                    link.appendChild(icon(channel.kind === 'private' ? 'bi-lock' : (channel.contact_id ? 'bi-person-vcard' : 'bi-hash')));
                    link.appendChild(el('span', 'ws-channel-name', channel.name));

                    if (channel.notify === 'none') {
                        link.appendChild(icon('bi-bell-slash', 'text-body-secondary'));
                    }

                    if (channel.mentions) {
                        link.appendChild(el('span', 'ws-count ws-count-mention', channel.mentions));
                    } else if (channel.unread && channel.notify !== 'none') {
                        link.appendChild(el('span', 'ws-count', channel.unread > 99 ? '99+' : channel.unread));
                    }

                    // A pin at the end of the line, shown on hover (and always
                    // on a pinned channel).
                    var pin = el('span', 'ws-pin' + (channel.pinned ? ' ws-pinned' : ''));
                    pin.setAttribute('role', 'button');
                    pin.title = channel.pinned ? t('unpin') : t('pin');
                    pin.appendChild(icon(channel.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle'));
                    pin.addEventListener('click', function (event) {
                        event.stopPropagation();
                        self.pinChannel(channel, !channel.pinned);
                    });
                    link.appendChild(pin);

                    link.addEventListener('click', function () {
                        self.root.classList.remove('ws-show-side');
                        self.open(channel.id, 0);
                    });

                    onContext(link, function () { return self.channelActions(channel); });

                    box.appendChild(link);
                });

                if (!list.length) {
                    box.appendChild(el('div', 'small text-body-secondary px-2', t('no_channels')));
                }

                scroll.appendChild(box);
            }

            if (pinned.length) {
                section(t('pinned'), pinned, true, 'bi-pin-angle');
            }

            section(t('my_channels'), mine, true);

            if (others.length) {
                section(t('other_channels'), others, false);
            }

            var work = group(t('nav_work'));
            link(work, t('my_tasks'), 'bi-check2-square', function () { window.location.href = CFG.urls.tasks; });

            if (CFG.notes) {
                link(work, t('nav_notes'), 'bi-journal-text', function () { window.location.href = CFG.urls.notes; });
            }
            link(work, t('planning_board'), 'bi-calendar-week', function () { window.location.href = CFG.urls.board; });
            link(work, t('work_calendar'), 'bi-calendar3', function () { window.location.href = CFG.urls.calendar; });

            var records = group(t('nav_records'));
            link(records, t('nav_timeline'), 'bi-clock-history', function () { window.location.href = CFG.urls.timeline; });
            link(records, t('archived_channels'), 'bi-archive', function () { self.showArchived(); });

            if (BOOT.me.rights.staff) {
                link(records, t('private_channels_audit'), 'bi-shield-lock', function () { self.showAudit(); });
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

        reloadChannels: function (openId) {
            var self = this;

            api('ws_channels').then(function (data) {
                BOOT.channels = data.channels;
                self.drawSide();

                if (openId) {
                    self.open(openId, 0);
                }
            }).catch(fail);
        },

        showArchived: function () {
            var self = this;
            var node = offcanvas('ws-archived', t('archived_channels'));
            var body = clear(node.querySelector('.offcanvas-body'));
            node.querySelector('.offcanvas-footer').classList.add('d-none');
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
            var body = clear(node.querySelector('.offcanvas-body'));
            node.querySelector('.offcanvas-footer').classList.add('d-none');
            body.appendChild(el('p', 'small text-body-secondary', t('audit_help')));
            showOffcanvas(node);

            api('ws_audit_list').then(function (data) {
                if (!data.channels.length) {
                    body.appendChild(el('div', 'ws-empty', t('no_private_channels')));
                }

                data.channels.forEach(function (channel) {
                    var row = el('div', 'd-flex align-items-center gap-2 py-2 border-bottom');
                    var text = el('div', 'flex-grow-1');
                    text.appendChild(el('div', 'fw-semibold', channel.name));
                    text.appendChild(el('small', 'text-body-secondary', t('audit_row', channel.owner, channel.members)));
                    row.appendChild(icon('bi-lock'));
                    row.appendChild(text);

                    var openButton = button('btn btn-sm ' + (channel.opened ? 'btn-ghost' : 'btn-outline-warning'), channel.opened ? t('open') : t('open_for_audit'), 'bi-eye');
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

        showHome: function (asked) {
            var self = this;

            self.stop();
            self.channel = null;
            self.view = 'home';
            self.drawSide();

            var center = clear(self.center);
            var head = el('div', 'ws-head');
            var toggle = button('btn btn-sm btn-ghost d-md-none', '', 'bi-list', t('channels'));
            toggle.addEventListener('click', function () { self.root.classList.add('ws-show-side'); });
            head.appendChild(toggle);

            var title = el('div', 'ws-head-title');
            var h = el('h2');
            h.appendChild(icon('bi-house', 'me-1 text-body-secondary'));
            h.appendChild(document.createTextNode(t('home')));
            title.appendChild(h);
            var date = el('div', 'ws-topic');
            title.appendChild(date);
            head.appendChild(title);
            center.appendChild(head);

            var body = el('div', 'ws-home');
            body.appendChild(el('div', 'ws-empty', t('loading')));
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

            var layout = el('div', 'row g-3');
            var main = el('div', 'col-xl-8');
            var side = el('div', 'col-xl-4');
            layout.appendChild(main);
            layout.appendChild(side);

            body.appendChild(el('h3', 'ws-home-hello', t('home_hello', data.name)));
            body.appendChild(layout);

            var pair = el('div', 'row g-3');
            var tasksCol = el('div', 'col-md-6');
            var talkCol = el('div', 'col-md-6');
            pair.appendChild(tasksCol);
            pair.appendChild(talkCol);
            main.appendChild(pair);

            // Coming up: the person's open tasks, the nearest due date first.
            var tasks = homeCard(t('home_tasks'), 'bi-check2-square', CFG.urls.tasks, t('my_tasks'));

            if (data.tasks_open) {
                var counts = el('div', 'ws-home-counts');
                counts.appendChild(el('span', '', t('home_tasks_count', data.tasks_open)));

                if (data.tasks_overdue) {
                    counts.appendChild(el('span', 'text-danger', t('home_tasks_overdue', data.tasks_overdue)));
                }

                tasks.body.appendChild(counts);
            }

            if (!data.tasks.length) {
                tasks.body.appendChild(el('div', 'ws-home-empty', t('home_tasks_empty')));
            }

            data.tasks.forEach(function (task) {
                var row = button('ws-home-row');
                row.appendChild(icon(task.overdue ? 'bi-exclamation-circle' : 'bi-circle', task.overdue ? 'text-danger' : 'text-body-secondary'));

                var text = el('span', 'ws-home-row-text');
                text.appendChild(el('span', 'ws-home-row-title', task.title));
                text.appendChild(el('small', task.overdue ? 'text-danger' : 'text-body-secondary', task.number + ' · ' + task.due_label));
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

            // What this place is for: open for somebody who has not written
            // anything yet, folded for the others.
            var about = el('details', 'card ws-home-about');
            about.open = !!data.newcomer;

            var summary = el('summary', 'card-header');
            summary.appendChild(icon('bi-info-circle', 'me-1'));
            summary.appendChild(document.createTextNode(t('home_about')));
            about.appendChild(summary);

            var aboutBody = el('div', 'card-body');
            aboutBody.appendChild(el('p', 'mb-3', t('home_about_text')));

            var tiles = el('div', 'row g-2');
            var tileList = [
                ['bi-hash', t('channels'), t('home_about_channels')],
                ['bi-check2-square', t('nav_work'), t('home_about_tasks')],
                ['bi-patch-check', t('nav_timeline'), t('home_about_decisions')]
            ];

            if (data.claude) {
                tileList.push(['bi-stars', 'Claude', t('home_about_claude')]);
            }

            tileList.forEach(function (tile) {
                var col = el('div', 'col-sm-6');
                var box = el('div', 'ws-home-tile');
                var name = el('div', 'ws-home-tile-title');
                name.appendChild(icon(tile[0], 'me-1'));
                name.appendChild(document.createTextNode(tile[1]));
                box.appendChild(name);
                box.appendChild(el('div', 'small text-body-secondary', tile[2]));
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
            var publicButton = button('btn btn-sm btn-primary rounded-pill px-3', t('home_new_channel'), 'bi-hash');
            publicButton.addEventListener('click', function () { channelForm(null, { kind: 'public' }, created); });
            start.appendChild(publicButton);

            var privateButton = button('btn btn-sm btn-outline-secondary rounded-pill px-3', t('home_new_private'), 'bi-lock');
            privateButton.addEventListener('click', function () { channelForm(null, { kind: 'private' }, created); });
            start.appendChild(privateButton);
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
                    row.appendChild(icon('bi-hash', 'text-body-secondary'));

                    var text = el('span', 'ws-home-row-text');
                    text.appendChild(el('span', 'ws-home-row-title', channel.name));
                    text.appendChild(el('small', 'text-body-secondary', (channel.topic ? channel.topic + ' · ' : '') + t('home_members', channel.members)));
                    row.appendChild(text);

                    var joinButton = button('btn btn-sm btn-outline-primary rounded-pill', t('join'));
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

        drawEmptyCenter: function () {
            var center = clear(this.center);
            var empty = el('div', 'ws-empty m-auto');
            empty.appendChild(icon('bi-chat-square-text'));
            empty.appendChild(document.createTextNode(t('pick_channel')));
            center.appendChild(empty);
        },

        // ── One channel ──

        open: function (channelId, messageId) {
            var self = this;

            self.stop();
            self.pending = null;
            self.reply = null;
            self.editing = null;
            self.labels = [];

            api('ws_channel_open', { channel_id: channelId, message_id: messageId || 0 }).then(function (data) {
                self.view = 'channel';
                self.channel = data.channel;
                self.messages = data.messages;
                self.briefing = data.briefing || null;

                // Opening a channel reads it; the sidebar says so at once
                // rather than on the next sync.
                (BOOT.channels || []).forEach(function (item) {
                    if (item.id === channelId) {
                        item.unread = 0;
                        item.mentions = 0;
                    }
                });

                self.lastId = data.last_id;
                self.sinceTs = data.now;
                self.hasMore = data.has_more;
                self.tab = 'messages';
                self.drawCenter();
                self.drawSide();

                if (messageId) {
                    var target = self.center.querySelector('[data-ws-message="' + messageId + '"]');

                    if (target) {
                        target.classList.add('ws-msg-highlight');
                        target.scrollIntoView({ block: 'center' });
                    }
                } else {
                    self.scrollToEnd();
                }

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', CFG.urls.workspace + '?channel=' + channelId);
                }

                self.schedule();
            }).catch(function (error) {
                fail(error);
                self.drawEmptyCenter();
            });
        },

        drawCenter: function () {
            var self = this;
            var channel = self.channel;
            var center = clear(self.center);

            // Header: name, topic, the customer, the people, the tools.
            var head = el('div', 'ws-head');
            var toggle = button('btn btn-sm btn-ghost d-md-none', '', 'bi-list', t('channels'));
            toggle.addEventListener('click', function () { self.root.classList.add('ws-show-side'); });
            head.appendChild(toggle);

            var title = el('div', 'ws-head-title');
            var h = el('h2');
            h.appendChild(icon(channel.kind === 'private' ? 'bi-lock' : 'bi-hash', 'me-1 text-body-secondary'));
            h.appendChild(document.createTextNode(channel.name));
            title.appendChild(h);

            var topic = el('div', 'ws-topic');

            if (channel.contact) {
                setHtml(topic, channel.contact.html);
                topic.appendChild(document.createTextNode(' '));
            }

            if (channel.department) {
                topic.appendChild(el('span', 'badge me-1', channel.department.name)).style.background = channel.department.color;
            }

            topic.appendChild(document.createTextNode(channel.topic || ''));
            title.appendChild(topic);
            head.appendChild(title);

            var actions = el('div', 'ws-head-actions');
            var faces = button('btn btn-sm btn-ghost d-inline-flex align-items-center gap-1', '', '', t('members'));
            var stack = el('span', 'ws-avatars');

            channel.members.slice(0, 4).forEach(function (member) { stack.appendChild(avatar(member)); });
            faces.appendChild(stack);
            faces.appendChild(el('span', 'small', channel.members.length));
            faces.addEventListener('click', function () { self.showMembers(); });
            actions.appendChild(faces);

            var addTask = button('btn btn-sm btn-ghost', '', 'bi-check2-square', t('new_task'));
            addTask.addEventListener('click', function () {
                taskDrawer.open(0, { channel_id: channel.id, department_id: channel.department ? channel.department.id : 0, refs: channel.contact ? [{ type: 'contact', id: channel.contact.id, label: channel.contact.label, icon: 'bi-person-vcard', token: '<#contact:' + channel.contact.id + '>' }] : [] }, function () { self.sync(); });
            });
            actions.appendChild(addTask);

            actions.appendChild(self.channelMenu());
            head.appendChild(actions);
            center.appendChild(head);

            if (channel.audit) {
                var auditBar = el('div', 'alert alert-warning rounded-0 m-0 py-2 small');
                auditBar.appendChild(icon('bi-shield-lock', 'me-1'));
                auditBar.appendChild(document.createTextNode(t('audit_bar')));
                center.appendChild(auditBar);
            } else if (!channel.joined && channel.kind === 'public') {
                var joinBar = el('div', 'alert alert-info rounded-0 m-0 py-2 small d-flex align-items-center gap-2');
                joinBar.appendChild(el('span', 'flex-grow-1', t('not_joined')));
                var join = button('btn btn-sm btn-primary rounded-pill px-3', t('join'));
                join.addEventListener('click', function () {
                    api('ws_channel_join', { channel_id: channel.id }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                });
                joinBar.appendChild(join);
                center.appendChild(joinBar);
            }

            var tabs = el('div', 'ws-tabs');
            tabs.setAttribute('role', 'tablist');

            [['messages', t('tab_messages')], ['decisions', t('tab_decisions')], ['tasks', t('tab_tasks')], ['files', t('tab_files')], ['summary', t('tab_summary')]].forEach(function (tab) {
                var item = button(self.tab === tab[0] ? 'active' : '', tab[1]);
                item.setAttribute('role', 'tab');
                item.addEventListener('click', function () {
                    self.tab = tab[0];
                    self.drawCenter();

                    if (tab[0] === 'messages') {
                        self.scrollToEnd();
                    }
                });
                tabs.appendChild(item);
            });

            center.appendChild(tabs);

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
            }

            var pane = el('div', 'ws-pane ws-pane-' + self.tab);
            center.appendChild(pane);
            self.pane = pane;

            // A picture that finishes loading under the newest line pushes
            // it out of view; whoever was reading at the end stays there.
            // (The conversation is no longer pulled down on every look.)
            self.atEnd = true;
            pane.addEventListener('scroll', function () {
                self.atEnd = self.nearEnd();
            }, { passive: true });
            pane.addEventListener('load', function (event) {
                if (self.atEnd && event.target && (event.target.tagName === 'IMG')) {
                    self.scrollToEnd();
                }
            }, true);

            if (self.tab === 'messages') {
                self.drawMessages();
                center.appendChild(self.composer());
                self.fit();
            } else if (self.tab === 'decisions') {
                self.loadList('ws_channel_decisions', t('no_decisions'));
            } else if (self.tab === 'files') {
                self.drawFiles();
            } else if (self.tab === 'tasks') {
                self.drawTasks();
            } else {
                self.drawSummary();
            }
        },

        channelMenu: function () {
            var self = this;
            var channel = self.channel;
            var wrap = el('div', 'dropdown');
            var toggle = button('btn btn-sm btn-ghost', '', 'bi-three-dots-vertical', t('more'));
            toggle.setAttribute('data-bs-toggle', 'dropdown');
            wrap.appendChild(toggle);

            var menu = el('ul', 'dropdown-menu dropdown-menu-end');

            function item(label, iconName, handler) {
                var li = el('li');
                var link = button('dropdown-item link-body-emphasis', label, iconName);
                link.addEventListener('click', handler);
                li.appendChild(link);
                menu.appendChild(li);
            }

            function divider() {
                var li = el('li');
                li.appendChild(el('hr', 'dropdown-divider'));
                menu.appendChild(li);
            }

            if (channel.can_manage) {
                item(t('edit_channel'), 'bi-pencil', function () {
                    channelForm(channel, {}, function (id) { self.reloadChannels(id); });
                });
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

            if (channel.kind === 'private' && channel.can_manage) {
                item(t('make_public'), 'bi-unlock', function () {
                    ask(t('make_public_confirm'), t('make_public'), true).then(function (yes) {
                        if (yes) {
                            api('ws_channel_make_public', { channel_id: channel.id }).then(function () { self.reloadChannels(channel.id); }).catch(fail);
                        }
                    });
                });
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

            wrap.appendChild(menu);

            return wrap;
        },

        showMembers: function () {
            var self = this;
            var channel = self.channel;
            var node = offcanvas('ws-members', t('members'));
            var body = clear(node.querySelector('.offcanvas-body'));
            var footer = clear(node.querySelector('.offcanvas-footer'));

            footer.classList.add('d-none');

            // Claude, where it can be asked: listed, but no person to open.
            if (BOOT.claude && channel.claude && channel.claude.available) {
                var claudeRow = el('div', 'd-flex align-items-center gap-2 py-2 border-bottom');
                var claudeFace = avatar({ avatar: BOOT.claude.avatar }, '2rem');

                claudeFace.style.borderRadius = '50%';
                claudeRow.appendChild(claudeFace);

                var claudeText = el('div', 'flex-grow-1');
                claudeText.appendChild(el('div', 'fw-semibold', BOOT.claude.name));
                claudeText.appendChild(el('small', 'text-body-secondary', t('claude_member')));
                claudeRow.appendChild(claudeText);
                body.appendChild(claudeRow);
            }

            channel.members.forEach(function (member) {
                var row = el('div', 'd-flex align-items-center gap-2 py-2 border-bottom');
                onContext(row, function () { return personMenu(member); });
                row.appendChild(avatar(member, '2rem')).style.borderRadius = '50%';
                var text = el('div', 'flex-grow-1');
                text.appendChild(el('div', 'fw-semibold', member.name));
                text.appendChild(el('small', 'text-body-secondary', (member.id === channel.owner_id ? t('owner') + ' · ' : '') + (member.title || '')));
                row.appendChild(text);
                row.appendChild(el('span', 'badge rounded-pill ' + (member.presence === 'online' ? 'text-bg-success' : 'text-bg-secondary'), t('presence_' + member.presence)));

                if (channel.can_manage && member.id !== channel.owner_id && member.id !== BOOT.me.id) {
                    var remove = button('btn btn-sm btn-ghost', '', 'bi-x-lg', t('remove'));
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
                    body.appendChild(el('div', 'form-label mt-3', t('add_people')));
                    var picker = peoplePicker([], outside);
                    body.appendChild(picker);

                    footer.classList.remove('d-none');
                    var add = button('btn btn-sm btn-primary rounded-pill px-3', t('add_people'), 'bi-person-plus');
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
                var older = button('btn btn-sm btn-ghost d-block mx-auto mb-2', t('older_messages'), 'bi-arrow-up');
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

            self.messages.forEach(function (message) {
                self.appendMessage(message, previous, pane);
                previous = message;
            });
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
                line.appendChild(el('span', 'ms-2', message.time));

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
            face.appendChild(avatar(message.sender));
            node.appendChild(face);

            var main = el('div', 'ws-msg-main');
            var meta = el('div', 'ws-msg-meta');
            meta.appendChild(el('b', '', message.sender ? message.sender.name : ''));
            meta.appendChild(el('span', '', message.time));

            if (message.edited) {
                meta.appendChild(el('span', '', t('edited')));
            }

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

            main.appendChild(meta);

            if (message.parent_id) {
                var parent = self.findMessage(message.parent_id);
                var quote = el('div', 'ws-msg-quote');
                quote.appendChild(icon('bi-reply', 'me-1'));
                quote.appendChild(document.createTextNode(parent ? ((parent.sender ? parent.sender.name + ': ' : '') + self.plain(parent.html)) : t('earlier_message')));
                main.appendChild(quote);
            }

            var body = el('div', 'ws-msg-body');

            if (message.deleted) {
                body.textContent = t('message_deleted');
            } else if (message.note_card) {
                body.appendChild(self.noteCardNode(message.note_card));
            } else {
                setHtml(body, message.html);
                chipMenus(body);
                self.bindChecks(body, message);
            }

            main.appendChild(body);

            if (message.poll) {
                main.appendChild(self.pollNode(message));
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

            if (message.reactions && message.reactions.length) {
                main.appendChild(self.reactionRow(message));
            }

            // Somebody mentioned here who is not in the channel: one click
            // brings them in, and the mention reaches them.
            if (message.invite && message.invite.length && !message.deleted) {
                main.appendChild(self.inviteNode(message));
            }

            if (message.claude) {
                main.appendChild(self.claudeStateNode(message));
            }

            node.appendChild(main);

            if (!message.deleted && message.kind !== 'task') {
                node.appendChild(self.messageTools(message));
                onContext(node, function (event) { return self.messageActions(message, event); });

                // No hover on a phone: a tap on the message shows its tools.
                node.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, input, label, .ws-task-card, .ws-poll')) {
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

        plain: function (markup, full) {
            var box = document.createElement('div');
            box.innerHTML = markup || '';
            var text = (box.textContent || '').replace(/\s+/g, ' ').trim();
            return (!full && text.length > 90) ? text.slice(0, 89) + '…' : text;
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

            // A checklist item becomes a task of its own.
            if (checkItem && self.channel.can_post) {
                var itemText = ((checkItem.querySelector('.ws-check-text') || checkItem).textContent || '').trim();

                items.push({ icon: 'bi-check2-square', label: t('task_from_item'), action: function () {
                    taskDrawer.open(0, {
                        channel_id: self.channel.id,
                        title: itemText.slice(0, 200),
                        department_id: self.channel.department ? self.channel.department.id : 0
                    }, function () { self.sync(); });
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
                    taskDrawer.open(0, {
                        channel_id: self.channel.id,
                        title: self.plain(message.html).slice(0, 200),
                        description: message.raw || '',
                        department_id: self.channel.department ? self.channel.department.id : 0
                    }, function () { self.sync(); });
                } });
            }

            // Anybody's message, kept as a copy in the person's own notes.
            if (CFG.notes && !message.deleted && (message.sender_kind === 'user' || message.sender_kind === 'app')
                && ['message', 'note', 'decision'].indexOf(message.kind) !== -1) {
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

            items.push('-');
            items.push({ icon: 'bi-clipboard', label: t('copy_text'), action: function () {
                copyText(self.plain(message.html, true));
            } });
            items.push({ icon: 'bi-link-45deg', label: t('copy_link'), action: function () {
                copyText(absoluteUrl(CFG.urls.workspace + '?channel=' + self.channel.id + '&message=' + message.id));
            } });

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

            if (message.can_edit || message.can_delete) {
                items.push('-');
            }

            if (message.can_edit) {
                items.push({ icon: 'bi-pencil', label: t('edit'), tool: true, action: function () { self.editMessage(message); } });
            }

            if (message.can_delete) {
                items.push({ icon: 'bi-trash', label: t('delete'), tool: true, danger: true, action: function () {
                    ask(t('delete_confirm'), t('delete'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        api('ws_delete', { message_id: message.id }).then(function () {
                            message.deleted = true;
                            message.html = '';
                            message.file = null;
                            self.replaceMessage(message);
                        }).catch(fail);
                    });
                } });
            }

            return items;
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
                text.appendChild(go);
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
                    var large = button('btn btn-sm btn-ghost py-0', '', 'bi-arrows-fullscreen', t('preview'));
                    large.addEventListener('click', function () { previewFile(file); });
                    bar.appendChild(large);
                }

                var save = el('a', 'btn btn-sm btn-ghost py-0');
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

            if (kind === 'pdf') {
                var look = button('btn btn-sm btn-outline-secondary', t('preview'), 'bi-eye');
                look.addEventListener('click', function () { previewFile(file); });
                card.appendChild(look);
            }

            if (file.text) {
                var read = button('btn btn-sm btn-outline-secondary', t('file_edit_text'), 'bi-file-earmark-text');
                read.addEventListener('click', function () { self.openText(message); });
                card.appendChild(read);
            }

            var get = el('a', 'btn btn-sm btn-ghost');
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

            if (card.excerpt) {
                box.appendChild(el('div', 'ws-nb-msg-card-text', card.excerpt));
            }

            var foot = el('div', 'ws-nb-msg-card-foot');

            if (card.owner) {
                foot.appendChild(el('span', '', t('notes_owner', card.owner.name)));
            }

            if (card.updated_by) {
                foot.appendChild(el('span', '', t('notes_last_change', card.updated_by.name, card.updated)));
            }

            var read = button('btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 ms-auto', t('notes_card_open'), 'bi-eye');
            read.addEventListener('click', function (event) {
                event.stopPropagation();
                openNoteView(card.id);
            });
            foot.appendChild(read);
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

            var invite = button('btn btn-sm btn-outline-primary rounded-pill py-0 px-2', t('mention_invite'), 'bi-person-plus', t('mention_invite_help'));
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

        // A text file of the conversation in a window: read it, and save it
        // over the person's own file or send an edited copy of anybody
        // else's. A Markdown file also shows as it reads, figures worked out.
        openText: function (message) {
            var self = this;

            api('ws_file_text', { message_id: message.id }).then(function (data) {
                var node = el('div', 'modal fade ws-text-modal');
                node.tabIndex = -1;
                node.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down"><div class="modal-content">'
                    + '<div class="modal-header"><h5 class="modal-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
                    + '<div class="modal-body"></div><div class="modal-footer"></div></div></div>';
                node.querySelector('.modal-title').appendChild(icon('bi-file-earmark-text', 'me-2'));
                node.querySelector('.modal-title').appendChild(document.createTextNode(data.name));
                node.querySelector('.btn-close').setAttribute('aria-label', t('close'));

                var body = node.querySelector('.modal-body');
                var footer = node.querySelector('.modal-footer');
                var area = el('textarea', 'form-control ws-text-area');
                var view = el('div', 'ws-msg-body ws-text-view d-none');
                var writable = !!(data.can_replace || data.can_send);

                area.value = data.text;
                area.spellcheck = false;
                area.readOnly = !writable;
                area.setAttribute('aria-label', data.name);

                if (data.markdown) {
                    var tabs = el('div', 'btn-group btn-group-sm mb-2 ws-text-tabs');
                    var write = button('btn btn-outline-secondary active', t('file_text_write'), 'bi-pencil');
                    var read = button('btn btn-outline-secondary', t('file_text_view'), 'bi-eye');

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
                        view.appendChild(el('div', 'small text-body-secondary', t('loading')));

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

                footer.appendChild(el('div', 'ws-grow small text-body-secondary', data.can_replace ? t('file_text_mine_help') : (data.can_send ? t('file_text_copy_help') : t('file_text_readonly'))));

                var close = button('btn btn-sm btn-ghost', t('close'));
                close.setAttribute('data-bs-dismiss', 'modal');
                footer.appendChild(close);

                var instance = null;

                if (writable) {
                    var save = button('btn btn-sm btn-primary rounded-pill px-3', data.can_replace ? t('file_text_save') : t('file_text_send'), data.can_replace ? 'bi-check2' : 'bi-send');

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
                instance = bootstrap.Modal.getOrCreateInstance(node);
                node.addEventListener('hidden.bs.modal', function () { node.remove(); });
                node.addEventListener('shown.bs.modal', function () { area.focus(); });
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
                    previous = (i > 0) ? this.messages[i - 1] : null;
                }
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
        jumpTo: function (messageId) {
            var target = this.center.querySelector('[data-ws-message="' + messageId + '"]');

            if (target) {
                target.classList.add('ws-msg-highlight');
                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            } else {
                this.open(this.channel.id, messageId);
            }
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
            }).catch(fail);
        },

        // ── Checklists ──

        // The whole list becomes one task; the list stays here and both
        // places tick the same items.
        listToTask: function (message) {
            var self = this;
            var box = document.createElement('div');

            box.innerHTML = message.html || '';

            var first = box.querySelector('.ws-text');
            var title = first ? (first.textContent || '').replace(/\s+/g, ' ').trim().replace(/[:：]$/, '') : '';

            taskDrawer.open(0, {
                channel_id: self.channel.id,
                title: (title || t('list_task_title')).slice(0, 200),
                checklist_message_id: message.id,
                list_count: box.querySelectorAll('input[data-ws-check]').length,
                department_id: self.channel.department ? self.channel.department.id : 0
            }, function () { self.sync(true); });
        },

        // ── Claude ──

        claudeStateNode: function (message) {
            var state = message.claude;
            var line = el('div', 'ws-claude-state ws-claude-' + state.status);

            line.appendChild(icon(state.icon));
            line.appendChild(el('span', '', state.label));

            return line;
        },

        // Record changes an answer proposes: what each field holds and would
        // hold. Only the person who asked applies them, with their own rights.
        changesNode: function (message) {
            var self = this;
            var box = el('div', 'ws-drafts ws-changes');

            box.appendChild(el('div', 'ws-drafts-title', t('claude_changes')));

            message.changes.forEach(function (change) {
                var card = el('div', 'ws-draft ws-change ws-change-' + change.status + ' ws-change-do-' + change.action);
                var head = el('div', 'ws-draft-head');
                var icons = { create: 'bi-plus-square', 'delete': 'bi-trash3', add: 'bi-box-arrow-in-right', remove: 'bi-box-arrow-right' };

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

                if (change.hidden) {
                    card.appendChild(el('div', 'ws-draft-meta', t('change_hidden', change.count)));
                } else {
                    var list = el('dl', 'ws-change-fields');

                    change.fields.forEach(function (field) {
                        list.appendChild(el('dt', '', field.label));

                        var value = el('dd');

                        if (field.html) {
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

                if (change.warning && change.status === 'pending') {
                    var warning = el('div', 'ws-change-warning');
                    warning.appendChild(icon('bi-exclamation-triangle', 'me-1'));
                    warning.appendChild(document.createTextNode(change.warning));
                    card.appendChild(warning);
                }

                var actions = el('div', 'ws-draft-actions');

                if (change.can_apply) {
                    var apply = button('btn btn-sm btn-primary rounded-pill px-3', t('change_apply'), 'bi-check2');

                    apply.addEventListener('click', function () {
                        var go = change.warning ? ask(change.warning, t('change_apply'), true) : Promise.resolve(true);

                        go.then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            apply.disabled = true;

                            if (dismiss) {
                                dismiss.disabled = true;
                            }

                            api('ws_ai_change_apply', { change_id: change.id }).then(function () {
                                toast(t('change_done'), 'success');
                                self.sync();
                            }).catch(function (error) {
                                fail(error);
                                self.sync();
                            });
                        });
                    });

                    actions.appendChild(apply);
                }

                var dismiss = null;

                if (change.can_dismiss) {
                    dismiss = button('btn btn-sm btn-ghost', t('change_dismiss'), 'bi-x-lg');

                    dismiss.addEventListener('click', function () {
                        dismiss.disabled = true;
                        api('ws_ai_change_dismiss', { change_id: change.id }).then(function () { self.sync(); }).catch(function (error) {
                            dismiss.disabled = false;
                            fail(error);
                        });
                    });

                    actions.appendChild(dismiss);
                }

                if (actions.childNodes.length) {
                    card.appendChild(actions);
                }

                if (change.status === 'pending' && !change.can_apply) {
                    card.appendChild(el('div', 'ws-draft-done', change.no_right ? t('change_no_right') : t('change_waiting', change.asker)));
                } else if (change.status === 'applied') {
                    var done = el('div', 'ws-draft-done', t('change_applied', change.decided_by, change.decided) + ' ');

                    if (change.decision_id) {
                        var see = el('a', '', t('change_see_decision'));
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
                    card.appendChild(el('div', 'ws-draft-done text-warning-emphasis', t('change_stale')));
                } else if (change.status === 'failed') {
                    card.appendChild(el('div', 'ws-draft-done text-danger-emphasis', t('change_failed', change.error)));
                }

                box.appendChild(card);
            });

            return box;
        },

        draftsNode: function (message) {
            var self = this;
            var box = el('div', 'ws-drafts');

            box.appendChild(el('div', 'ws-drafts-title', t('claude_drafts')));

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
                    var accept = button('btn btn-sm btn-primary rounded-pill px-3', t('claude_draft_open'), 'bi-plus-lg');
                    var dismiss = button('btn btn-sm btn-ghost', t('claude_draft_dismiss'), 'bi-x-lg');

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

        bindChecks: function (body, message) {
            var self = this;

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
                var input = el('input', 'form-check-input');

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
                    var faces = el('span', 'ws-avatars ws-avatars-sm');

                    option.voters.slice(0, 4).forEach(function (voter) {
                        faces.appendChild(avatar(voter));
                    });

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
                var undo = button('btn btn-sm btn-ghost', t('poll_withdraw'), 'bi-arrow-counterclockwise');
                undo.addEventListener('click', function () { self.vote(message, []); });
                foot.appendChild(undo);
            }

            if (poll.result_id) {
                var result = button('btn btn-sm btn-ghost', t('poll_result'), 'bi-patch-check');
                result.addEventListener('click', function () { self.jumpTo(poll.result_id); });
                foot.appendChild(result);
            }

            if (poll.can_close) {
                var shut = button('btn btn-sm btn-outline-secondary', t('poll_close'), 'bi-lock');
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
            var body = clear(node.querySelector('.offcanvas-body'));
            var footer = clear(node.querySelector('.offcanvas-footer'));
            var questionLabels = [];

            node.querySelector('.offcanvas-title').textContent = poll ? t('edit_poll') : t('new_poll');
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
            var addOption = button('btn btn-sm btn-ghost', t('poll_add_option'), 'bi-plus-lg');

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
                var input = el('input', 'form-control' + (voted ? ' text-body-secondary' : ''));
                var remove = button('btn btn-outline-secondary', '', voted ? 'bi-lock' : 'bi-x-lg', voted ? t('poll_option_voted') : t('remove'));

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
                var wrap = el('div', 'form-check form-switch mb-2');
                var input = el('input', 'form-check-input');
                var caption = el('label', 'form-check-label', label);

                input.type = 'checkbox';
                input.id = nextId('ws-poll-switch-');
                input.setAttribute('role', 'switch');
                caption.htmlFor = input.id;
                wrap.appendChild(input);
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
                    body.appendChild(el('div', 'form-text mt-0 mb-2', t('poll_locked_voted')));
                }
            }

            var grid = el('div', 'row g-2 mt-1');
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

            var cancel = button('btn btn-sm btn-ghost', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'offcanvas');
            footer.appendChild(cancel);

            var create = button('btn btn-sm btn-primary rounded-pill px-3', poll ? t('save') : t('start_poll'), poll ? 'bi-check2' : 'bi-send');
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
                });
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
                });
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
                self.dragging = { id: channel.id, box: box };
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

            api('ws_messages_before', { channel_id: self.channel.id, before_id: first }).then(function (data) {
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

        // ── Composer ──

        composer: function () {
            var self = this;
            var wrap = el('div', 'ws-composer-wrap');
            self.composerWrap = wrap;

            if (!self.channel.can_post) {
                wrap.appendChild(el('div', 'small text-body-secondary text-center py-2', self.channel.audit ? t('audit_readonly') : (self.channel.archived ? t('archived_readonly') : t('cannot_post'))));
                return wrap;
            }

            self.previewBox = el('div');
            wrap.appendChild(self.previewBox);

            self.replyBar = el('div');
            wrap.appendChild(self.replyBar);

            var box = el('div', 'ws-composer');

            // The keys the box leaves to the conversation: the picker's,
            // Escape, and Arrow Up in an empty box to change one's last
            // message. true when used.
            function keys(event) {
                if (self.picker && self.pickerKey(event)) {
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

            var tools = el('div', 'ws-composer-tools');
            var attach = button('btn btn-sm btn-ghost', '', 'bi-paperclip', t('attach'));
            attach.addEventListener('click', function () { self.attach(); });
            tools.appendChild(attach);

            var mention = button('btn btn-sm btn-ghost', '', 'bi-at', t('mention'));
            mention.addEventListener('click', function () { self.insertTrigger('@'); });
            tools.appendChild(mention);

            var record = button('btn btn-sm btn-ghost', '', 'bi-hash', t('tag_record'));
            record.addEventListener('click', function () { self.insertTrigger('#'); });
            tools.appendChild(record);

            var emoji = button('btn btn-sm btn-ghost', '', 'bi-emoji-smile', t('insert_emoji'));
            emoji.addEventListener('click', function (event) {
                var box = emoji.getBoundingClientRect();

                event.stopPropagation();
                emojiPicker(box.left, box.top - 6, function (picked) { self.insertText(picked); }, true);
            });
            tools.appendChild(emoji);

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
                    BOOT.interact ? { icon: 'bi-bar-chart-line', label: t('start_poll'), action: function () { self.pollForm({}); } } : null
                ], true);
            });
            tools.appendChild(insert);

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
            tools.appendChild(command);

            tools.appendChild(el('span', 'ws-grow ws-composer-hint d-none d-lg-inline', t('composer_hint')));

            var send = button('btn btn-sm btn-primary rounded-pill px-3', t('send'), 'bi-send');
            send.addEventListener('click', function () { self.send(); });
            tools.appendChild(send);
            box.appendChild(tools);
            wrap.appendChild(box);

            input.addEventListener('input', function () {
                self.autosize();
                self.pickerCheck();
            });
            input.addEventListener('click', function () { self.pickerCheck(); });

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

        autosize: function () {
            var input = this.input;

            // The rich box grows by itself, up to its CSS height.
            if (!this.rich) {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 192) + 'px';
            }

            this.fit();
        },

        // What is in the box, as the markup a message is stored in.
        inputText: function () {
            return this.rich ? this.rich.markup() : this.input.value;
        },

        inputEmpty: function () {
            return this.rich ? this.rich.isEmpty() : (this.input.value === '');
        },

        clearInput: function () {
            if (this.rich) {
                this.rich.clear();
            } else if (this.input) {
                this.input.value = '';
            }

            this.labels = [];
            this.autosize();
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

            if (!self.reply && !self.editing) {
                return;
            }

            var row = el('div', 'ws-reply-bar');
            row.appendChild(icon(self.editing ? 'bi-pencil' : 'bi-reply'));
            row.appendChild(el('span', '', self.editing ? t('editing_message') : t('replying_to', self.reply.sender ? self.reply.sender.name : '', self.plain(self.reply.html))));

            // An answer to Claude goes to Claude.
            if (!self.editing && self.reply.sender && self.reply.sender.claude && self.channel.claude && self.channel.claude.available) {
                row.appendChild(el('span', 'text-body-secondary ms-1', t('claude_reply_hint')));
            }
            var cancel = button('btn btn-sm btn-ghost py-0', '', 'bi-x-lg', t('cancel'));
            cancel.addEventListener('click', function () { self.cancelCompose(); });
            row.appendChild(cancel);
            bar.appendChild(row);
        },

        cancelCompose: function () {
            if (this.picker) {
                this.closePicker();
                return;
            }

            if (this.editing) {
                this.clearInput();
            }

            this.reply = null;
            this.editing = null;
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

            var body = self.tokens(raw);
            self.busy = true;

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

            var data = { channel_id: self.channel.id, body: body, parent_id: self.reply ? self.reply.id : 0 };

            Object.keys(options || {}).forEach(function (key) { data[key] = options[key]; });

            api('ws_send', data).then(function (result) {
                self.busy = false;

                if (result.command && result.command.type === 'preview') {
                    self.pending = { body: body };
                    self.drawPreview(result.command);
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
                    var close = button('btn btn-sm btn-ghost float-end py-0', '', 'bi-x-lg', t('close'));
                    close.addEventListener('click', function () { box.remove(); });
                    box.insertBefore(close, box.firstChild);
                    self.pane.appendChild(box);
                    self.scrollToEnd();
                }

                if (result.command && result.command.type === 'done' && result.command.task_id) {
                    toast(t('task_created'), 'success');
                }

                self.clearInput();
                self.reply = null;
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

            var actions = el('div', 'd-flex flex-wrap gap-2 mt-2');
            var hard = command.check && command.check.hard;

            if (!hard) {
                var create = button('btn btn-sm btn-primary rounded-pill px-3', t('create_task'), 'bi-check2');
                create.addEventListener('click', function () { self.send({ confirm: 1, assignees: command.assignees }); });
                actions.appendChild(create);
            } else if (command.may_override) {
                var force = button('btn btn-sm btn-outline-warning', t('create_anyway'), 'bi-exclamation-triangle');
                force.addEventListener('click', function () { self.send({ confirm: 1, force: 1, assignees: command.assignees }); });
                actions.appendChild(force);
            }

            var cancel = button('btn btn-sm btn-ghost', t('cancel'));
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
                dd.appendChild(el('br'));
                dd.appendChild(el('span', 'text-body-secondary', item.example));
                list.appendChild(dd);
            });

            box.appendChild(list);
            var close = button('btn btn-sm btn-ghost float-end py-0', '', 'bi-x-lg', t('close'));
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

        // Files picked, dropped or pasted: one after the other, the text in
        // the composer going with the first. What the file manager refuses
        // (PHP, .htaccess, web.config, shell scripts, programs) the server
        // refuses here too, and says so.
        uploadFiles: function (files) {
            var self = this;
            var queue = (files || []).slice();
            var total = queue.length;
            var done = 0;
            var body = self.input ? self.tokens(self.inputText()) : '';

            if (!total || !self.channel || !self.channel.can_post) {
                return;
            }

            function next() {
                var file = queue.shift();

                if (!file) {
                    if (done > 0) {
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
                        body: body
                    }).then(function () {
                        done++;

                        if (body !== '') {
                            body = '';
                            self.clearInput();
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

            if (!self.noCommands && (match = /^\/([^\s]*)$/.exec(before))) {
                self.openPicker('command', match[1], before.length - match[0].length);
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

            self.closePicker();
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

            if (mode === 'command') {
                var typed = '/' + query.toLocaleLowerCase();
                var commands = [];

                (BOOT.commands || []).forEach(function (item) {
                    var aliases = item.command.split(' · ');
                    var match = aliases[0];

                    if (query !== '') {
                        match = null;

                        aliases.forEach(function (alias) {
                            if (!match && alias.toLocaleLowerCase().indexOf(typed) === 0) {
                                match = alias;
                            }
                        });
                    }

                    if (match) {
                        commands.push({ label: match, meta: item.description, icon: 'bi-slash-square', insert: match + ' ' });
                    }
                });

                self.drawPicker(commands);
                return;
            }

            if (mode === 'people') {
                var needle = query.toLocaleLowerCase();
                var items = [];

                // In a note @ asks Claude; people are tagged with #.
                if (self.notesMode) {
                    if (BOOT.claude && (!needle || 'claude'.indexOf(needle) === 0)) {
                        items.push(BOOT.claude.token
                            ? { label: 'Claude', meta: t('claude_meta'), icon: 'bi-stars', token: BOOT.claude.token, shown: '@Claude' }
                            : { label: 'Claude', meta: t('claude_not_ready'), icon: 'bi-stars', insert: '@Claude ' });
                    }

                    self.drawPicker(items);
                    return;
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

                BOOT.people.forEach(function (member) {
                    if (!needle || member.name.toLocaleLowerCase().indexOf(needle) !== -1 || member.username.toLocaleLowerCase().indexOf(needle) !== -1) {
                        items.push({ label: member.name, meta: member.title, avatar: member.avatar, token: '<@user:' + member.id + '>', shown: shownLabel('@', member.name) });
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
                        people.push({ label: member.name, meta: t('notes_person'), avatar: member.avatar, token: '<@user:' + member.id + '>', shown: shownLabel('@', member.name) });
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

                api('ws_ref_search', { type: type, q: search }).then(function (data) {
                    if (!self.picker || self.pickerMode !== 'record' || asked !== search) {
                        return;
                    }

                    self.drawPicker(people.concat(data.items.map(function (item) {
                        return { label: item.label, meta: item.meta, icon: item.icon, token: item.token, shown: shownLabel('#', item.label) };
                    })));
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

                if (self.notesMode) {
                    self.placePicker();
                }

                return;
            }

            self.pickerItems = items;
            self.pickerIndex = 0;

            if (!items.length) {
                list.appendChild(el('div', 'ws-picker-empty', emptyText || t('nothing_found')));
            }

            items.forEach(function (item, index) {
                var row = el('button', 'ws-picker-item' + (index === 0 ? ' active' : ''));
                row.type = 'button';

                if (item.avatar) {
                    var img = el('img');
                    img.src = item.avatar;
                    img.alt = '';
                    row.appendChild(img);
                } else {
                    row.appendChild(icon(item.icon || 'bi-dot'));
                }

                row.appendChild(el('span', 'ws-picker-label', item.label));

                if (item.meta) {
                    row.appendChild(el('span', 'ws-picker-meta', item.meta));
                }

                row.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    self.pick(item);
                });
                list.appendChild(row);
            });

            if (self.notesMode) {
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

            if (event.key === 'Escape') {
                event.preventDefault();
                this.closePicker();
                return true;
            }

            if (!items.length) {
                return false;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                this.pickerIndex = (this.pickerIndex + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;

                Array.prototype.forEach.call(this.picker.querySelectorAll('.ws-picker-item'), function (row, index) {
                    row.classList.toggle('active', index === this.pickerIndex);
                }, this);

                return true;
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                this.pick(items[this.pickerIndex]);
                return true;
            }

            return false;
        },

        pick: function (item) {
            // The rich box: what was typed becomes a chip, or the command's text.
            if (this.rich) {
                var count = Math.max(0, this.beforeCaret().length - this.pickerStart);

                this.rich.replaceTyped(count, item.token
                    ? { token: item.token, shown: item.shown, icon: item.avatar ? 'bi-person' : (item.icon || '') }
                    : { text: item.insert || '' });
                this.closePicker();
                this.autosize();
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

            pane.appendChild(el('div', 'small text-body-secondary', t('loading')));

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
                    var jump = button('btn btn-sm btn-ghost', t('show_in_conversation'), 'bi-arrow-right-short');
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

            pane.appendChild(el('div', 'small text-body-secondary', t('loading')));

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

                    if (message.file.kind === 'video' || message.file.kind === 'pdf') {
                        items.push({ icon: 'bi-eye', label: t('preview'), action: function () { previewFile(message.file); } });
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
                        row.appendChild(el('small', 'text-body-secondary ws-grow', (message.sender ? message.sender.name + ' · ' : '') + message.time));

                        if (message.file.kind === 'pdf' || message.file.kind === 'audio') {
                            var look = button('btn btn-sm btn-ghost', '', message.file.kind === 'pdf' ? 'bi-eye' : 'bi-play-circle', t('preview'));
                            look.addEventListener('click', function () {
                                if (message.file.kind === 'pdf') {
                                    previewFile(message.file);
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

                        var jump = button('btn btn-sm btn-ghost', '', 'bi-chat-left-text', t('show_in_conversation'));
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
            var bar = el('div', 'pg-toolbar mb-2');
            var status = select([['open', t('open_tasks')], ['closed', t('closed_tasks')], ['all', t('all_tasks')]], self.taskStatus || 'open');
            status.classList.add('w-auto');
            status.addEventListener('change', function () {
                self.taskStatus = status.value;
                self.drawCenter();
            });
            bar.appendChild(status);

            var add = button('btn btn-sm btn-primary rounded-pill px-3', t('new_task'), 'bi-plus-lg');
            add.addEventListener('click', function () {
                taskDrawer.open(0, { channel_id: self.channel.id, department_id: self.channel.department ? self.channel.department.id : 0 }, function () { self.drawCenter(); });
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
                pane.appendChild(el('div', 'small text-body-secondary mt-2', channel.summary_updated));
            }

            if (channel.can_post) {
                var edit = button('btn btn-sm btn-outline-secondary mt-3', t('edit_summary'), 'bi-pencil');
                edit.addEventListener('click', function () {
                    clear(pane);
                    var area = el('textarea', 'form-control');
                    area.rows = 14;
                    area.value = channel.summary || '';
                    pane.appendChild(el('p', 'small text-body-secondary', t('summary_help')));
                    pane.appendChild(area);

                    var actions = el('div', 'd-flex gap-2 mt-2');
                    var save = button('btn btn-sm btn-primary rounded-pill px-3', t('save'), 'bi-check2');
                    save.addEventListener('click', function () {
                        api('ws_channel_summary', { channel_id: channel.id, summary: area.value }).then(function (data) {
                            self.channel = data.channel;
                            self.drawCenter();
                            toast(t('summary_saved'), 'success');
                        }).catch(fail);
                    });
                    var cancel = button('btn btn-sm btn-ghost', t('cancel'));
                    cancel.addEventListener('click', function () { self.drawCenter(); });
                    actions.appendChild(save);
                    actions.appendChild(cancel);
                    pane.appendChild(actions);
                    area.focus();
                });
                pane.appendChild(edit);
            }
        },

        search: function (query) {
            var self = this;

            if (query.trim().length < 2) {
                return;
            }

            api('ws_search', { q: query }).then(function (data) {
                var node = offcanvas('ws-search', t('search_results'));
                var body = clear(node.querySelector('.offcanvas-body'));
                node.querySelector('.offcanvas-footer').classList.add('d-none');

                if (!data.messages.length) {
                    body.appendChild(el('div', 'ws-empty', t('nothing_found')));
                }

                data.messages.forEach(function (message) {
                    var row = el('button', 'ws-record-item w-100 text-start border-0 bg-transparent');
                    row.type = 'button';
                    var head = el('div', 'd-flex gap-2');
                    head.appendChild(el('b', '', '#' + message.channel));
                    head.appendChild(el('span', 'text-body-secondary', (message.sender ? message.sender.name + ' · ' : '') + message.time));
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

            self.timer = setTimeout(function () {
                self.sync();
            }, (waiting ? Math.min(2, every) : every) * 1000);
        },

        stop: function () {
            clearTimeout(this.timer);
            this.timer = null;
        },

        sync: function (scroll) {
            var self = this;

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

            api('ws_sync', {
                channel_id: self.channel.id,
                since_id: self.lastId,
                since_ts: self.sinceTs,
                read: document.hasFocus() ? 1 : 0
            }).then(function (data) {
                self.syncing = false;

                if (data.gone) {
                    toast(t('channel_gone'), 'warning');
                    self.channel = null;
                    self.reloadChannels(0);
                    self.drawEmptyCenter();
                    return;
                }

                var stick = scroll || self.nearEnd();
                var grew = false;

                if (self.tab === 'messages' && data.messages.length) {
                    var empty = self.pane.querySelector('.ws-empty');

                    if (empty) {
                        empty.remove();
                    }

                    data.messages.forEach(function (message) {
                        if (self.findMessage(message.id)) {
                            return;
                        }

                        var previous = self.messages.length ? self.messages[self.messages.length - 1] : null;
                        self.messages.push(message);
                        self.appendMessage(message, previous);
                        grew = true;
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
                BOOT.channels = data.channels;
                BOOT.inbox = data.inbox;

                // The channel list is drawn again only when it changed: every
                // few seconds from scratch it dropped a half-typed search, the
                // list's scroll and the pointer's hover.
                var sideKey = JSON.stringify([data.channels, data.inbox, self.channel ? self.channel.id : 0]);

                if (!self.dragging && (sideKey !== self.sideKey)) {
                    self.sideKey = sideKey;
                    self.drawSide();
                }

                // Down to the newest line only when there is a new line (or
                // after sending): someone reading a little above it stays put.
                if (stick && self.tab === 'messages' && (scroll || grew)) {
                    self.scrollToEnd();
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

    // ── Task rows (the tasks screen, a channel's task tab, drawers) ────

    function taskRows(container, tasks, onChange) {
        clear(container);

        if (!tasks.length) {
            var empty = el('div', 'ws-empty');
            empty.appendChild(icon('bi-check2-square'));
            empty.appendChild(document.createTextNode(t('no_tasks')));
            container.appendChild(empty);
            return;
        }

        tasks.forEach(function (task) {
            var row = el('div', 'ws-list-row' + (task.open ? '' : ' ws-row-done'));
            row.tabIndex = 0;
            row.setAttribute('role', 'button');
            row.appendChild(el('span', 'ws-status-dot ws-s-' + task.status));

            var main = el('div', 'ws-grow');
            var title = el('div', 'ws-row-title');
            title.appendChild(el('span', 'text-body-secondary me-1', task.number));

            if (task.recurring) {
                var repeats = icon('bi-arrow-repeat', 'text-body-secondary me-1');
                repeats.title = t('repeating');
                title.appendChild(repeats);
            }

            title.appendChild(document.createTextNode(task.title));
            main.appendChild(title);

            var sub = el('div', 'ws-row-sub');
            sub.appendChild(el('span', task.overdue ? 'text-danger' : '', task.due_label));
            sub.appendChild(document.createTextNode(' · ' + task.status_label + (task.estimate_label ? ' · ' + task.estimate_label : '')));
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
                row.appendChild(el('span', 'badge text-bg-danger', BOOT.priorities[task.priority]));
            }

            var faces = el('span', 'ws-avatars');

            task.assignees.slice(0, 4).forEach(function (member) {
                var img = avatar(member);
                img.title = member.name;
                faces.appendChild(img);
            });

            row.appendChild(faces);

            if (task.can_edit && task.open) {
                var done = button('btn btn-sm btn-ghost', '', 'bi-check2-circle', t('mark_done'));
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

    function startTasks(root) {
        clear(root);

        var state = { scope: 'mine', status: 'open', search: '', department_id: 0 };
        var bar = el('nav', 'pg-toolbar navigation mb-3');
        var list = el('div', 'card');
        var listBody = el('div', 'card-body p-0');

        list.appendChild(listBody);
        root.appendChild(bar);
        root.appendChild(list);

        var add = button('btn btn-sm btn-primary rounded-pill px-3', t('new_task'), 'bi-plus-circle');
        add.addEventListener('click', function () { taskDrawer.open(0, {}, load); });
        bar.appendChild(add);

        var scopes = el('div', 'btn-group btn-group-sm');
        var scopeList = [['mine', t('scope_mine')], ['created', t('scope_created')], ['department', t('scope_department')]];

        if (BOOT.me.rights.board) {
            scopeList.push(['all', t('scope_all')]);
        }

        scopeList.forEach(function (scope) {
            var item = button('btn btn-ghost' + (state.scope === scope[0] ? ' active' : ''), scope[1]);
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

        function load() {
            api('ws_tasks', state).then(function (data) {
                taskRows(listBody, data.tasks, load);
            }).catch(fail);
        }

        load();

        var query = params();

        if (query.task) {
            taskDrawer.open(parseInt(query.task, 10), null, load);
        } else if (query['new']) {
            taskDrawer.open(0, {}, load);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Planning board
    // ═══════════════════════════════════════════════════════════════════

    function startBoard(root) {
        clear(root);

        var state = { from: CFG.board_from, days: 7, department_id: 0, only_me: false, person_id: parseInt(params().person || 0, 10) || 0 };
        var bar = el('nav', 'pg-toolbar navigation mb-3');
        var wrap = el('div', 'ws-board-wrap');
        var legend = el('div', 'd-flex flex-wrap gap-3 small text-body-secondary mt-2');

        root.appendChild(bar);
        root.appendChild(wrap);
        root.appendChild(legend);

        var addEvent = button('btn btn-sm btn-primary rounded-pill px-3', t('add_plan_item'), 'bi-calendar-plus');
        addEvent.addEventListener('click', function () { eventForm(null, {}, load); });
        bar.appendChild(addEvent);

        var addTask = button('btn btn-sm btn-ghost', t('new_task'), 'bi-check2-square');
        addTask.addEventListener('click', function () { taskDrawer.open(0, {}, load); });
        bar.appendChild(addTask);

        var nav = el('div', 'btn-group btn-group-sm');
        var prev = button('btn btn-ghost', '', 'bi-chevron-left', t('previous'));
        var today = button('btn btn-ghost', t('today'));
        var next = button('btn btn-ghost', '', 'bi-chevron-right', t('next'));

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
            var item = button('btn btn-ghost' + (state.days === option[0] ? ' active' : ''), option[1]);
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

        var meWrap = el('div', 'form-check form-switch mb-0');
        var me = el('input', 'form-check-input');
        me.type = 'checkbox';
        me.id = 'ws-board-only-me';
        var meLabel = el('label', 'form-check-label small', t('only_me'));
        meLabel.htmlFor = me.id;
        me.addEventListener('change', function () {
            state.only_me = me.checked;
            load();
        });
        meWrap.appendChild(me);
        meWrap.appendChild(meLabel);
        bar.appendChild(meWrap);

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var conflicts = button('btn btn-sm btn-outline-secondary', t('conflicts'), 'bi-exclamation-triangle');
        conflicts.addEventListener('click', showConflicts);
        bar.appendChild(conflicts);

        [['ws-swatch-ok', t('legend_ok')], ['ws-swatch-full', t('legend_full')], ['ws-swatch-over', t('legend_over')], ['ws-swatch-away', t('legend_away')]].forEach(function (item) {
            var entry = el('span', 'd-inline-flex align-items-center gap-1');
            entry.appendChild(el('span', 'ws-swatch ' + item[0]));
            entry.appendChild(document.createTextNode(item[1]));
            legend.appendChild(entry);
        });

        var dragged = null;

        function load() {
            api('ws_board', state).then(draw).catch(fail);
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
                        var openEvent = function () {
                            api('ws_event_get', { event_id: event.id }).then(function (result) {
                                eventForm(result.event, {}, load);
                            }).catch(fail);
                        };

                        item.addEventListener('click', openEvent);
                        onContext(item, function () {
                            return [
                                { header: event.title },
                                { icon: 'bi-pencil', label: t('edit'), action: openEvent },
                                { icon: 'bi-trash', label: t('delete'), danger: true, action: function () {
                                    ask(t('delete_item_confirm'), t('delete'), true).then(function (yes) {
                                        if (yes) {
                                            api('ws_event_delete', { event_id: event.id }).then(load).catch(fail);
                                        }
                                    });
                                } }
                            ];
                        });
                        box.appendChild(item);
                    });

                    cell.tasks.forEach(function (task) {
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

        // The newest copy of a repeating task moved to another day: that copy
        // only ('one'), or the copies after it as well ('following'); '' when
        // the move was called off.
        function askSeries() {
            var box = el('div', 'ws-ask-choice');
            var inputs = {};

            [['one', t('move_repeat_one')], ['following', t('move_repeat_following')]].forEach(function (item, index) {
                var line = el('div', 'form-check');
                var input = el('input', 'form-check-input');
                input.type = 'radio';
                input.name = 'ws-series-choice';
                input.value = item[0];
                input.id = 'ws-series-' + item[0];
                input.checked = (index === 0);

                var label = el('label', 'form-check-label', item[1]);
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
            var menu = el('div', 'dropdown-menu show');
            menu.style.position = 'absolute';
            menu.style.zIndex = 30;
            menu.style.top = '1.6rem';
            menu.style.right = '0';

            var task = button('dropdown-item link-body-emphasis', t('new_task'), 'bi-check2-square');
            task.addEventListener('click', function () {
                menu.remove();
                taskDrawer.open(0, { assignees: [member.id], due_date: date }, load);
            });

            var item = button('dropdown-item link-body-emphasis', t('add_plan_item'), 'bi-calendar-plus');
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
            var body = clear(node.querySelector('.offcanvas-body'));
            node.querySelector('.offcanvas-footer').classList.add('d-none');
            body.appendChild(el('p', 'small text-body-secondary', t('conflicts_help')));
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
                    text.appendChild(el('small', 'text-body-secondary', item.tasks.map(function (task) { return task.title; }).join(', ')));
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
            show: { tasks: true, talk: true, events: true }
        };
        var data = null;

        var bar = el('nav', 'pg-toolbar navigation mb-3 ws-cal-toolbar');
        var layout = el('div', 'row g-4');
        var mainCol = el('div', 'col-xl-9');
        var sideCol = el('div', 'col-xl-3');
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
        var prev = button('btn btn-ghost', '', 'bi-chevron-left', t('previous_month'));
        var now = button('btn btn-ghost', t('this_month'));
        var next = button('btn btn-ghost', '', 'bi-chevron-right', t('next_month'));

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

        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var shows = el('div', 'btn-group btn-group-sm');
        shows.setAttribute('role', 'group');

        [['tasks', t('tasks'), 'bi-check2-square'], ['talk', t('conversations'), 'bi-chat-dots'], ['events', t('plan_items'), 'bi-calendar-event']].forEach(function (option) {
            var toggle = button('btn btn-ghost active', option[1], option[2]);
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

        function taskChip(task) {
            var chip = el('button', 'ws-cal-task' + (task.upcoming ? ' ws-cal-upcoming' : '') + (task.waits_for ? ' ws-cal-waits' : '') + (task.overdue ? ' ws-cal-late' : '') + (task.open ? '' : ' ws-cal-closed') + ((task.priority === 'urgent' || task.priority === 'high') ? ' ws-cal-hot' : ''));
            chip.type = 'button';
            chip.appendChild(task.upcoming ? icon('bi-arrow-repeat') : el('span', 'ws-status-dot ws-s-' + task.status));
            chip.appendChild(el('span', 'ws-cal-task-title', task.title));

            var faces = el('span', 'ws-avatars ws-avatars-sm');

            (task.people || []).slice(0, 3).forEach(function (id) {
                var member = person(id);

                if (member) {
                    faces.appendChild(avatar(member));
                }
            });

            chip.appendChild(faces);
            chip.title = task.upcoming
                ? comingText(task) + ' · ' + task.title + (task.people && task.people.length ? ' · ' + peopleNames(task.people) : '') + '\n' + t('upcoming_hint')
                : task.number + ' · ' + task.title + (task.people && task.people.length ? ' · ' + peopleNames(task.people) : '') + ' · ' + BOOT.statuses[task.status];
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
                week.forEach(function (day) {
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

                    if (state.show.events) {
                        day.events.forEach(function (event) {
                            if (shown >= limit) {
                                hidden++;
                                return;
                            }

                            var item = el('div', 'ws-cal-event ws-kind-' + event.kind, (event.time ? event.time + ' ' : '') + event.title);
                            item.title = event.title + (event.people && event.people.length ? ' · ' + peopleNames(event.people) : '');
                            cell.appendChild(item);
                            shown++;
                        });
                    }

                    if (state.show.tasks) {
                        day.tasks.forEach(function (task) {
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
            var body = clear(node.querySelector('.offcanvas-body'));
            var footer = clear(node.querySelector('.offcanvas-footer'));

            node.querySelector('.offcanvas-title').textContent = day.label;
            footer.classList.remove('d-none');

            var addEvent = button('btn btn-sm btn-ghost', t('add_plan_item'), 'bi-calendar-plus');
            addEvent.addEventListener('click', function () {
                hideOffcanvas(node);
                eventForm(null, { start: day.date, people: forWhom() }, load);
            });

            var addTask = button('btn btn-sm btn-primary rounded-pill px-3', t('new_task'), 'bi-check2-square');
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

            var tasks = section(t('tasks') + ' · ' + day.tasks.length, 'bi-check2-square');

            if (!day.tasks.length) {
                tasks.appendChild(el('div', 'small text-body-secondary', t('no_tasks')));
            }

            day.tasks.forEach(function (task) {
                var row = el('div', 'ws-list-row' + (task.open ? '' : ' ws-row-done') + (task.upcoming ? ' ws-row-upcoming' : ''));
                row.appendChild(task.upcoming ? icon('bi-arrow-repeat', 'text-body-secondary') : el('span', 'ws-status-dot ws-s-' + task.status));

                var main = el('div', 'ws-grow');
                var line = el('div', 'ws-row-title');

                if (!task.upcoming) {
                    line.appendChild(el('span', 'text-body-secondary me-1', task.number));
                }

                line.appendChild(document.createTextNode(task.title));
                main.appendChild(line);
                main.appendChild(el('div', 'ws-row-sub' + (task.overdue ? ' text-danger' : ''), (task.upcoming ? comingText(task) : BOOT.statuses[task.status]) + (task.people.length ? ' · ' + peopleNames(task.people) : '')));
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
                    var link = el('a', 'ws-record-item d-flex align-items-center gap-2');
                    link.href = CFG.urls.workspace + '?channel=' + item.id;
                    link.appendChild(icon(item['private'] ? 'bi-lock' : 'bi-hash'));
                    link.appendChild(el('span', 'ws-grow', item.name));
                    link.appendChild(el('span', 'badge rounded-pill text-bg-secondary', t('messages_count', item.count)));
                    talk.appendChild(link);
                });
            }

            showOffcanvas(node);
        }

        function drawSide() {
            var box = clear(side);
            var totals = data.totals;

            // The month in figures.
            var month = el('div', 'card mb-3');
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
                monthBody.appendChild(el('div', 'small text-body-secondary mt-2', t('busiest_day', data.busiest.label, data.busiest.tasks, data.busiest.messages)));
            }

            if (totals.unassigned) {
                monthBody.appendChild(el('div', 'small text-warning-emphasis mt-1', t('unassigned_count', totals.unassigned)));
            }

            if (totals.upcoming) {
                monthBody.appendChild(el('div', 'small text-body-secondary mt-1', t('upcoming_count', totals.upcoming)));
            }

            month.appendChild(monthBody);
            box.appendChild(month);

            // Who carries the most, who the least.
            if (data.most || data.least) {
                var extremes = el('div', 'card mb-3');
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
            var people = el('div', 'card mb-3');
            var peopleBody = el('div', 'card-body');
            var peopleHead = el('div', 'd-flex align-items-center mb-2');
            peopleHead.appendChild(el('h3', 'ws-side-card-title ws-grow mb-0', t('people_this_month')));

            if (state.person_id) {
                var all = button('btn btn-sm btn-ghost py-0', t('everyone'), 'bi-x');
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
                peopleBody.appendChild(el('div', 'small text-body-secondary', t('board_empty')));
            }

            data.people.forEach(function (item) {
                var row = el('button', 'ws-person-row' + (state.person_id === item.person.id ? ' active' : ''));
                row.type = 'button';
                row.appendChild(avatar(item.person));

                var text = el('div', 'ws-grow');
                var line = el('div', 'd-flex gap-2');
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
                text.appendChild(el('small', 'text-body-secondary', t('person_figures', item.done, item.open, item.overdue, item.minutes_label)));
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
                    var link = el('a', 'ws-record-item d-flex align-items-center gap-2');
                    link.href = CFG.urls.workspace + '?channel=' + item.id;
                    link.appendChild(icon(item['private'] ? 'bi-lock' : 'bi-hash'));
                    link.appendChild(el('span', 'ws-grow text-truncate', item.name));
                    link.appendChild(el('span', 'badge rounded-pill text-bg-secondary', String(item.count)));
                    channelsBody.appendChild(link);
                });

                channels.appendChild(channelsBody);
                box.appendChild(channels);
            }
        }

        load();
    }

    // The plan item form: a meeting, a visit, leave, a holiday.
    function eventForm(event, defaults, onDone) {
        var node = offcanvas('ws-event-form', '');
        var body = clear(node.querySelector('.offcanvas-body'));
        var footer = clear(node.querySelector('.offcanvas-footer'));
        var rights = BOOT.me.rights;

        defaults = defaults || {};
        footer.classList.remove('d-none');
        node.querySelector('.offcanvas-title').textContent = event ? t('edit_plan_item') : t('add_plan_item');

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

        var grid = el('div', 'row g-2');

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

        var allDayWrap = el('div', 'form-check form-switch mb-2');
        var allDay = el('input', 'form-check-input');
        allDay.type = 'checkbox';
        allDay.id = nextId('ws-ev-allday-');
        allDay.checked = event ? (String(event.all_day) === '1') : false;
        var allDayLabel = el('label', 'form-check-label', t('all_day'));
        allDayLabel.htmlFor = allDay.id;
        allDayWrap.appendChild(allDay);
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

        if (event && event.can_edit) {
            var remove = button('btn btn-sm btn-outline-warning me-auto', t('delete'), 'bi-trash');
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

        var cancel = button('btn btn-sm btn-ghost', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'offcanvas');
        footer.appendChild(cancel);

        if (!event || event.can_edit) {
            var save = button('btn btn-sm btn-primary rounded-pill px-3', t('save'), 'bi-check2');
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
                    note: note.value
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

    function openRecord(type, id, label) {
        var node = offcanvas('ws-record', t('workspace'));
        var body = clear(node.querySelector('.offcanvas-body'));
        var footer = clear(node.querySelector('.offcanvas-footer'));
        var token = '<#' + type + ':' + id + '>';
        var ref = { type: type, id: id, label: label || '', icon: (BOOT.ref_types[type] || {}).icon || 'bi-dot', token: token };

        node.classList.add('ws-record-drawer');
        node.querySelector('.offcanvas-title').textContent = t('workspace') + (label ? ' · ' + label : '');
        footer.classList.remove('d-none');
        body.appendChild(el('div', 'small text-body-secondary', t('loading')));
        showOffcanvas(node);

        var newTask = button('btn btn-sm btn-primary rounded-pill px-3', t('new_task'), 'bi-check2-square');
        newTask.addEventListener('click', function () {
            hideOffcanvas(node);
            taskDrawer.open(0, { refs: [ref], title: label || '' }, function () { openRecord(type, id, label); });
        });
        footer.appendChild(newTask);

        if (type === 'contact') {
            var newChannel = button('btn btn-sm btn-outline-secondary', t('customer_channel'), 'bi-hash');
            newChannel.addEventListener('click', function () {
                hideOffcanvas(node);
                channelForm(null, { name: label || '', contact_id: id, contact_label: label || '', kind: 'public' }, function (channelId) {
                    window.location.href = CFG.urls.workspace + '?channel=' + channelId;
                });
            });
            footer.appendChild(newChannel);
        }

        api('ws_record_refs', { type: type, id: id }).then(function (data) {
            clear(body);

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
                taskRows(taskList, data.tasks, function () { openRecord(type, id, label); });
            } else {
                taskList.appendChild(el('div', 'small text-body-secondary', t('no_tasks')));
            }

            var talk = el('div', 'ws-record-section');
            talk.appendChild(el('div', 'ws-record-title', t('conversations')));

            if (!data.messages.length) {
                talk.appendChild(el('div', 'small text-body-secondary', t('not_discussed')));
            }

            data.messages.forEach(function (message) {
                var link = el('a', 'ws-record-item');
                link.href = message.url;
                var head = el('div', 'd-flex gap-2 align-items-center');
                var where = el('b');
                where.appendChild(icon(message['private'] ? 'bi-lock' : 'bi-hash', 'me-1'));
                where.appendChild(document.createTextNode(message.channel));
                head.appendChild(where);
                head.appendChild(el('span', 'text-body-secondary', message.sender + ' · ' + message.time));
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
                var note = el('textarea', 'form-control form-control-sm mt-2');
                note.rows = 2;
                note.placeholder = t('share_placeholder');
                share.appendChild(note);
                var send = button('btn btn-sm btn-outline-secondary mt-2', t('share'), 'bi-send');
                send.addEventListener('click', function () {
                    api('ws_send', { channel_id: parseInt(target.value, 10), body: (note.value.trim() ? note.value.trim() + ' ' : '') + token }).then(function () {
                        toast(t('shared'), 'success');
                        openRecord(type, id, label);
                    }).catch(fail);
                });
                share.appendChild(send);
                body.appendChild(share);
            }
        }).catch(fail);
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

        var bar = el('nav', 'pg-toolbar navigation mb-3');
        var layout = el('div', 'row g-4');
        var mainCol = el('div', 'col-xl-9');
        var sideCol = el('div', 'col-xl-3 d-none d-xl-block');
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

        var notesToggle = button('btn btn-sm btn-ghost' + (state.notes ? ' active' : ''), t('tl_notes'), 'bi-sticky');
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

        var moreButton = button('btn btn-sm btn-outline-secondary rounded-pill px-3', t('tl_more'), 'bi-arrow-down');
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
                side.appendChild(el('div', 'card-body small text-body-secondary', t('tl_empty')));
                return;
            }

            var group = el('div', 'list-group list-group-flush');

            counted.forEach(function (channel) {
                var row = button('list-group-item list-group-item-action d-flex align-items-center gap-2' + (state.channel_id === channel.id ? ' active' : ''));
                row.appendChild(icon(channel.private ? 'bi-lock' : 'bi-hash'));
                row.appendChild(el('span', 'text-truncate flex-grow-1', channel.name));

                if (channel.archived) {
                    var archivedIcon = icon('bi-archive');
                    archivedIcon.title = t('tl_archived');
                    row.appendChild(archivedIcon);
                }

                row.appendChild(el('span', 'badge rounded-pill text-bg-light', channel.count));
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
            var box = el('details', 'ws-tl-hidden alert alert-secondary py-2 px-3');
            var line = el('summary');
            line.appendChild(icon('bi-shield-lock', 'me-1'));
            line.appendChild(document.createTextNode(t('tl_hidden', hidden.length, total)));
            box.appendChild(line);

            var rows = el('ul', 'list-unstyled mb-2 mt-2');

            hidden.forEach(function (channel) {
                var row = el('li', 'd-flex flex-wrap align-items-center gap-2 py-1');
                var name = el('span', 'fw-semibold');
                name.appendChild(icon('bi-lock', 'me-1'));
                name.appendChild(document.createTextNode(channel.name));

                if (channel.archived) {
                    var archivedIcon = icon('bi-archive', 'ms-1');
                    archivedIcon.title = t('tl_archived');
                    name.appendChild(archivedIcon);
                }

                row.appendChild(name);
                row.appendChild(el('span', 'text-body-secondary', t('tl_hidden_row', channel.count, channel.owner)));

                var open = button('btn btn-sm btn-outline-warning ms-auto', t('open_for_audit'), 'bi-eye');
                open.addEventListener('click', function () {
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
            box.appendChild(el('div', 'small text-body-secondary', t('tl_hidden_help')));
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
                proposal.appendChild(document.createTextNode(t('tl_claude') + ' · ' + item.claude.type_label));
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

            var jump = el('a', 'btn btn-sm btn-ghost ms-auto');
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
                var reset = button('btn btn-sm btn-outline-secondary rounded-pill px-3 mt-2', t('tl_clear'), 'bi-x-circle');
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

    // A note shared in a channel, read where it was shared: as it is now.
    function openNoteView(noteId) {
        api('ws_note_get', { note_id: noteId }).then(function (data) {
            var note = data.note;
            var node = el('div', 'modal fade ws-nb-view');
            node.tabIndex = -1;
            node.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-md-down"><div class="modal-content">'
                + '<div class="modal-header"><h5 class="modal-title text-truncate"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
                + '<div class="modal-body"></div><div class="modal-footer justify-content-start small text-body-secondary"></div></div></div>';
            node.querySelector('.modal-title').appendChild(icon('bi-journal-text', 'me-2'));
            node.querySelector('.modal-title').appendChild(document.createTextNode(note.name));
            node.querySelector('.btn-close').setAttribute('aria-label', t('close'));

            var body = node.querySelector('.modal-body');
            var html = el('div', 'ws-msg-body ws-nb-read');
            setHtml(html, note.html);
            chipMenus(html);
            body.appendChild(html);

            var footer = node.querySelector('.modal-footer');
            footer.appendChild(noteFaces(note, 6));

            if (note.updated_by) {
                footer.appendChild(el('span', '', t('notes_last_change', note.updated_by.name, note.updated)));
            }

            if (note.access === 'owner' || note.access === 'edit') {
                var edit = el('a', 'btn btn-sm btn-outline-secondary rounded-pill ms-auto', t('notes_open'));
                edit.href = CFG.urls.notes + '?note=' + note.id;
                footer.appendChild(edit);
            }

            document.body.appendChild(node);
            node.addEventListener('hidden.bs.modal', function () { node.remove(); });
            bootstrap.Modal.getOrCreateInstance(node).show();
        }).catch(fail);
    }

    // Everybody's own notes and the ones shared with them: the lists on the
    // left, the note open on the right, written in the workspace's writing
    // box. A note saves itself a moment after the last keystroke.
    function startNotes(root) {
        clear(root);

        if (!CFG.notes) {
            root.appendChild(el('div', 'alert alert-warning', t('notes_not_ready')));
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

        var bar = el('nav', 'pg-toolbar navigation mb-3');
        var layout = el('div', 'row g-4 ws-nb');
        var listCol = el('div', 'col-lg-4 col-xl-3 ws-nb-list-col');
        var paneCol = el('div', 'col-lg-8 col-xl-9 ws-nb-pane-col');
        var list = el('div', 'ws-nb-list');
        var pane = el('div', 'ws-nb-pane');

        listCol.appendChild(list);
        paneCol.appendChild(pane);
        layout.appendChild(listCol);
        layout.appendChild(paneCol);
        root.appendChild(bar);
        root.appendChild(layout);

        // ── toolbar ──

        var newNote = button('btn btn-sm btn-primary rounded-pill px-3', t('notes_new'), 'bi-plus-lg');
        newNote.addEventListener('click', function () { startNew(); });
        bar.appendChild(newNote);
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
                var item = el('div', 'ws-nb-item' + (current && current.id === note.id ? ' active' : ''));
                item.tabIndex = 0;
                item.setAttribute('role', 'button');

                var top = el('div', 'ws-nb-item-head');
                top.appendChild(icon(note.access === 'owner' ? (note.source ? 'bi-chat-square-quote' : 'bi-journal-text') : 'bi-people'));
                top.appendChild(el('span', 'ws-nb-item-name', note.name));

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
                intro.appendChild(icon('bi-journal'));
                intro.appendChild(el('div', 'small text-body-secondary', t('notes_intro')));
                list.appendChild(intro);
            }

            list.appendChild(drawSection(t('notes_mine'), lists.mine, search ? t('notes_empty_search') : t('notes_empty')));
            list.appendChild(drawSection(t('notes_shared'), lists.shared, search ? t('notes_empty_search') : t('notes_empty_shared')));
        }

        function loadList() {
            return api('ws_notes', { search: search }).then(function (data) {
                lists.mine = data.mine || [];
                lists.shared = data.shared || [];
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

                ['id', 'name', 'updated', 'updated_at', 'updated_by', 'access', 'owner', 'with', 'in', 'pinned', 'excerpt'].forEach(function (key) {
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

            box = el('div', 'alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 ws-nb-conflict');
            box.appendChild(el('span', 'me-auto', message || t('notes_conflict')));

            var load = button('btn btn-sm btn-outline-secondary rounded-pill', t('notes_conflict_load'));
            load.addEventListener('click', function () {
                dirty = false;
                box.remove();
                openNote(current.id, true);
            });
            box.appendChild(load);

            var keep = button('btn btn-sm btn-warning rounded-pill', t('notes_conflict_keep'));
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

        // Somebody else's change comes in while the note is open and nothing
        // was typed here since.
        function poll() {
            clearTimeout(pollTimer);
            pollTimer = setTimeout(poll, claudeWaiting ? 5000 : 15000);

            if (!current || !current.id || dirty || saving || document.hidden) {
                return;
            }

            var note = current;

            api('ws_note_get', { note_id: note.id }).then(function (data) {
                if (note !== current || dirty || saving) {
                    return;
                }

                var fresh = data.note;

                if (fresh.updated_at > note.updated_at) {
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

        // The lines that ask Claude are sent when one of them is finished with
        // Enter; the same line is never asked twice.
        function askClaude() {
            if (!current || !field || !BOOT.claude || !BOOT.claude.token) {
                return;
            }

            var lines = field.value().split('\n').filter(function (line) {
                return line.indexOf(BOOT.claude.token) !== -1;
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

            if (line) {
                line.classList.toggle('d-none', !claudeWaiting);
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

            var back = button('btn btn-sm btn-ghost d-lg-none', '', 'bi-arrow-left', t('notes_back'));
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
            title.value = current.title || '';
            title.readOnly = !editable();
            title.setAttribute('aria-label', t('notes_title'));
            title.addEventListener('input', changed);
            current.titleInput = title;
            headNode.appendChild(title);

            status = el('span', 'ws-nb-status');
            headNode.appendChild(status);
            setStatus(dirty ? 'unsaved' : (current.id && editable() ? 'saved' : ''));

            if (current.access === 'owner' || !current.id) {
                var share = button('btn btn-sm btn-ghost', t('notes_share'), 'bi-send');
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

            var more = button('btn btn-sm btn-ghost', '', 'bi-three-dots', t('more'));
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

        function toolbar() {
            var tools = el('div', 'ws-nb-tools');
            var rich = field.rich;
            var group = function () {
                var box = el('div', 'ws-nb-tool-group');
                tools.appendChild(box);
                return box;
            };
            var tool = function (box, iconName, label, action) {
                var node = button('btn btn-sm btn-ghost', '', iconName, label);
                node.addEventListener('mousedown', function (event) { event.preventDefault(); });
                node.addEventListener('click', action);
                box.appendChild(node);
                return node;
            };
            var format = function (command) {
                return function () {
                    rich.restore();
                    document.execCommand(command, false, null);
                    rich.node.dispatchEvent(new Event('input', { bubbles: true }));
                };
            };
            var block = function (kind, open) {
                return function () {
                    open(function (markup) {
                        if (markup) {
                            rich.insertBlock(kind, markup);
                        }
                    });
                };
            };

            var marks = group();
            tool(marks, 'bi-type-bold', t('notes_bold'), format('bold'));
            tool(marks, 'bi-type-italic', t('notes_italic'), format('italic'));
            tool(marks, 'bi-type-strikethrough', t('notes_strike'), format('strikeThrough'));

            var blocks = group();
            tool(blocks, 'bi-ui-checks', t('notes_checklist'), block('checklist', function (done) { window.PGWsEditor.openChecklist('', done); }));
            tool(blocks, 'bi-table', t('notes_table'), block('table', function (done) { window.PGWsEditor.openTable({ columns: 3, rows: 3 }, done, { insertLabel: t('insert_to_note') }); }));
            tool(blocks, 'bi-calculator', t('notes_calc'), block('code', function (done) { window.PGWsEditor.openCalc('', done, { insertLabel: t('insert_to_note') }); }));
            tool(blocks, 'bi-code-slash', t('notes_code'), block('code', function (done) { window.PGWsEditor.openCode('', done); }));

            var tags = group();
            tool(tags, 'bi-hash', t('notes_tag'), function () { field.insertTrigger('#'); });

            if (BOOT.claude) {
                tool(tags, 'bi-stars', t('notes_claude'), function () { field.insertTrigger('@'); });
            }

            var extras = group();
            var picker = el('input');
            picker.type = 'file';
            picker.multiple = true;
            picker.className = 'd-none';
            picker.addEventListener('change', function () {
                attach(picker.files);
                picker.value = '';
            });
            extras.appendChild(picker);
            tool(extras, 'bi-paperclip', t('notes_attach'), function () { picker.click(); });

            var emoji = tool(extras, 'bi-emoji-smile', t('notes_emoji'), function (event) {
                var box = emoji.getBoundingClientRect();

                event.stopPropagation();
                emojiPicker(box.left, box.bottom + 4, function (picked) { rich.insertText(picked); });
            });

            return tools;
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
                body.appendChild(el('div', 'alert alert-light border small py-2', t('notes_read_only')));

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
                onKeydown: function (event) {
                    if (event.key === 'Enter' && !event.shiftKey && BOOT.claude && BOOT.claude.token) {
                        setTimeout(function () {
                            if (field && field.value().indexOf(BOOT.claude.token) !== -1) {
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
            wrap.appendChild(field.rich.node);
            body.appendChild(wrap);

            var claudeLine = el('div', 'ws-nb-claude small text-body-secondary d-none');
            claudeLine.appendChild(el('span', 'spinner-border spinner-border-sm me-2'));
            claudeLine.appendChild(document.createTextNode(t('notes_claude_waiting')));
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
                return api('ws_note_get', { note_id: noteId });
            }).then(function (data) {
                current = data.note;
                dirty = false;
                remember(current.id);
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
            api('ws_note_save', { note_id: note.id, pinned: note.pinned ? 0 : 1 }).then(function () {
                if (current && current.id === note.id) {
                    current.pinned = !note.pinned;
                }

                loadList();
            }).catch(fail);
        }

        function sharesList(note) {
            var box = el('div', 'ws-nb-shares');

            box.appendChild(el('div', 'form-label mb-1', t('notes_shares')));

            if (!(note['with'] || []).length && !(note['in'] || []).length) {
                box.appendChild(el('div', 'small text-body-secondary', t('notes_only_you')));
            }

            (note['with'] || []).concat((note['in'] || []).map(function (channel) { return { channel: channel, share_id: channel.share_id }; })).forEach(function (share) {
                var row = el('div', 'ws-nb-share-row');

                if (share.person) {
                    row.appendChild(avatar(share.person));
                    row.appendChild(el('span', 'ws-grow text-truncate', share.person.name));
                    row.appendChild(el('span', 'small text-body-secondary', share.can_edit ? t('notes_can_edit') : t('notes_can_view')));
                } else {
                    row.appendChild(icon('bi-hash'));
                    row.appendChild(el('span', 'ws-grow text-truncate', share.channel.name || '?'));
                    row.appendChild(el('span', 'small text-body-secondary', t('notes_can_view')));
                }

                if (share.share_id) {
                    var remove = button('btn btn-sm btn-ghost ws-tool-danger', '', 'bi-x-lg', t('notes_unshare'));
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

            right.classList.add('w-auto', 'mt-2');
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
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Start
    // ═══════════════════════════════════════════════════════════════════

    function start() {
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
            }

            // What runs where no scheduler does (repeating tasks, requests to
            // Claude that waited), once the screen is up rather than before.
            window.setTimeout(function () {
                api('ws_tick').catch(function () {});
            }, 1500);

            // The inbox links of the rail and its drawer, drawn with the page.
            document.addEventListener('click', function (event) {
                var inboxLink = event.target.closest('[data-ws-inbox]');

                if (!inboxLink) {
                    return;
                }

                event.preventDefault();

                var drawer = document.getElementById('ws-nav-drawer');

                if (drawer && window.bootstrap && drawer.classList.contains('show')) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(drawer).hide();
                }

                openInbox(function () {});
            });

            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-ws-record]');

                if (!trigger) {
                    return;
                }

                event.preventDefault();

                var parts = trigger.getAttribute('data-ws-record').split(':');
                openRecord(parts[0], parseInt(parts[1], 10), trigger.getAttribute('data-ws-label') || '');
            });
        }).catch(function (error) {
            var root = document.getElementById('ws-root');

            if (root) {
                clear(root);
                root.appendChild(el('div', 'alert alert-warning', error.message));
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
