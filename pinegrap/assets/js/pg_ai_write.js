/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Write with AI": the button beside a field asks Pinegrap AI for a draft of
 * it (api.php, action ai_write) and shows the draft in a small panel under
 * the field's label, with the tone, an optional request, "Use this text" and
 * "Try again". Nothing reaches the field until the person uses the draft, and
 * nothing is saved until the form is.
 *
 * The buttons are drawn by pg_ai_write_button() (includes/fn/ai_write.php),
 * each with its settings in data-pg-ai-write: the target and the record, the
 * id of the field the draft goes into, its format (html for the rich text
 * editor, line for an input) and which element holds each value the model is
 * given. The words arrive from PHP through window.PinegrapAiWrite so they
 * stay inside lang().
 *
 * A draft in the html format comes back from the server already as markup
 * built from escaped words; a one-line draft is only ever set as text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var cfg = window.PinegrapAiWrite || {};
    var labels = cfg.labels || {};
    var tones = (cfg.tones && cfg.tones.length) ? cfg.tones : [['neutral', 'neutral']];

    // The tone chosen last is where the next panel starts.
    var lastTone = tones[0][0];

    function label(key) {
        return Object.prototype.hasOwnProperty.call(labels, key) ? String(labels[key]) : key;
    }

    function apiUrl() {
        return (typeof window.pg_api_url === 'function') ? window.pg_api_url() : 'api.php';
    }

    function formToken() {
        return (typeof window.software_token !== 'undefined') ? window.software_token : '';
    }

    function settingsOf(button) {
        try {
            var settings = JSON.parse(button.getAttribute('data-pg-ai-write') || '{}');
            return (settings && typeof settings === 'object') ? settings : {};
        } catch (e) {
            return {};
        }
    }

    // The rich text editor of a textarea, when one is running on it.
    function editorOf(id) {
        var editors = (window.CKEDITOR && window.CKEDITOR.instances) ? window.CKEDITOR.instances : null;

        return (editors && editors[id]) ? editors[id] : null;
    }

    function valueOf(id) {
        var editor = editorOf(id);

        if (editor) {
            return String(editor.getData() || '');
        }

        var el = document.getElementById(id);

        if (!el) {
            return '';
        }

        return (typeof el.value === 'string') ? el.value : String(el.textContent || '');
    }

    // The form's values right now, unsaved edits included.
    function contextOf(settings) {
        var out = {};
        var map = settings.context || {};

        for (var key in map) {
            if (Object.prototype.hasOwnProperty.call(map, key)) {
                var value = valueOf(String(map[key]));

                if (value.replace(/\s+/g, '') !== '') {
                    out[key] = value;
                }
            }
        }

        return out;
    }

    function make(tag, className, text) {
        var el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        if (text !== undefined) {
            el.textContent = text;
        }

        return el;
    }

    function panelId(settings) {
        return 'pg_ai_draft_' + String(settings.field || '').replace(/[^A-Za-z0-9_-]/g, '');
    }

    /* --------------------------------------------------------------- panel */

    function buildPanel(button, settings) {
        var panel = make('div', 'pg-ai-draft border rounded-3 p-2 mb-2 bg-body-tertiary');
        panel.id = panelId(settings);
        panel.setAttribute('role', 'group');
        panel.setAttribute('aria-label', label('panel'));

        var head = make('div', 'd-flex flex-wrap align-items-center gap-2 mb-2');
        var title = make('span', 'small fw-semibold text-primary');
        var icon = make('i', 'bi bi-stars me-1');
        icon.setAttribute('aria-hidden', 'true');
        title.appendChild(icon);
        title.appendChild(document.createTextNode(label('title')));
        head.appendChild(title);

        var tone = make('select', 'form-select form-select-sm w-auto ms-auto pg-ai-draft-tone');
        tone.setAttribute('aria-label', label('tone'));

        for (var i = 0; i < tones.length; i++) {
            var option = make('option', '', String(tones[i][1]));
            option.value = String(tones[i][0]);
            option.selected = (option.value === lastTone);
            tone.appendChild(option);
        }

        head.appendChild(tone);

        var close = make('button', 'btn-close');
        close.type = 'button';
        close.setAttribute('aria-label', label('close'));
        head.appendChild(close);
        panel.appendChild(head);

        var body = make('div', 'pg-ai-draft-body small');
        body.setAttribute('aria-live', 'polite');
        panel.appendChild(body);

        var hint = make('input', 'form-control form-control-sm mt-2 pg-ai-draft-hint');
        hint.type = 'text';
        hint.maxLength = 300;
        hint.placeholder = label('hint');
        hint.setAttribute('aria-label', label('hint'));
        panel.appendChild(hint);

        var foot = make('div', 'd-flex flex-wrap align-items-center gap-2 mt-2');
        var use = make('button', 'btn btn-sm btn-primary pg-ai-draft-use', label('use'));
        use.type = 'button';
        use.disabled = true;
        foot.appendChild(use);

        var again = make('button', 'btn btn-sm btn-outline-secondary pg-ai-draft-again');
        again.type = 'button';
        var againIcon = make('i', 'bi bi-arrow-clockwise me-1');
        againIcon.setAttribute('aria-hidden', 'true');
        again.appendChild(againIcon);
        again.appendChild(document.createTextNode(label('again')));
        foot.appendChild(again);
        foot.appendChild(make('span', 'form-text m-0 ms-auto', label('check')));
        panel.appendChild(foot);

        panel._pgAi = {
            button: button, settings: settings, seq: 0, result: null,
            tone: tone, hint: hint, body: body, use: use, again: again
        };

        close.addEventListener('click', function () {
            closePanel(panel, true);
        });

        tone.addEventListener('change', function () {
            lastTone = tone.value;
            ask(panel);
        });

        again.addEventListener('click', function () {
            ask(panel);
        });

        use.addEventListener('click', function () {
            apply(panel);
        });

        hint.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                ask(panel);
            }
        });

        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closePanel(panel, true);
            }
        });

        return panel;
    }

    function openPanel(button) {
        var settings = settingsOf(button);

        if (!settings.target || !settings.field) {
            return;
        }

        var existing = document.getElementById(panelId(settings));

        if (existing) {
            closePanel(existing, true);
            return;
        }

        var panel = buildPanel(button, settings);

        // Under the label row the button sits in, right above the field.
        var row = button.parentNode;
        row.parentNode.insertBefore(panel, row.nextSibling);
        button.setAttribute('aria-expanded', 'true');

        ask(panel);
    }

    function closePanel(panel, focusButton) {
        var state = panel._pgAi;

        // An answer still on its way is ignored.
        state.seq++;
        state.button.setAttribute('aria-expanded', 'false');

        if (panel.parentNode) {
            panel.parentNode.removeChild(panel);
        }

        if (focusButton) {
            state.button.focus();
        }
    }

    function setBusy(panel, busy) {
        var state = panel._pgAi;

        state.again.disabled = busy;
        state.tone.disabled = busy;
        state.use.disabled = busy || !state.result;
    }

    function showWriting(panel) {
        var body = panel._pgAi.body;
        var spinner = make('span', 'spinner-border spinner-border-sm me-2');
        spinner.setAttribute('aria-hidden', 'true');

        body.textContent = '';
        body.className = 'pg-ai-draft-body small text-body-secondary';
        body.appendChild(spinner);
        body.appendChild(document.createTextNode(label('writing')));
    }

    function showError(panel, message) {
        var body = panel._pgAi.body;

        body.className = 'pg-ai-draft-body small text-danger';
        body.textContent = message;
    }

    function showDraft(panel, result) {
        var state = panel._pgAi;
        var body = state.body;
        var box = make('div', 'pg-ai-draft-text border rounded bg-body p-2');

        box.style.maxHeight = '18rem';
        box.style.overflow = 'auto';

        // The html format is markup the server built from escaped words.
        if (state.settings.format === 'html') {
            box.innerHTML = String(result.html || '');
        } else {
            box.textContent = String(result.text || '');
        }

        body.textContent = '';
        body.className = 'pg-ai-draft-body small';
        body.appendChild(box);

        if ((typeof result.remaining === 'number') && (result.remaining <= 20)) {
            body.appendChild(make('div', 'form-text', label('left').replace('{n}', String(result.remaining))));
        }
    }

    function ask(panel) {
        var state = panel._pgAi;
        var settings = state.settings;
        var seq = ++state.seq;

        state.result = null;
        setBusy(panel, true);
        showWriting(panel);

        var payload = {
            action: 'ai_write',
            token: formToken(),
            target: settings.target,
            record: settings.record,
            record_id: settings.record_id || 0,
            context: contextOf(settings),
            tone: state.tone.value,
            hint: state.hint.value
        };

        fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (seq !== state.seq) {
                return;
            }

            if (data && (data.status === 'success')) {
                state.result = data;
                showDraft(panel, data);
            } else {
                showError(panel, (data && data.message) ? String(data.message) : label('failed'));
            }

            setBusy(panel, false);
        }).catch(function () {
            if (seq !== state.seq) {
                return;
            }

            showError(panel, label('failed'));
            setBusy(panel, false);
        });
    }

    // The draft into the field, the way typing would have put it there: the
    // field's own listeners (the SEO counters, the unsaved-changes check)
    // hear about it.
    function apply(panel) {
        var state = panel._pgAi;
        var settings = state.settings;
        var result = state.result;

        if (!result) {
            return;
        }

        var editor = editorOf(settings.field);
        var field = document.getElementById(settings.field);
        var value = (settings.format === 'html') ? String(result.html || '') : String(result.text || '');

        if (editor && (settings.format === 'html')) {
            editor.setData(value);
            closePanel(panel, false);
            editor.focus();
            return;
        }

        if (!field) {
            return;
        }

        field.value = value;

        var events = ['input', 'change'];

        for (var i = 0; i < events.length; i++) {
            var event;

            if (typeof window.Event === 'function') {
                event = new window.Event(events[i], { bubbles: true });
            } else {
                event = document.createEvent('Event');
                event.initEvent(events[i], true, true);
            }

            field.dispatchEvent(event);
        }

        closePanel(panel, false);
        field.focus();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = (target && target.closest) ? target.closest('.pg-ai-write-btn') : null;

        if (button) {
            event.preventDefault();
            openPanel(button);
        }
    });
}());
