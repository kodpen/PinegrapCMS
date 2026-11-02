<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Search and audits: one screen for the background engines that read the
 * whole site (search index, SEO scores, HTML structure, accessibility). Each
 * card says the last full run, whether the engine is running or due, how far
 * an unfinished pass is, what it could not read, its scheduled job switch,
 * and Run now, which drives the same pass with short requests
 * (search_audits_run.php; the index through update_search_index.php).
 * Managers and up, as the reports it links to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_audits.php');

$engines = pg_search_audits_engines();

// The site search reads the index only when it is set to advanced; the
// simple search reads the pages directly and has nothing to build.
if (!defined('SEARCH_TYPE') || (SEARCH_TYPE !== 'advanced')) {
    unset($engines['index']);
}

// ---- Card and switch requests (JSON) --------------------------------------

if (isset($_POST['action']) && is_string($_POST['action'])) {
    $username = pg_search_audits_json_gate();

    $key = (isset($_POST['engine']) && is_string($_POST['engine']) && isset($engines[$_POST['engine']])) ? $_POST['engine'] : '';

    if ($key === '') {
        pg_search_audits_refuse('engine', lang('Invalid request.'), 400);
    }

    if ($_POST['action'] === 'switch') {
        $on = !empty($_POST['on']);

        if (!pg_search_audits_switch($engines[$key]['job'], $on)) {
            pg_search_audits_refuse('upgrade', lang('Please run the software upgrade first.'), 409);
        }

        log_activity($on
            ? lang(array('string' => 'switched on the scheduled job {var:1}', 'vars' => array($engines[$key]['label'])))
            : lang(array('string' => 'switched off the scheduled job {var:1}', 'vars' => array($engines[$key]['label']))), $username);

        $selected_now = $on;
    } elseif ($_POST['action'] !== 'card') {
        pg_search_audits_refuse('action', lang('Invalid request.'), 400);
    }

    // After a switch, JOB_DISPATCH still holds the list this request started with.
    $state = pg_search_audits_engine_state($key, $engines[$key], pg_cron_run_rows(), pg_cron_health_last_runs(), isset($selected_now) ? $selected_now : null);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array(
        'ok'         => true,
        'html'       => pg_search_audits_card($state),
        'running'    => ($state['status']['state'] === 'running'),
        'unfinished' => (bool) $state['progress']['unfinished'],
    ), JSON_UNESCAPED_UNICODE);
    exit();
}

// ---- The screen ------------------------------------------------------------

$user = validate_user();
validate_area_access($user, 'manager');

$rows = pg_cron_run_rows();
$done = pg_cron_health_last_runs();
$output_cards = '';

foreach ($engines as $key => $engine) {
    $output_cards .= '<div class="col-12 lg:col-6 mb-7">' . pg_search_audits_card(pg_search_audits_engine_state($key, $engine, $rows, $done)) . '</div>';
}

$output_notes = '';

if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
    $output_notes .= '<p class="small fg-secondary">' . h(lang('Run Cron Jobs Automatically is off, so no engine runs on a schedule: switch it on in Site Settings › Updates and maintenance › Cron Jobs, or use Run now.')) . '</p>';
}

if (!isset($engines['index'])) {
    $output_notes .= '<p class="small fg-secondary">' . h(lang('The site search reads the pages directly, so there is no search index to build. Choose the advanced search in Site Settings to use one.')) . '</p>';
}

// Texts the script shows, in the panel language.
$script_texts = array(
    'working'  => lang('Working...'),
    'done'     => lang('Finished.'),
    'busy'     => lang('Another run of this pass is going. Try again when it has finished.'),
    'failed'   => lang('The request failed. Try again.'),
    'stopped'  => lang('Stopped after many requests; press Run now to carry on.'),
);

echo pg_page_shell(array(
        'title'               => lang('Search and audits'),
        'extra classes'       => 'page',
        'icon'                => 'page',
        'heading'             => lang('Search and audits'),
        'heading_description' => lang('The background passes that read the whole site: what each one did last, how far it is now, what it could not read, and whether it runs on a schedule.'),
        'breadcrumb'          => array(
            array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => lang('Search and audits')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $output_notes . '
            <div class="row mt-5" id="search_audits" data-pg-token="' . h((string) ($_SESSION['software']['token'] ?? '')) . '" data-pg-texts="' . h(json_encode($script_texts, JSON_UNESCAPED_UNICODE)) . '">
                ' . $output_cards . '
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var root = document.getElementById("search_audits");
    if (!root) { return; }
    var token = root.getAttribute("data-pg-token");
    var texts = JSON.parse(root.getAttribute("data-pg-texts"));
    // A pass that never ends must not keep the screen asking for ever.
    var maxRequests = 500;

    function post(url, data) {
        var body = new FormData();
        body.append("token", token);
        Object.keys(data).forEach(function (name) { body.append(name, data[name]); });
        return fetch(url, { method: "POST", body: body, credentials: "same-origin" }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok || !json.ok) { throw new Error(json.message || texts.failed); }
                return json;
            });
        });
    }

    function card(key) {
        return root.querySelector("[data-pg-audit-card=\"" + key + "\"]");
    }

    function note(key, text) {
        var el = card(key) && card(key).querySelector("[data-pg-audit-note]");
        if (el) { el.textContent = text; }
    }

    // Draws the card again; keeps the note the run left.
    function redraw(key, json) {
        var old = card(key);
        if (!old) { return; }
        var kept = old.querySelector("[data-pg-audit-note]").textContent;
        var holder = document.createElement("div");
        holder.innerHTML = json.html;
        old.replaceWith(holder.firstElementChild);
        note(key, kept);
    }

    function refresh(key) {
        return post("search_audits.php", { action: "card", engine: key }).then(function (json) {
            redraw(key, json);
            return json;
        });
    }

    // One step of the pass: the index through its own script, the others
    // through the run endpoint. Resolves to true when the pass is done.
    function step(key, first) {
        if (key === "index") {
            return refresh(key).then(function (before) {
                if (before.running) { throw new Error(texts.busy); }
                return fetch("update_search_index.php?continue=1", { credentials: "same-origin" });
            }).then(function () {
                return refresh(key);
            }).then(function (after) {
                return !after.unfinished;
            });
        }
        return post("search_audits_run.php", { engine: key, start: first ? "1" : "0" }).then(function (json) {
            if (json.message) { note(key, json.message); }
            return refresh(key).then(function () { return json.done; });
        });
    }

    function run(key) {
        var count = 0;
        var button = card(key).querySelector("[data-pg-audit-run]");
        button.disabled = true;
        note(key, texts.working);

        function next(first) {
            return step(key, first).then(function (done) {
                count++;
                if (done) { note(key, texts.done); return; }
                if (count >= maxRequests) { note(key, texts.stopped); return; }
                // The card was drawn again: keep its button busy.
                var current = card(key).querySelector("[data-pg-audit-run]");
                if (current) { current.disabled = true; }
                return next(false);
            });
        }

        next(true).catch(function (error) {
            note(key, error.message || texts.failed);
        }).then(function () {
            var current = card(key).querySelector("[data-pg-audit-run]");
            if (current) { current.disabled = false; }
        });
    }

    document.addEventListener("click", function (event) {
        var button = event.target.closest("[data-pg-audit-run]");
        if (button && root.contains(button)) { run(button.getAttribute("data-pg-audit-run")); }
    });

    document.addEventListener("change", function (event) {
        var input = event.target.closest("[data-pg-audit-switch]");
        if (!input || !root.contains(input)) { return; }
        var key = input.getAttribute("data-pg-audit-switch");
        input.disabled = true;
        post("search_audits.php", { action: "switch", engine: key, on: input.checked ? "1" : "" }).then(function (json) {
            redraw(key, json);
        }).catch(function (error) {
            input.checked = !input.checked;
            input.disabled = false;
            note(key, error.message || texts.failed);
        });
    });
})();
</script>
' . output_footer();
