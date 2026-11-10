/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: large files into Files. A video or sound from this
 * computer and a saved animation larger than one part
 * (window.pgImageEditorConfig.uploadPart) go in parts: upload_start, then
 * upload_part for each part in order, then the kind's own action
 * (video_upload, sound_upload, animation) with the upload's id instead of
 * the bytes. A part that gets no answer is sent again after a pause, once
 * the browser is online; a part the server already holds is answered with
 * its count, and the next part follows from there. The upload's id is kept
 * in this browser under the file's name, size and time, so the same file
 * picked again after a reload goes on from the bytes the server holds.
 * Smaller files go in one request as before. Sound's Add sound box gets
 * From this computer.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_mix.js
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, pct = X.pct;

    X.icons.upload = 'M12 15.5V4.5M7.5 9l4.5-4.5L16.5 9M4.5 15v3.5a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V15';

    function bytesText(n) {
        var sep = X.config().decimal || '.';

        if (n < 1024 * 1024) {
            return ((n > 0) ? Math.max(1, Math.round(n / 1024)) : 0) + ' KB';
        }

        return (Math.round(n / 104857.6) / 10).toFixed(1).replace('.', sep) + ' MB';
    }

    // The ids of unfinished uploads, kept in this browser only; a browser
    // that keeps nothing starts each upload again.
    function stored(key, value) {
        try {
            var s = window.localStorage;

            if (!key || !s) {
                return '';
            }

            if (value === undefined) {
                return s.getItem(key) || '';
            }

            if (value === null) {
                s.removeItem(key);
            } else {
                s.setItem(key, value);
            }
        } catch (e) {
            // Storage refused: the upload still works, without its resume.
        }

        return '';
    }

    // One request: the head as a line of JSON and the bytes after it
    // (pg_image_editor_binary_body()), or the JSON alone. Answers
    // { code, r } (r null for an answer that is no JSON); rejects only when
    // no answer came.
    function send(head, bytes) {
        var cfg = X.config(), body = Object.assign({}, head, { token: cfg.token || '' });

        return fetch(cfg.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': bytes ? 'application/octet-stream' : 'application/json', Accept: 'application/json' },
            body: bytes ? new Blob([JSON.stringify(body) + '\n', bytes]) : JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (r) {
                return { code: res.status, r: r };
            });
        });
    }

    function done(a, fallback) {
        if (!a.r || (a.r.status !== 'success')) {
            throw new Error((a.r && a.r.message) || fallback);
        }

        return a.r;
    }

    // ms, or until the browser is online again when it is not.
    function pause(ms) {
        return new Promise(function (resolve) {
            if (window.navigator && (window.navigator.onLine === false)) {
                window.addEventListener('online', function back() {
                    window.removeEventListener('online', back);
                    resolve();
                });
                return;
            }

            setTimeout(resolve, ms);
        });
    }

    // A file (a File or Blob) for the action head names: one request when
    // it fits one part, else in parts. opts.progress(done, size) follows
    // the parts (else a toast on the stage), opts.error is the message of
    // a refusal without one. Answers the action's answer.
    Editor.prototype.uploadSend = function (file, kind, head, opts) {
        var self = this, cfg = X.config(), o = opts || {}, fallback = o.error || t('uploadError');
        var max = Number(cfg.animationLimit) || 0, key, show;

        if (!M.uploadChunked(file.size, Number(cfg.uploadPart) || 0)) {
            return send(head, file).then(function (a) { return done(a, fallback); });
        }

        if (!M.uploadFits(file.size, max)) {
            return Promise.reject(new Error(t('uploadTooLarge').replace('{size}', bytesText(max))));
        }

        key = M.uploadKey(kind, (head.file_id || 0) + ':' + (head.folder_id || 0), file);
        show = o.progress || function (got, size) { self.uploadToast(file.name || head.name || '', got, size); };

        return this.uploadOpen(key, kind, file).then(function (up) {
            return self.uploadParts(up, file, show).then(function () {
                return send(Object.assign({}, head, { upload: up.id }));
            }).then(function (a) {
                // Taken or refused, the upload is not picked up again.
                stored(key, null);

                return done(a, fallback);
            });
        });
    };

    // The upload kept for this file, while the server still holds it, or a
    // new one: { id, part, received }.
    Editor.prototype.uploadOpen = function (key, kind, file) {
        var self = this, id = stored(key);

        function fresh() {
            return send({ action: 'upload_start', kind: kind, size: file.size }).then(function (a) {
                var r = done(a, t('uploadError'));

                stored(key, r.upload);

                return { id: r.upload, part: r.part, received: 0 };
            });
        }

        if (!id) {
            return fresh();
        }

        return send({ action: 'upload_status', upload: id }).then(function (a) {
            var r = a.r;

            if (!r || (r.status !== 'success') || (r.size !== file.size) || (r.kind !== kind)) {
                stored(key, null);

                return fresh();
            }

            // Said before the parts' progress, which waits a moment so
            // it is read.
            if (r.received > 0) {
                self.showMessage(t('uploadResume').replace('{name}', file.name || '').replace('{done}', bytesText(r.received)));
                self.uploadHold = Date.now() + 2500;
            }

            return { id: id, part: r.part, received: r.received };
        }, fresh);
    };

    // The parts from the server's count on, one at a time.
    Editor.prototype.uploadParts = function (up, file, show) {
        var self = this, tries = 0;

        function step(received) {
            var next = M.uploadNext(file.size, up.part, received);

            show(Math.min(received, file.size), file.size);

            if (!next) {
                return Promise.resolve();
            }

            return send({ action: 'upload_part', upload: up.id, offset: next.offset }, file.slice(next.offset, next.offset + next.length)).then(function (a) {
                if (a.r && (a.r.status === 'success')) {
                    tries = 0;

                    return step(a.r.received);
                }

                // The server holds other bytes than this part follows (a
                // part sent twice): on from its count.
                if (a.r && (a.r.code === 'upload_offset') && (typeof a.r.received === 'number')) {
                    return step(a.r.received);
                }

                if ((a.code >= 500) && (a.code !== 507)) {
                    return again(received);
                }

                return done(a, t('uploadError'));
            }, function () {
                return again(received);
            });
        }

        // No answer: the same part after a pause, while tries are left.
        function again(received) {
            if (++tries > M.UPLOAD_TRIES) {
                return Promise.reject(new Error(t('uploadBroken')));
            }

            self.showMessage(t('uploadWaiting'));

            return pause(M.uploadDelay(tries)).then(function () { return step(received); });
        }

        return step(up.received || 0);
    };

    // The parts' progress as the stage's toast, its text changed in place.
    Editor.prototype.uploadToast = function (name, got, size) {
        var text = t('uploadProgress').replace('{name}', name).replace('{pct}', pct(Math.floor(100 * got / size)))
            + ' · ' + t('uploadSent').replace('{done}', bytesText(got)).replace('{size}', bytesText(size));

        if (this.uploadHold && (Date.now() < this.uploadHold)) {
            return;
        }

        if (this.toastEl && this.toastEl.parentNode && this.uploadToastEl === this.toastEl) {
            this.toastEl.textContent = text;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(this.hideMessage.bind(this), 5000);
            return;
        }

        this.showMessage(text);
        this.uploadToastEl = this.toastEl;
    };

    // A progress function writing "{done} of {size} sent" into node.
    Editor.prototype.uploadInto = function (node) {
        return function (got, size) {
            if (node) {
                node.textContent = t('uploadSent').replace('{done}', bytesText(got)).replace('{size}', bytesText(size));
            }
        };
    };

    // ---- a sound from this computer ----

    // The button at the top of the Add sound box (openSoundFiles()), with
    // the editor's API only.
    Editor.prototype.soundComputerButton = function () {
        var self = this;

        if (!this.importApi()) {
            return null;
        }

        return el('button', { type: 'button', class: 'pgie-btn pgie-sndlocal', onclick: function () {
            var input = el('input', { type: 'file', accept: M.SOUND_TYPES.map(function (x) { return '.' + x; }).join(',') + ',audio/*' });

            input.addEventListener('change', function () {
                if (input.files && input.files[0]) {
                    self.closeConfirm();
                    self.soundImport(input.files[0]);
                }
            });
            input.click();
        } }, [icon('upload'), el('span', { text: t('soundComputer') })]);
    };

    // Into Files beside the picture or in the canvas's folder
    // (sound_upload), then a sound at the playhead (addSound()).
    Editor.prototype.soundImport = function (file) {
        var self = this, head;

        if (!this.importApi()) {
            this.showMessage(t('soundNeedsFiles'), true);
            return;
        }

        if (this.sounds().length >= M.SOUND_MAX) {
            return;
        }

        head = {
            action: 'sound_upload', file_id: this.opts.fileId || 0,
            folder_id: (this.opts.save && this.opts.save.folderId) || 0, name: file.name || ''
        };
        this.showMessage(t('soundUploading'));
        this.uploadSend(file, 'sound', head).then(function (r) {
            self.hideMessage();
            self.addSound({ id: r.id, name: r.name, url: r.url, size: bytesText(r.size || file.size) });
        }, function (e) {
            self.showMessage(e.message || t('uploadError'), true);
        });
    };
})(window.PgImageEditor.parts);
