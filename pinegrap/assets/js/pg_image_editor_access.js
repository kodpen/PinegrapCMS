/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: access: tool letters, the keyboard help sheet, the shortcut
 * names on the controls, Tab kept inside the editor and its boxes, and the
 * two-finger twist that turns the selected layer.
 *
 * A part of window.PgImageEditor, bundled last after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button;

    X.icons.keyboard = 'M3 6.5h18v11H3zM6.5 10h.01M9.5 10h.01M12.5 10h.01M15.5 10h.01M17.5 10h.01M7 14h10';

    var FOCUSABLE = 'button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])';
    var MAC = (typeof navigator !== 'undefined') && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '');

    // A key token of M.keyMap() as the sheet shows it.
    function keyName(k) {
        if (k === 'Mod') {
            return MAC ? '⌘' : 'Ctrl';
        }

        return { Space: t('keySpace'), Delete: t('keyDeleteName'), Arrows: t('keyArrows'), Drag: t('keyDrag') }[k] || k;
    }

    // The aria-keyshortcuts form of a key list: Control or Meta for Mod.
    function ariaKeys(keys) {
        return keys.map(function (k) { return (k === 'Mod') ? (MAC ? 'Meta' : 'Control') : k; }).join('+');
    }

    // The visible controls of box that take the focus, in order.
    function focusables(box) {
        return Array.prototype.filter.call(box.querySelectorAll(FOCUSABLE), function (n) {
            return !n.disabled && !n.hidden && (n.offsetParent !== null || n === document.activeElement);
        });
    }

    // ---- names and shortcuts on the controls ----

    // A tool button's name says its letter, and screen readers hear the
    // shortcuts of the rail, undo, redo and save.
    Editor.prototype.nameControls = function () {
        var self = this, list = this.tools.list;

        Object.keys(this.toolButtons || {}).forEach(function (id) {
            var b = self.toolButtons[id], k = M.toolKey(id, list);

            if (k && !b.hasAttribute('aria-keyshortcuts')) {
                b.title = t('tool_' + id) + ' (' + k + ')';
                b.setAttribute('aria-keyshortcuts', k);
            }
        });

        [[this.undoBtn, ['Mod', 'Z']], [this.redoBtn, ['Mod', 'Shift', 'Z']], [this.saveBtn, ['Mod', 'S']]].forEach(function (p) {
            if (p[0] && !p[0].hasAttribute('aria-keyshortcuts')) {
                p[0].setAttribute('aria-keyshortcuts', ariaKeys(p[1]));
            }
        });

        if (this.stage && !this.stage.getAttribute('aria-label')) {
            this.stage.setAttribute('aria-label', t('stageLabel'));
        }
    };

    // ---- the help sheet ----

    Editor.prototype.openHelp = function () {
        var self = this;

        if (this.helpBox || this.confirmBox) {
            return;
        }

        this.helpReturn = document.activeElement;

        var close = button('pgie-ibtn sm', t('close'), 'close', function () { self.closeHelp(); });
        var body = el('div', { class: 'pgie-keys' });

        M.keyMap(this.tools.list).forEach(function (g) {
            var dl = el('dl', { class: 'pgie-keylist' });

            g.rows.forEach(function (r) {
                var keys = el('dt');

                r.keys.forEach(function (k, i) {
                    if (i) {
                        keys.appendChild(document.createTextNode(' + '));
                    }

                    keys.appendChild(el('kbd', { text: keyName(k) }));
                });

                dl.appendChild(keys);
                dl.appendChild(el('dd', { text: t(r.label) }));
            });

            body.appendChild(el('section', { class: 'pgie-keygroup' }, [el('h3', { text: t(g.title) }), dl]));
        });

        var box = el('div', { class: 'pgie-confirm-box pgie-helpbox' }, [
            el('div', { class: 'pgie-ph' }, [el('h2', { id: 'pgie-help-title', text: t('keysTitle') }), el('div', { class: 'pgie-grow' }), close]),
            body
        ]);

        this.helpBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-help-title' }, [box]);
        // A press beside the sheet closes it.
        this.helpBox.addEventListener('pointerdown', function (e) {
            if (e.target === self.helpBox) {
                self.closeHelp();
            }
        });
        this.root.appendChild(this.helpBox);
        close.focus();
    };

    Editor.prototype.closeHelp = function () {
        if (!this.helpBox) {
            return false;
        }

        this.helpBox.parentNode.removeChild(this.helpBox);
        this.helpBox = null;

        var back = this.helpReturn;

        this.helpReturn = null;
        ((back && back.isConnected && !back.disabled) ? back : this.stage).focus({ preventScroll: true });

        return true;
    };

    // ---- Tab ----

    // Tab goes round inside the editor, and inside the help sheet or the
    // unsaved-changes question while one is open; the browser's own step
    // works between the ends. True when it moved the focus.
    Editor.prototype.trapTab = function (e) {
        var box = this.helpBox || this.confirmBox || this.root;
        var list = focusables(box), i = list.indexOf(document.activeElement);

        if (!list.length) {
            e.preventDefault();
            return true;
        }

        // From the stage, or between the ends, the browser steps on its own.
        if ((i < 0) && (box === this.root) && box.contains(document.activeElement)) {
            return false;
        }

        if ((i >= 0) && (e.shiftKey ? i > 0 : i < list.length - 1)) {
            return false;
        }

        e.preventDefault();
        list[M.trapFocus(list.length, i, e.shiftKey)].focus();

        return true;
    };

    // ---- the two-finger twist ----

    // A second finger while the first moves one layer turns it with the
    // line between the fingers; no zoom or pan then. True when it started.
    Editor.prototype.startTwist = function (ids) {
        var d = this.drag;

        if (!d || (d.kind !== 'move') || (d.start.length !== 1) || (ids.length !== 2)) {
            return false;
        }

        var l = this.layer(d.start[0].id);

        if (!l || l.locked) {
            return false;
        }

        this.guides = null;
        this.drag = { kind: 'twist', id: l.id, rot: l.rot || 0, from: this.fingerAngle(ids) };

        return true;
    };

    Editor.prototype.fingerAngle = function (ids) {
        var a = this.pointers[ids[0]], b = this.pointers[ids[1]];

        return Math.atan2(b.y - a.y, b.x - a.x);
    };

    Editor.prototype.moveTwist = function () {
        var d = this.drag, ids = Object.keys(this.pointers);

        if (ids.length !== 2) {
            return;
        }

        var a = M.twistAngle(d.rot, d.from, this.fingerAngle(ids));

        this.change(function (doc) {
            var l = X.find(doc, d.id);

            if (l) {
                l.rot = a;
            }
        }, 'drag', true);
    };

    // ---- hooks ----

    Editor.inits.push(function () {
        this.helpBox = null;
        this.helpReturn = null;
    });

    Editor.footItems.push(function () {
        var self = this, b = button('pgie-ibtn sm', t('keysTitle'), 'keyboard', function () { self.openHelp(); });

        b.setAttribute('aria-keyshortcuts', '?');
        b.setAttribute('aria-haspopup', 'dialog');

        return b;
    });

    Editor.views.push(Editor.prototype.nameControls);

    // First in line: while the sheet is open only Tab, Enter and Space on
    // its buttons and Escape work; ? opens it; a letter picks a tool.
    Editor.keys.unshift(function (e, mod, k) {
        if (this.helpBox) {
            return (k !== 'Enter') && (k !== ' ');
        }

        if (mod || e.altKey) {
            return false;
        }

        if (k === '?') {
            this.openHelp();
            return true;
        }

        if (e.shiftKey || (k.length !== 1)) {
            return false;
        }

        var id = M.toolForKey(k, this.tools.list);

        if (!id) {
            return false;
        }

        if (id !== this.tool) {
            this.pickTool(id);
        }

        return true;
    });

    Editor.escapes.unshift(function () {
        return this.closeHelp();
    });
}(window.PgImageEditor.parts));
