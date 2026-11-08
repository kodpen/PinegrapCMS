/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the code view part: switching the HTML column's code
 * view on, the page's document with its range map and locks, the selection
 * both ways between the editor and the canvas, the file tabs (stylesheets,
 * scripts, layouts, dynamic regions) and the search in all tabs. The editor
 * runs this file the first time the view is switched on or a file tab opens
 * (_sdPartLoad(), pg_designer_parts()); the switch, the tab strip, the
 * completion sources and the typed-changes engine stay in the core, which
 * hands this file what it uses through the kit (_sdPartKit()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('code', function (K) {
    'use strict';

    var _SD_CODE_KEY = K._SD_CODE_KEY;
    var _pgToken = K._pgToken;
    var _sdCode = K._sdCode;
    var _sdCodeActiveTab = K._sdCodeActiveTab;
    var _sdCodeBoundProps = K._sdCodeBoundProps;
    var _sdCodeEnabled = K._sdCodeEnabled;
    var _sdCodeFind = K._sdCodeFind;
    var _sdCodeLabelSwitch = K._sdCodeLabelSwitch;
    var _sdCodeLockOf = K._sdCodeLockOf;
    var _sdCodeLockTitle = K._sdCodeLockTitle;
    var _sdCodeMayEdit = K._sdCodeMayEdit;
    var _sdCodeOnChange = K._sdCodeOnChange;
    var _sdCodeOnLockHit = K._sdCodeOnLockHit;
    var _sdCodeOpenTag = K._sdCodeOpenTag;
    var _sdCodePhrases = K._sdCodePhrases;
    var _sdCodeTabFind = K._sdCodeTabFind;
    var _sdCodeTabsRender = K._sdCodeTabsRender;
    var _sdCodeTextKind = K._sdCodeTextKind;
    var _sdDocLang = K._sdDocLang;
    var _sdEmitNode = K._sdEmitNode;
    var _sdFrameRowText = K._sdFrameRowText;
    var _sdLegacyRegionForget = K._sdLegacyRegionForget;
    var _sdPageBodyAttrs = K._sdPageBodyAttrs;
    var _sdPageFrameRows = K._sdPageFrameRows;
    var _sdSelectRender = K._sdSelectRender;
    var _sdT = K._sdT;
    var esc = K.esc;
    var renderCanvas = K.renderCanvas;
    var renderHtmlTree = K.renderHtmlTree;
    var scrollCanvasToNode = K.scrollCanvasToNode;
    var sdToast = K.sdToast;

    function _sdCodeToggle(on) {
        var quad = document.getElementById('sd-bp-quad-tl');
        var btn  = document.getElementById('sd-html-code-toggle');
        if (!quad || !_sdCode.host) return;
        if (!on) {
            _sdCode.on = false;
            quad.classList.remove('sd-code-on');
            if (btn) btn.classList.remove('active');
            try { localStorage.setItem(_SD_CODE_KEY, '0'); } catch (e) {}
            renderHtmlTree();
            return;
        }
        if (typeof PgCodeEditor === 'undefined') return;
        _sdCode.on = true;
        quad.classList.add('sd-code-on');
        if (btn) btn.classList.add('active');
        try { localStorage.setItem(_SD_CODE_KEY, '1'); } catch (e) {}
        if (_sdCode.ed) { _sdCodeRefresh(); return; }
        if (_sdCode.loading) return;
        _sdCode.loading = true;
        PgCodeEditor.open(_sdCode.pageHost, {
            language: 'html',
            value: '',
            readOnly: !_sdCodeMayEdit(),
            phrases: _sdCodePhrases(),
            labels: { syntaxError: _sdT('Syntax error') },
            // The lock markers of the HTML K.tree, beside the locked lines.
            lockGutter: true,
            lockTitle: _sdCodeLockTitle,
            onSelection: _sdCodeOnSelection,
            onChange: _sdCodeOnChange,
            onLockHit: _sdCodeOnLockHit,
            keys: [_sdCodeFindKey()]
        }).then(function (ed) {
            _sdCode.loading = false;
            _sdCode.ed = ed;
            _sdCode.tabs[0].ed = ed;
            _sdCode.editable = _sdCodeMayEdit();
            // Switched off while the bundle was on its way.
            if (!_sdCode.on) return;
            _sdCode.text = '';
            _sdCodeRefresh();
            _sdCodeTabsFlush();
        }, function (err) {
            _sdCode.loading = false;
            sdToast('<strong>' + esc(_sdT('The code editor could not be loaded.')) + '</strong><br>' + esc(err && err.message ? err.message : ''), 'error', 6000);
            _sdCodeToggle(false);
        });
    }

    // The document for the editor: its text, the range map (one entry per
    // node and per frame row, with the islands an edit may touch) and the
    // locks derived from it.
    function _sdCodeDocument() {
        var text = '';
        var entries = [];
        var locks = [];
        var lang = _sdDocLang();
        var frame = _sdPageFrameRows();
        var addRow = function (r, indent, role) {
            var line = '    '.repeat(indent) + _sdFrameRowText(r);
            var from = text.length;
            text += line + '\n';
            entries.push({ key: role + ':' + entries.length, node: null, from: from, to: from + line.length, lock: 'full', role: role });
        };
        var addLine = function (line) {
            var from = text.length;
            text += line + '\n';
            entries.push({ key: 'frame:' + entries.length, node: null, from: from, to: from + line.length, lock: 'full', role: 'frame' });
        };

        addLine('<!DOCTYPE html>');
        addLine('<html lang="' + esc(lang) + '">');
        addLine('<head>');
        frame.head.forEach(function (r) { addRow(r, 1, 'head'); });
        addLine('</head>');

        // The <body> tag: the root node's attributes may change, the tag may not.
        var bodyOpen = '<body';
        var bodyIslands = [];
        _sdPageBodyAttrs().forEach(function (a) {
            bodyOpen += ' ' + a[0] + '="';
            var vf = bodyOpen.length;
            bodyOpen += esc(a[1]);
            bodyIslands.push([vf, bodyOpen.length]);
            bodyOpen += '"';
        });
        bodyOpen += '>';
        var bodyFrom = text.length;
        text += bodyOpen + '\n';
        var rootLock = _sdCodeLockOf(K.tree);
        entries.push({ key: 'body', node: K.tree, from: bodyFrom, to: bodyFrom + bodyOpen.length, lock: 'half', role: 'body',
            islands: (rootLock.attrs ? bodyIslands.map(function (p) { return [bodyFrom + p[0], bodyFrom + p[1]]; }) : []) });

        // The body: the emitter with its range map, widgets written whole.
        var sink = { ranges: [], scope: 0, full: true };
        var bodyStart = text.length;
        if (K.tree && K.tree.children) {
            K.tree.children.forEach(function (c) { text += _sdEmitNode(c, 1, text.length, sink); });
        }
        // Where the page's own content is: what is typed between the body
        // tag and the scripts belongs to the root node. Grows at both ends
        // so a line typed right after <body> or right before the scripts
        // counts.
        var contentEnd = text.length;
        entries.push({ key: 'content', node: K.tree, from: bodyStart, to: contentEnd, lock: '', role: 'content', grow: true });

        var recorded = new Set();
        sink.ranges.forEach(function (r) { recorded.add(r.node); });
        sink.ranges.forEach(function (r) {
            if (r.to <= r.from) return;
            var type = r.node.type;
            // A loop area is its two marker lines; the rows between them
            // are nodes of their own. A shared reference and a widget's root
            // wrapper hold nothing of their own either: what is inside decides.
            if (type === 'loop_area' || type === 'recipient_loop_area') {
                var openEnd = text.indexOf('-->', r.from) + 3;
                var closeAt = text.lastIndexOf('<!--', r.to);
                if (openEnd > 2 && closeAt > r.from) {
                    entries.push({ key: r.sid + ':' + r.node._id + ':open', node: r.node, sid: r.sid, from: r.from, to: openEnd, lock: 'full', role: 'loop' });
                    entries.push({ key: r.sid + ':' + r.node._id + ':close', node: r.node, sid: r.sid, from: closeAt, to: text.indexOf('-->', closeAt) + 3, lock: 'full', role: 'loop' });
                }
                return;
            }
            if (type === 'root' || type === 'shared_ref') return;
            // The range is the element itself: from its "<" to its last
            // character, without the indentation and the line break the
            // emitter puts around it, so a tag typed just before it is a
            // new element and not a change to it.
            var from = r.from, to = r.to;
            var open = _sdCodeOpenTag(text, from, to);
            if (open) from = open.from;
            else { while (from < to && /\s/.test(text[from])) from++; }
            while (to > from && /\s/.test(text[to - 1])) to--;
            if (to <= from) return;
            var lock = _sdCodeLockOf(r.node);
            var e = { key: r.sid + ':' + r.node._id, node: r.node, sid: r.sid, from: from, to: to, lock: lock.level, role: 'node',
                      kind: _sdCodeTextKind(r.node),
                      // Children the emitter could not locate (a component
                      // that rewrote them) stay as they are: the text is
                      // read for the element itself only.
                      atomic: !!(r.node.children && r.node.children.length && r.node.children.some(function (c) { return !recorded.has(c); })) };
            if (lock.level === 'half') e.islands = _sdCodeIslands(text, { from: from, to: to, node: r.node }, lock);
            entries.push(e);
        });

        frame.foot.forEach(function (r) { addRow(r, 1, 'foot'); });
        addLine('</body>');
        text += '</html>';
        var endFrom = text.length - 7;
        entries.push({ key: 'frame:end', node: null, from: endFrom, to: text.length, lock: 'full', role: 'frame' });

        entries.forEach(function (e) {
            if (e.lock === 'full') locks.push({ from: e.from, to: e.to, tag: e.role });
            else if (e.lock === 'half') locks.push({ from: e.from, to: e.to, tag: e.role, allow: e.islands || [] });
        });
        return { text: text, entries: entries, locks: locks, bodyStart: bodyStart };
    }

    // The islands of a half-locked node: its attribute values (when values
    // may change) and its own text (when the text may). Read from the text
    // itself, so a component's output needs no map of its own.
    function _sdCodeIslands(text, r, lock) {
        var out = [];
        var open = _sdCodeOpenTag(text, r.from, r.to);
        if (!open) return out;
        if (lock.attrs) {
            open.attrs.forEach(function (a) { if (a.valueFrom !== null) out.push([a.valueFrom, a.valueTo]); });
        }
        // The text as the emitter printed it: a bound text is its {{token}}
        // even when the node has no text of its own.
        var raw = (lock.text && r.node.props) ? _sdCodeBoundProps(r.node).text : '';
        if (typeof raw === 'string' && raw !== '') {
            var needle = (r.node.type === 'semantic') ? esc(raw) : raw;
            var at = text.indexOf(needle, open.end);
            if (at !== -1 && at + needle.length <= r.to) out.push([at, at + needle.length]);
        }
        return out;
    }

    // Writes the document when it changed, then shows the selection. Only
    // the part that differs is written (the longest common start and end
    // of the old and the new text are left alone): a keystroke in a panel
    // touches one attribute, and the cursor, the scroll position, the folds
    // and the undo history of the editor stay as they were. The ranges and
    // the locks are replaced whole: their positions in the new text are
    // known from the emitter, and the set is cheap to rebuild.
    function _sdCodeRefresh() {
        if (!_sdCodeEnabled()) return;
        var ed = _sdCode.ed;
        _sdCodeSyncMode();
        var doc = _sdCodeDocument();
        var current = ed.getValue();
        if (doc.text !== current) {
            _sdCode.busy = true;
            try {
                var d = _sdCodeTextDiff(current, doc.text);
                ed.applyExternal(d.from, d.to, d.insert);
                // The text is the K.tree's again: nothing typed is waiting.
                _sdCode.dirty = null;
                if (_sdCode.applyTimer) { clearTimeout(_sdCode.applyTimer); _sdCode.applyTimer = 0; }
            } finally { _sdCode.busy = false; }
        }
        // The map and the locks are replaced on every pass, text change or
        // not: a node the K.tree gained has no range yet even when its text
        // is already there (typed, then applied).
        ed.track(doc.entries.map(function (e) {
            return { id: e.key, from: e.from, to: e.to, data: e, grow: !!e.grow };
        }));
        ed.lock(doc.locks);
        _sdCode.text = doc.text;
        _sdCode.entries = doc.entries;
        _sdCodeRevealSelection();
    }

    // Read-only follows the page lock: a session that lost the page keeps
    // the view but not the keyboard.
    function _sdCodeSyncMode() {
        var editable = _sdCodeMayEdit();
        if (_sdCode.editable === editable) return;
        _sdCode.editable = editable;
        if (_sdCode.ed) _sdCode.ed.setReadOnly(!editable);
        _sdCodeLabelSwitch(document.getElementById('sd-html-code-toggle'), editable);
    }

    // The one replacement that turns `before` into `after`: the common start
    // and end are kept, what is between them is replaced. Compared by UTF-16
    // units, which is what the editor's positions count.
    function _sdCodeTextDiff(before, after) {
        var a = before.length, b = after.length;
        var start = 0, max = Math.min(a, b);
        while (start < max && before.charCodeAt(start) === after.charCodeAt(start)) start++;
        var end = 0;
        while (end < max - start && before.charCodeAt(a - 1 - end) === after.charCodeAt(b - 1 - end)) end++;
        return { from: start, to: a - end, insert: after.slice(start, b - end) };
    }

    // The entry of a node in the map, by identity (ids repeat across widgets).
    function _sdCodeEntryOf(node) {
        if (!node) return null;
        var list = _sdCode.entries;
        for (var i = 0; i < list.length; i++) if (list[i].node === node) return list[i];
        return null;
    }

    // Marks the selected node's opening tag and brings it into view; a
    // selection made in the editor itself is not scrolled to.
    function _sdCodeRevealSelection() {
        if (!_sdCodeEnabled()) return;
        var ed = _sdCode.ed;
        var entry = _sdCodeEntryOf(K.selectedNodes.length > 1 ? null : K.selectedNode);
        if (!entry) { ed.mark('sel'); return; }
        var live = ed.tracked(entry.key) || entry;
        var open = _sdCodeOpenTag(ed.getValue(), live.from, live.to);
        var from = open ? open.from : live.from, to = open ? open.end : Math.min(live.to, live.from + 200);
        ed.mark('sel', from, to, 'pg-ce-mark');
        if (!_sdCode.fromEditor && !_sdCode.applying) ed.scrollTo(from, { y: 'center' });
    }

    // The cursor moved by hand: the innermost element under it is selected
    // on the canvas, as a click on a row of the K.tree does.
    function _sdCodeOnSelection(inst, update) {
        if (_sdCode.busy || !_sdCode.on) return;
        var byHand = false;
        for (var i = 0; i < update.transactions.length; i++) {
            if (update.transactions[i].isUserEvent('select')) { byHand = true; break; }
        }
        if (!byHand) return;
        var pos = inst.selection().head;
        var hits = inst.trackedAt(pos);
        var entry = null;
        for (var j = 0; j < hits.length; j++) {
            var e = hits[j].data;
            if (e && e.node) { entry = e; break; }
        }
        if (!entry || entry.node === K.selectedNode) return;
        _sdCode.fromEditor = true;
        try {
            K.selectedNode = entry.node;
            K.selectedNodes = [];
            _sdSelectRender();
            scrollCanvasToNode(K.selectedNode);
        } finally { _sdCode.fromEditor = false; }
    }

    // Opens (or shows) a file tab. The code view switches on for it; a
    // request made before the bundle arrived waits in _sdCode.queued.
    function _sdCodeTabOpen(spec) {
        if (!_sdCode.host) return;
        if (!_sdCode.on) _sdCodeToggle(true);
        if (!_sdCode.ed) { _sdCode.queued = (_sdCode.queued || []).concat([spec]); return; }
        var tab = _sdCodeTabFind(spec.key);
        if (tab) {
            if (spec.title) tab.title = spec.title;
            tab.spec = spec;
            _sdCodeTabActivate(spec.key);
            return;
        }
        var pane = document.createElement('div');
        pane.className = 'sd-code-pane pg-code-editor';
        pane.setAttribute('data-key', spec.key);
        document.getElementById('sd-code-panes').appendChild(pane);
        tab = { key: spec.key, title: spec.title || '', icon: spec.icon || 'bi-file-earmark-code', pane: pane, ed: null, dirty: false, spec: spec, loaded: false };
        _sdCode.tabs.push(tab);
        tab.ed = PgCodeEditor.create(pane, {
            language: spec.language || 'css',
            value: '',
            readOnly: true,
            phrases: _sdCodePhrases(),
            labels: { syntaxError: _sdT('Syntax error') },
            keys: [{ key: 'Mod-s', run: function () { _sdCodeTabSave(spec.key); return true; } }, _sdCodeFindKey()],
            onChange: function () {
                if (!tab.loaded || tab.dirty) return;
                tab.dirty = true;
                _sdCodeTabsRender();
            }
        });
        _sdCodeTabActivate(spec.key);
        spec.load(function (res) {
            if (!_sdCodeTabFind(spec.key)) return;   // closed meanwhile
            if (res && res.ok) {
                tab.ed.setValue(res.content || '');
                tab.loaded = true;
                tab.dirty = false;
                tab.ed.setReadOnly(!!spec.readOnly);
            } else {
                tab.ed.setValue('/* ' + ((res && res.message) || _sdT('The file could not be read.')) + ' */');
            }
            _sdCodeTabsRender();
            if (_sdCode.active === spec.key && !spec.readOnly) tab.ed.focus();
        });
    }

    function _sdCodeTabsFlush() {
        var q = _sdCode.queued || [];
        _sdCode.queued = [];
        q.forEach(_sdCodeTabOpen);
    }

    function _sdCodeTabActivate(key) {
        var tab = _sdCodeTabFind(key);
        if (!tab) return;
        _sdCode.active = key;
        _sdCode.tabs.forEach(function (t) { t.pane.classList.toggle('sd-code-pane-active', t === tab); });
        _sdCodeTabsRender();
        if (key === 'page') _sdCodeRefresh();
        if (tab.ed && (key !== 'page' || _sdCodeMayEdit())) tab.ed.focus();
    }

    function _sdCodeTabClose(key) {
        var tab = _sdCodeTabFind(key);
        if (!tab || key === 'page') return;
        if (tab.dirty && !window.confirm(_sdT('Close the file without saving the changes?'))) return;
        if (tab.ed) tab.ed.destroy();
        if (tab.pane.parentNode) tab.pane.parentNode.removeChild(tab.pane);
        _sdCode.tabs = _sdCode.tabs.filter(function (t) { return t !== tab; });
        if (_sdCode.active === key) _sdCodeTabActivate('page');
        else _sdCodeTabsRender();
    }

    function _sdCodeTabSave(key) {
        var tab = _sdCodeTabFind(key);
        if (!tab || !tab.spec || !tab.ed || !tab.loaded) return;
        if (tab.spec.readOnly) return;
        var btn = document.getElementById('sd-code-file-save');
        if (btn) btn.disabled = true;
        tab.spec.save(tab.ed.getValue(), function (res) {
            if (btn) btn.disabled = false;
            if (res && res.ok) {
                tab.dirty = false;
                _sdCodeTabsRender();
                sdToast(esc(_sdT('The file was saved')) + ' <span class="bi bi-check2"></span>', 'success', 2200);
            } else {
                sdToast('<strong>' + esc(_sdT('The file could not be saved:')) + '</strong><br>' + esc((res && res.message) || ''), 'error', 5000);
            }
        });
    }

    // A dynamic region's PHP code as a file tab: read and saved through
    // api.php's dynamic_region_code, which admits administrators only (the
    // right of edit_dynamic_region.php) and checks the form token; the row
    // is the same dregion row the classic screen edits.
    function _sdDregionCodeOpen(name) {
        if (!name) return;
        var post = function (sub, extra, cb) {
            var body = { action: 'dynamic_region_code', sub_action: sub, name: name, token: _pgToken() };
            if (extra) Object.keys(extra).forEach(function (k) { body[k] = extra[k]; });
            var url = (K.design && K.design.apiUrl) ? K.design.apiUrl : ((window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/api.php');
            fetch(url, { method: 'POST', body: JSON.stringify(body), credentials: 'same-origin',
                         headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.status === 'success') cb({ ok: true, content: data.content || '' });
                    else cb({ ok: false, message: (data && data.message) || _sdT('Network error') });
                })
                .catch(function () { cb({ ok: false, message: _sdT('Network error') }); });
        };
        _sdCodeTabOpen({
            key: 'dregion:' + name,
            title: name + '.php',
            icon: 'bi-filetype-php',
            language: 'php',
            readOnly: false,
            url: null,
            load: function (cb) { post('read', null, cb); },
            save: function (content, cb) { post('save', { content: content }, cb); }
        });
    }

    // A classic page's custom layout (the PHP its system region draws) as a
    // file tab: read, regenerated and saved through api.php's layout_code,
    // which admits the classic layout editor's role (designers and
    // administrators) and checks the form token. A text saved equal to the
    // generated layout, white space aside, leaves the page unmodified.
    // Regenerate puts the generated layout in the tab; nothing is saved
    // until the tab is. Read only on a hosted site, which ignores the file.
    function _sdLayoutCodeOpen(page, regenerate) {
        if (!page || !(page.page_id > 0)) return;
        var key = 'layout:' + page.page_id;
        var readOnly = !!(K.design && K.design.layoutReadOnly);
        if (regenerate && readOnly) return;
        var post = function (sub, extra, cb) {
            var body = { action: 'layout_code', sub_action: sub, page_id: page.page_id, token: _pgToken() };
            if (extra) Object.keys(extra).forEach(function (k) { body[k] = extra[k]; });
            var url = (K.design && K.design.apiUrl) ? K.design.apiUrl : ((window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/api.php');
            fetch(url, { method: 'POST', body: JSON.stringify(body), credentials: 'same-origin',
                         headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.status === 'success') cb({ ok: true, content: data.content || '' });
                    else cb({ ok: false, message: (data && data.message) || _sdT('Network error') });
                })
                .catch(function () { cb({ ok: false, message: _sdT('Network error') }); });
        };
        if (regenerate && !window.confirm(_sdT('Replace the layout in the editor with a newly generated one? Nothing is saved until you save the file.'))) return;
        var open = _sdCodeTabFind(key);
        var spec = {
            key: key,
            title: _sdT('Layout (PHP)') + (page.page_name ? ': ' + page.page_name : ''),
            icon: 'bi-filetype-php',
            language: 'php',
            readOnly: readOnly,
            url: null,
            load: function (cb) {
                if (!regenerate) { post('read', null, cb); return; }
                // A new tab starts from the generated text, unsaved.
                post('regenerate', null, function (res) {
                    cb(res);
                    var tab = _sdCodeTabFind(key);
                    if (res.ok && tab) { tab.dirty = true; _sdCodeTabsRender(); }
                });
            },
            save: function (content, cb) {
                post('save', { content: content }, function (res) {
                    if (res.ok) { _sdLegacyRegionForget(page.page_id); if (typeof renderCanvas === 'function') renderCanvas(); }
                    cb(res);
                });
            }
        };
        _sdCodeTabOpen(spec);
        if (!regenerate || !open) return;
        if (!open.loaded) return;
        post('regenerate', null, function (res) {
            var tab = _sdCodeTabFind(key);
            if (!tab || !tab.ed) return;
            if (res.ok) tab.ed.setValue(res.content);
            else sdToast(esc(res.message || ''), 'error', 5000);
        });
    }

    // Ctrl+S with the keyboard in a file tab saves that file, not the design.
    function _sdCodeSaveShortcut() {
        var tab = _sdCodeActiveTab();
        if (!tab || tab.key === 'page' || !tab.ed || !tab.ed.hasFocus()) return false;
        _sdCodeTabSave(tab.key);
        return true;
    }

    var _SD_CODE_FIND_LIMIT = 500;   // hits per tab

    function _sdCodeFindKey() {
        return { key: 'Mod-Shift-f', run: function () { _sdCodeFindShow(true); return true; } };
    }

    function _sdCodeFindShow(on) {
        var bar = document.getElementById('sd-code-find');
        if (!bar) return;
        _sdCodeFind.open = !!on;
        bar.classList.toggle('d-none', !on);
        _sdCodeTabsRender();
        if (!on) { bar.innerHTML = ''; _sdCodeFind.hits = []; return; }
        // A one-line selection in the active tab is what is looked for.
        var tab = _sdCodeActiveTab();
        if (tab && tab.ed) {
            var sel = tab.ed.selection();
            var picked = sel.to > sel.from ? tab.ed.sliceDoc(sel.from, sel.to) : '';
            if (picked && picked.indexOf('\n') === -1) _sdCodeFind.query.search = picked;
        }
        var q = _sdCodeFind.query;
        var opt = function (name, label, icon) {
            return '<button type="button" class="sd-code-find-opt' + (q[name] ? ' active' : '') + '" data-opt="' + name + '" title="' + esc(label) + '" aria-pressed="' + (q[name] ? 'true' : 'false') + '">' + icon + '</button>';
        };
        bar.innerHTML =
            '<div class="sd-code-find-row">' +
                '<span class="bi bi-search"></span>' +
                '<input type="search" class="sd-code-find-input" id="sd-code-find-input" placeholder="' + esc(_sdT('Search in all tabs')) + '" value="' + esc(q.search) + '" spellcheck="false">' +
                opt('caseSensitive', _sdT('match case'), 'Aa') + opt('wholeWord', _sdT('by word'), 'ab') + opt('regexp', _sdT('regexp'), '.*') +
                '<span class="sd-code-find-count" id="sd-code-find-count"></span>' +
                '<button type="button" class="sd-code-find-opt" data-close="1" title="' + esc(_sdT('Close')) + '">&times;</button>' +
            '</div>' +
            '<div class="sd-code-find-results" id="sd-code-find-results"></div>';
        var input = bar.querySelector('#sd-code-find-input');
        input.addEventListener('input', function () {
            q.search = this.value;
            clearTimeout(_sdCodeFind.timer);
            _sdCodeFind.timer = setTimeout(_sdCodeFindRun, 200);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); _sdCodeFindShow(false); return; }
            if (e.key !== 'Enter') return;
            e.preventDefault();
            clearTimeout(_sdCodeFind.timer);
            if (q.search !== '' && !_sdCodeFind.hits.length) _sdCodeFindRun();
            var n = _sdCodeFind.hits.length;
            if (n) _sdCodeFindPick(((_sdCodeFind.at + (e.shiftKey ? -1 : 1)) % n + n) % n, true);
        });
        bar.querySelectorAll('.sd-code-find-opt').forEach(function (b) {
            b.addEventListener('click', function () {
                if (this.getAttribute('data-close')) { _sdCodeFindShow(false); return; }
                var name = this.getAttribute('data-opt');
                q[name] = !q[name];
                this.classList.toggle('active', q[name]);
                this.setAttribute('aria-pressed', q[name] ? 'true' : 'false');
                _sdCodeFindRun();
            });
        });
        bar.querySelector('#sd-code-find-results').addEventListener('click', function (e) {
            var hit = e.target.closest('[data-hit]');
            if (hit) _sdCodeFindPick(parseInt(hit.getAttribute('data-hit'), 10), false);
        });
        input.focus();
        input.select();
        _sdCodeFindRun();
    }

    function _sdCodeFindRun() {
        var list = document.getElementById('sd-code-find-results');
        var count = document.getElementById('sd-code-find-count');
        if (!list || !count) return;
        var q = _sdCodeFind.query;
        var hits = [], html = '', error = '', truncated = false, tabs = 0;
        if (q.search !== '' && typeof PgCodeEditor !== 'undefined') {
            _sdCode.tabs.forEach(function (t) {
                if (!t.ed || (t.key !== 'page' && !t.loaded) || error) return;
                var r = PgCodeEditor.findAll(t.ed.getValue(), q, _SD_CODE_FIND_LIMIT);
                if (r.error) { error = r.error; return; }
                if (!r.matches.length) return;
                truncated = truncated || r.truncated;
                tabs++;
                var title = t.key === 'page' ? _sdT('Page') : t.title;
                html += '<div class="sd-code-find-tab"><span class="bi ' + esc(t.icon) + '"></span>' + esc(title) + '<span class="sd-code-find-n">' + r.matches.length + '</span></div>';
                r.matches.forEach(function (m) {
                    html += '<div class="sd-code-find-hit" data-hit="' + hits.length + '"><span class="sd-code-find-ln">' + m.line + '</span>' + _sdCodeFindPreview(m) + '</div>';
                    hits.push({ key: t.key, from: m.from, to: m.to, text: m.text });
                });
            });
        }
        _sdCodeFind.hits = error ? [] : hits;
        _sdCodeFind.at = -1;
        if (error) {
            count.textContent = _sdT('Invalid regular expression');
            list.innerHTML = '';
        } else {
            count.textContent = q.search === '' ? '' : (hits.length ? _sdT('{var:1} results in {var:2} tabs', [hits.length + (truncated ? '+' : ''), tabs]) : _sdT('No results'));
            list.innerHTML = html;
        }
        count.classList.toggle('fg-danger', !!error);
    }

    // The hit's line, cut to the words around the match, the match marked.
    function _sdCodeFindPreview(m) {
        var line = m.lineText, end = Math.min(line.length, m.col + m.text.length);
        var start = Math.max(0, m.col - 40);
        var before = line.slice(start, m.col).replace(/^\s+/, '');
        var after = line.slice(end, end + 80);
        return '<span class="sd-code-find-text">' + (start > 0 ? '&hellip;' : '') + esc(before) +
            '<mark>' + esc(line.slice(m.col, end)) + '</mark>' + esc(after) + (end + 80 < line.length ? '&hellip;' : '') + '</span>';
    }

    // Opens the hit's tab and selects it; a text that changed since the
    // search (typed, a canvas edit, a closed tab) is searched again first.
    function _sdCodeFindPick(i, keepFocus) {
        var hit = _sdCodeFind.hits[i];
        if (!hit) return;
        var tab = _sdCodeTabFind(hit.key);
        if (!tab || !tab.ed || tab.ed.sliceDoc(hit.from, hit.to) !== hit.text) { _sdCodeFindRun(); return; }
        _sdCodeFind.at = i;
        if (_sdCode.active !== hit.key) _sdCodeTabActivate(hit.key);
        tab.ed.goTo(hit.from, hit.to, { focus: !keepFocus });
        var list = document.getElementById('sd-code-find-results');
        if (!list) return;
        list.querySelectorAll('.sd-code-find-hit.active').forEach(function (el) { el.classList.remove('active'); });
        var row = list.querySelector('[data-hit="' + i + '"]');
        if (row) { row.classList.add('active'); row.scrollIntoView({ block: 'nearest' }); }
        if (keepFocus) { var input = document.getElementById('sd-code-find-input'); if (input) input.focus(); }
    }

    return {
        toggle: _sdCodeToggle,
        refresh: _sdCodeRefresh,
        saveShortcut: _sdCodeSaveShortcut,
        tabOpen: _sdCodeTabOpen,
        tabActivate: _sdCodeTabActivate,
        tabClose: _sdCodeTabClose,
        tabSave: _sdCodeTabSave,
        findShow: _sdCodeFindShow,
        layoutOpen: _sdLayoutCodeOpen,
        dregionOpen: _sdDregionCodeOpen
    };
});
