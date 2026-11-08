/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Publish sheet. Pressing Publish lists every page of
 * the design with its state (a draft, on the site, waiting for its publish
 * time, a variant an open experiment holds) and whether it changed since
 * the last save. The drafts and the changed pages come checked; "Publish
 * selected" puts the checked drafts on the site and writes the design in
 * one save, holding back the unchecked pages on the site that changed (they
 * stay unsaved in the editor). The two "take off the site" actions of the
 * old publishing menu sit at the sheet's foot.
 *
 * The editor (style_designer.js) owns the pages and the save; it opens the
 * sheet with a bridge:
 *   rows()                 the pages, see defaults() for their shape
 *   publish(keys, hold, report)
 *                          keys: drafts to put on the site; hold: pages on
 *                          the site to leave out of the save; report(reply)
 *                          once the save is over: the server's reply, or
 *                          null when it did not run (it returns whether the
 *                          sheet showed the result, see outcome())
 *   takeOff(keys)          keep these pages off the site (asks first)
 * The markup (#sdPublishSheet) and its texts (data-sdp-text, from lang())
 * are printed by includes/designer_screen.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
var PgDesignerPublish = (function () {
    'use strict';

    // A row the sheet can check: not locked to this user, not a variant an
    // experiment keeps a draft.
    function selectable(r) {
        return !!r && !r.locked && r.state !== 'held';
    }

    // The keys checked when the sheet opens: every draft (a page waiting
    // for its publish time keeps waiting unless checked by hand) and every
    // page on the site that changed. A row is
    // { key, name, state: 'draft'|'scheduled'|'live'|'held', changed,
    //   locked, home, active }.
    function defaults(rows) {
        return (rows || []).filter(function (r) {
            return selectable(r) && (r.state === 'draft' || (r.state === 'live' && r.changed));
        }).map(function (r) { return r.key; });
    }

    // What "Publish selected" does with the checked keys: `publish` the
    // drafts to put on the site, `hold` the changed pages on the site left
    // out of the save, `save` whether there is anything to do. An unchecked
    // draft is saved as it is, off the site.
    function plan(rows, keys) {
        var on = {};
        (keys || []).forEach(function (k) { on[k] = true; });
        var out = { publish: [], hold: [], save: false };
        (rows || []).forEach(function (r) {
            if (!selectable(r)) return;
            if (r.state === 'draft' || r.state === 'scheduled') {
                if (on[r.key]) { out.publish.push(r.key); out.save = true; }
            } else if (r.state === 'live' && r.changed) {
                if (on[r.key]) out.save = true;
                else out.hold.push(r.key);
            } else if (on[r.key]) {
                out.save = true;
            }
        });
        return out;
    }

    // Where the save left each page, from the reply's `pages` ({ key,
    // draft }): `live` on the site, `draft` off it, `kept` not saved - held
    // back by the sheet, or held by another user (no state in the reply).
    // Names in the rows' order; a page the reply does not name is left out.
    function outcome(rows, pages, hold) {
        var got = {};
        (Array.isArray(pages) ? pages : []).forEach(function (m) { if (m && m.key) got[m.key] = m; });
        var held = {};
        (hold || []).forEach(function (k) { held[k] = true; });
        var out = { live: [], draft: [], kept: [] };
        (rows || []).forEach(function (r) {
            var m = got[r.key];
            if (!m) return;
            if (held[r.key] || typeof m.draft === 'undefined') out.kept.push(r.name);
            else out[m.draft ? 'draft' : 'live'].push(r.name);
        });
        return out;
    }

    var bridge = null;
    var current = [];   // the rows read when the sheet opened
    var checked = {};
    var saving = false;

    function el() { return document.getElementById('sdPublishSheet'); }

    function text(key) {
        var root = el();
        var t = root ? root.querySelector('[data-sdp-text="' + key + '"]') : null;
        return t ? t.textContent : key;
    }

    function rowEl(r) {
        var item = document.createElement('label');
        item.className = 'list-group-item bg-transparent d-flex align-items-center gap-3 sd-publish-row';
        item.setAttribute('data-key', r.key);
        var box = document.createElement('input');
        box.type = 'checkbox';
        box.className = 'check m-0 flex-shrink-0';
        box.checked = !!checked[r.key];
        box.disabled = !selectable(r);
        box.addEventListener('change', function () {
            if (box.checked) checked[r.key] = true; else delete checked[r.key];
            sync();
        });
        var name = document.createElement('span');
        name.className = 'flex-grow-1 text-truncate';
        name.textContent = r.name;
        if (r.active) name.classList.add('fw-semibold');
        var state = document.createElement('span');
        state.className = 'badge sd-publish-state sd-publish-state-' + r.state;
        state.textContent = text('state-' + r.state);
        item.appendChild(box);
        item.appendChild(name);
        if (r.changed) {
            var ch = document.createElement('span');
            ch.className = 'small fg-warning text-nowrap';
            ch.textContent = text('changed');
            item.appendChild(ch);
        }
        if (r.locked) {
            var lk = document.createElement('span');
            lk.className = 'small fg-secondary text-nowrap';
            lk.textContent = text('locked');
            item.appendChild(lk);
        }
        item.appendChild(state);
        return item;
    }

    function sync() {
        var root = el();
        if (!root || !bridge) return;
        var rows = current;
        var p = plan(rows, Object.keys(checked));
        var go = root.querySelector('[data-sdp-act="publish"]');
        if (go) go.disabled = !p.save;
        var note = root.querySelector('[data-sdp-hold-note]');
        if (note) note.classList.toggle('d-none', !p.hold.length);
        var active = null;
        rows.forEach(function (r) { if (r.active) active = r; });
        var showOff = function (act, on) {
            var b = root.querySelector('[data-sdp-act="' + act + '"]');
            if (b) b.classList.toggle('d-none', !on);
        };
        showOff('page-draft', !!active && active.state === 'live' && !active.home && !active.locked);
        showOff('all-draft', rows.some(function (r) { return r.state === 'live' && !r.home && !r.locked; }));
    }

    function draw() {
        var root = el();
        var list = root ? root.querySelector('[data-sdp-list]') : null;
        if (!list) return;
        list.textContent = '';
        current.forEach(function (r) { list.appendChild(rowEl(r)); });
        sync();
    }

    // The pick view (rows, Select all, the foot actions) or the result view
    // (where the pages went, Done).
    function view(result) {
        var root = el();
        if (!root) return;
        var q = function (sel) { return root.querySelector(sel); };
        ['[data-sdp-pick]', '[data-sdp-list]'].forEach(function (sel) { var n = q(sel); if (n) n.classList.toggle('d-none', result); });
        var res = q('[data-sdp-result]');
        if (res) res.classList.toggle('d-none', !result);
        ['publish', 'page-draft', 'all-draft'].forEach(function (act) {
            var b = q('[data-sdp-act="' + act + '"]');
            if (b && result) b.classList.add('d-none');
        });
        var go = q('[data-sdp-act="publish"]');
        if (go && !result) go.classList.remove('d-none');
        var done = q('[data-sdp-act="done"]');
        if (done) done.classList.toggle('d-none', !result);
        if (result) { var n = q('[data-sdp-hold-note]'); if (n) n.classList.add('d-none'); }
    }

    // The save in flight: the Publish button turns into a progress label,
    // the rows and the other actions wait.
    function busy(on) {
        var root = el();
        if (!root) return;
        saving = on;
        var go = root.querySelector('[data-sdp-act="publish"]');
        if (go) {
            if (on) {
                go._sdpLabel = go.innerHTML;
                go.textContent = '';
                var sp = document.createElement('span');
                sp.className = 'spinner-border spinner-border-sm me-2';
                sp.setAttribute('aria-hidden', 'true');
                go.appendChild(sp);
                go.appendChild(document.createTextNode(text('busy')));
            } else if (go._sdpLabel) {
                go.innerHTML = go._sdpLabel;
                delete go._sdpLabel;
            }
            go.setAttribute('aria-busy', on ? 'true' : 'false');
        }
        root.querySelectorAll('[data-sdp-act], [data-sdp-list] input').forEach(function (n) {
            if (on) n.disabled = true;
        });
        if (!on) {
            root.querySelectorAll('[data-sdp-act], [data-sdp-list] input').forEach(function (n) { n.disabled = false; });
            draw();
        }
    }

    function drawResult(out) {
        var res = el() ? el().querySelector('[data-sdp-result]') : null;
        if (!res) return;
        res.textContent = '';
        ['live', 'draft', 'kept'].forEach(function (k) {
            if (!out[k].length) return;
            var head = document.createElement('div');
            head.className = 'small fw-semibold px-5 pt-4 pb-2 sd-publish-result-' + k;
            head.textContent = text('result-' + k);
            res.appendChild(head);
            var list = document.createElement('ul');
            list.className = 'list-group list-group-flush';
            out[k].forEach(function (name) {
                var li = document.createElement('li');
                li.className = 'list-group-item bg-transparent text-truncate';
                li.textContent = name;
                list.appendChild(li);
            });
            res.appendChild(list);
        });
    }

    // Closes the sheet, then runs fn: a confirmation it opens must not sit
    // under a dialog still closing.
    function closeThen(fn) {
        var root = el();
        if (root && window.pgUI && root.open) pgUI.dialog.hide(root).then(fn);
        else fn();
    }

    function bind(root) {
        if (root._sdpBound) return;
        root._sdpBound = true;
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sdp-act]');
            if (!b || b.disabled || !bridge) return;
            var act = b.getAttribute('data-sdp-act');
            var rows = current;
            if (act === 'all') {
                var all = rows.filter(selectable).map(function (r) { return r.key; });
                var every = all.every(function (k) { return checked[k]; });
                checked = {};
                if (!every) all.forEach(function (k) { checked[k] = true; });
                draw();
            } else if (act === 'publish') {
                var p = plan(rows, Object.keys(checked));
                if (!p.save || saving) return;
                // The sheet stays open: the button shows the save, then the
                // sheet lists where the pages went. A save that did not run
                // (its reason is shown by the editor) gives the pick back.
                var report = function (reply) {
                    var root = el();
                    if (!saving) return false;
                    busy(false);
                    if (!reply) return false;
                    drawResult(outcome(rows, reply.pages, p.hold));
                    view(true);
                    return !!(root && root.open);
                };
                busy(true);
                bridge.publish(p.publish, p.hold, report);
            } else if (act === 'done') {
                closeThen(function () {});
            } else if (act === 'page-draft') {
                var a = rows.filter(function (r) { return r.active; });
                closeThen(function () { bridge.takeOff(a.map(function (r) { return r.key; })); });
            } else if (act === 'all-draft') {
                closeThen(function () { bridge.takeOff(rows.map(function (r) { return r.key; })); });
            }
        });
    }

    function open(b) {
        var root = el();
        if (!root || !b) return false;
        if (saving) { if (window.pgUI) pgUI.dialog.show(root); return true; }
        bridge = b;
        view(false);
        current = bridge.rows() || [];
        checked = {};
        defaults(current).forEach(function (k) { checked[k] = true; });
        bind(root);
        draw();
        if (window.pgUI) pgUI.dialog.show(root);
        return true;
    }

    return { defaults: defaults, plan: plan, outcome: outcome, open: open };
})();
