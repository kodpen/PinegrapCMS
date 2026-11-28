/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "New collection with pages": the Pages window's
 * second view. Step 1 asks a new collection's name and fields (the Title
 * first and fixed, at most twelve) or takes a collection that is there;
 * step 2 names the list page and the detail page and the group they go to.
 * "Create" makes the collection, its list page and its detail page one at
 * a time (designer/collection_pages, includes/fn/collection_pages.php),
 * each opened as a saved tab; a stop half way keeps what was made and "Try
 * again" makes only what is missing. The window ends on one line naming
 * what was made, each name a link. The widget layouts are the core's own
 * (the form built from fields, the list and detail starters), handed in
 * through the host (_pgCollectionHost()). Not needed to open a page: the
 * core runs this file the first time the row is chosen (_sdPartLoad()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('collection', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    var MAX_FIELDS = 12;
    var KINDS = ['text', 'rich', 'number', 'date', 'image', 'file', 'option', 'reference'];
    var ICONS = { text: 'bi-fonts', rich: 'bi-text-paragraph', number: 'bi-123', date: 'bi-calendar3', image: 'bi-image',
                  file: 'bi-paperclip', option: 'bi-ui-radios', reference: 'bi-link-45deg' };
    // How each kind is stored: the field type and, where the type alone
    // does not say it, the collection kind.
    var FIELD_TYPES = {
        text: { type: 'text box' }, rich: { type: 'text area', wysiwyg: 1 }, number: { type: 'text box', kind: 'number' },
        date: { type: 'date' }, image: { type: 'file upload', kind: 'image' }, file: { type: 'file upload' },
        option: { type: 'pick list' }, reference: { type: 'pick list', kind: 'reference' }
    };
    // What was typed, kept while the Pages window is open (S.win).
    var S = null;

    function _ncKindLabel(k) {
        return { text: _sdT('Text'), rich: _sdT('Rich text'), number: _sdT('Number'), date: _sdT('Date'), image: _sdT('Image'),
                 file: _sdT('File'), option: _sdT('Option set'), reference: _sdT('Reference') }[k] || k;
    }

    // ── Pure parts ──

    // A value put into a sentence with String.replace(), kept as typed: a
    // function's answer is not read for "$&", "$$" and the like.
    function _ncLit(value) {
        return function () { return String(value); };
    }

    // An address made from a name: lower case, Turkish letters and letters
    // with a mark as the plain letter, a hyphen for every run of anything
    // else, at most 100 characters.
    function _ncSlug(text) {
        var from = 'ıİIşŞğĞçÇöÖüÜâÂîÎûÛ', to = 'iiissggccoouuaaiiuu';
        var s = String(text == null ? '' : text), out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i), k = from.indexOf(c);
            if (k !== -1) { out += to.charAt(k); continue; }
            c = c.toLowerCase();
            if (c.normalize) c = c.normalize('NFD').charAt(0) || c;
            out += /[a-z0-9]/.test(c) ? c : '-';
        }
        return out.replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 100).replace(/-$/, '');
    }

    // The first of base, base-2, base-3… not in taken (lower case).
    function _ncFree(base, taken) {
        base = base || 'page';
        var t = {};
        (taken || []).forEach(function (n) { t[String(n).toLowerCase()] = true; });
        if (!t[base]) return base;
        for (var n = 2; ; n++) if (!t[base + '-' + n]) return base + '-' + n;
    }

    // The address page k ('list' or 'detail') takes from its name while
    // none was typed: free of the design's pages and of the other page.
    function _ncFollow(pages, k, title, taken) {
        var other = pages[k === 'list' ? 'detail' : 'list'];
        return _ncFree(_ncSlug(title), (taken || []).concat([String(other.name).trim()]));
    }

    // A field's name in the form, made from its label and kept apart from
    // the names used before it.
    function _ncKey(label, used) {
        var base = _ncSlug(label).replace(/-/g, '_') || 'field';
        var key = base;
        for (var n = 2; used.indexOf(key) !== -1; n++) key = base + '_' + n;
        used.push(key);
        return key;
    }

    // What step 1 refuses: {name: the collection name that is taken (its
    // collection), dup: a field name used twice, next: whether "Next" is on}.
    // fields: [{name, kind}] without the Title.
    function _ncCheck(state, collections) {
        var out = { taken: null, dup: '', next: false };
        if (state.choice === 'there') { out.next = !!state.chosen; return out; }
        var name = String(state.name || '').trim();
        (collections || []).forEach(function (c) {
            if (name && String(c.name).toLowerCase() === name.toLowerCase()) out.taken = c;
        });
        // The locked line shows the Title in the editor's language.
        var seen = { title: true };
        seen[String(_sdT('Title')).trim().toLowerCase()] = true;
        (state.fields || []).forEach(function (f) {
            var n = String(f.name || '').trim().toLowerCase();
            if (!n) return;
            if (seen[n] && !out.dup) out.dup = String(f.name).trim();
            seen[n] = true;
        });
        out.next = !!name && !out.taken && !out.dup;
        return out;
    }

    // The fields of a new collection as the form builder takes them: the
    // Title first, then each named line by its kind. refs: the collections
    // a reference may point into ({page_id}).
    function _ncFieldDefs(fields) {
        var used = ['title'];
        var out = [{ name: 'title', label: _sdT('Title'), type: 'text box', rss_field: 'title', required: true }];
        (fields || []).forEach(function (f) {
            var label = String(f.name || '').trim();
            if (!label) return;
            var d = { name: _ncKey(label, used), label: label };
            var base = FIELD_TYPES[f.kind] || FIELD_TYPES.text;
            Object.keys(base).forEach(function (k) { d[k] = base[k]; });
            if (f.kind === 'option') {
                d.options = String(f.options || '').split(',').map(function (o) { return o.trim(); })
                    .filter(function (o, i, a) { return o && a.indexOf(o) === i; })
                    .map(function (o) { return { value: o, label: o }; });
            } else if (f.kind === 'reference') {
                if (parseInt(f.ref, 10) > 0) d.reference_page_id = parseInt(f.ref, 10);
                else d = { name: d.name, label: label, type: 'text box' };
            }
            out.push(d);
        });
        return out;
    }

    // The fields a list's card shows: the Title, the first image, the first
    // text or option field. An image reads as one to the layout builder
    // (rss_field 'media' there, not stored).
    function _ncListDefs(defs) {
        var title = null, image = null, text = null;
        defs.forEach(function (d) {
            if (!title && d.rss_field === 'title') title = d;
            else if (!image && d.kind === 'image') image = d;
            else if (!text && !d.kind && (d.type === 'text box' || d.type === 'text area' || d.type === 'pick list' || d.type === 'radio button' || d.type === 'check box')) text = d;
        });
        return [title, image, text].filter(Boolean).map(_ncShowDef);
    }
    function _ncShowDef(d) {
        var c = {};
        Object.keys(d).forEach(function (k) { c[k] = d[k]; });
        if (c.kind === 'image') c.rss_field = 'media';
        return c;
    }

    // The closing line as HTML, each name a link (data-open: a page id).
    // made: {collection, list, detail} of {page_id, title}; coll: the
    // collection's {name, page_id}; fields: its count; isNew: made here.
    function _ncMadeHtml(made, coll, fields, isNew) {
        var link = function (m, text) { return '<a href="#" data-open="' + m.page_id + '">“' + esc(text) + '”</a>'; };
        var parts = [];
        if (isNew && made.collection) {
            parts.push(esc(_sdT('the collection {c} with {n} fields')).replace('{c}', _ncLit(link(made.collection, coll.name))).replace('{n}', String(fields)));
        }
        if (made.list) parts.push(esc(_sdT('the list page {p}')).replace('{p}', _ncLit(link(made.list, made.list.title))));
        if (made.detail) parts.push(esc(_sdT('the detail page {p}')).replace('{p}', _ncLit(link(made.detail, made.detail.title))));
        if (!parts.length) return '';
        var joined = parts.length === 1 ? parts[0]
            : parts.slice(0, -1).join(', ') + esc(_sdT(' and ')) + parts[parts.length - 1];
        if (isNew) return esc(_sdT('Made {list}.')).replace('{list}', _ncLit(joined));
        return esc(_sdT('Made {list} for the collection {c}.')).replace('{list}', _ncLit(joined))
            .replace('{c}', _ncLit(link({ page_id: coll.page_id }, coll.name)));
    }

    // What a collection that is there reads as: "5 fields · 3 items · shown
    // on “Classes”, “Class”".
    function _ncCollMeta(c) {
        var pages = (c.pages || []).map(function (p) { return '“' + p + '”'; }).join(', ');
        return _sdT('{n} fields').replace('{n}', c.fields.length) + ' · ' + _sdT('{n} items').replace('{n}', c.items) + ' · ' +
               (pages ? _sdT('shown on {pages}').replace('{pages}', _ncLit(pages)) : _sdT('on no page yet'));
    }

    // ── State ──

    function _ncFresh(win, host) {
        return { win: win, host: host, view: null, step: 1, choice: 'new', name: '', fields: [], chosen: null,
                 collections: null, pages: null, group: '', taken: {}, made: {}, groupId: '', error: '', busy: false };
    }

    function _ncColl() {
        if (S.choice === 'there') return S.chosen;
        return { name: String(S.name).trim(), page_id: S.made.collection ? S.made.collection.page_id : 0, items: 0 };
    }

    function _ncDefs() {
        if (S.choice === 'there') return S.chosen ? S.chosen.fields : [];
        return _ncFieldDefs(S.fields);
    }

    // The two pages' starting names and addresses, free of every page of
    // the design and of each other.
    function _ncPagesStart() {
        var name = _ncColl().name;
        var taken = S.host.addresses();
        var list = _ncFree(_ncSlug(name), taken);
        S.pages = {
            list: { title: name, name: list, typed: false },
            detail: { title: name, name: _ncFree(_ncSlug(name), taken.concat([list])), typed: false }
        };
        S.group = S.choice === 'there' ? '' : 'new';
    }

    // ── Drawing ──

    function _ncDraw() {
        var v = S.view;
        var making = S.step === 'making', done = S.step === 'done';
        var head = '<div class="sd-pw-head sd-nc-head">' +
            (making || done ? '<span class="bi bi-collection sd-pw-head-icon" aria-hidden="true"></span>'
                            : '<button type="button" class="sd-pw-close sd-nc-back" data-act="back" title="' + esc(_sdT('Back')) + '" aria-label="' + esc(_sdT('Back')) + '"><span class="bi bi-arrow-left" aria-hidden="true"></span></button>') +
            '<span class="sd-pw-title">' + esc(_sdT('New collection with pages')) + '</span>' +
            (typeof S.step === 'number' ? '<span class="sd-nc-step">' + esc(_sdT('{n} of 2').replace('{n}', S.step)) + '</span>' : '') + '</div>';
        var body = S.step === 1 ? _ncStep1() : S.step === 2 ? _ncStep2() : _ncMaking();
        v.setAttribute('aria-label', typeof S.step === 'number'
            ? _sdT('New collection with pages, step {n} of 2').replace('{n}', S.step) : _sdT('New collection with pages'));
        v.innerHTML = head + '<div class="sd-nc-body">' + body + '</div>' + _ncFoot() + '<div class="sd-nc-live visually-hidden" aria-live="polite"></div>';
    }

    function _ncStep1() {
        var chk = _ncCheck(S, S.collections);
        var h = '<div class="sd-nc-choice" role="radiogroup">' +
            ['new', 'there'].map(function (c) {
                return '<button type="button" role="radio" class="sd-nc-choose' + (S.choice === c ? ' active' : '') + '" data-choice="' + c + '" aria-checked="' + (S.choice === c) + '" tabindex="' + (S.choice === c ? 0 : -1) + '">' +
                       esc(c === 'new' ? _sdT('A new collection') : _sdT('One that is there')) + '</button>';
            }).join('') + '</div>';
        if (S.choice === 'there') {
            if (!S.collections) return h + '<div class="sd-pw-empty">' + esc(_sdT('Loading...')) + '</div>';
            if (!S.collections.length) return h + '<div class="sd-pw-empty">' + esc(_sdT('The site has no collection yet.')) + '</div>';
            h += '<div class="sd-nc-colls" role="listbox" aria-label="' + esc(_sdT('One that is there')) + '">' + S.collections.map(function (c, i) {
                var on = S.chosen && S.chosen.page_id === c.page_id;
                return '<button type="button" role="option" class="sd-nc-coll' + (on ? ' active' : '') + '" data-coll="' + i + '" aria-selected="' + !!on + '">' +
                       '<span class="bi bi-collection" aria-hidden="true"></span><span><span class="sd-nc-coll-name">' + esc(c.name) + '</span>' +
                       '<span class="sd-nc-sub">' + esc(_ncCollMeta(c)) + '</span></span>' + (on ? '<span class="bi bi-check2 ms-auto" aria-hidden="true"></span>' : '') + '</button>';
            }).join('') + '</div><p class="sd-nc-hint">' + esc(_sdT('The collection is not changed. Only its two pages are made.')) + '</p>';
            return h;
        }
        h += '<label class="sd-nc-label" for="sd-nc-name">' + esc(_sdT('Name')) + '</label>' +
             '<input type="text" class="sd-nc-input" id="sd-nc-name" maxlength="100" autocomplete="off" value="' + esc(S.name) + '" placeholder="' + esc(_sdT('Teachers, Recipes, Projects…')) + '"' + (chk.taken ? ' aria-invalid="true"' : '') + '>';
        if (chk.taken) {
            h += '<div class="sd-nc-bad" role="alert">' + esc(_sdT('A collection named “{name}” is there already.').replace('{name}', _ncLit(chk.taken.name))) +
                 ' <button type="button" class="sd-nc-link" data-act="use">' + esc(_sdT('Use it')) + '</button></div>';
        }
        var n = S.fields.length + 1;
        h += '<div class="sd-nc-label sd-nc-fields-head">' + esc(_sdT('Fields')) + '<span class="sd-nc-count">' + esc(_sdT('{n} of {max}').replace('{n}', n).replace('{max}', MAX_FIELDS)) + '</span></div>' +
             '<ol class="sd-nc-fields">' +
             '<li class="sd-nc-field sd-nc-locked"><span class="sd-nc-grip" aria-hidden="true"></span><input type="text" class="sd-nc-input sd-nc-fname" value="' + esc(_sdT('Title')) + '" readonly aria-label="' + esc(_sdT('Field {i} of {n}, {name}, {kind}').replace('{i}', 1).replace('{n}', n).replace('{name}', _ncLit(_sdT('Title'))).replace('{kind}', _ncLit(_sdT('Text')))) + '">' +
             '<span class="sd-nc-kind sd-nc-kind-fixed"><span class="bi bi-fonts" aria-hidden="true"></span>' + esc(_sdT('Text')) + '</span><span class="sd-nc-bin-gap"></span></li>';
        S.fields.forEach(function (f, i) {
            h += '<li class="sd-nc-field" data-f="' + i + '"><span class="sd-nc-grip bi bi-grip-vertical" draggable="true" aria-hidden="true"></span>' +
                 '<input type="text" class="sd-nc-input sd-nc-fname" maxlength="100" value="' + esc(f.name) + '" aria-label="' + esc(_sdT('Field {i} of {n}, {name}, {kind}').replace('{i}', i + 2).replace('{n}', n).replace('{name}', _ncLit(f.name || '')).replace('{kind}', _ncLit(_ncKindLabel(f.kind)))) + '"' +
                 (chk.dup && String(f.name).trim().toLowerCase() === chk.dup.toLowerCase() ? ' aria-invalid="true"' : '') + '>' +
                 '<button type="button" class="sd-nc-kind" data-kind="' + i + '" aria-haspopup="listbox"><span class="bi ' + ICONS[f.kind] + '" aria-hidden="true"></span>' + esc(_ncKindLabel(f.kind)) + '<span class="bi bi-chevron-down ms-auto" aria-hidden="true"></span></button>' +
                 '<button type="button" class="sd-nc-bin" data-bin="' + i + '" title="' + esc(_sdT('Remove')) + '" aria-label="' + esc(_sdT('Remove')) + '"><span class="bi bi-trash" aria-hidden="true"></span></button>';
            if (f.kind === 'option') {
                h += '<div class="sd-nc-subline"><span>' + esc(_sdT('Options')) + '</span><input type="text" class="sd-nc-input sd-nc-opts" data-opts="' + i + '" value="' + esc(f.options || '') + '" placeholder="' + esc(_sdT('Hatha, Vinyasa, Yin')) + '" aria-label="' + esc(_sdT('Options')) + '"></div>';
            } else if (f.kind === 'reference') {
                h += '<div class="sd-nc-subline"><span>' + esc(_sdT('Collection')) + '</span><select class="sd-nc-input sd-nc-ref" data-ref="' + i + '" aria-label="' + esc(_sdT('Collection')) + '">' +
                     '<option value="">' + esc(_sdT('Choose…')) + '</option>' + (S.collections || []).map(function (c) {
                         return '<option value="' + c.page_id + '"' + (String(f.ref) === String(c.page_id) ? ' selected' : '') + '>' + esc(c.name) + '</option>';
                     }).join('') + '</select></div>';
            }
            h += '</li>';
        });
        h += '</ol>';
        if (chk.dup) h += '<div class="sd-nc-bad" role="alert">' + esc(_sdT('Two fields are named “{name}”.').replace('{name}', _ncLit(chk.dup))) + '</div>';
        h += '<button type="button" class="sd-nc-add" data-act="add"' + (n >= MAX_FIELDS ? ' disabled' : '') + '><span class="bi bi-plus-lg" aria-hidden="true"></span>' + esc(_sdT('Add field')) + '</button>';
        h += '<p class="sd-nc-hint">' + esc(n >= MAX_FIELDS ? _sdT('Twelve fields here. More are added on the collection’s page afterwards.')
                                                           : _sdT('Every item has a Title: it is the item’s name in lists and makes its address.')) + '</p>';
        return h;
    }

    function _ncStep2() {
        var c = _ncColl();
        var isNew = S.choice === 'new';
        var h = '<div class="sd-nc-collline"><span class="bi bi-collection" aria-hidden="true"></span><span><span class="sd-nc-coll-name">' + esc(c.name) + '</span>' +
                '<span class="sd-nc-sub">' + esc(isNew ? _sdT('A new collection · {n} fields').replace('{n}', _ncDefs().length)
                                                       : _sdT('A collection that is there · {n} fields, {m} items').replace('{n}', c.fields.length).replace('{m}', c.items)) + '</span></span>' +
                '<button type="button" class="sd-nc-link ms-auto" data-act="back">' + esc(_sdT('Change')) + '</button></div>' +
                '<div class="sd-nc-label">' + esc(_sdT('Pages')) + '</div>';
        ['list', 'detail'].forEach(function (k) {
            var p = S.pages[k], bad = _ncPageBad(k);
            h += '<div class="sd-nc-page"><span class="sd-nc-page-label">' + esc(k === 'list' ? _sdT('List page') : _sdT('Detail page')) + '</span>' +
                 '<input type="text" class="sd-nc-input" data-ptitle="' + k + '" maxlength="100" value="' + esc(p.title) + '" aria-label="' + esc(k === 'list' ? _sdT('List page') : _sdT('Detail page')) + '"' + (bad === 'title' ? ' aria-invalid="true"' : '') + '>' +
                 '<span class="sd-nc-addr"><span aria-hidden="true">/</span><input type="text" class="sd-nc-input" data-pname="' + k + '" maxlength="100" value="' + esc(p.name) + '" aria-label="' + esc(_sdT('Address')) + '"' + (bad && bad !== 'title' ? ' aria-invalid="true"' : '') + '></span></div>';
            if (bad && bad !== 'title') h += '<div class="sd-nc-bad" role="alert">' + esc(bad) + '</div>';
        });
        h += '<label class="sd-nc-label" for="sd-nc-group">' + esc(_sdT('Group')) + '</label><select class="sd-nc-input" id="sd-nc-group">' +
             (isNew ? '<option value="new"' + (S.group === 'new' ? ' selected' : '') + '>' + esc(_sdT('{name} (a new group)').replace('{name}', _ncLit(c.name))) + '</option>' : '') +
             S.host.groups().map(function (g) { return '<option value="' + esc(g.id) + '"' + (S.group === g.id ? ' selected' : '') + '>' + esc(g.name) + '</option>'; }).join('') +
             '<option value=""' + (S.group === '' ? ' selected' : '') + '>' + esc(_sdT('Other')) + '</option></select>' +
             '<p class="sd-nc-hint">' + esc(_sdT('The list page shows the items; an item opens on the detail page. Both are pages like any other afterwards.')) + '</p>';
        return h;
    }

    // Why a page's line is not right: 'title' (no name), a sentence about
    // its address, or ''.
    function _ncPageBad(k) {
        var p = S.pages[k], other = S.pages[k === 'list' ? 'detail' : 'list'];
        if (!String(p.title).trim()) return 'title';
        var a = String(p.name).trim();
        if (!a || /[\s\/?#\\<>"%]/.test(a)) return _sdT('Give the page an address.');
        if (a.toLowerCase() === String(other.name).trim().toLowerCase() && k === 'detail') return _sdT('The two pages have one address.');
        if (S.host.addresses().indexOf(a.toLowerCase()) !== -1 || Object.prototype.hasOwnProperty.call(S.taken, a)) {
            var owner = S.taken[a];
            return owner ? _sdT('“/{addr}” is the address of the page “{page}”.').replace('{addr}', _ncLit(a)).replace('{page}', _ncLit(owner))
                         : _sdT('The address “/{addr}” is taken.').replace('{addr}', _ncLit(a));
        }
        return '';
    }

    function _ncMaking() {
        var c = _ncColl(), made = S.made;
        if (S.step === 'done') {
            var h = '<div class="sd-nc-done" role="status">' + _ncMadeHtml(made, c, _ncDefs().length, S.choice === 'new') + '</div>';
            if (c.page_id && !c.items) {
                h += '<p class="sd-nc-hint">' + esc(_sdT('The list is empty until the collection has an item.')) +
                     ' <a href="' + esc(S.host.itemUrl(c.page_id)) + '" target="_blank" rel="noopener">' + esc(_sdT('Add the first item')) + '</a></p>';
            }
            return h;
        }
        var lines = _ncSteps().map(function (what) {
            var m = made[what], now = S.busy === what, fail = S.error && S.failed === what;
            var label = what === 'collection' ? _sdT('The collection “{name}”').replace('{name}', _ncLit(c.name))
                      : (what === 'list' ? _sdT('The list page “{name}”') : _sdT('The detail page “{name}”')).replace('{name}', _ncLit(S.pages[what].title));
            var sub = what === 'collection' ? _sdT('{n} fields').replace('{n}', _ncDefs().length) : '/' + S.pages[what].name;
            return '<li class="sd-nc-made' + (m ? ' ok' : fail ? ' bad' : '') + '">' +
                   (m ? '<span class="bi bi-check-lg" aria-hidden="true"></span>' : now ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>'
                      : fail ? '<span class="bi bi-x-lg" aria-hidden="true"></span>' : '<span class="sd-nc-wait" aria-hidden="true"></span>') +
                   '<span>' + esc(label) + '</span><span class="sd-nc-sub ms-auto">' + esc(fail ? _sdT('not made') : sub) + '</span></li>';
        }).join('');
        return '<ol class="sd-nc-mades">' + lines + '</ol>' + (S.error ? '<div class="sd-nc-bad" role="alert">' + esc(S.error) + '</div>' : '');
    }

    function _ncFoot() {
        var note = '<span class="sd-nc-note">' + esc(_sdT('Nothing is made until “Create”.')) + '</span>';
        var btn = function (act, label, on, primary) {
            return '<button type="button" class="sd-nc-btn' + (primary ? ' sd-nc-primary' : '') + '" data-act="' + act + '"' + (on ? '' : ' disabled') + '>' + esc(label) + '</button>';
        };
        if (S.step === 1) return '<div class="sd-pw-foot sd-nc-foot">' + note + btn('next', _sdT('Next'), _ncCheck(S, S.collections).next, true) + '</div>';
        if (S.step === 2) {
            var ok = !_ncPageBad('list') && !_ncPageBad('detail');
            return '<div class="sd-pw-foot sd-nc-foot">' + note + btn('back', _sdT('Back'), true) + btn('create', _sdT('Create'), ok, true) + '</div>';
        }
        if (S.step === 'done') return '<div class="sd-pw-foot sd-nc-foot">' + btn('done', _sdT('Done'), true, true) + '</div>';
        if (S.error) return '<div class="sd-pw-foot sd-nc-foot">' + btn('close', _sdT('Close'), true) + btn('again', _sdT('Try again'), true, true) + '</div>';
        return '<div class="sd-pw-foot sd-nc-foot"><span class="sd-nc-note">' + esc(_sdT('Making…')) + '</span>' + btn('create', _sdT('Create'), false, true) + '</div>';
    }

    function _ncSay(text) {
        var live = S.view && S.view.querySelector('.sd-nc-live');
        if (live) { live.textContent = ''; setTimeout(function () { live.textContent = text; }, 30); }
    }

    // Draws again with the pointer kept in the box it was in.
    function _ncRedraw(focusSel) {
        var a = document.activeElement, want = focusSel || null, pos = null;
        if (!want && a && S.view.contains(a)) {
            if (a.id) want = '#' + a.id;
            else ['data-f', 'data-opts', 'data-ref', 'data-ptitle', 'data-pname', 'data-choice', 'data-act'].forEach(function (k) {
                if (!want && a.hasAttribute(k)) want = '[' + k + '="' + a.getAttribute(k) + '"]';
            });
            if (!want && a.closest('[data-f]')) want = '[data-f="' + a.closest('[data-f]').getAttribute('data-f') + '"] .sd-nc-fname';
            if (a.selectionStart != null) pos = a.selectionStart;
        }
        _ncDraw();
        var el = want ? S.view.querySelector(want) : null;
        if (el && !el.disabled) {
            el.focus({ preventScroll: true });
            if (pos !== null && el.setSelectionRange) try { el.setSelectionRange(pos, pos); } catch (e) { /* not a text box */ }
        }
    }

    // ── Making ──

    function _ncSteps() { return S.choice === 'new' ? ['collection', 'list', 'detail'] : ['list', 'detail']; }

    function _ncRequest(what) {
        var h = S.host, defs = _ncDefs(), c = _ncColl();
        var cid = what === 'collection' ? 0 : c.page_id;
        var req = { what: what, style_id: h.styleId, draft: h.draft ? 1 : 0, collection_page_id: cid };
        var tree, cfg;
        if (what === 'collection') {
            var title = _sdT('{name} collection').replace('{name}', _ncLit(c.name));
            req.collection = c.name;
            req.title = title;
            req.name = _ncFree(_ncSlug(title), h.addresses().concat([S.pages.list.name, S.pages.detail.name]));
            cfg = { regionType: 'custom_form', form_source: 'page' };
            tree = h.formTree(defs, c.name);
        } else {
            req.title = String(S.pages[what].title).trim();
            req.name = String(S.pages[what].name).trim();
            cfg = h.config(what === 'list' ? 'form_list_view' : 'form_item_view');
            cfg.custom_form_page_id = cid;
            if (what === 'list') {
                tree = h.starter('form_list_view', _ncListDefs(defs));
                _ncLinkCards(tree);
            } else {
                var t = defs.filter(function (d) { return d.rss_field === 'title'; })[0];
                if (t) cfg.title_field = t.name;
                tree = h.starter('form_item_view', defs.map(_ncShowDef));
                _ncRefNames(tree, defs);
                if (S.made.list) req.list_widget_id = S.made.list.widget_id;
            }
        }
        req.widget_tree = JSON.stringify(tree);
        req.widget_config = JSON.stringify(cfg);
        return req;
    }

    // Each card of the list opens its item: a link over the whole card.
    function _ncLinkCards(tree) {
        (function walk(n) {
            if (n.props && n.props.cssClass === 'card-body') {
                n.children.push(S.host.node('content', { contentType: 'link', text: _sdT('Open'), href: '#',
                    cssClass: 'card-link stretched-link small', customName: _sdT('Open'), _bindings: { href: 'form_item_view' } }));
                return;
            }
            (n.children || []).forEach(walk);
        })(tree);
    }

    // A reference prints the Title of what it points at, not its code.
    function _ncRefNames(tree, defs) {
        var refs = {};
        defs.forEach(function (d) { if (d.kind === 'reference' && d.reference_page_id) refs[d.name] = d.reference_page_id; });
        (function walk(n) {
            var b = n.props && n.props._bindings;
            if (b && refs[b.text]) {
                n.props._bindParams = { text: { field: b.text, page_id: refs[b.text], show: 'title' } };
                b.text = '__ref';
            }
            (n.children || []).forEach(walk);
        })(tree);
    }

    function _ncRun() {
        S.step = 'making';
        S.error = '';
        var todo = _ncSteps().filter(function (w) { return !S.made[w]; });
        _ncDraw();
        _ncSay(_sdT('Making the collection, the list page and the detail page'));
        var next = function () {
            if (!S || S.step !== 'making') return;
            var what = todo.shift();
            if (!what) { _ncFinish(); return; }
            S.busy = what;
            _ncDraw();
            var req = _ncRequest(what);
            var group = S.groupId ? { id: S.groupId } : S.group === 'new' ? { name: _ncColl().name } : { id: S.group };
            S.host.api('make', req).then(function (res) {
                if (!res || res.status !== 'success') throw new Error((res && res.message) || _sdT('The operation failed.'));
                S.made[what] = { page_id: res.page_id, widget_id: res.widget_id, title: req.title };
                return S.host.add(res.page_id, group).then(function (groupId) { if (groupId) S.groupId = groupId; }, function () { /* opened on the next load */ });
            }).then(next, function (err) {
                S.busy = false;
                S.failed = what;
                var name = what === 'collection' ? _sdT('The collection') : what === 'list' ? _sdT('The list page') : _sdT('The detail page');
                S.error = _sdT('{thing} was not made: {why}. What was made stays.').replace('{thing}', _ncLit(name))
                    .replace('{why}', _ncLit(String((err && err.message && !/fetch|network/i.test(err.message)) ? err.message : _sdT('the connection was lost')).replace(/\.$/, '')));
                _ncDraw();
                _ncSay(S.error);
            });
        };
        next();
    }

    function _ncFinish() {
        S.busy = false;
        S.step = 'done';
        _ncDraw();
        var done = S.view.querySelector('.sd-nc-done');
        _ncSay(done ? done.textContent : '');
        var b = S.view.querySelector('[data-act="done"]');
        if (b) b.focus({ preventScroll: true });
    }

    // ── Events ──

    function _ncLoadCollections() {
        if (S.collections) return;
        var s = S;
        s.host.api('list', {}).then(function (res) {
            if (S !== s) return;
            S.collections = (res && res.status === 'success' && Array.isArray(res.collections)) ? res.collections : [];
            _ncRedraw();
        }, function () { if (S === s) { S.collections = []; _ncRedraw(); } });
    }

    var _ncTakenTimer = 0;
    // Whether the two addresses are taken anywhere on the site, asked once
    // the hand rests.
    function _ncAskTaken() {
        clearTimeout(_ncTakenTimer);
        var s = S;
        _ncTakenTimer = setTimeout(function () {
            if (S !== s || S.step !== 2) return;
            var names = [S.pages.list.name, S.pages.detail.name].map(function (n) { return String(n).trim(); });
            S.host.api('taken', { names: names }).then(function (res) {
                if (S !== s || !res || res.status !== 'success') return;
                names.forEach(function (n) { delete S.taken[n]; });
                Object.keys(res.taken || {}).forEach(function (n) { S.taken[n] = res.taken[n]; });
                _ncRedraw();
            }, function () { /* checked again by Create */ });
        }, 300);
    }

    function _ncBack() {
        if (S.step === 2) { S.step = 1; _ncRedraw('[data-act="next"]'); return; }
        if (S.step === 1 || S.step === 'done') {
            // After the closing line the window starts afresh, on the same
            // view: the fresh state has none of its own.
            if (S.step === 'done') { var v = S.view; S = _ncFresh(S.win, S.host); S.view = v; }
            S.view.hidden = true;
            S.host.back();
        }
    }

    function _ncAct(act, el) {
        if (act === 'back') _ncBack();
        else if (act === 'next') {
            if (!_ncCheck(S, S.collections).next) return;
            if (!S.pages || S.pagesFor !== (S.choice === 'there' ? 'c' + S.chosen.page_id : 'n' + _ncColl().name)) {
                _ncPagesStart();
                S.pagesFor = S.choice === 'there' ? 'c' + S.chosen.page_id : 'n' + _ncColl().name;
            }
            S.step = 2;
            _ncRedraw('[data-ptitle="list"]');
            _ncAskTaken();
        } else if (act === 'create') { if (S.step === 2) _ncRun(); }
        else if (act === 'again') _ncRun();
        else if (act === 'close') {
            if (_ncSteps().some(function (w) { return S.made[w]; })) _ncFinish();
            else { S.step = 2; S.error = ''; _ncRedraw(); }
        } else if (act === 'done') _ncBack();
        else if (act === 'add') _ncAddField(S.fields.length);
        else if (act === 'use') {
            var chk = _ncCheck(S, S.collections);
            S.choice = 'there';
            S.chosen = chk.taken;
            _ncRedraw('[data-choice="there"]');
        }
    }

    function _ncAddField(at) {
        if (S.fields.length + 1 >= MAX_FIELDS) return;
        S.fields.splice(at, 0, { name: '', kind: 'text', options: '', ref: '' });
        _ncRedraw('[data-f="' + at + '"] .sd-nc-fname');
    }

    // Drops the lines left without a name, but the one being typed in.
    function _ncTidy(keep) {
        S.fields = S.fields.filter(function (f, i) { return i === keep || String(f.name).trim(); });
    }

    function _ncMove(i, to) {
        if (to < 0 || to >= S.fields.length || i === to) return;
        var f = S.fields.splice(i, 1)[0];
        S.fields.splice(to, 0, f);
        _ncRedraw('[data-f="' + to + '"] .sd-nc-fname');
        _ncSay(_sdT('{name}, place {i} of {n}').replace('{name}', _ncLit(f.name)).replace('{i}', to + 2).replace('{n}', S.fields.length + 1));
    }

    function _ncKindMenu(i, anchor) {
        _ncKindClose();
        var m = document.createElement('div');
        m.className = 'sd-pw-menu sd-nc-kinds';
        m.setAttribute('role', 'listbox');
        m.innerHTML = KINDS.map(function (k) {
            return '<button type="button" role="option" class="sd-pw-menu-item" data-k="' + k + '" aria-selected="' + (S.fields[i].kind === k) + '"><span class="bi ' + ICONS[k] + '" aria-hidden="true"></span>' + esc(_ncKindLabel(k)) + '</button>';
        }).join('');
        S.view.appendChild(m);
        var vr = S.view.getBoundingClientRect(), ar = anchor.getBoundingClientRect();
        m.style.top = (ar.bottom - vr.top + 2) + 'px';
        m.style.left = Math.max(4, Math.min(ar.left - vr.left, vr.width - 194)) + 'px';
        var cur = m.querySelector('[aria-selected="true"]') || m.querySelector('button');
        cur.focus();
        m.addEventListener('click', function (e) {
            var b = e.target.closest('[data-k]');
            if (!b) return;
            S.fields[i].kind = b.getAttribute('data-k');
            _ncKindClose();
            if (S.fields[i].kind === 'reference') _ncLoadCollections();
            _ncRedraw('[data-kind="' + i + '"]');
        });
        m.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); _ncKindClose(); anchor.focus(); return; }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            var btns = [].slice.call(m.querySelectorAll('button'));
            var at = btns.indexOf(document.activeElement) + (e.key === 'ArrowDown' ? 1 : -1);
            btns[(at + btns.length) % btns.length].focus();
        });
    }
    function _ncKindClose() {
        var m = S && S.view && S.view.querySelector('.sd-nc-kinds');
        if (m) { m.remove(); return true; }
        return false;
    }

    function _ncBind(v) {
        v.addEventListener('click', function (e) {
            var t = e.target;
            if (!t.closest('.sd-nc-kinds')) _ncKindClose();
            var b = t.closest('[data-open]');
            if (b) { e.preventDefault(); var pid = parseInt(b.getAttribute('data-open'), 10), host = S.host; _ncBack(); host.open(pid); return; }
            b = t.closest('[data-choice]');
            if (b) { S.choice = b.getAttribute('data-choice'); if (S.choice === 'there') _ncLoadCollections(); _ncRedraw('[data-choice="' + S.choice + '"]'); return; }
            b = t.closest('[data-coll]');
            if (b) { S.chosen = S.collections[parseInt(b.getAttribute('data-coll'), 10)]; _ncRedraw('[data-coll="' + b.getAttribute('data-coll') + '"]'); return; }
            b = t.closest('[data-kind]');
            if (b) { e.stopPropagation(); _ncKindMenu(parseInt(b.getAttribute('data-kind'), 10), b); return; }
            b = t.closest('[data-bin]');
            if (b) { S.fields.splice(parseInt(b.getAttribute('data-bin'), 10), 1); _ncRedraw('[data-act="add"]'); return; }
            b = t.closest('[data-act]');
            if (b && !b.disabled) _ncAct(b.getAttribute('data-act'), b);
        });
        v.addEventListener('input', function (e) {
            var t = e.target, li = t.closest('[data-f]');
            if (t.id === 'sd-nc-name') S.name = t.value;
            else if (t.classList.contains('sd-nc-fname') && li) S.fields[parseInt(li.getAttribute('data-f'), 10)].name = t.value;
            else if (t.hasAttribute('data-opts')) { S.fields[parseInt(t.getAttribute('data-opts'), 10)].options = t.value; return; }
            else if (t.hasAttribute('data-ptitle')) {
                var p = S.pages[t.getAttribute('data-ptitle')];
                p.title = t.value;
                // The address follows the name until one is typed by hand.
                if (!p.typed) p.name = _ncFollow(S.pages, t.getAttribute('data-ptitle'), t.value, S.host.addresses());
                _ncAskTaken();
            } else if (t.hasAttribute('data-pname')) { S.pages[t.getAttribute('data-pname')].name = t.value; S.pages[t.getAttribute('data-pname')].typed = true; _ncAskTaken(); }
            else return;
            _ncRedraw();
        });
        v.addEventListener('change', function (e) {
            var t = e.target;
            if (t.hasAttribute('data-ref')) S.fields[parseInt(t.getAttribute('data-ref'), 10)].ref = t.value;
            else if (t.id === 'sd-nc-group') S.group = t.value;
        });
        v.addEventListener('focusout', function (e) {
            var li = e.target.closest && e.target.closest('[data-f]');
            if (!li || (e.relatedTarget && li.contains(e.relatedTarget)) || (e.relatedTarget && e.relatedTarget.closest('.sd-nc-kinds'))) return;
            var n = S.fields.length;
            _ncTidy(-1);
            if (S.fields.length !== n) setTimeout(function () { if (S) _ncRedraw(); }, 0);
        });
        v.addEventListener('keydown', function (e) {
            var t = e.target, li = t.closest && t.closest('[data-f]');
            e.stopPropagation();
            if (e.key === 'Escape') {
                e.preventDefault();
                if (_ncKindClose()) return;
                if (S.step === 'making') return;
                _ncBack();
                return;
            }
            var ch = t.closest && t.closest('[data-choice]');
            if (ch && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
                e.preventDefault();
                S.choice = S.choice === 'new' ? 'there' : 'new';
                if (S.choice === 'there') _ncLoadCollections();
                _ncRedraw('[data-choice="' + S.choice + '"]');
                return;
            }
            if (li && e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
                e.preventDefault();
                var i = parseInt(li.getAttribute('data-f'), 10);
                _ncMove(i, i + (e.key === 'ArrowUp' ? -1 : 1));
                return;
            }
            if (e.key === 'Enter' && li && t.classList.contains('sd-nc-fname')) {
                e.preventDefault();
                if (String(t.value).trim()) _ncAddField(parseInt(li.getAttribute('data-f'), 10) + 1);
                return;
            }
            if (e.key === 'Enter' && t.id === 'sd-nc-name') { e.preventDefault(); _ncAct('next'); }
        });
        // A line dragged by its grip to its place.
        var from = -1;
        v.addEventListener('dragstart', function (e) {
            var li = e.target.closest && e.target.closest('[data-f]');
            if (!li || !e.target.classList.contains('sd-nc-grip')) return;
            from = parseInt(li.getAttribute('data-f'), 10);
            try { e.dataTransfer.setData('text/plain', String(from)); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setDragImage(li, 10, 10); } catch (x) { /* older browsers */ }
        });
        v.addEventListener('dragover', function (e) { if (from >= 0 && e.target.closest && e.target.closest('[data-f]')) e.preventDefault(); });
        v.addEventListener('drop', function (e) {
            var li = e.target.closest && e.target.closest('[data-f]');
            if (from < 0 || !li) return;
            e.preventDefault();
            var to = parseInt(li.getAttribute('data-f'), 10), f0 = from;
            from = -1;
            _ncMove(f0, to);
        });
        v.addEventListener('dragend', function () { from = -1; });
    }

    // Turns the Pages window `win` to the second view. What was typed is
    // kept while the same window stays open.
    function open(win, host) {
        if (!S || S.win !== win) S = _ncFresh(win, host);
        S.host = host;
        if (!S.view || !win.contains(S.view)) {
            S.view = document.createElement('div');
            S.view.className = 'sd-nc';
            S.view.setAttribute('role', 'group');
            win.appendChild(S.view);
            _ncBind(S.view);
        }
        S.view.hidden = false;
        if (S.step === 1 && S.choice === 'there') _ncLoadCollections();
        if (S.collections === null) _ncLoadCollections();
        _ncDraw();
        var first = S.view.querySelector(S.step === 1 ? (S.choice === 'new' ? '#sd-nc-name' : '[data-choice="there"]') : '[data-act]:not([disabled])');
        if (first) first.focus({ preventScroll: true });
    }

    return { open: open };
});
