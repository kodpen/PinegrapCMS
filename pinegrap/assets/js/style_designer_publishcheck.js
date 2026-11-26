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
 * over the canvas that leads back. A finding has one button: "Fix" (one
 * undo step of the page, with "Undo" in its place) or "Show me"; one to
 * look at may be ignored on its page with a reason, kept on the page tree's
 * root so the page's draft carries it. The Weight group reads the measure
 * seo_check takes on the same render. Not needed to open a page: the core runs
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
    var RENDERED = { a11y: true, seo: true, weight: true };
    // The weight's four measures in the order of their bars.
    var MEASURES = ['markup', 'styles', 'scripts', 'images'];
    // Weight findings drawn as a bar or a list, not as a card.
    var NO_CARD = { weight_markup: true, weight_styles: true, weight_scripts: true, weight_images: true, weight_total: true, css_unused: true };

    // key => { sig, found, sum, saved, weight, fixed: id => { text, undo } }
    var kept = {};
    var asking = null;   // { key, id, text } while an Ignore reason is typed
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
            link_self: _sdT('The link opens the page it is on.'),
            weight_failed: _sdT('The page weight could not be measured.')
        };
    }

    // What a page's check depends on: its tree and the settings the
    // search engine check reads. The same signature keeps the findings.
    function _pcSig(p, t) {
        // An Ignore writes on the tree's root but changes nothing checked.
        return JSON.stringify([t, p.page_id || 0, p.page_name || '', p.page_title || '', p.page_meta_description || '',
            p.page_search_keywords || '', p.page_search ? 1 : 0, p.page_sitemap ? 1 : 0, p.page_noindex ? 1 : 0],
        function (k, v) { return k === '_pcIgnored' ? undefined : v; });
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
        var body = K._sdSeoCheckBody(p, t);
        body.weight = 1;
        return K._pgApiPost('seo_check', body).then(function (res) {
            return res || false;
        }, function () { return false; });
    }

    function _pcRun(p, t) {
        var audit;
        try { audit = K.validateTree(t); } catch (e) { audit = false; }
        return Promise.all([_pcLinks(p), _pcSeo(p, t)]).then(function (got) {
            var links = got[0];
            var seo = got[1];
            var texts = _pcTexts();
            // The weight: null when not measured (a page not saved yet, an
            // older server), false when the draft did not render.
            var weight = (seo && seo.status === 'success' && seo.weight !== undefined) ? seo.weight : null;
            var found = C.regroup(F.read({ audit: audit, seo: seo, links: links ? links.list : false,
                weight: weight ? weight.findings : weight }, texts));
            try {
                found = found.concat(C.read(t, { samples: _pcSamples(), links: links ? links.spots : [], page: { id: p.page_id || 0, tab: p.key } }, texts));
            } catch (e) { /* the content check adds nothing when it cannot run */ }
            return { found: found, weight: weight || null };
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
                var sum = state(key).sum;
                Promise.resolve().then(function () { done(key, sum); });
                return;
            }
            _pcRun(p, t).then(function (got) {
                kept[key] = { sig: sig, found: got.found, weight: got.weight, fixed: {}, saved: p.page_id > 0 };
                done(key, state(key).sum);
            }, function () {
                delete kept[key];
                done(key, { state: 'failed' });
            });
        });
    }

    // A checked page's findings split into the counted, the ignored (with
    // their reasons, read from the page's tree) and the fixed ones, and the
    // summary of the counted ones.
    function state(key) {
        var k = kept[key];
        var p = K._pgPageByKey(key);
        if (!k || !p) return { shown: [], ignored: [], fixed: [], sum: { state: 'failed' } };
        var s = F.split(k.found, F.ignored(K._pgTreeOf(p)), k.fixed);
        s.sum = F.summary(s.shown);
        return s;
    }

    function _pcFind(key, id) {
        var k = kept[key];
        var hit = null;
        (k ? k.found : []).forEach(function (f) { if (f.id === id) hit = f; });
        return hit;
    }

    // The node and its parent in a tree, by id.
    function _pcNode(t, id) {
        var got = null;
        (function walk(n, up) {
            if (!n || got) return;
            if (String(n._id) === String(id)) { got = { node: n, parent: up }; return; }
            (n.children || []).forEach(function (c) { walk(c, n); });
        })(t, null);
        return got;
    }

    // What "Fix" does to a finding: { run(tree), undo(tree), text }, or null
    // when it needs a choice (then the finding has "Show me"). Never inside
    // a shared component: its tree belongs to every page that places it.
    function _pcFixer(key, f) {
        var k = kept[key];
        var p = K._pgPageByKey(key);
        if (!k || !p || f.shared || F.action(f, k.weight && k.weight.sizes) !== 'fix') return null;
        var at = _pcNode(K._pgTreeOf(p), f.node);
        if (!at) return null;
        if (f.source === 'audit' && f.code === 'heading_empty' && at.parent) {
            var parentId = at.parent._id;
            var copy = JSON.stringify(at.node);
            var index = at.parent.children.indexOf(at.node);
            return {
                text: _sdT('Fixed: the empty heading was removed.'),
                run: function (t) {
                    var here = _pcNode(t, f.node);
                    if (here && here.parent) here.parent.children.splice(here.parent.children.indexOf(here.node), 1);
                },
                undo: function (t) {
                    var up = _pcNode(t, parentId);
                    if (up && !_pcNode(t, f.node)) up.node.children.splice(Math.min(index, up.node.children.length), 0, JSON.parse(copy));
                }
            };
        }
        if (f.source === 'weight' && f.code === 'img_no_size') {
            var pr = at.node.props || {};
            var size = k.weight.sizes[String(f.node)];
            // Only an image element whose width and height are both empty:
            // a size the person typed (a unit, a percentage) is a choice.
            if (at.node.type !== 'component' || pr.componentType !== 'image' || String(pr.width || '') !== '' || String(pr.height || '') !== '') return null;
            return {
                text: _sdT('Fixed: the picture got its own width and height.'),
                run: function (t) {
                    var here = _pcNode(t, f.node);
                    if (!here) return;
                    here.node.props = here.node.props || {};
                    here.node.props.width = String(size[0]);
                    here.node.props.height = String(size[1]);
                },
                undo: function (t) {
                    var here = _pcNode(t, f.node);
                    if (!here) return;
                    here.node.props.width = '';
                    here.node.props.height = '';
                }
            };
        }
        return null;
    }

    // After a change made inside the sheet: the kept answer follows the
    // tree (no new check) and the sheet redraws the row.
    function _pcChanged(key, focus) {
        var k = kept[key];
        var p = K._pgPageByKey(key);
        if (k && p) k.sig = _pcSig(p, K._pgTreeOf(p));
        if (window.PgDesignerPublish && typeof window.PgDesignerPublish.update === 'function') {
            window.PgDesignerPublish.update(key, state(key).sum, focus || '');
        }
    }

    // "Fix": one undo step of the page; the card turns into its result line.
    function fix(key, id) {
        var f = _pcFind(key, id);
        var fx = f ? _pcFixer(key, f) : null;
        if (!fx || kept[key].fixed[id]) return false;
        if (!K._sdPageEdit(key, fx.run)) return false;
        kept[key].fixed[id] = { text: fx.text, undo: fx.undo };
        _pcChanged(key, '[data-pc-undo="' + _pcAttr(id) + '"]');
        return true;
    }

    // "Undo" on a result line: the fix taken back, as another undo step.
    function undo(key, id) {
        var k = kept[key];
        var done = k && k.fixed[id];
        if (!done || !K._sdPageEdit(key, done.undo)) return false;
        delete k.fixed[id];
        _pcChanged(key, '[data-pc-act="' + _pcAttr(id) + '"]');
        return true;
    }

    // "Ignore": the reason kept on the page tree's root (an edit of the
    // page, saved by Publish or by saving the page as a draft).
    function ignore(key, id, reason) {
        var f = _pcFind(key, id);
        var wrote = false;
        if (!f || !F.canIgnore(f) || String(reason || '').trim() === '') return false;
        if (!K._sdPageEdit(key, function (t) { wrote = F.ignore(t, id, reason); })) return false;
        if (wrote) _pcChanged(key);
        return wrote;
    }

    // "Stop ignoring".
    function unignore(key, id) {
        var p = K._pgPageByKey(key);
        if (!p || !Object.prototype.hasOwnProperty.call(F.ignored(K._pgTreeOf(p)), id)) return false;
        if (!K._sdPageEdit(key, function (t) { F.ignore(t, id, ''); })) return false;
        _pcChanged(key);
        return true;
    }

    function _pcAttr(v) {
        return String(v).replace(/["\\]/g, '\\$&');
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

    function _pcButton(label, attr, id, fn) {
        var b = _pcEl('button', 'pg-button pg-button-quiet pg-button-sm', label);
        b.type = 'button';
        if (attr) b.setAttribute(attr, id);
        b.addEventListener('click', function (e) { e.preventDefault(); fn(); });
        return b;
    }

    // The element's path from the page's top as a link, or "Page settings".
    function _pcPathEl(key, f, sentence) {
        var names = _pcPath({ page: key, node: f.node, shared: f.shared });
        var path = _pcEl('p', 'sd-pc-path');
        if (names.length) {
            var go = _pcEl('button', 'sd-pc-link', names.join(' › '));
            go.type = 'button';
            go.addEventListener('click', function (e) {
                e.preventDefault();
                _pcSelect(key, f, sentence, false);
            });
            path.appendChild(go);
        } else {
            path.appendChild(document.createTextNode(f.node ? (f.where || '') : _sdT('Page settings')));
        }
        return path;
    }

    // One finding: its mark, its sentence, the element's path, "Ignore on
    // this page" for one to look at, and one button at the end: "Fix" or
    // "Show me". While its reason is asked, the field sits inside the card.
    function _pcCard(key, f, redraw) {
        var block = f.level === 'block';
        var card = _pcEl('div', 'sd-pc-finding' + (block ? ' is-block' : ''));
        card.appendChild(_pcIcon(block ? 'bi-sign-stop-fill' : 'bi-exclamation-triangle-fill'));
        var body = _pcEl('div', 'sd-pc-body');
        var sentence = f.text + ((f.count > 1) ? ' (' + f.count + ')' : '');
        body.appendChild(_pcEl('p', 'sd-pc-text', sentence));
        if (f.detail && f.source !== 'audit') body.appendChild(_pcEl('p', 'sd-pc-detail', f.detail));
        var path = _pcPathEl(key, f, sentence);
        var mine = asking && asking.key === key && asking.id === f.id;
        if (F.canIgnore(f) && !mine) {
            var ig = _pcEl('button', 'sd-pc-link sd-pc-ignore', _sdT('Ignore on this page'));
            ig.type = 'button';
            ig.setAttribute('data-pc-ignore', f.id);
            ig.addEventListener('click', function (e) {
                e.preventDefault();
                asking = { key: key, id: f.id, text: '' };
                redraw('[data-pc-reason]');
            });
            path.appendChild(ig);
        }
        if (path.textContent) body.appendChild(path);
        if (mine) body.appendChild(_pcAsk(key, f, redraw));
        card.appendChild(body);
        var fx = _pcFixer(key, f);
        var act = fx ? _pcButton(_sdT('Fix'), 'data-pc-act', f.id, function () { fix(key, f.id); })
            : _pcButton(_sdT('Show me'), 'data-pc-act', f.id, function () { _pcSelect(key, f, sentence, true); });
        act.classList.add('sd-pc-act');
        card.appendChild(act);
        return card;
    }

    // The reason asked inside a card: required, 500 characters with a
    // counter; Escape closes only the field, Enter ignores.
    function _pcAsk(key, f, redraw) {
        var wrap = _pcEl('div', 'sd-pc-ask');
        var fid = 'sd-pc-reason-' + String(f.id).replace(/[^A-Za-z0-9_-]/g, '_');
        var lab = _pcEl('label', 'sd-pc-ask-label', _sdT('Why is it ignored on this page? The reason is kept with the page.'));
        lab.setAttribute('for', fid);
        var input = _pcEl('input', 'form-control form-control-sm');
        input.type = 'text';
        input.id = fid;
        input.maxLength = 500;
        input.required = true;
        input.value = asking.text || '';
        input.setAttribute('data-pc-reason', f.id);
        var count = _pcEl('small', 'sd-pc-ask-count', input.value.length + ' / 500');
        var row = _pcEl('div', 'sd-pc-ask-row');
        var go = _pcButton(_sdT('Ignore'), '', '', function () {
            // Closed before the redraw the Ignore makes; kept when it failed.
            var was = asking;
            asking = null;
            if (!ignore(key, f.id, input.value)) asking = was;
        });
        var cancel = _pcButton(_sdT('Cancel'), '', '', function () {
            asking = null;
            redraw('[data-pc-ignore="' + _pcAttr(f.id) + '"]');
        });
        go.disabled = input.value.trim() === '';
        input.addEventListener('input', function () {
            asking.text = input.value;
            count.textContent = input.value.length + ' / 500';
            go.disabled = input.value.trim() === '';
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); cancel.click(); }
            if (e.key === 'Enter') { e.preventDefault(); if (!go.disabled) go.click(); }
        });
        row.appendChild(count);
        row.appendChild(go);
        row.appendChild(cancel);
        wrap.appendChild(lab);
        wrap.appendChild(input);
        wrap.appendChild(row);
        return wrap;
    }

    // A fixed finding's result line in its card's place, with "Undo".
    function _pcFixedEl(key, f) {
        var line = _pcEl('div', 'sd-pc-fixed');
        line.setAttribute('role', 'status');
        line.appendChild(_pcIcon('bi-check-circle-fill'));
        line.appendChild(_pcEl('span', 'sd-pc-fixed-text', kept[key].fixed[f.id].text));
        line.appendChild(_pcButton(_sdT('Undo'), 'data-pc-undo', f.id, function () { undo(key, f.id); }));
        return line;
    }

    // The ignored findings of a group under a fold, dimmed, each with its
    // reason and "Stop ignoring".
    function _pcIgnoredEl(key, list) {
        var fold = _pcEl('details', 'sd-pc-ignored');
        fold.appendChild(_pcEl('summary', '', _sdT('Ignored on this page ({var})', String(list.length))));
        list.forEach(function (f) {
            var card = _pcEl('div', 'sd-pc-finding is-ignored');
            card.appendChild(_pcIcon('bi-eye-slash'));
            var body = _pcEl('div', 'sd-pc-body');
            body.appendChild(_pcEl('p', 'sd-pc-text', f.text));
            var path = _pcPathEl(key, f, f.text);
            if (path.textContent) body.appendChild(path);
            body.appendChild(_pcEl('p', 'sd-pc-detail', _sdT('Ignored: “{var}”', f.reason)));
            card.appendChild(body);
            var stop = _pcButton(_sdT('Stop ignoring'), 'data-pc-stop', f.id, function () { unignore(key, f.id); });
            stop.classList.add('sd-pc-act');
            card.appendChild(stop);
            fold.appendChild(card);
        });
        return fold;
    }

    function _pcKb(n) {
        n = Math.max(0, Number(n) || 0);
        if (n >= 1024 * 1024) return (Math.round(n / 1024 / 1024 * 10) / 10) + ' MB';
        return Math.round(n / 1024) + ' KB';
    }

    // The weight's four measures as bars against the budget: the mark at
    // two thirds is the budget; green under it, amber up to twice, red above
    // (pg_publish_weight_grade()).
    function _pcBars(w) {
        var names = { markup: _sdT('Markup'), styles: _sdT('Styles'), scripts: _sdT('Scripts'), images: _sdT('Pictures of the first screen') };
        var card = _pcEl('div', 'sd-pc-bars');
        MEASURES.forEach(function (m) {
            var bytes = (w.bytes && w.bytes[m]) || 0;
            var budget = (w.budget && w.budget[m]) || 1;
            var grade = (w.grades && w.grades.measures && w.grades.measures[m]) || 'green';
            var of = _sdT('{var:1} of {var:2}', [_pcKb(bytes), _pcKb(budget)]);
            var row = _pcEl('div', 'sd-pc-bar-row');
            row.setAttribute('aria-label', names[m] + ': ' + of + (grade === 'green' ? '' : ', ' + _sdT('over the budget')));
            row.setAttribute('role', 'img');
            row.appendChild(_pcEl('span', 'sd-pc-bar-name', names[m]));
            var bar = _pcEl('span', 'sd-pc-bar is-' + grade);
            var fill = _pcEl('span', 'sd-pc-bar-fill');
            fill.style.width = Math.min(100, Math.round(bytes / budget * 100 / 1.5)) + '%';
            bar.appendChild(fill);
            row.appendChild(bar);
            row.appendChild(_pcEl('span', 'sd-pc-bar-of', of));
            card.appendChild(row);
        });
        card.appendChild(_pcEl('p', 'sd-pc-bar-note', _sdT('The mark is the budget: green under it, amber up to twice the budget, red above.')));
        return card;
    }

    // The rules of the design's own stylesheet no page uses: one fold, the
    // same on every page's row, listed only.
    function _pcUnusedEl(w) {
        var rules = (w && w.unused) || [];
        if (!rules.length) return null;
        var fold = _pcEl('details', 'sd-pc-unused');
        fold.appendChild(_pcEl('summary', '', _sdT('Rules no page of the design uses') + ' · ' + _sdT('{var} rules', String(rules.length))));
        rules.forEach(function (r) {
            var line = _pcEl('div', 'sd-pc-unused-rule');
            line.appendChild(_pcEl('code', '', r.selector));
            if (r.media) line.appendChild(_pcEl('small', '', r.media));
            fold.appendChild(line);
        });
        var end = _pcEl('div', 'sd-pc-unused-end');
        end.appendChild(_pcEl('p', 'sd-pc-bar-note', _sdT('Listed for you to look at; nothing is removed by itself.')));
        // "Clean up" opens its window over the sheet with the rules open;
        // closing it gives the sheet back.
        if (K._sdPartUrls('cleanup')) {
            var clean = _pcEl('button', 'pg-button pg-button-quiet pg-button-sm');
            clean.type = 'button';
            clean.innerHTML = '<span class="bi bi-stars me-1" aria-hidden="true"></span>' + K.esc(_sdT('Clean up'));
            clean.addEventListener('click', function () { K._sdCleanupOpen('publishcheck'); });
            end.appendChild(clean);
        }
        fold.appendChild(end);
        return fold;
    }

    // The findings of a checked page in the five groups, always listed: a
    // group's count or "Nothing found", "Not checked" with "Try again" for a
    // check that did not run, the render's groups of a page not saved yet
    // with one line saying so. A fixed finding is its result line; ignored
    // ones sit under a fold at the end of their group.
    function findings(key, retry) {
        var k = kept[key];
        if (!k) return null;
        var box = _pcEl('div', 'sd-pc-groups');
        var st = state(key);
        var redraw = function (focus) { window.PgDesignerPublish.update(key, state(key).sum, focus || ''); };
        F.groups().forEach(function (g) {
            var inG = function (f) { return f.group === g; };
            var mine = st.shown.filter(inG);
            var shown = mine.filter(function (f) { return f.level !== 'failed'; });
            var failed = mine.length !== shown.length;
            var w = (g === 'weight') ? k.weight : null;
            var grp = _pcEl('div', 'sd-pc-group');
            var head = _pcEl('div', 'sd-pc-head');
            head.appendChild(_pcIcon(GROUP_ICON[g]));
            head.appendChild(_pcEl('b', '', _pcGroupName(g)));
            if (shown.length) head.appendChild(_pcEl('small', 'sd-pc-count', String(shown.length)));
            else if (failed) head.appendChild(_pcEl('small', '', _sdT('Not checked')));
            else if (g === 'weight' && !w) head.appendChild(_pcEl('small', '', _sdT('Not measured')));
            else head.appendChild(_pcEl('small', '', _sdT('Nothing found')));
            if (w && w.bytes) head.appendChild(_pcEl('small', 'sd-pc-total', _sdT('{var} in all', _pcKb(w.bytes.total))));
            grp.appendChild(head);
            if (failed) {
                var note = _pcEl('div', 'sd-pc-note');
                note.appendChild(_pcEl('span', '', _sdT('This check could not run. It blocks nothing.')));
                note.appendChild(_pcButton(_sdT('Try again'), '', '', function () {
                    delete kept[key];
                    retry();
                }));
                grp.appendChild(note);
            } else if (!k.saved && RENDERED[g]) {
                grp.appendChild(_pcEl('div', 'sd-pc-note', _sdT('The rest of this check runs once the page is saved.')));
            }
            if (w && w.bytes) grp.appendChild(_pcBars(w));
            shown.forEach(function (f) { if (!(f.source === 'weight' && NO_CARD[f.code])) grp.appendChild(_pcCard(key, f, redraw)); });
            st.fixed.filter(inG).forEach(function (f) { grp.appendChild(_pcFixedEl(key, f)); });
            var unused = w ? _pcUnusedEl(w) : null;
            if (unused) grp.appendChild(unused);
            var ign = st.ignored.filter(inG);
            if (ign.length) grp.appendChild(_pcIgnoredEl(key, ign));
            box.appendChild(grp);
        });
        return box;
    }

    // A press on a path: the sheet closes, the element is selected, and one
    // line over the canvas repeats the sentence with "Back to Publish". The
    // line goes with Escape or the next selection.
    // "Show me" also opens the place where the change is made: the
    // element's options come with its selection; a finding of the page
    // opens the page's settings.
    function _pcSelect(key, f, sentence, show) {
        window.PgDesignerPublish.close().then(function () {
            if (show && !f.node) {
                if (key !== K.activeKey) K._pgTabsSwitch(key);
                K._pgTabsOpenPageSettings();
                _pcLine(key, sentence);
                return;
            }
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

    return { check: check, findings: findings, state: state, fix: fix, undo: undo, ignore: ignore, unignore: unignore };
});
