/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker dialog (window.pgPicker): one Bootstrap 6 dialog for choosing a
 * file of a kind of pg_picker_kinds() (includes/fn/picker.php), read from the
 * picker.php endpoint. A folder tree, the open folder as tiles with pictures
 * or as a list, a folded name search across the folders, sorting, one pick,
 * the keyboard as in the File Manager; below 576 px a bottom sheet whose
 * folder chip opens a folder list in place of the tree. Files dropped on the
 * open folder or chosen with Upload are stored there (picker.php, request
 * upload) and the first becomes the pick; Recent lists the person's last
 * picks of the kind, kept in this browser.
 *
 * A page kind (config page_kinds) lists pages as rows with their address,
 * type and date, a chip per page type group, and pages in an archived folder
 * or in the Recycle Bin marked; neither can be chosen. A caller may hand in
 * entries of its own (extra: [{value, text, type, type_group, type_label, note}], under
 * extraLabel), listed beside the folders and found by the search; they are
 * never kept in Recent.
 *
 *   pgPicker.open({kind, value, title, query}) -> Promise of the picked
 *   item ({value, text, ...}) or null, settled once the dialog has closed, so
 *   the caller may focus or edit the page (docs/memory/ui/dialog-result-after-close.md);
 *   a query opens it on that search.
 *   pgPicker.open({kind, value: [values], multiple: true}) -> Promise of the
 *   picked items in the order they were picked, or null.
 *
 * A button with data-pg-picker-browse and data-pg-picker-kind opens it for
 * the field named by data-pg-picker-for and writes the pick there.
 *
 * The field (pg_picker_field(), [data-pg-picker-field]) is the second face:
 * a combobox showing the chosen value with its picture, searching the kind
 * as one types (a list of up to eight with pictures, the last row opening
 * Browse on the text, Alt+Down too), clear and Browse beside it; below
 * 576 px a tap opens the search in a half sheet. Its listeners sit on the
 * document, so a field in injected markup works as printed; a pick fires
 * change on its hidden input.
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
                // A page's type goes by the label shown, not its inner key.
                out = compareText(String(a.item.type_label || a.item.type || ''), String(b.item.type_label || b.item.type || ''), collator);
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

    // The line under a chosen name in the field: a page's address, the
    // folder, the picture size and the file size (pg_picker_field_meta()).
    function fieldMeta(item) {
        item = item || {};
        var parts = [item.address || '', pathText(item.path)];
        if (item.width && item.height) {
            parts.push(item.width + ' × ' + item.height);
        }
        parts.push(item.size_label || '');
        return parts.filter(Boolean).join(' · ');
    }

    // The text key of the field's result count: a full page of answers
    // says there are more.
    function resultsKey(count, more) {
        if (more) {
            return '{n}+ results · in every folder';
        }
        return count === 1 ? '1 result · in every folder' : '{n} results · in every folder';
    }

    function fileIcon(type) {
        var t = String(type || '').toLowerCase();
        var map = { pdf: 'pdf', zip: 'zip', rar: 'zip', doc: 'word', docx: 'word', xls: 'excel', xlsx: 'excel', csv: 'spreadsheet', ppt: 'ppt', pptx: 'ppt', txt: 'text', md: 'text', mp3: 'music', wav: 'music', ogg: 'music', mp4: 'play', webm: 'play', mov: 'play', js: 'code', css: 'code', html: 'code', json: 'code', xml: 'code' };
        if (/^(jpe?g|png|gif|webp|avif|svg|bmp)$/.test(t)) {
            return 'bi-file-earmark-image';
        }
        return map[t] ? 'bi-file-earmark-' + map[t] : 'bi-file-earmark';
    }

    function extensionOf(name) {
        var match = /\.([^.\/\\]+)$/.exec(String(name || ''));
        return match ? match[1].toLowerCase() : '';
    }

    // Why a file is refused before it is sent: 'type' (not one of the
    // kind's types), 'size' (over the largest file the server takes) or ''.
    function uploadRefusal(file, types, max) {
        types = (types || []).map(function (type) { return String(type).toLowerCase(); });
        if (types.length && types.indexOf(extensionOf(file && file.name)) === -1) {
            return 'type';
        }
        if (max > 0 && Number(file && file.size) > max) {
            return 'size';
        }
        return '';
    }

    // The Recent list with an item put first: each value once, at most
    // `max`, and only what finds the item again (resolve draws the rest).
    function addRecent(list, item, max) {
        var entry = {};
        ['value', 'text', 'type', 'folder'].forEach(function (key) {
            if (item && item[key] !== undefined) {
                entry[key] = item[key];
            }
        });
        return [entry].concat((Array.isArray(list) ? list : []).filter(function (old) {
            return old && old.value !== undefined && String(old.value) !== String(entry.value);
        })).slice(0, Math.max(1, max || 12));
    }

    function pickIndex(picks, item) {
        for (var i = 0; i < picks.length; i++) {
            if (String(picks[i].value) === String(item.value)) {
                return i;
            }
        }
        return -1;
    }

    // Several picks after a click: the item added at the end or taken out,
    // the others kept in the order they were picked (the order stored).
    function togglePick(picks, item) {
        var at = pickIndex(picks, item);
        return at === -1 ? picks.concat([item]) : picks.slice(0, at).concat(picks.slice(at + 1));
    }

    // Whether an item may be chosen: never a folder, nor a page marked
    // archived or binned (pg_picker_page_mark()).
    function pickable(item) {
        return !!item && !item.isFolder && !item.mark;
    }

    // Several picks after Shift: the files between two places added in the
    // order they are drawn; folders, marked pages and items already picked
    // are passed over.
    function rangePick(picks, items, from, to) {
        var out = picks.slice();
        var low = Math.max(0, Math.min(from, to)), high = Math.min(items.length - 1, Math.max(from, to));
        for (var i = low; i <= high; i++) {
            if (pickable(items[i]) && pickIndex(out, items[i]) === -1) {
                out.push(items[i]);
            }
        }
        return out;
    }

    // A size of a file not sent yet, as the panel writes sizes: 412 KB, 1.1 MB.
    function formatSize(bytes, lang) {
        var units = ['B', 'KB', 'MB', 'GB'], unit = 0;
        bytes = Math.max(0, Number(bytes) || 0);
        while (bytes >= 1024 && unit < units.length - 1) {
            bytes /= 1024;
            unit++;
        }
        var digits = unit >= 2 ? 1 : 0, text;
        try {
            text = new Intl.NumberFormat(lang || undefined, { maximumFractionDigits: digits }).format(bytes);
        } catch (e) {
            text = bytes.toFixed(digits);
        }
        return text + ' ' + units[unit];
    }

    // The type chips over a list of pages: each group a page in it belongs
    // to, in the set order, and the chosen one though nothing in view has it.
    function groupChips(items, order, active) {
        var present = {};
        (items || []).forEach(function (item) {
            if (item && !item.isFolder && item.type_group) {
                present[item.type_group] = true;
            }
        });
        if (active) {
            present[active] = true;
        }
        return (order || []).filter(function (key) { return present[key]; });
    }

    // A list kept to one chip's pages; the folders stay, to be opened.
    function inGroup(items, group) {
        return (items || []).filter(function (item) { return !group || item.isFolder || item.type_group === group; });
    }

    // The entries a caller hands in beside the pages (the designer's unsaved
    // tabs, `tab:<key>`; the editor's short links): each needs a value and a
    // name; the value is kept as text, as a field stores it. A type_label
    // names a type the page groups do not have.
    function extraItems(list) {
        return (Array.isArray(list) ? list : []).filter(function (entry) {
            return entry && entry.value !== undefined && entry.value !== null && String(entry.value) !== '' && String(entry.text || '') !== '';
        }).map(function (entry) {
            var item = {
                value: String(entry.value), text: String(entry.text), type: String(entry.type || ''),
                type_group: String(entry.type_group || 'standard'), note: String(entry.note || ''), extra: true
            };
            if (entry.type_label) {
                item.type_label = String(entry.type_label);
            }
            return item;
        });
    }

    // The entries whose folded name holds the typed text, as the server
    // matches the pages.
    function extraMatches(items, query) {
        var q = foldText(String(query || '').trim());
        return q ? (items || []).filter(function (item) { return foldText(item.text).indexOf(q) !== -1; }) : [];
    }

    var pure = {
        foldText: foldText, highlightParts: highlightParts, defaultDir: defaultDir, sortItems: sortItems,
        stepIndex: stepIndex, underFolder: underFolder, typeAheadIndex: typeAheadIndex, typesLabel: typesLabel,
        fill: fill, pathText: pathText, fileIcon: fileIcon, makeCollator: makeCollator,
        extensionOf: extensionOf, uploadRefusal: uploadRefusal, addRecent: addRecent, togglePick: togglePick,
        rangePick: rangePick, formatSize: formatSize, pickable: pickable, groupChips: groupChips, inGroup: inGroup,
        extraItems: extraItems, extraMatches: extraMatches, fieldMeta: fieldMeta, resultsKey: resultsKey
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

    function queryString(params) {
        var query = [];
        Object.keys(params).forEach(function (name) {
            [].concat(params[name]).forEach(function (value) {
                query.push(encodeURIComponent(Array.isArray(params[name]) ? name + '[]' : name) + '=' + encodeURIComponent(value));
            });
        });
        return query.join('&');
    }

    function request(params) {
        return root.fetch(conf().endpoint + '?' + queryString(params), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
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

    function hasFiles(event) {
        var types = event.dataTransfer && event.dataTransfer.types;
        return !!types && Array.prototype.indexOf.call(types, 'Files') !== -1;
    }

    function accessLabel(access) {
        var labels = { 'public': t('Public'), 'private': t('Private'), guest: t('Guest'), registration: t('Registration'), membership: t('Membership') };
        return labels[access] || '';
    }

    function markLabel(mark) {
        return mark === 'binned' ? t('In the Recycle Bin') : (mark === 'archived' ? t('Archived') : '');
    }

    // The page type groups in chip order, and each one's label.
    function groupOrder() {
        return (conf().page_groups || []).map(function (group) { return group.key; });
    }

    // A render rebuilds the chips and the column heads: the button pressed
    // gets the focus back, or it would fall to the page under the dialog.
    function refocus(box, attr, value) {
        var nodes = box.querySelectorAll('[' + attr + ']');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].getAttribute(attr) === value && !box.hidden) {
                nodes[i].focus();
                return;
            }
        }
    }

    function groupLabel(key) {
        var groups = conf().page_groups || [];
        for (var i = 0; i < groups.length; i++) {
            if (groups[i].key === key) {
                return groups[i].label;
            }
        }
        return '';
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
        this.multiple = !!this.opts.multiple;
        this.picks = [];
        this.anchor = -1;
        this.recentOn = false;
        this.recent = [];
        this.upload = null;
        this.uploading = false;
        this.typesList = [];
        this.dragDepth = 0;
        this.objectUrls = [];
        // Pages are rows (address, type, date), never tiles; their sort has
        // no size.
        this.pages = (conf().page_kinds || []).indexOf(this.kind) !== -1;
        this.extras = this.pages ? extraItems(this.opts.extra) : [];
        this.extraOn = false;
        this.group = '';
        this.chipKeys = [];
        if (this.pages) {
            this.view = 'list';
            if (this.sort === 'size') {
                this.sort = 'name';
                this.dir = defaultDir('name');
            }
        }
    }

    Picker.prototype.defaultTitle = function () {
        return this.opts.title || (this.pages ? t('Choose a page') : t('Choose a file'));
    };

    Picker.prototype.build = function () {
        var self = this;
        var parts = root.pgUI.dialog.build({ size: 'xl', className: 'pg-picker' + (this.pages ? ' pg-picker-is-pages' : ''), closeLabel: t('Close'), title: this.defaultTitle() });

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
        views.hidden = this.pages;

        this.uploadButton = button('btn btn-outline theme-primary pg-picker-upload', t('Upload'), 'bi-upload');
        this.uploadButton.hidden = true;
        this.fileInput = el('input', 'pg-picker-file');
        this.fileInput.type = 'file';
        this.fileInput.multiple = true;
        this.fileInput.hidden = true;
        this.fileInput.tabIndex = -1;

        [this.upButton, this.crumbNav, this.chip, search, sortBox, views, this.uploadButton, this.fileInput].forEach(function (node) { bar.appendChild(node); });

        var main = el('div', 'pg-picker-main');
        this.treeBox = el('nav', 'pg-picker-tree');
        this.treeBox.setAttribute('aria-label', t('Folders'));
        this.recentRow = button('pg-picker-row pg-picker-recent', t('Recently used'), 'bi-clock-history');
        this.treeBox.appendChild(this.recentRow);
        this.extraRow = button('pg-picker-row pg-picker-recent', this.opts.extraLabel || t('Not saved yet'), 'bi-file-earmark-plus');
        this.extraRow.hidden = !this.extras.length;
        this.treeBox.appendChild(this.extraRow);
        this.treeBox.appendChild(el('div', 'pg-picker-section', t('Folders')));
        this.treeList = el('ul');
        this.treeList.setAttribute('role', 'tree');
        this.treeList.setAttribute('aria-label', t('Folders'));
        this.treeBox.appendChild(this.treeList);

        var pane = el('section', 'pg-picker-pane');
        this.status = el('div', 'pg-picker-status');
        this.status.setAttribute('aria-live', 'polite');
        this.chipBox = el('div', 'pg-picker-chips');
        this.chipBox.setAttribute('role', 'group');
        this.chipBox.setAttribute('aria-label', t('Page types'));
        this.chipBox.hidden = true;
        this.head = el('div', 'pg-picker-head');
        this.itemsBox = el('div', 'pg-picker-items');
        this.itemsBox.id = 'pg_picker_items_' + Date.now();
        this.itemsBox.tabIndex = 0;
        this.itemsBox.setAttribute('role', 'listbox');
        this.itemsBox.setAttribute('aria-label', this.pages ? t('Pages') : t('Files'));
        if (this.multiple) {
            this.itemsBox.setAttribute('aria-multiselectable', 'true');
        }
        this.dropBox = el('div', 'pg-picker-drop');
        this.dropBox.hidden = true;
        this.uploadsBox = el('div', 'pg-picker-uploads');
        this.uploadsBox.hidden = true;
        this.uploadsBox.setAttribute('aria-live', 'polite');
        this.pane = pane;
        pane.appendChild(this.status);
        pane.appendChild(this.chipBox);
        pane.appendChild(this.head);
        pane.appendChild(this.itemsBox);
        pane.appendChild(this.dropBox);
        pane.appendChild(this.uploadsBox);
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
        this.uploadButton.addEventListener('click', function () { self.fileInput.click(); });
        this.fileInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(self.fileInput.files || []);
            self.fileInput.value = '';
            self.uploadFiles(files);
        });
        this.recentRow.addEventListener('click', function () { self.showRecent(); });
        this.extraRow.addEventListener('click', function () { self.showExtras(); });
        // A chip keeps the list to its pages; the chosen chip again, or All,
        // shows every page.
        this.chipBox.addEventListener('click', function (event) {
            var chip = event.target.closest('[data-group]');
            if (chip) {
                var key = chip.getAttribute('data-group');
                self.group = (key === self.group) ? '' : key;
                self.render(true);
                refocus(self.chipBox, 'data-group', key);
            }
        });

        // Files dragged over the pane are offered to the open folder; the
        // overlay names it, or says why it takes none. A drop elsewhere in
        // the dialog is kept from opening the file in the tab.
        this.pane.addEventListener('dragenter', function (event) {
            if (!hasFiles(event) || !self.upload || self.folderList) {
                return;
            }
            event.preventDefault();
            self.dragDepth++;
            self.showDrop(event);
        });
        this.pane.addEventListener('dragover', function (event) {
            if (!hasFiles(event) || !self.upload || self.folderList) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = self.canUpload() ? 'copy' : 'none';
        });
        this.pane.addEventListener('dragleave', function () {
            self.dragDepth = Math.max(0, self.dragDepth - 1);
            if (!self.dragDepth) {
                self.dropBox.hidden = true;
            }
        });
        this.pane.addEventListener('drop', function (event) {
            if (!hasFiles(event)) {
                return;
            }
            event.preventDefault();
            self.dragDepth = 0;
            self.dropBox.hidden = true;
            if (self.canUpload()) {
                self.uploadFiles(Array.prototype.slice.call(event.dataTransfer.files || []));
            }
        });
        ['dragover', 'drop'].forEach(function (type) {
            self.root.addEventListener(type, function (event) {
                if (hasFiles(event) && !self.pane.contains(event.target)) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'none';
                }
            });
        });

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
                var index = Number(node.getAttribute('data-index'));
                if (self.multiple && event.shiftKey) {
                    self.moveTo(index, true);
                } else {
                    self.moveTo(index);
                    if (self.multiple) {
                        self.togglePickAt(index);
                    }
                }
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
                refocus(self.head, 'data-sort', key);
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
        var keys = this.pages ? [['name', t('Name')], ['modified', t('Modified')], ['type', t('Type')]]
            : [['name', t('Name')], ['modified', t('Modified')], ['size', t('Size')], ['type', t('Type')]];
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
                self.typesList = body.types;
                self.types.textContent = self.pages ? body.types.join(', ') : typesLabel(body.types);
                self.fileInput.accept = body.types.map(function (type) { return '.' + type; }).join(',');
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
        this.recentOn = false;
        this.extraOn = false;
        this.showFolderList(false);
        this.leaveSearch();
        this.setStatus(t('Loading…'));
        return Promise.all([
            requestAll({ kind: this.kind, request: 'list', folder: id }, 20),
            this.loadTree([id])
        ]).then(function (out) {
            if (seq !== self.seq) {
                return;
            }
            self.crumbs = out[0].crumbs || [];
            self.files = (out[0].items || []).concat(out[0].binned || []);
            self.capped = !!out[0].more;
            self.upload = out[0].upload || null;
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

    Picker.prototype.leaveSearch = function () {
        if (this.query) {
            this.query = '';
            this.search.value = '';
            this.clear.hidden = true;
            this.results = null;
            this.fillSort();
        }
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
        if (text !== '' && (this.recentOn || this.extraOn)) {
            this.recentOn = false;
            this.extraOn = false;
            this.renderTree();
        }
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
            self.results = extraMatches(self.extras, text).concat(body.items || []);
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

    // What the pane lists, kept to the chosen chip; the chips are those of
    // the whole list.
    Picker.prototype.visible = function () {
        var list = this.listed();
        this.chipKeys = this.pages ? groupChips(list, groupOrder(), this.group) : [];
        return this.pages ? inGroup(list, this.group) : list;
    };

    Picker.prototype.listed = function () {
        if (this.extraOn) {
            return this.extras.slice();
        }
        if (this.recentOn) {
            return this.recent.slice();
        }
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
        this.renderChips();
        this.renderHead();
        this.renderUpload();
        this.sortSelect.disabled = this.recentOn || this.extraOn;

        if (resetCursor) {
            var first = this.multiple ? this.picks[0] : this.pick;
            this.cursor = -1;
            for (var i = 0; first && i < this.items.length; i++) {
                if (pickable(this.items[i]) && String(this.items[i].value) === String(first.value)) {
                    this.cursor = i;
                    break;
                }
            }
            this.anchor = this.cursor;
        }

        this.itemsBox.textContent = '';
        this.itemsBox.removeAttribute('aria-activedescendant');

        if (!this.items.length) {
            this.itemsBox.appendChild(this.emptyNode());
            return;
        }

        var fragment = doc.createDocumentFragment();
        this.items.forEach(function (item, index) { fragment.appendChild(self.itemNode(item, index)); });
        this.itemsBox.appendChild(fragment);
        this.markCursor(false);
    };

    Picker.prototype.itemNode = function (item, index) {
        var node = el('div', 'pg-picker-item' + (item.isFolder ? ' is-folder' : '') + (item.mark ? ' is-marked' : ''));
        node.id = this.itemsBox.id + '_' + index;
        node.setAttribute('role', 'option');
        node.setAttribute('data-index', String(index));
        node.setAttribute('aria-selected', 'false');
        if (item.mark) {
            node.setAttribute('aria-disabled', 'true');
        }

        var thumb = el('span', 'pg-picker-thumb');
        if (item.isFolder) {
            thumb.appendChild(icon('bi-folder-fill'));
        } else if (this.pages) {
            thumb.appendChild(icon(item.extra ? 'bi-file-earmark-plus' : 'bi-file-earmark-text'));
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
        if (this.isPicked(item)) {
            var tick = el('span', 'pg-picker-tick');
            tick.appendChild(icon('bi-check-lg'));
            thumb.appendChild(tick);
        }
        node.appendChild(thumb);

        var body = el('span', 'pg-picker-text');
        var name = highlighted(item.text, this.results ? this.query : '');
        if (item.mark) {
            var line = el('span', 'pg-picker-name-line');
            line.appendChild(name);
            line.appendChild(el('span', 'pg-picker-mark is-' + item.mark, markLabel(item.mark)));
            body.appendChild(line);
        } else {
            body.appendChild(name);
        }
        body.appendChild(el('span', 'pg-picker-meta', this.metaText(item)));
        node.appendChild(body);

        if (this.pages) {
            node.appendChild(el('span', 'pg-picker-col', item.isFolder ? t('{n} items', { n: item.count }) : (item.address || item.note || '')));
            node.appendChild(el('span', 'pg-picker-col', item.isFolder ? t('Folder') : (item.type_label || groupLabel(item.type_group))));
            node.appendChild(el('span', 'pg-picker-col', item.isFolder ? '' : formatDate(item.modified)));
        } else if (this.view === 'list') {
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
        if (item.extra) {
            return item.note;
        }
        if (this.results || this.recentOn) {
            return pathText(item.path);
        }
        if (this.pages) {
            return [item.address, item.type_label].filter(Boolean).join(' · ');
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
        var cols = this.pages ? [['name', t('Name')], ['', t('Address')], ['type', t('Type')], ['modified', t('Modified')]]
            : [['name', t('Name')], ['type', t('Type')], ['size', t('Size')], ['modified', t('Modified')], ['', t('Access')]];
        cols.forEach(function (col) {
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

    // A chip per page type group in the list, with All; shown once there
    // are two groups to tell apart, or one is chosen.
    Picker.prototype.renderChips = function () {
        var self = this;
        this.chipBox.textContent = '';
        this.chipBox.hidden = !this.pages || this.folderList || (this.chipKeys.length < 2 && !this.group);
        if (this.chipBox.hidden) {
            return;
        }
        [''].concat(this.chipKeys).forEach(function (key) {
            var chip = button('btn btn-sm pg-picker-only' + (self.group === key ? ' active' : ''), key ? groupLabel(key) : t('All'));
            chip.setAttribute('data-group', key);
            chip.setAttribute('aria-pressed', String(self.group === key));
            self.chipBox.appendChild(chip);
        });
    };

    Picker.prototype.renderStatus = function () {
        var self = this;
        this.status.textContent = '';
        if (this.recentOn || this.extraOn) {
            this.status.appendChild(el('span', '', (this.items.length === 1) ? t('1 item') : t('{n} items', { n: this.items.length })));
            if (this.recentOn) {
                this.status.appendChild(el('span', 'pg-picker-note', t('Your last picks in this browser')));
            }
        } else if (this.results) {
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
            if (folders && this.files.length && !this.pages) {
                text += ' · ' + t('{folders} folders, {files} files', { folders: folders, files: this.files.length });
            }
            this.status.appendChild(el('span', '', text));
            if (this.capped) {
                this.status.appendChild(el('span', 'pg-picker-note', t('Only the first 2,000 are shown; search to find the rest.')));
            }
        }
        if (this.items.some(function (item) { return !!item.mark; })) {
            this.status.appendChild(el('span', 'pg-picker-note', t('Archived pages and pages in the Recycle Bin cannot be chosen.')));
        }
        this.hint.textContent = this.results ? t('Esc clears the search')
            : (this.multiple ? t('A click adds or removes, Shift+click adds a row')
                : (this.view === 'list' ? t('A column heading sorts') : t('Double-click or Enter chooses')));
    };

    Picker.prototype.renderCrumbs = function () {
        var self = this;
        var crumbs = this.folderList ? this.listCrumbs() : this.crumbs;
        this.crumbNav.textContent = '';
        if (this.extraOn && !this.folderList) {
            this.crumbNav.appendChild(el('strong', '', this.extraRow.textContent));
        } else if (this.recentOn && !this.folderList) {
            this.crumbNav.appendChild(el('strong', '', t('Recently used')));
        } else if (this.results && !this.folderList) {
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
        this.upButton.disabled = !this.results && !this.recentOn && !this.extraOn && this.crumbs.length < 2;
        var here = this.extraOn ? this.extraRow.textContent : (this.recentOn ? t('Recently used')
            : (this.crumbs.length ? this.crumbs[this.crumbs.length - 1].name : (this.pages ? t('Pages') : t('Files'))));
        this.chip.textContent = '';
        this.chip.appendChild(icon('bi-folder-fill'));
        this.chip.appendChild(el('span', 'pg-btn-label', here));
        this.chip.appendChild(icon('bi-chevron-down'));
    };

    Picker.prototype.renderPick = function () {
        this.pickBox.textContent = '';
        if (this.multiple) {
            this.renderPicks();
            return;
        }
        this.choose.disabled = !this.pick;
        if (!this.pick) {
            this.pickBox.appendChild(el('span', 'pg-picker-meta', t('Nothing chosen yet')));
            return;
        }
        var thumb = el('span', 'pg-picker-thumb');
        if (this.pages) {
            thumb.appendChild(icon(this.pick.extra ? 'bi-file-earmark-plus' : 'bi-file-earmark-text'));
        } else if (this.pick.thumb) {
            var img = el('img');
            img.alt = '';
            img.src = this.pick.thumb;
            thumb.appendChild(img);
        } else {
            thumb.appendChild(icon(fileIcon(this.pick.type)));
        }
        var text = el('span', 'pg-picker-text');
        text.appendChild(el('strong', 'pg-picker-name', this.pick.text));
        var meta = [this.pages ? (this.pick.address || this.pick.note || '') : '', pathText(this.pick.path)];
        if (this.pick.width && this.pick.height) {
            meta.push(this.pick.width + ' × ' + this.pick.height);
        }
        meta.push(this.pick.size_label || '');
        text.appendChild(el('span', 'pg-picker-meta', meta.filter(Boolean).join(' · ')));
        this.pickBox.appendChild(thumb);
        this.pickBox.appendChild(text);
    };

    // Several picks: their pictures in a row, how many and their names.
    Picker.prototype.renderPicks = function () {
        var picks = this.picks;
        this.choose.disabled = !picks.length;
        if (!picks.length) {
            this.pickBox.appendChild(el('span', 'pg-picker-meta', t('Nothing chosen yet')));
            return;
        }
        var strip = el('span', 'pg-picker-strip');
        picks.slice(0, 4).forEach(function (pick) {
            var thumb = el('span', 'pg-picker-thumb');
            if (pick.thumb) {
                var img = el('img');
                img.alt = '';
                img.src = pick.thumb;
                thumb.appendChild(img);
            } else {
                thumb.appendChild(icon(pick.extra || pick.type_group ? 'bi-file-earmark-text' : fileIcon(pick.type)));
            }
            strip.appendChild(thumb);
        });
        var text = el('span', 'pg-picker-text');
        text.appendChild(el('strong', 'pg-picker-name', t('{n} chosen', { n: picks.length })));
        text.appendChild(el('span', 'pg-picker-meta', picks.map(function (pick) { return pick.text; }).join(', ')));
        this.pickBox.appendChild(strip);
        this.pickBox.appendChild(text);
    };

    Picker.prototype.isPicked = function (item) {
        if (!item || item.isFolder) {
            return false;
        }
        if (this.multiple) {
            return pickIndex(this.picks, item) !== -1;
        }
        return !!this.pick && String(item.value) === String(this.pick.value);
    };

    // What an empty pane says: no results, no Recent yet, or an empty
    // folder with Upload File and Recent.
    Picker.prototype.emptyNode = function () {
        var self = this;
        var box = el('div', 'pg-picker-empty');
        if (this.recentOn) {
            box.appendChild(icon('bi-clock-history'));
            box.appendChild(el('strong', '', t('Nothing chosen yet')));
            box.appendChild(el('p', '', this.pages ? t('Pages you choose appear here.') : t('Files you choose appear here.')));
            return box;
        }
        box.appendChild(icon(this.results ? 'bi-search' : (this.pages ? 'bi-file-earmark-text' : 'bi-images')));
        box.appendChild(el('strong', '', this.results ? t('No results found') : t('There is nothing to choose in this folder')));
        if (this.results || !this.upload) {
            return box;
        }
        box.appendChild(el('p', '', this.upload.ok ? t('Drag files here or choose them from your computer. An uploaded file is saved in this folder and chosen.') : this.upload.reason));
        var actions = el('div', 'pg-picker-empty-actions');
        if (this.upload.ok) {
            var upload = button('btn btn-solid theme-primary', t('Upload File'), 'bi-upload');
            upload.disabled = this.uploading;
            upload.addEventListener('click', function () { self.fileInput.click(); });
            actions.appendChild(upload);
        }
        var recent = button('btn btn-outline theme-secondary', t('Recently used'), 'bi-clock-history');
        recent.addEventListener('click', function () { self.showRecent(); });
        actions.appendChild(recent);
        box.appendChild(actions);
        return box;
    };

    Picker.prototype.markCursor = function (scroll) {
        var nodes = this.itemsBox.querySelectorAll('[data-index]');
        for (var i = 0; i < nodes.length; i++) {
            var item = this.items[i];
            var on = i === this.cursor;
            nodes[i].classList.toggle('is-cursor', on);
            nodes[i].setAttribute('aria-selected', String(this.isPicked(item)));
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
        var here = (this.recentOn || this.extraOn) ? -1 : this.folder;
        this.recentRow.classList.toggle('is-open', this.recentOn);
        this.recentRow.setAttribute('aria-current', String(this.recentOn));
        this.extraRow.classList.toggle('is-open', this.extraOn);
        this.extraRow.setAttribute('aria-current', String(this.extraOn));
        this.treeList.textContent = '';
        function branch(parent, list, level) {
            (self.tree[parent] || []).forEach(function (row) {
                var li = el('li');
                li.setAttribute('role', 'treeitem');
                li.setAttribute('data-folder', String(row.id));
                li.setAttribute('aria-level', String(level));
                li.setAttribute('aria-selected', String(row.id === here));
                li.tabIndex = -1;
                var line = el('div', 'pg-picker-row' + (row.id === here ? ' is-open' : ''));
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

    // An item with the folders down to it, for the footer.
    Picker.prototype.withPath = function (item) {
        return (item.path || item.extra) ? item : Object.assign({}, item, { path: this.crumbs.slice() });
    };

    // With one pick the cursor on a file is the pick, as the tile drawn
    // chosen in the screens; a folder or a marked page only takes the
    // cursor. With several
    // the cursor only moves, and `extend` (Shift) adds the files from the
    // anchor to it.
    Picker.prototype.moveTo = function (index, extend) {
        if (index < 0 || index >= this.items.length) {
            return;
        }
        this.cursor = index;
        var item = this.items[index];
        if (this.multiple) {
            if (extend) {
                this.setPicks(rangePick(this.picks, this.items, this.anchor < 0 ? index : this.anchor, index).map(this.withPath, this));
            } else {
                this.anchor = index;
            }
        } else if (pickable(item)) {
            this.pick = this.withPath(item);
            this.renderPick();
            this.refreshTicks();
        }
        this.markCursor(true);
    };

    Picker.prototype.setPicks = function (picks) {
        this.picks = picks;
        this.renderPick();
        this.refreshTicks();
        this.markCursor(false);
    };

    Picker.prototype.togglePickAt = function (index) {
        var item = this.items[index];
        if (pickable(item)) {
            this.setPicks(togglePick(this.picks, this.withPath(item)));
        }
    };

    Picker.prototype.refreshTicks = function () {
        var self = this;
        var nodes = this.itemsBox.querySelectorAll('[data-index]');
        for (var i = 0; i < nodes.length; i++) {
            var item = this.items[i];
            var tick = nodes[i].querySelector('.pg-picker-tick');
            var on = self.isPicked(item);
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
        if (!pickable(item)) {
            return;
        }
        if (this.multiple) {
            if (!this.isPicked(item)) {
                this.picks = this.picks.concat([this.withPath(item)]);
            }
        } else {
            this.pick = item;
        }
        this.finish();
    };

    Picker.prototype.goUp = function () {
        if (this.recentOn || this.extraOn) {
            this.openFolder(this.folder);
            return;
        }
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
        // Ctrl+A picks every file in view when several may be picked.
        if (this.multiple && (event.ctrlKey || event.metaKey) && !event.altKey && (key === 'a' || key === 'A')) {
            event.preventDefault();
            this.setPicks(rangePick(this.picks, this.items, 0, this.items.length - 1).map(this.withPath, this));
            return;
        }
        if (event.altKey || event.ctrlKey || event.metaKey) {
            if (event.altKey && key === 'ArrowUp') {
                event.preventDefault();
                this.goUp();
            }
            return;
        }
        if (key === 'Enter') {
            event.preventDefault();
            var current = this.items[this.cursor];
            // With several picks Enter chooses them; on a folder it opens it.
            if (this.multiple && this.picks.length && !(current && current.isFolder)) {
                this.finish();
            } else {
                this.activate(this.cursor);
            }
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
            this.moveTo(next, this.multiple && event.shiftKey);
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
            if (this.multiple) {
                this.togglePickAt(this.cursor);
            }
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
        this.titleNode.textContent = this.folderList ? t('Choose a folder') : this.defaultTitle();
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
            self.chipBox.hidden = true;
            self.itemsBox.textContent = '';
            self.hint.textContent = t('Tapping a folder shows its files');
            var recent = el('div', 'pg-picker-item pg-picker-folder-row' + (self.recentOn ? ' is-cursor' : ''));
            recent.setAttribute('role', 'option');
            recent.setAttribute('aria-selected', String(self.recentOn));
            recent.appendChild(icon('bi-clock-history'));
            recent.appendChild(el('span', 'pg-picker-label', t('Recently used')));
            recent.addEventListener('click', function () { self.showRecent(); });
            self.itemsBox.appendChild(recent);
            if (self.extras.length) {
                var extra = el('div', 'pg-picker-item pg-picker-folder-row' + (self.extraOn ? ' is-cursor' : ''));
                extra.setAttribute('role', 'option');
                extra.setAttribute('aria-selected', String(self.extraOn));
                extra.appendChild(icon('bi-file-earmark-plus'));
                extra.appendChild(el('span', 'pg-picker-label', self.extraRow.textContent));
                extra.addEventListener('click', function () { self.showExtras(); });
                self.itemsBox.appendChild(extra);
            }
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

    // ── Recent ────────────────────────────────────────────────────────────

    // Kept per person and kind in this browser: the values only, so a file
    // moved, renamed or binned since is drawn as it is now, or left out.
    Picker.prototype.recentKey = function () {
        return 'recent.' + (Number(conf().user) || 0) + '.' + this.kind;
    };

    Picker.prototype.readRecent = function () {
        try {
            var list = JSON.parse(remember(this.recentKey()) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    };

    Picker.prototype.keepRecent = function (items) {
        var list = this.readRecent();
        items.filter(function (item) { return !item.extra; }).reverse().forEach(function (item) { list = addRecent(list, item, 12); });
        remember(this.recentKey(), JSON.stringify(list));
    };

    Picker.prototype.showRecent = function () {
        var self = this, seq = ++this.seq, list = this.readRecent();
        this.showFolderList(false);
        this.leaveSearch();
        this.recentOn = true;
        this.extraOn = false;
        this.recent = [];
        this.renderTree();
        if (!list.length) {
            this.render(true);
            this.itemsBox.focus();
            return Promise.resolve();
        }
        this.setStatus(t('Loading…'));
        return request({ kind: this.kind, request: 'resolve', values: list.map(function (entry) { return String(entry.value); }) }).then(function (body) {
            if (seq !== self.seq) {
                return;
            }
            self.recent = (body.items || []).filter(function (item) { return item.state === 'ok'; });
            // What is missing, binned or out of reach now leaves the list.
            remember(self.recentKey(), JSON.stringify(list.filter(function (entry) {
                return pickIndex(self.recent, entry) !== -1;
            })));
            self.render(true);
            self.itemsBox.focus();
        }).catch(function (error) { self.fail(error); });
    };

    // The caller's own entries, as handed in.
    Picker.prototype.showExtras = function () {
        ++this.seq;
        this.showFolderList(false);
        this.leaveSearch();
        this.recentOn = false;
        this.extraOn = true;
        this.renderTree();
        this.render(true);
        this.itemsBox.focus();
    };

    // ── Upload ────────────────────────────────────────────────────────────

    Picker.prototype.canUpload = function () {
        return !!(this.upload && this.upload.ok) && !this.recentOn && !this.extraOn && !this.uploading;
    };

    Picker.prototype.renderUpload = function () {
        this.uploadButton.hidden = !this.upload;
        if (!this.upload) {
            return;
        }
        var reason = this.recentOn ? t('Open a folder to upload into it.') : (this.upload.ok ? '' : this.upload.reason);
        this.uploadButton.disabled = !!reason || this.uploading;
        this.uploadButton.title = reason;
    };

    Picker.prototype.folderName = function () {
        return this.crumbs.length ? this.crumbs[this.crumbs.length - 1].name : t('Files');
    };

    Picker.prototype.showDrop = function (event) {
        var max = conf().file_max_label;
        var count = (event.dataTransfer && event.dataTransfer.items) ? event.dataTransfer.items.length : 0;
        var ok = this.canUpload();
        this.dropBox.textContent = '';
        this.dropBox.classList.toggle('is-refused', !ok);
        this.dropBox.appendChild(icon(ok ? 'bi-cloud-arrow-up' : 'bi-slash-circle'));
        this.dropBox.appendChild(el('strong', '', ok ? t('Drop to upload into “{folder}”', { folder: this.folderName() })
            : (this.recentOn ? t('Open a folder to upload into it.') : (this.upload.reason || ''))));
        if (ok && max && count) {
            this.dropBox.appendChild(el('span', '', count === 1 ? t('1 file · at most {size} per file', { size: max }) : t('{n} files · at most {size} per file', { n: count, size: max })));
        }
        this.dropBox.hidden = false;
    };

    // Sends the files one after another into the open folder. A file of a
    // type the kind does not take or over the server's largest is refused
    // before it is sent; the first stored file becomes the pick (every
    // stored file with several picks).
    Picker.prototype.uploadFiles = function (files) {
        var self = this;
        if (!files.length || !this.canUpload()) {
            return Promise.resolve();
        }
        var folder = this.folder, cfg = conf(), batch = { total: files.length, stored: 0, picked: false };
        this.uploading = true;
        this.renderUpload();
        this.openUploads(batch);

        var chain = Promise.resolve();
        files.forEach(function (file) {
            var row = self.uploadRow(file);
            var refusal = uploadRefusal(file, self.typesList, Number(cfg.file_max) || 0);
            if (refusal) {
                self.rowRefused(row, refusal === 'type'
                    ? t('Only {types} files can be chosen here.', { types: typesLabel(self.typesList) })
                    : t('Larger than {size}, the biggest single file this server accepts.', { size: cfg.file_max_label }));
                return;
            }
            chain = chain.then(function () { return self.sendFile(file, folder, row); }).then(function (item) {
                if (item) {
                    batch.stored++;
                    self.uploaded(item, folder, row, batch);
                    self.uploadsHead(batch, false);
                }
            });
        });

        return chain.then(function () {
            self.uploading = false;
            self.uploadsHead(batch, true);
            if (folder === self.folder && !self.recentOn && !self.results) {
                self.render(true);
                self.markCursor(true);
            } else {
                self.renderUpload();
            }
        });
    };

    Picker.prototype.sendFile = function (file, folder, row) {
        var self = this;
        return new Promise(function (resolve) {
            var xhr = new root.XMLHttpRequest(), data = new root.FormData();
            data.append('token', conf().token || '');
            data.append('file', file, file.name);
            xhr.open('POST', conf().endpoint + '?' + queryString({ kind: self.kind, request: 'upload', folder: folder }));
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    self.rowProgress(row, event.loaded / event.total);
                }
            });
            xhr.addEventListener('load', function () {
                var body = {};
                try {
                    body = JSON.parse(xhr.responseText) || {};
                } catch (e) {
                    body = {};
                }
                if (xhr.status === 200 && body.item) {
                    resolve(body.item);
                    return;
                }
                self.rowRefused(row, body.error || t('The upload failed.'));
                resolve(null);
            });
            xhr.addEventListener('error', function () {
                self.rowRefused(row, t('The upload failed.'));
                resolve(null);
            });
            xhr.send(data);
        });
    };

    // A stored file joins its folder's tiles, is picked when it is the
    // first (or with several picks), and counts in the tree.
    Picker.prototype.uploaded = function (item, folder, row, batch) {
        var self = this, chosen = false;
        if (folder === this.folder) {
            this.files = this.files.filter(function (file) { return String(file.value) !== String(item.value); }).concat([item]);
        }
        if (this.multiple) {
            this.picks = this.picks.concat([item]);
            chosen = true;
        } else if (!batch.picked) {
            this.pick = item;
            batch.picked = true;
            chosen = true;
        }
        var ids = (item.path || []).map(function (crumb) { return Number(crumb.id); });
        Object.keys(this.tree).forEach(function (node) {
            self.tree[node].forEach(function (one) {
                if (ids.indexOf(Number(one.id)) !== -1) {
                    one.count = (Number(one.count) || 0) + 1;
                }
            });
        });
        this.rowDone(row, item, chosen);
        this.renderPick();
        this.renderTree();
        if (folder === this.folder && !this.recentOn && !this.results && !this.folderList) {
            this.render(true);
        }
    };

    Picker.prototype.openUploads = function (batch) {
        var self = this;
        this.objectUrls.forEach(function (url) { root.URL.revokeObjectURL(url); });
        this.objectUrls = [];
        this.uploadsBox.textContent = '';
        var head = el('div', 'pg-picker-uploads-head');
        head.appendChild(icon('bi-upload'));
        this.uploadsTitle = el('strong', '');
        head.appendChild(this.uploadsTitle);
        head.appendChild(el('span', 'pg-picker-uploads-folder', this.folderName()));
        this.uploadsClose = button('btn-close', '', '', t('Close'));
        this.uploadsClose.hidden = true;
        this.uploadsClose.addEventListener('click', function () { self.uploadsBox.hidden = true; self.itemsBox.focus(); });
        head.appendChild(this.uploadsClose);
        this.uploadsList = el('ul', 'pg-picker-uploads-list');
        this.uploadsBox.appendChild(head);
        this.uploadsBox.appendChild(this.uploadsList);
        this.uploadsBox.hidden = false;
        this.uploadsHead(batch, false);
    };

    Picker.prototype.uploadsHead = function (batch, done) {
        this.uploadsTitle.textContent = t(done ? 'Uploaded · {done} / {total}' : 'Uploading · {done} / {total}', { done: batch.stored, total: batch.total });
        this.uploadsClose.hidden = !done;
    };

    Picker.prototype.uploadRow = function (file) {
        var row = el('li', 'pg-picker-upload-row');
        var thumb = el('span', 'pg-picker-thumb');
        if (/^image\//.test(file.type || '') && root.URL && root.URL.createObjectURL) {
            var url = root.URL.createObjectURL(file);
            this.objectUrls.push(url);
            var img = el('img');
            img.alt = '';
            img.src = url;
            thumb.appendChild(img);
        } else {
            thumb.appendChild(icon(fileIcon(extensionOf(file.name))));
        }
        var text = el('span', 'pg-picker-text');
        text.appendChild(el('span', 'pg-picker-name', file.name));
        row.meta = el('span', 'pg-picker-meta', formatSize(file.size, doc.documentElement.lang));
        text.appendChild(row.meta);
        row.bar = el('span', 'pg-picker-progress');
        row.bar.appendChild(el('span'));
        text.appendChild(row.bar);
        row.state = el('span', 'pg-picker-upload-state', '0%');
        row.size = formatSize(file.size, doc.documentElement.lang);
        row.appendChild(thumb);
        row.appendChild(text);
        row.appendChild(row.state);
        this.uploadsList.appendChild(row);
        return row;
    };

    Picker.prototype.rowProgress = function (row, share) {
        var percent = Math.min(99, Math.round(share * 100));
        row.bar.firstChild.style.width = percent + '%';
        row.state.textContent = percent + '%';
    };

    Picker.prototype.rowDone = function (row, item, chosen) {
        row.classList.add('is-done');
        row.bar.hidden = true;
        row.meta.textContent = (item.size_label || row.size) + ' · ' + (chosen ? t('uploaded, chosen') : t('uploaded'));
        row.state.textContent = '';
        row.state.appendChild(icon('bi-check-circle-fill'));
    };

    Picker.prototype.rowRefused = function (row, reason) {
        row.classList.add('is-refused');
        row.bar.hidden = true;
        row.meta.textContent = reason;
        row.state.textContent = t('Not uploaded');
        var thumb = row.querySelector('.pg-picker-thumb');
        thumb.textContent = '';
        thumb.appendChild(icon('bi-exclamation-octagon'));
    };

    // ── Opening and closing ───────────────────────────────────────────────

    Picker.prototype.finish = function () {
        var picks = this.multiple ? this.picks : (this.pick ? [this.pick] : []);
        if (!picks.length) {
            return;
        }
        this.result = this.multiple ? picks.slice() : this.pick;
        this.keepRecent(picks);
        root.pgUI.dialog.hide(this.root);
    };

    Picker.prototype.start = function () {
        var self = this;
        var values = [].concat(this.opts.value === undefined || this.opts.value === null ? [] : this.opts.value)
            .map(String).filter(function (value) { return value !== ''; }).slice(0, this.multiple ? 50 : 1);
        var own = {};
        this.extras.forEach(function (entry) { own[entry.value] = entry; });
        var asked = values.filter(function (value) { return !own[value]; });
        var first = asked.length
            ? request({ kind: this.kind, request: 'resolve', values: asked }).then(function (body) {
                return body.items || [];
            }).catch(function () { return []; })
            : Promise.resolve([]);

        return Promise.all([first, this.loadTree([0])]).then(function (out) {
            // The picks in the order stored, the caller's entries among them.
            var picks = [];
            values.forEach(function (value) {
                var found = own[value] || null;
                for (var i = 0; !found && i < out[0].length; i++) {
                    if (out[0][i].state === 'ok' && String(out[0][i].value) === value) {
                        found = out[0][i];
                    }
                }
                if (found) {
                    picks.push(found);
                }
            });
            var item = picks[0];
            if (item) {
                if (self.multiple) {
                    self.picks = picks;
                } else {
                    self.pick = item;
                }
                self.renderPick();
                return item.extra ? self.showExtras() : self.openFolder(item.folder);
            }
            // A stored page that cannot be chosen again (archived, or in the
            // Recycle Bin, told in the folder it came from) opens its folder,
            // where it is shown marked.
            var kept = self.pages ? out[0].filter(function (entry) { return (entry.state === 'offline' || entry.state === 'binned') && entry.folder; })[0] : null;
            if (kept) {
                return self.openFolder(kept.folder);
            }
            var top = self.tree[0] || [];
            if (!top.length) {
                self.render(true);
                return null;
            }
            return self.openFolder(top[0].id);
        }).then(function () {
            // The field's "in Browse" row hands in what was typed.
            if (self.opts.query) {
                self.setQuery(self.opts.query);
                self.search.focus();
                return;
            }
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
                picker.objectUrls.forEach(function (url) { root.URL.revokeObjectURL(url); });
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

    // ── The field ─────────────────────────────────────────────────────────
    // pg_picker_field() prints the whole field: the posted value in a
    // hidden input, a combobox showing the chosen name with its picture and
    // folder, clear, Browse and an empty list. Typing asks picker.php to
    // search the kind and lists the answers with their pictures; the last
    // row opens Browse on the same text (Alt+Down too). Below 576 px a tap
    // opens the search in a half sheet. The listeners sit on the document,
    // so a field in injected markup needs no start.

    var FIELD_PAUSE = 250, FIELD_ROWS = 8;

    function onPhone() {
        return !!(root.matchMedia && root.matchMedia('(max-width: 575.98px)').matches);
    }

    function fillThumb(box, item, pages) {
        box.textContent = '';
        if (!item) {
            return;
        }
        if (item.thumb) {
            var img = el('img');
            img.alt = '';
            img.src = item.thumb;
            box.appendChild(img);
        } else {
            box.appendChild(icon(pages ? 'bi-file-earmark-text' : fileIcon(item.type)));
        }
    }

    function Field(node) {
        this.root = node;
        this.kind = node.getAttribute('data-pg-picker-kind') || 'file';
        this.pages = (conf().page_kinds || []).indexOf(this.kind) !== -1;
        this.value = node.querySelector('[data-pg-picker-value]');
        this.input = node.querySelector('.pg-pfield-input');
        this.thumb = node.querySelector('.pg-pfield-thumb');
        this.meta = node.querySelector('.pg-pfield-meta');
        this.pop = node.querySelector('.pg-pfield-pop');
        this.own = { input: this.input, status: node.querySelector('.pg-pfield-status'), list: node.querySelector('.pg-pfield-list') };
        this.host = this.own;
        // The chosen name, put back when a search is left without a pick.
        // Read from the value attribute: the Field is made on its first
        // event, which can come after the text changed (a paste fires no
        // keydown before its input event).
        this.text = this.input.defaultValue;
        this.items = [];
        this.query = '';
        this.active = -1;
        this.seq = 0;
        this.timer = null;
        this.sheet = null;
    }

    function fieldOf(target) {
        var node = (target && target.closest) ? target.closest('[data-pg-picker-field]') : null;
        if (!node) {
            return null;
        }
        if (!node.pgPickerField) {
            node.pgPickerField = new Field(node);
        }
        return node.pgPickerField;
    }

    Field.prototype.isOpen = function () {
        return !!this.sheet || !this.pop.hidden;
    };

    Field.prototype.show = function (on) {
        this.pop.hidden = !on;
        this.input.setAttribute('aria-expanded', String(!!on));
        this.root.classList.toggle('is-open', !!on);
        if (on && !this.own.list.firstChild) {
            this.draw([], '', false);
        }
    };

    // Nothing typed lists only Browse; a text is asked for after a pause,
    // and an answer to an older text is dropped.
    Field.prototype.typed = function () {
        var self = this, text = String(this.host.input.value).trim();
        if (!this.sheet) {
            this.root.classList.toggle('is-typing', this.input.value !== this.text);
            this.show(true);
        }
        root.clearTimeout(this.timer);
        if (text === '') {
            this.seq++;
            this.draw([], '', false);
            return;
        }
        this.timer = root.setTimeout(function () { self.search(text); }, FIELD_PAUSE);
    };

    Field.prototype.search = function (text) {
        var self = this, seq = ++this.seq;
        this.host.status.textContent = t('Searching…');
        request({ kind: this.kind, request: 'search', q: text, per_page: FIELD_ROWS }).then(function (body) {
            if (seq === self.seq) {
                self.draw(body.items || [], text, !!body.more);
            }
        }).catch(function (error) {
            if (seq === self.seq) {
                self.draw([], text, false);
                self.host.status.textContent = error.message;
            }
        });
    };

    Field.prototype.draw = function (items, text, more) {
        var self = this, list = this.host.list;
        this.items = items.slice();
        this.query = text;
        list.textContent = '';
        items.forEach(function (item, index) {
            var row = el('div', 'pg-pfield-option');
            row.id = list.id + '_' + index;
            row.setAttribute('role', 'option');
            row.setAttribute('data-index', String(index));
            var thumb = el('span', 'pg-pfield-thumb');
            fillThumb(thumb, item, self.pages);
            row.appendChild(thumb);
            var words = el('span', 'pg-pfield-words');
            words.appendChild(highlighted(String(item.text), text));
            words.appendChild(el('span', 'pg-pfield-meta', fieldMeta(item)));
            row.appendChild(words);
            // An archived or binned page is found, marked, and cannot be
            // chosen (pickable()), as in the dialog.
            if (item.mark) {
                row.classList.add('is-marked');
                row.setAttribute('aria-disabled', 'true');
                row.appendChild(el('span', 'pg-picker-mark is-' + item.mark, markLabel(item.mark)));
            }
            list.appendChild(row);
        });
        var more_row = el('div', 'pg-pfield-option pg-pfield-more');
        more_row.id = list.id + '_browse';
        more_row.setAttribute('role', 'option');
        more_row.setAttribute('data-index', String(items.length));
        more_row.appendChild(icon('bi-folder2-open'));
        more_row.appendChild(el('span', 'pg-pfield-words', text ? t('All results for “{q}” in Browse', { q: text }) : t('Browse…')));
        if (!this.sheet) {
            more_row.appendChild(el('kbd', '', 'Alt + ↓'));
        }
        list.appendChild(more_row);
        this.host.status.textContent = (text === '') ? t('Type to search…')
            : (items.length ? t(resultsKey(items.length, more), { n: items.length }) : t('No results found'));
        this.setActive(-1);
    };

    Field.prototype.setActive = function (index) {
        var rows = this.host.list.querySelectorAll('.pg-pfield-option');
        this.active = (index >= 0 && index < rows.length) ? index : -1;
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.toggle('is-active', i === this.active);
            rows[i].setAttribute('aria-selected', String(i === this.active));
        }
        if (this.active < 0) {
            this.host.input.removeAttribute('aria-activedescendant');
            return;
        }
        this.host.input.setAttribute('aria-activedescendant', rows[this.active].id);
        if (rows[this.active].scrollIntoView) {
            rows[this.active].scrollIntoView({ block: 'nearest' });
        }
    };

    // Writes a pick (null clears): the posted value, the name, its line and
    // picture; a note on the stored value goes with it. Fires the change a
    // screen listens to.
    Field.prototype.set = function (item) {
        this.value.value = item ? String(item.value) : '';
        this.text = item ? String(item.text) : '';
        this.input.value = this.text;
        this.meta.textContent = item ? fieldMeta(item) : '';
        fillThumb(this.thumb, item, this.pages);
        this.root.classList.remove('is-typing', 'is-warning', 'is-danger', 'is-error');
        this.root.classList.toggle('is-empty', !item);
        this.input.removeAttribute('aria-invalid');
        var note = this.root.querySelector('.pg-pfield-note');
        if (note) {
            this.input.setAttribute('aria-describedby', String(this.input.getAttribute('aria-describedby') || '').split(' ').filter(function (id) { return id && id !== note.id; }).join(' '));
            note.remove();
        }
        this.show(false);
        this.value.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Leaves a search without a pick: the chosen name comes back.
    Field.prototype.restore = function () {
        root.clearTimeout(this.timer);
        this.seq++;
        this.input.value = this.text;
        this.root.classList.remove('is-typing');
        this.show(false);
        this.setActive(-1);
    };

    Field.prototype.choose = function (index) {
        var self = this, item = this.items[index];
        if (index >= this.items.length) {
            return this.browse(this.query);
        }
        if (!item) {
            return null;
        }
        if (!pickable(item)) {
            this.host.status.textContent = t('Archived pages and pages in the Recycle Bin cannot be chosen.');
            return null;
        }
        if (this.sheet) {
            return this.closeSheet().then(function () {
                self.set(item);
                self.input.focus();
            });
        }
        this.set(item);
        return null;
    };

    Field.prototype.browse = function (text) {
        var self = this;
        var go = function () {
            self.restore();
            return open({ kind: self.kind, title: self.root.getAttribute('data-pg-picker-title') || undefined, value: self.value.value, query: text || '' }).then(function (item) {
                if (item) {
                    self.set(item);
                }
                self.input.focus();
            });
        };
        return this.sheet ? this.closeSheet().then(go) : go();
    };

    Field.prototype.key = function (event) {
        var rows = this.host.list.querySelectorAll('.pg-pfield-option').length;
        var open_now = this.isOpen();
        if (event.key === 'ArrowDown' && event.altKey) {
            event.preventDefault();
            this.browse(String(this.host.input.value).trim() === this.text && !this.sheet ? '' : String(this.host.input.value).trim());
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!open_now) {
                this.show(true);
                return;
            }
            this.setActive(Math.min(this.active + 1, rows - 1));
        } else if (event.key === 'ArrowUp' && open_now) {
            event.preventDefault();
            this.setActive(Math.max(this.active - 1, 0));
        } else if (event.key === 'Enter') {
            // Never submits the form the field sits in.
            event.preventDefault();
            if (open_now && rows) {
                this.choose(this.active >= 0 ? this.active : Math.max(0, this.items.findIndex(pickable)));
            }
        } else if (event.key === 'Escape' && !this.sheet && (open_now || this.input.value !== this.text)) {
            // Kept from the settings dialog around the field.
            event.preventDefault();
            event.stopPropagation();
            this.restore();
        }
    };

    // The phone's half sheet: the field's label as its title, a search box
    // and the same rows; a pick is written once the sheet has closed.
    Field.prototype.openSheet = function () {
        var self = this;
        if (this.sheet || !root.pgUI) {
            return;
        }
        var label = this.input.id ? doc.querySelector('label[for="' + this.input.id + '"]') : null;
        var parts = root.pgUI.dialog.build({ className: 'pg-pfield-sheet', closeLabel: t('Close'), title: label ? label.textContent.trim() : this.root.getAttribute('data-pg-picker-title') });
        var input = el('input', 'form-control pg-pfield-query');
        var status = el('div', 'pg-pfield-status');
        var list = el('div', 'pg-pfield-list');
        list.id = (this.input.id || 'pg_pfield') + '_sheet_list';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', parts.title.textContent);
        input.type = 'search';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-label', t('Search'));
        input.setAttribute('autocomplete', 'off');
        input.placeholder = t('Type to search…');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        parts.body.appendChild(input);
        parts.body.appendChild(status);
        parts.body.appendChild(list);
        input.addEventListener('input', function () { self.typed(); });
        input.addEventListener('keydown', function (event) { self.key(event); });
        list.addEventListener('click', function (event) {
            var row = event.target.closest('.pg-pfield-option');
            if (row) {
                self.choose(Number(row.getAttribute('data-index')));
            }
        });
        this.sheet = parts.root;
        this.host = { input: input, status: status, list: list };
        root.pgUI.on(parts.root, 'dialog', 'hidden', function () { self.dropSheet(); }, { once: true });
        doc.body.appendChild(parts.root);
        this.draw([], '', false);
        root.pgUI.dialog.show(parts.root).then(function () { input.focus(); });
    };

    Field.prototype.dropSheet = function () {
        if (this.sheet) {
            root.clearTimeout(this.timer);
            this.seq++;
            this.sheet.remove();
            this.sheet = null;
            this.host = this.own;
            this.items = [];
        }
    };

    Field.prototype.closeSheet = function () {
        var self = this, sheet = this.sheet;
        return Promise.resolve(sheet ? root.pgUI.dialog.hide(sheet) : null).then(function () { self.dropSheet(); });
    };

    function fieldInput(target) {
        return !!(target && target.classList && target.classList.contains('pg-pfield-input'));
    }

    doc.addEventListener('input', function (event) {
        if (fieldInput(event.target)) {
            fieldOf(event.target).typed();
        }
    });

    // In the capture phase: Bootstrap's Escape handler sits on the dialog
    // element around a field, before a bubbling document listener.
    doc.addEventListener('keydown', function (event) {
        if (fieldInput(event.target)) {
            fieldOf(event.target).key(event);
        }
    }, true);

    // On a phone the box opens the sheet: read-only before the tap's focus,
    // so no keyboard opens under it.
    doc.addEventListener('pointerdown', function (event) {
        var box = event.target.closest && event.target.closest('.pg-pfield-box');
        if (box) {
            var input = box.querySelector('.pg-pfield-input');
            input.readOnly = onPhone() && !event.target.closest('.pg-pfield-clear');
        }
    });

    // A press in the open list keeps the focus in the box.
    doc.addEventListener('mousedown', function (event) {
        if (event.target.closest && event.target.closest('.pg-pfield-pop')) {
            event.preventDefault();
        }
    });

    doc.addEventListener('click', function (event) {
        var field = fieldOf(event.target), target = event.target;
        if (!field) {
            return;
        }
        if (target.closest('.pg-pfield-clear')) {
            event.preventDefault();
            field.set(null);
            field.input.readOnly = false;
            field.input.focus();
        } else if (target.closest('.pg-pfield-browse')) {
            event.preventDefault();
            field.browse('');
        } else if (target.closest('.pg-pfield-option')) {
            field.choose(Number(target.closest('.pg-pfield-option').getAttribute('data-index')));
        } else if (target.closest('.pg-pfield-box')) {
            if (onPhone()) {
                event.preventDefault();
                field.openSheet();
                return;
            }
            field.input.readOnly = false;
            if (field.isOpen() && target.closest('.pg-pfield-caret')) {
                field.restore();
                return;
            }
            field.input.focus();
            if (!field.isOpen()) {
                // A typed name is replaced as a whole.
                if (!field.root.classList.contains('is-typing')) {
                    field.input.select();
                }
                field.show(true);
            }
        }
    });

    // Leaving the field (Tab, a click elsewhere) puts the chosen name back.
    doc.addEventListener('focusout', function (event) {
        var field = fieldInput(event.target) ? fieldOf(event.target) : null;
        var next = event.relatedTarget;
        if (field && !field.sheet && !(next && (field.pop.contains(next) || next === field.input))) {
            field.restore();
        }
    });

    // Empties a picker field from a screen's script (a typed address that
    // replaces a chosen page, the other of two exclusive pages): the field
    // under or around the given node, which fires change when it held a value.
    function clear(target) {
        var node = (target && target.closest) ? (target.closest('[data-pg-picker-field]') || target.querySelector('[data-pg-picker-field]')) : null;
        var field = node ? fieldOf(node) : null;
        if (field && field.value.value !== '') {
            field.set(null);
        }
    }

    root.pgPicker = { open: open, clear: clear, pure: pure };
})(typeof window !== 'undefined' ? window : this);
