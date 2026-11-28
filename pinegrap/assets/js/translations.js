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
    var progressTitle = progress ? progress.querySelector('.pg-tr-progress-title') : null;
    var progressOpen = progress ? progress.querySelector('.pg-tr-progress-open') : null;
    var automationLine = document.getElementById('pg_tr_automation');
    var jobTitle = '';   // "Translating "<scope>" with <engine>…" of the update followed
    var jobScope = '';
    var jobEngine = '';
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

    // The progress card takes the automation line's place while an update
    // runs: what it covers and its engine, how far it got, "Stop", and
    // "Open in Activity" once there is an update to open.
    function showProgress(message, done, total) {
        if (!progress) {
            return;
        }
        progress.classList.add('show');
        if (automationLine) {
            automationLine.hidden = true;
        }
        if (progressTitle) {
            progressTitle.textContent = running ? jobTitle : '';
        }
        if (progressOpen) {
            progressOpen.hidden = !running;
        }
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
        if (automationLine) {
            automationLine.hidden = false;
        }
    }

    function titleJob(scopeLabel, engine) {
        jobScope = scopeLabel || config.scope_label || '';
        jobEngine = engine || config.engine;
        jobTitle = text('translating', scopeLabel || config.scope_label || '', (config.engine_labels || {})[engine] || engine || '');
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

    // A number drawn on the server, moved by a row's change of state. The
    // server writes thousands with a comma (number_format()); it is read
    // without and written back with it.
    function bump(el, delta) {
        if (el && delta) {
            var value = Math.max(0, (parseInt(el.textContent.replace(/[^0-9]/g, ''), 10) || 0) + delta);
            el.textContent = String(value).replace(/\B(?=(\d{3})+$)/g, ',');
        }
    }

    // The counts drawn on the server follow a row's change of state (a
    // translation written or emptied, one turned reviewed or machine, one
    // flagged suspicious or no longer): the filter chips, the translated and
    // reviewed totals of the page list, "Approve all" and "Clear translations".
    function countState(before, after, wasFlagged, flagged) {
        if (before !== after) {
            ['pending', 'machine', 'reviewed'].forEach(function (key) {
                bump(document.querySelector('.pg-tr-chip-count[data-state="' + key + '"]'), ((after === key) ? 1 : 0) - ((before === key) ? 1 : 0));
            });
            bump(document.querySelector('.pg-tr-pending-count'), ((after === 'pending') ? 1 : 0) - ((before === 'pending') ? 1 : 0));
            bump(document.querySelector('.pg-tr-all-done'), ((after !== 'pending') ? 1 : 0) - ((before !== 'pending') ? 1 : 0));
            bump(document.querySelector('.pg-tr-reviewed-total'), ((after === 'reviewed') ? 1 : 0) - ((before === 'reviewed') ? 1 : 0));
        }
        bump(document.querySelector('.pg-tr-chip-count[data-state="suspicious"]'), (flagged ? 1 : 0) - (wasFlagged ? 1 : 0));
        countReviewAll(((after === 'machine') && !flagged ? 1 : 0) - ((before === 'machine') && !wasFlagged ? 1 : 0),
            ((after === 'machine') && flagged ? 1 : 0) - ((before === 'machine') && wasFlagged ? 1 : 0));
        countClear(before, after);
    }

    // "Approve all": the machine translations it marks, and the flagged ones
    // it marks when ticked. The toolbar button shows while either is left.
    function countReviewAll(plainDelta, flaggedDelta) {
        var go = document.querySelector('#pg_tr_review_all_modal .pg-tr-review-all-go');
        if (!go || (!plainDelta && !flaggedDelta)) {
            return;
        }
        var plain = Math.max(0, (parseInt(go.getAttribute('data-plain'), 10) || 0) + plainDelta);
        var flaggedCount = Math.max(0, (parseInt(go.getAttribute('data-flagged'), 10) || 0) + flaggedDelta);
        go.setAttribute('data-plain', plain);
        go.setAttribute('data-flagged', flaggedCount);
        var flaggedBox = document.querySelector('#pg_tr_review_all_modal .pg-tr-review-all-flagged');
        if (flaggedBox) {
            flaggedBox.disabled = (flaggedCount === 0);
        }
        var flaggedLabel = document.querySelector('#pg_tr_review_all_modal .pg-tr-review-all-flagged-count');
        if (flaggedLabel) {
            flaggedLabel.textContent = flaggedCount;
        }
        var button = document.getElementById('pg_tr_review_all');
        if (button) {
            button.classList.toggle('d-none', (plain + flaggedCount) === 0);
            var shown = button.querySelector('.pg-tr-review-all-shown');
            if (shown) {
                shown.textContent = (plain > 0) ? plain : flaggedCount;
            }
        }
    }

    function countClear(before, after) {
        var button = document.querySelector('#pg_tr_clear_modal .pg-tr-clear');
        if (!button || (before === after)) {
            return;
        }
        var all = (parseInt(button.getAttribute('data-all'), 10) || 0) + ((after !== 'pending') ? 1 : 0) - ((before !== 'pending') ? 1 : 0);
        var machine = (parseInt(button.getAttribute('data-machine'), 10) || 0) + ((after === 'machine') ? 1 : 0) - ((before === 'machine') ? 1 : 0);
        all = Math.max(0, all);
        machine = Math.max(0, Math.min(all, machine));
        button.setAttribute('data-all', all);
        button.setAttribute('data-machine', machine);
        var reviewedCount = document.querySelector('#pg_tr_clear_modal .pg-tr-clear-reviewed-count');
        if (reviewedCount) {
            reviewedCount.textContent = all - machine;
        }
    }

    function rowState(row, state, engine, suspicious) {
        var badge = row.querySelector('.pg-tr-state-badge');
        var before = row.getAttribute('data-state');
        var engineEl = row.querySelector('.pg-tr-engine');
        var review = row.querySelector('.pg-tr-review');
        var warn = row.querySelector('.pg-tr-suspicious');
        row.setAttribute('data-state', state);
        countState(before, state, !!(warn && !warn.classList.contains('d-none')), !!suspicious);

        if (badge) {
            badge.className = 'pg-pill pg-tr-state-badge ' + ((state === 'reviewed') ? 'pg-tone-teal' : ((state === 'machine') ? 'pg-tone-amber' : 'pg-tone-gray'));
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
            if (suspicious) {
                warn.setAttribute('title', suspiciousReason(row));
            } else {
                warn.removeAttribute('title');
            }
        }
        row.classList.remove('pg-tr-dirty');
        refuse(row, '');
        detailsChanged(row);
    }

    // Why a translation is flagged, as pg_tr_screen_suspicious_reason()
    // says it: the source given back, or a length out of proportion.
    function suspiciousReason(row) {
        var source = row.querySelector('.pg-tr-source');
        var box = row.querySelector('.pg-tr-target');
        var same = source && box && (source.textContent.trim().toLowerCase() === box.value.trim().toLowerCase());
        return same ? text('suspicious_same') : text('suspicious_length');
    }

    // A refused save: the server's sentence under the box, which takes the
    // field's rose ring; the typing stays. An empty message takes it away.
    function refuse(row, message) {
        var why = row.querySelector('.pg-tr-why');
        var box = row.querySelector('.pg-tr-target');
        if (!why || !box) {
            return;
        }
        why.querySelector('.pg-tr-why-text').textContent = message;
        why.hidden = !message;
        if (message) {
            box.setAttribute('aria-invalid', 'true');
        } else {
            box.removeAttribute('aria-invalid');
        }
    }

    // The translation box of the row after (1) or before (-1) this one.
    function nearBox(row, step) {
        var rows = Array.prototype.slice.call(app.querySelectorAll('.pg-tr-row'));
        var next = rows[rows.indexOf(row) + step];
        return next ? next.querySelector('.pg-tr-target') : null;
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

    // Typed and not saved: a filter, a page link, a language switch or the
    // screen's own reload after a job would drop it without a word. The
    // browser shows its own question.
    window.addEventListener('beforeunload', function (event) {
        if (!app.querySelector('.pg-tr-row.pg-tr-dirty')) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    // In a box: Ctrl+Enter saves it and moves to the next row's box once
    // stored; Alt+Down and Alt+Up go to the next and the previous box.
    app.addEventListener('keydown', function (event) {
        var target = event.target;
        if (!target.classList || !target.classList.contains('pg-tr-target')) {
            return;
        }
        var row = target.closest('.pg-tr-row');
        if (!row) {
            return;
        }
        if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            var button = row.querySelector('.pg-tr-save');
            if (button && !button.disabled) {
                saveRow(row, button, 'reviewed', true);
            }
        } else if (event.altKey && ((event.key === 'ArrowDown') || (event.key === 'ArrowUp'))) {
            var box = nearBox(row, (event.key === 'ArrowDown') ? 1 : -1);
            if (box) {
                event.preventDefault();
                box.focus();
            }
        }
    });

    // Stores a row's box as typed; a status of 'machine' keeps it a draft,
    // anything else stores it reviewed.
    // With next, the cursor moves to the next row's box once it is stored.
    // A refusal is shown under the box; a request that never got an answer
    // is told in a toast.
    function saveRow(row, button, status, next) {
        var box = row.querySelector('.pg-tr-target');
        button.disabled = true;
        call({
            action: 'save',
            string_id: parseInt(row.getAttribute('data-string-id'), 10),
            text: box.value,
            status: status
        }).then(function (data) {
            box.value = data.text;
            rowState(row, data.state, data.engine, data.suspicious === 1);
            toast(text('saved'), 'success');
            var following = next ? nearBox(row, 1) : null;
            if (following) {
                following.focus();
            }
        }).catch(function (error) {
            if (error.transient) {
                toast(error.message, 'error');
            } else {
                refuse(row, error.message);
                box.focus();
            }
        }).then(function () {
            button.disabled = false;
        });
    }

    // ── a long source: four lines, then "Show all" ──

    Array.prototype.forEach.call(app.querySelectorAll('.pg-tr-clamp'), function (source) {
        var more = source.parentNode.querySelector('.pg-tr-show-all');
        if (more && (source.scrollHeight > source.clientHeight + 1)) {
            more.classList.remove('d-none');
        }
    });

    // ── "Details": where the text is used, the wording it replaced, why
    //    it is flagged, and the row's own actions; read when first opened ──

    function detailsChanged(row) {
        var panel = row.querySelector('.pg-tr-details');
        if (!panel || !panel.hasAttribute('data-loaded')) {
            return;
        }
        panel.removeAttribute('data-loaded');
        if (!panel.hidden) {
            loadDetails(row, panel);
        }
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (content !== undefined) {
            node.textContent = content;
        }
        return node;
    }

    function icon(name) {
        var node = el('i', 'bi ' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function renderDetails(row, panel, data) {
        var state = row.getAttribute('data-state');
        panel.textContent = '';

        var used = el('span', 'pg-tr-details-value');
        (data.used || []).forEach(function (line) {
            var item = el(line.href ? 'a' : 'span');
            if (line.href) {
                item.href = line.href;
            }
            item.appendChild(icon(line.icon));
            item.appendChild(document.createTextNode(line.label));
            used.appendChild(item);
        });
        if (data.more > 0) {
            used.appendChild(el('span', 'pg-text-meta', text('details_more', data.more)));
        }
        if (!used.childNodes.length) {
            used.appendChild(el('span', 'pg-text-meta', text('details_unused')));
        }
        panel.appendChild(el('span', 'pg-tr-details-key', text('details_used')));
        panel.appendChild(used);

        if (data.earlier) {
            var earlier = el('span', 'pg-tr-details-value');
            var pair = el('span');
            var was = el('span', '', data.earlier.source);
            was.lang = config.source;
            pair.appendChild(was);
            if (data.earlier.translation) {
                pair.appendChild(el('span', 'pg-tr-details-arrow', '→'));
                var wasTranslated = el('span', '', data.earlier.translation);
                wasTranslated.lang = config.language;
                pair.appendChild(wasTranslated);
            }
            earlier.appendChild(pair);
            panel.appendChild(el('span', 'pg-tr-details-key', text('details_earlier')));
            panel.appendChild(earlier);
        }

        if (data.reason) {
            var reason = el('span', 'pg-tr-details-reason');
            reason.appendChild(icon('bi-exclamation-triangle'));
            reason.appendChild(document.createTextNode(data.reason));
            panel.appendChild(reason);
        }

        // "Translate again" for a text that has a translation and an engine
        // to send it to; a reviewed translation is left as it is by "keep
        // as in the source".
        var actions = el('span', 'pg-tr-details-actions');
        if ((state !== 'pending') && !strings.again_reason) {
            var again = el('button', 'pg-button pg-button-quiet pg-button-sm pg-tr-details-again');
            again.type = 'button';
            again.appendChild(icon('bi-arrow-repeat'));
            again.appendChild(document.createTextNode(text('again')));
            actions.appendChild(again);
        }
        if (state !== 'reviewed') {
            var keep = el('button', 'pg-button pg-button-quiet pg-button-sm pg-tr-details-keep');
            keep.type = 'button';
            keep.appendChild(icon('bi-check2-square'));
            keep.appendChild(document.createTextNode(text('keep_source')));
            actions.appendChild(keep);
        }
        if (actions.childNodes.length) {
            panel.appendChild(actions);
        }

        panel.setAttribute('data-loaded', '1');
    }

    function loadDetails(row, panel) {
        panel.setAttribute('aria-busy', 'true');
        return call({
            action: 'details',
            string_id: parseInt(row.getAttribute('data-string-id'), 10)
        }).then(function (data) {
            renderDetails(row, panel, data);
        }).catch(function (error) {
            panel.textContent = '';
            panel.appendChild(el('span', 'pg-tr-details-reason', error.message));
        }).then(function () {
            panel.removeAttribute('aria-busy');
        });
    }

    // "Keep as in the source and approve" for one row: the source becomes
    // its translation, approved, in place.
    function keepSource(row, button) {
        button.disabled = true;
        call({
            action: 'accept_source',
            string_ids: [parseInt(row.getAttribute('data-string-id'), 10)]
        }).then(function (data) {
            if (data.count > 0) {
                var source = row.querySelector('.pg-tr-source');
                row.querySelector('.pg-tr-target').value = source ? source.textContent : '';
                rowState(row, 'reviewed', 'manual', false);
                toast(data.message, 'success');
            } else {
                toast(data.message, 'error');
                button.disabled = false;
            }
        }).catch(function (error) {
            toast(error.message, 'error');
            button.disabled = false;
        });
    }

    app.addEventListener('click', function (event) {
        var save = event.target.closest('.pg-tr-save');
        var review = event.target.closest('.pg-tr-review');
        var toggle = event.target.closest('.pg-tr-details-toggle');
        var showAll = event.target.closest('.pg-tr-show-all');
        var keep = event.target.closest('.pg-tr-details-keep');
        var detailsAgain = event.target.closest('.pg-tr-details-again');

        if (detailsAgain) {
            if (!running && !preparing) {
                openAgain([detailsAgain.closest('.pg-tr-row')]);
            }
            return;
        }

        if (toggle) {
            var detailsRow = toggle.closest('.pg-tr-row');
            var panel = detailsRow.querySelector('.pg-tr-details');
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
            if (open && !panel.hasAttribute('data-loaded')) {
                loadDetails(detailsRow, panel);
            }
            return;
        }

        if (showAll) {
            var clamped = showAll.parentNode.querySelector('.pg-tr-source');
            var all = showAll.getAttribute('aria-expanded') !== 'true';
            clamped.classList.toggle('pg-tr-clamp', !all);
            showAll.setAttribute('aria-expanded', all ? 'true' : 'false');
            showAll.textContent = all ? text('show_less') : text('show_all');
            return;
        }

        if (keep) {
            if (!running) {
                keepSource(keep.closest('.pg-tr-row'), keep);
            }
            return;
        }

        if (save) {
            saveRow(save.closest('.pg-tr-row'), save, 'reviewed');
            return;
        }

        if (review) {
            var reviewRow = review.closest('.pg-tr-row');
            var next = (review.getAttribute('data-state') === 'reviewed') ? 'machine' : 'reviewed';

            // Typed and not saved yet: the new status goes with the typed
            // text, so the old text is not the one marked and the typed one
            // is not lost.
            if (reviewRow.classList.contains('pg-tr-dirty')) {
                saveRow(reviewRow, review, next);
                return;
            }

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

    // create() of a browser with the API but no service or language pack
    // behind it never settles either. A download in progress keeps the wait
    // alive; this long without a sign gives up with $message. While it waits,
    // "Stop" ends it through preparing.stop(). start(alive) calls create()
    // synchronously, so the click's gesture still reaches it.
    var CREATE_STALL_MS = 30000;
    var preparing = null;

    function guardCreate(start, message) {
        return new Promise(function (resolve, reject) {
            var timer = null;
            var settled = false;

            function settle() {
                settled = true;
                window.clearTimeout(timer);
                preparing = null;
            }

            function alive() {
                if (settled) {
                    return;
                }
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    settle();
                    reject(new Error(message));
                }, CREATE_STALL_MS);
            }

            preparing = {
                stop: function () {
                    if (!settled) {
                        settle();
                        var stopped = new Error(text('stopped'));
                        stopped.stopped = true;
                        reject(stopped);
                    }
                }
            };

            alive();

            start(alive).then(function (value) {
                if (settled) {
                    // Ready after the wait gave up: let it go.
                    if (value && (typeof value.destroy === 'function')) {
                        value.destroy();
                    }
                    return;
                }
                settle();
                resolve(value);
            }, function (error) {
                if (!settled) {
                    settle();
                    reject(error);
                }
            });
        });
    }

    // Created synchronously in the click handler itself: the API wants a user
    // gesture when the model still has to be downloaded, and the gesture is
    // gone once anything is awaited first - an availability() check that
    // takes a few seconds is enough to lose it. So create() is called at once;
    // availability() is asked only to explain a failure.
    function createTranslator() {
        if (!translatorSupported()) {
            return Promise.reject(new Error(text('translator_missing')));
        }

        showProgress(text('preparing'), 0, 1);

        return guardCreate(function (alive) {
            return startTranslator(alive);
        }, text('translator_stalled', config.source, config.language));
    }

    function startTranslator(alive) {
        var options = {
            sourceLanguage: config.source,
            targetLanguage: config.language,
            monitor: function (monitor) {
                monitor.addEventListener('downloadprogress', function (event) {
                    alive();
                    var percent = (event.total > 0) ? Math.round((event.loaded / event.total) * 100) : Math.round((event.loaded || 0) * 100);
                    showProgress(text('downloading', percent), percent, 100);
                });
            }
        };

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

        showProgress(text('model_preparing'), 0, 1);

        return guardCreate(startModel, text('model_stalled'));
    }

    function startModel(alive) {
        var options = modelLanguages(modelInput);
        options.initialPrompts = [{ role: 'system', content: config.ai_instructions || '' }];
        options.monitor = function (monitor) {
            monitor.addEventListener('downloadprogress', function (event) {
                alive();
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

                if (data.busy) {
                    // Another run (the scheduled job or a second open
                    // screen) is working the job: look again shortly, the
                    // counts above follow its progress.
                    return new Promise(function (resolve) { window.setTimeout(resolve, 5000); }).then(step);
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

    // ── a job: the engine it is opened for, then worked here for a browser
    //    engine, followed for Claude, run turn by turn for a server engine ──

    // A language the model does not write goes to the Translator API, in a
    // job of that engine (the server then packages its texts in runs).
    // Decided before anything is awaited: the click is the gesture a
    // download wants. Before the check has answered, a create() refused for
    // the language tells the same.
    // An update the server opens with a set engine ("Try again with the
    // failed ones") is strict: it keeps that engine, so the browser AI is not
    // swapped for the Translator API there.
    function prepareJob(engine, strict) {
        engine = engine || config.engine;

        var job = { engine: (!strict && (engine === 'chrome_ai') && (modelWrites === false) && modelSupported() && translatorSupported()) ? 'chrome' : engine };

        job.ready = (job.engine === 'chrome') ? createTranslator().then(translatorItem)
            : ((job.engine === 'chrome_ai') ? createModel().then(modelItem, function (error) {
                if (strict || !error.unwritable || !translatorSupported()) {
                    throw error;
                }
                job.engine = 'chrome';
                return createTranslator().then(translatorItem);
            }) : Promise.resolve(null));

        return job;
    }

    // The job the server opened (data: its job_id, job, pending, queue and
    // error), with the message the opening is told with.
    function followJob(job, translateItem, data, message, scopeLabel) {
        toast(message, 'info');
        titleJob(scopeLabel, job.engine);

        if (translateItem) {
            return runBrowserJob(translateItem, { id: data.job_id, total: data.pending, done: 0, failed: 0 });
        }

        if (job.engine === 'claude') {
            if (data.error) {
                toast(data.error, 'error');
            } else if (data.queue === 'held') {
                toast(text('queue_held', (data.job && data.job.error) || ''), 'warning');
            }

            return watchQueueJob(data.job || { id: data.job_id, total: data.pending, done_count: 0, failed_count: 0 });
        }

        // A server engine: the call only opened the job; every turn of
        // translating is a run call of its own.
        return runServerJob(data.job);
    }

    function jobFailed(error) {
        hideProgress();
        running = null;
        toast(error.message, error.stopped ? 'info' : 'error');
    }

    // ── "Translate pending" ──

    if (updateButton) {
        updateButton.addEventListener('click', function () {
            if (running || preparing) {
                return;
            }

            updateButton.disabled = true;
            showProgress(text('scanning'), 0, 1);

            var job = prepareJob();

            job.ready.then(function (translateItem) {
                // A model's download progress may have taken the line.
                if (translateItem) {
                    showProgress(text('scanning'), 0, 1);
                }

                if (job.engine !== config.engine) {
                    toast(text('model_fallback', config.language), 'info');
                }

                // A browser job an earlier visit left open is picked up where
                // it stopped rather than opened again.
                if (translateItem && config.open_job && (config.open_job.engine === job.engine) && ['queued', 'sent', 'running'].indexOf(config.open_job.status) !== -1) {
                    titleJob(config.open_job.scope_label, job.engine);
                    return runBrowserJob(translateItem, { id: config.open_job.id, total: config.open_job.total, done: config.open_job.done, failed: config.open_job.failed });
                }

                // The site is scanned in turns: a site of many pages takes
                // longer than one request may. Each answer says where the
                // next turn starts; the last one opens the job.
                var scan = function (from) {
                    return call({ action: 'update', scope: config.scope, engine: (job.engine !== config.engine) ? job.engine : '', from: from }).then(function (data) {
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

                    return followJob(job, translateItem, data, text('sent', data.pending));
                });
            }).catch(jobFailed).then(function () {
                updateButton.disabled = false;
            });
        });
    }

    // ── "Scan for new texts": the scope is read again, in turns, and what is
    //    new is listed as pending; nothing goes to an engine (the update
    //    action with the engine "By hand" opens no job) ──

    var scanButton = document.getElementById('pg_tr_scan');

    if (scanButton) {
        scanButton.addEventListener('click', function () {
            if (running || preparing) {
                return;
            }

            scanButton.disabled = true;

            if (updateButton) {
                updateButton.disabled = true;
            }

            showProgress(text('scanning'), 0, 1);

            var scan = function (from) {
                return call({ action: 'update', scope: config.scope, engine: 'manual', from: from }).then(function (data) {
                    if (data.extracting) {
                        showProgress(text('scanning_progress', data.next, data.total), data.next, data.total);
                        return scan(data.next);
                    }
                    return data;
                });
            };

            scan(0).then(function (data) {
                var message = data.pending ? text('scanned', data.pending) : text('scanned_none');

                hideProgress();
                toast(message, 'info');
                afterReload(message, 'info');
                window.setTimeout(function () { window.location.reload(); }, 800);
            }).catch(function (error) {
                hideProgress();
                toast(error.message, 'error');
                scanButton.disabled = false;

                if (updateButton && (config.engine_ready !== false)) {
                    updateButton.disabled = false;
                }
            });
        });
    }

    // ── the frame: "/" puts the cursor in "Search in the texts", "Find a
    //    page" narrows the rail's pages as it is typed, and the address
    //    follows the tab chosen so a reload or a shared link opens it ──

    var searchBox = document.getElementById('pg_tr_search');

    document.addEventListener('keydown', function (event) {
        var target = event.target;

        if ((event.key !== '/') || event.ctrlKey || event.metaKey || event.altKey || !searchBox || !searchBox.offsetParent) {
            return;
        }

        if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))) {
            return;
        }

        event.preventDefault();
        searchBox.focus();
        searchBox.select();
    });

    // On a phone the rail is a sheet from the lower edge, opened by the
    // scope button and closed by its backdrop or Escape.
    var scopeButton = app.querySelector('.pg-tr-scope');
    var rail = document.getElementById('pg_tr_rail');
    var railBackdrop = app.querySelector('.pg-tr-rail-backdrop');

    var railSheet = function (open) {
        rail.classList.toggle('show', open);
        railBackdrop.classList.toggle('show', open);
        scopeButton.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            var current = rail.querySelector('.pg-tr-rail-row.active') || rail.querySelector('a, input');

            if (current) {
                current.focus();
            }
        } else {
            scopeButton.focus();
        }
    };

    if (scopeButton && rail && railBackdrop) {
        scopeButton.addEventListener('click', function () {
            railSheet(!rail.classList.contains('show'));
        });
        railBackdrop.addEventListener('click', function () {
            railSheet(false);
        });
        document.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape') && rail.classList.contains('show')) {
                railSheet(false);
            }
        });
    }

    // "Scan for new texts" in "More" on a phone presses the head's button.
    Array.prototype.forEach.call(app.querySelectorAll('[data-pg-tr-scan-proxy]'), function (item) {
        item.addEventListener('click', function () {
            if (scanButton) {
                scanButton.click();
            }
        });
    });

    var findPage = document.getElementById('pg_tr_find_page');

    if (findPage) {
        var findNone = document.getElementById('pg_tr_find_none');

        findPage.addEventListener('input', function () {
            var needle = findPage.value.trim().toLocaleLowerCase();
            var shown = 0;

            Array.prototype.forEach.call(app.querySelectorAll('[data-pg-tr-page]'), function (row) {
                var name = row.querySelector('.pg-tr-rail-name');
                var match = (needle === '') || ((name ? ((name.getAttribute('title') || '') + ' ' + name.textContent) : '').toLocaleLowerCase().indexOf(needle) !== -1);

                row.hidden = !match;
                shown += match ? 1 : 0;
            });

            if (findNone) {
                findNone.hidden = (shown > 0) || (needle === '');
            }
        });
    }

    // "Search the glossary" narrows the terms as typed: the term, the
    // translation to use and the note are matched.
    var findTerm = document.getElementById('pg_tr_find_term');

    if (findTerm) {
        var termNone = document.getElementById('pg_tr_term_none');
        var termTable = app.querySelector('.pg-tr-terms');

        findTerm.addEventListener('input', function () {
            var needle = findTerm.value.trim().toLocaleLowerCase();
            var shown = 0;

            Array.prototype.forEach.call(app.querySelectorAll('.pg-tr-term'), function (row) {
                var haystack = [row.getAttribute('data-term'), row.getAttribute('data-translation'), row.getAttribute('data-note')].join(' ').toLocaleLowerCase();
                var match = (needle === '') || (haystack.indexOf(needle) !== -1);

                row.hidden = !match;
                shown += match ? 1 : 0;
            });

            if (termTable) {
                termTable.hidden = (shown === 0);
            }

            if (termNone) {
                termNone.hidden = (shown > 0);
            }
        });
    }

    // A term's remove button submits the one delete form after the question.
    var termDelete = document.getElementById('pg_tr_glossary_delete');

    if (termDelete) {
        termDelete.addEventListener('submit', function (event) {
            if (!window.confirm(termDelete.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    }

    app.addEventListener('click', function (event) {
        var tab = event.target.closest ? event.target.closest('[data-pg-tr-tab]') : null;

        if (!tab || !window.history || !window.history.replaceState) {
            return;
        }

        var address = new URL(window.location.href);
        var name = tab.getAttribute('data-pg-tr-tab');

        if (name === 'texts') {
            address.searchParams.delete('tab');
        } else {
            address.searchParams.set('tab', name);
        }

        window.history.replaceState(window.history.state, '', address.toString());
    });

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            // Nothing is sent yet while the browser gets its translator
            // ready: that wait is left without a question.
            if (preparing) {
                preparing.stop();
            } else if (running && window.confirm(text('confirm_cancel'))) {
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
            titleJob(config.open_job.scope_label, config.open_job.engine);
            resume({ id: config.open_job.id, total: config.open_job.total, done_count: config.open_job.done, failed_count: config.open_job.failed }).catch(function (error) {
                hideProgress();
                running = null;
                toast(error.message, 'error');
            });
        }
    }

    // ── "Automation for <language>": the engine, its fallback and
    //    "Translate on save" are saved together with the dialog's button,
    //    never on a pick. The engine's note follows the select; "Translate
    //    on save" is off with "By hand", and the five-minute line stands only
    //    for an engine the scheduled job works ──

    var automationModal = document.getElementById('pg_tr_automation_modal');

    if (automationModal && window.pgUI) {
        var autoEngine = automationModal.querySelector('#pg_tr_auto_engine');
        var autoFallback = automationModal.querySelector('#pg_tr_auto_fallback');
        var autoSave = automationModal.querySelector('#pg_tr_auto_save');
        var autoSaveOff = automationModal.querySelector('.pg-tr-auto-save-off');
        var autoSchedule = automationModal.querySelector('.pg-tr-auto-schedule');
        var autoGo = automationModal.querySelector('.pg-tr-auto-go');
        var autoSaveWas = autoSave ? autoSave.checked : false;

        var syncAutomation = function () {
            var engine = autoEngine.value;
            var kind = (config.engine_kinds || {})[engine] || 'panel';

            Array.prototype.forEach.call(automationModal.querySelectorAll('[data-pg-tr-engine-note]'), function (note) {
                note.hidden = (note.getAttribute('data-pg-tr-engine-note') !== engine);
            });

            // The engine is never its own fallback.
            Array.prototype.forEach.call(autoFallback.options, function (option) {
                option.disabled = (option.value !== '') && (option.value === engine);
            });

            if (autoFallback.value === engine) {
                autoFallback.value = '';
            }

            if (autoSave) {
                if (engine === 'manual') {
                    if (!autoSave.disabled) {
                        autoSaveWas = autoSave.checked;
                    }
                    autoSave.checked = false;
                    autoSave.disabled = true;
                } else if (autoSave.disabled) {
                    autoSave.disabled = false;
                    autoSave.checked = autoSaveWas;
                }
            }

            if (autoSaveOff) {
                autoSaveOff.hidden = (engine !== 'manual');
            }

            if (autoSchedule) {
                autoSchedule.hidden = ['server', 'queue'].indexOf(kind) === -1;
            }
        };

        autoEngine.addEventListener('change', syncAutomation);
        syncAutomation();

        autoGo.addEventListener('click', function () {
            if (autoGo.disabled) {
                return;
            }

            autoGo.disabled = true;

            call({
                action: 'automation',
                engine: autoEngine.value,
                fallback_engine: autoFallback.value,
                auto_update: (autoSave && autoSave.checked) ? 1 : 0
            }).then(function () {
                // The head's jobs, the line and the notes follow the engine.
                afterReload(text('automation_saved'), 'success');
                window.location.reload();
            }).catch(function (error) {
                autoGo.disabled = false;
                toast(error.message, 'error');
            });
        });
    }

    // ── "Activity": a row opens its update's drawer with the figures, the
    //    error as stored and the failed texts with their reasons. "Try again
    //    with the failed ones" sends them to the same engine, its caret to
    //    the fallback engine; an update still open has "Stop" ──

    var jobDrawer = document.getElementById('pg_tr_job_drawer');
    var drawerJob = null;   // { id, engine, scope_label, open, failed }

    function fact(label, value, bad) {
        var cell = el('div', 'pg-card pg-figure');
        var number = el('span', 'pg-figure-value' + (bad ? ' fg-danger' : ''), value);
        cell.appendChild(number);
        cell.appendChild(el('span', 'pg-figure-label', label));
        return cell;
    }

    function textAddress(hash) {
        var address = new URL(config.tab_url || window.location.href, window.location.href);
        ['page_id', 'group', 'filter', 'p', 'tab', 'af', 'ap'].forEach(function (key) {
            address.searchParams.delete(key);
        });
        address.searchParams.set('q', String(hash).slice(0, 8));
        return address.toString();
    }

    function renderJob(data) {
        var body = jobDrawer.querySelector('.pg-tr-job-body');
        var job = data.job || {};
        var status = String(job.status || '');
        var labels = strings.status_labels || {};
        var pill = jobDrawer.querySelector('.pg-tr-job-pill');
        var failedTotal = parseInt(data.failed_total, 10) || 0;

        pill.className = 'pg-pill pg-tr-job-pill ' + ((status === 'done') ? 'pg-tone-teal' : ((['failed', 'cancelled'].indexOf(status) !== -1) ? 'pg-tone-rose' : 'pg-tone-amber'));
        pill.textContent = labels[status] || status;
        body.textContent = '';

        var facts = el('div', 'pg-figures pg-tr-job-facts mb-5');
        facts.appendChild(fact(text('translated'), String(parseInt(job.done_count, 10) || 0)));
        facts.appendChild(fact(text('failed'), String(failedTotal), failedTotal > 0));
        facts.appendChild(fact(text('skipped'), String(parseInt(data.skipped, 10) || 0)));
        body.appendChild(facts);

        if (data.open) {
            var total = parseInt(job.total, 10) || 0;
            var done = parseInt(job.done_count, 10) || 0;
            var line = el('div', 'pg-tr-job-progress w-100 mb-5');
            var meter = el('span', 'progress flex-grow-1');
            var bar = el('span', 'progress-bar');
            bar.style.width = ((total > 0) ? Math.round((done / total) * 100) : 0) + '%';
            meter.appendChild(bar);
            line.appendChild(el('span', 'pg-text-2 text-nowrap', text('progress', done, total)));
            line.appendChild(meter);
            body.appendChild(line);
        }

        var kv = el('dl', 'pg-kv mb-5');
        [[text('engine_label'), data.engine_label || job.engine],
            [text('started'), data.started_text + (data.by ? ' · ' + data.by : '')],
            [text('finished_label'), data.finished_text || text('not_yet')]].forEach(function (pair) {
            var item = el('div');
            item.appendChild(el('dt', '', pair[0]));
            item.appendChild(el('dd', '', pair[1]));
            kv.appendChild(item);
        });
        body.appendChild(kv);

        if (job.error) {
            var note = el('div', 'pg-card-inner pg-tr-failed-reason p-3 mb-5');
            note.appendChild(icon('bi-exclamation-circle'));
            note.appendChild(el('span', '', job.error));
            body.appendChild(note);
        }

        body.appendChild(el('div', 'pg-text-meta fw-medium mb-2', text('failed_texts')));

        if (!data.failed || !data.failed.length) {
            body.appendChild(el('p', 'pg-text-2 mb-0', data.open ? text('none_so_far') : text('none_failed')));
        } else {
            data.failed.forEach(function (item) {
                var row = el('div', 'pg-tr-failed py-3');
                var source = el('div', 'pg-tr-failed-source mb-1', item.source);
                source.setAttribute('lang', config.source || '');
                row.appendChild(source);
                var reason = el('div', 'pg-tr-failed-reason');
                reason.appendChild(icon('bi-exclamation-circle'));
                reason.appendChild(el('span', '', item.error || text('no_reason')));
                var open = el('a', 'ms-auto', text('open_text'));
                open.href = textAddress(item.hash);
                reason.appendChild(open);
                row.appendChild(reason);
                body.appendChild(row);
            });

            if (failedTotal > data.failed.length) {
                body.appendChild(el('p', 'pg-text-2 mt-3 mb-0', text('details_more', failedTotal - data.failed.length)));
            }
        }

        drawerJob.open = !!data.open;
        drawerJob.failed = failedTotal;
        footJob();
    }

    // The drawer's foot: "Stop" while the update is open, the retry once it
    // ended with failed texts. The caret's fallback is offered only when it
    // is another engine than the update's own.
    function footJob() {
        var retryWrap = jobDrawer.querySelector('.pg-tr-job-retry-wrap');
        var stop = jobDrawer.querySelector('.pg-tr-job-stop');
        var more = jobDrawer.querySelector('.pg-tr-job-retry-more');

        stop.hidden = !drawerJob || !drawerJob.open;
        retryWrap.hidden = !drawerJob || drawerJob.open || !drawerJob.failed;

        if (more) {
            more.hidden = !drawerJob || (config.fallback === drawerJob.engine);
        }
    }

    function openJob(id, engine, scopeLabel) {
        if (!jobDrawer || !window.pgUI) {
            return;
        }

        drawerJob = { id: id, engine: engine, scope_label: scopeLabel, open: false, failed: 0 };
        jobDrawer.querySelector('#pg_tr_job_drawer_title').textContent = text('job_title', scopeLabel || '');
        jobDrawer.querySelector('.pg-tr-job-pill').textContent = '';
        jobDrawer.querySelector('.pg-tr-job-body').textContent = text('loading');
        footJob();
        pgUI.drawer.show(jobDrawer);

        call({ action: 'activity_job', job_id: id }).then(function (data) {
            if (drawerJob && (drawerJob.id === id)) {
                renderJob(data);
            }
        }).catch(function (error) {
            jobDrawer.querySelector('.pg-tr-job-body').textContent = error.message;
        });
    }

    if (jobDrawer) {
        document.addEventListener('click', function (event) {
            var row = event.target.closest ? event.target.closest('.pg-tr-job') : null;

            if (!row || (event.target.closest('a') && !event.target.closest('.pg-tr-job-open'))) {
                return;
            }

            openJob(parseInt(row.getAttribute('data-job-id'), 10), row.getAttribute('data-engine'), row.getAttribute('data-scope-label'));
        });

        var retryJob = function (fallback) {
            if (!drawerJob || busy()) {
                return;
            }

            var engine = fallback ? config.fallback : drawerJob.engine;
            var scopeLabel = drawerJob.scope_label;
            // A browser engine's translator is made in this click.
            var job = prepareJob(engine, true);

            pgUI.drawer.hide(jobDrawer);

            job.ready.then(function (translateItem) {
                return call({ action: 'retry', job_id: drawerJob.id, fallback: fallback ? 1 : 0 }).then(function (data) {
                    if (!data.job_id) {
                        toast(text('retry_none'), 'info');
                        return;
                    }

                    return followJob(job, translateItem, data, text('sent', data.pending), scopeLabel);
                });
            }).catch(jobFailed);
        };

        jobDrawer.querySelector('.pg-tr-job-retry').addEventListener('click', function () {
            retryJob(false);
        });

        var retryFallback = jobDrawer.querySelector('.pg-tr-job-retry-fallback');

        if (retryFallback) {
            retryFallback.addEventListener('click', function () {
                retryJob(true);
            });
        }

        // "Stop": the update this screen follows stops through its own loop;
        // another one is cancelled here and the list read again.
        jobDrawer.querySelector('.pg-tr-job-stop').addEventListener('click', function () {
            if (!drawerJob || !window.confirm(text('confirm_cancel'))) {
                return;
            }

            if (running && (running.id === drawerJob.id)) {
                running.stop = true;
                pgUI.drawer.hide(jobDrawer);
                return;
            }

            call({ action: 'cancel', job_id: drawerJob.id }).then(function () {
                afterReload(text('stopped'), 'info');
                window.location.reload();
            }).catch(function (error) {
                toast(error.message, 'error');
            });
        });
    }

    // "Open in Activity" of the progress card: the tab, then the drawer of
    // the update followed.
    if (progressOpen) {
        progressOpen.addEventListener('click', function (event) {
            var tab = document.getElementById('pg_tr_tab_activity');

            if (!running || !tab || !jobDrawer) {
                return;
            }

            event.preventDefault();
            tab.click();
            openJob(running.id, jobEngine, jobScope);
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
        // Read when used: a row saved or reviewed on the screen moves them
        // (countReviewAll()).
        var reviewAllPlain = function () {
            return parseInt(reviewAllGo.getAttribute('data-plain'), 10) || 0;
        };
        var reviewAllFlaggedCount = function () {
            return parseInt(reviewAllGo.getAttribute('data-flagged'), 10) || 0;
        };

        function syncReviewAll() {
            var count = reviewAllPlain() + (reviewAllFlagged.checked ? reviewAllFlaggedCount() : 0);
            reviewAllCount.textContent = text('review_all_count', count);
            reviewAllGo.disabled = (count === 0);
        }

        reviewAllFlagged.addEventListener('change', syncReviewAll);

        pgUI.dialog.on(reviewAllModal, 'show', function () {
            reviewAllFlagged.checked = (reviewAllPlain() === 0) && (reviewAllFlaggedCount() > 0);
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

    // ── the selection: a tick on every row, "Select all on this page", and
    //    the bar at the foot of the list with what can be done to the ticked
    //    texts. Each action changes the rows in place ──

    var pickAll = document.getElementById('pg_tr_pick_all');
    var selBar = document.getElementById('pg_tr_selbar');
    var acceptModal = document.getElementById('pg_tr_accept_modal');
    var againModal = document.getElementById('pg_tr_again_modal');
    var clearPickedModal = document.getElementById('pg_tr_clear_picked_modal');
    var againRows = [];

    function picks() {
        return Array.prototype.slice.call(app.querySelectorAll('.pg-tr-pick'));
    }

    function pickedRows() {
        return picks().filter(function (box) { return box.checked; }).map(function (box) { return box.closest('.pg-tr-row'); });
    }

    function rowIds(rows) {
        return rows.map(function (row) { return parseInt(row.getAttribute('data-string-id'), 10); });
    }

    function rowsOf(ids) {
        return (ids || []).map(function (id) {
            return app.querySelector('.pg-tr-row[data-string-id="' + parseInt(id, 10) + '"]');
        }).filter(Boolean);
    }

    function inState(rows, state) {
        return rows.filter(function (row) { return row.getAttribute('data-state') === state; }).length;
    }

    function syncPicks() {
        var all = picks();
        var count = pickedRows().length;

        if (selBar) {
            selBar.hidden = (count === 0);
            selBar.querySelector('.pg-tr-selbar-count').textContent = text('selected', count);
        }

        if (pickAll) {
            pickAll.checked = (all.length > 0) && (count === all.length);
            pickAll.indeterminate = (count > 0) && (count < all.length);
        }
    }

    function unpick() {
        picks().forEach(function (box) { box.checked = false; });
        syncPicks();
    }

    function flagged(row) {
        var warn = row.querySelector('.pg-tr-suspicious');
        return !!(warn && !warn.classList.contains('d-none'));
    }

    // A translation deleted: the row is pending again, its box empty.
    function rowCleared(row) {
        row.querySelector('.pg-tr-target').value = '';
        rowState(row, 'pending', '', false);
    }

    function busy() {
        return !!(running || preparing);
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

    app.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('.pg-tr-sel-approve, .pg-tr-sel-again, .pg-tr-sel-keep, .pg-tr-sel-clear, .pg-tr-sel-none') : null;

        if (!button || button.disabled) {
            return;
        }

        var rows = pickedRows();

        if (button.classList.contains('pg-tr-sel-none')) {
            unpick();
            return;
        }

        if (!rows.length || busy()) {
            return;
        }

        if (button.classList.contains('pg-tr-sel-approve')) {
            approvePicked(button, rows);
        } else if (button.classList.contains('pg-tr-sel-again')) {
            openAgain(rows);
        } else if (button.classList.contains('pg-tr-sel-keep') && acceptModal && window.pgUI) {
            acceptModal.querySelector('.pg-tr-accept-count').textContent = text('accept_count', rows.length);
            pgUI.dialog.show(acceptModal);
        } else if (button.classList.contains('pg-tr-sel-clear') && clearPickedModal && window.pgUI) {
            pgUI.dialog.show(clearPickedModal);
        }
    });

    // "Approve": the ticked machine translations reviewed at once.
    function approvePicked(button, rows) {
        button.disabled = true;

        call({ action: 'approve', string_ids: rowIds(rows) }).then(function (data) {
            rowsOf(data.ids).forEach(function (row) {
                var engineEl = row.querySelector('.pg-tr-engine');
                rowState(row, 'reviewed', engineEl ? engineEl.textContent : '', flagged(row));
            });
            toast(data.message, (data.count > 0) ? 'success' : 'info');
            unpick();
        }).catch(function (error) {
            toast(error.message, 'error');
        }).then(function () {
            button.disabled = false;
        });
    }

    // "Keep as in the source and approve": today's dialog and wording; the
    // source text becomes the translation of each ticked text not reviewed.
    if (acceptModal && window.pgUI) {
        var acceptGo = acceptModal.querySelector('.pg-tr-accept-go');

        acceptGo.addEventListener('click', function () {
            var rows = pickedRows();

            if (busy() || !rows.length) {
                return;
            }

            acceptGo.disabled = true;

            call({ action: 'accept_source', string_ids: rowIds(rows) }).then(function (data) {
                rowsOf(data.ids).forEach(function (row) {
                    var source = row.querySelector('.pg-tr-source');
                    row.querySelector('.pg-tr-target').value = source ? source.textContent : '';
                    rowState(row, 'reviewed', 'manual', false);
                });
                pgUI.dialog.hide(acceptModal);
                toast(data.message, (data.count > 0) ? 'success' : 'info');
                unpick();
            }).catch(function (error) {
                toast(error.message, 'error');
            }).then(function () {
                acceptGo.disabled = false;
            });
        });
    }

    // "Clear": the ticked texts' translations deleted, the reviewed ones
    // when ticked; the dialog names the count.
    if (clearPickedModal && window.pgUI) {
        var clearPickedGo = clearPickedModal.querySelector('.pg-tr-clear-picked-go');
        var clearPickedReviewed = clearPickedModal.querySelector('.pg-tr-clear-picked-reviewed');

        var syncClearPicked = function () {
            var rows = pickedRows();
            var reviewed = inState(rows, 'reviewed');
            var count = inState(rows, 'machine') + (clearPickedReviewed.checked ? reviewed : 0);

            clearPickedReviewed.disabled = (reviewed === 0);
            clearPickedModal.querySelector('.pg-tr-clear-picked-reviewed-count').textContent = reviewed;
            clearPickedModal.querySelector('.pg-tr-clear-picked-count').textContent = text('clear_count', count);
            clearPickedGo.disabled = (count === 0);
        };

        clearPickedReviewed.addEventListener('change', syncClearPicked);

        pgUI.dialog.on(clearPickedModal, 'show', function () {
            clearPickedReviewed.checked = false;
            syncClearPicked();
        });

        clearPickedGo.addEventListener('click', function () {
            var rows = pickedRows();

            if (busy() || !rows.length) {
                return;
            }

            clearPickedGo.disabled = true;

            call({ action: 'clear', scope: config.scope, string_ids: rowIds(rows), reviewed: clearPickedReviewed.checked ? 1 : 0 }).then(function (data) {
                rowsOf(data.ids).forEach(rowCleared);
                pgUI.dialog.hide(clearPickedModal);
                toast(data.message, 'success');
                unpick();
            }).catch(function (error) {
                toast(error.message, 'error');
            }).then(function () {
                clearPickedGo.disabled = false;
            });
        });
    }

    // "Translate again", from the bar or a row's details: the texts are
    // sent to the language's engine in a job of their own, followed as
    // "Translate pending" follows one. A reviewed translation is replaced
    // only when the dialog's tick says so.
    function openAgain(rows) {
        if (!againModal || !window.pgUI || strings.again_reason) {
            return;
        }

        var reviewed = inState(rows, 'reviewed');
        var box = againModal.querySelector('.pg-tr-again-reviewed');

        againRows = rows;
        againModal.querySelector('.pg-tr-again-count').textContent = text('again_count', rows.length, config.engine_label || '');
        againModal.querySelector('.pg-tr-again-reviewed-count').textContent = reviewed;
        box.checked = false;
        box.disabled = (reviewed === 0);
        pgUI.dialog.show(againModal);
    }

    if (againModal && window.pgUI) {
        var againGo = againModal.querySelector('.pg-tr-again-go');

        againGo.addEventListener('click', function () {
            var rows = againRows;
            var reviewed = againModal.querySelector('.pg-tr-again-reviewed').checked;

            if (busy() || !rows.length) {
                return;
            }

            // The browser's translator is made in this click (the gesture
            // a download wants), before the dialog closes.
            var job = prepareJob();

            pgUI.dialog.hide(againModal);

            job.ready.then(function (translateItem) {
                if (job.engine !== config.engine) {
                    toast(text('model_fallback', config.language), 'info');
                }

                return call({
                    action: 'again',
                    scope: config.scope,
                    string_ids: rowIds(rows),
                    reviewed: reviewed ? 1 : 0,
                    engine: (job.engine !== config.engine) ? job.engine : ''
                }).then(function (data) {
                    rowsOf(data.sent).forEach(rowCleared);
                    unpick();

                    if (!data.job_id) {
                        hideProgress();
                        toast(data.message, 'info');
                        return;
                    }

                    return followJob(job, translateItem, data, data.message);
                });
            }).catch(jobFailed);
        });
    }

    syncPicks();

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

        // A save the server refused comes back with what was typed in the
        // fields (data-reopen): the dialog opens again with it as it is.
        var reopening = (glossaryModal.getAttribute('data-reopen') === '1');

        pgUI.dialog.on(glossaryModal, 'show', function (event) {
            if (reopening) {
                reopening = false;
                syncKeep();
                return;
            }

            var row = event.relatedTarget ? event.relatedTarget.closest('.pg-tr-term') : null;

            [fields.term, fields.translation].forEach(function (field) {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');
            });

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
            var invalid = glossaryModal.querySelector('.is-invalid');
            (invalid || fields.term).focus();
        });

        if (reopening) {
            pgUI.dialog.show(glossaryModal);
        }
    }
})();
