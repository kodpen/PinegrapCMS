/**
 * Generic CodeMirror modal editor.
 *
 * A single Bootstrap 5 modal instance is created lazily on first use and reused
 * for every call. The caller supplies an initial value, a CodeMirror mode, a
 * title, and save/cancel callbacks.
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
 * Dependencies:
 *   - CodeMirror 5.65.9 assets loaded via get_codemirror_includes() PHP helper
 *   - Bootstrap 5 JS (bootstrap.Modal)
 *
 * This file is framework-agnostic (no jQuery, no inner Pinegrap dependencies)
 * so it can be pulled into any admin page that already includes CodeMirror.
 * Labels are read from window.PgCodeModalLabels when the including screen
 * emits that map; otherwise the English text is shown.
 */
(function () {
    'use strict';

    var MODE_MAP = {
        'html':       'htmlmixed',
        'htmlmixed':  'htmlmixed',
        'php':        'application/x-httpd-php',
        'css':        'css',
        'javascript': 'javascript',
        'js':         'javascript',
        'json':       'application/json',
        'xml':        'application/xml',
        'text':       'text/plain',
        'plain':      'text/plain'
    };

    var _modal       = null;   // Bootstrap Modal instance
    var _modalEl     = null;   // the <div> root
    var _textarea    = null;
    var _editor      = null;   // CodeMirror instance
    var _titleEl     = null;
    var _saveBtn     = null;
    var _opts        = null;   // current call's options
    var _didSave     = false;  // guard: distinguish Save click from dismiss

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
                        '<div class="modal-body p-0 d-flex flex-column" style="overflow:hidden;">' +
                            '<textarea id="pgCodeEditorTextarea" style="width:100%;height:100%;border:0;display:block;"></textarea>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + _h(_t('Cancel')) + '</button>' +
                            '<button type="button" class="btn btn-primary" id="pgCodeEditorSave">' + _h(_t('Save')) + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var host = document.createElement('div');
        host.innerHTML = html;
        _modalEl = host.firstChild;
        document.body.appendChild(_modalEl);

        _textarea = _modalEl.querySelector('#pgCodeEditorTextarea');
        _titleEl  = _modalEl.querySelector('#pgCodeEditorTitle');
        _saveBtn  = _modalEl.querySelector('#pgCodeEditorSave');

        // CodeMirror needs to sit inside the full height of the modal body.
        // Override the default sizing so editor fills available space.
        var style = document.createElement('style');
        style.textContent =
            '#pgCodeEditorModal .CodeMirror { height:100%; flex:1 1 auto; font-size:13px; }' +
            '#pgCodeEditorModal .modal-body { min-height:0; }';
        document.head.appendChild(style);

        _saveBtn.addEventListener('click', function () {
            if (!_opts || !_editor) return;
            _didSave = true;
            var v = _editor.getValue();
            try {
                if (typeof _opts.onSave === 'function') _opts.onSave(v);
            } finally {
                _modal.hide();
            }
        });

        _modalEl.addEventListener('hidden.bs.modal', function () {
            if (!_didSave && _opts && typeof _opts.onCancel === 'function') {
                try { _opts.onCancel(); } catch (e) {}
            }
            // Destroy editor so each open gets a clean instance with the right mode.
            if (_editor) {
                // First the refresh addon, which toTextArea() does not stop: it
                // would go on reading the offsetHeight of a wrapper that is no
                // longer in the document, every 250 ms, for the life of the tab.
                try { _editor.setOption('autoRefresh', false); } catch (e) {}

                _editor.toTextArea();
                _editor = null;
            }
            _opts = null;
        });
    }

    function _resolveTheme() {
        try {
            var dark = document.cookie.indexOf('prefers-color-scheme=dark') !== -1;
            return dark ? 'pastel-on-dark' : 'default';
        } catch (e) { return 'default'; }
    }

    window.openCodeEditor = function (opts) {
        opts = opts || {};
        if (typeof window.CodeMirror === 'undefined') {
            // Fallback so callers don't hang silently if the helper was forgotten.
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
        _textarea.value      = opts.code || '';
        if (opts.placeholder) _textarea.setAttribute('placeholder', opts.placeholder);

        if (!_modal) _modal = new bootstrap.Modal(_modalEl, { backdrop: 'static', keyboard: true });
        _modal.show();

        // CodeMirror must be initialized AFTER the modal is visible so the
        // textarea has non-zero dimensions (otherwise the editor renders 0×0).
        var onShown = function () {
            _modalEl.removeEventListener('shown.bs.modal', onShown);
            var mode = MODE_MAP[(opts.mode || 'html').toLowerCase()] || 'htmlmixed';
            _editor = CodeMirror.fromTextArea(_textarea, {
                mode: mode,
                lineNumbers: true,
                indentUnit: 4,
                matchTags: { bothTags: true },
                autoCloseTags: true,
                autoCloseBrackets: true,
                autoRefresh: true,
                styleActiveLine: true,
                lineWrapping: false,
                theme: _resolveTheme(),
                readOnly: !!opts.readonly,
                extraKeys: {
                    'F11':         function (cm) { cm.setOption('fullScreen', !cm.getOption('fullScreen')); },
                    'Esc':         function (cm) { if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false); },
                    'Ctrl-Space':  'autocomplete',
                    'Cmd-S':       function () { _saveBtn.click(); },
                    'Ctrl-S':      function () { _saveBtn.click(); }
                },
                viewportMargin: Infinity
            });
            setTimeout(function () { if (_editor) { _editor.refresh(); _editor.focus(); } }, 50);
        };
        _modalEl.addEventListener('shown.bs.modal', onShown);
    };
})();
