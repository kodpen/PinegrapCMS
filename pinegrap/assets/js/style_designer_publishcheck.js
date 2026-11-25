/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Publish check part: what is wrong with a page,
 * checked when the Publish sheet opens for its ticked pages. Each page's
 * existing checks run where they always ran (the canvas audit, the links'
 * map read, the server's render for search engines and accessibility, the
 * starters' sample content) and are read into one list of findings
 * (PgPublishFindings, PgPublishContent), kept per page until its tree or
 * settings change. The sheet (pg_designer_publish.js) shows each page's
 * result; an open row lists the findings in five groups, and a press on a
 * finding's path closes the sheet and selects the element, with one line
 * over the canvas that leads back. Not needed to open a page: the core runs
 * this file the first time the sheet checks a page (_sdPartLoad(),
 * pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('publishcheck', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var F = window.PgPublishFindings;
    var C = window.PgPublishContent;

    // The groups' heads, in the sheet's order.
    var GROUP_ICON = { links: 'bi-link-45deg', a11y: 'bi-universal-access', seo: 'bi-search', weight: 'bi-speedometer2', content: 'bi-card-text' };
    // The groups read from the server's render of a saved page.
    var RENDERED = { a11y: true, seo: true };

    var kept = {};       // key => { sig, found, sum, saved }
    var samples = null;  // the starters' sample values, built once
    var line = null;     // the line over the canvas after a press on a path

    function _pcGroupName(g) {
        return { links: _sdT('Links'), a11y: _sdT('Accessibility'), seo: _sdT('Search engines'), weight: _sdT('Weight'), content: _sdT('Content') }[g] || g;
    }

    // The sentences of the codes no check words itself.
    function _pcTexts() {
        return {
            link_broken: _sdT('The link leads to a page that is gone.'),
            link_empty: _sdT('The link has no target.'),
            page_unreached: _sdT('No link of the design leads to this page.'),
            field_leftover: _sdT('A value is kept for a component field that is closed.'),
            audit_failed: _sdT('The page check did not run.'),
            link_failed: _sdT('The links could not be checked.'),
            seo_failed: _sdT('The search engine check did not run.'),
            a11y_failed: _sdT('The accessibility check did not run.'),
            component_failed: _sdT('The component fields could not be checked.'),
            sample_text: _sdT('A starter\'s sample words are still on the page.'),
            sample_image: _sdT('A starter\'s sample picture is still on the page.'),
            link_self: _sdT('The link opens the page it is on.')
        };
    }

    // What a page's check depends on: its tree and the settings the
    // search engine check reads. The same signature keeps the findings.
    function _pcSig(p, t) {
        return JSON.stringify([t, p.page_id || 0, p.page_name || '', p.page_title || '', p.page_meta_description || '',
            p.page_search_keywords || '', p.page_search ? 1 : 0, p.page_sitemap ? 1 : 0, p.page_noindex ? 1 : 0]);
    }

    // The sample values of what the palette builds: the components' default
    // props and every ready-made block's tree.
    function _pcSamples() {
        if (samples) return samples;
        var starters = [];
        var comps = K.COMPONENTS || {};
        Object.keys(comps).forEach(function (k) {
            if (comps[k] && comps[k].defaultProps) starters.push(comps[k].defaultProps);
        });
        (K._sdBlockFamilies() || []).forEach(function (fam) {
            (fam.items || []).forEach(function (it) {
                var ct = it && it.extra && it.extra.componentType;
                if (!ct) return;
                try {
                    var built = fam.build(ct);
                    if (built) starters.push(built);
                } catch (e) { /* a block that cannot be built gives no samples */ }
            });
        });
        samples = C.samples(starters);
        return samples;
    }

    // The page's links as the Prototype view marks them, from one map read:
    // { list: canvasMarks() rows, spots: the page's marked hotspots }, or
    // false when the read failed.
    function _pcLinks(p) {
        var H = window.PGProtoHotspots;
        if (!H || typeof H.canvasMarks !== 'function') return Promise.resolve(false);
        var tabs = K._sdProtoTabs();
        var designed = H.design(tabs.filter(function (t) { return t.key === p.key; }), K.sharedCache);
        var request = H.canvasRequest(designed);
        var done = function (read) {
            var marked = H.mark(designed, read, tabs);
            var page = (marked.pages || [])[0];
            var got = {};
            got.list = H.canvasMarks(marked, true);
            got.spots = page ? (page.hotspots || []) : [];
            return got;
        };
        if (!request.hrefs.length && !request.page_ids.length) return Promise.resolve(done({}));
        return K._pgApiPost('prototype_map', request).then(function (read) {
            return (read && read.status === 'success') ? done(read) : false;
        }, function () { return false; });
    }

    // The search engine and accessibility check on the server's render of
    // a saved page; null for a page not saved yet (not asked).
    function _pcSeo(p, t) {
        if (!(p.page_id > 0)) return Promise.resolve(null);
        return K._pgApiPost('seo_check', K._sdSeoCheckBody(p, t)).then(function (res) {
            return res || false;
        }, function () { return false; });
    }

    function _pcRun(p, t) {
        var audit;
        try { audit = K.validateTree(t); } catch (e) { audit = false; }
        return Promise.all([_pcLinks(p), _pcSeo(p, t)]).then(function (got) {
            var links = got[0];
            var texts = _pcTexts();
            var found = C.regroup(F.read({ audit: audit, seo: got[1], links: links ? links.list : false }, texts));
            try {
                found = found.concat(C.read(t, { samples: _pcSamples(), links: links ? links.spots : [], page: { id: p.page_id || 0, tab: p.key } }, texts));
            } catch (e) { /* the content check adds nothing when it cannot run */ }
            return found;
        });
    }

    // Checks the pages; done(key, summary) for each, the kept answer at once
    // when the page did not change since.
    function check(keys, done) {
        K._pgTabsPersistActive();
        (keys || []).forEach(function (key) {
            var p = K._pgPageByKey(key);
            if (!p) { done(key, { state: 'failed' }); return; }
            var t = K._pgTreeOf(p) || K.createDefaultTree();
            var sig = _pcSig(p, t);
            if (kept[key] && kept[key].sig === sig) {
                var sum = kept[key].sum;
                Promise.resolve().then(function () { done(key, sum); });
                return;
            }
            _pcRun(p, t).then(function (found) {
                kept[key] = { sig: sig, found: found, sum: F.summary(found), saved: p.page_id > 0 };
                done(key, kept[key].sum);
            }, function () {
                delete kept[key];
                done(key, { state: 'failed' });
            });
        });
    }

    // The names from the page's top down to the element: "Main › Hero ›
    // Button". A finding of the page itself has none.
    function _pcPath(f) {
        if (!f.node) return [];
        var p = K._pgPageByKey(f.page);
        var cached = f.shared ? K.sharedCache[f.shared] : null;
        var root = f.shared ? (cached && cached.tree) : (p && K._pgTreeOf(p));
        var chain = null;
        (function walk(n, up) {
            if (!n || chain) return;
            var here = up.concat([n]);
            if (String(n._id) === String(f.node)) { chain = here; return; }
            (n.children || []).forEach(function (c) { walk(c, here); });
        })(root, []);
        if (!chain) return [];
        var names = chain.filter(function (n) { return n.type !== 'root'; }).map(function (n) { return K.getNodeLabel(n); });
        if (f.shared && cached && cached.name) names.unshift(String(cached.name));
        return names;
    }

    function _pcEl(tag, cls, txt) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (txt !== undefined && txt !== null) n.textContent = txt;
        return n;
    }

    function _pcIcon(cls) {
        var n = _pcEl('span', 'bi ' + cls);
        n.setAttribute('aria-hidden', 'true');
        return n;
    }

    // One finding: its mark, its sentence, the element's path as a link.
    function _pcCard(key, f) {
        var block = f.level === 'block';
        var card = _pcEl('div', 'sd-pc-finding' + (block ? ' is-block' : ''));
        card.appendChild(_pcIcon(block ? 'bi-sign-stop-fill' : 'bi-exclamation-triangle-fill'));
        var body = _pcEl('div', 'sd-pc-body');
        var sentence = f.text + ((f.count > 1) ? ' (' + f.count + ')' : '');
        body.appendChild(_pcEl('p', 'sd-pc-text', sentence));
        if (f.detail && f.source !== 'audit') body.appendChild(_pcEl('p', 'sd-pc-detail', f.detail));
        var names = _pcPath({ page: key, node: f.node, shared: f.shared });
        var path = _pcEl('p', 'sd-pc-path');
        if (names.length) {
            var go = _pcEl('button', 'sd-pc-link', names.join(' › '));
            go.type = 'button';
            go.addEventListener('click', function (e) {
                e.preventDefault();
                _pcSelect(key, f, sentence);
            });
            path.appendChild(go);
        } else {
            path.textContent = f.node ? (f.where || '') : _sdT('Page settings');
        }
        if (path.textContent) body.appendChild(path);
        card.appendChild(body);
        return card;
    }

    // The findings of a checked page in the five groups, always listed: a
    // group's count or "Nothing found", "Not checked" with "Try again" for a
    // check that did not run, the render's groups of a page not saved yet
    // with one line saying so.
    function findings(key, retry) {
        var k = kept[key];
        var box = _pcEl('div', 'sd-pc-groups');
        if (!k) return null;
        F.groups().forEach(function (g) {
            var mine = k.found.filter(function (f) { return f.group === g; });
            var shown = mine.filter(function (f) { return f.level !== 'failed'; });
            var failed = mine.length !== shown.length;
            var grp = _pcEl('div', 'sd-pc-group');
            var head = _pcEl('div', 'sd-pc-head');
            head.appendChild(_pcIcon(GROUP_ICON[g]));
            head.appendChild(_pcEl('b', '', _pcGroupName(g)));
            if (shown.length) head.appendChild(_pcEl('small', 'sd-pc-count', String(shown.length)));
            else if (g === 'weight') head.appendChild(_pcEl('small', '', _sdT('Not measured')));
            else if (failed) head.appendChild(_pcEl('small', '', _sdT('Not checked')));
            else head.appendChild(_pcEl('small', '', _sdT('Nothing found')));
            grp.appendChild(head);
            if (failed) {
                var note = _pcEl('div', 'sd-pc-note');
                note.appendChild(_pcEl('span', '', _sdT('This check could not run. It blocks nothing.')));
                var again = _pcEl('button', 'pg-button pg-button-quiet pg-button-sm', _sdT('Try again'));
                again.type = 'button';
                again.addEventListener('click', function (e) {
                    e.preventDefault();
                    delete kept[key];
                    retry();
                });
                note.appendChild(again);
                grp.appendChild(note);
            } else if (!k.saved && RENDERED[g]) {
                grp.appendChild(_pcEl('div', 'sd-pc-note', _sdT('The rest of this check runs once the page is saved.')));
            }
            shown.forEach(function (f) { grp.appendChild(_pcCard(key, f)); });
            box.appendChild(grp);
        });
        return box;
    }

    // A press on a path: the sheet closes, the element is selected, and one
    // line over the canvas repeats the sentence with "Back to Publish". The
    // line goes with Escape or the next selection.
    function _pcSelect(key, f, sentence) {
        window.PgDesignerPublish.close().then(function () {
            if (!K._sdSelectFound(key, f.node, f.shared)) {
                K.sdToast(K.esc(_sdT('The element is no longer on the page.')), 'warning', 3000);
                return;
            }
            _pcLine(key, sentence);
        });
    }

    function _pcLineOff() {
        if (!line) return;
        var l = line;
        line = null;
        if (l.el.parentNode) l.el.parentNode.removeChild(l.el);
        l.docs.forEach(function (d) {
            d.removeEventListener('pointerdown', l.away, true);
            d.removeEventListener('keydown', l.key, true);
        });
    }

    function _pcLine(key, sentence) {
        _pcLineOff();
        var el = _pcEl('div', 'sd-pc-line');
        el.setAttribute('role', 'status');
        el.appendChild(_pcIcon('bi-exclamation-triangle-fill'));
        el.appendChild(_pcEl('span', 'sd-pc-line-text', sentence));
        var back = _pcEl('button', 'pg-button pg-button-quiet pg-button-sm', _sdT('Back to Publish'));
        back.type = 'button';
        back.addEventListener('click', function (e) {
            e.preventDefault();
            _pcLineOff();
            K._pgPublishSheet({ reopen: key });
        });
        el.appendChild(back);
        document.body.appendChild(el);
        var docs = [document];
        if (K.canvasDoc && K.canvasDoc !== document) docs.push(K.canvasDoc);
        var l = {
            el: el,
            docs: docs,
            away: function (e) { if (!el.contains(e.target)) _pcLineOff(); },
            key: function (e) { if (e.key === 'Escape') _pcLineOff(); }
        };
        line = l;
        // Listened for from the next turn: the press that drew the line is
        // not the next selection.
        setTimeout(function () {
            if (line !== l) return;
            docs.forEach(function (d) {
                d.addEventListener('pointerdown', l.away, true);
                d.addEventListener('keydown', l.key, true);
            });
        }, 0);
    }

    return { check: check, findings: findings };
});
