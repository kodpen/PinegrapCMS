/**
 * Pinegrap - Enterprise Website Platform
 *
 * The theme switchers of the visual editor (Theme: Dropdown, Theme: Toggle,
 * Theme: Switch): light, dark or the system's choice, kept in localStorage
 * under "pg-theme" and applied as data-bs-theme on <html>.
 *
 * A switcher carries no script of its own, only its data-pg-theme-switcher
 * marker ("dropdown", "toggle" or "switch"). The server adds this file to a
 * page whose HTML holds the marker once every shared component is drawn in
 * (pg_theme_switcher_inject()), so a switcher placed on one page, in a shared
 * component of the page's design or in one taken from another design brings
 * the file with it - and leaves nothing behind when it is removed. The same
 * server step applies the stored choice in <head>, before the page paints.
 * The editor's canvas loads this file too and binds again after every render
 * (window.pgThemeSwitcherBind).
 *
 * window.pgSetTheme / pgGetTheme / pgResolvedTheme and the pg-theme-change
 * event are the engine the switchers' inline script used to define; pages
 * saved before keep that script, and whichever engine runs first is kept.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var KEY  = 'pg-theme';
    var root = document.documentElement;
    var mql  = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    // localStorage throws in some private modes and sandboxed frames; the
    // switcher then still works for the page on screen.
    var memory = null;

    function stored() {
        try {
            return window.localStorage.getItem(KEY) || 'auto';
        } catch (e) {
            return memory || 'auto';
        }
    }

    function resolve(theme) {
        return (!theme || theme === 'auto') ? ((mql && mql.matches) ? 'dark' : 'light') : theme;
    }

    function apply(theme) {
        root.setAttribute('data-bs-theme', resolve(theme));
    }

    function announce() {
        var ev;
        try {
            ev = new Event('pg-theme-change');
        } catch (e) {
            ev = document.createEvent('Event');
            ev.initEvent('pg-theme-change', false, false);
        }
        document.dispatchEvent(ev);
    }

    if (!window.pgThemeInit) {
        window.pgThemeInit = true;

        apply(stored());

        if (mql) {
            var follow = function () {
                if (stored() === 'auto') {
                    apply('auto');
                    announce();
                }
            };

            if (mql.addEventListener) {
                mql.addEventListener('change', follow);
            } else if (mql.addListener) {
                mql.addListener(follow);
            }
        }

        window.pgSetTheme = function (theme) {
            memory = theme;
            try {
                window.localStorage.setItem(KEY, theme);
            } catch (e) {}
            apply(theme);
            announce();
        };

        window.pgGetTheme = stored;

        window.pgResolvedTheme = function () {
            return resolve(window.pgGetTheme());
        };
    }

    // What the bound switchers show is redrawn from one listener. The canvas
    // throws its elements away on every render, so a switcher no longer in
    // the document is dropped here instead of being kept alive by a listener
    // of its own.
    var watched = [];

    function watch(box, sync) {
        watched.push({ box: box, sync: sync });
        sync();
    }

    document.addEventListener('pg-theme-change', function () {
        var live = [];

        for (var i = 0; i < watched.length; i++) {
            if (root.contains(watched[i].box)) {
                watched[i].sync();
                live.push(watched[i]);
            }
        }

        watched = live;
    });

    // Light / Dark / System menu: the active item is checked and the toggle
    // shows its icon.
    function bindDropdown(box) {
        var items = box.querySelectorAll('[data-bs-theme-value]');
        var icon  = box.querySelector('.pg-active-icon');

        function sync() {
            var theme = window.pgGetTheme();

            for (var i = 0; i < items.length; i++) {
                var on    = items[i].getAttribute('data-bs-theme-value') === theme;
                var check = items[i].querySelector('.pg-check');

                items[i].classList.toggle('active', on);
                items[i].setAttribute('aria-pressed', on ? 'true' : 'false');

                if (check) {
                    check.classList.toggle('d-none', !on);
                }
            }

            if (icon) {
                icon.className = 'bi pg-active-icon ' + (theme === 'dark' ? 'bi-moon-stars-fill' : (theme === 'light' ? 'bi-sun-fill' : 'bi-circle-half'));
            }
        }

        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', function (e) {
                e.preventDefault();
                window.pgSetTheme(this.getAttribute('data-bs-theme-value'));
            });
        }

        watch(box, sync);
    }

    // One button between light and dark; the icon shows where a click goes.
    function bindToggle(box) {
        var button = (box.tagName === 'BUTTON') ? box : box.querySelector('button');

        if (!button) {
            return;
        }

        // The button's own icon. On the editor's canvas the icon is wrapped
        // with the editor's toolbar, whose drag handle is a .bi too and
        // comes first: that one is skipped, or the toggle drew its sun on
        // the handle and kept the moon.
        function icon() {
            var all = button.querySelectorAll('i, .bi');

            for (var n = 0; n < all.length; n++) {
                if (!all[n].closest || !all[n].closest('.sd-tb')) {
                    return all[n];
                }
            }

            return null;
        }

        function sync() {
            var dark = window.pgResolvedTheme() === 'dark';
            var i    = icon();

            if (i) {
                i.className = 'bi ' + (dark ? 'bi-sun-fill' : 'bi-moon-stars-fill');
            }

            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        }

        button.addEventListener('click', function () {
            window.pgSetTheme(window.pgResolvedTheme() === 'dark' ? 'light' : 'dark');
        });

        watch(box, sync);
    }

    // A form switch: on is dark.
    function bindSwitch(box) {
        var input = (box.tagName === 'INPUT') ? box : box.querySelector('input[type="checkbox"]');

        if (!input) {
            return;
        }

        function sync() {
            input.checked = (window.pgResolvedTheme() === 'dark');
        }

        input.addEventListener('change', function () {
            window.pgSetTheme(input.checked ? 'dark' : 'light');
        });

        watch(box, sync);
    }

    // Binds every switcher under scope once; the canvas calls it again after
    // each render, when the old elements are gone.
    function bindAll(scope) {
        var boxes = (scope || document).querySelectorAll('[data-pg-theme-switcher]');

        for (var i = 0; i < boxes.length; i++) {
            var box = boxes[i];

            if (box.getAttribute('data-pg-theme-bound') === '1') {
                continue;
            }

            box.setAttribute('data-pg-theme-bound', '1');

            var kind = box.getAttribute('data-pg-theme-switcher');

            if (kind === 'toggle') {
                bindToggle(box);
            } else if (kind === 'switch') {
                bindSwitch(box);
            } else {
                bindDropdown(box);
            }
        }
    }

    window.pgThemeSwitcherBind = bindAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindAll(document);
        });
    } else {
        bindAll(document);
    }
})();
