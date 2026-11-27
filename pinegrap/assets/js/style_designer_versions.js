/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - a live page's new version, the door and the tab: a
 * draft that replaces the page when it is published, made by the server's
 * designer/version_start (includes/fn/page_versions.php). "Save as a new
 * version" (a live tab's menu, the command palette, the Publish sheet's
 * foot) sends that tab's changes to the new draft, opens the draft's tab
 * selected and lays the page as it is on the site back onto the live tab.
 * Where the page has a version the same door reads "Open the new version"
 * and selects its tab. The draft's tab menu has "Discard this version"
 * (designer/version_discard), which asks first. Not needed to open a page:
 * the core runs this file on the first press (_sdPartLoad()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('versions', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    // A start or a discard in flight: a second press waits for it.
    var busy = false;

    // The editor's versions block, by page id (an empty PHP object may come
    // as an array).
    function versions() {
        var d = K.design;
        if (!d) return {};
        if (!d.versions || Array.isArray(d.versions)) d.versions = {};
        return d.versions;
    }

    // The two rows of the versions block a started version gets, keyed by
    // page id: its draft's (role 'version') and its live page's (role
    // 'live'). Pure.
    function entries(answer, liveName) {
        var base = function (role) {
            return {
                role: role,
                bundle_id: answer.bundle_id | 0,
                draft_page_id: answer.draft_page_id | 0,
                live_page_id: answer.live_page_id | 0,
                draft_name: String(answer.draft_name || ''),
                live_name: String(liveName || '')
            };
        };
        var out = {};
        out[String(answer.draft_page_id | 0)] = base('version');
        out[String(answer.live_page_id | 0)] = base('live');
        return out;
    }

    // The started version joins the editor's state: the versions block,
    // and the draft held like any bundled draft until NV3's Publish (the
    // server answers the same after a reload, pg_experiment_editor_config()).
    function remember(answer, liveName) {
        var rows = entries(answer, liveName);
        var v = versions();
        Object.keys(rows).forEach(function (id) { v[id] = rows[id]; });
        var ex = K.design && K.design.experiments;
        var draftId = String(answer.draft_page_id | 0);
        if (ex && (!ex.pages || Array.isArray(ex.pages))) ex.pages = {};
        if (ex && !ex.pages[draftId]) {
            var bundle = _sdT('New version of {var:1}', liveName);
            ex.pages[draftId] = {
                role: 'bundle', held: true, bundle_id: answer.bundle_id | 0, bundle_name: bundle,
                label: _sdT('Held by the bundle {var:1}', bundle),
                hold_text: _sdT('The page "{var:1}" is in the release bundle "{var:2}", so it stays a draft until the bundle is published or deleted, or the page is removed from it.',
                    [String(answer.draft_name || ''), bundle])
            };
        }
    }

    function forget(entry) {
        var v = versions();
        delete v[String(entry.draft_page_id)];
        delete v[String(entry.live_page_id)];
        var ex = K.design && K.design.experiments;
        if (ex && ex.pages && !Array.isArray(ex.pages)) delete ex.pages[String(entry.draft_page_id)];
    }

    function load(pageId) {
        return K._pgApiPost('page_load', { page_id: pageId }).then(function (res) {
            if (!res || res.status !== 'success' || !res.page) throw new Error((res && res.message) || _sdT('The page could not be loaded.'));
            return res.page;
        });
    }

    // A saved page added as an unchanged tab right of `afterKey`.
    function addTab(sp, afterKey) {
        var key = 'p' + sp.page_id;
        var had = K._pgPageByKey(key);
        if (had) return had;
        sp.key = key;
        var p = K._pgPageFromRow(sp);
        p.baseline = K._pgBaselineOf(p);
        K._pgTabsInsertAfter(p, afterKey);
        K._pgTabsLayoutChanged();
        return p;
    }

    // A version's draft selected: its tab when open, loaded first when not.
    function openDraft(draftId, afterKey) {
        var key = 'p' + draftId;
        if (K._pgPageByKey(key)) {
            if (key !== K.activeKey) K._pgTabsSwitch(key);
            return Promise.resolve();
        }
        return load(draftId).then(function (sp) {
            addTab(sp, afterKey);
            K._pgTabsSwitch(key);
        });
    }

    function fail(err) {
        busy = false;
        K.sdToast(esc((err && err.message) || _sdT('Network error.')), 'error', 6000);
    }

    // "Save as a new version" on a live tab, or "Open the new version"
    // where it has one.
    function start(key) {
        var p = K._pgPageByKey(key);
        if (!p || busy) return;
        var own = K._pgVersionOf(p);
        if (own && own.role === 'live') {
            busy = true;
            openDraft(own.draft_page_id, key).then(function () { busy = false; }, fail);
            return;
        }
        if (!(p.page_id > 0) || p.page_draft || p.pickedPending) return;
        K._pgTabsPersistActive();
        // The tab as the save would send it; a tab nothing changed sends no
        // page and the copy stays as the page on the site is.
        var recs = K._pgTabsSerialize(key);
        if (!recs) return;
        var rec = recs[0];
        var changed = !!rec && !rec.unchanged;
        var liveName = p.savedName || p.page_name || '';
        busy = true;
        K.sdToast('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + esc(_sdT('Saving the new version…')), 'info', 3000);
        K._pgApiPost('version_start', { page_id: p.page_id, page: changed ? JSON.stringify(rec) : '' }).then(function (res) {
            if (!res || !res.ok) throw new Error((res && res.error) || _sdT('The new version could not be saved.'));
            remember(res, liveName);
            // An existing version is opened as it is: this tab's changes
            // were not taken, so they stay here.
            return Promise.all([load(res.draft_page_id), res.existing ? null : load(p.page_id)]).then(function (got) {
                var draft = addTab(got[0], p.key);
                K._pgTabsSwitch(draft.key);
                if (got[1]) K._pgContentLay(p, got[1].tree || K.createDefaultTree(), got[1], true);
                K._pgTabsRender();
                busy = false;
                K.sdToast(esc(res.existing
                    ? _sdT('This page already has a new version. It was opened; your changes stay on the page\'s own tab.')
                    : _sdT('The new version was saved as a draft. The page on the site stays as it is.')), res.existing ? 'warning' : 'success', 6000);
                (res.warnings || []).forEach(function (w) { K.sdToast(esc(String(w)), 'warning', 8000); });
            });
        }).catch(fail);
    }

    // "Discard this version" on its draft's tab: asked first, then the
    // draft goes to the Recycle Bin and the live page's tab is selected.
    function discard(key) {
        var p = K._pgPageByKey(key);
        var own = p ? K._pgVersionOf(p) : null;
        if (!own || own.role !== 'version' || busy) return;
        var msg = _sdT('The draft and its changes go to the Recycle Bin. The page on the site stays as it is.');
        var asked = (typeof window.pgConfirm === 'function')
            ? window.pgConfirm({ title: _sdT('Discard this version?'), message: msg,
                                 confirmText: _sdT('Discard'), cancelText: _sdT('Cancel'), variant: 'danger' })
            : Promise.resolve(window.confirm(msg));
        Promise.resolve(asked).then(function (ok) {
            if (!ok) return;
            busy = true;
            return K._pgApiPost('version_discard', { page_id: p.page_id }).then(function (res) {
                if (!res || !res.ok) throw new Error((res && res.error) || _sdT('The new version could not be discarded.'));
                forget(own);
                var idx = K.pages.indexOf(p);
                if (idx !== -1) K.pages.splice(idx, 1);
                if (!K.pages.length) { window.location.reload(); return; }
                if (K.activeKey === key) {
                    var live = K._pgPageByKey('p' + own.live_page_id) || K.pages[Math.max(0, idx - 1)];
                    K._pgTabsSwitch(live.key);
                }
                K._pgTabsLayoutChanged();
                busy = false;
                K.sdToast(esc(_sdT('The new version was discarded. Its draft is in the Recycle Bin.')), 'success', 6000);
            });
        }).catch(fail);
    }

    return { start: start, discard: discard, entries: entries };
});
