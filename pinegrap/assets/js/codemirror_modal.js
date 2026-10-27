/**
 * Pinegrap - Enterprise Website Platform
 *
 * Generic code editor dialog.
 *
 * A single dialog is created lazily on first use and reused for every
 * call. The caller supplies an initial value, a language,
 * a title, and save/cancel callbacks. The editor inside is the facade's
 * (window.PgCodeEditor, assets/js/pg_code_editor.js), whose engine is
 * fetched on the first open.
 *
 * Public API:
 *   window.openCodeEditor({
 *       code:        String,        // initial editor value
 *       mode:        String,        // 'html' | 'php' | 'css' | 'javascript' | 'json' | 'xml' | 'text'
 *       title:       String,        // modal title (optional, defaults to 'Code Editor')
 *       placeholder: String,        // placeholder hint shown when empty (optional)
 *       onSave:      function(code),// called with new value when user clicks Save
 *       onCancel:    function(),    // optional — called on dismiss without save
 *       saveLabel:   String,        // optional — label for the confirm button
 *       readonly:    Boolean        // optional — opens in read-only mode
 *   });
 *
 * PHP, XML and plain text are shown with the HTML grammar: the facade
 * carries HTML, CSS, JavaScript and JSON.
 *
 * Dependencies:
 *   - assets/js/pg_code_editor.js (PgCodeEditor) on the page
 *   - pgUI (assets/js/pg_ui.js), Bootstrap's JavaScript behind it
 *
 * Labels are read from window.PgCodeModalLabels when the including screen
 * emits that map; otherwise the English text is shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var _modalEl   = null;   // the <div> root
    var _host      = null;   // the editor's host inside the body
    var _editor    = null;   // PgCodeEditor instance
    var _titleEl   = null;
    var _saveBtn   = null;
    var _formatBtn = null;
    var _opts      = null;   // current call's options
    var _didSave   = false;  // guard: distinguish Save click from dismiss
    var _opening   = 0;      // the call the bundle is being fetched for

    function _t(key) {
        var map = window.PgCodeModalLabels;
        return (map && map[key]) ? String(map[key]) : key;
    }

    function _h(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _ensureModal() {
        if (_modalEl) return;

        var html =
            '<div class="modal fade" id="pgCodeEditorModal" tabindex="-1" aria-labelledby="pgCodeEditorTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
                    '<div class="modal-content" style="height:85vh;">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="pgCodeEditorTitle">' + _h(_t('Code Editor')) + '</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + _h(_t('Close')) + '"></button>' +
                        '</div>' +
                        '<div class="modal-body p-0 d-flex flex-column" style="overflow:hidden;min-height:0;">' +
                            '<div id="pgCodeEditorHost" class="pg-code-editor" style="flex:1 1 auto;min-height:0;"></div>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-outline-secondary me-auto d-none" id="pgCodeEditorFormat" title="Shift+Alt+F">' + _h(_t('Format code')) + '</button>' +
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + _h(_t('Cancel')) + '</button>' +
                            '<button type="button" class="btn btn-primary" id="pgCodeEditorSave">' + _h(_t('Save')) + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        _modalEl = wrap.firstChild;
        document.body.appendChild(_modalEl);

        _host     = _modalEl.querySelector('#pgCodeEditorHost');
        _titleEl  = _modalEl.querySelector('#pgCodeEditorTitle');
        _saveBtn  = _modalEl.querySelector('#pgCodeEditorSave');
        _formatBtn = _modalEl.querySelector('#pgCodeEditorFormat');

        _formatBtn.addEventListener('click', function () {
            if (!_editor) return;
            _editor.format();
            _editor.focus();
        });

        _saveBtn.addEventListener('click', function () {
            if (!_opts || !_editor) return;
            _didSave = true;
            var v = _editor.getValue();
            try {
                if (typeof _opts.onSave === 'function') _opts.onSave(v);
            } finally {
                pgUI.dialog.hide(_modalEl);
            }
        });

        pgUI.dialog.on(_modalEl, 'hidden', function () {
            if (!_didSave && _opts && typeof _opts.onCancel === 'function') {
                try { _opts.onCancel(); } catch (e) {}
            }
            // A fresh editor per open: the language and the text are the call's.
            if (_editor) { _editor.destroy(); _editor = null; }
            _modalEl.classList.remove('pg-code-editor-full');
            var dlg = _modalEl.querySelector('.modal-dialog');
            if (dlg) dlg.classList.remove('modal-fullscreen');
            _opts = null;
        });
    }

    function _theme() {
        try {
            return document.cookie.indexOf('prefers-color-scheme=dark') !== -1 ? 'dark' : 'light';
        } catch (e) { return 'light'; }
    }

    function _language(mode) {
        var m = String(mode || 'html').toLowerCase();
        if (m === 'css') return 'css';
        if (m === 'javascript' || m === 'js') return 'javascript';
        if (m === 'json') return 'json';
        return 'html';
    }

    // The facade formats CSS, JavaScript and JSON only.
    function _formats(mode) {
        return _language(mode) !== 'html';
    }

    function _toggleFullscreen() {
        var dlg = _modalEl ? _modalEl.querySelector('.modal-dialog') : null;
        if (dlg) dlg.classList.toggle('modal-fullscreen');
        return true;
    }

    // Escape leaves the full window first; only the next one cancels the
    // dialog (the editor stops an Escape it used before the modal hears it).
    function _leaveFullscreen() {
        var dlg = _modalEl ? _modalEl.querySelector('.modal-dialog') : null;
        if (!dlg || !dlg.classList.contains('modal-fullscreen')) return false;
        dlg.classList.remove('modal-fullscreen');
        return true;
    }

    window.openCodeEditor = function (opts) {
        opts = opts || {};
        if (typeof window.PgCodeEditor === 'undefined') {
            // Fallback so callers don't hang silently if the facade was forgotten.
            // A plain prompt preserves the save round-trip.
            var v = window.prompt(opts.title || _t('Code'), opts.code || '');
            if (v !== null && typeof opts.onSave === 'function') opts.onSave(v);
            else if (v === null && typeof opts.onCancel === 'function') opts.onCancel();
            return;
        }

        _ensureModal();
        _opts    = opts;
        _didSave = false;

        _titleEl.textContent = opts.title || _t('Code Editor');
        // The button says what it does. "Save" is wrong when the dialog
        // hands its content on to a next step rather than storing it.
        _saveBtn.textContent = opts.saveLabel || _t('Save');
        _formatBtn.classList.toggle('d-none', !!opts.readonly || !_formats(opts.mode));

        pgUI.dialog.show(_modalEl, { backdrop: 'static', keyboard: true });

        // The editor is built once the modal is visible, so it measures a
        // real box; the engine may still be on its way for the first open.
        var call = ++_opening;
        var mount = function () {
            if (call !== _opening || !_opts) return;
            if (_editor) { _editor.destroy(); _editor = null; }
            _editor = PgCodeEditor.create(_host, {
                language: _language(opts.mode),
                value: opts.code || '',
                readOnly: !!opts.readonly,
                theme: _theme(),
                placeholder: opts.placeholder || '',
                phrases: window.PgCodeEditorPhrases || null,
                labels: { syntaxError: _t('Syntax error') },
                keys: [
                    { key: 'Mod-s', run: function () { _saveBtn.click(); return true; } },
                    { key: 'F11', run: _toggleFullscreen },
                    { key: 'Escape', run: _leaveFullscreen }
                ]
            });
            setTimeout(function () { if (_editor && !opts.readonly) _editor.focus(); }, 50);
        };
        var onShown = function () {
            PgCodeEditor.load().then(mount, function (err) {
                if (call !== _opening) return;
                _host.textContent = (err && err.message) ? err.message : 'The code editor could not be loaded.';
            });
        };
        pgUI.dialog.on(_modalEl, 'shown', onShown, { once: true });
    };
})();
