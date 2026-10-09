/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the search box of every workspace screen: Ctrl+K (⌘K on a
 * Mac) or the magnifier of the rail opens it, and what is typed is looked
 * for in the channels, messages, decisions, tasks, notes, files and records
 * at once (ws_palette, includes/workspace/palette.php). The answer comes in
 * groups; the arrow keys move through it, Enter opens a line, Esc closes.
 *
 * # looks only among the records, @ among the people of the team, / among
 * the commands of the writing box (a command picked is written into the box
 * of the channel open on the screen). The last five searches are kept in
 * the browser, per person, and shown while the box is empty.
 *
 * assets/js/workspace.js opens it (window.PGWsPalette.open()) and lends it
 * its helpers through window.PGWsKit. Every text comes from the #ws-config
 * block; what the server sends is written with textContent, the matched
 * words marked with <mark> nodes built here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var KINDS = [
        ['channels', 'bi-hash'],
        ['messages', 'bi-chat-left-text'],
        ['decisions', 'bi-patch-check'],
        ['tasks', 'bi-check2-square'],
        ['notes', 'bi-journal-text'],
        ['files', 'bi-paperclip'],
        ['records', 'bi-tags']
    ];

    // The kinds that belong to one channel, for "only in this channel".
    var CHANNEL_KINDS = ['messages', 'decisions', 'tasks', 'files'];
    var RECENT_MAX = 5;

    var ui = null;
    var state = {
        open: false,
        scope: 'all',
        kind: '',
        items: [],
        active: -1,
        serial: 0,
        timer: null,
        after: null,
        here: null
    };

    function kit() {
        return window.PGWsKit || null;
    }

    function t() {
        var k = kit();

        return k ? k.t.apply(null, arguments) : String(arguments[0]);
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

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    function locale() {
        var k = kit();

        return (k && k.cfg && k.cfg.locale) ? String(k.cfg.locale) : undefined;
    }

    function lower(text) {
        try {
            return String(text).toLocaleLowerCase(locale());
        } catch (error) {
            return String(text).toLowerCase();
        }
    }

    // The text with every place the words are found wrapped in a <mark>, as
    // nodes: nothing the server sent is read as markup.
    function marked(text, query) {
        var box = document.createDocumentFragment();
        var source = String(text || '');
        var needle = lower(String(query || '').trim());
        var hay = lower(source);

        // Lower case that changes the length (a dotted capital I outside
        // Turkish) would put the marks in the wrong place: none then.
        if (!needle || (hay.length !== source.length)) {
            box.appendChild(document.createTextNode(source));
            return box;
        }

        var at = 0;
        var found = hay.indexOf(needle);

        while (found !== -1) {
            if (found > at) {
                box.appendChild(document.createTextNode(source.slice(at, found)));
            }

            box.appendChild(el('mark', 'ws-palette-mark', source.slice(found, found + needle.length)));
            at = found + needle.length;
            found = hay.indexOf(needle, at);
        }

        if (at < source.length) {
            box.appendChild(document.createTextNode(source.slice(at)));
        }

        return box;
    }

    // ── Recent searches (this browser, this person) ──

    function recentKey() {
        var k = kit();

        return 'pg-ws-palette-recent-' + ((k && k.cfg) ? k.cfg.me : 0);
    }

    function recentList() {
        try {
            var list = JSON.parse(window.localStorage.getItem(recentKey()) || '[]');

            return Array.isArray(list) ? list.filter(function (item) { return typeof item === 'string'; }).slice(0, RECENT_MAX) : [];
        } catch (error) {
            return [];
        }
    }

    function remember(query) {
        query = String(query || '').trim();

        if (query.length < 2) {
            return;
        }

        var list = recentList().filter(function (item) { return item !== query; });
        list.unshift(query);

        try {
            window.localStorage.setItem(recentKey(), JSON.stringify(list.slice(0, RECENT_MAX)));
        } catch (error) {
            // A browser that keeps nothing: the box works without its memory.
        }
    }

    function forget() {
        try {
            window.localStorage.removeItem(recentKey());
        } catch (error) {
            // Nothing kept, nothing to forget.
        }
    }

    // ── The window ──

    function build() {
        if (ui) {
            return ui;
        }

        ui = {};
        ui.node = el('div', 'modal fade ws-palette');
        ui.node.id = 'ws-palette';
        ui.node.tabIndex = -1;
        ui.node.setAttribute('aria-label', t('pal_open'));

        var dialog = el('div', 'modal-dialog modal-lg ws-palette-dialog');
        var content = el('div', 'modal-content');
        var head = el('div', 'ws-palette-head');

        head.appendChild(icon('bi-search', 'ws-palette-glass'));

        ui.input = el('input', 'form-control ws-palette-input');
        ui.input.type = 'search';
        ui.input.autocomplete = 'off';
        ui.input.spellcheck = false;
        ui.input.placeholder = t('pal_placeholder');
        ui.input.setAttribute('aria-label', t('pal_open'));
        ui.input.setAttribute('role', 'combobox');
        ui.input.setAttribute('aria-autocomplete', 'list');
        ui.input.setAttribute('aria-controls', 'ws-palette-list');
        ui.input.setAttribute('aria-expanded', 'true');
        head.appendChild(ui.input);
        head.appendChild(el('kbd', 'ws-palette-esc', 'Esc'));
        content.appendChild(head);

        ui.filters = el('div', 'ws-palette-filters');
        ui.scope = el('div', 'btn-group btn-group-sm ws-palette-scope');
        ui.scope.setAttribute('role', 'group');
        ui.filters.appendChild(ui.scope);
        ui.kinds = el('div', 'ws-palette-kinds');
        ui.kinds.setAttribute('role', 'group');
        ui.filters.appendChild(ui.kinds);
        content.appendChild(ui.filters);

        ui.list = el('div', 'ws-palette-list');
        ui.list.id = 'ws-palette-list';
        ui.list.setAttribute('role', 'listbox');
        ui.list.setAttribute('aria-label', t('pal_open'));
        content.appendChild(ui.list);

        ui.live = el('div', 'visually-hidden');
        ui.live.setAttribute('aria-live', 'polite');
        content.appendChild(ui.live);

        content.appendChild(el('div', 'ws-palette-foot', t('pal_hint')));
        dialog.appendChild(content);
        ui.node.appendChild(dialog);
        document.body.appendChild(ui.node);

        ui.input.addEventListener('input', function () {
            clearTimeout(state.timer);
            state.timer = setTimeout(search, 300);
        });

        ui.input.addEventListener('keydown', onKey);

        ui.node.addEventListener('shown.bs.modal', function () {
            ui.input.focus();
            ui.input.select();
        });

        ui.node.addEventListener('hidden.bs.modal', function () {
            var after = state.after;

            state.open = false;
            state.after = null;

            if (after) {
                after();
            }
        });

        return ui;
    }

    function drawFilters() {
        var here = state.here;

        clear(ui.scope);
        ui.scope.classList.toggle('d-none', !here);

        if (here) {
            [['all', t('pal_everywhere')], ['here', t('pal_here', here.name)]].forEach(function (option) {
                var item = el('button', 'btn btn-ghost' + (state.scope === option[0] ? ' active' : ''), option[1]);
                item.type = 'button';
                item.setAttribute('aria-pressed', state.scope === option[0] ? 'true' : 'false');
                item.addEventListener('click', function () {
                    state.scope = option[0];
                    drawFilters();
                    search();
                    ui.input.focus();
                });
                ui.scope.appendChild(item);
            });
        }

        clear(ui.kinds);

        [['', 'bi-asterisk']].concat(KINDS).forEach(function (kind) {
            var off = (state.scope === 'here') && kind[0] && (CHANNEL_KINDS.indexOf(kind[0]) === -1);
            var chip = el('button', 'btn btn-sm ws-palette-kind' + (state.kind === kind[0] ? ' active' : ''));

            chip.type = 'button';
            chip.disabled = !!off;
            chip.setAttribute('aria-pressed', state.kind === kind[0] ? 'true' : 'false');
            chip.appendChild(icon(kind[1], 'me-1'));
            chip.appendChild(document.createTextNode(kind[0] ? t('pal_kind_' + kind[0]) : t('pal_all_kinds')));
            chip.addEventListener('click', function () {
                state.kind = kind[0];
                drawFilters();
                search();
                ui.input.focus();
            });
            ui.kinds.appendChild(chip);
        });
    }

    // ── Searching ──

    function search() {
        var k = kit();
        var raw = ui.input.value;
        var typed = raw.trim();
        var first = typed.charAt(0);
        var prefixed = (first === '#') || (first === '@') || (first === '/');
        var mine = ++state.serial;

        clearTimeout(state.timer);
        state.timer = null;

        if (!typed) {
            showRecent();
            return;
        }

        if (!prefixed && (typed.length < 2)) {
            showNote(t('pal_short'));
            return;
        }

        if (!k) {
            return;
        }

        var kind = state.kind;

        if ((state.scope === 'here') && kind && (CHANNEL_KINDS.indexOf(kind) === -1)) {
            kind = '';
        }

        k.api('ws_palette', {
            q: typed,
            channel_id: ((state.scope === 'here') && state.here) ? state.here.id : 0,
            here: state.here ? state.here.id : 0,
            types: kind ? [kind] : [],
            limit: 8
        }).then(function (data) {
            if (mine === state.serial) {
                draw(data);
            }
        }).catch(function (error) {
            if (mine === state.serial) {
                showNote((error && error.message) ? error.message : t('error_generic'));
            }
        });
    }

    function showNote(text) {
        clear(ui.list);
        state.items = [];
        setActive(-1);
        ui.list.appendChild(el('div', 'ws-palette-empty', text));
    }

    function showRecent() {
        var list = recentList();

        clear(ui.list);
        state.items = [];
        setActive(-1);

        if (!list.length) {
            ui.list.appendChild(el('div', 'ws-palette-empty', t('pal_placeholder')));
            return;
        }

        var head = el('div', 'ws-palette-group');
        head.appendChild(icon('bi-clock-history', 'me-1'));
        head.appendChild(document.createTextNode(t('pal_recent')));

        var wipe = el('button', 'btn btn-link btn-sm ms-auto p-0 ws-palette-forget', t('pal_clear_recent'));
        wipe.type = 'button';
        wipe.addEventListener('click', function () {
            forget();
            showRecent();
            ui.input.focus();
        });
        head.appendChild(wipe);
        ui.list.appendChild(head);

        list.forEach(function (query) {
            addRow({ type: 'recent', title: query, icon: 'bi-clock-history', sub: '', date: '' }, '');
        });
    }

    function draw(data) {
        var query = data.query || '';
        var groups = data.groups || [];

        clear(ui.list);
        state.items = [];
        setActive(-1);

        if ((data.mode === 'commands') && !(state.here && state.here.canPost)) {
            ui.list.appendChild(el('div', 'ws-palette-note', t('pal_no_composer')));
        }

        var count = 0;

        groups.forEach(function (group) {
            if (!group.items || !group.items.length) {
                return;
            }

            var head = el('div', 'ws-palette-group');
            head.appendChild(icon(group.icon || 'bi-dot', 'me-1'));
            head.appendChild(document.createTextNode(group.title));
            head.appendChild(el('span', 'ws-palette-count', group.items.length));
            ui.list.appendChild(head);

            group.items.forEach(function (item) {
                addRow(item, (data.mode === 'commands') ? '' : query);
                count++;
            });
        });

        if (!count) {
            ui.list.appendChild(el('div', 'ws-palette-empty', t('nothing_found')));
        }

        ui.live.textContent = count ? t('pal_count', count) : t('nothing_found');
        setActive(count ? 0 : -1);
    }

    function addRow(item, query) {
        var k = kit();
        var index = state.items.length;
        var row = el('div', 'ws-palette-item' + (item.done ? ' ws-palette-done' : ''));

        row.id = 'ws-palette-opt-' + index;
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', 'false');

        if ((item.type === 'person') && item.avatar && k) {
            var face = k.avatar({ avatar: item.avatar, avatar_kind: item.avatar_kind }, '1.6rem');
            face.classList.add('ws-palette-face');
            row.appendChild(face);
        } else {
            row.appendChild(el('span', 'ws-palette-icon')).appendChild(icon(item.icon || 'bi-dot'));
        }

        var main = el('div', 'ws-palette-main');
        var title = el('div', 'ws-palette-title');
        title.appendChild(marked(item.title, query));
        main.appendChild(title);

        if (item.sub) {
            var sub = el('div', 'ws-palette-sub');
            sub.appendChild(marked(item.sub, query));
            main.appendChild(sub);
        }

        row.appendChild(main);

        if (item.date) {
            row.appendChild(el('span', 'ws-palette-date' + (item.overdue ? ' text-danger' : ''), item.date));
        }

        // A colleague: the conversation with them, and their account where
        // the reader may open it.
        if ((item.type === 'person') && !item.me && item.url && (typeof window.pgChatOpenWith === 'function')) {
            var account = el('a', 'btn btn-sm btn-ghost ws-palette-side');
            account.href = item.url;
            account.title = t('pal_profile');
            account.setAttribute('aria-label', t('pal_profile'));
            account.appendChild(icon('bi-person-gear'));
            account.addEventListener('click', function (event) { event.stopPropagation(); });
            row.appendChild(account);
        }

        row.addEventListener('mousemove', function () {
            if (state.active !== index) {
                setActive(index);
            }
        });

        row.addEventListener('click', function () {
            choose(index);
        });

        state.items.push({ data: item, node: row });
        ui.list.appendChild(row);
    }

    function setActive(index) {
        if (state.items[state.active]) {
            state.items[state.active].node.classList.remove('active');
            state.items[state.active].node.setAttribute('aria-selected', 'false');
        }

        state.active = index;

        if (state.items[index]) {
            var node = state.items[index].node;

            node.classList.add('active');
            node.setAttribute('aria-selected', 'true');
            ui.input.setAttribute('aria-activedescendant', node.id);

            if (node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }
        } else {
            ui.input.removeAttribute('aria-activedescendant');
        }
    }

    function onKey(event) {
        var count = state.items.length;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (count) {
                var step = (event.key === 'ArrowDown') ? 1 : -1;
                setActive((state.active + step + count) % count);
            }

            return;
        }

        if (event.key === 'Enter' && !event.isComposing) {
            event.preventDefault();

            // Typed faster than the answer came: the search runs now, and
            // Enter waits for the next one.
            if (state.timer) {
                search();
                return;
            }

            if (state.active >= 0) {
                choose(state.active);
            }
        }
    }

    // ── Going where a line points ──

    function go(url) {
        if (url) {
            window.location.href = url;
        }
    }

    function choose(index) {
        var entry = state.items[index];
        var k = kit();
        var here = state.here;

        if (!entry || !k) {
            return;
        }

        var item = entry.data;

        if (item.type === 'recent') {
            ui.input.value = item.title;
            search();
            ui.input.focus();
            return;
        }

        if ((item.type === 'command') && !(here && here.canPost)) {
            k.toast(t('pal_no_composer'), 'warning');
            return;
        }

        remember(ui.input.value);

        var action = function () {};

        switch (item.type) {
            case 'channel':
                action = function () {
                    if (here && here.openChannel) {
                        here.openChannel(item.channel_id, 0);
                    } else {
                        go(item.url);
                    }
                };
                break;

            case 'message':
            case 'decision':
                action = function () {
                    if (here && here.openChannel) {
                        here.openChannel(item.channel_id, item.message_id);
                    } else {
                        go(item.url);
                    }
                };
                break;

            case 'file':
                action = function () {
                    if (item.url) {
                        window.open(item.url, '_blank', 'noopener');
                    } else if (here && here.openChannel) {
                        here.openChannel(item.channel_id, item.message_id);
                    } else {
                        go(item.message_url);
                    }
                };
                break;

            case 'task':
                action = function () { k.openTask(item.id, null, null); };
                break;

            case 'person':
                action = function () {
                    if (!item.me && (typeof window.pgChatOpenWith === 'function')) {
                        k.directMessage(item.id);
                    } else {
                        go(item.url);
                    }
                };
                break;

            case 'command':
                action = function () { here.insert(item.insert); };
                break;

            default:
                action = function () { go(item.url); };
        }

        close(action);
    }

    // ── Opening and closing ──

    // What the screen says about itself: the channel open on it, and its
    // ways to open another or write into its box (assets/js/workspace.js).
    function readHere() {
        var k = kit();
        var here = (k && k.here) ? k.here() : null;

        return (here && here.id) ? here : null;
    }

    function open() {
        if (!window.bootstrap || !kit()) {
            return;
        }

        build();
        state.here = readHere();

        if (!state.here) {
            state.scope = 'all';
        }

        state.open = true;
        drawFilters();
        search();
        window.bootstrap.Modal.getOrCreateInstance(ui.node).show();
    }

    function close(after) {
        if (!ui || !state.open) {
            if (after) {
                after();
            }

            return;
        }

        state.after = after || null;
        window.bootstrap.Modal.getOrCreateInstance(ui.node).hide();
    }

    window.PGWsPalette = {
        open: open,
        close: function () { close(null); },
        isOpen: function () { return state.open; }
    };
})();
