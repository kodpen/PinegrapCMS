/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the selection's commands as a bar over the stage where the
 * panel is a sheet (a phone, a narrow or short window). A touch screen has
 * no Delete key, and a closed sheet hides the panel's buttons, so selected
 * layers, the selected picture and an area drawn with Select area each get
 * their main commands there, with Options opening the sheet for the rest
 * (arrange, align, colours, feather). Where the panel is not a sheet the
 * bar is drawn over the selected layers instead, so a command does not
 * take the tool's panel away; More opens the context menu's list there.
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

    // Where the panel is a sheet: the stylesheet's small-screen layout.
    var SHEET = '(max-width: 767.98px), (max-height: 499.98px)';

    X.icons.more = 'M5 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0zM10.5 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0zM16 12a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0z';
    X.icons.invert = 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 3.5v17M12 8.5l6.6-3.4M12 13l8.3-4.2M12 17.5l7.4-3.8';
    X.icons.nudgeLeft = 'M19 12H5M11 6l-6 6 6 6';
    X.icons.nudgeRight = 'M5 12h14M13 6l6 6-6 6';
    X.icons.nudgeUp = 'M12 19V5M6 11l6-6 6 6';
    X.icons.nudgeDown = 'M12 5v14M6 13l6 6 6-6';

    // A nudge arrow held this long moves 10 px, then again at every repeat
    // while it stays down; a shorter press is a click: 1 px.
    var NUDGE_HOLD = 400, NUDGE_REPEAT = 120;

    // A command: its icon, its label's key, what it does (this is the
    // editor), whether it removes something (drawn in the danger colour)
    // and, where it differs, its label's key on the bar over the object.
    var COMMANDS = {
        options: ['more', 'actOptions', function () {
            this.layersOpen = false;
            this.sheetOpen = true;
            this.render();
        }],
        removePin: ['puppet', 'puppetRemove', function () { this.removeChosenPin(); }, true],
        // The phone's bar says Split (its approved label); the bar over the
        // object uses the clip part's own Split clip, as the panel and the
        // play bar do.
        split: ['scissors', 'actSplit', function () { this.clipSplitHere(this.selected()[0].id); }, false, 'clipSplit'],
        // Group and Ungroup (pg_image_editor_layers.js), as Ctrl/Cmd+G and
        // Ctrl/Cmd+Shift+G do.
        group: ['group', 'groupLayers', function () { this.groupLayers(); }],
        ungroup: ['ungroup', 'ungroupLayers', function () { this.ungroupLayers(); }],
        duplicate: ['duplicate', 'duplicate', function () { this.addLayers(this.selected(), 10); }],
        remove: ['trash', 'delete', function () { this.removeLayers(); }, true],
        lockPicture: ['lock', 'lock', function () { this.lockPicture(true); }],
        pictureLayer: ['layers', 'pictureLayer', function () { this.pictureToLayer(); }],
        removePicture: ['trash', 'delete', function () { this.deletePicture(); }, true],
        // The arrows: icons only, a click 1 px, held 10 px (nudgeHold()).
        nudgeLeft: ['nudgeLeft', 'actNudgeLeft', function (b) { this.nudgeClick(b, 'left'); }],
        nudgeUp: ['nudgeUp', 'actNudgeUp', function (b) { this.nudgeClick(b, 'up'); }],
        nudgeDown: ['nudgeDown', 'actNudgeDown', function (b) { this.nudgeClick(b, 'down'); }],
        nudgeRight: ['nudgeRight', 'actNudgeRight', function (b) { this.nudgeClick(b, 'right'); }],
        erase: ['eraser', 'areaErase', function () { this.areaErase(); }, true],
        keep: ['crop', 'areaKeep', function () { this.areaKeep(); }],
        copy: ['layers', 'areaCopy', function () { this.areaCopy(); }],
        cutToLayer: ['scissors', 'areaCut', function () { this.areaCut(); }],
        adjust: ['adjust', 'areaAdjust', function () { this.areaAdjust(); }],
        redact: ['redact', 'areaRedact', function () { this.areaRedact(); }],
        invert: ['invert', 'areaInvert', function () { this.areaInvert(); }],
        deselect: ['close', 'areaClear', function () { this.areaClear(); }],
        // The bar over the object.
        editText: ['text', 'actEditText', function () { this.startTextEdit(this.selected()[0]); }],
        motion: ['motion', 'actMotion', function (b) {
            if (this.motionPop) {
                this.closeMotionPop(true);
            } else {
                this.openMotionPop(b);
            }
        }],
        // Follow (pg_image_editor_follow.js): arms the box over the video.
        follow: ['follow', 'followFollow', function () { this.followToggle(); }],
        lock: ['lock', 'lock', function () { this.lockSelected(true); }],
        unlock: ['unlock', 'unlock', function () { this.lockSelected(false); }],
        more: ['more', 'actMore', function (b) {
            var r = b.getBoundingClientRect();

            this.openMenu({ at: 'stage' }, r.left, r.bottom + 4, b);
        }]
    };

    Editor.inits.push(function () {
        this.actWrap = null;
        this.actBar = null;
        this.actKey = null;
    });

    // What is selected now, as M.actionList() reads it. The area's pixel
    // commands count only where the area part offers them, Convert to a
    // layer where the server can draw the picture (pictureLayerOn()).
    Editor.prototype.actionState = function () {
        var picking = (Editor.selectTools.indexOf(this.tool) >= 0) || (!!this.selectShown && this.selectShown()), picked = picking ? this.selected() : [];

        return {
            // Over the object where the panel is not a sheet; R18's other
            // moments (playing, recording) hide it there.
            place: this.actObject() ? 'object' : 'stage',
            kind: (picked.length === 1) ? picked[0].type : '',
            animate: this.tool === 'motion',
            layers: picked.length,
            free: picked.filter(function (l) { return !l.locked; }).length,
            // Group for several layers, Ungroup for a picked group.
            group: (picked.length > 1) && !!this.groupState && this.groupState().group,
            ungroup: (picked.length > 1) && !!this.groupState && this.groupState().ungroup,
            // The ones a nudge moves: an erased or adjusted area stays.
            move: picked.filter(function (l) { return !l.locked && !(Editor.layerTypes[l.type] || {}).fixed; }).length,
            picture: !!(this.board && this.board.pic && this.pictureOn && this.pictureOn()),
            area: (this.tool === 'area') && !!this.areaSelected && this.areaSelected(),
            pin: !!this.chosenPin && (this.chosenPin() !== null),
            sheet: !!this.panel && !this.sheetOpen,
            // One clip in the Animate tool: Split at the playhead.
            split: (this.tool === 'motion') && (picked.length === 1) && (picked[0].type === 'video') && !!this.clipSplitHere,
            // A layer Follow may move while a video shows at the playhead.
            follow: !!this.followOffered && this.followOffered(),
            can: {
                erase: !!this.areaErase, keep: !!this.areaKeep, copy: !!this.areaCopy, cutToLayer: !!this.areaCut, adjust: !!this.areaAdjust,
                redact: !!this.newRedaction, pictureLayer: !!this.pictureLayerOn && this.pictureLayerOn()
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
            this.actBar.addEventListener('keydown', function (e) { self.actKeyDown(e); });
            this.stage.appendChild(this.actWrap);
        }

        var object = this.actObject();

        key += object ? '|object' : '';

        if (key !== this.actKey) {
            this.actKey = key;
            this.actBar.textContent = '';

            list.forEach(function (item) {
                var arrow = item.name.indexOf('nudge') === 0;
                var c = COMMANDS[item.name], b = button('pgie-act' + (c[3] ? ' pgie-act-danger' : '') + (arrow ? ' pgie-actnudge' : ''), t((object && c[4]) || c[1]), c[0], function () {
                    c[2].call(self, b);
                }, !arrow);

                b.disabled = !!item.off;
                b.setAttribute('data-act', item.name);

                if (arrow) {
                    self.nudgeHold(b, item.name.slice(5).toLowerCase());
                }

                self.actBar.appendChild(b);
            });
        }

        var on = list.length > 0;

        this.actWrap.classList.toggle('pgie-actobj', object);
        this.actWrap.classList.toggle('on', on && !this.drag && !(object && ((this.playsAll && this.playsAll()) || this.recording || this.follow)));

        if (on && object) {
            this.placeObjectBar();
        } else if (on) {
            this.placeActions();
        }
    };

    // Whether the bar goes over the object: the panel is not a sheet.
    Editor.prototype.actObject = function () {
        return !(window.matchMedia && window.matchMedia(SHEET).matches);
    };

    // Over the selection's boxes as they are drawn (a turned layer, a
    // layer shown away at the playhead), above them or below where there
    // is no room, inside the stage and above the play bar and the tracks
    // (M.actBarPlace()). No box drawn (a tool that does not show the
    // selection, the key not held): no bar.
    Editor.prototype.placeObjectBar = function () {
        var boxes = this.selEl ? this.selEl.querySelectorAll('.pgie-selbox') : [], s = this.stage.getBoundingClientRect();
        var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;

        Array.prototype.forEach.call(boxes, function (b) {
            var r = b.getBoundingClientRect();

            x0 = Math.min(x0, r.left);
            y0 = Math.min(y0, r.top);
            x1 = Math.max(x1, r.right);
            y1 = Math.max(y1, r.bottom);
        });

        if (!boxes.length || !(x1 > x0)) {
            this.actWrap.classList.remove('on');
            return;
        }

        var bar = this.actBar.getBoundingClientRect();
        var at = M.actBarPlace({ x: x0 - s.left, y: y0 - s.top, w: x1 - x0, h: y1 - y0 }, { w: bar.width, h: bar.height },
            { w: s.width - this.panelCover(), h: s.height, bottom: this.stageInset || 0 });

        this.actWrap.style.setProperty('--pgie-act-x', at.x + 'px');
        this.actWrap.style.setProperty('--pgie-act-y', at.y + 'px');
        this.actWrap.classList.toggle('pgie-actbelow', at.below);
    };

    // Keys on the bar: the arrows move along it, Home and End to its ends,
    // Escape back to the stage; none of them reaches the stage's keys
    // (an arrow there nudges the layer).
    Editor.prototype.actKeyDown = function (e) {
        var bs = Array.prototype.filter.call(this.actBar.querySelectorAll('button'), function (b) { return !b.disabled; });
        var i = bs.indexOf(document.activeElement), k = e.key, to = -1;

        if (k === 'Escape') {
            to = -2;
        } else if ((k === 'ArrowRight') || (k === 'ArrowDown')) {
            to = (i + 1) % bs.length;
        } else if ((k === 'ArrowLeft') || (k === 'ArrowUp')) {
            to = (i <= 0) ? bs.length - 1 : i - 1;
        } else if (k === 'Home') {
            to = 0;
        } else if (k === 'End') {
            to = bs.length - 1;
        } else {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (to === -2) {
            this.stage.focus();
        } else if (bs[to]) {
            bs[to].focus();
        }
    };

    // Lock or unlock the selected layers: one undo step.
    Editor.prototype.lockSelected = function (on) {
        var ids = this.sel.slice();

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (ids.indexOf(l.id) >= 0) {
                    l.locked = !!on;
                }
            });
        });
    };

    // A click on an arrow nudges 1 px (an Enter or a Space on it too); the
    // click a held press ends with has moved the layers already.
    Editor.prototype.nudgeClick = function (b, dir) {
        if (b.pgHeld && (Date.now() - b.pgHeld < 1000)) {
            b.pgHeld = 0;
            return;
        }

        b.pgHeld = 0;
        this.nudge(dir, false);
    };

    // An arrow held down: 10 px once it is held NUDGE_HOLD ms, again every
    // NUDGE_REPEAT ms until it is let go or the finger leaves it; all of it
    // one undo step with the nudges before it (nudge()).
    Editor.prototype.nudgeHold = function (b, dir) {
        var self = this, wait = 0, repeat = 0, held = false;
        var stop = function () {
            window.clearTimeout(wait);
            window.clearInterval(repeat);
            wait = 0;
            repeat = 0;
        };

        b.addEventListener('pointerdown', function (e) {
            if ((e.button > 0) || b.disabled) {
                return;
            }

            stop();
            held = false;
            b.pgHeld = 0;
            wait = window.setTimeout(function () {
                held = true;
                self.nudge(dir, true);
                repeat = window.setInterval(function () {
                    if (!b.isConnected || !self.nudge(dir, true)) {
                        stop();
                    }
                }, NUDGE_REPEAT);
            }, NUDGE_HOLD);
        });
        b.addEventListener('pointerup', function () {
            stop();
            b.pgHeld = held ? Date.now() : 0;
        });
        ['pointercancel', 'pointerleave'].forEach(function (type) {
            b.addEventListener(type, stop);
        });
        // A long press would open the browser's own menu over the bar.
        b.addEventListener('contextmenu', function (e) { e.preventDefault(); });
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
