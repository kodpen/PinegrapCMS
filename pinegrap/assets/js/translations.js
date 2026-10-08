/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen: saving and reviewing a text, "update
 * translations", and the browser engine - the Chrome Translator API, which
 * translates on the operator's own computer. The server engines run in
 * translations_action.php; this script only starts them and shows their
 * progress.
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

    function finish(done, failed) {
        showProgress(text('finished', done, failed), 1, 1);
        running = null;
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
            badge.className = 'badge pg-tr-state-badge ' + ((state === 'reviewed') ? 'text-bg-success' : ((state === 'machine') ? 'text-bg-warning' : 'text-bg-secondary'));
            badge.textContent = text(state);
        }
        if (engineEl) {
            engineEl.textContent = (engine === 'manual') ? text('manual') : (engine || '');
        }
        if (review) {
            review.classList.toggle('d-none', state === 'pending');
            review.setAttribute('data-state', state);
            review.setAttribute('title', (state === 'reviewed') ? text('mark_machine') : text('mark_reviewed'));
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

    function runBrowserJob(translator, job) {
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
                            results.push({ string_id: item.string_id, runs: runs });
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
                    if (current.error) {
                        toast(current.error, 'error');
                    }
                    finish(running.done, running.failed);
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
                    if (current.error) {
                        toast(current.error, 'error');
                    }
                    finish(running.done, running.failed);
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

            var translatorPromise = (config.engine === 'chrome') ? createTranslator() : Promise.resolve(null);

            translatorPromise.then(function (translator) {
                // A browser job an earlier visit left open is picked up where
                // it stopped rather than opened again.
                if (translator && config.open_job && config.open_job.engine === 'chrome' && ['queued', 'sent', 'running'].indexOf(config.open_job.status) !== -1) {
                    return runBrowserJob(translator, { id: config.open_job.id, total: config.open_job.total, done: config.open_job.done, failed: config.open_job.failed });
                }

                return call({ action: 'update', scope: config.scope }).then(function (data) {
                    if (!data.pending) {
                        hideProgress();
                        toast(text('nothing_pending'), 'info');
                        window.setTimeout(function () { window.location.reload(); }, 800);
                        return;
                    }

                    if (!data.job_id) {
                        hideProgress();
                        toast(text('pending_by_hand', data.pending), 'info');
                        window.setTimeout(function () { window.location.reload(); }, 800);
                        return;
                    }

                    toast(text('sent', data.pending), 'info');

                    if (config.engine === 'chrome') {
                        return runBrowserJob(translator, { id: data.job_id, total: data.pending, done: 0, failed: 0 });
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
    if (config.open_job && config.open_job.engine !== 'chrome' && ['queued', 'sent', 'running'].indexOf(config.open_job.status) !== -1) {
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
    //    once, for a translation that is trusted as the engine gave it ──

    var reviewAllButton = document.getElementById('pg_tr_review_all');

    if (reviewAllButton) {
        reviewAllButton.addEventListener('click', function () {
            if (running || !window.confirm(text('confirm_review_all', reviewAllButton.getAttribute('data-count')))) {
                return;
            }

            reviewAllButton.disabled = true;

            call({ action: 'review_all', scope: config.scope }).then(function (data) {
                toast(data.message, 'success');
                window.setTimeout(function () { window.location.reload(); }, 800);
            }).catch(function (error) {
                reviewAllButton.disabled = false;
                toast(error.message, 'error');
            });
        });
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
            title: glossaryModal.querySelector('.modal-title')
        };

        // A kept term has no translation and may apply to every language.
        function syncKeep() {
            var keep = fields.keep.checked;
            glossaryModal.querySelector('.pg-tr-term-translation').classList.toggle('d-none', keep);
            glossaryModal.querySelector('.pg-tr-term-all').classList.toggle('d-none', !keep);
            fields.translation.required = !keep;
        }

        fields.keep.addEventListener('change', syncKeep);

        glossaryModal.addEventListener('show.bs.modal', function (event) {
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

        glossaryModal.addEventListener('shown.bs.modal', function () {
            fields.term.focus();
        });
    }
})();
