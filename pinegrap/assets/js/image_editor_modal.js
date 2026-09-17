/**
 * Generic image editor modal.
 *
 * Pintura in a Bootstrap 5 modal, saved through image_editor_save.php. One
 * modal instance is created on first use and reused, the same shape as
 * codemirror_modal.js — any screen that can show an image can offer editing
 * without knowing anything about Pintura or about how files are stored.
 *
 * Public API:
 *   window.openImageEditor({
 *       src:       String,          // what the editor loads (a URL on this site)
 *       fileName:  String,          // the row in `files`; defaults to the last
 *                                   // path segment of src
 *       fileId:    Number,          // optional, wins over fileName
 *       token:     String,          // optional CSRF token; falls back to
 *                                   // window.software_token / a form field
 *       saveUrl:   String,          // optional; defaults to image_editor_save.php
 *       folderId:  Number,          // optional; where a brand new picture goes
 *       saveLabel: String,          // optional; wording of the save button
 *                                   // next to the current page
 *       allowReplace: Boolean,      // default true
 *       allowCopy:    Boolean,      // default true
 *       newFile:   Boolean,         // optional; no file behind this picture
 *                                   // yet, so the footer asks for a format
 *                                   // rather than offering to keep one
 *       onSaved:   function(result) // { mode, name, url, width, height, size }
 *       onCancel:  function()
 *   });
 *
 *   window.imageEditorAvailable()   // false when Pintura did not load
 *
 * Dependencies: jQuery (Pintura ships as $.fn.doka), Bootstrap 5 JS, and the
 * Pintura assets — all emitted together by get_image_editor_includes().
 */
(function () {
    'use strict';

    var _modalEl  = null;
    var _modal    = null;
    var _editor   = null;
    var _opts     = null;
    var _blobData = '';     // the processed image as a data: URL
    var _didSave  = false;
    var _busy     = false;
    // The editor has the picture and can be asked for it. Before that, asking
    // returns an empty frame of the right size -- a blank white file written
    // over the one the operator meant to edit -- so the save controls stay
    // shut until the editor says the picture is in.
    var _ready    = false;
    var _readyTimer = 0;

    function t(key, fallback) {
        // Screens that carry the software's JS translations use lang(); the
        // tool must still work on one that does not.
        if (typeof window.lang === 'function') {
            try { var v = window.lang(key); if (v) return v; } catch (e) {}
        }
        return fallback || key;
    }

    function _tokenOf(opts) {
        if (opts && opts.token) return opts.token;
        if (typeof window.software_token !== 'undefined' && window.software_token) return window.software_token;
        var el = document.querySelector('input[name="token"]');
        return el ? el.value : '';
    }

    function _saveUrlOf(opts) {
        if (opts && opts.saveUrl) return opts.saveUrl;
        // Same directory as the screen doing the asking. Every caller so far
        // is a backend screen, and they all sit beside the endpoint.
        var base = window.location.pathname.replace(/[^\/]*$/, '');
        return base + 'image_editor_save.php';
    }

    function _nameFromSrc(src) {
        var s = String(src || '').split('?')[0].split('#')[0];
        var i = s.lastIndexOf('/');
        return decodeURIComponent(i === -1 ? s : s.slice(i + 1));
    }

    /**
     * The address the editor loads, which is never the one the browser
     * remembers.
     *
     * Files are served with a week of cache (get_file.php sends
     * "Cache-Control: public, max-age=604800"), so a picture that has been
     * replaced keeps answering with its old bytes until the week is out. A
     * tile showing yesterday's picture is a nuisance; an editor showing it is
     * a trap, because everything drawn on top is then saved over the newer
     * file and the operator's last edit disappears. Editing starts from what
     * is on disk, so this asks for it by an address no cache can answer.
     *
     * Data and blob sources are the picture itself and are left alone.
     */
    function _editorSrc(src) {
        var s = String(src || '');

        if ((s.indexOf('data:') === 0) || (s.indexOf('blob:') === 0)) { return s; }

        return s + ((s.indexOf('?') === -1) ? '?' : '&') + 'pgedit=' + Date.now();
    }

    window.imageEditorAvailable = function () {
        return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.doka);
    };

    /**
     * The tool's mark, drawn rather than fetched.
     *
     * Inline so it costs no request and needs no file sitting next to whichever
     * screen opened the editor, and in currentColor so it follows the heading
     * into either theme. Three shapes and no more: at nineteen pixels a fourth
     * one stops being a picture and starts being a smudge -- an earlier draft
     * had a pencil across the corner and that is exactly what it looked like.
     * The sun takes the accent colour where one is defined.
     */
    function _markSvg() {
        return '<svg class="me-2 flex-shrink-0" width="19" height="19" viewBox="0 0 20 20" aria-hidden="true" focusable="false">' +
            '<rect x="1.9" y="2.9" width="16.2" height="14.2" rx="3.2" fill="none" stroke="currentColor" stroke-width="1.6"/>' +
            '<circle cx="6.9" cy="7.8" r="1.5" fill="var(--bs-primary, currentColor)"/>' +
            '<path d="M2.6 14.9l4.6-4.5 3 2.9 2.4-2.3 4.2 4.1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>';
    }

    function _ensureModal() {
        if (_modalEl) return;

        var html =
            '<div class="modal fade" id="pgImageEditorModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-xl modal-dialog-centered">' +
                    '<div class="modal-content" style="height:88vh">' +
                        '<div class="modal-header py-2">' +
                            '<h6 class="modal-title mb-0 d-flex align-items-center" id="pgImageEditorTitle">' +
                                _markSvg() +
                                '<span id="pgImageEditorTitleText">' + t('Pintura Image Editor', 'Pintura Görsel Düzenleyici') + '</span>' +
                            '</h6>' +
                            '<button type="button" class="btn btn-sm btn-link text-body-secondary p-0 ms-auto me-2 lh-1" ' +
                                'id="pgImageEditorFull" aria-pressed="false">' +
                                '<span class="bi bi-arrows-fullscreen" id="pgImageEditorFullIcon"></span>' +
                            '</button>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                        '</div>' +
                        '<div class="modal-body p-0 position-relative" style="overflow:hidden">' +
                            '<div id="pgImageEditorHost" style="width:100%;height:100%"></div>' +
                        '</div>' +
                        '<div class="modal-footer py-2 justify-content-between" id="pgImageEditorFoot">' +
                            '<span class="small text-muted" id="pgImageEditorHint"></span>' +
                            '<span class="d-flex align-items-center gap-2" id="pgImageEditorActions"></span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var host = document.createElement('div');
        host.innerHTML = html;
        _modalEl = host.firstChild;
        document.body.appendChild(_modalEl);

        var style = document.createElement('style');
        style.textContent =
            '#pgImageEditorModal .modal-body { min-height:0; }' +
            '#pgImageEditorHost .pintura-editor, #pgImageEditorHost { height:100%; }' +
            // The 88vh below is what makes the windowed editor a window. Full
            // screen has to drop it, or the editor keeps a band of page
            // showing underneath the dialog that now covers the screen.
            '#pgImageEditorModal .modal-dialog.modal-fullscreen .modal-content { height:100% !important; }' +
            // Bootstrap gives .btn-close its own margin-left:auto. With the
            // fullscreen button also pushed right, two auto margins split the
            // free space and left a gap between the pair; the close button
            // only needs to sit next to its neighbour.
            '#pgImageEditorModal .modal-header .btn-close { margin-left:0; }';
        document.head.appendChild(style);

        var fullButton = _modalEl.querySelector('#pgImageEditorFull');

        if (fullButton) {
            fullButton.addEventListener('click', function () { _setFullscreen(!_isFullscreen()); });
        }

        // Windowed to begin with, and said so: the label and the pressed state
        // are written by the same function that flips them, so the button is
        // never sitting there with no tooltip waiting for its first click.
        _setFullscreen(false);

        _modalEl.addEventListener('hidden.bs.modal', function () {
            if (!_didSave && _opts && typeof _opts.onCancel === 'function') {
                try { _opts.onCancel(); } catch (e) {}
            }
            // A fresh editor per open: Pintura keeps the loaded image and the
            // whole undo history, and reusing the instance would show the
            // previous file's edits over the next file.
            try { if (_editor && _editor.destroy) _editor.destroy(); } catch (e) {}
            try { window.jQuery('#pgImageEditorHost').empty(); } catch (e) {}
            window.clearInterval(_readyTimer);
            _readyTimer = 0;
            _editor   = null;
            _opts     = null;
            _blobData = '';
            _busy     = false;
            _ready    = false;

            // Left windowed for the next open. Full screen is a choice about
            // the picture in front of you, not a setting, and inheriting it
            // silently is how a small crop ends up covering the screen.
            _setFullscreen(false);
        });
    }

    // ── Full screen ─────────────────────────────────────────────────────
    //
    // Bootstrap's own modal-fullscreen class does the layout; the work here is
    // telling the editor about it. Pintura measures its canvas when its box
    // changes, and a class swap is not an event it watches, so the resize is
    // announced once the dialog has settled at its new size.
    function _isFullscreen() {
        var dialog = _modalEl ? _modalEl.querySelector('.modal-dialog') : null;
        return !!(dialog && dialog.classList.contains('modal-fullscreen'));
    }

    function _setFullscreen(on) {
        if (!_modalEl) return;

        var dialog = _modalEl.querySelector('.modal-dialog');
        var button = _modalEl.querySelector('#pgImageEditorFull');
        var icon   = _modalEl.querySelector('#pgImageEditorFullIcon');
        if (!dialog) return;

        dialog.classList.toggle('modal-fullscreen', !!on);
        dialog.classList.toggle('modal-xl', !on);
        dialog.classList.toggle('modal-dialog-centered', !on);

        if (icon) {
            icon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        }

        if (button) {
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            var label = on ? t('Exit full screen', 'Tam ekrandan çık') : t('Full screen', 'Tam ekran');
            button.setAttribute('title', label);
            button.setAttribute('aria-label', label);
        }

        // Two frames: one for the class to apply, one for the browser to lay
        // the dialog out at its new size before the editor measures it.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                try { window.dispatchEvent(new Event('resize')); } catch (e) {}
            });
        });
    }

    // ── Footer ──────────────────────────────────────────────────────────
    //
    // The save controls are here from the moment the image opens, not after
    // a first "done" step. Asking how to save only once the operator has
    // already committed puts the decision at the point where they have the
    // least attention left, and it hid the one thing they might want to know
    // beforehand: that Replace overwrites the file every other page is using.
    //
    // Pintura's own export button is switched off (enableButtonExport:false)
    // so there is exactly one way to save. The buttons here call
    // editor.processImage() themselves.
    function _renderFoot() {
        var hint    = document.getElementById('pgImageEditorHint');
        var actions = document.getElementById('pgImageEditorActions');
        if (!hint || !actions) return;

        var name  = (_opts && _opts.fileName) || '';
        var isGif = /\.gif$/i.test(name);
        var allowReplace = (!_opts || _opts.allowReplace !== false) && !isGif;
        var allowCopy    = !_opts || _opts.allowCopy !== false;
        // A picture that does not exist yet has no format to keep, so "Same
        // format" would be an answer to a question nobody asked -- and it was
        // the option sitting there selected, which made the first save of a
        // blank canvas look like it was about to keep a format it never had.
        var isNew = !!(_opts && _opts.newFile);

        hint.innerHTML =
            '<span class="bi bi-image me-1"></span><strong>' + _esc(name) + '</strong>' +
            '<span id="pgImageEditorMeta" class="ms-2 opacity-75"></span>' +
            (isGif ? '<span class="ms-2 text-warning">' +
                     t('Animated GIF — new file only.', 'Hareketli GIF — yalnız yeni dosya.') + '</span>' : '');

        actions.innerHTML =
            '<span class="small text-muted me-1">' + t('Save as', 'Kayıt biçimi') + '</span>' +
            '<select class="form-select form-select-sm" id="pgImageEditorType" style="width:auto">' +
                (isNew ? '' : '<option value="">' + t('Same format', 'Aynı biçim') + '</option>') +
                '<option value="jpg">jpg</option>' +
                // png for a new picture: it is the only one of the three that
                // keeps every pixel and every transparent corner, which is
                // what a drawing wants. jpg and webp stay one click away.
                '<option value="png"' + (isNew ? ' selected' : '') + '>png</option>' +
                '<option value="webp">webp</option>' +
            '</select>' +
            (allowCopy
                ? '<button type="button" class="btn btn-sm btn-primary" id="pgImageEditorSaveCopy" ' +
                  'title="' + t('Keeps the original file and points this page at the copy.',
                                'Orijinal dosya kalır, bu sayfa kopyayı gösterir.') + '">' +
                  '<span class="bi bi-files me-1"></span>' +
                  ((_opts && _opts.saveLabel) ? _esc(_opts.saveLabel) : t('Save as new file', 'Yeni dosya olarak kaydet')) +
                  '</button>'
                : '') +
            (allowReplace
                ? '<button type="button" class="btn btn-sm btn-outline-warning" id="pgImageEditorSaveReplace" ' +
                  'title="' + t('Writes over the file. Every page using it changes.',
                                'Dosyanın üstüne yazar. Onu kullanan her sayfa değişir.') + '">' +
                  '<span class="bi bi-arrow-repeat me-1"></span>' + t('Replace', 'Değiştir') + '</button>'
                : '');

        var copy = document.getElementById('pgImageEditorSaveCopy');
        if (copy) copy.addEventListener('click', function () {
            var sel = document.getElementById('pgImageEditorType');
            _save('copy', sel ? sel.value : '');
        });
        var rep = document.getElementById('pgImageEditorSaveReplace');
        if (rep) rep.addEventListener('click', function () {
            var sel = document.getElementById('pgImageEditorType');
            _save('replace', sel ? sel.value : '');
        });

        _setReady(_ready);
    }

    /**
     * Open the save controls once the picture is in the editor.
     *
     * A large file, or a slow one, takes a moment to arrive and decode. Asked
     * for its image before then, the editor answers with an empty frame at the
     * right dimensions rather than with an error -- so a save made in that
     * moment writes a blank picture, and a Replace writes it over the original.
     * Nothing to save until there is something to save.
     */
    function _setReady(on) {
        _ready = !!on;

        var actions = document.getElementById('pgImageEditorActions');
        if (!actions) return;

        actions.querySelectorAll('button, select').forEach(function (el) { el.disabled = !_ready; });
    }

    function _esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Dimensions and weight of the file as it stands. Shown beside the name
    // so "Replace" is a decision made with the numbers in view.
    function _showMeta(w, h, bytes) {
        var el = document.getElementById('pgImageEditorMeta');
        if (!el) return;
        var bits = [];
        if (w && h) bits.push(w + ' × ' + h);
        if (bytes)  bits.push(_humanSize(bytes));
        el.textContent = bits.length ? '· ' + bits.join(' · ') : '';
    }

    function _humanSize(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function _busyState(on) {
        _busy = on;
        var actions = document.getElementById('pgImageEditorActions');
        if (!actions) return;
        actions.querySelectorAll('button, select').forEach(function (b) { b.disabled = on; });
        var hint = document.getElementById('pgImageEditorHint');
        if (on && hint) {
            hint.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' +
                             t('Saving…', 'Kaydediliyor…');
        }
    }

    function _save(mode, type) {
        if (_busy || !_ready || !_opts || !_editor) return;
        _busyState(true);

        // Whatever was being typed or dragged a moment ago is finished first.
        // A text shape commits its wording when it loses focus, and the save
        // button is outside the editor, so without this the last word typed
        // into a caption would not be in the picture that gets written.
        try {
            var focused = document.activeElement;
            var host = document.getElementById('pgImageEditorHost');
            if (focused && host && host.contains(focused) && focused.blur) { focused.blur(); }
        } catch (e) {}

        // The editor is asked for the image HERE rather than waiting for its
        // own export button: one save path, and the operator picked the mode
        // before pressing anything.
        var $ = window.jQuery;
        var out;
        try { out = $('#pgImageEditorHost').doka('processImage'); }
        catch (e) { out = null; }
        if (!out || typeof out.then !== 'function') {
            _busyState(false);
            _footError(t('The edited image could not be produced.', 'Düzenlenen görsel üretilemedi.'));
            return;
        }
        out.then(function (res) {
            var blob = res && (res.dest || res);
            if (!blob) throw new Error('no blob');
            var reader = new FileReader();
            reader.onload = function () { _post(mode, type, String(reader.result || '')); };
            reader.onerror = function () {
                _busyState(false);
                _footError(t('The edited image could not be read.', 'Düzenlenen görsel okunamadı.'));
            };
            reader.readAsDataURL(blob);
        }).catch(function () {
            _busyState(false);
            _footError(t('The edited image could not be produced.', 'Düzenlenen görsel üretilemedi.'));
        });
    }

    function _footError(msg) {
        var hint = document.getElementById('pgImageEditorHint');
        if (hint) hint.innerHTML = '<span class="text-danger"><span class="bi bi-x-circle me-1"></span>' + _esc(msg) + '</span>';
    }

    function _post(mode, type, dataUrl) {
        _blobData = dataUrl;
        var body = {
            token: _tokenOf(_opts),
            mode:  mode,
            type:  type || '',
            data:  _blobData
        };
        if (_opts.fileId)   body.file_id   = _opts.fileId;
        if (_opts.fileName) body.file_name = _opts.fileName;
        // A blank canvas has no file behind it; the folder is what tells the
        // endpoint to make a row rather than write over one.
        if (_opts.folderId) body.folder_id = _opts.folderId;

        fetch(_saveUrlOf(_opts), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.text().then(function (txt) {
            var j = null; try { j = JSON.parse(txt); } catch (e) {}
            return { ok: r.ok, status: r.status, data: j };
        }); })
        .then(function (res) {
            _busyState(false);
            if (!res.data || res.data.status !== 'success') {
                var msg = (res.data && res.data.message)
                    ? res.data.message
                    : (t('The image could not be saved.', 'Görsel kaydedilemedi.') + ' (' + res.status + ')');
                var hint = document.getElementById('pgImageEditorHint');
                if (hint) hint.innerHTML = '<span class="text-danger">' + msg + '</span>';
                return;
            }
            _didSave = true;
            var cb = _opts && _opts.onSaved;
            var out = res.data;
            if (_modal) _modal.hide();
            if (typeof cb === 'function') { try { cb(out); } catch (e) {} }
        })
        .catch(function () {
            _busyState(false);
            var hint = document.getElementById('pgImageEditorHint');
            if (hint) hint.innerHTML = '<span class="text-danger">' + t('Network error.', 'Ağ hatası.') + '</span>';
        });
    }

    window.openImageEditor = function (opts) {
        opts = opts || {};
        if (!window.imageEditorAvailable()) {
            // The tool is optional on any given screen; say so rather than
            // failing silently on a click that looked like it would work.
            if (typeof window.alert === 'function') {
                window.alert(t('The image editor is not loaded on this screen.',
                               'Görsel düzenleyici bu ekranda yüklü değil.'));
            }
            return;
        }
        if (!opts.src) return;
        if (!opts.fileName && !opts.fileId) opts.fileName = _nameFromSrc(opts.src);

        _ensureModal();
        _opts     = opts;
        _didSave  = false;
        _blobData = '';
        _ready    = false;

        // The heading says what the window is; which file is in it is written
        // along the bottom, next to its dimensions and the save buttons -- the
        // end of the window an operator is actually looking at when they
        // decide between replacing the picture and keeping a copy.

        if (!_modal) _modal = new bootstrap.Modal(_modalEl, { backdrop: 'static', keyboard: true });
        _renderFoot();
        _modal.show();

        // Pintura measures its container, so it can only be built once the
        // modal is actually on screen — same reason CodeMirror waits.
        var onShown = function () {
            _modalEl.removeEventListener('shown.bs.modal', onShown);
            var $ = window.jQuery;
            var d = $.fn.doka;

            d.setPlugins(d.plugin_crop, d.plugin_resize, d.plugin_filter,
                         d.plugin_finetune, d.plugin_decorate, d.plugin_sticker);

            var locale = Object.assign({}, d.locale_en_gb,
                d.plugin_crop_locale_en_gb, d.plugin_resize_locale_en_gb,
                d.plugin_finetune_locale_en_gb, d.plugin_filter_locale_en_gb,
                d.plugin_decorate_locale_en_gb, d.plugin_sticker_locale_en_gb,
                d.component_shape_editor_locale_en_gb,
                (window.pgImageEditorLocale || {}));

            var $host = $('#pgImageEditorHost');
            _editor = $host.doka({
                src: _editorSrc(opts.src),
                imageReader: d.createDefaultImageReader(),
                imageWriter: d.createDefaultImageWriter(),
                // The footer saves. Two save buttons that mean different
                // things — one of which does not know whether to replace or
                // copy — is one too many.
                enableButtonExport: false,
                cropEnableInfoIndicator: true,
                cropEnableButtonRotateRight: true,
                cropEnableButtonToggleCropLimit: true,
                cropEnableButtonFlipVertical: true,
                cropImageSelectionCornerStyle: 'hook',
                filterFunctions: d.plugin_filter_defaults.filterFunctions,
                filterOptions: d.plugin_filter_defaults.filterOptions,
                finetuneControlConfiguration: d.plugin_finetune_defaults.finetuneControlConfiguration,
                finetuneOptions: d.plugin_finetune_defaults.finetuneOptions,
                decorateTools: d.plugin_decorate_defaults.decorateTools,
                decorateToolShapes: d.plugin_decorate_defaults.decorateToolShapes,
                decorateShapeControls: d.plugin_decorate_defaults.decorateShapeControls,
                locale: locale
            });

            // Readiness, watched rather than waited for.
            //
            // The editor announces its picture with doka:load, but the modal
            // is reused from one picture to the next and that announcement
            // does not survive every second open -- and a save button that
            // stays grey forever is worse than one that opens a moment late.
            // So the editor's own load state is read directly; whichever
            // arrives first opens the controls.
            window.clearInterval(_readyTimer);
            _readyTimer = window.setInterval(function () {

                if (_ready || !_editor) { window.clearInterval(_readyTimer); return; }

                var state = null;
                var size  = null;

                try {
                    state = $host.doka('imageLoadState');
                    size  = $host.doka('imageSize');
                } catch (e) { return; }

                if (state && state.complete && size && size.width) {
                    window.clearInterval(_readyTimer);
                    _setReady(true);
                }
            }, 150);

            // Dimensions and weight, once the editor has the file. Shown in
            // the footer so "Replace" is a decision made with the numbers in
            // view rather than from the thumbnail.
            $host.on('doka:load', function (e) {
                var det = (e && e.detail) || {};
                var size = det.size || {};
                var src  = det.src || null;
                _showMeta(size.width || 0, size.height || 0, (src && src.size) ? src.size : 0);
                _setReady(true);
            });
            // A picture that never arrives leaves the save controls shut, so
            // the reason has to be said out loud rather than left as a row of
            // grey buttons.
            $host.on('doka:loaderror', function () {
                _footError(t('The image could not be opened.', 'Görsel açılamadı.'));
            });
        };
        _modalEl.addEventListener('shown.bs.modal', onShown);
    };
})();
