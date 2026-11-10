/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker dialog (window.pgPicker): one Bootstrap 6 dialog for choosing a
 * file of a kind of pg_picker_kinds() (includes/fn/picker.php), read from the
 * picker.php endpoint. A folder tree, the open folder as tiles with pictures
 * or as a list, a folded name search across the folders, sorting, one pick,
 * the keyboard as in the File Manager; below 576 px a bottom sheet whose
 * folder chip opens a folder list in place of the tree.
 *
 *   pgPicker.open({kind, value, title}) -> Promise of the picked item
 *   ({value, text, ...}) or null, settled once the dialog has closed, so the
 *   caller may focus or edit the page (docs/memory/ui/dialog-result-after-close.md).
 *
 * A button with data-pg-picker-browse and data-pg-picker-kind opens it for
 * the field named by data-pg-picker-for and writes the pick there.
 * pg_picker_script() prints the texts and the address as JSON
 * (#pg_picker_config) beside this script. pgPicker.pure holds the helpers
 * without the DOM (tests/picker_js_test.php runs them in Node).
 *
 * Served minified as assets/js/pg_picker.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    if (root.pgPicker) {
        return;
    }

    // ── Pure helpers ──────────────────────────────────────────────────────

    // The combining accents NFD splits off (U+0300 to U+036F).
    var MARKS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');
    var SPECIAL = { 'æ': 'ae', 'œ': 'oe', 'ß': 'ss', 'ø': 'o', 'ł': 'l', 'đ': 'd', 'ŧ': 't', 'ı': 'i' };

    // One character folded as the server matches names
    // (fold_keyword_for_matching()): lower case, İ, I, ı and i alike, the
    // accents dropped (ş as s, ü as u).
    function foldChar(c) {
        var low = c.toLocaleLowerCase('tr');
        if (SPECIAL[low]) {
            return SPECIAL[low];
        }
        return low.normalize ? low.normalize('NFD').replace(MARKS, '') : low;
    }

    // The folded text and, for each of its characters, the index of the
    // character it came from, so a match slices the original.
    function foldMap(text) {
        var out = '', at = [];
        text = String(text === null || text === undefined ? '' : text);
        for (var i = 0; i < text.length; i++) {
            var folded = foldChar(text.charAt(i));
            for (var j = 0; j < folded.length; j++) {
                at.push(i);
            }
            out += folded;
        }
        at.push(text.length);
        return { text: out, at: at };
    }

    function foldText(text) {
        return foldMap(text).text;
    }

    // The text cut around the folded match: [{text, match}].
    function highlightParts(text, query) {
        text = String(text || '');
        var q = foldText(String(query || '').trim());
        var map = foldMap(text);
        var found = q ? map.text.indexOf(q) : -1;

        if (found === -1) {
            return [{ text: text, match: false }];
        }

        var start = map.at[found], end = map.at[found + q.length];
        if (end === start) {
            end = start + 1;
        }

        return [
            { text: text.slice(0, start), match: false },
            { text: text.slice(start, end), match: true },
            { text: text.slice(end), match: false }
        ].filter(function (part) { return part.text !== ''; });
    }

    // The direction a sort key starts in: newest and largest first.
    function defaultDir(key) {
        return (key === 'modified' || key === 'size') ? 'desc' : 'asc';
    }

    function makeCollator(lang) {
        try {
            return new Intl.Collator(lang || undefined, { numeric: true, sensitivity: 'base' });
        } catch (e) {
            return null;
        }
    }

    function compareText(a, b, collator) {
        if (collator) {
            return collator.compare(a, b);
        }
        a = String(a).toLowerCase();
        b = String(b).toLowerCase();
        return a < b ? -1 : (a > b ? 1 : 0);
    }

    // Folders first, then by the key; 'best' keeps the server's order (the
    // search's names starting with the text first). Stable.
    function sortItems(items, key, dir, collator) {
        var factor = (dir === 'desc') ? -1 : 1;

        return items.map(function (item, index) { return { item: item, index: index }; }).sort(function (a, b) {
            var fa = a.item.isFolder ? 0 : 1, fb = b.item.isFolder ? 0 : 1, out = 0;

            if (fa !== fb) {
                return fa - fb;
            }
            if (key === 'size' || key === 'modified') {
                out = (Number(a.item[key]) || 0) - (Number(b.item[key]) || 0);
            } else if (key === 'type') {
                out = compareText(String(a.item.type || ''), String(b.item.type || ''), collator);
            } else if (key !== 'best') {
                out = compareText(String(a.item.text || ''), String(b.item.text || ''), collator);
            }
            if (out !== 0) {
                return out * factor;
            }
            // A tie goes by name, A to Z, then as it came.
            return ((key !== 'name' && key !== 'best') ? compareText(String(a.item.text || ''), String(b.item.text || ''), collator) : 0) || (a.index - b.index);
        }).map(function (entry) { return entry.item; });
    }

    // The cursor after a key over a grid of `columns` (1 for the list).
    function stepIndex(index, count, columns, key) {
        if (count < 1) {
            return -1;
        }
        columns = Math.max(1, columns | 0);
        var page = columns * Math.max(1, Math.floor(12 / columns));
        var steps = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: columns, ArrowUp: -columns, PageDown: page, PageUp: -page };
        if (key === 'Home') {
            return 0;
        }
        if (key === 'End') {
            return count - 1;
        }
        if (index < 0) {
            return 0;
        }
        if (!(key in steps)) {
            return index;
        }
        if (columns === 1 && (key === 'ArrowRight' || key === 'ArrowLeft')) {
            return index;
        }
        return Math.min(count - 1, Math.max(0, index + steps[key]));
    }

    // Results kept to one folder and the folders under it.
    function underFolder(items, folderId) {
        folderId = Number(folderId);
        return items.filter(function (item) {
            return (item.path || []).some(function (crumb) { return Number(crumb.id) === folderId; });
        });
    }

    // The first item whose name starts with the typed letters, from `from` on.
    function typeAheadIndex(items, typed, from) {
        var q = foldText(typed);
        if (!q) {
            return -1;
        }
        for (var n = 0; n < items.length; n++) {
            var i = (Math.max(0, from) + n) % items.length;
            if (foldText(items[i].text).indexOf(q) === 0) {
                return i;
            }
        }
        return -1;
    }

    function typesLabel(types) {
        return (types || []).map(function (type) { return String(type).toUpperCase(); }).join(', ');
    }

    function fill(text, vars) {
        return String(text || '').replace(/\{(\w+)\}/g, function (all, name) {
            return (vars && vars[name] !== undefined) ? String(vars[name]) : all;
        });
    }

    // A folder line under a name: the top folder (every path starts there)
    // is left out once there is a folder below it.
    function pathText(path) {
        path = path || [];
        return (path.length > 1 ? path.slice(1) : path).map(function (crumb) { return crumb.name; }).join(' / ');
    }

    function fileIcon(type) {
        var t = String(type || '').toLowerCase();
        var map = { pdf: 'pdf', zip: 'zip', rar: 'zip', doc: 'word', docx: 'word', xls: 'excel', xlsx: 'excel', csv: 'spreadsheet', ppt: 'ppt', pptx: 'ppt', txt: 'text', md: 'text', mp3: 'music', wav: 'music', ogg: 'music', mp4: 'play', webm: 'play', mov: 'play', js: 'code', css: 'code', html: 'code', json: 'code', xml: 'code' };
        if (/^(jpe?g|png|gif|webp|avif|svg|bmp)$/.test(t)) {
            return 'bi-file-earmark-image';
        }
        return map[t] ? 'bi-file-earmark-' + map[t] : 'bi-file-earmark';
    }

    var pure = {
        foldText: foldText, highlightParts: highlightParts, defaultDir: defaultDir, sortItems: sortItems,
        stepIndex: stepIndex, underFolder: underFolder, typeAheadIndex: typeAheadIndex, typesLabel: typesLabel,
        fill: fill, pathText: pathText, fileIcon: fileIcon, makeCollator: makeCollator
    };

    var doc = root.document;

    if (!doc || !doc.createElement) {
        root.pgPicker = { pure: pure };
        return;
    }

    // ── Settings, texts, storage ──────────────────────────────────────────

    var config = null;

    function conf() {
        if (!config) {
            var node = doc.getElementById('pg_picker_config');
            try {
                config = JSON.parse(node ? node.textContent : '{}') || {};
            } catch (e) {
                config = {};
            }
            config.texts = config.texts || {};
        }
        return config;
    }

    function t(key, vars) {
        var text = conf().texts[key];
        return fill(text === undefined ? key : text, vars);
    }

    function remember(name, value) {
        try {
            if (value === undefined) {
                return root.localStorage.getItem('pg.picker.' + name);
            }
            root.localStorage.setItem('pg.picker.' + name, value);
        } catch (e) {
            return null;
        }
        return value;
    }

    function el(tag, className, text) {
        var node = doc.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function icon(name) {
        var node = el('span', 'bi ' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';
        if (iconName) {
            node.appendChild(icon(iconName));
        }
        if (label) {
            node.appendChild(el('span', 'pg-btn-label', label));
        }
        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
        }
        return node;
    }

    function highlighted(text, query) {
        var span = el('span', 'pg-picker-name');
        highlightParts(text, query).forEach(function (part) {
            span.appendChild(part.match ? el('mark', '', part.text) : doc.createTextNode(part.text));
        });
        span.title = text;
        return span;
    }

    function request(params) {
        var query = [];
        Object.keys(params).forEach(function (name) {
            [].concat(params[name]).forEach(function (value) {
                query.push(encodeURIComponent(Array.isArray(params[name]) ? name + '[]' : name) + '=' + encodeURIComponent(value));
            });
        });
        return root.fetch(conf().endpoint + '?' + query.join('&'), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    if (!response.ok || body.error) {
                        throw new Error(body.error || t('The list could not be loaded.'));
                    }
                    return body;
                });
            });
    }

    // Every page of a list or search (at most `max` pages of 100).
    function requestAll(params, max) {
        var items = [], base = null;
        function next(page) {
            return request(Object.assign({}, params, { page: page, per_page: 100 })).then(function (body) {
                base = base || body;
                items = items.concat(body.items || []);
                return (body.more && page < max) ? next(page + 1) : Object.assign({}, base, { items: items, more: !!body.more });
            });
        }
        return next(1);
    }

    function formatDate(seconds) {
        if (!seconds) {
            return '';
        }
        try {
            return new Intl.DateTimeFormat(doc.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(seconds * 1000));
        } catch (e) {
            return new Date(seconds * 1000).toLocaleString();
        }
    }

    function accessLabel(access) {
        var labels = { 'public': t('Public'), 'private': t('Private'), guest: t('Guest'), registration: t('Registration'), membership: t('Membership') };
        return labels[access] || '';
    }

    // ── The dialog ────────────────────────────────────────────────────────

    function Picker(opts) {
        this.opts = opts || {};
        this.kind = String(this.opts.kind || 'file');
        this.collator = makeCollator(doc.documentElement.lang);
        this.tree = {};
        this.open = {};
        this.folder = 0;
        this.crumbs = [];
        this.files = [];
        this.results = null;
        this.query = '';
        this.onlyHere = false;
        this.view = remember('view') === 'list' ? 'list' : 'grid';
        this.sort = remember('sort') || 'name';
        this.dir = remember('dir') || defaultDir(this.sort);
        this.cursor = -1;
        this.pick = null;
        this.result = null;
        this.folderList = false;
        this.listFolder = 0;
        this.typed = '';
        this.seq = 0;
    }

    Picker.prototype.build = function () {
        var self = this;
        var parts = root.pgUI.dialog.build({ size: 'xl', className: 'pg-picker', closeLabel: t('Close'), title: this.opts.title || t('Choose a file') });

        this.root = parts.root;
        this.titleNode = parts.title;
        this.types = el('span', 'pg-picker-types');
        parts.title.after(this.types);

        var bar = el('div', 'pg-picker-bar');
        this.upButton = button('btn btn-icon pg-picker-up', '', 'bi-arrow-90deg-up', t('Up'));
        this.crumbNav = el('nav', 'pg-picker-crumbs');
        this.crumbNav.setAttribute('aria-label', t('Folders'));
        this.chip = button('btn pg-picker-chip', '', 'bi-folder-fill');
        this.chip.setAttribute('aria-haspopup', 'true');

        var search = el('div', 'pg-picker-search');
        search.appendChild(icon('bi-search'));
        this.search = el('input', 'form-control');
        this.search.type = 'search';
        this.search.placeholder = t('Search');
        this.search.setAttribute('aria-label', t('Search'));
        this.clear = button('pg-picker-clear', '', 'bi-x-circle-fill', t('Clear the search'));
        this.clear.hidden = true;
        search.appendChild(this.search);
        search.appendChild(this.clear);

        var sortBox = el('label', 'pg-picker-sort');
        sortBox.appendChild(icon('bi-sort-down'));
        this.sortSelect = el('select', 'form-control form-control-sm');
        this.sortSelect.setAttribute('aria-label', t('Sort'));
        sortBox.appendChild(this.sortSelect);

        var views = el('div', 'pg-picker-views');
        views.setAttribute('role', 'group');
        views.setAttribute('aria-label', t('View'));
        this.gridButton = button('btn btn-icon', '', 'bi-grid-3x3-gap', t('Grid'));
        this.listButton = button('btn btn-icon', '', 'bi-list-ul', t('List'));
        views.appendChild(this.gridButton);
        views.appendChild(this.listButton);

        [this.upButton, this.crumbNav, this.chip, search, sortBox, views].forEach(function (node) { bar.appendChild(node); });

        var main = el('div', 'pg-picker-main');
        this.treeBox = el('nav', 'pg-picker-tree');
        this.treeBox.setAttribute('aria-label', t('Folders'));
        this.treeBox.appendChild(el('div', 'pg-picker-section', t('Folders')));
        this.treeList = el('ul');
        this.treeList.setAttribute('role', 'tree');
        this.treeList.setAttribute('aria-label', t('Folders'));
        this.treeBox.appendChild(this.treeList);

        var pane = el('section', 'pg-picker-pane');
        this.status = el('div', 'pg-picker-status');
        this.status.setAttribute('aria-live', 'polite');
        this.head = el('div', 'pg-picker-head');
        this.itemsBox = el('div', 'pg-picker-items');
        this.itemsBox.id = 'pg_picker_items_' + Date.now();
        this.itemsBox.tabIndex = 0;
        this.itemsBox.setAttribute('role', 'listbox');
        this.itemsBox.setAttribute('aria-label', t('Files'));
        pane.appendChild(this.status);
        pane.appendChild(this.head);
        pane.appendChild(this.itemsBox);
        main.appendChild(this.treeBox);
        main.appendChild(pane);

        parts.body.appendChild(bar);
        parts.body.appendChild(main);

        this.pickBox = el('div', 'pg-picker-pick');
        this.hint = el('span', 'pg-picker-hint');
        this.cancel = button('btn btn-outline theme-secondary pg-picker-cancel', t('Cancel'));
        this.back = button('btn btn-outline theme-secondary pg-picker-back', t('Back'));
        this.back.hidden = true;
        this.choose = button('btn btn-solid theme-primary pg-picker-choose', t('Choose'));
        this.choose.disabled = true;
        [this.pickBox, this.hint, this.cancel, this.back, this.choose].forEach(function (node) { parts.footer.appendChild(node); });

        doc.body.appendChild(this.root);
        this.bind();
        this.fillSort();
        this.renderPick();

        var phone = root.matchMedia ? root.matchMedia('(max-width: 575.98px)') : null;
        var sheet = function () { self.root.classList.toggle('pg-picker-sheet', !!(phone && phone.matches)); if (!self.root.classList.contains('pg-picker-sheet')) { self.showFolderList(false); } };
        sheet();
        if (phone && phone.addEventListener) {
            phone.addEventListener('change', sheet);
        }
    };

    Picker.prototype.bind = function () {
        var self = this;

        this.upButton.addEventListener('click', function () { self.goUp(); });
        // The chip lists the open folder beside its siblings; at the top,
        // the folders inside it.
        this.chip.addEventListener('click', function () { self.showFolderList(true, self.parentOf(self.folder) || self.folder); });
        this.back.addEventListener('click', function () { self.showFolderList(false); });
        this.cancel.addEventListener('click', function () { root.pgUI.dialog.hide(self.root); });
        this.choose.addEventListener('click', function () { self.finish(); });
        this.gridButton.addEventListener('click', function () { self.setView('grid'); });
        this.listButton.addEventListener('click', function () { self.setView('list'); });
        this.sortSelect.addEventListener('change', function () { self.setSort(self.sortSelect.value); });
        this.clear.addEventListener('click', function () { self.setQuery(''); self.search.focus(); });

        var timer = null;
        this.search.addEventListener('input', function () {
            self.clear.hidden = self.search.value === '';
            clearTimeout(timer);
            timer = setTimeout(function () { self.setQuery(self.search.value); }, 250);
        });
        this.search.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(timer);
                self.setQuery(self.search.value);
                self.itemsBox.focus();
            }
        });

        // Escape clears a search (or leaves the phone's folder list) before
        // it closes the dialog. Taken on window in the capture phase: a
        // Bootstrap tooltip of a titled control listens on the document in
        // capture and spent the first Escape hiding itself
        // (docs/memory/ui/explorer-keyboard.md).
        this.escape = function (event) {
            if (event.key !== 'Escape' || !self.root.matches('[open]:not(.hiding)') || !self.root.contains(event.target)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            if (self.folderList) {
                self.showFolderList(false);
            } else if (self.query !== '' || self.search.value !== '') {
                self.setQuery('');
                self.search.focus();
            } else {
                root.pgUI.dialog.hide(self.root);
            }
        };
        root.addEventListener('keydown', this.escape, true);

        this.itemsBox.addEventListener('click', function (event) {
            var node = event.target.closest('[data-index]');
            if (node) {
                self.moveTo(Number(node.getAttribute('data-index')));
            }
            var go = event.target.closest('[data-folder-go]');
            if (go) {
                event.stopPropagation();
                self.showFolderList(true, Number(go.getAttribute('data-folder-go')));
            }
        });
        this.itemsBox.addEventListener('dblclick', function (event) {
            var node = event.target.closest('[data-index]');
            if (node) {
                self.activate(Number(node.getAttribute('data-index')));
            }
        });
        this.itemsBox.addEventListener('keydown', function (event) { self.itemsKey(event); });
        this.head.addEventListener('click', function (event) {
            var node = event.target.closest('[data-sort]');
            if (node) {
                var key = node.getAttribute('data-sort');
                self.setSort(key, (self.sort === key) ? (self.dir === 'asc' ? 'desc' : 'asc') : defaultDir(key));
            }
        });
        this.crumbNav.addEventListener('click', function (event) {
            var node = event.target.closest('[data-folder]');
            if (node) {
                event.preventDefault();
                if (self.folderList) {
                    self.showFolderList(true, Number(node.getAttribute('data-folder')));
                } else {
                    self.openFolder(Number(node.getAttribute('data-folder')));
                }
            }
        });
        this.treeList.addEventListener('click', function (event) {
            var row = event.target.closest('[role=treeitem]');
            if (!row) {
                return;
            }
            var id = Number(row.getAttribute('data-folder'));
            if (event.target.closest('.pg-picker-toggle')) {
                self.toggle(id);
            } else {
                self.openFolder(id);
            }
        });
        this.treeList.addEventListener('keydown', function (event) { self.treeKey(event); });
    };

    Picker.prototype.fillSort = function () {
        var keys = [['name', t('Name')], ['modified', t('Modified')], ['size', t('Size')], ['type', t('Type')]];
        if (this.results) {
            keys.unshift(['best', t('Best match')]);
        }
        this.sortSelect.textContent = '';
        for (var i = 0; i < keys.length; i++) {
            var option = el('option', '', keys[i][1]);
            option.value = keys[i][0];
            this.sortSelect.appendChild(option);
        }
        this.sortSelect.value = this.currentSort();
    };

    Picker.prototype.currentSort = function () {
        return (this.results && this.resultSort) ? this.resultSort : this.sort;
    };

    Picker.prototype.setSort = function (key, dir) {
        if (this.results) {
            this.resultSort = key;
            this.resultDir = dir || defaultDir(key);
        } else {
            this.sort = key;
            this.dir = dir || defaultDir(key);
            remember('sort', this.sort);
            remember('dir', this.dir);
        }
        this.sortSelect.value = key;
        this.render(true);
    };

    Picker.prototype.setView = function (view) {
        this.view = view;
        remember('view', view);
        this.render(true);
        this.itemsBox.focus();
    };

    // ── Data ──────────────────────────────────────────────────────────────

    Picker.prototype.loadTree = function (ids) {
        var self = this;
        var wanted = ids.filter(function (id) { return !self.tree[id]; });
        if (!wanted.length) {
            return Promise.resolve();
        }
        return request({ kind: this.kind, request: 'tree', node_ids: wanted }).then(function (body) {
            if (body.types) {
                self.types.textContent = typesLabel(body.types);
            }
            (body.nodes || []).forEach(function (node) { self.tree[node.id] = node.rows || []; });
        });
    };

    Picker.prototype.parentOf = function (id) {
        for (var i = this.crumbs.length - 1; i > 0; i--) {
            if (this.crumbs[i].id === id) {
                return this.crumbs[i - 1].id;
            }
        }
        return 0;
    };

    Picker.prototype.openFolder = function (id) {
        var self = this, seq = ++this.seq;
        this.folder = id;
        this.showFolderList(false);
        if (this.query) {
            this.query = '';
            this.search.value = '';
            this.clear.hidden = true;
            this.results = null;
            this.fillSort();
        }
        this.setStatus(t('Loading…'));
        return Promise.all([
            requestAll({ kind: this.kind, request: 'list', folder: id }, 20),
            this.loadTree([id])
        ]).then(function (out) {
            if (seq !== self.seq) {
                return;
            }
            self.crumbs = out[0].crumbs || [];
            self.files = out[0].items || [];
            self.capped = !!out[0].more;
            return self.loadTree(self.crumbs.map(function (crumb) { return crumb.id; })).then(function () {
                // The way down to the open folder stays unfolded, the open
                // folder too, so its branches show beside its tiles.
                self.crumbs.forEach(function (crumb) { self.open[crumb.id] = true; });
                self.renderTree();
                // The cursor starts on the pick when the folder holds it.
                self.render(true);
            });
        }).catch(function (error) { self.fail(error); });
    };

    Picker.prototype.setQuery = function (text) {
        var self = this, seq = ++this.seq;
        text = String(text || '').trim();
        this.search.value = text;
        this.clear.hidden = text === '';
        if (text === this.query && (text === '' || this.results)) {
            return;
        }
        this.query = text;
        this.onlyHere = false;
        if (text === '') {
            this.results = null;
            this.fillSort();
            this.render(true);
            return;
        }
        this.setStatus(t('Searching…'));
        requestAll({ kind: this.kind, request: 'search', q: text }, 4).then(function (body) {
            if (seq !== self.seq) {
                return;
            }
            self.results = body.items || [];
            self.resultSort = 'best';
            self.resultDir = 'asc';
            self.fillSort();
            self.render(true);
        }).catch(function (error) { self.fail(error); });
    };

    Picker.prototype.fail = function (error) {
        this.itemsBox.textContent = '';
        this.setStatus('');
        var box = el('div', 'pg-picker-empty');
        box.appendChild(icon('bi-exclamation-triangle'));
        box.appendChild(el('p', '', error && error.message ? error.message : t('The list could not be loaded.')));
        this.itemsBox.appendChild(box);
    };

    Picker.prototype.setStatus = function (text) {
        this.status.textContent = text;
    };

    // ── Drawing ───────────────────────────────────────────────────────────

    Picker.prototype.visible = function () {
        if (this.results) {
            var list = this.onlyHere ? underFolder(this.results, this.folder) : this.results;
            return sortItems(list, this.resultSort || 'best', this.resultDir || 'asc', this.collator);
        }
        var folders = (this.tree[this.folder] || []).map(function (row) {
            return { isFolder: true, id: row.id, text: row.name, count: row.count || 0, children: row.children, access: row.access, type: '' };
        });
        return sortItems(folders.concat(this.files), this.sort, this.dir, this.collator);
    };

    Picker.prototype.render = function (resetCursor) {
        var self = this;
        this.items = this.visible();
        this.root.classList.toggle('pg-picker-is-list', this.view === 'list');
        this.gridButton.setAttribute('aria-pressed', String(this.view === 'grid'));
        this.listButton.setAttribute('aria-pressed', String(this.view === 'list'));
        this.gridButton.classList.toggle('active', this.view === 'grid');
        this.listButton.classList.toggle('active', this.view === 'list');
        this.renderCrumbs();
        this.renderStatus();
        this.renderHead();

        if (resetCursor) {
            this.cursor = -1;
            for (var i = 0; this.pick && i < this.items.length; i++) {
                if (!this.items[i].isFolder && this.items[i].value === this.pick.value) {
                    this.cursor = i;
                }
            }
        }

        this.itemsBox.textContent = '';
        this.itemsBox.removeAttribute('aria-activedescendant');

        if (!this.items.length) {
            var box = el('div', 'pg-picker-empty');
            box.appendChild(icon(this.results ? 'bi-search' : 'bi-images'));
            box.appendChild(el('strong', '', this.results ? t('No results found') : t('There is nothing to choose in this folder')));
            this.itemsBox.appendChild(box);
            return;
        }

        var fragment = doc.createDocumentFragment();
        this.items.forEach(function (item, index) { fragment.appendChild(self.itemNode(item, index)); });
        this.itemsBox.appendChild(fragment);
        this.markCursor(false);
    };

    Picker.prototype.itemNode = function (item, index) {
        var node = el('div', 'pg-picker-item' + (item.isFolder ? ' is-folder' : ''));
        node.id = this.itemsBox.id + '_' + index;
        node.setAttribute('role', 'option');
        node.setAttribute('data-index', String(index));
        node.setAttribute('aria-selected', 'false');

        var thumb = el('span', 'pg-picker-thumb');
        if (item.isFolder) {
            thumb.appendChild(icon('bi-folder-fill'));
        } else if (item.thumb) {
            var img = el('img');
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.src = item.thumb;
            img.addEventListener('error', function () { img.replaceWith(icon(fileIcon(item.type))); });
            thumb.appendChild(img);
        } else {
            thumb.appendChild(icon(fileIcon(item.type)));
        }
        if (!item.isFolder && item.access && item.access !== 'public') {
            var lock = el('span', 'pg-picker-lock');
            lock.appendChild(icon('bi-lock-fill'));
            lock.title = accessLabel(item.access);
            thumb.appendChild(lock);
        }
        if (!item.isFolder && this.pick && item.value === this.pick.value) {
            var tick = el('span', 'pg-picker-tick');
            tick.appendChild(icon('bi-check-lg'));
            thumb.appendChild(tick);
        }
        node.appendChild(thumb);

        var body = el('span', 'pg-picker-text');
        body.appendChild(highlighted(item.text, this.results ? this.query : ''));
        body.appendChild(el('span', 'pg-picker-meta', this.metaText(item)));
        node.appendChild(body);

        if (this.view === 'list') {
            node.appendChild(el('span', 'pg-picker-col', item.isFolder ? t('Folder') : String(item.type || '').toUpperCase()));
            node.appendChild(el('span', 'pg-picker-col pg-picker-num', item.isFolder ? t('{n} items', { n: item.count }) : (item.size_label || '')));
            node.appendChild(el('span', 'pg-picker-col', item.isFolder ? '' : formatDate(item.modified)));
            var access = el('span', 'pg-picker-col');
            if (!item.isFolder && item.access) {
                if (item.access !== 'public') {
                    access.appendChild(icon('bi-lock-fill'));
                }
                access.appendChild(doc.createTextNode(' ' + accessLabel(item.access)));
            }
            node.appendChild(access);
        }
        return node;
    };

    Picker.prototype.metaText = function (item) {
        if (item.isFolder) {
            return t('{n} items', { n: item.count });
        }
        if (this.results) {
            return pathText(item.path);
        }
        var parts = [];
        if (item.width && item.height) {
            parts.push(item.width + ' × ' + item.height);
        }
        if (item.size_label) {
            parts.push(item.size_label);
        }
        return parts.join(' · ');
    };

    Picker.prototype.renderHead = function () {
        var self = this;
        this.head.textContent = '';
        this.head.hidden = this.view !== 'list' || !this.items.length;
        [['name', t('Name')], ['type', t('Type')], ['size', t('Size')], ['modified', t('Modified')], ['', t('Access')]].forEach(function (col) {
            var cell = col[0] ? button('pg-picker-col', col[1]) : el('span', 'pg-picker-col', col[1]);
            if (col[0]) {
                var on = self.currentSort() === col[0];
                cell.setAttribute('data-sort', col[0]);
                if (on) {
                    cell.appendChild(icon((self.results ? self.resultDir : self.dir) === 'desc' ? 'bi-arrow-down' : 'bi-arrow-up'));
                    cell.setAttribute('aria-pressed', 'true');
                }
            }
            if (col[0] === 'size') {
                cell.classList.add('pg-picker-num');
            }
            self.head.appendChild(cell);
        });
    };

    Picker.prototype.renderStatus = function () {
        var self = this;
        this.status.textContent = '';
        if (this.results) {
            var summary = el('strong', '', t('{n} results for “{q}”', { n: this.items.length, q: this.query }));
            this.status.appendChild(summary);
            this.status.appendChild(el('span', 'pg-picker-where', this.onlyHere ? '' : t('in every folder')));
            var here = this.crumbs.length ? this.crumbs[this.crumbs.length - 1].name : '';
            if (here) {
                var chip = button('btn btn-sm pg-picker-only' + (this.onlyHere ? ' active' : ''), t('Only in “{folder}”', { folder: here }), 'bi-folder');
                chip.setAttribute('aria-pressed', String(this.onlyHere));
                chip.addEventListener('click', function () { self.onlyHere = !self.onlyHere; self.render(true); });
                this.status.appendChild(chip);
            }
            this.status.appendChild(el('span', 'pg-picker-note', t('Capital letters and Turkish letters do not matter')));
        } else {
            var folders = (this.tree[this.folder] || []).length;
            var text = (this.items.length === 1) ? t('1 item') : t('{n} items', { n: this.items.length });
            if (folders && this.files.length) {
                text += ' · ' + t('{folders} folders, {files} files', { folders: folders, files: this.files.length });
            }
            this.status.appendChild(el('span', '', text));
            if (this.capped) {
                this.status.appendChild(el('span', 'pg-picker-note', t('Only the first 2,000 are shown; search to find the rest.')));
            }
        }
        this.hint.textContent = this.results ? t('Esc clears the search') : (this.view === 'list' ? t('A column heading sorts') : t('Double-click or Enter chooses'));
    };

    Picker.prototype.renderCrumbs = function () {
        var self = this;
        var crumbs = this.folderList ? this.listCrumbs() : this.crumbs;
        this.crumbNav.textContent = '';
        if (this.results && !this.folderList) {
            this.crumbNav.appendChild(el('strong', '', t('Search results')));
        } else {
            crumbs.forEach(function (crumb, index) {
                if (index) {
                    self.crumbNav.appendChild(icon('bi-chevron-right'));
                }
                var link = el(index === crumbs.length - 1 && !self.folderList ? 'strong' : 'a', '', crumb.name);
                if (link.tagName === 'A') {
                    link.href = '#';
                    link.setAttribute('data-folder', String(crumb.id));
                } else {
                    link.setAttribute('aria-current', 'location');
                }
                self.crumbNav.appendChild(link);
            });
        }
        this.upButton.disabled = !this.results && this.crumbs.length < 2;
        var here = this.crumbs.length ? this.crumbs[this.crumbs.length - 1].name : t('Files');
        this.chip.textContent = '';
        this.chip.appendChild(icon('bi-folder-fill'));
        this.chip.appendChild(el('span', 'pg-btn-label', here));
        this.chip.appendChild(icon('bi-chevron-down'));
    };

    Picker.prototype.renderPick = function () {
        this.pickBox.textContent = '';
        this.choose.disabled = !this.pick;
        if (!this.pick) {
            this.pickBox.appendChild(el('span', 'pg-picker-meta', t('Nothing chosen yet')));
            return;
        }
        var thumb = el('span', 'pg-picker-thumb');
        if (this.pick.thumb) {
            var img = el('img');
            img.alt = '';
            img.src = this.pick.thumb;
            thumb.appendChild(img);
        } else {
            thumb.appendChild(icon(fileIcon(this.pick.type)));
        }
        var text = el('span', 'pg-picker-text');
        text.appendChild(el('strong', 'pg-picker-name', this.pick.text));
        var meta = [pathText(this.pick.path)];
        if (this.pick.width && this.pick.height) {
            meta.push(this.pick.width + ' × ' + this.pick.height);
        }
        meta.push(this.pick.size_label || '');
        text.appendChild(el('span', 'pg-picker-meta', meta.filter(Boolean).join(' · ')));
        this.pickBox.appendChild(thumb);
        this.pickBox.appendChild(text);
    };

    Picker.prototype.markCursor = function (scroll) {
        var nodes = this.itemsBox.querySelectorAll('[data-index]');
        for (var i = 0; i < nodes.length; i++) {
            var item = this.items[i];
            var on = i === this.cursor;
            nodes[i].classList.toggle('is-cursor', on);
            nodes[i].setAttribute('aria-selected', String(!!(item && !item.isFolder && this.pick && item.value === this.pick.value)));
            if (on) {
                this.itemsBox.setAttribute('aria-activedescendant', nodes[i].id);
                if (scroll && nodes[i].scrollIntoView) {
                    nodes[i].scrollIntoView({ block: 'nearest' });
                }
            }
        }
    };

    // ── The tree ──────────────────────────────────────────────────────────

    Picker.prototype.renderTree = function () {
        var self = this;
        var focused = doc.activeElement && this.treeList.contains(doc.activeElement) ? doc.activeElement.getAttribute('data-folder') : null;
        this.treeList.textContent = '';
        function branch(parent, list, level) {
            (self.tree[parent] || []).forEach(function (row) {
                var li = el('li');
                li.setAttribute('role', 'treeitem');
                li.setAttribute('data-folder', String(row.id));
                li.setAttribute('aria-level', String(level));
                li.setAttribute('aria-selected', String(row.id === self.folder));
                li.tabIndex = -1;
                var line = el('div', 'pg-picker-row' + (row.id === self.folder ? ' is-open' : ''));
                var toggle = el('span', 'pg-picker-toggle' + (self.open[row.id] ? ' open' : ''));
                if (row.children) {
                    li.setAttribute('aria-expanded', String(!!self.open[row.id]));
                    toggle.appendChild(icon('bi-chevron-right'));
                }
                line.appendChild(toggle);
                line.appendChild(icon('bi-folder-fill pg-picker-folder'));
                line.appendChild(el('span', 'pg-picker-label', row.name));
                line.appendChild(el('span', 'pg-picker-count', row.count ? String(row.count) : '0'));
                li.appendChild(line);
                if (row.children && self.open[row.id] && self.tree[row.id]) {
                    var ul = el('ul');
                    ul.setAttribute('role', 'group');
                    branch(row.id, ul, level + 1);
                    li.appendChild(ul);
                }
                list.appendChild(li);
            });
        }
        branch(0, this.treeList, 1);
        var rows = this.treeRows();
        var current = this.treeList.querySelector('[aria-selected=true]') || rows[0];
        if (current) {
            current.tabIndex = 0;
        }
        if (focused) {
            var again = this.treeList.querySelector('[data-folder="' + focused + '"]');
            if (again) {
                rows.forEach(function (row) { row.tabIndex = -1; });
                again.tabIndex = 0;
                again.focus();
            }
        }
    };

    Picker.prototype.treeRows = function () {
        return Array.prototype.slice.call(this.treeList.querySelectorAll('[role=treeitem]'));
    };

    Picker.prototype.toggle = function (id, state) {
        var self = this;
        var open = state === undefined ? !this.open[id] : state;
        this.open[id] = open;
        (open ? this.loadTree([id]) : Promise.resolve()).then(function () { self.renderTree(); }).catch(function (error) { self.fail(error); });
    };

    Picker.prototype.treeKey = function (event) {
        var rows = this.treeRows();
        var row = event.target.closest('[role=treeitem]');
        var at = rows.indexOf(row);
        var id = row ? Number(row.getAttribute('data-folder')) : 0;
        var target = null;
        switch (event.key) {
            case 'ArrowDown': target = rows[at + 1]; break;
            case 'ArrowUp': target = rows[at - 1]; break;
            case 'Home': target = rows[0]; break;
            case 'End': target = rows[rows.length - 1]; break;
            case 'ArrowRight':
                if (row && row.getAttribute('aria-expanded') === 'false') {
                    this.toggle(id, true);
                } else {
                    target = row && row.querySelector('[role=treeitem]');
                }
                break;
            case 'ArrowLeft':
                if (row && row.getAttribute('aria-expanded') === 'true') {
                    this.toggle(id, false);
                } else {
                    target = row && row.parentNode.closest('[role=treeitem]');
                }
                break;
            case 'Enter':
            case ' ':
                this.openFolder(id);
                break;
            default:
                return;
        }
        event.preventDefault();
        if (target) {
            rows.forEach(function (one) { one.tabIndex = -1; });
            target.tabIndex = 0;
            target.focus();
        }
    };

    // ── Moving, picking ───────────────────────────────────────────────────

    Picker.prototype.columns = function () {
        if (this.view === 'list') {
            return 1;
        }
        var nodes = this.itemsBox.querySelectorAll('[data-index]');
        if (nodes.length < 2) {
            return 1;
        }
        var top = nodes[0].offsetTop, n = 1;
        while (n < nodes.length && nodes[n].offsetTop === top) {
            n++;
        }
        return n;
    };

    // The cursor on a file is the pick, as the tile drawn chosen in the
    // screens; a folder only takes the cursor. A pick keeps the folder it
    // was made in for the footer.
    Picker.prototype.moveTo = function (index) {
        if (index < 0 || index >= this.items.length) {
            return;
        }
        this.cursor = index;
        var item = this.items[index];
        if (!item.isFolder) {
            this.pick = item.path ? item : Object.assign({}, item, { path: this.crumbs.slice() });
            this.renderPick();
            this.refreshTicks();
        }
        this.markCursor(true);
    };

    Picker.prototype.refreshTicks = function () {
        var self = this;
        var nodes = this.itemsBox.querySelectorAll('[data-index]');
        for (var i = 0; i < nodes.length; i++) {
            var item = this.items[i];
            var tick = nodes[i].querySelector('.pg-picker-tick');
            var on = !!(item && !item.isFolder && self.pick && item.value === self.pick.value);
            if (on && !tick) {
                tick = el('span', 'pg-picker-tick');
                tick.appendChild(icon('bi-check-lg'));
                nodes[i].querySelector('.pg-picker-thumb').appendChild(tick);
            } else if (!on && tick) {
                tick.remove();
            }
        }
    };

    Picker.prototype.activate = function (index) {
        var item = this.items[index];
        if (!item) {
            return;
        }
        if (item.isFolder) {
            this.openFolder(item.id);
            this.itemsBox.focus();
            return;
        }
        this.pick = item;
        this.finish();
    };

    Picker.prototype.goUp = function () {
        if (this.results) {
            this.setQuery('');
            return;
        }
        if (this.crumbs.length > 1) {
            this.openFolder(this.crumbs[this.crumbs.length - 2].id);
        }
    };

    Picker.prototype.itemsKey = function (event) {
        var key = event.key;
        if (event.altKey || event.ctrlKey || event.metaKey) {
            if (event.altKey && key === 'ArrowUp') {
                event.preventDefault();
                this.goUp();
            }
            return;
        }
        if (key === 'Enter') {
            event.preventDefault();
            this.activate(this.cursor);
            return;
        }
        if (key === 'Backspace') {
            event.preventDefault();
            this.goUp();
            return;
        }
        var next = stepIndex(this.cursor, this.items.length, this.columns(), key);
        if (next !== this.cursor || /^(Arrow|Home|End|Page)/.test(key)) {
            event.preventDefault();
            this.moveTo(next);
            return;
        }
        if (key.length === 1 && key !== ' ') {
            var self = this;
            clearTimeout(this.typedTimer);
            this.typed += key;
            this.typedTimer = setTimeout(function () { self.typed = ''; }, 700);
            var found = typeAheadIndex(this.items, this.typed, this.typed.length > 1 ? this.cursor : this.cursor + 1);
            if (found !== -1) {
                this.moveTo(found);
            }
        } else if (key === ' ') {
            event.preventDefault();
            this.moveTo(Math.max(0, this.cursor));
        }
    };

    // ── The phone's folder list ───────────────────────────────────────────

    // The folders down to the listed one, read off the branches loaded.
    Picker.prototype.listCrumbs = function () {
        var self = this, parent = {}, out = [], seen = {}, id = this.listFolder;
        Object.keys(this.tree).forEach(function (node) {
            self.tree[node].forEach(function (row) { parent[row.id] = { id: Number(node), name: row.name }; });
        });
        while (id && parent[id] && !seen[id]) {
            seen[id] = true;
            out.unshift({ id: id, name: parent[id].name });
            id = parent[id].id;
        }
        return out;
    };

    Picker.prototype.showFolderList = function (on, parent) {
        var self = this;
        if (!this.root) {
            return;
        }
        var was = this.folderList;
        this.folderList = !!on;
        this.root.classList.toggle('pg-picker-folders', this.folderList);
        this.back.hidden = !this.folderList;
        this.titleNode.textContent = this.folderList ? t('Choose a folder') : (this.opts.title || t('Choose a file'));
        if (!this.folderList) {
            if (was && this.items) {
                this.render(false);
            }
            return;
        }
        this.listFolder = parent || 0;
        this.loadTree([this.listFolder]).then(function () {
            self.renderCrumbs();
            self.status.textContent = '';
            self.head.hidden = true;
            self.itemsBox.textContent = '';
            self.hint.textContent = t('Tapping a folder shows its files');
            (self.tree[self.listFolder] || []).forEach(function (folder, index) {
                var line = el('div', 'pg-picker-item pg-picker-folder-row' + (folder.id === self.folder ? ' is-cursor' : ''));
                line.setAttribute('role', 'option');
                line.setAttribute('aria-selected', String(folder.id === self.folder));
                line.id = self.itemsBox.id + '_f' + index;
                line.appendChild(icon('bi-folder-fill pg-picker-folder'));
                line.appendChild(el('span', 'pg-picker-label', folder.name));
                line.appendChild(el('span', 'pg-picker-count', String(folder.count || 0)));
                line.addEventListener('click', function (event) {
                    if (!event.target.closest('[data-folder-go]')) {
                        self.openFolder(folder.id);
                    }
                });
                if (folder.children) {
                    var go = button('btn btn-icon', '', 'bi-chevron-right', t('Folders in {folder}', { folder: folder.name }));
                    go.setAttribute('data-folder-go', String(folder.id));
                    line.appendChild(go);
                }
                self.itemsBox.appendChild(line);
            });
        }).catch(function (error) { self.fail(error); });
    };

    // ── Opening and closing ───────────────────────────────────────────────

    Picker.prototype.finish = function () {
        if (!this.pick) {
            return;
        }
        this.result = this.pick;
        root.pgUI.dialog.hide(this.root);
    };

    Picker.prototype.start = function () {
        var self = this;
        var value = this.opts.value;
        var first = (value !== undefined && value !== null && String(value) !== '')
            ? request({ kind: this.kind, request: 'resolve', values: [String(value)] }).then(function (body) {
                var item = (body.items || [])[0];
                return (item && item.state === 'ok') ? item : null;
            }).catch(function () { return null; })
            : Promise.resolve(null);

        return Promise.all([first, this.loadTree([0])]).then(function (out) {
            var item = out[0];
            if (item) {
                self.pick = item;
                self.renderPick();
                return self.openFolder(item.folder);
            }
            var top = self.tree[0] || [];
            if (!top.length) {
                self.render(true);
                return null;
            }
            return self.openFolder(top[0].id);
        }).then(function () {
            self.itemsBox.focus();
        }).catch(function (error) { self.fail(error); });
    };

    function open(opts) {
        return new Promise(function (resolve) {
            var picker = new Picker(opts);
            var before = doc.activeElement;
            picker.build();
            root.pgUI.on(picker.root, 'dialog', 'hidden', function () {
                var result = picker.result;
                root.removeEventListener('keydown', picker.escape, true);
                picker.root.remove();
                if (before && before.focus && doc.contains(before)) {
                    before.focus();
                }
                resolve(result);
            }, { once: true });
            root.pgUI.dialog.show(picker.root).then(function () { picker.start(); });
        });
    }

    // A Browse button writes the pick into its field: a hooked select gets
    // the option if it lacks it, then the value through pgCombobox, which
    // fires the change the screen listens to.
    function writeField(field, item) {
        var value = String(item.value);
        if (field.tagName === 'SELECT') {
            var found = Array.prototype.some.call(field.options, function (option) { return option.value === value; });
            if (!found) {
                var option = el('option', '', item.text);
                option.value = value;
                field.appendChild(option);
            }
            if (root.pgCombobox) {
                root.pgCombobox.set(field, value);
                return;
            }
        }
        field.value = value;
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    doc.addEventListener('click', function (event) {
        var trigger = event.target.closest && event.target.closest('[data-pg-picker-browse]');
        if (!trigger || !root.pgUI) {
            return;
        }
        event.preventDefault();
        var field = doc.getElementById(trigger.getAttribute('data-pg-picker-for') || '');
        open({
            kind: trigger.getAttribute('data-pg-picker-kind'),
            title: trigger.getAttribute('data-pg-picker-title') || undefined,
            value: field ? field.value : ''
        }).then(function (item) {
            if (item && field) {
                writeField(field, item);
            }
        });
    });

    root.pgPicker = { open: open, pure: pure };
})(typeof window !== 'undefined' ? window : this);
