/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the fonts part: the Google Fonts browser opened by
 * "Add a Google Font" (the Fonts section's menu and the command palette),
 * its searchable list of the font catalogue and the detail with the
 * preview and the weights. Not needed to open a page: the editor runs this
 * file the first time the browser is asked for (_sdPartLoad(),
 * pg_designer_parts()). It reaches the editor only through the kit
 * (_sdPartKit()) and the host the assets panel hands to open() (the fonts
 * added to the design, the catalogue, and the writer that saves the list).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('fonts', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // The assets panel's host, handed to every call: added() the design's
    // fonts ({ family, weights, enabled, movedToCss }), set(list) saves a
    // new list and redraws the Fonts section, catalogue() the font names.
    var H = null;
    var gfModalEl = null;
    var _gfOpenFamily = null;

    // The modal is built the first time the browser opens, with its close
    // button, its search field and the Escape key.
    function buildGfModal() {
        if (gfModalEl) return;
        gfModalEl = document.createElement('div');
        gfModalEl.id = 'sd-gf-modal';
        gfModalEl.style.display = 'none';
        gfModalEl.innerHTML =
            '<div class="sd-gf-modal-dialog">' +
                '<div class="sd-gf-modal-hdr">' +
                    '<span class="bi bi-fonts me-3"></span><span class="sd-gf-modal-title">Google Fonts</span>' +
                    '<button id="sd-gf-modal-close" class="sd-gf-modal-close" type="button" aria-label="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>' +
                '</div>' +
                '<div class="sd-gf-modal-body">' +
                    '<div class="sd-gf-modal-left">' +
                        '<div class="sd-gf-toolbar">' +
                            '<span class="bi bi-search sd-gf-search-icon"></span>' +
                            '<input type="text" id="sd-gf-search" class="sd-gf-search" placeholder="' + esc(_sdT('Search fonts...')) + '">' +
                        '</div>' +
                        '<div id="sd-gf-list" class="sd-gf-list"></div>' +
                    '</div>' +
                    '<div class="sd-gf-modal-right" id="sd-gf-detail">' +
                        '<div class="sd-gf-detail-empty"><span class="bi bi-arrow-left"></span> ' + esc(_sdT('Pick a font from the list')) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(gfModalEl);
        document.getElementById('sd-gf-modal-close').addEventListener('click', closeGfModal);
        gfModalEl.addEventListener('click', function(e) { if (e.target === gfModalEl) closeGfModal(); });
        gfModalEl.querySelector('#sd-gf-search').addEventListener('input', function() { renderGfBrowser(this.value); });
        // Escape closes the browser before the editor's own Escape handling.
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && gfModalEl.style.display !== 'none') { e.stopPropagation(); closeGfModal(); } }, true);
    }

    function openGfModal() {
        buildGfModal();
        gfModalEl.style.display = 'flex';
        renderGfBrowser('');
        setTimeout(function() { var s = document.getElementById('sd-gf-search'); if (s) { s.value = ''; s.focus(); } }, 50);
    }

    function closeGfModal() {
        gfModalEl.style.display = 'none';
        var det = document.getElementById('sd-gf-detail');
        if (det) det.innerHTML = '<div class="sd-gf-detail-empty"><span class="bi bi-arrow-left"></span> ' + esc(_sdT('Pick a font from the list')) + '</div>';
        _gfOpenFamily = null;
    }

    function renderGfBrowser(query) {
        var el = document.getElementById('sd-gf-list');
        if (!el) return;
        query = (query || '').toLowerCase().trim();
        var fonts = H.catalogue().filter(function(f) { return f !== 'Default' && (!query || f.toLowerCase().indexOf(query) !== -1); });
        var addedFams = H.added().map(function(f) { return f.family; });
        // Render the entire filtered list — the catalog (~180 entries)
        // is small enough that a virtual scroller would be overkill, and
        // the filter input on top means most queries narrow the list to
        // a handful anyway. Earlier builds capped at 100 with a "+X
        // more…" footer; that hid common picks like Roboto Slab and
        // forced the user to type to find them.
        el.innerHTML = fonts.map(function(f) {
            var added = addedFams.indexOf(f) !== -1;
            var activeCls = (_gfOpenFamily === f) ? ' active' : '';
            return '<div class="sd-gf-item' + (added ? ' sd-gf-added-mark' : '') + activeCls + '" data-gf-pick="' + f + '"><span class="sd-gf-item-name">' + f + '</span>' + (added ? '<span class="bi bi-check2-circle sd-gf-icon-ok"></span>' : '<span class="bi bi-plus-circle sd-gf-icon-add"></span>') + '</div>';
        }).join('');
        el.querySelectorAll('[data-gf-pick]').forEach(function(item) {
            item.addEventListener('click', function() { openGfPicker(this.dataset.gfPick, this); });
        });
    }

    function openGfPicker(family, anchorEl) {
        // Same-click toggle removed: the previous "click again to
        // deselect" behaviour read as a bug — the user thought their
        // click was lost. Now clicking a font always opens (or refreshes)
        // its detail panel; selection is cleared only by the modal close.
        _gfOpenFamily = family;
        document.querySelectorAll('.sd-gf-item.active').forEach(function(el) { el.classList.remove('active'); });
        // Active class derives from `_gfOpenFamily` rather than the
        // anchorEl reference — that way a stale anchorEl from an old
        // render (e.g. after Add re-renders the list) still highlights
        // the correct row instead of silently doing nothing.
        var liveAnchor = anchorEl;
        if (!liveAnchor || !liveAnchor.isConnected) {
            liveAnchor = document.querySelector('.sd-gf-item[data-gf-pick="' + family.replace(/"/g, '\\"') + '"]');
        }
        if (liveAnchor) liveAnchor.classList.add('active');
        var gfAdded = H.added();
        var existing = null; for (var i=0; i<gfAdded.length; i++) { if (gfAdded[i].family === family) { existing = gfAdded[i]; break; } }
        var selWts = existing ? existing.weights.slice() : [400];
        // Load preview font — the <link> targets the parent document so
        // the preview area in the modal can render in the chosen face.
        var prevLink = document.getElementById('sd-gf-preview-link');
        if (!prevLink) { prevLink = document.createElement('link'); prevLink.id = 'sd-gf-preview-link'; prevLink.rel = 'stylesheet'; document.head.appendChild(prevLink); }
        prevLink.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':wght@100;200;300;400;500;600;700;800;900&display=swap';
        var det = document.getElementById('sd-gf-detail');
        if (!det) return;
        // Detail layout (top-down): title → preview card → weights row
        // (numbers in default UI font; small sample swatch shows what the
        // weight looks like) → actions row.
        // Sample text follows the user's preview convention but is given
        // a card-style wrapper so the boundary between "preview text"
        // and "weight controls" is visually obvious.
        det.innerHTML =
            '<div class="sd-gf-det-head">' +
                '<div class="sd-gf-det-title">' + family + '</div>' +
                (existing
                    ? '<span class="sd-gf-det-status sd-gf-det-status-added"><span class="bi bi-check2-circle me-1"></span>' + esc(_sdT('Already added')) + '</span>'
                    : '<span class="sd-gf-det-status">' + esc(_sdT('Not added yet')) + '</span>') +
            '</div>' +
            '<div class="sd-gf-det-preview-card">' +
                '<div class="sd-gf-det-preview-label">' + esc(_sdT('Preview')) + '</div>' +
                '<div class="sd-gf-det-preview" style="font-family:\'' + family + '\',sans-serif">AaBbCcDd 0123</div>' +
                '<div class="sd-gf-det-preview-pangram" style="font-family:\'' + family + '\',sans-serif">' + esc(_sdT('The quick brown fox jumps over the lazy dog.')) + '</div>' +
            '</div>' +
            '<div class="sd-gf-det-subtitle">' + esc(_sdT('Weights')) + '</div>' +
            '<div class="sd-gf-det-wts">' + [100,200,300,400,500,600,700,800,900].map(function(w) {
                var isOn = selWts.indexOf(w) !== -1;
                return '<label class="sd-gf-det-wt' + (isOn ? ' active' : '') + '">' +
                            '<input type="checkbox" value="' + w + '"' + (isOn ? ' checked' : '') + '>' +
                            '<span class="sd-gf-det-wt-num">' + w + '</span>' +
                            '<span class="sd-gf-det-wt-sample" style="font-family:\'' + family + '\',sans-serif;font-weight:' + w + '">Aa</span>' +
                        '</label>';
            }).join('') + '</div>' +
            '<div class="sd-gf-det-actions">' +
                '<button class="sd-gf-det-add btn btn-solid theme-primary btn-sm" type="button"><span class="bi bi-plus-circle me-1"></span>' +
                    (existing ? _sdT('Update') : _sdT('Add')) +
                '</button>' +
                (existing ? '<button class="sd-gf-det-remove btn btn-outline theme-danger btn-sm" type="button"><span class="bi bi-trash me-1"></span>' + esc(_sdT('Remove')) + '</button>' : '') +
            '</div>';
        // Active state on weight chips so the user sees their selection
        // without staring at the tiny native checkbox.
        det.querySelectorAll('.sd-gf-det-wt input').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var lbl = this.closest('.sd-gf-det-wt');
                if (lbl) lbl.classList.toggle('active', this.checked);
            });
        });
        det.querySelector('.sd-gf-det-add').addEventListener('click', function() {
            var wts = []; det.querySelectorAll('input:checked').forEach(function(c) { wts.push(parseInt(c.value,10)); });
            if (!wts.length) wts = [400];
            var list = H.added().filter(function(f) { return f.family !== family; });
            list.push({ family: family, weights: wts, enabled: true });
            H.set(list);
            renderGfBrowser(document.getElementById('sd-gf-search') ? document.getElementById('sd-gf-search').value : '');
            // Re-open with the now-existing entry so the title flips to
            // "Update" and the Remove button appears.
            openGfPicker(family);
        });
        var remBtn = det.querySelector('.sd-gf-det-remove');
        if (remBtn) remBtn.addEventListener('click', function() {
            H.set(H.added().filter(function(f) { return f.family !== family; }));
            renderGfBrowser(document.getElementById('sd-gf-search') ? document.getElementById('sd-gf-search').value : '');
            // Keep _gfOpenFamily as-is so the font can be re-added
            // immediately without having to re-find it in the list. The
            // detail re-renders with the "Not added yet" status.
            openGfPicker(family);
        });
    }

    return {
        open: function (host) { H = host; openGfModal(); },
        pick: function (host, family) { H = host; openGfPicker(family); }
    };
});
