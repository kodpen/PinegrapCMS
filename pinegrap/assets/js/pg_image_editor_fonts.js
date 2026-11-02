/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: Google Fonts for the Text tool. Under the font tiles a
 * button opens a sheet with a search box, the categories and a list of the
 * shared catalogue (pg_google_fonts.js), each row drawn in its own face as
 * it scrolls into view. A choice sets the text's font to 'g:<family>', as a
 * site font is, and joins the last five families shown as tiles after the
 * site's fonts (kept in this browser).
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, button = X.button;

    var STORE = 'pgie-recent-fonts', SAMPLE = 'Ağ Iı Şş Çç';

    X.icons.fontPick = 'M4 19l5.5-14h1L16 19M6.2 14h7.6M17 9h4M19 7v4';

    // ---- the recent list ----

    // The last five Google families chosen in this browser, newest first.
    Editor.prototype.recentFonts = function () {
        var list = [];

        try {
            list = JSON.parse(window.localStorage.getItem(STORE) || '[]');
        } catch (err) {
            list = [];
        }

        return M.fontRecent(list, '');
    };

    Editor.prototype.rememberFont = function (family) {
        try {
            window.localStorage.setItem(STORE, JSON.stringify(M.fontRecent(this.recentFonts(), family)));
        } catch (err) {
            // No storage here: the tiles simply do not remember.
        }
    };

    // ---- the sheet ----

    // A row drawn in its face once it is in view: its stylesheet is linked
    // then (loadTextFont), not for all two hundred at once.
    function observe(list, self) {
        if (!('IntersectionObserver' in window)) {
            return null;
        }

        return new IntersectionObserver(function (entries, watcher) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                var row = entry.target, family = row.getAttribute('data-family');

                watcher.unobserve(row);
                self.loadTextFont({ font: 'g:' + family, size: 20, weight: 400, text: SAMPLE });
                row.classList.add('pgie-gf-in');
            });
        }, { root: list, rootMargin: '120px 0px' });
    }

    Editor.prototype.openFontSheet = function (current) {
        var self = this;

        if (this.fontBox) {
            return;
        }

        this.fontReturn = document.activeElement;
        this.fontState = { query: '', category: '', active: 0, current: current || '' };

        var close = button('pgie-ibtn sm', t('close'), 'close', function () { self.closeFontSheet(); });
        var search = el('input', { type: 'search', class: 'pgie-gf-search', placeholder: t('fontSearch'), 'aria-label': t('fontSearch'), autocomplete: 'off', spellcheck: 'false' });
        var cats = el('div', { class: 'pgie-seg pgie-gf-cats', role: 'group', 'aria-label': t('fontCategory') });
        var list = el('ul', { class: 'pgie-gf-list', id: 'pgie-gf-list', role: 'listbox', tabindex: '0', 'aria-label': t('googleFonts') });

        [''].concat(window.PgGoogleFonts ? window.PgGoogleFonts.order : []).forEach(function (key) {
            cats.appendChild(el('button', { type: 'button', 'data-cat': key, 'aria-pressed': key ? 'false' : 'true', class: key ? '' : 'on', text: t(key ? 'fontCat_' + key : 'fontAll'), onclick: function () {
                self.fontState.category = key;
                self.fontState.active = 0;
                Array.prototype.forEach.call(cats.children, function (b) {
                    var on = b.getAttribute('data-cat') === key;

                    b.classList.toggle('on', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                self.renderFontRows();
            } }));
        });

        search.addEventListener('input', function () {
            self.fontState.query = search.value;
            self.fontState.active = 0;
            self.renderFontRows();
        });

        var box = el('div', { class: 'pgie-confirm-box pgie-gfbox' }, [
            el('div', { class: 'pgie-ph' }, [el('h2', { id: 'pgie-gf-title', text: t('googleFonts') }), el('div', { class: 'pgie-grow' }), close]),
            search, cats, list
        ]);

        this.fontBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-gf-title' }, [box]);
        this.fontList = list;
        this.fontWatch = observe(list, this);
        this.fontBox.addEventListener('pointerdown', function (e) {
            if (e.target === self.fontBox) {
                self.closeFontSheet();
            }
        });
        // The sheet's keys stay in the sheet: arrows move the choice, Enter
        // takes it, Escape closes, Tab goes round its controls.
        this.fontBox.addEventListener('keydown', function (e) { self.fontKey(e, search); });
        this.root.appendChild(this.fontBox);
        this.renderFontRows();
        search.focus();
    };

    Editor.prototype.renderFontRows = function () {
        var self = this, s = this.fontState, list = this.fontList, rows = M.fontSearch(window.PgGoogleFonts, s.query, s.category);

        if (this.fontWatch) {
            this.fontWatch.disconnect();
        }

        list.textContent = '';
        this.fontRows = rows;
        s.active = Math.max(0, Math.min(s.active, rows.length - 1));

        if (!rows.length) {
            list.removeAttribute('aria-activedescendant');
            list.appendChild(el('li', { class: 'pgie-gf-none', role: 'presentation', text: t('fontNone') }));
            return;
        }

        rows.forEach(function (r, i) {
            var face = M.textFamily('g:' + r.family);
            var name = el('span', { class: 'pgie-gf-name', text: r.family }), sample = el('span', { class: 'pgie-gf-sample', text: SAMPLE });
            var row = el('li', { id: 'pgie-gf-' + i, role: 'option', 'data-family': r.family, 'aria-selected': (('g:' + r.family) === s.current) ? 'true' : 'false',
                class: 'pgie-gf-row' + ((i === s.active) ? ' pgie-gf-active' : '') }, [name, sample]);

            name.style.fontFamily = face;
            sample.style.fontFamily = face;
            row.addEventListener('click', function () { self.pickFont(r.family); });
            list.appendChild(row);

            if (self.fontWatch) {
                self.fontWatch.observe(row);
            } else {
                self.loadTextFont({ font: 'g:' + r.family, size: 20, weight: 400, text: SAMPLE });
            }
        });

        list.setAttribute('aria-activedescendant', 'pgie-gf-' + s.active);
    };

    Editor.prototype.moveFontActive = function (to) {
        var s = this.fontState, n = this.fontRows.length, old = this.fontList.querySelector('.pgie-gf-active'), row;

        if (!n) {
            return;
        }

        s.active = Math.max(0, Math.min(n - 1, to));

        if (old) {
            old.classList.remove('pgie-gf-active');
        }

        row = document.getElementById('pgie-gf-' + s.active);

        if (row) {
            row.classList.add('pgie-gf-active');
            row.scrollIntoView({ block: 'nearest' });
            this.fontList.setAttribute('aria-activedescendant', row.id);
        }
    };

    Editor.prototype.fontKey = function (e, search) {
        var s = this.fontState, page = 8;

        e.stopPropagation();

        if (e.key === 'Escape') {
            e.preventDefault();
            this.closeFontSheet();
        } else if ((e.key === 'ArrowDown') || (e.key === 'ArrowUp') || (e.key === 'PageDown') || (e.key === 'PageUp')) {
            e.preventDefault();
            this.moveFontActive(s.active + ({ ArrowDown: 1, ArrowUp: -1, PageDown: page, PageUp: -page })[e.key]);
        } else if (((e.key === 'Home') || (e.key === 'End')) && (e.target !== search)) {
            e.preventDefault();
            this.moveFontActive((e.key === 'Home') ? 0 : this.fontRows.length - 1);
        } else if ((e.key === 'Enter') && this.fontRows.length && (e.target.tagName !== 'BUTTON')) {
            e.preventDefault();
            this.pickFont(this.fontRows[s.active].family);
        } else if (e.key === 'Tab') {
            var list = Array.prototype.filter.call(this.fontBox.querySelectorAll('button, input, [tabindex="0"]'), function (n) { return !n.disabled; });
            var i = list.indexOf(document.activeElement);

            if ((i < 0) || (e.shiftKey ? i === 0 : i === list.length - 1)) {
                e.preventDefault();
                list[M.trapFocus(list.length, i, e.shiftKey)].focus();
            }
        }
    };

    // The choice: the selected texts (or the next text) take the family.
    Editor.prototype.pickFont = function (family) {
        this.rememberFont(family);
        this.closeFontSheet();
        this.setTextStyle({ font: 'g:' + family });
        this.renderPanel();
    };

    Editor.prototype.closeFontSheet = function () {
        if (!this.fontBox) {
            return false;
        }

        if (this.fontWatch) {
            this.fontWatch.disconnect();
        }

        this.fontBox.parentNode.removeChild(this.fontBox);
        this.fontBox = this.fontList = this.fontWatch = null;

        var back = this.fontReturn;

        this.fontReturn = null;
        ((back && back.isConnected && !back.disabled) ? back : this.stage).focus({ preventScroll: true });

        return true;
    };

    // The button under the font tiles (pg_image_editor_text.js).
    Editor.prototype.fontSheetButton = function (current) {
        var self = this;

        return el('button', { type: 'button', class: 'pgie-btn pgie-gf-open', 'aria-haspopup': 'dialog', onclick: function () { self.openFontSheet(current); } }, [
            icon('fontPick'), el('span', { text: t('googleFontsChoose') })
        ]);
    };
}(window.PgImageEditor.parts));
