/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Write with AI": the button beside a field asks for a draft of it and shows
 * the draft in a small panel under the field's label, with the tone, an
 * optional request, "Use this text" and "Try again". Nothing reaches the
 * field until the person uses the draft, and nothing is saved until the form
 * is.
 *
 * Two models can write (window.PinegrapAiWrite.engine, from
 * pg_ai_write_engine()):
 *
 * - pinegrap: the site's Pinegrap AI. One request (api.php, action
 *   ai_write) and the server answers with the draft.
 * - browser: the model of the person's own browser (Gemini Nano through the
 *   Prompt API of a recent desktop Chrome), for a site without Pinegrap AI.
 *   The buttons come hidden and are shown only in a browser whose model can
 *   write the site's language, or can write English that the browser's
 *   Translator API then brings into it. The server checks the request and
 *   says what to ask (step "prompt"), the model writes on the device, and the
 *   server makes the answer fit for the field (step "finish"). The model is
 *   created in the click itself: one the browser has yet to download wants
 *   the person's gesture, which is gone once anything is awaited.
 *
 * The buttons are drawn by pg_ai_write_button() (includes/fn/ai_write.php),
 * each with its settings in data-pg-ai-write: the target and the record, the
 * id of the field the draft goes into, its format (html for the rich text
 * editor, line for an input) and which element holds each value the model is
 * given. The words arrive from PHP through window.PinegrapAiWrite so they
 * stay inside lang().
 *
 * A draft in the html format comes back from the server already as markup
 * built from escaped words, whichever model wrote it; a one-line draft is
 * only ever set as text.
 *
 * A used draft is announced with the event pg:ai-write-used from the field
 * (announce()); the screens that mark the texts written with AI listen for
 * it (assets/js/pg_ai_fields.js).
 *
 * An image's alt text (settings.image: the element holding the image's
 * address) is written by the browser's model whatever engine the site has:
 * nothing says Pinegrap AI's model reads images, and the image goes from
 * the browser to its own model only. Such a button comes hidden and the
 * screen shows it when window.PinegrapAiWrite.imageReady() resolves true:
 * the model takes image input in this browser.
 *
 * A box that is no form field (the Workspace writing box) gives its button
 * an adapter, button.pgAiWrite: context() the values to send, place(panel)
 * puts the panel on the screen, use(text) takes the draft in through the
 * box's own change path. Such buttons are drawn by a script after this one
 * has run: window.PinegrapAiWrite.ready says a model writes in this browser,
 * and pg:ai-write-ready is sent on the document when it becomes true.
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
    var engine = (cfg.engine === 'browser') ? 'browser' : 'pinegrap';
    var siteLanguage = /^[a-z]{2,3}$/.test(String(cfg.language || '')) ? String(cfg.language) : 'en';

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

    // The TinyMCE 3 editor of a textarea (the "previous" editor version)
    // once it runs on it. It keeps its own copy of the text and writes it
    // back into the textarea when the form is sent, so the textarea alone is
    // neither read nor written while it runs. While it loads, or when it is
    // turned off, the textarea is the field.
    function tinyOf(id) {
        var tiny = window.tinymce || window.tinyMCE;
        var editor = (tiny && (typeof tiny.get === 'function')) ? tiny.get(id) : null;

        return (editor && editor.initialized && !editor.isHidden()) ? editor : null;
    }

    function valueOf(id) {
        var editor = editorOf(id);
        var tiny = editor ? null : tinyOf(id);

        if (editor) {
            return String(editor.getData() || '');
        }

        if (tiny) {
            return String(tiny.getContent() || '');
        }

        var el = document.getElementById(id);

        if (!el) {
            return '';
        }

        return (typeof el.value === 'string') ? el.value : String(el.textContent || '');
    }

    // The record the draft is for. In the visual designer every tab is a
    // page, so the page is the one open when the button is pressed.
    function recordIdOf(settings) {
        if ((settings.record_from === 'designer') && (typeof StyleDesigner !== 'undefined') && StyleDesigner
            && (typeof StyleDesigner.aiBridge === 'function')) {
            var page = StyleDesigner.aiBridge().page();

            return page ? (parseInt(page.page_id, 10) || 0) : 0;
        }

        return settings.record_id || 0;
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
        var panel = make('div', 'pg-ai-draft border rounded-5 p-3 mb-3 bg-1');
        panel.id = panelId(settings);

        // On a dark screen that does not use Bootstrap's colour modes (the
        // designer's settings), the panel takes the dark ones itself.
        if (settings.theme === 'dark') {
            panel.setAttribute('data-bs-theme', 'dark');
        }
        panel.setAttribute('role', 'group');
        panel.setAttribute('aria-label', label('panel'));

        var head = make('div', 'd-flex flex-wrap align-items-center gap-3 mb-3');
        var title = make('span', 'small fw-semibold fg-primary');
        var icon = make('i', 'bi bi-stars me-1');
        icon.setAttribute('aria-hidden', 'true');
        title.appendChild(icon);
        title.appendChild(document.createTextNode(onBrowser(settings) ? label('title_browser') : label('title')));
        head.appendChild(title);

        // An image's alt text says what the image shows: no tone.
        var tone = make('select', 'form-control form-control-sm w-auto ms-auto pg-ai-draft-tone' + (settings.image ? ' d-none' : ''));
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

        var hint = make('input', 'form-control form-control-sm mt-3 pg-ai-draft-hint');
        hint.type = 'text';
        hint.maxLength = 300;
        hint.placeholder = label('hint');
        hint.setAttribute('aria-label', label('hint'));
        panel.appendChild(hint);

        var foot = make('div', 'd-flex flex-wrap align-items-center gap-3 mt-3');
        var use = make('button', 'btn btn-sm btn-solid theme-primary pg-ai-draft-use', label('use'));
        use.type = 'button';
        use.disabled = true;
        foot.appendChild(use);

        var again = make('button', 'btn btn-sm btn-outline theme-secondary pg-ai-draft-again');
        again.type = 'button';
        var againIcon = make('i', 'bi bi-arrow-clockwise me-1');
        againIcon.setAttribute('aria-hidden', 'true');
        again.appendChild(againIcon);
        again.appendChild(document.createTextNode(label('again')));
        foot.appendChild(again);
        foot.appendChild(make('span', 'form-text m-0 ms-auto', label('check')));
        panel.appendChild(foot);

        panel._pgAi = {
            button: button, settings: settings, seq: 0, over: 0, result: null, held: [],
            adapter: button.pgAiWrite || null,
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

                // Like "Try again" and the tone, Enter does nothing while a
                // request runs: each request takes a draft from the day's
                // quota.
                if (!again.disabled) {
                    ask(panel);
                }
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

        // Under the label row the button sits in, right above the field;
        // where the box's adapter says.
        if (button.pgAiWrite && (typeof button.pgAiWrite.place === 'function')) {
            button.pgAiWrite.place(panel);
        } else {
            var row = button.parentNode;
            row.parentNode.insertBefore(panel, row.nextSibling);
        }

        button.setAttribute('aria-expanded', 'true');

        ask(panel);
    }

    function closePanel(panel, focusButton) {
        var state = panel._pgAi;

        // An answer still on its way is ignored; the browser's model stops.
        state.seq++;
        releaseHeld(state);
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

    // What is going on, with a spinner: writing, a download, a translation.
    function showStatus(panel, text) {
        var body = panel._pgAi.body;
        var spinner = make('span', 'spinner-border spinner-border-sm me-3');
        spinner.setAttribute('aria-hidden', 'true');

        body.textContent = '';
        body.className = 'pg-ai-draft-body small fg-secondary';
        body.appendChild(spinner);
        body.appendChild(document.createTextNode(text));
    }

    function showError(panel, message) {
        var body = panel._pgAi.body;

        body.className = 'pg-ai-draft-body small fg-danger';
        body.textContent = message;
    }

    function showDraft(panel, result) {
        var state = panel._pgAi;
        var body = state.body;
        var box = make('div', 'pg-ai-draft-text border rounded-4 bg-body p-3');

        box.style.maxHeight = '18rem';
        box.style.overflow = 'auto';

        // The html format is markup the server built from escaped words; a
        // plain text draft keeps its line breaks, as the field will.
        if (state.settings.format === 'html') {
            box.innerHTML = String(result.html || '');
        } else {
            box.style.whiteSpace = 'pre-wrap';
            box.textContent = String(result.text || '');
        }

        body.textContent = '';
        body.className = 'pg-ai-draft-body small';
        body.appendChild(box);

        if ((typeof result.remaining === 'number') && (result.remaining <= 20)) {
            body.appendChild(make('div', 'form-text', label('left').replace('{n}', String(result.remaining))));
        }
    }

    /* -------------------------------------------------------------- asking */

    // An error whose words the panel shows as they are.
    function failure(message) {
        var error = new Error(message);
        error.pgMessage = message;
        return error;
    }

    function post(payload) {
        return fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json();
        }).then(null, function () {
            throw failure(label('failed'));
        });
    }

    // The server's answer, or its refusal as an error.
    function accepted(data) {
        if (data && (data.status === 'success')) {
            return data;
        }

        throw failure((data && data.message) ? String(data.message) : label('failed'));
    }

    // What the server is told about the field and the form right now.
    function requestOf(panel) {
        var state = panel._pgAi;
        var settings = state.settings;

        return {
            action: 'ai_write',
            token: formToken(),
            target: settings.target,
            record: settings.record,
            record_id: recordIdOf(settings),
            field_id: settings.field_id || 0,
            context: (state.adapter && (typeof state.adapter.context === 'function')) ? state.adapter.context() : contextOf(settings),
            tone: state.tone.value,
            hint: state.hint.value
        };
    }

    // A new request: the one before it is forgotten.
    function begin(panel, text) {
        var state = panel._pgAi;
        var seq = ++state.seq;

        releaseHeld(state);
        state.result = null;
        setBusy(panel, true);
        showStatus(panel, text);

        return seq;
    }

    function finish(panel, seq, data, error) {
        var state = panel._pgAi;

        if ((seq !== state.seq) || (state.over === seq)) {
            return;
        }

        // A model still on its way for this request is let go when it comes.
        state.over = seq;
        releaseHeld(state);

        if (data) {
            state.result = data;
            showDraft(panel, data);
        } else if (error && error.pgMessage) {
            showError(panel, error.pgMessage);
        } else {
            showError(panel, onBrowser(state.settings) ? label('browser_failed') : label('failed'));
        }

        setBusy(panel, false);
    }

    // Whether the browser's model writes this field's draft.
    function onBrowser(settings) {
        return (engine === 'browser') || !!settings.image;
    }

    function ask(panel) {
        if (onBrowser(panel._pgAi.settings)) {
            askBrowser(panel);
        } else {
            askPinegrap(panel);
        }
    }

    function askPinegrap(panel) {
        var seq = begin(panel, label('writing'));

        post(requestOf(panel)).then(accepted).then(function (data) {
            finish(panel, seq, data, null);
        }, function (error) {
            finish(panel, seq, null, error);
        });
    }

    /* ------------------------------------------- the browser's built-in AI */

    // The answer's shape: the same object Pinegrap AI is asked for.
    var ANSWER = { type: 'object', properties: { text: { type: 'string' } }, required: ['text'] };

    // How this browser writes the site's language: mode 'direct', 'bridge'
    // (in English, then translated) or '' when it cannot, and input: the
    // languages the model is told it reads. One plan for text, one for a
    // text that comes with an image.
    var textPlan = { mode: '', input: ['en'] };
    var imagePlan = null;
    var imagePlanNow = null;

    function usable(state) {
        return !!state && (state !== 'unavailable') && (state !== 'no');
    }

    // A browser that has the API but no model behind it may never settle an
    // availability() promise.
    function withTimeout(promise, ms) {
        return new Promise(function (resolve, reject) {
            var timer = window.setTimeout(function () {
                reject(new Error('timeout'));
            }, ms);

            Promise.resolve(promise).then(function (value) {
                window.clearTimeout(timer);
                resolve(value);
            }, function (error) {
                window.clearTimeout(timer);
                reject(error);
            });
        });
    }

    function release(model) {
        try {
            if (model && (typeof model.destroy === 'function')) {
                model.destroy();
            }
        } catch (e) { /* already gone */ }
    }

    // The models of the request under way; destroying one stops its work.
    function releaseHeld(state) {
        var held = state.held || [];

        state.held = [];

        for (var i = 0; i < held.length; i++) {
            release(held[i]);
        }
    }

    function modelSupported() {
        return (typeof window.LanguageModel !== 'undefined') && window.LanguageModel
            && (typeof window.LanguageModel.create === 'function') && (typeof window.LanguageModel.availability === 'function');
    }

    function translatorSupported() {
        return (typeof window.Translator !== 'undefined') && window.Translator
            && (typeof window.Translator.create === 'function') && (typeof window.Translator.availability === 'function');
    }

    // What the model is told it gets: text in these languages, and an
    // image when one comes with it.
    function inputsOf(languages, image) {
        var inputs = [{ type: 'text', languages: languages }];

        if (image) {
            inputs.push({ type: 'image' });
        }

        return inputs;
    }

    // The rules are English and the data is in the site's language, which
    // the model reads even when it does not officially (Turkish, for one):
    // the languages are tried from the most exact down to English.
    function planBrowser(image) {
        var plan = { mode: '', input: ['en'] };

        if (!window.Promise || !modelSupported()) {
            return Promise.resolve(plan);
        }

        var check = function (input, output) {
            return withTimeout(window.LanguageModel.availability({
                expectedInputs: inputsOf(input, image),
                expectedOutputs: [{ type: 'text', languages: [output] }]
            }), 10000).then(function (state) {
                if (usable(state)) {
                    plan.input = input;
                    return true;
                }

                return false;
            }, function () {
                return false;
            });
        };

        var direct = (siteLanguage === 'en') ? [['en']] : [['en', siteLanguage], [siteLanguage]];

        return direct.reduce(function (chain, input) {
            return chain.then(function (found) {
                return found || check(input, siteLanguage);
            });
        }, Promise.resolve(false)).then(function (found) {
            if (found) {
                return 'direct';
            }

            if ((siteLanguage === 'en') || !translatorSupported()) {
                return '';
            }

            return check(['en'], 'en').then(function (writes) {
                if (!writes) {
                    return '';
                }

                return withTimeout(window.Translator.availability({ sourceLanguage: 'en', targetLanguage: siteLanguage }), 10000)
                    .then(function (state) {
                        return usable(state) ? 'bridge' : '';
                    }, function () {
                        return '';
                    });
            });
        }).then(function (mode) {
            plan.mode = mode;
            return plan;
        });
    }

    // Whether this browser's model reads images and writes from them, asked
    // once: true or false.
    function imageReady() {
        if (!imagePlan) {
            imagePlan = planBrowser(true);
        }

        return imagePlan.then(function (plan) {
            imagePlanNow = plan;
            return plan.mode !== '';
        });
    }

    cfg.imageReady = imageReady;

    function percentOf(event) {
        var share = (event.total > 0) ? (event.loaded / event.total) : (event.loaded || 0);

        return Math.max(0, Math.min(100, Math.round(share * 100)));
    }

    function createModel(plan, output, image, monitor, signal) {
        var options = {
            expectedInputs: inputsOf(plan.input, image),
            expectedOutputs: [{ type: 'text', languages: [output] }],
            monitor: monitor
        };

        if (signal) {
            options.signal = signal;
        }

        try {
            return Promise.resolve(window.LanguageModel.create(options));
        } catch (error) {
            return Promise.reject(error);
        }
    }

    function createTranslator(monitor, signal) {
        var options = { sourceLanguage: 'en', targetLanguage: siteLanguage, monitor: monitor };

        if (signal) {
            options.signal = signal;
        }

        try {
            return Promise.resolve(window.Translator.create(options));
        } catch (error) {
            return Promise.reject(error);
        }
    }

    function promptModel(session, prompt) {
        var asked = new Promise(function (resolve) {
            resolve(session.prompt(prompt, { responseConstraint: ANSWER, omitResponseConstraintInput: true }));
        }).then(null, function (error) {
            // A browser without structured output.
            if (error && ((error.name === 'NotSupportedError') || (error.name === 'TypeError'))) {
                return session.prompt(prompt);
            }

            throw error;
        });

        return withTimeout(asked, 120000);
    }

    // The record's data with every long value halved, for a model whose
    // room the whole of it did not fit; the same text when nothing is long.
    function shrink(json) {
        var parsed;
        var changed = false;

        try {
            parsed = JSON.parse(json);
        } catch (e) {
            return json;
        }

        var cut = function (value) {
            if ((typeof value === 'string') && (value.length > 200)) {
                changed = true;
                return value.slice(0, Math.floor(value.length / 2));
            }

            return value;
        };

        if (parsed && parsed.data && (typeof parsed.data === 'object')) {
            Object.keys(parsed.data).forEach(function (key) {
                parsed.data[key] = cut(parsed.data[key]);
            });
        }

        if (parsed && (typeof parsed.current_text === 'string')) {
            parsed.current_text = cut(parsed.current_text);
        }

        return changed ? JSON.stringify(parsed) : json;
    }

    // The rules and the data in one prompt: the model was created before
    // they arrived, and a system prompt is given only at creation. An image
    // goes with them in the same message.
    function writeDraft(session, data, picture) {
        var rules = String(data.rules || '');

        var attempt = function (record, left) {
            var text = rules + '\n\n' + record;
            var prompt = picture ? [{ role: 'user', content: [{ type: 'text', value: text }, { type: 'image', value: picture }] }] : text;

            return promptModel(session, prompt).then(null, function (error) {
                var smaller = (error && (error.name === 'QuotaExceededError') && (left > 0)) ? shrink(record) : record;

                if (smaller !== record) {
                    return attempt(smaller, left - 1);
                }

                throw error;
            });
        };

        return attempt(String(data.data || ''), 3);
    }

    // The draft out of the model's answer: the "text" field, or the answer
    // itself when the browser gave no structured output.
    function answerText(raw) {
        var answer = String(raw || '').trim();

        try {
            var parsed = JSON.parse(answer.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, ''));

            if (parsed && (typeof parsed.text === 'string')) {
                return parsed.text;
            }
        } catch (e) { /* plain text */ }

        return answer;
    }

    // An English draft into the site's language, line by line: list marks
    // and empty lines stay as they are.
    function translateText(translator, text) {
        var out = [];
        var chain = Promise.resolve();

        String(text).split('\n').forEach(function (line) {
            chain = chain.then(function () {
                var match = /^(\s*(?:[-*•]|\d{1,2}[.)])\s+)?([\s\S]*)$/.exec(line);
                var mark = match[1] || '';

                if (!/\S/.test(match[2])) {
                    out.push(line);
                    return null;
                }

                return Promise.resolve(translator.translate(match[2])).then(function (done) {
                    out.push(mark + String(done || ''));
                });
            });
        });

        return chain.then(function () {
            return out.join('\n');
        });
    }

    // The picture at an address, for the model: a file of this site, or
    // of another one that lets this page read it. A failure says so.
    function loadImage(address) {
        var url;

        try {
            url = new URL(String(address || '').trim(), document.baseURI);
        } catch (e) {
            url = null;
        }

        if (!String(address || '').trim() || !url || !/^https?:$/.test(url.protocol)) {
            return Promise.reject(failure(label('no_image')));
        }

        return fetch(url.href, { credentials: 'same-origin' }).then(function (response) {
            return response.ok ? response.blob() : null;
        }).then(function (blob) {
            if (!blob || !/^image\//.test(String(blob.type || ''))) {
                throw new Error('not an image');
            }

            return blob;
        }).then(null, function () {
            throw failure(label('image_failed'));
        });
    }

    function askBrowser(panel) {
        var state = panel._pgAi;
        var seq = begin(panel, label('writing_browser'));
        var image = !!state.settings.image;
        var request = requestOf(panel);

        var current = function () {
            return (seq === state.seq) && (state.over !== seq);
        };

        // Closing the panel or asking again also stops a model still being
        // created or downloaded for this request.
        var controller = (typeof window.AbortController === 'function') ? new window.AbortController() : null;

        if (controller) {
            state.held.push({ destroy: function () { controller.abort(); } });
        }

        var monitor = function (m) {
            m.addEventListener('downloadprogress', function (event) {
                if (current()) {
                    showStatus(panel, label('downloading').replace('{n}', String(percentOf(event))));
                }
            });
        };

        // Kept for this request, or let go when it is no longer the one.
        var keep = function (made) {
            var kept = made.then(function (model) {
                if (current()) {
                    state.held.push(model);
                } else {
                    release(model);
                }

                return model;
            });

            // A failure here is reported where the model is awaited.
            kept.then(null, function () {});

            return kept;
        };

        // In the click itself, before the request below. A field that
        // describes an image was offered only once its plan was known.
        var plan = image ? (imagePlanNow || textPlan) : textPlan;
        var bridge = (plan.mode === 'bridge');
        var signal = controller ? controller.signal : null;
        var model = keep(createModel(plan, bridge ? 'en' : siteLanguage, image, monitor, signal));
        var translator = bridge ? keep(createTranslator(monitor, signal)) : null;
        var picture = image ? loadImage(valueOf(String(state.settings.image))) : null;

        if (picture) {
            // A failure here is reported where the picture is awaited.
            picture.then(null, function () {});
        }

        request.engine = 'browser';
        request.step = 'prompt';
        request.language = bridge ? 'en' : siteLanguage;

        post(request).then(accepted).then(function (data) {
            return Promise.all([model, picture]).then(function (made) {
                // A download may have shown its progress until now.
                if (current()) {
                    showStatus(panel, label('writing_browser'));
                }

                return writeDraft(made[0], data, made[1]);
            });
        }).then(function (answer) {
            var text = answerText(answer);

            if (!bridge) {
                return text;
            }

            if (current()) {
                showStatus(panel, label('translating'));
            }

            return translator.then(function (made) {
                return translateText(made, text);
            });
        }).then(function (text) {
            return post({
                action: 'ai_write',
                token: formToken(),
                engine: 'browser',
                step: 'finish',
                target: request.target,
                record: request.record,
                record_id: request.record_id,
                field_id: request.field_id,
                text: text
            }).then(accepted);
        }).then(function (data) {
            finish(panel, seq, data, null);
        }, function (error) {
            finish(panel, seq, null, error);
        });
    }

    // Tells the screen that a draft went into a field: pg:ai-write-used,
    // sent from the field's element (from the document when it has none)
    // once the field holds the draft, bubbling, naming the field. A screen
    // that marks the texts written with AI (assets/js/pg_ai_fields.js)
    // listens for it; others do not.
    function announce(settings) {
        var detail = {
            field: String(settings.field || ''),
            target: String(settings.target || ''),
            record: String(settings.record || ''),
            record_id: recordIdOf(settings),
            format: String(settings.format || '')
        };
        var source = document.getElementById(detail.field) || document;
        var event;

        try {
            event = new CustomEvent('pg:ai-write-used', { bubbles: true, detail: detail });
        } catch (e) {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('pg:ai-write-used', true, false, detail);
        }

        source.dispatchEvent(event);
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

        if (state.adapter && (typeof state.adapter.use === 'function')) {
            closePanel(panel, false);
            state.adapter.use(String(result.text || ''));
            return;
        }

        var editor = editorOf(settings.field);
        var tiny = editor ? null : tinyOf(settings.field);
        var field = document.getElementById(settings.field);
        var value = (settings.format === 'html') ? String(result.html || '') : String(result.text || '');

        // The editor loads new data in the background: the draft is announced
        // once it is in, so that a listener reads the field's new text.
        if (editor && (settings.format === 'html')) {
            editor.setData(value, function () {
                announce(settings);
            });
            closePanel(panel, false);
            editor.focus();
            return;
        }

        // TinyMCE takes the draft at once. Through its command the change is
        // one undo step and raises the editor's change event; the textarea is
        // written as well, for whatever reads it before the save.
        if (tiny && (settings.format === 'html')) {
            tiny.execCommand('mceSetContent', false, value);
            tiny.save();
            announce(settings);
            closePanel(panel, false);
            tiny.focus();
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

        announce(settings);
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

    // Tells the buttons drawn later by a script that a model writes here.
    function ready() {
        cfg.ready = true;

        try {
            document.dispatchEvent(new CustomEvent('pg:ai-write-ready'));
        } catch (e) { /* no CustomEvent: such a button stays hidden */ }
    }

    // The buttons come hidden for the browser's AI: shown where it writes.
    if (engine === 'browser') {
        planBrowser(false).then(function (plan) {
            textPlan = plan;

            if (!plan.mode) {
                return;
            }

            ready();

            var buttons = document.querySelectorAll('.pg-ai-write-btn.d-none');

            // A button with an adapter is shown by its own box.
            for (var i = 0; i < buttons.length; i++) {
                if (!buttons[i].pgAiWrite) {
                    buttons[i].classList.remove('d-none');
                }
            }
        });
    } else {
        ready();
    }
}());
