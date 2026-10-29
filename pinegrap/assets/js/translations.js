/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen: saving and reviewing a text, "update
 * translations", "clear translations", the selected texts kept as written
 * in the source language, and the two browser engines, which
 * translate on the operator's own computer: the Chrome Translator API
 * (chrome), in runs of plain text, and the browser's built-in language
 * model, Gemini Nano through the Prompt API (chrome_ai), one whole text at a
 * time with its context, the glossary and the style note. The server
 * engines run in translations_action.php; this script only starts them and
 * shows their progress.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var app = document.getElementById('pg_tr_app');

    if (!app) {
        return;
    }

    var config = {};

    try {
        config = JSON.parse(app.getAttribute('data-config') || '{}');
    } catch (e) {
        return;
    }

    var strings = config.strings || {};
    var progress = document.getElementById('pg_tr_progress');
    var progressText = progress ? progress.querySelector('.pg-tr-progress-text') : null;
    var progressBar = progress ? progress.querySelector('.pg-tr-progress-bar') : null;
    var cancelButton = progress ? progress.querySelector('.pg-tr-cancel') : null;
    var updateButton = document.getElementById('pg_tr_update');
    var running = null;   // { id, total, done, failed, stop }

    function text(key, a, b) {
        var s = strings[key] || key;
        s = s.replace('{var2}', (b === undefined) ? '' : b);
        return s.replace('{var}', (a === undefined) ? '' : a);
    }

    // A request that never reached the endpoint's own answer - the network,
    // a server or proxy time limit, an error page - is marked transient: the
    // job loop waits it out instead of giving up on the first one.
    function transientError() {
        var error = new Error(text('network_error'));
        error.transient = true;
        return error;
    }

    function call(payload) {
        payload.token = config.token;
        if (!payload.language && config.language) {
            payload.language = config.language;
        }
        return fetch(config.action_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                throw transientError();
            });
        }, function () {
            throw transientError();
        }).then(function (data) {
            if (!data || data.status !== 'success') {
                throw new Error((data && data.message) ? data.message : text('network_error'));
            }
            return data;
        });
    }

    function toast(message, kind) {
        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: message, variant: (kind === 'error') ? 'danger' : (kind || 'info'), delay: (kind === 'error') ? 8000 : 4000 });
            return;
        }
        if (kind === 'error') {
            window.alert(message);
        }
    }

    // A message shown right before the screen reloads is gone with the
    // reload; it is stored here and shown once more on the next load.
    function afterReload(message, kind) {
        try {
            window.sessionStorage.setItem('pg-tr-toast', JSON.stringify({ m: message, k: kind }));
        } catch (e) { }
    }

    try {
        var pending = window.sessionStorage.getItem('pg-tr-toast');
        if (pending) {
            window.sessionStorage.removeItem('pg-tr-toast');
            pending = JSON.parse(pending);
            if (pending && pending.m) {
                toast(pending.m, pending.k);
            }
        }
    } catch (e) { }

    function showProgress(message, done, total) {
        if (!progress) {
            return;
        }
        progress.classList.add('show');
        if (progressText) {
            progressText.textContent = message;
        }
        if (progressBar) {
            var percent = (total > 0) ? Math.round((done / total) * 100) : 0;
            progressBar.style.width = percent + '%';
        }
    }

    function hideProgress() {
        if (progress) {
            progress.classList.remove('show');
        }
    }

    // A job's own error, or any text that failed, comes back as an error.
    function finish(done, failed, error) {
        showProgress(text('finished', done, failed), 1, 1);
        running = null;
        if (error) {
            afterReload(error, 'error');
        } else {
            afterReload(text('finished', done, failed), (failed > 0) ? 'error' : 'success');
        }
        window.setTimeout(function () {
            window.location.reload();
        }, 1200);
    }

    // ── the editor rows ──

    function rowState(row, state, engine, suspicious) {
        var badge = row.querySelector('.pg-tr-state-badge');
        var engineEl = row.querySelector('.pg-tr-engine');
        var review = row.querySelector('.pg-tr-review');
        var warn = row.querySelector('.pg-tr-suspicious');

        if (badge) {
            badge.className = 'badge pg-tr-state-badge ' + ((state === 'reviewed') ? 'theme-success' : ((state === 'machine') ? 'theme-warning' : 'theme-secondary'));
            badge.textContent = text(state);
        }
        if (engineEl) {
            engineEl.textContent = (engine === 'manual') ? text('manual') : (engine || '');
        }
        if (review) {
            review.classList.toggle('d-none', state === 'pending');
            review.setAttribute('data-state', state);
            var reviewLabel = (state === 'reviewed') ? text('mark_machine') : text('mark_reviewed');
            review.setAttribute('title', reviewLabel);
            review.setAttribute('aria-label', reviewLabel);
            var icon = review.querySelector('i');
            if (icon) {
                icon.className = 'bi ' + ((state === 'reviewed') ? 'bi-eye-slash' : 'bi-eye');
            }
        }
        if (warn) {
            warn.classList.toggle('d-none', !suspicious);
        }
        row.classList.remove('pg-tr-dirty');
    }

    app.addEventListener('input', function (event) {
        var target = event.target;
        if (target.classList && target.classList.contains('pg-tr-target')) {
            var row = target.closest('.pg-tr-row');
            if (row) {
                row.classList.add('pg-tr-dirty');
            }
        }
    });

    // Ctrl+Enter in a box saves it.
    app.addEventListener('keydown', function (event) {
        var target = event.target;
        if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey) && target.classList && target.classList.contains('pg-tr-target')) {
            event.preventDefault();
            var row = target.closest('.pg-tr-row');
            var button = row ? row.querySelector('.pg-tr-save') : null;
            if (button) {
                button.click();
            }
        }
    });

    app.addEventListener('click', function (event) {
        var save = event.target.closest('.pg-tr-save');
        var review = event.target.closest('.pg-tr-review');

        if (save) {
            var row = save.closest('.pg-tr-row');
            var box = row.querySelector('.pg-tr-target');
            save.disabled = true;
            call({
                action: 'save',
                string_id: parseInt(row.getAttribute('data-string-id'), 10),
                text: box.value
            }).then(function (data) {
                box.value = data.text;
                rowState(row, data.state, data.engine, data.suspicious === 1);
                toast(text('saved'), 'success');
            }).catch(function (error) {
                toast(error.message, 'error');
            }).then(function () {
                save.disabled = false;
            });
            return;
        }

        if (review) {
            var reviewRow = review.closest('.pg-tr-row');
            var next = (review.getAttribute('data-state') === 'reviewed') ? 'machine' : 'reviewed';
            review.disabled = true;
            call({
                action: 'status',
                string_id: parseInt(reviewRow.getAttribute('data-string-id'), 10),
                status: next
            }).then(function (data) {
                var engineEl = reviewRow.querySelector('.pg-tr-engine');
                rowState(reviewRow, data.state, engineEl ? engineEl.textContent : '', !reviewRow.querySelector('.pg-tr-suspicious').classList.contains('d-none'));
            }).catch(function (error) {
                toast(error.message, 'error');
            }).then(function () {
                review.disabled = false;
            });
        }
    });

    // ── the browser engine ──

    function translatorSupported() {
        return (typeof self.Translator !== 'undefined') && (typeof self.Translator.create === 'function');
    }

    // A browser that has the API but no translation service behind it never
    // settles the availability promise; the screen must not wait forever.
    function withTimeout(promise, ms, message) {
        return new Promise(function (resolve, reject) {
            var timer = window.setTimeout(function () { reject(new Error(message)); }, ms);
            Promise.resolve(promise).then(function (value) { window.clearTimeout(timer); resolve(value); },
                function (error) { window.clearTimeout(timer); reject(error); });
        });
    }

    // Created synchronously in the click handler itself: the API wants a user
    // gesture when the model still has to be downloaded, and the gesture is
    // gone once anything is awaited first - an availability() check that
    // takes a few seconds is enough to lose it. So create() is called at once;
    // availability() is asked only to explain a failure.
    function createTranslator() {
        var options = {
            sourceLanguage: config.source,
            targetLanguage: config.language,
            monitor: function (monitor) {
                monitor.addEventListener('downloadprogress', function (event) {
                    var percent = (event.total > 0) ? Math.round((event.loaded / event.total) * 100) : Math.round((event.loaded || 0) * 100);
                    showProgress(text('downloading', percent), percent, 100);
                });
            }
        };

        if (!translatorSupported()) {
            return Promise.reject(new Error(text('translator_missing')));
        }

        var created;

        try {
            created = self.Translator.create(options);
        } catch (error) {
            created = Promise.reject(error);
        }

        return Promise.resolve(created).catch(function (error) {
            if (typeof self.Translator.availability !== 'function') {
                throw error;
            }

            return withTimeout(self.Translator.availability({ sourceLanguage: config.source, targetLanguage: config.language }), 10000, error.message)
                .then(function (availability) {
                    if (availability === 'unavailable' || availability === 'no') {
                        throw new Error(text('translator_unavailable', config.source, config.language));
                    }

                    throw error;
                });
        });
    }

    // The Translator API: an item's runs, one after another.
    function translatorItem(translator) {
        return function (item) {
            var runs = [];
            var runChain = Promise.resolve();
            item.runs.forEach(function (run) {
                runChain = runChain.then(function () {
                    return Promise.resolve(translator.translate(run)).then(function (out) {
                        runs.push(String(out || ''));
                    });
                });
            });
            return runChain.then(function () {
                return { string_id: item.string_id, runs: runs };
            });
        };
    }

    // ── the browser AI engine: Gemini Nano through the Prompt API ──

    var MODEL_ANSWER = { type: 'object', properties: { translation: { type: 'string' } }, required: ['translation'] };

    function modelSupported() {
        return (typeof self.LanguageModel !== 'undefined') && (typeof self.LanguageModel.create === 'function');
    }

    function modelLanguages(input) {
        return {
            expectedInputs: [{ type: 'text', languages: input }],
            expectedOutputs: [{ type: 'text', languages: [config.language] }]
        };
    }

    function release(model) {
        try {
            if (model && (typeof model.destroy === 'function')) {
                model.destroy();
            }
        } catch (e) { /* already gone */ }
    }

    // The languages the model is told it will read: the source and the
    // target; without a source it does not officially read (Turkish, for
    // one), which it reads all the same; failing that, English. Worked out
    // when the screen opens, because create() has to be called in the click
    // itself.
    var modelInput = [config.source, config.language];
    var modelPlanned = Promise.resolve(false);

    if ((config.engine === 'chrome_ai') && modelSupported() && (typeof self.LanguageModel.availability === 'function')) {
        modelPlanned = [[config.source, config.language], [config.language], ['en']].reduce(function (chain, input) {
            return chain.then(function (found) {
                if (found) {
                    return true;
                }
                return withTimeout(self.LanguageModel.availability(modelLanguages(input)), 10000, '').then(function (state) {
                    if (state && (state !== 'unavailable') && (state !== 'no')) {
                        modelInput = input;
                        return true;
                    }
                    return false;
                }, function () {
                    return false;
                });
            });
        }, Promise.resolve(false));
    }

    // Whether the model writes the target language: false when no
    // availability() answer above allowed it. The model writes a handful of
    // languages, and Turkish is not one of them; such a language is
    // translated with the Translator API instead (the click handler), and the
    // note says so as soon as it is known.
    var modelWrites = null;

    modelPlanned.then(function (found) {
        modelWrites = found;

        if (!found && (config.engine === 'chrome_ai') && modelSupported() && translatorSupported()) {
            var note = document.querySelector('.pg-tr-chrome-note');

            if (note) {
                var line = document.createElement('div');
                line.className = 'small mt-3 fw-semibold';
                line.textContent = text('model_fallback', config.language);
                note.appendChild(line);
            }
        }
    });

    // Created in the click handler itself, like the translator: a model that
    // still has to be downloaded wants the gesture.
    function createModel() {
        if (!modelSupported()) {
            return Promise.reject(new Error(text('model_missing')));
        }

        var options = modelLanguages(modelInput);
        options.initialPrompts = [{ role: 'system', content: config.ai_instructions || '' }];
        options.monitor = function (monitor) {
            monitor.addEventListener('downloadprogress', function (event) {
                var percent = (event.total > 0) ? Math.round((event.loaded / event.total) * 100) : Math.round((event.loaded || 0) * 100);
                showProgress(text('downloading', percent), percent, 100);
            });
        };

        var created;

        try {
            created = self.LanguageModel.create(options);
        } catch (error) {
            created = Promise.reject(error);
        }

        return Promise.resolve(created).catch(function (error) {
            return modelPlanned.then(function (found) {
                if (!found) {
                    var unwritable = new Error(text('model_unavailable', config.language));
                    unwritable.unwritable = true;
                    throw unwritable;
                }
                throw error;
            });
        });
    }

    // What the model is sent for one text: the item as the AI engines get
    // it, without the empty parts.
    function modelRequest(item) {
        var request = { text: item.text, format: item.format };
        var where = {};

        Object.keys(item.where || {}).forEach(function (key) {
            if (item.where[key]) {
                where[key] = item.where[key];
            }
        });

        if (Object.keys(where).length) {
            request.where = where;
        }
        if (item.previous) {
            request.previous = item.previous;
        }
        if (item.glossary && item.glossary.length) {
            request.glossary = item.glossary;
        }

        return JSON.stringify(request);
    }

    // The translation out of the model's answer: the "translation" field, or
    // the answer itself when the browser gave no structured output.
    function modelAnswer(raw) {
        var answer = String(raw || '').trim();

        try {
            var parsed = JSON.parse(answer.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, ''));
            if (parsed && (typeof parsed.translation === 'string')) {
                return parsed.translation;
            }
        } catch (e) { /* plain text */ }

        return answer;
    }

    function modelItem(session) {
        var ask = function (model, item) {
            return Promise.resolve(model.prompt(modelRequest(item), { responseConstraint: MODEL_ANSWER })).then(null, function (error) {
                // A browser without structured output.
                if (error && ((error.name === 'NotSupportedError') || (error.name === 'TypeError'))) {
                    return model.prompt(modelRequest(item));
                }
                throw error;
            });
        };

        return function (item) {
            // Each text on a copy of the session: the instructions stay,
            // the earlier texts do not fill the model's context.
            var asked = (typeof session.clone === 'function')
                ? Promise.resolve(session.clone()).then(function (copy) {
                    return ask(copy, item).then(function (raw) {
                        release(copy);
                        return raw;
                    }, function (error) {
                        release(copy);
                        throw error;
                    });
                })
                : ask(session, item);

            return withTimeout(asked, 120000, text('network_error')).then(function (raw) {
                return { string_id: item.string_id, text: modelAnswer(raw) };
            });
        };
    }

    // A browser engine's job: the packages are fetched, every item is
    // translated by translateItem() (translatorItem() or modelItem()) and
    // the answers are sent back, until the job is done.
    function runBrowserJob(translateItem, job) {
        running = { id: job.id, total: job.total, done: job.done || 0, failed: job.failed || 0, stop: false };
        showProgress(text('progress', running.done, running.total), running.done, running.total);

        function batch() {
            if (running.stop) {
                return call({ action: 'cancel', job_id: running.id }).then(function () {
                    hideProgress();
                    running = null;
                    window.location.reload();
                });
            }

            return call({ action: 'package', job_id: running.id }).then(function (data) {
                if (!data.items || !data.items.length) {
                    return call({ action: 'results', job_id: running.id, results: [] }).then(function (summary) {
                        finish(running.done, running.failed);
                    });
                }

                var results = [];
                var chain = Promise.resolve();

                data.items.forEach(function (item) {
                    chain = chain.then(function () {
                        if (running.stop) {
                            return;
                        }
                        return translateItem(item).then(function (result) {
                            results.push(result);
                        }).catch(function (error) {
                            results.push({ string_id: item.string_id, error: String(error && error.message ? error.message : error) });
                        });
                    });
                });

                return chain.then(function () {
                    return call({ action: 'results', job_id: running.id, results: results });
                }).then(function (summary) {
                    running.done += summary.done || 0;
                    running.failed += summary.failed || 0;
                    showProgress(text('progress', running.done, running.total), running.done, running.total);
                    if (summary.finished) {
                        finish(running.done, running.failed);
                        return;
                    }
                    return batch();
                });
            });
        }

        return batch();
    }

    // ── the server engines: poll until the job is finished ──

    function runServerJob(job) {
        running = { id: job.id, total: job.total, done: job.done_count !== undefined ? parseInt(job.done_count, 10) : (job.done || 0), failed: job.failed_count !== undefined ? parseInt(job.failed_count, 10) : (job.failed || 0), stop: false };
        showProgress(text('progress', running.done, running.total), running.done, running.total);

        // Calls that answered with an error and no progress, or did not
        // answer at all, one after another. A paused engine answers that way
        // for a minute, and a run the server or a proxy cut off is followed
        // by one that gets through; the loop waits them out and gives up only
        // when it keeps happening.
        var idle = 0;

        function waitOut(message, giveUp) {
            idle++;

            if (idle >= 8) {
                toast(giveUp, 'error');
                hideProgress();
                running = null;
                return;
            }

            showProgress(message, running.done, running.total);

            return new Promise(function (resolve) { window.setTimeout(resolve, 20000); }).then(step);
        }

        function step() {
            if (running.stop) {
                return call({ action: 'cancel', job_id: running.id }).then(function () {
                    running = null;
                    window.location.reload();
                });
            }

            return call({ action: 'run', job_id: running.id }).then(function (data) {
                var current = data.job || {};
                running.done = parseInt(current.done_count, 10) || 0;
                running.failed = parseInt(current.failed_count, 10) || 0;
                showProgress(text('progress', running.done, running.total), running.done, running.total);

                if (data.finished || ['done', 'failed', 'cancelled'].indexOf(current.status) !== -1) {
                    finish(running.done, running.failed, current.error);
                    return;
                }

                if (data.error && !data.done) {
                    // The engine answered with an error and nothing was
                    // translated. What keeps failing is left to the
                    // scheduled job.
                    return waitOut(data.error, data.error);
                }

                idle = 0;

                return step();
            }, function (error) {
                if (!error.transient) {
                    throw error;
                }

                return waitOut(text('retrying'), error.message);
            });
        }

        return step();
    }

    // ── the queue engine (Claude): the routine works outside; the screen
    //    follows the job until it closes ──

    function watchQueueJob(job) {
        running = { id: job.id, total: job.total, done: job.done_count !== undefined ? parseInt(job.done_count, 10) : (job.done || 0), failed: job.failed_count !== undefined ? parseInt(job.failed_count, 10) : (job.failed || 0), stop: false };
        showProgress(text('queue_waiting'), running.done, running.total);

        function step() {
            if (running.stop) {
                return call({ action: 'cancel', job_id: running.id }).then(function () {
                    running = null;
                    window.location.reload();
                });
            }

            return call({ action: 'job', job_id: running.id }).then(function (data) {
                var current = data.job || {};
                running.done = parseInt(current.done_count, 10) || 0;
                running.failed = parseInt(current.failed_count, 10) || 0;

                if (['done', 'failed', 'cancelled'].indexOf(current.status) !== -1) {
                    finish(running.done, running.failed, current.error);
                    return;
                }

                showProgress((current.status === 'running') ? text('progress', running.done, running.total) : text('queue_waiting'), running.done, running.total);

                return new Promise(function (resolve) { window.setTimeout(resolve, 5000); }).then(step);
            });
        }

        return step();
    }

    // ── "Update translations" ──

    if (updateButton) {
        updateButton.addEventListener('click', function () {
            if (running) {
                return;
            }

            updateButton.disabled = true;
            showProgress(text('scanning'), 0, 1);

            // A language the model does not write goes to the Translator API,
            // in a job of that engine (the server then packages its texts in
            // runs). Decided before anything is awaited: the click is the
            // gesture a download wants. Before the check has answered, a
            // create() refused for the language tells the same.
            var jobEngine = ((config.engine === 'chrome_ai') && (modelWrites === false) && modelSupported() && translatorSupported()) ? 'chrome' : config.engine;

            var browserPromise = (jobEngine === 'chrome') ? createTranslator().then(translatorItem)
                : ((jobEngine === 'chrome_ai') ? createModel().then(modelItem, function (error) {
                    if (!error.unwritable || !translatorSupported()) {
                        throw error;
                    }
                    jobEngine = 'chrome';
                    return createTranslator().then(translatorItem);
                }) : Promise.resolve(null));

            browserPromise.then(function (translateItem) {
                // A model's download progress may have taken the line.
                if (translateItem) {
                    showProgress(text('scanning'), 0, 1);
                }

                if (jobEngine !== config.engine) {
                    toast(text('model_fallback', config.language), 'info');
                }

                // A browser job an earlier visit left open is picked up where
                // it stopped rather than opened again.
                if (translateItem && config.open_job && (config.open_job.engine === jobEngine) && ['queued', 'sent', 'running'].indexOf(config.open_job.status) !== -1) {
                    return runBrowserJob(translateItem, { id: config.open_job.id, total: config.open_job.total, done: config.open_job.done, failed: config.open_job.failed });
                }

                // The site is scanned in turns: a site of many pages takes
                // longer than one request may. Each answer says where the
                // next turn starts; the last one opens the job.
                var scan = function (from) {
                    return call({ action: 'update', scope: config.scope, engine: (jobEngine !== config.engine) ? jobEngine : '', from: from }).then(function (data) {
                        if (data.extracting) {
                            showProgress(text('scanning_progress', data.next, data.total), data.next, data.total);
                            return scan(data.next);
                        }
                        return data;
                    });
                };

                return scan(0).then(function (data) {
                    if (!data.pending) {
                        hideProgress();
                        toast(text('nothing_pending'), 'info');
                        afterReload(text('nothing_pending'), 'info');
                        window.setTimeout(function () { window.location.reload(); }, 800);
                        return;
                    }

                    if (!data.job_id) {
                        hideProgress();
                        toast(text('pending_by_hand', data.pending), 'info');
                        afterReload(text('pending_by_hand', data.pending), 'info');
                        window.setTimeout(function () { window.location.reload(); }, 800);
                        return;
                    }

                    toast(text('sent', data.pending), 'info');

                    if (translateItem) {
                        return runBrowserJob(translateItem, { id: data.job_id, total: data.pending, done: 0, failed: 0 });
                    }

                    if (config.engine === 'claude') {
                        if (data.error) {
                            toast(data.error, 'error');
                        } else if (data.queue === 'held') {
                            toast(text('queue_held', (data.job && data.job.error) || ''), 'warning');
                        }

                        return watchQueueJob(data.job || { id: data.job_id, total: data.pending, done_count: 0, failed_count: 0 });
                    }

                    // A server engine: the update only opened the job; every
                    // turn of translating is a run call of its own.
                    return runServerJob(data.job);
                });
            }).catch(function (error) {
                hideProgress();
                running = null;
                toast(error.message, 'error');
            }).then(function () {
                updateButton.disabled = false;
            });
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            if (running && window.confirm(text('confirm_cancel'))) {
                running.stop = true;
            }
        });
    }

    // A job left open by an earlier visit: a server job is resumed, a queue
    // job is followed, a browser job needs the button (the API wants the
    // gesture).
    if (config.open_job && (['chrome', 'chrome_ai'].indexOf(config.open_job.engine) === -1) && ['queued', 'sent', 'running'].indexOf(config.open_job.status) !== -1) {
        var resume = (config.open_job.engine === 'claude') ? watchQueueJob : runServerJob;

        if ((config.open_job.engine === 'claude') || (config.open_job.status !== 'sent')) {
            resume({ id: config.open_job.id, total: config.open_job.total, done_count: config.open_job.done, failed_count: config.open_job.failed }).catch(function (error) {
                hideProgress();
                running = null;
                toast(error.message, 'error');
            });
        }
    }

    // ── the engine select in the toolbar: saved at once, then the screen
    //    reloads with that engine's note and button ──

    var engineSelect = document.getElementById('pg_tr_engine');

    if (engineSelect) {
        engineSelect.addEventListener('change', function () {
            var previous = config.engine;
            engineSelect.disabled = true;

            call({ action: 'engine', engine: engineSelect.value }).then(function () {
                window.location.reload();
            }).catch(function (error) {
                engineSelect.value = previous;
                engineSelect.disabled = false;
                toast(error.message, 'error');
            });
        });
    }

    // ── "approve all": every machine translation of the scope reviewed at
    //    once, for a translation that is trusted as the engine gave it. The
    //    dialog names the count; the ones flagged suspicious go along only
    //    when ticked, and are ticked already when nothing else is left ──

    var reviewAllButton = document.getElementById('pg_tr_review_all');
    var reviewAllModal = document.getElementById('pg_tr_review_all_modal');

    if (reviewAllButton && reviewAllModal && window.pgUI) {
        var reviewAllGo = reviewAllModal.querySelector('.pg-tr-review-all-go');
        var reviewAllFlagged = reviewAllModal.querySelector('.pg-tr-review-all-flagged');
        var reviewAllCount = reviewAllModal.querySelector('.pg-tr-review-all-count');
        var reviewAllPlain = parseInt(reviewAllGo.getAttribute('data-plain'), 10) || 0;
        var reviewAllFlaggedCount = parseInt(reviewAllGo.getAttribute('data-flagged'), 10) || 0;

        function syncReviewAll() {
            var count = reviewAllPlain + (reviewAllFlagged.checked ? reviewAllFlaggedCount : 0);
            reviewAllCount.textContent = text('review_all_count', count);
            reviewAllGo.disabled = (count === 0);
        }

        reviewAllFlagged.addEventListener('change', syncReviewAll);

        pgUI.dialog.on(reviewAllModal, 'show', function () {
            reviewAllFlagged.checked = (reviewAllPlain === 0) && (reviewAllFlaggedCount > 0);
            syncReviewAll();
        });

        reviewAllButton.addEventListener('click', function () {
            if (!running) {
                pgUI.dialog.show(reviewAllModal);
            }
        });

        reviewAllGo.addEventListener('click', function () {
            if (running) {
                return;
            }

            reviewAllGo.disabled = true;

            call({ action: 'review_all', scope: config.scope, suspicious: reviewAllFlagged.checked ? 1 : 0 }).then(function (data) {
                toast(data.message, 'success');
                afterReload(data.message, 'success');
                window.setTimeout(function () { window.location.reload(); }, 800);
            }).catch(function (error) {
                reviewAllGo.disabled = false;
                toast(error.message, 'error');
            });
        });
    }

    // ── "clear translations": the translations of the scope deleted, so the
    //    next update sends the texts again; the dialog names the count ──

    var clearModal = document.getElementById('pg_tr_clear_modal');

    if (clearModal) {
        var clearButton = clearModal.querySelector('.pg-tr-clear');
        var clearReviewed = clearModal.querySelector('.pg-tr-clear-reviewed');
        var clearCount = clearModal.querySelector('.pg-tr-clear-count');

        function syncClear() {
            var count = parseInt(clearButton.getAttribute(clearReviewed.checked ? 'data-all' : 'data-machine'), 10) || 0;
            clearCount.textContent = text('clear_count', count);
            clearButton.disabled = (count === 0);
        }

        clearReviewed.addEventListener('change', syncClear);

        pgUI.dialog.on(clearModal, 'show', function () {
            clearReviewed.checked = false;
            syncClear();
        });

        clearButton.addEventListener('click', function () {
            if (running) {
                return;
            }

            clearButton.disabled = true;

            call({ action: 'clear', scope: config.scope, reviewed: clearReviewed.checked ? 1 : 0 }).then(function (data) {
                toast(data.message, 'success');
                afterReload(data.message, 'success');
                window.setTimeout(function () { window.location.reload(); }, 800);
            }).catch(function (error) {
                clearButton.disabled = false;
                toast(error.message, 'error');
            });
        });
    }

    // ── "keep as in the source and approve": the texts ticked in the list
    //    get their source text as the translation, approved by hand; the
    //    dialog names the count ──

    var acceptButton = document.getElementById('pg_tr_accept_source');
    var acceptModal = document.getElementById('pg_tr_accept_modal');
    var pickAll = document.getElementById('pg_tr_pick_all');

    if (acceptButton && acceptModal && window.pgUI) {
        var acceptGo = acceptModal.querySelector('.pg-tr-accept-go');
        var acceptCount = acceptModal.querySelector('.pg-tr-accept-count');
        var pickCount = acceptButton.querySelector('.pg-tr-pick-count');

        function picks() {
            return Array.prototype.slice.call(app.querySelectorAll('.pg-tr-pick'));
        }

        function picked() {
            return picks().filter(function (box) { return box.checked; });
        }

        function syncPicks() {
            var all = picks();
            var count = picked().length;

            pickCount.textContent = count;
            acceptButton.disabled = (count === 0);

            if (pickAll) {
                pickAll.checked = (all.length > 0) && (count === all.length);
                pickAll.indeterminate = (count > 0) && (count < all.length);
            }
        }

        app.addEventListener('change', function (event) {
            var target = event.target;

            if (target === pickAll) {
                picks().forEach(function (box) { box.checked = pickAll.checked; });
                syncPicks();
            } else if (target.classList && target.classList.contains('pg-tr-pick')) {
                syncPicks();
            }
        });

        pgUI.dialog.on(acceptModal, 'show', function () {
            var count = picked().length;
            acceptCount.textContent = text('accept_count', count);
            acceptGo.disabled = (count === 0);
        });

        acceptButton.addEventListener('click', function () {
            if (!running && picked().length) {
                pgUI.dialog.show(acceptModal);
            }
        });

        acceptGo.addEventListener('click', function () {
            var ids = picked().map(function (box) { return parseInt(box.value, 10); });

            if (running || !ids.length) {
                return;
            }

            acceptGo.disabled = true;

            call({ action: 'accept_source', string_ids: ids }).then(function (data) {
                toast(data.message, 'success');
                afterReload(data.message, 'success');
                window.setTimeout(function () { window.location.reload(); }, 800);
            }).catch(function (error) {
                acceptGo.disabled = false;
                toast(error.message, 'error');
            });
        });

        syncPicks();
    }

    // ── the glossary modal: one form for a new term and for an existing one ──

    var glossaryModal = document.getElementById('pg_tr_glossary_modal');

    if (glossaryModal) {
        var fields = {
            id: glossaryModal.querySelector('input[name="id"]'),
            term: glossaryModal.querySelector('input[name="term"]'),
            translation: glossaryModal.querySelector('input[name="translation"]'),
            keep: glossaryModal.querySelector('input[name="keep"]'),
            all: glossaryModal.querySelector('input[name="all_languages"]'),
            caseSensitive: glossaryModal.querySelector('input[name="case_sensitive"]'),
            note: glossaryModal.querySelector('input[name="note"]'),
            title: glossaryModal.querySelector('.dialog-title')
        };

        // A kept term has no translation and may apply to every language.
        function syncKeep() {
            var keep = fields.keep.checked;
            glossaryModal.querySelector('.pg-tr-term-translation').classList.toggle('d-none', keep);
            glossaryModal.querySelector('.pg-tr-term-all').classList.toggle('d-none', !keep);
            fields.translation.required = !keep;
        }

        fields.keep.addEventListener('change', syncKeep);

        pgUI.dialog.on(glossaryModal, 'show', function (event) {
            var row = event.relatedTarget ? event.relatedTarget.closest('.pg-tr-term') : null;

            fields.id.value = row ? row.getAttribute('data-id') : '0';
            fields.term.value = row ? row.getAttribute('data-term') : '';
            fields.translation.value = row ? row.getAttribute('data-translation') : '';
            fields.keep.checked = !!(row && row.getAttribute('data-keep') === '1');
            fields.all.checked = !!(row && row.getAttribute('data-all') === '1');
            fields.caseSensitive.checked = !!(row && row.getAttribute('data-case') === '1');
            fields.note.value = row ? row.getAttribute('data-note') : '';
            fields.title.textContent = row ? text('glossary_edit') : text('glossary_add');
            syncKeep();
        });

        pgUI.dialog.on(glossaryModal, 'shown', function () {
            fields.term.focus();
        });
    }
})();
