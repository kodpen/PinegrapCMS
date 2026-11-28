/**
 * Pinegrap - Enterprise Website Platform
 *
 * The document head (window.pgDocHead): the one row a note, a Data table,
 * the Artifact Canvas, a file window and the view windows draw at their top.
 * Three groups left to right: identity (Back, the kind's tile, the name,
 * renamed in place, the meta, the save state), history and slot (Undo, Redo,
 * the window's own controls), acts (faces, Share, Download, the main
 * button, the "more" menu, grow, Close). Under 640 px it keeps Back, the
 * name, the state's dot, the main button and the menu; Undo, Redo, the slot,
 * Share and Download become the menu's first rows.
 *
 * The head draws and orders; each window keeps its body and its saving.
 * pgDocHead.build(options) returns the element and its setters. The words
 * come from window.pgDocHeadWords (pg_doc_head_includes()), keyed by their
 * English; a missing word shows its key. No Bootstrap script is needed: the
 * menu is the head's own, its look in backend.src.css ("Look A: document
 * head").
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (window) {
    'use strict';

    var DH = {};

    // The menu's groups, in their order; a line stands between two groups.
    // 'own' is where the kind's own rows go.
    DH.MENU_GROUPS = [['open', 'fullscreen'], ['copyLink', 'send'], ['download', 'export', 'print'], ['versions'], ['own'], ['help', 'delete']];

    // The icon and the word of each known row.
    DH.MENU_ROWS = {
        open: ['bi-box-arrow-up-right', 'Open in a new tab'],
        fullscreen: ['bi-arrows-fullscreen', 'Full screen'],
        copyLink: ['bi-link-45deg', 'Copy link'],
        send: ['bi-send', 'Send to a channel'],
        download: ['bi-download', 'Download'],
        'export': ['bi-file-earmark-arrow-down', 'Export…'],
        print: ['bi-printer', 'Print'],
        versions: ['bi-clock-history', 'Versions'],
        help: ['bi-question-circle', 'Help'],
        'delete': ['bi-trash3', 'Delete']
    };

    // The states a head shows and their words.
    DH.STATES = {
        saving: 'Saving…',
        saved: 'Saved',
        unsaved: 'Unsaved changes',
        failed: 'Could not save'
    };

    // Below this width (the window's) the head takes the phone's shape.
    DH.PHONE_QUERY = '(max-width: 639.98px)';

    /**
     * A word in the page's language: the caller's, then the page's, then
     * the English key itself. {var:1} is replaced with `value`.
     */
    DH.word = function (key, words, value) {
        var own = words && Object.prototype.hasOwnProperty.call(words, key) ? words[key] : null;
        var page = window.pgDocHeadWords && Object.prototype.hasOwnProperty.call(window.pgDocHeadWords, key) ? window.pgDocHeadWords[key] : null;
        var text = String(own !== null ? own : (page !== null ? page : key));

        return value === undefined ? text : text.split('{var:1}').join(String(value));
    };

    /**
     * A typed name made clean: spaces trimmed and runs of white space made
     * one. An empty name keeps the one before it.
     */
    DH.cleanName = function (typed, before) {
        var name = String(typed === null || typed === undefined ? '' : typed).replace(/\s+/g, ' ').replace(/^ | $/g, '');

        return name === '' ? String(before || '') : name;
    };

    /**
     * How long ago a save was, in words: "Just now" under a minute, minutes
     * under an hour, then the clock time (hh:mm, the browser's own zone).
     */
    DH.ago = function (time, now, words) {
        var seconds = Math.max(0, Math.round((now - time) / 1000));

        if (seconds < 60) {
            return DH.word('Just now', words);
        }

        if (seconds < 3600) {
            return DH.word('{var:1} min ago', words, Math.floor(seconds / 60));
        }

        var date = new Date(time);

        return ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2);
    };

    /**
     * What the state shows: its class, its words and its tooltip. Null for
     * none (a view-only window, or no state given). `info` holds `time`
     * (ms, when saved), `ago` (true: the words add "· 2 min ago") and
     * `reason` (why saving failed).
     */
    DH.stateView = function (state, info, now, words) {
        info = info || {};

        if (!state || !Object.prototype.hasOwnProperty.call(DH.STATES, state)) {
            return null;
        }

        var view = { state: state, text: DH.word(DH.STATES[state], words), title: '' };

        if (state === 'saved' && info.time) {
            view.title = new Date(info.time).toLocaleString();

            if (info.ago) {
                view.text += ' · ' + DH.ago(info.time, now, words);
            }
        } else if (state === 'failed' && info.reason) {
            view.title = String(info.reason);
        } else if (state !== 'saved') {
            view.title = view.text;
        }

        return view;
    };

    /**
     * One menu row from what the caller gave for a key: a function (run on
     * click) or an object {onClick, label, icon, count, danger}.
     */
    function menuRow(key, given, words) {
        var known = DH.MENU_ROWS[key] || ['bi-dot', key];
        var spec = typeof given === 'function' ? { onClick: given } : (given || {});

        return {
            key: key,
            icon: spec.icon || known[0],
            label: spec.label ? String(spec.label) : DH.word(known[1], words),
            count: spec.count === undefined || spec.count === null ? null : spec.count,
            danger: key === 'delete' || !!spec.danger,
            run: typeof spec.onClick === 'function' ? spec.onClick : null
        };
    }

    /**
     * The menu's rows in their groups and order, a group with no row left
     * out. `menu` names each known row (DH.MENU_ROWS) and holds the kind's
     * own rows in `own` (a list of {icon, label, onClick}). `first` is a
     * group put before all (the phone's Undo, Redo, Share, Download).
     */
    DH.menuGroups = function (menu, first, words) {
        var groups = [];

        menu = menu || {};

        if (first && first.length) {
            groups.push(first.slice());
        }

        DH.MENU_GROUPS.forEach(function (keys) {
            var rows = [];

            keys.forEach(function (key) {
                if (key === 'own') {
                    (menu.own || []).forEach(function (spec, i) {
                        if (spec) {
                            rows.push(menuRow('own' + i, spec, words));
                        }
                    });
                } else if (menu[key]) {
                    rows.push(menuRow(key, menu[key], words));
                }
            });

            if (rows.length) {
                groups.push(rows);
            }
        });

        return groups;
    };

    /**
     * The one row of a menu that holds only one, which stands as an icon
     * button instead of the menu; null when the menu holds more or none.
     */
    DH.singleRow = function (groups) {
        return groups.length === 1 && groups[0].length === 1 ? groups[0][0] : null;
    };

    /**
     * The main button's look: 'fill' (Save, New row), or 'quiet' (a view
     * window's edit door). Unknown styles are 'fill'.
     */
    DH.mainClass = function (main) {
        var style = main && main.style === 'quiet' ? 'quiet' : 'fill';

        return 'pg-button pg-button-' + style + ' pg-button-sm pg-doc-head-main';
    };

    // ---------------------------------------------------------------- DOM

    function el(tag, className, text) {
        var node = window.document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name) {
        var node = el('i', 'bi ' + name);

        node.setAttribute('aria-hidden', 'true');

        return node;
    }

    function iconButton(name, label, className) {
        var button = el('button', 'pg-icon-button' + (className ? ' ' + className : ''));

        button.type = 'button';
        button.title = label;
        button.setAttribute('aria-label', label);
        button.appendChild(icon(name));

        return button;
    }

    function textButton(className, iconName, label) {
        var button = el('button', className);

        button.type = 'button';

        if (iconName) {
            button.appendChild(icon(iconName));
        }

        button.appendChild(el('span', 'pg-btn-label', label));

        return button;
    }

    function separator() {
        var node = el('span', 'pg-doc-head-sep');

        node.setAttribute('aria-hidden', 'true');

        return node;
    }

    function call(fn, arg) {
        if (typeof fn === 'function') {
            return fn(arg);
        }

        return undefined;
    }

    function isPhone() {
        return !!(window.matchMedia && window.matchMedia(DH.PHONE_QUERY).matches);
    }

    /**
     * Builds a head. Options (each part is drawn only when given):
     *   kind     {icon, tone, label}    the tile; tone is a .pg-tone-* name
     *   back     {onClick, label}       Back, at the left
     *   name     string; rename: function (name) saves a new name (a
     *            returned promise that rejects puts the old name back);
     *            without it the name is text only
     *   meta     string                 size, row count, "W × H px"
     *   state    'saving'|'saved'|'unsaved'|'failed'; stateInfo {time, ago, reason}
     *   undo     {onUndo, onRedo, canUndo, canRedo}
     *   slot     an element: the kind's own controls (at most five)
     *   faces    [{initials, name, picture}]
     *   share    {onClick, label}
     *   download {onClick}              an icon button (view windows)
     *   main     {label, icon, style 'fill'|'quiet', gate (dim until a
     *            change), onClick} or {node} (the canvas's split button)
     *   menu     {open, fullscreen, copyLink, send, download, export, print,
     *            versions, own: [...], help, delete}: see DH.menuGroups()
     *   grow     {onClick, label, icon}
     *   close    {onClick, label}
     *   words    the caller's words over the page's
     * Returns {root, setName, setMeta, setState, setUndo, setMain, isDirty,
     * closeMenu, destroy}.
     */
    DH.build = function (options) {
        var o = options || {};
        var words = o.words || null;
        var root = el('div', 'pg-doc-head');
        var ident = el('div', 'pg-doc-head-ident');
        var history = el('div', 'pg-doc-head-history');
        var acts = el('div', 'pg-doc-head-acts');
        var api = { root: root };
        var current = { name: DH.cleanName(o.name, ''), state: null, info: null, dirty: false };
        var timer = null;
        var nameNode, nameInput, stateNode, stateText, metaNode, undoButton, redoButton, mainButton, moreWrap, menuNode, moreButton;

        root.setAttribute('role', 'group');

        // Identity: Back, the tile, the name, the meta, the state.
        if (o.back || o.close) {
            var back = o.back || o.close;
            var backButton = iconButton('bi-arrow-left', back === o.back && o.back.label ? o.back.label : DH.word('Back', words), 'pg-doc-head-back' + (o.back ? '' : ' pg-doc-head-phone-only'));

            backButton.addEventListener('click', function () {
                call(back.onClick);
            });
            ident.appendChild(backButton);
        }

        if (o.kind) {
            var tile = el('span', 'pg-icon-tile pg-doc-head-tile' + (o.kind.tone ? ' pg-tone-' + o.kind.tone : ''));

            tile.appendChild(icon(o.kind.icon || 'bi-file-earmark'));

            if (o.kind.label) {
                tile.title = o.kind.label;
                tile.setAttribute('role', 'img');
                tile.setAttribute('aria-label', o.kind.label);
            } else {
                tile.setAttribute('aria-hidden', 'true');
            }

            ident.appendChild(tile);
        }

        if (typeof o.rename === 'function') {
            nameNode = el('button', 'pg-doc-head-name is-renamable');
            nameNode.type = 'button';
        } else {
            nameNode = el('span', 'pg-doc-head-name');
        }

        ident.appendChild(nameNode);

        metaNode = el('span', 'pg-doc-head-meta');
        ident.appendChild(metaNode);

        stateNode = el('span', 'pg-doc-head-state');
        stateNode.setAttribute('role', 'status');
        stateNode.appendChild(el('i', 'pg-doc-head-dot'));
        stateText = el('span', 'pg-doc-head-state-text');
        stateNode.appendChild(stateText);
        stateNode.hidden = true;
        ident.appendChild(stateNode);

        root.appendChild(ident);

        // History and slot.
        if (o.undo) {
            undoButton = iconButton('bi-arrow-counterclockwise', DH.word('Undo', words), 'pg-doc-head-undo');
            redoButton = iconButton('bi-arrow-clockwise', DH.word('Redo', words), 'pg-doc-head-redo');
            undoButton.addEventListener('click', function () {
                call(o.undo.onUndo);
            });
            redoButton.addEventListener('click', function () {
                call(o.undo.onRedo);
            });
            history.appendChild(separator());
            history.appendChild(undoButton);
            history.appendChild(redoButton);
        }

        var slot = null;

        if (o.slot) {
            slot = el('div', 'pg-doc-head-slot');
            slot.appendChild(o.slot);
            history.appendChild(separator());
            history.appendChild(slot);
        }

        if (history.firstChild) {
            root.appendChild(history);
        }

        root.appendChild(el('span', 'pg-doc-head-space'));

        // Acts: faces, Share, Download, the main button, the menu, grow, Close.
        if (o.faces && o.faces.length) {
            var faces = el('span', 'pg-doc-head-faces');

            o.faces.slice(0, 4).forEach(function (face, i) {
                var dot = el('span', 'pg-initials' + (i % 3 ? ' pg-initials-' + (i % 3 + 1) : ''), face.picture ? null : face.initials);

                if (face.picture) {
                    var img = el('img');

                    img.src = face.picture;
                    img.alt = '';
                    dot.appendChild(img);
                }

                dot.title = face.name || face.initials || '';
                faces.appendChild(dot);
            });

            acts.appendChild(faces);
        }

        var shareButton = null;

        if (o.share) {
            shareButton = textButton('pg-button pg-button-ghost pg-button-sm pg-doc-head-share', 'bi-person-plus', o.share.label || DH.word('Share', words));
            shareButton.addEventListener('click', function () {
                call(o.share.onClick);
            });
            acts.appendChild(shareButton);
        }

        if (o.download) {
            var downloadButton = iconButton('bi-download', DH.word('Download', words), 'pg-doc-head-download');

            downloadButton.addEventListener('click', function () {
                call(o.download.onClick);
            });
            acts.appendChild(downloadButton);
        }

        if (o.main && o.main.node) {
            mainButton = null;
            o.main.node.classList.add('pg-doc-head-main-node');
            acts.appendChild(o.main.node);
        } else if (o.main) {
            mainButton = textButton(DH.mainClass(o.main), o.main.icon || null, o.main.label || DH.word('Save', words));
            mainButton.addEventListener('click', function (event) {
                if (mainButton.getAttribute('aria-disabled') === 'true') {
                    event.preventDefault();
                    return;
                }

                call(o.main.onClick, event);
            });
            acts.appendChild(mainButton);
        }

        // The menu: its rows for a wide window; a one-row menu stands as an
        // icon button there, and ⋯ is then drawn for the phone alone.
        var groups = DH.menuGroups(o.menu, null, words);
        var single = DH.singleRow(groups);
        var phoneFirst = [];

        if (o.undo) {
            phoneFirst.push({ key: 'undo', icon: 'bi-arrow-counterclockwise', label: DH.word('Undo', words), run: o.undo.onUndo });
            phoneFirst.push({ key: 'redo', icon: 'bi-arrow-clockwise', label: DH.word('Redo', words), run: o.undo.onRedo });
        }

        if (o.share) {
            phoneFirst.push({ key: 'share', icon: 'bi-person-plus', label: o.share.label || DH.word('Share', words), run: o.share.onClick });
        }

        if (o.download && !(o.menu && o.menu.download)) {
            phoneFirst.push({ key: 'download', icon: 'bi-download', label: DH.word('Download', words), run: o.download.onClick });
        }

        if (single) {
            var singleButton = iconButton(single.icon, single.label, 'pg-doc-head-single' + (single.danger ? ' is-danger' : ''));

            singleButton.addEventListener('click', function () {
                call(single.run);
            });
            acts.appendChild(singleButton);
        }

        if (groups.length || phoneFirst.length || slot) {
            moreWrap = el('span', 'pg-doc-head-more');
            moreButton = iconButton('bi-three-dots', DH.word('More actions', words), 'pg-doc-head-more-button' + (groups.length > 0 && !single ? '' : ' pg-doc-head-phone-only'));
            moreButton.setAttribute('aria-haspopup', 'menu');
            moreButton.setAttribute('aria-expanded', 'false');
            menuNode = el('div', 'pg-doc-head-menu');
            menuNode.setAttribute('role', 'menu');
            menuNode.hidden = true;
            moreWrap.appendChild(moreButton);
            moreWrap.appendChild(menuNode);
            acts.appendChild(moreWrap);

            moreButton.addEventListener('click', function () {
                if (menuNode.hidden) {
                    openMenu();
                } else {
                    api.closeMenu();
                }
            });
        }

        if (o.grow) {
            var growLabel = o.grow.label || DH.word('Full screen', words);
            var growButton = iconButton(o.grow.icon || 'bi-arrows-fullscreen', growLabel, 'pg-doc-head-grow');

            growButton.addEventListener('click', function () {
                call(o.grow.onClick);
            });
            acts.appendChild(growButton);
        }

        if (o.close) {
            var closeButton = iconButton('bi-x-lg', o.close.label || DH.word('Close', words), 'pg-doc-head-close');

            closeButton.addEventListener('click', function () {
                call(o.close.onClick, api.isDirty());
            });
            acts.appendChild(closeButton);
        }

        root.appendChild(acts);

        // ------------------------------------------------------ the menu

        function menuItem(row) {
            var item = el('button', 'pg-doc-head-menu-item' + (row.danger ? ' is-danger' : ''));

            item.type = 'button';
            item.setAttribute('role', 'menuitem');
            item.setAttribute('data-row', row.key);
            item.appendChild(icon(row.icon));
            item.appendChild(el('span', 'pg-doc-head-menu-label', row.label));

            if (row.count !== null && row.count !== undefined) {
                item.appendChild(el('span', 'pg-count', row.count));
            }

            if (row.key === 'undo' && undoButton) {
                item.disabled = undoButton.disabled;
            } else if (row.key === 'redo' && redoButton) {
                item.disabled = redoButton.disabled;
            }

            item.addEventListener('click', function () {
                api.closeMenu();
                call(row.run);
            });

            return item;
        }

        function onOutside(event) {
            if (moreWrap && !moreWrap.contains(event.target)) {
                api.closeMenu();
            }
        }

        function onKey(event) {
            var items = Array.prototype.slice.call(menuNode.querySelectorAll('.pg-doc-head-menu-item:not(:disabled)'));
            var at = items.indexOf(window.document.activeElement);

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                api.closeMenu();
                moreButton.focus();
            } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
                event.preventDefault();
                at = event.key === 'ArrowDown' ? (at + 1) % items.length : (at <= 0 ? items.length - 1 : at - 1);
                items[at].focus();
            }
        }

        function openMenu() {
            var phone = isPhone();
            var drawn = DH.menuGroups(o.menu, phone ? phoneFirst : null, words);

            menuNode.textContent = '';

            drawn.forEach(function (rows, i) {
                if (i) {
                    menuNode.appendChild(el('hr', 'pg-doc-head-menu-line'));
                }

                rows.forEach(function (row) {
                    menuNode.appendChild(menuItem(row));
                });
            });

            // On a phone the slot follows Undo and Redo.
            if (phone && slot) {
                var holder = el('div', 'pg-doc-head-menu-slot');
                var redo = menuNode.querySelector('[data-row="redo"]');

                holder.appendChild(o.slot);
                menuNode.insertBefore(holder, redo ? redo.nextSibling : menuNode.firstChild);
            }

            menuNode.hidden = false;
            moreButton.setAttribute('aria-expanded', 'true');
            window.document.addEventListener('pointerdown', onOutside, true);
            menuNode.addEventListener('keydown', onKey);

            var first = menuNode.querySelector('.pg-doc-head-menu-item:not(:disabled)');

            if (first) {
                first.focus();
            }
        }

        api.closeMenu = function () {
            if (!menuNode || menuNode.hidden) {
                return;
            }

            // The slot goes back to the head.
            if (slot && o.slot.parentNode !== slot) {
                slot.appendChild(o.slot);
            }

            menuNode.hidden = true;
            menuNode.textContent = '';
            moreButton.setAttribute('aria-expanded', 'false');
            window.document.removeEventListener('pointerdown', onOutside, true);
            menuNode.removeEventListener('keydown', onKey);
        };

        // ------------------------------------------------------ the name

        function drawName() {
            nameNode.textContent = current.name;
            nameNode.title = current.name;
            root.setAttribute('aria-label', current.name);
        }

        function startRename() {
            if (nameInput) {
                return;
            }

            var before = current.name;
            var done = false;

            nameInput = el('input', 'pg-doc-head-name pg-doc-head-name-input');
            nameInput.type = 'text';
            nameInput.value = before;
            nameInput.setAttribute('aria-label', DH.word('Rename', words));
            nameNode.hidden = true;
            nameNode.parentNode.insertBefore(nameInput, nameNode);
            nameInput.focus();
            nameInput.select();

            function finish(save) {
                if (done) {
                    return;
                }

                done = true;

                var name = save ? DH.cleanName(nameInput.value, before) : before;

                nameInput.remove();
                nameInput = null;
                nameNode.hidden = false;
                api.setName(name);
                nameNode.focus();

                if (save && name !== before) {
                    var answer = o.rename(name);

                    if (answer && typeof answer.then === 'function') {
                        answer.then(null, function () {
                            api.setName(before);
                        });
                    } else if (answer === false) {
                        api.setName(before);
                    }
                }
            }

            nameInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    finish(true);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    finish(false);
                }
            });
            nameInput.addEventListener('blur', function () {
                finish(true);
            });
        }

        if (typeof o.rename === 'function') {
            nameNode.addEventListener('click', startRename);
        }

        // ------------------------------------------------------ setters

        api.setName = function (name) {
            current.name = DH.cleanName(name, current.name);
            drawName();
        };

        api.setMeta = function (meta) {
            metaNode.textContent = meta === null || meta === undefined ? '' : String(meta);
            metaNode.hidden = metaNode.textContent === '';
        };

        function drawState() {
            var view = DH.stateView(current.state, current.info, Date.now(), words);

            stateNode.hidden = !view;
            stateNode.className = 'pg-doc-head-state' + (view ? ' is-' + view.state : '');
            stateText.textContent = view ? view.text : '';
            stateNode.title = view ? (view.title || view.text) : '';
        }

        api.setState = function (state, info) {
            current.state = state || null;
            current.info = info || null;
            current.dirty = state === 'unsaved' || (state === 'failed' && current.dirty);
            drawState();

            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }

            // "· 2 min ago" moves on by itself.
            if (state === 'saved' && info && info.ago && info.time) {
                timer = window.setInterval(drawState, 30000);
            }
        };

        api.isDirty = function () {
            return current.dirty;
        };

        api.setUndo = function (canUndo, canRedo) {
            if (undoButton) {
                undoButton.disabled = !canUndo;
                redoButton.disabled = !canRedo;
            }
        };

        api.setMain = function (state) {
            state = state || {};

            if (!mainButton) {
                return;
            }

            if (state.label !== undefined) {
                mainButton.querySelector('.pg-btn-label').textContent = String(state.label);
            }

            if (state.dirty !== undefined) {
                var off = !!(o.main.gate && !state.dirty);

                mainButton.classList.toggle('is-off', off);
                mainButton.setAttribute('aria-disabled', off ? 'true' : 'false');
            }

            if (state.disabled !== undefined) {
                mainButton.disabled = !!state.disabled;
            }
        };

        api.destroy = function () {
            api.closeMenu();

            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }

            root.remove();
        };

        drawName();
        api.setMeta(o.meta);
        api.setState(o.state, o.stateInfo);

        if (o.undo) {
            api.setUndo(o.undo.canUndo, o.undo.canRedo);
        }

        if (mainButton) {
            api.setMain({ dirty: !!(o.main && o.main.dirty) });
        }

        return api;
    };

    /**
     * Builds a head into each element of `scope` (the document when none)
     * marked data-pg-doc-head, from the JSON options it holds. Callbacks
     * cannot be written in JSON: such a head shows, renames in place and
     * opens its menu, nothing more (the Design examples screen).
     */
    DH.mountAll = function (scope) {
        var hosts = (scope || window.document).querySelectorAll('[data-pg-doc-head]');

        Array.prototype.forEach.call(hosts, function (host) {
            var options;

            if (host.getAttribute('data-pg-doc-head-built')) {
                return;
            }

            try {
                options = JSON.parse(host.getAttribute('data-pg-doc-head'));
            } catch (e) {
                return;
            }

            if (options.renamable) {
                options.rename = function () {};
            }

            if (options.slotHtml) {
                var holder = window.document.createElement('span');

                holder.className = 'pg-doc-head-slot-items';
                holder.innerHTML = options.slotHtml;
                options.slot = holder;
            }

            ['back', 'share', 'download', 'grow', 'close'].forEach(function (key) {
                if (options[key] === true) {
                    options[key] = {};
                }
            });

            if (options.menu) {
                Object.keys(options.menu).forEach(function (key) {
                    if (options.menu[key] === true) {
                        options.menu[key] = function () {};
                    }
                });
            }

            host.setAttribute('data-pg-doc-head-built', '1');
            host.appendChild(DH.build(options).root);
        });
    };

    window.pgDocHead = DH;

    if (window.document && window.document.addEventListener) {
        if (window.document.readyState === 'loading') {
            window.document.addEventListener('DOMContentLoaded', function () {
                DH.mountAll();
            });
        } else {
            DH.mountAll();
        }
    }
})(window);
