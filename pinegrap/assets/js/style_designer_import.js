/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the import part: the HTML / ZIP import dialog, the
 * upload to designer_import.php, and the reply (from the import or the
 * paste-a-page dialog) opened as unsaved tabs, with the blocks repeated
 * across the pages offered as shared components. Not needed to open a page:
 * the editor runs this file the first time the import is used
 * (_sdPartLoad(), pg_designer_parts()), and the file reaches the editor
 * only through the kit it is handed (_sdPartKit()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('import', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var gid = K.gid;
    var getNodeLabel = K.getNodeLabel;
    var createDefaultTree = K.createDefaultTree;
    var reindex = K.reindex;
    var _backfillIds = K._backfillIds;
    var _normalizeToggleableShowState = K._normalizeToggleableShowState;
    var _pgNewPageDraft = K._pgNewPageDraft;
    var _pgTabsInsertAfter = K._pgTabsInsertAfter;
    var _pgTabsPersistActive = K._pgTabsPersistActive;
    var _pgTabsRender = K._pgTabsRender;
    var _pgTabsSwitch = K._pgTabsSwitch;
    var _pgTemplateTrackRows = K._pgTemplateTrackRows;
    var _pgToken = K._pgToken;
    var _pgTreeOf = K._pgTreeOf;
    var _refreshSharedPalette = K._refreshSharedPalette;
    var _sdSmartActiveHost = K._sdSmartActiveHost;
    var _sdUsesBootstrap = K._sdUsesBootstrap;

    // ── HTML / ZIP import ─────────────────────────────────────────────────
    // designer_import.php does the parsing; this side opens what comes back
    // as unsaved tabs and folds the assets into the shared panel.
    function _pgImportOpen() {
        var modalEl = document.getElementById('sdImportModal');
        if (!modalEl) return;
        var status = document.getElementById('sd-import-status');
        if (status) { status.style.display = 'none'; status.innerHTML = ''; }
        var file = document.getElementById('sd-import-file'); if (file) file.value = '';
        var run = document.getElementById('sd-import-run');
        if (run && !run._pgBound) {
            run._pgBound = true;
            run.addEventListener('click', _pgImportRun);
        }
        pgUI.dialog.show(modalEl);
    }

    function _pgImportRun() {
        var fileEl = document.getElementById('sd-import-file');
        var projEl = document.getElementById('sd-import-project');
        var status = document.getElementById('sd-import-status');
        var run    = document.getElementById('sd-import-run');
        if (!fileEl || !fileEl.files || !fileEl.files.length) {
            sdToast(_sdT('Choose a file first.'), 'warning', 3000);
            return;
        }
        var f = fileEl.files[0];
        var fd = new FormData();
        fd.append('file', f, f.name);
        fd.append('project_name', projEl ? projEl.value : '');
        fd.append('token', _pgToken());
        _pgTabsPersistActive();
        fd.append('skip_names', JSON.stringify(K.pages.map(function (p) { return p.page_name; }).filter(Boolean)));
        // A design without a framework keeps the project's own Bootstrap.
        fd.append('framework_bootstrap', _sdUsesBootstrap() ? '1' : '0');

        if (run) { run.disabled = true; }
        if (status) { status.style.display = ''; status.innerHTML = '<span class="spinner-border spinner-border-sm me-3"></span>' + esc(_sdT('Importing…')); }

        var url = (window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/designer_import.php';
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.text().then(function (t) { var j = null; try { j = JSON.parse(t); } catch (e) {} return { ok: r.ok, status: r.status, data: j, raw: t }; }); })
        .then(function (res) {
            if (run) run.disabled = false;
            if (!res.data || res.data.status !== 'success') {
                var msg = (res.data && res.data.message) ? res.data.message : (_sdT('Import failed') + ' (' + res.status + ').');
                if (status) status.innerHTML = '<span class="fg-danger"><span class="bi bi-x-circle me-1"></span>' + esc(msg) + '</span>';
                return;
            }
            _pgImportApply(res.data, projEl ? projEl.value : '');
        })
        .catch(function () {
            if (run) run.disabled = false;
            if (status) status.innerHTML = '<span class="fg-danger">' + esc(_sdT('Network error.')) + '</span>';
        });
    }

    /**
     * Open an import reply as tabs.
     *
     * Shared by the file import and the paste-a-page dialog: both produce the
     * same {pages, css, js, fonts, warnings} shape, and a second copy of this
     * would drift the moment one of them learned something the other did not.
     */
    /* ── Parts repeated across the imported pages ─────────────────────────
     * A site exported page by page carries its header, footer, call to
     * action band… once per page. Before the pages open, blocks that are the
     * same on two or more pages are offered as shared components: accepted,
     * each becomes one shared_components row and every copy a reference to
     * it, so the next edit to the header is made once.
     *
     * "The same" is compared on the tree with the editor ids left out. A
     * menu that marks the current page differs on every page only by its
     * active link; such blocks are matched with the active states ignored,
     * and the shared copy gets the smart active state, which marks the
     * current page's link when the page is drawn.
     *
     * The rows are made at once (the canvas draws a reference from its row)
     * and carry category "import:…"; leaving a never-published design
     * discards them like a template's (pg_design_template_discard()).
     */
    var _PG_IMPORT_SHARED_DEPTH = 3;      // how deep under <body> blocks are looked for
    var _PG_IMPORT_SHARED_MIN   = 3;      // descendants a block needs to be worth sharing

    function _pgImportStripIds(node, activeToo) {
        if (!node || typeof node !== 'object') return node;
        var out = {};
        Object.keys(node).forEach(function (k) {
            if (k === '_id' || k.indexOf('_pgImp') === 0) return;
            if (k === 'children') {
                out.children = (node.children || []).map(function (c) { return _pgImportStripIds(c, activeToo); });
                return;
            }
            if (k === 'props') {
                var src = (node.props && !Array.isArray(node.props)) ? node.props : {};
                var pr = {};
                Object.keys(src).forEach(function (pk) { pr[pk] = src[pk]; });
                if (activeToo) {
                    if (typeof pr.cssClass === 'string') {
                        pr.cssClass = pr.cssClass.split(/\s+/).filter(function (c) { return c && c !== 'active'; }).join(' ');
                    }
                    if (Array.isArray(pr._attrs)) {
                        pr._attrs = pr._attrs.filter(function (a) { return !(a && String(a.name || '').toLowerCase() === 'aria-current'); });
                    }
                }
                out.props = pr;
                return;
            }
            out[k] = node[k];
        });
        return out;
    }

    function _pgImportSize(node) {
        var n = 0;
        (node.children || []).forEach(function (c) { n += 1 + _pgImportSize(c); });
        return n;
    }

    function _pgImportText(node) {
        var p = (node && node.props && !Array.isArray(node.props)) ? node.props : {};
        var t = (typeof p.text === 'string' ? p.text : '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (t) return t;
        for (var i = 0; i < (node.children || []).length; i++) {
            var c = _pgImportText(node.children[i]);
            if (c) return c;
        }
        return '';
    }

    function _pgImportBlockName(node, projectName) {
        var tag = (node.props && node.props.tag) || '';
        var names = { header: _sdT('Site Header'), footer: _sdT('Site Footer'), nav: _sdT('Navigation'), aside: _sdT('Sidebar') };
        var label = names[tag];
        if (!label) {
            // A band in the middle of the page reads by its first words.
            var words = _pgImportText(node).slice(0, 30).trim();
            label = (tag === 'section' ? _sdT('Section') : getNodeLabel(node)) + (words ? ' · ' + words : '');
        }
        return (projectName ? projectName + ' · ' : '') + label;
    }

    // The groups of blocks that two or more pages share, outermost first: a
    // block inside a shared one is not offered on its own.
    function _pgImportFindShared(pages) {
        var groups = {};
        var order = [];
        pages.forEach(function (sp, pi) {
            var walk = function (n, depth) {
                (n.children || []).forEach(function (c) {
                    if (!c || (c.type !== 'semantic' && c.type !== 'container')) return;
                    if (_pgImportSize(c) >= _PG_IMPORT_SHARED_MIN) {
                        var sig = JSON.stringify(_pgImportStripIds(c, true));
                        var g = groups[sig];
                        if (!g) { g = groups[sig] = { sig: sig, pages: {}, nodes: [], raw: {}, depth: depth }; order.push(g); }
                        g.pages[pi] = true;
                        g.nodes.push(c);
                        g.raw[JSON.stringify(_pgImportStripIds(c, false))] = true;
                        if (depth < g.depth) g.depth = depth;
                    }
                    if (depth < _PG_IMPORT_SHARED_DEPTH) walk(c, depth + 1);
                });
            };
            if (sp.tree) walk(sp.tree, 1);
        });
        var list = order.filter(function (g) { return Object.keys(g.pages).length >= 2; });
        list.sort(function (a, b) { return (a.depth - b.depth) || (_pgImportSize(b.nodes[0]) - _pgImportSize(a.nodes[0])); });
        var mark = function (n) { (n.children || []).forEach(function (c) { c._pgImpInside = true; mark(c); }); };
        var accepted = list.filter(function (g) {
            if (g.nodes.every(function (n) { return n._pgImpInside; })) return false;
            g.nodes.forEach(mark);
            return true;
        });
        var unmark = function (n) { delete n._pgImpInside; (n.children || []).forEach(unmark); };
        pages.forEach(function (sp) { if (sp.tree) unmark(sp.tree); });
        return accepted;
    }

    // The question: one line per block, ticked; resolves with the chosen
    // groups ([] when the operator keeps the pages as they are).
    function _pgImportAskShared(groups, projectName) {
        return new Promise(function (resolve) {
            var rows = groups.map(function (g, i) {
                var preview = _pgImportText(g.nodes[0]).slice(0, 70);
                var tag = (g.nodes[0].props && g.nodes[0].props.tag) || g.nodes[0].type;
                return '<label class="list-group-item d-flex gap-3 align-items-start">' +
                    '<input class="check flex-shrink-0 mt-1" type="checkbox" checked data-sd-imp-group="' + i + '">' +
                    '<span class="flex-grow-1 min-w-0">' +
                        '<span class="d-block fw-semibold">' + esc(_pgImportBlockName(g.nodes[0], '')) +
                            ' <code class="small">&lt;' + esc(tag) + '&gt;</code></span>' +
                        '<span class="d-block small fg-secondary">' + esc(_sdT('On {var} pages', Object.keys(g.pages).length)) +
                            (preview ? ' · ' + esc(preview) : '') + '</span>' +
                    '</span>' +
                '</label>';
            }).join('');
            var el = document.createElement('dialog');
            el.className = 'dialog pg-dialog-centered dialog-scrollable';
            el.tabIndex = -1;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('aria-labelledby', 'sd-imp-shared-title');
            el.innerHTML =
                '<div class="dialog-header">' +
                            '<h5 class="dialog-title" id="sd-imp-shared-title"><span class="bi bi-puzzle me-3" aria-hidden="true"></span>' + esc(_sdT('Make the repeated parts shared?')) + '</h5>' +
                        '</div>' +
                        '<div class="dialog-body">' +
                            '<p class="small mb-5">' + esc(_sdT('These blocks are the same on more than one imported page. As shared components they are edited once and change on every page; left as they are, each page keeps its own copy.')) + '</p>' +
                            '<div class="list-group">' + rows + '</div>' +
                        '</div>' +
                        '<div class="dialog-footer">' +
                            '<button type="button" class="btn btn-solid theme-secondary" data-sd-imp="keep">' + esc(_sdT('Keep them on each page')) + '</button>' +
                            '<button type="button" class="btn btn-solid theme-primary" data-sd-imp="share">' + esc(_sdT('Make shared')) + '</button>' +
                        '</div>';
            document.body.appendChild(el);
            var chosen = [];
            var modal = pgUI.dialog.get(el, { backdrop: 'static', keyboard: false });
            el.querySelector('[data-sd-imp="share"]').addEventListener('click', function () {
                el.querySelectorAll('[data-sd-imp-group]').forEach(function (cb) {
                    if (cb.checked) chosen.push(groups[parseInt(cb.getAttribute('data-sd-imp-group'), 10)]);
                });
                modal.hide();
            });
            el.querySelector('[data-sd-imp="keep"]').addEventListener('click', function () { modal.hide(); });
            pgUI.dialog.on(el, 'hidden', function () {
                if (el.parentNode) el.parentNode.removeChild(el);
                resolve(chosen);
            });
            modal.show();
        });
    }

    // One row for a chosen group, then every copy on every page becomes a
    // reference to it. Resolves with the number of rows made.
    function _pgImportMakeShared(groups, pages, projectName) {
        var made = 0;
        var chain = Promise.resolve();
        groups.forEach(function (g) {
            chain = chain.then(function () {
                var varies = Object.keys(g.raw).length > 1;
                var tree = JSON.parse(JSON.stringify(_pgImportStripIds(g.nodes[0], varies)));
                if (varies) {
                    // The current page's link is marked when the page is drawn.
                    var host = null, queue = [tree];
                    while (queue.length && !host) {
                        var n = queue.shift();
                        if (_sdSmartActiveHost(n)) host = n;
                        (n.children || []).forEach(function (c) { queue.push(c); });
                    }
                    (host || tree).props.smartActive = true;
                }
                _backfillIds(tree);
                var url = (K.design && K.design.apiUrl) ? K.design.apiUrl : ((window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/api.php');
                return fetch(url, { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ action: 'shared_component', sub_action: 'create', token: _pgToken(),
                        name: _pgImportBlockName(g.nodes[0], projectName),
                        tree_json: JSON.stringify(tree),
                        category: 'import:' + String(projectName || 'paste').slice(0, 80) })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    var sid = res && res.status === 'success' ? parseInt(res.id, 10) : 0;
                    if (!(sid > 0)) return;
                    made++;
                    var name = res.name || _pgImportBlockName(g.nodes[0], projectName);
                    K.sharedCache[sid] = { name: name, tree: tree };
                    _pgTemplateTrackRows({ shared: [{ id: sid }] });
                    var swap = function (n) {
                        (n.children || []).forEach(function (c, i) {
                            if (g.nodes.indexOf(c) !== -1) {
                                n.children[i] = { _id: gid(), type: 'shared_ref', props: { sharedId: sid, sharedName: name }, children: [] };
                            } else {
                                swap(c);
                            }
                        });
                    };
                    pages.forEach(function (sp) { if (sp.tree) swap(sp.tree); });
                });
            });
        });
        return chain.then(function () { return made; });
    }

    function _pgImportApply(d, projectName) {
        var pages = (d && d.pages) || [];
        var groups = (pages.length > 1) ? _pgImportFindShared(pages) : [];
        if (!groups.length) { _pgImportApplyPages(d, projectName, 0); return; }
        var im = document.getElementById('sdImportModal');
        if (im) pgUI.dialog.hide(im);
        _pgImportAskShared(groups, projectName).then(function (chosen) {
            if (!chosen.length) return 0;
            return _pgImportMakeShared(chosen, pages, projectName).catch(function () {
                sdToast(_sdT('The shared components could not all be made; the pages keep their own copies of the rest.'), 'warning', 7000);
                return 0;
            });
        }).then(function (made) {
            _pgImportApplyPages(d, projectName, made || 0);
        });
    }

    function _pgImportApplyPages(d, projectName, sharedMade) {
            // The new tabs' keys are settled first: a stylesheet or script
            // that only some of the imported pages used (`page_names`, from
            // pg_di_scope_assets()) is kept to those tabs, as "tab:<key>"
            // until the save gives them ids.
            var tabKeys = [], keyByName = {};
            (d.pages || []).forEach(function (sp) {
                K.newTabSeq++;
                var k = 'new' + Date.now() + '_' + K.newTabSeq;
                tabKeys.push(k);
                if (sp.page_name) keyByName[sp.page_name] = k;
            });
            function scoped(list) {
                return (Array.isArray(list) ? list : []).map(function (a) {
                    if (!a || !Array.isArray(a.page_names)) return a;
                    var c = Object.assign({}, a);
                    delete c.page_names;
                    var refs = a.page_names.filter(function (n) { return !!keyByName[n]; }).map(function (n) { return 'tab:' + keyByName[n]; });
                    if (refs.length) c.pages = refs;
                    return c;
                });
            }
            // Assets first, so the canvas paints the new pages with their CSS.
            var added = (typeof window.sdImportAssets === 'function') ? window.sdImportAssets({ css: scoped(d.css), js: scoped(d.js), fonts: d.fonts }) : { css: 0, js: 0, fonts: 0 };
            // A brand-new design opens with one blank, unnamed tab. Once the
            // import brings real pages, that placeholder is only in the way.
            if ((d.pages || []).length) {
                _pgTabsPersistActive();
                K.pages = K.pages.filter(function (p) {
                    var t = _pgTreeOf(p);
                    var blank = p.page_id === 0 && !p.page_name && (!t || !t.children || !t.children.length);
                    return !blank;
                });
            }
            // Imported pages live in the project's Import/<name> folder, next
            // to their files. The folder was created a moment ago, so the
            // settings panel's folder list does not know it yet — add it.
            var importFolder = parseInt(d.folder_id, 10) || 0;
            if (importFolder > 0) {
                var fsel = document.getElementById('pg_page_folder');
                if (fsel && ![].some.call(fsel.options, function (o) { return o.value === String(importFolder); })) {
                    var fo = document.createElement('option');
                    fo.value = String(importFolder);
                    fo.textContent = d.folder_label || (_sdT('Import / ') + (projectName || ''));
                    fsel.appendChild(fo);
                }
            }
            // Pages as unsaved tabs, in their order right of the open one.
            var firstKey = null;
            var afterKey = K.activeKey;
            (d.pages || []).forEach(function (sp, i) {
                var tree = sp.tree || createDefaultTree();
                _backfillIds(tree);
                _normalizeToggleableShowState(tree);
                var p = {
                    key: tabKeys[i],
                    page_id: 0, page_name: sp.page_name || '', page_folder: importFolder,
                    page_title: sp.page_title || '', page_meta_description: sp.page_meta_description || '', page_meta_tags: '', page_custom_jsonld: '',
                    page_canonical_url: '', page_noarchive: 0, page_max_snippet: '',
                    page_search: 1, page_search_keywords: '', page_sitemap: 1, page_home: 0, page_noindex: 0, page_nofollow: 0,
                    page_draft: _pgNewPageDraft(),
                    pg_comments: 0, pg_comments_label: '', pg_comments_allow_new: 1, pg_comments_rating: 0,
                    pg_comments_attachments: 0, pg_comments_select_name: 0, pg_comments_message: '', pg_comments_closed_message: '', pg_comments_watchers_managed: 0,
                    tree: tree, undoStack: [], redoStack: [], nodeIdCounter: reindex(tree),
                    baseline: null, treeLoadWarning: false
                };
                _pgTabsInsertAfter(p, afterKey);
                afterKey = p.key;
                if (!firstKey) firstKey = p.key;
            });
            var im = document.getElementById('sdImportModal');
            if (im) pgUI.dialog.hide(im);
            if (firstKey) _pgTabsSwitch(firstKey); else _pgTabsRender();
            var summary = (d.pages || []).length + ' ' + _sdT('page(s)') + ', ' + added.css + ' CSS, ' + added.js + ' JS, ' + added.fonts + ' ' + _sdT('font(s)') +
                (sharedMade ? ', ' + _sdT('{var} shared component(s)', sharedMade) : '');
            if (sharedMade && typeof _refreshSharedPalette === 'function') _refreshSharedPalette();
            sdToast('<strong>' + esc(_sdT('Imported:')) + '</strong> ' + esc(summary) + '<br>' + esc(_sdT('Review the tabs, then publish.')), 'success', 7000);
            if (Array.isArray(d.warnings) && d.warnings.length) {
                sdToast('<strong>' + esc(_sdT('Notes')) + '</strong><br>' + d.warnings.slice(0, 8).map(esc).join('<br>') + (d.warnings.length > 8 ? '<br>…' : ''), 'warning', 12000);
            }
    }

    return { open: _pgImportOpen, apply: _pgImportApply };
});
