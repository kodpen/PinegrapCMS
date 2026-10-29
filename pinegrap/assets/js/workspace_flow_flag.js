/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a flow run's flag: its state, how long it took and one line of
 * its result, the rest folded behind a down arrow. The flow canvas draws it
 * above a flow's last box and in the flow list (assets/js/workspace_flow.js),
 * the channel screen under the line of a run started from the composer
 * (assets/js/workspace.js). It needs only window.PGWsKit and the fl_flag_*
 * and fl_run_* texts of ws_flow_js_strings(), which every workspace screen
 * carries.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Each state's word and mark; a run done is Completed.
    var FLAG_STATES = {
        done: ['fl_flag_done', 'bi-flag-fill'],
        waiting: ['fl_run_waiting', 'bi-hourglass-split'],
        running: ['fl_run_running', 'bi-arrow-repeat'],
        failed: ['fl_run_failed', 'bi-x-octagon-fill'],
        cancelled: ['fl_run_cancelled_state', 'bi-slash-circle']
    };
    // A result longer than this many characters, or of more than one line,
    // folds behind the flag's down arrow.
    var FLAG_LINE = 60;

    // The ids of the folded parts, unique on the page.
    var uid = 0;

    // Does a result fold behind the down arrow? Over FLAG_LINE characters
    // or more than one line.
    function flagFolds(result) {
        return (result.length > FLAG_LINE) || (result.indexOf('\n') >= 0);
    }

    // How long a run took, or has taken so far: "45 s", "12 min",
    // "2 h 5 min", "3 d 4 h".
    function flagDuration(run, tr) {
        var from = Date.parse(run.started_at || '');
        var to = run.finished_at ? Date.parse(run.finished_at) : Date.now();
        var secs = Math.max(0, Math.round((to - from) / 1000));

        if (isNaN(secs)) {
            return '';
        }

        if (secs < 60) {
            return tr('fl_flag_sec', secs);
        }

        var mins = Math.floor(secs / 60);

        if (mins < 60) {
            return tr('fl_flag_min', mins);
        }

        if (mins < 1440) {
            return tr('fl_flag_hour', Math.floor(mins / 60), mins % 60);
        }

        return tr('fl_flag_day', Math.floor(mins / 1440), Math.floor((mins % 1440) / 60));
    }

    // A run's flag: Completed once it ended well, Waiting, Running, Failed
    // or Cancelled until then, in its tone with a mark beside the word, how
    // long it took and one line of its result (the ws_flow_run_result_pick()
    // sentence). A longer result folds behind a down arrow, which opens it
    // whole with options.open, the link to the run, under it; a running run
    // the person may cancel has options.stop as Stop. options.key names the
    // flag's controls for the focus kept across a redraw. Needs only the
    // kit (window.PGWsKit) and the fl_flag_* and fl_run_* texts.
    function runFlag(run, options) {
        var kit = window.PGWsKit;
        var tr = kit.t;
        var mk = kit.el;
        var opts = options || {};
        var known = FLAG_STATES[run.status];
        var flag = mk('div', 'wsf-flag wsf-flag-' + (known ? run.status : 'none'));
        var line = mk('div', 'wsf-flag-line');
        var result = String(run.result || '');
        var long = flagFolds(result);
        var key = opts.key || ('flag-' + run.id);

        flag.setAttribute('role', 'group');
        flag.setAttribute('aria-label', opts.label || tr('fl_flag_last'));
        line.appendChild(kit.icon(known ? known[1] : 'bi-question-circle'));
        line.appendChild(mk('span', 'wsf-flag-state', known ? tr(known[0]) : String(run.status || '')));

        var took = flagDuration(run, tr);

        if (took) {
            line.appendChild(mk('span', 'wsf-flag-time', took));
        }

        if (result) {
            var first = mk('span', 'wsf-flag-result', result.split('\n')[0]);

            first.title = result;
            line.appendChild(first);
        }

        if ((run.status === 'running') && run.can_cancel && opts.stop) {
            var stop = kit.button('btn btn-sm btn-link', tr('fl_flag_stop'), 'bi-stop-circle');

            stop.setAttribute('data-refocus', key + '-stop');
            stop.addEventListener('click', function () {
                opts.stop(run, stop);
            });
            line.appendChild(stop);
        }

        flag.appendChild(line);

        if (!long && !opts.open) {
            return flag;
        }

        var fold = kit.button('btn btn-sm btn-link wsf-flag-fold', '', 'bi-chevron-down', tr('fl_flag_more'));
        var whole = mk('div', 'wsf-flag-whole d-none');

        uid++;
        whole.id = 'wsf-flag-whole-' + uid;
        fold.setAttribute('aria-expanded', 'false');
        fold.setAttribute('aria-controls', whole.id);
        fold.setAttribute('data-refocus', key + '-fold');
        fold.addEventListener('click', function () {
            var shown = fold.getAttribute('aria-expanded') !== 'true';

            fold.setAttribute('aria-expanded', shown ? 'true' : 'false');
            fold.title = tr(shown ? 'fl_flag_less' : 'fl_flag_more');
            fold.setAttribute('aria-label', fold.title);
            whole.classList.toggle('d-none', !shown);
        });
        line.appendChild(fold);

        if (long) {
            whole.appendChild(mk('div', '', result));
        }

        if (opts.open) {
            var link = kit.button('btn btn-sm btn-link', tr('fl_flag_open'));

            link.setAttribute('data-refocus', key + '-open');
            link.addEventListener('click', function () {
                opts.open(run);
            });
            whole.appendChild(link);
        }

        flag.appendChild(whole);

        return flag;
    }

    window.PGWsFlowFlag = { runFlag: runFlag, duration: flagDuration, folds: flagFolds };
})();
