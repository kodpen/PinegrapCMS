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
 *   publish(keys, hold, report, reasons)
 *                          keys: drafts to put on the site; hold: pages on
 *                          the site to leave out of the save; reasons: key =>
 *                          why an administrator publishes a draft waiting for
 *                          a prototype link's approval anyway; report(reply)
 *                          once the save is over: the server's reply, or
 *                          null when it did not run (it returns whether the
 *                          sheet showed the result, see outcome())
 *   takeOff(keys)          keep these pages off the site (asks first)
 *   check(keys, done)      optional, the Publish check: checks these pages
 *                          and calls done(key, summary) for each, the
 *                          summary of PgPublishFindings.summary(), or
 *                          { state: 'failed' } when the check did not run
 *   findings(key, retry)   the element listing a checked page's findings,
 *                          drawn under its row; retry() checks it again
 *   version                optional, a live page's new version:
 *                          label(key) the foot button's text, run(key) its
 *                          press, publish(key, replace, done) publishes a
 *                          version's draft after the save and calls
 *                          done(answer: ok, changed, error, or null),
 *                          texts the words of the "Replace anyway" ask
 * update(key, summary) redraws a page's result after a change made inside
 * the sheet ("Fix", "Undo", "Ignore").
 * A version's row (state 'version', `replaces` the live page's name) is
 * published by its bundle once the save is over; a live page changed since
 * the version was started is asked in the row's place: "Replace anyway"
 * or "Cancel".
 * With check(), a ticked page gets its result at the end of its row; a
 * blocking page goes on the site only with "Publish anyway" and a reason
 * (publish()'s fifth argument: key => reason), as a held row does.
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

    function has(map, key) {
        return !!map && Object.prototype.hasOwnProperty.call(map, key);
    }

    // A row the sheet can check: not locked to this user, not held (an
    // experiment's variant, a bundle's draft, a draft waiting for approval)
    // unless an administrator chose "Publish anyway" for it, and not
    // blocked by the Publish check unless "Publish anyway" was chosen for
    // it (`anyway`: key => the reason typed so far; `blocked`: key => true).
    function selectable(r, anyway, blocked) {
        if (!r || r.locked) return false;
        if (has(blocked, r.key) && !has(anyway, r.key)) return false;
        return r.state !== 'held' || !!(r.override && has(anyway, r.key));
    }

    // The keys checked when the sheet opens: every draft (a page waiting
    // for its publish time keeps waiting unless checked by hand), every
    // page on the site that changed, and a new version only on its own tab
    // (it replaces a page on the site). A row is
    // { key, name, state: 'draft'|'scheduled'|'live'|'held'|'version', label, note, changed,
    //   compare, locked, home, active, override, replaces }; `compare`: a page on the
    //   site that changed, offered "Compare with the live page"; `override`: a held draft this
    //   person may publish anyway with a reason; `replaces`, `replacesKey`: a version's live
    //   page name and its tab's key.
    function defaults(rows, check) {
        var blocked = (check && check.blocked) || null;
        return (rows || []).filter(function (r) {
            return selectable(r, null, blocked)
                && (r.state === 'draft' || (r.state === 'live' && r.changed) || (r.state === 'version' && r.active));
        }).map(function (r) { return r.key; });
    }

    // What "Publish selected" does with the checked keys: `publish` the
    // drafts to put on the site, `hold` the changed pages on the site left
    // out of the save, `versions` the new versions published by their
    // bundles after the save (saved as drafts in it), `save` whether there
    // is anything to do. An unchecked
    // draft is saved as it is, off the site. A held draft checked with
    // "Publish anyway" is published with its reason (`reasons`); without
    // one, `missing` keeps the button off.
    // `check` is the Publish check's state: `pending` the ticked pages still
    // being checked (`waiting` keeps the button off), `blocked` the pages
    // with a blocking finding: one goes only with "Publish anyway" and its
    // reason (`checks`: key => reason); left out, a page on the site that
    // changed is held out of the save and a draft stays a draft.
    function plan(rows, keys, anyway, check) {
        var on = {};
        (keys || []).forEach(function (k) { on[k] = true; });
        var blocked = (check && check.blocked) || null;
        var pending = (check && check.pending) || null;
        var out = { publish: [], hold: [], versions: [], save: false, reasons: {}, checks: {}, missing: false, waiting: false };
        (rows || []).forEach(function (r) {
            if (!selectable(r, anyway, blocked)) {
                if (r && !r.locked && has(blocked, r.key) && r.state === 'live' && r.changed) out.hold.push(r.key);
                return;
            }
            if (on[r.key] && has(pending, r.key)) out.waiting = true;
            var why = String((anyway && anyway[r.key]) || '').trim();
            if (on[r.key] && (r.state === 'held' || has(blocked, r.key))) {
                if (!why) { out.missing = true; return; }
                if (has(blocked, r.key)) out.checks[r.key] = why;
            }
            if (r.state === 'held') {
                if (!on[r.key]) return;
                out.publish.push(r.key);
                out.reasons[r.key] = why;
                out.save = true;
            } else if (r.state === 'draft' || r.state === 'scheduled') {
                if (on[r.key]) { out.publish.push(r.key); out.save = true; }
            } else if (r.state === 'version') {
                if (on[r.key]) { out.versions.push(r.key); out.save = true; }
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
    // `versions`: key => 'live' for a new version its bundle published (the
    // page it replaced is listed once, as on the site, under its name) or
    // 'draft' for one that stays a draft.
    function outcome(rows, pages, hold, versions) {
        var got = {};
        (Array.isArray(pages) ? pages : []).forEach(function (m) { if (m && m.key) got[m.key] = m; });
        var held = {};
        (hold || []).forEach(function (k) { held[k] = true; });
        var out = { live: [], draft: [], kept: [] };
        var replaced = {};
        (rows || []).forEach(function (r) {
            if (has(versions, r.key) && versions[r.key] === 'live' && r.replacesKey) replaced[r.replacesKey] = true;
        });
        (rows || []).forEach(function (r) {
            if (replaced[r.key]) return;
            if (has(versions, r.key)) {
                if (versions[r.key] === 'live') out.live.push(r.replaces || r.name);
                else out.draft.push(r.name);
                return;
            }
            var m = got[r.key];
            if (!m) return;
            if (held[r.key] || typeof m.draft === 'undefined') out.kept.push(r.name);
            else out[m.draft ? 'draft' : 'live'].push(r.name);
        });
        return out;
    }

    // What a row's result says, from its summary: `kind` its look (run, ok,
    // warn, bad, none), `text` its data-sdp-text key, `n` the count it
    // names. A page being checked is `run`; a page not ticked has none. A
    // page with blocking findings says "n blocking"; its others are listed
    // inside.
    function result(sum, busy) {
        if (busy) return { kind: 'run', text: 'checking', n: 0 };
        if (!sum) return null;
        if (sum.state === 'block') return { kind: 'bad', text: 'result-block', n: sum.block || 0 };
        if (sum.state === 'look') return { kind: 'warn', text: 'result-look', n: sum.look || 0 };
        if (sum.state === 'ready') return { kind: 'ok', text: 'result-ready', n: 0 };
        return { kind: 'none', text: 'result-failed', n: 0 };
    }

    var bridge = null;
    var current = [];   // the rows read when the sheet opened
    var checked = {};
    var anyway = {};    // held or blocking rows published anyway: key => reason
    var saving = false;
    var results = {};   // the Publish check: key => summary
    var pending = {};   // key => true while the page is checked
    var openKey = '';   // the row whose findings are shown
    var round = 0;      // answers of an earlier opening are dropped
    // The new versions published after the save: { todo, replace, done,
    // ask: key => why, reply, hold, rows }; null when none runs.
    var vrun = null;

    function blockedKeys() {
        var out = {};
        Object.keys(results).forEach(function (k) { if (results[k] && results[k].state === 'block') out[k] = true; });
        return out;
    }

    function checkState() {
        return { blocked: blockedKeys(), pending: pending };
    }

    // Checks the ticked pages that have no result yet.
    function ask(keys) {
        if (!bridge || typeof bridge.check !== 'function') return;
        var todo = (keys || []).filter(function (k) { return !has(results, k) && !has(pending, k); });
        if (!todo.length) return;
        todo.forEach(function (k) { pending[k] = true; });
        var mine = round;
        bridge.check(todo, function (key, sum) {
            if (mine !== round || !has(pending, key)) return;
            delete pending[key];
            results[key] = sum || { state: 'failed' };
            // A blocking page is unticked until "Publish anyway".
            if (results[key].state === 'block' && !has(anyway, key)) delete checked[key];
            if (!saving) draw();
        });
    }

    function el() { return document.getElementById('sdPublishSheet'); }

    function text(key) {
        var root = el();
        var t = root ? root.querySelector('[data-sdp-text="' + key + '"]') : null;
        return t ? t.textContent : key;
    }

    // A word of the new version's ask, handed by the editor.
    function vtext(key) {
        var t = bridge && bridge.version && bridge.version.texts;
        return (t && t[key]) || key;
    }

    function rowEl(r) {
        var item = document.createElement('label');
        item.className = 'list-group-item bg-transparent d-flex align-items-center gap-3 sd-publish-row';
        item.setAttribute('data-key', r.key);
        var box = document.createElement('input');
        box.type = 'checkbox';
        box.className = 'check m-0 flex-shrink-0';
        var blocked = blockedKeys();
        box.checked = !!checked[r.key];
        box.disabled = !selectable(r, anyway, blocked) || !!vrun;
        box.addEventListener('change', function () {
            if (box.checked) checked[r.key] = true; else delete checked[r.key];
            // Unchecked, a draft published anyway is held again.
            if (!box.checked && has(anyway, r.key)) { delete anyway[r.key]; draw(); return; }
            // Ticked, a page is checked.
            if (box.checked && !has(results, r.key)) { ask([r.key]); draw(); return; }
            sync();
        });
        var name = document.createElement('span');
        name.className = 'flex-grow-1 text-truncate sd-publish-name';
        if (has(blocked, r.key) && !has(anyway, r.key)) name.classList.add('sd-publish-dim');
        name.textContent = r.name;
        if (r.active) name.classList.add('fw-semibold');
        var state = document.createElement('span');
        // A new version is a draft: it wears the draft's badge.
        state.className = 'badge sd-publish-state sd-publish-state-' + (r.state === 'version' ? 'draft' : r.state);
        // A held row may bring its own words (a release bundle's draft names
        // the bundle); otherwise the state's text from the markup.
        state.textContent = r.label || text('state-' + r.state);
        if (r.note) { state.title = r.note; item.title = r.note; }
        item.appendChild(box);
        item.appendChild(name);
        if (r.changed) {
            var ch = document.createElement('span');
            ch.className = 'small fg-warning text-nowrap';
            ch.textContent = text('changed');
            item.appendChild(ch);
        }
        if (r.compare && bridge && typeof bridge.compare === 'function') {
            var cmp = document.createElement('button');
            cmp.type = 'button';
            cmp.className = 'pg-button pg-button-quiet pg-button-sm flex-shrink-0';
            cmp.title = text('compare');
            cmp.setAttribute('aria-label', text('compare'));
            cmp.innerHTML = '<span class="bi bi-arrow-left-right" aria-hidden="true"></span>';
            cmp.addEventListener('click', function (e) {
                // Inside the row's label: the click must not tick the box.
                e.preventDefault();
                bridge.compare(r.key);
            });
            item.appendChild(cmp);
        }
        if (r.locked) {
            var lk = document.createElement('span');
            lk.className = 'small fg-secondary text-nowrap';
            lk.textContent = text('locked');
            item.appendChild(lk);
        }
        item.appendChild(state);
        var res = resultEl(r);
        if (res) {
            // On a narrow sheet the result goes to a second line.
            var brk = document.createElement('span');
            brk.className = 'sd-publish-brk';
            item.appendChild(brk);
            item.appendChild(res);
        }
        if (((r.state === 'held' && r.override) || has(blocked, r.key)) && !has(anyway, r.key)) {
            var any = document.createElement('button');
            any.type = 'button';
            any.className = 'pg-button pg-button-quiet pg-button-sm text-nowrap';
            any.textContent = text('anyway');
            any.addEventListener('click', function (e) {
                e.preventDefault();
                anyway[r.key] = '';
                checked[r.key] = true;
                draw();
                var f = el() && el().querySelector('[data-sdp-reason="' + r.key + '"]');
                if (f) f.focus();
            });
            item.appendChild(any);
        }
        return item;
    }

    // The result at the end of a checked row: "Checking…" while it runs,
    // then a button that opens the page's findings under the row.
    function resultEl(r) {
        if (!bridge || typeof bridge.check !== 'function' || (!checked[r.key] && !has(results, r.key))) return null;
        var how = result(results[r.key], has(pending, r.key));
        if (!how) return null;
        var label = text(how.text).replace('{n}', String(how.n));
        var ICON = { ok: 'bi-check-circle-fill', warn: 'bi-exclamation-triangle-fill', bad: 'bi-sign-stop-fill', none: 'bi-dash-circle' };
        if (how.kind === 'run') {
            var run = document.createElement('span');
            run.className = 'sd-publish-result is-run';
            var sp = document.createElement('span');
            sp.className = 'spinner-border spinner-border-sm';
            sp.setAttribute('aria-hidden', 'true');
            run.appendChild(sp);
            run.appendChild(document.createTextNode(label));
            return run;
        }
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'sd-publish-result is-' + how.kind + (openKey === r.key ? ' is-open' : '');
        b.setAttribute('aria-expanded', openKey === r.key ? 'true' : 'false');
        b.setAttribute('data-sdp-result-key', r.key);
        var ic = document.createElement('span');
        ic.className = 'bi ' + ICON[how.kind];
        ic.setAttribute('aria-hidden', 'true');
        var ch = document.createElement('span');
        ch.className = 'bi ' + (openKey === r.key ? 'bi-chevron-up' : 'bi-chevron-down');
        ch.setAttribute('aria-hidden', 'true');
        b.appendChild(ic);
        b.appendChild(document.createTextNode(label));
        b.appendChild(ch);
        b.addEventListener('click', function (e) {
            e.preventDefault();
            openKey = (openKey === r.key) ? '' : r.key;
            draw();
            var again = el() && el().querySelector('[data-sdp-result-key="' + r.key + '"]');
            if (again) again.focus();
        });
        return b;
    }

    // The findings of the open row, drawn by the check.
    function findingsEl(r) {
        if (openKey !== r.key || !has(results, r.key) || !bridge || typeof bridge.findings !== 'function') return null;
        var box = bridge.findings(r.key, function () {
            delete results[r.key];
            ask([r.key]);
            draw();
        });
        if (!box) return null;
        var wrap = document.createElement('div');
        wrap.className = 'sd-publish-findings';
        wrap.appendChild(box);
        return wrap;
    }

    // The reason asked under a held or blocking row published anyway.
    function reasonEl(r) {
        var wrap = document.createElement('div');
        wrap.className = 'list-group-item bg-transparent pt-0 sd-publish-reason';
        var id = 'sdp-reason-' + String(r.key).replace(/[^A-Za-z0-9_-]/g, '_');
        var lab = document.createElement('label');
        lab.className = 'form-label small fg-warning mb-1';
        lab.setAttribute('for', id);
        lab.textContent = text(has(blockedKeys(), r.key) ? 'anyway-check' : 'anyway-reason');
        var input = document.createElement('input');
        input.type = 'text';
        input.id = id;
        input.className = 'form-control form-control-sm';
        input.maxLength = 500;
        input.required = true;
        input.setAttribute('data-sdp-reason', r.key);
        input.value = anyway[r.key] || '';
        input.addEventListener('input', function () { anyway[r.key] = input.value; sync(); });
        wrap.appendChild(lab);
        wrap.appendChild(input);
        return wrap;
    }

    // In a new version's row place, when its live page changed since the
    // version was started: why, "Replace anyway" and "Cancel".
    function askEl(r) {
        var wrap = document.createElement('div');
        wrap.className = 'list-group-item bg-transparent sd-publish-reason';
        wrap.setAttribute('data-sdp-ask', r.key);
        wrap.setAttribute('role', 'group');
        var why = document.createElement('p');
        why.className = 'small fg-warning mb-2';
        var name = document.createElement('span');
        name.className = 'fw-semibold';
        name.textContent = r.replaces || r.name;
        why.appendChild(name);
        why.appendChild(document.createTextNode(': ' + (vrun.ask[r.key] || '')));
        var acts = document.createElement('div');
        acts.className = 'd-flex flex-wrap gap-2';
        [['replace', 'pg-button pg-button-sm pg-button-danger'], ['cancel', 'pg-button pg-button-sm pg-button-quiet']].forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = b[1];
            btn.textContent = vtext(b[0]);
            btn.addEventListener('click', function () { answerAsk(r.key, b[0] === 'replace'); });
            acts.appendChild(btn);
        });
        wrap.appendChild(why);
        wrap.appendChild(acts);
        return wrap;
    }

    // The checked new versions, one after the other, each by its bundle.
    // A changed live page waits for its ask; when none is left, the result.
    function versionsNext() {
        if (!vrun) return;
        var key = vrun.todo.shift();
        if (!key) {
            if (Object.keys(vrun.ask).length) {
                busy(false);
                var first = el() && el().querySelector('[data-sdp-ask] button');
                if (first) first.focus();
                return;
            }
            var run = vrun;
            vrun = null;
            busy(false);
            drawResult(outcome(run.rows, run.reply.pages, run.hold, run.done));
            view(true);
            return;
        }
        var mine = round;
        bridge.version.publish(key, !!vrun.replace[key], function (ans) {
            if (mine !== round || !vrun) return;
            if (ans && ans.ok) vrun.done[key] = 'live';
            else if (ans && ans.changed) vrun.ask[key] = String(ans.error || '');
            else vrun.done[key] = 'draft';
            versionsNext();
        });
    }

    function answerAsk(key, replace) {
        if (!vrun || saving || !has(vrun.ask, key)) return;
        delete vrun.ask[key];
        if (replace) {
            vrun.replace[key] = true;
            vrun.todo.push(key);
        } else {
            vrun.done[key] = 'draft';
        }
        // Another ask still open waits for its answer.
        if (Object.keys(vrun.ask).length && !vrun.todo.length) { draw(); return; }
        busy(true);
        versionsNext();
    }

    function sync() {
        var root = el();
        if (!root || !bridge) return;
        var rows = current;
        var p = plan(rows, Object.keys(checked), anyway, checkState());
        var go = root.querySelector('[data-sdp-act="publish"]');
        if (go) go.disabled = !p.save || p.missing || p.waiting || !!vrun;
        var all = root.querySelector('[data-sdp-act="all"]');
        if (all) all.disabled = !!vrun;
        var busyN = Object.keys(pending).length;
        var wait = root.querySelector('[data-sdp-checking]');
        if (wait) {
            wait.classList.toggle('d-none', !busyN);
            var wt = wait.querySelector('[data-sdp-checking-text]');
            if (wt) wt.textContent = text('checking-n').replace('{n}', String(busyN));
        }
        var note = root.querySelector('[data-sdp-hold-note]');
        if (note) note.classList.toggle('d-none', !p.hold.length);
        var active = null;
        rows.forEach(function (r) { if (r.active) active = r; });
        var showOff = function (act, on) {
            var b = root.querySelector('[data-sdp-act="' + act + '"]');
            if (b) b.classList.toggle('d-none', !on);
        };
        // While a new version's ask is open, only its two buttons act.
        showOff('page-draft', !vrun && !!active && active.state === 'live' && !active.home && !active.locked);
        showOff('all-draft', !vrun && rows.some(function (r) { return r.state === 'live' && !r.home && !r.locked; }));
        // "Save as a new version" (or "Open the new version") for the open
        // page, where the editor offers it.
        var nv = root.querySelector('[data-sdp-version]');
        var nvLabel = (active && !vrun && bridge.version) ? bridge.version.label(active.key) : '';
        if (nv) {
            nv.classList.toggle('d-none', !nvLabel);
            var nvText = nv.querySelector('[data-sdp-version-label]');
            if (nvText && nvLabel) nvText.textContent = nvLabel;
        }
    }

    function draw() {
        var root = el();
        var list = root ? root.querySelector('[data-sdp-list]') : null;
        if (!list) return;
        list.textContent = '';
        current.forEach(function (r) {
            if (vrun && has(vrun.ask, r.key)) { list.appendChild(askEl(r)); return; }
            list.appendChild(rowEl(r));
            if (has(anyway, r.key)) list.appendChild(reasonEl(r));
            var f = findingsEl(r);
            if (f) list.appendChild(f);
        });
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
        var nv = q('[data-sdp-version]');
        if (nv && result) nv.classList.add('d-none');
        var go = q('[data-sdp-act="publish"]');
        if (go && !result) go.classList.remove('d-none');
        var done = q('[data-sdp-act="done"]');
        if (done) done.classList.toggle('d-none', !result);
        if (result) {
            var n = q('[data-sdp-hold-note]');
            if (n) n.classList.add('d-none');
            var w = q('[data-sdp-checking]');
            if (w) w.classList.add('d-none');
        }
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
                var all = rows.filter(function (r) { return selectable(r, anyway, blockedKeys()); }).map(function (r) { return r.key; });
                var every = all.every(function (k) { return checked[k]; });
                checked = {};
                if (!every) all.forEach(function (k) { checked[k] = true; });
                ask(Object.keys(checked));
                draw();
            } else if (act === 'publish') {
                var p = plan(rows, Object.keys(checked), anyway, checkState());
                if (!p.save || p.missing || p.waiting || saving) return;
                // The sheet stays open: the button shows the save, then the
                // sheet lists where the pages went. A save that did not run
                // (its reason is shown by the editor) gives the pick back.
                var report = function (reply) {
                    var root = el();
                    if (!saving) return false;
                    if (reply && p.versions.length && bridge.version && typeof bridge.version.publish === 'function') {
                        // The versions go after the save is over: the
                        // editor takes the reply in first (baselines).
                        vrun = { todo: p.versions.slice(), replace: {}, done: {}, ask: {}, reply: reply, hold: p.hold, rows: rows };
                        setTimeout(versionsNext, 0);
                        return !!(root && root.open);
                    }
                    busy(false);
                    if (!reply) return false;
                    drawResult(outcome(rows, reply.pages, p.hold));
                    view(true);
                    return !!(root && root.open);
                };
                busy(true);
                bridge.publish(p.publish, p.hold, report, p.reasons, p.checks);
            } else if (act === 'done') {
                closeThen(function () {});
            } else if (act === 'page-draft') {
                var a = rows.filter(function (r) { return r.active; });
                closeThen(function () { bridge.takeOff(a.map(function (r) { return r.key; })); });
            } else if (act === 'new-version') {
                var v = rows.filter(function (r) { return r.active; });
                if (v.length && bridge.version) closeThen(function () { bridge.version.run(v[0].key); });
            } else if (act === 'all-draft') {
                closeThen(function () { bridge.takeOff(rows.map(function (r) { return r.key; })); });
            }
        });
    }

    // `b.reopen`: the row whose findings are open again ("Back to Publish").
    function open(b) {
        var root = el();
        if (!root || !b) return false;
        if (saving) { if (window.pgUI) pgUI.dialog.show(root); return true; }
        bridge = b;
        vrun = null;
        view(false);
        current = bridge.rows() || [];
        checked = {};
        anyway = {};
        results = {};
        pending = {};
        round++;
        openKey = b.reopen ? String(b.reopen) : '';
        defaults(current).forEach(function (k) { checked[k] = true; });
        if (openKey) checked[openKey] = true;
        bind(root);
        ask(Object.keys(checked));
        draw();
        if (window.pgUI) pgUI.dialog.show(root);
        return true;
    }

    // A page's result changed inside the sheet (a "Fix", its "Undo", an
    // "Ignore"): the rows are read again (the page may now be changed), the
    // row's result and findings drawn again, and the focus put on `focus`
    // (a selector inside the sheet) when given.
    function update(key, sum, focus) {
        if (!bridge || saving || vrun || !has(results, key)) return;
        results[key] = sum || { state: 'failed' };
        current = bridge.rows() || current;
        if (results[key].state === 'block' && !has(anyway, key)) delete checked[key];
        draw();
        var f = focus && el() ? el().querySelector(focus) : null;
        if (f) f.focus();
    }

    // Closes the sheet: a press on a finding's path selects its element.
    function close() {
        var root = el();
        if (root && window.pgUI && root.open) return pgUI.dialog.hide(root);
        return Promise.resolve();
    }

    return { defaults: defaults, plan: plan, outcome: outcome, result: result, open: open, close: close, update: update };
})();
