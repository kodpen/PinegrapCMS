/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the context menu. A right click on the stage, a rail item
 * or a scene's card (a long press on touch, Shift+F10 or the menu key)
 * opens the commands of the tool and the selection there: the layers'
 * clipboard, order, alignment, lock and visibility and their type's own
 * commands, the picture's and an area's commands, the empty canvas's paste,
 * Import and fit, a group item's modes, a scene's moves. Text fields keep
 * the browser's own menu.
 *
 * A part of window.PgImageEditor, bundled last (pg_js_bundles()); it hangs
 * its code on the editor through PgImageEditor.parts
 * (docs/memory/ui/image-editor-parts.md). Which items show is the model's
 * M.menuList(), whose selection commands start from M.actionList().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el;

    // A touch held this long without moving opens the menu.
    var LONG_PRESS = 550, LONG_SLOP = 10;

    // A command: its label's key, what it does (this is the editor; ctx the
    // place the menu was opened for) and whether it removes something (drawn
    // in the danger colour). Items with a submenu have no action.
    var COMMANDS = {
        cut: ['ctxCut', function () {
            this.clip = this.selected().map(M.clone);
            this.removeLayers();
        }],
        copyLayers: ['ctxCopy', function () { this.clip = this.selected().map(M.clone); }],
        paste: ['ctxPaste', function () {
            this.toSelect();
            this.addLayers(this.clip, 10);
        }],
        duplicate: ['duplicate', function () { this.addLayers(this.selected(), 10); }],
        remove: ['delete', function () { this.removeLayers(); }, true],
        order: ['arrange'],
        front: ['bringFront', function () { this.arrangeLayers('top'); }],
        forward: ['bringForward', function () { this.arrangeLayers('up'); }],
        backward: ['sendBackward', function () { this.arrangeLayers('down'); }],
        back: ['sendBack', function () { this.arrangeLayers('bottom'); }],
        align: ['align'],
        alignLeft: ['alignLeft', function () { this.alignLayers('left'); }],
        alignCenter: ['alignCenter', function () { this.alignLayers('center'); }],
        alignRight: ['alignRight', function () { this.alignLayers('right'); }],
        alignTop: ['alignTop', function () { this.alignLayers('top'); }],
        alignMiddle: ['alignMiddle', function () { this.alignLayers('middle'); }],
        alignBottom: ['alignBottom', function () { this.alignLayers('bottom'); }],
        distX: ['distributeX', function () { this.spreadLayers('x'); }],
        distY: ['distributeY', function () { this.spreadLayers('y'); }],
        lock: ['lock', function () { this.flagLayers('locked', true); }],
        unlock: ['unlock', function () { this.flagLayers('locked', false); }],
        hide: ['hide', function () { this.flagLayers('hidden', true); }],
        show: ['show', function () { this.flagLayers('hidden', false); }],
        combine: ['pathfinder'],
        unite: ['pfUnite', function () { this.pathfinder('unite'); }],
        minusFront: ['pfMinusFront', function () { this.pathfinder('minusFront'); }],
        intersect: ['pfIntersect', function () { this.pathfinder('intersect'); }],
        exclude: ['pfExclude', function () { this.pathfinder('exclude'); }],
        divide: ['pfDivide', function () { this.pathfinder('divide'); }],
        outlines: ['outlineConvert', function () { this.convertToOutlines(); }],
        expandWarp: ['ctxExpandWarp', function () { this.expandWarp(this.selected()[0]); }],
        breakApart: ['svgBreakApart', function () { this.svgBreakApart(this.sel[0]); }],
        lockPicture: ['lock', function () { this.lockPicture(true); }],
        removePicture: ['delete', function () { this.deletePicture(); }, true],
        erase: ['areaErase', function () { this.areaErase(); }, true],
        cutToLayer: ['areaCut', function () { this.areaCut(); }],
        keep: ['areaKeep', function () { this.areaKeep(); }],
        copy: ['areaCopy', function () { this.areaCopy(); }],
        adjust: ['areaAdjust', function () { this.areaAdjust(); }],
        redact: ['areaRedact', function () { this.areaRedact(); }],
        invert: ['areaInvert', function () { this.areaInvert(); }],
        deselect: ['areaClear', function () { this.areaClear(); }],
        selectAll: ['selectAll', function () { this.selectAll(); }],
        import: ['import'],
        importComputer: ['importComputer', function () { this.pickImports(); }],
        importSite: ['importSite', function () { this.openImportFiles(); }],
        importFile: ['ctxImportFile', function () { this.pickImports(); }],
        canvasSize: ['canvasSize', function () {
            if (this.tool === 'artboard') {
                this.layersOpen = false;
                this.sheetOpen = true;
                this.render();
            } else {
                this.pickTool('artboard');
            }
        }],
        fit: ['zoomFit', function () { this.fit(); }],
        mode: [null, function (ctx, it) {
            if (it.mode !== this.tool) {
                this.pickTool(it.mode);
            }
        }],
        sceneDuplicate: ['sceneDuplicate', function () { this.addScene(true); }],
        sceneDelete: ['sceneDelete', function (ctx) {
            this.sceneChange(function (doc) { M.sceneDelete(doc, ctx.scene); });
        }, true],
        sceneEarlier: ['sceneEarlier', function (ctx) {
            this.sceneChange(function (doc) { M.sceneMove(doc, ctx.scene, -1); });
        }],
        sceneLater: ['sceneLater', function (ctx) {
            this.sceneChange(function (doc) { M.sceneMove(doc, ctx.scene, 1); });
        }]
    };

    Editor.inits.push(function () {
        this.ctx = null;
        this.ctxBound = false;
        this.ctxKeyAt = 0;
        this.ctxLongAt = 0;
        this.ctxHeld = null;
        this.ctxReleaseAt = 0;
        this.ctxPress = null;
    });

    // Every selected layer locked (or shown) when value is true, else
    // unlocked (or hidden): one undo step.
    Editor.prototype.flagLayers = function (key, value) {
        var ids = this.sel;

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (ids.indexOf(l.id) >= 0) {
                    l[key] = value;
                }
            });
        });
    };

    // What M.menuList() reads for the place the menu opens on.
    Editor.prototype.menuState = function (ctx) {
        var s = { at: ctx.at };

        if (ctx.at === 'rail') {
            s.modes = ctx.item.modes;
            s.tool = this.tool;

            return s;
        }

        if (ctx.at === 'scene') {
            var list = M.sceneList(this.doc);

            s.count = list.length;
            s.max = M.SCENE_MAX;
            s.index = list.findIndex(function (sc) { return sc.id === ctx.scene; });

            return s;
        }

        var a = this.actionState(), picked = (a.layers > 0) ? this.selected() : [];
        var free = picked.filter(function (l) { return !l.locked; }), one = (free.length === 1) && (picked.length === 1) ? free[0] : null;

        s.layers = a.layers;
        s.free = a.free;
        s.picture = a.picture;
        s.area = a.area;
        s.can = a.can;
        s.clip = !!(this.clip && this.clip.length);
        s.locked = picked.length > 0 && picked.every(function (l) { return !!l.locked; });
        s.hidden = picked.length > 0 && picked.every(function (l) { return !!l.hidden; });
        s.areas = (picked.length && this.pathfinderItems) ? this.pathfinderItems().length : 0;
        s.texts = (picked.length && this.convertToOutlines) ? this.selectedTexts().filter(function (l) {
            return !l.locked && String(l.text || '').trim();
        }).length : 0;
        s.warp = !!(one && this.expandWarp && (M.warpOn(one) || M.puppetOn(one)));
        s.svg = !!(one && this.svgBreakApart && (one.type === 'svg') && one.imported);
        s.count = this.doc.layers.length;
        s.imports = !!this.importBtn;
        s.site = s.imports && this.importApi();
        s.canvas = this.tools.list.indexOf('artboard') >= 0;

        return s;
    };

    // ---- opening and closing ----

    // The menu for ctx ({ at, item, scene }) at the screen point x, y; ret
    // takes the focus back when it closes without a command. No items: no
    // menu.
    Editor.prototype.openMenu = function (ctx, x, y, ret) {
        var self = this, items;

        this.closeMenu();

        if (!this.ready || this.failed || this.saving || this.confirmBox) {
            return;
        }

        if (this.saveMenu) {
            this.toggleSaveMenu(false);
        }

        if (this.importMenu) {
            this.toggleImportMenu(false);
        }

        items = M.menuList(this.menuState(ctx));

        if (!items.length) {
            return;
        }

        var menu = this.menuNode(items, ctx, t('ctxMenu'));

        if (!menu.querySelector('button')) {
            return;
        }

        this.ctx = { menu: menu, sub: null, opener: null, ret: ret || this.stage, at: ctx };
        this.root.appendChild(menu);
        this.placeMenu(menu, x, y);
        this.focusItem(menu, 0);

        this.ctxOutside = function (e) {
            if (self.ctx && !self.ctx.menu.contains(e.target) && !(self.ctx.sub && self.ctx.sub.contains(e.target))) {
                self.closeMenu();
            }
        };
        document.addEventListener('pointerdown', this.ctxOutside, true);
    };

    // focus true gives the focus back to where the menu was opened from.
    Editor.prototype.closeMenu = function (focus) {
        var c = this.ctx;

        if (!c) {
            return;
        }

        this.ctx = null;
        document.removeEventListener('pointerdown', this.ctxOutside, true);
        [c.sub, c.menu].forEach(function (m) {
            if (m && m.parentNode) {
                m.parentNode.removeChild(m);
            }
        });

        if (focus) {
            this.menuReturn(c);
        }
    };

    // The focus back where menu c was opened from: that element, a card
    // drawn again by then the open scene's, else the stage. Only when the
    // focus is lost, not when a command moved it (a box it opened).
    Editor.prototype.menuReturn = function (c) {
        var a = document.activeElement, back = (c.ret && c.ret.isConnected) ? c.ret : null;

        if (a && (a !== document.body) && a.isConnected && this.root.contains(a)) {
            return;
        }

        if (!back && (c.at.at === 'scene')) {
            back = this.openCard();
        }

        (back || this.stage).focus();
    };

    // A menu's element: a button per item (role menuitem, menuitemradio for
    // a mode), a separator line, a submenu's opener.
    Editor.prototype.menuNode = function (items, ctx, label) {
        var self = this, menu = el('div', { class: 'pgie-menu pgie-ctx', role: 'menu', 'aria-label': label });
        var strings = X.config().strings || {};

        // A name without a command here (a later command of the selection
        // bar) is left out, and so is a separator it leaves at an end or
        // beside another.
        items = items.filter(function (it) { return it.sep || COMMANDS[it.name]; }).filter(function (it, i, list) {
            return !it.sep || ((i > 0) && (i < list.length - 1) && !list[i + 1].sep);
        });

        items.forEach(function (it) {
            if (it.sep) {
                menu.appendChild(el('div', { class: 'pgie-ctxsep', role: 'separator' }));
                return;
            }

            var c = COMMANDS[it.name], text = c[0] ? t(c[0]) : t((strings['mode_' + it.mode] !== undefined) ? 'mode_' + it.mode : 'tool_' + it.mode);
            var b = el('button', {
                type: 'button',
                class: (c[2] ? 'pgie-ctx-danger' : '') + (it.sub ? ' pgie-ctxopen' : ''),
                role: it.mode ? 'menuitemradio' : 'menuitem',
                text: text
            });

            if (it.mode) {
                b.setAttribute('aria-checked', it.on ? 'true' : 'false');
            }

            if (it.off) {
                b.setAttribute('aria-disabled', 'true');
            }

            if (it.sub) {
                b.setAttribute('aria-haspopup', 'menu');
                b.setAttribute('aria-expanded', 'false');
                b.addEventListener('pointerenter', function (e) {
                    if (e.pointerType === 'mouse') {
                        self.openSub(b, it, ctx, false);
                    }
                });
            } else {
                // Another item of the main menu closes an open submenu.
                b.addEventListener('pointerenter', function (e) {
                    if ((e.pointerType === 'mouse') && self.ctx && self.ctx.sub && self.ctx.menu.contains(b)) {
                        self.closeSub(false);
                    }
                });
            }

            b.addEventListener('click', function () {
                if (it.off) {
                    return;
                }

                if (it.sub) {
                    self.openSub(b, it, ctx, true);
                    return;
                }

                self.closeMenu(true);
                c[1].call(self, ctx, it);

                // A scene's command draws the strip again, and the redraw
                // gives the lost focus to the stage: the open card takes it.
                if (ctx.at === 'scene') {
                    window.setTimeout(function () {
                        var card = self.openCard();

                        if (card && (document.activeElement === self.stage)) {
                            card.focus();
                        }
                    }, 0);
                }
            });
            menu.appendChild(b);
        });

        menu.addEventListener('keydown', function (e) { self.menuKey(e, menu); });
        // The menu's own presses and wheel stay off the stage under it.
        ['pointerdown', 'pointerup', 'dblclick', 'wheel', 'contextmenu'].forEach(function (type) {
            menu.addEventListener(type, function (e) {
                e.stopPropagation();

                if (type === 'contextmenu') {
                    e.preventDefault();
                }
            });
        });

        return menu;
    };

    // The submenu of opener's item beside it; focus true moves the focus
    // to its first item (a key, a press), a mouse over it does not.
    Editor.prototype.openSub = function (opener, it, ctx, focus) {
        var c = this.ctx;

        if (!c) {
            return;
        }

        if (c.opener === opener) {
            if (focus) {
                this.focusItem(c.sub, 0);
            }

            return;
        }

        this.closeSub(false);

        if (it.off) {
            return;
        }

        c.sub = this.menuNode(it.sub, ctx, opener.textContent);
        c.sub.classList.add('pgie-ctxsub');
        c.opener = opener;
        opener.setAttribute('aria-expanded', 'true');
        this.root.appendChild(c.sub);

        var r = opener.getBoundingClientRect(), w = c.sub.offsetWidth, vw = document.documentElement.clientWidth;

        // Right of the opener, else left of it, else under it (a phone).
        if (r.right + w <= vw - 8) {
            this.placeMenu(c.sub, r.right - 4, r.top - 6);
        } else if (r.left - w >= 8) {
            this.placeMenu(c.sub, r.left - w + 4, r.top - 6);
        } else {
            this.placeMenu(c.sub, r.left + 16, r.bottom);
        }

        if (focus) {
            this.focusItem(c.sub, 0);
        }
    };

    Editor.prototype.closeSub = function (focus) {
        var c = this.ctx;

        if (!c || !c.sub) {
            return;
        }

        c.sub.parentNode.removeChild(c.sub);
        c.sub = null;
        c.opener.setAttribute('aria-expanded', 'false');

        if (focus) {
            c.opener.focus();
        }

        c.opener = null;
    };

    // At x, y, kept inside the window (moved left or up where it would
    // pass the edge).
    Editor.prototype.placeMenu = function (menu, x, y) {
        var vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
        var w = menu.offsetWidth, h = menu.offsetHeight;

        menu.style.left = Math.max(8, Math.min(x, vw - w - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(y, vh - h - 8)) + 'px';
    };

    Editor.prototype.focusItem = function (menu, at) {
        var list = menu.querySelectorAll('button');

        if (list.length) {
            list[(at + list.length) % list.length].focus();
        }
    };

    // The keys inside a menu: the arrows move (Right opens a submenu, Left
    // and Escape close it), Home and End, Escape and Tab close the menu.
    // Enter and Space press the focused item as on any button. The editor's
    // own keys stay out.
    Editor.prototype.menuKey = function (e, menu) {
        var list = Array.prototype.slice.call(menu.querySelectorAll('button')), at = list.indexOf(document.activeElement);
        var k = e.key, sub = this.ctx && (menu === this.ctx.sub), cur = list[at];

        e.stopPropagation();

        if ((k === 'ArrowDown') || (k === 'ArrowUp')) {
            e.preventDefault();
            this.focusItem(menu, (at < 0) ? 0 : at + ((k === 'ArrowDown') ? 1 : -1));
        } else if ((k === 'Home') || (k === 'End')) {
            e.preventDefault();
            this.focusItem(menu, (k === 'Home') ? 0 : -1);
        } else if ((k === 'ArrowRight') && cur && cur.classList.contains('pgie-ctxopen')) {
            e.preventDefault();
            cur.click();
        } else if (((k === 'ArrowLeft') || (k === 'Escape')) && sub) {
            e.preventDefault();
            this.closeSub(true);
        } else if ((k === 'Escape') || (k === 'Tab')) {
            e.preventDefault();
            this.closeMenu(true);
        } else if ((k === 'ContextMenu') || ((k === 'F10') && e.shiftKey)) {
            e.preventDefault();
        }
    };

    // ---- where the menu opens ----

    // The open scene's card in the strip, or null.
    Editor.prototype.openCard = function () {
        return this.sceneDock ? this.sceneDock.querySelector('.pgie-sopen') : null;
    };

    // ctx for an element of the editor: a rail item, a scene's card or the
    // stage; null elsewhere (the panel, the top bar), and in a text field.
    Editor.prototype.menuPlace = function (target) {
        var self = this, card, item = null;

        if (!target || !target.closest || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName || '') || target.isContentEditable) {
            return null;
        }

        if (this.rail && this.rail.contains(target) && !this.tools.mini) {
            (this.railItems || []).forEach(function (it) {
                var b = self.toolButtons[it.id];

                if (b && b.contains(target)) {
                    item = it;
                }
            });

            return item ? { at: 'rail', item: item, el: this.toolButtons[item.id] } : null;
        }

        card = target.closest('.pgie-scard');

        if (card && card.getAttribute('data-scene')) {
            return { at: 'scene', scene: card.getAttribute('data-scene'), el: card };
        }

        if (this.stage.contains(target) && !(this.actBar && this.actBar.contains(target))) {
            return { at: 'stage', el: this.stage };
        }

        return null;
    };

    // A press for the menu on the stage at e: a layer under it is selected
    // first as Select would (a tool that does not select layers gives way
    // to Select there), an empty spot clears the selection; Select area
    // keeps its area. pick false: the tool's own press already did.
    Editor.prototype.menuPick = function (e, pick) {
        var p, hit;

        if (!pick || (this.tool === 'area') || this.cropping()) {
            return;
        }

        if (Editor.selectTools.indexOf(this.tool) < 0) {
            p = this.toDoc(e.clientX, e.clientY);
            hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k);

            if (!hit) {
                return;
            }

            this.tool = 'select';
        }

        Editor.pointers.select.down.call(this, {
            clientX: e.clientX, clientY: e.clientY, target: e.target, shiftKey: false, button: 0, pointerId: -1,
            preventDefault: function () {}
        });
        this.endDrag();
        this.render();
    };

    // The menu for ctx from a pointer at e (a right click, a long press).
    Editor.prototype.menuAt = function (ctx, e, pick) {
        if (ctx.at === 'stage') {
            if (this.cropping()) {
                return;
            }

            this.menuPick(e, pick);
        } else if ((ctx.at === 'scene') && (ctx.scene !== this.doc.scene)) {
            // The scene's commands work on the open scene.
            this.openScene(ctx.scene);
            ctx.el = this.openCard();
        }

        this.openMenu(ctx, e.clientX, e.clientY, ctx.el);
    };

    // Shift+F10 or the menu key: the menu of the focused rail item or card,
    // else of the stage under the selection's middle (the stage's middle
    // without one).
    Editor.prototype.menuFromKey = function () {
        var ctx = this.menuPlace(document.activeElement) || { at: 'stage', el: this.stage }, r, x, y;

        if (ctx.at === 'stage') {
            if (this.cropping()) {
                return;
            }

            r = this.stage.getBoundingClientRect();
            x = r.left + r.width / 2;
            y = r.top + r.height / 2;

            if (this.sel.length && this.selEl) {
                var b = this.selEl.getBoundingClientRect();

                if (b.width || b.height) {
                    x = b.left + b.width / 2;
                    y = b.top + b.height / 2;
                }
            }
        } else {
            r = ctx.el.getBoundingClientRect();
            x = r.left + Math.min(24, r.width / 2);
            y = r.bottom;

            if ((ctx.at === 'scene') && (ctx.scene !== this.doc.scene)) {
                this.openScene(ctx.scene);
                ctx.el = this.openCard();
            }
        }

        this.ctxKeyAt = Date.now();
        this.openMenu(ctx, x, y, ctx.el || null);
    };

    Editor.keys.push(function (e, mod, k) {
        if (!mod && ((k === 'ContextMenu') || ((k === 'F10') && e.shiftKey))) {
            this.menuFromKey();
            return true;
        }

        return false;
    });

    // Escape with the menu open (its keys are its own; a press outside
    // leaves the focus elsewhere) closes it first.
    Editor.escapes.unshift(function () {
        if (this.ctx) {
            this.closeMenu(true);
            return true;
        }

        return false;
    });

    // ---- the browser's events ----

    // The listeners, once the dialog is there: the browser's contextmenu
    // (a right click; the menu key and Shift+F10 where the key itself did
    // not reach the editor), a long press of a finger, and the click a
    // finger's release after a long press makes.
    Editor.prototype.bindMenu = function () {
        var self = this, d = this.dialog;

        d.addEventListener('contextmenu', function (e) {
            var ctx = self.menuPlace(e.target);

            if (!ctx) {
                return;
            }

            e.preventDefault();

            // The key's own menu, or a long press already handled.
            if ((Date.now() - self.ctxKeyAt < 1000) || (Date.now() - self.ctxLongAt < 1500) || self.ctxPress) {
                return;
            }

            if (!e.clientX && !e.clientY) {
                self.menuFromKey();
                return;
            }

            self.menuAt(ctx, e, true);
        });

        d.addEventListener('pointerdown', function (e) {
            var ctx;

            // A press after the menu key: its menu is no longer coming.
            self.ctxKeyAt = 0;
            self.cancelPress();

            if ((e.pointerType !== 'touch') || self.ctx) {
                return;
            }

            ctx = self.menuPlace(e.target);

            if (!ctx || ((ctx.at === 'stage') && !self.pressOpens())) {
                return;
            }

            self.ctxPress = { id: e.pointerId, x: e.clientX, y: e.clientY, e: e, ctx: ctx, timer: window.setTimeout(function () {
                var p = self.ctxPress;

                self.ctxPress = null;

                if (!p || (Object.keys(self.pointers).length > 1)) {
                    return;
                }

                // The tool's press ends where it is: the layer it took
                // stays selected, nothing moved.
                if ((p.ctx.at === 'stage') && self.drag && Editor.pointers[self.tool]) {
                    Editor.pointers[self.tool].up.call(self, p.e);
                }

                self.ctxLongAt = Date.now();
                self.ctxHeld = p.id;
                self.menuAt(p.ctx, p.e, false);
            }, LONG_PRESS) };
        }, true);

        d.addEventListener('pointermove', function (e) {
            var p = self.ctxPress;

            if (p && ((p.id !== e.pointerId) || (Math.hypot(e.clientX - p.x, e.clientY - p.y) > LONG_SLOP))) {
                self.cancelPress();
            }
        }, true);

        ['pointerup', 'pointercancel'].forEach(function (type) {
            d.addEventListener(type, function (e) {
                self.cancelPress();

                if (e.pointerId === self.ctxHeld) {
                    self.ctxHeld = null;
                    self.ctxReleaseAt = Date.now();
                }
            }, true);
        });

        // The release of a long press is not a press of what lies under the
        // finger: the rail item or card it held, or the menu's item that
        // opened under it.
        d.addEventListener('click', function (e) {
            if (Date.now() - self.ctxReleaseAt < 400) {
                self.ctxReleaseAt = 0;
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    };

    Editor.prototype.cancelPress = function () {
        if (this.ctxPress) {
            window.clearTimeout(this.ctxPress.timer);
            this.ctxPress = null;
        }
    };

    // A long press on the stage opens the menu with Select, the tools that
    // select as it does and Select area; elsewhere a held finger draws or
    // types.
    Editor.prototype.pressOpens = function () {
        return !this.cropping() && ((Editor.pointers[this.tool] === Editor.pointers.select) || (this.tool === 'area'));
    };

    // Bound on the first redraw, when the dialog exists; a redraw that
    // drops the place the menu was for (a failed open, a save) closes it.
    Editor.views.push(function () {
        if (!this.ctxBound && this.dialog) {
            this.ctxBound = true;
            this.bindMenu();
        }

        if (this.ctx && (this.failed || this.saving)) {
            this.closeMenu(false);
        }
    });
}(window.PgImageEditor.parts));
