/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the review view part: with "Only what I changed"
 * switched on, one list in the Options tabs' place of every row changed in
 * the three tabs, each with its path (tab › section), its value and a reset
 * arrow; under it "Reset all", asked first and one undo step
 * (window.PGOptionsReview). Not needed to open a page: the core runs this
 * file the first time the switch is turned on (_sdPartLoad(),
 * pg_designer_parts()) and hands it the panel through a host
 * (_sdReviewHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('review', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var R = window.PGOptionsReview;

    // The place of the reset arrow that had the focus, so the list drawn
    // again after a reset puts it back on the row that took its place.
    var _rvFocusAt = null;

    // A value as the row shows it: a colour with its square, "— Multiple —"
    // when the selected elements differ, "none" when it is empty.
    function _rvValue(e) {
        if (e.kind === 'mixed') return '<span class="sd-review-v sd-review-word">' + esc(_sdT('— Multiple —')) + '</span>';
        if (e.kind === 'none') return '<span class="sd-review-v sd-review-word">' + esc(_sdT('none')) + '</span>';
        return (e.kind === 'color' ? '<span class="sd-color-preview ' + esc(e.swatch) + '" aria-hidden="true"></span>' : '') +
            '<span class="sd-review-v" title="' + esc(e.value) + '">' + esc(e.value) + '</span>';
    }

    function draw(host) {
        var box = host.box;
        if (!R || !box || !box.isConnected) return;
        var rows = host.rows();
        var list = R.list(rows, { content: host.tabName('content'), style: host.tabName('style'), advanced: host.tabName('advanced') });
        var locked = host.locked();
        if (!list.length) {
            box.innerHTML = '<p class="sd-review-empty">' + esc(_sdT('No option is changed here.')) + '</p>';
            _rvFocusAt = null;
            return;
        }
        box.innerHTML = '<ul class="sd-review-list" aria-label="' + esc(_sdT('Changed options')) + '">' +
            list.map(function (e) {
                var b = rows[e.i].lbl.previousElementSibling;
                var off = locked || !b || !b.classList.contains('sd-chg-reset') || b.disabled;
                return '<li class="sd-review-row">' +
                    '<div class="sd-review-tx"><b>' + esc(e.label) + '</b><span>' + esc(e.path) + '</span></div>' +
                    '<div class="sd-review-val">' + _rvValue(e) +
                        (e.share ? '<span class="sd-review-share" title="' + esc(_sdT('Changed in {var:1} of {var:2} elements', e.share.split('/'))) + '">' + esc(e.share) + '</span>' : '') +
                    '</div>' +
                    '<button type="button" class="sd-review-reset bi bi-arrow-counterclockwise" data-rv-i="' + e.i + '"' +
                        ' title="' + esc(_sdT('Reset to default')) + '" aria-label="' + esc(_sdT('Reset {var} to default', e.label)) + '"' + (off ? ' disabled' : '') + '></button>' +
                '</li>';
            }).join('') +
        '</ul>' +
        '<div class="sd-review-ft">' +
            '<button type="button" class="pg-button pg-button-ghost pg-button-sm sd-review-copy"' + (host.several ? ' disabled title="' + esc(_sdT('Copy style takes one element: select only one.')) + '"' : '') + '>' +
                '<span class="bi bi-brush" aria-hidden="true"></span><span class="pg-btn-label">' + esc(_sdT('Copy style')) + '</span></button>' +
            '<button type="button" class="pg-button pg-button-ghost pg-button-sm sd-review-all"' + (locked ? ' disabled' : '') + '>' +
                '<span class="bi bi-arrow-counterclockwise" aria-hidden="true"></span><span class="pg-btn-label">' + esc(_sdT('Reset all')) + '</span></button>' +
        '</div>';

        var arrows = box.querySelectorAll('.sd-review-reset');
        if (_rvFocusAt !== null) {
            var to = arrows[Math.min(_rvFocusAt, arrows.length - 1)];
            if (to && !to.disabled) to.focus();
            _rvFocusAt = null;
        }

        box.addEventListener('click', function (ev) {
            var t = ev.target.closest ? ev.target.closest('button') : null;
            if (!t || t.disabled || !box.contains(t)) return;
            if (t.classList.contains('sd-review-reset')) {
                var r = rows[Number(t.getAttribute('data-rv-i'))];
                if (!r) return;
                _rvFocusAt = Array.prototype.indexOf.call(box.querySelectorAll('.sd-review-reset'), t);
                host.reset(r.lbl);
                return;
            }
            if (t.classList.contains('sd-review-copy')) {
                host.copyStyle();
                return;
            }
            if (t.classList.contains('sd-review-all')) {
                host.ask(list.length).then(function (ok) {
                    if (!ok) return;
                    var n = host.resetAll();
                    if (n) K.sdToast(esc(_sdT('{var} options back to their defaults.', n)), 'success');
                });
            }
        });
    }

    return { draw: draw };
});
