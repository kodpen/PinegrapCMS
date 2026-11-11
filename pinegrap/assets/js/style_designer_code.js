/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the code view part: switching the HTML column's code
 * view on, the page's document with its range map and locks, the selection
 * both ways between the editor and the canvas, what is typed read back into
 * the tree (with the {{token}} bindings), the file tabs (stylesheets,
 * scripts, layouts, dynamic regions) and the search in all tabs. The editor
 * runs this file the first time the view is switched on or a file tab opens
 * (_sdPartLoad(), pg_designer_parts()); the switch, the tab strip, the
 * completion sources and the bound props of the output stay in the core,
 * which hands this file what it uses through the kit (_sdPartKit()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('code', function (K) {
    'use strict';

    var _SD_CODE_KEY = K._SD_CODE_KEY;
    var _backfillIds = K._backfillIds;
    var _cfAdoptOnDrop = K._cfAdoptOnDrop;
    var _collab = K._collab;
    var _getBindableProps = K._getBindableProps;
    var _isInsideSystemWidget = K._isInsideSystemWidget;
    var _nodeInTree = K._nodeInTree;
    var _parseInlineStyle = K._parseInlineStyle;
    var _pgApiPost = K._pgApiPost;
    var _pgToken = K._pgToken;
    var _resolveSharedSid = K._resolveSharedSid;
    var _sdAnyParent = K._sdAnyParent;
    var _sdApplyInlineStyle = K._sdApplyInlineStyle;
    var _sdAttrBindNameOk = K._sdAttrBindNameOk;
    var _sdAttrTokenOk = K._sdAttrTokenOk;
    var _sdBindTextSlot = K._sdBindTextSlot;
    var _sdCanEditNode = K._sdCanEditNode;
    var _sdCode = K._sdCode;
    var _sdCodeActiveTab = K._sdCodeActiveTab;
    var _sdCodeAttrsWith = K._sdCodeAttrsWith;
    var _sdCodeBindToken = K._sdCodeBindToken;
    var _sdCodeBoundProps = K._sdCodeBoundProps;
    var _sdCodeEnabled = K._sdCodeEnabled;
    var _sdCodeFind = K._sdCodeFind;
    var _sdCodeLabelSwitch = K._sdCodeLabelSwitch;
    var _sdCodeMayEdit = K._sdCodeMayEdit;
    var _sdCodePhrases = K._sdCodePhrases;
    var _sdCodeTabFind = K._sdCodeTabFind;
    var _sdCodeTabsRender = K._sdCodeTabsRender;
    var _sdContentSizingNode = K._sdContentSizingNode;
    var _sdDecodeEntities = K._sdDecodeEntities;
    var _sdDocLang = K._sdDocLang;
    var _sdEmitNode = K._sdEmitNode;
    var _sdFrameRowText = K._sdFrameRowText;
    var _sdFxAttrs = K._sdFxAttrs;
    var _sdFxNormalize = K._sdFxNormalize;
    var _sdImgInRatio = K._sdImgInRatio;
    var _sdIsSentence = K._sdIsSentence;
    var _sdLegacyRegionForget = K._sdLegacyRegionForget;
    var _sdNodeSetBinding = K._sdNodeSetBinding;
    var _sdPageBodyAttrs = K._sdPageBodyAttrs;
    var _sdPageFrameRows = K._sdPageFrameRows;
    var _sdRetargetLabels = K._sdRetargetLabels;
    var _sdSelectRender = K._sdSelectRender;
    var _sdT = K._sdT;
    var _sdUserAttrValue = K._sdUserAttrValue;
    var _serializeInlineDecls = K._serializeInlineDecls;
    var _sharedCache = K._sharedCache;
    var _sharedDirty = K._sharedDirty;
    var _swBindTokenOptions = K._swBindTokenOptions;
    var esc = K.esc;
    var getNodeExtraStyle = K.getNodeExtraStyle;
    var gid = K.gid;
    var hasLockedAncestor = K.hasLockedAncestor;
    var render = K.render;
    var renderCanvas = K.renderCanvas;
    var renderHtmlTree = K.renderHtmlTree;
    var saveState = K.saveState;
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

    var _SD_CODE_APPLY_MS = 400;

    // What a hand may change on a node, in the code view's terms:
    // { level: 'full' | 'half' | '', attrs: bool, text: bool }. The same
    // questions the canvas asks (_sdCanEditNode(), the layer lock, the
    // shared component), answered once per node while the document is built.
    function _sdCodeLockOf(node) {
        if (!node) return { level: 'full', attrs: false, text: false };
        if (node.type === 'region') return { level: 'full', attrs: false, text: false };
        if (!_collab.mayEdit) return { level: 'full', attrs: false, text: false };
        var structural = _sdCanEditNode(node);
        if (node.type === 'root') return { level: 'half', attrs: structural, text: false };
        // A text island only where _sdCodeApplyText() writes it back: a
        // component prints its own label (a btn, a badge), so a keystroke
        // there would be taken and then dropped by the next redraw.
        var textOk = structural || (_sdCanEditNode(node, 'text') && _sdCodeTextKind(node) !== 'none');
        if (!structural) {
            // A content role outside its area: the text, nothing else.
            return textOk ? { level: 'half', attrs: false, text: true } : { level: 'full', attrs: false, text: false };
        }
        // A sentence with values: its attribute values; its words and the
        // places of its values are the panel's (_sdSentenceLayout()).
        if (_sdIsSentence(node)) return { level: 'half', attrs: true, text: false };
        // A locked layer, a shared component or a widget (owner's rule:
        // half-locked): values and text, never the structure.
        if ((node.props && node.props._locked) || hasLockedAncestor(node) || _resolveSharedSid(node) > 0) {
            return { level: 'half', attrs: true, text: true };
        }
        return { level: '', attrs: true, text: true };
    }

    // The opening tag at the start of a node's text: where the tag name is,
    // each attribute's name and value, and where the tag ends. null when the
    // text does not begin with a tag (a region placeholder, raw markup).
    function _sdCodeOpenTag(text, from, to) {
        var i = from;
        while (i < to && (text[i] === ' ' || text[i] === '\t' || text[i] === '\n')) i++;
        if (text[i] !== '<' || text[i + 1] === '!' || text[i + 1] === '/') return null;
        var m = /^<([A-Za-z][\w:-]*)/.exec(text.slice(i, Math.min(to, i + 200)));
        if (!m) return null;
        var out = { from: i, tagFrom: i + 1, tagTo: i + 1 + m[1].length, attrs: [], end: 0 };
        var p = out.tagTo;
        var attrRe = /\s+([^\s=\/>"']+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?|\s*\/?>/y;
        while (p < to) {
            attrRe.lastIndex = p;
            var a = attrRe.exec(text);
            if (!a) return null;
            if (a[1] === undefined) { out.end = attrRe.lastIndex; return out; }
            var entry = { name: a[1], nameFrom: a.index + (a[0].length - a[0].replace(/^\s+/, '').length), nameTo: 0, valueFrom: null, valueTo: null };
            entry.nameTo = entry.nameFrom + a[1].length;
            if (a[2] !== undefined || a[3] !== undefined || a[4] !== undefined) {
                var val = a[2] !== undefined ? a[2] : (a[3] !== undefined ? a[3] : a[4]);
                var quoted = a[4] === undefined;
                entry.valueTo = attrRe.lastIndex - (quoted ? 1 : 0);
                entry.valueFrom = entry.valueTo - val.length;
            }
            out.attrs.push(entry);
            p = attrRe.lastIndex;
        }
        return null;
    }

    // ========================= CODE VIEW: TYPED CHANGES INTO THE TREE =========================
    // What is typed is read back from the editor's syntax tree after a pause
    // and put into the nodes it belongs to. The range map says which element
    // of the text is which node: an element whose "<" sits where a node's
    // range starts is that node; one without a range is new. An existing
    // node changes in place (tag, attributes, text), a free node's children
    // are rebuilt from the elements inside it (so a deletion, a move or a
    // paste in the text is one in the tree), a new element with no node
    // inside it goes through the server's converter (the same one a paste
    // uses) and stands as a Custom HTML node until the answer comes. Locks
    // never reach here: the editor refused the keystroke. After the tree is
    // written the normal render follows, and the refresh writes back the
    // tree's own spelling of what was typed (quotes, escaping, indentation).

    // The editor changed under the operator's hands: remember the span and
    // apply after a pause. The facade's writes (busy) are not typing.
    function _sdCodeOnChange(inst, update) {
        if (_sdCode.busy || !_sdCode.on || !_sdCodeMayEdit()) return;
        var d = _sdCode.dirty;
        if (d) {
            d.from = update.changes.mapPos(d.from, -1);
            d.to = update.changes.mapPos(d.to, 1);
        }
        update.changes.iterChangedRanges(function (fromA, toA, fromB, toB) {
            if (!d) d = { from: fromB, to: toB };
            else { d.from = Math.min(d.from, fromB); d.to = Math.max(d.to, toB); }
        });
        _sdCode.dirty = d;
        if (_sdCode.applyTimer) clearTimeout(_sdCode.applyTimer);
        _sdCode.applyTimer = setTimeout(_sdCodeApply, _SD_CODE_APPLY_MS);
    }

    // Whatever is waiting goes in now (a save, a tab switch).
    function _sdCodeFlush() {
        if (!_sdCode.applyTimer) return;
        clearTimeout(_sdCode.applyTimer);
        _sdCode.applyTimer = 0;
        _sdCodeApply();
    }

    function _sdCodeOnLockHit(hit) {
        var now = Date.now();
        if (now - _sdCode.lockToastAt < 2500) return;
        _sdCode.lockToastAt = now;
        sdToast('<span class="bi bi-lock-fill me-1"></span>' + esc(_sdCodeLockTitle(hit.tag, true)), 'warning', 3500);
    }

    // Why a line is locked, by the lock's tag: the tooltip of the lock
    // marker in the gutter and the toast of a refused keystroke. `partly`
    // says the line (or the hit) has parts that may change.
    function _sdCodeLockTitle(tag, partly) {
        switch (tag) {
            case 'head': case 'foot': case 'frame':
                return _sdT('These lines come from the page settings and the design files; change them there.');
            case 'body':
                return _sdT('The body tag is fixed; its class, id and attribute values can change.');
            case 'loop':
                return _sdT('The loop markers of a system widget cannot change.');
        }
        return partly ? _sdT('This element is locked here: only its attribute values and its text can change.') : _sdT('Locked');
    }

    // The innermost thing whose text holds the whole typed span: a node, the
    // body tag (its attributes) or the page content between the tags.
    function _sdCodeScopeOf(dirty) {
        var ed = _sdCode.ed;
        var hits = ed.trackedAt(dirty.from);
        for (var i = 0; i < hits.length; i++) {
            var h = hits[i];
            if (!(h.from <= dirty.from && dirty.to <= h.to)) continue;
            var role = h.data && h.data.role;
            if (role === 'node' || role === 'body' || role === 'content') return { entry: h.data, from: h.from, to: h.to };
        }
        return null;
    }

    // The node an element of the text stands for: the one whose range starts
    // at the element's "<". Nothing there: the element is new.
    function _sdCodeOwnerOf(item) {
        var hits = _sdCode.ed.trackedAt(item.from);
        for (var i = 0; i < hits.length; i++) {
            if (hits[i].from === item.from && hits[i].data && hits[i].data.role === 'node') return hits[i].data;
        }
        return null;
    }

    // Reads the elements between `from` and `to` among the children of a
    // syntax node: elements with their tags, attributes and children, text
    // runs with their raw markup. null when the parser left an error there
    // (an unclosed tag, a stray close tag): nothing is applied until the
    // text parses, and the lint shows where it does not.
    function _sdCodeParse(syntax, from, to, text) {
        var items = [];
        for (var child = syntax.firstChild; child; child = child.nextSibling) {
            if (child.to <= from || child.from >= to) continue;
            var name = child.name;
            if (child.type.isError || name === 'MismatchedCloseTag' || name === 'IncompleteCloseTag') return null;
            if (name === 'Element') {
                var el = _sdCodeParseElement(child, text);
                if (!el) return null;
                items.push(el);
            } else if (name === 'Text' || name === 'EntityReference' || name === 'CharacterReference' || name === 'InvalidEntity') {
                var raw = text.slice(child.from, child.to);
                var last = items.length ? items[items.length - 1] : null;
                if (last && last.kind === 'text' && last.to === child.from) { last.to = child.to; last.raw += raw; }
                else items.push({ kind: 'text', from: child.from, to: child.to, raw: raw });
            }
            // Comments, doctype and processing instructions are not nodes.
        }
        return items;
    }

    function _sdCodeParseElement(node, text) {
        var open = node.getChild('OpenTag') || node.getChild('SelfClosingTag');
        if (!open) return null;
        var tagNode = open.getChild('TagName');
        if (!tagNode) return null;
        var item = {
            kind: 'element', from: node.from, to: node.to,
            tag: text.slice(tagNode.from, tagNode.to).toLowerCase(),
            open: { from: open.from, to: open.to },
            selfClosing: open.name === 'SelfClosingTag',
            close: null, attrs: [], children: [], texts: []
        };
        var attrs = open.getChildren('Attribute');
        for (var i = 0; i < attrs.length; i++) {
            var an = attrs[i].getChild('AttributeName');
            if (!an) continue;
            var av = attrs[i].getChild('AttributeValue');
            var au = av ? null : attrs[i].getChild('UnquotedAttributeValue');
            var value = null;
            if (av) {
                var q = text.slice(av.from, av.to);
                value = (q.length >= 2 && (q[0] === '"' || q[0] === "'")) ? q.slice(1, -1) : q;
            } else if (au) {
                value = text.slice(au.from, au.to);
            }
            item.attrs.push({ name: text.slice(an.from, an.to).toLowerCase(), value: value === null ? null : _sdCodeDecode(value) });
        }
        var close = node.getChild('CloseTag');
        if (close) item.close = { from: close.from, to: close.to };
        var innerFrom = open.to, innerTo = close ? close.from : node.to;
        item.innerFrom = innerFrom; item.innerTo = innerTo;
        // Raw-text elements: the text is what it is.
        var raw = node.getChild('ScriptText') || node.getChild('StyleText') || node.getChild('TextareaText');
        if (raw) { item.texts.push({ from: raw.from, to: raw.to, raw: text.slice(raw.from, raw.to) }); return item; }
        if (innerTo <= innerFrom) return item;
        var inner = _sdCodeParse(node, innerFrom, innerTo, text);
        if (inner === null) return null;
        for (var k = 0; k < inner.length; k++) {
            if (inner[k].kind === 'element') item.children.push(inner[k]);
            else item.texts.push(inner[k]);
        }
        item.items = inner;
        return item;
    }

    function _sdCodeDecode(s) {
        if (s.indexOf('&') === -1) return s;
        var t = document.createElement('textarea');
        t.innerHTML = s;
        return t.value;
    }

    // How a node holds its text: 'plain' (a semantic element's text node),
    // 'inner' (a content element's innerHTML), 'outer' (Custom HTML: the
    // markup itself), 'none' (nothing of its own to read).
    function _sdCodeTextKind(node) {
        if (!node) return 'none';
        // A sentence's words are written in the panel: its values sit
        // between them as elements of their own.
        if (_sdIsSentence(node)) return 'none';
        if (node.type === 'semantic') return 'plain';
        if (node.type === 'content') {
            var ct = node.props.contentType;
            if (ct === 'custom_html') return 'outer';
            if (['heading', 'paragraph', 'span', 'text', 'link', 'button'].indexOf(ct) !== -1) return 'inner';
        }
        return 'none';
    }

    // Puts the typed span into the tree. Called after the pause, and
    // before a save or a tab switch.
    function _sdCodeApply() {
        _sdCode.applyTimer = 0;
        if (!_sdCodeEnabled() || !_sdCode.dirty || !_sdCodeMayEdit()) return;
        var ed = _sdCode.ed;
        var dirty = _sdCode.dirty;
        var text = ed.getValue();
        var syntax;
        try { syntax = ed.syntaxTree(); } catch (e) { syntax = null; }
        if (!syntax) return;
        var scope = _sdCodeScopeOf(dirty);
        if (!scope) { _sdCode.dirty = null; return; }

        var changed = false;
        var report = { derived: false, tag: false, imports: [] };
        _sdCode.applying = true;
        try {
            if (scope.entry.role === 'body') {
                // The body tag's attributes belong to the root node.
                var bodyEl = _sdCodeElementAt(syntax, scope.from);
                if (!bodyEl) return;
                var body = _sdCodeParseElementShallow(bodyEl, text);
                if (!body) return;
                saveState('run:code');
                _sdCodeApplyAttrs(K.tree, body, scope.entry, { level: 'half', attrs: true, text: false }, report);
                changed = true;
            } else if (scope.entry.role === 'content') {
                // The page's own content, between the body tag and the scripts.
                var bodyNode = _sdCodeElementAt(syntax, _sdCodeBodyOpenAt(text));
                if (!bodyNode) return;
                var items = _sdCodeParse(bodyNode, scope.from, scope.to, text);
                if (items === null) return;   // not well-formed yet
                saveState('run:code');
                _sdCodeReconcileChildren(K.tree, items, 0, report);
                changed = true;
            } else {
                var entry = scope.entry;
                var el = _sdCodeElementAt(syntax, scope.from);
                // The element must still cover the typed span; when the
                // typing split it, the parent reads it as a whole.
                if (!el || el.to < dirty.to) { _sdCodeApplyOuter(dirty); return; }
                var item = _sdCodeParseElement(el, text);
                if (!item) return;
                saveState('run:code');
                _sdCodeReconcileNode(entry.node, item, entry, report);
                changed = true;
            }
        } finally {
            _sdCode.applying = false;
        }
        if (!changed) return;
        _sdCode.dirty = null;
        if (K.selectedNode && !_sdCodeNodeExists(K.selectedNode)) { K.selectedNode = null; K.selectedNodes = []; }
        _sdCode.applying = true;
        try { render(); } finally { _sdCode.applying = false; }
        if (report.derived) sdToast(esc(_sdT('A class set by the element\'s type cannot be removed in the code; use the options panel.')), 'warning', 4000);
        if (report.tag) sdToast(esc(_sdT('The tag of this element is set by its type; use the options panel.')), 'warning', 4000);
        report.imports.forEach(_sdCodeImport);
    }

    // The typed span is no longer inside one element (a close tag was typed
    // or removed): widen to the enclosing one and try again from there.
    function _sdCodeApplyOuter(dirty) {
        var hits = _sdCode.ed.trackedAt(dirty.from);
        var entry = null;
        for (var i = 0; i < hits.length; i++) {
            if (hits[i].from <= dirty.from && dirty.to <= hits[i].to && hits[i].data && hits[i].data.role === 'node') { entry = hits[i]; break; }
        }
        // Grow the span to the whole parent of the element that broke.
        var parentHit = null;
        for (var j = 0; j < hits.length; j++) {
            if (entry && hits[j].data === entry.data) continue;
            if (hits[j].from <= (entry ? entry.from : dirty.from) && (entry ? entry.to : dirty.to) <= hits[j].to && hits[j].data && (hits[j].data.role === 'node' || hits[j].data.role === 'content')) { parentHit = hits[j]; break; }
        }
        if (!parentHit) return;
        _sdCode.dirty = { from: parentHit.from, to: parentHit.to };
        _sdCodeApply();
    }

    function _sdCodeBodyOpenAt(text) {
        var e = null;
        for (var i = 0; i < _sdCode.entries.length; i++) if (_sdCode.entries[i].role === 'body') { e = _sdCode.entries[i]; break; }
        var live = e ? _sdCode.ed.tracked(e.key) : null;
        return live ? live.from : text.indexOf('<body');
    }

    // The Element syntax node whose opening "<" is at `pos`.
    function _sdCodeElementAt(syntax, pos) {
        var n = syntax.resolveInner(pos + 1, 1);
        while (n) {
            if (n.name === 'Element' && n.from === pos) return n;
            n = n.parent;
        }
        return null;
    }

    function _sdCodeParseElementShallow(node, text) {
        var open = node.getChild('OpenTag');
        if (!open) return null;
        var item = { kind: 'element', from: node.from, to: node.to, tag: 'body', open: { from: open.from, to: open.to }, attrs: [], children: [], texts: [] };
        var attrs = open.getChildren('Attribute');
        for (var i = 0; i < attrs.length; i++) {
            var an = attrs[i].getChild('AttributeName');
            if (!an) continue;
            var av = attrs[i].getChild('AttributeValue');
            var au = av ? null : attrs[i].getChild('UnquotedAttributeValue');
            var value = null;
            if (av) { var q = text.slice(av.from, av.to); value = (q.length >= 2 && (q[0] === '"' || q[0] === "'")) ? q.slice(1, -1) : q; }
            else if (au) value = text.slice(au.from, au.to);
            item.attrs.push({ name: text.slice(an.from, an.to).toLowerCase(), value: value === null ? null : _sdCodeDecode(value) });
        }
        return item;
    }

    function _sdCodeNodeExists(node) {
        if (_nodeInTree(node, K.tree)) return true;
        for (var k in _sharedCache) {
            if (Object.prototype.hasOwnProperty.call(_sharedCache, k) && _sharedCache[k] && _sharedCache[k].tree && _nodeInTree(node, _sharedCache[k].tree)) return true;
        }
        return false;
    }

    // One element of the text against its node.
    function _sdCodeReconcileNode(node, item, entry, report) {
        var lock = _sdCodeLockOf(node);
        if (lock.level === 'full' || node.type === 'region') return;
        var sid = entry.sid || 0;
        if (lock.attrs) _sdCodeApplyTag(node, item, entry, report);
        if (lock.attrs) _sdCodeApplyAttrs(node, item, entry, lock, report);
        if (lock.text) _sdCodeApplyText(node, item, entry, report);
        // The structure follows the text only for a free node whose children
        // the emitter could place; a locked one keeps its children.
        if (lock.level === '' && !entry.atomic && entry.kind !== 'inner' && entry.kind !== 'outer' && node.type !== 'shared_ref') {
            _sdCodeReconcileChildren(node, item.items || [], sid, report);
        }
        if (sid) _sharedDirty[sid] = true;
    }

    // A parent's children, rebuilt from the elements inside it in the text.
    function _sdCodeReconcileChildren(parent, items, sid, report) {
        var kids = [];
        var isPlain = parent.type === 'semantic';
        var textRuns = items.filter(function (it) { return it.kind === 'text' && it.raw.trim() !== ''; });
        var elements = items.filter(function (it) { return it.kind === 'element'; });
        // Text beside elements in a plain element becomes spans, in order,
        // as the converter does: a text node has one place, before or
        // after all the children.
        var mixed = isPlain && textRuns.length && elements.length && !_sdCodeTextSlot(parent, items);
        items.forEach(function (it) {
            if (it.kind === 'text') {
                if (it.raw.trim() === '') return;
                if (parent.type === 'root' || mixed) kids.push(_sdCodeNewSpan(it.raw));
                return;
            }
            var owner = _sdCodeOwnerOf(it) || _sdCodeOwnerInside(it);
            if (owner && (owner.sid || 0) !== sid) {
                // The element belongs to a shared component or widget this
                // parent places: the reference stays, its tree is its own.
                var ref = _sdCodeSharedRefFor(parent, owner.sid);
                if (ref && kids.indexOf(ref) === -1) kids.push(ref);
                return;
            }
            if (owner && owner.node && owner.from === it.from) {
                _sdCodeReconcileNode(owner.node, it, owner, report);
                kids.push(owner.node);
            } else if (_sdCodeHasOwnedInside(it)) {
                var wrap = _sdCodeNewSemantic(it);
                kids.push(wrap);
                _sdCodeReconcileChildren(wrap, it.items || [], sid, report);
            } else {
                var html = _sdCode.ed.sliceDoc(it.from, it.to);
                var holder = _sdCodeNewHolder(html);
                kids.push(holder);
                report.imports.push(holder);
            }
        });
        parent.children = kids;
        if (mixed) parent.props.text = '';
    }

    function _sdCodeHasOwnedInside(item) {
        for (var i = 0; i < item.children.length; i++) {
            if (_sdCodeOwnerOf(item.children[i]) || _sdCodeHasOwnedInside(item.children[i])) return true;
        }
        return false;
    }

    // The first owned element inside an element without an owner of its own
    // (a widget's root wrapper prints no tag: its children are the first
    // owned elements seen).
    function _sdCodeOwnerInside(item) {
        for (var i = 0; i < item.children.length; i++) {
            var o = _sdCodeOwnerOf(item.children[i]);
            if (o) return o;
        }
        return null;
    }

    // The shared_ref among a parent's children that places component `sid`.
    function _sdCodeSharedRefFor(parent, sid) {
        var kids = parent.children || [];
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].type === 'shared_ref' && parseInt(kids[i].props && kids[i].props.sharedId, 10) === sid) return kids[i];
        }
        return null;
    }

    // A new element around existing nodes: a plain element with the typed
    // tag and attributes, so the nodes inside keep what they are.
    /* @pg-site-output */
    function _sdCodeNewSemantic(item) {
        var props = { tag: item.tag, cssClass: '' };
        var attrs = [];
        item.attrs.forEach(function (a) {
            var v = a.value === null ? '' : a.value;
            if (a.name === 'class') props.cssClass = v;
            else if (a.name === 'id') props.id = v;
            else if (item.tag === 'a' && (a.name === 'href' || a.name === 'target' || a.name === 'rel')) props[a.name] = v;
            else attrs.push({ name: a.name, value: v });
        });
        if (attrs.length) props._attrs = attrs;
        return { _id: gid(), type: 'semantic', props: props, children: [] };
    }

    /* @pg-site-output */
    function _sdCodeNewSpan(raw) {
        return { _id: gid(), type: 'content', props: { contentType: 'span', text: raw.trim(), cssClass: '' }, children: [] };
    }

    // The place a new element keeps until the converter answers: its own
    // markup as Custom HTML, which prints the same text.
    /* @pg-site-output */
    function _sdCodeNewHolder(html) {
        return { _id: gid(), type: 'content', props: { contentType: 'custom_html', html: html, cssClass: '', _sdPending: html }, children: [] };
    }

    // The converter reads the new element and the holder gives way to what
    // it made. No answer, or nothing made: the holder stays as Custom HTML
    // and the page prints the markup as typed.
    function _sdCodeImport(holder) {
        var html = holder.props._sdPending;
        if (!html) return;
        _pgApiPost('import_fragment', { html: html }).then(function (res) {
            if (!_sdCodeNodeExists(holder)) return;
            // Typed again meanwhile: ask about the newer text instead.
            if (holder.props.html !== html) { holder.props._sdPending = holder.props.html; _sdCodeImport(holder); return; }
            delete holder.props._sdPending;
            var kids = (res && res.status === 'success' && Array.isArray(res.children)) ? res.children : [];
            if (!kids.length) { render(); return; }
            _backfillIds({ type: 'root', props: {}, children: kids });
            var owner = _sdAnyParent(holder);
            if (!owner || !owner.parent) return;
            var idx = owner.parent.children.indexOf(holder);
            if (idx === -1) return;
            Array.prototype.splice.apply(owner.parent.children, [idx, 1].concat(kids));
            kids.forEach(function (k) { if (typeof _cfAdoptOnDrop === 'function') _cfAdoptOnDrop(k); });
            if (owner.sid) _sharedDirty[owner.sid] = true;
            if (K.selectedNode === holder) K.selectedNode = kids[kids.length - 1];
            _sdCode.applying = true;
            try { render(); } finally { _sdCode.applying = false; }
        }).catch(function () {
            delete holder.props._sdPending;
        });
    }

    // The tag name: a plain element and a heading may change theirs; the
    // rest wear the tag their type prints.
    function _sdCodeApplyTag(node, item, entry, report) {
        var oldOpen = _sdCodeOpenTag(_sdCode.text, entry.from, entry.to);
        var oldTag = oldOpen ? _sdCode.text.slice(oldOpen.tagFrom, oldOpen.tagTo).toLowerCase() : '';
        if (!oldTag || item.tag === oldTag) return;
        if (!/^[a-z][a-z0-9-]*$/.test(item.tag)) return;
        if (node.type === 'semantic') { node.props.tag = item.tag; return; }
        if (node.type === 'content' && node.props.contentType === 'heading' && /^h[1-6]$/.test(item.tag)) { node.props.tag = item.tag; return; }
        report.tag = true;
    }

    // Attributes: only those that differ from what the emitter printed are
    // touched, each by the Attributes panel's rules.
    function _sdCodeApplyAttrs(node, item, entry, lock, report) {
        var oldOpen = _sdCodeOpenTag(_sdCode.text, entry.from, entry.to);
        var oldMap = {};
        if (oldOpen) oldOpen.attrs.forEach(function (a) {
            oldMap[a.name.toLowerCase()] = a.valueFrom === null ? '' : _sdCodeDecode(_sdCode.text.slice(a.valueFrom, a.valueTo));
        });
        var newMap = {};
        item.attrs.forEach(function (a) { newMap[a.name] = a.value === null ? '' : a.value; });
        var names = {};
        Object.keys(oldMap).forEach(function (n) { names[n] = 1; });
        Object.keys(newMap).forEach(function (n) { names[n] = 1; });
        Object.keys(names).forEach(function (name) {
            var was = Object.prototype.hasOwnProperty.call(oldMap, name) ? oldMap[name] : undefined;
            var now = Object.prototype.hasOwnProperty.call(newMap, name) ? newMap[name] : undefined;
            if (was === now) return;
            _sdCodeSetAttr(node, name, was, now, report);
        });
    }

    function _sdCodeSetAttr(node, name, was, now, report) {
        var p = node.props;
        if (name === 'class') {
            var oldT = (was || '').split(/\s+/).filter(Boolean), newT = (now || '').split(/\s+/).filter(Boolean);
            var cls = (p.cssClass || '').split(/\s+/).filter(Boolean);
            var isIcon = node.type === 'content' && p.contentType === 'icon';
            oldT.forEach(function (t) {
                if (newT.indexOf(t) !== -1) return;
                var at = cls.indexOf(t);
                if (at !== -1) cls.splice(at, 1);
                else if (isIcon && /^bi-/.test(t)) { /* the icon itself: replaced below */ }
                else if (t !== 'bi' || !isIcon) report.derived = true;
            });
            newT.forEach(function (t) {
                if (oldT.indexOf(t) !== -1) return;
                if (isIcon && /^bi-/.test(t)) { p.iconName = t; return; }
                if (cls.indexOf(t) === -1) cls.push(t);
            });
            p.cssClass = cls.join(' ');
            return;
        }
        if (name === 'id') {
            if (node.type === 'root') { _sdCodeSetRootAttr(node, 'id', now); return; }
            var oldId = p.id || '';
            p.id = now || '';
            _sdRetargetLabels(oldId, p.id);
            return;
        }
        if (name === 'style') {
            if (node.type === 'root') { _sdCodeSetRootAttr(node, 'style', now); return; }
            // The typed style is the whole attribute: the old inline style
            // field, printed ahead of the Attributes panel's style
            // (_sdNodeStyle()), is folded into it.
            if (p.inlineStyle) p.inlineStyle = '';
            _sdApplyInlineStyle(node, _sdCodeTakeSizing(node, was, now || ''));
            return;
        }
        var key = _sdCodeAttrProp(node, name);
        // A value written as {{token}} inside a widget is a binding, not a
        // literal; a literal written over a binding ends it.
        if (_sdCodeApplyBoundValue(node, key || name, now)) return;
        if (key) { p[key] = now || ''; return; }
        if (_sdCodeSetTitleAttr(node, name, now)) return;
        if (_sdCodeSetFxAttr(node, name, now)) return;
        if (!Array.isArray(p._attrs)) p._attrs = [];
        var idx = -1;
        for (var i = 0; i < p._attrs.length; i++) {
            if (p._attrs[i] && String(p._attrs[i].name || '').toLowerCase() === name) { idx = i; break; }
        }
        if (now === undefined) { if (idx !== -1) p._attrs.splice(idx, 1); }
        else if (idx === -1) p._attrs.push({ name: name, value: now });
        else p._attrs[idx].value = now;
    }

    // The Display & Sizing declarations a typed style held where the
    // emitter printed them (getNodeExtraStyle(), between the type's own
    // style and the Attributes panel's) go back to their props: a changed
    // value sets the prop, a deleted one clears it, an unchanged one leaves
    // it as stored ('600' printed as 600px). Returns the rest of the typed
    // style for _sdApplyInlineStyle(); without this the sizing was copied
    // into _attrs.style and printed twice, and deleting it changed nothing.
    // An image's width and height stay with _sdInlineStyleProps(). When the
    // printed style does not hold the sizing where expected (a component's
    // own markup), the typed style is returned as it is.
    function _sdCodeTakeSizing(node, was, now) {
        var p = node.props;
        var isContent = node.type === 'content';
        var isImg = isContent && p.contentType === 'image';
        if (!isContent && node.type !== 'semantic' && ['container', 'row', 'col'].indexOf(node.type) === -1) return now;
        if (isImg && _sdImgInRatio(p)) return now;
        var sizing = _parseInlineStyle(getNodeExtraStyle(isContent ? _sdContentSizingNode(node) : node));
        if (!sizing.length) return now;
        var wasDecls = _parseInlineStyle(was || '');
        var user = _parseInlineStyle(_sdUserAttrValue(p._attrs, 'style'));
        var at = wasDecls.length - sizing.length - user.length;
        if (at < 0) return now;
        for (var i = 0; i < sizing.length; i++) {
            var w = wasDecls[at + i];
            if (w.name !== sizing[i].name || w.value !== sizing[i].value) return now;
        }
        var props = {
            'z-index': 'zIndex', 'width': 'width', 'min-width': 'minWidth', 'max-width': 'maxWidth',
            'height': 'height', 'min-height': 'minHeight', 'max-height': 'maxHeight',
            'overflow': 'overflow', 'overflow-x': 'overflowX', 'overflow-y': 'overflowY',
            'top': 'top', 'right': 'right', 'bottom': 'bottom', 'left': 'left',
            '--bs-aspect-ratio': 'aspectRatioCustom'
        };
        var nowDecls = _parseInlineStyle(now);
        var lower = function (d) { return String(d.name).toLowerCase(); };
        sizing.forEach(function (d, si) {
            var name = d.name, prop = props[name];
            if (!prop || (isImg && (name === 'width' || name === 'height'))) return;
            // The sizing declaration is the n-th of its name in the printed
            // style (a font or old inline style may have written one before).
            var nth = 0;
            for (var k = 0; k < at + si; k++) if (lower(wasDecls[k]) === name) nth++;
            var hit = -1;
            for (var j = 0; j < nowDecls.length; j++) {
                if (lower(nowDecls[j]) === name && nth-- === 0) { hit = j; break; }
            }
            if (hit === -1) { p[prop] = ''; return; }
            var v = String(nowDecls[hit].value).trim();
            nowDecls.splice(hit, 1);
            if (v !== d.value) p[prop] = v;
        });
        return _serializeInlineDecls(nowDecls);
    }

    // The title and tooltip attributes _sdNodeTitleAttrs() printed go back
    // to the props they came from: the tooltip's text, placement and switch,
    // or the plain title. false when the attribute is the Attributes
    // panel's (an entry of that name, or another toggle's data-bs-toggle),
    // which the caller then writes to the list.
    function _sdCodeSetTitleAttr(node, name, now) {
        var p = node.props;
        if (name !== 'title' && name !== 'data-bs-placement' && name !== 'data-bs-toggle') return false;
        var written = {};
        (Array.isArray(p._attrs) ? p._attrs : []).forEach(function (a) {
            if (a && a.name) written[String(a.name).toLowerCase()] = true;
        });
        if (written[name]) return false;
        var tipOn = !!p.tooltipEnabled && !written['data-bs-toggle'];
        if (name === 'title') {
            if (tipOn) { p.tooltipTitle = now || ''; return true; }
            if (p.title && ['content', 'semantic', 'container', 'row', 'col'].indexOf(node.type) !== -1) { p.title = now || ''; return true; }
            return false;
        }
        if (name === 'data-bs-placement') {
            if (tipOn) { p.tooltipPlacement = now || 'top'; return true; }
            return false;
        }
        // data-bs-toggle="tooltip" is the tooltip's switch; another value
        // typed over it turns the tooltip off and goes to the list.
        if (!tipOn || !p.tooltipTitle) return false;
        if (now === 'tooltip') return true;
        p.tooltipEnabled = false;
        return now === undefined;
    }

    // A data-pg-fx attribute edited in the code view goes back to the
    // interaction it was printed from (_sdFxAttrs()): the node's attributes
    // as printed, with this one changed, read back into props._fx. A
    // data-pg-fx naming no known effect ends the interaction; false when the
    // attribute is not the interaction's (another name, or a value typed on
    // a node that has none and names no effect), which the caller then
    // writes to the Attributes panel's list.
    function _sdCodeSetFxAttr(node, name, now) {
        if (name !== 'data-pg-fx' && name.indexOf('data-pg-fx-') !== 0) return false;
        var p = node.props;
        var had = _sdFxNormalize(p._fx);
        if (!had && name !== 'data-pg-fx') return false;
        var attrs = {};
        _sdFxAttrs(p).replace(/\s(data-pg-fx[a-z-]*)(?:="([^"]*)")?/g, function (all, n, v) { attrs[n] = v === undefined ? '' : v; return all; });
        if (now === undefined) delete attrs[name]; else attrs[name] = now;
        var fx = { effect: attrs['data-pg-fx'] };
        ['trigger', 'duration', 'delay', 'easing', 'stagger', 'distance', 'offset', 'speed', 'from'].forEach(function (f) {
            if (attrs['data-pg-fx-' + f] !== undefined) fx[f] = attrs['data-pg-fx-' + f];
        });
        fx.repeat = attrs['data-pg-fx-repeat'] !== undefined;
        fx = _sdFxNormalize(fx);
        if (fx) { p._fx = fx; return true; }
        if (!had) return false;
        delete p._fx;
        return true;
    }

    // The body tag's id and style live where the Attributes panel keeps the
    // root node's: the dedicated field and the attribute list respectively.
    function _sdCodeSetRootAttr(node, name, now) {
        var p = node.props;
        if (name === 'id') { p.id = now || ''; return; }
        if (!Array.isArray(p._attrs)) p._attrs = [];
        // The style printed is the font and inline style props plus
        // _attrs.style; what is typed replaces the lot as _attrs.style.
        p.fontFamily = '';
        p.inlineStyle = '';
        var idx = -1;
        for (var i = 0; i < p._attrs.length; i++) if (p._attrs[i] && String(p._attrs[i].name || '').toLowerCase() === 'style') { idx = i; break; }
        if (!now) { if (idx !== -1) p._attrs.splice(idx, 1); }
        else if (idx === -1) p._attrs.push({ name: 'style', value: now });
        else p._attrs[idx].value = now;
    }

    // The prop an attribute of this node is printed from, when it has one.
    function _sdCodeAttrProp(node, name) {
        var p = node.props, t = node.type, ct = p.contentType, cpt = p.componentType;
        if (t === 'content' && ct === 'image' && /^(src|alt|width|height)$/.test(name)) return name;
        var linkLike = (t === 'content' && ct === 'link') || (t === 'semantic' && String(p.tag || '').toLowerCase() === 'a')
            || (t === 'component' && cpt === 'btn' && (p.btnElement || 'a') === 'a');
        if (linkLike && /^(href|target|rel)$/.test(name)) return name;
        if (t === 'component' && cpt === 'btn' && p.btnElement === 'input') { if (name === 'type') return 'btnType'; if (name === 'value') return 'text'; }
        if (t === 'component' && cpt === 'btn' && p.btnElement === 'button' && name === 'type') return 'btnType';
        return null;
    }

    // Where a plain element's single text sits against its children: before
    // them all ('start'), after them all ('end'), or nowhere clean (null).
    function _sdCodeTextSlot(node, items) {
        var firstEl = -1, lastEl = -1, firstTx = -1, lastTx = -1;
        items.forEach(function (it, i) {
            if (it.kind === 'element') { if (firstEl === -1) firstEl = i; lastEl = i; }
            else if (it.raw.trim() !== '') { if (firstTx === -1) firstTx = i; lastTx = i; }
        });
        if (firstTx === -1) return 'none';
        if (firstEl === -1) return 'end';
        if (lastTx < firstEl) return 'start';
        if (firstTx > lastEl) return 'end';
        return null;
    }

    // The node's own text, by the kind of node.
    function _sdCodeApplyText(node, item, entry, report) {
        var ed = _sdCode.ed;
        var kind = entry.kind || _sdCodeTextKind(node);
        if (kind === 'none') return;
        if (kind === 'outer') { node.props.html = ed.sliceDoc(item.from, item.to); return; }
        if (kind === 'inner') {
            // Everything between the tags is the text, except the children
            // that are nodes of their own (an icon inside a link); those
            // stay children, in the order they are in, and one removed
            // from the text goes.
            var inner = '';
            var pos = item.innerFrom;
            var kept = [];
            (item.items || []).forEach(function (it) {
                var owner = (it.kind === 'element') ? _sdCodeOwnerOf(it) : null;
                if (owner && owner.node) {
                    inner += ed.sliceDoc(pos, it.from);
                    pos = it.to;
                    _sdCodeReconcileNode(owner.node, it, owner, report);
                    kept.push(owner.node);
                }
            });
            inner += ed.sliceDoc(pos, item.innerTo);
            node.props.text = _sdCodeTextFromTyped(node, inner.trim());
            if (_sdCodeLockOf(node).level === '' && !entry.atomic) node.children = kept;
            return;
        }
        // plain: a semantic element's text node, entities decoded. Beside
        // children it keeps its one slot; mixed with them it is split into
        // spans by _sdCodeReconcileChildren().
        var items = item.items || [];
        var slot = _sdCodeTextSlot(node, items);
        if (slot === null) return;   // handled as spans
        var runs = items.filter(function (it) { return it.kind === 'text' && it.raw.trim() !== ''; });
        var ws = ['pre', 'textarea', 'code'].indexOf(String(node.props.tag || '').toLowerCase()) !== -1;
        var joined = runs.map(function (r) { return r.raw; }).join('');
        node.props.text = _sdCodeTextFromTyped(node, ws ? _sdCodeDecode(joined) : _sdCodeDecode(joined.replace(/\s+/g, ' ').trim()));
        var tag = String(node.props.tag || '').toLowerCase();
        if ((tag === 'button' || tag === 'a') && slot !== 'none') node.props.textPosition = slot;
    }

    // ========================= CODE VIEW: DATA BINDINGS AS {{TOKENS}} =========================
    // A value a system widget fills at render time (props._bindings.text,
    // href, src, alt) is not in the page's HTML: the tree keeps the
    // design-time value and the server puts the bound one in its place. The
    // code view would show the design-time value, and a change to it would
    // show nothing on the site, so there a bound value prints as {{token}}.
    // Typing {{token}} over a bindable value of an element inside a widget
    // binds it; writing a literal over a {{token}} ends the binding; typing
    // {{ offers the tokens the Bind Data panel offers. Outside a widget the
    // braces are text like any other.
    var _SD_CODE_BIND_RE = /\{\{\s*([A-Za-z0-9_-]+)\s*\}\}/;

    // A token still being typed or picked from the completion list: a {{
    // with no }} after it, or an empty {{}} (closed brackets).
    function _sdCodeHalfToken(value) {
        return (typeof value === 'string') && (/\{\{(?![\s\S]*\}\})/.test(value) || /\{\{\s*\}\}/.test(value));
    }

    // The code view applies what is typed after a pause, so a token being
    // typed over a bound (or bindable) value is applied half done: the
    // binding ends and "{{" stands as the value. What the prop held before
    // that is kept here, once, so the finished token gets the design-time
    // text or value and the date format back (_sdCodeBindHeld()). A literal
    // typed over it instead lets it go. One at a time: one token is typed
    // at once.
    function _sdCodeHoldBinding(node, prop, typed) {
        var held = _sdCode.heldBinding;
        var same = !!(held && held.node === node && held.prop === prop);
        if (!_sdCodeHalfToken(typed)) {
            if (same) _sdCode.heldBinding = null;
            return;
        }
        if (same) return;
        var p = node.props;
        _sdCode.heldBinding = {
            node: node,
            prop: prop,
            token: (p._bindings && p._bindings[prop]) || '',
            has: Object.prototype.hasOwnProperty.call(p, prop),
            value: p[prop],
            format: (p._bindFormats && p._bindFormats[prop]) || ''
        };
    }

    // Binds `prop` to `token` (_sdNodeSetBinding()), bringing back the date
    // format held for the same token. The record held for this node and
    // prop ({ has, value, ... }), or null when nothing was held.
    function _sdCodeBindHeld(node, prop, token) {
        var held = _sdCode.heldBinding;
        if (held && held.node === node && held.prop === prop) _sdCode.heldBinding = null;
        else held = null;
        _sdNodeSetBinding(node, prop, token);
        if (held && held.format && held.token === token) {
            if (!node.props._bindFormats) node.props._bindFormats = {};
            node.props._bindFormats[prop] = held.format;
        }
        return held;
    }

    // Whether `prop` of this node is a binding a widget reads: the node is
    // inside a system widget and the Bind Data panel offers the prop. The
    // panel offers a plain element's text only when it has one, so the text
    // being typed stands in for it (`textHint`).
    function _sdCodeBindable(node, prop, textHint) {
        if (!node || !prop || !_isInsideSystemWidget(node)) return false;
        var probe = node;
        if (prop === 'text' && textHint && node.props && !node.props.text) {
            var props = {};
            for (var k in node.props) if (Object.prototype.hasOwnProperty.call(node.props, k)) props[k] = node.props[k];
            props.text = textHint;
            probe = { type: node.type, props: props, children: node.children };
        }
        var list = _getBindableProps(probe);
        for (var i = 0; i < list.length; i++) if (list[i].prop === prop) return true;
        return false;
    }

    // The run of words a bound text's {{token}} stands for in the code
    // view: what _sdBindTextSlot() replaces. The whole text when the markup
    // wraps no single run.
    function _sdCodeSlotRun(text) {
        text = (text == null) ? '' : String(text);
        if (!/<[a-z\/!]/i.test(text)) return text;
        var parts = text.split(/(<[^>]*>)/);
        var slot = -1;
        for (var i = 0; i < parts.length; i += 2) {
            if (parts[i] === '') continue;
            if (_sdDecodeEntities(parts[i]).replace(/ /g, ' ').trim() === '') continue;
            if (slot !== -1) return text;
            slot = i;
        }
        if (slot === -1) return text;
        return /^(\s*)([\s\S]*?)(\s*)$/.exec(parts[slot])[2];
    }

    // The text a typed value leaves on the node once its {{token}} is read:
    // the token binds the text, and the words it stood for go back in its
    // place, so the design-time text survives a change to the markup around
    // the token. A text without a token typed over a bound text ends the
    // binding and stands as written.
    function _sdCodeTextFromTyped(node, typed) {
        typed = (typed == null) ? '' : String(typed);
        if (!_sdCodeBindable(node, 'text', typed)) return typed;
        var cur = (node.props._bindings && node.props._bindings.text) || '';
        var m = _SD_CODE_BIND_RE.exec(typed);
        if (!m) {
            _sdCodeHoldBinding(node, 'text', typed);
            if (cur) _sdNodeSetBinding(node, 'text', '');
            return typed;
        }
        var before = node.props.text;
        if (m[1] !== cur) {
            var held = _sdCodeBindHeld(node, 'text', m[1]);
            if (held) before = held.has ? held.value : '';
        }
        var kept = typed.replace(m[0], _sdCodeSlotRun(before));
        // Words typed beside the token are kept on the node but never
        // shown: the page prints the bound value alone unless the markup
        // wraps a single run of words (pg_binding_text_slot()). Said once,
        // like a refused keystroke.
        if (_sdBindTextSlot(kept, m[0]) !== typed && Date.now() - (_sdCode.bindToastAt || 0) > 2500) {
            _sdCode.bindToastAt = Date.now();
            sdToast(esc(_sdT('The words typed beside the token are not shown: a bound element shows the bound value alone. Give them an element of their own.')), 'warning', 4000);
        }
        return kept;
    }

    // A typed attribute value, or the value of an <input> button: {{token}}
    // binds `prop`, a literal over a binding ends it. True when the value
    // was the binding and the caller has nothing left to store.
    function _sdCodeApplyBoundValue(node, prop, value) {
        if (!_sdCodeBindable(node, prop, (prop === 'text') ? value : '')) return false;
        var cur = (node.props._bindings && node.props._bindings[prop]) || '';
        var m = (typeof value === 'string') ? /^\{\{\s*([A-Za-z0-9_-]+)\s*\}\}$/.exec(value) : null;
        if (m) {
            if (m[1] !== cur) {
                var held = _sdCodeBindHeld(node, prop, m[1]);
                // The design-time value comes back from under the "{{".
                if (held && held.has) node.props[prop] = held.value;
                else if (held) delete node.props[prop];
            }
            return true;
        }
        _sdCodeHoldBinding(node, prop, value);
        if (cur) _sdNodeSetBinding(node, prop, '');
        return false;
    }

    // The node whose element holds a position of the code view.
    function _sdCodeNodeAtPos(pos) {
        var hits = _sdCode.ed.trackedAt(pos);
        for (var i = 0; i < hits.length; i++) {
            var d = hits[i].data;
            if (d && d.role === 'node' && d.node) return d.node;
        }
        return null;
    }

    // Inside an element's text, or the value of a bindable attribute, typing
    // {{ offers the tokens of the element's widget. In any other attribute
    // that takes a value of the record (_sdAttrBindNameOk()) it offers the
    // tokens an attribute may print. Only in the page's code view: a file
    // tab and the dialog have no nodes.
    function _sdCodeBindingCompletion(context, syntax) {
        if (!_sdCodeEnabled() || !_sdCode.ed.view) return null;
        if (context.view ? (context.view !== _sdCode.ed.view) : (context.state.doc !== _sdCode.ed.view.state.doc)) return null;
        var word = context.matchBefore(/\{\{\s*[A-Za-z0-9_-]*/);
        if (!word) return null;
        var sn = syntax.node;
        var prop = '', inAttr = false;
        if (sn.name === 'AttributeValue' || sn.name === 'UnquotedAttributeValue') {
            var attr = sn.parent;
            var nameNode = attr ? attr.getChild('AttributeName') : null;
            prop = nameNode ? context.state.sliceDoc(nameNode.from, nameNode.to).toLowerCase() : '';
            if (['href', 'src', 'alt', 'value'].indexOf(prop) === -1) {
                if (!_sdAttrBindNameOk(prop)) return null;
                inAttr = true;
            }
        } else if (sn.name === 'Text') {
            prop = 'text';
        } else {
            return null;
        }
        var node = _sdCodeNodeAtPos(context.pos);
        if (!node) return null;
        if (inAttr) {
            if (!_isInsideSystemWidget(node)) return null;
        } else {
            if (prop !== 'text') prop = _sdCodeAttrProp(node, prop) || prop;
            if (prop === 'value' || !_sdCodeBindable(node, prop, (prop === 'text') ? word.text : '')) return null;
        }
        var options = _swBindTokenOptions(node);
        if (inAttr) options = options.filter(function (o) { return o.token && _sdAttrTokenOk(o.token); });
        if (!options.length) return null;
        var typedFrom = word.from + word.text.replace(/[A-Za-z0-9_-]*$/, '').length;
        var typed = context.state.sliceDoc(typedFrom, context.pos);
        var closed = context.state.sliceDoc(context.pos, context.pos + 2) === '}}';
        var seen = {}, out = [];
        options.forEach(function (o) {
            if (!o.token || seen[o.token] || o.token === typed) return;
            seen[o.token] = true;
            var opt = { label: o.token, type: 'variable', apply: closed ? o.token : o.token + '}}' };
            if (o.label && o.label !== o.token) opt.detail = String(o.label).replace(/<[^>]*>/g, '');
            out.push(opt);
        });
        return out.length ? { from: typedFrom, options: out, validFor: /^[A-Za-z0-9_-]*$/ } : null;
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
        dregionOpen: _sdDregionCodeOpen,
        flush: _sdCodeFlush,
        bindingCompletion: _sdCodeBindingCompletion
    };
});
