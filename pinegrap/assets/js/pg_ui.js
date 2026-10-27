/**
 * Pinegrap - Enterprise Website Platform
 *
 * pgUI: the one place panel code reaches Bootstrap's JavaScript.
 *
 * The panel moves from Bootstrap 5.3 to 6 (docs/memory/plans/bootstrap-6-panel.md).
 * Bootstrap 6 renames Modal, Offcanvas and Dropdown to Dialog, Drawer and Menu,
 * builds the first two on the native <dialog>, renames their events
 * (*.bs.dialog, *.bs.drawer, *.bs.menu), ships as an ES module without the
 * jQuery plugins and returns promises from show() and hide(). Code that goes
 * through pgUI works on both versions, so the switch changes this file and
 * the markup, not every caller.
 *
 * Components are named by their Bootstrap 6 kind: 'dialog', 'drawer', 'menu',
 * 'tooltip', 'popover', 'collapse', 'tab', 'toast'. A target is an element, a
 * jQuery object or a selector.
 *
 *   pgUI.ready(fn)                    after Bootstrap's script has run
 *   pgUI.show('dialog', el[, opts])   a promise, settled when it is shown
 *   pgUI.hide / toggle                the same
 *   pgUI.get / find / dispose         instance (created / existing / gone)
 *   pgUI.isOpen(kind, el), anyOpen(kind)
 *   pgUI.on(el, kind, 'hidden', fn[, {once: true}])   both event names; returns off()
 *   pgUI.dialog.build({...})          dialog markup for the loaded version
 *   pgUI.overlayHost(el)              where a floating layer must be appended
 *   pgUI.dialog.show(el), pgUI.menu.hide(el), ...   the same per kind
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window, document) {
    'use strict';

    // Bootstrap 5 and 6 names of each component: the export and the event
    // namespace (shown.bs.<ev>), plus the class of the open state root.
    var KINDS = {
        dialog: { v5: 'Modal', v6: 'Dialog', ev5: 'modal', ev6: 'dialog' },
        drawer: { v5: 'Offcanvas', v6: 'Drawer', ev5: 'offcanvas', ev6: 'drawer' },
        menu: { v5: 'Dropdown', v6: 'Menu', ev5: 'dropdown', ev6: 'menu' },
        tooltip: { v5: 'Tooltip', v6: 'Tooltip', ev5: 'tooltip', ev6: 'tooltip' },
        popover: { v5: 'Popover', v6: 'Popover', ev5: 'popover', ev6: 'popover' },
        collapse: { v5: 'Collapse', v6: 'Collapse', ev5: 'collapse', ev6: 'collapse' },
        tab: { v5: 'Tab', v6: 'Tab', ev5: 'tab', ev6: 'tab' },
        toast: { v5: 'Toast', v6: 'Toast', ev5: 'toast', ev6: 'toast' },
        // Bootstrap 6 only: under 5 there is no constructor and get() is null.
        range: { v5: 'Range', v6: 'Range', ev5: 'range', ev6: 'range' }
    };

    // Kinds whose show() and hide() end with a shown/hidden event.
    var EVENTFUL = { dialog: true, drawer: true, menu: true, collapse: true, toast: true, tooltip: true, popover: true };

    // A v5 transition that never reports back (an element removed while it
    // was opening) must not leave a caller's promise pending for good.
    var SETTLE_AFTER = 1500;

    function lib() {
        return window.bootstrap || null;
    }

    // 6 when Bootstrap 6 is loaded (it has Dialog), 5 for Bootstrap 5, 0 when
    // no Bootstrap is on the page yet.
    function version() {
        var b = lib();
        if (!b) {
            return 0;
        }
        return b.Dialog ? 6 : 5;
    }

    function names(kind) {
        var k = KINDS[kind];
        if (!k) {
            throw new Error('pgUI: unknown component "' + kind + '"');
        }
        return k;
    }

    function ctor(kind) {
        var b = lib(), k = names(kind);
        if (!b) {
            return null;
        }
        return b[version() === 6 ? k.v6 : k.v5] || null;
    }

    function node(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target.jquery) {
            return target[0] || null;
        }
        return target;
    }

    // Options written for one version, read by the loaded one: Popper's
    // config became floatingConfig, Collapse lost `toggle`.
    function options(kind, opts) {
        var out = {}, key;
        opts = opts || {};
        for (key in opts) {
            if (Object.prototype.hasOwnProperty.call(opts, key)) {
                out[key] = opts[key];
            }
        }
        if (version() === 6) {
            if (out.popperConfig !== undefined && out.floatingConfig === undefined) {
                out.floatingConfig = out.popperConfig;
            }
            delete out.popperConfig;
            if (kind === 'collapse') {
                delete out.toggle;
            }
        } else if (out.floatingConfig !== undefined) {
            if (out.popperConfig === undefined) {
                out.popperConfig = out.floatingConfig;
            }
            delete out.floatingConfig;
        }
        return out;
    }

    function get(kind, target, opts) {
        var C = ctor(kind), el = node(target);
        if (!C || !el) {
            return null;
        }
        return C.getOrCreateInstance(el, options(kind, opts));
    }

    function find(kind, target) {
        var C = ctor(kind), el = node(target);
        if (!C || !el) {
            return null;
        }
        return C.getInstance(el) || null;
    }

    function dispose(kind, target) {
        var instance = find(kind, target);
        if (instance && typeof instance.dispose === 'function') {
            instance.dispose();
        }
    }

    // Open state as the markup shows it: a v6 <dialog> is open, a v5 modal,
    // offcanvas, menu or collapse carries .show.
    function isOpen(kind, target) {
        var el = node(target);
        names(kind);
        if (!el) {
            return false;
        }
        if (el.tagName === 'DIALOG') {
            return !!el.open;
        }
        if (kind === 'menu' && el.getAttribute && el.getAttribute('aria-expanded') !== null) {
            return el.getAttribute('aria-expanded') === 'true';
        }
        return !!(el.classList && el.classList.contains('show'));
    }

    function anyOpen(kind) {
        var selectors = {
            dialog: 'dialog.dialog[open], .modal.show',
            drawer: 'dialog.drawer[open], .offcanvas.show'
        };
        names(kind);
        return !!(selectors[kind] && document.querySelector(selectors[kind]));
    }

    /**
     * Listens to a component event under both versions' names: 'hidden' on a
     * dialog listens to hidden.bs.modal and hidden.bs.dialog. Native listeners
     * only: Bootstrap 6 fires no jQuery events. Returns a function that
     * removes the listener.
     */
    function on(target, kind, name, handler, opts) {
        var el = node(target), k = names(kind), types, once = !!(opts && opts.once), done = false;
        if (!el) {
            return function () {};
        }
        types = [name + '.bs.' + k.ev5];
        if (k.ev6 !== k.ev5) {
            types.push(name + '.bs.' + k.ev6);
        }
        function listener(event) {
            if (once) {
                if (done) {
                    return;
                }
                done = true;
                off();
            }
            return handler.call(this, event);
        }
        function off() {
            for (var i = 0; i < types.length; i++) {
                el.removeEventListener(types[i], listener);
            }
        }
        for (var i = 0; i < types.length; i++) {
            el.addEventListener(types[i], listener);
        }
        return off;
    }

    // Calls show/hide/toggle and returns a promise settled when the change is
    // done: Bootstrap 6 returns one; under 5 the shown/hidden event settles it.
    function run(kind, target, method, opts) {
        var instance = get(kind, target, opts), el = node(target), result, wanted;
        if (!instance || typeof instance[method] !== 'function') {
            return Promise.resolve(null);
        }
        if (version() === 6) {
            result = instance[method]();
            return (result && typeof result.then === 'function') ? result.then(function () { return instance; }) : Promise.resolve(instance);
        }
        wanted = (method === 'toggle') ? (isOpen(kind, el) ? 'hide' : 'show') : method;
        if (!EVENTFUL[kind] || (wanted === 'show') === isOpen(kind, el)) {
            instance[method]();
            return Promise.resolve(instance);
        }
        return new Promise(function (resolve) {
            var timer = null;
            var off = on(el, kind, wanted === 'show' ? 'shown' : 'hidden', function () {
                window.clearTimeout(timer);
                resolve(instance);
            }, { once: true });
            timer = window.setTimeout(function () {
                off();
                resolve(instance);
            }, SETTLE_AFTER);
            instance[method]();
        });
    }

    /**
     * Where a floating layer (toast, select2 dropdown, date picker, popover
     * container) is appended. Bootstrap 6 dialogs sit in the browser's top
     * layer and make the rest of the page inert, so a layer for something
     * inside an open dialog must be inside that dialog. Under Bootstrap 5
     * z-index still works and the body stays the host.
     */
    function overlayHost(target) {
        var el = node(target), open;
        if (version() !== 6) {
            return document.body;
        }
        if (el && el.closest) {
            open = el.closest('dialog[open]');
            if (open) {
                return open;
            }
        }
        open = document.querySelectorAll('dialog[open]');
        return open.length ? open[open.length - 1] : document.body;
    }

    function make(tag, classes, attrs) {
        var el = document.createElement(tag), key;
        if (classes) {
            el.className = classes;
        }
        for (key in attrs || {}) {
            if (Object.prototype.hasOwnProperty.call(attrs, key)) {
                el.setAttribute(key, attrs[key]);
            }
        }
        return el;
    }

    /**
     * Dialog markup for the loaded version, not yet in the document. Options:
     * id, title (text), size ('sm'|'lg'|'xl'|'fullscreen'), scrollable,
     * centered (Bootstrap 5 only; a v6 dialog is centred), backdrop ('static'
     * or false), keyboard (false), className, closeLabel (the close button's
     * translated label), footer (false leaves it out). Returns the parts:
     * {root, header, title, body, footer, close}; the caller fills body and
     * footer, appends root and shows it with pgUI.show('dialog', root).
     */
    function buildDialog(opts) {
        var o = opts || {}, k = KINDS.dialog, six = version() === 6, parts = {}, box, size;
        size = o.size ? (six ? 'dialog-' : 'modal-') + o.size : '';

        if (six) {
            parts.root = make('dialog', ['dialog', size, o.centered ? 'pg-dialog-centered' : '', o.scrollable ? 'dialog-scrollable' : '', o.className || ''].join(' ').replace(/\s+/g, ' ').trim());
            box = parts.root;
        } else {
            parts.root = make('div', ['modal', 'fade', o.className || ''].join(' ').trim(), { tabindex: '-1', 'aria-hidden': 'true' });
            var shell = make('div', ['modal-dialog', size, o.centered ? 'modal-dialog-centered' : '', o.scrollable ? 'modal-dialog-scrollable' : ''].join(' ').replace(/\s+/g, ' ').trim());
            box = make('div', 'modal-content');
            shell.appendChild(box);
            parts.root.appendChild(shell);
        }

        if (o.id) {
            parts.root.id = o.id;
        }
        if (o.backdrop !== undefined) {
            parts.root.setAttribute('data-bs-backdrop', String(o.backdrop));
        }
        if (o.keyboard === false) {
            parts.root.setAttribute('data-bs-keyboard', 'false');
        }

        var prefix = six ? 'dialog' : 'modal';
        parts.header = make('div', prefix + '-header');
        parts.title = make('h5', prefix + '-title');
        if (o.title) {
            parts.title.textContent = o.title;
        }
        parts.close = make('button', 'btn-close', { type: 'button' });
        parts.close.setAttribute('data-bs-dismiss', six ? k.ev6 : k.ev5);
        if (o.closeLabel) {
            parts.close.setAttribute('aria-label', o.closeLabel);
        }
        parts.header.appendChild(parts.title);
        parts.header.appendChild(parts.close);
        parts.body = make('div', prefix + '-body');
        box.appendChild(parts.header);
        box.appendChild(parts.body);

        parts.footer = null;
        if (o.footer !== false) {
            parts.footer = make('div', prefix + '-footer');
            box.appendChild(parts.footer);
        }

        return parts;
    }

    // Resolves once Bootstrap's script has run. Bootstrap 5 is a classic
    // script before this one; Bootstrap 6 is a deferred module whose bridge
    // fires pg:bootstrap-ready (or resolves the promise it set up first).
    if (!window.pgBootstrapReady) {
        window.pgBootstrapReady = lib() ? Promise.resolve(lib()) : new Promise(function (resolve) {
            document.addEventListener('pg:bootstrap-ready', function (event) {
                resolve((event && event.detail) || lib());
            }, { once: true });
        });
    }

    function ready(fn) {
        return window.pgBootstrapReady.then(fn);
    }

    // Wrappers given to bubbleRange(), so a redrawn inspector disposes the
    // instances of the markup it replaced (Bootstrap keeps every instance in
    // a Map keyed by its element until dispose()).
    var ranges = [];

    // A Bootstrap 6 Range on a .form-range wrapper, with the value bubble.
    // Bootstrap starts only the ranges present at DOMContentLoaded, so markup
    // drawn later comes here. format(value) gives the bubble's text. Returns
    // a promise of the instance (null under Bootstrap 5).
    function bubbleRange(target, format) {
        var el = node(target);
        return ready(function () {
            ranges = ranges.filter(function (r) {
                if (r.isConnected === false) {
                    dispose('range', r);
                    return false;
                }
                return r !== el;
            });
            if (!el) {
                return null;
            }
            var inst = get('range', el, { bubble: true, formatter: format || null });
            if (inst) {
                ranges.push(el);
            }
            return inst;
        });
    }

    var pgUI = {
        version: version,
        names: names,
        options: options,
        ready: ready,
        get: get,
        find: find,
        dispose: dispose,
        isOpen: isOpen,
        anyOpen: anyOpen,
        on: on,
        overlayHost: overlayHost,
        show: function (kind, target, opts) { return run(kind, target, 'show', opts); },
        hide: function (kind, target) { return run(kind, target, 'hide'); },
        toggle: function (kind, target, opts) { return run(kind, target, 'toggle', opts); }
    };

    // pgUI.dialog.show(el), pgUI.menu.hide(el), ... for every kind.
    Object.keys(KINDS).forEach(function (kind) {
        pgUI[kind] = {
            get: function (target, opts) { return get(kind, target, opts); },
            find: function (target) { return find(kind, target); },
            show: function (target, opts) { return run(kind, target, 'show', opts); },
            hide: function (target) { return run(kind, target, 'hide'); },
            toggle: function (target, opts) { return run(kind, target, 'toggle', opts); },
            dispose: function (target) { dispose(kind, target); },
            isOpen: function (target) { return isOpen(kind, target); },
            on: function (target, name, handler, opts) { return on(target, kind, name, handler, opts); }
        };
    });
    pgUI.dialog.build = buildDialog;
    pgUI.range.bubble = bubbleRange;

    window.pgUI = pgUI;
})(window, document);
