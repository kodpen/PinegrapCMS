/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the selection's commands as a bar over the stage where the
 * panel is a sheet (a phone, a narrow or short window). A touch screen has
 * no Delete key, and a closed sheet hides the panel's buttons, so selected
 * layers, the selected picture and an area drawn with Select area each get
 * their main commands there, with Options opening the sheet for the rest
 * (arrange, align, colours, feather).
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_builder.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md). Which
 * commands show is the model's M.actionList().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button;

    X.icons.more = 'M5 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0zM10.5 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0zM16 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0z';
    X.icons.invert = 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 3.5v17M12 8.5l6.6-3.4M12 13l8.3-4.2M12 17.5l7.4-3.8';

    // A command: its icon, its label's key, what it does (this is the
    // editor) and whether it removes something (drawn in the danger colour).
    var COMMANDS = {
        options: ['more', 'actOptions', function () {
            this.layersOpen = false;
            this.sheetOpen = true;
            this.render();
        }],
        removePin: ['puppet', 'puppetRemove', function () { this.removeChosenPin(); }, true],
        split: ['scissors', 'actSplit', function () { this.clipSplitAt(this.selected()[0]); }],
        duplicate: ['duplicate', 'duplicate', function () { this.addLayers(this.selected(), 10); }],
        remove: ['trash', 'delete', function () { this.removeLayers(); }, true],
        lockPicture: ['lock', 'lock', function () { this.lockPicture(true); }],
        removePicture: ['trash', 'delete', function () { this.deletePicture(); }, true],
        erase: ['eraser', 'areaErase', function () { this.areaErase(); }, true],
        keep: ['crop', 'areaKeep', function () { this.areaKeep(); }],
        copy: ['layers', 'areaCopy', function () { this.areaCopy(); }],
        cutToLayer: ['scissors', 'areaCut', function () { this.areaCut(); }],
        adjust: ['adjust', 'areaAdjust', function () { this.areaAdjust(); }],
        redact: ['redact', 'areaRedact', function () { this.areaRedact(); }],
        invert: ['invert', 'areaInvert', function () { this.areaInvert(); }],
        deselect: ['close', 'areaClear', function () { this.areaClear(); }]
    };

    Editor.inits.push(function () {
        this.actWrap = null;
        this.actBar = null;
        this.actKey = null;
    });

    // What is selected now, as M.actionList() reads it. The area's pixel
    // commands count only where the area part offers them.
    Editor.prototype.actionState = function () {
        var picking = Editor.selectTools.indexOf(this.tool) >= 0, picked = picking ? this.selected() : [];

        return {
            layers: picked.length,
            free: picked.filter(function (l) { return !l.locked; }).length,
            picture: !!(this.board && this.board.pic && this.pictureOn && this.pictureOn()),
            area: (this.tool === 'area') && !!this.areaSelected && this.areaSelected(),
            pin: !!this.chosenPin && (this.chosenPin() !== null),
            sheet: !!this.panel && !this.sheetOpen,
            // One clip in the Animate tool: Split at the playhead.
            split: (this.tool === 'motion') && (picked.length === 1) && (picked[0].type === 'video') && !!this.clipSplitAt,
            can: {
                erase: !!this.areaErase, keep: !!this.areaKeep, copy: !!this.areaCopy, cutToLayer: !!this.areaCut, adjust: !!this.areaAdjust,
                redact: !!this.newRedaction
            }
        };
    };

    // The bar: drawn again only when its commands change, placed on every
    // redraw of the stage above what covers the stage's bottom or right (an
    // open sheet, the Animate tool's bar), hidden while something is being
    // dragged or typed. Outside the small-screen layout the stylesheet keeps
    // it hidden: the panel shows the same buttons there.
    Editor.prototype.renderActions = function () {
        var self = this, list = (this.failed || this.textEdit || this.areaPoly || this.cropping()) ? [] : M.actionList(this.actionState());
        var key = JSON.stringify(list);

        if (!this.actWrap) {
            this.actBar = el('div', { class: 'pgie-acts', role: 'toolbar', 'aria-label': t('actBar') });
            this.actWrap = el('div', { class: 'pgie-actwrap' }, [this.actBar]);
            // The bar sits on the stage: a press, a double click or a wheel
            // on it is its own, not the tool's (select, draw, zoom).
            ['pointerdown', 'pointerup', 'dblclick', 'wheel'].forEach(function (type) {
                self.actBar.addEventListener(type, function (e) { e.stopPropagation(); });
            });
            this.stage.appendChild(this.actWrap);
        }

        if (key !== this.actKey) {
            this.actKey = key;
            this.actBar.textContent = '';

            list.forEach(function (item) {
                var c = COMMANDS[item.name], b = button('pgie-act' + (c[3] ? ' pgie-act-danger' : ''), t(c[1]), c[0], function () {
                    c[2].call(self);
                }, true);

                b.disabled = !!item.off;
                self.actBar.appendChild(b);
            });
        }

        var on = list.length > 0;

        this.actWrap.classList.toggle('on', on && !this.drag);

        if (on) {
            this.placeActions();
        }
    };

    // Above an open sheet that overlaps the stage's bottom (a phone held
    // upright), left of one over its right side, above the Animate tool's
    // bar along its bottom.
    Editor.prototype.placeActions = function () {
        var s = this.stage.getBoundingClientRect(), bottom = this.stageInset || 0;

        if (this.panel && this.sheetOpen) {
            var p = this.panel.getBoundingClientRect();

            if ((p.height > 0) && (p.top > s.top) && (p.top < s.bottom) && (p.left <= s.left + 1)) {
                bottom = Math.max(bottom, Math.round(s.bottom - p.top));
            }
        }

        this.actWrap.style.setProperty('--pgie-act-bottom', bottom + 'px');
        this.actWrap.style.setProperty('--pgie-act-right', this.panelCover() + 'px');
    };

    Editor.views.push(Editor.prototype.renderActions);
}(window.PgImageEditor.parts));
