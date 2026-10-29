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

    // The facade's editor over `textarea`, `height` tall (and `extra`
    // options, the dialog's width and isolation). Calls `done` with the
    // instance, or with null when there is no editor to be had.
    function attachTo(textarea, height, done, extra) {
        useFacade(function () {
            if (!window.PgCodeEditor || !textarea.parentNode) {
                done(null);
                return;
            }

            var options = {
                language: 'html',
                theme: theme(),
                height: height,
                className: 'pg-cke-source',
                labels: (window.pgCodeEditorConfig && window.pgCodeEditorConfig.labels) || {}
            };

            for (var key in (extra || {})) {
                if (Object.prototype.hasOwnProperty.call(extra, key)) options[key] = extra[key];
            }

            window.PgCodeEditor.attach(textarea, options).then(done, function () { done(null); });
        });
    }

    // CKEditor's dialog presses OK on Enter and moves the focus on Tab. In
    // the code both belong to the editor, as does a key the editor used
    // (Escape closing its search or its completion list), so those stop at
    // the editor. Escape the editor leaves alone still cancels the dialog.
    function keepKeys(container) {
        container.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter') || (event.key === 'Tab') || event.defaultPrevented) {
                event.stopPropagation();
            }
        });
    }

    // The caption a field's textarea stands for: its aria-label, else the
    // text of its aria-labelledby elements, else its first label without the
    // hidden parts (the required marker's "*"). The rule of the code
    // editor's pgCeTextareaName() (pg_code_editor.js), which is not loaded
    // yet when the rich text editor starts.
    function fieldName(textarea) {
        var name = textarea.getAttribute('aria-label') || '';
        var ids = textarea.getAttribute('aria-labelledby');
        if (!name && ids) {
            name = ids.split(/\s+/).map(function (id) {
                var el = id ? document.getElementById(id) : null;
                return el ? el.textContent : '';
            }).join(' ');
        }
        if (!name.trim() && textarea.labels && textarea.labels.length) {
            var copy = textarea.labels[0].cloneNode(true);
            var hidden = copy.querySelectorAll('[aria-hidden="true"]');
            for (var i = 0; i < hidden.length; i++) hidden[i].parentNode.removeChild(hidden[i]);
            name = copy.textContent;
        }
        return name.replace(/\s+/g, ' ').trim();
    }

    // CKEDITOR.replace() for the field `id` with `config` shared by every
    // field of the page. The editor is named by the field's caption: the
    // editing frame's title and its body's aria-label (config.title), and the
    // voice label of the role=application wrapper (config.applicationTitle,
    // which otherwise reads "Rich Text Editor, <element id>"). CKEditor puts
    // the voice label into its markup without encoding it, so it gets the
    // HTML-encoded name. Without a caption the shared config stays as it is.
    // CKEditor hides the textarea, so a click on its label would reach
    // nothing; it focuses the editor instead.
    window.pgCkeditorReplace = function (id, config) {
        var textarea = document.getElementById(id);
        var name = textarea ? fieldName(textarea) : '';
        var own = config;

        if (name) {
            own = CKEDITOR.tools.extend({}, config);
            own.title = name;
            own.applicationTitle = CKEDITOR.tools.htmlEncode(name);
        }

        var editor = CKEDITOR.replace(id, own);
        var labels = (textarea && textarea.labels) || [];

        for (var i = 0; i < labels.length; i++) {
            labels[i].addEventListener('click', function (event) {
                event.preventDefault();
                if (editor.status === 'ready') {
                    editor.focus();
                }
            });
        }

        return editor;
    };

    // Says under an editor in Source mode that Tab indents and which key
    // makes it move to the next field (the facade's Ctrl+M, Shift+Alt+M on a
    // Mac), as pg_code_editor_attach() does under a code field. Returns the
    // line, or null without the text.
    function keysHint(editor) {
        var labels = (window.pgCodeEditorConfig && window.pgCodeEditorConfig.labels) || {};

        if (!labels.keysHint || !editor.container) {
            return null;
        }

        var platform = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || navigator.userAgent || '';
        var hint = document.createElement('div');
        hint.id = 'cke_' + editor.name + '_keys_hint';
        hint.className = 'form-text';
        hint.textContent = String(labels.keysHint).replace('{var}', /mac|iphone|ipad|ipod/i.test(platform) ? 'Shift+Alt+M' : 'Ctrl+M');

        var container = editor.container.$;
        container.parentNode.insertBefore(hint, container.nextSibling);

        return hint;
    }

    // CKEditor's keystroke handler listens on the editable, the source
    // textarea the code editor hides; keys typed in the code editor never
    // reach it. Alt+F10 (toolbar, where the Source button leaves Source mode)
    // and Alt+0 (accessibility help) are passed on from the editor's host.
    function forwardKeys(editor, container) {
        container.addEventListener('keydown', function (event) {
            if (!event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            var command = (event.key === 'F10') ? 'toolbarFocus'
                : ((event.code === 'Digit0') || (event.key === '0')) ? 'a11yHelp'
                : '';

            if (command && editor.getCommand(command)) {
                event.preventDefault();
                event.stopPropagation();
                editor.execCommand(command);
            }
        });
    }

    CKEDITOR.plugins.add('pgcodeeditor', {
        init: function (editor) {

            // ── Source mode ────────────────────────────────────────────────
            var source = null;      // the facade's editor while Source is on
            var hint = null;        // the line under it that says how Tab leaves
            var turn = 0;           // which entry into Source mode is current

            function leaveSource() {
                turn++;

                if (source) {
                    // The textarea already holds the text: attach() writes
                    // every edit into it, and CKEditor reads it from there.
                    source.destroy();
                    source = null;
                }

                if (hint) {
                    if (hint.parentNode) hint.parentNode.removeChild(hint);
                    hint = null;
                }
            }

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

                // CKEditor names the source textarea only in an inline
                // editor; the code editor takes its name from there.
                if (editor.title && !textarea.getAttribute('aria-label')) {
                    textarea.setAttribute('aria-label', editor.title);
                }

                hint = keysHint(editor);

                attachTo(textarea, '100%', function (instance) {
                    // Left Source mode while the editor was on its way.
                    if (!instance || mine !== turn || editor.mode !== 'source') {
                        if (instance) instance.destroy();
                        // No code editor: the plain textarea stays, where Tab
                        // moves on, so the line would be wrong.
                        if (!instance && (mine === turn) && hint) {
                            if (hint.parentNode) hint.parentNode.removeChild(hint);
                            hint = null;
                        }
                        return;
                    }

                    source = instance;
                    forwardKeys(editor, instance.container || instance.host);
                    // editor.focus() (Escape from the toolbar, a click on the
                    // field's label) focuses the editable, which is the
                    // hidden textarea now.
                    editable.focus = function () {
                        if (source) source.focus();
                    };
                    // CKEditor's own events still say the content changed, for
                    // the undo stack and the screen's unsaved-changes checks.
                    instance.textarea.addEventListener('input', function () {
                        editor.fire('change');
                    });
                    instance.focus();
                }, hint ? { describedBy: hint.id } : null);
            });

            editor.on('beforeModeUnload', leaveSource);
            editor.on('destroy', leaveSource);
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

            // The textarea gives the dialog its size (sourcedialog sets it
            // from the window); the editor takes the same once it is hidden,
            // or the dialog shrinks to its narrowest. The dialog resets every
            // element inside it, so the editor lives in a shadow root.
            attachTo(textarea, (textarea.offsetHeight || 400) + 'px', function (instance) {
                if (!instance || mine !== dialog._pgCodeTurn) {
                    if (instance) instance.destroy();
                    return;
                }
                keepKeys(instance.container || instance.host);
                dialog._pgCodeEditor = instance;
                instance.focus();
            }, { width: (textarea.offsetWidth || 800) + 'px', isolate: true });
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
