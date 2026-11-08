/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the interactions part: the rows of the properties
 * panel's Interactions section (the effect, its trigger and timing, the
 * sticky offset, the parallax speed), the fields that write a node's
 * props._fx and the "Play on the canvas" button. Not needed to open a
 * page: the core draws the section with an empty body and runs this file
 * the first time an open section is shown (_sdPartLoad(),
 * pg_designer_parts()). What the page and the canvas need at open - the
 * spec, the normalizer, the attributes and the canvas script - stays in
 * the core and reaches this file through the kit (_sdPartKit()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('interactions', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var row = K.row;
    var swAttr = K.swAttr;
    var _sdFxSpec = K._sdFxSpec;
    var _sdFxNormalize = K._sdFxNormalize;

    // The node's interaction after one field of the panel changed: the
    // stored one with `field` set to `value`, normalized; null when the
    // effect is cleared. Fields that no longer apply drop out; switching
    // the effect keeps the timing.
    function _sdFxSet(fx, field, value) {
        if (field === 'effect' && !value) return null;
        var cur = _sdFxNormalize(fx) || {}, next = {};
        for (var k in cur) if (Object.prototype.hasOwnProperty.call(cur, k)) next[k] = cur[k];
        next[field] = value;
        return _sdFxNormalize(next);
    }

    // The section's rows for the node: the effect, then the rows that
    // follow from it and its trigger.
    function _sdFxRows(n) {
        var fx = _sdFxNormalize(n.props._fx) || { effect: '' };
        var spec = _sdFxSpec();
        var select = function (field, opts) {
            return '<select class="form-control form-control-sm" data-fx="' + field + '">' + opts.map(function (o) {
                return '<option value="' + esc(o[0]) + '"' + (o[0] === fx[field] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
            }).join('') + '</select>';
        };
        var number = function (field, unit, placeholder) {
            var r = spec.numbers[field];
            var v = (field === 'distance' && !fx.distance) ? '' : fx[field];
            return '<div class="input-group input-group-sm">' +
                '<input type="number" class="form-control form-control-sm" data-fx="' + field + '" min="' + r[0] + '" max="' + r[1] + '" step="1" value="' + esc(String(v)) + '"' +
                (placeholder ? ' placeholder="' + esc(String(placeholder)) + '"' : '') + '>' +
                '<span class="input-group-text">' + unit + '</span></div>';
        };
        var h = row(_sdT('Effect'), select('effect', [
            ['', _sdT('None')], ['fade', _sdT('Fade in')],
            ['slide-up', _sdT('Slide up')], ['slide-down', _sdT('Slide down')],
            ['slide-left', _sdT('Slide left')], ['slide-right', _sdT('Slide right')],
            ['scale', _sdT('Scale')], ['sticky', _sdT('Sticky')], ['parallax', _sdT('Parallax')]
        ]));
        if (fx.trigger) {
            var entrance = fx.trigger === 'load' || fx.trigger === 'view';
            h += row(_sdT('Trigger'), select('trigger', [
                ['view', _sdT('Scroll into view')], ['load', _sdT('Page load')],
                ['hover', _sdT('Hover')], ['click', _sdT('Click')]
            ]));
            h += row(_sdT('Duration'), number('duration', 'ms'));
            h += row(_sdT('Delay'), number('delay', 'ms'));
            h += row(_sdT('Easing'), select('easing', [
                ['ease-out', _sdT('Ease out')], ['ease', _sdT('Ease')], ['ease-in', _sdT('Ease in')],
                ['ease-in-out', _sdT('Ease in and out')], ['linear', _sdT('Linear')]
            ]));
            if (/^slide-/.test(fx.effect)) h += row(_sdT('Distance'), number('distance', 'px', entrance ? 24 : 6));
            if (entrance) h += row(_sdT('Stagger children'), number('stagger', 'ms'));
            if (fx.trigger === 'view') h += row(_sdT('Every time'), swAttr('data-fx="repeat"', !!fx.repeat));
        }
        if (fx.effect === 'sticky') h += row(_sdT('Top offset'), number('offset', 'px'));
        if (fx.effect === 'parallax') h += row(_sdT('Speed'), number('speed', '%'));
        if (fx.trigger) {
            h += row('', '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-fx-play>' +
                '<span class="bi bi-play-fill"></span> ' + esc(_sdT('Play on the canvas')) + '</button>');
        }
        if (fx.effect) {
            h += '<div class="sd-prop-row"><div class="form-text small mb-0">' +
                esc(_sdT('Plays on the published page and in Preview. Visitors who ask for less motion see the element without it.')) +
                '</div></div>';
        }
        return h;
    }

    // The Play button: the node's interaction once on the canvas, whether
    // the canvas's motion plays or not.
    function _sdFxReplay(node) {
        var canvasDoc = K.canvasDoc;
        var el = K._sdCanvasInnerOf(node);
        if (el && !el.hasAttribute('data-pg-fx')) el = el.querySelector('[data-pg-fx]');
        var win = canvasDoc && canvasDoc.defaultView;
        if (!el || !win) return;
        if (win.pgFx && typeof win.pgFx.replay === 'function') { win.pgFx.replay(el); return; }
        // The file is not in the frame yet: load it, then play.
        K._sdFxCanvas();
        var scr = canvasDoc.getElementById('pg-fx-js');
        if (scr) scr.addEventListener('load', function () { if (win.pgFx && win.pgFx.replay) win.pgFx.replay(el); });
    }

    // The fields write props._fx (_sdFxSet()). A choice is one undo step
    // and redraws the panel, whose rows follow the effect and trigger; a
    // typed number is one undo step per focus and redraws the panel once
    // it is left, showing the value kept.
    function _sdFxFill(body, n) {
        body.innerHTML = _sdFxRows(n);
        var play = body.querySelector('[data-fx-play]');
        if (play) play.addEventListener('click', function () { _sdFxReplay(n); });
        body.querySelectorAll('[data-fx]').forEach(function (el) {
            var field = el.getAttribute('data-fx');
            var typed = el.type === 'number';
            var before = typed ? K._sdUndoBeforeFirstInput(el) : null;
            el.addEventListener(typed ? 'input' : 'change', function () {
                if (typed) before(); else K.saveState();
                var fx = _sdFxSet(n.props._fx, field, el.type === 'checkbox' ? el.checked : el.value);
                if (fx) n.props._fx = fx; else delete n.props._fx;
                if (!typed) { K.render(); return; }
                K.renderCanvas();
                K.renderTree();
                K.renderStatusBar();
                K.renderAttrsPanel();
                K.renderHtmlTree();
                K._sdLivePreviewSchedule();
            });
            if (typed) el.addEventListener('change', function () { K.render(); });
        });
    }

    return {
        fill: _sdFxFill,
        rows: _sdFxRows,
        set: _sdFxSet
    };
});
