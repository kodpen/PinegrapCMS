/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rich text editor's (CKEditor 4) HTML source in the code editor facade
 * (window.PgCodeEditor, assets/js/pg_code_editor.js), the editor every other
 * code field of the panel uses.
 *
 * A CKEditor plugin, "pgcodeeditor", for the two places CKEditor shows its
 * HTML: the Source mode (the sourcearea plugin, a textarea in place of the
 * editing area) and the Source dialog (the sourcedialog plugin, a textarea in
 * a dialog). In both the facade's editor takes the textarea's place and
 * writes every edit back into it, so CKEditor reads the HTML from the
 * textarea as it always did and nothing about saving changes.
 *
 * The facade and its engine are fetched the first time either opens: through
 * window.pgCodeEditorUse() on a panel screen (backend.src.js), from
 * window.pgCodeEditorConfig.facade on a site page, where an editor edits a
 * region or a visitor fills a rich text field. When neither can be had the
 * textarea stays, which is what CKEditor shows without a code editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    if (typeof CKEDITOR === 'undefined' || CKEDITOR.plugins.get('pgcodeeditor')) {
        return;
    }

    var waiting = null;     // callbacks while the facade loads on a site page

    // Runs `run` once the facade is on the page (or once it is known that it
    // will not be).
    function useFacade(run) {
        if (window.PgCodeEditor) {
            if (window.pgCodeEditorConfig) {
                window.PgCodeEditor.configure(window.pgCodeEditorConfig);
            }
            run();
            return;
        }

        if (typeof window.pgCodeEditorUse === 'function') {
            window.pgCodeEditorUse(run);
            return;
        }

        var config = window.pgCodeEditorConfig || {};

        if (!config.facade) {
            run();
            return;
        }

        if (waiting) {
            waiting.push(run);
            return;
        }

        waiting = [run];

        var script = document.createElement('script');
        script.src = config.facade;
        script.onload = script.onerror = function () {
            var callbacks = waiting;
            waiting = null;
            if (window.PgCodeEditor) {
                window.PgCodeEditor.configure(config);
            }
            callbacks.forEach(function (callback) {
                try { callback(); } catch (e) {}
            });
        };
        document.head.appendChild(script);
    }

    // The panel's colour scheme, as the panel stores it; a site page without
    // that choice follows the system's.
    function theme() {
        try {
            if (document.cookie.indexOf('prefers-color-scheme=dark') !== -1) return 'dark';
            if (document.cookie.indexOf('prefers-color-scheme=light') !== -1) return 'light';
        } catch (e) {}
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }

    // The facade's editor over `textarea`, `height` tall. Resolves with the
    // instance, or with null when there is no editor to be had.
    function attachTo(textarea, height, done) {
        useFacade(function () {
            if (!window.PgCodeEditor || !textarea.parentNode) {
                done(null);
                return;
            }

            window.PgCodeEditor.attach(textarea, {
                language: 'html',
                theme: theme(),
                height: height,
                className: 'pg-cke-source',
                labels: (window.pgCodeEditorConfig && window.pgCodeEditorConfig.labels) || {}
            }).then(done, function () { done(null); });
        });
    }

    CKEDITOR.plugins.add('pgcodeeditor', {
        init: function (editor) {

            // ── Source mode ────────────────────────────────────────────────
            var source = null;      // the facade's editor while Source is on
            var turn = 0;           // which entry into Source mode is current

            editor.on('mode', function () {
                if (editor.mode !== 'source') {
                    return;
                }

                var editable = editor.editable();
                var textarea = editable ? editable.$ : null;

                if (!textarea || textarea.tagName !== 'TEXTAREA') {
                    return;
                }

                var mine = ++turn;

                attachTo(textarea, '100%', function (instance) {
                    // Left Source mode while the editor was on its way.
                    if (!instance || mine !== turn || editor.mode !== 'source') {
                        if (instance) instance.destroy();
                        return;
                    }

                    source = instance;
                    // CKEditor's own events still say the content changed, for
                    // the undo stack and the screen's unsaved-changes checks.
                    instance.textarea.addEventListener('input', function () {
                        editor.fire('change');
                    });
                    instance.focus();
                });
            });

            editor.on('beforeModeUnload', function () {
                turn++;

                if (source) {
                    // The textarea already holds the text: attach() writes
                    // every edit into it, and CKEditor reads it from there.
                    source.destroy();
                    source = null;
                }
            });

            editor.on('destroy', function () {
                turn++;

                if (source) {
                    source.destroy();
                    source = null;
                }
            });
        }
    });

    // ── Source dialog ──────────────────────────────────────────────────────
    CKEDITOR.on('dialogDefinition', function (event) {
        if (event.data.name !== 'sourcedialog') {
            return;
        }

        var definition = event.data.definition;
        var onShow = definition.onShow;
        var onHide = definition.onHide;

        definition.onShow = function () {
            if (onShow) onShow.apply(this, arguments);

            var dialog = this;
            var field = dialog.getContentElement('main', 'data');
            var textarea = field ? field.getInputElement().$ : null;

            if (!textarea) {
                return;
            }

            var mine = dialog._pgCodeTurn = (dialog._pgCodeTurn || 0) + 1;

            attachTo(textarea, (textarea.offsetHeight || 400) + 'px', function (instance) {
                if (!instance || mine !== dialog._pgCodeTurn) {
                    if (instance) instance.destroy();
                    return;
                }
                dialog._pgCodeEditor = instance;
                instance.focus();
            });
        };

        definition.onHide = function () {
            this._pgCodeTurn = (this._pgCodeTurn || 0) + 1;

            if (this._pgCodeEditor) {
                this._pgCodeEditor.destroy();
                this._pgCodeEditor = null;
            }

            if (onHide) onHide.apply(this, arguments);
        };
    });
})();
